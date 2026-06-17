<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tipoUsuario = $_SESSION['tipo_usuario'] ?? 0;

include 'menu.php';
include 'conn.php';

// ========================================
// FUNCIONES AUXILIARES
// ========================================

function normalizeOrigen($orig) {
    if (!$orig) return '';
    if (preg_match('/^c(\d+)\./i', $orig, $m)) return 'c' . $m[1];
    return trim($orig);
}

function getDisplayOrigenFromLead($lead) {
    $cname = trim($lead['campaign_name'] ?? '');
    if (preg_match('/^(c(\d+)\.|e(\d+)-)/i', $cname, $matches)) {
        if (!empty($matches[2])) {
            return 'C' . $matches[2];
        } elseif (!empty($matches[3])) {
            return 'E' . $matches[3];
        }
    }
    if (!empty($cname) && $cname !== 'N/A') {
        return $cname;
    }
    return trim($lead['desde_publicidad'] ?? 'Sin datos');
}

function getMedioFromLead($lead) {
    $platform = trim($lead['platform'] ?? '');
    if ($platform !== '') return $platform;
    $campaign = trim($lead['campaign_name'] ?? '');
    if ($campaign !== '' && $campaign !== 'N/A') return $campaign;
    return 'Sin datos';
}

// ========================================
// LEER FILTROS
// ========================================

$filterPlataforma = isset($_GET['filter_plataforma']) ? trim($_GET['filter_plataforma']) : '';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$platformLabel = 'Todo';
if ($filterPlataforma === 'campania') {
    $platformLabel = 'Campañas digitales';
} elseif ($filterPlataforma === 'organico') {
    $platformLabel = 'Orgánico';
} elseif ($filterPlataforma === 'wp') {
    $platformLabel = 'Wedding Planner';
}

// ========================================
// OBTENER MAPA DE TABLA_ORIGEN => TIPO
// ========================================
 
$tablaTipoMap = [];
$sqlTablasMap = "SELECT nombre, tipo FROM tablas_leads";
$resultTablasMap = $conn->query($sqlTablasMap);
if ($resultTablasMap && $resultTablasMap->num_rows > 0) {
    while ($row = $resultTablasMap->fetch_assoc()) {
        $tablaTipoMap[$row['nombre']] = intval($row['tipo']);
    }
}

// ========================================
// 1. OBTENER LEADS PRE-CALIFICADOS
// ========================================

$tablasPreLeads = [];
$sqlTablas = "SELECT nombre FROM tablas_leads ORDER BY nombre";
$resultTablas = $conn->query($sqlTablas);
if ($resultTablas && $resultTablas->num_rows > 0) {
    while ($row = $resultTablas->fetch_assoc()) {
        $tablasPreLeads[] = $row['nombre'];
    }
}

$allPreLeads = [];
foreach ($tablasPreLeads as $tableName) {
    $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($checkTable && $checkTable->num_rows > 0) {
        // Verificar qué columnas existen en la tabla
        $columns = [];
        $columnsResult = $conn->query("SHOW COLUMNS FROM `$tableName`");
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        
        // Construir la consulta según las columnas disponibles (igual que consulta_leads.php)
        $sqlLeads = "SELECT *, '$tableName' as tabla_origen FROM `$tableName` WHERE " .
            (in_array('descartado', $columns) ? "descartado = 0 OR descartado IS NULL" : "1=1") . " ORDER BY created_time DESC";
        
        $result = $conn->query($sqlLeads);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $allPreLeads[] = $row;
            }
        }
    }
}

// Aplicar filtros de plataforma y fecha a PRE-Q
$filteredPreLeads = [];
foreach ($allPreLeads as $lead) {
    // Filtro de plataforma
    if ($filterPlataforma !== '') {
        $tabla = $lead['tabla_origen'] ?? '';
        $tipo = $tablaTipoMap[$tabla] ?? -1;
        
        if ($filterPlataforma === 'organico' && $tipo !== 0) continue;
        if ($filterPlataforma === 'campania' && $tipo !== 1) continue;
        if ($filterPlataforma === 'wp' && $tipo !== 2) continue;
    }
    
    // Filtro de fecha
    if ($startDate !== '' || $endDate !== '') {
        $ct = $lead['created_time'] ?? '';
        if (empty($ct)) continue;
        $ts = strtotime($ct);
        if ($ts === false) continue;
        $d = date('Y-m-d', $ts);
        
        if ($startDate !== '') {
            $sd = date('Y-m-d', strtotime($startDate));
            if ($d < $sd) continue;
        }
        if ($endDate !== '') {
            $ed = date('Y-m-d', strtotime($endDate));
            if ($d > $ed) continue;
        }
    }
    
    $filteredPreLeads[] = $lead;
}

// ========================================
// 2. OBTENER LEADS POST-CALIFICADOS
// ========================================

$appointmentIds = [];
$appointmentQuery = $conn->query("SELECT DISTINCT idclie FROM calendario");
if ($appointmentQuery && $appointmentQuery->num_rows > 0) {
    while ($row = $appointmentQuery->fetch_assoc()) {
        $appointmentIds[] = intval($row['idclie']);
    }
}

$appointmentsByClient = [];
if (!empty($appointmentIds)) {
    $idsList = implode(',', array_map('intval', $appointmentIds));
    $apptRes = $conn->query("SELECT * FROM calendario WHERE idclie IN ($idsList)");
    if ($apptRes && $apptRes->num_rows > 0) {
        while ($ar = $apptRes->fetch_assoc()) {
            $idclie = intval($ar['idclie']);
            if ($idclie <= 0) continue;
            
            if (!isset($appointmentsByClient[$idclie])) {
                $appointmentsByClient[$idclie] = $ar;
            } else {
                $prev = $appointmentsByClient[$idclie];
                $replace = false;
                if (!empty($ar['fecha']) && !empty($prev['fecha'])) {
                    $t1 = strtotime($ar['fecha'] . ' ' . ($ar['hora'] ?? '')) ?: 0;
                    $t2 = strtotime($prev['fecha'] . ' ' . ($prev['hora'] ?? '')) ?: 0;
                    if ($t1 > $t2) $replace = true;
                }
                if ($replace) $appointmentsByClient[$idclie] = $ar;
            }
        }
    }
}

$allPostLeads = [];
if (!empty($appointmentIds)) {
    $idsList = implode(',', array_map('intval', $appointmentIds));
    $sql = "SELECT * FROM contact_form WHERE id IN ($idsList)";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($cf = $result->fetch_assoc()) {
            $merged = $cf;
            $merged['tabla_origen'] = $cf['tabla_origen'] ?? '';
            $merged['original_lead_id'] = $cf['original_lead_id'] ?? null;
            
            // Obtener datos del lead original
            $formName = $cf['tabla_origen'] ?? '';
            $origId = intval($cf['original_lead_id'] ?? 0);
            if (!empty($formName) && $origId > 0) {
                $escapedForm = $conn->real_escape_string($formName);
                $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
                    if ($leadRes && $leadRes->num_rows > 0) {
                        $leadRow = $leadRes->fetch_assoc();
                        foreach ($leadRow as $k => $v) {
                            if (!isset($merged[$k]) || $merged[$k] === '' || $merged[$k] === null) {
                                $merged[$k] = $v;
                            }
                        }
                    }
                }
            }
            
            // Añadir estatus desde la cita
            $cid = intval($cf['id']);
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
            
            $allPostLeads[] = $merged;
        }
    }
}

// Aplicar filtros a POST-Q
$filteredPostLeads = [];
foreach ($allPostLeads as $lead) {
    // Filtro de plataforma
    if ($filterPlataforma !== '') {
        $tabla = $lead['tabla_origen'] ?? '';
        $tipo = $tablaTipoMap[$tabla] ?? -1;
        
        if ($filterPlataforma === 'organico' && $tipo !== 0) continue;
        if ($filterPlataforma === 'campania' && $tipo !== 1) continue;
        if ($filterPlataforma === 'wp' && $tipo !== 2) continue;
    }
    
    // Filtro de fecha
    if ($startDate !== '' || $endDate !== '') {
        $ct = $lead['submission_date'] ?? $lead['created_time'] ?? '';
        if (empty($ct)) continue;
        $ts = strtotime($ct);
        if ($ts === false) continue;
        $d = date('Y-m-d', $ts);
        
        if ($startDate !== '') {
            $sd = date('Y-m-d', strtotime($startDate));
            if ($d < $sd) continue;
        }
        if ($endDate !== '') {
            $ed = date('Y-m-d', strtotime($endDate));
            if ($d > $ed) continue;
        }
    }
    
    $filteredPostLeads[] = $lead;
}

// ========================================
// 3. OBTENER CLIENTES
// ========================================

$allClientes = [];
$sql = "SELECT * FROM contact_form WHERE cliente = 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($cf = $result->fetch_assoc()) {
        $merged = $cf;
        $merged['tabla_origen'] = $cf['tabla_origen'] ?? '';
        $merged['original_lead_id'] = $cf['original_lead_id'] ?? null;
        
        // Obtener datos del lead original
        $formName = $cf['tabla_origen'] ?? '';
        $origId = intval($cf['original_lead_id'] ?? 0);
        if (!empty($formName) && $origId > 0) {
            $escapedForm = $conn->real_escape_string($formName);
            $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
                if ($leadRes && $leadRes->num_rows > 0) {
                    $leadRow = $leadRes->fetch_assoc();
                    foreach ($leadRow as $k => $v) {
                        if (!isset($merged[$k]) || $merged[$k] === '' || $merged[$k] === null) {
                            $merged[$k] = $v;
                        }
                    }
                }
            }
        }
        
        $allClientes[] = $merged;
    }
}

// Aplicar filtros a CLIENTES
$filteredClientes = [];
foreach ($allClientes as $lead) {
    // Filtro de plataforma
    if ($filterPlataforma !== '') {
        $tabla = $lead['tabla_origen'] ?? '';
        $tipo = $tablaTipoMap[$tabla] ?? -1;
        
        if ($filterPlataforma === 'organico' && $tipo !== 0) continue;
        if ($filterPlataforma === 'campania' && $tipo !== 1) continue;
        if ($filterPlataforma === 'wp' && $tipo !== 2) continue;
    }
    
    // Filtro de fecha
    if ($startDate !== '' || $endDate !== '') {
        $ct = $lead['fecha_cambio_cliente'] ?? $lead['submission_date'] ?? '';
        if (empty($ct)) continue;
        $ts = strtotime($ct);
        if ($ts === false) continue;
        $d = date('Y-m-d', $ts);
        
        if ($startDate !== '') {
            $sd = date('Y-m-d', strtotime($startDate));
            if ($d < $sd) continue;
        }
        if ($endDate !== '') {
            $ed = date('Y-m-d', strtotime($endDate));
            if ($d > $ed) continue;
        }
    }
    
    $filteredClientes[] = $lead;
}

// ========================================
// 4. CALCULAR MÉTRICAS GLOBALES
// ========================================

$totalRegistros = count($filteredPreLeads);
$totalAgendados = count($filteredPostLeads);
$totalAtendidos = 0;
$totalClientes = count($filteredClientes);

foreach ($filteredPostLeads as $lead) {
    $st = mb_strtolower(trim($lead['estatus'] ?? ''), 'UTF-8');
    if ($st === 'atendido' || $st === '1') {
        $totalAtendidos++;
    }
}

// HIT RATES
$tasaBookingPreQ = $totalRegistros > 0 ? round(($totalAgendados / $totalRegistros) * 100, 2) : 0;
$tasaAsistenciaPreQ = $totalRegistros > 0 ? round(($totalAtendidos / $totalRegistros) * 100, 2) : 0;
$tasaAsistenciaPostQ = $totalAgendados > 0 ? round(($totalAtendidos / $totalAgendados) * 100, 2) : 0;
$tasaConversionClientes = $totalAtendidos > 0 ? round(($totalClientes / $totalAtendidos) * 100, 2) : 0;

// ========================================
// 5. CONTEOS POR MEDIO Y ORIGEN
// ========================================

$conteosPorMedio = [];
$conteosPorOrigen = [];
$detallesPorMedio = [];
$detallesPorOrigen = [];
$origenLabels = [];
$sinDatosMedioPre = [];
$sinDatosMedioPost = [];
$sinDatosMedioClientes = [];

// Procesar PRE-Q
foreach ($filteredPreLeads as $lead) {
    $medio = getMedioFromLead($lead);
    if ($medio === 'Sin datos') {
        if (count($sinDatosMedioPre) < 50) {
            $sinDatosMedioPre[] = [
                'id' => $lead['id'] ?? null,
                'tabla' => $lead['tabla_origen'] ?? null,
                'platform' => $lead['platform'] ?? null,
                'campaign_name' => $lead['campaign_name'] ?? null,
                'desde_publicidad' => $lead['desde_publicidad'] ?? null,
                'created_time' => $lead['created_time'] ?? null
            ];
        }
    }
    $origenDisplay = getDisplayOrigenFromLead($lead);
    $origenKey = normalizeOrigen($origenDisplay);
    if ($origenKey === '') $origenKey = 'Sin datos';
    if (!isset($origenLabels[$origenKey])) $origenLabels[$origenKey] = $origenDisplay;
    
    // Conteo por medio
    if (!isset($conteosPorMedio[$medio])) {
        $conteosPorMedio[$medio] = 0;
        $detallesPorMedio[$medio] = [
            'total' => 0,
            'agendados' => 0,
            'atendidos' => 0,
            'clientes' => 0
        ];
    }
    $conteosPorMedio[$medio]++;
    $detallesPorMedio[$medio]['total']++;
    
    // Conteo por origen
    if (!isset($conteosPorOrigen[$origenKey])) {
        $conteosPorOrigen[$origenKey] = 0;
        $detallesPorOrigen[$origenKey] = [
            'total' => 0,
            'agendados' => 0,
            'atendidos' => 0,
            'clientes' => 0
        ];
    }
    $conteosPorOrigen[$origenKey]++;
    $detallesPorOrigen[$origenKey]['total']++;
}

// Procesar POST-Q
foreach ($filteredPostLeads as $lead) {
    $medio = getMedioFromLead($lead);
    if ($medio === 'Sin datos') {
        if (count($sinDatosMedioPost) < 50) {
            $sinDatosMedioPost[] = [
                'id' => $lead['id'] ?? null,
                'tabla' => $lead['tabla_origen'] ?? null,
                'platform' => $lead['platform'] ?? null,
                'campaign_name' => $lead['campaign_name'] ?? null,
                'desde_publicidad' => $lead['desde_publicidad'] ?? null,
                'submission_date' => $lead['submission_date'] ?? null,
                'created_time' => $lead['created_time'] ?? null,
                'estatus' => $lead['estatus'] ?? null
            ];
        }
    }
    $origenDisplay = getDisplayOrigenFromLead($lead);
    $origenKey = normalizeOrigen($origenDisplay);
    if ($origenKey === '') $origenKey = 'Sin datos';
    if (!isset($origenLabels[$origenKey])) $origenLabels[$origenKey] = $origenDisplay;
    $st = mb_strtolower(trim($lead['estatus'] ?? ''), 'UTF-8');
    
    // Actualizar detalles por medio
    if (!isset($detallesPorMedio[$medio])) {
        $detallesPorMedio[$medio] = [
            'total' => 0,
            'agendados' => 0,
            'atendidos' => 0,
            'clientes' => 0
        ];
    }
    $detallesPorMedio[$medio]['agendados']++;
    
    if ($st === 'atendido' || $st === '1') {
        $detallesPorMedio[$medio]['atendidos']++;
    }
    
    // Actualizar detalles por origen
    if (!isset($detallesPorOrigen[$origenKey])) {
        $detallesPorOrigen[$origenKey] = [
            'total' => 0,
            'agendados' => 0,
            'atendidos' => 0,
            'clientes' => 0
        ];
    }
    $detallesPorOrigen[$origenKey]['agendados']++;
    
    if ($st === 'atendido' || $st === '1') {
        $detallesPorOrigen[$origenKey]['atendidos']++;
    }
}

// Procesar CLIENTES
foreach ($filteredClientes as $lead) {
    $medio = getMedioFromLead($lead);
    if ($medio === 'Sin datos') {
        if (count($sinDatosMedioClientes) < 50) {
            $sinDatosMedioClientes[] = [
                'id' => $lead['id'] ?? null,
                'tabla' => $lead['tabla_origen'] ?? null,
                'platform' => $lead['platform'] ?? null,
                'campaign_name' => $lead['campaign_name'] ?? null,
                'desde_publicidad' => $lead['desde_publicidad'] ?? null,
                'fecha_cambio_cliente' => $lead['fecha_cambio_cliente'] ?? null
            ];
        }
    }
    $origenDisplay = getDisplayOrigenFromLead($lead);
    $origenKey = normalizeOrigen($origenDisplay);
    if ($origenKey === '') $origenKey = 'Sin datos';
    if (!isset($origenLabels[$origenKey])) $origenLabels[$origenKey] = $origenDisplay;
    
    if (isset($detallesPorMedio[$medio])) {
        $detallesPorMedio[$medio]['clientes']++;
    }
    
    if (!isset($detallesPorOrigen[$origenKey])) {
        $detallesPorOrigen[$origenKey] = [
            'total' => 0,
            'agendados' => 0,
            'atendidos' => 0,
            'clientes' => 0
        ];
    }
    $detallesPorOrigen[$origenKey]['clientes']++;
}

// Ordenar por total descendente
arsort($conteosPorMedio);
arsort($conteosPorOrigen);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Dashboard - Performance y ROAS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
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

        .reportes-dashboard .flex { display: flex; }
        .reportes-dashboard .flex-col { flex-direction: column; }
        .reportes-dashboard .items-center { align-items: center; }
        .reportes-dashboard .items-start { align-items: flex-start; }
        .reportes-dashboard .justify-between { justify-content: space-between; }
        .reportes-dashboard .gap-2 { gap: 0.5rem; }
        .reportes-dashboard .gap-3 { gap: 0.75rem; }
        .reportes-dashboard .gap-4 { gap: 1rem; }
        .reportes-dashboard .gap-5 { gap: 1.25rem; }
        .reportes-dashboard .gap-6 { gap: 1.5rem; }
        .reportes-dashboard .space-y-3 > * + * { margin-top: 0.75rem; }
        .reportes-dashboard .space-y-4 > * + * { margin-top: 1rem; }
        .reportes-dashboard .grid { display: grid; }
        .reportes-dashboard .grid-cols-1 { grid-template-columns: 1fr; }
        .reportes-dashboard .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
        .reportes-dashboard .grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
        .reportes-dashboard .grid-cols-12 { grid-template-columns: repeat(12, 1fr); }
        .reportes-dashboard .rounded-2xl { border-radius: 1rem; }
        .reportes-dashboard .rounded-xl { border-radius: 0.75rem; }
        .reportes-dashboard .rounded-full { border-radius: 9999px; }
        .reportes-dashboard .h-2\.5 { height: 0.625rem; }
        .reportes-dashboard .w-2\.5 { width: 0.625rem; }
        .reportes-dashboard .min-w-0 { min-width: 0; }
        .reportes-dashboard .shrink-0 { flex-shrink: 0; }
        .reportes-dashboard .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .reportes-dashboard .p-3 { padding: 0.75rem; }
        .reportes-dashboard .p-4 { padding: 1rem; }
        .reportes-dashboard .p-5 { padding: 1.25rem; }
        .reportes-dashboard .p-6 { padding: 1.5rem; }
        .reportes-dashboard .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .reportes-dashboard .px-5 { padding-left: 1.25rem; padding-right: 1.25rem; }
        .reportes-dashboard .px-2\.5 { padding-left: 0.625rem; padding-right: 0.625rem; }
        .reportes-dashboard .py-1 { padding-top: 0.25rem; padding-bottom: 0.25rem; }
        .reportes-dashboard .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .reportes-dashboard .py-4 { padding-top: 1rem; padding-bottom: 1rem; }
        .reportes-dashboard .py-6 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
        .reportes-dashboard .mt-1 { margin-top: 0.25rem; }
        .reportes-dashboard .mt-2 { margin-top: 0.5rem; }
        .reportes-dashboard .mt-3 { margin-top: 0.75rem; }
        .reportes-dashboard .mt-4 { margin-top: 1rem; }
        .reportes-dashboard .mt-6 { margin-top: 1.5rem; }
        .reportes-dashboard .mt-0\.5 { margin-top: 0.125rem; }
        .reportes-dashboard .mb-2 { margin-bottom: 0.5rem; }
        .reportes-dashboard .text-xs { font-size: 0.75rem; line-height: 1rem; }
        .reportes-dashboard .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
        .reportes-dashboard .text-base { font-size: 1rem; line-height: 1.5rem; }
        .reportes-dashboard .text-lg { font-size: 1.125rem; line-height: 1.75rem; }
        .reportes-dashboard .text-2xl { font-size: 1.5rem; line-height: 2rem; }
        .reportes-dashboard .font-semibold { font-weight: 600; }
        .reportes-dashboard .font-bold { font-weight: 700; }
        .reportes-dashboard .text-right { text-align: right; }
        .reportes-dashboard .uppercase { text-transform: uppercase; }
        .reportes-dashboard .tracking-wide { letter-spacing: 0.025em; }
        .reportes-dashboard .tracking-tight { letter-spacing: -0.025em; }
        .reportes-dashboard .text-slate-500 { color: var(--slate-500); }
        .reportes-dashboard .text-slate-600 { color: var(--slate-600); }
        .reportes-dashboard .text-slate-700 { color: var(--slate-700); }
        .reportes-dashboard .text-slate-900 { color: var(--slate-900); }
        .reportes-dashboard .text-emerald-700 { color: var(--emerald-700); }
        .reportes-dashboard .text-blue-700 { color: var(--blue-700); }
        .reportes-dashboard .text-fuchsia-700 { color: var(--fuchsia-700); }
        .reportes-dashboard .text-amber-700 { color: var(--amber-700); }
        .reportes-dashboard .text-sky-700 { color: var(--sky-700); }
        .reportes-dashboard .bg-white { background-color: var(--white); }
        .reportes-dashboard .bg-slate-50 { background-color: var(--slate-50); }
        .reportes-dashboard .bg-blue-50 { background-color: var(--blue-50); }
        .reportes-dashboard .bg-emerald-50 { background-color: var(--emerald-50); }
        .reportes-dashboard .bg-amber-50 { background-color: var(--amber-50); }
        .reportes-dashboard .bg-fuchsia-50 { background-color: var(--fuchsia-50); }
        .reportes-dashboard .bg-sky-50 { background-color: var(--sky-50); }
        .reportes-dashboard .bg-gradient-emerald { background: linear-gradient(to right, var(--emerald-600), var(--sky-600)); }
        .reportes-dashboard .bg-gradient-blue { background: linear-gradient(to right, var(--blue-600), var(--sky-600)); }
        .reportes-dashboard .bg-gradient-sky { background: linear-gradient(to right, var(--sky-700), var(--blue-600)); }
        .reportes-dashboard .bg-gradient-fuchsia { background: linear-gradient(to right, var(--fuchsia-600), var(--orange-400)); }
        .reportes-dashboard .bg-gradient-green { background: linear-gradient(to right, var(--emerald-700), var(--lime-600)); }
        .reportes-dashboard .shadow-sm { box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .reportes-dashboard .ring-1 { box-shadow: 0 0 0 1px rgba(0,0,0,0.05); }
        .reportes-dashboard .ring-black\/5 { box-shadow: 0 0 0 1px rgba(0,0,0,0.05); }
        .reportes-dashboard .ring-black\/10 { box-shadow: 0 0 0 1px rgba(0,0,0,0.1); }
        .reportes-dashboard .border-b { border-bottom: 1px solid var(--slate-200); }
        .reportes-dashboard .overflow-x-auto { overflow-x: auto; }
        .reportes-dashboard .custom-scroll { scrollbar-width: thin; scrollbar-color: var(--slate-300) transparent; }
        .reportes-dashboard .custom-scroll::-webkit-scrollbar { height: 6px; }
        .reportes-dashboard .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .reportes-dashboard .custom-scroll::-webkit-scrollbar-thumb { background-color: var(--slate-300); border-radius: 20px; }

        .reportes-dashboard .dashboard-header {
            position: sticky;
            top: 0;
            z-index: 30;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            background-color: rgba(255,255,255,0.8);
            backdrop-filter: blur(8px);
        }

        .reportes-dashboard .card {
            border-radius: 1rem;
            background-color: var(--white);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .reportes-dashboard .stat-card {
            border-radius: 1rem;
            padding: 1rem;
            border: 1px solid rgba(0,0,0,0.05);
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

        .reportes-dashboard .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 9999px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            border: 1px solid rgba(0,0,0,0.1);
            background-color: var(--white);
            color: var(--slate-900);
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
        }

        .reportes-dashboard .btn:hover { background-color: var(--slate-50); }
        .reportes-dashboard .btn-primary { background-color: var(--slate-50); }

        .reportes-dashboard .select-wrapper { position: relative; }
        .reportes-dashboard select.custom-select,
        .reportes-dashboard input.custom-input {
            appearance: none;
            border-radius: 9999px;
            background-color: var(--slate-50);
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--slate-900);
            border: 1px solid rgba(0,0,0,0.1);
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
            border: 1px solid rgba(0,0,0,0.1);
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

        .reportes-dashboard .tabular-nums { font-variant-numeric: tabular-nums; }
        .reportes-dashboard .space-y-3 > * + * { margin-top: 0.75rem; }
        .reportes-dashboard .divide-y > * + * { border-top: 1px solid rgba(0,0,0,0.05); }

        .reportes-dashboard .keyword-card {
            border-radius: 1rem;
            background-color: var(--slate-50);
            padding: 1rem;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .reportes-dashboard .star-card {
            border-radius: 1rem;
            background-color: var(--slate-50);
            padding: 1rem;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .reportes-dashboard .chart-container {
            height: 20rem;
            width: 100%;
        }

        @media (min-width: 640px) {
            .reportes-dashboard .sm\:flex-row { flex-direction: row; }
            .reportes-dashboard .sm\:items-center { align-items: center; }
            .reportes-dashboard .sm\:justify-between { justify-content: space-between; }
        }

        @media (min-width: 768px) {
            .reportes-dashboard .md\:grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
        }

        @media (min-width: 1024px) {
            .reportes-dashboard .lg\:grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
        }

        @media (min-width: 1280px) {
            .reportes-dashboard .xl\:grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
            .reportes-dashboard .xl\:grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
            .reportes-dashboard .xl\:col-span-3 { grid-column: span 3 / span 3; }
            .reportes-dashboard .xl\:col-span-1 { grid-column: span 1 / span 1; }
        }
    </style>
</head>
<body>
<?php
$platformCards = [];
foreach ($detallesPorOrigen as $origenKey => $detalle) {
    $origenLabel = $origenLabels[$origenKey] ?? $origenKey;
    $platformCards[] = [
        'platform' => $origenLabel,
        'contactos' => (int) $detalle['total'],
        'A' => (int) $detalle['agendados'],
        'AA' => (int) $detalle['atendidos'],
        'AAA' => (int) $detalle['clientes'],
        'ganados' => (int) $detalle['clientes'],
        'cotizado' => 0,
        'vendido' => 0,
        'inversion' => 0,
        'campaigns' => []
    ];
}

$keywordStars = [];
foreach ($conteosPorMedio as $medio => $count) {
    $keywordStars[] = [
        'kw' => $medio,
        'note' => number_format($count) . ' leads'
    ];
    if (count($keywordStars) >= 4) break;
}
?>

<div class="reportes-dashboard">
   

    <div class="">
        <div class="card">
            <div class="flex sm:flex-row flex-col sm:justify-between sm:items-center gap-3 px-5 py-4 border-b">
                <div>
                    <div class="font-semibold text-slate-900 text-sm">Filtros</div>
                    <div class="mt-1 text-slate-500 text-xs">Rango de fechas y plataforma</div>
                </div>
                <form method="GET" action="reportes.php" class="flex flex-wrap items-center gap-2">
                    <div class="select-wrapper">
                        <input type="date" name="start_date" class="custom-input" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    <div class="select-wrapper">
                        <input type="date" name="end_date" class="custom-input" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    <div class="select-wrapper" style="min-width: 200px;">
                        <select name="filter_plataforma" class="custom-select">
                            <option value="" <?php echo $filterPlataforma === '' ? 'selected' : ''; ?>>Todas</option>
                            <option value="campania" <?php echo $filterPlataforma === 'campania' ? 'selected' : ''; ?>>Campañas digitales</option>
                            <option value="organico" <?php echo $filterPlataforma === 'organico' ? 'selected' : ''; ?>>Orgánico</option>
                            <option value="wp" <?php echo $filterPlataforma === 'wp' ? 'selected' : ''; ?>>Wedding Planner</option>
                        </select>
                        <i class="select-arrow fas fa-chevron-down"></i>
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="reportes.php" class="btn">Limpiar</a>
                </form>
            </div>
        </div>

        <div class="gap-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 mt-6">
            <div class="card">
                <div class="p-4">
                    <div class="bg-blue-50 stat-card">
                        <div class="font-semibold text-blue-700 text-xs uppercase tracking-wide">Registros PRE-Q</div>
                        <div class="mt-1 font-semibold text-slate-900 text-2xl tracking-tight"><?php echo number_format($totalRegistros); ?></div>
                        <div class="flex justify-between items-center gap-3 mt-2 text-slate-600 text-xs">
                            <span class="truncate">Plataforma</span>
                            <span class="font-semibold text-slate-900 shrink-0"><?php echo htmlspecialchars($platformLabel); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="p-4">
                    <div class="bg-emerald-50 stat-card">
                        <div class="font-semibold text-emerald-800 text-xs uppercase tracking-wide">Agendados</div>
                        <div class="mt-1 font-semibold text-slate-900 text-2xl tracking-tight"><?php echo number_format($totalAgendados); ?></div>
                        <div class="flex justify-between items-center gap-3 mt-2 text-slate-600 text-xs">
                            <span class="truncate">Tasa booking</span>
                            <span class="font-semibold text-slate-900 shrink-0"><?php echo $tasaBookingPreQ; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="p-4">
                    <div class="bg-amber-50 stat-card">
                        <div class="font-semibold text-amber-700 text-xs uppercase tracking-wide">Atendidos</div>
                        <div class="mt-1 font-semibold text-slate-900 text-2xl tracking-tight"><?php echo number_format($totalAtendidos); ?></div>
                        <div class="flex justify-between items-center gap-3 mt-2 text-slate-600 text-xs">
                            <span class="truncate">Asistencia Post-Q</span>
                            <span class="font-semibold text-slate-900 shrink-0"><?php echo $tasaAsistenciaPostQ; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card" style="margin-bottom: 20px;">
                <div class="p-4">
                    <div class="bg-fuchsia-50 stat-card">
                        <div class="font-semibold text-fuchsia-700 text-xs uppercase tracking-wide">Clientes</div>
                        <div class="mt-1 font-semibold text-slate-900 text-2xl tracking-tight"><?php echo number_format($totalClientes); ?></div>
                        <div class="flex justify-between items-center gap-3 mt-2 text-slate-600 text-xs">
                            <span class="truncate">Conversión</span>
                            <span class="font-semibold text-slate-900 shrink-0"><?php echo $tasaConversionClientes; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
 <div class="card py-4" style="margin-top: 20px;">
                <div class="px-5 py-4 border-b">
                    <div class="font-semibold text-slate-900 text-sm">Hit Rates</div>
                    <div class="mt-1 text-slate-500 text-xs">Resumen de conversiones</div>
                </div>
                <div class="gap-3 grid grid-cols-4 p-5">
                    <div class="star-card">
                        <div class="font-semibold text-slate-600 text-xs">Booking Pre-Q</div>
                        <div class="mt-1 font-semibold text-slate-900 text-sm"><?php echo $tasaBookingPreQ; ?>%</div>
                    </div>
                    <div class="star-card">
                        <div class="font-semibold text-slate-600 text-xs">Asistencia Pre-Q</div>
                        <div class="mt-1 font-semibold text-slate-900 text-sm"><?php echo $tasaAsistenciaPreQ; ?>%</div>
                    </div>
                    <div class="star-card">
                        <div class="font-semibold text-slate-600 text-xs">Asistencia Post-Q</div>
                        <div class="mt-1 font-semibold text-slate-900 text-sm"><?php echo $tasaAsistenciaPostQ; ?>%</div>
                    </div>
                    <div class="star-card">
                        <div class="font-semibold text-slate-600 text-xs">Conversión a clientes</div>
                        <div class="mt-1 font-semibold text-slate-900 text-sm"><?php echo $tasaConversionClientes; ?>%</div>
                    </div>
                </div>
            </div>
        <div class="card mt-6  p-5">
            <div class="flex justify-between items-center gap-3">
                <div>
                    <div class="font-semibold text-slate-900 text-sm">Info por plataforma</div>
                    <div class="mt-1 text-slate-500 text-xs">Cards con KPIs + embudo (sin recortes, legible)</div>
                </div>
                <div class="text-slate-500 text-xs">Desliza horizontal en pantallas pequeñas</div>
            </div>
            <div class="mt-3 pb-2 overflow-x-auto custom-scroll">
                <div id="platform-cards" class="flex gap-4 min-w-max"></div>
            </div>
        </div>

        <div class="gap-4 grid grid-cols-1 md:grid-cols-2 mt-6">
            <div class="card">
                <div class="px-5 py-4 border-b">
                    <div class="font-semibold text-slate-900 text-sm">Gráfica de pastel - Análisis por Medio</div>
                    <div class="mt-1 text-slate-500 text-xs">Distribución de leads por medio</div>
                </div>
                <div class="p-5">
                    <div class="chart-container">
                        <canvas id="chartMedio"></canvas>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="px-5 py-4 border-b">
                    <div class="font-semibold text-slate-900 text-sm">Gráfica de pastel - Análisis por Origen</div>
                    <div class="mt-1 text-slate-500 text-xs">Distribución de leads por origen</div>
                </div>
                <div class="p-5">
                    <div class="chart-container">
                        <canvas id="chartOrigen"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="gap-4 grid grid-cols-1 xl:grid-cols-4 mt-6">
            <div class="xl:col-span-3 card">
                <div class="flex sm:flex-row flex-col sm:justify-between sm:items-center gap-3 px-5 py-4 border-b">
                    <div>
                        <div class="font-semibold text-slate-900 text-sm">Análisis por Medio</div>
                        <div class="mt-1 text-slate-500 text-xs">Tabla estilo dashboard</div>
                    </div>
                </div>
                <div class="p-5">
                    <table id="tablaMedio" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Medio</th>
                                <th>Total</th>
                                <th>Agendados</th>
                                <th>Atendidos</th>
                                <th>Clientes</th>
                                <th>%Conv</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conteosPorMedio as $medio => $total): 
                                $detalle = $detallesPorMedio[$medio];
                                if ($detalle['total'] == 0) continue;
                                $pctConvCliente = $detalle['atendidos'] > 0 ? round(($detalle['clientes'] / $detalle['atendidos']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($medio); ?></td>
                                <td><?php echo $detalle['total']; ?></td>
                                <td><?php echo $detalle['agendados']; ?></td>
                                <td><?php echo $detalle['atendidos']; ?></td>
                                <td><?php echo $detalle['clientes']; ?></td>
                                <td><?php echo $pctConvCliente; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="xl:col-span-1 card">
                <div class="px-5 py-4 border-b">
                    <div class="font-semibold text-slate-900 text-sm">Keyword Estrella</div>
                    <div class="mt-1 text-slate-500 text-xs">Medios destacados</div>
                </div>
                <div class="p-5">
                    <div id="keyword-stars" class="space-y-3"></div>
                </div>
            </div>
        </div>

        <div class="gap-4 grid grid-cols-1 xl:grid-cols-2 mt-6">
            <div class="card">
                <div class="px-5 py-4 border-b">
                    <div class="font-semibold text-slate-900 text-sm">Análisis por Origen</div>
                    <div class="mt-1 text-slate-500 text-xs">Tabla estilo dashboard</div>
                </div>
                <div class="p-5">
                    <div class="rounded-2xl ring-1 ring-black/10 overflow-hidden">
                        <table id="tablaOrigen" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Origen</th>
                                <th>Total</th>
                                <th>Agendados</th>
                                <th>Atendidos</th>
                                <th>Clientes</th>
                                <th>%Conv</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conteosPorOrigen as $origenKey => $total): 
                                $detalle = $detallesPorOrigen[$origenKey];
                                if ($detalle['total'] == 0) continue;
                                $origenLabel = $origenLabels[$origenKey] ?? $origenKey;
                                $pctConvCliente = $detalle['atendidos'] > 0 ? round(($detalle['clientes'] / $detalle['atendidos']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($origenLabel); ?></td>
                                <td><?php echo $detalle['total']; ?></td>
                                <td><?php echo $detalle['agendados']; ?></td>
                                <td><?php echo $detalle['atendidos']; ?></td>
                                <td><?php echo $detalle['clientes']; ?></td>
                                <td><?php echo $pctConvCliente; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

           
        </div>
    </div>

    <?php include 'footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const DASH_DATA = {
        keywordStars: <?php echo json_encode($keywordStars, JSON_UNESCAPED_UNICODE); ?>,
        platforms: <?php echo json_encode($platformCards, JSON_UNESCAPED_UNICODE); ?>,
        sinDatosMedio: {
            pre: <?php echo json_encode($sinDatosMedioPre, JSON_UNESCAPED_UNICODE); ?>,
            post: <?php echo json_encode($sinDatosMedioPost, JSON_UNESCAPED_UNICODE); ?>,
            clientes: <?php echo json_encode($sinDatosMedioClientes, JSON_UNESCAPED_UNICODE); ?>
        },
        conteosMedio: <?php echo json_encode($conteosPorMedio, JSON_UNESCAPED_UNICODE); ?>,
        conteosOrigen: <?php echo json_encode($conteosPorOrigen, JSON_UNESCAPED_UNICODE); ?>,
        origenLabels: <?php echo json_encode($origenLabels, JSON_UNESCAPED_UNICODE); ?>
    };

    const PLATFORM_UI = {
        "Google Ads": { accent: "bg-gradient-emerald", soft: "bg-emerald-50", text: "text-emerald-700" },
        "Facebook": { accent: "bg-gradient-blue", soft: "bg-blue-50", text: "text-blue-700" },
        "LinkedIn": { accent: "bg-gradient-sky", soft: "bg-sky-50", text: "text-sky-700" },
        "Instagram": { accent: "bg-gradient-fuchsia", soft: "bg-fuchsia-50", text: "text-fuchsia-700" },
        "SEO": { accent: "bg-gradient-green", soft: "bg-emerald-50", text: "text-emerald-700" },
        "default": { accent: "bg-gradient-emerald", soft: "bg-emerald-50", text: "text-emerald-700" }
    };

    const safeDiv = (a, b) => (b ? a / b : 0);
    const pct = (n) => `${(Number(n || 0) * 100).toFixed(1)}%`;

    function renderPlatformCards() {
        const container = document.getElementById('platform-cards');
        if (!container) return;

        container.innerHTML = (DASH_DATA.platforms || []).map(p => {
            const ui = PLATFORM_UI[p.platform] || PLATFORM_UI.default;
            const calificados = (p.A || 0) + (p.AA || 0) + (p.AAA || 0);
            const conv = safeDiv(p.ganados || 0, calificados || 1);

            return `
                <div class="platform-card">
                    <div class="h-full card">
                        <div class="platform-card-top ${ui.accent}"></div>
                        <div class="p-4">
                            <div class="flex justify-between items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full ${ui.accent}"></span>
                                    <div class="font-semibold text-slate-900 text-sm">${p.platform}</div>
                                </div>
                                <span class="rounded-full ${ui.soft} px-2.5 py-1 text-xs font-semibold ${ui.text} ring-1 ring-black/5">
                                    Conversión ${pct(conv)}
                                </span>
                            </div>

                            <div class="gap-3 grid grid-cols-2 mt-3">
                                <div class="bg-slate-50 p-3 rounded-xl ring-1 ring-black/5">
                                    <div class="font-semibold text-slate-500 text-xs uppercase tracking-wide"># Contactos</div>
                                    <div class="mt-1 font-semibold tabular-nums text-slate-900 text-base">${(p.contactos || 0).toLocaleString("es-MX")}</div>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl ring-1 ring-black/5">
                                    <div class="font-semibold text-slate-500 text-xs uppercase tracking-wide"># Agendados</div>
                                    <div class="mt-1 font-semibold tabular-nums text-slate-900 text-base">${(p.A || 0).toLocaleString("es-MX")}</div>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl ring-1 ring-black/5">
                                    <div class="font-semibold text-slate-500 text-xs uppercase tracking-wide"># Atendidos</div>
                                    <div class="mt-1 font-semibold tabular-nums text-slate-900 text-base">${(p.AA || 0).toLocaleString("es-MX")}</div>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl ring-1 ring-black/5">
                                    <div class="font-semibold text-slate-500 text-xs uppercase tracking-wide"># Clientes</div>
                                    <div class="mt-1 font-semibold tabular-nums text-slate-900 text-base">${(p.AAA || 0).toLocaleString("es-MX")}</div>
                                </div>
                            </div>

                            <div class="space-y-3 mt-4">
                                <div class="bg-white p-3 rounded-2xl ring-1 ring-black/5">
                                    <div class="flex justify-between items-start gap-3">
                                        <div class="min-w-0">
                                            <div class="font-semibold text-slate-700 text-xs"># Leads calificados</div>
                                            <div class="mt-0.5 text-slate-500 text-xs">% calif / contactos: ${pct(safeDiv(calificados, p.contactos || 1))}</div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="font-semibold tabular-nums text-slate-900 text-lg">${calificados.toLocaleString("es-MX")}</div>
                                        </div>
                                    </div>
                                    <div class="mt-2 progress-bar">
                                        <div class="progress-bar-fill ${ui.accent}" style="width: ${Math.max(2, Math.min(100, safeDiv(calificados, p.contactos || 1) * 100))}%"></div>
                                    </div>
                                </div>

                                <div class="bg-white p-3 rounded-2xl ring-1 ring-black/5">
                                    <div class="flex justify-between items-start gap-3">
                                        <div class="min-w-0">
                                            <div class="font-semibold text-slate-700 text-xs"># Leads ganados</div>
                                            <div class="mt-0.5 text-slate-500 text-xs">% ganados / calif: ${pct(conv)}</div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="font-semibold tabular-nums text-slate-900 text-lg">${(p.ganados || 0).toLocaleString("es-MX")}</div>
                                        </div>
                                    </div>
                                    <div class="mt-2 progress-bar">
                                        <div class="progress-bar-fill ${ui.accent}" style="width: ${Math.max(2, Math.min(100, conv * 100))}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderKeywordStars() {
        const container = document.getElementById('keyword-stars');
        if (!container) return;
        container.innerHTML = (DASH_DATA.keywordStars || []).map(k => `
            <div class="keyword-card">
                <div class="font-semibold text-slate-900 text-sm">${k.kw}</div>
                <div class="mt-1 text-slate-500 text-xs">${k.note}</div>
            </div>
        `).join('');
    }

    function createPieChart(canvasId, labels, values) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !labels.length) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        const palette = [
            '#2563eb', '#059669', '#c026d3', '#d97706', '#0284c7',
            '#16a34a', '#7c3aed', '#0ea5e9', '#f97316', '#1d4ed8',
            '#047857', '#a21caf', '#b45309', '#0369a1', '#65a30d'
        ];

        new Chart(ctx, {
            type: 'pie',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map((_, i) => palette[i % palette.length]),
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const value = ctx.parsed || 0;
                                const total = ctx.dataset.data.reduce((acc, n) => acc + n, 0) || 1;
                                const pctValue = ((value / total) * 100).toFixed(1);
                                return `${ctx.label}: ${value} (${pctValue}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        console.log('Medios disponibles (keywordStars):', (DASH_DATA.keywordStars || []).map(k => k.kw));
        console.log('Plataformas (platforms):', (DASH_DATA.platforms || []).map(p => p.platform));
        console.log('Registros con platform="Sin datos":', (DASH_DATA.platforms || []).filter(p => String(p.platform).toLowerCase() === 'sin datos'));
        console.log('Sin datos - PRE-Q (sample):', DASH_DATA.sinDatosMedio?.pre || []);
        console.log('Sin datos - POST-Q (sample):', DASH_DATA.sinDatosMedio?.post || []);
        console.log('Sin datos - CLIENTES (sample):', DASH_DATA.sinDatosMedio?.clientes || []);
        renderPlatformCards();
        
        // Inicializar DataTables
        $('#tablaMedio').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            order: [[1, 'desc']],
            paging: false,
            searching: false,
            info: false,
            lengthChange: false,
            responsive: true
        });
        
        $('#tablaOrigen').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            order: [[1, 'desc']],
            paging: false,
            searching: false,
            info: false,
            lengthChange: false,
            responsive: true
        });
        renderKeywordStars();

        const mediosLabels = Object.keys(DASH_DATA.conteosMedio || {});
        const mediosValues = mediosLabels.map(k => Number(DASH_DATA.conteosMedio[k] || 0));
        createPieChart('chartMedio', mediosLabels, mediosValues);

        const origenKeys = Object.keys(DASH_DATA.conteosOrigen || {});
        const origenLabels = origenKeys.map(k => DASH_DATA.origenLabels?.[k] || k);
        const origenValues = origenKeys.map(k => Number(DASH_DATA.conteosOrigen[k] || 0));
        createPieChart('chartOrigen', origenLabels, origenValues);
    });
</script>
</body>
</html>
