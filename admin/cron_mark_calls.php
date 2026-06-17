<?php
// cron_mark_calls.php
// Ejecutar desde cron: php /path/to/admin/cron_mark_calls.php
// Marca estatus_llamada=1 para leads exportados hace más de 3 horas.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/conn.php';

$logDir = __DIR__ . '/export_logs';
$logFile = $logDir . '/exported_leads.json';

if (!file_exists($logFile)) {
    echo "No export log found at $logFile\n";
    exit(0);
}

$raw = file_get_contents($logFile);
$exportLog = json_decode($raw, true);
if (!is_array($exportLog)) {
    echo "Invalid export log format\n";
    exit(1);
}

$now = time();
$processed = 0;
$skipped = 0;
$errors = 0;

foreach ($exportLog as $key => $value) {
    // Determine exported_at and whether already handled
    $exportedAt = null;
    $llamadaMarkedAt = null;
    if (is_string($value)) {
        $exportedAt = $value;
    } elseif (is_array($value)) {
        $exportedAt = $value['exported_at'] ?? $value['exportedAt'] ?? null;
        $llamadaMarkedAt = $value['llamada_marked_at'] ?? $value['llamadaMarkedAt'] ?? null;
    }

    if (!$exportedAt) {
        $skipped++;
        continue;
    }

    if (!empty($llamadaMarkedAt)) {
        // already processed
        $skipped++;
        continue;
    }

    $ts = strtotime($exportedAt);
    if ($ts === false) {
        $errors++;
        continue;
    }

    if (($now - $ts) < 15 * 60) {
    // not old enough
    $skipped++;
    continue;
}

    // parse key: expected format 'tabla:id' or 'tabla:id' as in exported_leads.json
    $parts = explode(':', $key, 2);
    if (count($parts) !== 2) {
        $errors++;
        continue;
    }
    $table = $parts[0];
    $id = intval($parts[1]);
    if ($table === '' || $id <= 0) {
        $errors++;
        continue;
    }

    // validate table exists in tablas_leads
    $stmt = $conn->prepare('SELECT 1 FROM tablas_leads WHERE nombre = ? LIMIT 1');
    if (!$stmt) {
        echo "DB prepare error checking tablas_leads\n";
        $errors++;
        continue;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        // table not registered; skip
        $skipped++;
        continue;
    }

    // check if column estatus_llamada exists
    $colRes = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "` LIKE 'estatus_llamada'");
    if (!$colRes || $colRes->num_rows === 0) {
        // column missing; skip
        echo "Table $table has no column estatus_llamada; skipping $key\n";
        $skipped++;
        continue;
    }

    // perform update
    $safeTable = str_replace('`', '', $table);
    // Check current estatus_llamada and only proceed if it's 0
    $checkSql = "SELECT estatus_llamada FROM `" . $conn->real_escape_string($safeTable) . "` WHERE id = ? LIMIT 1";
    $stmtCheck = $conn->prepare($checkSql);
    if (!$stmtCheck) {
        echo "Prepare failed (check) for $table:$id\n";
        $errors++;
        continue;
    }
    $stmtCheck->bind_param('i', $id);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    if (!$resCheck || $resCheck->num_rows === 0) {
        echo "Row not found $table id=$id; skipping\n";
        $skipped++;
        continue;
    }
    $rowCheck = $resCheck->fetch_assoc();
    $currStatus = isset($rowCheck['estatus_llamada']) ? intval($rowCheck['estatus_llamada']) : null;
    if ($currStatus !== 0) {
        echo "Skipping $table id=$id because estatus_llamada != 0 (currently " . var_export($currStatus, true) . ")\n";
        $skipped++;
        continue;
    }
    $sql = "UPDATE `" . $conn->real_escape_string($safeTable) . "` SET estatus_llamada = 1 WHERE id = ?";
    $stmtUp = $conn->prepare($sql);
    if (!$stmtUp) {
        echo "Prepare failed for $table:$id\n";
        $errors++;
        continue;
    }
    $stmtUp->bind_param('i', $id);
    if ($stmtUp->execute()) {
        $processed++;
        // mark in log
        $exportLog[$key] = [
            'exported_at' => date('Y-m-d H:i:s', $ts),
            'llamada_marked_at' => date('Y-m-d H:i:s', $now)
        ];
        echo "Marked estatus_llamada=1 for $table id=$id\n";
    } else {
        echo "Failed to update $table id=$id\n";
        $errors++;
    }
}

// persist updated log if any processed
if ($processed > 0) {
    file_put_contents($logFile, json_encode($exportLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Updated log file with $processed processed entries\n";
}

echo "Done. processed=$processed skipped=$skipped errors=$errors\n";
exit(0);

?>
