<?php
include 'conn.php';

// Inicializamos un array para guardar los resultados
$respuesta = array();

// Obtener los datos enviados por AJAX
$horarios = json_decode($_POST['horarios'], true);
$usuarioId = $_POST['usuarioId'];

// Preparar la consulta SQL para guardar o actualizar los horarios
foreach ($horarios as $fecha => $horas) {
    // Convertir las horas a formato JSON
    $horasJson = json_encode($horas);

    // Primero verificar si ya existe la fecha en la base de datos
    $stmt_check = $conn->prepare("SELECT id FROM atencion WHERE dia = ? AND idusu = ?");
    $stmt_check->bind_param("si", $fecha, $usuarioId);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    // Si el array de horas está vacío, eliminar el registro para esa fecha
    if (empty($horas)) {
        if ($result_check->num_rows > 0) {
            $stmt_delete = $conn->prepare("DELETE FROM atencion WHERE dia = ? AND idusu = ?");
            $stmt_delete->bind_param("si", $fecha, $usuarioId);
            if ($stmt_delete->execute()) {
                $respuesta[] = array(
                    'fecha' => $fecha,
                    'status' => 'success',
                    'message' => "Horarios eliminados para la fecha: $fecha"
                );
            } else {
                $respuesta[] = array(
                    'fecha' => $fecha,
                    'status' => 'error',
                    'message' => "Error al eliminar los horarios: " . $stmt_delete->error
                );
            }
            $stmt_delete->close();
        }
        $stmt_check->close();
        continue;
    }

    if ($result_check->num_rows > 0) {
        // Si existe, actualizar la fila
        $stmt_update = $conn->prepare("UPDATE atencion SET horarios = ? WHERE dia = ? AND idusu = ?");
        $stmt_update->bind_param("ssi", $horasJson, $fecha, $usuarioId);

        // Ejecutar la actualizaci��n
        if ($stmt_update->execute()) {
            // Guardamos el resultado de la operaci��n en el array
            $respuesta[] = array(
                'fecha' => $fecha,
                'status' => 'success',
                'message' => "Horarios actualizados exitosamente para la fecha: $fecha"
            );
        } else {
            $respuesta[] = array(
                'fecha' => $fecha,
                'status' => 'error',
                'message' => "Error al actualizar los horarios: " . $stmt_update->error
            );
        }

        $stmt_update->close();
    } else {
        // Si no existe, insertar una nueva fila
        $stmt_insert = $conn->prepare("INSERT INTO atencion (dia, horarios, idusu) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("ssi", $fecha, $horasJson, $usuarioId);

        // Ejecutar la inserci��n
        if ($stmt_insert->execute()) {
            $respuesta[] = array(
                'fecha' => $fecha,
                'status' => 'success',
                'message' => "Horarios guardados exitosamente para la fecha: $fecha"
            );
        } else {
            $respuesta[] = array(
                'fecha' => $fecha,
                'status' => 'error',
                'message' => "Error al guardar los horarios: " . $stmt_insert->error
            );
        }

        $stmt_insert->close();
    }

    $stmt_check->close();
}

// Cerrar la conexi��n
$conn->close();

// Devolver la respuesta en formato JSON
echo json_encode($respuesta);
?>
