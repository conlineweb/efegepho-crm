<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

include 'conn.php';
require_once __DIR__ . '/lib_marketing_template_email.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$userid = $_SESSION['uid'] ?? null;

// Validate input
if (!isset($_POST['template_id']) || !isset($_POST['tabla_origen']) || !isset($_POST['lead_id'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros.']);
    exit;
}

$templateId = intval($_POST['template_id']);
$tablaOrigen = $_POST['tabla_origen'];
$leadId = intval($_POST['lead_id']);

$result = send_marketing_template_email($conn, $templateId, $tablaOrigen, $leadId, $userid ? intval($userid) : null);

if (!empty($result['success'])) {
    echo json_encode($result);
} else {
    http_response_code(500);
    echo json_encode($result);
}

$conn->close();
