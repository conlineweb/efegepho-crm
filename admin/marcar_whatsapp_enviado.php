<?php
header('Content-Type: application/json');
include 'conn.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$id = isset($_POST['id']) ? trim($_POST['id']) : '';
$tabla = isset($_POST['tabla_origen']) ? trim($_POST['tabla_origen']) : '';

if ($id === '' || $tabla === '') {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

// Validar que la tabla sea una tabla de leads conocida
$stmt = $conn->prepare('SELECT 1 FROM tablas_leads WHERE nombre = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
    exit;
}
$stmt->bind_param('s', $tabla);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Tabla de leads no válida']);
    exit;
}

// Verificar si la columna whatsapp_enviado (y opcionalmente fecha_envio_whatsapp) existe
$safeTable = str_replace('`', '', $tabla);
$safeTable = $conn->real_escape_string($safeTable);
$colRes = $conn->query("SHOW COLUMNS FROM `" . $safeTable . "` LIKE 'whatsapp_enviado'");
if (!$colRes || $colRes->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'La tabla no tiene el campo whatsapp_enviado']);
    exit;
}

// Optional: check if fecha_envio_whatsapp exists
$colRes2 = $conn->query("SHOW COLUMNS FROM `" . $safeTable . "` LIKE 'fecha_envio_whatsapp'");
$hasFechaCol = ($colRes2 && $colRes2->num_rows > 0);

// Ejecutar actualización
if ($hasFechaCol) {
    $sql = "UPDATE `" . $safeTable . "` SET whatsapp_enviado = 1, fecha_envio_whatsapp = NOW() WHERE id = ? LIMIT 1";
} else {
    $sql = "UPDATE `" . $safeTable . "` SET whatsapp_enviado = 1 WHERE id = ? LIMIT 1";
}
$stmtUp = $conn->prepare($sql);
if (!$stmtUp) {
    echo json_encode(['success' => false, 'message' => 'Error preparando la consulta']);
    exit;
}
$intId = intval($id);
$stmtUp->bind_param('i', $intId);

if ($stmtUp->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el lead']);
}

?>