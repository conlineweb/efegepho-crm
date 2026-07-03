<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';
require_once __DIR__ . '/lead_field_badge_helper.php';
require_once __DIR__ . '/lead_origin_helper.php';
require_once __DIR__ . '/usuario_roles_helper.php';
require_once __DIR__ . '/wp_eventos_contact_form_helper.php';
require_once __DIR__ . '/calendario_estatus_historial_helper.php';
require_once __DIR__ . '/evento_wp_post_helper.php';
require_once __DIR__ . '/planner_event_display_helper.php';

if (!function_exists('firstNonEmptyValue')) {
    function firstNonEmptyValue()
    {
        foreach (func_get_args() as $value) {
            if ($value === null) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }
}

if (!function_exists('mapTipoClienteValue')) {
    function mapTipoClienteValue($raw)
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return '';
        }

        if ($value === '1' || strcasecmp($value, 'Wedding Planner') === 0) {
            return 'Wedding Planner';
        }

        if ($value === '0' || strcasecmp($value, 'Cliente Final') === 0) {
            return 'Cliente Final';
        }

        return '';
    }
}

if (!function_exists('isTrustedClienteFinalTipo')) {
    function isTrustedClienteFinalTipo($tipoRaw, $lead, $howDidYouMeet)
    {
        if (mapTipoClienteValue($tipoRaw) !== 'Cliente Final') {
            return false;
        }

        $how = trim((string) $howDidYouMeet);
        if ($how === '1') {
            return false;
        }

        if (in_array($how, ['2', '3'], true)) {
            return true;
        }

        return strtolower(trim((string) ($lead['lead_status'] ?? ''))) === 'manual';
    }
}

if (!function_exists('getTipoClienteLabel')) {
    function getTipoClienteLabel($lead, $cfData = null)
    {
        $tablaOrigen = $lead['tabla_origen'] ?? '';

        $cfTipoCliente = null;
        $cfHowDidYouMeet = null;
        if (is_array($cfData)) {
            $cfTipoCliente = $cfData['tipo_cliente'] ?? null;
            $cfHowDidYouMeet = $cfData['how_did_you_meet'] ?? null;
        } elseif ($cfData !== null) {
            $cfTipoCliente = $cfData;
        }

        if (mapTipoClienteValue($cfTipoCliente) === 'Wedding Planner') {
            return 'Wedding Planner';
        }

        if (mapTipoClienteValue($lead['tipo_cliente'] ?? '') === 'Wedding Planner') {
            return 'Wedding Planner';
        }

        $howDidYouMeet = firstNonEmptyValue(
            $cfHowDidYouMeet,
            $lead['how_did_you_meet_raw'] ?? '',
            $lead['how_did_you_meet'] ?? ''
        );

        $inferred = inferTipoClienteFromOriginData($howDidYouMeet, $tablaOrigen);
        if ($inferred !== '') {
            return $inferred;
        }

        if (isTrustedClienteFinalTipo($cfTipoCliente, $lead, $howDidYouMeet)) {
            return 'Cliente Final';
        }

        if (isTrustedClienteFinalTipo($lead['tipo_cliente'] ?? '', $lead, $howDidYouMeet)) {
            return 'Cliente Final';
        }

        return '—';
    }
}

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

    if (is_array($contactFormRow) && isset($contactFormRow['cliente']) && intval($contactFormRow['cliente']) === 1) {
        return 'cliente';
    }

    if (strcasecmp((string) $tableName, 'eventos_wp') === 0) {
        global $conn;
        $eventId = (int) ($leadRow['id'] ?? 0);
        if (isset($conn) && $eventId > 0) {
            $cfRow = is_array($contactFormRow) ? $contactFormRow : [];
            $cf = [
                'tabla_origen' => 'eventos_wp',
                'form_name' => 'eventos_wp',
                'original_lead_id' => $eventId,
                'tipo_cliente' => $cfRow['tipo_cliente'] ?? 1,
                'cliente' => $cfRow['cliente'] ?? 0,
            ];
            $cf = wpEventosCfHydratePlannerContactFormFields($conn, $cf);
            $resolved = wpEventosCfResolvePlannerTipoClienteEstatus($cf);
            if ($resolved !== null && $resolved !== '') {
                return $resolved;
            }
        }

        if (is_array($contactFormRow) && !empty($contactFormRow)) {
            return 'atendido';
        }

        return 'agendado';
    }

    if ($appointmentRow === null || !isset($appointmentRow['estatus'])) {
        return 'lead';
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

function leadBoardStatusToHistorialCode($status)
{
    $normalized = strtolower(trim((string) $status));
    $map = [
        'agendado' => 0,
        'atendido' => 1,
        'fantasma' => 2,
        'muerto'   => 3,
    ];

    return $map[$normalized] ?? null;
}

function leadBoardIsValidDateValue($raw)
{
    $value = trim((string) $raw);

    return $value !== '' && strpos($value, '0000-00-00') !== 0;
}

function leadBoardBuildHistorialDateMap($conn, array $calendarioIds)
{
    $map = [];
    if (!($conn instanceof mysqli) || empty($calendarioIds)) {
        return $map;
    }

    ensureCalendarioEstatusHistorialTable($conn);

    $idsList = implode(',', array_map('intval', $calendarioIds));
    if ($idsList === '') {
        return $map;
    }

    $res = $conn->query(
        "SELECT id_calendario, estatus, fecha_cambio
         FROM calendario_estatus_historial
         WHERE id_calendario IN ($idsList)
         ORDER BY fecha_cambio ASC, id ASC"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $calId = (int) ($row['id_calendario'] ?? 0);
            $estatusCode = (int) ($row['estatus'] ?? -1);
            $fecha = trim((string) ($row['fecha_cambio'] ?? ''));
            if ($calId > 0 && $fecha !== '') {
                $map[$calId . '|' . $estatusCode] = $fecha;
            }
        }
    }

    return $map;
}

function resolveLeadBoardWpEventStatusUpdatedAt(array $milestones, ?array $contactForm)
{
    $statusKey = wpPlannerResolveEventStatusKey(
        $milestones['estatus'] ?? '',
        $contactForm['cliente'] ?? ($milestones['contact_form_cliente'] ?? null)
    );

    switch ($statusKey) {
        case 'cliente':
            if (is_array($contactForm) && leadBoardIsValidDateValue($contactForm['fecha_cambio_cliente'] ?? '')) {
                return trim((string) $contactForm['fecha_cambio_cliente']);
            }
            if (leadBoardIsValidDateValue($milestones['contact_form_fecha_cambio_cliente'] ?? '')) {
                return trim((string) $milestones['contact_form_fecha_cambio_cliente']);
            }
            if (leadBoardIsValidDateValue($milestones['fecha_cliente'] ?? '')) {
                return trim((string) $milestones['fecha_cliente']);
            }
            break;
        case 'cotizado':
            if (leadBoardIsValidDateValue($milestones['fecha_cotizado'] ?? '')) {
                return trim((string) $milestones['fecha_cotizado']);
            }
            // Sin fecha de cotizado: usar la de agendado.
        case 'pendiente':
            if (leadBoardIsValidDateValue($milestones['fecha_agendado'] ?? '')) {
                return trim((string) $milestones['fecha_agendado']);
            }
            if (leadBoardIsValidDateValue($milestones['fecha_registro'] ?? '')) {
                return trim((string) $milestones['fecha_registro']);
            }
            break;
        case 'atendido':
        case 'cliente_inminente':
            if (leadBoardIsValidDateValue($milestones['fecha_atendido'] ?? '')) {
                return trim((string) $milestones['fecha_atendido']);
            }
            if (is_array($contactForm) && leadBoardIsValidDateValue($contactForm['submission_date'] ?? '')) {
                return trim((string) $contactForm['submission_date']);
            }
            break;
        case 'muerto':
        case 'rechazado':
            if (leadBoardIsValidDateValue($milestones['fecha_muerto'] ?? '')) {
                return trim((string) $milestones['fecha_muerto']);
            }
            break;
    }

    if (leadBoardIsValidDateValue($milestones['fecha_actualizacion_estatus'] ?? '')) {
        return trim((string) $milestones['fecha_actualizacion_estatus']);
    }

    return '';
}

function resolveLeadBoardStatusUpdatedAt($status, $tableName, $calendarioId, array $historialDateMap, $appointment, array $lead, array $eventoMilestonesById, ?array $contactForm = null, array $historialFechaByCfIdByCode = [])
{
    $normalized = strtolower(trim((string) $status));
    $estatusCode = leadBoardStatusToHistorialCode($status);
    $cfId = is_array($contactForm) ? (int) ($contactForm['cf_id'] ?? 0) : 0;

    // Eventos WP (wedding planner): hitos en eventos_wp, no calendario_estatus_historial.
    if (strcasecmp((string) $tableName, 'eventos_wp') === 0) {
        $eventId = (int) ($lead['id'] ?? 0);
        $milestones = is_array($eventoMilestonesById[$eventId] ?? null)
            ? $eventoMilestonesById[$eventId]
            : $lead;

        return resolveLeadBoardWpEventStatusUpdatedAt($milestones, $contactForm);
    }

    if ($cfId > 0 && $estatusCode !== null && !empty($historialFechaByCfIdByCode[$estatusCode][$cfId])) {
        return trim((string) $historialFechaByCfIdByCode[$estatusCode][$cfId]);
    }

    if ($calendarioId > 0 && $estatusCode !== null) {
        $histKey = $calendarioId . '|' . $estatusCode;
        if (!empty($historialDateMap[$histKey]) && leadBoardIsValidDateValue($historialDateMap[$histKey])) {
            return trim((string) $historialDateMap[$histKey]);
        }
    }

    if ($normalized === 'agendado' && is_array($appointment)) {
        $fechaRegistro = trim((string) ($appointment['fecha_registro'] ?? ''));
        if (leadBoardIsValidDateValue($fechaRegistro)) {
            return $fechaRegistro;
        }

        $fecha = trim((string) ($appointment['fecha'] ?? ''));
        $hora = trim((string) ($appointment['hora'] ?? ''));
        if (leadBoardIsValidDateValue($fecha)) {
            return trim($fecha . ($hora !== '' ? ' ' . $hora : ''));
        }
    }

    if ($normalized === 'atendido') {
        if (is_array($appointment)) {
            $fecha = trim((string) ($appointment['fecha'] ?? ''));
            $hora = trim((string) ($appointment['hora'] ?? ''));
            if (leadBoardIsValidDateValue($fecha)) {
                return trim($fecha . ($hora !== '' ? ' ' . $hora : ''));
            }
        }

        foreach (['submission_date', 'created_time'] as $field) {
            if (is_array($contactForm) && leadBoardIsValidDateValue($contactForm[$field] ?? '')) {
                return trim((string) $contactForm[$field]);
            }
            if (leadBoardIsValidDateValue($lead[$field] ?? '')) {
                return trim((string) $lead[$field]);
            }
        }
    }

    if (in_array($normalized, ['muerto', 'fantasma'], true) && is_array($appointment)) {
        $fecha = trim((string) ($appointment['fecha'] ?? ''));
        $hora = trim((string) ($appointment['hora'] ?? ''));
        if (leadBoardIsValidDateValue($fecha)) {
            return trim($fecha . ($hora !== '' ? ' ' . $hora : ''));
        }
    }

    if ($normalized === 'agendado' && leadBoardIsValidDateValue($lead['calendario_fecha'] ?? '')) {
        $horaLead = trim((string) ($lead['calendario_hora'] ?? ''));

        return trim((string) $lead['calendario_fecha'] . ($horaLead !== '' ? ' ' . $horaLead : ''));
    }

    return '';
}

function formatLeadBoardStatusDate($raw)
{
    if (!leadBoardIsValidDateValue($raw)) {
        return '—';
    }

    $ts = strtotime((string) $raw);
    if ($ts === false) {
        return '—';
    }

    $hasTime = preg_match('/\d{1,2}:\d{2}/', (string) $raw);

    return $hasTime ? date('d/m/Y H:i', $ts) : date('d/m/Y', $ts);
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

// Vendedor (tipoUsu=1) y gestor de galería (tipoUsu=2): vista completa sin forzar filtro propio.
$mlbUserId = intval($_SESSION['uid'] ?? 0);
$isVendedoraLocked = !usuarioTipoVeTodoEnVistasComerciales($tipoUsuario)
    && (int) $tipoUsuario === USUARIO_ROL_VENDEDOR;
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

$wpEventosLeads = wpEventosCfBuildLeadsForConsultaLeads($conn);
$appointmentsByEventId = wpEventosCfGetAppointmentsByEventId($conn);
if (!empty($wpEventosLeads)) {
    $existingKeys = [];
    foreach ($allLeads as $existingLead) {
        $existingKeys[($existingLead['tabla_origen'] ?? '') . '|' . (int) ($existingLead['id'] ?? 0)] = true;
    }
    foreach ($wpEventosLeads as $wpLead) {
        if ($searchQuery !== '' && !wpEventosCfLeadMatchesSearch($wpLead, $searchQuery)) {
            continue;
        }

        if ($isVendedoraLocked) {
            $assignedVendor = wpEventosCfResolveEventVendedorId($conn, $wpLead, null, $appointmentsByEventId);
            if ($assignedVendor !== $mlbUserId) {
                continue;
            }
        }

        $key = ($wpLead['tabla_origen'] ?? '') . '|' . (int) ($wpLead['id'] ?? 0);
        if (isset($existingKeys[$key])) {
            continue;
        }
        $allLeads[] = $wpLead;
        $existingKeys[$key] = true;
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

    $sql = "SELECT id, original_lead_id, cliente, how_did_you_meet, engagement, tipo_cliente, fecha_cambio_cliente, submission_date, created_time FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")";
    $resCf = $conn->query($sql);

    if ($resCf) {
        while ($row = $resCf->fetch_assoc()) {
            $key = $t . '|' . intval($row['original_lead_id']);
            $contactFormByLead[$key] = [
                'cf_id' => intval($row['id']),
                'cliente' => isset($row['cliente']) ? intval($row['cliente']) : 0,
                'how_did_you_meet' => $row['how_did_you_meet'] ?? null,
                'engagement' => $row['engagement'] ?? null,
                'tipo_cliente' => $row['tipo_cliente'] ?? null,
                'fecha_cambio_cliente' => $row['fecha_cambio_cliente'] ?? null,
                'submission_date' => $row['submission_date'] ?? null,
                'created_time' => $row['created_time'] ?? null,
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

// Agentes asignables: vendedoras (1), líderes de planners (5) y admins (0)
$agents = [];
$agentTiposSql = usuarioSqlInTiposAsignacionLeadBoard();
$resAgents = $conn->query("SELECT id, nombre, apepat, tipoUsu FROM usuarios WHERE tipoUsu IN ($agentTiposSql) ORDER BY nombre, apepat");
if ($resAgents) {
    while ($a = $resAgents->fetch_assoc()) {
        $agentId = intval($a['id'] ?? 0);
        if ($agentId <= 0) {
            continue;
        }
        $agents[$agentId] = [
            'full' => trim(($a['nombre'] ?? '') . ' ' . ($a['apepat'] ?? '')),
            'nombre' => trim((string) ($a['nombre'] ?? '')),
            'tipoUsu' => (int) ($a['tipoUsu'] ?? 0),
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

$allCalendarioIdsForHistorial = [];
foreach ($appointmentsByCFId as $apptRow) {
    $calId = (int) ($apptRow['id'] ?? 0);
    if ($calId > 0) {
        $allCalendarioIdsForHistorial[$calId] = true;
    }
}
foreach ($allLeads as $leadRowForCal) {
    $calId = (int) ($leadRowForCal['id_calendario'] ?? 0);
    if ($calId > 0) {
        $allCalendarioIdsForHistorial[$calId] = true;
    }
}
$historialDateMap = leadBoardBuildHistorialDateMap($conn, array_keys($allCalendarioIdsForHistorial));

$historialFechaByCfIdByCode = [
    0 => plannerProfileBatchLoadFechaHistorialByContactFormIds($conn, $cfIds, [0]),
    1 => plannerProfileBatchLoadFechaAtencionByContactFormIds($conn, $cfIds),
    2 => plannerProfileBatchLoadFechaHistorialByContactFormIds($conn, $cfIds, [2]),
    3 => plannerProfileBatchLoadFechaHistorialByContactFormIds($conn, $cfIds, [3]),
];

$eventoMilestonesById = [];
$eventoWpIds = $leadIdsByTable['eventos_wp'] ?? [];
if (!empty($eventoWpIds)) {
    $evIdsList = implode(',', array_map('intval', array_unique($eventoWpIds)));
    $evRes = $conn->query(
        "SELECT e.id, e.estatus, e.fecha_agendado, e.fecha_cotizado, e.fecha_atendido,
                e.fecha_cliente, e.fecha_muerto, e.fecha_registro, e.fecha_actualizacion_estatus,
                cf.cliente AS contact_form_cliente, cf.fecha_cambio_cliente AS contact_form_fecha_cambio_cliente
         FROM eventos_wp e
         LEFT JOIN contact_form cf
           ON cf.original_lead_id = e.id
          AND LOWER(TRIM(COALESCE(cf.tabla_origen, ''))) = 'eventos_wp'
         WHERE e.id IN ($evIdsList)"
    );
    if ($evRes) {
        while ($evRow = $evRes->fetch_assoc()) {
            $evId = (int) ($evRow['id'] ?? 0);
            if ($evId > 0) {
                $eventoMilestonesById[$evId] = $evRow;
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

function resolveLeadBoardVendedorId(array $lead, $appointment, array $appointmentsByCalendarioId, $conn = null, ?array $appointmentsByEventId = null)
{
    if ($conn && function_exists('wpEventosCfIsWpEventLead') && wpEventosCfIsWpEventLead($lead)) {
        return wpEventosCfResolveEventVendedorId(
            $conn,
            $lead,
            is_array($appointment) ? $appointment : null,
            $appointmentsByEventId
        );
    }

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
    if (!$appointment && wpEventosCfIsWpEventLead($lead)) {
        $eventIdForVendor = wpEventosCfResolveEventIdFromLead($lead);
        if ($eventIdForVendor > 0 && isset($appointmentsByEventId[$eventIdForVendor])) {
            $appointment = $appointmentsByEventId[$eventIdForVendor];
        }
    }

    $status = resolveLeadStatus($tableName, $contactForm, $appointment, $lead);

    $normalizedStatus = strtolower(trim((string) $status));
    if ($normalizedStatus === '' || $normalizedStatus === 'lead' || $normalizedStatus === 'cliente') {
        continue;
    }

    if (strcasecmp($tableName, 'eventos_wp') === 0) {
        $nameDisplay = wpEventosCfResolveLeadNameDisplay(array_merge($lead, ['tabla_origen' => 'eventos_wp']));
        $name = $nameDisplay['primary'] !== '' ? $nameDisplay['primary'] : ('Evento WP #' . $leadId);
    } else {
        $name = trim((string) ($lead['full_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($lead['names'] ?? ''));
        }
        if ($name === '') {
            $name = 'Lead #' . $leadId;
        }
    }

    $createdAt = trim((string) ($lead['created_time'] ?? ''));
    $createdDate = '—';
    if ($createdAt !== '' && strtotime($createdAt) !== false) {
        $createdDate = date('d/m/Y', strtotime($createdAt));
    }

    $originValue = $contactForm['how_did_you_meet'] ?? ($lead['how_did_you_meet'] ?? '');
    if (strcasecmp($tableName, 'eventos_wp') === 0 && trim((string) $originValue) === '') {
        $originValue = '1';
    }

    $vendedorId = resolveLeadBoardVendedorId($lead, $appointment, $appointmentsByCalendarioId, $conn, $appointmentsByEventId);
    $vendedorAgent = ($vendedorId > 0 && isset($agents[$vendedorId])) ? $agents[$vendedorId] : null;
    $vendedorName = is_array($vendedorAgent) ? ($vendedorAgent['full'] ?? '') : '';
    if ($vendedorName === '' && $vendedorId > 0) {
        $vendedorName = wpEventosCfResolveAsesorDisplayName($conn, $vendedorId);
    }
    $vendedorNombre = is_array($vendedorAgent) ? ($vendedorAgent['nombre'] ?? '') : '';
    if ($vendedorNombre === '' && $vendedorName !== '') {
        $vendedorNombre = trim(explode(' ', $vendedorName, 2)[0]);
    }

    $liKey = strtolower($tableName) . '|' . $leadId;
    $lastContactDate = $lastInteractionMap[$liKey] ?? null;

    $tipoClienteLabel = getTipoClienteLabel($lead, $contactForm);

    $calendarioId = 0;
    if (is_array($appointment)) {
        $calendarioId = (int) ($appointment['id'] ?? 0);
    }
    if ($calendarioId <= 0) {
        $calendarioId = (int) ($lead['id_calendario'] ?? 0);
    }

    $statusUpdatedRaw = resolveLeadBoardStatusUpdatedAt(
        $status,
        $tableName,
        $calendarioId,
        $historialDateMap,
        $appointment,
        $lead,
        $eventoMilestonesById,
        $contactForm,
        $historialFechaByCfIdByCode
    );

    $rows[] = [
        'id'            => $leadId,
        'cf_id'         => $cfId,
        'tabla_origen'  => $tableName,
        'calendario_id' => $calendarioId,
        'name'          => $name,
        'created_date'  => $createdDate,
        'last_contact'  => $lastContactDate,
        'tipo_cliente'  => $tipoClienteLabel,
        'origin'        => originLabelFromValue($originValue),
        'status'        => $status,
        'status_updated_at' => $statusUpdatedRaw,
        'status_updated_display' => formatLeadBoardStatusDate($statusUpdatedRaw),
        'vendedor_id'      => $vendedorId,
        'vendedor_name'    => $vendedorName,
        'vendedor_nombre'  => $vendedorNombre,
        'engagement'    => $contactForm['engagement'] ?? null,
    ];
}

$canAssignVendor = !$isVendedoraLocked;
$mlbAgentesJs = [];
foreach ($agents as $agentId => $agentData) {
    $nombreAgente = is_array($agentData) ? ($agentData['nombre'] ?? '') : '';
    $fullLabel = is_array($agentData) ? ($agentData['full'] ?? '') : (string) $agentData;
    $tipoUsuAgent = is_array($agentData) ? (int) ($agentData['tipoUsu'] ?? 0) : 0;
    if ($tipoUsuAgent > 0 && $tipoUsuAgent !== USUARIO_ROL_VENDEDOR && function_exists('usuarioRolLabel')) {
        $rolLabel = usuarioRolLabel($tipoUsuAgent);
        if ($rolLabel !== '' && $rolLabel !== 'Vendedor') {
            $fullLabel = trim($fullLabel . ' (' . $rolLabel . ')');
        }
    }
    $mlbAgentesJs[] = [
        'id' => (int) $agentId,
        'label' => $fullLabel,
        'initial' => getVendedorInitial($nombreAgente),
        'color' => getVendedorColor($nombreAgente),
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
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        border-bottom: 1px solid var(--border);
    }

    .leadboard-tabs-main {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .leadboard-tabs-action {
        margin-left: auto;
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
    .td-name-link {
        color: var(--ink);
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
    }
    .td-name-link:hover {
        color: var(--gold);
        text-decoration: underline;
    }
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

    .badge-tipo-cliente {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        white-space: nowrap;
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

    .js-mlb-assign-vendedor {
        cursor: pointer;
        transition: opacity 0.15s ease;
    }

    .js-mlb-assign-vendedor:hover {
        opacity: 0.82;
    }

    .vendedor-assign-cell.js-mlb-assign-vendedor:hover .vendedor-name {
        text-decoration: underline;
        text-decoration-color: rgba(30, 41, 59, 0.35);
    }

    .vendedor-sin-badge.js-mlb-assign-vendedor:hover {
        border-color: #c5cdd8;
        color: #4b5563;
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
            <div class="leadboard-tabs-main">
                <a href="<?php echo htmlspecialchars(buildLeadBoardUrl(['view' => 'active', 'segment' => 'all']), ENT_QUOTES, 'UTF-8'); ?>" class="leadboard-tab <?php echo $view === 'active' ? 'active' : ''; ?>">Activos <span class="leadboard-tab-count"><?php echo number_format($activeCount); ?></span></a>
                <a href="<?php echo htmlspecialchars(buildLeadBoardUrl(['view' => 'dead', 'segment' => 'all']), ENT_QUOTES, 'UTF-8'); ?>" class="leadboard-tab <?php echo $view === 'dead' ? 'active' : ''; ?>">Muertos <span class="leadboard-tab-count"><?php echo number_format($deadCount); ?></span></a>
            </div>
            <div class="leadboard-tabs-action">
                <a href="my_lead_board_leads.php" class="efege-btn efege-btn-primary">Gestión de leads</a>
            </div>
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
                        <th>ID</th>
                        <th>Nombre del lead</th>
                        <th>Fecha de registro</th>
                        <th>Ultimo contacto</th>
                        <th>Tipo de cliente</th>
                        <th>Origen</th>
                        <th>Vendedor/a</th>
                        <th>Engagement del cliente</th>
                        <th>Estatus</th>
                        <th>Actualización estatus</th>
                        <th class="actions-col">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($displayRows)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">No hay leads para mostrar</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($displayRows as $row): ?>
                            <?php
                                $leadInteractionUrl = 'lead_interaction.php?tabla_origen=' . urlencode((string) $row['tabla_origen']) . '&id=' . intval($row['id']);
                                $rowLeadId = (int) ($row['id'] ?? 0);
                                $rowCfId = (int) ($row['cf_id'] ?? 0);
                                $rowCalendarioId = (int) ($row['calendario_id'] ?? 0);
                                $rowVendedorId = (int) ($row['vendedor_id'] ?? 0);
                                $rowTablaOrigen = (string) ($row['tabla_origen'] ?? '');
                            ?>
                            <tr
                                data-lead-id="<?php echo $rowLeadId; ?>"
                                data-tabla-origen="<?php echo htmlspecialchars($rowTablaOrigen, ENT_QUOTES, 'UTF-8'); ?>"
                                data-cf-id="<?php echo $rowCfId; ?>"
                                data-calendario-id="<?php echo $rowCalendarioId; ?>"
                                data-vendedor-id="<?php echo $rowVendedorId; ?>"
                                data-lead-name="<?php echo htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <td>
                                    <?php
                                        $cfIdDisplay = (int) ($row['cf_id'] ?? 0);
                                        echo $cfIdDisplay > 0
                                            ? htmlspecialchars((string) $cfIdDisplay, ENT_QUOTES, 'UTF-8')
                                            : '<span class="td-inline-muted" style="opacity:.45;">—</span>';
                                    ?>
                                </td>
                                <td>
                                    <a class="td-name-link" href="<?php echo htmlspecialchars($leadInteractionUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Ver interacciones del lead">
                                        <?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
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
                                <td><?php echo renderTipoClienteBadge($row['tipo_cliente'] ?? '—'); ?></td>
                                <td>
                                    <span class="<?php echo htmlspecialchars(originBadgeClass($row['origin']), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($row['origin'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td data-column="vendedor">
                                    <?php if ($canAssignVendor): ?>
                                        <?php if ($row['vendedor_name'] !== ''): ?>
                                            <?php
                                            $vendedorInitial = getVendedorInitial($row['vendedor_nombre'] ?? '');
                                            $vendedorColor = getVendedorColor($row['vendedor_nombre'] ?? '');
                                            ?>
                                            <div class="vendedor-badge vendedor-assign-cell js-mlb-assign-vendedor" title="Clic para cambiar vendedora" role="button" tabindex="0">
                                                <div class="vendedor-circle" style="background-color: <?php echo htmlspecialchars($vendedorColor, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars($vendedorInitial, ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                                <span class="vendedor-name"><?php echo htmlspecialchars($row['vendedor_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="vendedor-sin-badge js-mlb-assign-vendedor" title="Clic para asignar vendedora" role="button" tabindex="0">Sin vendedor</span>
                                        <?php endif; ?>
                                    <?php elseif ($row['vendedor_name'] !== ''): ?>
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
                                <td>
                                    <span
                                        class="td-inline-muted"
                                        title="<?php echo htmlspecialchars((string) ($row['status_updated_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    ><?php echo htmlspecialchars((string) ($row['status_updated_display'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
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

<?php if ($canAssignVendor): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const mlbAgentes = <?php echo json_encode($mlbAgentesJs, JSON_UNESCAPED_UNICODE); ?>;

    function escapeHtmlMlb(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function findAgentMatch(vendorId) {
        return (mlbAgentes || []).find(function (agent) {
            return parseInt(agent.id, 10) === vendorId;
        }) || null;
    }

    function renderVendedorCellHtml(vendorId, nombre, apepat) {
        const fullName = (String(nombre || '') + ' ' + String(apepat || '')).trim();
        if (vendorId > 0 && fullName) {
            const agentMatch = findAgentMatch(vendorId);
            const initial = agentMatch && agentMatch.initial ? agentMatch.initial : (fullName.charAt(0).toUpperCase() || 'S');
            const color = agentMatch && agentMatch.color ? agentMatch.color : '#64748B';
            return '<div class="vendedor-badge vendedor-assign-cell js-mlb-assign-vendedor" title="Clic para cambiar vendedora" role="button" tabindex="0">' +
                '<div class="vendedor-circle" style="background-color:' + color + '">' + escapeHtmlMlb(initial) + '</div>' +
                '<span class="vendedor-name">' + escapeHtmlMlb(fullName) + '</span></div>';
        }
        return '<span class="vendedor-sin-badge js-mlb-assign-vendedor" title="Clic para asignar vendedora" role="button" tabindex="0">Sin vendedor</span>';
    }

    function openAssignVendedorPicker(row) {
        const leadId = parseInt(row.getAttribute('data-lead-id'), 10) || 0;
        const tablaOrigen = row.getAttribute('data-tabla-origen') || '';
        const cfId = parseInt(row.getAttribute('data-cf-id'), 10) || 0;
        const calendarioId = parseInt(row.getAttribute('data-calendario-id'), 10) || 0;
        const currentId = parseInt(row.getAttribute('data-vendedor-id'), 10) || 0;
        const leadName = row.getAttribute('data-lead-name') || '';

        if (!leadId || !tablaOrigen) {
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No se pudo identificar el lead.' });
            return;
        }

        let optionsHtml = '<option value="0">Sin vendedor</option>';
        (mlbAgentes || []).forEach(function (agente) {
            const agentId = parseInt(agente.id, 10) || 0;
            if (!agentId) {
                return;
            }
            const selected = agentId === currentId ? ' selected' : '';
            const label = agente.label || ('Usuario #' + agentId);
            optionsHtml += '<option value="' + agentId + '"' + selected + '>' + escapeHtmlMlb(label) + '</option>';
        });

        Swal.fire({
            title: 'Asignar vendedora',
            html: '<p style="margin:0 0 12px;color:#64748b;font-size:0.9rem;">' + escapeHtmlMlb(leadName) + '</p>' +
                '<select id="swalMlbVendedorSelect" class="form-select" style="width:100%;">' + optionsHtml + '</select>',
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            preConfirm: function () {
                const selectEl = document.getElementById('swalMlbVendedorSelect');
                return selectEl ? parseInt(selectEl.value, 10) || 0 : 0;
            }
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            let newVendorId = parseInt(result.value, 10);
            if (isNaN(newVendorId)) {
                newVendorId = 0;
            }

            const body = new URLSearchParams();
            body.append('tabla_origen', tablaOrigen);
            body.append('lead_id', String(leadId));
            body.append('cf_id', String(cfId));
            body.append('calendario_id', String(calendarioId));
            body.append('id_vendedor_asignado', String(newVendorId));

            fetch('asignar_vendedor_lead_board.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            })
                .then(function (response) { return response.json(); })
                .then(function (resp) {
                    if (resp && resp.success) {
                        const savedVendorId = parseInt(resp.id_vendedor_asignado, 10) || 0;
                        row.setAttribute('data-vendedor-id', String(savedVendorId));
                        const vendorCell = row.querySelector('td[data-column="vendedor"]');
                        if (vendorCell) {
                            vendorCell.innerHTML = renderVendedorCellHtml(
                                savedVendorId,
                                resp.vendedor_nombre || '',
                                resp.vendedor_apepat || ''
                            );
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Listo',
                            text: resp.message || 'Vendedora actualizada',
                            timer: 1600,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo asignar', 'error');
                    }
                })
                .catch(function () {
                    Swal.fire('Error', 'Error al conectar con el servidor', 'error');
                });
        });
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.js-mlb-assign-vendedor');
        if (!trigger) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        const row = trigger.closest('tr');
        if (row) {
            openAssignVendedorPicker(row);
        }
    });

    document.addEventListener('keydown', function (event) {
        const trigger = event.target.closest('.js-mlb-assign-vendedor');
        if (!trigger || (event.key !== 'Enter' && event.key !== ' ')) {
            return;
        }
        event.preventDefault();
        const row = trigger.closest('tr');
        if (row) {
            openAssignVendedorPicker(row);
        }
    });
})();
</script>
<?php endif; ?>

</div>
</body>
</html>
