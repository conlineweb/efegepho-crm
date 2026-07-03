<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');

include 'conn.php';
require_once __DIR__ . '/wp_eventos_contact_form_helper.php';
require_once __DIR__ . '/evento_wp_post_helper.php';

function cierreClienteJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['login'])) {
    cierreClienteJsonResponse(401, ['success' => false, 'message' => 'Sesión no válida.']);
}

$kind = strtolower(trim((string) ($_POST['kind'] ?? '')));
$contactFormId = (int) ($_POST['contact_form_id'] ?? 0);
$eventId = (int) ($_POST['event_id'] ?? 0);
$fecha = trim((string) ($_POST['fecha'] ?? ''));
$hora = trim((string) ($_POST['hora'] ?? ''));

if ($fecha === '') {
    cierreClienteJsonResponse(400, ['success' => false, 'message' => 'La fecha es obligatoria.']);
}

$horaNormalized = $hora !== '' ? $hora : '00:00';
if (preg_match('/^\d{2}:\d{2}$/', $horaNormalized)) {
    $horaNormalized .= ':00';
}

$fechaTs = strtotime($fecha . ' ' . $horaNormalized);
if ($fechaTs === false || $fechaTs <= 0) {
    cierreClienteJsonResponse(400, ['success' => false, 'message' => 'Fecha u hora inválida.']);
}

$fechaRegistroDb = date('Y-m-d H:i:s', $fechaTs);
$displayFormatted = date('d/m/Y h:i a', $fechaTs);

try {
    if ($kind === 'eventos_wp') {
        if ($eventId <= 0) {
            cierreClienteJsonResponse(400, ['success' => false, 'message' => 'Evento de planner inválido.']);
        }

        wpEventEnsureTable($conn);
        wpEventEnsureStatusMilestoneColumns($conn);

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
            cierreClienteJsonResponse(404, ['success' => false, 'message' => 'Evento de Wedding Planner no encontrado.']);
        }

        $statusKey = wpPlannerResolveEventStatusKey(
            $eventRow['estatus'] ?? '',
            $eventRow['contact_form_cliente'] ?? null
        );
        if ($statusKey !== 'cliente') {
            cierreClienteJsonResponse(400, [
                'success' => false,
                'message' => 'Solo se puede editar la fecha cuando el evento ya es Cliente.',
            ]);
        }

        $conn->begin_transaction();

        $stmt = $conn->prepare('UPDATE eventos_wp SET fecha_cliente = ?, fecha_actualizacion_estatus = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización del evento.');
        }
        $stmt->bind_param('ssi', $fechaRegistroDb, $fechaRegistroDb, $eventId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar la fecha de cliente del evento.');
        }
        $stmt->close();

        wpEventEnsureContactFormColumns($conn);

        $wpContactFormId = $contactFormId;
        if ($wpContactFormId <= 0) {
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
        }

        if ($wpContactFormId <= 0) {
            throw new Exception('No se encontró el registro en contact_form del evento.');
        }

        $stmt = $conn->prepare('UPDATE contact_form SET fecha_cambio_cliente = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar contact_form.');
        }
        $stmt->bind_param('si', $fechaRegistroDb, $wpContactFormId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar contact_form.');
        }
        $stmt->close();

        $conn->commit();

        cierreClienteJsonResponse(200, [
            'success' => true,
            'message' => 'Fecha de cierre del evento actualizada.',
            'display' => $displayFormatted,
            'data_order' => $fechaTs,
        ]);
    }

    if ($kind !== 'lead') {
        cierreClienteJsonResponse(400, ['success' => false, 'message' => 'Tipo de registro no soportado.']);
    }

    if ($contactFormId <= 0) {
        cierreClienteJsonResponse(400, ['success' => false, 'message' => 'Lead inválido.']);
    }

    $stmt = $conn->prepare('SELECT id, tabla_origen, form_name, cliente FROM contact_form WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo consultar el lead.');
    }
    $stmt->bind_param('i', $contactFormId);
    $stmt->execute();
    $cfRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cfRow) {
        cierreClienteJsonResponse(404, ['success' => false, 'message' => 'Lead no encontrado.']);
    }

    if (wpEventosCfIsContactForm($cfRow)) {
        cierreClienteJsonResponse(400, [
            'success' => false,
            'message' => 'Este registro pertenece a un evento de Wedding Planner. Edítalo como evento WP, no como lead comercial.',
        ]);
    }

    if ((int) ($cfRow['cliente'] ?? 0) !== 1) {
        cierreClienteJsonResponse(400, [
            'success' => false,
            'message' => 'Solo se puede editar la fecha de cierre en registros marcados como cliente.',
        ]);
    }

    $stmt = $conn->prepare('UPDATE contact_form SET fecha_cambio_cliente = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualización.');
    }
    $stmt->bind_param('si', $fechaRegistroDb, $contactFormId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar la fecha de cierre.');
    }
    $stmt->close();

    cierreClienteJsonResponse(200, [
        'success' => true,
        'message' => 'Fecha de cierre actualizada.',
        'display' => $displayFormatted,
        'data_order' => $fechaTs,
    ]);
} catch (Exception $e) {
    @ $conn->rollback();
    cierreClienteJsonResponse(500, ['success' => false, 'message' => $e->getMessage()]);
}
