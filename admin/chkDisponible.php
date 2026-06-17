<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'conn.php';

$idusu = isset($_POST['idusu']) ? (int)$_POST['idusu'] : 0;

if (isset($_POST['listar_dias']) && $idusu > 0) {
    header('Content-Type: application/json; charset=utf-8');
    $datesWithHours = [];
    $stmt = $conn->prepare('SELECT dia, horarios FROM atencion WHERE idusu = ?');
    $stmt->bind_param('i', $idusu);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $day = trim((string)($row['dia'] ?? ''));
        $hours = json_decode($row['horarios'] ?? '[]', true);
        if ($day !== '' && is_array($hours) && count($hours) > 0) {
            $datesWithHours[] = $day;
        }
    }
    $stmt->close();
    echo json_encode(['success' => true, 'dates' => array_values(array_unique($datesWithHours))]);
    $conn->close();
    exit;
}

if (isset($_POST['dia']) && $idusu > 0) {
    $stmt = $conn->prepare("SELECT horarios, idusu FROM `atencion` WHERE dia = ? AND idusu = ?");
    $stmt->bind_param('si', $_POST['dia'], $idusu);
    $stmt->execute();
    $res = $stmt->get_result();
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
}

if (isset($_POST['fecha']) && $idusu > 0) {
    $excludeId = isset($_POST['exclude_id']) ? (int)$_POST['exclude_id'] : 0;
    $sql = 'SELECT id, hora, idusu FROM calendario WHERE fecha = ? AND idusu = ? AND eliminado = 0';
    if ($excludeId > 0) {
        $sql .= ' AND id <> ?';
    }
    $sql .= ' ORDER BY hora ASC';

    $stmt = $conn->prepare($sql);
    if ($excludeId > 0) {
        $stmt->bind_param('sii', $_POST['fecha'], $idusu, $excludeId);
    } else {
        $stmt->bind_param('si', $_POST['fecha'], $idusu);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    $stmt->close();
}

if (isset($_POST['vendedores'])) {
    $stmt = $conn->prepare("SELECT * FROM `usuarios` WHERE id = ?");
    $stmt->bind_param('i', $idusu); 
    $stmt->execute();
    $res = $stmt->get_result();
    echo json_encode($res->fetch_all(MYSQLI_ASSOC)); 
}
?>
