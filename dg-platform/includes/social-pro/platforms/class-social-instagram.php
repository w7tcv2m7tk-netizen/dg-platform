<?php
/**
 * Instagram publisher (via Meta Graph API).
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Social_Instagram extends DG_Social_Platform {

    public function publish($post) {
        $token = $this->connection['access_token'] ?? '';
        $ig_id = $this->connection['instagram_account_id'] ?? '';
        if ($token === '' || $ig_id === '') {
            return $this->fail('Instagram Business account not linked. Connect Facebook and link your IG account.');
        }

        if (!$post->media_url) {
            return $this->fail('Instagram requires an image URL. Add a media URL to your post.');
        }

        $caption = trim(strip_tags($post->content));
        if (strlen($caption) > 2200) {
            $caption = substr($caption, 0, 2197) . '…';
        }

        $create_url = 'https://graph.facebook.com/v19.0/' . rawurlencode($ig_id) . '/media';
        $create = wp_remote_post($create_url, [
            'timeout' => 30,
            'body' => [
                'image_url' => $post->media_url,
                'caption' => $caption,
                'access_token' => $token,
            ],
        ]);

        if (is_wp_error($create)) {
            return $this->fail($create->get_error_message());
        }

        $create_data = json_decode(wp_remote_retrieve_body($create), true);
        if (!empty($create_data['error']['message'])) {
            return $this->fail($create_data['error']['message']);
        }
        if (empty($create_data['id'])) {
            return $this->fail('Instagram media container creation failed.');
        }

        $publish_url = 'https://graph.facebook.com/v19.0/' . rawurlencode($ig_id) . '/media_publish';
        $publish = wp_remote_post($publish_url, [
            'timeout' => 30,
            'body' => [
                'creation_id' => $create_data['id'],
                'access_token' => $token,
            ],
        ]);

        if (is_wp_error($publish)) {
            return $this->fail($publish->get_error_message());
        }

        $publish_data = json_decode(wp_remote_retrieve_body($publish), true);
        if (!empty($publish_data['error']['message'])) {
            return $this->fail($publish_data['error']['message']);
        }

        return $this->ok('Published to Instagram.', $publish_data['id'] ?? '', '');
    }
}
