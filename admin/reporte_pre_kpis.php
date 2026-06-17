<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';

function formatCreatedTime_rk($dateString)
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

function isB1B2CampaignName_rk($campaignName)
{
    if ($campaignName === null)
        return false;
    $v = strtolower(trim((string) $campaignName));
    if ($v === '')
        return false;
    $v = preg_replace('/\s+/', ' ', $v);
    return in_array($v, ['b1', 'b1 (usa)', 'b1 usa', 'b2', 'b2 (mx)', 'b2 mx'], true);
}

function normalizeFirstContactChannelLabel_rk($value)
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

function resolveLeadStatus_rk($tableName, $contactFormRow, $appointmentRow)
{
    if (isset($contactFormRow['cliente']) && intval($contactFormRow['cliente']) === 1) {
        return 'cliente';
    }
    if ($appointmentRow === null || !isset($appointmentRow['estatus'])) {
        return 'lead';
    }
    if (
        strcasecmp((string) $tableName, 'wedding_planners') === 0 ||
        strcasecmp((string) $tableName, 'wp_eventos_afianzados') === 0
    ) {
        return 'agendado';
    }
    $rawStatus = $appointmentRow['estatus'];
    $intStatus = is_numeric($rawStatus) ? intval($rawStatus) : null;
    if ($intStatus === 1) return 'atendido';
    if ($intStatus === 3) return 'muerto';
    if ($intStatus === 0) return 'agendado';
    if ($intStatus === 2) return 'fantasma';
    return is_string($rawStatus) && trim($rawStatus) !== '' ? $rawStatus : 'agendado';
}

// Leer filtro de plataforma (viene por GET)
$filterPlataforma = isset($_GET['filter_plataforma']) ? trim($_GET['filter_plataforma']) : '';
// Leer filtros de fecha y búsqueda (vienen por GET)
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Persistir filtros de fecha en sesión
if (isset($_GET['show_all'])) {
    unset($_SESSION['leads_filter_start'], $_SESSION['leads_filter_end']);
} elseif ($startDate !== '' || $endDate !== '') {
    $_SESSION['leads_filter_start'] = $startDate;
    $_SESSION['leads_filter_end']   = $endDate;
} elseif (!empty($_SESSION['leads_filter_start']) || !empty($_SESSION['leads_filter_end'])) {
    $startDate = $_SESSION['leads_filter_start'] ?? '';
    $endDate   = $_SESSION['leads_filter_end']   ?? '';
}

// Default: últimos 14 días
if ($startDate === '' && $endDate === '' && empty($_GET['show_all'])) {
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-14 days', strtotime($endDate)));
}

$platformLabel = 'todo';
if ($filterPlataforma === 'campania') {
    $platformLabel = 'campañas digitales';
} elseif ($filterPlataforma === 'organico') {
    $platformLabel = 'organico';
}

// Obtener tablas de leads según el filtro de plataforma
$tablas = [];
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
        $tablas[] = $row['nombre'];
    }
}

// Consultar todos los leads de todas las tablas
$allLeads = [];
$b1b2Values = ['b1', 'b1 (usa)', 'b1 usa', 'b2', 'b2 (mx)', 'b2 mx'];

foreach ($tablas as $tableName) {
    $checkTable = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    if ($checkTable->num_rows > 0) {
        $columns = [];
        $columnsResult = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tableName) . "`");
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }

        $whereParts = [];

        if (in_array('descartado', $columns)) {
            $whereParts[] = "(descartado = 0 OR descartado IS NULL)";
        }

        if (($startDate !== '' || $endDate !== '') && in_array('created_time', $columns)) {
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
                if (in_array($colName, $columns)) {
                    $searchCols[] = "IFNULL(`$colName`, '')";
                }
            }
            if (!empty($searchCols)) {
                $q = $conn->real_escape_string(mb_strtolower($searchQuery, 'UTF-8'));
                $whereParts[] = "LOWER(CONCAT_WS(' ', " . implode(', ', $searchCols) . ")) LIKE '%" . $q . "%'";
            }
        }

        if ($tableName === 'organic_leads' && in_array('campaign_name', $columns)) {
            $campaignCol = "LOWER(TRIM(campaign_name))";
            $inList = "'" . implode("','", array_map([$conn, 'real_escape_string'], $b1b2Values)) . "'";
            if ($filterPlataforma === 'organico') {
                $whereParts[] = "($campaignCol IS NULL OR $campaignCol = '' OR $campaignCol NOT IN ($inList))";
            } elseif ($filterPlataforma === 'campania') {
                $whereParts[] = "$campaignCol IN ($inList)";
            }
        }

        $whereSql = !empty($whereParts) ? implode(' AND ', $whereParts) : '1=1';
        $orderCol = in_array('created_time', $columns) ? 'created_time' : (in_array('id', $columns) ? 'id' : null);
        $orderSql = $orderCol ? " ORDER BY `$orderCol` DESC" : '';
        $sqlLeads = "SELECT *, '$tableName' as tabla_origen FROM `" . $conn->real_escape_string($tableName) . "` WHERE " . $whereSql . $orderSql;

        $resultLeads = $conn->query($sqlLeads);
        if ($resultLeads && $resultLeads->num_rows > 0) {
            while ($lead = $resultLeads->fetch_assoc()) {
                $allLeads[] = $lead;
            }
        }
    }
}

// Aplicar filtros a los leads
$filteredLeads = [];
foreach ($allLeads as $lead) {
    $campaignName = $lead['campaign_name'] ?? '';
    $isB1B2 = isB1B2CampaignName_rk($campaignName);
    $tablaOrigen = $lead['tabla_origen'] ?? '';

    if ($filterPlataforma === 'organico' && $isB1B2) continue;
    if ($filterPlataforma === 'campania' && $tablaOrigen === 'organic_leads' && !$isB1B2) continue;

    $filteredLeads[] = $lead;
}

// Filtro por búsqueda
$normalizedSearch = mb_strtolower($searchQuery, 'UTF-8');
if ($normalizedSearch !== '') {
    $filteredBySearch = [];
    foreach ($filteredLeads as $lead) {
        $haystack = trim(
            ($lead['full_name'] ?? '') . ' ' .
            ($lead['email'] ?? '') . ' ' .
            ($lead['phone'] ?? '') . ' ' .
            ($lead['campaign_name'] ?? '') . ' ' .
            ($lead['form_name'] ?? '') . ' ' .
            ($lead['platform'] ?? '') . ' ' .
            ($lead['tabla_origen'] ?? '')
        );
        if ($haystack !== '' && mb_stripos($haystack, $normalizedSearch, 0, 'UTF-8') !== false) {
            $filteredBySearch[] = $lead;
        }
    }
    $filteredLeads = $filteredBySearch;
}

// Ordenar por created_time (más recientes primero)
usort($filteredLeads, function ($a, $b) {
    $ta = (!empty($a['created_time']) && strtotime($a['created_time']) !== false) ? strtotime($a['created_time']) : 0;
    $tb = (!empty($b['created_time']) && strtotime($b['created_time']) !== false) ? strtotime($b['created_time']) : 0;
    if ($ta === $tb) return 0;
    return ($ta > $tb) ? -1 : 1;
});

$totalCount = count($filteredLeads);

// Construir mapa por fecha
$map = [];
$minTs = null;
$maxTs = null;
foreach ($filteredLeads as $lead) {
    if (empty($lead['created_time'])) continue;
    $ts = strtotime($lead['created_time']);
    if ($ts === false) continue;
    $d = date('Y-m-d', $ts);
    if (!isset($map[$d])) $map[$d] = 0;
    $map[$d]++;
    if ($minTs === null || $ts < $minTs) $minTs = $ts;
    if ($maxTs === null || $ts > $maxTs) $maxTs = $ts;
}

if ($minTs === null) {
    $datesJson = json_encode([]);
    $countsJson = json_encode([]);
} else {
    $start = strtotime(date('Y-m-d', $minTs));
    $end = strtotime(date('Y-m-d', $maxTs));
    $dates = [];
    $counts = [];
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $d = date('Y-m-d', $ts);
        $c = isset($map[$d]) ? $map[$d] : 0;
        $dates[] = $d;
        $counts[] = $c;
    }
    $datesJson = json_encode($dates);
    $countsJson = json_encode($counts);
}

$leadIdsByTable = [];
foreach ($filteredLeads as $lead) {
    $t = $lead['tabla_origen'] ?? '';
    $id = intval($lead['id'] ?? 0);
    if ($t === '' || $id <= 0) continue;
    if (!isset($leadIdsByTable[$t])) $leadIdsByTable[$t] = [];
    $leadIdsByTable[$t][] = $id;
}

// Build contact_form map
$contactFormByLead = [];
$cfIds = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '') continue;
    $sql = "SELECT id, original_lead_id, cliente, how_did_you_meet, hear_about_us, engagement, first_contact_channel FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")";
    $resCf = $conn->query($sql);
    if ($resCf) {
        while ($row = $resCf->fetch_assoc()) {
            $key = $t . '|' . intval($row['original_lead_id']);
            $contactFormByLead[$key] = [
                'cf_id'                => intval($row['id']),
                'cliente'              => isset($row['cliente']) ? intval($row['cliente']) : 0,
                'how_did_you_meet'     => $row['how_did_you_meet'] ?? null,
                'hear_about_us'        => $row['hear_about_us'] ?? null,
                'engagement'           => $row['engagement'] ?? null,
                'first_contact_channel'=> $row['first_contact_channel'] ?? null,
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
            if ($idclie <= 0) continue;
            if (!isset($appointmentsByCFId[$idclie])) {
                $appointmentsByCFId[$idclie] = $ar;
            } else {
                $prev = $appointmentsByCFId[$idclie];
                $replace = false;
                if (!empty($ar['fecha']) && !empty($prev['fecha'])) {
                    $t1 = strtotime($ar['fecha'] . ' ' . ($ar['hora'] ?? '')) ?: 0;
                    $t2 = strtotime($prev['fecha'] . ' ' . ($prev['hora'] ?? '')) ?: 0;
                    if ($t1 > $t2) $replace = true;
                } elseif (!empty($ar['id']) && !empty($prev['id'])) {
                    if (intval($ar['id']) > intval($prev['id'])) $replace = true;
                }
                if ($replace) $appointmentsByCFId[$idclie] = $ar;
            }
        }
    }
}

// Build status map
$leadStatusMap = [];
foreach ($leadIdsByTable as $t => $ids) {
    foreach ($ids as $lid) {
        $key = $t . '|' . intval($lid);
        $leadStatusMap[$key] = 'lead';
        if (isset($contactFormByLead[$key])) {
            $cf = $contactFormByLead[$key];
            $cfId = intval($cf['cf_id']);
            $appointment = ($cfId > 0 && isset($appointmentsByCFId[$cfId])) ? $appointmentsByCFId[$cfId] : null;
            $leadStatusMap[$key] = resolveLeadStatus_rk($t, $cf, $appointment);
        }
    }
}

// Calcular leads atendidos y clientes
$leadsAtendidosTotal = 0;
$leadsClientesTotal = 0;
$totalLeadsBeforeStatusFilter = count($filteredLeads);
foreach ($filteredLeads as $lead) {
    $tabla = $lead['tabla_origen'] ?? '';
    $lid = isset($lead['id']) ? intval($lead['id']) : 0;
    $key = $tabla . '|' . $lid;
    if (isset($leadStatusMap[$key])) {
        $status = strtolower($leadStatusMap[$key]);
        if ($status === 'atendido') $leadsAtendidosTotal++;
        if ($status === 'cliente') $leadsClientesTotal++;
    }
}

// Contar "Personas que agendaron" directamente desde contact_form + calendario
$_tablaTipoMapAgd = [];
$_rTablasAgd = $conn->query("SELECT nombre, tipo FROM tablas_leads");
if ($_rTablasAgd && $_rTablasAgd->num_rows > 0) {
    while ($_rT = $_rTablasAgd->fetch_assoc()) {
        $_tablaTipoMapAgd[$_rT['nombre']] = intval($_rT['tipo']);
    }
}

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

            if (isset($_apptByCf[$cfId])) {
                $apptFecha = trim($_apptByCf[$cfId]['fecha'] ?? '');
                $apptHora = trim($_apptByCf[$cfId]['hora'] ?? '');
                $apptTs = ($apptFecha !== '') ? strtotime($apptFecha . ' ' . $apptHora) : false;
                if ($apptFecha === '' || $apptFecha === '0000-00-00' || $apptTs === false || $apptTs <= 0) continue;
            }

            $dateField = !empty($_cf['created_time']) ? $_cf['created_time'] : ($_cf['submission_date'] ?? '');
            if (!empty($tablaOrigen) && $origId > 0) {
                $safeTbl = $conn->real_escape_string($tablaOrigen);
                $checkTbl = $conn->query("SHOW TABLES LIKE '$safeTbl'");
                if ($checkTbl && $checkTbl->num_rows > 0) {
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

            if ($startDate !== '' || $endDate !== '') {
                if (empty($dateField)) continue;
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

            if ($filterPlataforma !== '') {
                $tipoTabla = isset($_tablaTipoMapAgd[$tablaOrigen]) ? $_tablaTipoMapAgd[$tablaOrigen] : -1;
                $campaignNameLower = strtolower(trim($_cf['campaign_name'] ?? ''));
                $isB1B2 = isB1B2CampaignName_rk($campaignNameLower);
                if ($filterPlataforma === 'organico') {
                    if ($isB1B2) continue;
                    if ($tipoTabla !== 0 && $campaignNameLower !== 'website' && $campaignNameLower !== 'reg manual') continue;
                }
                if ($filterPlataforma === 'campania') {
                    if ($tipoTabla !== 1 && !$isB1B2) continue;
                }
            }

            $apptStatus = $_apptByCf[$cfId]['estatus'] ?? null;
            $intStatus = is_numeric($apptStatus) ? intval($apptStatus) : null;
            $isCliente = (isset($_cf['cliente']) && intval($_cf['cliente']) === 1);
            if ($isCliente || ($intStatus !== null && in_array($intStatus, [0, 1, 2, 3], true))) {
                $leadsAgendadosTotal++;
            }
        }
    }
}
unset($_apptByCf, $_tablaTipoMapAgd);

$tasaConversion = $totalLeadsBeforeStatusFilter > 0 ? round(($leadsAgendadosTotal / $totalLeadsBeforeStatusFilter) * 100, 2) : 0;
$tasaAsistenciaPostQ = $leadsAgendadosTotal > 0 ? round(($leadsAtendidosTotal / $leadsAgendadosTotal) * 100, 2) : 0;
$tasaConversionClientes = $leadsAtendidosTotal > 0 ? round(($leadsClientesTotal / $leadsAtendidosTotal) * 100, 2) : 0;

// Recalcular conteos finales
$totalCount = count($filteredLeads);
$todayCount = 0;
$today = date('Y-m-d');
foreach ($filteredLeads as $lead) {
    if (empty($lead['created_time'])) continue;
    $ts = strtotime($lead['created_time']);
    if ($ts === false) continue;
    if (date('Y-m-d', $ts) === $today) $todayCount++;
}

// Reconstruir mapa por fecha
$map = [];
$minTs = null;
$maxTs = null;
foreach ($filteredLeads as $lead) {
    if (empty($lead['created_time'])) continue;
    $ts = strtotime($lead['created_time']);
    if ($ts === false) continue;
    $d = date('Y-m-d', $ts);
    if (!isset($map[$d])) $map[$d] = 0;
    $map[$d]++;
    if ($minTs === null || $ts < $minTs) $minTs = $ts;
    if ($maxTs === null || $ts > $maxTs) $maxTs = $ts;
}

if ($minTs === null) {
    $datesJson = json_encode([]);
    $countsJson = json_encode([]);
} else {
    $start = strtotime(date('Y-m-d', $minTs));
    $end = strtotime(date('Y-m-d', $maxTs));
    $dates = [];
    $counts = [];
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $d = date('Y-m-d', $ts);
        $c = isset($map[$d]) ? $map[$d] : 0;
        $dates[] = $d;
        $counts[] = $c;
    }
    $datesJson = json_encode($dates);
    $countsJson = json_encode($counts);
}

// Datos para gráfica de método de contacto
$contactMethodCounts = [];
foreach ($filteredLeads as $lead) {
    $raw = trim((string)($lead['first_contact_channel'] ?? ''));
    $tabla = $lead['tabla_origen'] ?? '';
    $lid = isset($lead['id']) ? intval($lead['id']) : 0;
    $key = $tabla . '|' . $lid;
    // Enriquecer con contact_form si falta
    if ($raw === '' && isset($contactFormByLead[$key]) && !empty($contactFormByLead[$key]['first_contact_channel'])) {
        $raw = trim((string)$contactFormByLead[$key]['first_contact_channel']);
    }
    $st = isset($leadStatusMap[$key]) ? strtolower($leadStatusMap[$key]) : 'lead';
    $contactLabel = normalizeFirstContactChannelLabel_rk($raw);
    if (!isset($contactMethodCounts[$contactLabel])) $contactMethodCounts[$contactLabel] = 0;
    $contactMethodCounts[$contactLabel]++;
}
arsort($contactMethodCounts);
$contactMethodPieData = [];
foreach ($contactMethodCounts as $label => $count) {
    if ($count <= 0) continue;
    $entry = ['name' => $label, 'y' => $count];
    $contactMethodPieData[] = $entry;
}
$contactMethodPieJson = json_encode($contactMethodPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Datos para gráfica "Desde cuándo nos conoce"
$knownUsCounts = [];
foreach ($filteredLeads as $lead) {
    $raw = trim($lead['how_long_known_us'] ?? '');
    $tabla = $lead['tabla_origen'] ?? '';
    $lid = isset($lead['id']) ? intval($lead['id']) : 0;
    $key = $tabla . '|' . $lid;
    $st = isset($leadStatusMap[$key]) ? strtolower($leadStatusMap[$key]) : 'lead';
    if ($raw === '' || $raw === '—') {
        $label = 'Sin dato';
    } else {
        $knownUsLabelMap = [
            'less than 3 months'          => 'Menos de 3 meses',
            'between 3 months and 1 year' => 'Entre 3 meses y 1 año',
            'more than 1 year'            => 'Más de 1 año',
            'not asked'                   => 'No se preguntó',
        ];
        $label = $knownUsLabelMap[mb_strtolower($raw, 'UTF-8')] ?? $raw;
    }
    $knownUsCounts[$label] = ($knownUsCounts[$label] ?? 0) + 1;
}
arsort($knownUsCounts);
$knownUsPieData = [];
foreach ($knownUsCounts as $label => $count) {
    if ($count <= 0) continue;
    $entry = ['name' => $label, 'y' => $count];
    $knownUsPieData[] = $entry;
}
$knownUsPieJson = json_encode($knownUsPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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
    <title>Reporte KPIs</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <style>
/* NEW EFEGE REPORT DASHBOARD STYLES (Light Mode Adapted) */
:root {
  --gold: #C5A028;
  --gold-dim: rgba(197,160,40,0.12);
  --ink: #1E293B;
  --muted: #64748B;
  --surface: #F8FAFC;
  --border: #E2E8F0;
  --planner: #7C3AED;
  --planner-bg: rgba(124,58,237,0.10);
  --community: #059669;
  --community-bg: rgba(5,150,105,0.10);
  --newmarket: #2563EB;
  --newmarket-bg: rgba(37,99,235,0.10);
  --eng-low: #F59E0B;
  --eng-mid: #3B82F6;
  --eng-high: #10B981;
  --dark: #F8FAFC;
}

body { background: var(--dark, #F8FAFC) !important; }

.efege-page {
    width: 100%;
    margin: 0 auto;
    padding: 18px 24px 40px;
}

.efege-page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 8px;
}
.efege-page-header-left { flex: 1; min-width: 0; }
.efege-page-header-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.efege-page-title {
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 22px;
    font-weight: 700;
    color: var(--ink);
    letter-spacing: -0.3px;
    line-height: 1.25;
}
.efege-page-title-sub { color: var(--ink); }
.efege-page-subtitle { font-size: 12px; color: var(--muted); margin-top: 4px; }
.efege-date-range {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--gold-dim);
    color: var(--gold);
    font-size: 12px;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 20px;
}
.efege-date-range span { color: var(--ink); font-weight: 600; }
.efege-live-badge {
    font-size: 11px;
    font-weight: 700;
    color: #dc2626;
    background: rgba(220,38,38,0.08);
    padding: 4px 10px;
    border-radius: 20px;
}

/* ── EFEGE FILTER BAR ── */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    border-radius: 12px;
    background: #fff;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    position: sticky;
    top: 20px;
    z-index: 100;
}
.filter-label {
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--muted);
}
.efege-filter-input,
.efege-filter-select {
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: var(--ink);
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 7px 12px;
    outline: none;
    transition: border-color 0.2s;
}
.efege-filter-input:focus,
.efege-filter-select:focus { border-color: var(--gold); }
.efege-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 13px;
    font-weight: 600;
    padding: 7px 16px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--ink);
    cursor: pointer;
    text-decoration: none;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
    white-space: nowrap;
}
.efege-btn:hover { border-color: var(--gold); color: var(--gold); text-decoration: none; }
.efege-btn-primary { background: var(--gold); border-color: var(--gold); color: #fff; }
.efege-btn-primary:hover { background: #b8921f; border-color: #b8921f; color: #fff; }

/* ── REPORTS SECTION ── */
.reports-section {
    margin-top: 18px;
    background: #fff;
    border-radius: 14px;
    padding: 28px 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    border: 1px solid var(--border);
}
.reports-section-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 22px;
}
.reports-section-step {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--gold);
    margin-bottom: 4px;
}
.reports-section-title {
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 18px;
    font-weight: 700;
    color: var(--ink);
}
.reports-section-subtitle {
    font-size: 13px;
    color: var(--muted);
    margin-top: 2px;
}
.reports-range-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--gold-dim);
    color: var(--gold);
    font-size: 12px;
    font-weight: 600;
    padding: 5px 14px;
    border-radius: 20px;
    white-space: nowrap;
}
.reports-layout {
    display: flex;
    flex-direction: column;
    gap: 20px;
    width: 100%;
}
.reports-kpi-stack {
    display: flex;
    flex-direction: row;
    gap: 14px;
    width: 100%;
}
.reports-kpi-stack > * {
    flex: 1 1 0;
    min-width: 0;
}
.reports-kpi-grid-secondary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.report-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 18px 16px;
    border: 1px solid var(--border);
}
.report-card-highlight {
    background: linear-gradient(135deg, #fdf6e3 0%, #fff 100%);
    border-color: var(--gold);
}
.report-card-label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: var(--muted);
    margin-bottom: 6px;
}
.report-card-value {
    font-size: 28px;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -0.5px;
    line-height: 1.1;
}
.report-card-note {
    font-size: 12px;
    color: var(--muted);
    margin-top: 4px;
}
.report-formula {
    font-size: 11px;
    color: var(--muted);
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid var(--border);
}
.report-formula strong { color: var(--ink); }
.report-chart-card {
    background: var(--surface);
    border-radius: 12px;
    padding: 18px 16px;
    border: 1px solid var(--border);
}
.report-chart-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}
.report-chart-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--ink);
    margin: 0;
}
.report-chart-subtitle {
    font-size: 12px;
    color: var(--muted);
    margin-top: 2px;
}
.report-chart {
    min-height: 260px;
}
.reports-charts-row {
    display: flex;
    gap: 14px;
}
.reports-charts-row .report-chart-card {
    flex: 1 1 0;
    min-width: 0;
}

@media (max-width: 900px) {
    .reports-kpi-stack { flex-direction: column; }
    .reports-kpi-grid-secondary { grid-template-columns: 1fr; }
    .reports-charts-row { flex-direction: column; }
}
    </style>
</head>
<body>
<div class="efege-page">

    <!-- PAGE HEADER -->
    <div class="efege-page-header">
        <div class="efege-page-header-left">
            <div class="efege-page-title"><span class="efege-page-title-sub">Reporte KPIs</span></div>
            <div class="efege-page-subtitle">Reporte de tasa de calificación · Plataforma: <strong><?php echo htmlspecialchars($platformLabel); ?></strong></div>
        </div>
        <div class="efege-page-header-right">
            <?php if ($startDate && $endDate): ?>
            <div class="efege-date-range">📅 <span><?php echo date('d M Y', strtotime($startDate)); ?></span>&nbsp;→&nbsp;<span><?php echo date('d M Y', strtotime($endDate)); ?></span></div>
            <?php endif; ?>
            <div class="efege-live-badge">🔴 Live</div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <br>
    <form method="get" class="filter-bar" id="filterForm">
        <span class="filter-label">Filtrar</span>
        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
        <input type="date" name="start_date" class="efege-filter-input"
            value="<?php echo htmlspecialchars($startDate ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Desde">
        <input type="date" name="end_date" class="efege-filter-input"
            value="<?php echo htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Hasta">
        <button type="submit" class="efege-btn efege-btn-primary" style="background:#0f172a; border-color:#0f172a; color:#fff;">
            <i class="fas fa-filter"></i> Filtrar
        </button>
        <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>?show_all=1" class="efege-btn">Limpiar</a>
    </form>

    <!-- REPORTS SECTION -->
    <section class="reports-section">
        <div class="reports-section-header">
            <div>
                <div class="reports-section-step">Apartado de Reportes</div>
                <h2 class="reports-section-title">Reporte de tasa de calificación</h2>
                <p class="reports-section-subtitle">Usa el filtro por rango de fechas de arriba para actualizar este reporte.</p>
            </div>
            <div class="reports-range-chip">
                <i class="bi bi-calendar3"></i>
                <span id="report-period-text"><?php echo htmlspecialchars($reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <div class="reports-layout">
            <div class="reports-kpi-stack">
                <article class="report-card report-card-highlight">
                    <div class="report-card-label">Tasa de calificación</div>
                    <div class="report-card-value" id="report-qualification-rate"><?php echo $tasaConversion; ?>%</div>
                    <div class="report-card-note" id="report-qualification-detail"><?php echo number_format($leadsAgendadosTotal); ?> personas que agendaron de <?php echo number_format($totalCount); ?> registros</div>
                    <div class="report-formula"><strong>Fórmula:</strong> (Cantidad de personas que agendaron / Total de registros) x 100</div>
                </article>

                <div class="reports-kpi-grid-secondary">
                    <article class="report-card">
                        <div class="report-card-label">Total de registros</div>
                        <div class="report-card-value" id="report-total-registros"><?php echo number_format($totalCount); ?></div>
                        <div class="report-card-note">Leads incluidos en el rango seleccionado.</div>
                    </article>

                    <article class="report-card">
                        <div class="report-card-label">Personas que agendaron</div>
                        <div class="report-card-value" id="report-agendaron-count"><?php echo number_format($leadsAgendadosTotal); ?></div>
                        <div class="report-card-note">Registros con estatus agendado, atendido, fantasma o muerto.</div>
                    </article>
                </div>
            </div>

            <div class="reports-charts-row" style="flex-direction:column;width:100%;">
                <article class="report-chart-card" style="width:100%;">
                    <div class="report-chart-header">
                        <div>
                            <h3 class="report-chart-title">Conteo total de registros por periodo</h3>
                            <p class="report-chart-subtitle">Se muestra el total de leads por día dentro del rango seleccionado.</p>
                        </div>
                    </div>
                    <div id="leadsChart" class="report-chart"></div>
                </article>
            </div>
        </div>

        <div style="display:flex;gap:12px;width:100%;margin-top:16px;">
            <article class="report-chart-card" style="flex:1 1 0;min-width:0;">
                <div class="report-chart-header">
                    <div>
                        <h3 class="report-chart-title">Método de contacto</h3>
                        <p class="report-chart-subtitle">Distribución de leads según canal de origen del registro.</p>
                    </div>
                </div>
                <div id="contactMethodChart" class="report-chart"></div>
            </article>

            <article class="report-chart-card" style="flex:1 1 0;min-width:0;">
                <div class="report-chart-header">
                    <div>
                        <h3 class="report-chart-title">Desde cuándo nos conoce</h3>
                        <p class="report-chart-subtitle">Distribución de leads según el tiempo que llevan conociendo a Efege.</p>
                    </div>
                </div>
                <div id="knownUsChart" class="report-chart"></div>
            </article>
        </div>
    </section>

</div><!-- /efege-page -->

<script src="assets/static/js/components/dark.js"></script>
<script src="assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="assets/compiled/js/app.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var chartDates = <?php echo isset($datesJson) ? $datesJson : '[]'; ?>;
    var chartCounts = <?php echo isset($countsJson) ? $countsJson : '[]'; ?>;
    var contactMethodSeries = <?php echo isset($contactMethodPieJson) ? $contactMethodPieJson : '[]'; ?>;
    var knownUsSeries = <?php echo isset($knownUsPieJson) ? $knownUsPieJson : '[]'; ?>;

    var maxLeads = 0;
    chartCounts.forEach(function (count) {
        if (count > maxLeads) maxLeads = count;
    });
    var yAxisMax = Math.ceil(maxLeads * 1.15);
    if (yAxisMax < 5) yAxisMax = 5;
    var tickInterval = Math.ceil(yAxisMax / 5);
    if (tickInterval < 1) tickInterval = 1;

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

    Highcharts.chart('contactMethodChart', {
        chart: {
            type: 'pie',
            backgroundColor: '#f8f9fa',
            borderRadius: 12,
            spacingTop: 10,
            spacingRight: 10,
            spacingBottom: 10,
            spacingLeft: 10
        },
        title: { text: null },
        colors: ['#16a34a', '#2563eb', '#f59e0b', '#c026d3', '#0284c7', '#94a3b8'],
        tooltip: {
            pointFormat: '<b>{point.y}</b> registros ({point.percentage:.1f}%)'
        },
        accessibility: {
            point: { valueSuffix: '%' }
        },
        plotOptions: {
            pie: {
                innerSize: '48%',
                allowPointSelect: true,
                cursor: 'pointer',
                dataLabels: {
                    enabled: true,
                    format: '<b>{point.name}</b><br>{point.y} registros'
                },
                showInLegend: true
            }
        },
        legend: {
            align: 'center',
            verticalAlign: 'bottom',
            itemStyle: { fontSize: '11px' }
        },
        series: [{
            name: 'Método de contacto',
            colorByPoint: true,
            data: contactMethodSeries
        }],
        credits: { enabled: false }
    });

    Highcharts.chart('knownUsChart', {
        chart: {
            type: 'pie',
            backgroundColor: '#f8f9fa',
            borderRadius: 12,
            spacingTop: 10,
            spacingRight: 10,
            spacingBottom: 10,
            spacingLeft: 10
        },
        title: { text: null },
        colors: ['#16a34a', '#2563eb', '#f59e0b', '#c026d3', '#0284c7', '#94a3b8'],
        tooltip: {
            pointFormat: '<b>{point.y}</b> registros ({point.percentage:.1f}%)'
        },
        accessibility: {
            point: { valueSuffix: '%' }
        },
        plotOptions: {
            pie: {
                innerSize: '48%',
                allowPointSelect: true,
                cursor: 'pointer',
                dataLabels: {
                    enabled: true,
                    format: '<b>{point.name}</b><br>{point.y} registros'
                },
                showInLegend: true
            }
        },
        legend: {
            align: 'center',
            verticalAlign: 'bottom',
            itemStyle: { fontSize: '11px' }
        },
        series: [{
            name: 'Desde cuándo nos conoce',
            colorByPoint: true,
            data: knownUsSeries
        }],
        credits: { enabled: false }
    });
});
</script>
</body>
</html>
