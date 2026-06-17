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
$sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo = 1 ORDER BY nombre";
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

// Recopilar orígenes únicos de todos los leads
$origenesUnicos = [];
foreach ($allLeads as $lead) {
    $origen = $lead['campaign_name'] ?? '';

    // Normalizar origen (extraer c + número si aplica)
    $origenNormalizado = $origen;
    if (preg_match('/^c(\d+)\./i', $origen, $matches)) {
        $origenNormalizado = 'c' . $matches[1];
    }

    if (!empty($origenNormalizado) && !in_array($origenNormalizado, $origenesUnicos)) {
        $origenesUnicos[] = $origenNormalizado;
    }
}
sort($origenesUnicos);

// Leer filtros de fecha (vienen por GET)
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$filterOrigen = isset($_GET['filter_origen']) ? trim($_GET['filter_origen']) : ''; 

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
    if ($t === '' || $id <= 0)
        continue;
    if (!isset($leadIdsByTable[$t]))
        $leadIdsByTable[$t] = [];
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
$tasaConversion = $totalLeadsBeforeStatusFilter > 0 ? round(($leadsAgendadosTotal / $totalLeadsBeforeStatusFilter), 2) : 0;
?>
<?php

// Re-ordenar después de filtrar por estatus
usort($filteredLeads, function ($a, $b) {
    $ta = (!empty($a['created_time']) && strtotime($a['created_time']) !== false) ? strtotime($a['created_time']) : 0;
    $tb = (!empty($b['created_time']) && strtotime($b['created_time']) !== false) ? strtotime($b['created_time']) : 0;
    if ($ta === $tb)
        return 0;
    return ($ta > $tb) ? -1 : 1;
});

// Recalcular conteos y serie (basados en $filteredLeads finales)
$totalCount = count($filteredLeads);
$todayCount = 0;
$today = date('Y-m-d');
foreach ($filteredLeads as $lead) {
    if (empty($lead['created_time']))
        continue;
    $ts = strtotime($lead['created_time']);
    if ($ts === false)
        continue;
    if (date('Y-m-d', $ts) === $today)
        $todayCount++;
}

// Reconstruir mapa por fecha y la serie para la gráfica
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
    if ($t === '' || $id <= 0)
        continue;
    if (!isset($leadIdsByTable[$t]))
        $leadIdsByTable[$t] = [];
    $leadIdsByTable[$t][] = $id;
}
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '')
        continue;
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
    if (trim($idsList) === '')
        continue;
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
    if (trim($idsList) === '')
        continue;
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

// Obtener campañas existentes para el modal (desde la tabla costo_campanias)
$campanias = [];
$resCamp = $conn->query("SELECT id, nombre_campania FROM costo_campanias ORDER BY nombre_campania");
if ($resCamp && $resCamp->num_rows > 0) {
    while ($rc = $resCamp->fetch_assoc()) {
        $campanias[] = $rc;
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
    <title>Costos por camapaña</title>
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
    .status-agendado {
        color: #7b1fa2;
        font-weight: 700;
    }

    .status-fantasma {
        color: #dc3545;
        font-weight: 700;
    }

    .status-atendido {
        color: #007bff;
        font-weight: 700;
    }

    .status-muerto {
        color: #dc3545;
        font-weight: 700;
    }

    .status-cliente {
        color: #28a745;
        font-weight: 700;
    }

    .status-default {
        font-weight: 700;
        color: inherit;
    }

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
        color: #6c757d;
        /* muted */
        margin-bottom: .65rem;
    }
</style>
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="order-last order-md-1 col-12 col-md-12">
                <h3>Costos por camapaña</h3>
                <br>
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
                                value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>
                        <div class="col-6 col-sm-3">
                            <label class="form-label small mb-1">Hasta</label>
                            <input type="date" name="end_date" class="form-control"
                                value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        <div class="col-6 col-sm-2">
                            <label class="form-label small mb-1">Origen</label>
                            <select name="filter_origen" id="filterOrigen" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($origenesUnicos as $origen): ?>
                                    <option value="<?php echo htmlspecialchars($origen); ?>" <?php echo ($filterOrigen === $origen) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($origen); ?>
                                    </option>
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



        </div>


    </div>



</div>

<!-- Tabla de resumen por campaña (ancho completo) -->
<div class="row">
  <div class="col-12">
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Resumen por campaña</h5>
          <button id="addGastoBtn" class="btn btn-success btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#addGastoModal">Añadir gastos por campaña</button>
        </div>
        <div class="table-responsive">
          <table id="campaignsTable" class="table table-striped table-bordered" style="width:100%">
            <thead>
              <tr>
                <th>Campaña</th>
                <th>Fecha inicio</th>
                <th>Fecha fin</th>
                <th>Total de costos ($)</th>
                <th>Total de leads</th>
                <th>Costo por lead</th>
              </tr>
            </thead>
            <tbody>
              <!-- Rellenado por JS -->
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3" style="text-align:right">Totales:</th>
                <th class="tot-costs">$0.00</th>
                <th class="tot-leads">0</th>
                <th class="tot-cost-per-lead">$0.00</th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Añadir gastos por campaña -->
<div class="modal fade" id="addGastoModal" tabindex="-1" aria-labelledby="addGastoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="addGastoForm">
        <div class="modal-header">
          <h5 class="modal-title" id="addGastoModalLabel">Añadir gastos por campaña</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Campaña</label>
            <select name="campaign_name" id="campaignSelect" class="form-select" required>
              <option value="">Seleccione...</option>
              <?php foreach ($origenesUnicos as $origen): ?>
                <option value="<?php echo htmlspecialchars($origen); ?>"><?php echo htmlspecialchars($origen); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Monto</label>
            <input type="number" step="0.01" min="0" class="form-control" name="monto" id="montoInput" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Fecha inicio</label>
            <input type="datetime-local" class="form-control" name="fecha_inicio" id="fechaInicioInput" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Fecha fin</label>
            <input type="datetime-local" class="form-control" name="fecha_fin" id="fechaFinInput" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('addGastoForm').addEventListener('submit', function(e){
    e.preventDefault();
    var campaignName = document.getElementById('campaignSelect').value;
    var monto = document.getElementById('montoInput').value;
    var fechaInicio = document.getElementById('fechaInicioInput').value;
    var fechaFin = document.getElementById('fechaFinInput').value;
    if (!campaignName) {
        Swal.fire('Error','Seleccione una campaña','error');
        return;
    }
    fetch('agregar_monto_campania.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'add_campaign_cost',
            campaign_name: campaignName,
            monto: monto,
            fecha_inicio: fechaInicio,
            fecha_fin: fechaFin
        })
    }).then(r=>r.json()).then(function(resp){
        if (resp.status === 'success') {
            var modalEl = document.getElementById('addGastoModal');
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            // Show success AFTER the modal is fully hidden to avoid leftover backdrop
            var onHidden = function() {
                // Show success alert
                Swal.fire('Listo','Gasto guardado correctamente','success');
                // Update filter select (si existe)
                try {
                    var sel = document.getElementById('filterOrigen');
                    if (sel) {
                        var exists = false;
                        for (var i=0;i<sel.options.length;i++){
                            if (sel.options[i].value === resp.nombre_campania) { exists = true; break; }
                        }
                        if (!exists) {
                            var option = document.createElement('option');
                            option.value = resp.nombre_campania;
                            option.text = resp.nombre_campania;
                            sel.appendChild(option);
                        }
                    }
                } catch(e){/* no importa */}
                // Reset form
                document.getElementById('addGastoForm').reset();
                // Refresh summary table
                loadCampaignsTable();
                modalEl.removeEventListener('hidden.bs.modal', onHidden);
                // Safety: remove any stray backdrops
                document.querySelectorAll('.modal-backdrop').forEach(function(b){ b.parentNode && b.parentNode.removeChild(b); });
            };
            modalEl.addEventListener('hidden.bs.modal', onHidden);
            modal.hide();
            // Safety: in case hidden event doesn't fire (older Bootstrap), call onHidden after 600ms
            setTimeout(function(){
                if (document.querySelectorAll('.modal-backdrop').length > 0) {
                    onHidden();
                }
            }, 600);
        } else {
            Swal.fire('Error', resp.message || 'Error al guardar', 'error');
        }
    }).catch(function(err){
        Swal.fire('Error', 'Error de red', 'error');
    });
});

// DataTable: cargar datos por campaña según rango de fechas
function loadCampaignsTable() {
    var sd = document.querySelector('input[name="start_date"]').value || '';
    var ed = document.querySelector('input[name="end_date"]').value || '';
    var fo = document.getElementById('filterOrigen') ? document.getElementById('filterOrigen').value || '' : '';
    // Show loading state
    var tbody = document.querySelector('#campaignsTable tbody');
    tbody.innerHTML = '<tr><td colspan="6">Cargando...</td></tr>';

    var params = new URLSearchParams({start_date: sd, end_date: ed, filter_origen: fo});
    fetch('get_costs_by_campaign.php?' + params.toString()).then(r => r.json()).then(function(resp){
        if (resp.status !== 'success') {
            tbody.innerHTML = '<tr><td colspan="6">Error: ' + (resp.message || 'Error al obtener datos') + '</td></tr>';
            return;
        }
        var data = resp.data || [];
        // Initialize or reinitialize DataTable
        if ($.fn.DataTable.isDataTable('#campaignsTable')) {
            $('#campaignsTable').DataTable().clear().destroy();
        }
        var table = $('#campaignsTable').DataTable({
            data: data,
            columns: [
                { data: 'campaign', title: 'Campaña' },
                { data: 'fecha_inicio', title: 'Fecha inicio', render: function(data){ return data ? (new Date(data.replace(' ', 'T'))).toLocaleString() : ''; } },
                { data: 'fecha_fin', title: 'Fecha fin', render: function(data){ return data ? (new Date(data.replace(' ', 'T'))).toLocaleString() : ''; } },
                { data: 'total_costs', title: 'Total de costos ($)', render: function(data){ var v = parseFloat(data) || 0; return '$' + v.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}); } },
                { data: 'total_leads', title: 'Total de leads' },
                { data: 'cost_per_lead', title: 'Costo por lead', render: function(data){ var v = parseFloat(data) || 0; return '$' + v.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}); } }
            ],
            order: [[3, 'desc']],
            pageLength: 25,
            responsive: true,
            autoWidth: false,
            scrollX: true,
            // Usar footerCallback para recalcular totales cada vez que la tabla se dibuja/refiltra
            footerCallback: function ( row, data, start, end, display ) {
                var api = this.api();
                var parseNum = function(i){
                    if (typeof i === 'string') {
                        var cleaned = i.replace(/[^0-9\-.,]/g, '').replace(/,/g, '');
                        return parseFloat(cleaned) || 0;
                    }
                    return parseFloat(i) || 0;
                };
                var costsData = api.column(3, {search:'applied'}).data().toArray();
                var leadsData = api.column(4, {search:'applied'}).data().toArray();
                var cplData = api.column(5, {search:'applied'}).data().toArray();

                var totalCosts = costsData.reduce(function(a,b){ return a + parseNum(b); }, 0);
                var totalLeads = leadsData.reduce(function(a,b){ return a + (parseInt(b) || 0); }, 0);
                var totalCPL = cplData.reduce(function(a,b){ return a + parseNum(b); }, 0);

                var foot = $(api.table().footer());
                if (!data || data.length === 0) {
                    foot.find('.tot-costs').text('');
                    foot.find('.tot-leads').text('');
                    foot.find('.tot-cost-per-lead').text('');
                } else {
                    foot.find('.tot-costs').text('$' + totalCosts.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}));
                    foot.find('.tot-leads').text(totalLeads);
                    foot.find('.tot-cost-per-lead').text('$' + totalCPL.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}));
                }
            }
        });
    }).catch(function(err){
        tbody.innerHTML = '<tr><td colspan="6">Error de red</td></tr>';
    });
}

// Load table on page load
document.addEventListener('DOMContentLoaded', function(){
    loadCampaignsTable();
    // Reload when date filters change
    document.querySelector('input[name="start_date"]').addEventListener('change', loadCampaignsTable);
    document.querySelector('input[name="end_date"]').addEventListener('change', loadCampaignsTable);
    // Reload when origin filter changes
    var foEl = document.getElementById('filterOrigen');
    if (foEl) foEl.addEventListener('change', loadCampaignsTable);
    // Also reload when filter form is submitted
    document.getElementById('filterForm').addEventListener('submit', function(e){
        e.preventDefault();
        loadCampaignsTable();
    });
});
</script>

<?php

include 'footer.php';

?>


</body>

</html>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/locale/es.js"></script>

<!-- Note: jQuery, Bootstrap and DataTables are loaded globally in `footer.php` to avoid duplicate conflicting versions. -->


</body>

</html>