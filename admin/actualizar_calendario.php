<?php
// Incluir la conexión a la base de datos 
include 'conn.php';  // Aquí deberías incluir tu archivo de conexión a la base de datos
require_once __DIR__ . '/calendario_estatus_historial_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$usuarioActualizaCal = isset($_SESSION['uid']) && $_SESSION['uid'] !== '' ? intval($_SESSION['uid']) : null;

// Verificar si se ha recibido la variable citas por POST
if (isset($_POST['citas'])) {
    $citas = $_POST['citas'];  // Obtener las citas desde el POST
    
    $allUpdated = true;  // Bandera para verificar si todas las actualizaciones fueron exitosas

    // Usamos un for para recorrer las citas, en vez de un foreach
    $numCitas = count($citas); // Número total de citas que estamos procesando
    for ($i = 0; $i < $numCitas; $i++) {
        $id = $citas[$i]['id'];  // El id del registro en la tabla calendario

        // Capturar el estatus anterior ANTES de sobreescribirlo, para el historial
        $estatusAnteriorCal = null;
        if ($stmtPrevCal = $conn->prepare("SELECT estatus FROM calendario WHERE id = ? LIMIT 1")) {
            $stmtPrevCal->bind_param('i', $id);
            if ($stmtPrevCal->execute()) {
                $resPrevCal = $stmtPrevCal->get_result();
                if ($resPrevCal && ($rowPrevCal = $resPrevCal->fetch_assoc())) {
                    $estatusAnteriorCal = intval($rowPrevCal['estatus']);
                }
            }
            $stmtPrevCal->close();
        }

        // Preparar la consulta para actualizar el registro
        $query = "UPDATE calendario SET idusu = 0, estatus = 0 WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);  // El id es un entero
        $stmt->execute();

        // Verificar si la consulta fue exitosa
        if ($stmt->affected_rows == 0) {
            // Si no se actualizó el registro, marcar como error
            $allUpdated = false;
        } else {
            // Registrar la liberación de la cita (vuelve a agendado = 0) en el historial
            registrarCambioEstatusCalendario($conn, $id, 0, [
                'estatus_anterior' => $estatusAnteriorCal,
                'usuario'          => $usuarioActualizaCal,
                'origen'           => 'actualizar_calendario',
                'observaciones'    => 'Liberacion/actualizacion de cita',
            ]);
        }

        $stmt->close();
        if($i == $numCitas - 1){
                    echo json_encode(['status' => 'success', 'message' => 'Todos los registros fueron actualizados correctamente']);

        }
    }

    // Después de intentar actualizar todos los registros, devolver el status
    if (!$allUpdated) {
                echo json_encode(['status' => 'error', 'message' => 'Algunos registros no pudieron ser actualizados']);
    } 

    // Cerrar la conexión al final del script
    $conn->close();
} else {
    // Si no se recibieron las citas en el POST
    echo json_encode(['status' => 'error', 'message' => 'No se recibieron los datos para actualizar']);
}
?>
