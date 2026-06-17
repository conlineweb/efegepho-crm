<?php
/**
 * Helper para el historial de cambios de estatus de citas (tabla `calendario`).
 *
 * Objetivo: conservar la trazabilidad completa de TODAS las transiciones de estatus
 * de una cita, ya que `calendario.estatus` solo guarda el estatus actual (se sobreescribe).
 *
 * Códigos de estatus usados (consistentes con el resto del sistema):
 *   0 = agendado / pendiente
 *   1 = atendido
 *   2 = fantasma (expirada)
 *   3 = muerto (sin respuesta)
 *   4 = cliente (cierre)  -> no existe en calendario.estatus, pero se registra aquí para trazabilidad
 *
 * Diseño aditivo: no modifica ninguna lectura existente. Solo agrega una tabla nueva
 * y se invoca en cada punto donde se actualiza el estatus.
 */

if (!function_exists('ensureCalendarioEstatusHistorialTable')) {
    /**
     * Crea la tabla `calendario_estatus_historial` si no existe. Idempotente.
     */
    function ensureCalendarioEstatusHistorialTable($conn)
    {
        static $verificada = false;
        if ($verificada) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `calendario_estatus_historial` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_calendario` INT NOT NULL,
            `estatus` INT NOT NULL COMMENT '0=agendado,1=atendido,2=fantasma,3=muerto,4=cliente',
            `estatus_anterior` INT DEFAULT NULL,
            `fecha_cambio` DATETIME NOT NULL,
            `usuario` INT DEFAULT NULL,
            `origen` VARCHAR(50) DEFAULT NULL COMMENT 'archivo/accion que origino el cambio',
            `observaciones` TEXT DEFAULT NULL,
            `es_estimado` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = reconstruido por backfill (fecha aproximada)',
            PRIMARY KEY (`id`),
            KEY `idx_id_calendario` (`id_calendario`),
            KEY `idx_estatus` (`estatus`),
            KEY `idx_fecha_cambio` (`fecha_cambio`),
            KEY `idx_cal_estatus_fecha` (`id_calendario`, `estatus`, `fecha_cambio`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3";

        $ok = $conn->query($sql);
        if ($ok) {
            $verificada = true;
        }
        return (bool) $ok;
    }
}

if (!function_exists('registrarCambioEstatusCalendario')) {
    /**
     * Inserta un registro en el historial de estatus.
     *
     * @param mysqli $conn
     * @param int    $idCalendario  id de la fila en `calendario`
     * @param int    $estatusNuevo  estatus al que se cambió (ver tabla de códigos arriba)
     * @param array  $opts {
     *     @var int|null    estatus_anterior  Si es null, se lee de `calendario` antes de insertar.
     *     @var string|null fecha_cambio      'Y-m-d H:i:s'. Default: ahora.
     *     @var int|null    usuario           id del usuario que ejecuta el cambio.
     *     @var string|null origen            etiqueta del archivo/acción.
     *     @var string|null observaciones     texto libre (p.ej. comentario).
     *     @var bool        es_estimado       true si la fecha es aproximada (backfill).
     * }
     * @return bool
     */
    function registrarCambioEstatusCalendario($conn, $idCalendario, $estatusNuevo, array $opts = [])
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $idCalendario = intval($idCalendario);
        if ($idCalendario <= 0) {
            return false;
        }
        $estatusNuevo = intval($estatusNuevo);

        ensureCalendarioEstatusHistorialTable($conn);

        // Resolver estatus anterior: usar el provisto, o leerlo de calendario.
        $estatusAnterior = array_key_exists('estatus_anterior', $opts) ? $opts['estatus_anterior'] : null;
        if ($estatusAnterior === null && empty($opts['skip_lookup_anterior'])) {
            if ($stmt = $conn->prepare("SELECT estatus FROM calendario WHERE id = ? LIMIT 1")) {
                $stmt->bind_param('i', $idCalendario);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($res && ($row = $res->fetch_assoc())) {
                        $estatusAnterior = intval($row['estatus']);
                    }
                }
                $stmt->close();
            }
        }
        $estatusAnterior = ($estatusAnterior === null) ? null : intval($estatusAnterior);

        $fechaCambio   = !empty($opts['fecha_cambio']) ? $opts['fecha_cambio'] : date('Y-m-d H:i:s');
        $usuario       = isset($opts['usuario']) && $opts['usuario'] !== '' ? intval($opts['usuario']) : null;
        $origen        = isset($opts['origen']) ? substr((string) $opts['origen'], 0, 50) : null;
        $observaciones = isset($opts['observaciones']) ? (string) $opts['observaciones'] : null;
        $esEstimado    = !empty($opts['es_estimado']) ? 1 : 0;

        $sql = "INSERT INTO calendario_estatus_historial
                    (id_calendario, estatus, estatus_anterior, fecha_cambio, usuario, origen, observaciones, es_estimado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        // Tipos: i i i s i s s i
        $stmt->bind_param(
            'iiisissi',
            $idCalendario,
            $estatusNuevo,
            $estatusAnterior,
            $fechaCambio,
            $usuario,
            $origen,
            $observaciones,
            $esEstimado
        );

        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    }
}

if (!function_exists('calendarioEstatusHistorialLabel')) {
    function calendarioEstatusHistorialLabel($code)
    {
        $map = [
            0 => 'Agendado',
            1 => 'Atendido',
            2 => 'Fantasma',
            3 => 'Muerto',
            4 => 'Cliente',
        ];
        $code = (int) $code;
        return $map[$code] ?? ('Estatus ' . $code);
    }
}

if (!function_exists('tracerNormalizeDateTime')) {
    function tracerNormalizeDateTime($value)
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '' || strpos($value, '0000-00-00') === 0) {
            return null;
        }
        $candidate = (strpos($value, 'T') !== false) ? substr($value, 0, 19) : $value;
        $ts = strtotime($candidate);
        if ($ts === false || $ts <= 0) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }
}

if (!function_exists('tracerFormatDisplayDateTime')) {
    function tracerFormatDisplayDateTime($value)
    {
        $normalized = tracerNormalizeDateTime($value);
        if ($normalized === null) {
            return '—';
        }
        return date('d/m/Y h:i a', strtotime($normalized));
    }
}

if (!function_exists('tracerResolveUsuarioNombre')) {
    function tracerResolveUsuarioNombre($conn, $usuarioId)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return null;
        }
        static $cache = [];
        if (isset($cache[$usuarioId])) {
            return $cache[$usuarioId];
        }
        $stmt = $conn->prepare('SELECT nombre, apePat FROM usuarios WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $res = $stmt->get_result();
        $nombre = null;
        if ($res && ($row = $res->fetch_assoc())) {
            $nombre = trim(($row['nombre'] ?? '') . ' ' . ($row['apePat'] ?? ''));
            if ($nombre === '') {
                $nombre = 'Usuario #' . $usuarioId;
            }
        }
        $stmt->close();
        $cache[$usuarioId] = $nombre;
        return $nombre;
    }
}

if (!function_exists('tracerFinalizeEvents')) {
    /**
     * Ordena eventos por fecha y elimina campos internos de ordenamiento.
     *
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    function tracerFinalizeEvents(array $events)
    {
        usort($events, function ($a, $b) {
            $ta = (int) ($a['sort_ts'] ?? 0);
            $tb = (int) ($b['sort_ts'] ?? 0);
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }
            return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
        });

        $cleanEvents = [];
        foreach ($events as $ev) {
            unset($ev['sort_ts'], $ev['sort_order']);
            $cleanEvents[] = $ev;
        }

        return $cleanEvents;
    }
}

if (!function_exists('tracerAppendLeadInteractionsToEvents')) {
    /**
     * Agrega interacciones de lead_interactions al timeline, en orden cronológico.
     */
    function tracerAppendLeadInteractionsToEvents($conn, array &$events, $contactFormId = 0, $tablaOrigen = '', $origId = 0)
    {
        if (!($conn instanceof mysqli)) {
            return;
        }

        $tableCheck = $conn->query("SHOW TABLES LIKE 'lead_interactions'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return;
        }

        $contactFormId = (int) $contactFormId;
        $tablaOrigen = trim((string) $tablaOrigen);
        $origId = (int) $origId;

        $whereParts = [];
        $params = [];
        $types = '';

        if ($contactFormId > 0) {
            $whereParts[] = "(LOWER(tabla_origen) = 'contact_form' AND lead_id = ?)";
            $params[] = $contactFormId;
            $types .= 'i';
        }

        if ($tablaOrigen !== '' && $origId > 0) {
            $whereParts[] = "(LOWER(tabla_origen) = LOWER(?) AND (lead_id = ? OR original_lead_id = ?))";
            $params[] = $tablaOrigen;
            $params[] = $origId;
            $params[] = $origId;
            $types .= 'sii';
        }

        if (empty($whereParts)) {
            return;
        }

        $sql = 'SELECT * FROM lead_interactions WHERE ' . implode(' OR ', $whereParts)
            . ' ORDER BY COALESCE(interaction_date, DATE(created_at)) ASC,'
            . ' COALESCE(interaction_time, TIME(created_at)) ASC, id ASC';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return;
        }

        $bindArgs = [$types];
        foreach ($params as $index => $value) {
            $bindArgs[] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindArgs);

        if (!$stmt->execute()) {
            $stmt->close();
            return;
        }

        $res = $stmt->get_result();
        $seen = [];

        while ($res && ($row = $res->fetch_assoc())) {
            $interactionId = (int) ($row['id'] ?? 0);
            if ($interactionId > 0 && isset($seen['id:' . $interactionId])) {
                continue;
            }

            $notes = trim((string) ($row['notes'] ?? ''));
            $interactionType = trim((string) ($row['interaction_type'] ?? ''));
            $outcome = trim((string) ($row['outcome'] ?? ''));
            $dedupeKey = implode('|', [
                trim((string) ($row['interaction_date'] ?? '')),
                trim((string) ($row['interaction_time'] ?? '')),
                tracerNormalizeDateTime($row['created_at'] ?? null) ?? '',
                $interactionType,
                $notes,
                $outcome,
            ]);
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            if ($interactionId > 0) {
                $seen['id:' . $interactionId] = true;
            }
            $seen[$dedupeKey] = true;

            $interactionDate = trim((string) ($row['interaction_date'] ?? ''));
            $interactionTime = trim((string) ($row['interaction_time'] ?? ''));
            if ($interactionTime !== '' && preg_match('/^\d{1,2}:\d{2}$/', $interactionTime)) {
                $interactionTime .= ':00';
            }

            $interactionDateTime = null;
            if ($interactionDate !== '') {
                $interactionDateTime = tracerNormalizeDateTime(trim($interactionDate . ' ' . ($interactionTime !== '' ? $interactionTime : '00:00:00')));
            }
            if ($interactionDateTime === null) {
                $interactionDateTime = tracerNormalizeDateTime($row['created_at'] ?? null);
            }
            if ($interactionDateTime === null) {
                continue;
            }

            $label = $interactionType !== '' ? $interactionType : 'Interacción';
            $detalle = $notes;
            if ($detalle === '' && $outcome !== '' && strcasecmp($outcome, 'sin respuesta') !== 0) {
                $detalle = $outcome;
            }

            $meta = [];
            if ($outcome !== '' && strcasecmp($outcome, 'sin respuesta') !== 0 && $outcome !== $detalle) {
                $meta['Resultado'] = $outcome;
            }

            $nextAction = trim((string) ($row['next_action'] ?? ''));
            $nextActionDate = trim((string) ($row['next_action_date'] ?? ''));
            if ($nextAction !== '' || $nextActionDate !== '') {
                $nextActionText = $nextAction;
                if ($nextActionDate !== '') {
                    $nextActionText = trim($nextActionText . ($nextActionText !== '' ? ' · ' : '') . $nextActionDate);
                }
                $meta['Siguiente acción'] = $nextActionText;
            }

            $createdByName = tracerResolveUsuarioNombre($conn, $row['created_by'] ?? 0);
            if ($createdByName) {
                $meta['Registrado por'] = $createdByName;
            }

            $events[] = [
                'sort_ts'         => strtotime($interactionDateTime),
                'sort_order'      => 25,
                'fecha'           => $interactionDateTime,
                'fecha_display'   => tracerFormatDisplayDateTime($interactionDateTime),
                'tipo'            => 'interaccion',
                'label'           => $label,
                'detalle'         => $detalle,
                'meta'            => $meta,
                'es_estimado'     => false,
            ];
        }

        $stmt->close();
    }
}

if (!function_exists('obtenerTrazabilidadLeadPorContactForm')) {
    /**
     * Timeline completo de un lead post-calificación (contact_form.id).
     *
     * @return array{success:bool,error?:string,lead?:array,events?:array}
     */
    function obtenerTrazabilidadLeadPorContactForm($conn, $contactFormId)
    {
        if (!($conn instanceof mysqli)) {
            return ['success' => false, 'error' => 'Conexión inválida'];
        }

        ensureCalendarioEstatusHistorialTable($conn);

        $contactFormId = (int) $contactFormId;
        if ($contactFormId <= 0) {
            return ['success' => false, 'error' => 'ID de lead inválido'];
        }

        $stmt = $conn->prepare('SELECT * FROM contact_form WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return ['success' => false, 'error' => 'No se pudo consultar el lead'];
        }
        $stmt->bind_param('i', $contactFormId);
        $stmt->execute();
        $cfRes = $stmt->get_result();
        $cf = $cfRes ? $cfRes->fetch_assoc() : null;
        $stmt->close();

        if (!$cf) {
            return ['success' => false, 'error' => 'Lead no encontrado'];
        }

        $leadName = trim((string) ($cf['names'] ?? ''));
        if ($leadName === '') {
            $leadName = 'Lead #' . $contactFormId;
        }

        $tablaOrigen = trim((string) ($cf['tabla_origen'] ?? ''));
        $origId = (int) ($cf['original_lead_id'] ?? 0);
        $origCreatedTime = null;
        $origLeadName = null;

        if ($tablaOrigen !== '' && $origId > 0) {
            $escapedForm = $conn->real_escape_string($tablaOrigen);
            $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
                if ($leadRes && $leadRes->num_rows > 0) {
                    $leadRow = $leadRes->fetch_assoc();
                    $origCreatedTime = tracerNormalizeDateTime($leadRow['created_time'] ?? $leadRow['created_at'] ?? null);
                    foreach (['full_name', 'names', 'name'] as $nameKey) {
                        $candidate = trim((string) ($leadRow[$nameKey] ?? ''));
                        if ($candidate !== '') {
                            $origLeadName = $candidate;
                            break;
                        }
                    }
                }
            }
        }

        if ($origLeadName !== null && $origLeadName !== '') {
            $leadName = $origLeadName;
        }

        $events = [];

        if ($origCreatedTime !== null) {
            $events[] = [
                'sort_ts'     => strtotime($origCreatedTime),
                'sort_order'  => 10,
                'fecha'       => $origCreatedTime,
                'fecha_display' => tracerFormatDisplayDateTime($origCreatedTime),
                'tipo'        => 'pre_lead',
                'label'       => 'Registro del lead',
                'detalle'     => 'Pre-calificación en ' . $tablaOrigen . ' (ID ' . $origId . ')',
                'meta'        => [],
                'es_estimado' => false,
            ];
        }

        $cfCreated = tracerNormalizeDateTime($cf['created_time'] ?? null);
        $cfSubmitted = tracerNormalizeDateTime($cf['submission_date'] ?? null);
        $postEntryTime = $cfCreated ?? $cfSubmitted;

        $isDuplicatePostEntry = false;
        if ($postEntryTime !== null && $origCreatedTime !== null) {
            $isDuplicatePostEntry = abs(strtotime($postEntryTime) - strtotime($origCreatedTime)) <= 60;
        }

        if ($postEntryTime !== null && !$isDuplicatePostEntry) {
            $events[] = [
                'sort_ts'     => strtotime($postEntryTime),
                'sort_order'  => 20,
                'fecha'       => $postEntryTime,
                'fecha_display' => tracerFormatDisplayDateTime($postEntryTime),
                'tipo'        => 'post_lead',
                'label'       => 'Entrada post-calificación',
                'detalle'     => 'Registro en contact_form (ID ' . $contactFormId . ')',
                'meta'        => [],
                'es_estimado' => false,
            ];
        }

        $calStmt = $conn->prepare(
            'SELECT c.*, u.nombre AS v_nombre, u.apePat AS v_apePat
             FROM calendario c
             LEFT JOIN usuarios u ON u.id = c.idusu
             WHERE c.idclie = ?
             ORDER BY c.id ASC'
        );
        if (!$calStmt) {
            return ['success' => false, 'error' => 'No se pudo consultar citas'];
        }
        $calStmt->bind_param('i', $contactFormId);
        $calStmt->execute();
        $calRes = $calStmt->get_result();

        $latestCal = null;

        while ($cal = $calRes->fetch_assoc()) {
            $latestCal = $cal;
            $calId = (int) ($cal['id'] ?? 0);
            if ($calId <= 0) {
                continue;
            }

            $citaFecha = trim((string) ($cal['fecha'] ?? ''));
            $citaHora = trim((string) ($cal['hora'] ?? ''));
            $citaSlot = tracerNormalizeDateTime($citaFecha . ' ' . $citaHora);
            $vendedor = trim(($cal['v_nombre'] ?? '') . ' ' . ($cal['v_apePat'] ?? ''));
            if ($vendedor === '') {
                $vendedor = null;
            }

            $histStmt = $conn->prepare(
                'SELECT h.*, u.nombre AS h_nombre, u.apePat AS h_apePat
                 FROM calendario_estatus_historial h
                 LEFT JOIN usuarios u ON u.id = h.usuario
                 WHERE h.id_calendario = ?
                 ORDER BY h.fecha_cambio ASC, h.id ASC'
            );
            if (!$histStmt) {
                continue;
            }
            $histStmt->bind_param('i', $calId);
            $histStmt->execute();
            $histRes = $histStmt->get_result();

            $histCount = 0;
            while ($hist = $histRes->fetch_assoc()) {
                $histCount++;
                $estatusCode = (int) ($hist['estatus'] ?? -1);

                $fechaCambio = tracerNormalizeDateTime($hist['fecha_cambio'] ?? null);
                if ($fechaCambio === null) {
                    continue;
                }

                $estatusLabel = calendarioEstatusHistorialLabel($estatusCode);
                $anteriorCode = $hist['estatus_anterior'];
                $anteriorLabel = ($anteriorCode !== null && $anteriorCode !== '')
                    ? calendarioEstatusHistorialLabel((int) $anteriorCode)
                    : null;

                $detalle = 'Cita #' . $calId;
                if ($citaSlot !== null) {
                    $detalle .= ' · Sesión: ' . tracerFormatDisplayDateTime($citaSlot);
                }
                if ($anteriorLabel !== null && $anteriorLabel !== $estatusLabel) {
                    $detalle .= ' · De ' . $anteriorLabel . ' a ' . $estatusLabel;
                }

                $meta = [];
                if ($vendedor) {
                    $meta['Asesora'] = $vendedor;
                }
                $histUser = trim(($hist['h_nombre'] ?? '') . ' ' . ($hist['h_apePat'] ?? ''));
                if ($histUser !== '') {
                    $meta['Registrado por'] = $histUser;
                }
                if (!empty($hist['origen'])) {
                    $meta['Origen'] = (string) $hist['origen'];
                }
                if (!empty($hist['observaciones'])) {
                    $meta['Notas'] = (string) $hist['observaciones'];
                }

                $events[] = [
                    'sort_ts'       => strtotime($fechaCambio),
                    'sort_order'    => 30 + $estatusCode,
                    'fecha'         => $fechaCambio,
                    'fecha_display' => tracerFormatDisplayDateTime($fechaCambio),
                    'tipo'          => 'estatus_' . $estatusCode,
                    'label'         => $estatusLabel,
                    'detalle'       => $detalle,
                    'meta'          => $meta,
                    'es_estimado'   => !empty($hist['es_estimado']),
                ];
            }
            $histStmt->close();

            if ($histCount === 0) {
                $fallbackTime = tracerNormalizeDateTime($cal['fecha_registro'] ?? null) ?? $postEntryTime ?? $citaSlot;
                $estatusActual = (int) ($cal['estatus'] ?? 0);
                if ($fallbackTime !== null) {
                    $events[] = [
                        'sort_ts'       => strtotime($fallbackTime),
                        'sort_order'    => 30 + $estatusActual,
                        'fecha'         => $fallbackTime,
                        'fecha_display' => tracerFormatDisplayDateTime($fallbackTime),
                        'tipo'          => 'estatus_' . $estatusActual,
                        'label'         => calendarioEstatusHistorialLabel($estatusActual) . ' (actual)',
                        'detalle'       => 'Cita #' . $calId . ' · Sin historial detallado; se muestra estatus actual',
                        'meta'          => $vendedor ? ['Asesora' => $vendedor] : [],
                        'es_estimado'   => true,
                    ];
                }
            }
        }
        $calStmt->close();

        $estatusActualLabel = '—';
        if ((int) ($cf['cliente'] ?? 0) === 1) {
            $estatusActualLabel = 'Cliente';
        } elseif ($latestCal !== null) {
            $estatusActualLabel = calendarioEstatusHistorialLabel((int) ($latestCal['estatus'] ?? 0));
        }

        $fechaCambioCliente = tracerNormalizeDateTime($cf['fecha_cambio_cliente'] ?? null);
        if ((int) ($cf['cliente'] ?? 0) === 1 && $fechaCambioCliente !== null) {
            $alreadyLogged = false;
            foreach ($events as $ev) {
                if (($ev['tipo'] ?? '') === 'estatus_4' && abs(($ev['sort_ts'] ?? 0) - strtotime($fechaCambioCliente)) <= 120) {
                    $alreadyLogged = true;
                    break;
                }
            }
            if (!$alreadyLogged) {
                $events[] = [
                    'sort_ts'       => strtotime($fechaCambioCliente),
                    'sort_order'    => 74,
                    'fecha'         => $fechaCambioCliente,
                    'fecha_display' => tracerFormatDisplayDateTime($fechaCambioCliente),
                    'tipo'          => 'cliente_cierre',
                    'label'         => 'Cliente cerrado',
                    'detalle'       => 'Cierre registrado en contact_form',
                    'meta'          => [],
                    'es_estimado'   => false,
                ];
            }
        }

        tracerAppendLeadInteractionsToEvents($conn, $events, $contactFormId, $tablaOrigen, $origId);

        $cleanEvents = tracerFinalizeEvents($events);

        return [
            'success' => true,
            'lead'    => [
                'id'           => $contactFormId,
                'nombre'       => $leadName,
                'email'        => trim((string) ($cf['email_address'] ?? '')),
                'tabla_origen' => $tablaOrigen,
                'estatus_actual' => $estatusActualLabel,
            ],
            'events'  => $cleanEvents,
        ];
    }
}

if (!function_exists('obtenerTrazabilidadLeadSoloPreCalificacion')) {
    /**
     * Trazabilidad mínima cuando el lead aún no tiene contact_form.
     *
     * @return array{success:bool,error?:string,lead?:array,events?:array}
     */
    function obtenerTrazabilidadLeadSoloPreCalificacion($conn, $tablaOrigen, $origId)
    {
        if (!($conn instanceof mysqli)) {
            return ['success' => false, 'error' => 'Conexión inválida'];
        }

        $tablaOrigen = trim((string) $tablaOrigen);
        $origId = (int) $origId;
        if ($tablaOrigen === '' || $origId <= 0) {
            return ['success' => false, 'error' => 'Parámetros inválidos'];
        }

        $escapedTable = $conn->real_escape_string($tablaOrigen);
        $checkTable = $conn->query("SHOW TABLES LIKE '$escapedTable'");
        if (!$checkTable || $checkTable->num_rows === 0) {
            return ['success' => false, 'error' => 'Tabla de origen no encontrada'];
        }

        $leadRes = $conn->query("SELECT * FROM `$escapedTable` WHERE id = $origId LIMIT 1");
        if (!$leadRes || $leadRes->num_rows === 0) {
            return ['success' => false, 'error' => 'Lead no encontrado'];
        }

        $leadRow = $leadRes->fetch_assoc();
        require_once __DIR__ . '/pltrace_leads_helper.php';

        $leadName = dashComercialResolveLeadName($leadRow);
        if ($leadName === '' || $leadName === '—') {
            $leadName = 'Lead #' . $origId;
        }

        $status = pltraceResolveLeadStatus($tablaOrigen, null, null, $leadRow);
        $createdTime = tracerNormalizeDateTime($leadRow['created_time'] ?? $leadRow['created_at'] ?? null);
        $events = [];

        if ($createdTime !== null) {
            $events[] = [
                'sort_ts'       => strtotime($createdTime),
                'sort_order'    => 10,
                'fecha'         => $createdTime,
                'fecha_display' => tracerFormatDisplayDateTime($createdTime),
                'tipo'          => 'pre_lead',
                'label'         => 'Registro del lead',
                'detalle'       => 'Pre-calificación en ' . $tablaOrigen . ' (ID ' . $origId . ')',
                'meta'          => [],
                'es_estimado'   => false,
            ];
        }

        tracerAppendLeadInteractionsToEvents($conn, $events, 0, $tablaOrigen, $origId);
        $cleanEvents = tracerFinalizeEvents($events);

        return [
            'success' => true,
            'lead'    => [
                'id'             => $origId,
                'nombre'         => $leadName,
                'email'          => dashComercialResolveLeadEmail($leadRow),
                'tabla_origen'   => $tablaOrigen,
                'estatus_actual' => ucfirst($status),
            ],
            'events'  => $cleanEvents,
        ];
    }
}

if (!function_exists('obtenerTrazabilidadLead')) {
    /**
     * Punto de entrada unificado: contact_form.id o tabla_origen + original_lead_id.
     *
     * @param array{contact_form_id?:int,id?:int,tabla_origen?:string,tabla?:string,orig_id?:int,original_lead_id?:int} $options
     * @return array{success:bool,error?:string,lead?:array,events?:array}
     */
    function obtenerTrazabilidadLead($conn, array $options = [])
    {
        $contactFormId = (int) ($options['contact_form_id'] ?? $options['id'] ?? 0);
        $tablaOrigen = trim((string) ($options['tabla_origen'] ?? $options['tabla'] ?? ''));
        $origId = (int) ($options['orig_id'] ?? $options['original_lead_id'] ?? 0);

        if ($contactFormId > 0) {
            return obtenerTrazabilidadLeadPorContactForm($conn, $contactFormId);
        }

        if ($tablaOrigen !== '' && $origId > 0) {
            require_once __DIR__ . '/pltrace_leads_helper.php';
            $cfId = pltraceFindContactFormId($conn, $tablaOrigen, $origId);
            if ($cfId > 0) {
                return obtenerTrazabilidadLeadPorContactForm($conn, $cfId);
            }
            return obtenerTrazabilidadLeadSoloPreCalificacion($conn, $tablaOrigen, $origId);
        }

        return ['success' => false, 'error' => 'Parámetros inválidos'];
    }
}
