<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conn.php';
header('Content-Type: application/json');

$response = [];

// Primero, verifica si ya hay un archivo registrado en la base de datos
$check_sql = "SELECT * FROM nextstep WHERE id = 1";  // Asumiendo que el id = 1 es único
$check_result = $conn->query($check_sql);
if ($check_result && $check_result->num_rows > 0) {
    $row = $check_result->fetch_assoc();
    // Si ya existe un archivo registrado en la base de datos, lo agregamos a la respuesta
    $response['nombre'] = $row['nombre'];  // Nombre del archivo
    $response['ruta'] = $row['ruta'];  // Ruta del archivo en la base de datos
} else {
    $response['nombre'] = null;  // No hay archivo registrado
    $response['ruta'] = null;
}

// Ahora maneja la subida de un nuevo archivo
if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
    $nombre_fijo = "NEXTSTEPSEFEGE.pdf";
    $tmp = $_FILES['archivo']['tmp_name'];
    $ruta_relativa = '../uploads_nextstep/' . $nombre_fijo;

    // Crear el directorio si no existe
    if (!file_exists('../uploads_nextstep')) {
        mkdir('../uploads_nextstep', 0777, true);
    }

    // Eliminar el archivo anterior si ya existe
    if (file_exists($ruta_relativa)) {
        unlink($ruta_relativa);
    }

    // Mover el archivo subido al directorio
    if (move_uploaded_file($tmp, $ruta_relativa)) {
        $ruta_bd = "uploads_nextstep/" . $nombre_fijo;
        $nombre_original = $_FILES['archivo']['name'];

        // Escapado seguro
        $ruta_segura = $conn->real_escape_string($ruta_bd);
        $nombre_seguro = $conn->real_escape_string($nombre_original);

        // Si ya hay un registro, actualizamos; si no, insertamos uno nuevo
        if ($row) {
            $sql = "UPDATE nextstep SET ruta = '$ruta_segura', nombre = '$nombre_seguro' WHERE id = 1";
        } else {
            $sql = "INSERT INTO nextstep (id, ruta, nombre) VALUES (1, '$ruta_segura', '$nombre_seguro')";
        }

        if ($conn->query($sql) === TRUE) {
            $response = [
                'success' => true,
                'message' => 'Archivo subido y ruta registrada correctamente.',
                'ruta' => $ruta_segura,
                'nombre' => $nombre_seguro
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Error al guardar en base de datos: ' . $conn->error
            ];
        }
    } else {
        $response = [
            'success' => false,
            'message' => 'Error al mover el archivo.'
        ];
    }
} else {
    $response = [
        'success' => false,
        'message' => 'No se recibió ningún archivo o hubo un error.'
    ];
}


$sql = "SELECT * FROM nextstep";
$result = $conn->query($sql);

$response = array();

if ($result->num_rows > 0) {
    // Si hay resultados, los agregamos al array de respuesta
    while($row = $result->fetch_assoc()) {
        $response[] = $row; // Agrega cada fila como un elemento del array
    }
    $response = [
        'success' => true,
        'data' => $response // Enviamos los datos de la tabla
    ];
} else {
    $response = [
        'success' => false,
        'message' => 'No se encontraron datos.'
    ];
}

$conn->close();
echo json_encode($response);
