<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';

function wpAppointmentsPlannerName(array $planner)
{
    $empresa = trim((string) ($planner['empresa_wp'] ?? ''));
    if ($empresa !== '') {
        return $empresa;
    }

    $fullName = trim((string) ($planner['full_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    $campaign = trim((string) ($planner['campaign_name'] ?? ''));
    if ($campaign !== '') {
        return $campaign;
    }

    return 'WP #' . intval($planner['wedding_planner_id'] ?? 0);
}

function wpAppointmentsFormatDate($dateValue)
{
    if (empty($dateValue)) {
        return 'Sin fecha';
    }

    $timestamp = strtotime((string) $dateValue);
    if ($timestamp === false) {
        return (string) $dateValue;
    }

    return date('d/m/Y', $timestamp);
}

function wpAppointmentsFormatHour($timeValue)
{
    if (empty($timeValue)) {
        return 'Sin hora';
    }

    $timestamp = strtotime((string) $timeValue);
    if ($timestamp === false) {
        return (string) $timeValue;
    }

    return date('H:i', $timestamp);
}

function wpAppointmentsFormatDateTime($dateValue, $timeValue)
{
  $dateLabel = wpAppointmentsFormatDate($dateValue);
  $timeLabel = wpAppointmentsFormatHour($timeValue);

  if ($dateLabel === 'Sin fecha' && $timeLabel === 'Sin hora') {
    return 'Sin fecha';
  }

  if ($dateLabel === 'Sin fecha') {
    return $timeLabel;
  }

  if ($timeLabel === 'Sin hora') {
    return $dateLabel;
  }

  return $dateLabel . ' ' . $timeLabel;
}

function wpAppointmentsStatusLabel($value)
{
    $status = is_numeric($value) ? intval($value) : null;
    if ($status === 1) {
        return 'Atendido';
    }
    if ($status === 2) {
        return 'Fantasma';
    }
    if ($status === 3) {
        return 'Muerto';
    }
    if ($status === 0) {
        return 'Pendiente';
    }

    $raw = trim((string) $value);
    return $raw !== '' ? $raw : 'Pendiente';
}

function wpAppointmentsStatusClass($value)
{
    $status = is_numeric($value) ? intval($value) : null;
    if ($status === 1) {
        return 'status-attended';
    }
    if ($status === 2 || $status === 3) {
        return 'status-dead';
    }

    return 'status-pending';
}

function wpAppointmentsEngagementLabel($value)
{
  $engagement = is_numeric($value) ? intval($value) : null;
  if ($engagement === 1) {
    return 'Bajo';
  }
  if ($engagement === 2) {
    return 'Medio';
  }
  if ($engagement === 3) {
    return 'Alto';
  }

  return 'Sin dato';
}

function wpAppointmentsActionLabel($selectedTab)
{
  if ($selectedTab === 'attended') {
    return 'Atendida';
  }
  if ($selectedTab === 'dead') {
    return 'Muerta';
  }

  return 'Pendiente';
}

$selectedTab = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : 'pending';
$allowedTabs = ['pending', 'attended', 'dead'];
if (!in_array($selectedTab, $allowedTabs, true)) {
    $selectedTab = 'pending';
}

$statusCondition = ' AND c.estatus = 0';
if ($selectedTab === 'attended') {
    $statusCondition = ' AND c.estatus = 1';
} elseif ($selectedTab === 'dead') {
    $statusCondition = ' AND c.estatus IN (2, 3)';
}

$appointments = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'attended' => 0,
    'dead' => 0,
    'planners' => 0,
    'stale_pending' => 0,
    'min_date' => '',
    'max_date' => '',
];

$statsSql = "SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN c.estatus = 0 THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN c.estatus = 1 THEN 1 ELSE 0 END) AS attended_count,
    SUM(CASE WHEN c.estatus IN (2, 3) THEN 1 ELSE 0 END) AS dead_count,
    COUNT(DISTINCT e.wedding_planner_id) AS planner_count,
    SUM(CASE WHEN c.estatus = 0 AND c.fecha < DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS stale_pending_count,
    MIN(c.fecha) AS min_date,
    MAX(c.fecha) AS max_date
FROM calendario c
INNER JOIN eventos_wp e ON e.id = c.idclie
WHERE c.tipo = 1
  AND c.eliminado = 0";
$statsResult = $conn->query($statsSql);
if ($statsResult) {
    $statsRow = $statsResult->fetch_assoc();
    if ($statsRow) {
        $stats['total'] = intval($statsRow['total'] ?? 0);
        $stats['pending'] = intval($statsRow['pending_count'] ?? 0);
        $stats['attended'] = intval($statsRow['attended_count'] ?? 0);
        $stats['dead'] = intval($statsRow['dead_count'] ?? 0);
        $stats['planners'] = intval($statsRow['planner_count'] ?? 0);
        $stats['stale_pending'] = intval($statsRow['stale_pending_count'] ?? 0);
        $stats['min_date'] = (string) ($statsRow['min_date'] ?? '');
        $stats['max_date'] = (string) ($statsRow['max_date'] ?? '');
    }
}

$appointmentsSql = "SELECT
    c.id AS calendario_id,
  c.idusu AS calendario_asesor_id,
    c.fecha,
    c.fecha_cliente,
    c.hora,
    c.hora_cliente,
    c.cliente_city,
    c.cliente_engagement,
    c.estatus,
    e.id AS evento_wp_id,
    e.wedding_planner_id,
  e.id_coordinador,
  e.lugar,
  e.fecha_registro,
  e.novios,
  e.id_asesor,
  e.modo,
  e.tipo_paquete,
  e.id_paquete,
  e.paquete_personalizado,
    wp.empresa_wp,
    wp.full_name,
    wp.campaign_name,
    u.nombre AS vendedor_nombre,
    u.apepat AS vendedor_apepat
FROM calendario c
INNER JOIN eventos_wp e ON e.id = c.idclie
INNER JOIN wedding_planners wp ON wp.id = e.wedding_planner_id
LEFT JOIN usuarios u ON u.id = c.idusu
WHERE c.tipo = 1
  AND c.eliminado = 0" . $statusCondition . "
ORDER BY c.fecha DESC, c.hora DESC, c.id DESC";
$appointmentsResult = $conn->query($appointmentsSql);
if ($appointmentsResult) {
    while ($row = $appointmentsResult->fetch_assoc()) {
        $appointments[] = $row;
    }
}

$agentes = [];
$agentesResult = $conn->query("SELECT id, nombre, apepat FROM usuarios WHERE tipoUsu = 1 ORDER BY nombre ASC, apepat ASC");
if ($agentesResult) {
  while ($agente = $agentesResult->fetch_assoc()) {
    $agentes[] = $agente;
  }
}

$paquetes = [];
$paquetesResult = $conn->query("SELECT id, nombre FROM paquetes ORDER BY nombre ASC");
if ($paquetesResult) {
  while ($paquete = $paquetesResult->fetch_assoc()) {
    $paquetes[] = $paquete;
  }
}

$subtitle = 'Sin rango de fechas';
if ($stats['min_date'] !== '' && $stats['max_date'] !== '') {
    $subtitle = wpAppointmentsFormatDate($stats['min_date']) . ' → ' . wpAppointmentsFormatDate($stats['max_date']);
}

$sectionTitle = 'Citas de wedding planners';
if ($selectedTab === 'attended') {
    $sectionTitle = 'Citas atendidas de wedding planners';
} elseif ($selectedTab === 'dead') {
    $sectionTitle = 'Citas muertas de wedding planners';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Citas Wedding Planners</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #f0f2f5;
    --surface: #ffffff;
    --border: #e4e7ec;
    --text-primary: #111827;
    --text-secondary: #374151;
    --text-muted: #9ca3af;
    --text-sub: #6b7280;
    --accent: #111827;
    --green: #16a34a;
    --green-bg: #dcfce7;
    --orange: #d97706;
    --orange-bg: #fef3c7;
    --orange-border: #fcd34d;
    --red: #dc2626;
    --red-bg: #fee2e2;
    --blue: #2563eb;
    --blue-bg: #dbeafe;
    --yellow-bg: #fffbeb;
    --yellow-border: #fde68a;
    --yellow-text: #92400e;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text-primary);
    min-height: 100vh;
    padding: 24px;
    font-size: 13px;
    line-height: 1.5;
  }

  .page {
   
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
  }

  .top-header {
    padding: 20px 24px 0;
    border-bottom: 1px solid var(--border);
  }

  .header-top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
  }

  .top-header h1 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
    letter-spacing: -0.2px;
    margin-bottom: 3px;
  }

  .top-header .subtitle {
    font-size: 12px;
    color: var(--text-muted);
  }

  .subtitle span {
    color: var(--blue);
  }

  .tabs {
    display: flex;
    gap: 0;
  }

  .tab {
    padding: 10px 18px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-sub);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: color 0.15s, border-color 0.15s;
    user-select: none;
    text-decoration: none;
    display: inline-block;
  }

  .tab:hover { color: var(--text-primary); }

  .tab.active {
    color: var(--text-primary);
    border-bottom-color: var(--text-primary);
    font-weight: 600;
  }

  .stats {
    display: flex;
    gap: 0;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
  }

  .stat-item {
    flex: 1;
    padding-right: 28px;
    border-right: 1px solid var(--border);
    margin-right: 28px;
  }

  .stat-item:last-child {
    border-right: none;
    margin-right: 0;
    padding-right: 0;
  }

  .stat-label {
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 5px;
  }

  .stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    letter-spacing: -0.5px;
    line-height: 1;
  }

  .stat-value.orange { color: var(--orange); }
  .stat-value.green { color: var(--green); }
  .stat-value.red { color: var(--red); }

  .stat-sub {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 3px;
  }

  .alert-banner {
    margin: 0 24px 16px;
    padding: 11px 16px;
    background: var(--yellow-bg);
    border: 1px solid var(--yellow-border);
    border-radius: 8px;
    font-size: 12.5px;
    color: var(--yellow-text);
    line-height: 1.5;
  }

  .table-section {
    margin: 0 24px 24px;
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
  }

  .table-section-header {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    background: #fafafa;
  }

  table {
    width: 100%;
    border-collapse: collapse;
  }

  thead tr {
    border-bottom: 1px solid var(--border);
  }

  th {
    padding: 9px 14px;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--text-muted);
    text-align: left;
    background: #fafafa;
    white-space: nowrap;
  }

  tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background 0.1s;
  }

  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: #f9fafb; }

  td {
    padding: 13px 14px;
    vertical-align: middle;
  }

  .col-id,
  .col-date,
  .col-hour {
    font-size: 12px;
    color: var(--text-muted);
    white-space: nowrap;
  }

  .seller-appointment {
    color: var(--text-primary);
    font-weight: 700;
  }

  .lead-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    display: block;
    margin-bottom: 2px;
  }

  .lead-sub {
    font-size: 11px;
    color: var(--text-muted);
  }

  .asesor-cell {
    display: flex;
    align-items: center;
    gap: 7px;
  }

  .asesor-dot {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--blue);
    color: white;
    font-size: 10px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .asesor-name {
    font-size: 12.5px;
    color: var(--text-secondary);
  }

  .status-pill {
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
    display: inline-block;
  }

  .status-pending {
    background: var(--orange-bg);
    color: var(--orange);
  }

  .status-attended {
    background: var(--green-bg);
    color: var(--green);
  }

  .status-dead {
    background: var(--red-bg);
    color: var(--red);
  }

  .acciones-cell {
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
  }

  .btn {
    padding: 5px 12px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    font-family: inherit;
    transition: opacity 0.15s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .btn:hover { opacity: 0.82; }

  .btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-secondary);
  }

  .btn-dark {
    background: var(--accent);
    color: #fff;
  }

  .btn-danger {
    background: var(--red);
    color: #fff;
  }

  .btn-success {
    background: var(--green);
    color: #fff;
  }

  .modal-backdrop-custom {
    position: fixed;
    inset: 0;
    background: rgba(17, 24, 39, 0.45);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1200;
    padding: 24px;
  }

  .modal-backdrop-custom.is-open {
    display: flex;
  }

  .status-modal {
    width: 100%;
    max-width: 420px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: 0 30px 80px rgba(17, 24, 39, 0.22);
    overflow: hidden;
  }

  .status-modal-header {
    padding: 18px 20px 12px;
    border-bottom: 1px solid var(--border);
  }

  .status-modal-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 4px;
  }

  .status-modal-subtitle {
    font-size: 12px;
    color: var(--text-sub);
  }

  .status-modal-body {
    padding: 18px 20px;
    display: grid;
    gap: 10px;
  }

  .status-option-btn {
    width: 100%;
    justify-content: flex-start;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
  }

  .status-modal-footer {
    padding: 0 20px 20px;
    display: flex;
    justify-content: flex-end;
  }

  .modal-button-stack {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .action-card-btn {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    min-height: 132px;
    border: 1.5px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    background: #fff;
    transition: border-color 0.15s ease, background 0.15s ease, transform 0.15s ease;
    text-align: left;
    font-family: inherit;
  }

  .action-card-btn:hover {
    opacity: 1;
    transform: translateY(-1px);
  }

  .action-card-btn .action-card-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
    display: block;
  }

  .action-card-btn .action-card-desc {
    font-size: 12px;
    line-height: 1.45;
    color: var(--text-sub);
    display: block;
  }

  .action-card-btn.action-card-create:hover {
    border-color: var(--green);
    background: var(--green-bg);
  }

  .action-card-btn.action-card-dead:hover {
    border-color: var(--red);
    background: var(--red-bg);
  }

  .event-modal {
    width: 100%;
    max-width: 760px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: 0 30px 80px rgba(17, 24, 39, 0.22);
    overflow: hidden;
  }

  .event-modal-body {
    padding: 18px 20px;
    display: grid;
    gap: 18px;
    max-height: 80vh;
    overflow-y: auto;
  }

  .event-section {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    display: grid;
    gap: 14px;
  }

  .event-section-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-primary);
  }

  .event-section-subtitle {
    font-size: 12px;
    color: var(--text-sub);
    margin-top: -8px;
  }

  .event-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }

  .event-field {
    display: grid;
    gap: 6px;
  }

  .event-field label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
  }

  .event-field input,
  .event-field select {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 11px 12px;
    font: inherit;
    color: var(--text-primary);
    background: #fff;
  }

  .event-readonly {
    padding: 11px 12px;
    border: 1px dashed var(--border);
    border-radius: 10px;
    font-size: 12px;
    color: var(--text-secondary);
    background: #fafafa;
  }

  .package-list {
    display: grid;
    gap: 10px;
  }

  .package-item {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #fff;
    padding: 12px 14px;
    text-align: left;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
  }

  .package-item.active {
    border-color: var(--blue);
    background: var(--blue-bg);
  }

  .package-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
  }

  .package-label {
    font-size: 11px;
    color: var(--text-sub);
  }

  .package-dot {
    width: 12px;
    height: 12px;
    border-radius: 999px;
    border: 2px solid var(--border);
    background: transparent;
    flex-shrink: 0;
  }

  .package-item.active .package-dot {
    border-color: var(--blue);
    background: var(--blue);
  }

  .empty-state {
    padding: 28px 20px;
    text-align: center;
    color: var(--text-sub);
    font-size: 13px;
  }

  @media (max-width: 980px) {
    .header-top-row,
    .stats {
      flex-direction: column;
      align-items: flex-start;
    }

    .stat-item {
      width: 100%;
      border-right: none;
      border-bottom: 1px solid var(--border);
      margin-right: 0;
      padding-right: 0;
      padding-bottom: 16px;
      margin-bottom: 16px;
    }

    .stat-item:last-child {
      border-bottom: none;
      padding-bottom: 0;
      margin-bottom: 0;
    }

    .table-section {
      overflow-x: auto;
    }

    table {
      min-width: 1080px;
    }

    .event-grid-2 {
      grid-template-columns: 1fr;
    }

    .modal-button-stack {
      grid-template-columns: 1fr;
    }
  }
</style>
</head>
<body>
<div class="page">
  <div class="top-header">
    <div class="header-top-row">
      <div>
        <h1>Citas Wedding Planners</h1>
        <p class="subtitle">Seguimiento de citas exclusivas de wedding planners &middot; <span><?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?></span></p>
      </div>
      <a href="consulta_wp.php" class="btn btn-outline">Volver a Wedding Planners</a>
    </div>

    <div class="tabs">
      <a class="tab <?php echo $selectedTab === 'pending' ? 'active' : ''; ?>" href="post_qualified_wedding_planners.php?status=pending">Pendientes</a>
      <a class="tab <?php echo $selectedTab === 'attended' ? 'active' : ''; ?>" href="post_qualified_wedding_planners.php?status=attended">Atendidos</a>
      <a class="tab <?php echo $selectedTab === 'dead' ? 'active' : ''; ?>" href="post_qualified_wedding_planners.php?status=dead">Muertos</a>
    </div>
  </div>

  <div class="stats">
    <div class="stat-item">
      <div class="stat-label">Total citas</div>
      <div class="stat-value"><?php echo $stats['total']; ?></div>
    </div>
    <div class="stat-item">
      <div class="stat-label">Pendientes</div>
      <div class="stat-value orange"><?php echo $stats['pending']; ?></div>
    </div>
    <div class="stat-item">
      <div class="stat-label">Atendidas</div>
      <div class="stat-value green"><?php echo $stats['attended']; ?></div>
    </div>
    <div class="stat-item">
      <div class="stat-label">Muertas</div>
      <div class="stat-value red"><?php echo $stats['dead']; ?></div>
    </div>
    <div class="stat-item">
      <div class="stat-label">WP únicos</div>
      <div class="stat-value"><?php echo $stats['planners']; ?></div>
    </div>
  </div>

  <div class="alert-banner mt-3">
    <?php if ($stats['stale_pending'] > 0): ?>
      <?php echo $stats['stale_pending']; ?> cita<?php echo $stats['stale_pending'] === 1 ? '' : 's'; ?> pendiente<?php echo $stats['stale_pending'] === 1 ? '' : 's'; ?> lleva<?php echo $stats['stale_pending'] === 1 ? '' : 'n'; ?> más de 7 días sin cierre.
    <?php else: ?>
      No hay citas pendientes con más de 7 días de antigüedad.
    <?php endif; ?>
  </div>

  <div class="table-section">
    <div class="table-section-header"><?php echo htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8'); ?></div>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre del WP</th>
          <th>Hora de la cita (vendedor)</th>
          <th>Hora de la cita (cliente)</th>
          <th>Asesor</th>
          <th>Engagement</th>
          <th>Estatus</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($appointments)): ?>
          <?php foreach ($appointments as $appointment): ?>
            <?php
              $plannerName = wpAppointmentsPlannerName($appointment);
              $advisorName = trim((string) (($appointment['vendedor_nombre'] ?? '') . ' ' . ($appointment['vendedor_apepat'] ?? '')));
              $advisorInitial = $advisorName !== '' ? strtoupper(substr($advisorName, 0, 1)) : '?';
              $clientCity = trim((string) ($appointment['cliente_city'] ?? ''));
              $appointmentPayload = [
                'calendario_id' => intval($appointment['calendario_id'] ?? 0),
                'evento_wp_id' => intval($appointment['evento_wp_id'] ?? 0),
                'wedding_planner_id' => intval($appointment['wedding_planner_id'] ?? 0),
                'id_coordinador' => intval($appointment['id_coordinador'] ?? 0),
                'cliente_city' => (string) ($appointment['cliente_city'] ?? ''),
                'lugar' => (string) ($appointment['lugar'] ?? ''),
                'fecha' => (string) ($appointment['fecha'] ?? ''),
                'fecha_registro' => (string) ($appointment['fecha_registro'] ?? ''),
                'novios' => (string) ($appointment['novios'] ?? ''),
                'id_asesor' => intval($appointment['calendario_asesor_id'] ?? 0) > 0
                  ? intval($appointment['calendario_asesor_id'])
                  : intval($appointment['id_asesor'] ?? 0),
                'modo' => (string) ($appointment['modo'] ?? 'asistencia_post_q'),
                'tipo_paquete' => (string) ($appointment['tipo_paquete'] ?? 'estandar'),
                'id_paquete' => intval($appointment['id_paquete'] ?? 0),
                'planner_name' => $plannerName
              ];
            ?>
            <tr>
              <td class="col-id"><?php echo intval($appointment['calendario_id'] ?? 0); ?></td>
              <td>
                <span class="lead-name"><?php echo htmlspecialchars($plannerName, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="lead-sub">WP #<?php echo intval($appointment['wedding_planner_id'] ?? 0); ?> · Evento #<?php echo intval($appointment['evento_wp_id'] ?? 0); ?></span>
              </td>
              <td class="col-hour seller-appointment"><?php echo htmlspecialchars(wpAppointmentsFormatDateTime($appointment['fecha'] ?? '', $appointment['hora'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <span class="lead-name"><?php echo htmlspecialchars(wpAppointmentsFormatDateTime($appointment['fecha_cliente'] ?? '', $appointment['hora_cliente'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="lead-sub"><?php echo htmlspecialchars($clientCity !== '' ? $clientCity : 'Sin ciudad', ENT_QUOTES, 'UTF-8'); ?></span>
              </td>
              <td>
                <div class="asesor-cell">
                  <div class="asesor-dot"><?php echo htmlspecialchars($advisorInitial, ENT_QUOTES, 'UTF-8'); ?></div>
                  <span class="asesor-name"><?php echo htmlspecialchars($advisorName !== '' ? $advisorName : 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
              </td>
              <td><?php echo htmlspecialchars(wpAppointmentsEngagementLabel($appointment['cliente_engagement'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><span class="status-pill <?php echo wpAppointmentsStatusClass($appointment['estatus'] ?? null); ?>"><?php echo htmlspecialchars(wpAppointmentsStatusLabel($appointment['estatus'] ?? null), ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td>
                <div class="acciones-cell">
                  <a class="btn btn-outline" href="planner_profile.php?id=<?php echo intval($appointment['wedding_planner_id'] ?? 0); ?>">Ver detalles</a>
                  <?php if ($selectedTab === 'pending'): ?>
                    <button
                      type="button"
                      class="btn btn-dark js-open-status-modal"
                      data-calendario-id="<?php echo intval($appointment['calendario_id'] ?? 0); ?>"
                      data-planner-name="<?php echo htmlspecialchars($plannerName, ENT_QUOTES, 'UTF-8'); ?>">Cambiar estatus</button>
                  <?php elseif ($selectedTab === 'attended'): ?>
                    <button
                      type="button"
                      class="btn btn-dark js-open-attended-actions"
                      data-appointment="<?php echo htmlspecialchars(json_encode($appointmentPayload), ENT_QUOTES, 'UTF-8'); ?>">Cambiar de estatus</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" class="empty-state">No hay citas de wedding planners para este filtro.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-backdrop-custom" id="wpStatusModal" aria-hidden="true">
  <div class="status-modal" role="dialog" aria-modal="true" aria-labelledby="wpStatusModalTitle">
    <div class="status-modal-header">
      <div class="status-modal-title" id="wpStatusModalTitle">Cambiar estatus</div>
      <div class="status-modal-subtitle" id="wpStatusModalSubtitle">Selecciona el nuevo estatus de la cita.</div>
    </div>
    <div class="status-modal-body">
      <button type="button" class="btn btn-success status-option-btn js-status-option" data-estatus="1">Pasar a atendido</button>
      <button type="button" class="btn btn-danger status-option-btn js-status-option" data-estatus="3">Pasar a muerto</button>
    </div>
    <div class="status-modal-footer">
      <button type="button" class="btn btn-outline" id="wpStatusModalCancel">Cancelar</button>
    </div>
  </div>
</div>

<div class="modal-backdrop-custom" id="wpAttendedActionModal" aria-hidden="true">
  <div class="status-modal" role="dialog" aria-modal="true" aria-labelledby="wpAttendedActionTitle">
    <div class="status-modal-header">
      <div class="status-modal-title" id="wpAttendedActionTitle">Cambiar de estatus</div>
      <div class="status-modal-subtitle" id="wpAttendedActionSubtitle">Selecciona la siguiente acción para esta cita atendida.</div>
    </div>
    <div class="status-modal-body modal-button-stack">
      <button type="button" class="action-card-btn action-card-create" id="wpAttendedCreateEventBtn">
        <span class="action-card-title">Crear evento</span>
        <span class="action-card-desc">Completa los datos del evento y genera el registro que aparecerá en post leads.</span>
      </button>
      <button type="button" class="action-card-btn action-card-dead" id="wpAttendedDeadBtn">
        <span class="action-card-title">Pasar a muerto</span>
        <span class="action-card-desc">Cierra esta cita atendida como muerta sin crear un nuevo post lead.</span>
      </button>
    </div>
    <div class="status-modal-footer">
      <button type="button" class="btn btn-outline" id="wpAttendedActionCancel">Cancelar</button>
    </div>
  </div>
</div>

<div class="modal-backdrop-custom" id="wpEventModal" aria-hidden="true">
  <div class="event-modal" role="dialog" aria-modal="true" aria-labelledby="wpEventModalTitle">
    <div class="status-modal-header">
      <div class="status-modal-title" id="wpEventModalTitle">Crear evento</div>
      <div class="status-modal-subtitle" id="wpEventModalSubtitle">Completa la información del evento para generar el post lead.</div>
    </div>
    <div class="event-modal-body">
      <form id="wpAttendedEventForm">
        <input type="hidden" id="evtAttendedCalendarioId" name="calendario_id" value="0">
        <input type="hidden" id="evtAttendedEventId" name="evento_wp_id" value="0">
        <input type="hidden" id="evtAttendedWpId" name="wedding_planner_id" value="0">
        <input type="hidden" id="evtAttendedModo" name="modo" value="asistencia_post_q">
        <input type="hidden" id="evtAttendedTipoPaquete" name="tipo_paquete" value="estandar">
        <input type="hidden" id="evtAttendedPaquetePersonalizado" name="paquete_personalizado" value="">

        <div class="event-section">
          <div class="event-section-title">Datos principales</div>
          <div class="event-section-subtitle">Se usará el mismo Wedding Planner de la cita atendida.</div>
          <div class="event-field">
            <label>Wedding Planner</label>
            <div class="event-readonly" id="evtAttendedPlannerLabel">Sin seleccionar</div>
          </div>
          <div class="event-grid-2">
            <div class="event-field">
              <label for="evtAttendedCoordinador">Coordinador</label>
              <select id="evtAttendedCoordinador" name="id_coordinador">
                <option value="">Seleccione un Wedding Planner primero...</option>
              </select>
            </div>
            <div class="event-field">
              <label for="evtAttendedAsesor">Asesor</label>
              <select id="evtAttendedAsesor" name="id_asesor" required>
                <option value="">Seleccionar asesor</option>
                <?php foreach ($agentes as $ag): ?>
                  <option value="<?php echo intval($ag['id']); ?>"><?php echo htmlspecialchars(trim(($ag['nombre'] ?? '') . ' ' . ($ag['apepat'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="event-field">
            <label for="evtAttendedNovios">Nombre de los novios</label>
            <input type="text" id="evtAttendedNovios" name="novios" required>
          </div>
          <div class="event-field">
            <label for="evtAttendedCity">Ciudad de los novios</label>
            <input type="text" id="evtAttendedCity" name="city" required>
          </div>
        </div>

        <div class="event-section">
          <div class="event-section-title">Fecha y lugar</div>
          <div class="event-grid-2">
            <div class="event-field">
              <label for="evtAttendedLugar">Lugar del evento</label>
              <input type="text" id="evtAttendedLugar" name="lugar">
            </div>
            <div class="event-field">
              <label for="evtAttendedFecha">Fecha del evento</label>
              <input type="datetime-local" id="evtAttendedFecha" name="fecha">
            </div>
          </div>
          <div class="event-field">
            <label for="evtAttendedFechaRegistro">Fecha de registro</label>
            <input type="datetime-local" id="evtAttendedFechaRegistro" name="fecha_registro" required>
          </div>
        </div>

        <div class="event-section">
          <div class="event-section-title">Paquete</div>
          <div class="package-list" id="evtAttendedPaqueteList">
            <?php foreach ($paquetes as $pq): ?>
              <button type="button" class="package-item" data-paq-id="<?php echo intval($pq['id']); ?>">
                <div>
                  <div class="package-name"><?php echo htmlspecialchars($pq['nombre'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="package-label">Paquete disponible en el catálogo actual</div>
                </div>
                <div class="package-dot"></div>
              </button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" id="evtAttendedPaqueteId" name="id_paquete" value="">
        </div>
      </form>
    </div>
    <div class="status-modal-footer">
      <button type="button" class="btn btn-outline" id="wpEventModalCancel">Cancelar</button>
      <button type="button" class="btn btn-dark" id="wpEventModalSave">Guardar evento</button>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  (function () {
    var pendingModalEl = document.getElementById('wpStatusModal');
    if (!pendingModalEl) {
      return;
    }

    var pendingSubtitleEl = document.getElementById('wpStatusModalSubtitle');
    var pendingCancelBtn = document.getElementById('wpStatusModalCancel');
    var attendedActionModalEl = document.getElementById('wpAttendedActionModal');
    var attendedActionSubtitleEl = document.getElementById('wpAttendedActionSubtitle');
    var attendedActionCancelBtn = document.getElementById('wpAttendedActionCancel');
    var attendedCreateEventBtn = document.getElementById('wpAttendedCreateEventBtn');
    var attendedDeadBtn = document.getElementById('wpAttendedDeadBtn');
    var eventModalEl = document.getElementById('wpEventModal');
    var eventModalCancelBtn = document.getElementById('wpEventModalCancel');
    var eventModalSaveBtn = document.getElementById('wpEventModalSave');
    var eventFormEl = document.getElementById('wpAttendedEventForm');
    var plannerLabelEl = document.getElementById('evtAttendedPlannerLabel');
    var coordinadorSelectEl = document.getElementById('evtAttendedCoordinador');
    var asesorSelectEl = document.getElementById('evtAttendedAsesor');
    var noviosInputEl = document.getElementById('evtAttendedNovios');
    var cityInputEl = document.getElementById('evtAttendedCity');
    var lugarInputEl = document.getElementById('evtAttendedLugar');
    var fechaInputEl = document.getElementById('evtAttendedFecha');
    var fechaRegistroInputEl = document.getElementById('evtAttendedFechaRegistro');
    var calendarioIdInputEl = document.getElementById('evtAttendedCalendarioId');
    var eventIdInputEl = document.getElementById('evtAttendedEventId');
    var weddingPlannerIdInputEl = document.getElementById('evtAttendedWpId');
    var modoInputEl = document.getElementById('evtAttendedModo');
    var paqueteTipoInputEl = document.getElementById('evtAttendedTipoPaquete');
    var paqueteIdInputEl = document.getElementById('evtAttendedPaqueteId');
    var currentPendingCalendarioId = 0;
    var currentAttendedAppointment = null;
    var pendingBusy = false;
    var eventBusy = false;

    function setModalOpen(modalEl, isOpen) {
      if (!modalEl) {
        return;
      }
      if (isOpen) {
        modalEl.classList.add('is-open');
        modalEl.setAttribute('aria-hidden', 'false');
      } else {
        modalEl.classList.remove('is-open');
        modalEl.setAttribute('aria-hidden', 'true');
      }
    }

    function openPendingModal(calendarioId, plannerName) {
      currentPendingCalendarioId = calendarioId;
      pendingSubtitleEl.textContent = plannerName
        ? ('Selecciona el nuevo estatus para la cita de ' + plannerName + '.')
        : 'Selecciona el nuevo estatus de la cita.';
      setModalOpen(pendingModalEl, true);
    }

    function closePendingModal() {
      if (pendingBusy) {
        return;
      }
      setModalOpen(pendingModalEl, false);
      currentPendingCalendarioId = 0;
    }

    function closeAttendedActionModal() {
      setModalOpen(attendedActionModalEl, false);
      currentAttendedAppointment = null;
    }

    function closeEventModal() {
      if (eventBusy) {
        return;
      }
      setModalOpen(eventModalEl, false);
    }

    function resetEventModalState() {
      if (eventFormEl) {
        eventFormEl.reset();
      }
      calendarioIdInputEl.value = '0';
      eventIdInputEl.value = '0';
      weddingPlannerIdInputEl.value = '0';
      modoInputEl.value = 'asistencia_post_q';
      paqueteTipoInputEl.value = 'estandar';
      plannerLabelEl.textContent = 'Sin seleccionar';
      coordinadorSelectEl.innerHTML = '<option value="">Seleccione un Wedding Planner primero...</option>';
      selectPackage('');
      currentAttendedAppointment = null;
    }

    function forceCloseEventModal() {
      eventBusy = false;
      setModalOpen(eventModalEl, false);
      resetEventModalState();
    }

    function parseAppointmentPayload(rawValue) {
      if (!rawValue) {
        return null;
      }

      try {
        return JSON.parse(rawValue);
      } catch (error) {
        return null;
      }
    }

    function normalizeDateTimeForInput(rawValue) {
      if (!rawValue) {
        return '';
      }

      var normalized = rawValue.toString().replace(' ', 'T');
      var date = new Date(normalized);
      if (isNaN(date.getTime())) {
        return '';
      }

      var local = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
      return local.toISOString().slice(0, 16);
    }

    function setCurrentDateTimeInput(inputEl) {
      if (!inputEl) {
        return;
      }
      var now = new Date();
      var local = new Date(now.getTime() - (now.getTimezoneOffset() * 60000));
      inputEl.value = local.toISOString().slice(0, 16);
    }

    function selectPackage(packageId) {
      var buttons = document.querySelectorAll('#evtAttendedPaqueteList .package-item');
      buttons.forEach(function (button) {
        var matches = (button.getAttribute('data-paq-id') || '') === String(packageId || '');
        button.classList.toggle('active', matches);
      });
      paqueteIdInputEl.value = packageId || '';
    }

    function loadCoordinadores(weddingPlannerId, selectedCoordId) {
      coordinadorSelectEl.innerHTML = '<option value="">Cargando...</option>';

      if (!weddingPlannerId) {
        coordinadorSelectEl.innerHTML = '<option value="">Seleccione un Wedding Planner primero...</option>';
        return;
      }

      $.ajax({
        url: 'obtener_coordinadores_wp.php',
        type: 'GET',
        dataType: 'json',
        data: { id_wp: weddingPlannerId },
        success: function (resp) {
          coordinadorSelectEl.innerHTML = '';
          if (resp && resp.success && resp.coordinadores && resp.coordinadores.length) {
            coordinadorSelectEl.insertAdjacentHTML('beforeend', '<option value="">Seleccionar...</option>');
            resp.coordinadores.forEach(function (item) {
              var label = (item.nombre || '').trim() || ('Coordinador #' + (item.id || ''));
              var option = document.createElement('option');
              option.value = item.id;
              option.textContent = label;
              coordinadorSelectEl.appendChild(option);
            });
            if (selectedCoordId) {
              coordinadorSelectEl.value = String(selectedCoordId);
            }
            return;
          }

          coordinadorSelectEl.innerHTML = '<option value="">No hay coordinadores disponibles</option>';
        },
        error: function () {
          coordinadorSelectEl.innerHTML = '<option value="">Error al cargar coordinadores</option>';
        }
      });
    }

    function openAttendedActionModal(appointment) {
      if (!appointment || !appointment.calendario_id) {
        return;
      }

      currentAttendedAppointment = appointment;
      attendedActionSubtitleEl.textContent = appointment.planner_name
        ? ('Selecciona la siguiente acción para la cita de ' + appointment.planner_name + '.')
        : 'Selecciona la siguiente acción para esta cita atendida.';
      setModalOpen(attendedActionModalEl, true);
    }

    function openEventModal(appointment) {
      if (!appointment) {
        return;
      }

      calendarioIdInputEl.value = appointment.calendario_id || 0;
      eventIdInputEl.value = appointment.evento_wp_id || 0;
      weddingPlannerIdInputEl.value = appointment.wedding_planner_id || 0;
      modoInputEl.value = appointment.modo || 'asistencia_post_q';
      paqueteTipoInputEl.value = 'estandar';
      plannerLabelEl.textContent = appointment.planner_name || ('WP #' + (appointment.wedding_planner_id || ''));
      noviosInputEl.value = appointment.novios || '';
      cityInputEl.value = appointment.cliente_city || '';
      lugarInputEl.value = appointment.lugar || '';
      fechaInputEl.value = normalizeDateTimeForInput(appointment.fecha || '');
      fechaRegistroInputEl.value = normalizeDateTimeForInput(appointment.fecha_registro || '');
      if (!fechaRegistroInputEl.value) {
        setCurrentDateTimeInput(fechaRegistroInputEl);
      }
      asesorSelectEl.value = appointment.id_asesor ? String(appointment.id_asesor) : '';
      selectPackage(appointment.id_paquete || '');
      loadCoordinadores(appointment.wedding_planner_id || 0, appointment.id_coordinador || 0);
      setModalOpen(eventModalEl, true);
    }

    function setEventButtonsDisabled(disabled) {
      eventBusy = disabled;
      var buttons = eventModalEl.querySelectorAll('button');
      buttons.forEach(function (button) {
        button.disabled = disabled;
      });
    }

    function updateAppointmentStatus(calendarioId, estatus, onSuccess) {
      if (!calendarioId || pendingBusy) {
        return;
      }

      pendingBusy = true;
      var buttons = pendingModalEl.querySelectorAll('button');
      buttons.forEach(function (button) {
        button.disabled = true;
      });

      $.ajax({
        url: 'actualizar_estatus_cita_wp.php',
        type: 'POST',
        dataType: 'json',
        data: {
          calendario_id: calendarioId,
          estatus: estatus
        },
        success: function (resp) {
          if (resp && resp.success) {
            Swal.fire({
              icon: 'success',
              title: 'Estatus actualizado',
              text: resp.message || 'La cita se actualizó correctamente.'
            }).then(function () {
              if (typeof onSuccess === 'function') {
                onSuccess(resp);
                return;
              }
              window.location.reload();
            });
            return;
          }

          Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo actualizar el estatus.', 'error');
        },
        error: function (xhr) {
          var message = 'No se pudo actualizar el estatus.';
          if (xhr && xhr.responseText) {
            try {
              var parsed = JSON.parse(xhr.responseText);
              if (parsed && parsed.message) {
                message = parsed.message;
              }
            } catch (e) {}
          }
          Swal.fire('Error', message, 'error');
        },
        complete: function () {
          pendingBusy = false;
          buttons.forEach(function (button) {
            button.disabled = false;
          });
          closePendingModal();
        }
      });
    }

    function saveEventFromAttendedAppointment() {
      var calendarioId = parseInt(calendarioIdInputEl.value || '0', 10);
      var coordinadorId = parseInt(coordinadorSelectEl.value || '0', 10);
      var asesorId = parseInt(asesorSelectEl.value || '0', 10);
      var paqueteId = parseInt(paqueteIdInputEl.value || '0', 10);
      var novios = (noviosInputEl.value || '').trim();
      var city = (cityInputEl.value || '').trim();
      var fechaRegistro = (fechaRegistroInputEl.value || '').trim();

      if (!calendarioId) {
        Swal.fire('Error', 'No se pudo identificar la cita.', 'error');
        return;
      }
      if (!novios) {
        Swal.fire('Error', 'Ingrese el nombre de los novios.', 'warning');
        return;
      }
      if (!city) {
        Swal.fire('Error', 'Ingrese la ciudad de los novios.', 'warning');
        return;
      }
      if (!asesorId) {
        Swal.fire('Error', 'Seleccione un asesor.', 'warning');
        return;
      }
      if (!fechaRegistro) {
        Swal.fire('Error', 'Seleccione la fecha de registro.', 'warning');
        return;
      }
      if (!paqueteId) {
        Swal.fire('Error', 'Seleccione un paquete.', 'warning');
        return;
      }

      setEventButtonsDisabled(true);
      eventModalSaveBtn.textContent = 'Guardando...';

      $.ajax({
        url: 'guardar_evento_desde_cita_wp.php',
        type: 'POST',
        dataType: 'json',
        data: {
          calendario_id: calendarioId,
          id_coordinador: coordinadorId,
          novios: novios,
          city: city,
          id_asesor: asesorId,
          fecha_registro: fechaRegistro,
          lugar: lugarInputEl.value || '',
          fecha: fechaInputEl.value || '',
          modo: modoInputEl.value || 'asistencia_post_q',
          tipo_paquete: 'estandar',
          id_paquete: paqueteId,
          paquete_personalizado: ''
        },
        success: function (resp) {
          if (resp && resp.success) {
            forceCloseEventModal();
            Swal.fire({
              icon: 'success',
              title: 'Evento guardado',
              text: resp.message || 'El evento se guardó correctamente.'
            }).then(function () {
              window.location.reload();
            });
            return;
          }
          var responseMessage = (resp && resp.message) ? resp.message : 'No se pudo guardar el evento.';

          Swal.fire('Error', responseMessage, 'error');
        },
        error: function (xhr) {
          var message = 'No se pudo guardar el evento.';
          if (xhr && xhr.responseText) {
            try {
              var parsed = JSON.parse(xhr.responseText);
              if (parsed && parsed.message) {
                message = parsed.message;
              }
            } catch (error) {}
          }
          Swal.fire('Error', message, 'error');
        },
        complete: function () {
          setEventButtonsDisabled(false);
          eventModalSaveBtn.textContent = 'Guardar evento';
        }
      });
    }

    document.addEventListener('click', function (event) {
      var openBtn = event.target.closest('.js-open-status-modal');
      if (openBtn) {
        openPendingModal(
          parseInt(openBtn.getAttribute('data-calendario-id') || '0', 10),
          openBtn.getAttribute('data-planner-name') || ''
        );
        return;
      }

      var openAttendedBtn = event.target.closest('.js-open-attended-actions');
      if (openAttendedBtn) {
        openAttendedActionModal(parseAppointmentPayload(openAttendedBtn.getAttribute('data-appointment')));
        return;
      }

      if (event.target === pendingModalEl || event.target === pendingCancelBtn) {
        closePendingModal();
        return;
      }

      var statusBtn = event.target.closest('.js-status-option');
      if (statusBtn) {
        updateAppointmentStatus(currentPendingCalendarioId, parseInt(statusBtn.getAttribute('data-estatus') || '0', 10));
        return;
      }

      if (event.target === attendedActionModalEl || event.target === attendedActionCancelBtn) {
        closeAttendedActionModal();
        return;
      }

      var attendedCreateBtn = event.target.closest('#wpAttendedCreateEventBtn');
      if (attendedCreateBtn) {
        var selectedAppointment = currentAttendedAppointment;
        closeAttendedActionModal();
        openEventModal(selectedAppointment);
        return;
      }

      var attendedDeadActionBtn = event.target.closest('#wpAttendedDeadBtn');
      if (attendedDeadActionBtn) {
        if (!currentAttendedAppointment || !currentAttendedAppointment.calendario_id) {
          return;
        }
        var calendarId = parseInt(currentAttendedAppointment.calendario_id || '0', 10);
        closeAttendedActionModal();
        updateAppointmentStatus(calendarId, 3);
        return;
      }

      if (event.target.closest('#evtAttendedPaqueteList .package-item')) {
        var packageButton = event.target.closest('#evtAttendedPaqueteList .package-item');
        selectPackage(packageButton.getAttribute('data-paq-id') || '');
        return;
      }

      var eventModalCancelAction = event.target.closest('#wpEventModalCancel');
      if (event.target === eventModalEl || eventModalCancelAction) {
        closeEventModal();
        return;
      }

      var eventModalSaveAction = event.target.closest('#wpEventModalSave');
      if (eventModalSaveAction) {
        saveEventFromAttendedAppointment();
        return;
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') {
        return;
      }

      if (pendingModalEl.classList.contains('is-open')) {
        closePendingModal();
      }
      if (attendedActionModalEl && attendedActionModalEl.classList.contains('is-open')) {
        closeAttendedActionModal();
      }
      if (eventModalEl && eventModalEl.classList.contains('is-open')) {
        closeEventModal();
      }
    });
  })();
</script>
</body>
</html>