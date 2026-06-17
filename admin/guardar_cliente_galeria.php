<?php
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibir datos del formulario
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $telefono = $_POST['telefono'];
    $fechaRegistro = $_POST['fechaRegistro'];
    $fechaGaleria = $_POST['fechaGaleria'];
    $fechaVencimientoGaleria = $_POST['fechaVencimientoGaleria'];
    $whatsapp = $_POST['whatsapp'];

    // Preparar la consulta SQL (usando sentencias preparadas para prevenir inyección SQL)
    $stmt = $conn->prepare("INSERT INTO galeria (nombre, email, phone, fecha_registro_cliente, fecha_registro_galeria, fecha_vencimiento_galeria,id_whatsapp) 
                           VALUES (?, ?, ?, ?, ?, ? , ?)");
    $stmt->bind_param("sssssss", $nombre, $correo, $telefono, $fechaRegistro, $fechaGaleria, $fechaVencimientoGaleria,$whatsapp);

    // Ejecutar la consulta
    if ($stmt->execute()) {
        // Registro exitoso, devuelve un JSON de éxito
        echo json_encode(["status" => "success", "message" => "Registro exitoso"]);
    } else {
        // Error en el registro, devuelve un JSON de error
        echo json_encode(["status" => "error", "message" => "Error en la inserción de datos"]);
        error_log("Error en la inserción: " . $stmt->error); // Log del error para depuración
    }

    $stmt->close();
    $conn->close();
} else {
    // Si no es una petición POST, responde con error
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
}
?>
