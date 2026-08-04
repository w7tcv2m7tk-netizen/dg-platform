<?php
/**
 * LinkedIn publisher.
 *
 * @package DG_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class DG_Social_LinkedIn extends DG_Social_Platform {

    public function publish($post) {
        $token = $this->connection['access_token'] ?? '';
        $author = $this->connection['author_urn'] ?? '';
        if ($token === '' || $author === '') {
            return $this->fail('LinkedIn not connected. Complete OAuth and set author URN.');
        }

        $text = trim(strip_tags($post->content));
        if ($post->link_url) {
            $text .= ($text !== '' ? "\n\n" : '') . $post->link_url;
        }

        $body = [
            'author' => $author,
            'commentary' => $text,
            'visibility' => 'PUBLIC',
            'lifecycleState' => 'PUBLISHED',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
        ];

        $response = wp_remote_post('https://api.linkedin.com/rest/posts', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'LinkedIn-Version' => '202401',
                'X-Restli-Protocol-Version' => '2.0.0',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return $this->fail($response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $msg = $data['message'] ?? ($data['error_description'] ?? 'LinkedIn API error (' . $code . ').');
            return $this->fail($msg);
        }

        $post_id = wp_remote_retrieve_header($response, 'x-restli-id');
        return $this->ok('Published to LinkedIn.', $post_id ?: '', '');
    }
}
