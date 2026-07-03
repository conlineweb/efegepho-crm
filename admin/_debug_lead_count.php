<?php
/**
 * Diagnóstico temporal — sube a admin/ en cPanel y abre en el navegador:
 * https://sandbox.efegepho.com.mx/admin/_debug_lead_count.php
 * BORRA este archivo cuando termines.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

include __DIR__ . '/conn.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Diagnóstico consulta_leads.php ===\n";
echo "Base de datos: " . ($conn->query('SELECT DATABASE()')->fetch_row()[0] ?? '?') . "\n";
echo "Fecha servidor: " . date('Y-m-d H:i:s') . "\n\n";

$coreTables = [
    'tablas_leads',
    'organic_leads',
    'contact_form',
    'eventos_wp',
    'calendario',
    'wp_citas_leads',
    'wp_eventos_afianzados',
    'wedding_planners',
];

echo "--- Conteo tablas principales ---\n";
foreach ($coreTables as $t) {
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    if (!$r || $r->num_rows === 0) {
        echo "$t: (no existe)\n";
        continue;
    }
    $c = (int) $conn->query("SELECT COUNT(*) c FROM `$t`")->fetch_assoc()['c'];
    echo "$t: $c\n";
}

echo "\n--- Tablas en tablas_leads con filas (consulta_leads las recorre) ---\n";
$sqlTablas = "SELECT nombre, tipo FROM tablas_leads WHERE tipo != 2 OR nombre = 'wp_citas_leads' ORDER BY nombre";
$res = $conn->query($sqlTablas);
$totalFromTablas = 0;
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $tn = $row['nombre'];
        $chk = $conn->query("SHOW TABLES LIKE '$tn'");
        if (!$chk || $chk->num_rows === 0) {
            echo "  $tn (tipo {$row['tipo']}): TABLA NO EXISTE\n";
            continue;
        }
        $where = '';
        $cols = $conn->query("SHOW COLUMNS FROM `$tn`");
        $hasDescartado = false;
        if ($cols) {
            while ($col = $cols->fetch_assoc()) {
                if ($col['Field'] === 'descartado') {
                    $hasDescartado = true;
                    break;
                }
            }
        }
        if ($hasDescartado) {
            $where = ' WHERE (descartado = 0 OR descartado IS NULL)';
        }
        $c = (int) $conn->query("SELECT COUNT(*) c FROM `$tn`$where")->fetch_assoc()['c'];
        if ($c > 0) {
            echo "  $tn (tipo {$row['tipo']}): $c filas activas\n";
            $totalFromTablas += $c;
            $sample = $conn->query("SELECT id, full_name, created_time FROM `$tn`$where ORDER BY id DESC LIMIT 3");
            if ($sample) {
                while ($s = $sample->fetch_assoc()) {
                    echo "      id={$s['id']} | " . ($s['full_name'] ?? '—') . " | " . ($s['created_time'] ?? 'sin fecha') . "\n";
                }
            }
        }
    }
}

echo "\n--- Eventos WP (segunda fuente en consulta_leads) ---\n";
$wpSql = "SELECT cf.id AS cf_id, cf.original_lead_id, cf.submission_date, e.novios, e.fecha_registro
          FROM contact_form cf
          INNER JOIN eventos_wp e ON e.id = cf.original_lead_id
          WHERE LOWER(TRIM(COALESCE(cf.tabla_origen, ''))) = 'eventos_wp'";
$wpRes = $conn->query($wpSql);
$wpCount = $wpRes ? $wpRes->num_rows : 0;
echo "contact_form + eventos_wp: $wpCount\n";
if ($wpRes) {
    while ($row = $wpRes->fetch_assoc()) {
        echo "  evento id={$row['original_lead_id']} | cf_id={$row['cf_id']} | novios=" . ($row['novios'] ?? '—') . "\n";
    }
}

echo "\n--- Citas huérfanas (inflan 'agendados' sin lead visible) ---\n";
$orphanSql = "SELECT cf.id, cf.tabla_origen, cf.original_lead_id, c.fecha
              FROM calendario c
              INNER JOIN contact_form cf ON cf.id = c.idclie
              WHERE LOWER(COALESCE(cf.tabla_origen, '')) NOT IN ('wedding_planners','wedding_planner')
              LIMIT 20";
$orphanRes = $conn->query($orphanSql);
echo "Registros calendario+contact_form: " . ($orphanRes ? $orphanRes->num_rows : 0) . "\n";
if ($orphanRes) {
    while ($row = $orphanRes->fetch_assoc()) {
        $tbl = $row['tabla_origen'] ?? '';
        $oid = (int) ($row['original_lead_id'] ?? 0);
        $exists = '?';
        if ($tbl !== '' && $oid > 0) {
            $safe = $conn->real_escape_string($tbl);
            $chk = $conn->query("SHOW TABLES LIKE '$safe'");
            if ($chk && $chk->num_rows > 0) {
                $ex = (int) $conn->query("SELECT COUNT(*) c FROM `$safe` WHERE id = $oid")->fetch_assoc()['c'];
                $exists = $ex > 0 ? 'SÍ' : 'NO (huérfano)';
            }
        }
        echo "  cf_id={$row['id']} | tabla=$tbl | orig_id=$oid | lead existe: $exists\n";
    }
}

echo "\n=== Si consulta_leads muestra 1 lead, revisa la tabla que aparece arriba con filas. ===\n";
echo "Borra este archivo cuando termines.\n";
