<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

include 'conn.php'; // Incluye el archivo de conexión a la base de datos

// Verificar que se recibieron los datos necesarios
if (!isset($_POST['id']) || !isset($_POST['tabla_origen'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Faltan datos requeridos'
    ]);
    exit;
}

$id = intval($_POST['id']);
$tablaOrigen = $_POST['tabla_origen'];

// Sanitizar el nombre de la tabla para evitar SQL injection
$tablaOrigen = preg_replace('/[^a-zA-Z0-9_]/', '', $tablaOrigen);

// Verificar que la tabla existe
$checkTable = $conn->query("SHOW TABLES LIKE '$tablaOrigen'");
if ($checkTable->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'La tabla especificada no existe'
    ]);
    exit;
}

// Verificar que la columna 'descartado' existe en la tabla
$columnsResult = $conn->query("SHOW COLUMNS FROM `$tablaOrigen` LIKE 'descartado'");
if ($columnsResult->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'La columna descartado no existe en esta tabla'
    ]);
    exit;
}

// Actualizar el registro para marcarlo como descartado
$stmt = $conn->prepare("UPDATE `$tablaOrigen` SET descartado = 1 WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Lead descartado exitosamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró el lead o ya estaba descartado'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar el registro: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
