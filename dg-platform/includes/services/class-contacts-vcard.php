<?php
/**
 * vCard (.vcf) import for core Contacts.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Contacts_Vcard {

    /** @return array<int,array<string,string>>|WP_Error */
    public static function parse_file($path) {
        if (!is_readable($path)) {
            return new WP_Error('unreadable', 'Cannot read uploaded file.');
        }

        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            return new WP_Error('empty', 'vCard file is empty.');
        }

        return self::parse_string($content);
    }

    /** @return array<int,array<string,string>> */
    public static function parse_string($content) {
        $cards = [];
        foreach (self::split_vcards($content) as $block) {
            $parsed = self::parse_block($block);
            if (!empty($parsed['email']) || !empty($parsed['first_name']) || !empty($parsed['last_name']) || !empty($parsed['phone'])) {
                $cards[] = $parsed;
            }
        }

        return $cards;
    }

    /**
     * @param array<int,array<string,string>> $vcards
     * @return array{imported:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public static function import_vcards(array $vcards) {
        $stats = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($vcards as $index => $vcard) {
            $result = self::import_one($vcard, $index + 1);
            if ($result === 'imported') {
                $stats['imported']++;
            } elseif ($result === 'updated') {
                $stats['updated']++;
            } elseif (is_string($result) && strpos($result, 'skip:') === 0) {
                $stats['skipped']++;
                $stats['errors'][] = 'Card ' . ($index + 1) . ': ' . substr($result, 5);
            }
        }

        return $stats;
    }

    /** @param array<int,string> $block */
    private static function parse_block(array $block) {
        $data = [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'position' => '',
            'organisation' => '',
            'notes' => '',
        ];

        $emails = [];
        $phones = [];

        foreach ($block as $line) {
            $property = self::parse_line($line);
            if (!$property) {
                continue;
            }

            [$name, $value] = $property;
            $value = trim($value);
            if ($value === '') {
                continue;
            }

            switch ($name) {
                case 'N':
                    $parts = explode(';', $value);
                    $data['last_name'] = self::clean_text($parts[0] ?? '');
                    $data['first_name'] = self::clean_text($parts[1] ?? '');
                    break;
                case 'FN':
                    if ($data['first_name'] === '' && $data['last_name'] === '') {
                        self::apply_formatted_name($data, $value);
                    }
                    break;
                case 'EMAIL':
                    $emails[] = sanitize_email($value);
                    break;
                case 'TEL':
                    $phones[] = self::clean_phone($value);
                    break;
                case 'TITLE':
                    $data['position'] = self::clean_text($value);
                    break;
                case 'ORG':
                    $org_parts = explode(';', $value);
                    $data['organisation'] = self::clean_text($org_parts[0] ?? $value);
                    break;
                case 'NOTE':
                    $data['notes'] = self::clean_text($value);
                    break;
            }
        }

        $emails = array_values(array_filter(array_unique($emails)));
        $phones = array_values(array_filter(array_unique($phones)));

        if (!empty($emails[0])) {
            $data['email'] = $emails[0];
        }
        if (!empty($phones[0])) {
            $data['phone'] = $phones[0];
        }

        return $data;
    }

    /** @param array<string,string> $vcard */
    private static function import_one(array $vcard, $card_num) {
        $email = sanitize_email($vcard['email'] ?? '');
        if ($email === '') {
            return 'skip:No email address — add an email in the vCard or import via CSV.';
        }

        $data = [
            'first_name' => sanitize_text_field($vcard['first_name'] ?? ''),
            'last_name' => sanitize_text_field($vcard['last_name'] ?? ''),
            'email' => $email,
            'phone' => sanitize_text_field($vcard['phone'] ?? ''),
            'position' => sanitize_text_field($vcard['position'] ?? ''),
            'notes' => sanitize_textarea_field($vcard['notes'] ?? ''),
            'source' => 'vcard_import',
            'status' => 'active',
        ];

        if ($data['first_name'] === '' && $data['last_name'] === '') {
            $local = strstr($email, '@', true);
            $data['first_name'] = $local ? sanitize_text_field(str_replace(['.', '_'], ' ', $local)) : 'Imported';
        }

        $existing = DG_Contacts::get_by_email($email);
        if ($existing) {
            DG_Contacts::update($existing->id, $data);
            $contact_id = (int) $existing->id;
            $result = 'updated';
        } else {
            $contact_id = DG_Contacts::create($data);
            if (is_wp_error($contact_id)) {
                return 'skip:' . $contact_id->get_error_message();
            }
            $result = 'imported';
        }

        if (!empty($vcard['organisation']) && class_exists('DG_Organisations')) {
            self::maybe_link_organisation($contact_id, $vcard['organisation'], $email);
        }

        return $result;
    }

    private static function maybe_link_organisation($contact_id, $org_name, $email) {
        global $wpdb;

        $org_name = sanitize_text_field($org_name);
        if ($org_name === '') {
            return;
        }

        $table = DG_Organisations::table();
        $org = $wpdb->get_row($wpdb->prepare(
            'SELECT id FROM ' . $table . ' WHERE name = %s LIMIT 1',
            $org_name
        ));

        if (!$org) {
            $org_id = DG_Organisations::create([
                'name' => $org_name,
                'email' => $email,
                'source' => 'vcard_import',
            ]);
        } else {
            $org_id = (int) $org->id;
        }

        if ($org_id) {
            DG_Contacts::update($contact_id, ['organisation_id' => $org_id]);
        }
    }

    /** @return array<int,array<int,string>> */
    private static function split_vcards($content) {
        $blocks = [];
        $current = [];
        $in_card = false;

        foreach (self::unfold_lines($content) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (stripos($trimmed, 'BEGIN:VCARD') === 0) {
                $in_card = true;
                $current = [];
                continue;
            }

            if (stripos($trimmed, 'END:VCARD') === 0) {
                if ($current) {
                    $blocks[] = $current;
                }
                $in_card = false;
                $current = [];
                continue;
            }

            if ($in_card) {
                $current[] = $trimmed;
            }
        }

        return $blocks;
    }

    /** @return array<int,string> */
    private static function unfold_lines($content) {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);
        $out = [];

        foreach ($lines as $line) {
            if ($line !== '' && isset($line[0]) && ($line[0] === ' ' || $line[0] === "\t") && $out) {
                $out[count($out) - 1] .= substr($line, 1);
            } else {
                $out[] = $line;
            }
        }

        return $out;
    }

    /** @return array{0:string,1:string}|null */
    private static function parse_line($line) {
        $pos = strpos($line, ':');
        if ($pos === false) {
            return null;
        }

        $left = substr($line, 0, $pos);
        $value = substr($line, $pos + 1);
        $name = strtoupper(strtok($left, ';'));
        if (strpos($name, '.') !== false) {
            $name_parts = explode('.', $name);
            $name = strtoupper(end($name_parts));
        }

        if (stripos($left, 'ENCODING=QUOTED-PRINTABLE') !== false) {
            $value = self::decode_quoted_printable($value);
        }

        if (preg_match('/CHARSET=([^;]+)/i', $left, $m)) {
            $charset = strtoupper($m[1]);
            if ($charset !== 'UTF-8' && function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($value, 'UTF-8', $charset);
                if ($converted !== false) {
                    $value = $converted;
                }
            }
        }

        return [$name, $value];
    }

    private static function decode_quoted_printable($value) {
        $value = preg_replace("/=\r?\n/", '', $value);
        return quoted_printable_decode($value);
    }

    /** @param array<string,string> $data */
    private static function apply_formatted_name(array &$data, $fn) {
        $fn = self::clean_text($fn);
        if ($fn === '') {
            return;
        }

        if (strpos($fn, ',') !== false) {
            $parts = array_map('trim', explode(',', $fn, 2));
            $data['last_name'] = self::clean_text($parts[0] ?? '');
            $data['first_name'] = self::clean_text($parts[1] ?? '');
            return;
        }

        $parts = preg_split('/\s+/', $fn);
        if (count($parts) === 1) {
            $data['first_name'] = $parts[0];
            return;
        }

        $data['first_name'] = array_shift($parts);
        $data['last_name'] = implode(' ', $parts);
    }

    private static function clean_text($value) {
        return sanitize_text_field(str_replace('\\,', ',', str_replace('\\n', "\n", $value)));
    }

    private static function clean_phone($value) {
        $value = str_replace('\\,', ',', $value);
        return sanitize_text_field(preg_replace('/[^\d+\s().-]/', '', $value));
    }
}
