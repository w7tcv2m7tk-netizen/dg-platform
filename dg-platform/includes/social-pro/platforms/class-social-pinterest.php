<?php
/**
 * Pinterest pin publisher.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Social_Pinterest extends DG_Social_Platform {

    public function publish($post) {
        $token = $this->connection['access_token'] ?? '';
        $board_id = $this->connection['board_id'] ?? '';
        if ($token === '' || $board_id === '') {
            return $this->fail('Pinterest not connected or board ID missing.');
        }

        if (!$post->media_url) {
            return $this->fail('Pinterest requires an image URL.');
        }

        $title = $post->title !== '' ? $post->title : wp_trim_words(strip_tags($post->content), 8, '');
        $description = trim(strip_tags($post->content));
        if ($post->link_url) {
            $link = $post->link_url;
        } else {
            $link = DG_Social_Pro_Settings::get('default_link', home_url('/'));
        }

        $body = [
            'board_id' => $board_id,
            'title' => substr($title, 0, 100),
            'description' => substr($description, 0, 500),
            'link' => $link,
            'media_source' => [
                'source_type' => 'image_url',
                'url' => $post->media_url,
            ],
        ];

        $response = wp_remote_post('https://api.pinterest.com/v5/pins', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return $this->fail($response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $msg = $data['message'] ?? ($data['error']['message'] ?? 'Pinterest API error (' . $code . ').');
            return $this->fail($msg);
        }

        return $this->ok('Published to Pinterest.', $data['id'] ?? '', $data['link'] ?? '');
    }
}
