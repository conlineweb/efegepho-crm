<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';
require_once __DIR__ . '/usuario_roles_helper.php';

define('MLB_LEADS_SHELL', true);

$tab = isset($_GET['tab']) ? trim((string) $_GET['tab']) : 'precalificados';
$allowedTabs = ['precalificados', 'agendados', 'atendidos', 'clientes'];

if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'precalificados';
}

$tabFiles = [
    'precalificados' => 'my_lead_board_leads_tab_precalificados.php',
    'agendados'      => 'my_lead_board_leads_tab_agendados.php',
    'atendidos'      => 'my_lead_board_leads_tab_atendidos.php',
    'clientes'       => 'my_lead_board_leads_tab_clientes.php',
];

$tabLabels = [
    'precalificados' => 'Precalificados',
    'agendados'      => 'Agendados',
    'atendidos'      => 'Atendidos',
    'clientes'       => 'Clientes',
];

function buildMlbLeadsUrl(array $overrides = [])
{
    $params = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    if (!isset($params['tab'])) {
        $params['tab'] = 'precalificados';
    }

    $query = http_build_query($params);

    return 'my_lead_board_leads.php' . ($query !== '' ? ('?' . $query) : '');
}

$tabIncludePath = __DIR__ . '/' . $tabFiles[$tab];
if (!is_file($tabIncludePath)) {
    http_response_code(500);
    echo 'Vista de leads no disponible.';
    exit;
}

ob_start();
include $tabIncludePath;
$mlbTabHtml = ob_get_clean();

$mlbTabTotalTitles = [
    'precalificados' => 'Total de precalificados',
    'agendados'      => 'Total de agendados',
    'atendidos'      => 'Total de atendidos',
    'clientes'       => 'Total de clientes',
];

$mlbTabTotalCount = 0;
if ($tab === 'precalificados') {
    $mlbTabTotalCount = isset($totalCount) ? (int) $totalCount : 0;
} else {
    $mlbTabTotalCount = isset($leadsCountFiltered) ? (int) $leadsCountFiltered : 0;
}

$mlbTabRangeNote = '';
if (isset($reportRangeLabel) && trim((string) $reportRangeLabel) !== '') {
    $mlbTabRangeNote = trim((string) $reportRangeLabel);
} elseif (!empty($startDate) || !empty($endDate)) {
    $rangeStart = !empty($startDate) ? date('d/m/Y', strtotime($startDate)) : '...';
    $rangeEnd = !empty($endDate) ? date('d/m/Y', strtotime($endDate)) : '...';
    $mlbTabRangeNote = $rangeStart . ' → ' . $rangeEnd;
}
?>
<link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    .mlb-leads-page {
        padding: 0 20px 48px;
        max-width: 100%;
    }

    .mlb-leads-topbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 16px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .mlb-leads-topbar h1 {
        margin: 0 0 4px;
        font-size: 28px;
        font-weight: 700;
        color: #111;
    }

    .mlb-leads-topbar p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .mlb-leads-shell {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }

    .mlb-leads-tabs-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 20px;
        border-bottom: 1px solid #e2e8f0;
        flex-wrap: wrap;
    }

    .mlb-leads-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .mlb-leads-tab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 14px;
        font-size: 14px;
        font-weight: 700;
        color: #4b5563;
        border: 1px solid #e2e8f0;
        border-radius: 9999px;
        background: #fff;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .mlb-leads-tab:hover {
        border-color: #cdd4df;
        background: #f9fafb;
        color: #1f2937;
    }

    .mlb-leads-tab.active {
        color: #fff;
        background: #111;
        border-color: #111;
    }

    .mlb-leads-tab-pane {
        padding: 0;
    }

    .mlb-leads-tab-content .efege-page,
    .mlb-leads-tab-content .efege-postq {
        padding: 0 0 24px;
    }

    .mlb-leads-tab-content .filter-bar {
        margin: 16px 20px 0;
    }

    .mlb-leads-tab-content .table-wrap {
        margin: 16px 20px 0;
    }

    .efege-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 9999px;
        font-size: 13px;
        font-weight: 700;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #1f2937;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .efege-btn:hover {
        border-color: #111;
        color: #111;
    }

    .efege-btn-primary {
        background: #111;
        border-color: #111;
        color: #fff;
    }

    .efege-btn-primary:hover {
        background: #333;
        border-color: #333;
        color: #fff;
    }

    .mlb-leads-summary {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .mlb-leads-summary-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 20px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .mlb-leads-summary-label {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 6px;
    }

    .mlb-leads-summary-value {
        font-size: 32px;
        font-weight: 800;
        line-height: 1;
        color: #0f172a;
        letter-spacing: -0.5px;
    }

    .mlb-leads-summary-note {
        font-size: 12px;
        color: #64748b;
        text-align: right;
    }

    .mlb-leads-summary-note strong {
        color: #0f172a;
    }
</style>

<div class="mlb-leads-page">
    <header class="mlb-leads-topbar">
        <div>
            <h1>Leads</h1>
            <p>Precalificados, agendados, atendidos y clientes en una sola vista</p>
        </div>
        <div>
            <a href="my_lead_board.php" class="efege-btn">← My Lead Board</a>
        </div>
    </header>

    <div class="mlb-leads-shell">
        <div class="mlb-leads-tabs-row">
            <div class="mlb-leads-tabs">
                <?php foreach ($allowedTabs as $tabKey): ?>
                    <a href="<?php echo htmlspecialchars(buildMlbLeadsUrl(['tab' => $tabKey]), ENT_QUOTES, 'UTF-8'); ?>"
                       class="mlb-leads-tab <?php echo $tab === $tabKey ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($tabLabels[$tabKey], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mlb-leads-summary">
            <div class="mlb-leads-summary-card">
                <div>
                    <div class="mlb-leads-summary-label"><?php echo htmlspecialchars($mlbTabTotalTitles[$tab] ?? 'Total', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="mlb-leads-summary-value"><?php echo number_format($mlbTabTotalCount); ?></div>
                </div>
                <?php if ($mlbTabRangeNote !== ''): ?>
                    <div class="mlb-leads-summary-note">
                        Periodo filtrado<br>
                        <strong><?php echo htmlspecialchars($mlbTabRangeNote, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                <?php else: ?>
                    <div class="mlb-leads-summary-note">
                        Mostrando todos los registros del tab activo
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
        <script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
        <script src="https://code.highcharts.com/highcharts.js"></script>
        <script src="https://code.highcharts.com/modules/accessibility.js"></script>

        <div class="mlb-leads-tab-pane">
            <?php echo $mlbTabHtml; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
