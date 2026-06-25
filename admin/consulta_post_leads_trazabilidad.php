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
$filterTipo = isset($_GET['filter_tipo']) ? trim((string) $_GET['filter_tipo']) : '';
if (!in_array($filterTipo, ['', 'wp', 'leads', 'clientes'], true)) {
    $filterTipo = '';
}
$filterEstatus = isset($_GET['filter_estatus']) ? trim((string) $_GET['filter_estatus']) : '';
$allowedEstatus = ['', 'lead', 'agendado', 'atendido', 'fantasma', 'muerto', 'cliente'];
if (!in_array($filterEstatus, $allowedEstatus, true)) {
    $filterEstatus = '';
}

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

$allTraceLeads = pltraceFetchConsultaLeadsList($conn, $startDate, $endDate, $filterPlataforma);
$traceLeadTotalCount = count($allTraceLeads);
$traceLeadWpCount = 0;
$traceLeadClientCount = 0;
$traceLeadOnlyCount = 0;
foreach ($allTraceLeads as $leadRowCount) {
    if (!empty($leadRowCount['is_wedding_planner'])) {
        $traceLeadWpCount++;
    } elseif (!empty($leadRowCount['is_cliente'])) {
        $traceLeadClientCount++;
    } else {
        $traceLeadOnlyCount++;
    }
}

$traceLeads = $allTraceLeads;
if ($filterTipo === 'wp') {
    $traceLeads = array_values(array_filter($allTraceLeads, function ($row) {
        return !empty($row['is_wedding_planner']);
    }));
} elseif ($filterTipo === 'clientes') {
    $traceLeads = array_values(array_filter($allTraceLeads, function ($row) {
        return !empty($row['is_cliente']) && empty($row['is_wedding_planner']);
    }));
} elseif ($filterTipo === 'leads') {
    $traceLeads = array_values(array_filter($allTraceLeads, function ($row) {
        return empty($row['is_wedding_planner']) && empty($row['is_cliente']);
    }));
}

$traceLeadCountBeforeEstatus = count($traceLeads);
if ($filterEstatus !== '') {
    $traceLeads = array_values(array_filter($traceLeads, function ($row) use ($filterEstatus) {
        $raw = mb_strtolower(trim((string) ($row['estatus_raw'] ?? $row['estatus'] ?? '')), 'UTF-8');
        return $raw === $filterEstatus;
    }));
}

$pltracePendingBadgesMap = pltraceFetchLeadPendingBadgesMap($conn, $traceLeads);

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
    foreach ($allTraceLeads as $leadRow) {
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

$initialDetailName = 'Selecciona un lead';
$initialDetailEstatus = '';
$initialDetailVendedora = '';
$initialDetailHasLead = false;
if ($selectedKey !== '') {
    foreach ($allTraceLeads as $leadRowMeta) {
        if ((string) ($leadRowMeta['trace_key'] ?? '') === $selectedKey) {
            $initialDetailName = trim((string) ($leadRowMeta['nombre'] ?? $initialDetailName));
            $initialDetailEstatus = trim((string) ($leadRowMeta['estatus'] ?? ''));
            $initialDetailVendedora = trim((string) ($leadRowMeta['vendedora'] ?? ''));
            $initialDetailHasLead = true;
            break;
        }
    }
}
$initialDetailVendedoraInitial = pltraceVendedorInitial($initialDetailVendedora);
$initialDetailVendedoraColor = pltraceVendedorColor($initialDetailVendedora);

$reportRangeLabel = 'Todos los registros';
if ($startDate !== '' || $endDate !== '') {
    $reportRangeStart = $startDate !== '' ? date('d/m/Y', strtotime($startDate)) : '...';
    $reportRangeEnd   = $endDate !== '' ? date('d/m/Y', strtotime($endDate)) : '...';
    $reportRangeLabel = $reportRangeStart . ' → ' . $reportRangeEnd;
}

$pltraceListQueryParams = [];
if ($startDate !== '') {
    $pltraceListQueryParams['start_date'] = $startDate;
}
if ($endDate !== '') {
    $pltraceListQueryParams['end_date'] = $endDate;
}
if ($filterPlataforma !== '') {
    $pltraceListQueryParams['filter_plataforma'] = $filterPlataforma;
}
if ($filterEstatus !== '') {
    $pltraceListQueryParams['filter_estatus'] = $filterEstatus;
}
if ($selectedTabla !== '' && $selectedOrigId > 0) {
    $pltraceListQueryParams['tabla'] = $selectedTabla;
    $pltraceListQueryParams['orig_id'] = $selectedOrigId;
} elseif ($selectedCfId > 0) {
    $pltraceListQueryParams['cf_id'] = $selectedCfId;
}

$pltraceBuildTipoFilterUrl = function ($tipoValue) use ($pltraceListQueryParams) {
    $params = $pltraceListQueryParams;
    if ($tipoValue !== '') {
        $params['filter_tipo'] = $tipoValue;
    } else {
        unset($params['filter_tipo']);
    }
    $query = http_build_query($params);
    return 'consulta_post_leads_trazabilidad.php' . ($query !== '' ? '?' . $query : '');
};

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

        #pltraceApp .pltrace-filter-row input[type="date"],
        #pltraceApp .pltrace-filter-row select {
            flex: 1 1 120px;
            min-width: 0;
            border: 1px solid var(--pltrace-border);
            background: #fff;
            color: var(--pltrace-ink);
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 0.78rem;
        }

        #pltraceApp .pltrace-filter-row select {
            flex: 1 1 100%;
            cursor: pointer;
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
            position: relative;
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
        #pltraceApp .pltrace-badge--wp { background: #fdf4ff; color: #86198f; margin-left: 4px; }

        #pltraceApp .pltrace-lead-type-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 0 0 12px;
        }

        #pltraceApp .pltrace-lead-type-tab {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid var(--pltrace-border);
            background: #fff;
            color: var(--pltrace-muted);
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 0.72rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }

        #pltraceApp .pltrace-lead-type-tab:hover {
            border-color: var(--pltrace-gold);
            color: var(--pltrace-ink);
        }

        #pltraceApp .pltrace-lead-type-tab.pltrace-lead-type-tab--active {
            background: var(--pltrace-gold-soft);
            border-color: var(--pltrace-gold);
            color: #7c5e10;
        }

        #pltraceApp .pltrace-lead-type-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: rgba(100, 116, 139, 0.12);
            font-size: 0.66rem;
            font-weight: 700;
        }

        #pltraceApp .pltrace-lead-type-tab.pltrace-lead-type-tab--active .pltrace-lead-type-count {
            background: rgba(197, 160, 40, 0.22);
            color: #7c5e10;
        }

        #pltraceApp .pltrace-lead-action-top {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 6px;
        }

        #pltraceApp .interaction-type-badge,
        #pltraceApp .outcome-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.62rem;
            font-weight: 600;
            border-radius: 999px;
            padding: 2px 7px;
            border: 1px solid transparent;
            line-height: 1.2;
        }

        #pltraceApp .pending-chip {
            border-radius: 999px;
            padding: 2px 7px;
            font-size: 0.62rem;
            font-weight: 700;
            background: rgba(197, 160, 40, 0.16);
            color: #8c6816;
            line-height: 1.2;
        }

        #pltraceApp .pending-chip--overdue {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        #pltraceApp .type-llamada      { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
        #pltraceApp .type-whatsapp     { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        #pltraceApp .type-email        { background: #ede9fe; color: #6d28d9; border-color: #ddd6fe; }
        #pltraceApp .type-reunion      { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        #pltraceApp .type-nota         { background: #e2e8f0; color: #334155; border-color: #cbd5e1; }
        #pltraceApp .type-sinrespuesta { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }
        #pltraceApp .type-default      { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
        #pltraceApp .outcome-positivo    { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        #pltraceApp .outcome-neutral     { background: #f1f5f9; color: #334155; border-color: #e2e8f0; }
        #pltraceApp .outcome-negativo    { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
        #pltraceApp .outcome-cerrar      { background: #ffedd5; color: #c2410c; border-color: #fed7aa; }
        #pltraceApp .outcome-seguimiento { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        #pltraceApp .outcome-sinrespuesta{ background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }
        #pltraceApp .outcome-default     { background: #f8fafc; color: #475569; border-color: #e2e8f0; }

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

        #pltraceApp .pltrace-detail-identity {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1;
            cursor: pointer;
            border-radius: 10px;
            padding: 4px 6px;
            margin: -4px -6px;
            transition: background 0.15s;
        }

        #pltraceApp .pltrace-detail-identity:hover {
            background: var(--pltrace-bg);
        }

        #pltraceApp .pltrace-detail-identity.pltrace-detail-identity--disabled {
            cursor: default;
            pointer-events: none;
        }

        #pltraceApp .pltrace-detail-identity.pltrace-detail-identity--disabled:hover {
            background: transparent;
        }

        #pltraceApp .pltrace-detail-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--pltrace-gold-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7a5c00;
            flex-shrink: 0;
            font-size: 1rem;
        }

        #pltraceApp .pltrace-detail-avatar[hidden] {
            display: none;
        }

        #pltraceApp .pltrace-detail-text {
            min-width: 0;
        }

        #pltraceApp .pltrace-detail-head-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        #pltraceApp .pltrace-detail-vendedora {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.84rem;
            color: var(--pltrace-ink);
        }

        #pltraceApp .pltrace-detail-vendedora[hidden] {
            display: none;
        }

        #pltraceApp .pltrace-detail-vendedora-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.78rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        #pltraceApp .pltrace-detail-vendedora-name {
            font-weight: 600;
            white-space: nowrap;
        }

        #pltraceApp .pltrace-detail-vendedora-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 500;
            color: var(--pltrace-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
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

        #pltraceApp .pltrace-filter-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 12px 20px;
            border-bottom: 1px solid var(--pltrace-border);
            background: var(--pltrace-surface);
        }

        #pltraceApp .pltrace-filter-tabs[hidden] {
            display: none;
        }

        #pltraceApp .pltrace-filter-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--pltrace-border);
            background: var(--pltrace-bg);
            color: var(--pltrace-muted);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s, box-shadow 0.15s;
        }

        #pltraceApp .pltrace-filter-tab:hover {
            border-color: #d4cfc4;
            color: var(--pltrace-ink);
        }

        #pltraceApp .pltrace-filter-tab.pltrace-filter-tab--active {
            background: var(--pltrace-gold-soft);
            border-color: var(--pltrace-gold);
            color: #5c4600;
            box-shadow: 0 0 0 1px rgba(197, 160, 40, 0.2);
        }

        #pltraceApp .pltrace-filter-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.08);
            font-size: 0.68rem;
            font-weight: 700;
            line-height: 1;
        }

        #pltraceApp .pltrace-filter-tab.pltrace-filter-tab--active .pltrace-filter-count {
            background: rgba(197, 160, 40, 0.25);
            color: #5c4600;
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

        #pltraceApp .pltrace-unread-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: #dc2626;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            line-height: 18px;
            text-align: center;
            box-shadow: 0 1px 4px rgba(220, 38, 38, 0.35);
        }

        #pltraceApp .pltrace-unread-badge[hidden] {
            display: none;
        }

        #pltraceApp .pltrace-composer {
            border-top: 1px solid var(--pltrace-border);
            background: var(--pltrace-surface);
            padding: 12px 16px;
            display: flex;
            gap: 10px;
            align-items: center;
            position: relative;
        }

        #pltraceApp .pltrace-composer-actions {
            position: relative;
            flex-shrink: 0;
        }

        #pltraceApp .pltrace-composer-plus {
            width: 42px;
            height: 42px;
            border: 1px solid var(--pltrace-border);
            border-radius: 10px;
            background: #fff;
            color: var(--pltrace-ink);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: background 0.15s, border-color 0.15s;
        }

        #pltraceApp .pltrace-composer-plus:hover:not(:disabled) {
            background: var(--pltrace-bg);
            border-color: var(--pltrace-gold);
        }

        #pltraceApp .pltrace-composer-plus:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        #pltraceApp .pltrace-chat-input {
            flex: 1;
            min-width: 0;
            border: 1px solid var(--pltrace-border);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.88rem;
            color: var(--pltrace-ink);
            background: #fff;
        }

        #pltraceApp .pltrace-chat-input:focus {
            outline: none;
            border-color: var(--pltrace-gold);
            box-shadow: 0 0 0 2px rgba(197, 160, 40, 0.15);
        }

        #pltraceApp .pltrace-chat-input:disabled {
            background: var(--pltrace-bg);
            color: var(--pltrace-muted);
        }

        #pltraceApp .pltrace-chat-send {
            width: 42px;
            height: 42px;
            border: none;
            border-radius: 10px;
            background: var(--pltrace-gold);
            color: #1a1a1a;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: background 0.15s, transform 0.1s;
        }

        #pltraceApp .pltrace-chat-send:hover:not(:disabled) {
            background: #b89224;
        }

        #pltraceApp .pltrace-chat-send:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        #pltraceApp .pltrace-chat-row {
            max-width: 720px;
            margin: 0 auto 10px;
            display: flex;
            width: 100%;
        }

        #pltraceApp .pltrace-chat-row--in {
            justify-content: flex-start;
        }

        #pltraceApp .pltrace-chat-row--out {
            justify-content: flex-end;
        }

        #pltraceApp .pltrace-chat-bubble {
            max-width: 78%;
            padding: 10px 14px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            line-height: 1.4;
        }

        #pltraceApp .pltrace-chat-bubble--in {
            background: #fff;
            border: 1px solid var(--pltrace-border);
            border-top-left-radius: 4px;
        }

        #pltraceApp .pltrace-chat-bubble--out {
            background: linear-gradient(135deg, #f5edd6 0%, #ede0b8 100%);
            border: 1px solid rgba(197, 160, 40, 0.35);
            border-top-right-radius: 4px;
        }

        #pltraceApp .pltrace-chat-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }

        #pltraceApp .pltrace-chat-author {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--pltrace-ink);
        }

        #pltraceApp .pltrace-chat-role {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 2px 7px;
            border-radius: 999px;
            background: var(--pltrace-gold-soft);
            color: #7a5c00;
        }

        #pltraceApp .pltrace-chat-text {
            font-size: 0.88rem;
            color: #334155;
            white-space: pre-wrap;
            word-break: break-word;
        }

        #pltraceApp .pltrace-chat-time {
            margin-top: 6px;
            font-size: 0.7rem;
            color: var(--pltrace-muted);
            text-align: right;
        }

        @keyframes pltraceMsgIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #pltraceApp .pltrace-msg-animate {
            animation: pltraceMsgIn 0.28s ease-out;
        }

        .pltrace-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 12000;
            background: rgba(15, 23, 42, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .pltrace-modal-overlay[hidden] {
            display: none;
        }

        .pltrace-modal {
            width: min(520px, 100%);
            max-height: calc(100vh - 40px);
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .pltrace-modal--lg {
            width: min(760px, 100%);
            max-height: calc(100vh - 32px);
        }

        .pltrace-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--pltrace-border);
        }

        .pltrace-modal-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: var(--pltrace-ink);
        }

        .pltrace-modal-close {
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 8px;
            background: transparent;
            color: var(--pltrace-muted);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .pltrace-modal-close:hover {
            background: var(--pltrace-bg);
            color: var(--pltrace-ink);
        }

        .pltrace-modal-body {
            padding: 14px 18px 18px;
            overflow: auto;
        }

        .pltrace-modal-body--flush {
            padding: 0;
        }

        #pltraceApp .pltrace-action-popover {
            position: absolute;
            bottom: calc(100% + 10px);
            left: 0;
            z-index: 50;
            min-width: 240px;
            padding: 8px 0;
            background: #233138;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(15, 23, 42, 0.35);
            transform-origin: bottom left;
        }

        #pltraceApp .pltrace-action-popover[hidden] {
            display: none;
        }

        #pltraceApp .pltrace-action-popover:not([hidden]) {
            animation: pltracePopoverIn 0.18s ease-out;
        }

        @keyframes pltracePopoverIn {
            from {
                opacity: 0;
                transform: translateY(8px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        #pltraceApp .pltrace-action-option {
            width: 100%;
            border: none;
            border-radius: 0;
            background: transparent;
            color: #e9edef;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 0.92rem;
            font-weight: 500;
            cursor: pointer;
            text-align: left;
            transition: background 0.12s;
        }

        #pltraceApp .pltrace-action-option:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        #pltraceApp .pltrace-action-option-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.95rem;
            color: #fff;
        }

        #pltraceApp .pltrace-action-option-icon--interaction {
            background: #0ea5e9;
        }

        #pltraceApp .pltrace-action-option-icon--upload {
            background: #8b5cf6;
        }

        #pltraceApp .pltrace-composer-upload-indicator {
            position: absolute;
            left: 16px;
            right: 16px;
            bottom: calc(100% + 8px);
            z-index: 40;
            background: #fff;
            border: 1px solid var(--pltrace-border);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
            padding: 12px 14px;
            animation: pltraceMsgIn 0.22s ease-out;
        }

        #pltraceApp .pltrace-composer-upload-indicator[hidden] {
            display: none;
        }

        #pltraceApp .pltrace-upload-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 0.82rem;
            color: var(--pltrace-ink);
        }

        #pltraceApp .pltrace-upload-head i {
            color: #8b5cf6;
        }

        #pltraceApp .pltrace-upload-name {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 600;
        }

        #pltraceApp .pltrace-upload-progress {
            height: 6px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        #pltraceApp .pltrace-upload-progress-bar {
            height: 100%;
            width: 0;
            border-radius: 999px;
            background: linear-gradient(90deg, #8b5cf6, #c5a028);
            transition: width 0.15s ease;
        }

        #pltraceApp .pltrace-chat-bubble--file {
            max-width: min(88%, 420px);
        }

        #pltraceApp .pltrace-chat-file-meta {
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        #pltraceApp .pltrace-chat-file-meta-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        #pltraceApp .pltrace-chat-file-name {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--pltrace-ink);
            word-break: break-word;
        }

        #pltraceApp .pltrace-chat-file-type {
            font-size: 0.72rem;
            color: var(--pltrace-muted);
        }

        #pltraceApp .pltrace-chat-image-btn {
            display: block;
            width: 100%;
            padding: 0;
            border: none;
            background: transparent;
            border-radius: 10px;
            overflow: hidden;
            cursor: zoom-in;
        }

        #pltraceApp .pltrace-chat-image-btn img {
            display: block;
            width: 100%;
            max-height: 280px;
            object-fit: cover;
            border-radius: 10px;
        }

        #pltraceApp .pltrace-chat-file--video video {
            display: block;
            width: 100%;
            max-height: 280px;
            border-radius: 10px;
            background: #0f172a;
        }

        #pltraceApp .pltrace-chat-pdf-card,
        #pltraceApp .pltrace-chat-doc-card {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 12px;
            border: 1px solid var(--pltrace-border);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.72);
            text-align: left;
        }

        #pltraceApp .pltrace-chat-pdf-card {
            flex: 1;
            min-width: 0;
            border: none;
            background: transparent;
            padding: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
        }

        #pltraceApp .pltrace-chat-pdf-card:hover,
        #pltraceApp .pltrace-chat-doc-card:hover {
            border-color: rgba(197, 160, 40, 0.45);
        }

        #pltraceApp .pltrace-chat-file-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.15rem;
            color: #fff;
        }

        #pltraceApp .pltrace-chat-file-icon--pdf { background: #ef4444; }
        #pltraceApp .pltrace-chat-file-icon--document { background: #2563eb; }
        #pltraceApp .pltrace-chat-file-icon--archive { background: #f59e0b; }
        #pltraceApp .pltrace-chat-file-icon--other { background: #64748b; }

        #pltraceApp .pltrace-chat-file-info {
            flex: 1;
            min-width: 0;
        }

        #pltraceApp .pltrace-chat-file-size {
            font-size: 0.72rem;
            color: var(--pltrace-muted);
        }

        #pltraceApp .pltrace-chat-download-btn {
            flex-shrink: 0;
            border: 1px solid var(--pltrace-border);
            background: #fff;
            color: var(--pltrace-ink);
            border-radius: 8px;
            padding: 7px 10px;
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        #pltraceApp .pltrace-chat-download-btn:hover {
            border-color: var(--pltrace-gold);
            background: var(--pltrace-gold-soft);
        }

        #pltraceApp .pltrace-chat-row--uploading .pltrace-chat-bubble {
            opacity: 0.75;
        }

        .pltrace-media-modal {
            position: relative;
            width: min(920px, calc(100vw - 32px));
            max-height: calc(100vh - 32px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pltrace-media-modal img {
            max-width: 100%;
            max-height: calc(100vh - 80px);
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.35);
        }

        .pltrace-media-modal-close {
            position: absolute;
            top: -12px;
            right: -12px;
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 999px;
            background: #fff;
            color: #334155;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.18);
        }

        .pltrace-modal-head-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pltrace-modal-download {
            border: 1px solid var(--pltrace-border);
            background: #fff;
            color: var(--pltrace-ink);
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .pltrace-modal-download:hover {
            border-color: var(--pltrace-gold);
            background: var(--pltrace-gold-soft);
        }

        .pltrace-modal--profile {
            --pf-bg: #ffffff;
            --pf-surface: #f8fafc;
            --pf-surface-hover: #f1f5f9;
            --pf-border: #e8e5df;
            --pf-text: #1e293b;
            --pf-muted: #64748b;
            --pf-accent: #c5a028;
            --pf-accent-soft: #f5edd6;
            --pf-blue: #3b82f6;
            --pf-blue-soft: #eff6ff;

            width: min(480px, calc(100vw - 24px));
            max-height: calc(100vh - 32px);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
            border: 1px solid var(--pf-border);
            background: #ffffff;
        }

        .pltrace-profile-card {
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 32px);
            overflow-x: hidden;
            overflow-y: auto;
            background: #ffffff;
            color: var(--pf-text);
        }

        .pltrace-profile-layout {
            position: relative;
            min-width: 0;
            background: #ffffff;
        }

        .pltrace-profile-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 8px;
            background: var(--pf-surface);
            color: var(--pf-muted);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            z-index: 3;
            transition: background 0.15s, color 0.15s;
        }

        .pltrace-profile-close:hover {
            background: var(--pf-surface-hover);
            color: var(--pf-text);
        }

        .pltrace-profile-header {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 20px 52px 16px 20px;
            background: var(--pf-bg);
            border-bottom: 1px solid var(--pf-border);
        }

        .pltrace-profile-header-main {
            flex: 1;
            min-width: 0;
        }

        .pltrace-profile-avatar {
            width: 56px;
            height: 56px;
            flex-shrink: 0;
            border-radius: 50%;
            border: 2px solid #fff;
            background: linear-gradient(145deg, #f5edd6, #e8d9a8);
            color: #7a5c00;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        }

        .pltrace-profile-name {
            margin: 0 0 4px;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--pf-text);
            letter-spacing: -0.01em;
            line-height: 1.25;
            word-break: break-word;
        }

        .pltrace-profile-handle {
            margin: 0 0 6px;
            font-size: 0.78rem;
            color: var(--pf-muted);
            word-break: break-all;
        }

        .pltrace-profile-subline {
            margin: 0 0 10px;
            font-size: 0.76rem;
            color: var(--pf-muted);
        }

        .pltrace-profile-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .pltrace-profile-tag {
            padding: 4px 10px;
            border-radius: 6px;
            background: var(--pf-surface);
            color: var(--pf-muted);
            font-size: 0.68rem;
            font-weight: 600;
            border: 1px solid var(--pf-border);
        }

        .pltrace-profile-tag--estatus {
            background: var(--pf-accent-soft);
            color: #7a5c00;
            border-color: #e8d9a8;
        }

        .pltrace-profile-body {
            padding: 0 20px 20px;
        }

        .pltrace-profile-section {
            margin-bottom: 20px;
        }

        .pltrace-profile-section:last-child {
            margin-bottom: 0;
        }

        .pltrace-profile-section-title {
            margin: 0 0 10px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--pf-muted);
        }

        .pltrace-profile-fields {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .pltrace-profile-field-label {
            display: block;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--pf-muted);
            margin-bottom: 3px;
        }

        .pltrace-profile-field-value {
            display: block;
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--pf-text);
            line-height: 1.4;
            word-break: break-word;
        }

        .pltrace-profile-visit {
            display: flex;
            gap: 0;
            background: var(--pf-surface);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 14px;
            border: 1px solid var(--pf-border);
        }

        .pltrace-profile-visit-accent {
            width: 4px;
            flex-shrink: 0;
            background: var(--pf-blue);
        }

        .pltrace-profile-visit-body {
            flex: 1;
            padding: 12px 14px;
            min-width: 0;
        }

        .pltrace-profile-visit-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 4px;
        }

        .pltrace-profile-visit-head span:first-child {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--pf-muted);
        }

        .pltrace-profile-visit-badge {
            padding: 2px 8px;
            border-radius: 4px;
            background: var(--pf-blue-soft);
            color: #1d4ed8;
            font-size: 0.65rem;
            font-weight: 700;
        }

        .pltrace-profile-visit-date {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--pf-text);
            margin-bottom: 2px;
        }

        .pltrace-profile-visit-sub {
            font-size: 0.76rem;
            color: var(--pf-muted);
        }

        .pltrace-profile-agent {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            background: var(--pf-surface);
            border-radius: 10px;
            border: 1px solid var(--pf-border);
            margin-bottom: 14px;
        }

        .pltrace-profile-agent-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.82rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .pltrace-profile-agent-label {
            display: block;
            font-size: 0.62rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--pf-muted);
            margin-bottom: 2px;
        }

        .pltrace-profile-agent-name {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--pf-text);
        }

        .pltrace-profile-note {
            padding: 12px 14px;
            border-radius: 10px;
            background: var(--pf-accent-soft);
            border: 1px solid #e8d9a8;
            border-left: 3px solid var(--pf-accent);
        }

        .pltrace-profile-note-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--pf-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .pltrace-profile-note-text {
            font-size: 0.84rem;
            line-height: 1.55;
            color: var(--pf-text);
            white-space: pre-wrap;
            word-break: break-word;
        }

        .pltrace-profile-loading,
        .pltrace-profile-error {
            padding: 48px 24px;
            text-align: center;
            color: var(--pf-muted);
            font-size: 0.9rem;
            background: var(--pf-bg);
        }

        .pltrace-profile-loading i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--pf-accent);
        }

        @media (max-width: 520px) {
            .pltrace-modal--profile {
                width: calc(100vw - 16px);
            }
        }

        .pltrace-pdf-frame {
            width: 100%;
            height: min(78vh, 820px);
            border: 0;
            display: block;
            background: #f8fafc;
        }

        .pltrace-interaction-frame {
            width: 100%;
            height: min(72vh, 720px);
            border: 0;
            display: block;
            background: #f8fafc;
        }

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
            <?php
            $pltraceListSub = count($traceLeads) . ' registros';
            $pltraceFilteredTotal = ($filterTipo !== '' ? $traceLeadCountBeforeEstatus : $traceLeadTotalCount);
            if (($filterTipo !== '' || $filterEstatus !== '') && count($traceLeads) !== $pltraceFilteredTotal) {
                $pltraceListSub .= ' · ' . $pltraceFilteredTotal . ' en total';
            }
            ?>
            <p class="pltrace-list-sub">Leads pre-calificación · <?php echo htmlspecialchars($pltraceListSub, ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="pltrace-lead-type-tabs">
                <a href="<?php echo htmlspecialchars($pltraceBuildTipoFilterUrl(''), ENT_QUOTES, 'UTF-8'); ?>"
                   class="pltrace-lead-type-tab<?php echo $filterTipo === '' ? ' pltrace-lead-type-tab--active' : ''; ?>">
                    Todos <span class="pltrace-lead-type-count"><?php echo (int) $traceLeadTotalCount; ?></span>
                </a>
                <a href="<?php echo htmlspecialchars($pltraceBuildTipoFilterUrl('wp'), ENT_QUOTES, 'UTF-8'); ?>"
                   class="pltrace-lead-type-tab<?php echo $filterTipo === 'wp' ? ' pltrace-lead-type-tab--active' : ''; ?>">
                    WP <span class="pltrace-lead-type-count"><?php echo (int) $traceLeadWpCount; ?></span>
                </a>
                <a href="<?php echo htmlspecialchars($pltraceBuildTipoFilterUrl('leads'), ENT_QUOTES, 'UTF-8'); ?>"
                   class="pltrace-lead-type-tab<?php echo $filterTipo === 'leads' ? ' pltrace-lead-type-tab--active' : ''; ?>">
                    Leads <span class="pltrace-lead-type-count"><?php echo (int) $traceLeadOnlyCount; ?></span>
                </a>
                <a href="<?php echo htmlspecialchars($pltraceBuildTipoFilterUrl('clientes'), ENT_QUOTES, 'UTF-8'); ?>"
                   class="pltrace-lead-type-tab<?php echo $filterTipo === 'clientes' ? ' pltrace-lead-type-tab--active' : ''; ?>">
                    Clientes <span class="pltrace-lead-type-count"><?php echo (int) $traceLeadClientCount; ?></span>
                </a>
            </div>
            <div class="pltrace-search-box">
                <i class="fas fa-search"></i>
                <input type="search" id="pltraceSearchInput" class="pltrace-search-input" placeholder="Buscar lead...">
            </div>
        </div>

        <form method="get" class="pltrace-filter-row">
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>">
            <select name="filter_estatus" aria-label="Filtrar por estatus">
                <option value=""<?php echo $filterEstatus === '' ? ' selected' : ''; ?>>Todos los estatus</option>
                <option value="lead"<?php echo $filterEstatus === 'lead' ? ' selected' : ''; ?>>Lead</option>
                <option value="agendado"<?php echo $filterEstatus === 'agendado' ? ' selected' : ''; ?>>Agendado</option>
                <option value="atendido"<?php echo $filterEstatus === 'atendido' ? ' selected' : ''; ?>>Atendido</option>
                <option value="cliente"<?php echo $filterEstatus === 'cliente' ? ' selected' : ''; ?>>Cliente</option>
                <option value="fantasma"<?php echo $filterEstatus === 'fantasma' ? ' selected' : ''; ?>>Fantasma</option>
                <option value="muerto"<?php echo $filterEstatus === 'muerto' ? ' selected' : ''; ?>>Muerto</option>
            </select>
            <?php if ($filterTipo !== ''): ?>
                <input type="hidden" name="filter_tipo" value="<?php echo htmlspecialchars($filterTipo, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
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
                    $pendingBadge = $pltracePendingBadgesMap[$traceKey] ?? null;
                    $isWeddingPlanner = !empty($lead['is_wedding_planner']);
                    $vendedoraNombre = trim((string) ($lead['vendedora'] ?? ''));
                    ?>
                    <button type="button"
                        class="pltrace-lead-card<?php echo $traceKey === $selectedKey ? ' pltrace-selected' : ''; ?>"
                        data-pltrace-key="<?php echo htmlspecialchars($traceKey, ENT_QUOTES, 'UTF-8'); ?>"
                        data-pltrace-tabla="<?php echo htmlspecialchars($tablaOrigen, ENT_QUOTES, 'UTF-8'); ?>"
                        data-pltrace-orig-id="<?php echo (int) $origId; ?>"
                        data-pltrace-cf-id="<?php echo (int) $cfId; ?>"
                        data-pltrace-name="<?php echo htmlspecialchars($lead['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-pltrace-estatus="<?php echo htmlspecialchars($lead['estatus'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-pltrace-vendedora="<?php echo htmlspecialchars($vendedoraNombre, ENT_QUOTES, 'UTF-8'); ?>"
                        data-pltrace-is-wp="<?php echo $isWeddingPlanner ? '1' : '0'; ?>"
                        data-pltrace-search="<?php echo htmlspecialchars(mb_strtolower(($lead['nombre'] ?? '') . ' ' . ($lead['email'] ?? '') . ' ' . ($lead['telefono'] ?? '') . ' ' . $vendedoraNombre, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="pltrace-unread-badge" hidden aria-label="Mensajes no leídos"></span>
                        <div class="pltrace-lead-avatar"><i class="fas fa-user"></i></div>
                        <div class="pltrace-lead-body">
                            <div class="pltrace-lead-name"><?php echo htmlspecialchars($lead['nombre'] ?? 'Sin nombre', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="pltrace-lead-email"><?php echo htmlspecialchars($lead['email'] ?: ($lead['telefono'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <span class="pltrace-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($lead['estatus'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($isWeddingPlanner): ?>
                                <span class="pltrace-badge pltrace-badge--wp" title="Wedding Planner">WP</span>
                            <?php endif; ?>
                            <?php if (is_array($pendingBadge)): ?>
                                <div class="pltrace-lead-action-top">
                                    <?php if (($pendingBadge['source'] ?? '') === 'interaccion'): ?>
                                        <?php
                                        $evtType = trim((string) ($pendingBadge['interaction_type'] ?? ''));
                                        $evtOutcome = trim((string) ($pendingBadge['outcome'] ?? ''));
                                        ?>
                                        <?php if ($evtType !== ''): ?>
                                            <span class="interaction-type-badge <?php echo pltraceTypeClass($evtType); ?>">
                                                <span><?php echo htmlspecialchars(pltraceTypeIcon($evtType), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span><?php echo htmlspecialchars($evtType, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($evtOutcome !== ''): ?>
                                            <span class="outcome-badge <?php echo pltraceOutcomeClass($evtOutcome); ?>">
                                                <span><?php echo htmlspecialchars(pltraceOutcomeIcon($evtOutcome), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span><?php echo htmlspecialchars($evtOutcome, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="interaction-type-badge type-default">📅 Cita</span>
                                    <?php endif; ?>
                                    <span class="pending-chip<?php echo !empty($pendingBadge['is_overdue']) ? ' pending-chip--overdue' : ''; ?>">
                                        <?php echo htmlspecialchars((string) ($pendingBadge['due_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <section class="pltrace-detail-panel">
        <header class="pltrace-detail-head">
            <div class="pltrace-detail-identity<?php echo $initialDetailHasLead ? '' : ' pltrace-detail-identity--disabled'; ?>"
                id="pltraceDetailIdentity"
                role="button"
                tabindex="<?php echo $initialDetailHasLead ? '0' : '-1'; ?>"
                aria-label="Ver información del lead"
                title="<?php echo $initialDetailHasLead ? 'Ver información del lead' : ''; ?>">
                <span class="pltrace-detail-avatar" id="pltraceDetailAvatar"<?php echo $initialDetailHasLead ? '' : ' hidden'; ?> aria-hidden="true">
                    <i class="fas fa-user"></i>
                </span>
                <div class="pltrace-detail-text">
                    <h2 class="pltrace-detail-title" id="pltraceDetailTitle"><?php echo htmlspecialchars($initialDetailName, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="pltrace-detail-sub" id="pltraceDetailSub">
                        <?php if ($initialDetailHasLead && $initialDetailEstatus !== ''): ?>
                            Estatus actual: <?php echo htmlspecialchars($initialDetailEstatus, ENT_QUOTES, 'UTF-8'); ?>
                        <?php else: ?>
                            Historial completo de estatus y fechas
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="pltrace-detail-head-right">
                <div class="pltrace-detail-vendedora" id="pltraceDetailVendedora"<?php echo $initialDetailVendedora === '' ? ' hidden' : ''; ?>>
                    <span class="pltrace-detail-vendedora-circle" id="pltraceDetailVendedoraCircle" style="background-color: <?php echo htmlspecialchars($initialDetailVendedoraColor, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($initialDetailVendedoraInitial, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <span>
                        <span class="pltrace-detail-vendedora-label">Vendedora</span>
                        <span class="pltrace-detail-vendedora-name" id="pltraceDetailVendedoraName"><?php echo htmlspecialchars($initialDetailVendedora, ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                </div>
                <div class="pltrace-period-chip"><i class="far fa-calendar"></i> <?php echo htmlspecialchars($reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </header>

        <div class="pltrace-filter-tabs" id="pltraceFilterTabs" hidden>
            <button type="button" class="pltrace-filter-tab pltrace-filter-tab--active" data-filter="todos">
                Todos <span class="pltrace-filter-count" data-count-for="todos">0</span>
            </button>
            <button type="button" class="pltrace-filter-tab" data-filter="sistema">
                Sistema <span class="pltrace-filter-count" data-count-for="sistema">0</span>
            </button>
            <button type="button" class="pltrace-filter-tab" data-filter="interacciones">
                Interacciones <span class="pltrace-filter-count" data-count-for="interacciones">0</span>
            </button>
            <button type="button" class="pltrace-filter-tab" data-filter="chat">
                Chat <span class="pltrace-filter-count" data-count-for="chat">0</span>
            </button>
        </div>

        <div class="pltrace-timeline-scroll" id="pltraceTimelineScroll">
            <div class="pltrace-timeline-empty" id="pltraceTimelineEmpty">Selecciona un lead de la lista para ver su trazabilidad.</div>
        </div>

        <div class="pltrace-composer" id="pltraceComposer">
            <div class="pltrace-composer-actions">
                <button type="button" id="pltraceComposerPlus" class="pltrace-composer-plus" disabled title="Más acciones" aria-haspopup="menu" aria-expanded="false" aria-controls="pltraceActionPopover">
                    <i class="fas fa-plus"></i>
                </button>
                <div class="pltrace-action-popover" id="pltraceActionPopover" hidden role="menu" aria-label="Acciones">
                    <button type="button" class="pltrace-action-option" id="pltraceActionRegisterInteraction" role="menuitem">
                        <span class="pltrace-action-option-icon pltrace-action-option-icon--interaction" aria-hidden="true">
                            <i class="fas fa-comments"></i>
                        </span>
                        <span>Registrar interacción</span>
                    </button>
                    <button type="button" class="pltrace-action-option" id="pltraceActionUploadFile" role="menuitem">
                        <span class="pltrace-action-option-icon pltrace-action-option-icon--upload" aria-hidden="true">
                            <i class="fas fa-paperclip"></i>
                        </span>
                        <span>Cargar archivo</span>
                    </button>
                </div>
                <input type="file" id="pltraceFileInput" hidden>
            </div>
            <div class="pltrace-composer-upload-indicator" id="pltraceUploadIndicator" hidden aria-live="polite">
                <div class="pltrace-upload-head">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span class="pltrace-upload-name" id="pltraceUploadName">Subiendo archivo...</span>
                    <span id="pltraceUploadPercent">0%</span>
                </div>
                <div class="pltrace-upload-progress">
                    <div class="pltrace-upload-progress-bar" id="pltraceUploadProgressBar"></div>
                </div>
            </div>
            <input type="text" id="pltraceChatInput" class="pltrace-chat-input" placeholder="Selecciona un lead para enviar un mensaje al equipo..." maxlength="5000" disabled>
            <button type="button" id="pltraceChatSend" class="pltrace-chat-send" disabled title="Enviar mensaje">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </section>
</div>
</div>

<div class="pltrace-modal-overlay" id="pltraceProfileModal" hidden aria-hidden="true">
    <div class="pltrace-modal pltrace-modal--profile" role="dialog" aria-modal="true" aria-labelledby="pltraceProfileModalName">
        <div class="pltrace-profile-card" id="pltraceProfileModalBody">
            <div class="pltrace-profile-loading"><i class="fas fa-circle-notch fa-spin"></i>Selecciona un lead para ver su información.</div>
        </div>
    </div>
</div>

<div class="pltrace-modal-overlay" id="pltraceInteractionModal" hidden aria-hidden="true">
    <div class="pltrace-modal pltrace-modal--lg" role="dialog" aria-modal="true" aria-labelledby="pltraceInteractionModalTitle">
        <header class="pltrace-modal-head">
            <h3 class="pltrace-modal-title" id="pltraceInteractionModalTitle">Registrar interacción</h3>
            <button type="button" class="pltrace-modal-close" id="pltraceInteractionModalClose" title="Cerrar" aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </header>
        <div class="pltrace-modal-body pltrace-modal-body--flush">
            <iframe id="pltraceInteractionFrame" class="pltrace-interaction-frame" title="Formulario de interacción"></iframe>
        </div>
    </div>
</div>

<div class="pltrace-modal-overlay" id="pltraceImageModal" hidden aria-hidden="true">
    <div class="pltrace-media-modal" role="dialog" aria-modal="true" aria-label="Vista ampliada">
        <button type="button" class="pltrace-media-modal-close" id="pltraceImageModalClose" title="Cerrar" aria-label="Cerrar">
            <i class="fas fa-times"></i>
        </button>
        <img id="pltraceImageModalImg" src="" alt="Imagen ampliada">
    </div>
</div>

<div class="pltrace-modal-overlay" id="pltracePdfModal" hidden aria-hidden="true">
    <div class="pltrace-modal pltrace-modal--lg" role="dialog" aria-modal="true" aria-labelledby="pltracePdfModalTitle">
        <header class="pltrace-modal-head">
            <h3 class="pltrace-modal-title" id="pltracePdfModalTitle">Documento PDF</h3>
            <div class="pltrace-modal-head-actions">
                <a href="#" id="pltracePdfModalDownload" class="pltrace-modal-download" download>
                    <i class="fas fa-download"></i> Descargar
                </a>
                <button type="button" class="pltrace-modal-close" id="pltracePdfModalClose" title="Cerrar" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </header>
        <div class="pltrace-modal-body pltrace-modal-body--flush">
            <iframe id="pltracePdfFrame" class="pltrace-pdf-frame" title="Visor PDF"></iframe>
        </div>
    </div>
</div>

<script>
(function () {
    const INITIAL_TRACE_KEY = <?php echo json_encode($selectedKey, JSON_UNESCAPED_UNICODE); ?>;
    const INITIAL_TABLA = <?php echo json_encode($selectedTabla, JSON_UNESCAPED_UNICODE); ?>;
    const INITIAL_ORIG_ID = <?php echo (int) $selectedOrigId; ?>;
    const INITIAL_CF_ID = <?php echo (int) $selectedCfId; ?>;
    const INITIAL_DETAIL_NAME = <?php echo json_encode($initialDetailName, JSON_UNESCAPED_UNICODE); ?>;
    const INITIAL_DETAIL_ESTATUS = <?php echo json_encode($initialDetailEstatus, JSON_UNESCAPED_UNICODE); ?>;
    const INITIAL_DETAIL_VENDEDORA = <?php echo json_encode($initialDetailVendedora, JSON_UNESCAPED_UNICODE); ?>;
    const INITIAL_DETAIL_HAS_LEAD = <?php echo $initialDetailHasLead ? 'true' : 'false'; ?>;
    const CURRENT_USER_ID = <?php echo (int) ($_SESSION['uid'] ?? 0); ?>;
    const PLTRACE_CHAT_API = 'post_lead_tracer.php';
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
    const avatarEl = document.getElementById('pltraceDetailAvatar');
    const vendedoraEl = document.getElementById('pltraceDetailVendedora');
    const vendedoraCircleEl = document.getElementById('pltraceDetailVendedoraCircle');
    const vendedoraNameEl = document.getElementById('pltraceDetailVendedoraName');
    const searchEl = document.getElementById('pltraceSearchInput');
    const chatInputEl = document.getElementById('pltraceChatInput');
    const chatSendEl = document.getElementById('pltraceChatSend');
    const composerPlusEl = document.getElementById('pltraceComposerPlus');
    const actionPopoverEl = document.getElementById('pltraceActionPopover');
    const actionRegisterEl = document.getElementById('pltraceActionRegisterInteraction');
    const actionUploadEl = document.getElementById('pltraceActionUploadFile');
    const fileInputEl = document.getElementById('pltraceFileInput');
    const uploadIndicatorEl = document.getElementById('pltraceUploadIndicator');
    const uploadNameEl = document.getElementById('pltraceUploadName');
    const uploadPercentEl = document.getElementById('pltraceUploadPercent');
    const uploadProgressBarEl = document.getElementById('pltraceUploadProgressBar');
    const imageModalEl = document.getElementById('pltraceImageModal');
    const imageModalImgEl = document.getElementById('pltraceImageModalImg');
    const imageModalCloseEl = document.getElementById('pltraceImageModalClose');
    const pdfModalEl = document.getElementById('pltracePdfModal');
    const pdfModalTitleEl = document.getElementById('pltracePdfModalTitle');
    const pdfModalDownloadEl = document.getElementById('pltracePdfModalDownload');
    const pdfModalCloseEl = document.getElementById('pltracePdfModalClose');
    const pdfFrameEl = document.getElementById('pltracePdfFrame');
    const interactionModalEl = document.getElementById('pltraceInteractionModal');
    const interactionModalCloseEl = document.getElementById('pltraceInteractionModalClose');
    const interactionFrameEl = document.getElementById('pltraceInteractionFrame');
    const profileModalEl = document.getElementById('pltraceProfileModal');
    const profileModalBodyEl = document.getElementById('pltraceProfileModalBody');
    const detailIdentityEl = document.getElementById('pltraceDetailIdentity');
    const filterTabsEl = document.getElementById('pltraceFilterTabs');

    let loadToken = 0;
    let allEvents = [];
    let activeTimelineFilter = 'todos';
    let currentLead = { tabla: '', origId: 0, cfId: 0, traceKey: '' };
    let lastMessageId = 0;
    let chatPollTimer = null;
    let badgePollTimer = null;
    let markReadTimer = null;
    let isSending = false;
    let isUploading = false;
    let timelineFingerprint = '';

    function vendedorInitialFromName(name) {
        const trimmed = String(name || '').trim();
        if (!trimmed) return 'S';
        return trimmed.charAt(0).toUpperCase();
    }

    function vendedorColorFromName(name) {
        const colors = {
            B: '#3B82F6',
            A: '#10B981',
            E: '#8B5CF6',
            L: '#F59E0B',
            M: '#EC4899',
            C: '#06B6D4',
            D: '#EF4444'
        };
        return colors[vendedorInitialFromName(name)] || '#64748B';
    }

    function updateDetailHeader(options) {
        const name = options && options.name ? String(options.name) : 'Selecciona un lead';
        const estatus = options && options.estatus ? String(options.estatus) : '';
        const vendedora = options && options.vendedora ? String(options.vendedora).trim() : '';
        const hasLead = !!(options && options.hasLead);

        if (titleEl) titleEl.textContent = name;
        if (subEl) {
            subEl.textContent = estatus
                ? ('Estatus actual: ' + estatus)
                : (hasLead ? 'Historial completo de estatus, interacciones y mensajes' : 'Historial completo de estatus y fechas');
        }
        if (avatarEl) avatarEl.hidden = !hasLead;
        if (vendedoraEl) {
            if (vendedora) {
                vendedoraEl.hidden = false;
                if (vendedoraCircleEl) {
                    vendedoraCircleEl.textContent = vendedorInitialFromName(vendedora);
                    vendedoraCircleEl.style.backgroundColor = vendedorColorFromName(vendedora);
                }
                if (vendedoraNameEl) vendedoraNameEl.textContent = vendedora;
            } else {
                vendedoraEl.hidden = true;
            }
        }
        if (detailIdentityEl) {
            const canOpenProfile = hasLead && (
                (currentLead.tabla && currentLead.origId > 0) || currentLead.cfId > 0
            );
            detailIdentityEl.classList.toggle('pltrace-detail-identity--disabled', !canOpenProfile);
            detailIdentityEl.tabIndex = canOpenProfile ? 0 : -1;
            detailIdentityEl.title = canOpenProfile ? 'Ver información del lead' : '';
        }
    }

    function buildProfileUrl(tabla, origId, cfId) {
        const params = new URLSearchParams();
        params.set('action', 'lead_profile');
        if (cfId > 0) {
            params.set('cf_id', String(cfId));
        }
        if (tabla) {
            params.set('tabla', tabla);
        }
        if (origId > 0) {
            params.set('orig_id', String(origId));
        }
        return PLTRACE_CHAT_API + '?' + params.toString();
    }

    function isProfileEmptyValue(value) {
        const v = String(value || '').trim();
        return v === '' || v === '—';
    }

    function buildProfileFieldHtml(field) {
        const value = field.value || '';
        if (isProfileEmptyValue(value)) return '';
        return '<div class="pltrace-profile-field">' +
            '<span class="pltrace-profile-field-label">' + escapeHtml(field.label || '') + '</span>' +
            '<span class="pltrace-profile-field-value">' + escapeHtml(value) + '</span>' +
        '</div>';
    }

    function collectProfileFields(sections, titles, skipLabels) {
        const fields = [];
        const skip = skipLabels || [];
        sections.forEach(function (section) {
            if (titles.indexOf(section.title) === -1) return;
            (section.fields || []).forEach(function (field) {
                if (field.multiline) return;
                if (skip.indexOf(field.label) !== -1) return;
                fields.push(field);
            });
        });
        return fields;
    }

    function findProfileField(fields, label) {
        for (let i = 0; i < fields.length; i++) {
            if (fields[i].label === label) return fields[i];
        }
        return null;
    }

    function renderProfileSection(title, fields, excludeLabels) {
        const exclude = excludeLabels || [];
        const visible = fields.filter(function (field) {
            return exclude.indexOf(field.label) === -1 && !isProfileEmptyValue(field.value);
        });
        if (!visible.length) return '';

        let html = '<section class="pltrace-profile-section">';
        html += '<h3 class="pltrace-profile-section-title">' + escapeHtml(title) + '</h3>';
        html += '<div class="pltrace-profile-fields">';
        visible.forEach(function (field) {
            html += buildProfileFieldHtml(field);
        });
        html += '</div></section>';
        return html;
    }

    function renderLeadProfile(profile) {
        if (!profileModalBodyEl) return;
        const sections = Array.isArray(profile.sections) ? profile.sections : [];
        if (!sections.length) {
            profileModalBodyEl.innerHTML = '<div class="pltrace-profile-error">No hay información disponible para este lead.</div>';
            return;
        }

        const hero = profile.hero || {};
        const nombre = hero.nombre || profile.nombre || 'Lead';
        const email = hero.email || '—';
        const city = hero.city || '—';
        const estatus = hero.estatus || '—';
        const vendedora = hero.vendedora || '—';
        const handle = !isProfileEmptyValue(email) ? email : ('Lead #' + (profile.orig_id || ''));

        const skipInGrid = ['Nombre completo', 'Correo electrónico'];
        const contactFields = collectProfileFields(sections, ['Datos de contacto'], skipInGrid);
        const originFields = collectProfileFields(sections, [
            'Tipo de cliente',
            'Primer contacto',
            'Conocimiento',
            'Fecha de registro'
        ]);
        const followFields = collectProfileFields(sections, ['Asignación y seguimiento']);

        const proximaSesion = findProfileField(followFields, 'Próxima sesión');
        const estatusActual = findProfileField(followFields, 'Estatus actual');
        const estatusSesion = findProfileField(followFields, 'Estatus de la sesión');
        const vendedoraField = findProfileField(followFields, 'Vendedora asignada');
        const vendedoraNombre = vendedoraField && !isProfileEmptyValue(vendedoraField.value)
            ? vendedoraField.value
            : (!isProfileEmptyValue(vendedora) ? vendedora : '');

        const followExclude = [
            'Próxima sesión',
            'Estatus actual',
            'Estatus de la sesión',
            'Vendedora asignada'
        ];

        let html = '<div class="pltrace-profile-layout">';
        html += '<button type="button" class="pltrace-profile-close" id="pltraceProfileInlineClose" title="Cerrar" aria-label="Cerrar"><i class="fas fa-times"></i></button>';
        html += '<header class="pltrace-profile-header">';
        html += '<div class="pltrace-profile-avatar" aria-hidden="true"><i class="fas fa-user"></i></div>';
        html += '<div class="pltrace-profile-header-main">';
        html += '<h2 class="pltrace-profile-name" id="pltraceProfileModalName">' + escapeHtml(nombre) + '</h2>';
        html += '<p class="pltrace-profile-handle">' + escapeHtml(handle) + '</p>';
        if (!isProfileEmptyValue(city)) {
            html += '<p class="pltrace-profile-subline">' + escapeHtml(city) + '</p>';
        }
        const tags = Array.isArray(profile.tags) ? profile.tags : [];
        if (tags.length) {
            html += '<div class="pltrace-profile-tags">';
            tags.forEach(function (tag) {
                const tagClass = (tag === estatus)
                    ? 'pltrace-profile-tag pltrace-profile-tag--estatus'
                    : 'pltrace-profile-tag';
                html += '<span class="' + tagClass + '">' + escapeHtml(tag) + '</span>';
            });
            html += '</div>';
        }
        html += '</div></header>';

        html += '<div class="pltrace-profile-body">';
        html += renderProfileSection('Datos de contacto', contactFields);
        html += renderProfileSection('Origen y perfil', originFields);

        const hasFollowBlock = followFields.some(function (f) {
            return !isProfileEmptyValue(f.value);
        });
        if (hasFollowBlock) {
            html += '<section class="pltrace-profile-section">';
            html += '<h3 class="pltrace-profile-section-title">Seguimiento</h3>';

            const visitDate = proximaSesion && !isProfileEmptyValue(proximaSesion.value) ? proximaSesion.value : '';
            const visitBadge = estatusActual && !isProfileEmptyValue(estatusActual.value)
                ? estatusActual.value
                : (estatusSesion && !isProfileEmptyValue(estatusSesion.value) ? estatusSesion.value : estatus);
            if (visitDate || (!isProfileEmptyValue(visitBadge) && visitBadge !== '—')) {
                html += '<div class="pltrace-profile-visit">';
                html += '<div class="pltrace-profile-visit-accent"></div>';
                html += '<div class="pltrace-profile-visit-body">';
                html += '<div class="pltrace-profile-visit-head">';
                html += '<span>' + (visitDate ? 'Próxima sesión' : 'Estatus') + '</span>';
                if (!isProfileEmptyValue(visitBadge)) {
                    html += '<span class="pltrace-profile-visit-badge">' + escapeHtml(visitBadge) + '</span>';
                }
                html += '</div>';
                if (visitDate) {
                    html += '<div class="pltrace-profile-visit-date">' + escapeHtml(visitDate) + '</div>';
                }
                if (estatusSesion && !isProfileEmptyValue(estatusSesion.value)) {
                    html += '<div class="pltrace-profile-visit-sub">Sesión: ' + escapeHtml(estatusSesion.value) + '</div>';
                }
                html += '</div></div>';
            }

            if (vendedoraNombre) {
                const vInitial = vendedorInitialFromName(vendedoraNombre);
                const vColor = vendedorColorFromName(vendedoraNombre);
                html += '<div class="pltrace-profile-agent">';
                html += '<div class="pltrace-profile-agent-avatar" style="background-color:' + escapeAttr(vColor) + '">' + escapeHtml(vInitial) + '</div>';
                html += '<div><span class="pltrace-profile-agent-label">Vendedora asignada</span>';
                html += '<span class="pltrace-profile-agent-name">' + escapeHtml(vendedoraNombre) + '</span></div>';
                html += '</div>';
            }

            const followRest = followFields.filter(function (field) {
                return followExclude.indexOf(field.label) === -1 && !isProfileEmptyValue(field.value);
            });
            if (followRest.length) {
                html += '<div class="pltrace-profile-fields">';
                followRest.forEach(function (field) {
                    html += buildProfileFieldHtml(field);
                });
                html += '</div>';
            }
            html += '</section>';
        }

        const bio = String(profile.bio || '').trim();
        if (bio) {
            html += '<section class="pltrace-profile-section">';
            html += '<h3 class="pltrace-profile-section-title">Notas del cliente</h3>';
            html += '<div class="pltrace-profile-note">';
            html += '<div class="pltrace-profile-note-text">' + escapeHtml(bio) + '</div>';
            html += '</div></section>';
        }
        html += '</div></div>';

        profileModalBodyEl.innerHTML = html;
        const inlineClose = document.getElementById('pltraceProfileInlineClose');
        if (inlineClose) {
            inlineClose.addEventListener('click', closeLeadProfileModal);
        }
    }

    function closeLeadProfileModal() {
        closeModal(profileModalEl);
    }

    function openLeadProfileModal() {
        if ((!currentLead.tabla || currentLead.origId <= 0) && currentLead.cfId <= 0) return;
        if (profileModalBodyEl) {
            profileModalBodyEl.innerHTML = '<div class="pltrace-profile-loading"><i class="fas fa-circle-notch fa-spin"></i>Cargando información del lead...</div>';
        }
        openModal(profileModalEl);

        fetch(buildProfileUrl(currentLead.tabla, currentLead.origId, currentLead.cfId), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success || !data.profile) {
                    if (profileModalBodyEl) {
                        profileModalBodyEl.innerHTML = '<div class="pltrace-profile-error">' +
                            escapeHtml((data && (data.error || data.message)) || 'No se pudo cargar la información') +
                            '</div>';
                    }
                    return;
                }
                renderLeadProfile(data.profile);
            })
            .catch(function () {
                if (profileModalBodyEl) {
                    profileModalBodyEl.innerHTML = '<div class="pltrace-profile-error">Error de comunicación con el servidor.</div>';
                }
            });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/'/g, '&#39;');
    }

    function fileIconClass(categoria) {
        if (categoria === 'pdf') return 'pltrace-chat-file-icon--pdf';
        if (categoria === 'archive') return 'pltrace-chat-file-icon--archive';
        if (categoria === 'document') return 'pltrace-chat-file-icon--document';
        return 'pltrace-chat-file-icon--other';
    }

    function fileIconFa(categoria, extension) {
        if (categoria === 'pdf') return 'fa-file-pdf';
        if (categoria === 'archive') return 'fa-file-archive';
        if (categoria === 'video') return 'fa-file-video';
        if (categoria === 'image') return 'fa-file-image';
        const ext = String(extension || '').toLowerCase();
        if (['doc', 'docx', 'rtf'].indexOf(ext) !== -1) return 'fa-file-word';
        if (['xls', 'xlsx', 'csv'].indexOf(ext) !== -1) return 'fa-file-excel';
        if (ext === 'txt') return 'fa-file-alt';
        return 'fa-file';
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

    function getEventCategory(ev) {
        const tipo = ev.tipo || 'default';
        if (tipo === 'chat_mensaje') return 'chat';
        if (tipo === 'interaccion') return 'interacciones';
        return 'sistema';
    }

    function filterEventsForTab(events, tab) {
        if (tab === 'todos') return events.slice();
        return events.filter(function (ev) {
            return getEventCategory(ev) === tab;
        });
    }

    function computeCategoryCounts(events) {
        let sistema = 0;
        let interacciones = 0;
        let chat = 0;
        events.forEach(function (ev) {
            const cat = getEventCategory(ev);
            if (cat === 'sistema') sistema++;
            else if (cat === 'interacciones') interacciones++;
            else if (cat === 'chat') chat++;
        });
        return {
            todos: events.length,
            sistema: sistema,
            interacciones: interacciones,
            chat: chat
        };
    }

    function emptyMessageForFilter(tab) {
        if (tab === 'sistema') return 'No hay cambios de estatus del sistema para este lead.';
        if (tab === 'interacciones') return 'No hay interacciones registradas para este lead.';
        if (tab === 'chat') return 'No hay mensajes de chat para este lead.';
        return 'Sin eventos registrados para este lead.';
    }

    function updateFilterTabUI() {
        if (!filterTabsEl) return;
        const counts = computeCategoryCounts(allEvents);
        filterTabsEl.querySelectorAll('.pltrace-filter-tab').forEach(function (btn) {
            const filter = btn.dataset.filter || 'todos';
            btn.classList.toggle('pltrace-filter-tab--active', filter === activeTimelineFilter);
            const countEl = btn.querySelector('.pltrace-filter-count');
            if (countEl) {
                countEl.textContent = String(counts[filter] || 0);
            }
        });
    }

    function setTimelineFilter(tab) {
        activeTimelineFilter = tab || 'todos';
        refreshTimelineView({ preserveScroll: true });
    }

    function setFilterTabsVisible(visible) {
        if (filterTabsEl) filterTabsEl.hidden = !visible;
    }

    function refreshTimelineView(opts) {
        opts = opts || {};
        updateFilterTabUI();
        const filtered = filterEventsForTab(allEvents, activeTimelineFilter);
        renderTrace(filtered, opts);
    }

    function setAllEvents(events, opts) {
        allEvents = sortEvents(events || []);
        timelineFingerprint = eventFingerprint(allEvents);
        lastMessageId = 0;
        allEvents.forEach(function (ev) {
            if (ev.tipo === 'chat_mensaje' && ev.chat && ev.chat.message_id) {
                const mid = parseInt(ev.chat.message_id, 10) || 0;
                if (mid > lastMessageId) lastMessageId = mid;
            }
        });
        refreshTimelineView(opts);
    }

    function eventSortValue(ev) {
        const date = parseEventDate(ev.fecha);
        const ts = date ? date.getTime() : 0;
        const msgId = ev.chat && ev.chat.message_id ? parseInt(ev.chat.message_id, 10) : 0;
        return [ts, msgId];
    }

    function sortEvents(events) {
        return events.slice().sort(function (a, b) {
            const va = eventSortValue(a);
            const vb = eventSortValue(b);
            if (va[0] !== vb[0]) return va[0] - vb[0];
            return va[1] - vb[1];
        });
    }

    function eventFingerprint(events) {
        try {
            return JSON.stringify(events);
        } catch (e) {
            return String(events.length);
        }
    }

    function isNearBottom(el) {
        if (!el) return true;
        const remaining = el.scrollHeight - el.scrollTop - el.clientHeight;
        return remaining < 48;
    }

    function scrollTimelineToBottom() {
        if (chatEl) chatEl.scrollTop = chatEl.scrollHeight;
    }

    function setComposerEnabled(enabled) {
        if (chatInputEl) {
            chatInputEl.disabled = !enabled;
            chatInputEl.placeholder = enabled
                ? 'Escribe un mensaje al equipo...'
                : 'Selecciona un lead para enviar un mensaje al equipo...';
        }
        if (chatSendEl) chatSendEl.disabled = !enabled || isSending || isUploading;
        if (composerPlusEl) composerPlusEl.disabled = !enabled || isUploading;
    }

    function syncBodyScrollLock() {
        const anyOpen = (interactionModalEl && !interactionModalEl.hidden) ||
            (imageModalEl && !imageModalEl.hidden) ||
            (pdfModalEl && !pdfModalEl.hidden);
        document.body.style.overflow = anyOpen ? 'hidden' : '';
    }

    function openOverlayModal(modalEl) {
        if (!modalEl) return;
        modalEl.hidden = false;
        modalEl.setAttribute('aria-hidden', 'false');
        syncBodyScrollLock();
    }

    function closeOverlayModal(modalEl) {
        if (!modalEl) return;
        modalEl.hidden = true;
        modalEl.setAttribute('aria-hidden', 'true');
        syncBodyScrollLock();
    }

    function openImageModal(url) {
        if (!url || !imageModalEl || !imageModalImgEl) return;
        imageModalImgEl.src = url;
        openOverlayModal(imageModalEl);
    }

    function closeImageModal() {
        if (imageModalImgEl) imageModalImgEl.src = '';
        closeOverlayModal(imageModalEl);
    }

    function openPdfModal(url, name, downloadUrl) {
        if (!url || !pdfModalEl || !pdfFrameEl) return;
        if (pdfModalTitleEl) pdfModalTitleEl.textContent = name || 'Documento PDF';
        if (pdfModalDownloadEl) {
            pdfModalDownloadEl.href = downloadUrl || url;
            pdfModalDownloadEl.setAttribute('download', name || 'documento.pdf');
        }
        pdfFrameEl.src = url;
        openOverlayModal(pdfModalEl);
    }

    function closePdfModal() {
        if (pdfFrameEl) pdfFrameEl.src = 'about:blank';
        closeOverlayModal(pdfModalEl);
    }

    function showUploadIndicator(fileName, percent) {
        if (!uploadIndicatorEl) return;
        uploadIndicatorEl.hidden = false;
        if (uploadNameEl) uploadNameEl.textContent = 'Subiendo ' + fileName + '...';
        updateUploadProgress(percent, fileName);
    }

    function updateUploadProgress(percent, fileName) {
        const pct = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
        if (uploadProgressBarEl) uploadProgressBarEl.style.width = pct + '%';
        if (uploadPercentEl) uploadPercentEl.textContent = pct + '%';
        if (uploadNameEl && fileName) uploadNameEl.textContent = 'Subiendo ' + fileName + '...';
    }

    function hideUploadIndicator() {
        if (!uploadIndicatorEl) return;
        uploadIndicatorEl.hidden = true;
        if (uploadProgressBarEl) uploadProgressBarEl.style.width = '0%';
        if (uploadPercentEl) uploadPercentEl.textContent = '0%';
    }

    function openModal(modalEl) {
        openOverlayModal(modalEl);
    }

    function closeModal(modalEl) {
        closeOverlayModal(modalEl);
    }

    function buildInteractionEmbedUrl(tabla, origId) {
        return 'lead_interaction.php?embed=1&tabla_origen=' + encodeURIComponent(tabla) +
            '&id=' + encodeURIComponent(String(origId));
    }

    function isActionPopoverOpen() {
        return actionPopoverEl && !actionPopoverEl.hidden;
    }

    function openActionPopover() {
        if (!currentLead.tabla || currentLead.origId <= 0 || !actionPopoverEl) return;
        actionPopoverEl.hidden = false;
        actionPopoverEl.setAttribute('aria-hidden', 'false');
        if (composerPlusEl) composerPlusEl.setAttribute('aria-expanded', 'true');
    }

    function closeActionPopover() {
        if (!actionPopoverEl) return;
        actionPopoverEl.hidden = true;
        actionPopoverEl.setAttribute('aria-hidden', 'true');
        if (composerPlusEl) composerPlusEl.setAttribute('aria-expanded', 'false');
    }

    function toggleActionPopover() {
        if (isActionPopoverOpen()) {
            closeActionPopover();
        } else {
            openActionPopover();
        }
    }

    function openInteractionModal() {
        if (!currentLead.tabla || currentLead.origId <= 0) return;
        closeActionPopover();
        if (interactionFrameEl) {
            interactionFrameEl.src = buildInteractionEmbedUrl(currentLead.tabla, currentLead.origId);
        }
        openModal(interactionModalEl);
    }

    function closeInteractionModal(clearFrame) {
        closeModal(interactionModalEl);
        if (clearFrame !== false && interactionFrameEl) {
            interactionFrameEl.src = 'about:blank';
        }
    }

    function reloadCurrentLeadTrace() {
        if (!currentLead.tabla || currentLead.origId <= 0) return;
        loadLeadTrace(
            currentLead.tabla,
            currentLead.origId,
            currentLead.cfId,
            {
                name: titleEl ? titleEl.textContent : '',
                estatus: (subEl && subEl.textContent.indexOf('Estatus actual: ') === 0)
                    ? subEl.textContent.replace('Estatus actual: ', '')
                    : '',
                vendedora: vendedoraNameEl ? vendedoraNameEl.textContent : '',
                hasLead: true,
                timelineFilter: activeTimelineFilter
            }
        );
    }

    function buildDownloadBtnHtml(downloadUrl, nombre) {
        if (!downloadUrl) return '';
        return '<a href="' + escapeAttr(downloadUrl) + '" class="pltrace-chat-download-btn" download="' + escapeAttr(nombre) + '" title="Descargar archivo">' +
            '<i class="fas fa-download"></i> Descargar' +
        '</a>';
    }

    function buildFileMetaHtml(nombre, tipoLabel, tamano, downloadUrl) {
        return '<div class="pltrace-chat-file-meta">' +
            '<div class="pltrace-chat-file-meta-info">' +
                '<span class="pltrace-chat-file-name">' + escapeHtml(nombre) + '</span>' +
                '<span class="pltrace-chat-file-type">' + escapeHtml(tipoLabel) + '</span>' +
                (tamano ? '<span class="pltrace-chat-file-size">' + escapeHtml(tamano) + '</span>' : '') +
            '</div>' +
            buildDownloadBtnHtml(downloadUrl, nombre) +
        '</div>';
    }

    function buildFileContentHtml(archivo) {
        if (!archivo) return '';

        const categoria = archivo.categoria || 'other';
        const nombre = archivo.nombre || 'Archivo';
        const tipoLabel = archivo.tipo_label || 'Archivo';
        const tamano = archivo.tamano_display || '';
        const url = archivo.url || '';
        const downloadUrl = archivo.download_url || url;
        const iconClass = fileIconClass(categoria);
        const iconFa = fileIconFa(categoria, archivo.extension);

        const metaHtml = buildFileMetaHtml(nombre, tipoLabel, tamano, downloadUrl);

        if (categoria === 'image' && url) {
            return '<div class="pltrace-chat-file pltrace-chat-file--image">' +
                '<button type="button" class="pltrace-chat-image-btn" data-pltrace-image="' + escapeAttr(url) + '" title="Ver imagen ampliada">' +
                    '<img src="' + escapeAttr(url) + '" alt="' + escapeAttr(nombre) + '" loading="lazy">' +
                '</button>' +
                metaHtml +
            '</div>';
        }

        if (categoria === 'video' && url) {
            return '<div class="pltrace-chat-file pltrace-chat-file--video">' +
                '<video controls preload="metadata" src="' + escapeAttr(url) + '"></video>' +
                metaHtml +
            '</div>';
        }

        if (categoria === 'pdf') {
            return '<div class="pltrace-chat-file pltrace-chat-file--pdf">' +
                '<div class="pltrace-chat-doc-card">' +
                    '<button type="button" class="pltrace-chat-pdf-card" data-pltrace-pdf="' + escapeAttr(url) + '" data-pltrace-pdf-name="' + escapeAttr(nombre) + '" data-pltrace-pdf-download="' + escapeAttr(downloadUrl) + '">' +
                        '<span class="pltrace-chat-file-icon ' + iconClass + '"><i class="fas ' + iconFa + '"></i></span>' +
                        '<span class="pltrace-chat-file-info">' +
                            '<span class="pltrace-chat-file-name">' + escapeHtml(nombre) + '</span>' +
                            '<span class="pltrace-chat-file-type">' + escapeHtml(tipoLabel) + '</span>' +
                            (tamano ? '<span class="pltrace-chat-file-size">' + escapeHtml(tamano) + '</span>' : '') +
                        '</span>' +
                    '</button>' +
                    buildDownloadBtnHtml(downloadUrl, nombre) +
                '</div>' +
            '</div>';
        }

        return '<div class="pltrace-chat-file pltrace-chat-file--doc">' +
            '<div class="pltrace-chat-doc-card">' +
                '<span class="pltrace-chat-file-icon ' + iconClass + '"><i class="fas ' + iconFa + '"></i></span>' +
                '<span class="pltrace-chat-file-info">' +
                    '<span class="pltrace-chat-file-name">' + escapeHtml(nombre) + '</span>' +
                    '<span class="pltrace-chat-file-type">' + escapeHtml(tipoLabel) + '</span>' +
                    (tamano ? '<span class="pltrace-chat-file-size">' + escapeHtml(tamano) + '</span>' : '') +
                '</span>' +
                buildDownloadBtnHtml(downloadUrl, nombre) +
            '</div>' +
        '</div>';
    }

    function buildChatBubbleHtml(ev, animate) {
        const chat = ev.chat || {};
        const isMine = !!chat.is_mine;
        const rowClass = isMine ? 'out' : 'in';
        const msgId = chat.message_id ? String(chat.message_id) : '';
        const tipoContenido = chat.tipo_contenido || 'texto';
        const isFile = tipoContenido === 'archivo' && chat.archivo;
        const bubbleClass = isFile ? ' pltrace-chat-bubble--file' : '';

        let bodyHtml = '';
        if (isFile) {
            bodyHtml = buildFileContentHtml(chat.archivo);
        } else {
            bodyHtml = '<div class="pltrace-chat-text">' + escapeHtml(ev.detalle || '') + '</div>';
        }

        return '<div class="pltrace-chat-row pltrace-chat-row--' + rowClass + (animate ? ' pltrace-msg-animate' : '') + '" data-message-id="' + escapeHtml(msgId) + '">' +
            '<div class="pltrace-chat-bubble pltrace-chat-bubble--' + rowClass + bubbleClass + '">' +
                '<div class="pltrace-chat-meta">' +
                    '<span class="pltrace-chat-author">' + escapeHtml(chat.usuario_nombre || 'Usuario') + '</span>' +
                    '<span class="pltrace-chat-role">' + escapeHtml(chat.usuario_rol || '') + '</span>' +
                '</div>' +
                bodyHtml +
                '<div class="pltrace-chat-time">' + escapeHtml(ev.fecha_display || '—') + '</div>' +
            '</div>' +
        '</div>';
    }

    function renderTrace(events, opts) {
        opts = opts || {};
        const wasNearBottom = opts.preserveScroll ? isNearBottom(chatEl) : true;
        const animateNew = !!opts.animateNew;

        chatEl.innerHTML = '';
        if (!events.length) {
            chatEl.innerHTML = '<div class="pltrace-timeline-empty">' + escapeHtml(emptyMessageForFilter(activeTimelineFilter)) + '</div>';
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

            if (tipo === 'chat_mensaje') {
                const wrap = document.createElement('div');
                wrap.innerHTML = buildChatBubbleHtml(ev, animateNew);
                chatEl.appendChild(wrap.firstChild);
                return;
            }

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

        if (wasNearBottom) {
            scrollTimelineToBottom();
        }
    }

    function mergeChatEvents(newEvents) {
        if (!newEvents || !newEvents.length) return false;

        const existingIds = {};
        allEvents.forEach(function (ev) {
            if (ev.tipo === 'chat_mensaje' && ev.chat && ev.chat.message_id) {
                existingIds[String(ev.chat.message_id)] = true;
            }
        });

        let added = false;
        newEvents.forEach(function (ev) {
            const mid = ev.chat && ev.chat.message_id ? String(ev.chat.message_id) : '';
            if (mid && existingIds[mid]) return;
            if (mid) existingIds[mid] = true;
            allEvents.push(ev);
            added = true;
        });

        if (!added) return false;

        allEvents = sortEvents(allEvents);
        const fp = eventFingerprint(allEvents);
        if (fp === timelineFingerprint) return false;
        timelineFingerprint = fp;

        lastMessageId = 0;
        allEvents.forEach(function (ev) {
            if (ev.tipo === 'chat_mensaje' && ev.chat && ev.chat.message_id) {
                const mid = parseInt(ev.chat.message_id, 10) || 0;
                if (mid > lastMessageId) lastMessageId = mid;
            }
        });

        refreshTimelineView({ preserveScroll: true, animateNew: true });
        refreshUnreadBadges();
        return true;
    }

    function updateUnreadBadge(traceKey, count) {
        appRoot.querySelectorAll('.pltrace-lead-card').forEach(function (card) {
            if ((card.dataset.pltraceKey || '') !== traceKey) return;
            const badge = card.querySelector('.pltrace-unread-badge');
            if (!badge) return;

            const n = parseInt(count, 10) || 0;
            if (n > 0) {
                badge.textContent = n > 9 ? '9+' : String(n);
                badge.hidden = false;
            } else {
                badge.textContent = '';
                badge.hidden = true;
            }
        });
    }

    function collectVisibleTraceKeys() {
        const keys = [];
        appRoot.querySelectorAll('.pltrace-lead-card').forEach(function (btn) {
            if (btn.style.display === 'none') return;
            const key = btn.dataset.pltraceKey || '';
            if (key) keys.push(key);
        });
        return keys;
    }

    function refreshUnreadBadges() {
        const keys = collectVisibleTraceKeys();
        if (!keys.length) return;

        const body = new URLSearchParams();
        keys.forEach(function (key) {
            body.append('trace_keys[]', key);
        });

        fetch(PLTRACE_CHAT_API + '?action=chat_unread_batch', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) return;
                const counts = data.counts || {};
                keys.forEach(function (key) {
                    updateUnreadBadge(key, counts[key] || 0);
                });
            })
            .catch(function () {});
    }

    function scheduleMarkRead() {
        if (!currentLead.tabla || currentLead.origId <= 0) return;
        if (markReadTimer) clearTimeout(markReadTimer);
        markReadTimer = setTimeout(function () {
            const body = new URLSearchParams();
            body.set('tabla', currentLead.tabla);
            body.set('orig_id', String(currentLead.origId));
            fetch(PLTRACE_CHAT_API + '?action=chat_mark_read', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        updateUnreadBadge(currentLead.traceKey, 0);
                        refreshUnreadBadges();
                    }
                })
                .catch(function () {});
        }, 600);
    }

    function stopChatPolling() {
        if (chatPollTimer) {
            clearInterval(chatPollTimer);
            chatPollTimer = null;
        }
    }

    function startChatPolling() {
        stopChatPolling();
        if (!currentLead.tabla || currentLead.origId <= 0) return;

        chatPollTimer = setInterval(function () {
            if (!currentLead.tabla || currentLead.origId <= 0) return;
            const url = PLTRACE_CHAT_API + '?action=chat_poll&tabla=' + encodeURIComponent(currentLead.tabla) +
                '&orig_id=' + encodeURIComponent(String(currentLead.origId)) +
                '&since_id=' + encodeURIComponent(String(lastMessageId || 0));

            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.success) return;
                    if (typeof data.last_id === 'number' && data.last_id > lastMessageId) {
                        // lastMessageId updated in renderTrace after merge
                    }
                    if (data.events && data.events.length) {
                        mergeChatEvents(data.events);
                        scheduleMarkRead();
                    }
                })
                .catch(function () {});
        }, 2500);
    }

    function startBadgePolling() {
        if (badgePollTimer) return;
        refreshUnreadBadges();
        badgePollTimer = setInterval(refreshUnreadBadges, 9000);
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
        closeActionPopover();
        hideUploadIndicator();
        const traceKey = tabla + '|' + origId;
        currentLead = { tabla: tabla, origId: origId, cfId: cfId, traceKey: traceKey };

        setActiveItem(traceKey);
        setComposerEnabled(tabla !== '' && origId > 0);
        stopChatPolling();
        activeTimelineFilter = (meta && meta.timelineFilter) ? meta.timelineFilter : 'todos';
        setFilterTabsVisible(false);
        allEvents = [];

        const name = (meta && meta.name)
            ? meta.name
            : ((INITIAL_DETAIL_HAS_LEAD && INITIAL_DETAIL_NAME !== 'Selecciona un lead')
                ? INITIAL_DETAIL_NAME
                : ('Lead #' + origId));
        const estatus = (meta && meta.estatus) ? meta.estatus : (INITIAL_DETAIL_ESTATUS || '');
        const vendedora = (meta && meta.vendedora) ? meta.vendedora : (INITIAL_DETAIL_VENDEDORA || '');
        updateDetailHeader({
            name: name,
            estatus: estatus,
            vendedora: vendedora,
            hasLead: true
        });

        chatEl.innerHTML = '<div class="pltrace-timeline-empty">Cargando trazabilidad...</div>';

        const token = ++loadToken;
        fetch(buildTracerUrl(tabla, origId, cfId), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (token !== loadToken) return;
                if (!data || !data.success) {
                    chatEl.innerHTML = '<div class="pltrace-timeline-empty">' + escapeHtml((data && data.error) || 'No se pudo cargar la trazabilidad') + '</div>';
                    setComposerEnabled(false);
                    setFilterTabsVisible(false);
                    return;
                }
                if (data.lead && data.lead.tabla_origen && data.lead.orig_id) {
                    currentLead.tabla = data.lead.tabla_origen;
                    currentLead.origId = parseInt(data.lead.orig_id, 10) || currentLead.origId;
                    currentLead.traceKey = currentLead.tabla + '|' + currentLead.origId;
                    setComposerEnabled(true);
                }
                updateDetailHeader({
                    name: (data.lead && data.lead.nombre) ? data.lead.nombre : name,
                    estatus: (data.lead && data.lead.estatus_actual) ? data.lead.estatus_actual : estatus,
                    vendedora: (data.lead && data.lead.vendedora) ? data.lead.vendedora : vendedora,
                    hasLead: true
                });
                setFilterTabsVisible(true);
                setAllEvents(data.events || [], { animateNew: false });
                scheduleMarkRead();
                startChatPolling();
            })
            .catch(function () {
                if (token !== loadToken) return;
                chatEl.innerHTML = '<div class="pltrace-timeline-empty">Error de comunicación con el servidor.</div>';
                setComposerEnabled(false);
                setFilterTabsVisible(false);
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

    function sendChatMessage() {
        if (!chatInputEl || !currentLead.tabla || currentLead.origId <= 0 || isSending) return;
        const text = (chatInputEl.value || '').trim();
        if (!text) return;

        isSending = true;
        setComposerEnabled(true);

        const body = new URLSearchParams();
        body.set('tabla', currentLead.tabla);
        body.set('orig_id', String(currentLead.origId));
        body.set('mensaje', text);
        if (currentLead.cfId > 0) {
            body.set('cf_id', String(currentLead.cfId));
        }

        fetch(PLTRACE_CHAT_API + '?action=chat_send', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success && data.event) {
                    chatInputEl.value = '';
                    mergeChatEvents([data.event]);
                    scrollTimelineToBottom();
                } else {
                    alert((data && data.message) ? data.message : 'No se pudo enviar el mensaje');
                }
            })
            .catch(function () {
                alert('Error de comunicación al enviar el mensaje');
            })
            .finally(function () {
                isSending = false;
                setComposerEnabled(currentLead.tabla !== '' && currentLead.origId > 0);
            });
    }

    function uploadChatFile(file) {
        if (!file || !currentLead.tabla || currentLead.origId <= 0 || isUploading) return;

        closeActionPopover();
        isUploading = true;
        setComposerEnabled(true);
        showUploadIndicator(file.name, 0);

        const formData = new FormData();
        formData.append('archivo', file);
        formData.append('tabla', currentLead.tabla);
        formData.append('orig_id', String(currentLead.origId));
        if (currentLead.cfId > 0) {
            formData.append('cf_id', String(currentLead.cfId));
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', PLTRACE_CHAT_API + '?action=chat_upload', true);
        xhr.withCredentials = true;

        xhr.upload.addEventListener('progress', function (e) {
            if (!e.lengthComputable) return;
            const pct = Math.round((e.loaded / e.total) * 100);
            updateUploadProgress(pct, file.name);
        });

        xhr.onload = function () {
            hideUploadIndicator();
            let data = null;
            try {
                data = JSON.parse(xhr.responseText || '{}');
            } catch (err) {
                data = null;
            }

            if (xhr.status >= 200 && xhr.status < 300 && data && data.success && data.event) {
                mergeChatEvents([data.event]);
                scrollTimelineToBottom();
            } else {
                const msg = (data && (data.message || data.error)) ? (data.message || data.error) : 'No se pudo cargar el archivo';
                alert(msg);
            }

            isUploading = false;
            setComposerEnabled(currentLead.tabla !== '' && currentLead.origId > 0);
        };

        xhr.onerror = function () {
            hideUploadIndicator();
            alert('Error de comunicación al cargar el archivo');
            isUploading = false;
            setComposerEnabled(currentLead.tabla !== '' && currentLead.origId > 0);
        };

        xhr.send(formData);
    }

    appRoot.querySelectorAll('.pltrace-lead-card').forEach(function (btn) {
        btn.addEventListener('click', function () {
            loadLeadTrace(
                btn.dataset.pltraceTabla || '',
                parseInt(btn.dataset.pltraceOrigId, 10) || 0,
                parseInt(btn.dataset.pltraceCfId, 10) || 0,
                {
                    name: btn.dataset.pltraceName || '',
                    estatus: btn.dataset.pltraceEstatus || '',
                    vendedora: btn.dataset.pltraceVendedora || ''
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
            refreshUnreadBadges();
        });
    }

    if (chatSendEl) {
        chatSendEl.addEventListener('click', sendChatMessage);
    }
    if (chatInputEl) {
        chatInputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendChatMessage();
            }
        });
    }

    if (composerPlusEl) {
        composerPlusEl.addEventListener('click', function (e) {
            e.stopPropagation();
            if (composerPlusEl.disabled) return;
            toggleActionPopover();
        });
    }
    document.addEventListener('click', function (e) {
        if (!isActionPopoverOpen()) return;
        const actionsWrap = composerPlusEl ? composerPlusEl.closest('.pltrace-composer-actions') : null;
        if (actionsWrap && actionsWrap.contains(e.target)) return;
        closeActionPopover();
    });
    if (actionRegisterEl) {
        actionRegisterEl.addEventListener('click', openInteractionModal);
    }
    if (actionUploadEl && fileInputEl) {
        actionUploadEl.addEventListener('click', function () {
            closeActionPopover();
            if (composerPlusEl && composerPlusEl.disabled) return;
            fileInputEl.value = '';
            fileInputEl.click();
        });
        fileInputEl.addEventListener('change', function () {
            const file = fileInputEl.files && fileInputEl.files[0] ? fileInputEl.files[0] : null;
            if (file) uploadChatFile(file);
        });
    }
    if (chatEl) {
        chatEl.addEventListener('click', function (e) {
            const imageBtn = e.target.closest('[data-pltrace-image]');
            if (imageBtn) {
                e.preventDefault();
                openImageModal(imageBtn.getAttribute('data-pltrace-image') || '');
                return;
            }
            const pdfBtn = e.target.closest('[data-pltrace-pdf]');
            if (pdfBtn) {
                e.preventDefault();
                openPdfModal(
                    pdfBtn.getAttribute('data-pltrace-pdf') || '',
                    pdfBtn.getAttribute('data-pltrace-pdf-name') || 'Documento PDF',
                    pdfBtn.getAttribute('data-pltrace-pdf-download') || ''
                );
            }
        });
    }
    if (imageModalCloseEl) {
        imageModalCloseEl.addEventListener('click', closeImageModal);
    }
    if (imageModalEl) {
        imageModalEl.addEventListener('click', function (e) {
            if (e.target === imageModalEl) closeImageModal();
        });
    }
    if (pdfModalCloseEl) {
        pdfModalCloseEl.addEventListener('click', closePdfModal);
    }
    if (pdfModalEl) {
        pdfModalEl.addEventListener('click', function (e) {
            if (e.target === pdfModalEl) closePdfModal();
        });
    }
    if (interactionModalCloseEl) {
        interactionModalCloseEl.addEventListener('click', function () {
            closeInteractionModal(true);
        });
    }
    if (interactionModalEl) {
        interactionModalEl.addEventListener('click', function (e) {
            if (e.target === interactionModalEl) closeInteractionModal(true);
        });
    }
    if (profileModalEl) {
        profileModalEl.addEventListener('click', function (e) {
            if (e.target === profileModalEl) closeLeadProfileModal();
        });
    }
    if (detailIdentityEl) {
        detailIdentityEl.addEventListener('click', openLeadProfileModal);
        detailIdentityEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openLeadProfileModal();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (profileModalEl && !profileModalEl.hidden) {
            closeLeadProfileModal();
            return;
        }
        if (interactionModalEl && !interactionModalEl.hidden) {
            closeInteractionModal(true);
            return;
        }
        if (pdfModalEl && !pdfModalEl.hidden) {
            closePdfModal();
            return;
        }
        if (imageModalEl && !imageModalEl.hidden) {
            closeImageModal();
            return;
        }
        if (isActionPopoverOpen()) {
            closeActionPopover();
        }
    });

    window.addEventListener('message', function (e) {
        if (e.origin !== window.location.origin) return;
        const data = e.data || {};
        if (data.type === 'pltrace_interaction_close') {
            closeInteractionModal(true);
            return;
        }
        if (data.type === 'pltrace_interaction_saved') {
            closeInteractionModal(true);
            reloadCurrentLeadTrace();
        }
    });

    if (filterTabsEl) {
        filterTabsEl.querySelectorAll('.pltrace-filter-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setTimelineFilter(btn.dataset.filter || 'todos');
            });
        });
    }

    startBadgePolling();

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
                    estatus: initialBtn.dataset.pltraceEstatus || '',
                    vendedora: initialBtn.dataset.pltraceVendedora || ''
                }
            );
        } else if (INITIAL_TABLA && INITIAL_ORIG_ID > 0) {
            loadLeadTrace(INITIAL_TABLA, INITIAL_ORIG_ID, INITIAL_CF_ID, {
                name: INITIAL_DETAIL_NAME,
                estatus: INITIAL_DETAIL_ESTATUS,
                vendedora: INITIAL_DETAIL_VENDEDORA
            });
        } else if (INITIAL_CF_ID > 0) {
            loadLeadTrace('', 0, INITIAL_CF_ID, {
                name: INITIAL_DETAIL_NAME,
                estatus: INITIAL_DETAIL_ESTATUS,
                vendedora: INITIAL_DETAIL_VENDEDORA
            });
        }
    }
})();
</script>
</body>
</html>
