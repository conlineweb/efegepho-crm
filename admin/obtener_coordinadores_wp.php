<?php
include 'conn.php';
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$idWp = isset($_GET['id_wp']) ? intval($_GET['id_wp']) : 0;

if ($idWp <= 0) {
    echo json_encode(['success' => false, 'message' => 'Wedding Planner inválido', 'coordinadores' => []]);
    exit;
}

try {
    $stmt = $conn->prepare('SELECT id, nombre, telefono, correo, planner_reason, how_long_known_us, first_contact_channel, campaign_name, platform, how_did_you_meet, fecha_creacion FROM coordinadores_wp WHERE id_wp = ? ORDER BY fecha_creacion DESC, id DESC');
    $stmt->bind_param('i', $idWp);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $coordinadores = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $coordinadores[] = [
                'id' => intval($row['id']),
                'nombre' => $row['nombre'] ?? '',
                'telefono' => $row['telefono'] ?? '',
                'correo' => $row['correo'] ?? '',
                'planner_reason' => $row['planner_reason'] ?? '',
                'how_long_known_us' => $row['how_long_known_us'] ?? '',
                'first_contact_channel' => $row['first_contact_channel'] ?? '',
                'campaign_name' => $row['campaign_name'] ?? '',
                'platform' => $row['platform'] ?? '',
                'how_did_you_meet' => $row['how_did_you_meet'] ?? '',
                'fecha_creacion' => $row['fecha_creacion'] ?? ''
            ];
        }
    }

    echo json_encode(['success' => true, 'coordinadores' => $coordinadores]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'coordinadores' => []]);
    exit;
}
?>