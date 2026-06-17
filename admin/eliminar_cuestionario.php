<?php
header('Content-Type: application/json'); // Asegura que devuelva JSON

include 'conn.php'; // tu conexión

// Obtiene el JSON de la solicitud
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "ID no proporcionado"
    ]);
    exit;
}

$id = $input['id'];

// Ejecuta el delete
$stmt = $conn->prepare("DELETE FROM cuestionario WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Cuestionario eliminado correctamente"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "No se pudo eliminar el cuestionario"
    ]);
}

$stmt->close();
$conn->close();
