<?php
require __DIR__ . '/conn.php';

$queries = [
    'calendario_distinct_idclie' => 'SELECT COUNT(DISTINCT idclie) AS c FROM calendario',
    'contact_form_in_calendario' => 'SELECT COUNT(*) AS c FROM contact_form cf INNER JOIN calendario cal ON cal.idclie = cf.id',
    'eventos_wp_total' => 'SELECT COUNT(*) AS c FROM eventos_wp',
    'eventos_wp_wp_not_deleted' => 'SELECT COUNT(*) AS c FROM eventos_wp e LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id WHERE (wp.eliminado = 0 OR wp.eliminado IS NULL)',
    'cf_eventos_wp_in_cal' => "SELECT COUNT(*) AS c FROM contact_form cf INNER JOIN calendario cal ON cal.idclie = cf.id WHERE LOWER(TRIM(COALESCE(cf.tabla_origen, ''))) = 'eventos_wp'",
    'cf_not_wp_planner_in_cal' => "SELECT COUNT(*) AS c FROM contact_form cf INNER JOIN calendario cal ON cal.idclie = cf.id WHERE LOWER(TRIM(COALESCE(cf.tabla_origen, ''))) NOT IN ('wedding_planners', 'wedding_planner', 'eventos_wp')",
];

foreach ($queries as $label => $sql) {
    $res = $conn->query($sql);
    $c = ($res && ($row = $res->fetch_assoc())) ? (int) $row['c'] : -1;
    echo $label . ': ' . $c . PHP_EOL;
}

require_once __DIR__ . '/planner_event_display_helper.php';
$records = provisionalFetchAllRecordsForList($conn, true);
echo 'provisionalFetchAllRecordsForList(true): ' . count($records) . PHP_EOL;
$records2 = provisionalFetchAllRecordsForList($conn, false);
echo 'provisionalFetchAllRecordsForList(false): ' . count($records2) . PHP_EOL;

$ctx = (function () use ($conn) {
    require_once __DIR__ . '/consulta_session_leads_helper.php';
    return consultaSessionLeadsBuildAllLeads($conn, false);
})();
echo 'consultaSessionLeadsBuildAllLeads allLeads: ' . count($ctx['allLeads']) . PHP_EOL;
