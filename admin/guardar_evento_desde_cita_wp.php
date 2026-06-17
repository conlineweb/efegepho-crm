<?php
ob_start();
include 'conn.php';
require_once __DIR__ . '/evento_wp_post_helper.php';
require_once __DIR__ . '/wp_citas_leads_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function wpEventEncodeJsonPayload(array $payload)
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json !== false) {
        return $json;
    }

    error_log('[guardar_evento_desde_cita_wp] json_encode failed: ' . json_last_error_msg());

    $fallback = [
        'success' => false,
        'message' => 'Error al serializar la respuesta JSON.'
    ];

    $fallbackJson = json_encode($fallback);
    if ($fallbackJson === false) {
        return '{"success":false,"message":"Error interno de serializacion JSON."}';
    }

    return $fallbackJson;
}

function wpEventJsonResponse($statusCode, array $payload)
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
    }
    if (ob_get_length()) {
        ob_clean();
    }

    $jsonResponse = wpEventEncodeJsonPayload($payload);
    if ($jsonResponse === '' && !headers_sent()) {
        http_response_code(500);
    }

    echo $jsonResponse;
    exit;
}

register_shutdown_function(function () {
    $lastError = error_get_last();
    if (!$lastError) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array(intval($lastError['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }

    if (ob_get_length()) {
        ob_clean();
    }

    echo wpEventEncodeJsonPayload([
        'success' => false,
        'message' => 'Error interno al guardar el evento.'
    ]);
});

$calendarioId = isset($_POST['calendario_id']) ? intval($_POST['calendario_id']) : 0;
$montoVentaRaw = isset($_POST['monto_venta']) ? trim((string) $_POST['monto_venta']) : '';

error_log('[guardar_evento_desde_cita_wp] Request received: ' . json_encode([
    'calendario_id' => $calendarioId,
    'id_asesor' => isset($_POST['id_asesor']) ? intval($_POST['id_asesor']) : 0,
    'id_coordinador' => isset($_POST['id_coordinador']) ? intval($_POST['id_coordinador']) : 0,
    'id_paquete' => isset($_POST['id_paquete']) ? intval($_POST['id_paquete']) : 0,
    'novios' => isset($_POST['novios']) ? trim((string) $_POST['novios']) : '',
    'city' => isset($_POST['city']) ? trim((string) $_POST['city']) : '',
    'fecha' => isset($_POST['fecha']) ? trim((string) $_POST['fecha']) : '',
    'monto_venta_raw' => $montoVentaRaw
]));

if ($calendarioId <= 0) {
    wpEventJsonResponse(400, ['success' => false, 'message' => 'Cita inválida.']);
}

$montoVenta = null;
if ($montoVentaRaw !== '') {
    if (!is_numeric($montoVentaRaw)) {
        wpEventJsonResponse(400, ['success' => false, 'message' => 'Monto de venta inválido.']);
    }
    $montoVenta = round((float) $montoVentaRaw, 2);
    if ($montoVenta < 0) {
        wpEventJsonResponse(400, ['success' => false, 'message' => 'Monto de venta inválido.']);
    }
}

try {
    $appointment = wpEventResolveByCalendarId($conn, $calendarioId);
    $eventId = intval($appointment['idclie'] ?? 0);
    $weddingPlannerId = intval($appointment['wedding_planner_id'] ?? 0);

    error_log('[guardar_evento_desde_cita_wp] Appointment resolved: ' . json_encode([
        'calendario_id' => $calendarioId,
        'event_id' => $eventId,
        'wedding_planner_id' => $weddingPlannerId
    ]));

    $payload = [
        'wedding_planner_id' => $weddingPlannerId,
        'id_coordinador' => isset($_POST['id_coordinador']) ? intval($_POST['id_coordinador']) : 0,
        'novios' => isset($_POST['novios']) ? trim((string) $_POST['novios']) : '',
        'city' => isset($_POST['city']) ? trim((string) $_POST['city']) : '',
        'id_asesor' => isset($_POST['id_asesor']) ? intval($_POST['id_asesor']) : 0,
        'fecha_registro' => isset($_POST['fecha_registro']) ? trim((string) $_POST['fecha_registro']) : '',
        'lugar' => isset($_POST['lugar']) ? trim((string) $_POST['lugar']) : '',
        'fecha' => isset($_POST['fecha']) ? trim((string) $_POST['fecha']) : '',
        'modo' => isset($_POST['modo']) ? trim((string) $_POST['modo']) : 'asistencia_post_q',
        'tipo_paquete' => isset($_POST['tipo_paquete']) ? trim((string) $_POST['tipo_paquete']) : 'estandar',
        'id_paquete' => isset($_POST['id_paquete']) ? intval($_POST['id_paquete']) : 0,
        'paquete_personalizado' => isset($_POST['paquete_personalizado']) ? trim((string) $_POST['paquete_personalizado']) : ''
    ];

    $conn->begin_transaction();

    wpEventPersistExistingEvent($conn, $eventId, $payload);
    $postLeadCalendarId = wpEventEnsurePostLeadCalendarSynced($conn, $eventId, 1);
    wpEventSyncPostLeadEngagement($conn, $eventId, $calendarioId);
    wpEventUpsertPostLeadSaleAmount($conn, $eventId, $montoVenta);

    $updateAppointmentStmt = $conn->prepare('UPDATE calendario SET estatus = 1 WHERE id = ? LIMIT 1');
    if ($updateAppointmentStmt) {
        $updateAppointmentStmt->bind_param('i', $calendarioId);
        if (!$updateAppointmentStmt->execute()) {
            error_log('WP attended appointment status sync warning for calendario #' . $calendarioId . ': ' . $updateAppointmentStmt->error);
        }
        $updateAppointmentStmt->close();
    }

    $updateEventStatusStmt = $conn->prepare('UPDATE eventos_wp SET estatus = ?, fecha_actualizacion_estatus = NOW() WHERE id = ? LIMIT 1');
    if ($updateEventStatusStmt) {
        $eventStatus = '2';
        $updateEventStatusStmt->bind_param('si', $eventStatus, $eventId);
        if (!$updateEventStatusStmt->execute()) {
            error_log('WP attended event status sync warning for evento #' . $eventId . ': ' . $updateEventStatusStmt->error);
        }
        $updateEventStatusStmt->close();
    }

    $updateLeadClienteStmt = $conn->prepare("UPDATE contact_form SET cliente = 2, fecha_cambio_cliente = NOW() WHERE original_lead_id = ? AND LOWER(COALESCE(tabla_origen, '')) = 'eventos_wp'");
    if ($updateLeadClienteStmt) {
        $updateLeadClienteStmt->bind_param('i', $eventId);
        if (!$updateLeadClienteStmt->execute()) {
            error_log('WP cotizado lead sync warning for evento #' . $eventId . ': ' . $updateLeadClienteStmt->error);
        }
        $updateLeadClienteStmt->close();
    }

    try {
        wpCitasSyncLeadByEventId($conn, $eventId);
    } catch (Exception $syncError) {
        error_log('WP attended event sync warning for evento #' . $eventId . ': ' . $syncError->getMessage());
    }

    $conn->commit();

    error_log('[guardar_evento_desde_cita_wp] Success: ' . json_encode([
        'calendario_id' => $calendarioId,
        'evento_id' => $eventId,
        'post_lead_calendar_id' => $postLeadCalendarId,
        'monto_venta' => $montoVenta
    ]));

    wpEventJsonResponse(200, [
        'success' => true,
        'message' => 'El evento se guardó y ya aparece en post leads.',
        'evento_id' => $eventId,
        'post_lead_calendar_id' => $postLeadCalendarId
    ]);
} catch (Exception $e) {
    error_log('WP attended event save error for calendario #' . $calendarioId . ': ' . $e->getMessage());
    error_log('[guardar_evento_desde_cita_wp] Exception trace: ' . $e->getTraceAsString());
    @ $conn->rollback();
    wpEventJsonResponse(500, ['success' => false, 'message' => $e->getMessage()]);
}
