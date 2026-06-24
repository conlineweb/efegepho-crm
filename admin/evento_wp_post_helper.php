<?php

require_once __DIR__ . '/usuario_roles_helper.php';

function wpEventFirstNonEmptyValue()
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

function wpEventEnsureColumnExists($conn, $table, $columnName, $columnDef)
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

function wpEventEnsureTable($conn)
{
    $createSql = "CREATE TABLE IF NOT EXISTS eventos_wp (
        id INT AUTO_INCREMENT PRIMARY KEY,
        wedding_planner_id INT NOT NULL,
        id_coordinador INT DEFAULT NULL,
        ciudad_novios VARCHAR(255) DEFAULT NULL,
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

    wpEventEnsureColumnExists($conn, 'eventos_wp', 'estatus', "`estatus` VARCHAR(20) DEFAULT NULL AFTER `created_time`");
    wpEventEnsureColumnExists($conn, 'eventos_wp', 'ciudad_novios', "`ciudad_novios` VARCHAR(255) DEFAULT NULL AFTER `id_coordinador`");
}

function wpEventFetchPackageData($conn, $packageId)
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

function wpEventNormalizeDateTime($value)
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

function wpEventEnsureContactFormColumns($conn)
{
    wpEventEnsureColumnExists($conn, 'contact_form', 'how_long_known_us', "`how_long_known_us` VARCHAR(150) DEFAULT NULL");
    wpEventEnsureColumnExists($conn, 'contact_form', 'first_contact_channel', "`first_contact_channel` VARCHAR(255) DEFAULT NULL");
    wpEventEnsureColumnExists($conn, 'contact_form', 'form_name', "`form_name` VARCHAR(255) DEFAULT NULL");
    wpEventEnsureColumnExists($conn, 'contact_form', 'created_time', "`created_time` DATETIME DEFAULT NULL");
    wpEventEnsureColumnExists($conn, 'contact_form', 'manual', "`manual` TINYINT(1) DEFAULT 0");
    wpEventEnsureColumnExists($conn, 'contact_form', 'desde_publicidad', "`desde_publicidad` TINYINT(1) DEFAULT 0");
    wpEventEnsureColumnExists($conn, 'contact_form', 'id_vendedor_asignado', "`id_vendedor_asignado` INT DEFAULT NULL");
    wpEventEnsureColumnExists($conn, 'contact_form', 'how_did_you_meet', "`how_did_you_meet` VARCHAR(50) DEFAULT NULL");
    wpEventEnsureColumnExists($conn, 'contact_form', 'engagement', "`engagement` INT DEFAULT 0");
    wpEventEnsureColumnExists($conn, 'contact_form', 'city', "`city` VARCHAR(255) DEFAULT ''");
    wpEventEnsureColumnExists($conn, 'contact_form', 'paquete', "`paquete` VARCHAR(100) DEFAULT NULL");
    wpEventEnsureColumnExists($conn, 'contact_form', 'paquete_cotizado', "`paquete_cotizado` VARCHAR(100) DEFAULT NULL");
    wpEventEnsureColumnExists($conn, 'contact_form', 'date_appointment', "`date_appointment` DATE DEFAULT NULL");
    wpEventEnsureColumnExists($conn, 'contact_form', 'time_appointment', "`time_appointment` TIME DEFAULT NULL");
    wpEventEnsureColumnExists($conn, 'contact_form', 'monto_venta', "`monto_venta` DECIMAL(12,2) DEFAULT NULL");
}

function wpEventFetchSourceData($conn, $eventId)
{
    $sql = "SELECT
                ev.id AS id_evento,
                ev.wedding_planner_id,
                ev.id_coordinador,
                ev.ciudad_novios,
                ev.lugar,
                ev.fecha,
                ev.fecha_registro,
                ev.novios,
                ev.id_asesor,
                ev.tipo_paquete,
                ev.id_paquete,
                ev.paquete_personalizado,
                ev.created_time,
                p.nombre AS paquete_nombre,
                wp.campaign_name AS wp_campaign_name,
                wp.platform AS wp_platform,
                wp.full_name AS wp_full_name,
                wp.empresa_wp,
                wp.phone AS wp_phone,
                wp.email AS wp_email,
                wp.city AS wp_city,
                wp.how_long_known_us AS wp_how_long_known_us,
                wp.first_contact_channel AS wp_first_contact_channel,
                wp.when_are_you_getting_married_,
                wp.where_is_your_marriage_taking_place_,
                wp.id_vendedor_asignado,
                (SELECT c.cliente_city FROM calendario c WHERE c.idclie = ev.id AND c.tipo = 1 AND c.eliminado = 0 ORDER BY c.id DESC LIMIT 1) AS cita_cliente_city,
                (SELECT c.cliente_engagement FROM calendario c WHERE c.idclie = ev.id AND c.tipo = 1 AND c.eliminado = 0 ORDER BY c.id DESC LIMIT 1) AS cita_cliente_engagement,
                coord.nombre AS coordinador_nombre,
                coord.how_did_you_meet AS coordinador_engagement
            FROM eventos_wp ev
            INNER JOIN wedding_planners wp ON wp.id = ev.wedding_planner_id
            LEFT JOIN coordinadores_wp coord ON coord.id = ev.id_coordinador
            LEFT JOIN paquetes p ON p.id = ev.id_paquete
            WHERE ev.id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('No se pudo consultar la información del evento: ' . $conn->error);
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $source = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$source) {
        throw new Exception('No se encontró el evento para generar el post-lead.');
    }

    return $source;
}

function wpEventPersistExistingEvent($conn, $eventId, array $payload)
{
    $wpId = intval($payload['wedding_planner_id'] ?? 0);
    $idCoordinador = intval($payload['id_coordinador'] ?? 0);
    $novios = trim((string) ($payload['novios'] ?? ''));
    $city = trim((string) ($payload['city'] ?? ''));
    $idAsesor = intval($payload['id_asesor'] ?? 0);
    $modo = trim((string) ($payload['modo'] ?? ''));
    $fechaRegistro = trim((string) ($payload['fecha_registro'] ?? ''));
    $tipoPaquete = trim((string) ($payload['tipo_paquete'] ?? 'estandar'));
    $idPaquete = intval($payload['id_paquete'] ?? 0);
    $paquetePersonalizado = trim((string) ($payload['paquete_personalizado'] ?? ''));
    $lugar = trim((string) ($payload['lugar'] ?? ''));
    $fecha = trim((string) ($payload['fecha'] ?? ''));

    if ($eventId <= 0) {
        throw new Exception('Evento inválido.');
    }
    if ($wpId <= 0) {
        throw new Exception('Wedding Planner inválido.');
    }
    if ($novios === '') {
        throw new Exception('Ingrese el nombre de los novios');
    }
    if ($city === '') {
        throw new Exception('Ingrese la ciudad de los novios');
    }
    if ($idAsesor <= 0) {
        throw new Exception('Seleccione un asesor');
    }
    if ($modo === '') {
        $modo = 'asistencia_post_q';
    }
    if ($fechaRegistro === '') {
        throw new Exception('Seleccione la fecha de registro');
    }
    if (!in_array($tipoPaquete, ['estandar', 'personalizado'], true)) {
        $tipoPaquete = 'estandar';
    }

    if ($tipoPaquete === 'estandar') {
        if ($idPaquete <= 0) {
            throw new Exception('Seleccione un paquete estándar');
        }

        $packageRow = wpEventFetchPackageData($conn, $idPaquete);
        if (!$packageRow) {
            throw new Exception('El paquete seleccionado no existe');
        }

        $idPaquete = intval($packageRow['id']);
        $paquetePersonalizado = '';
    } else {
        if ($paquetePersonalizado === '') {
            throw new Exception('Ingrese el paquete personalizado');
        }
        $idPaquete = 0;
    }

    $stmt = $conn->prepare('SELECT id FROM wedding_planners WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar el Wedding Planner: ' . $conn->error);
    }
    $stmt->bind_param('i', $wpId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
    if (!$res || $res->num_rows === 0) {
        throw new Exception('Wedding Planner no encontrado');
    }

    if ($idCoordinador > 0) {
        $stmt = $conn->prepare('SELECT id FROM coordinadores_wp WHERE id = ? AND id_wp = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo validar el coordinador: ' . $conn->error);
        }
        $stmt->bind_param('ii', $idCoordinador, $wpId);
        $stmt->execute();
        $resCoord = $stmt->get_result();
        $stmt->close();
        if (!$resCoord || $resCoord->num_rows === 0) {
            throw new Exception('Coordinador no encontrado para este Wedding Planner');
        }
    }

    $stmt = $conn->prepare('SELECT tipoUsu FROM usuarios WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar el asesor: ' . $conn->error);
    }
    $stmt->bind_param('i', $idAsesor);
    $stmt->execute();
    $resAsesor = $stmt->get_result();
    $stmt->close();
    if (!$resAsesor || $resAsesor->num_rows === 0) {
        throw new Exception('Asesor no encontrado');
    }

    $rowAsesor = $resAsesor->fetch_assoc();
    if (!usuarioTipoPuedeAsignarSesionWp($rowAsesor['tipoUsu'] ?? -1)) {
        throw new Exception('El usuario seleccionado no es un asesor válido.');
    }

    wpEventEnsureTable($conn);
    $fechaDb = wpEventNormalizeDateTime($fecha);
    $fechaRegistroDb = wpEventNormalizeDateTime($fechaRegistro);
    if ($fechaRegistroDb === null) {
        throw new Exception('Seleccione una fecha de registro válida');
    }

    $stmt = $conn->prepare('UPDATE eventos_wp SET wedding_planner_id = ?, id_coordinador = ?, ciudad_novios = ?, lugar = ?, fecha = ?, fecha_registro = ?, novios = ?, id_asesor = ?, modo = ?, tipo_paquete = ?, id_paquete = ?, paquete_personalizado = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Error prepare update: ' . $conn->error);
    }
    $stmt->bind_param('iisssssissisi', $wpId, $idCoordinador, $city, $lugar, $fechaDb, $fechaRegistroDb, $novios, $idAsesor, $modo, $tipoPaquete, $idPaquete, $paquetePersonalizado, $eventId);
    if (!$stmt->execute()) {
        throw new Exception('Error al actualizar evento: ' . $stmt->error);
    }
    $stmt->close();

    return $eventId;
}

function wpEventResolveCitaEngagement($conn, $eventId, $calendarId = 0)
{
    $eventId = intval($eventId);
    $calendarId = intval($calendarId);

    if ($calendarId > 0) {
        $stmt = $conn->prepare('SELECT cliente_engagement FROM calendario WHERE id = ? AND tipo = 1 AND eliminado = 0 LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $calendarId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $row = $result ? $result->fetch_assoc() : null;
                $engagement = intval($row['cliente_engagement'] ?? 0);
                $stmt->close();
                if ($engagement >= 1 && $engagement <= 3) {
                    return $engagement;
                }
            } else {
                $stmt->close();
            }
        }
    }

    if ($eventId <= 0) {
        return 0;
    }

    $stmt = $conn->prepare('SELECT cliente_engagement FROM calendario WHERE idclie = ? AND tipo = 1 AND eliminado = 0 ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $eventId);
    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $engagement = intval($row['cliente_engagement'] ?? 0);
    if ($engagement < 1 || $engagement > 3) {
        return 0;
    }

    return $engagement;
}

function wpEventSyncPostLeadEngagement($conn, $eventId, $calendarId = 0)
{
    $eventId = intval($eventId);
    if ($eventId <= 0) {
        return;
    }

    $engagement = wpEventResolveCitaEngagement($conn, $eventId, $calendarId);
    if ($engagement < 1 || $engagement > 3) {
        return;
    }

    $stmt = $conn->prepare("UPDATE contact_form SET engagement = ? WHERE original_lead_id = ? AND LOWER(COALESCE(tabla_origen, '')) = 'eventos_wp' LIMIT 1");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('ii', $engagement, $eventId);
    $stmt->execute();
    $stmt->close();
}

function wpEventUpsertPostLeadContactForm($conn, $eventId)
{
    wpEventEnsureContactFormColumns($conn);
    $source = wpEventFetchSourceData($conn, $eventId);

    $tablaOrigen = 'eventos_wp';
    $formName = 'eventos_wp';
    $fullName = wpEventFirstNonEmptyValue($source['empresa_wp'] ?? '', $source['wp_full_name'] ?? '', $source['novios'] ?? '', $source['wp_campaign_name'] ?? '', 'Evento WP');
    $telephone = trim((string) ($source['wp_phone'] ?? ''));
    $countryCode = '';
    $email = trim((string) ($source['wp_email'] ?? ''));
    $city = wpEventFirstNonEmptyValue($source['ciudad_novios'] ?? '', $source['cita_cliente_city'] ?? '', $source['wp_city'] ?? '');
    $weddingDate = !empty($source['fecha']) ? date('Y-m-d', strtotime($source['fecha'])) : (!empty($source['when_are_you_getting_married_']) ? date('Y-m-d', strtotime($source['when_are_you_getting_married_'])) : null);
    $weddingLocation = wpEventFirstNonEmptyValue($source['lugar'] ?? '', $source['where_is_your_marriage_taking_place_'] ?? '');
    $campaignName = wpEventFirstNonEmptyValue($source['wp_campaign_name'] ?? '', $source['empresa_wp'] ?? '', $source['wp_full_name'] ?? '', 'Evento WP');
    $howLongKnownUs = trim((string) ($source['wp_how_long_known_us'] ?? ''));
    $firstContactChannel = trim((string) ($source['wp_first_contact_channel'] ?? ''));
    $cliente = 0;
    $fechaCambioCliente = '0000-00-00 00:00:00';
    $idVendedorAsignado = intval($source['id_asesor'] ?? 0) > 0 ? intval($source['id_asesor']) : intval($source['id_vendedor_asignado'] ?? 0);
    $engagement = wpEventResolveCitaEngagement($conn, $eventId);
    $paquete = ($source['tipo_paquete'] ?? '') === 'estandar' && intval($source['id_paquete'] ?? 0) > 0 ? (string) intval($source['id_paquete']) : '';
    $paqueteCotizado = ($source['tipo_paquete'] ?? '') === 'personalizado' ? trim((string) ($source['paquete_personalizado'] ?? '')) : trim((string) ($source['paquete_nombre'] ?? ''));
    $manual = 1;
    $desdePublicidad = 1;
    $howDidYouMeet = '1';
    $submissionDate = !empty($source['created_time']) ? $source['created_time'] : date('Y-m-d H:i:s');
    $createdTime = $submissionDate;
    $dateAppointment = !empty($source['fecha']) ? date('Y-m-d', strtotime($source['fecha'])) : null;
    $timeAppointment = !empty($source['fecha']) ? date('H:i:s', strtotime($source['fecha'])) : null;

    $stmt = $conn->prepare('SELECT id FROM contact_form WHERE original_lead_id = ? AND LOWER(tabla_origen) = LOWER(?) LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar el contact_form del evento: ' . $conn->error);
    }
    $stmt->bind_param('is', $eventId, $tablaOrigen);
    $stmt->execute();
    $cfResult = $stmt->get_result();
    $cfRow = $cfResult ? $cfResult->fetch_assoc() : null;
    $stmt->close();

    if ($cfRow) {
        $contactFormId = intval($cfRow['id']);
        $stmt = $conn->prepare('UPDATE contact_form SET names = ?, telephone = ?, country_code = ?, email_address = ?, wedding_date = ?, wedding_location = ?, city = ?, campaign_name = ?, form_name = ?, how_long_known_us = ?, first_contact_channel = ?, cliente = ?, fecha_cambio_cliente = ?, id_vendedor_asignado = ?, engagement = ?, paquete = ?, paquete_cotizado = ?, manual = ?, desde_publicidad = ?, how_did_you_meet = ?, submission_date = ?, created_time = ?, date_appointment = ?, time_appointment = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización del post-lead: ' . $conn->error);
        }
        $stmt->bind_param('sssssssssssisiissiisssssi', $fullName, $telephone, $countryCode, $email, $weddingDate, $weddingLocation, $city, $campaignName, $formName, $howLongKnownUs, $firstContactChannel, $cliente, $fechaCambioCliente, $idVendedorAsignado, $engagement, $paquete, $paqueteCotizado, $manual, $desdePublicidad, $howDidYouMeet, $submissionDate, $createdTime, $dateAppointment, $timeAppointment, $contactFormId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar contact_form del post-lead: ' . $stmt->error);
        }
        $stmt->close();
        return $contactFormId;
    }

    $stmt = $conn->prepare('INSERT INTO contact_form (names, telephone, country_code, email_address, wedding_date, wedding_location, city, campaign_name, original_lead_id, tabla_origen, form_name, how_long_known_us, first_contact_channel, cliente, fecha_cambio_cliente, id_vendedor_asignado, engagement, paquete, paquete_cotizado, manual, desde_publicidad, how_did_you_meet, submission_date, created_time, date_appointment, time_appointment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la inserción del post-lead: ' . $conn->error);
    }
    $stmt->bind_param('ssssssssissssisiisssssssss', $fullName, $telephone, $countryCode, $email, $weddingDate, $weddingLocation, $city, $campaignName, $eventId, $tablaOrigen, $formName, $howLongKnownUs, $firstContactChannel, $cliente, $fechaCambioCliente, $idVendedorAsignado, $engagement, $paquete, $paqueteCotizado, $manual, $desdePublicidad, $howDidYouMeet, $submissionDate, $createdTime, $dateAppointment, $timeAppointment);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo insertar contact_form del post-lead: ' . $stmt->error);
    }
    $contactFormId = intval($stmt->insert_id);
    $stmt->close();

    return $contactFormId;
}

function wpEventEnsurePostLeadCalendarSynced($conn, $eventId, $calendarStatus)
{
    $contactFormId = wpEventUpsertPostLeadContactForm($conn, $eventId);
    if ($contactFormId <= 0) {
        throw new Exception('No se pudo obtener el contact_form del post-lead.');
    }

    $source = wpEventFetchSourceData($conn, $eventId);
    $assignedAdvisorId = intval($source['id_asesor'] ?? 0) > 0 ? intval($source['id_asesor']) : intval($source['id_vendedor_asignado'] ?? 0);
    $createdTime = !empty($source['created_time']) ? $source['created_time'] : date('Y-m-d H:i:s');
    $displayName = wpEventFirstNonEmptyValue($source['novios'] ?? '', $source['empresa_wp'] ?? '', $source['wp_full_name'] ?? '', $source['wp_campaign_name'] ?? '', 'Evento WP');
    $dateAppointment = !empty($source['fecha']) ? date('Y-m-d', strtotime($source['fecha'])) : date('Y-m-d', strtotime($createdTime));
    $timeAppointment = !empty($source['fecha']) ? date('H:i:s', strtotime($source['fecha'])) : date('H:i:s', strtotime($createdTime));
    $calendarTitle = 'Post lead atendido: ' . substr($displayName, 0, 120);
    $calendarNote = 'Cita espejo generada automaticamente desde una cita atendida de Wedding Planner.';

    $stmt = $conn->prepare('SELECT id FROM calendario WHERE idclie = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar el calendario del post-lead: ' . $conn->error);
    }
    $stmt->bind_param('i', $contactFormId);
    $stmt->execute();
    $calendarResult = $stmt->get_result();
    $calendarRow = $calendarResult ? $calendarResult->fetch_assoc() : null;
    $stmt->close();

    if ($calendarRow) {
        $calendarId = intval($calendarRow['id']);
        $stmt = $conn->prepare('UPDATE calendario SET idusu = ?, fecha = ?, fecha_cliente = ?, hora = ?, hora_cliente = ?, titulo = ?, nota = ?, estatus = ?, eliminado = 0 WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización del calendario espejo: ' . $conn->error);
        }
        $stmt->bind_param('issssssii', $assignedAdvisorId, $dateAppointment, $dateAppointment, $timeAppointment, $timeAppointment, $calendarTitle, $calendarNote, $calendarStatus, $calendarId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar el calendario espejo: ' . $stmt->error);
        }
        $stmt->close();
        return $calendarId;
    }

    $stmt = $conn->prepare('INSERT INTO calendario (idusu, fecha, fecha_cliente, hora, hora_cliente, titulo, nota, idclie, estatus, eliminado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la inserción del calendario espejo: ' . $conn->error);
    }
    $stmt->bind_param('issssssii', $assignedAdvisorId, $dateAppointment, $dateAppointment, $timeAppointment, $timeAppointment, $calendarTitle, $calendarNote, $contactFormId, $calendarStatus);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo insertar el calendario espejo: ' . $stmt->error);
    }
    $calendarId = intval($stmt->insert_id);
    $stmt->close();

    return $calendarId;
}

function wpEventUpsertPostLeadSaleAmount($conn, $eventId, $montoVenta)
{
    wpEventEnsureContactFormColumns($conn);

    $tablaOrigen = 'eventos_wp';
    $stmt = $conn->prepare('SELECT id FROM contact_form WHERE original_lead_id = ? AND LOWER(tabla_origen) = LOWER(?) LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo preparar validación de monto en contact_form: ' . $conn->error);
    }
    $stmt->bind_param('is', $eventId, $tablaOrigen);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $contactFormId = intval($row['id'] ?? 0);
    if ($contactFormId <= 0) {
        return;
    }

    if ($montoVenta === null) {
        $montoVenta = 0.0;
    }

    $stmt = $conn->prepare('UPDATE contact_form SET monto_venta = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo preparar actualización de monto_venta: ' . $conn->error);
    }
    $stmt->bind_param('di', $montoVenta, $contactFormId);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('No se pudo guardar el monto de venta en contact_form: ' . $error);
    }
    $stmt->close();
}

function wpEventResolveByCalendarId($conn, $calendarioId)
{
    $stmt = $conn->prepare('SELECT c.id, c.idclie, c.estatus, e.wedding_planner_id FROM calendario c INNER JOIN eventos_wp e ON e.id = c.idclie WHERE c.id = ? AND c.tipo = 1 AND c.eliminado = 0 LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo consultar la cita WP: ' . $conn->error);
    }
    $stmt->bind_param('i', $calendarioId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        throw new Exception('La cita atendida no existe o ya no está disponible.');
    }

    return $row;
}
