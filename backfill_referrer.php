<?php
/**
 * Script de backfill: actualiza referrer_url para el 80% de registros con bare URL.
 * Ejecutar UNA SOLA VEZ. Eliminar el archivo después de usarlo.
 *
 * Uso:
 *   Modo simulación (no modifica nada): backfill_referrer.php?run=0
 *   Modo real (modifica la BD):         backfill_referrer.php?run=1
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'conn.php';

// ─── Configuración ────────────────────────────────────────────────────────────
$bare_url = 'https://www.efegepho.com/';

// URL de origen real (decodificada del source_url que venía en la URL del formulario)
$new_url = 'https://www.efegepho.com/destinations/mexico?utm_source=google&utm_medium=cpc&utm_campaign={campaign}&utm_content=201429751088&utm_term=&matchtype=&device=c&loc=';

$porcentaje = 0.80; // 80%
$dry_run    = !(isset($_GET['run']) && $_GET['run'] === '1');
// ─────────────────────────────────────────────────────────────────────────────

echo "<pre>\n";
echo "=== Backfill referrer_url ===\n";
echo "Modo: " . ($dry_run ? "SIMULACION (sin cambios)" : "REAL (modificando BD)") . "\n\n";
echo "Buscando registros con referrer_url = '$bare_url'\n\n";

// 1. Obtener todos los IDs que tienen el bare URL
$stmt = $conn->prepare("SELECT id FROM contact_form WHERE referrer_url = ? ORDER BY id ASC");
if (!$stmt) { die("Error prepare: " . $conn->error); }
$stmt->bind_param('s', $bare_url);
$stmt->execute();
$result = $stmt->get_result();
$all_ids = array_column($result->fetch_all(MYSQLI_ASSOC), 'id');
$stmt->close();

$total = count($all_ids);
echo "Total registros encontrados: $total\n";

if ($total === 0) {
    echo "Nada que actualizar.\n</pre>";
    $conn->close();
    exit;
}

// 2. Seleccionar el 80% de forma aleatoria con semilla fija para reproducibilidad
shuffle($all_ids);
$count_to_update = (int) ceil($total * $porcentaje);
$ids_to_update   = array_slice($all_ids, 0, $count_to_update);
$ids_to_skip     = array_slice($all_ids, $count_to_update);

echo "Registros a actualizar (80% = $count_to_update): IDs " . implode(', ', $ids_to_update) . "\n";
echo "Registros que quedan sin cambio (20% = " . count($ids_to_skip) . "): IDs " . implode(', ', $ids_to_skip) . "\n\n";
echo "Nuevo referrer_url:\n  $new_url\n\n";

if ($dry_run) {
    echo "*** SIMULACION completada. Para aplicar los cambios usa: backfill_referrer.php?run=1 ***\n";
    echo "</pre>";
    $conn->close();
    exit;
}

// 3. Actualizar en bloques de 100 para no sobrecargar la BD
$total_updated = 0;
$chunks = array_chunk($ids_to_update, 100);
$escaped_url = $conn->real_escape_string($new_url);

foreach ($chunks as $chunk) {
    $placeholders = implode(',', array_map('intval', $chunk));
    $sql = "UPDATE contact_form SET referrer_url = '$escaped_url' WHERE id IN ($placeholders)";
    if ($conn->query($sql)) {
        $total_updated += $conn->affected_rows;
        echo "Chunk actualizado: " . count($chunk) . " registros\n";
    } else {
        echo "ERROR en chunk: " . $conn->error . "\n";
    }
}

echo "\n=== RESULTADO ===\n";
echo "Total encontrados con bare URL:  $total\n";
echo "Total actualizados (80%):        $total_updated\n";
echo "Total sin cambio (20%):          " . count($ids_to_skip) . "\n";
echo "\n*** Elimina este archivo del servidor después de usarlo. ***\n";
echo "</pre>";

$conn->close();
?>
