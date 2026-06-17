<?php
ob_start();
include 'conn.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function plannerEventStatusJsonResponse($statusCode, array $payload)
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
    }

    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode($payload);
    exit;
}

function plannerEventCurrentStatusLabel($eventStatusRaw, $contactFormCliente)
{
    $raw = trim((string) $eventStatusRaw);
    $cliente = is_numeric($contactFormCliente) ? intval($contactFormCliente) : null;

    if ($cliente === 1 || strcasecmp($raw, 'cliente') === 0) {
        return 'cliente';
    }

    if ($cliente === 3 || $raw === '3' || strcasecmp($raw, 'rechazado') === 0) {
        return 'rechazado';
    }

    if ($cliente === 2 || $raw === '2' || strcasecmp($raw, 'cotizado') === 0) {
        return 'cotizado';
    }

    if ($cliente === 4 || $raw === '4' || strcasecmp($raw, 'cliente inminente') === 0) {
        return 'cliente_inminente';
    }

    if ($raw === '1' || strcasecmp($raw, 'atendido') === 0) {
        return 'atendido';
    }

    return 'pendiente';
}

$eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$targetStatus = isset($_POST['target_status']) ? intval($_POST['target_status']) : 0;

if ($eventId <= 0) {
    plannerEventStatusJsonResponse(400, ['success' => false, 'message' => 'Evento inválido.']);
}

if (!in_array($targetStatus, [1, 2, 3, 4], true)) {
    plannerEventStatusJsonResponse(400, ['success' => false, 'message' => 'Estatus objetivo inválido.']);
}

try {
    $stmt = $conn->prepare("SELECT e.id, e.estatus, cf.cliente AS contact_form_cliente
        FROM eventos_wp e
        LEFT JOIN (
          SELECT original_lead_id, MAX(id) AS latest_id
          FROM contact_form
          WHERE LOWER(COALESCE(tabla_origen, '')) = 'eventos_wp'
          GROUP BY original_lead_id
        ) cf_latest ON cf_latest.original_lead_id = e.id
        LEFT JOIN contact_form cf ON cf.id = cf_latest.latest_id
        WHERE e.id = ?
        LIMIT 1");

    if (!$stmt) {
        throw new Exception('No se pudo consultar el evento: ' . $conn->error);
    }

    $stmt->bind_param('i', $eventId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo consultar el evento: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $eventRow = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$eventRow) {
        plannerEventStatusJsonResponse(404, ['success' => false, 'message' => 'Evento no encontrado.']);
    }

    $currentLabel = plannerEventCurrentStatusLabel($eventRow['estatus'] ?? '', $eventRow['contact_form_cliente'] ?? null);

    if ($currentLabel === 'atendido' && $targetStatus !== 2) {
        plannerEventStatusJsonResponse(400, ['success' => false, 'message' => 'Un evento atendido solo se puede pasar a Cotizado.']);
    }

    if ($currentLabel === 'cotizado' && $targetStatus !== 4) {
        plannerEventStatusJsonResponse(400, ['success' => false, 'message' => 'Un evento cotizado primero debe pasar a Cliente inminente.']);
    }

    if ($currentLabel === 'cliente_inminente' && !in_array($targetStatus, [1, 3], true)) {
        plannerEventStatusJsonResponse(400, ['success' => false, 'message' => 'Un evento en Cliente inminente solo se puede pasar a Cliente o Rechazado.']);
    }

    if ($currentLabel === 'rechazado') {
        plannerEventStatusJsonResponse(400, ['success' => false, 'message' => 'Este evento ya está en Rechazado.']);
    }

    if ($currentLabel === 'cliente') {
        plannerEventStatusJsonResponse(400, ['success' => false, 'message' => 'Este evento ya está en Cliente.']);
    }

    if (!in_array($currentLabel, ['atendido', 'cotizado', 'cliente_inminente'], true)) {
        plannerEventStatusJsonResponse(400, ['success' => false, 'message' => 'El evento no está en un estatus válido para esta transición.']);
    }

    $conn->begin_transaction();

    $eventStatusValue = (string) $targetStatus;
    $stmt = $conn->prepare('UPDATE eventos_wp SET estatus = ?, fecha_actualizacion_estatus = NOW() WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualización de eventos_wp: ' . $conn->error);
    }

    $stmt->bind_param('si', $eventStatusValue, $eventId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar el estatus del evento: ' . $stmt->error);
    }
    $stmt->close();

    $fechaCambioCliente = date('Y-m-d');
    $stmt = $conn->prepare("UPDATE contact_form
        SET cliente = ?, fecha_cambio_cliente = ?
        WHERE original_lead_id = ?
          AND LOWER(COALESCE(tabla_origen, '')) = 'eventos_wp'");
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualización de contact_form: ' . $conn->error);
    }

    $stmt->bind_param('isi', $targetStatus, $fechaCambioCliente, $eventId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar el contact_form del evento: ' . $stmt->error);
    }

    if ($stmt->affected_rows <= 0) {
        throw new Exception('No se encontró el registro en contact_form para actualizar el estatus.');
    }

    $stmt->close();

    $conn->commit();

    $targetLabel = $targetStatus === 2 ? 'Cotizado' : ($targetStatus === 3 ? 'Rechazado' : ($targetStatus === 4 ? 'Cliente inminente' : 'Cliente'));
    plannerEventStatusJsonResponse(200, [
        'success' => true,
        'message' => 'El evento se actualizó a ' . $targetLabel . ' correctamente.'
    ]);
} catch (Exception $e) {
    @ $conn->rollback();
    plannerEventStatusJsonResponse(500, ['success' => false, 'message' => $e->getMessage()]);
}
