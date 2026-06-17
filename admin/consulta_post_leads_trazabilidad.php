<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tipoUsuario = $_SESSION['tipo_usuario'] ?? 0;

include 'menu.php';
include 'conn.php';
require_once __DIR__ . '/pltrace_leads_helper.php';

$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate   = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$filterPlataforma = isset($_GET['filter_plataforma']) ? trim($_GET['filter_plataforma']) : '';

if (isset($_GET['show_all'])) {
    unset($_SESSION['leads_filter_start'], $_SESSION['leads_filter_end']);
} elseif ($startDate !== '' || $endDate !== '') {
    $_SESSION['leads_filter_start'] = $startDate;
    $_SESSION['leads_filter_end']   = $endDate;
} elseif (!empty($_SESSION['leads_filter_start']) || !empty($_SESSION['leads_filter_end'])) {
    $startDate = $_SESSION['leads_filter_start'] ?? '';
    $endDate   = $_SESSION['leads_filter_end'] ?? '';
}

if ($startDate === '' && $endDate === '' && empty($_GET['show_all'])) {
    $endDate   = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-14 days', strtotime($endDate)));
}

$traceLeads = pltraceFetchConsultaLeadsList($conn, $startDate, $endDate, $filterPlataforma);

$selectedTabla = isset($_GET['tabla']) ? trim((string) $_GET['tabla']) : '';
$selectedOrigId = isset($_GET['orig_id']) ? (int) $_GET['orig_id'] : 0;
$selectedCfId = isset($_GET['cf_id']) ? (int) $_GET['cf_id'] : 0;
if ($selectedCfId <= 0 && isset($_GET['id'])) {
    $selectedCfId = (int) $_GET['id'];
}

$selectedKey = '';
if ($selectedTabla !== '' && $selectedOrigId > 0) {
    $selectedKey = $selectedTabla . '|' . $selectedOrigId;
} elseif ($selectedCfId > 0) {
    foreach ($traceLeads as $leadRow) {
        if ((int) ($leadRow['cf_id'] ?? 0) === $selectedCfId) {
            $selectedKey = (string) ($leadRow['trace_key'] ?? '');
            $selectedTabla = (string) ($leadRow['tabla_origen'] ?? $selectedTabla);
            $selectedOrigId = (int) ($leadRow['orig_id'] ?? $selectedOrigId);
            break;
        }
    }
    if ($selectedKey === '') {
        $cfStmt = $conn->prepare('SELECT tabla_origen, original_lead_id FROM contact_form WHERE id = ? LIMIT 1');
        if ($cfStmt) {
            $cfStmt->bind_param('i', $selectedCfId);
            if ($cfStmt->execute()) {
                $cfRes = $cfStmt->get_result();
                if ($cfRes && ($cfRow = $cfRes->fetch_assoc())) {
                    $resolvedTabla = trim((string) ($cfRow['tabla_origen'] ?? ''));
                    $resolvedOrigId = (int) ($cfRow['original_lead_id'] ?? 0);
                    if ($resolvedTabla !== '' && $resolvedOrigId > 0) {
                        $selectedTabla = $resolvedTabla;
                        $selectedOrigId = $resolvedOrigId;
                        $selectedKey = $resolvedTabla . '|' . $resolvedOrigId;
                    }
                }
            }
            $cfStmt->close();
        }
    }
}

$hasExplicitSelection = ($selectedTabla !== '' && $selectedOrigId > 0) || $selectedCfId > 0;
if ($selectedKey === '' && !empty($traceLeads) && !$hasExplicitSelection) {
    $selectedKey = (string) ($traceLeads[0]['trace_key'] ?? '');
}

$reportRangeLabel = 'Todos los registros';
if ($startDate !== '' || $endDate !== '') {
    $reportRangeStart = $startDate !== '' ? date('d/m/Y', strtotime($startDate)) : '...';
    $reportRangeEnd   = $endDate !== '' ? date('d/m/Y', strtotime($endDate)) : '...';
    $reportRangeLabel = $reportRangeStart . ' → ' . $reportRangeEnd;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trazabilidad de Leads — EFEGE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .pltrace-wrap {
            padding: 16px 20px 0px 0px;
            box-sizing: border-box;
           
            min-height: calc(100vh - 72px);
        }

        /* Estilos acotados a #pltraceApp — no usar :root ni clases genéricas del menú */
        #pltraceApp {
            --pltrace-bg: #f8fafc;
            --pltrace-surface: #ffffff;
            --pltrace-list-bg: #faf9f7;
            --pltrace-border: #e8e5df;
            --pltrace-ink: #1e293b;
            --pltrace-muted: #64748b;
            --pltrace-gold: #c5a028;
            --pltrace-gold-soft: #f5edd6;
            --pltrace-active: #fffbeb;

            display: grid;
            grid-template-columns: 320px 1fr;
            height: calc(100vh - 72px);
            min-height: 640px;
            margin: 0;
            font-family: 'DM Sans', 'Segoe UI', system-ui, sans-serif;
            color: var(--pltrace-ink);
            background: var(--pltrace-bg);
            overflow: hidden;
            border: 1px solid var(--pltrace-border);
            border-radius: 12px;
        }

        #pltraceApp .pltrace-list-panel {
            background: var(--pltrace-list-bg);
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--pltrace-border);
            min-height: 0;
        }

        #pltraceApp .pltrace-list-head {
            padding: 18px 16px 12px;
            border-bottom: 1px solid var(--pltrace-border);
            background: var(--pltrace-surface);
        }

        #pltraceApp .pltrace-list-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0 0 4px;
            color: var(--pltrace-ink);
        }

        #pltraceApp .pltrace-list-sub {
            margin: 0 0 12px;
            font-size: 0.78rem;
            color: var(--pltrace-muted);
        }

        #pltraceApp .pltrace-search-box {
            position: relative;
        }

        #pltraceApp .pltrace-search-box i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--pltrace-muted);
            font-size: 0.82rem;
        }

        #pltraceApp .pltrace-search-input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid var(--pltrace-border);
            background: #fff;
            color: var(--pltrace-ink);
            border-radius: 8px;
            padding: 9px 10px 9px 32px;
            font-size: 0.88rem;
        }

        #pltraceApp .pltrace-search-input::placeholder { color: #94a3b8; }

        #pltraceApp .pltrace-filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 10px 12px;
            border-bottom: 1px solid var(--pltrace-border);
            background: var(--pltrace-surface);
        }

        #pltraceApp .pltrace-filter-row input[type="date"] {
            flex: 1 1 120px;
            min-width: 0;
            border: 1px solid var(--pltrace-border);
            background: #fff;
            color: var(--pltrace-ink);
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 0.78rem;
        }

        #pltraceApp .pltrace-filter-submit {
            border: 1px solid var(--pltrace-gold);
            background: var(--pltrace-gold);
            color: #1a1a1a;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
        }

        #pltraceApp .pltrace-filter-submit:hover {
            background: #b89224;
            border-color: #b89224;
        }

        #pltraceApp .pltrace-leads-scroll {
            flex: 1;
            overflow: auto;
            padding: 8px;
            min-height: 0;
        }

        #pltraceApp .pltrace-lead-card {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            width: 100%;
            border: 1px solid transparent;
            background: var(--pltrace-surface);
            color: inherit;
            text-align: left;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 6px;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        #pltraceApp .pltrace-lead-card:hover {
            border-color: var(--pltrace-border);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        }

        #pltraceApp .pltrace-lead-card.pltrace-selected {
            background: var(--pltrace-active);
            border-color: var(--pltrace-gold);
            box-shadow: 0 0 0 1px rgba(197, 160, 40, 0.25);
        }

        #pltraceApp .pltrace-lead-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--pltrace-gold-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7a5c00;
            flex-shrink: 0;
        }

        #pltraceApp .pltrace-lead-body { min-width: 0; flex: 1; }

        #pltraceApp .pltrace-lead-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--pltrace-ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #pltraceApp .pltrace-lead-email {
            font-size: 0.74rem;
            color: var(--pltrace-muted);
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #pltraceApp .pltrace-badge {
            display: inline-block;
            margin-top: 6px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        #pltraceApp .pltrace-badge--atendido { background: #ecfdf5; color: #047857; }
        #pltraceApp .pltrace-badge--cliente { background: #f5f3ff; color: #6d28d9; }
        #pltraceApp .pltrace-badge--fantasma { background: #fffbeb; color: #b45309; }
        #pltraceApp .pltrace-badge--muerto { background: #fef2f2; color: #b91c1c; }
        #pltraceApp .pltrace-badge--agendado { background: #eff6ff; color: #1d4ed8; }
        #pltraceApp .pltrace-badge--lead { background: #f1f5f9; color: #334155; }
        #pltraceApp .pltrace-badge--default { background: #f1f5f9; color: #475569; }

        #pltraceApp .pltrace-list-empty {
            padding: 16px;
            color: var(--pltrace-muted);
            font-size: 0.86rem;
        }

        #pltraceApp .pltrace-detail-panel {
            display: flex;
            flex-direction: column;
            min-height: 0;
            background: var(--pltrace-surface);
        }

        #pltraceApp .pltrace-detail-head {
            padding: 16px 24px;
            border-bottom: 1px solid var(--pltrace-border);
            background: var(--pltrace-surface);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        #pltraceApp .pltrace-detail-title {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 700;
        }

        #pltraceApp .pltrace-detail-sub {
            margin: 4px 0 0;
            font-size: 0.84rem;
            color: var(--pltrace-muted);
        }

        #pltraceApp .pltrace-period-chip {
            font-size: 0.78rem;
            color: var(--pltrace-muted);
            background: var(--pltrace-bg);
            border: 1px solid var(--pltrace-border);
            border-radius: 999px;
            padding: 6px 12px;
            white-space: nowrap;
        }

        #pltraceApp .pltrace-timeline-scroll {
            flex: 1;
            overflow: auto;
            padding: 24px 20px 32px;
            background: linear-gradient(180deg, #faf9f7 0%, #fff 140px);
            min-height: 0;
        }

        #pltraceApp .pltrace-timeline-empty {
            height: 100%;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--pltrace-muted);
            font-size: 0.95rem;
            text-align: center;
            padding: 24px;
        }

        #pltraceApp .pltrace-day-marker {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 0 16px;
            color: var(--pltrace-muted);
            font-size: 0.76rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        #pltraceApp .pltrace-day-marker::before,
        #pltraceApp .pltrace-day-marker::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--pltrace-border);
        }

        #pltraceApp .pltrace-entry {
            max-width: 720px;
            margin: 0 auto 14px;
            display: grid;
            grid-template-columns: 40px 1fr;
            gap: 12px;
            align-items: start;
        }

        #pltraceApp .pltrace-entry-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.9rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.1);
        }

        #pltraceApp .pltrace-entry-card {
            background: #fff;
            border: 1px solid var(--pltrace-border);
            border-radius: 12px;
            padding: 12px 14px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        #pltraceApp .pltrace-entry--interaccion {
            margin-bottom: 10px;
        }

        #pltraceApp .pltrace-entry--interaccion .pltrace-entry-icon {
            width: 28px;
            height: 28px;
            font-size: 0.72rem;
            box-shadow: none;
            opacity: 0.9;
        }

        #pltraceApp .pltrace-entry--interaccion .pltrace-entry-body {
            padding: 1px 0 0;
            border-left: 2px solid #bae6fd;
            padding-left: 10px;
        }

        #pltraceApp .pltrace-entry--interaccion .pltrace-entry-when {
            font-size: 0.72rem;
            margin-bottom: 2px;
        }

        #pltraceApp .pltrace-entry--interaccion .pltrace-entry-label {
            font-size: 0.86rem;
            font-weight: 600;
            color: #0369a1;
            margin-bottom: 2px;
        }

        #pltraceApp .pltrace-entry--interaccion .pltrace-entry-text {
            font-size: 0.82rem;
            color: #64748b;
        }

        #pltraceApp .pltrace-entry--interaccion .pltrace-entry-extra {
            margin-top: 4px;
            padding-top: 0;
            border-top: none;
            font-size: 0.76rem;
        }

        #pltraceApp .pltrace-entry-when {
            font-size: 0.74rem;
            color: var(--pltrace-muted);
            margin-bottom: 4px;
        }

        #pltraceApp .pltrace-entry-label {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        #pltraceApp .pltrace-entry-text {
            font-size: 0.84rem;
            color: #475569;
            line-height: 1.45;
        }

        #pltraceApp .pltrace-entry-extra {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed var(--pltrace-border);
            font-size: 0.78rem;
            color: var(--pltrace-muted);
            line-height: 1.5;
        }

        #pltraceApp .pltrace-estimated-tag {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 7px;
            border-radius: 999px;
            background: #fff7ed;
            color: #c2410c;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        #pltraceApp .pltrace-readonly-note {
            border-top: 1px solid var(--pltrace-border);
            background: var(--pltrace-bg);
            padding: 14px 20px;
            color: var(--pltrace-muted);
            font-size: 0.84rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #pltraceApp .pltrace-readonly-note i { color: #94a3b8; }

        #pltraceApp .pltrace-ico-pre_lead { background: #818cf8; }
        #pltraceApp .pltrace-ico-post_lead { background: #60a5fa; }
        #pltraceApp .pltrace-ico-estatus_0 { background: #3b82f6; }
        #pltraceApp .pltrace-ico-estatus_1 { background: #10b981; }
        #pltraceApp .pltrace-ico-estatus_2 { background: #f59e0b; }
        #pltraceApp .pltrace-ico-estatus_3 { background: #ef4444; }
        #pltraceApp .pltrace-ico-estatus_4,
        #pltraceApp .pltrace-ico-cliente_cierre { background: #8b5cf6; }
        #pltraceApp .pltrace-ico-interaccion { background: #0ea5e9; }
        #pltraceApp .pltrace-ico-default { background: #94a3b8; }

        @media (max-width: 900px) {
            #pltraceApp {
                grid-template-columns: 1fr;
                height: auto;
                min-height: calc(100vh - 72px);
            }
            #pltraceApp .pltrace-list-panel { max-height: 42vh; }
            #pltraceApp .pltrace-detail-panel { min-height: 58vh; }
        }
    </style>
</head>
<body>

<div class="pltrace-wrap">
<div id="pltraceApp">
    <aside class="pltrace-list-panel">
        <div class="pltrace-list-head">
            <h1 class="pltrace-list-title">Trazabilidad</h1>
            <p class="pltrace-list-sub">Leads pre-calificación · <?php echo count($traceLeads); ?> registros</p>
            <div class="pltrace-search-box">
                <i class="fas fa-search"></i>
                <input type="search" id="pltraceSearchInput" class="pltrace-search-input" placeholder="Buscar lead...">
            </div>
        </div>

        <form method="get" class="pltrace-filter-row">
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="pltrace-filter-submit">Filtrar</button>
            <?php if ($selectedKey !== ''): ?>
                <?php
                $selParts = explode('|', $selectedKey, 2);
                $selTabla = $selParts[0] ?? '';
                $selOrigId = isset($selParts[1]) ? (int) $selParts[1] : 0;
                ?>
                <?php if ($selTabla !== '' && $selOrigId > 0): ?>
                    <input type="hidden" name="tabla" value="<?php echo htmlspecialchars($selTabla, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="orig_id" value="<?php echo (int) $selOrigId; ?>">
                <?php endif; ?>
            <?php endif; ?>
        </form>

        <div class="pltrace-leads-scroll" id="pltraceLeadsScroll">
            <?php if (empty($traceLeads)): ?>
                <div class="pltrace-list-empty">No hay leads en este periodo.</div>
            <?php else: ?>
                <?php foreach ($traceLeads as $lead): ?>
                    <?php
                    $traceKey = (string) ($lead['trace_key'] ?? '');
                    $tablaOrigen = (string) ($lead['tabla_origen'] ?? '');
                    $origId = (int) ($lead['orig_id'] ?? 0);
                    $cfId = (int) ($lead['cf_id'] ?? 0);
                    $statusRaw = mb_strtolower(trim((string) ($lead['estatus_raw'] ?? $lead['estatus'] ?? '')), 'UTF-8');
                    $badgeClass = 'pltrace-badge--default';
                    if ($statusRaw === 'lead') $badgeClass = 'pltrace-badge--lead';
                    elseif ($statusRaw === 'atendido') $badgeClass = 'pltrace-badge--atendido';
                    elseif ($statusRaw === 'cliente') $badgeClass = 'pltrace-badge--cliente';
                    elseif ($statusRaw === 'fantasma') $badgeClass = 'pltrace-badge--fantasma';
                    elseif ($statusRaw === 'muerto') $badgeClass = 'pltrace-badge--muerto';
                    elseif ($statusRaw === 'agendado') $badgeClass = 'pltrace-badge--agendado';
                    ?>
                    <button type="button"
                        class="pltrace-lead-card<?php echo $traceKey === $selectedKey ? ' pltrace-selected' : ''; ?>"
                        data-pltrace-key="<?php echo htmlspecialchars($traceKey, ENT_QUOTES, 'UTF-8'); ?>"
                        data-pltrace-tabla="<?php echo htmlspecialchars($tablaOrigen, ENT_QUOTES, 'UTF-8'); ?>"
                        data-pltrace-orig-id="<?php echo (int) $origId; ?>"
                        data-pltrace-cf-id="<?php echo (int) $cfId; ?>"
                        data-pltrace-name="<?php echo htmlspecialchars($lead['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-pltrace-estatus="<?php echo htmlspecialchars($lead['estatus'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-pltrace-search="<?php echo htmlspecialchars(mb_strtolower(($lead['nombre'] ?? '') . ' ' . ($lead['email'] ?? '') . ' ' . ($lead['telefono'] ?? ''), 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="pltrace-lead-avatar"><i class="fas fa-user"></i></div>
                        <div class="pltrace-lead-body">
                            <div class="pltrace-lead-name"><?php echo htmlspecialchars($lead['nombre'] ?? 'Sin nombre', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="pltrace-lead-email"><?php echo htmlspecialchars($lead['email'] ?: ($lead['telefono'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <span class="pltrace-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($lead['estatus'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <section class="pltrace-detail-panel">
        <header class="pltrace-detail-head">
            <div>
                <h2 class="pltrace-detail-title" id="pltraceDetailTitle">Selecciona un lead</h2>
                <p class="pltrace-detail-sub" id="pltraceDetailSub">Historial completo de estatus y fechas</p>
            </div>
            <div class="pltrace-period-chip"><i class="far fa-calendar"></i> <?php echo htmlspecialchars($reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        </header>

        <div class="pltrace-timeline-scroll" id="pltraceTimelineScroll">
            <div class="pltrace-timeline-empty" id="pltraceTimelineEmpty">Selecciona un lead de la lista para ver su trazabilidad.</div>
        </div>

        <div class="pltrace-readonly-note">
            <i class="fas fa-lock"></i>
            Vista de solo lectura — por ahora no se pueden enviar mensajes, solo consultar la trazabilidad.
        </div>
    </section>
</div>
</div>

<script>
(function () {
    const INITIAL_TRACE_KEY = <?php echo json_encode($selectedKey, JSON_UNESCAPED_UNICODE); ?>;
    const INITIAL_TABLA = <?php echo json_encode($selectedTabla, JSON_UNESCAPED_UNICODE); ?>;
    const INITIAL_ORIG_ID = <?php echo (int) $selectedOrigId; ?>;
    const INITIAL_CF_ID = <?php echo (int) $selectedCfId; ?>;
    const ICONS = {
        pre_lead: 'fa-user-plus',
        post_lead: 'fa-inbox',
        estatus_0: 'fa-calendar-plus',
        estatus_1: 'fa-headset',
        estatus_2: 'fa-ghost',
        estatus_3: 'fa-ban',
        estatus_4: 'fa-handshake',
        cliente_cierre: 'fa-handshake',
        interaccion: 'fa-comments',
        default: 'fa-circle-info'
    };

    const appRoot = document.getElementById('pltraceApp');
    const chatEl = document.getElementById('pltraceTimelineScroll');
    const titleEl = document.getElementById('pltraceDetailTitle');
    const subEl = document.getElementById('pltraceDetailSub');
    const searchEl = document.getElementById('pltraceSearchInput');
    let loadToken = 0;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function parseEventDate(raw) {
        if (!raw) return null;
        const normalized = String(raw).replace(' ', 'T');
        const d = new Date(normalized);
        return Number.isNaN(d.getTime()) ? null : d;
    }

    function formatRelativeDay(date) {
        const now = new Date();
        const startToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const startDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const diffDays = Math.round((startToday - startDate) / 86400000);

        if (diffDays === 0) return 'Hoy';
        if (diffDays === 1) return 'Ayer';
        if (diffDays > 1 && diffDays < 7) return 'Hace ' + diffDays + ' días';
        if (diffDays >= 7 && diffDays < 14) return 'Hace 1 semana';
        if (diffDays >= 14 && diffDays < 21) return 'Hace 2 semanas';
        if (diffDays >= 21 && diffDays < 28) return 'Hace 3 semanas';
        return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function dayKey(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }

    function renderTrace(events) {
        chatEl.innerHTML = '';
        if (!events.length) {
            chatEl.innerHTML = '<div class="pltrace-timeline-empty">Sin eventos registrados para este lead.</div>';
            return;
        }

        let lastDay = null;
        events.forEach(function (ev) {
            const date = parseEventDate(ev.fecha);
            if (date) {
                const key = dayKey(date);
                if (key !== lastDay) {
                    lastDay = key;
                    const sep = document.createElement('div');
                    sep.className = 'pltrace-day-marker';
                    sep.textContent = formatRelativeDay(date);
                    chatEl.appendChild(sep);
                }
            }

            const tipo = ev.tipo || 'default';
            const icon = ICONS[tipo] || ICONS.default;
            const estimated = ev.es_estimado ? '<span class="pltrace-estimated-tag">Fecha estimada</span>' : '';

            let metaHtml = '';
            if (ev.meta && typeof ev.meta === 'object') {
                Object.keys(ev.meta).forEach(function (key) {
                    if (!ev.meta[key]) return;
                    metaHtml += '<div><strong>' + escapeHtml(key) + ':</strong> ' + escapeHtml(ev.meta[key]) + '</div>';
                });
            }

            const block = document.createElement('div');
            const isInteraccion = tipo === 'interaccion';
            block.className = isInteraccion ? 'pltrace-entry pltrace-entry--interaccion' : 'pltrace-entry';
            const bodyClass = isInteraccion ? 'pltrace-entry-body' : 'pltrace-entry-card';
            block.innerHTML =
                '<div class="pltrace-entry-icon pltrace-ico-' + escapeHtml(tipo) + '"><i class="fas ' + icon + '"></i></div>' +
                '<div class="' + bodyClass + '">' +
                    '<div class="pltrace-entry-when">' + escapeHtml(ev.fecha_display || '—') + estimated + '</div>' +
                    '<div class="pltrace-entry-label">' + escapeHtml(ev.label || 'Evento') + '</div>' +
                    (ev.detalle ? '<div class="pltrace-entry-text">' + escapeHtml(ev.detalle) + '</div>' : '') +
                    (metaHtml ? '<div class="pltrace-entry-extra">' + metaHtml + '</div>' : '') +
                '</div>';
            chatEl.appendChild(block);
        });

        chatEl.scrollTop = chatEl.scrollHeight;
    }

    function setActiveItem(traceKey) {
        appRoot.querySelectorAll('.pltrace-lead-card').forEach(function (btn) {
            btn.classList.toggle('pltrace-selected', (btn.dataset.pltraceKey || '') === traceKey);
        });
    }

    function buildTracerUrl(tabla, origId, cfId) {
        if (cfId > 0) {
            return 'post_lead_tracer.php?id=' + encodeURIComponent(cfId);
        }
        return 'post_lead_tracer.php?tabla=' + encodeURIComponent(tabla) + '&orig_id=' + encodeURIComponent(origId);
    }

    function loadLeadTrace(tabla, origId, cfId, meta) {
        const traceKey = tabla + '|' + origId;
        setActiveItem(traceKey);

        const name = meta && meta.name ? meta.name : ('Lead #' + origId);
        const estatus = meta && meta.estatus ? meta.estatus : '';
        titleEl.textContent = name;
        subEl.textContent = estatus ? ('Estatus actual: ' + estatus) : 'Historial completo de estatus y fechas';

        chatEl.innerHTML = '<div class="pltrace-timeline-empty">Cargando trazabilidad...</div>';

        const token = ++loadToken;
        fetch(buildTracerUrl(tabla, origId, cfId), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (token !== loadToken) return;
                if (!data || !data.success) {
                    chatEl.innerHTML = '<div class="pltrace-timeline-empty">' + escapeHtml((data && data.error) || 'No se pudo cargar la trazabilidad') + '</div>';
                    return;
                }
                if (data.lead && data.lead.nombre) {
                    titleEl.textContent = data.lead.nombre;
                }
                if (data.lead && data.lead.estatus_actual) {
                    subEl.textContent = 'Estatus actual: ' + data.lead.estatus_actual;
                }
                renderTrace(data.events || []);
            })
            .catch(function () {
                if (token !== loadToken) return;
                chatEl.innerHTML = '<div class="pltrace-timeline-empty">Error de comunicación con el servidor.</div>';
            });

        const url = new URL(window.location.href);
        url.searchParams.delete('id');
        url.searchParams.delete('cf_id');
        url.searchParams.set('tabla', tabla);
        url.searchParams.set('orig_id', String(origId));
        if (cfId > 0) {
            url.searchParams.set('cf_id', String(cfId));
        }
        window.history.replaceState({}, '', url.toString());
    }

    appRoot.querySelectorAll('.pltrace-lead-card').forEach(function (btn) {
        btn.addEventListener('click', function () {
            loadLeadTrace(
                btn.dataset.pltraceTabla || '',
                parseInt(btn.dataset.pltraceOrigId, 10) || 0,
                parseInt(btn.dataset.pltraceCfId, 10) || 0,
                {
                    name: btn.dataset.pltraceName || '',
                    estatus: btn.dataset.pltraceEstatus || ''
                }
            );
        });
    });

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            const q = searchEl.value.trim().toLowerCase();
            appRoot.querySelectorAll('.pltrace-lead-card').forEach(function (btn) {
                const hay = (btn.dataset.pltraceSearch || '').indexOf(q) !== -1;
                btn.style.display = hay ? '' : 'none';
            });
        });
    }

    if (INITIAL_TRACE_KEY || INITIAL_CF_ID > 0) {
        let initialBtn = null;
        appRoot.querySelectorAll('.pltrace-lead-card').forEach(function (btn) {
            if (!initialBtn && INITIAL_TRACE_KEY && (btn.dataset.pltraceKey || '') === INITIAL_TRACE_KEY) {
                initialBtn = btn;
            }
        });
        if (initialBtn) {
            loadLeadTrace(
                initialBtn.dataset.pltraceTabla || '',
                parseInt(initialBtn.dataset.pltraceOrigId, 10) || 0,
                parseInt(initialBtn.dataset.pltraceCfId, 10) || 0,
                {
                    name: initialBtn.dataset.pltraceName || '',
                    estatus: initialBtn.dataset.pltraceEstatus || ''
                }
            );
        } else if (INITIAL_TABLA && INITIAL_ORIG_ID > 0) {
            loadLeadTrace(INITIAL_TABLA, INITIAL_ORIG_ID, INITIAL_CF_ID, {});
        } else if (INITIAL_CF_ID > 0) {
            loadLeadTrace('', 0, INITIAL_CF_ID, {});
        }
    }
})();
</script>
</body>
</html>
