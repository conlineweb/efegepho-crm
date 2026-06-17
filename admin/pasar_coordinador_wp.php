<?php
include 'conn.php';
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$idCoordinador = isset($_POST['id_coordinador']) ? intval($_POST['id_coordinador']) : 0;
$idWpOrigen = isset($_POST['id_wp_origen']) ? intval($_POST['id_wp_origen']) : 0;
$idWpDestino = isset($_POST['id_wp_destino']) ? intval($_POST['id_wp_destino']) : 0;

if ($idCoordinador <= 0 || $idWpOrigen <= 0 || $idWpDestino <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos para mover coordinador']);
    exit;
}

if ($idWpOrigen === $idWpDestino) {
    echo json_encode(['success' => false, 'message' => 'El Wedding Planner destino debe ser diferente al origen']);
    exit;
}

try {
    $conn->begin_transaction();

    $stmtDestino = $conn->prepare('SELECT id FROM wedding_planners WHERE id = ? AND estatus = 1 LIMIT 1');
    if (!$stmtDestino) {
        throw new Exception('Error al preparar validación de destino: ' . $conn->error);
    }
    $stmtDestino->bind_param('i', $idWpDestino);
    $stmtDestino->execute();
    $resDestino = $stmtDestino->get_result();
    $stmtDestino->close();

    if (!$resDestino || $resDestino->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Wedding Planner destino no válido o inactivo']);
        exit;
    }

    $stmtCoord = $conn->prepare('SELECT id, id_wp FROM coordinadores_wp WHERE id = ? LIMIT 1');
    if (!$stmtCoord) {
        throw new Exception('Error al preparar validación de coordinador: ' . $conn->error);
    }
    $stmtCoord->bind_param('i', $idCoordinador);
    $stmtCoord->execute();
    $resCoord = $stmtCoord->get_result();
    $stmtCoord->close();

    if (!$resCoord || $resCoord->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Coordinador no encontrado']);
        exit;
    }

    $coord = $resCoord->fetch_assoc();
    $idWpActual = intval($coord['id_wp']);

    if ($idWpActual !== $idWpOrigen) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'El coordinador ya no pertenece al Wedding Planner seleccionado']);
        exit;
    }

    $stmtMove = $conn->prepare('UPDATE coordinadores_wp SET id_wp = ? WHERE id = ? AND id_wp = ? LIMIT 1');
    if (!$stmtMove) {
        throw new Exception('Error al preparar movimiento: ' . $conn->error);
    }
    $stmtMove->bind_param('iii', $idWpDestino, $idCoordinador, $idWpOrigen);
    if (!$stmtMove->execute()) {
        throw new Exception('Error al mover coordinador: ' . $stmtMove->error);
    }
    $affected = $stmtMove->affected_rows;
    $stmtMove->close();

    if ($affected <= 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'No se realizaron cambios']);
        exit;
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'El coordinador fue reasignado correctamente',
        'id_coordinador' => $idCoordinador,
        'id_wp_origen' => $idWpOrigen,
        'id_wp_destino' => $idWpDestino
    ]);
    exit;
} catch (Exception $e) {
    if ($conn) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>
