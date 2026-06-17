<?php
include 'conn.php'; // Asegúrate de que esta conexión esté correctamente configurada

if (isset($_POST['fecha'])) {
    $fechaSeleccionada = $_POST['fecha'];
    
    // Verificar si la fecha ya está bloqueada
    $query = "SELECT * FROM dias_bloqueados WHERE fecha = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $fechaSeleccionada);
    $stmt->execute();
    $result = $stmt->get_result();

    // Si la fecha ya está bloqueada, eliminarla
    if ($result->num_rows > 0) {
        // Fecha ya bloqueada, eliminarla
        $deleteQuery = "DELETE FROM dias_bloqueados WHERE fecha = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("s", $fechaSeleccionada);
        $deleteStmt->execute();

        // Enviar respuesta JSON
        echo json_encode(['status' => 'success', 'action' => 'unblocked']);
    } else {
        // Si no está bloqueada, agregarla
        $insertQuery = "INSERT INTO dias_bloqueados (fecha) VALUES (?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("s", $fechaSeleccionada);
        $insertStmt->execute();

        // Enviar respuesta JSON
        echo json_encode(['status' => 'success', 'action' => 'blocked']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió la fecha']);
}
?>
