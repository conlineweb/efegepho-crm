<?php
include 'conn.php';
require_once __DIR__ . '/wp_citas_leads_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function mapWpLeadStatusFromCalendar($estatus)
{
    if ($estatus === 1) {
        return 'atendido';
    }
    if ($estatus === 3) {
        return 'muerto';
    }

    return 'agendado';
}

$calendarioId = isset($_POST['calendario_id']) ? intval($_POST['calendario_id']) : 0;
$estatus = isset($_POST['estatus']) ? intval($_POST['estatus']) : -1;

if ($calendarioId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cita inválida.']);
    exit;
}

if (!in_array($estatus, [1, 3], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Estatus no permitido.']);
    exit;
}

$checkCurrentStmt = $conn->prepare('SELECT id, estatus FROM calendario WHERE id = ? AND tipo = 1 AND eliminado = 0 LIMIT 1');
if (!$checkCurrentStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo validar la cita.']);
    exit;
}
$checkCurrentStmt->bind_param('i', $calendarioId);
if (!$checkCurrentStmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo validar la cita.']);
    $checkCurrentStmt->close();
    exit;
}
$currentResult = $checkCurrentStmt->get_result();
$currentCita = $currentResult ? $currentResult->fetch_assoc() : null;
$checkCurrentStmt->close();

if (!$currentCita) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'La cita no existe o ya no está disponible.']);
    exit;
}

$currentEstatus = intval($currentCita['estatus'] ?? -1);
if ($estatus === 1 && $currentEstatus === 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La cita ya está marcada como atendida.']);
    exit;
}
if ($estatus === 3 && $currentEstatus === 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La cita ya está marcada como muerta.']);
    exit;
}

try {
    $conn->begin_transaction();

    $eventId = 0;
    $eventStmt = $conn->prepare('SELECT idclie FROM calendario WHERE id = ? AND tipo = 1 AND eliminado = 0 LIMIT 1');
    if (!$eventStmt) {
        throw new Exception('No se pudo obtener el evento de la cita: ' . $conn->error);
    }

    $eventStmt->bind_param('i', $calendarioId);
    if (!$eventStmt->execute()) {
        throw new Exception('No se pudo obtener el evento de la cita: ' . $eventStmt->error);
    }

    $eventResult = $eventStmt->get_result();
    $eventRow = $eventResult ? $eventResult->fetch_assoc() : null;
    $eventStmt->close();

    if ($eventRow) {
        $eventId = intval($eventRow['idclie'] ?? 0);
    }

    $stmt = $conn->prepare('UPDATE calendario SET estatus = ? WHERE id = ? AND tipo = 1 AND eliminado = 0 LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualización de la cita: ' . $conn->error);
    }

    $stmt->bind_param('ii', $estatus, $calendarioId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar el estatus de la cita: ' . $stmt->error);
    }
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($affectedRows === 0) {
        $checkStmt = $conn->prepare('SELECT id, estatus FROM calendario WHERE id = ? AND tipo = 1 AND eliminado = 0 LIMIT 1');
        if (!$checkStmt) {
            throw new Exception('No se pudo validar la cita actualizada: ' . $conn->error);
        }

        $checkStmt->bind_param('i', $calendarioId);
        if (!$checkStmt->execute()) {
            throw new Exception('No se pudo validar la cita actualizada: ' . $checkStmt->error);
        }

        $checkResult = $checkStmt->get_result();
        $currentRow = $checkResult ? $checkResult->fetch_assoc() : null;
        $checkStmt->close();

        if (!$currentRow) {
            throw new Exception('La cita no existe o ya no está disponible.');
        }

        if (intval($currentRow['estatus'] ?? -1) !== $estatus) {
            throw new Exception('No se pudo actualizar la cita.');
        }
    }

    $leadStatus = mapWpLeadStatusFromCalendar($estatus);
    $wpCitasTableExists = $conn->query("SHOW TABLES LIKE 'wp_citas_leads'");
    if ($wpCitasTableExists && $wpCitasTableExists->num_rows > 0) {
        $mirrorStmt = $conn->prepare('UPDATE wp_citas_leads SET lead_status = ?, estatus_cita = ?, descartado = 0 WHERE id_calendario = ?');
        if ($mirrorStmt) {
            $mirrorStmt->bind_param('ssi', $leadStatus, $leadStatus, $calendarioId);
            if (!$mirrorStmt->execute()) {
                error_log('WP mirror status update warning for calendario #' . $calendarioId . ': ' . $mirrorStmt->error);
            }
            $mirrorStmt->close();
        } else {
            error_log('WP mirror status prepare warning for calendario #' . $calendarioId . ': ' . $conn->error);
        }
    }

    try {
        wpCitasSyncLeadByCalendarId($conn, $calendarioId);
    } catch (Exception $syncError) {
        error_log('WP status sync warning for calendario #' . $calendarioId . ': ' . $syncError->getMessage());
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $estatus === 1 ? 'La cita se marcó como atendida.' : 'La cita se marcó como muerta.',
        'estatus' => $estatus
    ]);
    exit;
} catch (Exception $e) {
    error_log('WP status update error for calendario #' . $calendarioId . ': ' . $e->getMessage());
    @ $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
