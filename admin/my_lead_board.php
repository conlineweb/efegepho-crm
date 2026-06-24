<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';

function resolveLeadStatus($tableName, $contactFormRow, $appointmentRow, $leadRow = null)
{
    if (strcasecmp((string) $tableName, 'wp_citas_leads') === 0) {
        $directCandidates = [
            $leadRow['estatus_cita'] ?? null,
            $leadRow['estatus'] ?? null,
            $leadRow['lead_status'] ?? null,
            $appointmentRow['estatus'] ?? null,
        ];

        foreach ($directCandidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    if (isset($contactFormRow['cliente']) && intval($contactFormRow['cliente']) === 1) {
        return 'cliente';
    }

    if ($appointmentRow === null || !isset($appointmentRow['estatus'])) {
        return 'lead';
    }

    if (
        strcasecmp((string) $tableName, 'wedding_planners') === 0 ||
        strcasecmp((string) $tableName, 'eventos_wp') === 0
    ) {
        return 'agendado';
    }

    $rawStatus = $appointmentRow['estatus'];
    $intStatus = is_numeric($rawStatus) ? intval($rawStatus) : null;

    if ($intStatus === 1) {
        return 'atendido';
    }
    if ($intStatus === 3) {
        return 'muerto';
    }
    if ($intStatus === 0) {
        return 'agendado';
    }
    if ($intStatus === 2) {
        return 'fantasma';
    }

    return is_string($rawStatus) && trim($rawStatus) !== '' ? $rawStatus : 'agendado';
}

function originLabelFromValue($value)
{
    $raw = trim((string) $value);
    $map = [
        '1' => 'Wedding Planner',
        '2' => 'Comunidad',
        '3' => 'New Market'
    ];

    if ($raw === '') {
        return 'Sin dato';
    }

    return $map[$raw] ?? $raw;
}

function originBadgeClass($origin)
{
    $normalized = strtolower(trim((string) $origin));
    if ($normalized === 'new market') {
        return 'origin-badge origin-newmarket';
    }
    if ($normalized === 'comunidad') {
        return 'origin-badge origin-community';
    }
    if ($normalized === 'wedding planner') {
        return 'origin-badge origin-planner';
    }

    return 'origin-badge origin-default';
}

function statusLabel($status)
{
    $normalized = strtolower(trim((string) $status));
    if ($normalized === 'lead' || $normalized === '') {
        return 'Lead';
    }
    if ($normalized === 'agendado') {
        return 'Agendado';
    }
    if ($normalized === 'atendido') {
        return 'Atendido';
    }
    if ($normalized === 'muerto') {
        return 'Muerto';
    }
    if ($normalized === 'fantasma') {
        return 'Fantasma';
    }
    if ($normalized === 'cliente') {
        return 'Cliente';
    }

    return ucfirst($normalized);
}

function statusBadgeClass($status)
{
    $normalized = strtolower(trim((string) $status));

    if ($normalized === 'lead' || $normalized === '') {
        return 'status status-pending';
    }
    if ($normalized === 'atendido') {
        return 'status status-attended';
    }
    if ($normalized === 'agendado') {
        return 'status status-scheduled';
    }
    if ($normalized === 'muerto') {
        return 'status status-pending';
    }
    if ($normalized === 'fantasma') {
        return 'status status-pending';
    }
    if ($normalized === 'cliente') {
        return 'status status-closed';
    }

    return 'status status-pending';
}

function getVendedorInitial($nombre)
{
    $nombre = trim((string) $nombre);
    if ($nombre === '') {
        return 'S';
    }

    return mb_strtoupper(mb_substr($nombre, 0, 1, 'UTF-8'), 'UTF-8');
}

function getVendedorColor($nombre)
{
    $colors = [
        'B' => '#3B82F6',
        'A' => '#10B981',
        'E' => '#8B5CF6',
        'L' => '#F59E0B',
        'M' => '#EC4899',
        'C' => '#06B6D4',
        'D' => '#EF4444',
    ];
    $initial = getVendedorInitial($nombre);

    return $colors[$initial] ?? '#64748B';
}

$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$view = isset($_GET['view']) ? trim((string) $_GET['view']) : 'active';
$segment = isset($_GET['segment']) ? trim((string) $_GET['segment']) : 'all';

if (!in_array($view, ['active', 'dead'], true)) {
    $view = 'active';
}
if (!in_array($segment, ['all', 'pending', 'attended'], true)) {
    $segment = 'all';
}
if ($view === 'dead') {
    $segment = 'all';
}

$filterVendedor = isset($_GET['vendedor']) ? intval($_GET['vendedor']) : 0;
$filterEstatus  = isset($_GET['estatus'])  ? trim((string) $_GET['estatus'])  : '';
$allowedEstatus = ['', 'agendado', 'atendido', 'muerto', 'fantasma'];
if (!in_array($filterEstatus, $allowedEstatus, true)) {
    $filterEstatus = '';
}

// Si el usuario es vendedora (tipoUsu=1), forzar que solo vea sus propios leads.
// Excepción: usuario 20 administra esta vista y ve todos los leads (como admin).
const MLB_VIEW_ADMIN_USER_IDS = [20];
$mlbUserId = intval($_SESSION['uid'] ?? 0);
$mlbViewAsAdmin = in_array($mlbUserId, MLB_VIEW_ADMIN_USER_IDS, true);
$isVendedoraLocked = ($tipoUsuario == '1' && !$mlbViewAsAdmin);
if ($isVendedoraLocked) {
    $filterVendedor = $mlbUserId;
}

function buildLeadBoardUrl($overrides = [])
{
    $params = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    $query = http_build_query($params);
    return 'my_lead_board.php' . ($query !== '' ? ('?' . $query) : '');
}

$tablas = [];
$resultTablas = $conn->query("SELECT nombre FROM tablas_leads WHERE tipo != 2 OR nombre = 'wp_citas_leads' ORDER BY nombre");
if ($resultTablas && $resultTablas->num_rows > 0) {
    while ($row = $resultTablas->fetch_assoc()) {
        $tablas[] = $row['nombre'];
    }
}

$allLeads = [];
foreach ($tablas as $tableName) {
    $checkTable = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        continue;
    }

    $columns = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tableName) . "`");
    if ($columnsResult) {
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
    }

    $whereParts = [];
    if (in_array('descartado', $columns, true)) {
        $whereParts[] = "(descartado = 0 OR descartado IS NULL)";
    }

    if ($searchQuery !== '') {
        $searchCols = [];
        foreach (['full_name', 'email', 'phone', 'campaign_name', 'form_name', 'platform'] as $colName) {
            if (in_array($colName, $columns, true)) {
                $searchCols[] = "IFNULL(`$colName`, '')";
            }
        }
        if (!empty($searchCols)) {
            $q = $conn->real_escape_string(mb_strtolower($searchQuery, 'UTF-8'));
            $whereParts[] = "LOWER(CONCAT_WS(' ', " . implode(', ', $searchCols) . ")) LIKE '%" . $q . "%'";
        }
    }

    $whereSql = !empty($whereParts) ? implode(' AND ', $whereParts) : '1=1';
    $orderCol = in_array('created_time', $columns, true) ? 'created_time' : (in_array('id', $columns, true) ? 'id' : null);
    $orderSql = $orderCol ? " ORDER BY `$orderCol` DESC" : '';

    $sqlLeads = "SELECT *, '" . $conn->real_escape_string($tableName) . "' as tabla_origen FROM `" . $conn->real_escape_string($tableName) . "` WHERE " . $whereSql . $orderSql;
    $resultLeads = $conn->query($sqlLeads);

    if ($resultLeads && $resultLeads->num_rows > 0) {
        while ($lead = $resultLeads->fetch_assoc()) {
            $allLeads[] = $lead;
        }
    }
}

usort($allLeads, function ($a, $b) {
    $ta = (!empty($a['created_time']) && strtotime($a['created_time']) !== false) ? strtotime($a['created_time']) : 0;
    $tb = (!empty($b['created_time']) && strtotime($b['created_time']) !== false) ? strtotime($b['created_time']) : 0;
    if ($ta === $tb) {
        return 0;
    }
    return ($ta > $tb) ? -1 : 1;
});

$leadIdsByTable = [];
foreach ($allLeads as $lead) {
    $t = $lead['tabla_origen'] ?? '';
    $id = intval($lead['id'] ?? 0);
    if ($t === '' || $id <= 0) {
        continue;
    }
    if (!isset($leadIdsByTable[$t])) {
        $leadIdsByTable[$t] = [];
    }
    $leadIdsByTable[$t][] = $id;
}

$contactFormByLead = [];
$cfIds = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '') {
        continue;
    }

    $sql = "SELECT id, original_lead_id, cliente, how_did_you_meet, engagement FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")";
    $resCf = $conn->query($sql);

    if ($resCf) {
        while ($row = $resCf->fetch_assoc()) {
            $key = $t . '|' . intval($row['original_lead_id']);
            $contactFormByLead[$key] = [
                'cf_id' => intval($row['id']),
                'cliente' => isset($row['cliente']) ? intval($row['cliente']) : 0,
                'how_did_you_meet' => $row['how_did_you_meet'] ?? null,
                'engagement' => $row['engagement'] ?? null,
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
            if ($idclie <= 0) {
                continue;
            }

            if (!isset($appointmentsByCFId[$idclie])) {
                $appointmentsByCFId[$idclie] = $ar;
                continue;
            }

            $prev = $appointmentsByCFId[$idclie];
            $replace = false;
            if (!empty($ar['fecha']) && !empty($prev['fecha'])) {
                $t1 = strtotime($ar['fecha'] . ' ' . ($ar['hora'] ?? '')) ?: 0;
                $t2 = strtotime($prev['fecha'] . ' ' . ($prev['hora'] ?? '')) ?: 0;
                if ($t1 > $t2) {
                    $replace = true;
                }
            } elseif (!empty($ar['id']) && !empty($prev['id'])) {
                if (intval($ar['id']) > intval($prev['id'])) {
                    $replace = true;
                }
            }

            if ($replace) {
                $appointmentsByCFId[$idclie] = $ar;
            }
        }
    }
}

$calendarioIds = [];
foreach ($allLeads as $leadRow) {
    $calendarioId = intval($leadRow['id_calendario'] ?? 0);
    if ($calendarioId > 0) {
        $calendarioIds[$calendarioId] = true;
    }
}

$appointmentsByCalendarioId = [];
if (!empty($calendarioIds)) {
    $calList = implode(',', array_map('intval', array_keys($calendarioIds)));
    $calRes = $conn->query('SELECT id, idusu FROM calendario WHERE id IN (' . $calList . ')');
    if ($calRes) {
        while ($calRow = $calRes->fetch_assoc()) {
            $calId = intval($calRow['id'] ?? 0);
            if ($calId > 0) {
                $appointmentsByCalendarioId[$calId] = $calRow;
            }
        }
    }
}

// Fetch all agents (vendedoras) for name lookup and filter dropdown
$agents = [];
$resAgents = $conn->query("SELECT id, nombre, apepat FROM usuarios WHERE tipoUsu IN (0,1) ORDER BY nombre, apepat");
if ($resAgents) {
    while ($a = $resAgents->fetch_assoc()) {
        $agentId = intval($a['id'] ?? 0);
        if ($agentId <= 0) {
            continue;
        }
        $agents[$agentId] = [
            'full' => trim(($a['nombre'] ?? '') . ' ' . ($a['apepat'] ?? '')),
            'nombre' => trim((string) ($a['nombre'] ?? '')),
        ];
    }
}

// Fetch last interaction date per (tabla_origen, lead_id)
$lastInteractionMap = [];
if (!empty($leadIdsByTable)) {
    $unionParts = [];
    foreach ($leadIdsByTable as $t => $ids) {
        $safeTable = $conn->real_escape_string($t);
        $idsList = implode(',', array_map('intval', $ids));
        if ($idsList === '') continue;
        $unionParts[] = "SELECT tabla_origen, lead_id, COALESCE(interaction_date, DATE(created_at)) AS last_date FROM lead_interactions WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND lead_id IN (" . $idsList . ")";
    }
    if (!empty($unionParts)) {
        $unionSql = "SELECT tabla_origen, lead_id, MAX(last_date) AS last_date FROM (" . implode(' UNION ALL ', $unionParts) . ") AS li_union GROUP BY tabla_origen, lead_id";
        $liRes = $conn->query($unionSql);
        if ($liRes) {
            while ($liRow = $liRes->fetch_assoc()) {
                $key = strtolower($liRow['tabla_origen']) . '|' . intval($liRow['lead_id']);
                $lastInteractionMap[$key] = $liRow['last_date'];
            }
        }
    }
}

function getEngagementLabelWithEmoji($engRaw)
{
    $raw = trim((string) $engRaw);
    if ($raw === '' || $raw === '0') return '';
    $normalized = mb_strtolower($raw, 'UTF-8');
    if ($normalized === '1' || $normalized === 'bajo')  return '😑 Bajo';
    if ($normalized === '2' || $normalized === 'medio') return '😃 Medio';
    if ($normalized === '3' || $normalized === 'alto')  return '🔥 Alto';
    return $raw;
}

function formatLastContact($dateStr) {
    if (empty($dateStr) || strtotime($dateStr) === false) return '—';
    $today = new DateTime('today');
    $interactionDate = new DateTime($dateStr);
    $interactionDate->setTime(0, 0, 0);
    $diff = (int) $today->diff($interactionDate)->format('%r%a');
    $days = -$diff;
    if ($days < 0) return 'Futuro';
    if ($days === 0) return 'Hoy';
    if ($days === 1) return 'Ayer';
    if ($days < 7) return $days . ' días';
    if ($days < 30) {
        $weeks = (int) floor($days / 7);
        return $weeks . ' sem.';
    }
    $months = (int) floor($days / 30);
    return $months . ' mes' . ($months > 1 ? 'es' : '');
}

function resolveLeadBoardVendedorId(array $lead, $appointment, array $appointmentsByCalendarioId)
{
    if (is_array($appointment)) {
        $fromAppointment = intval($appointment['idusu'] ?? 0);
        if ($fromAppointment > 0) {
            return $fromAppointment;
        }
    }

    $calendarioId = intval($lead['id_calendario'] ?? 0);
    if ($calendarioId > 0 && isset($appointmentsByCalendarioId[$calendarioId])) {
        $fromCalendario = intval($appointmentsByCalendarioId[$calendarioId]['idusu'] ?? 0);
        if ($fromCalendario > 0) {
            return $fromCalendario;
        }
    }

    return intval($lead['id_vendedor_asignado'] ?? $lead['usuario_asignado'] ?? 0);
}

$rows = [];
foreach ($allLeads as $lead) {
    $tableName = (string) ($lead['tabla_origen'] ?? '');
    $leadId = intval($lead['id'] ?? 0);
    $key = $tableName . '|' . $leadId;

    $contactForm = $contactFormByLead[$key] ?? null;
    $cfId = intval($contactForm['cf_id'] ?? 0);
    $appointment = ($cfId > 0 && isset($appointmentsByCFId[$cfId])) ? $appointmentsByCFId[$cfId] : null;

    $status = resolveLeadStatus($tableName, $contactForm ?: [], $appointment, $lead);

    $normalizedStatus = strtolower(trim((string) $status));
    if ($normalizedStatus === '' || $normalizedStatus === 'lead' || $normalizedStatus === 'cliente') {
        continue;
    }

    $name = trim((string) ($lead['full_name'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($lead['names'] ?? ''));
    }
    if ($name === '') {
        $name = 'Lead #' . $leadId;
    }

    $createdAt = trim((string) ($lead['created_time'] ?? ''));
    $createdDate = '—';
    if ($createdAt !== '' && strtotime($createdAt) !== false) {
        $createdDate = date('d/m/Y', strtotime($createdAt));
    }

    $originValue = $contactForm['how_did_you_meet'] ?? ($lead['how_did_you_meet'] ?? '');

    $vendedorId = resolveLeadBoardVendedorId($lead, $appointment, $appointmentsByCalendarioId);
    $vendedorAgent = ($vendedorId > 0 && isset($agents[$vendedorId])) ? $agents[$vendedorId] : null;
    $vendedorName = is_array($vendedorAgent) ? ($vendedorAgent['full'] ?? '') : '';
    $vendedorNombre = is_array($vendedorAgent) ? ($vendedorAgent['nombre'] ?? '') : '';

    $liKey = strtolower($tableName) . '|' . $leadId;
    $lastContactDate = $lastInteractionMap[$liKey] ?? null;

    $rows[] = [
        'id'            => $leadId,
        'cf_id'         => $cfId,
        'tabla_origen'  => $tableName,
        'name'          => $name,
        'created_date'  => $createdDate,
        'last_contact'  => $lastContactDate,
        'origin'        => originLabelFromValue($originValue),
        'status'        => $status,
        'vendedor_id'      => $vendedorId,
        'vendedor_name'    => $vendedorName,
        'vendedor_nombre'  => $vendedorNombre,
        'engagement'    => $contactForm['engagement'] ?? null,
    ];
}

$totalCount = count($rows);
$deadCount = 0;
$pendingCount = 0;
$attendedCount = 0;

foreach ($rows as $row) {
    $normalized = strtolower(trim((string) ($row['status'] ?? '')));
    if ($normalized === 'muerto') {
        $deadCount++;
    } elseif ($normalized === 'atendido') {
        $attendedCount++;
    } else {
        $pendingCount++;
    }
}

$activeCount = max(0, $totalCount - $deadCount);

$rowsForView = [];
foreach ($rows as $row) {
    $normalized = strtolower(trim((string) ($row['status'] ?? '')));
    $isDead = ($normalized === 'muerto');

    if ($view === 'dead' && !$isDead) {
        continue;
    }
    if ($view === 'active' && $isDead) {
        continue;
    }
    $rowsForView[] = $row;
}

$viewTotalCount = count($rowsForView);
$viewPendingCount = 0;
$viewAttendedCount = 0;
foreach ($rowsForView as $row) {
    $normalized = strtolower(trim((string) ($row['status'] ?? '')));
    if ($normalized === 'atendido') {
        $viewAttendedCount++;
    } else {
        $viewPendingCount++;
    }
}

$displayRows = [];
foreach ($rowsForView as $row) {
    $normalized = strtolower(trim((string) ($row['status'] ?? '')));
    $isAttended = ($normalized === 'atendido');
    $isPending = !$isAttended;

    if ($segment === 'attended' && !$isAttended) {
        continue;
    }
    if ($segment === 'pending' && !$isPending) {
        continue;
    }
    if ($filterVendedor > 0 && intval($row['vendedor_id']) !== $filterVendedor) {
        continue;
    }
    if ($filterEstatus !== '' && $normalized !== $filterEstatus) {
        continue;
    }
    $displayRows[] = $row;
}

$displayCount = count($displayRows);
$viewLabel = $view === 'dead' ? 'Leads muertos' : 'Leads activos';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --panel: #ffffff;
        --surface: #f8f6f2;
        --border: #e8e2d8;
        --ink: #2c2c2a;
        --muted: #888780;
        --gold: #c5a028;
        --eng-low: #c17a00;
        --eng-mid: #2563eb;
        --community: #059669;
    }

    .leadboard-page {
        padding: 12px 20px 40px;
        font-family: 'DM Sans', system-ui, sans-serif;
    }

    .wp-accounts-topbar {
        background: #ffffff;
        border-bottom: 1px solid #e2e5ea;
        padding: 14px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 82px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07), 0 1px 2px rgba(0, 0, 0, 0.04);
        margin-bottom: 14px;
    }

    .wp-accounts-topbar-left {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 4px;
        padding: 2px 0;
    }

    .wp-accounts-topbar-left h1 {
        font-size: 17px;
        font-weight: 700;
        color: #1a1d23;
        letter-spacing: -0.3px;
        margin: 0;
        line-height: 1.2;
        font-family: 'DM Sans', system-ui, sans-serif;
    }

    .wp-accounts-topbar-left p {
        font-size: 12px;
        color: #9ca3af;
        margin: 0;
        line-height: 1.35;
        font-family: 'DM Sans', system-ui, sans-serif;
    }

    .wp-accounts-topbar-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .leadboard-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }

    .leadboard-title {
        margin: 0;
        color: var(--ink);
        font-size: 32px;
        font-family: 'Cormorant Garamond', Georgia, serif;
        letter-spacing: 1px;
        line-height: 1;
    }

    .leadboard-subtitle {
        margin: 4px 0 0;
        font-size: 12px;
        color: var(--muted);
        font-weight: 600;
    }

    .efege-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--ink);
        text-decoration: none;
        white-space: nowrap;
    }

    .efege-btn-primary {
        background: #111;
        color: #fff;
        border-color: #111;
    }

    .table-wrap {
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    .leadboard-tabs {
        padding: 12px 20px 12px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        border-bottom: 1px solid var(--border);
    }

    .leadboard-tab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 8px 10px 8px 14px;
        font-size: 15px;
        font-weight: 700;
        color: #4b5563;
        border: 1px solid var(--border);
        border-radius: 9999px;
        background: #fff;
        line-height: 1;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .leadboard-tab:hover {
        border-color: #cdd4df;
        background: #f9fafb;
        color: #1f2937;
    }

    .leadboard-tab.active {
        color: #fff;
        background: #111;
        border-color: #111;
    }

    .leadboard-tab-count {
        min-width: 32px;
        height: 32px;
        padding: 0 10px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.2px;
        background: #eef2f7;
        color: #1f2937;
    }

    .leadboard-tab.active .leadboard-tab-count {
        background: rgba(255, 255, 255, 0.18);
        color: #fff;
    }

    .leadboard-thin-divider {
        height: 1px;
        background: var(--border);
        margin: 0 20px;
    }

    .leadboard-subtabs {
        padding: 12px 20px 12px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        border-bottom: 1px solid var(--border);
    }

    .filter-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: #fff;
        color: var(--muted);
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
    }

    .filter-pill.active {
        background: #111;
        border-color: #111;
        color: #fff;
    }

    .table-header-custom {
        padding: 14px 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .table-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--ink);
    }

    .table-count {
        font-size: 11px;
        color: var(--muted);
    }

    .efege-filter-search {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 7px;
        color: var(--ink);
        font-family: 'DM Sans', system-ui, sans-serif;
        font-size: 12px;
        padding: 7px 12px;
        outline: none;
        transition: border-color 0.2s;
        width: 220px;
    }

    .efege-filter-search::placeholder { color: var(--muted); }
    .efege-filter-search:focus { border-color: var(--gold); }

    .efege-filter-select {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 7px;
        color: var(--ink);
        font-family: 'DM Sans', system-ui, sans-serif;
        font-size: 12px;
        padding: 7px 10px;
        outline: none;
        transition: border-color 0.2s;
        cursor: pointer;
    }
    .efege-filter-select:focus { border-color: var(--gold); }

    .efege-table-scroll {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 800px;
        width: 100%;
        scrollbar-width: thin;
        scrollbar-color: var(--border, #e2e8f0) transparent;
    }

    .efege-table-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
    .efege-table-scroll::-webkit-scrollbar-track { background: transparent; }
    .efege-table-scroll::-webkit-scrollbar-thumb { background: var(--border, #e2e8f0); border-radius: 3px; }

    .efege-table {
        font-size: 13px;
        font-family: 'DM Sans', system-ui, sans-serif;
    }

    .efege-table thead th {
        background: var(--surface) !important;
        color: var(--muted) !important;
        font-size: 10px !important;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        border-bottom: 1px solid var(--border) !important;
        padding: 10px 16px !important;
        white-space: nowrap;
        font-weight: 600 !important;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .efege-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
    .efege-table tbody tr:hover { background: var(--surface) !important; }

    .efege-table tbody td {
        padding: 12px 16px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid var(--border) !important;
        color: var(--ink);
    }

    .status {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .status-pending { background: rgba(245,158,11,0.12); color: var(--eng-low); }
    .status-scheduled { background: rgba(59,130,246,0.12); color: var(--eng-mid); }
    .status-attended { background: rgba(16,185,129,0.12); color: var(--community); }
    .status-closed { background: rgba(197,160,40,0.15); color: var(--gold); }

    .td-name { font-weight: 600; color: var(--ink); font-size: 13px; }
    .td-inline-muted { font-size: 11px; color: var(--muted); }

    .origin-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
    }

    .origin-newmarket {
        background: rgba(37, 99, 235, 0.12);
        color: #1d4f91;
    }

    .origin-community {
        background: rgba(5, 150, 105, 0.14);
        color: #116b54;
    }

    .origin-planner {
        background: rgba(124, 58, 237, 0.12);
        color: #5a33a8;
    }

    .origin-default {
        background: rgba(107, 114, 128, 0.12);
        color: #4b5563;
    }

    .vendedor-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #1e293b;
    }

    .vendedor-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 600;
        font-size: 13px;
        flex-shrink: 0;
    }

    .vendedor-name {
        font-weight: 500;
    }

    .vendedor-sin-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #f4f5f7;
        border: 1px solid #e2e5ea;
        border-radius: 20px;
        padding: 4px 11px;
        font-size: 12px;
        font-weight: 500;
        color: #6b7280;
        white-space: nowrap;
    }

    .actions-col {
        text-align: center;
    }

    .actions-group {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .action-btn {
        width: 34px;
        height: 34px;
        padding: 0;
        border-radius: 8px;
        border: 1px solid #d9dde5;
        background: #fff;
        color: #6b7280;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.15s ease;
        cursor: pointer;
        font-size: 14px;
        line-height: 1;
    }

    .eng {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    .eng-low  { background: rgba(245,158,11,0.12);  color: #c17a00; }
    .eng-mid  { background: rgba(59,130,246,0.12);   color: #2563eb; }
    .eng-high { background: rgba(16,185,129,0.12);   color: #10B981; }

    .contact-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
        margin-right: 5px;
    }
    .contact-dot-green { background: #10B981; }
    .contact-dot-red   { background: #ef4444; }
    .contact-dot-gray  { background: #d1d5db; }
    .contact-cell { display: inline-flex; align-items: center; }

    .action-btn:hover {
        border-color: #bfc6d3;
        color: #374151;
        background: #f9fafb;
    }

    @media (max-width: 768px) {
        .leadboard-page { padding: 8px 12px 30px; }
        .leadboard-top { flex-direction: column; align-items: flex-start; }
        .table-header-custom { flex-direction: column; align-items: flex-start; }
        .efege-filter-search { width: 100%; }
    }
</style>

<div class="leadboard-page">
    <header class="wp-accounts-topbar">
        <div class="wp-accounts-topbar-left">
            <h1>My Lead Board</h1>
            <p>Todos mis leads activos, directos y de planners</p>
        </div>
        <div class="wp-accounts-topbar-actions">
            <a href="formulario-registro-manual.php" class="efege-btn efege-btn-primary">+ Nuevo lead</a>
        </div>
    </header>

    <div class="table-wrap">
        <div class="leadboard-tabs">
            <a href="<?php echo htmlspecialchars(buildLeadBoardUrl(['view' => 'active', 'segment' => 'all']), ENT_QUOTES, 'UTF-8'); ?>" class="leadboard-tab <?php echo $view === 'active' ? 'active' : ''; ?>">Activos <span class="leadboard-tab-count"><?php echo number_format($activeCount); ?></span></a>
            <a href="<?php echo htmlspecialchars(buildLeadBoardUrl(['view' => 'dead', 'segment' => 'all']), ENT_QUOTES, 'UTF-8'); ?>" class="leadboard-tab <?php echo $view === 'dead' ? 'active' : ''; ?>">Muertos <span class="leadboard-tab-count"><?php echo number_format($deadCount); ?></span></a>
        </div>

        <div class="leadboard-thin-divider"></div>

        <?php if ($view === 'active'): ?>
            <div class="leadboard-subtabs">
                <a href="<?php echo htmlspecialchars(buildLeadBoardUrl(['segment' => 'all']), ENT_QUOTES, 'UTF-8'); ?>" class="filter-pill <?php echo $segment === 'all' ? 'active' : ''; ?>">Todos (<?php echo number_format($viewTotalCount); ?>)</a>
                <a href="<?php echo htmlspecialchars(buildLeadBoardUrl(['segment' => 'pending']), ENT_QUOTES, 'UTF-8'); ?>" class="filter-pill <?php echo $segment === 'pending' ? 'active' : ''; ?>">Pendientes (<?php echo number_format($viewPendingCount); ?>)</a>
                <a href="<?php echo htmlspecialchars(buildLeadBoardUrl(['segment' => 'attended']), ENT_QUOTES, 'UTF-8'); ?>" class="filter-pill <?php echo $segment === 'attended' ? 'active' : ''; ?>">Atendidos (<?php echo number_format($viewAttendedCount); ?>)</a>
            </div>
        <?php endif; ?>

        <div class="table-header-custom">
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="table-title"><?php echo htmlspecialchars($viewLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="table-count"><?php echo number_format($displayCount); ?> leads</span>
            </div>
            <form method="get" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="segment" value="<?php echo htmlspecialchars($segment, ENT_QUOTES, 'UTF-8'); ?>">

                <?php if (!$isVendedoraLocked): ?>
                <select name="vendedor" class="efege-filter-select" onchange="this.form.submit()">
                    <option value="">Todas las vendedoras</option>
                    <?php foreach ($agents as $aId => $agentData): ?>
                        <?php $agentLabel = is_array($agentData) ? ($agentData['full'] ?? '') : (string) $agentData; ?>
                        <option value="<?php echo intval($aId); ?>" <?php echo $filterVendedor === intval($aId) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($agentLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <select name="estatus" class="efege-filter-select" onchange="this.form.submit()">
                    <option value="">Todos los estatus</option>
                    <option value="agendado" <?php echo $filterEstatus === 'agendado' ? 'selected' : ''; ?>>Agendado</option>
                    <option value="atendido" <?php echo $filterEstatus === 'atendido' ? 'selected' : ''; ?>>Atendido</option>
                    <option value="fantasma" <?php echo $filterEstatus === 'fantasma' ? 'selected' : ''; ?>>Fantasma</option>
                    <option value="muerto"   <?php echo $filterEstatus === 'muerto'   ? 'selected' : ''; ?>>Muerto</option>
                </select>

                <div style="position:relative;">
                    <i style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;" class="fas fa-search"></i>
                    <input
                        type="text"
                        class="efege-filter-search"
                        style="padding-left:30px;"
                        name="search"
                        placeholder="Buscar por nombre..."
                        value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>
            </form>
        </div>

        <div class="efege-table-scroll">
            <table class="efege-table table table-hover">
                <thead>
                    <tr>
                        <th>Nombre del lead</th>
                        <th>Fecha de registro</th>
                        <th>Ultimo contacto</th>
                        <th>Origen</th>
                        <th>Vendedor/a</th>
                        <th>Engagement del cliente</th>
                        <th>Estatus</th>
                        <th class="actions-col">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($displayRows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No hay leads para mostrar</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($displayRows as $row): ?>
                            <tr>
                                <td><div class="td-name"><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></div></td>
                                <td><span class="td-inline-muted"><?php echo htmlspecialchars($row['created_date'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td>
                                    <?php
                                        $lc = formatLastContact($row['last_contact']);
                                        $lcDays = null;
                                        if (!empty($row['last_contact']) && strtotime($row['last_contact']) !== false) {
                                            $lcDays = (int) floor((strtotime('today') - strtotime($row['last_contact'])) / 86400);
                                        }
                                        if ($lcDays === null) {
                                            $dotClass = 'contact-dot contact-dot-gray';
                                        } elseif ($lcDays >= 10) {
                                            $dotClass = 'contact-dot contact-dot-red';
                                        } else {
                                            $dotClass = 'contact-dot contact-dot-green';
                                        }
                                    ?>
                                    <span class="contact-cell td-inline-muted" title="<?php echo htmlspecialchars($row['last_contact'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="<?php echo $dotClass; ?>"></span><?php echo htmlspecialchars($lc, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo htmlspecialchars(originBadgeClass($row['origin']), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($row['origin'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['vendedor_name'] !== ''): ?>
                                        <?php
                                        $vendedorInitial = getVendedorInitial($row['vendedor_nombre'] ?? '');
                                        $vendedorColor = getVendedorColor($row['vendedor_nombre'] ?? '');
                                        ?>
                                        <div class="vendedor-badge">
                                            <div class="vendedor-circle" style="background-color: <?php echo htmlspecialchars($vendedorColor, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($vendedorInitial, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <span class="vendedor-name"><?php echo htmlspecialchars($row['vendedor_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="vendedor-sin-badge">Sin vendedor</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $engLabel = getEngagementLabelWithEmoji($row['engagement']);
                                        $engNorm  = mb_strtolower(trim((string) ($row['engagement'] ?? '')), 'UTF-8');
                                        $engClass = 'eng eng-low';
                                        if ($engNorm === '3' || $engNorm === 'alto')  { $engClass = 'eng eng-high'; }
                                        elseif ($engNorm === '2' || $engNorm === 'medio') { $engClass = 'eng eng-mid'; }
                                    ?>
                                    <?php if ($engLabel !== ''): ?>
                                        <span class="<?php echo htmlspecialchars($engClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($engLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php else: ?>
                                        <span class="td-inline-muted" style="opacity:.45;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?php echo statusBadgeClass($row['status']); ?>">
                                        <?php echo htmlspecialchars(statusLabel($row['status']), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="actions-col">
                                    <?php
                                        $traceParams = [];
                                        $traceTabla = trim((string) ($row['tabla_origen'] ?? ''));
                                        $traceOrigId = (int) ($row['id'] ?? 0);
                                        if ($traceTabla !== '' && $traceOrigId > 0) {
                                            $traceParams['tabla'] = $traceTabla;
                                            $traceParams['orig_id'] = $traceOrigId;
                                        }
                                        $cfIdForTrace = (int) ($row['cf_id'] ?? 0);
                                        if ($cfIdForTrace > 0) {
                                            $traceParams['cf_id'] = $cfIdForTrace;
                                        }
                                        $traceUrl = 'consulta_post_leads_trazabilidad.php?' . http_build_query($traceParams);
                                    ?>
                                    <div class="actions-group">
                                        <a class="action-btn" title="Chat" href="<?php echo htmlspecialchars($traceUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-message"></i>
                                        </a>
                                        <a class="action-btn" title="Registrar interacción" href="lead_interaction.php?tabla_origen=<?php echo urlencode((string) $row['tabla_origen']); ?>&id=<?php echo intval($row['id']); ?>">
                                            <i class="fas fa-comments"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
</body>
</html>
