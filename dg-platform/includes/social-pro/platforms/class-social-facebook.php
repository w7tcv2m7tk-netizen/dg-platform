<?php
/**
 * Facebook Page publisher.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Social_Facebook extends DG_Social_Platform {

    public function publish($post) {
        $token = $this->connection['page_access_token'] ?? $this->connection['access_token'] ?? '';
        $page_id = $this->connection['page_id'] ?? '';
        if ($token === '' || $page_id === '') {
            return $this->fail('Facebook Page not configured. Reconnect and select a Page.');
        }

        $message = trim(strip_tags($post->content));
        if ($post->link_url) {
            $message .= ($message !== '' ? "\n\n" : '') . $post->link_url;
        }

        $params = [
            'message' => $message,
            'access_token' => $token,
        ];

        if ($post->media_url) {
            $params['link'] = $post->media_url;
        } elseif ($post->link_url) {
            $params['link'] = $post->link_url;
        }

        $url = 'https://graph.facebook.com/v19.0/' . rawurlencode($page_id) . '/feed';
        $response = wp_remote_post($url, [
            'timeout' => 30,
            'body' => $params,
        ]);

        if (is_wp_error($response)) {
            return $this->fail($response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($data['error']['message'])) {
            return $this->fail($data['error']['message']);
        }
        if (empty($data['id'])) {
            return $this->fail('Facebook did not return a post ID.');
        }

        return $this->ok('Published to Facebook.', $data['id'], 'https://facebook.com/' . $data['id']);
    }
}
