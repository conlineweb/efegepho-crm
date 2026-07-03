<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/dashboard_comercial_period_helper.php';

include 'menu.php';
include 'conn.php';
require_once __DIR__ . '/dashboard_comercial_helper.php';
require_once __DIR__ . '/status_badge_helper.php';
require_once __DIR__ . '/lead_field_badge_helper.php';

$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate   = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

list($startDate, $endDate) = dashComercialResolvePeriodDates($startDate, $endDate);

$dashMinPeriodDate = dashComercialGetMinPeriodDate();

$metrics = dashComercialComputeMetrics($conn, $startDate, $endDate);
$globalKpis = $metrics['global'];
$vendedoras = $metrics['vendedoras'];

$reportRangeLabel = date('d/m/Y', strtotime($startDate)) . ' → ' . date('d/m/Y', strtotime($endDate));

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Comercial — EFEGE</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --gold: #C5A028;
            --gold-light: #f5edd6;
            --ink: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --card: #ffffff;
            --success: #059669;
            --info: #2563eb;
            --purple: #7c3aed;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--ink); }
        .efege-page {   padding: 24px 20px 48px; }
        .page-header { margin-bottom: 20px; }
        .page-title { font-family: 'Cormorant Garamond', serif; font-size: 2rem; font-weight: 600; margin: 0 0 6px; }
        .page-subtitle { color: var(--muted); margin: 0; font-size: 0.95rem; }
        .filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; margin-bottom: 24px; }
        .filter-label { font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin-right: 4px; }
        .efege-filter-input { border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; font-size: 0.9rem; }
        .efege-btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border); background: #fff; color: var(--ink); border-radius: 8px; padding: 8px 14px; font-size: 0.88rem; text-decoration: none; cursor: pointer; }
        .efege-btn-primary { background: var(--ink); border-color: var(--ink); color: #fff; }
        .section-block { margin-bottom: 36px; }
        .section-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 18px; flex-wrap: wrap; }
        .section-step { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--gold); margin-bottom: 4px; }
        .section-title { font-family: 'Cormorant Garamond', serif; font-size: 1.55rem; margin: 0 0 4px; }
        .section-subtitle { margin: 0; color: var(--muted); font-size: 0.9rem; }
        .range-chip { display: inline-flex; align-items: center; gap: 8px; background: var(--gold-light); color: #7a5c00; border-radius: 999px; padding: 8px 14px; font-size: 0.85rem; font-weight: 600; white-space: nowrap; }
        .global-kpi-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        @media (max-width: 992px) { .global-kpi-grid { grid-template-columns: 1fr; } }
        .global-kpi-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 22px 24px; box-shadow: 0 1px 3px rgba(15,23,42,0.04); cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s; }
        .global-kpi-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 14px rgba(15,23,42,0.08); transform: translateY(-1px); }
        .global-kpi-card:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }
        .global-kpi-card.full-width { grid-column: 1 / -1; }
        .global-kpi-card.calificacion { border-top: 4px solid var(--gold); }
        .global-kpi-card.atencion { border-top: 4px solid var(--info); }
        .global-kpi-card.cierre { border-top: 4px solid var(--success); }
        .global-kpi-card .kpi-label { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); margin-bottom: 8px; }
        .global-kpi-card .kpi-value { font-size: 2.4rem; font-weight: 700; line-height: 1; margin-bottom: 10px; color: var(--ink); }
        .global-kpi-card .kpi-detail { font-size: 0.92rem; color: var(--muted); }
        .global-kpi-card .kpi-formula { font-size: 0.8rem; color: var(--muted); margin-top: 8px; }
        .kpi-tipo-breakdown { margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border); display: grid; gap: 10px; }
        .global-kpi-card.cierre .kpi-tipo-breakdown { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 20px; }
        @media (max-width: 640px) { .global-kpi-card.cierre .kpi-tipo-breakdown { grid-template-columns: 1fr; } }
        .kpi-tipo-group-title { font-size: 0.84rem; font-weight: 600; color: var(--ink); margin-bottom: 6px; }
        .kpi-tipo-group-title strong { font-weight: 700; }
        .kpi-tipo-list { list-style: none; margin: 0; padding: 0 0 0 4px; }
        .kpi-tipo-list li { font-size: 0.8rem; color: var(--muted); display: flex; align-items: center; gap: 7px; line-height: 1.5; }
        .kpi-tipo-list li strong { color: var(--ink); font-weight: 600; }
        .kpi-tipo-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .kpi-tipo-dot.planner { background: #9333ea; }
        .kpi-tipo-dot.final { background: #2563eb; }
        .kpi-tipo-breakdown.vendor-compact { margin-top: 10px; padding-top: 10px; grid-template-columns: 1fr; gap: 8px; }
        .vendor-card .kpi-tipo-group-title { font-size: 0.78rem; margin-bottom: 4px; }
        .vendor-card .kpi-tipo-list li { font-size: 0.76rem; }
        .vendor-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
        .vendor-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 18px; box-shadow: 0 1px 3px rgba(15,23,42,0.04); cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s; }
        .vendor-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 14px rgba(15,23,42,0.08); transform: translateY(-1px); }
        .vendor-card:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }
        .vendor-card.inactive { opacity: 0.55; }
        .vendor-name { font-size: 1.05rem; font-weight: 700; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
        .vendor-kpi { margin-bottom: 12px; }
        .vendor-kpi:last-child { margin-bottom: 0; }
        .vendor-kpi-head { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin-bottom: 4px; }
        .vendor-kpi-title { font-size: 0.78rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; }
        .vendor-kpi-pct { font-size: 1.25rem; font-weight: 700; color: var(--ink); }
        .vendor-kpi-detail { font-size: 0.84rem; color: var(--muted); }
        .vendor-bar { height: 5px; background: #eef2f7; border-radius: 999px; margin-top: 6px; overflow: hidden; }
        .vendor-bar-fill { height: 100%; border-radius: 999px; background: var(--gold); }
        .vendor-bar-fill.atencion { background: var(--info); }
        .vendor-bar-fill.cierre { background: var(--success); }
        .empty-vendors { background: var(--card); border: 1px dashed var(--border); border-radius: 12px; padding: 32px; text-align: center; color: var(--muted); }
        .source-note { margin-top: 24px; padding: 14px 16px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; font-size: 0.84rem; color: #92400e; }
        .dashboard-view-note { margin-top: 16px; padding: 14px 16px 14px 20px; background: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #2563eb; border-radius: 10px; font-size: 0.86rem; color: #0c4a6e; }
        .dashboard-view-note-title { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #0369a1; margin-bottom: 6px; }
        .dashboard-view-note p { margin: 0; line-height: 1.5; }
        .global-kpi-card .kpi-na { color: var(--muted); font-size: 1.8rem; }
        .dc-modal { position: fixed; inset: 0; z-index: 2000; display: none; align-items: center; justify-content: center; padding: 20px; }
        .dc-modal.is-open { display: flex; }
        .dc-modal-backdrop { position: absolute; inset: 0; background: rgba(15,23,42,0.45); }
        .dc-modal-dialog { position: relative; width: min(1360px, 100%); max-height: 88vh; background: var(--card); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 24px 60px rgba(15,23,42,0.2); display: flex; flex-direction: column; overflow: hidden; }
        .dc-modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 20px 24px 14px; border-bottom: 1px solid var(--border); }
        .dc-modal-title { font-family: 'Cormorant Garamond', serif; font-size: 1.45rem; margin: 0; }
        .dc-modal-subtitle { margin: 4px 0 0; font-size: 0.85rem; color: var(--muted); }
        .dc-modal-close { border: none; background: var(--bg); color: var(--muted); width: 36px; height: 36px; border-radius: 8px; font-size: 1.4rem; line-height: 1; cursor: pointer; }
        .dc-modal-close:hover { background: #eef2f7; color: var(--ink); }
        .dc-modal-tabs { display: flex; gap: 8px; padding: 14px 24px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
        .dc-modal-tab { border: 1px solid var(--border); background: #fff; color: var(--muted); border-radius: 999px; padding: 8px 14px; font-size: 0.82rem; font-weight: 600; cursor: pointer; }
        .dc-modal-tab.is-active { background: var(--ink); border-color: var(--ink); color: #fff; }
        .dc-modal-body { padding: 0; overflow: auto; flex: 1; }
        .dc-modal-loading, .dc-modal-empty { padding: 40px 24px; text-align: center; color: var(--muted); }
        .dc-records-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
        .dc-records-table th, .dc-records-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: top; }
        .dc-records-table th { position: sticky; top: 0; background: var(--bg); color: var(--muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; z-index: 1; }
        .dc-records-table th.dc-date-col, .dc-records-table td.dc-date-col {
            white-space: nowrap;
            min-width: 78px;
            text-align: center;
            vertical-align: middle;
            font-size: 0.72rem;
        }
        .dc-records-table th.dc-date-col--agenda,
        .dc-records-table td.dc-date-col--agenda {
            color: #7a5c00;
            background: rgba(245, 237, 214, 0.55);
        }
        .dc-records-table th.dc-date-col--atencion,
        .dc-records-table td.dc-date-col--atencion {
            color: #1d4ed8;
            background: rgba(239, 246, 255, 0.75);
        }
        .dc-records-table th.dc-date-col--cierre,
        .dc-records-table td.dc-date-col--cierre {
            color: #047857;
            background: rgba(236, 253, 245, 0.75);
        }
        .dc-records-table th.dc-date-col--agenda { background: #f5edd6; }
        .dc-records-table th.dc-date-col--atencion { background: #eff6ff; }
        .dc-records-table th.dc-date-col--cierre { background: #ecfdf5; }
        .dc-records-table td.dc-date-col--atencion {
            white-space: normal;
            min-width: 82px;
            max-width: 96px;
            padding: 8px 6px;
        }
        .dc-records-table td.dc-date-col--cierre {
            min-width: 72px;
            max-width: 88px;
            padding: 8px 6px;
        }
        .dc-records-table td.dc-date-col .dc-date-value {
            display: block;
            font-size: 0.72rem;
            font-weight: 500;
            line-height: 1.15;
            text-align: center;
        }
        .dc-date-pending-cell {
            display: block;
            line-height: 1.15;
            text-align: center;
        }
        .dc-date-atencion-cell {
            display: block;
            line-height: 1.15;
            text-align: center;
        }
        .dc-date-pending {
            color: var(--muted);
            font-style: italic;
            font-size: 0.68rem;
            display: block;
            line-height: 1.15;
            text-align: center;
        }
        .dc-date-pending-note {
            display: block;
            margin-top: 2px;
            color: #64748b;
            font-size: 0.58rem;
            font-style: normal;
            font-weight: 400;
            line-height: 1.15;
            word-break: break-word;
            hyphens: auto;
            text-align: center;
        }
        .dc-date-pending-note--solo {
            margin-top: 0;
            font-size: 0.58rem;
            line-height: 1.2;
        }
        .dc-records-group-row td {
            padding: 14px 14px 10px;
            background: var(--bg);
            border-bottom: 1px solid var(--border);
        }
        .dc-records-group-row--cierre td { border-left: 4px solid var(--success); }
        .dc-records-group-row--atendido td { border-left: 4px solid var(--info); }
        .dc-records-group-row--agenda td { border-left: 4px solid var(--gold); }
        .dc-records-group-head {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .dc-records-group-title {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--ink);
        }
        .dc-records-group-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            background: #fff;
            border: 1px solid var(--border);
            color: var(--ink);
        }
        .dc-records-group-row--cierre .dc-records-group-count {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #047857;
        }
        .dc-records-group-row--atendido .dc-records-group-count {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }
        .dc-records-group-row--agenda .dc-records-group-count {
            background: var(--gold-light);
            border-color: #e9d48a;
            color: #7a5c00;
        }
        .dc-records-table tr.dc-records-group-row + tr td { padding-top: 12px; }
        .dc-modal-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 24px; border-bottom: 1px solid var(--border); background: var(--bg); }
        .dc-modal-toolbar-left { display: flex; align-items: center; gap: 12px; flex: 1 1 auto; min-width: 0; }
        .dc-modal-toolbar-right { display: flex; align-items: center; justify-content: flex-end; flex: 0 1 auto; margin-left: auto; }
        .dc-records-count { font-size: 0.82rem; color: var(--muted); white-space: nowrap; }
        .dc-modal-search-wrap { position: relative; flex: 1 1 220px; max-width: 320px; min-width: 180px; }
        .dc-modal-search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 0.82rem; pointer-events: none; }
        .dc-modal-search { width: 100%; border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px 8px 32px; font-size: 0.86rem; background: #fff; color: var(--ink); }
        .dc-modal-search:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 2px rgba(197,160,40,0.15); }
        .dc-modal-search-wrap[hidden] { display: none; }
        .dc-modal-status-wrap { flex: 0 0 180px; min-width: 160px; }
        .dc-modal-status-wrap[hidden] { display: none; }
        .dc-modal-status-filter {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.86rem;
            background: #fff;
            color: var(--ink);
        }
        .dc-modal-status-filter:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 2px rgba(197,160,40,0.15); }
        .badge-tipo-cliente { display: inline-flex; align-items: center; border-radius: 999px; padding: 3px 10px; font-size: 0.72rem; font-weight: 600; white-space: nowrap; }
        <?php echo getLeadStatusBadgeCss(); ?>
    </style>
</head>
<body>
<div class="efege-page">

    <header class="page-header">
        <h1 class="page-title">Dashboard Comercial</h1>
        <p class="page-subtitle">Indicadores de calificación, atención y cierre — global y por vendedora. Fuente: histórico de estatus.</p>
    </header>

    <form method="get" class="filter-bar">
        <span class="filter-label">Periodo</span>
        <input type="date" name="start_date" class="efege-filter-input" min="<?php echo htmlspecialchars($dashMinPeriodDate, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="date" name="end_date" class="efege-filter-input" min="<?php echo htmlspecialchars($dashMinPeriodDate, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="efege-btn efege-btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
    </form>

    <!-- INDICADORES GLOBALES -->
    <section class="section-block">
        <div class="section-head">
            <div>
                <div class="section-step">Indicadores globales</div>
                <h2 class="section-title">Desempeño comercial general</h2>
                <p class="section-subtitle">Métricas consolidadas de lead funnel en el periodo seleccionado.</p>
            </div>
            <div class="range-chip"><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <?php $cierreKpi = $globalKpis['cierre']; ?>

        <style>
        .dashboard-view-note {
            position: relative;
            padding-right: 56px;
        }
        .dashboard-view-note-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 6px;
        }
        .info-modal-btn {
            position: absolute;
            right: 14px;
            top: 14px;
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            padding: 0;
            box-sizing: border-box;
            border-radius: 11px;
            border: 1.5px solid #ef4444;
            background: #fef2f2;
            color: #dc2626;
            cursor: pointer;
            line-height: 0;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.16);
            transition: background 0.15s, border-color 0.15s, color 0.15s, box-shadow 0.15s, transform 0.15s;
        }
        .info-modal-btn i {
            display: block;
            font-size: 1.05rem;
            line-height: 1;
            width: 1em;
            height: 1em;
        }
        .info-modal-btn:hover {
            background: #fee2e2;
            border-color: #dc2626;
            color: #b91c1c;
            box-shadow: 0 4px 14px rgba(220, 38, 38, 0.22);
            transform: translateY(-1px);
        }
        .info-modal-btn:focus-visible {
            outline: 2px solid var(--gold);
            outline-offset: 2px;
        }
        .info-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .info-modal-content {
            position: relative;
            width: min(860px, 100%);
            max-height: 85vh;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .info-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 22px 24px 18px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
        }
        .info-modal-header-main {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            min-width: 0;
        }
        .info-modal-header-icon {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: var(--info);
            font-size: 1.25rem;
        }
        .info-modal-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.55rem;
            font-weight: 600;
            line-height: 1.15;
            margin: 0 0 6px;
            color: var(--ink);
        }
        .info-modal-subtitle {
            margin: 0;
            font-size: 0.86rem;
            color: var(--muted);
            line-height: 1.45;
        }
        .info-modal-close {
            flex-shrink: 0;
            border: none;
            background: var(--bg);
            color: var(--muted);
            width: 36px;
            height: 36px;
            border-radius: 8px;
            font-size: 1.4rem;
            line-height: 1;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .info-modal-close:hover {
            background: #eef2f7;
            color: var(--ink);
        }
        .info-modal-body {
            padding: 22px 24px 28px;
            overflow-y: auto;
        }
        .info-modal-section {
            margin-bottom: 32px;
        }
        .info-modal-section:last-child {
            margin-bottom: 0;
        }
        .info-modal-section-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 8px;
        }
        .info-modal-section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 14px;
            color: var(--ink);
        }
        .info-modal-text {
            margin: 0 0 16px;
            font-size: 0.9rem;
            line-height: 1.6;
            color: var(--ink);
        }
        .info-modal-section > .info-modal-text:last-child,
        .info-modal-section > .info-modal-highlight:last-child,
        .info-modal-section > .info-modal-notice:last-child,
        .info-modal-section > .info-modal-formula-list:last-child,
        .info-modal-section > .info-modal-kpi-map:last-child,
        .info-modal-section > .info-modal-example:last-child,
        .info-modal-section > .info-modal-result-grid:last-child,
        .info-modal-section > .info-modal-list:last-child {
            margin-bottom: 0;
        }
        .info-modal-flow {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin: 0 0 16px;
        }
        @media (max-width: 720px) {
            .info-modal-flow { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 420px) {
            .info-modal-flow { grid-template-columns: 1fr; }
        }
        .info-modal-flow-step {
            position: relative;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 10px;
            text-align: center;
        }
        .info-modal-flow-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            right: -8px;
            width: 8px;
            height: 1px;
            background: #cbd5e1;
            transform: translateY(-50%);
        }
        @media (max-width: 720px) {
            .info-modal-flow-step:not(:last-child)::after { display: none; }
        }
        .info-modal-flow-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: var(--ink);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .info-modal-flow-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--ink);
            line-height: 1.35;
        }
        .info-modal-stages {
            display: grid;
            gap: 14px;
            margin-top: 4px;
        }
        .info-modal-stage {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 18px;
            border-left: 4px solid var(--info);
        }
        .info-modal-stage:nth-child(1) { border-left-color: var(--gold); }
        .info-modal-stage:nth-child(2) { border-left-color: var(--purple); }
        .info-modal-stage:nth-child(3) { border-left-color: var(--info); }
        .info-modal-stage:nth-child(4) { border-left-color: var(--success); }
        .info-modal-stage-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 8px;
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--ink);
        }
        .info-modal-stage-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            padding: 0 7px;
            border-radius: 999px;
            background: var(--bg);
            border: 1px solid var(--border);
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--muted);
        }
        .info-modal-stage p {
            margin: 0;
            font-size: 0.88rem;
            line-height: 1.55;
            color: var(--muted);
        }
        .info-modal-stage .info-modal-highlight {
            margin-top: 14px;
            margin-bottom: 0;
        }
        .info-modal-example {
            margin: 0 0 16px;
            padding: 14px 16px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
        }
        .info-modal-example-title {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #92400e;
            margin-bottom: 8px;
        }
        .info-modal-example-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 6px;
        }
        .info-modal-example-list li {
            display: flex;
            align-items: baseline;
            gap: 8px;
            font-size: 0.84rem;
            color: #78350f;
            line-height: 1.45;
        }
        .info-modal-example-list li strong {
            min-width: 118px;
            font-weight: 600;
            color: #92400e;
        }
        .info-modal-highlight {
            margin: 0 0 16px;
            padding: 12px 14px;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-left: 4px solid var(--info);
            border-radius: 10px;
            font-size: 0.86rem;
            line-height: 1.55;
            color: #0c4a6e;
        }
        .info-modal-key-rule {
            margin-bottom: 28px;
            padding: 14px 16px;
            background: var(--gold-light);
            border: 1px solid #e9d48a;
            border-left: 4px solid var(--gold);
            border-radius: 10px;
        }
        .info-modal-key-rule-title {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #7a5c00;
            margin-bottom: 6px;
        }
        .info-modal-key-rule p {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.55;
            color: #713f12;
        }
        .info-modal-kpi-map {
            display: grid;
            gap: 8px;
            margin: 0 0 16px;
        }
        .info-modal-kpi-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.84rem;
        }
        .info-modal-kpi-row span:first-child {
            font-weight: 600;
            color: var(--ink);
        }
        .info-modal-kpi-row span:last-child {
            color: var(--muted);
            text-align: right;
        }
        .info-modal-notice {
            margin: 0 0 16px;
            padding: 12px 14px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            border-radius: 10px;
            font-size: 0.84rem;
            line-height: 1.55;
            color: #92400e;
        }
        .info-modal-formula-list {
            display: grid;
            gap: 12px;
            margin: 0 0 16px;
        }
        .info-modal-formula-card {
            padding: 14px 16px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
        }
        .info-modal-formula-card.calificacion { border-left: 4px solid var(--gold); }
        .info-modal-formula-card.atencion { border-left: 4px solid var(--info); }
        .info-modal-formula-card.cierre { border-left: 4px solid var(--success); }
        .info-modal-formula-name {
            font-size: 0.84rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 6px;
        }
        .info-modal-formula-expr {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 8px;
        }
        .info-modal-formula-card p {
            margin: 0;
            font-size: 0.84rem;
            line-height: 1.5;
            color: var(--muted);
        }
        .info-modal-result-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin: 0 0 16px;
        }
        @media (max-width: 560px) {
            .info-modal-result-grid { grid-template-columns: 1fr; }
        }
        .info-modal-result-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.84rem;
        }
        .info-modal-result-item span:first-child {
            color: var(--ink);
            font-weight: 600;
        }
        .info-modal-result-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 0.72rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .info-modal-result-badge.yes {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
        }
        .info-modal-result-badge.no {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }
        .info-modal-list {
            margin: 0;
            padding-left: 1.15rem;
            display: grid;
            gap: 8px;
        }
        .info-modal-list li {
            font-size: 0.88rem;
            line-height: 1.55;
            color: var(--muted);
        }
        .info-modal-conclusion {
            padding: 16px 18px;
            background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
            border: 1px solid var(--border);
            border-radius: 12px;
        }
        .info-modal-conclusion .info-modal-text {
            color: var(--muted);
        }
        </style>


<div class="dashboard-view-note" style="margin-bottom: 16px;">

    <button type="button" class="info-modal-btn" onclick="openInfoModal()" title="Información sobre consultas por período" aria-label="Información sobre consultas por período">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
    </button>

    <div class="dashboard-view-note-title">
        Vista Dashboard
    </div>

    <p>
        <strong>Nota:</strong> Los indicadores de Leads Precalificados, Leads Agendados, 
        Leads Atendidos y Clientes Cerrados coinciden con sus respectivas pantallas 
        cuando se consulta el mismo rango de fechas. Únicamente en Leads Atendidos 
        se consideran registros a partir del <strong>15 de junio de 2026</strong>, 
        al igual que en esta vista.
    </p>

</div>


<!-- MODAL -->
<div class="info-modal" id="infoModal">

<div class="info-modal-content">

<div class="info-modal-header">
    <div class="info-modal-header-main">
        <span class="info-modal-header-icon" aria-hidden="true"><i class="bi bi-journal-text"></i></span>
        <div>
            <h2 class="info-modal-title">Lógica de Consulta de Leads y Clientes por Período</h2>
            <p class="info-modal-subtitle">Guía de referencia: pantallas de consulta, estatus visibles, indicadores del dashboard y tasas por periodo.</p>
        </div>
    </div>
    <button type="button" class="info-modal-close" onclick="closeInfoModal()" aria-label="Cerrar">&times;</button>
</div>

<div class="info-modal-body">

<div class="info-modal-key-rule">
    <div class="info-modal-key-rule-title">Dos lógicas complementarias</div>
    <p>Las <strong>pantallas de consulta</strong> (Leads, Agendados, Atendidos) filtran por <strong>fecha de registro</strong> y muestran el estatus vigente. Los <strong>indicadores del dashboard</strong> filtran cada KPI por <strong>la fecha de su propia etapa</strong>. Por eso un mismo lead puede aparecer distinto según la vista o el indicador consultado.</p>
</div>

<section class="info-modal-section">
    <div class="info-modal-section-label">Introducción</div>
    <h3 class="info-modal-section-title">Objetivo</h3>
    <p class="info-modal-text">
        Esta guía explica cómo funcionan las consultas por periodo en el sistema:
        qué registros aparecen en cada pantalla, qué estatus se muestran y cómo se
        calculan los indicadores y tasas del dashboard.
    </p>
</section>

<section class="info-modal-section">
    <div class="info-modal-section-label">Pantallas de consulta</div>
    <h3 class="info-modal-section-title">Leads, Agendados y Atendidos</h3>
    <p class="info-modal-text">
        Como referencia de la lógica del sistema, todas estas consultas toman como base la
        <strong>fecha de registro del lead</strong>. Si el registro cae dentro del rango
        de fechas consultado, el registro se incluye y se muestra con su
        <strong>estatus actual</strong>, sin importar en qué etapa avanzó después.
    </p>
    <div class="info-modal-example">
        <div class="info-modal-example-title">Ejemplo</div>
        <ul class="info-modal-example-list">
            <li><strong>Registro:</strong> 15 de junio</li>
            <li><strong>Recorrido:</strong> agendado → atendido → cliente cerrado</li>
            <li><strong>Consulta:</strong> rango que incluye el 15 de junio</li>
        </ul>
    </div>
    <div class="info-modal-highlight">
        El registro <strong>sí aparece</strong> y se muestra con estatus <strong>Cliente Cerrado</strong>,
        porque siempre se presenta el estatus vigente al momento de la consulta.
    </div>
    <p class="info-modal-text">
        Esta lógica aplica para las vistas de <strong>Leads</strong>, <strong>Agendados</strong> y
        <strong>Atendidos</strong>. La vista de <strong>Clientes Cerrados</strong> es la única excepción:
        solo muestra registros cuyo estatus actual es Cliente Cerrado.
    </p>
</section>

<section class="info-modal-section">
    <div class="info-modal-section-label">Estatus visibles</div>
    <h3 class="info-modal-section-title">Estatus mostrados por cada vista</h3>
    <p class="info-modal-text">Además del filtro por fecha de registro, cada pantalla solo incluye ciertos estatus:</p>
    <div class="info-modal-stages">
        <article class="info-modal-stage">
            <h4 class="info-modal-stage-title"><span class="info-modal-stage-badge">Leads</span></h4>
            <p>Lead, Agendado, Atendido, Fantasma, Muerto y Cliente Cerrado.</p>
        </article>
        <article class="info-modal-stage">
            <h4 class="info-modal-stage-title"><span class="info-modal-stage-badge">Agendados</span></h4>
            <p>Agendado, Atendido, Fantasma, Muerto y Cliente Cerrado.</p>
        </article>
        <article class="info-modal-stage">
            <h4 class="info-modal-stage-title"><span class="info-modal-stage-badge">Atendidos</span></h4>
            <p>Atendido, Muerto (si pasó previamente por Atendido) y Cliente Cerrado.</p>
        </article>
        <article class="info-modal-stage">
            <h4 class="info-modal-stage-title"><span class="info-modal-stage-badge">Clientes Cerrados</span></h4>
            <p>Únicamente Cliente Cerrado.</p>
        </article>
    </div>
</section>

<section class="info-modal-section">
    <div class="info-modal-section-label">KPIs</div>
    <h3 class="info-modal-section-title">Atendidos y Clientes Cerrados son métricas distintas</h3>
    <div class="info-modal-notice">
        <strong>Importante:</strong> no es correcto definir el total de atendidos del mes como la suma de atendidos + clientes cerrados.
    </div>
    <p class="info-modal-text">
        En el sistema, ambas métricas representan etapas distintas del embudo y usan cortes de fecha diferentes:
    </p>
    <ul class="info-modal-list">
        <li><strong>Atendidos</strong> se mide por <strong>fecha de atención</strong> (historial de atención / post-leads).</li>
        <li><strong>Clientes cerrados</strong> se mide por <strong>fecha de cierre</strong> (<code>fecha_cambio_cliente</code>).</li>
    </ul>
    <p class="info-modal-text">
        Son cortes diferentes: un lead puede ser atendido en un mes y cerrar en otro. Sumarlos distorsiona
        los indicadores y puede generar doble conteo. Por eso deben mantenerse como KPIs independientes;
        pueden mostrarse juntos en la misma vista, pero no consolidarse en un único total mensual de atendidos.
    </p>
</section>

<section class="info-modal-section">
    <div class="info-modal-section-label">Proceso</div>
    <h3 class="info-modal-section-title">Flujo del proceso comercial</h3>
    <p class="info-modal-text">Cada cliente avanza por estas etapas durante su ciclo:</p>
    <div class="info-modal-flow">
        <div class="info-modal-flow-step">
            <span class="info-modal-flow-num">1</span>
            <span class="info-modal-flow-label">Lead Precalificado</span>
        </div>
        <div class="info-modal-flow-step">
            <span class="info-modal-flow-num">2</span>
            <span class="info-modal-flow-label">Lead Agendado</span>
        </div>
        <div class="info-modal-flow-step">
            <span class="info-modal-flow-num">3</span>
            <span class="info-modal-flow-label">Lead Atendido</span>
        </div>
        <div class="info-modal-flow-step">
            <span class="info-modal-flow-num">4</span>
            <span class="info-modal-flow-label">Cliente Cerrado</span>
        </div>
    </div>
    <p class="info-modal-text">Cada etapa registra su propia fecha por separado.</p>
</section>

<section class="info-modal-section">
    <div class="info-modal-section-label">Ejemplo · Dashboard</div>
    <h3 class="info-modal-section-title">Caso práctico de indicadores por etapa</h3>
    <p class="info-modal-text">Para los KPIs del dashboard, cada indicador usa su propia fecha. Imagina un lead con estas fechas y una consulta del <strong>15 al 30 de junio</strong>:</p>
    <div class="info-modal-example">
        <div class="info-modal-example-title">Fechas del lead</div>
        <ul class="info-modal-example-list">
            <li><strong>Registro:</strong> 10 de junio</li>
            <li><strong>Agenda:</strong> 14 de junio</li>
            <li><strong>Atención:</strong> 20 de junio</li>
            <li><strong>Cierre:</strong> 28 de junio</li>
        </ul>
    </div>
    <p class="info-modal-text">Este lead aparecería así en cada indicador del dashboard:</p>
    <div class="info-modal-result-grid">
        <div class="info-modal-result-item">
            <span>Leads Precalificados</span>
            <span class="info-modal-result-badge no">No aparece</span>
        </div>
        <div class="info-modal-result-item">
            <span>Leads Agendados</span>
            <span class="info-modal-result-badge no">No aparece</span>
        </div>
        <div class="info-modal-result-item">
            <span>Leads Atendidos</span>
            <span class="info-modal-result-badge yes">Sí aparece</span>
        </div>
        <div class="info-modal-result-item">
            <span>Clientes Cerrados</span>
            <span class="info-modal-result-badge yes">Sí aparece</span>
        </div>
    </div>
</section>

<section class="info-modal-section">
    <div class="info-modal-section-label">Criterios</div>
    <h3 class="info-modal-section-title">Fechas utilizadas en cada etapa</h3>
    <div class="info-modal-stages">

        <article class="info-modal-stage">
            <h4 class="info-modal-stage-title"><span class="info-modal-stage-badge">1</span> Leads Precalificados</h4>
            <p>Se filtra por la <strong>Fecha de Registro</strong> del lead.</p>
            <div class="info-modal-highlight">
                En el ejemplo, el registro fue el 10 de junio, fuera del periodo consultado (15 al 30 de junio). Por eso <strong>no aparece</strong>.
            </div>
        </article>

        <article class="info-modal-stage">
            <h4 class="info-modal-stage-title"><span class="info-modal-stage-badge">2</span> Leads Agendados</h4>
            <p>Se filtra por la <strong>Fecha de Agenda</strong>.</p>
            <div class="info-modal-highlight">
                En el ejemplo, la agenda fue el 14 de junio, también fuera del periodo consultado. Por eso <strong>no aparece</strong>.
            </div>
        </article>

        <article class="info-modal-stage">
            <h4 class="info-modal-stage-title"><span class="info-modal-stage-badge">3</span> Leads Atendidos</h4>
            <p>Se filtra por la <strong>Fecha de Atención</strong>.</p>
            <div class="info-modal-highlight">
                En el ejemplo, la atención fue el 20 de junio, dentro del periodo del 15 al 30 de junio. Por eso <strong>sí aparece</strong>.
            </div>
        </article>

        <article class="info-modal-stage">
            <h4 class="info-modal-stage-title"><span class="info-modal-stage-badge">4</span> Clientes Cerrados</h4>
            <p>Se filtra por la <strong>Fecha de Cierre</strong>.</p>
            <div class="info-modal-highlight">
                En el ejemplo, el cierre fue el 28 de junio, dentro del periodo consultado. Por eso <strong>sí aparece</strong>.
            </div>
        </article>

    </div>
</section>

<section class="info-modal-section">
    <div class="info-modal-section-label">Contexto</div>
    <h3 class="info-modal-section-title">¿Por qué los totales pueden ser diferentes?</h3>
    <ul class="info-modal-list">
        <li>Un lead puede registrarse en una fecha y agendarse en otra.</li>
        <li>La atención puede ocurrir días o semanas después de la agenda.</li>
        <li>El cierre puede darse después de la atención.</li>
        <li>No todos los leads avanzan a la siguiente etapa.</li>
    </ul>
</section>

<section class="info-modal-section info-modal-conclusion">
    <div class="info-modal-section-label">Resumen</div>
    <h3 class="info-modal-section-title">Conclusión</h3>
    <p class="info-modal-text">
        Las pantallas de consulta filtran por fecha de registro y muestran el estatus vigente.
        Los indicadores del dashboard, en cambio, miden la actividad real de cada etapa comercial
        según su propia fecha, no el historial completo del cliente.
    </p>
    <p class="info-modal-text">
        Este comportamiento es intencional: Atendidos y Clientes Cerrados deben interpretarse como
        KPIs independientes, y las tasas deben calcularse con la fecha que corresponde a cada etapa.
    </p>
</section>

<section class="info-modal-section">
    <div class="info-modal-section-label">Dashboard</div>
    <h3 class="info-modal-section-title">Relación con los indicadores de esta vista</h3>
    <p class="info-modal-text">
        A diferencia de las pantallas de consulta, los indicadores de este dashboard no filtran por
        fecha de registro. Cada KPI usa la fecha que corresponde a su etapa:
    </p>
    <div class="info-modal-kpi-map">
        <div class="info-modal-kpi-row">
            <span>Leads precalificados / Tasa de agendas (leads)</span>
            <span>Fecha de registro</span>
        </div>
        <div class="info-modal-kpi-row">
            <span>Leads agendados / Tasa de agendas (agendas)</span>
            <span>Fecha de agenda</span>
        </div>
        <div class="info-modal-kpi-row">
            <span>Leads atendidos / Tasa de atención</span>
            <span>Fecha de atención</span>
        </div>
        <div class="info-modal-kpi-row">
            <span>Clientes cerrados / Tasa de cierre</span>
            <span>Fecha de cierre</span>
        </div>
    </div>
    <div class="info-modal-notice">
        <strong>Importante:</strong> en Leads Atendidos de este dashboard solo se consideran registros a partir del <strong>15 de junio de 2026</strong>, igual que en su pantalla correspondiente.
    </div>
</section>

<section class="info-modal-section">
    <div class="info-modal-section-label">Tasas</div>
    <h3 class="info-modal-section-title">¿Cómo se calculan las tasas y por qué por corte?</h3>
    <p class="info-modal-text">
        Para el cálculo de tasas, la estructura correcta mantiene Atendidos y Clientes Cerrados
        como KPIs independientes. Cada total del periodo se filtra con la fecha de su propia etapa,
        no con una sola fecha del lead:
    </p>
    <div class="info-modal-formula-list">
        <article class="info-modal-formula-card calificacion">
            <div class="info-modal-formula-name">Tasa de agendas</div>
            <div class="info-modal-formula-expr">Agendas del periodo ÷ Leads del periodo</div>
            <p>Las agendas se cuentan por <strong>fecha de agenda</strong> y los leads por <strong>fecha de registro</strong>.</p>
        </article>
        <article class="info-modal-formula-card atencion">
            <div class="info-modal-formula-name">Tasa de atención</div>
            <div class="info-modal-formula-expr">Atendidos del periodo ÷ Agendas del periodo</div>
            <p>Los atendidos se cuentan por <strong>fecha de atención</strong> y las agendas por <strong>fecha de agenda</strong>.</p>
        </article>
        <article class="info-modal-formula-card cierre">
            <div class="info-modal-formula-name">Tasa de cierre</div>
            <div class="info-modal-formula-expr">Clientes cerrados del periodo ÷ Atendidos del periodo</div>
            <p>Los clientes se cuentan por <strong>fecha de cierre</strong> y los atendidos por <strong>fecha de atención</strong>.</p>
        </article>
    </div>
    <p class="info-modal-text">
        Esto responde a una pregunta operativa: <strong>¿qué pasó en este periodo en cada etapa?</strong>
        No mide el recorrido completo de un solo grupo fijo de leads registrados en el mes.
    </p>
    <div class="info-modal-highlight">
        Por eso el numerador y el denominador de una tasa pueden no ser los mismos clientes.
        Por ejemplo, alguien atendido en junio pudo haberse agendado en mayo, o un cliente cerrado en junio
        pudo haber sido atendido antes. La tasa sigue siendo válida: mide la relación entre la actividad
        real de cada etapa dentro del corte consultado.
    </div>
    <p class="info-modal-text">
        Este criterio es intencional porque coincide con las pantallas del sistema y permite evaluar
        el desempeño comercial del periodo con datos comparables entre indicadores y reportes.
    </p>
</section>

</div>
</div>
</div>



<script>

function openInfoModal(){
    document.getElementById("infoModal").style.display="flex";
}


function closeInfoModal(){
    document.getElementById("infoModal").style.display="none";
}


window.onclick=function(event){
    let modal=document.getElementById("infoModal");

    if(event.target === modal){
        modal.style.display="none";
    }
}

</script>
        <article class="global-kpi-card cierre" style="margin-bottom: 16px;" data-kpi="cierre" role="button" tabindex="0" aria-label="Ver detalle de Tasa de Cierre Global">
            <div class="kpi-label">Tasa de Cierre Global</div>
            <div class="kpi-value<?php echo !empty($cierreKpi['sin_base']) ? ' kpi-na' : ''; ?>"><?php echo htmlspecialchars($cierreKpi['porcentaje'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="kpi-detail">
                <strong><?php echo number_format($cierreKpi['numerador']); ?></strong> <?php echo htmlspecialchars($cierreKpi['label_numerador'], ENT_QUOTES, 'UTF-8'); ?>
                &nbsp;/&nbsp;
                <strong><?php echo number_format($cierreKpi['denominador']); ?></strong> <?php echo htmlspecialchars($cierreKpi['label_denominador'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php
            if (!empty($cierreKpi['tipo_desglose'])) {
                echo dashComercialRenderTipoDesgloseHtml($cierreKpi['tipo_desglose']);
            }
            ?>
            <div class="kpi-formula">Clientes cerrados ÷ Atendidos</div>
        </article>

        <div class="global-kpi-grid">
            <?php
            $bottomCards = [
                ['key' => 'atencion',     'class' => 'atencion',     'title' => 'Tasa de Atención Global', 'formula' => 'Atendidos ÷ Agendas'],
                ['key' => 'calificacion', 'class' => 'calificacion', 'title' => 'Tasa de agendas',         'formula' => 'Agendas ÷ Leads'],
            ];
            foreach ($bottomCards as $card):
                $kpi = $globalKpis[$card['key']];
            ?>
            <article class="global-kpi-card <?php echo $card['class']; ?>" data-kpi="<?php echo htmlspecialchars($card['key'], ENT_QUOTES, 'UTF-8'); ?>" role="button" tabindex="0" aria-label="Ver detalle de <?php echo htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="kpi-label"><?php echo htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="kpi-value<?php echo !empty($kpi['sin_base']) ? ' kpi-na' : ''; ?>"><?php echo htmlspecialchars($kpi['porcentaje'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="kpi-detail">
                    <strong><?php echo number_format($kpi['numerador']); ?></strong> <?php echo htmlspecialchars($kpi['label_numerador'], ENT_QUOTES, 'UTF-8'); ?>
                    &nbsp;/&nbsp;
                    <strong><?php echo number_format($kpi['denominador']); ?></strong> <?php echo htmlspecialchars($kpi['label_denominador'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php
                if ($card['key'] === 'atencion' && !empty($kpi['tipo_desglose'])) {
                    echo dashComercialRenderTipoDesgloseHtml($kpi['tipo_desglose']);
                }
                ?>
                <div class="kpi-formula"><?php echo htmlspecialchars($card['formula'], ENT_QUOTES, 'UTF-8'); ?></div>
            </article>
            <?php endforeach; ?>
        </div>

    </section>

    <!-- INDICADORES POR VENDEDORA -->
    <section class="section-block">
        <div class="section-head">
            <div>
                <div class="section-step">Indicadores por vendedora</div>
                <h2 class="section-title">Desempeño individual</h2>
                
            </div>
        </div>

        <?php if (empty($vendedoras)): ?>
            <div class="empty-vendors">No hay vendedoras registradas en el sistema.</div>
        <?php else: ?>
            <div class="vendor-grid">
                <?php foreach ($vendedoras as $v): ?>
                    <?php
                    $pctCal = floatval(str_replace('%', '', $v['calificacion']['porcentaje']));
                    $pctAte = floatval(str_replace('%', '', $v['atencion']['porcentaje']));
                    $pctCie = floatval(str_replace('%', '', $v['cierre']['porcentaje']));
                    ?>
                    <article class="vendor-card<?php echo $v['tiene_actividad'] ? '' : ' inactive'; ?>"
                        data-vendor-id="<?php echo (int) $v['id']; ?>"
                        data-vendor-name="<?php echo htmlspecialchars($v['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                        role="button"
                        tabindex="0"
                        aria-label="Ver detalle de <?php echo htmlspecialchars($v['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="vendor-name"><?php echo htmlspecialchars($v['nombre'], ENT_QUOTES, 'UTF-8'); ?></div>

                       
                        <div class="vendor-kpi">
                            <div class="vendor-kpi-head">
                                <span class="vendor-kpi-title">Tasa de Cierre</span>
                                <span class="vendor-kpi-pct"><?php echo htmlspecialchars($v['cierre']['porcentaje'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="vendor-kpi-detail"><?php echo number_format($v['cierre']['numerador']); ?> clientes / <?php echo number_format($v['cierre']['denominador']); ?> atendidos</div>
                            <?php
                            if (!empty($v['cierre']['tipo_desglose'])) {
                                echo dashComercialRenderTipoDesgloseHtml($v['cierre']['tipo_desglose'], 'vendor-compact');
                            }
                            ?>
                            <div class="vendor-bar"><span class="vendor-bar-fill cierre" style="width: <?php echo min(100, max(0, $pctCie)); ?>%;"></span></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div id="kpiDetailModal" class="dc-modal" aria-hidden="true">
        <div class="dc-modal-backdrop" data-close-modal></div>
        <div class="dc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="kpiModalTitle">
            <div class="dc-modal-header">
                <div>
                    <h3 class="dc-modal-title" id="kpiModalTitle">Detalle KPI</h3>
                    <p class="dc-modal-subtitle" id="kpiModalSubtitle"></p>
                </div>
                <button type="button" class="dc-modal-close" data-close-modal aria-label="Cerrar">&times;</button>
            </div>
            <div class="dc-modal-tabs" id="kpiModalTabs"></div>
            <div class="dc-modal-toolbar">
                <div class="dc-modal-toolbar-left">
                    <div class="dc-modal-status-wrap" id="kpiModalStatusWrap" hidden>
                        <select id="kpiModalStatusFilter" class="dc-modal-status-filter" aria-label="Filtrar por cohorte">
                            <option value="">Todos</option>
                            <option value="cierre">Cierres</option>
                            <option value="atendido">Atendidos</option>
                            <option value="agenda">Agendados</option>
                        </select>
                    </div>
                    <div class="dc-records-count" id="kpiModalCount"></div>
                </div>
                <div class="dc-modal-toolbar-right">
                    <div class="dc-modal-search-wrap" id="kpiModalSearchWrap" hidden>
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input type="search" id="kpiModalSearch" class="dc-modal-search" placeholder="Buscar en la tabla..." autocomplete="off" aria-label="Buscar en la tabla">
                    </div>
                </div>
            </div>
            <div class="dc-modal-body">
                <div class="dc-modal-loading" id="kpiModalLoading">Cargando registros...</div>
                <div class="dc-modal-empty" id="kpiModalEmpty" hidden>Sin registros en este periodo.</div>
                <table class="dc-records-table" id="kpiModalTable" hidden>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Tipo</th>
                            <th class="dc-date-col dc-date-col--agenda">Fecha agenda</th>
                            <th class="dc-date-col dc-date-col--atencion">Fecha atendido</th>
                            <th class="dc-date-col dc-date-col--cierre">Fecha cierre</th>
                            <th>Vendedora</th>
                            <th>Estatus</th>
                        </tr>
                    </thead>
                    <tbody id="kpiModalTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<script>
(function () {
    const FILTER_START = <?php echo json_encode($startDate, JSON_UNESCAPED_UNICODE); ?>;
    const FILTER_END = <?php echo json_encode($endDate, JSON_UNESCAPED_UNICODE); ?>;
    const RANGE_LABEL = <?php echo json_encode($reportRangeLabel, JSON_UNESCAPED_UNICODE); ?>;
    window.TIPO_CLIENTE_BADGE_OVERRIDES = <?php echo json_encode(getTipoClienteBadgeColorOverrides(), JSON_UNESCAPED_UNICODE); ?>;

    const KPI_CONFIG = {
        cierre: {
            title: 'Tasa de Cierre Global',
            tabs: [
                { type: 'clientes', label: 'Clientes cerrados' }
            ]
        },
        calificacion: {
            title: 'Tasa de agendas',
            tabs: [
                { type: 'agendas', label: 'Agendas' }
            ]
        },
        atencion: {
            title: 'Tasa de Atención Global',
            tabs: [
                { type: 'atendidos', label: 'Atendidos' }
            ]
        }
    };

    const MODAL_TABLE_COLUMNS = 9;

    const VENDOR_TABS = {
        tabs: [
            { type: 'todos', label: 'Todos' }
        ]
    };

    function resolveModalCacheKey(type) {
        if (type === 'todos' || type === 'agendas') {
            return 'all-agendas|' + FILTER_START + '|' + FILTER_END + '|' + (activeVendorId || 'global');
        }
        return type + '|' + FILTER_START + '|' + FILTER_END + '|' + (activeVendorId || 'global');
    }

    function resolveModalFetchType(type) {
        if (type === 'todos') {
            return 'agendas';
        }
        return type;
    }

    const modal = document.getElementById('kpiDetailModal');
    const modalTitle = document.getElementById('kpiModalTitle');
    const modalSubtitle = document.getElementById('kpiModalSubtitle');
    const modalTabs = document.getElementById('kpiModalTabs');
    const modalCount = document.getElementById('kpiModalCount');
    const modalSearchWrap = document.getElementById('kpiModalSearchWrap');
    const modalStatusWrap = document.getElementById('kpiModalStatusWrap');
    const modalStatusFilter = document.getElementById('kpiModalStatusFilter');
    const modalSearch = document.getElementById('kpiModalSearch');
    const modalLoading = document.getElementById('kpiModalLoading');
    const modalEmpty = document.getElementById('kpiModalEmpty');
    const modalTable = document.getElementById('kpiModalTable');
    const modalTableBody = document.getElementById('kpiModalTableBody');

    let activeKpi = null;
    let activeVendorId = null;
    let activeTabType = null;
    let recordsCache = {};
    let currentModalRows = [];
    let vendorModalSections = null;
    let vendorModalSummary = null;

    const VENDOR_SECTION_FILTER_MAP = {
        cierre: 'cierre',
        atendido: 'atencion',
        agenda: 'agenda'
    };

    const VENDOR_SECTION_RENDER_GROUPS = [
        { id: 'cierre', key: 'cierre', label: 'Considerados como cierres' },
        { id: 'atendido', key: 'atencion', label: 'Considerados como atendidos' },
        { id: 'agenda', key: 'agenda', label: 'Considerados como agendas' }
    ];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    <?php echo renderLeadStatusBadgeJsFunctions(); ?>

    function normalizeTipoClienteColorKey(label) {
        var key = (label || '').trim().toLowerCase();
        if (!key || key === '—' || key === '-' || key === 'n/a' || key === 'sin dato') {
            return '__empty__';
        }
        if (key === '1' || key === 'wedding planner') {
            return 'wedding planner';
        }
        if (key === '0' || key === 'cliente final') {
            return 'cliente final';
        }
        return key;
    }

    function getTipoClienteBadgeColors(label) {
        var key = normalizeTipoClienteColorKey(label);
        if (window.TIPO_CLIENTE_BADGE_OVERRIDES && window.TIPO_CLIENTE_BADGE_OVERRIDES[key]) {
            return window.TIPO_CLIENTE_BADGE_OVERRIDES[key];
        }
        return { bg: 'rgba(148,163,184,0.12)', border: 'rgba(148,163,184,0.28)', color: '#94a3b8' };
    }

    function getTipoClienteBadgeStyleAttr(label) {
        var colors = getTipoClienteBadgeColors(label);
        return 'background:' + colors.bg + ';border:1px solid ' + colors.border + ';color:' + colors.color + ';';
    }

    function jsRenderTipoClienteBadge(label) {
        var display = (label || '').trim() || '—';
        return '<span class="badge-tipo-cliente" style="' + getTipoClienteBadgeStyleAttr(display) + '">' + escapeHtml(display) + '</span>';
    }

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function setLoading(isLoading) {
        modalLoading.hidden = !isLoading;
        if (isLoading) {
            modalEmpty.hidden = true;
            modalTable.hidden = true;
            modalSearchWrap.hidden = true;
            modalStatusWrap.hidden = true;
        }
    }

    function resetModalSearch() {
        modalSearch.value = '';
    }

    function resetModalStatusFilter() {
        modalStatusFilter.value = '';
    }

    function setModalStatusFilterVisible(isVisible) {
        modalStatusWrap.hidden = !isVisible;
        if (!isVisible) {
            resetModalStatusFilter();
        }
    }

    function normalizeStatusKey(value) {
        return String(value ?? '').trim().toLowerCase();
    }

    function filterRowsByMilestoneGroup(rows, groupFilter) {
        const term = normalizeStatusKey(groupFilter);
        if (!term) {
            return rows;
        }
        return rows.filter(function (row) {
            return resolveRowMilestoneGroup(row) === term;
        });
    }

    function cloneVendorSections(sections) {
        return {
            cierre: (sections.cierre || []).slice(),
            atencion: (sections.atencion || []).slice(),
            agenda: (sections.agenda || []).slice()
        };
    }

    function flattenVendorSections(sections) {
        return []
            .concat(sections.cierre || [])
            .concat(sections.atencion || [])
            .concat(sections.agenda || []);
    }

    function resolveVendorSectionFilterKey(groupFilter) {
        const term = normalizeStatusKey(groupFilter);
        if (!term) {
            return '';
        }
        return VENDOR_SECTION_FILTER_MAP[term] || '';
    }

    function buildVendorSectionsForRender(sections, groupFilter, searchQuery) {
        const sectionFilterKey = resolveVendorSectionFilterKey(groupFilter);
        const filteredSections = {
            cierre: [],
            atencion: [],
            agenda: []
        };

        VENDOR_SECTION_RENDER_GROUPS.forEach(function (group) {
            if (sectionFilterKey && sectionFilterKey !== group.key) {
                return;
            }
            filteredSections[group.key] = filterRowsBySearch(sections[group.key] || [], searchQuery);
        });

        return filteredSections;
    }

    function rowSearchBlob(row) {
        return [
            row.nombre,
            row.email,
            row.telefono,
            row.tipo_cliente,
            row.fecha_agenda,
            row.fecha_atencion,
            row.fecha_cierre,
            row.vendedora,
            row.estatus,
            row.estatus_key,
            row.fecha
        ].map(function (value) {
            return String(value ?? '').toLowerCase();
        }).join(' ');
    }

    function filterRowsBySearch(rows, query) {
        const term = String(query || '').trim().toLowerCase();
        if (!term) {
            return rows;
        }
        return rows.filter(function (row) {
            return rowSearchBlob(row).indexOf(term) !== -1;
        });
    }

    function normalizeMilestoneGroupId(groupKey) {
        if (groupKey === 'atencion') {
            return 'atendido';
        }
        return groupKey;
    }

    function resolveRowMilestoneGroup(row) {
        if (row.milestone_group) {
            return normalizeMilestoneGroupId(row.milestone_group);
        }
        const key = normalizeStatusKey(row.estatus_key || row.estatus);
        if (key === 'cliente') {
            return 'cierre';
        }
        if (key === 'atendido' || key === 'muerto') {
            return 'atendido';
        }
        if (key === 'agendado' || key === 'fantasma') {
            return 'agenda';
        }
        return 'otros';
    }

    function summaryKeyForGroupId(groupId) {
        if (groupId === 'atendido') {
            return 'atencion';
        }
        return groupId;
    }

    function isModalDateEmpty(value) {
        const normalized = String(value ?? '').trim();
        if (normalized === '') {
            return true;
        }
        return normalized === '—'
            || normalized === '-'
            || normalized === '–'
            || normalized === '\u2014'
            || normalized === '\u2013';
    }

    function jsRenderPendingDateCell(colClass) {
        return '<td class="dc-date-col ' + colClass + '"><span class="dc-date-pending">pendiente</span></td>';
    }

    function jsRenderMilestoneDateCell(value, colClass, options) {
        const opts = options || {};
        if (!isModalDateEmpty(value)) {
            return '<td class="dc-date-col ' + colClass + '"><span class="dc-date-value">' + escapeHtml(value) + '</span></td>';
        }

        if (opts.showPending === false) {
            return '<td class="dc-date-col ' + colClass + '"><span class="dc-date-value">—</span></td>';
        }

        if (opts.noteOnly && opts.pendingNote) {
            return '<td class="dc-date-col ' + colClass + '">'
                + '<span class="dc-date-pending-note dc-date-pending-note--solo">' + escapeHtml(opts.pendingNote) + '</span>'
                + '</td>';
        }

        return jsRenderPendingDateCell(colClass);
    }

    function jsRenderDataRow(row) {
        return '<tr>'
            + '<td>' + escapeHtml(row.nombre) + '</td>'
            + '<td>' + escapeHtml(row.email) + '</td>'
            + '<td>' + escapeHtml(row.telefono) + '</td>'
            + '<td>' + jsRenderTipoClienteBadge(row.tipo_cliente) + '</td>'
            + jsRenderMilestoneDateCell(row.fecha_agenda, 'dc-date-col--agenda')
            + jsRenderMilestoneDateCell(row.fecha_atencion, 'dc-date-col--atencion', {
                noteOnly: true,
                pendingNote: row.fecha_atencion_pending_note || null
            })
            + jsRenderMilestoneDateCell(row.fecha_cierre, 'dc-date-col--cierre', {
                showPending: row.fecha_cierre_show_pending !== false
            })
            + '<td>' + escapeHtml(row.vendedora) + '</td>'
            + '<td>' + jsRenderLeadStatusBadge(row.estatus_key || row.estatus, row.estatus) + '</td>'
            + '</tr>';
    }

    function jsRenderGroupHeaderRow(groupId, label, count) {
        const countLabel = count + ' registro' + (count === 1 ? '' : 's');
        return '<tr class="dc-records-group-row dc-records-group-row--' + escapeHtml(groupId) + '">'
            + '<td colspan="' + MODAL_TABLE_COLUMNS + '">'
            + '<div class="dc-records-group-head">'
            + '<span class="dc-records-group-title">' + escapeHtml(label) + '</span>'
            + '<span class="dc-records-group-count" title="' + escapeHtml(countLabel) + '">'
            + escapeHtml(String(count)) + ' · ' + (count === 1 ? 'registro' : 'registros')
            + '</span>'
            + '</div>'
            + '</td>'
            + '</tr>';
    }

    function renderGroupedVendorSections(sections) {
        let html = '';

        VENDOR_SECTION_RENDER_GROUPS.forEach(function (group) {
            const items = sections[group.key] || [];
            if (!items.length) {
                return;
            }
            html += jsRenderGroupHeaderRow(group.id, group.label, items.length);
            html += items.map(jsRenderDataRow).join('');
        });

        return html;
    }

    function renderTableRows(rows, vendorSections) {
        if (activeVendorId && vendorSections) {
            return renderGroupedVendorSections(vendorSections);
        }
        return rows.map(jsRenderDataRow).join('');
    }

    function updateModalCount(visibleCount, totalCount) {
        const total = totalCount ?? visibleCount;
        if (total === 0) {
            modalCount.textContent = '';
            return;
        }
        if (visibleCount === total) {
            modalCount.textContent = total + ' registro' + (total === 1 ? '' : 's');
            return;
        }
        modalCount.textContent = visibleCount + ' de ' + total + ' registro' + (total === 1 ? '' : 's');
    }

    function renderRows(rows, options) {
        const opts = options || {};
        const searchQuery = opts.searchQuery != null ? opts.searchQuery : modalSearch.value;
        const cohortFilter = normalizeStatusKey(modalStatusFilter.value);
        const useVendorSections = Boolean(activeVendorId && vendorModalSections);
        const baseVendorSections = useVendorSections ? cloneVendorSections(vendorModalSections) : null;
        const renderedVendorSections = useVendorSections
            ? buildVendorSectionsForRender(baseVendorSections, cohortFilter, '')
            : null;
        const filteredVendorSections = useVendorSections
            ? buildVendorSectionsForRender(baseVendorSections, cohortFilter, searchQuery)
            : null;

        const sourceRows = useVendorSections
            ? flattenVendorSections(renderedVendorSections)
            : (opts.sourceRows || rows);
        const cohortFilteredRows = useVendorSections
            ? flattenVendorSections(renderedVendorSections)
            : filterRowsByMilestoneGroup(sourceRows, modalStatusFilter.value);
        const filteredRows = useVendorSections
            ? flattenVendorSections(filteredVendorSections)
            : filterRowsBySearch(cohortFilteredRows, searchQuery);

        currentModalRows = sourceRows.slice();
        modalTableBody.innerHTML = renderTableRows(filteredRows, filteredVendorSections);

        const hasSourceRows = sourceRows.length > 0;
        modalSearchWrap.hidden = !hasSourceRows;
        modalStatusWrap.hidden = !hasSourceRows || !activeVendorId;

        let totalForCount = cohortFilteredRows.length;
        if (useVendorSections && cohortFilter && vendorModalSummary) {
            const summaryKey = summaryKeyForGroupId(cohortFilter);
            if (vendorModalSummary[summaryKey] != null) {
                totalForCount = Number(vendorModalSummary[summaryKey]);
            }
        } else if (useVendorSections && !cohortFilter && vendorModalSummary) {
            totalForCount = Number(vendorModalSummary.cierre || 0)
                + Number(vendorModalSummary.atencion || 0)
                + Number(vendorModalSummary.agenda || 0);
        }
        updateModalCount(filteredRows.length, totalForCount);

        if (!hasSourceRows) {
            modalEmpty.hidden = false;
            modalEmpty.textContent = 'Sin registros en este periodo.';
            modalTable.hidden = true;
            return;
        }

        if (cohortFilteredRows.length === 0) {
            modalEmpty.hidden = false;
            modalEmpty.textContent = 'Ningún registro coincide con la cohorte seleccionada.';
            modalTable.hidden = true;
            return;
        }

        if (filteredRows.length === 0) {
            modalEmpty.hidden = false;
            modalEmpty.textContent = 'Ningún registro coincide con la búsqueda.';
            modalTable.hidden = true;
            return;
        }

        modalEmpty.hidden = true;
        modalTable.hidden = false;
    }

    async function fetchRecordsByType(type) {
        const fetchType = resolveModalFetchType(type);
        const cacheKey = resolveModalCacheKey(type);
        if (recordsCache[cacheKey]) {
            return recordsCache[cacheKey];
        }

        const params = new URLSearchParams({ type: fetchType, start_date: FILTER_START, end_date: FILTER_END });
        if (activeVendorId) {
            params.set('vendor_id', String(activeVendorId));
        }

        const response = await fetch('dashboard_comercial_records.php?' + params.toString());
        const rawText = await response.text();
        let data = null;
        try {
            data = rawText ? JSON.parse(rawText) : null;
        } catch (parseError) {
            throw new Error('Respuesta inválida del servidor.');
        }
        if (!data) {
            throw new Error('El servidor no devolvió datos.');
        }
        if (!response.ok) {
            throw new Error(data.error || 'No se pudieron cargar los registros');
        }

        const payload = {
            rows: data.rows || [],
            sections: data.sections || null,
            summary: data.summary || null
        };
        recordsCache[cacheKey] = payload;
        return payload;
    }

    function applyModalPayload(payload) {
        vendorModalSections = activeVendorId ? (payload.sections || null) : null;
        vendorModalSummary = activeVendorId ? (payload.summary || null) : null;
        renderRows(payload.rows || []);
    }

    async function loadTabRecords(type) {
        activeTabType = type;
        resetModalSearch();
        resetModalStatusFilter();
        const cacheKey = resolveModalCacheKey(type);
        if (recordsCache[cacheKey]) {
            applyModalPayload(recordsCache[cacheKey]);
            return;
        }

        setLoading(true);
        modalEmpty.textContent = 'Sin registros en este periodo.';
        try {
            const payload = await fetchRecordsByType(type);
            applyModalPayload(payload);
        } catch (error) {
            modalCount.textContent = '';
            modalSearchWrap.hidden = true;
            modalStatusWrap.hidden = true;
            modalEmpty.hidden = false;
            modalEmpty.textContent = error.message || 'Error al cargar registros.';
            modalTable.hidden = true;
        } finally {
            setLoading(false);
        }
    }

    function renderTabs(config) {
        if (!config.tabs || config.tabs.length <= 1) {
            modalTabs.innerHTML = '';
            modalTabs.hidden = true;
            return;
        }

        modalTabs.hidden = false;
        modalTabs.innerHTML = config.tabs.map(function (tab, index) {
            const activeClass = index === 0 ? ' is-active' : '';
            return '<button type="button" class="dc-modal-tab' + activeClass + '" data-type="' + escapeHtml(tab.type) + '">'
                + escapeHtml(tab.label)
                + '</button>';
        }).join('');

        modalTabs.querySelectorAll('.dc-modal-tab').forEach(function (button) {
            button.addEventListener('click', function () {
                modalTabs.querySelectorAll('.dc-modal-tab').forEach(function (el) { el.classList.remove('is-active'); });
                button.classList.add('is-active');
                loadTabRecords(button.dataset.type);
            });
        });
    }

    function openKpiModal(kpiKey) {
        const config = KPI_CONFIG[kpiKey];
        if (!config) return;

        resetModalSearch();
        resetModalStatusFilter();
        activeKpi = kpiKey;
        activeVendorId = null;
        vendorModalSections = null;
        vendorModalSummary = null;
        setModalStatusFilterVisible(false);
        modalTitle.textContent = config.title;
        modalSubtitle.textContent = 'Periodo: ' + RANGE_LABEL;
        renderTabs(config);
        openModal();
        loadTabRecords(config.tabs[0].type);
    }

    function openVendorModal(vendorId, vendorName) {
        resetModalSearch();
        resetModalStatusFilter();
        activeKpi = null;
        activeVendorId = vendorId;
        setModalStatusFilterVisible(true);
        modalTitle.textContent = vendorName;
        modalSubtitle.textContent = 'Periodo: ' + RANGE_LABEL;
        renderTabs(VENDOR_TABS);
        openModal();
        loadTabRecords(VENDOR_TABS.tabs[0].type);
    }

    document.querySelectorAll('.global-kpi-card[data-kpi]').forEach(function (card) {
        card.addEventListener('click', function () { openKpiModal(card.dataset.kpi); });
        card.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openKpiModal(card.dataset.kpi);
            }
        });
    });

    document.querySelectorAll('.vendor-card[data-vendor-id]').forEach(function (card) {
        card.addEventListener('click', function () {
            openVendorModal(parseInt(card.dataset.vendorId, 10), card.dataset.vendorName || 'Vendedora');
        });
        card.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openVendorModal(parseInt(card.dataset.vendorId, 10), card.dataset.vendorName || 'Vendedora');
            }
        });
    });

    modalSearch.addEventListener('input', function () {
        if (!currentModalRows.length) {
            return;
        }
        renderRows(currentModalRows, { sourceRows: currentModalRows, searchQuery: modalSearch.value });
    });

    modalStatusFilter.addEventListener('change', function () {
        if (!currentModalRows.length) {
            return;
        }
        renderRows(currentModalRows, { sourceRows: currentModalRows, searchQuery: modalSearch.value });
    });

    modal.querySelectorAll('[data-close-modal]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
})();
</script>
<?php include 'footer.php'; ?>
</body>
</html>
