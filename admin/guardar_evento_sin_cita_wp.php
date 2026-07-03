<?php
ob_start();
include 'conn.php';
require_once __DIR__ . '/usuario_roles_helper.php';
require_once __DIR__ . '/evento_wp_post_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function wpEventNoAppointmentJsonResponse($statusCode, array $payload)
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

    echo json_encode([
        'success' => false,
        'message' => 'Error interno al crear el evento sin cita.'
    ]);
});

$weddingPlannerId = isset($_POST['wedding_planner_id']) ? intval($_POST['wedding_planner_id']) : 0;
$idAsesor = isset($_POST['id_asesor']) ? intval($_POST['id_asesor']) : 0;
$idCoordinador = isset($_POST['id_coordinador']) ? intval($_POST['id_coordinador']) : 0;
$novios = isset($_POST['novios']) ? trim((string) $_POST['novios']) : '';
$city = isset($_POST['city']) ? trim((string) $_POST['city']) : '';
$lugar = isset($_POST['lugar']) ? trim((string) $_POST['lugar']) : '';
$fecha = isset($_POST['fecha']) ? trim((string) $_POST['fecha']) : '';
$fechaRegistro = isset($_POST['fecha_registro']) ? trim((string) $_POST['fecha_registro']) : '';
$modo = isset($_POST['modo']) ? trim((string) $_POST['modo']) : 'sin_cita_directa';
$tipoPaquete = isset($_POST['tipo_paquete']) ? trim((string) $_POST['tipo_paquete']) : 'estandar';
$idPaquete = isset($_POST['id_paquete']) ? intval($_POST['id_paquete']) : 0;
$paquetePersonalizado = isset($_POST['paquete_personalizado']) ? trim((string) $_POST['paquete_personalizado']) : '';
$montoVentaRaw = isset($_POST['monto_venta']) ? trim((string) $_POST['monto_venta']) : '';

if ($weddingPlannerId <= 0) {
    wpEventNoAppointmentJsonResponse(400, ['success' => false, 'message' => 'Wedding planner inválido.']);
}
if ($novios === '') {
    wpEventNoAppointmentJsonResponse(400, ['success' => false, 'message' => 'Ingresa el nombre de los novios.']);
}
if ($city === '') {
    wpEventNoAppointmentJsonResponse(400, ['success' => false, 'message' => 'Ingresa la ciudad del evento.']);
}
if ($fechaRegistro === '') {
    wpEventNoAppointmentJsonResponse(400, ['success' => false, 'message' => 'Fecha de registro inválida.']);
}
if ($tipoPaquete !== 'estandar') {
    wpEventNoAppointmentJsonResponse(400, ['success' => false, 'message' => 'Tipo de paquete inválido.']);
}
if ($idPaquete <= 0) {
    wpEventNoAppointmentJsonResponse(400, ['success' => false, 'message' => 'Selecciona un paquete válido.']);
}

$montoVenta = 0.0;
if ($montoVentaRaw !== '') {
    if (!is_numeric($montoVentaRaw)) {
        wpEventNoAppointmentJsonResponse(400, ['success' => false, 'message' => 'Monto de venta inválido.']);
    }
    $montoVenta = round((float) $montoVentaRaw, 2);
    if ($montoVenta < 0) {
        wpEventNoAppointmentJsonResponse(400, ['success' => false, 'message' => 'Monto de venta inválido.']);
    }
}

try {
    wpEventEnsureTable($conn);
    wpEventEnsureContactFormColumns($conn);

    $packageRow = wpEventFetchPackageData($conn, $idPaquete);
    if (!$packageRow) {
        throw new Exception('El paquete seleccionado no existe.');
    }

    $stmt = $conn->prepare('SELECT id, empresa_wp, full_name, campaign_name, phone, email, city, how_long_known_us, first_contact_channel, id_vendedor_asignado FROM wedding_planners WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar el wedding planner: ' . $conn->error);
    }
    $stmt->bind_param('i', $weddingPlannerId);
    $stmt->execute();
    $plannerResult = $stmt->get_result();
    $plannerRow = $plannerResult ? $plannerResult->fetch_assoc() : null;
    $stmt->close();

    if (!$plannerRow) {
        throw new Exception('Wedding planner no encontrado.');
    }

    if ($idAsesor <= 0) {
        $idAsesor = (int) ($plannerRow['id_vendedor_asignado'] ?? 0);
    }
    if ($idAsesor <= 0) {
        throw new Exception('No hay asesor asignado en el wedding planner.');
    }

    $stmt = $conn->prepare('SELECT id, tipoUsu FROM usuarios WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar el asesor: ' . $conn->error);
    }
    $stmt->bind_param('i', $idAsesor);
    $stmt->execute();
    $advisorResult = $stmt->get_result();
    $advisorRow = $advisorResult ? $advisorResult->fetch_assoc() : null;
    $stmt->close();

    if (!$advisorRow || !usuarioTipoEsAsesorEventoWp($advisorRow['tipoUsu'] ?? -1)) {
        throw new Exception('Asesor inválido.');
    }

    if ($idCoordinador > 0) {
        $stmt = $conn->prepare('SELECT id FROM coordinadores_wp WHERE id = ? AND id_wp = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo validar el coordinador: ' . $conn->error);
        }
        $stmt->bind_param('ii', $idCoordinador, $weddingPlannerId);
        $stmt->execute();
        $coordResult = $stmt->get_result();
        $coordRow = $coordResult ? $coordResult->fetch_assoc() : null;
        $stmt->close();

        if (!$coordRow) {
            throw new Exception('Coordinador inválido para este wedding planner.');
        }
    }

    $fechaDb = wpEventNormalizeDateTime($fecha);
    $fechaRegistroDb = wpEventNormalizeDateTime($fechaRegistro);
    if ($fechaRegistroDb === null) {
        throw new Exception('Fecha de registro inválida.');
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare('INSERT INTO eventos_wp (wedding_planner_id, id_coordinador, ciudad_novios, lugar, fecha, fecha_registro, novios, id_asesor, modo, tipo_paquete, id_paquete, paquete_personalizado, created_time, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la inserción del evento: ' . $conn->error);
    }
    $eventStatus = '2';
    $stmt->bind_param('iisssssississs', $weddingPlannerId, $idCoordinador, $city, $lugar, $fechaDb, $fechaRegistroDb, $novios, $idAsesor, $modo, $tipoPaquete, $idPaquete, $paquetePersonalizado, $fechaRegistroDb, $eventStatus);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo crear el evento: ' . $stmt->error);
    }
    $eventId = intval($stmt->insert_id);
    $stmt->close();

    if ($eventId <= 0) {
        throw new Exception('No se pudo obtener el ID del evento creado.');
    }

    $touchStatusDateStmt = $conn->prepare('UPDATE eventos_wp SET fecha_actualizacion_estatus = NOW() WHERE id = ? LIMIT 1');
    if ($touchStatusDateStmt) {
        $touchStatusDateStmt->bind_param('i', $eventId);
        if (!$touchStatusDateStmt->execute()) {
            error_log('WP event without appointment status date sync warning for evento #' . $eventId . ': ' . $touchStatusDateStmt->error);
        }
        $touchStatusDateStmt->close();
    }

    wpPlannerStampEventStatusMilestones($conn, $eventId, 2);

    wpEventEnsureContactFormColumns($conn);

    $plannerName = wpEventFirstNonEmptyValue($plannerRow['empresa_wp'] ?? '', $plannerRow['full_name'] ?? '', $plannerRow['campaign_name'] ?? '', 'Evento sin cita');
    $plannerPhone = trim((string) ($plannerRow['phone'] ?? ''));
    $plannerEmail = trim((string) ($plannerRow['email'] ?? ''));
    $plannerCity = wpEventFirstNonEmptyValue($city, $plannerRow['city'] ?? '');
    $weddingDate = !empty($fechaDb) ? date('Y-m-d', strtotime($fechaDb)) : null;
    $weddingLocation = wpEventFirstNonEmptyValue($lugar, 'Sin lugar');
    $firstContactChannel = trim((string) ($plannerRow['first_contact_channel'] ?? ''));
    $campaignName = $firstContactChannel;
    $howLongKnownUs = trim((string) ($plannerRow['how_long_known_us'] ?? ''));
    $cliente = 2;
    $fechaCambioCliente = date('Y-m-d', strtotime($fechaRegistroDb));
    $engagement = 0;
    $paquete = (string) intval($idPaquete);
    $paqueteCotizado = trim((string) ($packageRow['nombre'] ?? ''));
    $manual = 1;
    $desdePublicidad = 1;
    $howDidYouMeet = '1';
    $tipoCliente = 1;
    $submissionDate = $fechaRegistroDb;
    $createdTime = $fechaRegistroDb;
    $dateAppointment = $weddingDate;
    $timeAppointment = !empty($fechaDb) ? date('H:i:s', strtotime($fechaDb)) : null;
    $tablaOrigen = 'eventos_wp';
    $formName = 'eventos_wp';

    $stmt = $conn->prepare('INSERT INTO contact_form (names, telephone, country_code, email_address, wedding_date, wedding_location, city, campaign_name, original_lead_id, tabla_origen, form_name, how_long_known_us, first_contact_channel, cliente, fecha_cambio_cliente, id_vendedor_asignado, engagement, paquete, paquete_cotizado, manual, desde_publicidad, how_did_you_meet, tipo_cliente, evento_wp_id, wp_id, submission_date, created_time, date_appointment, time_appointment, monto_venta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la inserción en contact_form: ' . $conn->error);
    }

    $countryCode = '';
    $contactFormInsertTypes = 'ssssssss' // names .. campaign_name
        . 'i'                             // original_lead_id
        . 'ssss'                          // tabla_origen, form_name, how_long_known_us, first_contact_channel
        . 'isiiss'                        // cliente, fecha_cambio, vendedor, engagement, paquete, cotizado
        . 'ii'                            // manual, desde_publicidad
        . 's'                             // how_did_you_meet
        . 'iii'                           // tipo_cliente, evento_wp_id, wp_id
        . 'ssss'                          // submission_date, created_time, date/time appointment
        . 'd';                            // monto_venta
    $stmt->bind_param($contactFormInsertTypes, $plannerName, $plannerPhone, $countryCode, $plannerEmail, $weddingDate, $weddingLocation, $plannerCity, $campaignName, $eventId, $tablaOrigen, $formName, $howLongKnownUs, $firstContactChannel, $cliente, $fechaCambioCliente, $idAsesor, $engagement, $paquete, $paqueteCotizado, $manual, $desdePublicidad, $howDidYouMeet, $tipoCliente, $eventId, $weddingPlannerId, $submissionDate, $createdTime, $dateAppointment, $timeAppointment, $montoVenta);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo crear el registro en contact_form: ' . $stmt->error);
    }
    $contactFormId = intval($stmt->insert_id);
    $stmt->close();

    $conn->commit();

    wpEventNoAppointmentJsonResponse(200, [
        'success' => true,
        'message' => 'Evento sin cita creado correctamente.',
        'evento_id' => $eventId,
        'contact_form_id' => $contactFormId
    ]);
} catch (Exception $e) {
    @ $conn->rollback();
    error_log('WP event without appointment save error for planner #' . $weddingPlannerId . ': ' . $e->getMessage());
    wpEventNoAppointmentJsonResponse(500, ['success' => false, 'message' => $e->getMessage()]);
}
