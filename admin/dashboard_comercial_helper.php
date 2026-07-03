<?php
/**
 * Métricas del Dashboard Comercial.
 *
 * Agendas: cohorte de consulta_agendados_leads.php (fecha en que agendó).
 * Atendidos: cohorte de consulta_post_leads.php (fecha de atención, mín. 15/jun/2026).
 * Clientes: contact_form.cliente = 1 con fecha_cambio_cliente en el periodo (clientes.php).
 * Tabla historial: referencia por fecha_cambio.
 */

require_once __DIR__ . '/calendario_estatus_historial_helper.php';
require_once __DIR__ . '/status_badge_helper.php';
require_once __DIR__ . '/dashboard_comercial_period_helper.php';
require_once __DIR__ . '/usuario_roles_helper.php';
require_once __DIR__ . '/wp_eventos_contact_form_helper.php';
require_once __DIR__ . '/planner_event_display_helper.php';

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
        $min = dashComercialGetMinPeriodDate();
        $minTs = strtotime($min);

        $effectiveStart = $min;
        if (trim((string) $startDate) !== '') {
            $ts = strtotime($startDate);
            if ($ts !== false) {
                $sd = date('Y-m-d', $ts);
                if ($ts >= $minTs) {
                    $effectiveStart = $sd;
                }
            }
        }

        $parts = [
            "DATE($columnExpr) >= '" . $conn->real_escape_string($effectiveStart) . "'",
        ];

        if (trim((string) $endDate) !== '') {
            $ts = strtotime($endDate);
            if ($ts !== false) {
                $ed = date('Y-m-d', max($ts, $minTs));
                $parts[] = "DATE($columnExpr) <= '" . $conn->real_escape_string($ed) . "'";
            }
        }

        return implode(' AND ', $parts);
    }
}

if (!function_exists('dashComercialCountWpEventosLeadsInPeriod')) {
    /**
     * Eventos WP registrados en contact_form (misma fuente que consulta_leads.php).
     */
    function dashComercialCountWpEventosLeadsInPeriod($conn, $startDate, $endDate, array $existingKeys = [])
    {
        $count = 0;
        foreach (wpEventosCfBuildLeadsForConsultaLeads($conn) as $wpLead) {
            $key = ($wpLead['tabla_origen'] ?? '') . '|' . (int) ($wpLead['id'] ?? 0);
            if (isset($existingKeys[$key])) {
                continue;
            }

            $dateField = trim((string) ($wpLead['created_time'] ?? $wpLead['submission_date'] ?? ''));
            if (!dashComercialLeadInDateRange($dateField, $startDate, $endDate)) {
                continue;
            }

            $count++;
        }

        return $count;
    }
}

if (!function_exists('dashComercialCountTotalLeads')) {
    /**
     * Total de leads pre-calificación en el periodo (tablas_leads + eventos_wp en contact_form).
     */
    function dashComercialCountTotalLeads($conn, $startDate, $endDate)
    {
        $total = 0;
        $existingKeys = [];
        $resTablas = $conn->query("SELECT nombre FROM tablas_leads WHERE tipo != 2 OR nombre = 'wp_citas_leads' ORDER BY nombre");
        if (!$resTablas) {
            return dashComercialCountWpEventosLeadsInPeriod($conn, $startDate, $endDate);
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

            $sql = "SELECT id FROM `$tableName` WHERE " . implode(' AND ', $where);
            $res = $conn->query($sql);
            if (!$res) {
                continue;
            }

            while ($r = $res->fetch_assoc()) {
                $leadId = (int) ($r['id'] ?? 0);
                if ($leadId <= 0) {
                    continue;
                }
                $existingKeys[$tableName . '|' . $leadId] = true;
                $total++;
            }
        }

        $total += dashComercialCountWpEventosLeadsInPeriod($conn, $startDate, $endDate, $existingKeys);

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
        static $cacheByConn = [];
        $cacheKey = is_object($conn) ? spl_object_id($conn) : 0;
        if ($cacheKey > 0 && isset($cacheByConn[$cacheKey])) {
            return $cacheByConn[$cacheKey];
        }

        $map = [];
        $res = $conn->query('SELECT * FROM calendario');
        if (!$res) {
            if ($cacheKey > 0) {
                $cacheByConn[$cacheKey] = $map;
            }
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

        if ($cacheKey > 0) {
            $cacheByConn[$cacheKey] = $map;
        }

        return $map;
    }
}

if (!function_exists('dashComercialGetAppointmentsByEventId')) {
    /**
     * Citas WP: calendario.idclie = eventos_wp.id (tipo = 1).
     */
    function dashComercialGetAppointmentsByEventId($conn)
    {
        static $cacheByConn = [];
        $cacheKey = is_object($conn) ? spl_object_id($conn) : 0;
        if ($cacheKey > 0 && isset($cacheByConn[$cacheKey])) {
            return $cacheByConn[$cacheKey];
        }

        $map = wpEventosCfGetAppointmentsByEventId($conn);
        if ($cacheKey > 0) {
            $cacheByConn[$cacheKey] = $map;
        }

        return $map;
    }
}

if (!function_exists('dashComercialIsEventosWpContactForm')) {
    function dashComercialIsEventosWpContactForm(array $cf)
    {
        $form = strtolower(trim((string) ($cf['form_name'] ?? '')));
        $tabla = strtolower(trim((string) ($cf['tabla_origen'] ?? '')));

        return $form === 'eventos_wp' || $tabla === 'eventos_wp';
    }
}

if (!function_exists('dashComercialResolveAppointmentForLead')) {
    function dashComercialResolveAppointmentForLead(array $cf, array $appointmentsByClient, array $appointmentsByEventId)
    {
        $cid = (int) ($cf['id'] ?? 0);
        if ($cid > 0 && isset($appointmentsByClient[$cid])) {
            return $appointmentsByClient[$cid];
        }

        if (!dashComercialIsEventosWpContactForm($cf)) {
            return null;
        }

        $eventId = (int) ($cf['original_lead_id'] ?? 0);
        if ($eventId > 0 && isset($appointmentsByEventId[$eventId])) {
            return $appointmentsByEventId[$eventId];
        }

        return null;
    }
}

if (!function_exists('dashComercialAppointmentDateIsValid')) {
    function dashComercialAppointmentDateIsValid(?array $appt)
    {
        if (!is_array($appt)) {
            return true;
        }

        $apptFechaRaw = trim($appt['fecha'] ?? '');
        $apptHoraRaw = trim($appt['hora'] ?? '');
        $apptTs = ($apptFechaRaw !== '') ? strtotime($apptFechaRaw . ' ' . $apptHoraRaw) : false;

        return !($apptFechaRaw === '' || $apptFechaRaw === '0000-00-00' || $apptTs === false || $apptTs <= 0);
    }
}

if (!function_exists('dashComercialResolvePostLeadEstatus')) {
    function dashComercialResolvePostLeadEstatus(array $cf, ?array $appt)
    {
        if (dashComercialIsEventosWpContactForm($cf)) {
            global $conn;
            if (isset($conn)) {
                $cf = wpEventosCfHydratePlannerContactFormFields($conn, $cf);
            }

            $plannerEstatus = wpEventosCfResolvePlannerTipoClienteEstatus($cf);
            if ($plannerEstatus !== null) {
                return $plannerEstatus;
            }

            if (is_array($appt) && isset($appt['estatus'])) {
                $mapped = dashComercialResolveLeadStatus($cf, $appt);
                if ($mapped === 'agendado') {
                    return 'atendido';
                }
                if ($mapped !== '' && $mapped !== 'cliente') {
                    return $mapped;
                }
            }

            return 'atendido';
        }

        return dashComercialResolveLeadStatus($cf, $appt);
    }
}

if (!function_exists('dashComercialResolvePostLeadDateField')) {
    function dashComercialResolvePostLeadDateField(
        array $item,
        array $fechaAtencionByContactFormId = [],
        array $appointmentsByClient = [],
        array $appointmentsByEventId = [],
        array $fechaMuertoHistorialByCfId = []
    ) {
        $cf = $item['cf'] ?? [];
        $merged = array_merge($cf, [
            'id'      => (int) ($cf['id'] ?? 0),
            'estatus' => (string) ($item['estatus'] ?? ''),
        ]);
        $appt = !empty($item['appt']) && is_array($item['appt']) ? $item['appt'] : null;
        $estatusKey = strtolower(trim((string) ($item['estatus'] ?? '')));

        if (in_array($estatusKey, ['muerto', 'fantasma'], true)) {
            $cfId = (int) ($merged['id'] ?? 0);
            if ($cfId > 0 && !empty($fechaMuertoHistorialByCfId[$cfId])) {
                return $fechaMuertoHistorialByCfId[$cfId];
            }
        }

        $dateField = plannerProfileResolvePostCohortDate($merged, $fechaAtencionByContactFormId, $appt);
        if ($dateField === '' && in_array($estatusKey, ['muerto', 'fantasma'], true)) {
            return dashComercialResolveAgendadosCohortDateField($merged, $appointmentsByClient, $appointmentsByEventId);
        }
        if ($dateField === '' && in_array($estatusKey, ['muerto', 'fantasma'], true)) {
            foreach (['created_time', 'submission_date'] as $field) {
                $raw = trim((string) ($merged[$field] ?? ($item['created_time'] ?? '')));
                if ($raw !== '' && strpos($raw, '0000-00-00') !== 0) {
                    return $raw;
                }
            }
        }

        return $dateField;
    }
}

if (!function_exists('dashComercialResolveCohortVendorId')) {
    function dashComercialResolveCohortVendorId(array $cf, ?array $appt)
    {
        if (is_array($appt)) {
            $fromAppt = (int) ($appt['idusu'] ?? $appt['id_vendedor_asignado'] ?? 0);
            if ($fromAppt > 0) {
                return $fromAppt;
            }
        }

        return (int) ($cf['id_vendedor_asignado'] ?? $cf['usuario_asignado'] ?? 0);
    }
}

if (!function_exists('dashComercialResolveLeadVendorId')) {
    /**
     * Misma resolución de vendedor/a que clientes.php y consulta_post_leads.php al filtrar por columna.
     * Leads normales: cita (idusu) → contact_form. Eventos WP: cita → evento/asesor → planner.
     */
    function dashComercialResolveLeadVendorId($conn, array $lead, ?array $appt = null, ?array $appointmentsByEventId = null)
    {
        if (!function_exists('wpEventosCfIsWpEventLead')) {
            require_once __DIR__ . '/wp_eventos_contact_form_helper.php';
        }

        if (!is_array($appointmentsByEventId)) {
            $appointmentsByEventId = dashComercialGetAppointmentsByEventId($conn);
        }

        if (function_exists('wpEventosCfIsWpEventLead') && wpEventosCfIsWpEventLead($lead)) {
            return (int) wpEventosCfResolveEventVendedorId($conn, $lead, $appt, $appointmentsByEventId);
        }

        return (int) dashComercialResolveCohortVendorId($lead, $appt);
    }
}

if (!function_exists('dashComercialResolveCohortItemVendorId')) {
    function dashComercialResolveCohortItemVendorId($conn, array $item, ?array $appointmentsByEventId = null)
    {
        $cf = is_array($item['cf'] ?? null) ? $item['cf'] : [];
        $appt = $item['appt'] ?? [];
        $apptArg = (is_array($appt) && $appt !== []) ? $appt : null;

        $lead = $cf;
        if (dashComercialIsEventosWpContactForm($cf)) {
            $enriched = dashComercialEnrichEventosWpCohortItem($conn, [
                'cf'   => $cf,
                'appt' => is_array($appt) ? $appt : [],
            ]);
            $lead = $enriched['cf'];
            if ($apptArg === null && !empty($enriched['appt']) && is_array($enriched['appt'])) {
                $apptArg = $enriched['appt'];
            }
        } elseif ($cf !== []) {
            dashComercialMergeLeadOrigIntoCf($conn, $lead, $cf);
        }

        return dashComercialResolveLeadVendorId($conn, $lead, $apptArg, $appointmentsByEventId);
    }
}

if (!function_exists('dashComercialEnrichEventosWpCohortItem')) {
    function dashComercialEnrichEventosWpCohortItem($conn, array $item)
    {
        if (!dashComercialIsEventosWpContactForm($item['cf'] ?? [])) {
            return $item;
        }

        $cf = $item['cf'];
        $eventId = (int) ($cf['original_lead_id'] ?? 0);
        if ($eventId <= 0) {
            return $item;
        }

        $leadRes = $conn->query("SELECT novios, id_asesor, fecha_atendido FROM eventos_wp WHERE id = $eventId LIMIT 1");
        if (!$leadRes || $leadRes->num_rows === 0) {
            return $item;
        }

        $leadRow = $leadRes->fetch_assoc();
        $merged = wpEventosCfEnrichMergedLead($conn, $cf, $cf);
        $item['cf'] = array_merge($cf, $merged);
        if (!empty($leadRow['fecha_atendido'])) {
            $item['cf']['fecha_atendido'] = trim((string) $leadRow['fecha_atendido']);
            $item['cf']['evento_fecha_atendido'] = $item['cf']['fecha_atendido'];
        }
        if (!empty($merged['full_name'])) {
            $item['cf']['names'] = $merged['full_name'];
        }
        if (!empty($merged['wedding_planner_name'])) {
            $item['cf']['wedding_planner_name'] = $merged['wedding_planner_name'];
        }
        if ((int) ($item['cf']['id_vendedor_asignado'] ?? 0) <= 0 && (int) ($leadRow['id_asesor'] ?? 0) > 0) {
            $item['cf']['id_vendedor_asignado'] = (int) $leadRow['id_asesor'];
        }

        return $item;
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
        if (dashComercialIsEventosWpContactForm($cf) && (int) ($cf['tipo_cliente'] ?? 0) === 1) {
            global $conn;
            if (isset($conn)) {
                $cf = wpEventosCfHydratePlannerContactFormFields($conn, $cf);
            }
            $plannerEstatus = wpEventosCfResolvePlannerTipoClienteEstatus($cf);
            if ($plannerEstatus !== null) {
                return $plannerEstatus;
            }
        }

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

if (!function_exists('dashComercialPostLeadDateInRange')) {
    /**
     * Misma regla de fechas que consulta_post_leads.php (incluye mínimo 15/jun/2026).
     */
    function dashComercialPostLeadDateInRange($createdTime, $startDate, $endDate)
    {
        $d = dashComercialExtractDateFromTimestamp($createdTime);
        if ($d === null) {
            return false;
        }

        $minDate = dashComercialGetMinPeriodDate();
        if ($d < $minDate) {
            return false;
        }

        if ($startDate === '' && $endDate === '') {
            return true;
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

if (!function_exists('dashComercialSanitizeDisplayFechaAtencionRaw')) {
    /**
     * Fecha atendido en modal: ocultar atenciones anteriores al 15/jun/2026 (misma regla que post_leads).
     */
    function dashComercialSanitizeDisplayFechaAtencionRaw($rawDate)
    {
        $rawDate = trim((string) $rawDate);
        if ($rawDate === '' || strpos($rawDate, '0000-00-00') === 0) {
            return '';
        }

        $dateOnly = dashComercialExtractDateFromTimestamp($rawDate);
        if ($dateOnly === null || $dateOnly < dashComercialGetMinPeriodDate()) {
            return '';
        }

        return $rawDate;
    }
}

if (!function_exists('dashComercialIsAttendedDateBeforeMinPeriod')) {
    function dashComercialIsAttendedDateBeforeMinPeriod($rawDate)
    {
        $rawDate = trim((string) $rawDate);
        if ($rawDate === '' || strpos($rawDate, '0000-00-00') === 0) {
            return false;
        }

        $dateOnly = dashComercialExtractDateFromTimestamp($rawDate);
        if ($dateOnly === null) {
            return false;
        }

        return $dateOnly < dashComercialGetMinPeriodDate();
    }
}

if (!function_exists('dashComercialGetAttendedDateBeforeMinPeriodLegend')) {
    function dashComercialGetAttendedDateBeforeMinPeriodLegend()
    {
        return 'No se muestra: anterior al 15/Jun/26';
    }
}

if (!function_exists('dashComercialFilterAtencionDatesFromMinPeriod')) {
    /**
     * @param array<int, string> $fechaAtencionByContactFormId
     * @return array<int, string>
     */
    function dashComercialFilterAtencionDatesFromMinPeriod(array $fechaAtencionByContactFormId)
    {
        $filtered = [];
        foreach ($fechaAtencionByContactFormId as $cfId => $fecha) {
            $sanitized = dashComercialSanitizeDisplayFechaAtencionRaw($fecha);
            if ($sanitized !== '') {
                $filtered[(int) $cfId] = $sanitized;
            }
        }

        return $filtered;
    }
}

if (!function_exists('dashComercialBatchLoadHistorialEstatusFlagsByContactFormIds')) {
    /**
     * Valida paso por historial usando solo estatus 1 (atendido) y 3 (muerto).
     *
     * @param int[] $contactFormIds
     * @return array{atendido: array<int, true>, muerto: array<int, true>}
     */
    function dashComercialBatchLoadHistorialEstatusFlagsByContactFormIds($conn, array $contactFormIds)
    {
        ensureCalendarioEstatusHistorialTable($conn);

        $atendido = [];
        $muerto = [];
        $contactFormIds = array_values(array_unique(array_filter(array_map('intval', $contactFormIds), static function ($id) {
            return $id > 0;
        })));
        if (empty($contactFormIds)) {
            return ['atendido' => $atendido, 'muerto' => $muerto];
        }

        foreach (array_chunk($contactFormIds, 500) as $chunk) {
            $idsList = implode(',', $chunk);
            $sql = "SELECT DISTINCT c.idclie AS idclie, CAST(h.estatus AS SIGNED) AS estatus_code
                    FROM calendario_estatus_historial h
                    INNER JOIN calendario c ON c.id = h.id_calendario
                    WHERE c.idclie IN ($idsList)
                      AND CAST(h.estatus AS SIGNED) IN (1, 3)
                      AND COALESCE(c.eliminado, 0) = 0";
            $res = $conn->query($sql);
            if (!$res) {
                continue;
            }
            while ($row = $res->fetch_assoc()) {
                $idclie = (int) ($row['idclie'] ?? 0);
                $code = (int) ($row['estatus_code'] ?? 0);
                if ($idclie <= 0) {
                    continue;
                }
                if ($code === 1) {
                    $atendido[$idclie] = true;
                } elseif ($code === 3) {
                    $muerto[$idclie] = true;
                }
            }
        }

        foreach (array_chunk($contactFormIds, 500) as $chunk) {
            $idsList = implode(',', $chunk);
            $wpRes = $conn->query(
                "SELECT DISTINCT cf.id AS idclie, CAST(h.estatus AS SIGNED) AS estatus_code
                 FROM calendario_estatus_historial h
                 INNER JOIN calendario c ON c.id = h.id_calendario
                 INNER JOIN contact_form cf ON cf.original_lead_id = c.idclie
                   AND LOWER(TRIM(COALESCE(cf.tabla_origen, ''))) = 'eventos_wp'
                 WHERE cf.id IN ($idsList)
                   AND CAST(h.estatus AS SIGNED) IN (1, 3)
                   AND c.tipo = 1
                   AND COALESCE(c.eliminado, 0) = 0"
            );
            if (!$wpRes) {
                continue;
            }
            while ($wpRow = $wpRes->fetch_assoc()) {
                $idclie = (int) ($wpRow['idclie'] ?? 0);
                $code = (int) ($wpRow['estatus_code'] ?? 0);
                if ($idclie <= 0) {
                    continue;
                }
                if ($code === 1) {
                    $atendido[$idclie] = true;
                } elseif ($code === 3) {
                    $muerto[$idclie] = true;
                }
            }
        }

        return ['atendido' => $atendido, 'muerto' => $muerto];
    }
}

if (!function_exists('dashComercialLoadAtendidoHistorialClientIds')) {
    /**
     * IDs de contact_form que alguna vez alcanzaron estatus 1 (atendido) o 4 (cliente) en calendario_estatus_historial.
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
                WHERE h.estatus IN (1, 4)';

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

        if ($clientIds !== null) {
            $clientIds = array_values(array_filter(array_map('intval', $clientIds), function ($id) {
                return $id > 0;
            }));
            if (!empty($clientIds)) {
                $idsList = implode(',', $clientIds);
                $wpRes = $conn->query(
                    "SELECT DISTINCT cf.id AS idclie
                     FROM calendario_estatus_historial h
                     INNER JOIN calendario c ON c.id = h.id_calendario
                     INNER JOIN contact_form cf ON cf.original_lead_id = c.idclie
                       AND LOWER(TRIM(COALESCE(cf.tabla_origen, ''))) = 'eventos_wp'
                     WHERE h.estatus IN (1, 4)
                       AND c.tipo = 1
                       AND cf.id IN ($idsList)"
                );
                if ($wpRes) {
                    while ($wpRow = $wpRes->fetch_assoc()) {
                        $idclie = (int) ($wpRow['idclie'] ?? 0);
                        if ($idclie > 0) {
                            $ids[$idclie] = true;
                        }
                    }
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

if (!function_exists('dashComercialLoadFullContactFormRow')) {
    function dashComercialLoadFullContactFormRow($conn, int $cfId)
    {
        if ($cfId <= 0) {
            return null;
        }

        $res = $conn->query("SELECT * FROM contact_form WHERE id = $cfId LIMIT 1");
        if (!$res || $res->num_rows === 0) {
            return null;
        }

        return $res->fetch_assoc();
    }
}

if (!function_exists('dashComercialMergeLeadOrigIntoCf')) {
    function dashComercialMergeLeadOrigIntoCf($conn, array &$merged, array $cf)
    {
        $formName = trim((string) ($cf['tabla_origen'] ?? ''));
        $origId = (int) ($cf['original_lead_id'] ?? 0);
        if ($formName === '' || $origId <= 0) {
            return;
        }

        $escapedForm = $conn->real_escape_string($formName);
        $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
        if (!$checkTable || $checkTable->num_rows === 0) {
            return;
        }

        $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
        if (!$leadRes || $leadRes->num_rows === 0) {
            return;
        }

        $leadRow = $leadRes->fetch_assoc();
        if (!empty($leadRow['full_name'])) {
            $merged['full_name'] = $leadRow['full_name'];
        } elseif (!empty($leadRow['names'])) {
            $merged['full_name'] = $leadRow['names'];
        } elseif (!empty($leadRow['name'])) {
            $merged['full_name'] = $leadRow['name'];
        }

        if (!empty($leadRow['fecha_cambio_cliente'])) {
            $merged['fecha_cambio_cliente'] = $leadRow['fecha_cambio_cliente'];
        } elseif (!empty($leadRow['created_time'])) {
            $merged['created_time'] = $leadRow['created_time'];
        } elseif (!empty($leadRow['created_at'])) {
            $merged['created_time'] = $leadRow['created_at'];
        }

        foreach ($leadRow as $k => $v) {
            if (is_string($v)) {
                $v = trim($v);
            }
            if (!isset($merged[$k]) || $merged[$k] === '' || $merged[$k] === null) {
                $merged[$k] = $v;
            }
        }
    }
}

if (!function_exists('dashComercialLoadLeadRowForCf')) {
    function dashComercialLoadLeadRowForCf($conn, array $cf)
    {
        $formName = trim((string) ($cf['tabla_origen'] ?? ''));
        $origId = (int) ($cf['original_lead_id'] ?? 0);
        if ($formName === '' || $origId <= 0) {
            return null;
        }

        $escapedForm = $conn->real_escape_string($formName);
        $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
        if (!$checkTable || $checkTable->num_rows === 0) {
            return null;
        }

        $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
        if (!$leadRes || $leadRes->num_rows === 0) {
            return null;
        }

        return $leadRes->fetch_assoc();
    }
}

if (!function_exists('dashComercialLeadArrivalDateField')) {
    /** Fecha de llegada / sesión (consulta_agendados_leads), no fecha de cierre. */
    function dashComercialLeadArrivalDateField(array $lead)
    {
        if (function_exists('postLeadResolveDateFieldForLead')) {
            return postLeadResolveDateFieldForLead($lead);
        }

        return wpEventosCfResolveDateField($lead);
    }
}

if (!function_exists('dashComercialBuildFullClienteCohortItem')) {
    /**
     * Ítem de cohorte cliente con merge completo (misma base que clientes.php).
     *
     * @return array{cf: array, appt: array, estatus: string, created_time: string}|null
     */
    function dashComercialBuildFullClienteCohortItem($conn, array $cf, array $appointmentsByClient)
    {
        $cfId = (int) ($cf['id'] ?? 0);
        if ($cfId <= 0) {
            return null;
        }

        $fechaCambio = dashComercialResolveClienteFechaCambioLikeClientes($conn, $cf);
        if ($fechaCambio === '') {
            return null;
        }

        $merged = $cf;
        dashComercialMergeLeadOrigIntoCf($conn, $merged, $cf);
        $merged['cliente'] = 1;
        $merged['estatus'] = 'cliente';
        $merged['fecha_cambio_cliente'] = $fechaCambio;
        if (empty($merged['full_name'])) {
            $merged['full_name'] = $cf['names'] ?? 'N/A';
        }

        $appt = $appointmentsByClient[$cfId] ?? null;
        if (is_array($appt)) {
            if (isset($appt['idusu']) && (int) $appt['idusu'] > 0) {
                if (empty($merged['id_vendedor_asignado'])) {
                    $merged['id_vendedor_asignado'] = (int) $appt['idusu'];
                }
                if (empty($merged['usuario_asignado'])) {
                    $merged['usuario_asignado'] = (int) $appt['idusu'];
                }
            }
        }

        return [
            'cf'           => $merged,
            'appt'         => is_array($appt) ? $appt : [],
            'estatus'      => 'cliente',
            'created_time' => $fechaCambio,
        ];
    }
}

if (!function_exists('dashComercialFilterCohortByVendedora')) {
    function dashComercialFilterCohortByVendedora($conn, array $cohort, $idVendedora = null)
    {
        if ($idVendedora === null) {
            return $cohort;
        }

        $appointmentsByEventId = dashComercialGetAppointmentsByEventId($conn);
        $filtered = [];
        foreach ($cohort as $cid => $item) {
            if (dashComercialResolveCohortItemVendorId($conn, $item, $appointmentsByEventId) === (int) $idVendedora) {
                $filtered[$cid] = $item;
            }
        }

        return $filtered;
    }
}

if (!function_exists('dashComercialAgendasModalEstatusKeys')) {
    /** Estatus válidos para Tasa de agendas / modal Leads agendados. */
    function dashComercialAgendasModalEstatusKeys()
    {
        return ['agendado', 'fantasma', 'muerto', 'cliente', 'atendido'];
    }
}

if (!function_exists('dashComercialAtendidosModalEstatusKeys')) {
    /** Estatus válidos para Tasa de atención / modal Leads atendidos. */
    function dashComercialAtendidosModalEstatusKeys()
    {
        return ['atendido', 'cliente', 'muerto'];
    }
}

if (!function_exists('dashComercialClientesModalEstatusKeys')) {
    /** Estatus válidos para Tasa de cierre / modal Clientes. */
    function dashComercialClientesModalEstatusKeys()
    {
        return ['cliente'];
    }
}

if (!function_exists('dashComercialResolveCohortItemEstatusKey')) {
    function dashComercialResolveCohortItemEstatusKey(array $item)
    {
        $cf = is_array($item['cf'] ?? null) ? $item['cf'] : [];
        $appt = is_array($item['appt'] ?? null) ? $item['appt'] : null;

        if (dashComercialIsEventosWpContactForm($cf) && (int) ($cf['tipo_cliente'] ?? 0) === 1) {
            global $conn;
            if (isset($conn)) {
                $cf = wpEventosCfHydratePlannerContactFormFields($conn, $cf);
            }
            $plannerEstatus = wpEventosCfResolvePlannerTipoClienteEstatus($cf);
            if ($plannerEstatus !== null) {
                return $plannerEstatus;
            }
        }

        $estatusItem = mb_strtolower(trim((string) ($item['estatus'] ?? '')), 'UTF-8');
        if ($estatusItem === 'cliente' || (int) ($cf['cliente'] ?? 0) === 1) {
            return 'cliente';
        }

        $estatus = $estatusItem;
        if ($estatus === '' && $cf !== []) {
            $estatus = mb_strtolower(trim((string) ($cf['estatus'] ?? '')), 'UTF-8');
        }
        if ($estatus === '' && $appt !== null && $cf !== []) {
            $estatus = mb_strtolower(trim((string) dashComercialResolveLeadStatus($cf, $appt)), 'UTF-8');
        }

        if ($estatus !== '' && is_numeric($estatus)) {
            $codeMap = [
                '0' => 'agendado',
                '1' => 'atendido',
                '2' => 'fantasma',
                '3' => 'muerto',
                '4' => 'cliente',
            ];
            $n = (string) (int) $estatus;
            if (isset($codeMap[$n])) {
                return $codeMap[$n];
            }
        }

        return normalizeLeadStatusKey($estatus);
    }
}

if (!function_exists('dashComercialFilterCohortByEstatusKeys')) {
    function dashComercialFilterCohortByEstatusKeys(array $cohort, array $allowedKeys)
    {
        $allowed = [];
        foreach ($allowedKeys as $key) {
            $allowed[normalizeLeadStatusKey($key)] = true;
        }

        $filtered = [];
        foreach ($cohort as $cid => $item) {
            $estatusKey = dashComercialResolveCohortItemEstatusKey($item);
            if ($estatusKey !== '' && isset($allowed[$estatusKey])) {
                $filtered[$cid] = $item;
            }
        }

        return $filtered;
    }
}

if (!function_exists('dashComercialFilterCohortForAgendasModal')) {
    function dashComercialFilterCohortForAgendasModal(array $cohort)
    {
        return dashComercialFilterCohortByEstatusKeys($cohort, dashComercialAgendasModalEstatusKeys());
    }
}

if (!function_exists('dashComercialFilterCohortForAtendidosModal')) {
    function dashComercialFilterCohortForAtendidosModal(array $cohort)
    {
        return dashComercialFilterCohortByEstatusKeys($cohort, dashComercialAtendidosModalEstatusKeys());
    }
}

if (!function_exists('dashComercialFilterCohortForClientesModal')) {
    function dashComercialFilterCohortForClientesModal(array $cohort)
    {
        return dashComercialFilterCohortByEstatusKeys($cohort, dashComercialClientesModalEstatusKeys());
    }
}

if (!function_exists('dashComercialResolveAgendadosCohortDateField')) {
    /**
     * Fecha de cohorte para agendas — misma lógica que consulta_agendados_leads.php.
     */
    function dashComercialResolveAgendadosCohortDateField(
        array $lead,
        array $appointmentsByClient = [],
        array $appointmentsByEventId = []
    ) {
        if (function_exists('postLeadResolveAgendaCohortDateForLead')) {
            return postLeadResolveAgendaCohortDateForLead($lead, $appointmentsByClient, $appointmentsByEventId);
        }

        $appt = null;
        if (function_exists('postLeadResolveAppointmentForLead')) {
            $appt = postLeadResolveAppointmentForLead($lead, $appointmentsByClient, $appointmentsByEventId);
        }

        if (function_exists('plannerProfileResolveAgendaCohortDate')) {
            return plannerProfileResolveAgendaCohortDate($lead, is_array($appt) ? $appt : null);
        }

        return wpEventosCfResolveDateField($lead);
    }
}

if (!function_exists('dashComercialLeadHasAgendaAppointment')) {
    function dashComercialLeadHasAgendaAppointment(?array $appt)
    {
        if (!is_array($appt)) {
            return false;
        }

        if ((int) ($appt['id'] ?? 0) > 0) {
            return true;
        }

        $fecha = trim((string) ($appt['fecha'] ?? ''));

        return $fecha !== '' && $fecha !== '0000-00-00';
    }
}

if (!function_exists('dashComercialMergeClientesIntoAgendadosCohort')) {
    /**
     * Incorpora clientes contact_form.cliente=1 con cita que no entraron vía session allLeads
     * (p. ej. excluidos por how_did_you_meet=1 o fecha distinta en el filtro previo).
     */
    function dashComercialMergeClientesIntoAgendadosCohort(
        $conn,
        array &$cohort,
        $startDate,
        $endDate,
        $idVendedora,
        array $appointmentsByClient,
        array $appointmentsByEventId
    ) {
        $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
        $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
        $hasFechaFilter = ($sd !== null || $ed !== null);

        $sql = "SELECT * FROM contact_form WHERE cliente = 1 AND LOWER(COALESCE(tabla_origen, '')) != 'wedding_planners' ORDER BY submission_date DESC";
        $res = $conn->query($sql);
        if (!$res) {
            return;
        }

        while ($cf = $res->fetch_assoc()) {
            $tablaOrigenRaw = strtolower(trim($cf['tabla_origen'] ?? ''));
            if ($tablaOrigenRaw === 'wedding_planners' || $tablaOrigenRaw === 'wedding_planner') {
                continue;
            }

            $cid = (int) ($cf['id'] ?? 0);
            if ($cid <= 0 || isset($cohort[$cid])) {
                continue;
            }

            if (!isset($appointmentsByClient[$cid])) {
                continue;
            }

            $merged = $cf;
            dashComercialMergeLeadOrigIntoCf($conn, $merged, $cf);
            $merged['cliente'] = 1;
            $merged['estatus'] = 'cliente';

            $appt = dashComercialResolveAppointmentForLead($merged, $appointmentsByClient, $appointmentsByEventId);
            if (!dashComercialLeadHasAgendaAppointment(is_array($appt) ? $appt : null)) {
                continue;
            }

            if ($hasFechaFilter) {
                $dateField = dashComercialLeadArrivalDateField($merged);
                if ($dateField === '') {
                    continue;
                }
                $d = dashComercialExtractDateFromTimestamp($dateField);
                if ($d === null) {
                    continue;
                }
                if ($sd && $d < $sd) {
                    continue;
                }
                if ($ed && $d > $ed) {
                    continue;
                }
            }

            if ($idVendedora !== null) {
                $vendedorId = dashComercialResolveLeadVendorId(
                    $conn,
                    $merged,
                    is_array($appt) ? $appt : null,
                    $appointmentsByEventId
                );
                if ((int) $vendedorId !== (int) $idVendedora) {
                    continue;
                }
            }

            $fechaCambio = dashComercialResolveClienteFechaCambioLikeClientes($conn, $cf);
            $merged['fecha_cambio_cliente'] = $fechaCambio;

            $cohort[$cid] = [
                'cf'           => $merged,
                'appt'         => is_array($appt) ? $appt : [],
                'estatus'      => 'cliente',
                'created_time' => dashComercialLeadArrivalDateField($merged),
            ];
        }
    }
}

if (!function_exists('dashComercialMergeEventosWpIntoAgendadosCohort')) {
    /**
     * Incorpora contact_form eventos_wp (p. ej. cliente=2 / Cotizado) ausentes de session allLeads.
     */
    function dashComercialMergeEventosWpIntoAgendadosCohort(
        $conn,
        array &$cohort,
        $startDate,
        $endDate,
        array $appointmentsByClient,
        array $appointmentsByEventId
    ) {
        $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
        $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
        $hasFechaFilter = ($sd !== null || $ed !== null);

        $items = wpEventosCfBuildDashboardCohortItems(
            $conn,
            $appointmentsByClient,
            $appointmentsByEventId,
            array_fill_keys(array_keys($cohort), true)
        );

        foreach ($items as $cid => $item) {
            $cid = (int) $cid;
            if ($cid <= 0 || isset($cohort[$cid])) {
                continue;
            }

            $cf = is_array($item['cf'] ?? null) ? $item['cf'] : [];
            $cf = dashComercialHydrateEventosWpLeadRow($conn, $cf);
            $item['cf'] = $cf;
            if ((int) ($cf['tipo_cliente'] ?? 0) !== 1) {
                continue;
            }

            if ($hasFechaFilter) {
                $dateField = dashComercialResolveAgendadosCohortDateField(
                    $cf,
                    $appointmentsByClient,
                    $appointmentsByEventId
                );
                if ($dateField === '') {
                    continue;
                }
                $d = dashComercialExtractDateFromTimestamp($dateField);
                if ($d === null) {
                    continue;
                }
                if ($sd && $d < $sd) {
                    continue;
                }
                if ($ed && $d > $ed) {
                    continue;
                }
            }

            $estatusKey = dashComercialResolveCohortItemEstatusKey($item);
            if ($estatusKey !== 'agendado') {
                continue;
            }

            $item['created_time'] = $hasFechaFilter
                ? dashComercialResolveAgendadosCohortDateField($cf, $appointmentsByClient, $appointmentsByEventId)
                : ($item['created_time'] ?? wpEventosCfResolveDateField($cf));

            $cohort[$cid] = $item;
        }
    }
}

if (!function_exists('dashComercialBuildAgendadosCohort')) {
    /**
     * Cohorte agendados — réplica de consulta_agendados_leads.php:
     * consultaSessionLeadsBuildAllLeads(false) filtrado por fecha en que agendó.
     *
     * @return array<int, array{cf: array, appt: array, estatus: string, created_time: string}>
     */
    function dashComercialBuildAgendadosCohort($conn, $startDate, $endDate, $idVendedora = null)
    {
        static $cache = [];
        $cacheKey = (string) $startDate . "\0" . (string) $endDate;

        if (!isset($cache[$cacheKey])) {
            require_once __DIR__ . '/consulta_session_leads_helper.php';

            $ctx = consultaSessionLeadsBuildAllLeads($conn, false);
            $appointmentsByClient = $ctx['appointmentsByClient'];
            $appointmentsByEventId = $ctx['appointmentsByEventId'];
            $allLeads = $ctx['allLeads'];

            $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
            $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
            $hasFechaFilter = ($sd !== null || $ed !== null);

            $cohort = [];
            foreach ($allLeads as $lead) {
                $cid = (int) ($lead['id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }

                if (dashComercialIsEventosWpContactForm($lead)) {
                    $lead = dashComercialHydrateEventosWpLeadRow($conn, $lead);
                }

                $dateField = dashComercialResolveAgendadosCohortDateField(
                    $lead,
                    $appointmentsByClient,
                    $appointmentsByEventId
                );

                if ($hasFechaFilter) {
                    if ($dateField === '') {
                        continue;
                    }
                    $d = dashComercialExtractDateFromTimestamp($dateField);
                    if ($d === null) {
                        continue;
                    }
                    if ($sd && $d < $sd) {
                        continue;
                    }
                    if ($ed && $d > $ed) {
                        continue;
                    }
                }

                $appt = postLeadResolveAppointmentForLead($lead, $appointmentsByClient, $appointmentsByEventId);

                $cohort[$cid] = [
                    'cf'           => $lead,
                    'appt'         => is_array($appt) ? $appt : [],
                    'estatus'      => (string) ($lead['estatus'] ?? ''),
                    'created_time' => $dateField !== '' ? $dateField : dashComercialLeadArrivalDateField($lead),
                ];
            }

            dashComercialMergeEventosWpIntoAgendadosCohort(
                $conn,
                $cohort,
                $startDate,
                $endDate,
                $appointmentsByClient,
                $appointmentsByEventId
            );

            $cache[$cacheKey] = $cohort;
        }

        return dashComercialFilterCohortByVendedora($conn, $cache[$cacheKey], $idVendedora);
    }
}

if (!function_exists('dashComercialEventosWpCountsAsPostLead')) {
    /**
     * eventos_wp en post-leads / atendidos: atendido o cliente; muerto solo si hubo atendido en historial.
     * Cotizado y pendiente (agendado) pertenecen a consulta_agendados_leads.
     */
    function dashComercialEventosWpCountsAsPostLead(array $item)
    {
        if (!dashComercialIsEventosWpContactForm($item['cf'] ?? [])) {
            return false;
        }

        $estatusKey = dashComercialResolveCohortItemEstatusKey($item);

        return in_array($estatusKey, ['atendido', 'cliente', 'muerto'], true);
    }
}

if (!function_exists('dashComercialHydrateEventosWpLeadRow')) {
    function dashComercialHydrateEventosWpLeadRow($conn, array $lead)
    {
        if (!dashComercialIsEventosWpContactForm($lead)) {
            return $lead;
        }

        $cf = wpEventosCfHydratePlannerContactFormFields($conn, $lead);

        return wpEventosCfEnrichMergedLead($conn, array_merge($lead, $cf), $cf);
    }
}

if (!function_exists('dashComercialBuildPostLeadsCohort')) {
    /**
     * Cohorte post-calificada (réplica de consulta_post_leads.php):
     * historial con estatus 1 (atendido) o 4 (cliente) en calendario_estatus_historial,
     * cierre cliente en contact_form, o eventos_wp atendido/cliente/muerto (muerto solo si hubo atendido).
     *
     * @return array<int, array{cf: array, appt: array|null, estatus: string, created_time: string}>
     */
    function dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate, $idVendedora = null)
    {
        static $cache = [];
        $cacheKey = (string) $startDate . "\0" . (string) $endDate;

        if (!isset($cache[$cacheKey])) {
            $appointmentsByClient = dashComercialGetAppointmentsByClient($conn);
            $appointmentsByEventId = dashComercialGetAppointmentsByEventId($conn);
            $allLeads = [];

            if (!empty($appointmentsByClient)) {
                $idsList = implode(',', array_map('intval', array_keys($appointmentsByClient)));
                $result = $conn->query("SELECT * FROM contact_form WHERE id IN ($idsList)");
                if ($result) {
                    while ($cf = $result->fetch_assoc()) {
                        $tablaOrigenRaw = strtolower(trim($cf['tabla_origen'] ?? ''));
                        if ($tablaOrigenRaw === 'wedding_planners' || $tablaOrigenRaw === 'wedding_planner') {
                            continue;
                        }

                        $cid = (int) ($cf['id'] ?? 0);
                        if ($cid <= 0) {
                            continue;
                        }

                        if (dashComercialIsEventosWpContactForm($cf)) {
                            $cf = wpEventosCfHydratePlannerContactFormFields($conn, $cf);
                        }

                        $appt = dashComercialResolveAppointmentForLead($cf, $appointmentsByClient, $appointmentsByEventId);
                        if ($appt !== null && !dashComercialAppointmentDateIsValid($appt)) {
                            continue;
                        }

                        $estatus = dashComercialResolvePostLeadEstatus($cf, $appt);
                        if ($estatus === 'agendado') {
                            continue;
                        }

                        $howDidYouMeetRaw = trim((string) ($cf['how_did_you_meet'] ?? ''));
                        if (!dashComercialIsEventosWpContactForm($cf) && $howDidYouMeetRaw === '1' && $estatus === 'cliente') {
                            continue;
                        }

                        $formName = $cf['tabla_origen'] ?? '';
                        $origId = (int) ($cf['original_lead_id'] ?? 0);
                        if (dashComercialIsEventosWpContactForm($cf)) {
                            $createdTime = $cf['created_time'] ?? $cf['submission_date'] ?? '';
                        } else {
                            $createdTime = dashComercialResolveLeadCreatedTime($conn, $cf, $formName, $origId);
                        }

                        $item = [
                            'cf'           => $cf,
                            'appt'         => is_array($appt) ? $appt : [],
                            'estatus'      => $estatus,
                            'created_time' => $createdTime,
                        ];
                        if (dashComercialIsEventosWpContactForm($cf)) {
                            $item = dashComercialEnrichEventosWpCohortItem($conn, $item);
                        }
                        $allLeads[$cid] = $item;
                    }
                }
            }

            $wpItems = wpEventosCfBuildDashboardCohortItems(
                $conn,
                $appointmentsByClient,
                $appointmentsByEventId,
                array_fill_keys(array_keys($allLeads), true),
                null,
                ['skip_agendado' => true]
            );
            foreach ($wpItems as $cfId => $item) {
                $allLeads[$cfId] = $item;
            }

            $fechaAtencionByCfId = plannerProfileBatchLoadFechaAtencionByContactFormIds(
                $conn,
                array_map('intval', array_keys($allLeads))
            );
            $fechaMuertoHistorialByCfId = plannerProfileBatchLoadFechaHistorialByContactFormIds(
                $conn,
                array_map('intval', array_keys($allLeads)),
                [3]
            );

            $displayCandidates = [];
            foreach ($allLeads as $cid => $item) {
                $dateField = dashComercialResolvePostLeadDateField(
                    $item,
                    $fechaAtencionByCfId,
                    $appointmentsByClient,
                    $appointmentsByEventId,
                    $fechaMuertoHistorialByCfId
                );
                if (!dashComercialPostLeadDateInRange($dateField, $startDate, $endDate)) {
                    continue;
                }

                $item['created_time'] = $dateField;
                $displayCandidates[$cid] = $item;
            }

            $cohort = [];
            if (!empty($displayCandidates)) {
                $atendidoHistIds = dashComercialLoadAtendidoHistorialClientIds(
                    $conn,
                    array_map('intval', array_keys($displayCandidates))
                );

                foreach ($displayCandidates as $cid => $item) {
                    $estatusKey = dashComercialResolveCohortItemEstatusKey($item);
                    $passedAtendidoHistorial = isset($atendidoHistIds[$cid]);
                    $isCliente = $estatusKey === 'cliente'
                        || (int) ($item['cf']['cliente'] ?? 0) === 1;

                    if (dashComercialIsEventosWpContactForm($item['cf'])) {
                        if (!dashComercialEventosWpCountsAsPostLead($item)) {
                            continue;
                        }
                        if (
                            in_array($estatusKey, ['muerto', 'fantasma'], true)
                            && !$passedAtendidoHistorial
                        ) {
                            continue;
                        }
                        $cohort[$cid] = $item;
                        continue;
                    }

                    if ($passedAtendidoHistorial || $isCliente) {
                        $cohort[$cid] = $item;
                    }
                }
            }

            $cache[$cacheKey] = $cohort;
        }

        return dashComercialFilterCohortByVendedora($conn, $cache[$cacheKey], $idVendedora);
    }
}

if (!function_exists('dashComercialBuildAtendidosCohort')) {
    /**
     * Atendidos: post-leads (incluye estatus cliente) + clientes cerrados no presentes en post-leads.
     *
     * @return array<int, array{cf: array, appt: array|null, estatus: string, created_time: string}>
     */
    function dashComercialBuildAtendidosCohort($conn, $startDate, $endDate, $idVendedora = null)
    {
        $cohort = dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate, $idVendedora);

        foreach (dashComercialBuildClientesCohort($conn, $startDate, $endDate, $idVendedora) as $cid => $item) {
            if (!isset($cohort[$cid])) {
                $cohort[$cid] = $item;
                continue;
            }

            // Priorizar datos de cierre cuando contact_form.cliente = 1
            if (dashComercialResolveCohortItemEstatusKey($item) === 'cliente') {
                $cohort[$cid] = $item;
            }
        }

        return $cohort;
    }
}

if (!function_exists('dashComercialResolveClienteFechaCambioLikeClientes')) {
    /**
     * Misma resolución de fecha_cambio_cliente que clientes.php (contact_form + tabla origen).
     */
    function dashComercialResolveClienteFechaCambioLikeClientes($conn, array $cf)
    {
        $fechaCambio = trim((string) ($cf['fecha_cambio_cliente'] ?? ''));
        $formName = trim((string) ($cf['tabla_origen'] ?? ''));
        $origId = (int) ($cf['original_lead_id'] ?? 0);

        if ($formName === '' || $origId <= 0) {
            return $fechaCambio;
        }

        $escapedForm = $conn->real_escape_string($formName);
        $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
        if (!$checkTable || $checkTable->num_rows === 0) {
            return $fechaCambio;
        }

        $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
        if ($leadRes && $leadRes->num_rows > 0) {
            $leadRow = $leadRes->fetch_assoc();
            if (!empty($leadRow['fecha_cambio_cliente'])) {
                $fechaCambio = trim((string) $leadRow['fecha_cambio_cliente']);
            }
        }

        return $fechaCambio;
    }
}

if (!function_exists('dashComercialBuildClientesCohort')) {
    /**
     * Réplica exacta de clientes.php: contact_form.cliente = 1, excl. wedding_planners,
     * fecha_cambio_cliente (con fallback al lead original) y filtro de fechas en PHP.
     *
     * @return array<int, array{cf: array, appt: array, estatus: string, created_time: string}>
     */
    function dashComercialBuildClientesCohort($conn, $startDate, $endDate, $idVendedora = null)
    {
        static $cache = [];
        $cacheKey = (string) $startDate . "\0" . (string) $endDate;

        if (!isset($cache[$cacheKey])) {
            require_once __DIR__ . '/pltrace_leads_helper.php';

            $appointmentsByClient = dashComercialGetAppointmentsByClient($conn);
            $cohort = [];

            $sql = "SELECT * FROM contact_form WHERE cliente = 1 AND LOWER(COALESCE(tabla_origen, '')) != 'wedding_planners' ORDER BY submission_date DESC";
            $res = $conn->query($sql);
            if ($res) {
                while ($cf = $res->fetch_assoc()) {
                    $tablaOrigenRaw = strtolower(trim($cf['tabla_origen'] ?? ''));
                    if ($tablaOrigenRaw === 'wedding_planners' || $tablaOrigenRaw === 'wedding_planner') {
                        continue;
                    }

                    $item = dashComercialBuildFullClienteCohortItem($conn, $cf, $appointmentsByClient);
                    if ($item === null) {
                        continue;
                    }

                    if (!pltraceClienteDateInRange($item['created_time'], $startDate, $endDate)) {
                        continue;
                    }

                    $cfId = (int) ($cf['id'] ?? 0);
                    $cohort[$cfId] = $item;
                }
            }

            $cache[$cacheKey] = $cohort;
        }

        if ($idVendedora === null) {
            return $cache[$cacheKey];
        }

        $filtered = [];
        $appointmentsByEventId = dashComercialGetAppointmentsByEventId($conn);
        foreach ($cache[$cacheKey] as $cid => $item) {
            if (dashComercialResolveCohortItemVendorId($conn, $item, $appointmentsByEventId) === (int) $idVendedora) {
                $filtered[$cid] = $item;
            }
        }

        return $filtered;
    }
}

if (!function_exists('dashComercialLoadLeadContextForTipoCliente')) {
    function dashComercialLoadLeadContextForTipoCliente($conn, array $cf)
    {
        $context = $cf;
        $cfId = (int) ($cf['id'] ?? 0);
        if ($cfId > 0 && (!isset($context['how_did_you_meet']) || trim((string) $context['how_did_you_meet']) === '')) {
            $cfRes = $conn->query("SELECT tipo_cliente, how_did_you_meet FROM contact_form WHERE id = $cfId LIMIT 1");
            if ($cfRes && $cfRes->num_rows > 0) {
                $cfRow = $cfRes->fetch_assoc();
                foreach (['tipo_cliente', 'how_did_you_meet'] as $field) {
                    if (!isset($context[$field]) || trim((string) $context[$field]) === '') {
                        $context[$field] = $cfRow[$field] ?? '';
                    }
                }
            }
        }

        $formName = trim((string) ($cf['tabla_origen'] ?? ''));
        $origId = (int) ($cf['original_lead_id'] ?? 0);
        if ($formName === '' || $origId <= 0) {
            return $context;
        }

        $escapedForm = $conn->real_escape_string($formName);
        $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
        if (!$checkTable || $checkTable->num_rows === 0) {
            return $context;
        }

        $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
        if (!$leadRes || $leadRes->num_rows === 0) {
            return $context;
        }

        $leadRow = $leadRes->fetch_assoc();
        foreach ($leadRow as $key => $value) {
            if (!isset($context[$key]) || $context[$key] === '' || $context[$key] === null) {
                $context[$key] = $value;
            }
        }

        return $context;
    }
}

if (!function_exists('dashComercialResolveTipoClienteLabel')) {
    function dashComercialResolveTipoClienteLabel($conn, array $item)
    {
        require_once __DIR__ . '/lead_origin_helper.php';

        $cf = $item['cf'] ?? [];
        if (dashComercialIsEventosWpContactForm($cf)) {
            return 'Wedding Planner';
        }

        $lead = dashComercialLoadLeadContextForTipoCliente($conn, $cf);
        $howDidYouMeet = trim((string) ($lead['how_did_you_meet_raw'] ?? $lead['how_did_you_meet'] ?? ''));

        if (mapTipoClienteValueForOrigin($lead['tipo_cliente'] ?? '') === 'Wedding Planner') {
            return 'Wedding Planner';
        }

        $tablaOrigen = strtolower(trim((string) ($lead['tabla_origen'] ?? '')));
        if (in_array($tablaOrigen, ['wedding_planners', 'wedding_planner', 'wp_citas_leads'], true)) {
            return 'Wedding Planner';
        }

        if ($howDidYouMeet === '1') {
            return 'Wedding Planner';
        }

        if (in_array($tablaOrigen, ['eventos_wp', 'wp_eventos_afianzados'], true)) {
            return 'Wedding Planner';
        }

        if (mapTipoClienteValueForOrigin($lead['tipo_cliente'] ?? '') === 'Cliente Final') {
            return 'Cliente Final';
        }

        if (in_array($howDidYouMeet, ['2', '3'], true)) {
            return 'Cliente Final';
        }

        return '—';
    }
}

if (!function_exists('dashComercialCohortItemToLeadRow')) {
    /** Fila unificada contact_form + estatus de cohorte (como en las vistas de consulta). */
    function dashComercialCohortItemToLeadRow(array $item)
    {
        $cf = is_array($item['cf'] ?? null) ? $item['cf'] : [];

        return array_merge($cf, [
            'id'      => (int) ($cf['id'] ?? 0),
            'estatus' => (string) ($item['estatus'] ?? ($cf['estatus'] ?? '')),
        ]);
    }
}

if (!function_exists('dashComercialResolveCohortItemAppointment')) {
    function dashComercialResolveCohortItemAppointment(
        array $item,
        array $appointmentsByClient = [],
        array $appointmentsByEventId = []
    ) {
        if (is_array($item['appt'] ?? null) && !empty($item['appt'])) {
            return $item['appt'];
        }

        $cf = is_array($item['cf'] ?? null) ? $item['cf'] : [];
        if ($cf === []) {
            return null;
        }
        if (!function_exists('postLeadResolveAppointmentForLead')) {
            require_once __DIR__ . '/consulta_session_leads_helper.php';
        }
        if (!function_exists('postLeadResolveAppointmentForLead')) {
            return null;
        }

        $resolved = postLeadResolveAppointmentForLead($cf, $appointmentsByClient, $appointmentsByEventId);

        return is_array($resolved) ? $resolved : null;
    }
}

if (!function_exists('dashComercialBatchLoadFechaAtencionByContactFormIds')) {
    /**
     * Primera fecha en que la cita alcanzó estatus atendido (1), por contact_form.id.
     *
     * @param int[] $contactFormIds
     * @return array<int, string>
     */
    function dashComercialBatchLoadFechaAtencionByContactFormIds($conn, array $contactFormIds)
    {
        return plannerProfileBatchLoadFechaAtencionByContactFormIds($conn, $contactFormIds);
    }
}

if (!function_exists('dashComercialResolveDisplayFechaAgendaRaw')) {
    /**
     * Fecha agenda — alineada con consulta_agendados_leads («¿Cuándo agendó?»).
     * Cliente final: calendario.fecha_registro → contact_form.date_appointment → calendario.fecha.
     * Wedding Planner: eventos_wp.fecha_agendado → fecha_cotizado → misma cadena calendario.
     */
    function dashComercialResolveDisplayFechaAgendaRaw(
        $conn,
        array $item,
        array $appointmentsByClient = [],
        array $appointmentsByEventId = []
    ) {
        $merged = dashComercialCohortItemToLeadRow($item);
        $appt = dashComercialResolveCohortItemAppointment($item, $appointmentsByClient, $appointmentsByEventId);

        $agendaRaw = dashComercialResolveAgendadosCohortDateField(
            $merged,
            $appointmentsByClient,
            $appointmentsByEventId
        );
        if ($agendaRaw === '') {
            $agendaRaw = plannerProfileResolveClienteFinalAgendaDate($merged, $appt);
        }
        if ($agendaRaw === '' && dashComercialIsEventosWpContactForm($merged)) {
            $agendaRaw = plannerProfileResolveEventAgendaDate($merged);
        }

        $agendaRaw = trim((string) $agendaRaw);

        return ($agendaRaw !== '' && strpos($agendaRaw, '0000-00-00') !== 0) ? $agendaRaw : '';
    }
}

if (!function_exists('dashComercialLeadHasAtencionHistorial')) {
    function dashComercialLeadHasAtencionHistorial(array $item, array $fechaAtencionByContactFormId = [])
    {
        $merged = dashComercialCohortItemToLeadRow($item);
        $cfId = (int) ($merged['id'] ?? 0);

        return $cfId > 0 && !empty($fechaAtencionByContactFormId[$cfId]);
    }
}

if (!function_exists('dashComercialLeadQualifiesForDisplayFechaAtencion')) {
    /**
     * Solo mostrar fecha atendido cuando el lead ya salió de «agendado» puro.
     */
    function dashComercialLeadQualifiesForDisplayFechaAtencion(array $item, array $fechaAtencionByContactFormId = [])
    {
        $merged = dashComercialCohortItemToLeadRow($item);
        $cfId = (int) ($merged['id'] ?? 0);

        if (dashComercialIsEventosWpContactForm($merged)) {
            return true;
        }

        $estatusKey = dashComercialResolveCohortItemEstatusKey($item);
        if ($estatusKey === 'atendido') {
            return $cfId > 0 && !empty($fechaAtencionByContactFormId[$cfId]);
        }

        if ($estatusKey === 'agendado') {
            return false;
        }
        if ($estatusKey === 'cliente') {
            return $cfId > 0 && !empty($fechaAtencionByContactFormId[$cfId]);
        }
        if ($estatusKey === 'muerto') {
            return true;
        }

        return dashComercialLeadHasAtencionHistorial($item, $fechaAtencionByContactFormId);
    }
}

if (!function_exists('dashComercialResolveAttendedHistorialOnlyCandidateRaw')) {
    /**
     * Fecha atendido registrada (historial estatus=1 o eventos_wp), sin proxies ni fecha muerto.
     */
    function dashComercialResolveAttendedHistorialOnlyCandidateRaw(
        $conn,
        array $item,
        array $fechaAtencionByContactFormId = []
    ) {
        $merged = dashComercialCohortItemToLeadRow($item);

        if (dashComercialIsEventosWpContactForm($merged)) {
            if (!function_exists('wpEventResolveFechaAtendido')) {
                require_once __DIR__ . '/evento_wp_post_helper.php';
            }

            $fromRow = trim((string) wpEventResolveFechaAtendido($merged, false));
            if ($fromRow !== '' && strpos($fromRow, '0000-00-00') !== 0) {
                return $fromRow;
            }

            $fromPlanner = trim((string) plannerProfileResolveEventAttendedDate($merged));
            if ($fromPlanner !== '' && strpos($fromPlanner, '0000-00-00') !== 0) {
                return $fromPlanner;
            }

            $eventId = (int) ($merged['original_lead_id'] ?? 0);
            if ($eventId > 0) {
                $stmt = $conn->prepare('SELECT fecha_atendido FROM eventos_wp WHERE id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $eventId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $eventRow = $result ? $result->fetch_assoc() : null;
                    $stmt->close();
                    $fecha = trim((string) ($eventRow['fecha_atendido'] ?? ''));
                    if ($fecha !== '' && strpos($fecha, '0000-00-00') !== 0) {
                        return $fecha;
                    }
                }
            }

            return '';
        }

        $cfId = (int) ($merged['id'] ?? 0);
        if ($cfId > 0 && !empty($fechaAtencionByContactFormId[$cfId])) {
            $fecha = trim((string) $fechaAtencionByContactFormId[$cfId]);
            if ($fecha !== '' && strpos($fecha, '0000-00-00') !== 0) {
                return $fecha;
            }
        }

        return '';
    }
}

if (!function_exists('dashComercialResolveDisplayFechaAtencionCandidateRaw')) {
    function dashComercialResolveDisplayFechaAtencionCandidateRaw(
        $conn,
        array $item,
        array $fechaAtencionByContactFormId = [],
        array $fechaMuertoHistorialByContactFormId = []
    ) {
        unset($fechaMuertoHistorialByContactFormId);

        $merged = dashComercialCohortItemToLeadRow($item);
        if (!dashComercialIsEventosWpContactForm($merged)) {
            $cfId = (int) ($merged['id'] ?? 0);
            if ($cfId > 0 && !empty($fechaAtencionByContactFormId[$cfId])) {
                return trim((string) $fechaAtencionByContactFormId[$cfId]);
            }

            return '';
        }

        $candidate = dashComercialResolveAttendedHistorialOnlyCandidateRaw(
            $conn,
            $item,
            $fechaAtencionByContactFormId
        );
        if ($candidate !== '') {
            return $candidate;
        }

        return '';
    }
}

if (!function_exists('dashComercialResolveDisplayFechaAtencionRaw')) {
    /**
     * Fecha atendido:
     * - Wedding Planner: eventos_wp.fecha_atendido
     * - Cliente final: calendario_estatus_historial.fecha_cambio (estatus=1)
     */
    function dashComercialResolveDisplayFechaAtencionRaw(
        $conn,
        array $item,
        array $fechaAtencionByContactFormId = [],
        array $appointmentsByClient = [],
        array $appointmentsByEventId = [],
        array $fechaMuertoHistorialByContactFormId = []
    ) {
        unset($appointmentsByClient, $appointmentsByEventId);

        if (!dashComercialLeadQualifiesForDisplayFechaAtencion($item, $fechaAtencionByContactFormId)) {
            return '';
        }

        $candidate = dashComercialResolveDisplayFechaAtencionCandidateRaw(
            $conn,
            $item,
            $fechaAtencionByContactFormId,
            $fechaMuertoHistorialByContactFormId
        );

        return dashComercialSanitizeDisplayFechaAtencionRaw($candidate);
    }
}

if (!function_exists('dashComercialResolveDisplayFechaAtencionPendingReason')) {
    function dashComercialResolveDisplayFechaAtencionPendingReason(
        $conn,
        array $item,
        array $fechaAtencionByContactFormId = []
    ) {
        if (!dashComercialLeadQualifiesForDisplayFechaAtencion($item, $fechaAtencionByContactFormId)) {
            return null;
        }

        $candidate = dashComercialResolveAttendedHistorialOnlyCandidateRaw(
            $conn,
            $item,
            $fechaAtencionByContactFormId
        );
        if ($candidate === '' || !dashComercialIsAttendedDateBeforeMinPeriod($candidate)) {
            return null;
        }

        return 'before_min_period';
    }
}

if (!function_exists('dashComercialResolveDisplayFechaCierreRaw')) {
    /**
     * Fecha cierre — clientes.php / eventos_wp.fecha_cliente para WP.
     */
    function dashComercialResolveDisplayFechaCierreRaw($conn, array $item)
    {
        $merged = dashComercialCohortItemToLeadRow($item);
        $estatusKey = dashComercialResolveCohortItemEstatusKey($item);
        $isCliente = $estatusKey === 'cliente' || (int) ($merged['cliente'] ?? 0) === 1;
        if (!$isCliente) {
            return '';
        }

        if (dashComercialIsEventosWpContactForm($merged)) {
            $fechaCliente = trim((string) plannerProfileResolveEventClienteDate($merged));
            if ($fechaCliente !== '' && strpos($fechaCliente, '0000-00-00') !== 0) {
                return $fechaCliente;
            }
        }

        $fechaCambio = plannerProfileResolveClienteFinalClienteDate($merged);
        if ($fechaCambio === '') {
            $fechaCambio = dashComercialResolveClienteFechaCambioLikeClientes($conn, $merged);
        }

        $fechaCambio = trim((string) $fechaCambio);

        return ($fechaCambio !== '' && strpos($fechaCambio, '0000-00-00') !== 0) ? $fechaCambio : '';
    }
}

if (!function_exists('dashComercialApplyModalMilestoneDateVisibility')) {
    /** Oculta columnas de fecha que no corresponden a la sección del modal. */
    function dashComercialApplyModalMilestoneDateVisibility($milestoneGroup, $fechaAgendaRaw, $fechaAtencionRaw, $fechaCierreRaw)
    {
        $group = strtolower(trim((string) $milestoneGroup));

        if ($group === 'agenda') {
            return [$fechaAgendaRaw, '', ''];
        }
        if ($group === 'atencion') {
            return [$fechaAgendaRaw, $fechaAtencionRaw, ''];
        }

        return [$fechaAgendaRaw, $fechaAtencionRaw, $fechaCierreRaw];
    }
}

if (!function_exists('dashComercialCohortToDisplayRows')) {
    function dashComercialCohortToDisplayRows($conn, array $cohort, $milestoneGroup = null)
    {
        $vendorMap = dashComercialBuildVendorMap($conn);
        $rows = [];
        $contactFormIds = [];

        foreach ($cohort as $cid => $item) {
            $cf = is_array($item['cf'] ?? null) ? $item['cf'] : [];
            $cfId = (int) ($cf['id'] ?? $cid);
            if ($cfId > 0) {
                $contactFormIds[] = $cfId;
            }
        }

        $fechaAtencionByContactFormIdFull = dashComercialBatchLoadFechaAtencionByContactFormIds($conn, $contactFormIds);
        $fechaMuertoHistorialByContactFormId = plannerProfileBatchLoadFechaHistorialByContactFormIds($conn, $contactFormIds, [3]);
        $appointmentsByClient = dashComercialGetAppointmentsByClient($conn);
        $appointmentsByEventId = dashComercialGetAppointmentsByEventId($conn);

        foreach ($cohort as $cid => $item) {
            $cf = $item['cf'];
            $appt = $item['appt'];
            $estatusKey = dashComercialResolveCohortItemEstatusKey($item);
            $createdTime = $item['created_time'];
            $formName = $cf['tabla_origen'] ?? '';
            $idusu = dashComercialResolveCohortItemVendorId($conn, $item);
            $tipoCliente = dashComercialResolveTipoClienteLabel($conn, $item);
            $fechaAgendaRaw = dashComercialResolveDisplayFechaAgendaRaw(
                $conn,
                $item,
                $appointmentsByClient,
                $appointmentsByEventId
            );
            $fechaAtencionRaw = dashComercialResolveDisplayFechaAtencionRaw(
                $conn,
                $item,
                $fechaAtencionByContactFormIdFull,
                $appointmentsByClient,
                $appointmentsByEventId,
                $fechaMuertoHistorialByContactFormId
            );
            $fechaCierreRaw = dashComercialResolveDisplayFechaCierreRaw($conn, $item);

            list($fechaAgendaRaw, $fechaAtencionRaw, $fechaCierreRaw) = dashComercialApplyModalMilestoneDateVisibility(
                $milestoneGroup,
                $fechaAgendaRaw,
                $fechaAtencionRaw,
                $fechaCierreRaw
            );

            $milestoneKey = strtolower(trim((string) $milestoneGroup));
            $fechaAtencionPendingNote = null;
            if (
                $fechaAtencionRaw === ''
                && $milestoneKey !== 'agenda'
                && dashComercialLeadQualifiesForDisplayFechaAtencion($item, $fechaAtencionByContactFormIdFull)
            ) {
                $fechaAtencionPendingNote = dashComercialGetAttendedDateBeforeMinPeriodLegend();
            }
            $fechaCierreShowPending = $fechaCierreRaw === '';

            $rows[] = [
                'id'        => (int) ($cf['id'] ?? $cid),
                'nombre'    => dashComercialResolveLeadName($cf),
                'email'     => dashComercialResolveLeadEmail($cf),
                'telefono'  => dashComercialResolveLeadPhone($cf),
                'fecha'     => dashComercialFormatDisplayDate($createdTime),
                'fecha_raw' => $createdTime,
                'fecha_agenda'   => $fechaAgendaRaw !== ''
                    ? dashComercialFormatDisplayDateModal($fechaAgendaRaw)
                    : '',
                'fecha_atencion' => $fechaAtencionRaw !== ''
                    ? dashComercialFormatDisplayDateModal($fechaAtencionRaw)
                    : '',
                'fecha_atencion_pending_note' => $fechaAtencionPendingNote,
                'fecha_cierre'   => $fechaCierreRaw !== ''
                    ? dashComercialFormatDisplayDateModal($fechaCierreRaw)
                    : '',
                'fecha_cierre_show_pending' => $fechaCierreShowPending,
                'vendedora' => dashComercialResolveUsuarioDisplayNameById($conn, $idusu, $vendorMap),
                'origen'    => trim((string) $formName) ?: '—',
                'tipo_cliente' => $tipoCliente,
                'estatus'   => getLeadStatusDisplayLabel($estatusKey),
                'estatus_key' => $estatusKey,
                'cita'      => (is_array($appt) && trim((string) ($appt['fecha'] ?? '')) !== '')
                    ? dashComercialFormatDisplayDate(trim(($appt['fecha'] ?? '') . ' ' . ($appt['hora'] ?? '')))
                    : '—',
            ];
        }

        usort($rows, function ($a, $b) {
            return strcmp((string) ($b['fecha_raw'] ?? ''), (string) ($a['fecha_raw'] ?? ''));
        });

        return $rows;
    }
}

if (!function_exists('dashComercialMergeDisplayRowMilestoneDates')) {
    function dashComercialMergeDisplayRowMilestoneDates(array $existing, array $incoming)
    {
        $merged = array_merge($existing, $incoming);

        foreach (['fecha_agenda', 'fecha_atencion', 'fecha_cierre'] as $field) {
            $incomingVal = trim((string) ($incoming[$field] ?? ''));
            $existingVal = trim((string) ($existing[$field] ?? ''));

            if ($incomingVal !== '' && $incomingVal !== '—') {
                $merged[$field] = $incoming[$field];
                continue;
            }
            if ($existingVal !== '' && $existingVal !== '—') {
                $merged[$field] = $existing[$field];
            }
        }

        if (!empty($incoming['fecha_atencion_pending_note'])) {
            $merged['fecha_atencion_pending_note'] = $incoming['fecha_atencion_pending_note'];
        } elseif (!empty($existing['fecha_atencion_pending_note'])) {
            $merged['fecha_atencion_pending_note'] = $existing['fecha_atencion_pending_note'];
        }

        if (array_key_exists('fecha_cierre_show_pending', $incoming)) {
            $merged['fecha_cierre_show_pending'] = $incoming['fecha_cierre_show_pending'];
        } elseif (array_key_exists('fecha_cierre_show_pending', $existing)) {
            $merged['fecha_cierre_show_pending'] = $existing['fecha_cierre_show_pending'];
        }

        return $merged;
    }
}

if (!function_exists('dashComercialMergeDisplayRowsByRecordId')) {
    /**
     * Une filas del modal priorizando la etapa más avanzada: clientes > atendidos > agendas.
     *
     * @param array<int, array<string, mixed>> ...$rowSets en orden agendas, atendidos, clientes
     */
    function dashComercialMergeDisplayRowsByRecordId(array ...$rowSets)
    {
        $byKey = [];

        foreach ($rowSets as $rows) {
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $key = 'id:' . $id;
                    if (isset($byKey[$key])) {
                        $byKey[$key] = dashComercialMergeDisplayRowMilestoneDates($byKey[$key], $row);
                    } else {
                        $byKey[$key] = $row;
                    }
                    continue;
                }

                $altKey = 'alt:'
                    . mb_strtolower(trim((string) ($row['email'] ?? '')), 'UTF-8') . '|'
                    . mb_strtolower(trim((string) ($row['telefono'] ?? '')), 'UTF-8') . '|'
                    . mb_strtolower(trim((string) ($row['nombre'] ?? '')), 'UTF-8');
                if ($altKey !== 'alt:||') {
                    if (isset($byKey[$altKey])) {
                        $byKey[$altKey] = dashComercialMergeDisplayRowMilestoneDates($byKey[$altKey], $row);
                    } else {
                        $byKey[$altKey] = $row;
                    }
                }
            }
        }

        $merged = array_values($byKey);
        usort($merged, function ($a, $b) {
            return strcmp((string) ($b['fecha_raw'] ?? ''), (string) ($a['fecha_raw'] ?? ''));
        });

        return $merged;
    }
}

if (!function_exists('dashComercialFetchAgendaCohortRows')) {
    /** Cohorte de agendas (misma lista que consulta_agendados_leads.php). */
    function dashComercialFetchAgendaCohortRows($conn, $startDate, $endDate, $idVendedora = null)
    {
        $cohort = dashComercialBuildAgendadosCohort($conn, $startDate, $endDate, $idVendedora);

        return dashComercialCohortToDisplayRows($conn, $cohort, 'agenda');
    }
}

if (!function_exists('dashComercialCountAgendas')) {
    /**
     * Sesiones agendadas (consulta_agendados_leads.php / leadsCountFiltered).
     */
    function dashComercialCountAgendas($conn, $startDate, $endDate, $idVendedora = null)
    {
        return count(dashComercialBuildAgendadosCohort($conn, $startDate, $endDate, $idVendedora));
    }
}

if (!function_exists('dashComercialCountAtendidos')) {
    /**
     * Sesiones atendidas: post-leads con historial estatus 1 (cliente final) o eventos_wp.
     */
    function dashComercialCountAtendidos($conn, $startDate, $endDate, $idVendedora = null)
    {
        return count(dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate, $idVendedora));
    }
}

if (!function_exists('dashComercialCountClientes')) {
    /**
     * Clientes cerrados: contact_form.cliente = 1 (fecha_cambio_cliente en periodo).
     */
    function dashComercialCountClientes($conn, $startDate, $endDate, $idVendedora = null)
    {
        return count(dashComercialBuildClientesCohort($conn, $startDate, $endDate, $idVendedora));
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

        try {
            $stmt = $conn->prepare($sql);
        } catch (\Throwable $e) {
            error_log('dashComercialCountEstatusCalendario prepare error: ' . $e->getMessage());
            return 0;
        }
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

if (!function_exists('dashComercialBuildTipoClienteBreakdown')) {
    /**
     * @return array{total: int, wedding_planner: int, cliente_final: int}
     */
    function dashComercialBuildTipoClienteBreakdown($conn, array $cohort)
    {
        $counts = [
            'total'           => count($cohort),
            'wedding_planner' => 0,
            'cliente_final'   => 0,
        ];

        foreach ($cohort as $item) {
            $label = dashComercialResolveTipoClienteLabel($conn, $item);
            if ($label === 'Wedding Planner') {
                $counts['wedding_planner']++;
            } elseif ($label === 'Cliente Final') {
                $counts['cliente_final']++;
            }
        }

        return $counts;
    }
}

if (!function_exists('dashComercialBuildCierreTipoDesgloseGroups')) {
    /**
     * @return array<int, array{label: string, breakdown: array{total: int, wedding_planner: int, cliente_final: int}}>
     */
    function dashComercialBuildCierreTipoDesgloseGroups($conn, array $clientesCohort, array $atendidosCohort)
    {
        return [
            ['label' => 'Clientes', 'breakdown' => dashComercialBuildTipoClienteBreakdown($conn, $clientesCohort)],
            ['label' => 'Atendidos', 'breakdown' => dashComercialBuildTipoClienteBreakdown($conn, $atendidosCohort)],
        ];
    }
}

if (!function_exists('dashComercialBuildAtencionTipoDesgloseGroups')) {
    /**
     * @return array<int, array{label: string, breakdown: array{total: int, wedding_planner: int, cliente_final: int}}>
     */
    function dashComercialBuildAtencionTipoDesgloseGroups($conn, array $atendidosCohort)
    {
        return [
            ['label' => 'Atendidos', 'breakdown' => dashComercialBuildTipoClienteBreakdown($conn, $atendidosCohort)],
        ];
    }
}

if (!function_exists('dashComercialRenderTipoDesgloseHtml')) {
    /**
     * @param array<int, array{label: string, breakdown: array{total: int, wedding_planner: int, cliente_final: int}}> $groups
     */
    function dashComercialRenderTipoDesgloseHtml(array $groups, $extraClass = '')
    {
        if (empty($groups)) {
            return '';
        }

        $class = 'kpi-tipo-breakdown';
        $extraClass = trim((string) $extraClass);
        if ($extraClass !== '') {
            $class .= ' ' . $extraClass;
        }

        $html = '<div class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">';
        foreach ($groups as $group) {
            $label = htmlspecialchars((string) ($group['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $bd = $group['breakdown'] ?? [];
            $total = (int) ($bd['total'] ?? 0);
            $wp = (int) ($bd['wedding_planner'] ?? 0);
            $cf = (int) ($bd['cliente_final'] ?? 0);

            $html .= '<div class="kpi-tipo-group">';
            $html .= '<div class="kpi-tipo-group-title">' . $label . ': <strong>' . number_format($total) . '</strong></div>';
            $html .= '<ul class="kpi-tipo-list">';
            $html .= '<li><span class="kpi-tipo-dot planner" aria-hidden="true"></span> Wedding Planner: <strong>' . number_format($wp) . '</strong></li>';
            $html .= '<li><span class="kpi-tipo-dot final" aria-hidden="true"></span> Cliente Final: <strong>' . number_format($cf) . '</strong></li>';
            $html .= '</ul></div>';
        }
        $html .= '</div>';

        return $html;
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

if (!function_exists('dashComercialResolveUsuarioNombreCompleto')) {
    function dashComercialResolveUsuarioNombreCompleto(array $row)
    {
        $parts = [
            trim((string) ($row['nombre'] ?? '')),
            trim((string) ($row['apepat'] ?? $row['apePat'] ?? '')),
            trim((string) ($row['apemat'] ?? $row['apeMat'] ?? '')),
        ];
        $full = trim(implode(' ', array_filter($parts, static function ($part) {
            return $part !== '';
        })));
        if ($full !== '') {
            return $full;
        }

        return trim((string) ($row['usuario'] ?? ''));
    }
}

if (!function_exists('dashComercialBuildUsuariosById')) {
    function dashComercialBuildUsuariosById($conn)
    {
        static $byId = null;
        if (is_array($byId)) {
            return $byId;
        }

        $byId = [];
        if ($conn) {
            $res = $conn->query('SELECT * FROM usuarios');
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $id = (int) ($row['id'] ?? 0);
                    if ($id > 0) {
                        $byId[$id] = $row;
                    }
                }
            }
        }

        return $byId;
    }
}

if (!function_exists('dashComercialGetVendedoras')) {
    function dashComercialGetVendedoras($conn)
    {
        $list = [];
        $tiposSql = usuarioSqlInTiposAsesorEventoWp();
        $res = $conn->query("SELECT id, nombre, apepat FROM usuarios WHERE tipoUsu IN ($tiposSql) ORDER BY nombre ASC, apepat ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = intval($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $nombre = dashComercialResolveUsuarioNombreCompleto($row);
                $list[] = [
                    'id'     => $id,
                    'nombre' => $nombre !== '' ? $nombre : ('Vendedora #' . $id),
                ];
            }
        }
        return $list;
    }
}

if (!function_exists('dashComercialResolveUsuarioDisplayNameById')) {
    /**
     * Nombre visible de cualquier usuario asignado, sin filtrar por tipoUsu.
     */
    function dashComercialResolveUsuarioDisplayNameById($conn, int $userId, ?array $vendorMap = null)
    {
        if ($userId === 99) {
            return 'Agente de llamada (IA)';
        }
        if ($userId === 100) {
            return 'Agente de whatsapp (IA)';
        }
        if ($userId <= 0) {
            return '—';
        }

        if (!is_array($vendorMap)) {
            $vendorMap = dashComercialBuildVendorMap($conn);
        }

        $usuariosById = dashComercialBuildUsuariosById($conn);
        if (isset($usuariosById[$userId])) {
            $fromRow = dashComercialResolveUsuarioNombreCompleto($usuariosById[$userId]);
            if ($fromRow !== '') {
                return $fromRow;
            }
        }

        $label = trim((string) ($vendorMap[$userId] ?? ''));
        if ($label !== '' && !preg_match('/^Vendedora #\d+$/', $label)) {
            return $label;
        }

        if (function_exists('wpEventosCfResolveAsesorDisplayName')) {
            $resolved = wpEventosCfResolveAsesorDisplayName($conn, $userId);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        if ($conn) {
            try {
                $stmt = $conn->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $stmt->close();
                    $direct = dashComercialResolveUsuarioNombreCompleto(is_array($row) ? $row : []);
                    if ($direct !== '') {
                        return $direct;
                    }
                }
            } catch (\Throwable $e) {
                error_log('dashComercialResolveUsuarioDisplayNameById error for user #' . $userId . ': ' . $e->getMessage());
            }
        }

        return $label !== '' ? $label : ('Vendedora #' . $userId);
    }
}

if (!function_exists('dashComercialComputeMetrics')) {
    function dashComercialComputeMetrics($conn, $startDate, $endDate)
    {
        ensureCalendarioEstatusHistorialTable($conn);

        $totalLeads     = dashComercialCountTotalLeads($conn, $startDate, $endDate);
        $totalAgendas   = dashComercialCountAgendas($conn, $startDate, $endDate);

        $atendidosCohort = dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate);
        $clientesCohort = dashComercialBuildClientesCohort($conn, $startDate, $endDate);
        $totalAtendidos = count($atendidosCohort);
        $totalClientes  = count($clientesCohort);

        $cierreTipoDesglose   = dashComercialBuildCierreTipoDesgloseGroups($conn, $clientesCohort, $atendidosCohort);
        $atencionTipoDesglose = dashComercialBuildAtencionTipoDesgloseGroups($conn, $atendidosCohort);

        $global = [
            'calificacion' => dashComercialBuildKpi($totalAgendas, $totalLeads, 'Agendas', 'Leads'),
            'atencion'     => array_merge(
                dashComercialBuildKpi($totalAtendidos, $totalAgendas, 'Atendidos', 'Agendas'),
                ['tipo_desglose' => $atencionTipoDesglose]
            ),
            'cierre'       => array_merge(
                dashComercialBuildKpi($totalClientes, $totalAtendidos, 'Clientes cerrados', 'Atendidos'),
                ['tipo_desglose' => $cierreTipoDesglose]
            ),
        ];

        $vendedoras = [];
        foreach (dashComercialGetVendedoras($conn) as $v) {
            $vid = $v['id'];
            $leadsV = dashComercialCountLeadsAsignadosVendedora($conn, $vid, $startDate, $endDate);
            $agendasV = dashComercialCountAgendas($conn, $startDate, $endDate, $vid);

            $atendidosVCohort = dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate, $vid);
            $clientesVCohort = dashComercialBuildClientesCohort($conn, $startDate, $endDate, $vid);
            $atendV    = count($atendidosVCohort);
            $clientesV = count($clientesVCohort);

            $vendedoras[] = [
                'id'     => $vid,
                'nombre' => $v['nombre'],
                'calificacion' => dashComercialBuildKpi($agendasV, $leadsV, 'Agendas', 'Leads asignados'),
                'atencion'     => dashComercialBuildKpi($atendV, $agendasV, 'Atendidos', 'Agendas'),
                'cierre'       => array_merge(
                    dashComercialBuildKpi($clientesV, $atendV, 'Clientes cerrados', 'Atendidos'),
                    ['tipo_desglose' => dashComercialBuildCierreTipoDesgloseGroups($conn, $clientesVCohort, $atendidosVCohort)]
                ),
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
    /**
     * Mapa id => nombre para mostrar vendedor/a asignado/a.
     * Incluye todos los usuarios (cualquier tipoUsu) porque un lead puede
     * quedar asignado a cuentas fuera del rol vendedora.
     */
    function dashComercialBuildVendorMap($conn)
    {
        $map = [];
        foreach (dashComercialBuildUsuariosById($conn) as $id => $row) {
            $nombre = dashComercialResolveUsuarioNombreCompleto($row);
            if ($nombre !== '') {
                $map[$id] = $nombre;
            }
        }

        return $map;
    }
}

if (!function_exists('dashComercialResolveLeadVendorLabel')) {
    function dashComercialResolveLeadVendorLabel($conn, array $lead, ?array $appt = null, ?array $vendorMap = null, ?array $appointmentsByEventId = null)
    {
        if (!is_array($vendorMap)) {
            $vendorMap = dashComercialBuildVendorMap($conn);
        }

        $vendorId = dashComercialResolveLeadVendorId($conn, $lead, $appt, $appointmentsByEventId);

        return dashComercialResolveUsuarioDisplayNameById($conn, (int) $vendorId, $vendorMap);
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
        return date('d/m/Y', $ts);
    }
}

if (!function_exists('dashComercialFormatDisplayDateModal')) {
    /** Formato compacto del modal: 05/Jun/26 */
    function dashComercialFormatDisplayDateModal($raw)
    {
        if ($raw === null || $raw === '') {
            return '—';
        }
        $ts = strtotime((string) $raw);
        if ($ts === false || $ts <= 0) {
            return '—';
        }

        static $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $month = $months[(int) date('n', $ts) - 1] ?? date('m', $ts);
        $month = ucfirst($month);

        return date('d', $ts) . '/' . $month . '/' . date('y', $ts);
    }
}

if (!function_exists('dashComercialResolveLeadName')) {
    function dashComercialResolveLeadName(array $row)
    {
        if (wpEventosCfIsContactForm($row) || strcasecmp((string) ($row['tabla_origen'] ?? ''), 'eventos_wp') === 0) {
            $novios = trim((string) ($row['novios'] ?? ''));
            if ($novios === '') {
                foreach (['names', 'full_name'] as $key) {
                    $val = trim((string) ($row[$key] ?? ''));
                    if ($val !== '') {
                        $novios = $val;
                        break;
                    }
                }
            }
            $planner = wpEventosCfResolvePlannerDisplayName($row);
            if ($novios !== '' && $planner !== '' && strcasecmp($novios, $planner) !== 0) {
                return $novios . ' (WP: ' . $planner . ')';
            }
            if ($novios !== '') {
                return $novios;
            }
            if ($planner !== '') {
                return $planner;
            }
        }

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
                    'vendedora'     => dashComercialResolveUsuarioDisplayNameById($conn, $idusu, $vendorMap),
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

if (!function_exists('dashComercialResolveDisplayRowMilestoneRank')) {
    /** Orden de visualización: clientes, atendidos, agendados. */
    function dashComercialResolveDisplayRowMilestoneRank(array $row)
    {
        $key = strtolower(trim((string) ($row['estatus_key'] ?? $row['estatus'] ?? '')));

        if ($key === 'cliente') {
            return 1;
        }
        if (in_array($key, ['atendido', 'muerto'], true)) {
            return 2;
        }
        if (in_array($key, ['agendado', 'fantasma'], true)) {
            return 3;
        }

        return 4;
    }
}

if (!function_exists('dashComercialSortDisplayRowsByMilestone')) {
    function dashComercialSortDisplayRowsByMilestone(array $rows)
    {
        usort($rows, function ($a, $b) {
            $rankDiff = dashComercialResolveDisplayRowMilestoneRank($a) <=> dashComercialResolveDisplayRowMilestoneRank($b);
            if ($rankDiff !== 0) {
                return $rankDiff;
            }

            return strcmp((string) ($b['fecha_raw'] ?? ''), (string) ($a['fecha_raw'] ?? ''));
        });

        return $rows;
    }
}

if (!function_exists('dashComercialBuildAgendadosDisplayCohort')) {
    /**
     * Sección Agendados del modal: consulta_agendados_leads del periodo,
     * excluyendo post_leads, clientes cerrados y estatus distintos de agendado/cotizado.
     */
    function dashComercialBuildAgendadosDisplayCohort($conn, $startDate, $endDate, $idVendedora = null)
    {
        $agendadosCohort = dashComercialBuildAgendadosCohort($conn, $startDate, $endDate, $idVendedora);
        $atencionCohort = dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate, $idVendedora);
        $clientesCohort = dashComercialBuildClientesCohort($conn, $startDate, $endDate, $idVendedora);

        foreach (array_keys($atencionCohort) as $cid) {
            unset($agendadosCohort[$cid]);
        }
        foreach (array_keys($clientesCohort) as $cid) {
            unset($agendadosCohort[$cid]);
        }

        $display = [];
        foreach ($agendadosCohort as $cid => $item) {
            $estatusKey = dashComercialResolveCohortItemEstatusKey($item);
            if (dashComercialIsEventosWpContactForm($item['cf'] ?? [])) {
                if (!in_array($estatusKey, ['agendado', 'cotizado'], true)) {
                    continue;
                }
            } elseif ($estatusKey !== 'agendado') {
                continue;
            }

            $display[$cid] = $item;
        }

        return $display;
    }
}

if (!function_exists('dashComercialFetchVendorModalSections')) {
    /**
     * Modal unificado por vendedora: mismas fuentes que consulta_agendados_leads,
     * consulta_post_leads y clientes.php.
     * - Cierres → clientes cerrados del periodo
     * - Atención → post_leads del periodo (incluye clientes del KPI)
     * - Agenda → agendados del periodo que aún no están en post_leads
     */
    function dashComercialFetchVendorModalSections($conn, $startDate, $endDate, $idVendedora)
    {
        $sections = [
            'cierre'   => dashComercialCohortToDisplayRows(
                $conn,
                dashComercialBuildClientesCohort($conn, $startDate, $endDate, $idVendedora),
                'cierre'
            ),
            'atencion' => dashComercialCohortToDisplayRows(
                $conn,
                dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate, $idVendedora),
                'atencion'
            ),
            'agenda'   => dashComercialCohortToDisplayRows(
                $conn,
                dashComercialBuildAgendadosDisplayCohort($conn, $startDate, $endDate, $idVendedora),
                'agenda'
            ),
        ];

        foreach ($sections as $groupKey => $groupRows) {
            foreach ($sections[$groupKey] as $idx => $row) {
                $sections[$groupKey][$idx]['milestone_group'] = $groupKey;
            }
        }

        $summary = [
            'cierre'   => count($sections['cierre']),
            'atencion' => count($sections['atencion']),
            'agenda'   => count($sections['agenda']),
        ];

        $rowsFlat = array_merge($sections['cierre'], $sections['atencion'], $sections['agenda']);

        return [
            'sections' => $sections,
            'summary'  => $summary,
            'rows'     => $rowsFlat,
        ];
    }
}

if (!function_exists('dashComercialFetchAgendaRecords')) {
    /**
     * Todas las agendas del periodo.
     * Con vendedora: unión clientes + atendidos + agendas (tab «Todos»), ordenadas por etapa.
     * Global: cohorte de agendas del periodo.
     */
    function dashComercialFetchAgendaRecords($conn, $startDate, $endDate, $idVendedora = null)
    {
        $agendaRows = dashComercialFetchAgendaCohortRows($conn, $startDate, $endDate, $idVendedora);

        if ($idVendedora === null) {
            return $agendaRows;
        }

        return dashComercialFetchVendorModalSections($conn, $startDate, $endDate, $idVendedora)['rows'];
    }
}

if (!function_exists('dashComercialFetchAtendidoRecords')) {
    function dashComercialFetchAtendidoRecords($conn, $startDate, $endDate, $idVendedora = null)
    {
        $cohort = dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate, $idVendedora);

        return dashComercialCohortToDisplayRows($conn, $cohort, 'atencion');
    }
}

if (!function_exists('dashComercialFetchClienteRecords')) {
    function dashComercialFetchClienteRecords($conn, $startDate, $endDate, $idVendedora = null)
    {
        $cohort = dashComercialBuildClientesCohort($conn, $startDate, $endDate, $idVendedora);

        return dashComercialCohortToDisplayRows($conn, $cohort, 'cierre');
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

        $wpEventosLeads = wpEventosCfBuildLeadsForConsultaLeads($conn);
        $existingKeys = [];
        foreach ($rows as $existingRow) {
            $existingKeys[($existingRow['origen'] ?? '') . '|' . (int) ($existingRow['id'] ?? 0)] = true;
        }
        foreach ($wpEventosLeads as $wpLead) {
            $key = ($wpLead['tabla_origen'] ?? '') . '|' . (int) ($wpLead['id'] ?? 0);
            if (isset($existingKeys[$key])) {
                continue;
            }
            $dateField = trim((string) ($wpLead['created_time'] ?? $wpLead['submission_date'] ?? ''));
            if (!dashComercialLeadInDateRange($dateField, $startDate, $endDate)) {
                continue;
            }
            $rows[] = [
                'id'          => (int) ($wpLead['id'] ?? 0),
                'nombre'      => dashComercialResolveLeadName($wpLead),
                'email'       => dashComercialResolveLeadEmail($wpLead),
                'telefono'    => dashComercialResolveLeadPhone($wpLead),
                'fecha'       => dashComercialFormatDisplayDate($wpLead['created_time'] ?? ''),
                'fecha_raw'   => $wpLead['created_time'] ?? '',
                'vendedora'   => '—',
                'origen'      => 'eventos_wp',
                'estatus'     => 'Atendido',
                'estatus_key' => 'atendido',
                'cita'        => '—',
            ];
            $existingKeys[$key] = true;
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
            case 'todos':
            case 'agendas':
                return dashComercialFetchAgendaRecords($conn, $startDate, $endDate, $idVendedora);
            case 'leads':
                return dashComercialFetchLeadRecords($conn, $startDate, $endDate);
            default:
                return [];
        }
    }
}
