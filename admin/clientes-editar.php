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

if (!function_exists('getOrigenCategoriaLabel')) {
    function getOrigenCategoriaLabel($lead) {
        $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
        $howMap = ['1' => 'Wedding Planner', '2' => 'Community', '3' => 'New Market'];
        return ($howRaw !== '' && isset($howMap[$howRaw])) ? $howMap[$howRaw] : 'N/A';
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
            'whatsapp'                => 'WhatsApp',
            'instagram dm - campaign' => 'Instagram DM - Campaña',
            'instagram dm campaign'   => 'Instagram DM - Campaña',
            'instagram dm - organic'  => 'Instagram DM - Orgánico',
            'instagram dm organic'    => 'Instagram DM - Orgánico',
            'email'                   => 'Correo electrónico',
            'correo electronico'      => 'Correo electrónico',
            'correo electrónico'      => 'Correo electrónico',
            'mail'                    => 'Correo electrónico',
            'phone call'              => 'Phone call',
            'llamada telefonica'      => 'Phone call',
            'llamada telefónica'      => 'Phone call',
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
        $n = mb_strtolower($raw, 'UTF-8');
        if ($n === '0') return 'Sin dato';
        if ($n === '1' || $n === 'bajo')  return '😑 Bajo';
        if ($n === '2' || $n === 'medio') return '😃 Medio';
        if ($n === '3' || $n === 'alto')  return '🔥 Alto';
        return $raw;
    }
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

// Consultar registros de contact_form (todos los que son clientes)
$allLeads = [];
$sql = "SELECT * FROM contact_form WHERE cliente = 1 ORDER BY submission_date DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($cf = $result->fetch_assoc()) {
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
                    foreach ($leadRow as $k => $v) {
                        if (!isset($merged[$k]))
                            $merged[$k] = $v;
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
$resCampaign = $conn->query("SELECT COUNT(*) AS cnt FROM contact_form WHERE campaign_name IS NOT NULL AND TRIM(campaign_name) <> ''");
if ($resCampaign) {
    $r = $resCampaign->fetch_assoc();
    $campaignCount = isset($r['cnt']) ? intval($r['cnt']) : 0;
}

// Leer filtros de fecha desde GET (se usan más abajo para conteos)
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Calcular conteo total de leads que se muestran en la tabla (tienen cita) y conteo filtrado por fecha
$leadsCount = count($allLeads);
$leadsCountFiltered = 0;

if ($startDate === '' && $endDate === '') {
    $leadsCountFiltered = $leadsCount;
} else {
    $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
    $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;

    foreach ($allLeads as $lead) {
        // Usar fecha_cambio_cliente para metrics en la página de clientes; fallback a created_time/submission_date
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
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

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

        $campaignCountFiltered++;
    }
}

// Crear lista filtrada para mostrar en la tabla (si hay filtros aplicados)
$displayLeads = [];
// Si no hay filtros, mostramos todo
if ($startDate === '' && $endDate === '') {
    $displayLeads = $allLeads;
} else {
    $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
    $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;

    foreach ($allLeads as $lead) {
        // Usar fecha_cambio_cliente where available, otherwise fall back to created_time/submission_date
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

// Clientes totales (para mostrar en card)
$clientesTotal = $leadsCount;

// ===== Calcular clientes ingresados hoy y conteos por día (máximo 60 días) =====
// Removed card metrics (clientes ingresados hoy) - not used on this view.
// Chart and per-day client counts removed for this page (not used here)

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

// Obtener catálogo de paquetes (para sección "Paquete de interés" del modal de edición)
$paquetesArr = [];
$paquetesMap  = [];
$paquetesResult = $conn->query("SELECT id, nombre FROM paquetes ORDER BY id");
if ($paquetesResult) {
    while ($rp = $paquetesResult->fetch_assoc()) {
        $paquetesArr[] = $rp;
        $paquetesMap[intval($rp['id'])] = $rp['nombre'];
    }
}





// Cerrar la conexión (se puede reabrir más tarde si es necesario)
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads Postcalificados</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <!-- Highcharts removed on this page (not needed) -->
    <style>
        #verMasModal p {
            font-size: 1.15rem;
        }

        .option1 {
            background-color: green;
            color: white;
        }

        .option2 {
            background-color: red;
            color: white;
        }

        .option3 {
            background-color: #D4EDBC;
            color: #11734B;
        }

        .option4 {
            background-color: #ffe5a0;
            color: #8c7850;
        }

        .option5 {
            background-color: #ffcfc9;
            color: red;
        }

        .option6 {
            background-color: #e4cff1;
            color: #795d8d;
        }

        .option7 {
            background-color: #b9e3fa;
            color: #417295;
        }

        .option8 {
            background-color: #e9eaee;
            color: black;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .form-select-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .table-responsive {
            border-radius: 0.375rem;
            overflow-x: auto;
        }

        /* Small package cards improved design */
        .pkg-card {
            padding: 0.55rem 0.7rem;
            min-height: 90px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-radius: 0.6rem;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
            border: 1px solid rgba(0, 0, 0, 0.06);
        }

        .pkg-card .card-body {
            padding: 0.6rem;
        }

        .pkg-card .card-title {
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .pkg-card .count-number {
            font-size: 1.4rem;
        }

        .pkg-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 0.8rem 1.2rem rgba(0, 0, 0, 0.08);
        }

        .total-card {
            border: 1px solid rgba(0, 0, 0, 0.08);
            background: white;
            color: black;
        }

        @media (max-width: 575px) {
            .pkg-card {
                min-height: 88px;
            }

            .pkg-card .count-number {
                font-size: 1.2rem;
            }

            .pkg-card .card-title {
                font-size: 0.9rem;
            }
        }

        /* Small stat card for counts (used on other pages) */
        .stat-card {
            border-radius: .6rem;
            min-height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        /* ===== Edit Client Modal (style based on sfSessionBody from consulta.php) ===== */
        #editClientModal .modal-dialog {
            max-width: 760px;
        }

        #editClientModal .modal-content {
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
            border: none;
        }

        #editClientModal .modal-header {
            background: #eee8dc;
            color: #464646;
            border-bottom: none;
            padding: 22px 28px;
            display: block;
        }

        #editClientModal .modal-header h5 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
        }

        #editClientModal #sfSessionBody {
            padding: 28px 32px;
            overflow-y: auto;
            max-height: calc(100vh - 260px);
        }

        .ecm-section {
            margin-bottom: 24px;
        }

        .ecm-section-title {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
        }

        .ecm-section-number {
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

        .ecm-section-heading h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1rem;
            color: #464646;
        }

        .ecm-section-heading p {
            margin: 2px 0 0;
            font-size: 0.8rem;
            color: #777;
        }

        .ecm-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .ecm-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }

        .ecm-field label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 4px;
            display: block;
        }

        .ecm-field .form-control,
        .ecm-field .form-select {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 0.9rem;
            color: #333;
            outline: none;
            box-shadow: none;
        }

        .ecm-field .form-control:focus,
        .ecm-field .form-select:focus {
            border-color: #464646;
            box-shadow: none;
        }

        .ecm-origin-box {
            border: 1.5px solid #464646;
            border-radius: 12px;
            padding: 16px 18px;
        }

        .ecm-origin-badge {
            display: inline-block;
            background: #464646;
            color: #eee8dc;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 4px;
            margin-right: 10px;
            letter-spacing: 0.6px;
        }

        .ecm-origin-title {
            font-weight: 700;
            font-size: 1rem;
            color: #333;
        }

        .ecm-category-btns {
            display: flex;
            gap: 12px;
            margin: 14px 0 16px;
            flex-wrap: wrap;
        }

        .ecm-category-btn {
            flex: 1;
            min-width: 120px;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            padding: 14px 10px;
            text-align: center;
            cursor: pointer;
            background: #fff;
            font-size: 0.85rem;
            font-weight: 600;
            color: #444;
            transition: all 0.15s;
        }

        .ecm-category-btn .ecm-cat-icon {
            font-size: 1.5rem;
            display: block;
            margin-bottom: 6px;
        }

        .ecm-category-btn:hover {
            border-color: #464646;
        }

        .ecm-category-btn.active {
            border-color: #464646;
            background: #464646;
            color: #eee8dc;
        }

        .ecm-tipo-cliente-btn {
            flex: 1;
            min-width: 140px;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            padding: 14px 10px;
            text-align: center;
            cursor: pointer;
            background: #fff;
            font-size: 0.85rem;
            font-weight: 600;
            color: #444;
            transition: all 0.15s;
        }
        .ecm-tipo-cliente-btn .ecm-cat-icon { font-size: 1.5rem; display: block; margin-bottom: 6px; }
        .ecm-tipo-cliente-btn:hover { border-color: #464646; }
        .ecm-tipo-cliente-btn.active { border-color: #464646; background: #464646; color: #eee8dc; }

        .ecm-how-to-ask {
            margin-top: 12px;
            font-size: 0.78rem;
            color: #666;
            font-style: italic;
        }

        .ecm-package-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .ecm-package-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            padding: 13px 16px;
            cursor: pointer;
            transition: all 0.15s;
            background: #fff;
        }

        .ecm-package-item:hover {
            border-color: #464646;
        }

        .ecm-package-item.active {
            border-color: #eee8dc;
            background: #f5f5f3;
        }

        .ecm-package-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: #222;
        }

        .ecm-package-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #ccc;
            background: #fff;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
        }

        .ecm-package-item.active .ecm-package-dot {
            border-color: #eee8dc;
            background: #eee8dc;
        }

        .ecm-package-item.active .ecm-package-dot::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #fff;
        }

        .ecm-engagement-btns {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .ecm-engagement-btn {
            flex: 1;
            min-width: 130px;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            padding: 16px 10px;
            text-align: center;
            cursor: pointer;
            background: #fff;
            transition: all 0.15s;
        }

        .ecm-engagement-btn .ecm-eng-icon {
            font-size: 1.8rem;
            display: block;
            margin-bottom: 6px;
        }

        .ecm-engagement-btn .ecm-eng-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: #424242;
            display: block;
        }

        .ecm-engagement-btn .ecm-eng-desc {
            font-size: 0.75rem;
            color: #888;
            margin-top: 4px;
        }

        .ecm-engagement-btn:hover {
            border-color: #464646;
        }

        .ecm-engagement-btn.active {
            border-color: #464646;
            background: #464646;
        }

        .ecm-engagement-btn.active .ecm-eng-name {
            color: #fff;
        }

        /* ¿Desde hace cuánto nos conoce el cliente? choice cards */
        .ecm-known-us-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 8px;
        }
        @media (max-width: 600px) {
            .ecm-known-us-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .ecm-known-us-btn {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 14px;
            cursor: pointer;
            background: #fff;
            transition: all 0.15s;
            text-align: left;
            font-family: inherit;
        }
        .ecm-known-us-btn:hover { border-color: #464646; }
        .ecm-known-us-btn.active {
            border-color: #464646;
            background: #f5f1e8;
        }
        .ecm-known-us-btn .ecmku-title {
            font-weight: 700;
            font-size: 0.88rem;
            color: #222;
            margin-bottom: 4px;
            display: block;
        }
        .ecm-known-us-btn .ecmku-desc {
            font-size: 0.76rem;
            color: #888;
            line-height: 1.35;
            display: block;
        }

        .ecm-comments-group {
            margin-bottom: 24px;
        }

        .ecm-comments-group h6 {
            font-weight: 700;
            color: #464646;
            margin-bottom: 12px;
        }

        .ecm-comment-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #f8f8f8;
            padding: 12px 14px;
            min-height: 84px;
        }

        .ecm-comment-card-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #555;
            margin-bottom: 6px;
        }

        .ecm-comment-card-text {
            font-size: 0.9rem;
            color: #333;
            white-space: pre-wrap;
            word-break: break-word;
        }

        #editClientModal .modal-footer {
            display: flex;
            gap: 12px;
            padding: 20px 32px 28px;
            border-top: 1px solid #eee;
        }

        #editClientModal .ecm-btn-cancel {
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

        #editClientModal .ecm-btn-cancel:hover {
            background: #f5f5f5;
        }

        #editClientModal .ecm-btn-submit {
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

        #editClientModal .ecm-btn-submit:hover {
            background: #5a5a5a;
            color: #eee8dc;
        }

        @media (max-width: 600px) {
            #editClientModal #sfSessionBody {
                padding: 20px 16px;
            }

            #editClientModal .modal-header {
                padding: 18px 16px;
            }

            #editClientModal .modal-footer {
                padding: 16px;
            }

            .ecm-grid-2,
            .ecm-grid-3 {
                grid-template-columns: 1fr;
            }
        }

        /* ── STATUS PILLS ── */
        .clie-status-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        .clie-status-agendado { background: rgba(59,130,246,0.10);  color: #1d4ed8; }
        .clie-status-atendido { background: rgba(16,185,129,0.10);  color: #047857; }
        .clie-status-fantasma { background: rgba(239,68,68,0.10);   color: #b91c1c; }
        .clie-status-muerto   { background: rgba(100,116,139,0.10); color: #475569; }
        .clie-status-cliente  { background: rgba(197,160,40,0.12);  color: #92700c; }
        .clie-status-default  { background: rgba(197,160,40,0.12);  color: #92700c; }

        /* ── ENGAGEMENT PILLS ── */
        .clie-eng-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        .clie-eng-low  { background: rgba(245,158,11,0.12); color: #B45309; }
        .clie-eng-mid  { background: rgba(59,130,246,0.12); color: #1d4ed8; }
        .clie-eng-high { background: rgba(16,185,129,0.12); color: #047857; }

        /* ── NAME CELL ── */
        .clie-td-name { font-weight: 600; font-size: 13px; }
    </style>
</head>

<body>
    <div class="page-heading " style="margin-top:30px">
        <div class="page-title mb-4">
            <div class="row">
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <h3>Leads Postcalificados</h3>
                    <p class="text-muted">Gestión y seguimiento de leads postcalificados</p>
                    <div>
                        <button id="btnAddClient" class="btn btn-success ms-3">Agregar Cliente</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtro, cards y grafica eliminados en esta vista según petición -->

        <section class="section">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Lista de Leads</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="leadsTable" class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>¿Dónde se casa?</th>
                                    <th>¿Cuándo se casa?</th>
                                    <th>Ciudad de origen del cliente</th>
                                    <th>¿Cuándo llegó?</th>
                                    <th>Fecha que se cerró</th>
                                    <th>Monto de la venta</th>
                                    <th>Puntos</th>
                                    <th>¿Qué se les vendió?</th>
                                    <th>Método de contacto</th>
                                    <th>Desde cuándo nos conoce</th>
                                    <th>De dónde nos conoce el cliente</th>
                                    <th>Engagement</th>
                                    <th>Estatus</th>
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

                                        <!-- Nombre -->
                                        <td><div class="clie-td-name"><?php echo htmlspecialchars($lead['full_name'] ?? ''); ?></div></td>

                                        <!-- ¿Dónde se casa? -->
                                        <td><?php
                                            $wl = trim((string)($lead['wedding_location'] ?? ''));
                                            echo htmlspecialchars($wl !== '' && $wl !== 'N/A' ? $wl : '—', ENT_QUOTES, 'UTF-8');
                                        ?></td>

                                        <!-- ¿Cuándo se casa? -->
                                        <td><?php
                                            $wd = trim((string)($lead['wedding_date'] ?? ''));
                                            echo htmlspecialchars(formatLeadDate($wd), ENT_QUOTES, 'UTF-8');
                                        ?></td>

                                        <!-- Ciudad de origen -->
                                        <td><?php
                                            $city = trim((string)($lead['city'] ?? ''));
                                            echo htmlspecialchars($city !== '' ? $city : '—', ENT_QUOTES, 'UTF-8');
                                        ?></td>

                                        <!-- ¿Cuándo llegó? -->
                                        <?php
                                        $llegadaTs  = 0;
                                        $llegadaRaw = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
                                        if (!empty($llegadaRaw)) $llegadaTs = strtotime($llegadaRaw) ?: 0;
                                        ?>
                                        <td data-order="<?php echo intval($llegadaTs); ?>">
                                            <?php echo htmlspecialchars(formatCreatedTime($llegadaRaw)); ?>
                                        </td>

                                        <!-- Fecha que se cerró -->
                                        <?php
                                        $fechaCierreRaw = $lead['fecha_cambio_cliente'] ?? '';
                                        $fechaCierreTs  = (!empty($fechaCierreRaw) && strtotime($fechaCierreRaw) !== false) ? intval(strtotime($fechaCierreRaw)) : 0;
                                        $fechaCierreOut = $fechaCierreTs > 0 ? htmlspecialchars(formatCreatedTime($fechaCierreRaw)) : '—';
                                        ?>
                                        <td data-order="<?php echo $fechaCierreTs; ?>"><?php echo $fechaCierreOut; ?></td>

                                        <!-- Monto de la venta -->
                                        <?php
                                        $montoRaw = trim((string)($lead['monto_venta'] ?? ''));
                                        $montoOrder = 0;
                                        $montoOut = '—';
                                        if ($montoRaw !== '') {
                                            $montoNumeric = floatval(preg_replace('/[^0-9\.\-]/', '', $montoRaw));
                                            if ($montoRaw === '0' || $montoRaw === '0.0' || $montoNumeric !== 0.0) $montoOrder = $montoNumeric;
                                            $montoOut = htmlspecialchars($montoRaw, ENT_QUOTES, 'UTF-8');
                                        }
                                        ?>
                                        <td data-order="<?php echo $montoOrder; ?>"><?php echo $montoOut; ?></td>

                                        <!-- Puntos -->
                                        <?php
                                        $puntosRaw = trim((string)($lead['puntos'] ?? ''));
                                        $puntosOrder = 0;
                                        $puntosOut = '—';
                                        if ($puntosRaw !== '') {
                                            $puntosNumeric = floatval(preg_replace('/[^0-9\.\-]/', '', $puntosRaw));
                                            if ($puntosRaw === '0' || $puntosRaw === '0.0' || $puntosNumeric !== 0.0) $puntosOrder = $puntosNumeric;
                                            $puntosOut = htmlspecialchars($puntosRaw, ENT_QUOTES, 'UTF-8');
                                        }
                                        ?>
                                        <td data-order="<?php echo $puntosOrder; ?>"><?php echo $puntosOut; ?></td>

                                        <!-- ¿Qué se les vendió? -->
                                        <?php
                                        $vendidoRaw = trim((string)($lead['que_se_les_vendio'] ?? ''));
                                        $vendidoOut = $vendidoRaw !== '' ? htmlspecialchars($vendidoRaw, ENT_QUOTES, 'UTF-8') : '—';
                                        ?>
                                        <td><?php echo $vendidoOut; ?></td>

                                        <!-- Método de contacto -->
                                        <td><?php echo htmlspecialchars(normalizeFirstContactChannelLabel($lead['first_contact_channel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>

                                        <!-- Desde cuándo nos conoce -->
                                        <td><?php
                                            $knu    = trim((string)($lead['how_long_known_us'] ?? ''));
                                            $knuMap = [
                                                'less than 3 months'          => 'Menos de 6 meses',
                                                'less than 6 months'          => 'Menos de 6 meses',
                                                'between 3 months and 1 year' => 'Más de 6 meses',
                                                'more than 6 months'          => 'Más de 6 meses',
                                                'more than 1 year'            => 'Más de 6 meses',
                                                'not asked'                   => 'Menos de 6 meses',
                                            ];
                                            $knuLabel = ($knu !== '' && $knu !== '—') ? ($knuMap[mb_strtolower($knu, 'UTF-8')] ?? $knu) : '—';
                                            echo htmlspecialchars($knuLabel, ENT_QUOTES, 'UTF-8');
                                        ?></td>

                                        <!-- De dónde nos conoce -->
                                        <td><?php echo htmlspecialchars(getOrigenCategoriaLabel($lead)); ?></td>

                                        <!-- Engagement -->
                                        <td><?php
                                            $engRaw   = trim((string)($lead['engagement'] ?? ''));
                                            $engNorm  = mb_strtolower($engRaw, 'UTF-8');
                                            $engClass = 'clie-eng-low';
                                            if ($engNorm === '3' || $engNorm === 'alto')      $engClass = 'clie-eng-high';
                                            elseif ($engNorm === '2' || $engNorm === 'medio') $engClass = 'clie-eng-mid';
                                            $engLabel = getEngagementLabelWithEmoji($lead);
                                            if ($engLabel !== 'N/A' && $engLabel !== 'Sin dato') {
                                                echo '<span class="clie-eng-pill ' . $engClass . '">' . htmlspecialchars($engLabel) . '</span>';
                                            } else {
                                                echo '<span style="color:#94a3b8;">' . htmlspecialchars($engLabel) . '</span>';
                                            }
                                        ?></td>

                                        <!-- Estatus -->
                                        <?php
                                        $statusRaw     = isset($lead['estatus']) ? trim((string)$lead['estatus']) : '';
                                        $statusLower   = $statusRaw !== '' ? mb_strtolower($statusRaw, 'UTF-8') : '';
                                        $statusDisplay = $statusRaw !== '' ? ucfirst($statusLower) : '—';
                                        $statusClass   = 'clie-status-default';
                                        if      ($statusLower === 'agendado') $statusClass = 'clie-status-agendado';
                                        elseif  ($statusLower === 'atendido') $statusClass = 'clie-status-atendido';
                                        elseif  ($statusLower === 'fantasma') $statusClass = 'clie-status-fantasma';
                                        elseif  ($statusLower === 'muerto')   $statusClass = 'clie-status-muerto';
                                        elseif  ($statusLower === 'cliente')  $statusClass = 'clie-status-cliente';
                                        ?>
                                        <td>
                                            <span class="clie-status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusDisplay); ?></span>
                                        </td>

                                        <!-- Sesión oficial (hidden) -->
                                        <td style="display:none">
                                            <select class="form-select form-select-sm lead-update"
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
                                        <!-- Paquete -->
                                        <td><?php
                                            $paqRaw = trim((string)($lead['paquete'] ?? $lead['paquete_cotizado'] ?? ''));
                                            if ($paqRaw !== '' && ctype_digit($paqRaw) && isset($paquetesMap[intval($paqRaw)])) {
                                                $paqName = $paquetesMap[intval($paqRaw)];
                                            } else {
                                                $paqName = $paqRaw;
                                            }
                                            echo $paqName !== '' ? htmlspecialchars($paqName, ENT_QUOTES, 'UTF-8') : '<span style="color:#94a3b8;">—</span>';
                                        ?></td>
                                        <?php endif; ?>

                                        <!-- Compromiso (hidden) -->
                                        <td style="display:none">
                                            <select class="form-select form-select-sm lead-update"
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
                                            <select class="form-select form-select-sm lead-update"
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
                                                <button class="btn btn-primary btn-sm me-1"
                                                    onclick="editClient(<?php echo intval($lead['id']); ?>)">
                                                    Editar
                                                </button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Modal para ver más detalles -->
    <div class="modal fade" id="verMasModal" tabindex="-1" aria-labelledby="verMasModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verMasModalLabel">Detalles del Lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- El contenido se llenará con JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para listar comentarios -->
    <div class="modal fade" id="comentariosModal" tabindex="-1" aria-labelledby="comentariosModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="comentariosModalLabel">Comentarios del vendedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="comentariosModalBody">
                    <!-- Contenido generado dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar cliente -->
    <div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editClientModalLabel">Editar Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="sfSessionBody">
                    <ul class="nav nav-pills mb-3" id="editClientTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="edit-client-data-tab" data-bs-toggle="pill" data-bs-target="#edit-client-data-pane" type="button" role="tab" aria-controls="edit-client-data-pane" aria-selected="true">Datos</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="edit-client-comments-tab" data-bs-toggle="pill" data-bs-target="#edit-client-comments-pane" type="button" role="tab" aria-controls="edit-client-comments-pane" aria-selected="false">Comentarios</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="editClientTabContent">
                        <div class="tab-pane fade show active" id="edit-client-data-pane" role="tabpanel" aria-labelledby="edit-client-data-tab">
                    <form id="editClientForm">
                        <input type="hidden" id="editClientId" name="id" value="">
                        <input type="hidden" id="edit_tipo_cliente" name="tipo_cliente" value="">
                        <input type="hidden" id="edit_how_did_you_meet" name="how_did_you_meet" value="">
                        <input type="hidden" id="edit_engagement" name="engagement" value="">
                        <input type="hidden" id="edit_how_long_known_us" name="how_long_known_us" value="">

                        <div class="ecm-section">
                            <div class="ecm-section-title">
                                <div class="ecm-section-number">1</div>
                                <div class="ecm-section-heading">
                                    <h5>Datos del cliente</h5>
                                    <p>Actualiza los datos principales y de contacto del cliente.</p>
                                </div>
                            </div>

                            <div class="ecm-field" style="margin-bottom: 12px;">
                                <label>Nombre</label>
                                <input type="text" id="edit_name" name="names" class="form-control">
                            </div>

                            <div class="ecm-grid-2">
                                <div class="ecm-field">
                                    <label>Correo electrónico</label>
                                    <input type="email" id="edit_email_address" name="email_address" class="form-control" placeholder="correo@ejemplo.com">
                                </div>
                                <div class="ecm-field">
                                    <label>WhatsApp / Teléfono</label>
                                    <input type="text" id="edit_telephone" name="telephone" class="form-control" placeholder="+52 000 000 0000">
                                </div>
                                <div class="ecm-field">
                                    <label>Fecha de la boda</label>
                                    <input type="date" id="edit_wedding_date" name="wedding_date" class="form-control">
                                </div>
                                <div class="ecm-field">
                                    <label>Campaña (opcional)</label>
                                    <input type="text" id="edit_campaign_name" name="campaign_name" class="form-control" placeholder="Opcional (dejar vacío si no aplica)">
                                </div>
                                <div class="ecm-field">
                                    <label>Origen</label>
                                    <select id="edit_desde_publicidad" name="desde_publicidad" class="form-select">
                                        <option value="0">Website</option>
                                        <option value="1">Publicidad</option>
                                        <option value="2">Instagram Orgánico</option>
                                        <option value="3">Whatsapp</option>
                                        <option value="4">Correo electrónico</option>
                                    </select>
                                </div>
                                <div class="ecm-field">
                                    <label>Ubicación de la boda</label>
                                    <input type="text" id="edit_wedding_location" name="wedding_location" class="form-control">
                                </div>
                                <div class="ecm-field">
                                    <label>Ciudad de origen del cliente</label>
                                    <input type="text" id="edit_city" name="city" class="form-control" placeholder="Ej. Ciudad de México">
                                </div>
                                <div class="ecm-field">
                                    <label>Vendedor asignado</label>
                                    <select id="edit_id_vendedor_asignado" name="id_vendedor_asignado" class="form-select">
                                        <option value="0">-- Sin asignar --</option>
                                        <?php foreach ($vendedores as $v): ?>
                                            <option value="<?php echo intval($v['id']); ?>"><?php echo htmlspecialchars($v['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="ecm-field">
                                    <label>Método de contacto (Primer canal)</label>
                                    <select id="edit_first_contact_channel" name="first_contact_channel" class="form-select">
                                        <option value="">Seleccionar...</option>
                                        <option value="WhatsApp">WhatsApp</option>
                                        <option value="IG">IG</option>
                                        <option value="Facebook">Facebook</option>
                                        <option value="Email">Correo electrónico</option>
                                        <option value="Phone call">Llamada telefónica</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="ecm-section">
                            <div class="ecm-section-title">
                                <div class="ecm-section-number">&#9733;</div>
                                <div class="ecm-section-heading">
                                    <h5>Tipo de cliente</h5>
                                    <p>¿Es un Wedding Planner o un Cliente Final?</p>
                                </div>
                            </div>
                            <div class="ecm-category-btns">
                                <button type="button" class="ecm-tipo-cliente-btn" data-tipo="1">
                                    <span class="ecm-cat-icon">💼</span>Wedding Planner
                                </button>
                                <button type="button" class="ecm-tipo-cliente-btn" data-tipo="0">
                                    <span class="ecm-cat-icon">👫</span>Cliente Final
                                </button>
                            </div>
                        </div>

                        <div class="ecm-section">
                            <div class="ecm-section-title">
                                <div class="ecm-section-number">2</div>
                                <div class="ecm-section-heading">
                                    <h5>Engagement del cliente</h5>
                                    <p>Tu percepción después de la sesión. Sé honesta.</p>
                                </div>
                            </div>

                            <div class="ecm-engagement-btns">
                                <button type="button" class="ecm-engagement-btn" data-eng="1">
                                    <span class="ecm-eng-icon">😑</span>
                                    <span class="ecm-eng-name">Bajo</span>
                                    <div class="ecm-eng-desc">Poco entusiasmo, muchas dudas o precio</div>
                                </button>
                                <button type="button" class="ecm-engagement-btn" data-eng="2">
                                    <span class="ecm-eng-icon">😃</span>
                                    <span class="ecm-eng-name">Medio</span>
                                    <div class="ecm-eng-desc">Interés real, pero falta de decisión</div>
                                </button>
                                <button type="button" class="ecm-engagement-btn" data-eng="3">
                                    <span class="ecm-eng-icon">🔥</span>
                                    <span class="ecm-eng-name">Alto</span>
                                    <div class="ecm-eng-desc">Muy interesados, listos para cerrar</div>
                                </button>
                            </div>
                        </div>

                        <div class="ecm-section">
                            <div class="ecm-section-title">
                                <div class="ecm-section-number">4</div>
                                <div class="ecm-section-heading">
                                    <h5>¿Desde hace cuánto nos conoce el cliente? (refuerzo)</h5>
                                    <p>Confirma directamente con el cliente durante la sesión.</p>
                                </div>
                            </div>
                            <div class="ecm-known-us-grid" id="ecm-known-us-grid">
                                <button type="button" class="ecm-known-us-btn" data-val="Less than 6 months">
                                    <span class="ecmku-title">Menos de 6 meses</span>
                                    <span class="ecmku-desc">Nos conociste recientemente.</span>
                                </button>
                                <button type="button" class="ecm-known-us-btn" data-val="More than 6 months">
                                    <span class="ecmku-title">Más de 6 meses</span>
                                    <span class="ecmku-desc">Ya tenías un tiempo siguiéndonos.</span>
                                </button>
                            </div>
                        </div>

                        <div class="ecm-section">
                            <div class="ecm-section-title">
                                <div class="ecm-section-number">5</div>
                                <div class="ecm-section-heading">
                                    <h5>Datos de cierre de venta</h5>
                                    <p>Información del cierre y monto de venta.</p>
                                </div>
                            </div>

                            <div class="ecm-field" style="margin-bottom: 12px;">
                                <label>Paquete de interés</label>
                                <div id="edit_package_list" class="ecm-package-list"></div>
                            </div>
                            <input type="hidden" id="edit_paquete" name="paquete" value="">

                            <div class="ecm-field" style="margin-top: 12px;">
                                <label>¿Qué se les vendió?</label>
                                <input type="text" id="edit_que_se_les_vendio" name="que_se_les_vendio" class="form-control" placeholder="Ej. 1 Foto 1 Video">
                            </div>

                            <div class="ecm-grid-3" style="margin-top: 12px;">
                                <div class="ecm-field">
                                    <label>Monto de venta</label>
                                    <input type="number" step="0.01" id="edit_monto_venta" name="monto_venta" class="form-control">
                                </div>
                                <div class="ecm-field">
                                    <label>Puntos</label>
                                    <input type="number" step="0.01" id="edit_puntos" name="puntos" class="form-control">
                                </div>
                                <div class="ecm-field">
                                    <label>Fecha que se cerró</label>
                                    <input type="date" id="edit_fecha_cambio_cliente" name="fecha_cambio_cliente" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="ecm-section" style="margin-bottom: 0;">
                            <div class="ecm-section-title">
                                <div class="ecm-section-number">3</div>
                                <div class="ecm-section-heading">
                                    <h5>Control interno</h5>
                                    <p>Fecha de registro del cliente.</p>
                                </div>
                            </div>
                            <div class="ecm-field">
                                <label>Fecha de registro (fecha y hora)</label>
                                <input type="datetime-local" id="edit_submission_date" name="submission_date" class="form-control">
                            </div>
                        </div>

                    </form>
                        </div>
                        <div class="tab-pane fade" id="edit-client-comments-pane" role="tabpanel" aria-labelledby="edit-client-comments-tab">
                            <div id="editCommentsContainer" class="pt-2">
                                <p class="text-muted mb-0">Sin comentarios disponibles.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ecm-btn-cancel" data-bs-dismiss="modal">Cerrar</button>
                    <button id="saveEditClientBtn" type="button" class="ecm-btn-submit">Guardar cambios</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para agregar cliente -->
    <div class="modal fade" id="addClientModal" tabindex="-1" aria-labelledby="addClientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addClientModalLabel">Agregar Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="addClientForm">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nombre</label>
                                <input type="text" id="add_name" name="names" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Email</label>
                                <input type="email" id="add_email" name="email_address" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" id="add_telephone" name="telephone" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Vendedor asignado</label>
                                <select id="add_id_vendedor_asignado" name="id_vendedor_asignado" class="form-select">
                                    <option value="0">-- Sin asignar --</option>
                                    <?php foreach ($vendedores as $v): ?>
                                        <option value="<?php echo intval($v['id']); ?>"><?php echo htmlspecialchars($v['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">País Código</label>
                                <select id="add_country_code" name="country_code" class="form-select"></select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Wedding Date</label>
                                <input type="date" class="form-control" id="add_wedding_date" name="wedding_date">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Fecha de registro (fecha y hora)</label>
                                <input type="datetime-local" class="form-control" id="add_submission_date" name="submission_date">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button id="addClientSaveBtn" type="button" class="btn btn-primary">Agregar</button>
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
        const packageStyles = <?php echo json_encode($packageStyles); ?>;
        const paquetesData = <?php echo json_encode($paquetesArr); ?>;

        // Detect the column index for "Fecha que se cerró" (dynamic since some columns are hidden by user type)
        var dateColIndex = $('#leadsTable thead th').filter(function () {
            return $(this).text().trim().toLowerCase().indexOf('fecha que se cerr') !== -1; // ignore accents
        }).index();
        if (typeof dateColIndex === 'undefined' || dateColIndex < 0) dateColIndex = 0; // fallback

        var leadsTableDT = $('#leadsTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            // Default ordering: por fecha que se cerró (mas reciente primero)
            order: [[dateColIndex, 'desc']],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            responsive: true,
            // ColumnDefs: keep all columns orderable, no special numeric type needed
            columnDefs: [
                { orderable: true, targets: '_all' }
            ]
        });

        // Highcharts removed: no trend chart on this view

        // Escapa texto para evitar XSS
        function escapeHtml(unsafe) {
            if (!unsafe && unsafe !== 0) return '';
            return String(unsafe)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Aplica la(s) clase(s) de la opción seleccionada al elemento <select>
        function applyOptionClass($el) {
            if (!$el || !$el.length) return;
            // quitar clases option1..option8 si existen
            $el.removeClass('option1 option2 option3 option4 option5 option6 option7 option8');
            var cls = $el.find('option:selected').attr('class');
            if (cls) {
                // puede contener múltiples clases, solo aplicarlas
                $el.addClass(cls);
            }
        }

        function renderEditPackageList(selectedPackageName) {
            var list = $('#edit_package_list');
            if (!list.length) return;
            list.empty();

            var selected = (selectedPackageName || '').toString().trim().toLowerCase();
            if (!paquetesData || !paquetesData.length) {
                list.append('<div class="text-muted">Sin paquetes disponibles.</div>');
                return;
            }

            paquetesData.forEach(function (p) {
                var nombre = (p && p.nombre ? String(p.nombre) : '').trim();
                if (!nombre) return;
                var active = (selected && selected === nombre.toLowerCase()) ? ' active' : '';
                var item = '';
                item += '<div class="ecm-package-item' + active + '" data-paq-name="' + escapeHtml(nombre) + '">';
                item += '  <div class="ecm-package-name">' + escapeHtml(nombre) + '</div>';
                item += '  <div class="ecm-package-dot"></div>';
                item += '</div>';
                list.append(item);
            });
        }

        function updateEcmAutoHowDidYouMeet() {
            var tipoCliente = String($('#edit_tipo_cliente').val() || '');
            var howLong     = String($('#edit_how_long_known_us').val() || '');
            var origin = '';
            if (tipoCliente === '1') {
                origin = '1'; // Wedding Planner
            } else if (tipoCliente === '0') {
                if (howLong === 'Less than 6 months') {
                    origin = '3'; // New Audience
                } else if (howLong === 'More than 6 months') {
                    origin = '2'; // Community
                }
            }
            $('#edit_how_did_you_meet').val(origin);
        }

        $(document).on('click', '.ecm-package-item', function () {
            $('.ecm-package-item').removeClass('active');
            $(this).addClass('active');
            var selectedPack = $(this).attr('data-paq-name') || '';
            $('#edit_paquete').val(selectedPack);
            $('#edit_que_se_les_vendio').val(selectedPack);
        });

        $(document).on('click', '.ecm-engagement-btn', function () {
            $('.ecm-engagement-btn').removeClass('active');
            $(this).addClass('active');
            $('#edit_engagement').val($(this).data('eng') || '');
        });

        $(document).on('click', '.ecm-known-us-btn', function () {
            $('.ecm-known-us-btn').removeClass('active');
            $(this).addClass('active');
            $('#edit_how_long_known_us').val($(this).data('val') || '');
            updateEcmAutoHowDidYouMeet();
        });

        $(document).on('click', '.ecm-tipo-cliente-btn', function () {
            $('.ecm-tipo-cliente-btn').removeClass('active');
            $(this).addClass('active');
            $('#edit_tipo_cliente').val(String($(this).data('tipo')));
            updateEcmAutoHowDidYouMeet();
        });

        // Obtener comentarios, mostrarlos en modal y permitir agregar desde el modal
        window.verComentarios = function verComentarios(id) {
            Swal.fire({
                title: 'Cargando comentarios...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            $.ajax({
                url: 'actualizar_lead.php',
                method: 'POST',
                dataType: 'json',
                data: { action: 'get_comments', id: id },
                success: function (resp) {
                    Swal.close();
                    if (resp && resp.success) {
                        const comments = resp.comments || [];
                        let html = '';
                        html += '<div id="commentsList">';
                        if (!comments.length) {
                            html += '<p>No hay comentarios aún.</p>';
                        } else {
                            html += '<div class="list-group mb-3">';
                            comments.forEach(function (c) {
                                const when = escapeHtml(c.created_at || '');
                                const author = escapeHtml(c.author || 'Vendedor');
                                const text = escapeHtml(c.text || '');
                                html += '<div class="list-group-item">';
                                html += '<div class="d-flex w-100 justify-content-between">';
                                html += '<h6 class="mb-1">' + author + '</h6>';
                                html += '<small>' + when + '</small>';
                                html += '</div>';
                                html += '<p class="mb-1">' + text + '</p>';
                                html += '</div>';
                            });
                            html += '</div>';
                        }
                        html += '</div>';

                        // textarea para agregar nuevo comentario
                        html += '<div class="mb-3">';
                        html += '<label class="form-label">Agregar comentario</label>';
                        html += '<textarea id="newCommentText" class="form-control" rows="4" placeholder="Escribe un comentario..."></textarea>';
                        html += '</div>';
                        html += '<div class="text-end"><button id="saveCommentBtn" class="btn btn-primary">Guardar comentario</button></div>';

                        document.getElementById('comentariosModalBody').innerHTML = html;
                        var modal = new bootstrap.Modal(document.getElementById('comentariosModal'));
                        modal.show();

                        // Handler para guardar comentario sin recargar
                        $('#saveCommentBtn').on('click', function () {
                            var comment = $('#newCommentText').val();
                            if (!comment || !comment.trim()) {
                                Swal.fire('Error', 'El comentario no puede estar vacío', 'error');
                                return;
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
                                        // actualizar la lista de comentarios en el modal
                                        const commentsNew = resp2.comments || [];
                                        let listHtml = '';
                                        if (!commentsNew.length) {
                                            listHtml = '<p>No hay comentarios aún.</p>';
                                        } else {
                                            listHtml = '<div class="list-group mb-3">';
                                            commentsNew.forEach(function (c) {
                                                const when = escapeHtml(c.created_at || '');
                                                const author = escapeHtml(c.author || 'Vendedor');
                                                const text = escapeHtml(c.text || '');
                                                listHtml += '<div class="list-group-item">';
                                                listHtml += '<div class="d-flex w-100 justify-content-between">';
                                                listHtml += '<h6 class="mb-1">' + author + '</h6>';
                                                listHtml += '<small>' + when + '</small>';
                                                listHtml += '</div>';
                                                listHtml += '<p class="mb-1">' + text + '</p>';
                                                listHtml += '</div>';
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
                                complete: function () {
                                    $btn.prop('disabled', false);
                                }
                            });
                        });
                    } else {
                        Swal.fire('Error', resp.message || 'No se pudieron obtener los comentarios', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                }
            });
        }


        // Handle inline updates for selects and text inputs
        // When a select changes
        // Apply classes initially
        $('.lead-update').each(function () { applyOptionClass($(this)); });

        $(document).on('change', '.lead-update', function () {
            var el = $(this);
            var id = el.data('id');
            var field = el.data('field');
            var value = el.val();

            // optimistic UI feedback
            var originalBg = el.closest('td').css('background-color');
            el.prop('disabled', true);

            $.ajax({
                url: 'actualizar_lead.php',
                method: 'POST',
                dataType: 'json',
                data: { id: id, field: field, value: value },
                success: function (resp) {
                    if (resp && resp.success) {
                        // flash green
                        el.closest('td').css('background', '#e6ffed');
                        setTimeout(function () { el.closest('td').css('background', ''); }, 800);
                        // aplicar clase de la opción seleccionada al select
                        applyOptionClass(el);
                    } else {
                        Swal.fire('Error', resp.message || 'No se pudo actualizar', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                },
                complete: function () {
                    el.prop('disabled', false);
                }
            });
        });

        // Get column index by header text (case-insensitive contains)
        function getColIndex(headerText) {
            var idx = -1;
            $('#leadsTable thead th').each(function (i) {
                if ($(this).text().trim().toLowerCase().indexOf(headerText.toLowerCase()) !== -1) {
                    idx = i;
                    return false;
                }
            });
            return idx;
        }

        // Convert MySQL datetime or date string to HTML date input value (YYYY-MM-DD)
        function toDateInput(mysqlDate) {
            if (!mysqlDate) return '';
            // mysqlDate may be 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS'
            var dpart = mysqlDate.split(' ')[0];
            // Validate
            if (!/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(dpart)) return '';
            return dpart;
        }

        // Convert MySQL datetime (YYYY-MM-DD HH:MM:SS) to HTML datetime-local value (YYYY-MM-DDTHH:MM)
        function toDateTimeInput(mysqlDatetime) {
            if (!mysqlDatetime) return '';
            // Treat zero-dates as empty
            if (typeof mysqlDatetime === 'string' && mysqlDatetime.indexOf('0000-00-00') === 0) return '';
            var s = mysqlDatetime.replace(' ', 'T');
            // Keep YYYY-MM-DDTHH:MM (drop seconds)
            var m = s.match(/^([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2})(:[0-9]{2})?$/);
            if (m) return m[1];
            var d = new Date(s);
            if (isNaN(d.getTime())) return '';
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        }

        function fromDateTimeInputToMysql(datetimeLocal) {
            if (!datetimeLocal) return '';
            var v = datetimeLocal.replace('T', ' ');
            if (/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$/.test(v)) v = v + ':00';
            return v;
        }

        // Convert MySQL datetime (YYYY-MM-DD HH:MM:SS) to HTML datetime-local value (YYYY-MM-DDTHH:MM)
        function toDateTimeInput(mysqlDatetime) {
            if (!mysqlDatetime) return '';
            // Accept 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS' or 'YYYY-MM-DDTHH:MM:SS'
            var s = mysqlDatetime.replace(' ', 'T');
            // Drop seconds if present
            var m = s.match(/^([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2})(:[0-9]{2})?$/);
            if (m) return m[1];
            // Try to parse and format
            var d = new Date(s);
            if (isNaN(d.getTime())) return '';
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        }

        function fromDateTimeInputToMysql(datetimeLocal) {
            if (!datetimeLocal) return '';
            // datetimeLocal is 'YYYY-MM-DDTHH:MM' or 'YYYY-MM-DDTHH:MM:SS'
            var v = datetimeLocal.replace('T', ' ');
            if (/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$/.test(v)) v = v + ':00';
            return v;
        }

        function fromDateInputToMysql(dateInput) {
            if (!dateInput) return '';
            // dateInput already in YYYY-MM-DD; return as-is (server accepts date string)
            return dateInput;
        }

        function formatDateSpanish(mysqlDatetime) {
            if (!mysqlDatetime) return 'sin datos';
            // Support 'YYYY-MM-DD' and 'YYYY-MM-DD HH:MM:SS' or 'YYYY-MM-DDTHH:MM:SS'
            var ds = mysqlDatetime;
            if (/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(ds)) {
                ds = ds + 'T00:00:00';
            } else {
                ds = ds.replace(' ', 'T');
            }
            var d = new Date(ds);
            if (isNaN(d.getTime())) return 'sin datos';
            var months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
            return d.getDate() + ' de ' + months[d.getMonth()] + ' de ' + d.getFullYear();
        }

        function renderEditCommentsTab(client) {
            var container = $('#editCommentsContainer');
            if (!container.length) return;

            var comments = (client && client.related_comments) ? client.related_comments : {};
            var groups = [
                {
                    title: 'Wedding Planner',
                    items: [
                        { label: 'Comentario al pasar a atendido', value: comments.wp_initial || '' },
                        { label: 'Comentario al pasar a cliente', value: comments.wp_client || '' }
                    ]
                },
                {
                    title: 'Agenda',
                    items: [
                        { label: 'Comentario al pasar a atendido', value: comments.agenda_initial || '' },
                        { label: 'Comentario al pasar a cliente', value: comments.agenda_client || '' }
                    ]
                }
            ];

            var html = '';
            var hasAnyComment = false;

            groups.forEach(function (group) {
                var validItems = group.items.filter(function (item) {
                    return item.value && String(item.value).trim() !== '';
                });

                if (!validItems.length) return;
                hasAnyComment = true;

                html += '<div class="ecm-comments-group">';
                html += '<h6>' + escapeHtml(group.title) + '</h6>';
                html += '<div class="ecm-grid-2">';

                validItems.forEach(function (item) {
                    html += '<div class="ecm-comment-card">';
                    html += '<div class="ecm-comment-card-title">' + escapeHtml(item.label) + '</div>';
                    html += '<div class="ecm-comment-card-text">' + escapeHtml(item.value) + '</div>';
                    html += '</div>';
                });

                html += '</div>';
                html += '</div>';
            });

            if (!hasAnyComment) {
                html = '<p class="text-muted mb-0">Sin comentarios disponibles.</p>';
            }

            container.html(html);
        }

        // Open edit modal: fetch latest client info then open the modal
        window.editClient = function editClient(id) {
            Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            $.ajax({
                url: 'actualizar_lead.php',
                method: 'POST',
                dataType: 'json',
                data: { action: 'get_client', id: id },
                success: function (resp) {
                    Swal.close();
                    if (!resp || !resp.success) {
                        Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo obtener el cliente', 'error');
                        return;
                    }
                    var client = resp.client;
                    $('#editClientId').val(client.id);
                    $('#edit_name').val(client.names || '');
                    $('#edit_email_address').val(client.email_address || '');
                    $('#edit_telephone').val(client.telephone || '');
                    $('#edit_wedding_date').val(toDateInput(client.wedding_date || ''));
                    $('#edit_campaign_name').val(client.campaign_name || '');
                    // Set origin select if provided by server
                    try { $('#edit_desde_publicidad').val(typeof client.desde_publicidad !== 'undefined' ? String(client.desde_publicidad) : '0'); } catch (e) {}
                    $('#edit_wedding_location').val(client.wedding_location || '');
                    $('#edit_city').val(client.city || '');
                    // Set vendedor asignado
                    $('#edit_id_vendedor_asignado').val(client.id_vendedor_asignado || '0');
                    $('#edit_first_contact_channel').val(client.first_contact_channel || '');
                    var selectedPack = (client.que_se_les_vendio || client.paquete || '');
                    $('#edit_paquete').val(client.paquete || selectedPack || '');
                    $('#edit_que_se_les_vendio').val(client.que_se_les_vendio || selectedPack || '');
                    renderEditPackageList(selectedPack || '');
                    $('#edit_engagement').val(client.engagement || '');
                    $('.ecm-engagement-btn').removeClass('active');
                    if (client.engagement !== null && typeof client.engagement !== 'undefined' && String(client.engagement) !== '') {
                        $('.ecm-engagement-btn[data-eng="' + String(client.engagement) + '"]').addClass('active');
                    }
                    $('#edit_how_long_known_us').val(client.how_long_known_us || '');
                    $('.ecm-known-us-btn').removeClass('active');
                    if (client.how_long_known_us) {
                        $('.ecm-known-us-btn[data-val="' + String(client.how_long_known_us) + '"]').addClass('active');
                    }
                    // tipo_cliente
                    var tipoCliente = (client.tipo_cliente !== null && typeof client.tipo_cliente !== 'undefined') ? String(client.tipo_cliente) : '';
                    $('#edit_tipo_cliente').val(tipoCliente);
                    $('.ecm-tipo-cliente-btn').removeClass('active');
                    if (tipoCliente !== '') {
                        $('.ecm-tipo-cliente-btn[data-tipo="' + tipoCliente + '"]').addClass('active');
                    }
                    updateEcmAutoHowDidYouMeet();
                    $('#edit_puntos').val(client.puntos || '');
                    $('#edit_monto_venta').val(client.monto_venta || '');
                    $('#edit_fecha_cambio_cliente').val(toDateInput(client.fecha_cambio_cliente || ''));
                    // submission_date (datetime) - prefer server value, fallback to row data attribute
                    var $row = $('tr[id^="lead-row-' + id + '-"]').first();
                    var fallbackSubmission = $row && $row.length ? $row.attr('data-submission_date') : '';
                    $('#edit_submission_date').val(toDateTimeInput(client.submission_date || fallbackSubmission || ''));
                    renderEditCommentsTab(client);

                    var dataTabTrigger = document.getElementById('edit-client-data-tab');
                    if (dataTabTrigger) {
                        bootstrap.Tab.getOrCreateInstance(dataTabTrigger).show();
                    }

                    var modal = new bootstrap.Modal(document.getElementById('editClientModal'));
                    modal.show();
                },
                error: function () { Swal.close(); Swal.fire('Error', 'Error de comunicación con el servidor', 'error'); }
            });
        }

        // Save edited client
        $('#saveEditClientBtn').on('click', function () {
            var id = parseInt($('#editClientId').val());
            if (!id) { Swal.fire('Error', 'Falta el ID del cliente', 'error'); return; }
            var fields = {
                names: $('#edit_name').val().trim(),
                email_address: $('#edit_email_address').val().trim(),
                telephone: $('#edit_telephone').val().trim(),
                wedding_date: fromDateInputToMysql($('#edit_wedding_date').val().trim()),
                campaign_name: $('#edit_campaign_name').val().trim(),
                desde_publicidad: $('#edit_desde_publicidad').val().trim(),
                how_did_you_meet: $('#edit_how_did_you_meet').val().trim(),
                hear_about_us: $('#edit_hear_about_us').val().trim(),
                engagement: $('#edit_engagement').val().trim(),
                how_long_known_us: $('#edit_how_long_known_us').val().trim(),
                wedding_location: $('#edit_wedding_location').val().trim(),
                city: $('#edit_city').val().trim(),
                paquete: $('#edit_paquete').val().trim(),
                puntos: $('#edit_puntos').val().trim(),
                monto_venta: $('#edit_monto_venta').val().trim(),
                que_se_les_vendio: $('#edit_que_se_les_vendio').val().trim(),
                fecha_cambio_cliente: fromDateInputToMysql($('#edit_fecha_cambio_cliente').val().trim()),
                submission_date: fromDateTimeInputToMysql($('#edit_submission_date').val().trim()),
                id_vendedor_asignado: $('#edit_id_vendedor_asignado').val() || '0',
                first_contact_channel: $('#edit_first_contact_channel').val().trim(),
                tipo_cliente: $('#edit_tipo_cliente').val()
            };

            if (!['1', '2', '3'].includes(fields.how_did_you_meet)) {
                delete fields.how_did_you_meet;
            }

            Swal.fire({ title: 'Guardando cambios...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            $.ajax({
                url: 'actualizar_lead.php',
                method: 'POST',
                dataType: 'json',
                data: { action: 'update_client', id: id, fields: JSON.stringify(fields) },
                success: function (resp) {
                    Swal.close();
                    if (!resp || !resp.success) {
                        Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo guardar', 'error');
                        return;
                    }

                    // Update table row and data attributes
                    var row = $('tr[id^="lead-row-' + id + '-"]').first();
                    if (row && row.length) {
                        row.attr('data-names', fields.names);
                        row.attr('data-campaign_name', fields.campaign_name);
                        row.attr('data-wedding_location', fields.wedding_location);
                        row.attr('data-paquete', fields.paquete);
                        row.attr('data-puntos', fields.puntos);
                        row.attr('data-monto_venta', fields.monto_venta);
                        row.attr('data-que_se_les_vendio', fields.que_se_les_vendio);
                        row.attr('data-id_vendedor_asignado', fields.id_vendedor_asignado);
                        // update origin attribute
                        row.attr('data-desde_publicidad', fields.desde_publicidad || '');
                        row.attr('data-fecha_cambio_cliente', resp.client.fecha_cambio_cliente || '');
                        row.attr('data-submission_date', resp.client.submission_date || row.attr('data-submission_date') || '');
                        row.attr('data-submission_date', resp.client.submission_date || '');

                        // ── helpers for display normalization ──
                        function _normalizeChannel(val) {
                            if (!val || !val.trim()) return 'Sin dato';
                            var k = val.trim().toLowerCase().replace(/[–—]/g, '-').replace(/\s+/g, ' ');
                            var chMap = {
                                'whatsapp': 'WhatsApp', 'email': 'Correo electrónico',
                                'correo electronico': 'Correo electrónico', 'correo electrónico': 'Correo electrónico',
                                'mail': 'Correo electrónico', 'phone call': 'Phone call',
                                'llamada telefonica': 'Phone call', 'llamada telefónica': 'Phone call',
                                'instagram dm - campaign': 'Instagram DM - Campaña',
                                'instagram dm campaign': 'Instagram DM - Campaña',
                                'instagram dm - organic': 'Instagram DM - Orgánico',
                                'instagram dm organic': 'Instagram DM - Orgánico'
                            };
                            return chMap[k] !== undefined ? chMap[k] : val.trim();
                        }
                        function _knuLabel(val) {
                            var k = (val || '').trim().toLowerCase();
                            var knuMap = {
                                'less than 3 months': 'Menos de 3 meses',
                                'between 3 months and 1 year': 'Entre 3 meses y 1 año',
                                'more than 1 year': 'Más de 1 año',
                                'not asked': 'No se preguntó'
                            };
                            return knuMap[k] || (k ? val.trim() : '—');
                        }
                        function _origenLabel(val) {
                            var v = String(val || '').trim();
                            return v === '1' ? 'Wedding Planner' : v === '2' ? 'Community' : v === '3' ? 'New Market' : 'N/A';
                        }
                        function _engHtml(val) {
                            var n = String(val || '').trim().toLowerCase();
                            if (!n || n === '') return '<span style="color:#94a3b8;">N/A</span>';
                            if (n === '0') return '<span style="color:#94a3b8;">Sin dato</span>';
                            if (n === '1' || n === 'bajo')  return '<span class="clie-eng-pill clie-eng-low">😑 Bajo</span>';
                            if (n === '2' || n === 'medio') return '<span class="clie-eng-pill clie-eng-mid">😃 Medio</span>';
                            if (n === '3' || n === 'alto')  return '<span class="clie-eng-pill clie-eng-high">🔥 Alto</span>';
                            return '<span style="color:#94a3b8;">' + val + '</span>';
                        }
                        function _dash(v) { return (v && v.trim() !== '') ? v.trim() : '—'; }

                        // ── Column updates ──
                        var nameCol       = getColIndex('Nombre');
                        var bodaCol       = getColIndex('¿Dónde se casa?');
                        var cuandoCol     = getColIndex('¿Cuándo se casa?');
                        var ciudadCol     = getColIndex('Ciudad de origen');
                        var metodoCont    = getColIndex('Método de contacto');
                        var desdeCol      = getColIndex('Desde cuándo nos conoce');
                        var dondeCol      = getColIndex('De dónde nos conoce');
                        var engCol        = getColIndex('Engagement');
                        var paqueteCol    = getColIndex('Paquete');
                        var puntosCol     = getColIndex('Puntos');
                        var montoCol      = getColIndex('Monto de la venta');
                        var queVendidoCol = getColIndex('¿Qué se les vendió?');
                        var fechaCol      = getColIndex('Fecha que se cerró');

                        if (nameCol >= 0)
                            row.find('td').eq(nameCol).html('<div class="clie-td-name">' + escapeHtml(fields.names || '') + '</div>');
                        if (bodaCol >= 0)
                            row.find('td').eq(bodaCol).text(_dash(fields.wedding_location));
                        if (cuandoCol >= 0) {
                            var wdFmt = fields.wedding_date ? fields.wedding_date.replace(/(\d{4})-(\d{2})-(\d{2})/, '$3/$2/$1') : '—';
                            row.find('td').eq(cuandoCol).text(wdFmt);
                        }
                        if (ciudadCol >= 0)
                            row.find('td').eq(ciudadCol).text(_dash(fields.city));
                        if (metodoCont >= 0)
                            row.find('td').eq(metodoCont).text(_normalizeChannel(fields.first_contact_channel));
                        if (desdeCol >= 0)
                            row.find('td').eq(desdeCol).text(_knuLabel(fields.how_long_known_us));
                        if (dondeCol >= 0)
                            row.find('td').eq(dondeCol).text(_origenLabel(fields.how_did_you_meet));
                        if (engCol >= 0)
                            row.find('td').eq(engCol).html(_engHtml(fields.engagement));
                        if (paqueteCol >= 0) {
                            var $pq = row.find('td').eq(paqueteCol);
                            var paqDisplay = (fields.paquete || '').trim();
                            $pq.text(paqDisplay !== '' ? paqDisplay : '—');
                            var style = packageStyles[paqDisplay] || null;
                            if (style) { $pq.css({ 'background-color': style.bg, 'color': style.color }); }
                            else { $pq.css({ 'background-color': '', 'color': '' }); }
                        }
                        if (puntosCol >= 0)
                            row.find('td').eq(puntosCol).text(_dash(fields.puntos));
                        if (montoCol >= 0)
                            row.find('td').eq(montoCol).text(fields.monto_venta && fields.monto_venta !== '' ? fields.monto_venta : '—');
                        if (queVendidoCol >= 0)
                            row.find('td').eq(queVendidoCol).text(_dash(fields.que_se_les_vendio));
                        if (fechaCol >= 0) {
                            var ts = 0;
                            if (resp.client && resp.client.fecha_cambio_cliente) ts = Math.floor(new Date(resp.client.fecha_cambio_cliente.replace(' ', 'T')).getTime() / 1000);
                            var fechaText = resp.client && resp.client.fecha_cambio_cliente ? formatDateSpanish(resp.client.fecha_cambio_cliente) : '—';
                            row.find('td').eq(fechaCol).attr('data-order', ts).text(fechaText);
                        }

                        }

                    // Close modal and reload page to reflect changes
                    var m = bootstrap.Modal.getInstance(document.getElementById('editClientModal'));
                    if (m) m.hide();

                    Swal.fire({ title: 'Guardado', text: 'Cliente actualizado correctamente', icon: 'success', timer: 1500, showConfirmButton: false })
                        .then(function () { location.reload(); });
                },
                error: function () { Swal.close(); Swal.fire('Error', 'Error de comunicación con el servidor', 'error'); }
            });
        });

        // Calcular puntos automáticamente a partir de monto de venta
        // Regla: puntos = floor(monto_venta / 77000)
        (function () {
            var $monto = $('#edit_monto_venta');
            var $puntos = $('#edit_puntos');

            function calcularPuntos() {
                var v = $monto.val();
                if (!v || v === '') {
                    // no modificar si está vacío
                    $puntos.val('');
                    return;
                }
                // permitir separador de miles o comas, limpiar
                var num = parseFloat(String(v).replace(/[^0-9.\-]/g, ''));
                if (isNaN(num) || num <= 0) {
                    $puntos.val('');
                    return;
                }
                var pts = num / 77000;
                // mínimo 0
                if (pts < 0) pts = 0;
                // Mostrar con 2 decimales
                $puntos.val(pts.toFixed(2));
            }

            // Calcular al perder el foco y en input (debounced)
            var timeoutId = null;
            $monto.on('input', function () {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(calcularPuntos, 250);
            });
            $monto.on('change', calcularPuntos);
        })();

        // When a text input (comments) loses focus, save it
        var commentTimer = {};
        $(document).on('input', '.lead-update-text', function () {
            var el = $(this);
            var id = el.data('id');
            var field = el.data('field');
            var value = el.val();

            // debounce per element
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
                    error: function () {
                        Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                    },
                    complete: function () {
                        el.prop('disabled', false);
                    }
                });
            }, 700);
        });

        // ---------- Add Client modal handlers ----------
        // Populate countries in the add modal
        function populateAddCountryCodes() {
            const selectElement = document.getElementById('add_country_code');
            if (!selectElement) return;
            // prevent duplicate options
            if (selectElement.dataset.loaded === '1') return;
            fetch('JS/countries_codes.json')
                .then(response => response.json())
                .then(data => {
                    let countries = data.countries || [];
                    countries.sort((a, b) => a.name.localeCompare(b.name));
                    countries.forEach(country => {
                        const option = document.createElement('option');
                        option.value = country.code;
                        option.textContent = `${country.code} (${country.name})`;
                        selectElement.appendChild(option);
                    });
                    selectElement.dataset.loaded = '1';
                })
                .catch(err => { console.error('Error loading country codes', err); });
        }

        // Open add client modal
        $('#btnAddClient').on('click', function () {
            // reset form
            $('#addClientForm')[0].reset();
            // populate countries
            populateAddCountryCodes();
            var modal = new bootstrap.Modal(document.getElementById('addClientModal'));
            modal.show();
        });

        // Add client save
        $('#addClientSaveBtn').on('click', function () {
            var names = $('#add_name').val().trim();
            var email = $('#add_email').val().trim();
            var telephone = $('#add_telephone').val().trim();
            var wedding_date = $('#add_wedding_date').val().trim();
            var country_code = $('#add_country_code').val() || '';
            var id_vendedor_asignado = $('#add_id_vendedor_asignado').val() || '';

            if (!names) { Swal.fire('Error', 'El nombre es requerido', 'error'); return; }
            if (!email) { Swal.fire('Error', 'El email es requerido', 'error'); return; }

            Swal.fire({ title: 'Verificando duplicados...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            $.ajax({
                url: 'actualizar_lead.php',
                method: 'POST',
                dataType: 'json',
                data: { action: 'check_existing', name: names, email: email },
                success: function (resp) {
                    Swal.close();
                    if (!resp || !resp.success) {
                        Swal.fire('Error', resp && resp.message ? resp.message : 'Error al verificar duplicados', 'error');
                        return;
                    }

                    var conflicts = [];
                    if (resp.exists_name) conflicts.push('nombre');
                    if (resp.exists_email) conflicts.push('email');

                    function doSave() {
                        Swal.fire({ title: 'Guardando cliente...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        $.ajax({
                            url: 'guardar_cliente.php',
                            method: 'POST',
                            data: JSON.stringify({
                                names: names,
                                email_address: email,
                                telephone: telephone,
                                country_code: country_code,
                                wedding_date: wedding_date,
                                id_vendedor_asignado: id_vendedor_asignado || null,
                                submission_date: fromDateTimeInputToMysql($('#add_submission_date').val().trim()) || ''
                            }),
                            contentType: 'application/json',
                            dataType: 'json',
                            success: function (r) {
                                Swal.close();
                                if (r && r.success) {
                                    Swal.fire('Éxito', r.message || 'Cliente agregado correctamente', 'success').then(function () { location.reload(); });
                                } else {
                                    // Show debug if provided
                                    var msg = r && r.message ? r.message : 'Error al guardar cliente';
                                    if (r && r.debug) msg += '\n\n[DEBUG] ' + r.debug;
                                    Swal.fire('Error', msg, 'error');
                                }
                            },
                            error: function (xhr) {
                                Swal.close();
                                // Show server response if available
                                var respText = xhr && xhr.responseText ? xhr.responseText : '';
                                console.error('Guardar cliente error', xhr.status, respText);
                                var show = 'Error de comunicación con el servidor';
                                if (respText) show += '<br><br><pre style="white-space:pre-wrap;">' + $('<div>').text(respText).html() + '</pre>';
                                Swal.fire({ icon: 'error', title: 'Error', html: show });
                            }
                        });
                    }

                    if (conflicts.length > 0) {
                        var msg = 'Existe un cliente con el mismo ' + conflicts.join(' y ') + '. ¿Deseas ir al cliente existente?';
                        Swal.fire({ title: 'Conflicto de duplicado', text: msg, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ver cliente', cancelButtonText: 'Cancelar' }).then(function (res) {
                            if (res.isConfirmed) {
                                if (resp.matches && resp.matches.length) {
                                    var existingId = resp.matches[0].id;
                                    if (existingId) {
                                        // Open the edit client modal for the found client
                                        editClient(existingId);
                                    }
                                }
                            }
                        });
                    } else {
                        doSave();
                    }
                },
                error: function (xhr) { Swal.close(); console.error('check_existing error', xhr.status, xhr.responseText); Swal.fire('Error', 'Error de comunicación con el servidor: ' + xhr.status + ' - ' + (xhr.responseText || ''), 'error'); }
            });
        });
    </script>
</body>

</html>