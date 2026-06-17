<?php
include 'conn.php';

header('Content-Type: application/json');

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$afianzado = isset($_POST['afianzado']) ? intval($_POST['afianzado']) : -1;

if ($id <= 0 || !in_array($afianzado, [0, 1, 2, 3], true)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$stmtCheck = $conn->prepare('SELECT id FROM wedding_planners WHERE id = ? LIMIT 1');
if (!$stmtCheck) {
    echo json_encode(['success' => false, 'message' => 'Error al validar planner']);
    exit;
}
$stmtCheck->bind_param('i', $id);
$stmtCheck->execute();
$exists = $stmtCheck->get_result()->num_rows > 0;
$stmtCheck->close();

if (!$exists) {
    echo json_encode(['success' => false, 'message' => 'Planner no encontrado']);
    exit;
}

$stmt = $conn->prepare("UPDATE `wedding_planners` SET `afianzado` = ? WHERE `id` = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . $conn->error]);
    exit;
}

$stmt->bind_param('ii', $afianzado, $id);
$stmt->execute();

if ($stmt->errno) {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $stmt->error]);
} else {
    $labels = [0 => 'No afianzado', 1 => 'Afianzado', 2 => 'En proceso', 3 => 'Nuevo'];
    echo json_encode([
        'success' => true,
        'afianzado' => $afianzado,
        'label' => $labels[$afianzado] ?? 'No afianzado',
    ]);
}

$stmt->close();
