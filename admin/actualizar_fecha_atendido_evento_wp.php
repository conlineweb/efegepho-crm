<?php
include 'conn.php';
require_once __DIR__ . '/evento_wp_post_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function wpAttendedDateJsonResponse($statusCode, array $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$fechaAtendidoRaw = isset($_POST['fecha_atendido']) ? trim((string) $_POST['fecha_atendido']) : '';

if ($eventId <= 0) {
    wpAttendedDateJsonResponse(400, ['success' => false, 'message' => 'Evento inválido.']);
}

$fechaAtendidoDb = wpEventNormalizeDateTime($fechaAtendidoRaw);
if ($fechaAtendidoDb === null) {
    wpAttendedDateJsonResponse(400, ['success' => false, 'message' => 'Fecha de atención inválida.']);
}

wpEventEnsureTable($conn);

try {
    $contactFormClienteSelect = wpPlannerSqlContactFormClienteSelect('e');
    $stmt = $conn->prepare("SELECT e.id, e.estatus, {$contactFormClienteSelect} AS contact_form_cliente
        FROM eventos_wp e
        WHERE e.id = ?
        LIMIT 1");
    if (!$stmt) {
        throw new Exception('No se pudo validar el evento: ' . $conn->error);
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $eventResult = $stmt->get_result();
    $eventRow = $eventResult ? $eventResult->fetch_assoc() : null;
    $stmt->close();

    if (!$eventRow) {
        wpAttendedDateJsonResponse(404, ['success' => false, 'message' => 'Evento no encontrado.']);
    }

    $statusKey = wpPlannerResolveEventStatusKey(
        $eventRow['estatus'] ?? '',
        $eventRow['contact_form_cliente'] ?? null
    );
    $allowedAttendedKeys = ['atendido', 'cliente_inminente', 'cliente'];
    if (!in_array($statusKey, $allowedAttendedKeys, true)) {
        wpAttendedDateJsonResponse(400, ['success' => false, 'message' => 'Solo se puede editar la fecha cuando el evento ya es Atendido o posterior.']);
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare('UPDATE eventos_wp SET fecha_atendido = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualización del evento: ' . $conn->error);
    }
    $stmt->bind_param('si', $fechaAtendidoDb, $eventId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar la fecha de atención del evento: ' . $stmt->error);
    }
    $stmt->close();

    wpEventEnsureContactFormColumns($conn);

    $contactFormId = 0;
    $stmt = $conn->prepare("SELECT id FROM contact_form
        WHERE original_lead_id = ?
          AND LOWER(TRIM(COALESCE(tabla_origen, ''))) = 'eventos_wp'
        ORDER BY id DESC
        LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $eventId);
        if ($stmt->execute()) {
            $cfResult = $stmt->get_result();
            $cfRow = $cfResult ? $cfResult->fetch_assoc() : null;
            $contactFormId = intval($cfRow['id'] ?? 0);
        }
        $stmt->close();
    }

    if ($contactFormId > 0) {
        $stmt = $conn->prepare('UPDATE contact_form SET created_time = ?, submission_date = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización de contact_form: ' . $conn->error);
        }
        $stmt->bind_param('ssi', $fechaAtendidoDb, $fechaAtendidoDb, $contactFormId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar contact_form: ' . $stmt->error);
        }
        $stmt->close();
    }

    $tablesResult = $conn->query("SHOW TABLES LIKE 'wp_eventos_afianzados'");
    if ($tablesResult && $tablesResult->num_rows > 0) {
        $callDate = date('Y-m-d', strtotime($fechaAtendidoDb));
        $mirrorStmt = $conn->prepare('UPDATE wp_eventos_afianzados SET choose_your_call_date_ = ? WHERE id_evento = ?');
        if ($mirrorStmt) {
            $mirrorStmt->bind_param('si', $callDate, $eventId);
            $mirrorStmt->execute();
            $mirrorStmt->close();
        }
    }

    $conn->commit();

    wpAttendedDateJsonResponse(200, [
        'success' => true,
        'message' => 'Fecha de atención actualizada correctamente.',
        'fecha_atendido' => $fechaAtendidoDb,
    ]);
} catch (Exception $e) {
    @ $conn->rollback();
    wpAttendedDateJsonResponse(500, ['success' => false, 'message' => $e->getMessage()]);
}
