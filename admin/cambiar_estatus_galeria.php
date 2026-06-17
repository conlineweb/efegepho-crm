<?php
include 'conn.php';  // Incluimos el archivo de conexión
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 
// Inicializamos un array para la respuesta
$response = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obtenemos el ID y el nuevo estatus desde el formulario
   
    // Verificamos que el ID y el estatus no estén vacíos
 if (isset($_POST['id']) && $_POST['id'] !== '' && isset($_POST['estatus']) && $_POST['estatus'] !== '') {
    $id = $_POST['id'];
    $estatus = $_POST['estatus'];
        // Preparamos la consulta para actualizar el estatus
        $query = "UPDATE galeria SET estatus = ? WHERE id = ?";

        // Usamos sentencias preparadas para evitar inyecciones SQL
        if ($stmt = mysqli_prepare($conn, $query)) {
            // Enlazamos los parámetros
            mysqli_stmt_bind_param($stmt, "ii", $estatus, $id);

            // Ejecutamos la consulta
            if (mysqli_stmt_execute($stmt)) {
                // Si la actualización fue exitosa
                $response['status'] = 'success';
                $response['message'] = 'Estatus actualizado correctamente';
            } else {
                // Si hubo un error al ejecutar la consulta
                $response['status'] = 'error';
                $response['message'] = 'Error al actualizar el estatus';
            }

            // Cerramos la sentencia
            mysqli_stmt_close($stmt);
        } else {
            // Si la preparación de la consulta falló
            $response['status'] = 'error';
            $response['message'] = 'Error en la consulta SQL';
        }
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Faltan datos para actualizar el estatus';
    }

    // Cerramos la conexión a la base de datos
    mysqli_close($conn);
} else {
    $response['status'] = 'error';
    $response['message'] = 'Solicitud inválida';
}

// Establecemos el encabezado de respuesta a JSON
header('Content-Type: application/json');

// Devolvemos la respuesta en formato JSON
echo json_encode($response);
?>
