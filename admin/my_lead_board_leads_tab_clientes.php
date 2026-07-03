<?php
/** Tab embed: clientes */
if (!defined('MLB_LEADS_SHELL')) { http_response_code(403); exit('Forbidden'); }

// Sesión y $tipoUsuario los provee menu.php en el shell

// embed: menu en shell
if (!isset($conn)) { include 'conn.php'; }
require_once __DIR__ . '/campaign_badge_helper.php';
require_once __DIR__ . '/lead_field_badge_helper.php';
require_once __DIR__ . '/lead_origin_helper.php';
require_once __DIR__ . '/wp_eventos_contact_form_helper.php';
require_once __DIR__ . '/dashboard_comercial_helper.php';

function clientesResolveFechaCierreCliente(array $lead)
{
    global $conn;

    $fecha = trim((string) ($lead['fecha_cambio_cliente'] ?? ''));
    if ($fecha !== '' && strpos($fecha, '0000-00-00') !== 0) {
        return $fecha;
    }

    if (isset($conn) && function_exists('dashComercialResolveClienteFechaCambioLikeClientes')) {
        $fecha = trim((string) dashComercialResolveClienteFechaCambioLikeClientes($conn, $lead));
        if ($fecha !== '' && strpos($fecha, '0000-00-00') !== 0) {
            return $fecha;
        }
    }

    return '';
}

function clientesFormatFechaCierreDisplay($fechaRaw)
{
    $fechaRaw = trim((string) $fechaRaw);
    if ($fechaRaw === '' || strpos($fechaRaw, '0000-00-00') === 0) {
        return '—';
    }

    $formatted = formatCreatedTime($fechaRaw);
    if ($formatted === '') {
        $formatted = formatArrivalDateTime($fechaRaw);
    }

    return $formatted !== '' ? $formatted : '—';
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

function formatCreatedDateOnly($dateString)
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
    return "$day de $month de $year";
}

// Obtener todos los IDs de contact_form que tienen citas en calendario
$appointmentIds = [];
$appointmentQuery = $conn->query("SELECT DISTINCT idclie FROM calendario");
if ($appointmentQuery && $appointmentQuery->num_rows > 0) {
    while ($row = $appointmentQuery->fetch_assoc()) {
        $appointmentIds[] = intval($row['idclie']);
    }
}

// Si hay citas, obtener datos de las citas para extraer el estatus (mapear por idclie)
$appointmentsByClient = [];
if (!empty($appointmentIds)) {
    $idsList = implode(',', array_map('intval', $appointmentIds));
    $apptRes = $conn->query("SELECT * FROM calendario WHERE idclie IN ($idsList)");
    if ($apptRes && $apptRes->num_rows > 0) {
        while ($ar = $apptRes->fetch_assoc()) {
            $idclie = isset($ar['idclie']) ? intval($ar['idclie']) : 0;
            if ($idclie <= 0)
                continue;

            // Si ya existe uno, elegir el más reciente por fecha+h ora si están disponibles, sino por id
            if (!isset($appointmentsByClient[$idclie])) {
                $appointmentsByClient[$idclie] = $ar;
            } else {
                $prev = $appointmentsByClient[$idclie];
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
                    $appointmentsByClient[$idclie] = $ar;
            }
        }
    }
}

$appointmentsByEventId = wpEventosCfGetAppointmentsByEventId($conn);

// Cargar mapa id → nombre de paquetes
$paquetesMap = [];
$paqRes = $conn->query("SELECT id, nombre FROM paquetes ORDER BY id");
if ($paqRes) {
    while ($paqRow = $paqRes->fetch_assoc()) {
        $paquetesMap[intval($paqRow['id'])] = $paqRow['nombre'];
    }
}

// Consultar registros de contact_form (todos los que son Cierres) — excluir Wedding Planners
$allLeads = [];
// Excluir filas cuya tabla_origen sea 'wedding_planners'
$sql = "SELECT * FROM contact_form WHERE cliente = 1 AND LOWER(COALESCE(tabla_origen, '')) != 'wedding_planners' ORDER BY submission_date DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($cf = $result->fetch_assoc()) {
        // Defensa adicional: saltar registros con tabla_origen wedding_planners si los hay
        $tablaOrigenRaw = strtolower(trim($cf['tabla_origen'] ?? ''));
        if ($tablaOrigenRaw === 'wedding_planners' || $tablaOrigenRaw === 'wedding_planner') {
            continue;
        }

        // Datos básicos del contact_form
        $merged = $cf;

        $merged['tabla_origen'] = $cf['tabla_origen'] ?? '';
        $merged['original_lead_id'] = $cf['original_lead_id'] ?? null;
        $merged['full_name'] = $cf['names'] ?? 'N/A';
        $merged['submission_date'] = $cf['submission_date'] ?? '';
        // Ensure we have a date for filtering: prefer contact_form.fecha_cambio_cliente for clients page
        // For client reporting use fecha_cambio_cliente (the date when lead changed to client)
        $merged['fecha_cambio_cliente'] = $cf['fecha_cambio_cliente'] ?? '';
        // Keep legacy created_time for fallback but prefer fecha_cambio_cliente above
        $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';
        // wedding_location se asigna después del merge con el lead original para no bloquear el valor real
        $merged['wedding_location'] = (isset($cf['wedding_location']) && trim($cf['wedding_location']) !== '') ? $cf['wedding_location'] : '';
        // Verificar si tiene cita
        $merged['has_appointment'] = in_array(intval($cf['id']), $appointmentIds) ? 1 : 0;

        // Obtener datos del lead original si es necesario
        $formName = $cf['tabla_origen'] ?? '';
        $origId = intval($cf['original_lead_id'] ?? 0);
        if (!empty($formName) && $origId > 0) {
            $escapedForm = $conn->real_escape_string($formName);
            $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
                if ($leadRes && $leadRes->num_rows > 0) {
                    $leadRow = $leadRes->fetch_assoc();
                    // Only override the contact_form fecha_cambio_cliente fallback when the original lead has a meaningful value
                    // Actualizar nombre si está disponible
                    if (!empty($leadRow['full_name']))
                        $merged['full_name'] = $leadRow['full_name'];
                    elseif (!empty($leadRow['names']))
                        $merged['full_name'] = $leadRow['names'];
                    elseif (!empty($leadRow['name']))
                        $merged['full_name'] = $leadRow['name'];

                    // Actualizar fecha de creación si está disponible
                    if (!empty($leadRow['fecha_cambio_cliente'])) {
                        $merged['fecha_cambio_cliente'] = $leadRow['fecha_cambio_cliente'];
                    } elseif (!empty($leadRow['created_time'])) {
                        $merged['created_time'] = $leadRow['created_time'];
                    } elseif (!empty($leadRow['created_at'])) {
                        $merged['created_time'] = $leadRow['created_at'];
                    }

                    // Añadir todos los campos del lead original
                    // Si el `contact_form` tiene el campo pero está vacío, preferimos el valor
                    // del lead original para evitar perder datos como `platform` o `form_name`.
                    foreach ($leadRow as $k => $v) {
                        if (is_string($v)) $v = trim($v);
                        if (!isset($merged[$k]) || $merged[$k] === '' || $merged[$k] === null) {
                            $merged[$k] = $v;
                        }
                    }
                }
            }
        }



        // Garantizar campaign_name - usar 'N/A' si no existe o está vacío (incluye cadenas vacías o sólo espacios)
        $merged['campaign_name'] = (isset($cf['campaign_name']) && trim($cf['campaign_name']) !== '') ? $cf['campaign_name'] : 'N/A';

        // Fallback final de wedding_location: revisar también el campo alternativo del formulario
        if (empty($merged['wedding_location']) || $merged['wedding_location'] === 'N/A') {
            $altWl = trim((string)($merged['where_are_you_getting_married_'] ?? ''));
            if ($altWl !== '') {
                $merged['wedding_location'] = $altWl;
            } else {
                $merged['wedding_location'] = 'N/A';
            }
        }

        // ===== MAPEO DEL ASESOR ASIGNADO (idusu) =====
        // Si la cita existe en calendario y en $appointmentsByClient guardamos el id del asesor
        // en los campos que usa el resto del código: id_vendedor_asignado y usuario_asignado.
        // Esto evita hacer un JOIN complejo y respeta la lógica previa que eligió la
        // cita más reciente para cada lead.
        $cid = intval($cf['id']);
        if ($cid > 0 && isset($appointmentsByClient[$cid])) {
            $appt = $appointmentsByClient[$cid];
            // "idusu" es la columna usada en 'calendario' para el asesor asignado (si está presente)
            if (isset($appt['idusu']) && $appt['idusu'] !== '') {
                // mantener valores existentes en contact_form si ya vienen con prioridad
                if (!isset($merged['id_vendedor_asignado']) || empty($merged['id_vendedor_asignado'])) {
                    $merged['id_vendedor_asignado'] = intval($appt['idusu']);
                }
                if (!isset($merged['usuario_asignado']) || empty($merged['usuario_asignado'])) {
                    $merged['usuario_asignado'] = intval($appt['idusu']);
                }
            }
            // También respetar otras posibles columnas en la tabla calendario que almacenen
            // el id del vendedor (por ejemplo id_vendedor_asignado) — prioridad si idusu no existe
            if (!isset($merged['id_vendedor_asignado']) || empty($merged['id_vendedor_asignado'])) {
                if (isset($appt['id_vendedor_asignado']) && $appt['id_vendedor_asignado'] !== '') {
                    $merged['id_vendedor_asignado'] = intval($appt['id_vendedor_asignado']);
                }
            }
        }

        // Añadir estatus desde la cita si existe y mapear a etiquetas legibles
        $cid = intval($cf['id']);
        $merged['estatus'] = '';
        if ($cf['cliente'] == 1) {

            $merged['estatus'] = 'cliente';
        } else
            if (isset($appointmentsByClient[$cid]) && isset($appointmentsByClient[$cid]['estatus'])) {
                $rawStatus = $appointmentsByClient[$cid]['estatus'];
                // Intentar normalizar a entero cuando sea posible
                $intStatus = is_numeric($rawStatus) ? intval($rawStatus) : null;

                if ($intStatus === 1) {
                    $merged['estatus'] = 'atendido';
                } elseif ($intStatus === 3) {
                    $merged['estatus'] = 'muerto';
                } elseif ($intStatus === 0) {
                    $merged['estatus'] = 'agendado';
                } elseif ($intStatus === 2) {
                    $merged['estatus'] = 'fantasma';
                } else {
                    // Si no es un número conocido, dejar el valor crudo como fallback
                    $merged['estatus'] = $rawStatus;
                }
            }

        // Guardar origen real antes de auto-fill para tipo de cliente
        $merged['how_did_you_meet_raw'] = trim((string)($merged['how_did_you_meet'] ?? ''));

        // Resolver how_did_you_meet: how_long_known_us prevalece en registro manual / website
        applyResolvedHowDidYouMeetToLead($merged);

        $allLeads[] = $merged;
    }
}

// Calcular conteo de registros en contact_form con campaign_name no vacío (para mostrar en un card)
$campaignCount = 0;
$resCampaign = $conn->query("SELECT COUNT(*) AS cnt FROM contact_form WHERE campaign_name IS NOT NULL AND TRIM(campaign_name) <> '' AND LOWER(COALESCE(tabla_origen, '')) != 'wedding_planners'");
if ($resCampaign) {
    $r = $resCampaign->fetch_assoc();
    $campaignCount = isset($r['cnt']) ? intval($r['cnt']) : 0;
}

// Leer filtro de plataforma desde GET
$filterPlataforma = isset($_GET['filter_plataforma']) ? trim($_GET['filter_plataforma']) : '';
// Etiqueta amigable para mostrar en los cards: (todo|redes sociales|organico)
$platformLabel = 'todo';
if ($filterPlataforma === 'campania') {
    $platformLabel = 'redes sociales';
} elseif ($filterPlataforma === 'organico') {
    $platformLabel = 'organico';
} 

// Obtener mapa de tabla_origen => tipo desde tablas_leads para filtro de plataforma
$tablaTipoMap = [];
$sqlTablasMap = "SELECT nombre, tipo FROM tablas_leads";
$resultTablasMap = $conn->query($sqlTablasMap);
if ($resultTablasMap && $resultTablasMap->num_rows > 0) {
    while ($row = $resultTablasMap->fetch_assoc()) {
        $tablaTipoMap[$row['nombre']] = intval($row['tipo']);
    }
}

// Leer filtros de fecha desde GET (se usan más abajo para conteos)
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
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
// Si el usuario no proporciona fechas, por defecto mostrar los últimos 14 días
// Solo aplicar el default cuando NO se pida explícitamente "mostrar todo" (show_all=1)
if ($startDate === '' && $endDate === '' && empty($_GET['show_all'])) {
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-14 days', strtotime($endDate)));
} 

// Helper: Obtener el Origen mostrado para un lead (normaliza campaign o usa desde_publicidad)
if (!function_exists('getDisplayOrigenFromLead')) {
    function getDisplayOrigenFromLead($lead) {
        $orig = trim($lead['campaign_name'] ?? '');
        // c123.abc -> c123
        if (preg_match('/^c(\d+)\./i', $orig, $m)) return 'c' . $m[1];
        // Si no hay campaign_name útil, usar desde_publicidad para el label mostrado
        if ($orig === '' || mb_strtolower($orig, 'UTF-8') === 'n/a') {
            $dp = isset($lead['desde_publicidad']) ? intval($lead['desde_publicidad']) : null;
            switch ($dp) {
                case 0: return 'Website';
                // map 'Publicidad' to 'Website' per request
                case 1: return 'Website';
                case 2: return 'Instagram Orgánico';
                case 3: return 'Whatsapp';
                case 4: return 'Correo electrónico';
                default: return 'Otro';
            }
        }
        // Si por alguna razón $orig contiene la palabra 'publicidad' (varios orígenes pueden venir así), normalizar a Website
        if (mb_strtolower($orig, 'UTF-8') === 'publicidad') return 'Website';
        return $orig;
    }
}

// (Código de orígenes, medios y estatus removido - se usa filtro de Plataforma)

// Helper: Normalize origen (si no existe) — igual que en consulta_post_leads.php
if (!function_exists('normalizeOrigen')) {
    function normalizeOrigen($orig) {
        if (!$orig) return '';
        if (preg_match('/^c(\d+)\./i', $orig, $m)) return 'c' . $m[1];
        return $orig;
    }
}

if (!function_exists('isB1B2CampaignName')) {
    function isB1B2CampaignName($campaignName)
    {
        if ($campaignName === null)
            return false;
        $v = strtolower(trim((string) $campaignName));
        if ($v === '')
            return false;
        $v = preg_replace('/\s+/', ' ', $v);
        return in_array($v, ['b1', 'b1 (usa)', 'b1 usa', 'b2', 'b2 (mx)', 'b2 mx'], true);
    }
}

if (!function_exists('getDondeNosConocioLabel')) {
    function getDondeNosConocioLabel($lead) {
        $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
        $hearRaw = trim((string)($lead['hear_about_us'] ?? ''));

        $howMap = [
            '1' => 'Wedding Planner',
            '2' => 'Community',
            '3' => 'New Audience'
        ];

        $hearMap = [
            '1' => 'Meta Ads — anuncio en Instagram / Facebook',
            '2' => 'SEO — buscaron en Google',
            '3' => 'Colaboración / Influencer / Famoso',
            '4' => 'Publicación / Prensa / Revista',
            '5' => 'Otro'
        ];

        $howLabel = '';
        if ($howRaw !== '') {
            $howLabel = $howMap[$howRaw] ?? $howRaw;
        }

        $hearLabel = '';
        if ($hearRaw !== '') {
            $hearLabel = $hearMap[$hearRaw] ?? $hearRaw;
        }

        if ($howLabel !== '' && $hearLabel !== '') {
            return $howLabel . ' / ' . $hearLabel;
        }
        if ($howLabel !== '') {
            return $howLabel;
        }
        if ($hearLabel !== '') {
            return $hearLabel;
        }

        return 'N/A';
    }
}

if (!function_exists('getEngagementLabelWithEmoji')) {
    function getEngagementLabelWithEmoji($lead) {
        $raw = trim((string)($lead['engagement'] ?? ($lead['sfm_engagement'] ?? '')));
        if ($raw === '') return 'N/A';

        $normalized = mb_strtolower($raw, 'UTF-8');

        if ($normalized === '0') {
            return 'Sin dato';
        }

        if ($normalized === '1' || $normalized === 'bajo') {
            return '😑 Bajo';
        }
        if ($normalized === '2' || $normalized === 'medio') {
            return '😃 Medio';
        }
        if ($normalized === '3' || $normalized === 'alto') {
            return '🔥 Alto';
        }

        return $raw;
    }
}

// (moved above) getDisplayOrigenFromLead - previously defined later, removed to avoid duplicate declaration.

if (!function_exists('normalizeFirstContactChannelLabel')) {
    function normalizeFirstContactChannelLabel($value) {
        $normalized = trim((string) $value);
        if ($normalized === '') return 'Sin dato';
        $key = mb_strtolower($normalized, 'UTF-8');
        $key = str_replace(['–', '—'], '-', $key);
        $key = preg_replace('/\s+/', ' ', $key);
        $map = [
            'whatsapp'                 => 'WhatsApp',
            'instagram dm - campaign'  => 'Instagram DM - Campaña',
            'instagram dm campaign'    => 'Instagram DM - Campaña',
            'instagram dm - organic'   => 'Instagram DM - Orgánico',
            'instagram dm organic'     => 'Instagram DM - Orgánico',
            'email'                    => 'Correo electrónico',
            'correo electronico'       => 'Correo electrónico',
            'correo electrónico'       => 'Correo electrónico',
            'mail'                     => 'Correo electrónico',
            'phone call'               => 'Phone call',
            'llamada telefonica'       => 'Phone call',
            'llamada telefónica'       => 'Phone call',
        ];
        return $map[$key] ?? $normalized;
    }
}

if (!function_exists('getContactChannelBadgeLabel')) {
    function getContactChannelBadgeLabel($label) {
        $map = [
            'WhatsApp' => '💬 WhatsApp',
            'Instagram DM - Campaña' => '📸 IG Campaign',
            'Instagram DM - Orgánico' => '📸 IG Organic',
            'Correo electrónico' => '✉️ Email',
            'Llamada telefónica' => '📞 Phone Call',
            'Phone call' => '📞 Phone Call',
            'Sin dato' => 'Not asked'
        ];

        return $map[$label] ?? $label;
    }
}

if (!function_exists('getKnownUsBadgeLabel')) {
    function getKnownUsBadgeLabel($value) {
        $normalized = trim((string) $value);
        if ($normalized === '' || strcasecmp($normalized, 'Sin dato') === 0 || strcasecmp($normalized, 'Not asked') === 0) {
            return 'Not asked';
        }

        return $normalized;
    }
}

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

if (!function_exists('inferTipoClienteFromOriginData')) {
    function inferTipoClienteFromOriginData($howDidYouMeet, $tablaOrigen)
    {
        $tabla = strtolower(trim((string) $tablaOrigen));

        if (in_array($tabla, ['eventos_wp', 'wp_eventos_afianzados', 'wp_citas_leads'], true)) {
            return 'Cliente Final';
        }

        if ($tabla === 'wedding_planners') {
            return 'Wedding Planner';
        }

        $how = trim((string) $howDidYouMeet);
        if ($how === '1') {
            return 'Wedding Planner';
        }

        if (in_array($how, ['2', '3'], true)) {
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

if (!function_exists('formatCampaignDisplayLabel')) {
    function formatCampaignDisplayLabel($value, $referrerUrl = '', $firstContactChannel = '') {
        $normalized = trim((string) $value);
        if ($normalized === '' || mb_strtolower($normalized, 'UTF-8') === 'n/a') {
            return '—';
        }

        if (mb_strtolower($normalized, 'UTF-8') === 'reg manual') {
            return 'Cierres retroactivos';
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
}

if (!function_exists('formatPercentage')) {
    function formatPercentage($part, $total, $decimals = 1) {
        $total = floatval($total);
        if ($total <= 0) return '0%';
        $formatted = number_format((floatval($part) / $total) * 100, $decimals, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted . '%';
    }
}

if (!function_exists('formatLeadDate')) {
    function formatLeadDate($dateString) {
        $value = trim((string) $dateString);
        if ($value === '') return '—';
        $timestamp = strtotime($value);
        if ($timestamp === false) return $value;
        return date('d/m/Y', $timestamp);
    }
}

// Calcular conteo total de leads que se muestran en la tabla (tienen cita) y conteo filtrado por fecha
$leadsCount = count($allLeads);
$leadsCountFiltered = 0;

if ($startDate === '' && $endDate === '') {
    $leadsCountFiltered = $leadsCount;
} else {
    $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
    $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;

    foreach ($allLeads as $lead) {
        // Usar fecha_cambio_cliente para metrics en la página de Cierres; fallback a created_time/submission_date
        // Use only fecha_cambio_cliente for client metrics/time filters (no fallbacks)
        $ct = $lead['fecha_cambio_cliente'] ?? '';
        if (empty($ct))
            continue;
        $ts = strtotime($ct);
        if ($ts === false)
            continue;
        $d = date('Y-m-d', $ts);

        if ($sd && $d < $sd)
            continue;
        if ($ed && $d > $ed)
            continue;

        $leadsCountFiltered++;
    }
}

// Leer filtros de fecha desde GET (para el card de conteo filtrado)
// No sobrescribir valores ya calculados (p. ej. el default de 14 días). Solo usar GET si viene explícito.
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : ($startDate ?? '');
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : ($endDate ?? '');

// Calcular conteo de leads con campaña dentro del rango de fechas si se aplican filtros
$campaignCountFiltered = 0;
if ($startDate === '' && $endDate === '') {
    // Si no hay filtro, usar el conteo total
    $campaignCountFiltered = $campaignCount;
} else {
    // Normalizar a YYYY-MM-DD
    $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
    $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;

    foreach ($allLeads as $lead) {
        $cn = isset($lead['campaign_name']) ? trim($lead['campaign_name']) : '';
        if ($cn === '' || strtolower($cn) === 'n/a')
            continue;

        // Usar fecha_cambio_cliente para metrics; fallback a created_time/submission_date
        // Use only fecha_cambio_cliente for client metrics/time filters
        $ct = $lead['fecha_cambio_cliente'] ?? '';
        if (empty($ct))
            continue;
        $ts = strtotime($ct);
        if ($ts === false)
            continue;
        $d = date('Y-m-d', $ts);

        if ($sd && $d < $sd)
            continue;
        if ($ed && $d > $ed)
            continue;

        // Filtro por plataforma (basado en tabla_origen)
        if ($filterPlataforma !== '') {
            $tablaOrigen = $lead['tabla_origen'] ?? '';
            $tipoTabla = isset($tablaTipoMap[$tablaOrigen]) ? $tablaTipoMap[$tablaOrigen] : -1;
            $campaignNameLower = strtolower(trim($lead['campaign_name'] ?? ''));
            $isB1B2 = isB1B2CampaignName($campaignNameLower);

            if ($filterPlataforma === 'organico') {
                // Incluir si es tipo 0 O si campaign_name es website o reg manual
                // Excluir B1/B2 para que no aparezcan en orgánico
                if ($isB1B2) continue;
                if ($tipoTabla !== 0 && $campaignNameLower !== 'website' && $campaignNameLower !== 'reg manual') continue;
            }
            if ($filterPlataforma === 'campania') {
                // Incluir campañas (tipo 1) y también B1/B2 que vienen de organic_leads
                if ($tipoTabla !== 1 && !$isB1B2) continue;
            }
        }

        $campaignCountFiltered++;
    }
}

// Crear lista filtrada para mostrar en la tabla (aplicar filtros de Plataforma y fecha)
$displayLeads = [];

// Aplicar filtros
if ($startDate === '' && $endDate === '' && $filterPlataforma === '') {
    $displayLeads = $allLeads;
} else {
    $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
    $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;

    foreach ($allLeads as $lead) {
        // Filtro por fecha
        $ct = $lead['fecha_cambio_cliente'] ?? '';
        if (empty($ct))
            continue;
        $ts = strtotime($ct);
        if ($ts === false)
            continue;
        $d = date('Y-m-d', $ts);

        if ($sd && $d < $sd) continue;
        if ($ed && $d > $ed) continue;

        // Filtro por plataforma (basado en tabla_origen)
        if ($filterPlataforma !== '') {
            $tablaOrigen = $lead['tabla_origen'] ?? '';
            $tipoTabla = isset($tablaTipoMap[$tablaOrigen]) ? $tablaTipoMap[$tablaOrigen] : -1;
            $campaignNameLower = strtolower(trim($lead['campaign_name'] ?? ''));
            $isB1B2 = isB1B2CampaignName($campaignNameLower);

            if ($filterPlataforma === 'organico') {
                // Incluir si es tipo 0 O si campaign_name es website o reg manual
                // Excluir B1/B2 para que no aparezcan en orgánico
                if ($isB1B2) continue;
                if ($tipoTabla !== 0 && $campaignNameLower !== 'website' && $campaignNameLower !== 'reg manual') continue;
            }
            if ($filterPlataforma === 'campania') {
                // Incluir campañas (tipo 1) y también B1/B2 que vienen de organic_leads
                if ($tipoTabla !== 1 && !$isB1B2) continue;
            }
        }

        $displayLeads[] = $lead;
    }
}

// ===== Ordenar los leads por fecha que se cerró (fecha_cambio_cliente) - más reciente primero =====
// Usamos strtotime y manejamos valores vacíos colocando fechas inválidas al final
usort($displayLeads, function ($a, $b) {
    $ta = (!empty($a['fecha_cambio_cliente']) && strtotime($a['fecha_cambio_cliente']) !== false) ? strtotime($a['fecha_cambio_cliente']) : 0;
    $tb = (!empty($b['fecha_cambio_cliente']) && strtotime($b['fecha_cambio_cliente']) !== false) ? strtotime($b['fecha_cambio_cliente']) : 0;
    // Queremos ordenar DESC por fecha (más reciente -> menor índice)
    if ($tb === $ta) return 0;
    return ($tb < $ta) ? -1 : 1;
});

// Asegurar que el conteo mostrado coincide con la lista que se muestra
$leadsCountFiltered = count($displayLeads);

// Cierres totales (para mostrar en card) - solo del año actual
$clientesTotal = 0;
$currentYear = date('Y');
foreach ($allLeads as $lead) {
    $fecha = $lead['fecha_cambio_cliente'] ?? '';
    if (!empty($fecha) && date('Y', strtotime($fecha)) == $currentYear) {
        $clientesTotal++;
    }
}

// ===== TASA DE CONVERSIÓN (Pre-qualified leads) =====
// Replicar EXACTAMENTE la lógica de consulta_leads.php
// Obtener todas las tablas de leads pre-calificados
$tablasPreLeads = [];
$sqlTablasPreLeads = "SELECT nombre FROM tablas_leads WHERE tipo != 2 ORDER BY nombre";
$resultTablasPreLeads = $conn->query($sqlTablasPreLeads);
if ($resultTablasPreLeads && $resultTablasPreLeads->num_rows > 0) {
    while ($row = $resultTablasPreLeads->fetch_assoc()) {
        $tablasPreLeads[] = $row['nombre'];
    }
}

// Consultar todos los leads pre-calificados de todas las tablas
$allPreLeadsForConversion = [];
foreach ($tablasPreLeads as $tableName) {
    $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $columns = [];
        $columnsResult = $conn->query("SHOW COLUMNS FROM `$tableName`");
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        $sqlLeadsPreCal = "SELECT *, '$tableName' as tabla_origen FROM `$tableName` WHERE " .
            (in_array('descartado', $columns) ? "descartado = 0 OR descartado IS NULL" : "1=1") . " ORDER BY created_time DESC";
        $resultLeadsPreCal = $conn->query($sqlLeadsPreCal);
        if ($resultLeadsPreCal && $resultLeadsPreCal->num_rows > 0) {
            while ($lead = $resultLeadsPreCal->fetch_assoc()) {
                $allPreLeadsForConversion[] = $lead;
            }
        }
    }
}

// Aplicar filtros de fecha (igual que consulta_leads.php)
$filteredPreLeadsForConversion = [];
foreach ($allPreLeadsForConversion as $lead) {
    // Filtro por fecha
    if ($startDate !== '' || $endDate !== '') {
        if (empty($lead['created_time'])) continue;
        // Extraer fecha local igual que consulta_leads.php: si es ISO 8601 (contiene 'T'),
        // tomar solo los primeros 10 caracteres para evitar conversión de zona horaria.
        $rawCTpre = $lead['created_time'];
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $rawCTpre, $_mPre)) {
            $leadDate = $_mPre[1];
        } else {
            continue;
        }
        $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
        $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
        if ($sd && $leadDate < $sd) continue;
        if ($ed && $leadDate > $ed) continue;
    }
    $filteredPreLeadsForConversion[] = $lead;
}

// Construir mapa de IDs por tabla
$leadIdsByTablePre = [];
foreach ($filteredPreLeadsForConversion as $lead) {
    $t = $lead['tabla_origen'] ?? '';
    $id = intval($lead['id'] ?? 0);
    if ($t === '' || $id <= 0) continue;
    if (!isset($leadIdsByTablePre[$t])) $leadIdsByTablePre[$t] = [];
    $leadIdsByTablePre[$t][] = $id;
}

// Build map of contact_form entries and appointments (igual que consulta_leads.php)
$contactFormByLeadPre = [];
$cfIdsPre = [];
foreach ($leadIdsByTablePre as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '') continue;
    $sql = "SELECT id, original_lead_id, cliente FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")";
    $resCf = $conn->query($sql);
    if ($resCf) {
        while ($row = $resCf->fetch_assoc()) {
            $key = $t . '|' . intval($row['original_lead_id']);
            $contactFormByLeadPre[$key] = [
                'cf_id' => intval($row['id']),
                'cliente' => isset($row['cliente']) ? intval($row['cliente']) : 0
            ];
            $cfIdsPre[] = intval($row['id']);
        }
    }
}

$appointmentsByCFIdPre = [];
if (!empty($cfIdsPre)) {
    $cfList = implode(',', array_map('intval', $cfIdsPre));
    $apptRes = $conn->query("SELECT * FROM calendario WHERE idclie IN (" . $cfList . ")");
    if ($apptRes && $apptRes->num_rows > 0) {
        while ($ar = $apptRes->fetch_assoc()) {
            $idclie = isset($ar['idclie']) ? intval($ar['idclie']) : 0;
            if ($idclie <= 0) continue;
            if (!isset($appointmentsByCFIdPre[$idclie])) {
                $appointmentsByCFIdPre[$idclie] = $ar;
            } else {
                $prev = $appointmentsByCFIdPre[$idclie];
                $replace = false;
                if (!empty($ar['fecha']) && !empty($prev['fecha'])) {
                    $t1 = strtotime($ar['fecha'] . ' ' . ($ar['hora'] ?? '')) ?: 0;
                    $t2 = strtotime($prev['fecha'] . ' ' . ($prev['hora'] ?? '')) ?: 0;
                    if ($t1 > $t2) $replace = true;
                } elseif (!empty($ar['id']) && !empty($prev['id'])) {
                    if (intval($ar['id']) > intval($prev['id'])) $replace = true;
                }
                if ($replace) $appointmentsByCFIdPre[$idclie] = $ar;
            }
        }
    }
}

// Build final map of status per lead (tabla|id) - EXACTO como consulta_leads.php
$leadStatusMapPre = [];
foreach ($leadIdsByTablePre as $t => $ids) {
    foreach ($ids as $lid) {
        $key = $t . '|' . intval($lid);
        $leadStatusMapPre[$key] = 'lead'; // default
        if (isset($contactFormByLeadPre[$key])) {
            $cf = $contactFormByLeadPre[$key];
            if (strcasecmp($t, 'eventos_wp') === 0) {
                $cfRow = [
                    'id' => intval($cf['cf_id']),
                    'original_lead_id' => intval($lid),
                    'tabla_origen' => 'eventos_wp',
                    'form_name' => 'eventos_wp',
                    'tipo_cliente' => 1,
                    'cliente' => $cf['cliente'] ?? 0,
                ];
                $cfHydrated = wpEventosCfHydratePlannerContactFormFields($conn, $cfRow);
                $plannerEstatus = wpEventosCfResolvePlannerTipoClienteEstatus($cfHydrated);
                if ($plannerEstatus !== null && $plannerEstatus !== '') {
                    $leadStatusMapPre[$key] = $plannerEstatus;
                    continue;
                }
            }
            if (isset($cf['cliente']) && intval($cf['cliente']) === 1) {
                $leadStatusMapPre[$key] = 'cliente';
                continue;
            }
            $cfId = intval($cf['cf_id']);
            if ($cfId > 0 && isset($appointmentsByCFIdPre[$cfId]) && isset($appointmentsByCFIdPre[$cfId]['estatus'])) {
                $rawStatus = $appointmentsByCFIdPre[$cfId]['estatus'];
                $intStatus = is_numeric($rawStatus) ? intval($rawStatus) : null;
                if ($intStatus === 1) {
                    $leadStatusMapPre[$key] = 'atendido';
                } elseif ($intStatus === 3) {
                    $leadStatusMapPre[$key] = 'muerto';
                } elseif ($intStatus === 0) {
                    $leadStatusMapPre[$key] = 'agendado';
                } elseif ($intStatus === 2) {
                    $leadStatusMapPre[$key] = 'fantasma';
                } else {
                    $leadStatusMapPre[$key] = is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : 'agendado';
                }
            }
        }
    }
}

// Calcular leads que agendaron (agendado, atendido, fantasma, muerto) - ANTES del filtro por estatus
$leadsAgendadosTotal = 0;
$totalLeadsBeforeStatusFilter = count($filteredPreLeadsForConversion);
foreach ($filteredPreLeadsForConversion as $lead) {
    $tabla = $lead['tabla_origen'] ?? '';
    $lid = isset($lead['id']) ? intval($lead['id']) : 0;
    $key = $tabla . '|' . $lid;
    if (isset($leadStatusMapPre[$key])) {
        $status = strtolower($leadStatusMapPre[$key]);
        if (in_array($status, ['agendado', 'atendido', 'fantasma', 'muerto'], true) || in_array($status, ['0','1','2','3'], true)) {
            $leadsAgendadosTotal++;
        }
    }
}

// Calcular tasa de conversión (porcentaje)
$tasaConversion = $totalLeadsBeforeStatusFilter > 0 ? round(($leadsAgendadosTotal / $totalLeadsBeforeStatusFilter) * 100, 2) : 0;

// ===== RATIO DE CONVERSIÓN (Post-qualified leads) =====
// Función auxiliar para detectar leads fantasma (igual que en consulta_post_leads.php)
function isFantasmaLead($lead) {
    $st = $lead['estatus'] ?? '';
    if (is_numeric($st) && intval($st) === 2) return true;
    $stl = mb_strtolower(trim((string)$st), 'UTF-8');
    return $stl === 'fantasma';
}

// REPLICAR EXACTAMENTE la lógica de consulta_post_leads.php
// En consulta_post_leads.php usa $displayLeads que se filtra desde $allLeads

// Obtener TODOS los leads post-qualified (con cita en calendario)
$allPostLeadsForRatio = [];
if (!empty($appointmentIds)) {
    $idsList = implode(',', array_map('intval', $appointmentIds));
    // Excluir registros de Wedding Planners (tabla_origen)
    $sql = "SELECT * FROM contact_form WHERE id IN ($idsList) AND LOWER(COALESCE(tabla_origen, '')) != 'wedding_planners'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($cf = $result->fetch_assoc()) {
            // Defensa adicional: saltar si tabla_origen indica WP
            $tablaOrigenCF = strtolower(trim($cf['tabla_origen'] ?? ''));
            if ($tablaOrigenCF === 'wedding_planners' || $tablaOrigenCF === 'wedding_planner') continue;
            $merged = $cf;
            $merged['submission_date'] = $cf['submission_date'] ?? '';
            $merged['fecha_cambio_cliente'] = $cf['fecha_cambio_cliente'] ?? '';
            // Preserve created_time from contact_form as fallback
            $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';

            // ==== Buscar created_time del lead original (igual que consulta_post_leads.php) ====
            $formNamePoQ = $cf['tabla_origen'] ?? '';
            $origIdPoQ   = intval($cf['original_lead_id'] ?? 0);
            if (!empty($formNamePoQ) && $origIdPoQ > 0) {
                $escapedFormPoQ = $conn->real_escape_string($formNamePoQ);
                $chkTblPoQ = $conn->query("SHOW TABLES LIKE '$escapedFormPoQ'");
                if ($chkTblPoQ && $chkTblPoQ->num_rows > 0) {
                    // Verificar qué columnas de fecha existen para no generar error
                    $colsPoQ = [];
                    $colResPoQ = $conn->query("SHOW COLUMNS FROM `$escapedFormPoQ`");
                    if ($colResPoQ) {
                        while ($colRowPoQ = $colResPoQ->fetch_assoc()) {
                            $colsPoQ[] = $colRowPoQ['Field'];
                        }
                    }
                    $selectColsPoQ = [];
                    if (in_array('created_time', $colsPoQ)) $selectColsPoQ[] = 'created_time';
                    if (in_array('created_at',   $colsPoQ)) $selectColsPoQ[] = 'created_at';
                    if (!empty($selectColsPoQ)) {
                        $selStrPoQ = implode(', ', array_map(fn($c) => "`$c`", $selectColsPoQ));
                        $leadResPoQ = $conn->query("SELECT $selStrPoQ FROM `$escapedFormPoQ` WHERE id = $origIdPoQ LIMIT 1");
                        if ($leadResPoQ && $leadResPoQ->num_rows > 0) {
                            $leadRowPoQ = $leadResPoQ->fetch_assoc();
                            if (!empty($leadRowPoQ['created_time'])) {
                                $merged['created_time'] = $leadRowPoQ['created_time'];
                            } elseif (!empty($leadRowPoQ['created_at'])) {
                                $merged['created_time'] = $leadRowPoQ['created_at'];
                            }
                        }
                    }
                }
            }
            // ==== fin lookup lead original ====
            
            $cid = intval($cf['id']);
            
            // IMPORTANTE: Ocultar leads cuya fecha de cita es inválida (igual que consulta_post_leads.php línea 145-152)
            if ($cid > 0 && isset($appointmentsByClient[$cid])) {
                $appt = $appointmentsByClient[$cid];
                $apptFechaRaw = trim($appt['fecha'] ?? '');
                $apptHoraRaw = trim($appt['hora'] ?? '');
                $apptTs = ($apptFechaRaw !== '') ? strtotime($apptFechaRaw . ' ' . $apptHoraRaw) : false;
                if ($apptFechaRaw === '' || $apptFechaRaw === '0000-00-00' || $apptTs === false || $apptTs <= 0) {
                    // Omitir este registro: la fecha de cita es inválida
                    continue;
                }
            }
            
            // Añadir estatus: eventos_wp usa flujo planner; el resto usa calendario
            $merged['estatus'] = '';
            if ($tablaOrigenCF === 'eventos_wp') {
                $cfRow = array_merge($cf, [
                    'tabla_origen' => 'eventos_wp',
                    'form_name' => 'eventos_wp',
                ]);
                $cfHydrated = wpEventosCfHydratePlannerContactFormFields($conn, $cfRow);
                $plannerEstatus = wpEventosCfResolvePlannerTipoClienteEstatus($cfHydrated);
                if ($plannerEstatus !== null && $plannerEstatus !== '') {
                    $merged['estatus'] = $plannerEstatus;
                }
            } elseif ($cf['cliente'] == 1) {
                $merged['estatus'] = 'cliente';
            } elseif (isset($appointmentsByClient[$cid]) && isset($appointmentsByClient[$cid]['estatus'])) {
                $rawStatus = $appointmentsByClient[$cid]['estatus'];
                $intStatus = is_numeric($rawStatus) ? intval($rawStatus) : null;
                if ($intStatus === 1) {
                    $merged['estatus'] = 'atendido';
                } elseif ($intStatus === 3) {
                    $merged['estatus'] = 'muerto';
                } elseif ($intStatus === 0) {
                    $merged['estatus'] = 'agendado';
                } elseif ($intStatus === 2) {
                    $merged['estatus'] = 'fantasma';
                } else {
                    $merged['estatus'] = $rawStatus;
                }
            }
            
            $allPostLeadsForRatio[] = $merged;
        }
    }
}

// Crear displayLeads filtrado (EXACTO como consulta_post_leads.php)
$displayPostLeadsForRatio = [];
if ($startDate === '' && $endDate === '') {
    $displayPostLeadsForRatio = $allPostLeadsForRatio;
} else {
    $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
    $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
    
    foreach ($allPostLeadsForRatio as $lead) {
        // Usar created_time del lead original si existe, sino submission_date
        // (igual que consulta_post_leads.php)
        $dateField = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
        if (empty($dateField)) continue;
        // Extraer fecha local sin conversión de zona horaria (ISO 8601 o datetime)
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $dateField, $_mPoQ)) continue;
        $d = $_mPoQ[1];
        
        if ($sd && $d < $sd) continue;
        if ($ed && $d > $ed) continue;
        
        $displayPostLeadsForRatio[] = $lead;
    }
}

// Contar igual que consulta_post_leads.php (SIN excluir fantasmas)
$leadsPostCountFiltered = 0;
foreach ($displayPostLeadsForRatio as $lead) {
    $leadsPostCountFiltered++;
}

// Contar clientes cerrados: reusar $leadsCountFiltered (ya calculado con fecha_cambio_cliente)
$leadsWithFechaCambio = $leadsCountFiltered;

// Calcular Tasa de Conversión: clientes cerrados / agendados totales × 100
$conversionRatio = 0;
if ($leadsPostCountFiltered > 0) {
    $conversionRatio = round(($leadsWithFechaCambio / $leadsPostCountFiltered) * 100, 2);
}

// ===== HIT RATE GLOBAL (Atendidos / Clientes × 100) =====
// Mantener conteos base
$totalPreLeadsForHitRate = count($filteredPreLeadsForConversion);
$totalClientesForHitRate = count($displayLeads);

// Contar cuántos post-qualified están en estatus 'atendido' (numerador correcto)
$totalAtendidosPostFiltered = 0;
foreach ($displayPostLeadsForRatio as $lead) {
    $st = isset($lead['estatus']) ? mb_strtolower(trim((string)$lead['estatus']), 'UTF-8') : '';
    if ($st === 'atendido' || $st === '1' || (is_numeric($st) && intval($st) === 1)) {
        $totalAtendidosPostFiltered++;
    }
}

// Calcular hit rate como porcentaje: (Atendidos / Clientes) * 100
$hitRateGlobal = ($totalClientesForHitRate > 0) ? round(($totalAtendidosPostFiltered / $totalClientesForHitRate) * 100, 2) : 0.0; 

// ===== Calcular clientes ingresados hoy y conteos por día (máximo 60 días) =====
$todayClientsCount = 0;
$today = date('Y-m-d');
// Count today's clients from allLeads (no filter) similar to consulta_leads.php
foreach ($allLeads as $lead) {
    // Count using fecha_cambio_cliente (preferred) for 'cliente' metrics
    // Only consider fecha_cambio_cliente for "clientes ingresados hoy" metric
    $fechaRaw = $lead['fecha_cambio_cliente'] ?? '';
    if (empty($fechaRaw))
        continue;
    $ts = strtotime($fechaRaw);
    if ($ts === false)
        continue;
    if (date('Y-m-d', $ts) === $today)
        $todayClientsCount++;
}
$map = [];
$minTs = null;
$maxTs = null;
foreach ($displayLeads as $lead) {
    // Use fecha_cambio_cliente for clients page filter and chart
    // Use only fecha_cambio_cliente for clients page filter and chart
    $ct = $lead['fecha_cambio_cliente'] ?? '';
    if (empty($ct))
        continue;
    $ts = strtotime($ct);
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

// Determinar rango: máximo 60 días hacia atrás desde hoy
$end = strtotime('today');
$start = $end - (59 * 86400); // 60 dias incluyendo hoy
// Ajustar inicio si el primer lead fue más reciente que este rango
if ($minTs !== null) {
    $minDayStart = strtotime(date('Y-m-d', $minTs));
    if ($minDayStart > $start)
        $start = $minDayStart;
}
// Ajustar fin al más reciente registro si existe y no supera hoy
if ($maxTs !== null) {
    $maxDay = strtotime(date('Y-m-d', $maxTs));
    if ($maxDay < $end)
        $end = $maxDay;
}

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

$clientsSeriesJson = json_encode($series);
$clientsDatesJson = json_encode($dates);
$clientsCountsJson = json_encode($counts);

// ===== OBTENER LEADS PRE-CALIFICADOS (de tablas_leads) PARA LA GRÁFICA =====
$tablasLeads = [];
// Excluir tablas de tipo 2 (Wedding Planner)
$sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo != 2 ORDER BY nombre";
$resultTablas = $conn->query($sqlTablas);
if ($resultTablas && $resultTablas->num_rows > 0) {
    while ($row = $resultTablas->fetch_assoc()) {
        $tablasLeads[] = $row['nombre'];
    }
} 

// Consultar todos los leads de todas las tablas de leads pre-calificados
$allPreLeads = [];
foreach ($tablasLeads as $tableName) {
    $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $columns = [];
        $columnsResult = $conn->query("SHOW COLUMNS FROM `$tableName`");
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        $sqlLeads = "SELECT created_time FROM `$tableName` WHERE " .
            (in_array('descartado', $columns) ? "descartado = 0 OR descartado IS NULL" : "1=1");
        $resultLeads = $conn->query($sqlLeads);
        if ($resultLeads && $resultLeads->num_rows > 0) {
            while ($lead = $resultLeads->fetch_assoc()) {
                $allPreLeads[] = $lead;
            }
        }
    }
}

// Construir mapa por día para leads pre-calificados
$preLeadsDayMap = [];
foreach ($allPreLeads as $lead) {
    if (empty($lead['created_time']))
        continue;
    $ts = strtotime($lead['created_time']);
    if ($ts === false)
        continue;
    $d = date('Y-m-d', $ts);
    if (!isset($preLeadsDayMap[$d]))
        $preLeadsDayMap[$d] = 0;
    $preLeadsDayMap[$d]++;
}

// Generar serie de leads pre-calificados usando el mismo rango de fechas
$preLeadsSeries = [];
for ($ts = $start; $ts <= $end; $ts += 86400) {
    $d = date('Y-m-d', $ts);
    $c = isset($preLeadsDayMap[$d]) ? $preLeadsDayMap[$d] : 0;
    $x = $ts * 1000;
    $preLeadsSeries[] = [$x, $c];
}
$preLeadsSeriesJson = json_encode($preLeadsSeries);

// ===== OBTENER LEADS POST-CALIFICADOS (contact_form con cita en calendario) =====
$postLeadsIds = [];
$postLeadsQuery = $conn->query("SELECT DISTINCT idclie FROM calendario");
if ($postLeadsQuery && $postLeadsQuery->num_rows > 0) {
    while ($row = $postLeadsQuery->fetch_assoc()) {
        $postLeadsIds[] = intval($row['idclie']);
    }
}

$postLeadsDayMap = [];
if (!empty($postLeadsIds)) {
    $idsListPost = implode(',', array_map('intval', $postLeadsIds));
    // Excluir registros de Wedding Planners (tabla_origen)
    $sqlPost = "SELECT submission_date FROM contact_form WHERE id IN ($idsListPost) AND LOWER(COALESCE(tabla_origen, '')) != 'wedding_planners'";
    $resPost = $conn->query($sqlPost);
    if ($resPost && $resPost->num_rows > 0) {
        while ($pl = $resPost->fetch_assoc()) {
            $ct = $pl['submission_date'] ?? '';
            if (empty($ct))
                continue;
            $ts = strtotime($ct);
            if ($ts === false)
                continue;
            $d = date('Y-m-d', $ts);
            if (!isset($postLeadsDayMap[$d]))
                $postLeadsDayMap[$d] = 0;
            $postLeadsDayMap[$d]++;
        }
    }
}

// Generar serie de leads post-calificados usando el mismo rango de fechas
$postLeadsSeries = [];
for ($ts = $start; $ts <= $end; $ts += 86400) {
    $d = date('Y-m-d', $ts);
    $c = isset($postLeadsDayMap[$d]) ? $postLeadsDayMap[$d] : 0;
    $x = $ts * 1000;
    $postLeadsSeries[] = [$x, $c];
}
$postLeadsSeriesJson = json_encode($postLeadsSeries);

$packageStyles = [
    'Mini Evento' => ['bg' => 'white', 'color' => 'blue'],
    '2 Foto y 2 Video' => ['bg' => '#006400', 'color' => '#90EE90'],
    '1 Foto' => ['bg' => '#FFB6C1', 'color' => 'red'],
    '1 Video' => ['bg' => '#00008B', 'color' => 'white'],
    '2 Foto' => ['bg' => '#FFFFE0', 'color' => '#696969'],
    '3 foto' => ['bg' => '#DDA0DD', 'color' => 'purple'],
    '2 video' => ['bg' => '#D3D3D3', 'color' => '#696969'],
    '1 Foto 1 Video' => ['bg' => '#ADD8E6', 'color' => '#00008B'],
    '2 Foto 1 Video' => ['bg' => '#90EE90', 'color' => '#006400'],
    '3 Foto y 2 Video' => ['bg' => '#800080', 'color' => 'white'],
];

$packageCounts = [];
foreach ($displayLeads as $lead) {
    $paq = trim($lead['paquete'] ?? '');
    if ($paq !== '' && $paq !== 'sin datos') {
        if (!isset($packageCounts[$paq])) {
            $packageCounts[$paq] = 0;
        }
        $packageCounts[$paq]++;
    }
}
$totalVendidos = array_sum($packageCounts);

// Realizar la consulta SQL para obtener todos los usuarios
$sql = "SELECT * FROM usuarios WHERE tipoUsu = 1";
$result = $conn->query($sql);

$vendedores = array(); // Array para almacenar los resultados

// Revisar que la consulta no falló (evitar ->num_rows sobre boolean false)
if ($result && $result->num_rows > 0) {
    // Si hay resultados, los almacenamos en el array
    while ($row = $result->fetch_assoc()) {
        $vendedores[] = $row; // Agregar cada fila al array
    }
}

$dashVendorMap = dashComercialBuildVendorMap($conn);





// ===== Distribución de cómo nos conocieron (donut chart) =====
$howDidYouMeetCounts = ['Wedding Planner' => 0, 'Community' => 0, 'New Audience' => 0, 'Sin dato' => 0];
foreach ($displayLeads as $lead) {
    $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
    if ($howRaw === '1') $howDidYouMeetCounts['Wedding Planner']++;
    elseif ($howRaw === '2') $howDidYouMeetCounts['Community']++;
    elseif ($howRaw === '3') $howDidYouMeetCounts['New Audience']++;
    else $howDidYouMeetCounts['Sin dato']++;
}
arsort($howDidYouMeetCounts);
$howDidYouMeetTotal = max(1, array_sum($howDidYouMeetCounts));
$wpDeg    = round($howDidYouMeetCounts['Wedding Planner'] / $howDidYouMeetTotal * 360);
$commEnd  = $wpDeg + round($howDidYouMeetCounts['Community'] / $howDidYouMeetTotal * 360);
$nmEnd    = $commEnd + round($howDidYouMeetCounts['New Audience'] / $howDidYouMeetTotal * 360);
$originDonutGradient = "#3B82F6 0deg {$wpDeg}deg, #10B981 {$wpDeg}deg {$commEnd}deg, #A855F7 {$commEnd}deg {$nmEnd}deg, #94a3b8 {$nmEnd}deg 360deg";

// ===== Distribución de engagement (donut chart) =====
$engagementCounts = ['Alto' => 0, 'Medio' => 0, 'Bajo' => 0, 'Sin dato' => 0];
foreach ($displayLeads as $lead) {
    $raw  = trim((string)($lead['engagement'] ?? ($lead['sfm_engagement'] ?? '')));
    $norm = mb_strtolower($raw, 'UTF-8');
    if ($norm === '3' || $norm === 'alto')   $engagementCounts['Alto']++;
    elseif ($norm === '2' || $norm === 'medio') $engagementCounts['Medio']++;
    elseif ($norm === '1' || $norm === 'bajo')  $engagementCounts['Bajo']++;
    else $engagementCounts['Sin dato']++;
}
$engagementTotal = max(1, array_sum($engagementCounts));
$altoEnd  = round($engagementCounts['Alto']  / $engagementTotal * 360);
$medioEnd = $altoEnd  + round($engagementCounts['Medio'] / $engagementTotal * 360);
$bajoEnd  = $medioEnd + round($engagementCounts['Bajo']  / $engagementTotal * 360);
$engDonutGradient = "#10B981 0deg {$altoEnd}deg, #3B82F6 {$altoEnd}deg {$medioEnd}deg, #F59E0B {$medioEnd}deg {$bajoEnd}deg, #94a3b8 {$bajoEnd}deg 360deg";

// ===== Top 5 paquetes para bar chart =====
arsort($packageCounts);
$topPackages = array_slice($packageCounts, 0, 5, true);
$maxPkgCount = max(1, !empty($packageCounts) ? max($packageCounts) : 1);

// ===== ¿Desde cuándo nos conoce? de cierres (how_long_known_us) =====
$howLongKnownUsLabelMap = [
    'less than 3 months'          => 'Menos de 3 meses',
    'between 3 months and 1 year' => 'Entre 3 meses y 1 año',
    'more than 1 year'            => 'Más de 1 año',
    'not asked'                   => 'No se preguntó',
];
$howLongCounts = [];
foreach ($displayLeads as $lead) {
    $raw = trim((string)($lead['how_long_known_us'] ?? ''));
    $lbl = ($raw === '' || $raw === '—') ? 'Sin dato' : ($howLongKnownUsLabelMap[mb_strtolower($raw, 'UTF-8')] ?? $raw);
    $howLongCounts[$lbl] = ($howLongCounts[$lbl] ?? 0) + 1;
}
arsort($howLongCounts);
$howLongMax = max(1, !empty($howLongCounts) ? max($howLongCounts) : 1);

// ===== Campañas de cierres (campaign_name) =====
$campaignCounts = [];
foreach ($displayLeads as $lead) {
    $_refUrl = trim((string)($lead['referrer_url'] ?? ''));
    $_fcc    = trim((string)($lead['first_contact_channel'] ?? ''));
    $cn = formatCampaignDisplayLabel(trim((string)($lead['campaign_name'] ?? '')), $_refUrl, $_fcc);
    if ($cn === '' || $cn === '—') $cn = 'Sin dato';
    $campaignCounts[$cn] = ($campaignCounts[$cn] ?? 0) + 1;
}
arsort($campaignCounts);
$hearLabelMap = [
    '1' => 'Meta Ads',
    '2' => 'SEO / Search',
    '3' => 'Colaboración',
    '4' => 'Prensa / Revista',
    '5' => 'Otro',
];
$newMarketSubCounts = [];
foreach ($displayLeads as $lead) {
    $howRaw  = trim((string)($lead['how_did_you_meet'] ?? ''));
    $hearRaw = trim((string)($lead['hear_about_us'] ?? ''));
    if ($howRaw !== '3') continue;
    $k = ($hearRaw !== '') ? ($hearLabelMap[$hearRaw] ?? $hearRaw) : 'Sin dato';
    $newMarketSubCounts[$k] = ($newMarketSubCounts[$k] ?? 0) + 1;
}
arsort($newMarketSubCounts);
$newMarketSubTotal = max(1, array_sum($newMarketSubCounts));
$newMarketSubMax   = !empty($newMarketSubCounts) ? max($newMarketSubCounts) : 1;

// ─── Variables extra para la sección de Reportes (nuevo diseño) ───────────────

// Etiqueta del periodo para el chip de rango
$reportRangeLabel = 'Todos los registros';
if (($startDate !== '' || $endDate !== '') && empty($_GET['show_all'])) {
    $reportRangeStart = $startDate !== '' ? date('d/m/Y', strtotime($startDate)) : '...';
    $reportRangeEnd   = $endDate   !== '' ? date('d/m/Y', strtotime($endDate))   : '...';
    $reportRangeLabel = $reportRangeStart . ' → ' . $reportRangeEnd;
}

// JSON para gráfica de pie "Método de contacto" (Highcharts) — usa la columna `first_contact_channel`
$contactMethodCounts = [];
foreach ($displayLeads as $lead) {
    $label = normalizeFirstContactChannelLabel($lead['first_contact_channel'] ?? '');
    if ($label === '') $label = 'Sin dato';
    $contactMethodCounts[$label] = ($contactMethodCounts[$label] ?? 0) + 1;
}
arsort($contactMethodCounts);

$clientsConOrigenConocido = 0;
$clientsLlamadaRealizada = 0;
$clientsPaqueteConfirmado = 0;
$clientsOriginPendingCount = 0;
$clientsEngagementHigh = 0;
foreach ($displayLeads as $lead) {
    $originRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
    $officialSession = mb_strtolower(trim((string)($lead['sesion_oficial'] ?? '')), 'UTF-8');
    $engagementRaw = mb_strtolower(trim((string)($lead['engagement'] ?? ($lead['sfm_engagement'] ?? ''))), 'UTF-8');
    $packageRaw = trim((string)($lead['paquete'] ?? ''));
    $legacyPackageRaw = trim((string)($lead['paquete_cotizado'] ?? ''));

    if (in_array($originRaw, ['1', '2', '3'], true)) {
        $clientsConOrigenConocido++;
    } else {
        $clientsOriginPendingCount++;
    }

    if (in_array($officialSession, ['si', 'sí'], true)) {
        $clientsLlamadaRealizada++;
    }

    if (
        ($packageRaw !== '' && $packageRaw !== '—' && $packageRaw !== '0' && mb_strtolower($packageRaw, 'UTF-8') !== 'sin datos') ||
        ($legacyPackageRaw !== '' && $legacyPackageRaw !== '—')
    ) {
        $clientsPaqueteConfirmado++;
    }

    if ($engagementRaw === '3' || $engagementRaw === 'alto') {
        $clientsEngagementHigh++;
    }
}

$clientsEventWpIds = [];
foreach ($displayLeads as $lead) {
    if (strcasecmp((string) ($lead['tabla_origen'] ?? ''), 'eventos_wp') !== 0) {
        continue;
    }

    $eventId = intval($lead['original_lead_id'] ?? 0);
    if ($eventId > 0) {
        $clientsEventWpIds[$eventId] = $eventId;
    }
}

$clientsEventWpAfianzadosMap = [];
if (!empty($clientsEventWpIds)) {
    $clientsEventIdsList = implode(',', array_map('intval', array_values($clientsEventWpIds)));
    $sqlClientsEventWpAfianzados = "SELECT e.id, COALESCE(wp.afianzado, 0) AS afianzado FROM eventos_wp e LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id WHERE e.id IN ($clientsEventIdsList)";
    $resClientsEventWpAfianzados = $conn->query($sqlClientsEventWpAfianzados);
    if ($resClientsEventWpAfianzados) {
        while ($rowClientsEventWp = $resClientsEventWpAfianzados->fetch_assoc()) {
            $clientsEventWpAfianzadosMap[intval($rowClientsEventWp['id'] ?? 0)] = intval($rowClientsEventWp['afianzado'] ?? 0) === 1 ? 1 : 0;
        }
    }
}

$clientsWpAfianzadosCount = 0;
foreach ($displayLeads as $lead) {
    if (strcasecmp((string) ($lead['tabla_origen'] ?? ''), 'eventos_wp') !== 0) {
        continue;
    }

    $eventId = intval($lead['original_lead_id'] ?? 0);
    if (intval($clientsEventWpAfianzadosMap[$eventId] ?? 0) === 1) {
        $clientsWpAfianzadosCount++;
    }
}

$clientsConversionRate = $leadsPostCountFiltered > 0
    ? round(($leadsCountFiltered / $leadsPostCountFiltered) * 100, 2)
    : 0.0;

$howContactPieData = [];
$howContactLabelColors = [
    'WhatsApp'                      => '#3B82F6',
    'Instagram DM - Campaña'        => '#10B981',
    'Instagram DM - Orgánico'       => '#A855F7',
    'Correo electrónico'            => '#F59E0B',
    'Phone call'                    => '#EF4444',
    'Sin dato'                      => '#94a3b8',
];
foreach ($contactMethodCounts as $hlabel => $hcount) {
    if ($hcount <= 0) continue;
    $howContactPieData[] = ['name' => $hlabel, 'y' => $hcount, 'color' => ($howContactLabelColors[$hlabel] ?? null)];
}
$howContactPieJson = json_encode($howContactPieData, JSON_UNESCAPED_UNICODE);

// JSON para gráfica de pie "¿Desde cuándo nos conoce? de cierres" (Highcharts)
$howLongPieData = [];
$howLongColorMap = [
    'Menos de 3 meses'      => '#C5A028',
    'Entre 3 meses y 1 año' => '#3B82F6',
    'Más de 1 año'          => '#10B981',
    'No se preguntó'        => '#A855F7',
    'Sin dato'              => '#94a3b8',
];
foreach ($howLongCounts as $llabel => $lcount) {
    if ($lcount <= 0) continue;
    $howLongPieData[] = ['name' => $llabel, 'y' => $lcount, 'color' => ($howLongColorMap[$llabel] ?? null)];
}
$howLongPieJson = json_encode($howLongPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// JSON para gráfica de pie "De dónde nos conocen" (Highcharts) — usa la columna `how_did_you_meet`
$whereKnowPieData = [];
$whereKnowColorMap = [
    'Wedding Planner' => '#3B82F6',
    'Community'       => '#10B981',
    'New Audience'      => '#A855F7',
    'Sin dato'        => '#94a3b8',
];
foreach ($howDidYouMeetCounts as $wlabel => $wcount) {
    if ($wcount <= 0) continue;
    $whereKnowPieData[] = ['name' => $wlabel, 'y' => $wcount, 'color' => ($whereKnowColorMap[$wlabel] ?? null)];
}
$whereKnowPieJson = json_encode($whereKnowPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// clientsDatesJson / clientsCountsJson ya están generados arriba
// Alias para usar el mismo nombre de variable que en los scripts de reportes
$datesJson  = $clientsDatesJson  ?? json_encode([]);
$countsJson = $clientsCountsJson ?? json_encode([]);

// ===== DATA PARA REPORTE PRE-Q (replica consulta_leads.php) =====

// Serie por día Pre-Q
$preQDayMap = [];
$preQGlobalMin = null;
$preQGlobalMax = null;
foreach ($filteredPreLeadsForConversion as $lead) {
    if (empty($lead['created_time'])) continue;
    $rawCT = $lead['created_time'];
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $rawCT, $_mPQ)) continue;
    $d = $_mPQ[1];
    $ts = strtotime($rawCT);
    if ($ts === false) continue;
    if (!isset($preQDayMap[$d])) $preQDayMap[$d] = 0;
    $preQDayMap[$d]++;
    if ($preQGlobalMin === null || $ts < $preQGlobalMin) $preQGlobalMin = $ts;
    if ($preQGlobalMax === null || $ts > $preQGlobalMax) $preQGlobalMax = $ts;
}
$preQDates = []; $preQCounts = [];
if ($preQGlobalMin !== null) {
    if ($startDate === '' && $endDate === '') {
        $preQEnd   = strtotime('today');
        $preQStart = strtotime(date('Y-m-d', strtotime('-59 days')));
    } else {
        $preQStart = strtotime(date('Y-m-d', $preQGlobalMin));
        $preQEnd   = strtotime(date('Y-m-d', $preQGlobalMax));
        if ($startDate !== '') { $sdTsPQ = strtotime($startDate); if ($sdTsPQ !== false) $preQStart = $sdTsPQ; }
        if ($endDate   !== '') { $edTsPQ = strtotime($endDate);   if ($edTsPQ !== false) $preQEnd   = $edTsPQ; }
    }
    if ($preQEnd < $preQStart) $preQEnd = $preQStart;
    for ($ts = $preQStart; $ts <= $preQEnd; $ts += 86400) {
        $d = date('Y-m-d', $ts);
        $preQDates[]  = $d;
        $preQCounts[] = isset($preQDayMap[$d]) ? $preQDayMap[$d] : 0;
    }
}
$preQDatesJson  = json_encode($preQDates);
$preQCountsJson = json_encode($preQCounts);

// Pie de método de contacto Pre-Q
$preQContactCounts = [];
foreach ($filteredPreLeadsForConversion as $lead) {
    $lbl = normalizeFirstContactChannelLabel($lead['first_contact_channel'] ?? '');
    $preQContactCounts[$lbl] = ($preQContactCounts[$lbl] ?? 0) + 1;
}
arsort($preQContactCounts);
$preQContactPieData = [];
foreach ($preQContactCounts as $lbl => $cnt) {
    if ($cnt <= 0) continue;
    $preQContactPieData[] = ['name' => $lbl, 'y' => $cnt];
}
$preQContactPieJson = json_encode($preQContactPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Pie "desde cuándo nos conoce" Pre-Q
$preQKnownUsLabelMap = [
    'less than 3 months'          => 'Menos de 3 meses',
    'between 3 months and 1 year' => 'Entre 3 meses y 1 año',
    'more than 1 year'            => 'Más de 1 año',
    'not asked'                   => 'No se preguntó',
];
$preQKnownUsCounts = [];
foreach ($filteredPreLeadsForConversion as $lead) {
    $raw = trim((string)($lead['how_long_known_us'] ?? ''));
    $lbl = ($raw === '' || $raw === '—') ? 'Sin dato' : ($preQKnownUsLabelMap[mb_strtolower($raw, 'UTF-8')] ?? $raw);
    $preQKnownUsCounts[$lbl] = ($preQKnownUsCounts[$lbl] ?? 0) + 1;
}
arsort($preQKnownUsCounts);
$preQKnownUsPieData = [];
foreach ($preQKnownUsCounts as $lbl => $cnt) {
    if ($cnt <= 0) continue;
    $preQKnownUsPieData[] = ['name' => $lbl, 'y' => $cnt];
}
$preQKnownUsPieJson = json_encode($preQKnownUsPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ===== DATA PARA REPORTE POST-Q (replica consulta_post_leads.php) =====

// Serie por día Post-Q
$postQDayMap = [];
$postQGlobalMin = null;
$postQGlobalMax = null;
foreach ($displayPostLeadsForRatio as $lead) {
    $df = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
    if (empty($df)) continue;
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $df, $_mPostQ)) continue;
    $d  = $_mPostQ[1];
    $ts = strtotime($df);
    if ($ts === false) continue;
    if (!isset($postQDayMap[$d])) $postQDayMap[$d] = 0;
    $postQDayMap[$d]++;
    if ($postQGlobalMin === null || $ts < $postQGlobalMin) $postQGlobalMin = $ts;
    if ($postQGlobalMax === null || $ts > $postQGlobalMax) $postQGlobalMax = $ts;
}
$postQDates = []; $postQCounts = [];
if ($postQGlobalMin !== null) {
    if ($startDate === '' && $endDate === '') {
        $postQEnd   = strtotime('today');
        $postQStart = strtotime(date('Y-m-d', strtotime('-59 days')));
    } else {
        $postQStart = strtotime(date('Y-m-d', $postQGlobalMin));
        $postQEnd   = strtotime(date('Y-m-d', $postQGlobalMax));
        if ($startDate !== '') { $sdTsPoQ = strtotime($startDate); if ($sdTsPoQ !== false) $postQStart = $sdTsPoQ; }
        if ($endDate   !== '') { $edTsPoQ = strtotime($endDate);   if ($edTsPoQ !== false) $postQEnd   = $edTsPoQ; }
    }
    if ($postQEnd < $postQStart) $postQEnd = $postQStart;
    for ($ts = $postQStart; $ts <= $postQEnd; $ts += 86400) {
        $d = date('Y-m-d', $ts);
        $postQDates[]  = $d;
        $postQCounts[] = isset($postQDayMap[$d]) ? $postQDayMap[$d] : 0;
    }
}
$postQDatesJson  = json_encode($postQDates);
$postQCountsJson = json_encode($postQCounts);

// Pie "Dónde nos conoció el cliente" Post-Q (método de contacto)
$postQContactCounts = [];
foreach ($displayPostLeadsForRatio as $lead) {
    $lbl = normalizeFirstContactChannelLabel($lead['first_contact_channel'] ?? '');
    $postQContactCounts[$lbl] = ($postQContactCounts[$lbl] ?? 0) + 1;
}
arsort($postQContactCounts);
$postQContactPieData = [];
foreach ($postQContactCounts as $lbl => $cnt) {
    if ($cnt <= 0) continue;
    $postQContactPieData[] = ['name' => $lbl, 'y' => $cnt];
}
$postQContactPieJson = json_encode($postQContactPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Pie "desde cuándo nos conoce" Post-Q
$postQKnownUsLabelMap = [
    'less than 3 months'          => 'Menos de 3 meses',
    'between 3 months and 1 year' => 'Entre 3 meses y 1 año',
    'more than 1 year'            => 'Más de 1 año',
    'not asked'                   => 'No se preguntó',
];
$postQKnownUsCounts = [];
foreach ($displayPostLeadsForRatio as $lead) {
    $raw = trim((string)($lead['how_long_known_us'] ?? ''));
    $lbl = ($raw === '' || $raw === '—') ? 'Sin dato' : ($postQKnownUsLabelMap[mb_strtolower($raw, 'UTF-8')] ?? $raw);
    $postQKnownUsCounts[$lbl] = ($postQKnownUsCounts[$lbl] ?? 0) + 1;
}
arsort($postQKnownUsCounts);
$postQKnownUsPieData = [];
foreach ($postQKnownUsCounts as $lbl => $cnt) {
    if ($cnt <= 0) continue;
    $postQKnownUsPieData[] = ['name' => $lbl, 'y' => $cnt];
}
$postQKnownUsPieJson = json_encode($postQKnownUsPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Tasa asistencia Post-Q (Atendidos / Total agendados)
$postQAsistenciaRate = ($leadsPostCountFiltered > 0)
    ? round(($totalAtendidosPostFiltered / $leadsPostCountFiltered) * 100, 2)
    : 0.0;

// Cerrar la conexión (se puede reabrir más tarde si es necesario)

?>


<div class="mlb-leads-tab-content mlb-leads-tab-clientes">
<div class="efege-postq">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --gold:         #C5A028;
            --gold-dim:     rgba(197,160,40,0.12);
            --gold-border:  rgba(197,160,40,0.30);
            --bg:           #f8fafc;
            --panel:        #ffffff;
            --surface:      #f1f5f9;
            --ink:          #0f172a;
            --ink-soft:     #1e293b;
            --muted:        #64748b;
            --border:       rgba(0,0,0,0.08);
            --planner:      #3B82F6;
            --planner-bg:   rgba(59,130,246,0.10);
            --community:    #10B981;
            --comm-bg:      rgba(16,185,129,0.10);
            --newmarket:    #A855F7;
            --new-bg:       rgba(168,85,247,0.10);
            --eng-low:      #F59E0B;
            --eng-mid:      #3B82F6;
            --eng-high:     #10B981;
            --radius:       12px;
        }
        body { background: var(--bg) !important; }
        .efege-postq {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
            font-size: 13px;
            padding: 0 20px 60px;
        }

        /* ── PAGE HEADER ── */
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
            font-family: 'DM Sans', sans-serif;
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
            white-space: nowrap;
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

        /* ── KPI STRIP ── */
        .efege-postq .kpi-strip {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .efege-postq .kpi-card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: box-shadow 0.2s;
        }
        .efege-postq .kpi-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
        .efege-postq .kpi-label {
            font-size: 10px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
            font-weight: 600;
        }
        .efege-postq .kpi-value {
            font-size: 30px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1;
            margin-bottom: 6px;
        }
        .efege-postq .kpi-sub { font-size: 11px; color: var(--muted); }
        .efege-postq .kpi-tag {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        /* ── FILTER BAR ── */
        .efege-postq .filter-bar {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px 20px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            position: sticky;
            top: 20px;
            z-index: 100;
        }
        .efege-postq .filter-label {
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 600;
            flex-shrink: 0;
        }
        .efege-postq .filter-select,
        .efege-postq .filter-date {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            font-weight: 500;
            padding: 7px 12px;
            cursor: pointer;
            outline: none;
            transition: border-color 0.2s;
        }
        .efege-postq .filter-select {
            padding-right: 28px;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }
        .efege-postq .filter-select:focus,
        .efege-postq .filter-date:focus { border-color: var(--gold); }
        .efege-postq .filter-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 18px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .efege-postq .filter-btn-primary { background: var(--ink); border-color: var(--ink); color: #fff; }
        .efege-postq .filter-btn-primary:hover { background: #334155; border-color: #334155; color: #fff; }
        .efege-postq .filter-btn-reset { background: transparent; border-color: var(--border); color: var(--muted); }
        .efege-postq .filter-btn-reset:hover { border-color: var(--gold); color: var(--gold); }
        .efege-postq .efege-btn {
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
            font-family: 'DM Sans', sans-serif;
            white-space: nowrap;
        }
        .efege-postq .efege-btn:hover { border-color: var(--gold); color: var(--gold); text-decoration: none; }
        .efege-postq .efege-btn-success { background: #059669; border-color: #059669; color: #fff; }
        .efege-postq .efege-btn-success:hover { background: #047857; border-color: #047857; color: #fff; }
        .efege-postq .efege-filter-search {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            padding: 7px 12px;
            outline: none;
            transition: border-color 0.2s;
            width: 220px;
        }
        .efege-postq .efege-filter-search:focus { border-color: var(--gold); }

        /* ── REPORTES SECTION ── */
        .efege-postq .reports-section {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 16px;
        }
        .efege-postq .reports-section-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .efege-postq .reports-section-step {
            font-size: 11px;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: var(--gold);
            font-weight: 700;
            margin-bottom: 6px;
        }
        .efege-postq .reports-section-title { font-size: 18px; font-weight: 700; color: var(--ink); margin: 0 0 4px; }
        .efege-postq .reports-section-subtitle { font-size: 12px; color: var(--muted); margin: 0; }
        .efege-postq .reports-range-chip {
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
        .efege-postq .reports-layout {
            display: grid;
            grid-template-columns: minmax(160px, 0.4fr) 1fr;
            gap: 16px;
            align-items: start;
        }
        .efege-postq .reports-kpi-stack { display: grid; gap: 12px; }
        .efege-postq .reports-kpi-grid-secondary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .efege-postq .reports-charts-row { display: flex; gap: 12px; }
        .efege-postq .reports-charts-row .report-chart-card { flex: 1 1 0; min-width: 0; }
        .efege-postq .report-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
        }
        .efege-postq .report-card-highlight {
            background: linear-gradient(135deg, rgba(197,160,40,0.10), rgba(197,160,40,0.04));
            border-color: rgba(197,160,40,0.22);
        }
        .efege-postq .report-card-label {
            font-size: 11px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 10px;
        }
        .efege-postq .report-card-value {
            font-size: 30px;
            line-height: 1;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 8px;
        }
        .efege-postq .report-card-note { font-size: 12px; color: var(--muted); }
        .efege-postq .report-formula {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed var(--border);
            font-size: 12px;
            color: var(--ink-soft);
        }
        .efege-postq .report-formula strong { color: var(--ink); }
        .efege-postq .report-chart-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
        }
        .efege-postq .report-chart-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }
        .efege-postq .report-chart-title { font-size: 15px; font-weight: 700; color: var(--ink); margin: 0 0 4px; }
        .efege-postq .report-chart-subtitle { font-size: 12px; color: var(--muted); margin: 0; }
        .efege-postq .report-chart { width: 100%; min-height: 240px; }
        .efege-postq .report-overview-grid,
        .efege-postq .report-breakdown-stack { display: grid; gap: 16px; }
        .efege-postq .report-chart-card-full { width: 100%; }
        .efege-postq .report-metric-grid,
        .efege-postq .report-breakdown-grid { display: grid; gap: 12px; }
        .efege-postq .report-metric-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .efege-postq .report-breakdown-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .efege-postq .report-breakdown-card,
        .efege-postq .report-insight {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
        }
        .efege-postq .report-breakdown-list { display: grid; gap: 10px; margin-top: 14px; }
        .efege-postq .report-breakdown-item { display: grid; gap: 8px; }
        .efege-postq .report-breakdown-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .efege-postq .report-breakdown-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
            min-width: 0;
        }
        .efege-postq .report-breakdown-meta {
            flex-shrink: 0;
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
        }
        .efege-postq .report-breakdown-bar {
            width: 100%;
            height: 8px;
            border-radius: 999px;
            background: var(--panel);
            overflow: hidden;
        }
        .efege-postq .report-breakdown-fill {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, rgba(37,99,235,0.85), rgba(197,160,40,0.7));
        }
        .efege-postq .report-breakdown-empty { margin: 0; font-size: 12px; color: var(--muted); }
        .efege-postq .report-origin-pending {
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
        .efege-postq .report-origin-pending strong { font-weight: 700; }
        .efege-postq .report-insight {
            background: linear-gradient(135deg, rgba(197,160,40,0.10), rgba(37,99,235,0.05));
            border-color: rgba(197,160,40,0.22);
        }
        .efege-postq .report-insight-kicker {
            font-size: 11px;
            letter-spacing: 1.1px;
            text-transform: uppercase;
            color: var(--gold);
            font-weight: 700;
            margin-bottom: 8px;
        }
        .efege-postq .report-insight-text {
            margin: 0;
            font-size: 18px;
            line-height: 1.4;
            color: var(--ink);
            font-weight: 600;
        }
        .efege-postq .report-insight-text span { color: var(--gold); }
        .efege-postq .report-insight-detail { margin: 8px 0 0; font-size: 12px; color: var(--muted); }
        @media (max-width: 900px) {
            .efege-postq .reports-layout,
            .efege-postq .reports-kpi-grid-secondary,
            .efege-postq .report-metric-grid,
            .efege-postq .report-breakdown-grid { grid-template-columns: 1fr; }
            .efege-postq .reports-charts-row { flex-direction: column; }
        }

        /* ── ACCORDION REPORTES ── */
        .efege-postq .report-accordion {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-top: 32px;
            overflow: hidden;
        }
        .efege-postq .report-accordion-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            cursor: pointer;
            user-select: none;
            gap: 12px;
            border-bottom: 1px solid transparent;
            transition: background 0.15s, border-color 0.2s;
        }
        .efege-postq .report-accordion-header:hover { background: var(--surface); }
        .efege-postq .report-accordion.is-open > .report-accordion-header { border-bottom-color: var(--border); }
        .efege-postq .report-accordion-title-group { flex: 1 1 0; min-width: 0; }
        .efege-postq .report-accordion-label {
            font-size: 11px;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: var(--gold);
            font-weight: 700;
            margin-bottom: 4px;
        }
        .efege-postq .report-accordion-title { font-size: 16px; font-weight: 700; color: var(--ink); margin: 0; }
        .efege-postq .report-accordion-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .efege-postq .report-accordion-caret {
            width: 28px; height: 28px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: var(--surface);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
            color: var(--muted);
            transition: transform 0.25s ease, background 0.2s, color 0.2s, border-color 0.2s;
            flex-shrink: 0;
        }
        .efege-postq .report-accordion.is-open .report-accordion-caret {
            transform: rotate(180deg);
            background: var(--gold);
            color: #fff;
            border-color: var(--gold);
        }
        .efege-postq .report-accordion-body {
            overflow: hidden;
            max-height: 0;
            transition: max-height 0.38s ease;
        }
        .efege-postq .report-accordion.is-open .report-accordion-body { max-height: 9999px; }
        .efege-postq .report-accordion-inner { padding: 20px; }

        /* ── TABLE WRAP ── */
        .efege-postq .table-wrap {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .efege-postq .tw-header,
        .efege-postq .table-header-custom {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .efege-postq .tw-title,
        .efege-postq .table-title { font-size: 13px; font-weight: 600; color: var(--ink); }
        .efege-postq .tw-count,
        .efege-postq .table-count { font-size: 11px; color: var(--muted); }
        .efege-postq .tw-scroll,
        .efege-postq .efege-table-scroll {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 820px;
            width: 100%;
            scrollbar-width: thin;
            scrollbar-color: var(--border) transparent;
        }
        .efege-postq .efege-table-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .efege-postq .efege-table-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        .efege-postq .dataTables_scrollBody { scrollbar-width: thin; scrollbar-color: var(--border,#e2e8f0) transparent; }
        .efege-postq .dataTables_scrollBody::-webkit-scrollbar { height: 6px; }
        .efege-postq .dataTables_scrollBody::-webkit-scrollbar-track { background: transparent; }
        .efege-postq .dataTables_scrollBody::-webkit-scrollbar-thumb { background: var(--border,#e2e8f0); border-radius: 3px; }

        /* ── TABLE STYLES ── */
        .efege-postq table.dt-table,
        .efege-postq table.efege-table { width: 100% !important; border-collapse: collapse; font-family: 'DM Sans',sans-serif; font-size: 13px; }
        .efege-postq table.dt-table thead th,
        .efege-postq table.efege-table thead th {
            text-align: left; padding: 10px 16px;
            font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase;
            color: var(--muted); border-bottom: 1px solid var(--border);
            background: var(--surface); white-space: nowrap; font-weight: 600;
        }
        .efege-postq table.dt-table tbody tr,
        .efege-postq table.efege-table tbody tr { border-bottom: 1px solid rgba(0,0,0,0.04); transition: background 0.15s; }
        .efege-postq table.dt-table tbody tr:last-child,
        .efege-postq table.efege-table tbody tr:last-child { border-bottom: none; }
        .efege-postq table.dt-table tbody tr:hover,
        .efege-postq table.efege-table tbody tr:hover { background: var(--surface); }
        .efege-postq table.dt-table td,
        .efege-postq table.efege-table td { padding: 12px 16px; vertical-align: middle; white-space: nowrap; color: var(--ink); max-width: 220px; overflow: hidden; text-overflow: ellipsis; cursor: default; }
        .efege-postq table.dt-table td[data-column="nombre"],
        .efege-postq table.efege-table td[data-column="nombre"] { max-width: 180px; }
        .efege-postq table.dt-table td[data-column="boda"],
        .efege-postq table.dt-table td[data-column="que_se_les_vendio"],
        .efege-postq table.dt-table td[data-column="paquete"],
        .efege-postq table.efege-table td[data-column="boda"],
        .efege-postq table.efege-table td[data-column="que_se_les_vendio"],
        .efege-postq table.efege-table td[data-column="paquete"] { max-width: 200px; }
        .efege-postq table.dt-table td[data-column="metodo_contacto"],
        .efege-postq table.dt-table td[data-column="tipo_cliente"],
        .efege-postq table.dt-table td[data-column="desde_conoce"],
        .efege-postq table.dt-table td[data-column="origen_cliente"],
        .efege-postq table.efege-table td[data-column="metodo_contacto"],
        .efege-postq table.efege-table td[data-column="tipo_cliente"],
        .efege-postq table.efege-table td[data-column="desde_conoce"],
        .efege-postq table.efege-table td[data-column="origen_cliente"] { max-width: 160px; }

        /* ── CELL TOOLTIP ── */
        .td-tooltip {
            position: fixed;
            z-index: 9999;
            background: rgba(15,23,42,0.94);
            color: #fff;
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-family: 'DM Sans', sans-serif;
            max-width: 380px;
            white-space: normal;
            word-break: break-word;
            line-height: 1.45;
            pointer-events: none;
            box-shadow: 0 4px 18px rgba(0,0,0,0.22);
            opacity: 0;
            transition: opacity 0.15s;
        }
        .td-tooltip.visible { opacity: 1; }
        .efege-postq .td-name { font-weight: 600; color: var(--ink); font-size: 13px; }
        .efege-postq .td-sub  { font-size: 11px; color: var(--muted); margin-top: 2px; }
        .efege-postq .td-inline-muted { font-size: 11px; color: var(--muted); }

        /* ── STATUS PILLS ── */
        .efege-postq .status-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .efege-postq .status-agendado  { background: rgba(59,130,246,0.10);  color: #1d4ed8; }
        .efege-postq .status-atendido  { background: rgba(16,185,129,0.10);  color: #047857; }
        .efege-postq .status-fantasma  { background: rgba(239,68,68,0.10);   color: #b91c1c; }
        .efege-postq .status-muerto    { background: rgba(100,116,139,0.10); color: #475569; }
        .efege-postq .status-cliente   { background: rgba(197,160,40,0.12);  color: #92700c; border: 1px solid rgba(197,160,40,0.30); }
        .efege-postq .status-default   { background: var(--gold-dim);        color: #92700c; }

        .efege-postq td[data-column="estatus"] .td-sub-fecha-cliente {
            margin-top: 4px;
            font-size: 10px;
            line-height: 1.2;
            white-space: normal;
            max-width: 140px;
            margin-left: auto;
            margin-right: auto;
        }

        .mlb-cierre-date-cell {
            cursor: pointer;
            color: var(--planner);
            font-weight: 600;
            text-decoration: underline dotted;
            text-underline-offset: 3px;
            transition: color 0.15s ease;
        }

        .mlb-cierre-date-cell:hover {
            color: #1d4ed8;
        }

        .mlb-cierre-date-cell.is-empty {
            color: var(--muted);
            font-weight: 500;
        }

        .mlb-cierre-date-hint {
            display: block;
            margin-top: 3px;
            font-size: 10px;
            font-weight: 500;
            color: var(--muted);
        }

        /* ── ENGAGEMENT PILLS ── */
        .efege-postq .eng-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .efege-postq .eng-low  { background: rgba(245,158,11,0.12); color: #B45309; }
        .efege-postq .eng-mid  { background: rgba(59,130,246,0.12); color: #1d4ed8; }
        .efege-postq .eng-high { background: rgba(16,185,129,0.12); color: #047857; }

        /* ── ORIGIN BADGE ── */
        .efege-postq .origin-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .efege-postq .origin-planner  { background: var(--planner-bg); color: var(--planner); }
        .efege-postq .origin-community{ background: var(--comm-bg);    color: var(--community); }
        .efege-postq .origin-newmarket{ background: var(--new-bg);     color: var(--newmarket); }
        .efege-postq .origin-default  { background: var(--surface);    color: var(--muted); border: 1px solid var(--border); }
        .efege-postq .badge,
        .efege-postq .ch-badge,
        .efege-postq .badge-campaign,
        .efege-postq .badge-contact-method,
        .efege-postq .badge-tipo-cliente {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }
        .efege-postq .badge-planner { background: var(--planner-bg); color: var(--planner); }
        .efege-postq .badge-community { background: var(--comm-bg); color: var(--community); }
        .efege-postq .badge-newmarket { background: var(--new-bg); color: var(--newmarket); }
        .efege-postq .badge-neutral,
        .efege-postq .ch-badge { background: var(--surface); color: var(--muted); border: 1px solid var(--border); }
        .efege-postq .badge-campaign,
        .efege-postq .badge-contact-method,
        .efege-postq .badge-tipo-cliente {
            letter-spacing: .05em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .efege-postq .badge-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex-shrink: 0; }

        /* ── ACTION BUTTON ── */
        .efege-postq .action-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 13px; border-radius: 20px; font-size: 11px; font-weight: 600;
            border: 1px solid var(--border); background: var(--surface); color: var(--ink);
            cursor: pointer; transition: all 0.2s; font-family: 'DM Sans',sans-serif; white-space: nowrap;
        }
        .efege-postq .action-btn:hover { background: var(--gold-dim); border-color: var(--gold-border); color: #92700c; }

        /* ── INLINE SELECT ── */
        .efege-postq table.dt-table .lead-update,
        .efege-postq table.dt-table .form-select,
        .efege-postq table.efege-table .lead-update,
        .efege-postq table.efege-table .form-select {
            font-size: 11px; padding: 4px 24px 4px 8px; border-radius: 6px;
            border: 1px solid var(--border); background: var(--surface); color: var(--ink);
            min-width: 130px; appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 8px center;
            cursor: pointer; transition: border-color 0.2s; font-family: 'DM Sans',sans-serif;
        }
        .efege-postq table.dt-table .lead-update:focus,
        .efege-postq table.dt-table .form-select:focus,
        .efege-postq table.efege-table .lead-update:focus,
        .efege-postq table.efege-table .form-select:focus { border-color: var(--gold); outline: none; }

        /* ── DATATABLES OVERRIDES ── */
        .efege-postq .dataTables_wrapper .dataTables_filter label { font-size: 12px; color: var(--muted); }
        .efege-postq .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--border); border-radius: 7px; padding: 6px 12px;
            font-size: 12px; background: var(--surface); color: var(--ink);
            font-family: 'DM Sans',sans-serif; outline: none;
        }
        .efege-postq .dataTables_wrapper .dataTables_filter input:focus { border-color: var(--gold); }
        .efege-postq .dataTables_wrapper .dt-buttons button,
        .efege-postq .dataTables_wrapper .dt-buttons a {
            border-radius: 7px; font-size: 12px; font-weight: 600; padding: 7px 14px;
            background: #059669; color: #fff; border: none;
            font-family: 'DM Sans',sans-serif; cursor: pointer; transition: background 0.2s;
        }
        .efege-postq .dataTables_wrapper .dt-buttons button:hover { background: #047857; }
        .efege-postq .dataTables_wrapper .dataTables_info { font-size: 11px; color: var(--muted); }

        /* ── COLUMN FILTER SYSTEM ── */
        .efege-postq .filter-icon { cursor: pointer; margin-left: 5px; font-size: 0.75rem; color: var(--muted); transition: color 0.2s; }
        .efege-postq .filter-icon:hover { color: var(--ink); }
        .efege-postq .filter-icon.active { color: var(--planner); }
        .efege-postq .th-flex-label { display: flex; align-items: flex-start; gap: 3px; }
        .efege-postq .th-flex-label .th-compact-label { flex: 1; }
        .efege-postq .th-flex-label .filter-icon { flex-shrink: 0; margin-top: 1px; }
        .efege-postq .th-compact-label { display: block; white-space: normal !important; word-break: normal; overflow-wrap: normal; line-height: 1.15; }

        .filter-dropdown {
            position: fixed; background: var(--panel); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: 0 8px 28px rgba(0,0,0,0.12);
            z-index: 1050; min-width: 250px; max-width: 400px; display: none;
        }
        .filter-dropdown.show { display: block; }
        .filter-dropdown-header {
            padding: 12px 16px; border-bottom: 1px solid var(--border);
            background: var(--surface); font-weight: 600; font-size: 12px;
            display: flex; justify-content: space-between; align-items: center;
            border-radius: var(--radius) var(--radius) 0 0;
        }
        .filter-dropdown-body { padding: 12px 16px; max-height: 300px; overflow-y: auto; }
        .filter-search {
            width: 100%; padding: 6px 12px; margin-bottom: 10px;
            border: 1px solid var(--border); border-radius: 7px; font-size: 12px;
            background: var(--surface); color: var(--ink); font-family: 'DM Sans',sans-serif; outline: none;
        }
        .filter-search:focus { border-color: var(--gold); }
        .filter-option { display: flex; align-items: center; padding: 5px 4px; cursor: pointer; user-select: none; border-radius: 5px; }
        .filter-option:hover { background: var(--surface); }
        .filter-option input[type="checkbox"] { margin-right: 8px; cursor: pointer; }
        .filter-option label { cursor: pointer; margin: 0; flex: 1; font-size: 12px; color: var(--ink); }
        .filter-dropdown-footer {
            padding: 10px 16px; border-top: 1px solid var(--border);
            background: var(--surface); display: flex; gap: 8px; justify-content: space-between;
            border-radius: 0 0 var(--radius) var(--radius);
        }
        .filter-btn-sm { padding: 5px 14px; font-size: 11px; font-weight: 600; border-radius: 6px; cursor: pointer; font-family: 'DM Sans',sans-serif; border: 1px solid var(--border); transition: all 0.2s; }
        .filter-btn-sm-secondary { background: var(--surface); color: var(--muted); }
        .filter-btn-sm-secondary:hover { border-color: var(--gold); color: var(--gold); }
        .filter-btn-sm-primary   { background: var(--ink); color: #fff; border-color: var(--ink); }
        .filter-btn-sm-primary:hover { background: #1e293b; }

        /* ── OPTION COLOR BADGES (selects) ── */
        .option1 { background-color: #059669; color: #fff; }
        .option2 { background-color: #dc2626; color: #fff; }
        .option3 { background-color: #D4EDBC; color: #11734B; }
        .option4 { background-color: #ffe5a0; color: #8c7850; }
        .option5 { background-color: #ffcfc9; color: #dc2626; }
        .option6 { background-color: #e4cff1; color: #795d8d; }
        .option7 { background-color: #b9e3fa; color: #417295; }
        .option8 { background-color: #e9eaee; color: #555; }

        /* ── ANIMATIONS ── */
        @keyframes rowIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        .efege-postq table.dt-table tbody tr { animation: rowIn 0.18s ease forwards; }

        /* ── RESPONSIVE ── */
        @media (max-width: 1100px) { .efege-postq .kpi-strip { grid-template-columns: repeat(3,1fr); } }
        @media (max-width: 700px) { .efege-postq .kpi-strip { grid-template-columns: repeat(2,1fr); } .efege-postq { padding: 0 12px 40px; } }
    </style>
    <div class="filter-bar">
        <span class="filter-label">Filtros</span>
        <form method="get" id="filterForm" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;"><input type="hidden" name="tab" value="clientes">
            <input type="date" name="start_date" class="filter-date"
                value="<?php echo htmlspecialchars($startDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="date" name="end_date" class="filter-date"
                value="<?php echo htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
           
            <button type="submit" class="filter-btn filter-btn-primary">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <a href="my_lead_board_leads.php?tab=clientes&show_all=1" class="filter-btn filter-btn-reset">
                <i class="fas fa-times"></i> Limpiar
            </a>
        </form>
    </div>
    <div class="table-wrap">
        <div class="table-header-custom">
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="table-title">Clientes Cerrados</span>
                <span class="table-count" id="table-count-label"><?php echo number_format($leadsCountFiltered); ?> clientes · <?php echo number_format($clientsPaqueteConfirmado); ?> con paquete</span>
                <button id="exportExcelBtn" class="efege-btn efege-btn-success" type="button">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="position:relative;">
                    <i style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:11px;" class="fas fa-search"></i>
                    <input type="text" id="tableSearchInput" class="efege-filter-search" placeholder="Buscar..." style="padding-left:26px;">
                </div>
            </div>
        </div>
        <div class="efege-table-scroll">
            <table id="leadsTable" class="efege-table table table-hover">
                <thead>
                    <tr>
                        <th data-column="nombre">Nombre</th>
                        <th data-column="metodo_contacto"><div class="th-flex-label"><span class="th-compact-label">Método contacto</span><i class="filter-icon fas fa-filter" data-column="metodo_contacto"></i></div></th>
                        <th data-column="tipo_cliente"><div class="th-flex-label"><span class="th-compact-label">Tipo de cliente</span><i class="filter-icon fas fa-filter" data-column="tipo_cliente"></i></div></th>
                        <th data-column="origen_cliente"><div class="th-flex-label"><span class="th-compact-label">Origen</span><i class="filter-icon fas fa-filter" data-column="origen_cliente"></i></div></th>
                        <th data-column="campana"><div class="th-flex-label"><span class="th-compact-label">Campaña</span><i class="filter-icon fas fa-filter" data-column="campana"></i></div></th>
                        <th data-column="estatus">Estatus <i class="filter-icon fas fa-filter" data-column="estatus"></i></th>
                        <th data-column="vendedor"><div class="th-flex-label"><span class="th-compact-label">Vendedor/a</span><i class="filter-icon fas fa-filter" data-column="vendedor"></i></div></th>
                        <th data-column="fecha">¿Cuándo cerró?</th>
                        <th style="display:none">Sesión Oficial</th>
                        <th style="display:none">Compromiso</th>
                        <th style="display:none">Técnica Cierre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($displayLeads as $lead):
                        $apptForVendor = null;
                        $cfIdForVendor = intval($lead['id'] ?? 0);
                        if ($cfIdForVendor > 0 && isset($appointmentsByClient[$cfIdForVendor])) {
                            $apptForVendor = $appointmentsByClient[$cfIdForVendor];
                        }
                        if (!$apptForVendor && wpEventosCfIsWpEventLead($lead)) {
                            $apptForVendor = wpEventosCfResolveAppointment($lead, $appointmentsByClient, $appointmentsByEventId);
                        }
                        $vendedorLabel = dashComercialResolveLeadVendorLabel($conn, $lead, $apptForVendor, $dashVendorMap, $appointmentsByEventId);
                        /* Estatus pill */
                        $statusDisplay = 'Cliente';
                        $statusClass = 'status-cliente';
                        $statusLower = 'cliente';
                        /* Origin badge */
                        $howRaw2 = trim((string)($lead['how_did_you_meet'] ?? ''));
                        if      ($howRaw2 === '1') { $origClass = 'origin-planner';   $origLabel = 'Wedding Planner'; }
                        elseif  ($howRaw2 === '2') { $origClass = 'origin-community'; $origLabel = 'Community';       }
                        elseif  ($howRaw2 === '3') { $origClass = 'origin-newmarket'; $origLabel = 'New Audience';      }
                        else { $origClass = 'origin-default'; $origLabel = getDondeNosConocioLabel($lead); }
                        /* Engagement */
                        $engRaw   = trim((string)($lead['engagement'] ?? ($lead['sfm_engagement'] ?? '')));
                        $engNorm  = mb_strtolower($engRaw, 'UTF-8');
                        $engClass = 'eng-low';
                        if      ($engNorm === '3' || $engNorm === 'alto')  $engClass = 'eng-high';
                        elseif  ($engNorm === '2' || $engNorm === 'medio') $engClass = 'eng-mid';
                        $engLabel = getEngagementLabelWithEmoji($lead);
                        /* Fecha llegada (lead) */
                        $fechaLlegadaRaw = $lead['created_time'] ?? $lead['submission_date'] ?? '';
                        $fechaLlegadaTs  = (!empty($fechaLlegadaRaw) && strtotime($fechaLlegadaRaw) !== false) ? intval(strtotime($fechaLlegadaRaw)) : 0;
                        $fechaLlegadaOut = $fechaLlegadaTs > 0 ? htmlspecialchars(formatArrivalDateTime($fechaLlegadaRaw), ENT_QUOTES, 'UTF-8') : '—';

                        /* Fecha cierre (cuándo pasó a cliente) */
                        $fechaCierreRaw = clientesResolveFechaCierreCliente($lead);
                        $fechaCierreTs  = (!empty($fechaCierreRaw) && strtotime($fechaCierreRaw) !== false) ? intval(strtotime($fechaCierreRaw)) : 0;
                        $fechaCierreOut = htmlspecialchars(clientesFormatFechaCierreDisplay($fechaCierreRaw), ENT_QUOTES, 'UTF-8');
                        $fechaCierreDateInput = '';
                        $fechaCierreTimeInput = '';
                        if ($fechaCierreTs > 0) {
                            $fechaCierreDateInput = date('Y-m-d', $fechaCierreTs);
                            $fechaCierreTimeInput = date('H:i', $fechaCierreTs);
                        }
                        $isWpEventLead = function_exists('wpEventosCfIsWpEventLead') && wpEventosCfIsWpEventLead($lead);
                        $cierreEditKind = $isWpEventLead ? 'eventos_wp' : 'lead';
                        $cierreContactFormId = (int) ($lead['id'] ?? 0);
                        $cierreEventId = 0;
                        if ($isWpEventLead && function_exists('wpEventosCfResolveEventIdFromLead')) {
                            $cierreEventId = (int) wpEventosCfResolveEventIdFromLead($lead);
                        } elseif ($isWpEventLead) {
                            $cierreEventId = (int) ($lead['original_lead_id'] ?? 0);
                        }
                        $leadNameForCierreModal = wpEventosCfResolveLeadNameDisplay($lead);
                        $leadNamePrimaryCierre = is_array($leadNameForCierreModal) ? ($leadNameForCierreModal['primary'] ?? '') : '';

                        /* Monto de la venta */
                        $montoRaw = trim((string)($lead['monto_venta'] ?? ''));
                        $montoOrder = 0;
                        $montoOut = '—';
                        if ($montoRaw !== '') {
                            $montoNumeric = floatval(preg_replace('/[^0-9\.\-]/', '', $montoRaw));
                            if ($montoRaw === '0' || $montoRaw === '0.0' || $montoNumeric !== 0.0) {
                                $montoOrder = $montoNumeric;
                            }
                            $montoOut = '$' . number_format($montoNumeric, 2, '.', ',');
                        }

                        /* Puntos */
                        $puntosRaw = trim((string)($lead['puntos'] ?? ''));
                        $puntosOrder = 0;
                        $puntosOut = '—';
                        if ($puntosRaw !== '') {
                            $puntosNumeric = floatval(preg_replace('/[^0-9\.\-]/', '', $puntosRaw));
                            if ($puntosRaw === '0' || $puntosRaw === '0.0' || $puntosNumeric !== 0.0) {
                                $puntosOrder = $puntosNumeric;
                            }
                            $puntosOut = htmlspecialchars($puntosRaw, ENT_QUOTES, 'UTF-8');
                        }

                        /* Qué se les vendió */
                        $vendidoRaw = trim((string)($lead['que_se_les_vendio'] ?? ''));
                        $vendidoOut = $vendidoRaw !== '' ? htmlspecialchars($vendidoRaw, ENT_QUOTES, 'UTF-8') : '—';

                        /* Paquete */
                        $paqRaw = trim($lead['paquete'] ?? $lead['paquete_cotizado'] ?? '');
                        if ($paqRaw !== '' && ctype_digit($paqRaw) && isset($paquetesMap[intval($paqRaw)])) {
                            $paqOut = htmlspecialchars($paquetesMap[intval($paqRaw)]);
                        } else {
                            $paqOut = ($paqRaw !== '') ? htmlspecialchars($paqRaw) : '—';
                        }
                    ?>
                    <?php
                        $contactMethodLabel = normalizeFirstContactChannelLabel($lead['first_contact_channel'] ?? '');
                        $contactChannelBadgeLabel = getContactChannelBadgeLabel($contactMethodLabel);
                        $campaignDisplayLabel = formatCampaignDisplayLabel($lead['campaign_name'] ?? '');
                        $knownUsRaw = trim((string)($lead['how_long_known_us'] ?? ''));
                        $knownUsDisplay = ($knownUsRaw === '' || $knownUsRaw === '—')
                            ? 'Sin dato'
                            : ($howLongKnownUsLabelMap[mb_strtolower($knownUsRaw, 'UTF-8')] ?? $knownUsRaw);
                        $knownUsBadgeLabel = getKnownUsBadgeLabel($knownUsDisplay);
                        $tipoClienteLabel = getTipoClienteLabel($lead, [
                            'tipo_cliente' => $lead['tipo_cliente'] ?? null,
                            'how_did_you_meet' => $lead['how_did_you_meet_raw'] ?? ($lead['how_did_you_meet'] ?? ''),
                        ]);
                        $originBadgeLabel = getOrigenCategoriaLabel($lead);
                        $originBadgeClass = 'badge badge-neutral';
                        if ($originBadgeLabel === 'Wedding Planner') {
                            $originBadgeClass = 'badge badge-planner';
                        } elseif ($originBadgeLabel === 'Community') {
                            $originBadgeClass = 'badge badge-community';
                        } elseif ($originBadgeLabel === 'New Audience') {
                            $originBadgeClass = 'badge badge-newmarket';
                        } elseif ($originBadgeLabel === 'N/A') {
                            $originBadgeLabel = 'Por confirmar';
                        }
                        $originCategoryRaw = trim((string)($lead['how_did_you_meet_raw'] ?? $lead['how_did_you_meet'] ?? ''));
                        $isOriginKnown = in_array($originCategoryRaw, ['1', '2', '3'], true) ? 1 : 0;
                        $isWpAfianzado = (
                            strcasecmp((string)($lead['tabla_origen'] ?? ''), 'eventos_wp') === 0 &&
                            intval($clientsEventWpAfianzadosMap[intval($lead['original_lead_id'] ?? 0)] ?? 0) === 1
                        ) ? 1 : 0;
                        $isPackageConfirmed = (
                            ($paqRaw !== '' && $paqRaw !== '—' && $paqRaw !== '0' && mb_strtolower($paqRaw, 'UTF-8') !== 'sin datos') ||
                            (trim((string)($lead['paquete_cotizado'] ?? '')) !== '' && trim((string)($lead['paquete_cotizado'] ?? '')) !== '—')
                        ) ? 1 : 0;
                    ?>
                    <tr id="lead-row-<?php echo $lead['id']; ?>-<?php echo htmlspecialchars($lead['tabla_origen']); ?>"
                        data-origin-known="<?php echo $isOriginKnown; ?>"
                        data-wp-afianzado="<?php echo $isWpAfianzado; ?>"
                        data-package-confirmed="<?php echo $isPackageConfirmed; ?>">

                        <!-- Nombre -->
                        <td data-column="nombre">
                            <?php $nameDisplay = wpEventosCfResolveLeadNameDisplay($lead); ?>
                            <div class="td-name"><?php echo htmlspecialchars($nameDisplay['primary'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if (!empty($nameDisplay['secondary'])): ?>
                                <div class="td-sub"><?php echo htmlspecialchars($nameDisplay['secondary'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($lead['email'])): ?>
                                <div class="td-sub"><?php echo htmlspecialchars($lead['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- Método de contacto -->
                        <td data-column="metodo_contacto"><?php echo renderContactMethodBadge($contactMethodLabel, $contactChannelBadgeLabel); ?></td>

                        <!-- Tipo de cliente -->
                        <td data-column="tipo_cliente"><?php echo renderTipoClienteBadge($tipoClienteLabel); ?></td>

                        <!-- Origen -->
                        <td data-column="origen_cliente"><span class="<?php echo htmlspecialchars($originBadgeClass, ENT_QUOTES, 'UTF-8'); ?>"><span class="badge-dot"></span><?php echo htmlspecialchars($originBadgeLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>

                        <!-- Campaña -->
                        <td data-column="campana"><?php echo renderCampaignBadge($campaignDisplayLabel); ?></td>

                        <!-- Estatus -->
                        <td class="text-center align-middle" data-column="estatus">
                            <span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                            <div class="td-sub td-sub-fecha-cliente"><?php echo $fechaCierreOut; ?></div>
                        </td>

                        <!-- Vendedor/a -->
                        <td data-column="vendedor"><?php echo htmlspecialchars($vendedorLabel, ENT_QUOTES, 'UTF-8'); ?></td>

                        <!-- ¿Cuándo cerró? -->
                        <td data-column="fecha"
                            data-order="<?php echo intval($fechaCierreTs); ?>"
                            class="mlb-cierre-date-cell js-edit-cierre-date<?php echo $fechaCierreOut === '—' ? ' is-empty' : ''; ?>"
                            title="Clic para editar cuándo cerró"
                            data-lead-kind="<?php echo htmlspecialchars($cierreEditKind, ENT_QUOTES, 'UTF-8'); ?>"
                            data-contact-form-id="<?php echo $cierreContactFormId; ?>"
                            data-event-id="<?php echo $cierreEventId; ?>"
                            data-fecha="<?php echo htmlspecialchars($fechaCierreDateInput, ENT_QUOTES, 'UTF-8'); ?>"
                            data-hora="<?php echo htmlspecialchars($fechaCierreTimeInput, ENT_QUOTES, 'UTF-8'); ?>"
                            data-lead-name="<?php echo htmlspecialchars($leadNamePrimaryCierre, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo $fechaCierreOut; ?>
                            <span class="mlb-cierre-date-hint">Editar</span>
                        </td>

                        <!-- Sesión oficial (hidden) -->
                        <td style="display:none">
                            <select class="form-select-sm form-select lead-update"
                                data-id="<?php echo intval($lead['id']); ?>" data-field="sesion_oficial"
                                data-table="<?php echo htmlspecialchars($lead['tabla_origen']); ?>">
                                <option value="">--Seleccionar--</option>
                                <option value="Si" class="option1" <?php echo (isset($lead['sesion_oficial']) && $lead['sesion_oficial'] === 'Si') ? 'selected' : ''; ?>>Sí</option>
                                <option value="aun no (no llegaron)" class="option2" <?php echo (isset($lead['sesion_oficial']) && $lead['sesion_oficial'] === 'aun no (no llegaron)') ? 'selected' : ''; ?>>Aún no (no llegaron)</option>
                                <option value="aun no pero ya agendaron" class="option3" <?php echo (isset($lead['sesion_oficial']) && $lead['sesion_oficial'] === 'aun no pero ya agendaron') ? 'selected' : ''; ?>>Aún no pero ya agendaron</option>
                                <option value="no, es relacion directa con planner" class="option4" <?php echo (isset($lead['sesion_oficial']) && $lead['sesion_oficial'] === 'no, es relacion directa con planner') ? 'selected' : ''; ?>>No, relación directa con planner</option>
                            </select>
                        </td>

                        <!-- Compromiso cliente (hidden) -->
                        <td style="display:none">
                            <select class="form-select-sm form-select lead-update"
                                data-id="<?php echo intval($lead['id']); ?>" data-field="compromiso_cliente"
                                data-table="<?php echo htmlspecialchars($lead['tabla_origen']); ?>">
                                <option value="">--Seleccionar--</option>
                                <option value="alto"     class="option3" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'alto')     ? 'selected' : ''; ?>>Alto</option>
                                <option value="normal"   class="option4" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'normal')   ? 'selected' : ''; ?>>Normal</option>
                                <option value="bajo"     class="option5" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'bajo')     ? 'selected' : ''; ?>>Bajo</option>
                                <option value="muerto"   class="option2" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'muerto')   ? 'selected' : ''; ?>>Muerto</option>
                                <option value="cerrado"  class="option1" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'cerrado')  ? 'selected' : ''; ?>>Cerrado</option>
                                <option value="stand by" class="option7" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'stand by') ? 'selected' : ''; ?>>Stand by</option>
                            </select>
                        </td>

                        <!-- Técnica cierre (hidden) -->
                        <td style="display:none">
                            <select class="form-select-sm form-select lead-update"
                                data-id="<?php echo intval($lead['id']); ?>" data-field="tecnica_cierre"
                                data-table="<?php echo htmlspecialchars($lead['tabla_origen']); ?>">
                                <option value="">--Seleccionar--</option>
                                <option value="Aun no"            class="option2" <?php echo (isset($lead['tecnica_cierre']) && $lead['tecnica_cierre'] === 'Aun no')            ? 'selected' : ''; ?>>Aún no</option>
                                <option value="Sí"                class="option1" <?php echo (isset($lead['tecnica_cierre']) && $lead['tecnica_cierre'] === 'Sí')                ? 'selected' : ''; ?>>Sí</option>
                                <option value="aun no es momento" class="option8" <?php echo (isset($lead['tecnica_cierre']) && $lead['tecnica_cierre'] === 'aun no es momento') ? 'selected' : ''; ?>>Aún no es momento</option>
                            </select>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div><!-- /.table-wrap -->
</div>

<!-- ── Modal: editar fecha de cierre ── -->
<div class="modal fade" id="editCierreDateModal" tabindex="-1" aria-labelledby="editCierreDateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCierreDateModalLabel">Editar fecha de cierre</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" style="font-size:13px;color:var(--muted);">
                    <strong id="editCierreDateLeadName" style="color:var(--ink);"></strong>
                </p>
                <p class="mb-3" id="editCierreDateContext" style="font-size:12px;color:var(--muted);"></p>
                <form id="editCierreDateForm">
                    <input type="hidden" id="editCierreDateKind" value="lead">
                    <input type="hidden" id="editCierreDateContactFormId" value="0">
                    <input type="hidden" id="editCierreDateEventId" value="0">
                    <div class="mb-3">
                        <label for="editCierreDateFecha" class="form-label">Fecha en que cerró</label>
                        <input type="date" class="form-control" id="editCierreDateFecha" required>
                    </div>
                    <div class="mb-0">
                        <label for="editCierreDateHora" class="form-label">Hora</label>
                        <input type="time" class="form-control" id="editCierreDateHora">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSaveCierreDate">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
    const vendedores = <?php echo json_encode($vendedores); ?>;

    (function () {
        var editCierreDateModalEl = document.getElementById('editCierreDateModal');
        var editCierreDateModal = editCierreDateModalEl ? new bootstrap.Modal(editCierreDateModalEl) : null;
        var activeCierreDateCell = null;

        $(document).on('click', '.js-edit-cierre-date, .mlb-cierre-date-hint', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $cell = $(this).closest('td.js-edit-cierre-date');
            if (!$cell.length) {
                $cell = $(this).filter('.js-edit-cierre-date');
            }
            if (!$cell.length) {
                return;
            }
            activeCierreDateCell = $cell[0];
            var kind = String($cell.attr('data-lead-kind') || 'lead');
            $('#editCierreDateKind').val(kind);
            $('#editCierreDateContactFormId').val($cell.attr('data-contact-form-id') || '0');
            $('#editCierreDateEventId').val($cell.attr('data-event-id') || '0');
            $('#editCierreDateFecha').val($cell.attr('data-fecha') || '');
            $('#editCierreDateHora').val($cell.attr('data-hora') || '');
            $('#editCierreDateLeadName').text($cell.attr('data-lead-name') || 'Cliente');
            if (kind === 'eventos_wp') {
                $('#editCierreDateContext').text('Evento de Wedding Planner: actualiza eventos_wp.fecha_cliente y contact_form.fecha_cambio_cliente. No es el mismo campo que un lead comercial.');
            } else {
                $('#editCierreDateContext').text('Lead comercial: actualiza contact_form.fecha_cambio_cliente. No modifica eventos_wp.');
            }
            if (editCierreDateModal) {
                editCierreDateModal.show();
            }
        });

        $('#btnSaveCierreDate').on('click', function () {
            var fecha = $('#editCierreDateFecha').val();
            if (!fecha) {
                Swal.fire('Error', 'Indica la fecha.', 'error');
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.ajax({
                url: 'actualizar_fecha_cierre_cliente_lead.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    kind: $('#editCierreDateKind').val(),
                    contact_form_id: $('#editCierreDateContactFormId').val(),
                    event_id: $('#editCierreDateEventId').val(),
                    fecha: fecha,
                    hora: $('#editCierreDateHora').val()
                },
                success: function (resp) {
                    if (resp && resp.success) {
                        if (activeCierreDateCell && resp.display) {
                            var $cell = $(activeCierreDateCell);
                            var $row = $cell.closest('tr');
                            $cell.empty();
                            $cell.append(document.createTextNode(resp.display));
                            $cell.append($('<span class="mlb-cierre-date-hint">Editar</span>'));
                            $cell.removeClass('is-empty');
                            if (resp.data_order) {
                                $cell.attr('data-order', resp.data_order);
                            }
                            $cell.attr('data-fecha', fecha);
                            $cell.attr('data-hora', $('#editCierreDateHora').val() || '');
                            $row.find('.td-sub-fecha-cliente').text(resp.display);
                            if ($.fn.dataTable.isDataTable('#leadsTable')) {
                                $('#leadsTable').DataTable().row($row).invalidate();
                            }
                        }
                        if (editCierreDateModal) {
                            editCierreDateModal.hide();
                        }
                        Swal.fire('Guardado', resp.message || 'Fecha actualizada.', 'success');
                    } else {
                        Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo guardar.', 'error');
                    }
                },
                error: function (xhr) {
                    var msg = 'Error de comunicación.';
                    try {
                        var parsed = JSON.parse(xhr.responseText);
                        if (parsed.message) {
                            msg = parsed.message;
                        }
                    } catch (ignore) {}
                    Swal.fire('Error', msg, 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        });
    })();

    $(document).ready(function () {
        var table = $('#leadsTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            ordering:  false,
            paging:    false,
            info: true,
            dom: '<""r>t<"d-flex justify-content-between align-items-center mt-2"il>',
            buttons: [{ extend: 'excelHtml5', title: 'Cierres EFEGE', exportOptions: { columns: ':visible:not(:last-child)' } }],
            drawCallback: function () { applyColumnFilters(); }
        });

        $('#exportExcelBtn').on('click', function () {
            table.button(0).trigger();
        });

        $('#tableSearchInput').on('input', function () {
            table.search(this.value).draw();
        });

        updateDynamicCards();

        // ── REPORTE DE CIERRES (solo si el contenedor existe en esta vista) ──
        var clientsChartEl = document.getElementById('clientsChart');
        if (clientsChartEl && typeof Highcharts !== 'undefined') {
        var chartDates  = <?php echo $datesJson; ?>;
        var chartCounts = <?php echo $countsJson; ?>;

        var maxCierres = 0;
        chartCounts.forEach(function(c) { if (c > maxCierres) maxCierres = c; });
        var yAxisMax      = Math.ceil(maxCierres * 1.15);
        if (yAxisMax < 5) yAxisMax = 5;
        var tickInterval  = Math.ceil(yAxisMax / 5);
        if (tickInterval < 1) tickInterval = 1;

        Highcharts.chart('clientsChart', {
            chart: { type: 'line', backgroundColor: '#f8f9fa', borderRadius: 12,
                     spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10 },
            title: { text: null },
            xAxis: {
                categories: chartDates, crosshair: true,
                labels: { rotation: -45, style: { fontSize: '11px' } },
                lineColor: '#d9e2ec'
            },
            yAxis: {
                min: 0, max: yAxisMax, tickInterval: tickInterval,
                title: { text: 'Cierres' }, allowDecimals: false, gridLineColor: '#e2e8f0'
            },
            legend: { enabled: false },
            plotOptions: {
                line: {
                    color: '#C5A028',
                    marker: { enabled: true, radius: 4 },
                    dataLabels: {
                        enabled: true,
                        formatter: function() { return this.y > 0 ? this.y : ''; },
                        style: { fontSize: '10px', fontWeight: '600', textOutline: 'none' }
                    }
                }
            },
            series: [{ name: 'Cierres', data: chartCounts }],
            tooltip: {
                backgroundColor: 'rgba(15,23,42,0.92)', style: { color: '#fff' }, borderWidth: 0,
                formatter: function() { return '<b>' + this.x + '</b><br/>Cierres: <b>' + this.y + '</b>'; }
            },
            credits: { enabled: false }
        });
        }

        // ── TOOLTIP EN CELDAS TRUNCADAS ───────────────────────────────────────────
        var $tip = $('<div class="td-tooltip"></div>').appendTo('body');
        $(document).on('mouseenter', '#leadsTable td', function(e) {
            var el = this;
            if (el.scrollWidth <= el.clientWidth) return; // no truncado
            $tip.text($(el).text().trim()).addClass('visible');
        }).on('mousemove', '#leadsTable td', function(e) {
            if (!$tip.hasClass('visible')) return;
            var x = e.clientX + 14, y = e.clientY + 14;
            if (x + 400 > window.innerWidth)  x = e.clientX - 400;
            if (y + 80  > window.innerHeight) y = e.clientY - 50;
            $tip.css({ left: x, top: y });
        }).on('mouseleave', '#leadsTable td', function() {
            $tip.removeClass('visible');
        });
        // ── FIN TOOLTIP ──────────────────────────────────────────────────────────
    });

    // ── Utility: escape HTML ──────────────────────────────
    function escapeHtml(unsafe) {
        if (!unsafe && unsafe !== 0) return '';
        return String(unsafe)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function applyOptionClass($el) {
        if (!$el || !$el.length) return;
        $el.removeClass('option1 option2 option3 option4 option5 option6 option7 option8');
        var cls = $el.find('option:selected').attr('class');
        if (cls) $el.addClass(cls);
    }

    // ── Ver comentarios ───────────────────────────────────
    function verComentarios(id) {
        Swal.fire({ title: 'Cargando comentarios...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        $.ajax({
            url: 'actualizar_lead.php', method: 'POST', dataType: 'json',
            data: { action: 'get_comments', id: id },
            success: function (resp) {
                Swal.close();
                if (resp && resp.success) {
                    const comments = resp.comments || [];
                    let html = '<div id="commentsList">';
                    if (!comments.length) {
                        html += '<p>No hay comentarios aún.</p>';
                    } else {
                        html += '<div class="list-group mb-3">';
                        comments.forEach(function (c) {
                            html += '<div class="list-group-item">';
                            html += '<div class="d-flex justify-content-between w-100"><h6 class="mb-1">' + escapeHtml(c.author || 'Vendedor') + '</h6><small>' + escapeHtml(c.created_at || '') + '</small></div>';
                            html += '<p class="mb-1">' + escapeHtml(c.text || '') + '</p></div>';
                        });
                        html += '</div>';
                    }
                    html += '</div>';
                    html += '<div class="mb-3"><label class="form-label">Agregar comentario</label><textarea id="newCommentText" class="form-control" rows="4" placeholder="Escribe un comentario..."></textarea></div>';
                    html += '<div class="text-end"><button id="saveCommentBtn" class="btn btn-primary">Guardar comentario</button></div>';
                    document.getElementById('comentariosModalBody').innerHTML = html;
                    var modal = new bootstrap.Modal(document.getElementById('comentariosModal'));
                    modal.show();
                    $('#saveCommentBtn').on('click', function () {
                        var comment = $('#newCommentText').val();
                        if (!comment || !comment.trim()) { Swal.fire('Error', 'El comentario no puede estar vacío', 'error'); return; }
                        var $btn = $(this); $btn.prop('disabled', true);
                        Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        $.ajax({
                            url: 'actualizar_lead.php', method: 'POST', dataType: 'json',
                            data: { action: 'add_comment', id: id, comment: comment, author: '' },
                            success: function (resp2) {
                                Swal.close();
                                if (resp2 && resp2.success) {
                                    Swal.fire('Guardado', 'Comentario agregado correctamente', 'success');
                                    const cn = resp2.comments || [];
                                    let lh = cn.length ? '<div class="list-group mb-3">' : '<p>No hay comentarios aún.</p>';
                                    cn.forEach(function (c) { lh += '<div class="list-group-item"><div class="d-flex justify-content-between w-100"><h6 class="mb-1">' + escapeHtml(c.author || 'Vendedor') + '</h6><small>' + escapeHtml(c.created_at || '') + '</small></div><p class="mb-1">' + escapeHtml(c.text || '') + '</p></div>'; });
                                    if (cn.length) lh += '</div>';
                                    $('#commentsList').html(lh);
                                    $('#newCommentText').val('');
                                } else { Swal.fire('Error', resp2.message || 'No se pudo guardar el comentario', 'error'); }
                            },
                            error: function () { Swal.close(); Swal.fire('Error', 'Error de comunicación con el servidor', 'error'); },
                            complete: function () { $btn.prop('disabled', false); }
                        });
                    });
                } else { Swal.fire('Error', resp.message || 'No se pudieron obtener los comentarios', 'error'); }
            },
            error: function () { Swal.fire('Error', 'Error de comunicación con el servidor', 'error'); }
        });
    }

    // ── Inline update (selects) ───────────────────────────
    $(document).ready(function () {
        $('.lead-update').each(function () { applyOptionClass($(this)); });

        $(document).on('change', '.lead-update', function () {
            var el = $(this), id = el.data('id'), field = el.data('field'), value = el.val();
            el.prop('disabled', true);
            $.ajax({
                url: 'actualizar_lead.php', method: 'POST', dataType: 'json',
                data: { id: id, field: field, value: value },
                success: function (resp) {
                    if (resp && resp.success) {
                        el.closest('td').css('background', '#e6ffed');
                        setTimeout(function () { el.closest('td').css('background', ''); }, 800);
                        applyOptionClass(el);
                    } else { Swal.fire('Error', resp.message || 'No se pudo actualizar', 'error'); }
                },
                error: function () { Swal.fire('Error', 'Error de comunicación con el servidor', 'error'); },
                complete: function () { el.prop('disabled', false); }
            });
        });
    });

    // ── Dynamic KPI update ────────────────────────────────
    function updateDynamicCards() {
        var totalVisible = 0;
        var originKnownVisible = 0;
        var wpAfianzadosVisible = 0;
        var packageVisible = 0;

        $('#leadsTable tbody tr:visible').each(function () {
            totalVisible++;
            originKnownVisible += Number($(this).data('origin-known') || 0);
            wpAfianzadosVisible += Number($(this).data('wp-afianzado') || 0);
            packageVisible += Number($(this).data('package-confirmed') || 0);
        });

        var totalPostLeads = <?php echo intval($leadsPostCountFiltered); ?>;
        var convRate = totalPostLeads > 0 ? ((totalVisible / totalPostLeads) * 100).toFixed(1) : '0.0';
        var originPendingVisible = Math.max(totalVisible - originKnownVisible, 0);
        var wpAfianzadosRate = totalVisible > 0 ? ((wpAfianzadosVisible / totalVisible) * 100).toFixed(1) : '0.0';

        $('#kpi-clients-total').text(totalVisible);
        $('#kpi-origin-known').text(originKnownVisible);
        $('#kpi-wp-afianzados').text(wpAfianzadosVisible);
        $('#kpi-wp-afianzados-detail').text(wpAfianzadosRate + '% del total corresponde a WP afianzados.');
        $('#kpi-package-confirmed').text(packageVisible);
        $('#kpi-package-confirmed-detail').text((totalVisible > 0 ? ((packageVisible / totalVisible) * 100).toFixed(1) : '0.0') + '% del total tiene paquete registrado.');
        $('#report-close-rate').text(convRate + '%');
        $('#report-close-rate-detail').text(totalVisible + ' cierres de ' + totalPostLeads + ' sesiones post-calificadas');
        $('#report-origin-pending-count').text(originPendingVisible + ' clientes');
        $('#table-count-label').text(totalVisible + ' clientes · ' + packageVisible + ' con paquete');
    }

    // ════════════════════════════════════════════════════
    // SISTEMA DE FILTROS POR COLUMNA
    // ════════════════════════════════════════════════════
    var columnFilters = {};
    var currentFilterDropdown = null;

    function getUniqueColumnValues(columnName) {
        var values = new Set();
        $('#leadsTable tbody tr').each(function () {
            var cell = $(this).find('td[data-column="' + columnName + '"]');
            if (cell.length > 0) {
                var text = cell.text().trim();
                if (columnName === 'estatus') {
                    text = cell.find('.status-pill').first().text().trim();
                }
                if (text !== '') values.add(text);
            }
        });
        return Array.from(values).sort();
    }

    function createFilterDropdown(columnName, iconElement) {
        closeFilterDropdown();
        var uniqueValues = getUniqueColumnValues(columnName);
        if (uniqueValues.length === 0) return;

        var dropdown = $('<div class="filter-dropdown show"></div>');
        var header   = $('<div class="filter-dropdown-header"></div>');
        header.append('<span>Filtrar por ' + columnName + '</span>');
        var closeBtn = $('<button type="button" class="btn-close btn-sm" style="font-size:0.7rem;"></button>');
        closeBtn.on('click', closeFilterDropdown);
        header.append(closeBtn);
        dropdown.append(header);

        var body            = $('<div class="filter-dropdown-body"></div>');
        var searchInput     = $('<input type="text" class="filter-search" placeholder="Buscar...">');
        body.append(searchInput);

        var optionsContainer  = $('<div class="filter-options"></div>');
        var selectAllDiv      = $('<div class="filter-option mb-2"></div>');
        var selectAllCheckbox = $('<input type="checkbox" id="selectAll_' + columnName + '" checked>');
        var selectAllLabel    = $('<label for="selectAll_' + columnName + '"><strong>Seleccionar todo</strong></label>');
        selectAllDiv.append(selectAllCheckbox).append(selectAllLabel);
        optionsContainer.append(selectAllDiv);
        optionsContainer.append('<hr style="margin:0.5rem 0;">');

        var selectedValues = columnFilters[columnName] || uniqueValues.slice();
        uniqueValues.forEach(function (value, index) {
            var optionDiv = $('<div class="filter-option" data-value="' + escapeHtml(value) + '"></div>');
            var checkbox  = $('<input type="checkbox" id="filter_' + columnName + '_' + index + '">');
            if (selectedValues.includes(value)) checkbox.prop('checked', true);
            var label = $('<label for="filter_' + columnName + '_' + index + '">' + escapeHtml(value) + '</label>');
            optionDiv.append(checkbox).append(label);
            optionsContainer.append(optionDiv);
        });
        body.append(optionsContainer);
        dropdown.append(body);

        var footer   = $('<div class="filter-dropdown-footer"></div>');
        var clearBtn = $('<button type="button" class="filter-btn-sm filter-btn-sm-secondary">Limpiar</button>');
        var applyBtn = $('<button type="button" class="filter-btn-sm filter-btn-sm-primary">Aplicar</button>');

        clearBtn.on('click', function () {
            optionsContainer.find('input[type="checkbox"]').prop('checked', true);
            delete columnFilters[columnName];
            applyColumnFilters();
            closeFilterDropdown();
        });

        applyBtn.on('click', function () {
            var selected = [];
            optionsContainer.find('.filter-option:not(:first)').each(function () {
                var checkbox = $(this).find('input[type="checkbox"]');
                if (checkbox.prop('checked')) selected.push($(this).data('value'));
            });
            if (selected.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Advertencia', text: 'Debe seleccionar al menos un valor', confirmButtonText: 'OK' });
                return;
            }
            columnFilters[columnName] = selected;
            applyColumnFilters();
            closeFilterDropdown();
        });

        footer.append(clearBtn).append(applyBtn);
        dropdown.append(footer);
        $('body').append(dropdown);

        var anchorRect     = iconElement.getBoundingClientRect();
        var dropdownWidth  = dropdown.outerWidth();
        var dropdownHeight = dropdown.outerHeight();
        var viewportWidth  = window.innerWidth;
        var viewportHeight = window.innerHeight;
        var gap = 5;
        var left = anchorRect.left;
        var top  = anchorRect.bottom + gap;
        if (left + dropdownWidth  > viewportWidth  - 10) left = Math.max(10, viewportWidth  - dropdownWidth  - 10);
        if (top  + dropdownHeight > viewportHeight - 10) top  = anchorRect.top - dropdownHeight - gap;
        dropdown.css({ position: 'fixed', left: left + 'px', top: top + 'px' });
        currentFilterDropdown = dropdown;
        $(iconElement).addClass('active');

        searchInput.on('input', function () {
            var searchTerm = $(this).val().toLowerCase();
            optionsContainer.find('.filter-option:not(:first)').each(function () {
                $(this).toggle($(this).find('label').text().toLowerCase().includes(searchTerm));
            });
        });

        selectAllCheckbox.on('change', function () {
            optionsContainer.find('.filter-option:not(:first) input[type="checkbox"]:visible').prop('checked', $(this).prop('checked'));
        });

        optionsContainer.on('change', '.filter-option:not(:first) input[type="checkbox"]', function () {
            var visibleBoxes = optionsContainer.find('.filter-option:not(:first):visible input[type="checkbox"]');
            var checkedBoxes = visibleBoxes.filter(':checked');
            selectAllCheckbox.prop('checked', visibleBoxes.length === checkedBoxes.length);
        });
    }

    function closeFilterDropdown() {
        if (currentFilterDropdown) {
            currentFilterDropdown.remove();
            currentFilterDropdown = null;
            $('.filter-icon.active').removeClass('active');
        }
    }

    function applyColumnFilters() {
        $('.filter-icon').removeClass('active');
        Object.keys(columnFilters).forEach(function (col) {
            $('.filter-icon[data-column="' + col + '"]').addClass('active');
        });
        $('#leadsTable tbody tr').each(function () {
            var row     = $(this);
            var showRow = true;
            for (var columnName in columnFilters) {
                var cell = row.find('td[data-column="' + columnName + '"]');
                if (cell.length > 0) {
                    var cellValue = cell.text().trim();
                    if (columnName === 'estatus') {
                        cellValue = cell.find('.status-pill').first().text().trim();
                    }
                    var allowedValues = columnFilters[columnName];
                    if (!allowedValues.includes(cellValue)) { showRow = false; break; }
                }
            }
            showRow ? row.show() : row.hide();
        });
        updateDynamicCards();
    }

    $(document).on('click', '.filter-icon', function (e) {
        e.stopPropagation();
        createFilterDropdown($(this).data('column'), this);
    });

    $(document).on('click', function (e) {
        if (currentFilterDropdown && !$(e.target).closest('.filter-dropdown').length && !$(e.target).hasClass('filter-icon')) {
            closeFilterDropdown();
        }
    });

    $(document).on('click', '.filter-dropdown', function (e) { e.stopPropagation(); });
    $(document).on('keydown', function (e) { if (e.key === 'Escape' && currentFilterDropdown) closeFilterDropdown(); });

</script>
<?php
// ── DIAGNÓSTICO: clientes con origen "Sin dato" ───────────────────────────────
$_sinDatoLeads = [];
foreach ($displayLeads as $_lead) {
    $_howRaw = trim((string)($_lead['how_did_you_meet'] ?? ''));
    if (!in_array($_howRaw, ['1', '2', '3'], true)) {
        $_motivo = $_howRaw === ''
            ? 'Campo how_did_you_meet está vacío en la BD'
            : 'Valor desconocido: "' . addslashes($_howRaw) . '" (se esperaba 1, 2 o 3)';
        $_sinDatoLeads[] = [
            'id'               => $_lead['id'] ?? '?',
            'nombre'           => $_lead['full_name'] ?? '',
            'how_did_you_meet' => $_howRaw === '' ? '(vacío)' : $_howRaw,
            'tabla_origen'     => $_lead['tabla_origen'] ?? '',
            'motivo'           => $_motivo,
        ];
    }
}
?>
<script>
(function () {
    var sinDatoLeads = <?php echo json_encode($_sinDatoLeads, JSON_UNESCAPED_UNICODE); ?>;
    if (sinDatoLeads.length === 0) {
        console.log('[clientes] Origen del cliente: todos los registros tienen origen definido ✅');
        return;
    }
    console.group('[clientes] ' + sinDatoLeads.length + ' registro(s) con origen "Sin dato":');
    sinDatoLeads.forEach(function (lead) {
        console.log(
            'ID: ' + lead.id +
            ' | Nombre: ' + (lead.nombre || '(sin nombre)') +
            ' | Tabla: ' + (lead.tabla_origen || '(sin tabla)') +
            ' → ' + lead.motivo
        );
    });
    console.groupEnd();
})();
</script>
</body>
</html>

</div>
<?php