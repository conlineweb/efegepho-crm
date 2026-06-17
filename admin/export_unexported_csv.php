<?php
// export_unexported_csv.php
// Exports only leads that have not been previously exported.
// Uses a server-side JSON file at admin/export_logs/exported_leads.json to track exported keys (tabla_origen:id)
// No DB updates made.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conn.php';

// Where we will store exported record keys and timestamps
$logDir = __DIR__ . '/export_logs';
$logFile = $logDir . '/exported_leads.json';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$exportLog = [];
if (file_exists($logFile)) {
    $raw = file_get_contents($logFile);
    $exportLog = json_decode($raw, true) ?: [];
}

// Helper: return combined unique key for a lead
function lead_key($lead)
{
    $tid = isset($lead['tabla_origen']) ? $lead['tabla_origen'] : (isset($lead['form_name']) ? $lead['form_name'] : 'unknown');
    $id = isset($lead['id']) ? $lead['id'] : '';
    return $tid . ':' . $id;
}

// Build all leads (same logic used in exportar_leads.php)
$tablas = [];
$res = $conn->query("SELECT nombre FROM tablas_leads ORDER BY nombre");
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        $tablas[] = $r['nombre'];
    }
}

$allLeads = [];
foreach ($tablas as $tableName) {
    $check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    if ($check && $check->num_rows > 0) {
        $columns = [];
        $colsRes = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tableName) . "`");
        while ($c = $colsRes->fetch_assoc())
            $columns[] = $c['Field'];

        $where = (in_array('descartado', $columns) ? "descartado = 0 OR descartado IS NULL" : "1=1");
        $q = "SELECT *, '" . $conn->real_escape_string($tableName) . "' as tabla_origen FROM `" . $conn->real_escape_string($tableName) . "` WHERE $where ORDER BY created_time DESC";
        $rs = $conn->query($q);
        if ($rs && $rs->num_rows > 0) {
            while ($lead = $rs->fetch_assoc())
                $allLeads[] = $lead;
        }
    }
}

// Option: include previously exported using ?include_exported=1
$includeExported = isset($_GET['include_exported']) && $_GET['include_exported'] == '1';
// Optional language filter: ?lang=es or ?lang=en (es => phone contains +52; en => everything else)
$lang = isset($_GET['lang']) ? trim($_GET['lang']) : '';

// Prepare list to export: if includeExported, export them all and annotate exported_at if present in log
// Determine candidate leads: only those assigned to Agente llamada (IA) (usuario_asignado == 99)
$candidates = [];
$leadIdsByTable = [];
foreach ($allLeads as $lead) {
    if (!isset($lead['usuario_asignado']))
        continue;
    if (intval($lead['usuario_asignado']) !== 99)
        continue;
    $t = isset($lead['tabla_origen']) ? $lead['tabla_origen'] : (isset($lead['form_name']) ? $lead['form_name'] : '');
    $id = isset($lead['id']) ? intval($lead['id']) : 0;
    if ($t === '' || $id <= 0)
        continue;
    $candidates[] = $lead;
    if (!isset($leadIdsByTable[$t]))
        $leadIdsByTable[$t] = [];
    $leadIdsByTable[$t][] = $id;
}

// Find scheduled candidate leads via contact_form and exclude them
$scheduled = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '')
        continue;
    $rs = $conn->query("SELECT original_lead_id FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")");
    if ($rs) {
        while ($r = $rs->fetch_assoc()) {
            $scheduled[$t . ':' . intval($r['original_lead_id'])] = true;
        }
    }
}

$toExport = [];
$now = date('Y-m-d H:i:s');
foreach ($candidates as $lead) {
    // language filter
    $rawPhoneForLang = isset($lead['phone']) ? $lead['phone'] : '';
    $phoneCleanForLang = preg_replace('/^\s*p:\s*/i', '', trim($rawPhoneForLang));
    if ($lang === 'es' && !(stripos($phoneCleanForLang, '+52') !== false || preg_match('/^\+?52/', ltrim($phoneCleanForLang))))
        continue;
    if ($lang === 'en' && (stripos($phoneCleanForLang, '+52') !== false || preg_match('/^\+?52/', ltrim($phoneCleanForLang))))
        continue;
    $t = isset($lead['tabla_origen']) ? $lead['tabla_origen'] : (isset($lead['form_name']) ? $lead['form_name'] : '');
    $id = isset($lead['id']) ? intval($lead['id']) : 0;
    $key1 = $t . ':' . $id;
    if (isset($scheduled[$key1]))
        continue; // skip scheduled

    $key = lead_key($lead);
    $already = isset($exportLog[$key]);
    if (!$already || $includeExported) {
        $lead_exported_at = $already ? $exportLog[$key] : $now;
        $lead['exported_at'] = $lead_exported_at;
        $lead['_export_key'] = $key;
        $toExport[] = $lead;
    }
}

// Mark newly exported leads in the log now (both for includeExported and default exports)
if (count($toExport) > 0) {
    $updated = false;
    foreach ($toExport as $lead) {
        $k = $lead['_export_key'];
        if (!isset($exportLog[$k])) {
            $exportLog[$k] = $now;
            $updated = true;
        }
    }
    // write log back to file if anything new was marked
    if ($updated) {
        file_put_contents($logFile, json_encode($exportLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// Build CSV
$filename = 'leads_unexported_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel opens correctly
fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

// header columns — include origin and id (do NOT include export timestamp in CSV)
fputcsv($out, ['Name', 'Phone', 'Email', 'Table', 'LeadId']);

foreach ($toExport as $lead) {
    // normalize phone similar to exportar_leads.php
    $rawPhone = isset($lead['phone']) ? $lead['phone'] : '';
    $phoneClean = preg_replace('/^\s*p:\s*/i', '', trim($rawPhone));

    // Prevent Excel showing scientific notation or a visible leading apostrophe:
    // Use an Excel formula that returns a string, e.g. ="12345" or ="+1216..." — this displays the full number without extra characters.
    if ($phoneClean !== '') {
        // Remove any leading '+' signs, escape double quotes and wrap in an Excel formula to preserve formatting
        $phoneNoPlus = str_replace('+', '', $phoneClean);
        $phoneForCsv = '="' . str_replace('"', '""', $phoneNoPlus) . '"';
    } else {
        $phoneForCsv = '';
    }
    $row = [
        isset($lead['full_name']) ? $lead['full_name'] : '',
        $phoneForCsv,
        isset($lead['email']) ? $lead['email'] : '',
        isset($lead['tabla_origen']) ? $lead['tabla_origen'] : '',
        isset($lead['id']) ? $lead['id'] : ''
    ];
    fputcsv($out, $row);
}

fclose($out);
exit;
