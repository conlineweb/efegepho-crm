<?php
include 'menu.php';
include 'conn.php';

// permisos / sesión
$tipoUsuario = $_SESSION['tus'] ?? null;
$userid = $_SESSION['uid'] ?? null;

// cargar plantillas de marketing
$templates = [];
$resT = $conn->query("SELECT id, nombre FROM marketing_templates ORDER BY nombre ASC");
if ($resT && $resT->num_rows > 0) {
    while ($r = $resT->fetch_assoc()) {
        $templates[] = $r;
    }
}

// obtener todas las tablas de leads (omitimos `wedding_planners`)
$tablas = [];
$sqlTablas = "SELECT nombre FROM tablas_leads ORDER BY nombre";
$resTab = $conn->query($sqlTablas);
if ($resTab && $resTab->num_rows > 0) {
    while ($r = $resTab->fetch_assoc()) {
        $tname = isset($r['nombre']) ? trim($r['nombre']) : '';
        // omitir tabla wedding_planners (comparación case-insensitive)
        if ($tname !== '' && mb_strtolower($tname, 'UTF-8') === 'wedding_planners') {
            continue;
        }
        $tablas[] = $r['nombre'];
    }
}

// recoger leads - campos mínimos: id, nombre, campaign_name (origen), form_name/platform (medio), tabla_origen
$allLeads = [];
foreach ($tablas as $tableName) {
    // verificar existencia
    $check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    if (!$check || $check->num_rows == 0)
        continue;

    // obtener columnas
    $cols = [];
    $cres = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tableName) . "`");
    while ($c = $cres->fetch_assoc()) {
        $cols[] = $c['Field'];
    }

    // columnas candidates for name
    $nameCandidates = ['full_name', 'fullname', 'name', 'first_name', 'nombre', 'nombre_completo', 'firstName'];
    $nameCol = '';
    foreach ($nameCandidates as $nc) {
        if (in_array($nc, $cols)) {
            $nameCol = $nc;
            break;
        }
    }

    // build select columns to fetch: id plus a few helpful fields if exist
    $selectCols = ['id'];
    if (in_array('campaign_name', $cols))
        $selectCols[] = 'campaign_name';
    if (in_array('form_name', $cols))
        $selectCols[] = 'form_name';
    if (in_array('platform', $cols))
        $selectCols[] = 'platform';
    if (in_array('created_time', $cols))
        $selectCols[] = 'created_time';
    if (in_array('email', $cols))
        $selectCols[] = 'email';
    // include the name column if found
    if ($nameCol)
        $selectCols[] = $nameCol;

    $sql = "SELECT " . implode(',', $selectCols) . " FROM `" . $conn->real_escape_string($tableName) . "` ORDER BY created_time DESC";
    $resL = $conn->query($sql);
    if ($resL && $resL->num_rows > 0) {
        while ($lr = $resL->fetch_assoc()) {
            $lead = [];
            $lead['id'] = intval($lr['id']);
            // build display name
            $displayName = '';
            if ($nameCol && !empty($lr[$nameCol]))
                $displayName = $lr[$nameCol];
            // fallback to email or telefono
            if (empty($displayName)) {
                if (!empty($lr['email']))
                    $displayName = $lr['email'];
                elseif (!empty($lr['phone']))
                    $displayName = $lr['phone'];
                else
                    $displayName = 'Sin nombre';
            }
            $lead['nombre'] = $displayName;
            $lead['campaign_name'] = $lr['campaign_name'] ?? '';
            $lead['medio'] = trim((string) ($lr['form_name'] ?? $lr['platform'] ?? ''));
            $lead['tabla_origen'] = $tableName;
            $lead['created_time'] = $lr['created_time'] ?? '';
            $lead['email'] = $lr['email'] ?? '';
            $allLeads[] = $lead;
        }
    }
}

// ordenar por created_time desc si existe
usort($allLeads, function ($a, $b) {
    $ta = (!empty($a['created_time']) && strtotime($a['created_time']) !== false) ? strtotime($a['created_time']) : 0;
    $tb = (!empty($b['created_time']) && strtotime($b['created_time']) !== false) ? strtotime($b['created_time']) : 0;
    if ($ta === $tb)
        return 0;
    return ($ta > $tb) ? -1 : 1;
});

// Construir orígenes únicos y medios por origen
$origenesUnicos = [];
$mediosPorOrigen = [];
foreach ($allLeads as $lead) {
    $origen = $lead['campaign_name'] ?? '';
    $medio = $lead['medio'] ?? '';

    $origenNormalizado = $origen;
    if (preg_match('/^c(\d+)\./i', $origen, $matches)) {
        $origenNormalizado = 'c' . $matches[1];
    }

    if (!empty($origenNormalizado) && !in_array($origenNormalizado, $origenesUnicos)) {
        $origenesUnicos[] = $origenNormalizado;
    }

    if (!empty($origenNormalizado) && !empty($medio)) {
        if (!isset($mediosPorOrigen[$origenNormalizado]))
            $mediosPorOrigen[$origenNormalizado] = [];
        if (!in_array($medio, $mediosPorOrigen[$origenNormalizado]))
            $mediosPorOrigen[$origenNormalizado][] = $medio;
    }
}
sort($origenesUnicos);

// Leer filtros de GET
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$filterOrigen = isset($_GET['filter_origen']) ? trim($_GET['filter_origen']) : '';
$filterMedio = isset($_GET['filter_medio']) ? trim($_GET['filter_medio']) : '';
$filterEstatus = isset($_GET['filter_estatus']) ? trim($_GET['filter_estatus']) : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Aplicar filtros iniciales (fecha, origen, medio) — OMITIR leads sin email
$filteredLeads = [];
foreach ($allLeads as $lead) {
    // Omitir registros que no tengan correo electrónico (requisito: sólo leads con email para Mailing)
    if (empty(trim((string)($lead['email'] ?? '')))) {
        continue;
    }

    // Fecha
    if ($startDate !== '' || $endDate !== '') {
        if (empty($lead['created_time']))
            continue;
        $leadDate = date('Y-m-d', strtotime($lead['created_time']));
        $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
        $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
        if ($sd && $leadDate < $sd)
            continue;
        if ($ed && $leadDate > $ed)
            continue;
    }

    // Origen
    if ($filterOrigen !== '') {
        $leadOrigen = $lead['campaign_name'] ?? '';
        $leadOrigenNormalizado = $leadOrigen;
        if (preg_match('/^c(\d+)\./i', $leadOrigen, $matches)) {
            $leadOrigenNormalizado = 'c' . $matches[1];
        }
        if ($leadOrigenNormalizado !== $filterOrigen)
            continue;
    }

    // Medio
    if ($filterMedio !== '') {
        $leadMedio = trim((string) ($lead['medio'] ?? ''));
        if ($leadMedio !== $filterMedio)
            continue;
    }

    $filteredLeads[] = $lead;
}

$normalizedSearch = mb_strtolower($searchQuery, 'UTF-8');
if ($normalizedSearch !== '') {
    $filteredBySearch = [];
    foreach ($filteredLeads as $lead) {
        $haystack = trim(
            ($lead['nombre'] ?? '') . ' ' .
            ($lead['email'] ?? '') . ' ' .
            ($lead['campaign_name'] ?? '') . ' ' .
            ($lead['medio'] ?? '')
        );
        if ($haystack !== '' && mb_stripos($haystack, $normalizedSearch, 0, 'UTF-8') !== false) {
            $filteredBySearch[] = $lead;
        }
    }
    $filteredLeads = $filteredBySearch;
}

// Construir ids por tabla a partir de los leads filtrados (para determinar estatus)
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

// Mapear plantillas programadas por lead (tabla|id) — ahora puede haber varias plantillas por lead
$leadTemplateMap = []; // example: ['tabla|123' => [1,5,9]]
$leadTemplateQueueDetails = []; // map: 'tabla|lead|template' => [status, scheduled_at, last_sent_at, sent_count, error_message]
foreach ($leadIdsByTable as $t => $ids) {
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '')
        continue;
    $safeTable = $conn->real_escape_string($t);
    $sqlQ = "SELECT lead_id, template_id, status, scheduled_at, last_sent_at, sent_count, error_message FROM marketing_template_queue WHERE tabla_origen = '" . $safeTable . "' AND lead_id IN (" . $idsList . ")";
    $resQ = $conn->query($sqlQ);
    if ($resQ) {
        while ($row = $resQ->fetch_assoc()) {
            $key = $t . '|' . intval($row['lead_id']);
            if (!isset($leadTemplateMap[$key]))
                $leadTemplateMap[$key] = [];
            $leadTemplateMap[$key][] = intval($row['template_id']);

            $dqk = $t . '|' . intval($row['lead_id']) . '|' . intval($row['template_id']);
            $leadTemplateQueueDetails[$dqk] = [
                'status' => $row['status'] ?? null,
                'scheduled_at' => $row['scheduled_at'] ?? null,
                'last_sent_at' => $row['last_sent_at'] ?? null,
                'sent_count' => isset($row['sent_count']) ? intval($row['sent_count']) : 0,
                'error_message' => $row['error_message'] ?? null
            ];
        }
    }
}

// Leer aperturas por template para los leads filtrados (si existen)
$emailOpensMap = []; // key: 'tabla|lead|template' => earliest opened_at
foreach ($leadIdsByTable as $t => $ids) {
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '') continue;
    $safeTable = $conn->real_escape_string($t);
    $sqlO = "SELECT lead_id, template_id, MIN(opened_at) AS opened_at FROM email_opens WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND lead_id IN (" . $idsList . ") AND template_id IS NOT NULL GROUP BY lead_id, template_id";
    $resO = $conn->query($sqlO);
    if ($resO) {
        while ($r = $resO->fetch_assoc()) {
            if (empty($r['template_id'])) continue;
            $k = $t . '|' . intval($r['lead_id']) . '|' . intval($r['template_id']);
            $emailOpensMap[$k] = $r['opened_at'];
        }
    }
}

// Consultar contact_form para mapear citas/estatus
$contactFormColumns = [];
$resContactFormColumns = $conn->query("SHOW COLUMNS FROM contact_form");
if ($resContactFormColumns) {
    while ($col = $resContactFormColumns->fetch_assoc()) {
        $contactFormColumns[mb_strtolower((string)($col['Field'] ?? ''), 'UTF-8')] = true;
    }
}
$hasCfMarketingTemplateId = !empty($contactFormColumns['marketing_template_id']);
$hasCfMarketingEmailClickId = !empty($contactFormColumns['marketing_email_click_id']);
$hasCfMarketingClickAt = !empty($contactFormColumns['marketing_click_at']);

$contactFormByLead = [];
$cfIds = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '')
        continue;
    $cfSelect = "id, original_lead_id, cliente";
    $cfSelect .= $hasCfMarketingTemplateId ? ", marketing_template_id" : ", NULL AS marketing_template_id";
    $cfSelect .= $hasCfMarketingEmailClickId ? ", marketing_email_click_id" : ", NULL AS marketing_email_click_id";
    $cfSelect .= $hasCfMarketingClickAt ? ", marketing_click_at" : ", NULL AS marketing_click_at";
    $sql = "SELECT " . $cfSelect . " FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")";
    $resCf = $conn->query($sql);
    if ($resCf) {
        while ($row = $resCf->fetch_assoc()) {
            $key = $t . '|' . intval($row['original_lead_id']);
            $contactFormByLead[$key] = [
                'cf_id' => intval($row['id']),
                'cliente' => isset($row['cliente']) ? intval($row['cliente']) : 0,
                'marketing_template_id' => isset($row['marketing_template_id']) ? intval($row['marketing_template_id']) : 0,
                'marketing_email_click_id' => isset($row['marketing_email_click_id']) ? intval($row['marketing_email_click_id']) : 0,
                'marketing_click_at' => $row['marketing_click_at'] ?? null
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

// Build final map of status per lead (tabla|id)
$leadStatusMap = [];
foreach ($leadIdsByTable as $t => $ids) {
    foreach ($ids as $lid) {
        $key = $t . '|' . intval($lid);
        $leadStatusMap[$key] = 'lead'; // default
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

// Prepare unique estatus list for the filter UI (derived from the leadStatusMap)
$estatusUnicos = [];
foreach ($leadStatusMap as $k => $v) {
    $vNorm = trim((string) $v);
    if ($vNorm === '')
        $vNorm = 'lead';
    if (!in_array($vNorm, $estatusUnicos))
        $estatusUnicos[] = $vNorm;
}
sort($estatusUnicos);

// Build map of templates by id for quick lookups in the UI (nombre)
$templatesById = [];
foreach ($templates as $t) {
    $templatesById[intval($t['id'])] = $t['nombre'];
}

// -- Aplicar filtro por estatus (usando $leadStatusMap que ya fue construido) --
if ($filterEstatus !== '') {
    $filteredLeadsByStatus = [];
    foreach ($filteredLeads as $lead) {
        $key = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);
        $stat = isset($leadStatusMap[$key]) ? $leadStatusMap[$key] : 'lead';
        if (mb_strtolower($stat, 'UTF-8') === mb_strtolower($filterEstatus, 'UTF-8')) {
            $filteredLeadsByStatus[] = $lead;
        }
    }
    $filteredLeads = $filteredLeadsByStatus;
}

// Crear lista separada para Agendados/Atendidos — además, excluir estos estatus del listado "Todos"
$leadsAgendados = [];
foreach ($filteredLeads as $lead) {
    $key = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);
    $stat = isset($leadStatusMap[$key]) ? mb_strtolower(trim((string)$leadStatusMap[$key]), 'UTF-8') : '';
    // aceptar sólo estatus agendado/atendido o códigos 0/1
    if ($stat === 'agendado' || $stat === 'atendido' || $stat === '0' || $stat === '1') {
        $leadsAgendados[] = $lead;
    }
}

// Excluir 'agendado' y 'atendido' del listado "Todos" para que sólo aparezcan en la pestaña 'Agendados'
$filteredLeads = array_values(array_filter($filteredLeads, function ($lead) use ($leadStatusMap) {
    $key = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);
    $stat = isset($leadStatusMap[$key]) ? mb_strtolower(trim((string)$leadStatusMap[$key]), 'UTF-8') : '';
    return !in_array($stat, ['agendado', 'atendido', '0', '1'], true);
}));

// Re-ordenar después de filtrar
usort($filteredLeads, function ($a, $b) {
    $ta = (!empty($a['created_time']) && strtotime($a['created_time']) !== false) ? strtotime($a['created_time']) : 0;
    $tb = (!empty($b['created_time']) && strtotime($b['created_time']) !== false) ? strtotime($b['created_time']) : 0;
    if ($ta === $tb)
        return 0;
    return ($ta > $tb) ? -1 : 1;
});

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mailing</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            border-radius: 9999px; /* fallback: garantizar borde redondeado si falta la clase base */
        }

        /* Bootstrap-style tabs for leads (replaces previous .leads-tab-btn styles) */
        #leadsTabNav {
            border-bottom: none;
        }
        #leadsTabNav .nav-link {
            padding: 0.45rem 0.9rem;
            border-radius: 0.5rem 0.5rem 0 0;
            color: var(--slate-900);
            background: transparent;
            border: 1px solid transparent;
        }
        #leadsTabNav .nav-link.active {
            background-color: var(--white);
            color: var(--slate-900);
            border-color: rgba(0,0,0,0.08) rgba(0,0,0,0.08) var(--white);
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
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

        /* Toda la celda del checkbox es clickeable */
        td.check-td {
            cursor: pointer;
            user-select: none;
        }

        /* Columna de origen más compacta y centrada */
        .origen-col {
            width: 80px;
            min-width: 60px;
            text-align: center;
            font-size: 0.85rem;
            vertical-align: middle;
        }

        /* Nombre más compacto con truncado */
        .name-col {
            width: 200px;
            min-width: 140px;
            max-width: 320px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ---------- Template badge (mejor diseño) ---------- */
        .tpl-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 6px 10px;
            border-radius: 999px;
            background: linear-gradient(180deg,#fbfdff,#eef2ff);
            border: 1px solid rgba(2,6,23,0.06);
            box-shadow: 0 1px 2px rgba(2,6,23,0.04);
            min-width: 150px;
            max-width: 360px;
            transition: transform .12s ease, box-shadow .12s ease;
            font-family: inherit;
        }
        .tpl-badge:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(2,6,23,0.08); }
        .tpl-badge .tpl-left { display:flex; gap:8px; align-items:center; overflow:hidden; }
        .tpl-badge .tpl-title { font-weight:700; font-size:0.90rem; color:var(--slate-900); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px; }
        .tpl-badge .tpl-meta { display:flex; gap:8px; align-items:center; font-size:0.72rem; color:#6b7280; white-space:nowrap; }
        .tpl-pill { display:inline-flex; gap:8px; align-items:center; padding:4px 8px; border-radius:999px; font-weight:700; font-size:0.68rem; color:#fff; }
        .tpl-pill i { font-size:0.86rem; opacity:0.95; }
        .tpl-pill--open { background: linear-gradient(90deg,#10b981,#059669); }
        .tpl-pill--sent { background: linear-gradient(90deg,#4f46e5,#2563eb); }
        .tpl-pill--scheduled { background:#f3f4f6; color:#374151; border:1px solid #e6e7eb; }
        .tpl-pill--error { background:#f97316; }
        .tpl-meta-date { color:#6b7280; font-weight:600; font-size:0.72rem; }
        .tpl-meta-lead-status { font-size:0.72rem; font-weight:700; }
        .tpl-badge .tpl-meta.tpl-meta-lead-status--agendado { color:var(--blue-700); }
        .tpl-badge .tpl-meta.tpl-meta-lead-status--atendido { color:var(--fuchsia-700); }
        .tpl-badge.tpl-badge--attributed { background:linear-gradient(180deg,#fff8eb,#ffedd5) !important; border-color:rgba(217,119,6,0.28); box-shadow:0 0 0 1px rgba(217,119,6,0.08), 0 8px 20px rgba(217,119,6,0.10); }
        .tpl-badge .tpl-meta.tpl-meta-attributed { color:#b45309; font-weight:700; }
        .tpl-source-note { display:block; margin-bottom:8px; color:#b45309; font-size:0.74rem; font-weight:700; }
        .muted { color:#94a3b8; font-weight:600; }
        .tpl-actions { margin-left:auto; display:flex; gap:6px; align-items:center; }
        .tpl-remove { background:transparent; border:1px solid rgba(0,0,0,0.06); color:#d9464a; width:26px; height:26px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; font-weight:700; line-height:1; }
        /* ---------------------------------------------------- */


    </style>
</head>

<body>
    <div class="reportes-dashboard">
        <div class="py-6">
            <div class="card">
                <div class="flex sm:flex-row flex-col sm:justify-between sm:items-center gap-3 px-5 py-4 border-b">
                    <div>
                        <div class="font-semibold text-slate-900 text-sm">Mailing</div>
                        <div class="mt-1 text-slate-500 text-xs">Aquí puedes ver los leads y seleccionar una plantilla por cada lead.</div>
                    </div>
                </div>
            </div>

            <!-- FILTER BAR STICKY -->
            <div style="position:sticky;top:20px;z-index:100;background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:14px 20px;margin-top:16px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
                <form method="get" class="flex flex-wrap items-center gap-2" id="filterForm" style="display:contents;">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <div class="select-wrapper">
                            <input type="date" name="start_date" class="custom-input"
                                value="<?php echo htmlspecialchars($startDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="select-wrapper">
                            <input type="date" name="end_date" class="custom-input"
                                value="<?php echo htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="select-wrapper" style="min-width: 180px;">
                            <select name="filter_origen" id="filterOrigen" class="custom-select">
                                <option value="">Todos</option>
                                <?php foreach ($origenesUnicos as $origen): ?>
                                    <option value="<?php echo htmlspecialchars($origen); ?>" <?php echo ($filterOrigen === $origen) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($origen); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="select-arrow fas fa-chevron-down"></i>
                        </div>
                        <div class="select-wrapper" style="min-width: 180px;">
                            <select name="filter_medio" id="filterMedio" class="custom-select">
                                <option value="">Todos</option>
                                <?php
                                $mediosDisponibles = [];
                                if ($filterOrigen !== '' && isset($mediosPorOrigen[$filterOrigen])) {
                                    $mediosDisponibles = $mediosPorOrigen[$filterOrigen];
                                } else {
                                    foreach ($mediosPorOrigen as $meds) {
                                        foreach ($meds as $m)
                                            if (!in_array($m, $mediosDisponibles))
                                                $mediosDisponibles[] = $m;
                                    }
                                }
                                sort($mediosDisponibles);
                                foreach ($mediosDisponibles as $medio): ?>
                                    <option value="<?php echo htmlspecialchars($medio); ?>" <?php echo ($filterMedio === $medio) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($medio); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="select-arrow fas fa-chevron-down"></i>
                        </div>
                        <div class="select-wrapper" style="min-width: 160px;">
                            <select name="filter_estatus" id="filterEstatus" class="custom-select">
                                <option value="">Todos</option>
                                <?php foreach ($estatusUnicos as $estatus): ?>
                                    <option value="<?php echo htmlspecialchars($estatus); ?>" <?php echo ($filterEstatus === $estatus) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($estatus)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="select-arrow fas fa-chevron-down"></i>
                        </div>
                        <button type="submit" class="rd-btn rd-btn-primary">Filtrar</button>
                        <a href="consulta_leads_marketing.php" class="rd-btn">Reset</a>
                    </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                <div class="card p-5">
                    <div class="font-semibold text-slate-900 text-sm">Asignación masiva de plantillas</div>
                    <div class="mt-1 text-slate-500 text-xs">Selecciona uno o más leads y asigna varias plantillas de golpe</div>
                    <div class="flex flex-wrap items-center gap-2 mt-4">
                        <button type="button" id="bulkApplyBtn" class="rd-btn rd-btn-primary"><i class="fas fa-layer-group"></i> Seleccionar plantillas y aplicar</button>
                        <span class="text-slate-500 text-xs" id="bulkSelectedCount">0 seleccionados</span>
                    </div>
                </div>
                <div class="card p-5">
                    <div class="font-semibold text-slate-900 text-sm">Búsqueda rápida</div>
                    <div class="mt-1 text-slate-500 text-xs">Filtra por nombre, correo u origen</div>
                    <div class="relative mt-4" style="position:relative;">
                        <i class="search-icon fas fa-search"></i>
                        <input type="text" id="leadsSearchInput" class="search-input" placeholder="Buscar por nombre, correo u origen"
                            value="<?php echo htmlspecialchars($searchQuery); ?>" />
                    </div>
                </div>
            </div>

            <div class="card mt-6">
                <div class="flex sm:flex-row flex-col sm:justify-between sm:items-center gap-3 px-5 py-4 border-b">
                    <div>
                        <div class="font-semibold text-slate-900 text-sm">Listado de Leads</div>
                        <div class="mt-1 text-slate-500 text-xs">Asignación de plantillas y envíos</div>
                    </div>
                </div>
                <div class="p-5">
                    <!-- Tabs: Listado / Agendados (Bootstrap) -->
                    <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center;">
                        <ul class="nav nav-tabs" id="leadsTabNav" role="tablist" style="gap:8px;margin:0;">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="leads-tab-all" data-tab="all" href="#" role="tab" aria-controls="all" aria-selected="true">Listado de Leads</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" id="leads-tab-agendados" data-tab="agendados" href="#" role="tab" aria-controls="agendados" aria-selected="false">Agendados</a>
                            </li>
                        </ul>
                        <div style="margin-left:auto;font-size:0.9rem;color:var(--slate-600);">Mostrar: <strong id="leadsTabLabel">Todos</strong></div>
                    </div>

                    <div id="leadsTableWrapperAll" class="overflow-x-auto custom-scroll">
                        <table id="leadsMarketingTable" class="table table-hover table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th style="width:40px; text-align:center;">
                                        <input type="checkbox" id="selectAllLeads" />
                                    </th>
                                    <th class="name-col">Nombre</th>
                                    <th class="status-col">Estatus</th>
                                    <th class="origen-col">Origen</th>
                                    <th>Medio</th>
                                    <th>¿Cuándo llegó el lead?</th>
                                    <th>Correo</th>
                                    <th style="width:160px; text-align:center;">Envíos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredLeads as $lead): ?>
                                    <?php $mapKeyRow = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0); $rowStatus = isset($leadStatusMap[$mapKeyRow]) ? $leadStatusMap[$mapKeyRow] : 'lead'; $rowStatus = mb_strtolower(trim((string)$rowStatus), 'UTF-8'); ?>
                                    <tr data-tabla="<?php echo htmlspecialchars($lead['tabla_origen']); ?>" data-status="<?php echo htmlspecialchars($rowStatus); ?>">
                                        <td class="text-center align-middle check-td">
                                            <input type="checkbox" class="lead-check" data-tabla="<?php echo htmlspecialchars($lead['tabla_origen']); ?>" data-leadid="<?php echo intval($lead['id']); ?>" />
                                        </td>
                                        <td class="name-col">
                                            <?php echo htmlspecialchars($lead['nombre']); ?>
                                            <?php if (!empty($lead['email'])): ?>
                                                <div class="text-muted small"><?php echo htmlspecialchars($lead['email']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center align-middle status-col status-primary">
                                            <?php
                                                $displayStatusLabel = 'Lead';
                                                if ($rowStatus === 'agendado') $displayStatusLabel = 'Agendado';
                                                elseif ($rowStatus === 'atendido') $displayStatusLabel = 'Atendido';
                                                elseif ($rowStatus === 'cliente') $displayStatusLabel = 'Cliente';
                                                elseif ($rowStatus === 'fantasma') $displayStatusLabel = 'Fantasma';
                                                elseif ($rowStatus === 'muerto') $displayStatusLabel = 'Muerto';
                                                else $displayStatusLabel = ucfirst($rowStatus);
                                            ?>
                                            <span class="status-cell status-<?php echo htmlspecialchars($rowStatus); ?>"><?php echo htmlspecialchars($displayStatusLabel); ?></span>
                                        </td>
                                        <td class="text-center align-middle origen-col">
                                            <?php
                                            $cname = $lead['campaign_name'] ?? '';
                                            // Mostrar como "C{n}" si el formato es c<number>.   También soporta e<number>-
                                            if (preg_match('/^(c(\d+)\.|e(\d+)-)/i', $cname, $matches)) {
                                                if (!empty($matches[2])) {
                                                    echo 'C' . $matches[2];
                                                } elseif (!empty($matches[3])) {
                                                    echo 'E' . $matches[3];
                                                }
                                            } else {
                                                echo htmlspecialchars($cname);
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($lead['medio'] ?? ''); ?></td>
                                        <td data-order="<?php echo (!empty($lead['created_time']) && strtotime($lead['created_time']) !== false) ? strtotime($lead['created_time']) : 0; ?>">
                                            <?php
                                            if (!empty($lead['created_time']) && strtotime($lead['created_time']) !== false) {
                                                $ts = strtotime($lead['created_time']);
                                                $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                                                echo date('j', $ts) . ' de ' . $months[date('n', $ts) - 1] . ' del ' . date('Y', $ts);
                                            } else {
                                                echo '';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $mapKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);
                                            $assigned = isset($leadTemplateMap[$mapKey]) ? $leadTemplateMap[$mapKey] : [];
                                            $leadBadgeStatus = '';
                                            $leadBadgeStatusClass = '';
                                            $attributedTemplateId = isset($contactFormByLead[$mapKey]['marketing_template_id']) ? intval($contactFormByLead[$mapKey]['marketing_template_id']) : 0;
                                            $attributedTemplateName = $attributedTemplateId > 0 && isset($templatesById[$attributedTemplateId]) ? $templatesById[$attributedTemplateId] : ($attributedTemplateId > 0 ? ('Plantilla ' . $attributedTemplateId) : '');
                                            $showAttributedOutside = $attributedTemplateId > 0 && !in_array($attributedTemplateId, array_map('intval', $assigned), true);
                                            if ($rowStatus === 'agendado') {
                                                $leadBadgeStatus = 'Cita agendada';
                                                $leadBadgeStatusClass = 'tpl-meta-lead-status--agendado';
                                            } elseif ($rowStatus === 'atendido') {
                                                $leadBadgeStatus = 'Cita atendida';
                                                $leadBadgeStatusClass = 'tpl-meta-lead-status--atendido';
                                            }
                                            ?>
                                            <div class="templates-cell" data-tabla="<?php echo htmlspecialchars($lead['tabla_origen']); ?>" data-leadid="<?php echo intval($lead['id']); ?>" style="display:flex;align-items:center;gap:10px;min-width:0;">
                                                <div class="badges-container" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;min-width:0;">
                                                    <?php if ($showAttributedOutside && $attributedTemplateName !== ''): ?>
                                                        <span class="tpl-source-note">Agendó desde: <?php echo htmlspecialchars($attributedTemplateName); ?></span>
                                                    <?php endif; ?>
                                                    <?php foreach ($assigned as $tid): ?>
                                                        <?php
                                                            $tname = isset($templatesById[intval($tid)]) ? $templatesById[intval($tid)] : 'Plantilla ' . intval($tid);
                                                            $queueKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']) . '|' . intval($tid);
                                                            $qinfo = $leadTemplateQueueDetails[$queueKey] ?? null;
                                                            $openAt = $emailOpensMap[$queueKey] ?? null;
                                                            $isAttributedTemplate = $attributedTemplateId > 0 && intval($tid) === $attributedTemplateId;
                                                        ?>
                                                        <span class="tpl-badge<?php echo $isAttributedTemplate ? ' tpl-badge--attributed' : ''; ?>" data-templateid="<?php echo intval($tid); ?>" style="background:#eef2ff;padding:8px;border-radius:12px;font-size:0.82rem;display:inline-flex;flex-direction:column;align-items:flex-start;gap:6px;white-space:nowrap;min-width:140px;">
                                                            <div style="display:flex;align-items:center;gap:8px;width:100%;justify-content:space-between;">
                                                                <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px;">
                                                                    <?php echo htmlspecialchars($tname); ?>
                                                                </div>
                                                                <button class="btn-remove-tpl" data-templateid="<?php echo intval($tid); ?>" style="border:none;background:transparent;color:#d00;cursor:pointer;font-weight:700;line-height:1;padding:0 4px;">&times;</button>
                                                            </div>
                                                            <?php if ($isAttributedTemplate): ?>
                                                                <div class="tpl-meta tpl-meta-attributed">Correo que agendó</div>
                                                            <?php endif; ?>
                                                            <?php if ($leadBadgeStatus !== ''): ?>
                                                                <div class="tpl-meta tpl-meta-lead-status <?php echo htmlspecialchars($leadBadgeStatusClass); ?>"><?php echo htmlspecialchars($leadBadgeStatus); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ($qinfo):
                                                                $status = strtolower(trim((string)$qinfo['status'] ?? ''));
                                                            ?>
                                                                <?php if (!empty($qinfo['scheduled_at']) && ($status === 'pending' || $status === 'scheduled' || $status === 'error')): ?>
                                                                    <div class="tpl-meta" style="font-size:0.72rem;color:#6b7280;">Programado: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($qinfo['scheduled_at']))); ?></div>
                                                                <?php elseif ($status === 'sent'): ?>
                                                                    <?php if ($openAt): ?>
                                                                        <div class="tpl-meta" style="font-size:0.72rem;color:#16a34a;font-weight:700;">Abierto: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($openAt))); ?></div>
                                                                    <?php else: ?>
                                                                        <div class="tpl-meta" style="font-size:0.72rem;color:#6b7280;">Enviado: <?php echo htmlspecialchars(!empty($qinfo['last_sent_at']) ? date('d/m/Y H:i', strtotime($qinfo['last_sent_at'])) : date('d/m/Y H:i', strtotime($qinfo['scheduled_at'] ?? ''))); ?> <span style="color:#94a3b8;">• No visto</span></div>
                                                                    <?php endif; ?>
                                                                <?php elseif ($status === 'error'): ?>
                                                                    <div class="tpl-meta" style="font-size:0.72rem;color:#d97706;">Error</div>
                                                                <?php else: ?>
                                                                    <?php if (!empty($qinfo['scheduled_at'])): ?>
                                                                        <div class="tpl-meta" style="font-size:0.72rem;color:#6b7280;">Programado: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($qinfo['scheduled_at']))); ?></div>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <?php if (!empty($emailOpensMap[($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']) . '|' . intval($tid)])): ?>
                                                                    <div class="tpl-meta" style="font-size:0.72rem;color:#16a34a;">Abierto: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($emailOpensMap[($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']) . '|' . intval($tid)]))); ?></div>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>

                                                <div style="flex-shrink:0;">
                                                    <button class="rd-btn btn-add-templates" data-tabla="<?php echo htmlspecialchars($lead['tabla_origen']); ?>" data-leadid="<?php echo intval($lead['id']); ?>">Agrega plantilla</button>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <button class="btn btn-sm btn-outline-primary btn-view-sent" data-tabla="<?php echo htmlspecialchars($lead['tabla_origen']); ?>" data-leadid="<?php echo intval($lead['id']); ?>">Historial</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                </div>

                    <div id="leadsTableWrapperAgendados" class="overflow-x-auto custom-scroll" style="display:none;">
                        <table id="leadsMarketingTableAgendados" class="table table-hover table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th style="width:40px; text-align:center;">
                                        <input type="checkbox" id="selectAllLeadsAgendados" />
                                    </th>
                                    <th class="name-col">Nombre</th>
                                    <th class="status-col">Estatus</th>
                                    <th class="origen-col">Origen</th>
                                    <th>Medio</th>
                                    <th>¿Cuándo llegó el lead?</th>
                                    <th>Correo</th>
                                    <th style="width:160px; text-align:center;">Envíos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leadsAgendados as $lead): ?>
                                    <?php $mapKeyRow = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0); $rowStatus = isset($leadStatusMap[$mapKeyRow]) ? $leadStatusMap[$mapKeyRow] : 'lead'; $rowStatus = mb_strtolower(trim((string)$rowStatus), 'UTF-8'); ?>
                                    <tr data-tabla="<?php echo htmlspecialchars($lead['tabla_origen']); ?>" data-status="<?php echo htmlspecialchars($rowStatus); ?>">
                                        <td class="text-center align-middle check-td">
                                            <input type="checkbox" class="lead-check" data-tabla="<?php echo htmlspecialchars($lead['tabla_origen']); ?>" data-leadid="<?php echo intval($lead['id']); ?>" />
                                        </td>
                                        <td class="name-col">
                                            <?php echo htmlspecialchars($lead['nombre']); ?>
                                            <?php if (!empty($lead['email'])): ?>
                                                <div class="text-muted small"><?php echo htmlspecialchars($lead['email']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center align-middle status-col status-primary">
                                            <?php
                                                $displayStatusLabel = 'Lead';
                                                if ($rowStatus === 'agendado') $displayStatusLabel = 'Agendado';
                                                elseif ($rowStatus === 'atendido') $displayStatusLabel = 'Atendido';
                                                elseif ($rowStatus === 'cliente') $displayStatusLabel = 'Cliente';
                                                elseif ($rowStatus === 'fantasma') $displayStatusLabel = 'Fantasma';
                                                elseif ($rowStatus === 'muerto') $displayStatusLabel = 'Muerto';
                                                else $displayStatusLabel = ucfirst($rowStatus);
                                            ?>
                                            <span class="status-cell status-<?php echo htmlspecialchars($rowStatus); ?>"><?php echo htmlspecialchars($displayStatusLabel); ?></span>
                                        </td>
                                        <td class="text-center align-middle origen-col">
                                            <?php
                                            $cname = $lead['campaign_name'] ?? '';
                                            if (preg_match('/^(c(\d+)\.|e(\d+)-)/i', $cname, $matches)) {
                                                if (!empty($matches[2])) {
                                                    echo 'C' . $matches[2];
                                                } elseif (!empty($matches[3])) {
                                                    echo 'E' . $matches[3];
                                                }
                                            } else {
                                                echo htmlspecialchars($cname);
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($lead['medio'] ?? ''); ?></td>
                                        <td data-order="<?php echo (!empty($lead['created_time']) && strtotime($lead['created_time']) !== false) ? strtotime($lead['created_time']) : 0; ?>">
                                            <?php
                                            if (!empty($lead['created_time']) && strtotime($lead['created_time']) !== false) {
                                                $ts = strtotime($lead['created_time']);
                                                $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                                                echo date('j', $ts) . ' de ' . $months[date('n', $ts) - 1] . ' del ' . date('Y', $ts);
                                            } else {
                                                echo '';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $mapKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id'] ?? 0);
                                            $assigned = isset($leadTemplateMap[$mapKey]) ? $leadTemplateMap[$mapKey] : [];
                                            $leadBadgeStatus = '';
                                            $leadBadgeStatusClass = '';
                                            $attributedTemplateId = isset($contactFormByLead[$mapKey]['marketing_template_id']) ? intval($contactFormByLead[$mapKey]['marketing_template_id']) : 0;
                                            $attributedTemplateName = $attributedTemplateId > 0 && isset($templatesById[$attributedTemplateId]) ? $templatesById[$attributedTemplateId] : ($attributedTemplateId > 0 ? ('Plantilla ' . $attributedTemplateId) : '');
                                            $showAttributedOutside = $attributedTemplateId > 0 && !in_array($attributedTemplateId, array_map('intval', $assigned), true);
                                            if ($rowStatus === 'agendado') {
                                                $leadBadgeStatus = 'Cita agendada';
                                                $leadBadgeStatusClass = 'tpl-meta-lead-status--agendado';
                                            } elseif ($rowStatus === 'atendido') {
                                                $leadBadgeStatus = 'Cita atendida';
                                                $leadBadgeStatusClass = 'tpl-meta-lead-status--atendido';
                                            }
                                            ?>
                                            <div class="templates-cell" data-tabla="<?php echo htmlspecialchars($lead['tabla_origen']); ?>" data-leadid="<?php echo intval($lead['id']); ?>" style="display:flex;align-items:center;gap:10px;min-width:0;">
                                                <div class="badges-container" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;min-width:0;">
                                                    <?php if ($showAttributedOutside && $attributedTemplateName !== ''): ?>
                                                        <span class="tpl-source-note">Agendó desde: <?php echo htmlspecialchars($attributedTemplateName); ?></span>
                                                    <?php endif; ?>
                                                    <?php foreach ($assigned as $tid): ?>
                                                        <?php
                                                            $tname = isset($templatesById[intval($tid)]) ? $templatesById[intval($tid)] : 'Plantilla ' . intval($tid);
                                                            $queueKey = ($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']) . '|' . intval($tid);
                                                            $qinfo = $leadTemplateQueueDetails[$queueKey] ?? null;
                                                            $openAt = $emailOpensMap[$queueKey] ?? null;
                                                            $isAttributedTemplate = $attributedTemplateId > 0 && intval($tid) === $attributedTemplateId;
                                                        ?>
                                                        <span class="tpl-badge<?php echo $isAttributedTemplate ? ' tpl-badge--attributed' : ''; ?>" data-templateid="<?php echo intval($tid); ?>" style="background:#eef2ff;padding:8px;border-radius:12px;font-size:0.82rem;display:inline-flex;flex-direction:column;align-items:flex-start;gap:6px;white-space:nowrap;min-width:140px;">
                                                            <div style="display:flex;align-items:center;gap:8px;width:100%;justify-content:space-between;">
                                                                <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:220px;">
                                                                    <?php echo htmlspecialchars($tname); ?>
                                                                </div>
                                                                <button class="btn-remove-tpl" data-templateid="<?php echo intval($tid); ?>" style="border:none;background:transparent;color:#d00;cursor:pointer;font-weight:700;line-height:1;padding:0 4px;">&times;</button>
                                                            </div>
                                                            <?php if ($isAttributedTemplate): ?>
                                                                <div class="tpl-meta tpl-meta-attributed">Correo que agendó</div>
                                                            <?php endif; ?>
                                                            <?php if ($leadBadgeStatus !== ''): ?>
                                                                <div class="tpl-meta tpl-meta-lead-status <?php echo htmlspecialchars($leadBadgeStatusClass); ?>"><?php echo htmlspecialchars($leadBadgeStatus); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ($qinfo):
                                                                $status = strtolower(trim((string)$qinfo['status'] ?? ''));
                                                            ?>
                                                                <?php if (!empty($qinfo['scheduled_at']) && ($status === 'pending' || $status === 'scheduled' || $status === 'error')): ?>
                                                                    <div class="tpl-meta" style="font-size:0.72rem;color:#6b7280;">Programado: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($qinfo['scheduled_at']))); ?></div>
                                                                <?php elseif ($status === 'sent'): ?>
                                                                    <?php if ($openAt): ?>
                                                                        <div class="tpl-meta" style="font-size:0.72rem;color:#16a34a;font-weight:700;">Abierto: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($openAt))); ?></div>
                                                                    <?php else: ?>
                                                                        <div class="tpl-meta" style="font-size:0.72rem;color:#6b7280;">Enviado: <?php echo htmlspecialchars(!empty($qinfo['last_sent_at']) ? date('d/m/Y H:i', strtotime($qinfo['last_sent_at'])) : date('d/m/Y H:i', strtotime($qinfo['scheduled_at'] ?? ''))); ?> <span style="color:#94a3b8;">• No visto</span></div>
                                                                    <?php endif; ?>
                                                                <?php elseif ($status === 'error'): ?>
                                                                    <div class="tpl-meta" style="font-size:0.72rem;color:#d97706;">Error</div>
                                                                <?php else: ?>
                                                                    <?php if (!empty($qinfo['scheduled_at'])): ?>
                                                                        <div class="tpl-meta" style="font-size:0.72rem;color:#6b7280;">Programado: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($qinfo['scheduled_at']))); ?></div>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <?php if (!empty($emailOpensMap[($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']) . '|' . intval($tid)])): ?>
                                                                    <div class="tpl-meta" style="font-size:0.72rem;color:#16a34a;">Abierto: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($emailOpensMap[($lead['tabla_origen'] ?? '') . '|' . intval($lead['id']) . '|' . intval($tid)]))); ?></div>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>

                                                <div style="flex-shrink:0;">
                                                    <button class="rd-btn btn-add-templates" data-tabla="<?php echo htmlspecialchars($lead['tabla_origen']); ?>" data-leadid="<?php echo intval($lead['id']); ?>">Agrega plantilla</button>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <button class="btn btn-sm btn-outline-primary btn-view-sent" data-tabla="<?php echo htmlspecialchars($lead['tabla_origen']); ?>" data-leadid="<?php echo intval($lead['id']); ?>">Historial</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Correos enviados -->
    <div class="modal fade" id="modalSentEmails" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSentEmailsTitle">Correos enviados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="modalSentEmailsBody" style="max-height:60vh; overflow:auto;">
                    <div class="text-center py-3">Cargando...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function () {
            var tableAll = $('#leadsMarketingTable').DataTable({
                paging: false,
                order: [[5, 'desc']],
                lengthChange: false,
                searching: false,
                dom: 'rtip',
                columnDefs: [
                    { orderable: false, targets: [0, 7] }, // checkbox + acciones (última columna)
                    { visible: true, targets: [2] } // columna 'Estatus' visible en la vista "Todos"
                ]
            });

            var tableAg = $('#leadsMarketingTableAgendados').DataTable({
                paging: false,
                order: [[5, 'desc']],
                lengthChange: false,
                searching: false,
                dom: 'rtip',
                columnDefs: [
                    { orderable: false, targets: [0, 7] }, // checkbox + acciones
                    { visible: true, targets: [2] } // mostrar columna 'Estatus' en Agendados
                ]
            });

            // mantener parámetro de pestaña desde la URL para marcar la pestaña activa
            window.leadsMarketingActiveTab = (new URL(window.location.href)).searchParams.get('tab') || 'all';

            // Inicializar state de tabs (UI) según URL (usando Bootstrap nav-tabs)
            (function initLeadsTabs() {
                var initial = window.leadsMarketingActiveTab || 'all';
                $('#leadsTabNav .nav-link').each(function () {
                    var t = $(this).data('tab');
                    if (t === initial) {
                        $(this).addClass('active').attr('aria-selected', 'true');
                        $('#leadsTabLabel').text($(this).text());
                    } else {
                        $(this).removeClass('active').attr('aria-selected', 'false');
                    }
                });

                // mostrar/ocultar tablas según pestaña inicial (dos tablas separadas)
                try {
                    if (initial === 'agendados') {
                        $('#leadsTableWrapperAll').hide();
                        $('#leadsTableWrapperAgendados').show();
                        tableAg.columns.adjust().draw();
                    } else {
                        $('#leadsTableWrapperAll').show();
                        $('#leadsTableWrapperAgendados').hide();
                        tableAll.columns.adjust().draw();
                    }
                } catch (e) {
                    // ignorar
                }
            })();

            // Manejar clicks en tabs (Bootstrap-style)
            $(document).on('click', '#leadsTabNav .nav-link', function (e) {
                e.preventDefault();
                var tab = $(this).data('tab') || 'all';
                window.leadsMarketingActiveTab = tab;

                // actualizar UI (activar la pestaña)
                $('#leadsTabNav .nav-link').removeClass('active').attr('aria-selected', 'false');
                $(this).addClass('active').attr('aria-selected', 'true');
                $('#leadsTabLabel').text($(this).text());

                // persistir en la URL (no recargar)
                var url = new URL(window.location.href);
                if (tab === 'all') url.searchParams.delete('tab'); else url.searchParams.set('tab', tab);
                history.replaceState(null, '', url.toString());

                // mostrar/ocultar tablas según pestaña (dos tablas separadas)
                try {
                    if (tab === 'agendados') {
                        $('#leadsTableWrapperAll').hide();
                        $('#leadsTableWrapperAgendados').show();
                        tableAg.columns.adjust().draw();
                    } else {
                        $('#leadsTableWrapperAll').show();
                        $('#leadsTableWrapperAgendados').hide();
                        tableAll.columns.adjust().draw();
                    }
                } catch (e) {}

                updateBulkSelectedCount();
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

            function updateBulkSelectedCount() {
                // sólo contar checkboxes visibles (respeta la tabla activa)
                var count = $('.lead-check:checked:visible').length;
                $('#bulkSelectedCount').text(count + ' seleccionados');
                var allVisible = $('.lead-check:visible').length;

                $('#selectAllLeads').prop('checked', $('#leadsTableWrapperAll').is(':visible') && count > 0 && count === allVisible);
                $('#selectAllLeadsAgendados').prop('checked', $('#leadsTableWrapperAgendados').is(':visible') && count > 0 && count === allVisible);
            }

            $('#selectAllLeads').on('change', function () {
                var checked = $(this).is(':checked');
                // afectar sólo filas visibles dentro de la tabla 'Todos'
                $('#leadsTableWrapperAll .lead-check:visible').prop('checked', checked);
                updateBulkSelectedCount();
            });

            $('#selectAllLeadsAgendados').on('change', function () {
                var checked = $(this).is(':checked');
                // afectar sólo filas visibles dentro de la tabla 'Agendados'
                $('#leadsTableWrapperAgendados .lead-check:visible').prop('checked', checked);
                updateBulkSelectedCount();
            });

            $(document).on('change', '.lead-check', function () {
                updateBulkSelectedCount();
            });

            // Click anywhere in the checkbox TD toggles the checkbox
            $(document).on('click', 'td.check-td', function (e) {
                if ($(e.target).is('input[type=checkbox]')) return; // already handled natively
                var $chk = $(this).find('.lead-check');
                $chk.prop('checked', !$chk.prop('checked')).trigger('change');
            });

            // bulk apply handled later (uses the new add_templates_to_lead endpoint)
            // (old behaviour that relied on single-select has been replaced)


            // Open modal (SweetAlert) to add one or more templates to a lead
            $(document).on('click', '.btn-add-templates', function () {
                var tabla = $(this).data('tabla');
                var leadId = $(this).data('leadid');
                var $cell = $(this).closest('.templates-cell');
                var assigned = [];
                $cell.find('.tpl-badge').each(function () { assigned.push(String($(this).data('templateid'))); });

                // build checkbox list from server-side templates (rendered into JS)
                var tplHtml = '';
                <?php foreach ($templates as $t): ?>
                    tplHtml += '<div style="margin:6px 0;"><label style="font-weight:600;display:inline-flex;gap:8px;align-items:center;"><input type="checkbox" class="swal-tpl-chk" value="<?php echo intval($t['id']); ?>" ' + (assigned.indexOf('<?php echo intval($t['id']); ?>') !== -1 ? 'checked' : '') + ' /> <span><?php echo htmlspecialchars($t['nombre']); ?></span></label></div>';
                <?php endforeach; ?>

                Swal.fire({
                    title: 'Selecciona plantillas',
                    html: '<div class="swal-templates-list" style="max-height:360px;overflow-y:auto;padding-right:8px;text-align:left;">' + tplHtml + '</div>',
                    showCancelButton: true,
                    confirmButtonText: 'Agregar seleccionadas',
                    width: 600,
                    willOpen: () => {
                        // mejorar accesibilidad: permitir foco en primer checkbox si existe
                        setTimeout(function () { var $first = $('.swal2-html-container .swal-tpl-chk').first(); if ($first.length) $first.focus(); }, 20);
                    },
                    preConfirm: () => {
                        var selected = [];
                        // limitar la búsqueda a los checkboxes dentro del modal
                        $('.swal2-html-container .swal-tpl-chk:checked').each(function () { selected.push($(this).val()); });
                        if (selected.length === 0) {
                            Swal.showValidationMessage('Selecciona al menos una plantilla');
                        }
                        return selected;
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    var selected = result.value || [];
                    Swal.fire({ title: 'Guardando plantillas...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                    $.post('add_templates_to_lead.php', { template_ids: selected, tabla_origen: tabla, lead_id: leadId }, function (resp) {
                        Swal.close();
                        if (!resp || !resp.success) {
                            return Swal.fire({ icon: 'error', title: 'Error', text: (resp && resp.message) ? resp.message : 'No se pudo agregar' });
                        }
                        // recargar para mostrar estado (programado/enviado/abierto)
                        Swal.fire({ icon: 'success', title: 'Plantillas agregadas' }).then(function () { window.location.reload(); });
                    }, 'json').fail(function () {
                        Swal.close();
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error en el servidor' });
                    });
                });
            });

            // templatesById map (client-side) populated from PHP
            var templatesById = {};
            <?php foreach ($templates as $t): ?>
                templatesById['<?php echo intval($t['id']); ?>'] = <?php echo json_encode($t['nombre']); ?>;
            <?php endforeach; ?>

            // remove single template badge
            $(document).on('click', '.btn-remove-tpl', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var tplId = $btn.data('templateid');
                var $cell = $btn.closest('.templates-cell');
                var tabla = $cell.data('tabla');
                var leadId = $cell.data('leadid');
                Swal.fire({ title: 'Eliminar plantilla?', text: '¿Eliminar esta plantilla para el lead?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar' }).then(function (res) {
                    if (!res.isConfirmed) return;
                    $.post('remove_template_from_lead.php', { template_id: tplId, tabla_origen: tabla, lead_id: leadId }, function (resp) {
                        if (!resp || !resp.success) {
                            return Swal.fire({ icon: 'error', title: 'Error', text: (resp && resp.message) ? resp.message : 'No se pudo eliminar' });
                        }
                        // remove badge from DOM
                        $btn.closest('.tpl-badge').remove();
                        Swal.fire({ icon: 'success', title: 'Eliminado' });
                    }, 'json').fail(function () {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error en el servidor' });
                    });
                });
            });

            // update bulk apply behaviour: open template-checkbox modal, then call add_templates_to_lead for each checked lead
            $('#bulkApplyBtn').off('click').on('click', function () {
                var $checks = $('.lead-check:checked');
                if ($checks.length === 0) {
                    return Swal.fire({ icon: 'info', title: 'Sin selección', text: 'Selecciona al menos un lead antes de aplicar plantillas.' });
                }

                // build checkbox list from all available templates
                var tplHtml = '';
                <?php foreach ($templates as $t): ?>
                    tplHtml += '<div style="margin:6px 0;"><label style="font-weight:600;display:inline-flex;gap:8px;align-items:center;cursor:pointer;"><input type="checkbox" class="swal-bulk-tpl-chk" value="<?php echo intval($t['id']); ?>" /> <span><?php echo htmlspecialchars($t['nombre']); ?></span></label></div>';
                <?php endforeach; ?>

                Swal.fire({
                    title: 'Selecciona plantillas a asignar',
                    html: '<div style="text-align:left;margin-bottom:8px;font-size:0.8rem;color:#64748b;">Se asignarán a <strong>' + $checks.length + '</strong> lead(s) seleccionado(s)</div>' +
                          '<div class="swal-templates-list" style="max-height:360px;overflow-y:auto;padding-right:8px;text-align:left;">' + tplHtml + '</div>',
                    showCancelButton: true,
                    confirmButtonText: 'Aplicar a ' + $checks.length + ' lead(s)',
                    cancelButtonText: 'Cancelar',
                    width: 600,
                    willOpen: () => {
                        setTimeout(function () { var $first = $('.swal2-html-container .swal-bulk-tpl-chk').first(); if ($first.length) $first.focus(); }, 20);
                    },
                    preConfirm: () => {
                        var selected = [];
                        $('.swal2-html-container .swal-bulk-tpl-chk:checked').each(function () { selected.push($(this).val()); });
                        if (selected.length === 0) {
                            Swal.showValidationMessage('Debes seleccionar al menos una plantilla.');
                            return false;
                        }
                        return selected;
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    var selectedTplIds = result.value || [];

                    Swal.fire({ title: 'Aplicando plantillas...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                    var requests = [];
                    $checks.each(function () {
                        var leadId = $(this).data('leadid');
                        var tabla = $(this).data('tabla');
                        requests.push($.post('add_templates_to_lead.php', { template_ids: selectedTplIds, tabla_origen: tabla, lead_id: leadId }));
                    });

                    $.when.apply($, requests).done(function () {
                        // Collect actual added counts from all responses
                        var args = Array.prototype.slice.call(arguments);
                        var totalAdded = 0;
                        var responses = (requests.length === 1) ? [args] : args;
                        responses.forEach(function (r) {
                            var resp = Array.isArray(r) ? r[0] : r;
                            if (resp && resp.added) totalAdded += resp.added.length;
                        });
                        Swal.close();
                        if (totalAdded === 0) {
                            Swal.fire({ icon: 'warning', title: 'Sin cambios', text: 'Las plantillas seleccionadas ya estaban asignadas o no se pudieron agregar.' });
                        } else {
                            Swal.fire({ icon: 'success', title: 'Plantillas aplicadas', text: totalAdded + ' asignación(es) realizada(s) en ' + $checks.length + ' lead(s).' }).then(function () { window.location.reload(); });
                        }
                    }).fail(function () {
                        Swal.close();
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Ocurrió un error al aplicar plantillas' });
                    });
                });
            });

            // show sent emails modal
            $('#leadsMarketingTable, #leadsMarketingTableAgendados').on('click', '.btn-view-sent', function () {
                var tabla = $(this).data('tabla');
                var leadId = $(this).data('leadid');
                var $body = $('#modalSentEmailsBody');
                $('#modalSentEmailsTitle').text('Correos enviados — Lead ' + leadId);
                $body.html('<div class="text-center py-3">Cargando...</div>');
                var modal = new bootstrap.Modal(document.getElementById('modalSentEmails'));
                modal.show();

                $.post('fetch_sent_emails.php', { tabla_origen: tabla, lead_id: leadId }, function (resp) {
                    if (!resp || !resp.success) {
                        $body.html('<div class="text-danger p-3">Error: ' + (resp && resp.message ? resp.message : 'Sin datos') + '</div>');
                        return;
                    }
                    var rows = resp.data || [];
                    if (!rows.length) {
                        $body.html('<div class="text-muted p-3">No se encontraron envíos para este lead.</div>');
                        return;
                    }
                    var html = '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Plantilla</th><th>Enviado</th><th>Éxito</th><th>Mensaje error</th></tr></thead><tbody>';
                    rows.forEach(function (r) {
                        var sentAt = r.sent_at ? r.sent_at : '';
                        var success = r.success == 1 ? '<span class="text-success">Sí</span>' : '<span class="text-danger">No</span>';
                        var err = r.mail_error ? $('<div>').text(r.mail_error).html() : '';
                        html += '<tr><td>' + $('<div>').text(r.template_nombre).html() + '</td><td>' + $('<div>').text(sentAt).html() + '</td><td>' + success + '</td><td>' + err + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                    $body.html(html);
                }, 'json').fail(function () {
                    $body.html('<div class="text-danger p-3">Error al obtener datos del servidor</div>');
                });
            });
        });
    </script>
</body>

</html>