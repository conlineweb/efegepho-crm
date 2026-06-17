<?php
// Incluir la conexión a la base de datos
include 'conn.php';  // Aquí deberías incluir tu archivo de conexión a la base de datos

// Verificar si los datos fueron enviados
if (isset($_POST['userId']) && isset($_POST['fechasSeleccionadas'])) {
    $userId = $_POST['userId'];  // ID del usuario
    $fechasSeleccionadas = $_POST['fechasSeleccionadas'];  // Fechas seleccionadas con horas no seleccionadas

    // Verificar si el array no está vacío
    if (!empty($fechasSeleccionadas)) {
        $rows = [];
        
        // Recorrer las fechas seleccionadas
        foreach ($fechasSeleccionadas as $fechaData) {
            $fecha = $fechaData['fecha'];
            $horasNoSeleccionadas = $fechaData['horariosNoSeleccionados'] ?? [];

            // Limpiar cualquier espacio extra en las horas
            $horasNoSeleccionadas = array_map('trim', $horasNoSeleccionadas);

            // Si hay horas no seleccionadas, construimos la consulta
            if (!empty($horasNoSeleccionadas)) {
                // Construir la parte de la consulta con OR dinámicamente para las horas no seleccionadas
                $conditions = [];
                foreach ($horasNoSeleccionadas as $hora) {
                    $conditions[] = "hora = ?";
                }

                // Unir las condiciones con OR para las horas
                $whereClause = implode(" OR ", $conditions);

                // Preparar la consulta SQL con la cláusula WHERE dinámica para horas y la fecha
                $query = "SELECT * FROM calendario WHERE idusu = ? AND fecha = ? AND ($whereClause) AND estatus = 0";

                // Preparar la consulta
                $stmt = $conn->prepare($query);

                // Crear el array de parámetros para bind_param (primer parámetro es el tipo de datos)
                $params = [$userId, $fecha];  // Primero el idusu y la fecha

                // Agregar las horas no seleccionadas al array de parámetros
                foreach ($horasNoSeleccionadas as $hora) {
                    $params[] = $hora;  // Añadimos cada hora como string (formato HH:00:00)
                }

                // Enlazar los parámetros a la consulta
                $types = 'is' . str_repeat('s', count($horasNoSeleccionadas));  // 'i' para el idusu, 's' para la fecha y las horas
                $stmt->bind_param($types, ...$params); // Bind del ID de usuario, la fecha y las horas no seleccionadas

                // Ejecutar la consulta
                if (!$stmt->execute()) {
                    echo "Error al ejecutar la consulta: " . $stmt->error; // Si hay un error en la ejecución
                    exit();
                }

                $result = $stmt->get_result();

                // Verificar si hay registros con horas no seleccionadas
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        // Obtener el día de la semana en formato numérico (1 = lunes, 7 = domingo)
                        $idclie = $row['idclie'];  // Obtener el idclie del registro de calendario
                        
                        // Realizar la consulta adicional a la tabla contact_form
                        $clientQuery = "SELECT * FROM contact_form WHERE id = ?";
                        $clientStmt = $conn->prepare($clientQuery);
                        $clientStmt->bind_param('i', $idclie);
                        $clientStmt->execute();
                        $clientResult = $clientStmt->get_result();

                        if ($clientResult->num_rows > 0) {
                            // Si se encuentra el cliente, añadirlo al registro
                            $clientData = $clientResult->fetch_assoc();
                            $row['cliente'] = $clientData;  // Añadir los datos del cliente al registro de calendario
                        } else {
                            // Si no se encuentra el cliente, añadir un campo vacío o mensaje
                            $row['cliente'] = null;
                        }

                        // Agregar el registro de calendario (con el cliente) a la lista de resultados
                        $rows[] = $row;
                        $clientStmt->close(); // Cerrar el stmt de la consulta del cliente
                    }
                }
            }
        }

        // Si hay citas encontradas
        if (count($rows) > 0) {
            echo json_encode(['status' => 'found', 'message' => 'Se encontraron registros con horas no seleccionadas.', 'data' => $rows]);
        } else {
            echo json_encode(['status' => 'not_found', 'message' => 'No se encontraron registros.']);
        }
    } else {
        // Si el array de fechas seleccionadas está vacío
        echo json_encode(['status' => 'error', 'message' => 'No se enviaron horas o días no seleccionados.']);
    }

    // Cerrar la conexión a la base de datos
    $conn->close();
} else {
    // Si los datos no fueron enviados correctamente
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
}
?>
