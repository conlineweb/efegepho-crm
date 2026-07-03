<?php
include 'conn.php';
require_once __DIR__ . '/evento_wp_post_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function wpCreateContactFormJsonResponse($statusCode, array $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

if ($eventId <= 0) {
    wpCreateContactFormJsonResponse(400, ['success' => false, 'message' => 'Evento inválido.']);
}

try {
    $result = wpEventEnsureContactFormForEvent($conn, $eventId);

    wpCreateContactFormJsonResponse(200, [
        'success' => true,
        'created' => !empty($result['created']),
        'message' => $result['message'] ?? 'Listo.',
        'contact_form_id' => intval($result['contact_form_id'] ?? 0),
        'event_id' => $eventId,
    ]);
} catch (Exception $e) {
    wpCreateContactFormJsonResponse(500, [
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
