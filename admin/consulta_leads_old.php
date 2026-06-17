<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

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

// Obtener todas las tablas de leads
$tablas = [];
$sqlTablas = "SELECT nombre FROM tablas_leads ORDER BY nombre";
$resultTablas = $conn->query($sqlTablas);

if ($resultTablas && $resultTablas->num_rows > 0) {
    while ($row = $resultTablas->fetch_assoc()) {
        $tablas[] = $row['nombre'];
    }
}

// Consultar todos los leads de todas las tablas
$allLeads = [];

foreach ($tablas as $tableName) {
    // Verificar que la tabla existe
    $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($checkTable->num_rows > 0) {
        // Verificar qué columnas existen en la tabla
        $columns = [];
        $columnsResult = $conn->query("SHOW COLUMNS FROM `$tableName`");
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }

        // Construir la consulta según las columnas disponibles ORDENANDOLO DEL MAS RECIENTE AL MAS ANTIGUIO
        $sqlLeads = "SELECT *, '$tableName' as tabla_origen FROM `$tableName` WHERE " .
            (in_array('descartado', $columns) ? "descartado = 0 OR descartado IS NULL" : "1=1") . " ORDER BY created_time DESC";

        $resultLeads = $conn->query($sqlLeads);
        if ($resultLeads && $resultLeads->num_rows > 0) {
            while ($lead = $resultLeads->fetch_assoc()) {
                $allLeads[] = $lead;
            }
        }
    }
}

// Nota: no cerrar la conexión aquí porque la usaremos más adelante
// $conn->close();

// Calcular conteo de leads del día actual basado en `created_time`
$todayCount = 0;
$today = date('Y-m-d');
foreach ($allLeads as $lead) {
    if (empty($lead['created_time']))
        continue;
    $ts = strtotime($lead['created_time']);
    if ($ts === false)
        continue;
    if (date('Y-m-d', $ts) === $today) {
        $todayCount++;
    }
}

// Recopilar orígenes únicos y medios por origen de todos los leads
$origenesUnicos = [];
$mediosPorOrigen = [];
foreach ($allLeads as $lead) {
    $origen = $lead['campaign_name'] ?? '';
    // Priorizar `form_name` si está disponible, sino usar `platform`
    $medio = trim((string)($lead['form_name'] ?? $lead['platform'] ?? ''));
    
    // Normalizar origen (extraer c + número si aplica)
    $origenNormalizado = $origen;
    if (preg_match('/^c(\d+)\./i', $origen, $matches)) {
        $origenNormalizado = 'c' . $matches[1];
    }
    
    if (!empty($origenNormalizado) && !in_array($origenNormalizado, $origenesUnicos)) {
        $origenesUnicos[] = $origenNormalizado;
    }
    
    // Mapear medios por origen
    if (!empty($origenNormalizado) && !empty($medio)) {
        if (!isset($mediosPorOrigen[$origenNormalizado])) {
            $mediosPorOrigen[$origenNormalizado] = [];
        }
        if (!in_array($medio, $mediosPorOrigen[$origenNormalizado])) {
            $mediosPorOrigen[$origenNormalizado][] = $medio;
        }
    }
}
sort($origenesUnicos);

// Leer filtros de fecha (vienen por GET)
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$filterOrigen = isset($_GET['filter_origen']) ? trim($_GET['filter_origen']) : '';
$filterMedio = isset($_GET['filter_medio']) ? trim($_GET['filter_medio']) : '';
// Nuevo filtro por estatus (estatus normalizado como en $leadStatusMap)
$filterEstatus = isset($_GET['filter_estatus']) ? trim($_GET['filter_estatus']) : '';

// Aplicar filtros a los leads
$filteredLeads = [];
foreach ($allLeads as $lead) {
    // Filtro por fecha
    if ($startDate !== '' || $endDate !== '') {
        if (empty($lead['created_time']))
            continue;
        $leadDate = date('Y-m-d', strtotime($lead['created_time']));
        $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
        $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
        if ($sd && $leadDate < $sd)
            continue;
        if ($ed && $leadDate > $ed)
            continue;
    }
    
    // Filtro por origen
    if ($filterOrigen !== '') {
        $leadOrigen = $lead['campaign_name'] ?? '';
        $leadOrigenNormalizado = $leadOrigen;
        if (preg_match('/^c(\d+)\./i', $leadOrigen, $matches)) {
            $leadOrigenNormalizado = 'c' . $matches[1];
        }
        if ($leadOrigenNormalizado !== $filterOrigen) {
            continue;
        }
    }
    
    // Filtro por medio (priorizar form_name)
    if ($filterMedio !== '') {
        $leadMedio = trim((string)($lead['form_name'] ?? $lead['platform'] ?? ''));
        if ($leadMedio !== $filterMedio) {
            continue;
        }
    }
    
    $filteredLeads[] = $lead;
}

// Ordenar globalmente los leads por created_time (más recientes primero)
usort($filteredLeads, function ($a, $b) {
    $ta = (!empty($a['created_time']) && strtotime($a['created_time']) !== false) ? strtotime($a['created_time']) : 0;
    $tb = (!empty($b['created_time']) && strtotime($b['created_time']) !== false) ? strtotime($b['created_time']) : 0;
    if ($ta === $tb)
        return 0;
    return ($ta > $tb) ? -1 : 1;
});

// Conteo total de leads mostrados después del filtro (antes de filtro por estatus)
$totalCount = count($filteredLeads);

// Construir mapa por fecha y determinar primer/último created_time
$map = [];
$minTs = null;
$maxTs = null;
foreach ($filteredLeads as $lead) {
    if (empty($lead['created_time']))
        continue;
    $ts = strtotime($lead['created_time']);
    if ($ts === false)
        continue;
    $d = date('Y-m-d', $ts);
    if (!isset($map[$d]))
        $map[$d] = 0;
    $map[$d]++;
    if ($minTs === null || $ts < $minTs)
        $minTs = $ts;
    if ($maxTs === null || $ts > $maxTs)
        $maxTs = $ts;
}

// Si no hay datos, preparar arrays vacíos
if ($minTs === null) {
    $seriesJson = json_encode([]);
    $datesJson = json_encode([]);
    $countsJson = json_encode([]);
} else {
    // Normalizar al inicio del día
    $start = strtotime(date('Y-m-d', $minTs));
    $end = strtotime(date('Y-m-d', $maxTs));
    $series = [];
    $dates = [];
    $counts = [];
    for ($ts = $start; $ts <= $end; $ts += 86400) { // recorrer por días
        $d = date('Y-m-d', $ts);
        $c = isset($map[$d]) ? $map[$d] : 0;
        // Highcharts espera timestamp en ms
        $series[] = [$ts * 1000, $c];
        $dates[] = $d;
        $counts[] = $c;
    }
    $seriesJson = json_encode($series);
    $datesJson = json_encode($dates);
    $countsJson = json_encode($counts);
}

$leadIdsByTable = [];
foreach ($filteredLeads as $lead) {
    $t = $lead['tabla_origen'] ?? '';
    $id = intval($lead['id'] ?? 0);
    if ($t === '' || $id <= 0) continue;
    if (!isset($leadIdsByTable[$t])) $leadIdsByTable[$t] = [];
    $leadIdsByTable[$t][] = $id;
}
// --- Build a map of contact_form entries and appointments (to determine estatus for leads) ---
$contactFormByLead = [];
$cfIds = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '')
        continue;
    $sql = "SELECT id, original_lead_id, cliente FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")";
    $resCf = $conn->query($sql);
    if ($resCf) {
        while ($row = $resCf->fetch_assoc()) {
            $key = $t . '|' . intval($row['original_lead_id']);
            $contactFormByLead[$key] = [
                'cf_id' => intval($row['id']),
                'cliente' => isset($row['cliente']) ? intval($row['cliente']) : 0
            ];
            $cfIds[] = intval($row['id']);
        }
    }
}

$appointmentsByCFId = [];
if (!empty($cfIds)) {
    $cfList = implode(',', array_map('intval', $cfIds));
    $apptRes = $conn->query("SELECT * FROM calendario WHERE idclie IN (" . $cfList . ")");
    if ($apptRes && $apptRes->num_rows > 0) {
        while ($ar = $apptRes->fetch_assoc()) {
            $idclie = isset($ar['idclie']) ? intval($ar['idclie']) : 0;
            if ($idclie <= 0)
                continue;

            if (!isset($appointmentsByCFId[$idclie])) {
                $appointmentsByCFId[$idclie] = $ar;
            } else {
                $prev = $appointmentsByCFId[$idclie];
                $replace = false;
                if (!empty($ar['fecha']) && !empty($prev['fecha'])) {
                    $t1 = strtotime($ar['fecha'] . ' ' . ($ar['hora'] ?? '')) ?: 0;
                    $t2 = strtotime($prev['fecha'] . ' ' . ($prev['hora'] ?? '')) ?: 0;
                    if ($t1 > $t2)
                        $replace = true;
                } elseif (!empty($ar['id']) && !empty($prev['id'])) {
                    if (intval($ar['id']) > intval($prev['id']))
                        $replace = true;
                }
                if ($replace)
                    $appointmentsByCFId[$idclie] = $ar;
            }
        }
    }
}

// Build final map of status per lead (tabla|id)
$leadStatusMap = [];
foreach ($leadIdsByTable as $t => $ids) {
    foreach ($ids as $lid) {
        $key = $t . '|' . intval($lid);
        $leadStatusMap[$key] = 'lead'; // default
        if (isset($contactFormByLead[$key])) {
            $cf = $contactFormByLead[$key];
            if (isset($cf['cliente']) && intval($cf['cliente']) === 1) {
                $leadStatusMap[$key] = 'cliente';
                continue;
            }
            $cfId = intval($cf['cf_id']);
            if ($cfId > 0 && isset($appointmentsByCFId[$cfId]) && isset($appointmentsByCFId[$cfId]['estatus'])) {
                $rawStatus = $appointmentsByCFId[$cfId]['estatus'];
                $intStatus = is_numeric($rawStatus) ? intval($rawStatus) : null;
                if ($intStatus === 1) {
                    $leadStatusMap[$key] = 'atendido';
                } elseif ($intStatus === 3) {
                    $leadStatusMap[$key] = 'muerto';
                } elseif ($intStatus === 0) {
                    $leadStatusMap[$key] = 'agendado';
                } elseif ($intStatus === 2) {
                    $leadStatusMap[$key] = 'fantasma';
                } else {
                    $leadStatusMap[$key] = is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : 'agendado';
                }
            }
        }
    }
}
?>
<?php
// Prepare unique estatus list for the filter UI (derived from the leadStatusMap)
$estatusUnicos = [];
foreach ($leadStatusMap as $k => $v) {
    $vNorm = trim((string)$v);
    if ($vNorm === '') $vNorm = 'lead';
    if (!in_array($vNorm, $estatusUnicos)) $estatusUnicos[] = $vNorm;
}
sort($estatusUnicos);

// Calcular leads que agendaron (agendado, atendido, cliente) - ANTES del filtro por estatus
$leadsAgendadosTotal = 0;
$totalLeadsBeforeStatusFilter = count($filteredLeads);
foreach ($filteredLeads as $lead) {
    $tabla = $lead['tabla_origen'] ?? '';
    $lid = isset($lead['id']) ? intval($lead['id']) : 0;
    $key = $tabla . '|' . $lid;
    if (isset($leadStatusMap[$key])) {
        $status = strtolower($leadStatusMap[$key]);
        if ($status === 'agendado' || $status === 'atendido' || $status === 'cliente') {
            $leadsAgendadosTotal++;
        }
    }
}

// Calcular tasa de conversión (porcentaje)
$tasaConversion = $totalLeadsBeforeStatusFilter > 0 ? round(($leadsAgendadosTotal / $totalLeadsBeforeStatusFilter) * 100, 2) : 0;
?>
<?php
// -- Aplicar filtro por estatus (usando $leadStatusMap que ya fue construido) --
if ($filterEstatus !== '') {
    $filteredLeadsByStatus = [];
    foreach ($filteredLeads as $lead) {
        $key = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);
        $stat = isset($leadStatusMap[$key]) ? $leadStatusMap[$key] : 'lead';
        if (mb_strtolower($stat, 'UTF-8') === mb_strtolower($filterEstatus, 'UTF-8')) {
            $filteredLeadsByStatus[] = $lead;
        }
    }
    $filteredLeads = $filteredLeadsByStatus;
}

// Re-ordenar después de filtrar por estatus
usort($filteredLeads, function ($a, $b) {
    $ta = (!empty($a['created_time']) && strtotime($a['created_time']) !== false) ? strtotime($a['created_time']) : 0;
    $tb = (!empty($b['created_time']) && strtotime($b['created_time']) !== false) ? strtotime($b['created_time']) : 0;
    if ($ta === $tb) return 0;
    return ($ta > $tb) ? -1 : 1;
});

// Recalcular conteos y serie (basados en $filteredLeads finales)
$totalCount = count($filteredLeads);
$todayCount = 0;
$today = date('Y-m-d');
foreach ($filteredLeads as $lead) {
    if (empty($lead['created_time'])) continue;
    $ts = strtotime($lead['created_time']);
    if ($ts === false) continue;
    if (date('Y-m-d', $ts) === $today) $todayCount++;
}

// Reconstruir mapa por fecha y la serie para la gráfica
$map = [];
$minTs = null;
$maxTs = null;
foreach ($filteredLeads as $lead) {
    if (empty($lead['created_time'])) continue;
    $ts = strtotime($lead['created_time']);
    if ($ts === false) continue;
    $d = date('Y-m-d', $ts);
    if (!isset($map[$d])) $map[$d] = 0;
    $map[$d]++;
    if ($minTs === null || $ts < $minTs) $minTs = $ts;
    if ($maxTs === null || $ts > $maxTs) $maxTs = $ts;
}

if ($minTs === null) {
    $seriesJson = json_encode([]);
    $datesJson = json_encode([]);
    $countsJson = json_encode([]);
} else {
    $start = strtotime(date('Y-m-d', $minTs));
    $end = strtotime(date('Y-m-d', $maxTs));
    $series = [];
    $dates = [];
    $counts = [];
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $d = date('Y-m-d', $ts);
        $c = isset($map[$d]) ? $map[$d] : 0;
        $series[] = [$ts * 1000, $c];
        $dates[] = $d;
        $counts[] = $c;
    }
    $seriesJson = json_encode($series);
    $datesJson = json_encode($dates);
    $countsJson = json_encode($counts);
}

// Recalcular maps finales (opens, wedding locations, scheduled) para los leads filtrados
$opensMap = [];
$leadIdsByTable = [];
foreach ($filteredLeads as $lead) {
    $t = $lead['tabla_origen'] ?? '';
    $id = intval($lead['id'] ?? 0);
    if ($t === '' || $id <= 0) continue;
    if (!isset($leadIdsByTable[$t])) $leadIdsByTable[$t] = [];
    $leadIdsByTable[$t][] = $id;
}
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '') continue;
    $sql = "SELECT lead_id, correo FROM email_opens WHERE tabla_origen = '" . $safeTable . "' AND lead_id IN (" . $idsList . ")";
    $resOp = $conn->query($sql);
    if ($resOp) {
        while ($rowOp = $resOp->fetch_assoc()) {
            $key = $t . '|' . intval($rowOp['lead_id']) . '|' . intval($rowOp['correo']);
            $opensMap[$key] = true;
        }
    }
}

$weddingLocationsMap = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '') continue;
    $sql = "SELECT original_lead_id, wedding_location FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ") AND wedding_location IS NOT NULL AND wedding_location != ''";
    $resLoc = $conn->query($sql);
    if ($resLoc) {
        while ($rowLoc = $resLoc->fetch_assoc()) {
            $key = $t . '|' . intval($rowLoc['original_lead_id']);
            $weddingLocationsMap[$key] = $rowLoc['wedding_location'];
        }
    }
}

$scheduledLeadsMap = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '') continue;
    $sql = "SELECT original_lead_id FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")";
    $resSched = $conn->query($sql);
    if ($resSched) {
        while ($rowSched = $resSched->fetch_assoc()) {
            $key = $t . '|' . intval($rowSched['original_lead_id']);
            $scheduledLeadsMap[$key] = true;
        }
    }
}
?>
<?php
// Obtener vendedores (tipoUsu = 0 o 1) para los selects de asignación
$vendedores = [];
$resV = $conn->query("SELECT id, nombre, apepat FROM usuarios WHERE tipoUsu IN (0,1) ORDER BY nombre, apepat");
if ($resV && $resV->num_rows > 0) {
    while ($r = $resV->fetch_assoc()) {
        $vendedores[] = $r;
    }
}

// Obtener solo agentes (tipoUsu = 1) para asignación obligatoria en el modal Wedding Planner
$agentes = [];
$resA = $conn->query("SELECT id, nombre, apepat FROM usuarios WHERE tipoUsu = 1 ORDER BY nombre, apepat");
if ($resA && $resA->num_rows > 0) {
    while ($ra = $resA->fetch_assoc()) {
        $agentes[] = $ra;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre qualified leads</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/heatmap.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
</head>
<style>
    #verMasModal p {
        font-size: 1.15rem;
    }

    table {
        width: 100% !important;
    }

    /* Columna de asignación más ancha para mejor visibilidad */
    .assign-col {
        width: 220px;
        min-width: 160px;
    }

    /* Columna de origen más compacta y centrada */
    .origen-col {
        width: 80px;
        min-width: 60px;
        text-align: center;
        font-size: 0.85rem;
    }

    .stat-card {
        border-radius: .6rem;
        min-height: 110px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
    }

    /* Estatus coloring similar to consulta_post_leads.php */
    .status-agendado { color: #7b1fa2; font-weight: 700; }
    .status-fantasma { color: #dc3545; font-weight: 700; }
    .status-atendido { color: #007bff; font-weight: 700; }
    .status-muerto { color: #dc3545; font-weight: 700; }
    .status-cliente { color: #28a745; font-weight: 700; }
    .status-default { font-weight: 700; color: inherit; }

    @media (max-width: 767px) {
        .stat-card {
            min-height: 90px;
        }

        .assign-col {
            width: 140px;
            min-width: 110px;
        }
    }

    /* Modal section headers */
    .modal-section-header {
        margin-bottom: .45rem;
        display: block;
    }
    .modal-section-title {
        font-size: 1rem;
        margin: 0 0 .15rem 0;
        font-weight: 700;
    }
    .modal-section-subtitle {
        font-size: .85rem;
        color: #6c757d; /* muted */
        margin-bottom: .65rem;
    }
</style>
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="order-last order-md-1 col-12 col-md-12">
                <h3>Pre qualified leads</h3>
                <br>
            </div>
            <div class="order-first order-md-2 col-12 col-md-12">

            </div>
        </div>
    </div>



    <div class="row mb-3">
        <!-- Sección de filtros y estadísticas -->
        <div class="col-12 col-lg-6 mb-3 mb-lg-0">
            <!-- Formulario de filtros -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end" id="filterForm">
                        <div class="col-6 col-sm-3">
                            <label class="form-label small mb-1">Desde</label>
                            <input type="date" name="start_date" class="form-control"
                                value="<?php echo htmlspecialchars($startDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-6 col-sm-3">
                            <label class="form-label small mb-1">Hasta</label>
                            <input type="date" name="end_date" class="form-control"
                                value="<?php echo htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-6 col-sm-2">
                            <label class="form-label small mb-1">Origen</label>
                            <select name="filter_origen" id="filterOrigen" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($origenesUnicos as $origen): ?>
                                    <option value="<?php echo htmlspecialchars($origen ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filterOrigen === $origen) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($origen ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-sm-2">
                            <label class="form-label small mb-1">Medio</label>
                            <select name="filter_medio" id="filterMedio" class="form-select">
                                <option value="">Todos</option>
                                <?php 
                                // Mostrar medios del origen seleccionado o todos si no hay filtro
                                $mediosDisponibles = [];
                                if ($filterOrigen !== '' && isset($mediosPorOrigen[$filterOrigen])) {
                                    $mediosDisponibles = $mediosPorOrigen[$filterOrigen];
                                } else {
                                    // Recopilar todos los medios únicos
                                    foreach ($mediosPorOrigen as $medios) {
                                        foreach ($medios as $m) {
                                            if (!in_array($m, $mediosDisponibles)) {
                                                $mediosDisponibles[] = $m;
                                            }
                                        }
                                    }
                                }
                                sort($mediosDisponibles);
                                foreach ($mediosDisponibles as $medio): ?>
                                    <option value="<?php echo htmlspecialchars($medio ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filterMedio === $medio) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($medio ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-sm-2">
                            <label class="form-label small mb-1">Estatus</label>
                            <select name="filter_estatus" id="filterEstatus" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($estatusUnicos as $estatus): ?>
                                    <option value="<?php echo htmlspecialchars($estatus ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($filterEstatus === $estatus) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($estatus ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" title="Filtrar">
                                <i class="bi bi-funnel"></i>
                            </button>
                            <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="btn btn-secondary"
                                title="Limpiar">
                                <i class="bi bi-x-circle"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cards de estadísticas -->
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card stat-card bg-primary text-white h-100">
                        <div class="card-body d-flex align-items-center justify-content-between p-3">
                            <div>
                                <div class="small opacity-90 mb-1">Leads pre qualified hoy</div>
                                <div class="display-5 fw-bold"><?php echo intval($todayCount); ?></div>
                            </div>
                            <div class="card-icon" aria-hidden="true">📈</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card stat-card bg-success text-white h-100">
                        <div class="card-body d-flex align-items-center justify-content-between p-3">
                            <div>
                                <div class="small opacity-90 mb-1">Leads pre qualified totales</div>
                                <div class="display-5 fw-bold"><?php echo intval($totalLeadsBeforeStatusFilter); ?></div>
                            </div>
                            <div class="card-icon" aria-hidden="true">📊</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card stat-card bg-info text-white h-100">
                        <div class="card-body d-flex align-items-center justify-content-between p-3">
                            <div>
                                <div class="small opacity-90 mb-1">Tasa de booking de leads precalificados</div>
                                <div class="display-5 fw-bold"><?php echo $tasaConversion; ?>%</div>
                                <div class="small opacity-90 mt-2">
                                    <small><?php echo $leadsAgendadosTotal; ?> agendados de <?php echo $totalLeadsBeforeStatusFilter; ?></small>
                                </div>
                            </div>
                            <div class="card-icon" aria-hidden="true">🎯</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de gráfica -->
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Tendencia de Leads</h5>
                    <!-- Aquí irá la gráfica -->
                    <div id="leadsChart" style="min-height: 250px; width:100%;"></div>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Listado de Leads</h5>
                <div>
                    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#registroManualModal">
                        <i class="bi bi-person-plus"></i> Registrar Lead Manual
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registroManualWeddingModal">
                        <i class="bi bi-person-plus"></i> Registrar Lead Manual – Wedding Planner
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="leadsTable" class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th data-column="id">ID <i class="bi bi-funnel filter-icon" data-column="id"></i></th>

                                <th data-column="nombre">Nombre del lead <i class="bi bi-funnel filter-icon" data-column="nombre"></i></th>
                                <th data-column="fecha">¿Cuándo llegó el lead? <i class="bi bi-funnel filter-icon" data-column="fecha"></i></th>
                                <th class="origen-col text-center" data-column="origen">Origen <i class="bi bi-funnel filter-icon" data-column="origen"></i></th>
                                <th class="text-center" data-column="medio">Medio <i class="bi bi-funnel filter-icon" data-column="medio"></i></th>
                                <th class="text-center" data-column="estatus">Estatus <i class="bi bi-funnel filter-icon" data-column="estatus"></i></th>
                                <th data-column="consentimiento">Consentimiento de contacto <i class="bi bi-funnel filter-icon" data-column="consentimiento"></i></th>
                                <th data-column="boda">¿Dónde es la boda? <i class="bi bi-funnel filter-icon" data-column="boda"></i></th>
                                <?php if ($tipoUsuario != "4"): ?>
                                    <th class="assign-col" data-column="asignado">Asignar Pre Q <i class="bi bi-funnel filter-icon" data-column="asignado"></i></th>
                                    <th>Script Pre Q</th>
                                    <th>Correo 1</th>
                                    <th>Correo 2</th>
                                    <th class="text-center">Llamada</th>
                                    <th class="text-center">WhatsApp IA</th>
                                    <th>Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredLeads as $lead): ?>
                                <?php // Propagar bandera de agendado/scheduled para uso en el modal (JS, JSON) ?>
                                <?php $rowScheduledKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']); $lead['scheduled'] = isset($scheduledLeadsMap[$rowScheduledKey]) ? 1 : 0; ?>
                                <?php // Determinar estatus del lead usando los mapas calculados anteriormente ?>
                                <?php $rowStatusKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']); $lead['estatus'] = isset($leadStatusMap[$rowStatusKey]) ? $leadStatusMap[$rowStatusKey] : 'lead'; ?>
                                <tr id="lead-row-<?php echo $lead['id']; ?>-<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-lead-id="<?php echo intval($lead['id']); ?>"
                                    data-tabla="<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-full-name="<?php echo htmlspecialchars($lead['full_name'] ?? ''); ?>"
                                    data-when-married="<?php echo htmlspecialchars($lead['when_are_you_getting_married_'] ?? ''); ?>"
                                    data-email="<?php echo htmlspecialchars($lead['email'] ?? ''); ?>">
                                    <td data-column="id"><?php echo htmlspecialchars($lead['id'] ?? ''); ?></td>
                                    <td data-column="nombre"><?php echo htmlspecialchars($lead['full_name'] ?? ''); ?></td>
                                    <td data-column="fecha"><?php echo htmlspecialchars(formatCreatedTime($lead['created_time'] ?? '')); ?></td>
                                    <td class="origen-col" data-column="origen">
<?php
$cname = $lead['campaign_name'] ?? '';

// C + número + punto   Ó   E + número + guion
if (preg_match('/^(c(\d+)\.|e(\d+)-)/i', $cname, $matches)) {

    if (!empty($matches[2])) {
        // Caso C
        echo 'C' . $matches[2];
    } elseif (!empty($matches[3])) {
        // Caso E
        echo 'E' . $matches[3];
    }

} else {
    echo htmlspecialchars($cname);
}
?>
</td>
                                    <td class="text-center align-middle" data-column="medio">
                                        <?php echo htmlspecialchars($lead['form_name'] ?? $lead['platform'] ?? ''); ?>
                                    </td>
                                    <?php
                                        // Normalize and prepare display for estatus like in consulta_post_leads.php
                                        $statusRaw = isset($lead['estatus']) ? trim((string) $lead['estatus']) : '';
                                        $statusLower = $statusRaw !== '' ? mb_strtolower($statusRaw, 'UTF-8') : '';
                                        $statusDisplay = $statusLower !== '' ? ucfirst($statusLower) : '';
                                        $statusClass = 'status-default';
                                        if ($statusLower === 'agendado') {
                                            $statusClass = 'status-agendado';
                                        } elseif ($statusLower === 'fantasma') {
                                            $statusClass = 'status-fantasma';
                                        } elseif ($statusLower === 'atendido') {
                                            $statusClass = 'status-atendido';
                                        } elseif ($statusLower === 'muerto') {
                                            $statusClass = 'status-muerto';
                                        } elseif ($statusLower === 'cliente') {
                                            $statusClass = 'status-cliente';
                                        }
                                    ?>
                                    <td class="text-center align-middle" data-column="estatus"><span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusDisplay ?: 'Lead'); ?></span></td>
                                    <td class="align-middle" data-column="consentimiento">
                                        <?php
                                            $consentRaw = $lead['are_you_ok_with_us_contacting_you__or__did_you_want_to_contact_u'] ?? '';
                                            if ($consentRaw !== '' && $consentRaw !== null) {
                                                // Quitar guiones bajos y normalizar espacios para presentar de forma legible
                                                $consentDisplay = preg_replace('/\s+/', ' ', str_replace('_', ' ', $consentRaw));
                                                echo htmlspecialchars($consentDisplay);
                                            } else {
                                                echo 'No aplica';
                                            }
                                        ?>
                                    </td>
                                    <td data-column="boda"><?php
                                    // Primero intentar obtener del lead original
                                    $weddingLocation = $lead['where_are_you_getting_married_'] ?? $lead['where_is_your_marriage_taking_place_'] ?? '';

                                    // Si está vacío o es N/A, buscar en contact_form
                                    if (empty($weddingLocation) || $weddingLocation === 'N/A') {
                                        $locationKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);
                                        if (isset($weddingLocationsMap[$locationKey])) {
                                            $weddingLocation = $weddingLocationsMap[$locationKey];
                                        } else {
                                            $weddingLocation = 'N/A';
                                        }
                                    }
                                    echo htmlspecialchars($weddingLocation);
                                    ?></td>
                                    <?php if ($tipoUsuario != "4"): ?>
                                        <td data-column="asignado">
                                            <select class="form-select form-select-sm assign-select"
                                                data-lead-id="<?php echo $lead['id']; ?>"
                                                data-tabla="<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                onchange="asignarUsuario(this)">
                                                <option value="">-- Asignar --</option>
                                                <!-- Opción manual agregada para Agente llamada (IA) -->
                                                <option value="99" <?php echo (isset($lead['usuario_asignado']) && intval($lead['usuario_asignado']) === 99) ? 'selected' : ''; ?>>Agente de llamada (IA)</option>
                                                <!-- Opción manual agregada para Agente whatsapp (IA) -->
                                                <option value="100" <?php echo (isset($lead['usuario_asignado']) && intval($lead['usuario_asignado']) === 100) ? 'selected' : ''; ?>>Agente de whatsapp (IA)</option>
                                                <?php foreach ($vendedores as $v): ?>
                                                    <option value="<?php echo $v['id']; ?>" <?php echo (isset($lead['usuario_asignado']) && $lead['usuario_asignado'] == $v['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($v['nombre'] . ' ' . $v['apepat']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <?php if ($lead['tabla_origen'] === 'organic_leads'): ?>
                                                <button class="btn btn-secondary btn-sm" disabled>Ver Script</button>
                                            <?php else: ?>
                                                <button class="btn btn-primary btn-sm" onclick="verScript()">Ver Script</button>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            // Verificar si es organic_leads y ya agendó
                                            $organicScheduledKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']);
                                            $organicScheduled = isset($scheduledLeadsMap[$organicScheduledKey]);
                                            // Propagar bandera de agendado al objeto lead para uso en el modal (JS)
                                            $lead['scheduled'] = $organicScheduled ? 1 : 0;
                                            ?>
                                            <?php if ($lead['tabla_origen'] === 'organic_leads'): ?>
                                                <?php if ($organicScheduled): ?>
                                                    <button class="btn btn-success btn-sm d-block w-100" disabled>Ya agendó</button>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-sm d-block w-100" disabled>N/A</button>
                                                <?php endif; ?>
                                            <?php elseif ($lead['correo_uno_enviado'] == 1): ?>
                                                <?php
                                                $keyOpen1 = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']) . '|1';
                                                $opened1 = isset($opensMap[$keyOpen1]);
                                                ?>
                                                <?php if ($opened1): ?>
                                                    <button class="btn btn-success btn-sm d-block w-100" disabled title="Enviado y abierto">Correo 1
                                                        Enviado <br> <span class="small" style="font-weight: 700;">visto</span></button>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-sm d-block w-100" disabled
                                                        title="Enviado (no abierto)">Correo 1 Enviado</button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button class="btn btn-primary btn-sm d-block w-100"
                                                    onclick="enviarPrimerCorreo(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($lead['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($lead['when_are_you_getting_married_'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($lead['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')">Enviar
                                                    Correo 1</button>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($lead['tabla_origen'] === 'organic_leads'): ?>
                                                <?php if ($organicScheduled): ?>
                                                    <button class="btn btn-success btn-sm d-block w-100" disabled>Ya agendó</button>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-sm d-block w-100" disabled>N/A</button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php
                                                // Verificar si ya agendó: existe en contact_form con form_name + original_lead_id
                                                $scheduled = isset($scheduledLeadsMap[$organicScheduledKey]);
                                                ?>
                                                <?php if ($scheduled): ?>
                                                    <button class="btn btn-success btn-sm d-block w-100" disabled>Ya agendó</button>
                                                <?php elseif ($lead['correo_dos_enviado'] == 1): ?>
                                                    <?php
                                                    $keyOpen2 = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']) . '|2';
                                                    $opened2 = isset($opensMap[$keyOpen2]);
                                                    ?>
                                                    <?php if ($opened2): ?>
                                                        <button class="btn btn-success btn-sm d-block w-100" disabled title="Enviado y abierto">Correo 2
                                                            Enviado <br> <span class="small" style="font-weight: 700;">visto</span></button>
                                                    <?php else: ?>
                                                        <button class="btn btn-secondary btn-sm d-block w-100" disabled
                                                            title="Enviado (no abierto)">Correo 2 Enviado</button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button class="btn btn-primary btn-sm d-block w-100"
                                                        onclick="enviarSegundoCorreo(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($lead['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($lead['when_are_you_getting_married_'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($lead['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>')">Enviar
                                                        Correo 2</button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Nueva columna: Estado de la llamada (botón grande deshabilitado) -->
                                        <td class="text-center align-middle">
                                            <?php
                                                // Mostrar N/A si no está asignado al agente de llamadas (usuario 99)
                                                $assignedAgent = isset($lead['usuario_asignado']) ? intval($lead['usuario_asignado']) : 0;
                                                $callText = 'N/A';
                                                $callClass = 'btn-secondary';
                                                if ($assignedAgent === 99) {
                                                    $callStatus = isset($lead['estatus_llamada']) ? intval($lead['estatus_llamada']) : 0;
                                                    $callText = 'Llamada pendiente (IA)';
                                                    $callClass = 'btn-secondary';
                                                    if ($callStatus === 1) {
                                                        $callText = 'Llamado (IA)';
                                                        $callClass = 'btn-primary';
                                                    } elseif ($callStatus === 2) {
                                                        $callText = 'Ya agendó (IA)';
                                                        $callClass = 'btn-success';
                                                    } elseif ($callStatus === 3) {
                                                        $callText = 'No agendó (IA)';
                                                        $callClass = 'btn-danger';
                                                    }
                                                }
                                            ?>
                                            <button class="btn <?php echo $callClass; ?> btn-sm d-block w-100" disabled title="Estado de llamada: <?php echo htmlspecialchars($callText ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($callText ?? '', ENT_QUOTES, 'UTF-8'); ?></button>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php
                                                // WhatsApp IA column: mostrar N/A si no está asignado a Agente WhatsApp (100)
                                                $whatsappText = 'N/A';
                                                $whatsappClass = 'btn-secondary';
                                                if ($assignedAgent === 100) {
                                                    $waStatus = isset($lead['estatus_whatsapp']) ? intval($lead['estatus_whatsapp']) : 0;
                                                    if ($waStatus === 0) {
                                                        $whatsappText = 'WhatsApp pendiente (IA)';
                                                        $whatsappClass = 'btn-secondary';
                                                    } elseif ($waStatus === 1) {
                                                        $whatsappText = 'Ya agendó (IA)';
                                                        $whatsappClass = 'btn-success';
                                                    } elseif ($waStatus === 2) {
                                                        $whatsappText = 'No agendó (IA)';
                                                        $whatsappClass = 'btn-danger';
                                                    } else {
                                                        // Valor desconocido: mostrar pendiente por seguridad
                                                        $whatsappText = 'WhatsApp pendiente (IA)';
                                                        $whatsappClass = 'btn-secondary';
                                                    }
                                                }
                                            ?>
                                            <button class="btn <?php echo $whatsappClass; ?> btn-sm d-block w-100" disabled title="Estado de WhatsApp: <?php echo htmlspecialchars($whatsappText ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($whatsappText ?? '', ENT_QUOTES, 'UTF-8'); ?></button>
                                        </td>
                                        <td class="gap-2">
                                                <?php if ($lead['tabla_origen'] === 'organic_leads'): ?>
                                                <?php if ($organicScheduled): ?>
                                                    <!-- Ya agendó: solo mostrar badge -->
                                                    <span class="badge bg-success me-2">Ya agendó</span>
                                                <?php else: ?>
                                                    <?php $cleanPhone = preg_replace('/\D/', '', $lead['phone'] ?? ''); ?>
                                                    <!-- Para organic_leads que NO han agendado: mostrar botón de ver link y WhatsApp si hay teléfono -->
                                                    <button class="btn btn-info btn-sm mb-2" title="Ver Link del Formulario"
                                                        style="background-color: #5e543f; color: white; border: 1px solid #5e543f; margin-right: 5px;"
                                                        onclick="verLinkFormulario('<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', <?php echo $lead['id']; ?>)"><i
                                                            class="bi bi-link-45deg"></i> Ver Link</button>
                                                    <?php if (!empty($cleanPhone) && strlen($cleanPhone) >= 8): ?>
                                                        <?php if (isset($lead['whatsapp_enviado']) && $lead['whatsapp_enviado'] == 1): ?>
                                                            <button class="btn btn-success btn-sm mb-2 whatsapp-btn" title="WhatsApp enviado" style="background-color:#25D366;color:white;border: 1px solid #25D366; margin-right:5px;"><i class="bi bi-whatsapp"></i> WhatsApp<br/><small class="text-muted">enviado</small></button>
                                                        <?php else: ?>
                                                            <button class="btn btn-success btn-sm mb-2 whatsapp-btn" title="Enviar por WhatsApp" style="background-color:#25D366;color:white;border: 1px solid #25D366; margin-right:5px;" onclick='sendWhatsApp(this, <?php echo json_encode($lead['tabla_origen']); ?>, <?php echo $lead['id']; ?>, <?php echo json_encode($cleanPhone); ?>, <?php echo json_encode($lead['full_name'] ?? ''); ?>)'><i class="bi bi-whatsapp"></i> WhatsApp</button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php // For non-organic leads, show 'Ver Link' if not scheduled ?>
                                                <?php $cleanPhone = preg_replace('/\D/', '', $lead['phone'] ?? ''); ?>
                                                <?php if (empty($lead['scheduled']) || $lead['scheduled'] == 0): ?>
                                                    <button class="btn btn-info btn-sm mb-2" title="Ver Link del Formulario"
                                                        style="background-color: #5e543f; color: white; border: 1px solid #5e543f; margin-right: 5px;"
                                                        onclick="verLinkFormulario('<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', <?php echo $lead['id']; ?>)"><i class="bi bi-link-45deg"></i> Ver Link</button>
                                                    <?php if (!empty($cleanPhone) && strlen($cleanPhone) >= 8): ?>
                                                        <?php if (isset($lead['whatsapp_enviado']) && $lead['whatsapp_enviado'] == 1): ?>
                                                            <button class="btn btn-success btn-sm mb-2 whatsapp-btn" title="WhatsApp enviado" style="background-color:#25D366;color:white;border: 1px solid #25D366; margin-right:5px;"><i class="bi bi-whatsapp"></i> WhatsApp<br/><small class="text-muted">enviado</small></button>
                                                        <?php else: ?>
                                                            <button class="btn btn-success btn-sm mb-2 whatsapp-btn" title="Enviar por WhatsApp" style="background-color:#25D366;color:white;border: 1px solid #25D366; margin-right:5px;" onclick='sendWhatsApp(this, <?php echo json_encode($lead['tabla_origen']); ?>, <?php echo $lead['id']; ?>, <?php echo json_encode($cleanPhone); ?>, <?php echo json_encode($lead['full_name'] ?? ''); ?>)'><i class="bi bi-whatsapp"></i> WhatsApp</button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php 
                                                // Botón de historial WhatsApp si está asignado al agente 100
                                                $assignedToWhatsApp = isset($lead['usuario_asignado']) && intval($lead['usuario_asignado']) === 100;
                                                if ($assignedToWhatsApp): 
                                                ?>
                                                    <button class="btn btn-info btn-sm mb-2 wa-conversation-btn" data-wa-conversation="1" data-lead-id="<?php echo (int)$lead['id']; ?>" data-tabla="<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" title="Ver Conversación de WhatsApp"
                                                        onclick='verHistorialWhatsApp(<?php echo json_encode($lead['id']); ?>, <?php echo json_encode($lead['tabla_origen']); ?>)'>
                                                        <i class="bi bi-whatsapp"></i> Conversación
                                                        <span class="badge bg-danger rounded-pill ms-2 d-none wa-unread-badge" title="Mensaje nuevo / requiere atención" aria-hidden="true"></span>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-info btn-sm mb-2" title="Ver Más"
                                                    style="background-color: #5e543f; color: white; border: 1px solid #5e543f; margin-right: 5px;"
                                                    onclick='verMas(<?php echo json_encode($lead); ?>)'><i
                                                        class="bi bi-plus"></i></button>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="verMasModal" tabindex="-1" aria-labelledby="verMasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verMasModalLabel">Detalles del Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be populated by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Modal grande para conversación de WhatsApp -->
<div class="modal fade" id="whatsappHistorialModal" tabindex="-1" aria-labelledby="whatsappHistorialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="whatsappHistorialModalLabel">Conversación de WhatsApp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="whatsappHistorialBody">
                <div class="wa-wrapper">
                    <div class="wa-topbar">
                        <div class="wa-title">
                            <i class="bi bi-whatsapp wa-icon" aria-hidden="true"></i>
                            <div class="wa-title-text">
                                <div class="wa-lead-name" id="waLeadName">Conversación de WhatsApp</div>
                                <div class="wa-lead-phone" id="waLeadPhone"></div>
                            </div>
                        </div>
                        <div class="wa-meta" id="waMeta"></div>
                    </div>
                    <div class="wa-chat" id="waChat"></div>
                    <div class="wa-composer" aria-label="Enviar mensaje (deshabilitado)">
                        <label class="btn btn-outline-secondary wa-clip disabled" title="Adjuntar archivo (deshabilitado)">
                            <i class="bi bi-paperclip" aria-hidden="true"></i>
                            <input type="file" class="d-none" disabled>
                        </label>
                        <input type="text" class="form-control wa-input" placeholder="Escribe un mensaje" disabled>
                        <button type="button" class="btn btn-success wa-send" disabled title="Enviar (deshabilitado)">
                            <i class="bi bi-send" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para registro manual de leads -->
<div class="modal fade" id="registroManualModal" tabindex="-1" aria-labelledby="registroManualModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="registroManualModalLabel">Registrar Lead Manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formLeadManual">
                    <div class="mb-3">
                        <label for="leadNombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="leadNombre" name="nombre" required>
                        <div class="invalid-feedback">El nombre es obligatorio</div>
                    </div>
                    <div class="mb-3">
                        <label for="leadCorreo" class="form-label">Correo <span class="text-muted">(opcional)</span></label>
                        <input type="email" class="form-control" id="leadCorreo" name="correo">
                        <div class="invalid-feedback">Ingrese un correo válido</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-4">
                            <label for="leadCountryCode" class="form-label">Código país <span class="text-muted">(opcional)</span></label>
                            <select class="form-select" id="leadCountryCode" name="country_code">
                                <option value="">Seleccionar...</option>
                                <!-- options populated dynamically -->
                            </select>
                            <div class="invalid-feedback">Seleccione el código de país si incluye teléfono</div>
                        </div>
                        <div class="col-8">
                            <label for="leadTelefono" class="form-label">Teléfono <br><span class="text-muted">(opcional)</span></label>
                            <input type="text" class="form-control" id="leadTelefono" name="telefono" placeholder="Ej: 4771232323 (10 dígitos)">
                            <div class="invalid-feedback">El teléfono debe tener 10 dígitos (solo números)</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="leadOrigen" class="form-label">Origen <span class="text-danger">*</span></label>
                        <select class="form-select" id="leadOrigen" name="campaign_name" required>
                            <option value="">Seleccionar...</option>
                            <option value="b1 (USA)">b1 (USA)</option>
                            <option value="b2 (MX)">b2 (MX)</option>
                            <option value="ig organico">ig organico</option>
                            <option value="prospectos">prospectos</option>
                            <option value="wp">wp</option>
                          <option value="whatsapp">whatsapp</option>
                           <option value="mail">mail</option>

                        </select>
                        <div class="invalid-feedback">El origen es obligatorio</div>
                    </div>
                    <div class="mb-3">
                        <label for="leadMedio" class="form-label">Medio <span class="text-danger">*</span></label>
                        <select class="form-select" id="leadMedio" name="platform" required>
                            <option value="">Seleccionar...</option>
                            <option value="ig usa">ig usa</option>
                            <option value="ig mexico">ig mexico</option>
                            <option value="ig">ig</option>
                            <option value="prospectos">prospectos</option>
                            <option value="wp">wp</option>
                            <option value="fb">fb</option>
                            <option value="whatsapp">whatsapp</option>
                             <option value="mail">mail</option>
                        </select>
                        <div class="invalid-feedback">El medio es obligatorio</div>
                    </div>
                    <div class="mb-3">
                        <label for="leadFechaRegistro" class="form-label">Fecha de registro <span class="text-muted">(opcional - si no se selecciona, se usará la fecha actual)</span></label>
                        <input type="datetime-local" class="form-control" id="leadFechaRegistro" name="fecha_registro">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnGuardarLeadManual">
                    <i class="bi bi-save"></i> Guardar Lead
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para registro manual de Wedding Planner -->
<div class="modal fade" id="registroManualWeddingModal" tabindex="-1" aria-labelledby="registroManualWeddingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="registroManualWeddingModalLabel">Registrar Lead Manual – Wedding Planner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formWPManual">
                    <div class="mb-3">
                        <label for="wpCampaignName" class="form-label">Nombre de Wedding Planner <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="wpCampaignName" name="campaign_name" required>
                        <div class="invalid-feedback">El nombre del Wedding Planner es obligatorio</div>
                    </div>
                    <div class="mb-3">
                        <label for="wpWeddingLocation" class="form-label">Lugar de la boda</label>
                        <input type="text" class="form-control" id="wpWeddingLocation" name="wedding_location">
                    </div>
                    <div class="mb-3">
                        <label for="wpWeddingDate" class="form-label">Fecha de la boda</label>
                        <input type="date" class="form-control" id="wpWeddingDate" name="wedding_date">
                    </div>
                    <div class="mb-3">
                        <label for="wpFullName" class="form-label">Nombre de los novios<span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="wpFullName" name="full_name" required>
                        <div class="invalid-feedback">El nombre de los novios es obligatorio</div>
                    </div>
                    <div class="mb-3">
                        <label for="wpAsesor" class="form-label">Asesor <span class="text-danger">*</span></label>
                        <select class="form-select" id="wpAsesor" name="asesor" required>
                            <option value="">Seleccionar asesor</option>
                            <?php foreach ($agentes as $agente): ?>
                                <option value="<?php echo intval($agente['id']); ?>"><?php echo htmlspecialchars($agente['nombre'] . ' ' . $agente['apepat']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">El asesor es obligatorio</div>
                    </div>



                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarWeddingPlanner"><i class="bi bi-save"></i> Guardar Wedding Planner</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de éxito con link del formulario -->
<div class="modal fade" id="linkFormularioModal" tabindex="-1" aria-labelledby="linkFormularioModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="linkFormularioModalLabel"><i class="bi bi-check-circle"></i> Lead Registrado
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-center mb-3">El lead ha sido registrado correctamente.</p>
                <div class="mb-3">
                    <label for="linkFormulario" class="form-label">Link del formulario de agenda:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="linkFormulario" readonly>
                        <button class="btn btn-outline-primary" type="button" id="btnCopiarLink" title="Copiar link">
                            <i class="bi bi-clipboard"></i> Copiar
                        </button>
                    </div>
                </div>
                <div id="copiadoAlert" class="alert alert-success d-none" role="alert">
                    <i class="bi bi-check2"></i> ¡Link copiado al portapapeles!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php

include 'footer.php';

?>


</body>

</html>

<style>
    /* WhatsApp-like view (contained inside modal only) */
    .wa-wrapper {
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        overflow: hidden;
        height: 72vh;
        display: flex;
        flex-direction: column;
        background: #e5ddd5;
    }

    .wa-topbar {
        background: #f0f2f5;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        padding: 10px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .wa-title {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .wa-icon {
        font-size: 22px;
        color: #25d366;
    }

    .wa-title-text {
        min-width: 0;
    }

    .wa-lead-name {
        font-weight: 600;
        line-height: 1.15;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 56vw;
    }

    .wa-lead-phone {
        font-size: 12px;
        color: rgba(0, 0, 0, 0.6);
        line-height: 1.15;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 56vw;
    }

    .wa-meta {
        font-size: 12px;
        color: rgba(0, 0, 0, 0.55);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 30vw;
        text-align: right;
    }

    .wa-chat {
        flex: 1;
        overflow: auto;
        padding: 14px 12px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .wa-composer {
        background: #f0f2f5;
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        padding: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .wa-clip {
        width: 40px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        padding: 0;
    }

    .wa-clip i {
        display: block;
        line-height: 1;
        font-size: 18px;
    }

    .wa-input {
        border-radius: 12px;
    }

    .wa-send {
        width: 44px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        padding: 0;
    }

    .wa-send i {
        display: block;
        line-height: 1;
        font-size: 18px;
    }

    .wa-row {
        display: flex;
        width: 100%;
    }

    .wa-row.in {
        justify-content: flex-start;
    }

    .wa-row.out {
        justify-content: flex-end;
    }

    .wa-row.meta {
        justify-content: center;
    }

    .wa-bubble {
        max-width: 78%;
        padding: 10px 12px;
        border-radius: 10px;
        box-shadow: 0 1px 1px rgba(0, 0, 0, 0.08);
        white-space: pre-wrap;
        word-break: break-word;
        line-height: 1.35;
        font-size: 14px;
    }

    .wa-bubble.in {
        background: #ffffff;
        border-top-left-radius: 6px;
    }

    .wa-bubble.out {
        background: #dcf8c6;
        border-top-right-radius: 6px;
    }

    .wa-bubble.meta {
        background: rgba(255, 255, 255, 0.7);
        color: rgba(0, 0, 0, 0.7);
        font-size: 12px;
        box-shadow: none;
    }

    .wa-empty {
        color: rgba(0, 0, 0, 0.6);
        text-align: center;
        padding: 24px 12px;
    }

    .wa-loading {
        color: rgba(0, 0, 0, 0.6);
        text-align: center;
        padding: 24px 12px;
    }

    .wa-unread-badge {
        font-size: .65rem;
        line-height: 1;
        min-width: 1.1rem;
        text-align: center;
    }

    /* Column Filter Styles */
    .filter-icon {
        cursor: pointer;
        margin-left: 5px;
        font-size: 0.85rem;
        color: #6c757d;
        transition: color 0.2s;
    }

    .filter-icon:hover {
        color: #495057;
    }

    .filter-icon.active {
        color: #007bff;
        font-weight: bold;
    }

    .filter-dropdown {
        position: absolute;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        z-index: 1050;
        min-width: 250px;
        max-width: 400px;
        display: none;
    }

    .filter-dropdown.show {
        display: block;
    }

    .filter-dropdown-header {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #dee2e6;
        background-color: #f8f9fa;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .filter-dropdown-body {
        padding: 0.75rem 1rem;
        max-height: 300px;
        overflow-y: auto;
    }

    .filter-search {
        width: 100%;
        padding: 0.375rem 0.75rem;
        margin-bottom: 0.75rem;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        font-size: 0.875rem;
    }

    .filter-option {
        display: flex;
        align-items: center;
        padding: 0.375rem 0;
        cursor: pointer;
        user-select: none;
    }

    .filter-option:hover {
        background-color: #f8f9fa;
    }

    .filter-option input[type="checkbox"] {
        margin-right: 0.5rem;
        cursor: pointer;
    }

    .filter-option label {
        cursor: pointer;
        margin: 0;
        flex: 1;
        font-size: 0.9rem;
    }

    .filter-dropdown-footer {
        padding: 0.75rem 1rem;
        border-top: 1px solid #dee2e6;
        background-color: #f8f9fa;
        display: flex;
        gap: 0.5rem;
        justify-content: space-between;
    }

    .filter-btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        border-radius: 0.25rem;
    }

    thead th {
        position: relative;
        white-space: nowrap;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/locale/es.js"></script>

<!-- Note: jQuery, Bootstrap and DataTables are loaded globally in `footer.php` to avoid duplicate conflicting versions. -->
<script>
    // --- WhatsApp unread / attention badges ---
    var waUnreadCache = {};
    var waUnreadInFlight = {};

    function waKey(leadId, tablaOrigen) {
        return String(leadId) + '|' + String(tablaOrigen);
    }

    function waUpdateBadge(btn, status) {
        if (!btn) return;
        var badge = btn.querySelector('.wa-unread-badge');
        if (!badge) return;

        // Only show badges during human handoff mode.
        var handoffEnabled = !!(status && status.handoff && status.handoff.enabled);
        if (!handoffEnabled) {
            badge.textContent = '';
            badge.classList.add('d-none');
            return;
        }

        var unreadCount = status && typeof status.unreadCount === 'number' ? status.unreadCount : 0;
        var needsAttention = !!(status && status.needsAttention);

        var show = false;
        var text = '';
        if (unreadCount > 0) {
            show = true;
            text = unreadCount > 9 ? '9+' : String(unreadCount);
        } else if (needsAttention) {
            show = true;
            text = '!';
        }

        if (show) {
            badge.textContent = text;
            badge.classList.remove('d-none');
        } else {
            badge.textContent = '';
            badge.classList.add('d-none');
        }
    }

    function waFetchUnreadStatus(leadId, tablaOrigen, cb) {
        var key = waKey(leadId, tablaOrigen);
        var now = Date.now();
        if (waUnreadCache[key] && (now - waUnreadCache[key].t) < 8000) {
            cb && cb(waUnreadCache[key].status);
            return;
        }
        if (waUnreadInFlight[key]) return;
        waUnreadInFlight[key] = true;

        $.ajax({
            url: 'ajax/whatsapp_unread_status.php',
            type: 'POST',
            dataType: 'json',
            data: { leadId: leadId, tablaOrigen: tablaOrigen },
            success: function (resp) {
                if (resp && resp.success) {
                    waUnreadCache[key] = { t: Date.now(), status: resp };
                    cb && cb(resp);
                }
            },
            complete: function () {
                waUnreadInFlight[key] = false;
            }
        });
    }

    function waRefreshConversationButtons(scopeEl) {
        var scope = scopeEl || document;
        if (!scope.querySelectorAll) return;
        var btns = scope.querySelectorAll('.wa-conversation-btn[data-wa-conversation="1"]');
        if (!btns || !btns.length) return;

        btns.forEach(function (btn) {
            var leadId = btn.getAttribute('data-lead-id');
            var tablaOrigen = btn.getAttribute('data-tabla');
            if (!leadId || !tablaOrigen) return;
            waFetchUnreadStatus(leadId, tablaOrigen, function (status) {
                waUpdateBadge(btn, status);
            });
        });
    }

    function waMarkRead(leadId, tablaOrigen) {
        if (!leadId || !tablaOrigen) return;
        $.ajax({
            url: 'ajax/whatsapp_mark_read.php',
            type: 'POST',
            dataType: 'json',
            data: { leadId: leadId, tablaOrigen: tablaOrigen },
            complete: function () {
                try { delete waUnreadCache[waKey(leadId, tablaOrigen)]; } catch (e) {}
                waRefreshConversationButtons(document);
            }
        });
    }

    function escapeHtml(unsafe) {
        if (unsafe === undefined || unsafe === null) return '';
        return String(unsafe)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showErrorModalFromResponse(response, xhr) {
        var message = (response && (response.message || response.msg)) ? (response.message || response.msg) : 'Hubo un error al procesar la solicitud';
        var html = '<div style="text-align:left;">' + escapeHtml(message);
        if (response && response.mail_error) {
            html += '<hr><div><strong>Detalle:</strong><pre style="white-space:pre-wrap;">' + escapeHtml(response.mail_error) + '</pre></div>';
        }
        if (xhr && xhr.status) {
            html += '<hr><div><strong>HTTP:</strong> ' + xhr.status + ' ' + escapeHtml(xhr.statusText || '') + '</div>';
        }
        if (xhr && xhr.responseText) {
            // Try to parse JSON if present
            try {
                var parsed = JSON.parse(xhr.responseText);
                html += '<hr><div><strong>Body:</strong><pre style="white-space:pre-wrap;">' + escapeHtml(JSON.stringify(parsed, null, 2)) + '</pre></div>';
            } catch (e) {
                html += '<hr><div><strong>Body:</strong><pre style="white-space:pre-wrap;">' + escapeHtml(xhr.responseText) + '</pre></div>';
            }
        }
        html += '</div>';
        Swal.fire({
            title: 'Error',
            html: html,
            icon: 'error',
            width: '70%'
        });
        console.error('AJAX error details:', response, xhr);
    }
    // Mapa de medios por origen generado desde PHP
    var mediosPorOrigen = <?php echo json_encode($mediosPorOrigen); ?>;
    
    $(document).ready(function () {
        // Manejar cambio de origen para cargar medios dinámicamente
        $('#filterOrigen').on('change', function() {
            var origenSeleccionado = $(this).val();
            var $medioSelect = $('#filterMedio');
            var medioActual = $medioSelect.val();
            
            // Limpiar opciones de medio
            $medioSelect.empty();
            $medioSelect.append('<option value="">Todos</option>');
            
            if (origenSeleccionado === '') {
                // Si no hay origen seleccionado, mostrar todos los medios únicos
                var todosLosMedios = [];
                for (var origen in mediosPorOrigen) {
                    mediosPorOrigen[origen].forEach(function(medio) {
                        if (todosLosMedios.indexOf(medio) === -1) {
                            todosLosMedios.push(medio);
                        }
                    });
                }
                todosLosMedios.sort();
                todosLosMedios.forEach(function(medio) {
                    $medioSelect.append('<option value="' + medio + '">' + medio + '</option>');
                });
            } else {
                // Mostrar solo los medios del origen seleccionado
                if (mediosPorOrigen[origenSeleccionado]) {
                    var mediosDelOrigen = mediosPorOrigen[origenSeleccionado].slice().sort();
                    mediosDelOrigen.forEach(function(medio) {
                        $medioSelect.append('<option value="' + medio + '">' + medio + '</option>');
                    });
                }
            }
            
            // Intentar mantener el medio seleccionado si existe en las nuevas opciones
            if (medioActual && $medioSelect.find('option[value="' + medioActual + '"]').length > 0) {
                $medioSelect.val(medioActual);
            }
        });
        
        $('#leadsTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            // Preserve the server-side (PHP) ordering in the table and
            // avoid client-side reordering when cells are updated via AJAX.
            // Previously this used `order: [[6, 'desc']]` which caused rows
            // to jump to the end when certain columns were updated. Using an
            // empty order keeps rows in DOM order (the PHP-provided order).
            order: [],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ],
            responsive: true,
            drawCallback: function () {
                waRefreshConversationButtons(document);
            },
            columnDefs: [
                { orderable: true, targets: '_all' }
            ]
        });

        // Initial + periodic refresh so new inbound messages show a badge without reloading.
        setTimeout(function () {
            waRefreshConversationButtons(document);
        }, 800);

        setInterval(function () {
            waRefreshConversationButtons(document);
        }, 15000);

        // Inicializar atributo data-prev para poder revertir en caso de error
        $('.assign-select').each(function () {
            $(this).attr('data-prev', $(this).val());
        });

        // Highcharts: datos generados por PHP (serie con timestamps en ms)
        var chartSeries = <?php echo isset($seriesJson) ? $seriesJson : '[]'; ?>;

        // Calcular el máximo valor de leads
        var maxLeads = 0;
        chartSeries.forEach(function (point) {
            if (point[1] > maxLeads) maxLeads = point[1];
        });

        // Calcular un techo apropiado para el eje Y (redondear hacia arriba)
        var yAxisMax = Math.ceil(maxLeads * 1.15); // 15% más que el máximo
        if (yAxisMax < 10) yAxisMax = 10; // Mínimo de 10

        // Calcular intervalo de ticks dinámico
        var tickInterval = Math.ceil(yAxisMax / 10);
        if (tickInterval < 1) tickInterval = 1;

        Highcharts.chart('leadsChart', {
            chart: {
                type: 'line',
                backgroundColor: '#f8f9fa',
                borderRadius: 8
            },
            title: {
                text: 'Pre qualified por día',
                style: { fontSize: '16px', fontWeight: 'bold' }
            },
            xAxis: {
                type: 'datetime',
                title: { text: 'Fecha' },
                labels: {
                    rotation: -45,
                    style: { fontSize: '11px' },
                    formatter: function () {
                        return Highcharts.dateFormat('%e %b', this.value);
                    }
                },
                gridLineWidth: 1,
                gridLineColor: '#e0e0e0'
            },
            yAxis: {
                min: 0,
                max: yAxisMax,
                tickInterval: tickInterval,
                title: { text: 'Cantidad de Leads' },
                allowDecimals: false,
                gridLineColor: '#e0e0e0',
                labels: {
                    style: { fontSize: '11px' }
                }
            },
            plotOptions: {
                line: {
                    dataLabels: {
                        enabled: true,
                        formatter: function () {
                            return this.y > 0 ? this.y : '';
                        },
                        style: {
                            fontSize: '10px',
                            fontWeight: 'bold',
                            textOutline: 'none'
                        }
                    },
                    marker: {
                        enabled: true,
                        radius: 4
                    },
                    lineWidth: 2,
                    color: '#28a745' // green color for the chart line
                }
            },
            series: [{
                name: 'Leads',
                data: chartSeries,
                showInLegend: false
            }],
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.85)',
                style: { color: '#fff' },
                borderRadius: 8,
                formatter: function () {
                    return '<b>' + Highcharts.dateFormat('%A, %e de %B %Y', this.x) + '</b><br/>' +
                        '<span style="color:#90ee90">●</span> ' + this.series.name + ': <b>' + this.y + '</b>';
                }
            },
            credits: { enabled: false },
            responsive: {
                rules: [{
                    condition: { maxWidth: 500 },
                    chartOptions: {
                        xAxis: {
                            labels: {
                                rotation: -90,
                                style: { fontSize: '9px' }
                            }
                        },
                        plotOptions: {
                            line: {
                                dataLabels: { enabled: false }
                            }
                        }
                    }
                }]
            }
        });
    });

    <?php if ($tipoUsuario != "4"): ?>
        function enviarPrimerCorreo(id, tablaOrigen, fullName, whenMarried, email) {
            Swal.fire({
                title: '¿Enviar primer correo?',
                text: `Se enviará el primer correo a ${fullName} (${email})`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Enviando correo...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: 'enviar_primer_correo_lead.php',
                        type: 'POST',
                        data: {
                            id: id,
                            tabla_origen: tablaOrigen,
                            full_name: fullName,
                            when_are_you_getting_married: whenMarried,
                            email: email
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire(
                                    '¡Enviado!',
                                    'El primer correo ha sido enviado exitosamente.',
                                    'success'
                                );

                                // Actualizar el botón usando la API de DataTables
                                var table = $('#leadsTable').DataTable();
                                var rowNode = $(`#lead-row-${id}-${tablaOrigen}`)[0];
                                if (rowNode) {
                                    // columna 9 corresponde a la columna "Correo 1"
                                    var cellIndex = 9;
                                    try {
                                        // Mark as sent but not yet opened (grey) after sending
                                        table.cell(rowNode, cellIndex).data('<button class="btn btn-secondary btn-sm d-block w-100" disabled title="Enviado (no abierto)">Correo 1 Enviado</button>');
                                        table.draw(false);
                                    } catch (e) {
                                        // fallback to direct DOM update if DataTables call fails
                                        const row = $(`#lead-row-${id}-${tablaOrigen}`);
                                        const correo1Cell = row.find('td:eq(9)');
                                        correo1Cell.html('<button class="btn btn-secondary btn-sm d-block w-100" disabled title="Enviado (no abierto)">Correo 1 Enviado</button>');
                                    }
                                }
                            } else {
                                showErrorModalFromResponse(response);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Error:', error);
                            showErrorModalFromResponse(null, xhr);
                        }
                    });
                }
            });
        }

        function enviarSegundoCorreo(id, tablaOrigen, fullName, whenMarried, email) {
            Swal.fire({
                title: '¿Enviar segundo correo?',
                text: `Se enviará el segundo correo a ${fullName} (${email})`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Enviando correo...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: 'enviar_segundo_correo_lead.php',
                        type: 'POST',
                        data: {
                            id: id,
                            tabla_origen: tablaOrigen,
                            full_name: fullName,
                            when_are_you_getting_married: whenMarried,
                            email: email
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire(
                                    '¡Enviado!',
                                    'El segundo correo ha sido enviado exitosamente.',
                                    'success'
                                );

                                // Actualizar el botón usando la API de DataTables
                                var table = $('#leadsTable').DataTable();
                                var rowNode = $(`#lead-row-${id}-${tablaOrigen}`)[0];
                                if (rowNode) {
                                    // columna 10 corresponde a la columna "Correo 2"
                                    var cellIndex = 10;
                                    try {
                                        // Mark as sent but not yet opened (grey) after sending
                                        table.cell(rowNode, cellIndex).data('<button class="btn btn-secondary btn-sm d-block w-100" disabled title="Enviado (no abierto)">Correo 2 Enviado</button>');
                                        table.draw(false);
                                    } catch (e) {
                                        // fallback to direct DOM update if DataTables call fails
                                        const row = $(`#lead-row-${id}-${tablaOrigen}`);
                                        const correo2Cell = row.find('td:eq(10)');
                                        correo2Cell.html('<button class="btn btn-secondary btn-sm d-block w-100" disabled title="Enviado (no abierto)">Correo 2 Enviado</button>');
                                    }
                                }
                            } else {
                                showErrorModalFromResponse(response);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Error:', error);
                            showErrorModalFromResponse(null, xhr);
                        }
                    });
                }
            });
        }

        function descartarLead(id, tablaOrigen) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Este lead será marcado como descartado",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, descartar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('descartarLead called with', id, tablaOrigen);
                    $.ajax({
                        url: 'descartar_lead.php',
                        type: 'POST',
                        data: {
                            id: id,
                            tabla_origen: tablaOrigen
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire(
                                    '¡Descartado!',
                                    'El lead ha sido descartado exitosamente.',
                                    'success'
                                );

                                // Remover la fila de la tabla sin recargar
                                var table = $('#leadsTable').DataTable();
                                // Prefer data-attributes to locate the row (avoids issues when tablaOrigen contains special chars)
                                var $row = $('tr[data-lead-id="' + id + '"][data-tabla="' + tablaOrigen + '"]');
                                if ($row.length) {
                                    var row = table.row($row);
                                    row.remove().draw();
                                } else {
                                    // Fallback to id-based selector
                                    try {
                                        var row2 = table.row($('#lead-row-' + id + '-' + tablaOrigen));
                                        row2.remove().draw();
                                    } catch (e) {
                                        console.warn('Could not find row to remove for', id, tablaOrigen);
                                    }
                                }
                                // Close the 'Ver Más' modal if open
                                try { var verMasModal = bootstrap.Modal.getInstance(document.getElementById('verMasModal')); if (verMasModal) verMasModal.hide(); } catch (e) {}
                            } else {
                                Swal.fire(
                                    'Error',
                                    response.message || 'No se pudo descartar el lead',
                                    'error'
                                );
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Error:', error);
                            Swal.fire(
                                'Error',
                                'Hubo un problema al procesar la solicitud',
                                'error'
                            );
                        }
                    });
                }
            });
        }

        function asignarUsuario(selectElem) {
            var usuarioId = selectElem.value;
            var leadId = $(selectElem).data('lead-id');
            var tabla = $(selectElem).data('tabla');
            var $sel = $(selectElem);
            var prev = $sel.attr('data-prev') || '';

            // Enviar sin confirmación y mostrar un pequeño toast; si falla, revertir la selección
            $sel.prop('disabled', true);
            $.ajax({
                url: 'asignar_usuario_lead.php',
                type: 'POST',
                data: {
                    id: leadId,
                    tabla_origen: tabla,
                    usuario_id: usuarioId
                },
                dataType: 'json',
                success: function (resp) {
                    console.log(resp);
                    if (resp.success) {
                        // Actualizar data-prev al nuevo valor
                        $sel.attr('data-prev', usuarioId);
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Vendedor asignado',
                            showConfirmButton: false,
                            timer: 1400
                        });
                        // Mostrar advertencia si la asignación funcionó pero no se pudo enviar correo
                        if (resp.mail_sent === false) {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'warning',
                                title: 'Asignado, pero no se pudo enviar notificación por correo',
                                showConfirmButton: false,
                                timer: 2200
                            });
                            if (resp.mail_error) console.warn('Error enviando notificación:', resp.mail_error);
                        }
                    } else {
                        // Revertir a valor anterior
                        $sel.val(prev);
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'error',
                            title: resp.message || 'No se pudo asignar el vendedor',
                            showConfirmButton: false,
                            timer: 2200
                        });
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);

                    $sel.val(prev);
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        title: 'Error en la petición al servidor',
                        showConfirmButton: false,
                        timer: 2200
                    });
                },
                complete: function () {
                    $sel.prop('disabled', false);
                }
            });
        }
    <?php endif; ?>

    function reestablecerCorreos(id, tablaOrigen) {
        Swal.fire({
            title: '¿Reestablecer correos? ',
            text: 'Esto establecerá los estados de envío de correos a no enviados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, reestablecer',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;
            Swal.fire({
                title: 'Reestableciendo...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: 'reestablecer_correos_lead.php',
                type: 'POST',
                data: { id: id, tabla_origen: tablaOrigen },
                dataType: 'json',
                success: function (resp) {
                    if (resp.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Reestablecido',
                            text: 'Los estados de envío se reestablecieron correctamente',
                            showConfirmButton: false,
                            timer: 1500
                        });

                        // Update only the columns that the server claims to have updated
                        var table = $('#leadsTable').DataTable();
                        var rowNode = $(`#lead-row-${id}-${tablaOrigen}`)[0];
                        var $row = $(`#lead-row-${id}-${tablaOrigen}`);
                        var fullName = $row.data('full-name') || '';
                        var whenMarried = $row.data('when-married') || '';
                        var email = $row.data('email') || '';

                        // Build new buttons using JSON.stringify to ensure proper escaping
                        var correo1Btn = `<button class="btn btn-primary btn-sm d-block w-100" onclick='enviarPrimerCorreo(${id}, ${JSON.stringify(tablaOrigen)}, ${JSON.stringify(fullName)}, ${JSON.stringify(whenMarried)}, ${JSON.stringify(email)})'>Enviar Correo 1</button>`;
                        var correo2Btn = `<button class="btn btn-primary btn-sm d-block w-100" onclick='enviarSegundoCorreo(${id}, ${JSON.stringify(tablaOrigen)}, ${JSON.stringify(fullName)}, ${JSON.stringify(whenMarried)}, ${JSON.stringify(email)})'>Enviar Correo 2</button>`;

                        try {
                            if (rowNode) {
                                // Column index mapping: 8 = correo 1, 9 = correo 2
                                var cellIdx1 = 9;
                                var cellIdx2 = 10;
                                // Update only the columns that existed and were updated server-side
                                if (Array.isArray(resp.updated) && resp.updated.length > 0) {
                                    if (resp.updated.indexOf('correo_uno_enviado') !== -1 || resp.updated.indexOf('fecha_envio_correo_uno') !== -1) {
                                        table.cell(rowNode, cellIdx1).data(correo1Btn);
                                    }
                                    if (resp.updated.indexOf('correo_dos_enviado') !== -1 || resp.updated.indexOf('fecha_envio_correo_dos') !== -1) {
                                        table.cell(rowNode, cellIdx2).data(correo2Btn);
                                    }
                                } else {
                                    // If no `updated` info, fall back to updating both
                                    table.cell(rowNode, cellIdx1).data(correo1Btn);
                                    table.cell(rowNode, cellIdx2).data(correo2Btn);
                                }
                                table.draw(false);
                                // Close the 'Ver Más' modal if open
                                try { var verMasModal = bootstrap.Modal.getInstance(document.getElementById('verMasModal')); if (verMasModal) verMasModal.hide(); } catch (e) {}
                            } else {
                                // fallback: direct DOM (update only columns that exist)
                                var correo1Cell = $row.find('td:eq(9)');
                                var correo2Cell = $row.find('td:eq(10)');
                                if (Array.isArray(resp.updated) && resp.updated.length > 0) {
                                    if (resp.updated.indexOf('correo_uno_enviado') !== -1 || resp.updated.indexOf('fecha_envio_correo_uno') !== -1) {
                                        correo1Cell.html(correo1Btn);
                                    }
                                    if (resp.updated.indexOf('correo_dos_enviado') !== -1 || resp.updated.indexOf('fecha_envio_correo_dos') !== -1) {
                                        correo2Cell.html(correo2Btn);
                                    }
                                } else {
                                    correo1Cell.html(correo1Btn);
                                    correo2Cell.html(correo2Btn);
                                }
                            }
                        } catch (e) {
                            // As a fallback, just refresh the page silently if DOM update fails
                            console.warn('No se pudo actualizar la tabla dinámicamente. Error:', e);
                        }
                    } else {
                        Swal.fire('Error', resp.message || 'No se pudo reestablecer', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    Swal.fire('Error', 'Hubo un problema al procesar la solicitud', 'error');
                }
            });
        });
    }

    // Delegated click handler for descartar buttons (ensures click works even when buttons are injected into modal)
    $(document).on('click', '.btn-descartar', function (e) {
        var id = $(this).data('id');
        var tabla = $(this).data('tabla');
        try { descartarLead(id, tabla); } catch (err) { console.error('Error calling descartarLead:', err, id, tabla); }
    });

    function verMas(lead) {
        // Build the details table for the lead
        let detailsHeaderHtml = '<div class="modal-section-header">' +
                                '<h6 class="modal-section-title">Información del Lead</h6>' +
                                '<div class="modal-section-subtitle">Datos registrados y estado actual</div>' +
                                '</div>';
        let content = '<table class="table table-striped">';
        const consentField = 'are_you_ok_with_us_contacting_you__or__did_you_want_to_contact_u';
        const baseFields = ['id', 'id_excel', 'created_time', 'ad_id', 'ad_name', 'adset_id', 'adset_name', 'campaign_id', 'campaign_name', 'form_id', 'form_name', 'is_organic', 'platform', 'when_are_you_getting_married_', 'where_are_you_getting_married_', 'choose_your_call_date_', 'email', 'full_name', 'phone', 'lead_status', 'fecha_importacion', 'correo_uno_enviado', 'fecha_envio_correo_uno', 'correo_dos_enviado', 'fecha_envio_correo_dos', 'descartado'];
        // Start with base list; include consentField at the top only if it's present and non-empty on this lead
        let fields = baseFields.slice();
        if (lead && Object.prototype.hasOwnProperty.call(lead, consentField) && lead[consentField] !== null && String(lead[consentField]).trim() !== '') {
            fields.unshift(consentField);
        }
        fields.forEach(field => {
            var raw = lead[field];
            var value = (raw === undefined || raw === null || String(raw).trim() === '') ? 'N/A' : escapeHtml(raw);

            // Normalizar y presentar el campo de consentimiento de forma amigable
            if (field === consentField) {
                var rawStr = String(raw || '').trim();
                if (rawStr === '') {
                    value = 'No aplica';
                } else {
                    // Quitar guiones bajos y colapsar espacios, luego escapar
                    var v = rawStr.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();
                    value = escapeHtml(v);
                }
            }

            if (field === 'where_are_you_getting_married_') {
                var rawAlt = lead['where_are_you_getting_married_'] || lead['where_is_your_marriage_taking_place_'];
                value = (rawAlt === undefined || rawAlt === null || String(rawAlt).trim() === '') ? 'N/A' : escapeHtml(rawAlt);
            }
            if (['correo_uno_enviado', 'correo_dos_enviado', 'descartado'].includes(field)) {
                value = (raw == 1) ? 'Sí' : 'No';
            }
            var label = (field === consentField) ? 'Consentimiento de contacto' : field.replace(/_/g, ' ');
            content += `<tr><td><strong>${label}</strong></td><td>${value}</td></tr>`;
        });
        content += '</table>';
        // Build action buttons HTML to be included inside the modal (placed above the details table)
        var actionsHtml = '<div class="mb-3">'; // use bottom margin so table appears below actions
        // Add a header & subtitle for the actions block
        actionsHtml += '<div class="modal-section-header">' +
                   '<h6 class="modal-section-title">Acciones</h6>' +
                   '<div class="modal-section-subtitle">Operaciones disponibles para este lead</div>' +
                   '</div>';
        var tablaOrigen = lead['tabla_origen'] || '';
        var leadId = lead['id'] || '';
        var usuarioAsignado = lead['usuario_asignado'] ? parseInt(lead['usuario_asignado']) : 0;

        // Botón para ver conversación de WhatsApp (solo si está asignado al agente 100)
        if (usuarioAsignado === 100) {
            actionsHtml += '<button class="btn btn-info btn-sm mb-2 wa-conversation-btn btn-wa-conversacion" data-wa-conversation="1" data-lead-id="' + String(leadId) + '" data-tabla="' + String(tablaOrigen) + '" onclick="verHistorialWhatsApp(' + JSON.stringify(leadId) + ', ' + JSON.stringify(tablaOrigen) + ')">' +
                           '<i class="bi bi-whatsapp"></i> Ver Conversación de WhatsApp' +
                           '<span class="badge bg-danger rounded-pill ms-2 d-none wa-unread-badge" title="Mensaje nuevo / requiere atención" aria-hidden="true"></span>' +
                           '</button>';
        }

        // If it's an organic lead, show link (if not scheduled) and a discard button
        if (tablaOrigen === 'organic_leads') {
            actionsHtml += '<div class="d-flex gap-2">';
            if (lead['scheduled'] && lead['scheduled'] == 1) {
                actionsHtml += '<span class="badge bg-success">Ya agendó</span>';
            }
            // Ver Link: show only if not scheduled
            if (!lead['scheduled'] || lead['scheduled'] == 0) {
                actionsHtml += '<button class="btn btn-info btn-sm" title="Ver Link del Formulario" style="background-color: #5e543f; color: white; border: 1px solid #5e543f;" onclick=\'verLinkFormulario(' + JSON.stringify(tablaOrigen) + ', ' + JSON.stringify(leadId) + ')\'><i class="bi bi-link-45deg"></i> Ver Link</button>';
                // WhatsApp button (if phone present)
                if (lead['phone'] && String(lead['phone']).replace(/\D/g, '').length >= 8) {
                    if (lead['whatsapp_enviado'] && parseInt(lead['whatsapp_enviado']) === 1) {
                            actionsHtml += '<button class="btn btn-success btn-sm whatsapp-btn" title="WhatsApp enviado" style="background-color:#25D366;color:white;border: 1px solid #25D366; margin-left:5px;"><i class="bi bi-whatsapp"></i> WhatsApp<br/><small class="text-muted">enviado</small></button>';
                        } else {
                            actionsHtml += '<button class="btn btn-success btn-sm whatsapp-btn" title="Enviar por WhatsApp" style="background-color:#25D366;color:white;border: 1px solid #25D366; margin-left:5px;" onclick=\'sendWhatsApp(this, ' + JSON.stringify(tablaOrigen) + ', ' + JSON.stringify(leadId) + ', ' + JSON.stringify(lead['phone'] || '') + ', ' + JSON.stringify(lead['full_name'] || '') + ')\'><i class="bi bi-whatsapp"></i> WhatsApp</button>';
                        }
                }
            }
            actionsHtml += '<button class="btn btn-secondary btn-sm btn-descartar" title="Descartar" data-id="' + String(leadId) + '" data-tabla="' + String(tablaOrigen) + '"><i class="bi bi-x-circle"></i> Descartar</button>';
            actionsHtml += '</div>';
        } else {
            // Non-organic leads: show Agenda manual (link), Reestablecer, and Descartar
            var agendaUrl = 'https://citas.efegepho.com.mx/inquire-form.php?tabla_origen=' + encodeURIComponent(tablaOrigen) + '&id=' + encodeURIComponent(leadId);
            actionsHtml += '<div class="d-flex gap-2">';
            if (!lead['scheduled'] || lead['scheduled'] == 0) {
                actionsHtml += '<a class="btn btn-warning btn-sm" title="Agenda manual" target="_blank" rel="noopener noreferrer" style="background-color: #5e543f; color: white; border: 1px solid #5e543f;" href="' + agendaUrl + '"><i class="bi bi-calendar"></i> Agenda manual</a>';
                // Ver Link for non-organics: also show if not scheduled
                actionsHtml += '<button class="btn btn-info btn-sm" title="Ver Link del Formulario" style="background-color: #5e543f; color: white; border: 1px solid #5e543f; margin-left:5px;" onclick=\'verLinkFormulario(' + JSON.stringify(tablaOrigen) + ', ' + JSON.stringify(leadId) + ')\'><i class="bi bi-link-45deg"></i> Ver Link</button>';
                // WhatsApp button for non-organics if phone present
                if (lead['phone'] && String(lead['phone']).replace(/\D/g, '').length >= 8) {
                    if (lead['whatsapp_enviado'] && parseInt(lead['whatsapp_enviado']) === 1) {
                            actionsHtml += '<button class="btn btn-success btn-sm whatsapp-btn" title="WhatsApp enviado" style="background-color:#25D366;color:white;border: 1px solid #25D366; margin-left:5px;"><i class="bi bi-whatsapp"></i> WhatsApp<br/><small class="text-muted">enviado</small></button>';
                        } else {
                            actionsHtml += '<button class="btn btn-success btn-sm whatsapp-btn" title="Enviar por WhatsApp" style="background-color:#25D366;color:white;border: 1px solid #25D366; margin-left:5px;" onclick=\'sendWhatsApp(this, ' + JSON.stringify(tablaOrigen) + ', ' + JSON.stringify(leadId) + ', ' + JSON.stringify(lead['phone'] || '') + ', ' + JSON.stringify(lead['full_name'] || '') + ')\'><i class="bi bi-whatsapp"></i> WhatsApp</button>';
                        }
                }
            } else {
                actionsHtml += '<span class="badge bg-success">Ya agendó</span>';
            }
            actionsHtml += '<button class="btn btn-primary btn-sm" title="Reestablecer" style="background-color: #5e543f; color: white; border: 1px solid #5e543f;" onclick=\'reestablecerCorreos(' + JSON.stringify(leadId) + ', ' + JSON.stringify(tablaOrigen) + ')\'><i class="bi bi-arrow-clockwise"></i> Reestablecer</button>';
            actionsHtml += '<button class="btn btn-secondary btn-sm btn-descartar" title="Descartar" data-id="' + String(leadId) + '" data-tabla="' + String(tablaOrigen) + '"><i class="bi bi-x-circle"></i> Descartar</button>';
            actionsHtml += '</div>';
        }

        actionsHtml += '</div>';

        // Prepend actions area above the details table inside the modal
        // Ensure `detailsHeaderHtml` is inserted after the actions so the title/subtitle appear below the buttons and above the details
        document.getElementById('modalBody').innerHTML = actionsHtml + detailsHeaderHtml + content;

        // Refrescar badge de atención/no leídos dentro del modal Ver Más
        if (usuarioAsignado === 100 && leadId && tablaOrigen) {
            try { waRefreshConversationButtons(document.getElementById('modalBody')); } catch (e) {}
        }
        var modal = new bootstrap.Modal(document.getElementById('verMasModal'));
        modal.show();
    }

    function verScript() {
        var pdfPath = "../files/Script para Forms ( whatsapp y llamada).pdf";
        var src = encodeURI(pdfPath);
        Swal.fire({
            title: 'Vista previa del Script',
            html: '<div style="height:70vh; width:100%;"><iframe src="' + src + '" style="width:100%;height:100%;border:0;" allowfullscreen></iframe></div>',
            width: '90%',
            showCloseButton: true,
            showConfirmButton: false,
        });
    }

    function verLinkFormulario(tablaOrigen, leadId) {
        var link = 'https://citas.efegepho.com.mx/inquire-form.php?tabla_origen=' + encodeURIComponent(tablaOrigen) + '&id=' + encodeURIComponent(leadId);
        Swal.fire({
            title: 'Link del Formulario',
            html: '<div class="mb-3">' +
                  '<label class="form-label">Copia este link para enviarlo al lead:</label>' +
                  '<div class="input-group">' +
                  '<input type="text" class="form-control" id="linkOrganicLead" value="' + link + '" readonly>' +
                  '<button class="btn btn-outline-primary" type="button" onclick="copiarLinkOrganic()" title="Copiar link">' +
                  '<i class="bi bi-clipboard"></i> Copiar' +
                  '</button>' +
                  '</div>' +
                  '</div>' +
                  '<div id="copiadoAlertOrganic" class="alert alert-success d-none" role="alert">' +
                  '<i class="bi bi-check2"></i> ¡Link copiado al portapapeles!' +
                  '</div>',
            showCloseButton: true,
            showConfirmButton: false,
            width: '600px'
        });
    }

    function sendWhatsApp(el, tablaOrigen, leadId, phone, fullName) {
        var cleaned = (phone || '').replace(/\D/g, '');
        if (!cleaned || cleaned.length < 8) {
            Swal.fire({
                icon: 'error',
                title: 'Número inválido',
                text: 'El número de teléfono no es válido para enviar por WhatsApp.'
            });
            return;
        }
        // Build the inquire form link
        var link = 'https://citas.efegepho.com.mx/inquire-form.php?tabla_origen=' + encodeURIComponent(tablaOrigen) + '&id=' + encodeURIComponent(leadId);
        var name = fullName || '';
        var txt = 'Hola ' + name + ', te comparto el link para agendar: ' + link;
        var waLink = 'https://wa.me/' + cleaned + '?text=' + encodeURIComponent(txt);
        window.open(waLink, '_blank');

        // Send AJAX request to mark whatsapp as sent
        $.ajax({
            url: 'marcar_whatsapp_enviado.php',
            type: 'POST',
            data: { id: leadId, tabla_origen: tablaOrigen },
            dataType: 'json',
            success: function (resp) {
                if (resp && resp.success) {
                    // Update clicked button and table buttons visually without disabling them
                    try {
                        if (el) {
                            $(el).prop('disabled', false).html('<i class="bi bi-whatsapp"></i> WhatsApp<br/><small class="text-muted">enviado</small>');
                        }
                        // Also update table row button(s), if any
                        var $row = $(`#lead-row-${leadId}-${tablaOrigen}`);
                        if ($row.length) {
                            var $btns = $row.find('.whatsapp-btn');
                            $btns.each(function () {
                                $(this).prop('disabled', false).html('<i class="bi bi-whatsapp"></i> WhatsApp<br/><small class="text-muted">enviado</small>');
                            });
                        }
                    } catch (e) {
                        console.error('Error updating UI after marking WhatsApp:', e);
                    }
                } else {
                    Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo actualizar estado de WhatsApp', 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);
                Swal.fire('Error', 'No se pudo actualizar estado de WhatsApp', 'error');
            }
        });
    }

    function verHistorialWhatsApp(leadId, tablaOrigen) {
        console.log('verHistorialWhatsApp llamado con leadId:', leadId, 'tablaOrigen:', tablaOrigen);
        
        // Verificar que el modal existe
        var modalElement = document.getElementById('whatsappHistorialModal');
        if (!modalElement) {
            console.error('Modal whatsappHistorialModal no encontrado');
            return;
        }

        // Guardar contexto actual para envío (se usa por el handler del botón enviar)
        modalElement._waCurrentLeadId = leadId;
        modalElement._waCurrentTablaOrigen = tablaOrigen;

        function waScrollToBottom() {
            var chatEl = document.getElementById('waChat');
            if (!chatEl) return;
            chatEl.scrollTop = chatEl.scrollHeight;
        }

        function waIsNearBottom(chatEl) {
            if (!chatEl) return true;
            var remaining = chatEl.scrollHeight - chatEl.scrollTop - chatEl.clientHeight;
            return remaining < 40;
        }

        function waStartPolling(loadFn) {
            // Keep polling state on the modal element so it survives re-open.
            try {
                if (modalElement._waPollTimer) {
                    clearInterval(modalElement._waPollTimer);
                    modalElement._waPollTimer = null;
                }
                modalElement._waPollLeadId = leadId;
                modalElement._waPollTablaOrigen = tablaOrigen;
                modalElement._waPollTimer = setInterval(function () {
                    // Only poll while modal is actually visible
                    if (!modalElement.classList.contains('show')) return;
                    loadFn(true);
                }, 1000);
            } catch (e) {
                console.error('Error iniciando polling WhatsApp:', e);
            }
        }

        function waStopPolling() {
            try {
                if (modalElement && modalElement._waPollTimer) {
                    clearInterval(modalElement._waPollTimer);
                    modalElement._waPollTimer = null;
                }
            } catch (e) {
                // ignore
            }
        }

        function waSetComposerEnabled(enabled) {
            var composer = modalElement ? modalElement.querySelector('.wa-composer') : null;
            if (!composer) return;
            var input = composer.querySelector('.wa-input');
            var sendBtn = composer.querySelector('.wa-send');
            var clipLabel = composer.querySelector('.wa-clip');
            var clipInput = clipLabel ? clipLabel.querySelector('input[type="file"]') : null;

            if (enabled) {
                composer.setAttribute('aria-label', 'Enviar mensaje');
                if (input) input.disabled = false;
                if (sendBtn) {
                    sendBtn.disabled = false;
                    sendBtn.removeAttribute('title');
                }
                if (clipLabel) clipLabel.classList.remove('disabled');
                if (clipInput) clipInput.disabled = false;
            } else {
                composer.setAttribute('aria-label', 'Enviar mensaje (deshabilitado)');
                if (input) input.disabled = true;
                if (sendBtn) {
                    sendBtn.disabled = true;
                    sendBtn.setAttribute('title', 'Enviar (deshabilitado)');
                }
                if (clipLabel) clipLabel.classList.add('disabled');
                if (clipInput) clipInput.disabled = true;
            }
        }

        function waBindSendOnce() {
            if (!modalElement || modalElement.dataset.waSendBound === '1') return;
            modalElement.dataset.waSendBound = '1';

            var composer = modalElement.querySelector('.wa-composer');
            if (!composer) return;
            var input = composer.querySelector('.wa-input');
            var sendBtn = composer.querySelector('.wa-send');

            function doSend() {
                if (!input || input.disabled) return;
                var text = (input.value || '').trim();
                if (!text) return;

                var currentLeadId = modalElement._waCurrentLeadId;
                var currentTablaOrigen = modalElement._waCurrentTablaOrigen;
                if (!currentLeadId || !currentTablaOrigen) return;

                sendBtn && (sendBtn.disabled = true);

                $.ajax({
                    url: 'ajax/whatsapp_send_message.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        leadId: currentLeadId,
                        tablaOrigen: currentTablaOrigen,
                        message: text
                    },
                    success: function (resp) {
                        if (resp && resp.success) {
                            input.value = '';
                            // Force refresh so the sent message appears
                            try { modalElement._waLastFingerprint = null; } catch (e) {}

                            // Refresh badges right away (agent responded)
                            try {
                                delete waUnreadCache[waKey(currentLeadId, currentTablaOrigen)];
                            } catch (e) {}
                            try { waRefreshConversationButtons(document); } catch (e) {}
                        } else {
                            Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo enviar el mensaje', 'error');
                        }
                    },
                    error: function (xhr) {
                        Swal.fire('Error', 'Error al enviar el mensaje', 'error');
                        console.error('Error AJAX enviar WhatsApp:', xhr && xhr.responseText ? xhr.responseText : xhr);
                    },
                    complete: function () {
                        sendBtn && (sendBtn.disabled = false);
                    }
                });
            }

            if (sendBtn) {
                sendBtn.addEventListener('click', doSend);
            }
            if (input) {
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        doSend();
                    }
                });
            }
        }

        // Ensure we always open at the latest message
        if (!modalElement.dataset.waScrollHook) {
            modalElement.addEventListener('shown.bs.modal', function () {
                // Wait for layout
                setTimeout(waScrollToBottom, 0);
            });
            modalElement.addEventListener('hidden.bs.modal', function () {
                waStopPolling();
                // Refresh badges after closing chat modal
                try { waRefreshConversationButtons(document); } catch (e) {}
            });
            modalElement.dataset.waScrollHook = '1';
        }
        
        // Cerrar el modal "Ver Más" si está abierto
        var verMasModal = bootstrap.Modal.getInstance(document.getElementById('verMasModal'));
        if (verMasModal) {
            console.log('Cerrando modal Ver Más');
            verMasModal.hide();
        }
        
        // Esperar un poco para que se cierre el modal anterior antes de abrir el nuevo
        setTimeout(function() {
            console.log('Abriendo modal de WhatsApp');

            var chat = document.getElementById('waChat');
            var leadNameEl = document.getElementById('waLeadName');
            var leadPhoneEl = document.getElementById('waLeadPhone');
            var metaEl = document.getElementById('waMeta');

            if (leadNameEl) leadNameEl.textContent = 'Conversación de WhatsApp';
            if (leadPhoneEl) leadPhoneEl.textContent = '';
            if (metaEl) metaEl.textContent = '';
            if (chat) chat.innerHTML = '<div class="wa-loading">Cargando conversación…</div>';

            // Abrir el modal de historial de WhatsApp
            var modal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });

            modal.show();
            console.log('Modal mostrado');

            // Scroll immediately (also runs again on shown.bs.modal)
            setTimeout(waScrollToBottom, 0);

            function waLoadHistory(isSilent) {
                // Cargar historial desde el servidor
                $.ajax({
                    url: 'ajax/whatsapp_historial.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        leadId: leadId,
                        tablaOrigen: tablaOrigen
                    },
                    success: function(resp) {
                        if (!chat) return;

                        if (!resp || !resp.success) {
                            if (!isSilent) chat.innerHTML = '<div class="wa-empty">No se pudo cargar la conversación.</div>';
                            return;
                        }

                        if (resp.lead) {
                            if (leadNameEl) leadNameEl.textContent = resp.lead.full_name ? String(resp.lead.full_name) : 'Conversación de WhatsApp';
                            if (leadPhoneEl) leadPhoneEl.textContent = resp.lead.phone ? ('+' + String(resp.lead.phone)) : '';
                        }

                        // Habilitar composer solo si el lead pidió hablar con agente
                        var handoffEnabled = !!(resp.handoff && resp.handoff.enabled);
                        waSetComposerEnabled(handoffEnabled);
                        if (handoffEnabled) {
                            waBindSendOnce();
                        }

                        if (metaEl) {
                            metaEl.textContent = resp.found && resp.file ? ('Archivo: ' + resp.file) : '';
                        }

                        // Fingerprint to avoid re-rendering identical content (prevents flicker)
                        var fp = '';
                        try {
                            fp = JSON.stringify({ found: !!resp.found, file: resp.file || '', messages: resp.messages || [] });
                        } catch (e) {
                            fp = String(resp.file || '') + '|' + String(resp.found);
                        }
                        if (modalElement._waLastFingerprint && modalElement._waLastFingerprint === fp) {
                            return;
                        }
                        modalElement._waLastFingerprint = fp;

                        if (!resp.found) {
                            if (!isSilent) chat.innerHTML = '<div class="wa-empty">No se encontró conversación para este número.</div>';
                            return;
                        }

                        var msgs = Array.isArray(resp.messages) ? resp.messages : [];
                        if (!msgs.length) {
                            if (!isSilent) chat.innerHTML = '<div class="wa-empty">La conversación está vacía.</div>';
                            return;
                        }

                        var wasNearBottom = waIsNearBottom(chat);

                        var html = '';
                        // Avoid rendering consecutive duplicate messages (common when session files briefly contain repeated entries)
                        var _lastWaKey = null;
                        msgs.forEach(function(m) {
                            var role = (m && m.role) ? String(m.role) : '';
                            var content = (m && m.content !== undefined && m.content !== null) ? String(m.content) : '';
                            content = content.trim();
                            if (!content) return;

                            // Remove internal/system prompts from display (these are not user-facing)
                            if (role === 'system') {
                                var up = (content || '').toUpperCase();
                                if (up.indexOf('IDENTIDAD Y OBJETIVO') === 0 || up.indexOf('DATOS YA OBTENIDOS DEL LEAD') === 0 || up.indexOf('PROMPT') !== -1) {
                                    return; // skip prompt/system message
                                }
                            }

                            // Skip consecutive identical messages to avoid duplicate display
                            var waKey = role + '|' + content;
                            if (waKey === _lastWaKey) return;
                            _lastWaKey = waKey;

                            // Required mapping: "user" = lead. "system" = sent by you.
                            // We also treat "assistant" as sent by you.
                            var kind = 'out';
                            if (role === 'user') {
                                kind = 'in';
                            } else if (role === 'system' && (content.indexOf('IDENTIDAD Y OBJETIVO') === 0 || content.indexOf('Datos ya obtenidos del lead:') === 0)) {
                                kind = 'meta';
                            }

                            if (kind === 'meta') {
                                html += '<div class="wa-row meta"><div class="wa-bubble meta">' + escapeHtml(content) + '</div></div>';
                            } else {
                                html += '<div class="wa-row ' + kind + '"><div class="wa-bubble ' + kind + '">' + escapeHtml(content) + '</div></div>';
                            }
                        });

                        chat.innerHTML = html || '<div class="wa-empty">No hay mensajes para mostrar.</div>';
                        // Scroll to bottom only if user was already at the bottom
                        if (wasNearBottom) {
                            setTimeout(waScrollToBottom, 0);
                        }

                        // Mark as read (debounced) so the badge clears for unread messages.
                        try {
                            if (modalElement._waMarkReadTimer) {
                                clearTimeout(modalElement._waMarkReadTimer);
                            }
                            modalElement._waMarkReadTimer = setTimeout(function () {
                                waMarkRead(leadId, tablaOrigen);
                            }, 600);
                        } catch (e) {}
                    },
                    error: function(xhr) {
                        if (!isSilent && chat) chat.innerHTML = '<div class="wa-empty">Error al cargar la conversación.</div>';
                        console.error('Error AJAX conversación WhatsApp:', xhr && xhr.responseText ? xhr.responseText : xhr);
                    }
                });
            }

            // Primera carga (con UI)
            waLoadHistory(false);
            // Refresco en vivo mientras el modal esté abierto
            waStartPolling(waLoadHistory);
        }, 350);
    }

    function copiarLinkOrganic() {
        var linkInput = document.getElementById('linkOrganicLead');
        linkInput.select();
        linkInput.setSelectionRange(0, 99999);
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(linkInput.value).then(function () {
                document.getElementById('copiadoAlertOrganic').classList.remove('d-none');
                setTimeout(function () {
                    document.getElementById('copiadoAlertOrganic').classList.add('d-none');
                }, 2000);
            }).catch(function () {
                document.execCommand('copy');
                document.getElementById('copiadoAlertOrganic').classList.remove('d-none');
                setTimeout(function () {
                    document.getElementById('copiadoAlertOrganic').classList.add('d-none');
                }, 2000);
            });
        } else {
            document.execCommand('copy');
            document.getElementById('copiadoAlertOrganic').classList.remove('d-none');
            setTimeout(function () {
                document.getElementById('copiadoAlertOrganic').classList.add('d-none');
            }, 2000);
        }
    }

    // --- Funcionalidad para registro manual de leads ---
    $(document).ready(function () {
        // Populate country code select for manual lead registration
        (function loadCountryCodes() {
            var selectElement = document.getElementById('leadCountryCode');
            if (!selectElement) return;
            fetch('JS/countries_codes.json?' + new Date().getTime(), { cache: 'no-store' })
                .then(function (resp) {
                    if (!resp.ok) throw new Error('Failed to load countries');
                    return resp.json();
                })
                .then(function (data) {
                    if (!data || !data.countries) return;
                    data.countries.sort(function (a, b) { return a.name.localeCompare(b.name); });
                    data.countries.forEach(function (country) {
                        var opt = document.createElement('option');
                        opt.value = country.code;
                        opt.text = country.code + ' (' + country.name + ')';
                        selectElement.appendChild(opt);
                    });
                })
                .catch(function (err) {
                    // Fall back to basic list
                    var fallback = [
                        { code: '+52', name: 'Mexico' },
                        { code: '+1', name: 'United States' },
                        { code: '+44', name: 'United Kingdom' }
                    ];
                    fallback.forEach(function (country) {
                        var opt = document.createElement('option');
                        opt.value = country.code;
                        opt.text = country.code + ' (' + country.name + ')';
                        selectElement.appendChild(opt);
                    });
                    console.error('Error loading countries:', err);
                });
        })();
        // Quitar formato al pegar en ciertos inputs: insertar solo texto plano en la posición del cursor
        (function () {
            function attachPlainPaste(id) {
                var el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('paste', function (e) {
                    e.preventDefault();
                    var clipboard = (e.clipboardData || window.clipboardData);
                    var text = clipboard && clipboard.getData ? (clipboard.getData('text/plain') || clipboard.getData('text')) : '';
                    // Normalizar espacios y quitar saltos de línea
                    text = text.replace(/\s+/g, ' ').trim();
                    var start = typeof this.selectionStart === 'number' ? this.selectionStart : this.value.length;
                    var end = typeof this.selectionEnd === 'number' ? this.selectionEnd : this.value.length;
                    var val = this.value || '';
                    this.value = val.slice(0, start) + text + val.slice(end);
                    var pos = start + text.length;
                    try { this.setSelectionRange(pos, pos); } catch (err) {}
                });
            }
            // Attach to inputs that should strip formatting on paste
            attachPlainPaste('leadNombre');
            attachPlainPaste('wpCampaignName');
        })();

        // Normalizar texto antes de guardar en BD (quita formato, emojis raros y normaliza compat chars)
        function normalizeForDB(str) {
            if (str === undefined || str === null) return '';
            str = String(str);
            try { str = str.normalize('NFKC'); } catch (e) {}
            // Eliminar caracteres de ancho cero/formatting
            str = str.replace(/[\u200B-\u200F\uFEFF]/g, '');
            // Descomponer y quitar marcas diacríticas
            try {
                str = str.normalize('NFD').replace(/\p{M}/gu, '');
            } catch (e) {
                str = str.replace(/[\u0300-\u036f]/g, '');
            }
            // Quitar símbolos/emoji manteniendo letras, números, puntuación y espacios
            try {
                str = str.replace(/[^\p{L}\p{N}\p{P}\p{Zs}]/gu, '');
            } catch (e) {
                // Fallback más compatible: mantener ASCII básico y algunos acentos comunes
                str = str.replace(/[^\w \-\.,;:()¿?¡!\u00C0-\u017F]/g, '');
            }
            // Normalizar espacios
            str = str.replace(/\s+/g, ' ').trim();
            return str;
        }

        // Guardar lead manual
        $('#btnGuardarLeadManual').on('click', function () {
            var form = document.getElementById('formLeadManual');
            var isValid = true;

            // Resetear validaciones
            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });

            // Validar nombre (obligatorio) — normalizar antes de validar/guardar
            var nombreRaw = $('#leadNombre').val();
            var nombre = normalizeForDB(nombreRaw);
            if (!nombre) {
                $('#leadNombre').addClass('is-invalid');
                isValid = false;
            }

            // Validar correo (opcional pero si tiene valor debe ser válido)
            var correo = $('#leadCorreo').val().trim();
            if (correo && !isValidEmail(correo)) {
                $('#leadCorreo').addClass('is-invalid');
                isValid = false;
            }

            // Validar origen (obligatorio)
            var origen = $('#leadOrigen').val();
            if (!origen) {
                $('#leadOrigen').addClass('is-invalid');
                isValid = false;
            }

            // Validar medio (obligatorio)
            var medio = $('#leadMedio').val();
            if (!medio) {
                $('#leadMedio').addClass('is-invalid');
                isValid = false;
            }

            // Validar teléfono (si se proporciona)
            var selectedCode = $('#leadCountryCode').val() || '';
            var codeDigits = (selectedCode || '').replace(/\D/g, '');
            var phoneVal = $('#leadTelefono').val() ? $('#leadTelefono').val().trim() : '';
            var phoneDigits = phoneVal.replace(/\D/g, '');
            if (phoneDigits.length > 0) {
                // Debe ser exactamente 10 dígitos
                if (phoneDigits.length !== 10) {
                    $('#leadTelefono').addClass('is-invalid');
                    isValid = false;
                }
                // Si hay teléfono, el código de país es obligatorio
                if (!selectedCode || selectedCode.trim() === '') {
                    $('#leadCountryCode').addClass('is-invalid');
                    isValid = false;
                }
            }

            if (!isValid) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: 'Por favor complete los campos obligatorios',
                    showConfirmButton: false,
                    timer: 2500
                });
                return;
            }

            // Deshabilitar botón y mostrar loading
            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...');

            // build telefono final: prefix selected country code stripped of '+' then concat with phone digits
            var selectedCode = $('#leadCountryCode').val() || '';
            var codeDigits = (selectedCode || '').replace(/\D/g, '');
            var phoneVal = $('#leadTelefono').val() ? $('#leadTelefono').val().trim() : '';
            var phoneDigits = phoneVal.replace(/\D/g, '');
            var finalPhone = phoneDigits;
            if (codeDigits && phoneDigits.length > 0) {
                if (!phoneDigits.startsWith(codeDigits)) finalPhone = codeDigits + phoneDigits;
            }

            $.ajax({
                url: 'guardar_lead_manual.php',
                type: 'POST',
                data: {
                    nombre: nombre,
                    correo: correo,
                    telefono: finalPhone,
                    telefono_local: phoneDigits,
                    country_code: selectedCode,
                    campaign_name: origen,
                    platform: medio,
                    fecha_registro: $('#leadFechaRegistro').val()
                },
                dataType: 'json',
                success: function (resp) {
                    if (resp.success) {
                        // Cerrar modal de registro
                        var registroModal = bootstrap.Modal.getInstance(document.getElementById('registroManualModal'));
                        registroModal.hide();

                        // Limpiar formulario
                        form.reset();

                        // Mostrar modal con link
                        $('#linkFormulario').val(resp.link);
                        var linkModal = new bootstrap.Modal(document.getElementById('linkFormularioModal'));
                        linkModal.show();

                        // Recargar la página después de cerrar el modal de éxito para mostrar el nuevo lead
                        document.getElementById('linkFormularioModal').addEventListener('hidden.bs.modal', function handler() {
                            document.getElementById('linkFormularioModal').removeEventListener('hidden.bs.modal', handler);
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: resp.message || 'No se pudo guardar el lead',
                            icon: 'error'
                        });
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error:', error);
                    showErrorModalFromResponse(null, xhr);
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="bi bi-save"></i> Guardar Lead');
                }
            });
        });

                // Copiar link al portapapeles
        $('#btnCopiarLink').on('click', function () {
            var linkInput = document.getElementById('linkFormulario');
            linkInput.select();
            linkInput.setSelectionRange(0, 99999); // Para móviles

            // Usar API moderna si está disponible
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(linkInput.value).then(function () {
                    mostrarAlertaCopiado();
                }).catch(function () {
                    // Fallback
                    document.execCommand('copy');
                    mostrarAlertaCopiado();
                });
            } else {
                document.execCommand('copy');
                mostrarAlertaCopiado();
            }
        });

        function mostrarAlertaCopiado() {
            $('#copiadoAlert').removeClass('d-none');
            setTimeout(function () {
                $('#copiadoAlert').addClass('d-none');
            }, 2000);
        }

        function isValidEmail(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }

        // Limpiar validaciones al cerrar modal
        $('#registroManualModal').on('hidden.bs.modal', function () {
            var form = document.getElementById('formLeadManual');
            form.reset();
            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });
        });

        // Limpia validaciones sobre input/changes para telefono y country code
        $('#leadTelefono').off('input change').on('input change', function () {
            var v = $(this).val() ? $(this).val().trim() : '';
            if (v && v.replace(/\D/g,'').length === 10) {
                $(this).removeClass('is-invalid');
                $('#leadCountryCode').removeClass('is-invalid');
            }
        });
        $('#leadCountryCode').off('change').on('change', function () {
            var sel = $(this).val() || '';
            if (sel && ($('#leadTelefono').val() || '').replace(/\D/g,'').length === 10) {
                $(this).removeClass('is-invalid');
            }
        });



        $('#btnGuardarWeddingPlanner').on('click', function () {
            var isValid = true;
            $('#formWPManual').find('.is-invalid').removeClass('is-invalid');

            var campaignRaw = $('#wpCampaignName').val();
            var fullNameRaw = $('#wpFullName').val();
            var campaign = normalizeForDB(campaignRaw) || '';
            var fullName = normalizeForDB(fullNameRaw) || '';
            var asesor = $('#wpAsesor').val();
            if (!campaign) { $('#wpCampaignName').addClass('is-invalid'); isValid=false;}
            if (!fullName) { $('#wpFullName').addClass('is-invalid'); isValid=false; }
            if (!asesor) { $('#wpAsesor').addClass('is-invalid'); isValid=false; }

            // No date/time fields are required nor sent; nothing to validate here
            var date_apt = null;
            var time_apt = null;
            if (!isValid) {
                Swal.fire({ icon: 'warning', title: 'Complete los campos obligatorios' });
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...');

            $.ajax({
                url: 'guardar_wedding_planner_manual.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    campaign_name: campaign,
                    where_is: $('#wpWeddingLocation').val(),
                    when_are: $('#wpWeddingDate').val(),
                    full_name: fullName,
                    id_vendedor_asignado: asesor
                },
                success: function (resp) {
                    if (resp && resp.success) {
                        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('registroManualWeddingModal'));
                        modal.hide();
                        Swal.fire({ icon: 'success', title: 'Guardado', text: 'Wedding Planner registrado y cita agendada' }).then(function () { location.reload(); });
                    } else {
                        Swal.fire('Error', resp.message || 'No se pudo guardar', 'error');
                    }
                },
                error: function (xhr) {
                    console.error('Error:', xhr);
                    showErrorModalFromResponse(null, xhr);
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="bi bi-save"></i> Guardar Wedding Planner');
                }
            });
        });
    });

    // ========================================
    // SISTEMA DE FILTROS POR COLUMNA
    // ========================================
    
    var columnFilters = {}; // Almacena los valores seleccionados por columna
    var currentFilterDropdown = null; // Referencia al dropdown actualmente abierto
    
    // Función para obtener todos los valores únicos de una columna
    function getUniqueColumnValues(columnName) {
        var values = new Set();
        $('#leadsTable tbody tr').each(function() {
            var cell = $(this).find('td[data-column="' + columnName + '"]');
            if (cell.length > 0) {
                var text = cell.text().trim();
                if (text !== '') {
                    values.add(text);
                }
            }
        });
        return Array.from(values).sort();
    }
    
    // Función para crear el dropdown de filtro
    function createFilterDropdown(columnName, iconElement) {
        // Cerrar cualquier dropdown abierto
        closeFilterDropdown();
        
        var uniqueValues = getUniqueColumnValues(columnName);
        if (uniqueValues.length === 0) {
            return; // No hay valores para filtrar
        }
        
        // Crear el dropdown
        var dropdown = $('<div class="filter-dropdown show"></div>');
        
        // Header
        var header = $('<div class="filter-dropdown-header"></div>');
        header.append('<span>Filtrar por ' + columnName + '</span>');
        var closeBtn = $('<button type="button" class="btn-close btn-sm" style="font-size: 0.7rem;"></button>');
        closeBtn.on('click', closeFilterDropdown);
        header.append(closeBtn);
        dropdown.append(header);
        
        // Body
        var body = $('<div class="filter-dropdown-body"></div>');
        
        // Buscador
        var searchInput = $('<input type="text" class="filter-search" placeholder="Buscar...">');
        body.append(searchInput);
        
        // Opciones
        var optionsContainer = $('<div class="filter-options"></div>');
        
        // Botón "Seleccionar todo"
        var selectAllDiv = $('<div class="filter-option mb-2"></div>');
        var selectAllCheckbox = $('<input type="checkbox" id="selectAll_' + columnName + '" checked>');
        var selectAllLabel = $('<label for="selectAll_' + columnName + '"><strong>Seleccionar todo</strong></label>');
        selectAllDiv.append(selectAllCheckbox).append(selectAllLabel);
        optionsContainer.append(selectAllDiv);
        optionsContainer.append('<hr style="margin: 0.5rem 0;">');
        
        // Obtener valores previamente seleccionados
        var selectedValues = columnFilters[columnName] || uniqueValues.slice();
        
        // Crear checkboxes para cada valor único
        uniqueValues.forEach(function(value, index) {
            var optionDiv = $('<div class="filter-option" data-value="' + escapeHtml(value) + '"></div>');
            var checkbox = $('<input type="checkbox" id="filter_' + columnName + '_' + index + '">');
            
            // Marcar si está en los valores seleccionados
            if (selectedValues.includes(value)) {
                checkbox.prop('checked', true);
            }
            
            var label = $('<label for="filter_' + columnName + '_' + index + '">' + escapeHtml(value) + '</label>');
            
            optionDiv.append(checkbox).append(label);
            optionsContainer.append(optionDiv);
        });
        
        body.append(optionsContainer);
        dropdown.append(body);
        
        // Footer
        var footer = $('<div class="filter-dropdown-footer"></div>');
        var clearBtn = $('<button type="button" class="btn btn-sm btn-secondary filter-btn">Limpiar</button>');
        var applyBtn = $('<button type="button" class="btn btn-sm btn-primary filter-btn">Aplicar</button>');
        
        clearBtn.on('click', function() {
            optionsContainer.find('input[type="checkbox"]').prop('checked', true);
            delete columnFilters[columnName];
            applyColumnFilters();
            closeFilterDropdown();
        });
        
        applyBtn.on('click', function() {
            // Recopilar valores seleccionados
            var selected = [];
            optionsContainer.find('.filter-option:not(:first)').each(function() {
                var checkbox = $(this).find('input[type="checkbox"]');
                if (checkbox.prop('checked')) {
                    selected.push($(this).data('value'));
                }
            });
            
            if (selected.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Advertencia',
                    text: 'Debe seleccionar al menos un valor',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            columnFilters[columnName] = selected;
            applyColumnFilters();
            closeFilterDropdown();
        });
        
        footer.append(clearBtn).append(applyBtn);
        dropdown.append(footer);
        
        // Posicionar el dropdown
        $('body').append(dropdown);
        
        var iconOffset = $(iconElement).offset();
        var iconHeight = $(iconElement).outerHeight();
        var dropdownWidth = dropdown.outerWidth();
        var dropdownHeight = dropdown.outerHeight();
        var windowWidth = $(window).width();
        var windowHeight = $(window).height();
        
        var left = iconOffset.left;
        var top = iconOffset.top + iconHeight + 5;
        
        // Ajustar si se sale de la pantalla por la derecha
        if (left + dropdownWidth > windowWidth) {
            left = windowWidth - dropdownWidth - 10;
        }
        
        // Ajustar si se sale de la pantalla por abajo
        if (top + dropdownHeight > windowHeight) {
            top = iconOffset.top - dropdownHeight - 5;
        }
        
        dropdown.css({
            left: left + 'px',
            top: top + 'px'
        });
        
        currentFilterDropdown = dropdown;
        
        // Marcar el ícono como activo
        $(iconElement).addClass('active');
        
        // Funcionalidad de búsqueda
        searchInput.on('input', function() {
            var searchTerm = $(this).val().toLowerCase();
            optionsContainer.find('.filter-option:not(:first)').each(function() {
                var text = $(this).find('label').text().toLowerCase();
                if (text.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
        
        // Funcionalidad "Seleccionar todo"
        selectAllCheckbox.on('change', function() {
            var isChecked = $(this).prop('checked');
            optionsContainer.find('.filter-option:not(:first) input[type="checkbox"]:visible').prop('checked', isChecked);
        });
        
        // Actualizar "Seleccionar todo" cuando cambian los checkboxes individuales
        optionsContainer.on('change', '.filter-option:not(:first) input[type="checkbox"]', function() {
            updateSelectAllCheckbox();
        });
        
        function updateSelectAllCheckbox() {
            var visibleCheckboxes = optionsContainer.find('.filter-option:not(:first):visible input[type="checkbox"]');
            var checkedCheckboxes = visibleCheckboxes.filter(':checked');
            selectAllCheckbox.prop('checked', visibleCheckboxes.length === checkedCheckboxes.length);
        }
        
        updateSelectAllCheckbox();
    }
    
    // Función para cerrar el dropdown de filtro
    function closeFilterDropdown() {
        if (currentFilterDropdown) {
            currentFilterDropdown.remove();
            currentFilterDropdown = null;
            $('.filter-icon.active').removeClass('active');
        }
    }
    
    // Función para aplicar los filtros a la tabla
    function applyColumnFilters() {
        var activeFiltersCount = Object.keys(columnFilters).length;
        
        // Actualizar iconos activos
        $('.filter-icon').removeClass('active');
        Object.keys(columnFilters).forEach(function(col) {
            $('.filter-icon[data-column="' + col + '"]').addClass('active');
        });
        
        // Filtrar filas
        $('#leadsTable tbody tr').each(function() {
            var row = $(this);
            var showRow = true;
            
            // Verificar cada filtro activo
            for (var columnName in columnFilters) {
                var cell = row.find('td[data-column="' + columnName + '"]');
                if (cell.length > 0) {
                    var cellValue = cell.text().trim();
                    var allowedValues = columnFilters[columnName];
                    
                    if (!allowedValues.includes(cellValue)) {
                        showRow = false;
                        break;
                    }
                }
            }
            
            if (showRow) {
                row.show();
            } else {
                row.hide();
            }
        });
        
        // Actualizar el contador si existe
        updateLeadsCount();
    }
    
    // Función para actualizar el contador de leads visibles
    function updateLeadsCount() {
        var visibleCount = $('#leadsTable tbody tr:visible').length;
        var totalCount = $('#leadsTable tbody tr').length;
        
        // Puedes agregar un elemento para mostrar el contador si lo deseas
        // Por ejemplo: $('#filterStatus').text('Mostrando ' + visibleCount + ' de ' + totalCount + ' leads');
    }
    
    // Event listener para los íconos de filtro
    $(document).on('click', '.filter-icon', function(e) {
        e.stopPropagation();
        var columnName = $(this).data('column');
        createFilterDropdown(columnName, this);
    });
    
    // Cerrar dropdown al hacer clic fuera
    $(document).on('click', function(e) {
        if (currentFilterDropdown && !$(e.target).closest('.filter-dropdown').length && !$(e.target).hasClass('filter-icon')) {
            closeFilterDropdown();
        }
    });
    
    // Prevenir que el dropdown se cierre al hacer clic dentro de él
    $(document).on('click', '.filter-dropdown', function(e) {
        e.stopPropagation();
    });
    
    // Cerrar dropdown con la tecla Escape
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && currentFilterDropdown) {
            closeFilterDropdown();
        }
    });
    
    // ========================================
    // FIN SISTEMA DE FILTROS POR COLUMNA
    // ========================================
</script>

</body>

</html>