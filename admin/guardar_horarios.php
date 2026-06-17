<?php
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['userId']) ? intval($_POST['userId']) : null;
    $dia = isset($_POST['dia']) ? intval($_POST['dia']) : null;
    $horas = isset($_POST['horas']) && is_array($_POST['horas']) ? $_POST['horas'] : [];

    if (empty($userId) || empty($dia)) {
        echo json_encode(['status' => 'error', 'message' => 'Faltan datos para guardar']);
        exit;
    }

    if (empty($horas)) {
        try {
            $stmtDelete = $conn->prepare("DELETE FROM atencion WHERE idusu = ? AND dia = ?");
            $stmtDelete->bind_param("ii", $userId, $dia);
            $stmtDelete->execute();

            echo json_encode(['status' => 'success', 'message' => 'Horario eliminado correctamente']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Error al eliminar el horario: ' . $e->getMessage()]);
        }
    } else {
        $horasFormateadas = array_map(function ($hora) {
            return str_pad($hora, 2, '0', STR_PAD_LEFT) . ":00";
        }, $horas);

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("SELECT id FROM atencion WHERE idusu = ? AND dia = ?");
            $stmt->bind_param("ii", $userId, $dia);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $stmtUpdate = $conn->prepare("UPDATE atencion SET horarios = ? WHERE idusu = ? AND dia = ?");
                $stmtUpdate->bind_param("sii", json_encode($horasFormateadas), $userId, $dia);
                $stmtUpdate->execute();
            } else {
                $stmtInsert = $conn->prepare("INSERT INTO atencion (idusu, dia, horarios) VALUES (?, ?, ?)");
                $stmtInsert->bind_param("iis", $userId, $dia, json_encode($horasFormateadas));
                $stmtInsert->execute();
            }

            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Horarios guardados o actualizados correctamente']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar o actualizar los horarios: ' . $e->getMessage()]);
        }
    }
}
?>