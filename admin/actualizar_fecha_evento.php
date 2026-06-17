<?php
include 'conn.php'; // Suponiendo que ya tienes la conexión a la base de datos

// Verificar si los parámetros están definidos
if (isset($_POST['nuevaFecha']) && isset($_POST['idclie'])) {
    $nuevaFecha = $_POST['nuevaFecha'];
    $idclie = $_POST['idclie'];

    // Asegurarse de que la fecha sea válida
    $fecha_valida = date('Y-m-d', strtotime($nuevaFecha));
    
    // Si la fecha es válida
    if ($fecha_valida) {
        // Actualizar el campo 'wedding_date' en la tabla 'contact_form' para el cliente con el id correspondiente
        $query = "UPDATE contact_form SET wedding_date = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $fecha_valida, $idclie); // 's' para string (fecha) y 'i' para integer (id_cliente)

        if ($stmt->execute()) {
            // Responder con éxito
            echo json_encode(['success' => true]);
        } else {
            // Responder con error si no se pudo ejecutar
            echo json_encode(['success' => false, 'message' => 'Error al actualizar la fecha']);
        }
        $stmt->close();
    } else {
        // Responder con error si la fecha no es válida
        echo json_encode(['success' => false, 'message' => 'Fecha inválida']);
    }
} else {
    // Responder con error si los parámetros no están definidos
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
}

$conn->close();
?>
