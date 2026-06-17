<?php
// Incluir el archivo de conexión a la base de datos
include 'conn.php';

// Configurar el directorio de subida de archivos
$upload_dir = 'uploads/';

// Obtener el ID del cliente (idclie) y las respuestas (en formato JSON)
$idclie = $_POST['idclie'];

$respuestas = json_decode($_POST['respuestas'], true);

// Crear el objeto de respuesta
$response = [];

// Subir los archivos
foreach ($_FILES as $key => $file) {
    // Verificar si el archivo es válido
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Obtener la extensión del archivo
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

        // Generar un nombre único para el archivo (nombre original + fecha y hora)
        $timestamp = date('Y-m-d_H-i-s');  // Fecha y hora en formato 'Y-m-d_H-i-s'
        $nombre_archivo = basename($file['name'], ".$ext") . "_$timestamp.$ext";  // Nuevo nombre con timestamp
        $ruta_destino = $upload_dir . $nombre_archivo;

        // Subir el archivo
        if (move_uploaded_file($file['tmp_name'], $ruta_destino)) {
            // Encontrar el índice del archivo en las respuestas y reemplazarlo con la ruta del archivo
            $archivo_key = str_replace('archivo_', '', $key); // Extraemos el número del archivo (ejemplo: archivo_1 -> 1)

            // Buscamos la respuesta en el JSON y modificamos el objeto de respuesta
            foreach ($respuestas as &$respuesta) {
                if (isset($respuesta['respuesta']) && $respuesta['respuesta'] == "archivo_$archivo_key") {
                    // Actualizamos el objeto con el tipo de archivo y la ruta
                    $respuesta['respuesta'] = [
                        'res' => $ruta_destino,  // Ruta completa del archivo
                        'tipo' => $ext  // Tipo de archivo (pdf, jpg, png, etc.)
                    ];
                }
            }
        } else {
            // Si el archivo no se pudo subir, devolvemos un error
            $response['status'] = 'error';
            $response['message'] = 'Error al subir el archivo: ' . $nombre_archivo;
            echo json_encode($response);
            exit;
        }
    } else {
        // Si hubo un error con el archivo
        $response['status'] = 'error';
        $response['message'] = 'Error al subir uno de los archivos';
        echo json_encode($response);
        exit;
    }
}

// Manejar respuestas tipo array (como checkboxes)
foreach ($respuestas as &$respuesta) {
    // Verificar si la respuesta ya tiene la estructura esperada (tipo y res)
    if (!isset($respuesta['respuesta']['tipo']) || !isset($respuesta['respuesta']['res'])) {
        // Si la respuesta es un array (checkboxes o múltiples valores)
        if (is_array($respuesta['respuesta'])) {
            // Formato esperado para respuestas tipo array (como checkboxes)
            $respuesta['respuesta'] = [
                'tipo' => 'array',  // Tipo de respuesta (en este caso, texto)
                'res' => $respuesta['respuesta']  // Array con las respuestas seleccionadas
            ];
        } else {
            // Si no es un array (es un solo valor), lo tratamos como texto
            $respuesta['respuesta'] = [
                'tipo' => 'text',
                'res' => $respuesta['respuesta']
            ];
        }
    }
}

// Depuración: Verifica las respuestas antes de guardarlas
// var_dump($respuestas);

// Una vez que los archivos se suben, ahora guardamos las respuestas en la base de datos
try {
    // Codificamos las respuestas en JSON para almacenarlas
    $respuestas_json = json_encode($respuestas);

    // Obtenemos la fecha y hora actuales
    $fecha_actual = date("Y-m-d");  // Formato: YYYY-mm-dd
    $hora_actual = date("H:i:s");   // Formato: hh:mm:ss
    $idcuestionario = $_POST['idcuestionario'];
    $idres = $_POST['idres'];

    // Verificar si ya existe un registro con el id dado y si ya fue respondido
    $stmt_check = $conn->prepare("SELECT estatus FROM respuestas_cuestionario WHERE id = ?");
    $stmt_check->bind_param("i", $idres);
    $stmt_check->execute();
    $stmt_check->bind_result($estatus_existente);
    $found = $stmt_check->fetch();
    $stmt_check->close();

    if ($found && $estatus_existente != 2) {
        // El registro existe y aún no había sido respondido: actualizar
        $stmt = $conn->prepare("UPDATE respuestas_cuestionario SET respuestas = ?, fecha = ?, hora = ?, estatus = 2 WHERE id = ?");
        $stmt->bind_param("sssi", $respuestas_json, $fecha_actual, $hora_actual, $idres);

        $stmt->execute();
        $stmt->close();

        $response['status'] = 'success';
        $response['message'] = 'Respuestas actualizadas correctamente';
    } else {
        // El registro no existe o ya había sido respondido: insertar una nueva respuesta
        $stmt = $conn->prepare("INSERT INTO respuestas_cuestionario (respuestas, id_clie, id_cuestionario, fecha, hora, estatus) VALUES (?, ?, ?, ?, ?, 2)");
        $stmt->bind_param("siiss", $respuestas_json, $idclie, $idcuestionario, $fecha_actual, $hora_actual);

        $stmt->execute();
        $stmt->close();

        $response['status'] = 'success';
        $response['message'] = 'Respuestas guardadas correctamente';
    }

    // Devolvemos la respuesta en formato JSON
    echo json_encode($response);

} catch (Exception $e) {
    // Capturamos cualquier excepción que pueda ocurrir
    $response['status'] = 'error';
    $response['message'] = 'Excepción: ' . $e->getMessage();
    echo json_encode($response);
}


// Cerramos la conexión
$conn->close();
?>