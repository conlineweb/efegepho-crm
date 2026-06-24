<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ── Interaction-card helpers (mirrors planner_profile.php) ──────────────────
function idxNormalizeKey($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','ñ'=>'n','Ñ'=>'n'];
    return strtolower(strtr($value, $map));
}
function idxTypeClass($type) {
    $k = idxNormalizeKey($type);
    if ($k === 'llamada')       return 'type-llamada';
    if ($k === 'whatsapp')      return 'type-whatsapp';
    if ($k === 'email')         return 'type-email';
    if ($k === 'reunion')       return 'type-reunion';
    if ($k === 'nota interna')  return 'type-nota';
    if ($k === 'sin respuesta') return 'type-sinrespuesta';
    return 'type-default';
}
function idxTypeIcon($type) {
    $k = idxNormalizeKey($type);
    if ($k === 'llamada')       return '📞';
    if ($k === 'whatsapp')      return '💬';
    if ($k === 'email')         return '✉️';
    if ($k === 'reunion')       return '🤝';
    if ($k === 'nota interna')  return '📝';
    if ($k === 'sin respuesta') return '…';
    return '💭';
}
function idxOutcomeClass($outcome) {
    $k = idxNormalizeKey($outcome);
    if ($k === 'positivo')             return 'outcome-positivo';
    if ($k === 'neutral')              return 'outcome-neutral';
    if ($k === 'negativo')             return 'outcome-negativo';
    if ($k === 'listo para cerrar')    return 'outcome-cerrar';
    if ($k === 'requiere seguimiento') return 'outcome-seguimiento';
    if ($k === 'sin respuesta')        return 'outcome-sinrespuesta';
    return 'outcome-default';
}
function idxOutcomeIcon($outcome) {
    $k = idxNormalizeKey($outcome);
    if ($k === 'positivo')             return '✅';
    if ($k === 'neutral')              return '⚪';
    if ($k === 'negativo')             return '❌';
    if ($k === 'listo para cerrar')    return '🔥';
    if ($k === 'requiere seguimiento') return '⏳';
    if ($k === 'sin respuesta')        return '…';
    return '•';
}
function idxFormatDate($dateString) {
    if (empty($dateString)) return '';
    $ts = strtotime($dateString);
    if ($ts === false) return $dateString;
    $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return date('j', $ts) . ' ' . $months[date('n', $ts) - 1] . ' ' . date('Y', $ts);
}
function idxDashboardFormatTime($hora) {
    $hora = trim((string) $hora);
    if ($hora === '' || $hora === '00:00:00') return '';
    $ts = strtotime('1970-01-01 ' . $hora);
    return $ts !== false ? date('g:i a', $ts) : $hora;
}
function idxDashboardCalendarTitle(array $ev, array $leadNameMap = []) {
    $titulo = trim((string) ($ev['titulo'] ?? ''));
    if ($titulo !== '') return $titulo;

    $nota = trim((string) ($ev['nota'] ?? ''));
    if ($nota !== '') {
        return mb_strlen($nota) > 100 ? mb_substr($nota, 0, 97) . '...' : $nota;
    }

    $comentario = trim((string) ($ev['comentario'] ?? ''));
    if ($comentario !== '') {
        return mb_strlen($comentario) > 100 ? mb_substr($comentario, 0, 97) . '...' : $comentario;
    }

    $idclie = intval($ev['idclie'] ?? 0);
    $leadLabel = ($idclie > 0 && isset($leadNameMap[$idclie])) ? trim((string) $leadNameMap[$idclie]) : '';
    if ($leadLabel !== '') {
        if (intval($ev['tipo'] ?? 0) === 1) {
            return 'Llamada WP: ' . $leadLabel;
        }
        return $leadLabel;
    }

    return intval($ev['tipo'] ?? 0) === 1 ? 'Llamada introductoria WP' : 'Evento en calendario';
}
function idxDashboardCalendarSubtitle(array $evt, array $userNameMap = []) {
    $parts = [];
    $timeLabel = idxDashboardFormatTime($evt['hora'] ?? '');
    if ($timeLabel !== '') $parts[] = $timeLabel;

    $owner = trim((string) ($evt['owner'] ?? ''));
    if ($owner !== '' && $owner !== 'Mi agenda') $parts[] = $owner;

    return implode(' · ', $parts);
}
function idxDashboardInteractionTitle(array $row) {
    $action = trim((string) ($row['next_action'] ?? ''));
    if ($action === '') $action = 'Seguimiento';

    $type = trim((string) ($row['interaction_type'] ?? ''));
    if ($type !== '') return $type . ' · ' . $action;

    return $action;
}
function idxDashboardIsWpOrigen($tablaOrigen) {
    $key = strtolower(trim((string) $tablaOrigen));
    return in_array($key, ['wedding_planners', 'wedding_planner', 'eventos_wp', 'wp_citas_leads'], true);
}
function idxDashboardAssigneeTypeLabel($kind) {
    if ($kind === 'wp') return 'Wedding Planner';
    if ($kind === 'lead') return 'Lead';
    return 'Cita';
}
function idxDashboardFormatVendorName($nombre, $apepat) {
    $name = trim((string) $nombre);
    $ape = trim((string) $apepat);
    if ($ape !== '' && $ape !== '.') {
        $name = trim($name . ' ' . $ape);
    }
    return $name;
}
function idxDashboardLoadVendorNameMap($conn, array $userIds, array $existingMap = []) {
    $map = $existingMap;
    $missing = [];
    foreach ($userIds as $uid) {
        $uid = intval($uid);
        if ($uid <= 0 || isset($map[$uid])) continue;
        $missing[$uid] = true;
    }
    if (empty($missing)) return $map;

    $idList = implode(',', array_map('intval', array_keys($missing)));
    $res = $conn->query("SELECT id, nombre, apepat FROM usuarios WHERE id IN ($idList)");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $label = idxDashboardFormatVendorName($row['nombre'] ?? '', $row['apepat'] ?? '');
            if ($label !== '') {
                $map[intval($row['id'] ?? 0)] = $label;
            }
        }
    }
    return $map;
}
function idxFilterCalendarioEventsWithValidContact($conn, array $events) {
    if (empty($events)) {
        return $events;
    }

    $leadIdclies = [];
    foreach ($events as $ev) {
        if (intval($ev['tipo'] ?? 0) === 1) {
            continue;
        }
        $idclie = intval($ev['idclie'] ?? 0);
        if ($idclie > 0) {
            $leadIdclies[$idclie] = true;
        }
    }

    $validLeadIds = [];
    if (!empty($leadIdclies)) {
        $idList = implode(',', array_map('intval', array_keys($leadIdclies)));
        $cfRes = $conn->query("SELECT id FROM contact_form WHERE id IN ($idList)");
        if ($cfRes) {
            while ($cfRow = $cfRes->fetch_assoc()) {
                $validLeadIds[intval($cfRow['id'] ?? 0)] = true;
            }
        }
    }

    return array_values(array_filter($events, function ($ev) use ($validLeadIds) {
        if (intval($ev['tipo'] ?? 0) === 1) {
            return true;
        }
        $idclie = intval($ev['idclie'] ?? 0);
        if ($idclie <= 0) {
            return true;
        }
        return isset($validLeadIds[$idclie]);
    }));
}
function idxDashboardAssigneeKey($tablaOrigen, $leadId) {
    return strtolower(trim((string) $tablaOrigen)) . '|' . intval($leadId);
}
function idxDashboardFetchInteractionAssignees($conn, array $rows) {
    $map = [];
    $byTable = [];

    foreach ($rows as $row) {
        $origen = strtolower(trim((string) ($row['tabla_origen'] ?? '')));
        $leadId = intval($row['lead_id'] ?? 0);
        if ($origen === '' || $leadId <= 0) continue;
        $byTable[$origen][$leadId] = true;
    }

    foreach ($byTable as $origen => $ids) {
        $idList = implode(',', array_map('intval', array_keys($ids)));
        if ($idList === '') continue;

        if (in_array($origen, ['wedding_planners', 'wedding_planner'], true)) {
            $res = $conn->query("SELECT id, COALESCE(NULLIF(TRIM(empresa_wp), ''), NULLIF(TRIM(full_name), ''), NULLIF(TRIM(campaign_name), ''), CONCAT('WP #', id)) AS label
                FROM wedding_planners WHERE id IN ($idList)");
            if ($res) {
                while ($item = $res->fetch_assoc()) {
                    $id = intval($item['id'] ?? 0);
                    $map[idxDashboardAssigneeKey($origen, $id)] = [
                        'kind'  => 'wp',
                        'label' => trim((string) ($item['label'] ?? '')),
                        'url'   => 'planner_profile.php?id=' . $id,
                    ];
                }
            }
            continue;
        }

        if ($origen === 'contact_form') {
            $res = $conn->query("SELECT id, COALESCE(NULLIF(TRIM(names), ''), NULLIF(TRIM(campaign_name), ''), CONCAT('Lead #', id)) AS label
                FROM contact_form WHERE id IN ($idList)");
            if ($res) {
                while ($item = $res->fetch_assoc()) {
                    $id = intval($item['id'] ?? 0);
                    $map[idxDashboardAssigneeKey($origen, $id)] = [
                        'kind'  => 'lead',
                        'label' => trim((string) ($item['label'] ?? '')),
                        'url'   => 'lead_interaction.php?tabla_origen=contact_form&id=' . $id,
                    ];
                }
            }
            continue;
        }

        if ($origen === 'eventos_wp') {
            $res = $conn->query("SELECT e.id, e.wedding_planner_id,
                COALESCE(NULLIF(TRIM(wp.empresa_wp), ''), NULLIF(TRIM(wp.full_name), ''), NULLIF(TRIM(wp.campaign_name), ''), CONCAT('WP #', wp.id)) AS label
                FROM eventos_wp e
                LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id
                WHERE e.id IN ($idList)");
            if ($res) {
                while ($item = $res->fetch_assoc()) {
                    $id = intval($item['id'] ?? 0);
                    $plannerId = intval($item['wedding_planner_id'] ?? 0);
                    $map[idxDashboardAssigneeKey($origen, $id)] = [
                        'kind'  => 'wp',
                        'label' => trim((string) ($item['label'] ?? '')),
                        'url'   => $plannerId > 0 ? 'planner_profile.php?id=' . $plannerId : '',
                    ];
                }
            }
            continue;
        }

        if ($origen === 'wp_citas_leads') {
            $res = $conn->query("SELECT wcl.id, wcl.id_wedding_planner,
                COALESCE(NULLIF(TRIM(wp.empresa_wp), ''), NULLIF(TRIM(wp.full_name), ''), NULLIF(TRIM(wp.campaign_name), ''), CONCAT('WP #', wp.id)) AS label
                FROM wp_citas_leads wcl
                LEFT JOIN wedding_planners wp ON wp.id = wcl.id_wedding_planner
                WHERE wcl.id IN ($idList)");
            if ($res) {
                while ($item = $res->fetch_assoc()) {
                    $id = intval($item['id'] ?? 0);
                    $plannerId = intval($item['id_wedding_planner'] ?? 0);
                    $map[idxDashboardAssigneeKey($origen, $id)] = [
                        'kind'  => 'wp',
                        'label' => trim((string) ($item['label'] ?? '')),
                        'url'   => $plannerId > 0 ? 'planner_profile.php?id=' . $plannerId : '',
                    ];
                }
            }
            continue;
        }

        foreach (array_keys($ids) as $id) {
            $key = idxDashboardAssigneeKey($origen, $id);
            if (isset($map[$key])) continue;
            $map[$key] = [
                'kind'  => idxDashboardIsWpOrigen($origen) ? 'wp' : 'lead',
                'label' => ucfirst(str_replace('_', ' ', $origen)) . ' #' . intval($id),
                'url'   => 'lead_interaction.php?tabla_origen=' . urlencode($origen) . '&id=' . intval($id),
            ];
        }
    }

    return $map;
}
function idxDashboardApplyAssignee(array $item, array $assigneeMap) {
    $origen = trim((string) ($item['tabla_origen'] ?? ''));
    $leadId = intval($item['lead_id'] ?? 0);
    if ($origen !== '' && $leadId > 0) {
        $assignee = $assigneeMap[idxDashboardAssigneeKey($origen, $leadId)] ?? null;
        if (is_array($assignee)) {
            $item['assignee_kind']  = $assignee['kind'] ?? 'lead';
            $item['assignee_label'] = trim((string) ($assignee['label'] ?? ''));
            $item['assignee_url']   = trim((string) ($assignee['url'] ?? ''));
        }
    }
    return $item;
}
// ────────────────────────────────────────────────────────────────────────────

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

$userid = $_SESSION['uid'];

$sql_user_data = "SELECT tipoUsu FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql_user_data);
$stmt->bind_param("i", $userid); // Vincula el parámetro (i = entero)
$stmt->execute(); // Ejecuta la consulta

$resultUser = $stmt->get_result(); // Obtiene el resultado

// Verifica si hay resultados
if ($resultUser->num_rows > 0) {
    $row = $resultUser->fetch_assoc(); // Obtiene la fila como un arreglo asociativo
    $tipoUsu = $row['tipoUsu']; // Extrae el valor de tipoUsu
} else {
    echo "No se encontró ningún usuario con el ID proporcionado.";
}

$events = []; // Inicializamos el array de eventos
$users = [];  // Inicializamos el array de usuarios

if ($tipoUsu == 1) {
    // Consulta SQL para traer todos los datos de la tabla calendario que coincidan con el idusu
    $sql = "SELECT * FROM calendario WHERE idusu = ?";
    $stmt = $conn->prepare($sql); // Prepara la consulta
    $stmt->bind_param("i", $userid); // Vincula el parámetro (i = entero)
    $stmt->execute(); // Ejecuta la consulta

    $result = $stmt->get_result(); // Obtiene el resultado

    // Verifica si hay resultados
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $events[] = $row; // Guarda los eventos en un array
        }
    }
} elseif (usuarioTipoEsAdminLike($tipoUsu)) {
    // ADMIN / Líder de Planners

    // obtener todos los datos del calendario
    $sql = "SELECT * FROM calendario";
    $stmt = $conn->prepare($sql); // Prepara la consulta
    $stmt->execute(); // Ejecuta la consulta

    $result = $stmt->get_result(); // Obtiene el resultado

    // Verifica si hay resultados
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $events[] = $row; // Guarda los eventos en un array
        }

        // obtener todos los usuarios
        $sql_users = "SELECT * FROM usuarios WHERE tipoUsu != ?";
        $stmt_users = $conn->prepare($sql_users); // Prepara la consulta
        $stmt_users->bind_param("i", $tipoUsu); // Asocia la variable $tipoUsu
        $stmt_users->execute(); // Ejecuta la consulta

        $result_users = $stmt_users->get_result(); // Obtiene el resultado

        // Verifica si hay resultados
        if ($result_users->num_rows > 0) {
            while ($row_user = $result_users->fetch_assoc()) {
                $users[] = $row_user; // Guarda los usuarios en un array
            }
        }
    }
}

$events = idxFilterCalendarioEventsWithValidContact($conn, $events);

// En este punto, siempre tendrás los arrays $events y $users, aunque estén vacíos.

// ═══════════════════════════════════════════════════════════════════════════════
// DATOS PARA SECCIONES DE REPORTES (basado en clientes.php)
// ═══════════════════════════════════════════════════════════════════════════════

$tipoUsuario = $tipoUsu; // Sync: usar el tipoUsu obtenido de la BD

function formatCreatedTime($dateString)
{
    if (empty($dateString)) return '';
    $timestamp = strtotime($dateString);
    if ($timestamp === false) return $dateString;
    $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
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
    if (empty($dateString)) return '';
    $timestamp = strtotime($dateString);
    if ($timestamp === false) return $dateString;
    $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $month = $months[date('n', $timestamp) - 1];
    $day = date('j', $timestamp);
    $year = date('Y', $timestamp);
    return "$day de $month de $year";
}

// Obtener todos los IDs de contact_form que tienen citas en calendario
$rpt_appointmentIds = [];
$rpt_userFilter = ($tipoUsu == 1) ? ' WHERE idusu = ' . intval($userid) : '';
$rpt_appointmentQuery = $conn->query("SELECT DISTINCT idclie FROM calendario" . $rpt_userFilter);
if ($rpt_appointmentQuery && $rpt_appointmentQuery->num_rows > 0) {
    while ($rpt_row = $rpt_appointmentQuery->fetch_assoc()) {
        $rpt_appointmentIds[] = intval($rpt_row['idclie']);
    }
}

// Si hay citas, obtener datos de las citas para extraer el estatus
$rpt_appointmentsByClient = [];
if (!empty($rpt_appointmentIds)) {
    $rpt_idsList = implode(',', array_map('intval', $rpt_appointmentIds));
    $rpt_apptRes = $conn->query("SELECT * FROM calendario WHERE idclie IN ($rpt_idsList)");
    if ($rpt_apptRes && $rpt_apptRes->num_rows > 0) {
        while ($ar = $rpt_apptRes->fetch_assoc()) {
            $rpt_idclie = isset($ar['idclie']) ? intval($ar['idclie']) : 0;
            if ($rpt_idclie <= 0) continue;
            if (!isset($rpt_appointmentsByClient[$rpt_idclie])) {
                $rpt_appointmentsByClient[$rpt_idclie] = $ar;
            } else {
                $prev = $rpt_appointmentsByClient[$rpt_idclie];
                $replace = false;
                if (!empty($ar['fecha']) && !empty($prev['fecha'])) {
                    $t1 = strtotime($ar['fecha'] . ' ' . ($ar['hora'] ?? '')) ?: 0;
                    $t2 = strtotime($prev['fecha'] . ' ' . ($prev['hora'] ?? '')) ?: 0;
                    if ($t1 > $t2) $replace = true;
                } elseif (!empty($ar['id']) && !empty($prev['id'])) {
                    if (intval($ar['id']) > intval($prev['id'])) $replace = true;
                }
                if ($replace) $rpt_appointmentsByClient[$rpt_idclie] = $ar;
            }
        }
    }
}

// Consultar registros de contact_form (Cierres) — excluir Wedding Planners
$rpt_allLeads = [];
$rpt_vendorFilter = ($tipoUsu == 1) ? ' AND id_vendedor_asignado = ' . intval($userid) : '';
$rpt_sql = "SELECT * FROM contact_form WHERE cliente = 1 AND LOWER(COALESCE(tabla_origen, '')) != 'wedding_planners'" . $rpt_vendorFilter . " ORDER BY submission_date DESC";
$rpt_result = $conn->query($rpt_sql);
if ($rpt_result && $rpt_result->num_rows > 0) {
    while ($cf = $rpt_result->fetch_assoc()) {
        $tablaOrigenRaw = strtolower(trim($cf['tabla_origen'] ?? ''));
        if ($tablaOrigenRaw === 'wedding_planners' || $tablaOrigenRaw === 'wedding_planner') continue;
        $merged = $cf;
        $merged['tabla_origen'] = $cf['tabla_origen'] ?? '';
        $merged['original_lead_id'] = $cf['original_lead_id'] ?? null;
        $merged['full_name'] = $cf['names'] ?? 'N/A';
        $merged['submission_date'] = $cf['submission_date'] ?? '';
        $merged['fecha_cambio_cliente'] = $cf['fecha_cambio_cliente'] ?? '';
        $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';
        $merged['wedding_location'] = (isset($cf['wedding_location']) && trim($cf['wedding_location']) !== '') ? $cf['wedding_location'] : 'N/A';
        $merged['has_appointment'] = in_array(intval($cf['id']), $rpt_appointmentIds) ? 1 : 0;
        $formName = $cf['tabla_origen'] ?? '';
        $origId = intval($cf['original_lead_id'] ?? 0);
        if (!empty($formName) && $origId > 0) {
            $escapedForm = $conn->real_escape_string($formName);
            $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
                if ($leadRes && $leadRes->num_rows > 0) {
                    $leadRow = $leadRes->fetch_assoc();
                    if (!empty($leadRow['full_name'])) $merged['full_name'] = $leadRow['full_name'];
                    elseif (!empty($leadRow['names'])) $merged['full_name'] = $leadRow['names'];
                    elseif (!empty($leadRow['name'])) $merged['full_name'] = $leadRow['name'];
                    if (!empty($leadRow['fecha_cambio_cliente'])) {
                        $merged['fecha_cambio_cliente'] = $leadRow['fecha_cambio_cliente'];
                    } elseif (!empty($leadRow['created_time'])) {
                        $merged['created_time'] = $leadRow['created_time'];
                    } elseif (!empty($leadRow['created_at'])) {
                        $merged['created_time'] = $leadRow['created_at'];
                    }
                    foreach ($leadRow as $k => $v) {
                        if (is_string($v)) $v = trim($v);
                        if (!isset($merged[$k]) || $merged[$k] === '' || $merged[$k] === null) {
                            $merged[$k] = $v;
                        }
                    }
                }
            }
        }
        $merged['campaign_name'] = (isset($cf['campaign_name']) && trim($cf['campaign_name']) !== '') ? $cf['campaign_name'] : 'N/A';
        $cid = intval($cf['id']);
        if ($cid > 0 && isset($rpt_appointmentsByClient[$cid])) {
            $appt = $rpt_appointmentsByClient[$cid];
            if (isset($appt['idusu']) && $appt['idusu'] !== '') {
                if (!isset($merged['id_vendedor_asignado']) || empty($merged['id_vendedor_asignado'])) {
                    $merged['id_vendedor_asignado'] = intval($appt['idusu']);
                }
                if (!isset($merged['usuario_asignado']) || empty($merged['usuario_asignado'])) {
                    $merged['usuario_asignado'] = intval($appt['idusu']);
                }
            }
        }
        $merged['estatus'] = '';
        if ($cf['cliente'] == 1) {
            $merged['estatus'] = 'cliente';
        } elseif (isset($rpt_appointmentsByClient[$cid]) && isset($rpt_appointmentsByClient[$cid]['estatus'])) {
            $rawStatus = $rpt_appointmentsByClient[$cid]['estatus'];
            $intStatus = is_numeric($rawStatus) ? intval($rawStatus) : null;
            if ($intStatus === 1) $merged['estatus'] = 'atendido';
            elseif ($intStatus === 3) $merged['estatus'] = 'muerto';
            elseif ($intStatus === 0) $merged['estatus'] = 'agendado';
            elseif ($intStatus === 2) $merged['estatus'] = 'fantasma';
            else $merged['estatus'] = $rawStatus;
        }
        $rpt_allLeads[] = $merged;
    }
}

// Leer filtros de fecha desde GET
$rpt_filterPlataforma = isset($_GET['filter_plataforma']) ? trim($_GET['filter_plataforma']) : '';
$rpt_platformLabel = 'todo';
if ($rpt_filterPlataforma === 'campania') $rpt_platformLabel = 'redes sociales';
elseif ($rpt_filterPlataforma === 'organico') $rpt_platformLabel = 'organico';

$rpt_tablaTipoMap = [];
$rpt_sqlTablasMap = "SELECT nombre, tipo FROM tablas_leads";
$rpt_resultTablasMap = $conn->query($rpt_sqlTablasMap);
if ($rpt_resultTablasMap && $rpt_resultTablasMap->num_rows > 0) {
    while ($rpt_row = $rpt_resultTablasMap->fetch_assoc()) {
        $rpt_tablaTipoMap[$rpt_row['nombre']] = intval($rpt_row['tipo']);
    }
}

$rpt_startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$rpt_endDate   = isset($_GET['end_date'])   ? trim($_GET['end_date'])   : '';
if ($rpt_startDate === '' && $rpt_endDate === '' && empty($_GET['show_all'])) {
    $rpt_endDate   = date('Y-m-d');
    $rpt_startDate = date('Y-m-d', strtotime('-14 days', strtotime($rpt_endDate)));
}

if (!function_exists('getDisplayOrigenFromLead')) {
    function getDisplayOrigenFromLead($lead) {
        $orig = trim($lead['campaign_name'] ?? '');
        if (preg_match('/^c(\d+)\./i', $orig, $m)) return 'c' . $m[1];
        if ($orig === '' || mb_strtolower($orig, 'UTF-8') === 'n/a') {
            $dp = isset($lead['desde_publicidad']) ? intval($lead['desde_publicidad']) : null;
            switch ($dp) { case 0: return 'Website'; case 1: return 'Website'; case 2: return 'Instagram Orgánico'; case 3: return 'Whatsapp'; case 4: return 'Correo electrónico'; default: return 'Otro'; }
        }
        if (mb_strtolower($orig, 'UTF-8') === 'publicidad') return 'Website';
        return $orig;
    }
}

if (!function_exists('normalizeOrigen')) {
    function normalizeOrigen($orig) {
        if (!$orig) return '';
        if (preg_match('/^c(\d+)\./i', $orig, $m)) return 'c' . $m[1];
        return $orig;
    }
}

if (!function_exists('isB1B2CampaignName')) {
    function isB1B2CampaignName($campaignName) {
        if ($campaignName === null) return false;
        $v = strtolower(trim((string)$campaignName));
        if ($v === '') return false;
        $v = preg_replace('/\s+/', ' ', $v);
        return in_array($v, ['b1','b1 (usa)','b1 usa','b2','b2 (mx)','b2 mx'], true);
    }
}

if (!function_exists('getDondeNosConocioLabel')) {
    function getDondeNosConocioLabel($lead) {
        $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
        $hearRaw = trim((string)($lead['hear_about_us'] ?? ''));
        $howMap = ['1' => 'Wedding Planner', '2' => 'Community', '3' => 'New Market'];
        $hearMap = ['1' => 'Meta Ads — anuncio en Instagram / Facebook', '2' => 'SEO — buscaron en Google', '3' => 'Colaboración / Influencer / Famoso', '4' => 'Publicación / Prensa / Revista', '5' => 'Otro'];
        $howLabel = $howRaw !== '' ? ($howMap[$howRaw] ?? $howRaw) : '';
        $hearLabel = $hearRaw !== '' ? ($hearMap[$hearRaw] ?? $hearRaw) : '';
        if ($howLabel !== '' && $hearLabel !== '') return $howLabel . ' / ' . $hearLabel;
        if ($howLabel !== '') return $howLabel;
        if ($hearLabel !== '') return $hearLabel;
        return 'N/A';
    }
}

if (!function_exists('getEngagementLabelWithEmoji')) {
    function getEngagementLabelWithEmoji($lead) {
        $raw = trim((string)($lead['engagement'] ?? ($lead['sfm_engagement'] ?? '')));
        if ($raw === '') return 'N/A';
        $normalized = mb_strtolower($raw, 'UTF-8');
        if ($normalized === '0') return 'Sin dato';
        if ($normalized === '1' || $normalized === 'bajo') return '😑 Bajo';
        if ($normalized === '2' || $normalized === 'medio') return '😃 Medio';
        if ($normalized === '3' || $normalized === 'alto') return '🔥 Alto';
        return $raw;
    }
}

if (!function_exists('normalizeFirstContactChannelLabel')) {
    function normalizeFirstContactChannelLabel($value) {
        $normalized = trim((string)$value);
        if ($normalized === '') return 'Sin dato';
        $key = mb_strtolower($normalized, 'UTF-8');
        $key = str_replace(['–','—'], '-', $key);
        $key = preg_replace('/\s+/', ' ', $key);
        $map = ['whatsapp' => 'WhatsApp', 'instagram dm - campaign' => 'Instagram DM - Campaña', 'instagram dm campaign' => 'Instagram DM - Campaña', 'instagram dm - organic' => 'Instagram DM - Orgánico', 'instagram dm organic' => 'Instagram DM - Orgánico', 'email' => 'Correo electrónico', 'correo electronico' => 'Correo electrónico', 'correo electrónico' => 'Correo electrónico', 'mail' => 'Correo electrónico', 'phone call' => 'Llamada telefónica', 'llamada telefonica' => 'Llamada telefónica', 'llamada telefónica' => 'Llamada telefónica'];
        return $map[$key] ?? $normalized;
    }
}

if (!function_exists('formatLeadDate')) {
    function formatLeadDate($dateString) {
        $value = trim((string)$dateString);
        if ($value === '') return '—';
        $timestamp = strtotime($value);
        if ($timestamp === false) return $value;
        return date('d/m/Y', $timestamp);
    }
}

// Crear lista filtrada de displayLeads
$rpt_displayLeads = [];
if ($rpt_startDate === '' && $rpt_endDate === '' && $rpt_filterPlataforma === '') {
    $rpt_displayLeads = $rpt_allLeads;
} else {
    $rpt_sd = $rpt_startDate !== '' ? date('Y-m-d', strtotime($rpt_startDate)) : null;
    $rpt_ed = $rpt_endDate   !== '' ? date('Y-m-d', strtotime($rpt_endDate))   : null;
    foreach ($rpt_allLeads as $lead) {
        $ct = $lead['fecha_cambio_cliente'] ?? '';
        if (empty($ct)) continue;
        $ts = strtotime($ct);
        if ($ts === false) continue;
        $d = date('Y-m-d', $ts);
        if ($rpt_sd && $d < $rpt_sd) continue;
        if ($rpt_ed && $d > $rpt_ed) continue;
        if ($rpt_filterPlataforma !== '') {
            $tablaOrigen = $lead['tabla_origen'] ?? '';
            $tipoTabla = isset($rpt_tablaTipoMap[$tablaOrigen]) ? $rpt_tablaTipoMap[$tablaOrigen] : -1;
            $campaignNameLower = strtolower(trim($lead['campaign_name'] ?? ''));
            $isB1B2 = isB1B2CampaignName($campaignNameLower);
            if ($rpt_filterPlataforma === 'organico') { if ($isB1B2) continue; if ($tipoTabla !== 0 && $campaignNameLower !== 'website' && $campaignNameLower !== 'reg manual') continue; }
            if ($rpt_filterPlataforma === 'campania') { if ($tipoTabla !== 1 && !$isB1B2) continue; }
        }
        $rpt_displayLeads[] = $lead;
    }
}

usort($rpt_displayLeads, function ($a, $b) {
    $ta = (!empty($a['fecha_cambio_cliente']) && strtotime($a['fecha_cambio_cliente']) !== false) ? strtotime($a['fecha_cambio_cliente']) : 0;
    $tb = (!empty($b['fecha_cambio_cliente']) && strtotime($b['fecha_cambio_cliente']) !== false) ? strtotime($b['fecha_cambio_cliente']) : 0;
    if ($tb === $ta) return 0;
    return ($tb < $ta) ? -1 : 1;
});

$rpt_leadsCountFiltered = count($rpt_displayLeads);

// today count
$rpt_todayClientsCount = 0;
$rpt_today = date('Y-m-d');
foreach ($rpt_allLeads as $lead) {
    $fechaRaw = $lead['fecha_cambio_cliente'] ?? '';
    if (empty($fechaRaw)) continue;
    $ts = strtotime($fechaRaw);
    if ($ts === false) continue;
    if (date('Y-m-d', $ts) === $rpt_today) $rpt_todayClientsCount++;
}

// Chart series for closures
$rpt_map = []; $rpt_minTs = null; $rpt_maxTs = null;
foreach ($rpt_displayLeads as $lead) {
    $ct = $lead['fecha_cambio_cliente'] ?? '';
    if (empty($ct)) continue;
    $ts = strtotime($ct);
    if ($ts === false) continue;
    $d = date('Y-m-d', $ts);
    if (!isset($rpt_map[$d])) $rpt_map[$d] = 0;
    $rpt_map[$d]++;
    if ($rpt_minTs === null || $ts < $rpt_minTs) $rpt_minTs = $ts;
    if ($rpt_maxTs === null || $ts > $rpt_maxTs) $rpt_maxTs = $ts;
}
$rpt_end   = strtotime('today');
$rpt_start = $rpt_end - (59 * 86400);
if ($rpt_minTs !== null) { $rpt_minDayStart = strtotime(date('Y-m-d', $rpt_minTs)); if ($rpt_minDayStart > $rpt_start) $rpt_start = $rpt_minDayStart; }
if ($rpt_maxTs !== null) { $rpt_maxDay = strtotime(date('Y-m-d', $rpt_maxTs)); if ($rpt_maxDay < $rpt_end) $rpt_end = $rpt_maxDay; }
$rpt_dates = []; $rpt_counts = [];
for ($ts = $rpt_start; $ts <= $rpt_end; $ts += 86400) {
    $d = date('Y-m-d', $ts);
    $rpt_dates[]  = $d;
    $rpt_counts[] = isset($rpt_map[$d]) ? $rpt_map[$d] : 0;
}
$rpt_datesJson  = json_encode($rpt_dates);
$rpt_countsJson = json_encode($rpt_counts);

// Desde cuándo nos conoce de cierres (how_long_known_us)
$rpt_howLongKnownUsLabelMap = [
    'less than 3 months'          => 'Menos de 3 meses',
    'between 3 months and 1 year' => 'Entre 3 meses y 1 año',
    'more than 1 year'            => 'Más de 1 año',
    'not asked'                   => 'No se preguntó',
];
$rpt_howLongCounts = [];
foreach ($rpt_displayLeads as $lead) {
    $raw = trim((string)($lead['how_long_known_us'] ?? ''));
    $lbl = ($raw === '' || $raw === '—') ? 'Sin dato' : ($rpt_howLongKnownUsLabelMap[mb_strtolower($raw, 'UTF-8')] ?? $raw);
    $rpt_howLongCounts[$lbl] = ($rpt_howLongCounts[$lbl] ?? 0) + 1;
}
arsort($rpt_howLongCounts);

// Método de contacto (first_contact_channel)
$rpt_howContactCounts = [];
foreach ($rpt_displayLeads as $lead) {
    $contactRaw = $lead['metodo_contacto'] ?? $lead['first_contact_channel'] ?? '';
    $label = normalizeFirstContactChannelLabel($contactRaw);
    $rpt_howContactCounts[$label] = ($rpt_howContactCounts[$label] ?? 0) + 1;
}
arsort($rpt_howContactCounts);

// Pie JSONs for closures
$rpt_howContactPieData = [];
$rpt_howContactLabelColors = [
    'WhatsApp' => '#25D366',
    'Instagram DM - Campaña' => '#C13584',
    'Instagram DM - Orgánico' => '#C13584',
    'Correo electrónico' => '#2563eb',
    'Llamada telefónica' => '#10B981',
    'Sin dato' => '#94a3b8',
];
foreach ($rpt_howContactCounts as $hlabel => $hcount) {
    if ($hcount <= 0) continue;
    $rpt_howContactPieData[] = ['name' => $hlabel, 'y' => $hcount, 'color' => ($rpt_howContactLabelColors[$hlabel] ?? null)];
}
$rpt_howContactPieJson = json_encode($rpt_howContactPieData, JSON_UNESCAPED_UNICODE);

$rpt_howLongPieData = [];
$rpt_howLongColorMap = ['Menos de 3 meses' => '#C5A028', 'Entre 3 meses y 1 año' => '#3B82F6', 'Más de 1 año' => '#10B981', 'No se preguntó' => '#A855F7', 'Sin dato' => '#94a3b8'];
foreach ($rpt_howLongCounts as $llabel => $lcount) {
    if ($lcount <= 0) continue;
    $rpt_howLongPieData[] = ['name' => $llabel, 'y' => $lcount, 'color' => ($rpt_howLongColorMap[$llabel] ?? null)];
}
$rpt_howLongPieJson = json_encode($rpt_howLongPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ──── PRE-QUALIFIED DATA ────
$rpt_tablasPreLeads = [];
$rpt_sqlTablasPreLeads = "SELECT nombre FROM tablas_leads WHERE tipo != 2 ORDER BY nombre";
$rpt_resultTablasPreLeads = $conn->query($rpt_sqlTablasPreLeads);
if ($rpt_resultTablasPreLeads && $rpt_resultTablasPreLeads->num_rows > 0) {
    while ($rpt_row = $rpt_resultTablasPreLeads->fetch_assoc()) { $rpt_tablasPreLeads[] = $rpt_row['nombre']; }
}

$rpt_allPreLeads = [];
foreach ($rpt_tablasPreLeads as $tableName) {
    $chk = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($chk && $chk->num_rows > 0) {
        $rpt_cols = []; $rpt_colRes = $conn->query("SHOW COLUMNS FROM `$tableName`");
        while ($rpt_col = $rpt_colRes->fetch_assoc()) { $rpt_cols[] = $rpt_col['Field']; }
        $rpt_sqlPre = "SELECT *, '$tableName' as tabla_origen FROM `$tableName` WHERE " . (in_array('descartado', $rpt_cols) ? "descartado = 0 OR descartado IS NULL" : "1=1") . " ORDER BY created_time DESC";
        $rpt_resPreLeads = $conn->query($rpt_sqlPre);
        if ($rpt_resPreLeads && $rpt_resPreLeads->num_rows > 0) {
            while ($rpt_lead = $rpt_resPreLeads->fetch_assoc()) { $rpt_allPreLeads[] = $rpt_lead; }
        }
    }
}

$rpt_filteredPreLeads = [];
foreach ($rpt_allPreLeads as $lead) {
    if ($rpt_startDate !== '' || $rpt_endDate !== '') {
        if (empty($lead['created_time'])) continue;
        $rawCT = $lead['created_time'];
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $rawCT, $_mPre)) continue;
        $leadDate = $_mPre[1];
        $rpt_sdPre = $rpt_startDate !== '' ? date('Y-m-d', strtotime($rpt_startDate)) : null;
        $rpt_edPre = $rpt_endDate   !== '' ? date('Y-m-d', strtotime($rpt_endDate))   : null;
        if ($rpt_sdPre && $leadDate < $rpt_sdPre) continue;
        if ($rpt_edPre && $leadDate > $rpt_edPre) continue;
    }
    $rpt_filteredPreLeads[] = $lead;
}

// Build leadIdsByTable and status map for Pre-Q
$rpt_leadIdsByTablePre = [];
foreach ($rpt_filteredPreLeads as $lead) {
    $t = $lead['tabla_origen'] ?? ''; $lid = intval($lead['id'] ?? 0);
    if ($t === '' || $lid <= 0) continue;
    if (!isset($rpt_leadIdsByTablePre[$t])) $rpt_leadIdsByTablePre[$t] = [];
    $rpt_leadIdsByTablePre[$t][] = $lid;
}
$rpt_contactFormByLeadPre = []; $rpt_cfIdsPre = [];
foreach ($rpt_leadIdsByTablePre as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '') continue;
    $rpt_preQVendorFilter = ($tipoUsu == 1) ? ' AND id_vendedor_asignado = ' . intval($userid) : '';
    $rpt_resCf = $conn->query("SELECT id, original_lead_id, cliente FROM contact_form WHERE LOWER(tabla_origen) = LOWER('$safeTable') AND original_lead_id IN ($idsList)" . $rpt_preQVendorFilter);
    if ($rpt_resCf) {
        while ($rpt_row = $rpt_resCf->fetch_assoc()) {
            $key = $t . '|' . intval($rpt_row['original_lead_id']);
            $rpt_contactFormByLeadPre[$key] = ['cf_id' => intval($rpt_row['id']), 'cliente' => isset($rpt_row['cliente']) ? intval($rpt_row['cliente']) : 0];
            $rpt_cfIdsPre[] = intval($rpt_row['id']);
        }
    }
}
$rpt_apptsByCFIdPre = [];
if (!empty($rpt_cfIdsPre)) {
    $cfList = implode(',', array_map('intval', $rpt_cfIdsPre));
    $rpt_preQApptFilter = ($tipoUsu == 1) ? ' AND idusu = ' . intval($userid) : '';
    $rpt_apptResPre = $conn->query("SELECT * FROM calendario WHERE idclie IN ($cfList)" . $rpt_preQApptFilter);
    if ($rpt_apptResPre && $rpt_apptResPre->num_rows > 0) {
        while ($ar = $rpt_apptResPre->fetch_assoc()) {
            $rpt_idclie = isset($ar['idclie']) ? intval($ar['idclie']) : 0;
            if ($rpt_idclie <= 0) continue;
            if (!isset($rpt_apptsByCFIdPre[$rpt_idclie])) $rpt_apptsByCFIdPre[$rpt_idclie] = $ar;
            else { $prev = $rpt_apptsByCFIdPre[$rpt_idclie]; $rep = false; if (!empty($ar['fecha']) && !empty($prev['fecha'])) { $t1 = strtotime($ar['fecha'].' '.($ar['hora'] ?? '')) ?: 0; $t2 = strtotime($prev['fecha'].' '.($prev['hora'] ?? '')) ?: 0; if ($t1 > $t2) $rep = true; } elseif (!empty($ar['id']) && !empty($prev['id'])) { if (intval($ar['id']) > intval($prev['id'])) $rep = true; } if ($rep) $rpt_apptsByCFIdPre[$rpt_idclie] = $ar; }
        }
    }
}
$rpt_leadStatusMapPre = [];
foreach ($rpt_leadIdsByTablePre as $t => $ids) {
    foreach ($ids as $lid) {
        $key = $t . '|' . intval($lid);
        $rpt_leadStatusMapPre[$key] = 'lead';
        if (isset($rpt_contactFormByLeadPre[$key])) {
            $cf = $rpt_contactFormByLeadPre[$key];
            if (isset($cf['cliente']) && intval($cf['cliente']) === 1) { $rpt_leadStatusMapPre[$key] = 'cliente'; continue; }
            $cfId = intval($cf['cf_id']);
            if ($cfId > 0 && isset($rpt_apptsByCFIdPre[$cfId]['estatus'])) {
                $rawStatus = $rpt_apptsByCFIdPre[$cfId]['estatus'];
                $intStatus = is_numeric($rawStatus) ? intval($rawStatus) : null;
                if ($intStatus === 1) $rpt_leadStatusMapPre[$key] = 'atendido';
                elseif ($intStatus === 3) $rpt_leadStatusMapPre[$key] = 'muerto';
                elseif ($intStatus === 0) $rpt_leadStatusMapPre[$key] = 'agendado';
                elseif ($intStatus === 2) $rpt_leadStatusMapPre[$key] = 'fantasma';
                else $rpt_leadStatusMapPre[$key] = is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : 'agendado';
            }
        }
    }
}
$rpt_leadsAgendadosTotal = 0;
$rpt_totalPreLeads = count($rpt_filteredPreLeads);
foreach ($rpt_filteredPreLeads as $lead) {
    $t = $lead['tabla_origen'] ?? ''; $lid = isset($lead['id']) ? intval($lead['id']) : 0;
    $key = $t . '|' . $lid;
    if (isset($rpt_leadStatusMapPre[$key])) {
        $status = strtolower($rpt_leadStatusMapPre[$key]);
        if (in_array($status, ['agendado','atendido','fantasma','muerto'], true) || in_array($status, ['0','1','2','3'], true)) $rpt_leadsAgendadosTotal++;
    }
}
$rpt_tasaConversion = $rpt_totalPreLeads > 0 ? round(($rpt_leadsAgendadosTotal / $rpt_totalPreLeads) * 100, 2) : 0;

// Pre-Q chart series
$rpt_preQDayMap = []; $rpt_preQGlobalMin = null; $rpt_preQGlobalMax = null;
foreach ($rpt_filteredPreLeads as $lead) {
    if (empty($lead['created_time'])) continue;
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $lead['created_time'], $_mPQ)) continue;
    $d = $_mPQ[1]; $ts = strtotime($lead['created_time']); if ($ts === false) continue;
    if (!isset($rpt_preQDayMap[$d])) $rpt_preQDayMap[$d] = 0; $rpt_preQDayMap[$d]++;
    if ($rpt_preQGlobalMin === null || $ts < $rpt_preQGlobalMin) $rpt_preQGlobalMin = $ts;
    if ($rpt_preQGlobalMax === null || $ts > $rpt_preQGlobalMax) $rpt_preQGlobalMax = $ts;
}
$rpt_preQDates = []; $rpt_preQCounts = [];
if ($rpt_preQGlobalMin !== null) {
    if ($rpt_startDate === '' && $rpt_endDate === '') { $rpt_preQEnd = strtotime('today'); $rpt_preQStart = strtotime(date('Y-m-d', strtotime('-59 days'))); }
    else { $rpt_preQStart = strtotime(date('Y-m-d', $rpt_preQGlobalMin)); $rpt_preQEnd = strtotime(date('Y-m-d', $rpt_preQGlobalMax)); if ($rpt_startDate !== '') { $sdts = strtotime($rpt_startDate); if ($sdts !== false) $rpt_preQStart = $sdts; } if ($rpt_endDate !== '') { $edts = strtotime($rpt_endDate); if ($edts !== false) $rpt_preQEnd = $edts; } }
    if ($rpt_preQEnd < $rpt_preQStart) $rpt_preQEnd = $rpt_preQStart;
    for ($ts = $rpt_preQStart; $ts <= $rpt_preQEnd; $ts += 86400) { $d = date('Y-m-d', $ts); $rpt_preQDates[] = $d; $rpt_preQCounts[] = isset($rpt_preQDayMap[$d]) ? $rpt_preQDayMap[$d] : 0; }
}
$rpt_preQDatesJson  = json_encode($rpt_preQDates);
$rpt_preQCountsJson = json_encode($rpt_preQCounts);

// Pre-Q contact pie
$rpt_preQContactCounts = [];
foreach ($rpt_filteredPreLeads as $lead) { $lbl = normalizeFirstContactChannelLabel($lead['first_contact_channel'] ?? ''); $rpt_preQContactCounts[$lbl] = ($rpt_preQContactCounts[$lbl] ?? 0) + 1; }
arsort($rpt_preQContactCounts);
$rpt_preQContactPieData = [];
foreach ($rpt_preQContactCounts as $lbl => $cnt) { if ($cnt <= 0) continue; $rpt_preQContactPieData[] = ['name' => $lbl, 'y' => $cnt]; }
$rpt_preQContactPieJson = json_encode($rpt_preQContactPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Pre-Q known-us pie
$rpt_preQKnownUsLabelMap = ['less than 3 months' => 'Menos de 3 meses', 'between 3 months and 1 year' => 'Entre 3 meses y 1 año', 'more than 1 year' => 'Más de 1 año', 'not asked' => 'No se preguntó'];
$rpt_preQKnownUsCounts = [];
foreach ($rpt_filteredPreLeads as $lead) { $raw = trim((string)($lead['how_long_known_us'] ?? '')); $lbl = ($raw === '' || $raw === '—') ? 'Sin dato' : ($rpt_preQKnownUsLabelMap[mb_strtolower($raw, 'UTF-8')] ?? $raw); $rpt_preQKnownUsCounts[$lbl] = ($rpt_preQKnownUsCounts[$lbl] ?? 0) + 1; }
arsort($rpt_preQKnownUsCounts);
$rpt_preQKnownUsPieData = [];
foreach ($rpt_preQKnownUsCounts as $lbl => $cnt) { if ($cnt <= 0) continue; $rpt_preQKnownUsPieData[] = ['name' => $lbl, 'y' => $cnt]; }
$rpt_preQKnownUsPieJson = json_encode($rpt_preQKnownUsPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ──── POST-QUALIFIED DATA ────
function isFantasmaLead($lead) {
    $st = $lead['estatus'] ?? '';
    if (is_numeric($st) && intval($st) === 2) return true;
    return mb_strtolower(trim((string)$st), 'UTF-8') === 'fantasma';
}

$rpt_allPostLeads = [];
if (!empty($rpt_appointmentIds)) {
    $idsList = implode(',', array_map('intval', $rpt_appointmentIds));
    $rpt_sqlPost = "SELECT * FROM contact_form WHERE id IN ($idsList) AND LOWER(COALESCE(tabla_origen, '')) != 'wedding_planners'";
    $rpt_resPost = $conn->query($rpt_sqlPost);
    if ($rpt_resPost && $rpt_resPost->num_rows > 0) {
        while ($cf = $rpt_resPost->fetch_assoc()) {
            $tablaOrigenCF = strtolower(trim($cf['tabla_origen'] ?? ''));
            if ($tablaOrigenCF === 'wedding_planners' || $tablaOrigenCF === 'wedding_planner') continue;
            $merged = $cf;
            $merged['submission_date'] = $cf['submission_date'] ?? '';
            $merged['fecha_cambio_cliente'] = $cf['fecha_cambio_cliente'] ?? '';
            $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';
            $formNamePoQ = $cf['tabla_origen'] ?? ''; $origIdPoQ = intval($cf['original_lead_id'] ?? 0);
            if (!empty($formNamePoQ) && $origIdPoQ > 0) {
                $escapedFormPoQ = $conn->real_escape_string($formNamePoQ);
                $chkTblPoQ = $conn->query("SHOW TABLES LIKE '$escapedFormPoQ'");
                if ($chkTblPoQ && $chkTblPoQ->num_rows > 0) {
                    $colsPoQ = []; $colResPoQ = $conn->query("SHOW COLUMNS FROM `$escapedFormPoQ`");
                    if ($colResPoQ) { while ($colRowPoQ = $colResPoQ->fetch_assoc()) { $colsPoQ[] = $colRowPoQ['Field']; } }
                    $selectColsPoQ = [];
                    if (in_array('created_time', $colsPoQ)) $selectColsPoQ[] = 'created_time';
                    if (in_array('created_at', $colsPoQ)) $selectColsPoQ[] = 'created_at';
                    if (!empty($selectColsPoQ)) {
                        $selStrPoQ = implode(', ', array_map(fn($c) => "`$c`", $selectColsPoQ));
                        $leadResPoQ = $conn->query("SELECT $selStrPoQ FROM `$escapedFormPoQ` WHERE id = $origIdPoQ LIMIT 1");
                        if ($leadResPoQ && $leadResPoQ->num_rows > 0) { $leadRowPoQ = $leadResPoQ->fetch_assoc(); if (!empty($leadRowPoQ['created_time'])) $merged['created_time'] = $leadRowPoQ['created_time']; elseif (!empty($leadRowPoQ['created_at'])) $merged['created_time'] = $leadRowPoQ['created_at']; }
                    }
                }
            }
            $cid = intval($cf['id']);
            if ($cid > 0 && isset($rpt_appointmentsByClient[$cid])) {
                $appt = $rpt_appointmentsByClient[$cid];
                $apptFechaRaw = trim($appt['fecha'] ?? ''); $apptHoraRaw = trim($appt['hora'] ?? '');
                $apptTs = ($apptFechaRaw !== '') ? strtotime($apptFechaRaw . ' ' . $apptHoraRaw) : false;
                if ($apptFechaRaw === '' || $apptFechaRaw === '0000-00-00' || $apptTs === false || $apptTs <= 0) continue;
            }
            $merged['estatus'] = '';
            if ($cf['cliente'] == 1) { $merged['estatus'] = 'cliente'; }
            elseif (isset($rpt_appointmentsByClient[$cid]['estatus'])) {
                $rawStatus = $rpt_appointmentsByClient[$cid]['estatus'];
                $intStatus = is_numeric($rawStatus) ? intval($rawStatus) : null;
                if ($intStatus === 1) $merged['estatus'] = 'atendido'; elseif ($intStatus === 3) $merged['estatus'] = 'muerto'; elseif ($intStatus === 0) $merged['estatus'] = 'agendado'; elseif ($intStatus === 2) $merged['estatus'] = 'fantasma'; else $merged['estatus'] = $rawStatus;
            }
            $rpt_allPostLeads[] = $merged;
        }
    }
}

$rpt_displayPostLeads = [];
if ($rpt_startDate === '' && $rpt_endDate === '') {
    $rpt_displayPostLeads = $rpt_allPostLeads;
} else {
    $rpt_sdPost = $rpt_startDate !== '' ? date('Y-m-d', strtotime($rpt_startDate)) : null;
    $rpt_edPost = $rpt_endDate   !== '' ? date('Y-m-d', strtotime($rpt_endDate))   : null;
    foreach ($rpt_allPostLeads as $lead) {
        $dateField = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
        if (empty($dateField)) continue;
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $dateField, $_mPoQ)) continue;
        $d = $_mPoQ[1];
        if ($rpt_sdPost && $d < $rpt_sdPost) continue;
        if ($rpt_edPost && $d > $rpt_edPost) continue;
        $rpt_displayPostLeads[] = $lead;
    }
}

$rpt_leadsPostCountFiltered = count($rpt_displayPostLeads);
$rpt_leadsWithFechaCambio = 0;
foreach ($rpt_displayPostLeads as $lead) {
    $st = isset($lead['estatus']) ? mb_strtolower(trim((string)$lead['estatus']), 'UTF-8') : '';
    if ($st === 'cliente' || (isset($lead['cliente']) && intval($lead['cliente']) === 1)) $rpt_leadsWithFechaCambio++;
}
$rpt_conversionRatio = $rpt_leadsPostCountFiltered > 0 ? round(($rpt_leadsWithFechaCambio / $rpt_leadsPostCountFiltered) * 100, 2) : 0;
$rpt_totalAtendidos = 0;
foreach ($rpt_displayPostLeads as $lead) {
    $st = isset($lead['estatus']) ? mb_strtolower(trim((string)$lead['estatus']), 'UTF-8') : '';
    if ($st === 'atendido' || $st === '1' || (is_numeric($st) && intval($st) === 1)) $rpt_totalAtendidos++;
}
$rpt_postQAsistenciaRate = $rpt_leadsPostCountFiltered > 0 ? round(($rpt_totalAtendidos / $rpt_leadsPostCountFiltered) * 100, 2) : 0.0;

// Post-Q chart series
$rpt_postQDayMap = []; $rpt_postQGlobalMin = null; $rpt_postQGlobalMax = null;
foreach ($rpt_displayPostLeads as $lead) {
    $df = !empty($lead['created_time']) ? $lead['created_time'] : ($lead['submission_date'] ?? '');
    if (empty($df)) continue;
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $df, $_mPostQ)) continue;
    $d = $_mPostQ[1]; $ts = strtotime($df); if ($ts === false) continue;
    if (!isset($rpt_postQDayMap[$d])) $rpt_postQDayMap[$d] = 0; $rpt_postQDayMap[$d]++;
    if ($rpt_postQGlobalMin === null || $ts < $rpt_postQGlobalMin) $rpt_postQGlobalMin = $ts;
    if ($rpt_postQGlobalMax === null || $ts > $rpt_postQGlobalMax) $rpt_postQGlobalMax = $ts;
}
$rpt_postQDates = []; $rpt_postQCounts = [];
if ($rpt_postQGlobalMin !== null) {
    if ($rpt_startDate === '' && $rpt_endDate === '') { $rpt_postQEnd = strtotime('today'); $rpt_postQStart = strtotime(date('Y-m-d', strtotime('-59 days'))); }
    else { $rpt_postQStart = strtotime(date('Y-m-d', $rpt_postQGlobalMin)); $rpt_postQEnd = strtotime(date('Y-m-d', $rpt_postQGlobalMax)); if ($rpt_startDate !== '') { $sdts = strtotime($rpt_startDate); if ($sdts !== false) $rpt_postQStart = $sdts; } if ($rpt_endDate !== '') { $edts = strtotime($rpt_endDate); if ($edts !== false) $rpt_postQEnd = $edts; } }
    if ($rpt_postQEnd < $rpt_postQStart) $rpt_postQEnd = $rpt_postQStart;
    for ($ts = $rpt_postQStart; $ts <= $rpt_postQEnd; $ts += 86400) { $d = date('Y-m-d', $ts); $rpt_postQDates[] = $d; $rpt_postQCounts[] = isset($rpt_postQDayMap[$d]) ? $rpt_postQDayMap[$d] : 0; }
}
$rpt_postQDatesJson  = json_encode($rpt_postQDates);
$rpt_postQCountsJson = json_encode($rpt_postQCounts);

// Post-Q contact pie
$rpt_postQContactCounts = [];
foreach ($rpt_displayPostLeads as $lead) { $lbl = normalizeFirstContactChannelLabel($lead['first_contact_channel'] ?? ''); $rpt_postQContactCounts[$lbl] = ($rpt_postQContactCounts[$lbl] ?? 0) + 1; }
arsort($rpt_postQContactCounts);
$rpt_postQContactPieData = [];
foreach ($rpt_postQContactCounts as $lbl => $cnt) { if ($cnt <= 0) continue; $rpt_postQContactPieData[] = ['name' => $lbl, 'y' => $cnt]; }
$rpt_postQContactPieJson = json_encode($rpt_postQContactPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Post-Q known-us pie
$rpt_postQKnownUsLabelMap = ['less than 3 months' => 'Menos de 3 meses', 'between 3 months and 1 year' => 'Entre 3 meses y 1 año', 'more than 1 year' => 'Más de 1 año', 'not asked' => 'No se preguntó'];
$rpt_postQKnownUsCounts = [];
foreach ($rpt_displayPostLeads as $lead) { $raw = trim((string)($lead['how_long_known_us'] ?? '')); $lbl = ($raw === '' || $raw === '—') ? 'Sin dato' : ($rpt_postQKnownUsLabelMap[mb_strtolower($raw, 'UTF-8')] ?? $raw); $rpt_postQKnownUsCounts[$lbl] = ($rpt_postQKnownUsCounts[$lbl] ?? 0) + 1; }
arsort($rpt_postQKnownUsCounts);
$rpt_postQKnownUsPieData = [];
foreach ($rpt_postQKnownUsCounts as $lbl => $cnt) { if ($cnt <= 0) continue; $rpt_postQKnownUsPieData[] = ['name' => $lbl, 'y' => $cnt]; }
$rpt_postQKnownUsPieJson = json_encode($rpt_postQKnownUsPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Range label
$rpt_reportRangeLabel = 'Todos los registros';
if (($rpt_startDate !== '' || $rpt_endDate !== '') && empty($_GET['show_all'])) {
    $rpt_reportRangeStart = $rpt_startDate !== '' ? date('d/m/Y', strtotime($rpt_startDate)) : '...';
    $rpt_reportRangeEnd   = $rpt_endDate   !== '' ? date('d/m/Y', strtotime($rpt_endDate))   : '...';
    $rpt_reportRangeLabel = $rpt_reportRangeStart . ' → ' . $rpt_reportRangeEnd;
}

// ═══════════════════════════════════════════════════════════════════════════════
// DASHBOARD RESUMEN PARA EL INDEX (solo acomodo visual, usa datos existentes)
// ═══════════════════════════════════════════════════════════════════════════════
$dashboardNowTs = time();
$dashboardToday = date('Y-m-d', $dashboardNowTs);
$dashboardTomorrow = date('Y-m-d', strtotime('+1 day', $dashboardNowTs));
$dashboardUserNameMap = [];
foreach ($users as $u) {
    $dashboardUserNameMap[intval($u['id'] ?? 0)] = idxDashboardFormatVendorName($u['nombre'] ?? '', $u['apepat'] ?? '');
}
$dashboardEventVendorIds = [];
foreach ($events as $ev) {
    $eventVendorId = intval($ev['idusu'] ?? 0);
    if ($eventVendorId > 0) {
        $dashboardEventVendorIds[$eventVendorId] = true;
    }
}
$dashboardUserNameMap = idxDashboardLoadVendorNameMap($conn, array_keys($dashboardEventVendorIds), $dashboardUserNameMap);

$dashboardIdclieNameMap = [];
$dashboardIdcliePlannerMap = [];
$dashboardIdclieIds = [];
foreach ($events as $ev) {
    $idclie = intval($ev['idclie'] ?? 0);
    if ($idclie > 0) {
        $dashboardIdclieIds[$idclie] = true;
    }
}
if (!empty($dashboardIdclieIds)) {
    $idclieList = implode(',', array_map('intval', array_keys($dashboardIdclieIds)));

    $cfNameRes = $conn->query("SELECT id, COALESCE(NULLIF(TRIM(names), ''), NULLIF(TRIM(campaign_name), ''), CONCAT('Lead #', id)) AS label FROM contact_form WHERE id IN ($idclieList)");
    if ($cfNameRes) {
        while ($cfRow = $cfNameRes->fetch_assoc()) {
            $dashboardIdclieNameMap[intval($cfRow['id'] ?? 0)] = trim((string) ($cfRow['label'] ?? ''));
        }
    }

    $wpNameRes = $conn->query("SELECT e.id, e.wedding_planner_id, COALESCE(NULLIF(TRIM(wp.empresa_wp), ''), NULLIF(TRIM(wp.full_name), ''), NULLIF(TRIM(wp.campaign_name), ''), CONCAT('WP #', wp.id)) AS label
        FROM eventos_wp e
        LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id
        WHERE e.id IN ($idclieList)");
    if ($wpNameRes) {
        while ($wpRow = $wpNameRes->fetch_assoc()) {
            $eventoId = intval($wpRow['id'] ?? 0);
            $dashboardIdclieNameMap[$eventoId] = trim((string) ($wpRow['label'] ?? ''));
            $dashboardIdcliePlannerMap[$eventoId] = intval($wpRow['wedding_planner_id'] ?? 0);
        }
    }
}

$dashboardEventsNormalized = [];
foreach ($events as $ev) {
    $fecha = trim((string)($ev['fecha'] ?? ''));
    $hora  = trim((string)($ev['hora'] ?? '00:00:00'));
    if ($fecha === '') {
        continue;
    }
    $evtTs = strtotime($fecha . ' ' . ($hora !== '' ? $hora : '00:00:00'));
    if ($evtTs === false) {
        continue;
    }
    $idusu = intval($ev['idusu'] ?? 0);
    $owner = $dashboardUserNameMap[$idusu] ?? 'Mi agenda';
    $evtItem = [
        'id'       => intval($ev['id'] ?? 0),
        'title'    => idxDashboardCalendarTitle($ev, $dashboardIdclieNameMap),
        'timestamp'=> $evtTs,
        'date_only'=> date('Y-m-d', $evtTs),
        'owner'    => $owner,
        'hora'     => $hora,
        'tipo'     => isset($ev['tipo']) ? intval($ev['tipo']) : 0,
        'source'   => 'calendario',
    ];
    $evtItem['subtitle'] = idxDashboardCalendarSubtitle($evtItem, $dashboardUserNameMap);
    $evtItem['vendedor_label'] = $dashboardUserNameMap[$idusu] ?? '';
    $idclie = intval($ev['idclie'] ?? 0);
    $evtTipo = intval($ev['tipo'] ?? 0);
    if ($idclie > 0 && isset($dashboardIdclieNameMap[$idclie])) {
        $evtItem['assignee_label'] = $dashboardIdclieNameMap[$idclie];
        if ($evtTipo === 1) {
            $evtItem['assignee_kind'] = 'wp';
            $plannerId = intval($dashboardIdcliePlannerMap[$idclie] ?? 0);
            $evtItem['assignee_url'] = $plannerId > 0 ? 'planner_profile.php?id=' . $plannerId : '';
        } else {
            $evtItem['assignee_kind'] = 'lead';
            $evtItem['assignee_url'] = 'lead_interaction.php?tabla_origen=contact_form&id=' . $idclie;
        }
    }
    $dashboardEventsNormalized[] = $evtItem;
}

usort($dashboardEventsNormalized, function ($a, $b) {
    return $a['timestamp'] <=> $b['timestamp'];
});

$dashboardCitasHoyCount = 0;
$dashboardTodayUpcoming = [];
$dashboardAccionesVencidas = 0;
$dashboardPendingActions = [];
$dashboardUpcomingDeadlines = [];
$dashboardIn30DaysLimit = strtotime('+30 days', $dashboardNowTs);
$dashboardLlamadasIntroductoriasCount = 0;
$dashboardLlamadasIntroductoriasList = [];

foreach ($dashboardEventsNormalized as $evt) {
    $evtDate  = $evt['date_only'];
    $evtTipo  = intval($evt['tipo'] ?? 0);
    $isWP     = ($evtTipo === 1);

    if ($isWP) {
        // WP citas → Llamadas introductorias (all pending/upcoming)
        if ($evt['timestamp'] >= $dashboardNowTs) {
            $dashboardLlamadasIntroductoriasCount++;
            $dashboardLlamadasIntroductoriasList[] = $evt;
        }
    } else {
        // Regular citas → Citas hoy
        if ($evtDate === $dashboardToday) {
            $dashboardCitasHoyCount++;
        }
        if ($evtDate === $dashboardToday && $evt['timestamp'] >= $dashboardNowTs) {
            $dashboardTodayUpcoming[] = $evt;
        }
    }

    if ($evt['timestamp'] >= $dashboardNowTs && $evt['timestamp'] <= $dashboardIn30DaysLimit) {
        $dashboardUpcomingDeadlines[] = $evt;
    }
}

// Ensure next_action_completed column exists before querying it
$liColCheck = $conn->query("SHOW COLUMNS FROM `lead_interactions` LIKE 'next_action_completed'");
if ($liColCheck && $liColCheck->num_rows === 0) {
    $conn->query("ALTER TABLE `lead_interactions` ADD COLUMN `next_action_completed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `next_action_date`");
}

// Fetch scheduled actions from lead_interactions (next_action_date upcoming)
$liSql = "SELECT li.id, li.next_action, li.next_action_date, li.next_action_completed, li.interaction_type, li.outcome, li.tabla_origen, li.lead_id, li.notes, li.created_by,
    COALESCE(NULLIF(TRIM(CONCAT(u.nombre, ' ', u.apepat)), ''), NULLIF(TRIM(u.nombre), ''), '') AS vendedor_label
    FROM lead_interactions li
    LEFT JOIN usuarios u ON u.id = li.created_by
    WHERE li.next_action_date IS NOT NULL
      AND (li.next_action_completed IS NULL OR li.next_action_completed = 0)";
$idxSeeAllPendingInteractions = (usuarioTipoEsAdminLike($tipoUsu) || intval($userid) === 20);
if (!$idxSeeAllPendingInteractions) {
    $liSafeUserId = intval($userid);
    $liSql .= " AND (li.created_by = $liSafeUserId OR li.created_by IS NULL)";
}
$liSql .= " ORDER BY li.next_action_date ASC LIMIT 50";
$liResult = $conn->query($liSql);
$dashboardVencidasActions = [];
$liRowsBuffer = [];
if ($liResult) {
    while ($liRow = $liResult->fetch_assoc()) {
        $liRowsBuffer[] = $liRow;
    }
}
$interactionAssigneeMap = idxDashboardFetchInteractionAssignees($conn, $liRowsBuffer);
foreach ($liRowsBuffer as $liRow) {
    $liDate = trim((string) ($liRow['next_action_date'] ?? ''));
    $liTs = $liDate !== '' ? strtotime($liDate) : false;
    if ($liTs === false) continue;
    $liItem = [
        'id'               => intval($liRow['id'] ?? 0),
        'title'            => idxDashboardInteractionTitle($liRow),
        'timestamp'        => $liTs,
        'date_only'        => $liDate,
        'tabla_origen'     => trim((string) ($liRow['tabla_origen'] ?? '')),
        'lead_id'          => intval($liRow['lead_id'] ?? 0),
        'owner'            => trim((string) ($liRow['tabla_origen'] ?? '')) . ' #' . intval($liRow['lead_id'] ?? 0),
        'interaction_type' => trim((string) ($liRow['interaction_type'] ?? '')),
        'outcome'          => trim((string) ($liRow['outcome'] ?? '')),
        'source'           => 'interaccion',
        'vendedor_label'   => trim((string) ($liRow['vendedor_label'] ?? '')),
    ];
    $liItem = idxDashboardApplyAssignee($liItem, $interactionAssigneeMap);
    if ($liDate < $dashboardToday) {
        $dashboardAccionesVencidas++;
        $dashboardVencidasActions[] = $liItem;
    } else {
        $dashboardPendingActions[] = $liItem;
    }
}

// Mezclar citas futuras del calendario con interacciones pendientes
foreach ($dashboardEventsNormalized as $evt) {
    if ($evt['timestamp'] >= $dashboardNowTs && $evt['timestamp'] <= $dashboardIn30DaysLimit) {
        $dashboardPendingActions[] = $evt;
    }
}

// Sort: pending (not overdue) interactions + future calendar events
usort($dashboardPendingActions, function ($a, $b) { return $a['timestamp'] <=> $b['timestamp']; });
$dashboardPendingActions = array_values(array_filter($dashboardPendingActions, function ($e) use ($dashboardNowTs) {
    if (($e['source'] ?? '') === 'interaccion') {
        return true;
    }
    return $e['timestamp'] >= $dashboardNowTs;
}));
$dashboardPendingActions = array_slice($dashboardPendingActions, 0, 5);

// Sort vencidas by date descending (most overdue first)
usort($dashboardVencidasActions, function ($a, $b) { return $a['timestamp'] <=> $b['timestamp']; });

// Rebuild upcoming deadlines (already mixed in above)
usort($dashboardUpcomingDeadlines, function ($a, $b) { return $a['timestamp'] <=> $b['timestamp']; });
$dashboardUpcomingDeadlines = array_slice(array_filter($dashboardUpcomingDeadlines, function ($e) use ($dashboardNowTs, $dashboardIn30DaysLimit) {
    return $e['timestamp'] >= $dashboardNowTs && $e['timestamp'] <= $dashboardIn30DaysLimit;
}), 0, 3);

$dashboardProximaHoraLabel = 'sin próximas';
if (!empty($dashboardTodayUpcoming)) {
    $dashboardProximaHoraLabel = 'próxima: ' . date('g:i a', $dashboardTodayUpcoming[0]['timestamp']);
}

$dashboardLeadsActivos = intval($rpt_leadsPostCountFiltered ?? 0);
$dashboardPlannersSinContacto = 0;
$dashboardNoReply7dCount = 0;

// Planners sin contacto: WPs asignados a la vendedora (o todos si admin) sin interacción en 10+ días
$_wpVendorSql = "SELECT COUNT(*) AS cnt
    FROM wedding_planners wp
    LEFT JOIN (
        SELECT lead_id, MAX(COALESCE(interaction_date, DATE(created_at))) AS last_interaction
        FROM lead_interactions
        WHERE LOWER(tabla_origen) = 'wedding_planners'
        GROUP BY lead_id
    ) li ON li.lead_id = wp.id
    WHERE (wp.descartado IS NULL OR wp.descartado = 0)";
if ($tipoUsu == 1) {
    $_wpVendorSql .= " AND wp.id_vendedor_asignado = " . intval($userid);
}
$_wpVendorSql .= " AND (li.last_interaction IS NULL OR li.last_interaction <= DATE_SUB(CURDATE(), INTERVAL 10 DAY))";
$_wpVendorRes = $conn->query($_wpVendorSql);
if ($_wpVendorRes) {
    $dashboardPlannersSinContacto = intval($_wpVendorRes->fetch_assoc()['cnt'] ?? 0);
}

foreach ($rpt_displayPostLeads as $lead) {
    $created = trim((string)($lead['created_time'] ?? $lead['submission_date'] ?? ''));
    $createdTs = $created !== '' ? strtotime($created) : false;
    $status = mb_strtolower(trim((string)($lead['estatus'] ?? '')), 'UTF-8');

    if ($status === 'agendado' && $createdTs !== false && (($dashboardNowTs - $createdTs) / 86400) > 7) {
        $dashboardNoReply7dCount++;
    }
}

if (!function_exists('dashboardDueLabel')) {
    function dashboardDueLabel($timestamp, $today, $tomorrow)
    {
        $d = date('Y-m-d', $timestamp);
        if ($d === $today) {
            return 'vence hoy';
        }
        if ($d === $tomorrow) {
            return 'mañana ' . date('g:i a', $timestamp);
        }
        $days = (int)floor((strtotime($d) - strtotime($today)) / 86400);
        if ($days > 1 && $days <= 7) {
            return $days . ' días';
        }
        return date('j M', $timestamp);
    }
}

$conn->close();
?>


<style>
   

/* Contenedor principal del calendario */
#calendar {
    max-width: 80%;
    margin: 0 auto;
    padding: 20px;
    background-color: #fdfdfd;
    border-radius: 12px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    font-family: 'Roboto', sans-serif;
}

/* Encabezado del calendario (mes/año y controles) */
.calendar-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.calendar-controls h3 {
    font-size: 1.75rem;
    color: #333;
    margin: 0;
    font-weight: bold;
}

.calendar-controls button {
    padding: 10px 15px;
    font-size: 1rem;
    color: #464646;
    background-color: #eee8dc;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 123, 255, 0.2);
}

.calendar-controls button:hover {
    background-color: #0056b3;
    transform: translateY(-2px);
}

/* Tabla del calendario */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    background-color: #ffffff;
    border-radius: 8px;
    overflow: hidden;
}

table th {
    font-weight: bold;
    padding: 15px;
    text-align: center;
   color: #464646;
    background-color: #eee8dc;
    border: 1px solid #e0e0e0;
    text-transform: uppercase;
    font-size: 0.9rem;
}

table td {
    padding: 20px;
    text-align: center;
    color: #333;
    border: 1px solid #e0e0e0;
    cursor: pointer;
    font-size: 1rem;
    transition: background-color 0.3s ease, color 0.3s ease, transform 0.2s ease;
}

table td:hover {
    background-color: #f1f9ff;
    color: #007bff;
    transform: scale(1.05);
    box-shadow: inset 0 0 5px rgba(0, 123, 255, 0.1);
}

/* Días seleccionados o eventos */
table td.selected,
table td.event {
    background-color: #28a745;
    color: #fff;
    border: 1px solid #28a745;
    box-shadow: inset 0 0 10px rgba(40, 167, 69, 0.5);
    font-weight: bold;
}

/* Estilo para días deshabilitados (fuera del mes actual) */
table td.disabled {
    background-color: #f8f9fa;
    color: #b0b0b0;
    cursor: not-allowed;
}

/* Ajustes responsivos */
@media (max-width: 768px) {
    #calendar {
        padding: 10px;
    }

    .calendar-controls h3 {
        font-size: 1.5rem;
    }

    table th,
    table td {
        padding: 10px;
        font-size: 0.85rem;
    }
}

/* Context Menu */
.context-menu {
    position: absolute;
    z-index: 1000;
    background-color: #ffffff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 5px 0;
    animation: fadeIn 0.3s ease;
}

.context-menu ul {
    list-style-type: none;
    margin: 0;
    padding: 0;
}

.context-menu ul > li {
    padding: 10px 20px;
    color: #333;
    cursor: pointer;
    font-size: 0.95rem;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.context-menu ul > li:hover {
    color: #ffffff;
    background-color: #007bff;
}

/* Center event content (time + title) and prepare event for absolute badge */
.fc-day-grid-event {
    position: relative;
}

.fc-day-grid-event .fc-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-align: center;
}

/* Badge with agent initial positioned on the right */
.agent-initial-badge {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    z-index: 5;
}

/* Iconos */
.fa,
.fas {
    font-size: 0.85rem;
    margin-right: 6px;
    color: #007bff;
}

.fc-day-header .fc-widget-header .fc-sat {
    display: flex;
    justify-content: flex-end;
}

/* Animaciones */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* ═══════════════════════════════════════════════════════════
   ESTILOS DE REPORTES (basado en clientes.php / efege-postq)
   ═══════════════════════════════════════════════════════════ */

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

.efege-postq {
    font-family: 'DM Sans', sans-serif;
    color: var(--ink);
    min-height: 100vh;
    font-size: 13px;
    padding: 0 20px 60px;
}

/* ── PAGE HEADER ── */
.efege-postq .efege-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 20px 0 16px;
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border);
}
.efege-postq .efege-page-header-left { flex: 1; min-width: 0; }
.efege-postq .efege-page-header-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.efege-postq .efege-page-title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 26px;
    font-weight: 600;
    letter-spacing: 2px;
    color: var(--ink);
    line-height: 1;
    margin-bottom: 4px;
}
.efege-postq .efege-title-accent { color: var(--gold); }
.efege-postq .efege-page-title-sub {
    font-size: 14px;
    letter-spacing: 1px;
    font-weight: 400;
    color: var(--muted);
    font-family: 'DM Sans', sans-serif;
}
.efege-postq .efege-page-subtitle { font-size: 12px; color: var(--muted); margin-top: 4px; }
.efege-postq .efege-date-range {
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
.efege-postq .efege-date-range span { color: var(--ink); font-weight: 600; }
.efege-postq .efege-live-badge {
    font-size: 11px;
    color: var(--muted);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 6px 12px;
}

/* 15-days notice banner */
.efege-postq .efege-15days-notice {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, rgba(197,160,40,0.10), rgba(197,160,40,0.04));
    border: 1px solid var(--gold-border);
    border-left: 4px solid var(--gold);
    border-radius: 10px;
    padding: 11px 18px;
    margin-bottom: 16px;
    font-size: 13px;
    color: var(--ink-soft);
    line-height: 1.45;
}
.efege-postq .efege-15days-notice .efege-15days-icon {
    font-size: 16px;
    flex-shrink: 0;
}
.efege-postq .efege-15days-notice strong {
    color: var(--ink);
    font-weight: 700;
}

/* ── INDEX DASHBOARD COMPACT LAYOUT ── */
.efege-postq .dashboard-kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.efege-postq .dashboard-kpi-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 12px;
}
.efege-postq .dashboard-kpi-label {
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 6px;
    font-weight: 700;
}
.efege-postq .dashboard-kpi-value {
    font-size: 36px;
    line-height: 1;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 6px;
}
.efege-postq .dashboard-kpi-note {
    font-size: 12px;
    color: var(--muted);
}
.efege-postq .dashboard-kpi-alert .dashboard-kpi-value { color: #dc2626; }
.efege-postq .dashboard-kpi-warn .dashboard-kpi-value { color: var(--gold); }

.efege-postq .dashboard-warning {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff8eb;
    border: 1px solid #e7c67d;
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 14px;
    color: #946719;
    font-size: 14px;
    font-weight: 600;
}

.efege-postq .dashboard-main-grid {
    display: grid;
    grid-template-columns: 1.1fr 1.65fr;
    gap: 16px;
    align-items: start;
}

.efege-postq .dashboard-panel {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}

.efege-postq .pending-section-title {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 14px;
    color: var(--ink);
}

.efege-postq .pending-actions-list,
.efege-postq .deadlines-list {
    display: grid;
    gap: 10px;
}

.efege-postq .pending-action-item {
    border: 1px solid var(--border);
    border-left: 4px solid var(--planner);
    border-radius: 10px;
    padding: 10px 12px;
    background: #fff;
}
.efege-postq .pending-action-item:nth-child(2n) { border-left-color: var(--eng-low); }
.efege-postq .pending-action-item:nth-child(3n) { border-left-color: var(--community); }
.efege-postq .pending-action-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 4px;
}
.efege-postq .pending-action-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--muted);
    font-size: 13px;
}
.efege-postq .pending-chip {
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: 700;
    background: rgba(197,160,40,0.16);
    color: #8c6816;
}

/* ── Interaction card styles (mirrors planner_profile.php) ── */
.efege-postq .pending-actions-list .appointment-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.efege-postq .pending-actions-list .appointment-item {
    padding: 10px 10px;
    border-bottom: 1px solid #e2e8f0;
    transition: background 0.15s ease;
}
.efege-postq .pending-actions-list .appointment-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.efege-postq .pending-actions-list .appointment-top {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 4px;
}
.efege-postq .pending-actions-list .appointment-meta {
    font-size: 12px;
    color: #64748b;
    font-weight: 400;
}
.efege-postq .idx-assignee {
    margin-top: 2px;
}
.efege-postq .idx-assignee a {
    color: var(--eng);
    text-decoration: none;
    font-weight: 600;
}
.efege-postq .idx-assignee a:hover {
    text-decoration: underline;
}
.efege-postq .idx-vendedor {
    color: var(--muted);
}
.efege-postq .pending-actions-list .interaction-note {
    font-size: 12px;
    color: #475569;
    margin-top: 6px;
    line-height: 1.45;
}
.idx-btn-complete {
    margin-left: 6px;
    padding: 2px 8px;
    font-size: 11px;
    border-radius: 999px;
    cursor: pointer;
    background: #f0fdf4;
    border: 1px solid #86efac;
    color: #166534;
    font-weight: 600;
    vertical-align: middle;
}
.idx-btn-complete:hover { background: #dcfce7; }
.idx-completed-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    font-size: 11px;
    font-weight: 700;
    margin-left: 6px;
    vertical-align: middle;
}
.efege-postq .interaction-type-badge,
.efege-postq .outcome-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 999px;
    padding: 3px 9px;
    border: 1px solid transparent;
}
.efege-postq .type-llamada      { background:#dbeafe; color:#1d4ed8; border-color:#bfdbfe; }
.efege-postq .type-whatsapp     { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
.efege-postq .type-email        { background:#ede9fe; color:#6d28d9; border-color:#ddd6fe; }
.efege-postq .type-reunion      { background:#fef3c7; color:#92400e; border-color:#fde68a; }
.efege-postq .type-nota         { background:#e2e8f0; color:#334155; border-color:#cbd5e1; }
.efege-postq .type-sinrespuesta { background:#f3f4f6; color:#6b7280; border-color:#e5e7eb; }
.efege-postq .type-default      { background:#f8fafc; color:#475569; border-color:#e2e8f0; }
.efege-postq .outcome-positivo    { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
.efege-postq .outcome-neutral     { background:#f1f5f9; color:#334155; border-color:#e2e8f0; }
.efege-postq .outcome-negativo    { background:#fee2e2; color:#b91c1c; border-color:#fecaca; }
.efege-postq .outcome-cerrar      { background:#ffedd5; color:#c2410c; border-color:#fed7aa; }
.efege-postq .outcome-seguimiento { background:#fef3c7; color:#92400e; border-color:#fde68a; }
.efege-postq .outcome-sinrespuesta{ background:#f3f4f6; color:#6b7280; border-color:#e5e7eb; }
.efege-postq .outcome-default     { background:#f8fafc; color:#475569; border-color:#e2e8f0; }

.efege-postq .deadlines-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin: 16px 0 10px;
}
.efege-postq .deadlines-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--ink);
}
.efege-postq .deadlines-sub {
    color: var(--muted);
    font-size: 12px;
}
.efege-postq .deadline-item {
    display: grid;
    grid-template-columns: 62px 1fr auto;
    gap: 10px;
    align-items: center;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    padding-bottom: 8px;
}
.efege-postq .deadline-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.efege-postq .deadline-date {
    font-size: 20px;
    color: var(--muted);
}
.efege-postq .deadline-text {
    font-size: 17px;
    color: var(--ink-soft);
    font-weight: 500;
    line-height: 1.35;
}
.efege-postq .deadline-sub {
    display: block;
    margin-top: 2px;
    font-size: 13px;
    font-weight: 400;
    color: var(--muted);
}

.efege-postq .calendar-shell {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}

@media (max-width: 1300px) {
    .efege-postq .dashboard-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .efege-postq .dashboard-main-grid { grid-template-columns: 1fr; }
}
@media (max-width: 760px) {
    .efege-postq .dashboard-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .efege-postq .deadline-item { grid-template-columns: 52px 1fr; }
    .efege-postq .deadline-item .pending-chip { justify-self: start; }
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

/* ── ACCORDION REPORTES ── */
.efege-postq .report-accordion {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 16px;
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

/* ── CALENDAR SECTION ── */
.efege-postq .calendar-section {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px 28px;
    margin-bottom: 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}
.efege-postq .calendar-section-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}
.efege-postq .calendar-section-step {
    font-size: 11px;
    letter-spacing: 1.4px;
    text-transform: uppercase;
    color: var(--gold);
    font-weight: 700;
    margin-bottom: 6px;
}
.efege-postq .calendar-section-title { font-size: 18px; font-weight: 700; color: var(--ink); margin: 0 0 4px; }
.efege-postq .calendar-section-subtitle { font-size: 12px; color: var(--muted); margin: 0; }

/* User filter pill */
.efege-postq .calendar-user-filter {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
    flex-wrap: wrap;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 16px;
}
.efege-postq .calendar-user-filter label {
    font-size: 11px;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    white-space: nowrap;
}
.efege-postq .calendar-user-filter select {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--ink);
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    font-weight: 500;
    padding: 7px 32px 7px 12px;
    cursor: pointer;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    min-width: 220px;
}
.efege-postq .calendar-user-filter select:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--gold-dim);
}

/* FullCalendar overrides */
.efege-postq #calendar {
    max-width: 100%;
    margin: 0;
    padding: 0;
    background: transparent;
    border-radius: 0;
    box-shadow: none;
}

/* Toolbar */
.efege-postq .fc-toolbar {
    margin-bottom: 18px !important;
    align-items: center !important;
}
.efege-postq .fc-toolbar h2 {
    font-size: 17px;
    font-weight: 700;
    color: var(--ink);
    font-family: 'DM Sans', sans-serif;
    letter-spacing: 0.3px;
}
.efege-postq .fc-button {
    background: var(--panel) !important;
    border: 1px solid var(--border) !important;
    color: var(--ink) !important;
    font-family: 'DM Sans', sans-serif !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    padding: 6px 14px !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.06) !important;
    transition: all 0.18s !important;
    text-transform: capitalize !important;
}
.efege-postq .fc-button:hover {
    background: var(--gold-dim) !important;
    border-color: var(--gold-border) !important;
    color: #92700c !important;
    box-shadow: 0 2px 6px rgba(197,160,40,0.18) !important;
}
.efege-postq .fc-button-primary:not(:disabled).fc-button-active,
.efege-postq .fc-button-primary:not(:disabled):active {
    background: var(--gold) !important;
    border-color: var(--gold) !important;
    color: #fff !important;
    box-shadow: 0 2px 8px rgba(197,160,40,0.35) !important;
}

/* Day-of-week header row */
.efege-postq .fc-head-container .fc-widget-header {
    background: var(--surface) !important;
    border-color: var(--border) !important;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
    overflow: hidden;
}
.efege-postq .fc-day-header {
    font-size: 10px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 1.2px !important;
    color: var(--muted) !important;
    padding: 10px 0 !important;
    border-color: var(--border) !important;
}
.efege-postq .fc-day-header.fc-sat,
.efege-postq .fc-day-header.fc-sun {
    color: var(--gold) !important;
    opacity: 0.85;
}

/* Day cells */
.efege-postq .fc-day-grid .fc-row .fc-bg td,
.efege-postq .fc-day-grid .fc-row .fc-content-skeleton td {
    border-color: rgba(0,0,0,0.05) !important;
}
.efege-postq .fc-day-top {
    padding: 6px 8px 4px !important;
}
.efege-postq .fc-day-number {
    font-size: 12px !important;
    font-weight: 600 !important;
    color: var(--ink-soft) !important;
    width: 24px;
    height: 24px;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.15s, color 0.15s;
}

/* Today */
.efege-postq .fc-today {
    background: rgba(197,160,40,0.06) !important;
}
.efege-postq .fc-today .fc-day-number {
    background: var(--gold) !important;
    color: #fff !important;
    font-weight: 700 !important;
}

/* Weekend columns subtle tint */
.efege-postq .fc-sat,
.efege-postq .fc-sun {
    background: rgba(0,0,0,0.012) !important;
}

/* Out-of-month days */
.efege-postq .fc-other-month .fc-day-number {
    color: var(--border) !important;
    opacity: 0.5;
}

/* Event pills */
.efege-postq .fc-day-grid-event {
    border-radius: 6px !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    border: none !important;
    padding: 3px 7px !important;
    margin: 1px 3px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12) !important;
    transition: opacity 0.15s, transform 0.15s !important;
    cursor: pointer !important;
}
.efege-postq .fc-day-grid-event:hover {
    opacity: 0.88 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 3px 8px rgba(0,0,0,0.18) !important;
}
.efege-postq .fc-day-grid-event .fc-content {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* More-events link */
.efege-postq .fc-more {
    font-size: 11px !important;
    font-weight: 600 !important;
    color: var(--gold) !important;
    padding: 1px 4px !important;
}
.efege-postq .fc-more:hover { text-decoration: underline !important; }

@media (max-width: 900px) {
    .efege-postq .reports-layout,
    .efege-postq .reports-kpi-grid-secondary { grid-template-columns: 1fr; }
    .efege-postq .reports-charts-row { flex-direction: column; }
}
@media (max-width: 1100px) { .efege-postq .reports-layout { grid-template-columns: 1fr; } }
</style>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario</title>
   
   
</head>
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css'>
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/@fullcalendar/core@4.2.0/main.min.css'>
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@4.3.0/main.min.css'>
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
<link href='https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap' rel='stylesheet'>
<script src='https://code.highcharts.com/highcharts.js'></script>
<script src='https://code.highcharts.com/modules/accessibility.js'></script>


<div class="efege-postq">

    <!-- ════════════════ PAGE HEADER ════════════════ -->
    <div class="efege-page-header">
        <div class="efege-page-header-left">
            <div class="efege-page-title"><span class="efege-page-title-sub">Calendario</span></div>
            <div class="efege-page-subtitle" style="display:none;">Resumen de reportes y agenda · Plataforma: <strong><?php echo htmlspecialchars(ucfirst($rpt_platformLabel)); ?></strong></div>
        </div>
        <div class="efege-page-header-right" style="display:none;">
            <?php if (!empty($rpt_startDate) && !empty($rpt_endDate) && empty($_GET['show_all'])): ?>
            <div class="efege-date-range">📅 <span><?php echo date('d M Y', strtotime($rpt_startDate)); ?></span>&nbsp;→&nbsp;<span><?php echo date('d M Y', strtotime($rpt_endDate)); ?></span></div>
            <?php else: ?>
            <div class="efege-date-range">📅 <span>Todos los registros</span></div>
            <?php endif; ?>
            <div class="efege-live-badge">🔴 Live</div>
        </div>
    </div>

    <div class="efege-15days-notice" style="display:none;">
        <span>La información mostrada corresponde a los <strong>últimos 15 días</strong>.</span>
    </div>

    <!-- ════════════════ REPORTES SECTION (Cierres) ════════════════ -->
    <section class="reports-section" style="display:none;">
        <div class="reports-section-header">
            <div>
                <div class="reports-section-step">Apartado de Reportes</div>
                <h2 class="reports-section-title">Reporte de cierres</h2>
                <p class="reports-section-subtitle">Clientes que cerraron en los últimos 15 días.</p>
            </div>
            <div class="reports-range-chip">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo htmlspecialchars($rpt_reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <div class="reports-layout">
            <div class="reports-kpi-stack">
                <article class="report-card report-card-highlight">
                    <div class="report-card-label">Tasa de Conversión</div>
                    <div class="report-card-value"><?php echo ($rpt_leadsPostCountFiltered > 0) ? number_format($rpt_conversionRatio, 1) . '%' : 'N/A'; ?></div>
                    <div class="report-card-note"><?php echo number_format($rpt_leadsWithFechaCambio); ?> cierres de <?php echo number_format($rpt_leadsPostCountFiltered); ?> agendados</div>
                    <div class="report-formula"><strong>Fórmula:</strong> (Clientes cerrados / Total agendados) × 100</div>
                </article>
                <article class="report-card">
                    <div class="report-card-label">Total cierres</div>
                    <div class="report-card-value"><?php echo number_format($rpt_leadsCountFiltered); ?></div>
                    <div class="report-card-note">Clientes en el período.</div>
                </article>
            </div>

            <div class="reports-charts-row">
                <article class="report-chart-card">
                    <div class="report-chart-header">
                        <div>
                            <h3 class="report-chart-title">Cierres por período</h3>
                            <p class="report-chart-subtitle">Total de cierres por día dentro del rango seleccionado.</p>
                        </div>
                    </div>
                    <div id="rpt_clientsChart" class="report-chart"></div>
                </article>
                <article class="report-chart-card">
                    <div class="report-chart-header">
                        <div>
                            <h3 class="report-chart-title">Método de contacto</h3>
                            <p class="report-chart-subtitle">Distribución de cierres según origen del cliente.</p>
                        </div>
                    </div>
                    <div id="rpt_clientsContactChart" class="report-chart"></div>
                </article>
                <article class="report-chart-card">
                    <div class="report-chart-header">
                        <div>
                            <h3 class="report-chart-title">Desde cuándo nos conoce</h3>
                            <p class="report-chart-subtitle">Distribución de cierres según el tiempo que llevan conociendo a Efege.</p>
                        </div>
                    </div>
                    <div id="rpt_clientsHowLongChart" class="report-chart"></div>
                </article>
            </div>
        </div>
    </section>

    <!-- ════════════════ REPORTE PRE-QUALIFIED ════════════════ -->
    <section class="reports-section" style="display:none;">
        <div class="reports-section-header">
            <div>
                <div class="reports-section-step">Reporte Pre-Qualified</div>
                <h2 class="reports-section-title">Reporte de tasa de calificación</h2>
                <p class="reports-section-subtitle">Leads pre-calificados en los últimos 15 días.</p>
            </div>
            <div class="reports-range-chip">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo htmlspecialchars($rpt_reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
                <div class="reports-layout">
                    <div class="reports-kpi-stack">
                        <article class="report-card report-card-highlight">
                            <div class="report-card-label">Tasa de calificación</div>
                            <div class="report-card-value"><?php echo $rpt_tasaConversion; ?>%</div>
                            <div class="report-card-note"><?php echo number_format($rpt_leadsAgendadosTotal); ?> personas que agendaron de <?php echo number_format($rpt_totalPreLeads); ?> registros</div>
                            <div class="report-formula"><strong>Fórmula:</strong> (Cantidad de personas que agendaron / Total de registros) × 100</div>
                        </article>
                        <div class="reports-kpi-grid-secondary">
                            <article class="report-card">
                                <div class="report-card-label">Total de registros</div>
                                <div class="report-card-value"><?php echo number_format($rpt_totalPreLeads); ?></div>
                                <div class="report-card-note">Leads incluidos en el rango seleccionado.</div>
                            </article>
                            <article class="report-card">
                                <div class="report-card-label">Personas que agendaron</div>
                                <div class="report-card-value"><?php echo number_format($rpt_leadsAgendadosTotal); ?></div>
                                <div class="report-card-note">Registros con estatus agendado, atendido, fantasma o muerto.</div>
                            </article>
                        </div>
                    </div>
                    <div class="reports-charts-row">
                        <article class="report-chart-card">
                            <div class="report-chart-header">
                                <div>
                                    <h3 class="report-chart-title">Conteo total de registros por período</h3>
                                    <p class="report-chart-subtitle">Total de leads Pre-Q por día dentro del rango seleccionado.</p>
                                </div>
                            </div>
                            <div id="rpt_preQLeadsChart" class="report-chart"></div>
                        </article>
                        <article class="report-chart-card">
                            <div class="report-chart-header">
                                <div>
                                    <h3 class="report-chart-title">Método de contacto</h3>
                                    <p class="report-chart-subtitle">Distribución de leads según canal de origen del registro.</p>
                                </div>
                            </div>
                            <div id="rpt_preQContactMethodChart" class="report-chart"></div>
                        </article>
                        <article class="report-chart-card">
                            <div class="report-chart-header">
                                <div>
                                    <h3 class="report-chart-title">Desde cuándo nos conoce</h3>
                                    <p class="report-chart-subtitle">Distribución de leads según el tiempo que llevan conociendo a Efege.</p>
                                </div>
                            </div>
                            <div id="rpt_preQKnownUsChart" class="report-chart"></div>
                        </article>
                    </div>
                </div>
    </section>

    <!-- ════════════════ REPORTE POST-QUALIFIED ════════════════ -->
    <section class="reports-section" style="display:none;">
        <div class="reports-section-header">
            <div>
                <div class="reports-section-step">Reporte Post-Qualified</div>
                <h2 class="reports-section-title">Reporte de sesiones agendadas</h2>
                <p class="reports-section-subtitle">Leads con cita agendada en los últimos 15 días.</p>
            </div>
            <div class="reports-range-chip">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo htmlspecialchars($rpt_reportRangeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
                <div class="reports-layout">
                    <div class="reports-kpi-stack">
                        <article class="report-card report-card-highlight">
                            <div class="report-card-label">Tasa de Conversión</div>
                            <div class="report-card-value"><?php echo ($rpt_leadsPostCountFiltered > 0) ? number_format($rpt_conversionRatio, 1) . '%' : 'N/A'; ?></div>
                            <div class="report-card-note"><?php echo number_format($rpt_leadsWithFechaCambio); ?> cierres de <?php echo number_format($rpt_leadsPostCountFiltered); ?> agendados</div>
                            <div class="report-formula"><strong>Fórmula:</strong> (Clientes cerrados / Total agendados) × 100</div>
                        </article>
                        <div class="reports-kpi-grid-secondary">
                            <article class="report-card">
                                <div class="report-card-label">Total agendados</div>
                                <div class="report-card-value"><?php echo number_format($rpt_leadsPostCountFiltered); ?></div>
                                <div class="report-card-note">Registros con cita en el período.</div>
                            </article>
                            <article class="report-card">
                                <div class="report-card-label">Sesiones atendidas</div>
                                <div class="report-card-value"><?php echo number_format($rpt_totalAtendidos); ?></div>
                                <div class="report-card-note">Citas con estatus atendido.</div>
                            </article>
                        </div>
                    </div>
                    <div class="reports-charts-row">
                        <article class="report-chart-card">
                            <div class="report-chart-header">
                                <div>
                                    <h3 class="report-chart-title">Sesiones agendadas por período</h3>
                                    <p class="report-chart-subtitle">Total de sesiones agendadas por día dentro del rango seleccionado.</p>
                                </div>
                            </div>
                            <div id="rpt_postQLeadsChart" class="report-chart"></div>
                        </article>
                        <article class="report-chart-card">
                            <div class="report-chart-header">
                                <div>
                                    <h3 class="report-chart-title">Método de contacto</h3>
                                    <p class="report-chart-subtitle">Distribución de sesiones según canal de origen del agendado.</p>
                                </div>
                            </div>
                            <div id="rpt_postQContactMethodChart" class="report-chart"></div>
                        </article>
                        <article class="report-chart-card">
                            <div class="report-chart-header">
                                <div>
                                    <h3 class="report-chart-title">Desde cuándo nos conoce</h3>
                                    <p class="report-chart-subtitle">Distribución de sesiones según el tiempo que llevan conociendo a Efege.</p>
                                </div>
                            </div>
                            <div id="rpt_postQKnownUsChart" class="report-chart"></div>
                        </article>
                    </div>
                </div>
    </section>

    <section class="dashboard-kpi-grid">
        <article class="dashboard-kpi-card">
            <div class="dashboard-kpi-label">Citas hoy</div>
            <div class="dashboard-kpi-value"><?php echo number_format($dashboardCitasHoyCount); ?></div>
            <div class="dashboard-kpi-note"><?php echo htmlspecialchars($dashboardProximaHoraLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
        <article class="dashboard-kpi-card">
            <div class="dashboard-kpi-label">Llamadas introductorias</div>
            <div class="dashboard-kpi-value"><?php echo number_format($dashboardLlamadasIntroductoriasCount); ?></div>
            <div class="dashboard-kpi-note"><?php echo $dashboardLlamadasIntroductoriasCount === 1 ? 'cita pendiente de WP' : 'citas pendientes de WP'; ?></div>
        </article>
        <article class="dashboard-kpi-card dashboard-kpi-alert">
            <div class="dashboard-kpi-label">Acciones vencidas</div>
            <div class="dashboard-kpi-value"><?php echo number_format($dashboardAccionesVencidas); ?></div>
            <div class="dashboard-kpi-note">requieren atención</div>
        </article>
        <article class="dashboard-kpi-card">
            <div class="dashboard-kpi-label">Leads activos</div>
            <div class="dashboard-kpi-value"><?php echo number_format($dashboardLeadsActivos); ?></div>
            <div class="dashboard-kpi-note">en mi pipeline</div>
        </article>
        <article class="dashboard-kpi-card dashboard-kpi-warn">
            <div class="dashboard-kpi-label">Planner sin contacto</div>
            <div class="dashboard-kpi-value"><?php echo number_format($dashboardPlannersSinContacto); ?></div>
            <div class="dashboard-kpi-note">+10 días</div>
        </article>
    </section>

    <?php if ($dashboardPlannersSinContacto > 0): ?>
    <div class="dashboard-warning">
        <span>⚠</span>
        <span><?php echo number_format($dashboardPlannersSinContacto); ?> planner<?php echo $dashboardPlannersSinContacto !== 1 ? 's' : ''; ?> llevan más de 10 días sin interacción. Considera retomar contacto.</span>
    </div>
    <?php endif; ?>

    <section class="dashboard-main-grid">
        <aside class="dashboard-panel">
            <h2 class="pending-section-title">Acciones pendientes</h2>
            <div class="pending-actions-list">
                <?php if (!empty($dashboardPendingActions)): ?>
                    <div class="appointment-list">
                    <?php foreach ($dashboardPendingActions as $evt): ?>
                        <?php
                            $evtType    = trim((string) ($evt['interaction_type'] ?? ''));
                            $evtOutcome = trim((string) ($evt['outcome'] ?? ''));
                            $evtSource  = $evt['source'] ?? 'calendario';
                            $evtDueLabel = htmlspecialchars(dashboardDueLabel($evt['timestamp'], $dashboardToday, $dashboardTomorrow), ENT_QUOTES, 'UTF-8');
                            $evtId      = intval($evt['id'] ?? 0);
                        ?>
                        <div class="appointment-item" <?php echo ($evtSource === 'interaccion' && $evtId > 0) ? 'data-interaction-id="' . $evtId . '"' : ''; ?>>
                            <?php if ($evtSource === 'interaccion'): ?>
                                <div class="appointment-top">
                                    <?php if ($evtType !== ''): ?>
                                        <span class="interaction-type-badge <?php echo idxTypeClass($evtType); ?>">
                                            <span><?php echo htmlspecialchars(idxTypeIcon($evtType), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span><?php echo htmlspecialchars($evtType, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($evtOutcome !== ''): ?>
                                        <span class="outcome-badge <?php echo idxOutcomeClass($evtOutcome); ?>">
                                            <span><?php echo htmlspecialchars(idxOutcomeIcon($evtOutcome), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span><?php echo htmlspecialchars($evtOutcome, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="appointment-meta"><?php echo htmlspecialchars(idxFormatDate($evt['date_only']), ENT_QUOTES, 'UTF-8'); ?> &middot; <?php echo $evtDueLabel; ?></div>
                                <?php if (!empty($evt['assignee_label'])): ?>
                                <div class="appointment-meta idx-assignee">
                                    <?php echo htmlspecialchars(idxDashboardAssigneeTypeLabel($evt['assignee_kind'] ?? 'lead'), ENT_QUOTES, 'UTF-8'); ?>:
                                    <?php if (!empty($evt['assignee_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($evt['assignee_url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($evt['assignee_label'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($evt['assignee_label'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <div class="appointment-meta idx-vendedor">Vendedor: <?php echo htmlspecialchars(trim((string) ($evt['vendedor_label'] ?? '')) !== '' ? $evt['vendedor_label'] : 'Sin registrar', ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="appointment-meta">Próxima acción: <?php echo htmlspecialchars($evt['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($evtId > 0): ?>
                                        <button type="button" class="idx-btn-complete" data-interaction-id="<?php echo $evtId; ?>">Completar</button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="appointment-top">
                                    <span class="interaction-type-badge type-default">📅 Cita</span>
                                    <span class="pending-chip"><?php echo $evtDueLabel; ?></span>
                                </div>
                                <div class="appointment-meta">
                                    <?php echo htmlspecialchars(idxFormatDate($evt['date_only']), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (!empty($evt['subtitle'])): ?>
                                        &middot; <?php echo htmlspecialchars($evt['subtitle'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php elseif (!empty($evt['owner'])): ?>
                                        &middot; <?php echo htmlspecialchars($evt['owner'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($evt['assignee_label'])): ?>
                                <div class="appointment-meta idx-assignee">
                                    <?php echo htmlspecialchars(idxDashboardAssigneeTypeLabel($evt['assignee_kind'] ?? 'lead'), ENT_QUOTES, 'UTF-8'); ?>:
                                    <?php if (!empty($evt['assignee_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($evt['assignee_url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($evt['assignee_label'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($evt['assignee_label'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($evt['vendedor_label'])): ?>
                                <div class="appointment-meta idx-vendedor">Vendedor: <?php echo htmlspecialchars($evt['vendedor_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                                <?php
                                    $calTitle = trim((string) ($evt['title'] ?? ''));
                                    $calAssignee = trim((string) ($evt['assignee_label'] ?? ''));
                                    $calShowTitle = $calTitle !== '' && ($calAssignee === '' || strcasecmp($calTitle, $calAssignee) !== 0);
                                ?>
                                <?php if ($calShowTitle): ?>
                                <div class="interaction-note"><?php echo htmlspecialchars($calTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <article class="pending-action-item">
                        <div class="pending-action-title">Sin acciones por ahora</div>
                        <div class="pending-action-meta">
                            <span>La agenda está al día</span>
                        </div>
                    </article>
                <?php endif; ?>
            </div>

            <?php if (!empty($dashboardVencidasActions)): ?>
            <div class="deadlines-header" style="margin-top:18px;">
                <div class="deadlines-title" style="color:#b91c1c;">Acciones vencidas <span class="deadlines-sub" style="color:#ef4444;">(<?php echo count($dashboardVencidasActions); ?>)</span></div>
            </div>
            <div class="pending-actions-list">
                <div class="appointment-list">
                <?php foreach ($dashboardVencidasActions as $evt): ?>
                    <?php
                        $evtType    = trim((string) ($evt['interaction_type'] ?? ''));
                        $evtOutcome = trim((string) ($evt['outcome'] ?? ''));
                        $evtId      = intval($evt['id'] ?? 0);
                    ?>
                    <div class="appointment-item" style="border-left:3px solid #ef4444;" <?php echo $evtId > 0 ? 'data-interaction-id="' . $evtId . '"' : ''; ?>>
                        <div class="appointment-top">
                            <?php if ($evtType !== ''): ?>
                                <span class="interaction-type-badge <?php echo idxTypeClass($evtType); ?>">
                                    <span><?php echo htmlspecialchars(idxTypeIcon($evtType), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span><?php echo htmlspecialchars($evtType, ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            <?php endif; ?>
                            <?php if ($evtOutcome !== ''): ?>
                                <span class="outcome-badge <?php echo idxOutcomeClass($evtOutcome); ?>">
                                    <span><?php echo htmlspecialchars(idxOutcomeIcon($evtOutcome), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span><?php echo htmlspecialchars($evtOutcome, ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                            <?php endif; ?>
                            <span class="pending-chip" style="background:#fee2e2;color:#b91c1c;border-color:#fecaca;">Vencida</span>
                        </div>
                        <div class="appointment-meta" style="color:#b91c1c;"><?php echo htmlspecialchars(idxFormatDate($evt['date_only']), ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if (!empty($evt['assignee_label'])): ?>
                        <div class="appointment-meta idx-assignee">
                            <?php echo htmlspecialchars(idxDashboardAssigneeTypeLabel($evt['assignee_kind'] ?? 'lead'), ENT_QUOTES, 'UTF-8'); ?>:
                            <?php if (!empty($evt['assignee_url'])): ?>
                                <a href="<?php echo htmlspecialchars($evt['assignee_url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($evt['assignee_label'], ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($evt['assignee_label'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="appointment-meta idx-vendedor">Vendedor: <?php echo htmlspecialchars(trim((string) ($evt['vendedor_label'] ?? '')) !== '' ? $evt['vendedor_label'] : 'Sin registrar', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="appointment-meta">Próxima acción: <?php echo htmlspecialchars($evt['title'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($evtId > 0): ?>
                                <button type="button" class="idx-btn-complete" data-interaction-id="<?php echo $evtId; ?>">Completar</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="deadlines-header">
                <div class="deadlines-title">Upcoming Deadlines <span class="deadlines-sub">(30 días)</span></div>
            </div>
            <div class="deadlines-list">
                <?php if (!empty($dashboardUpcomingDeadlines)): ?>
                    <?php foreach ($dashboardUpcomingDeadlines as $evt): ?>
                        <div class="deadline-item">
                            <div class="deadline-date"><?php echo htmlspecialchars(strtolower(date('j M', $evt['timestamp'])), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="deadline-text">
                                <?php echo htmlspecialchars($evt['title'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($evt['subtitle'])): ?>
                                    <span class="deadline-sub"><?php echo htmlspecialchars($evt['subtitle'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="pending-chip"><?php echo htmlspecialchars(dashboardDueLabel($evt['timestamp'], $dashboardToday, $dashboardTomorrow), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="deadline-item">
                        <div class="deadline-date">--</div>
                        <div class="deadline-text">Sin deadlines en los próximos 30 días</div>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <section class="calendar-shell">
            <div class="calendar-section">
                <div class="calendar-section-header">
                    <div>
                        <div class="calendar-section-step">Agenda</div>
                        <h2 class="calendar-section-title">Calendario</h2>
                        <p class="calendar-section-subtitle">Visualiza y agrega eventos en el calendario.</p>
                    </div>
                </div>

                <?php if (usuarioTipoEsAdminLike($tipoUsu)): ?>
                <div class="calendar-user-filter">
                    <label for="userFilter">Filtrar por usuario:</label>
                    <select id="userFilter">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= htmlspecialchars($user['id']) ?>">
                                <?= htmlspecialchars($user['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div id='calendar'></div>
            </div>
        </section>
    </section>
    <div class="modal fade edit-form" id="form" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title" id="modal-title">Agregar evento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="myForm">
                    <div class="modal-body">
                       
                        <div class="form-group">
                            <label for="event-title">titulo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="event-title" placeholder="Ingresa el nombre del evento" required>
                        </div>
                        <div class="form-group">
                            <label for="start-date">Fecha <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="start-date" placeholder="start-date" required>
                        </div>
                       
                                               <div>
                                                   <label for="eventDescription">Descripción <span class="text-danger">*</span></label>
                                                     <textarea class="form-control" id="eventDescription" name="description" rows="3" placeholder="Descripción del evento" required></textarea>
                                               </div>
                                                 <div class="form-group">
                                                      <label for="eventTime" class="form-label">Hora</label>
                            <input type="time" class="form-control" id="eventTime" name="time" required>
                                                 </div>
                                                  <?php if (usuarioTipoEsAdminLike($tipoUsu)): ?>
                        <div class="form-group">
                            <label for="userSelect">Seleccionar usuario</label>
                            <select class="form-control" id="userSelect" name="userSelect" required>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= htmlspecialchars($user['id']) ?>">
                                        <?= htmlspecialchars($user['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                        </div>
                        
                    <div class="modal-footer border-top-0 d-flex justify-content-center">
                        <button type="submit" class="btn btn-success" id="submit-button">Submit</button>
                      </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade edit-form" id="second-form" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title" id="modal-title-2">Agregar evento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="mySecondForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="event-title">titulo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="event-title-2" placeholder="Ingresa el nombre del evento" required>
                        </div>
                       
                                               <div>
                                                   <label for="eventDescription">Descripción <span class="text-danger">*</span></label>
                                                     <textarea class="form-control" id="eventDescription-2" name="description" rows="3" placeholder="Descripción del evento" required></textarea>
                                               </div>
                                                 <div class="form-group">
                                                      <label for="eventTime" class="form-label">Hora</label>
                            <input type="time" class="form-control" id="eventTime-2" name="time" required>
                                                 </div>
                                                  <?php if (usuarioTipoEsAdminLike($tipoUsu)): ?>
                        <div class="form-group">
                            <label for="userSelect">Seleccionar usuario</label>
                            <select class="form-control" id="userSelect" name="userSelect" required>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= htmlspecialchars($user['id']) ?>">
                                        <?= htmlspecialchars($user['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                        </div>
                        
                    <div class="modal-footer border-top-0 d-flex justify-content-center">
                        <button type="submit" class="btn btn-success" id="submit-button-2">Submit</button>
                      </div>
                </form>
            </div>
        </div>
    </div>
<div class="modal fade" id="modalData" tabindex="-1" aria-labelledby="modalDataLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">M&aacute;s informaci&oacute;n del evento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
                <!-- Aquí se mostrarán los detalles del evento -->
                <div id="modalDataContent">
                    <!-- El contenido se llenará dinámicamente con JavaScript -->
                </div>
            </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

</div>

<?php include 'footer.php'; ?>
   
    
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@4.2.0/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@4.2.0/main.js'></script>
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@4.2.0/main.js'></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/uuid@8.3.2/dist/umd/uuidv4.min.js'></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/static/js/components/dark.js"></script>
<script src="assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>



<script>
   $(document).ready(function () {
       
  const calendarEl = $('#calendar')[0];
  const myModal = new bootstrap.Modal($('#form')[0]);
 const mySecondModal = new bootstrap.Modal($('#second-form')[0]);

    
    
    
    
  const dangerAlert = $('#danger-alert')[0];
  const close = $('.btn-close')[0];
const eventsUser = <?php echo json_encode($events); ?>;
    const users = <?php echo json_encode($users); ?>;

    console.log("eventsUser ",eventsUser)
    console.log("users ",users)

const tipousu = <?php echo $tipoUsu; ?>;
        console.log("tipousu ",tipousu)


function formatTime(hora){
    
   

// Dividir la hora en horas, minutos y segundos
let [hh, mm, ss] = hora.split(':');

// Convertir la hora de 24 horas a 12 horas
let hour = parseInt(hh, 10);
let period = hour >= 12 ? 'PM' : 'AM';
hour = hour % 12 || 12;  // Si la hora es 0 (medianoche), convertimos a 12

// Formatear la hora como "hh:mm AM/PM"
return formattedHora = `${hour}:${mm} ${period}`;
}



// Generador determinístico de colores por id de usuario
function colorForId(id) {
    // Usamos un multiplicador primo para dispersar los tonos y mantenerlos reproducibles
    const hue = (parseInt(id, 10) * 137) % 360;
    return `hsl(${hue},70%,45%)`;
}

let c = []

if(tipousu == 0){
    
// Filtramos los eventos cuando cambia la selección del usuario
document.getElementById('userFilter').addEventListener('change', function() {
    const selectedUserId = this.value; // ID del usuario seleccionado

    // Filtramos los eventos según el usuario seleccionado
    const filteredEvents = selectedUserId
        ? eventsUser.filter(event => event.idusu == selectedUserId) // Filtrar por idusu
        : eventsUser; // Mostrar todos los eventos si no se selecciona un usuario

    // Procesamos los eventos filtrados y actualizamos el calendario
   c = filteredEvents.map(event => {
        let idusu = event.idusu;
        const color = colorForId(idusu);
        return {
            id: event.id,
            idusu: idusu,
            title: formatTime(event.hora)+" "+event.titulo,
              start: `${event.fecha}T${event.hora}`,
            end: event.fecha,
            backgroundColor: color,
            borderColor: color,
            allDay: false,
            nota: event.nota,
            hora: event.hora,
        };
    });
    
   // Actualizamos el calendario con los eventos filtrados
    calendar.getEvents().forEach(event => event.remove()); // Eliminar eventos existentes
    calendar.addEventSource(c); // Agregar los nuevos eventos filtrados

   

   
});
}


if (tipousu == 0 && eventsUser.length > 0) {
    c = eventsUser.map(event => {
        const color = colorForId(event.idusu);
        return {
            id: event.id,
            idusu: event.idusu,
            title:formatTime(event.hora)+" "+event.titulo,

               start: `${event.fecha}T${event.hora}`,
            end: event.fecha,
            backgroundColor: color,
            borderColor: color,
            allDay: false,
            nota: "",
            hora: event.hora,
        };
    });

    
} else if (tipousu == 1 && eventsUser.length > 0) {
    c = eventsUser.map(event => {
        return {
            title:formatTime(event.hora)+" "+event.titulo,

            start: event.fecha,
            end: event.fecha,
            backgroundColor: "#0000FF",
            borderColor: "#0000FF",
            allDay: false,
            id: event.id,
            nota: "",
            hora: event.hora,
        };
    });

   
}

const myEvents = c

  const calendar = new FullCalendar.Calendar(calendarEl, {
    // customButtons: {
    //   customButton: {
    //     text: 'Agregar evento',
    //     click: function () {
    //       myModal.show();
    //       $('#modal-title').html('Agregar evento');
    //       $('#submit-button').html('Agregar evento').removeClass('btn-primary').addClass('btn-success');

    //       $(close).on('click', function () {
    //         myModal.hide();
    //       });
    //     },
    //   },
    // },
    header: {
      center: 'customButton',
      right: 'today, prev,next ',
    },
    plugins: ['dayGrid', 'interaction'],
    allDay: false,
    editable: true,
    selectable: true,
    unselectAuto: false,
    displayEventTime: false,
    events: myEvents,
    eventClick: function(info) {
        console.log("info ", info)
        let id = info.event.id
        let title = info.event.title
        
        $.ajax({
            url: 'consulta-calendario.php', // Archivo PHP que procesará la consulta
            type: 'POST',
            data: {
                id: id // Enviar el id al servidor
            },
            success: function(response) {
           var data = JSON.parse(response);
                         console.log("datos de la cita ",data)

           let cliente = data.cliente;
           let vendedor = data.vendedor;
           let evento = data.evento;
            let eventoNota = "Sin nota"
        //   let eventoNota = evento.nota != '' ? evento.nota : "Sin nota"
            if(data){
                var formattedDate = moment(evento.fecha + ' ' + evento.hora).format('DD-MM-YYYY hh:mm a');
                           var formattedTime = moment(evento.hora, 'HH:mm:ss').format('hh:mm a');

let comonosconocio;  // Declaramos la variable fuera del switch

switch (cliente.hear_about_us) {
    case "1": 
    case 1: 
        comonosconocio = "Instagram";
        break;
        
    case "2": 
    case 2: 
        comonosconocio = "Google Ads";
        break;
        
    case "3": 
    case 3: 
        comonosconocio = "Website";
        break;
        
    case "4": 
    case 4: 
        comonosconocio = "Wedding Planner";
        break;
        
    case "5": 
    case 5: 
        comonosconocio = "Recommendation";
        break;

    default:
        comonosconocio = cliente.hear_about_us;  // Mantenemos el valor original
}
let asesor = vendedor.nombre;

if (vendedor.apeMat && vendedor.apeMat !== '.') {
  asesor += " " + vendedor.apeMat;
}

if (vendedor.apePat && vendedor.apePat !== '.') {
  asesor += " " + vendedor.apePat;
}        // Formatear los datos para mostrarlos en el modal
       var htmlContent = '<div>';
        htmlContent += '<div class="my-2">';
        htmlContent += '<h3><strong>Datos del cliente</strong></h3>'; 
        htmlContent += '<div class="d-flex justify-content-between mt-1">'+
            '<p class="m-1"><strong>Nombre del cliente:</strong> '+cliente.names+'</p></div>';
        htmlContent += '<div class="d-flex justify-content-between mt-1">'+
            '<p class="m-1"><strong>Teléfono:</strong> '+cliente.telephone+'</p></div>';
        htmlContent += '<div class="d-flex justify-content-between mt-1">'+
            '<p class="m-1"><strong>Correo:</strong> '+cliente.email_address+'</p></div><hr>';
            
        htmlContent += '<h3><strong>Datos de la cita</strong></h3>';
         
         // Mostrar fecha y hora del vendedor
         htmlContent += '<div class="d-flex justify-content-between mt-1" style="background-color: #e7f3ff; padding: 8px; border-radius: 5px;">'+
            '<p class="m-1"><strong><i class="fas fa-user-tie"></i> Fecha de la cita (Vendedor):</strong> '+evento.fecha +'</p></div>';
         htmlContent += '<div class="d-flex justify-content-between mt-1" style="background-color: #e7f3ff; padding: 8px; border-radius: 5px;">'+
            '<p class="m-1"><strong><i class="fas fa-clock"></i> Hora de la cita (Vendedor):</strong> '+formattedTime +'</p></div>';
         
         // Mostrar fecha y hora del cliente si son diferentes
         if (evento.fecha_cliente && evento.fecha_cliente != '0000-00-00' && evento.fecha_cliente != evento.fecha) {
             htmlContent += '<div class="d-flex justify-content-between mt-1" style="background-color: #e7ffe7; padding: 8px; border-radius: 5px;">'+
                '<p class="m-1"><strong><i class="fas fa-user"></i> Fecha de la cita (Cliente):</strong> '+evento.fecha_cliente +'</p></div>';
         }
         
         if (evento.hora_cliente && evento.hora_cliente != '00:00:00' && evento.hora_cliente != evento.hora) {
             var formattedTimeCliente = moment(evento.hora_cliente, 'HH:mm:ss').format('hh:mm a');
             htmlContent += '<div class="d-flex justify-content-between mt-1" style="background-color: #e7ffe7; padding: 8px; border-radius: 5px;">'+
                '<p class="m-1"><strong><i class="fas fa-clock"></i> Hora de la cita (Cliente):</strong> '+formattedTimeCliente +'</p></div>';
         }
         
        htmlContent += '<div class="d-flex justify-content-between mt-1">'+
            '<p class="m-1"><strong>Asesor asignado:</strong> '+asesor+'</p></div>';
            htmlContent += '<div class="d-flex justify-content-between mt-1">'+
            '<p class="m-1"><strong>Nota:</strong> '+eventoNota+'</p></div>';
           htmlContent += '<div class="d-flex justify-content-between mt-1">'+
    '<p class="m-1"><strong>Enlace de llamada:</strong> <a href="' + vendedor.enlace_meet + '" target="_blank">' + vendedor.enlace_meet + '</a></p>'+
'</div>';
        
        // Agregar botón de reagendar manual
        htmlContent += '<div class="d-flex justify-content-end mt-3">'+
            '<a href="reagendar_manual.php?id='+evento.id+'" class="btn btn-primary btn-sm">'+
            '<i class="fas fa-calendar-alt"></i> Reagendar (Zonas Horarias)</a>'+
        '</div>';
        
        htmlContent += '</div><hr>';

         
        var formattedWedding = moment(cliente.wedding_date).format('DD-MM-YYYY');

         htmlContent += '<div class="my-3">';
         htmlContent += '<h3><strong>Datos del evento</strong></h3>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Día del evento:</strong> ' + formattedWedding + '</p>'
         +'</div>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Lugar del evento:</strong> ' + cliente.wedding_location + '</p>'
         +'</div>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Total de invitados:</strong> ' +cliente.guests_count + '</p>'
         +'</div>';
        htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Servicio de interés:</strong> ' +cliente.service_interest + '</p>'
         +'</div>';
          htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Estilo de fotografía:</strong> ' +cliente.look_preference + '</p>'
         +'</div>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Planificador de boda:</strong> ' +cliente.wedding_planner + '</p>'
         +'</div>';
          htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>¿Cómo nos conoció?:</strong> ' +comonosconocio + '</p>'
         +'</div>';
          htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>¿Cómo se conocieron?:</strong> ' +cliente.how_did_you_meet + '</p>'
         +'</div>';
           htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Actividades de pareja:</strong> ' +cliente.couple_activities + '</p>'
         +'</div>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Película o canción favorita:</strong> ' +cliente.favorite_movie_or_song + '</p>'
         +'</div>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Instagram:</strong> ' +cliente.instagram_handle + '</p>'
         +'</div>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Detalles adicionales:</strong> ' +cliente.additional_details + '</p>'
         +'</div>';
      
        htmlContent += '</div>';

        // Mostrar los datos en el modal
        $('#modalDataContent').html(htmlContent);
        $('#modalData').modal('show');
            }
            //     if (data) {
                    
                  
            //           // Rellenar el modal con la información
            //         document.getElementById("modalData").querySelector("#modalTitle").textContent =data.calendario.titulo + "de"+data.usuario.nombre;
            //         document.getElementById("modalData").querySelector("#modalDateStart").textContent = "Fecha: " + data.calendario.fecha;
            //         document.getElementById("modalData").querySelector("#modalTime").textContent = "Hora: " +formatTime(data.calendario.hora) ;
            //         document.getElementById("modalData").querySelector("#modalDescription").textContent = "Descripción: " + data.calendario.nota;
            
            // // Mostrar el modal
            // $('#modalData').modal('show');
            //     } else {
            //         console.log('No se encontró el evento');
            //     }
            },
            error: function(xhr, status, error) {
                console.error("Error al realizar la consulta: " + error);
            }
        });
        
       
        },
    eventRender: function (info) {
      $(info.el).on('contextmenu', function (e) {
        e.preventDefault();
        let existingMenu = $('.context-menu');
        existingMenu.length && existingMenu.remove();
        let menu = $('<div>', { class: 'context-menu' }).html(`
          <ul>
            <li><i class="fas fa-edit"></i>Edit</li>
            <li><i class="fas fa-trash-alt"></i>Delete</li>
          </ul>
        `);

        const eventIndex = myEvents.findIndex(event => event.id === info.event.id);
    console.log(eventIndex)
        $('body').append(menu);
        menu.css({ top: e.pageY + 'px', left: e.pageX + 'px' });

        

       
        $(document).on('click', function () {
          menu.remove();
        });
      });

      // Mostrar la inicial del agente (solo la letra, en color blanco sobre un círculo del mismo color del evento)
      try {
        // eliminar badge si ya existe (evita duplicados al re-render)
        const prev = info.el.querySelector('.agent-initial-badge');
        if (prev) prev.remove();

        const idusu = info.event.extendedProps && info.event.extendedProps.idusu;
        let initial = '';
        if (idusu) {
          const user = users.find(u => u.id == idusu);
          if (user && user.nombre) initial = user.nombre.trim().charAt(0).toUpperCase();
        }

        if (initial) {
          const bg = info.event.backgroundColor || (idusu ? colorForId(idusu) : '#000');
          const badge = document.createElement('span');
          badge.className = 'agent-initial-badge';
          badge.textContent = initial;
          // Solo aplicamos el color de fondo dinámicamente; el resto lo controla la clase CSS
          badge.style.background = bg;

          // Insertar como hijo del evento para que la posición absoluta lo coloque a la derecha
          const contentEl = info.el.querySelector('.fc-content') || info.el;
          contentEl.appendChild(badge);
        }
      } catch (e) {
        console.error('Error al renderizar la inicial del agente', e);
      }
    },

    eventDrop: function (info) {
      let myEvents = JSON.parse(localStorage.getItem('events')) || [];
      const eventIndex = myEvents.findIndex(event => event.id === info.event.id);
      // Intentamos conservar el color actual del evento; caemos a azul si no hay información
      const bg = info.event.backgroundColor || (info.event.extendedProps && info.event.extendedProps.idusu ? colorForId(info.event.extendedProps.idusu) : '#0000FF');
      const br = info.event.borderColor || bg;
      const updatedEvent = {
        ...myEvents[eventIndex],
        id: info.event.id,
        title: info.event.title,
        start: moment(info.event.start).format('YYYY-MM-DD'),
        end: moment(info.event.end).format('YYYY-MM-DD'),
        backgroundColor: bg,
        borderColor: br,
      };

      myEvents.splice(eventIndex, 1, updatedEvent);
      localStorage.setItem('events', JSON.stringify(myEvents));
    },
  });
  
  
  
  
  
  
  
let startDate = '';
  calendar.on('select', function (info) {
    //  startDate = info.startStr;
      
    //   //esto se ejecuta al presionar en la fecha
    //   mySecondModal.show();
    //       $('#modal-title-2').html('Agregar evento en la fecha '+startDate);
    //       $('#submit-button-2').html('Agregar evento').removeClass('btn-primary').addClass('btn-success');

    //       $(close).on('click', function () {
    //         mySecondModal.hide();
    //       });
      

      
    // $('#start-date').val(info.startStr);
    // const endDate = moment(info.endStr, 'YYYY-MM-DD').subtract(1, 'day').format('YYYY-MM-DD');
    // $('#end-date').val(endDate);
    // if ($('#start-date').val() === endDate) {
    //   $('#end-date').val('');
    // }
  });

  calendar.render();

  $('#myForm').on('submit', function (event) {
    event.preventDefault();
    const formData = $(this).serialize();
    const title = $('#event-title').val();
    const startDate = $('#start-date').val();
    const time = $('#eventTime').val();
    const nota = $('#eventDescription').val();
    const endDateFormatted = moment(startDate, 'YYYY-MM-DD').add(1, 'day').format('YYYY-MM-DD');
    const eventId = uuidv4();
        
         let dataToSend = null
           if ($("#userSelect").length) {
            
            const additionalData = '&title=' + title + '&date=' + startDate + '&time=' + time + '&description=' + nota+ '&iduser=' +$("#userSelect").val();
             dataToSend  = formData + additionalData;
        }else{
            
         const additionalData = '&title=' + title + '&date=' + startDate + '&time=' + time + '&description=' + nota;
             dataToSend = formData + additionalData;
          
        }
    
    
            
                    $.ajax({
                        url: 'guardar-evento.php',
                        type: 'POST',
                        data: dataToSend,
                        dataType: 'json',
                        success: function (response) {
                            if (response.status === 'success') {
                                alert(response.message);
                                $("#dayModal").modal("hide");
                                generateCalendar();
                                
                                
                            } else {
                                alert(response.message);
                            }
                        },
                        error: function () {
                            alert('Error en la solicitud');
                        }
                    });
    
   

    myModal.hide();
    $('#myForm')[0].reset();
  });

  myModal._element.addEventListener('hide.bs.modal', function () {
    $(dangerAlert).hide();
    $('form')[0].reset();
  });
  
  
  
  
  
  
    $('#mySecondForm').on('submit', function (e) {
        e.preventDefault(); // Prevenir el envío del formulario
    const formData = $(this).serialize() 
    
       
    
    
          // Obtener los valores de los campos
        const title = $('#event-title-2').val(); // Obtener el valor del campo de texto
        const nota = $('#eventDescription-2').val(); // Obtener el valor del textarea
        const time = $('#eventTime-2').val(); // Obtener el valor del campo de hora    
         let dataToSend = null
           if ($("#userSelect").length) {
            
            const additionalData = '&title=' + title + '&date=' + startDate + '&time=' + time + '&description=' + nota+ '&iduser=' +$("#userSelect").val();
             dataToSend  = formData + additionalData;
        }else{
            
          const additionalData = '&title=' + title + '&date=' + startDate + '&time=' + time + '&description=' + nota+ '&iduser=' + <?php echo $userid; ?>;
              dataToSend = formData + additionalData;
    console.log(dataToSend);
          
        }
                $.ajax({
                url: 'guardar-evento.php',
                type: 'POST',
                data: dataToSend,
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        $("#dayModal").modal("hide");
                        generateCalendar(); // Actualizar el calendario despu谷s de agregar el evento
                    } else {
                        alert(response.message);
                    }
                },
                error: function () {
                    alert('Error en la solicitud');
                }
            });
             mySecondModal.hide();
            $('#mySecondForm')[0].reset();

    });
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
  
});

</script>

<script>
// ── ACCORDION TOGGLE ──────────────────────────────────────────────────────────
function rptToggleAccordion(id) {
    var el = document.getElementById(id);
    if (el) el.classList.toggle('is-open');
}

// ── HIGHCHARTS REPORT CHARTS ──────────────────────────────────────────────────
$(document).ready(function () {
    var chartDates        = <?php echo $rpt_datesJson; ?>;
    var chartCounts       = <?php echo $rpt_countsJson; ?>;
    var howContactSeries  = <?php echo $rpt_howContactPieJson; ?>;
    var howLongSeries     = <?php echo $rpt_howLongPieJson; ?>;

    var maxCierres = 0;
    chartCounts.forEach(function(c) { if (c > maxCierres) maxCierres = c; });
    var yAxisMax = Math.ceil(maxCierres * 1.15); if (yAxisMax < 5) yAxisMax = 5;
    var tickInterval = Math.ceil(yAxisMax / 5); if (tickInterval < 1) tickInterval = 1;

    Highcharts.chart('rpt_clientsChart', {
        chart: { type: 'line', backgroundColor: '#f8f9fa', borderRadius: 12, spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10 },
        title: { text: null },
        xAxis: { categories: chartDates, crosshair: true, labels: { rotation: -45, style: { fontSize: '11px' } }, lineColor: '#d9e2ec' },
        yAxis: { min: 0, max: yAxisMax, tickInterval: tickInterval, title: { text: 'Cierres' }, allowDecimals: false, gridLineColor: '#e2e8f0' },
        legend: { enabled: false },
        plotOptions: { line: { color: '#C5A028', marker: { enabled: true, radius: 4 }, dataLabels: { enabled: true, formatter: function() { return this.y > 0 ? this.y : ''; }, style: { fontSize: '10px', fontWeight: '600', textOutline: 'none' } } } },
        series: [{ name: 'Cierres', data: chartCounts }],
        tooltip: { backgroundColor: 'rgba(15,23,42,0.92)', style: { color: '#fff' }, borderWidth: 0, formatter: function() { return '<b>' + this.x + '</b><br/>Cierres: <b>' + this.y + '</b>'; } },
        credits: { enabled: false }
    });

    var pieOptions = {
        chart: { type: 'pie', backgroundColor: '#f8f9fa', borderRadius: 12, spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10 },
        title: { text: null },
        tooltip: { pointFormat: '<b>{point.y}</b> cierres ({point.percentage:.1f}%)' },
        accessibility: { point: { valueSuffix: '%' } },
        plotOptions: { pie: { innerSize: '48%', allowPointSelect: true, cursor: 'pointer', dataLabels: { enabled: true, format: '<b>{point.name}</b><br>{point.y}' }, showInLegend: true } },
        legend: { align: 'center', verticalAlign: 'bottom', itemStyle: { fontSize: '11px' } },
        credits: { enabled: false }
    };

    Highcharts.chart('rpt_clientsContactChart', Object.assign({}, pieOptions, {
        series: [{ name: 'Método de contacto', colorByPoint: true, data: howContactSeries }]
    }));
    Highcharts.chart('rpt_clientsHowLongChart', Object.assign({}, pieOptions, {
        series: [{ name: 'Desde cuándo nos conoce', colorByPoint: true, data: howLongSeries }]
    }));

    // ── Pre-Q Charts ──────────────────────────────────────────────────────────
    var preQChartDates    = <?php echo $rpt_preQDatesJson; ?>;
    var preQChartCounts   = <?php echo $rpt_preQCountsJson; ?>;
    var preQContactSeries = <?php echo $rpt_preQContactPieJson; ?>;
    var preQKnownUsSeries = <?php echo $rpt_preQKnownUsPieJson; ?>;

    var preQMaxLeads = 0; preQChartCounts.forEach(function(c) { if (c > preQMaxLeads) preQMaxLeads = c; });
    var preQYAxisMax = Math.ceil(preQMaxLeads * 1.15); if (preQYAxisMax < 5) preQYAxisMax = 5;
    var preQTickInterval = Math.ceil(preQYAxisMax / 5); if (preQTickInterval < 1) preQTickInterval = 1;

    Highcharts.chart('rpt_preQLeadsChart', {
        chart: { type: 'line', backgroundColor: '#f8f9fa', borderRadius: 12, spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10 },
        title: { text: null },
        xAxis: { categories: preQChartDates, crosshair: true, labels: { rotation: -45, style: { fontSize: '11px' } }, lineColor: '#d9e2ec' },
        yAxis: { min: 0, max: preQYAxisMax, tickInterval: preQTickInterval, title: { text: 'Registros' }, allowDecimals: false, gridLineColor: '#e2e8f0' },
        legend: { enabled: false },
        plotOptions: { line: { color: '#2563eb', marker: { enabled: true, radius: 4 }, dataLabels: { enabled: true, formatter: function() { return this.y > 0 ? this.y : ''; }, style: { fontSize: '10px', fontWeight: '600', textOutline: 'none' } } } },
        series: [{ name: 'Registros', data: preQChartCounts }],
        tooltip: { backgroundColor: 'rgba(15,23,42,0.92)', style: { color: '#fff' }, borderWidth: 0, formatter: function() { return '<b>' + this.x + '</b><br/>Total: <b>' + this.y + '</b>'; } },
        credits: { enabled: false }
    });

    var preQPieOptions = { chart: { type: 'pie', backgroundColor: '#f8f9fa', borderRadius: 12, spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10 }, title: { text: null }, colors: ['#16a34a','#2563eb','#f59e0b','#c026d3','#0284c7','#94a3b8'], tooltip: { pointFormat: '<b>{point.y}</b> registros ({point.percentage:.1f}%)' }, accessibility: { point: { valueSuffix: '%' } }, plotOptions: { pie: { innerSize: '48%', allowPointSelect: true, cursor: 'pointer', dataLabels: { enabled: true, format: '<b>{point.name}</b><br>{point.y} registros' }, showInLegend: true } }, legend: { align: 'center', verticalAlign: 'bottom', itemStyle: { fontSize: '11px' } }, credits: { enabled: false } };

    Highcharts.chart('rpt_preQContactMethodChart', Object.assign({}, preQPieOptions, {
        series: [{ name: '¿Dónde nos conocieron?', colorByPoint: true, data: preQContactSeries }]
    }));
    Highcharts.chart('rpt_preQKnownUsChart', Object.assign({}, preQPieOptions, {
        series: [{ name: 'Desde cuándo nos conoce', colorByPoint: true, data: preQKnownUsSeries }]
    }));

    // ── Post-Q Charts ─────────────────────────────────────────────────────────
    var postQChartDates    = <?php echo $rpt_postQDatesJson; ?>;
    var postQChartCounts   = <?php echo $rpt_postQCountsJson; ?>;
    var postQContactSeries = <?php echo $rpt_postQContactPieJson; ?>;
    var postQKnownUsSeries = <?php echo $rpt_postQKnownUsPieJson; ?>;

    var postQMaxLeads = 0; postQChartCounts.forEach(function(c) { if (c > postQMaxLeads) postQMaxLeads = c; });
    var postQYAxisMax = Math.ceil(postQMaxLeads * 1.15); if (postQYAxisMax < 5) postQYAxisMax = 5;
    var postQTickInterval = Math.ceil(postQYAxisMax / 5); if (postQTickInterval < 1) postQTickInterval = 1;

    Highcharts.chart('rpt_postQLeadsChart', {
        chart: { type: 'line', backgroundColor: '#f8f9fa', borderRadius: 12, spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10 },
        title: { text: null },
        xAxis: { categories: postQChartDates, crosshair: true, labels: { rotation: -45, style: { fontSize: '11px' } }, lineColor: '#d9e2ec' },
        yAxis: { min: 0, max: postQYAxisMax, tickInterval: postQTickInterval, title: { text: 'Sesiones' }, allowDecimals: false, gridLineColor: '#e2e8f0' },
        legend: { enabled: false },
        plotOptions: { line: { color: '#C5A028', marker: { enabled: true, radius: 4 }, dataLabels: { enabled: true, formatter: function() { return this.y > 0 ? this.y : ''; }, style: { fontSize: '10px', fontWeight: '600', textOutline: 'none' } } } },
        series: [{ name: 'Sesiones', data: postQChartCounts }],
        tooltip: { backgroundColor: 'rgba(15,23,42,0.92)', style: { color: '#fff' }, borderWidth: 0, formatter: function() { return '<b>' + this.x + '</b><br/>Sesiones: <b>' + this.y + '</b>'; } },
        credits: { enabled: false }
    });

    var postQPieOptions = { chart: { type: 'pie', backgroundColor: '#f8f9fa', borderRadius: 12, spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10 }, title: { text: null }, colors: ['#16a34a','#2563eb','#f59e0b','#c026d3','#0284c7','#94a3b8'], tooltip: { pointFormat: '<b>{point.y}</b> sesiones ({point.percentage:.1f}%)' }, accessibility: { point: { valueSuffix: '%' } }, plotOptions: { pie: { innerSize: '48%', allowPointSelect: true, cursor: 'pointer', dataLabels: { enabled: true, format: '<b>{point.name}</b><br>{point.y} sesiones' }, showInLegend: true } }, legend: { align: 'center', verticalAlign: 'bottom', itemStyle: { fontSize: '11px' } }, credits: { enabled: false } };

    Highcharts.chart('rpt_postQContactMethodChart', Object.assign({}, postQPieOptions, {
        series: [{ name: 'Método de contacto', colorByPoint: true, data: postQContactSeries }]
    }));
    Highcharts.chart('rpt_postQKnownUsChart', Object.assign({}, postQPieOptions, {
        series: [{ name: 'Desde cuándo nos conoce', colorByPoint: true, data: postQKnownUsSeries }]
    }));
});
</script>

<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.idx-btn-complete');
    if (!btn) return;
    var id = parseInt(btn.dataset.interactionId, 10);
    if (!id) return;
    btn.disabled = true;
    btn.textContent = '…';
    fetch('complete_interaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(function (r) {
        if (!r.ok) {
            return r.text().then(function (t) { throw new Error('HTTP ' + r.status + ': ' + t.substring(0, 200)); });
        }
        return r.json();
    })
    .then(function (data) {
        if (data.ok) {
            var item = btn.closest('.appointment-item');
            if (item) {
                item.style.opacity = '0.4';
                item.style.pointerEvents = 'none';
                btn.remove();
                var badge = document.createElement('span');
                badge.className = 'idx-completed-badge';
                badge.textContent = '✓ Completada';
                var meta = item.querySelector('.appointment-meta:last-child');
                if (meta) meta.appendChild(badge);
            }
        } else {
            btn.disabled = false;
            btn.textContent = 'Completar';
            alert('No se pudo marcar como completada.');
        }
    })
    .catch(function (err) {
        btn.disabled = false;
        btn.textContent = 'Completar';
        alert('Error de red: ' + (err && err.message ? err.message : err));
    });
});
</script>


</body>
</html>
