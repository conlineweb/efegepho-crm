<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['login']) || $_SESSION['login'] === false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$contactFormId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$tablaOrigen   = isset($_GET['tabla']) ? trim((string) $_GET['tabla']) : '';
$origId        = isset($_GET['orig_id']) ? (int) $_GET['orig_id'] : 0;

if ($contactFormId <= 0 && ($tablaOrigen === '' || $origId <= 0)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/calendario_estatus_historial_helper.php';

$result = obtenerTrazabilidadLead($conn, [
    'contact_form_id' => $contactFormId,
    'tabla_origen'    => $tablaOrigen,
    'orig_id'         => $origId,
]);
$conn->close();

if (empty($result['success'])) {
    http_response_code(404);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
