<?php
/**
 * Métricas del Dashboard Comercial.
 *
 * Agendas / Atendidos: cohorte post-leads (created_time del lead).
 * Clientes: cierres dentro de esa cohorte.
 * Tabla historial: referencia por fecha_cambio.
 */

require_once __DIR__ . '/calendario_estatus_historial_helper.php';
require_once __DIR__ . '/status_badge_helper.php';

if (!function_exists('dashComercialFormatPct')) {
    function dashComercialFormatPct($numerador, $denominador, $decimals = 1)
    {
        $denominador = floatval($denominador);
        if ($denominador <= 0) {
            return number_format(0, $decimals, '.', '') . '%';
        }
        $pct = (floatval($numerador) / $denominador) * 100;
        $formatted = number_format($pct, $decimals, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted . '%';
    }
}

if (!function_exists('dashComercialBuildDateCondition')) {
    function dashComercialBuildDateCondition($conn, $startDate, $endDate, $columnExpr)
    {
        $parts = [];
        if ($startDate !== '') {
            $sd = date('Y-m-d', strtotime($startDate));
            $parts[] = "DATE($columnExpr) >= '" . $conn->real_escape_string($sd) . "'";
        }
        if ($endDate !== '') {
            $ed = date('Y-m-d', strtotime($endDate));
            $parts[] = "DATE($columnExpr) <= '" . $conn->real_escape_string($ed) . "'";
        }
        return empty($parts) ? '1=1' : implode(' AND ', $parts);
    }
}

if (!function_exists('dashComercialCountTotalLeads')) {
    /**
     * Total de leads pre-calificación en el periodo (tablas_leads, excl. WP).
     */
    function dashComercialCountTotalLeads($conn, $startDate, $endDate)
    {
        $total = 0;
        $resTablas = $conn->query("SELECT nombre FROM tablas_leads WHERE tipo != 2 OR nombre = 'wp_citas_leads' ORDER BY nombre");
        if (!$resTablas) {
            return 0;
        }

        while ($rowTabla = $resTablas->fetch_assoc()) {
            $tableName = $rowTabla['nombre'] ?? '';
            if ($tableName === '') {
                continue;
            }
            $safeTable = $conn->real_escape_string($tableName);
            $check = $conn->query("SHOW TABLES LIKE '$safeTable'");
            if (!$check || $check->num_rows === 0) {
                continue;
            }

            $colsRes = $conn->query("SHOW COLUMNS FROM `$tableName`");
            $columns = [];
            if ($colsRes) {
                while ($col = $colsRes->fetch_assoc()) {
                    $columns[] = $col['Field'];
                }
            }
            if (!in_array('created_time', $columns, true)) {
                continue;
            }

            $where = ['1=1'];
            if (in_array('descartado', $columns, true)) {
                $where[] = '(descartado = 0 OR descartado IS NULL)';
            }
            if ($startDate !== '' || $endDate !== '') {
                $dateExtract = "CASE
                    WHEN created_time LIKE '%T%' THEN DATE(STR_TO_DATE(SUBSTRING(created_time, 1, 19), '%Y-%m-%dT%H:%i:%s'))
                    ELSE DATE(created_time)
                END";
                if ($startDate !== '') {
                    $sd = date('Y-m-d', strtotime($startDate));
                    $where[] = "$dateExtract >= '" . $conn->real_escape_string($sd) . "'";
                }
                if ($endDate !== '') {
                    $ed = date('Y-m-d', strtotime($endDate));
                    $where[] = "$dateExtract <= '" . $conn->real_escape_string($ed) . "'";
                }
            }

            $sql = "SELECT COUNT(*) AS c FROM `$tableName` WHERE " . implode(' AND ', $where);
            $res = $conn->query($sql);
            if ($res && ($r = $res->fetch_assoc())) {
                $total += intval($r['c'] ?? 0);
            }
        }

        return $total;
    }
}

if (!function_exists('dashComercialExtractDateFromTimestamp')) {
    function dashComercialExtractDateFromTimestamp($timestamp)
    {
        if (empty($timestamp)) {
            return null;
        }
        if (strpos($timestamp, 'T') !== false) {
            $normalized = substr($timestamp, 0, 19);
            $ts = strtotime($normalized);
            if ($ts === false) {
                return null;
            }
            return date('Y-m-d', $ts);
        }
        $ts = strtotime($timestamp);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }
}

if (!function_exists('dashComercialGetAppointmentsByClient')) {
    /**
     * Cita más reciente por idclie (misma lógica que consulta_agendados_leads.php).
     */
    function dashComercialGetAppointmentsByClient($conn)
    {
        $map = [];
        $res = $conn->query('SELECT * FROM calendario');
        if (!$res) {
            return $map;
        }
        while ($ar = $res->fetch_assoc()) {
            $idclie = isset($ar['idclie']) ? (int) $ar['idclie'] : 0;
            if ($idclie <= 0) {
                continue;
            }
            if (!isset($map[$idclie])) {
                $map[$idclie] = $ar;
                continue;
            }
            $prev = $map[$idclie];
            $replace = false;
            if (!empty($ar['fecha']) && !empty($prev['fecha'])) {
                $t1 = strtotime($ar['fecha'] . ' ' . ($ar['hora'] ?? '')) ?: 0;
                $t2 = strtotime($prev['fecha'] . ' ' . ($prev['hora'] ?? '')) ?: 0;
                if ($t1 > $t2) {
                    $replace = true;
                }
            } elseif (!empty($ar['id']) && !empty($prev['id'])) {
                if ((int) $ar['id'] > (int) $prev['id']) {
                    $replace = true;
                }
            }
            if ($replace) {
                $map[$idclie] = $ar;
            }
        }
        return $map;
    }
}

if (!function_exists('dashComercialResolveLeadCreatedTime')) {
    function dashComercialResolveLeadCreatedTime($conn, array $cf, $formName, $origId)
    {
        $createdTime = $cf['created_time'] ?? $cf['submission_date'] ?? '';
        if ($formName !== '' && $origId > 0) {
            $escapedForm = $conn->real_escape_string($formName);
            $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
                if ($leadRes && $leadRes->num_rows > 0) {
                    $leadRow = $leadRes->fetch_assoc();
                    if (!empty($leadRow['created_time'])) {
                        $createdTime = $leadRow['created_time'];
                    } elseif (!empty($leadRow['created_at'])) {
                        $createdTime = $leadRow['created_at'];
                    }
                }
            }
        }
        return $createdTime;
    }
}

if (!function_exists('dashComercialResolveLeadStatus')) {
    function dashComercialResolveLeadStatus(array $cf, ?array $appt)
    {
        if ((int) ($cf['cliente'] ?? 0) === 1) {
            return 'cliente';
        }
        if ($appt && isset($appt['estatus'])) {
            $rawStatus = $appt['estatus'];
            $intStatus = is_numeric($rawStatus) ? (int) $rawStatus : null;
            if ($intStatus === 1) {
                return 'atendido';
            }
            if ($intStatus === 3) {
                return 'muerto';
            }
            if ($intStatus === 0) {
                return 'agendado';
            }
            if ($intStatus === 2) {
                return 'fantasma';
            }
            return (string) $rawStatus;
        }
        return '';
    }
}

if (!function_exists('dashComercialLeadPassesAgendadosFilters')) {
    function dashComercialLeadPassesAgendadosFilters(array $cf, ?array $appt, $estatus)
    {
        $tablaOrigenRaw = strtolower(trim($cf['tabla_origen'] ?? ''));
        if ($tablaOrigenRaw === 'wedding_planners' || $tablaOrigenRaw === 'wedding_planner') {
            return false;
        }
        if (!$appt) {
            return false;
        }
        $apptFechaRaw = trim($appt['fecha'] ?? '');
        $apptHoraRaw = trim($appt['hora'] ?? '');
        $apptTs = ($apptFechaRaw !== '') ? strtotime($apptFechaRaw . ' ' . $apptHoraRaw) : false;
        if ($apptFechaRaw === '' || $apptFechaRaw === '0000-00-00' || $apptTs === false || $apptTs <= 0) {
            return false;
        }
        $howDidYouMeetRaw = trim((string) ($cf['how_did_you_meet'] ?? ''));
        if ($howDidYouMeetRaw === '1' && $estatus === 'cliente') {
            return false;
        }
        return true;
    }
}

if (!function_exists('dashComercialLeadInDateRange')) {
    function dashComercialLeadInDateRange($createdTime, $startDate, $endDate)
    {
        if ($startDate === '' && $endDate === '') {
            return true;
        }
        $d = dashComercialExtractDateFromTimestamp($createdTime);
        if ($d === null) {
            return false;
        }
        $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
        $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
        if ($sd && $d < $sd) {
            return false;
        }
        if ($ed && $d > $ed) {
            return false;
        }
        return true;
    }
}

if (!function_exists('dashComercialLoadAtendidoHistorialClientIds')) {
    /**
     * IDs de contact_form que alguna vez alcanzaron estatus 1 en calendario_estatus_historial.
     *
     * @param int[]|null $clientIds null = todos; array = restringir a esos idclie
     * @return array<int, true>
     */
    function dashComercialLoadAtendidoHistorialClientIds($conn, ?array $clientIds = null)
    {
        ensureCalendarioEstatusHistorialTable($conn);

        $ids = [];
        $sql = 'SELECT DISTINCT c.idclie AS idclie
                FROM calendario_estatus_historial h
                INNER JOIN calendario c ON c.id = h.id_calendario
                WHERE h.estatus = 1';

        if ($clientIds !== null) {
            $clientIds = array_values(array_filter(array_map('intval', $clientIds), function ($id) {
                return $id > 0;
            }));
            if (empty($clientIds)) {
                return $ids;
            }
            $sql .= ' AND c.idclie IN (' . implode(',', $clientIds) . ')';
        }

        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $idclie = (int) ($row['idclie'] ?? 0);
                if ($idclie > 0) {
                    $ids[$idclie] = true;
                }
            }
        }

        return $ids;
    }
}

if (!function_exists('dashComercialLeadPassesPostLeadsBaseFilters')) {
    /**
     * Filtros base de consulta_post_leads.php antes del historial de atendido.
     */
    function dashComercialLeadPassesPostLeadsBaseFilters(array $cf, ?array $appt, $estatus)
    {
        if (!dashComercialLeadPassesAgendadosFilters($cf, $appt, $estatus)) {
            return false;
        }
        if ($estatus === 'agendado') {
            return false;
        }
        return true;
    }
}

if (!function_exists('dashComercialLeadPassesPostLeadsFilters')) {
    /**
     * @deprecated Usar dashComercialBuildPostLeadsCohort para replicar el orden de post_leads.
     */
    function dashComercialLeadPassesPostLeadsFilters(array $cf, ?array $appt, $estatus, array $atendidoHistIds)
    {
        if (!dashComercialLeadPassesPostLeadsBaseFilters($cf, $appt, $estatus)) {
            return false;
        }
        $cid = (int) ($cf['id'] ?? 0);
        if ($cid <= 0 || !isset($atendidoHistIds[$cid])) {
            return false;
        }
        return true;
    }
}

if (!function_exists('dashComercialBuildPostLeadsCohort')) {
    /**
     * Cohorte post-calificada (misma secuencia que consulta_post_leads.php):
     * 1) filtros base + fecha created_time del lead
     * 2) historial estatus 1 solo sobre candidatos del paso 1
     *
     * @return array<int, array{cf: array, appt: array, estatus: string, created_time: string}>
     */
    function dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate, $idVendedora = null)
    {
        $appointmentsByClient = dashComercialGetAppointmentsByClient($conn);
        if (empty($appointmentsByClient)) {
            return [];
        }

        $idsList = implode(',', array_map('intval', array_keys($appointmentsByClient)));
        $result = $conn->query("SELECT * FROM contact_form WHERE id IN ($idsList)");
        if (!$result) {
            return [];
        }

        $candidates = [];
        while ($cf = $result->fetch_assoc()) {
            $cid = (int) ($cf['id'] ?? 0);
            if ($cid <= 0 || !isset($appointmentsByClient[$cid])) {
                continue;
            }

            $appt = $appointmentsByClient[$cid];
            $estatus = dashComercialResolveLeadStatus($cf, $appt);
            if (!dashComercialLeadPassesPostLeadsBaseFilters($cf, $appt, $estatus)) {
                continue;
            }

            if ($idVendedora !== null) {
                $idusu = (int) ($appt['idusu'] ?? $appt['id_vendedor_asignado'] ?? 0);
                if ($idusu !== (int) $idVendedora) {
                    continue;
                }
            }

            $formName = $cf['tabla_origen'] ?? '';
            $origId = (int) ($cf['original_lead_id'] ?? 0);
            $createdTime = dashComercialResolveLeadCreatedTime($conn, $cf, $formName, $origId);
            if (!dashComercialLeadInDateRange($createdTime, $startDate, $endDate)) {
                continue;
            }

            $candidates[$cid] = [
                'cf'           => $cf,
                'appt'         => $appt,
                'estatus'      => $estatus,
                'created_time' => $createdTime,
            ];
        }

        if (empty($candidates)) {
            return [];
        }

        $atendidoHistIds = dashComercialLoadAtendidoHistorialClientIds(
            $conn,
            array_map('intval', array_keys($candidates))
        );

        $cohort = [];
        foreach ($candidates as $cid => $item) {
            if (isset($atendidoHistIds[$cid])) {
                $cohort[$cid] = $item;
            }
        }

        return $cohort;
    }
}

if (!function_exists('dashComercialCohortToDisplayRows')) {
    function dashComercialCohortToDisplayRows($conn, array $cohort)
    {
        $vendorMap = dashComercialBuildVendorMap($conn);
        $rows = [];

        foreach ($cohort as $cid => $item) {
            $cf = $item['cf'];
            $appt = $item['appt'];
            $estatus = $item['estatus'];
            $createdTime = $item['created_time'];
            $formName = $cf['tabla_origen'] ?? '';
            $idusu = (int) ($appt['idusu'] ?? $appt['id_vendedor_asignado'] ?? 0);

            $rows[] = [
                'id'        => (int) $cid,
                'nombre'    => dashComercialResolveLeadName($cf),
                'email'     => dashComercialResolveLeadEmail($cf),
                'telefono'  => dashComercialResolveLeadPhone($cf),
                'fecha'     => dashComercialFormatDisplayDate($createdTime),
                'fecha_raw' => $createdTime,
                'vendedora' => $vendorMap[$idusu] ?? ($idusu > 0 ? ('Vendedora #' . $idusu) : '—'),
                'origen'    => trim((string) $formName) ?: '—',
                'estatus'   => $estatus !== '' ? ucfirst($estatus) : '—',
                'estatus_key' => $estatus,
                'cita'      => dashComercialFormatDisplayDate(trim(($appt['fecha'] ?? '') . ' ' . ($appt['hora'] ?? ''))),
            ];
        }

        usort($rows, function ($a, $b) {
            return strcmp((string) ($b['fecha_raw'] ?? ''), (string) ($a['fecha_raw'] ?? ''));
        });

        return $rows;
    }
}

if (!function_exists('dashComercialCountAgendas')) {
    /**
     * Sesiones post-calificadas (consulta_post_leads.php / leadsCountFiltered).
     */
    function dashComercialCountAgendas($conn, $startDate, $endDate, $idVendedora = null)
    {
        return count(dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate, $idVendedora));
    }
}

if (!function_exists('dashComercialCountAtendidos')) {
    /**
     * Misma cohorte que post-leads: ya requiere historial de atendido.
     */
    function dashComercialCountAtendidos($conn, $startDate, $endDate, $idVendedora = null)
    {
        return dashComercialCountAgendas($conn, $startDate, $endDate, $idVendedora);
    }
}

if (!function_exists('dashComercialCountClientes')) {
    /**
     * Cierres dentro de la cohorte post-leads (estatus cliente / cliente = 1).
     */
    function dashComercialCountClientes($conn, $startDate, $endDate, $idVendedora = null)
    {
        $count = 0;
        foreach (dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate, $idVendedora) as $item) {
            $estatus = mb_strtolower(trim((string) ($item['estatus'] ?? '')), 'UTF-8');
            if ($estatus === 'cliente' || (int) ($item['cf']['cliente'] ?? 0) === 1) {
                $count++;
            }
        }
        return $count;
    }
}

if (!function_exists('dashComercialCountHistorialEstatus')) {
    /**
     * Cuenta citas (id_calendario) distintas que alcanzaron un estatus en el periodo.
     *
     * @param int|null $idVendedora null = global; int = filtrar por calendario.idusu
     */
    function dashComercialCountHistorialEstatus($conn, $estatusCode, $startDate, $endDate, $idVendedora = null)
    {
        ensureCalendarioEstatusHistorialTable($conn);

        $estatusCode = (int) $estatusCode;
        $dateCond = dashComercialBuildDateCondition($conn, $startDate, $endDate, 'h.fecha_cambio');
        $vendorCond = '1=1';
        if ($idVendedora !== null) {
            $vendorCond = 'c.idusu = ' . (int) $idVendedora;
        }

        // Prepared statement: evita ambiguedad al interpolar estatus = 0 en SQL
        $sql = "SELECT COUNT(DISTINCT h.id_calendario) AS total
                FROM calendario_estatus_historial h
                INNER JOIN calendario c ON c.id = h.id_calendario
                INNER JOIN contact_form cf ON cf.id = c.idclie
                WHERE CAST(h.estatus AS SIGNED) = ?
                  AND LOWER(COALESCE(cf.tabla_origen, '')) NOT IN ('wedding_planners','wedding_planner')
                  AND COALESCE(c.eliminado, 0) = 0
                  AND $dateCond
                  AND $vendorCond";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $estatusCode);
        $stmt->execute();
        $res = $stmt->get_result();
        $total = 0;
        if ($res && ($row = $res->fetch_assoc())) {
            $total = (int) ($row['total'] ?? 0);
        }
        $stmt->close();
        return $total;
    }
}

if (!function_exists('dashComercialHistorialBreakdown')) {
    /**
     * Conteo de registros en calendario_estatus_historial por estatus (periodo).
     */
    function dashComercialHistorialBreakdown($conn, $startDate, $endDate)
    {
        ensureCalendarioEstatusHistorialTable($conn);
        $dateCond = dashComercialBuildDateCondition($conn, $startDate, $endDate, 'h.fecha_cambio');

        $labels = [
            0 => 'Agendado (0)',
            1 => 'Atendido (1)',
            2 => 'Fantasma (2)',
            3 => 'Muerto (3)',
            4 => 'Cliente (4)',
        ];
        $counts = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0];

        $sql = "SELECT CAST(h.estatus AS SIGNED) AS estatus_code, COUNT(*) AS total
                FROM calendario_estatus_historial h
                INNER JOIN calendario c ON c.id = h.id_calendario
                INNER JOIN contact_form cf ON cf.id = c.idclie
                WHERE LOWER(COALESCE(cf.tabla_origen, '')) NOT IN ('wedding_planners','wedding_planner')
                  AND COALESCE(c.eliminado, 0) = 0
                  AND $dateCond
                GROUP BY CAST(h.estatus AS SIGNED)";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $code = (int) ($row['estatus_code'] ?? -1);
                if (array_key_exists($code, $counts)) {
                    $counts[$code] = (int) ($row['total'] ?? 0);
                }
            }
        }

        $rows = [];
        foreach ($labels as $code => $label) {
            $rows[] = ['code' => $code, 'label' => $label, 'total' => $counts[$code]];
        }
        return $rows;
    }
}

if (!function_exists('dashComercialCountLeadsAsignadosVendedora')) {
    /**
     * Leads asignados a una vendedora: contact_form vinculados a calendario con idusu = vendedora
     * y fecha de entrada del lead dentro del periodo.
     */
    function dashComercialCountLeadsAsignadosVendedora($conn, $idVendedora, $startDate, $endDate)
    {
        $idVendedora = intval($idVendedora);
        if ($idVendedora <= 0) {
            return 0;
        }

        $dateCond = dashComercialBuildDateCondition($conn, $startDate, $endDate, 'COALESCE(cf.created_time, cf.submission_date)');

        $sql = "SELECT COUNT(DISTINCT cf.id) AS total
                FROM contact_form cf
                INNER JOIN calendario c ON c.idclie = cf.id
                WHERE c.idusu = $idVendedora
                  AND COALESCE(c.eliminado, 0) = 0
                  AND LOWER(COALESCE(cf.tabla_origen, '')) NOT IN ('wedding_planners','wedding_planner')
                  AND $dateCond";

        $res = $conn->query($sql);
        if ($res && ($row = $res->fetch_assoc())) {
            return intval($row['total'] ?? 0);
        }
        return 0;
    }
}

if (!function_exists('dashComercialBuildKpi')) {
    function dashComercialBuildKpi($numerador, $denominador, $labelNumerador, $labelDenominador)
    {
        $numerador = (int) $numerador;
        $denominador = (int) $denominador;
        $sinBase = ($denominador <= 0);

        return [
            'numerador'         => $numerador,
            'denominador'       => $denominador,
            'porcentaje'        => $sinBase ? 'N/A' : dashComercialFormatPct($numerador, $denominador),
            'label_numerador'   => $labelNumerador,
            'label_denominador' => $labelDenominador,
            'detalle'           => number_format($numerador) . ' / ' . number_format($denominador),
            'sin_base'          => $sinBase,
        ];
    }
}

if (!function_exists('dashComercialGetVendedoras')) {
    function dashComercialGetVendedoras($conn)
    {
        $list = [];
        $res = $conn->query("SELECT id, nombre, apePat, apeMat FROM usuarios WHERE tipoUsu = 1 ORDER BY nombre ASC, apePat ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = intval($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $nombre = trim(($row['nombre'] ?? '') . ' ' . ($row['apePat'] ?? ''));
                $list[] = [
                    'id'     => $id,
                    'nombre' => $nombre !== '' ? $nombre : ('Vendedora #' . $id),
                ];
            }
        }
        return $list;
    }
}

if (!function_exists('dashComercialComputeMetrics')) {
    function dashComercialComputeMetrics($conn, $startDate, $endDate)
    {
        ensureCalendarioEstatusHistorialTable($conn);

        $totalLeads     = dashComercialCountTotalLeads($conn, $startDate, $endDate);
        $totalAgendas   = dashComercialCountAgendas($conn, $startDate, $endDate);
        $totalAtendidos = dashComercialCountAtendidos($conn, $startDate, $endDate);
        $totalClientes  = dashComercialCountClientes($conn, $startDate, $endDate);

        $global = [
            'calificacion' => dashComercialBuildKpi($totalAgendas, $totalLeads, 'Agendas', 'Leads'),
            'atencion'     => dashComercialBuildKpi($totalAtendidos, $totalAgendas, 'Atendidos', 'Agendas'),
            'cierre'       => dashComercialBuildKpi($totalClientes, $totalAtendidos, 'Clientes cerrados', 'Atendidos'),
        ];

        $vendedoras = [];
        foreach (dashComercialGetVendedoras($conn) as $v) {
            $vid = $v['id'];
            $leadsV    = dashComercialCountLeadsAsignadosVendedora($conn, $vid, $startDate, $endDate);
            $agendasV  = dashComercialCountAgendas($conn, $startDate, $endDate, $vid);
            $atendV    = dashComercialCountAtendidos($conn, $startDate, $endDate, $vid);
            $clientesV = dashComercialCountClientes($conn, $startDate, $endDate, $vid);

            $vendedoras[] = [
                'id'     => $vid,
                'nombre' => $v['nombre'],
                'calificacion' => dashComercialBuildKpi($agendasV, $leadsV, 'Agendas', 'Leads asignados'),
                'atencion'     => dashComercialBuildKpi($atendV, $agendasV, 'Atendidos', 'Agendas'),
                'cierre'       => dashComercialBuildKpi($clientesV, $atendV, 'Clientes cerrados', 'Atendidos'),
                'tiene_actividad' => ($leadsV + $agendasV + $atendV + $clientesV) > 0,
            ];
        }

        return [
            'global'     => $global,
            'vendedoras' => $vendedoras,
            'totals'     => [
                'leads'     => $totalLeads,
                'agendas'   => $totalAgendas,
                'atendidos' => $totalAtendidos,
                'clientes'  => $totalClientes,
            ],
            'historial_breakdown' => dashComercialHistorialBreakdown($conn, $startDate, $endDate),
        ];
    }
}

if (!function_exists('dashComercialBuildVendorMap')) {
    function dashComercialBuildVendorMap($conn)
    {
        $map = [];
        foreach (dashComercialGetVendedoras($conn) as $v) {
            $map[(int) $v['id']] = $v['nombre'];
        }
        return $map;
    }
}

if (!function_exists('dashComercialFormatDisplayDate')) {
    function dashComercialFormatDisplayDate($raw)
    {
        if ($raw === null || $raw === '') {
            return '—';
        }
        $ts = strtotime((string) $raw);
        if ($ts === false || $ts <= 0) {
            return '—';
        }
        return date('d/m/Y H:i', $ts);
    }
}

if (!function_exists('dashComercialResolveLeadName')) {
    function dashComercialResolveLeadName(array $row)
    {
        foreach (['names', 'full_name', 'name'] as $key) {
            $val = trim((string) ($row[$key] ?? ''));
            if ($val !== '') {
                return $val;
            }
        }
        return 'Sin nombre';
    }
}

if (!function_exists('dashComercialResolveLeadPhone')) {
    function dashComercialResolveLeadPhone(array $row)
    {
        foreach (['phone', 'telephone', 'celular', 'telefono', 'contacto_celular'] as $key) {
            $val = trim((string) ($row[$key] ?? ''));
            if ($val !== '') {
                return $val;
            }
        }
        return '—';
    }
}

if (!function_exists('dashComercialResolveLeadEmail')) {
    function dashComercialResolveLeadEmail(array $row)
    {
        foreach (['email', 'email_address'] as $key) {
            $val = trim((string) ($row[$key] ?? ''));
            if ($val !== '') {
                return $val;
            }
        }
        return '—';
    }
}

if (!function_exists('dashComercialFetchHistorialRecords')) {
    /**
     * Registros distintos por cita que alcanzaron un estatus en el periodo.
     */
    function dashComercialFetchHistorialRecords($conn, $estatusCode, $startDate, $endDate, $idVendedora = null)
    {
        ensureCalendarioEstatusHistorialTable($conn);

        $estatusCode = (int) $estatusCode;
        $dateCond = dashComercialBuildDateCondition($conn, $startDate, $endDate, 'h.fecha_cambio');
        $vendorCond = '1=1';
        if ($idVendedora !== null) {
            $vendorCond = 'c.idusu = ' . (int) $idVendedora;
        }

        $vendorMap = dashComercialBuildVendorMap($conn);
        $labels = [0 => 'Agendado', 1 => 'Atendido', 2 => 'Fantasma', 3 => 'Muerto', 4 => 'Cliente'];
        $labelKeys = [0 => 'agendado', 1 => 'atendido', 2 => 'fantasma', 3 => 'muerto', 4 => 'cliente'];

        $sql = "SELECT
                    h.id_calendario,
                    MAX(h.fecha_cambio) AS fecha_relevante,
                    cf.id AS cf_id,
                    cf.names,
                    cf.email_address,
                    cf.telephone,
                    cf.contacto_celular,
                    cf.tabla_origen,
                    c.idusu,
                    c.fecha AS cita_fecha,
                    c.hora AS cita_hora
                FROM calendario_estatus_historial h
                INNER JOIN calendario c ON c.id = h.id_calendario
                INNER JOIN contact_form cf ON cf.id = c.idclie
                WHERE CAST(h.estatus AS SIGNED) = $estatusCode
                  AND LOWER(COALESCE(cf.tabla_origen, '')) NOT IN ('wedding_planners','wedding_planner')
                  AND COALESCE(c.eliminado, 0) = 0
                  AND $dateCond
                  AND $vendorCond
                GROUP BY h.id_calendario, cf.id, cf.names, cf.email_address, cf.telephone, cf.contacto_celular, cf.tabla_origen, c.idusu, c.fecha, c.hora
                ORDER BY fecha_relevante DESC";

        $rows = [];
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $idusu = (int) ($row['idusu'] ?? 0);
                $rows[] = [
                    'id'            => (int) ($row['cf_id'] ?? 0),
                    'nombre'        => dashComercialResolveLeadName($row),
                    'email'         => dashComercialResolveLeadEmail($row),
                    'telefono'      => dashComercialResolveLeadPhone($row),
                    'fecha'         => dashComercialFormatDisplayDate($row['fecha_relevante'] ?? ''),
                    'fecha_raw'     => $row['fecha_relevante'] ?? '',
                    'vendedora'     => $vendorMap[$idusu] ?? ($idusu > 0 ? ('Vendedora #' . $idusu) : '—'),
                    'origen'        => trim((string) ($row['tabla_origen'] ?? '')) ?: '—',
                    'estatus'       => $labels[$estatusCode] ?? (string) $estatusCode,
                    'estatus_key'   => $labelKeys[$estatusCode] ?? normalizeLeadStatusKey($labels[$estatusCode] ?? ''),
                    'cita'          => trim((string) ($row['cita_fecha'] ?? '')) !== ''
                        ? dashComercialFormatDisplayDate(trim($row['cita_fecha'] . ' ' . ($row['cita_hora'] ?? '')))
                        : '—',
                ];
            }
        }
        return $rows;
    }
}

if (!function_exists('dashComercialFetchAgendaRecords')) {
    function dashComercialFetchAgendaRecords($conn, $startDate, $endDate, $idVendedora = null)
    {
        return dashComercialCohortToDisplayRows(
            $conn,
            dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate, $idVendedora)
        );
    }
}

if (!function_exists('dashComercialFetchAtendidoRecords')) {
    function dashComercialFetchAtendidoRecords($conn, $startDate, $endDate, $idVendedora = null)
    {
        return dashComercialFetchAgendaRecords($conn, $startDate, $endDate, $idVendedora);
    }
}

if (!function_exists('dashComercialFetchClienteRecords')) {
    function dashComercialFetchClienteRecords($conn, $startDate, $endDate, $idVendedora = null)
    {
        $cohort = dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate, $idVendedora);
        $clientes = [];
        foreach ($cohort as $cid => $item) {
            $estatus = mb_strtolower(trim((string) ($item['estatus'] ?? '')), 'UTF-8');
            if ($estatus === 'cliente' || (int) ($item['cf']['cliente'] ?? 0) === 1) {
                $clientes[$cid] = $item;
            }
        }
        return dashComercialCohortToDisplayRows($conn, $clientes);
    }
}

if (!function_exists('dashComercialFetchLeadRecords')) {
    function dashComercialFetchLeadRecords($conn, $startDate, $endDate)
    {
        $rows = [];
        $resTablas = $conn->query("SELECT nombre FROM tablas_leads WHERE tipo != 2 OR nombre = 'wp_citas_leads' ORDER BY nombre");
        if (!$resTablas) {
            return $rows;
        }

        while ($rowTabla = $resTablas->fetch_assoc()) {
            $tableName = $rowTabla['nombre'] ?? '';
            if ($tableName === '') {
                continue;
            }
            $safeTable = $conn->real_escape_string($tableName);
            $check = $conn->query("SHOW TABLES LIKE '$safeTable'");
            if (!$check || $check->num_rows === 0) {
                continue;
            }

            $colsRes = $conn->query("SHOW COLUMNS FROM `$tableName`");
            $columns = [];
            if ($colsRes) {
                while ($col = $colsRes->fetch_assoc()) {
                    $columns[] = $col['Field'];
                }
            }
            if (!in_array('created_time', $columns, true)) {
                continue;
            }

            $selectCols = ['id', 'created_time'];
            foreach (['full_name', 'names', 'name', 'email', 'email_address', 'phone', 'telephone', 'celular', 'telefono', 'contacto_celular', 'campaign_name'] as $col) {
                if (in_array($col, $columns, true)) {
                    $selectCols[] = $col;
                }
            }
            $selectCols = array_unique($selectCols);

            $where = ['1=1'];
            if (in_array('descartado', $columns, true)) {
                $where[] = '(descartado = 0 OR descartado IS NULL)';
            }
            if ($startDate !== '' || $endDate !== '') {
                $dateExtract = "CASE
                    WHEN created_time LIKE '%T%' THEN DATE(STR_TO_DATE(SUBSTRING(created_time, 1, 19), '%Y-%m-%dT%H:%i:%s'))
                    ELSE DATE(created_time)
                END";
                if ($startDate !== '') {
                    $sd = date('Y-m-d', strtotime($startDate));
                    $where[] = "$dateExtract >= '" . $conn->real_escape_string($sd) . "'";
                }
                if ($endDate !== '') {
                    $ed = date('Y-m-d', strtotime($endDate));
                    $where[] = "$dateExtract <= '" . $conn->real_escape_string($ed) . "'";
                }
            }

            $sql = 'SELECT ' . implode(', ', array_map(function ($c) {
                return '`' . $c . '`';
            }, $selectCols)) . " FROM `$tableName` WHERE " . implode(' AND ', $where) . ' ORDER BY created_time DESC';

            $res = $conn->query($sql);
            if (!$res) {
                continue;
            }

            while ($lead = $res->fetch_assoc()) {
                $createdTime = $lead['created_time'] ?? '';
                $rows[] = [
                    'id'        => (int) ($lead['id'] ?? 0),
                    'nombre'    => dashComercialResolveLeadName($lead),
                    'email'     => dashComercialResolveLeadEmail($lead),
                    'telefono'  => dashComercialResolveLeadPhone($lead),
                    'fecha'     => dashComercialFormatDisplayDate($createdTime),
                    'fecha_raw' => $createdTime,
                    'vendedora' => '—',
                    'origen'    => $tableName,
                    'estatus'   => 'Lead',
                    'estatus_key' => 'lead',
                    'cita'      => '—',
                ];
            }
        }

        usort($rows, function ($a, $b) {
            return strcmp((string) ($b['fecha_raw'] ?? ''), (string) ($a['fecha_raw'] ?? ''));
        });

        return $rows;
    }
}

if (!function_exists('dashComercialFetchRecordsByType')) {
    function dashComercialFetchRecordsByType($conn, $type, $startDate, $endDate, $idVendedora = null)
    {
        switch ($type) {
            case 'clientes':
                return dashComercialFetchClienteRecords($conn, $startDate, $endDate, $idVendedora);
            case 'atendidos':
                return dashComercialFetchAtendidoRecords($conn, $startDate, $endDate, $idVendedora);
            case 'agendas':
                return dashComercialFetchAgendaRecords($conn, $startDate, $endDate, $idVendedora);
            case 'leads':
                return dashComercialFetchLeadRecords($conn, $startDate, $endDate);
            default:
                return [];
        }
    }
}
