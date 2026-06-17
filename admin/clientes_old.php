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
$sqlTablasPreLeads = "SELECT nombre FROM tablas_leads ORDER BY nombre";
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
        $leadDate = date('Y-m-d', strtotime($lead['created_time']));
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

// Calcular leads que agendaron (agendado, atendido, cliente) - ANTES del filtro por estatus
$leadsAgendadosTotal = 0;
$totalLeadsBeforeStatusFilter = count($filteredPreLeadsForConversion);
foreach ($filteredPreLeadsForConversion as $lead) {
    $tabla = $lead['tabla_origen'] ?? '';
    $lid = isset($lead['id']) ? intval($lead['id']) : 0;
    $key = $tabla . '|' . $lid;
    if (isset($leadStatusMapPre[$key])) {
        $status = strtolower($leadStatusMapPre[$key]);
        if ($status === 'agendado' || $status === 'atendido' || $status === 'cliente') {
            $leadsAgendadosTotal++;
        }
    }
}

// Calcular tasa de conversión (porcentaje)
$tasaConversion = $totalLeadsBeforeStatusFilter > 0 ? round(($leadsAgendadosTotal / $totalLeadsBeforeStatusFilter), 2) : 0;

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
            
            // Añadir estatus desde la cita (igual que en consulta_post_leads.php)
            $merged['estatus'] = '';
            if ($cf['cliente'] == 1) {
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
        // Use submission_date from contact_form (igual que consulta_post_leads.php)
        $ct = $lead['submission_date'] ?? '';
        if (empty($ct)) continue;
        $ts = strtotime($ct);
        if ($ts === false) continue;
        $d = date('Y-m-d', $ts);
        
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

// Contar leads con fecha_cambio_cliente (SIN excluir fantasmas)
$leadsWithFechaCambio = 0;
foreach ($displayPostLeadsForRatio as $lead) {
    $fechaCambio = isset($lead['fecha_cambio_cliente']) ? trim($lead['fecha_cambio_cliente']) : '';
    if (!empty($fechaCambio) && $fechaCambio !== '0000-00-00' && $fechaCambio !== '0000-00-00 00:00:00') {
        $leadsWithFechaCambio++;
    }
}

// Calcular ratio de conversión (EXACTO como consulta_post_leads.php: leads totales / leads que se convirtieron a cliente)
$conversionRatio = 0;
if ($leadsWithFechaCambio > 0) {
    $conversionRatio = round( ($leadsWithFechaCambio / $leadsPostCountFiltered) * 100 , 2);
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





// ===== Distribución de cómo nos conocieron (donut chart) =====
$howDidYouMeetCounts = ['Wedding Planner' => 0, 'Community' => 0, 'New Market' => 0, 'Sin dato' => 0];
foreach ($displayLeads as $lead) {
    $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
    if ($howRaw === '1') $howDidYouMeetCounts['Wedding Planner']++;
    elseif ($howRaw === '2') $howDidYouMeetCounts['Community']++;
    elseif ($howRaw === '3') $howDidYouMeetCounts['New Market']++;
    else $howDidYouMeetCounts['Sin dato']++;
}
$howDidYouMeetTotal = max(1, array_sum($howDidYouMeetCounts));
$wpDeg    = round($howDidYouMeetCounts['Wedding Planner'] / $howDidYouMeetTotal * 360);
$commEnd  = $wpDeg + round($howDidYouMeetCounts['Community'] / $howDidYouMeetTotal * 360);
$nmEnd    = $commEnd + round($howDidYouMeetCounts['New Market'] / $howDidYouMeetTotal * 360);
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

// ===== How Long Clients Knew EFEGE Before Closing =====
$howLongCounts = ['< 3 meses' => 0, '3 m – 1 año' => 0, '> 1 año' => 0, 'Sin dato' => 0];
foreach ($displayLeads as $lead) {
    $createdRaw = $lead['created_time'] ?? $lead['submission_date'] ?? '';
    $closedRaw  = $lead['fecha_cambio_cliente'] ?? '';
    if (empty($createdRaw) || empty($closedRaw)) { $howLongCounts['Sin dato']++; continue; }
    $tsCreated = strtotime($createdRaw);
    $tsClosed  = strtotime($closedRaw);
    if ($tsCreated === false || $tsClosed === false) { $howLongCounts['Sin dato']++; continue; }
    $diffDays = max(0, intval(($tsClosed - $tsCreated) / 86400));
    if ($diffDays < 90)       $howLongCounts['< 3 meses']++;
    elseif ($diffDays < 365)  $howLongCounts['3 m – 1 año']++;
    else                      $howLongCounts['> 1 año']++;
}
$howLongMax = max(1, max($howLongCounts));

// ===== New Market sub-source (Form B / hear_about_us) =====
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

// Cerrar la conexión (se puede reabrir más tarde si es necesario)
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierres — EFEGE</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
    /* ══════════════════════════════════════════
       EFEGE CLIENTS — LIGHT MODE REDESIGN
    ══════════════════════════════════════════ */
    :root {
      --gold:         #C5A028;
      --gold-dim:     rgba(197,160,40,0.12);
      --gold-border:  rgba(197,160,40,0.30);
      --bg:           #f8fafc;
      --panel:        #ffffff;
      --surface:      #f1f5f9;
      --ink:          #0f172a;
      --ink-soft:     #1e293b;
      --ink-strong:   #0f172a;
      --muted:        #64748b;
      --border:       rgba(0,0,0,0.08);
      --shadow:       0 1px 3px rgba(0,0,0,0.04);
      --shadow-md:    0 4px 14px rgba(0,0,0,0.08);
      --planner:      #3B82F6;
      --planner-bg:   rgba(59,130,246,0.10);
      --community:    #10B981;
      --community-bg: rgba(16,185,129,0.10);
      --newmarket:    #A855F7;
      --newmarket-bg: rgba(168,85,247,0.10);
      --eng-low:      #F59E0B;
      --eng-mid:      #3B82F6;
      --eng-high:     #10B981;
      --radius:       12px;
    }
body { background: var(--dark, #F8FAFC) !important; }
    .efege-clients {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--ink);
      min-height: 100vh;
      padding: 0 20px 60px;
      font-size: 13px;
    }

    /* ── PAGE HEADER ── */
    .ec-page-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      padding: 20px 0 16px;
      margin-bottom: 20px;
      border-bottom: 1px solid var(--border);
    }
    .ec-page-header-left  { flex: 1; min-width: 0; }
    .ec-page-header-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
    .ec-page-title {
      font-family: 'Cormorant Garamond', Georgia, serif;
      font-size: 26px;
      font-weight: 600;
      letter-spacing: 2px;
      color: var(--ink);
      line-height: 1;
      margin-bottom: 4px;
    }
    .efege-title-accent { color: var(--gold); }
    .ec-page-title-sub {
      font-size: 14px;
      letter-spacing: 1px;
      font-weight: 400;
      color: var(--muted);
      font-family: 'DM Sans', sans-serif;
    }
    .ec-page-subtitle {
      font-size: 12px;
      color: var(--muted);
      margin-top: 4px;
    }
    .ec-date-range {
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
    .ec-date-range span { color: var(--ink); font-weight: 600; }
    .ec-live-badge {
      font-size: 11px;
      color: var(--muted);
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 6px 12px;
    }

    /* ── KPI STRIP ── */
    .ec-kpi-strip {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 12px;
      margin-bottom: 20px;
    }
    .ec-kpi-card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 18px 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
      transition: box-shadow 0.2s;
    }
    .ec-kpi-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
    .ec-kpi-label {
      font-size: 10px;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--muted);
      font-weight: 600;
      margin-bottom: 8px;
    }
    .ec-kpi-value {
      font-size: 30px;
      font-weight: 700;
      color: var(--ink);
      line-height: 1;
      margin-bottom: 6px;
    }
    .ec-kpi-sub { font-size: 11px; color: var(--muted); }
    .ec-kpi-tag {
      display: inline-block;
      padding: 1px 8px;
      border-radius: 20px;
      font-size: 10px;
      font-weight: 600;
    }

    /* ── INSIGHTS GRID 2×2 ── */
    .ec-insights-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 20px;
    }
    .ec-chart-card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 22px;
      box-shadow: var(--shadow);
    }
    .ec-chart-title {
      font-size: 10px;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--muted);
      font-weight: 600;
      margin-bottom: 18px;
    }

    /* Donut */
    .ec-donut-wrap { display: flex; align-items: center; gap: 24px; }
    .ec-donut {
      width: 108px; height: 108px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .ec-donut-hole {
      width: 64px; height: 64px;
      background: var(--panel);
      border-radius: 50%;
    }
    .ec-donut-legend { flex: 1; }
    .ec-legend-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 10px;
    }
    .ec-legend-left { display: flex; align-items: center; gap: 8px; }
    .ec-legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .ec-legend-name { font-size: 12px; color: var(--ink); font-weight: 500; }
    .ec-legend-pct  { font-size: 13px; font-weight: 700; color: var(--ink-strong); }

    /* Bar chart */
    .ec-bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 11px; }
    .ec-bar-label {
      font-size: 11px; color: var(--muted); width: 130px; flex-shrink: 0;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ec-bar-track {
      flex: 1; height: 8px;
      background: var(--surface);
      border-radius: 4px; overflow: hidden;
      border: 1px solid var(--border);
    }
    .ec-bar-fill {
      height: 100%; border-radius: 4px;
      background: var(--gold);
      transition: width 0.6s ease;
    }
    .ec-bar-val { font-size: 11px; font-weight: 700; color: var(--ink); width: 32px; text-align: right; }

    /* ── FILTER BAR ── */
    .ec-filter-bar {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 14px 20px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      box-shadow: var(--shadow);
    }
    .ec-filter-label {
      font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase;
      color: var(--muted); font-weight: 600; flex-shrink: 0; margin-right: 4px;
    }
    .ec-filter-input, .ec-filter-select {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 7px;
      color: var(--ink);
      font-family: 'DM Sans', sans-serif;
      font-size: 12px;
      padding: 7px 12px;
      outline: none;
      transition: border-color 0.2s;
    }
    .ec-filter-select {
      padding-right: 28px;
      cursor: pointer; appearance: none; -webkit-appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 10px center;
      min-width: 160px;
    }
    .ec-filter-input:focus, .ec-filter-select:focus { border-color: var(--gold); }
    .ec-filter-btn {
      padding: 7px 18px; border-radius: 7px;
      font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 600;
      cursor: pointer; border: 1px solid transparent; transition: all 0.2s;
    }
    .ec-filter-btn-primary { background: var(--ink-strong); color: #fff; }
    .ec-filter-btn-primary:hover { background: #334155; }
    .ec-filter-btn-reset { background: transparent; border-color: var(--border); color: var(--muted); }
    .ec-filter-btn-reset:hover { color: var(--gold); border-color: var(--gold-border); }
    .ec-filter-search-wrap { position: relative; margin-left: auto; }
    .ec-filter-search {
      background: var(--surface); border: 1px solid var(--border); border-radius: 7px;
      color: var(--ink); font-family: 'DM Sans', sans-serif; font-size: 12px;
      padding: 7px 12px 7px 32px; outline: none; width: 200px; transition: border-color 0.2s;
    }
    .ec-filter-search:focus { border-color: var(--gold); }
    .ec-filter-search::placeholder { color: var(--muted); }
    .ec-filter-search-icon {
      position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
      color: var(--muted); font-size: 12px; pointer-events: none;
    }

    /* ── TABLE ── */
    .ec-table-wrap {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow);
    }
    .ec-table-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
    }
    .ec-table-title { font-size: 14px; font-weight: 600; color: var(--ink-strong); }
    .ec-table-count { font-size: 11px; color: var(--muted); }
    .ec-table-scroll { overflow: visible; }
    /* DataTables scrollX: solo el cuerpo de la tabla hace scroll horizontal */
    .ec-table-scroll .dataTables_scrollBody {
        scrollbar-width: thin;
        scrollbar-color: var(--border, #e2e8f0) transparent;
    }
    .ec-table-scroll .dataTables_scrollBody::-webkit-scrollbar { height: 6px; }
    .ec-table-scroll .dataTables_scrollBody::-webkit-scrollbar-track { background: transparent; }
    .ec-table-scroll .dataTables_scrollBody::-webkit-scrollbar-thumb { background: var(--border, #e2e8f0); border-radius: 3px; }
    .ec-table-wrap table { width: 100%; border-collapse: collapse; }
    .ec-table-wrap thead th {
      text-align: left; padding: 10px 16px;
      font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase;
      color: var(--muted); font-weight: 600;
      border-bottom: 1px solid var(--border);
      background: var(--surface); white-space: nowrap;
    }
    .ec-table-wrap tbody tr {
      border-bottom: 1px solid rgba(0,0,0,0.05);
      transition: background 0.15s;
    }
    .ec-table-wrap tbody tr:last-child { border-bottom: none; }
    .ec-table-wrap tbody tr:hover { background: var(--surface); }
    .ec-table-wrap td { padding: 12px 16px; vertical-align: middle; white-space: nowrap; }
    .ec-td-name { font-weight: 600; color: var(--ink-strong); font-size: 13px; }
    .ec-td-sub  { font-size: 11px; color: var(--muted); margin-top: 2px; }

    /* ── BADGES ── */
    .ec-badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap;
    }
    .ec-badge-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
    .ec-badge-planner  { background: var(--planner-bg);  color: var(--planner);  }
    .ec-badge-community{ background: var(--community-bg);color: var(--community);}
    .ec-badge-newmarket{ background: var(--newmarket-bg); color: var(--newmarket);}
    .ec-badge-default  { background: rgba(100,116,139,0.10); color: var(--muted); }
    .ec-badge-gold     { background: var(--gold-dim); color: var(--gold); border: 1px solid var(--gold-border); }

    /* Engagement pills */
    .ec-eng {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
    }
    .ec-eng-alto  { background: rgba(16,185,129,0.10); color: var(--eng-high); }
    .ec-eng-medio { background: rgba(59,130,246,0.10);  color: var(--eng-mid);  }
    .ec-eng-bajo  { background: rgba(245,158,11,0.10);  color: var(--eng-low);  }
    .ec-eng-na    { background: rgba(100,116,139,0.10); color: var(--muted);    }

    .ec-pkg { font-size: 12px; color: var(--ink); font-weight: 500; }

    /* Action button */
    .ec-btn-sm {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600;
      border: 1px solid var(--border); background: var(--surface); color: var(--ink);
      cursor: pointer; transition: all 0.2s; font-family: 'DM Sans', sans-serif;
    }
    .ec-btn-sm:hover { background: var(--gold-dim); border-color: var(--gold-border); color: var(--gold); }

    /* ── COLUMN FILTER SYSTEM ── */
    .filter-icon {
      cursor: pointer; margin-left: 4px; color: var(--muted);
      font-size: 0.75rem; transition: color 0.2s;
    }
    .filter-icon:hover, .filter-icon.active { color: var(--gold); }
    .filter-dropdown {
      position: absolute; background: var(--panel); border: 1px solid var(--border);
      border-radius: 10px; box-shadow: var(--shadow-md); padding: 1rem;
      min-width: 250px; max-width: 350px; z-index: 1050; max-height: 400px; overflow-y: auto;
    }
    .filter-dropdown-header {
      font-weight: 600; margin-bottom: 0.75rem; padding-bottom: 0.5rem;
      border-bottom: 1px solid var(--border); display: flex; justify-content: space-between;
      align-items: center; font-size: 12px; color: var(--ink);
    }
    .filter-search {
      width: 100%; padding: 6px 10px; margin-bottom: 0.75rem;
      border: 1px solid var(--border); border-radius: 7px; font-size: 12px;
      background: var(--surface); color: var(--ink); outline: none;
    }
    .filter-options { max-height: 200px; overflow-y: auto; margin-bottom: 0.75rem; }
    .filter-option { display: flex; align-items: center; padding: 4px 0; font-size: 12px; color: var(--ink); }
    .filter-option input[type="checkbox"] { margin-right: 8px; accent-color: var(--gold); }
    .filter-option label { margin: 0; cursor: pointer; flex: 1; }
    .filter-actions { display: flex; gap: 8px; padding-top: 10px; border-top: 1px solid var(--border); }
    .filter-actions button {
      flex: 1; padding: 6px 10px; font-size: 11px; font-weight: 600;
      border: none; border-radius: 7px; cursor: pointer; transition: background-color 0.2s;
      font-family: 'DM Sans', sans-serif;
    }
    .filter-apply { background: var(--ink-strong); color: #fff; }
    .filter-apply:hover { background: #334155; }
    .filter-clear { background: var(--surface); color: var(--muted); border: 1px solid var(--border); }
    .filter-clear:hover { background: var(--gold-dim); color: var(--gold); }

    /* Option colour classes (selects) */
    .option1 { background-color: green; color: white; }
    .option2 { background-color: red; color: white; }
    .option3 { background-color: #D4EDBC; color: #11734B; }
    .option4 { background-color: #ffe5a0; color: #8c7850; }
    .option5 { background-color: #ffcfc9; color: red; }
    .option6 { background-color: #e4cff1; color: #795d8d; }
    .option7 { background-color: #b9e3fa; color: #417295; }
    .option8 { background-color: #e9eaee; color: black; }

    .lead-update {
      font-size: 11px; border-radius: 20px; border: 1px solid var(--border);
      padding: 3px 8px; background: var(--surface); color: var(--ink);
      cursor: pointer; font-family: 'DM Sans', sans-serif;
    }

    /* DataTables overrides */
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 7px; padding: 5px 10px;
      color: var(--ink); font-family: 'DM Sans', sans-serif;
    }
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
      font-size: 12px; color: var(--muted); padding: 12px 20px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:not(.disabled):hover {
      background: var(--gold-dim) !important; border-color: var(--gold-border) !important;
      color: var(--gold) !important; border-radius: 6px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
      background: var(--gold-dim) !important; border-color: var(--gold-border) !important;
      color: var(--gold) !important; border-radius: 6px;
    }

    @media (max-width: 1024px) {
      .ec-kpi-strip    { grid-template-columns: repeat(3,1fr); }
      .ec-insights-row { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
      .efege-clients   { padding: 16px; }
      .ec-kpi-strip    { grid-template-columns: repeat(2,1fr); }
    }
    </style>
</head>
<body>
<div class="efege-clients">

  <!-- ════ PAGE HEADER ════ -->
  <div class="ec-page-header">
    <div class="ec-page-header-left">
      <div class="ec-page-title"><span class="ec-page-title-sub">Cierres</span></div>
      <div class="ec-page-subtitle">Gestión y seguimiento de clientes cerrados &middot; Plataforma: <strong><?php echo htmlspecialchars(ucfirst($platformLabel)); ?></strong></div>
    </div>
    <div class="ec-page-header-right">
      <?php if (!empty($startDate) && !empty($endDate) && empty($_GET['show_all'])): ?>
      <div class="ec-date-range">📅 <span><?php echo date('d M Y', strtotime($startDate)); ?></span>&nbsp;→&nbsp;<span><?php echo date('d M Y', strtotime($endDate)); ?></span></div>
      <?php elseif (!empty($startDate) && empty($_GET['show_all'])): ?>
      <div class="ec-date-range">📅 <span>Desde <?php echo date('d M Y', strtotime($startDate)); ?></span></div>
      <?php else: ?>
      <div class="ec-date-range">📅 <span>Todos los registros</span></div>
      <?php endif; ?>
      <div class="ec-live-badge">🔴 Live</div>
    </div>
  </div>

  <!-- ════ KPI STRIP ════ -->
  <div class="ec-kpi-strip">
    <div class="ec-kpi-card">
      <div class="ec-kpi-label">Cierres en período</div>
      <div class="ec-kpi-value" style="color:var(--gold)" id="card-total-count"><?php echo intval($leadsCountFiltered); ?></div>
      <div class="ec-kpi-sub">clientes en el rango</div>
    </div>
    <div class="ec-kpi-card">
      <div class="ec-kpi-label">Tasa de conversión</div>
      <div class="ec-kpi-value" id="card-conversion-rate"><?php echo number_format($conversionRatio, 1); ?>%</div>
      <div class="ec-kpi-sub" id="card-conversion-detail"><?php echo intval($leadsWithFechaCambio); ?> cierres / <?php echo intval($leadsPostCountFiltered); ?> post-qualified</div>
    </div>
    <div class="ec-kpi-card">
      <div class="ec-kpi-label">From Community</div>
      <div class="ec-kpi-value" style="color:var(--community)"><?php echo intval($howDidYouMeetCounts['Community']); ?></div>
      <div class="ec-kpi-sub">
        <span class="ec-kpi-tag" style="background:rgba(16,185,129,0.12);color:var(--community)">
          <?php echo $howDidYouMeetTotal > 0 ? round($howDidYouMeetCounts['Community'] / $howDidYouMeetTotal * 100) : 0; ?>%
        </span> de cierres
      </div>
    </div>
    <div class="ec-kpi-card">
      <div class="ec-kpi-label">From Planner</div>
      <div class="ec-kpi-value" style="color:var(--planner)"><?php echo intval($howDidYouMeetCounts['Wedding Planner']); ?></div>
      <div class="ec-kpi-sub">
        <span class="ec-kpi-tag" style="background:rgba(59,130,246,0.12);color:var(--planner)">
          <?php echo $howDidYouMeetTotal > 0 ? round($howDidYouMeetCounts['Wedding Planner'] / $howDidYouMeetTotal * 100) : 0; ?>%
        </span> de cierres
      </div>
    </div>
    <div class="ec-kpi-card">
      <div class="ec-kpi-label">From New Market</div>
      <div class="ec-kpi-value" style="color:var(--newmarket)"><?php echo intval($howDidYouMeetCounts['New Market']); ?></div>
      <div class="ec-kpi-sub">
        <span class="ec-kpi-tag" style="background:rgba(168,85,247,0.12);color:var(--newmarket)">
          <?php echo $howDidYouMeetTotal > 0 ? round($howDidYouMeetCounts['New Market'] / $howDidYouMeetTotal * 100) : 0; ?>%
        </span> este per&iacute;odo
      </div>
    </div>
  </div>

  <!-- ════ INSIGHTS ROW 2×2 ════ -->
  <div class="ec-insights-row">

    <!-- Chart 1: Cierres por Origen -->
    <div class="ec-chart-card">
      <div class="ec-chart-title">Cierres por Origen — Dónde nos conocieron</div>
      <div class="ec-donut-wrap">
        <div class="ec-donut" style="background:conic-gradient(<?php echo htmlspecialchars($originDonutGradient); ?>);">
          <div class="ec-donut-hole"></div>
        </div>
        <div class="ec-donut-legend">
          <?php
          $originColors = [
            'Wedding Planner' => 'var(--planner)',
            'Community'       => 'var(--community)',
            'New Market'      => 'var(--newmarket)',
            'Sin dato'        => '#94a3b8',
          ];
          foreach ($howDidYouMeetCounts as $olabel => $ocnt):
            $opct = $howDidYouMeetTotal > 0 ? round($ocnt / $howDidYouMeetTotal * 100) : 0;
            $ocol = $originColors[$olabel] ?? '#94a3b8';
          ?>
          <div class="ec-legend-item">
            <div class="ec-legend-left">
              <div class="ec-legend-dot" style="background:<?php echo $ocol; ?>"></div>
              <span class="ec-legend-name"><?php echo htmlspecialchars($olabel); ?></span>
            </div>
            <span class="ec-legend-pct" style="color:<?php echo $ocol; ?>"><?php echo $opct; ?>%</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Chart 2: Cierres por Engagement -->
    <div class="ec-chart-card">
      <div class="ec-chart-title">Cierres por Nivel de Engagement</div>
      <div class="ec-donut-wrap">
        <div class="ec-donut" style="background:conic-gradient(<?php echo htmlspecialchars($engDonutGradient); ?>);">
          <div class="ec-donut-hole"></div>
        </div>
        <div class="ec-donut-legend">
          <?php
          $engColors = [
            'Alto'     => 'var(--eng-high)',
            'Medio'    => 'var(--eng-mid)',
            'Bajo'     => 'var(--eng-low)',
            'Sin dato' => '#94a3b8',
          ];
          foreach ($engagementCounts as $elabel => $ecnt):
            $epct = $engagementTotal > 0 ? round($ecnt / $engagementTotal * 100) : 0;
            $ecol = $engColors[$elabel] ?? '#94a3b8';
          ?>
          <div class="ec-legend-item">
            <div class="ec-legend-left">
              <div class="ec-legend-dot" style="background:<?php echo $ecol; ?>"></div>
              <span class="ec-legend-name"><?php echo htmlspecialchars($elabel); ?></span>
            </div>
            <span class="ec-legend-pct" style="color:<?php echo $ecol; ?>"><?php echo $epct; ?>%</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Chart 4: How Long Clients Knew Us Before Closing -->
    <div class="ec-chart-card">
      <div class="ec-chart-title">How Long Clients Knew Us Before Closing</div>
      <?php if (array_sum($howLongCounts) > 0): ?>
        <?php foreach (['< 3 meses', '3 m – 1 año', '> 1 año', 'Sin dato'] as $hlLabel):
          $hlCnt = $howLongCounts[$hlLabel] ?? 0;
          $hlPct = $howLongMax > 0 ? intval(round($hlCnt / $howLongMax * 100)) : 0;
        ?>
        <div class="ec-bar-row">
          <div class="ec-bar-label"><?php echo htmlspecialchars($hlLabel); ?></div>
          <div class="ec-bar-track"><div class="ec-bar-fill" style="width:<?php echo $hlPct; ?>%;background:var(--gold)"></div></div>
          <div class="ec-bar-val"><?php echo intval($hlCnt); ?></div>
        </div>
        <?php endforeach; ?>
        <?php
          $monthsPlus    = ($howLongCounts['3 m – 1 año'] ?? 0) + ($howLongCounts['> 1 año'] ?? 0);
          $monthsPlusPct = ($leadsCountFiltered > 0) ? round($monthsPlus / $leadsCountFiltered * 100) : 0;
        ?>
        <?php if ($monthsPlusPct >= 50): ?>
        <div style="margin-top:14px;padding:10px 14px;background:var(--surface);border-radius:8px;border:1px solid var(--border);">
          <div style="font-size:10px;color:var(--muted);margin-bottom:3px;letter-spacing:1px;text-transform:uppercase;font-weight:600;">Key insight</div>
          <div style="font-size:12px;color:var(--ink);"><?php echo $monthsPlusPct; ?>% de clientes conoc&iacute;an a EFEGE <strong style="color:var(--gold)">3+ meses</strong> antes de cerrar.</div>
        </div>
        <?php endif; ?>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:140px;gap:8px;color:var(--muted);">
          <div style="font-size:30px;">&#x23F3;</div>
          <div style="font-size:13px;font-weight:600;color:var(--ink);">Sin datos en el per&iacute;odo</div>
          <div style="font-size:11px;">Crea leads con fecha de creaci&oacute;n y cierre para poblar este gr&aacute;fico.</div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Chart 5: New Market sub-source (Form B) -->
    <div class="ec-chart-card">
      <div class="ec-chart-title">New Market &mdash; How They Found Us Exactly <span style="color:rgba(197,160,40,0.5);font-size:10px;margin-left:6px;">Form B</span></div>
      <?php if (!empty($newMarketSubCounts)): ?>
        <?php foreach ($newMarketSubCounts as $subLabel => $subCnt):
          $subPct = $newMarketSubMax > 0 ? intval(round($subCnt / $newMarketSubMax * 100)) : 0;
        ?>
        <div class="ec-bar-row">
          <div class="ec-bar-label"><?php echo htmlspecialchars($subLabel); ?></div>
          <div class="ec-bar-track"><div class="ec-bar-fill" style="width:<?php echo $subPct; ?>%;background:var(--newmarket)"></div></div>
          <div class="ec-bar-val"><?php echo intval($subCnt); ?></div>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:14px;padding:10px 14px;background:var(--surface);border-radius:8px;border:1px solid var(--border);">
          <div style="font-size:10px;color:var(--muted);margin-bottom:3px;letter-spacing:1px;text-transform:uppercase;font-weight:600;">Total New Market</div>
          <div style="font-size:22px;font-weight:700;color:var(--newmarket)"><?php echo intval($howDidYouMeetCounts['New Market']); ?></div>
        </div>
      <?php else: ?>
        <div style="display:flex;align-items:center;justify-content:center;height:120px;flex-direction:column;gap:8px;">
          <div style="font-size:28px;">&#x1F680;</div>
          <div style="font-size:13px;font-weight:600;color:var(--ink);">No New Market closings this period</div>
          <div style="font-size:11px;color:var(--muted);text-align:center;max-width:240px;line-height:1.5;">This chart will populate once leads from Meta Ads, SEO, Collaborations or Press close a booking.</div>
        </div>
        <div style="border-top:1px solid var(--border);margin-top:12px;padding-top:12px;">
          <div class="ec-chart-title" style="margin-bottom:10px;">Preview &mdash; how it will look when data exists</div>
          <div style="opacity:0.4;">
            <div class="ec-bar-row"><div class="ec-bar-label">Meta Ads</div><div class="ec-bar-track"><div class="ec-bar-fill" style="width:55%;background:var(--newmarket)"></div></div><div class="ec-bar-val" style="color:var(--muted)">&mdash;</div></div>
            <div class="ec-bar-row"><div class="ec-bar-label">SEO / Search</div><div class="ec-bar-track"><div class="ec-bar-fill" style="width:25%;background:var(--newmarket)"></div></div><div class="ec-bar-val" style="color:var(--muted)">&mdash;</div></div>
            <div class="ec-bar-row"><div class="ec-bar-label">Colaboraci&oacute;n</div><div class="ec-bar-track"><div class="ec-bar-fill" style="width:12%;background:var(--newmarket)"></div></div><div class="ec-bar-val" style="color:var(--muted)">&mdash;</div></div>
            <div class="ec-bar-row"><div class="ec-bar-label">Prensa / Revista</div><div class="ec-bar-track"><div class="ec-bar-fill" style="width:8%;background:var(--newmarket)"></div></div><div class="ec-bar-val" style="color:var(--muted)">&mdash;</div></div>
          </div>
        </div>
      <?php endif; ?>
    </div>

  </div><!-- /insights-row -->

  <!-- ════ FILTER BAR ════ -->
  <div class="ec-filter-bar">
    <span class="ec-filter-label">Filtrar</span>
    <form method="get" id="filterForm" style="display:contents;">
      <input type="date" name="start_date" class="ec-filter-input"
        value="<?php echo htmlspecialchars($startDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      <input type="date" name="end_date" class="ec-filter-input"
        value="<?php echo htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      <select name="filter_plataforma" id="filterPlataforma" class="ec-filter-select">
        <option value="">Todas las plataformas</option>
        <option value="campania" <?php echo ($filterPlataforma === 'campania') ? 'selected' : ''; ?>>Redes Sociales</option>
        <option value="organico" <?php echo ($filterPlataforma === 'organico') ? 'selected' : ''; ?>>Orgánico</option>
      </select>
      <button type="submit" class="ec-filter-btn ec-filter-btn-primary">Filtrar</button>
      <a href="clientes.php?show_all=1" class="ec-filter-btn ec-filter-btn-reset" style="text-decoration:none;">Limpiar</a>
    </form>
    <div class="ec-filter-search-wrap">
      <i class="fas fa-search ec-filter-search-icon"></i>
      <input type="text" id="tableSearch" class="ec-filter-search" placeholder="Buscar cliente...">
    </div>
  </div>

  <!-- ════ TABLE ════ -->
  <div class="ec-table-wrap">
    <div class="ec-table-header">
      <span class="ec-table-title">Clientes Cerrados</span>
      <span class="ec-table-count" id="tableCountLabel"><?php echo intval($leadsCountFiltered); ?> clientes en el período</span>
    </div>
    <div class="ec-table-scroll">
      <table id="leadsTable" style="width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Origen <i class="filter-icon fas fa-filter" data-column="origen"></i></th>
            <th>Medio <i class="filter-icon fas fa-filter" data-column="medio"></i></th>
            <th>Dónde nos conoció <i class="filter-icon fas fa-filter" data-column="donde_conocio"></i></th>
            <th>Engagement <i class="filter-icon fas fa-filter" data-column="engagement"></i></th>
            <th>Ubicación Boda</th>
            <th>Vendedor <i class="filter-icon fas fa-filter" data-column="vendedor"></i></th>
            <th>Paquete <i class="filter-icon fas fa-filter" data-column="paquete"></i></th>
            <th>Puntos</th>
            <th>Monto</th>
            <th>¿Qué se vendió?</th>
            <th style="display:none">Sesión Oficial</th>
            <th style="display:none">Compromiso</th>
            <th style="display:none">Técnica Cierre</th>
            <th data-column="fecha">Fecha Cierre</th>
            <?php if ($tipoUsuario != 4): ?><th>Acciones</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($displayLeads as $lead):
            /* Vendor name */
            $assignedVendorId = 0;
            if (isset($lead['id_vendedor_asignado']) && $lead['id_vendedor_asignado'] !== null && $lead['id_vendedor_asignado'] !== '') {
                $assignedVendorId = intval($lead['id_vendedor_asignado']);
            } elseif (isset($lead['usuario_asignado']) && $lead['usuario_asignado'] !== null && $lead['usuario_asignado'] !== '') {
                $assignedVendorId = intval($lead['usuario_asignado']);
            }
            $assignedVendorName = 'No asignado';
            if (!empty($vendedores)) {
                foreach ($vendedores as $vendor) {
                    if (isset($vendor['id']) && intval($vendor['id']) === $assignedVendorId) {
                        $fn = trim((string)($vendor['nombre'] ?? $vendor['nombreUsu'] ?? ''));
                        $ln = trim((string)($vendor['apePat'] ?? ''));
                        $combined = trim($fn . ' ' . $ln);
                        $assignedVendorName = ($combined !== '') ? $combined : ($vendor['nombreUsu'] ?? $assignedVendorName);
                        break;
                    }
                }
            }
            /* Paquete */
            $paq    = $lead['paquete'] ?? $lead['paquete_cotizado'] ?? '';
            $paqOut = (is_string($paq) && trim($paq) !== '') ? htmlspecialchars($paq) : 'sin datos';
            /* Puntos */
            $ptsOut = (isset($lead['puntos']) && $lead['puntos'] !== null && $lead['puntos'] !== '') ? htmlspecialchars($lead['puntos']) : 'sin datos';
            /* Monto */
            $montoOut = (isset($lead['monto_venta']) && $lead['monto_venta'] !== '') ? '$' . number_format(floatval($lead['monto_venta']), 2) : 'sin datos';
            /* Qué se vendió */
            $queVendidoOut = (isset($lead['que_se_les_vendio']) && trim((string)$lead['que_se_les_vendio']) !== '') ? htmlspecialchars($lead['que_se_les_vendio']) : 'sin datos';
            /* Fecha cierre */
            $fechaCerradoRaw = $lead['fecha_cambio_cliente'] ?? '';
            $fechaCerradoTs  = (!empty($fechaCerradoRaw) && strtotime($fechaCerradoRaw) !== false) ? intval(strtotime($fechaCerradoRaw)) : 0;
            $fechaCerradoOut = ($fechaCerradoTs > 0) ? htmlspecialchars(formatCreatedDateOnly($fechaCerradoRaw)) : 'sin datos';
            /* Engagement */
            $engRaw  = trim((string)($lead['engagement'] ?? ($lead['sfm_engagement'] ?? '')));
            $engNorm = mb_strtolower($engRaw, 'UTF-8');
            if      ($engNorm === '3' || $engNorm === 'alto')  { $engClass = 'ec-eng-alto';  $engLabel = '🔥 Alto';  }
            elseif  ($engNorm === '2' || $engNorm === 'medio') { $engClass = 'ec-eng-medio'; $engLabel = '😃 Medio'; }
            elseif  ($engNorm === '1' || $engNorm === 'bajo')  { $engClass = 'ec-eng-bajo';  $engLabel = '😑 Bajo';  }
            else { $engClass = 'ec-eng-na'; $engLabel = 'N/A'; }
            /* Origin badge */
            $howRaw2 = trim((string)($lead['how_did_you_meet'] ?? ''));
            if      ($howRaw2 === '1') { $origClass = 'ec-badge-planner';   $origLabel = 'Wedding Planner'; }
            elseif  ($howRaw2 === '2') { $origClass = 'ec-badge-community'; $origLabel = 'Community';       }
            elseif  ($howRaw2 === '3') { $origClass = 'ec-badge-newmarket'; $origLabel = 'New Market';      }
            else { $origClass = 'ec-badge-default'; $origLabel = htmlspecialchars(getDondeNosConocioLabel($lead)); }
          ?>
          <tr id="lead-row-<?php echo $lead['id']; ?>-<?php echo htmlspecialchars($lead['tabla_origen']); ?>">
            <td>
              <div class="ec-td-name"><?php echo htmlspecialchars($lead['full_name'] ?? ''); ?></div>
              <div class="ec-td-sub"><?php echo $fechaCerradoOut; ?> · #<?php echo intval($lead['id']); ?></div>
            </td>
            <td data-column="origen"><?php echo htmlspecialchars(getDisplayOrigenFromLead($lead)); ?></td>
            <td data-column="medio"><?php $m = trim((string)($lead['form_name'] ?? $lead['platform'] ?? '')); echo htmlspecialchars($m !== '' ? $m : 'N/A'); ?></td>
            <td data-column="donde_conocio">
              <span class="ec-badge <?php echo $origClass; ?>"><span class="ec-badge-dot"></span><?php echo $origLabel; ?></span>
            </td>
            <td data-column="engagement">
              <span class="ec-eng <?php echo $engClass; ?>"><?php echo $engLabel; ?></span>
            </td>
            <td data-column="ubicacion"><?php echo htmlspecialchars($lead['wedding_location'] ?? ''); ?></td>
            <td data-column="vendedor"><?php echo htmlspecialchars($assignedVendorName); ?></td>
            <td data-column="paquete"><span class="ec-pkg"><?php echo $paqOut; ?></span></td>
            <td><?php echo $ptsOut; ?></td>
            <td><?php echo $montoOut; ?></td>
            <td><?php echo $queVendidoOut; ?></td>
            <td style="display:none">
              <select class="lead-update" data-id="<?php echo intval($lead['id']); ?>" data-field="sesion_oficial" data-table="<?php echo htmlspecialchars($lead['tabla_origen']); ?>">
                <option value="">--Seleccionar--</option>
                <option value="Si" class="option1" <?php echo (isset($lead['sesion_oficial']) && $lead['sesion_oficial'] === 'Si') ? 'selected' : ''; ?>>Sí</option>
                <option value="aun no (no llegaron)" class="option2" <?php echo (isset($lead['sesion_oficial']) && $lead['sesion_oficial'] === 'aun no (no llegaron)') ? 'selected' : ''; ?>>Aún no (no llegaron)</option>
                <option value="aun no pero ya agendaron" class="option3" <?php echo (isset($lead['sesion_oficial']) && $lead['sesion_oficial'] === 'aun no pero ya agendaron') ? 'selected' : ''; ?>>Aún no pero ya agendaron</option>
                <option value="no, es relacion directa con planner" class="option4" <?php echo (isset($lead['sesion_oficial']) && $lead['sesion_oficial'] === 'no, es relacion directa con planner') ? 'selected' : ''; ?>>No, relación directa con planner</option>
              </select>
            </td>
            <td style="display:none">
              <select class="lead-update" data-id="<?php echo intval($lead['id']); ?>" data-field="compromiso_cliente" data-table="<?php echo htmlspecialchars($lead['tabla_origen']); ?>">
                <option value="">--Seleccionar--</option>
                <option value="alto"   class="option3" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'alto')     ? 'selected' : ''; ?>>Alto</option>
                <option value="normal" class="option4" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'normal')   ? 'selected' : ''; ?>>Normal</option>
                <option value="bajo"   class="option5" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'bajo')     ? 'selected' : ''; ?>>Bajo</option>
                <option value="muerto" class="option2" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'muerto')   ? 'selected' : ''; ?>>Muerto</option>
                <option value="cerrado" class="option1" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'cerrado') ? 'selected' : ''; ?>>Cerrado</option>
                <option value="stand by" class="option7" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'stand by') ? 'selected' : ''; ?>>Stand by</option>
              </select>
            </td>
            <td style="display:none">
              <select class="lead-update" data-id="<?php echo intval($lead['id']); ?>" data-field="tecnica_cierre" data-table="<?php echo htmlspecialchars($lead['tabla_origen']); ?>">
                <option value="">--Seleccionar--</option>
                <option value="Aun no"            class="option2" <?php echo (isset($lead['tecnica_cierre']) && $lead['tecnica_cierre'] === 'Aun no')            ? 'selected' : ''; ?>>Aún no</option>
                <option value="Sí"                class="option1" <?php echo (isset($lead['tecnica_cierre']) && $lead['tecnica_cierre'] === 'Sí')                ? 'selected' : ''; ?>>Sí</option>
                <option value="aun no es momento" class="option8" <?php echo (isset($lead['tecnica_cierre']) && $lead['tecnica_cierre'] === 'aun no es momento') ? 'selected' : ''; ?>>Aún no es momento</option>
              </select>
            </td>
            <td data-column="fecha" data-order="<?php echo $fechaCerradoTs; ?>"><?php echo $fechaCerradoOut; ?></td>
            <?php if ($tipoUsuario != 4): ?>
            <td>
              <button class="ec-btn-sm" onclick="verComentarios(<?php echo intval($lead['id']); ?>)">
                <i class="fas fa-comment-dots"></i> Comentarios
              </button>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /efege-clients -->

<!-- Modal comentarios -->
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
<!-- Modal verMás (JS compat) -->
<div class="modal fade" id="verMasModal" tabindex="-1" aria-hidden="true">
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

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>

<script>
const vendedores = <?php echo json_encode($vendedores); ?>;

// ── DataTable ──
$('#leadsTable').DataTable({
    language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
    ordering  : false,
    paging    : true,
    pageLength: 25,
    scrollX   : true,
    drawCallback: function() { updateDynamicCards(); }
});

// ── Live search ──
$('#tableSearch').on('input', function(){
    var term = $(this).val().toLowerCase();
    $('#leadsTable tbody tr').each(function(){
        var name = $(this).find('td:first').text().toLowerCase();
        $(this).toggle(name.indexOf(term) !== -1 || term === '');
    });
    updateDynamicCards();
});

// ── Helpers ──
function escapeHtml(u) {
    if (!u && u !== 0) return '';
    return String(u).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;').replace(/'/g,'&#039;');
}
function applyOptionClass($el) {
    if (!$el || !$el.length) return;
    $el.removeClass('option1 option2 option3 option4 option5 option6 option7 option8');
    var cls = $el.find('option:selected').attr('class');
    if (cls) $el.addClass(cls);
}

// ── Comments modal ──
function verComentarios(id) {
    Swal.fire({ title: 'Cargando comentarios...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    $.ajax({
        url: 'actualizar_lead.php', method: 'POST', dataType: 'json',
        data: { action: 'get_comments', id: id },
        success: function(resp) {
            Swal.close();
            if (resp && resp.success) {
                const comments = resp.comments || [];
                let html = '<div id="commentsList">';
                if (!comments.length) {
                    html += '<p>No hay comentarios aún.</p>';
                } else {
                    html += '<div class="list-group mb-3">';
                    comments.forEach(function(c) {
                        html += '<div class="list-group-item">';
                        html += '<div class="d-flex justify-content-between w-100"><h6 class="mb-1">'+escapeHtml(c.author||'Vendedor')+'</h6><small>'+escapeHtml(c.created_at||'')+'</small></div>';
                        html += '<p class="mb-1">'+escapeHtml(c.text||'')+'</p></div>';
                    });
                    html += '</div>';
                }
                html += '</div>';
                html += '<div class="mb-3"><label class="form-label">Agregar comentario</label><textarea id="newCommentText" class="form-control" rows="4" placeholder="Escribe un comentario..."></textarea></div>';
                html += '<div class="text-end"><button id="saveCommentBtn" class="btn btn-primary">Guardar comentario</button></div>';
                document.getElementById('comentariosModalBody').innerHTML = html;
                var modal = new bootstrap.Modal(document.getElementById('comentariosModal'));
                modal.show();
                $('#saveCommentBtn').on('click', function(){
                    var comment = $('#newCommentText').val();
                    if (!comment || !comment.trim()) { Swal.fire('Error','El comentario no puede estar vacío','error'); return; }
                    var $btn = $(this); $btn.prop('disabled', true);
                    Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    $.ajax({
                        url: 'actualizar_lead.php', method: 'POST', dataType: 'json',
                        data: { action: 'add_comment', id: id, comment: comment, author: '' },
                        success: function(resp2){
                            Swal.close();
                            if (resp2 && resp2.success) {
                                Swal.fire('Guardado','Comentario agregado correctamente','success');
                                const cn = resp2.comments || [];
                                let lh = cn.length ? '<div class="list-group mb-3">' : '<p>No hay comentarios aún.</p>';
                                cn.forEach(function(c){ lh += '<div class="list-group-item"><div class="d-flex justify-content-between w-100"><h6 class="mb-1">'+escapeHtml(c.author||'Vendedor')+'</h6><small>'+escapeHtml(c.created_at||'')+'</small></div><p class="mb-1">'+escapeHtml(c.text||'')+'</p></div>'; });
                                if (cn.length) lh += '</div>';
                                $('#commentsList').html(lh);
                                $('#newCommentText').val('');
                            } else { Swal.fire('Error', resp2.message||'No se pudo guardar', 'error'); }
                        },
                        error: function(){ Swal.close(); Swal.fire('Error','Error de comunicación','error'); },
                        complete: function(){ $btn.prop('disabled', false); }
                    });
                });
            } else { Swal.fire('Error', resp.message||'No se pudieron obtener los comentarios', 'error'); }
        },
        error: function(){ Swal.fire('Error','Error de comunicación con el servidor','error'); }
    });
}

// ── Inline lead-update selects ──
$('.lead-update').each(function(){ applyOptionClass($(this)); });
$(document).on('change', '.lead-update', function(){
    var el = $(this), id = el.data('id'), field = el.data('field'), value = el.val();
    el.prop('disabled', true);
    $.ajax({
        url: 'actualizar_lead.php', method: 'POST', dataType: 'json',
        data: { id: id, field: field, value: value },
        success: function(resp){
            if (resp && resp.success) {
                el.closest('td').css('background','#f0fdf4');
                setTimeout(function(){ el.closest('td').css('background',''); }, 800);
                applyOptionClass(el);
            } else { Swal.fire('Error', resp.message||'No se pudo actualizar', 'error'); }
        },
        error: function(){ Swal.fire('Error','Error de comunicación','error'); },
        complete: function(){ el.prop('disabled', false); }
    });
});

// ── Dynamic KPI update ──
function updateDynamicCards() {
    var visibleRows = $('#leadsTable tbody tr:visible');
    var totalVisible = visibleRows.length;
    $('#card-total-count').text(totalVisible);
    $('#tableCountLabel').text(totalVisible + ' clientes en el período');
    var totalPostLeads = <?php echo intval($leadsPostCountFiltered); ?>;
    var convRate = totalPostLeads > 0 ? ((totalVisible / totalPostLeads) * 100).toFixed(1) : '0.0';
    $('#card-conversion-rate').text(convRate + '%');
    $('#card-conversion-detail').text(totalVisible + ' cierres / ' + totalPostLeads + ' post-qualified');
}

// ── Column filter system ──
var columnFilters = {};
var currentFilterDropdown = null;

function getUniqueColumnValues(columnName) {
    var values = new Set();
    $('#leadsTable tbody tr').each(function(){
        var cell = $(this).find('td[data-column="' + columnName + '"]');
        if (cell.length) { var text = cell.text().trim(); if (text) values.add(text); }
    });
    return Array.from(values).sort();
}

function createFilterDropdown(columnName, iconElement) {
    closeFilterDropdown();
    var values = getUniqueColumnValues(columnName);
    if (!values.length) return;
    var dropdown = $('<div class="filter-dropdown"></div>');
    dropdown.append('<div class="filter-dropdown-header"><span>Filtrar</span></div>');
    var searchBox = $('<input type="text" class="filter-search" placeholder="Buscar...">');
    dropdown.append(searchBox);
    var saDiv = $('<div class="filter-option"></div>');
    var saCb  = $('<input type="checkbox" id="selectAll_' + columnName + '" checked>');
    saDiv.append(saCb).append($('<label for="selectAll_' + columnName + '">Seleccionar todo</label>'));
    dropdown.append(saDiv);
    var optsContainer = $('<div class="filter-options"></div>');
    var currentFilters = columnFilters[columnName] || [];
    values.forEach(function(value, index){
        var optDiv = $('<div class="filter-option"></div>');
        var cb = $('<input type="checkbox" id="filter_' + columnName + '_' + index + '" value="' + value + '">');
        if (!currentFilters.length || currentFilters.includes(value)) cb.prop('checked', true);
        optDiv.append(cb).append($('<label for="filter_' + columnName + '_' + index + '">' + value + '</label>'));
        optsContainer.append(optDiv);
    });
    dropdown.append(optsContainer);
    var actionsDiv = $('<div class="filter-actions"></div>');
    var applyBtn = $('<button class="filter-apply">Aplicar</button>');
    var clearBtn = $('<button class="filter-clear">Limpiar</button>');
    applyBtn.on('click', function(){
        var sel = []; optsContainer.find('input[type="checkbox"]:checked').each(function(){ sel.push($(this).val()); });
        if (sel.length === values.length) { delete columnFilters[columnName]; $(iconElement).removeClass('active'); }
        else { columnFilters[columnName] = sel; $(iconElement).addClass('active'); }
        applyColumnFilters(); closeFilterDropdown();
    });
    clearBtn.on('click', function(){
        delete columnFilters[columnName]; $(iconElement).removeClass('active');
        applyColumnFilters(); closeFilterDropdown();
    });
    actionsDiv.append(applyBtn).append(clearBtn);
    dropdown.append(actionsDiv);
    searchBox.on('input', function(){
        var s = $(this).val().toLowerCase();
        optsContainer.find('.filter-option').each(function(){ $(this).toggle($(this).find('label').text().toLowerCase().includes(s)); });
    });
    saCb.on('change', function(){ optsContainer.find('input[type="checkbox"]:visible').prop('checked', $(this).prop('checked')); });
    optsContainer.on('change', 'input[type="checkbox"]', function(){
        var tot = optsContainer.find('input[type="checkbox"]:visible').length;
        var chk = optsContainer.find('input[type="checkbox"]:visible:checked').length;
        saCb.prop('checked', tot === chk);
    });
    var iconOffset = $(iconElement).offset();
    var left = iconOffset.left - 280;
    var top  = iconOffset.top + $(iconElement).outerHeight() + 5;
    if (left < 10) left = 10;
    dropdown.css({ left: left + 'px', top: top + 'px' });
    $('body').append(dropdown);
    currentFilterDropdown = dropdown;
    searchBox.focus();
}

function closeFilterDropdown() {
    if (currentFilterDropdown) { currentFilterDropdown.remove(); currentFilterDropdown = null; }
}

function applyColumnFilters() {
    $('#leadsTable tbody tr').each(function(){
        var row = $(this); var show = true;
        for (var col in columnFilters) {
            var cell = row.find('td[data-column="' + col + '"]');
            if (cell.length && !columnFilters[col].includes(cell.text().trim())) { show = false; break; }
        }
        row.toggle(show);
    });
    updateDynamicCards();
}

$(document).on('click', '.filter-icon', function(e){
    e.stopPropagation();
    createFilterDropdown($(this).data('column'), this);
});
$(document).on('click', function(e){
    if (currentFilterDropdown && !$(e.target).closest('.filter-dropdown').length && !$(e.target).hasClass('filter-icon'))
        closeFilterDropdown();
});
$(document).on('keydown', function(e){ if (e.key === 'Escape') closeFilterDropdown(); });
</script>
</body>
</html>
