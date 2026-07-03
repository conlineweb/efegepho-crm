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

function atendidoLeadJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['login'])) {
    atendidoLeadJsonResponse(401, ['success' => false, 'message' => 'Sesión no válida.']);
}

$kind = strtolower(trim((string) ($_POST['kind'] ?? '')));
$contactFormId = (int) ($_POST['contact_form_id'] ?? 0);
$eventId = (int) ($_POST['event_id'] ?? 0);
$calendarioId = (int) ($_POST['calendario_id'] ?? 0);
$fecha = trim((string) ($_POST['fecha'] ?? ''));
$hora = trim((string) ($_POST['hora'] ?? ''));

if ($fecha === '') {
    atendidoLeadJsonResponse(400, ['success' => false, 'message' => 'La fecha es obligatoria.']);
}

$horaNormalized = $hora !== '' ? $hora : '00:00';
if (preg_match('/^\d{2}:\d{2}$/', $horaNormalized)) {
    $horaNormalized .= ':00';
}

$fechaTs = strtotime($fecha . ' ' . $horaNormalized);
if ($fechaTs === false || $fechaTs <= 0) {
    atendidoLeadJsonResponse(400, ['success' => false, 'message' => 'Fecha u hora inválida.']);
}

$fechaRegistroDb = date('Y-m-d H:i:s', $fechaTs);
$displayFormatted = date('d/m/Y h:i a', $fechaTs);

try {
    if ($kind === 'eventos_wp') {
        if ($eventId <= 0) {
            atendidoLeadJsonResponse(400, ['success' => false, 'message' => 'Evento de planner inválido.']);
        }

        wpEventEnsureTable($conn);

        $contactFormClienteSelect = wpPlannerSqlContactFormClienteSelect('e');
        $stmt = $conn->prepare("SELECT e.id, e.estatus, {$contactFormClienteSelect} AS contact_form_cliente
            FROM eventos_wp e
            WHERE e.id = ?
            LIMIT 1");
        if (!$stmt) {
            throw new Exception('No se pudo validar el evento.');
        }
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $eventRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$eventRow) {
            atendidoLeadJsonResponse(404, ['success' => false, 'message' => 'Evento de Wedding Planner no encontrado.']);
        }

        $statusKey = wpPlannerResolveEventStatusKey(
            $eventRow['estatus'] ?? '',
            $eventRow['contact_form_cliente'] ?? null
        );
        $allowedAttendedKeys = ['atendido', 'cliente_inminente', 'cliente'];
        if (!in_array($statusKey, $allowedAttendedKeys, true)) {
            atendidoLeadJsonResponse(400, [
                'success' => false,
                'message' => 'Solo se puede editar la fecha cuando el evento ya es Atendido o posterior.',
            ]);
        }

        $conn->begin_transaction();

        $stmt = $conn->prepare('UPDATE eventos_wp SET fecha_atendido = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización del evento.');
        }
        $stmt->bind_param('si', $fechaRegistroDb, $eventId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar la fecha de atención del evento.');
        }
        $stmt->close();

        wpEventEnsureContactFormColumns($conn);

        $wpContactFormId = 0;
        $stmt = $conn->prepare("SELECT id FROM contact_form
            WHERE original_lead_id = ?
              AND LOWER(TRIM(COALESCE(tabla_origen, ''))) = 'eventos_wp'
            ORDER BY id DESC
            LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $eventId);
            if ($stmt->execute()) {
                $cfRow = $stmt->get_result()->fetch_assoc();
                $wpContactFormId = (int) ($cfRow['id'] ?? 0);
            }
            $stmt->close();
        }

        if ($wpContactFormId > 0) {
            $stmt = $conn->prepare('UPDATE contact_form SET created_time = ?, submission_date = ? WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ssi', $fechaRegistroDb, $fechaRegistroDb, $wpContactFormId);
                $stmt->execute();
                $stmt->close();
            }
        }

        $tablesResult = $conn->query("SHOW TABLES LIKE 'wp_eventos_afianzados'");
        if ($tablesResult && $tablesResult->num_rows > 0) {
            $callDate = date('Y-m-d', $fechaTs);
            $mirrorStmt = $conn->prepare('UPDATE wp_eventos_afianzados SET choose_your_call_date_ = ? WHERE id_evento = ?');
            if ($mirrorStmt) {
                $mirrorStmt->bind_param('si', $callDate, $eventId);
                $mirrorStmt->execute();
                $mirrorStmt->close();
            }
        }

        $conn->commit();

        atendidoLeadJsonResponse(200, [
            'success' => true,
            'message' => 'Fecha de atención del evento actualizada.',
            'display' => $displayFormatted,
            'data_order' => $fechaTs,
        ]);
    }

    if ($kind !== 'lead') {
        atendidoLeadJsonResponse(400, ['success' => false, 'message' => 'Tipo de registro no soportado.']);
    }

    if ($contactFormId <= 0) {
        atendidoLeadJsonResponse(400, ['success' => false, 'message' => 'Lead inválido.']);
    }

    $stmt = $conn->prepare('SELECT id, tabla_origen, form_name, estatus, cliente FROM contact_form WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo consultar el lead.');
    }
    $stmt->bind_param('i', $contactFormId);
    $stmt->execute();
    $cfRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cfRow) {
        atendidoLeadJsonResponse(404, ['success' => false, 'message' => 'Lead no encontrado.']);
    }

    if (wpEventosCfIsContactForm($cfRow)) {
        atendidoLeadJsonResponse(400, [
            'success' => false,
            'message' => 'Este registro pertenece a un evento de Wedding Planner. Edítalo como evento WP, no como lead comercial.',
        ]);
    }

    $estatus = strtolower(trim((string) ($cfRow['estatus'] ?? '')));
    $isCliente = ($estatus === 'cliente' || (int) ($cfRow['cliente'] ?? 0) === 1);
    if ($isCliente) {
        atendidoLeadJsonResponse(400, [
            'success' => false,
            'message' => 'Este lead ya es cliente. La fecha de atención se edita solo para registros atendidos.',
        ]);
    }

    ensureCalendarioEstatusHistorialTable($conn);

    $resolved = calendarioHistorialResolveFechaAtencionForContactForm($conn, $contactFormId, $calendarioId);
    $resolvedCalendarioId = (int) ($resolved['calendario_id'] ?? 0);
    if ($resolvedCalendarioId <= 0) {
        atendidoLeadJsonResponse(400, ['success' => false, 'message' => 'No se encontró cita asociada a este lead.']);
    }

    $conn->begin_transaction();

    if (!empty($resolved['historial_atendido_id'])) {
        $histId = (int) $resolved['historial_atendido_id'];
        $stmt = $conn->prepare('UPDATE calendario_estatus_historial SET fecha_cambio = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización del historial.');
        }
        $stmt->bind_param('si', $fechaRegistroDb, $histId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar la fecha de atención.');
        }
        $stmt->close();
    } else {
        $estatusAnterior = 0;
        if ($stmtCal = $conn->prepare('SELECT estatus FROM calendario WHERE id = ? LIMIT 1')) {
            $stmtCal->bind_param('i', $resolvedCalendarioId);
            $stmtCal->execute();
            $resCal = $stmtCal->get_result();
            if ($resCal && ($calRow = $resCal->fetch_assoc())) {
                $estatusAnterior = (int) ($calRow['estatus'] ?? 0);
            }
            $stmtCal->close();
        }

        $ok = registrarCambioEstatusCalendario($conn, $resolvedCalendarioId, 1, [
            'fecha_cambio'         => $fechaRegistroDb,
            'estatus_anterior'     => $estatusAnterior,
            'origen'               => 'my_lead_board_leads.php',
            'skip_lookup_anterior' => true,
        ]);

        if (!$ok) {
            throw new Exception('No se pudo registrar la fecha de atención.');
        }
    }

    $conn->commit();

    atendidoLeadJsonResponse(200, [
        'success' => true,
        'message' => 'Fecha en que atendió actualizada.',
        'display' => $displayFormatted,
        'data_order' => $fechaTs,
    ]);
} catch (Exception $e) {
    @ $conn->rollback();
    atendidoLeadJsonResponse(500, ['success' => false, 'message' => $e->getMessage()]);
}
