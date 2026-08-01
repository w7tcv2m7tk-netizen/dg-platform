<?php
/**
 * Property Report bridge endpoint for legacy Oxygen / inc/ form posts.
 *
 * DEPLOY: Copy this file to your site root as inc/send-property-report.php
 * (same location as the original standalone handler). It loads WordPress and
 * delegates to DG Platform's property report handler.
 *
 * @package DG_Platform
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$wp_load = dirname(__FILE__) . '/../wp-load.php';
if (!file_exists($wp_load)) {
    $wp_load = dirname(__FILE__) . '/../../wp-load.php';
}
if (!file_exists($wp_load)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'WordPress not found. Check wp-load.php path.']);
    exit;
}

require_once $wp_load;

header('Content-Type: application/json');

if (!function_exists('dg_re_process_property_report_lead')) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'DG Platform property report handler is unavailable.']);
    exit;
}

$result = dg_re_process_property_report_lead($_POST);

if (!empty($result['success'])) {
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
    ]);
    exit;
}

http_response_code(empty($result['message']) ? 500 : 400);
echo json_encode([
    'success' => false,
    'message' => $result['message'] ?? 'Unable to process report request.',
]);
