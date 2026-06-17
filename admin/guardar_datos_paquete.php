<?php
header('Content-Type: application/json');
include 'conn.php';

$idcliente = isset($_POST['idcliente']) ? intval($_POST['idcliente']) : 0;
$paquete = isset($_POST['paquete']) ? trim($_POST['paquete']) : '';
$puntos = isset($_POST['puntos']) ? trim((string)$_POST['puntos']) : '0';
$monto_venta = isset($_POST['monto_venta']) && $_POST['monto_venta'] !== '' ? floatval($_POST['monto_venta']) : 0;
$que_se_les_vendio = isset($_POST['que_se_les_vendio']) ? trim($_POST['que_se_les_vendio']) : '';

if($idcliente <= 0){
    echo json_encode(['success' => false, 'message' => 'ID de cliente inv��lido']);
    exit;
}

$fecha_cambio_cliente = isset($_POST['fecha_cambio_cliente']) ? trim($_POST['fecha_cambio_cliente']) : '';

$stmt = $conn->prepare("UPDATE contact_form SET paquete = ?, puntos = ?, monto_venta = ?, que_se_les_vendio = ?, fecha_cambio_cliente = NULLIF(?, '') WHERE id = ?");
if(!$stmt){
    echo json_encode(['success' => false, 'message' => 'Error en la preparaci��n de la consulta: '. $conn->error]);
    exit;
}

$stmt->bind_param('ssdssi', $paquete, $puntos, $monto_venta, $que_se_les_vendio, $fecha_cambio_cliente, $idcliente);
$exec = $stmt->execute();

if($exec){
    echo json_encode(['success' => true, 'data' => [
        'paquete' => $paquete,
        'puntos' => $puntos,
        'monto_venta' => $monto_venta,
        'que_se_les_vendio' => $que_se_les_vendio,
        'fecha_cambio_cliente' => $fecha_cambio_cliente
    ]]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error en la ejecuci��n: '. $stmt->error]);
}

$stmt->close();
$conn->close();
