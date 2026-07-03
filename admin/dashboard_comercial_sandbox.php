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
        .dc-modal-dialog { position: relative; width: min(1120px, 100%); max-height: 85vh; background: var(--card); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 24px 60px rgba(15,23,42,0.2); display: flex; flex-direction: column; overflow: hidden; }
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
        .dc-records-table tbody tr:hover { background: #f8fafc; }
        .dc-modal-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 24px; border-bottom: 1px solid var(--border); background: var(--bg); }
        .dc-records-count { font-size: 0.82rem; color: var(--muted); }
        .dc-modal-search-wrap { position: relative; flex: 1 1 220px; max-width: 320px; min-width: 180px; }
        .dc-modal-search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 0.82rem; pointer-events: none; }
        .dc-modal-search { width: 100%; border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px 8px 32px; font-size: 0.86rem; background: #fff; color: var(--ink); }
        .dc-modal-search:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 2px rgba(197,160,40,0.15); }
        .dc-modal-search-wrap[hidden] { display: none; }
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
                <p class="section-subtitle">Métricas consolidadas del funnel comercial en el periodo seleccionado.</p>
            </div>
            <div class="range-chip"><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <?php $cierreKpi = $globalKpis['cierre']; ?>

        <div class="dashboard-view-note" style="margin-bottom: 16px;">
            <div class="dashboard-view-note-title">Vista Dashboard</div>
            <p><strong>Nota:</strong> Los números de este panel coinciden con las pantallas de Leads, Agendados, Post-leads y Clientes si usas el mismo rango de fechas. En Atendidos y Post-leads solo se consideran registros desde el <strong>15 de junio de 2026</strong> en adelante.</p>
        </div>

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
                <p class="section-subtitle">Mismas tasas segmentadas por vendedor/a asignado (leads y eventos WP), alineadas con clientes.php y consulta_post_leads.php.</p>
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
                <div class="dc-records-count" id="kpiModalCount"></div>
                <div class="dc-modal-search-wrap" id="kpiModalSearchWrap" hidden>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" id="kpiModalSearch" class="dc-modal-search" placeholder="Buscar en la tabla..." autocomplete="off" aria-label="Buscar en la tabla">
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
                            <th>Fecha de atendido</th>
                            <th>Fecha cierre</th>
                            <th>Vendedora</th>
                            <th>Estatus</th>
                            <th>Cita</th>
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

    const VENDOR_TABS = {
        tabs: [
            { type: 'todos', label: 'Todos' },
            { type: 'agendas', label: 'Leads agendados' },
            { type: 'atendidos', label: 'Leads atendidos' },
            { type: 'clientes', label: 'Clientes' }
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
        }
    }

    function resetModalSearch() {
        modalSearch.value = '';
    }

    function rowSearchBlob(row) {
        return [
            row.nombre,
            row.email,
            row.telefono,
            row.tipo_cliente,
            row.fecha_atencion,
            row.fecha_cierre,
            row.vendedora,
            row.estatus,
            row.estatus_key,
            row.cita,
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
        const sourceRows = opts.sourceRows || rows;
        const searchQuery = opts.searchQuery != null ? opts.searchQuery : modalSearch.value;

        currentModalRows = sourceRows.slice();
        const filteredRows = filterRowsBySearch(sourceRows, searchQuery);

        modalTableBody.innerHTML = filteredRows.map(function (row) {
            return '<tr>'
                + '<td>' + escapeHtml(row.nombre) + '</td>'
                + '<td>' + escapeHtml(row.email) + '</td>'
                + '<td>' + escapeHtml(row.telefono) + '</td>'
                + '<td>' + jsRenderTipoClienteBadge(row.tipo_cliente) + '</td>'
                + '<td>' + escapeHtml((row.fecha_atencion && row.fecha_atencion !== '—') ? row.fecha_atencion : 'pendiente') + '</td>'
                + '<td>' + escapeHtml((row.fecha_cierre && row.fecha_cierre !== '—') ? row.fecha_cierre : 'pendiente') + '</td>'
                + '<td>' + escapeHtml(row.vendedora) + '</td>'
                + '<td>' + jsRenderLeadStatusBadge(row.estatus_key || row.estatus, row.estatus) + '</td>'
                + '<td>' + escapeHtml(row.cita) + '</td>'
                + '</tr>';
        }).join('');

        const hasSourceRows = sourceRows.length > 0;
        modalSearchWrap.hidden = !hasSourceRows;
        updateModalCount(filteredRows.length, sourceRows.length);

        if (!hasSourceRows) {
            modalEmpty.hidden = false;
            modalEmpty.textContent = 'Sin registros en este periodo.';
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

        recordsCache[cacheKey] = data.rows || [];
        return recordsCache[cacheKey];
    }

    async function loadTabRecords(type) {
        activeTabType = type;
        resetModalSearch();
        const cacheKey = resolveModalCacheKey(type);
        if (recordsCache[cacheKey]) {
            renderRows(recordsCache[cacheKey]);
            return;
        }

        setLoading(true);
        modalEmpty.textContent = 'Sin registros en este periodo.';
        try {
            const rows = await fetchRecordsByType(type);
            recordsCache[cacheKey] = rows;
            renderRows(rows);
        } catch (error) {
            modalCount.textContent = '';
            modalSearchWrap.hidden = true;
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
        activeKpi = kpiKey;
        activeVendorId = null;
        modalTitle.textContent = config.title;
        modalSubtitle.textContent = 'Periodo: ' + RANGE_LABEL;
        renderTabs(config);
        openModal();
        loadTabRecords(config.tabs[0].type);
    }

    function openVendorModal(vendorId, vendorName) {
        resetModalSearch();
        activeKpi = null;
        activeVendorId = vendorId;
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
