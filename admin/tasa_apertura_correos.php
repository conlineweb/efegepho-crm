<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';

// ------------------------------------------------------------
// Helper: format dates like in consulta_leads
// ------------------------------------------------------------
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

// ------------------------------------------------------------
// 1. Obtener todas las tablas que tienen columnas de envío
// ------------------------------------------------------------
$tablesWithSentColumns = [];
$colRes = $conn->query("
    SELECT DISTINCT TABLE_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND COLUMN_NAME IN ('correo_uno_enviado', 'correo_dos_enviado')
");
if ($colRes) {
    while ($row = $colRes->fetch_assoc()) {
        $tablesWithSentColumns[$row['TABLE_NAME']] = true;
    }
}

// ------------------------------------------------------------
// 2. Leer filtros GET
// ------------------------------------------------------------
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate   = isset($_GET['end_date'])   ? trim($_GET['end_date'])   : '';
$correo    = isset($_GET['correo'])     ? trim($_GET['correo'])     : '';

// ------------------------------------------------------------
// 3. WHERE para aperturas (opened_at en el rango)
// ------------------------------------------------------------
$whereOpens = "1=1";
if ($startDate !== '') {
    $sd = date('Y-m-d', strtotime($startDate)) . ' 00:00:00';
    $whereOpens .= " AND opened_at >= '" . $conn->real_escape_string($sd) . "'";
}
if ($endDate !== '') {
    $ed = date('Y-m-d', strtotime($endDate)) . ' 23:59:59';
    $whereOpens .= " AND opened_at <= '" . $conn->real_escape_string($ed) . "'";
}
if ($correo !== '' && in_array($correo, ['1', '2'], true)) {
    $whereOpens .= " AND correo = " . intval($correo);
}

// ------------------------------------------------------------
// 4. Obtener aperturas y validar que correspondan a envíos reales
//    (fecha de envío dentro del mismo rango Desde-Hasta)
// ------------------------------------------------------------
$res = $conn->query("SELECT * FROM email_opens WHERE " . $whereOpens . " ORDER BY opened_at DESC");
$validOpens = [];
$discardedLog = [];

if ($res && $res->num_rows > 0) {
    while ($open = $res->fetch_assoc()) {
        $tabla = $open['tabla_origen'];
        $leadId = intval($open['lead_id']);
        $correoTipo = intval($open['correo']);

        // a) La tabla debe estar en nuestra lista de tablas con columnas de envío
        if (empty($tabla) || !isset($tablesWithSentColumns[$tabla])) {
            $discardedLog[] = "Tabla '$tabla' sin columnas de envío - apertura ID {$open['id']} descartada";
            continue;
        }

        // b) Consultar el lead en su tabla original
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla);
        $leadQuery = $conn->query("
            SELECT full_name, email, phone,
                   correo_uno_enviado, correo_dos_enviado,
                   fecha_envio_correo_uno, fecha_envio_correo_dos
            FROM `$safeTable`
            WHERE id = $leadId
            LIMIT 1
        ");
        if (!$leadQuery || $leadQuery->num_rows === 0) {
            $discardedLog[] = "Lead ID $leadId no encontrado en $tabla - apertura ID {$open['id']} descartada";
            continue;
        }
        $lead = $leadQuery->fetch_assoc();

        // c) Validar que el correo fue realmente enviado y su fecha está dentro del rango seleccionado
        $isValid = false;
        if ($correoTipo === 1) {
            if (isset($lead['correo_uno_enviado']) && intval($lead['correo_uno_enviado']) === 1) {
                $fechaEnvio = $lead['fecha_envio_correo_uno'] ?? null;
                if (!empty($fechaEnvio) && $fechaEnvio !== '0000-00-00 00:00:00') {
                    $tsEnvio = strtotime($fechaEnvio);
                    $envioOk = true;
                    if ($startDate !== '') {
                        $sdSent = strtotime(date('Y-m-d', strtotime($startDate)) . ' 00:00:00');
                        if ($tsEnvio < $sdSent) $envioOk = false;
                    }
                    if ($endDate !== '') {
                        $edSent = strtotime(date('Y-m-d', strtotime($endDate)) . ' 23:59:59');
                        if ($tsEnvio > $edSent) $envioOk = false;
                    }
                    if ($envioOk) $isValid = true;
                }
            }
        } elseif ($correoTipo === 2) {
            if (isset($lead['correo_dos_enviado']) && intval($lead['correo_dos_enviado']) === 1) {
                $fechaEnvio = $lead['fecha_envio_correo_dos'] ?? null;
                if (!empty($fechaEnvio) && $fechaEnvio !== '0000-00-00 00:00:00') {
                    $tsEnvio = strtotime($fechaEnvio);
                    $envioOk = true;
                    if ($startDate !== '') {
                        $sdSent = strtotime(date('Y-m-d', strtotime($startDate)) . ' 00:00:00');
                        if ($tsEnvio < $sdSent) $envioOk = false;
                    }
                    if ($endDate !== '') {
                        $edSent = strtotime(date('Y-m-d', strtotime($endDate)) . ' 23:59:59');
                        if ($tsEnvio > $edSent) $envioOk = false;
                    }
                    if ($envioOk) $isValid = true;
                }
            }
        }

        if (!$isValid) {
            $discardedLog[] = "Lead ID $leadId en $tabla: envío de correo $correoTipo fuera de rango o no válido - apertura ID {$open['id']} descartada";
            continue;
        }

        // d) Apertura válida: agregar datos del lead
        $open['full_name'] = $lead['full_name'];
        $open['email']     = $lead['email'];
        $open['phone']     = $lead['phone'];
        $validOpens[] = $open;
    }
}

// Registrar en el log de PHP las aperturas descartadas (útil para depuración)
foreach ($discardedLog as $msg) {
    error_log("[Tasa apertura] $msg");
}

$rows = $validOpens; // a partir de aquí solo trabajamos con aperturas válidas

// ------------------------------------------------------------
// 5. Calcular conteos de aperturas (únicas, hoy, total, etc.)
// ------------------------------------------------------------
$today = date('Y-m-d');
$todayCount = 0;
$correo1TodayCount = 0;
$correo2TodayCount = 0;
$totalCount = count($rows);
$correo1Count = 0;
$correo2Count = 0;
$uniqueOpenKeys = [];
$uniqueOpensTotal = 0;
$uniqueOpensCorreo1 = 0;
$uniqueOpensCorreo2 = 0;

foreach ($rows as $r) {
    $ts = strtotime($r['opened_at']);
    $date = date('Y-m-d', $ts);
    $correoTipo = intval($r['correo']);

    // totales y hoy
    if ($ts !== false && $date === $today) {
        $todayCount++;
        if ($correoTipo === 1) $correo1TodayCount++;
        if ($correoTipo === 2) $correo2TodayCount++;
    }

    if ($correoTipo === 1) $correo1Count++;
    if ($correoTipo === 2) $correo2Count++;

    // aperturas únicas (lead + tabla + correo)
    $key = $r['tabla_origen'] . '|' . $r['lead_id'] . '|' . $correoTipo;
    if (!isset($uniqueOpenKeys[$key])) {
        $uniqueOpenKeys[$key] = true;
        $uniqueOpensTotal++;
        if ($correoTipo === 1) $uniqueOpensCorreo1++;
        if ($correoTipo === 2) $uniqueOpensCorreo2++;
    }
}

$repeatedOpensTotal   = max(0, $totalCount - $uniqueOpensTotal);
$repeatedOpensCorreo1 = max(0, $correo1Count - $uniqueOpensCorreo1);
$repeatedOpensCorreo2 = max(0, $correo2Count - $uniqueOpensCorreo2);

// ------------------------------------------------------------
// 6. Contar correos ENVIADOS (con fecha de envío en el mismo rango Desde-Hasta)
// ------------------------------------------------------------
$sentCorreo1 = 0;
$sentCorreo2 = 0;

// Construir cláusulas WHERE para fecha de envío (ambos filtros, Desde y Hasta)
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

// Filtro por tipo de correo
$filterCorreo1 = ($correo === '1');
$filterCorreo2 = ($correo === '2');

foreach (array_keys($tablesWithSentColumns) as $tableName) {
    $safeTable = $conn->real_escape_string($tableName);

    // Verificar qué columnas existen realmente en esta tabla
    $hasUno = false;
    $hasDos = false;
    $colCheck = $conn->query("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$safeTable'
          AND COLUMN_NAME IN ('correo_uno_enviado', 'correo_dos_enviado')
    ");
    if ($colCheck) {
        while ($c = $colCheck->fetch_assoc()) {
            if ($c['COLUMN_NAME'] === 'correo_uno_enviado') $hasUno = true;
            if ($c['COLUMN_NAME'] === 'correo_dos_enviado') $hasDos = true;
        }
    }

    // Correo 1
    if ($hasUno && !$filterCorreo2) {
        $q1 = $conn->query("
            SELECT COUNT(*) as cnt
            FROM `$safeTable`
            WHERE correo_uno_enviado = 1
              AND fecha_envio_correo_uno IS NOT NULL
              AND fecha_envio_correo_uno != '0000-00-00 00:00:00'
              $sentDateWhere1
        ");
        if ($q1) {
            $r1 = $q1->fetch_assoc();
            $sentCorreo1 += intval($r1['cnt']);
        }
    }

    // Correo 2
    if ($hasDos && !$filterCorreo1) {
        $q2 = $conn->query("
            SELECT COUNT(*) as cnt
            FROM `$safeTable`
            WHERE correo_dos_enviado = 1
              AND fecha_envio_correo_dos IS NOT NULL
              AND fecha_envio_correo_dos != '0000-00-00 00:00:00'
              $sentDateWhere2
        ");
        if ($q2) {
            $r2 = $q2->fetch_assoc();
            $sentCorreo2 += intval($r2['cnt']);
        }
    }
}

$sentTotal = $sentCorreo1 + $sentCorreo2;

// ------------------------------------------------------------
// 7. Calcular tasas de apertura
// ------------------------------------------------------------
$tasaAperturaPct        = ($sentTotal > 0) ? round(($uniqueOpensTotal / $sentTotal) * 100, 2) : 0;
$tasaAperturaCorreo1Pct = ($sentCorreo1 > 0) ? round(($uniqueOpensCorreo1 / $sentCorreo1) * 100, 2) : 0;
$tasaAperturaCorreo2Pct = ($sentCorreo2 > 0) ? round(($uniqueOpensCorreo2 / $sentCorreo2) * 100, 2) : 0;

// ------------------------------------------------------------
// 8. Determinar rango de fechas disponible para los inputs (basado en aperturas válidas)
// ------------------------------------------------------------
$minOpenDateAttr = '';
$maxOpenDateAttr = '';
$oldestOpen = null;
$newestOpen = null;
foreach ($rows as $r) {
    $ts = strtotime($r['opened_at']);
    if ($ts === false) continue;
    if ($oldestOpen === null || $ts < strtotime($oldestOpen['opened_at'])) $oldestOpen = $r;
    if ($newestOpen === null || $ts > strtotime($newestOpen['opened_at'])) $newestOpen = $r;
}
if ($oldestOpen) $minOpenDateAttr = date('Y-m-d', strtotime($oldestOpen['opened_at']));
if ($newestOpen) $maxOpenDateAttr = date('Y-m-d', strtotime($newestOpen['opened_at']));

// ------------------------------------------------------------
// 9. Preparar series para gráfico (últimos 60 días)
// ------------------------------------------------------------
$countsMap1 = [];
$countsMap2 = [];
for ($i = 0; $i < 60; $i++) {
    $d = date('Y-m-d', strtotime("-" . (59 - $i) . " days"));
    $countsMap1[$d] = 0;
    $countsMap2[$d] = 0;
}
foreach ($rows as $r) {
    $ts = strtotime($r['opened_at']);
    if ($ts === false) continue;
    $d = date('Y-m-d', $ts);
    $c = intval($r['correo']);
    if ($c === 1 && isset($countsMap1[$d])) $countsMap1[$d]++;
    if ($c === 2 && isset($countsMap2[$d])) $countsMap2[$d]++;
}
$series1 = [];
$series2 = [];
foreach ($countsMap1 as $d => $c) $series1[] = [strtotime($d) * 1000, $c];
foreach ($countsMap2 as $d => $c) $series2[] = [strtotime($d) * 1000, $c];
$seriesJson1 = json_encode($series1);
$seriesJson2 = json_encode($series2);

// ------------------------------------------------------------
// 10. Pequeño log en consola para seguimiento
// ------------------------------------------------------------
$oldestOpenLog = $oldestOpen ? [
    'id'          => $oldestOpen['id'],
    'tabla_origen'=> $oldestOpen['tabla_origen'],
    'lead_id'     => $oldestOpen['lead_id'],
    'full_name'   => $oldestOpen['full_name'],
    'correo'      => $oldestOpen['correo'],
    'opened_at'   => $oldestOpen['opened_at']
] : null;
$newestOpenLog = $newestOpen ? [
    'id'          => $newestOpen['id'],
    'tabla_origen'=> $newestOpen['tabla_origen'],
    'lead_id'     => $newestOpen['lead_id'],
    'full_name'   => $newestOpen['full_name'],
    'correo'      => $newestOpen['correo'],
    'opened_at'   => $newestOpen['opened_at']
] : null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tasa apertura correos</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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

        .rate-card {
            border-radius: .6rem;
            min-height: 140px;
            color: #fff;
        }

        .rate-card .rate-value {
            font-size: 2.5rem;
            line-height: 1;
        }

        .rate-card .stat-list {
            display: flex;
            flex-direction: column;
            gap: .35rem;
        }

        .rate-card .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .rate-card .stat-label {
            font-weight: 600;
        }

        .rate-card .stat-value {
            font-weight: 700;
        }

        .rate-icon {
            font-size: 1.6rem;
        }

        .rate-card small {
            white-space: normal;
        }

        .rate-card .stat-item {
            gap: .75rem;
        }

        .rate-card .stat-item>span {
            min-width: 0;
        }

        @media (max-width: 767px) {
            .rate-card .rate-value {
                font-size: 1.75rem;
            }
        }
    </style>
</head>

<body>
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1">
                    <h3>Tasa apertura correos</h3>
                </div>
                <div class="col-12 col-md-6 order-md-2 text-end">
                    <small>Shows leads that opened emails (email opens)</small>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <form method="get" class="row g-2 align-items-end justify-content-center">
                    <div class="col-6 col-md-4">
                        <label class="form-label small">Desde</label>
                        <input type="date" name="start_date" class="form-control"
                            value="<?php echo htmlspecialchars($startDate); ?>"
                            <?php if (!empty($minOpenDateAttr)) echo 'min="' . htmlspecialchars($minOpenDateAttr) . '"'; ?>
                            <?php if (!empty($maxOpenDateAttr)) echo 'max="' . htmlspecialchars($maxOpenDateAttr) . '"'; ?>>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small">Hasta</label>
                        <input type="date" name="end_date" class="form-control"
                            value="<?php echo htmlspecialchars($endDate); ?>"
                            <?php if (!empty($minOpenDateAttr)) echo 'min="' . htmlspecialchars($minOpenDateAttr) . '"'; ?>
                            <?php if (!empty($maxOpenDateAttr)) echo 'max="' . htmlspecialchars($maxOpenDateAttr) . '"'; ?>>
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
                            <button type="reset" class="btn btn-secondary">Resetar</button>
                        </div>
                    </div>
                </form>
                <div class="col-12 small text-muted mt-2">
                    <strong>Nota:</strong> Los correos <strong>enviados</strong> se cuentan únicamente si su fecha de envío está dentro del rango <strong>Desde - Hasta</strong> seleccionado.
                    Esto asegura que la tasa de apertura refleje exactamente el comportamiento de los correos enviados en ese período.
                    Solo se cuenta una apertura por lead (no se cuentan múltiples aperturas).
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-lg-6">
                <div class="row mt-3">
                    <div class="col-sm-4">
                        <div class="card bg-info text-white h-100 rate-card">
                            <div class="p-3 d-flex flex-column h-100">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="small opacity-90 mb-1">Tasa de apertura</div>
                                        <div class="display-5 fw-bold rate-value"><?php echo $tasaAperturaPct; ?>%</div>
                                        <small class="d-block opacity-75 mt-2">Nota: solo se cuenta una apertura por lead (no se cuentan múltiples aperturas).</small>
                                    </div>
                                    <div class="text-end rate-icon">📬</div>
                                </div>

                                <div class="mt-3 text-white small">
                                    <div class="stat-list">
                                        <div class="stat-item"><span class="stat-label">Enviados</span><span class="stat-value"><?php echo intval($sentTotal); ?></span></div>
                                        <div class="stat-item"><span class="stat-label">Aperturas únicas</span><span class="stat-value"><?php echo intval($uniqueOpensTotal); ?></span></div>
                                        <div class="stat-item"><span class="stat-label">Aperturas repetidas<br><span style="font-size: 10px;">(no cuentan para la tasa)</span></span><span class="stat-value"><?php echo intval($repeatedOpensTotal); ?></span></div>
                                    </div>

                                    <div class="small mt-3">Detalle por correo</div>

                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between align-items-baseline">
                                            <div class="fw-bold">Correo 1</div>
                                            <div class="fw-bold"><?php echo $tasaAperturaCorreo1Pct; ?>%</div>
                                        </div>
                                        <small class="d-block opacity-75">Enviados: <?php echo intval($sentCorreo1); ?> | Únicas: <?php echo intval($uniqueOpensCorreo1); ?> | Repetidas: <?php echo intval($repeatedOpensCorreo1); ?></small>
                                    </div>

                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between align-items-baseline">
                                            <div class="fw-bold">Correo 2</div>
                                            <div class="fw-bold"><?php echo $tasaAperturaCorreo2Pct; ?>%</div>
                                        </div>
                                        <small class="d-block opacity-75">Enviados: <?php echo intval($sentCorreo2); ?> | Únicas: <?php echo intval($uniqueOpensCorreo2); ?> | Repetidas: <?php echo intval($repeatedOpensCorreo2); ?></small>
                                    </div>
                                </div>

                                <div class="mt-auto text-end small opacity-75">
                                    Última apertura: <?php echo !empty($newestOpen) ? formatCreatedTime($newestOpen['opened_at']) : 'N/A'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card stat-card bg-primary text-white h-100">
                            <div class="card-body d-flex align-items-center justify-content-between p-3 w-100">
                                <div>
                                    <div class="small opacity-90 mb-1">Abiertos hoy</div>
                                    <div class="display-5 fw-bold"><?php echo $todayCount; ?></div>
                                    <small class="d-block opacity-75 mt-3">Correo 1: <?php echo intval($correo1TodayCount); ?><br>Correo 2: <?php echo intval($correo2TodayCount); ?></small>
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
                                    <small class="d-block opacity-75 mt-3">Correo 1: <?php echo intval($correo1Count); ?><br>Correo 2: <?php echo intval($correo2Count); ?></small>
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
                        <div id="opensChart" style="min-height:250px;width:100%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="opensTable" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Lead ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Correo</th>
                                    <th>Abierto a las</th>
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
                                        <td><?php echo htmlspecialchars(formatCreatedTime($r['opened_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/min/moment.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#opensTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                order: [],
                pageLength: 25,
                responsive: true
            });

            Highcharts.chart('opensChart', {
                chart: {
                    type: 'line'
                },
                title: {
                    text: 'Aperturas por día (últimos 60 días)'
                },
                xAxis: {
                    type: 'datetime'
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: 'Aperturas'
                    }
                },
                series: [{
                        name: 'Correo 1',
                        data: <?php echo $seriesJson1; ?>
                    },
                    {
                        name: 'Correo 2',
                        data: <?php echo $seriesJson2; ?>
                    }
                ],
                credits: {
                    enabled: false
                }
            });

            // Logging para depuración
            console.log('Fecha mínima habilitada (opens):', '<?php echo $minOpenDateAttr; ?>');
            console.log('Fecha máxima habilitada (opens):', '<?php echo $maxOpenDateAttr; ?>');
            console.log('Filtro de envíos aplicado (Desde y Hasta):', {
                start: '<?php echo htmlspecialchars($startDate); ?>',
                end: '<?php echo htmlspecialchars($endDate); ?>'
            });
            console.log('Aperturas descartadas (ver también log PHP):', <?php echo json_encode($discardedLog); ?>);
            console.log('Registro más antiguo:', <?php echo json_encode($oldestOpenLog); ?>);
            console.log('Registro más reciente:', <?php echo json_encode($newestOpenLog); ?>);
        });
    </script>
</body>

</html>