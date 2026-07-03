<?php
/**
 * Cruza planners con evento atendido (consulta_wp) vs filas visibles en consulta_post_leads.
 * Uso CLI: php verificar_wp_post_leads.php
 * Uso web: verificar_wp_post_leads.php?planner_ids=117,116,...
 */
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/evento_wp_post_helper.php';
require_once __DIR__ . '/lead_origin_helper.php';

if (!function_exists('isPostLeadEventosWpContactForm')) {
    function isPostLeadEventosWpContactForm(array $cf)
    {
        $form = strtolower(trim((string) ($cf['form_name'] ?? '')));
        $tabla = strtolower(trim((string) ($cf['tabla_origen'] ?? '')));

        return $form === 'eventos_wp' || $tabla === 'eventos_wp';
    }
}

$defaultPlannerIds = [
    117, 116, 110, 109, 107, 106, 105, 91, 90, 88, 79, 71, 61, 59, 57, 51, 41, 35, 32, 22, 21, 20,
];

$plannerIds = $defaultPlannerIds;
if (!empty($_GET['planner_ids'])) {
    $plannerIds = array_values(array_filter(array_map('intval', explode(',', (string) $_GET['planner_ids']))));
}

if (empty($plannerIds)) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Sin planner IDs.\n");
        exit(1);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Sin planner IDs.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function verifyExtractDateFromTimestamp($timestamp)
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

function verifyPostLeadDateField(array $lead)
{
    if (isPostLeadEventosWpContactForm($lead)) {
        if (intval($lead['has_appointment'] ?? 1) === 0) {
            if (!empty($lead['created_time'])) {
                return $lead['created_time'];
            }
            return $lead['submission_date'] ?? '';
        }
        $dateAppointment = trim((string) ($lead['date_appointment'] ?? ''));
        if ($dateAppointment !== '' && $dateAppointment !== '0000-00-00') {
            $timeAppointment = trim((string) ($lead['time_appointment'] ?? ''));
            return $timeAppointment !== '' ? ($dateAppointment . ' ' . $timeAppointment) : $dateAppointment;
        }
    }
    if (!empty($lead['created_time'])) {
        return $lead['created_time'];
    }
    return $lead['submission_date'] ?? '';
}

function verifyWouldPassDateFilter(array $lead, ?string $startDate, ?string $endDate)
{
    if ($startDate === null && $endDate === null) {
        return true;
    }
    $dateField = verifyPostLeadDateField($lead);
    if ($dateField === '') {
        return false;
    }
    $d = verifyExtractDateFromTimestamp($dateField);
    if ($d === null) {
        return false;
    }
    if ($startDate && $d < $startDate) {
        return false;
    }
    if ($endDate && $d > $endDate) {
        return false;
    }
    return true;
}

// --- Cargar citas (misma lógica base que consulta_post_leads) ---
$appointmentsByClient = [];
$appointmentIds = [];
$appointmentQuery = $conn->query('SELECT DISTINCT idclie FROM calendario');
if ($appointmentQuery) {
    while ($row = $appointmentQuery->fetch_assoc()) {
        $appointmentIds[] = intval($row['idclie']);
    }
}
if (!empty($appointmentIds)) {
    $idsList = implode(',', array_map('intval', $appointmentIds));
    $apptRes = $conn->query("SELECT * FROM calendario WHERE idclie IN ($idsList)");
    if ($apptRes) {
        while ($ar = $apptRes->fetch_assoc()) {
            $idclie = intval($ar['idclie'] ?? 0);
            if ($idclie <= 0) {
                continue;
            }
            if (!isset($appointmentsByClient[$idclie])) {
                $appointmentsByClient[$idclie] = $ar;
            } elseif (intval($ar['id'] ?? 0) > intval($appointmentsByClient[$idclie]['id'] ?? 0)) {
                $appointmentsByClient[$idclie] = $ar;
            }
        }
    }
}

$appointmentsByEventId = [];
$wpApptRes = $conn->query('SELECT * FROM calendario WHERE tipo = 1 AND eliminado = 0');
if ($wpApptRes) {
    while ($ar = $wpApptRes->fetch_assoc()) {
        $eventId = intval($ar['idclie'] ?? 0);
        if ($eventId <= 0) {
            continue;
        }
        if (!isset($appointmentsByEventId[$eventId])) {
            $appointmentsByEventId[$eventId] = $ar;
        } elseif (intval($ar['id'] ?? 0) > intval($appointmentsByEventId[$eventId]['id'] ?? 0)) {
            $appointmentsByEventId[$eventId] = $ar;
        }
    }
}

$wpEngagementByEventId = [];
$wpEngagementRes = $conn->query("SELECT idclie, cliente_engagement FROM calendario WHERE tipo = 1 AND eliminado = 0 AND cliente_engagement IS NOT NULL AND cliente_engagement > 0 ORDER BY id DESC");
if ($wpEngagementRes) {
    while ($wpEngRow = $wpEngagementRes->fetch_assoc()) {
        $eventId = intval($wpEngRow['idclie'] ?? 0);
        if ($eventId > 0 && !isset($wpEngagementByEventId[$eventId])) {
            $wpEngagementByEventId[$eventId] = intval($wpEngRow['cliente_engagement']);
        }
    }
}

function verifyResolveAppointment(array $cf, array $appointmentsByClient, array $appointmentsByEventId)
{
    $cid = intval($cf['id'] ?? 0);
    if ($cid > 0 && isset($appointmentsByClient[$cid])) {
        return $appointmentsByClient[$cid];
    }
    if (!isPostLeadEventosWpContactForm($cf)) {
        return null;
    }
    $eventId = intval($cf['original_lead_id'] ?? 0);
    if ($eventId > 0 && isset($appointmentsByEventId[$eventId])) {
        return $appointmentsByEventId[$eventId];
    }
    return null;
}

function verifyAppointmentDateIsValid(?array $appt)
{
    if (!is_array($appt)) {
        return true;
    }
    $apptFechaRaw = trim($appt['fecha'] ?? '');
    $apptHoraRaw = trim($appt['hora'] ?? '');
    $apptTs = ($apptFechaRaw !== '') ? strtotime($apptFechaRaw . ' ' . $apptHoraRaw) : false;
    return !($apptFechaRaw === '' || $apptFechaRaw === '0000-00-00' || $apptTs === false || $apptTs <= 0);
}

function verifyResolveEstatus(array $cf, ?array $appt)
{
    if (isPostLeadEventosWpContactForm($cf)) {
        if (is_array($appt) && isset($appt['estatus'])) {
            $intStatus = is_numeric($appt['estatus']) ? intval($appt['estatus']) : null;
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
        }
        return 'atendido';
    }
    return '';
}

// --- Eventos atendidos por planner (misma regla que consulta_wp) ---
$idsList = implode(',', array_map('intval', $plannerIds));
$sqlEvents = "SELECT ev.id AS evento_id, ev.wedding_planner_id, ev.novios, ev.modo, ev.fecha_registro,
                     cf.id AS contact_form_id, cf.names, cf.submission_date, cf.created_time,
                     cf.date_appointment, cf.time_appointment, cf.cliente AS cf_cliente,
                     wp.campaign_name AS planner_name
              FROM eventos_wp ev
              INNER JOIN contact_form cf ON cf.original_lead_id = ev.id
                AND LOWER(TRIM(COALESCE(cf.tabla_origen, ''))) = 'eventos_wp'
              INNER JOIN wedding_planners wp ON wp.id = ev.wedding_planner_id
              WHERE ev.wedding_planner_id IN ($idsList)
              ORDER BY ev.wedding_planner_id, ev.id";
$resEvents = $conn->query($sqlEvents);

$eventsByPlanner = [];
$allPostLeadRows = [];
if ($resEvents) {
    while ($row = $resEvents->fetch_assoc()) {
        $wpId = intval($row['wedding_planner_id']);
        $eventsByPlanner[$wpId][] = $row;

        $cf = [
            'id' => intval($row['contact_form_id']),
            'tabla_origen' => 'eventos_wp',
            'form_name' => 'eventos_wp',
            'original_lead_id' => intval($row['evento_id']),
            'created_time' => $row['created_time'],
            'submission_date' => $row['submission_date'],
            'date_appointment' => $row['date_appointment'],
            'time_appointment' => $row['time_appointment'],
        ];
        $appt = verifyResolveAppointment($cf, $appointmentsByClient, $appointmentsByEventId);
        $reason = null;
        $inAllLeads = true;

        if ($appt !== null && !verifyAppointmentDateIsValid($appt)) {
            $inAllLeads = false;
            $reason = 'cita_con_fecha_invalida';
        }
        $estatus = verifyResolveEstatus($cf, $appt);
        if ($estatus === 'agendado') {
            $inAllLeads = false;
            $reason = 'estatus_agendado';
        }

        $lead = $cf;
        $lead['has_appointment'] = $appt !== null ? 1 : 0;
        $lead['estatus'] = $estatus;
        $lead['wedding_planner_id'] = $wpId;
        $lead['planner_name'] = $row['planner_name'];
        $lead['novios'] = $row['novios'];
        $lead['modo'] = $row['modo'];
        $lead['in_all_leads'] = $inAllLeads;
        $lead['exclude_reason'] = $reason;
        $lead['filter_date_field'] = verifyPostLeadDateField($lead);
        $lead['filter_date'] = verifyExtractDateFromTimestamp($lead['filter_date_field']);

        $allPostLeadRows[] = $lead;
    }
}

$endDateDefault = date('Y-m-d');
$startDateDefault = date('Y-m-d', strtotime('-14 days', strtotime($endDateDefault)));

$report = [
    'generated_at' => date('c'),
    'planner_ids_checked' => count($plannerIds),
    'default_date_filter' => ['start' => $startDateDefault, 'end' => $endDateDefault],
    'summary' => [
        'planners_with_attended_event' => 0,
        'planners_in_post_leads_all' => 0,
        'planners_in_post_leads_default_14d' => 0,
        'contact_form_rows_total' => count($allPostLeadRows),
        'contact_form_in_all_leads' => 0,
        'contact_form_in_default_14d' => 0,
    ],
    'planners' => [],
];

foreach ($plannerIds as $wpId) {
    $events = $eventsByPlanner[$wpId] ?? [];
    $plannerRows = array_values(array_filter($allPostLeadRows, static function ($r) use ($wpId) {
        return intval($r['wedding_planner_id']) === $wpId;
    }));

    $name = $events[0]['planner_name'] ?? ($plannerRows[0]['planner_name'] ?? '');
    $inAll = false;
    $in14d = false;
    foreach ($plannerRows as $pr) {
        if ($pr['in_all_leads']) {
            $inAll = true;
        }
        if ($pr['in_all_leads'] && verifyWouldPassDateFilter($pr, $startDateDefault, $endDateDefault)) {
            $in14d = true;
        }
    }

    if (!empty($events)) {
        $report['summary']['planners_with_attended_event']++;
    }
    if ($inAll) {
        $report['summary']['planners_in_post_leads_all']++;
    }
    if ($in14d) {
        $report['summary']['planners_in_post_leads_default_14d']++;
    }

    $report['planners'][] = [
        'wp_id' => $wpId,
        'planner_name' => $name,
        'attended_events_count' => count($events),
        'visible_post_leads_show_all' => $inAll,
        'visible_post_leads_default_14d' => $in14d,
        'events' => array_map(static function ($pr) use ($startDateDefault, $endDateDefault) {
            return [
                'contact_form_id' => $pr['id'],
                'evento_id' => $pr['original_lead_id'],
                'modo' => $pr['modo'],
                'novios' => $pr['novios'],
                'has_appointment' => $pr['has_appointment'],
                'estatus' => $pr['estatus'],
                'in_all_leads' => $pr['in_all_leads'],
                'exclude_reason' => $pr['exclude_reason'],
                'filter_date' => $pr['filter_date'],
                'in_default_14d' => $pr['in_all_leads'] && verifyWouldPassDateFilter($pr, $startDateDefault, $endDateDefault),
            ];
        }, $plannerRows),
    ];
}

foreach ($allPostLeadRows as $pr) {
    if ($pr['in_all_leads']) {
        $report['summary']['contact_form_in_all_leads']++;
    }
    if ($pr['in_all_leads'] && verifyWouldPassDateFilter($pr, $startDateDefault, $endDateDefault)) {
        $report['summary']['contact_form_in_default_14d']++;
    }
}

$isCli = (PHP_SAPI === 'cli');
if ($isCli) {
    echo "=== WP atendidos vs consulta_post_leads ===\n";
    echo 'Filtro fecha por defecto post-leads: ' . $startDateDefault . ' .. ' . $endDateDefault . "\n\n";
    echo sprintf(
        "Planners revisados: %d | Con evento atendido: %d | En post-leads (show_all): %d | En post-leads (14d): %d\n",
        $report['planner_ids_checked'],
        $report['summary']['planners_with_attended_event'],
        $report['summary']['planners_in_post_leads_all'],
        $report['summary']['planners_in_post_leads_default_14d']
    );
    echo sprintf(
        "Filas contact_form eventos_wp: %d | En allLeads: %d | En 14d: %d\n\n",
        $report['summary']['contact_form_rows_total'],
        $report['summary']['contact_form_in_all_leads'],
        $report['summary']['contact_form_in_default_14d']
    );

    foreach ($report['planners'] as $p) {
        $allFlag = $p['visible_post_leads_show_all'] ? 'SI' : 'NO';
        $d14Flag = $p['visible_post_leads_default_14d'] ? 'SI' : 'NO';
        echo sprintf(
            "[%s] WP#%d %s | eventos:%d | post-leads show_all:%s | post-leads 14d:%s\n",
            ($p['attended_events_count'] > 0 ? 'OK' : 'SIN-CF'),
            $p['wp_id'],
            $p['planner_name'],
            $p['attended_events_count'],
            $allFlag,
            $d14Flag
        );
        foreach ($p['events'] as $ev) {
            echo sprintf(
                "    cf#%d evento#%d modo=%s estatus=%s fecha_filtro=%s allLeads=%s 14d=%s%s\n",
                $ev['contact_form_id'],
                $ev['evento_id'],
                $ev['modo'] ?? '',
                $ev['estatus'],
                $ev['filter_date'] ?? 'n/a',
                $ev['in_all_leads'] ? 'SI' : 'NO',
                $ev['in_default_14d'] ? 'SI' : 'NO',
                $ev['exclude_reason'] ? ' (' . $ev['exclude_reason'] . ')' : ''
            );
        }
    }
    exit(0);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
