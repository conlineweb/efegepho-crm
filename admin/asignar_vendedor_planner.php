<?php
include 'conn.php';
require_once __DIR__ . '/usuario_roles_helper.php';

header('Content-Type: application/json; charset=utf-8');

$plannerId = isset($_POST['planner_id']) ? intval($_POST['planner_id']) : 0;
$idVendedor = isset($_POST['id_vendedor_asignado']) ? intval($_POST['id_vendedor_asignado']) : 0;

if ($plannerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Planner inválido']);
    exit;
}

if ($idVendedor > 0) {
    $stmt = $conn->prepare('SELECT id, nombre, apepat, tipoUsu FROM usuarios WHERE id = ? LIMIT 1');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error al validar vendedora']);
        exit;
    }
    $stmt->bind_param('i', $idVendedor);
    $stmt->execute();
    $resu = $stmt->get_result();
    $stmt->close();

    if (!$resu || $resu->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Vendedora no encontrada']);
        exit;
    }

    $rowu = $resu->fetch_assoc();
    if (!usuarioTipoPuedeAsignarSesionWp($rowu['tipoUsu'] ?? -1)) {
        echo json_encode(['success' => false, 'message' => 'El usuario seleccionado no es una vendedora válida']);
        exit;
    }

    $vendedorNombre = trim((string) ($rowu['nombre'] ?? ''));
    $vendedorApepat = trim((string) ($rowu['apepat'] ?? ''));
} else {
    $vendedorNombre = '';
    $vendedorApepat = '';
}

$stmtCheck = $conn->prepare('SELECT id FROM wedding_planners WHERE id = ? LIMIT 1');
if (!$stmtCheck) {
    echo json_encode(['success' => false, 'message' => 'Error al validar planner']);
    exit;
}
$stmtCheck->bind_param('i', $plannerId);
$stmtCheck->execute();
$exists = $stmtCheck->get_result()->num_rows > 0;
$stmtCheck->close();

if (!$exists) {
    echo json_encode(['success' => false, 'message' => 'Planner no encontrado']);
    exit;
}

$stmt = $conn->prepare('UPDATE wedding_planners SET id_vendedor_asignado = ? WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error al preparar actualización']);
    exit;
}
$stmt->bind_param('ii', $idVendedor, $plannerId);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'No se pudo asignar: ' . $stmt->error]);
    $stmt->close();
    exit;
}
$stmt->close();

echo json_encode([
    'success' => true,
    'message' => $idVendedor > 0 ? 'Vendedora asignada' : 'Vendedora removida',
    'id_vendedor_asignado' => $idVendedor,
    'vendedor_nombre' => $vendedorNombre,
    'vendedor_apepat' => $vendedorApepat,
]);
