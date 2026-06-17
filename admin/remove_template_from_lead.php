<?php
header('Content-Type: application/json; charset=utf-8');
include 'conn.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$userid = $_SESSION['uid'] ?? null;

$tplRaw = isset($_POST['template_id']) ? trim((string)$_POST['template_id']) : '';
$tabla = isset($_POST['tabla_origen']) ? trim((string)$_POST['tabla_origen']) : '';
$leadRaw = isset($_POST['lead_id']) ? trim((string)$_POST['lead_id']) : '';

if ($tplRaw === '' || $tabla === '' || $leadRaw === '' || !ctype_digit($tplRaw) || !ctype_digit($leadRaw)) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit;
}
$templateId = intval($tplRaw);
$leadId = intval($leadRaw);

// safe check for table
$check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tabla) . "'");
if (!($check && $check->num_rows > 0)) {
    echo json_encode(['success' => false, 'message' => 'Tabla inválida']);
    exit;
}

// delete the queue entry regardless of status (pending, sent, error, etc.)
// this also prevents future sends if a scheduled entry exists
$del = $conn->prepare("DELETE FROM marketing_template_queue WHERE tabla_origen = ? AND lead_id = ? AND template_id = ? LIMIT 1");
if (!$del) {
    echo json_encode(['success' => false, 'message' => 'Error preparando eliminación']);
    exit;
}
$del->bind_param('sii', $tabla, $leadId, $templateId);
$del->execute();
$affected = $del->affected_rows;
$del->close();

if ($affected > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No se encontró la asignación de plantilla para este lead']);
}
$conn->close();
