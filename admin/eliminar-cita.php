<?php
// Incluimos la conexión a la base de datos
include 'conn.php'; 

// Verificamos si se ha recibido el ID a eliminar
if (isset($_POST['id'])) {
    $id = $_POST['id'];

    // Preparamos la consulta para actualizar los campos en la tabla calendario
    $sql = "UPDATE calendario 
            SET eliminado = 1, fecha = '0000-00-00', hora = '00:00:00' 
            WHERE id = ?";
    //  $sql = "UPDATE calendario 
    //         SET eliminado = 1, idusu = '0'
    //         WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        // Vinculamos el parámetro y ejecutamos
        $stmt->bind_param("i", $id);  // 'i' es para integer (id debe ser un número entero)
        $stmt->execute();

        // Verificamos si la consulta se ejecutó correctamente
        if ($stmt->affected_rows > 0) {
            echo json_encode(["status" => "success", "message" => "Cita eliminada correctamente."]);
        } else {
            echo json_encode(["status" => "error", "message" => "No se encontró el registro o ya fue eliminado."]);
        }

       
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Error al preparar la consulta."]);
    }

  
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "ID no recibido."]);
}
?>
