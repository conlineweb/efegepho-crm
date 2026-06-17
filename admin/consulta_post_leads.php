<?php


// Mover session_start() aquí y verificar si ya está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tipoUsuario = $_SESSION['tipo_usuario'] ?? 0; // Ajusta según tu sistema de autenticación

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos
require_once __DIR__ . '/evento_wp_post_helper.php';
require_once __DIR__ . '/campaign_badge_helper.php';
require_once __DIR__ . '/calendario_estatus_historial_helper.php';

function postLeadResolveEngagementValue(array $lead, array $appointmentsByClient, array $wpEngagementByEventId)
{
    $tablaOrigen = strtolower(trim((string) ($lead['tabla_origen'] ?? '')));
    $origId = intval($lead['original_lead_id'] ?? 0);
    $cfId = intval($lead['id'] ?? 0);

    if ($tablaOrigen === 'eventos_wp' && $origId > 0 && isset($wpEngagementByEventId[$origId])) {
        return $wpEngagementByEventId[$origId];
    }

    $current = intval($lead['engagement'] ?? 0);
    if ($current >= 1 && $current <= 3) {
        return $current;
    }

    if ($cfId > 0 && isset($appointmentsByClient[$cfId])) {
        $fromCalendar = intval($appointmentsByClient[$cfId]['cliente_engagement'] ?? 0);
        if ($fromCalendar >= 1 && $fromCalendar <= 3) {
            return $fromCalendar;
        }
    }

    return $current;
}

function formatCreatedTime($dateString)
{
    if (empty($dateString))
        return '';
    $timestamp = strtotime($dateString);
    // Tratar timestamps inválidos o no positivos como ausencia de fecha para evitar años extraños como "-0001"
    if ($timestamp === false || $timestamp <= 0)
        return '';
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

function isB1B2CampaignName($campaignName)
{
    if ($campaignName === null) {
        return false;
    }
    $v = strtolower(trim((string) $campaignName));
    if ($v === '') {
        return false;
    }
    $v = preg_replace('/\s+/', ' ', $v);
    return in_array($v, ['b1', 'b1 (usa)', 'b1 usa', 'b2', 'b2 (mx)', 'b2 mx'], true);
}

/**
 * Resuelve el método de contacto inicial (misma lógica que consulta_leads).
 */
function resolveFirstContactChannelForLead(array $lead, ?array $originalLead = null)
{
    $cfFcc = trim((string)($lead['first_contact_channel'] ?? ''));
    $origFcc = $originalLead ? trim((string)($originalLead['first_contact_channel'] ?? '')) : '';
    $origLower = mb_strtolower($origFcc, 'UTF-8');

    if ($origFcc !== '' && $origLower !== 'whatsapp') {
        return $origFcc;
    }

    $campaign = trim((string)($lead['campaign_name'] ?? ($originalLead['campaign_name'] ?? '')));
    $platform = mb_strtolower(trim((string)($lead['platform'] ?? ($originalLead['platform'] ?? ''))), 'UTF-8');
    $tipoIg = mb_strtolower(trim((string)($lead['tipo_ig'] ?? ($originalLead['tipo_ig'] ?? ''))), 'UTF-8');
    $campaignLower = mb_strtolower($campaign, 'UTF-8');

    if (isB1B2CampaignName($campaign)) {
        return 'IG';
    }
    if (in_array($platform, ['ig', 'ig usa', 'ig mexico'], true)) {
        return 'IG';
    }
    if ($campaignLower === 'ig organico') {
        return 'IG';
    }
    if ($tipoIg === 'campana' || $tipoIg === 'organico') {
        return 'IG';
    }
    if ($origLower === 'ig' || strpos($origLower, 'instagram') !== false) {
        return $origFcc !== '' ? $origFcc : 'IG';
    }

    if ($origFcc !== '') {
        return $origFcc;
    }

    $cfLower = mb_strtolower($cfFcc, 'UTF-8');
    if ($cfLower === 'ig' || strpos($cfLower, 'instagram') !== false) {
        return $cfFcc;
    }

    return $cfFcc;
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

$wpEngagementByEventId = [];
$wpEngagementRes = $conn->query("SELECT idclie, cliente_engagement FROM calendario WHERE tipo = 1 AND eliminado = 0 AND cliente_engagement IS NOT NULL AND cliente_engagement > 0 ORDER BY id DESC");
if ($wpEngagementRes && $wpEngagementRes->num_rows > 0) {
    while ($wpEngRow = $wpEngagementRes->fetch_assoc()) {
        $eventId = intval($wpEngRow['idclie'] ?? 0);
        if ($eventId > 0 && !isset($wpEngagementByEventId[$eventId])) {
            $wpEngagementByEventId[$eventId] = intval($wpEngRow['cliente_engagement']);
        }
    }
}

// Consultar registros de contact_form (solo los que tienen cita en calendario)
$allLeads = [];
if (!empty($appointmentIds)) {
    // Construir lista segura de IDs
    $idsList = implode(',', array_map('intval', $appointmentIds));
    $sql = "SELECT * FROM contact_form WHERE id IN ($idsList) ORDER BY submission_date DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($cf = $result->fetch_assoc()) {
            // Excluir registros provienen de Wedding Planners (no queremos nada de WP aquí)
            $tablaOrigenRaw = strtolower(trim($cf['tabla_origen'] ?? ''));
            if ($tablaOrigenRaw === 'wedding_planners' || $tablaOrigenRaw === 'wedding_planner') {
                // Saltar este registro
                continue;
            }

            // Datos básicos del contact_form
            $merged = $cf;
            $merged['engagement'] = postLeadResolveEngagementValue($merged, $appointmentsByClient, $wpEngagementByEventId);

            $merged['tabla_origen'] = $cf['tabla_origen'] ?? '';
            $merged['original_lead_id'] = $cf['original_lead_id'] ?? null;
            $merged['full_name'] = $cf['names'] ?? 'N/A';
            $merged['submission_date'] = $cf['submission_date'] ?? '';
            // Ensure we have a created_time for filtering: prefer contact_form.created_time, fallback to submission_date
            $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';
            // Garantizar wedding_location - usar 'N/A' si no existe o está vacío (incluye cadenas vacías o sólo espacios)
            $merged['wedding_location'] = (isset($cf['wedding_location']) && trim($cf['wedding_location']) !== '') ? $cf['wedding_location'] : 'N/A';
            // Verificar si tiene cita
            $merged['has_appointment'] = in_array(intval($cf['id']), $appointmentIds) ? 1 : 0;

            // Obtener datos del lead original si es necesario
            $formName = $cf['tabla_origen'] ?? '';
            $origId = intval($cf['original_lead_id'] ?? 0);
            $leadRow = null;
            if (!empty($formName) && $origId > 0) {
                $escapedForm = $conn->real_escape_string($formName);
                $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
                    if ($leadRes && $leadRes->num_rows > 0) {
                        $leadRow = $leadRes->fetch_assoc();
                        // Only override the contact_form created_time fallback when the original lead has a meaningful value
                        // Actualizar nombre si está disponible
                        if (!empty($leadRow['full_name']))
                            $merged['full_name'] = $leadRow['full_name'];
                        elseif (!empty($leadRow['names']))
                            $merged['full_name'] = $leadRow['names'];
                        elseif (!empty($leadRow['name']))
                            $merged['full_name'] = $leadRow['name'];

                        // Actualizar fecha de creación si está disponible
                        if (!empty($leadRow['created_time'])) {
                            $merged['created_time'] = $leadRow['created_time'];
                        } elseif (!empty($leadRow['created_at'])) {
                            $merged['created_time'] = $leadRow['created_at'];
                        }

                        // Añadir todos los campos del lead original
                        // Si el `contact_form` tiene el campo pero está vacío, preferimos el valor
                        // del lead original para evitar perder datos como `platform`.
                        foreach ($leadRow as $k => $v) {
                            if (is_string($v)) $v = trim($v);
                            if (!isset($merged[$k]) || $merged[$k] === '' || $merged[$k] === null) {
                                $merged[$k] = $v;
                            }
                        }

                    }
                }
            }



            // Garantizar campaign_name: contact_form primero, fallback al lead original
            $cfCampaign = trim((string)($cf['campaign_name'] ?? ''));
            if ($cfCampaign === '') {
                $cfCampaign = trim((string)($merged['campaign_name'] ?? ''));
            }
            $merged['campaign_name'] = $cfCampaign;

            // Método de contacto inicial: alinear con consulta_leads (lead original + inferencia IG)
            $merged['first_contact_channel'] = resolveFirstContactChannelForLead($merged, $leadRow);

            // ===== MAPEO DEL ASESOR ASIGNADO (idusu) =====
            // Si la cita existe en calendario y en $appointmentsByClient guardamos el id del asesor
            // en los campos que usa el resto del código: id_vendedor_asignado y usuario_asignado.
            // Esto evita hacer un JOIN complejo y respeta la lógica previa que eligió la
            // cita más reciente para cada lead.
            $cid = intval($cf['id']);
            // Ocultar leads cuya fecha de cita es inválida (por ejemplo '0000-00-00') para evitar mostrar "-0001"
            if ($cid > 0 && isset($appointmentsByClient[$cid])) {
                $appt = $appointmentsByClient[$cid];
                $apptFechaRaw = trim($appt['fecha'] ?? '');
                $apptHoraRaw = trim($appt['hora'] ?? '');
                $apptTs = ($apptFechaRaw !== '') ? strtotime($apptFechaRaw . ' ' . $apptHoraRaw) : false;
                if ($apptFechaRaw === '' || $apptFechaRaw === '0000-00-00' || $apptTs === false || $apptTs <= 0) {
                    // Omitir este registro: la fecha de cita es inválida y causaría la visualización de "-0001"
                    continue;
                }
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

            // Excluir registros con estatus 'agendado'
            if ($merged['estatus'] === 'agendado') {
                continue;
            }
            // Excluir registros con origen Wedding Planner (how_did_you_meet=1) y estatus 'cliente'
            $howDidYouMeetRaw = trim((string)($merged['how_did_you_meet'] ?? ''));
            $merged['how_did_you_meet_raw'] = $howDidYouMeetRaw;
            if ($howDidYouMeetRaw === '1' && $merged['estatus'] === 'cliente') {
                continue;
            }

            // Auto-fill how_did_you_meet desde how_long_known_us cuando está vacío
            if (trim((string)($merged['how_did_you_meet'] ?? '')) === '') {
                $_knownUs = mb_strtolower(trim((string)($merged['how_long_known_us'] ?? '')), 'UTF-8');
                if (in_array($_knownUs, ['less than 6 months', 'less than 3 months', 'between 3 months and 1 year'], true)) {
                    $merged['how_did_you_meet'] = '3'; // New Audience
                } elseif (in_array($_knownUs, ['more than 6 months', 'more than 1 year'], true)) {
                    $merged['how_did_you_meet'] = '2'; // Community
                }
            }

            $allLeads[] = $merged;
        }
    }
}

// Calcular conteo de registros en contact_form con campaign_name no vacío (para mostrar en un card)
$campaignCount = 0;
foreach ($allLeads as $lead) {
    $cn = isset($lead['campaign_name']) ? trim($lead['campaign_name']) : '';
    if ($cn === '' || mb_strtolower($cn, 'UTF-8') === 'n/a') continue;
    $campaignCount++;
}

// Leer filtro de plataforma desde GET
$filterPlataforma = isset($_GET['filter_plataforma']) ? trim($_GET['filter_plataforma']) : '';
// Etiqueta amigable para mostrar en los cards: (todo|Campañas digitales|organico)
$platformLabel = 'todo';
if ($filterPlataforma === 'campania') {
    $platformLabel = 'Campañas digitales';
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
// Si el usuario no proporciona fechas, por defecto mostrar los últimos 14 días
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
// Solo aplicar el default cuando NO se pida explícitamente "mostrar todo" (show_all=1)
if ($startDate === '' && $endDate === '' && empty($_GET['show_all'])) {
    // fecha de hoy como end_date y start_date 14 días antes
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-14 days', strtotime($endDate)));
} 
// (Código de orígenes y medios removido - se usa filtro de Plataforma)

// (Código de estatus únicos, medios únicos y filtros origen/medio/estatus removido)

// Helper: Normalize origen (c1., c2. -> c1/c2) - ensure function available globally
if (!function_exists('normalizeOrigen')) {
    function normalizeOrigen($orig) {
        if (!$orig) return '';
        if (preg_match('/^c(\d+)\./i', $orig, $m)) return 'c' . $m[1];
        return $orig;
    }
}

// Helper: determina si un lead tiene estatus 'fantasma'.
// Acepta tanto la cadena "fantasma" en diferentes mayúsculas como el valor numérico 2.
if (!function_exists('isFantasmaLead')) {
    function isFantasmaLead($lead) {
        $st = $lead['estatus'] ?? '';
        if (is_numeric($st) && intval($st) === 2) return true;
        $stl = mb_strtolower(trim((string)$st), 'UTF-8');
        return $stl === 'fantasma';
    }
}

// Helper: extrae fecha de un timestamp que puede ser ISO 8601 o datetime
// Similar a la lógica SQL: CASE WHEN created_time LIKE '%T%' THEN ... ELSE ... END
if (!function_exists('extractDateFromTimestamp')) {
    function extractDateFromTimestamp($timestamp) {
        if (empty($timestamp)) return null;
        
        // Si contiene 'T', es formato ISO 8601 (2026-02-03T09:12:17-06:00)
        if (strpos($timestamp, 'T') !== false) {
            // Extraer los primeros 19 caracteres (YYYY-MM-DDTHH:MM:SS)
            $normalized = substr($timestamp, 0, 19);
            // Convertir a timestamp de PHP
            $ts = strtotime($normalized);
            if ($ts === false) return null;
            return date('Y-m-d', $ts);
        }
        
        // Si es datetime normal (2026-02-03 09:12:17), extraer fecha directamente
        $ts = strtotime($timestamp);
        if ($ts === false) return null;
        return date('Y-m-d', $ts);
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

if (!function_exists('getOrigenCategoriaLabel')) {
    function getOrigenCategoriaLabel($lead) {
        $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
        $tablaOrigen = strtolower(trim((string)($lead['tabla_origen'] ?? '')));
        $howMap = [
            '1' => 'Wedding Planner',
            '2' => 'Community',
            '3' => 'New Audience'
        ];

        if (in_array($tablaOrigen, ['eventos_wp', 'wp_eventos_afianzados', 'wp_citas_leads'], true)) {
            $howStored = trim((string)($lead['how_did_you_meet_raw'] ?? $howRaw));
            if ($howStored === '' || $howStored === '1') {
                return 'Community';
            }
            $howRaw = $howStored;
        }

        if ($howRaw !== '' && isset($howMap[$howRaw])) {
            return $howMap[$howRaw];
        }
        return 'N/A';
    }
}

if (!function_exists('normalizeFirstContactChannelLabel')) {
    function normalizeFirstContactChannelLabel($value) {
        $normalized = trim((string) $value);
        if ($normalized === '') return 'Sin dato';
        $key = mb_strtolower($normalized, 'UTF-8');
        $key = str_replace(['–', '—'], '-', $key);
        $key = preg_replace('/\s+/', ' ', $key);
        $map = [
            'ig'                           => 'IG',
            'whatsapp'                     => 'WhatsApp',
            'instagram dm - campaign'      => 'Instagram DM - Campaña',
            'instagram dm campaign'        => 'Instagram DM - Campaña',
            'instagram dm - organic'       => 'Instagram DM - Orgánico',
            'instagram dm organic'         => 'Instagram DM - Orgánico',
            'email'                        => 'Correo electrónico',
            'correo electronico'           => 'Correo electrónico',
            'correo electrónico'           => 'Correo electrónico',
            'mail'                         => 'Correo electrónico',
            'phone call'                   => 'Phone call',
            'llamada telefonica'           => 'Phone call',
            'llamada telefónica'           => 'Phone call',
        ];
        return $map[$key] ?? $normalized;
    }
}

if (!function_exists('getContactChannelBadgeLabel')) {
    function getContactChannelBadgeLabel($label) {
        $map = [
            'IG' => 'IG',
            'WhatsApp' => 'WhatsApp',
            'Instagram DM - Campaña' => 'IG',
            'Instagram DM - Orgánico' => 'IG',
            'Correo electrónico' => 'Email',
            'Llamada telefónica' => 'Phone Call',
            'Phone call' => 'Phone Call',
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
        if ($normalized === '') {
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

if (!function_exists('formatLeadDate')) {
    function formatLeadDate($dateString) {
        $value = trim((string) $dateString);
        if ($value === '') return '—';
        $timestamp = strtotime($value);
        if ($timestamp === false) return $value;
        return date('d/m/Y', $timestamp);
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

if (!function_exists('getEngagementLabelWithEmoji')) {
    function getEngagementLabelWithEmoji($lead) {
        $raw = trim((string)($lead['engagement'] ?? ''));
        if ($raw === '') return 'N/A';
        $normalized = mb_strtolower($raw, 'UTF-8');
        if ($normalized === '0') return 'Sin dato';
        if ($normalized === '1' || $normalized === 'bajo') return '😑 Bajo';
        if ($normalized === '2' || $normalized === 'medio') return '😃 Medio';
        if ($normalized === '3' || $normalized === 'alto') return '🔥 Alto';
        return $raw;
    }
}

// Calcular conteo total de leads que se muestran en la tabla (tienen cita) y conteo filtrado por fecha
$leadsCount = 0;
foreach ($allLeads as $lead) {
    $leadsCount++;
}
$leadsCountFiltered = 0; 

if ($startDate === '' && $endDate === '') {
    $leadsCountFiltered = $leadsCount;
} else {
    $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
    $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;

    foreach ($allLeads as $lead) {
        // Usar created_time del lead original si existe, sino usar submission_date
        $dateField = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
        if (empty($dateField))
            continue;
        
        $d = extractDateFromTimestamp($dateField);
        if ($d === null)
            continue;

        if ($sd && $d < $sd)
            continue;
        if ($ed && $d > $ed)
            continue;

        $leadsCountFiltered++;
    }
}

// (Código de conteo de campaña filtrado removido - simplificado)

// Crear lista filtrada para mostrar en la tabla (aplicar filtros de Plataforma y fecha)
$displayLeads = [];

// Aplicar filtros
if ($startDate === '' && $endDate === '' && $filterPlataforma === '') {
    $displayLeads = $allLeads;
} else {
    $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
    $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
    $hasFechaFilter = ($sd !== null || $ed !== null);

    foreach ($allLeads as $lead) {
        // Filtro por fecha (solo validar fecha si hay filtro de fecha activo)
        if ($hasFechaFilter) {
            // Usar created_time del lead original si existe, sino usar submission_date
            $dateField = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
            if (empty($dateField))
                continue;
            
            $d = extractDateFromTimestamp($dateField);
            if ($d === null)
                continue;

            if ($sd && $d < $sd) continue;
            if ($ed && $d > $ed) continue;
        }

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

// Solo incluir leads que en algún momento pasaron a estatus 1 (atendido) según calendario_estatus_historial.
$postLeadsAtendidoHistIds = [];
ensureCalendarioEstatusHistorialTable($conn);
$candidateLeadIds = [];
foreach ($displayLeads as $lead) {
    $lid = intval($lead['id'] ?? 0);
    if ($lid > 0) {
        $candidateLeadIds[$lid] = $lid;
    }
}
if (!empty($candidateLeadIds)) {
    $idsList = implode(',', array_map('intval', array_values($candidateLeadIds)));
    $_resAtendidosHist = $conn->query(
        "SELECT DISTINCT c.idclie AS idclie
         FROM calendario_estatus_historial h
         INNER JOIN calendario c ON c.id = h.id_calendario
         WHERE h.estatus = 1
           AND c.idclie IN ($idsList)"
    );
    if ($_resAtendidosHist) {
        while ($_rowAH = $_resAtendidosHist->fetch_assoc()) {
            $idclie = intval($_rowAH['idclie'] ?? 0);
            if ($idclie > 0) {
                $postLeadsAtendidoHistIds[$idclie] = true;
            }
        }
    }
}
$displayLeadsFiltered = [];
foreach ($displayLeads as $lead) {
    if (isset($postLeadsAtendidoHistIds[intval($lead['id'] ?? 0)])) {
        $displayLeadsFiltered[] = $lead;
    }
}
$displayLeads = $displayLeadsFiltered;
$postLeadsLlamadaRealizada = count($displayLeads);
// Índices de columnas ocultas para DataTables (evita desfase thead/tbody → tabla vacía)
$dtHiddenColumnTargets = ($tipoUsuario != 4) ? [12, 14, 15] : [12, 13, 14];

// Asegurar que el conteo mostrado coincide con la lista que se muestra
$leadsCountFiltered = 0;
foreach ($displayLeads as $lead) {
    $leadsCountFiltered++;
}

// Contar leads con fecha_cambio_cliente
$leadsWithFechaCambio = 0;
foreach ($displayLeads as $lead) {
    $fechaCambio = isset($lead['fecha_cambio_cliente']) ? trim($lead['fecha_cambio_cliente']) : '';
    if (!empty($fechaCambio) && $fechaCambio !== '0000-00-00' && $fechaCambio !== '0000-00-00 00:00:00') {
        $leadsWithFechaCambio++;
    }
}

// Calcular ratio de conversión (leads totales / leads que se convirtieron a cliente)
$conversionRatio = 0;
if ($leadsWithFechaCambio > 0) {
    $conversionRatio =  $leadsWithFechaCambio/  $leadsCountFiltered;
}

// ============================================================================
// CÁLCULO DE MÉTRICAS PARA CARDS Y GRÁFICA (basado en datos filtrados)
// Estas métricas se actualizan automáticamente según los filtros aplicados:
// - Filtro de plataforma (organico/campania)
// - Filtro de fechas (start_date/end_date)
// ============================================================================

// ===== Conteo de leads que llegaron hoy (basado en created_time o submission_date filtrado) =====
$todayPostLeadsCount = 0;
$today = date('Y-m-d');
foreach ($displayLeads as $lead) {
    // Usar created_time del lead original si existe, sino usar submission_date
    $dateField = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
    if (empty($dateField))
        continue;
    
    $d = extractDateFromTimestamp($dateField);
    if ($d === null)
        continue;
    
    if ($d === $today)
        $todayPostLeadsCount++;
} 

// Construir serie de Highcharts: contar leads post qualified por día (filtrados)
// Construir mapa diario con conteo y min/max de horas (para mostrar en tooltip)
$dayMap = []; // ['YYYY-MM-DD' => ['count'=>int, 'min'=>ts, 'max'=>ts]]
$globalMin = null;
$globalMax = null;
// Use $displayLeads to ensure the chart reflects any active date filters
foreach ($displayLeads as $lead) {
    // Usar created_time del lead original si existe, sino usar submission_date
    $dateField = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
    if (empty($dateField))
        continue;
    
    $d = extractDateFromTimestamp($dateField);
    if ($d === null)
        continue;
    $ts = strtotime($dateField);
    if ($ts === false)
        continue;
    if (!isset($dayMap[$d])) {
        $dayMap[$d] = ['count' => 0, 'min' => $ts, 'max' => $ts];
    }
    $dayMap[$d]['count']++;
    if ($ts < $dayMap[$d]['min'])
        $dayMap[$d]['min'] = $ts;
    if ($ts > $dayMap[$d]['max'])
        $dayMap[$d]['max'] = $ts;

    if ($globalMin === null || $ts < $globalMin)
        $globalMin = $ts;
    if ($globalMax === null || $ts > $globalMax)
        $globalMax = $ts;
} 

if ($globalMin === null) {
    $seriesJson = json_encode([]);
    $datesJson = json_encode([]);
    $countsJson = json_encode([]);
    $seriesMetaJson = json_encode(new stdClass());
} else {
    // By default (no date filter) show last 60 days (including today).
    // If the user provided start_date / end_date via GET, respect those instead.
    if ($startDate === '' && $endDate === '') {
        $end = strtotime(date('Y-m-d')); // today at 00:00
        $start = strtotime(date('Y-m-d', strtotime('-59 days'))); // 60 days including today
    } else {
        // Start from dataset bounds but allow GET filters to override
        $start = strtotime(date('Y-m-d', $globalMin));
        $end = strtotime(date('Y-m-d', $globalMax));
        if ($startDate !== '') {
            $sd_ts = strtotime(date('Y-m-d', strtotime($startDate)));
            if ($sd_ts !== false)
                $start = $sd_ts;
        }
        if ($endDate !== '') {
            $ed_ts = strtotime(date('Y-m-d', strtotime($endDate)));
            if ($ed_ts !== false)
                $end = $ed_ts;
        }
    }

    // ensure valid range
    if ($end < $start)
        $end = $start;

    $series = [];
    $dates = [];
    $counts = [];
    $seriesMeta = [];
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $d = date('Y-m-d', $ts);
        $c = isset($dayMap[$d]) ? $dayMap[$d]['count'] : 0;
        // x value: start of day timestamp in ms (keeps x-axis per day)
        $x = $ts * 1000;
        $series[] = [$x, $c];
        $dates[] = $d;
        $counts[] = $c;

        if (isset($dayMap[$d])) {
            // guardar horas mín y máx en formato HH:MM para tooltip
            $metaMin = date('H:i', $dayMap[$d]['min']);
            $metaMax = date('H:i', $dayMap[$d]['max']);
            $seriesMeta[(string) $x] = ['min' => $metaMin, 'max' => $metaMax];
        }
    }
    $seriesJson = json_encode($series);
    $datesJson = json_encode($dates);
    $countsJson = json_encode($counts);
    $seriesMetaJson = json_encode($seriesMeta);
}

// ===== OBTENER LEADS PRE-CALIFICADOS (de tablas_leads) PARA LA GRÁFICA =====
$tablasLeads = [];
// Filtrar tablas según plataforma (igual que consulta_leads.php)
if ($filterPlataforma === 'organico') {
    $sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo = 0 ORDER BY nombre";
} elseif ($filterPlataforma === 'campania') {
    $sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo = 1 OR nombre = 'organic_leads' ORDER BY nombre";
} else {
    $sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo != 2 ORDER BY nombre";
}
$resultTablas = $conn->query($sqlTablas);
if ($resultTablas && $resultTablas->num_rows > 0) {
    while ($row = $resultTablas->fetch_assoc()) {
        $tablasLeads[] = $row['nombre'];
    }
}

$b1b2ValuesPreQ = ['b1', 'b1 (usa)', 'b1 usa', 'b2', 'b2 (mx)', 'b2 mx'];

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
        $wherePre = [];
        if (in_array('descartado', $columns)) {
            $wherePre[] = "(descartado = 0 OR descartado IS NULL)";
        }
        // Aplicar filtro B1/B2 en organic_leads según plataforma
        if ($tableName === 'organic_leads' && in_array('campaign_name', $columns)) {
            $campaignColPre = "LOWER(TRIM(campaign_name))";
            $inListPre = "'" . implode("','", array_map([$conn, 'real_escape_string'], $b1b2ValuesPreQ)) . "'";
            if ($filterPlataforma === 'organico') {
                $wherePre[] = "($campaignColPre IS NULL OR $campaignColPre = '' OR $campaignColPre NOT IN ($inListPre))";
            } elseif ($filterPlataforma === 'campania') {
                $wherePre[] = "$campaignColPre IN ($inListPre)";
            }
        }
        $whereSqlPre = !empty($wherePre) ? implode(' AND ', $wherePre) : '1=1';
        $sqlLeads = "SELECT created_time FROM `$tableName` WHERE " . $whereSqlPre;
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
$totalPreLeadsFiltered = 0; // Contador de leads pre-qualified que pasan los filtros

foreach ($allPreLeads as $lead) {
    if (empty($lead['created_time']))
        continue;
    // Extraer la fecha local sin aplicar conversión de zona horaria (igual que el filtro SQL en consulta_leads.php).
    // created_time puede ser ISO 8601 (2026-02-03T09:12:17-06:00) o datetime (2026-02-03 09:12:17).
    $rawCT = $lead['created_time'];
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $rawCT, $_m)) {
        $d = $_m[1];
    } else {
        continue;
    }
    if ($startDate !== '' || $endDate !== '') {
        $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
        $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
        if ($sd && $d < $sd)
            continue;
        if ($ed && $d > $ed)
            continue;
    }
    if (!isset($preLeadsDayMap[$d]))
        $preLeadsDayMap[$d] = 0;
    $preLeadsDayMap[$d]++;
    $totalPreLeadsFiltered++; // Contar cada lead pre-qualified que pasa los filtros
}

// Generar serie de leads pre-calificados usando el mismo rango de fechas
if ($globalMin === null) {
    $preLeadsSeriesJson = json_encode([]);
} else {
    $preLeadsSeries = [];
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $d = date('Y-m-d', $ts);
        $c = isset($preLeadsDayMap[$d]) ? $preLeadsDayMap[$d] : 0;
        $x = $ts * 1000;
        $preLeadsSeries[] = [$x, $c];
    }
    $preLeadsSeriesJson = json_encode($preLeadsSeries);
}

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

// ============================================================================
// CÁLCULO DE Tasa de Conversión PRE-Q (basado en datos filtrados)
// Fórmula: (clientes / agendas totales) * 100
// ============================================================================

// Calcular Tasa de Conversión POST-Q:
// - Numerador: clientes cerrados (cliente = 1)
// - Denominador: agendados totales (estatus agendado, atendido, fantasma, muerto, cliente)
$totalRegistrosFiltered = count($displayLeads); // Total de registros (leads con cita)

// Contar TODOS los clientes cerrados (cliente = 1) con fecha_cambio_cliente dentro del rango
// Consultar directamente de contact_form para incluir cierres sin cita en calendario
$totalAtendidosFiltered = 0;
$sd_c = ($startDate !== '') ? date('Y-m-d', strtotime($startDate)) : null;
$ed_c = ($endDate !== '') ? date('Y-m-d', strtotime($endDate)) : null;
$_sqlCierres = "SELECT fecha_cambio_cliente, tabla_origen FROM contact_form WHERE cliente = 1 AND LOWER(COALESCE(tabla_origen, '')) NOT IN ('wedding_planners','wedding_planner')";
$_resCierres = $conn->query($_sqlCierres);
if ($_resCierres && $_resCierres->num_rows > 0) {
    while ($_rowC = $_resCierres->fetch_assoc()) {
        if ($sd_c !== null || $ed_c !== null) {
            $fc = trim($_rowC['fecha_cambio_cliente'] ?? '');
            if (empty($fc)) continue;
            $fcts = strtotime($fc);
            if ($fcts === false) continue;
            $fd = date('Y-m-d', $fcts);
            if ($sd_c && $fd < $sd_c) continue;
            if ($ed_c && $fd > $ed_c) continue;
        }
        $totalAtendidosFiltered++;
    }
} 

// Tasa de Conversión Post-Q (Clientes cerrados / Agendados × 100)
// El grupo "agendados" incluye los estatus:
// 'agendado', 'atendido', 'fantasma', 'muerto' y 'cliente' — además de sus códigos numéricos 0,1,2,3.
$totalAgendadasFiltered = 0;
foreach ($displayLeads as $lead) {
    $st = isset($lead['estatus']) ? mb_strtolower(trim((string)$lead['estatus']), 'UTF-8') : '';

    // Considerar textos reconocidos O códigos numéricos (0..3) O cliente cerrado
    if (
        $st === 'agendado' ||
        $st === 'atendido' ||
        $st === 'fantasma' ||
        $st === 'muerto' ||
        $st === 'cliente' ||
        (is_numeric($st) && in_array(intval($st), [0,1,2,3], true)) ||
        (isset($lead['cliente']) && intval($lead['cliente']) === 1)
    ) {
        $totalAgendadasFiltered++;
    }
}
$asistenciaAtendidos = $totalAtendidosFiltered;
$asistenciaAgendadas = $totalAgendadasFiltered;
// Calcular Tasa de Conversión POST-Q como porcentaje: Clientes cerrados / Agendados × 100
$asistenciaRate = ($asistenciaAgendadas > 0) ? round(((float)$asistenciaAtendidos / (float)$asistenciaAgendadas) * 100, 2) : 0.0;

// Tasa de booking: agendados (booked) / leads pre-q × 100 (igual que consulta_leads.php)
$bookingRate = ($totalPreLeadsFiltered > 0) ? round(((float)$totalAgendadasFiltered / (float)$totalPreLeadsFiltered) * 100, 2) : 0.0;

// ============================================================================
// CONTEO DE ENGAGEMENT (solo entre atendidos)
// ============================================================================
$engHighCount = 0;
$engMidCount  = 0;
$engLowCount  = 0;
foreach ($displayLeads as $lead) {
    $st = isset($lead['estatus']) ? mb_strtolower(trim((string)$lead['estatus']), 'UTF-8') : '';
    $isAtendido = ($st === 'atendido' || $st === '1' || (is_numeric($st) && intval($st) === 1));
    if (!$isAtendido) continue;
    $engRaw = trim((string)($lead['engagement'] ?? ''));
    $engNorm = mb_strtolower($engRaw, 'UTF-8');
    if ($engNorm === '3' || $engNorm === 'alto')  $engHighCount++;
    elseif ($engNorm === '2' || $engNorm === 'medio') $engMidCount++;
    elseif ($engNorm === '1' || $engNorm === 'bajo')  $engLowCount++;
}

// Variables para la UI (mantener compatibilidad con nombres anteriores)
$totalRegistros = $totalRegistrosFiltered;
$totalAtendidos = $totalAtendidosFiltered;
// Compatibilidad con variables previas si hay código que las espera
$totalAgendas = $totalRegistrosFiltered;
$totalClientes = $totalAtendidosFiltered;

// ============================================================================
// DATOS PARA SECCIÓN DE REPORTES (2. Apartado de Reportes)
// Todos los displayLeads son agendados (tienen cita en calendario)
// ============================================================================

// Etiqueta del periodo para el chip de rango
$reportRangeLabel = 'Todos los registros';
if (($startDate !== '' || $endDate !== '') && empty($_GET['show_all'])) {
    $reportRangeStart = $startDate !== '' ? date('d/m/Y', strtotime($startDate)) : '...';
    $reportRangeEnd   = $endDate   !== '' ? date('d/m/Y', strtotime($endDate))   : '...';
    $reportRangeLabel = $reportRangeStart . ' → ' . $reportRangeEnd;
}

// Gráfica 1: serie por día (ya tenemos $datesJson / $countsJson de la lógica de Highcharts anterior)

// Gráfica 2: Distribución de contacto (Método de contacto)
$postContactMethodCounts = [];
foreach ($displayLeads as $lead) {
    $label = getContactChannelBadgeLabel(normalizeFirstContactChannelLabel(resolveFirstContactChannelForLead($lead)));
    $postContactMethodCounts[$label] = ($postContactMethodCounts[$label] ?? 0) + 1;
}
arsort($postContactMethodCounts);
$postContactMethodPieData = [];
foreach ($postContactMethodCounts as $label => $count) {
    if ($count <= 0) continue;
    $postContactMethodPieData[] = ['name' => $label, 'y' => $count];
}
$postContactMethodPieJson = json_encode($postContactMethodPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Gráfica 3: Desde cuándo nos conoce (how_long_known_us)
$postKnownUsCounts = [];
$postKnownUsLabelMap = [
    'less than 3 months'          => 'Menos de 3 meses',
    'between 3 months and 1 year' => 'Entre 3 meses y 1 año',
    'more than 1 year'            => 'Más de 1 año',
    'not asked'                   => 'No se preguntó',
];
foreach ($displayLeads as $lead) {
    $raw = trim((string)($lead['how_long_known_us'] ?? ''));
    if ($raw === '' || $raw === '—') {
        $label = 'Sin dato';
    } else {
        $label = $postKnownUsLabelMap[mb_strtolower($raw, 'UTF-8')] ?? $raw;
    }
    $postKnownUsCounts[$label] = ($postKnownUsCounts[$label] ?? 0) + 1;
}
arsort($postKnownUsCounts);

// Gráfica 5: Campañas (campaign_name)
$postCampaignCounts = [];
foreach ($displayLeads as $lead) {
    $_refUrl = trim((string)($lead['referrer_url'] ?? ''));
    $_fcc    = trim((string)($lead['first_contact_channel'] ?? ''));
    $cn = formatCampaignDisplayLabel(trim((string)($lead['campaign_name'] ?? '')), $_refUrl, $_fcc);
    if ($cn === '' || $cn === '—') $cn = 'Sin dato';
    $postCampaignCounts[$cn] = ($postCampaignCounts[$cn] ?? 0) + 1;
}
arsort($postCampaignCounts);

$postKnownUsPieData = [];
foreach ($postKnownUsCounts as $label => $count) {
    if ($count <= 0) continue;
    $postKnownUsPieData[] = ['name' => $label, 'y' => $count];
}
$postKnownUsPieJson = json_encode($postKnownUsPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Gráfica 4: De dónde nos conocen (how_did_you_meet)
$postWhereKnowCounts = ['Wedding Planner' => 0, 'Community' => 0, 'New Audience' => 0, 'Sin dato' => 0];
foreach ($displayLeads as $lead) {
    $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
    if ($howRaw === '1') $postWhereKnowCounts['Wedding Planner']++;
    elseif ($howRaw === '2') $postWhereKnowCounts['Community']++;
    elseif ($howRaw === '3') $postWhereKnowCounts['New Audience']++;
    else $postWhereKnowCounts['Sin dato']++;
}
$postWhereKnowColorMap = [
    'Wedding Planner' => '#3B82F6',
    'Community'       => '#10B981',
    'New Audience'    => '#A855F7',
    'Sin dato'        => '#94a3b8',
];
$postWhereKnowPieData = [];
foreach ($postWhereKnowCounts as $wlabel => $wcount) {
    if ($wcount <= 0) continue;
    $postWhereKnowPieData[] = ['name' => $wlabel, 'y' => $wcount, 'color' => ($postWhereKnowColorMap[$wlabel] ?? null)];
}
$postWhereKnowPieJson = json_encode($postWhereKnowPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$postLeadsConOrigenConocido = 0;
$postLeadsClosedCount = 0;
$postLeadsOriginPending = 0;
$postLeadsEngagementHigh = 0;

foreach ($displayLeads as $lead) {
    $originRaw = trim((string) ($lead['how_did_you_meet'] ?? ''));
    $statusRaw = mb_strtolower(trim((string) ($lead['estatus'] ?? '')), 'UTF-8');
    $engagementRaw = mb_strtolower(trim((string) ($lead['engagement'] ?? '')), 'UTF-8');

    if (in_array($originRaw, ['1', '2', '3'], true)) {
        $postLeadsConOrigenConocido++;
    } else {
        $postLeadsOriginPending++;
    }

    if ($statusRaw === 'cliente' || intval($lead['cliente'] ?? 0) === 1) {
        $postLeadsClosedCount++;
    }

    if ($engagementRaw === '3' || $engagementRaw === 'alto') {
        $postLeadsEngagementHigh++;
    }
}

$postEventWpIds = [];
foreach ($displayLeads as $lead) {
    if (strcasecmp((string) ($lead['tabla_origen'] ?? ''), 'eventos_wp') !== 0) {
        continue;
    }

    $eventId = intval($lead['original_lead_id'] ?? 0);
    if ($eventId > 0) {
        $postEventWpIds[$eventId] = $eventId;
    }
}

$postEventWpAfianzadosMap = [];
if (!empty($postEventWpIds)) {
    $postEventIdsList = implode(',', array_map('intval', array_values($postEventWpIds)));
    $sqlPostEventWpAfianzados = "SELECT e.id, COALESCE(wp.afianzado, 0) AS afianzado FROM eventos_wp e LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id WHERE e.id IN ($postEventIdsList)";
    $resPostEventWpAfianzados = $conn->query($sqlPostEventWpAfianzados);
    if ($resPostEventWpAfianzados) {
        while ($rowPostEventWp = $resPostEventWpAfianzados->fetch_assoc()) {
            $postEventWpAfianzadosMap[intval($rowPostEventWp['id'] ?? 0)] = intval($rowPostEventWp['afianzado'] ?? 0) === 1 ? 1 : 0;
        }
    }
}

// Conteo total de todos los eventos de wedding planners afianzados (sin filtro de fechas)
$resPostWpAfianzadosTotal = $conn->query("SELECT COUNT(e.id) AS total FROM eventos_wp e INNER JOIN wedding_planners wp ON wp.id = e.wedding_planner_id WHERE wp.afianzado = 1");
$postWpAfianzadosCount = 0;
if ($resPostWpAfianzadosTotal) {
    $rowPostWpAfianzadosTotal = $resPostWpAfianzadosTotal->fetch_assoc();
    $postWpAfianzadosCount = intval($rowPostWpAfianzadosTotal['total'] ?? 0);
}

// Cargar mapa de paquetes (id => nombre) para mostrar en la tabla
$paquetesMap = [];
$resPaq = $conn->query("SELECT id, nombre FROM paquetes ORDER BY id");
if ($resPaq && $resPaq->num_rows > 0) {
    while ($rowPaq = $resPaq->fetch_assoc()) {
        $paquetesMap[intval($rowPaq['id'])] = $rowPaq['nombre'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post-Qualified Leads — EFEGE</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/heatmap.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <style>
        /* ══════════════════════════════════════════════
           EFEGE Post-Qualified Leads  —  Light Mode
           Design language: reports_dashboard_EFEGE.html
        ══════════════════════════════════════════════ */
            :root {
                --gold: #C5A028;
                --gold-dim: rgba(197,160,40,0.12);
                --dark: #F8FAFC;
                --panel: #FFFFFF;
                --surface: #F1F5F9;
                --ink: #1E293B;
                --muted: #64748B;
                --border: #E2E8F0;
                --planner: #3B82F6;
                --planner-bg: rgba(59,130,246,0.12);
                --community: #10B981;
                --community-bg: rgba(16,185,129,0.12);
                --newmarket: #A855F7;
                --newmarket-bg: rgba(168,85,247,0.12);
                --eng-low: #F59E0B;
                --eng-mid: #3B82F6;
                --eng-high: #10B981;
                --danger: #DC2626;
                --danger-bg: rgba(220,38,38,0.12);
                --radius: 12px;
            }

            * { box-sizing: border-box; }
            body { background: var(--dark) !important; }

            .efege-page {
                padding: 0 20px 60px;
                font-family: 'DM Sans', system-ui, sans-serif;
                color: var(--ink);
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
            .efege-page-header-right { display: flex; align-items: center; gap: 10px; }

            .efege-page-title {
                font-family: 'Cormorant Garamond', Georgia, serif;
                font-size: 26px;
                font-weight: 600;
                letter-spacing: 2px;
                color: var(--ink);
                line-height: 1;
                margin-bottom: 4px;
            }

            .efege-page-title-sub {
                font-size: 14px;
                letter-spacing: 1px;
                font-weight: 400;
                color: var(--muted);
                font-family: 'DM Sans', system-ui, sans-serif;
            }

            .efege-page-subtitle { font-size: 12px; color: var(--muted); margin-top: 4px; }

            .efege-view-note {
                margin-bottom: 16px;
                padding: 14px 16px 14px 20px;
                background: #f0f9ff;
                border: 1px solid #bae6fd;
                border-left: 4px solid #2563eb;
                border-radius: 10px;
                font-size: 0.86rem;
                color: #0c4a6e;
                line-height: 1.5;
            }

            .efege-view-note p { margin: 0; }

            .efege-date-range,
            .efege-live-badge {
                display: inline-flex;
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

            .kpi-strip {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 12px;
                margin-bottom: 24px;
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

            .efege-filter-input,
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
            }

            .efege-filter-search { width: 220px; }
            .efege-filter-input:focus,
            .efege-filter-search:focus,
            .filter-search:focus,
            .lead-update:focus { border-color: var(--gold); }

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
            .efege-btn-primary { background: #0f172a; border-color: #0f172a; color: #fff; }
            .efege-btn-primary:hover { background: #1e293b; border-color: #1e293b; color: #fff; }
            .efege-btn-success { background: #059669; border-color: #059669; color: #fff; }
            .efege-btn-success:hover { background: #047857; border-color: #047857; color: #fff; }

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

            .report-overview-grid,
            .report-breakdown-stack {
                display: grid;
                gap: 16px;
            }

            .report-chart-card,
            .report-card,
            .report-breakdown-card,
            .report-insight {
                background: var(--dark);
                border: 1px solid var(--border);
                border-radius: 14px;
                padding: 18px;
            }

            .report-chart-card-full { width: 100%; }

            .report-chart-header,
            .report-breakdown-row {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
            }

            .report-chart-title {
                font-size: 15px;
                font-weight: 700;
                color: var(--ink);
                margin: 0 0 4px;
            }

            .report-chart-subtitle,
            .report-card-note,
            .report-insight-detail {
                font-size: 12px;
                color: var(--muted);
                margin: 0;
            }

            .report-scope-hint {
                font-size: 11px;
                font-weight: 400;
                color: var(--muted);
                margin-left: 6px;
            }

            .report-chart { width: 100%; min-height: 240px; }

            .report-metric-grid,
            .report-breakdown-grid {
                display: grid;
                gap: 12px;
            }

            .report-metric-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
            .report-breakdown-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }

            .report-card-highlight,
            .report-insight {
                background: linear-gradient(135deg, rgba(197,160,40,0.10), rgba(59,130,246,0.05));
                border-color: rgba(197,160,40,0.22);
            }

            .report-card-label,
            .report-insight-kicker {
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

            .report-breakdown-list { display: grid; gap: 10px; margin-top: 14px; }
            .report-breakdown-item { display: grid; gap: 8px; }
            .report-breakdown-label { font-size: 13px; font-weight: 600; color: var(--ink); min-width: 0; }
            .report-breakdown-meta { flex-shrink: 0; font-size: 12px; color: var(--muted); white-space: nowrap; }
            .report-breakdown-bar { width: 100%; height: 8px; border-radius: 999px; background: var(--surface); overflow: hidden; }
            .report-breakdown-fill { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, rgba(37,99,235,0.85), rgba(197,160,40,0.7)); }
            .report-breakdown-empty { margin: 0; font-size: 12px; color: var(--muted); }

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

            .report-origin-pending strong { font-weight: 700; }
            .report-insight-text { margin: 0; font-size: 18px; line-height: 1.4; color: var(--ink); font-weight: 600; }
            .report-insight-text span { color: var(--gold); }

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
                gap: 12px;
                flex-wrap: wrap;
            }

            .table-title { font-size: 13px; font-weight: 600; color: var(--ink); }
            .table-count { font-size: 11px; color: var(--muted); }

            .efege-table-scroll {
                overflow-x: auto;
                overflow-y: auto;
                max-height: 820px;
                width: 100%;
                scrollbar-width: thin;
                scrollbar-color: var(--border) transparent;
            }

            .efege-table-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
            .efege-table-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

            .efege-table {
                width: 100% !important;
                border-collapse: separate;
                border-spacing: 0;
                font-size: 13px;
                min-width: 1220px;
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

            .efege-table tbody tr { border-bottom: 1px solid var(--border); }
            .efege-table tbody tr:hover { background: rgba(248,250,252,0.75) !important; }
            .efege-table tbody td { padding: 12px 16px !important; border-bottom: 1px solid var(--border) !important; color: var(--ink); vertical-align: middle !important; }

            .td-name { font-weight: 600; color: var(--ink); font-size: 13px; }
            .td-sub, .td-inline-muted { font-size: 11px; color: var(--muted); }
            .td-emphasis { font-weight: 600; color: var(--ink); }

            .badge,
            .ch-badge,
            .known,
            .eng,
            .status {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 10px;
                font-weight: 600;
                white-space: nowrap;
            }

            .badge-planner { background: var(--planner-bg); color: var(--planner); }
            .badge-community { background: var(--community-bg); color: var(--community); }
            .badge-newmarket { background: var(--newmarket-bg); color: var(--newmarket); }
            .badge-neutral, .ch-badge { background: var(--surface); color: var(--muted); border: 1px solid var(--border); }
            .badge-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
            .known { background: rgba(197,160,40,0.1); color: var(--gold); }
            .badge-campaign {
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
            .eng-low { background: rgba(245,158,11,0.12); color: var(--eng-low); }
            .eng-mid { background: rgba(59,130,246,0.12); color: var(--eng-mid); }
            .eng-high { background: rgba(16,185,129,0.12); color: var(--eng-high); }
            .status-scheduled { background: rgba(59,130,246,0.12); color: var(--planner); }
            .status-attended { background: rgba(16,185,129,0.12); color: var(--community); }
            .status-pending { background: rgba(245,158,11,0.12); color: var(--eng-low); }
            .status-closed { background: rgba(197,160,40,0.15); color: var(--gold); }
            .status-danger { background: var(--danger-bg); color: var(--danger); }

            .action-stack {
                min-width: 120px;
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            .lead-update,
            .form-select {
                min-width: 150px;
                max-width: 190px;
                border: 1px solid var(--border);
                border-radius: 7px;
                padding: 6px 10px;
                background: var(--surface);
                color: var(--ink);
                font-size: 12px;
                font-family: 'DM Sans', sans-serif;
                outline: none;
            }

            .filter-icon {
                cursor: pointer;
                margin-left: 5px;
                font-size: 0.75rem;
                color: var(--muted);
                transition: color 0.2s;
            }

            .filter-icon:hover,
            .filter-icon.active { color: var(--planner); }

            .th-flex-label {
                display: flex;
                align-items: flex-start;
                gap: 3px;
            }

            .th-flex-label .th-compact-label { flex: 1; }
            .th-compact-label {
                display: block;
                max-width: 96px;
                white-space: normal !important;
                word-break: break-word;
                overflow-wrap: anywhere;
                line-height: 1.15;
            }

            .filter-dropdown {
                position: fixed;
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                box-shadow: 0 8px 28px rgba(0,0,0,0.12);
                z-index: 1050;
                min-width: 250px;
                max-width: 400px;
                display: none;
            }

            .filter-dropdown.show { display: block; }
            .filter-dropdown-header,
            .filter-dropdown-footer {
                padding: 12px 16px;
                background: var(--surface);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .filter-dropdown-header { border-bottom: 1px solid var(--border); border-radius: var(--radius) var(--radius) 0 0; font-size: 12px; font-weight: 600; }
            .filter-dropdown-footer { border-top: 1px solid var(--border); border-radius: 0 0 var(--radius) var(--radius); gap: 8px; }
            .filter-dropdown-body { padding: 12px 16px; max-height: 300px; overflow-y: auto; }
            .filter-search { width: 100%; padding: 6px 12px; margin-bottom: 10px; border: 1px solid var(--border); border-radius: 7px; font-size: 12px; background: var(--surface); color: var(--ink); outline: none; }
            .filter-option { display: flex; align-items: center; padding: 5px 4px; cursor: pointer; user-select: none; border-radius: 5px; }
            .filter-option:hover { background: var(--surface); }
            .filter-option input[type="checkbox"] { margin-right: 8px; }
            .filter-option label { cursor: pointer; margin: 0; flex: 1; font-size: 12px; color: var(--ink); }

            .filter-btn-sm {
                padding: 5px 14px;
                font-size: 11px;
                font-weight: 600;
                border-radius: 6px;
                cursor: pointer;
                border: 1px solid var(--border);
                transition: all 0.2s;
            }

            .filter-btn-sm-secondary { background: var(--surface); color: var(--muted); }
            .filter-btn-sm-primary { background: var(--ink); color: #fff; border-color: var(--ink); }

            .option1 { background-color: #059669; color: #fff; }
            .option2 { background-color: #dc2626; color: #fff; }
            .option3 { background-color: #D4EDBC; color: #11734B; }
            .option4 { background-color: #ffe5a0; color: #8c7850; }
            .option5 { background-color: #ffcfc9; color: #dc2626; }
            .option6 { background-color: #e4cff1; color: #795d8d; }
            .option7 { background-color: #b9e3fa; color: #417295; }
            .option8 { background-color: #e9eaee; color: #555; }

            #verMasModal p { font-size: 1.1rem; }

            @media (max-width: 1300px) {
                .report-breakdown-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }

            @media (max-width: 1100px) {
                .kpi-strip,
                .report-metric-grid,
                .report-breakdown-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }

            @media (max-width: 780px) {
                .efege-page { padding: 0 12px 40px; }
                .kpi-strip,
                .report-metric-grid,
                .report-breakdown-grid { grid-template-columns: 1fr; }
                .table-header-custom { align-items: flex-start; }
                .efege-filter-search { width: 100%; }
            }

            .post-actions-cell { display: flex; flex-wrap: wrap; gap: 6px; }
    </style>
</head>

<body>
<div class="efege-page">

    <!-- PAGE HEADER -->
    <div class="efege-page-header">
        <div class="efege-page-header-left">
            <div class="efege-page-title"><span class="efege-page-title-sub">Post-Qualified Leads</span></div>
            <div class="efege-page-subtitle">Sesiones post-calificadas · Plataforma: <strong><?php echo htmlspecialchars($platformLabel); ?></strong></div>
        </div>
        <div class="efege-page-header-right">
            <?php if (!empty($startDate) && !empty($endDate)): ?>
            <div class="efege-date-range">📅 <span><?php echo date('d M Y', strtotime($startDate)); ?></span>&nbsp;→&nbsp;<span><?php echo date('d M Y', strtotime($endDate)); ?></span></div>
            <?php endif; ?>
            <a href="consulta_post_leads_trazabilidad.php" class="efege-btn" style="text-decoration:none;">
                <i class="fas fa-route"></i> Vista trazabilidad
            </a>
            <div class="efege-live-badge">🔴 Live</div>
        </div>
    </div>

        <!-- ════════════════ FILTER BAR ════════════════ -->
        <div class="filter-bar">
            <span class="filter-label">Filtros</span>
            <form method="get" id="filterForm" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <input type="date" name="start_date" class="efege-filter-input"
                    value="<?php echo htmlspecialchars($startDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="date" name="end_date" class="efege-filter-input"
                    value="<?php echo htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <!--<select name="filter_plataforma" id="filterPlataforma" class="filter-select" style="min-width:180px;">
                    <option value="">Todas las plataformas</option>
                    <option value="campania" <?php echo ($filterPlataforma === 'campania') ? 'selected' : ''; ?>>Campañas digitales</option>
                    <option value="organico"  <?php echo ($filterPlataforma === 'organico')  ? 'selected' : ''; ?>>Orgánico</option>
                </select>-->
                <button type="submit" class="efege-btn efege-btn-primary">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <a href="consulta_post_leads.php?show_all=1" class="efege-btn">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            </form>
        </div>

        <div class="efege-view-note">
            <p><strong>Nota:</strong> Esta vista mostrará resultados correctos para el estatus <strong>Atendido</strong> a partir del <strong>15/jun/2026</strong>, fecha en la que comenzó a registrarse la información bajo la nueva lógica de seguimiento de estatus.</p>
        </div>

        <!-- ════════════════ 2. APARTADO DE REPORTES ════════════════ -->
        <section class="reports-section">
            <div class="reports-section-header">
                <div>
                    <div class="reports-section-step">2. Apartado de Reportes</div>
                    <h2 class="reports-section-title">Reporte de sesiones post-calificadas <span class="report-scope-hint">(mostrando desde estatus atendidos)</span></h2>
                    <p class="reports-section-subtitle">Mantiene la misma estructura de consulta_leads, pero usando exclusivamente los datos de post leads.</p>
                </div>
                <div class="reports-range-chip">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?php echo htmlspecialchars($reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>

            <div class="report-overview-grid">
                <article class="report-chart-card report-chart-card-full">
                    <div class="report-chart-header">
                        <div>
                            <h3 class="report-chart-title">Sesiones agendadas por periodo</h3>
                            <p class="report-chart-subtitle">Total de sesiones por dia dentro del rango seleccionado.</p>
                        </div>
                    </div>
                    <div id="postLeadsChart" class="report-chart"></div>
                </article>

                <div class="report-metric-grid">
                    <article class="kpi-card">
                        <div class="kpi-label">Leads Post-Calificados</div>
                        <div class="kpi-value" id="kpi-post-total"><?php echo number_format($leadsCountFiltered); ?></div>
                        <div class="kpi-sub">Sesiones con estatus atendido registrado en calendario_estatus_historial.</div>
                    </article>

                    <article class="kpi-card">
                        <div class="kpi-label">Origen Conocido</div>
                        <div class="kpi-value" id="kpi-origin-known"><?php echo number_format($postLeadsConOrigenConocido); ?></div>
                        <div class="kpi-sub"><?php echo formatPercentage($postLeadsConOrigenConocido, $leadsCountFiltered); ?> del total tiene origen confirmado.</div>
                    </article>

                    <article class="kpi-card">
                        <div class="kpi-label">Origen por confirmar</div>
                        <div class="kpi-value" id="kpi-origin-pending"><?php echo number_format($postLeadsOriginPending); ?></div>
                        <div class="kpi-sub" id="kpi-origin-pending-detail"><?php echo formatPercentage($postLeadsOriginPending, $leadsCountFiltered); ?> del total sigue sin origen confirmado.</div>
                    </article>

                    <article class="kpi-card">
                        <div class="kpi-label">Llamada Oficial Realizada</div>
                        <div class="kpi-value" id="kpi-official-call"><?php echo number_format($postLeadsLlamadaRealizada); ?></div>
                        <div class="kpi-sub">Leads que en algún momento pasaron a estatus atendido (calendario_estatus_historial).</div>
                    </article>

                    <article class="kpi-card">
                        <div class="kpi-label">Planner Afianzados</div>
                        <div class="kpi-value" id="kpi-wp-afianzados"><?php echo number_format($postWpAfianzadosCount); ?></div>
                        <div class="kpi-sub" id="kpi-wp-afianzados-detail">Total de eventos de todos los wedding planners afianzados.</div>
                    </article>
                </div>

                <div class="report-breakdown-grid">
                    <article class="report-breakdown-card">
                        <div class="report-chart-header">
                            <div>
                                <h3 class="report-chart-title">Metodo de contacto <span class="report-scope-hint">(mostrando desde estatus atendidos)</span></h3>
                            </div>
                        </div>
                        <div class="report-breakdown-list">
                            <?php if (!empty($postContactMethodCounts)): ?>
                                <?php foreach ($postContactMethodCounts as $label => $count): ?>
                                    <div class="report-breakdown-item">
                                        <div class="report-breakdown-row">
                                            <div class="report-breakdown-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="report-breakdown-meta"><?php echo number_format($count); ?> · <?php echo formatPercentage($count, $leadsCountFiltered); ?></div>
                                        </div>
                                        <div class="report-breakdown-bar"><span class="report-breakdown-fill" style="width: <?php echo htmlspecialchars(formatPercentage($count, $leadsCountFiltered, 2), ENT_QUOTES, 'UTF-8'); ?>;"></span></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="report-breakdown-empty">Sin datos para este periodo.</p>
                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="report-breakdown-card">
                        <div class="report-chart-header">
                            <div>
                                <h3 class="report-chart-title">Origen del cliente <span class="report-scope-hint">(mostrando desde estatus atendidos)</span></h3>
                            </div>
                        </div>
                        <div class="report-breakdown-list">
                            <?php if (!empty($postWhereKnowCounts)): ?>
                                <?php foreach ($postWhereKnowCounts as $label => $count): ?>
                                    <?php if ($count === 0) continue; ?>
                                    <div class="report-breakdown-item">
                                        <div class="report-breakdown-row">
                                            <div class="report-breakdown-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="report-breakdown-meta"><?php echo number_format($count); ?> · <?php echo formatPercentage($count, $leadsCountFiltered); ?></div>
                                        </div>
                                        <div class="report-breakdown-bar"><span class="report-breakdown-fill" style="width: <?php echo htmlspecialchars(formatPercentage($count, $leadsCountFiltered, 2), ENT_QUOTES, 'UTF-8'); ?>;"></span></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="report-breakdown-empty">Sin datos para este periodo.</p>
                            <?php endif; ?>
                        </div>
                        <div class="report-origin-pending" style="margin-top:10px;">
                            <strong id="report-origin-pending-count"><?php echo number_format($postLeadsOriginPending); ?> leads</strong>
                            <span>con origen aun no confirmado dentro del flujo post-cal.</span>
                        </div>
                    </article>

                    <article class="report-breakdown-card">
                        <div class="report-chart-header">
                            <div>
                                <h3 class="report-chart-title">Desde cuando nos conoce <span class="report-scope-hint">(mostrando desde estatus atendidos)</span></h3>
                            </div>
                        </div>
                        <div class="report-breakdown-list">
                            <?php if (!empty($postKnownUsCounts)): ?>
                                <?php foreach ($postKnownUsCounts as $label => $count): ?>
                                    <div class="report-breakdown-item">
                                        <div class="report-breakdown-row">
                                            <div class="report-breakdown-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="report-breakdown-meta"><?php echo number_format($count); ?> · <?php echo formatPercentage($count, $leadsCountFiltered); ?></div>
                                        </div>
                                        <div class="report-breakdown-bar"><span class="report-breakdown-fill" style="width: <?php echo htmlspecialchars(formatPercentage($count, $leadsCountFiltered, 2), ENT_QUOTES, 'UTF-8'); ?>;"></span></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="report-breakdown-empty">Sin datos para este periodo.</p>
                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="report-breakdown-card">
                        <div class="report-chart-header">
                            <div>
                                <h3 class="report-chart-title">Campañas <span class="report-scope-hint">(mostrando desde estatus atendidos)</span></h3>
                            </div>
                        </div>
                        <div class="report-breakdown-list">
                            <?php if (!empty($postCampaignCounts)): ?>
                                <?php foreach ($postCampaignCounts as $label => $count): ?>
                                    <div class="report-breakdown-item">
                                        <div class="report-breakdown-row">
                                            <div class="report-breakdown-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="report-breakdown-meta"><?php echo number_format($count); ?> · <?php echo formatPercentage($count, $leadsCountFiltered); ?></div>
                                        </div>
                                        <div class="report-breakdown-bar"><span class="report-breakdown-fill" style="width: <?php echo htmlspecialchars(formatPercentage($count, $leadsCountFiltered, 2), ENT_QUOTES, 'UTF-8'); ?>;"></span></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="report-breakdown-empty">Sin datos para este periodo.</p>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>

                <!-- <div class="report-insight">
                    <div class="report-insight-kicker">Tasa de cierre post-cal</div>
                    <p class="report-insight-text">→ <span id="report-close-rate"><?php echo number_format($asistenciaRate, 1); ?>%</span> de estas sesiones terminaron en cliente.</p>
                    <p class="report-insight-detail" id="report-close-rate-detail"><?php echo number_format($postLeadsClosedCount); ?> cierres de <?php echo number_format($totalAgendadasFiltered); ?> sesiones agendadas</p>
                </div> -->
            </div>
        </section>

        <!-- ════════════════ TABLE WRAP ════════════════ -->
        <div class="table-wrap">
            <div class="table-header-custom">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="table-title">Post-Qualified Leads</span>
                    <span class="table-count" id="table-count-label"><?php echo number_format($leadsCountFiltered); ?> sesiones · <?php echo number_format($postLeadsClosedCount); ?> cierres</span>
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
                            <th data-column="metodo_contacto"><div class="th-flex-label"><span class="th-compact-label">Método contacto</span><i class="filter-icon bi bi-funnel" data-column="metodo_contacto"></i></div></th>
                            <th data-column="tipo_cliente"><div class="th-flex-label"><span class="th-compact-label">Tipo de cliente</span><i class="filter-icon bi bi-funnel" data-column="tipo_cliente"></i></div></th>
                            <th data-column="origen_cliente"><div class="th-flex-label"><span class="th-compact-label">Origen</span><i class="filter-icon bi bi-funnel" data-column="origen_cliente"></i></div></th>
                            <th data-column="campana"><div class="th-flex-label"><span class="th-compact-label">Campaña</span><i class="filter-icon bi bi-funnel" data-column="campana"></i></div></th>
                            <th data-column="ciudad_origen"><span class="th-compact-label">Ciudad novios</span></th>
                            <th data-column="boda">¿Dónde se casa?</th>
                            <th data-column="cuando_se_casa">¿Cuándo se casa?</th>
                            <th data-column="fecha">¿Cuándo llegó?</th>
                            <th data-column="desde_conoce"><div class="th-flex-label"><span class="th-compact-label">Desde cuándo nos conoce</span><i class="filter-icon bi bi-funnel" data-column="desde_conoce"></i></div></th>
                            <th data-column="engagement"><div class="th-flex-label"><span class="th-compact-label">Engagement</span><i class="filter-icon bi bi-funnel" data-column="engagement"></i></div></th>
                            <th data-column="estatus">Estatus <i class="filter-icon bi bi-funnel" data-column="estatus"></i></th>
                            <th style="display:none">Sesión Oficial</th>
                            <?php if ($tipoUsuario != 4): ?>
                                <th>Paquete</th>
                            <?php endif; ?>
                            <th style="display:none">Compromiso</th>
                            <th style="display:none">Técnica Cierre</th>
                            <?php if ($tipoUsuario != 4): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayLeads as $lead): ?>
                            <?php
                                $originCategoryLabel = getOrigenCategoriaLabel($lead);
                                $originBadgeClass = 'badge badge-neutral';
                                if ($originCategoryLabel === 'Wedding Planner') {
                                    $originBadgeClass = 'badge badge-planner';
                                } elseif ($originCategoryLabel === 'Community') {
                                    $originBadgeClass = 'badge badge-community';
                                } elseif ($originCategoryLabel === 'New Audience') {
                                    $originBadgeClass = 'badge badge-newmarket';
                                }

                                $engRaw = trim((string)($lead['engagement'] ?? ''));
                                $engNorm = mb_strtolower($engRaw, 'UTF-8');
                                $engClass = 'eng eng-low';
                                if ($engNorm === '3' || $engNorm === 'alto') {
                                    $engClass = 'eng eng-high';
                                } elseif ($engNorm === '2' || $engNorm === 'medio') {
                                    $engClass = 'eng eng-mid';
                                }

                                $statusRaw = isset($lead['estatus']) ? trim((string)$lead['estatus']) : '';
                                $statusLower = $statusRaw !== '' ? mb_strtolower($statusRaw, 'UTF-8') : 'agendado';
                                $statusDisplay = ucfirst($statusLower);
                                $statusClass = 'status status-scheduled';
                                if ($statusLower === 'atendido') {
                                    $statusClass = 'status status-attended';
                                } elseif ($statusLower === 'cliente') {
                                    $statusClass = 'status status-closed';
                                } elseif ($statusLower === 'fantasma') {
                                    $statusClass = 'status status-pending';
                                } elseif ($statusLower === 'muerto') {
                                    $statusClass = 'status status-danger';
                                }

                                $isOriginKnown = in_array(trim((string)($lead['how_did_you_meet'] ?? '')), ['1', '2', '3'], true) ? 1 : 0;
                                // Todos los displayLeads ya pasaron filtro de calendario_estatus_historial (estatus 1)
                                $isOfficialCall = 1;
                                $isClosed = ($statusLower === 'cliente' || intval($lead['cliente'] ?? 0) === 1) ? 1 : 0;
                                $isWpAfianzado = (
                                    strcasecmp((string)($lead['tabla_origen'] ?? ''), 'eventos_wp') === 0 &&
                                    intval($postEventWpAfianzadosMap[intval($lead['original_lead_id'] ?? 0)] ?? 0) === 1
                                ) ? 1 : 0;
                                $isEngagementHigh = ($engNorm === '3' || $engNorm === 'alto') ? 1 : 0;
                                $resolvedContactChannel = resolveFirstContactChannelForLead($lead);
                                $contactMethodLabel = normalizeFirstContactChannelLabel($resolvedContactChannel);
                                $contactChannelBadgeLabel = getContactChannelBadgeLabel($contactMethodLabel);
                                $campaignDisplayLabel = formatCampaignDisplayLabel($lead['campaign_name'] ?? '');
                                $knownUsRaw = trim((string)($lead['how_long_known_us'] ?? ''));
                                $knuMap = [
                                    'less than 3 months' => 'Menos de 3 meses',
                                    'between 3 months and 1 year' => 'Entre 3 meses y 1 año',
                                    'more than 1 year' => 'Más de 1 año',
                                    'not asked' => 'No se preguntó',
                                ];
                                $knownUsDisplay = ($knownUsRaw !== '' && $knownUsRaw !== '—') ? ($knuMap[mb_strtolower($knownUsRaw, 'UTF-8')] ?? $knownUsRaw) : 'Sin dato';
                                $knownUsBadgeLabel = getKnownUsBadgeLabel($knownUsDisplay);
                                $tipoClienteLabel = getTipoClienteLabel($lead, [
                                    'tipo_cliente' => $lead['tipo_cliente'] ?? null,
                                    'how_did_you_meet' => $lead['how_did_you_meet_raw'] ?? ($lead['how_did_you_meet'] ?? ''),
                                ]);
                            ?>
                            <tr id="lead-row-<?php echo $lead['id']; ?>-<?php echo htmlspecialchars($lead['tabla_origen']); ?>"
                                data-origin-known="<?php echo $isOriginKnown; ?>"
                                data-llamada-oficial="<?php echo $isOfficialCall; ?>"
                                data-is-closed="<?php echo $isClosed; ?>"
                                data-wp-afianzado="<?php echo $isWpAfianzado; ?>"
                                data-engagement-high="<?php echo $isEngagementHigh; ?>"
                                data-status="<?php echo htmlspecialchars($statusLower, ENT_QUOTES, 'UTF-8'); ?>">

                                <!-- Nombre + ID -->
                                <td data-column="nombre">
                                    <div class="td-name"><?php echo htmlspecialchars($lead['full_name'] ?? ''); ?></div>
                                    <?php if (!empty($lead['email'])): ?>
                                        <div class="td-sub"><?php echo htmlspecialchars($lead['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                </td>

                                <!-- Método de contacto -->
                                <td data-column="metodo_contacto"><span class="ch-badge"><?php echo htmlspecialchars($contactChannelBadgeLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>

                                <!-- Tipo de cliente -->
                                <td data-column="tipo_cliente"><span class="td-inline-muted"><?php echo htmlspecialchars($tipoClienteLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>

                                <!-- Origen -->
                                <td data-column="origen_cliente">
                                    <span class="<?php echo htmlspecialchars($originBadgeClass, ENT_QUOTES, 'UTF-8'); ?>"><span class="badge-dot"></span><?php echo htmlspecialchars($originCategoryLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>

                                <!-- Campaña -->
                                <td data-column="campana"><?php echo renderCampaignBadge($campaignDisplayLabel); ?></td>

                                <!-- Ciudad novios -->
                                <td data-column="ciudad_origen"><?php
                                    $city = trim((string)($lead['city'] ?? ''));
                                    echo htmlspecialchars($city !== '' ? $city : '—', ENT_QUOTES, 'UTF-8');
                                ?></td>

                                <!-- ¿Dónde se casa? -->
                                <td data-column="boda"><?php
                                    $wl = trim((string)($lead['wedding_location'] ?? ''));
                                    echo htmlspecialchars($wl !== '' && $wl !== 'N/A' ? $wl : '—', ENT_QUOTES, 'UTF-8');
                                ?></td>

                                <!-- ¿Cuándo se casa? -->
                                <td data-column="cuando_se_casa"><?php
                                    $wd = trim((string)($lead['wedding_date'] ?? $lead['when_are_you_getting_married_'] ?? ''));
                                    echo htmlspecialchars(formatLeadDate($wd), ENT_QUOTES, 'UTF-8');
                                ?></td>

                                <!-- ¿Cuándo llegó? -->
                                <?php
                                $llegadaTs = 0;
                                $llegadaRaw = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
                                if (!empty($llegadaRaw)) $llegadaTs = strtotime($llegadaRaw) ?: 0;
                                ?>
                                <td data-column="fecha" data-order="<?php echo intval($llegadaTs); ?>">
                                    <?php echo htmlspecialchars(formatArrivalDateTime($llegadaRaw), ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <!-- Desde cuándo nos conoce -->
                                <td data-column="desde_conoce"><span class="known"><?php echo htmlspecialchars($knownUsBadgeLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>

                                <!-- Engagement (pill) -->
                                <td data-column="engagement">
                                    <?php
                                    $engLabel = getEngagementLabelWithEmoji($lead);
                                    if ($engLabel !== 'N/A' && $engLabel !== 'Sin dato') {
                                        echo '<span class="' . $engClass . '">' . htmlspecialchars($engLabel, ENT_QUOTES, 'UTF-8') . '</span>';
                                    } else {
                                        echo '<span style="color:var(--muted);">' . htmlspecialchars($engLabel) . '</span>';
                                    }
                                    ?>
                                </td>

                                <!-- Estatus (pill) -->
                                <td data-column="estatus">
                                    <span class="<?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
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

                                <?php if ($tipoUsuario != 4): ?>
                                    <!-- Paquete de interés -->
                                    <td><?php
                                        $paqId   = intval($lead['paquete'] ?? 0);
                                        $paqName = '';
                                        if ($paqId > 0 && isset($paquetesMap[$paqId])) {
                                            $paqName = $paquetesMap[$paqId];
                                        } elseif (!empty($lead['paquete_cotizado'])) {
                                            $paqName = $lead['paquete_cotizado']; // valor legacy
                                        }
                                        echo $paqName !== '' ? '<span class="ch-badge">' . htmlspecialchars($paqName, ENT_QUOTES, 'UTF-8') . '</span>' : '<span style="color:var(--muted)">—</span>';
                                    ?></td>
                                <?php endif; ?>

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

                                <?php if ($tipoUsuario != 4): ?>
                                    <td>
                                        <div class="post-actions-cell">
                                            <?php
                                            $traceParams = ['cf_id' => (int) ($lead['id'] ?? 0)];
                                            $traceTabla = trim((string) ($lead['tabla_origen'] ?? ''));
                                            $traceOrigId = (int) ($lead['original_lead_id'] ?? 0);
                                            if ($traceTabla !== '' && $traceOrigId > 0) {
                                                $traceParams['tabla'] = $traceTabla;
                                                $traceParams['orig_id'] = $traceOrigId;
                                            }
                                            $traceUrl = 'consulta_post_leads_trazabilidad.php?' . http_build_query($traceParams);
                                            ?>
                                            <a href="<?php echo htmlspecialchars($traceUrl, ENT_QUOTES, 'UTF-8'); ?>" class="efege-btn" style="text-decoration:none;">
                                                <i class="fas fa-route"></i> Trazabilidad
                                            </a>
                                            <button class="efege-btn" type="button" onclick="verComentarios(<?php echo intval($lead['id']); ?>)">
                                                <i class="fas fa-comment-dots"></i> Comentarios
                                            </button>
                                        </div>
                                    </td>
                                <?php endif; ?>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div><!-- /.table-wrap -->

</div><!-- /.efege-page -->

<!-- ── Modal: ver más detalles ── -->
<div class="modal fade" id="verMasModal" tabindex="-1" aria-labelledby="verMasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verMasModalLabel">Detalles del Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: comentarios ── -->
<div class="modal fade" id="comentariosModal" tabindex="-1" aria-labelledby="comentariosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="comentariosModalLabel">Comentarios del vendedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="comentariosModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script>
    const vendedores = <?php echo json_encode($vendedores); ?>;

    $(document).ready(function () {
        var dtHiddenCols = <?php echo json_encode($dtHiddenColumnTargets); ?>;
        var table = $('#leadsTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            ordering:  false,
            paging:    false,
            info: true,
            autoWidth: false,
            columnDefs: [
                { targets: dtHiddenCols, visible: false, searchable: false }
            ],
            dom: '<""r>t<"d-flex justify-content-between align-items-center mt-2"il>',
            buttons: [{ extend: 'excelHtml5', title: 'Leads Post Qualified', exportOptions: { columns: ':visible:not(:last-child)' } }],
            drawCallback: function () { applyColumnFilters(); },
            initComplete: function () { updateDynamicCards(); }
        });

        $('#exportExcelBtn').on('click', function () {
            table.button(0).trigger();
        });

        $('#tableSearchInput').on('input', function () {
            table.search(this.value).draw();
        });

        updateDynamicCards();

        // ── REPORTES CHARTS ────────────────────────────────────────────
        var postChartDates  = <?php echo isset($datesJson)  ? $datesJson  : '[]'; ?>;
        var postChartCounts = <?php echo isset($countsJson) ? $countsJson : '[]'; ?>;
        var postMaxLeads = 0;
        postChartCounts.forEach(function(c) { if (c > postMaxLeads) postMaxLeads = c; });
        var postYAxisMax = Math.ceil(postMaxLeads * 1.15);
        if (postYAxisMax < 5) postYAxisMax = 5;
        var postTickInterval = Math.ceil(postYAxisMax / 5);
        if (postTickInterval < 1) postTickInterval = 1;

        Highcharts.chart('postLeadsChart', {
            chart: { type: 'line', backgroundColor: '#f8f9fa', borderRadius: 12,
                spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10 },
            title: { text: null },
            xAxis: {
                categories: postChartDates, crosshair: true,
                labels: { rotation: -45, style: { fontSize: '11px' } },
                lineColor: '#d9e2ec'
            },
            yAxis: {
                min: 0, max: postYAxisMax, tickInterval: postTickInterval,
                title: { text: 'Sesiones' }, allowDecimals: false, gridLineColor: '#e2e8f0'
            },
            legend: { enabled: false },
            plotOptions: {
                line: {
                    color: '#C5A028',
                    lineWidth: 2.5,
                    marker: {
                        enabled: true,
                        radius: 4,
                        fillColor: '#C5A028',
                        lineWidth: 2,
                        lineColor: '#fff'
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function() { return this.y > 0 ? this.y : ''; },
                        style: { fontSize: '10px', fontWeight: '600', textOutline: 'none' }
                    }
                }
            },
            series: [{ name: 'Sesiones', data: postChartCounts }],
            tooltip: {
                backgroundColor: 'rgba(15,23,42,0.92)', style: { color: '#fff' }, borderWidth: 0,
                formatter: function() { return '<b>' + this.x + '</b><br/>Sesiones agendadas: <b>' + this.y + '</b>'; }
            },
            credits: { enabled: false }
        });

    });

    // ── Utility: escape HTML ─────────────────────────────
    function escapeHtml(unsafe) {
        if (!unsafe && unsafe !== 0) return '';
        return String(unsafe)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;').replace(/'/g, '&#039;');
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
            try {
                var parsed = JSON.parse(xhr.responseText);
                html += '<hr><div><strong>Body:</strong><pre style="white-space:pre-wrap;">' + escapeHtml(JSON.stringify(parsed, null, 2)) + '</pre></div>';
            } catch (e) {
                html += '<hr><div><strong>Body (raw):</strong><pre style="white-space:pre-wrap;">' + escapeHtml(xhr.responseText) + '</pre></div>';
            }
        } else if (xhr && xhr.status >= 500) {
            html += '<hr><div><strong>Nota:</strong> Revisa el log de errores del servidor.</div>';
        }
        html += '</div>';
        Swal.fire({ title: 'Error', html: html, icon: 'error', width: '70%' });
    }

    function applyOptionClass($el) {
        if (!$el || !$el.length) return;
        $el.removeClass('option1 option2 option3 option4 option5 option6 option7 option8');
        var cls = $el.find('option:selected').attr('class');
        if (cls) $el.addClass(cls);
    }

    // ── Ver comentarios ──────────────────────────────────
    function verComentarios(id) {
        Swal.fire({ title: 'Cargando comentarios...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        $.ajax({
            url: 'actualizar_lead.php',
            method: 'POST',
            dataType: 'json',
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
                            const when   = escapeHtml(c.created_at || '');
                            const author = escapeHtml(c.author || 'Vendedor');
                            const text   = escapeHtml(c.text || '');
                            html += '<div class="list-group-item">';
                            html += '<div class="d-flex justify-content-between w-100">';
                            html += '<h6 class="mb-1">' + author + '</h6>';
                            html += '<small>' + when + '</small></div>';
                            html += '<p class="mb-1">' + text + '</p></div>';
                        });
                        html += '</div>';
                    }
                    html += '</div>';
                    html += '<div class="mb-3"><label class="form-label">Agregar comentario</label>';
                    html += '<textarea id="newCommentText" class="form-control" rows="4" placeholder="Escribe un comentario..."></textarea></div>';
                    html += '<div class="text-end"><button id="saveCommentBtn" class="btn btn-primary">Guardar comentario</button></div>';

                    document.getElementById('comentariosModalBody').innerHTML = html;
                    var modal = new bootstrap.Modal(document.getElementById('comentariosModal'));
                    modal.show();

                    $('#saveCommentBtn').on('click', function () {
                        var comment = $('#newCommentText').val();
                        if (!comment || !comment.trim()) {
                            Swal.fire('Error', 'El comentario no puede estar vacío', 'error'); return;
                        }
                        var $btn = $(this);
                        $btn.prop('disabled', true);
                        Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                        $.ajax({
                            url: 'actualizar_lead.php',
                            method: 'POST',
                            dataType: 'json',
                            data: { action: 'add_comment', id: id, comment: comment, author: '' },
                            success: function (resp2) {
                                Swal.close();
                                if (resp2 && resp2.success) {
                                    Swal.fire('Guardado', 'Comentario agregado correctamente', 'success');
                                    const commentsNew = resp2.comments || [];
                                    let listHtml = '';
                                    if (!commentsNew.length) {
                                        listHtml = '<p>No hay comentarios aún.</p>';
                                    } else {
                                        listHtml = '<div class="list-group mb-3">';
                                        commentsNew.forEach(function (c) {
                                            const when   = escapeHtml(c.created_at || '');
                                            const author = escapeHtml(c.author || 'Vendedor');
                                            const text   = escapeHtml(c.text || '');
                                            listHtml += '<div class="list-group-item"><div class="d-flex justify-content-between w-100">';
                                            listHtml += '<h6 class="mb-1">' + author + '</h6><small>' + when + '</small></div>';
                                            listHtml += '<p class="mb-1">' + text + '</p></div>';
                                        });
                                        listHtml += '</div>';
                                    }
                                    $('#commentsList').html(listHtml);
                                    $('#newCommentText').val('');
                                } else {
                                    Swal.fire('Error', resp2.message || 'No se pudo guardar el comentario', 'error');
                                }
                            },
                            error: function () {
                                Swal.close();
                                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                            },
                            complete: function () { $btn.prop('disabled', false); }
                        });
                    });
                } else {
                    Swal.fire('Error', resp.message || 'No se pudieron obtener los comentarios', 'error');
                }
            },
            error: function () { Swal.fire('Error', 'Error de comunicación con el servidor', 'error'); }
        });
    }

    // ── Ver más (detalle completo del lead) ──────────────
    function verMas(lead) {
        let content = '<div class="row">';
        const fields = [
            { key: 'id',            label: 'ID' },
            { key: 'full_name',     label: 'Nombre Completo' },
            { key: 'email',         label: 'Email' },
            { key: 'phone',         label: 'Teléfono' },
            { key: 'created_time',  label: 'Fecha de Creación' },
            { key: 'campaign_name', label: 'Campaña' },
            { key: 'where_are_you_getting_married_', label: 'Ubicación de la Boda' },
            { key: 'form_name',     label: 'Formulario de Origen' },
            { key: 'has_appointment', label: 'Tiene Cita', format: value => value == 1 ? 'Sí' : 'No' }
        ];
        fields.forEach(field => {
            let value = lead[field.key] || 'N/A';
            if (field.format) value = field.format(value);
            if (field.key === 'where_are_you_getting_married_') {
                value = lead['where_are_you_getting_married_'] || lead['where_is_your_marriage_taking_place_'] || 'N/A';
            }
            content += `<div class="mb-3 col-md-6"><label class="form-label fw-bold">${field.label}:</label><p class="mb-0">${value}</p></div>`;
        });
        content += '</div>';
        document.getElementById('modalBody').innerHTML = content;
        var modal = new bootstrap.Modal(document.getElementById('verMasModal'));
        modal.show();
    }

    // ── Inline update (selects) ──────────────────────────
    $(document).ready(function () {
        $('.lead-update').each(function () { applyOptionClass($(this)); });

        $(document).on('change', '.lead-update', function () {
            var el    = $(this);
            var id    = el.data('id');
            var field = el.data('field');
            var value = el.val();
            el.prop('disabled', true);

            $.ajax({
                url: 'actualizar_lead.php',
                method: 'POST',
                dataType: 'json',
                data: { id: id, field: field, value: value },
                success: function (resp) {
                    if (resp && resp.success) {
                        el.closest('td').css('background', '#e6ffed');
                        setTimeout(function () { el.closest('td').css('background', ''); }, 800);
                        applyOptionClass(el);
                    } else {
                        Swal.fire('Error', resp.message || 'No se pudo actualizar', 'error');
                    }
                },
                error: function () { Swal.fire('Error', 'Error de comunicación con el servidor', 'error'); },
                complete: function () { el.prop('disabled', false); }
            });
        });

        var commentTimer = {};
        $(document).on('input', '.lead-update-text', function () {
            var el    = $(this);
            var id    = el.data('id');
            var field = el.data('field');
            var value = el.val();
            clearTimeout(commentTimer[id + field]);
            commentTimer[id + field] = setTimeout(function () {
                el.prop('disabled', true);
                $.ajax({
                    url: 'actualizar_lead.php',
                    method: 'POST',
                    dataType: 'json',
                    data: { id: id, field: field, value: value },
                    success: function (resp) {
                        if (resp && resp.success) {
                            el.closest('td').css('background', '#e6ffed');
                            setTimeout(function () { el.closest('td').css('background', ''); }, 800);
                        } else {
                            Swal.fire('Error', resp.message || 'No se pudo actualizar', 'error');
                        }
                    },
                    error: function () { Swal.fire('Error', 'Error de comunicación con el servidor', 'error'); },
                    complete: function () { el.prop('disabled', false); }
                });
            }, 700);
        });
    });

    // ════════════════════════════════════════════════════
    // SISTEMA DE FILTROS POR COLUMNA
    // ════════════════════════════════════════════════════
    var columnFilters = {};
    var currentFilterDropdown = null;

    function getUniqueColumnValues(columnName) {
        var values = new Set();
        getLeadsTableRows().each(function () {
            var cell = $(this).find('td[data-column="' + columnName + '"]');
            if (cell.length > 0) {
                var text = cell.text().trim();
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

        var body          = $('<div class="filter-dropdown-body"></div>');
        var searchInput   = $('<input type="text" class="filter-search" placeholder="Buscar...">');
        body.append(searchInput);

        var optionsContainer = $('<div class="filter-options"></div>');
        var selectAllDiv     = $('<div class="filter-option mb-2"></div>');
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

        var iconRect       = iconElement.getBoundingClientRect();
        var dropdownWidth  = dropdown.outerWidth();
        var dropdownHeight = dropdown.outerHeight();
        var windowWidth    = $(window).width();
        var windowHeight   = $(window).height();
        var left = iconRect.left;
        var top  = iconRect.bottom + 5;
        if (left + dropdownWidth  > windowWidth)  left = windowWidth  - dropdownWidth  - 10;
        if (top  + dropdownHeight > windowHeight) top  = iconRect.top - dropdownHeight - 5;
        dropdown.css({ left: left + 'px', top: top + 'px' });
        currentFilterDropdown = dropdown;
        $(iconElement).addClass('active');

        searchInput.on('input', function () {
            var searchTerm = $(this).val().toLowerCase();
            optionsContainer.find('.filter-option:not(:first)').each(function () {
                var text = $(this).find('label').text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
        });

        selectAllCheckbox.on('change', function () {
            var isChecked = $(this).prop('checked');
            optionsContainer.find('.filter-option:not(:first) input[type="checkbox"]:visible').prop('checked', isChecked);
        });

        optionsContainer.on('change', '.filter-option:not(:first) input[type="checkbox"]', function () {
            var visibleBoxes  = optionsContainer.find('.filter-option:not(:first):visible input[type="checkbox"]');
            var checkedBoxes  = visibleBoxes.filter(':checked');
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

    function getLeadsTableRows() {
        if ($.fn.dataTable.isDataTable('#leadsTable')) {
            return $($('#leadsTable').DataTable().rows({ search: 'applied' }).nodes());
        }
        return $('#leadsTable tbody tr');
    }

    function applyColumnFilters() {
        $('.filter-icon').removeClass('active');
        Object.keys(columnFilters).forEach(function (col) {
            $('.filter-icon[data-column="' + col + '"]').addClass('active');
        });
        getLeadsTableRows().each(function () {
            var row     = $(this);
            var showRow = true;
            for (var columnName in columnFilters) {
                var cell  = row.find('td[data-column="' + columnName + '"]');
                if (cell.length > 0) {
                    var cellValue    = cell.text().trim();
                    var allowedValues = columnFilters[columnName];
                    if (!allowedValues.includes(cellValue)) { showRow = false; break; }
                }
            }
            showRow ? row.show() : row.hide();
        });
        updateDynamicCards();
    }

    // ── Dynamic cards update ─────────────────────────────
    function updateDynamicCards() {
        var totalVisible = 0;
        var originKnownVisible = 0;
        var officialVisible = 0;
        var closedVisible = 0;
        var wpAfianzadosVisible = <?php echo intval($postWpAfianzadosCount); ?>;
        var engagementHighVisible = 0;

        getLeadsTableRows().each(function () {
            if (!$(this).is(':visible')) {
                return;
            }
            totalVisible++;
            originKnownVisible += Number($(this).attr('data-origin-known') || 0);
            officialVisible += Number($(this).attr('data-llamada-oficial') || 0);
            closedVisible += Number($(this).attr('data-is-closed') || 0);
            engagementHighVisible += Number($(this).attr('data-engagement-high') || 0);
        });

        var closeRate = totalVisible > 0 ? ((closedVisible / totalVisible) * 100).toFixed(1) : '0.0';
        var originPendingVisible = Math.max(totalVisible - originKnownVisible, 0);
        var originPendingRate = totalVisible > 0 ? ((originPendingVisible / totalVisible) * 100).toFixed(1) : '0.0';
        $('#kpi-post-total').text(totalVisible);
        $('#kpi-origin-known').text(originKnownVisible);
        $('#kpi-origin-pending').text(originPendingVisible);
        $('#kpi-origin-pending-detail').text(originPendingRate + '% del total sigue sin origen confirmado.');
        $('#kpi-official-call').text(officialVisible);
        $('#kpi-wp-afianzados').text(wpAfianzadosVisible.toLocaleString());
        $('#kpi-wp-afianzados-detail').text('Total de eventos de todos los wedding planners afianzados.');

        $('#report-close-rate').text(closeRate + '%');
        $('#report-close-rate-detail').text(closedVisible + ' cierres de ' + totalVisible + ' sesiones agendadas');
        $('#report-origin-pending-count').text(originPendingVisible + ' leads');
        $('#table-count-label').text(totalVisible + ' sesiones · ' + closedVisible + ' cierres');
    }

    // ── Column filter event wiring ───────────────────────
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

    // ════════════════════════════════════════════════════
    // FIN SISTEMA DE FILTROS POR COLUMNA
    // ════════════════════════════════════════════════════
</script>
<?php
// ── DIAGNÓSTICO: leads con origen "Sin dato" ──────────────────────────────────
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
        console.log('[consulta_post_leads] Origen del cliente: todos los registros tienen origen definido ✅');
        return;
    }
    console.group('[consulta_post_leads] ' + sinDatoLeads.length + ' registro(s) con origen "Sin dato":');
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