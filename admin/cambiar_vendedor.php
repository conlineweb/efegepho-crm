<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Mexico/General');

include 'conn.php'; // Incluye el archivo de conexi贸n a la base de datos
$id_cliente = $_POST['id_cliente'];
$nuevo_asesor_id = $_POST['nuevo_asesor_id'];

$sql = "UPDATE contact_form SET id_vendedor_asignado = ?, manual = 0 WHERE id = ?";
$stmt = $conn->prepare($sql);
$result = $stmt->execute([$nuevo_asesor_id, $id_cliente]);

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}