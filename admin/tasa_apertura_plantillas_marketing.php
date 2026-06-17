<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';

// Helper: format dates like in consulta_leads
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

// Fetch all marketing templates
$templates = [];
$res = $conn->query("SELECT id, nombre, sent_log, created_at FROM marketing_templates ORDER BY id DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $templates[] = $r;
    }
}

// Ensure clicks table/column exists so report can run even before first tracked click
$conn->query("CREATE TABLE IF NOT EXISTS `email_clicks` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT DEFAULT NULL,
    `tabla_origen` VARCHAR(255) DEFAULT NULL,
    `correo` TINYINT DEFAULT NULL,
    `template_id` INT DEFAULT NULL,
    `url` TEXT,
    `clicked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `ip` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

$ckClickTpl = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_clicks' AND COLUMN_NAME = 'template_id'");
if ($ckClickTpl && $ckClickTpl->num_rows === 0) {
    $conn->query("ALTER TABLE `email_clicks` ADD COLUMN `template_id` INT NULL AFTER `correo`");
}

$idxClick = $conn->query("SHOW INDEX FROM `email_clicks` WHERE Key_name = 'idx_template_click'");
if ($idxClick && $idxClick->num_rows === 0) {
    $conn->query("ALTER TABLE `email_clicks` ADD INDEX `idx_template_click` (`template_id`, `tabla_origen`(100), `lead_id`, `clicked_at`)");
}

// Prepare statements to check opens (will bind params inside loop)
$stmtOpenWithTemplate = $conn->prepare("SELECT opened_at FROM email_opens WHERE tabla_origen = ? AND lead_id = ? AND template_id = ? AND opened_at >= ? ORDER BY opened_at ASC LIMIT 1");
$stmtOpenNoTemplate = $conn->prepare("SELECT opened_at FROM email_opens WHERE tabla_origen = ? AND lead_id = ? AND opened_at >= ? ORDER BY opened_at ASC LIMIT 1");
$stmtAnyOpen = $conn->prepare("SELECT opened_at FROM email_opens WHERE tabla_origen = ? AND lead_id = ? ORDER BY opened_at ASC LIMIT 1");
$stmtClickAgg = $conn->prepare("SELECT COUNT(*) AS total_clicks, COUNT(DISTINCT CONCAT(tabla_origen, '|', lead_id)) AS unique_clicks, MAX(clicked_at) AS last_click FROM email_clicks WHERE template_id = ?");

// compute stats per template
$stats = [];
foreach ($templates as $t) {
    $id = intval($t['id']);
    $nombre = $t['nombre'] ?? '';
    $sentLogRaw = $t['sent_log'] ?? null;
    $entries = [];
    if (!empty($sentLogRaw)) {
        $decoded = json_decode($sentLogRaw, true);
        if (is_array($decoded)) $entries = $decoded;
    }

    $sentSuccess = 0;
    $openedLeads = []; // unique by tabla_origen + lead_id
    $lastOpen = null;

    foreach ($entries as $entry) {
        $success = isset($entry['success']) ? intval($entry['success']) : 0;
        if ($success !== 1) continue; // only count successful sends
        $sentSuccess++;
        $tabla = preg_replace('/[^a-zA-Z0-9_]/','', $entry['tabla_origen'] ?? '');
        $leadId = intval($entry['lead_id'] ?? 0);
        $sentAt = $entry['sent_at'] ?? null;
        if ($tabla === '' || $leadId <= 0) continue;

        $templateIdEntry = isset($entry['template_id']) ? intval($entry['template_id']) : null;

        $foundOpen = false;
        $ro = null;
        // 1) Prefer any open that includes the template_id (strongest match), regardless of timing
        if ($templateIdEntry !== null && $stmtOpenWithTemplate) {
            // template-only query (ignore sentAt) to catch opens even if time comparisons fail
            $q = $conn->prepare("SELECT opened_at FROM email_opens WHERE tabla_origen = ? AND lead_id = ? AND template_id = ? ORDER BY opened_at ASC LIMIT 1");
            if ($q) {
                $q->bind_param('sii', $tabla, $leadId, $templateIdEntry);
                $q->execute();
                $resOpen = $q->get_result();
                if ($resOpen && $resOpen->num_rows > 0) {
                    $ro = $resOpen->fetch_assoc();
                    $foundOpen = true;
                }
                $q->close();
            }
        }

        // 2) If not found by template, try opening after sentAt (original approach)
        if (!$foundOpen && $stmtOpenNoTemplate && !empty($sentAt)) {
            $stmtOpenNoTemplate->bind_param('sis', $tabla, $leadId, $sentAt);
            $stmtOpenNoTemplate->execute();
            $resOpen = $stmtOpenNoTemplate->get_result();
            if ($resOpen && $resOpen->num_rows > 0) {
                $ro = $resOpen->fetch_assoc();
                $foundOpen = true;
            }
        }

        // 3) fallback: any open for this lead/table
        if (!$foundOpen && $stmtAnyOpen) {
            $stmtAnyOpen->bind_param('si', $tabla, $leadId);
            $stmtAnyOpen->execute();
            $resOpen = $stmtAnyOpen->get_result();
            if ($resOpen && $resOpen->num_rows > 0) {
                $ro = $resOpen->fetch_assoc();
                $foundOpen = true;
            }
        }

        if ($foundOpen && $ro) {
            // mark lead as opened (unique per lead)
            $leadKey = $tabla . '|' . $leadId;
            $openedLeads[$leadKey] = true;
            if ($lastOpen === null || strtotime($ro['opened_at']) > strtotime($lastOpen)) $lastOpen = $ro['opened_at'];
        }

    }

    $uniqueOpens = count($openedLeads);
    $openRate = ($sentSuccess > 0) ? round(($uniqueOpens / $sentSuccess) * 100, 2) : 0;

    $totalClicks = 0;
    $uniqueClickLeads = 0;
    $lastClick = null;
    if ($stmtClickAgg) {
        $stmtClickAgg->bind_param('i', $id);
        $stmtClickAgg->execute();
        $resClickAgg = $stmtClickAgg->get_result();
        if ($resClickAgg && $resClickAgg->num_rows > 0) {
            $rClickAgg = $resClickAgg->fetch_assoc();
            $totalClicks = intval($rClickAgg['total_clicks'] ?? 0);
            $uniqueClickLeads = intval($rClickAgg['unique_clicks'] ?? 0);
            $lastClick = $rClickAgg['last_click'] ?? null;
        }
    }

    $clickRateUnique = ($sentSuccess > 0) ? round(($uniqueClickLeads / $sentSuccess) * 100, 2) : 0;
    $clickRateTotal = ($sentSuccess > 0) ? round(($totalClicks / $sentSuccess) * 100, 2) : 0;

    $stats[] = [
        'id' => $id,
        'nombre' => $nombre,
        'created_at' => $t['created_at'] ?? null,
        'sent' => $sentSuccess,
        'opens' => $uniqueOpens,
        'open_rate' => $openRate,
        'last_open' => $lastOpen,
        'clicks_unique' => $uniqueClickLeads,
        'clicks_total' => $totalClicks,
        'click_rate_unique' => $clickRateUnique,
        'click_rate_total' => $clickRateTotal,
        'last_click' => $lastClick
    ];
}

// Aggregate KPI totals
$kpiTotalTemplates  = count($stats);
$kpiTotalSent       = array_sum(array_column($stats, 'sent'));
$kpiTotalOpens      = array_sum(array_column($stats, 'opens'));
$kpiTotalClicks     = array_sum(array_column($stats, 'clicks_total'));
$kpiAvgOpenRate     = ($kpiTotalSent > 0) ? round(($kpiTotalOpens / $kpiTotalSent) * 100, 1) : 0;
$kpiAvgClickRate    = ($kpiTotalSent > 0) ? round(($kpiTotalClicks / $kpiTotalSent) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tasa apertura - Plantillas marketing</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        /* ── EFEGE Dashboard — Light Mode ── */
        :root {
            --gold:         #B8860B;
            --gold-dim:     rgba(184,134,11,0.10);
            --panel:        #FFFFFF;
            --surface:      #F4F6FA;
            --ink:          #1E2535;
            --muted:        #6B7896;
            --border:       #E3E8F0;
            --green:        #059669;
            --green-bg:     rgba(5,150,105,0.10);
            --blue:         #2563EB;
            --blue-bg:      rgba(37,99,235,0.10);
            --amber:        #D97706;
            --amber-bg:     rgba(217,119,6,0.10);
            --purple:       #7C3AED;
            --purple-bg:    rgba(124,58,237,0.10);
            --radius:       12px;
        }

        .efege-dash {
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            color: var(--ink);
        }

        /* ── KPI strip ── */
        .efege-kpi-strip {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .efege-kpi-card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 22px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }

        .efege-kpi-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--accent, var(--gold));
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .efege-kpi-label {
            font-size: 10px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .efege-kpi-value {
            font-size: 32px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 8px;
            color: var(--accent, var(--gold));
        }

        .efege-kpi-sub {
            font-size: 11px;
            color: var(--muted);
        }

        /* ── Section header ── */
        .efege-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .efege-section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 20px;
            color: var(--ink);
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .efege-section-sub {
            font-size: 11px;
            color: var(--muted);
        }

        /* ── Main table card ── */
        .efege-table-card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }

        .efege-table-card-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface);
        }

        .efege-table-card-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
        }

        /* Override DataTables inside our light card */
        .efege-table-card table.dataTable thead th,
        .efege-table-card table.dataTable thead td {
            background: var(--surface) !important;
            color: var(--muted) !important;
            font-size: 10px !important;
            letter-spacing: 1.5px !important;
            text-transform: uppercase !important;
            border-bottom: 1px solid var(--border) !important;
            font-weight: 600 !important;
            white-space: nowrap;
            padding: 11px 14px !important;
        }

        .efege-table-card table.dataTable tbody tr {
            background: var(--panel) !important;
            border-bottom: 1px solid var(--border) !important;
            transition: background 0.15s;
        }

        .efege-table-card table.dataTable tbody tr:hover {
            background: var(--surface) !important;
        }

        .efege-table-card table.dataTable tbody td {
            color: var(--ink) !important;
            border-top: none !important;
            padding: 12px 14px !important;
            vertical-align: middle;
            white-space: nowrap;
        }

        /* Pill badges */
        .efege-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.4;
        }

        .efege-pill-green  { background: var(--green-bg);  color: var(--green);  }
        .efege-pill-blue   { background: var(--blue-bg);   color: var(--blue);   }
        .efege-pill-amber  { background: var(--amber-bg);  color: var(--amber);  }
        .efege-pill-gold   { background: var(--gold-dim);  color: var(--gold);   }
        .efege-pill-purple { background: var(--purple-bg); color: var(--purple); }
        .efege-pill-muted  { background: rgba(107,120,150,0.10); color: var(--muted); }

        /* Rate bar inline */
        .rate-bar-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rate-bar-track {
            flex: 1;
            min-width: 60px;
            height: 6px;
            background: var(--surface);
            border-radius: 3px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .rate-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.6s ease;
        }

        .rate-bar-label {
            font-size: 12px;
            font-weight: 700;
            min-width: 38px;
            text-align: right;
        }

        /* Template name cell */
        .tpl-name { font-weight: 600; color: var(--ink); }
        .tpl-id   { font-size: 10px; color: var(--muted); margin-top: 2px; }

        /* Action buttons */
        .efege-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--muted);
            cursor: pointer;
            transition: all 0.18s;
            font-family: 'DM Sans', sans-serif;
        }

        .efege-btn:hover { color: var(--ink); border-color: #C5CAD8; background: #EAECF4; }
        .efege-btn.open  { color: var(--amber);  border-color: rgba(217,119,6,0.3);  background: var(--amber-bg);  }
        .efege-btn.open:hover  { background: rgba(217,119,6,0.18); }
        .efege-btn.click { color: var(--green);  border-color: rgba(5,150,105,0.3);  background: var(--green-bg);  }
        .efege-btn.click:hover { background: rgba(5,150,105,0.18); }
        .efege-btn.sent  { color: var(--blue);   border-color: rgba(37,99,235,0.3);  background: var(--blue-bg);   }
        .efege-btn.sent:hover  { background: rgba(37,99,235,0.18); }

        /* DataTables wrapper overrides */
        .efege-table-card .dataTables_wrapper .dataTables_length label,
        .efege-table-card .dataTables_wrapper .dataTables_filter label,
        .efege-table-card .dataTables_wrapper .dataTables_info,
        .efege-table-card .dataTables_wrapper .dataTables_paginate {
            color: var(--muted) !important;
            font-size: 12px !important;
        }

        .efege-table-card .dataTables_wrapper .dataTables_filter input,
        .efege-table-card .dataTables_wrapper .dataTables_length select {
            background: var(--panel) !important;
            border: 1px solid var(--border) !important;
            color: var(--ink) !important;
            border-radius: 7px !important;
            padding: 5px 10px !important;
            font-family: 'DM Sans', sans-serif !important;
            font-size: 12px !important;
        }

        .efege-table-card .dataTables_wrapper .dataTables_filter input:focus,
        .efege-table-card .dataTables_wrapper .dataTables_length select:focus {
            outline: none !important;
            border-color: var(--gold) !important;
        }

        .efege-table-card .dataTables_wrapper .paginate_button {
            background: var(--surface) !important;
            border: 1px solid var(--border) !important;
            color: var(--muted) !important;
            border-radius: 6px !important;
            margin: 0 2px !important;
        }

        .efege-table-card .dataTables_wrapper .paginate_button.current,
        .efege-table-card .dataTables_wrapper .paginate_button.current:hover {
            background: var(--gold-dim) !important;
            border-color: rgba(184,134,11,0.3) !important;
            color: var(--gold) !important;
        }

        .efege-table-card .dataTables_wrapper .paginate_button:hover {
            background: #EAECF4 !important;
            color: var(--ink) !important;
        }

        .efege-table-card .dataTables_wrapper {
            padding: 16px 20px 20px;
        }

        /* ── Modal light override ── */
        #modalTemplateSentLeads .modal-content {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
        }

        #modalTemplateSentLeads .modal-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 16px 20px;
        }

        #modalTemplateSentLeads .modal-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 18px;
            font-weight: 600;
            color: var(--ink);
            letter-spacing: 0.3px;
        }

        #modalTemplateSentLeads .modal-footer {
            background: var(--surface);
            border-top: 1px solid var(--border);
            padding: 12px 20px;
        }

        #modalTemplateSentLeads .modal-body {
            background: var(--panel);
            padding: 0;
        }

        /* Modal inner table */
        .efege-modal-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .efege-modal-table thead th {
            background: var(--surface);
            color: var(--muted);
            font-size: 10px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            font-weight: 600;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .efege-modal-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.12s;
        }

        .efege-modal-table tbody tr:last-child { border-bottom: none; }
        .efege-modal-table tbody tr:hover { background: var(--surface); }

        .efege-modal-table tbody td {
            padding: 10px 14px;
            color: var(--ink);
            vertical-align: middle;
        }

        .efege-modal-badge-yes  { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:700; background:var(--green-bg); color:var(--green); }
        .efege-modal-badge-no   { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:700; background:rgba(107,120,150,0.10); color:var(--muted); }
        .efege-modal-badge-fail { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:700; background:rgba(220,38,38,0.10); color:#DC2626; }

        #modalTemplateSentLeads .btn-secondary {
            background: var(--surface);
            border-color: var(--border);
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            border-radius: 7px;
            padding: 6px 16px;
            font-family: 'DM Sans', sans-serif;
        }

        #modalTemplateSentLeads .btn-secondary:hover {
            background: #EAECF4;
            color: var(--ink);
            border-color: #C5CAD8;
        }

        @media (max-width: 900px) {
            .efege-kpi-strip { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 600px) {
            .efege-kpi-strip { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="page-heading">

        <div class="efege-dash">

            <!-- ── Section header ── -->
            <div class="efege-section-header mb-3">
                <div>
                    <div class="efege-section-title">Plantillas de Marketing</div>
                    <div class="efege-section-sub mt-1">Rendimiento de envíos · Tasa de apertura y clics por plantilla</div>
                </div>
            </div>

            <!-- ── KPI Strip ── -->
            <div class="efege-kpi-strip">
                <div class="efege-kpi-card" style="--accent: var(--blue);">
                    <div class="efege-kpi-label">Plantillas activas</div>
                    <div class="efege-kpi-value"><?php echo $kpiTotalTemplates; ?></div>
                    <div class="efege-kpi-sub">en base de datos</div>
                </div>
                <div class="efege-kpi-card" style="--accent: var(--muted);">
                    <div class="efege-kpi-label">Correos enviados</div>
                    <div class="efege-kpi-value" style="color:var(--ink)"><?php echo number_format($kpiTotalSent); ?></div>
                    <div class="efege-kpi-sub">envíos exitosos totales</div>
                </div>
                <div class="efege-kpi-card" style="--accent: var(--amber);">
                    <div class="efege-kpi-label">Tasa de apertura</div>
                    <div class="efege-kpi-value" style="color:var(--amber)"><?php echo $kpiAvgOpenRate; ?>%</div>
                    <div class="efege-kpi-sub"><?php echo number_format($kpiTotalOpens); ?> aperturas únicas</div>
                </div>
                <div class="efege-kpi-card" style="--accent: var(--green);">
                    <div class="efege-kpi-label">Tasa de clics</div>
                    <div class="efege-kpi-value" style="color:var(--green)"><?php echo $kpiAvgClickRate; ?>%</div>
                    <div class="efege-kpi-sub"><?php echo number_format($kpiTotalClicks); ?> clics totales</div>
                </div>
                <div class="efege-kpi-card" style="--accent: var(--gold);">
                    <div class="efege-kpi-label">Click-to-Open</div>
                    <?php $cto = ($kpiTotalOpens > 0) ? round(($kpiTotalClicks / $kpiTotalOpens) * 100, 1) : 0; ?>
                    <div class="efege-kpi-value" style="color:var(--gold)"><?php echo $cto; ?>%</div>
                    <div class="efege-kpi-sub">clics sobre aperturas</div>
                </div>
            </div>

            <!-- ── Main table ── -->
            <div class="efege-table-card">
                <div class="efege-table-card-header">
                    <span class="efege-table-card-title">Detalle por plantilla</span>
                    <span style="font-size:11px; color:var(--muted);"><?php echo $kpiTotalTemplates; ?> plantillas</span>
                </div>
                <div class="table-responsive">
                    <table id="templatesRateTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Plantilla</th>
                                <th>Creada</th>
                                <th>Enviados</th>
                                <th>Aperturas únicas</th>
                                <th>Tasa apertura</th>
                                <th>Última apertura</th>
                                <th>Clics únicos</th>
                                <th>Clics totales</th>
                                <th>Tasa clic única</th>
                                <th>Tasa clic total</th>
                                <th>Último clic</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats as $s): ?>
                                <?php
                                    // Color for open rate
                                    $or = floatval($s['open_rate']);
                                    if ($or >= 40)       { $orClass = 'efege-pill-green';  $orBarColor = '#10B981'; }
                                    elseif ($or >= 20)   { $orClass = 'efege-pill-blue';   $orBarColor = '#3B82F6'; }
                                    elseif ($or > 0)     { $orClass = 'efege-pill-amber';  $orBarColor = '#F59E0B'; }
                                    else                 { $orClass = 'efege-pill-muted';  $orBarColor = '#6B7896'; }

                                    // Color for click rate
                                    $cr = floatval($s['click_rate_total']);
                                    if ($cr >= 10)       { $crClass = 'efege-pill-green';  $crBarColor = '#10B981'; }
                                    elseif ($cr >= 3)    { $crClass = 'efege-pill-blue';   $crBarColor = '#3B82F6'; }
                                    elseif ($cr > 0)     { $crClass = 'efege-pill-amber';  $crBarColor = '#F59E0B'; }
                                    else                 { $crClass = 'efege-pill-muted';  $crBarColor = '#6B7896'; }
                                ?>
                                <tr>
                                    <td>
                                        <div class="tpl-name"><?php echo htmlspecialchars($s['nombre']); ?></div>
                                        <div class="tpl-id">#<?php echo $s['id']; ?></div>
                                    </td>
                                    <td style="color:var(--muted); font-size:12px;"><?php echo !empty($s['created_at']) ? date('d/m/Y', strtotime($s['created_at'])) : '—'; ?></td>
                                    <td>
                                        <span class="efege-pill efege-pill-muted"><?php echo number_format(intval($s['sent'])); ?></span>
                                    </td>
                                    <td>
                                        <span class="efege-pill efege-pill-amber"><?php echo intval($s['opens']); ?></span>
                                    </td>
                                    <td>
                                        <div class="rate-bar-wrap">
                                            <div class="rate-bar-track">
                                                <div class="rate-bar-fill" style="width:<?php echo min(100, $or); ?>%; background:<?php echo $orBarColor; ?>;"></div>
                                            </div>
                                            <span class="rate-bar-label <?php echo $orClass; ?>" style="background:none; padding:0;"><?php echo $s['open_rate']; ?>%</span>
                                        </div>
                                    </td>
                                    <td style="color:var(--muted); font-size:11px;"><?php echo !empty($s['last_open']) ? formatCreatedTime($s['last_open']) : '<span style="color:var(--muted)">N/A</span>'; ?></td>
                                    <td>
                                        <span class="efege-pill efege-pill-green"><?php echo intval($s['clicks_unique']); ?></span>
                                    </td>
                                    <td>
                                        <span class="efege-pill efege-pill-blue"><?php echo intval($s['clicks_total']); ?></span>
                                    </td>
                                    <td>
                                        <div class="rate-bar-wrap">
                                            <div class="rate-bar-track">
                                                <div class="rate-bar-fill" style="width:<?php echo min(100, floatval($s['click_rate_unique'])); ?>%; background:<?php echo $crBarColor; ?>;"></div>
                                            </div>
                                            <span class="rate-bar-label" style="color:<?php echo $crBarColor; ?>;"><?php echo $s['click_rate_unique']; ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="rate-bar-wrap">
                                            <div class="rate-bar-track">
                                                <div class="rate-bar-fill" style="width:<?php echo min(100, $cr); ?>%; background:<?php echo $crBarColor; ?>;"></div>
                                            </div>
                                            <span class="rate-bar-label" style="color:<?php echo $crBarColor; ?>;"><?php echo $s['click_rate_total']; ?>%</span>
                                        </div>
                                    </td>
                                    <td style="color:var(--muted); font-size:11px;"><?php echo !empty($s['last_click']) ? formatCreatedTime($s['last_click']) : '<span style="color:var(--muted)">N/A</span>'; ?></td>
                                    <td>
                                        <div style="display:flex; gap:5px; flex-wrap:wrap;">
                                            <button class="efege-btn sent btn-view-sent" data-id="<?php echo $s['id']; ?>">✉ Envíos</button>
                                            <button class="efege-btn open btn-view-openers" data-id="<?php echo $s['id']; ?>">👁 Aperturas</button>
                                            <button class="efege-btn click btn-view-clickers" data-id="<?php echo $s['id']; ?>">🖱 Clics</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /efege-dash -->

        <!-- Modal: Leads enviados por plantilla (reused) -->
        <div class="modal fade" id="modalTemplateSentLeads" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTemplateSentLeadsTitle">Leads enviados</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body" id="modalTemplateSentLeadsBody" style="max-height:65vh; overflow:auto;">
                        <div class="py-3 text-center" style="color:var(--muted)">Cargando...</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function () {
            var dt = $('#templatesRateTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                responsive: true,
                language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
                drawCallback: function () {
                    // Re-wrap table inside our dark card after each draw
                    var $wrap = $('#templatesRateTable').closest('.table-responsive');
                    if (!$wrap.closest('.efege-table-card').length) {
                        $wrap.wrap('<div class="efege-table-card"><div class="efege-table-card-header"><span class="efege-table-card-title">Detalle por plantilla</span></div></div>');
                    }
                }
            });
            // Wrap the DataTables toolbar inside the card
            $('#templatesRateTable').closest('.efege-table-card').addClass('efege-table-card');

            $('#templatesRateTable').on('click', '.btn-view-sent', function () {
                const id = $(this).data('id');
                const $body = $('#modalTemplateSentLeadsBody');
                $('#modalTemplateSentLeadsTitle').text('Leads enviados — Plantilla ' + id);
                $body.html('<div class="py-3 text-center">Cargando...</div>');
                const modal = new bootstrap.Modal(document.getElementById('modalTemplateSentLeads'));
                modal.show();

                $.post('fetch_template_sent_leads.php', { template_id: id }, function (resp) {
                    if (!resp || !resp.success) {
                        $body.html('<div class="p-3 text-danger">Error: ' + (resp && resp.message ? resp.message : 'Sin datos') + '</div>');
                        return;
                    }
                    const rows = resp.data || [];
                    if (!rows.length) {
                        $body.html('<div class="p-4" style="color:var(--muted);text-align:center;font-size:13px;">No se encontraron envíos para esta plantilla.</div>');
                        return;
                    }
                    let html = '<div class="table-responsive"><table class="efege-modal-table"><thead><tr><th>Lead ID</th><th>Tabla</th><th>Nombre</th><th>Correo</th><th>Enviado</th><th>Éxito</th><th>Abrió</th><th>Abierto a las</th><th>Hizo clic</th><th>Primer clic</th><th># Clics</th></tr></thead><tbody>';
                    rows.forEach(function (r) {
                        const sentAt = r.sent_at ? r.sent_at : '—';
                        const success = r.success == 1 ? '<span class="efege-modal-badge-yes">✓ Sí</span>' : '<span class="efege-modal-badge-fail">✗ No</span>';
                        const opened = r.opened == 1 ? '<span class="efege-modal-badge-yes">✓ Sí</span>' : '<span class="efege-modal-badge-no">No</span>';
                        const openedAt = r.opened_at ? r.opened_at : '<span style="color:var(--muted)">—</span>';
                        const clicked = r.clicked == 1 ? '<span class="efege-modal-badge-yes">✓ Sí</span>' : '<span class="efege-modal-badge-no">No</span>';
                        const clickedAt = r.clicked_at ? r.clicked_at : '<span style="color:var(--muted)">—</span>';
                        const clickCount = parseInt(r.click_count || 0, 10);
                        const name = r.lead_name ? $('<div>').text(r.lead_name).html() : '<span style="color:var(--muted)">—</span>';
                        const correo = r.email ? $('<div>').text(r.email).html() : '<span style="color:var(--muted)">—</span>';
                        html += '<tr><td style="color:var(--muted);font-size:11px;">' + $('<div>').text(r.lead_id).html() + '</td><td style="color:var(--muted);font-size:11px;">' + $('<div>').text(r.tabla_origen).html() + '</td><td style="font-weight:600;">' + name + '</td><td style="color:var(--muted);">' + correo + '</td><td style="color:var(--muted);font-size:11px;">' + $('<div>').text(sentAt).html() + '</td><td>' + success + '</td><td>' + opened + '</td><td style="color:var(--muted);font-size:11px;">' + openedAt + '</td><td>' + clicked + '</td><td style="color:var(--muted);font-size:11px;">' + clickedAt + '</td><td style="font-weight:700;color:var(--ink);">' + $('<div>').text(clickCount).html() + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                    $body.html(html);
                }, 'json').fail(function () {
                    $body.html('<div class="p-3 text-danger">Error al obtener datos del servidor</div>');
                });
            });

            // view openers (leads that actually opened this template)
            $('#templatesRateTable').on('click', '.btn-view-openers', function () {
                const id = $(this).data('id');
                const $body = $('#modalTemplateSentLeadsBody');
                $('#modalTemplateSentLeadsTitle').text('Aperturas — Plantilla ' + id);
                $body.html('<div class="py-3 text-center">Cargando...</div>');
                const modal = new bootstrap.Modal(document.getElementById('modalTemplateSentLeads'));
                modal.show();

                $.post('fetch_template_sent_leads.php', { template_id: id }, function (resp) {
                    if (!resp || !resp.success) {
                        $body.html('<div class="p-3 text-danger">Error: ' + (resp && resp.message ? resp.message : 'Sin datos') + '</div>');
                        return;
                    }
                    const rows = (resp.data || []).filter(r => parseInt(r.opened) === 1);
                    if (!rows.length) {
                        $body.html('<div class="p-4" style="color:var(--muted);text-align:center;font-size:13px;">Ningún lead ha abierto esta plantilla todavía.</div>');
                        return;
                    }
                    let html = '<div class="table-responsive"><table class="efege-modal-table"><thead><tr><th>Lead ID</th><th>Tabla</th><th>Nombre</th><th>Correo</th><th>Abierto a las</th></tr></thead><tbody>';
                    rows.forEach(function (r) {
                        const openedAt = r.opened_at ? r.opened_at : '<span style="color:var(--muted)">—</span>';
                        const name = r.lead_name ? $('<div>').text(r.lead_name).html() : '<span style="color:var(--muted)">—</span>';
                        const correo = r.email ? $('<div>').text(r.email).html() : '<span style="color:var(--muted)">—</span>';
                        html += '<tr><td style="color:var(--muted);font-size:11px;">' + $('<div>').text(r.lead_id).html() + '</td><td style="color:var(--muted);font-size:11px;">' + $('<div>').text(r.tabla_origen).html() + '</td><td style="font-weight:600;">' + name + '</td><td style="color:var(--muted);">' + correo + '</td><td style="color:var(--muted);font-size:11px;">' + openedAt + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                    $body.html(html);
                }, 'json').fail(function () {
                    $body.html('<div class="p-3 text-danger">Error al obtener datos del servidor</div>');
                });
            });

            // view clickers (leads that clicked schedule now)
            $('#templatesRateTable').on('click', '.btn-view-clickers', function () {
                const id = $(this).data('id');
                const $body = $('#modalTemplateSentLeadsBody');
                $('#modalTemplateSentLeadsTitle').text('Clics — Plantilla ' + id);
                $body.html('<div class="py-3 text-center">Cargando...</div>');
                const modal = new bootstrap.Modal(document.getElementById('modalTemplateSentLeads'));
                modal.show();

                $.post('fetch_template_sent_leads.php', { template_id: id }, function (resp) {
                    if (!resp || !resp.success) {
                        $body.html('<div class="p-3 text-danger">Error: ' + (resp && resp.message ? resp.message : 'Sin datos') + '</div>');
                        return;
                    }
                    const rows = (resp.data || []).filter(r => parseInt(r.clicked) === 1);
                    if (!rows.length) {
                        $body.html('<div class="p-4" style="color:var(--muted);text-align:center;font-size:13px;">Ningún lead ha dado clic en Schedule now para esta plantilla.</div>');
                        return;
                    }
                    let html = '<div class="table-responsive"><table class="efege-modal-table"><thead><tr><th>Lead ID</th><th>Tabla</th><th>Nombre</th><th>Correo</th><th>Primer clic</th><th># Clics</th></tr></thead><tbody>';
                    rows.forEach(function (r) {
                        const clickedAt = r.clicked_at ? r.clicked_at : '<span style="color:var(--muted)">—</span>';
                        const clickCount = parseInt(r.click_count || 0, 10);
                        const name = r.lead_name ? $('<div>').text(r.lead_name).html() : '<span style="color:var(--muted)">—</span>';
                        const correo = r.email ? $('<div>').text(r.email).html() : '<span style="color:var(--muted)">—</span>';
                        html += '<tr><td style="color:var(--muted);font-size:11px;">' + $('<div>').text(r.lead_id).html() + '</td><td style="color:var(--muted);font-size:11px;">' + $('<div>').text(r.tabla_origen).html() + '</td><td style="font-weight:600;">' + name + '</td><td style="color:var(--muted);">' + correo + '</td><td style="color:var(--muted);font-size:11px;">' + clickedAt + '</td><td style="font-weight:700;color:var(--blue);">' + $('<div>').text(clickCount).html() + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                    $body.html(html);
                }, 'json').fail(function () {
                    $body.html('<div class="p-3 text-danger">Error al obtener datos del servidor</div>');
                });
            });
        });
    </script>
</body>
</html>