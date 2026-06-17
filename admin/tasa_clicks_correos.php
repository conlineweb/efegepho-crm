<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';

function formatCreatedTime($dateString)
{
    if (empty($dateString))
        return '';
    $timestamp = strtotime($dateString);
    if ($timestamp === false)
        return $dateString;
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $month = $months[date('n', $timestamp) - 1];
    $day = date('j', $timestamp);
    $year = date('Y', $timestamp);
    $hour = date('g', $timestamp);
    $min = date('i', $timestamp);
    $ampm = date('A', $timestamp) == 'AM' ? 'a.m.' : 'p.m.';
    return "$day de $month de $year a las $hour:$min $ampm";
}

$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
// Correo filter (1 or 2), empty = all
$correo = isset($_GET['correo']) ? trim($_GET['correo']) : '';

$whereClause = "1=1";
if ($startDate !== '') {
    $sd = date('Y-m-d', strtotime($startDate)) . ' 00:00:00';
    $whereClause .= " AND clicked_at >= '" . $conn->real_escape_string($sd) . "'";
}
if ($endDate !== '') {
    $ed = date('Y-m-d', strtotime($endDate)) . ' 23:59:59';
    $whereClause .= " AND clicked_at <= '" . $conn->real_escape_string($ed) . "'";
}
// apply correo filter if provided
if ($correo !== '' && in_array($correo, ['1', '2'], true)) {
    $whereClause .= " AND correo = " . intval($correo);
}

$res = $conn->query("SELECT * FROM email_clicks WHERE " . $whereClause . " ORDER BY clicked_at DESC");
$rows = [];
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        $leadInfo = ['full_name' => null, 'email' => null, 'phone' => null];
        $tabla_origen = $r['tabla_origen'];
        if (!empty($tabla_origen)) {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla_origen);
            $ck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($safeTable) . "'");
            if ($ck && $ck->num_rows > 0) {
                $q = $conn->query("SELECT full_name, email, phone, correo_uno_enviado, correo_dos_enviado, fecha_envio_correo_uno, fecha_envio_correo_dos FROM `$safeTable` WHERE id = " . intval($r['lead_id']) . " LIMIT 1");
                if ($q && $q->num_rows > 0) {
                    $leadInfo = $q->fetch_assoc();
                }
            }
        }
        // If the lead row exists, ensure the click corresponds to an actual sent email with a date
        if (!empty($leadInfo) && isset($r['correo'])) {
            $correoTipo = intval($r['correo']);
            $isValidSent = true;
            if ($correoTipo === 1) {
                if (!isset($leadInfo['correo_uno_enviado']) || intval($leadInfo['correo_uno_enviado']) !== 1)
                    $isValidSent = false;
                if (empty($leadInfo['fecha_envio_correo_uno']) || $leadInfo['fecha_envio_correo_uno'] === '0000-00-00 00:00:00')
                    $isValidSent = false;
            } elseif ($correoTipo === 2) {
                if (!isset($leadInfo['correo_dos_enviado']) || intval($leadInfo['correo_dos_enviado']) !== 1)
                    $isValidSent = false;
                if (empty($leadInfo['fecha_envio_correo_dos']) || $leadInfo['fecha_envio_correo_dos'] === '0000-00-00 00:00:00')
                    $isValidSent = false;
            }
            if (!$isValidSent) {
                // Skip adding this click record as it does not map to a properly-sent email (no fecha)
                continue;
            }
        }
        $r['full_name'] = $leadInfo['full_name'];
        $r['email'] = $leadInfo['email'];
        $r['phone'] = $leadInfo['phone'];
        $rows[] = $r;
    }
}

// Note: we no longer build $leadIdsByTable (not used) — leave implementation to queries that require it

$today = date('Y-m-d');
$todayCount = 0;
$correo1TodayCount = 0;
$correo2TodayCount = 0;
$totalCount = count($rows);
$correo1Count = 0;
$correo2Count = 0;
foreach ($rows as $r) {
    $ts = strtotime($r['clicked_at']);
    if ($ts !== false && date('Y-m-d', $ts) === $today)
        $todayCount++;
    if (intval($r['correo']) === 1)
        $correo1Count++;
    if (intval($r['correo']) === 2)
        $correo2Count++;
}

foreach ($rows as $r) {
    $ts = strtotime($r['clicked_at']);
    if ($ts === false)
        continue;
    if (date('Y-m-d', $ts) !== $today)
        continue;
    if (intval($r['correo']) === 1)
        $correo1TodayCount++;
    if (intval($r['correo']) === 2)
        $correo2TodayCount++;
}

// 60-day series for Correo 1 and Correo 2
$countsMap1 = [];
$countsMap2 = [];
for ($i = 0; $i < 60; $i++) {
    $d = date('Y-m-d', strtotime("-" . (59 - $i) . " days"));
    $countsMap1[$d] = 0;
    $countsMap2[$d] = 0;
}
foreach ($rows as $r) {
    $ts = strtotime($r['clicked_at']);
    if ($ts === false)
        continue;
    $d = date('Y-m-d', $ts);
    $correo = intval($r['correo']);
    if ($correo === 1 && isset($countsMap1[$d]))
        $countsMap1[$d]++;
    if ($correo === 2 && isset($countsMap2[$d]))
        $countsMap2[$d]++;
}
$series1 = [];
$series2 = [];
foreach ($countsMap1 as $d => $c) {
    $series1[] = [strtotime($d) * 1000, $c];
}
foreach ($countsMap2 as $d => $c) {
    $series2[] = [strtotime($d) * 1000, $c];
}
$seriesJson1 = json_encode($series1);
$seriesJson2 = json_encode($series2);

// ----- Calculate sent email counts across origin tables -----
$sentCorreo1 = 0;
$sentCorreo2 = 0;
$sentTotal = 0;

// Build date filters for sent counts
$sentDateWhere1 = '';
$sentDateWhere2 = '';
if ($startDate !== '') {
    $sdSent = date('Y-m-d', strtotime($startDate)) . ' 00:00:00';
    $sentDateWhere1 .= " AND fecha_envio_correo_uno >= '" . $conn->real_escape_string($sdSent) . "'";
    $sentDateWhere2 .= " AND fecha_envio_correo_dos >= '" . $conn->real_escape_string($sdSent) . "'";
}
if ($endDate !== '') {
    $edSent = date('Y-m-d', strtotime($endDate)) . ' 23:59:59';
    $sentDateWhere1 .= " AND fecha_envio_correo_uno <= '" . $conn->real_escape_string($edSent) . "'";
    $sentDateWhere2 .= " AND fecha_envio_correo_dos <= '" . $conn->real_escape_string($edSent) . "'";
}

// Apply correo filter for sent counts as well
$filterCorreo1 = ($correo === '1');
$filterCorreo2 = ($correo === '2');

// find tables containing correo_uno_enviado / correo_dos_enviado
$tables = [];
$tablesRes = $conn->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME IN ('correo_uno_enviado', 'correo_dos_enviado')");
if ($tablesRes) {
    while ($t = $tablesRes->fetch_assoc()) {
        $tables[$t['TABLE_NAME']] = true;
    }
}

foreach (array_keys($tables) as $tableName) {
    $safeTable = $conn->real_escape_string($tableName);
    // check if table has correo_uno_enviado column before querying
    $hasUno = false;
    $hasDos = false;
    $colRes = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $safeTable . "' AND COLUMN_NAME IN ('correo_uno_enviado','correo_dos_enviado')");
    if ($colRes) {
        while ($c = $colRes->fetch_assoc()) {
            if ($c['COLUMN_NAME'] === 'correo_uno_enviado')
                $hasUno = true;
            if ($c['COLUMN_NAME'] === 'correo_dos_enviado')
                $hasDos = true;
        }
    }
    // correo uno
    if ($hasUno && !$filterCorreo2) { // if column exists and we're not restricting to correo 2 only
        // Count all rows where correo_uno was sent (flag set and send date present).
        // This should include leads that never clicked (no email_clicks record).
        $q1 = $conn->query("SELECT COUNT(*) as cnt FROM `" . $safeTable . "` WHERE correo_uno_enviado = 1 AND fecha_envio_correo_uno IS NOT NULL AND fecha_envio_correo_uno != '0000-00-00 00:00:00'" . $sentDateWhere1);
        if ($q1) {
            $r1 = $q1->fetch_assoc();
            $sentCorreo1 += intval($r1['cnt']);
        }
    }
    // correo dos
    if ($hasDos && !$filterCorreo1) { // if column exists and we're not restricting to correo 1 only
        // Count all rows where correo_dos was sent (flag set and send date present).
        $q2 = $conn->query("SELECT COUNT(*) as cnt FROM `" . $safeTable . "` WHERE correo_dos_enviado = 1 AND fecha_envio_correo_dos IS NOT NULL AND fecha_envio_correo_dos != '0000-00-00 00:00:00'" . $sentDateWhere2);
        if ($q2) {
            $r2 = $q2->fetch_assoc();
            $sentCorreo2 += intval($r2['cnt']);
        }
    }
}
$sentTotal = $sentCorreo1 + $sentCorreo2;

// --- Gather send date debug information for all tables (for debugging) ---
$dateFieldsToCheck = ['fecha_envio_correo_uno', 'fecha_envio_correo_dos'];
$dateDebug = [];
foreach (array_keys($tables) as $tableName) {
    $safeTable = $conn->real_escape_string($tableName);
    foreach ($dateFieldsToCheck as $field) {
        $q = $conn->query("SELECT MIN($field) as min_date, MAX($field) as max_date FROM `" . $safeTable . "` WHERE $field IS NOT NULL AND $field != '0000-00-00 00:00:00'");
        if ($q && $q->num_rows > 0) {
            $r = $q->fetch_assoc();
            $dateDebug[] = [
                'table' => $tableName,
                'field' => $field,
                'min_date' => $r['min_date'],
                'max_date' => $r['max_date']
            ];
            // min/max for this table/field returned in $r['min_date']/$r['max_date'] — appended to dateDebug above
        }
    }
}

// Note: min/max based on sent dates were removed in favor of clicks-based min/max ($minClickDateAttr/$maxClickDateAttr)

// Unique clicks per lead+correo (to avoid counting multiple clicks from same lead)
$uniqueClickKeys = [];
$uniqueClicksTotal = 0;
$uniqueClicksCorreo1 = 0;
$uniqueClicksCorreo2 = 0;
foreach ($rows as $r) {
    $key = $r['tabla_origen'] . '|' . $r['lead_id'] . '|' . $r['correo'];
    if (isset($uniqueClickKeys[$key]))
        continue;
    $uniqueClickKeys[$key] = true;
    $uniqueClicksTotal++;
    if (intval($r['correo']) === 1)
        $uniqueClicksCorreo1++;
    if (intval($r['correo']) === 2)
        $uniqueClicksCorreo2++;
}

// Calculate click-through percentages
$tasaClicksPct = ($sentTotal > 0) ? round(($uniqueClicksTotal / $sentTotal) * 100, 2) : 0;
$tasaClicksCorreo1Pct = ($sentCorreo1 > 0) ? round(($uniqueClicksCorreo1 / $sentCorreo1) * 100, 2) : 0;
$tasaClicksCorreo2Pct = ($sentCorreo2 > 0) ? round(($uniqueClicksCorreo2 / $sentCorreo2) * 100, 2) : 0;

// Find the oldest and newest clicked_at records from the $rows array
$oldestClick = null;
$newestClick = null;
foreach ($rows as $r) {
    $ts = strtotime($r['clicked_at']);
    if ($ts === false) continue;
    if ($oldestClick === null || $ts < strtotime($oldestClick['clicked_at'])) {
        $oldestClick = $r;
    }
    if ($newestClick === null || $ts > strtotime($newestClick['clicked_at'])) {
        $newestClick = $r;
    }
}

function _click_summary_for_log($record)
{
    if (empty($record)) return null;
    return [
        'id' => isset($record['id']) ? $record['id'] : null,
        'tabla_origen' => isset($record['tabla_origen']) ? $record['tabla_origen'] : null,
        'lead_id' => isset($record['lead_id']) ? $record['lead_id'] : null,
        'full_name' => isset($record['full_name']) ? $record['full_name'] : null,
        'email' => isset($record['email']) ? $record['email'] : null,
        'phone' => isset($record['phone']) ? $record['phone'] : null,
        'correo' => isset($record['correo']) ? intval($record['correo']) : null,
        'url' => isset($record['url']) ? $record['url'] : null,
        'clicked_at' => isset($record['clicked_at']) ? $record['clicked_at'] : null,
        'abierto_a_las' => isset($record['clicked_at']) ? formatCreatedTime($record['clicked_at']) : null,
    ];
}
$oldestClickLog = _click_summary_for_log($oldestClick);
$newestClickLog = _click_summary_for_log($newestClick);

error_log('Oldest click: ' . json_encode($oldestClickLog));
error_log('Newest click: ' . json_encode($newestClickLog));

// Build min/max attributes for the date inputs from clicks (oldest/newest)
$minClickDateAttr = '';
$maxClickDateAttr = '';
if (!empty($oldestClick) && isset($oldestClick['clicked_at']) && !empty($oldestClick['clicked_at'])) {
    $minClickDateAttr = date('Y-m-d', strtotime($oldestClick['clicked_at']));
}
if (!empty($newestClick) && isset($newestClick['clicked_at']) && !empty($newestClick['clicked_at'])) {
    $maxClickDateAttr = date('Y-m-d', strtotime($newestClick['clicked_at']));
}
if (!empty($minClickDateAttr) && !empty($maxClickDateAttr) && $minClickDateAttr > $maxClickDateAttr) {
    $tmp = $minClickDateAttr;
    $minClickDateAttr = $maxClickDateAttr;
    $maxClickDateAttr = $tmp;
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tasa de clicks correos</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <style>
        .stat-card {
            border-radius: .6rem;
            min-height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        @media (max-width: 767px) {
            .stat-card {
                min-height: 90px;
            }
        }
    </style>
</head>

<body>
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1">
                    <h3>Tasa de clicks correos</h3>
                </div>
                <div class="col-12 col-md-6 order-md-2 text-end">
                    <small>Shows leads that clicked email links (email clicks)</small>
                </div>
            </div>
        </div>
<div class="row">
    <div class="col-lg-6 ">
        <form method="get" class="row g-2 align-items-end justify-content-center">
            <div class="col-6 col-md-4">
                        <label class="form-label small">Desde</label>
                        <input type="date" name="start_date" class="form-control"
                            value="<?php echo htmlspecialchars($startDate); ?>"
                            <?php if (!empty($minClickDateAttr)) echo 'min="' . htmlspecialchars($minClickDateAttr) . '"'; ?>
                            <?php if (!empty($maxClickDateAttr)) echo 'max="' . htmlspecialchars($maxClickDateAttr) . '"'; ?>>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small">Hasta</label>
                        <input type="date" name="end_date" class="form-control"
                            value="<?php echo htmlspecialchars($endDate); ?>"
                            <?php if (!empty($minClickDateAttr)) echo 'min="' . htmlspecialchars($minClickDateAttr) . '"'; ?>
                            <?php if (!empty($maxClickDateAttr)) echo 'max="' . htmlspecialchars($maxClickDateAttr) . '"'; ?>>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small">Correo</label>
                        <select class="form-select" name="correo">
                            <option value="" <?php echo ($correo === '') ? 'selected' : ''; ?>>Todos</option>
                            <option value="1" <?php echo ($correo === '1') ? 'selected' : ''; ?>>Correo 1</option>
                            <option value="2" <?php echo ($correo === '2') ? 'selected' : ''; ?>>Correo 2</option>
                        </select>
                    </div>
            <div class="col-6 col-md-2">
                <label class="form-label small d-none d-md-block">&nbsp;</label>
                <div class="d-flex gap-1">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <button type="button" id="btnResetClicks" class="btn btn-secondary">Resetar</button>
                </div>
            </div>
        </form>
    </div>
</div>
        <div class="row mb-3">
            <div class="col-lg-6 ">
              

                <div class="row  mt-3">
                    <div class="col-sm-4">
                        <div class="card stat-card bg-info text-white h-100">
                            <div class=" p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="small opacity-90 mb-1">Tasa de clicks</div>
                                        <div class="display-5 fw-bold"><?php echo $tasaClicksPct; ?>%</div>
                                    </div>
                                    <div class="text-end">🔗</div>
                                </div>

                                <div class="row text-white opacity-95 small">
                                    <div class="col-6">
                                        <strong>Enviados</strong>
                                    </div>
                                    <div class="col-6 text-end">
                                        <?php echo intval($sentTotal); ?>
                                    </div>
                                </div>
                                <div class="row text-white opacity-95 small">
                                    <div class="col-6">
                                        <strong>Clicks </strong>
                                    </div>
                                    <div class="col-6 text-end">
                                        <?php echo intval($uniqueClicksTotal); ?>
                                    </div>
                                </div>
                                <div class="row mt-2 small">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between">
                                            <div><strong>Correo 1</strong></div>
                                            <div class="text-end"><?php echo intval($sentCorreo1); ?> &nbsp; sent /
                                                <?php echo intval($uniqueClicksCorreo1); ?> clicks
                                                (<?php echo $tasaClicksCorreo1Pct; ?>%)</div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1">
                                            <div><strong>Correo 2</strong></div>
                                            <div class="text-end"><?php echo intval($sentCorreo2); ?>&nbsp; sent /
                                                <?php echo intval($uniqueClicksCorreo2); ?> clicks
                                                (<?php echo $tasaClicksCorreo2Pct; ?>%)</div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card stat-card bg-primary text-white h-100">
                            <div class="card-body d-flex align-items-center justify-content-between p-3 w-100">
                                <div>
                                    <div class="small opacity-90 mb-1">Clicks hoy</div>
                                    <div class="display-5 fw-bold"><?php echo $todayCount; ?></div>
                                    <small class="d-block opacity-75 mt-3">Correo 1:
                                        <?php echo intval($correo1TodayCount); ?> <br> Correo 2:
                                        <?php echo intval($correo2TodayCount); ?></small>
                                </div>
                                <div class="display-4 opacity-75">📈</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card stat-card bg-success text-white h-100">
                            <div class="card-body d-flex align-items-center justify-content-between p-3 w-100">
                                <div>
                                    <div class="small opacity-90 mb-1">Total</div>
                                    <div class="display-5 fw-bold"><?php echo $totalCount; ?></div>
                                    <small class="d-block opacity-75 mt-3">Correo 1:
                                        <?php echo intval($correo1Count); ?> <br> Correo 2:
                                        <?php echo intval($correo2Count); ?></small>
                                </div>
                                <div class="display-4 opacity-75">📊</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mt-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Tendencia últimos 60 días</h5>
                        <div id="clicksChart" style="min-height:250px;width:100%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="clicksTable" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Lead ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Correo</th>
                                    <th>URL</th>
                                    <th>Clicked at</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?php echo $r['id']; ?></td>
                                        <td><?php echo $r['lead_id']; ?></td>
                                        <td><?php echo htmlspecialchars($r['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($r['email']); ?></td>
                                        <td><?php echo intval($r['correo']); ?></td>
                                        <td><a href="<?php echo htmlspecialchars($r['url']); ?>"
                                                target="_blank"><?php echo htmlspecialchars($r['url']); ?></a></td>
                                        <td><?php echo htmlspecialchars(formatCreatedTime($r['clicked_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#clicksTable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
                order: [],
                pageLength: 25,
                responsive: true
            });

            Highcharts.chart('clicksChart', {
                chart: { type: 'line' },
                title: { text: 'Clicks por día (últimos 60 días)' },
                xAxis: { type: 'datetime' },
                yAxis: { min: 0, title: { text: 'Clicks' } },
                series: [
                    { name: 'Correo 1', data: <?php echo $seriesJson1; ?> },
                    { name: 'Correo 2', data: <?php echo $seriesJson2; ?> }
                ],
                credits: { enabled: false }
            });

            // Log the enabled dates (clicks-based)
            console.log('Fecha mínima habilitada (clicks):', '<?php echo $minClickDateAttr; ?>');
            console.log('Fecha máxima habilitada (clicks):', '<?php echo $maxClickDateAttr; ?>');
            console.log('Detalles de fechas por tabla y campo:', <?php echo json_encode($dateDebug); ?>);

            // Log the oldest and newest click entries (sanitized summary)
            console.log('Registro con click más lejano (oldest):', <?php echo json_encode($oldestClickLog); ?>);
            console.log('Registro con click más reciente (newest):', <?php echo json_encode($newestClickLog); ?>);

            // Reset button: remove query string and reload page
            $('#btnResetClicks').on('click', function () {
                var url = window.location.href.split('?')[0];
                window.location.href = url;
            });
        });
    </script>
</body>

</html>