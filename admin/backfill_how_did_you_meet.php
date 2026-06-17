<?php
/**
 * backfill_how_did_you_meet.php
 *
 * Rellena la columna how_did_you_meet en contact_form para todos los
 * registros donde está vacía (NULL o cadena vacía), usando la columna
 * how_long_known_us como fuente de verdad.
 *
 * Reglas:
 *   'less than 3 months'          → 3  (New Audience)
 *   'between 3 months and 1 year' → 3  (New Audience)
 *   'more than 1 year'            → 2  (Community)
 *   Cualquier otro valor          → se omite (no se toca)
 *
 * Uso: abrir en el navegador o ejecutar desde línea de comandos.
 * El parámetro GET ?dry_run=1 muestra lo que haría SIN modificar la BD.
 */

include 'conn.php';

header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

echo $dryRun
    ? "=== MODO SIMULACIÓN (dry_run=1) — no se modifica la BD ===\n\n"
    : "=== BACKFILL how_did_you_meet ===\n\n";

// Mapeo: valor BD → valor a asignar en how_did_you_meet
// Los valores reales en la BD son con capital y "6 months" como umbral.
$mapping = [
    // Valores reales del formulario actual
    'less than 6 months'          => '3', // New Audience
    'more than 6 months'          => '2', // Community
    // Valores legacy (formularios anteriores)
    'less than 3 months'          => '3', // New Audience
    'between 3 months and 1 year' => '3', // New Audience
    'more than 1 year'            => '2', // Community
];

// ── 1. Diagnóstico rápido ────────────────────────────────────────────────────
$diagRes = $conn->query("SELECT COUNT(*) AS cnt FROM contact_form
    WHERE (how_did_you_meet IS NULL OR TRIM(how_did_you_meet) = '')
      AND LOWER(COALESCE(tabla_origen,'')) NOT IN ('wedding_planners','wedding_planner')");
$diagRow = $diagRes ? $diagRes->fetch_assoc() : ['cnt' => '?'];
echo "Registros con how_did_you_meet vacío (sin importar how_long_known_us): {$diagRow['cnt']}\n\n";

// ── 2. Candidatos: how_did_you_meet vacío, buscar how_long_known_us también en
//       la tabla origen (organic_leads, form_website, etc.) si no está en contact_form
$sql = "SELECT id, names, how_long_known_us, tabla_origen, original_lead_id
        FROM contact_form
        WHERE (how_did_you_meet IS NULL OR TRIM(how_did_you_meet) = '')
          AND how_long_known_us IS NOT NULL
          AND TRIM(how_long_known_us) != ''
          AND LOWER(COALESCE(tabla_origen, '')) NOT IN ('wedding_planners', 'wedding_planner')
        ORDER BY id ASC";

$result = $conn->query($sql);
if (!$result) {
    echo "ERROR al consultar: " . $conn->error . "\n";
    exit(1);
}

$total     = $result->num_rows;
$updated   = 0;
$skipped   = 0;

echo "Registros candidatos: $total\n\n";

if ($total === 0) {
    echo "No hay registros que actualizar. ✅\n";
    exit(0);
}

if (!$dryRun) {
    $conn->begin_transaction();
}

while ($row = $result->fetch_assoc()) {
    $id       = intval($row['id']);
    $nombre   = trim($row['names'] ?? '(sin nombre)');
    $tabla    = $row['tabla_origen'] ?? '';
    $origId   = intval($row['original_lead_id'] ?? 0);

    // Intentar obtener how_long_known_us: primero desde contact_form, luego desde tabla origen
    $knownRaw = mb_strtolower(trim((string)($row['how_long_known_us'] ?? '')), 'UTF-8');

    if ($knownRaw === '' && $tabla !== '' && $origId > 0) {
        $escapedTabla = $conn->real_escape_string($tabla);
        $chk = $conn->query("SHOW TABLES LIKE '$escapedTabla'");
        if ($chk && $chk->num_rows > 0) {
            $origRes = $conn->query("SELECT how_long_known_us FROM `$escapedTabla` WHERE id = $origId LIMIT 1");
            if ($origRes && $origRes->num_rows > 0) {
                $origRow  = $origRes->fetch_assoc();
                $knownRaw = mb_strtolower(trim((string)($origRow['how_long_known_us'] ?? '')), 'UTF-8');
            }
        }
    }

    if ($knownRaw === '' || !isset($mapping[$knownRaw])) {
        $motivo = $knownRaw === '' ? 'how_long_known_us vacío en contact_form y tabla origen' : "valor no reconocido: \"$knownRaw\"";
        echo "OMITIDO  | ID: $id | $nombre | tabla: $tabla | $motivo\n";
        $skipped++;
        continue;
    }

    $newValue    = $mapping[$knownRaw];
    $originLabel = $newValue === '3' ? 'New Audience' : 'Community';

    echo "ACTUALIZAR | ID: $id | $nombre | tabla: $tabla | \"$knownRaw\" → how_did_you_meet = $newValue ($originLabel)\n";

    if (!$dryRun) {
        $stmt = $conn->prepare("UPDATE contact_form SET how_did_you_meet = ? WHERE id = ? AND (how_did_you_meet IS NULL OR TRIM(how_did_you_meet) = '')");
        $stmt->bind_param('si', $newValue, $id);
        if (!$stmt->execute()) {
            $conn->rollback();
            echo "\nERROR al actualizar ID $id: " . $stmt->error . "\n";
            exit(1);
        }
        $updated += $stmt->affected_rows;
        $stmt->close();
    } else {
        $updated++;
    }
}

if (!$dryRun) {
    $conn->commit();
}

echo "\n";
echo "─────────────────────────────────\n";
echo "Total candidatos : $total\n";
echo "Actualizados     : $updated\n";
echo "Omitidos         : $skipped\n";

if ($dryRun) {
    echo "\n(Simulación completada. Para aplicar los cambios quita ?dry_run=1 de la URL)\n";
} else {
    echo "\nBackfill completado ✅\n";
}
