<?php
include 'conn.php';
header('Content-Type: application/json');
ini_set('display_errors', 1); error_reporting(E_ALL);

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $stmt = $conn->prepare('DELETE FROM coordinadores_wp WHERE id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) throw new Exception('Error al eliminar coordinador: ' . $stmt->error);
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Coordinador no encontrado']);
    }
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>