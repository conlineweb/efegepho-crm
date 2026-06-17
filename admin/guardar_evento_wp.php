<?php
include 'conn.php';
header('Content-Type: application/json');
ini_set('display_errors', 1); error_reporting(E_ALL);

function first_non_empty_value()
{
    foreach (func_get_args() as $value) {
        if ($value === null) {
            continue;
        }

        $normalized = trim((string) $value);
        if ($normalized !== '') {
            return $normalized;
        }
    }

    return '';
}

function ensureColumnExists($conn, $table, $columnName, $columnDef)
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($columnName);
    $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safeTable}' AND COLUMN_NAME = '{$safeColumn}'";
    $res = $conn->query($checkSql);
    if ($res) {
        $row = $res->fetch_assoc();
        if (intval($row['c'] ?? 0) === 0) {
            $conn->query("ALTER TABLE `{$table}` ADD COLUMN {$columnDef}");
        }
    }
}

function ensureAfianzadoLeadTableExists($conn)
{
    $createSql = "CREATE TABLE IF NOT EXISTS wp_eventos_afianzados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_evento INT NOT NULL,
        id_wedding_planner INT NOT NULL,
        id_coordinador INT DEFAULT NULL,
        created_time DATETIME DEFAULT NULL,
        fecha_importacion DATETIME DEFAULT NULL,
        full_name VARCHAR(255) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        country_code VARCHAR(10) DEFAULT NULL,
        city VARCHAR(255) DEFAULT NULL,
        wedding_location VARCHAR(255) DEFAULT NULL,
        wedding_date DATE DEFAULT NULL,
        how_long_known_us VARCHAR(150) DEFAULT NULL,
        first_contact_channel VARCHAR(255) DEFAULT NULL,
        ad_id VARCHAR(100) DEFAULT NULL,
        ad_name VARCHAR(255) DEFAULT NULL,
        adset_id VARCHAR(100) DEFAULT NULL,
        adset_name VARCHAR(255) DEFAULT NULL,
        campaign_id VARCHAR(100) DEFAULT NULL,
        campaign_name VARCHAR(255) DEFAULT NULL,
        form_id VARCHAR(100) DEFAULT NULL,
        form_name VARCHAR(255) DEFAULT NULL,
        is_organic TINYINT(1) DEFAULT 1,
        platform VARCHAR(100) DEFAULT NULL,
        when_are_you_getting_married_ VARCHAR(255) DEFAULT NULL,
        where_are_you_getting_married_ VARCHAR(255) DEFAULT NULL,
        choose_your_call_date_ VARCHAR(255) DEFAULT NULL,
        lead_status VARCHAR(100) DEFAULT NULL,
        id_vendedor_asignado INT DEFAULT NULL,
        usuario_asignado INT DEFAULT NULL,
        correo_uno_enviado TINYINT(1) DEFAULT 0,
        fecha_envio_correo_uno DATETIME DEFAULT NULL,
        correo_dos_enviado TINYINT(1) DEFAULT 0,
        fecha_envio_correo_dos DATETIME DEFAULT NULL,
        whatsapp_enviado TINYINT(1) DEFAULT 0,
        fecha_envio_whatsapp DATETIME DEFAULT NULL,
        estatus_llamada TINYINT(1) DEFAULT 0,
        estatus_whatsapp TINYINT(1) DEFAULT 0,
        whatsapp_bot_sent TINYINT(1) DEFAULT 0,
        descartado TINYINT(1) DEFAULT 0,
        novios VARCHAR(255) DEFAULT NULL,
        coordinador_nombre VARCHAR(255) DEFAULT NULL,
        empresa_wp VARCHAR(255) DEFAULT NULL,
        evento_fecha DATETIME DEFAULT NULL,
        fecha_registro_evento DATETIME DEFAULT NULL,
        lugar_evento VARCHAR(255) DEFAULT NULL,
        modo_evento VARCHAR(64) DEFAULT NULL,
        tipo_paquete VARCHAR(32) DEFAULT NULL,
        id_paquete INT DEFAULT NULL,
        paquete_personalizado TEXT DEFAULT NULL,
        estatus_evento VARCHAR(20) DEFAULT NULL,
        UNIQUE KEY uniq_wp_evento_afianzado (id_evento),
        KEY idx_wp_eventos_afianzados_created_time (created_time),
        KEY idx_wp_eventos_afianzados_descartado (descartado),
        KEY idx_wp_eventos_afianzados_usuario (usuario_asignado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($createSql)) {
        throw new Exception('No se pudo crear la tabla de leads afianzados: ' . $conn->error);
    }
}

function ensureAfianzadoLeadTableRegistered($conn)
{
    $tableName = 'wp_eventos_afianzados';
    $stmt = $conn->prepare("SELECT 1 FROM tablas_leads WHERE nombre = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('No se pudo validar tablas_leads: ' . $conn->error);
    }

    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $exists = $stmt->get_result();
    $alreadyRegistered = $exists && $exists->num_rows > 0;
    $stmt->close();

    if ($alreadyRegistered) {
        return;
    }

    $columns = [];
    $columnsRes = $conn->query("SHOW COLUMNS FROM tablas_leads");
    if (!$columnsRes) {
        throw new Exception('No se pudo leer la estructura de tablas_leads: ' . $conn->error);
    }

    while ($column = $columnsRes->fetch_assoc()) {
        $columns[] = $column['Field'];
    }

    $insertColumns = [];
    $insertValues = [];
    $insertTypes = '';

    if (in_array('nombre', $columns, true)) {
        $insertColumns[] = 'nombre';
        $insertValues[] = $tableName;
        $insertTypes .= 's';
    }
    if (in_array('tipo', $columns, true)) {
        $insertColumns[] = 'tipo';
        $insertValues[] = 0;
        $insertTypes .= 'i';
    }
    if (in_array('descripcion', $columns, true)) {
        $insertColumns[] = 'descripcion';
        $insertValues[] = 'Eventos afianzados de Wedding Planner';
        $insertTypes .= 's';
    }
    if (in_array('estatus', $columns, true)) {
        $insertColumns[] = 'estatus';
        $insertValues[] = 1;
        $insertTypes .= 'i';
    }
    if (in_array('created_at', $columns, true)) {
        $insertColumns[] = 'created_at';
        $insertValues[] = date('Y-m-d H:i:s');
        $insertTypes .= 's';
    }
    if (in_array('updated_at', $columns, true)) {
        $insertColumns[] = 'updated_at';
        $insertValues[] = date('Y-m-d H:i:s');
        $insertTypes .= 's';
    }

    if (empty($insertColumns)) {
        throw new Exception('La tabla tablas_leads no tiene columnas compatibles para registrar el origen.');
    }

    $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
    $sql = "INSERT INTO tablas_leads (" . implode(', ', $insertColumns) . ") VALUES (" . $placeholders . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('No se pudo registrar la tabla de leads afianzados: ' . $conn->error);
    }

    $params = [];
    $params[] = &$insertTypes;
    foreach ($insertValues as $index => $value) {
        $insertValues[$index] = $value;
        $params[] = &$insertValues[$index];
    }

    call_user_func_array([$stmt, 'bind_param'], $params);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo insertar el origen en tablas_leads: ' . $stmt->error);
    }
    $stmt->close();
}

function fetchPackageData($conn, $packageId)
{
    $packageId = intval($packageId);
    if ($packageId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('SELECT id, nombre FROM paquetes WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo consultar la tabla de paquetes: ' . $conn->error);
    }

    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function fetchAfianzadoLeadSourceData($conn, $eventId)
{
    $sql = "SELECT
                ev.id AS id_evento,
                ev.wedding_planner_id,
                ev.id_coordinador,
                ev.lugar,
                ev.fecha,
                ev.fecha_registro,
                ev.novios,
                ev.id_asesor,
                ev.modo,
                ev.tipo_paquete,
                ev.id_paquete,
                ev.paquete_personalizado,
                p.nombre AS paquete_nombre,
                ev.created_time,
                ev.estatus AS estatus_evento,
                wp.afianzado,
                wp.campaign_name AS wp_campaign_name,
                wp.form_name AS wp_form_name,
                wp.platform AS wp_platform,
                wp.full_name AS wp_full_name,
                wp.empresa_wp,
                wp.when_are_you_getting_married_,
                wp.where_is_your_marriage_taking_place_,
                wp.id_vendedor_asignado,
                coord.nombre AS coordinador_nombre,
                coord.telefono AS coordinador_telefono,
                coord.correo AS coordinador_correo,
                coord.how_long_known_us AS coordinador_how_long_known_us,
                coord.first_contact_channel AS coordinador_first_contact_channel,
                coord.how_did_you_meet AS coordinador_engagement,
                coord.campaign_name AS coordinador_campaign_name,
                coord.platform AS coordinador_platform
            FROM eventos_wp ev
            INNER JOIN wedding_planners wp ON wp.id = ev.wedding_planner_id
            LEFT JOIN coordinadores_wp coord ON coord.id = ev.id_coordinador
            LEFT JOIN paquetes p ON p.id = ev.id_paquete
            WHERE ev.id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('No se pudo consultar la información del evento afianzado: ' . $conn->error);
    }

    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row;
}

function hideAfianzadoLeadByEvent($conn, $eventId)
{
    $stmt = $conn->prepare("UPDATE wp_eventos_afianzados SET descartado = 1 WHERE id_evento = ?");
    if (!$stmt) {
        throw new Exception('No se pudo ocultar el lead afianzado: ' . $conn->error);
    }

    $stmt->bind_param('i', $eventId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar el lead afianzado: ' . $stmt->error);
    }
    $stmt->close();

    $tablaOrigen = 'wp_eventos_afianzados';
    $stmt = $conn->prepare("DELETE cal FROM calendario cal INNER JOIN contact_form cf ON cf.id = cal.idclie INNER JOIN wp_eventos_afianzados wpa ON wpa.id = cf.original_lead_id WHERE cf.tabla_origen = ? AND wpa.id_evento = ?");
    if ($stmt) {
        $stmt->bind_param('si', $tablaOrigen, $eventId);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("DELETE cf FROM contact_form cf INNER JOIN wp_eventos_afianzados wpa ON wpa.id = cf.original_lead_id WHERE cf.tabla_origen = ? AND wpa.id_evento = ?");
    if ($stmt) {
        $stmt->bind_param('si', $tablaOrigen, $eventId);
        $stmt->execute();
        $stmt->close();
    }
}

function ensureContactFormColumnsForAfianzados($conn)
{
    ensureColumnExists($conn, 'contact_form', 'how_long_known_us', "`how_long_known_us` VARCHAR(150) DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'first_contact_channel', "`first_contact_channel` VARCHAR(255) DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'form_name', "`form_name` VARCHAR(255) DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'created_time', "`created_time` DATETIME DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'manual', "`manual` TINYINT(1) DEFAULT 0");
    ensureColumnExists($conn, 'contact_form', 'desde_publicidad', "`desde_publicidad` TINYINT(1) DEFAULT 0");
    ensureColumnExists($conn, 'contact_form', 'id_vendedor_asignado', "`id_vendedor_asignado` INT DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'how_did_you_meet', "`how_did_you_meet` VARCHAR(50) DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'engagement', "`engagement` INT DEFAULT 0");
    ensureColumnExists($conn, 'contact_form', 'paquete', "`paquete` VARCHAR(100) DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'paquete_cotizado', "`paquete_cotizado` VARCHAR(100) DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'date_appointment', "`date_appointment` DATE DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'time_appointment', "`time_appointment` TIME DEFAULT NULL");
}

function upsertAfianzadoLeadCalendar($conn, $contactFormId, array $payload)
{
    $contactFormId = intval($contactFormId);
    if ($contactFormId <= 0) {
        return;
    }

    $assignedAdvisorId = intval($payload['id_vendedor_asignado'] ?? 0);
    $fullName = trim((string) ($payload['full_name'] ?? ''));
    $eventDateTimeRaw = trim((string) ($payload['event_datetime'] ?? ''));
    $createdTimeRaw = trim((string) ($payload['created_time'] ?? ''));

    $appointmentTimestamp = 0;
    if ($eventDateTimeRaw !== '') {
        $appointmentTimestamp = strtotime($eventDateTimeRaw) ?: 0;
    }
    if ($appointmentTimestamp <= 0 && $createdTimeRaw !== '') {
        $appointmentTimestamp = strtotime($createdTimeRaw) ?: 0;
    }
    if ($appointmentTimestamp <= 0) {
        $appointmentTimestamp = time();
    }

    $appointmentDate = date('Y-m-d', $appointmentTimestamp);
    $appointmentTime = date('H:i:s', $appointmentTimestamp);
    $calendarTitle = 'Evento afianzado agendado: ' . substr($fullName !== '' ? $fullName : 'Lead afianzado', 0, 120);
    $calendarNote = 'Cita espejo generada automaticamente para mostrar el evento afianzado en post-leads.';
    $calendarStatus = 0;
    $calendarDeleted = 0;

    $stmt = $conn->prepare('SELECT id FROM calendario WHERE idclie = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar calendario para el lead afianzado: ' . $conn->error);
    }
    $stmt->bind_param('i', $contactFormId);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($existing) {
        $calendarId = intval($existing['id']);
        $stmt = $conn->prepare('UPDATE calendario SET idusu = ?, fecha = ?, fecha_cliente = ?, hora = ?, hora_cliente = ?, titulo = ?, nota = ?, estatus = ?, eliminado = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualizacion de calendario: ' . $conn->error);
        }
        $stmt->bind_param('issssssiii', $assignedAdvisorId, $appointmentDate, $appointmentDate, $appointmentTime, $appointmentTime, $calendarTitle, $calendarNote, $calendarStatus, $calendarDeleted, $calendarId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar calendario del lead afianzado: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT INTO calendario (idusu, fecha, fecha_cliente, hora, hora_cliente, titulo, nota, idclie, estatus, eliminado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la insercion en calendario: ' . $conn->error);
        }
        $stmt->bind_param('issssssiii', $assignedAdvisorId, $appointmentDate, $appointmentDate, $appointmentTime, $appointmentTime, $calendarTitle, $calendarNote, $contactFormId, $calendarStatus, $calendarDeleted);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo insertar calendario del lead afianzado: ' . $stmt->error);
        }
        $stmt->close();
    }

    $stmt = $conn->prepare('UPDATE contact_form SET date_appointment = ?, time_appointment = ? WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('ssi', $appointmentDate, $appointmentTime, $contactFormId);
        $stmt->execute();
        $stmt->close();
    }
}

function upsertAfianzadoLeadContactForm($conn, $mirrorLeadId, array $payload)
{
    ensureContactFormColumnsForAfianzados($conn);

    $tablaOrigen = 'wp_eventos_afianzados';
    $formName = 'wp_eventos_afianzados';
    $names = trim((string) ($payload['full_name'] ?? ''));
    $telephone = trim((string) ($payload['phone'] ?? ''));
    $countryCode = trim((string) ($payload['country_code'] ?? ''));
    $emailAddress = trim((string) ($payload['email'] ?? ''));
    $weddingDate = !empty($payload['wedding_date']) ? $payload['wedding_date'] : null;
    $weddingLocation = trim((string) ($payload['wedding_location'] ?? ''));
    $campaignName = trim((string) ($payload['campaign_name'] ?? ''));
    $howLongKnownUs = trim((string) ($payload['how_long_known_us'] ?? ''));
    $firstContactChannel = trim((string) ($payload['first_contact_channel'] ?? ''));
    $idVendedorAsignado = intval($payload['id_vendedor_asignado'] ?? 0);
    $submissionDate = !empty($payload['created_time']) ? $payload['created_time'] : date('Y-m-d H:i:s');
    $createdTime = $submissionDate;
    $dateAppointment = !empty($payload['date_appointment']) ? $payload['date_appointment'] : null;
    $timeAppointment = !empty($payload['time_appointment']) ? $payload['time_appointment'] : null;
    $engagement = intval($payload['engagement'] ?? 0);
    $paquete = trim((string) ($payload['paquete'] ?? ''));
    $paqueteCotizado = trim((string) ($payload['paquete_cotizado'] ?? ''));
    $fechaCambioCliente = date('Y-m-d');
    $cliente = 0;
    $manual = 1;
    $desdePublicidad = 1;
    $howDidYouMeet = '1';

    $stmt = $conn->prepare('SELECT id FROM contact_form WHERE original_lead_id = ? AND LOWER(tabla_origen) = LOWER(?) LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar contact_form para el lead afianzado: ' . $conn->error);
    }
    $stmt->bind_param('is', $mirrorLeadId, $tablaOrigen);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($existing) {
        $contactFormId = intval($existing['id']);
        $stmt = $conn->prepare('UPDATE contact_form SET names = ?, telephone = ?, country_code = ?, email_address = ?, wedding_date = ?, wedding_location = ?, campaign_name = ?, form_name = ?, how_long_known_us = ?, first_contact_channel = ?, id_vendedor_asignado = ?, created_time = ?, submission_date = ?, date_appointment = ?, time_appointment = ?, engagement = ?, paquete = ?, paquete_cotizado = ?, manual = ?, desde_publicidad = ?, how_did_you_meet = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización de contact_form: ' . $conn->error);
        }
        $stmt->bind_param('sssssssssssssssisssiisi', $names, $telephone, $countryCode, $emailAddress, $weddingDate, $weddingLocation, $campaignName, $formName, $howLongKnownUs, $firstContactChannel, $idVendedorAsignado, $createdTime, $submissionDate, $dateAppointment, $timeAppointment, $engagement, $paquete, $paqueteCotizado, $manual, $desdePublicidad, $howDidYouMeet, $contactFormId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar contact_form del lead afianzado: ' . $stmt->error);
        }
        $stmt->close();
        return $contactFormId;
    }

    $stmt = $conn->prepare('INSERT INTO contact_form (names, telephone, country_code, email_address, wedding_date, wedding_location, campaign_name, original_lead_id, tabla_origen, form_name, how_long_known_us, first_contact_channel, cliente, fecha_cambio_cliente, id_vendedor_asignado, engagement, paquete, paquete_cotizado, manual, desde_publicidad, how_did_you_meet, submission_date, created_time, date_appointment, time_appointment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la inserción en contact_form: ' . $conn->error);
    }
    $stmt->bind_param('sssssssissssiisissiisssss', $names, $telephone, $countryCode, $emailAddress, $weddingDate, $weddingLocation, $campaignName, $mirrorLeadId, $tablaOrigen, $formName, $howLongKnownUs, $firstContactChannel, $cliente, $fechaCambioCliente, $idVendedorAsignado, $engagement, $paquete, $paqueteCotizado, $manual, $desdePublicidad, $howDidYouMeet, $submissionDate, $createdTime, $dateAppointment, $timeAppointment);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo insertar contact_form del lead afianzado: ' . $stmt->error);
    }
    $contactFormId = $stmt->insert_id;
    $stmt->close();

    $idAsesorCheck = $conn->query("SHOW COLUMNS FROM contact_form LIKE 'id_asesor_asignado'");
    if ($idAsesorCheck && $idAsesorCheck->num_rows > 0 && $idVendedorAsignado > 0) {
        $stmt = $conn->prepare('UPDATE contact_form SET id_asesor_asignado = ? WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ii', $idVendedorAsignado, $contactFormId);
            $stmt->execute();
            $stmt->close();
        }
    }

    return $contactFormId;
}

function syncAfianzadoLeadForEvent($conn, $eventId)
{
    ensureAfianzadoLeadTableExists($conn);
    ensureAfianzadoLeadTableRegistered($conn);

    $source = fetchAfianzadoLeadSourceData($conn, $eventId);
    if (!$source) {
        throw new Exception('No se encontró la información del evento para sincronizar el lead afianzado.');
    }

    if (intval($source['afianzado'] ?? 0) !== 1) {
        hideAfianzadoLeadByEvent($conn, $eventId);
        return;
    }

    $createdTime = first_non_empty_value($source['created_time'] ?? '', date('Y-m-d H:i:s'));
    $campaignName = first_non_empty_value(
        $source['coordinador_campaign_name'] ?? '',
        $source['wp_campaign_name'] ?? '',
        $source['empresa_wp'] ?? '',
        'Wedding Planner Afianzado'
    );
    $platform = first_non_empty_value($source['coordinador_platform'] ?? '', $source['wp_platform'] ?? '', 'Wedding Planner');
    $fullName = first_non_empty_value($source['coordinador_nombre'] ?? '', $source['empresa_wp'] ?? '', $source['wp_full_name'] ?? '', $source['novios'] ?? '', 'Evento afianzado');
    $weddingLocation = first_non_empty_value($source['lugar'] ?? '', $source['where_is_your_marriage_taking_place_'] ?? '');
    $eventDate = !empty($source['fecha']) ? date('Y-m-d', strtotime($source['fecha'])) : null;
    $weddingDate = $eventDate ?: (!empty($source['when_are_you_getting_married_']) ? date('Y-m-d', strtotime($source['when_are_you_getting_married_'])) : null);
    $whenMarried = $weddingDate ?: null;
    $callDate = !empty($source['fecha_registro']) ? date('Y-m-d', strtotime($source['fecha_registro'])) : null;
    $assignedAdvisorId = intval($source['id_asesor'] ?? 0) > 0 ? intval($source['id_asesor']) : intval($source['id_vendedor_asignado'] ?? 0);
    $formName = 'wp_eventos_afianzados';
    $leadStatus = 'wp_evento_afianzado';
    $phone = trim((string) ($source['coordinador_telefono'] ?? ''));
    $email = trim((string) ($source['coordinador_correo'] ?? ''));
    $countryCode = '';
    $city = '';
    $howLongKnownUs = trim((string) ($source['coordinador_how_long_known_us'] ?? ''));
    $firstContactChannel = trim((string) ($source['coordinador_first_contact_channel'] ?? ''));
    $engagement = intval($source['coordinador_engagement'] ?? 0);
    $eventoFecha = !empty($source['fecha']) ? date('Y-m-d H:i:s', strtotime($source['fecha'])) : null;
    $fechaRegistroEvento = !empty($source['fecha_registro']) ? date('Y-m-d H:i:s', strtotime($source['fecha_registro'])) : null;
    $eventoLugar = trim((string) ($source['lugar'] ?? ''));
    $coordinadorNombre = trim((string) ($source['coordinador_nombre'] ?? ''));
    $empresaWp = first_non_empty_value($source['empresa_wp'] ?? '', $source['wp_full_name'] ?? '', $campaignName);
    $tipoPaquete = trim((string) ($source['tipo_paquete'] ?? ''));
    $idPaquete = intval($source['id_paquete'] ?? 0);
    $paquetePersonalizado = trim((string) ($source['paquete_personalizado'] ?? ''));
    $paqueteNombre = trim((string) ($source['paquete_nombre'] ?? ''));
    $paqueteContactForm = $tipoPaquete === 'estandar' && $idPaquete > 0 ? (string) $idPaquete : '';
    $paqueteCotizado = $tipoPaquete === 'personalizado' ? $paquetePersonalizado : $paqueteNombre;
    $modoEvento = trim((string) ($source['modo'] ?? ''));
    $estatusEvento = trim((string) ($source['estatus_evento'] ?? ''));
    $novios = trim((string) ($source['novios'] ?? ''));
    $idEvento = intval($source['id_evento']);
    $idWeddingPlanner = intval($source['wedding_planner_id']);
    $idCoordinador = intval($source['id_coordinador'] ?? 0);

    $checkStmt = $conn->prepare("SELECT id FROM wp_eventos_afianzados WHERE id_evento = ? LIMIT 1");
    if (!$checkStmt) {
        throw new Exception('No se pudo validar el lead afianzado existente: ' . $conn->error);
    }
    $checkStmt->bind_param('i', $idEvento);
    $checkStmt->execute();
    $existingResult = $checkStmt->get_result();
    $existingRow = $existingResult ? $existingResult->fetch_assoc() : null;
    $checkStmt->close();

    $mirrorLeadId = intval($existingRow['id'] ?? 0);

    if ($existingRow) {
        $stmt = $conn->prepare("UPDATE wp_eventos_afianzados
            SET id_wedding_planner = ?, id_coordinador = ?, created_time = ?, fecha_importacion = ?, full_name = ?, email = ?, phone = ?, country_code = ?, city = ?,
                wedding_location = ?, wedding_date = ?, how_long_known_us = ?, first_contact_channel = ?, campaign_name = ?, form_name = ?, platform = ?,
                when_are_you_getting_married_ = ?, where_are_you_getting_married_ = ?, choose_your_call_date_ = ?, lead_status = ?, id_vendedor_asignado = ?,
                usuario_asignado = ?, is_organic = 1, descartado = 0, novios = ?, coordinador_nombre = ?, empresa_wp = ?, evento_fecha = ?, fecha_registro_evento = ?,
                lugar_evento = ?, modo_evento = ?, tipo_paquete = ?, id_paquete = ?, paquete_personalizado = ?, estatus_evento = ?
            WHERE id_evento = ?");
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización del lead afianzado: ' . $conn->error);
        }

        $updateTypes = 'ii' . str_repeat('s', 18) . 'ii' . str_repeat('s', 8) . 'i' . 'ss' . 'i';
        $stmt->bind_param(
            $updateTypes,
            $idWeddingPlanner,
            $idCoordinador,
            $createdTime,
            $createdTime,
            $fullName,
            $email,
            $phone,
            $countryCode,
            $city,
            $weddingLocation,
            $weddingDate,
            $howLongKnownUs,
            $firstContactChannel,
            $campaignName,
            $formName,
            $platform,
            $whenMarried,
            $weddingLocation,
            $callDate,
            $leadStatus,
            $assignedAdvisorId,
            $assignedAdvisorId,
            $novios,
            $coordinadorNombre,
            $empresaWp,
            $eventoFecha,
            $fechaRegistroEvento,
            $eventoLugar,
            $modoEvento,
            $tipoPaquete,
            $idPaquete,
            $paquetePersonalizado,
            $estatusEvento,
            $idEvento
        );
    } else {
        $stmt = $conn->prepare("INSERT INTO wp_eventos_afianzados (
                id_evento, id_wedding_planner, id_coordinador, created_time, fecha_importacion, full_name, email, phone, country_code, city,
                wedding_location, wedding_date, how_long_known_us, first_contact_channel, campaign_name, form_name, platform,
                when_are_you_getting_married_, where_are_you_getting_married_, choose_your_call_date_, lead_status, id_vendedor_asignado,
                usuario_asignado, is_organic, descartado, novios, coordinador_nombre, empresa_wp, evento_fecha, fecha_registro_evento,
                lugar_evento, modo_evento, tipo_paquete, id_paquete, paquete_personalizado, estatus_evento
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, 1, 0, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?
            )");
        if (!$stmt) {
            throw new Exception('No se pudo preparar la inserción del lead afianzado: ' . $conn->error);
        }

        $insertTypes = 'iii' . str_repeat('s', 18) . 'ii' . str_repeat('s', 8) . 'i' . 'ss';
        $stmt->bind_param(
            $insertTypes,
            $idEvento,
            $idWeddingPlanner,
            $idCoordinador,
            $createdTime,
            $createdTime,
            $fullName,
            $email,
            $phone,
            $countryCode,
            $city,
            $weddingLocation,
            $weddingDate,
            $howLongKnownUs,
            $firstContactChannel,
            $campaignName,
            $formName,
            $platform,
            $whenMarried,
            $weddingLocation,
            $callDate,
            $leadStatus,
            $assignedAdvisorId,
            $assignedAdvisorId,
            $novios,
            $coordinadorNombre,
            $empresaWp,
            $eventoFecha,
            $fechaRegistroEvento,
            $eventoLugar,
            $modoEvento,
            $tipoPaquete,
            $idPaquete,
            $paquetePersonalizado,
            $estatusEvento
        );
    }

    if (!$stmt->execute()) {
        throw new Exception('No se pudo sincronizar el lead afianzado: ' . $stmt->error);
    }
    if (!$existingRow) {
        $mirrorLeadId = intval($stmt->insert_id);
    }
    $stmt->close();

    if ($mirrorLeadId > 0) {
        $appointmentDate = null;
        $appointmentTime = null;
        if (!empty($eventoFecha)) {
            $appointmentDate = date('Y-m-d', strtotime($eventoFecha));
            $appointmentTime = date('H:i:s', strtotime($eventoFecha));
        } elseif (!empty($createdTime)) {
            $appointmentDate = date('Y-m-d', strtotime($createdTime));
            $appointmentTime = date('H:i:s', strtotime($createdTime));
        }

        $contactFormId = upsertAfianzadoLeadContactForm($conn, $mirrorLeadId, [
            'full_name' => $fullName,
            'phone' => $phone,
            'country_code' => $countryCode,
            'email' => $email,
            'wedding_date' => $weddingDate,
            'wedding_location' => $weddingLocation,
            'campaign_name' => $campaignName,
            'how_long_known_us' => $howLongKnownUs,
            'first_contact_channel' => $firstContactChannel,
            'engagement' => $engagement,
            'paquete' => $paqueteContactForm,
            'paquete_cotizado' => $paqueteCotizado,
            'id_vendedor_asignado' => $assignedAdvisorId,
            'created_time' => $createdTime,
            'date_appointment' => $appointmentDate,
            'time_appointment' => $appointmentTime
        ]);

        upsertAfianzadoLeadCalendar($conn, $contactFormId, [
            'full_name' => $fullName,
            'id_vendedor_asignado' => $assignedAdvisorId,
            'event_datetime' => $eventoFecha,
            'created_time' => $createdTime
        ]);
    }
}

$data = $_POST;
$id = isset($data['id']) ? intval($data['id']) : 0;
$wp_id = isset($data['wedding_planner_id']) ? intval($data['wedding_planner_id']) : 0;
$id_coordinador = isset($data['id_coordinador']) ? intval($data['id_coordinador']) : 0;
$lugar = isset($data['lugar']) ? trim($data['lugar']) : '';
$fecha = isset($data['fecha']) ? trim($data['fecha']) : null;
$fecha_registro = isset($data['fecha_registro']) ? trim($data['fecha_registro']) : null;
$novios = isset($data['novios']) ? trim($data['novios']) : '';
$id_asesor = isset($data['id_asesor']) ? intval($data['id_asesor']) : 0;
$modo = isset($data['modo']) ? trim($data['modo']) : '';
$tipo_paquete = isset($data['tipo_paquete']) ? trim($data['tipo_paquete']) : '';
$id_paquete = isset($data['id_paquete']) ? intval($data['id_paquete']) : 0;
$paquete_personalizado = isset($data['paquete_personalizado']) ? trim($data['paquete_personalizado']) : '';
if ($wp_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Seleccione un Wedding Planner']);
    exit;
}
if ($id_coordinador <= 0) {
    echo json_encode(['success' => false, 'message' => 'Seleccione un coordinador']);
    exit;
}
if ($novios === '') {
    echo json_encode(['success' => false, 'message' => 'Ingrese el nombre de los novios']);
    exit;
}
if ($id_asesor <= 0) {
    echo json_encode(['success' => false, 'message' => 'Seleccione un asesor']);
    exit;
}
if ($modo === '') {
    $modo = 'asistencia_post_q';
}
if (empty($fecha_registro)) {
    echo json_encode(['success' => false, 'message' => 'Seleccione la fecha de registro']);
    exit;
}
// Paquete ya no es obligatorio desde la UI. Aceptar valores por defecto para evitar errores.
if (!in_array($tipo_paquete, ['estandar', 'personalizado'])) {
    $tipo_paquete = 'estandar';
}
// Normalize/sanitize package values to safe defaults
$id_paquete = max(0, $id_paquete);
$paquete_personalizado = $paquete_personalizado !== null ? $paquete_personalizado : '';
if ($tipo_paquete === 'estandar') {
    if ($id_paquete <= 0) {
        echo json_encode(['success' => false, 'message' => 'Seleccione un paquete estándar']);
        exit;
    }

    $packageRow = fetchPackageData($conn, $id_paquete);
    if (!$packageRow) {
        echo json_encode(['success' => false, 'message' => 'El paquete seleccionado no existe']);
        exit;
    }

    $id_paquete = intval($packageRow['id']);
    $paquete_personalizado = '';
} elseif ($tipo_paquete === 'personalizado') {
    if ($paquete_personalizado === '') {
        echo json_encode(['success' => false, 'message' => 'Ingrese el paquete personalizado']);
        exit;
    }
    // If personalizado but no text provided, set empty string
    $paquete_personalizado = $paquete_personalizado ?: '';
}

// Verify wedding planner exists
$stmt = $conn->prepare("SELECT id FROM wedding_planners WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $wp_id);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Wedding Planner no encontrado']);
    exit;
}

// Verify coordinador exists and belongs to wedding planner
$stmt = $conn->prepare("SELECT id FROM coordinadores_wp WHERE id = ? AND id_wp = ? LIMIT 1");
$stmt->bind_param('ii', $id_coordinador, $wp_id);
$stmt->execute();
$resC = $stmt->get_result();
$stmt->close();
if (!$resC || $resC->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Coordinador no encontrado para este Wedding Planner']);
    exit;
}

// Verify asesor exists and is an agent
$stmt = $conn->prepare("SELECT tipoUsu FROM usuarios WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id_asesor);
$stmt->execute();
$res2 = $stmt->get_result();
$stmt->close();
if (!$res2 || $res2->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Asesor no encontrado']);
    exit;
}
$rowu = $res2->fetch_assoc();
if (intval($rowu['tipoUsu']) !== 1) {
    echo json_encode(['success' => false, 'message' => 'El usuario seleccionado no es un asesor válido.']);
    exit;
}

// Create table if not exists
$createSql = "CREATE TABLE IF NOT EXISTS eventos_wp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wedding_planner_id INT NOT NULL,
    id_coordinador INT DEFAULT NULL,
    lugar VARCHAR(255) DEFAULT NULL,
    fecha DATETIME DEFAULT NULL,
    fecha_registro DATETIME DEFAULT NULL,
    novios VARCHAR(255) DEFAULT NULL,
    id_asesor INT DEFAULT NULL,
    modo VARCHAR(64) DEFAULT NULL,
    tipo_paquete VARCHAR(32) DEFAULT NULL,
    id_paquete INT DEFAULT NULL,
    paquete_personalizado TEXT DEFAULT NULL,
    created_time DATETIME DEFAULT NULL,
    estatus VARCHAR(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

$colCheck = $conn->query("SHOW COLUMNS FROM `eventos_wp` LIKE 'estatus'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE `eventos_wp` ADD COLUMN `estatus` VARCHAR(20) DEFAULT NULL AFTER `created_time`");
}

try {
    $created_time = date('Y-m-d H:i:s');

    // Normalize fecha to MySQL DATETIME if provided
    $fecha_db = null;
    if (!empty($fecha)) {
        $ts = strtotime($fecha);
        $fecha_db = $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
    $fecha_registro_db = null;
    if (!empty($fecha_registro)) {
        $tsr = strtotime($fecha_registro);
        $fecha_registro_db = $tsr ? date('Y-m-d H:i:s', $tsr) : null;
    }

    if ($tipo_paquete === 'estandar') {
        $paquete_personalizado = '';
    } elseif ($tipo_paquete === 'personalizado') {
        $id_paquete = 0;
    }

    if ($id > 0) {
        // Update existing event
        $stmt = $conn->prepare("UPDATE eventos_wp SET wedding_planner_id = ?, id_coordinador = ?, lugar = ?, fecha = ?, fecha_registro = ?, novios = ?, id_asesor = ?, modo = ?, tipo_paquete = ?, id_paquete = ?, paquete_personalizado = ? WHERE id = ?");
        if (!$stmt) throw new Exception('Error prepare update: ' . $conn->error);
        $stmt->bind_param('iissssissisi', $wp_id, $id_coordinador, $lugar, $fecha_db, $fecha_registro_db, $novios, $id_asesor, $modo, $tipo_paquete, $id_paquete, $paquete_personalizado, $id);
        if (!$stmt->execute()) throw new Exception('Error al actualizar evento: ' . $stmt->error);
        $stmt->close();
        syncAfianzadoLeadForEvent($conn, $id);
        echo json_encode(['success' => true, 'updated_id' => $id]);
        exit;
    } else {
        // Insert new event
        $stmt = $conn->prepare("INSERT INTO eventos_wp (wedding_planner_id, id_coordinador, lugar, fecha, fecha_registro, novios, id_asesor, modo, tipo_paquete, id_paquete, paquete_personalizado, created_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception('Error prepare insert: ' . $conn->error);
        // Types: i (wp_id), i (id_coordinador), s (lugar), s (fecha_db), s (fecha_registro_db), s (novios), i (id_asesor), s (modo), s (tipo_paquete), i (id_paquete), s (paquete_personalizado), s (created_time)
        $stmt->bind_param('iissssississ', $wp_id, $id_coordinador, $lugar, $fecha_db, $fecha_registro_db, $novios, $id_asesor, $modo, $tipo_paquete, $id_paquete, $paquete_personalizado, $created_time);
        if (!$stmt->execute()) throw new Exception('Error al insertar evento: ' . $stmt->error);
        $ev_id = $stmt->insert_id;
        $stmt->close();

        syncAfianzadoLeadForEvent($conn, $ev_id);

        echo json_encode(['success' => true, 'evento_id' => $ev_id]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>