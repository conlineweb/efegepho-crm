<?php
ob_start();
include 'conn.php';
require_once __DIR__ . '/usuario_roles_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function plannerEditEventJsonResponse($statusCode, array $payload)
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

function plannerEditEventNormalizeDateTime($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

$eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$plannerId = isset($_POST['wedding_planner_id']) ? intval($_POST['wedding_planner_id']) : 0;
$idCoordinador = isset($_POST['id_coordinador']) ? intval($_POST['id_coordinador']) : 0;
$idAsesor = isset($_POST['id_asesor']) ? intval($_POST['id_asesor']) : 0;
$idPaquete = isset($_POST['id_paquete']) ? intval($_POST['id_paquete']) : 0;
$novios = trim((string) ($_POST['novios'] ?? ''));
$city = trim((string) ($_POST['city'] ?? ''));
$lugar = trim((string) ($_POST['lugar'] ?? ''));
$fechaRaw = trim((string) ($_POST['fecha'] ?? ''));
$montoVentaRaw = trim((string) ($_POST['monto_venta'] ?? ''));

if ($eventId <= 0 || $plannerId <= 0) {
    plannerEditEventJsonResponse(400, ['success' => false, 'message' => 'Evento inválido.']);
}
if ($novios === '') {
    plannerEditEventJsonResponse(400, ['success' => false, 'message' => 'Escribe el nombre de los novios.']);
}
if ($city === '') {
    plannerEditEventJsonResponse(400, ['success' => false, 'message' => 'Escribe la ciudad del evento.']);
}
if ($idCoordinador <= 0) {
    plannerEditEventJsonResponse(400, ['success' => false, 'message' => 'Selecciona un coordinador.']);
}
if ($idAsesor <= 0) {
    plannerEditEventJsonResponse(400, ['success' => false, 'message' => 'Selecciona un asesor.']);
}
if ($idPaquete <= 0) {
    plannerEditEventJsonResponse(400, ['success' => false, 'message' => 'Selecciona un paquete.']);
}

$fechaDb = plannerEditEventNormalizeDateTime($fechaRaw);
if ($fechaRaw !== '' && $fechaDb === null) {
    plannerEditEventJsonResponse(400, ['success' => false, 'message' => 'La fecha del evento es inválida.']);
}

$montoVenta = null;
if ($montoVentaRaw !== '') {
    if (!is_numeric($montoVentaRaw)) {
        plannerEditEventJsonResponse(400, ['success' => false, 'message' => 'Monto de la venta inválido.']);
    }

    $montoVenta = round((float) $montoVentaRaw, 2);
    if ($montoVenta < 0) {
        plannerEditEventJsonResponse(400, ['success' => false, 'message' => 'Monto de la venta inválido.']);
    }
}

try {
    $stmt = $conn->prepare('SELECT id, wedding_planner_id FROM eventos_wp WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar el evento: ' . $conn->error);
    }

    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $eventRow = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$eventRow || intval($eventRow['wedding_planner_id'] ?? 0) !== $plannerId) {
        plannerEditEventJsonResponse(404, ['success' => false, 'message' => 'Evento no encontrado para este wedding planner.']);
    }

    $stmt = $conn->prepare('SELECT id FROM coordinadores_wp WHERE id = ? AND id_wp = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar el coordinador: ' . $conn->error);
    }
    $stmt->bind_param('ii', $idCoordinador, $plannerId);
    $stmt->execute();
    $coordRes = $stmt->get_result();
    $coordRow = $coordRes ? $coordRes->fetch_assoc() : null;
    $stmt->close();

    if (!$coordRow) {
        plannerEditEventJsonResponse(400, ['success' => false, 'message' => 'Coordinador inválido para este wedding planner.']);
    }

    $stmt = $conn->prepare('SELECT tipoUsu FROM usuarios WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar el asesor: ' . $conn->error);
    }
    $stmt->bind_param('i', $idAsesor);
    $stmt->execute();
    $advisorRes = $stmt->get_result();
    $advisorRow = $advisorRes ? $advisorRes->fetch_assoc() : null;
    $stmt->close();

    if (!$advisorRow || !usuarioTipoPuedeAsignarSesionWp($advisorRow['tipoUsu'] ?? -1)) {
        plannerEditEventJsonResponse(400, ['success' => false, 'message' => 'Asesor inválido.']);
    }

    $stmt = $conn->prepare('SELECT id, nombre FROM paquetes WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar el paquete: ' . $conn->error);
    }
    $stmt->bind_param('i', $idPaquete);
    $stmt->execute();
    $packageRes = $stmt->get_result();
    $packageRow = $packageRes ? $packageRes->fetch_assoc() : null;
    $stmt->close();

    if (!$packageRow) {
        plannerEditEventJsonResponse(400, ['success' => false, 'message' => 'Paquete inválido.']);
    }

    $conn->begin_transaction();

    $tipoPaquete = 'estandar';
    $paquetePersonalizado = '';
    $stmt = $conn->prepare('UPDATE eventos_wp
        SET id_coordinador = ?,
            id_asesor = ?,
            id_paquete = ?,
            tipo_paquete = ?,
            paquete_personalizado = ?,
            novios = ?,
            ciudad_novios = ?,
            lugar = ?,
            fecha = ?,
            fecha_actualizacion_estatus = NOW()
        WHERE id = ? AND wedding_planner_id = ?
        LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualización del evento: ' . $conn->error);
    }

    $stmt->bind_param(
        'iiissssssii',
        $idCoordinador,
        $idAsesor,
        $idPaquete,
        $tipoPaquete,
        $paquetePersonalizado,
        $novios,
        $city,
        $lugar,
        $fechaDb,
        $eventId,
        $plannerId
    );

    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar el evento: ' . $stmt->error);
    }
    $stmt->close();

    $weddingDate = null;
    if ($fechaDb !== null) {
        $weddingDate = date('Y-m-d', strtotime($fechaDb));
    }

    $packageName = trim((string) ($packageRow['nombre'] ?? ''));
    $packageValue = (string) $idPaquete;

    if ($montoVenta === null) {
        $stmt = $conn->prepare("UPDATE contact_form
            SET names = ?,
                city = ?,
                wedding_location = ?,
                wedding_date = ?,
                paquete = ?,
                paquete_cotizado = ?,
                monto_venta = NULL
            WHERE original_lead_id = ?
              AND LOWER(COALESCE(tabla_origen, '')) = 'eventos_wp'");
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización de contact_form: ' . $conn->error);
        }

        $stmt->bind_param('ssssssi', $novios, $city, $lugar, $weddingDate, $packageValue, $packageName, $eventId);
    } else {
        $stmt = $conn->prepare("UPDATE contact_form
            SET names = ?,
                city = ?,
                wedding_location = ?,
                wedding_date = ?,
                paquete = ?,
                paquete_cotizado = ?,
                monto_venta = ?
            WHERE original_lead_id = ?
              AND LOWER(COALESCE(tabla_origen, '')) = 'eventos_wp'");
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización de contact_form: ' . $conn->error);
        }

        $stmt->bind_param('ssssssdi', $novios, $city, $lugar, $weddingDate, $packageValue, $packageName, $montoVenta, $eventId);
    }

    if (!$stmt->execute()) {
        throw new Exception('No se pudo sincronizar contact_form: ' . $stmt->error);
    }
    $stmt->close();

    $conn->commit();

    plannerEditEventJsonResponse(200, ['success' => true, 'message' => 'Evento actualizado correctamente.']);
} catch (Exception $e) {
    @ $conn->rollback();
    plannerEditEventJsonResponse(500, ['success' => false, 'message' => $e->getMessage()]);
}
