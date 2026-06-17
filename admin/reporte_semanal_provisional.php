<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tipoUsuario = $_SESSION['tipo_usuario'] ?? 0;

include 'menu.php';
include 'conn.php';

// ======================== FUNCIONES AUXILIARES ========================

function getCohortLabel($dateStr)
{
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    if ($ts === false) return '';
    return date('o', $ts) . '-W' . str_pad((int)date('W', $ts), 2, '0', STR_PAD_LEFT);
}

function getISOWeekRange($isoYear, $isoWeek)
{
    $dto = new DateTime();
    $dto->setISODate($isoYear, $isoWeek, 1);
    $monday = $dto->format('Y-m-d');
    $dto->setISODate($isoYear, $isoWeek, 7);
    $sunday = $dto->format('Y-m-d');
    return [$monday, $sunday];
}

function subtractISOWeeks($currentLabel, $weeksBack)
{
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $currentLabel, $m)) return $currentLabel;
    $y = (int)$m[1];
    $w = (int)$m[2];
    $dto = new DateTime();
    $dto->setISODate($y, $w, 1);
    $dto->modify("-{$weeksBack} weeks");
    return $dto->format('o') . '-W' . str_pad((int)$dto->format('W'), 2, '0', STR_PAD_LEFT);
}

function addISOWeeks($currentLabel, $weeksForward)
{
    if (!preg_match('/^(\d{4})-W(\d{2})$/', $currentLabel, $m)) return $currentLabel;
    $dto = new DateTime();
    $dto->setISODate((int)$m[1], (int)$m[2], 1);
    $dto->modify("+{$weeksForward} weeks");
    return $dto->format('o') . '-W' . str_pad((int)$dto->format('W'), 2, '0', STR_PAD_LEFT);
}

function getOriginCategoryLabel($value)
{
    $map = ['1' => 'Wedding Planner', '2' => 'Community', '3' => 'New Market'];
    return $map[trim((string)$value)] ?? '';
}

function getOriginCategoryFromTipo($tipo)
{
    $map = [2 => 'Wedding Planner', 0 => 'Community', 1 => 'New Market'];
    return $map[$tipo] ?? 'Sin categoría';
}

function deriveOriginChannel($lead)
{
    $cn = trim($lead['campaign_name'] ?? '');
    if ($cn !== '' && $cn !== 'N/A') return $cn;
    $fc = trim($lead['first_contact_channel'] ?? '');
    if ($fc !== '') return $fc;
    $pl = trim($lead['platform'] ?? '');
    if ($pl !== '') return $pl;
    $to = trim($lead['tabla_origen'] ?? '');
    if ($to !== '') return $to;
    return 'Sin datos';
}

function fmtDate($d) {
    if (empty($d)) return '—';
    $ts = strtotime($d);
    return $ts ? date('d M Y', $ts) : $d;
}
function fmtDateShort($d) {
    if (empty($d)) return '—';
    $ts = strtotime($d);
    return $ts ? date('d M', $ts) : $d;
}
function safeRate($num, $den) {
    return $den > 0 ? round(($num / $den) * 100, 1) : 0;
}
function trendArrow($current, $previous) {
    if ($previous == 0 && $current == 0) return '<span style="color:var(--rs-light)">—</span>';
    if ($previous == 0) return '<span style="color:#10B981"><i class="fas fa-arrow-up"></i> nuevo</span>';
    $diff = $current - $previous;
    $pct = round(($diff / $previous) * 100, 0);
    if ($diff > 0) return '<span style="color:#10B981"><i class="fas fa-arrow-up"></i> +' . $pct . '%</span>';
    if ($diff < 0) return '<span style="color:#EF4444"><i class="fas fa-arrow-down"></i> ' . $pct . '%</span>';
    return '<span style="color:var(--rs-light)"><i class="fas fa-minus"></i> 0%</span>';
}
function catBadge($cat) {
    $map = [
        'Wedding Planner' => 'rs-badge-wp',
        'Community' => 'rs-badge-community',
        'New Market' => 'rs-badge-newmarket',
    ];
    $cls = $map[$cat] ?? 'rs-badge-sincat';
    return '<span class="rs-badge ' . $cls . '">' . htmlspecialchars($cat) . '</span>';
}

// ======================== NAVEGACIÓN DE SEMANAS (parámetro GET) ========================

$realCurrentWeek = date('o') . '-W' . str_pad((int)date('W'), 2, '0', STR_PAD_LEFT);

// Permitir navegación a semanas anteriores vía ?week=2026-W10
$selectedWeek = isset($_GET['week']) ? trim($_GET['week']) : $realCurrentWeek;
if (!preg_match('/^\d{4}-W\d{2}$/', $selectedWeek)) {
    $selectedWeek = $realCurrentWeek;
}
// No permitir seleccionar semanas futuras
if ($selectedWeek > $realCurrentWeek) {
    $selectedWeek = $realCurrentWeek;
}

$currentCohort = $selectedWeek;
$cohortW1 = subtractISOWeeks($currentCohort, 1);
$cohortW2 = subtractISOWeeks($currentCohort, 2);
$cohortW3 = subtractISOWeeks($currentCohort, 3);

// 8 semanas para tendencia histórica del Block 2
$historicalWeeks = [];
for ($i = 3; $i <= 10; $i++) {
    $historicalWeeks[] = subtractISOWeeks($currentCohort, $i);
}

$cohortLabels4 = [$currentCohort, $cohortW1, $cohortW2, $cohortW3];
$allRelevantCohorts = array_unique(array_merge($cohortLabels4, $historicalWeeks));

// Fechas lunes-domingo de cada cohorte para display
$cohortDateRanges = [];
foreach ($allRelevantCohorts as $cl) {
    preg_match('/^(\d{4})-W(\d{2})$/', $cl, $m);
    $cohortDateRanges[$cl] = getISOWeekRange((int)$m[1], (int)$m[2]);
}

$thisWeekRange = $cohortDateRanges[$currentCohort];

// Semanas para navegación
$prevWeek = subtractISOWeeks($currentCohort, 1);
$nextWeek = addISOWeeks($currentCohort, 1);
$canGoNext = ($nextWeek <= $realCurrentWeek);

// ======================== OBTENER MAPA DE TABLA_ORIGEN => TIPO ========================

$tablaTipoMap = [];
$sqlTablasMap = "SELECT nombre, tipo FROM tablas_leads";
$resultTablasMap = $conn->query($sqlTablasMap);
if ($resultTablasMap && $resultTablasMap->num_rows > 0) {
    while ($row = $resultTablasMap->fetch_assoc()) {
        $tablaTipoMap[$row['nombre']] = intval($row['tipo']);
    }
}

// ======================== 1. OBTENER TODOS LOS LEADS PRE-CALIFICADOS ========================

$tablasPreLeads = [];
$sqlTablas = "SELECT nombre FROM tablas_leads ORDER BY nombre";
$resultTablas = $conn->query($sqlTablas);
if ($resultTablas && $resultTablas->num_rows > 0) {
    while ($row = $resultTablas->fetch_assoc()) {
        $tablasPreLeads[] = $row['nombre'];
    }
}

$allPreLeads = [];
foreach ($tablasPreLeads as $tableName) {
    $escapedName = $conn->real_escape_string($tableName);
    $checkTable = $conn->query("SHOW TABLES LIKE '" . $escapedName . "'");
    if (!$checkTable || $checkTable->num_rows === 0) continue;

    $columns = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM `" . $escapedName . "`");
    while ($col = $columnsResult->fetch_assoc()) {
        $columns[] = $col['Field'];
    }

    $whereParts = [];
    if (in_array('descartado', $columns)) {
        $whereParts[] = "(descartado = 0 OR descartado IS NULL)";
    }
    $whereSql = !empty($whereParts) ? implode(' AND ', $whereParts) : '1=1';
    $orderCol = in_array('created_time', $columns) ? 'created_time' : (in_array('id', $columns) ? 'id' : null);
    $orderSql = $orderCol ? " ORDER BY `$orderCol` DESC" : '';

    $sqlLeads = "SELECT *, '" . $escapedName . "' as tabla_origen FROM `" . $escapedName . "` WHERE " . $whereSql . $orderSql;
    $resultLeads = $conn->query($sqlLeads);
    if ($resultLeads && $resultLeads->num_rows > 0) {
        while ($lead = $resultLeads->fetch_assoc()) {
            $allPreLeads[] = $lead;
        }
    }
}

// ======================== 2. CRUZAR CON CONTACT_FORM ========================

$leadIdsByTable = [];
foreach ($allPreLeads as $lead) {
    $t = $lead['tabla_origen'] ?? '';
    $id = intval($lead['id'] ?? 0);
    if ($t === '' || $id <= 0) continue;
    if (!isset($leadIdsByTable[$t])) $leadIdsByTable[$t] = [];
    $leadIdsByTable[$t][] = $id;
}

$contactFormByLead = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '') continue;

    $sql = "SELECT * FROM contact_form 
            WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') 
            AND original_lead_id IN (" . $idsList . ")";
    $resCf = $conn->query($sql);
    if ($resCf) {
        while ($row = $resCf->fetch_assoc()) {
            $key = $t . '|' . intval($row['original_lead_id']);
            $contactFormByLead[$key] = $row;
        }
    }
}

// ======================== 3. OBTENER CITAS DEL CALENDARIO ========================

$allCfIds = [];
foreach ($contactFormByLead as $cfData) {
    $allCfIds[] = intval($cfData['id']);
}

$directContactForms = [];
$sqlDirect = "SELECT * FROM contact_form ORDER BY submission_date DESC";
$resDirect = $conn->query($sqlDirect);
if ($resDirect && $resDirect->num_rows > 0) {
    while ($row = $resDirect->fetch_assoc()) {
        $directContactForms[intval($row['id'])] = $row;
        $allCfIds[] = intval($row['id']);
    }
}
$allCfIds = array_unique(array_filter($allCfIds));

$appointmentsByCFId = [];
if (!empty($allCfIds)) {
    $cfList = implode(',', array_map('intval', $allCfIds));
    $apptRes = $conn->query("SELECT idclie, estatus, fecha FROM calendario WHERE idclie IN (" . $cfList . ")");
    if ($apptRes && $apptRes->num_rows > 0) {
        while ($ar = $apptRes->fetch_assoc()) {
            $cid = intval($ar['idclie']);
            if (!isset($appointmentsByCFId[$cid]) || ($ar['fecha'] > $appointmentsByCFId[$cid]['fecha'])) {
                $appointmentsByCFId[$cid] = $ar;
            }
        }
    }
}

// ======================== 4. CONSTRUIR LISTA UNIFICADA DE LEADS ========================

$allLeads = [];
$seenCfIds = [];

foreach ($allPreLeads as $lead) {
    $t = $lead['tabla_origen'] ?? '';
    $lid = intval($lead['id'] ?? 0);
    $key = $t . '|' . $lid;
    $cfRow = $contactFormByLead[$key] ?? null;

    $entryDate = $lead['created_time'] ?? '';
    $tipo = $tablaTipoMap[$t] ?? -1;

    $cfId = $cfRow ? intval($cfRow['id']) : 0;
    $howDidYouMeet = $cfRow['how_did_you_meet'] ?? '';
    $firstContactChannel = $cfRow['first_contact_channel'] ?? ($lead['first_contact_channel'] ?? '');
    $campaignName = $lead['campaign_name'] ?? ($cfRow['campaign_name'] ?? '');
    $platform = $lead['platform'] ?? '';
    $fechaCambioCliente = $cfRow['fecha_cambio_cliente'] ?? '';
    $isCliente = $cfRow ? (intval($cfRow['cliente'] ?? 0) === 1) : false;
    $names = $lead['full_name'] ?? ($lead['name'] ?? ($cfRow['names'] ?? ''));
    $montoVenta = $cfRow ? floatval($cfRow['monto_venta'] ?? 0) : 0;

    if ($cfRow) $seenCfIds[] = $cfId;

    $sessionDate = '';
    $sessionEstatus = '';
    if ($cfId > 0 && isset($appointmentsByCFId[$cfId])) {
        $sessionDate = $appointmentsByCFId[$cfId]['fecha'] ?? '';
        $sessionEstatus = $appointmentsByCFId[$cfId]['estatus'] ?? '';
    }

    $originCategory = getOriginCategoryLabel($howDidYouMeet);
    if ($originCategory === '' && $tipo >= 0) {
        $originCategory = getOriginCategoryFromTipo($tipo);
    }
    if ($originCategory === '') $originCategory = 'Sin categoría';

    $merged = array_merge($lead, ['first_contact_channel' => $firstContactChannel, 'campaign_name' => $campaignName, 'platform' => $platform]);
    $originChannel = deriveOriginChannel($merged);
    $cohortLabel = getCohortLabel($entryDate);
    $closeDate = ($isCliente && !empty($fechaCambioCliente) && $fechaCambioCliente !== '0000-00-00') ? $fechaCambioCliente : '';

    $allLeads[] = [
        'nombre'            => $names,
        'email'             => $lead['email'] ?? ($cfRow['email_address'] ?? ''),
        'entry_date'        => $entryDate,
        'cohort_label'      => $cohortLabel,
        'session_date'      => $sessionDate,
        'session_estatus'   => $sessionEstatus,
        'close_date'        => $closeDate,
        'is_cliente'        => $isCliente,
        'origin_channel'    => $originChannel,
        'origin_category'   => $originCategory,
        'campaign_name'     => $campaignName,
        'tabla_origen'      => $t,
        'tipo'              => $tipo,
        'cf_id'             => $cfId,
        'monto_venta'       => $montoVenta,
    ];
}

foreach ($directContactForms as $cfId => $cf) {
    if (in_array($cfId, $seenCfIds)) continue;

    $tablaOrigen = strtolower(trim($cf['tabla_origen'] ?? ''));
    if ($tablaOrigen === 'wedding_planners' || $tablaOrigen === 'wedding_planner') continue;

    $howDidYouMeet = $cf['how_did_you_meet'] ?? '';
    $tipo = $tablaTipoMap[$cf['tabla_origen'] ?? ''] ?? -1;

    $originCategory = getOriginCategoryLabel($howDidYouMeet);
    if ($originCategory === '' && $tipo >= 0) {
        $originCategory = getOriginCategoryFromTipo($tipo);
    }
    if ($originCategory === '') $originCategory = 'Sin categoría';

    $entryDate = $cf['created_time'] ?? ($cf['submission_date'] ?? '');
    $cohortLabel = getCohortLabel($entryDate);

    $isCliente = intval($cf['cliente'] ?? 0) === 1;
    $fechaCambioCliente = $cf['fecha_cambio_cliente'] ?? '';
    $closeDate = ($isCliente && !empty($fechaCambioCliente) && $fechaCambioCliente !== '0000-00-00') ? $fechaCambioCliente : '';

    $sessionDate = '';
    $sessionEstatus = '';
    if (isset($appointmentsByCFId[intval($cfId)])) {
        $sessionDate = $appointmentsByCFId[intval($cfId)]['fecha'] ?? '';
        $sessionEstatus = $appointmentsByCFId[intval($cfId)]['estatus'] ?? '';
    }

    $allLeads[] = [
        'nombre'            => $cf['names'] ?? '',
        'email'             => $cf['email_address'] ?? ($cf['email'] ?? ''),
        'entry_date'        => $entryDate,
        'cohort_label'      => $cohortLabel,
        'session_date'      => $sessionDate,
        'session_estatus'   => $sessionEstatus,
        'close_date'        => $closeDate,
        'is_cliente'        => $isCliente,
        'origin_channel'    => deriveOriginChannel($cf),
        'origin_category'   => $originCategory,
        'campaign_name'     => $cf['campaign_name'] ?? '',
        'tabla_origen'      => $cf['tabla_origen'] ?? '',
        'tipo'              => $tipo,
        'cf_id'             => intval($cfId),
        'monto_venta'       => floatval($cf['monto_venta'] ?? 0),
    ];
}

// ======================== 4b. RECOLECTAR AÑOS/MESES/SEMANAS DISPONIBLES ========================

$availableWeeks = [];
foreach ($allLeads as $lead) {
    $cl = $lead['cohort_label'];
    if (empty($cl) || !preg_match('/^(\d{4})-W(\d{2})$/', $cl, $m)) continue;
    if (isset($availableWeeks[$cl])) continue;
    $y = (int)$m[1]; $w = (int)$m[2];
    $dto = new DateTime(); $dto->setISODate($y, $w, 1);
    $mo = (int)$dto->format('n');
    $wr = getISOWeekRange($y, $w);
    if ($cl > $realCurrentWeek) continue;
    $availableWeeks[$cl] = ['year' => $y, 'month' => $mo, 'week' => $w, 'range' => $wr];
}
krsort($availableWeeks);

$availableYears = [];
$availableMonths = [];
$availableWeeksByMonth = [];
$monthNames = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

foreach ($availableWeeks as $cl => $info) {
    $y = $info['year']; $mo = $info['month'];
    $availableYears[$y] = true;
    if (!isset($availableMonths[$y])) $availableMonths[$y] = [];
    $availableMonths[$y][$mo] = true;
    $key = $y . '-' . str_pad($mo, 2, '0', STR_PAD_LEFT);
    if (!isset($availableWeeksByMonth[$key])) $availableWeeksByMonth[$key] = [];
    $availableWeeksByMonth[$key][$cl] = $info;
}
krsort($availableYears);
foreach ($availableMonths as &$mos) { krsort($mos); }
unset($mos);

$selectedYear = null; $selectedMonth = null;
if (isset($availableWeeks[$currentCohort])) {
    $selectedYear = $availableWeeks[$currentCohort]['year'];
    $selectedMonth = $availableWeeks[$currentCohort]['month'];
} else {
    preg_match('/^(\d{4})-W(\d{2})$/', $currentCohort, $sm);
    $dto2 = new DateTime(); $dto2->setISODate((int)$sm[1], (int)$sm[2], 1);
    $selectedYear = (int)$sm[1]; $selectedMonth = (int)$dto2->format('n');
}

$filterDataJS = [];
foreach ($availableWeeksByMonth as $ym => $weeks) {
    foreach ($weeks as $cl => $info) {
        $filterDataJS[] = [
            'year' => $info['year'],
            'month' => $info['month'],
            'ym' => $ym,
            'week' => $cl,
            'label' => $cl . ' (' . fmtDateShort($info['range'][0]) . ' – ' . fmtDateShort($info['range'][1]) . ')',
        ];
    }
}

// ======================== 5. AGRUPAR POR COHORTE — incluyendo historial ========================

$block1Leads = array_filter($allLeads, fn($l) => $l['cohort_label'] === $currentCohort);

// Inicializar datos para las 4 semanas principales + histórico
$cohortData = [];
foreach ($allRelevantCohorts as $cl) {
    if (!isset($cohortDateRanges[$cl])) continue;
    $cohortData[$cl] = [
        'label' => $cl, 'range' => $cohortDateRanges[$cl],
        'total' => 0, 'sessions' => 0, 'closed' => 0, 'revenue' => 0,
        'by_category' => [
            'Wedding Planner' => ['total' => 0, 'sessions' => 0, 'closed' => 0],
            'Community'       => ['total' => 0, 'sessions' => 0, 'closed' => 0],
            'New Market'      => ['total' => 0, 'sessions' => 0, 'closed' => 0],
            'Sin categoría'   => ['total' => 0, 'sessions' => 0, 'closed' => 0],
        ],
    ];
}

foreach ($allLeads as $lead) {
    $cl = $lead['cohort_label'];
    if (!isset($cohortData[$cl])) continue;

    $cohortData[$cl]['total']++;
    $hasSession = !empty($lead['session_date']) && $lead['session_date'] !== '0000-00-00';
    $hasClosed = !empty($lead['close_date']) && $lead['close_date'] !== '0000-00-00';
    if ($hasSession) $cohortData[$cl]['sessions']++;
    if ($hasClosed) {
        $cohortData[$cl]['closed']++;
        $cohortData[$cl]['revenue'] += $lead['monto_venta'];
    }

    $cat = $lead['origin_category'];
    if (!isset($cohortData[$cl]['by_category'][$cat])) {
        $cohortData[$cl]['by_category'][$cat] = ['total' => 0, 'sessions' => 0, 'closed' => 0];
    }
    $cohortData[$cl]['by_category'][$cat]['total']++;
    if ($hasSession) $cohortData[$cl]['by_category'][$cat]['sessions']++;
    if ($hasClosed) $cohortData[$cl]['by_category'][$cat]['closed']++;
}

// Block 3: Cierres de la semana seleccionada
$block3Leads = [];
$weekMonday = $thisWeekRange[0];
$weekSunday = $thisWeekRange[1];
foreach ($allLeads as $lead) {
    if (empty($lead['close_date']) || $lead['close_date'] === '0000-00-00') continue;
    if ($lead['close_date'] >= $weekMonday && $lead['close_date'] <= $weekSunday) {
        $block3Leads[] = $lead;
    }
}

// ======================== KPIs BLOQUE 1 ========================

$b1Total = count($block1Leads);
$b1Sessions = 0;
$b1ByChannel = [];
$b1ByCategory = ['Wedding Planner' => ['leads' => 0, 'sessions' => 0], 'Community' => ['leads' => 0, 'sessions' => 0], 'New Market' => ['leads' => 0, 'sessions' => 0], 'Sin categoría' => ['leads' => 0, 'sessions' => 0]];
$b1PriorityFollowUp = [];

foreach ($block1Leads as $lead) {
    $hasSession = !empty($lead['session_date']) && $lead['session_date'] !== '0000-00-00';
    if ($hasSession) $b1Sessions++;

    $ch = $lead['origin_channel'];
    if (!isset($b1ByChannel[$ch])) {
        $b1ByChannel[$ch] = ['channel' => $ch, 'category' => $lead['origin_category'], 'leads' => 0, 'sessions' => 0];
    }
    $b1ByChannel[$ch]['leads']++;
    if ($hasSession) $b1ByChannel[$ch]['sessions']++;

    $cat = $lead['origin_category'];
    if (!isset($b1ByCategory[$cat])) $b1ByCategory[$cat] = ['leads' => 0, 'sessions' => 0];
    $b1ByCategory[$cat]['leads']++;
    if ($hasSession) $b1ByCategory[$cat]['sessions']++;

    // Leads de planners sin sesión = seguimiento prioritario
    if ($cat === 'Wedding Planner' && !$hasSession) {
        $b1PriorityFollowUp[] = $lead;
    }
}
$b1QualRate = $b1Total > 0 ? round(($b1Sessions / $b1Total) * 100, 1) : 0;
$b1NotScheduled = $b1Total - $b1Sessions;

// Semana anterior para comparación
$prevCohortData = $cohortData[$cohortW1] ?? null;
$prevTotal = $prevCohortData ? $prevCohortData['total'] : 0;
$prevSessions = $prevCohortData ? $prevCohortData['sessions'] : 0;
$prevQualRate = $prevTotal > 0 ? round(($prevSessions / $prevTotal) * 100, 1) : 0;

usort($b1ByChannel, fn($a, $b) => $b['sessions'] <=> $a['sessions']);

// ======================== KPIs BLOQUE 2 — Tendencia histórica ========================

$matureCohort = $cohortData[$cohortW3] ?? ['total' => 0, 'sessions' => 0, 'closed' => 0, 'revenue' => 0, 'by_category' => []];
$matureRate = safeRate($matureCohort['closed'], $matureCohort['total']);

// Datos para gráfica de tendencia (últimas 8 semanas maduras)
$trendLabels = [];
$trendRates = [];
$trendLeads = [];
$trendClosed = [];
foreach (array_reverse($historicalWeeks) as $hw) {
    $hd = $cohortData[$hw] ?? ['total' => 0, 'closed' => 0];
    $trendLabels[] = $hw;
    $trendRates[] = safeRate($hd['closed'], $hd['total']);
    $trendLeads[] = $hd['total'];
    $trendClosed[] = $hd['closed'];
}

// Cohorte W-4 para comparar tendencia de W-3
$cohortW4Data = !empty($historicalWeeks) ? ($cohortData[$historicalWeeks[0]] ?? null) : null;
$prevMatureRate = $cohortW4Data ? safeRate($cohortW4Data['closed'], $cohortW4Data['total']) : 0;

// ======================== KPIs BLOQUE 3 ========================

$b3Total = count($block3Leads);
$b3Revenue = 0;
$b3ByCategory = ['Wedding Planner' => 0, 'Community' => 0, 'New Market' => 0, 'Sin categoría' => 0];
$b3ByChannel = [];
$b3EntryCohorts = [];
$b3ClientDetails = [];

foreach ($block3Leads as $lead) {
    $cat = $lead['origin_category'];
    if (!isset($b3ByCategory[$cat])) $b3ByCategory[$cat] = 0;
    $b3ByCategory[$cat]++;
    $b3Revenue += $lead['monto_venta'];

    $ch = $lead['origin_channel'];
    if (!isset($b3ByChannel[$ch])) {
        $b3ByChannel[$ch] = ['channel' => $ch, 'category' => $lead['origin_category'], 'closed' => 0, 'cohort_labels' => [], 'revenue' => 0];
    }
    $b3ByChannel[$ch]['closed']++;
    $b3ByChannel[$ch]['revenue'] += $lead['monto_venta'];
    if (!empty($lead['cohort_label'])) {
        $b3ByChannel[$ch]['cohort_labels'][] = $lead['cohort_label'];
    }

    if (!empty($lead['cohort_label'])) {
        $b3EntryCohorts[] = $lead['cohort_label'];
    }

    $b3ClientDetails[] = [
        'nombre' => $lead['nombre'],
        'channel' => $lead['origin_channel'],
        'category' => $lead['origin_category'],
        'entry_date' => $lead['entry_date'],
        'cohort' => $lead['cohort_label'],
        'close_date' => $lead['close_date'],
        'monto' => $lead['monto_venta'],
    ];
}

$b3EstimatedEntry = '—';
if (!empty($b3EntryCohorts)) {
    $freq = array_count_values($b3EntryCohorts);
    arsort($freq);
    $b3EstimatedEntry = array_key_first($freq);
}

$b3PctWP = $b3Total > 0 ? round(($b3ByCategory['Wedding Planner'] / $b3Total) * 100, 1) : 0;
$b3PctCommunity = $b3Total > 0 ? round(($b3ByCategory['Community'] / $b3Total) * 100, 1) : 0;
$b3PctNewMarket = $b3Total > 0 ? round(($b3ByCategory['New Market'] / $b3Total) * 100, 1) : 0;

// Cierres de la semana pasada para comparación
$prevWeekRange = $cohortDateRanges[$cohortW1] ?? ['', ''];
$prevBlock3Count = 0;
foreach ($allLeads as $lead) {
    if (empty($lead['close_date']) || $lead['close_date'] === '0000-00-00') continue;
    if ($lead['close_date'] >= $prevWeekRange[0] && $lead['close_date'] <= $prevWeekRange[1]) {
        $prevBlock3Count++;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Semanal de Leads — <?php echo htmlspecialchars($currentCohort); ?> — EFEGE</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">

    <style>
        :root {
            --rs-bg: #F8FAFC; --rs-card: #FFFFFF; --rs-border: #E2E8F0; --rs-text: #1E293B; --rs-muted: #64748B; --rs-light: #94A3B8;
            --rs-blue: #3B82F6; --rs-blue-bg: #EFF6FF; --rs-blue-dark: #1D4ED8;
            --rs-green: #10B981; --rs-green-bg: #ECFDF5; --rs-green-dark: #065F46;
            --rs-amber: #F59E0B; --rs-amber-bg: #FFFBEB; --rs-amber-dark: #92400E;
            --rs-red: #EF4444; --rs-purple: #8B5CF6;
        }
        body { background: var(--rs-bg) !important; }
        * { font-family: 'DM Sans', sans-serif; }
        .rs-wrapper { max-width: 1440px; margin: 0 auto; padding: 24px 20px; }

        /* Header */
        .rs-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 28px; }
        .rs-page-title { font-weight: 700; font-size: 24px; color: var(--rs-text); margin: 0 0 4px; }
        .rs-page-sub { color: var(--rs-muted); font-size: 13px; margin: 0; }
        .rs-week-badge { display: inline-block; background: var(--rs-blue); color: #fff; font-size: 12px; font-weight: 600; padding: 3px 12px; border-radius: 20px; margin-left: 8px; vertical-align: middle; }

        /* Week navigation */
        .rs-nav { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .rs-nav-btn { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--rs-border); background: var(--rs-card); color: var(--rs-muted); cursor: pointer; transition: all .15s; text-decoration: none; }
        .rs-nav-btn:hover { background: var(--rs-blue-bg); color: var(--rs-blue); border-color: var(--rs-blue); }
        .rs-nav-btn.disabled { opacity: .35; pointer-events: none; }
        .rs-nav-select { padding: 6px 12px; border-radius: 10px; border: 1px solid var(--rs-border); background: var(--rs-card); font-size: 13px; font-weight: 500; color: var(--rs-text); cursor: pointer; }
        .rs-nav-today { padding: 6px 14px; border-radius: 10px; border: 1px solid var(--rs-blue); background: var(--rs-blue-bg); color: var(--rs-blue); font-size: 12px; font-weight: 600; text-decoration: none; }
        .rs-nav-today:hover { background: var(--rs-blue); color: #fff; }
        .rs-print-btn { padding: 6px 14px; border-radius: 10px; border: 1px solid var(--rs-border); background: var(--rs-card); color: var(--rs-muted); font-size: 12px; cursor: pointer; }
        .rs-print-btn:hover { background: #F1F5F9; }

        /* Executive Summary */
        .rs-exec { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 28px; }
        .rs-exec-card { background: var(--rs-card); border-radius: 14px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,.04); border-left: 4px solid transparent; }
        .rs-exec-card .num { font-weight: 700; font-size: 28px; color: var(--rs-text); line-height: 1.1; }
        .rs-exec-card .lbl { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; margin-top: 4px; }
        .rs-exec-card .trend { font-size: 11px; margin-top: 6px; }
        .rs-exec-card .sub { font-size: 10px; color: var(--rs-light); margin-top: 2px; }
        .rs-exec-card .formula { font-size: 9px; color: #B0BEC5; margin-top: 4px; font-style: italic; }
        .rs-exec-card .formula span { font-size: 8px; color: #CBD5E1; font-weight: 500; text-transform: uppercase; }

        /* Block containers */
        .rs-block { background: var(--rs-card); border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.05), 0 1px 2px rgba(0,0,0,.03); margin-bottom: 28px; overflow: hidden; }
        .rs-block-header { padding: 20px 24px 16px; border-bottom: 1px solid var(--rs-border); }
        .rs-block-number { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 10px; font-weight: 700; font-size: 16px; color: #fff; margin-right: 12px; vertical-align: middle; }
        .rs-bn-blue { background: var(--rs-blue); } .rs-bn-green { background: var(--rs-green); } .rs-bn-amber { background: var(--rs-amber); }
        .rs-block-title { font-weight: 600; font-size: 17px; color: var(--rs-text); vertical-align: middle; }
        .rs-block-dates { display: block; font-size: 12px; color: var(--rs-muted); font-weight: 400; margin-top: 4px; }
        .rs-block-body { padding: 24px; }

        /* KPI cards row */
        .rs-kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 24px; }
        .rs-kpi { padding: 18px; border-radius: 12px; text-align: center; }
        .rs-kpi-blue { background: var(--rs-blue-bg); } .rs-kpi-green { background: var(--rs-green-bg); }
        .rs-kpi-amber { background: var(--rs-amber-bg); } .rs-kpi-purple { background: #F5F3FF; }
        .rs-kpi-value { font-weight: 700; font-size: 30px; color: var(--rs-text); line-height: 1.1; }
        .rs-kpi-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin-top: 5px; }
        .rs-kpi-sub { font-size: 10px; color: var(--rs-light); margin-top: 2px; }
        .rs-kpi-formula { font-size: 9px; color: #B0BEC5; margin-top: 6px; font-style: italic; letter-spacing: .2px; }
        .rs-kpi-formula span { font-size: 8px; color: #CBD5E1; font-weight: 500; text-transform: uppercase; }
        .rs-kpi-blue .rs-kpi-label { color: var(--rs-blue-dark); } .rs-kpi-green .rs-kpi-label { color: var(--rs-green-dark); }
        .rs-kpi-amber .rs-kpi-label { color: var(--rs-amber-dark); } .rs-kpi-purple .rs-kpi-label { color: #5B21B6; }

        /* Funnel bar */
        .rs-funnel { display: flex; height: 44px; border-radius: 10px; overflow: hidden; margin-bottom: 20px; font-size: 12px; font-weight: 600; }
        .rs-funnel-seg { display: flex; align-items: center; justify-content: center; color: #fff; min-width: 60px; }
        .rs-funnel-blue { background: var(--rs-blue); } .rs-funnel-gray { background: #CBD5E1; color: #475569; }

        /* Category mini-bar inside Block 1 */
        .rs-cat-bars { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .rs-cat-bar { background: #F8FAFC; border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; }
        .rs-cat-bar-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: #fff; flex-shrink: 0; }
        .rs-cat-bar-info { flex: 1; min-width: 0; }
        .rs-cat-bar-name { font-size: 11px; font-weight: 600; color: var(--rs-muted); text-transform: uppercase; letter-spacing: .3px; }
        .rs-cat-bar-nums { font-size: 13px; font-weight: 600; color: var(--rs-text); margin-top: 2px; }
        .rs-cat-bar-rate { font-size: 11px; color: var(--rs-light); }

        /* Progress bar in table */
        .rs-progress { height: 6px; border-radius: 3px; background: #E2E8F0; overflow: hidden; min-width: 60px; }
        .rs-progress-bar { height: 100%; border-radius: 3px; }

        /* Tables */
        .rs-table { width: 100% !important; font-size: 13px; }
        .rs-table thead th { background: #F1F5F9; color: #475569; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; padding: 10px 14px; border-bottom: 2px solid var(--rs-border); white-space: nowrap; }
        .rs-table tbody td { padding: 10px 14px; color: #334155; vertical-align: middle; border-bottom: 1px solid #F1F5F9; }
        .rs-table tbody tr:hover { background: #F8FAFC; }
        .rs-table tbody tr.rs-priority { background: #FFF7ED; }

        /* Badges */
        .rs-badge { display: inline-block; padding: 2px 10px; border-radius: 6px; font-size: 11px; font-weight: 500; white-space: nowrap; }
        .rs-badge-wp { background: #DBEAFE; color: #1D4ED8; } .rs-badge-community { background: #D1FAE5; color: #065F46; }
        .rs-badge-newmarket { background: #FEF3C7; color: #92400E; } .rs-badge-sincat { background: #F1F5F9; color: #64748B; }
        .rs-badge-mature { background: #D1FAE5; color: #065F46; } .rs-badge-window { background: #FEF3C7; color: #92400E; }
        .rs-badge-early { background: #E0E7FF; color: #3730A3; } .rs-badge-fresh { background: #F1F5F9; color: #64748B; }
        .rs-badge-priority { background: #FEE2E2; color: #991B1B; font-size: 10px; }

        /* Category mini-cards for Block 2 */
        .rs-cat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 20px; }
        .rs-cat-card { border: 1px solid var(--rs-border); border-radius: 12px; padding: 16px; position: relative; overflow: hidden; }
        .rs-cat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .rs-cat-card-wp::before { background: #3B82F6; } .rs-cat-card-comm::before { background: #10B981; } .rs-cat-card-nm::before { background: #F59E0B; }
        .rs-cat-name { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        .rs-cat-rate { font-weight: 700; font-size: 28px; color: var(--rs-text); }
        .rs-cat-detail { font-size: 11px; color: var(--rs-muted); margin-top: 4px; }
        .rs-cat-expected { font-size: 10px; color: var(--rs-light); margin-top: 6px; padding-top: 6px; border-top: 1px solid #F1F5F9; }
        .rs-cat-indicator { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }

        /* Charts */
        .rs-charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 24px; }
        .rs-chart-card { border: 1px solid var(--rs-border); border-radius: 12px; padding: 16px; position: relative; }
        .rs-chart-card .rs-chart-wrap { position: relative; width: 100%; height: 280px; }
        .rs-chart-title { font-size: 13px; font-weight: 600; color: var(--rs-text); margin-bottom: 12px; }

        /* Alert banner */
        .rs-alert { background: #FFF7ED; border: 1px solid #FED7AA; border-radius: 10px; padding: 12px 16px; font-size: 12px; color: #9A3412; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 8px; }
        .rs-alert i { font-size: 14px; margin-top: 2px; }
        .rs-alert-blue { background: var(--rs-blue-bg); border-color: #93C5FD; color: var(--rs-blue-dark); }

        /* Info panel (collapsible) */
        .rs-info-toggle { background: none; border: 1px solid var(--rs-border); border-radius: 8px; padding: 8px 14px; font-size: 12px; color: var(--rs-muted); cursor: pointer; margin-top: 16px; display: inline-flex; align-items: center; gap: 6px; transition: all .2s; }
        .rs-info-toggle:hover { background: #F8FAFC; color: var(--rs-text); }
        .rs-info-panel { display: none; background: #F8FAFC; border: 1px solid var(--rs-border); border-radius: 10px; padding: 16px 20px; margin-top: 12px; font-size: 12px; color: #475569; line-height: 1.7; }
        .rs-info-panel.active { display: block; }
        .rs-info-panel ul { margin: 8px 0 0 16px; padding: 0; }
        .rs-info-panel li { margin-bottom: 6px; }
        .rs-info-panel strong { color: var(--rs-text); }

        /* Section title */
        .rs-section-title { font-size: 14px; font-weight: 600; color: var(--rs-text); margin: 24px 0 12px; display: flex; align-items: center; gap: 8px; }
        .rs-section-title i { font-size: 12px; color: var(--rs-muted); }

        /* Client detail table */
        .rs-detail-table td { font-size: 12px; padding: 8px 12px; }
        .rs-detail-table .rs-name { font-weight: 500; color: var(--rs-text); }
        .rs-detail-table .rs-monto { font-weight: 600; color: var(--rs-green-dark); }

        @media print { .rs-nav, .rs-print-btn, .rs-info-toggle, .rs-info-panel, .rs-nav-today { display: none !important; } .rs-block { break-inside: avoid; } }
        @media (max-width: 1024px) { .rs-exec { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) {
            .rs-kpi-row { grid-template-columns: repeat(2, 1fr); }
            .rs-cat-row, .rs-cat-bars { grid-template-columns: 1fr; }
            .rs-exec { grid-template-columns: repeat(2, 1fr); }
            .rs-charts-grid { grid-template-columns: 1fr; }
            .rs-header { flex-direction: column; }
        }
    </style>
</head>

<body>
<div class="rs-wrapper">

    <!-- ============================================================== -->
    <!-- HEADER CON NAVEGACIÓN DE SEMANAS                               -->
    <!-- ============================================================== -->
    <div class="rs-header">
        <div>
            <h1 class="rs-page-title">
                Reporte Semanal de Leads
                <span class="rs-week-badge"><?php echo htmlspecialchars($currentCohort); ?></span>
                <?php if ($currentCohort !== $realCurrentWeek): ?>
                    <span style="font-size:12px;color:var(--rs-amber);font-weight:500;margin-left:8px"><i class="fas fa-history"></i> Histórico</span>
                <?php endif; ?>
            </h1>
            <p class="rs-page-sub">
                Semana del <?php echo fmtDate($thisWeekRange[0]); ?> al <?php echo fmtDate($thisWeekRange[1]); ?>
                &mdash; Reporte basado en cohortes semanales &mdash; Generado <?php echo fmtDate(date('Y-m-d')); ?>
            </p>
        </div>
        <div class="rs-nav">
            <a href="?week=<?php echo urlencode($prevWeek); ?>" class="rs-nav-btn" title="Semana anterior"><i class="fas fa-chevron-left"></i></a>

            <select id="filterYear" class="rs-nav-select" title="Año">
                <?php foreach ($availableYears as $yr => $_): ?>
                <option value="<?php echo $yr; ?>" <?php echo $yr == $selectedYear ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                <?php endforeach; ?>
            </select>

            <select id="filterMonth" class="rs-nav-select" title="Mes">
                <?php if (isset($availableMonths[$selectedYear])):
                    foreach ($availableMonths[$selectedYear] as $mo => $_): ?>
                <option value="<?php echo $mo; ?>" <?php echo $mo == $selectedMonth ? 'selected' : ''; ?>><?php echo $monthNames[$mo]; ?></option>
                <?php endforeach; endif; ?>
            </select>

            <select id="filterWeek" class="rs-nav-select" title="Semana">
                <?php
                $ymKey = $selectedYear . '-' . str_pad($selectedMonth, 2, '0', STR_PAD_LEFT);
                $weeksForMonth = $availableWeeksByMonth[$ymKey] ?? [];
                krsort($weeksForMonth);
                foreach ($weeksForMonth as $wl => $wInfo):
                    $wr = $wInfo['range'];
                ?>
                <option value="<?php echo $wl; ?>" <?php echo $wl === $currentCohort ? 'selected' : ''; ?>>
                    <?php echo $wl; ?> (<?php echo fmtDateShort($wr[0]); ?> – <?php echo fmtDateShort($wr[1]); ?>)
                </option>
                <?php endforeach; ?>
            </select>

            <a href="?week=<?php echo urlencode($nextWeek); ?>" class="rs-nav-btn <?php echo !$canGoNext ? 'disabled' : ''; ?>" title="Semana siguiente"><i class="fas fa-chevron-right"></i></a>

            <?php if ($currentCohort !== $realCurrentWeek): ?>
                <a href="?week=<?php echo urlencode($realCurrentWeek); ?>" class="rs-nav-today">Hoy</a>
            <?php endif; ?>

            <button class="rs-print-btn" onclick="window.print()" title="Imprimir reporte"><i class="fas fa-print"></i> Imprimir</button>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- RESUMEN EJECUTIVO — 5 MÉTRICAS CLAVE                          -->
    <!-- ============================================================== -->
    <div class="rs-exec">
        <div class="rs-exec-card" style="border-left-color:var(--rs-blue)">
            <div class="num"><?php echo number_format($b1Total); ?></div>
            <div class="lbl" style="color:var(--rs-blue-dark)">Leads frescos</div>
            <div class="trend"><?php echo trendArrow($b1Total, $prevTotal); ?></div>
            <div class="sub">vs semana anterior (<?php echo $prevTotal; ?>)</div>
        </div>
        <div class="rs-exec-card" style="border-left-color:var(--rs-green)">
            <div class="num"><?php echo number_format($b1Sessions); ?></div>
            <div class="lbl" style="color:var(--rs-green-dark)">Sesiones agendadas</div>
            <div class="trend"><?php echo trendArrow($b1Sessions, $prevSessions); ?></div>
            <div class="sub">vs semana anterior (<?php echo $prevSessions; ?>)</div>
        </div>
        <div class="rs-exec-card" style="border-left-color:var(--rs-amber)">
            <div class="num"><?php echo $b1QualRate; ?>%</div>
            <div class="lbl" style="color:var(--rs-amber-dark)">Tasa calificación</div>
            <div class="trend"><?php echo trendArrow($b1QualRate, $prevQualRate); ?></div>
            <div class="sub">vs semana anterior (<?php echo $prevQualRate; ?>%)</div>
            <div class="formula"><?php echo $b1Sessions; ?> <span>sesiones</span> ÷ <?php echo $b1Total; ?> <span>leads</span> × 100</div>
        </div>
        <div class="rs-exec-card" style="border-left-color:#10B981">
            <div class="num"><?php echo $matureRate; ?>%</div>
            <div class="lbl" style="color:var(--rs-green-dark)">Close rate (<?php echo htmlspecialchars($cohortW3); ?>)</div>
            <div class="trend"><?php echo trendArrow($matureRate, $prevMatureRate); ?></div>
            <div class="sub">Cohorte maduro vs anterior (<?php echo $prevMatureRate; ?>%)</div>
            <div class="formula"><?php echo $matureCohort['closed']; ?> <span>cerrados</span> ÷ <?php echo $matureCohort['total']; ?> <span>leads</span> × 100</div>
        </div>
        <div class="rs-exec-card" style="border-left-color:var(--rs-purple)">
            <div class="num"><?php echo number_format($b3Total); ?></div>
            <div class="lbl" style="color:#5B21B6">Bodas esta semana</div>
            <div class="trend"><?php echo trendArrow($b3Total, $prevBlock3Count); ?></div>
            <div class="sub">vs semana anterior (<?php echo $prevBlock3Count; ?>)<?php if ($b3Revenue > 0) echo ' · $' . number_format($b3Revenue, 0); ?></div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- BLOQUE 1 — PIPELINE DE ESTA SEMANA                             -->
    <!-- ============================================================== -->
    <div class="rs-block">
        <div class="rs-block-header">
            <span class="rs-block-number rs-bn-blue">1</span>
            <span class="rs-block-title">Pipeline de esta semana</span>
            <span class="rs-block-dates">
                Leads del <?php echo fmtDateShort($thisWeekRange[0]); ?> al <?php echo fmtDateShort($thisWeekRange[1]); ?>
                &mdash; Solo leads cuya fecha de entrada cae en <?php echo htmlspecialchars($currentCohort); ?>.
                <strong>No incluye tasa de cierre</strong> — es imposible saberla todavía.
            </span>
        </div>
        <div class="rs-block-body">
            <!-- KPI Row -->
            <div class="rs-kpi-row">
                <div class="rs-kpi rs-kpi-blue">
                    <div class="rs-kpi-value"><?php echo number_format($b1Total); ?></div>
                    <div class="rs-kpi-label">Leads frescos generados</div>
                    <div class="rs-kpi-sub">esta semana</div>
                </div>
                <div class="rs-kpi rs-kpi-green">
                    <div class="rs-kpi-value"><?php echo number_format($b1Sessions); ?></div>
                    <div class="rs-kpi-label">Sesiones agendadas</div>
                    <div class="rs-kpi-sub">llamadas reservadas</div>
                </div>
                <div class="rs-kpi rs-kpi-amber">
                    <div class="rs-kpi-value"><?php echo $b1QualRate; ?>%</div>
                    <div class="rs-kpi-label">Tasa de calificación</div>
                    <div class="rs-kpi-sub">leads &rarr; sesión</div>
                    <div class="rs-kpi-formula"><?php echo $b1Sessions; ?> <span>sesiones</span> ÷ <?php echo $b1Total; ?> <span>leads</span> × 100</div>
                </div>
                <div class="rs-kpi rs-kpi-purple">
                    <div class="rs-kpi-value"><?php echo $b1NotScheduled; ?></div>
                    <div class="rs-kpi-label">Sin agendar</div>
                    <div class="rs-kpi-sub">en seguimiento activo</div>
                    <div class="rs-kpi-formula"><?php echo $b1Total; ?> <span>leads</span> − <?php echo $b1Sessions; ?> <span>sesiones</span></div>
                </div>
            </div>

            <!-- Distribución por categoría -->
            <div class="rs-cat-bars">
                <?php
                $catBarColors = ['Wedding Planner' => '#3B82F6', 'Community' => '#10B981', 'New Market' => '#F59E0B', 'Sin categoría' => '#94A3B8'];
                $catBarLabels = ['Wedding Planner' => 'WP', 'Community' => 'CO', 'New Market' => 'NM', 'Sin categoría' => '?'];
                foreach ($b1ByCategory as $catName => $catD):
                    if ($catD['leads'] === 0 && $catName === 'Sin categoría') continue;
                    $catQR = safeRate($catD['sessions'], $catD['leads']);
                ?>
                <div class="rs-cat-bar">
                    <div class="rs-cat-bar-icon" style="background:<?php echo $catBarColors[$catName] ?? '#94A3B8'; ?>"><?php echo $catBarLabels[$catName] ?? '?'; ?></div>
                    <div class="rs-cat-bar-info">
                        <div class="rs-cat-bar-name"><?php echo htmlspecialchars($catName); ?></div>
                        <div class="rs-cat-bar-nums"><?php echo $catD['leads']; ?> leads · <?php echo $catD['sessions']; ?> sesiones</div>
                        <div class="rs-cat-bar-rate">Calif.: <?php echo $catQR; ?>%</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Funnel Bar -->
            <?php
            $b1PctSessions = $b1Total > 0 ? round(($b1Sessions / $b1Total) * 100) : 0;
            $b1PctNot = 100 - $b1PctSessions;
            ?>
            <div class="rs-funnel">
                <?php if ($b1PctSessions > 0): ?>
                <div class="rs-funnel-seg rs-funnel-blue" style="width:<?php echo max($b1PctSessions, 8); ?>%">
                    <?php echo $b1Sessions; ?> sesiones (<?php echo $b1PctSessions; ?>%)
                </div>
                <?php endif; ?>
                <?php if ($b1PctNot > 0): ?>
                <div class="rs-funnel-seg rs-funnel-gray" style="width:<?php echo max($b1PctNot, 8); ?>%">
                    <?php echo $b1NotScheduled; ?> sin agendar (<?php echo $b1PctNot; ?>%)
                </div>
                <?php endif; ?>
            </div>

            <!-- Priority follow-up alert -->
            <?php if (!empty($b1PriorityFollowUp)): ?>
            <div class="rs-alert" style="background:#FEF2F2;border-color:#FECACA;color:#991B1B;margin-bottom:16px">
                <i class="fas fa-bell"></i>
                <span>
                    <strong><?php echo count($b1PriorityFollowUp); ?> lead(s) de Wedding Planner sin sesión agendada.</strong>
                    Estos leads tienen alta intención y deben contactarse personalmente dentro de 24 horas.
                    <?php foreach ($b1PriorityFollowUp as $pf): ?>
                        <br>&bull; <strong><?php echo htmlspecialchars($pf['nombre'] ?: 'Sin nombre'); ?></strong>
                        (<?php echo htmlspecialchars($pf['origin_channel']); ?>)
                    <?php endforeach; ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Channel Performance Table -->
            <div class="rs-section-title"><i class="fas fa-table-columns"></i> Desempeño por canal — ordenado por sesiones</div>
            <table id="block1Table" class="rs-table table table-hover">
                <thead>
                    <tr>
                        <th>Canal de origen</th>
                        <th>Categoría</th>
                        <th>Leads</th>
                        <th>Sesiones</th>
                        <th>Tasa calif.</th>
                        <th style="min-width:80px">Barra</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($b1ByChannel as $ch):
                        $chRate = $ch['leads'] > 0 ? round(($ch['sessions'] / $ch['leads']) * 100, 1) : 0;
                        $isPriority = ($ch['category'] === 'Wedding Planner' && $ch['sessions'] === 0 && $ch['leads'] > 0);
                    ?>
                    <tr class="<?php echo $isPriority ? 'rs-priority' : ''; ?>">
                        <td style="font-weight:500">
                            <?php echo htmlspecialchars($ch['channel']); ?>
                            <?php if ($isPriority): ?> <span class="rs-badge rs-badge-priority"><i class="fas fa-bell" style="font-size:9px"></i> Prioritario</span><?php endif; ?>
                        </td>
                        <td><?php echo catBadge($ch['category']); ?></td>
                        <td><?php echo $ch['leads']; ?></td>
                        <td style="font-weight:600"><?php echo $ch['sessions']; ?><?php if ($ch['sessions'] > 0) echo ' ★'; ?></td>
                        <td><?php echo $chRate; ?>%</td>
                        <td>
                            <div class="rs-progress">
                                <div class="rs-progress-bar" style="width:<?php echo min($chRate, 100); ?>%;background:<?php echo $chRate >= 20 ? 'var(--rs-green)' : ($chRate > 0 ? 'var(--rs-amber)' : 'var(--rs-red)'); ?>"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button class="rs-info-toggle" onclick="this.nextElementSibling.classList.toggle('active')">
                <i class="fas fa-lightbulb"></i> Cómo interpretar este bloque
            </button>
            <div class="rs-info-panel">
                <ul>
                    <li><strong>Ordenar por sesiones, no por leads.</strong> Un canal con muchos leads y 0 sesiones tiene bajo rendimiento frente a uno con pocos leads y varias sesiones. Volumen sin intención no es valioso.</li>
                    <li><strong>Campañas New Market con 0% en sus primeras semanas es normal.</strong> Las campañas Andromeda (Canadá, UK, Italia, USA) son de tope de funnel dirigidas a audiencias frías. No juzgarlas antes de 4–6 semanas de datos.</li>
                    <li><strong>Leads de planners con 0 sesiones = seguimiento prioritario.</strong> Un lead de un planner llega con confianza implícita. Contactarlos personalmente dentro de las 24 horas, sin esperar el flujo automatizado.</li>
                    <li><strong>La tasa de calificación es una señal, no un veredicto.</strong> Algunos leads de esta semana pueden agendar en la semana siguiente. El Bloque 2 contará la historia completa 3 semanas después.</li>
                    <li><strong>Si un canal pagado tiene 0 sesiones después de 4 semanas:</strong> detener la campaña o cambiar fundamentalmente el creativo y la segmentación.</li>
                    <li><strong>Si la tasa de calificación de un canal cae por debajo de 10% dos semanas seguidas:</strong> investigar si el problema es el seguimiento o si el creativo atrae al tipo equivocado de prospecto.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- BLOQUE 2 — COHORT CLOSE RATE                                   -->
    <!-- ============================================================== -->
    <div class="rs-block">
        <div class="rs-block-header">
            <span class="rs-block-number rs-bn-green">2</span>
            <span class="rs-block-title">Cohort Close Rate — La métrica real de conversión</span>
            <span class="rs-block-dates">
                Cohorte maduro: <strong><?php echo htmlspecialchars($cohortW3); ?></strong>
                (<?php echo fmtDateShort($cohortDateRanges[$cohortW3][0]); ?> – <?php echo fmtDateShort($cohortDateRanges[$cohortW3][1]); ?>)
                &mdash; La tasa de cierre confiable se mide 3 semanas después de la entrada del lead
            </span>
        </div>
        <div class="rs-block-body">

            <div class="rs-alert rs-alert-blue">
                <i class="fas fa-info-circle"></i>
                <span>
                    <strong>¿Por qué 3 semanas?</strong> El proceso de EFEGE toma entre 14 y 21 días: el lead llega, el qualifier lo revisa y agenda sesión, la sesión se realiza, el cliente decide y firma.
                    Medir la tasa de cierre antes de ese plazo da un número falso.
                </span>
            </div>

            <!-- KPI destacado del cohorte maduro -->
            <div class="rs-kpi-row">
                <div class="rs-kpi rs-kpi-green" style="position:relative">
                    <div class="rs-kpi-value" style="font-size:38px"><?php echo $matureRate; ?>%</div>
                    <div class="rs-kpi-label">Tasa de cierre real</div>
                    <div class="rs-kpi-sub">Cohorte <?php echo htmlspecialchars($cohortW3); ?> (maduro) <?php echo trendArrow($matureRate, $prevMatureRate); ?></div>
                    <div class="rs-kpi-formula"><?php echo $matureCohort['closed']; ?> <span>cerrados</span> ÷ <?php echo $matureCohort['total']; ?> <span>leads</span> × 100</div>
                </div>
                <div class="rs-kpi rs-kpi-blue">
                    <div class="rs-kpi-value"><?php echo $matureCohort['total']; ?></div>
                    <div class="rs-kpi-label">Leads en el cohorte</div>
                    <div class="rs-kpi-sub"><?php echo fmtDateShort($cohortDateRanges[$cohortW3][0]); ?> – <?php echo fmtDateShort($cohortDateRanges[$cohortW3][1]); ?></div>
                </div>
                <div class="rs-kpi rs-kpi-amber">
                    <div class="rs-kpi-value"><?php echo $matureCohort['sessions']; ?></div>
                    <div class="rs-kpi-label">Sesiones realizadas</div>
                    <div class="rs-kpi-sub"><?php echo safeRate($matureCohort['sessions'], $matureCohort['total']); ?>% del cohorte</div>
                    <div class="rs-kpi-formula"><?php echo $matureCohort['sessions']; ?> <span>sesiones</span> ÷ <?php echo $matureCohort['total']; ?> <span>leads</span> × 100</div>
                </div>
                <div class="rs-kpi rs-kpi-purple">
                    <div class="rs-kpi-value"><?php echo $matureCohort['closed']; ?></div>
                    <div class="rs-kpi-label">Clientes cerrados</div>
                    <div class="rs-kpi-sub"><?php if ($matureCohort['revenue'] > 0) echo '$' . number_format($matureCohort['revenue'], 0) . ' facturados'; else echo 'contratos firmados'; ?></div>
                </div>
            </div>

            <!-- Tabla comparativa de 4 semanas -->
            <div class="rs-section-title"><i class="fas fa-table"></i> Tabla comparativa de cohortes — últimas 4 semanas</div>
            <table class="rs-table table table-hover">
                <thead>
                    <tr>
                        <th>Cohorte</th>
                        <th>Periodo</th>
                        <th>Leads</th>
                        <th>Sesiones</th>
                        <th>Clientes</th>
                        <th>Tasa cierre</th>
                        <th>Estado de madurez</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $maturityInfo = [
                        $cohortW3      => ['label' => 'Maduro — usar este número', 'badge' => 'rs-badge-mature', 'show_rate' => true, 'icon' => '✓'],
                        $cohortW2      => ['label' => 'Ventana abierta — confirmar la próxima', 'badge' => 'rs-badge-window', 'show_rate' => true, 'icon' => '~'],
                        $cohortW1      => ['label' => 'Muy pronto — ventana aún no se abre', 'badge' => 'rs-badge-early', 'show_rate' => false, 'icon' => ''],
                        $currentCohort => ['label' => 'Leads frescos — medir en 3 semanas', 'badge' => 'rs-badge-fresh', 'show_rate' => false, 'icon' => ''],
                    ];
                    $displayOrder = [$cohortW3, $cohortW2, $cohortW1, $currentCohort];
                    foreach ($displayOrder as $cl):
                        $cd = $cohortData[$cl] ?? ['total' => 0, 'sessions' => 0, 'closed' => 0, 'range' => ['','']];
                        $mi = $maturityInfo[$cl];
                        $rate = safeRate($cd['closed'], $cd['total']);
                        $isMature = ($cl === $cohortW3);
                    ?>
                    <tr style="<?php echo $isMature ? 'background:#ECFDF5;font-weight:500' : ''; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($cl); ?></strong>
                            <?php if ($cl === $currentCohort) echo ' <span style="color:var(--rs-blue);font-size:10px">(ESTA SEMANA)</span>'; ?>
                            <?php if ($isMature) echo ' <span style="color:var(--rs-green);font-size:10px">★ PRINCIPAL</span>'; ?>
                        </td>
                        <td style="font-size:12px"><?php echo fmtDateShort($cd['range'][0]) . ' – ' . fmtDateShort($cd['range'][1]); ?></td>
                        <td><?php echo $cd['total']; ?></td>
                        <td><?php echo $cd['sessions']; ?></td>
                        <td><?php echo $mi['show_rate'] ? $cd['closed'] : '<span style="color:var(--rs-light)">—</span>'; ?></td>
                        <td>
                            <?php if ($mi['show_rate']): ?>
                                <strong style="<?php echo $isMature ? 'color:var(--rs-green-dark);font-size:16px' : 'color:var(--rs-amber-dark)'; ?>">
                                    <?php echo $rate; ?>%
                                </strong>
                                <?php if (!$isMature) echo '<span style="font-size:10px;color:var(--rs-light)"> provisional</span>'; ?>
                            <?php else: ?>
                                <span style="color:var(--rs-light)">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="rs-badge <?php echo $mi['badge']; ?>"><?php echo $mi['icon'] ? $mi['icon'] . ' ' : ''; ?><?php echo $mi['label']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Desglose por categoría del cohorte maduro -->
            <div class="rs-section-title"><i class="fas fa-pie-chart"></i> Tasa de cierre por categoría de origen — Cohorte <?php echo htmlspecialchars($cohortW3); ?></div>
            <div class="rs-cat-row">
                <?php
                $catDisplay = [
                    'Wedding Planner' => ['color' => '#1D4ED8', 'bg' => '#DBEAFE', 'expected' => '15–25%+', 'css' => 'rs-cat-card-wp', 'desc' => 'Referido directo de un planner que confía en EFEGE'],
                    'Community'       => ['color' => '#065F46', 'bg' => '#D1FAE5', 'expected' => '8–15%', 'css' => 'rs-cat-card-comm', 'desc' => 'Descubrió EFEGE por cuenta propia (web, IG orgánico, retargeting)'],
                    'New Market'      => ['color' => '#92400E', 'bg' => '#FEF3C7', 'expected' => '3–8%', 'css' => 'rs-cat-card-nm', 'desc' => 'Campañas pagadas a audiencias sin conocimiento previo de la marca'],
                ];
                foreach ($catDisplay as $catName => $catStyle):
                    $catData = $matureCohort['by_category'][$catName] ?? ['total' => 0, 'sessions' => 0, 'closed' => 0];
                    $catRate = safeRate($catData['closed'], $catData['total']);
                    $inRange = false;
                    if ($catName === 'Wedding Planner') $inRange = $catRate >= 15;
                    elseif ($catName === 'Community') $inRange = $catRate >= 8 && $catRate <= 15;
                    elseif ($catName === 'New Market') $inRange = $catRate >= 3 && $catRate <= 8;
                ?>
                <div class="rs-cat-card <?php echo $catStyle['css']; ?>">
                    <div class="rs-cat-name" style="color:<?php echo $catStyle['color']; ?>">
                        <span class="rs-cat-indicator" style="background:<?php echo $catStyle['color']; ?>"></span>
                        <?php echo $catName; ?>
                    </div>
                    <div class="rs-cat-rate">
                        <?php echo $catRate; ?>%
                        <?php if ($catData['total'] > 0): ?>
                            <?php if ($inRange): ?><span style="font-size:14px;color:var(--rs-green)">✓</span>
                            <?php else: ?><span style="font-size:14px;color:var(--rs-amber)">⚠</span><?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="rs-cat-detail">
                        <?php echo $catData['closed']; ?> clientes de <?php echo $catData['total']; ?> leads
                        (<?php echo $catData['sessions']; ?> sesiones)
                    </div>
                    <div class="rs-cat-expected">
                        <strong>Rango esperado:</strong> <?php echo $catStyle['expected']; ?><br>
                        <span style="color:#94A3B8"><?php echo $catStyle['desc']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Charts: donut + trend -->
            <div class="rs-charts-grid">
                <div class="rs-chart-card">
                    <div class="rs-chart-title">Distribución del cohorte <?php echo htmlspecialchars($cohortW3); ?> por categoría</div>
                    <div class="rs-chart-wrap"><canvas id="cohortCategoryChart"></canvas></div>
                </div>
                <div class="rs-chart-card">
                    <div class="rs-chart-title">Tendencia de tasa de cierre — últimas 8 semanas maduras</div>
                    <div class="rs-chart-wrap"><canvas id="trendChart"></canvas></div>
                </div>
            </div>

            <button class="rs-info-toggle" onclick="this.nextElementSibling.classList.toggle('active')">
                <i class="fas fa-lightbulb"></i> Cómo interpretar este bloque
            </button>
            <div class="rs-info-panel">
                <ul>
                    <li><strong>Si la tasa del cohorte maduro supera el 10%:</strong> la calidad de leads esa semana fue buena. Revisar qué canales generaron más leads y considerar aumentar inversión.</li>
                    <li><strong>Si la tasa cae por debajo del 5%:</strong> algo falló — calidad de leads, velocidad de seguimiento o la sesión de ventas. Investigar qué canales dominaron ese cohorte.</li>
                    <li><strong>Si New Market mejora semana tras semana:</strong> las campañas Andromeda están madurando. Considerar activar retargeting de mitad de funnel para esos mercados.</li>
                    <li><strong>Si Wedding Planner supera consistentemente a Community y New Market:</strong> hay evidencia para invertir más en alianzas estratégicas con planners. Más relaciones con planners = más leads de alta intención a costo casi cero.</li>
                    <li><strong>La fórmula correcta es:</strong> Tasa de cierre = Clientes cerrados del Cohorte X ÷ Total de leads del Cohorte X. Nunca Clientes de esta semana ÷ Leads de esta semana.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- BLOQUE 3 — CIERRES DE ESTA SEMANA                              -->
    <!-- ============================================================== -->
    <div class="rs-block">
        <div class="rs-block-header">
            <span class="rs-block-number rs-bn-amber">3</span>
            <span class="rs-block-title">Cierres de esta semana — Momentum de ingresos</span>
            <span class="rs-block-dates">
                Contratos firmados del <?php echo fmtDateShort($thisWeekRange[0]); ?> al <?php echo fmtDateShort($thisWeekRange[1]); ?>
                (estos clientes entraron al funnel aprox. en <?php echo htmlspecialchars($b3EstimatedEntry); ?>)
            </span>
        </div>
        <div class="rs-block-body">

            <div class="rs-alert">
                <i class="fas fa-triangle-exclamation"></i>
                <span>
                    <strong>Importante:</strong> Los clientes que cerraron esta semana <strong>NO</strong> son leads de esta semana.
                    Entraron al funnel semanas atrás (entrada estimada: <strong><?php echo htmlspecialchars($b3EstimatedEntry); ?></strong>).
                    El ciclo de ventas de EFEGE toma entre 14 y 21 días. Sin esta aclaración, quien lea el reporte podría pensar que estos cierres vienen de los leads de esta semana — ese es el error que este sistema busca eliminar.
                </span>
            </div>

            <div class="rs-kpi-row">
                <div class="rs-kpi rs-kpi-amber">
                    <div class="rs-kpi-value"><?php echo number_format($b3Total); ?></div>
                    <div class="rs-kpi-label">Bodas firmadas</div>
                    <div class="rs-kpi-sub">esta semana <?php echo trendArrow($b3Total, $prevBlock3Count); ?></div>
                </div>
                <?php if ($b3Revenue > 0): ?>
                <div class="rs-kpi rs-kpi-green">
                    <div class="rs-kpi-value">$<?php echo number_format($b3Revenue, 0); ?></div>
                    <div class="rs-kpi-label">Revenue confirmado</div>
                    <div class="rs-kpi-sub">monto total de ventas</div>
                </div>
                <?php endif; ?>
                <div class="rs-kpi rs-kpi-blue">
                    <div class="rs-kpi-value"><?php echo $b3PctWP; ?>%</div>
                    <div class="rs-kpi-label">Wedding Planner</div>
                    <div class="rs-kpi-sub"><?php echo $b3ByCategory['Wedding Planner']; ?> cierres</div>
                    <div class="rs-kpi-formula"><?php echo $b3ByCategory['Wedding Planner']; ?> <span>WP</span> ÷ <?php echo $b3Total; ?> <span>cierres</span> × 100</div>
                </div>
                <div class="rs-kpi" style="background:#F5F3FF">
                    <div class="rs-kpi-value"><?php echo $b3PctCommunity; ?>%</div>
                    <div class="rs-kpi-label" style="color:#065F46">Community</div>
                    <div class="rs-kpi-sub"><?php echo $b3ByCategory['Community']; ?> cierres</div>
                    <div class="rs-kpi-formula"><?php echo $b3ByCategory['Community']; ?> <span>comm</span> ÷ <?php echo $b3Total; ?> <span>cierres</span> × 100</div>
                </div>
                <div class="rs-kpi" style="background:#FFF7ED">
                    <div class="rs-kpi-value"><?php echo $b3PctNewMarket; ?>%</div>
                    <div class="rs-kpi-label" style="color:var(--rs-amber-dark)">New Market</div>
                    <div class="rs-kpi-sub"><?php echo $b3ByCategory['New Market']; ?> cierres</div>
                    <div class="rs-kpi-formula"><?php echo $b3ByCategory['New Market']; ?> <span>NM</span> ÷ <?php echo $b3Total; ?> <span>cierres</span> × 100</div>
                </div>
            </div>

            <?php if ($b3Total > 0): ?>

            <!-- Closings by Channel Table -->
            <div class="rs-section-title"><i class="fas fa-handshake"></i> Cierres por canal de origen</div>
            <table id="block3ChannelTable" class="rs-table table table-hover">
                <thead>
                    <tr>
                        <th>Canal de origen</th>
                        <th>Categoría</th>
                        <th>Clientes cerrados</th>
                        <th>Semana de entrada</th>
                        <?php if ($b3Revenue > 0): ?><th>Revenue</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($b3ByChannel as $ch):
                        $cohortLblDisplay = '';
                        if (!empty($ch['cohort_labels'])) {
                            $freq = array_count_values($ch['cohort_labels']);
                            arsort($freq);
                            $cohortLblDisplay = implode(', ', array_keys($freq));
                        }
                    ?>
                    <tr>
                        <td style="font-weight:500"><?php echo htmlspecialchars($ch['channel']); ?></td>
                        <td><?php echo catBadge($ch['category']); ?></td>
                        <td style="font-weight:600;font-size:15px"><?php echo $ch['closed']; ?></td>
                        <td><span class="rs-badge rs-badge-fresh"><?php echo htmlspecialchars($cohortLblDisplay ?: '—'); ?></span></td>
                        <?php if ($b3Revenue > 0): ?><td style="font-weight:500;color:var(--rs-green-dark)">$<?php echo number_format($ch['revenue'], 0); ?></td><?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Detalle individual de clientes cerrados -->
            <div class="rs-section-title"><i class="fas fa-users"></i> Detalle de clientes cerrados esta semana</div>
            <table id="block3DetailTable" class="rs-table rs-detail-table table table-hover">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Canal</th>
                        <th>Categoría</th>
                        <th>Fecha entrada</th>
                        <th>Cohorte entrada</th>
                        <th>Fecha cierre</th>
                        <th>Días en funnel</th>
                        <?php if ($b3Revenue > 0): ?><th>Monto</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($b3ClientDetails as $cd):
                        $daysInFunnel = '';
                        if (!empty($cd['entry_date']) && !empty($cd['close_date'])) {
                            $tsE = strtotime($cd['entry_date']);
                            $tsC = strtotime($cd['close_date']);
                            if ($tsE && $tsC) $daysInFunnel = max(0, round(($tsC - $tsE) / 86400));
                        }
                    ?>
                    <tr>
                        <td class="rs-name"><?php echo htmlspecialchars($cd['nombre'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($cd['channel']); ?></td>
                        <td><?php echo catBadge($cd['category']); ?></td>
                        <td><?php echo fmtDateShort($cd['entry_date']); ?></td>
                        <td><span class="rs-badge rs-badge-fresh"><?php echo htmlspecialchars($cd['cohort'] ?: '—'); ?></span></td>
                        <td><?php echo fmtDateShort($cd['close_date']); ?></td>
                        <td><?php echo $daysInFunnel !== '' ? $daysInFunnel . ' días' : '—'; ?></td>
                        <?php if ($b3Revenue > 0): ?><td class="rs-monto"><?php echo $cd['monto'] > 0 ? '$' . number_format($cd['monto'], 0) : '—'; ?></td><?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php else: ?>
            <div style="text-align:center;padding:48px 0;color:var(--rs-light)">
                <i class="fas fa-inbox" style="font-size:36px;margin-bottom:14px;display:block"></i>
                <div style="font-size:14px;font-weight:500">No hay cierres registrados esta semana</div>
                <div style="font-size:12px;margin-top:4px"><?php echo fmtDateShort($thisWeekRange[0]); ?> – <?php echo fmtDateShort($thisWeekRange[1]); ?></div>
            </div>
            <?php endif; ?>

            <button class="rs-info-toggle" onclick="this.nextElementSibling.classList.toggle('active')">
                <i class="fas fa-lightbulb"></i> Cómo interpretar este bloque
            </button>
            <div class="rs-info-panel">
                <ul>
                    <li><strong>Rastrear qué canales aparecen consistentemente en cierres durante 8–12 semanas.</strong> Esos son los canales que realmente generan dinero — protegerlos en decisiones de presupuesto.</li>
                    <li><strong>Cuando un canal nuevo produce su primer cierre, celebrarlo.</strong> Significa que el funnel completo funcionó — desde audiencia fría hasta contrato firmado.</li>
                    <li><strong>Usar el Bloque 3 para proyectar ingresos.</strong> Si el paquete promedio de boda es un monto X y esta semana se cerraron N bodas, calcular el revenue confirmado de la semana.</li>
                    <li><strong>Los "Días en funnel"</strong> muestran cuánto tarda un lead en convertirse. El promedio debería estar entre 14 y 21 días. Si es mucho mayor, revisar la velocidad de seguimiento.</li>
                    <li><strong>Siempre recordar:</strong> los clientes de este bloque NO vienen de los leads de esta semana. Vienen de cohortes anteriores. Por eso se muestra su semana de entrada.</li>
                </ul>
            </div>
        </div>
    </div>

</div><!-- /rs-wrapper -->

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
$(document).ready(function() {

    // ---- FILTROS CASCADA (Año → Mes → Semana) ----
    var filterData = <?php echo json_encode($filterDataJS); ?>;
    var monthNames = <?php echo json_encode($monthNames); ?>;

    var $year = $('#filterYear'), $month = $('#filterMonth'), $week = $('#filterWeek');

    function getMonthsForYear(yr) {
        var seen = {};
        filterData.forEach(function(d) { if (d.year == yr) seen[d.month] = true; });
        var arr = Object.keys(seen).map(Number);
        arr.sort(function(a,b){ return b - a; });
        return arr;
    }

    function getWeeksForYearMonth(yr, mo) {
        var arr = [];
        filterData.forEach(function(d) {
            if (d.year == yr && d.month == mo) arr.push(d);
        });
        arr.sort(function(a,b){ return b.week < a.week ? -1 : 1; });
        return arr;
    }

    $year.on('change', function() {
        var yr = parseInt(this.value);
        var months = getMonthsForYear(yr);
        $month.empty();
        months.forEach(function(mo) {
            $month.append('<option value="'+mo+'">' + (monthNames[mo]||mo) + '</option>');
        });
        $month.trigger('change');
    });

    $month.on('change', function() {
        var yr = parseInt($year.val());
        var mo = parseInt(this.value);
        var weeks = getWeeksForYearMonth(yr, mo);
        $week.empty();
        weeks.forEach(function(w) {
            $week.append('<option value="'+w.week+'">' + w.label + '</option>');
        });
    });

    $week.on('change', function() {
        if (this.value) window.location = '?week=' + encodeURIComponent(this.value);
    });

    var dtOpts = {
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        ordering: true, paging: false, searching: false,
        dom: 'Bfrtip', buttons: ['copy', 'csv', 'excel'], scrollX: true
    };

    if ($('#block1Table tbody tr').length > 0) {
        $('#block1Table').DataTable(Object.assign({}, dtOpts, { order: [[3, 'desc']] }));
    }
    if ($('#block3ChannelTable').length && $('#block3ChannelTable tbody tr').length > 0) {
        $('#block3ChannelTable').DataTable(Object.assign({}, dtOpts, { order: [[2, 'desc']] }));
    }
    if ($('#block3DetailTable').length && $('#block3DetailTable tbody tr').length > 0) {
        $('#block3DetailTable').DataTable(Object.assign({}, dtOpts, { paging: true, pageLength: 10, searching: true }));
    }

    // ---- CHARTS ----
    var fontFamily = "'DM Sans', sans-serif";

    // 1. Donut chart: Category distribution
    var ctxDonut = document.getElementById('cohortCategoryChart');
    if (ctxDonut) {
        var donutData = <?php
            $cd = [];
            foreach (['Wedding Planner', 'Community', 'New Market'] as $cn) {
                $d = $matureCohort['by_category'][$cn] ?? ['total' => 0];
                $cd[] = $d['total'];
            }
            echo json_encode($cd);
        ?>;
        if (donutData.some(v => v > 0)) {
            new Chart(ctxDonut, {
                type: 'doughnut',
                data: {
                    labels: ['Wedding Planner', 'Community', 'New Market'],
                    datasets: [{ data: donutData, backgroundColor: ['#3B82F6', '#10B981', '#F59E0B'], borderWidth: 3, borderColor: '#fff' }]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: {
                    legend: { position: 'bottom', labels: { font: { family: fontFamily, size: 11 }, padding: 14 } }
                }}
            });
        } else {
            ctxDonut.parentElement.innerHTML += '<p style="text-align:center;color:#94A3B8;font-size:12px;padding:20px 0">Sin datos para graficar</p>';
        }
    }

    // 2. Trend chart: Close rate over 8 weeks
    var ctxTrend = document.getElementById('trendChart');
    if (ctxTrend) {
        var trendLabels = <?php echo json_encode($trendLabels); ?>;
        var trendRates = <?php echo json_encode($trendRates); ?>;
        var trendLeads = <?php echo json_encode($trendLeads); ?>;
        var trendClosed = <?php echo json_encode($trendClosed); ?>;

        if (trendLeads.some(v => v > 0)) {
            new Chart(ctxTrend, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [
                        {
                            label: 'Tasa de cierre %',
                            data: trendRates,
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16,185,129,.1)',
                            fill: true,
                            tension: .3,
                            borderWidth: 2.5,
                            pointRadius: 4,
                            pointBackgroundColor: '#10B981',
                            yAxisID: 'y'
                        },
                        {
                            label: 'Leads',
                            data: trendLeads,
                            borderColor: '#94A3B8',
                            backgroundColor: 'transparent',
                            borderWidth: 1.5,
                            borderDash: [4, 4],
                            pointRadius: 3,
                            pointBackgroundColor: '#94A3B8',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'bottom', labels: { font: { family: fontFamily, size: 11 }, padding: 14 } } },
                    scales: {
                        y: { type: 'linear', position: 'left', title: { display: true, text: 'Tasa cierre %', font: { family: fontFamily, size: 11 } }, beginAtZero: true, ticks: { font: { family: fontFamily, size: 10 } } },
                        y1: { type: 'linear', position: 'right', title: { display: true, text: 'Leads', font: { family: fontFamily, size: 11 } }, beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { font: { family: fontFamily, size: 10 } } },
                        x: { ticks: { font: { family: fontFamily, size: 10 }, maxRotation: 45 } }
                    }
                }
            });
        } else {
            ctxTrend.parentElement.innerHTML += '<p style="text-align:center;color:#94A3B8;font-size:12px;padding:20px 0">Sin datos históricos suficientes</p>';
        }
    }
});
</script>
</body>
</html>
