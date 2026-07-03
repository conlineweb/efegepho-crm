<?php
/**
 * Validación de KPIs del Dashboard Comercial.
 * Usa exactamente las mismas funciones que dashboard_comercial.php.
 *
 * CLI:  php dashboard_comercial_validar.php [start_date] [end_date]
 * Web: /admin/dashboard_comercial_validar.php?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
 */
$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['login']) || $_SESSION['login'] === false) {
        http_response_code(403);
        die('Inicia sesión en el admin para usar esta herramienta.');
    }
    header('Content-Type: text/html; charset=utf-8');
}

require_once __DIR__ . '/dashboard_comercial_period_helper.php';
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/dashboard_comercial_helper.php';
require_once __DIR__ . '/dashboard_comercial_validar_helper.php';

if ($isCli) {
    $startDate = $argv[1] ?? date('Y-m-01');
    $endDate = $argv[2] ?? date('Y-m-d');
} else {
    $startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
}

list($startDate, $endDate) = dashComercialResolvePeriodDates($startDate, $endDate);

$t0 = microtime(true);
$metrics = dashComercialComputeMetrics($conn, $startDate, $endDate);

$agendadosRaw = dashComercialBuildAgendadosCohort($conn, $startDate, $endDate);
$agendadosModal = $agendadosRaw;
$atendidosRaw = dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate);
$atendidosModal = $atendidosRaw;
$clientesRaw = dashComercialBuildClientesCohort($conn, $startDate, $endDate);
$clientesModal = $clientesRaw;

$fetchLeads = dashComercialFetchLeadRecords($conn, $startDate, $endDate);
$fetchAgendas = dashComercialFetchAgendaRecords($conn, $startDate, $endDate);
$fetchAtendidos = dashComercialFetchAtendidoRecords($conn, $startDate, $endDate);
$fetchClientes = dashComercialFetchClienteRecords($conn, $startDate, $endDate);

$funnelAudit = dashComercialValidarFunnelFlows(
    $conn,
    $startDate,
    $endDate,
    $fetchLeads,
    $agendadosModal,
    $atendidosModal,
    $clientesModal
);

$elapsed = round(microtime(true) - $t0, 2);

$rows = [
    [
        'kpi' => 'Leads (precalificación)',
        'dashboard' => (int) ($metrics['totals']['leads'] ?? 0),
        'cohorte_raw' => null,
        'cohorte_modal' => null,
        'fetch_modal' => count($fetchLeads),
        'funcion' => 'dashComercialCountTotalLeads',
        'fuente' => 'tablas_leads + eventos_wp contact_form (created_time en periodo)',
    ],
    [
        'kpi' => 'Agendas',
        'dashboard' => (int) ($metrics['totals']['agendas'] ?? 0),
        'cohorte_raw' => count($agendadosRaw),
        'cohorte_modal' => count($agendadosModal),
        'fetch_modal' => count($fetchAgendas),
        'funcion' => 'dashComercialCountAgendas',
        'fuente' => 'consulta_agendados_leads (fecha en que agendó)',
    ],
    [
        'kpi' => 'Atendidos',
        'dashboard' => (int) ($metrics['totals']['atendidos'] ?? 0),
        'cohorte_raw' => count($atendidosRaw),
        'cohorte_modal' => count($atendidosModal),
        'fetch_modal' => count($fetchAtendidos),
        'funcion' => 'dashComercialCountAtendidos',
        'fuente' => 'consulta_post_leads (fecha de atención)',
    ],
    [
        'kpi' => 'Clientes',
        'dashboard' => (int) ($metrics['totals']['clientes'] ?? 0),
        'cohorte_raw' => count($clientesRaw),
        'cohorte_modal' => count($clientesModal),
        'fetch_modal' => count($fetchClientes),
        'funcion' => 'dashComercialCountClientes',
        'fuente' => 'contact_form.cliente=1; fecha_cambio_cliente en periodo',
    ],
];

function dashValidarOk($expected, $actual)
{
    return (int) $expected === (int) $actual ? 'OK' : 'REVISAR';
}

if ($isCli) {
    echo "=== Validación Dashboard Comercial ===\n";
    echo "Periodo: {$startDate} → {$endDate} ({$elapsed}s)\n\n";
    printf("%-22s %8s %8s %8s %8s\n", 'KPI', 'KPI', 'Raw', 'Modal', 'Fetch');
    echo str_repeat('-', 58) . "\n";
    foreach ($rows as $r) {
        printf(
            "%-22s %8d %8s %8s %8d %s\n",
            $r['kpi'],
            $r['dashboard'],
            $r['cohorte_raw'] === null ? '—' : (string) $r['cohorte_raw'],
            $r['cohorte_modal'] === null ? '—' : (string) $r['cohorte_modal'],
            $r['fetch_modal'],
            dashValidarOk($r['dashboard'], $r['fetch_modal'])
        );
    }
    echo "\nKPIs compuestos:\n";
    $g = $metrics['global'];
    echo "  Calificación: {$g['calificacion']['detalle']} ({$g['calificacion']['porcentaje']})\n";
    echo "  Atención:     {$g['atencion']['detalle']} ({$g['atencion']['porcentaje']})\n";
    echo "  Cierre:       {$g['cierre']['detalle']} ({$g['cierre']['porcentaje']})\n";
    echo "\nHistorial calendario (referencia, no es el KPI principal):\n";
    foreach ($metrics['historial_breakdown'] as $hb) {
        echo "  {$hb['label']}: {$hb['total']}\n";
    }
    echo "\n=== Auditoría embudo por flujo/origen ===\n";
    echo "Revisados: {$funnelAudit['summary']['total_checked']} · OK: {$funnelAudit['summary']['ok']} · Huecos: {$funnelAudit['summary']['gaps']}\n\n";
    foreach ($funnelAudit['flows'] as $flow) {
        echo "{$flow['titulo']}: {$flow['ok']}/{$flow['total']} OK";
        if ((int) $flow['gaps'] > 0) {
            echo " ({$flow['gaps']} con huecos)";
        }
        echo "\n";
    }
    $conn->close();
    exit(0);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validar KPIs — Dashboard Comercial</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 24px; }
        .wrap { max-width: 1100px; margin: 0 auto; }
        h1 { font-size: 1.5rem; margin: 0 0 8px; }
        .sub { color: #64748b; margin-bottom: 20px; font-size: 0.9rem; }
        form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 24px; }
        input[type=date] { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
        button, a.btn { padding: 8px 14px; border-radius: 8px; border: none; background: #0f172a; color: #fff; text-decoration: none; font-size: 0.9rem; cursor: pointer; }
        a.btn.secondary { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        th, td { padding: 12px 14px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 0.88rem; vertical-align: top; }
        th { background: #f1f5f9; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
        .ok { color: #059669; font-weight: 700; }
        .bad { color: #dc2626; font-weight: 700; }
        .note { margin-top: 20px; padding: 16px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; font-size: 0.86rem; line-height: 1.55; }
        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 20px 0; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; }
        .card h3 { margin: 0 0 6px; font-size: 0.78rem; color: #64748b; text-transform: uppercase; }
        .card .val { font-size: 1.4rem; font-weight: 700; }
        .card .det { font-size: 0.82rem; color: #64748b; margin-top: 4px; }
        @media (max-width: 720px) { .cards { grid-template-columns: 1fr; } }
        h2.section { font-size: 1.15rem; margin: 32px 0 12px; }
        .flow-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 20px; overflow: hidden; }
        .flow-head { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .flow-head h3 { margin: 0 0 4px; font-size: 1rem; }
        .flow-meta { font-size: 0.82rem; color: #64748b; }
        .flow-meta .ok { margin-right: 12px; }
        .flow-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .flow-table th, .flow-table td { padding: 8px 10px; border-bottom: 1px solid #eef2f7; text-align: left; vertical-align: top; }
        .flow-table th { background: #fff; color: #64748b; font-size: 0.72rem; text-transform: uppercase; }
        .rules-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px; padding: 14px 16px; margin: 20px 0; font-size: 0.84rem; line-height: 1.55; }
        .rules-box ul { margin: 8px 0 0; padding-left: 18px; }
        .warn { color: #b45309; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Validación de KPIs — Dashboard Comercial</h1>
    <p class="sub">Periodo <strong><?php echo htmlspecialchars(date('d/m/Y', strtotime($startDate)) . ' → ' . date('d/m/Y', strtotime($endDate)), ENT_QUOTES, 'UTF-8'); ?></strong> · calculado en <?php echo $elapsed; ?>s</p>

    <form method="get">
        <label>Desde <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>"></label>
        <label>Hasta <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>"></label>
        <button type="submit">Validar</button>
        <a class="btn secondary" href="dashboard_comercial.php?start_date=<?php echo urlencode($startDate); ?>&amp;end_date=<?php echo urlencode($endDate); ?>">Ir al dashboard</a>
    </form>

    <div class="cards">
        <?php
        $g = $metrics['global'];
        foreach (['calificacion' => 'Tasa de agendas', 'atencion' => 'Tasa de atención', 'cierre' => 'Tasa de cierre'] as $key => $title):
            $k = $g[$key];
        ?>
        <div class="card">
            <h3><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="val"><?php echo htmlspecialchars((string) $k['porcentaje'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="det"><?php echo htmlspecialchars($k['detalle'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>KPI</th>
                <th>Dashboard</th>
                <th>Cohorte raw</th>
                <th>Cohorte + filtro modal</th>
                <th>Filas modal (fetch)</th>
                <th>Estado</th>
                <th>Lógica</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $ok = dashValidarOk($r['dashboard'], $r['fetch_modal']);
            $cohorteOk = $r['cohorte_modal'] === null || dashValidarOk($r['dashboard'], $r['cohorte_modal']) === 'OK';
        ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($r['kpi'], ENT_QUOTES, 'UTF-8'); ?></strong><br><code><?php echo htmlspecialchars($r['funcion'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                <td><?php echo (int) $r['dashboard']; ?></td>
                <td><?php echo $r['cohorte_raw'] === null ? '—' : (int) $r['cohorte_raw']; ?></td>
                <td><?php echo $r['cohorte_modal'] === null ? '—' : (int) $r['cohorte_modal']; ?></td>
                <td><?php echo (int) $r['fetch_modal']; ?></td>
                <td class="<?php echo ($ok === 'OK' && $cohorteOk) ? 'ok' : 'bad'; ?>"><?php echo ($ok === 'OK' && $cohorteOk) ? 'OK' : 'REVISAR'; ?></td>
                <td><?php echo htmlspecialchars($r['fuente'], ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="note">
        <strong>Cómo interpretar</strong>
        <ul>
            <li><strong>Dashboard</strong> = número en la tarjeta KPI de <code>dashboard_comercial.php</code>.</li>
            <li><strong>Cohorte raw</strong> = registros en periodo antes del filtro de estatus del modal.</li>
            <li><strong>Cohorte + filtro modal</strong> = debe coincidir con <strong>Dashboard</strong>.</li>
            <li><strong>Filas modal (fetch)</strong> = lo que devuelve el modal al hacer clic; debe coincidir con Dashboard.</li>
            <li>Si <strong>Raw &gt; Modal</strong>, hay registros fuera de estatus permitidos (p. ej. fantasma no cuenta en Atendidos).</li>
            <li><strong>Leads</strong> no usa cohorte; viene de <code>tablas_leads</code> + WP en contact_form.</li>
        </ul>
        <p>Comparación cruzada: <code>consulta_agendados_leads.php</code> ≈ Agendas · <code>consulta_post_leads.php</code> ≈ Atendidos · <code>clientes.php</code> ≈ Clientes (mismo periodo de fechas).</p>
    </div>

    <h2 class="section">Auditoría de embudo por flujo y origen</h2>
    <p class="sub">Verifica que cada registro aparezca en las etapas que corresponden según la lógica actual del dashboard.</p>

    <div class="rules-box">
        <strong>Reglas implementadas en el sistema</strong>
        <ul>
            <li><strong>Sesión intro WP</strong> (<code>wp_citas_leads</code>): genera Lead precalificado. Si ya tiene evento en <code>contact_form eventos_wp</code>, debe verse en Agendas y, según <code>cliente</code>, en Atendidos/Clientes.</li>
            <li><strong>Evento WP directo</strong>: Lead (<code>eventos_wp|id</code>) + Agendas; <code>cliente=2</code> (Cotizado) → Atendidos; <code>cliente=1</code> → Atendidos + Clientes.</li>
            <li><strong>Comercial</strong> (website, reg manual, Facebook): Lead en tabla origen + Agendas con cita → Atendidos → Clientes si <code>cliente=1</code>.</li>
        </ul>
        <p><strong>Exclusiones conocidas:</strong> <?php echo htmlspecialchars($funnelAudit['rules']['exclusiones'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>

    <p class="sub">
        Resumen auditoría:
        <strong><?php echo (int) $funnelAudit['summary']['total_checked']; ?></strong> registros revisados ·
        <span class="ok"><?php echo (int) $funnelAudit['summary']['ok']; ?> OK</span> ·
        <?php if ((int) $funnelAudit['summary']['gaps'] > 0): ?>
            <span class="bad"><?php echo (int) $funnelAudit['summary']['gaps']; ?> con huecos</span>
        <?php else: ?>
            <span class="ok">0 huecos</span>
        <?php endif; ?>
    </p>

    <?php foreach ($funnelAudit['flows'] as $flowKey => $flow): ?>
    <div class="flow-card">
        <div class="flow-head">
            <h3><?php echo htmlspecialchars($flow['titulo'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="flow-meta">
                <span class="ok"><?php echo (int) $flow['ok']; ?>/<?php echo (int) $flow['total']; ?> OK</span>
                <?php if ((int) $flow['gaps'] > 0): ?>
                    <span class="bad"><?php echo (int) $flow['gaps']; ?> huecos</span>
                <?php endif; ?>
                — <?php echo htmlspecialchars($flow['descripcion'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
        <?php if (empty($flow['rows'])): ?>
            <p style="padding:16px;color:#64748b;margin:0;">Sin registros en este periodo.</p>
        <?php else: ?>
        <table class="flow-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Registro</th>
                    <th>Leads</th>
                    <th>Agendas</th>
                    <th>Atendidos</th>
                    <th>Clientes</th>
                    <th>Estado</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($flow['rows'] as $row):
                $audit = $row['audit'] ?? [];
                $exp = $audit['expected'] ?? [];
                $flags = $audit['flags'] ?? [];
                $rowId = (int) ($row['id'] ?? $row['wcl_id'] ?? 0);
                $rowName = trim((string) ($row['nombre'] ?? $row['full_name'] ?? ''));
                if ($rowName === '') {
                    $rowName = '—';
                }
                $rowOk = !empty($audit['ok']);
            ?>
                <tr>
                    <td><?php echo $rowId; ?></td>
                    <td><?php echo htmlspecialchars($rowName, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="<?php echo ($flags['leads'] ?? null) === false ? 'bad' : (($flags['leads'] ?? null) === true ? 'ok' : ''); ?>"><?php echo dashValidarRenderStageCell(!empty($exp['leads']), $flags['leads'] ?? null); ?></td>
                    <td class="<?php echo ($flags['agendas'] ?? null) === false ? 'bad' : (($flags['agendas'] ?? null) === true ? 'ok' : ''); ?>"><?php echo dashValidarRenderStageCell(!empty($exp['agendas']), $flags['agendas'] ?? null); ?></td>
                    <td class="<?php echo ($flags['atendidos'] ?? null) === false ? 'bad' : (($flags['atendidos'] ?? null) === true ? 'ok' : ''); ?>"><?php echo dashValidarRenderStageCell(!empty($exp['atendidos']), $flags['atendidos'] ?? null); ?></td>
                    <td class="<?php echo ($flags['clientes'] ?? null) === false ? 'bad' : (($flags['clientes'] ?? null) === true ? 'ok' : ''); ?>"><?php echo dashValidarRenderStageCell(!empty($exp['clientes']), $flags['clientes'] ?? null); ?></td>
                    <td class="<?php echo $rowOk ? 'ok' : 'bad'; ?>"><?php echo $rowOk ? 'OK' : 'REVISAR'; ?></td>
                    <td><?php echo htmlspecialchars((string) ($audit['notas'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
</body>
</html>
