<?php
// Incluir la conexión a la base de datos
include 'conn.php'; // Asegúrate de tener la conexión correctamente configurada 
$id = $_POST['id'];

// Hacer la consulta a la base de datos (tabla calendario)
$query = "SELECT * FROM calendario WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id); // 'i' para enteros, puede cambiarse según el tipo de 'id'
$stmt->execute();
$result = $stmt->get_result();

// Verificar si se encontró algún resultado
if ($result->num_rows > 0) {
    // Obtener los datos de la fila de calendario
    $row = $result->fetch_assoc();
    
    // Obtener el idUsu de la fila de calendario
    $idUsu = $row['idusu'];
    $idClie = $row['idclie']; // Obtener idclie de la tabla calendario
    
    // Realizar la segunda consulta a la tabla usuarios utilizando idUsu
    $queryUsuarios = "SELECT * FROM usuarios WHERE id = ?";
    $stmtUsuarios = $conn->prepare($queryUsuarios);
    $stmtUsuarios->bind_param("i", $idUsu); // 'i' para enteros
    $stmtUsuarios->execute();
    $resultUsuarios = $stmtUsuarios->get_result();

    // Verificar si se encontró algún usuario
    if ($resultUsuarios->num_rows > 0) {
        // Obtener los datos del usuario
        $userData = $resultUsuarios->fetch_assoc();
        
        // Realizar la tercera consulta a la tabla contact_form utilizando idClie
        $queryContactForm = "SELECT * FROM contact_form WHERE id = ?";
        $stmtContactForm = $conn->prepare($queryContactForm);
        $stmtContactForm->bind_param("i", $idClie); // 'i' para enteros
        $stmtContactForm->execute();
        $resultContactForm = $stmtContactForm->get_result();
        
        // Verificar si se encontró algún contacto
        if ($resultContactForm->num_rows > 0) {
            // Obtener los datos del contact_form
            $contactFormData = $resultContactForm->fetch_assoc();
            
            // Combinar los datos de las tres consultas
            $response = [
                'evento' => $row,
                'vendedor' => $userData,
                'cliente' => $contactFormData
            ];
            
            // Devolver los datos combinados como JSON
            echo json_encode($response);
        } else {
            echo json_encode(['message' => 'No se encontró el formulario de contacto']);
        }
        
        // Cerrar la consulta de contact_form
        $stmtContactForm->close();
    } else {
        echo json_encode(['message' => 'No se encontró el usuario']);
    }
    
    // Cerrar la consulta de usuarios
    $stmtUsuarios->close();
} else {
    echo json_encode(['message' => 'No se encontró el evento']);
}

// Cerrar la conexión
$stmt->close();
$conn->close();
?>
