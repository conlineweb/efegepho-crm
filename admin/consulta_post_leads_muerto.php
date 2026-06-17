<?php


// Mover session_start() aquí y verificar si ya está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tipoUsuario = $_SESSION['tipo_usuario'] ?? 0; // Ajusta según tu sistema de autenticación

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

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



            // Garantizar campaign_name - usar 'N/A' si no existe o está vacío (incluye cadenas vacías o sólo espacios)
            $merged['campaign_name'] = (isset($cf['campaign_name']) && trim($cf['campaign_name']) !== '') ? $cf['campaign_name'] : '';

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
            '3' => 'New Market'
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
        $howMap = [
            '1' => 'Wedding Planner',
            '2' => 'Community',
            '3' => 'New Market'
        ];
        if ($howRaw !== '' && isset($howMap[$howRaw])) {
            return $howMap[$howRaw];
        }
        return 'N/A';
    }
}

if (!function_exists('getComoLlegaronLabel')) {
    function getComoLlegaronLabel($lead) {
        // Solo mostrar si el origen es New Market (valor 3)
        $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
        if ($howRaw !== '3') {
            return '—';
        }
        $hearRaw = trim((string)($lead['hear_about_us'] ?? ''));
        $hearMap = [
            '1' => 'Meta Ads — anuncio en Instagram / Facebook',
            '2' => 'SEO — buscaron en Google',
            '3' => 'Colaboración / Influencer / Famoso',
            '4' => 'Publicación / Prensa / Revista',
            '5' => 'Otro'
        ];
        if ($hearRaw !== '') {
            return $hearMap[$hearRaw] ?? $hearRaw;
        }
        return '—';
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

if (!function_exists('formatLeadDate')) {
    function formatLeadDate($dateString) {
        $value = trim((string) $dateString);
        if ($value === '') return '—';
        $timestamp = strtotime($value);
        if ($timestamp === false) return $value;
        return date('d/m/Y', $timestamp);
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
        $ctUsed = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
        error_log("[POSTQ-AGENDADOS-DEBUG] CONTADO cfId=" . ($lead['id'] ?? '?') . " tabla=" . ($lead['tabla_origen'] ?? '') . " origId=" . ($lead['original_lead_id'] ?? '') . " dateUsed=$ctUsed estatus=$st");
    }
}
error_log("[POSTQ-AGENDADOS-DEBUG] TOTAL totalAgendadasFiltered=$totalAgendadasFiltered startDate=$startDate endDate=$endDate");
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
    $raw = trim((string)($lead['first_contact_channel'] ?? ''));
    if ($raw === '' && ($lead['estatus'] ?? '') === 'muerto') {
        $label = 'Muerto';
    } else {
        $label = normalizeFirstContactChannelLabel($raw);
    }
    $postContactMethodCounts[$label] = ($postContactMethodCounts[$label] ?? 0) + 1;
}
arsort($postContactMethodCounts);
$postContactMethodPieData = [];
foreach ($postContactMethodCounts as $label => $count) {
    if ($count <= 0) continue;
    $entry = ['name' => $label, 'y' => $count];
    if ($label === 'Muerto') $entry['color'] = '#ef4444';
    $postContactMethodPieData[] = $entry;
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
    if (($raw === '' || $raw === '—') && ($lead['estatus'] ?? '') === 'muerto') {
        $label = 'Muerto';
    } elseif ($raw === '' || $raw === '—') {
        $label = 'Sin dato';
    } else {
        $label = $postKnownUsLabelMap[mb_strtolower($raw, 'UTF-8')] ?? $raw;
    }
    $postKnownUsCounts[$label] = ($postKnownUsCounts[$label] ?? 0) + 1;
}
arsort($postKnownUsCounts);
$postKnownUsPieData = [];
foreach ($postKnownUsCounts as $label => $count) {
    if ($count <= 0) continue;
    $entry = ['name' => $label, 'y' => $count];
    if ($label === 'Muerto') $entry['color'] = '#ef4444';
    $postKnownUsPieData[] = $entry;
}
$postKnownUsPieJson = json_encode($postKnownUsPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Gráfica 4: De dónde nos conocen (how_did_you_meet)
$postWhereKnowCounts = ['Wedding Planner' => 0, 'Community' => 0, 'New Market' => 0, 'Muerto' => 0, 'Sin dato' => 0];
foreach ($displayLeads as $lead) {
    $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
    if ($howRaw === '1') $postWhereKnowCounts['Wedding Planner']++;
    elseif ($howRaw === '2') $postWhereKnowCounts['Community']++;
    elseif ($howRaw === '3') $postWhereKnowCounts['New Market']++;
    elseif ($howRaw === '' && ($lead['estatus'] ?? '') === 'muerto') $postWhereKnowCounts['Muerto']++;
    else $postWhereKnowCounts['Sin dato']++;
}
$postWhereKnowColorMap = [
    'Wedding Planner' => '#3B82F6',
    'Community'       => '#10B981',
    'New Market'      => '#A855F7',
    'Muerto'          => '#ef4444',
    'Sin dato'        => '#94a3b8',
];
$postWhereKnowPieData = [];
foreach ($postWhereKnowCounts as $wlabel => $wcount) {
    if ($wcount <= 0) continue;
    $postWhereKnowPieData[] = ['name' => $wlabel, 'y' => $wcount, 'color' => ($postWhereKnowColorMap[$wlabel] ?? null)];
}
$postWhereKnowPieJson = json_encode($postWhereKnowPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
body { background: var(--dark, #F8FAFC) !important; }
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

        .efege-postq .kpi-card:hover {
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }

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

        .efege-postq .kpi-sub {
            font-size: 11px;
            color: var(--muted);
        }

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

        .efege-postq .filter-btn-primary {
            background: var(--ink);
            border-color: var(--ink);
            color: #fff;
        }
        .efege-postq .filter-btn-primary:hover { background: #334155; border-color: #334155; color: #fff; }

        .efege-postq .filter-btn-reset {
            background: transparent;
            border-color: var(--border);
            color: var(--muted);
        }
        .efege-postq .filter-btn-reset:hover { border-color: var(--gold); color: var(--gold); }

        /* ── CHART CARD ── */
        .efege-postq .chart-card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .efege-postq .chart-card-title {
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 16px;
        }

        .efege-postq .chart-container { height: 20rem; width: 100%; }

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
        .efege-postq .reports-section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--ink);
            margin: 0 0 4px;
        }
        .efege-postq .reports-section-subtitle {
            font-size: 12px;
            color: var(--muted);
            margin: 0;
        }
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
        .efege-postq .report-chart-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
            margin: 0 0 4px;
        }
        .efege-postq .report-chart-subtitle { font-size: 12px; color: var(--muted); margin: 0; }
        .efege-postq .report-chart { width: 100%; min-height: 240px; }
        @media (max-width: 900px) {
            .efege-postq .reports-layout,
            .efege-postq .reports-kpi-grid-secondary { grid-template-columns: 1fr; }
            .efege-postq .reports-charts-row { flex-direction: column; }
        }

        /* ── TABLE WRAP ── */
        .efege-postq .table-wrap {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .efege-postq .tw-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .efege-postq .tw-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
        }

        .efege-postq .tw-count {
            font-size: 11px;
            color: var(--muted);
        }

        .efege-postq .tw-scroll { overflow: visible; }
        /* DataTables scrollX: solo el cuerpo de la tabla hace scroll horizontal */
        .efege-postq .dataTables_scrollBody {
            scrollbar-width: thin;
            scrollbar-color: var(--border, #e2e8f0) transparent;
        }
        .efege-postq .dataTables_scrollBody::-webkit-scrollbar { height: 6px; }
        .efege-postq .dataTables_scrollBody::-webkit-scrollbar-track { background: transparent; }
        .efege-postq .dataTables_scrollBody::-webkit-scrollbar-thumb { background: var(--border, #e2e8f0); border-radius: 3px; }

        /* ── TABLE STYLES ── */
        .efege-postq table.dt-table {
            width: 100% !important;
            border-collapse: collapse;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
        }

        .efege-postq table.dt-table thead th {
            text-align: left;
            padding: 10px 16px;
            font-size: 10px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            white-space: nowrap;
            font-weight: 600;
        }

        .efege-postq table.dt-table tbody tr {
            border-bottom: 1px solid rgba(0,0,0,0.04);
            transition: background 0.15s;
        }

        .efege-postq table.dt-table tbody tr:last-child { border-bottom: none; }
        .efege-postq table.dt-table tbody tr:hover { background: var(--surface); }

        .efege-postq table.dt-table td {
            padding: 12px 16px;
            vertical-align: middle;
            white-space: nowrap;
            color: var(--ink);
        }

        .efege-postq .td-name { font-weight: 600; color: var(--ink); font-size: 13px; }
        .efege-postq .td-sub  { font-size: 11px; color: var(--muted); margin-top: 2px; }

        /* ── STATUS PILLS ── */
        .efege-postq .status-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .efege-postq .status-agendado  { background: rgba(59,130,246,0.10);  color: #1d4ed8; }
        .efege-postq .status-atendido  { background: rgba(16,185,129,0.10);  color: #047857; }
        .efege-postq .status-fantasma  { background: rgba(239,68,68,0.10);   color: #b91c1c; }
        .efege-postq .status-muerto    { background: rgba(100,116,139,0.10); color: #475569; }
        .efege-postq .status-default   { background: var(--gold-dim);        color: #92700c; }

        /* ── ENGAGEMENT PILLS ── */
        .efege-postq .eng-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .efege-postq .eng-low  { background: rgba(245,158,11,0.12); color: #B45309; }
        .efege-postq .eng-mid  { background: rgba(59,130,246,0.12); color: #1d4ed8; }
        .efege-postq .eng-high { background: rgba(16,185,129,0.12); color: #047857; }

        /* ── ACTION BUTTON ── */
        .efege-postq .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 13px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--ink);
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'DM Sans', sans-serif;
            white-space: nowrap;
        }

        .efege-postq .action-btn:hover {
            background: var(--gold-dim);
            border-color: var(--gold-border);
            color: #92700c;
        }

        /* ── INLINE SELECT IN TABLE ── */
        .efege-postq table.dt-table .lead-update,
        .efege-postq table.dt-table .form-select {
            font-size: 11px;
            padding: 4px 24px 4px 8px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--ink);
            min-width: 130px;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            cursor: pointer;
            transition: border-color 0.2s;
            font-family: 'DM Sans', sans-serif;
        }

        .efege-postq table.dt-table .lead-update:focus,
        .efege-postq table.dt-table .form-select:focus { border-color: var(--gold); outline: none; }

        /* ── DATATABLES OVERRIDES ── */
        .efege-postq .dataTables_wrapper .dataTables_filter label {
            font-size: 12px;
            color: var(--muted);
        }

        .efege-postq .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--border);
            border-radius: 7px;
            padding: 6px 12px;
            font-size: 12px;
            background: var(--surface);
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            outline: none;
        }

        .efege-postq .dataTables_wrapper .dataTables_filter input:focus { border-color: var(--gold); }

        .efege-postq .dataTables_wrapper .dt-buttons button,
        .efege-postq .dataTables_wrapper .dt-buttons a {
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            padding: 7px 14px;
            background: #059669;
            color: #fff;
            border: none;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: background 0.2s;
        }

        .efege-postq .dataTables_wrapper .dt-buttons button:hover { background: #047857; }

        .efege-postq .dataTables_wrapper .dataTables_info {
            font-size: 11px;
            color: var(--muted);
        }

        /* ── COLUMN FILTER SYSTEM ── */
        .efege-postq .filter-icon {
            cursor: pointer;
            margin-left: 5px;
            font-size: 0.75rem;
            color: var(--muted);
            transition: color 0.2s;
        }
        .efege-postq .filter-icon:hover { color: var(--ink); }
        .efege-postq .filter-icon.active { color: var(--planner); }

        .efege-postq .th-flex-label {
            display: flex;
            align-items: flex-start;
            gap: 3px;
        }
        .efege-postq .th-flex-label .th-compact-label {
            flex: 1;
        }
        .efege-postq .th-flex-label .filter-icon {
            flex-shrink: 0;
            margin-top: 1px;
        }
        .efege-postq .th-compact-label {
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

        .filter-dropdown-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            font-weight: 600;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .filter-dropdown-body {
            padding: 12px 16px;
            max-height: 300px;
            overflow-y: auto;
        }

        .filter-search {
            width: 100%;
            padding: 6px 12px;
            margin-bottom: 10px;
            border: 1px solid var(--border);
            border-radius: 7px;
            font-size: 12px;
            background: var(--surface);
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            outline: none;
        }
        .filter-search:focus { border-color: var(--gold); }

        .filter-option {
            display: flex;
            align-items: center;
            padding: 5px 4px;
            cursor: pointer;
            user-select: none;
            border-radius: 5px;
        }
        .filter-option:hover { background: var(--surface); }
        .filter-option input[type="checkbox"] { margin-right: 8px; cursor: pointer; }
        .filter-option label {
            cursor: pointer;
            margin: 0;
            flex: 1;
            font-size: 12px;
            color: var(--ink);
        }

        .filter-dropdown-footer {
            padding: 10px 16px;
            border-top: 1px solid var(--border);
            background: var(--surface);
            display: flex;
            gap: 8px;
            justify-content: space-between;
            border-radius: 0 0 var(--radius) var(--radius);
        }

        .filter-btn-sm {
            padding: 5px 14px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }
        .filter-btn-sm-secondary { background: var(--surface); color: var(--muted); }
        .filter-btn-sm-secondary:hover { border-color: var(--gold); color: var(--gold); }
        .filter-btn-sm-primary   { background: var(--ink); color: #fff; border-color: var(--ink); }
        .filter-btn-sm-primary:hover { background: #1e293b; }

        /* ── OPTION COLOR BADGES (for selects) ── */
        .option1 { background-color: #059669; color: #fff; }
        .option2 { background-color: #dc2626; color: #fff; }
        .option3 { background-color: #D4EDBC; color: #11734B; }
        .option4 { background-color: #ffe5a0; color: #8c7850; }
        .option5 { background-color: #ffcfc9; color: #dc2626; }
        .option6 { background-color: #e4cff1; color: #795d8d; }
        .option7 { background-color: #b9e3fa; color: #417295; }
        .option8 { background-color: #e9eaee; color: #555; }

        /* ── INFO TOOLTIP ── */
        .info-tooltip {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: help;
            user-select: none;
        }
        .info-tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 0;
            top: 100%;
            margin-top: 6px;
            min-width: 240px;
            max-width: 360px;
            padding: 8px 10px;
            background: rgba(15,23,42,0.95);
            color: #fff;
            font-size: 11px;
            line-height: 1.4;
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.2);
            opacity: 0;
            visibility: hidden;
            transform: translateY(4px);
            transition: opacity 0.15s, transform 0.15s, visibility 0.15s;
            z-index: 60;
            font-family: 'DM Sans', sans-serif;
            pointer-events: none;
        }
        .info-tooltip:hover::after { opacity: 1; visibility: visible; transform: translateY(0); }

        /* ── MODAL OVERRIDES ── */
        #verMasModal p { font-size: 1.1rem; }

        /* ── ANIMATIONS ── */
        @keyframes rowIn {
            from { opacity: 0; transform: translateY(4px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .efege-postq table.dt-table tbody tr { animation: rowIn 0.18s ease forwards; }

        /* ── RESPONSIVE ── */
        @media (max-width: 1100px) {
            .efege-postq .kpi-strip { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 700px) {
            .efege-postq .kpi-strip { grid-template-columns: repeat(2, 1fr); }
            .efege-postq { padding: 0 12px 40px; }
        }
    </style>
</head>

<body>
<div class="efege-postq">

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
            <div class="efege-live-badge">🔴 Live</div>
        </div>
    </div>

        <!-- ════════════════ KPI STRIP ════════════════ -->
        <?php
            $isDateRangeActive_post = ((!empty($startDate) || !empty($endDate)) && empty($_GET['show_all']));
            $post_period_count = intval($leadsCountFiltered);
            $post_shown_count  = $isDateRangeActive_post ? $post_period_count : intval($todayPostLeadsCount);
            $post_card_title   = $isDateRangeActive_post ? 'en el periodo' : 'hoy';
        ?>
        <div class="kpi-strip">

        </div><!-- /.kpi-strip -->

        <!-- ════════════════ FILTER BAR ════════════════ -->
        <div class="filter-bar">
            <span class="filter-label">Filtros</span>
            <form method="get" id="filterForm" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <input type="date" name="start_date" class="filter-date"
                    value="<?php echo htmlspecialchars($startDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="date" name="end_date" class="filter-date"
                    value="<?php echo htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <!--<select name="filter_plataforma" id="filterPlataforma" class="filter-select" style="min-width:180px;">
                    <option value="">Todas las plataformas</option>
                    <option value="campania" <?php echo ($filterPlataforma === 'campania') ? 'selected' : ''; ?>>Campañas digitales</option>
                    <option value="organico"  <?php echo ($filterPlataforma === 'organico')  ? 'selected' : ''; ?>>Orgánico</option>
                </select>-->
                <button type="submit" class="filter-btn filter-btn-primary">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <a href="consulta_post_leads.php?show_all=1" class="filter-btn filter-btn-reset">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            </form>
        </div>

        <!-- ════════════════ 2. APARTADO DE REPORTES ════════════════ -->
        <section class="reports-section">
            <div class="reports-section-header">
                <div>
                    <div class="reports-section-step">2. Apartado de Reportes</div>
                    <h2 class="reports-section-title">Reporte de sesiones agendadas</h2>
                    <p class="reports-section-subtitle">Solo se consideran leads que tienen cita agendada. Usa el filtro por rango de fechas de arriba para actualizar este reporte.</p>
                </div>
                <div class="reports-range-chip">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?php echo htmlspecialchars($reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>

            <div class="reports-layout">
                <div class="reports-kpi-stack">
                    <article class="report-card report-card-highlight">
                        <div class="report-card-label">Tasa de Conversión</div>
                        <div class="report-card-value"><?php echo ($totalAgendadasFiltered > 0) ? number_format($asistenciaRate, 1) . '%' : 'N/A'; ?></div>
                        <div class="report-card-note"><?php echo number_format($totalAtendidosFiltered); ?> cierres de <?php echo number_format($totalAgendadasFiltered); ?> agendados</div>
                        <div class="report-formula"><strong>Fórmula:</strong> (Clientes cerrados / Total agendados) × 100</div>
                    </article>

                    <div class="reports-kpi-grid-secondary">
                        <article class="report-card">
                            <div class="report-card-label">Total agendados</div>
                            <div class="report-card-value"><?php echo number_format($totalAgendadasFiltered); ?></div>
                            <div class="report-card-note">Registros con cita en el periodo.</div>
                        </article>

                        <article class="report-card">
                            <div class="report-card-label">Sesiones atendidas</div>
                            <div class="report-card-value"><?php echo number_format($totalAtendidosFiltered); ?></div>
                            <div class="report-card-note">Citas con estatus atendido.</div>
                        </article>
                    </div>
                </div>

                <div class="reports-charts-row" style="flex-direction:column;">
                    <article class="report-chart-card" style="width:100%;">
                        <div class="report-chart-header">
                            <div>
                                <h3 class="report-chart-title">Sesiones agendadas por periodo</h3>
                                <p class="report-chart-subtitle">Total de sesiones agendadas por día dentro del rango seleccionado.</p>
                            </div>
                        </div>
                        <div id="postLeadsChart" class="report-chart"></div>
                    </article>

                   
                </div>
                
            </div>
             <div style="display:flex;gap:12px;width:100%;">
                        <article class="report-chart-card" style="flex:1 1 0;min-width:0;">
                            <div class="report-chart-header">
                                <div>
                                    <h3 class="report-chart-title">Método de contacto</h3>
                                    <p class="report-chart-subtitle">Distribución de sesiones según canal de contacto del agendado.</p>
                                </div>
                            </div>
                            <div id="postContactMethodChart" class="report-chart"></div>
                        </article>

                        <article class="report-chart-card" style="flex:1 1 0;min-width:0;">
                            <div class="report-chart-header">
                                <div>
                                    <h3 class="report-chart-title">Desde cuándo nos conoce</h3>
                                    <p class="report-chart-subtitle">Distribución de sesiones según el tiempo que llevan conociendo a Efege de agendados.</p>
                                </div>
                            </div>
                            <div id="postKnownUsChart" class="report-chart"></div>
                        </article>

                        <article class="report-chart-card" style="flex:1 1 0;min-width:0;">
                            <div class="report-chart-header">
                                <div>
                                    <h3 class="report-chart-title">De dónde nos conocen</h3>
                                    <p class="report-chart-subtitle">Distribución de sesiones según cómo llegaron a Efege (Wedding Planner, Community, New Market).</p>
                                </div>
                            </div>
                            <div id="postWhereKnowChart" class="report-chart"></div>
                        </article>
                    </div>
        </section>

        <!-- ════════════════ TABLE WRAP ════════════════ -->
        <div class="table-wrap">
            <div class="tw-header">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="tw-title">Post-Qualified Leads &mdash; Sesiones</span>
                    <button id="exportExcelBtn" class="btn btn-success btn-sm" style="font-size:11px;padding:4px 10px;display:inline-flex;align-items:center;gap:5px;">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </button>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="tw-count" id="table-count-label">
                        <?php echo number_format($leadsCountFiltered); ?> registros
                    </span>
                    <div style="position:relative;">
                        <i style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:11px;" class="fas fa-search"></i>
                        <input type="text" id="tableSearchInput" placeholder="Buscar..." style="padding:5px 10px 5px 26px;font-size:12px;border:1px solid var(--border);border-radius:7px;background:var(--surface);color:var(--ink);outline:none;width:200px;font-family:'DM Sans',sans-serif;">
                    </div>
                </div>
            </div>
            <div class="tw-scroll">
                <table id="leadsTable" class="dt-table">
                    <thead>
                        <tr>
                            <th data-column="nombre">Nombre</th>
                            <th data-column="boda">¿Dónde se casa?</th>
                            <th data-column="cuando_se_casa">¿Cuándo se casa?</th>
                            <th data-column="ciudad_origen"><span class="th-compact-label">Ciudad de origen del cliente</span></th>
                            <th data-column="fecha">¿Cuándo llegó?</th>
                            <th data-column="metodo_contacto"><div class="th-flex-label"><span class="th-compact-label">Método de contacto</span><i class="filter-icon bi bi-funnel" data-column="metodo_contacto"></i></div></th>
                            <th data-column="desde_conoce"><div class="th-flex-label"><span class="th-compact-label">Desde cuándo nos conoce</span><i class="filter-icon bi bi-funnel" data-column="desde_conoce"></i></div></th>
                            <th data-column="donde_conocio"><div class="th-flex-label"><span class="th-compact-label">De dónde nos conoce el cliente</span><i class="filter-icon bi bi-funnel" data-column="donde_conocio"></i></div></th>
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
                            <tr id="lead-row-<?php echo $lead['id']; ?>-<?php echo htmlspecialchars($lead['tabla_origen']); ?>">

                                <!-- Nombre + ID -->
                                <td data-column="nombre">
                                    <div class="td-name"><?php echo htmlspecialchars($lead['full_name'] ?? ''); ?></div>
                                </td>

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

                                <!-- Ciudad de De dónde nos conoce el cliente -->
                                <td data-column="ciudad_origen"><?php
                                    $city = trim((string)($lead['city'] ?? ''));
                                    echo htmlspecialchars($city !== '' ? $city : '—', ENT_QUOTES, 'UTF-8');
                                ?></td>

                                <!-- ¿Cuándo llegó? -->
                                <?php
                                $llegadaTs = 0;
                                $llegadaRaw = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
                                if (!empty($llegadaRaw)) $llegadaTs = strtotime($llegadaRaw) ?: 0;
                                ?>
                                <td data-column="fecha" data-order="<?php echo intval($llegadaTs); ?>">
                                    <?php echo htmlspecialchars(formatCreatedTime($llegadaRaw)); ?>
                                </td>

                                <!-- Método de contacto -->
                                <td data-column="metodo_contacto"><?php
                                    echo htmlspecialchars(normalizeFirstContactChannelLabel($lead['first_contact_channel'] ?? ''), ENT_QUOTES, 'UTF-8');
                                ?></td>

                                <!-- Desde cuándo nos conoce -->
                                <td data-column="desde_conoce"><?php
                                    $knu = trim((string)($lead['how_long_known_us'] ?? ''));
                                    $knuMap = [
                                        'less than 3 months'          => 'Menos de 3 meses',
                                        'between 3 months and 1 year' => 'Entre 3 meses y 1 año',
                                        'more than 1 year'            => 'Más de 1 año',
                                        'not asked'                   => 'No se preguntó',
                                    ];
                                    $knuLabel = ($knu !== '' && $knu !== '—') ? ($knuMap[mb_strtolower($knu, 'UTF-8')] ?? $knu) : '—';
                                    echo htmlspecialchars($knuLabel, ENT_QUOTES, 'UTF-8');
                                ?></td>

                                <!-- Dónde nos conoció -->
                                <td data-column="donde_conocio">
                                    <?php echo htmlspecialchars(getOrigenCategoriaLabel($lead)); ?>
                                </td>

                                <!-- Engagement (pill) -->
                                <td data-column="engagement">
                                    <?php
                                    $engRaw   = trim((string)($lead['engagement'] ?? ''));
                                    $engNorm  = mb_strtolower($engRaw, 'UTF-8');
                                    $engClass = 'eng-low';
                                    if ($engNorm === '3' || $engNorm === 'alto')  $engClass = 'eng-high';
                                    elseif ($engNorm === '2' || $engNorm === 'medio') $engClass = 'eng-mid';
                                    $engLabel = getEngagementLabelWithEmoji($lead);
                                    if ($engLabel !== 'N/A' && $engLabel !== 'Sin dato') {
                                        echo '<span class="eng-pill ' . $engClass . '">' . htmlspecialchars($engLabel) . '</span>';
                                    } else {
                                        echo '<span style="color:var(--muted);">' . htmlspecialchars($engLabel) . '</span>';
                                    }
                                    ?>
                                </td>

                                <!-- Estatus (pill) -->
                                <?php
                                $statusRaw     = isset($lead['estatus']) ? trim((string)$lead['estatus']) : '';
                                $statusLower   = $statusRaw !== '' ? mb_strtolower($statusRaw, 'UTF-8') : '';
                                $statusDisplay = $statusRaw !== '' ? ucfirst($statusLower) : '';
                                $statusClass   = 'status-default';
                                if      ($statusLower === 'agendado')  $statusClass = 'status-agendado';
                                elseif  ($statusLower === 'atendido')  $statusClass = 'status-atendido';
                                elseif  ($statusLower === 'fantasma')  $statusClass = 'status-fantasma';
                                elseif  ($statusLower === 'muerto')    $statusClass = 'status-muerto';
                                ?>
                                <td data-column="estatus">
                                    <span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusDisplay); ?></span>
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
                                        echo $paqName !== '' ? htmlspecialchars($paqName, ENT_QUOTES, 'UTF-8') : '<span style="color:var(--muted)">—</span>';
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
                                        <button class="action-btn" onclick="verComentarios(<?php echo intval($lead['id']); ?>)">
                                            <i class="fas fa-comment-dots"></i> Comentarios
                                        </button>
                                    </td>
                                <?php endif; ?>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div><!-- /.table-wrap -->

</div><!-- /.efege-postq -->

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
        var table = $('#leadsTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            ordering:  false,
            paging:    false,
            scrollX:   true,
            scrollY:   '800px',
            scrollCollapse: true,
            dom: '<""r>t<"d-flex justify-content-between align-items-center mt-2"il>',
            buttons: [{ extend: 'excelHtml5', title: 'Leads Post Qualified', exportOptions: { columns: ':visible:not(:last-child)' } }],
            drawCallback: function () { updateDynamicCards(); }
        });

        $('#exportExcelBtn').on('click', function () {
            table.button(0).trigger();
        });

        $('#tableSearchInput').on('input', function () {
            table.search(this.value).draw();
        });

        window.totalPreLeads = <?php echo isset($totalPreLeadsFiltered) ? intval($totalPreLeadsFiltered) : 0; ?>;

        updateDynamicCards();

        // ── REPORTES CHARTS ────────────────────────────────────────────
        var postChartDates  = <?php echo isset($datesJson)  ? $datesJson  : '[]'; ?>;
        var postChartCounts = <?php echo isset($countsJson) ? $countsJson : '[]'; ?>;
        var postContactMethodSeries = <?php echo isset($postContactMethodPieJson) ? $postContactMethodPieJson : '[]'; ?>;
        var postKnownUsSeries = <?php echo isset($postKnownUsPieJson) ? $postKnownUsPieJson : '[]'; ?>;

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

        var postPieOptions = {
            chart: { type: 'pie', backgroundColor: '#f8f9fa', borderRadius: 12,
                spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10 },
            title: { text: null },
            colors: ['#16a34a','#2563eb','#f59e0b','#c026d3','#0284c7','#94a3b8'],
            tooltip: { pointFormat: '<b>{point.y}</b> sesiones ({point.percentage:.1f}%)' },
            accessibility: { point: { valueSuffix: '%' } },
            plotOptions: {
                pie: {
                    innerSize: '48%', allowPointSelect: true, cursor: 'pointer',
                    dataLabels: { enabled: true, format: '<b>{point.name}</b><br>{point.y} sesiones' },
                    showInLegend: true
                }
            },
            legend: { align: 'center', verticalAlign: 'bottom', itemStyle: { fontSize: '11px' } },
            credits: { enabled: false }
        };

        Highcharts.chart('postContactMethodChart', Object.assign({}, postPieOptions, {
            series: [{ name: 'Método de contacto', colorByPoint: true, data: postContactMethodSeries }]
        }));

        Highcharts.chart('postKnownUsChart', Object.assign({}, postPieOptions, {
            series: [{ name: 'Desde cuándo nos conoce', colorByPoint: true, data: postKnownUsSeries }]
        }));

        var postWhereKnowSeries = <?php echo isset($postWhereKnowPieJson) ? $postWhereKnowPieJson : '[]'; ?>;
        Highcharts.chart('postWhereKnowChart', Object.assign({}, postPieOptions, {
            tooltip: { pointFormat: '<b>{point.y}</b> sesiones ({point.percentage:.1f}%)' },
            series: [{ name: '¿De dónde nos conocen?', colorByPoint: true, data: postWhereKnowSeries }]
        }));
        // ── FIN REPORTES CHARTS ────────────────────────────────────────

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
        $('#leadsTable tbody tr').each(function () {
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

        var iconOffset     = $(iconElement).offset();
        var iconHeight     = $(iconElement).outerHeight();
        var dropdownWidth  = dropdown.outerWidth();
        var dropdownHeight = dropdown.outerHeight();
        var windowWidth    = $(window).width();
        var windowHeight   = $(window).height();
        var left = iconOffset.left;
        var top  = iconOffset.top + iconHeight + 5;
        if (left + dropdownWidth  > windowWidth)  left = windowWidth  - dropdownWidth  - 10;
        if (top  + dropdownHeight > windowHeight) top  = iconOffset.top - dropdownHeight - 5;
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

    function applyColumnFilters() {
        $('.filter-icon').removeClass('active');
        Object.keys(columnFilters).forEach(function (col) {
            $('.filter-icon[data-column="' + col + '"]').addClass('active');
        });
        $('#leadsTable tbody tr').each(function () {
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
        var today       = new Date();
        var todayCount  = 0;
        var totalVisible = 0;

        $('#leadsTable tbody tr:visible').each(function () {
            totalVisible++;
            var submissionDate = $(this).find('td').eq(1).text().trim();
            if (submissionDate) {
                var monthNames   = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
                var todayPattern = today.getDate() + ' de ' + monthNames[today.getMonth()] + ' de ' + today.getFullYear();
                if (submissionDate.includes(todayPattern)) todayCount++;
            }
        });

        var totalVisibleAtendidos  = 0;
        var totalVisibleAgendadas  = 0;

        $('#leadsTable tbody tr:visible').each(function () {
            var estatusCell = $(this).find('td[data-column="estatus"]');
            if (estatusCell.length) {
                var estatus    = estatusCell.text().trim().toLowerCase();
                var estatusNum = (!isNaN(Number(estatus)) && estatus !== '') ? Number(estatus) : null;
                if (estatus === 'atendido' || estatusNum === 1) totalVisibleAtendidos++;
                if (['agendado','atendido','fantasma','muerto'].includes(estatus) || (estatusNum !== null && [0,1,2,3].includes(estatusNum))) totalVisibleAgendadas++;
            }
        });

        var preQTotal    = (window.totalPreLeads && window.totalPreLeads > 0) ? window.totalPreLeads : 0;
        var bookingRate  = (preQTotal > 0)          ? ((totalVisibleAgendadas  / preQTotal)          * 100).toFixed(2) : '0.00';
        var asistRate    = (totalVisibleAgendadas > 0) ? ((totalVisibleAtendidos / totalVisibleAgendadas) * 100).toFixed(2) : '0.00';

        var startVal = $('input[name="start_date"]').val();
        var endVal   = $('input[name="end_date"]').val();
        var showAll  = (new URLSearchParams(window.location.search)).has('show_all');
        var isRange  = !showAll && (startVal !== '' || endVal !== '');

        $('#card-today-title').text(isRange ? 'en el periodo' : 'hoy');
        $('#card-today-count').text(isRange ? totalVisible : todayCount);
        $('#card-total-count').text(totalVisible);
        $('#card-conversion-rate').text(bookingRate + '%');
        $('#card-conversion-detail').text(totalVisibleAgendadas + ' agendados / ' + preQTotal + ' leads pre-q');
        $('#card-agendados-count').text(totalVisibleAgendadas);
        $('#card-atendidos-count').text(totalVisibleAtendidos);
        $('#card-asistencia-postq-rate').text(asistRate + '%');
        $('#card-asistencia-rate').text(asistRate + '%');
        $('#card-asistencia-postq-detail').text(totalVisibleAtendidos + ' atendidos / ' + totalVisibleAgendadas + ' agendados');

        // Update table count label
        $('#table-count-label').text(totalVisible + ' registros');
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

<script>
(function() {
    var leadsData = <?php
        $debugLeads = array_map(function($l) {
            return [
                'id'              => $l['id'] ?? '',
                'tabla_origen'    => $l['tabla_origen'] ?? '',
                'nombre_completo' => $l['full_name'] ?? '',
                'metodo_contacto' => $l['first_contact_channel'] ?? '',
            ];
        }, $displayLeads);
        echo json_encode($debugLeads, JSON_UNESCAPED_UNICODE);
    ?>;
    console.log('Total registros sin filtrar: ' + leadsData.length);
    console.table(leadsData);
})();
</script>
</body>
</html>