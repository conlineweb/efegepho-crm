<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';

// ============================================================================
// HELPER FUNCTIONS (suffixed _rck to avoid redeclaration)
// ============================================================================

function formatCreatedTime_rck($dateString)
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

if (!function_exists('isB1B2CampaignName_rck')) {
    function isB1B2CampaignName_rck($campaignName)
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

if (!function_exists('normalizeFirstContactChannelLabel_rck')) {
    function normalizeFirstContactChannelLabel_rck($value) {
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
// DATA LOADING: Appointments + Contact Form (Cierres = cliente = 1)
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

// Consultar registros de contact_form (todos los que son Cierres) — excluir Wedding Planners
$allLeads = [];
$sql = "SELECT * FROM contact_form WHERE cliente = 1 AND LOWER(COALESCE(tabla_origen, '')) != 'wedding_planners' ORDER BY submission_date DESC";
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
        $merged['fecha_cambio_cliente'] = $cf['fecha_cambio_cliente'] ?? '';
        $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';
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

                    if (!empty($leadRow['fecha_cambio_cliente'])) {
                        $merged['fecha_cambio_cliente'] = $leadRow['fecha_cambio_cliente'];
                    } elseif (!empty($leadRow['created_time'])) {
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

        $merged['campaign_name'] = (isset($cf['campaign_name']) && trim($cf['campaign_name']) !== '') ? $cf['campaign_name'] : 'N/A';

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

// ============================================================================
// FILTERS
// ============================================================================

$filterPlataforma = isset($_GET['filter_plataforma']) ? trim($_GET['filter_plataforma']) : '';
$platformLabel = 'todo';
if ($filterPlataforma === 'campania') {
    $platformLabel = 'redes sociales';
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

// Build displayLeads filtered by fecha_cambio_cliente + platform
$displayLeads = [];
if ($startDate === '' && $endDate === '' && $filterPlataforma === '') {
    $displayLeads = $allLeads;
} else {
    $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
    $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;

    foreach ($allLeads as $lead) {
        $ct = $lead['fecha_cambio_cliente'] ?? '';
        if (empty($ct)) continue;
        $ts = strtotime($ct);
        if ($ts === false) continue;
        $d = date('Y-m-d', $ts);

        if ($sd && $d < $sd) continue;
        if ($ed && $d > $ed) continue;

        if ($filterPlataforma !== '') {
            $tablaOrigen = $lead['tabla_origen'] ?? '';
            $tipoTabla = isset($tablaTipoMap[$tablaOrigen]) ? $tablaTipoMap[$tablaOrigen] : -1;
            $campaignNameLower = strtolower(trim($lead['campaign_name'] ?? ''));
            $isB1B2 = isB1B2CampaignName_rck($campaignNameLower);

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

// Sort by fecha_cambio_cliente DESC
usort($displayLeads, function ($a, $b) {
    $ta = (!empty($a['fecha_cambio_cliente']) && strtotime($a['fecha_cambio_cliente']) !== false) ? strtotime($a['fecha_cambio_cliente']) : 0;
    $tb = (!empty($b['fecha_cambio_cliente']) && strtotime($b['fecha_cambio_cliente']) !== false) ? strtotime($b['fecha_cambio_cliente']) : 0;
    if ($tb === $ta) return 0;
    return ($tb < $ta) ? -1 : 1;
});

$leadsCountFiltered = count($displayLeads);

// ============================================================================
// KPI: CONVERSION RATE (cierres / agendados post-Q)
// ============================================================================

// Get all post-qualified leads (contact_form with appointments)
$allPostLeadsForRatio = [];
if (!empty($appointmentIds)) {
    $idsList = implode(',', array_map('intval', $appointmentIds));
    $sql = "SELECT * FROM contact_form WHERE id IN ($idsList) AND LOWER(COALESCE(tabla_origen, '')) != 'wedding_planners'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($cf = $result->fetch_assoc()) {
            $tablaOrigenCF = strtolower(trim($cf['tabla_origen'] ?? ''));
            if ($tablaOrigenCF === 'wedding_planners' || $tablaOrigenCF === 'wedding_planner') continue;

            $merged = $cf;
            $merged['submission_date'] = $cf['submission_date'] ?? '';
            $merged['fecha_cambio_cliente'] = $cf['fecha_cambio_cliente'] ?? '';
            $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';

            // Lookup original lead dates
            $formNamePoQ = $cf['tabla_origen'] ?? '';
            $origIdPoQ   = intval($cf['original_lead_id'] ?? 0);
            if (!empty($formNamePoQ) && $origIdPoQ > 0) {
                $escapedFormPoQ = $conn->real_escape_string($formNamePoQ);
                $chkTblPoQ = $conn->query("SHOW TABLES LIKE '$escapedFormPoQ'");
                if ($chkTblPoQ && $chkTblPoQ->num_rows > 0) {
                    $colsPoQ = [];
                    $colResPoQ = $conn->query("SHOW COLUMNS FROM `$escapedFormPoQ`");
                    if ($colResPoQ) {
                        while ($colRowPoQ = $colResPoQ->fetch_assoc()) {
                            $colsPoQ[] = $colRowPoQ['Field'];
                        }
                    }
                    $selectColsPoQ = [];
                    if (in_array('created_time', $colsPoQ)) $selectColsPoQ[] = 'created_time';
                    if (in_array('created_at',   $colsPoQ)) $selectColsPoQ[] = 'created_at';
                    if (!empty($selectColsPoQ)) {
                        $selStrPoQ = implode(', ', array_map(fn($c) => "`$c`", $selectColsPoQ));
                        $leadResPoQ = $conn->query("SELECT $selStrPoQ FROM `$escapedFormPoQ` WHERE id = $origIdPoQ LIMIT 1");
                        if ($leadResPoQ && $leadResPoQ->num_rows > 0) {
                            $leadRowPoQ = $leadResPoQ->fetch_assoc();
                            if (!empty($leadRowPoQ['created_time'])) {
                                $merged['created_time'] = $leadRowPoQ['created_time'];
                            } elseif (!empty($leadRowPoQ['created_at'])) {
                                $merged['created_time'] = $leadRowPoQ['created_at'];
                            }
                        }
                    }
                }
            }

            $cid = intval($cf['id']);

            // Skip invalid appointment dates
            if ($cid > 0 && isset($appointmentsByClient[$cid])) {
                $appt = $appointmentsByClient[$cid];
                $apptFechaRaw = trim($appt['fecha'] ?? '');
                $apptHoraRaw = trim($appt['hora'] ?? '');
                $apptTs = ($apptFechaRaw !== '') ? strtotime($apptFechaRaw . ' ' . $apptHoraRaw) : false;
                if ($apptFechaRaw === '' || $apptFechaRaw === '0000-00-00' || $apptTs === false || $apptTs <= 0) {
                    continue;
                }
            }

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

            $allPostLeadsForRatio[] = $merged;
        }
    }
}

// Filter post-leads by date
$displayPostLeadsForRatio = [];
if ($startDate === '' && $endDate === '') {
    $displayPostLeadsForRatio = $allPostLeadsForRatio;
} else {
    $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
    $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;

    foreach ($allPostLeadsForRatio as $lead) {
        $dateField = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
        if (empty($dateField)) continue;
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $dateField, $_mPoQ)) continue;
        $d = $_mPoQ[1];

        if ($sd && $d < $sd) continue;
        if ($ed && $d > $ed) continue;

        $displayPostLeadsForRatio[] = $lead;
    }
}

$leadsPostCountFiltered = count($displayPostLeadsForRatio);
$leadsWithFechaCambio = $leadsCountFiltered;

$conversionRatio = 0;
if ($leadsPostCountFiltered > 0) {
    $conversionRatio = round(($leadsWithFechaCambio / $leadsPostCountFiltered) * 100, 2);
}

// ============================================================================
// LINE CHART DATA: Cierres por día (uses fecha_cambio_cliente)
// ============================================================================

$map = [];
$minTs = null;
$maxTs = null;
foreach ($displayLeads as $lead) {
    $ct = $lead['fecha_cambio_cliente'] ?? '';
    if (empty($ct)) continue;
    $ts = strtotime($ct);
    if ($ts === false) continue;
    $d = date('Y-m-d', $ts);
    if (!isset($map[$d])) $map[$d] = 0;
    $map[$d]++;
    if ($minTs === null || $ts < $minTs) $minTs = $ts;
    if ($maxTs === null || $ts > $maxTs) $maxTs = $ts;
}

$end = strtotime('today');
$start = $end - (59 * 86400);
if ($minTs !== null) {
    $minDayStart = strtotime(date('Y-m-d', $minTs));
    if ($minDayStart > $start) $start = $minDayStart;
}
if ($maxTs !== null) {
    $maxDay = strtotime(date('Y-m-d', $maxTs));
    if ($maxDay < $end) $end = $maxDay;
}

$dates = [];
$counts = [];
for ($ts = $start; $ts <= $end; $ts += 86400) {
    $d = date('Y-m-d', $ts);
    $c = isset($map[$d]) ? $map[$d] : 0;
    $dates[] = $d;
    $counts[] = $c;
}
$datesJson = json_encode($dates);
$countsJson = json_encode($counts);

// ============================================================================
// PIE CHART DATA: Contact Method
// ============================================================================

$contactMethodCounts = [];
foreach ($displayLeads as $lead) {
    $label = normalizeFirstContactChannelLabel_rck($lead['first_contact_channel'] ?? '');
    if ($label === '') $label = 'Sin dato';
    $contactMethodCounts[$label] = ($contactMethodCounts[$label] ?? 0) + 1;
}

$howContactPieData = [];
$howContactLabelColors = [
    'WhatsApp'                      => '#3B82F6',
    'Instagram DM - Campaña'        => '#10B981',
    'Instagram DM - Orgánico'       => '#A855F7',
    'Correo electrónico'            => '#F59E0B',
    'Phone call'                    => '#EF4444',
    'Sin dato'                      => '#94a3b8',
];
foreach ($contactMethodCounts as $hlabel => $hcount) {
    if ($hcount <= 0) continue;
    $howContactPieData[] = ['name' => $hlabel, 'y' => $hcount, 'color' => ($howContactLabelColors[$hlabel] ?? null)];
}
$howContactPieJson = json_encode($howContactPieData, JSON_UNESCAPED_UNICODE);

// ============================================================================
// PIE CHART DATA: How Long Known Us
// ============================================================================

$howLongKnownUsLabelMap = [
    'less than 3 months'          => 'Menos de 3 meses',
    'between 3 months and 1 year' => 'Entre 3 meses y 1 año',
    'more than 1 year'            => 'Más de 1 año',
    'not asked'                   => 'No se preguntó',
];
$howLongCounts = [];
foreach ($displayLeads as $lead) {
    $raw = trim((string)($lead['how_long_known_us'] ?? ''));
    $lbl = ($raw === '' || $raw === '—') ? 'Sin dato' : ($howLongKnownUsLabelMap[mb_strtolower($raw, 'UTF-8')] ?? $raw);
    $howLongCounts[$lbl] = ($howLongCounts[$lbl] ?? 0) + 1;
}
arsort($howLongCounts);

$howLongPieData = [];
$howLongColorMap = [
    'Menos de 3 meses'      => '#C5A028',
    'Entre 3 meses y 1 año' => '#3B82F6',
    'Más de 1 año'          => '#10B981',
    'No se preguntó'        => '#A855F7',
    'Sin dato'              => '#94a3b8',
];
foreach ($howLongCounts as $llabel => $lcount) {
    if ($lcount <= 0) continue;
    $howLongPieData[] = ['name' => $llabel, 'y' => $lcount, 'color' => ($howLongColorMap[$llabel] ?? null)];
}
$howLongPieJson = json_encode($howLongPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ============================================================================
// PIE CHART DATA: Where They Know Us From (how_did_you_meet)
// ============================================================================

$howDidYouMeetCounts = ['Wedding Planner' => 0, 'Community' => 0, 'New Market' => 0, 'Sin dato' => 0];
foreach ($displayLeads as $lead) {
    $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
    if ($howRaw === '1') $howDidYouMeetCounts['Wedding Planner']++;
    elseif ($howRaw === '2') $howDidYouMeetCounts['Community']++;
    elseif ($howRaw === '3') $howDidYouMeetCounts['New Market']++;
    else $howDidYouMeetCounts['Sin dato']++;
}

$whereKnowPieData = [];
$whereKnowColorMap = [
    'Wedding Planner' => '#3B82F6',
    'Community'       => '#10B981',
    'New Market'      => '#A855F7',
    'Sin dato'        => '#94a3b8',
];
foreach ($howDidYouMeetCounts as $wlabel => $wcount) {
    if ($wcount <= 0) continue;
    $whereKnowPieData[] = ['name' => $wlabel, 'y' => $wcount, 'color' => ($whereKnowColorMap[$wlabel] ?? null)];
}
$whereKnowPieJson = json_encode($whereKnowPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ============================================================================
// REPORT RANGE LABEL
// ============================================================================

$reportRangeLabel = 'Todos los registros';
if (($startDate !== '' || $endDate !== '') && empty($_GET['show_all'])) {
    $reportRangeStart = $startDate !== '' ? date('d/m/Y', strtotime($startDate)) : '...';
    $reportRangeEnd   = $endDate   !== '' ? date('d/m/Y', strtotime($endDate))   : '...';
    $reportRangeLabel = $reportRangeStart . ' → ' . $reportRangeEnd;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Cierres KPIs</title>
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
    padding: 14px 20px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    position: sticky;
    top: 20px;
    z-index: 100;
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
            <div class="efege-page-title"><span class="efege-page-title-sub">Reporte Cierres KPIs</span></div>
            <div class="efege-page-subtitle">Reporte de clientes cerrados · Plataforma: <strong><?php echo htmlspecialchars($platformLabel); ?></strong></div>
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
                <h2 class="reports-section-title">Reporte de cierres</h2>
                <p class="reports-section-subtitle">Clientes que cerraron en el período seleccionado. Usa el filtro de fechas para actualizar este reporte.</p>
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
                    <div class="report-card-value"><?php echo ($leadsPostCountFiltered > 0) ? number_format($conversionRatio, 1) . '%' : 'N/A'; ?></div>
                    <div class="report-card-note"><?php echo number_format($leadsWithFechaCambio); ?> cierres de <?php echo number_format($leadsPostCountFiltered); ?> agendados</div>
                    <div class="report-formula"><strong>Fórmula:</strong> (Clientes cerrados / Total agendados) × 100</div>
                </article>

                <div class="reports-kpi-grid-secondary">
                    <article class="report-card">
                        <div class="report-card-label">Total cierres</div>
                        <div class="report-card-value"><?php echo number_format($leadsCountFiltered); ?></div>
                        <div class="report-card-note">Clientes en el período.</div>
                    </article>

                    <article class="report-card">
                        <div class="report-card-label">Total agendados</div>
                        <div class="report-card-value"><?php echo number_format($leadsPostCountFiltered); ?></div>
                        <div class="report-card-note">Registros con cita en el periodo.</div>
                    </article>
                </div>
            </div>

            <div class="reports-charts-row" style="flex-direction:column;width:100%;">
                <article class="report-chart-card" style="width:100%;">
                    <div class="report-chart-header">
                        <div>
                            <h3 class="report-chart-title">Cierres por período</h3>
                            <p class="report-chart-subtitle">Total de cierres por día dentro del rango seleccionado.</p>
                        </div>
                    </div>
                    <div id="clientsChart" class="report-chart"></div>
                </article>
            </div>
        </div>

        <div style="display:flex;gap:12px;width:100%;margin-top:16px;">
            <article class="report-chart-card" style="flex:1 1 0;min-width:0;">
                <div class="report-chart-header">
                    <div>
                        <h3 class="report-chart-title">Método de contacto</h3>
                        <p class="report-chart-subtitle">Distribución de cierres según origen del cliente.</p>
                    </div>
                </div>
                <div id="clientsContactChart" class="report-chart"></div>
            </article>

            <article class="report-chart-card" style="flex:1 1 0;min-width:0;">
                <div class="report-chart-header">
                    <div>
                        <h3 class="report-chart-title">Desde cuándo nos conoce</h3>
                        <p class="report-chart-subtitle">Distribución de cierres según el tiempo que llevan conociendo a Efege.</p>
                    </div>
                </div>
                <div id="clientsHowLongChart" class="report-chart"></div>
            </article>

            <article class="report-chart-card" style="flex:1 1 0;min-width:0;">
                <div class="report-chart-header">
                    <div>
                        <h3 class="report-chart-title">De dónde nos conocen</h3>
                        <p class="report-chart-subtitle">Distribución de cierres según cómo llegaron a Efege (Wedding Planner, Community, New Market).</p>
                    </div>
                </div>
                <div id="clientsWhereKnowChart" class="report-chart"></div>
            </article>
        </div>
    </section>

</div><!-- /efege-page -->

<script src="assets/static/js/components/dark.js"></script>
<script src="assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/compiled/js/app.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var chartDates  = <?php echo isset($datesJson)  ? $datesJson  : '[]'; ?>;
    var chartCounts = <?php echo isset($countsJson) ? $countsJson : '[]'; ?>;
    var howContactSeries  = <?php echo isset($howContactPieJson) ? $howContactPieJson : '[]'; ?>;
    var howLongSeries     = <?php echo isset($howLongPieJson) ? $howLongPieJson : '[]'; ?>;
    var whereKnowSeries   = <?php echo isset($whereKnowPieJson) ? $whereKnowPieJson : '[]'; ?>;

    var maxCierres = 0;
    chartCounts.forEach(function(c) { if (c > maxCierres) maxCierres = c; });
    var yAxisMax = Math.ceil(maxCierres * 1.15);
    if (yAxisMax < 5) yAxisMax = 5;
    var tickInterval = Math.ceil(yAxisMax / 5);
    if (tickInterval < 1) tickInterval = 1;

    // LINE CHART: Cierres por período
    Highcharts.chart('clientsChart', {
        chart: {
            type: 'line',
            backgroundColor: '#f8f9fa',
            borderRadius: 12,
            spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10
        },
        title: { text: null },
        xAxis: {
            categories: chartDates,
            crosshair: true,
            labels: { rotation: -45, style: { fontSize: '11px' } },
            lineColor: '#d9e2ec'
        },
        yAxis: {
            min: 0,
            max: yAxisMax,
            tickInterval: tickInterval,
            title: { text: 'Cierres' },
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
        series: [{ name: 'Cierres', data: chartCounts }],
        tooltip: {
            backgroundColor: 'rgba(15,23,42,0.92)',
            style: { color: '#fff' },
            borderWidth: 0,
            formatter: function() {
                return '<b>' + this.x + '</b><br/>Cierres: <b>' + this.y + '</b>';
            }
        },
        credits: { enabled: false }
    });

    // PIE CHART OPTIONS
    var pieOptions = {
        chart: {
            type: 'pie',
            backgroundColor: '#f8f9fa',
            borderRadius: 12,
            spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10
        },
        title: { text: null },
        tooltip: { pointFormat: '<b>{point.y}</b> cierres ({point.percentage:.1f}%)' },
        accessibility: { point: { valueSuffix: '%' } },
        plotOptions: {
            pie: {
                innerSize: '48%',
                allowPointSelect: true,
                cursor: 'pointer',
                dataLabels: {
                    enabled: true,
                    format: '<b>{point.name}</b><br>{point.y} cierres'
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

    // PIE CHART: Método de contacto
    Highcharts.chart('clientsContactChart', Object.assign({}, pieOptions, {
        series: [{ name: 'Método de contacto', colorByPoint: true, data: howContactSeries }]
    }));

    // PIE CHART: Desde cuándo nos conoce
    Highcharts.chart('clientsHowLongChart', Object.assign({}, pieOptions, {
        series: [{ name: '¿Desde cuándo nos conoce?', colorByPoint: true, data: howLongSeries }]
    }));

    // PIE CHART: De dónde nos conocen
    Highcharts.chart('clientsWhereKnowChart', Object.assign({}, pieOptions, {
        series: [{ name: '¿De dónde nos conocen?', colorByPoint: true, data: whereKnowSeries }]
    }));
});
</script>
</body>
</html>
