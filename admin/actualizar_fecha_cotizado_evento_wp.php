<?php
include 'conn.php';
require_once __DIR__ . '/evento_wp_post_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function wpCotizadoDateJsonResponse($statusCode, array $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$fechaCotizadoRaw = isset($_POST['fecha_cotizado']) ? trim((string) $_POST['fecha_cotizado']) : '';

if ($eventId <= 0) {
    wpCotizadoDateJsonResponse(400, ['success' => false, 'message' => 'Evento inválido.']);
}

$fechaCotizadoDb = wpEventNormalizeDateTime($fechaCotizadoRaw);
if ($fechaCotizadoDb === null) {
    wpCotizadoDateJsonResponse(400, ['success' => false, 'message' => 'Fecha de cotizado inválida.']);
}

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
        wpCotizadoDateJsonResponse(404, ['success' => false, 'message' => 'Evento no encontrado.']);
    }

    $statusKey = wpPlannerResolveEventStatusKey(
        $eventRow['estatus'] ?? '',
        $eventRow['contact_form_cliente'] ?? null
    );
    $allowedStatusKeys = ['cotizado', 'atendido', 'cliente_inminente', 'cliente'];
    if (!in_array($statusKey, $allowedStatusKeys, true)) {
        wpCotizadoDateJsonResponse(400, ['success' => false, 'message' => 'Solo se puede editar la fecha cuando el evento ya es Cotizado o posterior.']);
    }

    $conn->begin_transaction();

    wpEventEnsureStatusMilestoneColumns($conn);

    $stmt = $conn->prepare('UPDATE eventos_wp SET fecha_cotizado = ?, fecha_agendado = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualización del evento: ' . $conn->error);
    }
    $stmt->bind_param('ssi', $fechaCotizadoDb, $fechaCotizadoDb, $eventId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar la fecha de cotizado del evento: ' . $stmt->error);
    }
    $stmt->close();

    if ($statusKey === 'cotizado') {
        wpEventEnsureContactFormColumns($conn);

        $contactFormId = wpEventFindContactFormIdForEvent($conn, $eventId);
        if ($contactFormId <= 0) {
            throw new Exception('No se encontró el registro en contact_form del evento.');
        }

        $fechaCambioCliente = date('Y-m-d', strtotime($fechaCotizadoDb));
        $stmt = $conn->prepare('UPDATE contact_form SET created_time = ?, submission_date = ?, fecha_cambio_cliente = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización de contact_form: ' . $conn->error);
        }
        $stmt->bind_param('sssi', $fechaCotizadoDb, $fechaCotizadoDb, $fechaCambioCliente, $contactFormId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar contact_form: ' . $stmt->error);
        }
        $stmt->close();
    }

    $conn->commit();

    wpCotizadoDateJsonResponse(200, [
        'success' => true,
        'message' => 'Fecha de cotizado actualizada correctamente.',
        'fecha_cotizado' => $fechaCotizadoDb,
        'fecha_agendado' => $fechaCotizadoDb,
    ]);
} catch (Exception $e) {
    @ $conn->rollback();
    wpCotizadoDateJsonResponse(500, ['success' => false, 'message' => $e->getMessage()]);
}
