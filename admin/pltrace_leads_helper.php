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
            $sql = "SELECT id, original_lead_id, cliente
                    FROM contact_form
                    WHERE LOWER(tabla_origen) = LOWER('$safeTable')
                      AND original_lead_id IN ($idsList)";
            $resCf = $conn->query($sql);
            if ($resCf) {
                while ($row = $resCf->fetch_assoc()) {
                    $key = $t . '|' . (int) $row['original_lead_id'];
                    $contactFormByLead[$key] = [
                        'cf_id'   => (int) $row['id'],
                        'cliente' => (int) ($row['cliente'] ?? 0),
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

            $rows[] = [
                'trace_key'    => $key,
                'tabla_origen' => $tabla,
                'orig_id'      => $origId,
                'cf_id'        => $cfId,
                'nombre'       => dashComercialResolveLeadName($lead),
                'email'        => dashComercialResolveLeadEmail($lead),
                'telefono'     => dashComercialResolveLeadPhone($lead),
                'estatus'      => ucfirst($status),
                'estatus_raw'  => $status,
                'fecha'        => dashComercialFormatDisplayDate($createdTime),
                'fecha_raw'    => $createdTime,
            ];
        }

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
