<?php
/**
 * Base social platform publisher.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class DG_Social_Platform {

    /** @var string */
    protected $key;

    /** @var array<string,mixed> */
    protected $connection;

    /** @var array<string,mixed> */
    protected $definition;

    /** @param array<string,mixed> $connection */
    public function __construct($key, array $connection, array $definition) {
        $this->key = $key;
        $this->connection = $connection;
        $this->definition = $definition;
    }

    /**
     * @param object $post Social post row.
     * @return array{success:bool,message:string,external_id?:string,url?:string}
     */
    abstract public function publish($post);

    /** @return array{success:bool,message:string} */
    public function test_connection() {
        if (empty($this->connection['access_token'])) {
            return ['success' => false, 'message' => 'Not connected.'];
        }
        return ['success' => true, 'message' => 'Connected as ' . ($this->connection['account_name'] ?? 'unknown')];
    }

    /** @return array{body:array<string,mixed>|string,headers:array<string,string>} */
    protected function api_request($url, $method = 'POST', $body = [], $headers = []) {
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => array_merge([
                'Content-Type' => 'application/json',
            ], $headers),
        ];

        if ($method === 'GET') {
            if (!empty($body)) {
                $url = add_query_arg($body, $url);
            }
        } else {
            $args['body'] = is_array($body) ? wp_json_encode($body) : $body;
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message(), 'code' => 0, 'data' => []];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = ['raw' => $raw];
        }

        return ['code' => $code, 'data' => $data, 'error' => ''];
    }

    /** @return array{success:bool,message:string,external_id?:string,url?:string} */
    protected function fail($message) {
        return ['success' => false, 'message' => $message];
    }

    /** @return array{success:bool,message:string,external_id?:string,url?:string} */
    protected function ok($message, $external_id = '', $url = '') {
        return array_filter([
            'success' => true,
            'message' => $message,
            'external_id' => $external_id,
            'url' => $url,
        ]);
    }
}
