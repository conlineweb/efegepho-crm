<?php
include 'conn.php';
header('Content-Type: application/json');

$start = isset($_GET['start_date']) && trim($_GET['start_date']) !== '' ? trim($_GET['start_date']) : null;
$end = isset($_GET['end_date']) && trim($_GET['end_date']) !== '' ? trim($_GET['end_date']) : null;
$filterOrigen = isset($_GET['filter_origen']) && trim($_GET['filter_origen']) !== '' ? trim($_GET['filter_origen']) : null;

// Normalize to full datetime boundaries if dates provided
if ($start) {
    // If start comes as YYYY-MM-DD, set 00:00:00
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $start_dt = $start . ' 00:00:00';
    } else {
        $start_dt = $start;
    }
} else {
    $start_dt = '1970-01-01 00:00:00';
}
if ($end) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $end_dt = $end . ' 23:59:59';
    } else {
        $end_dt = $end;
    }
} else {
    $end_dt = '9999-12-31 23:59:59';
}

// Ensure dates are valid
$start_ts = strtotime($start_dt);
$end_ts = strtotime($end_dt);
if ($start_ts === false || $end_ts === false) {
    echo json_encode(['status' => 'error', 'message' => 'Fechas inválidas']);
    exit;
}
$start_db = date('Y-m-d H:i:s', $start_ts);
$end_db = date('Y-m-d H:i:s', $end_ts);

// 1) Get tables from tablas_leads where tipo = 1
$tables = [];
$res = $conn->query("SELECT nombre FROM tablas_leads WHERE tipo = 1");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $tables[] = $r['nombre'];
    }
}

// Build optional filter condition for campaign/origen
$filterCond = '';
if ($filterOrigen) {
    // If filter is like c123, include campaign_name = 'c123' OR campaign_name LIKE 'c123.%'
    if (preg_match('/^c\d+$/i', $filterOrigen)) {
        $f = $conn->real_escape_string($filterOrigen);
        $filterCond = " AND (campaign_name = '" . $f . "' OR campaign_name LIKE '" . $f . ".%')";
    } else {
        $f = $conn->real_escape_string($filterOrigen);
        $filterCond = " AND campaign_name = '" . $f . "'";
    }
}

// 2) Aggregate leads per campaign across tables
$leadsCount = []; // campaign => count
foreach ($tables as $table) {
    $safeTable = $conn->real_escape_string($table);
    // We'll select campaign_name and created_time, and filter by date using a parsed datetime
    $sql = "SELECT campaign_name, COUNT(*) as cnt FROM `" . $safeTable . "` WHERE campaign_name IS NOT NULL AND TRIM(campaign_name) <> '' " . $filterCond . " AND (STR_TO_DATE(LEFT(REPLACE(created_time,'T',' '),19),'%Y-%m-%d %H:%i:%s') BETWEEN '" . $conn->real_escape_string($start_db) . "' AND '" . $conn->real_escape_string($end_db) . "') GROUP BY campaign_name";
    $r2 = $conn->query($sql);
    if ($r2) {
        while ($rr = $r2->fetch_assoc()) {
            $name = trim($rr['campaign_name']);
            if ($name === '') continue;
            if (!isset($leadsCount[$name])) $leadsCount[$name] = 0;
            $leadsCount[$name] += intval($rr['cnt']);
        }
    }
}

// 3) Get individual cost records from costo_campanias and apply date filters on fecha_inicio/fecha_fin.
//    Behavior:
//      - If both start_date and end_date are provided, return costs where fecha_inicio >= start_date AND fecha_fin <= end_date (fully inside).
//      - If only start_date is provided, return costs where fecha_inicio >= start_date.
//      - If only end_date is provided, return costs where fecha_fin <= end_date.
//    We still apply optional filter on campaign name when provided.
$costRows = []; // array of cost entries with lead counts
$costQuery = "SELECT id, nombre_campania, monto, fecha_inicio, fecha_fin FROM costo_campanias WHERE 1=1";
// Apply date filters to the cost records themselves
if ($start && $end) {
    $costQuery .= " AND fecha_inicio >= '" . $conn->real_escape_string($start_db) . "' AND fecha_fin <= '" . $conn->real_escape_string($end_db) . "'";
} elseif ($start) {
    $costQuery .= " AND fecha_inicio >= '" . $conn->real_escape_string($start_db) . "'";
} elseif ($end) {
    $costQuery .= " AND fecha_fin <= '" . $conn->real_escape_string($end_db) . "'";
}
if ($filterOrigen) {
    if (preg_match('/^c\d+$/i', $filterOrigen)) {
        $f = $conn->real_escape_string($filterOrigen);
        $costQuery .= " AND (nombre_campania = '" . $f . "' OR nombre_campania LIKE '" . $f . ".%')";
    } else {
        $f = $conn->real_escape_string($filterOrigen);
        $costQuery .= " AND nombre_campania = '" . $f . "'";
    }
}
$costQuery .= " ORDER BY nombre_campania, fecha_inicio";
$resCosts = $conn->query($costQuery);
if ($resCosts) {
    while ($rc = $resCosts->fetch_assoc()) {
        $name = trim($rc['nombre_campania']);
        if ($name === '') continue;
        $monto = floatval($rc['monto']);
        // Normalize cost interval
        $cStartTs = strtotime($rc['fecha_inicio']);
        $cEndTs = strtotime($rc['fecha_fin']);
        $cStart = ($cStartTs === false) ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', $cStartTs);
        $cEnd = ($cEndTs === false) ? '9999-12-31 23:59:59' : date('Y-m-d H:i:s', $cEndTs);

        // Count leads for this cost interval across all tables
        $leadsForCost = 0;
        foreach ($tables as $table) {
            $safeTable = $conn->real_escape_string($table);
            if (preg_match('/^c\d+$/i', $name)) {
                $n = $conn->real_escape_string($name);
                $cond = "AND (campaign_name = '" . $n . "' OR campaign_name LIKE '" . $n . ".%')";
            } else {
                $n = $conn->real_escape_string($name);
                $cond = "AND campaign_name = '" . $n . "'";
            }
            $sql = "SELECT COUNT(*) AS cnt FROM `" . $safeTable . "` WHERE campaign_name IS NOT NULL AND TRIM(campaign_name) <> '' " . $cond . " AND (STR_TO_DATE(LEFT(REPLACE(created_time,'T',' '),19),'%Y-%m-%d %H:%i:%s') BETWEEN '" . $conn->real_escape_string($cStart) . "' AND '" . $conn->real_escape_string($cEnd) . "')";
            $r2 = $conn->query($sql);
            if ($r2 && ($rowCount = $r2->fetch_assoc())) {
                $leadsForCost += intval($rowCount['cnt']);
            }
        }

        $costRows[] = [
            'id' => intval($rc['id']),
            'nombre_campania' => $name,
            'monto' => $monto,
            'fecha_inicio' => $rc['fecha_inicio'],
            'fecha_fin' => $rc['fecha_fin'],
            'total_leads' => $leadsForCost
        ];
    }
}

// Also keep the legacy aggregated leads counts per campaign (within the user-selected start/end range)
// so we can include campaigns that have leads but no cost record in the filtered range.
// (This preserves previous behavior where campaigns without cost showed up with zero costs.)
// 4) Build final rows: one row per cost record, showing the leads that occurred during that cost's interval.
$rows = [];
$seenCampaigns = [];
foreach ($costRows as $cr) {
    $leads = isset($cr['total_leads']) ? intval($cr['total_leads']) : 0;
    $totalCost = isset($cr['monto']) ? floatval($cr['monto']) : 0.0;
    $costPerLead = ($leads > 0) ? round($totalCost / $leads, 2) : 0.00;
    $rows[] = [
        'campaign' => $cr['nombre_campania'],
        'total_costs' => number_format($totalCost, 2, '.', ''),
        'total_leads' => $leads,
        'cost_per_lead' => number_format($costPerLead, 2, '.', ''),
        'fecha_inicio' => $cr['fecha_inicio'],
        'fecha_fin' => $cr['fecha_fin'],
        'cost_id' => $cr['id']
    ];
    $seenCampaigns[] = $cr['nombre_campania'];
}



// Return results
echo json_encode(['status' => 'success', 'data' => $rows]);
$conn->close();
exit;