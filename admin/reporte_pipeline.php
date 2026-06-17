<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';

// ── HELPERS ──────────────────────────────────────────────────────────────

if (!function_exists('extractDateFromTimestamp')) {
    function extractDateFromTimestamp($timestamp) {
        if (empty($timestamp)) return null;
        if (strpos($timestamp, 'T') !== false) {
            $ts = strtotime(substr($timestamp, 0, 19));
            return ($ts !== false) ? date('Y-m-d', $ts) : null;
        }
        $ts = strtotime($timestamp);
        return ($ts !== false) ? date('Y-m-d', $ts) : null;
    }
}

if (!function_exists('normalizeFirstContactChannelLabel')) {
    function normalizeFirstContactChannelLabel($value) {
        $normalized = trim((string) $value);
        if ($normalized === '') return 'Pendiente de completar';
        $key = mb_strtolower($normalized, 'UTF-8');
        $key = str_replace(['–', '—'], '-', $key);
        $key = preg_replace('/\s+/', ' ', $key);
        $map = [
            'whatsapp'                     => 'WhatsApp',
            'instagram dm - campaign'      => 'Instagram DM - Campaña',
            'instagram dm campaign'        => 'Instagram DM - Campaña',
            'instagram dm - organic'       => 'Instagram DM - Orgánico',
            'instagram dm organic'         => 'Instagram DM - Orgánico',
            'email'                        => 'Correo electrónico',
            'correo electronico'           => 'Correo electrónico',
            'correo electrónico'           => 'Correo electrónico',
            'mail'                         => 'Correo electrónico',
            'phone call'                   => 'Llamada telefónica',
            'llamada telefonica'           => 'Llamada telefónica',
            'llamada telefónica'           => 'Llamada telefónica',
        ];
        return $map[$key] ?? $normalized;
    }
}

if (!function_exists('isB1B2CampaignName')) {
    function isB1B2CampaignName($campaignName) {
        if ($campaignName === null) return false;
        $v = strtolower(trim((string) $campaignName));
        if ($v === '') return false;
        $v = preg_replace('/\s+/', ' ', $v);
        return in_array($v, ['b1', 'b1 (usa)', 'b1 usa', 'b2', 'b2 (mx)', 'b2 mx'], true);
    }
}

if (!function_exists('getDondeNosConocioLabel')) {
    function getDondeNosConocioLabel($lead) {
        $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
        $howMap = ['1' => 'Wedding Planner', '2' => 'Community', '3' => 'New Market'];

        if ($howRaw === '' || !isset($howMap[$howRaw])) {
            return 'Pendiente de completar';
        }

        return $howMap[$howRaw];
    }
}

if (!function_exists('formatCampaignDisplay')) {
    function formatCampaignDisplay($campaignName) {
        $v = trim((string)$campaignName);
        if ($v === '') return '';
        // Si empieza con C o B seguido de uno o más dígitos y un punto, mostrar solo el código corto (ej. C3, C10, B1)
        if (preg_match('/^([CB]\d+)\./i', $v, $m)) {
            return strtoupper($m[1]);
        }
        return $v;
    }
}

// ── FILTROS ──────────────────────────────────────────────────────────────

$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate   = isset($_GET['end_date'])   ? trim($_GET['end_date'])   : '';
$filterYear  = isset($_GET['year'])  ? intval($_GET['year'])  : 0;
$filterMonth = isset($_GET['month']) ? intval($_GET['month']) : 0;
$filterWeek  = isset($_GET['week'])  ? trim($_GET['week'])   : '';

// Prioridad de filtros: año/mes/semana > rango de fechas
$hasNavFilter = ($filterWeek !== '' || $filterMonth > 0 || $filterYear > 0);

if ($hasNavFilter) {
    // Ignorar rango de fechas cuando se usa navegación de intervalos
    $startDate = '';
    $endDate = '';
    unset($_SESSION['leads_filter_start'], $_SESSION['leads_filter_end']);
} else {
    // Persistir en sesión
    if (isset($_GET['show_all'])) {
        unset($_SESSION['leads_filter_start'], $_SESSION['leads_filter_end']);
    } elseif ($startDate !== '' || $endDate !== '') {
        $_SESSION['leads_filter_start'] = $startDate;
        $_SESSION['leads_filter_end']   = $endDate;
    } elseif (!empty($_SESSION['leads_filter_start']) || !empty($_SESSION['leads_filter_end'])) {
        $startDate = $_SESSION['leads_filter_start'] ?? '';
        $endDate   = $_SESSION['leads_filter_end']   ?? '';
    }
}

// Navegación por semana ISO (YYYY-WXX)
if ($filterWeek !== '' && preg_match('/^(\d{4})-W(\d{2})$/', $filterWeek, $wm)) {
    $dto = new DateTime();
    $dto->setISODate((int)$wm[1], (int)$wm[2], 1);
    $startDate = $dto->format('Y-m-d');
    $dto->setISODate((int)$wm[1], (int)$wm[2], 7);
    $endDate = $dto->format('Y-m-d');
} elseif ($filterMonth > 0 && $filterYear > 0 && $startDate === '' && $endDate === '') {
    $startDate = sprintf('%04d-%02d-01', $filterYear, $filterMonth);
    $endDate   = date('Y-m-t', strtotime($startDate));
} elseif ($filterYear > 0 && $startDate === '' && $endDate === '') {
    $startDate = $filterYear . '-01-01';
    $endDate   = $filterYear . '-12-31';
}

if ($startDate === '' && $endDate === '' && empty($_GET['show_all'])) {
    $endDate   = date('Y-m-d');
    $startDate = date('Y-m-01'); // default: mes actual
}

$reportRangeLabel = 'Todos los registros';
if (($startDate !== '' || $endDate !== '') && empty($_GET['show_all'])) {
    $rl1 = $startDate !== '' ? date('d/m/Y', strtotime($startDate)) : '...';
    $rl2 = $endDate   !== '' ? date('d/m/Y', strtotime($endDate))   : '...';
    $reportRangeLabel = "$rl1 → $rl2";
}

// ── PER-SECTION FILTER OVERRIDES ─────────────────────────────────────────
$preStart  = isset($_GET['pre_start'])  ? trim($_GET['pre_start'])  : '';
$preEnd    = isset($_GET['pre_end'])    ? trim($_GET['pre_end'])    : '';
$preYear   = isset($_GET['pre_year'])   ? intval($_GET['pre_year']) : 0;
$preMonth  = isset($_GET['pre_month'])  ? intval($_GET['pre_month']) : 0;
$preWeek   = isset($_GET['pre_week'])   ? trim($_GET['pre_week'])   : '';

$postStart = isset($_GET['post_start']) ? trim($_GET['post_start']) : '';
$postEnd   = isset($_GET['post_end'])   ? trim($_GET['post_end'])   : '';
$postYear  = isset($_GET['post_year'])  ? intval($_GET['post_year']) : 0;
$postMonth = isset($_GET['post_month']) ? intval($_GET['post_month']) : 0;
$postWeek  = isset($_GET['post_week'])  ? trim($_GET['post_week'])  : '';

$cliStart  = isset($_GET['cli_start'])  ? trim($_GET['cli_start'])  : '';
$cliEnd    = isset($_GET['cli_end'])    ? trim($_GET['cli_end'])    : '';
$cliYear   = isset($_GET['cli_year'])   ? intval($_GET['cli_year']) : 0;
$cliMonth  = isset($_GET['cli_month'])  ? intval($_GET['cli_month']) : 0;
$cliWeek   = isset($_GET['cli_week'])   ? trim($_GET['cli_week'])   : '';

function resolveSectionDateRange($sectionStart, $sectionEnd, $sectionYear, $sectionMonth, $sectionWeek, $globalStart, $globalEnd) {
    if ($sectionWeek !== '' && preg_match('/^(\d{4})-W(\d{2})$/', $sectionWeek, $m)) {
        $dto = new DateTime();
        $dto->setISODate(intval($m[1]), intval($m[2]), 1);
        $start = $dto->format('Y-m-d');
        $dto->setISODate(intval($m[1]), intval($m[2]), 7);
        $end = $dto->format('Y-m-d');
        return [$start, $end];
    }

    if ($sectionYear > 0 && $sectionMonth > 0) {
        $start = sprintf('%04d-%02d-01', $sectionYear, $sectionMonth);
        $end = date('Y-m-t', strtotime($start));
        return [$start, $end];
    }

    if ($sectionYear > 0) {
        $start = sprintf('%04d-01-01', $sectionYear);
        $end = sprintf('%04d-12-31', $sectionYear);
        return [$start, $end];
    }

    $start = $sectionStart !== '' ? $sectionStart : $globalStart;
    $end   = $sectionEnd   !== '' ? $sectionEnd   : $globalEnd;

    return [$start, $end];
}

// Effective per-section date ranges (section override > global)
list($preEffStart, $preEffEnd)   = resolveSectionDateRange($preStart,  $preEnd,  $preYear,  $preMonth,  $preWeek,  $startDate, $endDate);
list($postEffStart,$postEffEnd)  = resolveSectionDateRange($postStart, $postEnd, $postYear, $postMonth, $postWeek, $startDate, $endDate);
list($cliEffStart, $cliEffEnd)   = resolveSectionDateRange($cliStart,  $cliEnd,  $cliYear,  $cliMonth,  $cliWeek,  $startDate, $endDate);

// Generar datos para los selects de navegación
$navMonthNames = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$currentYear = (int)date('Y');
$currentWeekISO = date('o') . '-W' . str_pad((int)date('W'), 2, '0', STR_PAD_LEFT);

$navYearMonths = [];
$yearMonthRes = $conn->query("SELECT DISTINCT YEAR(submission_date) AS yr, MONTH(submission_date) AS mo FROM contact_form WHERE submission_date IS NOT NULL AND submission_date >= '2020-01-01' AND submission_date != '0000-00-00 00:00:00' ORDER BY yr DESC, mo DESC");
if ($yearMonthRes) {
    while ($r = $yearMonthRes->fetch_assoc()) {
        $yr = intval($r['yr'] ?? 0);
        $mo = intval($r['mo'] ?? 0);
        if ($yr > 0 && $mo >= 1 && $mo <= 12) {
            $navYearMonths[$yr][$mo] = true;
        }
    }
}

$navYears = array_keys($navYearMonths);
rsort($navYears);

// Asegurar año actual solo si existe en datos
if (empty($navYears)) {
    $navYears = [$currentYear];
}

// Convertir meses a listas ordenadas y quitar meses futuros sin datos
foreach ($navYearMonths as $yr => $months) {
    $sorted = array_keys($months);
    sort($sorted);
    $navYearMonths[$yr] = $sorted;
}

$navWeeksData = [];
$weekRes = $conn->query("SELECT DISTINCT YEARWEEK(submission_date, 3) AS yw FROM contact_form WHERE submission_date IS NOT NULL AND submission_date >= '2020-01-01' AND submission_date != '0000-00-00 00:00:00' ORDER BY yw DESC");
if ($weekRes) {
    while ($r = $weekRes->fetch_assoc()) {
        $yw = (string)($r['yw'] ?? '');
        if ($yw === '' || strlen($yw) < 6) continue;
        $isoYear = intval(substr($yw, 0, 4));
        $isoWeek = intval(substr($yw, 4));
        if ($isoYear <= 0 || $isoWeek < 1 || $isoWeek > 53) continue;

        $dto = new DateTime();
        $dto->setISODate($isoYear, $isoWeek, 1);
        $wEnd = clone $dto;
        $wEnd->modify('+6 days');

        $startMonth = (int)$dto->format('n');
        $endMonth = (int)$wEnd->format('n');

        $navWeeksData[] = [
            'year'       => $isoYear,
            'startMonth' => $startMonth,
            'endMonth'   => $endMonth,
            'week'       => sprintf('%04d-W%02d', $isoYear, $isoWeek),
            'label'      => sprintf('%04d-W%02d (%s – %s)', $isoYear, $isoWeek, $dto->format('d/m'), $wEnd->format('d/m')),
        ];
    }
}

// Determinar año/mes seleccionados para los selects
$navSelectedYear  = $filterYear  > 0 ? $filterYear  : ($startDate !== '' ? (int)date('Y', strtotime($startDate)) : $currentYear);
$navSelectedMonth = $filterMonth > 0 ? $filterMonth : ($startDate !== '' ? (int)date('n', strtotime($startDate)) : (int)date('n'));
$navSelectedWeek  = $filterWeek;

if (!in_array($navSelectedYear, $navYears, true)) {
    $navSelectedYear = !empty($navYears) ? $navYears[0] : $currentYear;
}

$availableMonthsForYear = $navYearMonths[$navSelectedYear] ?? [];
if (empty($availableMonthsForYear)) {
    $availableMonthsForYear = range(1, 12);
}

if (!in_array($navSelectedMonth, $availableMonthsForYear, true)) {
    $navSelectedMonth = !empty($availableMonthsForYear) ? $availableMonthsForYear[0] : (int)date('n');
}

// ── 1. CARGAR PRE-Q LEADS (todos los registros de tablas_leads) ─────────

$tablas = [];
$sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo != 2 ORDER BY nombre";
$resultTablas = $conn->query($sqlTablas);
if ($resultTablas && $resultTablas->num_rows > 0) {
    while ($row = $resultTablas->fetch_assoc()) {
        $tablas[] = $row['nombre'];
    }
}
// Deduplicar por si alguna tabla aparece dos veces en tablas_leads
$tablas = array_values(array_unique($tablas));

// Compute widest date range needed for preLeads (covers PRE and POST sections)
$_loadStarts = array_filter([$preEffStart, $postEffStart], function($v) { return $v !== ''; });
$_loadEnds   = array_filter([$preEffEnd, $postEffEnd], function($v) { return $v !== ''; });
$sd = !empty($_loadStarts) ? date('Y-m-d', strtotime(min($_loadStarts))) : null;
$ed = !empty($_loadEnds)   ? date('Y-m-d', strtotime(max($_loadEnds)))   : null;

$preLeads = [];
foreach ($tablas as $tableName) {
    $checkTable = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    if ($checkTable->num_rows === 0) continue;

    $columns = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tableName) . "`");
    while ($col = $columnsResult->fetch_assoc()) {
        $columns[] = $col['Field'];
    }

    $whereParts = [];
    if (in_array('descartado', $columns)) {
        $whereParts[] = "(descartado = 0 OR descartado IS NULL)";
    }

    if (($sd || $ed) && in_array('created_time', $columns)) {
        $dateExtract = "CASE WHEN created_time LIKE '%T%' THEN DATE(STR_TO_DATE(SUBSTRING(created_time, 1, 19), '%Y-%m-%dT%H:%i:%s')) ELSE DATE(created_time) END";
        if ($sd) $whereParts[] = $dateExtract . " >= '" . $conn->real_escape_string($sd) . "'";
        if ($ed) $whereParts[] = $dateExtract . " <= '" . $conn->real_escape_string($ed) . "'";
    }

    $whereSql = !empty($whereParts) ? implode(' AND ', $whereParts) : '1=1';
    $orderCol = in_array('created_time', $columns) ? 'created_time' : (in_array('id', $columns) ? 'id' : null);
    $orderSql = $orderCol ? " ORDER BY `$orderCol` DESC" : '';
    $sql = "SELECT *, '" . $conn->real_escape_string($tableName) . "' as tabla_origen FROM `" . $conn->real_escape_string($tableName) . "` WHERE " . $whereSql . $orderSql;

    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $row['tabla_origen'] = $tableName;
            $preLeads[] = $row;
        }
    }
}

// ── wedding_planners (tipo=2) NO se carga en preLeads ──────────────────────
// Los WP afianzados del flujo nuevo ya están en tablas_leads como wp_eventos_afianzados (tipo!=2)
// y se cargan arriba. Los WP viejos (sin contact_form) no deben contarse en el pipeline.
// ────────────────────────────────────────────────────────────────────────────

// ── Enriquecer wedding_planners con datos del coordinador (first_contact_channel, how_did_you_meet) ──
// (Bloque de enriquecimiento de coordinadores para raw wedding_planners eliminado:
// ya no se cargan leads crudos de esa tabla en preLeads.
// Los leads de wp_eventos_afianzados ya tienen first_contact_channel en su tabla,
// y reciben how_did_you_meet desde el cfRow carry-over más abajo.)

// Map pre-leads to contact_form + calendario for status enrichment
$leadIdsByTable = [];
foreach ($preLeads as $lead) {
    $t = $lead['tabla_origen'] ?? '';
    $id = intval($lead['id'] ?? 0);
    if ($t === '' || $id <= 0) continue;
    $leadIdsByTable[$t][] = $id;
}

// Get contact_form mapping
$cfMap = []; // tabla_origen => [original_lead_id => cf_row]
foreach ($leadIdsByTable as $tabla => $ids) {
    if (empty($ids)) continue;
    $idsList = implode(',', array_map('intval', $ids));
    $escapedTabla = $conn->real_escape_string($tabla);
    $cfSql = "SELECT * FROM contact_form WHERE LOWER(tabla_origen) = LOWER('$escapedTabla') AND original_lead_id IN ($idsList)";
    $cfRes = $conn->query($cfSql);
    if ($cfRes && $cfRes->num_rows > 0) {
        while ($cfRow = $cfRes->fetch_assoc()) {
            $origId = intval($cfRow['original_lead_id'] ?? 0);
            if ($origId > 0) {
                $cfMap[$tabla][$origId] = $cfRow;
            }
        }
    }
}

// Get calendario appointments
$allCfIds = [];
foreach ($cfMap as $tabla => $rows) {
    foreach ($rows as $cfRow) {
        $allCfIds[] = intval($cfRow['id']);
    }
}

$appointmentsByClient = [];
if (!empty($allCfIds)) {
    $cfIdsList = implode(',', array_unique(array_map('intval', $allCfIds)));
    $apptRes = $conn->query("SELECT * FROM calendario WHERE idclie IN ($cfIdsList)");
    if ($apptRes && $apptRes->num_rows > 0) {
        while ($ar = $apptRes->fetch_assoc()) {
            $idclie = intval($ar['idclie'] ?? 0);
            if ($idclie <= 0) continue;
            if (!isset($appointmentsByClient[$idclie])) {
                $appointmentsByClient[$idclie] = $ar;
            } else {
                $prev = $appointmentsByClient[$idclie];
                $t1 = strtotime(($ar['fecha'] ?? '') . ' ' . ($ar['hora'] ?? '')) ?: 0;
                $t2 = strtotime(($prev['fecha'] ?? '') . ' ' . ($prev['hora'] ?? '')) ?: 0;
                if ($t1 > $t2) $appointmentsByClient[$idclie] = $ar;
            }
        }
    }
}

// Enrich pre-leads with status
foreach ($preLeads as &$lead) {
    $t  = $lead['tabla_origen'] ?? '';
    $id = intval($lead['id'] ?? 0);
    $cfRow = $cfMap[$t][$id] ?? null;
    $apptRow = null;
    if ($cfRow) {
        $cfId = intval($cfRow['id']);
        $apptRow = $appointmentsByClient[$cfId] ?? null;
    }

    // Determine status
    if ($cfRow && isset($cfRow['cliente']) && intval($cfRow['cliente']) === 1) {
        $lead['_status'] = 'cliente';
    } elseif ($apptRow !== null && isset($apptRow['estatus'])) {
        $rawS = $apptRow['estatus'];
        $intS = is_numeric($rawS) ? intval($rawS) : null;
        if ($intS === 0) $lead['_status'] = 'agendado';
        elseif ($intS === 1) $lead['_status'] = 'atendido';
        elseif ($intS === 2) $lead['_status'] = 'fantasma';
        elseif ($intS === 3) $lead['_status'] = 'muerto';
        else $lead['_status'] = 'agendado';
    } else {
        $lead['_status'] = 'lead';
    }

    // Carry over contact_form fields for charts
    if ($cfRow) {
        foreach (['first_contact_channel', 'how_did_you_meet', 'hear_about_us', 'campaign_name'] as $f) {
            if (empty($lead[$f]) && !empty($cfRow[$f])) {
                $lead[$f] = $cfRow[$f];
            }
        }
    }

    // Fallback para leads de origen WP: siempre son "Wedding Planner" (how_did_you_meet=1).
    // Cubre registros antiguos donde contact_form no tenía ese campo.
    if (empty($lead['how_did_you_meet'])) {
        $tablaOrigenNorm = strtolower(trim($lead['tabla_origen'] ?? ''));
        if (in_array($tablaOrigenNorm, ['wp_eventos_afianzados', 'wedding_planners'], true)) {
            $lead['how_did_you_meet'] = '1';
        }
    }

    // Determine date for each lead
    $lead['_date'] = extractDateFromTimestamp($lead['created_time'] ?? '');
}
unset($lead);

// ── 2. CLASIFICAR EN ETAPAS ─────────────────────────────────────────────

$preStage  = []; // PRE: todos los registros
$postStage = []; // POST: agendados + fantasma + atendido + muerto + cliente
$clientStage = []; // CLIENTES: solo cierres (desde contact_form directamente)

// Per-section date boundaries (PRE)
$_preSd  = $preEffStart  !== '' ? date('Y-m-d', strtotime($preEffStart))  : null;
$_preEd  = $preEffEnd    !== '' ? date('Y-m-d', strtotime($preEffEnd))    : null;

foreach ($preLeads as $lead) {
    $st = $lead['_status'];
    $d  = $lead['_date'];

    // PRE = todos (filtered by PRE section date range)
    // Leads con fecha nula se incluyen (la SQL ya filtró por fecha cuando la columna existe,
    // igual que consulta_leads.php que no aplica un segundo filtro PHP sobre leads sin created_time)
    $inPre = true;
    if ($_preSd && $d !== null && $d < $_preSd) $inPre = false;
    if ($_preEd && $d !== null && $d > $_preEd) $inPre = false;
    if ($inPre) $preStage[] = $lead;

}

// ── 2b. CARGAR POST-LEADS directamente de contact_form + calendario ─────
// (Misma lógica que consulta_post_leads.php para que los totales coincidan)
$_postSd = $postEffStart !== '' ? date('Y-m-d', strtotime($postEffStart)) : null;
$_postEd = $postEffEnd   !== '' ? date('Y-m-d', strtotime($postEffEnd))   : null;

$postAppointmentIds = [];
$postApptQuery = $conn->query("SELECT DISTINCT idclie FROM calendario");
if ($postApptQuery && $postApptQuery->num_rows > 0) {
    while ($row = $postApptQuery->fetch_assoc()) {
        $postAppointmentIds[] = intval($row['idclie']);
    }
}

$postAppointmentsByClient = [];
if (!empty($postAppointmentIds)) {
    $postIdsList = implode(',', array_map('intval', $postAppointmentIds));
    $postApptRes = $conn->query("SELECT * FROM calendario WHERE idclie IN ($postIdsList)");
    if ($postApptRes && $postApptRes->num_rows > 0) {
        while ($ar = $postApptRes->fetch_assoc()) {
            $idclie = intval($ar['idclie'] ?? 0);
            if ($idclie <= 0) continue;
            if (!isset($postAppointmentsByClient[$idclie])) {
                $postAppointmentsByClient[$idclie] = $ar;
            } else {
                $prev = $postAppointmentsByClient[$idclie];
                $t1 = strtotime(($ar['fecha'] ?? '') . ' ' . ($ar['hora'] ?? '')) ?: 0;
                $t2 = strtotime(($prev['fecha'] ?? '') . ' ' . ($prev['hora'] ?? '')) ?: 0;
                if ($t1 > $t2) $postAppointmentsByClient[$idclie] = $ar;
            }
        }
    }
}

if (!empty($postAppointmentIds)) {
    $postIdsList = implode(',', array_map('intval', $postAppointmentIds));
    $postCfSql = "SELECT * FROM contact_form WHERE id IN ($postIdsList) ORDER BY submission_date DESC";
    $postCfResult = $conn->query($postCfSql);
    if ($postCfResult && $postCfResult->num_rows > 0) {
        while ($cf = $postCfResult->fetch_assoc()) {
            $tablaOrigenRaw = strtolower(trim($cf['tabla_origen'] ?? ''));
            if ($tablaOrigenRaw === 'wedding_planners' || $tablaOrigenRaw === 'wedding_planner') continue;

            $merged = $cf;
            $merged['full_name'] = $cf['names'] ?? 'N/A';
            $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';

            // Enriquecer con datos del lead original
            $formName = $cf['tabla_origen'] ?? '';
            $origId = intval($cf['original_lead_id'] ?? 0);
            if (!empty($formName) && $origId > 0) {
                $escapedForm = $conn->real_escape_string($formName);
                $checkT = $conn->query("SHOW TABLES LIKE '$escapedForm'");
                if ($checkT && $checkT->num_rows > 0) {
                    $leadR = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
                    if ($leadR && $leadR->num_rows > 0) {
                        $leadRow = $leadR->fetch_assoc();
                        if (!empty($leadRow['created_time'])) {
                            $merged['created_time'] = $leadRow['created_time'];
                        } elseif (!empty($leadRow['created_at'])) {
                            $merged['created_time'] = $leadRow['created_at'];
                        }
                        foreach ($leadRow as $k => $v) {
                            if (is_string($v)) $v = trim($v);
                            if (!isset($merged[$k]) || $merged[$k] === '' || $merged[$k] === null) {
                                $merged[$k] = $v;
                            }
                        }
                    }
                }
            }

            // Omitir registros con fecha de cita inválida (como consulta_post_leads)
            $cid = intval($cf['id']);
            if ($cid > 0 && isset($postAppointmentsByClient[$cid])) {
                $appt = $postAppointmentsByClient[$cid];
                $apptFechaRaw = trim($appt['fecha'] ?? '');
                $apptHoraRaw = trim($appt['hora'] ?? '');
                $apptTs = ($apptFechaRaw !== '') ? strtotime($apptFechaRaw . ' ' . $apptHoraRaw) : false;
                if ($apptFechaRaw === '' || $apptFechaRaw === '0000-00-00' || $apptTs === false || $apptTs <= 0) {
                    continue;
                }
            }

            // Determinar estatus
            if (isset($cf['cliente']) && intval($cf['cliente']) === 1) {
                $merged['_status'] = 'cliente';
            } elseif ($cid > 0 && isset($postAppointmentsByClient[$cid]) && isset($postAppointmentsByClient[$cid]['estatus'])) {
                $rawS = $postAppointmentsByClient[$cid]['estatus'];
                $intS = is_numeric($rawS) ? intval($rawS) : null;
                if ($intS === 0) $merged['_status'] = 'agendado';
                elseif ($intS === 1) $merged['_status'] = 'atendido';
                elseif ($intS === 2) $merged['_status'] = 'fantasma';
                elseif ($intS === 3) $merged['_status'] = 'muerto';
                else $merged['_status'] = 'agendado';
            } else {
                $merged['_status'] = 'agendado';
            }

            // Fallback how_did_you_meet para leads de origen WP en POST
            if (empty($merged['how_did_you_meet'])) {
                $tablaOrigenNorm = strtolower(trim($merged['tabla_origen'] ?? ''));
                if (in_array($tablaOrigenNorm, ['wp_eventos_afianzados', 'wedding_planners'], true)) {
                    $merged['how_did_you_meet'] = '1';
                }
            }

            // Filtrar por rango de fecha POST
            // Igual que consulta_post_leads.php: siempre saltar leads sin fecha parseable;
            // cuando hay filtro de fecha, también comparar contra el rango.
            $d = extractDateFromTimestamp($merged['created_time'] ?? '');
            if ($d === null) continue;
            if ($_postSd && $d < $_postSd) continue;
            if ($_postEd && $d > $_postEd) continue;

            $merged['_date'] = $d;
            $postStage[] = $merged;
        }
    }
}

// ── 2c. CARGAR CIERRES directamente de contact_form (cliente = 1) ───────
// Cargar TODOS los clientes y filtrar por fecha_cambio_cliente en PHP (como clientes.php)
$_cliSd = $cliEffStart !== '' ? date('Y-m-d', strtotime($cliEffStart)) : null;
$_cliEd = $cliEffEnd   !== '' ? date('Y-m-d', strtotime($cliEffEnd))   : null;
$clientWhere = "cliente = 1 AND LOWER(COALESCE(tabla_origen, '')) != 'wedding_planners'";
$clientSql = "SELECT * FROM contact_form WHERE $clientWhere ORDER BY submission_date DESC";
$clientResult = $conn->query($clientSql);
if ($clientResult && $clientResult->num_rows > 0) {
    while ($cf = $clientResult->fetch_assoc()) {
        $tablaOrigenRaw = strtolower(trim($cf['tabla_origen'] ?? ''));
        if ($tablaOrigenRaw === 'wedding_planners' || $tablaOrigenRaw === 'wedding_planner') continue;

        $merged = $cf;
        $merged['full_name'] = $cf['names'] ?? 'N/A';
        $merged['fecha_cambio_cliente'] = $cf['fecha_cambio_cliente'] ?? '';
        $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';

        // Enriquecer con datos del lead original
        $formName = $cf['tabla_origen'] ?? '';
        $origId = intval($cf['original_lead_id'] ?? 0);
        if (!empty($formName) && $origId > 0) {
            $escapedForm = $conn->real_escape_string($formName);
            $checkT = $conn->query("SHOW TABLES LIKE '$escapedForm'");
            if ($checkT && $checkT->num_rows > 0) {
                $leadR = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
                if ($leadR && $leadR->num_rows > 0) {
                    $leadRow = $leadR->fetch_assoc();
                    foreach ($leadRow as $k => $v) {
                        if (is_string($v)) $v = trim($v);
                        if (!isset($merged[$k]) || $merged[$k] === '' || $merged[$k] === null) {
                            $merged[$k] = $v;
                        }
                    }
                }
            }
        }

        // Fallback how_did_you_meet para leads WP en CLI:
        // - wp_eventos_afianzados: afianzados que pasaron a cliente (how_did_you_meet='1' puesto por markAfianzadoEventAsClient)
        // - eventos_wp: no-afianzados que pasaron directamente a cliente (how_did_you_meet='1' puesto por upsertEventoClientContactForm)
        // Registros anteriores pueden tener el campo vacío → forzar el fallback.
        if (empty($merged['how_did_you_meet'])) {
            $tablaOrigenNorm = strtolower(trim($merged['tabla_origen'] ?? ''));
            if (in_array($tablaOrigenNorm, ['wp_eventos_afianzados', 'eventos_wp', 'wedding_planners'], true)) {
                $merged['how_did_you_meet'] = '1';
            }
        }

        // Filtrar por fecha_cambio_cliente en PHP (como clientes.php)
        $fcc = $merged['fecha_cambio_cliente'] ?? '';
        if (empty($fcc)) continue;
        $fccTs = strtotime($fcc);
        if ($fccTs === false) continue;
        $fccDate = date('Y-m-d', $fccTs);
        if ($_cliSd && $fccDate < $_cliSd) continue;
        if ($_cliEd && $fccDate > $_cliEd) continue;

        $clientStage[] = $merged;
    }
}

// ── 3. FUNCIONES DE AGREGACIÓN PARA CADA ETAPA ─────────────────────────

function buildGroupBreakdown($leads) {
    $total = count($leads);

    $contactMethodCounts = [];
    foreach ($leads as $lead) {
        $label = normalizeFirstContactChannelLabel($lead['first_contact_channel'] ?? '');
        $contactMethodCounts[$label] = ($contactMethodCounts[$label] ?? 0) + 1;
    }
    arsort($contactMethodCounts);

    $contactPie = [];
    foreach ($contactMethodCounts as $label => $count) {
        if ($count <= 0) continue;
        $contactPie[] = ['name' => $label, 'y' => $count];
    }

    $origenCounts = [];
    foreach ($leads as $lead) {
        $label = getDondeNosConocioLabel($lead);
        $origenCounts[$label] = ($origenCounts[$label] ?? 0) + 1;
    }
    arsort($origenCounts);

    $origenPie = [];
    foreach ($origenCounts as $label => $count) {
        if ($count <= 0) continue;
        $origenPie[] = ['name' => $label, 'y' => $count];
    }

    return [
        'total'               => $total,
        'contactMethodCounts' => $contactMethodCounts,
        'contactPie'          => $contactPie,
        'origenCounts'        => $origenCounts,
        'origenPie'           => $origenPie,
    ];
}

function buildStageData($leads) {
    $regularLeads   = [];
    $afianzadoLeads = [];
    foreach ($leads as $lead) {
        $tablaOrigen = strtolower(trim($lead['tabla_origen'] ?? ''));
        $esAfianzado = ($tablaOrigen === 'wedding_planners') && intval($lead['afianzado'] ?? 0) === 1;
        if ($esAfianzado) {
            $afianzadoLeads[] = $lead;
        } else {
            $regularLeads[] = $lead;
        }
    }

    $regular   = buildGroupBreakdown($regularLeads);
    $afianzado = buildGroupBreakdown($afianzadoLeads);

    // Combined totals
    $allContacts = [];
    foreach ($leads as $lead) {
        $label = normalizeFirstContactChannelLabel($lead['first_contact_channel'] ?? '');
        $allContacts[$label] = ($allContacts[$label] ?? 0) + 1;
    }
    arsort($allContacts);
    $contactPie = [];
    foreach ($allContacts as $label => $count) {
        if ($count <= 0) continue;
        $contactPie[] = ['name' => $label, 'y' => $count];
    }

    $allOrigen = [];
    foreach ($leads as $lead) {
        $label = getDondeNosConocioLabel($lead);
        $allOrigen[$label] = ($allOrigen[$label] ?? 0) + 1;
    }
    arsort($allOrigen);
    $origenPie = [];
    foreach ($allOrigen as $label => $count) {
        if ($count <= 0) continue;
        $origenPie[] = ['name' => $label, 'y' => $count];
    }

    return [
        'total'               => count($leads),
        'contactMethodCounts' => $allContacts,
        'contactPie'          => $contactPie,
        'origenCounts'        => $allOrigen,
        'origenPie'           => $origenPie,
        'regular'             => $regular,
        'afianzado'           => $afianzado,
    ];
}

// Excluir muertos solo de la etapa de clientes (para alinear conteos con consulta_leads.php y consulta_post_leads.php,
// que incluyen leads "muerto" en sus totales de PRE y POST)
$clientStage = array_values(array_filter($clientStage, function($l) use ($postAppointmentsByClient) {
    $_cid = intval($l['id'] ?? 0);
    if ($_cid > 0 && isset($postAppointmentsByClient[$_cid])) {
        $_calEst = $postAppointmentsByClient[$_cid]['estatus'] ?? null;
        if (is_numeric($_calEst) && intval($_calEst) === 3) return false;
    }
    return true;
}));

$preData    = buildStageData($preStage);
$postData   = buildStageData($postStage);
$clientData = buildStageData($clientStage);

// JSON encode for charts
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$preContactPieJson    = json_encode($preData['contactPie'], $jsonFlags);
$preOrigenPieJson     = json_encode($preData['origenPie'], $jsonFlags);
$postContactPieJson   = json_encode($postData['contactPie'], $jsonFlags);
$postOrigenPieJson    = json_encode($postData['origenPie'], $jsonFlags);
$clientContactPieJson = json_encode($clientData['contactPie'], $jsonFlags);
$clientOrigenPieJson  = json_encode($clientData['origenPie'], $jsonFlags);

// Split chart JSON (regular vs afianzado)
$preContactLeadsPieJson  = json_encode($preData['regular']['contactPie'],      $jsonFlags);
$preContactAfzPieJson    = json_encode($preData['afianzado']['contactPie'],    $jsonFlags);
$preOrigenLeadsPieJson   = json_encode($preData['regular']['origenPie'],       $jsonFlags);
$preOrigenAfzPieJson     = json_encode($preData['afianzado']['origenPie'],     $jsonFlags);
$postContactLeadsPieJson = json_encode($postData['regular']['contactPie'],     $jsonFlags);
$postContactAfzPieJson   = json_encode($postData['afianzado']['contactPie'],   $jsonFlags);
$postOrigenLeadsPieJson  = json_encode($postData['regular']['origenPie'],      $jsonFlags);
$postOrigenAfzPieJson    = json_encode($postData['afianzado']['origenPie'],    $jsonFlags);
$cliContactLeadsPieJson  = json_encode($clientData['regular']['contactPie'],   $jsonFlags);
$cliContactAfzPieJson    = json_encode($clientData['afianzado']['contactPie'], $jsonFlags);
$cliOrigenLeadsPieJson   = json_encode($clientData['regular']['origenPie'],    $jsonFlags);
$cliOrigenAfzPieJson     = json_encode($clientData['afianzado']['origenPie'],  $jsonFlags);

// POST status breakdown
$postStatusCounts = ['agendado' => 0, 'atendido' => 0, 'fantasma' => 0, 'muerto' => 0, 'cliente' => 0];
foreach ($postStage as $lead) {
    $st = $lead['_status'] ?? 'agendado';
    if (isset($postStatusCounts[$st])) $postStatusCounts[$st]++;
}

// Conversion rates
$convPreToPost = $preData['total'] > 0 ? round(($postData['total'] / $preData['total']) * 100, 1) : 0;
$convPostToClient = $postData['total'] > 0 ? round(($clientData['total'] / $postData['total']) * 100, 1) : 0;
$convPreToClient = $preData['total'] > 0 ? round(($clientData['total'] / $preData['total']) * 100, 1) : 0;

$statusLabels = [
    'agendado' => ['label' => 'Agendado', 'color' => '#f59e0b', 'icon' => 'fa-clock'],
    'atendido' => ['label' => 'Atendido', 'color' => '#16a34a', 'icon' => 'fa-check-circle'],
    'fantasma' => ['label' => 'Fantasma', 'color' => '#94a3b8', 'icon' => 'fa-ghost'],
    'muerto'   => ['label' => 'Muerto',   'color' => '#ef4444', 'icon' => 'fa-times-circle'],
    'cliente'  => ['label' => 'Cliente',   'color' => '#2563eb', 'icon' => 'fa-star'],
];
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>
<style>
:root {
    --gold: #C5A028;
    --gold-dim: rgba(197,160,40,0.12);
    --dark: #F8FAFC;
    --panel: #FFFFFF;
    --surface: #F1F5F9;
    --ink: #1E293B;
    --ink-soft: #475569;
    --muted: #64748B;
    --border: #E2E8F0;
    --white: #FFFFFF;
    --radius: 12px;
    --pre-color: #464646;
    --post-color: #464646;
    --client-color: #464646;
}

body { background: var(--dark) !important; }

.efege-page {
    padding: 0 20px 60px;
    font-family: 'DM Sans', system-ui, sans-serif;
}

.efege-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 20px 0 16px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border);
}
.efege-page-header-left { flex: 1; min-width: 0; }
.efege-page-header-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.efege-page-title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 26px;
    font-weight: 600;
    letter-spacing: 2px;
    color: var(--ink);
    line-height: 1;
    margin-bottom: 4px;
}
.efege-title-accent { color: var(--gold); }
.efege-page-title-sub {
    font-size: 14px;
    letter-spacing: 1px;
    font-weight: 400;
    color: var(--muted);
    font-family: 'DM Sans', system-ui, sans-serif;
}
.efege-page-subtitle { font-size: 12px; color: var(--muted); margin-top: 4px; }
.efege-date-range {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    color: var(--muted);
}
.efege-date-range span { color: var(--ink); font-weight: 600; }

/* ── FILTER BAR ───────────────────────────────── */
.filter-bar {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 20px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    position: sticky;
    top: 20px;
    z-index: 100;
}
.filter-label {
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    margin-right: 4px;
    flex-shrink: 0;
}
.efege-filter-input,
.efege-filter-select {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 7px;
    color: var(--ink);
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 12px;
    padding: 7px 12px;
    outline: none;
    transition: border-color 0.2s;
    appearance: none;
    -webkit-appearance: none;
}
.efege-filter-select {
    padding-right: 28px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748B' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    cursor: pointer;
}
.efege-filter-input:focus,
.efege-filter-select:focus { border-color: var(--gold); }
.efege-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--ink);
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    font-family: 'DM Sans', system-ui, sans-serif;
    white-space: nowrap;
}
.efege-btn:hover { border-color: var(--gold); color: var(--gold); text-decoration: none; }
.efege-btn-primary { background: var(--gold); border-color: var(--gold); color: #fff; }
.efege-btn-primary:hover { background: #b8921f; border-color: #b8921f; color: #fff; }

/* ── REPORTS SECTION ──────────────────────────── */
.reports-section {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 16px;
}
.reports-section-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.reports-section-step {
    font-size: 11px;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    color: var(--gold);
    font-weight: 700;
    margin-bottom: 6px;
}
.reports-section-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--ink);
    margin: 0 0 4px;
}
.reports-section-subtitle {
    font-size: 12px;
    color: var(--muted);
    margin: 0;
}
.reports-range-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 999px;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--muted);
    font-size: 12px;
}

/* ── REPORT CARDS ─────────────────────────────── */
.report-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px;
}
.report-card-highlight {
    background: linear-gradient(135deg, rgba(197,160,40,0.10), rgba(197,160,40,0.04));
    border-color: rgba(197,160,40,0.22);
}
.report-card-label {
    font-size: 11px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 10px;
}
.report-card-value {
    font-size: 30px;
    line-height: 1;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 8px;
}
.report-card-note {
    font-size: 12px;
    color: var(--muted);
}
.report-formula {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px dashed var(--border);
    font-size: 12px;
    color: var(--ink-soft);
}
.report-formula strong {
    color: var(--ink);
}

/* ── REPORT CHART CARDS ──────────────────────── */
.report-chart-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px;
}
.report-chart-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
}
.report-chart-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
    margin: 0 0 4px;
}
.report-chart-subtitle {
    font-size: 12px;
    color: var(--muted);
    margin: 0;
}
.report-chart {
    width: 100%;
    min-height: 280px;
}

/* ── FUNNEL ROW ──────────────────────────────── */
.pipeline-funnel-row {
    display: flex;
    align-items: stretch;
    justify-content: center;
    gap: 0;
    margin-bottom: 16px;
}
.funnel-card {
    flex: 1;
    max-width: 320px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px 18px 20px;
    text-align: center;
    border-top: 4px solid transparent;
    transition: all 0.2s;
}
.funnel-card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    transform: translateY(-2px);
}
.funnel-card--pre    { border-top-color: var(--pre-color); }
.funnel-card--post   { border-top-color: var(--post-color); }
.funnel-card--client { border-top-color: var(--client-color); }
.funnel-card-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    margin-bottom: 12px;
}
.funnel-card--pre    .funnel-card-icon { background: rgba(70,70,70,0.12); color: #464646; }
.funnel-card--post   .funnel-card-icon { background: rgba(70,70,70,0.12); color: #464646; }
.funnel-card--client .funnel-card-icon { background: rgba(70,70,70,0.12); color: #464646; }
.funnel-card-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 8px;
}
.funnel-card--pre    .funnel-card-label { color: var(--pre-color); }
.funnel-card--post   .funnel-card-label { color: var(--post-color); }
.funnel-card--client .funnel-card-label { color: var(--client-color); }
.funnel-card-value {
    font-size: 36px;
    font-weight: 700;
    color: var(--ink);
    line-height: 1;
    margin-bottom: 6px;
}
.funnel-card-desc {
    font-size: 11px;
    color: var(--muted);
}
.funnel-arrow {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0 10px;
    flex-shrink: 0;
}
.funnel-arrow i {
    font-size: 20px;
    color: var(--muted);
}
.funnel-arrow-pct {
    font-size: 10px;
    font-weight: 700;
    color: var(--ink);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 3px 10px;
    margin-top: 6px;
    white-space: nowrap;
}
.funnel-global-rate {
    text-align: center;
    margin: 0 0 24px;
    font-size: 12px;
    color: var(--muted);
}
.funnel-global-rate strong {
    color: var(--ink);
}

/* ── STAGE SECTION ───────────────────────────── */
.stage-section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
}
.stage-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.stage-dot--pre    { background: var(--pre-color); }
.stage-dot--post   { background: var(--post-color); }
.stage-dot--client { background: var(--client-color); }
.stage-section-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--ink);
    margin: 0;
}
.stage-section-subtitle {
    font-size: 12px;
    color: var(--muted);
    margin: 0 0 0 auto;
    font-weight: 600;
}

/* ── STAGE ROW ───────────────────────────────── */
.stage-row {
    display: flex;
    gap: 12px;
    width: 100%;
    align-items: stretch;
}
.stage-block {
    flex: 1 1 0;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0;
    align-items: stretch;
}
.report-chart-card {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
}
.report-chart-card .report-chart {
    flex: 1 1 auto;
    min-height: 260px;
}
.stage-block-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: var(--muted);
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
}

/* ── CONTEO TABLE ──────────────────────────── */
.conteo-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    margin-bottom: 16px;
    font-family: 'DM Sans', system-ui, sans-serif;
}
.conteo-table th {
    text-align: left;
    padding: 7px 10px;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
    font-weight: 600;
}
.conteo-table th:nth-child(2),
.conteo-table th:nth-child(3) {
    text-align: right;
    width: 60px;
}
.conteo-table td {
    padding: 8px 10px;
    color: var(--ink);
    border-bottom: 1px solid rgba(0,0,0,0.04);
}
.conteo-table td:nth-child(2),
.conteo-table td:nth-child(3) {
    text-align: right;
    font-weight: 600;
}
.conteo-table tr:last-child td {
    border-bottom: none;
}
.conteo-table tbody tr:hover td {
    background: rgba(0,0,0,0.02);
}
.conteo-total-row td {
    font-weight: 700;
    color: var(--ink);
    border-top: 1px solid var(--border);
    border-bottom: none;
    background: rgba(0,0,0,0.02) !important;
}
.pct-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 20px;
    background: rgba(0,0,0,0.04);
    font-size: 10px;
    font-weight: 600;
    color: var(--muted);
}

/* ── SECTION FILTER BAR ───────────────────── */
.section-filter-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.section-filter-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-filter-label {
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    font-weight: 600;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 5px;
}
.section-filter-bar .efege-filter-input {
    font-size: 11px;
    padding: 5px 8px;
}
.section-filter-active-label {
    font-size: 10px;
    font-weight: 600;
    color: var(--gold);
    background: var(--gold-dim);
    padding: 3px 8px;
    border-radius: 6px;
}

/* ── PIPELINE TABS ─────────────────────────── */
.pipeline-tabs {
    display: flex;
    gap: 4px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 6px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.pipeline-tab {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #464646;
    background: transparent;
    color: var(--muted);
    cursor: pointer;
    transition: all 0.18s;
    font-family: 'DM Sans', system-ui, sans-serif;
    white-space: nowrap;
    user-select: none;
}
.pipeline-tab:hover {
    background: var(--surface);
    color: var(--ink);
}
.pipeline-tab.active {
    background: var(--ink);
    color: #fff;
    border-color: #eee8dc;
}
.pipeline-tab .tab-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 20px;
    background: rgba(255,255,255,0.18);
    min-width: 22px;
    text-align: center;
}
.pipeline-tab:not(.active) .tab-badge {
    background: var(--surface);
    color: var(--ink-soft);
}
.pipeline-tab[data-tab="pre"].active   { background: var(--pre-color);    border-color: #eee8dc; }
.pipeline-tab[data-tab="post"].active  { background: var(--post-color);   border-color: #eee8dc; }
.pipeline-tab[data-tab="cli"].active   { background: var(--client-color); border-color: #eee8dc; }

/* ── STAGE MAIN SECTIONS ─────────────────────── */
.stage-main-section {
    margin-bottom: 24px;
}
.stage-main-section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
    padding: 10px 16px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: 3px solid var(--gold);
    border-radius: 10px;
}
.stage-main-section-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: var(--gold-dim);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-size: 13px;
    flex-shrink: 0;
}
.stage-main-section-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--ink);
    margin: 0;
    letter-spacing: 0.3px;
}
.stage-sub-header {
    font-size: 10px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--muted);
    margin-bottom: 10px;
    padding-bottom: 6px;
    border-bottom: 1px dashed var(--border);
    display: flex;
    align-items: center;
    gap: 6px;
}
.stage-sub-header .sub-badge {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 1px 8px;
    font-size: 10px;
    font-weight: 700;
    color: var(--ink-soft);
    margin-left: 2px;
}

/* ── CARD SPLIT ──────────────────────────────── */
.card-split {
    display: flex;
    gap: 0;
    align-items: stretch;
}
.card-split-half {
    flex: 1 1 0;
    min-width: 0;
    padding: 0 18px 0 0;
}
.card-split-half:last-child {
    padding: 0 0 0 18px;
}
.card-split-divider {
    width: 1px;
    background: var(--border);
    margin: 4px 0;
    flex-shrink: 0;
    align-self: stretch;
}
@media (max-width: 768px) {
    .card-split { flex-direction: column; }
    .card-split-half,
    .card-split-half:last-child { padding: 0; }
    .card-split-divider { width: auto; height: 1px; margin: 12px 0; }
}

.stage-sections-row {
    display: flex;
    gap: 16px;
    align-items: stretch;
}
.stage-sections-row > .stage-main-section {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}
.stage-sections-row > .stage-main-section > .report-chart-card {
    flex: 1;
}
@media (max-width: 900px) {
    .stage-sections-row { flex-direction: column; }
}

/* ── PIPELINE DATATABLE ───────────────────────────── */
.pipeline-dt-wrap {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px 20px;
    margin-top: 16px;
}
.pipeline-dt-wrap .dt-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}
.pipeline-dt-wrap .dt-header-icon {
    width: 28px; height: 28px;
    border-radius: 8px;
    background: var(--gold-dim);
    display: inline-flex; align-items: center; justify-content: center;
    color: var(--gold); font-size: 13px; flex-shrink: 0;
}
.pipeline-dt-wrap .dt-header-title {
    font-size: 14px; font-weight: 700; color: var(--ink); margin: 0;
}
.pipeline-dt-wrap .dt-header-badge {
    margin-left: auto;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 11px; font-weight: 700; color: var(--ink-soft);
}
table.pipeline-dt {
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 12px; color: var(--ink); width: 100% !important;
}
table.pipeline-dt thead th {
    font-size: 10px; text-transform: uppercase; letter-spacing: 1px;
    font-weight: 700; color: var(--muted); background: var(--surface);
    border-bottom: 1px solid var(--border) !important;
    padding: 9px 12px !important; white-space: nowrap;
}
table.pipeline-dt tbody td {
    padding: 9px 12px !important; vertical-align: middle;
    border-bottom: 1px solid rgba(0,0,0,0.04) !important; color: var(--ink);
}
table.pipeline-dt tbody tr:hover td { background: rgba(197,160,40,0.04); }
.dt-name-main { font-weight: 600; font-size: 12px; }
.dt-name-sub  { font-size: 11px; color: var(--muted); }
.dt-status-badge {
    display: inline-block; padding: 2px 9px; border-radius: 20px;
    font-size: 10px; font-weight: 700; text-transform: capitalize; white-space: nowrap;
}
.dt-status-lead     { background: #f1f5f9; color: #475569; }
.dt-status-agendado { background: rgba(245,158,11,0.12); color: #b45309; }
.dt-status-atendido { background: rgba(22,163,74,0.12);  color: #15803d; }
.dt-status-fantasma,.dt-status-muerto { background: rgba(148,163,184,0.18); color: #64748b; }
.dt-status-cliente  { background: rgba(37,99,235,0.12);  color: #1d4ed8; }
.dt-type-badge {
    display: inline-block; padding: 2px 9px; border-radius: 20px;
    font-size: 10px; font-weight: 700; white-space: nowrap;
}
.dt-type-lead { background: var(--surface); color: var(--muted); border: 1px solid var(--border); }
.dt-type-afz  { background: rgba(197,160,40,0.14); color: #92710a; border: 1px solid rgba(197,160,40,0.3); }

/* ── RESPONSIVE ─────────────────────────────── */
@media (max-width: 900px) {
    .stage-row { flex-direction: column; }
}
@media (max-width: 768px) {
    .pipeline-funnel-row {
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }
    .funnel-card { max-width: 100%; width: 100%; }
    .funnel-arrow {
        flex-direction: row;
        padding: 4px 0;
    }
    .funnel-arrow i { transform: rotate(90deg); }
}

</style>
</head>

<body>
<div class="efege-page">

    <!-- PAGE HEADER -->
    <div class="efege-page-header">
        <div class="efege-page-header-left">
            <div class="efege-page-title"><span class="efege-page-title-sub">Pipeline Report</span></div>
            <div class="efege-page-subtitle">Vista consolidada de PRE (Registros), POST (Agendas) y CLIENTES (Cierres).</div>
        </div>
        <div class="efege-page-header-right">
            <div class="efege-date-range">📅 <span><?php echo htmlspecialchars($reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></span></div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <form method="get" class="filter-bar" id="pipelineFilterForm">
        <span class="filter-label">Filtrar</span>
        <input type="date" name="start_date" class="efege-filter-input" value="<?php echo htmlspecialchars($startDate ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Desde">
        <input type="date" name="end_date" class="efege-filter-input" value="<?php echo htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Hasta">
        <button type="submit" class="efege-btn efege-btn-primary" style="background:#0f172a; border-color:#0f172a; color:#fff;"><i class="fas fa-filter"></i> Filtrar</button>

        <div style="display:flex;align-items:center;gap:6px;margin-left:auto;">
            <span class="filter-label">Año</span>
            <select id="pipeFilterYear" class="efege-filter-select">
                <?php foreach ($navYears as $yr): ?>
                <option value="<?php echo $yr; ?>" <?php echo $yr == $navSelectedYear ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                <?php endforeach; ?>
            </select>

            <span class="filter-label">Mes</span>
            <select id="pipeFilterMonth" class="efege-filter-select">
                <?php
                $availableMonths = $navYearMonths[$navSelectedYear] ?? [];
                if (empty($availableMonths)) {
                    $availableMonths = range(1, 12);
                }
                foreach ($availableMonths as $mo):
                ?>
                <option value="<?php echo $mo; ?>" <?php echo $mo == $navSelectedMonth ? 'selected' : ''; ?>><?php echo $navMonthNames[$mo]; ?></option>
                <?php endforeach; ?>
            </select>

            <span class="filter-label">Semana</span>
            <select id="pipeFilterWeek" class="efege-filter-select">
                <?php
                foreach ($navWeeksData as $wd) {
                    if ($wd['year'] == $navSelectedYear && ($wd['startMonth'] == $navSelectedMonth || $wd['endMonth'] == $navSelectedMonth)) {
                        $sel = ($wd['week'] === $navSelectedWeek) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($wd['week']) . '" ' . $sel . '>' . htmlspecialchars($wd['label']) . '</option>';
                    }
                }
                ?>
            </select>
        </div>

        <a href="reporte_pipeline.php?show_all=1" class="efege-btn"><i class="fas fa-times"></i> Limpiar</a>
        <?php foreach (['pre_start'=>$preStart,'pre_end'=>$preEnd,'post_start'=>$postStart,'post_end'=>$postEnd,'cli_start'=>$cliStart,'cli_end'=>$cliEnd] as $_pn=>$_pv): ?>
        <?php if ($_pv !== ''): ?><input type="hidden" name="<?php echo $_pn; ?>" value="<?php echo htmlspecialchars($_pv); ?>"><?php endif; ?>
        <?php endforeach; ?>
    </form>

    <!-- TABS -->
    <div class="pipeline-tabs" id="pipelineTabs">
        <button class="pipeline-tab active" data-tab="all" type="button">
            <i class="fas fa-layer-group"></i> Todos
            <span class="tab-badge"><?php echo number_format($preData['total'] + $postData['total'] + $clientData['total']); ?></span>
        </button>
        <button class="pipeline-tab" data-tab="pre" type="button">
            <i class="fas fa-user-check"></i> Pre calificados
            <span class="tab-badge"><?php echo number_format($preData['total']); ?></span>
        </button>
        <button class="pipeline-tab" data-tab="post" type="button">
            <i class="fas fa-calendar-check"></i> Post calificados
            <span class="tab-badge"><?php echo number_format($postData['total']); ?></span>
        </button>
        <button class="pipeline-tab" data-tab="cli" type="button">
            <i class="fas fa-handshake"></i> Cerrados
            <span class="tab-badge"><?php echo number_format($clientData['total']); ?></span>
        </button>
    </div>

    <!-- ═══════════ PRE (Registros) ═══════════ -->
    <section class="reports-section" id="section-pre">
        <div class="stage-section-header">
            <span class="stage-dot stage-dot--pre"></span>
            <h2 class="stage-section-title">PRE — Registros</h2>
            <span class="stage-section-subtitle"><?php echo number_format($preData['total']); ?> registros</span>
        </div>
        <div class="section-filter-bar">
            <span class="section-filter-label"><i class="fas fa-sliders-h"></i> Filtro personalizado</span>
            <input type="date" class="efege-filter-input section-date" data-section="pre" data-field="start" value="<?php echo htmlspecialchars($preStart); ?>" placeholder="Desde">
            <input type="date" class="efege-filter-input section-date" data-section="pre" data-field="end" value="<?php echo htmlspecialchars($preEnd); ?>" placeholder="Hasta">

            <div class="section-filter-right">
                <select class="efege-filter-select section-year" data-section="pre" name="pre_year">
                    <option value="">Año</option>
                    <?php foreach ($navYears as $yr): ?>
                    <option value="<?php echo $yr; ?>" <?php echo $yr == $preYear ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="efege-filter-select section-month" data-section="pre" name="pre_month">
                    <option value="">Mes</option>
                    <?php foreach ($navMonthNames as $mo => $name): ?>
                    <option value="<?php echo $mo; ?>" <?php echo $mo == $preMonth ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="efege-filter-select section-week" data-section="pre" name="pre_week">
                    <option value="">Semana</option>
                    <?php foreach ($navWeeksData as $wd): ?>
                    <option value="<?php echo htmlspecialchars($wd['week']); ?>" <?php echo $wd['week'] == $preWeek ? 'selected' : ''; ?>><?php echo htmlspecialchars($wd['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="button" class="efege-btn section-filter-apply" data-section="pre" style="background:#0f172a;border-color:#0f172a;color:#fff;padding:5px 10px;font-size:11px;"><i class="fas fa-filter"></i> Filtrar</button>
            <?php if ($preStart !== '' || $preEnd !== '' || $preYear > 0 || $preMonth > 0 || $preWeek !== ''): ?>
            <button type="button" class="efege-btn section-filter-clear" data-section="pre" style="padding:5px 10px;font-size:11px;"><i class="fas fa-times"></i></button>
            <span class="section-filter-active-label">Filtro activo</span>
            <?php endif; ?>
        </div>
        <div class="stage-sections-row">
        <!-- Método de contacto -->
        <div class="stage-main-section">
            <div class="stage-main-section-header">
                <span class="stage-main-section-icon"><i class="fas fa-phone-alt"></i></span>
                <h3 class="stage-main-section-title">Método de contacto</h3>
            </div>
            <article class="report-chart-card">
                <div class="card-split">
                    <div class="card-split-half">
                        <div class="stage-sub-header"><i class="fas fa-users"></i> Leads <span class="sub-badge"><?php echo number_format($preData['regular']['total']); ?></span></div>
                        <table class="conteo-table">
                            <thead><tr><th>Método</th><th>Conteo</th><th>%</th></tr></thead>
                            <tbody>
                            <?php foreach ($preData['regular']['contactMethodCounts'] as $label => $count):
                                $pct = $preData['regular']['total'] > 0 ? round(($count / $preData['regular']['total']) * 100, 1) : 0; ?>
                                <tr><td><?php echo htmlspecialchars($label); ?></td><td><?php echo number_format($count); ?></td><td><span class="pct-badge"><?php echo $pct; ?>%</span></td></tr>
                            <?php endforeach; ?>
                            <tr class="conteo-total-row"><td>Total</td><td><?php echo number_format($preData['regular']['total']); ?></td><td></td></tr>
                            </tbody>
                        </table>
                        <div id="preContactLeadsChart" class="report-chart"></div>
                    </div>
                    <div class="card-split-divider"></div>
                    <div class="card-split-half">
                        <div class="stage-sub-header"><i class="fas fa-star"></i> WP Afianzado - Leads <span class="sub-badge"><?php echo number_format($preData['afianzado']['total']); ?></span></div>
                        <table class="conteo-table">
                            <thead><tr><th>Método</th><th>Conteo</th><th>%</th></tr></thead>
                            <tbody>
                            <?php foreach ($preData['afianzado']['contactMethodCounts'] as $label => $count):
                                $pct = $preData['afianzado']['total'] > 0 ? round(($count / $preData['afianzado']['total']) * 100, 1) : 0; ?>
                                <tr><td><?php echo htmlspecialchars($label); ?></td><td><?php echo number_format($count); ?></td><td><span class="pct-badge"><?php echo $pct; ?>%</span></td></tr>
                            <?php endforeach; ?>
                            <tr class="conteo-total-row"><td>Total</td><td><?php echo number_format($preData['afianzado']['total']); ?></td><td></td></tr>
                            </tbody>
                        </table>
                        <div id="preContactAfzChart" class="report-chart"></div>
                    </div>
                </div>
            </article>
        </div>

        <!-- Origen -->
        <div class="stage-main-section">
            <div class="stage-main-section-header">
                <span class="stage-main-section-icon"><i class="fas fa-map-marker-alt"></i></span>
                <h3 class="stage-main-section-title">Origen — ¿De dónde nos conoce?</h3>
            </div>
            <article class="report-chart-card">
                <div class="card-split">
                    <div class="card-split-half">
                        <div class="stage-sub-header"><i class="fas fa-users"></i> Leads <span class="sub-badge"><?php echo number_format($preData['regular']['total']); ?></span></div>
                        <table class="conteo-table">
                            <thead><tr><th>Origen</th><th>Conteo</th><th>%</th></tr></thead>
                            <tbody>
                            <?php foreach ($preData['regular']['origenCounts'] as $label => $count):
                                $pct = $preData['regular']['total'] > 0 ? round(($count / $preData['regular']['total']) * 100, 1) : 0; ?>
                                <tr><td><?php echo htmlspecialchars($label); ?></td><td><?php echo number_format($count); ?></td><td><span class="pct-badge"><?php echo $pct; ?>%</span></td></tr>
                            <?php endforeach; ?>
                            <tr class="conteo-total-row"><td>Total</td><td><?php echo number_format($preData['regular']['total']); ?></td><td></td></tr>
                            </tbody>
                        </table>
                        <div id="preOrigenLeadsChart" class="report-chart"></div>
                    </div>
                    <div class="card-split-divider"></div>
                    <div class="card-split-half">
                        <div class="stage-sub-header"><i class="fas fa-star"></i> WP Afianzado - Leads <span class="sub-badge"><?php echo number_format($preData['afianzado']['total']); ?></span></div>
                        <table class="conteo-table">
                            <thead><tr><th>Origen</th><th>Conteo</th><th>%</th></tr></thead>
                            <tbody>
                            <?php foreach ($preData['afianzado']['origenCounts'] as $label => $count):
                                $pct = $preData['afianzado']['total'] > 0 ? round(($count / $preData['afianzado']['total']) * 100, 1) : 0; ?>
                                <tr><td><?php echo htmlspecialchars($label); ?></td><td><?php echo number_format($count); ?></td><td><span class="pct-badge"><?php echo $pct; ?>%</span></td></tr>
                            <?php endforeach; ?>
                            <tr class="conteo-total-row"><td>Total</td><td><?php echo number_format($preData['afianzado']['total']); ?></td><td></td></tr>
                            </tbody>
                        </table>
                        <div id="preOrigenAfzChart" class="report-chart"></div>
                    </div>
                </div>
            </article>
        </div>
        </div><!-- /.stage-sections-row -->

        <!-- DataTable — Registros (PRE) -->
        <div class="pipeline-dt-wrap">
            <div class="dt-header">
                <span class="dt-header-icon"><i class="fas fa-table"></i></span>
                <h3 class="dt-header-title">Registros</h3>
                <span class="dt-header-badge"><?php echo number_format(count($preStage)); ?> registros</span>
            </div>
            <table id="dtPre" class="pipeline-dt table table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Fecha registro</th>
                        <th>Método contacto</th>
                        <th>Origen</th>
                        <th>Campaña</th>
                        <th>Estatus</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($preStage as $r):
                    $rName    = htmlspecialchars($r['full_name'] ?? $r['names'] ?? '', ENT_QUOTES, 'UTF-8');
                    $rEmail   = htmlspecialchars($r['email'] ?? '', ENT_QUOTES, 'UTF-8');
                    $rPhone   = htmlspecialchars($r['phone'] ?? '', ENT_QUOTES, 'UTF-8');
                    $rDateRaw = $r['_date'] ?? '';
                    $rDateTs  = $rDateRaw !== '' ? strtotime($rDateRaw) : false;
                    $rDateFmt = ($rDateTs !== false && $rDateTs > 0) ? date('d/m/Y', $rDateTs) : '—';
                    $rDateOrd = ($rDateTs !== false && $rDateTs > 0) ? date('Y-m-d', $rDateTs) : '';
                    $rContacto = htmlspecialchars(normalizeFirstContactChannelLabel($r['first_contact_channel'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $rCampaign = htmlspecialchars(formatCampaignDisplay($r['campaign_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $rOrigen   = htmlspecialchars(getDondeNosConocioLabel($r), ENT_QUOTES, 'UTF-8');
                    $rStatus   = $r['_status'] ?? 'lead';
                    $rIsAfz    = strtolower(trim($r['tabla_origen'] ?? '')) === 'wedding_planners' && intval($r['afianzado'] ?? 0) === 1;
                ?>
                <tr>
                    <td>
                        <div class="dt-name-main"><?php echo $rName; ?></div>
                        <?php if ($rEmail): ?><div class="dt-name-sub"><?php echo $rEmail; ?></div><?php endif; ?>
                    </td>
                    <td><?php echo $rEmail ?: '—'; ?></td>
                    <td><?php echo $rPhone ?: '—'; ?></td>
                    <td data-order="<?php echo htmlspecialchars($rDateOrd); ?>"><?php echo $rDateFmt; ?></td>
                    <td><?php echo $rContacto; ?></td>
                    <td><?php echo $rOrigen; ?></td>
                    <td><?php echo $rCampaign ?: '—'; ?></td>
                    <td><span class="dt-status-badge dt-status-<?php echo htmlspecialchars($rStatus); ?>"><?php echo ucfirst($rStatus); ?></span></td>
                    <td><?php if ($rIsAfz): ?><span class="dt-type-badge dt-type-afz"><i class="fas fa-star" style="font-size:9px;"></i> WP Afianzado</span><?php else: ?><span class="dt-type-badge dt-type-lead">Lead</span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ═══════════ POST (Agendas) ═══════════ -->
    <section class="reports-section" id="section-post">
        <div class="stage-section-header">
            <span class="stage-dot stage-dot--post"></span>
            <h2 class="stage-section-title">POST — Agendas</h2>
            <span class="stage-section-subtitle"><?php echo number_format($postData['total']); ?> agendados</span>
        </div>
        <div class="section-filter-bar">
            <span class="section-filter-label"><i class="fas fa-sliders-h"></i> Filtro personalizado</span>
            <input type="date" class="efege-filter-input section-date" data-section="post" data-field="start" value="<?php echo htmlspecialchars($postStart); ?>" placeholder="Desde">
            <input type="date" class="efege-filter-input section-date" data-section="post" data-field="end" value="<?php echo htmlspecialchars($postEnd); ?>" placeholder="Hasta">

            <div class="section-filter-right">
                <select class="efege-filter-select section-year" data-section="post" name="post_year">
                    <option value="">Año</option>
                    <?php foreach ($navYears as $yr): ?>
                    <option value="<?php echo $yr; ?>" <?php echo $yr == $postYear ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="efege-filter-select section-month" data-section="post" name="post_month">
                    <option value="">Mes</option>
                    <?php foreach ($navMonthNames as $mo => $name): ?>
                    <option value="<?php echo $mo; ?>" <?php echo $mo == $postMonth ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="efege-filter-select section-week" data-section="post" name="post_week">
                    <option value="">Semana</option>
                    <?php foreach ($navWeeksData as $wd): ?>
                    <option value="<?php echo htmlspecialchars($wd['week']); ?>" <?php echo $wd['week'] == $postWeek ? 'selected' : ''; ?>><?php echo htmlspecialchars($wd['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="button" class="efege-btn section-filter-apply" data-section="post" style="background:#0f172a;border-color:#0f172a;color:#fff;padding:5px 10px;font-size:11px;"><i class="fas fa-filter"></i> Filtrar</button>
            <?php if ($postStart !== '' || $postEnd !== '' || $postYear > 0 || $postMonth > 0 || $postWeek !== ''): ?>
            <button type="button" class="efege-btn section-filter-clear" data-section="post" style="padding:5px 10px;font-size:11px;"><i class="fas fa-times"></i></button>
            <span class="section-filter-active-label">Filtro activo</span>
            <?php endif; ?>
        </div>
        <div class="stage-sections-row">
        <!-- Método de contacto -->
        <div class="stage-main-section">
            <div class="stage-main-section-header">
                <span class="stage-main-section-icon"><i class="fas fa-phone-alt"></i></span>
                <h3 class="stage-main-section-title">Método de contacto</h3>
            </div>
            <article class="report-chart-card">
                <div class="card-split">
                    <div class="card-split-half">
                        <div class="stage-sub-header"><i class="fas fa-users"></i> Agendas <span class="sub-badge"><?php echo number_format($postData['regular']['total']); ?></span></div>
                        <table class="conteo-table">
                            <thead><tr><th>Método</th><th>Conteo</th><th>%</th></tr></thead>
                            <tbody>
                            <?php foreach ($postData['regular']['contactMethodCounts'] as $label => $count):
                                $pct = $postData['regular']['total'] > 0 ? round(($count / $postData['regular']['total']) * 100, 1) : 0; ?>
                                <tr><td><?php echo htmlspecialchars($label); ?></td><td><?php echo number_format($count); ?></td><td><span class="pct-badge"><?php echo $pct; ?>%</span></td></tr>
                            <?php endforeach; ?>
                            <tr class="conteo-total-row"><td>Total</td><td><?php echo number_format($postData['regular']['total']); ?></td><td></td></tr>
                            </tbody>
                        </table>
                        <div id="postContactLeadsChart" class="report-chart"></div>
                    </div>
                    <div class="card-split-divider"></div>
                    <div class="card-split-half">
                        <div class="stage-sub-header"><i class="fas fa-star"></i> WP Afianzado - Agendas <span class="sub-badge"><?php echo number_format($postData['afianzado']['total']); ?></span></div>
                        <table class="conteo-table">
                            <thead><tr><th>Método</th><th>Conteo</th><th>%</th></tr></thead>
                            <tbody>
                            <?php foreach ($postData['afianzado']['contactMethodCounts'] as $label => $count):
                                $pct = $postData['afianzado']['total'] > 0 ? round(($count / $postData['afianzado']['total']) * 100, 1) : 0; ?>
                                <tr><td><?php echo htmlspecialchars($label); ?></td><td><?php echo number_format($count); ?></td><td><span class="pct-badge"><?php echo $pct; ?>%</span></td></tr>
                            <?php endforeach; ?>
                            <tr class="conteo-total-row"><td>Total</td><td><?php echo number_format($postData['afianzado']['total']); ?></td><td></td></tr>
                            </tbody>
                        </table>
                        <div id="postContactAfzChart" class="report-chart"></div>
                    </div>
                </div>
            </article>
        </div>

        <!-- Origen -->
        <div class="stage-main-section">
            <div class="stage-main-section-header">
                <span class="stage-main-section-icon"><i class="fas fa-map-marker-alt"></i></span>
                <h3 class="stage-main-section-title">Origen — ¿De dónde nos conoce?</h3>
            </div>
            <article class="report-chart-card">
                <div class="card-split">
                    <div class="card-split-half">
                        <div class="stage-sub-header"><i class="fas fa-users"></i> Agendas <span class="sub-badge"><?php echo number_format($postData['regular']['total']); ?></span></div>
                        <table class="conteo-table">
                            <thead><tr><th>Origen</th><th>Conteo</th><th>%</th></tr></thead>
                            <tbody>
                            <?php foreach ($postData['regular']['origenCounts'] as $label => $count):
                                $pct = $postData['regular']['total'] > 0 ? round(($count / $postData['regular']['total']) * 100, 1) : 0; ?>
                                <tr><td><?php echo htmlspecialchars($label); ?></td><td><?php echo number_format($count); ?></td><td><span class="pct-badge"><?php echo $pct; ?>%</span></td></tr>
                            <?php endforeach; ?>
                            <tr class="conteo-total-row"><td>Total</td><td><?php echo number_format($postData['regular']['total']); ?></td><td></td></tr>
                            </tbody>
                        </table>
                        <div id="postOrigenLeadsChart" class="report-chart"></div>
                    </div>
                    <div class="card-split-divider"></div>
                    <div class="card-split-half">
                        <div class="stage-sub-header"><i class="fas fa-star"></i> WP Afianzado - Agendas <span class="sub-badge"><?php echo number_format($postData['afianzado']['total']); ?></span></div>
                        <table class="conteo-table">
                            <thead><tr><th>Origen</th><th>Conteo</th><th>%</th></tr></thead>
                            <tbody>
                            <?php foreach ($postData['afianzado']['origenCounts'] as $label => $count):
                                $pct = $postData['afianzado']['total'] > 0 ? round(($count / $postData['afianzado']['total']) * 100, 1) : 0; ?>
                                <tr><td><?php echo htmlspecialchars($label); ?></td><td><?php echo number_format($count); ?></td><td><span class="pct-badge"><?php echo $pct; ?>%</span></td></tr>
                            <?php endforeach; ?>
                            <tr class="conteo-total-row"><td>Total</td><td><?php echo number_format($postData['afianzado']['total']); ?></td><td></td></tr>
                            </tbody>
                        </table>
                        <div id="postOrigenAfzChart" class="report-chart"></div>
                    </div>
                </div>
            </article>
        </div>
        </div><!-- /.stage-sections-row -->

        <!-- DataTable — Agendas (POST) -->
        <div class="pipeline-dt-wrap">
            <div class="dt-header">
                <span class="dt-header-icon"><i class="fas fa-table"></i></span>
                <h3 class="dt-header-title">Agendas</h3>
                <span class="dt-header-badge"><?php echo number_format(count($postStage)); ?> registros</span>
            </div>
            <table id="dtPost" class="pipeline-dt table table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Fecha registro</th>
                        <th>Método contacto</th>
                        <th>Origen</th>
                        <th>Campaña</th>
                        <th>Estatus</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($postStage as $r):
                    $rName    = htmlspecialchars($r['full_name'] ?? $r['names'] ?? '', ENT_QUOTES, 'UTF-8');
                    $rEmail   = htmlspecialchars($r['email'] ?? '', ENT_QUOTES, 'UTF-8');
                    $rPhone   = htmlspecialchars($r['phone'] ?? '', ENT_QUOTES, 'UTF-8');
                    $rDateRaw = $r['_date'] ?? '';
                    $rDateTs  = $rDateRaw !== '' ? strtotime($rDateRaw) : false;
                    $rDateFmt = ($rDateTs !== false && $rDateTs > 0) ? date('d/m/Y', $rDateTs) : '—';
                    $rDateOrd = ($rDateTs !== false && $rDateTs > 0) ? date('Y-m-d', $rDateTs) : '';
                    $rContacto = htmlspecialchars(normalizeFirstContactChannelLabel($r['first_contact_channel'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $rCampaign = htmlspecialchars(formatCampaignDisplay($r['campaign_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $rOrigen   = htmlspecialchars(getDondeNosConocioLabel($r), ENT_QUOTES, 'UTF-8');
                    $rStatus   = $r['_status'] ?? 'agendado';
                    $rIsAfz    = strtolower(trim($r['tabla_origen'] ?? '')) === 'wedding_planners' && intval($r['afianzado'] ?? 0) === 1;
                ?>
                <tr>
                    <td>
                        <div class="dt-name-main"><?php echo $rName; ?></div>
                        <?php if ($rEmail): ?><div class="dt-name-sub"><?php echo $rEmail; ?></div><?php endif; ?>
                    </td>
                    <td><?php echo $rEmail ?: '—'; ?></td>
                    <td><?php echo $rPhone ?: '—'; ?></td>
                    <td data-order="<?php echo htmlspecialchars($rDateOrd); ?>"><?php echo $rDateFmt; ?></td>
                    <td><?php echo $rContacto; ?></td>
                    <td><?php echo $rOrigen; ?></td>
                    <td><?php echo $rCampaign ?: '—'; ?></td>
                    <td><span class="dt-status-badge dt-status-<?php echo htmlspecialchars($rStatus); ?>"><?php echo ucfirst($rStatus); ?></span></td>
                    <td><?php if ($rIsAfz): ?><span class="dt-type-badge dt-type-afz"><i class="fas fa-star" style="font-size:9px;"></i> WP Afianzado</span><?php else: ?><span class="dt-type-badge dt-type-lead">Lead</span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ═══════════ CLIENTES (Cierres) ═══════════ -->
    <section class="reports-section" id="section-cli">
        <div class="stage-section-header">
            <span class="stage-dot stage-dot--client"></span>
            <h2 class="stage-section-title">CLIENTES — Cierres</h2>
            <span class="stage-section-subtitle"><?php echo number_format($clientData['total']); ?> cierres</span>
        </div>
        <div class="section-filter-bar">
            <span class="section-filter-label"><i class="fas fa-sliders-h"></i> Filtro personalizado</span>
            <input type="date" class="efege-filter-input section-date" data-section="cli" data-field="start" value="<?php echo htmlspecialchars($cliStart); ?>" placeholder="Desde">
            <input type="date" class="efege-filter-input section-date" data-section="cli" data-field="end" value="<?php echo htmlspecialchars($cliEnd); ?>" placeholder="Hasta">

            <div class="section-filter-right">
                <select class="efege-filter-select section-year" data-section="cli" name="cli_year">
                    <option value="">Año</option>
                    <?php foreach ($navYears as $yr): ?>
                    <option value="<?php echo $yr; ?>" <?php echo $yr == $cliYear ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="efege-filter-select section-month" data-section="cli" name="cli_month">
                    <option value="">Mes</option>
                    <?php foreach ($navMonthNames as $mo => $name): ?>
                    <option value="<?php echo $mo; ?>" <?php echo $mo == $cliMonth ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>

                <select class="efege-filter-select section-week" data-section="cli" name="cli_week">
                    <option value="">Semana</option>
                    <?php foreach ($navWeeksData as $wd): ?>
                    <option value="<?php echo htmlspecialchars($wd['week']); ?>" <?php echo $wd['week'] == $cliWeek ? 'selected' : ''; ?>><?php echo htmlspecialchars($wd['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="button" class="efege-btn section-filter-apply" data-section="cli" style="background:#0f172a;border-color:#0f172a;color:#fff;padding:5px 10px;font-size:11px;"><i class="fas fa-filter"></i> Filtrar</button>
            <?php if ($cliStart !== '' || $cliEnd !== '' || $cliYear > 0 || $cliMonth > 0 || $cliWeek !== ''): ?>
            <button type="button" class="efege-btn section-filter-clear" data-section="cli" style="padding:5px 10px;font-size:11px;"><i class="fas fa-times"></i></button>
            <span class="section-filter-active-label">Filtro activo</span>
            <?php endif; ?>
        </div>
        <div class="stage-sections-row">
        <!-- Método de contacto -->
        <div class="stage-main-section">
            <div class="stage-main-section-header">
                <span class="stage-main-section-icon"><i class="fas fa-phone-alt"></i></span>
                <h3 class="stage-main-section-title">Método de contacto</h3>
            </div>
            <article class="report-chart-card">
                <div class="card-split">
                    <div class="card-split-half">
                        <div class="stage-sub-header"><i class="fas fa-users"></i> Clientes <span class="sub-badge"><?php echo number_format($clientData['regular']['total']); ?></span></div>
                        <table class="conteo-table">
                            <thead><tr><th>Método</th><th>Conteo</th><th>%</th></tr></thead>
                            <tbody>
                            <?php foreach ($clientData['regular']['contactMethodCounts'] as $label => $count):
                                $pct = $clientData['regular']['total'] > 0 ? round(($count / $clientData['regular']['total']) * 100, 1) : 0; ?>
                                <tr><td><?php echo htmlspecialchars($label); ?></td><td><?php echo number_format($count); ?></td><td><span class="pct-badge"><?php echo $pct; ?>%</span></td></tr>
                            <?php endforeach; ?>
                            <tr class="conteo-total-row"><td>Total</td><td><?php echo number_format($clientData['regular']['total']); ?></td><td></td></tr>
                            </tbody>
                        </table>
                        <div id="cliContactLeadsChart" class="report-chart"></div>
                    </div>
                    <div class="card-split-divider"></div>
                    <div class="card-split-half">
                        <div class="stage-sub-header"><i class="fas fa-star"></i> WP Afianzado - Clientes <span class="sub-badge"><?php echo number_format($clientData['afianzado']['total']); ?></span></div>
                        <table class="conteo-table">
                            <thead><tr><th>Método</th><th>Conteo</th><th>%</th></tr></thead>
                            <tbody>
                            <?php foreach ($clientData['afianzado']['contactMethodCounts'] as $label => $count):
                                $pct = $clientData['afianzado']['total'] > 0 ? round(($count / $clientData['afianzado']['total']) * 100, 1) : 0; ?>
                                <tr><td><?php echo htmlspecialchars($label); ?></td><td><?php echo number_format($count); ?></td><td><span class="pct-badge"><?php echo $pct; ?>%</span></td></tr>
                            <?php endforeach; ?>
                            <tr class="conteo-total-row"><td>Total</td><td><?php echo number_format($clientData['afianzado']['total']); ?></td><td></td></tr>
                            </tbody>
                        </table>
                        <div id="cliContactAfzChart" class="report-chart"></div>
                    </div>
                </div>
            </article>
        </div>

        <!-- Origen -->
        <div class="stage-main-section">
            <div class="stage-main-section-header">
                <span class="stage-main-section-icon"><i class="fas fa-map-marker-alt"></i></span>
                <h3 class="stage-main-section-title">Origen — ¿De dónde nos conoce?</h3>
            </div>
            <article class="report-chart-card">
                <div class="card-split">
                    <div class="card-split-half">
                        <div class="stage-sub-header"><i class="fas fa-users"></i> Clientes <span class="sub-badge"><?php echo number_format($clientData['regular']['total']); ?></span></div>
                        <table class="conteo-table">
                            <thead><tr><th>Origen</th><th>Conteo</th><th>%</th></tr></thead>
                            <tbody>
                            <?php foreach ($clientData['regular']['origenCounts'] as $label => $count):
                                $pct = $clientData['regular']['total'] > 0 ? round(($count / $clientData['regular']['total']) * 100, 1) : 0; ?>
                                <tr><td><?php echo htmlspecialchars($label); ?></td><td><?php echo number_format($count); ?></td><td><span class="pct-badge"><?php echo $pct; ?>%</span></td></tr>
                            <?php endforeach; ?>
                            <tr class="conteo-total-row"><td>Total</td><td><?php echo number_format($clientData['regular']['total']); ?></td><td></td></tr>
                            </tbody>
                        </table>
                        <div id="cliOrigenLeadsChart" class="report-chart"></div>
                    </div>
                    <div class="card-split-divider"></div>
                    <div class="card-split-half">
                        <div class="stage-sub-header"><i class="fas fa-star"></i> WP Afianzado - Clientes <span class="sub-badge"><?php echo number_format($clientData['afianzado']['total']); ?></span></div>
                        <table class="conteo-table">
                            <thead><tr><th>Origen</th><th>Conteo</th><th>%</th></tr></thead>
                            <tbody>
                            <?php foreach ($clientData['afianzado']['origenCounts'] as $label => $count):
                                $pct = $clientData['afianzado']['total'] > 0 ? round(($count / $clientData['afianzado']['total']) * 100, 1) : 0; ?>
                                <tr><td><?php echo htmlspecialchars($label); ?></td><td><?php echo number_format($count); ?></td><td><span class="pct-badge"><?php echo $pct; ?>%</span></td></tr>
                            <?php endforeach; ?>
                            <tr class="conteo-total-row"><td>Total</td><td><?php echo number_format($clientData['afianzado']['total']); ?></td><td></td></tr>
                            </tbody>
                        </table>
                        <div id="cliOrigenAfzChart" class="report-chart"></div>
                    </div>
                </div>
            </article>
        </div>
        </div><!-- /.stage-sections-row -->

        <!-- DataTable — Cierres (CLI) -->
        <div class="pipeline-dt-wrap">
            <div class="dt-header">
                <span class="dt-header-icon"><i class="fas fa-table"></i></span>
                <h3 class="dt-header-title">Cierres</h3>
                <span class="dt-header-badge"><?php echo number_format(count($clientStage)); ?> registros</span>
            </div>
            <table id="dtCli" class="pipeline-dt table table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Fecha cierre</th>
                        <th>Monto de venta</th>
                        <th>Método contacto</th>
                        <th>Origen</th>
                        <th>Campaña</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($clientStage as $r):
                    $rName    = htmlspecialchars($r['full_name'] ?? $r['names'] ?? '', ENT_QUOTES, 'UTF-8');
                    $rEmail   = htmlspecialchars($r['email'] ?? '', ENT_QUOTES, 'UTF-8');
                    $rPhone   = htmlspecialchars($r['phone'] ?? '', ENT_QUOTES, 'UTF-8');
                    $rFcc     = $r['fecha_cambio_cliente'] ?? '';
                    $rFccTs   = $rFcc !== '' ? strtotime($rFcc) : false;
                    $rDateFmt = ($rFccTs !== false && $rFccTs > 0) ? date('d/m/Y', $rFccTs) : '—';
                    $rDateOrd = ($rFccTs !== false && $rFccTs > 0) ? date('Y-m-d', $rFccTs) : '';
                    $rContacto = htmlspecialchars(normalizeFirstContactChannelLabel($r['first_contact_channel'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $rCampaign = htmlspecialchars(formatCampaignDisplay($r['campaign_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $rOrigen   = htmlspecialchars(getDondeNosConocioLabel($r), ENT_QUOTES, 'UTF-8');
                    $rIsAfz    = strtolower(trim($r['tabla_origen'] ?? '')) === 'wedding_planners' && intval($r['afianzado'] ?? 0) === 1;
                    $montoRaw  = trim((string)($r['monto_venta'] ?? ''));
                    $montoOrder = 0;
                    $montoOut   = '—';
                    if ($montoRaw !== '') {
                        $montoNumeric = floatval(preg_replace('/[^0-9\.\-]/', '', $montoRaw));
                        if ($montoRaw === '0' || $montoRaw === '0.0' || $montoNumeric !== 0.0) {
                            $montoOrder = $montoNumeric;
                        }
                        $montoOut = '$' . number_format($montoNumeric, 2, '.', ',');
                    }
                ?>
                <tr>
                    <td>
                        <div class="dt-name-main"><?php echo $rName; ?></div>
                        <?php if ($rEmail): ?><div class="dt-name-sub"><?php echo $rEmail; ?></div><?php endif; ?>
                    </td>
                    <td><?php echo $rEmail ?: '—'; ?></td>
                    <td><?php echo $rPhone ?: '—'; ?></td>
                    <td data-order="<?php echo htmlspecialchars($rDateOrd); ?>"><?php echo $rDateFmt; ?></td>
                    <td data-order="<?php echo $montoOrder; ?>"><?php echo $montoOut; ?></td>
                    <td><?php echo $rContacto; ?></td>
                    <td><?php echo $rOrigen; ?></td>
                    <td><?php echo $rCampaign ?: '—'; ?></td>
                    <td><?php if ($rIsAfz): ?><span class="dt-type-badge dt-type-afz"><i class="fas fa-star" style="font-size:9px;"></i> WP Afianzado</span><?php else: ?><span class="dt-type-badge dt-type-lead">Lead</span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</div><!-- /.efege-page -->

<?php include 'footer.php'; ?>

<script>
$(document).ready(function () {
    var pieColors = ['#16a34a', '#2563eb', '#f59e0b', '#c026d3', '#0284c7', '#ef4444', '#94a3b8', '#64748b', '#8b5cf6', '#ec4899'];

    function buildPieOptions(unit) {
        return {
            chart: {
                type: 'pie', backgroundColor: 'transparent', borderRadius: 14,
                spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10,
                style: { fontFamily: 'DM Sans, system-ui, sans-serif' }
            },
            title: { text: null },
            colors: pieColors,
            tooltip: { pointFormat: '<b>{point.y}</b> ' + unit + ' ({point.percentage:.1f}%)', style: { fontSize: '12px' } },
            accessibility: { point: { valueSuffix: '%' } },
            plotOptions: {
                pie: {
                    innerSize: '48%', allowPointSelect: true, cursor: 'pointer',
                    dataLabels: { enabled: true, format: '<b>{point.name}</b><br>{point.y}', style: { fontSize: '11px', fontWeight: '600', color: '#1E293B' } },
                    showInLegend: true
                }
            },
            legend: { align: 'center', verticalAlign: 'bottom', itemStyle: { fontSize: '11px', fontWeight: '500', color: '#64748B' } },
            credits: { enabled: false }
        };
    }

    function renderPie(id, data, seriesName, unit) {
        if (data.length > 0) {
            Highcharts.chart(id, Object.assign({}, buildPieOptions(unit), {
                series: [{ name: seriesName, colorByPoint: true, data: data }]
            }));
        }
    }

    // ── PRE ──
    renderPie('preContactLeadsChart',  <?php echo $preContactLeadsPieJson; ?>,  'Método de contacto', 'registros');
    renderPie('preContactAfzChart',    <?php echo $preContactAfzPieJson; ?>,    'Método de contacto', 'registros');
    renderPie('preOrigenLeadsChart',   <?php echo $preOrigenLeadsPieJson; ?>,   'De dónde nos conoce', 'registros');
    renderPie('preOrigenAfzChart',     <?php echo $preOrigenAfzPieJson; ?>,     'De dónde nos conoce', 'registros');

    // ── POST ──
    renderPie('postContactLeadsChart', <?php echo $postContactLeadsPieJson; ?>, 'Método de contacto', 'agendados');
    renderPie('postContactAfzChart',   <?php echo $postContactAfzPieJson; ?>,   'Método de contacto', 'agendados');
    renderPie('postOrigenLeadsChart',  <?php echo $postOrigenLeadsPieJson; ?>,  'De dónde nos conoce', 'agendados');
    renderPie('postOrigenAfzChart',    <?php echo $postOrigenAfzPieJson; ?>,    'De dónde nos conoce', 'agendados');

    // ── CLIENTES ──
    renderPie('cliContactLeadsChart',  <?php echo $cliContactLeadsPieJson; ?>,  'Método de contacto', 'cierres');
    renderPie('cliContactAfzChart',    <?php echo $cliContactAfzPieJson; ?>,    'Método de contacto', 'cierres');
    renderPie('cliOrigenLeadsChart',   <?php echo $cliOrigenLeadsPieJson; ?>,   'De dónde nos conoce', 'cierres');
    renderPie('cliOrigenAfzChart',     <?php echo $cliOrigenAfzPieJson; ?>,     'De dónde nos conoce', 'cierres');

    // ── DATATABLES ──
    var dtLang = { url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json' };
    var dtPre  = $('#dtPre').DataTable({
        language: dtLang,
        pageLength: 25,
        order: [[3, 'desc']],
        columnDefs: [{ targets: [1, 2], visible: false }],
        dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6'f>>rtip"
    });
    var dtPost = $('#dtPost').DataTable({
        language: dtLang,
        pageLength: 25,
        order: [[3, 'desc']],
        columnDefs: [{ targets: [1, 2], visible: false }],
        dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6'f>>rtip"
    });
    var dtCli  = $('#dtCli').DataTable({
        language: dtLang,
        pageLength: 25,
        order: [[3, 'desc']],
        columnDefs: [{ targets: [1, 2], visible: false }],
        dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6'f>>rtip"
    });

    // ── PIPELINE TABS ──
    var sections = { pre: $('#section-pre'), post: $('#section-post'), cli: $('#section-cli') };
    var $tabs = $('#pipelineTabs .pipeline-tab');

    function showTab(tab) {
        $tabs.removeClass('active');
        $tabs.filter('[data-tab="' + tab + '"]').addClass('active');
        if (tab === 'all') {
            sections.pre.show();
            sections.post.show();
            sections.cli.show();
        } else {
            $.each(sections, function(key, $el) {
                if (key === tab) $el.show(); else $el.hide();
            });
        }
        // Redraw DataTables for newly-visible sections (they mis-calculate width when hidden)
        if (tab === 'all' || tab === 'pre')  { dtPre.columns.adjust().draw(false); }
        if (tab === 'all' || tab === 'post') { dtPost.columns.adjust().draw(false); }
        if (tab === 'all' || tab === 'cli')  { dtCli.columns.adjust().draw(false); }
        try { localStorage.setItem('pipelineTab', tab); } catch(e) {}
    }

    // Restore last tab
    var savedTab = 'all';
    try { savedTab = localStorage.getItem('pipelineTab') || 'all'; } catch(e) {}
    showTab(savedTab);

    $tabs.on('click', function() {
        showTab($(this).data('tab'));
    });

    // ── FILTROS CASCADA (Año → Mes → Semana) ──
    var navWeeksData = <?php echo json_encode($navWeeksData, JSON_UNESCAPED_UNICODE); ?>;
    var navYearMonths = <?php echo json_encode($navYearMonths, JSON_UNESCAPED_UNICODE); ?>;
    var navMonthNames = <?php echo json_encode($navMonthNames, JSON_UNESCAPED_UNICODE); ?>;
    var $pYear = $('#pipeFilterYear'), $pMonth = $('#pipeFilterMonth'), $pWeek = $('#pipeFilterWeek');

    function getMonthsForYear(yr) {
        var arr = navYearMonths[yr] || [];
        return arr.slice();
    }

    function getWeeksForYearMonth(yr, mo) {
        var arr = [];
        navWeeksData.forEach(function(d) {
            if (d.year == yr && (d.startMonth == mo || d.endMonth == mo)) arr.push(d);
        });
        return arr;
    }

    function getSectionParams() {
        var p = new URLSearchParams(window.location.search);
        var keys = ['pre_start','pre_end','post_start','post_end','cli_start','cli_end'];
        var r = '';
        keys.forEach(function(k) { if (p.has(k)) r += '&' + k + '=' + encodeURIComponent(p.get(k)); });
        return r;
    }

    $pYear.on('change', function() {
        var yr = parseInt(this.value);
        window.location = 'reporte_pipeline.php?year=' + yr + getSectionParams();
    });

    $pMonth.on('change', function() {
        var yr = parseInt($pYear.val());
        var mo = parseInt(this.value);
        window.location = 'reporte_pipeline.php?year=' + yr + '&month=' + mo + getSectionParams();
    });

    $pWeek.on('change', function() {
        var wk = this.value;
        if (wk) {
            window.location = 'reporte_pipeline.php?week=' + encodeURIComponent(wk) + getSectionParams();
        }
    });

    // ── PER-SECTION FILTERS ──
    $('.section-filter-apply').on('click', function() {
        var section = $(this).data('section');
        var startVal = $('input.section-date[data-section="' + section + '"][data-field="start"]').val();
        var endVal = $('input.section-date[data-section="' + section + '"][data-field="end"]').val();
        var yearVal = $('select.section-year[data-section="' + section + '"]').val();
        var monthVal = $('select.section-month[data-section="' + section + '"]').val();
        var weekVal = $('select.section-week[data-section="' + section + '"]').val();

        var params = new URLSearchParams(window.location.search);

        if (startVal) params.set(section + '_start', startVal); else params.delete(section + '_start');
        if (endVal) params.set(section + '_end', endVal); else params.delete(section + '_end');

        if (yearVal) params.set(section + '_year', yearVal); else params.delete(section + '_year');
        if (monthVal) params.set(section + '_month', monthVal); else params.delete(section + '_month');
        if (weekVal) params.set(section + '_week', weekVal); else params.delete(section + '_week');

        params.delete('show_all');
        window.location = 'reporte_pipeline.php?' + params.toString();
    });

    $('.section-filter-clear').on('click', function() {
        var section = $(this).data('section');
        var params = new URLSearchParams(window.location.search);

        params.delete(section + '_start');
        params.delete(section + '_end');
        params.delete(section + '_year');
        params.delete(section + '_month');
        params.delete(section + '_week');

        window.location = 'reporte_pipeline.php?' + params.toString();
    });
});
</script>

</body>
</html>
