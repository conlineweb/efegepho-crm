<?php
include __DIR__ . '/conn.php';

$tables = ['tablas_leads', 'organic_leads', 'contact_form', 'eventos_wp', 'wp_citas_leads', 'calendario'];
foreach ($tables as $t) {
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    if ($r && $r->num_rows > 0) {
        $c = $conn->query("SELECT COUNT(*) c FROM `$t`")->fetch_assoc()['c'];
        echo "$t: $c\n";
    } else {
        echo "$t: (no existe)\n";
    }
}

echo "\n--- tablas_leads con datos ---\n";
$res = $conn->query('SELECT nombre, tipo FROM tablas_leads ORDER BY nombre');
while ($row = $res->fetch_assoc()) {
    $tn = $row['nombre'];
    $chk = $conn->query("SHOW TABLES LIKE '$tn'");
    if ($chk && $chk->num_rows > 0) {
        $c = (int) $conn->query("SELECT COUNT(*) c FROM `$tn`")->fetch_assoc()['c'];
        if ($c > 0) {
            echo "  $tn (tipo {$row['tipo']}): $c rows\n";
        }
    }
}

echo "\n--- wpEventosCf query ---\n";
$sql = "SELECT cf.id, cf.original_lead_id, cf.tabla_origen, cf.submission_date, e.id as eid, e.novios
        FROM contact_form cf
        INNER JOIN eventos_wp e ON e.id = cf.original_lead_id
        WHERE LOWER(TRIM(COALESCE(cf.tabla_origen, ''))) = 'eventos_wp'";
$r = $conn->query($sql);
echo 'rows: ' . ($r ? $r->num_rows : 'error ' . $conn->error) . "\n";
if ($r) {
    while ($row = $r->fetch_assoc()) {
        print_r($row);
    }
}
