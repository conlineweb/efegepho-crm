<?php
include 'conn.php';
header('Content-Type: application/json');

$sql = "SELECT ruta, nombre FROM nextstep WHERE id = 1"; 
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'ruta' => $row['ruta'],
        'nombre' => $row['nombre']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No hay documento registrado'
    ]);
}
?>
