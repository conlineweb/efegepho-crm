<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos
require_once __DIR__ . '/campaign_badge_helper.php';
require_once __DIR__ . '/lead_field_badge_helper.php';
require_once __DIR__ . '/lead_origin_helper.php';
require_once __DIR__ . '/wp_eventos_contact_form_helper.php';

$__weddingDateNotDefinedCol = $conn->query("SHOW COLUMNS FROM `organic_leads` LIKE 'wedding_date_not_defined'");
if ($__weddingDateNotDefinedCol && $__weddingDateNotDefinedCol->num_rows === 0) {
    $conn->query("ALTER TABLE `organic_leads` ADD COLUMN `wedding_date_not_defined` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=cliente sin fecha de evento definida'");
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

function formatArrivalDateTime($dateString)
{
    if (empty($dateString))
        return '';
    $timestamp = strtotime($dateString);
    if ($timestamp === false || $timestamp <= 0)
        return '';
    return date('d/m/Y h:i a', $timestamp);
}

function formatLeadDate($dateString, $notDefined = false)
{
    if ($notDefined) {
        return 'Sin definir';
    }

    $value = trim((string) $dateString);
    if ($value === '')
        return '—';

    $timestamp = strtotime($value);
    if ($timestamp === false)
        return $value;

    return date('d/m/Y', $timestamp);
}

function formatLeadSessionTimeDisplay($timeString)
{
    $value = trim((string) $timeString);
    if ($value === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('h:i A', $timestamp);
}

function buildLeadSessionInfo(array $lead, $cfRow, $appointment, $assignedUserId, array $vendedoresById)
{
    $hasAppointment = is_array($appointment) && (
        trim((string) ($appointment['fecha'] ?? '')) !== '' ||
        trim((string) ($appointment['fecha_cliente'] ?? '')) !== '' ||
        trim((string) ($appointment['hora'] ?? '')) !== '' ||
        trim((string) ($appointment['hora_cliente'] ?? '')) !== ''
    );
    $status = mb_strtolower(trim((string) ($lead['estatus'] ?? '')), 'UTF-8');
    $isScheduledStatus = in_array($status, ['agendado', 'atendido', 'cliente'], true);

    if (!$hasAppointment && !$isScheduledStatus) {
        return null;
    }

    $clientName = firstNonEmptyValue(
        $lead['full_name'] ?? '',
        is_array($cfRow) ? ($cfRow['names'] ?? '') : ''
    );

    $sessionDate = '';
    $sessionTime = '';
    if (is_array($appointment)) {
        $sessionDate = firstNonEmptyValue($appointment['fecha_cliente'] ?? '', $appointment['fecha'] ?? '');
        $sessionTime = firstNonEmptyValue($appointment['hora_cliente'] ?? '', $appointment['hora'] ?? '');
    }
    if ($sessionDate === '' && is_array($cfRow)) {
        $sessionDate = trim((string) ($cfRow['date_appointment'] ?? ''));
    }
    if ($sessionTime === '' && is_array($cfRow)) {
        $sessionTime = trim((string) ($cfRow['time_appointment'] ?? ''));
    }

    $advisorLabel = getVendedorAsignadoLabel($assignedUserId, $vendedoresById);
    $meetLink = '';
    if ($assignedUserId > 0 && isset($vendedoresById[$assignedUserId])) {
        $meetLink = trim((string) ($vendedoresById[$assignedUserId]['enlace_meet'] ?? ''));
    }

    return [
        'nombre_cliente' => $clientName !== '' ? $clientName : '—',
        'fecha' => formatLeadDate($sessionDate),
        'hora' => formatLeadSessionTimeDisplay($sessionTime),
        'fecha_raw' => $sessionDate,
        'hora_raw' => $sessionTime,
        'asesora' => $advisorLabel !== '—' ? $advisorLabel : 'Sin asignar',
        'enlace_meet' => $meetLink !== '' ? $meetLink : '—',
    ];
}

function firstNonEmptyValue()
{
    foreach (func_get_args() as $value) {
        if ($value === null)
            continue;

        $normalized = trim((string) $value);
        if ($normalized !== '')
            return $normalized;
    }

    return '';
}

function isB1B2CampaignName($campaignName)
{
    if ($campaignName === null)
        return false;
    $v = strtolower(trim((string) $campaignName));
    if ($v === '')
        return false;
    $v = preg_replace('/\s+/', ' ', $v);
    return in_array($v, ['b1', 'b1 (usa)', 'b1 usa', 'b2', 'b2 (mx)', 'b2 mx', 'b3', 'b3 (mex 2)', 'b3 mex 2', 'b4', 'b4 (latam)', 'b4 latam'], true);
}

function getDondeNosConocioLabel($lead)
{
    $howRaw = resolveHowDidYouMeetCode(
        $lead['how_did_you_meet'] ?? '',
        $lead['how_long_known_us'] ?? '',
        $lead
    );
    $hearRaw = trim((string)($lead['hear_about_us'] ?? ''));
    $tablaOrigen = strtolower(trim((string) ($lead['tabla_origen'] ?? '')));

    $howMap = [
        '1' => 'Wedding Planner',
        '2' => 'Community',
        '3' => 'New Audience'
    ];

    if ($howRaw === '' && isWpPlannerLeadTable($tablaOrigen)) {
        $howRaw = '1';
    }

    $howLabel = ($howRaw !== '') ? ($howMap[$howRaw] ?? $howRaw) : '';

    if ($howLabel !== '') return $howLabel;
    return 'N/A';
}

function getOriginCategoryLabel($lead)
{
    return getOrigenCategoriaLabel($lead);
}

function getOriginBadgeClass($label)
{
    if ($label === 'Wedding Planner') {
        return 'badge badge-planner';
    }

    if ($label === 'Community') {
        return 'badge badge-community';
    }

    if ($label === 'New Audience') {
        return 'badge badge-newmarket';
    }

    return 'badge badge-neutral';
}

function getContactChannelBadgeLabel($label)
{
    $map = [
        'WhatsApp' => 'WhatsApp',
        'Instagram DM - Campaña' => 'IG Campaign',
        'Instagram DM - Orgánico' => 'IG Organic',
        'Correo electrónico' => 'Email',
        'Llamada telefónica' => 'Phone Call',
        'Sin dato' => 'Not asked'
    ];

    return $map[$label] ?? $label;
}

function getKnownUsBadgeLabel($value)
{
    $normalized = trim((string) $value);
    if ($normalized === '' || strcasecmp($normalized, 'Sin dato') === 0 || strcasecmp($normalized, 'Not asked') === 0) {
        return 'Todavía no sabemos';
    }

    return $normalized;
}

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

function formatCampaignDisplayLabel($value, $referrerUrl = '', $firstContactChannel = '')
{
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return '—';
    }

    // c-pattern: calcular base label pero NO retornar aún (puede necesitar sufijo)
    $baseLabel = preg_match('/^(c\d+)\b/i', $normalized, $matches)
        ? strtolower($matches[1])
        : $normalized;

    // Distinguir Website orgánico vs Website Google Ads
    if (strtolower($normalized) === 'website') {
        $hasTracking = false;
        if (trim((string)$referrerUrl) !== '') {
            $query = parse_url((string)$referrerUrl, PHP_URL_QUERY) ?? '';
            if ($query !== '') {
                parse_str($query, $params);
                $trackingKeys = ['gclid','gbraid','wbraid','fbclid','msclkid','ttclid',
                    'utm_source','utm_medium','utm_campaign','utm_content','utm_term','utm_id'];
                foreach ($params as $k => $v) {
                    $kl = strtolower((string)$k);
                    if (strpos($kl, 'utm_') === 0 || in_array($kl, $trackingKeys, true)) {
                        $hasTracking = true;
                        break;
                    }
                }
            }
        }
        return $hasTracking ? 'Website - Google Ads' : 'Website - Orgánico';
    }

    $lower = strtolower($normalized);

    if ($lower === 'mail') {
        return 'Mail - Orgánico';
    }

    if ($lower === 'whatsapp') {
        return 'WhatsApp - Orgánico';
    }

    if ($lower === 'ig organico') {
        return 'Ig - Orgánico';
    }

    // Prefijo según origen: b1/b2 → "IG - ", leadform/andromeda/c-pattern → "leadform - "
    $fccLower = strtolower(trim((string)$firstContactChannel));
    $normalizedLower = strtolower($normalized);
    $isCPattern = (bool) preg_match('/^(c\d+)\b/i', $normalized);
    if (isB1B2CampaignName($normalized)) {
        return 'IG - ' . $baseLabel;
    }
    $isLeadform = ($fccLower === 'leadform')
        || strpos($normalizedLower, 'andromeda') !== false
        || $isCPattern;
    $prefix = $isLeadform ? 'leadform - ' : '';
    return $prefix . $baseLabel;
}

function getEngagementLabelWithEmoji($lead)
{
    $raw = trim((string)($lead['engagement'] ?? ''));
    if ($raw === '') return 'N/A';

    $normalized = mb_strtolower($raw, 'UTF-8');
    if ($normalized === '0') return 'Sin dato';
    if ($normalized === '1' || $normalized === 'bajo') return '😑 Bajo';
    if ($normalized === '2' || $normalized === 'medio') return '😃 Medio';
    if ($normalized === '3' || $normalized === 'alto') return '🔥 Alto';

    return $raw;
}

function normalizeFirstContactChannelLabel($value)
{
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return 'Sin dato';
    }

    $key = mb_strtolower($normalized, 'UTF-8');
    $key = str_replace(['–', '—'], '-', $key);
    $key = preg_replace('/\s+/', ' ', $key);

    $map = [
        'whatsapp' => 'WhatsApp',
        'instagram dm - campaign' => 'Instagram DM - Campaña',
        'instagram dm campaign' => 'Instagram DM - Campaña',
        'instagram dm - organic' => 'Instagram DM - Orgánico',
        'instagram dm organic' => 'Instagram DM - Orgánico',
        'email' => 'Correo electrónico',
        'correo electronico' => 'Correo electrónico',
        'correo electrónico' => 'Correo electrónico',
        'mail' => 'Correo electrónico',
        'phone call' => 'Llamada telefónica',
        'llamada telefonica' => 'Llamada telefónica',
        'llamada telefónica' => 'Llamada telefónica'
    ];

    return $map[$key] ?? $normalized;
}

function formatPercentage($part, $total, $decimals = 1)
{
    $total = floatval($total);
    if ($total <= 0) {
        return '0%';
    }

    $formatted = number_format((floatval($part) / $total) * 100, $decimals, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');

    return $formatted . '%';
}

function resolveLeadStatus($tableName, $contactFormRow, $appointmentRow, $leadRow = null)
{
    if (strcasecmp((string) $tableName, 'wp_citas_leads') === 0) {
        $directStatus = firstNonEmptyValue(
            $leadRow['lead_status'] ?? '',
            $leadRow['estatus_cita'] ?? '',
            $leadRow['estatus'] ?? ''
        );
        if ($directStatus !== '') {
            return trim(mb_strtolower($directStatus, 'UTF-8'));
        }
    }

    if (isset($contactFormRow['cliente']) && intval($contactFormRow['cliente']) === 1) {
        return 'cliente';
    }

    if (strcasecmp((string) $tableName, 'eventos_wp') === 0) {
        if ($contactFormRow !== null) {
            global $conn;
            if (isset($conn)) {
                $cf = [
                    'tabla_origen' => 'eventos_wp',
                    'form_name' => 'eventos_wp',
                    'original_lead_id' => (int) ($leadRow['id'] ?? 0),
                    'tipo_cliente' => $contactFormRow['tipo_cliente'] ?? 1,
                    'cliente' => $contactFormRow['cliente'] ?? 0,
                ];
                $cf = wpEventosCfHydratePlannerContactFormFields($conn, $cf);
                $resolved = wpEventosCfResolvePlannerTipoClienteEstatus($cf);
                if ($resolved !== null && $resolved !== '') {
                    return $resolved;
                }
            }

            return 'atendido';
        }

        return 'agendado';
    }

    if ($appointmentRow === null || !isset($appointmentRow['estatus'])) {
        return 'lead';
    }

    if (strcasecmp((string) $tableName, 'wedding_planners') === 0) {
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

// Leer filtro de plataforma (viene por GET)
$filterPlataforma = isset($_GET['filter_plataforma']) ? trim($_GET['filter_plataforma']) : '';
// Leer filtros de fecha y búsqueda (vienen por GET)
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
// Persistir filtros de fecha en sesión para compartirlos entre consulta_leads, consulta_post_leads y clientes
if (isset($_GET['show_all'])) {
    unset($_SESSION['leads_filter_start'], $_SESSION['leads_filter_end']);
} elseif ($startDate !== '' || $endDate !== '') {
    $_SESSION['leads_filter_start'] = $startDate;
    $_SESSION['leads_filter_end']   = $endDate;
} elseif (!empty($_SESSION['leads_filter_start']) || !empty($_SESSION['leads_filter_end'])) {
    $startDate = $_SESSION['leads_filter_start'] ?? '';
    $endDate   = $_SESSION['leads_filter_end']   ?? '';
}
// Si no se pasan fechas por GET, por defecto mostrar los últimos 14 días
// Solo aplicar el default cuando NO se haya pedido explícitamente "mostrar todo" (show_all=1)
if ($startDate === '' && $endDate === '' && empty($_GET['show_all'])) {
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-14 days', strtotime($endDate)));
}
// Etiqueta amigable para mostrar en los cards: (todo|campañas digitales|organico)
$platformLabel = 'todo';
if ($filterPlataforma === 'campania') {
    $platformLabel = 'campañas digitales';
} elseif ($filterPlataforma === 'organico') {
    $platformLabel = 'organico';
}

// Obtener tablas de leads según el filtro de plataforma
$tablas = [];
if ($filterPlataforma === 'organico') {
    // Filtrar solo tablas con tipo = 0 (Orgánico)
    $sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo = 0 ORDER BY nombre";
} elseif ($filterPlataforma === 'campania') {
    // Filtrar tablas de campaña y también organic_leads para incluir B1/B2
    $sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo = 1 OR nombre = 'organic_leads' ORDER BY nombre";
} else {
    // Sin filtro, mostrar todas las tablas excepto Wedding Planner (tipo = 2),
    // pero incluir explícitamente las citas reales espejadas de WP.
    $sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo != 2 OR nombre = 'wp_citas_leads' ORDER BY nombre";
}

$resultTablas = $conn->query($sqlTablas);

if ($resultTablas && $resultTablas->num_rows > 0) {
    while ($row = $resultTablas->fetch_assoc()) {
        $tablas[] = $row['nombre'];
    }
}

// Consultar todos los leads de todas las tablas
$allLeads = [];

$b1b2Values = [
    'b1', 'b1 (usa)', 'b1 usa',
    'b2', 'b2 (mx)', 'b2 mx',
    'b3', 'b3 (mex 2)', 'b3 mex 2',
    'b4', 'b4 (latam)', 'b4 latam'
];

foreach ($tablas as $tableName) {
    // Verificar que la tabla existe
    $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($checkTable->num_rows > 0) {
        // Verificar qué columnas existen en la tabla
        $columns = [];
        $columnsResult = $conn->query("SHOW COLUMNS FROM `$tableName`");
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }

        // Construir condiciones SQL para filtrar en base de datos (más rápido que en PHP)
        $whereParts = [];

        if (in_array('descartado', $columns)) {
            $whereParts[] = "(descartado = 0 OR descartado IS NULL)";
        }

        if (($startDate !== '' || $endDate !== '') && in_array('created_time', $columns)) {
            $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
            $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
            // Manejar tanto formato ISO 8601 (2026-02-03T09:12:17-06:00) como datetime (2026-02-03 09:12:17)
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
                if (in_array($colName, $columns)) {
                    $searchCols[] = "IFNULL(`$colName`, '')";
                }
            }
            if (!empty($searchCols)) {
                $q = $conn->real_escape_string(mb_strtolower($searchQuery, 'UTF-8'));
                $whereParts[] = "LOWER(CONCAT_WS(' ', " . implode(', ', $searchCols) . ")) LIKE '%" . $q . "%'";
            }
        }

        // Mover B1/B2 de orgánico a campañas digitales desde SQL cuando aplica
        if ($tableName === 'organic_leads' && in_array('campaign_name', $columns)) {
            $campaignCol = "LOWER(TRIM(campaign_name))";
            $inList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $b1b2Values)) . "'";
            if ($filterPlataforma === 'organico') {
                $whereParts[] = "($campaignCol IS NULL OR $campaignCol = '' OR $campaignCol NOT IN ($inList))";
            } elseif ($filterPlataforma === 'campania') {
                $whereParts[] = "$campaignCol IN ($inList)";
            }
        }

        // Construir la consulta según las columnas disponibles ORDENANDOLO DEL MAS RECIENTE AL MAS ANTIGUIO
        $whereSql = !empty($whereParts) ? implode(' AND ', $whereParts) : '1=1';
        $orderCol = in_array('created_time', $columns) ? 'created_time' : (in_array('id', $columns) ? 'id' : null);
        $orderSql = $orderCol ? " ORDER BY `$orderCol` DESC" : '';
        $sqlLeads = "SELECT *, '$tableName' as tabla_origen FROM `$tableName` WHERE " . $whereSql . $orderSql;

        // DEBUG: Log query for debugging
        if ($startDate !== '' || $endDate !== '') {
            error_log("CONSULTA TABLA $tableName: " . $sqlLeads);
        }

        $resultLeads = $conn->query($sqlLeads);
        if ($resultLeads && $resultLeads->num_rows > 0) {
            while ($lead = $resultLeads->fetch_assoc()) {
                $allLeads[] = $lead;
                // DEBUG: Log leads fetched
                if ($startDate !== '' || $endDate !== '') {
                    error_log("Lead obtenido de $tableName: ID=" . ($lead['id'] ?? 'N/A') . ", Nombre=" . ($lead['full_name'] ?? 'N/A') . ", Fecha=" . ($lead['created_time'] ?? 'N/A'));
                }
            }
        }
    }
}

$wpEventosLeads = wpEventosCfBuildLeadsForConsultaLeads($conn);
if (!empty($wpEventosLeads)) {
    $existingKeys = [];
    foreach ($allLeads as $existingLead) {
        $existingKeys[($existingLead['tabla_origen'] ?? '') . '|' . (int) ($existingLead['id'] ?? 0)] = true;
    }
    foreach ($wpEventosLeads as $wpLead) {
        $key = ($wpLead['tabla_origen'] ?? '') . '|' . (int) ($wpLead['id'] ?? 0);
        if (isset($existingKeys[$key])) {
            continue;
        }

        if (($startDate !== '' || $endDate !== '') && empty($_GET['show_all'])) {
            $dateField = trim((string) (
                $wpLead['fecha_registro'] ?? $wpLead['created_time'] ?? $wpLead['submission_date'] ?? ''
            ));
            if ($dateField === '') {
                continue;
            }
            $ts = strtotime($dateField);
            if ($ts === false) {
                continue;
            }
            $d = date('Y-m-d', $ts);
            $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
            $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
            if ($sd && $d < $sd) {
                continue;
            }
            if ($ed && $d > $ed) {
                continue;
            }
        }

        $allLeads[] = $wpLead;
        $existingKeys[$key] = true;
    }
}

// Nota: no cerrar la conexión aquí porque la usaremos más adelante
// $conn->close();

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

// (Código de recopilación de orígenes y medios removido - ya no se usan esos filtros)

// DEBUG: Log total leads before filtering
if ($startDate !== '' || $endDate !== '') {
    error_log("Total leads obtenidos de BD (allLeads): " . count($allLeads));
}

// Aplicar filtros a los leads
$filteredLeads = [];
foreach ($allLeads as $lead) {
    $campaignName = $lead['campaign_name'] ?? '';
    $isB1B2 = isB1B2CampaignName($campaignName);
    $tablaOrigen = $lead['tabla_origen'] ?? '';

    // Filtrar por plataforma: mover B1/B2 de orgánico a campañas digitales
    if ($filterPlataforma === 'organico' && $isB1B2) {
        if ($startDate !== '' || $endDate !== '') {
            error_log("Lead filtrado por B1/B2 orgánico: ID=" . ($lead['id'] ?? 'N/A'));
        }
        continue;
    }
    if ($filterPlataforma === 'campania' && $tablaOrigen === 'organic_leads' && !$isB1B2) {
        if ($startDate !== '' || $endDate !== '') {
            error_log("Lead filtrado por campania no B1/B2: ID=" . ($lead['id'] ?? 'N/A'));
        }
        continue;
    }

    // El filtro por fecha ya se aplicó en la consulta SQL (líneas 103-111)
    // No necesitamos re-filtrar aquí

    // No hay filtros adicionales por origen, medio o estatus
    $filteredLeads[] = $lead;
}

// DEBUG: Log total leads after platform filtering
if ($startDate !== '' || $endDate !== '') {
    error_log("Total leads después de filtro plataforma (filteredLeads): " . count($filteredLeads));
}

// Filtro por búsqueda (server-side) para reducir carga en cliente
$normalizedSearch = mb_strtolower($searchQuery, 'UTF-8');
if ($normalizedSearch !== '') {
    if ($startDate !== '' || $endDate !== '') {
        error_log("Aplicando filtro de búsqueda: '$normalizedSearch'");
    }
    $filteredBySearch = [];
    foreach ($filteredLeads as $lead) {
        if (wpEventosCfLeadMatchesSearch($lead, $searchQuery)) {
            $filteredBySearch[] = $lead;
        }
    }
    $filteredLeads = $filteredBySearch;
    if ($startDate !== '' || $endDate !== '') {
        error_log("Total leads después de filtro búsqueda: " . count($filteredLeads));
    }
}

// Ordenar globalmente los leads por created_time (más recientes primero)
usort($filteredLeads, function ($a, $b) {
    $ta = (!empty($a['created_time']) && strtotime($a['created_time']) !== false) ? strtotime($a['created_time']) : 0;
    $tb = (!empty($b['created_time']) && strtotime($b['created_time']) !== false) ? strtotime($b['created_time']) : 0;
    if ($ta === $tb)
        return 0;
    return ($ta > $tb) ? -1 : 1;
});

// Conteo total de leads mostrados después del filtro (antes de filtro por estatus)
$totalCount = count($filteredLeads);

// Construir mapa por fecha y determinar primer/último created_time
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

// Si no hay datos, preparar arrays vacíos
if ($minTs === null) {
    $seriesJson = json_encode([]);
    $datesJson = json_encode([]);
    $countsJson = json_encode([]);
} else {
    // Normalizar al inicio del día
    $start = strtotime(date('Y-m-d', $minTs));
    $end = strtotime(date('Y-m-d', $maxTs));
    $series = [];
    $dates = [];
    $counts = [];
    for ($ts = $start; $ts <= $end; $ts += 86400) { // recorrer por días
        $d = date('Y-m-d', $ts);
        $c = isset($map[$d]) ? $map[$d] : 0;
        // Highcharts espera timestamp en ms
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
// --- Build a map of contact_form entries and appointments (to determine estatus for leads) ---
$contactFormByLead = [];
$cfIds = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '')
        continue;
    $sql = "SELECT id, original_lead_id, names, date_appointment, time_appointment, cliente, how_did_you_meet, hear_about_us, engagement, first_contact_channel, referrer_url, how_long_known_us, tipo_cliente FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")";
    $resCf = $conn->query($sql);
    if ($resCf) {
        while ($row = $resCf->fetch_assoc()) {
            $key = $t . '|' . intval($row['original_lead_id']);
            $contactFormByLead[$key] = [
                'cf_id'                => intval($row['id']),
                'names'                => $row['names'] ?? '',
                'date_appointment'     => $row['date_appointment'] ?? '',
                'time_appointment'     => $row['time_appointment'] ?? '',
                'cliente'              => isset($row['cliente']) ? intval($row['cliente']) : 0,
                'how_did_you_meet'     => $row['how_did_you_meet'] ?? null,
                'hear_about_us'        => $row['hear_about_us'] ?? null,
                'engagement'           => $row['engagement'] ?? null,
                'first_contact_channel'=> $row['first_contact_channel'] ?? null,
                'referrer_url'         => $row['referrer_url'] ?? '',
                'how_long_known_us'    => $row['how_long_known_us'] ?? '',
                'tipo_cliente'         => $row['tipo_cliente'] ?? null,
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

$calendarioIds = [];
foreach ($filteredLeads as $leadRow) {
    $calendarioId = intval($leadRow['id_calendario'] ?? 0);
    if ($calendarioId > 0) {
        $calendarioIds[$calendarioId] = true;
    }
}

$appointmentsByCalendarioId = [];
if (!empty($calendarioIds)) {
    $calList = implode(',', array_map('intval', array_keys($calendarioIds)));
    $calRes = $conn->query('SELECT id, idusu, fecha, hora, fecha_cliente, hora_cliente FROM calendario WHERE id IN (' . $calList . ')');
    if ($calRes) {
        while ($calRow = $calRes->fetch_assoc()) {
            $calId = intval($calRow['id'] ?? 0);
            if ($calId > 0) {
                $appointmentsByCalendarioId[$calId] = $calRow;
            }
        }
    }
}

$appointmentsByEventId = wpEventosCfGetAppointmentsByEventId($conn);

// Build final map of status per lead (tabla|id)
$leadStatusMap = [];
$leadRowsByKey = [];
foreach ($filteredLeads as $leadRow) {
    $leadRowsByKey[($leadRow['tabla_origen'] ?? '') . '|' . intval($leadRow['id'] ?? 0)] = $leadRow;
}

foreach ($leadIdsByTable as $t => $ids) {
    foreach ($ids as $lid) {
        $key = $t . '|' . intval($lid);
        $leadStatusMap[$key] = 'lead';
        $cf = isset($contactFormByLead[$key]) ? $contactFormByLead[$key] : null;
        $cfId = $cf ? intval($cf['cf_id']) : 0;
        $appointment = ($cfId > 0 && isset($appointmentsByCFId[$cfId])) ? $appointmentsByCFId[$cfId] : null;

        if (isset($leadRowsByKey[$key])) {
            $leadStatusMap[$key] = resolveLeadStatus($t, $cf, $appointment, $leadRowsByKey[$key]);
        }
    }
}

// Build map of template name used to schedule (latest click to inquire-form) per lead (tabla|id)
$agendoTemplateByLead = [];
$hasEmailClicksTable = false;
$checkEmailClicksTable = $conn->query("SHOW TABLES LIKE 'email_clicks'");
if ($checkEmailClicksTable && $checkEmailClicksTable->num_rows > 0) {
    $hasEmailClicksTable = true;
}

if ($hasEmailClicksTable) {
    foreach ($leadIdsByTable as $t => $ids) {
        $safeTable = $conn->real_escape_string($t);
        $idsList = implode(',', array_map('intval', $ids));
        if (trim($idsList) === '')
            continue;

        $sqlClicks = "SELECT ec.lead_id, ec.template_id, ec.clicked_at, mt.nombre AS template_nombre
                      FROM email_clicks ec
                      LEFT JOIN marketing_templates mt ON mt.id = ec.template_id
                      WHERE LOWER(ec.tabla_origen) = LOWER('" . $safeTable . "')
                        AND ec.lead_id IN (" . $idsList . ")
                        AND ec.template_id IS NOT NULL
                        AND ec.url LIKE '%inquire-form.php%'
                      ORDER BY ec.clicked_at DESC, ec.id DESC";
        $resClicks = $conn->query($sqlClicks);
        if ($resClicks) {
            while ($rowClick = $resClicks->fetch_assoc()) {
                $leadIdClick = isset($rowClick['lead_id']) ? intval($rowClick['lead_id']) : 0;
                if ($leadIdClick <= 0)
                    continue;
                $k = $t . '|' . $leadIdClick;
                if (!isset($agendoTemplateByLead[$k])) {
                    $templateNombre = trim((string) ($rowClick['template_nombre'] ?? ''));
                    if ($templateNombre === '') {
                        $templateIdTmp = isset($rowClick['template_id']) ? intval($rowClick['template_id']) : 0;
                        $templateNombre = $templateIdTmp > 0 ? ('Template #' . $templateIdTmp) : '';
                    }
                    $agendoTemplateByLead[$k] = $templateNombre;
                }
            }
        }
    }
}
?>
<?php
// (Código de estatus únicos removido - ya no se usa filtro de estatus)

// Calcular leads que agendaron (agendado, atendido, fantasma, muerto, cliente)
// Contamos atendidos y clientes desde $filteredLeads + $leadStatusMap (perspectiva lead tables)
$leadsAtendidosTotal = 0;
$leadsClientesTotal = 0;
$totalLeadsBeforeStatusFilter = count($filteredLeads);
foreach ($filteredLeads as $lead) {
    $tabla = $lead['tabla_origen'] ?? '';
    $lid = isset($lead['id']) ? intval($lead['id']) : 0;
    $key = $tabla . '|' . $lid;
    if (isset($leadStatusMap[$key])) {
        $status = strtolower($leadStatusMap[$key]);
        if ($status === 'atendido') {
            $leadsAtendidosTotal++;
        }
        if ($status === 'cliente') {
            $leadsClientesTotal++;
        }
    }
}

// =====================================================================
// Contar "Personas que agendaron" directamente desde contact_form + calendario
// (misma lógica que consulta_post_leads.php para que los números coincidan)
// =====================================================================
$_tablaTipoMapAgd = [];
$_rTablasAgd = $conn->query("SELECT nombre, tipo FROM tablas_leads");
if ($_rTablasAgd && $_rTablasAgd->num_rows > 0) {
    while ($_rT = $_rTablasAgd->fetch_assoc()) {
        $_tablaTipoMapAgd[$_rT['nombre']] = intval($_rT['tipo']);
    }
}

// Obtener citas más recientes por idclie (mismo criterio que consulta_post_leads)
$_apptByCf = [];
$_qCalAll = $conn->query("SELECT * FROM calendario");
if ($_qCalAll) {
    while ($_ar = $_qCalAll->fetch_assoc()) {
        $idclie = intval($_ar['idclie'] ?? 0);
        if ($idclie <= 0) continue;
        if (!isset($_apptByCf[$idclie])) {
            $_apptByCf[$idclie] = $_ar;
        } else {
            $prev = $_apptByCf[$idclie];
            $replace = false;
            if (!empty($_ar['fecha']) && !empty($prev['fecha'])) {
                $t1 = strtotime($_ar['fecha'] . ' ' . ($_ar['hora'] ?? '')) ?: 0;
                $t2 = strtotime($prev['fecha'] . ' ' . ($prev['hora'] ?? '')) ?: 0;
                if ($t1 > $t2) $replace = true;
            } elseif (!empty($_ar['id']) && !empty($prev['id'])) {
                if (intval($_ar['id']) > intval($prev['id'])) $replace = true;
            }
            if ($replace) $_apptByCf[$idclie] = $_ar;
        }
    }
}

$leadsAgendadosTotal = 0;
if (!empty($_apptByCf)) {
    $cfIdsList = implode(',', array_map('intval', array_keys($_apptByCf)));
    $_qCfAgd = $conn->query("SELECT id, tabla_origen, original_lead_id, created_time, submission_date, campaign_name, cliente FROM contact_form WHERE id IN ($cfIdsList) AND LOWER(COALESCE(tabla_origen, '')) NOT IN ('wedding_planners','wedding_planner')");
    if ($_qCfAgd) {
        while ($_cf = $_qCfAgd->fetch_assoc()) {
            $cfId = intval($_cf['id']);
            $tablaOrigen = $_cf['tabla_origen'] ?? '';
            $origId = intval($_cf['original_lead_id'] ?? 0);

            // Validar fecha de la cita (excluir citas con fecha inválida, igual que consulta_post_leads)
            if (isset($_apptByCf[$cfId])) {
                $apptFecha = trim($_apptByCf[$cfId]['fecha'] ?? '');
                $apptHora = trim($_apptByCf[$cfId]['hora'] ?? '');
                $apptTs = ($apptFecha !== '') ? strtotime($apptFecha . ' ' . $apptHora) : false;
                if ($apptFecha === '' || $apptFecha === '0000-00-00' || $apptTs === false || $apptTs <= 0) continue;
            }

            // Determinar fecha del lead: preferir created_time del lead original, fallback a contact_form
            // (misma lógica de merge que consulta_post_leads.php)
            $dateField = !empty($_cf['created_time']) ? $_cf['created_time'] : ($_cf['submission_date'] ?? '');
            if (!empty($tablaOrigen) && $origId > 0) {
                $safeTbl = $conn->real_escape_string($tablaOrigen);
                $checkTbl = $conn->query("SHOW TABLES LIKE '$safeTbl'");
                if ($checkTbl && $checkTbl->num_rows > 0) {
                    // Verificar qué columnas de fecha existen en la tabla original
                    $_colsRes = $conn->query("SHOW COLUMNS FROM `$safeTbl` WHERE Field IN ('created_time','created_at')");
                    $_availCols = [];
                    if ($_colsRes) {
                        while ($_colRow = $_colsRes->fetch_assoc()) {
                            $_availCols[] = $_colRow['Field'];
                        }
                    }
                    if (!empty($_availCols)) {
                        $selectCols = implode(',', array_map(function($c) { return "`$c`"; }, $_availCols));
                        $origRes = $conn->query("SELECT $selectCols FROM `$safeTbl` WHERE id = " . intval($origId) . " LIMIT 1");
                        if ($origRes && $origRes->num_rows > 0) {
                            $origRow = $origRes->fetch_assoc();
                            if (!empty($origRow['created_time'])) {
                                $dateField = $origRow['created_time'];
                            } elseif (!empty($origRow['created_at'])) {
                                $dateField = $origRow['created_at'];
                            }
                        }
                    }
                }
            }

            // Aplicar filtro de fecha (misma lógica que extractDateFromTimestamp en consulta_post_leads)
            if ($startDate !== '' || $endDate !== '') {
                if (empty($dateField)) continue;
                // Extraer fecha: si es ISO 8601, tomar primeros 19 chars sin timezone
                if (strpos($dateField, 'T') !== false) {
                    $_ts = strtotime(substr($dateField, 0, 19));
                } else {
                    $_ts = strtotime($dateField);
                }
                if ($_ts === false) continue;
                $_d = date('Y-m-d', $_ts);
                $_sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
                $_ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
                if ($_sd && $_d < $_sd) continue;
                if ($_ed && $_d > $_ed) continue;
            }

            // Aplicar filtro de plataforma
            if ($filterPlataforma !== '') {
                $tipoTabla = isset($_tablaTipoMapAgd[$tablaOrigen]) ? $_tablaTipoMapAgd[$tablaOrigen] : -1;
                $campaignNameLower = strtolower(trim($_cf['campaign_name'] ?? ''));
                $isB1B2 = isB1B2CampaignName($campaignNameLower);
                if ($filterPlataforma === 'organico') {
                    if ($isB1B2) continue;
                    if ($tipoTabla !== 0 && $campaignNameLower !== 'website' && $campaignNameLower !== 'reg manual') continue;
                }
                if ($filterPlataforma === 'campania') {
                    if ($tipoTabla !== 1 && !$isB1B2) continue;
                }
            }

            // Verificar estatus de la cita o si es cliente
            $apptStatus = $_apptByCf[$cfId]['estatus'] ?? null;
            $intStatus = is_numeric($apptStatus) ? intval($apptStatus) : null;
            $isCliente = (isset($_cf['cliente']) && intval($_cf['cliente']) === 1);
            if ($isCliente || ($intStatus !== null && in_array($intStatus, [0, 1, 2, 3], true))) {
                $leadsAgendadosTotal++;
                error_log("[AGENDADOS-DEBUG] CONTADO cfId=$cfId tabla=$tablaOrigen origId=$origId dateField=$dateField estatus=$apptStatus cliente=" . ($_cf['cliente'] ?? 'null'));
            } else {
                error_log("[AGENDADOS-DEBUG] NO-CONTADO cfId=$cfId tabla=$tablaOrigen origId=$origId dateField=$dateField estatus=$apptStatus cliente=" . ($_cf['cliente'] ?? 'null'));
            }
        }
    }
}
error_log("[AGENDADOS-DEBUG] TOTAL leadsAgendadosTotal=$leadsAgendadosTotal startDate=$startDate endDate=$endDate filterPlataforma=$filterPlataforma");
// Liberar memoria
unset($_apptByCf, $_tablaTipoMapAgd);

// Calcular tasa de conversión (porcentaje)
$tasaConversion = $totalLeadsBeforeStatusFilter > 0 ? round(($leadsAgendadosTotal / $totalLeadsBeforeStatusFilter) * 100, 2) : 0;

// Calcular tasa de asistencia Post-Q (Atendidos de los Agendados)
$tasaAsistenciaPostQ = $leadsAgendadosTotal > 0 ? round(($leadsAtendidosTotal / $leadsAgendadosTotal) * 100, 2) : 0;

// Calcular tasa de conversión a clientes (Clientes de los Atendidos)
$tasaConversionClientes = $leadsAtendidosTotal > 0 ? round(($leadsClientesTotal / $leadsAtendidosTotal) * 100, 2) : 0;
?>
<?php
// (Código de filtro por estatus removido)

// Re-ordenar después de filtrar por estatus
usort($filteredLeads, function ($a, $b) {
    $ta = (!empty($a['created_time']) && strtotime($a['created_time']) !== false) ? strtotime($a['created_time']) : 0;
    $tb = (!empty($b['created_time']) && strtotime($b['created_time']) !== false) ? strtotime($b['created_time']) : 0;
    if ($ta === $tb)
        return 0;
    return ($ta > $tb) ? -1 : 1;
});

// Recalcular conteos y serie (basados en $filteredLeads finales)
$totalCount = count($filteredLeads);
$todayCount = 0;
$today = date('Y-m-d');
foreach ($filteredLeads as $lead) {
    if (empty($lead['created_time']))
        continue;
    $ts = strtotime($lead['created_time']);
    if ($ts === false)
        continue;
    if (date('Y-m-d', $ts) === $today)
        $todayCount++;
}

// Reconstruir mapa por fecha y la serie para la gráfica
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

$eventWpIds = [];
foreach ($filteredLeads as $lead) {
    if (strcasecmp((string) ($lead['tabla_origen'] ?? ''), 'eventos_wp') === 0) {
        $eventId = intval($lead['id'] ?? 0);
        if ($eventId > 0) {
            $eventWpIds[$eventId] = $eventId;
        }
    }
}

$eventWpAfianzadosMap = [];
if (!empty($eventWpIds)) {
    $eventIdsList = implode(',', array_map('intval', array_values($eventWpIds)));
    $sqlEventWpAfianzados = "SELECT e.id, COALESCE(wp.afianzado, 0) AS afianzado FROM eventos_wp e LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id WHERE e.id IN ($eventIdsList)";
    $resEventWpAfianzados = $conn->query($sqlEventWpAfianzados);
    if ($resEventWpAfianzados) {
        while ($rowEventWp = $resEventWpAfianzados->fetch_assoc()) {
            $eventWpAfianzadosMap[intval($rowEventWp['id'] ?? 0)] = intval($rowEventWp['afianzado'] ?? 0) === 1 ? 1 : 0;
        }
    }
}

// Conteo total de todos los eventos de wedding planners afianzados (sin filtro de fechas)
$resWpAfianzadosTotal = $conn->query("SELECT COUNT(e.id) AS total FROM eventos_wp e INNER JOIN wedding_planners wp ON wp.id = e.wedding_planner_id WHERE wp.afianzado = 1");
$wpAfianzadosCount = 0;
if ($resWpAfianzadosTotal) {
    $rowWpAfianzadosTotal = $resWpAfianzadosTotal->fetch_assoc();
    $wpAfianzadosCount = intval($rowWpAfianzadosTotal['total'] ?? 0);
}

$contactMethodCounts = [];
$originClientCounts = [];
$knownUsCounts = [];
$campaignDisplayCounts = [];
$pendingLeadCount = 0;
foreach ($filteredLeads as $lead) {
    $_reportRowKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);
    applyResolvedHowDidYouMeetToLead(
        $lead,
        isset($contactFormByLead[$_reportRowKey]) ? $contactFormByLead[$_reportRowKey] : null
    );

    $contactLabel = normalizeFirstContactChannelLabel(resolveFirstContactChannelForLead($lead));
    if (!isset($contactMethodCounts[$contactLabel])) {
        $contactMethodCounts[$contactLabel] = 0;
    }
    $contactMethodCounts[$contactLabel]++;

    $originLabel = getDondeNosConocioLabel($lead);
    if (!isset($originClientCounts[$originLabel])) {
        $originClientCounts[$originLabel] = 0;
    }
    $originClientCounts[$originLabel]++;

    $_rowKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);
    $_hlku = trim((string)($lead['how_long_known_us'] ?? ''));
    if ($_hlku === '') {
        $_hlku = trim((string)($contactFormByLead[$_rowKey]['how_long_known_us'] ?? ''));
    }
    $knownUsLabel = getKnownUsBadgeLabel($_hlku);
    if (!isset($knownUsCounts[$knownUsLabel])) {
        $knownUsCounts[$knownUsLabel] = 0;
    }
    $knownUsCounts[$knownUsLabel]++;
    $_cfRefUrl = isset($contactFormByLead[$_rowKey]['referrer_url']) ? $contactFormByLead[$_rowKey]['referrer_url'] : '';
    $_cfFcc    = isset($contactFormByLead[$_rowKey]['first_contact_channel']) ? $contactFormByLead[$_rowKey]['first_contact_channel'] : ($lead['first_contact_channel'] ?? '');
    $_campLabel = formatCampaignDisplayLabel(trim((string)($lead['campaign_name'] ?? '')), $_cfRefUrl, $_cfFcc);
    if ($_campLabel === '' || $_campLabel === '—') $_campLabel = 'Sin dato';
    if (!isset($campaignDisplayCounts[$_campLabel])) {
        $campaignDisplayCounts[$_campLabel] = 0;
    }
    $campaignDisplayCounts[$_campLabel]++;

    if ($originLabel === 'N/A') {
        $pendingLeadCount++;
    }
}

arsort($contactMethodCounts);
arsort($originClientCounts);
arsort($knownUsCounts);
arsort($campaignDisplayCounts);

// Recalcular maps finales (opens, wedding locations, scheduled) para los leads filtrados
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
$weddingDatesMap = [];
$leadCitiesMap = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '')
        continue;
    $sql = "SELECT original_lead_id, wedding_location, wedding_date, city FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")";
    $resLoc = $conn->query($sql);
    if ($resLoc) {
        while ($rowLoc = $resLoc->fetch_assoc()) {
            $key = $t . '|' . intval($rowLoc['original_lead_id']);
            if (!empty($rowLoc['wedding_location'])) {
                $weddingLocationsMap[$key] = $rowLoc['wedding_location'];
            }
            if (!empty($rowLoc['wedding_date'])) {
                $weddingDatesMap[$key] = $rowLoc['wedding_date'];
            }
            if (!empty($rowLoc['city'])) {
                $leadCitiesMap[$key] = $rowLoc['city'];
            }
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
// OPTIMIZACIÓN: leer el directorio UNA sola vez y usar lookup en memoria (evita miles de file_exists)
$whatsappSessionsMap = [];
$sessionsDir = __DIR__ . '/../whatsapp/sessions';
if (is_dir($sessionsDir)) {
    $files = scandir($sessionsDir);
    $sessionFiles = [];
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $sessionFiles[$f] = true;
    }

    foreach ($filteredLeads as $lead) {
        $phone = $lead['phone'] ?? '';
        if (empty($phone))
            continue;

        // Normalizar teléfono a solo dígitos
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
            if ($phoneClean) {
                $possibleFiles[] = "whatsapp_{$phoneClean}.json";
                $possibleFiles[] = "whatsapp_521{$phoneClean}.json";
                $possibleFiles[] = "whatsapp_52{$phoneClean}.json";
            }
        }

        if (strlen($phoneDigits) > 10) {
            $last10 = substr($phoneDigits, -10);
            $possibleFiles[] = "whatsapp_{$last10}.json";
            $possibleFiles[] = "whatsapp_521{$last10}.json";
        }

        $hasSession = false;
        foreach ($possibleFiles as $filename) {
            if (isset($sessionFiles[$filename])) {
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
?>
<?php
// Obtener vendedores (tipoUsu = 0 o 1) para los selects de asignación
$vendedores = [];
$resV = $conn->query("SELECT id, nombre, apepat, enlace_meet FROM usuarios WHERE tipoUsu IN (0,1) ORDER BY nombre, apepat");
if ($resV && $resV->num_rows > 0) {
    while ($r = $resV->fetch_assoc()) {
        $vendedores[] = $r;
    }
}
$vendedoresById = [];
foreach ($vendedores as $v) {
    $vendedoresById[intval($v['id'])] = $v;
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

function getVendedorAsignadoLabel($usuarioAsignado, array $vendedoresById)
{
    global $conn;

    $assignedId = intval($usuarioAsignado);
    if ($assignedId === 99) {
        return 'Agente de llamada (IA)';
    }
    if ($assignedId === 100) {
        return 'Agente de whatsapp (IA)';
    }
    if ($assignedId > 0 && isset($vendedoresById[$assignedId])) {
        $v = $vendedoresById[$assignedId];
        return trim(($v['nombre'] ?? '') . ' ' . ($v['apepat'] ?? ''));
    }
    if ($assignedId > 0 && isset($conn) && function_exists('wpEventosCfResolveAsesorDisplayName')) {
        $label = wpEventosCfResolveAsesorDisplayName($conn, $assignedId);
        if ($label !== '') {
            return $label;
        }
    }
    return '—';
}

// Obtener solo agentes (tipoUsu = 1) para asignación obligatoria en el modal Wedding Planner
$agentes = [];
$resA = $conn->query("SELECT id, nombre, apepat FROM usuarios WHERE tipoUsu = 1 ORDER BY nombre, apepat");
if ($resA && $resA->num_rows > 0) {
    while ($ra = $resA->fetch_assoc()) {
        $agentes[] = $ra;
    }
}

$reportRangeLabel = 'Todos los registros';
if (($startDate !== '' || $endDate !== '') && empty($_GET['show_all'])) {
    $reportRangeStart = $startDate !== '' ? date('d/m/Y', strtotime($startDate)) : '...';
    $reportRangeEnd = $endDate !== '' ? date('d/m/Y', strtotime($endDate)) : '...';
    $reportRangeLabel = $reportRangeStart . ' → ' . $reportRangeEnd;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre qualified leads</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/heatmap.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
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
            background-color: var(--slate-50);
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

        #verMasModal p {
            font-size: 1.15rem;
        }

        table {
            width: 100% !important;
        }

        /* Columna de asignación más ancha para mejor visibilidad */
        .assign-col {
            width: 220px;
            min-width: 160px;
        }

        /* Select de asignación oculto (se mantiene en DOM para la lógica existente) */
        th.assign-col-hidden,
        td[data-column="asignado"],
        th[data-column="fecha_registro"],
        td[data-column="fecha_registro"] {
            display: none !important;
        }

        td[data-column="estatus"] .td-sub-fecha-registro {
            margin-top: 4px;
            font-size: 10px;
            line-height: 1.2;
            white-space: normal;
            max-width: 140px;
            margin-left: auto;
            margin-right: auto;
        }

        th[data-column="vendedor"],
        td[data-column="vendedor"] {
            min-width: 140px;
            white-space: nowrap;
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

        /* Mantener columnas informativas legibles sin romper el cálculo de DataTables */
        th[data-column="ciudad_origen"],
        td[data-column="ciudad_origen"],
        th[data-column="metodo_contacto"],
        td[data-column="metodo_contacto"],
        th[data-column="tipo_cliente"],
        td[data-column="tipo_cliente"],
        th[data-column="campana"],
        td[data-column="campana"],
        th[data-column="desde_conoce"],
        td[data-column="desde_conoce"] {
            min-width: 128px;
        }

        th[data-column="ciudad_origen"],
        th[data-column="metodo_contacto"],
        th[data-column="tipo_cliente"],
        th[data-column="desde_conoce"] {
            white-space: normal !important;
            line-height: 1.2;
            font-size: 9px !important;
            letter-spacing: 0.2px;
            word-break: break-word;
            overflow-wrap: anywhere;
            hyphens: auto;
        }

        .efege-table thead th .th-compact-label {
            display: block;
            max-width: 140px;
            white-space: normal !important;
            word-break: break-word;
            overflow-wrap: anywhere;
            line-height: 1.15;
        }

        .th-flex-label {
            display: flex;
            align-items: flex-start;
            gap: 3px;
        }
        .th-flex-label .th-compact-label {
            flex: 1;
        }
        .th-flex-label .filter-icon {
            flex-shrink: 0;
            margin-top: 1px;
        }

        td[data-column="ciudad_origen"],
        td[data-column="campana"],
        td[data-column="metodo_contacto"],
        td[data-column="tipo_cliente"],
        td[data-column="desde_conoce"] {
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
        }

        /* Modal section headers */
        .modal-section-header {
            margin-bottom: .45rem;
            display: block;
        }

        .modal-section-title {
            font-size: 1rem;
            margin: 0 0 .15rem 0;
            font-weight: 700;
        }

        .modal-section-subtitle {
            font-size: .85rem;
            color: #6c757d;
            margin-bottom: .65rem;
        }
    
/* NEW EFEGE REPORT DASHBOARD STYLES (Light Mode Adapted) */
:root {
  --gold: #C5A028;
  --gold-dim: rgba(197,160,40,0.12);
  --dark: #F8FAFC;
  --panel: #FFFFFF;
  --surface: #F1F5F9;
  --ink: #1E293B;
  --muted: #64748B;
  --border: #E2E8F0;
  --white: #FFFFFF;

  --planner: #3B82F6;
  --planner-bg: rgba(59,130,246,0.12);
  --community: #10B981;
  --community-bg: rgba(16,185,129,0.12);
  --newmarket: #A855F7;
  --newmarket-bg: rgba(168,85,247,0.12);

  --eng-low: #F59E0B;
  --eng-mid: #3B82F6;
  --eng-high: #10B981;

  --radius: 12px;
}

.kpi-strip {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
  margin-bottom: 24px;
}
@media (max-width: 900px) {
  .kpi-strip { grid-template-columns: repeat(3, 1fr); }
}

.kpi-card {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 18px 20px;
}

.kpi-label {
  font-size: 10px;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 8px;
}

.kpi-value {
  font-size: 28px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1;
  margin-bottom: 6px;
}

.kpi-sub {
  font-size: 11px;
  color: var(--muted);
}
.kpi-sub .up { color: var(--community); }
.kpi-sub .tag { 
  display: inline-block;
  padding: 1px 7px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 600;
}

.filter-bar {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px 20px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  position: sticky;
  top: 20px;
  z-index: 100;
}

.reports-section {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 16px;
}

.reports-section-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.reports-section-step {
    font-size: 11px;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    color: var(--gold);
    font-weight: 700;
    margin-bottom: 6px;
}

.reports-section-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--ink);
    margin: 0 0 4px;
}

.reports-section-subtitle {
    font-size: 12px;
    color: var(--muted);
    margin: 0;
}

.reports-range-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 999px;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--muted);
    font-size: 12px;
}

.reports-grid {
    display: grid;
    grid-template-columns: minmax(260px, 1.4fr) repeat(2, minmax(180px, 1fr));
    gap: 12px;
}

.reports-layout {
    display: grid;
    grid-template-columns: minmax(160px, 0.4fr) 1fr;
    gap: 16px;
    align-items: start;
}

.reports-kpi-stack {
    display: grid;
    gap: 12px;
}

.reports-charts-row {
    display: flex;
    gap: 12px;
}

.reports-charts-row .report-chart-card {
    flex: 1 1 0;
    min-width: 0;
}

.reports-kpi-grid-secondary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.report-card {
    background: var(--slate-50);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px;
}

.report-card-highlight {
    background: linear-gradient(135deg, rgba(197,160,40,0.10), rgba(197,160,40,0.04));
    border-color: rgba(197,160,40,0.22);
}

.report-card-label {
    font-size: 11px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 10px;
}

.report-card-value {
    font-size: 30px;
    line-height: 1;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 8px;
}

.report-card-note {
    font-size: 12px;
    color: var(--muted);
}

.report-formula {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px dashed var(--border);
    font-size: 12px;
    color: var(--slate-700);
}

.report-formula strong {
    color: var(--ink);
}

.report-chart-card {
    background: var(--slate-50);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px;
}

.report-chart-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
}

.report-chart-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
    margin: 0 0 4px;
}

.report-chart-subtitle {
    font-size: 12px;
    color: var(--muted);
    margin: 0;
}

.report-chart {
    width: 100%;
    min-height: 240px;
}

.report-overview-grid {
    display: grid;
    gap: 16px;
}

.report-chart-card-full {
    width: 100%;
}

.report-metric-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.report-breakdown-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.report-breakdown-card {
    background: var(--slate-50);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px;
}

.report-breakdown-stack {
    display: grid;
    gap: 10px;
}

.report-breakdown-list {
    display: grid;
    gap: 10px;
    margin-top: 14px;
}

.report-breakdown-item {
    display: grid;
    gap: 8px;
}

.report-breakdown-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.report-breakdown-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
    min-width: 0;
}

.report-breakdown-meta {
    flex-shrink: 0;
    font-size: 12px;
    color: var(--muted);
    white-space: nowrap;
}

.report-breakdown-bar {
    width: 100%;
    height: 8px;
    border-radius: 999px;
    background: var(--surface);
    overflow: hidden;
}

.report-breakdown-fill {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, rgba(37,99,235,0.85), rgba(197,160,40,0.7));
}

.report-breakdown-empty {
    margin: 0;
    font-size: 12px;
    color: var(--muted);
}

.report-insight {
    background: linear-gradient(135deg, rgba(197,160,40,0.10), rgba(37,99,235,0.05));
    border: 1px solid rgba(197,160,40,0.22);
    border-radius: 14px;
    padding: 18px;
}

.report-insight-kicker {
    font-size: 11px;
    letter-spacing: 1.1px;
    text-transform: uppercase;
    color: var(--gold);
    font-weight: 700;
    margin-bottom: 8px;
}

.report-insight-text {
    margin: 0;
    font-size: 18px;
    line-height: 1.4;
    color: var(--ink);
    font-weight: 600;
}

.report-insight-text span {
    color: var(--gold);
}

.report-insight-detail {
    margin: 8px 0 0;
    font-size: 12px;
    color: var(--muted);
}

.report-origin-pending {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 12px;
    background: #fdf0dc;
    border: 1px solid rgba(138,92,30,.12);
    color: #8a5c1e;
    font-size: 12px;
}

.report-origin-pending strong {
    font-weight: 700;
}

@media (max-width: 900px) {
    .reports-layout,
    .reports-grid,
    .reports-kpi-grid-secondary,
    .report-metric-grid,
    .report-breakdown-grid {
        grid-template-columns: 1fr;
    }
    .reports-charts-row {
        flex-direction: column;
    }
}

.filter-label {
  font-size: 11px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--muted);
  margin-right: 4px;
  flex-shrink: 0;
}

.table-wrap {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
}

.table-header-custom {
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
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

.badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}

.badge-planner { background: var(--planner-bg); color: var(--planner); }
.badge-community { background: var(--community-bg); color: var(--community); }
.badge-newmarket { background: var(--newmarket-bg); color: var(--newmarket); }
.badge-neutral { background: var(--surface); color: var(--muted); }
.badge-method {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--muted);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: .04em;
}

.badge-campaign,
.badge-contact-method,
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

.badge-dot {
  width: 5px; height: 5px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}

.ch-badge {
  display: inline-flex;
  align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--surface);
    border: 1px solid var(--border);
    font-size: 10px;
    font-weight: 600;
    color: var(--muted);
}

.eng {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
}
.eng-low { background: rgba(245,158,11,0.12); color: var(--eng-low); }
.eng-mid { background: rgba(59,130,246,0.12); color: var(--eng-mid); }
.eng-high { background: rgba(16,185,129,0.12); color: var(--eng-high); }

.known {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  background: rgba(197,160,40,0.1);
  color: var(--gold);
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
.td-sub { font-size: 11px; color: var(--muted);  }
.td-inline-muted {
    font-size: 11px;
    color: var(--muted);
}
.td-emphasis {
    font-weight: 600;
    color: var(--ink);
}

.action-stack {
    min-width: 140px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.action-stack .btn,
.action-stack .badge,
.action-stack a.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: auto;
    margin-right: 0 !important;
    margin-bottom: 0 !important;
    white-space: normal;
    text-align: center;
}

.action-stack .btn,
.action-stack .badge,
.action-stack a.btn {
    flex-shrink: 0;
}

.lead-session-tabs .nav-link {
    font-size: 0.95rem;
    color: #64748b;
}

.lead-session-tabs .nav-link.active {
    color: #8a4a0f;
    font-weight: 600;
}

.lead-session-preview {
    white-space: pre-wrap;
    text-align: left;
    font-size: 0.92rem;
    line-height: 1.65;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px;
    max-height: 340px;
    overflow-y: auto;
    color: #1e293b;
}

.lead-session-copy-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.lead-session-copy-btn {
    background: #8a4a0f;
    border-color: #8a4a0f;
    color: #fff;
}

.lead-session-copy-btn:hover {
    background: #6d3a0c;
    border-color: #6d3a0c;
    color: #fff;
}

.lead-session-copy-btn-en {
    background: #5e543f;
    border-color: #5e543f;
}

.lead-session-copy-btn-en:hover {
    background: #4a4332;
    border-color: #4a4332;
}

/* ── EFEGE PAGE LAYOUT ── */
body { background: var(--dark, #F8FAFC) !important; }
.efege-page {
  padding: 0 20px 60px;
  font-family: 'DM Sans', system-ui, sans-serif;
}
.efege-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding: 20px 0 16px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--border);
}
.efege-page-header-left { flex: 1; min-width: 0; }
.efege-page-header-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.efege-page-title {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: 26px;
  font-weight: 600;
  letter-spacing: 2px;
  color: var(--ink);
  line-height: 1;
  margin-bottom: 4px;
}
.efege-title-accent { color: var(--gold); }
.efege-page-title-sub {
  font-size: 14px;
  letter-spacing: 1px;
  font-weight: 400;
  color: var(--muted);
  font-family: 'DM Sans', system-ui, sans-serif;
}
.efege-page-subtitle { font-size: 12px; color: var(--muted); margin-top: 4px; }
.efege-date-range {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 6px 12px;
  font-size: 12px;
  color: var(--muted);
}
.efege-date-range span { color: var(--ink); font-weight: 600; }
.efege-live-badge {
  font-size: 11px;
  color: var(--muted);
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 6px 12px;
}
/* ── EFEGE FILTER CONTROLS ── */
.efege-filter-input,
.efege-filter-select {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 7px;
  color: var(--ink);
  font-family: 'DM Sans', system-ui, sans-serif;
  font-size: 12px;
  padding: 7px 12px;
  outline: none;
  transition: border-color 0.2s;
  appearance: none;
  -webkit-appearance: none;
}
.efege-filter-select {
  padding-right: 28px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748B' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  cursor: pointer;
}
.efege-filter-input:focus,
.efege-filter-select:focus { border-color: var(--gold); }
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
/* ── EFEGE BUTTONS ── */
.efege-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 7px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  font-family: 'DM Sans', system-ui, sans-serif;
  white-space: nowrap;
}
.efege-btn:hover { border-color: var(--gold); color: var(--gold); text-decoration: none; }
.efege-btn-primary { background: var(--gold); border-color: var(--gold); color: #fff; }
.efege-btn-primary:hover { background: #b8921f; border-color: #b8921f; color: #fff; }
/* ── EFEGE TABLE ── */
/* Scroll horizontal lo maneja DataTables; vertical lo manejamos con CSS */
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
.efege-table { font-size: 13px; font-family: 'DM Sans', system-ui, sans-serif; }
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
  /* Sticky header via CSS: no necesita scrollY de DataTables */
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
.efege-table.table-striped tbody tr:nth-of-type(odd) { background-color: rgba(248,250,252,0.5); }
/* Status pill overrides for EFEGE */
.status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.status.status-pending { background: rgba(245,158,11,0.12); color: var(--eng-low); }
.status.status-scheduled { background: rgba(59,130,246,0.12); color: var(--eng-mid); }
.status.status-attended { background: rgba(16,185,129,0.12); color: var(--community); }
.status.status-closed { background: rgba(197,160,40,0.15); color: var(--gold); }

/* ===== REGISTRAR LEAD MANUAL MODAL (sfSessionBody style) ===== */
.rlm-modal .modal-content { border-radius: 14px; overflow: hidden; box-shadow: 0 24px 80px rgba(0,0,0,0.35); border: none; }
.rlm-modal .modal-header { background: #eee8dc; border-bottom: none; padding: 22px 28px; display: flex; align-items: flex-start; justify-content: space-between; }
.rlm-modal .modal-header h5 { font-size: 1.35rem; font-weight: 700; color: #464646; margin: 0 0 4px; }
.rlm-modal .modal-header .rlm-subtitle { font-size: 0.8rem; color: #464646; opacity: 0.72; margin: 0; }
.rlm-modal .modal-body { padding: 28px 32px; overflow-y: auto; max-height: 60vh; }
.rlm-section { margin-bottom: 28px; }
.rlm-section-title { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 14px; }
.rlm-section-number { width: 32px; height: 32px; background: #464646; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; flex-shrink: 0; margin-top: 2px; }
.rlm-section-heading h5 { margin: 0; font-weight: 700; font-size: 1rem; color: #464646; }
.rlm-section-heading p { margin: 2px 0 0; font-size: 0.8rem; color: #777; }
.rlm-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.rlm-phone-row { display: grid; grid-template-columns: 1fr 2fr; gap: 8px; }
.rlm-field { margin-bottom: 14px; }
.rlm-field label { font-size: 0.8rem; font-weight: 600; color: #555; margin-bottom: 4px; display: block; }
.rlm-tag { display: inline-block; background: #464646; color: #eee8dc; font-size: 0.7rem; font-weight: 600; padding: 1px 6px; border-radius: 4px; margin-left: 6px; }
.rlm-optional { font-size: 0.7rem; color: #999; margin-left: 6px; }
.rlm-field input[type="text"],
.rlm-field input[type="email"],
.rlm-field input[type="datetime-local"],
.rlm-field input[type="date"],
.rlm-field select,
.rlm-field textarea { width: 100%; border: 1px solid #ddd; border-radius: 8px; padding: 9px 12px; font-size: 0.9rem; color: #333; outline: none; transition: border-color 0.2s; background: #fff; }
.rlm-field textarea { min-height: 96px; resize: vertical; line-height: 1.45; }
.rlm-field input:focus, .rlm-field select:focus, .rlm-field textarea:focus { border-color: #464646; }
.rlm-field input.rlm-input-error, .rlm-field select.rlm-input-error { border-color: #e53935 !important; background-color: #fff5f5; }
.rlm-checkbox-label { display: flex; align-items: center; gap: 8px; margin-top: 8px; font-size: 0.85rem; font-weight: 500; color: #555; cursor: pointer; }
.rlm-checkbox-label input[type="checkbox"] { width: auto; margin: 0; cursor: pointer; accent-color: #464646; }
.rlm-field input[type="date"]:disabled { background: #f5f5f5; color: #999; cursor: not-allowed; }
.rlm-choice-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 8px; }
#leadHowLongKnownUsGroup .rlm-choice-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.rlm-choice-card {
    border: 1px solid #ddd;
    border-radius: 12px;
    background: #fff;
    padding: 16px 14px;
    min-height: 96px;
    cursor: pointer;
    text-align: left;
    transition: all 0.18s ease;
    box-shadow: 0 1px 2px rgba(15,23,42,0.04);
}
.rlm-choice-card:hover:not(:disabled) { border-color: #464646; transform: translateY(-1px); box-shadow: 0 8px 20px rgba(15,23,42,0.08); }
.rlm-choice-card.active { border-color: #464646; background: #f5f1e8; box-shadow: 0 10px 24px rgba(70,70,70,0.12); }
.rlm-choice-card:disabled { opacity: 0.45; cursor: not-allowed; background: #f5f5f5; box-shadow: none; transform: none; }
.rlm-choice-card-title { display: block; font-size: 0.95rem; font-weight: 700; color: #464646; margin-bottom: 6px; }
.rlm-choice-card-desc { display: block; font-size: 0.8rem; line-height: 1.45; color: #777; }
.rlm-choice-group.is-invalid .rlm-choice-card { border-color: #e7b7b5; background: #fff8f7; }
.rlm-choice-group.is-invalid .rlm-choice-card.active { border-color: #e53935; background: #fff1f0; }
.rlm-error-msg { color: #e53935; font-size: 0.75rem; margin-top: 4px; display: none; }
.rlm-modal .modal-footer { display: flex; gap: 12px; padding: 20px 32px 28px; border-top: 1px solid #eee; background: #fff; }
.rlm-modal .modal-footer .rlm-btn-cancel { flex: 1; padding: 12px; border: 1.5px solid #ddd; border-radius: 10px; background: #fff; color: #555; font-weight: 600; cursor: pointer; font-size: 0.9rem; transition: all 0.15s; }
.rlm-modal .modal-footer .rlm-btn-cancel:hover { background: #f5f5f5; }
.rlm-modal .modal-footer .rlm-btn-submit { flex: 3; padding: 12px; border: none; border-radius: 10px; background: #eee8dc; color: #464646; font-weight: 700; cursor: pointer; font-size: 0.9rem; transition: background 0.15s; }
.rlm-modal .modal-footer .rlm-btn-submit:hover { background: #464646; color: #eee8dc; }
@media (max-width: 600px) {
    .rlm-modal .modal-body { padding: 20px 16px; }
    .rlm-modal .modal-footer { padding: 16px; }
    .rlm-modal .modal-header { padding: 18px 16px; }
    .rlm-grid-2, .rlm-phone-row { grid-template-columns: 1fr; }
        .rlm-choice-grid { grid-template-columns: 1fr; }
    #leadHowLongKnownUsGroup .rlm-choice-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

</style>
</head>

<body>
<div class="efege-page">

    <!-- PAGE HEADER -->
    <div class="efege-page-header">
        <div class="efege-page-header-left">
            <div class="efege-page-title"><span class="efege-page-title-sub">Pre-Qualified Leads</span></div>
            <div class="efege-page-subtitle">Leads pre-calificados · Plataforma: <strong><?php echo htmlspecialchars($platformLabel); ?></strong></div>
        </div>
        <div class="efege-page-header-right">
            <?php if ($startDate && $endDate): ?>
            <div class="efege-date-range">📅 <span><?php echo date('d M Y', strtotime($startDate)); ?></span>&nbsp;→&nbsp;<span><?php echo date('d M Y', strtotime($endDate)); ?></span></div>
            <?php endif; ?>
            <div class="efege-live-badge">🔴 Live</div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <br><form method="get" class="filter-bar" id="filterForm">
        <span class="filter-label">Filtrar</span>
        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
        <input type="date" name="start_date" class="efege-filter-input"
            value="<?php echo htmlspecialchars($startDate ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Desde">
        <input type="date" name="end_date" class="efege-filter-input"
            value="<?php echo htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Hasta">
        <!-- <select name="filter_plataforma" id="filterPlataforma" class="efege-filter-select">
            <option value="">Todas las plataformas</option>
            <option value="campania" <?php echo ($filterPlataforma === 'campania') ? 'selected' : ''; ?>>Campañas Digitales</option>
            <option value="organico" <?php echo ($filterPlataforma === 'organico') ? 'selected' : ''; ?>>Orgánico</option>
        </select> -->
      <button type="submit" class="efege-btn efege-btn-primary" style="background:#0f172a; border-color:#0f172a; color:#fff;">
    <i class="fas fa-filter"></i> Filtrar </button>
        <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>?show_all=1" class="efege-btn">Limpiar</a>
        <button type="button" class="efege-btn" data-bs-toggle="modal" data-bs-target="#registroManualModal" style="margin-left:auto;"><i class="bi bi-person-plus"></i> Registrar Lead</button>
    </form>

    <section class="reports-section">
        <div class="reports-section-header">
            <div>
                <div class="reports-section-step">2. Apartado de Reportes</div>
                <h2 class="reports-section-title">Reporte de tasa de calificación</h2>
                <p class="reports-section-subtitle">Usa el filtro por rango de fechas de arriba para actualizar este reporte.</p>
            </div>
            <div class="reports-range-chip">
                <i class="bi bi-calendar3"></i>
                <span id="report-period-text"><?php echo htmlspecialchars($reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <div class="report-overview-grid">
            <article class="report-chart-card report-chart-card-full">
                <div class="report-chart-header">
                    <div>
                        <h3 class="report-chart-title">Conteo total de registros por periodo</h3>
                        <p class="report-chart-subtitle">Se muestra el total de leads por día dentro del rango seleccionado.</p>
                    </div>
                </div>
                <div id="leadsChart" class="report-chart"></div>
            </article>

            <div class="report-metric-grid">
                <article class="report-card">
                    <div class="report-card-label">Total de leads</div>
                    <div class="report-card-value" id="report-total-registros"><?php echo number_format($totalCount); ?></div>
                    <div class="report-card-note">Leads incluidos en el rango seleccionado.</div>
                </article>

                <article class="report-card report-card-highlight">
                    <div class="report-card-label">Personas que agendaron</div>
                    <div class="report-card-value" id="report-agendaron-count"><?php echo number_format($leadsAgendadosTotal); ?></div>
                    <div class="report-card-note" id="report-agendaron-note"><?php echo formatPercentage($leadsAgendadosTotal, $totalCount); ?> del total avanzó a cita.</div>
                </article>

                <article class="report-card">
                    <div class="report-card-label">Pendientes</div>
                    <div class="report-card-value" id="report-pending-count"><?php echo number_format($pendingLeadCount); ?></div>
                    <div class="report-card-note" id="report-pending-note"><?php echo formatPercentage($pendingLeadCount, $totalCount); ?> sin Origen del Cliente capturado.</div>
                </article>

                <article class="report-card">
                    <div class="report-card-label">Wedding Planner Afianzados</div>
                    <div class="report-card-value" id="report-wp-afianzados-count"><?php echo number_format($wpAfianzadosCount); ?></div>
                    <div class="report-card-note" id="report-wp-afianzados-note">Total de eventos de todos los wedding planners afianzados.</div>
                </article>
            </div>

            <div class="report-breakdown-grid">
                <article class="report-breakdown-card">
                    <div class="report-card-label">Método de Contacto Inicial</div>
                    <div class="report-breakdown-list" id="report-contact-breakdown">
                        <?php if (!empty($contactMethodCounts)): ?>
                            <?php foreach ($contactMethodCounts as $label => $count): ?>
                                <?php $contactDisplayLabel = ($label === 'Sin dato') ? 'Not asked' : $label; ?>
                                <div class="report-breakdown-item">
                                    <div class="report-breakdown-row">
                                        <div class="report-breakdown-label"><?php echo htmlspecialchars($contactDisplayLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="report-breakdown-meta"><?php echo number_format($count); ?> · <?php echo formatPercentage($count, $totalCount); ?></div>
                                    </div>
                                    <div class="report-breakdown-bar"><span class="report-breakdown-fill" style="width: <?php echo htmlspecialchars(formatPercentage($count, $totalCount, 2), ENT_QUOTES, 'UTF-8'); ?>;"></span></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="report-breakdown-empty">Sin datos para este periodo.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="report-breakdown-card">
                    <div class="report-card-label">Origen del Cliente</div>
                    <div class="report-breakdown-list" id="report-origin-breakdown">
                        <?php if (!empty($originClientCounts)): ?>
                            <?php foreach ($originClientCounts as $label => $count): ?>
                                <?php $originDisplayLabel = ($label === 'N/A') ? 'Origen por confirmar' : $label; ?>
                                <div class="report-breakdown-item">
                                    <div class="report-breakdown-row">
                                        <div class="report-breakdown-label"><?php echo htmlspecialchars($originDisplayLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="report-breakdown-meta"><?php echo number_format($count); ?> · <?php echo formatPercentage($count, $totalCount); ?></div>
                                    </div>
                                    <div class="report-breakdown-bar"><span class="report-breakdown-fill" style="width: <?php echo htmlspecialchars(formatPercentage($count, $totalCount, 2), ENT_QUOTES, 'UTF-8'); ?>;"></span></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="report-breakdown-empty">Sin datos para este periodo.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="report-breakdown-card">
                    <div class="report-card-label">Desde cuándo nos conoce</div>
                    <div class="report-breakdown-list" id="report-known-us-breakdown">
                        <?php if (!empty($knownUsCounts)): ?>
                            <?php foreach ($knownUsCounts as $label => $count): ?>
                                <div class="report-breakdown-item">
                                    <div class="report-breakdown-row">
                                        <div class="report-breakdown-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="report-breakdown-meta"><?php echo number_format($count); ?> · <?php echo formatPercentage($count, $totalCount); ?></div>
                                    </div>
                                    <div class="report-breakdown-bar"><span class="report-breakdown-fill" style="width: <?php echo htmlspecialchars(formatPercentage($count, $totalCount, 2), ENT_QUOTES, 'UTF-8'); ?>;"></span></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="report-breakdown-empty">Sin datos para este periodo.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="report-breakdown-card">
                    <div class="report-card-label">Campañas</div>
                    <div class="report-breakdown-list" id="report-campaign-breakdown">
                        <?php if (!empty($campaignDisplayCounts)): ?>
                            <?php foreach ($campaignDisplayCounts as $label => $count): ?>
                                <div class="report-breakdown-item">
                                    <div class="report-breakdown-row">
                                        <div class="report-breakdown-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="report-breakdown-meta"><?php echo number_format($count); ?> · <?php echo formatPercentage($count, $totalCount); ?></div>
                                    </div>
                                    <div class="report-breakdown-bar"><span class="report-breakdown-fill" style="width: <?php echo htmlspecialchars(formatPercentage($count, $totalCount, 2), ENT_QUOTES, 'UTF-8'); ?>;"></span></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="report-breakdown-empty">Sin datos para este periodo.</p>
                        <?php endif; ?>
                    </div>
                </article>
            </div>

            <div class="report-origin-pending" id="report-origin-pending-note" style="margin-top:8px;">
                <strong><?php echo number_format($pendingLeadCount); ?> leads</strong>
                <span>con origen aún no confirmado — se completará en la llamada de calificación.</span>
            </div>

            <div class="report-insight">
                <div class="report-insight-kicker">Tasa de calificación</div>
                <p class="report-insight-text">→ <span id="report-qualification-rate"><?php echo $tasaConversion; ?>%</span> de estos leads avanzó al siguiente estadio en el período.</p>
                <p class="report-insight-detail" id="report-qualification-detail"><?php echo number_format($leadsAgendadosTotal); ?> personas que agendaron de <?php echo number_format($totalCount); ?> registros</p>
            </div>
        </div>
        
    </section>

    <!-- TABLE WRAP -->
    <div class="table-wrap">
        <div class="table-header-custom">
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="table-title">Pre-Qualified Leads</span>
                <span class="table-count"><?php echo number_format($totalCount); ?> leads&nbsp;·&nbsp;<?php echo number_format($leadsAgendadosTotal); ?> agendados&nbsp;·&nbsp;<?php echo number_format($leadsAtendidosTotal); ?> atendidos</span>
            </div>
            <div style="position:relative;">
                <i style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px;" class="fas fa-search"></i>
                <input type="text" id="leadsSearchInput" class="efege-filter-search" style="padding-left:30px;" placeholder="Buscar nombre, correo…" value="<?php echo htmlspecialchars($searchQuery); ?>" />
            </div>
        </div>
        <div class="efege-table-scroll">
            <table id="leadsTable" class="efege-table table table-hover">
                            <thead>
                                <tr>
                                    <th data-column="nombre">Nombre</th>
                                    <th data-column="fecha_registro">Fecha registro</th>
                                    <th data-column="metodo_contacto"><div class="th-flex-label"><span class="th-compact-label">Método contacto</span><i class="filter-icon bi bi-funnel" data-column="metodo_contacto"></i></div></th>
                                    <th data-column="tipo_cliente"><div class="th-flex-label"><span class="th-compact-label">Tipo de cliente</span><i class="filter-icon bi bi-funnel" data-column="tipo_cliente"></i></div></th>
                                    <th data-column="origen_cliente"><div class="th-flex-label"><span class="th-compact-label">Origen</span><i class="filter-icon bi bi-funnel" data-column="origen_cliente"></i></div></th>
                                    <th data-column="campana"><div class="th-flex-label"><span class="th-compact-label">Campaña</span><i class="filter-icon bi bi-funnel" data-column="campana"></i></div></th>
                                    <th class="text-center" data-column="estatus">Estatus <i
                                            class="filter-icon bi bi-funnel" data-column="estatus"></i></th>
                                    <th data-column="ciudad_origen"><span class="th-compact-label">Ciudad novios</span></th>
                                    <th data-column="boda">Destino boda</th>
                                    <th data-column="cuando_se_casa">Fecha boda</th>
                                    <th data-column="desde_conoce"><div class="th-flex-label"><span class="th-compact-label">Nos conocen hace</span><i class="filter-icon bi bi-funnel" data-column="desde_conoce"></i></div></th>
                                    <?php if ($tipoUsuario != "4"): ?>
                                        <th data-column="vendedor">Vendedor</th>
                                        <th class="assign-col assign-col-hidden" data-column="asignado">Vendedor</th>
                                        <th class="text-center">Llamada</th>
                                        <th class="text-center">WhatsApp IA</th>
                                        <th>Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredLeads as $lead): ?>
                                    <?php // Propagar bandera de agendado/scheduled para uso en el modal (JS, JSON) ?>
                                    <?php $rowScheduledKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']);
                                    $lead['scheduled'] = isset($scheduledLeadsMap[$rowScheduledKey]) ? 1 : 0; ?>
                                    <?php // Determinar estatus del lead usando los mapas calculados anteriormente ?>
                                    <?php $rowStatusKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']);
                                    $lead['estatus'] = isset($leadStatusMap[$rowStatusKey]) ? $leadStatusMap[$rowStatusKey] : 'lead';
                                    // Enriquecer solo campos operativos desde contact_form; origen se calcula sobre el lead
                                    if (isset($contactFormByLead[$rowStatusKey])) {
                                        $cfExtra = $contactFormByLead[$rowStatusKey];
                                        foreach (['engagement', 'first_contact_channel'] as $_cfField) {
                                            if (empty($lead[$_cfField]) && !empty($cfExtra[$_cfField])) {
                                                $lead[$_cfField] = $cfExtra[$_cfField];
                                            }
                                        }
                                    }

                                    $_leadForTipoCliente = $lead;
                                    $_cfRowForTipoCliente = isset($contactFormByLead[$rowStatusKey]) ? $contactFormByLead[$rowStatusKey] : null;

                                    $_tablaOLower = strtolower((string)($lead['tabla_origen'] ?? ''));
                                    $_fcc         = strtolower(trim($lead['first_contact_channel'] ?? ''));
                                    $_tipoIG      = strtolower(trim($lead['tipo_ig'] ?? ''));
                                    $_campaign    = strtolower(trim($lead['campaign_name'] ?? ''));
                                    $_igOrganico  = ($_tipoIG === 'organico' || $_campaign === 'ig organico');
                                    if (isWpPlannerLeadTable($_tablaOLower)) {
                                        if (trim((string) ($lead['how_did_you_meet'] ?? '')) === '') {
                                            $lead['how_did_you_meet'] = '1';
                                        }
                                    } elseif ($_fcc === 'leadform') {
                                        $lead['how_did_you_meet'] = '3'; // New Audience
                                    } elseif ($_fcc === 'ig') {
                                        if (!$_igOrganico) {
                                            $lead['how_did_you_meet'] = '3';
                                        } else {
                                            $lead['how_did_you_meet'] = '';
                                        }
                                    } elseif (in_array($_fcc, ['website', 'mail', 'email', 'whatsapp'], true)) {
                                        $lead['how_did_you_meet'] = '';
                                    }

                                    applyResolvedHowDidYouMeetToLead(
                                        $lead,
                                        isset($contactFormByLead[$rowStatusKey]) ? $contactFormByLead[$rowStatusKey] : null
                                    );

                                    $tipoClienteLabel = getTipoClienteLabel($lead, $_cfRowForTipoCliente);

                                    $weddingLocationDisplay = firstNonEmptyValue(
                                        $lead['wedding_location'] ?? '',
                                        $weddingLocationsMap[$rowStatusKey] ?? '',
                                        $lead['where_are_you_getting_married_'] ?? '',
                                        $lead['where_is_your_marriage_taking_place_'] ?? ''
                                    );
                                    $weddingDateNotDefined = !empty($lead['wedding_date_not_defined']);
                                    $weddingDateDisplay = firstNonEmptyValue(
                                        $lead['wedding_date'] ?? '',
                                        $weddingDatesMap[$rowStatusKey] ?? '',
                                        $lead['when_are_you_getting_married_'] ?? ''
                                    );
                                    $leadCityDisplay = firstNonEmptyValue(
                                        $lead['city'] ?? '',
                                        $leadCitiesMap[$rowStatusKey] ?? ''
                                    );
                                    $contactMethodDisplay = resolveFirstContactChannelForLead($lead);
                                    $_hlkuDisp = trim((string)($lead['how_long_known_us'] ?? ''));
                                    if ($_hlkuDisp === '' && isset($contactFormByLead[$rowStatusKey]['how_long_known_us'])) {
                                        $_hlkuDisp = trim((string)($contactFormByLead[$rowStatusKey]['how_long_known_us'] ?? ''));
                                    }
                                    $knownUsDisplay = firstNonEmptyValue($_hlkuDisp); ?>
                                    <?php
                                    $contactMethodLabel = normalizeFirstContactChannelLabel($contactMethodDisplay);
                                    $originClientLabel = getDondeNosConocioLabel($lead);
                                    $originCategoryLabel = getOriginCategoryLabel($lead);
                                    $originBadgeClass = getOriginBadgeClass($originCategoryLabel);
                                    $contactChannelBadgeLabel = getContactChannelBadgeLabel($contactMethodLabel);
                                    $knownUsBadgeLabel = getKnownUsBadgeLabel($knownUsDisplay);
                                    $campaignNameValue = trim((string) ($lead['campaign_name'] ?? ''));
                                    $_cfReferrerUrl = isset($contactFormByLead[$rowStatusKey]['referrer_url']) ? $contactFormByLead[$rowStatusKey]['referrer_url'] : '';
                                    $_cfFcc = isset($contactFormByLead[$rowStatusKey]['first_contact_channel']) ? $contactFormByLead[$rowStatusKey]['first_contact_channel'] : ($lead['first_contact_channel'] ?? '');
                                    $campaignDisplayLabel = formatCampaignDisplayLabel($campaignNameValue, $_cfReferrerUrl, $_cfFcc);
                                    $isEventWpAfianzado = (
                                        strcasecmp((string) ($lead['tabla_origen'] ?? ''), 'eventos_wp') === 0 &&
                                        intval($eventWpAfianzadosMap[intval($lead['id'] ?? 0)] ?? 0) === 1
                                    ) ? 1 : 0;
                                    $isManualWP = (isset($lead['tabla_origen']) && $lead['tabla_origen'] === 'wedding_planners');
                                    $nameDisplay = wpEventosCfResolveLeadNameDisplay($lead); ?>
                                    <tr id="lead-row-<?php echo $lead['id']; ?>-<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-lead-id="<?php echo intval($lead['id']); ?>"
                                        data-tabla="<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-full-name="<?php echo htmlspecialchars($nameDisplay['primary'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-when-married="<?php echo htmlspecialchars($lead['when_are_you_getting_married_'] ?? ''); ?>"
                                        data-email="<?php echo htmlspecialchars($lead['email'] ?? ''); ?>"
                                        data-campaign-name="<?php echo htmlspecialchars($campaignNameValue, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-contact-method="<?php echo htmlspecialchars($contactMethodLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-origin-label="<?php echo htmlspecialchars($originClientLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-origin-category="<?php echo htmlspecialchars($originCategoryLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-wp-afianzado="<?php echo $isEventWpAfianzado; ?>"
                                        data-known-us="<?php echo htmlspecialchars($knownUsBadgeLabel, ENT_QUOTES, 'UTF-8'); ?>">
                                        <td data-column="nombre">
                                        <div class="td-name"><?php echo htmlspecialchars($nameDisplay['primary'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if (!empty($nameDisplay['secondary'])): ?>
                                        <div class="td-sub"><?php echo htmlspecialchars($nameDisplay['secondary'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($lead['email'])): ?>
                                        <div class="td-sub"><?php echo htmlspecialchars($lead['email']); ?></div>
                                        <?php endif; ?>
                                        </td>
                                        <td data-column="fecha_registro"><span class="td-inline-muted"><?php echo htmlspecialchars(formatCreatedTime($lead['created_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td data-column="metodo_contacto"><?php echo renderContactMethodBadge($contactMethodLabel, $contactChannelBadgeLabel); ?></td>
                                        <td data-column="tipo_cliente"><?php echo renderTipoClienteBadge($tipoClienteLabel); ?></td>
                                        <td data-column="origen_cliente"><span class="<?php echo htmlspecialchars($originBadgeClass, ENT_QUOTES, 'UTF-8'); ?>"><span class="badge-dot"></span><?php echo htmlspecialchars($originCategoryLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td data-column="campana"><?php echo renderCampaignBadge($campaignDisplayLabel); ?></td>
                                        <?php
                                        // Normalize and prepare display for estatus like in consulta_post_leads.php
                                        $statusRaw = isset($lead['estatus']) ? trim((string) $lead['estatus']) : '';
                                        $statusLower = $statusRaw !== '' ? mb_strtolower($statusRaw, 'UTF-8') : '';
                                        $statusDisplay = $statusLower !== '' ? ucfirst($statusLower) : '';
                                        // Map to EFEGE pill classes
                                        $statusClass = 'status status-pending';
                                        if ($statusLower === 'agendado') {
                                            $statusClass = 'status status-scheduled';
                                        } elseif ($statusLower === 'atendido') {
                                            $statusClass = 'status status-attended';
                                        } elseif ($statusLower === 'cliente') {
                                            $statusClass = 'status status-closed';
                                        } elseif ($statusLower === 'muerto' || $statusLower === 'fantasma') {
                                            $statusClass = 'status status-pending';
                                        }
                                        $fechaRegistroDisplay = formatCreatedTime($lead['created_time'] ?? '');
                                        if ($fechaRegistroDisplay === '') {
                                            $fechaRegistroDisplay = '—';
                                        }
                                        ?>
                                        <td class="text-center align-middle" data-column="estatus">
                                            <span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusDisplay ?: 'Lead'); ?></span>
                                            <div class="td-sub td-sub-fecha-registro"><?php echo htmlspecialchars($fechaRegistroDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                        </td>
                                        <td data-column="ciudad_origen"><span class="td-inline-muted"><?php echo htmlspecialchars($leadCityDisplay !== '' ? $leadCityDisplay : '—', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td data-column="boda"><span class="td-emphasis"><?php echo htmlspecialchars($weddingLocationDisplay !== '' ? $weddingLocationDisplay : '—', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td data-column="cuando_se_casa"><span class="td-inline-muted"><?php echo htmlspecialchars(formatLeadDate($weddingDateDisplay, $weddingDateNotDefined), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td data-column="desde_conoce"><span class="known"><?php echo htmlspecialchars($knownUsBadgeLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <?php if ($tipoUsuario != "4"): ?>
                                            <?php
                                            $cfForVendedor = isset($contactFormByLead[$rowStatusKey]) ? $contactFormByLead[$rowStatusKey] : null;
                                            $cfIdForVendedor = $cfForVendedor ? intval($cfForVendedor['cf_id']) : 0;
                                            $appointmentForVendedor = ($cfIdForVendedor > 0 && isset($appointmentsByCFId[$cfIdForVendedor]))
                                                ? $appointmentsByCFId[$cfIdForVendedor]
                                                : null;
                                            if (!$appointmentForVendedor) {
                                                $calendarioIdForRow = intval($lead['id_calendario'] ?? 0);
                                                if ($calendarioIdForRow > 0 && isset($appointmentsByCalendarioId[$calendarioIdForRow])) {
                                                    $appointmentForVendedor = $appointmentsByCalendarioId[$calendarioIdForRow];
                                                }
                                            }
                                            if (
                                                !$appointmentForVendedor
                                                && wpEventosCfIsWpEventLead($lead)
                                            ) {
                                                $eventIdForVendor = wpEventosCfResolveEventIdFromLead($lead);
                                                if ($eventIdForVendor > 0 && isset($appointmentsByEventId[$eventIdForVendor])) {
                                                    $appointmentForVendedor = $appointmentsByEventId[$eventIdForVendor];
                                                }
                                            }
                                            $assignedUserId = resolveLeadBoardVendedorId($lead, $appointmentForVendedor, $appointmentsByCalendarioId, $conn, $appointmentsByEventId);
                                            $vendedorDisplayLabel = getVendedorAsignadoLabel($assignedUserId, $vendedoresById);
                                            $sessionInfo = buildLeadSessionInfo($lead, $cfForVendedor, $appointmentForVendedor, $assignedUserId, $vendedoresById);
                                            ?>
                                            <td data-column="vendedor"><span class="td-inline-muted"><?php echo htmlspecialchars($vendedorDisplayLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td data-column="asignado">
                                                <select class="form-select-sm form-select assign-select"
                                                    data-lead-id="<?php echo $lead['id']; ?>"
                                                    data-tabla="<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    onchange="asignarUsuario(this)">
                                                    <option value="">-- Asignar --</option>
                                                    <!-- Opción manual agregada para Agente llamada (IA) -->
                                                    <option value="99" <?php echo (isset($lead['usuario_asignado']) && intval($lead['usuario_asignado']) === 99) ? 'selected' : ''; ?>>Agente de
                                                        llamada (IA)</option>
                                                    <!-- Opción manual agregada para Agente whatsapp (IA) -->
                                                    <option value="100" <?php echo (isset($lead['usuario_asignado']) && intval($lead['usuario_asignado']) === 100) ? 'selected' : ''; ?>>Agente de
                                                        whatsapp (IA)</option>
                                                    <?php foreach ($vendedores as $v): ?>
                                                        <option value="<?php echo $v['id']; ?>" <?php echo (isset($lead['usuario_asignado']) && $lead['usuario_asignado'] == $v['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($v['nombre'] . ' ' . $v['apepat']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <!-- Nueva columna: Estado de la llamada (botón grande deshabilitado) -->
                                            <td class="text-center align-middle">
                                                <?php
                                                // Verificar si es organic_leads y ya agendó
                                                $organicScheduledKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']);
                                                $organicScheduled = isset($scheduledLeadsMap[$organicScheduledKey]);
                                                // Propagar bandera de agendado al objeto lead para uso en el modal (JS)
                                                $lead['scheduled'] = $organicScheduled ? 1 : 0;

                                                // Mostrar N/A si no está asignado al agente de llamadas (usuario 99)
                                                $assignedAgent = isset($lead['usuario_asignado']) ? intval($lead['usuario_asignado']) : 0;
                                                $callText = 'N/A';
                                                $callClass = 'btn-secondary';
                                                if ($assignedAgent === 99) {
                                                    $callStatus = isset($lead['estatus_llamada']) ? intval($lead['estatus_llamada']) : 0;
                                                    $callText = 'Llamada pendiente (IA)';
                                                    $callClass = 'btn-secondary';
                                                    if ($callStatus === 1) {
                                                        $callText = 'Llamado (IA)';
                                                        $callClass = 'btn-primary';
                                                    } elseif ($callStatus === 2) {
                                                        $callText = 'Ya agendó (IA)';
                                                        $callClass = 'btn-success';
                                                    } elseif ($callStatus === 3) {
                                                        $callText = 'No agendó (IA)';
                                                        $callClass = 'btn-danger';
                                                    }
                                                }
                                                ?>
                                                <button class="btn <?php echo $callClass; ?> btn-sm d-block w-100" disabled
                                                    title="Estado de llamada: <?php echo htmlspecialchars($callText ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($callText ?? '', ENT_QUOTES, 'UTF-8'); ?></button>
                                            </td>
                                            <td class="text-center align-middle">
                                                <?php
                                                // WhatsApp IA column: mostrar N/A si no está asignado a Agente WhatsApp (100)
                                                $whatsappText = 'N/A';
                                                $whatsappClass = 'btn-secondary';
                                                if ($assignedAgent === 100) {
                                                    $waStatus = isset($lead['estatus_whatsapp']) ? intval($lead['estatus_whatsapp']) : 0;
                                                    if ($waStatus === 0) {
                                                        $whatsappText = 'WhatsApp pendiente (IA)';
                                                        $whatsappClass = 'btn-secondary';
                                                    } elseif ($waStatus === 1) {
                                                        $whatsappText = 'Whatsapp Enviado (IA)';
                                                        $whatsappClass = 'btn-primary';
                                                    } elseif ($waStatus === 2) {
                                                        $whatsappText = 'Ya agendó (IA)';
                                                        $whatsappClass = 'btn-success';
                                                    } elseif ($waStatus === 3) {
                                                        $whatsappText = 'No agendó (IA)';
                                                        $whatsappClass = 'btn-danger';
                                                    } else {
                                                        // Valor desconocido: mostrar pendiente por seguridad
                                                        $whatsappText = 'WhatsApp pendiente (IA)';
                                                        $whatsappClass = 'btn-secondary';
                                                    }
                                                }
                                                ?>
                                                <button class="btn <?php echo $whatsappClass; ?> btn-sm d-block w-100" disabled
                                                    title="Estado de WhatsApp: <?php echo htmlspecialchars($whatsappText ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($whatsappText ?? '', ENT_QUOTES, 'UTF-8'); ?></button>
                                            </td>
                                            <td class="action-stack">
                                                <?php if ($lead['tabla_origen'] === 'organic_leads'): ?>
                                                    <?php if ($organicScheduled): ?>
                                                        <!-- Ya agendó: solo mostrar badge -->
                                                        <span class="bg-success me-2 badge">Ya agendó</span>
                                                    <?php else: ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php
                                                    // Botón de historial WhatsApp si tiene conversación (archivo de sesión existe)
                                                    /*
                                                    $leadKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);
                                                    $hasWhatsappConversation = isset($whatsappSessionsMap[$leadKey]);

                                                    if ($hasWhatsappConversation):
                                                        ?>
                                                        <button class="mb-2 btn btn-info btn-sm wa-conversation-btn"
                                                            data-wa-conversation="1" data-lead-id="<?php echo (int) $lead['id']; ?>"
                                                            data-tabla="<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            title="Ver Conversación de WhatsApp"
                                                            onclick='verHistorialWhatsApp(<?php echo htmlspecialchars(json_encode($lead['id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($lead['tabla_origen'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                            <i class="bi bi-whatsapp"></i> <br>
                                                            Ver Conversación
                                                            <span class="bg-danger ms-2 rounded-pill badge d-none wa-unread-badge"
                                                                title="Mensaje nuevo / requiere atención" aria-hidden="true"></span>
                                                        </button>
                                                    <?php endif;
                                                    */
                                                    ?>
                                                    <?php if ($lead['tabla_origen'] === 'wedding_planners'): ?>
                                                        <?php if (isset($lead['scheduled']) && $lead['scheduled'] == 1): ?>
                                                            <button class="mb-2 btn btn-success btn-sm" disabled
                                                                style="margin-right:5px;">Ya agendó</button>
                                                        <?php else: ?>
                                                            <button class="mb-2 btn btn-warning btn-sm wp-agendar-btn" title="Agendar"
                                                                style="margin-right:5px;" data-wp-id="<?php echo (int) $lead['id']; ?>"
                                                                data-tabla="<?php echo htmlspecialchars($lead['tabla_origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-id-vendedor="<?php echo intval($lead['id_vendedor_asignado'] ?? $lead['usuario_asignado'] ?? 0); ?>">
                                                                <i class="bi bi-calendar-plus"></i> Agendar
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php
                                                $traceParams = [];
                                                $traceTabla = trim((string) ($lead['tabla_origen'] ?? ''));
                                                $traceOrigId = (int) ($lead['id'] ?? 0);
                                                if ($traceTabla !== '' && $traceOrigId > 0) {
                                                    $traceParams['tabla'] = $traceTabla;
                                                    $traceParams['orig_id'] = $traceOrigId;
                                                }
                                                $cfForTrace = isset($contactFormByLead[$rowStatusKey]) ? $contactFormByLead[$rowStatusKey] : null;
                                                $cfIdForTrace = $cfForTrace ? (int) ($cfForTrace['cf_id'] ?? 0) : 0;
                                                if ($cfIdForTrace > 0) {
                                                    $traceParams['cf_id'] = $cfIdForTrace;
                                                }
                                                $traceUrl = 'consulta_post_leads_trazabilidad.php?' . http_build_query($traceParams);
                                                ?>
                                                <a href="<?php echo htmlspecialchars($traceUrl, ENT_QUOTES, 'UTF-8'); ?>" class="efege-btn" style="text-decoration:none;" title="Trazabilidad">
                                                    <i class="fas fa-message"></i>
                                                </a>
                                                <?php if (!empty($sessionInfo)): ?>
                                                    <button type="button" class="mb-2 btn btn-outline-success btn-sm lead-session-btn"
                                                        title="Ver datos de la sesión"
                                                        style="margin-right: 5px;"
                                                        onclick='verDatosSesion(<?php echo htmlspecialchars(json_encode($sessionInfo, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                        <i class="bi bi-calendar-check"></i> Sesión
                                                    </button>
                                                <?php endif; ?>
                                                <button class="mb-2 btn btn-info btn-sm" title="Ver Más"
                                                    style="background-color: #5e543f; color: white; border: 1px solid #5e543f; margin-right: 5px;"
                                                    onclick='verMas(<?php echo htmlspecialchars(json_encode($lead, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)'><i
                                                        class="bi bi-plus"></i></button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
        </div><!-- /efege-table-scroll -->
    </div><!-- /table-wrap -->
</div><!-- /efege-page -->

    <!-- Modal para seleccionar asesor al agendar WP -->
    <div class="modal fade" id="wpAgendarModal" tabindex="-1" aria-labelledby="wpAgendarModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="wpAgendarModalLabel">Seleccionar asesor para agendar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formWpAgendar">
                        <input type="hidden" id="wpAgendarId" name="wedding_planner_id" value="">
                        <div class="mb-3">
                            <label for="wpAgendarAsesor" class="form-label">Asesor <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="wpAgendarAsesor" name="id_vendedor_asignado" required>
                                <option value="">Seleccionar asesor</option>
                                <?php foreach ($vendedores as $v): ?>
                                    <option value="<?php echo intval($v['id']); ?>">
                                        <?php echo htmlspecialchars($v['nombre'] . ' ' . $v['apepat']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">El asesor es obligatorio</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmAgendarWp"><i class="bi bi-save"></i>
                        Agendar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="leadSessionModal" tabindex="-1" aria-labelledby="leadSessionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leadSessionModalLabel">Datos de la sesión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs lead-session-tabs" id="leadSessionTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="lead-session-es-tab" data-bs-toggle="tab" data-bs-target="#lead-session-es" type="button" role="tab">Español</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="lead-session-en-tab" data-bs-toggle="tab" data-bs-target="#lead-session-en" type="button" role="tab">English</button>
                        </li>
                    </ul>
                    <div class="tab-content pt-3" id="leadSessionTabContent">
                        <div class="tab-pane fade show active" id="lead-session-es" role="tabpanel">
                            <div id="leadSessionPreviewEs" class="lead-session-preview"></div>
                            <div class="lead-session-copy-row">
                                <button type="button" class="btn btn-sm lead-session-copy-btn" id="leadSessionCopyMsgEs">
                                    <i class="bi bi-clipboard"></i> Copiar mensaje (Español)
                                </button>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="lead-session-en" role="tabpanel">
                            <div id="leadSessionPreviewEn" class="lead-session-preview"></div>
                            <div class="lead-session-copy-row">
                                <button type="button" class="btn btn-sm lead-session-copy-btn lead-session-copy-btn-en" id="leadSessionCopyMsgEn">
                                    <i class="bi bi-clipboard"></i> Copy message (English)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="verMasModal" tabindex="-1" aria-labelledby="verMasModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verMasModalLabel">Detalles del Lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content will be populated by JS -->
                </div>
            </div>
        </div>
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

    <!-- Modal para registro manual de leads -->
    <div class="modal fade rlm-modal" id="registroManualModal" tabindex="-1" aria-labelledby="registroManualModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <!-- Header -->
                <div class="modal-header">
                    <div>
                        <h5 id="registroManualModalLabel">Registrar Lead Manual</h5>
                        <p class="rlm-subtitle">Completa los datos para crear un nuevo lead en el sistema</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Body -->
                <div class="modal-body">
                    <form id="formLeadManual">
                        <!-- Campo oculto para modo edición -->
                        <input type="hidden" id="leadEditId" name="lead_id" value="">
                        <!-- Campo oculto para origen del cliente (se asigna automáticamente) -->
                        <input type="hidden" id="leadHowDidYouMeet" name="how_did_you_meet" value="">

                        <!-- Sección 1: Datos de contacto -->
                        <div class="rlm-section">
                            <div class="rlm-section-title">
                                <div class="rlm-section-number">1</div>
                                <div class="rlm-section-heading">
                                    <h5>Datos de contacto</h5>
                                    <p>Nombre, correo, número de teléfono y datos básicos del evento.</p>
                                </div>
                            </div>

                            <div class="rlm-field">
                                <label for="leadNombre">Nombre completo <span class="rlm-tag">Requerido</span></label>
                                <input type="text" id="leadNombre" name="nombre" required placeholder="Nombre completo">
                                <span class="rlm-error-msg" id="err-leadNombre">El nombre es obligatorio</span>
                            </div>

                            <div class="rlm-grid-2">
                                <div class="rlm-field">
                                    <label for="leadCorreo">Correo electrónico <span class="rlm-optional">(opcional)</span></label>
                                    <input type="email" id="leadCorreo" name="correo" placeholder="correo@ejemplo.com">
                                    <span class="rlm-error-msg" id="err-leadCorreo">Si captura un correo, debe ser válido</span>
                                </div>
                                <div class="rlm-field">
                                    <label for="leadTelefono">WhatsApp / Teléfono <span class="rlm-optional">(opcional)</span></label>
                                    <div class="rlm-phone-row">
                                        <select id="leadCountryCode" name="country_code">
                                            <option value="">País</option>
                                            <!-- options populated dynamically -->
                                        </select>
                                        <input type="text" id="leadTelefono" name="telefono" placeholder="4771232323">
                                    </div>
                                    <span class="rlm-error-msg" id="err-leadTelefono">El teléfono debe tener mínimo 10 dígitos (solo números)</span>
                                </div>
                            </div>

                            <div class="rlm-grid-2">
                                <div class="rlm-field">
                                    <label for="leadWeddingLocation">Lugar del evento <span class="rlm-optional">(opcional)</span></label>
                                    <input type="text" id="leadWeddingLocation" name="wedding_location" placeholder="San Miguel de Allende">
                                </div>
                                <div class="rlm-field">
                                    <label for="leadWeddingDate">Fecha del evento <span class="rlm-optional">(opcional)</span></label>
                                    <input type="date" id="leadWeddingDate" name="wedding_date">
                                    <label class="rlm-checkbox-label" for="leadWeddingDateNotDefined">
                                        <input type="checkbox" id="leadWeddingDateNotDefined" name="wedding_date_not_defined" value="1">
                                        El cliente no ha definido aún
                                    </label>
                                </div>
                            </div>

                            <div class="rlm-field">
                                <label for="leadCity">Ciudad de origen del cliente <span class="rlm-optional">(opcional)</span></label>
                                <input type="text" id="leadCity" name="city" placeholder="León, Gto.">
                            </div>
                        </div>

                        <!-- Sección 2: Tipo de cliente -->
                        <div class="rlm-section">
                            <div class="rlm-section-title">
                                <div class="rlm-section-number">2</div>
                                <div class="rlm-section-heading">
                                    <h5>Tipo de cliente</h5>
                                    <p>Selecciona si el lead es un Wedding Planner o un Cliente Final.</p>
                                </div>
                            </div>

                            <input type="hidden" id="leadTipoCliente" name="tipo_cliente" value="Cliente Final" required>
                            <div class="rlm-choice-group" id="leadTipoClienteGroup">
                                <div class="rlm-choice-grid" style="grid-template-columns: repeat(2, minmax(0,1fr));">
                                    <button type="button" class="rlm-choice-card" data-target="#leadTipoCliente" data-value="Wedding Planner" disabled>
                                        <span class="rlm-choice-card-title">Wedding Planner</span>
                                        <span class="rlm-choice-card-desc">El lead es un wedding planner o coordinador de bodas.</span>
                                    </button>
                                    <button type="button" class="rlm-choice-card active" data-target="#leadTipoCliente" data-value="Cliente Final" aria-pressed="true">
                                        <span class="rlm-choice-card-title">Cliente Final</span>
                                        <span class="rlm-choice-card-desc">El lead es la pareja o cliente directo.</span>
                                    </button>
                                </div>
                            </div>
                            <span class="rlm-error-msg" id="err-leadTipoCliente">Este campo es obligatorio</span>
                        </div>

                        <!-- Sección 3: Primer contacto, origen y medio -->
                        <div class="rlm-section">
                            <div class="rlm-section-title">
                                <div class="rlm-section-number">3</div>
                                <div class="rlm-section-heading">
                                    <h5>Primer contacto</h5>
                                    <p>Selecciona primero el canal inicial y después confirma el origen. El medio se asigna automáticamente y se envía sin mostrarse.</p>
                                </div>
                            </div>

                            <div class="rlm-grid-2">
                                <div class="rlm-field">
                                    <label for="leadFirstContactChannel">Primer canal de contacto <span class="rlm-tag">Requerido</span></label>
                                    <select id="leadFirstContactChannel" name="first_contact_channel" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="WhatsApp">WhatsApp</option>
                                        <option value="IG">IG</option>
                                        <option value="Facebook">Facebook</option>
                                        <option value="TikTok">TikTok</option>
                                        <option value="Email">Correo electrónico</option>
                                        <option value="Phone call">Llamada telefónica</option>
                                    </select>
                                    <span class="rlm-error-msg" id="err-leadFirstContactChannel">Este campo es obligatorio</span>
                                </div>
                                <div class="rlm-field" id="leadTipoIGFieldWrap" style="display:none;">
                                    <label for="leadTipoIG">Tipo de IG <span class="rlm-tag">Requerido</span></label>
                                    <select id="leadTipoIG" name="tipo_ig">
                                        <option value="">Seleccionar...</option>
                                        <option value="organico">Orgánico</option>
                                        <option value="campana">Campaña</option>
                                    </select>
                                    <span class="rlm-error-msg" id="err-leadTipoIG">Este campo es obligatorio</span>
                                </div>
                            </div>

                            <div class="rlm-field" id="leadOrigenFieldWrap" style="display:none;">
                                <label for="leadOrigen">Campaña <span class="rlm-tag">Requerido</span></label>
                                <select id="leadOrigen" name="campaign_name" required>
                                    <option value="">Seleccionar...</option>
                                </select>
                                <span class="rlm-error-msg" id="err-leadOrigen">Es obligatorio</span>
                            </div>

                            <div class="rlm-field" id="leadMedioFieldWrap" style="display:none;">
                                <label for="leadMedio">Medio <span class="rlm-tag">Requerido</span></label>
                                <select id="leadMedio" name="platform" required>
                                    <option value="">Seleccionar...</option>
                                </select>
                                <span class="rlm-error-msg" id="err-leadMedio">El medio es obligatorio</span>
                            </div>
                        </div>

                        <!-- Sección 3: Conocimiento -->
                        <div class="rlm-section">
                            <div class="rlm-section-title">
                                <div class="rlm-section-number">4</div>
                                <div class="rlm-section-heading">
                                    <h5>Conocimiento</h5>
                                    <p>Captura la respuesta de antigüedad tal como se usa en el formulario web.</p>
                                </div>
                            </div>

                            <div class="rlm-field">
                                <label for="leadHowLongKnownUs">¿Desde hace cuánto nos conoce el cliente? <span class="rlm-tag">Requerido</span></label>
                                <input type="hidden" id="leadHowLongKnownUs" name="how_long_known_us" required>
                                <div class="rlm-choice-group" id="leadHowLongKnownUsGroup">
                                    <div class="rlm-choice-grid">
                                        <button type="button" class="rlm-choice-card" data-target="#leadHowLongKnownUs" data-value="Less than 6 months">
                                            <span class="rlm-choice-card-title">Menos de 6 meses</span>
                                            <span class="rlm-choice-card-desc">Nos conociste recientemente.</span>
                                        </button>
                                        <button type="button" class="rlm-choice-card" data-target="#leadHowLongKnownUs" data-value="More than 6 months">
                                            <span class="rlm-choice-card-title">Más de 6 meses</span>
                                            <span class="rlm-choice-card-desc">Ya tenías un tiempo siguiéndonos.</span>
                                        </button>
                                        <button type="button" class="rlm-choice-card" data-target="#leadHowLongKnownUs" data-value="Not asked">
                                            <span class="rlm-choice-card-title">Todavía no sabemos</span>
                                            <span class="rlm-choice-card-desc">Aún no tenemos esa información.</span>
                                        </button>
                                    </div>
                                </div>
                                <span class="rlm-error-msg" id="err-leadHowLongKnownUs">Este campo es obligatorio</span>
                            </div>
                        </div>

                        <!-- Sección 4: Fecha de registro -->
                        <div class="rlm-section" style="margin-bottom: 0;">
                            <div class="rlm-section-title">
                                <div class="rlm-section-number">4</div>
                                <div class="rlm-section-heading">
                                    <h5>Fecha de registro</h5>
                                    <p>Opcional — si no se selecciona se usará la fecha y hora actual.</p>
                                </div>
                            </div>
                            <div class="rlm-field" style="margin-bottom: 0;">
                                <label for="leadFechaRegistro">Fecha y hora <span class="rlm-optional">(opcional)</span></label>
                                <input type="datetime-local" id="leadFechaRegistro" name="fecha_registro">
                            </div>
                        </div>

                        <!-- Sección 5: Comentarios -->
                        <div class="rlm-section" style="margin-bottom: 0; margin-top: 20px;">
                            <div class="rlm-section-title">
                                <div class="rlm-section-number">5</div>
                                <div class="rlm-section-heading">
                                    <h5>Comentarios</h5>
                                    <p>Información adicional que el cliente compartió previamente, para conocimiento de la vendedora.</p>
                                </div>
                            </div>
                            <div class="rlm-field" style="margin-bottom: 0;">
                                <label for="leadComentarios">Notas del cliente <span class="rlm-optional">(opcional)</span></label>
                                <textarea id="leadComentarios" name="comentarios" rows="4" placeholder="Escribe aquí los comentarios o contexto adicional del cliente..."></textarea>
                            </div>
                        </div>

                    </form>
                </div>

                <!-- Footer -->
                <div class="modal-footer">
                    <button type="button" class="rlm-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="rlm-btn-submit" id="btnGuardarLeadManual">
                        <i class="bi bi-save"></i> Guardar Lead
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal para registro manual de Wedding Planner -->
    <div class="modal fade" id="registroManualWeddingModal" tabindex="-1"
        aria-labelledby="registroManualWeddingModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registroManualWeddingModalLabel">Registrar Lead Manual – Wedding Planner
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formWPManual">
                        <div class="mb-3">
                            <label for="wpCampaignName" class="form-label">Nombre de Wedding Planner <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="wpCampaignName" name="campaign_name" required>
                            <div class="invalid-feedback">El nombre del Wedding Planner es obligatorio</div>
                        </div>
                        <div class="mb-3">
                            <label for="wpWeddingLocation" class="form-label">Lugar de la boda</label>
                            <input type="text" class="form-control" id="wpWeddingLocation" name="wedding_location">
                        </div>
                        <div class="mb-3">
                            <label for="wpWeddingDate" class="form-label">Fecha de la boda</label>
                            <input type="date" class="form-control" id="wpWeddingDate" name="wedding_date">
                        </div>
                        <div class="mb-3">
                            <label for="wpFullName" class="form-label">Nombre de los novios<span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="wpFullName" name="full_name" required>
                            <div class="invalid-feedback">El nombre de los novios es obligatorio</div>
                        </div>
                        <div class="mb-3">
                            <label for="wpAsesor" class="form-label">Asesor <span class="text-danger">*</span></label>
                            <select class="form-select" id="wpAsesor" name="asesor" required>
                                <option value="">Seleccionar asesor</option>
                                <?php foreach ($agentes as $agente): ?>
                                    <option value="<?php echo intval($agente['id']); ?>">
                                        <?php echo htmlspecialchars($agente['nombre'] . ' ' . $agente['apepat']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">El asesor es obligatorio</div>
                        </div>

                        <div class="mb-3">
                            <label for="wpMode" class="form-label">Modo de registro <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="wpMode" name="modo" required>
                                <option value="">Seleccionar...</option>
                                <option value="asistencia_post_q">Asistencia Post-Q</option>
                                <option value="intencion_compra_pre_q">Intención de compra (Pre-Q)</option>
                            </select>
                            <div class="invalid-feedback">Seleccione el modo (Asistencia Post-Q)</div>
                        </div>



                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarWeddingPlanner"><i
                            class="bi bi-save"></i>
                        Guardar Wedding Planner</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de éxito con link del formulario: reemplazado por verLinkFormulario() (SweetAlert) -->

    <?php

    include 'footer.php';

    ?>


</body>

</html>

<style>
    /* WhatsApp-like view (contained inside modal only) */
    .wa-wrapper {
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        overflow: hidden;
        height: 72vh;
        display: flex;
        flex-direction: column;
        background: #e5ddd5;
    }

    .wa-topbar {
        background: #f0f2f5;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        padding: 10px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .wa-title {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .wa-icon {
        font-size: 22px;
        color: #25d366;
    }

    .wa-title-text {
        min-width: 0;
    }

    .wa-lead-name {
        font-weight: 600;
        line-height: 1.15;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 56vw;
    }

    .wa-lead-phone {
        font-size: 12px;
        color: rgba(0, 0, 0, 0.6);
        line-height: 1.15;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 56vw;
    }

    .wa-meta {
        font-size: 12px;
        color: rgba(0, 0, 0, 0.55);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 30vw;
        text-align: right;
    }

    .wa-chat {
        flex: 1;
        overflow: auto;
        padding: 14px 12px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .wa-composer {
        background: #f0f2f5;
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        padding: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .wa-clip {
        width: 40px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        padding: 0;
    }

    .wa-clip i {
        display: block;
        line-height: 1;
        font-size: 18px;
    }

    .wa-input {
        border-radius: 12px;
    }

    .wa-send {
        width: 44px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        padding: 0;
    }

    .wa-send i {
        display: block;
        line-height: 1;
        font-size: 18px;
    }

    .wa-row {
        display: flex;
        width: 100%;
    }

    .wa-row.in {
        justify-content: flex-start;
    }

    .wa-row.out {
        justify-content: flex-end;
    }

    .wa-row.meta {
        justify-content: center;
    }

    .wa-bubble {
        max-width: 78%;
        padding: 10px 12px;
        border-radius: 10px;
        box-shadow: 0 1px 1px rgba(0, 0, 0, 0.08);
        white-space: pre-wrap;
        word-break: break-word;
        line-height: 1.35;
        font-size: 14px;
    }

    .wa-bubble.in {
        background: #ffffff;
        border-top-left-radius: 6px;
    }

    .wa-bubble.out {
        background: #dcf8c6;
        border-top-right-radius: 6px;
    }

    .wa-bubble.meta {
        background: rgba(255, 255, 255, 0.7);
        color: rgba(0, 0, 0, 0.7);
        font-size: 12px;
        box-shadow: none;
    }

    .wa-empty {
        color: rgba(0, 0, 0, 0.6);
        text-align: center;
        padding: 24px 12px;
    }

    .wa-loading {
        color: rgba(0, 0, 0, 0.6);
        text-align: center;
        padding: 24px 12px;
    }

    .wa-unread-badge {
        font-size: .65rem;
        line-height: 1;
        min-width: 1.1rem;
        text-align: center;
    }

    /* Column Filter Styles */
    .filter-icon {
        cursor: pointer;
        margin-left: 5px;
        font-size: 0.85rem;
        color: #6c757d;
        transition: color 0.2s;
    }

    .filter-icon:hover {
        color: #495057;
    }

    .filter-icon.active {
        color: #007bff;
        font-weight: bold;
    }

    .filter-dropdown {
        position: absolute;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        z-index: 1050;
        min-width: 250px;
        max-width: 400px;
        display: none;
    }

    .filter-dropdown.show {
        display: block;
    }

    .filter-dropdown-header {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #dee2e6;
        background-color: #f8f9fa;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .filter-dropdown-body {
        padding: 0.75rem 1rem;
        max-height: 300px;
        overflow-y: auto;
    }

    .filter-search {
        width: 100%;
        padding: 0.375rem 0.75rem;
        margin-bottom: 0.75rem;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        font-size: 0.875rem;
    }

    .filter-option {
        display: flex;
        align-items: center;
        padding: 0.375rem 0;
        cursor: pointer;
        user-select: none;
    }

    .filter-option:hover {
        background-color: #f8f9fa;
    }

    .filter-option input[type="checkbox"] {
        margin-right: 0.5rem;
        cursor: pointer;
    }

    .filter-option label {
        cursor: pointer;
        margin: 0;
        flex: 1;
        font-size: 0.9rem;
    }

    .filter-dropdown-footer {
        padding: 0.75rem 1rem;
        border-top: 1px solid #dee2e6;
        background-color: #f8f9fa;
        display: flex;
        gap: 0.5rem;
        justify-content: space-between;
    }

    .filter-btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        border-radius: 0.25rem;
    }

    thead th {
        position: relative;
        white-space: nowrap;
    }

/* NEW EFEGE REPORT DASHBOARD STYLES (Light Mode Adapted) */
:root {
  --gold: #C5A028;
  --gold-dim: rgba(197,160,40,0.12);
  --dark: #F8FAFC;
  --panel: #FFFFFF;
  --surface: #F1F5F9;
  --ink: #1E293B;
  --muted: #64748B;
  --border: #E2E8F0;
  --white: #FFFFFF;

  --planner: #3B82F6;
  --planner-bg: rgba(59,130,246,0.12);
  --community: #10B981;
  --community-bg: rgba(16,185,129,0.12);
  --newmarket: #A855F7;
  --newmarket-bg: rgba(168,85,247,0.12);

  --eng-low: #F59E0B;
  --eng-mid: #3B82F6;
  --eng-high: #10B981;

  --radius: 12px;
}

.kpi-strip {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
  margin-bottom: 24px;
}
@media (max-width: 900px) {
  .kpi-strip { grid-template-columns: repeat(3, 1fr); }
}

.kpi-card {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 18px 20px;
}

.kpi-label {
  font-size: 10px;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 8px;
}

.kpi-value {
  font-size: 28px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1;
  margin-bottom: 6px;
}

.kpi-sub {
  font-size: 11px;
  color: var(--muted);
}
.kpi-sub .up { color: var(--community); }
.kpi-sub .tag { 
  display: inline-block;
  padding: 1px 7px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 600;
}

.filter-bar {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px 20px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  position: sticky;
  top: 20px;
  z-index: 100;
}

.filter-label {
  font-size: 11px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--muted);
  margin-right: 4px;
  flex-shrink: 0;
}

.table-wrap {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
}

.table-header-custom {
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
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

.badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
}

.badge-planner { background: var(--planner-bg); color: var(--planner); }
.badge-community { background: var(--community-bg); color: var(--community); }
.badge-newmarket { background: var(--newmarket-bg); color: var(--newmarket); }

.badge-dot {
  width: 5px; height: 5px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}

.ch-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  color: var(--muted);
}

.eng {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
}
.eng-low { background: rgba(245,158,11,0.12); color: var(--eng-low); }
.eng-mid { background: rgba(59,130,246,0.12); color: var(--eng-mid); }
.eng-high { background: rgba(16,185,129,0.12); color: var(--eng-high); }

.known {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  background: rgba(197,160,40,0.1);
  color: var(--gold);
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
.td-sub { font-size: 11px; color: var(--muted); margin-top: 2px; }

</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/locale/es.js"></script>

<!-- Note: jQuery, Bootstrap and DataTables are loaded globally in `footer.php` to avoid duplicate conflicting versions. -->
<script>
    // --- WhatsApp unread / attention badges ---
    var waUnreadCache = {};
    var waUnreadInFlight = {};

    function waKey(leadId, tablaOrigen) {
        return String(leadId) + '|' + String(tablaOrigen);
    }

    function waUpdateBadge(btn, status) {
        if (!btn) return;
        var badge = btn.querySelector('.wa-unread-badge');
        if (!badge) return;

        // Only show badges during human handoff mode.
        var handoffEnabled = !!(status && status.handoff && status.handoff.enabled);
        if (!handoffEnabled) {
            badge.textContent = '';
            badge.classList.add('d-none');
            return;
        }

        var unreadCount = status && typeof status.unreadCount === 'number' ? status.unreadCount : 0;
        var needsAttention = !!(status && status.needsAttention);

        var show = false;
        var text = '';
        if (unreadCount > 0) {
            show = true;
            text = unreadCount > 9 ? '9+' : String(unreadCount);
        } else if (needsAttention) {
            show = true;
            text = '!';
        }

        if (show) {
            badge.textContent = text;
            badge.classList.remove('d-none');
        } else {
            badge.textContent = '';
            badge.classList.add('d-none');
        }
    }

    function waFetchUnreadStatus(leadId, tablaOrigen, cb) {
        var key = waKey(leadId, tablaOrigen);
        var now = Date.now();
        if (waUnreadCache[key] && (now - waUnreadCache[key].t) < 8000) {
            cb && cb(waUnreadCache[key].status);
            return;
        }
        if (waUnreadInFlight[key]) return;
        waUnreadInFlight[key] = true;

        $.ajax({
            url: 'ajax/whatsapp_unread_status.php',
            type: 'POST',
            dataType: 'json',
            data: { leadId: leadId, tablaOrigen: tablaOrigen },
            success: function (resp) {
                if (resp && resp.success) {
                    waUnreadCache[key] = { t: Date.now(), status: resp };
                    cb && cb(resp);
                }
            },
            complete: function () {
                waUnreadInFlight[key] = false;
            }
        });
    }

    function waRefreshConversationButtons(scopeEl) {
        var scope = scopeEl || document;
        if (!scope.querySelectorAll) return;
        var btns = scope.querySelectorAll('.wa-conversation-btn[data-wa-conversation="1"]');
        if (!btns || !btns.length) return;

        btns.forEach(function (btn) {
            var leadId = btn.getAttribute('data-lead-id');
            var tablaOrigen = btn.getAttribute('data-tabla');
            if (!leadId || !tablaOrigen) return;
            waFetchUnreadStatus(leadId, tablaOrigen, function (status) {
                waUpdateBadge(btn, status);
            });
        });
    }

    function waMarkRead(leadId, tablaOrigen) {
        if (!leadId || !tablaOrigen) return;
        $.ajax({
            url: 'ajax/whatsapp_mark_read.php',
            type: 'POST',
            dataType: 'json',
            data: { leadId: leadId, tablaOrigen: tablaOrigen },
            complete: function () {
                try { delete waUnreadCache[waKey(leadId, tablaOrigen)]; } catch (e) { }
                waRefreshConversationButtons(document);
            }
        });
    }

    function escapeHtml(unsafe) {
        if (unsafe === undefined || unsafe === null) return '';
        return String(unsafe)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showErrorModalFromResponse(response, xhr) {
        var message = (response && (response.message || response.msg)) ? (response.message || response.msg) : 'Hubo un error al procesar la solicitud';
        var html = '<div style="text-align:left;">' + escapeHtml(message);
        if (response && response.mail_error) {
            html += '<hr><div><strong>Detalle:</strong><pre style="white-space:pre-wrap;">' + escapeHtml(response.mail_error) + '</pre></div>';
        }
        if (xhr && xhr.status) {
            html += '<hr><div><strong>HTTP:</strong> ' + xhr.status + ' ' + escapeHtml(xhr.statusText || '') + '</div>';
        }
        if (xhr && xhr.responseText) {
            // Try to parse JSON if present
            try {
                var parsed = JSON.parse(xhr.responseText);
                html += '<hr><div><strong>Body:</strong><pre style="white-space:pre-wrap;">' + escapeHtml(JSON.stringify(parsed, null, 2)) + '</pre></div>';
            } catch (e) {
                html += '<hr><div><strong>Body:</strong><pre style="white-space:pre-wrap;">' + escapeHtml(xhr.responseText) + '</pre></div>';
            }
        }
        html += '</div>';
        Swal.fire({
            title: 'Error',
            html: html,
            icon: 'error',
            width: '70%'
        });
        console.error('AJAX error details:', response, xhr);
    }
    $(document).ready(function () {
        // Si se solicita mostrar todo, adaptamos opciones para acelerar el renderizado
        var showAllInit = (new URLSearchParams(window.location.search)).has('show_all');

        leadsTableDT = $('#leadsTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            // Desactivar ordenamiento para evitar conflicto con iconos de filtro personalizados
            ordering: false,
            paging: false, // Desactivar paginación (seguimos mostrando todo cuando se pide)
            searching: false,
            dom: 'Brtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ],
            // RENDIMIENTO: diferir creación de nodos DOM (mejora notable con muchas filas)
            deferRender: true,
            // Mostrar indicador de procesamiento solo cuando pedimos TODO (mejor UX)
            processing: showAllInit,
            // Scroll lo maneja CSS en .efege-table-scroll; DataTables sin scrollX
            // para evitar que clone el thead y genere th con dataTables_sizing/sorting_disabled
            // drawCallback sigue siendo útil para refrescar botones dinámicos
            drawCallback: function () {
                waRefreshConversationButtons(document);
                _applyFilterVisibility(); // Re-aplicar visibilidad tras cualquier draw (ej. eliminación de fila)
                updateDynamicCards();
            },
            columnDefs: [
                { orderable: true, targets: '_all' }
            ]
        });

        setTimeout(function () {
            leadsTableDT.draw(false);
        }, 0);

        $(window).on('resize.leadsTableAdjust', function () {
            leadsTableDT.draw(false);
        });

        // Initial + periodic refresh so new inbound messages show a badge without reloading.
        setTimeout(function () {
            waRefreshConversationButtons(document);
        }, 800);

        setInterval(function () {
            waRefreshConversationButtons(document);
        }, 15000);

        // Inicializar cards dinámicos
        updateDynamicCards();

        // Inicializar atributo data-prev para poder revertir en caso de error
        $('.assign-select').each(function () {
            $(this).attr('data-prev', $(this).val());
        });

        var searchTimer;
        $('#leadsSearchInput').on('input', function () {
            var value = this.value || '';
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                var url = new URL(window.location.href);
                if (value.trim()) {
                    url.searchParams.set('search', value.trim());
                } else {
                    url.searchParams.delete('search');
                }
                window.location.href = url.toString();
            }, 450);
        });

        var chartDates = <?php echo isset($datesJson) ? $datesJson : '[]'; ?>;
        var chartCounts = <?php echo isset($countsJson) ? $countsJson : '[]'; ?>;

        var maxLeads = 0;
        chartCounts.forEach(function (count) {
            if (count > maxLeads) {
                maxLeads = count;
            }
        });

        var yAxisMax = Math.ceil(maxLeads * 1.15);
        if (yAxisMax < 5) {
            yAxisMax = 5;
        }

        var tickInterval = Math.ceil(yAxisMax / 5);
        if (tickInterval < 1) {
            tickInterval = 1;
        }

        Highcharts.chart('leadsChart', {
            chart: {
                type: 'line',
                backgroundColor: '#f8f9fa',
                borderRadius: 12,
                spacingTop: 10,
                spacingRight: 10,
                spacingBottom: 10,
                spacingLeft: 10
            },
            title: { text: null },
            xAxis: {
                categories: chartDates,
                crosshair: true,
                labels: {
                    rotation: -45,
                    style: { fontSize: '11px' }
                },
                lineColor: '#d9e2ec'
            },
            yAxis: {
                min: 0,
                max: yAxisMax,
                tickInterval: tickInterval,
                title: { text: 'Registros' },
                allowDecimals: false,
                gridLineColor: '#e2e8f0'
            },
            legend: { enabled: false },
            plotOptions: {
                line: {
                    color: '#2563eb',
                    lineWidth: 2.5,
                    marker: {
                        enabled: true,
                        radius: 4,
                        fillColor: '#2563eb',
                        lineWidth: 2,
                        lineColor: '#fff'
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function () {
                            return this.y > 0 ? this.y : '';
                        },
                        style: {
                            fontSize: '10px',
                            fontWeight: '600',
                            textOutline: 'none'
                        }
                    }
                }
            },
            series: [{
                name: 'Registros',
                data: chartCounts
            }],
            tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.92)',
                style: { color: '#fff' },
                borderWidth: 0,
                formatter: function () {
                    return '<b>' + this.x + '</b><br/>Total de registros: <b>' + this.y + '</b>';
                }
            },
            credits: { enabled: false }
        });
    });

    <?php if ($tipoUsuario != "4"): ?>
        function enviarPrimerCorreo(id, tablaOrigen, fullName, whenMarried, email) {
            Swal.fire({
                title: '¿Enviar primer correo?',
                text: `Se enviará el primer correo a ${fullName} (${email})`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Enviando correo...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: 'enviar_primer_correo_lead.php',
                        type: 'POST',
                        data: {
                            id: id,
                            tabla_origen: tablaOrigen,
                            full_name: fullName,
                            when_are_you_getting_married: whenMarried,
                            email: email
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire(
                                    '¡Enviado!',
                                    'El primer correo ha sido enviado exitosamente.',
                                    'success'
                                );
                            } else {
                                showErrorModalFromResponse(response);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Error:', error);
                            showErrorModalFromResponse(null, xhr);
                        }
                    });
                }
            });
        }

        function enviarSegundoCorreo(id, tablaOrigen, fullName, whenMarried, email) {
            Swal.fire({
                title: '¿Enviar segundo correo?',
                text: `Se enviará el segundo correo a ${fullName} (${email})`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Enviando correo...',
                        text: 'Por favor espera',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: 'enviar_segundo_correo_lead.php',
                        type: 'POST',
                        data: {
                            id: id,
                            tabla_origen: tablaOrigen,
                            full_name: fullName,
                            when_are_you_getting_married: whenMarried,
                            email: email
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire(
                                    '¡Enviado!',
                                    'El segundo correo ha sido enviado exitosamente.',
                                    'success'
                                );
                            } else {
                                showErrorModalFromResponse(response);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Error:', error);
                            showErrorModalFromResponse(null, xhr);
                        }
                    });
                }
            });
        }

        function descartarLead(id, tablaOrigen) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Este lead será marcado como descartado",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, descartar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('descartarLead called with', id, tablaOrigen);
                    $.ajax({
                        url: 'descartar_lead.php',
                        type: 'POST',
                        data: {
                            id: id,
                            tabla_origen: tablaOrigen
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire(
                                    '¡Descartado!',
                                    'El lead ha sido descartado exitosamente.',
                                    'success'
                                );

                                // Remover la fila de la tabla sin recargar
                                var table = $('#leadsTable').DataTable();
                                // Prefer data-attributes to locate the row (avoids issues when tablaOrigen contains special chars)
                                var $row = $('tr[data-lead-id="' + id + '"][data-tabla="' + tablaOrigen + '"]');
                                if ($row.length) {
                                    var row = table.row($row);
                                    row.remove().draw();
                                } else {
                                    // Fallback to id-based selector
                                    try {
                                        var row2 = table.row($('#lead-row-' + id + '-' + tablaOrigen));
                                        row2.remove().draw();
                                    } catch (e) {
                                        console.warn('Could not find row to remove for', id, tablaOrigen);
                                    }
                                }
                                // Close the 'Ver Más' modal if open
                                try { var verMasModal = bootstrap.Modal.getInstance(document.getElementById('verMasModal')); if (verMasModal) verMasModal.hide(); } catch (e) { }
                            } else {
                                Swal.fire(
                                    'Error',
                                    response.message || 'No se pudo descartar el lead',
                                    'error'
                                );
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Error:', error);
                            Swal.fire(
                                'Error',
                                'Hubo un problema al procesar la solicitud',
                                'error'
                            );
                        }
                    });
                }
            });
        }

        var vendedoresById = <?php echo json_encode($vendedoresById, JSON_UNESCAPED_UNICODE); ?>;
        var VENDEDOR_IA_LABELS = {
            99: 'Agente de llamada (IA)',
            100: 'Agente de whatsapp (IA)'
        };

        function getVendedorDisplayLabel(usuarioId) {
            var id = parseInt(usuarioId, 10) || 0;
            if (!id) {
                return '—';
            }
            if (VENDEDOR_IA_LABELS[id]) {
                return VENDEDOR_IA_LABELS[id];
            }
            var v = vendedoresById[id];
            if (v) {
                return ((v.nombre || '') + ' ' + (v.apepat || '')).trim() || '—';
            }
            return '—';
        }

        function copyTextToClipboard(text) {
            if (!text) {
                return Promise.reject(new Error('empty'));
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(text);
            }
            return new Promise(function (resolve, reject) {
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    resolve();
                } catch (err) {
                    document.body.removeChild(textarea);
                    reject(err);
                }
            });
        }

        var leadSessionMessages = { es: '', en: '' };

        function formatLeadSessionDateSpanish(dateStr) {
            var raw = (dateStr == null ? '' : String(dateStr)).trim();
            if (!raw || raw === '—') {
                return 'N/A';
            }
            var parsed = moment(raw, ['YYYY-MM-DD', 'DD/MM/YYYY', 'D/M/YYYY'], true);
            if (!parsed.isValid()) {
                parsed = moment(raw);
            }
            return parsed.isValid() ? parsed.locale('es').format('D [de] MMMM [de] YYYY') : raw;
        }

        function formatLeadSessionDateEnglish(dateStr) {
            var raw = (dateStr == null ? '' : String(dateStr)).trim();
            if (!raw || raw === '—') {
                return 'N/A';
            }
            var parsed = moment(raw, ['YYYY-MM-DD', 'DD/MM/YYYY', 'D/M/YYYY'], true);
            if (!parsed.isValid()) {
                parsed = moment(raw);
            }
            return parsed.isValid() ? parsed.locale('en').format('MMMM D, YYYY') : raw;
        }

        function formatLeadSessionTimeJs(hora) {
            var raw = (hora == null ? '' : String(hora)).trim();
            if (!raw || raw === '—') {
                return 'N/A';
            }
            var parsed = moment(raw, ['HH:mm:ss', 'HH:mm', 'h:mm A', 'hh:mm A'], true);
            if (!parsed.isValid()) {
                parsed = moment(raw, ['h:mm a', 'hh:mm a'], true);
            }
            return parsed.isValid() ? parsed.format('hh:mm A') : raw;
        }

        function buildLeadSessionMessageEs(data) {
            return (
                '✅ Tu sesión se ha agendado con éxito\n\n' +
                'Nombre del cliente: ' + data.nombreCliente + '\n' +
                'Día de la sesión: ' + data.fechaEs + '\n' +
                'Hora de la sesión: ' + data.hora + '\n' +
                'Asesora asignada: ' + data.asesora + '\n\n' +
                'Este es el enlace de tu videollamada:\n\n' +
                data.meetLinkEs + '\n\n' +
                'Te esperamos en la fecha y hora programadas. ¡Será un placer atenderte!'
            );
        }

        function buildLeadSessionMessageEn(data) {
            return (
                '✅ Your session has been successfully scheduled\n\n' +
                'Client Name: ' + data.nombreCliente + '\n' +
                'Session Date: ' + data.fechaEn + '\n' +
                'Session Time: ' + data.hora + '\n' +
                'Assigned Advisor: ' + data.asesora + '\n\n' +
                'Here is the link for your video call:\n\n' +
                data.meetLinkEn + '\n\n' +
                'We look forward to meeting with you at the scheduled date and time. It will be our pleasure to assist you!'
            );
        }

        function showLeadSessionCopyToast(title, icon) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: icon || 'success',
                title: title,
                showConfirmButton: false,
                timer: 2200,
                timerProgressBar: true
            });
        }

        function copyLeadSessionMessage(lang) {
            var text = leadSessionMessages[lang] || '';
            if (!text) {
                return;
            }
            copyTextToClipboard(text).then(function () {
                showLeadSessionCopyToast(
                    lang === 'es' ? 'Mensaje en español copiado' : 'English message copied',
                    'success'
                );
            }).catch(function () {
                showLeadSessionCopyToast('No se pudo copiar el mensaje', 'error');
            });
        }

        function verDatosSesion(sessionData) {
            if (!sessionData) {
                return;
            }

            var meetRaw = (sessionData.enlace_meet || '').trim();
            var meetLinkEs = (meetRaw && meetRaw !== '—') ? meetRaw : 'No disponible';
            var meetLinkEn = (meetRaw && meetRaw !== '—') ? meetRaw : 'Not available';
            var fechaSource = sessionData.fecha_raw || sessionData.fecha || '';
            var horaSource = sessionData.hora_raw || sessionData.hora || '';

            var messageData = {
                nombreCliente: sessionData.nombre_cliente || '—',
                asesora: sessionData.asesora || 'Sin asignar',
                meetLinkEs: meetLinkEs,
                meetLinkEn: meetLinkEn,
                fechaEs: formatLeadSessionDateSpanish(fechaSource),
                fechaEn: formatLeadSessionDateEnglish(fechaSource),
                hora: formatLeadSessionTimeJs(horaSource)
            };

            leadSessionMessages.es = buildLeadSessionMessageEs(messageData);
            leadSessionMessages.en = buildLeadSessionMessageEn(messageData);

            $('#leadSessionPreviewEs').text(leadSessionMessages.es);
            $('#leadSessionPreviewEn').text(leadSessionMessages.en);

            $('#leadSessionCopyMsgEs').off('click').on('click', function () {
                copyLeadSessionMessage('es');
            });
            $('#leadSessionCopyMsgEn').off('click').on('click', function () {
                copyLeadSessionMessage('en');
            });

            var esTab = document.getElementById('lead-session-es-tab');
            if (esTab && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(esTab).show();
            }

            var modalEl = document.getElementById('leadSessionModal');
            if (modalEl) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        }

        function asignarUsuario(selectElem) {
            // Coerce the select value to an integer when possible to avoid sending unexpected text.
            var rawVal = selectElem.value;
            var usuarioId = (rawVal === '' || rawVal === null) ? '' : parseInt(rawVal, 10);
            if (isNaN(usuarioId)) usuarioId = rawVal; // keep original if not numeric (defensive)
            var leadId = $(selectElem).data('lead-id');
            var tabla = $(selectElem).data('tabla');
            var $sel = $(selectElem);
            var $row = $sel.closest('tr');
            var prev = $sel.attr('data-prev') || '';

            // Enviar sin confirmación y mostrar un pequeño toast; si falla, revertir la selección
            $sel.prop('disabled', true);
            $.ajax({
                url: 'asignar_usuario_lead.php',
                type: 'POST',
                data: {
                    id: leadId,
                    tabla_origen: tabla,
                    usuario_id: usuarioId
                },
                dataType: 'json',
                success: function (resp) {
                    console.log(resp);
                    if (resp.success) {
                        // Actualizar data-prev al nuevo valor
                        $sel.attr('data-prev', usuarioId);
                        $row.find('td[data-column="vendedor"]').text(getVendedorDisplayLabel(usuarioId));
                        Swal.fire({
                            toast: true,
                            position: 'bottom-end',
                            icon: 'success',
                            title: 'Vendedor asignado',
                            showConfirmButton: false,
                            timer: 1400
                        });
                        // Mostrar advertencia si la asignación funcionó pero no se pudo enviar correo
                        if (resp.mail_sent === false) {
                            Swal.fire({
                                toast: true,
                                position: 'bottom-end',
                                icon: 'warning',
                                title: 'Asignado, pero no se pudo enviar notificación por correo',
                                showConfirmButton: false,
                                timer: 2200
                            });
                            if (resp.mail_error) console.warn('Error enviando notificación:', resp.mail_error);
                        }
                    } else {
                        // Revertir a valor anterior
                        $sel.val(prev);
                        Swal.fire({
                            toast: true,
                            position: 'bottom-end',
                            icon: 'error',
                            title: resp.message || 'No se pudo asignar el vendedor',
                            showConfirmButton: false,
                            timer: 2200
                        });
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);

                    $sel.val(prev);
                    Swal.fire({
                        toast: true,
                        position: 'bottom-end',
                        icon: 'error',
                        title: 'Error en la petición al servidor',
                        showConfirmButton: false,
                        timer: 2200
                    });
                },
                complete: function () {
                    $sel.prop('disabled', false);
                }
            });
        }
    <?php endif; ?>

    function reestablecerCorreos(id, tablaOrigen) {
        Swal.fire({
            title: '¿Reestablecer correos? ',
            text: 'Esto establecerá los estados de envío de correos a no enviados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, reestablecer',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;
            Swal.fire({
                title: 'Reestableciendo...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: 'reestablecer_correos_lead.php',
                type: 'POST',
                data: { id: id, tabla_origen: tablaOrigen },
                dataType: 'json',
                success: function (resp) {
                    if (resp.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Reestablecido',
                            text: 'Los estados de envío se reestablecieron correctamente',
                            showConfirmButton: false,
                            timer: 1500
                        });
                        // Cerrar el modal Ver Más si está abierto
                        try {
                            var verMasModal = bootstrap.Modal.getInstance(document.getElementById('verMasModal'));
                            if (verMasModal) {
                                verMasModal.hide();
                            }
                        } catch (e) { }
                    } else {
                        Swal.fire('Error', resp.message || 'No se pudo reestablecer', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    Swal.fire('Error', 'Hubo un problema al procesar la solicitud', 'error');
                }
            });
        });
    }

    // Delegated click handler for descartar buttons (ensures click works even when buttons are injected into modal)
    $(document).on('click', '.btn-descartar', function (e) {
        var id = $(this).data('id');
        var tabla = $(this).data('tabla');
        try { descartarLead(id, tabla); } catch (err) { console.error('Error calling descartarLead:', err, id, tabla); }
    });

    function verMas(lead) {
        // Build the details table for the lead
        let detailsHeaderHtml = '<div class="modal-section-header">' +
            '<h6 class="modal-section-title">Información del Lead</h6>' +
            '<div class="modal-section-subtitle">Datos registrados y estado actual</div>' +
            '</div>';
        let content = '<table class="table table-striped">';
        const consentField = 'are_you_ok_with_us_contacting_you__or__did_you_want_to_contact_u';
        const baseFields = ['id', 'id_excel', 'created_time', 'city', 'wedding_location', 'wedding_date', 'wedding_date_not_defined', 'how_long_known_us', 'first_contact_channel', 'how_did_you_meet', 'hear_about_us', 'ad_id', 'ad_name', 'adset_id', 'adset_name', 'campaign_id', 'campaign_name', 'form_id', 'form_name', 'is_organic', 'platform', 'when_are_you_getting_married_', 'where_are_you_getting_married_', 'choose_your_call_date_', 'email', 'full_name', 'phone', 'lead_status', 'estatus_cita', 'id_calendario', 'id_evento', 'id_wedding_planner', 'id_vendedor_asignado', 'usuario_asignado', 'calendario_fecha', 'calendario_hora', 'fecha_cliente', 'hora_cliente', 'comentario', 'cliente_engagement', 'fecha_importacion', 'correo_uno_enviado', 'fecha_envio_correo_uno', 'correo_dos_enviado', 'fecha_envio_correo_dos', 'descartado'];
        // Start with base list; include consentField at the top only if it's present and non-empty on this lead
        let fields = baseFields.slice();
        if (lead && Object.prototype.hasOwnProperty.call(lead, consentField) && lead[consentField] !== null && String(lead[consentField]).trim() !== '') {
            fields.unshift(consentField);
        }
        fields.forEach(field => {
            var raw = lead[field];
            var value = (raw === undefined || raw === null || String(raw).trim() === '') ? 'N/A' : escapeHtml(raw);

            // Normalizar y presentar el campo de consentimiento de forma amigable
            if (field === consentField) {
                var rawStr = String(raw || '').trim();
                if (rawStr === '') {
                    value = 'No aplica';
                } else {
                    // Quitar guiones bajos y colapsar espacios, luego escapar
                    var v = rawStr.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();
                    value = escapeHtml(v);
                }
            }

            if (field === 'where_are_you_getting_married_') {
                var rawAlt = lead['where_are_you_getting_married_'] || lead['where_is_your_marriage_taking_place_'];
                value = (rawAlt === undefined || rawAlt === null || String(rawAlt).trim() === '') ? 'N/A' : escapeHtml(rawAlt);
            }
            if (['correo_uno_enviado', 'correo_dos_enviado', 'descartado', 'wedding_date_not_defined'].includes(field)) {
                value = (raw == 1) ? 'Sí' : 'No';
            }
            var label = (field === consentField) ? 'Consentimiento de contacto' : field.replace(/_/g, ' ');
            content += `<tr><td><strong>${label}</strong></td><td>${value}</td></tr>`;
        });
        content += '</table>';
        // Build action buttons HTML to be included inside the modal (placed above the details table)
        var actionsHtml = '<div class="mb-3">'; // use bottom margin so table appears below actions
        // Add a header & subtitle for the actions block
        actionsHtml += '<div class="modal-section-header">' +
            '<h6 class="modal-section-title">Acciones</h6>' +
            '<div class="modal-section-subtitle">Operaciones disponibles para este lead</div>' +
            '</div>';
        var tablaOrigen = lead['tabla_origen'] || '';
        var leadId = lead['id'] || '';
        var usuarioAsignado = lead['usuario_asignado'] ? parseInt(lead['usuario_asignado']) : 0;

        // Botón para ver conversación de WhatsApp (solo si está asignado al agente 100)
        /*
        if (usuarioAsignado === 100) {
            actionsHtml += '<button class="mb-2 btn btn-info btn-sm wa-conversation-btn btn-wa-conversacion" data-wa-conversation="1" data-lead-id="' + String(leadId) + '" data-tabla="' + String(tablaOrigen) + '" onclick="verHistorialWhatsApp(' + JSON.stringify(leadId) + ', ' + JSON.stringify(tablaOrigen) + ')">' +
                '<i class="bi bi-whatsapp"></i> Ver Conversación de WhatsApp' +
                '<span class="bg-danger ms-2 rounded-pill badge d-none wa-unread-badge" title="Mensaje nuevo / requiere atención" aria-hidden="true"></span>' +
                '</button>';
        }
        */

        // If it's an organic lead, show link (if not scheduled) and a discard button
        if (tablaOrigen === 'organic_leads') {
            // Botón editar para leads manuales
            if (lead['lead_status'] === 'manual') {
                actionsHtml += '<button class="mb-1 btn btn-warning btn-sm btn-editar-lead" title="Editar Lead Manual" style="background-color:#c5a028;color:white;border:1px solid #c5a028;" data-lead-payload="' + encodeURIComponent(JSON.stringify(lead)) + '"><i class="bi bi-pencil"></i> Editar Lead</button>';
            }
            actionsHtml += '<div class="d-flex flex-wrap gap-2">';
            if (lead['scheduled'] && lead['scheduled'] == 1) {
                actionsHtml += '<span class="bg-success badge">Ya agendó</span>';
            }
            // Ver Link: show only if not scheduled
            if (!lead['scheduled'] || lead['scheduled'] == 0) {
                var agendaVendedoraUrlOrg = 'https://citas.efegepho.com.mx/inquire-form-vendedor.php?tabla_origen=' + encodeURIComponent(tablaOrigen) + '&id=' + encodeURIComponent(leadId);
                actionsHtml += '<button class="btn btn-info btn-sm" title="Ver Link del Formulario" style="background-color: #5e543f; color: white; border: 1px solid #5e543f;" onclick=\'verLinkFormulario(' + JSON.stringify(tablaOrigen) + ', ' + JSON.stringify(leadId) + ')\'><i class="bi bi-link-45deg"></i> Ver Link</button>';
                actionsHtml += '<a class="btn btn-sm" title="Agendar por vendedora" target="_blank" rel="noopener noreferrer" style="background-color: #8a4a0f; color: white; border: 1px solid #8a4a0f; margin-left:5px;" href="' + agendaVendedoraUrlOrg + '"><i class="bi bi-person-fill-gear"></i> Agendar vía interno</a>';
                // WhatsApp button (if phone present)
                if (lead['phone'] && String(lead['phone']).replace(/\D/g, '').length >= 8) {
                    if (lead['whatsapp_enviado'] && parseInt(lead['whatsapp_enviado']) === 1) {
                        actionsHtml += '<button class="btn btn-success btn-sm whatsapp-btn" title="WhatsApp enviado" style="background-color:#25D366;color:white;border: 1px solid #25D366; margin-left:5px;"><i class="bi bi-whatsapp"></i> WhatsApp<br/><small class="text-muted">enviado</small></button>';
                    } else {
                        actionsHtml += '<button class="btn btn-success btn-sm whatsapp-btn" title="Enviar por WhatsApp" style="background-color:#25D366;color:white;border: 1px solid #25D366; margin-left:5px;" onclick=\'sendWhatsApp(this, ' + JSON.stringify(tablaOrigen) + ', ' + JSON.stringify(leadId) + ', ' + JSON.stringify(lead['phone'] || '') + ', ' + JSON.stringify(lead['full_name'] || '') + ')\'><i class="bi bi-whatsapp"></i> WhatsApp</button>';
                    }
                }
            }
            actionsHtml += '<button class="btn btn-secondary btn-sm btn-descartar" title="Descartar" data-id="' + String(leadId) + '" data-tabla="' + String(tablaOrigen) + '"><i class="bi bi-x-circle"></i> Descartar</button>';
            actionsHtml += '</div>';
        } else {
            // Non-organic leads: show Agenda manual (link), Reestablecer, and Descartar
            var agendaUrl = 'https://citas.efegepho.com.mx/inquire-form.php?tabla_origen=' + encodeURIComponent(tablaOrigen) + '&id=' + encodeURIComponent(leadId);
            var agendaVendedoraUrl = 'https://citas.efegepho.com.mx/inquire-form-vendedor.php?tabla_origen=' + encodeURIComponent(tablaOrigen) + '&id=' + encodeURIComponent(leadId);
            actionsHtml += '<div class="d-flex gap-2 flex-wrap">';
            if (!lead['scheduled'] || lead['scheduled'] == 0) {
                actionsHtml += '<a class="btn btn-warning btn-sm" title="Agenda manual (cliente)" target="_blank" rel="noopener noreferrer" style="background-color: #5e543f; color: white; border: 1px solid #5e543f;" href="' + agendaUrl + '"><i class="bi bi-calendar"></i> Agenda vía cliente</a>';
                actionsHtml += '<a class="btn btn-sm" title="Agendar vía vendedora" target="_blank" rel="noopener noreferrer" style="background-color: #8a4a0f; color: white; border: 1px solid #8a4a0f;" href="' + agendaVendedoraUrl + '"><i class="bi bi-person-fill-gear"></i> Agendar vía inerno</a>';
                // Ver Link for non-organics: also show if not scheduled (hide for Wedding Planner)
                if (tablaOrigen !== 'wedding_planners') {
                    actionsHtml += '<button class="btn btn-info btn-sm" title="Ver Link del Formulario" style="background-color: #5e543f; color: white; border: 1px solid #5e543f; margin-left:5px;" onclick=\'verLinkFormulario(' + JSON.stringify(tablaOrigen) + ', ' + JSON.stringify(leadId) + ')\'><i class="bi bi-link-45deg"></i> Ver Link</button>';
                }
                // WhatsApp button for non-organics if phone present
                if (lead['phone'] && String(lead['phone']).replace(/\D/g, '').length >= 8) {
                    if (lead['whatsapp_enviado'] && parseInt(lead['whatsapp_enviado']) === 1) {
                        actionsHtml += '<button class="btn btn-success btn-sm whatsapp-btn" title="WhatsApp enviado" style="background-color:#25D366;color:white;border: 1px solid #25D366; margin-left:5px;"><i class="bi bi-whatsapp"></i> WhatsApp<br/><small class="text-muted">enviado</small></button>';
                    } else {
                        actionsHtml += '<button class="btn btn-success btn-sm whatsapp-btn" title="Enviar por WhatsApp" style="background-color:#25D366;color:white;border: 1px solid #25D366; margin-left:5px;" onclick=\'sendWhatsApp(this, ' + JSON.stringify(tablaOrigen) + ', ' + JSON.stringify(leadId) + ', ' + JSON.stringify(lead['phone'] || '') + ', ' + JSON.stringify(lead['full_name'] || '') + ')\'><i class="bi bi-whatsapp"></i> WhatsApp</button>';
                    }
                }
            } else {
                actionsHtml += '<span class="bg-success badge">Ya agendó</span>';
            }
            actionsHtml += '<button class="btn btn-primary btn-sm" title="Reestablecer" style="background-color: #5e543f; color: white; border: 1px solid #5e543f;" onclick=\'reestablecerCorreos(' + JSON.stringify(leadId) + ', ' + JSON.stringify(tablaOrigen) + ')\'><i class="bi bi-arrow-clockwise"></i> Reestablecer</button>';
            actionsHtml += '<button class="btn btn-secondary btn-sm btn-descartar" title="Descartar" data-id="' + String(leadId) + '" data-tabla="' + String(tablaOrigen) + '"><i class="bi bi-x-circle"></i> Descartar</button>';
            actionsHtml += '</div>';
        }

        actionsHtml += '</div>';

        // Prepend actions area above the details table inside the modal
        // Ensure `detailsHeaderHtml` is inserted after the actions so the title/subtitle appear below the buttons and above the details
        document.getElementById('modalBody').innerHTML = actionsHtml + detailsHeaderHtml + content;

        // Refrescar badge de atención/no leídos dentro del modal Ver Más
        if (usuarioAsignado === 100 && leadId && tablaOrigen) {
            try { waRefreshConversationButtons(document.getElementById('modalBody')); } catch (e) { }
        }
        var modal = new bootstrap.Modal(document.getElementById('verMasModal'));
        modal.show();
    }

    function verScript() {
        var pdfPath = "../files/Script para Forms ( whatsapp y llamada).pdf";
        var src = encodeURI(pdfPath);
        Swal.fire({
            title: 'Vista previa del Script',
            html: '<div style="height:70vh; width:100%;"><iframe src="' + src + '" style="width:100%;height:100%;border:0;" allowfullscreen></iframe></div>',
            width: '90%',
            showCloseButton: true,
            showConfirmButton: false,
        });
    }

    function verLinkFormulario(tablaOrigen, leadId, options) {
        options = options || {};
        var link = 'https://citas.efegepho.com.mx/inquire-form.php?tabla_origen=' + encodeURIComponent(tablaOrigen) + '&id=' + encodeURIComponent(leadId);
        var linkVendedora = 'https://citas.efegepho.com.mx/inquire-form-vendedor.php?tabla_origen=' + encodeURIComponent(tablaOrigen) + '&id=' + encodeURIComponent(leadId);
        var introHtml = options.introHtml || '';
        return Swal.fire({
            title: options.title || 'Links del Formulario',
            html: introHtml +
                '<div class="mb-3">' +
                '<label class="form-label"><strong>Link para el lead</strong> (agendar vía cliente):</label>' +
                '<div class="input-group">' +
                '<input type="text" class="form-control" id="linkOrganicLead" value="' + link + '" readonly>' +
                '<button class="btn-outline-primary btn" type="button" onclick="copiarLinkOrganic()" title="Copiar link">' +
                '<i class="bi bi-clipboard"></i> Copiar' +
                '</button>' +
                '</div>' +
                '</div>' +
                '<div id="copiadoAlertOrganic" class="alert alert-success d-none" role="alert">' +
                '<i class="bi bi-check2"></i> \u00a1Link copiado al portapapeles!' +
                '</div>' +
                '<hr>' +
                '<div class="mb-3">' +
                '<label class="form-label"><strong>Link para la vendedora</strong> (agendar vía interno):</label>' +
                '<div class="input-group">' +
                '<input type="text" class="form-control" id="linkVendedoraLead" value="' + linkVendedora + '" readonly>' +
                '<button class="btn-outline-warning btn" type="button" onclick="copiarLinkVendedora()" title="Copiar link vendedora">' +
                '<i class="bi bi-clipboard"></i> Copiar' +
                '</button>' +
                '</div>' +
                '</div>' +
                '<div id="copiadoAlertVendedora" class="alert alert-success d-none" role="alert">' +
                '<i class="bi bi-check2"></i> \u00a1Link vendedora copiado!' +
                '</div>',
            showCloseButton: true,
            showConfirmButton: false,
            width: '640px'
        }).then(function (result) {
            if (typeof options.onClose === 'function') {
                options.onClose(result);
            }
            return result;
        });
    }

    function sendWhatsApp(el, tablaOrigen, leadId, phone, fullName) {
        var cleaned = (phone || '').replace(/\D/g, '');
        if (!cleaned || cleaned.length < 8) {
            Swal.fire({
                icon: 'error',
                title: 'Número inválido',
                text: 'El número de teléfono no es válido para enviar por WhatsApp.'
            });
            return;
        }
        // Build the inquire form link
        var link = 'https://citas.efegepho.com.mx/inquire-form.php?tabla_origen=' + encodeURIComponent(tablaOrigen) + '&id=' + encodeURIComponent(leadId);
        var name = fullName || '';
        var txt = 'Hola ' + name + ', te comparto el link para agendar: ' + link;
        var waLink = 'https://wa.me/' + cleaned + '?text=' + encodeURIComponent(txt);
        window.open(waLink, '_blank');

        // Send AJAX request to mark whatsapp as sent
        $.ajax({
            url: 'marcar_whatsapp_enviado.php',
            type: 'POST',
            data: { id: leadId, tabla_origen: tablaOrigen },
            dataType: 'json',
            success: function (resp) {
                if (resp && resp.success) {
                    // Update clicked button and table buttons visually without disabling them
                    try {
                        if (el) {
                            $(el).prop('disabled', false).html('<i class="bi bi-whatsapp"></i> WhatsApp<br/><small class="text-muted">enviado</small>');
                        }
                        // Also update table row button(s), if any
                        var $row = $(`#lead-row-${leadId}-${tablaOrigen}`);
                        if ($row.length) {
                            var $btns = $row.find('.whatsapp-btn');
                            $btns.each(function () {
                                $(this).prop('disabled', false).html('<i class="bi bi-whatsapp"></i> WhatsApp<br/><small class="text-muted">enviado</small>');
                            });
                        }
                    } catch (e) {
                        console.error('Error updating UI after marking WhatsApp:', e);
                    }
                } else {
                    Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo actualizar estado de WhatsApp', 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);
                Swal.fire('Error', 'No se pudo actualizar estado de WhatsApp', 'error');
            }
        });
    }

    function verHistorialWhatsApp(leadId, tablaOrigen) {
        console.log('verHistorialWhatsApp llamado con leadId:', leadId, 'tablaOrigen:', tablaOrigen);

        // Verificar que el modal existe
        var modalElement = document.getElementById('whatsappHistorialModal');
        if (!modalElement) {
            console.error('Modal whatsappHistorialModal no encontrado');
            return;
        }

        // Guardar contexto actual para envío (se usa por el handler del botón enviar)
        modalElement._waCurrentLeadId = leadId;
        modalElement._waCurrentTablaOrigen = tablaOrigen;

        function waScrollToBottom() {
            var chatEl = document.getElementById('waChat');
            if (!chatEl) return;
            chatEl.scrollTop = chatEl.scrollHeight;
        }

        function waIsNearBottom(chatEl) {
            if (!chatEl) return true;
            var remaining = chatEl.scrollHeight - chatEl.scrollTop - chatEl.clientHeight;
            return remaining < 40;
        }

        function waStartPolling(loadFn) {
            // Keep polling state on the modal element so it survives re-open.
            try {
                if (modalElement._waPollTimer) {
                    clearInterval(modalElement._waPollTimer);
                    modalElement._waPollTimer = null;
                }
                modalElement._waPollLeadId = leadId;
                modalElement._waPollTablaOrigen = tablaOrigen;
                modalElement._waPollTimer = setInterval(function () {
                    // Only poll while modal is actually visible
                    if (!modalElement.classList.contains('show')) return;
                    loadFn(true);
                }, 1000);
            } catch (e) {
                console.error('Error iniciando polling WhatsApp:', e);
            }
        }

        function waStopPolling() {
            try {
                if (modalElement && modalElement._waPollTimer) {
                    clearInterval(modalElement._waPollTimer);
                    modalElement._waPollTimer = null;
                }
            } catch (e) {
                // ignore
            }
        }

        function waSetComposerEnabled(enabled) {
            var composer = modalElement ? modalElement.querySelector('.wa-composer') : null;
            if (!composer) return;
            var input = composer.querySelector('.wa-input');
            var sendBtn = composer.querySelector('.wa-send');
            var clipLabel = composer.querySelector('.wa-clip');
            var clipInput = clipLabel ? clipLabel.querySelector('input[type="file"]') : null;

            if (enabled) {
                composer.setAttribute('aria-label', 'Enviar mensaje');
                if (input) input.disabled = false;
                if (sendBtn) {
                    sendBtn.disabled = false;
                    sendBtn.removeAttribute('title');
                }
                if (clipLabel) clipLabel.classList.remove('disabled');
                if (clipInput) clipInput.disabled = false;
            } else {
                composer.setAttribute('aria-label', 'Enviar mensaje (deshabilitado)');
                if (input) input.disabled = true;
                if (sendBtn) {
                    sendBtn.disabled = true;
                    sendBtn.setAttribute('title', 'Enviar (deshabilitado)');
                }
                if (clipLabel) clipLabel.classList.add('disabled');
                if (clipInput) clipInput.disabled = true;
            }
        }

        function waBindSendOnce() {
            if (!modalElement || modalElement.dataset.waSendBound === '1') return;
            modalElement.dataset.waSendBound = '1';

            var composer = modalElement.querySelector('.wa-composer');
            if (!composer) return;
            var input = composer.querySelector('.wa-input');
            var sendBtn = composer.querySelector('.wa-send');

            function doSend() {
                if (!input || input.disabled) return;
                var text = (input.value || '').trim();
                if (!text) return;

                var currentLeadId = modalElement._waCurrentLeadId;
                var currentTablaOrigen = modalElement._waCurrentTablaOrigen;
                if (!currentLeadId || !currentTablaOrigen) return;

                sendBtn && (sendBtn.disabled = true);

                $.ajax({
                    url: 'ajax/whatsapp_send_message.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        leadId: currentLeadId,
                        tablaOrigen: currentTablaOrigen,
                        message: text
                    },
                    success: function (resp) {
                        if (resp && resp.success) {
                            input.value = '';
                            // Force refresh so the sent message appears
                            try { modalElement._waLastFingerprint = null; } catch (e) { }

                            // Refresh badges right away (agent responded)
                            try {
                                delete waUnreadCache[waKey(currentLeadId, currentTablaOrigen)];
                            } catch (e) { }
                            try { waRefreshConversationButtons(document); } catch (e) { }
                        } else {
                            Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo enviar el mensaje', 'error');
                        }
                    },
                    error: function (xhr) {
                        Swal.fire('Error', 'Error al enviar el mensaje', 'error');
                        console.error('Error AJAX enviar WhatsApp:', xhr && xhr.responseText ? xhr.responseText : xhr);
                    },
                    complete: function () {
                        sendBtn && (sendBtn.disabled = false);
                    }
                });
            }

            if (sendBtn) {
                sendBtn.addEventListener('click', doSend);
            }
            if (input) {
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        doSend();
                    }
                });
            }
        }

        // Ensure we always open at the latest message
        if (!modalElement.dataset.waScrollHook) {
            modalElement.addEventListener('shown.bs.modal', function () {
                // Wait for layout
                setTimeout(waScrollToBottom, 0);
            });
            modalElement.addEventListener('hidden.bs.modal', function () {
                waStopPolling();
                // Refresh badges after closing chat modal
                try { waRefreshConversationButtons(document); } catch (e) { }
            });
            modalElement.dataset.waScrollHook = '1';
        }

        // Cerrar el modal "Ver Más" si está abierto
        var verMasModal = bootstrap.Modal.getInstance(document.getElementById('verMasModal'));
        if (verMasModal) {
            console.log('Cerrando modal Ver Más');
            verMasModal.hide();
        }

        // Esperar un poco para que se cierre el modal anterior antes de abrir el nuevo
        setTimeout(function () {
            console.log('Abriendo modal de WhatsApp');

            var chat = document.getElementById('waChat');
            var leadNameEl = document.getElementById('waLeadName');
            var leadPhoneEl = document.getElementById('waLeadPhone');
            var metaEl = document.getElementById('waMeta');

            if (leadNameEl) leadNameEl.textContent = 'Conversación de WhatsApp';
            if (leadPhoneEl) leadPhoneEl.textContent = '';
            if (metaEl) metaEl.textContent = '';
            if (chat) chat.innerHTML = '<div class="wa-loading">Cargando conversación…</div>';

            // Abrir el modal de historial de WhatsApp
            var modal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });

            modal.show();
            console.log('Modal mostrado');

            // Scroll immediately (also runs again on shown.bs.modal)
            setTimeout(waScrollToBottom, 0);

            function waLoadHistory(isSilent) {
                // Cargar historial desde el servidor
                $.ajax({
                    url: 'ajax/whatsapp_historial.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        leadId: leadId,
                        tablaOrigen: tablaOrigen
                    },
                    success: function (resp) {
                        if (!chat) return;

                        if (!resp || !resp.success) {
                            if (!isSilent) chat.innerHTML = '<div class="wa-empty">No se pudo cargar la conversación.</div>';
                            return;
                        }

                        if (resp.lead) {
                            if (leadNameEl) leadNameEl.textContent = resp.lead.full_name ? String(resp.lead.full_name) : 'Conversación de WhatsApp';
                            if (leadPhoneEl) leadPhoneEl.textContent = resp.lead.phone ? ('+' + String(resp.lead.phone)) : '';
                        }

                        // Habilitar composer solo si el lead pidió hablar con agente
                        var handoffEnabled = !!(resp.handoff && resp.handoff.enabled);
                        waSetComposerEnabled(handoffEnabled);
                        if (handoffEnabled) {
                            waBindSendOnce();
                        }

                        if (metaEl) {
                            metaEl.textContent = resp.found && resp.file ? ('Archivo: ' + resp.file) : '';
                        }

                        // Fingerprint to avoid re-rendering identical content (prevents flicker)
                        var fp = '';
                        try {
                            fp = JSON.stringify({ found: !!resp.found, file: resp.file || '', messages: resp.messages || [] });
                        } catch (e) {
                            fp = String(resp.file || '') + '|' + String(resp.found);
                        }
                        if (modalElement._waLastFingerprint && modalElement._waLastFingerprint === fp) {
                            return;
                        }
                        modalElement._waLastFingerprint = fp;

                        if (!resp.found) {
                            if (!isSilent) chat.innerHTML = '<div class="wa-empty">No se encontró conversación para este número.</div>';
                            return;
                        }

                        var msgs = Array.isArray(resp.messages) ? resp.messages : [];
                        if (!msgs.length) {
                            if (!isSilent) chat.innerHTML = '<div class="wa-empty">La conversación está vacía.</div>';
                            return;
                        }

                        var wasNearBottom = waIsNearBottom(chat);

                        var html = '';
                        // Avoid rendering consecutive duplicate messages (common when session files briefly contain repeated entries)
                        var _lastWaKey = null;
                        msgs.forEach(function (m) {
                            var role = (m && m.role) ? String(m.role) : '';
                            var content = (m && m.content !== undefined && m.content !== null) ? String(m.content) : '';
                            content = content.trim();
                            if (!content) return;

                            // Remove internal/system prompts from display (these are not user-facing)
                            if (role === 'system') {
                                var up = (content || '').toUpperCase();
                                if (up.indexOf('IDENTIDAD Y OBJETIVO') === 0 || up.indexOf('DATOS YA OBTENIDOS DEL LEAD') === 0 || up.indexOf('PROMPT') !== -1) {
                                    return; // skip prompt/system message
                                }
                            }

                            // Skip consecutive identical messages to avoid duplicate display
                            var waKey = role + '|' + content;
                            if (waKey === _lastWaKey) return;
                            _lastWaKey = waKey;

                            // Required mapping: "user" = lead. "system" = sent by you.
                            // We also treat "assistant" as sent by you.
                            var kind = 'out';
                            if (role === 'user') {
                                kind = 'in';
                            } else if (role === 'system' && (content.indexOf('IDENTIDAD Y OBJETIVO') === 0 || content.indexOf('Datos ya obtenidos del lead:') === 0)) {
                                kind = 'meta';
                            }

                            if (kind === 'meta') {
                                html += '<div class="wa-row meta"><div class="wa-bubble meta">' + escapeHtml(content) + '</div></div>';
                            } else {
                                html += '<div class="wa-row ' + kind + '"><div class="wa-bubble ' + kind + '">' + escapeHtml(content) + '</div></div>';
                            }
                        });

                        chat.innerHTML = html || '<div class="wa-empty">No hay mensajes para mostrar.</div>';
                        // Scroll to bottom only if user was already at the bottom
                        if (wasNearBottom) {
                            setTimeout(waScrollToBottom, 0);
                        }

                        // Mark as read (debounced) so the badge clears for unread messages.
                        try {
                            if (modalElement._waMarkReadTimer) {
                                clearTimeout(modalElement._waMarkReadTimer);
                            }
                            modalElement._waMarkReadTimer = setTimeout(function () {
                                waMarkRead(leadId, tablaOrigen);
                            }, 600);
                        } catch (e) { }
                    },
                    error: function (xhr) {
                        if (!isSilent && chat) chat.innerHTML = '<div class="wa-empty">Error al cargar la conversación.</div>';
                        console.error('Error AJAX conversación WhatsApp:', xhr && xhr.responseText ? xhr.responseText : xhr);
                    }
                });
            }

            // Primera carga (con UI)
            waLoadHistory(false);
            // Refresco en vivo mientras el modal esté abierto
            waStartPolling(waLoadHistory);
        }, 350);
    }

    function copiarLinkOrganic() {
        var linkInput = document.getElementById('linkOrganicLead');
        linkInput.select();
        linkInput.setSelectionRange(0, 99999);

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(linkInput.value).then(function () {
                document.getElementById('copiadoAlertOrganic').classList.remove('d-none');
                setTimeout(function () {
                    document.getElementById('copiadoAlertOrganic').classList.add('d-none');
                }, 2000);
            }).catch(function () {
                document.execCommand('copy');
                document.getElementById('copiadoAlertOrganic').classList.remove('d-none');
                setTimeout(function () {
                    document.getElementById('copiadoAlertOrganic').classList.add('d-none');
                }, 2000);
            });
        } else {
            document.execCommand('copy');
            document.getElementById('copiadoAlertOrganic').classList.remove('d-none');
            setTimeout(function () {
                document.getElementById('copiadoAlertOrganic').classList.add('d-none');
            }, 2000);
        }
    }

    function copiarLinkVendedora() {
        var linkInput = document.getElementById('linkVendedoraLead');
        if (!linkInput) return;
        linkInput.select();
        linkInput.setSelectionRange(0, 99999);

        function mostrarAlertaVendedora() {
            var alerta = document.getElementById('copiadoAlertVendedora');
            if (!alerta) return;
            alerta.classList.remove('d-none');
            setTimeout(function () { alerta.classList.add('d-none'); }, 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(linkInput.value).then(function () {
                mostrarAlertaVendedora();
            }).catch(function () {
                document.execCommand('copy');
                mostrarAlertaVendedora();
            });
        } else {
            document.execCommand('copy');
            mostrarAlertaVendedora();
        }
    }

    // --- Funcionalidad para registro manual de leads ---
    $(document).ready(function () {
        // Populate country code select for manual lead registration
        (function loadCountryCodes() {
            var selectElement = document.getElementById('leadCountryCode');
            if (!selectElement) return;
            fetch('JS/countries_codes.json?' + new Date().getTime(), { cache: 'no-store' })
                .then(function (resp) {
                    if (!resp.ok) throw new Error('Failed to load countries');
                    return resp.json();
                })
                .then(function (data) {
                    if (!data || !data.countries) return;
                    data.countries.sort(function (a, b) { return a.name.localeCompare(b.name); });
                    data.countries.forEach(function (country) {
                        var opt = document.createElement('option');
                        opt.value = country.code;
                        opt.text = country.code + ' (' + country.name + ')';
                        selectElement.appendChild(opt);
                    });
                })
                .catch(function (err) {
                    // Fall back to basic list
                    var fallback = [
                        { code: '+52', name: 'Mexico' },
                        { code: '+1', name: 'United States' },
                        { code: '+44', name: 'United Kingdom' }
                    ];
                    fallback.forEach(function (country) {
                        var opt = document.createElement('option');
                        opt.value = country.code;
                        opt.text = country.code + ' (' + country.name + ')';
                        selectElement.appendChild(opt);
                    });
                    console.error('Error loading countries:', err);
                });
        })();
        // Quitar formato al pegar en ciertos inputs: insertar solo texto plano en la posición del cursor
        (function () {
            function attachPlainPaste(id) {
                var el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('paste', function (e) {
                    e.preventDefault();
                    var clipboard = (e.clipboardData || window.clipboardData);
                    var text = clipboard && clipboard.getData ? (clipboard.getData('text/plain') || clipboard.getData('text')) : '';
                    // Normalizar espacios y quitar saltos de línea
                    text = text.replace(/\s+/g, ' ').trim();
                    var start = typeof this.selectionStart === 'number' ? this.selectionStart : this.value.length;
                    var end = typeof this.selectionEnd === 'number' ? this.selectionEnd : this.value.length;
                    var val = this.value || '';
                    this.value = val.slice(0, start) + text + val.slice(end);
                    var pos = start + text.length;
                    try { this.setSelectionRange(pos, pos); } catch (err) { }
                });
            }
            // Attach to inputs that should strip formatting on paste
            attachPlainPaste('leadNombre');
            attachPlainPaste('wpCampaignName');
        })();

        // Normalizar texto antes de guardar en BD (quita formato, emojis raros y normaliza compat chars)
        function normalizeForDB(str) {
            if (str === undefined || str === null) return '';
            str = String(str);
            try { str = str.normalize('NFKC'); } catch (e) { }
            // Eliminar caracteres de ancho cero/formatting
            str = str.replace(/[\u200B-\u200F\uFEFF]/g, '');
            // Descomponer y quitar marcas diacríticas
            try {
                str = str.normalize('NFD').replace(/\p{M}/gu, '');
            } catch (e) {
                str = str.replace(/[\u0300-\u036f]/g, '');
            }
            // Quitar símbolos/emoji manteniendo letras, números, puntuación y espacios
            try {
                str = str.replace(/[^\p{L}\p{N}\p{P}\p{Zs}]/gu, '');
            } catch (e) {
                // Fallback más compatible: mantener ASCII básico y algunos acentos comunes
                str = str.replace(/[^\w \-\.,;:()¿?¡!\u00C0-\u017F]/g, '');
            }
            // Normalizar espacios
            str = str.replace(/\s+/g, ' ').trim();
            return str;
        }

        function normalizeManualPhone(phoneDigits, codeDigits) {
            phoneDigits = (phoneDigits || '').replace(/\D/g, '');
            codeDigits = (codeDigits || '').replace(/\D/g, '');

            if (!phoneDigits) {
                return { valid: true, local: '', full: '' };
            }

            if (phoneDigits.length >= 10) {
                return {
                    valid: true,
                    local: phoneDigits,
                    full: codeDigits ? (codeDigits + phoneDigits) : phoneDigits
                };
            }

            if (codeDigits && phoneDigits.indexOf(codeDigits) === 0) {
                var localDigits = phoneDigits.slice(codeDigits.length);
                if (localDigits.length >= 10) {
                    return {
                        valid: true,
                        local: localDigits,
                        full: codeDigits + localDigits
                    };
                }
            }

            return { valid: false, local: phoneDigits, full: phoneDigits };
        }

        var tipoIGRules = {
            'organico': {
                origenes: [],
                medios: [{ value: 'ig', label: 'IG' }],
                medioPorOrigen: {}
            },
            'campana': {
                origenes: [
                    { value: 'b1 (USA)', label: 'Chat B1 (USA)' },
                    { value: 'b2 (MX)', label: 'Chat B2 (Mex)' },
                    { value: 'b3 (Mex 2)', label: 'Chat B3 (Mex 2)' },
                    { value: 'b4 (Latam)', label: 'Chat B4 (Latam)' }
                ],
                medios: [
                    { value: 'ig usa', label: 'IG USA' },
                    { value: 'ig mexico', label: 'IG México' },
                    { value: 'ig mex 2', label: 'IG Mex 2' },
                    { value: 'ig latam', label: 'IG Latam' }
                ],
                medioPorOrigen: {
                    'b1 (USA)': 'ig usa',
                    'b2 (MX)': 'ig mexico',
                    'b3 (Mex 2)': 'ig mex 2',
                    'b4 (Latam)': 'ig latam'
                }
            }
        };

        var manualLeadChannelRules = {
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

        function populateManualLeadSelect($select, options, currentValue, placeholder) {
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

        function toggleManualLeadAutoField($fieldWrap, $select, options) {
            var optionCount = (options || []).length;
            var shouldHide = optionCount <= 1;

            $fieldWrap.toggle(!shouldHide);

            if (shouldHide) {
                $select.removeClass('is-invalid');
            }
        }

        function resolveManualLeadMedio(firstContactChannel, origenValue, currentMedio) {
            var rule;
            if (firstContactChannel === 'IG') {
                var tipoIG = $('#leadTipoIG').val() || '';
                rule = tipoIGRules[tipoIG] || null;
            } else {
                rule = manualLeadChannelRules[firstContactChannel] || null;
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

        function syncManualLeadMedio() {
            var firstContactChannel = $('#leadFirstContactChannel').val() || '';
            var origenValue = $('#leadOrigen').val() || '';
            var $medio = $('#leadMedio');
            var resolvedMedio = resolveManualLeadMedio(firstContactChannel, origenValue, $medio.val() || '');

            $medio.val(resolvedMedio);

            if (resolvedMedio) {
                $medio.removeClass('is-invalid');
            }

            return resolvedMedio;
        }

        function updateManualLeadOrigenFromTipoIG(resetSelection) {
            var tipoIG = $('#leadTipoIG').val() || '';
            var rule = tipoIGRules[tipoIG] || null;
            var $origen = $('#leadOrigen');
            var $origenFieldWrap = $('#leadOrigenFieldWrap');
            var currentOrigen = resetSelection ? '' : ($origen.val() || '');

            if (!rule) {
                $origenFieldWrap.hide();
                populateManualLeadSelect($origen, [], '', 'Seleccionar...');
                $origen.prop('disabled', true).removeClass('is-invalid');
                syncManualLeadMedio();
                return;
            }

            populateManualLeadSelect($origen, rule.origenes, currentOrigen, 'Seleccionar...');
            $origen.removeClass('is-invalid');
            toggleManualLeadAutoField($origenFieldWrap, $origen, rule.origenes);
            syncManualLeadMedio();
        }

        function updateManualLeadDependentFields(resetSelection) {
            var firstContactChannel = $('#leadFirstContactChannel').val() || '';
            var $origen = $('#leadOrigen');
            var $medio = $('#leadMedio');
            var $origenFieldWrap = $('#leadOrigenFieldWrap');
            var $medioFieldWrap = $('#leadMedioFieldWrap');
            var $tipoIGFieldWrap = $('#leadTipoIGFieldWrap');
            var $tipoIG = $('#leadTipoIG');

            if (firstContactChannel === 'IG') {
                $tipoIGFieldWrap.show();
                if (resetSelection) {
                    $tipoIG.val('').removeClass('is-invalid');
                }
                updateManualLeadOrigenFromTipoIG(resetSelection);
                $medioFieldWrap.hide();
                return;
            }

            // No es IG: ocultar y resetear campos IG
            $tipoIGFieldWrap.hide();
            $tipoIG.val('').removeClass('is-invalid');

            var rule = manualLeadChannelRules[firstContactChannel] || null;
            var currentOrigen = resetSelection ? '' : ($origen.val() || '');
            var currentMedio = resetSelection ? '' : ($medio.val() || '');

            if (!rule) {
                populateManualLeadSelect($origen, [], '', 'Selecciona primero el canal...');
                populateManualLeadSelect($medio, [], '', 'Selecciona primero el canal...');
                $origen.prop('disabled', true).removeClass('is-invalid');
                $medio.prop('disabled', true).removeClass('is-invalid');
                $origenFieldWrap.hide();
                $medioFieldWrap.hide();
                return;
            }

            populateManualLeadSelect($origen, rule.origenes, currentOrigen, 'Seleccionar...');
            populateManualLeadSelect($medio, rule.medios, currentMedio, 'Seleccionar...');
            $origen.removeClass('is-invalid');
            $medio.removeClass('is-invalid');
            toggleManualLeadAutoField($origenFieldWrap, $origen, rule.origenes);
            $medioFieldWrap.hide();
            syncManualLeadMedio();
        }

        function syncManualLeadChoiceCards(targetSelector) {
            var $input = $(targetSelector);
            var value = $input.val() || '';
            var $cards = $('.rlm-choice-card[data-target="' + targetSelector + '"]');

            $cards.removeClass('active').attr('aria-pressed', 'false');
            if (value) {
                $cards.filter('[data-value="' + value.replace(/"/g, '\\"') + '"]').addClass('active').attr('aria-pressed', 'true');
            }
        }

        // ── Auto-asignar how_did_you_meet según tipo_cliente + how_long_known_us ──
        function updateAutoHowDidYouMeet() {
            var tipoCliente   = $('#leadTipoCliente').val()    || '';
            var howLong       = $('#leadHowLongKnownUs').val() || '';
            var howDidYouMeet = '';
            if (tipoCliente === 'Wedding Planner') {
                howDidYouMeet = '1'; // Wedding Planner
            } else if (tipoCliente === 'Cliente Final') {
                if (howLong === 'Less than 6 months') {
                    howDidYouMeet = '3'; // New Audience
                } else if (howLong === 'More than 6 months') {
                    howDidYouMeet = '2'; // Community
                }
            }
            $('#leadHowDidYouMeet').val(howDidYouMeet);
        }

        // ── Helpers de formato para actualizar la fila en el DOM sin recargar ──
        window.jsFormatCreatedTime = function jsFormatCreatedTime(dtStr) {
            if (!dtStr) return '';
            var d = new Date(dtStr.replace(' ', 'T'));
            if (isNaN(d.getTime())) return dtStr;
            var day = d.getDate() < 10 ? '0' + d.getDate() : String(d.getDate());
            var month = (d.getMonth() + 1) < 10 ? '0' + (d.getMonth() + 1) : String(d.getMonth() + 1);
            var year = d.getFullYear();
            var h = d.getHours();
            var m = d.getMinutes();
            var ampm = h >= 12 ? 'pm' : 'am';
            var h12 = h % 12 || 12;
            var hh = h12 < 10 ? '0' + h12 : String(h12);
            var mm = m < 10 ? '0' + m : String(m);
            return day + '/' + month + '/' + year + ' ' + hh + ':' + mm + ' ' + ampm;
        }
        window.jsFormatLeadDate = function jsFormatLeadDate(dtStr, notDefined) {
            if (notDefined) return 'Sin definir';
            if (!dtStr) return '—';
            // Para 'YYYY-MM-DD' parseamos manualmente para evitar el desfase de zona horaria
            var isoDate = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(dtStr).trim());
            if (isoDate) {
                return isoDate[3] + '/' + isoDate[2] + '/' + isoDate[1];
            }
            var d = new Date(dtStr.replace(' ', 'T'));
            if (isNaN(d.getTime())) return dtStr || '—';
            var dd = d.getDate() < 10 ? '0' + d.getDate() : d.getDate();
            var mm = (d.getMonth() + 1) < 10 ? '0' + (d.getMonth() + 1) : (d.getMonth() + 1);
            return dd + '/' + mm + '/' + d.getFullYear();
        }
        window.jsNormalizeContactChannel = function jsNormalizeContactChannel(val) {
            if (!val) return val;
            var k = val.toLowerCase().replace(/[–—]/g, '-').replace(/\s+/g, ' ').trim();
            var map = {
                'whatsapp': 'WhatsApp',
                'instagram dm - campaign': 'Instagram DM - Campaña',
                'instagram dm - organic': 'Instagram DM - Orgánico',
                'fb': 'Facebook',
                'facebook': 'Facebook',
                'email': 'Correo electrónico',
                'mail': 'Correo electrónico',
                'phone call': 'Llamada telefónica',
                'phone': 'Llamada telefónica'
            };
            return map[k] || val;
        }

        // Variable para retener el objeto lead original mientras se edita
        var _currentEditingLead = null;

        // ── Editar lead manual: poblar el modal con datos existentes ──
        // Asignadas a window para que los onclick inline puedan llamarlas
        window.editarLeadManual = function editarLeadManual(lead) {
            _currentEditingLead = lead;  // guardar referencia para el DOM update
            // Marcar modo edición
            $('#leadEditId').val(lead['id'] || '');

            // Poblar campos de texto
            $('#leadNombre').val(lead['full_name'] || '');
            $('#leadCorreo').val(lead['email'] || '');
            $('#leadCity').val(lead['city'] || '');
            $('#leadWeddingLocation').val(lead['wedding_location'] || '');
            // wedding_date: 'YYYY-MM-DD' → input[type=date]
            var weddingDateNotDefined = lead['wedding_date_not_defined'] == 1 || lead['wedding_date_not_defined'] === '1' || lead['wedding_date_not_defined'] === true;
            $('#leadWeddingDateNotDefined').prop('checked', weddingDateNotDefined);
            $('#leadWeddingDate').val(weddingDateNotDefined ? '' : (lead['wedding_date'] ? String(lead['wedding_date']).substring(0, 10) : ''));
            syncLeadWeddingDateNotDefinedUI();

            // Teléfono: separar código de país y número local (últimos 10 dígitos)
            var rawPhone = String(lead['phone'] || '').replace(/\D/g, '');
            if (rawPhone.length > 10) {
                var localPart = rawPhone.slice(-10);
                var countryPart = rawPhone.slice(0, rawPhone.length - 10);
                var codeWithPlus = '+' + countryPart;
                $('#leadTelefono').val(localPart);
                // Intentar seleccionar opción del select (puede no estar cargada aún)
                (function trySetCode(attempts) {
                    setTimeout(function () {
                        var $cc = $('#leadCountryCode');
                        if ($cc.find('option[value="' + codeWithPlus + '"]').length) {
                            $cc.val(codeWithPlus);
                        } else if (attempts > 0) {
                            trySetCode(attempts - 1);
                        }
                    }, 250);
                })(8);
            } else if (rawPhone.length > 0) {
                $('#leadTelefono').val(rawPhone);
            } else {
                $('#leadTelefono').val('');
            }

            // Primer canal de contacto + origen + medio — poblar directamente con el valor guardado
            var _editChannel  = lead['first_contact_channel'] || '';
            var _editCampaign = lead['campaign_name'] || '';
            var _editTipoIG   = lead['tipo_ig'] || '';

            // Backward compat: leads legacy con 'Instagram DM – Campaign' / 'Instagram DM – Organic'
            if (_editChannel.indexOf('Instagram') !== -1 && _editChannel.indexOf('Campaign') !== -1) {
                _editChannel = 'IG';
                _editTipoIG = 'campana';
            } else if (_editChannel.indexOf('Instagram') !== -1 && _editChannel.indexOf('Organic') !== -1) {
                _editChannel = 'IG';
                _editTipoIG = 'organico';
            }

            $('#leadFirstContactChannel').val(_editChannel);

            var $_editOrigen     = $('#leadOrigen');
            var $_editMedio      = $('#leadMedio');
            var $_editOrigenWrap = $('#leadOrigenFieldWrap');
            var $_editMedioWrap  = $('#leadMedioFieldWrap');
            var $_editTipoIGWrap = $('#leadTipoIGFieldWrap');
            var $_editTipoIG     = $('#leadTipoIG');

            if (_editChannel === 'IG') {
                $_editTipoIGWrap.show();
                $_editTipoIG.val(_editTipoIG).removeClass('is-invalid');
                var _igRule = tipoIGRules[_editTipoIG] || null;
                if (_igRule) {
                    populateManualLeadSelect($_editOrigen, _igRule.origenes, _editCampaign, 'Seleccionar...');
                    $_editOrigen.removeClass('is-invalid');
                    toggleManualLeadAutoField($_editOrigenWrap, $_editOrigen, _igRule.origenes);
                } else {
                    $_editOrigenWrap.hide();
                    populateManualLeadSelect($_editOrigen, [], '', 'Seleccionar...');
                    $_editOrigen.prop('disabled', true).removeClass('is-invalid');
                }
                $_editMedioWrap.hide();
                syncManualLeadMedio();
            } else {
                $_editTipoIGWrap.hide();
                $_editTipoIG.val('').removeClass('is-invalid');
                var _editRule = manualLeadChannelRules[_editChannel] || null;
                if (_editRule) {
                    // Pasar _editCampaign como currentValue:
                    //  - canal 1 opción (WhatsApp/Email/Phone): se auto-selecciona y deshabilita, fieldWrap se oculta
                    populateManualLeadSelect($_editOrigen, _editRule.origenes, _editCampaign, 'Seleccionar...');
                    populateManualLeadSelect($_editMedio,  _editRule.medios,   '',            'Seleccionar...');
                    $_editOrigen.removeClass('is-invalid');
                    $_editMedio.removeClass('is-invalid');
                    toggleManualLeadAutoField($_editOrigenWrap, $_editOrigen, _editRule.origenes);
                    $_editMedioWrap.hide();
                    syncManualLeadMedio();
                } else {
                    populateManualLeadSelect($_editOrigen, [], '', 'Selecciona primero el canal...');
                    populateManualLeadSelect($_editMedio,  [], '', 'Selecciona primero el canal...');
                    $_editOrigen.prop('disabled', true).removeClass('is-invalid');
                    $_editMedio.prop('disabled',  true).removeClass('is-invalid');
                    $_editOrigenWrap.hide();
                    $_editMedioWrap.hide();
                }
            }

            // how_long_known_us: valor en hidden input y activar card
            $('#leadHowLongKnownUs').val(lead['how_long_known_us'] || '');
            syncManualLeadChoiceCards('#leadHowLongKnownUs');
            // how_did_you_meet: cargar valor existente
            $('#leadHowDidYouMeet').val(lead['how_did_you_meet'] || '');

            // created_time → datetime-local input (YYYY-MM-DDTHH:MM)
            var ct = String(lead['created_time'] || '');
            if (ct) {
                $('#leadFechaRegistro').val(ct.replace(' ', 'T').substring(0, 16));
            } else {
                $('#leadFechaRegistro').val('');
            }

            $('#leadComentarios').val(lead['registro_notas'] || lead['comentarios'] || '');

            // Cambiar título y botón del modal
            $('#registroManualModalLabel').text('Editar Lead Manual');
            $('#registroManualModal .rlm-subtitle').text('Modifica los datos del lead #' + (lead['id'] || ''));
            $('#btnGuardarLeadManual').html('<i class="bi bi-save"></i> Actualizar Lead');

            // Abrir modal
            var modal = new bootstrap.Modal(document.getElementById('registroManualModal'));
            modal.show();
        }

        // Versión que cierra verMasModal antes de abrir el modal de edición
        window.editarLeadManualAndCloseModal = function editarLeadManualAndCloseModal(lead) {
            try {
                var verMasModal = bootstrap.Modal.getInstance(document.getElementById('verMasModal'));
                if (verMasModal) verMasModal.hide();
            } catch (e) {}
            setTimeout(function () { editarLeadManual(lead); }, 300);
        }

        $(document).off('click.btnEditarLead').on('click.btnEditarLead', '.btn-editar-lead', function () {
            var payload = $(this).attr('data-lead-payload') || '';
            if (!payload) {
                return;
            }

            try {
                editarLeadManualAndCloseModal(JSON.parse(decodeURIComponent(payload)));
            } catch (error) {
                console.error('No se pudo abrir el editor del lead.', error);
                Swal.fire('Error', 'No se pudo abrir la informacion del lead para editar.', 'error');
            }
        });

        // Guardar lead manual
        $('#btnGuardarLeadManual').on('click', function () {
            var form = document.getElementById('formLeadManual');
            var isValid = true;
            var invalidFields = [];

            // Resetear validaciones
            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });

            // Validar nombre (obligatorio) — normalizar antes de validar/guardar
            var nombreRaw = $('#leadNombre').val();
            var nombre = normalizeForDB(nombreRaw);
            if (!nombre) {
                $('#leadNombre').addClass('is-invalid');
                isValid = false;
                invalidFields.push('Nombre completo');
            }

            // Validar correo (opcional pero si tiene valor debe ser válido)
            var correo = $('#leadCorreo').val().trim();
            if (correo && !isValidEmail(correo)) {
                $('#leadCorreo').addClass('is-invalid');
                isValid = false;
                invalidFields.push('Correo electrónico válido');
            }

            // Validar tipo_cliente (obligatorio)
            var tipoCliente = $('#leadTipoCliente').val();
            if (!tipoCliente) {
                $('#leadTipoClienteGroup').addClass('is-invalid');
                isValid = false;
                invalidFields.push('Tipo de cliente');
            }

            // Validar first_contact_channel (obligatorio)
            var firstContactChannel = $('#leadFirstContactChannel').val();
            if (!firstContactChannel) {
                $('#leadFirstContactChannel').addClass('is-invalid');
                isValid = false;
                invalidFields.push('Primer canal de contacto');
            }

            // Validar tipo_ig (obligatorio si canal es IG)
            var tipoIG = '';
            if (firstContactChannel === 'IG') {
                tipoIG = $('#leadTipoIG').val() || '';
                if (!tipoIG) {
                    $('#leadTipoIG').addClass('is-invalid');
                    isValid = false;
                    invalidFields.push('Tipo de IG');
                }
            }

            // Validar origen (obligatorio)
            // Para canales de una sola opción el select está disabled → leer valor nativo del DOM.
            // Como último recurso, resolverlo desde las reglas del canal.
            var _origenEl = document.getElementById('leadOrigen');
            var origen = (_origenEl ? _origenEl.value : '') || '';
            if (!origen && firstContactChannel) {
                var _ruleForOrigen = firstContactChannel === 'IG'
                    ? (tipoIGRules[tipoIG] || null)
                    : (manualLeadChannelRules[firstContactChannel] || null);
                if (_ruleForOrigen && _ruleForOrigen.origenes && _ruleForOrigen.origenes.length === 1) {
                    origen = _ruleForOrigen.origenes[0].value;
                    // Asegurarse de que el select también tenga el valor para el envío
                    if (_origenEl) _origenEl.value = origen;
                }
            }
            var origenRequired = !(firstContactChannel === 'IG' && tipoIG === 'organico');
            if (origenRequired && !origen) {
                $('#leadOrigen').addClass('is-invalid');
                isValid = false;
                invalidFields.push('Origen');
            }

            // Validar medio (obligatorio)
            // Para canales de una sola opción también resolver desde reglas si syncManualLeadMedio no devuelve nada
            var medio = syncManualLeadMedio();
            if (!medio && firstContactChannel && origen) {
                var _ruleForMedio = firstContactChannel === 'IG'
                    ? (tipoIGRules[tipoIG] || null)
                    : (manualLeadChannelRules[firstContactChannel] || null);
                if (_ruleForMedio && _ruleForMedio.medioPorOrigen && _ruleForMedio.medioPorOrigen[origen]) {
                    medio = _ruleForMedio.medioPorOrigen[origen];
                    var _medioEl = document.getElementById('leadMedio');
                    if (_medioEl) _medioEl.value = medio;
                } else if (_ruleForMedio && _ruleForMedio.medios && _ruleForMedio.medios.length === 1) {
                    medio = _ruleForMedio.medios[0].value;
                    var _medioEl2 = document.getElementById('leadMedio');
                    if (_medioEl2) _medioEl2.value = medio;
                }
            }
            if (!medio) {
                isValid = false;
                invalidFields.push('Medio');
            }

            // Validar how_long_known_us (obligatorio)
            var howLongKnownUs = $('#leadHowLongKnownUs').val();
            if (!howLongKnownUs) {
                $('#leadHowLongKnownUsGroup').addClass('is-invalid');
                isValid = false;
                invalidFields.push('¿Desde hace cuánto nos conoces?');
            }

            // Validar teléfono (si se proporciona)
            var selectedCode = $('#leadCountryCode').val() || '';
            var codeDigits = (selectedCode || '').replace(/\D/g, '');
            var phoneVal = $('#leadTelefono').val() ? $('#leadTelefono').val().trim() : '';
            var phoneDigits = phoneVal.replace(/\D/g, '');
            var normalizedPhone = normalizeManualPhone(phoneDigits, codeDigits);
            if (phoneDigits.length > 0) {
                // Si hay teléfono, el código de país es obligatorio
                if (!selectedCode || selectedCode.trim() === '') {
                    $('#leadCountryCode').addClass('is-invalid');
                    isValid = false;
                    invalidFields.push('Código de país');
                }

                // Permitir teléfono local de mínimo 10 dígitos o número completo con código de país seleccionado
                if (!normalizedPhone.valid) {
                    $('#leadTelefono').addClass('is-invalid');
                    isValid = false;
                    invalidFields.push('Teléfono (mínimo 10 dígitos)');
                }
            }

            if (!isValid) {
                Swal.fire({
                    toast: true,
                    position: 'bottom-end',
                    icon: 'warning',
                    title: invalidFields.length ? ('Revisa: ' + Array.from(new Set(invalidFields)).join(', ')) : 'Por favor complete los campos obligatorios',
                    showConfirmButton: false,
                    timer: 4000
                });
                return;
            }

            // Deshabilitar botón y mostrar loading
            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...');

            // build telefono final: prefix selected country code stripped of '+' then concat with phone digits
            var selectedCode = $('#leadCountryCode').val() || '';
            var codeDigits = (selectedCode || '').replace(/\D/g, '');
            var phoneVal = $('#leadTelefono').val() ? $('#leadTelefono').val().trim() : '';
            var phoneDigits = phoneVal.replace(/\D/g, '');
            var normalizedPhone = normalizeManualPhone(phoneDigits, codeDigits);
            var finalPhone = normalizedPhone.full || '';
            var localPhone = normalizedPhone.local || '';

            var editLeadId = parseInt($('#leadEditId').val() || '0');

            $.ajax({
                url: 'guardar_lead_manual.php',
                type: 'POST',
                data: {
                    lead_id: editLeadId || '',
                    nombre: nombre,
                    correo: correo,
                    telefono: finalPhone,
                    telefono_local: localPhone,
                    country_code: selectedCode,
                    city: ($('#leadCity').val() || '').trim(),
                    wedding_location: ($('#leadWeddingLocation').val() || '').trim(),
                    wedding_date: ($('#leadWeddingDate').val() || '').trim(),
                    wedding_date_not_defined: $('#leadWeddingDateNotDefined').is(':checked') ? 1 : 0,
                    campaign_name: origen,
                    platform: medio,
                    how_long_known_us: howLongKnownUs,
                    how_did_you_meet: $('#leadHowDidYouMeet').val() || '',
                    tipo_cliente: tipoCliente,
                    first_contact_channel: firstContactChannel,
                    tipo_ig: tipoIG,
                    fecha_registro: $('#leadFechaRegistro').val(),
                    comentarios: ($('#leadComentarios').val() || '').trim()
                },
                dataType: 'json',
                success: function (resp) {
                    if (resp.success) {
                        // Capturar valores ANTES de cerrar el modal y limpiar el formulario
                        var _snapCity          = ($('#leadCity').val() || '').trim();
                        var _snapWeddingLoc    = ($('#leadWeddingLocation').val() || '').trim();
                        var _snapWeddingDate   = ($('#leadWeddingDate').val() || '').trim();
                        var _snapWeddingDateNotDefined = $('#leadWeddingDateNotDefined').is(':checked');
                        var _snapFechaRegistro = ($('#leadFechaRegistro').val() || '').trim();
                        var _snapEditingLead   = _currentEditingLead;

                        // Cerrar modal de registro
                        var registroModal = bootstrap.Modal.getInstance(document.getElementById('registroManualModal'));
                        registroModal.hide();

                        // Limpiar formulario
                        form.reset();
                        $('#leadEditId').val('');

                        if (resp.mode === 'update') {
                            // ── Actualizar la fila en el DOM sin recargar ──
                            var updatedCity        = _snapCity;
                            var updatedWeddingLoc  = _snapWeddingLoc;
                            var updatedWeddingDate = _snapWeddingDate;
                            var updatedWeddingDateNotDefined = _snapWeddingDateNotDefined;
                            // Si no cambió la fecha, usar la del lead original
                            var updatedCreatedTime = _snapFechaRegistro ||
                                (_snapEditingLead ? (_snapEditingLead['created_time'] || '') : '');

                            // Buscar la fila por data-attributes (más confiable con DataTables scrollX)
                            var editTabla = _snapEditingLead ? (_snapEditingLead['tabla_origen'] || 'organic_leads') : 'organic_leads';
                            var $row = $('tr[data-lead-id="' + editLeadId + '"][data-tabla="' + editTabla + '"]');
                            if (!$row.length) {
                                // Fallback al selector de ID
                                $row = $('#lead-row-' + editLeadId + '-' + editTabla);
                            }
                            if ($row.length) {
                                $row.find('td[data-column="nombre"] .td-name').text(nombre);
                                var updatedFechaRegistro = jsFormatCreatedTime(updatedCreatedTime) || '—';
                                $row.find('td[data-column="fecha_registro"]').text(updatedFechaRegistro);
                                $row.find('td[data-column="estatus"] .td-sub-fecha-registro').text(updatedFechaRegistro);
                                $row.find('td[data-column="campana"]').html(jsRenderCampaignBadge(jsFormatCampaignDisplay(origen || '')));
                                $row.find('td[data-column="boda"]').text(updatedWeddingLoc || '—');
                                $row.find('td[data-column="cuando_se_casa"]').text(jsFormatLeadDate(updatedWeddingDate, updatedWeddingDateNotDefined));
                                $row.find('td[data-column="ciudad_origen"]').text(updatedCity || '—');
                                $row.find('td[data-column="metodo_contacto"]').html(jsRenderContactMethodBadge(jsNormalizeContactChannel(firstContactChannel), jsContactChannelDisplayLabel(jsNormalizeContactChannel(firstContactChannel))));
                                $row.find('td[data-column="tipo_cliente"]').html(jsRenderTipoClienteBadge(tipoCliente || '—'));
                                $row.find('td[data-column="desde_conoce"]').html(jsKnownUsBadge(howLongKnownUs || 'Not asked'));
                                // Actualizar atributos del tr
                                $row
                                    .attr('data-full-name', nombre)
                                    .attr('data-email', correo)
                                    .attr('data-campaign-name', origen)
                                    .attr('data-contact-method', jsNormalizeContactChannel(firstContactChannel))
                                    .attr('data-known-us', jsNormalizeKnownUsLabel(howLongKnownUs || 'Not asked'));
                                // Reconstruir objeto lead actualizado para el onclick del botón Editar
                                var updatedLead = $.extend({}, _snapEditingLead, {
                                    full_name:            nombre,
                                    email:                correo,
                                    phone:                finalPhone,
                                    city:                 updatedCity,
                                    wedding_location:     updatedWeddingLoc,
                                    wedding_date:         updatedWeddingDate,
                                    wedding_date_not_defined: updatedWeddingDateNotDefined ? 1 : 0,
                                    campaign_name:        origen,
                                    platform:             medio,
                                    first_contact_channel: firstContactChannel,
                                    tipo_ig:              tipoIG,
                                    how_long_known_us:    howLongKnownUs,
                                    tipo_cliente:         tipoCliente,
                                    created_time:         updatedCreatedTime.replace('T', ' ')
                                });
                                $row.find('.btn-editar-lead').attr('data-lead-payload', encodeURIComponent(JSON.stringify(updatedLead)));
                                // Notificar a DataTables que la fila cambió
                                try {
                                    var dt = $('#leadsTable').DataTable();
                                    dt.row($row).invalidate('dom').draw(false);
                                } catch (e) { /* DataTables no disponible */ }
                            }

                            Swal.fire({
                                toast: true,
                                position: 'bottom-end',
                                icon: 'success',
                                title: 'Lead actualizado correctamente',
                                showConfirmButton: false,
                                timer: 2500
                            });
                            _currentEditingLead = null;
                        } else {
                            // Modo creación: mostrar links del formulario (cliente y vendedora)
                            verLinkFormulario('organic_leads', resp.id, {
                                title: 'Lead registrado correctamente',
                                introHtml: '<p class="text-center mb-3">El lead ha sido registrado. Comparte el link correspondiente:</p>',
                                onClose: function () {
                                    location.reload();
                                }
                            });
                        }
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: resp.message || 'No se pudo guardar el lead',
                            icon: 'error'
                        });
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error:', error);
                    showErrorModalFromResponse(null, xhr);
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="bi bi-save"></i> Guardar Lead');
                }
            });
        });

        function isValidEmail(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }

        function syncLeadWeddingDateNotDefinedUI() {
            var notDefined = $('#leadWeddingDateNotDefined').is(':checked');
            $('#leadWeddingDate').prop('disabled', notDefined);
            if (notDefined) {
                $('#leadWeddingDate').val('');
            }
        }

        $('#leadWeddingDateNotDefined').off('change.leadWeddingDateNotDefined').on('change.leadWeddingDateNotDefined', syncLeadWeddingDateNotDefinedUI);
        $('#leadWeddingDate').off('change.leadWeddingDateInput').on('change.leadWeddingDateInput', function () {
            if ($(this).val()) {
                $('#leadWeddingDateNotDefined').prop('checked', false);
                $(this).prop('disabled', false);
            }
        });

        // Limpiar validaciones al cerrar modal
        $('#registroManualModal').on('hidden.bs.modal', function () {
            var form = document.getElementById('formLeadManual');
            form.reset();
            $('#leadWeddingDate').prop('disabled', false);
            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });
            $('#leadHowLongKnownUsGroup').removeClass('is-invalid');
            $('#leadTipoClienteGroup').removeClass('is-invalid');
            // Resetear modo edición
            $('#leadEditId').val('');
            // Resetear campo IG
            $('#leadTipoIG').val('');
            $('#leadTipoIGFieldWrap').hide();
            // Restaurar título y botón originales
            $('#registroManualModalLabel').text('Registrar Lead Manual');
            $('#registroManualModal .rlm-subtitle').text('Completa los datos para crear un nuevo lead en el sistema');
            $('#btnGuardarLeadManual').html('<i class="bi bi-save"></i> Guardar Lead');
            syncManualLeadChoiceCards('#leadHowLongKnownUs');
            syncManualLeadChoiceCards('#leadTipoCliente');
            updateManualLeadDependentFields(true);
        });

        updateManualLeadDependentFields(true);
        syncManualLeadChoiceCards('#leadHowLongKnownUs');
        syncManualLeadChoiceCards('#leadTipoCliente');

        $('#leadTipoCliente').off('change.manualLeadCards').on('change.manualLeadCards', function () {
            $('#leadTipoClienteGroup').removeClass('is-invalid');
            syncManualLeadChoiceCards('#leadTipoCliente');
            updateAutoHowDidYouMeet();
        });

        $('#leadFirstContactChannel').off('change.manualLeadDeps').on('change.manualLeadDeps', function () {
            $(this).removeClass('is-invalid');
            updateManualLeadDependentFields(true);
        });

        $('#leadOrigen').off('change.manualLeadValidation').on('change.manualLeadValidation', function () {
            if ($(this).val()) {
                $(this).removeClass('is-invalid');
            }
            syncManualLeadMedio();
        });

        $('#leadTipoIG').off('change.manualLeadDeps').on('change.manualLeadDeps', function () {
            $(this).removeClass('is-invalid');
            updateManualLeadOrigenFromTipoIG(true);
        });

        $(document).off('click.manualLeadChoiceCard').on('click.manualLeadChoiceCard', '.rlm-choice-card', function () {
            if ($(this).prop('disabled')) {
                return;
            }
            var targetSelector = $(this).data('target');
            var value = $(this).data('value');
            if (!targetSelector) {
                return;
            }

            $(targetSelector).val(value).trigger('change');
        });

        $('#leadHowLongKnownUs').off('change.manualLeadCards').on('change.manualLeadCards', function () {
            $('#leadHowLongKnownUsGroup').removeClass('is-invalid');
            syncManualLeadChoiceCards('#leadHowLongKnownUs');
            updateAutoHowDidYouMeet();
        });

        // Limpia validaciones sobre input/changes para telefono y country code
        $('#leadTelefono').off('input change').on('input change', function () {
            var v = $(this).val() ? $(this).val().trim() : '';
            if (v && v.replace(/\D/g, '').length >= 10) {
                $(this).removeClass('is-invalid');
                $('#leadCountryCode').removeClass('is-invalid');
            }
        });
        $('#leadCountryCode').off('change').on('change', function () {
            var sel = $(this).val() || '';
            if (sel && ($('#leadTelefono').val() || '').replace(/\D/g, '').length >= 10) {
                $(this).removeClass('is-invalid');
            }
        });



        $('#btnGuardarWeddingPlanner').on('click', function () {
            var isValid = true;
            $('#formWPManual').find('.is-invalid').removeClass('is-invalid');

            var campaignRaw = $('#wpCampaignName').val();
            var fullNameRaw = $('#wpFullName').val();
            var campaign = normalizeForDB(campaignRaw) || '';
            var fullName = normalizeForDB(fullNameRaw) || '';
            var asesor = $('#wpAsesor').val();
            var modo = $('#wpMode').val();

            if (!campaign) { $('#wpCampaignName').addClass('is-invalid'); isValid = false; }
            if (!fullName) { $('#wpFullName').addClass('is-invalid'); isValid = false; }
            if (!asesor) { $('#wpAsesor').addClass('is-invalid'); isValid = false; }
            if (!modo) { $('#wpMode').addClass('is-invalid'); isValid = false; }
            // Allowed modes: 'asistencia_post_q' => full workflow, 'intencion_compra_pre_q' => only wedding_planners
            var allowedModes = ['asistencia_post_q', 'intencion_compra_pre_q'];
            if (modo && allowedModes.indexOf(modo) === -1) { $('#wpMode').addClass('is-invalid'); isValid = false; }

            // No date/time fields are required nor sent; nothing to validate here
            var date_apt = null;
            var time_apt = null;
            if (!isValid) {
                Swal.fire({ icon: 'warning', title: 'Complete los campos obligatorios' });
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...');

            var endpoint = (modo === 'intencion_compra_pre_q') ? 'guardar_wedding_planner_only.php' : 'guardar_wedding_planner_manual.php';

            $.ajax({
                url: endpoint,
                type: 'POST',
                dataType: 'json',
                data: {
                    campaign_name: campaign,
                    where_is: $('#wpWeddingLocation').val(),
                    when_are: $('#wpWeddingDate').val(),
                    full_name: fullName,
                    id_vendedor_asignado: asesor,
                    modo: modo
                },
                success: function (resp) {
                    if (resp && resp.success) {
                        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('registroManualWeddingModal'));
                        modal.hide();
                        var successText = (modo === 'intencion_compra_pre_q') ? 'Wedding Planner registrado' : 'Wedding Planner registrado y cita agendada';
                        Swal.fire({ icon: 'success', title: 'Guardado', text: successText }).then(function () { location.reload(); });
                    } else {
                        Swal.fire('Error', resp.message || 'No se pudo guardar', 'error');
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
    });

    // ========================================
    // SISTEMA DE FILTROS POR COLUMNA
    // ========================================

    var columnFilters         = {}; // { colName: [val1, …] } — filtros activos
    var currentFilterDropdown = null;
    var leadsTableDT          = null; // DataTables instance (asignado en document.ready)

    // Aplica show/hide a cada fila según los filtros activos (sin side-effects adicionales)
    function getLeadTableRows() {
        // Excluir la fila placeholder de DataTables ("No hay datos…") que no tiene data-lead-id
        return $('#leadsTable tbody tr[data-lead-id]');
    }

    function _applyFilterVisibility() {
        var activeCols = Object.keys(columnFilters);
        getLeadTableRows().each(function () {
            var $row    = $(this);
            var visible = true;
            for (var i = 0; i < activeCols.length; i++) {
                var col   = activeCols[i];
                var $cell = $row.find('td[data-column="' + col + '"]');
                if (!$cell.length) { continue; }
                var val = $cell.text().trim();
                if (col === 'estatus') { val = $cell.find('.status').first().text().trim(); }
                if (col === 'metodo_contacto') { val = jsNormalizeContactChannel(val); }
                if (columnFilters[col].indexOf(val) === -1) { visible = false; break; }
            }
            $row.toggle(visible);
        });
    }

    // Recoge valores únicos de una columna (sobre todos los rows, no sólo visibles)
    function getUniqueColumnValues(columnName) {
        var seen   = {};
        var result = [];
        getLeadTableRows().each(function () {
            var $cell = $(this).find('td[data-column="' + columnName + '"]');
            if (!$cell.length) { return; }
            var text = $cell.text().trim();
            if (columnName === 'estatus') { text = $cell.find('.status').first().text().trim(); }
            if (!text || text === '—') { return; }
            if (columnName === 'metodo_contacto') { text = jsNormalizeContactChannel(text); }
            if (!seen[text]) { seen[text] = true; result.push(text); }
        });
        return result.sort();
    }

    // Función para crear el dropdown de filtro
    function createFilterDropdown(columnName, iconElement) {
        closeFilterDropdown();

        var uniqueValues   = getUniqueColumnValues(columnName);
        if (!uniqueValues.length) { return; }

        var selectedValues = columnFilters[columnName] ? columnFilters[columnName].slice() : uniqueValues.slice();

        var $drop = $('<div class="filter-dropdown show"></div>');

        // — Header —
        var $head = $('<div class="filter-dropdown-header"></div>');
        $head.append('<span>Filtrar columna</span>');
        var $x = $('<button type="button" class="btn-close btn-sm" style="font-size:0.7rem;"></button>');
        $x.on('click', closeFilterDropdown);
        $head.append($x);
        $drop.append($head);

        // — Body —
        var $body   = $('<div class="filter-dropdown-body"></div>');
        var $search = $('<input type="text" class="filter-search" placeholder="Buscar...">');
        $body.append($search);

        var $opts = $('<div class="filter-options"></div>');

        // Fila "Seleccionar todo"
        var allSel = (uniqueValues.length === selectedValues.length);
        var $saRow = $('<div class="filter-option mb-2"></div>');
        var $saChk = $('<input type="checkbox" id="sa_' + columnName + '"' + (allSel ? ' checked' : '') + '>');
        $saRow.append($saChk).append('<label for="sa_' + columnName + '"><strong>Seleccionar todo</strong></label>');
        $opts.append($saRow);
        $opts.append('<hr style="margin:0.25rem 0;">');

        // Filas de opciones
        uniqueValues.forEach(function (value, idx) {
            var isChk = (selectedValues.indexOf(value) !== -1);
            var $row  = $('<div class="filter-option"></div>').attr('data-value', value);
            var $chk  = $('<input type="checkbox" id="fc_' + columnName + '_' + idx + '"' + (isChk ? ' checked' : '') + '>');
            var $lbl  = $('<label></label>').attr('for', 'fc_' + columnName + '_' + idx).text(value);
            $row.append($chk).append($lbl);
            $opts.append($row);
        });

        $body.append($opts);
        $drop.append($body);

        // — Footer —
        var $foot  = $('<div class="filter-dropdown-footer"></div>');
        var $clear = $('<button type="button" class="filter-btn btn btn-sm btn-secondary">Limpiar</button>');
        $clear.on('click', function () {
            delete columnFilters[columnName];
            applyColumnFilters();
            closeFilterDropdown();
        });

        var $apply = $('<button type="button" class="filter-btn btn btn-sm btn-primary">Aplicar</button>');
        $apply.on('click', function () {
            var selected = [];
            $opts.find('.filter-option:not(:first-child) input[type="checkbox"]:checked')
                 .each(function () { selected.push($(this).closest('.filter-option').attr('data-value')); });

            if (!selected.length) {
                Swal.fire({ icon: 'warning', title: 'Advertencia', text: 'Selecciona al menos un valor.', confirmButtonText: 'OK' });
                return;
            }
            // Todos los valores seleccionados = sin filtro activo
            if (selected.length === uniqueValues.length) {
                delete columnFilters[columnName];
            } else {
                columnFilters[columnName] = selected;
            }
            applyColumnFilters();
            closeFilterDropdown();
        });

        $foot.append($clear).append($apply);
        $drop.append($foot);
        $('body').append($drop);

        // Posicionar
        var off  = $(iconElement).offset();
        var iH   = $(iconElement).outerHeight();
        var dW   = $drop.outerWidth();
        var dH   = $drop.outerHeight();
        var wW   = $(window).width();
        var wH   = $(window).height();
        var left = off.left;
        var top  = off.top + iH + 5;
        if (left + dW > wW) { left = wW - dW - 10; }
        if (top  + dH > wH) { top  = off.top - dH - 5; }
        $drop.css({ left: left + 'px', top: top + 'px' });

        currentFilterDropdown = $drop;
        $(iconElement).addClass('active');

        // Búsqueda en el dropdown
        $search.on('input', function () {
            var q = $(this).val().toLowerCase();
            $opts.find('.filter-option:not(:first-child)').each(function () {
                $(this).toggle($(this).find('label').text().toLowerCase().indexOf(q) !== -1);
            });
            _syncSelectAll();
        });

        // Seleccionar / deseleccionar todo
        $saChk.on('change', function () {
            $opts.find('.filter-option:not(:first-child):visible input[type="checkbox"]').prop('checked', $(this).prop('checked'));
        });

        // Checkbox individual → actualizar estado de "seleccionar todo"
        $opts.on('change', '.filter-option:not(:first-child) input[type="checkbox"]', _syncSelectAll);

        function _syncSelectAll() {
            var $vis = $opts.find('.filter-option:not(:first-child):visible input[type="checkbox"]');
            $saChk.prop('checked', $vis.length > 0 && $vis.filter(':checked').length === $vis.length);
        }
        _syncSelectAll();
    }

    // Función para cerrar el dropdown de filtro
    function closeFilterDropdown() {
        if (currentFilterDropdown) {
            currentFilterDropdown.remove();
            currentFilterDropdown = null;
            $('.filter-icon.active').removeClass('active');
        }
    }

    // Aplica los filtros activos y sincroniza el header de scroll de DataTables
    function applyColumnFilters() {
        // Sincronizar íconos activos
        $('.filter-icon').removeClass('active');
        Object.keys(columnFilters).forEach(function (col) {
            $('.filter-icon[data-column="' + col + '"]').addClass('active');
        });

        // Mostrar/ocultar filas según filtros
        _applyFilterVisibility();

        // Recalcular anchos de columna en el header del scroll (evita el desalineamiento)
        if (leadsTableDT) { leadsTableDT.draw(false); }

        updateLeadsCount();
        updateDynamicCards();
    }

    function updateLeadsCount() {
        // Placeholder — agrega #filterStatus si quieres mostrar "N de M leads"
    }

    // Función para actualizar los cards dinámicamente según filtros
    // NOTA: Los cards de "Tasa de calificación", "Total de registros" y "Personas que agendaron"
    // NO se ven afectados por filtros de columna. Solo los filtros de fecha y plataforma (server-side) los modifican.
    // "Personas que agendaron" se calcula desde contact_form + calendario (server-side) para ser
    // consistente con consulta_post_leads.php, ya que no todos los leads con cita existen en la tabla visible.
    var serverAgendadosCount = <?php echo intval($leadsAgendadosTotal); ?>;
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    window.CAMPAIGN_BADGE_OVERRIDES = <?php echo json_encode(getCampaignBadgeColorOverrides(), JSON_UNESCAPED_UNICODE); ?>;
    window.DEFAULT_FIELD_BADGE_COLORS = <?php echo json_encode(getDefaultFieldBadgeColors(), JSON_UNESCAPED_UNICODE); ?>;
    window.CONTACT_METHOD_BADGE_OVERRIDES = <?php echo json_encode(getContactMethodBadgeColorOverrides(), JSON_UNESCAPED_UNICODE); ?>;
    window.TIPO_CLIENTE_BADGE_OVERRIDES = <?php echo json_encode(getTipoClienteBadgeColorOverrides(), JSON_UNESCAPED_UNICODE); ?>;
    <?php echo renderCampaignBadgeJsFunctions(); ?>
    <?php echo renderLeadFieldBadgeJsFunctions(); ?>

    function formatPercent(count, totalCount) {
        if (!totalCount) {
            return '0%';
        }

        var formatted = ((count / totalCount) * 100).toFixed(1);
        return formatted.replace(/\.0$/, '') + '%';
    }

    function jsOriginBadge(label) {
        var normalized = (label || '').trim() || 'Origen por confirmar';
        var badgeClass = 'badge badge-neutral';

        if (normalized === 'Wedding Planner') {
            badgeClass = 'badge badge-planner';
        } else if (normalized === 'Community') {
            badgeClass = 'badge badge-community';
        } else if (normalized === 'New Audience') {
            badgeClass = 'badge badge-newmarket';
        }

        return '<span class="' + badgeClass + '"><span class="badge-dot"></span>' + escapeHtml(normalized) + '</span>';
    }

    function jsContactChannelDisplayLabel(label) {
        var normalized = (label || '').trim() || 'Sin dato';
        var map = {
            'WhatsApp': 'WhatsApp',
            'Instagram DM - Campaña': 'IG Campaign',
            'Instagram DM - Orgánico': 'IG Organic',
            'Correo electrónico': 'Email',
            'Llamada telefónica': 'Phone Call',
            'Sin dato': 'Not asked'
        };

        return Object.prototype.hasOwnProperty.call(map, normalized) ? map[normalized] : normalized;
    }

    function jsContactChannelBadge(label) {
        var normalized = (label || '').trim() || 'Sin dato';
        return jsRenderContactMethodBadge(normalized, jsContactChannelDisplayLabel(normalized));
    }

    function jsKnownUsBadge(label) {
        var normalized = (label || '').trim();
        if (!normalized || normalized.toLowerCase() === 'sin dato' || normalized.toLowerCase() === 'not asked') {
            normalized = 'Todavía no sabemos';
        }

        return '<span class="known">' + escapeHtml(normalized) + '</span>';
    }

    function jsFormatCampaignDisplay(label) {
        var normalized = (label || '').trim();
        if (!normalized) {
            return '—';
        }

        var match = normalized.match(/^(c\d+)\b/i);
        if (match) {
            return match[1].toLowerCase();
        }

        return normalized;
    }

    function jsNormalizeKnownUsLabel(label) {
        var normalized = (label || '').trim();
        if (!normalized || normalized.toLowerCase() === 'sin dato' || normalized.toLowerCase() === 'not asked') {
            return 'Todavía no sabemos';
        }

        return normalized;
    }

    function renderBreakdownList(targetSelector, counts, totalCount, emptyLabel) {
        var entries = Object.keys(counts).map(function (label) {
            return { label: label, count: counts[label] };
        }).sort(function (left, right) {
            if (right.count !== left.count) {
                return right.count - left.count;
            }

            return left.label.localeCompare(right.label, 'es');
        });

        if (!entries.length) {
            $(targetSelector).html('<p class="report-breakdown-empty">' + escapeHtml(emptyLabel) + '</p>');
            return;
        }

        var html = entries.map(function (entry) {
            var percentage = totalCount > 0 ? ((entry.count / totalCount) * 100).toFixed(2) : '0.00';
            var displayLabel = entry.label;
            if (displayLabel === 'N/A') {
                displayLabel = 'Origen por confirmar';
            } else if (displayLabel === 'Sin dato' || displayLabel === 'Not asked' || displayLabel === 'Todavía no sabemos') {
                displayLabel = 'Todavía no sabemos';
            }
            return '<div class="report-breakdown-item">'
                + '<div class="report-breakdown-row">'
                + '<div class="report-breakdown-label">' + escapeHtml(displayLabel) + '</div>'
                + '<div class="report-breakdown-meta">' + entry.count.toLocaleString() + ' · ' + formatPercent(entry.count, totalCount) + '</div>'
                + '</div>'
                + '<div class="report-breakdown-bar"><span class="report-breakdown-fill" style="width: ' + percentage + '%;"></span></div>'
                + '</div>';
        }).join('');

        $(targetSelector).html(html);
    }

    function updateDynamicCards() {
        var $rows = getLeadTableRows();
        var totalCount = $rows.length;
        var agendadosCount = serverAgendadosCount;
        var pendingCount = 0;
        var wpAfianzadosCount = <?php echo intval($wpAfianzadosCount); ?>;
        var contactCounts = {};
        var originCounts = {};
        var knownUsCounts = {};

        $rows.each(function () {
            var $row = $(this);
            var tablaOrigen = ($row.attr('data-tabla') || '').trim().toLowerCase();
            var contactMethod = ($row.attr('data-contact-method') || 'Sin dato').trim() || 'Sin dato';
            var originLabel = ($row.attr('data-origin-label') || 'N/A').trim() || 'N/A';
            var wpAfianzado = ($row.attr('data-wp-afianzado') || '0').trim();
            var knownUsLabel = jsNormalizeKnownUsLabel($row.attr('data-known-us') || 'Not asked');

            if (originLabel === 'N/A') {
                pendingCount++;
            }

            contactCounts[contactMethod] = (contactCounts[contactMethod] || 0) + 1;
            originCounts[originLabel] = (originCounts[originLabel] || 0) + 1;
            knownUsCounts[knownUsLabel] = (knownUsCounts[knownUsLabel] || 0) + 1;
        });

        // Calcular tasa de calificación (agendaron / total)
        var tasaConversion = totalCount > 0 ? ((agendadosCount / totalCount) * 100).toFixed(2) : '0.00';

        var startVal = $('input[name="start_date"]').val();
        var endVal = $('input[name="end_date"]').val();
        var showAll = (new URLSearchParams(window.location.search)).has('show_all');
        var isRange = !showAll && (startVal !== '' || endVal !== '');

        $('#report-total-registros').text(totalCount.toLocaleString());
        $('#report-agendaron-count').text(agendadosCount.toLocaleString());
        $('#report-agendaron-note').text(formatPercent(agendadosCount, totalCount) + ' del total avanzó a cita.');
        $('#report-pending-count').text(pendingCount.toLocaleString());
        $('#report-pending-note').text(formatPercent(pendingCount, totalCount) + ' sin Origen del Cliente capturado.');
        $('#report-wp-afianzados-count').text(wpAfianzadosCount.toLocaleString());
        $('#report-wp-afianzados-note').text('Total de eventos de todos los wedding planners afianzados.');
        $('#report-origin-pending-note').html('<strong>' + pendingCount.toLocaleString() + ' leads</strong><span>con origen aún no confirmado — se completará en la llamada de calificación.</span>');
        $('#report-qualification-rate').text(tasaConversion + '%');
        $('#report-qualification-detail').text(agendadosCount.toLocaleString() + ' personas que agendaron de ' + totalCount.toLocaleString() + ' registros');
        $('#report-period-text').text(isRange ? ((startVal || '...') + ' → ' + (endVal || '...')) : 'Todos los registros');
        renderBreakdownList('#report-contact-breakdown', contactCounts, totalCount, 'Sin datos para este periodo.');
        renderBreakdownList('#report-origin-breakdown', originCounts, totalCount, 'Sin datos para este periodo.');
        renderBreakdownList('#report-known-us-breakdown', knownUsCounts, totalCount, 'Sin datos para este periodo.');
    }

    // Event listener para los íconos de filtro
    $(document).on('click', '.filter-icon', function (e) {
        e.stopPropagation();
        var columnName = $(this).data('column');
        createFilterDropdown(columnName, this);
    });

    // Cerrar dropdown al hacer clic fuera
    $(document).on('click', function (e) {
        if (currentFilterDropdown && !$(e.target).closest('.filter-dropdown').length && !$(e.target).hasClass('filter-icon')) {
            closeFilterDropdown();
        }
    });

    // Prevenir que el dropdown se cierre al hacer clic dentro de él
    $(document).on('click', '.filter-dropdown', function (e) {
        e.stopPropagation();
    });

    // Cerrar dropdown con la tecla Escape
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && currentFilterDropdown) {
            closeFilterDropdown();
        }
    });

    // ========================================
    // FIN SISTEMA DE FILTROS POR COLUMNA
    // ========================================

    // --- Agendar Wedding Planner (cliente manual sin cita) ---
    $(document).on('click', '.wp-agendar-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var wpId = $btn.data('wp-id');
        var tabla = $btn.data('tabla');
        var idVendedor = parseInt($btn.attr('data-id-vendedor') || 0, 10) || 0;

        // If there's no vendor assigned, show modal to pick one
        if (!idVendedor) {
            $('#wpAgendarId').val(wpId);
            $('#wpAgendarAsesor').val('');
            var modal = new bootstrap.Modal(document.getElementById('wpAgendarModal'));
            modal.show();
            return;
        }

        Swal.fire({
            title: 'Agendar cita',
            text: 'Esto creará una entrada en contacto y una cita en calendario asignada al asesor seleccionado. ¿Deseas continuar?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, agendar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Agendando...');
            $.ajax({
                url: 'agendar_wedding_planner.php',
                type: 'POST',
                dataType: 'json',
                data: { wedding_planner_id: wpId },
                success: function (resp) {
                    if (resp && resp.success) {
                        Swal.fire({ icon: 'success', title: 'Agendado', text: 'Se creó la entrada en Contact Form y la cita en Calendario.' }).then(function () { location.reload(); });
                    } else {
                        Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo agendar', 'error');
                    }
                },
                error: function (xhr) {
                    showErrorModalFromResponse(null, xhr);
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="bi bi-calendar-plus"></i> Agendar');
                }
            });
        });
    });

    // Handler: confirm in modal to pick advisor and agendar
    $('#btnConfirmAgendarWp').on('click', function () {
        var $btn = $(this);
        var wpId = parseInt($('#wpAgendarId').val() || 0, 10);
        var selected = parseInt($('#wpAgendarAsesor').val() || 0, 10);
        if (!selected) {
            $('#wpAgendarAsesor').addClass('is-invalid');
            return;
        }
        $('#wpAgendarAsesor').removeClass('is-invalid');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Agendando...');
        $.ajax({
            url: 'agendar_wedding_planner.php',
            type: 'POST',
            dataType: 'json',
            data: { wedding_planner_id: wpId, override_vendedor_id: selected },
            success: function (resp) {
                if (resp && resp.success) {
                    var modalEl = document.getElementById('wpAgendarModal');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    Swal.fire({ icon: 'success', title: 'Agendado', text: 'Se creó la entrada en Contact Form y la cita en Calendario.' }).then(function () { location.reload(); });
                } else {
                    Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo agendar', 'error');
                }
            },
            error: function (xhr) {
                showErrorModalFromResponse(null, xhr);
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="bi bi-save"></i> Agendar');
            }
        });
    });
</script>

</body>

</html>