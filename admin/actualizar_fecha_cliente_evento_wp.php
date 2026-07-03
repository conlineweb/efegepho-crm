<?php
include 'conn.php';
require_once __DIR__ . '/evento_wp_post_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function wpClienteDateJsonResponse($statusCode, array $payload)
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$fechaClienteRaw = isset($_POST['fecha_cliente']) ? trim((string) $_POST['fecha_cliente']) : '';

if ($eventId <= 0) {
    wpClienteDateJsonResponse(400, ['success' => false, 'message' => 'Evento inválido.']);
}

$fechaClienteDb = wpEventNormalizeDateTime($fechaClienteRaw);
if ($fechaClienteDb === null) {
    wpClienteDateJsonResponse(400, ['success' => false, 'message' => 'Fecha de cliente inválida.']);
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
        wpClienteDateJsonResponse(404, ['success' => false, 'message' => 'Evento no encontrado.']);
    }

    $statusKey = wpPlannerResolveEventStatusKey(
        $eventRow['estatus'] ?? '',
        $eventRow['contact_form_cliente'] ?? null
    );
    if ($statusKey !== 'cliente') {
        wpClienteDateJsonResponse(400, ['success' => false, 'message' => 'Solo se puede editar la fecha cuando el evento ya es Cliente.']);
    }

    $conn->begin_transaction();

    wpEventEnsureStatusMilestoneColumns($conn);

    $stmt = $conn->prepare('UPDATE eventos_wp SET fecha_cliente = ?, fecha_actualizacion_estatus = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualización del evento: ' . $conn->error);
    }
    $stmt->bind_param('ssi', $fechaClienteDb, $fechaClienteDb, $eventId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar la fecha del evento: ' . $stmt->error);
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

    if ($contactFormId <= 0) {
        throw new Exception('No se encontró el registro en contact_form del evento.');
    }

    $stmt = $conn->prepare('UPDATE contact_form SET fecha_cambio_cliente = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualización de contact_form: ' . $conn->error);
    }
    $stmt->bind_param('si', $fechaClienteDb, $contactFormId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar la fecha de cliente: ' . $stmt->error);
    }
    $stmt->close();

    $conn->commit();

    wpClienteDateJsonResponse(200, [
        'success' => true,
        'message' => 'Fecha de cliente actualizada correctamente.',
        'fecha_cliente' => $fechaClienteDb,
    ]);
} catch (Exception $e) {
    @ $conn->rollback();
    wpClienteDateJsonResponse(500, ['success' => false, 'message' => $e->getMessage()]);
}
