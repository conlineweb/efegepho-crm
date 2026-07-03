<?php
include 'conn.php';
require_once __DIR__ . '/calendario_estatus_historial_helper.php';
require_once __DIR__ . '/evento_wp_post_helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

function emailSafe($value)
{
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatEventDateValue($value)
{
        $normalized = trim((string) $value);
        if ($normalized === '') {
                return 'N/A';
        }

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
                return $normalized;
        }

        return date('d/m/Y H:i', $timestamp);
}

function enviarCorreoEventoWp($correoDestino, $asunto, $titulo, $cuerpo, $despedida)
{
    $correoDestino = "proyectos@conlineweb.com";
        $mail = new PHPMailer(true);

        $mensaje = "
<html>
<head>
    <title>$asunto</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond&display=swap');

        .bg{
            width: 96%;
            margin: 0 auto;
            padding: 50px 0px;
            background-color: #e8e8e8;
            font-family: 'Cormorant Garamond', serif;
        }

        p {
            margin: 15px;
        }

        .container {
            width: 450px;
            margin: 0 auto;
            border-radius: 30px;
            background-color: #fff;
            line-height: 1.5;
            font-size: 1.2rem;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-container {
            padding: 10px 30px;
            margin: 10px;
        }

        .header {
            text-align: left;
            padding: 10px 50px;
            font-size: 1.5rem;
            background-color: #eee8dc;
            color: black;
            font-weight: 500;
            margin-top: 13px;
        }

        .content {
            padding: 20px 0px 0px 0px;
            margin: 0px;
        }
    </style>
</head>
<body>
    <div class='bg'>
        <div class='container'>
            <div class='content'>
                <div class='header'>
                    $titulo
                </div>
                <div class='card-container'>
                    <p>$cuerpo</p>
                    <p>$despedida</p>
                </div>
            </div>
        </div>
        <div style='text-align: center;'>
            <img style='width: 140px; margin: 0 auto;' alt='efegephologo' src='https://sandbox.efegepho.com.mx/admin/assets/img/logofgep.png'/>
        </div>
    </div>
</body>
</html>
";

        $correoRemitente = 'info@efegepho.com.mx';
        $nombreRemitente = 'InfoEfegepho';

        try {
                $mail->isSMTP();
                $mail->SMTPAuth = true;
                $mail->SMTPSecure = 'starttls';
                $mail->Port = 587;
                $mail->Host = 'smtp.gmail.com';
                $mail->Username = $correoRemitente;
                $mail->Password = 'glhewzgjzdnsbuvj';
                $mail->setFrom($correoRemitente, $nombreRemitente);
                $mail->addAddress($correoDestino, 'Admin');
                $mail->Subject = $asunto;
                $mail->isHTML(true);
                $mail->Body = $mensaje;
                $mail->send();
        } catch (MailException $e) {
                error_log('enviarCorreoEventoWp error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo);
        }
}

function getEventoWpNotificationData($conn, $eventId)
{
        $sql = "SELECT
                                e.id,
                                e.estatus,
                                e.comentario,
                e.comentario_a_cliente,
                                e.fecha_registro,
                                e.fecha,
                                e.lugar,
                                e.novios,
                                e.modo,
                                e.tipo_paquete,
                                e.paquete_personalizado,
                                e.id_paquete,
                                wp.afianzado,
                                COALESCE(NULLIF(TRIM(wp.empresa_wp), ''), NULLIF(TRIM(wp.full_name), ''), NULLIF(TRIM(wp.campaign_name), '')) AS wp_nombre,
                                c.nombre AS coordinador_nombre,
                                c.telefono AS coordinador_telefono,
                                c.correo AS coordinador_correo,
                                c.how_long_known_us AS coordinador_how_long_known_us,
                                c.first_contact_channel AS coordinador_first_contact_channel,
                                u.nombre AS asesor_nombre,
                                u.apepat AS asesor_apepat,
                                p.nombre AS paquete_nombre
                        FROM eventos_wp e
                        LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id
                        LEFT JOIN coordinadores_wp c ON c.id = e.id_coordinador
                        LEFT JOIN usuarios u ON u.id = e.id_asesor
                        LEFT JOIN paquetes p ON p.id = e.id_paquete
                        WHERE e.id = ?
                        LIMIT 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
                throw new Exception('No se pudo preparar la consulta del correo del evento: ' . $conn->error);
        }

        $stmt->bind_param('i', $eventId);
        if (!$stmt->execute()) {
                throw new Exception('No se pudo consultar la información del correo del evento: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row;
}

function sendEventoWpStatusEmail($conn, $eventId, $estatus, $comentario, $comentarioACliente = '')
{
    if (!in_array($estatus, ['atendido', 'muerto', 'cliente'], true)) {
                return;
        }

        $data = getEventoWpNotificationData($conn, $eventId);
        if (!$data) {
                return;
        }

        $isAttended = $estatus === 'atendido';
        $isClient = $estatus === 'cliente';
        $statusLabel = $isClient ? 'Client' : ($isAttended ? 'Attended' : 'Dead');
        $statusText = $statusLabel;
        $subject = $isClient ? 'WP event moved to client' : ($isAttended ? 'WP event attended successfully' : 'WP dead event');
        $wpNombre = first_non_empty_value($data['wp_nombre'] ?? '', 'Wedding Planner');
        $coordinadorNombre = first_non_empty_value($data['coordinador_nombre'] ?? '', 'N/A');
        $coordinadorTelefono = first_non_empty_value($data['coordinador_telefono'] ?? '', 'N/A');
        $coordinadorCorreo = first_non_empty_value($data['coordinador_correo'] ?? '', 'N/A');
        $coordinadorTiempo = first_non_empty_value($data['coordinador_how_long_known_us'] ?? '', 'N/A');
        $coordinadorCanal = first_non_empty_value($data['coordinador_first_contact_channel'] ?? '', 'N/A');
        $asesorNombre = trim(first_non_empty_value(($data['asesor_nombre'] ?? '') . ' ' . ($data['asesor_apepat'] ?? ''), 'N/A'));
        $paquete = ($data['tipo_paquete'] ?? '') === 'personalizado'
                ? first_non_empty_value($data['paquete_personalizado'] ?? '', 'N/A')
                : first_non_empty_value($data['paquete_nombre'] ?? '', 'N/A');
        $afianzado = intval($data['afianzado'] ?? 0) === 1 ? 'Yes' : 'No';
        $comentarioFinal = first_non_empty_value($comentario, $data['comentario'] ?? '', 'N/A');
        $comentarioClienteFinal = first_non_empty_value($comentarioACliente, $data['comentario_a_cliente'] ?? '', 'N/A');

        $titulo = 'WP Event Status Update';
        $cuerpo = ($isClient
            ? '<p>A Wedding Planner event was moved to client.</p>'
            : ($isAttended
                ? '<p>A Wedding Planner event was marked as attended.</p>'
                : '<p>A Wedding Planner event was marked as dead.</p>'))
                . "
<br><hr style='border:1px solid #ddd;margin:16px 0;'>
    <p><strong>Event Summary</strong></p>
<table style='width:100%;border-collapse:collapse;font-size:1rem;'>
        <tr><td style='padding:4px 8px;color:#666;width:45%;'>Status</td><td style='padding:4px 8px;'><strong>" . emailSafe($statusLabel) . "</strong></td></tr>
    <tr><td style='padding:4px 8px;color:#666;'>Wedding Planner</td><td style='padding:4px 8px;'>" . emailSafe($wpNombre) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Affiliated</td><td style='padding:4px 8px;'>" . emailSafe($afianzado) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Advisor</td><td style='padding:4px 8px;'>" . emailSafe($asesorNombre) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Registration Date</td><td style='padding:4px 8px;'>" . emailSafe(formatEventDateValue($data['fecha_registro'] ?? '')) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Event Date</td><td style='padding:4px 8px;'>" . emailSafe(formatEventDateValue($data['fecha'] ?? '')) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Event Location</td><td style='padding:4px 8px;'>" . emailSafe(first_non_empty_value($data['lugar'] ?? '', 'N/A')) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Couple</td><td style='padding:4px 8px;'>" . emailSafe(first_non_empty_value($data['novios'] ?? '', 'N/A')) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Mode</td><td style='padding:4px 8px;'>" . emailSafe(first_non_empty_value($data['modo'] ?? '', 'N/A')) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Package</td><td style='padding:4px 8px;'>" . emailSafe($paquete) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Coordinator</td><td style='padding:4px 8px;'>" . emailSafe($coordinadorNombre) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Coordinator Phone</td><td style='padding:4px 8px;'>" . emailSafe($coordinadorTelefono) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Coordinator Email</td><td style='padding:4px 8px;'>" . emailSafe($coordinadorCorreo) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>How Long They Have Known Us</td><td style='padding:4px 8px;'>" . emailSafe($coordinadorTiempo) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Contact Channel</td><td style='padding:4px 8px;'>" . emailSafe($coordinadorCanal) . "</td></tr>
        <tr><td style='padding:4px 8px;color:#666;'>Initial Comment</td><td style='padding:4px 8px;'>" . nl2br(emailSafe($comentarioFinal)) . "</td></tr>";

        if ($isClient) {
            $cuerpo .= "
        <tr><td style='padding:4px 8px;color:#666;'>Client Comment</td><td style='padding:4px 8px;'>" . nl2br(emailSafe($comentarioClienteFinal)) . "</td></tr>";
        }

        $cuerpo .= "
    </table>";

        $despedida = 'Regards';
        $correosAdmins = ['proyectos@conlineweb.com'];

        foreach ($correosAdmins as $correoAdmin) {
                enviarCorreoEventoWp($correoAdmin, $subject, $titulo, $cuerpo, $despedida);
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
        return;
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
    ensureColumnExists($conn, 'contact_form', 'tipo_cliente', "`tipo_cliente` TINYINT(1) NULL DEFAULT NULL COMMENT '1=Wedding Planner, 0=Cliente Final'");
    ensureColumnExists($conn, 'contact_form', 'engagement', "`engagement` INT DEFAULT 0");
    ensureColumnExists($conn, 'contact_form', 'paquete', "`paquete` VARCHAR(100) DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'paquete_cotizado', "`paquete_cotizado` VARCHAR(100) DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'date_appointment', "`date_appointment` DATE DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'time_appointment', "`time_appointment` TIME DEFAULT NULL");
    ensureColumnExists($conn, 'contact_form', 'evento_wp_id', "`evento_wp_id` INT DEFAULT NULL COMMENT 'ID del evento en eventos_wp'");
    ensureColumnExists($conn, 'contact_form', 'wp_id', "`wp_id` INT DEFAULT NULL COMMENT 'ID del wedding planner en wedding_planners'");
}

function upsertEventoClientContactForm($conn, $eventId)
{
    ensureContactFormColumnsForAfianzados($conn);

    $sql = "SELECT
                ev.id AS id_evento,
                ev.wedding_planner_id,
                ev.id_coordinador,
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
                wp.when_are_you_getting_married_,
                wp.where_is_your_marriage_taking_place_,
                wp.id_vendedor_asignado,
                wp.first_contact_channel AS wp_first_contact_channel,
                coord.nombre AS coordinador_nombre,
                coord.telefono AS coordinador_telefono,
                coord.correo AS coordinador_correo,
                coord.how_long_known_us AS coordinador_how_long_known_us,
                coord.first_contact_channel AS coordinador_first_contact_channel,
                coord.how_did_you_meet AS coordinador_engagement,
                coord.campaign_name AS coordinador_campaign_name
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
        throw new Exception('No se encontró el evento para generar el cliente.');
    }

    $tablaOrigen = 'eventos_wp';
    $formName = 'eventos_wp';
    $fullName = first_non_empty_value($source['coordinador_nombre'] ?? '', $source['empresa_wp'] ?? '', $source['wp_full_name'] ?? '', $source['novios'] ?? '', 'Evento WP');
    $telephone = trim((string) ($source['coordinador_telefono'] ?? ''));
    $countryCode = '';
    $email = trim((string) ($source['coordinador_correo'] ?? ''));
    $weddingDate = !empty($source['fecha']) ? date('Y-m-d', strtotime($source['fecha'])) : (!empty($source['when_are_you_getting_married_']) ? date('Y-m-d', strtotime($source['when_are_you_getting_married_'])) : null);
    $weddingLocation = first_non_empty_value($source['lugar'] ?? '', $source['where_is_your_marriage_taking_place_'] ?? '');
    $firstContactChannel = first_non_empty_value(
        $source['coordinador_first_contact_channel'] ?? '',
        $source['wp_first_contact_channel'] ?? ''
    );
    $campaignName = $firstContactChannel;
    $howLongKnownUs = trim((string) ($source['coordinador_how_long_known_us'] ?? ''));
    $cliente = 1;
    $fechaCambioCliente = date('Y-m-d');
    $idVendedorAsignado = intval($source['id_asesor'] ?? 0) > 0 ? intval($source['id_asesor']) : intval($source['id_vendedor_asignado'] ?? 0);
    $engagement = intval($source['coordinador_engagement'] ?? 0);
    $paquete = ($source['tipo_paquete'] ?? '') === 'estandar' && intval($source['id_paquete'] ?? 0) > 0 ? (string) intval($source['id_paquete']) : '';
    $paqueteCotizado = ($source['tipo_paquete'] ?? '') === 'personalizado' ? trim((string) ($source['paquete_personalizado'] ?? '')) : trim((string) ($source['paquete_nombre'] ?? ''));
    $manual = 1;
    $desdePublicidad = 1;
    $howDidYouMeet = '1';
    $tipoCliente = 1;
    $eventoWpId = $eventId;
    $wpId = intval($source['wedding_planner_id'] ?? 0);
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
        $stmt = $conn->prepare('UPDATE contact_form SET names = ?, telephone = ?, country_code = ?, email_address = ?, wedding_date = ?, wedding_location = ?, campaign_name = ?, form_name = ?, how_long_known_us = ?, first_contact_channel = ?, cliente = ?, fecha_cambio_cliente = ?, id_vendedor_asignado = ?, engagement = ?, paquete = ?, paquete_cotizado = ?, manual = ?, desde_publicidad = ?, how_did_you_meet = ?, tipo_cliente = ?, evento_wp_id = ?, wp_id = ?, submission_date = ?, created_time = ?, date_appointment = ?, time_appointment = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización del cliente: ' . $conn->error);
        }
        $stmt->bind_param('ssssssssssisiissiisiissssii', $fullName, $telephone, $countryCode, $email, $weddingDate, $weddingLocation, $campaignName, $formName, $howLongKnownUs, $firstContactChannel, $cliente, $fechaCambioCliente, $idVendedorAsignado, $engagement, $paquete, $paqueteCotizado, $manual, $desdePublicidad, $howDidYouMeet, $tipoCliente, $eventoWpId, $wpId, $submissionDate, $createdTime, $dateAppointment, $timeAppointment, $contactFormId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar contact_form del cliente: ' . $stmt->error);
        }
        $stmt->close();
        return;
    }

    $stmt = $conn->prepare('INSERT INTO contact_form (names, telephone, country_code, email_address, wedding_date, wedding_location, campaign_name, original_lead_id, tabla_origen, form_name, how_long_known_us, first_contact_channel, cliente, fecha_cambio_cliente, id_vendedor_asignado, engagement, paquete, paquete_cotizado, manual, desde_publicidad, how_did_you_meet, tipo_cliente, evento_wp_id, wp_id, submission_date, created_time, date_appointment, time_appointment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la inserción del cliente: ' . $conn->error);
    }
    $stmt->bind_param('sssssssissssisissiiisssss', $fullName, $telephone, $countryCode, $email, $weddingDate, $weddingLocation, $campaignName, $eventId, $tablaOrigen, $formName, $howLongKnownUs, $firstContactChannel, $cliente, $fechaCambioCliente, $idVendedorAsignado, $engagement, $paquete, $paqueteCotizado, $manual, $desdePublicidad, $howDidYouMeet, $tipoCliente, $eventoWpId, $wpId, $submissionDate, $createdTime, $dateAppointment, $timeAppointment);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo insertar contact_form del cliente: ' . $stmt->error);
    }
    $stmt->close();
}

function markAfianzadoEventAsClient($conn, $eventId)
{
    ensureAfianzadoCalendarSynced($conn, $eventId, '2', 1);

    $tablaOrigen = 'wp_eventos_afianzados';
    $fechaCambioCliente = date('Y-m-d');
    $stmt = $conn->prepare("UPDATE contact_form cf INNER JOIN wp_eventos_afianzados wpa ON wpa.id = cf.original_lead_id INNER JOIN eventos_wp ev ON ev.id = wpa.id_evento SET cf.cliente = 1, cf.fecha_cambio_cliente = ?, cf.tipo_cliente = 1, cf.how_did_you_meet = '1', cf.evento_wp_id = ev.id, cf.wp_id = ev.wedding_planner_id, cf.first_contact_channel = COALESCE(NULLIF(TRIM(wpa.first_contact_channel), ''), cf.first_contact_channel), cf.campaign_name = COALESCE(NULLIF(TRIM(wpa.first_contact_channel), ''), cf.campaign_name) WHERE cf.tabla_origen = ? AND wpa.id_evento = ?");
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualización a cliente del afianzado: ' . $conn->error);
    }
    $stmt->bind_param('ssi', $fechaCambioCliente, $tablaOrigen, $eventId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar a cliente el afianzado: ' . $stmt->error);
    }
    $stmt->close();
}

function ensureAfianzadoCalendarSynced($conn, $eventId, $eventStatus, $calendarStatus)
{
    ensureAfianzadoLeadTableExists($conn);
    ensureAfianzadoLeadTableRegistered($conn);
    ensureContactFormColumnsForAfianzados($conn);

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
                ev.created_time,
                wp.afianzado,
                wp.campaign_name AS wp_campaign_name,
                wp.platform AS wp_platform,
                wp.full_name AS wp_full_name,
                wp.empresa_wp,
                wp.when_are_you_getting_married_,
                wp.where_is_your_marriage_taking_place_,
                wp.id_vendedor_asignado,
                wp.first_contact_channel AS wp_first_contact_channel,
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
            WHERE ev.id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('No se pudo consultar la información del evento afianzado: ' . $conn->error);
    }

    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $source = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$source || intval($source['afianzado'] ?? 0) !== 1) {
        return;
    }

    $createdTime = first_non_empty_value($source['created_time'] ?? '', date('Y-m-d H:i:s'));
    $firstContactChannel = first_non_empty_value(
        $source['coordinador_first_contact_channel'] ?? '',
        $source['wp_first_contact_channel'] ?? ''
    );
    $campaignName = $firstContactChannel;
    $platform = first_non_empty_value($source['coordinador_platform'] ?? '', $source['wp_platform'] ?? '', 'Wedding Planner');
    $fullName = first_non_empty_value($source['coordinador_nombre'] ?? '', $source['empresa_wp'] ?? '', $source['wp_full_name'] ?? '', $source['novios'] ?? '', 'Evento afianzado');
    $weddingLocation = first_non_empty_value($source['lugar'] ?? '', $source['where_is_your_marriage_taking_place_'] ?? '');
    $eventDate = !empty($source['fecha']) ? date('Y-m-d', strtotime($source['fecha'])) : null;
    $weddingDate = $eventDate ?: (!empty($source['when_are_you_getting_married_']) ? date('Y-m-d', strtotime($source['when_are_you_getting_married_'])) : null);
    $callDate = !empty($source['fecha_registro']) ? date('Y-m-d', strtotime($source['fecha_registro'])) : null;
    $assignedAdvisorId = intval($source['id_asesor'] ?? 0) > 0 ? intval($source['id_asesor']) : intval($source['id_vendedor_asignado'] ?? 0);
    $phone = trim((string) ($source['coordinador_telefono'] ?? ''));
    $email = trim((string) ($source['coordinador_correo'] ?? ''));
    $howLongKnownUs = trim((string) ($source['coordinador_how_long_known_us'] ?? ''));
    $engagement = intval($source['coordinador_engagement'] ?? 0);
    $eventoFecha = !empty($source['fecha']) ? date('Y-m-d H:i:s', strtotime($source['fecha'])) : null;
    $fechaRegistroEvento = !empty($source['fecha_registro']) ? date('Y-m-d H:i:s', strtotime($source['fecha_registro'])) : null;
    $coordinadorNombre = trim((string) ($source['coordinador_nombre'] ?? ''));
    $empresaWp = first_non_empty_value($source['empresa_wp'] ?? '', $source['wp_full_name'] ?? '', $source['wp_campaign_name'] ?? '');

    $stmt = $conn->prepare('SELECT id FROM wp_eventos_afianzados WHERE id_evento = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar el lead afianzado existente: ' . $conn->error);
    }
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $mirrorResult = $stmt->get_result();
    $mirrorRow = $mirrorResult ? $mirrorResult->fetch_assoc() : null;
    $stmt->close();

    $mirrorLeadId = intval($mirrorRow['id'] ?? 0);
    if ($mirrorLeadId > 0) {
        $stmt = $conn->prepare('UPDATE wp_eventos_afianzados SET full_name = ?, email = ?, phone = ?, wedding_location = ?, wedding_date = ?, how_long_known_us = ?, first_contact_channel = ?, campaign_name = ?, form_name = ?, platform = ?, choose_your_call_date_ = ?, lead_status = ?, id_vendedor_asignado = ?, usuario_asignado = ?, novios = ?, coordinador_nombre = ?, empresa_wp = ?, evento_fecha = ?, fecha_registro_evento = ?, lugar_evento = ?, modo_evento = ?, tipo_paquete = ?, id_paquete = ?, paquete_personalizado = ?, estatus_evento = ?, descartado = 0 WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización del lead afianzado: ' . $conn->error);
        }
        $formName = 'wp_eventos_afianzados';
        $leadStatus = 'wp_evento_afianzado';
        $modoEvento = trim((string) ($source['modo'] ?? ''));
        $tipoPaquete = trim((string) ($source['tipo_paquete'] ?? ''));
        $idPaquete = intval($source['id_paquete'] ?? 0);
        $paquetePersonalizado = trim((string) ($source['paquete_personalizado'] ?? ''));
        $stmt->bind_param('ssssssssssssiissssssisssii', $fullName, $email, $phone, $weddingLocation, $weddingDate, $howLongKnownUs, $firstContactChannel, $campaignName, $formName, $platform, $callDate, $leadStatus, $assignedAdvisorId, $assignedAdvisorId, $source['novios'], $coordinadorNombre, $empresaWp, $eventoFecha, $fechaRegistroEvento, $source['lugar'], $modoEvento, $tipoPaquete, $idPaquete, $paquetePersonalizado, $eventStatus, $mirrorLeadId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar wp_eventos_afianzados: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT INTO wp_eventos_afianzados (id_evento, id_wedding_planner, id_coordinador, created_time, fecha_importacion, full_name, email, phone, country_code, city, wedding_location, wedding_date, how_long_known_us, first_contact_channel, campaign_name, form_name, platform, when_are_you_getting_married_, where_are_you_getting_married_, choose_your_call_date_, lead_status, id_vendedor_asignado, usuario_asignado, is_organic, descartado, novios, coordinador_nombre, empresa_wp, evento_fecha, fecha_registro_evento, lugar_evento, modo_evento, tipo_paquete, id_paquete, paquete_personalizado, estatus_evento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la inserción del lead afianzado: ' . $conn->error);
        }
        $countryCode = '';
        $city = '';
        $formName = 'wp_eventos_afianzados';
        $leadStatus = 'wp_evento_afianzado';
        $modoEvento = trim((string) ($source['modo'] ?? ''));
        $tipoPaquete = trim((string) ($source['tipo_paquete'] ?? ''));
        $idPaquete = intval($source['id_paquete'] ?? 0);
        $paquetePersonalizado = trim((string) ($source['paquete_personalizado'] ?? ''));
        $stmt->bind_param('iiissssssssssssssssssiiisssssssisss', $eventId, $source['wedding_planner_id'], $source['id_coordinador'], $createdTime, $createdTime, $fullName, $email, $phone, $countryCode, $city, $weddingLocation, $weddingDate, $howLongKnownUs, $firstContactChannel, $campaignName, $formName, $platform, $weddingDate, $weddingLocation, $callDate, $leadStatus, $assignedAdvisorId, $assignedAdvisorId, $source['novios'], $coordinadorNombre, $empresaWp, $eventoFecha, $fechaRegistroEvento, $source['lugar'], $modoEvento, $tipoPaquete, $idPaquete, $paquetePersonalizado, $eventStatus);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo insertar wp_eventos_afianzados: ' . $stmt->error);
        }
        $mirrorLeadId = intval($stmt->insert_id);
        $stmt->close();
    }

    if ($mirrorLeadId <= 0) {
        throw new Exception('No se pudo obtener el lead espejo afianzado');
    }

    $tablaOrigen = 'wp_eventos_afianzados';
    $stmt = $conn->prepare('SELECT id FROM contact_form WHERE original_lead_id = ? AND LOWER(tabla_origen) = LOWER(?) LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar contact_form del lead afianzado: ' . $conn->error);
    }
    $stmt->bind_param('is', $mirrorLeadId, $tablaOrigen);
    $stmt->execute();
    $cfResult = $stmt->get_result();
    $cfRow = $cfResult ? $cfResult->fetch_assoc() : null;
    $stmt->close();

    $contactFormId = intval($cfRow['id'] ?? 0);
    $dateAppointment = !empty($eventoFecha) ? date('Y-m-d', strtotime($eventoFecha)) : (!empty($createdTime) ? date('Y-m-d', strtotime($createdTime)) : null);
    $timeAppointment = !empty($eventoFecha) ? date('H:i:s', strtotime($eventoFecha)) : (!empty($createdTime) ? date('H:i:s', strtotime($createdTime)) : null);
    $cliente = 0;
    $fechaCambioCliente = date('Y-m-d');
    $manual = 1;
    $desdePublicidad = 1;
    $howDidYouMeet = '1';
    $paquete = '';
    $paqueteCotizado = '';
    $countryCode = '';

    if ($contactFormId > 0) {
        $stmt = $conn->prepare('UPDATE contact_form SET names = ?, telephone = ?, country_code = ?, email_address = ?, wedding_date = ?, wedding_location = ?, campaign_name = ?, form_name = ?, how_long_known_us = ?, first_contact_channel = ?, id_vendedor_asignado = ?, created_time = ?, submission_date = ?, date_appointment = ?, time_appointment = ?, engagement = ?, paquete = ?, paquete_cotizado = ?, manual = ?, desde_publicidad = ?, how_did_you_meet = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización de contact_form: ' . $conn->error);
        }
        $formName = 'wp_eventos_afianzados';
        $stmt->bind_param('ssssssssssissssissiisi', $fullName, $phone, $countryCode, $email, $weddingDate, $weddingLocation, $campaignName, $formName, $howLongKnownUs, $firstContactChannel, $assignedAdvisorId, $createdTime, $createdTime, $dateAppointment, $timeAppointment, $engagement, $paquete, $paqueteCotizado, $manual, $desdePublicidad, $howDidYouMeet, $contactFormId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar contact_form: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT INTO contact_form (names, telephone, country_code, email_address, wedding_date, wedding_location, campaign_name, original_lead_id, tabla_origen, form_name, how_long_known_us, first_contact_channel, cliente, fecha_cambio_cliente, id_vendedor_asignado, engagement, paquete, paquete_cotizado, manual, desde_publicidad, how_did_you_meet, submission_date, created_time, date_appointment, time_appointment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la inserción en contact_form: ' . $conn->error);
        }
        $formName = 'wp_eventos_afianzados';
        $stmt->bind_param('sssssssissssisiissiisssss', $fullName, $phone, $countryCode, $email, $weddingDate, $weddingLocation, $campaignName, $mirrorLeadId, $tablaOrigen, $formName, $howLongKnownUs, $firstContactChannel, $cliente, $fechaCambioCliente, $assignedAdvisorId, $engagement, $paquete, $paqueteCotizado, $manual, $desdePublicidad, $howDidYouMeet, $createdTime, $createdTime, $dateAppointment, $timeAppointment);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo insertar contact_form: ' . $stmt->error);
        }
        $contactFormId = intval($stmt->insert_id);
        $stmt->close();
    }

    if ($contactFormId <= 0) {
        throw new Exception('No se pudo obtener el contact_form del evento afianzado');
    }

    $calendarTitle = 'Evento afianzado ' . ($calendarStatus === 1 ? 'atendido' : 'agendado') . ': ' . substr($fullName !== '' ? $fullName : 'Lead afianzado', 0, 120);
    $calendarNote = 'Cita espejo generada automaticamente para mostrar el evento afianzado en post-leads.';
    $stmt = $conn->prepare('SELECT id FROM calendario WHERE idclie = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar calendario del lead afianzado: ' . $conn->error);
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
            throw new Exception('No se pudo preparar la actualización de calendario: ' . $conn->error);
        }
        $stmt->bind_param('issssssii', $assignedAdvisorId, $dateAppointment, $dateAppointment, $timeAppointment, $timeAppointment, $calendarTitle, $calendarNote, $calendarStatus, $calendarId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar calendario: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        ensureCalendarioFechaRegistroColumn($conn);
        $fechaRegistroCal = calendarioBookingTimestamp();
        $stmt = $conn->prepare('INSERT INTO calendario (idusu, fecha, fecha_cliente, hora, hora_cliente, fecha_registro, titulo, nota, idclie, estatus, eliminado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la inserción en calendario: ' . $conn->error);
        }
        $stmt->bind_param('isssssssii', $assignedAdvisorId, $dateAppointment, $dateAppointment, $timeAppointment, $timeAppointment, $fechaRegistroCal, $calendarTitle, $calendarNote, $contactFormId, $calendarStatus);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo insertar calendario: ' . $stmt->error);
        }
        $stmt->close();
    }
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$estatus = isset($_POST['estatus']) ? trim((string)$_POST['estatus']) : '';
$comentario = isset($_POST['comentario']) ? trim((string)$_POST['comentario']) : '';
$comentarioACliente = isset($_POST['comentario_a_cliente']) ? trim((string)$_POST['comentario_a_cliente']) : '';

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Evento inválido']);
    exit;
}

if (!in_array($estatus, ['atendido', 'muerto', 'cliente'], true)) {
    echo json_encode(['success' => false, 'message' => 'Estatus inválido']);
    exit;
}

if (in_array($estatus, ['atendido', 'muerto'], true) && $comentario === '') {
    echo json_encode(['success' => false, 'message' => 'Debes agregar un comentario']);
    exit;
}

if ($estatus === 'cliente' && $comentarioACliente === '') {
    echo json_encode(['success' => false, 'message' => 'Debes agregar un comentario para pasarlo a cliente']);
    exit;
}

ensureColumnExists($conn, 'eventos_wp', 'estatus', "`estatus` VARCHAR(20) DEFAULT NULL AFTER `created_time`");
ensureColumnExists($conn, 'eventos_wp', 'comentario_a_cliente', "`comentario_a_cliente` TEXT DEFAULT NULL AFTER `comentario`");
ensureColumnExists($conn, 'eventos_wp', 'fecha_actualizacion_estatus', "`fecha_actualizacion_estatus` DATETIME DEFAULT NULL AFTER `estatus`");
wpEventEnsureFechaAtendidoColumn($conn);

try {
    $eventStatus = $estatus === 'atendido' ? '1' : ($estatus === 'cliente' ? '2' : '3');

    $stmt = $conn->prepare('SELECT e.id, e.wedding_planner_id, e.estatus, wp.afianzado FROM eventos_wp e LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id WHERE e.id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Error prepare select: ' . $conn->error);
    }

    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new Exception('Error al consultar el evento: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $eventRow = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$eventRow) {
        throw new Exception('Evento no encontrado');
    }

    $currentStatus = trim((string) ($eventRow['estatus'] ?? ''));
    $isCurrentlyAttended = $currentStatus === '1' || strcasecmp($currentStatus, 'atendido') === 0;
    if ($estatus === 'cliente' && !$isCurrentlyAttended) {
        throw new Exception('Solo se puede pasar a cliente un evento atendido.');
    }

    $conn->begin_transaction();

    if ($estatus === 'cliente') {
        $stmt = $conn->prepare('UPDATE eventos_wp SET estatus = ?, comentario_a_cliente = ?, fecha_actualizacion_estatus = NOW() WHERE id = ? LIMIT 1');
    } else {
        $stmt = $conn->prepare('UPDATE eventos_wp SET estatus = ?, comentario = ?, fecha_actualizacion_estatus = NOW() WHERE id = ? LIMIT 1');
    }
    if (!$stmt) {
        throw new Exception('Error prepare update: ' . $conn->error);
    }

    if ($estatus === 'cliente') {
        $stmt->bind_param('ssi', $eventStatus, $comentarioACliente, $id);
    } else {
        $stmt->bind_param('ssi', $eventStatus, $comentario, $id);
    }
    if (!$stmt->execute()) {
        throw new Exception('Error al actualizar estatus: ' . $stmt->error);
    }

    if ($stmt->affected_rows < 0) {
        throw new Exception('No se pudo actualizar el evento');
    }

    $stmt->close();

    if ($estatus === 'atendido') {
        wpEventStampFechaAtendidoIfEmpty($conn, $id);
    }

    $isAfianzado = intval($eventRow['afianzado'] ?? 0) === 1;
    if ($isAfianzado && $estatus === 'atendido') {
        ensureAfianzadoCalendarSynced($conn, $id, $eventStatus, 1);
    }

    if ($estatus === 'cliente') {
        if ($isAfianzado) {
            markAfianzadoEventAsClient($conn, $id);
        } else {
            upsertEventoClientContactForm($conn, $id);
        }
    }

    $conn->commit();
    sendEventoWpStatusEmail($conn, $id, $estatus, $comentario, $comentarioACliente);
    $successMessage = 'El estatus del evento se actualizó correctamente.';
    if ($estatus === 'cliente') {
        $successMessage = 'El evento se pasó a cliente correctamente.';
    }
    echo json_encode(['success' => true, 'message' => $successMessage]);
    exit;
} catch (Exception $e) {
    if ($conn->errno === 0) {
        $conn->rollback();
    } else {
        @ $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>