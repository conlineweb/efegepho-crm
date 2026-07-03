<?php
/**
 * Lista de leads para la vista de trazabilidad (misma base que consulta_leads.php).
 */

require_once __DIR__ . '/dashboard_comercial_helper.php';

if (!function_exists('pltraceIsB1B2CampaignName')) {
    function pltraceIsB1B2CampaignName($campaignName)
    {
        if ($campaignName === null) {
            return false;
        }
        $v = strtolower(trim((string) $campaignName));
        if ($v === '') {
            return false;
        }
        $v = preg_replace('/\s+/', ' ', $v);
        return in_array($v, ['b1', 'b1 (usa)', 'b1 usa', 'b2', 'b2 (mx)', 'b2 mx', 'b3', 'b3 (mex 2)', 'b3 mex 2', 'b4', 'b4 (latam)', 'b4 latam'], true);
    }
}

if (!function_exists('pltraceMapTipoClienteValue')) {
    function pltraceMapTipoClienteValue($raw)
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return '';
        }
        if ($value === '1' || strcasecmp($value, 'Wedding Planner') === 0) {
            return 'Wedding Planner';
        }
        if ($value === '0' || strcasecmp($value, 'Cliente Final') === 0) {
            return 'Cliente Final';
        }
        return '';
    }
}

if (!function_exists('pltraceIsWeddingPlannerLead')) {
    /**
     * Identifica wedding planners para trazabilidad.
     * Incluye citas espejadas en wp_citas_leads (agendar desde planner_profile.php).
     */
    function pltraceIsWeddingPlannerLead($tablaOrigen, $leadRow, $cfRow = null)
    {
        $tabla = strtolower(trim((string) $tablaOrigen));

        if (in_array($tabla, ['wedding_planners', 'wp_citas_leads', 'eventos_wp', 'wp_eventos_afianzados'], true)) {
            return true;
        }

        if ((int) ($leadRow['id_wedding_planner'] ?? 0) > 0) {
            return true;
        }

        $cfTipo = is_array($cfRow) ? ($cfRow['tipo_cliente'] ?? '') : '';
        if (pltraceMapTipoClienteValue($cfTipo) === 'Wedding Planner') {
            return true;
        }
        if (pltraceMapTipoClienteValue($leadRow['tipo_cliente'] ?? '') === 'Wedding Planner') {
            return true;
        }

        $how = '';
        if (is_array($cfRow) && trim((string) ($cfRow['how_did_you_meet'] ?? '')) !== '') {
            $how = trim((string) $cfRow['how_did_you_meet']);
        }
        if ($how === '') {
            $how = trim((string) ($leadRow['how_did_you_meet'] ?? ''));
        }
        if ($how === '1') {
            return true;
        }

        $campaign = strtolower(trim((string) ($leadRow['campaign_name'] ?? '')));
        $platform = strtolower(trim((string) ($leadRow['platform'] ?? '')));
        if ($campaign === 'wedding planner' || $platform === 'wedding planner') {
            return true;
        }

        return false;
    }
}

if (!function_exists('pltraceIsClienteLead')) {
    /**
     * Misma regla que clientes.php: contact_form.cliente = 1, excluyendo wedding_planners.
     */
    function pltraceIsClienteLead($tablaOrigen, $cfRow = null)
    {
        if (!is_array($cfRow) || (int) ($cfRow['cliente'] ?? 0) !== 1) {
            return false;
        }

        $tabla = strtolower(trim((string) $tablaOrigen));
        return !in_array($tabla, ['wedding_planners', 'wedding_planner'], true);
    }
}

if (!function_exists('pltraceGetTablaTipoMap')) {
    function pltraceGetTablaTipoMap($conn)
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];
        $res = $conn->query('SELECT nombre, tipo FROM tablas_leads');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $name = trim((string) ($row['nombre'] ?? ''));
                if ($name !== '') {
                    $cache[$name] = (int) ($row['tipo'] ?? -1);
                }
            }
        }

        return $cache;
    }
}

if (!function_exists('pltraceClientePassesPlataformaFilter')) {
    function pltraceClientePassesPlataformaFilter(array $leadData, $tablaOrigen, $filterPlataforma, array $tablaTipoMap)
    {
        if ($filterPlataforma === '') {
            return true;
        }

        $tipoTabla = isset($tablaTipoMap[$tablaOrigen]) ? (int) $tablaTipoMap[$tablaOrigen] : -1;
        $campaignNameLower = strtolower(trim((string) ($leadData['campaign_name'] ?? '')));
        $isB1B2 = pltraceIsB1B2CampaignName($campaignNameLower);

        if ($filterPlataforma === 'organico') {
            if ($isB1B2) {
                return false;
            }
            return $tipoTabla === 0
                || $campaignNameLower === 'website'
                || $campaignNameLower === 'reg manual';
        }

        if ($filterPlataforma === 'campania') {
            return $tipoTabla === 1 || $isB1B2;
        }

        return true;
    }
}

if (!function_exists('pltraceClienteDateInRange')) {
    function pltraceClienteDateInRange($fechaCambioCliente, $startDate, $endDate)
    {
        $fechaCambioCliente = trim((string) $fechaCambioCliente);
        if ($fechaCambioCliente === '' || strpos($fechaCambioCliente, '0000-00-00') === 0) {
            return ($startDate === '' && $endDate === '');
        }

        $ts = strtotime($fechaCambioCliente);
        if ($ts === false) {
            return false;
        }

        $d = date('Y-m-d', $ts);
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

if (!function_exists('pltraceMergeClientesIntoTraceList')) {
    /**
     * Incorpora cierres de clientes.php que no entraron por created_time del lead.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    function pltraceMergeClientesIntoTraceList($conn, array $rows, $startDate, $endDate, $filterPlataforma = '')
    {
        if (!($conn instanceof mysqli)) {
            return $rows;
        }

        $existingKeys = [];
        foreach ($rows as $index => $row) {
            $key = (string) ($row['trace_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $existingKeys[$key] = $index;
        }

        $tablaTipoMap = pltraceGetTablaTipoMap($conn);
        $appointmentsByCFId = dashComercialGetAppointmentsByClient($conn);

        $vendorMap = dashComercialBuildVendorMap($conn);

        $sql = "SELECT id, tabla_origen, original_lead_id, cliente, how_did_you_meet, tipo_cliente,
                       id_vendedor_asignado, names, email_address, fecha_cambio_cliente, submission_date, created_time, campaign_name
                FROM contact_form
                WHERE cliente = 1
                  AND LOWER(COALESCE(tabla_origen, '')) NOT IN ('wedding_planners', 'wedding_planner')";
        $resCf = $conn->query($sql);
        if (!$resCf) {
            return $rows;
        }

        while ($cfRow = $resCf->fetch_assoc()) {
            $tabla = trim((string) ($cfRow['tabla_origen'] ?? ''));
            $origId = (int) ($cfRow['original_lead_id'] ?? 0);
            if ($tabla === '' || $origId <= 0) {
                continue;
            }

            if (!pltraceClienteDateInRange($cfRow['fecha_cambio_cliente'] ?? '', $startDate, $endDate)) {
                continue;
            }

            $key = $tabla . '|' . $origId;
            $cfPayload = [
                'cf_id'                 => (int) ($cfRow['id'] ?? 0),
                'cliente'               => 1,
                'how_did_you_meet'      => $cfRow['how_did_you_meet'] ?? null,
                'tipo_cliente'          => $cfRow['tipo_cliente'] ?? null,
                'id_vendedor_asignado'  => (int) ($cfRow['id_vendedor_asignado'] ?? 0),
            ];

            if (isset($existingKeys[$key])) {
                $rows[$existingKeys[$key]]['is_cliente'] = 1;
                $rows[$existingKeys[$key]]['cf_id'] = (int) ($cfRow['id'] ?? 0);
                $rows[$existingKeys[$key]]['estatus'] = 'Cliente';
                $rows[$existingKeys[$key]]['estatus_raw'] = 'cliente';
                if (empty($rows[$existingKeys[$key]]['vendedora'])) {
                    $cfIdExisting = (int) ($cfRow['id'] ?? 0);
                    $apptExisting = ($cfIdExisting > 0 && isset($appointmentsByCFId[$cfIdExisting]))
                        ? $appointmentsByCFId[$cfIdExisting]
                        : null;
                    $vendedorId = pltraceResolveLeadVendedorId($cfPayload, $apptExisting, null);
                    $rows[$existingKeys[$key]]['vendedor_id'] = $vendedorId;
                    $rows[$existingKeys[$key]]['vendedora'] = pltraceResolveLeadVendedora($vendedorId, $vendorMap);
                }
                continue;
            }

            $escapedTable = $conn->real_escape_string($tabla);
            $checkTable = $conn->query("SHOW TABLES LIKE '$escapedTable'");
            if (!$checkTable || $checkTable->num_rows === 0) {
                continue;
            }

            $leadRes = $conn->query("SELECT * FROM `$escapedTable` WHERE id = $origId LIMIT 1");
            if (!$leadRes || $leadRes->num_rows === 0) {
                continue;
            }

            $leadRow = $leadRes->fetch_assoc();
            $leadRow['tabla_origen'] = $tabla;

            if (!pltraceClientePassesPlataformaFilter($leadRow, $tabla, $filterPlataforma, $tablaTipoMap)) {
                continue;
            }

            $cfId = (int) ($cfRow['id'] ?? 0);
            $appointment = ($cfId > 0 && isset($appointmentsByCFId[$cfId])) ? $appointmentsByCFId[$cfId] : null;
            $fechaCambio = trim((string) ($cfRow['fecha_cambio_cliente'] ?? ''));
            $sortDate = $fechaCambio !== '' ? $fechaCambio : ($leadRow['created_time'] ?? '');
            $vendedorId = pltraceResolveLeadVendedorId($cfPayload, $appointment, $leadRow);

            $rows[] = [
                'trace_key'          => $key,
                'tabla_origen'       => $tabla,
                'orig_id'            => $origId,
                'cf_id'              => $cfId,
                'nombre'             => dashComercialResolveLeadName($leadRow) ?: trim((string) ($cfRow['names'] ?? '')),
                'email'              => dashComercialResolveLeadEmail($leadRow) ?: trim((string) ($cfRow['email_address'] ?? '')),
                'telefono'           => dashComercialResolveLeadPhone($leadRow),
                'estatus'            => 'Cliente',
                'estatus_raw'        => 'cliente',
                'fecha'              => dashComercialFormatDisplayDate($sortDate),
                'fecha_raw'          => $sortDate,
                'is_wedding_planner' => 0,
                'is_cliente'         => 1,
                'vendedor_id'        => $vendedorId,
                'vendedora'          => pltraceResolveLeadVendedora($vendedorId, $vendorMap),
            ];
            $existingKeys[$key] = count($rows) - 1;
        }

        usort($rows, function ($a, $b) {
            return strcmp((string) ($b['fecha_raw'] ?? ''), (string) ($a['fecha_raw'] ?? ''));
        });

        return $rows;
    }
}

if (!function_exists('pltraceResolveLeadStatus')) {
    function pltraceResolveLeadStatus($tableName, $contactFormRow, $appointmentRow, $leadRow = null)
    {
        if (strcasecmp((string) $tableName, 'wp_citas_leads') === 0) {
            foreach (['lead_status', 'estatus_cita', 'estatus'] as $key) {
                $val = trim((string) ($leadRow[$key] ?? ''));
                if ($val !== '') {
                    return mb_strtolower($val, 'UTF-8');
                }
            }
        }

        if (is_array($contactFormRow) && (int) ($contactFormRow['cliente'] ?? 0) === 1) {
            return 'cliente';
        }

        if ($appointmentRow === null || !isset($appointmentRow['estatus'])) {
            return 'lead';
        }

        if (strcasecmp((string) $tableName, 'wedding_planners') === 0 ||
            strcasecmp((string) $tableName, 'eventos_wp') === 0) {
            return 'agendado';
        }

        $rawStatus = $appointmentRow['estatus'];
        $intStatus = is_numeric($rawStatus) ? (int) $rawStatus : null;

        if ($intStatus === 1) return 'atendido';
        if ($intStatus === 3) return 'muerto';
        if ($intStatus === 0) return 'agendado';
        if ($intStatus === 2) return 'fantasma';

        return (is_string($rawStatus) && trim($rawStatus) !== '') ? trim($rawStatus) : 'agendado';
    }
}

if (!function_exists('pltraceGetLeadTables')) {
    function pltraceGetLeadTables($conn, $filterPlataforma = '')
    {
        $tablas = [];
        if ($filterPlataforma === 'organico') {
            $sql = "SELECT nombre FROM tablas_leads WHERE tipo = 0 ORDER BY nombre";
        } elseif ($filterPlataforma === 'campania') {
            $sql = "SELECT nombre FROM tablas_leads WHERE tipo = 1 OR nombre = 'organic_leads' ORDER BY nombre";
        } else {
            $sql = "SELECT nombre FROM tablas_leads WHERE tipo != 2 OR nombre = 'wp_citas_leads' ORDER BY nombre";
        }

        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $name = trim((string) ($row['nombre'] ?? ''));
                if ($name !== '') {
                    $tablas[] = $name;
                }
            }
        }
        return $tablas;
    }
}

if (!function_exists('pltraceResolveLeadVendedorId')) {
    function pltraceResolveLeadVendedorId($cf, $appointment, $leadRow = null)
    {
        if (is_array($cf) && (int) ($cf['id_vendedor_asignado'] ?? 0) > 0) {
            return (int) $cf['id_vendedor_asignado'];
        }
        if (is_array($leadRow)) {
            foreach (['id_vendedor_asignado', 'usuario_asignado'] as $key) {
                if ((int) ($leadRow[$key] ?? 0) > 0) {
                    return (int) $leadRow[$key];
                }
            }
        }
        if (is_array($appointment)) {
            foreach (['idusu', 'id_vendedor_asignado'] as $key) {
                if ((int) ($appointment[$key] ?? 0) > 0) {
                    return (int) $appointment[$key];
                }
            }
        }
        return 0;
    }
}

if (!function_exists('pltraceResolveLeadVendedora')) {
    function pltraceResolveLeadVendedora($vendedorId, array $vendorMap, $conn = null)
    {
        $vendedorId = (int) $vendedorId;
        if ($vendedorId <= 0) {
            return '';
        }
        if ($conn && function_exists('dashComercialResolveUsuarioDisplayNameById')) {
            return dashComercialResolveUsuarioDisplayNameById($conn, $vendedorId, $vendorMap);
        }

        return $vendorMap[$vendedorId] ?? ('Vendedora #' . $vendedorId);
    }
}

if (!function_exists('pltraceVendedorInitial')) {
    function pltraceVendedorInitial($nombre)
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '') {
            return 'S';
        }
        return mb_strtoupper(mb_substr($nombre, 0, 1, 'UTF-8'), 'UTF-8');
    }
}

if (!function_exists('pltraceVendedorColor')) {
    function pltraceVendedorColor($nombre)
    {
        $colors = [
            'B' => '#3B82F6',
            'A' => '#10B981',
            'E' => '#8B5CF6',
            'L' => '#F59E0B',
            'M' => '#EC4899',
            'C' => '#06B6D4',
            'D' => '#EF4444',
        ];
        return $colors[pltraceVendedorInitial($nombre)] ?? '#64748B';
    }
}

if (!function_exists('pltraceFetchConsultaLeadsList')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function pltraceFetchConsultaLeadsList($conn, $startDate, $endDate, $filterPlataforma = '')
    {
        $b1b2Values = [
            'b1', 'b1 (usa)', 'b1 usa', 'b2', 'b2 (mx)', 'b2 mx',
            'b3', 'b3 (mex 2)', 'b3 mex 2', 'b4', 'b4 (latam)', 'b4 latam',
        ];

        $allLeads = [];
        foreach (pltraceGetLeadTables($conn, $filterPlataforma) as $tableName) {
            $safeTable = $conn->real_escape_string($tableName);
            $checkTable = $conn->query("SHOW TABLES LIKE '$safeTable'");
            if (!$checkTable || $checkTable->num_rows === 0) {
                continue;
            }

            $columns = [];
            $columnsResult = $conn->query("SHOW COLUMNS FROM `$tableName`");
            if ($columnsResult) {
                while ($col = $columnsResult->fetch_assoc()) {
                    $columns[] = $col['Field'];
                }
            }
            if (!in_array('created_time', $columns, true)) {
                continue;
            }

            $whereParts = [];
            if (in_array('descartado', $columns, true)) {
                $whereParts[] = '(descartado = 0 OR descartado IS NULL)';
            }

            if (($startDate !== '' || $endDate !== '') && in_array('created_time', $columns, true)) {
                $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
                $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
                $dateExtract = "CASE
                    WHEN created_time LIKE '%T%' THEN DATE(STR_TO_DATE(SUBSTRING(created_time, 1, 19), '%Y-%m-%dT%H:%i:%s'))
                    ELSE DATE(created_time)
                END";
                if ($sd) {
                    $whereParts[] = $dateExtract . " >= '" . $conn->real_escape_string($sd) . "'";
                }
                if ($ed) {
                    $whereParts[] = $dateExtract . " <= '" . $conn->real_escape_string($ed) . "'";
                }
            }

            if ($tableName === 'organic_leads' && in_array('campaign_name', $columns, true)) {
                $campaignCol = 'LOWER(TRIM(campaign_name))';
                $inList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $b1b2Values)) . "'";
                if ($filterPlataforma === 'organico') {
                    $whereParts[] = "($campaignCol IS NULL OR $campaignCol = '' OR $campaignCol NOT IN ($inList))";
                } elseif ($filterPlataforma === 'campania') {
                    $whereParts[] = "$campaignCol IN ($inList)";
                }
            }

            $whereSql = !empty($whereParts) ? implode(' AND ', $whereParts) : '1=1';
            $sqlLeads = "SELECT *, '$tableName' AS tabla_origen FROM `$tableName` WHERE $whereSql ORDER BY created_time DESC";
            $resultLeads = $conn->query($sqlLeads);
            if ($resultLeads) {
                while ($lead = $resultLeads->fetch_assoc()) {
                    $allLeads[] = $lead;
                }
            }
        }

        $filteredLeads = [];
        foreach ($allLeads as $lead) {
            $campaignName = $lead['campaign_name'] ?? '';
            $isB1B2 = pltraceIsB1B2CampaignName($campaignName);
            $tablaOrigen = $lead['tabla_origen'] ?? '';

            if ($filterPlataforma === 'organico' && $isB1B2) {
                continue;
            }
            if ($filterPlataforma === 'campania' && $tablaOrigen === 'organic_leads' && !$isB1B2) {
                continue;
            }
            $filteredLeads[] = $lead;
        }

        $leadIdsByTable = [];
        foreach ($filteredLeads as $lead) {
            $t = $lead['tabla_origen'] ?? '';
            $id = (int) ($lead['id'] ?? 0);
            if ($t === '' || $id <= 0) {
                continue;
            }
            $leadIdsByTable[$t][] = $id;
        }

        $contactFormByLead = [];
        $cfIds = [];
        foreach ($leadIdsByTable as $t => $ids) {
            $safeTable = $conn->real_escape_string($t);
            $idsList = implode(',', array_map('intval', array_unique($ids)));
            if ($idsList === '') {
                continue;
            }
            $sql = "SELECT id, original_lead_id, cliente, how_did_you_meet, tipo_cliente, id_vendedor_asignado
                    FROM contact_form
                    WHERE LOWER(tabla_origen) = LOWER('$safeTable')
                      AND original_lead_id IN ($idsList)";
            $resCf = $conn->query($sql);
            if ($resCf) {
                while ($row = $resCf->fetch_assoc()) {
                    $key = $t . '|' . (int) $row['original_lead_id'];
                    $contactFormByLead[$key] = [
                        'cf_id'                => (int) $row['id'],
                        'cliente'              => (int) ($row['cliente'] ?? 0),
                        'how_did_you_meet'     => $row['how_did_you_meet'] ?? null,
                        'tipo_cliente'         => $row['tipo_cliente'] ?? null,
                        'id_vendedor_asignado' => (int) ($row['id_vendedor_asignado'] ?? 0),
                    ];
                    $cfIds[] = (int) $row['id'];
                }
            }
        }

        $appointmentsByCFId = dashComercialGetAppointmentsByClient($conn);
        // Re-key by cf id only for leads we care about
        $apptMap = [];
        foreach ($cfIds as $cfId) {
            if (isset($appointmentsByCFId[$cfId])) {
                $apptMap[$cfId] = $appointmentsByCFId[$cfId];
            }
        }

        $vendorMap = dashComercialBuildVendorMap($conn);
        $rows = [];
        foreach ($filteredLeads as $lead) {
            $tabla = (string) ($lead['tabla_origen'] ?? '');
            $origId = (int) ($lead['id'] ?? 0);
            if ($tabla === '' || $origId <= 0) {
                continue;
            }

            $key = $tabla . '|' . $origId;
            $cf = $contactFormByLead[$key] ?? null;
            $cfId = $cf ? (int) $cf['cf_id'] : 0;
            $appointment = ($cfId > 0 && isset($apptMap[$cfId])) ? $apptMap[$cfId] : null;
            $status = pltraceResolveLeadStatus($tabla, $cf, $appointment, $lead);
            $createdTime = $lead['created_time'] ?? '';
            $isWeddingPlanner = pltraceIsWeddingPlannerLead($tabla, $lead, $cf);
            $isCliente = pltraceIsClienteLead($tabla, $cf);
            $vendedorId = pltraceResolveLeadVendedorId($cf, $appointment, $lead);

            $rows[] = [
                'trace_key'          => $key,
                'tabla_origen'       => $tabla,
                'orig_id'            => $origId,
                'cf_id'              => $cfId,
                'nombre'             => dashComercialResolveLeadName($lead),
                'email'              => dashComercialResolveLeadEmail($lead),
                'telefono'           => dashComercialResolveLeadPhone($lead),
                'estatus'            => ucfirst($status),
                'estatus_raw'        => $status,
                'fecha'              => dashComercialFormatDisplayDate($createdTime),
                'fecha_raw'          => $createdTime,
                'is_wedding_planner' => $isWeddingPlanner ? 1 : 0,
                'is_cliente'         => $isCliente ? 1 : 0,
                'vendedor_id'        => $vendedorId,
                'vendedora'          => pltraceResolveLeadVendedora($vendedorId, $vendorMap),
            ];
        }

        $rows = pltraceMergeClientesIntoTraceList($conn, $rows, $startDate, $endDate, $filterPlataforma);

        usort($rows, function ($a, $b) {
            return strcmp((string) ($b['fecha_raw'] ?? ''), (string) ($a['fecha_raw'] ?? ''));
        });

        return $rows;
    }
}

if (!function_exists('pltraceFindContactFormId')) {
    function pltraceFindContactFormId($conn, $tablaOrigen, $origId)
    {
        $tablaOrigen = trim((string) $tablaOrigen);
        $origId = (int) $origId;
        if ($tablaOrigen === '' || $origId <= 0) {
            return 0;
        }
        $safeTable = $conn->real_escape_string($tablaOrigen);
        $res = $conn->query(
            "SELECT id FROM contact_form
             WHERE LOWER(tabla_origen) = LOWER('$safeTable')
               AND original_lead_id = $origId
             ORDER BY id DESC
             LIMIT 1"
        );
        if ($res && ($row = $res->fetch_assoc())) {
            return (int) ($row['id'] ?? 0);
        }
        return 0;
    }
}

if (!function_exists('pltraceNormalizeKey')) {
    function pltraceNormalizeKey($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $map = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'ñ' => 'n', 'Ñ' => 'n'];
        return strtolower(strtr($value, $map));
    }
}

if (!function_exists('pltraceTypeClass')) {
    function pltraceTypeClass($type)
    {
        $k = pltraceNormalizeKey($type);
        if ($k === 'llamada') {
            return 'type-llamada';
        }
        if ($k === 'whatsapp') {
            return 'type-whatsapp';
        }
        if ($k === 'email') {
            return 'type-email';
        }
        if ($k === 'reunion') {
            return 'type-reunion';
        }
        if ($k === 'nota interna') {
            return 'type-nota';
        }
        if ($k === 'sin respuesta') {
            return 'type-sinrespuesta';
        }
        return 'type-default';
    }
}

if (!function_exists('pltraceTypeIcon')) {
    function pltraceTypeIcon($type)
    {
        $k = pltraceNormalizeKey($type);
        if ($k === 'llamada') {
            return '📞';
        }
        if ($k === 'whatsapp') {
            return '💬';
        }
        if ($k === 'email') {
            return '✉️';
        }
        if ($k === 'reunion') {
            return '🤝';
        }
        if ($k === 'nota interna') {
            return '📝';
        }
        if ($k === 'sin respuesta') {
            return '…';
        }
        return '💭';
    }
}

if (!function_exists('pltraceOutcomeClass')) {
    function pltraceOutcomeClass($outcome)
    {
        $k = pltraceNormalizeKey($outcome);
        if ($k === 'positivo') {
            return 'outcome-positivo';
        }
        if ($k === 'neutral') {
            return 'outcome-neutral';
        }
        if ($k === 'negativo') {
            return 'outcome-negativo';
        }
        if ($k === 'listo para cerrar') {
            return 'outcome-cerrar';
        }
        if ($k === 'requiere seguimiento') {
            return 'outcome-seguimiento';
        }
        if ($k === 'sin respuesta') {
            return 'outcome-sinrespuesta';
        }
        return 'outcome-default';
    }
}

if (!function_exists('pltraceOutcomeIcon')) {
    function pltraceOutcomeIcon($outcome)
    {
        $k = pltraceNormalizeKey($outcome);
        if ($k === 'positivo') {
            return '✅';
        }
        if ($k === 'neutral') {
            return '⚪';
        }
        if ($k === 'negativo') {
            return '❌';
        }
        if ($k === 'listo para cerrar') {
            return '🔥';
        }
        if ($k === 'requiere seguimiento') {
            return '⏳';
        }
        if ($k === 'sin respuesta') {
            return '…';
        }
        return '•';
    }
}

if (!function_exists('pltraceDueLabel')) {
    function pltraceDueLabel($timestamp, $today, $tomorrow)
    {
        $d = date('Y-m-d', $timestamp);
        if ($d === $today) {
            return 'vence hoy';
        }
        if ($d === $tomorrow) {
            return 'mañana ' . date('g:i a', $timestamp);
        }
        $days = (int) floor((strtotime($d) - strtotime($today)) / 86400);
        if ($days > 1 && $days <= 7) {
            return $days . ' días';
        }
        return date('j M', $timestamp);
    }
}

if (!function_exists('pltraceEnsureInteractionCompletedColumn')) {
    function pltraceEnsureInteractionCompletedColumn($conn)
    {
        $liColCheck = $conn->query("SHOW COLUMNS FROM `lead_interactions` LIKE 'next_action_completed'");
        if ($liColCheck && $liColCheck->num_rows === 0) {
            $conn->query("ALTER TABLE `lead_interactions` ADD COLUMN `next_action_completed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `next_action_date`");
        }
    }
}

if (!function_exists('pltraceFetchLeadPendingBadgesMap')) {
    /**
     * Próxima acción pendiente por lead (interacción o cita), alineado con index.php.
     *
     * @return array<string, array<string, mixed>>
     */
    function pltraceFetchLeadPendingBadgesMap($conn, array $traceLeads)
    {
        if (empty($traceLeads)) {
            return [];
        }

        pltraceEnsureInteractionCompletedColumn($conn);

        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $nowTs = time();

        $idsByTable = [];
        $cfIdByKey = [];
        foreach ($traceLeads as $lead) {
            $key = (string) ($lead['trace_key'] ?? '');
            $tabla = (string) ($lead['tabla_origen'] ?? '');
            $origId = (int) ($lead['orig_id'] ?? 0);
            $cfId = (int) ($lead['cf_id'] ?? 0);
            if ($key === '' || $tabla === '' || $origId <= 0) {
                continue;
            }
            $idsByTable[$tabla][] = $origId;
            if ($cfId > 0) {
                $cfIdByKey[$key] = $cfId;
            }
        }

        $interactionByKey = [];
        foreach ($idsByTable as $tableName => $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (empty($ids)) {
                continue;
            }
            $idsList = implode(',', $ids);
            $safeTable = $conn->real_escape_string($tableName);
            $sql = "SELECT id, tabla_origen, lead_id, interaction_type, outcome, next_action_date, next_action
                    FROM lead_interactions
                    WHERE LOWER(tabla_origen) = LOWER('$safeTable')
                      AND lead_id IN ($idsList)
                      AND next_action_date IS NOT NULL
                      AND (next_action_completed IS NULL OR next_action_completed = 0)
                    ORDER BY next_action_date ASC, id ASC";
            $res = $conn->query($sql);
            if (!$res) {
                continue;
            }
            while ($row = $res->fetch_assoc()) {
                $key = trim((string) ($row['tabla_origen'] ?? '')) . '|' . (int) ($row['lead_id'] ?? 0);
                if (isset($interactionByKey[$key])) {
                    continue;
                }
                $dateOnly = trim((string) ($row['next_action_date'] ?? ''));
                $ts = $dateOnly !== '' ? strtotime($dateOnly) : false;
                if ($ts === false) {
                    continue;
                }
                $interactionByKey[$key] = [
                    'source'           => 'interaccion',
                    'interaction_type' => trim((string) ($row['interaction_type'] ?? '')),
                    'outcome'          => trim((string) ($row['outcome'] ?? '')),
                    'timestamp'        => $ts,
                    'date_only'        => $dateOnly,
                    'is_overdue'       => ($dateOnly < $today),
                ];
            }
        }

        $citaByCfId = [];
        $cfIds = array_values(array_unique(array_filter(array_values($cfIdByKey))));
        if (!empty($cfIds)) {
            $idsList = implode(',', array_map('intval', $cfIds));
            $sql = "SELECT idclie, fecha, hora
                    FROM calendario
                    WHERE idclie IN ($idsList)
                    ORDER BY fecha ASC, hora ASC, id ASC";
            $res = $conn->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $cfId = (int) ($row['idclie'] ?? 0);
                    if ($cfId <= 0 || isset($citaByCfId[$cfId])) {
                        continue;
                    }
                    $fecha = trim((string) ($row['fecha'] ?? ''));
                    if ($fecha === '') {
                        continue;
                    }
                    $hora = trim((string) ($row['hora'] ?? '00:00:00'));
                    $ts = strtotime($fecha . ' ' . ($hora !== '' ? $hora : '00:00:00'));
                    if ($ts === false || $ts < $nowTs) {
                        continue;
                    }
                    $citaByCfId[$cfId] = [
                        'source'    => 'calendario',
                        'timestamp' => $ts,
                        'date_only' => date('Y-m-d', $ts),
                        'is_overdue'=> false,
                    ];
                }
            }
        }

        $badgesMap = [];
        foreach ($traceLeads as $lead) {
            $key = (string) ($lead['trace_key'] ?? '');
            if ($key === '') {
                continue;
            }

            $candidates = [];
            if (isset($interactionByKey[$key])) {
                $candidates[] = $interactionByKey[$key];
            }
            $cfId = (int) ($cfIdByKey[$key] ?? 0);
            if ($cfId > 0 && isset($citaByCfId[$cfId])) {
                $candidates[] = $citaByCfId[$cfId];
            }
            if (empty($candidates)) {
                continue;
            }

            usort($candidates, function ($a, $b) {
                return ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0);
            });
            $pick = $candidates[0];
            $pick['due_label'] = $pick['is_overdue']
                ? 'Vencida'
                : pltraceDueLabel($pick['timestamp'], $today, $tomorrow);
            $badgesMap[$key] = $pick;
        }

        return $badgesMap;
    }
}

if (!function_exists('pltraceFormatProfileDisplay')) {
    function pltraceFormatProfileDisplay($value)
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : '—';
    }
}

if (!function_exists('pltraceFormatHowLongKnownUsLabel')) {
    function pltraceFormatHowLongKnownUsLabel($raw)
    {
        $value = trim((string) $raw);
        $map = [
            'Less than 6 months'           => 'Menos de 6 meses',
            'More than 6 months'           => 'Más de 6 meses',
            'Not asked'                    => 'Todavía no sabemos',
            'Less than 3 months'           => 'Menos de 6 meses',
            'Between 3 months and 1 year'  => 'Más de 6 meses',
            'More than 1 year'             => 'Más de 6 meses',
        ];
        return $map[$value] ?? pltraceFormatProfileDisplay($value);
    }
}

if (!function_exists('pltracePickLeadField')) {
    function pltracePickLeadField(array $keys, ?array $leadRow, ?array $cfRow = null)
    {
        foreach ($keys as $key) {
            if (is_array($cfRow)) {
                $val = trim((string) ($cfRow[$key] ?? ''));
                if ($val !== '') {
                    return $val;
                }
            }
            if (is_array($leadRow)) {
                $val = trim((string) ($leadRow[$key] ?? ''));
                if ($val !== '') {
                    return $val;
                }
            }
        }
        return '';
    }
}

if (!function_exists('pltraceFormatProfileDate')) {
    function pltraceFormatProfileDate($raw, $includeTime = false)
    {
        $raw = trim((string) $raw);
        if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
            return '—';
        }
        $ts = strtotime($raw);
        if ($ts === false || $ts <= 0) {
            return '—';
        }
        return $includeTime ? date('d/m/Y h:i a', $ts) : date('d/m/Y', $ts);
    }
}

if (!function_exists('pltraceResolveProfileTipoCliente')) {
    function pltraceResolveProfileTipoCliente(?array $leadRow, ?array $cfRow, $tablaOrigen = '')
    {
        if (is_array($cfRow)) {
            $mapped = pltraceMapTipoClienteValue($cfRow['tipo_cliente'] ?? '');
            if ($mapped !== '') {
                return $mapped;
            }
        }
        if (is_array($leadRow)) {
            $mapped = pltraceMapTipoClienteValue($leadRow['tipo_cliente'] ?? '');
            if ($mapped !== '') {
                return $mapped;
            }
        }
        if (pltraceIsWeddingPlannerLead($tablaOrigen, is_array($leadRow) ? $leadRow : [], is_array($cfRow) ? $cfRow : null)) {
            return 'Wedding Planner';
        }
        $how = pltracePickLeadField(['how_did_you_meet'], $leadRow, $cfRow);
        if ($how === '1') {
            return 'Wedding Planner';
        }
        if ($how === '0' || $how !== '') {
            return 'Cliente Final';
        }
        return '—';
    }
}

if (!function_exists('pltraceFetchLeadProfile')) {
    /**
     * Perfil completo del lead para modal de detalle (registro manual / contact_form).
     *
     * @return array{success:bool,error?:string,profile?:array<string,mixed>}
     */
    function pltraceFetchLeadProfile($conn, $tablaOrigen, $origId, $cfId = 0)
    {
        if (!($conn instanceof mysqli)) {
            return ['success' => false, 'error' => 'Conexión inválida'];
        }

        require_once __DIR__ . '/calendario_estatus_historial_helper.php';

        $tablaOrigen = trim((string) $tablaOrigen);
        $origId = (int) $origId;
        $cfId = (int) $cfId;

        if ($cfId <= 0 && $tablaOrigen !== '' && $origId > 0) {
            $cfId = pltraceFindContactFormId($conn, $tablaOrigen, $origId);
        }

        $cfRow = null;
        if ($cfId > 0) {
            $stmt = $conn->prepare('SELECT * FROM contact_form WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $cfId);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    $cfRow = $res ? $res->fetch_assoc() : null;
                }
                $stmt->close();
            }
            if (is_array($cfRow)) {
                if ($tablaOrigen === '') {
                    $tablaOrigen = trim((string) ($cfRow['tabla_origen'] ?? ''));
                }
                if ($origId <= 0) {
                    $origId = (int) ($cfRow['original_lead_id'] ?? 0);
                }
            }
        }

        $leadRow = null;
        if ($tablaOrigen !== '' && $origId > 0) {
            $escapedTable = $conn->real_escape_string($tablaOrigen);
            $checkTable = $conn->query("SHOW TABLES LIKE '$escapedTable'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $leadRes = $conn->query("SELECT * FROM `$escapedTable` WHERE id = $origId LIMIT 1");
                if ($leadRes && $leadRes->num_rows > 0) {
                    $leadRow = $leadRes->fetch_assoc();
                }
            }
        }

        if (!is_array($cfRow) && !is_array($leadRow)) {
            return ['success' => false, 'error' => 'Lead no encontrado'];
        }

        $appointmentsByCFId = dashComercialGetAppointmentsByClient($conn);
        $appointment = ($cfId > 0 && isset($appointmentsByCFId[$cfId])) ? $appointmentsByCFId[$cfId] : null;

        $cfPayload = is_array($cfRow) ? ['id_vendedor_asignado' => (int) ($cfRow['id_vendedor_asignado'] ?? 0)] : null;
        $vendedorId = pltraceResolveLeadVendedorId($cfPayload, $appointment, $leadRow);
        $vendedora = pltraceResolveLeadVendedora($vendedorId, dashComercialBuildVendorMap($conn));

        $nombre = dashComercialResolveLeadName(is_array($leadRow) ? $leadRow : (is_array($cfRow) ? $cfRow : []));
        if ($nombre === 'Sin nombre' && is_array($cfRow)) {
            $nombre = trim((string) ($cfRow['names'] ?? ''));
        }

        $email = pltracePickLeadField(['email_address', 'email'], $leadRow, $cfRow);
        if ($email === '' && is_array($leadRow)) {
            $email = dashComercialResolveLeadEmail($leadRow);
        }
        if ($email === '—') {
            $email = '';
        }

        $telefono = pltracePickLeadField(['telephone', 'phone', 'telefono', 'contacto_celular', 'celular'], $leadRow, $cfRow);
        if ($telefono === '' && is_array($leadRow)) {
            $telefono = dashComercialResolveLeadPhone($leadRow);
        }
        if ($telefono === '—') {
            $telefono = '';
        }

        $countryCode = pltracePickLeadField(['country_code'], $leadRow, $cfRow);
        if ($countryCode !== '' && $telefono !== '' && strpos($telefono, $countryCode) !== 0) {
            $countryDigits = preg_replace('/\D/', '', $countryCode);
            if ($countryDigits !== '' && strpos(preg_replace('/\D/', '', $telefono), $countryDigits) === 0) {
                $telefonoDisplay = '+' . preg_replace('/\D/', '', $telefono);
            } else {
                $telefonoDisplay = trim($countryCode . ' ' . $telefono);
            }
        } else {
            $telefonoDisplay = $telefono;
        }

        $city = pltracePickLeadField(['city'], $leadRow, $cfRow);
        $weddingLocation = pltracePickLeadField(
            ['wedding_location', 'where_are_you_getting_married_', 'where_is_your_marriage_taking_place_'],
            $leadRow,
            $cfRow
        );

        $weddingDateNotDefined = pltracePickLeadField(['wedding_date_not_defined'], $leadRow, $cfRow);
        $weddingDateRaw = pltracePickLeadField(['wedding_date', 'when_are_you_getting_married_'], $leadRow, $cfRow);
        if ($weddingDateNotDefined === '1' || $weddingDateRaw === 'not defined' || strcasecmp($weddingDateRaw, 'not defined') === 0) {
            $weddingDateLabel = 'El cliente no ha definido aún';
        } elseif ($weddingDateRaw !== '') {
            $weddingDateLabel = pltraceFormatProfileDate($weddingDateRaw, false);
        } else {
            $weddingDateLabel = '—';
        }

        $tipoCliente = pltraceResolveProfileTipoCliente($leadRow, $cfRow, $tablaOrigen);
        $firstContact = pltracePickLeadField(['first_contact_channel'], $leadRow, $cfRow);
        $tipoIg = pltracePickLeadField(['tipo_ig'], $leadRow, $cfRow);
        if ($firstContact === 'IG' && $tipoIg === 'organico') {
            $firstContact = 'IG (Orgánico)';
        } elseif ($firstContact === 'IG' && $tipoIg === 'campana') {
            $firstContact = 'IG (Campaña)';
        }
        $campaignName = pltracePickLeadField(['campaign_name'], $leadRow, $cfRow);
        $platform = pltracePickLeadField(['platform'], $leadRow, $cfRow);
        $howLongKnown = pltraceFormatHowLongKnownUsLabel(
            pltracePickLeadField(['how_long_known_us'], $leadRow, $cfRow)
        );

        $createdRaw = pltracePickLeadField(['created_time', 'created_at', 'submission_date'], $leadRow, $cfRow);
        if ($createdRaw === '' && is_array($cfRow)) {
            $createdRaw = trim((string) ($cfRow['submission_date'] ?? ''));
        }
        $fechaRegistro = pltraceFormatProfileDate($createdRaw, true);
        if ($fechaRegistro === '—' && function_exists('tracerFormatStoredDateTime')) {
            $normalized = tracerNormalizeDateTime($createdRaw);
            if ($normalized !== null) {
                $fechaRegistro = tracerFormatStoredDateTime($normalized);
            }
        }

        $comentarios = '';
        if (function_exists('tracerResolvePreLeadRegistroNotas')) {
            $comentarios = tracerResolvePreLeadRegistroNotas(
                is_array($leadRow) ? $leadRow : [],
                is_array($cfRow) ? $cfRow : null
            );
        }

        $status = pltraceResolveLeadStatus($tablaOrigen, is_array($cfRow) ? $cfRow : null, $appointment, $leadRow);
        $estatusLabel = ucfirst((string) $status);
        if ((int) (is_array($cfRow) ? ($cfRow['cliente'] ?? 0) : 0) === 1) {
            $estatusLabel = 'Cliente';
        } elseif (is_array($appointment) && isset($appointment['estatus']) && function_exists('calendarioEstatusHistorialLabel')) {
            $estatusLabel = calendarioEstatusHistorialLabel((int) $appointment['estatus']);
        }

        $citaFecha = '';
        $citaHora = '';
        $citaEstatus = '';
        $proximaSesion = '—';
        if (is_array($appointment)) {
            $citaFecha = trim((string) ($appointment['fecha'] ?? ''));
            $citaHora = trim((string) ($appointment['hora'] ?? ''));
            if ($citaFecha !== '' && $citaFecha !== '0000-00-00') {
                $citaTs = strtotime($citaFecha . ' ' . ($citaHora !== '' ? $citaHora : '00:00:00'));
                $citaDisplay = pltraceFormatProfileDate($citaFecha . ' ' . ($citaHora !== '' ? $citaHora : '00:00:00'), true);
                if ($citaTs !== false && $citaTs >= time() && (int) ($appointment['estatus'] ?? -1) === 0) {
                    $proximaSesion = $citaDisplay;
                } elseif ($citaTs !== false && $citaTs >= time()) {
                    $proximaSesion = $citaDisplay;
                }
            }
            if (isset($appointment['estatus']) && function_exists('calendarioEstatusHistorialLabel')) {
                $citaEstatus = calendarioEstatusHistorialLabel((int) $appointment['estatus']);
            }
        }

        $proximaAccion = '—';
        if ($tablaOrigen !== '' && $origId > 0) {
            pltraceEnsureInteractionCompletedColumn($conn);
            $safeTable = $conn->real_escape_string($tablaOrigen);
            $sqlNext = "SELECT interaction_type, outcome, next_action, next_action_date
                        FROM lead_interactions
                        WHERE LOWER(tabla_origen) = LOWER('$safeTable')
                          AND lead_id = $origId
                          AND next_action_date IS NOT NULL
                          AND (next_action_completed IS NULL OR next_action_completed = 0)
                        ORDER BY next_action_date ASC, id ASC
                        LIMIT 1";
            $resNext = $conn->query($sqlNext);
            if ($resNext && ($nextRow = $resNext->fetch_assoc())) {
                $parts = [];
                $type = trim((string) ($nextRow['interaction_type'] ?? ''));
                $outcome = trim((string) ($nextRow['outcome'] ?? ''));
                $action = trim((string) ($nextRow['next_action'] ?? ''));
                $dateOnly = trim((string) ($nextRow['next_action_date'] ?? ''));
                if ($type !== '') {
                    $parts[] = $type;
                }
                if ($outcome !== '') {
                    $parts[] = $outcome;
                }
                if ($action !== '') {
                    $parts[] = $action;
                }
                if ($dateOnly !== '') {
                    $parts[] = pltraceFormatProfileDate($dateOnly, true);
                }
                if (!empty($parts)) {
                    $proximaAccion = implode(' · ', $parts);
                }
            }
        }

        $sections = [
            [
                'number'      => 1,
                'title'       => 'Datos de contacto',
                'description' => 'Nombre, correo, número de teléfono y datos básicos del evento.',
                'fields'      => [
                    ['label' => 'Nombre completo', 'value' => pltraceFormatProfileDisplay($nombre), 'icon' => 'user'],
                    ['label' => 'Correo electrónico', 'value' => pltraceFormatProfileDisplay($email), 'icon' => 'envelope'],
                    ['label' => 'WhatsApp / Teléfono', 'value' => pltraceFormatProfileDisplay($telefonoDisplay), 'icon' => 'phone'],
                    ['label' => 'Lugar del evento', 'value' => pltraceFormatProfileDisplay($weddingLocation), 'icon' => 'map-marker-alt'],
                    ['label' => 'Fecha del evento', 'value' => $weddingDateLabel, 'icon' => 'heart'],
                    ['label' => 'Ciudad de origen del cliente', 'value' => pltraceFormatProfileDisplay($city), 'icon' => 'building'],
                ],
            ],
            [
                'number'      => 2,
                'title'       => 'Tipo de cliente',
                'description' => 'Wedding Planner o Cliente Final.',
                'fields'      => [
                    ['label' => 'Tipo de cliente', 'value' => pltraceFormatProfileDisplay($tipoCliente), 'icon' => 'user-tag'],
                ],
            ],
            [
                'number'      => 3,
                'title'       => 'Primer contacto',
                'description' => 'Canal inicial, origen y medio.',
                'fields'      => [
                    ['label' => 'Primer canal de contacto', 'value' => pltraceFormatProfileDisplay($firstContact), 'icon' => 'comment-dots'],
                    ['label' => 'Campaña / Origen', 'value' => pltraceFormatProfileDisplay($campaignName), 'icon' => 'bullhorn'],
                    ['label' => 'Medio', 'value' => pltraceFormatProfileDisplay($platform), 'icon' => 'share-alt'],
                ],
            ],
            [
                'number'      => 4,
                'title'       => 'Conocimiento',
                'description' => 'Antigüedad con la marca.',
                'fields'      => [
                    ['label' => '¿Desde hace cuánto nos conoce?', 'value' => $howLongKnown, 'icon' => 'clock'],
                ],
            ],
            [
                'number'      => 5,
                'title'       => 'Fecha de registro',
                'description' => 'Fecha y hora de captura del lead.',
                'fields'      => [
                    ['label' => 'Fecha y hora', 'value' => $fechaRegistro, 'icon' => 'calendar-plus'],
                ],
            ],
            [
                'number'      => 6,
                'title'       => 'Asignación y seguimiento',
                'description' => 'Vendedora, estatus y citas.',
                'fields'      => [
                    ['label' => 'Vendedora asignada', 'value' => pltraceFormatProfileDisplay($vendedora), 'icon' => 'user-tie'],
                    ['label' => 'Estatus actual', 'value' => pltraceFormatProfileDisplay($estatusLabel), 'icon' => 'flag'],
                    ['label' => 'Próxima sesión', 'value' => $proximaSesion, 'icon' => 'calendar-check'],
                    ['label' => 'Estatus de la sesión', 'value' => pltraceFormatProfileDisplay($citaEstatus), 'icon' => 'clipboard-check'],
                    ['label' => 'Próxima acción pendiente', 'value' => $proximaAccion, 'icon' => 'tasks'],
                ],
            ],
            [
                'number'      => 7,
                'title'       => 'Comentarios',
                'description' => 'Notas del cliente para conocimiento de la vendedora.',
                'fields'      => [
                    ['label' => 'Notas del cliente', 'value' => pltraceFormatProfileDisplay($comentarios), 'multiline' => true, 'icon' => 'sticky-note'],
                ],
            ],
        ];

        return [
            'success' => true,
            'profile' => [
                'nombre'    => $nombre,
                'hero'      => [
                    'nombre'          => pltraceFormatProfileDisplay($nombre),
                    'email'           => pltraceFormatProfileDisplay($email),
                    'city'            => pltraceFormatProfileDisplay($city),
                    'estatus'         => pltraceFormatProfileDisplay($estatusLabel),
                    'vendedora'       => pltraceFormatProfileDisplay($vendedora),
                    'fecha_registro'  => $fechaRegistro,
                    'initial'         => pltraceVendedorInitial($nombre),
                ],
                'tags'      => array_values(array_filter(array_unique([
                    $tipoCliente !== '—' ? $tipoCliente : '',
                    $estatusLabel !== '—' ? $estatusLabel : '',
                    $firstContact !== '' ? $firstContact : '',
                    $campaignName !== '' ? $campaignName : '',
                    $platform !== '' ? $platform : '',
                ]))),
                'bio'       => $comentarios !== '' ? $comentarios : '',
                'sections'  => $sections,
                'tabla'     => $tablaOrigen,
                'orig_id'   => $origId,
                'cf_id'     => $cfId,
            ],
        ];
    }
}
