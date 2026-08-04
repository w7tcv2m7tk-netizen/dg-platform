<?php
/**
 * X (Twitter) publisher.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Social_X extends DG_Social_Platform {

    public function publish($post) {
        $token = $this->connection['access_token'] ?? '';
        if ($token === '') {
            return $this->fail('X not connected. Complete OAuth in Connections.');
        }

        $text = trim(strip_tags($post->content));
        if ($post->link_url) {
            $combined = $text . ($text !== '' ? ' ' : '') . $post->link_url;
            if (strlen($combined) <= 280) {
                $text = $combined;
            }
        }

        if (strlen($text) > 280) {
            $text = substr($text, 0, 277) . '…';
        }

        $body = ['text' => $text];

        $response = wp_remote_post('https://api.twitter.com/2/tweets', [
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

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($data['errors'][0]['message'])) {
            return $this->fail($data['errors'][0]['message']);
        }
        if (!empty($data['detail'])) {
            return $this->fail($data['detail']);
        }

        $tweet_id = $data['data']['id'] ?? '';
        $username = $this->connection['account_name'] ?? 'i';
        $url = $tweet_id ? 'https://x.com/' . rawurlencode($username) . '/status/' . $tweet_id : '';

        return $this->ok('Published to X.', $tweet_id, $url);
    }
}
