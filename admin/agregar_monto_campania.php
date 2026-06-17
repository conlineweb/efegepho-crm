<?php
include 'conn.php';
// Manejar petición AJAX para agregar gasto por campaña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_campaign_cost') {
    header('Content-Type: application/json');
    $nombre = isset($_POST['campaign_name']) ? trim($_POST['campaign_name']) : '';
    $monto_camp = isset($_POST['monto']) ? trim($_POST['monto']) : '';
    $f_inicio = isset($_POST['fecha_inicio']) ? trim($_POST['fecha_inicio']) : '';
    $f_fin = isset($_POST['fecha_fin']) ? trim($_POST['fecha_fin']) : '';

    if ($nombre === '' || $monto_camp === '' || $f_inicio === '' || $f_fin === '') {
        echo json_encode(['status' => 'error', 'message' => 'Faltan datos.']);
        $conn->close();
        exit;
    }

    // Convertir datetime-local (YYYY-MM-DDTHH:MM) a formato MySQL 'YYYY-MM-DD HH:MM:SS'
    $f_inicio_db = str_replace('T', ' ', $f_inicio);
    if (strlen($f_inicio_db) <= 16) $f_inicio_db .= ':00';
    $f_fin_db = str_replace('T', ' ', $f_fin);
    if (strlen($f_fin_db) <= 16) $f_fin_db .= ':00';

    $montoFloat = floatval(str_replace(',', '.', $monto_camp));

    // Asegurarse de que la columna pueda almacenar decimales (intentar migrar si es necesario)
    @ $conn->query("ALTER TABLE costo_campanias MODIFY monto decimal(10,2) NOT NULL");

    $stmt = $conn->prepare("INSERT INTO costo_campanias (nombre_campania, monto, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Error en la consulta: ' . $conn->error]);
        $conn->close();
        exit;
    }
    $stmt->bind_param('sdss', $nombre, $montoFloat, $f_inicio_db, $f_fin_db);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Gasto agregado', 'inserted_id' => $stmt->insert_id, 'nombre_campania' => $nombre]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// Si se accede sin action, retornar error
header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Solicitud inválida']);
$conn->close();
exit;
