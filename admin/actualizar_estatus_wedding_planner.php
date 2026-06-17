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

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$estatus = isset($_POST['estatus']) ? intval($_POST['estatus']) : -1;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

if (!in_array($estatus, [1, 2], true)) {
    echo json_encode(['success' => false, 'message' => 'Estatus inválido']);
    exit;
}

try {
    $conn->begin_transaction();

    $stmtCheck = $conn->prepare('SELECT id FROM wedding_planners WHERE id = ? AND estatus = 0 LIMIT 1');
    if (!$stmtCheck) {
        throw new Exception('Error al preparar validación: ' . $conn->error);
    }

    $stmtCheck->bind_param('i', $id);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    $stmtCheck->close();

    if (!$resCheck || $resCheck->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Wedding Planner pendiente no encontrado o ya procesado']);
        exit;
    }

    $stmtUpdate = $conn->prepare('UPDATE wedding_planners SET estatus = ? WHERE id = ? LIMIT 1');
    if (!$stmtUpdate) {
        throw new Exception('Error al preparar actualización: ' . $conn->error);
    }

    $stmtUpdate->bind_param('ii', $estatus, $id);
    if (!$stmtUpdate->execute()) {
        throw new Exception('Error al actualizar estatus: ' . $stmtUpdate->error);
    }

    $affected = $stmtUpdate->affected_rows;
    $stmtUpdate->close();

    if ($affected <= 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'No se realizaron cambios']);
        exit;
    }

    $updatedCoordinadores = 0;
    $resCol = $conn->query("SHOW COLUMNS FROM coordinadores_wp LIKE 'estatus'");
    if ($resCol && $resCol->num_rows > 0) {
        $stmtUpdateCoord = $conn->prepare('UPDATE coordinadores_wp SET estatus = ? WHERE id_wp = ?');
        if (!$stmtUpdateCoord) {
            throw new Exception('Error al preparar actualización de coordinadores: ' . $conn->error);
        }

        $stmtUpdateCoord->bind_param('ii', $estatus, $id);
        if (!$stmtUpdateCoord->execute()) {
            throw new Exception('Error al actualizar coordinadores: ' . $stmtUpdateCoord->error);
        }

        $updatedCoordinadores = $stmtUpdateCoord->affected_rows;
        $stmtUpdateCoord->close();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $estatus === 1 ? 'Wedding Planner enviado a Post' : 'Wedding Planner descartado',
        'id' => $id,
        'estatus' => $estatus,
        'coordinadores_actualizados' => max(0, intval($updatedCoordinadores))
    ]);
    exit;
} catch (Exception $e) {
    if ($conn && $conn->errno !== null) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>