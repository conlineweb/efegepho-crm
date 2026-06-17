<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';

function formatCreatedTime_rpk($dateString)
{
    if (empty($dateString))
        return '';
    $timestamp = strtotime($dateString);
    if ($timestamp === false || $timestamp <= 0)
        return '';
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $month = $months[date('n', $timestamp) - 1];
    $day = date('j', $timestamp);
    $year = date('Y', $timestamp);
    $hour = date('g', $timestamp);
    $min = date('i', $timestamp);
    $ampm = date('A', $timestamp) == 'AM' ? 'a.m.' : 'p.m.';
    return "$day de $month de $year a las $hour:$min $ampm";
}

if (!function_exists('isB1B2CampaignName_rpk')) {
    function isB1B2CampaignName_rpk($campaignName)
    {
        if ($campaignName === null)
            return false;
        $v = strtolower(trim((string) $campaignName));
        if ($v === '')
            return false;
        $v = preg_replace('/\s+/', ' ', $v);
        return in_array($v, ['b1', 'b1 (usa)', 'b1 usa', 'b2', 'b2 (mx)', 'b2 mx'], true);
    }
}

if (!function_exists('extractDateFromTimestamp_rpk')) {
    function extractDateFromTimestamp_rpk($timestamp) {
        if (empty($timestamp)) return null;
        if (strpos($timestamp, 'T') !== false) {
            $normalized = substr($timestamp, 0, 19);
            $ts = strtotime($normalized);
            if ($ts === false) return null;
            return date('Y-m-d', $ts);
        }
        $ts = strtotime($timestamp);
        if ($ts === false) return null;
        return date('Y-m-d', $ts);
    }
}

if (!function_exists('normalizeFirstContactChannelLabel_rpk')) {
    function normalizeFirstContactChannelLabel_rpk($value) {
        $normalized = trim((string) $value);
        if ($normalized === '') return 'Sin dato';
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
            'phone call'                   => 'Phone call',
            'llamada telefonica'           => 'Phone call',
            'llamada telefónica'           => 'Phone call',
        ];
        return $map[$key] ?? $normalized;
    }
}

// ============================================================================
// DATA LOADING: Appointments + Contact Form (same as consulta_post_leads.php)
// ============================================================================

$appointmentIds = [];
$appointmentQuery = $conn->query("SELECT DISTINCT idclie FROM calendario");
if ($appointmentQuery && $appointmentQuery->num_rows > 0) {
    while ($row = $appointmentQuery->fetch_assoc()) {
        $appointmentIds[] = intval($row['idclie']);
    }
}

$appointmentsByClient = [];
if (!empty($appointmentIds)) {
    $idsList = implode(',', array_map('intval', $appointmentIds));
    $apptRes = $conn->query("SELECT * FROM calendario WHERE idclie IN ($idsList)");
    if ($apptRes && $apptRes->num_rows > 0) {
        while ($ar = $apptRes->fetch_assoc()) {
            $idclie = isset($ar['idclie']) ? intval($ar['idclie']) : 0;
            if ($idclie <= 0) continue;
            if (!isset($appointmentsByClient[$idclie])) {
                $appointmentsByClient[$idclie] = $ar;
            } else {
                $prev = $appointmentsByClient[$idclie];
                $replace = false;
                if (!empty($ar['fecha']) && !empty($prev['fecha'])) {
                    $t1 = strtotime($ar['fecha'] . ' ' . ($ar['hora'] ?? '')) ?: 0;
                    $t2 = strtotime($prev['fecha'] . ' ' . ($prev['hora'] ?? '')) ?: 0;
                    if ($t1 > $t2) $replace = true;
                } elseif (!empty($ar['id']) && !empty($prev['id'])) {
                    if (intval($ar['id']) > intval($prev['id'])) $replace = true;
                }
                if ($replace) $appointmentsByClient[$idclie] = $ar;
            }
        }
    }
}

$allLeads = [];
if (!empty($appointmentIds)) {
    $idsList = implode(',', array_map('intval', $appointmentIds));
    $sql = "SELECT * FROM contact_form WHERE id IN ($idsList) ORDER BY submission_date DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($cf = $result->fetch_assoc()) {
            $tablaOrigenRaw = strtolower(trim($cf['tabla_origen'] ?? ''));
            if ($tablaOrigenRaw === 'wedding_planners' || $tablaOrigenRaw === 'wedding_planner') {
                continue;
            }

            $merged = $cf;
            $merged['tabla_origen'] = $cf['tabla_origen'] ?? '';
            $merged['original_lead_id'] = $cf['original_lead_id'] ?? null;
            $merged['full_name'] = $cf['names'] ?? 'N/A';
            $merged['submission_date'] = $cf['submission_date'] ?? '';
            $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';
            $merged['wedding_location'] = (isset($cf['wedding_location']) && trim($cf['wedding_location']) !== '') ? $cf['wedding_location'] : 'N/A';
            $merged['has_appointment'] = in_array(intval($cf['id']), $appointmentIds) ? 1 : 0;

            $formName = $cf['tabla_origen'] ?? '';
            $origId = intval($cf['original_lead_id'] ?? 0);
            if (!empty($formName) && $origId > 0) {
                $escapedForm = $conn->real_escape_string($formName);
                $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
                    if ($leadRes && $leadRes->num_rows > 0) {
                        $leadRow = $leadRes->fetch_assoc();
                        if (!empty($leadRow['full_name']))
                            $merged['full_name'] = $leadRow['full_name'];
                        elseif (!empty($leadRow['names']))
                            $merged['full_name'] = $leadRow['names'];
                        elseif (!empty($leadRow['name']))
                            $merged['full_name'] = $leadRow['name'];

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

            $merged['campaign_name'] = (isset($cf['campaign_name']) && trim($cf['campaign_name']) !== '') ? $cf['campaign_name'] : '';

            $cid = intval($cf['id']);
            if ($cid > 0 && isset($appointmentsByClient[$cid])) {
                $appt = $appointmentsByClient[$cid];
                $apptFechaRaw = trim($appt['fecha'] ?? '');
                $apptHoraRaw = trim($appt['hora'] ?? '');
                $apptTs = ($apptFechaRaw !== '') ? strtotime($apptFechaRaw . ' ' . $apptHoraRaw) : false;
                if ($apptFechaRaw === '' || $apptFechaRaw === '0000-00-00' || $apptTs === false || $apptTs <= 0) {
                    continue;
                }
            }

            $cid = intval($cf['id']);
            $merged['estatus'] = '';
            if ($cf['cliente'] == 1) {
                $merged['estatus'] = 'cliente';
            } elseif (isset($appointmentsByClient[$cid]) && isset($appointmentsByClient[$cid]['estatus'])) {
                $rawStatus = $appointmentsByClient[$cid]['estatus'];
                $intStatus = is_numeric($rawStatus) ? intval($rawStatus) : null;
                if ($intStatus === 1) {
                    $merged['estatus'] = 'atendido';
                } elseif ($intStatus === 3) {
                    $merged['estatus'] = 'muerto';
                } elseif ($intStatus === 0) {
                    $merged['estatus'] = 'agendado';
                } elseif ($intStatus === 2) {
                    $merged['estatus'] = 'fantasma';
                } else {
                    $merged['estatus'] = $rawStatus;
                }
            }

            $allLeads[] = $merged;
        }
    }
}

// ============================================================================
// FILTERS
// ============================================================================

$filterPlataforma = isset($_GET['filter_plataforma']) ? trim($_GET['filter_plataforma']) : '';
$platformLabel = 'todo';
if ($filterPlataforma === 'campania') {
    $platformLabel = 'Campañas digitales';
} elseif ($filterPlataforma === 'organico') {
    $platformLabel = 'organico';
}

$tablaTipoMap = [];
$sqlTablasMap = "SELECT nombre, tipo FROM tablas_leads";
$resultTablasMap = $conn->query($sqlTablasMap);
if ($resultTablasMap && $resultTablasMap->num_rows > 0) {
    while ($row = $resultTablasMap->fetch_assoc()) {
        $tablaTipoMap[$row['nombre']] = intval($row['tipo']);
    }
}

$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

if (isset($_GET['show_all'])) {
    unset($_SESSION['leads_filter_start'], $_SESSION['leads_filter_end']);
} elseif ($startDate !== '' || $endDate !== '') {
    $_SESSION['leads_filter_start'] = $startDate;
    $_SESSION['leads_filter_end']   = $endDate;
} elseif (!empty($_SESSION['leads_filter_start']) || !empty($_SESSION['leads_filter_end'])) {
    $startDate = $_SESSION['leads_filter_start'] ?? '';
    $endDate   = $_SESSION['leads_filter_end']   ?? '';
}

if ($startDate === '' && $endDate === '' && empty($_GET['show_all'])) {
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-14 days', strtotime($endDate)));
}

// Filter displayLeads
$displayLeads = [];
if ($startDate === '' && $endDate === '' && $filterPlataforma === '') {
    $displayLeads = $allLeads;
} else {
    $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
    $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
    $hasFechaFilter = ($sd !== null || $ed !== null);

    foreach ($allLeads as $lead) {
        if ($hasFechaFilter) {
            $dateField = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
            if (empty($dateField)) continue;
            $d = extractDateFromTimestamp_rpk($dateField);
            if ($d === null) continue;
            if ($sd && $d < $sd) continue;
            if ($ed && $d > $ed) continue;
        }

        if ($filterPlataforma !== '') {
            $tablaOrigen = $lead['tabla_origen'] ?? '';
            $tipoTabla = isset($tablaTipoMap[$tablaOrigen]) ? $tablaTipoMap[$tablaOrigen] : -1;
            $campaignNameLower = strtolower(trim($lead['campaign_name'] ?? ''));
            $isB1B2 = isB1B2CampaignName_rpk($campaignNameLower);

            if ($filterPlataforma === 'organico') {
                if ($isB1B2) continue;
                if ($tipoTabla !== 0 && $campaignNameLower !== 'website' && $campaignNameLower !== 'reg manual') continue;
            }
            if ($filterPlataforma === 'campania') {
                if ($tipoTabla !== 1 && !$isB1B2) continue;
            }
        }

        $displayLeads[] = $lead;
    }
}

$leadsCountFiltered = count($displayLeads);

// ============================================================================
// KPI CALCULATIONS
// ============================================================================

$todayPostLeadsCount = 0;
$today = date('Y-m-d');
foreach ($displayLeads as $lead) {
    $dateField = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
    if (empty($dateField)) continue;
    $d = extractDateFromTimestamp_rpk($dateField);
    if ($d === null) continue;
    if ($d === $today) $todayPostLeadsCount++;
}

// Build daily chart series
$dayMap = [];
$globalMin = null;
$globalMax = null;
foreach ($displayLeads as $lead) {
    $dateField = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
    if (empty($dateField)) continue;
    $d = extractDateFromTimestamp_rpk($dateField);
    if ($d === null) continue;
    $ts = strtotime($dateField);
    if ($ts === false) continue;
    if (!isset($dayMap[$d])) {
        $dayMap[$d] = ['count' => 0, 'min' => $ts, 'max' => $ts];
    }
    $dayMap[$d]['count']++;
    if ($ts < $dayMap[$d]['min']) $dayMap[$d]['min'] = $ts;
    if ($ts > $dayMap[$d]['max']) $dayMap[$d]['max'] = $ts;
    if ($globalMin === null || $ts < $globalMin) $globalMin = $ts;
    if ($globalMax === null || $ts > $globalMax) $globalMax = $ts;
}

if ($globalMin === null) {
    $datesJson = json_encode([]);
    $countsJson = json_encode([]);
} else {
    $start = strtotime(date('Y-m-d', $globalMin));
    $end = strtotime(date('Y-m-d', $globalMax));
    if ($startDate !== '') {
        $sd_ts = strtotime(date('Y-m-d', strtotime($startDate)));
        if ($sd_ts !== false) $start = $sd_ts;
    }
    if ($endDate !== '') {
        $ed_ts = strtotime(date('Y-m-d', strtotime($endDate)));
        if ($ed_ts !== false) $end = $ed_ts;
    }
    if ($end < $start) $end = $start;

    $dates = [];
    $counts = [];
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $d = date('Y-m-d', $ts);
        $c = isset($dayMap[$d]) ? $dayMap[$d]['count'] : 0;
        $dates[] = $d;
        $counts[] = $c;
    }
    $datesJson = json_encode($dates);
    $countsJson = json_encode($counts);
}

// Conversion rate: clientes cerrados / agendados
$totalRegistrosFiltered = count($displayLeads);

$totalAtendidosFiltered = 0;
$sd_c = ($startDate !== '') ? date('Y-m-d', strtotime($startDate)) : null;
$ed_c = ($endDate !== '') ? date('Y-m-d', strtotime($endDate)) : null;
$_sqlCierres = "SELECT fecha_cambio_cliente, tabla_origen FROM contact_form WHERE cliente = 1 AND LOWER(COALESCE(tabla_origen, '')) NOT IN ('wedding_planners','wedding_planner')";
$_resCierres = $conn->query($_sqlCierres);
if ($_resCierres && $_resCierres->num_rows > 0) {
    while ($_rowC = $_resCierres->fetch_assoc()) {
        if ($sd_c !== null || $ed_c !== null) {
            $fc = trim($_rowC['fecha_cambio_cliente'] ?? '');
            if (empty($fc)) continue;
            $fcts = strtotime($fc);
            if ($fcts === false) continue;
            $fd = date('Y-m-d', $fcts);
            if ($sd_c && $fd < $sd_c) continue;
            if ($ed_c && $fd > $ed_c) continue;
        }
        $totalAtendidosFiltered++;
    }
}

$totalAgendadasFiltered = 0;
foreach ($displayLeads as $lead) {
    $st = isset($lead['estatus']) ? mb_strtolower(trim((string)$lead['estatus']), 'UTF-8') : '';
    if (
        $st === 'agendado' ||
        $st === 'atendido' ||
        $st === 'fantasma' ||
        $st === 'muerto' ||
        $st === 'cliente' ||
        (is_numeric($st) && in_array(intval($st), [0,1,2,3], true)) ||
        (isset($lead['cliente']) && intval($lead['cliente']) === 1)
    ) {
        $totalAgendadasFiltered++;
    }
}

$asistenciaAtendidos = $totalAtendidosFiltered;
$asistenciaAgendadas = $totalAgendadasFiltered;
$asistenciaRate = ($asistenciaAgendadas > 0) ? round(((float)$asistenciaAtendidos / (float)$asistenciaAgendadas) * 100, 2) : 0.0;

// ============================================================================
// CHART DATA: Contact Method, Known Us, Where Know
// ============================================================================

$postContactMethodCounts = [];
foreach ($displayLeads as $lead) {
    $raw = trim((string)($lead['first_contact_channel'] ?? ''));
    if ($raw === '' && ($lead['estatus'] ?? '') === 'muerto') {
        $label = 'Muerto';
    } else {
        $label = normalizeFirstContactChannelLabel_rpk($raw);
    }
    $postContactMethodCounts[$label] = ($postContactMethodCounts[$label] ?? 0) + 1;
}
arsort($postContactMethodCounts);
$postContactMethodPieData = [];
foreach ($postContactMethodCounts as $label => $count) {
    if ($count <= 0) continue;
    $entry = ['name' => $label, 'y' => $count];
    if ($label === 'Muerto') $entry['color'] = '#ef4444';
    $postContactMethodPieData[] = $entry;
}
$postContactMethodPieJson = json_encode($postContactMethodPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$postKnownUsCounts = [];
$postKnownUsLabelMap = [
    'less than 3 months'          => 'Menos de 3 meses',
    'between 3 months and 1 year' => 'Entre 3 meses y 1 año',
    'more than 1 year'            => 'Más de 1 año',
    'not asked'                   => 'No se preguntó',
];
foreach ($displayLeads as $lead) {
    $raw = trim((string)($lead['how_long_known_us'] ?? ''));
    if (($raw === '' || $raw === '—') && ($lead['estatus'] ?? '') === 'muerto') {
        $label = 'Muerto';
    } elseif ($raw === '' || $raw === '—') {
        $label = 'Sin dato';
    } else {
        $label = $postKnownUsLabelMap[mb_strtolower($raw, 'UTF-8')] ?? $raw;
    }
    $postKnownUsCounts[$label] = ($postKnownUsCounts[$label] ?? 0) + 1;
}
arsort($postKnownUsCounts);
$postKnownUsPieData = [];
foreach ($postKnownUsCounts as $label => $count) {
    if ($count <= 0) continue;
    $entry = ['name' => $label, 'y' => $count];
    if ($label === 'Muerto') $entry['color'] = '#ef4444';
    $postKnownUsPieData[] = $entry;
}
$postKnownUsPieJson = json_encode($postKnownUsPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$postWhereKnowCounts = ['Wedding Planner' => 0, 'Community' => 0, 'New Market' => 0, 'Muerto' => 0, 'Sin dato' => 0];
foreach ($displayLeads as $lead) {
    $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
    if ($howRaw === '1') $postWhereKnowCounts['Wedding Planner']++;
    elseif ($howRaw === '2') $postWhereKnowCounts['Community']++;
    elseif ($howRaw === '3') $postWhereKnowCounts['New Market']++;
    elseif ($howRaw === '' && ($lead['estatus'] ?? '') === 'muerto') $postWhereKnowCounts['Muerto']++;
    else $postWhereKnowCounts['Sin dato']++;
}
$postWhereKnowColorMap = [
    'Wedding Planner' => '#3B82F6',
    'Community'       => '#10B981',
    'New Market'      => '#A855F7',
    'Muerto'          => '#ef4444',
    'Sin dato'        => '#94a3b8',
];
$postWhereKnowPieData = [];
foreach ($postWhereKnowCounts as $wlabel => $wcount) {
    if ($wcount <= 0) continue;
    $postWhereKnowPieData[] = ['name' => $wlabel, 'y' => $wcount, 'color' => ($postWhereKnowColorMap[$wlabel] ?? null)];
}
$postWhereKnowPieJson = json_encode($postWhereKnowPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$reportRangeLabel = 'Todos los registros';
if (($startDate !== '' || $endDate !== '') && empty($_GET['show_all'])) {
    $reportRangeStart = $startDate !== '' ? date('d/m/Y', strtotime($startDate)) : '...';
    $reportRangeEnd   = $endDate   !== '' ? date('d/m/Y', strtotime($endDate))   : '...';
    $reportRangeLabel = $reportRangeStart . ' → ' . $reportRangeEnd;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Post KPIs</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <style>
:root {
  --gold: #C5A028;
  --gold-dim: rgba(197,160,40,0.12);
  --ink: #1E293B;
  --muted: #64748B;
  --surface: #F8FAFC;
  --border: #E2E8F0;
  --dark: #F8FAFC;
}

body { background: var(--dark, #F8FAFC) !important; }

.efege-page {
    width: 100%;
    margin: 0 auto;
    padding: 18px 24px 40px;
}

.efege-page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 8px;
}
.efege-page-header-left { flex: 1; min-width: 0; }
.efege-page-header-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.efege-page-title {
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 22px;
    font-weight: 700;
    color: var(--ink);
    letter-spacing: -0.3px;
    line-height: 1.25;
}
.efege-page-title-sub { color: var(--ink); }
.efege-page-subtitle { font-size: 12px; color: var(--muted); margin-top: 4px; }
.efege-date-range {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--gold-dim);
    color: var(--gold);
    font-size: 12px;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 20px;
}
.efege-date-range span { color: var(--ink); font-weight: 600; }
.efege-live-badge {
    font-size: 11px;
    font-weight: 700;
    color: #dc2626;
    background: rgba(220,38,38,0.08);
    padding: 4px 10px;
    border-radius: 20px;
}

.filter-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    padding: 12px 0;
    border-radius: 12px;
}
.filter-label {
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--muted);
}
.efege-filter-input {
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: var(--ink);
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 7px 12px;
    outline: none;
    transition: border-color 0.2s;
}
.efege-filter-input:focus { border-color: var(--gold); }
.efege-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 13px;
    font-weight: 600;
    padding: 7px 16px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--ink);
    cursor: pointer;
    text-decoration: none;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
    white-space: nowrap;
}
.efege-btn:hover { border-color: var(--gold); color: var(--gold); text-decoration: none; }
.efege-btn-primary { background: var(--gold); border-color: var(--gold); color: #fff; }
.efege-btn-primary:hover { background: #b8921f; border-color: #b8921f; color: #fff; }

/* ── REPORTS SECTION ── */
.reports-section {
    margin-top: 18px;
    background: #fff;
    border-radius: 14px;
    padding: 28px 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    border: 1px solid var(--border);
}
.reports-section-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 22px;
}
.reports-section-step {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gold);
    margin-bottom: 4px;
}
.reports-section-title {
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 18px;
    font-weight: 700;
    color: var(--ink);
}
.reports-section-subtitle {
    font-size: 13px;
    color: var(--muted);
    margin-top: 2px;
}
.reports-range-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--gold-dim);
    color: var(--gold);
    font-size: 12px;
    font-weight: 600;
    padding: 5px 14px;
    border-radius: 20px;
    white-space: nowrap;
}
.reports-layout {
    display: flex;
    flex-direction: column;
    gap: 20px;
    width: 100%;
}
.reports-kpi-stack {
    display: flex;
    flex-direction: row;
    gap: 14px;
    width: 100%;
}
.reports-kpi-stack > * {
    flex: 1 1 0;
    min-width: 0;
}
.reports-kpi-grid-secondary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.report-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 18px 16px;
    border: 1px solid var(--border);
}
.report-card-highlight {
    background: linear-gradient(135deg, #fdf6e3 0%, #fff 100%);
    border-color: var(--gold);
}
.report-card-label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: var(--muted);
    margin-bottom: 6px;
}
.report-card-value {
    font-size: 28px;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -0.5px;
    line-height: 1.1;
}
.report-card-note {
    font-size: 12px;
    color: var(--muted);
    margin-top: 4px;
}
.report-formula {
    font-size: 11px;
    color: var(--muted);
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid var(--border);
}
.report-formula strong { color: var(--ink); }
.report-chart-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 18px 16px;
    border: 1px solid var(--border);
}
.report-chart-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}
.report-chart-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--ink);
    margin: 0;
}
.report-chart-subtitle {
    font-size: 12px;
    color: var(--muted);
    margin-top: 2px;
}
.report-chart {
    min-height: 260px;
}
.reports-charts-row {
    display: flex;
    gap: 14px;
    width: 100%;
}
.reports-charts-row .report-chart-card {
    flex: 1 1 0;
    min-width: 0;
}

@media (max-width: 900px) {
    .reports-kpi-stack { flex-direction: column; }
    .reports-kpi-grid-secondary { grid-template-columns: 1fr; }
    .reports-charts-row { flex-direction: column; }
}
    </style>
</head>
<body>
<div class="efege-page">

    <!-- PAGE HEADER -->
    <div class="efege-page-header">
        <div class="efege-page-header-left">
            <div class="efege-page-title"><span class="efege-page-title-sub">Reporte Post KPIs</span></div>
            <div class="efege-page-subtitle">Reporte de sesiones agendadas · Plataforma: <strong><?php echo htmlspecialchars($platformLabel); ?></strong></div>
        </div>
        <div class="efege-page-header-right">
            <?php if ($startDate && $endDate): ?>
            <div class="efege-date-range">📅 <span><?php echo date('d M Y', strtotime($startDate)); ?></span>&nbsp;→&nbsp;<span><?php echo date('d M Y', strtotime($endDate)); ?></span></div>
            <?php endif; ?>
            <div class="efege-live-badge">🔴 Live</div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <br>
    <form method="get" class="filter-bar" id="filterForm">
        <span class="filter-label">Filtrar</span>
        <input type="date" name="start_date" class="efege-filter-input"
            value="<?php echo htmlspecialchars($startDate ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Desde">
        <input type="date" name="end_date" class="efege-filter-input"
            value="<?php echo htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Hasta">
        <button type="submit" class="efege-btn efege-btn-primary" style="background:#0f172a; border-color:#0f172a; color:#fff;">
            <i class="fas fa-filter"></i> Filtrar
        </button>
        <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>?show_all=1" class="efege-btn">Limpiar</a>
    </form>

    <!-- REPORTS SECTION -->
    <section class="reports-section">
        <div class="reports-section-header">
            <div>
                <div class="reports-section-step">Apartado de Reportes</div>
                <h2 class="reports-section-title">Reporte de sesiones agendadas</h2>
                <p class="reports-section-subtitle">Solo se consideran leads que tienen cita agendada. Usa el filtro por rango de fechas de arriba para actualizar este reporte.</p>
            </div>
            <div class="reports-range-chip">
                <i class="bi bi-calendar3"></i>
                <span><?php echo htmlspecialchars($reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <div class="reports-layout">
            <div class="reports-kpi-stack">
                <article class="report-card report-card-highlight">
                    <div class="report-card-label">Tasa de Conversión</div>
                    <div class="report-card-value"><?php echo ($totalAgendadasFiltered > 0) ? number_format($asistenciaRate, 1) . '%' : 'N/A'; ?></div>
                    <div class="report-card-note"><?php echo number_format($totalAtendidosFiltered); ?> cierres de <?php echo number_format($totalAgendadasFiltered); ?> agendados</div>
                    <div class="report-formula"><strong>Fórmula:</strong> (Clientes cerrados / Total agendados) × 100</div>
                </article>

                <div class="reports-kpi-grid-secondary">
                    <article class="report-card">
                        <div class="report-card-label">Total agendados</div>
                        <div class="report-card-value"><?php echo number_format($totalAgendadasFiltered); ?></div>
                        <div class="report-card-note">Registros con cita en el periodo.</div>
                    </article>

                    <article class="report-card">
                        <div class="report-card-label">Clientes cerrados</div>
                        <div class="report-card-value"><?php echo number_format($totalAtendidosFiltered); ?></div>
                        <div class="report-card-note">Cierres con fecha_cambio_cliente en el periodo.</div>
                    </article>
                </div>
            </div>

            <div class="reports-charts-row" style="flex-direction:column;width:100%;">
                <article class="report-chart-card" style="width:100%;">
                    <div class="report-chart-header">
                        <div>
                            <h3 class="report-chart-title">Sesiones agendadas por periodo</h3>
                            <p class="report-chart-subtitle">Total de sesiones agendadas por día dentro del rango seleccionado.</p>
                        </div>
                    </div>
                    <div id="postLeadsChart" class="report-chart"></div>
                </article>
            </div>
        </div>

        <div style="display:flex;gap:12px;width:100%;margin-top:16px;">
            <article class="report-chart-card" style="flex:1 1 0;min-width:0;">
                <div class="report-chart-header">
                    <div>
                        <h3 class="report-chart-title">Método de contacto</h3>
                        <p class="report-chart-subtitle">Distribución de sesiones según canal de contacto del agendado.</p>
                    </div>
                </div>
                <div id="postContactMethodChart" class="report-chart"></div>
            </article>

            <article class="report-chart-card" style="flex:1 1 0;min-width:0;">
                <div class="report-chart-header">
                    <div>
                        <h3 class="report-chart-title">Desde cuándo nos conoce</h3>
                        <p class="report-chart-subtitle">Distribución de sesiones según el tiempo que llevan conociendo a Efege.</p>
                    </div>
                </div>
                <div id="postKnownUsChart" class="report-chart"></div>
            </article>

            <article class="report-chart-card" style="flex:1 1 0;min-width:0;">
                <div class="report-chart-header">
                    <div>
                        <h3 class="report-chart-title">De dónde nos conocen</h3>
                        <p class="report-chart-subtitle">Distribución según cómo llegaron a Efege (Wedding Planner, Community, New Market).</p>
                    </div>
                </div>
                <div id="postWhereKnowChart" class="report-chart"></div>
            </article>
        </div>
    </section>

</div><!-- /efege-page -->

<script src="assets/static/js/components/dark.js"></script>
<script src="assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/compiled/js/app.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var postChartDates  = <?php echo isset($datesJson)  ? $datesJson  : '[]'; ?>;
    var postChartCounts = <?php echo isset($countsJson) ? $countsJson : '[]'; ?>;
    var postContactMethodSeries = <?php echo isset($postContactMethodPieJson) ? $postContactMethodPieJson : '[]'; ?>;
    var postKnownUsSeries = <?php echo isset($postKnownUsPieJson) ? $postKnownUsPieJson : '[]'; ?>;
    var postWhereKnowSeries = <?php echo isset($postWhereKnowPieJson) ? $postWhereKnowPieJson : '[]'; ?>;

    var postMaxLeads = 0;
    postChartCounts.forEach(function(c) { if (c > postMaxLeads) postMaxLeads = c; });
    var postYAxisMax = Math.ceil(postMaxLeads * 1.15);
    if (postYAxisMax < 5) postYAxisMax = 5;
    var postTickInterval = Math.ceil(postYAxisMax / 5);
    if (postTickInterval < 1) postTickInterval = 1;

    Highcharts.chart('postLeadsChart', {
        chart: {
            type: 'line',
            backgroundColor: '#f8f9fa',
            borderRadius: 12,
            spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10
        },
        title: { text: null },
        xAxis: {
            categories: postChartDates,
            crosshair: true,
            labels: { rotation: -45, style: { fontSize: '11px' } },
            lineColor: '#d9e2ec'
        },
        yAxis: {
            min: 0,
            max: postYAxisMax,
            tickInterval: postTickInterval,
            title: { text: 'Sesiones' },
            allowDecimals: false,
            gridLineColor: '#e2e8f0'
        },
        legend: { enabled: false },
        plotOptions: {
            line: {
                color: '#C5A028',
                lineWidth: 2.5,
                marker: {
                    enabled: true,
                    radius: 4,
                    fillColor: '#C5A028',
                    lineWidth: 2,
                    lineColor: '#fff'
                },
                dataLabels: {
                    enabled: true,
                    formatter: function() { return this.y > 0 ? this.y : ''; },
                    style: { fontSize: '10px', fontWeight: '600', textOutline: 'none' }
                }
            }
        },
        series: [{ name: 'Sesiones', data: postChartCounts }],
        tooltip: {
            backgroundColor: 'rgba(15,23,42,0.92)',
            style: { color: '#fff' },
            borderWidth: 0,
            formatter: function() {
                return '<b>' + this.x + '</b><br/>Sesiones agendadas: <b>' + this.y + '</b>';
            }
        },
        credits: { enabled: false }
    });

    var postPieOptions = {
        chart: {
            type: 'pie',
            backgroundColor: '#f8f9fa',
            borderRadius: 12,
            spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10
        },
        title: { text: null },
        colors: ['#16a34a','#2563eb','#f59e0b','#c026d3','#0284c7','#94a3b8'],
        tooltip: { pointFormat: '<b>{point.y}</b> sesiones ({point.percentage:.1f}%)' },
        accessibility: { point: { valueSuffix: '%' } },
        plotOptions: {
            pie: {
                innerSize: '48%',
                allowPointSelect: true,
                cursor: 'pointer',
                dataLabels: {
                    enabled: true,
                    format: '<b>{point.name}</b><br>{point.y} sesiones'
                },
                showInLegend: true
            }
        },
        legend: {
            align: 'center',
            verticalAlign: 'bottom',
            itemStyle: { fontSize: '11px' }
        },
        credits: { enabled: false }
    };

    Highcharts.chart('postContactMethodChart', Object.assign({}, postPieOptions, {
        series: [{ name: 'Método de contacto', colorByPoint: true, data: postContactMethodSeries }]
    }));

    Highcharts.chart('postKnownUsChart', Object.assign({}, postPieOptions, {
        series: [{ name: 'Desde cuándo nos conoce', colorByPoint: true, data: postKnownUsSeries }]
    }));

    Highcharts.chart('postWhereKnowChart', Object.assign({}, postPieOptions, {
        series: [{ name: '¿De dónde nos conocen?', colorByPoint: true, data: postWhereKnowSeries }]
    }));
});
</script>
</body>
</html>
