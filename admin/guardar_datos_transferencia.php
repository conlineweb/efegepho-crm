<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conn.php';

// Recibe los datos del formulario
$clabe = $_POST['clabe'] ?? '';
$tarjeta = $_POST['tarjeta'] ?? '';
$nombre_banco = $_POST['nombre_banco'] ?? '';
$nombre_titular = $_POST['nombre_titular'] ?? '';
$cuenta_id = isset($_POST['cuenta']) ? intval($_POST['cuenta']) : 0;

// Validaci¨®n
if ($cuenta_id < 1 || $cuenta_id > 4) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cuenta inv¨¢lido.']);
    exit;
}

if (empty($clabe) || empty($tarjeta) || empty($nombre_banco) || empty($nombre_titular)) {
    echo json_encode(['status' => 'error', 'message' => 'Todos los campos son obligatorios.']);
    exit;
}

// Verifica si ya existe el registro con ese ID
$sql = "SELECT * FROM datos_transferencia WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cuenta_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Si ya existe, actualiza
    $update_sql = "UPDATE datos_transferencia SET clabe = ?, tarjeta = ?, nombre_banco = ?, nombre_titular = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssssi", $clabe, $tarjeta, $nombre_banco, $nombre_titular, $cuenta_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Datos actualizados correctamente (Cuenta ' . $cuenta_id . ').']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar los datos: ' . $stmt->error]);
    }
} else {
    // Si no existe, inserta nuevo
    $insert_sql = "INSERT INTO datos_transferencia (id, clabe, tarjeta, nombre_banco, nombre_titular) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("issss", $cuenta_id, $clabe, $tarjeta, $nombre_banco, $nombre_titular);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Datos guardados correctamente (Cuenta ' . $cuenta_id . ').']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al guardar los datos: ' . $stmt->error]);
    }
}

$stmt->close();
$conn->close();
?>
