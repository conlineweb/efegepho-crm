<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

$_cwpTipoUsu = intval($_SESSION['tus'] ?? -1);
$_cwpUserId  = intval($_SESSION['uid'] ?? 0);
$_cwpWpViewAsAdmin = usuarioEsAdminVistaConsultaWp($_cwpUserId);
// Vendedor (1) y gestor de galería (2) ven todos los planners; sin filtro por asignación propia.
$_cwpRestrictByVendedor = $_cwpUserId > 0
    && $_cwpTipoUsu === USUARIO_ROL_VENDEDOR
    && !usuarioTipoVeTodoEnVistasComerciales($_cwpTipoUsu)
    && !$_cwpWpViewAsAdmin;
$_cwpCanDeleteWp = usuarioPuedeOcultarWeddingPlanner($_cwpTipoUsu, $_cwpUserId);

function ensureColumnExists($conn, $table, $columnName, $columnDef)
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($columnName);
    $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safeTable}' AND COLUMN_NAME = '{$safeColumn}'";
    $res = $conn->query($checkSql);
    if ($res) {
        $row = $res->fetch_assoc();
        if (intval($row['c'] ?? 0) === 0) {
            $conn->query("ALTER TABLE `{$table}` ADD COLUMN {$columnDef}");
        }
    }
}

ensureColumnExists($conn, 'wedding_planners', 'empresa_wp', "`empresa_wp` VARCHAR(255) DEFAULT NULL AFTER `where_is_your_marriage_taking_place_`");
ensureColumnExists($conn, 'wedding_planners', 'phone', "`phone` VARCHAR(255) DEFAULT NULL AFTER `id_vendedor_asignado`");
ensureColumnExists($conn, 'wedding_planners', 'email', "`email` VARCHAR(255) DEFAULT NULL AFTER `empresa_wp`");
ensureColumnExists($conn, 'wedding_planners', 'city', "`city` VARCHAR(255) DEFAULT NULL AFTER `email`");
ensureColumnExists($conn, 'wedding_planners', 'planner_reason', "`planner_reason` VARCHAR(255) DEFAULT NULL AFTER `how_long_known_us`");
ensureColumnExists($conn, 'wedding_planners', 'how_long_known_us', "`how_long_known_us` VARCHAR(150) DEFAULT NULL AFTER `whatsapp_bot_sent`");
ensureColumnExists($conn, 'wedding_planners', 'first_contact_channel', "`first_contact_channel` VARCHAR(255) DEFAULT NULL AFTER `estatus`");
ensureColumnExists($conn, 'wedding_planners', 'eliminado', "`eliminado` TINYINT(1) NOT NULL DEFAULT 0 AFTER `afianzado`");
ensureColumnExists($conn, 'coordinadores_wp', 'how_did_you_meet', "`how_did_you_meet` TINYINT(1) NOT NULL DEFAULT 1 AFTER `nombre`");

$conn->query("UPDATE wedding_planners SET empresa_wp = full_name WHERE (empresa_wp IS NULL OR TRIM(empresa_wp) = '') AND full_name IS NOT NULL AND TRIM(full_name) <> ''");

// Asegurar que exista la columna afianzado en wedding_planners
$colCheck = $conn->query("SHOW COLUMNS FROM `wedding_planners` LIKE 'afianzado'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE `wedding_planners` ADD COLUMN `afianzado` TINYINT(1) NOT NULL DEFAULT 0");
}

function normalizeKnownUsLabel($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'N/A';
    }

    $map = [
        'less than 3 months'          => 'Menos de 6 meses',
        'less than 6 months'          => 'Menos de 6 meses',
        'between 3 months and 1 year' => 'Más de 6 meses',
        'more than 6 months'          => 'Más de 6 meses',
        'more than 1 year'            => 'Más de 6 meses',
        'not asked'                   => 'Menos de 6 meses',
    ];

    $key = mb_strtolower($raw, 'UTF-8');
    return $map[$key] ?? $raw;
}

function formatCoordinatorFirstContactLabelPhp($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'N/A';
    }

    $map = [
        'WhatsApp' => 'WhatsApp',
        'IG' => 'IG',
        'Facebook' => 'Facebook',
        'Email' => 'Correo electrónico',
        'Phone call' => 'Llamada telefónica',
    ];

    return $map[$raw] ?? $raw;
}

function getWeddingPlannerDisplayName(array $lead)
{
    $empresa = trim((string)($lead['empresa_wp'] ?? ''));
    if ($empresa !== '')
        return $empresa;

    $fullName = trim((string)($lead['full_name'] ?? ''));
    if ($fullName !== '')
        return $fullName;

    $campaign = trim((string)($lead['campaign_name'] ?? ''));
    if ($campaign !== '')
        return $campaign;

    return 'WP #' . intval($lead['id'] ?? 0);
}

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

function getAfianzadoStatusLabel($value)
{
    $status = intval($value);
    if ($status === 3) {
        return 'Nuevo';
    }
    if ($status === 1) {
        return 'Afianzado';
    }
    if ($status === 2) {
        return 'En proceso';
    }

    return 'No afianzado';
}

function getAfianzadoButtonClass($value)
{
    $status = intval($value);
    if ($status === 3) {
        return 'btn-dark';
    }
    if ($status === 1) {
        return 'btn-success';
    }
    if ($status === 2) {
        return 'btn-warning';
    }

    return 'btn-outline-secondary';
}

function formatCompactPlannerDate($dateString)
{
    if (empty($dateString)) {
        return 'Sin fecha';
    }

    $timestamp = strtotime((string) $dateString);
    if ($timestamp === false) {
        return (string) $dateString;
    }

    $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return date('j', $timestamp) . ' ' . $months[date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
}

function getPlannerActivityMeta($dateString)
{
    if (empty($dateString)) {
        return ['label' => 'Sin fecha', 'dot' => 'dot-gray', 'days' => null];
    }

    $timestamp = strtotime((string) $dateString);
    if ($timestamp === false) {
        return ['label' => (string) $dateString, 'dot' => 'dot-gray', 'days' => null];
    }

    $today = strtotime(date('Y-m-d'));
    $current = strtotime(date('Y-m-d', $timestamp));
    $daysDiff = (int) floor(($today - $current) / 86400);

    if ($daysDiff <= 0) {
        return ['label' => 'Hoy', 'dot' => 'dot-green', 'days' => 0];
    }
    if ($daysDiff === 1) {
        return ['label' => 'Ayer', 'dot' => 'dot-green', 'days' => 1];
    }
    if ($daysDiff < 10) {
        return ['label' => 'Hace ' . $daysDiff . 'd', 'dot' => 'dot-green', 'days' => $daysDiff];
    }

    return ['label' => 'Hace ' . $daysDiff . 'd', 'dot' => 'dot-red', 'days' => $daysDiff];
}

function parseDecimalValue($value)
{
    if ($value === null) {
        return 0.0;
    }

    if (is_numeric($value)) {
        return (float) $value;
    }

    $normalized = preg_replace('/[^0-9.,\-]/', '', (string) $value);
    if ($normalized === '' || $normalized === '-' || $normalized === '.' || $normalized === ',') {
        return 0.0;
    }

    if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
        $normalized = str_replace(',', '', $normalized);
    } elseif (strpos($normalized, ',') !== false) {
        $normalized = str_replace(',', '.', $normalized);
    }

    return is_numeric($normalized) ? (float) $normalized : 0.0;
}

function getLeadPipelineValue(array $lead)
{
    foreach (['monto_venta', 'monto', 'importe', 'presupuesto', 'budget'] as $field) {
        if (array_key_exists($field, $lead)) {
            $value = parseDecimalValue($lead[$field]);
            if ($value > 0) {
                return $value;
            }
        }
    }

    return 0.0;
}

function leadHasDealActive(array $lead)
{
    if (parseDecimalValue($lead['monto_venta'] ?? 0) > 0) {
        return true;
    }

    $paquete = trim((string) ($lead['paquete'] ?? ''));
    if ($paquete !== '' && strtolower($paquete) !== 'null') {
        return true;
    }

    if (intval($lead['puntos'] ?? 0) > 0) {
        return true;
    }

    return false;
}

function cwpIsValidWpPlannerId($wpId, array $validWpPlannerIds)
{
    $wpId = intval($wpId);
    if ($wpId <= 0) {
        return false;
    }

    if (empty($validWpPlannerIds)) {
        return true;
    }

    return isset($validWpPlannerIds[$wpId]);
}

function resolveWpEntityIdFromLead(array $lead, array $wpProfileIdByEmail, array $wpProfileEmailCount, array $wpProfileIdByPhone, array $wpProfilePhoneCount, array $validWpPlannerIds = [])
{
    $leadTable = trim((string) ($lead['tabla_origen'] ?? ''));
    if (strcasecmp($leadTable, 'wedding_planners') !== 0) {
        return 0;
    }

    $candidateId = intval($lead['id'] ?? 0);
    return cwpIsValidWpPlannerId($candidateId, $validWpPlannerIds) ? $candidateId : 0;
}

function getAfianzadoBadgeClass($value)
{
    $status = intval($value);
    if ($status === 1) {
        return 'badge-si';
    }
    if ($status === 2) {
        return 'badge-proc';
    }
    if ($status === 3) {
        return 'badge-nuevo';
    }

    return 'badge-no';
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
        'B' => '#3B82F6',  // azul
        'A' => '#10B981',  // verde
        'E' => '#8B5CF6',  // morado
        'L' => '#F59E0B',  // naranja
        'M' => '#EC4899',  // rosa
        'C' => '#06B6D4',  // cyan
        'D' => '#EF4444',  // rojo
    ];
    $initial = getVendedorInitial($nombre);
    return $colors[$initial] ?? '#64748B';
}

// Vista WP: replica la consulta base de consulta_leads para el universo sin filtro de plataforma.
$filterPlataforma = 'wedding_planners';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterEventos = isset($_GET['filter_eventos']) ? trim((string) $_GET['filter_eventos']) : '';
if (!in_array($filterEventos, ['', 'con_evento'], true)) {
    $filterEventos = '';
}
$showAll = isset($_GET['show_all']);

if ($showAll) {
    $startDate = '';
    $endDate = '';
    $searchQuery = '';
}

// En esta vista no forzamos rango por defecto: si no hay fechas, se muestran todos.

$platformLabel = 'Wedding Planner';


$tablas = [];
$sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo = 2 ORDER BY nombre";
$resultTablas = $conn->query($sqlTablas);
if ($resultTablas && $resultTablas->num_rows > 0) {
    while ($row = $resultTablas->fetch_assoc()) {
        $tablas[] = $row['nombre'];
    }
}

$allLeads = [];
$b1b2Values = [
    'b1', 'b1 (usa)', 'b1 usa',
    'b2', 'b2 (mx)', 'b2 mx'
];

foreach ($tablas as $tableName) {
    if ($tableName === 'wp_citas_leads') {
        continue;
    }

    $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $columns = [];
        $columnsResult = $conn->query("SHOW COLUMNS FROM `$tableName`");
        while ($columnsResult && $col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }

        $whereParts = [];

        if (in_array('descartado', $columns, true)) {
            $whereParts[] = "(descartado = 0 OR descartado IS NULL)";
        }

        if ($tableName === 'wedding_planners' && in_array('eliminado', $columns, true)) {
            $whereParts[] = "(eliminado = 0 OR eliminado IS NULL)";
        }

        if (($startDate !== '' || $endDate !== '') && in_array('created_time', $columns, true)) {
            $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
            $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
            $dateExtract = "CASE 
                WHEN created_time LIKE '%T%' THEN DATE(STR_TO_DATE(SUBSTRING(created_time, 1, 19), '%Y-%m-%dT%H:%i:%s'))
                ELSE DATE(created_time)
            END";
            if ($sd) {
                $whereParts[] = $dateExtract . " >= '" . $conn->real_escape_string($sd) . "'";
            }
            if ($ed) {
                $whereParts[] = $dateExtract . " <= '" . $conn->real_escape_string($ed) . "'";
            }
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

        if ($tableName === 'organic_leads' && in_array('campaign_name', $columns, true)) {
            $campaignCol = "LOWER(TRIM(campaign_name))";
            $inList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $b1b2Values)) . "'";
        }

        $whereSql = !empty($whereParts) ? implode(' AND ', $whereParts) : '1=1';
        $orderCol = in_array('created_time', $columns, true) ? 'created_time' : (in_array('id', $columns, true) ? 'id' : null);
        $orderSql = $orderCol ? " ORDER BY `$orderCol` DESC" : '';
        $sqlLeads = "SELECT *, '$tableName' AS tabla_origen FROM `$tableName` WHERE " . $whereSql . $orderSql;

        $resultLeads = $conn->query($sqlLeads);
        if ($resultLeads && $resultLeads->num_rows > 0) {
            while ($lead = $resultLeads->fetch_assoc()) {
                $allLeads[] = $lead;
            }
        }
    }
}

// Calcular conteo de leads del día actual basado en `created_time`
$todayCount = 0;
$today = date('Y-m-d');
foreach ($allLeads as $lead) {
    if (empty($lead['created_time']))
        continue;
    $ts = strtotime($lead['created_time']);
    if ($ts === false)
        continue;
    if (date('Y-m-d', $ts) === $today) {
        $todayCount++;
    }
}

// (Mantengo la mayor parte de la lógica original para estatus, maps y UI, igual que en consulta_leads.php)

// DEBUG: Log total leads before filtering
if ($startDate !== '' || $endDate !== '') {
    error_log("Total leads obtenidos de BD (allLeads): " . count($allLeads));
}

// En consulta WP se muestran todos los registros de tablas tipo WP (tipo = 2).
// Filtrar por vendedor asignado solo cuando aplica restricción (ya no para tipoUsu 1 ni 2).
if ($_cwpRestrictByVendedor) {
    $allLeads = array_values(array_filter($allLeads, function ($lead) use ($_cwpUserId) {
        return intval($lead['id_vendedor_asignado'] ?? 0) === $_cwpUserId;
    }));
}

// Eventos "atendidos": registrados en contact_form (pasar a evento desde cita o evento sin cita).
$eventosCountByWp = [];
$resEventosWp = $conn->query(
    "SELECT ev.wedding_planner_id, COUNT(DISTINCT ev.id) AS cnt
     FROM eventos_wp ev
     INNER JOIN contact_form cf ON cf.original_lead_id = ev.id
       AND LOWER(TRIM(COALESCE(cf.tabla_origen, ''))) = 'eventos_wp'
     WHERE ev.wedding_planner_id > 0
     GROUP BY ev.wedding_planner_id"
);
if ($resEventosWp) {
    while ($rowEv = $resEventosWp->fetch_assoc()) {
        $wpIdKey = intval($rowEv['wedding_planner_id'] ?? 0);
        if ($wpIdKey > 0) {
            $eventosCountByWp[$wpIdKey] = intval($rowEv['cnt'] ?? 0);
        }
    }
}

$filteredLeads = $allLeads;
if ($filterEventos === 'con_evento') {
    $filteredLeads = array_values(array_filter($filteredLeads, function ($lead) use ($eventosCountByWp) {
        if (strcasecmp(trim((string) ($lead['tabla_origen'] ?? '')), 'wedding_planners') !== 0) {
            return false;
        }

        $wpId = intval($lead['id'] ?? 0);

        return $wpId > 0 && intval($eventosCountByWp[$wpId] ?? 0) > 0;
    }));
}

// Ordenar por fecha de registro descendente (más reciente primero)
usort($filteredLeads, function ($a, $b) {
    $ta = strtotime((string) ($a['created_time'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['created_time'] ?? '')) ?: 0;

    if ($ta === $tb) {
        return intval($b['id'] ?? 0) <=> intval($a['id'] ?? 0);
    }

    return $tb <=> $ta;
});

// Recalcular conteo del dia con el mismo conjunto que se muestra en la tabla.
$todayCount = 0;
$today = date('Y-m-d');
foreach ($filteredLeads as $lead) {
    if (empty($lead['created_time'])) {
        continue;
    }
    $ts = strtotime((string) $lead['created_time']);
    if ($ts === false) {
        continue;
    }
    if (date('Y-m-d', $ts) === $today) {
        $todayCount++;
    }
}

// Recalcular conteos y mapas (copio la lógica existente para compatibilidad)
$totalCount = count($filteredLeads);

$map = [];
$minTs = null;
$maxTs = null;
foreach ($filteredLeads as $lead) {
    if (empty($lead['created_time']))
        continue;
    $ts = strtotime($lead['created_time']);
    if ($ts === false)
        continue;
    $d = date('Y-m-d', $ts);
    if (!isset($map[$d]))
        $map[$d] = 0;
    $map[$d]++;
    if ($minTs === null || $ts < $minTs)
        $minTs = $ts;
    if ($maxTs === null || $ts > $maxTs)
        $maxTs = $ts;
}

if ($minTs === null) {
    $seriesJson = json_encode([]);
    $datesJson = json_encode([]);
    $countsJson = json_encode([]);
} else {
    $start = strtotime(date('Y-m-d', $minTs));
    $end = strtotime(date('Y-m-d', $maxTs));
    $series = [];
    $dates = [];
    $counts = [];
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $d = date('Y-m-d', $ts);
        $c = isset($map[$d]) ? $map[$d] : 0;
        $series[] = [$ts * 1000, $c];
        $dates[] = $d;
        $counts[] = $c;
    }
    $seriesJson = json_encode($series);
    $datesJson = json_encode($dates);
    $countsJson = json_encode($counts);
}

$leadIdsByTable = [];
foreach ($filteredLeads as $lead) {
    $t = $lead['tabla_origen'] ?? '';
    $id = intval($lead['id'] ?? 0);
    if ($t === '' || $id <= 0)
        continue;
    if (!isset($leadIdsByTable[$t]))
        $leadIdsByTable[$t] = [];
    $leadIdsByTable[$t][] = $id;
}

// (Mantengo las siguientes secciones idénticas a `consulta_leads.php` para mapas, modales y JS)

$contactFormByLead = [];
$cfIds = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '')
        continue;
    $sql = "SELECT id, original_lead_id, cliente FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")";
    $resCf = $conn->query($sql);
    if ($resCf) {
        while ($row = $resCf->fetch_assoc()) {
            $key = $t . '|' . intval($row['original_lead_id']);
            $contactFormByLead[$key] = [
                'cf_id' => intval($row['id']),
                'cliente' => isset($row['cliente']) ? intval($row['cliente']) : 0
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
            if ($idclie <= 0)
                continue;

            if (!isset($appointmentsByCFId[$idclie])) {
                $appointmentsByCFId[$idclie] = $ar;
            } else {
                $prev = $appointmentsByCFId[$idclie];
                $replace = false;
                if (!empty($ar['fecha']) && !empty($prev['fecha'])) {
                    $t1 = strtotime($ar['fecha'] . ' ' . ($ar['hora'] ?? '')) ?: 0;
                    $t2 = strtotime($prev['fecha'] . ' ' . ($prev['hora'] ?? '')) ?: 0;
                    if ($t1 > $t2)
                        $replace = true;
                } elseif (!empty($ar['id']) && !empty($prev['id'])) {
                    if (intval($ar['id']) > intval($prev['id']))
                        $replace = true;
                }
                if ($replace)
                    $appointmentsByCFId[$idclie] = $ar;
            }
        }
    }
}

$leadLastInteractionByKey = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '') {
        continue;
    }

    $interactionSql = "SELECT lead_id, MAX(created_at) AS last_created_at
        FROM lead_interactions
        WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "')
          AND lead_id IN (" . $idsList . ")
        GROUP BY lead_id";
    $interactionRes = $conn->query($interactionSql);
    if ($interactionRes) {
        while ($interactionRow = $interactionRes->fetch_assoc()) {
            $key = $t . '|' . intval($interactionRow['lead_id'] ?? 0);
            $leadLastInteractionByKey[$key] = $interactionRow['last_created_at'] ?? '';
        }
    }
}

$leadStatusMap = [];
foreach ($leadIdsByTable as $t => $ids) {
    foreach ($ids as $lid) {
        $key = $t . '|' . intval($lid);
        $leadStatusMap[$key] = 'lead';
        if (isset($contactFormByLead[$key])) {
            $cf = $contactFormByLead[$key];
            if (isset($cf['cliente']) && intval($cf['cliente']) === 1) {
                $leadStatusMap[$key] = 'cliente';
                continue;
            }
            $cfId = intval($cf['cf_id']);
            if ($cfId > 0 && isset($appointmentsByCFId[$cfId]) && isset($appointmentsByCFId[$cfId]['estatus'])) {
                $rawStatus = $appointmentsByCFId[$cfId]['estatus'];
                $intStatus = is_numeric($rawStatus) ? intval($rawStatus) : null;
                if ($intStatus === 1) {
                    $leadStatusMap[$key] = 'atendido';
                } elseif ($intStatus === 3) {
                    $leadStatusMap[$key] = 'muerto';
                } elseif ($intStatus === 0) {
                    $leadStatusMap[$key] = 'agendado';
                } elseif ($intStatus === 2) {
                    $leadStatusMap[$key] = 'fantasma';
                } else {
                    $leadStatusMap[$key] = is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : 'agendado';
                }
            }
        }
    }
}

// Calcular leads que agendaron con la misma regla amplia de consulta_leads.
$leadsAgendadosTotal = 0;
$leadsAtendidosTotal = 0;
$leadsClientesTotal = 0;
$totalLeadsBeforeStatusFilter = count($filteredLeads);
foreach ($filteredLeads as $lead) {
    $tabla = $lead['tabla_origen'] ?? '';
    $lid = isset($lead['id']) ? intval($lead['id']) : 0;
    $key = $tabla . '|' . $lid;
    if (isset($leadStatusMap[$key])) {
        $status = strtolower($leadStatusMap[$key]);
        if (in_array($status, ['agendado', 'atendido', 'fantasma', 'muerto', 'cliente'], true)) {
            $leadsAgendadosTotal++;
        }
        if ($status === 'atendido') {
            $leadsAtendidosTotal++;
        }
        if ($status === 'cliente') {
            $leadsClientesTotal++;
        }
    }
}

$tasaConversion = $totalLeadsBeforeStatusFilter > 0 ? round(($leadsAgendadosTotal / $totalLeadsBeforeStatusFilter) * 100, 2) : 0;
$tasaAsistenciaPostQ = $leadsAgendadosTotal > 0 ? round(($leadsAtendidosTotal / $leadsAgendadosTotal) * 100, 2) : 0;
$tasaConversionClientes = $leadsAtendidosTotal > 0 ? round(($leadsClientesTotal / $leadsAtendidosTotal) * 100, 2) : 0;

// Reconstruir mapas finales para opens, wedding locations y scheduled
$opensMap = [];
$leadIdsByTable = [];
foreach ($filteredLeads as $lead) {
    $t = $lead['tabla_origen'] ?? '';
    $id = intval($lead['id'] ?? 0);
    if ($t === '' || $id <= 0)
        continue;
    if (!isset($leadIdsByTable[$t]))
        $leadIdsByTable[$t] = [];
    $leadIdsByTable[$t][] = $id;
}
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '')
        continue;
    $sql = "SELECT lead_id, correo FROM email_opens WHERE tabla_origen = '" . $safeTable . "' AND lead_id IN (" . $idsList . ")";
    $resOp = $conn->query($sql);
    if ($resOp) {
        while ($rowOp = $resOp->fetch_assoc()) {
            $key = $t . '|' . intval($rowOp['lead_id']) . '|' . intval($rowOp['correo']);
            $opensMap[$key] = true;
        }
    }
}

$weddingLocationsMap = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '')
        continue;
    $sql = "SELECT original_lead_id, wedding_location FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ") AND wedding_location IS NOT NULL AND wedding_location != ''";
    $resLoc = $conn->query($sql);
    if ($resLoc) {
        while ($rowLoc = $resLoc->fetch_assoc()) {
            $key = $t . '|' . intval($rowLoc['original_lead_id']);
            $weddingLocationsMap[$key] = $rowLoc['wedding_location'];
        }
    }
}

$scheduledLeadsMap = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '')
        continue;
    $sql = "SELECT original_lead_id FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")";
    $resSched = $conn->query($sql);
    if ($resSched) {
        while ($rowSched = $resSched->fetch_assoc()) {
            $key = $t . '|' . intval($rowSched['original_lead_id']);
            $scheduledLeadsMap[$key] = true;
        }
    }
}

// Verificar qué leads tienen conversación de WhatsApp (archivo de sesión existe)
$whatsappSessionsMap = [];
$sessionsDir = __DIR__ . '/../whatsapp/sessions';
if (is_dir($sessionsDir)) {
    foreach ($filteredLeads as $lead) {
        $phone = $lead['phone'] ?? '';
        if (empty($phone))
            continue;

        $phoneDigits = preg_replace('/\D+/', '', $phone);
        if (empty($phoneDigits))
            continue;

        $possibleFiles = [
            "whatsapp_{$phoneDigits}.json",
            "whatsapp_521{$phoneDigits}.json",
            "whatsapp_52{$phoneDigits}.json",
        ];

        if (strpos($phone, 'p:') === 0) {
            $phoneClean = preg_replace('/\D+/', '', substr($phone, 2));
            $possibleFiles[] = "whatsapp_{$phoneClean}.json";
            $possibleFiles[] = "whatsapp_521{$phoneClean}.json";
            $possibleFiles[] = "whatsapp_52{$phoneClean}.json";
        }

        if (strlen($phoneDigits) > 10) {
            $last10 = substr($phoneDigits, -10);
            $possibleFiles[] = "whatsapp_{$last10}.json";
            $possibleFiles[] = "whatsapp_521{$last10}.json";
        }

        $hasSession = false;
        foreach ($possibleFiles as $filename) {
            if (file_exists($sessionsDir . '/' . $filename)) {
                $hasSession = true;
                break;
            }
        }

        if ($hasSession) {
            $key = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);
            $whatsappSessionsMap[$key] = true;
        }
    }
}

// Obtener agentes (necesarios para registro manual de Wedding Planner y asignación en tabla)
$agentes = [];
$vendedorById = [];
$resA = $conn->query('SELECT id, nombre, apepat FROM usuarios WHERE tipoUsu IN (' . usuarioSqlInTiposAsesorEventoWp() . ') ORDER BY nombre, apepat');
if ($resA && $resA->num_rows > 0) {
    while ($ra = $resA->fetch_assoc()) {
        $agentes[] = $ra;
        $aid = intval($ra['id'] ?? 0);
        if ($aid > 0) {
            $vendedorById[$aid] = $ra;
        }
    }
}
if ($_cwpWpViewAsAdmin && $_cwpUserId > 0 && !isset($vendedorById[$_cwpUserId])) {
    $stmtSelfVendor = $conn->prepare('SELECT id, nombre, apepat FROM usuarios WHERE id = ? LIMIT 1');
    if ($stmtSelfVendor) {
        $stmtSelfVendor->bind_param('i', $_cwpUserId);
        $stmtSelfVendor->execute();
        $resSelfVendor = $stmtSelfVendor->get_result();
        if ($resSelfVendor && $resSelfVendor->num_rows > 0) {
            $selfVendor = $resSelfVendor->fetch_assoc();
            $agentes[] = $selfVendor;
            $vendedorById[$_cwpUserId] = $selfVendor;
        }
        $stmtSelfVendor->close();
    }
}

$wpVendedorIdByPlanner = [];
$wpAfianzadoByPlanner = [];
$resWpV = $conn->query('SELECT id, id_vendedor_asignado, afianzado FROM wedding_planners WHERE (eliminado = 0 OR eliminado IS NULL)');
if ($resWpV && $resWpV->num_rows > 0) {
    while ($rwv = $resWpV->fetch_assoc()) {
        $wpIdKey = intval($rwv['id'] ?? 0);
        if ($wpIdKey <= 0) {
            continue;
        }
        $wpVendedorIdByPlanner[$wpIdKey] = intval($rwv['id_vendedor_asignado'] ?? 0);
        $wpAfianzadoByPlanner[$wpIdKey] = intval($rwv['afianzado'] ?? 0);
    }
}

$cwpAgentesJs = [];
foreach ($agentes as $agente) {
    $nombreAgente = trim((string) ($agente['nombre'] ?? ''));
    $cwpAgentesJs[] = [
        'id' => intval($agente['id'] ?? 0),
        'label' => trim($nombreAgente . ' ' . ($agente['apepat'] ?? '')),
        'initial' => getVendedorInitial($nombreAgente),
        'color' => getVendedorColor($nombreAgente),
    ];
}

$diasBloqueados = [];
$resultBloqueoDias = $conn->query("SELECT fecha FROM dias_bloqueados");
if ($resultBloqueoDias && $resultBloqueoDias->num_rows > 0) {
    while ($rowBloqueo = $resultBloqueoDias->fetch_assoc()) {
        if (!empty($rowBloqueo['fecha'])) {
            $diasBloqueados[] = $rowBloqueo['fecha'];
        }
    }
}

$weddingPlanners = [];
// solo planners activos (estatus = 1) para el dropdown de coordinadores
$_wpDropdownWhere = 'WHERE estatus = 1 AND (eliminado = 0 OR eliminado IS NULL)';
if ($_cwpRestrictByVendedor) {
    $_wpDropdownWhere .= ' AND id_vendedor_asignado = ' . $_cwpUserId;
}
$resWp = $conn->query("SELECT id, campaign_name, full_name, empresa_wp FROM wedding_planners {$_wpDropdownWhere} ORDER BY COALESCE(NULLIF(TRIM(empresa_wp), ''), NULLIF(TRIM(full_name), ''), NULLIF(TRIM(campaign_name), ''), id), id");
if ($resWp && $resWp->num_rows > 0) {
    while ($rw = $resWp->fetch_assoc()) {
        $weddingPlanners[] = $rw;
    }
}

$wpProfileIdByEmail = [];
$wpProfileIdByPhone = [];
$wpProfileEmailCount = [];
$wpProfilePhoneCount = [];
$wpContactByPlanner = [];
$resWpProfileMap = $conn->query("SELECT id, email, phone, full_name, empresa_wp, campaign_name FROM wedding_planners WHERE (eliminado = 0 OR eliminado IS NULL)");
if ($resWpProfileMap) {
    while ($wpMap = $resWpProfileMap->fetch_assoc()) {
        $wpId = intval($wpMap['id'] ?? 0);
        if ($wpId <= 0) {
            continue;
        }

        $wpContactByPlanner[$wpId] = [
            'phone' => trim((string) ($wpMap['phone'] ?? '')),
            'email' => trim((string) ($wpMap['email'] ?? '')),
        ];

        $emailKey = mb_strtolower(trim((string)($wpMap['email'] ?? '')), 'UTF-8');
        if ($emailKey !== '') {
            $wpProfileEmailCount[$emailKey] = intval($wpProfileEmailCount[$emailKey] ?? 0) + 1;
            if (!isset($wpProfileIdByEmail[$emailKey])) {
                $wpProfileIdByEmail[$emailKey] = $wpId;
            }
        }

        $phoneDigits = preg_replace('/\D+/', '', (string)($wpMap['phone'] ?? ''));
        if ($phoneDigits !== '') {
            $wpProfilePhoneCount[$phoneDigits] = intval($wpProfilePhoneCount[$phoneDigits] ?? 0) + 1;
            if (!isset($wpProfileIdByPhone[$phoneDigits])) {
                $wpProfileIdByPhone[$phoneDigits] = $wpId;
            }
            if (strlen($phoneDigits) > 10) {
                $last10 = substr($phoneDigits, -10);
                if ($last10 !== '') {
                    $wpProfilePhoneCount[$last10] = intval($wpProfilePhoneCount[$last10] ?? 0) + 1;
                    if (!isset($wpProfileIdByPhone[$last10])) {
                        $wpProfileIdByPhone[$last10] = $wpId;
                    }
                }
            }
        }
    }
}

// Calcular conteo de coordinadores por Wedding Planner para mostrar en la lista
$coordinatorCounts = [];
$wpIds = [];
foreach ($filteredLeads as $lead) {
    $idWp = resolveWpEntityIdFromLead($lead, $wpProfileIdByEmail, $wpProfileEmailCount, $wpProfileIdByPhone, $wpProfilePhoneCount, $wpVendedorIdByPlanner);
    if ($idWp > 0) {
        $wpIds[] = $idWp;
    }
}
$wpIds = array_values(array_unique($wpIds));
if (!empty($wpIds)) {
    $idsList = implode(',', array_map('intval', $wpIds));
    $resCnt = $conn->query("SELECT id_wp, COUNT(*) AS cnt FROM coordinadores_wp WHERE id_wp IN (" . $idsList . ") GROUP BY id_wp");
    if ($resCnt) {
        while ($rc = $resCnt->fetch_assoc()) {
            $coordinatorCounts[intval($rc['id_wp'])] = intval($rc['cnt']);
        }
    }
}

$allCoordinators = [];
if (!empty($wpIds)) {
    $idsList = implode(',', array_map('intval', $wpIds));
    $sqlCoordinators = "SELECT c.id, c.id_wp, c.nombre, c.fecha_creacion,
            COALESCE(NULLIF(TRIM(wp.empresa_wp), ''), NULLIF(TRIM(wp.full_name), ''), NULLIF(TRIM(wp.campaign_name), ''), CONCAT('WP #', wp.id)) AS wp_display_name
        FROM coordinadores_wp c
        INNER JOIN wedding_planners wp ON wp.id = c.id_wp
        WHERE c.id_wp IN (" . $idsList . ")
          AND (wp.eliminado = 0 OR wp.eliminado IS NULL)
        ORDER BY c.fecha_creacion DESC, c.id DESC";
    $resCoordinators = $conn->query($sqlCoordinators);
    if ($resCoordinators) {
        while ($rowCoordinator = $resCoordinators->fetch_assoc()) {
            $allCoordinators[] = $rowCoordinator;
        }
    }
}

$afianzadosCount = 0;
$activeWeddingsByWp = [];
$dealBadgeByLeadKey = [];
$pipelineWpTotal = 0.0;
$stalePlanners = [];

// Recopilar los WP IDs visibles para restringir las consultas de bodas y pipeline
$visibleWpIds = [];
foreach ($filteredLeads as $lead) {
    $vid = resolveWpEntityIdFromLead($lead, $wpProfileIdByEmail, $wpProfileEmailCount, $wpProfileIdByPhone, $wpProfilePhoneCount, $wpVendedorIdByPlanner);
    if ($vid > 0) {
        $visibleWpIds[$vid] = true;
    }
}
$visibleWpIdsList = implode(',', array_map('intval', array_keys($visibleWpIds)));

// Contar y sumar bodas/pipeline: solo cuando contact_form.cliente = 1 (cliente real).
// contact_form.cliente = 4 es "cliente inminente" y NO debe contarse.
// Se une contact_form con eventos_wp para obtener el wedding_planner_id.
if ($visibleWpIdsList !== '') {
    $asesorFilter = '';
    if ($_cwpRestrictByVendedor) {
        $asesorFilter = " AND ev.id_asesor = " . intval($_cwpUserId);
    }

    $ewRes = $conn->query(
        "SELECT ev.wedding_planner_id, COUNT(*) AS cnt
         FROM contact_form cf
         INNER JOIN eventos_wp ev ON ev.id = cf.original_lead_id AND LOWER(cf.tabla_origen) = 'eventos_wp'
         WHERE cf.cliente = 1
           AND ev.wedding_planner_id IN (" . $visibleWpIdsList . ")"
        . $asesorFilter .
        " GROUP BY ev.wedding_planner_id"
    );
    if ($ewRes) {
        while ($ewRow = $ewRes->fetch_assoc()) {
            $ewWpId = intval($ewRow['wedding_planner_id'] ?? 0);
            if ($ewWpId > 0) {
                $activeWeddingsByWp[$ewWpId] = intval($ewRow['cnt']);
            }
        }
    }

    // Pipeline WP: suma de monto_venta solo de eventos que son cliente real (cf.cliente = 1)
    $pipRes = $conn->query(
        "SELECT COALESCE(SUM(cf.monto_venta), 0) AS total
         FROM contact_form cf
         INNER JOIN eventos_wp ev ON ev.id = cf.original_lead_id AND LOWER(cf.tabla_origen) = 'eventos_wp'
         WHERE cf.cliente = 1
           AND ev.wedding_planner_id IN (" . $visibleWpIdsList . ")"
        . $asesorFilter
    );
    if ($pipRes) {
        $pipRow = $pipRes->fetch_assoc();
        $pipelineWpTotal = floatval($pipRow['total'] ?? 0);
    }
}

foreach ($filteredLeads as $lead) {
    $rowKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);

    $dealBadgeByLeadKey[$rowKey] = leadHasDealActive($lead);

    if (strcasecmp((string)($lead['tabla_origen'] ?? ''), 'wedding_planners') !== 0) {
        continue;
    }
    if (intval($lead['afianzado'] ?? 0) === 1) {
        $afianzadosCount++;
    }

    $activityDate = $leadLastInteractionByKey[$rowKey] ?? ($lead['created_time'] ?? '');
    $activityMeta = getPlannerActivityMeta($activityDate);
    if (($activityMeta['days'] ?? null) !== null && $activityMeta['days'] >= 10) {
        $stalePlanners[] = [
            'name' => getWeddingPlannerDisplayName($lead),
            'id' => intval($lead['id'] ?? 0),
            'days' => intval($activityMeta['days']),
        ];
    }
}

usort($stalePlanners, function ($a, $b) {
    return intval($b['days'] ?? 0) <=> intval($a['days'] ?? 0);
});

$stalePlannerCount = count($stalePlanners);
$stalePlannerPreview = array_slice($stalePlanners, 0, 3);

// Sumar bodas activas solo de los planners visibles en filteredLeads
$activeWeddingsTotal = array_sum($activeWeddingsByWp);

function cwpNormalizePhoneForExport($phone)
{
    $phoneClean = preg_replace('/^\s*p:\s*/i', '', trim((string) $phone));
    if ($phoneClean === '') {
        return '';
    }

    $phoneNoPlus = str_replace('+', '', $phoneClean);
    return '="' . str_replace('"', '""', $phoneNoPlus) . '"';
}

function cwpBuildPlannerExportRows(array $filteredLeads, array $wpProfileIdByEmail, array $wpProfileEmailCount, array $wpProfileIdByPhone, array $wpProfilePhoneCount, array $wpVendedorIdByPlanner, array $wpContactByPlanner, array $allCoordinators)
{
    $coordinatorNamesByWp = [];
    foreach ($allCoordinators as $coord) {
        $wpId = intval($coord['id_wp'] ?? 0);
        if ($wpId <= 0) {
            continue;
        }
        $coordName = trim((string) ($coord['nombre'] ?? ''));
        if ($coordName === '') {
            continue;
        }
        if (!isset($coordinatorNamesByWp[$wpId])) {
            $coordinatorNamesByWp[$wpId] = [];
        }
        $coordinatorNamesByWp[$wpId][] = $coordName;
    }

    $exportRows = [];
    $seenKeys = [];

    foreach ($filteredLeads as $lead) {
        $wpEntityId = resolveWpEntityIdFromLead($lead, $wpProfileIdByEmail, $wpProfileEmailCount, $wpProfileIdByPhone, $wpProfilePhoneCount, $wpVendedorIdByPlanner);
        $exportKey = $wpEntityId > 0
            ? ('wp:' . $wpEntityId)
            : (($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0));
        if (isset($seenKeys[$exportKey])) {
            continue;
        }
        $seenKeys[$exportKey] = true;

        $nombre = getWeddingPlannerDisplayName($lead);
        $phone = '';
        $email = '';
        if ($wpEntityId > 0 && isset($wpContactByPlanner[$wpEntityId])) {
            $phone = trim((string) ($wpContactByPlanner[$wpEntityId]['phone'] ?? ''));
            $email = trim((string) ($wpContactByPlanner[$wpEntityId]['email'] ?? ''));
        }
        if ($phone === '') {
            $phone = preg_replace('/^\s*p:\s*/i', '', trim((string) ($lead['phone'] ?? '')));
        }
        if ($email === '') {
            $email = trim((string) ($lead['email'] ?? ''));
        }

        $coordinadores = '';
        if ($wpEntityId > 0 && !empty($coordinatorNamesByWp[$wpEntityId])) {
            $coordinadores = implode('; ', $coordinatorNamesByWp[$wpEntityId]);
        }

        $exportRows[] = [
            'nombre' => $nombre,
            'telefono' => $phone,
            'correo' => $email,
            'coordinadores' => $coordinadores,
        ];
    }

    return $exportRows;
}

$exportFormat = isset($_GET['export']) ? strtolower(trim((string) $_GET['export'])) : '';
if (in_array($exportFormat, ['csv', 'excel'], true)) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    $exportRows = cwpBuildPlannerExportRows(
        $filteredLeads,
        $wpProfileIdByEmail,
        $wpProfileEmailCount,
        $wpProfileIdByPhone,
        $wpProfilePhoneCount,
        $wpVendedorIdByPlanner,
        $wpContactByPlanner,
        $allCoordinators
    );
    $exportFilename = 'planners_wp_' . date('Ymd_His');

    if ($exportFormat === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $exportFilename . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Nombre', 'Teléfono', 'Correo', 'Coordinadores']);
        foreach ($exportRows as $row) {
            fputcsv($out, [
                $row['nombre'],
                cwpNormalizePhoneForExport($row['telefono']),
                $row['correo'],
                $row['coordinadores'],
            ]);
        }
        fclose($out);
        exit;
    }

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $exportFilename . '.xls"');
    echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';
    echo '<tr><th>Nombre</th><th>Teléfono</th><th>Correo</th><th>Coordinadores</th></tr>';
    foreach ($exportRows as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($row['telefono'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($row['correo'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($row['coordinadores'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

$cwpExportQuery = $_GET;
unset($cwpExportQuery['export']);
$cwpExportBaseQuery = http_build_query($cwpExportQuery);
$cwpExportCsvUrl = 'consulta_wp.php?' . ($cwpExportBaseQuery !== '' ? $cwpExportBaseQuery . '&' : '') . 'export=csv';
$cwpExportExcelUrl = 'consulta_wp.php?' . ($cwpExportBaseQuery !== '' ? $cwpExportBaseQuery . '&' : '') . 'export=excel';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuentas WP</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/heatmap.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .reportes-dashboard {
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
            --emerald-50: #ecfdf5;
            --emerald-600: #059669;
            --emerald-700: #047857;
            --emerald-800: #065f46;
            --blue-50: #eff6ff;
            --blue-600: #2563eb;
            --blue-700: #1d4ed8;
            --fuchsia-50: #fdf4ff;
            --fuchsia-600: #c026d3;
            --fuchsia-700: #a21caf;
            --amber-50: #fffbeb;
            --amber-600: #d97706;
            --amber-700: #b45309;
            --sky-50: #f0f9ff;
            --sky-600: #0284c7;
            --sky-700: #0369a1;
            --green-600: #16a34a;
            --orange-400: #fb923c;
            --lime-600: #65a30d;
            --white: #ffffff;
        }

        .reportes-dashboard * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .reportes-dashboard {
           
            color: var(--slate-900);
            line-height: 1.5;
            min-height: 100vh;
        }

        .reportes-dashboard .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .reportes-dashboard .flex {
            display: flex;
        }

        .reportes-dashboard .flex-col {
            flex-direction: column;
        }

        .reportes-dashboard .items-center {
            align-items: center;
        }

        .reportes-dashboard .items-start {
            align-items: flex-start;
        }

        .reportes-dashboard .justify-between {
            justify-content: space-between;
        }

        .reportes-dashboard .gap-2 {
            gap: 0.5rem;
        }

        .reportes-dashboard .gap-3 {
            gap: 0.75rem;
        }

        .reportes-dashboard .gap-4 {
            gap: 1rem;
        }

        .reportes-dashboard .gap-5 {
            gap: 1.25rem;
        }

        .reportes-dashboard .gap-6 {
            gap: 1.5rem;
        }

        .reportes-dashboard .space-y-3>*+* {
            margin-top: 0.75rem;
        }

        .reportes-dashboard .space-y-4>*+* {
            margin-top: 1rem;
        }

        .reportes-dashboard .grid {
            display: grid;
        }

        .reportes-dashboard .grid-cols-1 {
            grid-template-columns: 1fr;
        }

        .reportes-dashboard .grid-cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .reportes-dashboard .grid-cols-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        .reportes-dashboard .grid-cols-12 {
            grid-template-columns: repeat(12, 1fr);
        }

        .reportes-dashboard .rounded-2xl {
            border-radius: 1rem;
        }

        .reportes-dashboard .rounded-xl {
            border-radius: 0.75rem;
        }

        .reportes-dashboard .rounded-full {
            border-radius: 9999px;
        }

        .reportes-dashboard .h-2\.5 {
            height: 0.625rem;
        }

        .reportes-dashboard .w-2\.5 {
            width: 0.625rem;
        }

        .reportes-dashboard .min-w-0 {
            min-width: 0;
        }

        .reportes-dashboard .shrink-0 {
            flex-shrink: 0;
        }

        .reportes-dashboard .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .reportes-dashboard .p-3 {
            padding: 0.75rem;
        }

        .reportes-dashboard .p-4 {
            padding: 1rem;
        }

        .reportes-dashboard .p-5 {
            padding: 1.25rem;
        }

        .reportes-dashboard .p-6 {
            padding: 1.5rem;
        }

        .reportes-dashboard .px-4 {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .reportes-dashboard .px-5 {
            padding-left: 1.25rem;
            padding-right: 1.25rem;
        }

        .reportes-dashboard .px-2\.5 {
            padding-left: 0.625rem;
            padding-right: 0.625rem;
        }

        .reportes-dashboard .py-1 {
            padding-top: 0.25rem;
            padding-bottom: 0.25rem;
        }

        .reportes-dashboard .py-2 {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        .reportes-dashboard .py-4 {
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .reportes-dashboard .py-6 {
            padding-top: 1.5rem;
            padding-bottom: 1.5rem;
        }

        .reportes-dashboard .mt-1 {
            margin-top: 0.25rem;
        }

        .reportes-dashboard .mt-2 {
            margin-top: 0.5rem;
        }

        .reportes-dashboard .mt-3 {
            margin-top: 0.75rem;
        }

        .reportes-dashboard .mt-4 {
            margin-top: 1rem;
        }

        .reportes-dashboard .mt-6 {
            margin-top: 1.5rem;
        }

        .reportes-dashboard .mt-0\.5 {
            margin-top: 0.125rem;
        }

        .reportes-dashboard .mb-2 {
            margin-bottom: 0.5rem;
        }

        .reportes-dashboard .text-xs {
            font-size: 0.75rem;
            line-height: 1rem;
        }

        .reportes-dashboard .text-sm {
            font-size: 0.875rem;
            line-height: 1.25rem;
        }

        .reportes-dashboard .text-base {
            font-size: 1rem;
            line-height: 1.5rem;
        }

        .reportes-dashboard .text-lg {
            font-size: 1.125rem;
            line-height: 1.75rem;
        }

        .reportes-dashboard .text-2xl {
            font-size: 1.5rem;
            line-height: 2rem;
        }

        .reportes-dashboard .font-semibold {
            font-weight: 600;
        }

        .reportes-dashboard .font-bold {
            font-weight: 700;
        }

        .reportes-dashboard .text-right {
            text-align: right;
        }

        .reportes-dashboard .uppercase {
            text-transform: uppercase;
        }

        .reportes-dashboard .tracking-wide {
            letter-spacing: 0.025em;
        }

        .reportes-dashboard .tracking-tight {
            letter-spacing: -0.025em;
        }

        .reportes-dashboard .text-slate-500 {
            color: var(--slate-500);
        }

        .reportes-dashboard .text-slate-600 {
            color: var(--slate-600);
        }

        .reportes-dashboard .text-slate-700 {
            color: var(--slate-700);
        }

        .reportes-dashboard .text-slate-900 {
            color: var(--slate-900);
        }

        .reportes-dashboard .text-emerald-700 {
            color: var(--emerald-700);
        }

        .reportes-dashboard .text-blue-700 {
            color: var(--blue-700);
        }

        .reportes-dashboard .text-fuchsia-700 {
            color: var(--fuchsia-700);
        }

        .reportes-dashboard .text-amber-700 {
            color: var(--amber-700);
        }

        .reportes-dashboard .text-sky-700 {
            color: var(--sky-700);
        }

        .reportes-dashboard .bg-white {
            background-color: var(--white);
        }

        .reportes-dashboard .bg-slate-50 {
            background-color: var(--slate-50);
        }

        .reportes-dashboard .bg-blue-50 {
            background-color: var(--blue-50);
        }

        .reportes-dashboard .bg-emerald-50 {
            background-color: var(--emerald-50);
        }

        .reportes-dashboard .bg-amber-50 {
            background-color: var(--amber-50);
        }

        .reportes-dashboard .bg-fuchsia-50 {
            background-color: var(--fuchsia-50);
        }

        .reportes-dashboard .bg-sky-50 {
            background-color: var(--sky-50);
        }

        .reportes-dashboard .bg-gradient-emerald {
            background: linear-gradient(to right, var(--emerald-600), var(--sky-600));
        }

        .reportes-dashboard .bg-gradient-blue {
            background: linear-gradient(to right, var(--blue-600), var(--sky-600));
        }

        .reportes-dashboard .bg-gradient-sky {
            background: linear-gradient(to right, var(--sky-700), var(--blue-600));
        }

        .reportes-dashboard .bg-gradient-fuchsia {
            background: linear-gradient(to right, var(--fuchsia-600), var(--orange-400));
        }

        .reportes-dashboard .bg-gradient-green {
            background: linear-gradient(to right, var(--emerald-700), var(--lime-600));
        }

        /* Vendedor Badge with Circle */
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
            color: white;
            font-weight: 600;
            font-size: 13px;
            flex-shrink: 0;
        }

        .vendedor-name {
            font-weight: 500;
        }

        .js-assign-vendedor {
            cursor: pointer;
            transition: opacity 0.15s ease;
        }

        .js-assign-vendedor:hover {
            opacity: 0.82;
        }

        .vendedor-assign-cell .origin-badge.js-assign-vendedor:hover {
            background: #e2e8f0;
        }

        /* Updated Stats Cards - More Discrete */
        .wp-accounts-stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .wp-accounts-stat-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            transition: all 0.2s;
        }

        .wp-accounts-stat-card:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .wp-accounts-stat-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 6px;
            text-transform: none;
        }

        .wp-accounts-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
        }

        .wp-accounts-stat-value.success {
            color: #10b981;
        }

        .wp-accounts-stat-value.warning {
            color: #f59e0b;
        }

        .wp-accounts-stat-value.danger {
            color: #ef4444;
        }

        .reportes-dashboard .shadow-sm {
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .ring-1 {
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .ring-black\/5 {
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .ring-black\/10 {
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
        }

        .reportes-dashboard .border-b {
            border-bottom: 1px solid var(--slate-200);
        }

        .reportes-dashboard .overflow-x-auto {
            overflow-x: auto;
        }

        .reportes-dashboard .custom-scroll {
            scrollbar-width: thin;
            scrollbar-color: var(--slate-300) transparent;
        }

        .reportes-dashboard .custom-scroll::-webkit-scrollbar {
            height: 6px;
        }

        .reportes-dashboard .custom-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .reportes-dashboard .custom-scroll::-webkit-scrollbar-thumb {
            background-color: var(--slate-300);
            border-radius: 20px;
        }

        .reportes-dashboard .dashboard-header {
            position: sticky;
            top: 0;
            z-index: 30;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(8px);
        }

        .reportes-dashboard .card {
            border-radius: 1rem;
            background-color: var(--white);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .stat-card {
            border-radius: 1rem;
            padding: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .platform-card {
            min-width: 260px;
            max-width: 320px;
            flex-shrink: 0;
        }

        .reportes-dashboard .platform-card-top {
            height: 0.375rem;
            width: 100%;
            border-radius: 1rem 1rem 0 0;
        }

        .reportes-dashboard .progress-bar {
            height: 0.5rem;
            width: 100%;
            border-radius: 9999px;
            background-color: var(--slate-100);
            overflow: hidden;
        }

        .reportes-dashboard .progress-bar-fill {
            height: 100%;
            border-radius: 9999px;
        }

        .reportes-dashboard .rd-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 9999px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background-color: #8d774a;
            color: var(--white);
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
        }

        .reportes-dashboard .rd-btn i,
        .reportes-dashboard .btn i,
        .reportes-dashboard .btn-sm i,
        .reportes-dashboard table.table .btn i,
        .reportes-dashboard table.table .btn-sm i,
        .reportes-dashboard table.table button i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .reportes-dashboard .rd-btn:hover {
            background-color: #7b6840;
        }

        .reportes-dashboard .rd-btn-primary {
            background-color: #464646;
            color: var(--white);
        }

        .reportes-dashboard .rd-btn-primary:hover {
            background-color: #2f2f2f;
        }

        .reportes-dashboard .select-wrapper {
            position: relative;
        }

        .reportes-dashboard select.custom-select,
        .reportes-dashboard input.custom-input {
            appearance: none;
            border-radius: 9999px;
            background-color: var(--slate-50);
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--slate-900);
            border: 1px solid rgba(0, 0, 0, 0.1);
            width: 100%;
        }

        .reportes-dashboard .select-arrow {
            pointer-events: none;
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--slate-500);
        }

        .reportes-dashboard .search-input {
            width: 16rem;
            border-radius: 9999px;
            background-color: var(--slate-50);
            padding: 0.5rem 0.75rem 0.5rem 2.5rem;
            font-size: 0.875rem;
            color: var(--slate-900);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .reportes-dashboard .search-icon {
            pointer-events: none;
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--slate-400);
        }

        .reportes-dashboard .table-header {
            background-color: var(--slate-50);
            padding: 0.5rem 1rem;
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--slate-600);
        }

        .reportes-dashboard .table-row {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }

        .reportes-dashboard .tabular-nums {
            font-variant-numeric: tabular-nums;
        }

        .reportes-dashboard .space-y-3>*+* {
            margin-top: 0.75rem;
        }

        .reportes-dashboard .divide-y>*+* {
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .keyword-card {
            border-radius: 1rem;
            background-color: var(--slate-50);
            padding: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .star-card {
            border-radius: 1rem;
            background-color: var(--slate-50);
            padding: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .chart-container {
            height: 20rem;
            width: 100%;
        }

        .reportes-dashboard .btn,
        .reportes-dashboard .btn-sm {
            border-radius: 9999px;
            font-weight: 600;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: background-color 0.2s, border-color 0.2s, color 0.2s;
        }

        .reportes-dashboard .btn-primary {
            background-color: var(--slate-900);
            border-color: var(--slate-900);
            color: var(--white);
        }

        .reportes-dashboard .btn-primary:hover {
            background-color: var(--slate-800);
            border-color: var(--slate-800);
            color: var(--white);
        }

        .reportes-dashboard .btn-secondary {
            background-color: var(--slate-100);
            border-color: var(--slate-200);
            color: var(--slate-900);
        }

        .reportes-dashboard .btn-secondary:hover {
            background-color: var(--slate-200);
            border-color: var(--slate-300);
        }

        .reportes-dashboard .btn-success {
            background-color: var(--emerald-600);
            border-color: var(--emerald-600);
            color: var(--white);
        }

        .reportes-dashboard .btn-success:hover {
            background-color: var(--emerald-700);
            border-color: var(--emerald-700);
            color: var(--white);
        }

        .reportes-dashboard .btn-success:disabled,
        .reportes-dashboard .btn-success.disabled {
            background-color: var(--emerald-600);
            border-color: var(--emerald-600);
            color: var(--white);
            opacity: 1;
        }

        .reportes-dashboard .btn-danger {
            background-color: #dc2626;
            border-color: #dc2626;
            color: var(--white);
        }

        .reportes-dashboard .btn-warning {
            background-color: var(--amber-600);
            border-color: var(--amber-600);
            color: var(--white);
        }

        .reportes-dashboard .btn-info {
            background-color: var(--sky-600);
            border-color: var(--sky-600);
            color: var(--white);
        }

        .reportes-dashboard .btn-outline {
            background-color: transparent;
            border-color: var(--slate-300);
            color: var(--slate-900);
        }

        .reportes-dashboard .btn-outline:hover {
            background-color: var(--slate-100);
        }

        .reportes-dashboard table.table .btn,
        .reportes-dashboard table.table .btn-sm,
        .reportes-dashboard table.table button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 9999px;
            padding: 0.4rem 0.9rem;
            font-weight: 600;
            font-size: 0.8125rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background-color: var(--slate-50);
            color: var(--slate-900);
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s, color 0.2s;
        }

        .reportes-dashboard table.table .btn:hover,
        .reportes-dashboard table.table .btn-sm:hover,
        .reportes-dashboard table.table button:hover {
            background-color: var(--slate-100);
        }

        .reportes-dashboard table.table .btn:disabled,
        .reportes-dashboard table.table .btn-sm:disabled,
        .reportes-dashboard table.table button:disabled {
            background-color: var(--slate-100);
            border-color: var(--slate-200);
            color: var(--slate-500);
            cursor: not-allowed;
        }

        .reportes-dashboard table.table .btn-success,
        .reportes-dashboard table.table .btn-success:hover {
            background-color: #198754;
            border-color: #198754;
            color: #ffffff;
        }

        .reportes-dashboard table.table .btn-success:disabled,
        .reportes-dashboard table.table .btn-success.disabled {
            background-color: #198754;
            border-color: #198754;
            color: #ffffff;
            opacity: 1;
        }

        .reportes-dashboard table.table .btn-dark,
        .reportes-dashboard table.table .btn-dark:hover {
            background-color: #343a40;
            border-color: #343a40;
            color: #ffffff;
        }

        .reportes-dashboard table.table .btn-dark:disabled,
        .reportes-dashboard table.table .btn-dark.disabled {
            background-color: #343a40;
            border-color: #343a40;
            color: #ffffff;
            opacity: 1;
        }

        .reportes-dashboard table.table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
            font-size: 0.875rem;
            color: var(--slate-900);
        }

        .reportes-dashboard table.table thead th {
            background-color: var(--slate-50);
            color: var(--slate-600);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--slate-200);
            padding: 0.75rem 1rem;
            white-space: nowrap;
        }

        .reportes-dashboard table.table tbody td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--slate-100);
            vertical-align: middle;
        }

        .reportes-dashboard table.table tbody tr:hover {
            background-color: var(--slate-50);
        }

        .reportes-dashboard table.table-striped tbody tr:nth-of-type(odd) {
            background-color: #fbfdff;
        }

        .reportes-dashboard table.table-striped tbody tr:nth-of-type(odd):hover {
            background-color: var(--slate-50);
        }

        .reportes-dashboard .table-responsive,
        .reportes-dashboard .overflow-x-auto {
            border-radius: 1rem;
        }

        @media (min-width: 640px) {
            .reportes-dashboard .sm\:flex-row {
                flex-direction: row;
            }

            .reportes-dashboard .sm\:items-center {
                align-items: center;
            }

            .reportes-dashboard .sm\:justify-between {
                justify-content: space-between;
            }
        }

        @media (min-width: 768px) {
            .reportes-dashboard .md\:grid-cols-2 {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .reportes-dashboard .lg\:grid-cols-4 {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (min-width: 1280px) {
            .reportes-dashboard .xl\:grid-cols-3 {
                grid-template-columns: repeat(3, 1fr);
            }

            .reportes-dashboard .xl\:grid-cols-4 {
                grid-template-columns: repeat(4, 1fr);
            }

            .reportes-dashboard .xl\:col-span-3 {
                grid-column: span 3 / span 3;
            }

            .reportes-dashboard .xl\:col-span-1 {
                grid-column: span 1 / span 1;
            }
        }

        .reportes-dashboard .relationship-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .reportes-dashboard .relationship-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.75rem 1rem;
            border: 1px solid var(--slate-200);
            border-radius: 9999px;
            background: var(--white);
            color: var(--slate-700);
            font-weight: 700;
        }

        .reportes-dashboard .relationship-tab-btn.active {
            background: var(--slate-900);
            border-color: var(--slate-900);
            color: var(--white);
        }

        .reportes-dashboard .relationship-tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.75rem;
            height: 1.75rem;
            padding: 0 0.5rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.18);
            font-size: 0.75rem;
            line-height: 1;
        }

        .reportes-dashboard .relationship-tab-btn:not(.active) .relationship-tab-count {
            background: var(--slate-100);
            color: var(--slate-700);
        }

        .reportes-dashboard .tab-context-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1rem;
            border-radius: 0.9rem;
            border: 1px solid var(--slate-200);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.08), rgba(2, 132, 199, 0.04));
            color: var(--slate-700);
        }

        .reportes-dashboard .tab-context-banner strong {
            color: var(--slate-900);
        }

        .reportes-dashboard .tab-context-banner.is-focused {
            border-color: rgba(37, 99, 235, 0.3);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.14), rgba(6, 95, 70, 0.08));
        }

        .reportes-dashboard .tab-context-banner .context-reset-btn {
            white-space: nowrap;
        }

        .reportes-dashboard .relation-highlight {
            background-color: rgba(37, 99, 235, 0.08) !important;
            box-shadow: inset 4px 0 0 var(--blue-600);
        }

        .reportes-dashboard .relation-muted {
            opacity: 0.4;
        }

        .reportes-dashboard .relation-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            background: var(--blue-50);
            color: var(--blue-700);
            font-size: 0.75rem;
            font-weight: 700;
        }

        .reportes-dashboard .coordinator-empty-state {
            padding: 2rem 1rem;
            text-align: center;
            color: var(--slate-500);
        }

        table {
            width: 100% !important;
        }

        /* Columna de asignación más ancha para mejor visibilidad */
        .assign-col {
            width: 220px;
            min-width: 160px;
        }

        /* Columna de origen más compacta y centrada */
        .origen-col {
            width: 80px;
            min-width: 60px;
            text-align: center;
            font-size: 0.85rem;
        }

        /* Limitar ancho de la columna "¿Dónde es la boda?" */
        th[data-column="boda"],
        td[data-column="boda"] {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Estatus coloring similar to consulta_post_leads.php */
        .status-agendado {
            color: #7b1fa2;
            font-weight: 700;
        }

        .status-fantasma {
            color: #dc3545;
            font-weight: 700;
        }

        .status-atendido {
            color: #007bff;
            font-weight: 700;
        }

        .status-muerto {
            color: #dc3545;
            font-weight: 700;
        }

        .status-cliente {
            color: #28a745;
            font-weight: 700;
        }

        .status-default {
            font-weight: 700;
            color: inherit;
        }

        @media (max-width: 767px) {
            .assign-col {
                width: 140px;
                min-width: 110px;
            }

            .reportes-dashboard .tab-context-banner {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* ===== REGISTRAR LEAD MANUAL STYLE FOR WP MODALS ===== */
        .rlm-modal .modal-content {
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
            border: none;
        }

        .rlm-modal .modal-header {
            background: #eee8dc;
            border-bottom: none;
            padding: 22px 28px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .rlm-modal .modal-header h5 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #464646;
            margin: 0 0 4px;
        }

        .rlm-modal .modal-header .rlm-subtitle {
            font-size: 0.8rem;
            color: #464646;
            opacity: 0.72;
            margin: 0;
        }

        .rlm-modal .modal-body {
            padding: 28px 32px;
            overflow-y: auto;
            max-height: 60vh;
        }

        .rlm-section {
            margin-bottom: 28px;
        }

        .rlm-section-title {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
        }

        .rlm-section-number {
            width: 32px;
            height: 32px;
            background: #464646;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .rlm-section-heading h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1rem;
            color: #464646;
        }

        .rlm-section-heading p {
            margin: 2px 0 0;
            font-size: 0.8rem;
            color: #777;
        }

        .rlm-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .rlm-field {
            margin-bottom: 14px;
        }

        .rlm-field label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 4px;
            display: block;
        }

        .rlm-tag {
            display: inline-block;
            background: #464646;
            color: #eee8dc;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 4px;
            margin-left: 6px;
        }

        .rlm-optional {
            font-size: 0.7rem;
            color: #999;
            margin-left: 6px;
        }

        .rlm-field input[type="text"],
        .rlm-field input[type="email"],
        .rlm-field input[type="date"],
        .rlm-field select {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 0.9rem;
            color: #333;
            outline: none;
            transition: border-color 0.2s;
            background: #fff;
        }

        .rlm-field input:focus,
        .rlm-field select:focus {
            border-color: #464646;
        }

        .rlm-choice-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 8px;
        }

        .rlm-choice-card {
            border: 1px solid #ddd;
            border-radius: 12px;
            background: #fff;
            padding: 16px 14px;
            min-height: 96px;
            cursor: pointer;
            text-align: left;
            transition: all 0.18s ease;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .rlm-choice-card:hover {
            border-color: #464646;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }

        .rlm-choice-card.active {
            border-color: #464646;
            background: #f5f1e8;
            box-shadow: 0 10px 24px rgba(70, 70, 70, 0.12);
        }

        .rlm-choice-card-title {
            display: block;
            font-size: 0.95rem;
            font-weight: 700;
            color: #464646;
            margin-bottom: 6px;
        }

        .rlm-choice-card-desc {
            display: block;
            font-size: 0.8rem;
            line-height: 1.45;
            color: #777;
        }

        .rlm-engagement-btns {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 8px;
        }

        .rlm-engagement-btns.is-invalid {
            padding: 10px;
            border: 1px solid #dc2626;
            border-radius: 12px;
            background: rgba(220, 38, 38, 0.04);
        }

        .rlm-engagement-card {
            border: 1px solid #ddd;
            border-radius: 12px;
            background: #fff;
            padding: 16px 14px;
            min-height: 96px;
            cursor: pointer;
            text-align: left;
            transition: all 0.18s ease;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .rlm-engagement-card:hover {
            border-color: #464646;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }

        .rlm-engagement-card.active {
            border-color: #464646;
            background: #f5f1e8;
            box-shadow: 0 10px 24px rgba(70, 70, 70, 0.12);
        }

        .rlm-engagement-icon {
            display: block;
            font-size: 1.4rem;
            margin-bottom: 8px;
        }

        .rlm-engagement-name {
            display: block;
            font-size: 0.95rem;
            font-weight: 700;
            color: #464646;
            margin-bottom: 6px;
        }

        .rlm-engagement-desc {
            display: block;
            font-size: 0.8rem;
            line-height: 1.45;
            color: #777;
        }

        .rlm-modal .modal-footer {
            display: flex;
            gap: 12px;
            padding: 20px 32px 28px;
            border-top: 1px solid #eee;
            background: #fff;
        }

        .rlm-modal .modal-footer .rlm-btn-cancel {
            flex: 1;
            padding: 12px;
            border: 1.5px solid #ddd;
            border-radius: 10px;
            background: #fff;
            color: #555;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.15s;
        }

        .rlm-modal .modal-footer .rlm-btn-cancel:hover {
            background: #f5f5f5;
        }

        .rlm-modal .modal-footer .rlm-btn-submit {
            flex: 3;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: #eee8dc;
            color: #464646;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.15s;
        }

        .rlm-modal .modal-footer .rlm-btn-submit:hover {
            background: #464646;
            color: #eee8dc;
        }

        @media (max-width: 600px) {
            .rlm-modal .modal-body {
                padding: 20px 16px;
            }

            .rlm-modal .modal-footer {
                padding: 16px;
            }

            .rlm-modal .modal-header {
                padding: 18px 16px;
            }

            .rlm-grid-2,
            .rlm-choice-grid,
            .rlm-engagement-btns {
                grid-template-columns: 1fr;
            }
        }


        .wp-accounts-page {
            min-height: 100vh;
            color: #1a1d23;
            font-family: 'DM Sans', sans-serif;
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
            position: sticky;
            top: 60px;
            margin: 60px 8px 0;
            border-radius: 12px;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07), 0 1px 2px rgba(0, 0, 0, 0.04);
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
        }

        .wp-accounts-topbar-left p {
            font-size: 12px;
            color: #9ca3af;
            margin: 0;
            line-height: 1.35;
        }

        .wp-accounts-topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .wp-accounts-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
            white-space: nowrap;
            text-decoration: none;
        }

        .wp-accounts-action-btn.ghost {
            background: transparent;
            border: 1.5px solid #e2e5ea;
            color: #1a1d23;
        }

        .wp-accounts-action-btn.ghost:hover {
            background: #f4f5f7;
            border-color: #c5cad4;
        }

        .wp-accounts-action-btn.primary {
            background: #111827;
            color: #ffffff;
        }

        .wp-accounts-action-btn.primary:hover {
            background: #374151;
        }

        .wp-accounts-main {
            padding: 24px 12px 48px;
            max-width: 100%;
            margin: 0;
        }

        .wp-accounts-tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e2e5ea;
            margin-bottom: 22px;
        }

        .wp-accounts-tab {
            padding: 10px 18px 11px;
            font-size: 14px;
            font-weight: 500;
            color: #6b7280;
            cursor: pointer;
            border: none;
            background: transparent;
            font-family: inherit;
            border-bottom: 2.5px solid transparent;
            margin-bottom: -2px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.15s;
        }

        .wp-accounts-tab:hover {
            color: #1a1d23;
        }

        .wp-accounts-tab.active {
            color: #1a1d23;
            font-weight: 700;
            border-bottom-color: #111827;
        }

        .wp-accounts-tab-badge {
            background: #f4f5f7;
            border: 1px solid #e2e5ea;
            color: #6b7280;
            font-size: 11px;
            font-weight: 600;
            padding: 1px 7px;
            border-radius: 20px;
            font-family: 'DM Mono', monospace;
        }

        .wp-accounts-tab.active .wp-accounts-tab-badge {
            background: #111827;
            color: #ffffff;
            border-color: #111827;
        }

        .wp-accounts-banner-info,
        .wp-accounts-context-banner {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: #1e40af;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-weight: 500;
        }

        .wp-accounts-context-banner strong {
            color: #1e3a8a;
        }

        /* Old stats grid styles removed - using new discrete design */

        .wp-accounts-banner-alert {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 11px 16px;
            font-size: 13px;
            color: #92400e;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .wp-accounts-alert-dot {
            width: 8px;
            height: 8px;
            background: #f59e0b;
            border-radius: 50%;
            flex-shrink: 0;
            animation: accounts-pulse 2s ease-in-out infinite;
        }

        @keyframes accounts-pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.6;
                transform: scale(0.85);
            }
        }

        .wp-accounts-filterbar {
            background: #ffffff;
            border: 1px solid #e2e5ea;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07), 0 1px 2px rgba(0, 0, 0, 0.04);
            padding: 14px 16px;
            margin-bottom: 22px;
        }

        .wp-accounts-filterbar form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .wp-accounts-filter-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }

        .wp-accounts-input,
        .wp-accounts-search {
            border: 1px solid #e2e5ea;
            border-radius: 8px;
            background: #ffffff;
            padding: 9px 12px;
            font-size: 13px;
            color: #1a1d23;
            min-height: 40px;
        }

        .wp-accounts-search-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            min-width: 260px;
        }

        .wp-accounts-search-wrap .search-icon {
            position: absolute;
            left: 12px;
            color: #9ca3af;
        }

        .wp-accounts-search {
            width: 100%;
            padding-left: 34px;
            padding-right: 34px;
        }

        .wp-accounts-search-clear {
            display: none;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            line-height: 1;
            font-size: 13px;
        }

        .wp-accounts-search-count {
            font-size: 12px;
            color: #6b7280;
            white-space: nowrap;
        }

        .wp-accounts-table-wrapper {
            background: #ffffff;
            border: 1px solid #e2e5ea;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07), 0 1px 2px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            margin-bottom: 18px;
        }

        .wp-accounts-table-scroll {
            overflow-x: auto;
        }

        .wp-accounts-table-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 10px 16px;
            border-top: 1px solid #e2e5ea;
            background: #fafafa;
            font-size: 12px;
            color: #6b7280;
        }

        .wp-accounts-table-footer strong {
            color: #374151;
            font-weight: 600;
        }

        .wp-accounts-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1040px;
        }

        .wp-accounts-table thead th {
            padding: 12px 16px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #9ca3af;
            text-align: left;
            background: #fafafa;
            border-bottom: 1px solid #e2e5ea;
            white-space: nowrap;
        }

        .wp-accounts-table tbody tr {
            border-bottom: 1px solid #e2e5ea;
            transition: background 0.12s;
        }

        .wp-accounts-table tbody tr:last-child {
            border-bottom: none;
        }

        .wp-accounts-table tbody tr:hover {
            background: #fafbff;
        }

        .wp-accounts-table td {
            padding: 14px 16px;
            vertical-align: middle;
        }

        .td-id,
        .td-origin,
        .td-date {
            font-family: 'DM Mono', monospace;
            font-size: 12px;
            color: #9ca3af;
            font-weight: 500;
            white-space: nowrap;
        }

        .planner-name {
            font-weight: 600;
            font-size: 14px;
            color: #1a1d23;
        }

        .planner-name-link {
            text-decoration: none;
            color: inherit;
            display: inline-block;
        }

        .planner-name-link:hover {
            color: #111827;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .planner-date {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .relation-badge,
        .badge-coord,
        .origin-badge {
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

        .afianzado-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .js-assign-afianzado {
            cursor: pointer;
            transition: opacity 0.15s ease;
        }

        .js-assign-afianzado:hover {
            opacity: 0.82;
        }

        .badge-si {
            background: #ecfdf5;
            color: #065f46;
        }

        .badge-no {
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #e2e5ea;
        }

        .badge-proc {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
        }

        .badge-nuevo {
            background: #ede9fe;
            color: #5b21b6;
            border: 1px solid #ddd6fe;
        }

        .activity-cell {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            font-weight: 500;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .dot-green {
            background: #10b981;
        }

        .dot-red {
            background: #ef4444;
        }

        .dot-gray {
            background: #d1d5db;
        }

        .wp-accounts-view-btn {
            padding: 6px 14px;
            border-radius: 7px;
            border: 1.5px solid #e2e5ea;
            background: #ffffff;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            color: #1a1d23;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .wp-accounts-view-btn:hover {
            background: #111827;
            color: #ffffff;
            border-color: #111827;
        }

        .wp-accounts-actions-cell {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }

        .wp-accounts-delete-btn {
            padding: 6px 14px;
            border-radius: 7px;
            border: 1.5px solid #fecaca;
            background: #fff5f5;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            color: #b91c1c;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .wp-accounts-delete-btn:hover {
            background: #b91c1c;
            color: #ffffff;
            border-color: #b91c1c;
        }

        .coordinator-empty-state {
            padding: 28px 20px;
            text-align: center;
            color: #6b7280;
            font-size: 13px;
        }

        .relation-highlight {
            background-color: #eff6ff !important;
            box-shadow: inset 3px 0 0 #3b82f6;
        }

        .relation-muted {
            opacity: 0.45;
        }

        @media (max-width: 980px) {
            .wp-accounts-topbar,
            .wp-accounts-topbar-left,
            .wp-accounts-topbar-actions {
                flex-wrap: wrap;
            }

            .wp-accounts-topbar {
                height: auto;
                padding: 14px 20px;
                min-height: 0;
                top: 10px;
                margin: 10px 10px 0;
            }

            .wp-accounts-main {
                padding: 16px;
            }

            .wp-accounts-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .wp-accounts-filterbar form {
                align-items: stretch;
            }

            .wp-accounts-search-wrap {
                min-width: 100%;
            }
        }

        @media (max-width: 640px) {
            .wp-accounts-stat-card {
                flex: 1 1 100%;
                border-right: none;
            }

            .wp-accounts-tabs {
                overflow-x: auto;
            }

            .wp-accounts-context-banner,
            .wp-accounts-banner-info,
            .wp-accounts-banner-alert {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
    <div class="wp-accounts-page">
        <header class="wp-accounts-topbar">
            <div class="wp-accounts-topbar-left">
                <h1>Cuentas WP</h1>
                <p>Directorio completo · Plataforma: <?php echo htmlspecialchars($platformLabel, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="wp-accounts-topbar-actions">
                <a href="<?php echo htmlspecialchars($cwpExportCsvUrl, ENT_QUOTES, 'UTF-8'); ?>" class="wp-accounts-action-btn ghost" title="Descargar listado en CSV">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </a>
                <a href="<?php echo htmlspecialchars($cwpExportExcelUrl, ENT_QUOTES, 'UTF-8'); ?>" class="wp-accounts-action-btn ghost" title="Descargar listado en Excel">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </a>
                <button type="button" class="wp-accounts-action-btn primary" data-bs-toggle="modal" data-bs-target="#registroManualWeddingModal">+ Registrar WP</button>
            </div>
        </header>

        <main class="wp-accounts-main">


            <div class="wp-accounts-banner-info">
                <span>Vista compartida — todas las vendedoras y Lead Qualifier ven quién lleva cada cuenta para redirigir correctamente.</span>
            </div>

            <div class="wp-accounts-stats-grid">
                <div class="wp-accounts-stat-card">
                    <div class="wp-accounts-stat-label">Total planners</div>
                    <div class="wp-accounts-stat-value"><?php echo intval($totalCount); ?></div>
                </div>
                <div class="wp-accounts-stat-card">
                    <div class="wp-accounts-stat-label">Afianzados</div>
                    <div class="wp-accounts-stat-value success"><?php echo intval($afianzadosCount); ?></div>
                </div>
                <div class="wp-accounts-stat-card">
                    <div class="wp-accounts-stat-label">Bodas activas</div>
                    <div class="wp-accounts-stat-value"><?php echo intval($activeWeddingsTotal); ?></div>
                </div>
                <div class="wp-accounts-stat-card">
                    <div class="wp-accounts-stat-label">Sin actividad +10d</div>
                    <div class="wp-accounts-stat-value danger"><?php echo intval($stalePlannerCount); ?></div>
                </div>
                <div class="wp-accounts-stat-card">
                    <div class="wp-accounts-stat-label">Pipeline WP</div>
                    <div class="wp-accounts-stat-value">$<?php echo number_format($pipelineWpTotal, 0); ?></div>
                </div>
            </div>

            <div class="wp-accounts-banner-alert">
                <div class="wp-accounts-alert-dot"></div>
                <span>
                    <?php if (!empty($stalePlannerPreview)): ?>
                        ⚠ <strong>Top 3 con más días sin actividad:</strong>
                        <?php echo htmlspecialchars(implode(' · ', array_map(function ($planner) {
                            return ($planner['name'] ?? 'WP') . ' (' . intval($planner['days'] ?? 0) . 'd)';
                        }, $stalePlannerPreview)), ENT_QUOTES, 'UTF-8'); ?>
                    <?php else: ?>
                        No hay wedding planners con más de 10 días sin actividad en la vista actual.
                    <?php endif; ?>
                </span>
            </div>

            <div class="wp-accounts-filterbar">
                <form method="get" id="filterForm">
                    <select name="vendedor" class="wp-accounts-input">
                        <option value="">Todos los asesores</option>
                        <?php foreach ($agentes as $agente): ?>
                            <option value="<?php echo intval($agente['id']); ?>"><?php echo htmlspecialchars(trim(($agente['nombre'] ?? '') . ' ' . ($agente['apepat'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="afianzamiento" class="wp-accounts-input">
                        <option value="">Afianzamiento</option>
                        <option value="0">No afianzado</option>
                        <option value="1">Afianzado</option>
                        <option value="2">En proceso</option>
                        <option value="3">Nuevo</option>
                    </select>
                    <select name="actividad" class="wp-accounts-input">
                        <option value="">Actividad</option>
                        <option value="hoy">Hoy</option>
                        <option value="ayer">Ayer</option>
                        <option value="7d">Últimos 7 días</option>
                        <option value="mas10d">Más de 10 días</option>
                    </select>
                    <select name="filter_eventos" class="wp-accounts-input" onchange="this.form.submit()">
                        <option value=""<?php echo $filterEventos === '' ? ' selected' : ''; ?>>Todos los planners</option>
                        <option value="con_evento"<?php echo $filterEventos === 'con_evento' ? ' selected' : ''; ?>>Solo con evento atendido</option>
                    </select>
                    <div class="wp-accounts-search-wrap">
                        <i class="search-icon fas fa-search"></i>
                        <input type="text" id="leadsSearchInput" name="search" form="filterForm" class="wp-accounts-search"
                            placeholder="Buscar planner..."
                            value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>"
                            autocomplete="off" />
                        <button type="button" id="leadsSearchClear" class="wp-accounts-search-clear" title="Limpiar búsqueda">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                    <span id="leadsSearchCount" class="wp-accounts-search-count"></span>
                </form>
            </div>

            <div>
                <div>
                    <div class="wp-accounts-table-wrapper">
                        <div class="wp-accounts-table-scroll">
                            <table id="leadsTable" class="wp-accounts-table">
                                <thead>
                                    <tr>
                                        <th data-column="id">ID</th>
                                        <th data-column="nombre">NOMBRE DEL PLANNER</th>
                                        <th data-column="vendedor">VENDEDOR/A</th>
                                        <th data-column="coordinadores">COORDINADORES</th>
                                        <th data-column="afianzado">AFIANZADO</th>
                                        <th data-column="bodas">BODAS</th>
                                        <th>ACTIVIDAD</th>
                                        <th>ACCIONES</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filteredLeads as $lead): ?>
                                        <?php $rowScheduledKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']);
                                        $lead['scheduled'] = isset($scheduledLeadsMap[$rowScheduledKey]) ? 1 : 0; ?>
                                        <?php $rowStatusKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']);
                                        $lead['estatus'] = isset($leadStatusMap[$rowStatusKey]) ? $leadStatusMap[$rowStatusKey] : 'lead'; ?>
                                        <?php $displayName = getWeddingPlannerDisplayName($lead); ?>
                                        <?php
                                        $leadTable = trim((string) ($lead['tabla_origen'] ?? ''));
                                        $isWeddingPlannerRow = strcasecmp($leadTable, 'wedding_planners') === 0;
                                        $wpEntityId = resolveWpEntityIdFromLead($lead, $wpProfileIdByEmail, $wpProfileEmailCount, $wpProfileIdByPhone, $wpProfilePhoneCount, $wpVendedorIdByPlanner);

                                        $rowKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);
                                        $activityDate = $leadLastInteractionByKey[$rowKey] ?? ($lead['created_time'] ?? '');
                                        $activityMeta = getPlannerActivityMeta($activityDate);
                                        $activeWeddingsForRow = intval($activeWeddingsByWp[$wpEntityId] ?? 0);
                                        $eventosCountForRow = intval($eventosCountByWp[$wpEntityId] ?? 0);
                                        $showDealBadge = !empty($dealBadgeByLeadKey[$rowKey]);

                                        $coordinatorsCount = intval($coordinatorCounts[$wpEntityId] ?? 0);
                                        $afianzadoValue = 0;
                                        if ($isWeddingPlannerRow) {
                                            $afianzadoValue = intval($lead['afianzado'] ?? 0);
                                        } elseif ($wpEntityId > 0) {
                                            $afianzadoValue = intval($wpAfianzadoByPlanner[$wpEntityId] ?? 0);
                                        }
                                        $canAssignAfianzado = $wpEntityId > 0;
                                        
                                        // Vendedor info (desde wedding_planners)
                                        $assignedVendorId = 0;
                                        if ($isWeddingPlannerRow) {
                                            $assignedVendorId = intval($lead['id_vendedor_asignado'] ?? 0);
                                        } elseif ($wpEntityId > 0) {
                                            $assignedVendorId = intval($wpVendedorIdByPlanner[$wpEntityId] ?? 0);
                                        }
                                        $vendedorNombre = '';
                                        $vendedorApepat = '';
                                        if ($assignedVendorId > 0 && isset($vendedorById[$assignedVendorId])) {
                                            $vendedorNombre = trim((string) ($vendedorById[$assignedVendorId]['nombre'] ?? ''));
                                            $vendedorApepat = trim((string) ($vendedorById[$assignedVendorId]['apepat'] ?? ''));
                                        }
                                        $vendedorFullName = trim($vendedorNombre . ' ' . $vendedorApepat);
                                        $vendedorInitial = getVendedorInitial($vendedorNombre);
                                        $vendedorColor = getVendedorColor($vendedorNombre);
                                        $canAssignVendor = $wpEntityId > 0;
                                        ?>
                                        <tr id="lead-row-<?php echo $lead['id']; ?>-<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-lead-id="<?php echo intval($lead['id']); ?>"
                                            data-wp-id="<?php echo intval($wpEntityId); ?>"
                                            data-vendedor-id="<?php echo intval($assignedVendorId); ?>"
                                            data-afianzado="<?php echo intval($afianzadoValue); ?>"
                                            data-eventos-count="<?php echo intval($eventosCountForRow); ?>"
                                            data-has-eventos="<?php echo $eventosCountForRow > 0 ? 1 : 0; ?>"
                                            data-wp-name="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-tabla="<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-full-name="<?php echo htmlspecialchars(mb_strtolower($displayName, 'UTF-8')); ?>"
                                            data-email="<?php echo htmlspecialchars(mb_strtolower($lead['email'] ?? '', 'UTF-8')); ?>"
                                            data-phone="<?php echo htmlspecialchars(mb_strtolower($lead['phone'] ?? '', 'UTF-8')); ?>"
                                            data-campaign="<?php echo htmlspecialchars(mb_strtolower($lead['campaign_name'] ?? '', 'UTF-8')); ?>"
                                            data-when-married="<?php echo htmlspecialchars($lead['when_are_you_getting_married_'] ?? ''); ?>">
                                            <td class="td-id" data-column="id"><?php echo htmlspecialchars($lead['id'] ?? ''); ?></td>
                                            <td data-column="nombre">
                                                <?php if ($wpEntityId > 0): ?>
                                                    <a class="planner-name planner-name-link"
                                                        href="planner_profile.php?id=<?php echo intval($wpEntityId); ?>"
                                                        title="Ver perfil del planner">
                                                        <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <div class="planner-name">
                                                        <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($showDealBadge): ?>
                                                    <div><span class="relation-badge" style="background: rgba(16, 185, 129, 0.18); color: #065f46;">Deal</span></div>
                                                <?php endif; ?>
                                                <div class="planner-date"><?php echo htmlspecialchars(formatCompactPlannerDate($lead['created_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                            </td>
                                            <td data-column="vendedor">
                                                <?php if ($canAssignVendor): ?>
                                                    <?php if ($vendedorFullName !== ''): ?>
                                                        <div class="vendedor-badge vendedor-assign-cell js-assign-vendedor" title="Clic para cambiar vendedora">
                                                            <div class="vendedor-circle" style="background-color: <?php echo htmlspecialchars($vendedorColor, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <?php echo htmlspecialchars($vendedorInitial, ENT_QUOTES, 'UTF-8'); ?>
                                                            </div>
                                                            <span class="vendedor-name"><?php echo htmlspecialchars($vendedorFullName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="origin-badge js-assign-vendedor" title="Clic para asignar vendedora">Sin vendedor</span>
                                                    <?php endif; ?>
                                                <?php elseif ($vendedorFullName !== ''): ?>
                                                    <div class="vendedor-badge">
                                                        <div class="vendedor-circle" style="background-color: <?php echo htmlspecialchars($vendedorColor, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars($vendedorInitial, ENT_QUOTES, 'UTF-8'); ?>
                                                        </div>
                                                        <span class="vendedor-name"><?php echo htmlspecialchars($vendedorFullName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="origin-badge">Sin vendedor</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-column="coordinadores">
                                                <span class="relation-badge"><?php echo $coordinatorsCount; ?> coordinador<?php echo $coordinatorsCount === 1 ? '' : 'es'; ?></span>
                                            </td>
                                            <td data-column="afianzado">
                                                <?php if ($canAssignAfianzado): ?>
                                                    <span class="afianzado-badge <?php echo htmlspecialchars(getAfianzadoBadgeClass($afianzadoValue), ENT_QUOTES, 'UTF-8'); ?> js-assign-afianzado"
                                                        title="Clic para cambiar afianzado"
                                                        role="button"
                                                        tabindex="0">
                                                        <?php echo htmlspecialchars(getAfianzadoStatusLabel($afianzadoValue), ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="origin-badge">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-column="bodas">
                                                <span class="relation-badge"><?php echo $activeWeddingsForRow; ?></span>
                                            </td>
                                            <td>
                                                <div class="activity-cell">
                                                    <div class="dot <?php echo htmlspecialchars($activityMeta['dot'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                                                    <?php echo htmlspecialchars($activityMeta['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="wp-accounts-actions-cell">
                                                    <?php if ($wpEntityId > 0): ?>
                                                        <a class="wp-accounts-view-btn" title="Ver perfil"
                                                            href="planner_profile.php?id=<?php echo intval($wpEntityId); ?>">Ver perfil</a>
                                                    <?php else: ?>
                                                        <span class="origin-badge">Sin perfil</span>
                                                    <?php endif; ?>
                                                    <?php if ($_cwpCanDeleteWp && $isWeddingPlannerRow && intval($lead['id'] ?? 0) > 0): ?>
                                                        <button type="button" class="wp-accounts-delete-btn" title="Ocultar wedding planner"
                                                            onclick="deleteWeddingPlanner(<?php echo intval($lead['id']); ?>)">
                                                            <i class="bi bi-trash"></i> Eliminar
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="wp-accounts-table-footer">
                            <span id="wp-table-count-label">
                                <strong><?php echo number_format($totalCount); ?></strong>
                                <?php echo $totalCount === 1 ? ' registro' : ' registros'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            </main>
    </div>

    <!-- Modal grande para conversación de WhatsApp -->
    <div class="modal fade" id="whatsappHistorialModal" tabindex="-1" aria-labelledby="whatsappHistorialModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="whatsappHistorialModalLabel">Conversación de WhatsApp</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="whatsappHistorialBody">
                    <div class="wa-wrapper">
                        <div class="wa-topbar">
                            <div class="wa-title">
                                <i class="bi bi-whatsapp wa-icon" aria-hidden="true"></i>
                                <div class="wa-title-text">
                                    <div class="wa-lead-name" id="waLeadName">Conversación de WhatsApp</div>
                                    <div class="wa-lead-phone" id="waLeadPhone"></div>
                                </div>
                            </div>
                            <div class="wa-meta" id="waMeta"></div>
                        </div>
                        <div class="wa-chat" id="waChat"></div>
                        <div class="wa-composer" aria-label="Enviar mensaje (deshabilitado)">
                            <label class="btn-outline-secondary btn wa-clip disabled"
                                title="Adjuntar archivo (deshabilitado)">
                                <i class="bi bi-paperclip" aria-hidden="true"></i>
                                <input type="file" class="d-none" disabled>
                            </label>
                            <input type="text" class="form-control wa-input" placeholder="Escribe un mensaje" disabled>
                            <button type="button" class="btn btn-success wa-send" disabled
                                title="Enviar (deshabilitado)">
                                <i class="bi bi-send" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para registro manual -->
    <div class="modal fade" id="registroManualModal" tabindex="-1" aria-labelledby="registroManualModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registroManualModalLabel">Registrar contacto manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formLeadManual">
                        <div class="mb-3">
                            <label for="leadNombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="leadNombre" name="nombre" required>
                            <div class="invalid-feedback">El nombre es obligatorio</div>
                        </div>
                        <div class="mb-3">
                            <label for="leadCorreo" class="form-label">Correo <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="leadCorreo" name="correo" required>
                            <div class="invalid-feedback">Ingrese un correo válido</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-4">
                                <label for="leadCountryCode" class="form-label">Código país <span
                                        class="text-muted">(opcional)</span></label>
                                <select class="form-select" id="leadCountryCode" name="country_code">
                                    <option value="">Seleccionar...</option>
                                    <!-- options populated dynamically -->
                                </select>
                                <div class="invalid-feedback">Seleccione el código de país si incluye teléfono</div>
                            </div>
                            <div class="col-8">
                                <label for="leadTelefono" class="form-label">Teléfono <br><span
                                        class="text-muted">(opcional)</span></label>
                                <input type="text" class="form-control" id="leadTelefono" name="telefono"
                                    placeholder="Ej: 4771232323 (10 dígitos)">
                                <div class="invalid-feedback">El teléfono debe tener 10 dígitos (solo números)</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="leadOrigen" class="form-label">Origen <span class="text-danger">*</span></label>
                            <select class="form-select" id="leadOrigen" name="campaign_name" required>
                                <option value="">Seleccionar...</option>
                                <option value="b1 (USA)">b1 (USA)</option>
                                <option value="b2 (MX)">b2 (MX)</option>
                                <option value="ig organico">ig organico</option>
                                <option value="prospectos">prospectos</option>
                                <option value="whatsapp">whatsapp</option>
                                <option value="mail">mail</option>

                            </select>
                            <div class="invalid-feedback">El origen es obligatorio</div>
                        </div>
                        <div class="mb-3">
                            <label for="leadMedio" class="form-label">Medio <span class="text-danger">*</span></label>
                            <select class="form-select" id="leadMedio" name="platform" required>
                                <option value="">Seleccionar...</option>
                                <option value="ig usa">ig usa</option>
                                <option value="ig mexico">ig mexico</option>
                                <option value="ig">ig</option>
                                <option value="prospectos">prospectos</option>
                                <option value="fb">fb</option>
                                <option value="whatsapp">whatsapp</option>
                                <option value="mail">mail</option>
                            </select>
                            <div class="invalid-feedback">El medio es obligatorio</div>
                        </div>
                        <div class="mb-3">
                            <label for="leadFechaRegistro" class="form-label">Fecha de registro <span
                                    class="text-muted">(opcional - si no se selecciona, se usará la fecha
                                    actual)</span></label>
                            <input type="datetime-local" class="form-control" id="leadFechaRegistro"
                                name="fecha_registro">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btnGuardarLeadManual">
                        <i class="bi bi-save"></i> Guardar contacto
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para registro manual de Wedding Planner -->
    <div class="modal fade rlm-modal" id="registroManualWeddingModal" tabindex="-1"
        aria-labelledby="registroManualWeddingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="registroManualWeddingModalLabel">Registrar Wedding Planner Manual</h5>
                        <p class="rlm-subtitle">Captura los datos principales del wedding planner con el mismo formato visual del registro de coordinadores.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formWPManual">
                        <div class="rlm-section">
                            <div class="rlm-section-title">
                                <div class="rlm-section-number">1</div>
                                <div class="rlm-section-heading">
                                    <h5>Datos principales</h5>
                                    <p>Define la identidad y los canales de contacto del wedding planner.</p>
                                </div>
                            </div>

                            <div class="rlm-field">
                                <label for="wpCampaignName">Nombre de Wedding Planner <span class="rlm-tag">Requerido</span></label>
                                <input type="text" id="wpCampaignName" name="campaign_name" required placeholder="Nombre de contacto o referencia principal">
                                <div class="invalid-feedback">El nombre del Wedding Planner es obligatorio</div>
                            </div>

                            <div class="rlm-grid-2">
                                <div class="rlm-field">
                                    <label for="wpCompany">Empresa <span class="rlm-optional">Opcional</span></label>
                                    <input type="text" id="wpCompany" name="empresa_wp" placeholder="Nombre de la empresa">
                                </div>
                                <div class="rlm-field">
                                    <label for="wpCity">Ciudad <span class="rlm-optional">Opcional</span></label>
                                    <input type="text" id="wpCity" name="city" placeholder="Ciudad base del wedding planner">
                                </div>
                            </div>

                            <div class="rlm-grid-2">
                                <div class="rlm-field">
                                    <label for="wpEmail">Correo <span class="rlm-optional">Opcional</span></label>
                                    <input type="email" id="wpEmail" name="email" placeholder="correo@dominio.com">
                                </div>
                                <div class="rlm-field">
                                    <label for="wpPhone">Teléfono / WhatsApp <span class="rlm-optional">Opcional</span></label>
                                    <input type="text" id="wpPhone" name="phone" placeholder="Número de contacto">
                                </div>
                            </div>

                            <div class="rlm-grid-2">
                                <div class="rlm-field">
                                    <label for="wpAfianzadoStatus">Estatus de afianzado <span class="rlm-optional">Opcional</span></label>
                                    <select id="wpAfianzadoStatus" name="afianzado">
                                        <option value="0">No afianzado</option>
                                        <option value="3">Nuevo</option>
                                        <option value="2">En proceso</option>
                                        <option value="1">Afianzado</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="rlm-section">
                            <div class="rlm-section-title">
                                <div class="rlm-section-number">2</div>
                                <div class="rlm-section-heading">
                                    <h5>Primer contacto</h5>
                                    <p>Selecciona primero el canal inicial y después confirma el origen. El medio se asigna automáticamente y se envía sin mostrarse.</p>
                                </div>
                            </div>

                            <div class="rlm-grid-2">
                                <div class="rlm-field">
                                    <label for="wpFirstContactChannel">Primer canal de contacto <span class="rlm-tag">Requerido</span></label>
                                    <select id="wpFirstContactChannel" name="first_contact_channel" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="WhatsApp">WhatsApp</option>
                                        <option value="IG">IG</option>
                                        <option value="Facebook">Facebook</option>
                                        <option value="Email">Correo electrónico</option>
                                        <option value="Phone call">Llamada telefónica</option>
                                    </select>
                                    <div class="invalid-feedback">Este campo es obligatorio</div>
                                </div>
                                <div class="rlm-field" id="wpTipoIGFieldWrap" style="display:none;">
                                    <label for="wpTipoIG">Tipo de IG <span class="rlm-tag">Requerido</span></label>
                                    <select id="wpTipoIG" name="tipo_ig">
                                        <option value="">Seleccionar...</option>
                                        <option value="organico">Orgánico</option>
                                        <option value="campana">Campaña</option>
                                    </select>
                                    <div class="invalid-feedback">Este campo es obligatorio</div>
                                </div>
                            </div>

                            <div class="rlm-field" id="wpOrigenFieldWrap" style="display:none;">
                                <label for="wpOrigen">Campaña <span class="rlm-tag">Requerido</span></label>
                                <select id="wpOrigen" name="wp_origin">
                                    <option value="">Seleccionar...</option>
                                </select>
                                <div class="invalid-feedback">Es obligatorio</div>
                            </div>
                            <input type="hidden" id="wpPlatform" name="platform">
                        </div>
                        <div class="rlm-section" style="margin-bottom:0;">
                            <div class="rlm-section-title">
                                <div class="rlm-section-number">3</div>
                                <div class="rlm-section-heading">
                                    <h5>Conocimiento</h5>
                                    <p>Captura la respuesta de antigüedad tal como se usa en el formulario web.</p>
                                </div>
                            </div>

                            <div class="rlm-field">
                                <label for="wpHowLongKnownUs">¿Desde hace cuánto nos conoce el cliente? <span class="rlm-optional">(opcional)</span></label>
                                <input type="hidden" id="wpHowLongKnownUs" name="how_long_known_us">
                                <div class="rlm-choice-group" id="wpHowLongKnownUsGroup">
                                    <div class="rlm-choice-grid">
                                        <button type="button" class="coord-choice-card rlm-choice-card" data-target="#wpHowLongKnownUs" data-value="Less than 6 months" aria-pressed="false">
                                            <span class="coord-choice-card-title rlm-choice-card-title">Menos de 6 meses</span>
                                            <span class="coord-choice-card-desc rlm-choice-card-desc">Nos conociste recientemente.</span>
                                        </button>
                                        <button type="button" class="coord-choice-card rlm-choice-card" data-target="#wpHowLongKnownUs" data-value="More than 6 months" aria-pressed="false">
                                            <span class="coord-choice-card-title rlm-choice-card-title">Más de 6 meses</span>
                                            <span class="coord-choice-card-desc rlm-choice-card-desc">Ya tenías un tiempo siguiéndonos.</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rlm-section" style="margin-bottom:0;">
                            <div class="rlm-section-title">
                                <div class="rlm-section-number">4</div>
                                <div class="rlm-section-heading">
                                    <h5>Asignación y cita</h5>
                                    <p>Selecciona la vendedora y, si hace falta, agenda la cita desde este mismo modal.</p>
                                </div>
                            </div>

                            <div class="rlm-field">
                                <label for="wpAssignedVendor">Vendedora asignada <span class="rlm-optional">Opcional</span></label>
                                <select id="wpAssignedVendor" name="id_vendedor_asignado">
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($agentes as $agente): ?>
                                        <?php $agenteNombre = trim((string) (($agente['nombre'] ?? '') . ' ' . ($agente['apepat'] ?? ''))); ?>
                                        <option value="<?php echo intval($agente['id'] ?? 0); ?>"><?php echo htmlspecialchars($agenteNombre !== '' ? $agenteNombre : ('Usuario #' . intval($agente['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="rlm-field" style="display:flex; align-items:flex-end; margin-bottom:10px;">
                                <div style="display:flex; align-items:center; gap:0.65rem; min-height:44px;">
                                    <input type="checkbox" id="wpScheduleAppointment" name="schedule_appointment" style="width:18px; height:18px; accent-color:#464646;">
                                    <label for="wpScheduleAppointment" style="margin:0; font-size:0.92rem; font-weight:700; color:#464646;">¿Quieres agendar una cita?</label>
                                </div>
                            </div>

                            <div id="wpScheduleFields" style="display:none; margin-top:8px;">
                                <div class="rlm-grid-2">
                                    <div class="rlm-field">
                                        <label for="wpDateAppointment">Fecha de cita <span class="rlm-tag">Requerido</span></label>
                                        <input type="date" id="wpDateAppointment" name="date_appointment">
                                        <div class="invalid-feedback">Selecciona una fecha disponible</div>
                                    </div>
                                    <div class="rlm-field">
                                        <label for="wpTimeAppointment">Horario disponible <span class="rlm-tag">Requerido</span></label>
                                        <select id="wpTimeAppointment" name="time_appointment">
                                            <option value="">Selecciona una hora</option>
                                        </select>
                                        <div class="invalid-feedback">Selecciona un horario disponible</div>
                                    </div>
                                </div>
                                <div class="rlm-grid-2">
                                    <div class="rlm-field">
                                        <label for="wpClientDateAppointment">Fecha de cita para el cliente <span class="rlm-tag">Requerido</span></label>
                                        <input type="date" id="wpClientDateAppointment" name="client_date_appointment">
                                        <div class="invalid-feedback">Selecciona la fecha para el cliente</div>
                                    </div>
                                    <div class="rlm-field">
                                        <label for="wpClientTimeAppointment">Hora de cita para el cliente <span class="rlm-tag">Requerido</span></label>
                                        <input type="time" id="wpClientTimeAppointment" name="client_time_appointment" step="60">
                                        <div class="invalid-feedback">Selecciona la hora para el cliente</div>
                                    </div>
                                </div>
                                <div class="form-text text-muted" style="margin-top:-4px; margin-bottom:12px;">Se llena por defecto con la fecha y hora elegidas para la vendedora, pero puedes ajustarlas si el cliente está en otra zona horaria.</div>
                                <div class="rlm-field">
                                    <label for="wpClientCity">Ciudad del cliente <span class="rlm-tag">Requerido</span></label>
                                    <input type="text" id="wpClientCity" name="cliente_city" placeholder="Ej. Monterrey, Nuevo León">
                                    <div class="invalid-feedback">Escribe la ciudad del cliente</div>
                                </div>
                                <div class="rlm-field">
                                    <label>Engagement del cliente <span class="rlm-tag">Requerido</span></label>
                                    <input type="hidden" id="wpClientEngagement" name="cliente_engagement">
                                    <div class="rlm-engagement-btns" id="wpEngagementGroup">
                                        <button type="button" class="rlm-engagement-card" data-target="#wpClientEngagement" data-value="1" aria-pressed="false">
                                            <span class="rlm-engagement-icon">😑</span>
                                            <span class="rlm-engagement-name">Bajo</span>
                                            <span class="rlm-engagement-desc">Poco entusiasmo, muchas dudas o precio</span>
                                        </button>
                                        <button type="button" class="rlm-engagement-card" data-target="#wpClientEngagement" data-value="2" aria-pressed="false">
                                            <span class="rlm-engagement-icon">😃</span>
                                            <span class="rlm-engagement-name">Medio</span>
                                            <span class="rlm-engagement-desc">Interés real, pero falta de decisión</span>
                                        </button>
                                        <button type="button" class="rlm-engagement-card" data-target="#wpClientEngagement" data-value="3" aria-pressed="false">
                                            <span class="rlm-engagement-icon">🔥</span>
                                            <span class="rlm-engagement-name">Alto</span>
                                            <span class="rlm-engagement-desc">Muy interesados, listos para cerrar</span>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback" id="wpClientEngagementFeedback" style="display:none;">Selecciona el engagement del cliente</div>
                                </div>
                                <div class="rlm-field">
                                    <label for="wpSellerNotes">Notas para el vendedor <span class="rlm-optional">Opcional</span></label>
                                    <textarea id="wpSellerNotes" name="seller_notes" rows="4" placeholder="Escribe aquí detalles relevantes para quien atenderá la cita" style="width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 9px 12px; font-size: 0.9rem; color: #333; outline: none; transition: border-color 0.2s; background: #fff; resize: vertical;"></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="rlm-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="rlm-btn-submit" id="btnGuardarWeddingPlanner"><i
                            class="bi bi-save"></i> Guardar Wedding Planner</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const blockedDatesWp = <?php echo json_encode($diasBloqueados, JSON_UNESCAPED_UNICODE); ?>;
        const cwpAgentes = <?php echo json_encode($cwpAgentesJs, JSON_UNESCAPED_UNICODE); ?>;
        const cwpAfianzadoOptions = [
            { value: 0, label: 'No afianzado' },
            { value: 3, label: 'Nuevo' },
            { value: 2, label: 'En proceso' },
            { value: 1, label: 'Afianzado' }
        ];

        function normalizeCoordinatorKnownUsValue(value) {
            var raw = String(value || '').trim();
            if (!raw) {
                return '';
            }

            var normalized = raw.toLowerCase();
            var map = {
                'less than 3 months': 'Less than 6 months',
                'less than 6 months': 'Less than 6 months',
                'between 3 months and 1 year': 'More than 6 months',
                'more than 6 months': 'More than 6 months',
                'more than 1 year': 'More than 6 months',
                'not asked': 'Less than 6 months',
                'menos de 3 meses': 'Less than 6 months',
                'menos de 6 meses': 'Less than 6 months',
                'entre 3 meses y 1 ano': 'More than 6 months',
                'entre 3 meses y 1 año': 'More than 6 months',
                'mas de 6 meses': 'More than 6 months',
                'más de 6 meses': 'More than 6 months',
                'mas de 1 ano': 'More than 6 months',
                'más de 1 ano': 'More than 6 months',
                'mas de 1 año': 'More than 6 months',
                'más de 1 año': 'More than 6 months',
                'no se pregunto': 'Less than 6 months',
                'no se preguntó': 'Less than 6 months'
            };

            return map[normalized] || raw;
        }

        function formatCoordinatorKnownUsLabel(value) {
            var normalized = normalizeCoordinatorKnownUsValue(value);
            var map = {
                'Less than 3 months': 'Menos de 6 meses',
                'Less than 6 months': 'Menos de 6 meses',
                'Between 3 months and 1 year': 'Más de 6 meses',
                'More than 6 months': 'Más de 6 meses',
                'More than 1 year': 'Más de 6 meses',
                'Not asked': 'Menos de 6 meses'
            };

            return map[normalized] || (normalized || 'N/A');
        }

        function syncCoordinatorChoiceCards(targetSelector) {
            var $input = $(targetSelector);
            var value = normalizeCoordinatorKnownUsValue($input.val());
            var $cards = $('.coord-choice-card[data-target="' + targetSelector + '"]');

            if (value !== ($input.val() || '')) {
                $input.val(value);
            }

            $cards.removeClass('active').attr('aria-pressed', 'false');
            if (value) {
                $cards.filter('[data-value="' + value.replace(/"/g, '\\"') + '"]').addClass('active').attr('aria-pressed', 'true');
            }
        }

        function formatCoordinatorFirstContactLabel(value) {
            var map = {
                'WhatsApp': 'WhatsApp',
                'IG': 'IG',
                'Facebook': 'Facebook',
                'Email': 'Correo electrónico',
                'Phone call': 'Llamada telefónica'
            };

            return map[value] || (value || 'N/A');
        }

        function formatCoordinatorHowDidYouMeetLabel(value) {
            var normalized = String(value || '').trim();
            var map = {
                '1': 'Wedding Planner',
                '2': 'Community',
                '3': 'New Market'
            };

            return map[normalized] || (normalized || 'Sin dato');
        }

        function formatCoordinatorTableValue(value) {
            var normalized = String(value || '').trim();
            return normalized ? normalized : 'Sin dato';
        }

        function getWeddingPlannerNameFromLead(lead) {
            if (!lead) {
                return '';
            }

            return String(lead.empresa_wp || lead.wp_display_name || lead.full_name || lead.campaign_name || ('WP #' + (lead.id || ''))).trim();
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getAfianzadoBadgeState(value) {
            var normalized = parseInt(value, 10);
            if (normalized === 3) {
                return { label: 'Nuevo', className: 'afianzado-badge badge-nuevo js-assign-afianzado' };
            }
            if (normalized === 1) {
                return { label: 'Afianzado', className: 'afianzado-badge badge-si js-assign-afianzado' };
            }
            if (normalized === 2) {
                return { label: 'En proceso', className: 'afianzado-badge badge-proc js-assign-afianzado' };
            }

            return { label: 'No afianzado', className: 'afianzado-badge badge-no js-assign-afianzado' };
        }

        function renderAfianzadoCellHtml(value) {
            var state = getAfianzadoBadgeState(value);
            return '<span class="' + state.className + '" title="Clic para cambiar afianzado" role="button" tabindex="0">' +
                escapeHtml(state.label) + '</span>';
        }

        function applyScheduleDateBounds() {
            var dateInput = document.getElementById('wpDateAppointment');
            var clientDateInput = document.getElementById('wpClientDateAppointment');
            if (!dateInput) {
                return;
            }

            var today = new Date();
            dateInput.setAttribute('min', today.toISOString().split('T')[0]);
            if (clientDateInput) {
                clientDateInput.setAttribute('min', today.toISOString().split('T')[0]);
            }
            today.setDate(today.getDate() + 20);
            dateInput.setAttribute('max', today.toISOString().split('T')[0]);
            if (clientDateInput) {
                clientDateInput.setAttribute('max', today.toISOString().split('T')[0]);
            }
        }

        function isBlockedWpDate(dateValue) {
            return blockedDatesWp.indexOf(dateValue) !== -1;
        }

        function syncEngagementCards(targetSelector) {
            var $input = $(targetSelector);
            var value = String($input.val() || '').trim();
            var $cards = $('.rlm-engagement-card[data-target="' + targetSelector + '"]');
            var $group = $cards.closest('.rlm-engagement-btns');

            $cards.removeClass('active').attr('aria-pressed', 'false');
            if (value) {
                $cards.filter('[data-value="' + value.replace(/"/g, '\\"') + '"]').addClass('active').attr('aria-pressed', 'true');
                $group.removeClass('is-invalid');
                $('#wpClientEngagementFeedback').hide();
            }
        }

        function resetWeddingPlannerScheduleFields() {
            $('#wpDateAppointment, #wpTimeAppointment, #wpClientDateAppointment, #wpClientTimeAppointment, #wpAssignedVendor, #wpClientCity').removeClass('is-invalid');
            $('#wpDateAppointment').val('');
            $('#wpTimeAppointment').html('<option value="">Selecciona una hora</option>');
            $('#wpClientDateAppointment').val('').removeData('last-source-value');
            $('#wpClientTimeAppointment').val('').removeData('last-source-value');
            $('#wpClientCity').val('');
            $('#wpClientEngagement').val('');
            $('#wpEngagementGroup').removeClass('is-invalid');
            $('#wpClientEngagementFeedback').hide();
            syncEngagementCards('#wpClientEngagement');
            $('#wpSellerNotes').val('');
        }

        function syncWeddingPlannerClientAppointmentFields() {
            var sellerDate = ($('#wpDateAppointment').val() || '').trim();
            var sellerTime = ($('#wpTimeAppointment').val() || '').trim();
            var $clientDate = $('#wpClientDateAppointment');
            var $clientTime = $('#wpClientTimeAppointment');

            if ($clientDate.length) {
                var previousSellerDate = String($clientDate.data('last-source-value') || '');
                var currentClientDate = String($clientDate.val() || '').trim();
                if (!currentClientDate || currentClientDate === previousSellerDate) {
                    $clientDate.val(sellerDate);
                }
                $clientDate.data('last-source-value', sellerDate);
            }

            if ($clientTime.length) {
                var previousSellerTime = String($clientTime.data('last-source-value') || '');
                var currentClientTime = String($clientTime.val() || '').trim();
                if (!currentClientTime || currentClientTime === previousSellerTime) {
                    $clientTime.val(sellerTime ? sellerTime.slice(0, 5) : '');
                }
                $clientTime.data('last-source-value', sellerTime ? sellerTime.slice(0, 5) : '');
            }
        }

        function loadWeddingPlannerAvailableTimes() {
            var vendedorId = ($('#wpAssignedVendor').val() || '').trim();
            var selectedDate = ($('#wpDateAppointment').val() || '').trim();

            if (!vendedorId || !selectedDate) {
                $('#wpTimeAppointment').html('<option value="">Selecciona una hora</option>');
                return;
            }

            if (isBlockedWpDate(selectedDate)) {
                $('#wpDateAppointment').val('').addClass('is-invalid');
                $('#wpTimeAppointment').html('<option value="">Sin horarios disponibles</option>');
                Swal.fire({ icon: 'warning', title: 'La fecha seleccionada está bloqueada' });
                return;
            }

            var formData = new FormData();
            formData.append('dia', selectedDate);
            formData.append('idusu', vendedorId);

            fetch('chkDisponible.php', {
                method: 'POST',
                body: formData
            })
                .then(function (response) { return response.json(); })
                .then(function (horariosJson) {
                    var occupiedForm = new FormData();
                    occupiedForm.append('fecha', selectedDate);
                    occupiedForm.append('idusu', vendedorId);

                    return fetch('chkDisponible.php', {
                        method: 'POST',
                        body: occupiedForm
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (occupiedTimes) {
                            var selectHtml = '';
                            var available = [];

                            if (Array.isArray(horariosJson) && horariosJson.length > 0 && horariosJson[0].horarios) {
                                var horarios = JSON.parse(horariosJson[0].horarios);
                                horarios.forEach(function (horario) {
                                    var current = horario;
                                    if (Array.isArray(occupiedTimes) && occupiedTimes.length > 0) {
                                        occupiedTimes.forEach(function (time) {
                                            if (time.hora === horario + ':00') {
                                                current = null;
                                            }
                                        });
                                    }

                                    if (current && available.indexOf(current) === -1) {
                                        available.push(current);
                                    }
                                });
                            }

                            available.sort(function (a, b) {
                                var partsA = a.split(':');
                                var partsB = b.split(':');
                                return (parseInt(partsA[0], 10) * 60 + parseInt(partsA[1], 10)) - (parseInt(partsB[0], 10) * 60 + parseInt(partsB[1], 10));
                            });

                            if (available.length > 0) {
                                selectHtml += '<option value="">Selecciona una hora</option>';
                                available.forEach(function (hora) {
                                    selectHtml += '<option value="' + hora + ':00">' + hora + '</option>';
                                });
                            } else {
                                selectHtml = '<option value="">Sin Horarios Disponibles</option>';
                            }

                            $('#wpTimeAppointment').html(selectHtml);
                        });
                })
                .catch(function (error) {
                    console.error('Error al obtener horarios disponibles:', error);
                    $('#wpTimeAppointment').html('<option value="">Sin Horarios Disponibles</option>');
                });
        }

        var pendingRelationFocus = null;

        function updateContextBanner(elementId, title, message, isFocused) {
            var banner = document.getElementById(elementId);
            if (!banner) {
                return;
            }

            var html = '<div><strong>' + escapeHtml(title) + '</strong><br><span>' + escapeHtml(message) + '</span></div>' +
                '<button type="button" style="padding: 10px !important;" class="btn btn-outline btn-sm context-reset-btn" onclick="clearRelationFocus()">Mostrar todos</button>';
            banner.innerHTML = html;
            banner.classList.toggle('is-focused', !!isFocused);
        }

        function scrollRowIntoView(row) {
            if (!row || typeof row.scrollIntoView !== 'function') {
                return;
            }

            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function clearTableRelationState(selector) {
            document.querySelectorAll(selector).forEach(function (row) {
                row.classList.remove('relation-highlight', 'relation-muted');
            });
        }

        function clearRelationFocus() {
            pendingRelationFocus = null;
            clearTableRelationState('#leadsTable tbody tr');
            clearTableRelationState('#coordinatorsTable tbody tr');
            updateContextBanner('wpTabContext', 'Wedding Planners', 'Mostrando todos los wedding planners disponibles.', false);
            updateContextBanner('coordinatorsTabContext', 'Coordinadores', 'Mostrando todos los coordinadores asociados a los wedding planners visibles.', false);
        }

        function activateRelationshipTab(triggerId) {
            var trigger = document.getElementById(triggerId);
            if (!trigger) {
                return;
            }

            bootstrap.Tab.getOrCreateInstance(trigger).show();
        }

        function applyCoordinatorFocus(wpId, wpName) {
            var rows = document.querySelectorAll('#coordinatorsTable tbody tr[data-wp-id]');
            var firstMatch = null;
            var matches = 0;

            rows.forEach(function (row) {
                var belongsToWp = parseInt(row.getAttribute('data-wp-id'), 10) === parseInt(wpId, 10);
                row.classList.toggle('relation-highlight', belongsToWp);
                row.classList.toggle('relation-muted', !belongsToWp);
                if (belongsToWp) {
                    matches++;
                    if (!firstMatch) {
                        firstMatch = row;
                    }
                }
            });

            updateContextBanner(
                'coordinatorsTabContext',
                'Coordinadores',
                matches > 0
                    ? ('Resaltando ' + matches + ' coordinador(es) relacionados con ' + wpName + '.')
                    : ('No hay coordinadores registrados para ' + wpName + '.'),
                true
            );
            scrollRowIntoView(firstMatch);
        }

        function applyWeddingPlannerFocus(wpId, coordinatorName) {
            var rows = document.querySelectorAll('#leadsTable tbody tr[data-wp-id]');
            var firstMatch = null;

            rows.forEach(function (row) {
                var isMatch = parseInt(row.getAttribute('data-wp-id'), 10) === parseInt(wpId, 10);
                row.classList.toggle('relation-highlight', isMatch);
                row.classList.toggle('relation-muted', !isMatch);
                if (isMatch && !firstMatch) {
                    firstMatch = row;
                }
            });

            var wpName = firstMatch ? (firstMatch.getAttribute('data-wp-name') || ('WP #' + wpId)) : ('WP #' + wpId);
            updateContextBanner(
                'wpTabContext',
                'Wedding Planners',
                coordinatorName
                    ? ('Resaltando el wedding planner relacionado con ' + coordinatorName + '.')
                    : ('Resaltando ' + wpName + '.'),
                true
            );
            scrollRowIntoView(firstMatch);
        }

        function applyPendingRelationFocus() {
            if (!pendingRelationFocus) {
                clearRelationFocus();
                return;
            }

            clearTableRelationState('#leadsTable tbody tr');
            clearTableRelationState('#coordinatorsTable tbody tr');

            if (pendingRelationFocus.type === 'wp-to-coordinators') {
                applyCoordinatorFocus(pendingRelationFocus.wpId, pendingRelationFocus.wpName);
                return;
            }

            if (pendingRelationFocus.type === 'coordinator-to-wp') {
                applyWeddingPlannerFocus(pendingRelationFocus.wpId, pendingRelationFocus.coordinatorName);
            }
        }

        function focusCoordinadoresTab(lead) {
            var wpId = lead && lead.id ? parseInt(lead.id, 10) : 0;
            if (!wpId) {
                return;
            }

            pendingRelationFocus = {
                type: 'wp-to-coordinators',
                targetPane: 'coordinadores-tab-pane',
                wpId: wpId,
                wpName: getWeddingPlannerNameFromLead(lead)
            };
            activateRelationshipTab('coordinadores-tab-trigger');
        }

        function focusWeddingPlannerTab(wpId, coordinatorName) {
            if (!wpId) {
                return;
            }

            pendingRelationFocus = {
                type: 'coordinator-to-wp',
                targetPane: 'wedding-planners-tab-pane',
                wpId: parseInt(wpId, 10),
                coordinatorName: String(coordinatorName || '').trim()
            };
            activateRelationshipTab('wedding-planners-tab-trigger');
        }

        var coordTipoIGRules = {
            'organico': {
                origenes: [],
                medios: [{ value: 'ig', label: 'IG' }],
                medioPorOrigen: {}
            },
            'campana': {
                origenes: [
                    { value: 'b1 (USA)', label: 'B1' },
                    { value: 'b2 (MX)', label: 'B2' }
                ],
                medios: [
                    { value: 'ig usa', label: 'IG USA' },
                    { value: 'ig mexico', label: 'IG México' }
                ],
                medioPorOrigen: { 'b1 (USA)': 'ig usa', 'b2 (MX)': 'ig mexico' }
            }
        };

        var coordChannelRules = {
            'Email': {
                origenes: [{ value: 'mail', label: 'Mail' }],
                medios: [{ value: 'mail', label: 'Mail' }],
                medioPorOrigen: { 'mail': 'mail' }
            },
            'Phone call': {
                origenes: [{ value: 'phone call', label: 'Llamada telefónica' }],
                medios: [{ value: 'phone call', label: 'Llamada telefónica' }],
                medioPorOrigen: { 'phone call': 'phone call' }
            },
            'WhatsApp': {
                origenes: [{ value: 'whatsapp', label: 'WhatsApp' }],
                medios: [{ value: 'whatsapp', label: 'WhatsApp' }],
                medioPorOrigen: { 'whatsapp': 'whatsapp' }
            },
            'IG': { origenes: [], medios: [], medioPorOrigen: {} },
            'Facebook': {
                origenes: [{ value: 'fb', label: 'Facebook' }],
                medios: [{ value: 'fb', label: 'Facebook' }],
                medioPorOrigen: { 'fb': 'fb' }
            }
        };

        var wpTipoIGRules = {
            'organico': {
                origenes: [],
                medios: [{ value: 'ig', label: 'IG' }],
                medioPorOrigen: {}
            },
            'campana': {
                origenes: [
                    { value: 'b1 (USA)', label: 'B1' },
                    { value: 'b2 (MX)', label: 'B2' }
                ],
                medios: [
                    { value: 'ig usa', label: 'IG USA' },
                    { value: 'ig mexico', label: 'IG México' }
                ],
                medioPorOrigen: { 'b1 (USA)': 'ig usa', 'b2 (MX)': 'ig mexico' }
            }
        };

        var wpChannelRules = {
            'Email': {
                origenes: [{ value: 'mail', label: 'Mail' }],
                medios: [{ value: 'mail', label: 'Mail' }],
                medioPorOrigen: { 'mail': 'mail' }
            },
            'Phone call': {
                origenes: [{ value: 'phone call', label: 'Llamada telefónica' }],
                medios: [{ value: 'phone call', label: 'Llamada telefónica' }],
                medioPorOrigen: { 'phone call': 'phone call' }
            },
            'WhatsApp': {
                origenes: [{ value: 'whatsapp', label: 'WhatsApp' }],
                medios: [{ value: 'whatsapp', label: 'WhatsApp' }],
                medioPorOrigen: { 'whatsapp': 'whatsapp' }
            },
            'IG': { origenes: [], medios: [], medioPorOrigen: {} },
            'Facebook': {
                origenes: [{ value: 'fb', label: 'Facebook' }],
                medios: [{ value: 'fb', label: 'Facebook' }],
                medioPorOrigen: { 'fb': 'fb' }
            }
        };

        function populateCoordinatorSelect($select, options, currentValue, placeholder) {
            var html = ['<option value="">' + placeholder + '</option>'];
            (options || []).forEach(function (option) {
                html.push('<option value="' + option.value + '">' + option.label + '</option>');
            });

            $select.html(html.join(''));

            var hasCurrentValue = !!currentValue && (options || []).some(function (option) {
                return option.value === currentValue;
            });

            if ((options || []).length === 1) {
                $select.val(options[0].value).prop('disabled', true);
            } else {
                $select.prop('disabled', !(options || []).length);
                $select.val(hasCurrentValue ? currentValue : '');
            }
        }

        function toggleCoordinatorAutoField($fieldWrap, $select, options) {
            var optionCount = (options || []).length;
            var shouldHide = optionCount <= 1;

            $fieldWrap.toggle(!shouldHide);

            if (shouldHide) {
                $select.removeClass('is-invalid');
            }
        }

        function resolveCoordinatorMedio(firstContactChannel, origenValue, currentMedio) {
            var rule;
            if (firstContactChannel === 'IG') {
                var tipoIG = $('#coordTipoIG').val() || '';
                rule = coordTipoIGRules[tipoIG] || null;
            } else {
                rule = coordChannelRules[firstContactChannel] || null;
            }
            var medios = rule && rule.medios ? rule.medios : [];

            if (!rule || !medios.length) {
                return '';
            }

            if (rule.medioPorOrigen && origenValue && rule.medioPorOrigen[origenValue]) {
                return rule.medioPorOrigen[origenValue];
            }

            if (medios.length === 1) {
                return medios[0].value;
            }

            var hasCurrentValue = !!currentMedio && medios.some(function (option) {
                return option.value === currentMedio;
            });

            return hasCurrentValue ? currentMedio : '';
        }

        function syncCoordinatorMedio() {
            var firstContactChannel = $('#coordFirstContactChannel').val() || '';
            var origenValue = $('#coordOrigen').val() || '';
            var $medio = $('#coordMedio');
            var resolvedMedio = resolveCoordinatorMedio(firstContactChannel, origenValue, $medio.val() || '');

            $medio.val(resolvedMedio);
            return resolvedMedio;
        }

        function updateCoordinatorOrigenFromTipoIG(resetSelection) {
            var tipoIG = $('#coordTipoIG').val() || '';
            var rule = coordTipoIGRules[tipoIG] || null;
            var $origen = $('#coordOrigen');
            var $origenFieldWrap = $('#coordOrigenFieldWrap');
            var currentOrigen = resetSelection ? '' : ($origen.val() || '');

            if (!rule) {
                $origenFieldWrap.hide();
                populateCoordinatorSelect($origen, [], '', 'Seleccionar...');
                $origen.prop('disabled', true).removeClass('is-invalid');
                syncCoordinatorMedio();
                return;
            }

            populateCoordinatorSelect($origen, rule.origenes, currentOrigen, 'Seleccionar...');
            $origen.removeClass('is-invalid');
            toggleCoordinatorAutoField($origenFieldWrap, $origen, rule.origenes);
            syncCoordinatorMedio();
        }

        function updateCoordinatorDependentFields(resetSelection) {
            var firstContactChannel = $('#coordFirstContactChannel').val() || '';
            var $origen = $('#coordOrigen');
            var $origenFieldWrap = $('#coordOrigenFieldWrap');
            var $tipoIGFieldWrap = $('#coordTipoIGFieldWrap');
            var $tipoIG = $('#coordTipoIG');
            var $medio = $('#coordMedio');

            if (firstContactChannel === 'IG') {
                $tipoIGFieldWrap.show();
                if (resetSelection) {
                    $tipoIG.val('').removeClass('is-invalid');
                }
                updateCoordinatorOrigenFromTipoIG(resetSelection);
                return;
            }

            $tipoIGFieldWrap.hide();
            $tipoIG.val('').removeClass('is-invalid');

            var rule = coordChannelRules[firstContactChannel] || null;
            var currentOrigen = resetSelection ? '' : ($origen.val() || '');
            var currentMedio = resetSelection ? '' : ($medio.val() || '');

            if (!rule) {
                populateCoordinatorSelect($origen, [], '', 'Selecciona primero el canal...');
                $origen.prop('disabled', true).removeClass('is-invalid');
                $origenFieldWrap.hide();
                $medio.val('');
                return;
            }

            populateCoordinatorSelect($origen, rule.origenes, currentOrigen, 'Seleccionar...');
            $origen.removeClass('is-invalid');
            toggleCoordinatorAutoField($origenFieldWrap, $origen, rule.origenes);
            $medio.val(resolveCoordinatorMedio(firstContactChannel, $origen.val() || currentOrigen, currentMedio));
        }

        function resolveWeddingPlannerMedio(firstContactChannel, origenValue, currentMedio) {
            var rule;
            if (firstContactChannel === 'IG') {
                var tipoIG = $('#wpTipoIG').val() || '';
                rule = wpTipoIGRules[tipoIG] || null;
            } else {
                rule = wpChannelRules[firstContactChannel] || null;
            }
            var medios = rule && rule.medios ? rule.medios : [];

            if (!rule || !medios.length) {
                return '';
            }

            if (rule.medioPorOrigen && origenValue && rule.medioPorOrigen[origenValue]) {
                return rule.medioPorOrigen[origenValue];
            }

            if (medios.length === 1) {
                return medios[0].value;
            }

            var hasCurrentValue = !!currentMedio && medios.some(function (option) {
                return option.value === currentMedio;
            });

            return hasCurrentValue ? currentMedio : '';
        }

        function syncWeddingPlannerMedio() {
            var firstContactChannel = $('#wpFirstContactChannel').val() || '';
            var origenValue = $('#wpOrigen').val() || '';
            var $medio = $('#wpPlatform');
            var resolvedMedio = resolveWeddingPlannerMedio(firstContactChannel, origenValue, $medio.val() || '');

            $medio.val(resolvedMedio);
            return resolvedMedio;
        }

        function updateWeddingPlannerOrigenFromTipoIG(resetSelection) {
            var tipoIG = $('#wpTipoIG').val() || '';
            var rule = wpTipoIGRules[tipoIG] || null;
            var $origen = $('#wpOrigen');
            var $origenFieldWrap = $('#wpOrigenFieldWrap');
            var currentOrigen = resetSelection ? '' : ($origen.val() || '');

            if (!rule) {
                $origenFieldWrap.hide();
                populateCoordinatorSelect($origen, [], '', 'Seleccionar...');
                $origen.prop('disabled', true).removeClass('is-invalid');
                syncWeddingPlannerMedio();
                return;
            }

            populateCoordinatorSelect($origen, rule.origenes, currentOrigen, 'Seleccionar...');
            $origen.removeClass('is-invalid');
            toggleCoordinatorAutoField($origenFieldWrap, $origen, rule.origenes);
            syncWeddingPlannerMedio();
        }

        function updateWeddingPlannerDependentFields(resetSelection) {
            var firstContactChannel = $('#wpFirstContactChannel').val() || '';
            var $origen = $('#wpOrigen');
            var $origenFieldWrap = $('#wpOrigenFieldWrap');
            var $tipoIGFieldWrap = $('#wpTipoIGFieldWrap');
            var $tipoIG = $('#wpTipoIG');
            var $medio = $('#wpPlatform');

            if (firstContactChannel === 'IG') {
                $tipoIGFieldWrap.show();
                if (resetSelection) {
                    $tipoIG.val('').removeClass('is-invalid');
                }
                updateWeddingPlannerOrigenFromTipoIG(resetSelection);
                return;
            }

            $tipoIGFieldWrap.hide();
            $tipoIG.val('').removeClass('is-invalid');

            var rule = wpChannelRules[firstContactChannel] || null;
            var currentOrigen = resetSelection ? '' : ($origen.val() || '');
            var currentMedio = resetSelection ? '' : ($medio.val() || '');

            if (!rule) {
                populateCoordinatorSelect($origen, [], '', 'Selecciona primero el canal...');
                $origen.prop('disabled', true).removeClass('is-invalid');
                $origenFieldWrap.hide();
                $medio.val('');
                return;
            }

            populateCoordinatorSelect($origen, rule.origenes, currentOrigen, 'Seleccionar...');
            $origen.removeClass('is-invalid');
            toggleCoordinatorAutoField($origenFieldWrap, $origen, rule.origenes);
            $medio.val(resolveWeddingPlannerMedio(firstContactChannel, $origen.val() || currentOrigen, currentMedio));
        }

        document.addEventListener('DOMContentLoaded', function () {
            clearRelationFocus();
            applyScheduleDateBounds();

            $(document).on('click', '.coord-choice-card', function () {
                var $card = $(this);
                var targetSelector = $card.data('target');
                if (!targetSelector) {
                    return;
                }

                var $input = $(targetSelector);
                var nextValue = $card.data('value') || '';
                var currentValue = normalizeCoordinatorKnownUsValue($input.val());
                $input.val(currentValue === nextValue ? '' : nextValue).trigger('change');
                syncCoordinatorChoiceCards(targetSelector);
            });

            $(document).on('click', '.rlm-engagement-card', function () {
                var $card = $(this);
                var targetSelector = $card.data('target');
                if (!targetSelector) {
                    return;
                }

                var $input = $(targetSelector);
                var nextValue = String($card.data('value') || '');
                var currentValue = String($input.val() || '').trim();
                $input.val(currentValue === nextValue ? '' : nextValue).trigger('change');
                syncEngagementCards(targetSelector);
            });

            $('#coordHowLongKnownUs, #wpHowLongKnownUs').on('change', function () {
                syncCoordinatorChoiceCards('#' + this.id);
            });

            $('#wpClientEngagement').on('change', function () {
                syncEngagementCards('#' + this.id);
            });

            $('#wpClientCity').on('input', function () {
                $(this).removeClass('is-invalid');
            });

            $('#wpFirstContactChannel').on('change', function () {
                $(this).removeClass('is-invalid');
                updateWeddingPlannerDependentFields(true);
            });

            $('#wpTipoIG').on('change', function () {
                $(this).removeClass('is-invalid');
                updateWeddingPlannerOrigenFromTipoIG(true);
            });

            $('#wpOrigen').on('change', function () {
                $(this).removeClass('is-invalid');
                syncWeddingPlannerMedio();
            });

            $('#wpScheduleAppointment').on('change', function () {
                var checked = $(this).is(':checked');
                $('#wpScheduleFields').toggle(checked);
                if (!checked) {
                    resetWeddingPlannerScheduleFields();
                    return;
                }

                if (($('#wpAssignedVendor').val() || '').trim()) {
                    loadWeddingPlannerAvailableTimes();
                }
            });

            $('#wpAssignedVendor').on('change', function () {
                $(this).removeClass('is-invalid');
                if ($('#wpScheduleAppointment').is(':checked')) {
                    resetWeddingPlannerScheduleFields();
                }
            });

            $('#wpDateAppointment').on('change', function (event) {
                $(this).removeClass('is-invalid');
                $('#wpTimeAppointment').removeClass('is-invalid');
                var selectedDate = event.target.value || '';

                if (selectedDate && isBlockedWpDate(selectedDate)) {
                    event.target.value = '';
                    $('#wpTimeAppointment').html('<option value="">Selecciona una hora</option>');
                    $(this).addClass('is-invalid');
                    Swal.fire({ icon: 'warning', title: 'La fecha seleccionada está bloqueada' });
                    return;
                }

                syncWeddingPlannerClientAppointmentFields();
                loadWeddingPlannerAvailableTimes();
            });

            $('#wpTimeAppointment').on('change', function () {
                $(this).removeClass('is-invalid');
                syncWeddingPlannerClientAppointmentFields();
            });

            $('#wpClientDateAppointment, #wpClientTimeAppointment').on('change input', function () {
                $(this).removeClass('is-invalid');
            });

            syncCoordinatorChoiceCards('#wpHowLongKnownUs');

            $('#btnGuardarWeddingPlanner').on('click', function () {
                var name = ($('#wpCampaignName').val() || '').trim();
                var company = ($('#wpCompany').val() || '').trim();
                var city = ($('#wpCity').val() || '').trim();
                var email = ($('#wpEmail').val() || '').trim();
                var phone = ($('#wpPhone').val() || '').trim();
                var firstContactChannel = ($('#wpFirstContactChannel').val() || '').trim();
                var tipoIG = ($('#wpTipoIG').val() || '').trim();
                var origen = ($('#wpOrigen').val() || '').trim();
                var howLongKnownUs = ($('#wpHowLongKnownUs').val() || '').trim();
                var platform = ($('#wpPlatform').val() || '').trim();
                var assignedVendor = ($('#wpAssignedVendor').val() || '').trim();
                var afianzadoStatus = parseInt($('#wpAfianzadoStatus').val() || '0', 10);
                var wantsAppointment = $('#wpScheduleAppointment').is(':checked');
                var appointmentDate = ($('#wpDateAppointment').val() || '').trim();
                var appointmentTime = ($('#wpTimeAppointment').val() || '').trim();
                var clientAppointmentDate = ($('#wpClientDateAppointment').val() || '').trim();
                var clientAppointmentTime = ($('#wpClientTimeAppointment').val() || '').trim();
                var clientCity = ($('#wpClientCity').val() || '').trim();
                var clientEngagement = ($('#wpClientEngagement').val() || '').trim();
                var sellerNotes = ($('#wpSellerNotes').val() || '').trim();
                $('#wpCampaignName, #wpEmail, #wpFirstContactChannel, #wpTipoIG, #wpOrigen, #wpAssignedVendor, #wpDateAppointment, #wpTimeAppointment, #wpClientDateAppointment, #wpClientTimeAppointment, #wpClientCity').removeClass('is-invalid');
                $('#wpEngagementGroup').removeClass('is-invalid');
                $('#wpClientEngagementFeedback').hide();
                if (!name) {
                    $('#wpCampaignName').addClass('is-invalid');
                    Swal.fire({ icon: 'warning', title: 'Complete el nombre del Wedding Planner' });
                    return;
                }
                if (!firstContactChannel) {
                    $('#wpFirstContactChannel').addClass('is-invalid');
                    Swal.fire({ icon: 'warning', title: 'Seleccione el primer canal de contacto' });
                    return;
                }
                if (firstContactChannel === 'IG' && !tipoIG) {
                    $('#wpTipoIG').addClass('is-invalid');
                    Swal.fire({ icon: 'warning', title: 'Seleccione el tipo de IG' });
                    return;
                }
                if ($('#wpOrigenFieldWrap').is(':visible') && !origen) {
                    $('#wpOrigen').addClass('is-invalid');
                    Swal.fire({ icon: 'warning', title: 'Seleccione la campaña de origen' });
                    return;
                }
                if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    $('#wpEmail').addClass('is-invalid');
                    Swal.fire({ icon: 'warning', title: 'Correo inválido' });
                    return;
                }
                if (wantsAppointment && !assignedVendor) {
                    $('#wpAssignedVendor').addClass('is-invalid');
                    Swal.fire({ icon: 'warning', title: 'Seleccione una vendedora para agendar la cita' });
                    return;
                }
                if (wantsAppointment && !appointmentDate) {
                    $('#wpDateAppointment').addClass('is-invalid');
                    Swal.fire({ icon: 'warning', title: 'Seleccione una fecha para la cita' });
                    return;
                }
                if (wantsAppointment && !appointmentTime) {
                    $('#wpTimeAppointment').addClass('is-invalid');
                    Swal.fire({ icon: 'warning', title: 'Seleccione un horario disponible' });
                    return;
                }
                if (wantsAppointment && !clientAppointmentDate) {
                    $('#wpClientDateAppointment').addClass('is-invalid');
                    Swal.fire({ icon: 'warning', title: 'Seleccione la fecha de la cita para el cliente' });
                    return;
                }
                if (wantsAppointment && !clientAppointmentTime) {
                    $('#wpClientTimeAppointment').addClass('is-invalid');
                    Swal.fire({ icon: 'warning', title: 'Seleccione la hora de la cita para el cliente' });
                    return;
                }
                if (wantsAppointment && !clientCity) {
                    $('#wpClientCity').addClass('is-invalid');
                    Swal.fire({ icon: 'warning', title: 'Escriba la ciudad del cliente' });
                    return;
                }
                if (wantsAppointment && !clientEngagement) {
                    $('#wpEngagementGroup').addClass('is-invalid');
                    $('#wpClientEngagementFeedback').show();
                    Swal.fire({ icon: 'warning', title: 'Seleccione el engagement del cliente' });
                    return;
                }

                platform = syncWeddingPlannerMedio();

                var $btn = $(this);
                var editId = $btn.data('edit-id') || 0;
                var loadingText = editId ? '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Actualizando...' : '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';
                $btn.prop('disabled', true).html(loadingText);

                var payload = {
                    campaign_name: origen || name,
                    full_name: name,
                    empresa_wp: company,
                    city: city,
                    phone: phone,
                    email: email,
                    how_long_known_us: howLongKnownUs,
                    first_contact_channel: firstContactChannel,
                    platform: platform || 'Wedding Planner',
                    id_vendedor_asignado: assignedVendor || 0,
                    afianzado: isNaN(afianzadoStatus) ? 0 : afianzadoStatus
                };
                if (editId) payload.id = editId;

                $.ajax({
                    url: 'guardar_wedding_planner_only.php',
                    type: 'POST',
                    dataType: 'json',
                    data: payload,
                    success: function (resp) {
                        if (resp && resp.success) {
                            var weddingPlannerId = resp.wedding_planner_id || resp.updated_id || editId;
                            if (!wantsAppointment || !weddingPlannerId) {
                                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('registroManualWeddingModal'));
                                modal.hide();
                                $btn.data('edit-id', 0);
                                Swal.fire({ icon: 'success', title: editId ? 'Actualizado' : 'Guardado', text: editId ? 'Wedding Planner actualizado' : 'Wedding Planner registrado' }).then(function () { location.reload(); });
                                return;
                            }

                            $.ajax({
                                url: 'agendar_wedding_planner.php',
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    wedding_planner_id: weddingPlannerId,
                                    override_vendedor_id: assignedVendor,
                                    fecha: appointmentDate,
                                    hora: appointmentTime,
                                    fecha_cliente: clientAppointmentDate,
                                    hora_cliente: clientAppointmentTime,
                                    comentario: sellerNotes,
                                    cliente_city: clientCity,
                                    cliente_engagement: clientEngagement
                                },
                                success: function (agendarResp) {
                                    if (!agendarResp || !agendarResp.success) {
                                        Swal.fire('Error', agendarResp && agendarResp.message ? agendarResp.message : 'No se pudo crear la cita', 'error');
                                        return;
                                    }

                                    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('registroManualWeddingModal'));
                                    modal.hide();
                                    $btn.data('edit-id', 0);
                                    Swal.fire({
                                        icon: 'success',
                                        title: editId ? 'Actualizado y agendado' : 'Guardado y agendado',
                                        text: 'La llamada se registró correctamente.'
                                    }).then(function () { location.reload(); });
                                },
                                error: function (xhr) {
                                    console.error('Error creando cita:', xhr);
                                    var backendMessage = '';
                                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                                        backendMessage = xhr.responseJSON.message;
                                    } else if (xhr && xhr.responseText) {
                                        try {
                                            var parsed = JSON.parse(xhr.responseText);
                                            backendMessage = parsed && parsed.message ? parsed.message : '';
                                        } catch (parseError) {
                                            backendMessage = '';
                                        }
                                    }
                                    Swal.fire('Error', backendMessage || 'No se pudo crear la cita para el wedding planner', 'error');
                                }
                            });
                        } else {
                            Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo guardar', 'error');
                        }
                    },
                    error: function (xhr) {
                        console.error('Error:', xhr);
                        showErrorModalFromResponse(null, xhr);
                    },
                    complete: function () {
                        $btn.prop('disabled', false).html('<i class="bi bi-save"></i> Guardar Wedding Planner');
                    }
                });
            });

            // Limpiar formulario al cerrar
            $('#registroManualWeddingModal').on('hidden.bs.modal', function () {
                $('#formWPManual')[0].reset();
                $('#formWPManual').find('.is-invalid').removeClass('is-invalid');
                $('#wpAfianzadoStatus').val('0');
                $('#wpScheduleAppointment').prop('checked', false);
                $('#wpScheduleFields').hide();
                resetWeddingPlannerScheduleFields();
                updateWeddingPlannerDependentFields(true);
                syncCoordinatorChoiceCards('#wpHowLongKnownUs');
                $('#btnGuardarWeddingPlanner').data('edit-id', 0);
            });

            window.openEditWeddingPlanner = function (lead) {
                var name = (lead && (lead.full_name || lead.campaign_name || lead.empresa_wp || lead.wp_display_name)) ? (lead.full_name || lead.campaign_name || lead.empresa_wp || lead.wp_display_name) : '';
                var firstContactChannel = lead && lead.first_contact_channel ? String(lead.first_contact_channel) : '';
                var platformValue = lead && lead.platform ? String(lead.platform) : '';
                var campaignValue = lead && lead.campaign_name ? String(lead.campaign_name) : '';
                var inferredTipoIG = '';
                var inferredOrigen = '';

                if (firstContactChannel === 'IG') {
                    if (platformValue === 'ig usa' || platformValue === 'ig mexico') {
                        inferredTipoIG = 'campana';
                        inferredOrigen = campaignValue;
                    } else if (platformValue === 'ig') {
                        inferredTipoIG = 'organico';
                    }
                } else if (campaignValue && campaignValue !== name) {
                    inferredOrigen = campaignValue;
                }

                $('#wpCampaignName').val(name);
                $('#wpCompany').val(lead && lead.empresa_wp ? lead.empresa_wp : '');
                $('#wpCity').val(lead && lead.city ? lead.city : '');
                $('#wpEmail').val(lead && lead.email ? lead.email : '');
                $('#wpPhone').val(lead && lead.phone ? lead.phone : '');
                $('#wpFirstContactChannel').val(firstContactChannel);
                $('#wpTipoIG').val(inferredTipoIG);
                updateWeddingPlannerDependentFields(false);
                $('#wpOrigen').val(inferredOrigen);
                $('#wpPlatform').val(platformValue);
                syncWeddingPlannerMedio();
                $('#wpHowLongKnownUs').val(lead && lead.how_long_known_us ? lead.how_long_known_us : '');
                syncCoordinatorChoiceCards('#wpHowLongKnownUs');
                $('#wpAssignedVendor').val('');
                $('#wpAfianzadoStatus').val(lead && typeof lead.afianzado !== 'undefined' ? String(parseInt(lead.afianzado, 10) || 0) : '0');
                $('#btnGuardarWeddingPlanner').data('edit-id', lead && lead.id ? parseInt(lead.id, 10) : 0);
                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('registroManualWeddingModal'));
                modal.show();
            };

            function escapeHtmlCwp(text) {
                return String(text || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function renderVendedorCellHtml(vendorId, nombre, apepat) {
                var fullName = (String(nombre || '') + ' ' + String(apepat || '')).trim();
                if (vendorId > 0 && fullName) {
                    var agentMatch = (cwpAgentes || []).find(function (a) { return parseInt(a.id, 10) === vendorId; });
                    var initial = agentMatch && agentMatch.initial ? agentMatch.initial : (fullName.charAt(0).toUpperCase() || 'S');
                    var color = agentMatch && agentMatch.color ? agentMatch.color : '#64748B';
                    return '<div class="vendedor-badge vendedor-assign-cell js-assign-vendedor" title="Clic para cambiar vendedora">' +
                        '<div class="vendedor-circle" style="background-color:' + color + '">' + escapeHtmlCwp(initial) + '</div>' +
                        '<span class="vendedor-name">' + escapeHtmlCwp(fullName) + '</span></div>';
                }
                return '<span class="origin-badge js-assign-vendedor" title="Clic para asignar vendedora">Sin vendedor</span>';
            }

            function openAssignVendedorPicker($row) {
                var wpId = parseInt($row.attr('data-wp-id'), 10) || 0;
                var wpName = $row.attr('data-wp-name') || '';
                var currentId = parseInt($row.attr('data-vendedor-id'), 10) || 0;

                if (!wpId) {
                    Swal.fire({ icon: 'info', title: 'Sin perfil', text: 'Este registro no tiene wedding planner vinculado.' });
                    return;
                }

                var optionsHtml = '<option value="0">Sin vendedor</option>';
                (cwpAgentes || []).forEach(function (agente) {
                    var agentId = parseInt(agente.id, 10) || 0;
                    if (!agentId) {
                        return;
                    }
                    var selected = agentId === currentId ? ' selected' : '';
                    var label = agente.label || ('Usuario #' + agentId);
                    optionsHtml += '<option value="' + agentId + '"' + selected + '>' + escapeHtmlCwp(label) + '</option>';
                });

                Swal.fire({
                    title: 'Asignar vendedora',
                    html: '<p style="margin:0 0 12px;color:#64748b;font-size:0.9rem;">' + escapeHtmlCwp(wpName) + '</p>' +
                        '<select id="swalVendedorSelect" class="form-select" style="width:100%;">' + optionsHtml + '</select>',
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
                    focusConfirm: false,
                    preConfirm: function () {
                        var selectEl = document.getElementById('swalVendedorSelect');
                        return selectEl ? parseInt(selectEl.value, 10) || 0 : 0;
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    var newVendorId = parseInt(result.value, 10);
                    if (isNaN(newVendorId)) {
                        newVendorId = 0;
                    }

                    $.post('asignar_vendedor_planner.php', {
                        planner_id: wpId,
                        id_vendedor_asignado: newVendorId
                    }, function (resp) {
                        if (resp && resp.success) {
                            $row.attr('data-vendedor-id', newVendorId);
                            $row.find('td[data-column="vendedor"]').html(
                                renderVendedorCellHtml(
                                    parseInt(resp.id_vendedor_asignado, 10) || 0,
                                    resp.vendedor_nombre || '',
                                    resp.vendedor_apepat || ''
                                )
                            );
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
                    }, 'json').fail(function () {
                        Swal.fire('Error', 'Error al conectar con el servidor', 'error');
                    });
                });
            }

            $('#leadsTable tbody').on('click', '.js-assign-vendedor', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openAssignVendedorPicker($(this).closest('tr'));
            });

            function openAssignAfianzadoPicker($row) {
                var wpId = parseInt($row.attr('data-wp-id'), 10) || 0;
                var wpName = $row.attr('data-wp-name') || '';
                var currentValue = parseInt($row.attr('data-afianzado'), 10) || 0;

                if (!wpId) {
                    Swal.fire({ icon: 'info', title: 'Sin perfil', text: 'Este registro no tiene wedding planner vinculado.' });
                    return;
                }

                var optionsHtml = '';
                (cwpAfianzadoOptions || []).forEach(function (option) {
                    var optionValue = parseInt(option.value, 10);
                    if (isNaN(optionValue)) {
                        return;
                    }
                    var selected = optionValue === currentValue ? ' selected' : '';
                    optionsHtml += '<option value="' + optionValue + '"' + selected + '>' +
                        escapeHtmlCwp(option.label || '') + '</option>';
                });

                Swal.fire({
                    title: 'Estatus de afianzado',
                    html: '<p style="margin:0 0 12px;color:#64748b;font-size:0.9rem;">' + escapeHtmlCwp(wpName) + '</p>' +
                        '<select id="swalAfianzadoSelect" class="form-select" style="width:100%;">' + optionsHtml + '</select>',
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
                    focusConfirm: false,
                    preConfirm: function () {
                        var selectEl = document.getElementById('swalAfianzadoSelect');
                        return selectEl ? parseInt(selectEl.value, 10) || 0 : 0;
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    var newValue = parseInt(result.value, 10);
                    if (isNaN(newValue)) {
                        newValue = 0;
                    }

                    $.post('actualizar_afianzado_wp.php', {
                        id: wpId,
                        afianzado: newValue
                    }, function (resp) {
                        if (resp && resp.success) {
                            var savedValue = parseInt(resp.afianzado, 10);
                            if (isNaN(savedValue)) {
                                savedValue = newValue;
                            }
                            $row.attr('data-afianzado', savedValue);
                            $row.find('td[data-column="afianzado"]').html(renderAfianzadoCellHtml(savedValue));
                            Swal.fire({
                                icon: 'success',
                                title: 'Listo',
                                text: 'Afianzado actualizado a: ' + (resp.label || getAfianzadoBadgeState(savedValue).label),
                                timer: 1600,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo actualizar', 'error');
                        }
                    }, 'json').fail(function () {
                        Swal.fire('Error', 'Error al conectar con el servidor', 'error');
                    });
                });
            }

            $('#leadsTable tbody').on('click', '.js-assign-afianzado', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openAssignAfianzadoPicker($(this).closest('tr'));
            });

            $('#leadsTable tbody').on('keydown', '.js-assign-afianzado', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openAssignAfianzadoPicker($(this).closest('tr'));
                }
            });

            window.deleteWeddingPlanner = function (id) {
                if (!id) return;
                Swal.fire({
                    title: '¿Ocultar Wedding Planner?',
                    text: 'El planner se ocultará de la lista pero permanecerá en la base de datos.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ocultar',
                    cancelButtonText: 'Cancelar'
                }).then(function (r) {
                    if (r.isConfirmed) {
                        $.post('eliminar_wedding_planner.php', { id: id }, function (resp) {
                            if (resp && resp.success) {
                                Swal.fire('Oculto', 'Wedding Planner ocultado correctamente', 'success').then(function () { location.reload(); });
                            } else {
                                Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo ocultar', 'error');
                            }
                        }, 'json').fail(function (xhr) { showErrorModalFromResponse(null, xhr); });
                    }
                });
            };

            // End helpers
        });
    </script>

    <!-- Modal de éxito con link del formulario -->
    <div class="modal fade" id="linkFormularioModal" tabindex="-1" aria-labelledby="linkFormularioModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="bg-success text-white modal-header">
                    <h5 class="modal-title" id="linkFormularioModalLabel"><i class="bi bi-check-circle"></i> Registro
                        guardado
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3 text-center">El registro se guardó correctamente.</p>
                    <div class="mb-3">
                        <label for="linkFormulario" class="form-label">Link del formulario de agenda:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="linkFormulario" readonly>
                            <button class="btn-outline-primary btn" type="button" id="btnCopiarLink"
                                title="Copiar link">
                                <i class="bi bi-clipboard"></i> Copiar
                            </button>
                        </div>
                    </div>
                    <div id="copiadoAlert" class="alert alert-success d-none" role="alert">
                        <i class="bi bi-check2"></i> ¡Link copiado al portapapeles!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var debounceTimer = null;

        function updateTableCountLabel(visible, total) {
            var footerEl = document.getElementById('wp-table-count-label');
            if (!footerEl) {
                return;
            }

            if (visible === total) {
                footerEl.innerHTML = '<strong>' + visible.toLocaleString() + '</strong> ' + (visible === 1 ? 'registro' : 'registros');
                return;
            }

            footerEl.innerHTML = 'Mostrando <strong>' + visible.toLocaleString() + '</strong> de <strong>' + total.toLocaleString() + '</strong> registros';
        }

        function filterTable(query) {
            var q = query.trim().toLowerCase();
            var rows = document.querySelectorAll('#leadsTable tbody tr');
            var visible = 0;
            var total = rows.length;

            for (var i = 0; i < rows.length; i++) {
                var row = rows[i];
                if (!q) {
                    row.style.display = '';
                    visible++;
                    continue;
                }
                var name     = row.getAttribute('data-full-name') || '';
                var email    = row.getAttribute('data-email') || '';
                var phone    = row.getAttribute('data-phone') || '';
                var campaign = row.getAttribute('data-campaign') || '';

                if (name.indexOf(q) !== -1 || email.indexOf(q) !== -1 ||
                    phone.indexOf(q) !== -1 || campaign.indexOf(q) !== -1) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            }

            updateTableCountLabel(visible, total);

            var countEl = document.getElementById('leadsSearchCount');
            if (countEl) {
                countEl.textContent = q ? (visible + ' resultado' + (visible !== 1 ? 's' : '')) : '';
            }
        }

        function updateClearBtn(val) {
            var btn = document.getElementById('leadsSearchClear');
            if (btn) btn.style.display = val.trim() ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function () {
            var input = document.getElementById('leadsSearchInput');
            var clearBtn = document.getElementById('leadsSearchClear');
            if (!input) return;

            var initialRows = document.querySelectorAll('#leadsTable tbody tr');
            updateTableCountLabel(initialRows.length, initialRows.length);

            // Apply filter on page load if there is a pre-filled search value
            if (input.value.trim()) {
                filterTable(input.value);
                updateClearBtn(input.value);
            }

            input.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                var val = this.value;
                updateClearBtn(val);
                debounceTimer = setTimeout(function () {
                    filterTable(val);
                }, 200);
            });

            // Prevent form submit on Enter inside the search field (live filter handles it)
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(debounceTimer);
                    filterTable(this.value);
                }
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    input.value = '';
                    updateClearBtn('');
                    clearTimeout(debounceTimer);
                    filterTable('');
                    input.focus();
                });
            }
        });
    })();
    </script>

    <?php

    include 'footer.php';

    ?>

    <!-- Compatibilidad con simple-datatables global (evita error cuando no existe #table1) -->
    <table id="table1" style="display:none;">
        <thead>
            <tr><th>placeholder</th></tr>
        </thead>
        <tbody></tbody>
    </table>


</body>

</html>