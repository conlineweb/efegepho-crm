<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');

include 'conn.php';
require_once __DIR__ . '/calendario_estatus_historial_helper.php';
require_once __DIR__ . '/wp_eventos_contact_form_helper.php';
require_once __DIR__ . '/evento_wp_post_helper.php';

function agendaLeadJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['login'])) {
    agendaLeadJsonResponse(401, ['success' => false, 'message' => 'Sesión no válida.']);
}

$kind = strtolower(trim((string) ($_POST['kind'] ?? '')));
$contactFormId = (int) ($_POST['contact_form_id'] ?? 0);
$eventId = (int) ($_POST['event_id'] ?? 0);
$calendarioId = (int) ($_POST['calendario_id'] ?? 0);
$fecha = trim((string) ($_POST['fecha'] ?? ''));
$hora = trim((string) ($_POST['hora'] ?? ''));

if ($fecha === '') {
    agendaLeadJsonResponse(400, ['success' => false, 'message' => 'La fecha es obligatoria.']);
}

$horaNormalized = $hora !== '' ? $hora : '00:00';
if (preg_match('/^\d{2}:\d{2}$/', $horaNormalized)) {
    $horaNormalized .= ':00';
}

$fechaTs = strtotime($fecha . ' ' . $horaNormalized);
if ($fechaTs === false || $fechaTs <= 0) {
    agendaLeadJsonResponse(400, ['success' => false, 'message' => 'Fecha u hora inválida.']);
}

$fechaRegistroDb = date('Y-m-d H:i:s', $fechaTs);
$dateAppointment = date('Y-m-d', $fechaTs);
$timeAppointment = date('H:i:s', $fechaTs);
$displayFormatted = date('d/m/Y h:i a', $fechaTs);

try {
    if ($kind === 'eventos_wp') {
        if ($eventId <= 0) {
            agendaLeadJsonResponse(400, ['success' => false, 'message' => 'Evento de planner inválido.']);
        }

        $stmt = $conn->prepare('SELECT id FROM eventos_wp WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo validar el evento.');
        }
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $eventRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$eventRow) {
            agendaLeadJsonResponse(404, ['success' => false, 'message' => 'Evento de Wedding Planner no encontrado.']);
        }

        wpEventEnsureStatusMilestoneColumns($conn);

        $stmt = $conn->prepare('UPDATE eventos_wp SET fecha_agendado = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización del evento.');
        }
        $stmt->bind_param('si', $fechaRegistroDb, $eventId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar la fecha de agenda del evento.');
        }
        $stmt->close();

        agendaLeadJsonResponse(200, [
            'success' => true,
            'message' => 'Fecha de agenda del evento actualizada.',
            'display' => $displayFormatted,
            'data_order' => $fechaTs,
        ]);
    }

    if ($kind !== 'lead') {
        agendaLeadJsonResponse(400, ['success' => false, 'message' => 'Tipo de registro no soportado.']);
    }

    if ($contactFormId <= 0) {
        agendaLeadJsonResponse(400, ['success' => false, 'message' => 'Lead inválido.']);
    }

    $stmt = $conn->prepare('SELECT id, tabla_origen, form_name FROM contact_form WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo consultar el lead.');
    }
    $stmt->bind_param('i', $contactFormId);
    $stmt->execute();
    $cfRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cfRow) {
        agendaLeadJsonResponse(404, ['success' => false, 'message' => 'Lead no encontrado.']);
    }

    if (wpEventosCfIsContactForm($cfRow)) {
        agendaLeadJsonResponse(400, [
            'success' => false,
            'message' => 'Este registro pertenece a un evento de Wedding Planner. Edítalo como evento WP, no como lead comercial.',
        ]);
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare('UPDATE contact_form SET date_appointment = ?, time_appointment = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo preparar contact_form.');
    }
    $stmt->bind_param('ssi', $dateAppointment, $timeAppointment, $contactFormId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar contact_form.');
    }
    $stmt->close();

    ensureCalendarioFechaRegistroColumn($conn);

    if ($calendarioId > 0) {
        $stmt = $conn->prepare('UPDATE calendario SET fecha_registro = ? WHERE id = ? AND idclie = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar calendario.');
        }
        $stmt->bind_param('sii', $fechaRegistroDb, $calendarioId, $contactFormId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare('SELECT id FROM calendario WHERE idclie = ? ORDER BY id DESC LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $contactFormId);
            $stmt->execute();
            $calRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $latestCalId = (int) ($calRow['id'] ?? 0);
            if ($latestCalId > 0) {
                $stmt = $conn->prepare('UPDATE calendario SET fecha_registro = ? WHERE id = ? AND idclie = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('sii', $fechaRegistroDb, $latestCalId, $contactFormId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    $conn->commit();

    agendaLeadJsonResponse(200, [
        'success' => true,
        'message' => 'Fecha en que agendó actualizada.',
        'display' => $displayFormatted,
        'data_order' => $fechaTs,
    ]);
} catch (Exception $e) {
    @ $conn->rollback();
    agendaLeadJsonResponse(500, ['success' => false, 'message' => $e->getMessage()]);
}
