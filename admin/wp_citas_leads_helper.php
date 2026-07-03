<?php

function wpCitasEnsureColumnExists($conn, $table, $columnName, $columnDef)
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($columnName);
    $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safeTable}' AND COLUMN_NAME = '{$safeColumn}'";
    $res = $conn->query($checkSql);
    if ($res) {
        $row = $res->fetch_assoc();
        if (intval($row['c'] ?? 0) === 0) {
            if (!$conn->query("ALTER TABLE `{$table}` ADD COLUMN {$columnDef}")) {
                throw new Exception('No se pudo agregar la columna ' . $columnName . ' en ' . $table . ': ' . $conn->error);
            }
        }
    }
}

function wpCitasTableHasColumn($conn, $table, $columnName)
{
    static $cache = [];

    $key = strtolower($table . '|' . $columnName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($columnName);
    $sql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safeTable}' AND COLUMN_NAME = '{$safeColumn}'";
    $res = $conn->query($sql);
    $exists = false;
    if ($res) {
        $row = $res->fetch_assoc();
        $exists = intval($row['c'] ?? 0) > 0;
    }

    $cache[$key] = $exists;
    return $exists;
}

function wpCitasFirstNonEmptyValue()
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

function wpCitasBindValues($stmt, array &$values)
{
    $types = '';
    $params = [];
    $params[] = &$types;

    foreach ($values as $index => $value) {
        if ($value === null) {
            $types .= 's';
            $values[$index] = null;
        } elseif (is_int($value)) {
            $types .= 'i';
        } else {
            $types .= 's';
            if (!is_string($value)) {
                $values[$index] = (string) $value;
            }
        }

        $params[] = &$values[$index];
    }

    call_user_func_array([$stmt, 'bind_param'], $params);
}

function wpCitasEnsureLeadTableExists($conn)
{
    $createSql = "CREATE TABLE IF NOT EXISTS wp_citas_leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_calendario INT NOT NULL,
        id_evento INT NOT NULL,
        id_wedding_planner INT NOT NULL,
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
        how_did_you_meet VARCHAR(50) DEFAULT NULL,
        hear_about_us VARCHAR(255) DEFAULT NULL,
        campaign_name VARCHAR(255) DEFAULT NULL,
        form_name VARCHAR(255) DEFAULT NULL,
        platform VARCHAR(100) DEFAULT NULL,
        choose_your_call_date_ VARCHAR(255) DEFAULT NULL,
        lead_status VARCHAR(100) DEFAULT NULL,
        estatus_cita VARCHAR(100) DEFAULT NULL,
        id_vendedor_asignado INT DEFAULT NULL,
        usuario_asignado INT DEFAULT NULL,
        calendario_fecha DATE DEFAULT NULL,
        calendario_hora TIME DEFAULT NULL,
        fecha_cliente DATE DEFAULT NULL,
        hora_cliente TIME DEFAULT NULL,
        comentario TEXT DEFAULT NULL,
        cliente_engagement TINYINT(1) DEFAULT NULL,
        descartado TINYINT(1) DEFAULT 0,
        tipo_cliente TINYINT(1) DEFAULT NULL COMMENT '1=Wedding Planner, 0=Cliente Final',
        UNIQUE KEY uniq_wp_citas_leads_calendario (id_calendario),
        KEY idx_wp_citas_leads_created_time (created_time),
        KEY idx_wp_citas_leads_descartado (descartado),
        KEY idx_wp_citas_leads_evento (id_evento),
        KEY idx_wp_citas_leads_planner (id_wedding_planner)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($createSql)) {
        throw new Exception('No se pudo crear la tabla wp_citas_leads: ' . $conn->error);
    }

    wpCitasEnsureColumnExists($conn, 'wp_citas_leads', 'tipo_cliente', "`tipo_cliente` TINYINT(1) DEFAULT NULL COMMENT '1=Wedding Planner, 0=Cliente Final' AFTER `descartado`");
}

function wpCitasEnsureLeadTableRegistered($conn)
{
    $tableName = 'wp_citas_leads';
    $stmt = $conn->prepare('SELECT 1 FROM tablas_leads WHERE nombre = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar tablas_leads para wp_citas_leads: ' . $conn->error);
    }

    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $res = $stmt->get_result();
    $alreadyRegistered = $res && $res->num_rows > 0;
    $stmt->close();

    if ($alreadyRegistered) {
        return;
    }

    $columns = [];
    $columnsRes = $conn->query('SHOW COLUMNS FROM tablas_leads');
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
        $insertValues[] = 2;
        $insertTypes .= 'i';
    }
    if (in_array('descripcion', $columns, true)) {
        $insertColumns[] = 'descripcion';
        $insertValues[] = 'Citas reales de Wedding Planner';
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
    $sql = 'INSERT INTO tablas_leads (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('No se pudo registrar wp_citas_leads en tablas_leads: ' . $conn->error);
    }

    $params = [];
    $params[] = &$insertTypes;
    foreach ($insertValues as $index => $value) {
        $insertValues[$index] = $value;
        $params[] = &$insertValues[$index];
    }

    call_user_func_array([$stmt, 'bind_param'], $params);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo insertar wp_citas_leads en tablas_leads: ' . $stmt->error);
    }
    $stmt->close();
}

function wpCitasMapLeadStatus($eventStatus, $calendarStatus)
{
    $eventStatus = trim((string) $eventStatus);
    if ($eventStatus !== '') {
        if (is_numeric($eventStatus)) {
            $intStatus = intval($eventStatus);
            if ($intStatus === 1) {
                return 'atendido';
            }
            if ($intStatus === 2) {
                return 'cliente';
            }
            if ($intStatus === 3) {
                return 'muerto';
            }
            if ($intStatus === 0) {
                return 'agendado';
            }
        }

        $normalized = mb_strtolower($eventStatus, 'UTF-8');
        if (in_array($normalized, ['agendado', 'atendido', 'cliente', 'muerto', 'fantasma'], true)) {
            return $normalized;
        }

        return $eventStatus;
    }

    $calendarStatus = trim((string) $calendarStatus);
    if ($calendarStatus === '') {
        return 'agendado';
    }

    if (is_numeric($calendarStatus)) {
        $intStatus = intval($calendarStatus);
        if ($intStatus === 1) {
            return 'atendido';
        }
        if ($intStatus === 2) {
            return 'fantasma';
        }
        if ($intStatus === 3) {
            return 'muerto';
        }
        if ($intStatus === 0) {
            return 'agendado';
        }
    }

    return $calendarStatus;
}

function wpCitasBuildWeddingPlannerSelect($conn, $columnName, $alias, $fallback = 'NULL')
{
    if (wpCitasTableHasColumn($conn, 'wedding_planners', $columnName)) {
        return 'wp.`' . $columnName . '` AS `' . $alias . '`';
    }

    return $fallback . ' AS `' . $alias . '`';
}

function wpCitasFetchSourceByCalendarId($conn, $calendarId)
{
    $selects = [
        'c.id AS id_calendario',
        'c.idclie AS id_evento',
        'c.fecha',
        'c.hora',
        'c.fecha_cliente',
        'c.hora_cliente',
        'c.fecha_registro',
        'c.comentario',
        'c.cliente_city',
        'c.cliente_engagement',
        'c.estatus AS calendario_estatus',
        'c.idusu',
        'c.eliminado',
        'ev.wedding_planner_id',
        'ev.id_asesor',
        'ev.fecha_registro AS evento_fecha_registro',
        'ev.created_time AS evento_created_time',
        'ev.estatus AS evento_estatus',
        wpCitasBuildWeddingPlannerSelect($conn, 'campaign_name', 'wp_campaign_name', "''"),
        wpCitasBuildWeddingPlannerSelect($conn, 'empresa_wp', 'wp_empresa', "''"),
        wpCitasBuildWeddingPlannerSelect($conn, 'full_name', 'wp_full_name', "''"),
        wpCitasBuildWeddingPlannerSelect($conn, 'phone', 'wp_phone', "''"),
        wpCitasBuildWeddingPlannerSelect($conn, 'email', 'wp_email', "''"),
        wpCitasBuildWeddingPlannerSelect($conn, 'city', 'wp_city', "''"),
        wpCitasBuildWeddingPlannerSelect($conn, 'planner_reason', 'wp_planner_reason', "''"),
        wpCitasBuildWeddingPlannerSelect($conn, 'how_long_known_us', 'wp_how_long_known_us', "''"),
        wpCitasBuildWeddingPlannerSelect($conn, 'first_contact_channel', 'wp_first_contact_channel', "''"),
        wpCitasBuildWeddingPlannerSelect($conn, 'how_did_you_meet', 'wp_how_did_you_meet', "''"),
        wpCitasBuildWeddingPlannerSelect($conn, 'hear_about_us', 'wp_hear_about_us', "''"),
        wpCitasBuildWeddingPlannerSelect($conn, 'id_vendedor_asignado', 'wp_id_vendedor_asignado', '0')
    ];

    $sql = 'SELECT ' . implode(",\n                ", $selects) . "
            FROM calendario c
            INNER JOIN eventos_wp ev ON ev.id = c.idclie
            INNER JOIN wedding_planners wp ON wp.id = ev.wedding_planner_id
            WHERE c.id = ? AND c.tipo = 1
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('No se pudo consultar la fuente de wp_citas_leads: ' . $conn->error);
    }

    $stmt->bind_param('i', $calendarId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row;
}

function wpCitasDiscardLeadByCalendarId($conn, $calendarId)
{
    wpCitasEnsureLeadTableExists($conn);
    wpCitasEnsureLeadTableRegistered($conn);

    $stmt = $conn->prepare('UPDATE wp_citas_leads SET descartado = 1 WHERE id_calendario = ?');
    if (!$stmt) {
        throw new Exception('No se pudo preparar el descarte de wp_citas_leads: ' . $conn->error);
    }

    $stmt->bind_param('i', $calendarId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo descartar el lead espejo de WP: ' . $stmt->error);
    }
    $stmt->close();
}

function wpCitasSyncLeadByCalendarId($conn, $calendarId)
{
    wpCitasEnsureLeadTableExists($conn);
    wpCitasEnsureLeadTableRegistered($conn);

    $source = wpCitasFetchSourceByCalendarId($conn, $calendarId);
    if (!$source) {
        wpCitasDiscardLeadByCalendarId($conn, $calendarId);
        return false;
    }

    if (intval($source['eliminado'] ?? 0) === 1) {
        wpCitasDiscardLeadByCalendarId($conn, $calendarId);
        return true;
    }

    $createdTime = wpCitasFirstNonEmptyValue(
        $source['evento_fecha_registro'] ?? '',
        $source['fecha_registro'] ?? '',
        $source['evento_created_time'] ?? '',
        date('Y-m-d H:i:s')
    );
    $fullName = wpCitasFirstNonEmptyValue(
        $source['wp_empresa'] ?? '',
        $source['wp_full_name'] ?? '',
        $source['wp_campaign_name'] ?? '',
        'Wedding Planner'
    );
    $leadStatus = wpCitasMapLeadStatus($source['evento_estatus'] ?? '', $source['calendario_estatus'] ?? '');
    $hearAboutUs = wpCitasFirstNonEmptyValue($source['wp_hear_about_us'] ?? '', $source['wp_planner_reason'] ?? '');
    $idVendedorAsignado = intval($source['idusu'] ?? 0) > 0
        ? intval($source['idusu'])
        : (intval($source['id_asesor'] ?? 0) > 0
            ? intval($source['id_asesor'])
            : intval($source['wp_id_vendedor_asignado'] ?? 0));

    $payload = [
        'id_calendario' => intval($source['id_calendario']),
        'id_evento' => intval($source['id_evento']),
        'id_wedding_planner' => intval($source['wedding_planner_id'] ?? 0),
        'created_time' => $createdTime,
        'fecha_importacion' => date('Y-m-d H:i:s'),
        'full_name' => $fullName,
        'email' => trim((string) ($source['wp_email'] ?? '')),
        'phone' => trim((string) ($source['wp_phone'] ?? '')),
        'country_code' => '',
        'city' => wpCitasFirstNonEmptyValue($source['cliente_city'] ?? '', $source['wp_city'] ?? ''),
        'wedding_location' => '',
        'wedding_date' => null,
        'how_long_known_us' => trim((string) ($source['wp_how_long_known_us'] ?? '')),
        'first_contact_channel' => trim((string) ($source['wp_first_contact_channel'] ?? '')),
        'how_did_you_meet' => '1',
        'tipo_cliente' => 1,
        'hear_about_us' => $hearAboutUs,
        'campaign_name' => 'Wedding Planner',
        'form_name' => 'wp_citas_leads',
        'platform' => 'Wedding Planner',
        'choose_your_call_date_' => !empty($source['fecha']) ? date('Y-m-d', strtotime($source['fecha'])) : null,
        'lead_status' => $leadStatus,
        'estatus_cita' => $leadStatus,
        'id_vendedor_asignado' => $idVendedorAsignado,
        'usuario_asignado' => $idVendedorAsignado,
        'calendario_fecha' => !empty($source['fecha']) ? date('Y-m-d', strtotime($source['fecha'])) : null,
        'calendario_hora' => !empty($source['hora']) ? date('H:i:s', strtotime($source['hora'])) : null,
        'fecha_cliente' => !empty($source['fecha_cliente']) ? date('Y-m-d', strtotime($source['fecha_cliente'])) : null,
        'hora_cliente' => !empty($source['hora_cliente']) ? date('H:i:s', strtotime($source['hora_cliente'])) : null,
        'comentario' => trim((string) ($source['comentario'] ?? '')),
        'cliente_engagement' => isset($source['cliente_engagement']) && $source['cliente_engagement'] !== '' ? intval($source['cliente_engagement']) : null,
        'descartado' => 0
    ];

    $stmt = $conn->prepare('SELECT id FROM wp_citas_leads WHERE id_calendario = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('No se pudo validar wp_citas_leads existente: ' . $conn->error);
    }
    $stmt->bind_param('i', $calendarId);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($existing) {
        $mirrorId = intval($existing['id']);
        $stmt = $conn->prepare('UPDATE wp_citas_leads SET id_evento = ?, id_wedding_planner = ?, created_time = ?, fecha_importacion = ?, full_name = ?, email = ?, phone = ?, country_code = ?, city = ?, wedding_location = ?, wedding_date = ?, how_long_known_us = ?, first_contact_channel = ?, how_did_you_meet = ?, tipo_cliente = ?, hear_about_us = ?, campaign_name = ?, form_name = ?, platform = ?, choose_your_call_date_ = ?, lead_status = ?, estatus_cita = ?, id_vendedor_asignado = ?, usuario_asignado = ?, calendario_fecha = ?, calendario_hora = ?, fecha_cliente = ?, hora_cliente = ?, comentario = ?, cliente_engagement = ?, descartado = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización de wp_citas_leads: ' . $conn->error);
        }

        $updateValues = [
            $payload['id_evento'],
            $payload['id_wedding_planner'],
            $payload['created_time'],
            $payload['fecha_importacion'],
            $payload['full_name'],
            $payload['email'],
            $payload['phone'],
            $payload['country_code'],
            $payload['city'],
            $payload['wedding_location'],
            $payload['wedding_date'],
            $payload['how_long_known_us'],
            $payload['first_contact_channel'],
            $payload['how_did_you_meet'],
            $payload['tipo_cliente'],
            $payload['hear_about_us'],
            $payload['campaign_name'],
            $payload['form_name'],
            $payload['platform'],
            $payload['choose_your_call_date_'],
            $payload['lead_status'],
            $payload['estatus_cita'],
            $payload['id_vendedor_asignado'],
            $payload['usuario_asignado'],
            $payload['calendario_fecha'],
            $payload['calendario_hora'],
            $payload['fecha_cliente'],
            $payload['hora_cliente'],
            $payload['comentario'],
            $payload['cliente_engagement'],
            $payload['descartado'],
            $mirrorId
        ];
        wpCitasBindValues($stmt, $updateValues);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar wp_citas_leads: ' . $stmt->error);
        }
        $stmt->close();
        return $mirrorId;
    }

    $stmt = $conn->prepare('INSERT INTO wp_citas_leads (id_calendario, id_evento, id_wedding_planner, created_time, fecha_importacion, full_name, email, phone, country_code, city, wedding_location, wedding_date, how_long_known_us, first_contact_channel, how_did_you_meet, tipo_cliente, hear_about_us, campaign_name, form_name, platform, choose_your_call_date_, lead_status, estatus_cita, id_vendedor_asignado, usuario_asignado, calendario_fecha, calendario_hora, fecha_cliente, hora_cliente, comentario, cliente_engagement, descartado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new Exception('No se pudo preparar la inserción de wp_citas_leads: ' . $conn->error);
    }

    $insertValues = [
        $payload['id_calendario'],
        $payload['id_evento'],
        $payload['id_wedding_planner'],
        $payload['created_time'],
        $payload['fecha_importacion'],
        $payload['full_name'],
        $payload['email'],
        $payload['phone'],
        $payload['country_code'],
        $payload['city'],
        $payload['wedding_location'],
        $payload['wedding_date'],
        $payload['how_long_known_us'],
        $payload['first_contact_channel'],
        $payload['how_did_you_meet'],
        $payload['tipo_cliente'],
        $payload['hear_about_us'],
        $payload['campaign_name'],
        $payload['form_name'],
        $payload['platform'],
        $payload['choose_your_call_date_'],
        $payload['lead_status'],
        $payload['estatus_cita'],
        $payload['id_vendedor_asignado'],
        $payload['usuario_asignado'],
        $payload['calendario_fecha'],
        $payload['calendario_hora'],
        $payload['fecha_cliente'],
        $payload['hora_cliente'],
        $payload['comentario'],
        $payload['cliente_engagement'],
        $payload['descartado']
    ];
    wpCitasBindValues($stmt, $insertValues);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo insertar wp_citas_leads: ' . $stmt->error);
    }

    $mirrorId = intval($stmt->insert_id);
    $stmt->close();
    return $mirrorId;
}

function wpCitasSyncLeadByEventId($conn, $eventId)
{
    wpCitasEnsureLeadTableExists($conn);
    wpCitasEnsureLeadTableRegistered($conn);

    $calendarIds = [];
    $stmt = $conn->prepare('SELECT id FROM calendario WHERE idclie = ? AND tipo = 1');
    if (!$stmt) {
        throw new Exception('No se pudo consultar calendario para sync de evento WP: ' . $conn->error);
    }

    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res ? $res->fetch_assoc() : null) {
        if (!$row) {
            break;
        }
        $calendarIds[] = intval($row['id']);
    }
    $stmt->close();

    if (empty($calendarIds)) {
        $stmt = $conn->prepare('UPDATE wp_citas_leads SET descartado = 1 WHERE id_evento = ?');
        if (!$stmt) {
            throw new Exception('No se pudo descartar wp_citas_leads por evento: ' . $conn->error);
        }
        $stmt->bind_param('i', $eventId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo descartar wp_citas_leads del evento: ' . $stmt->error);
        }
        $stmt->close();
        return [];
    }

    $syncedIds = [];
    foreach ($calendarIds as $calendarId) {
        $syncedId = wpCitasSyncLeadByCalendarId($conn, $calendarId);
        if ($syncedId !== false) {
            $syncedIds[] = $syncedId;
        }
    }

    return $syncedIds;
}
