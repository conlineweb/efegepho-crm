<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conn.php'; // Incluye el archivo de conexión a la base de datos


// Verifica si el formulario contiene los datos necesarios
if (isset($_POST['date'], $_POST['time'], $_POST['title'], $_POST['iduser'],
$_POST['description'])) {
    $userid = $_POST['iduser'];

    // Recibir datos del formulario
    $fecha = $_POST['date']; // Fecha seleccionada
    $hora = $_POST['time']; // Hora del evento
    $titulo = $_POST['title']; // Título del evento
    $descripcion = $_POST['description']; // Descripción del evento

    // Intentar insertar los datos en la base de datos
    try {
       $sql = "INSERT INTO calendario (fecha, hora, titulo, nota, idusu) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $fecha, $hora, $titulo, $descripcion, $userid); // Adjust the type of each parameter
        $stmt->execute();

        // Enviar respuesta en caso de éxito
        echo json_encode(['status' => 'success', 'message' => 'Evento guardado con éxito.']);
    } catch (Exception $e) {
        // Enviar respuesta en caso de error, incluyendo el mensaje del error
        echo json_encode([
            'status' => 'error',
            'message' => 'Hubo un error al guardar el evento: ' . $e->getMessage(), // Mensaje de error
            'error_details' => [
                'code' => $e->getCode(), // Código del error
                'trace' => $e->getTrace() // Traza del error (si quieres ver el rastro completo)
            ]
        ]);
    }
} else {
    // Si no se reciben todos los datos, enviar error
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos en el formulario.']);
}
