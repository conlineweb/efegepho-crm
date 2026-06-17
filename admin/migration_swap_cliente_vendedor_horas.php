<?php
/**
 * Migration: normalize calendario datetime mapping.
 *
 * Canonical mapping:
 * - calendario.fecha/hora => vendedor
 * - calendario.fecha_cliente/hora_cliente => cliente
 *
 * This script detects swapped rows using contact_form as source of truth:
 * - c.fecha/hora matches cf.date_appointment_cust/time_appointment_cust
 * - c.fecha_cliente/hora_cliente matches cf.date_appointment/time_appointment
 *
 * Usage:
 * - Dry run (default): migration_swap_cliente_vendedor_horas.php
 * - Apply updates: migration_swap_cliente_vendedor_horas.php?apply=1
 * - Limit rows: migration_swap_cliente_vendedor_horas.php?apply=1&limit=200
 */

include 'conn.php';

function normalizeDateValue($value) {
    $v = trim((string)$value);
    if ($v === '' || $v === '0000-00-00' || $v === '00-00-00') {
        return '';
    }
    $dt = date_create($v);
    return $dt ? $dt->format('Y-m-d') : '';
}

function normalizeTimeValue($value) {
    $v = trim((string)$value);
    if ($v === '' || $v === '00:00:00') {
        return '';
    }
    $dt = date_create('1970-01-01 ' . $v);
    return $dt ? $dt->format('H:i:s') : '';
}

function rowIsSwapped(array $row) {
    $calSellerDate = normalizeDateValue($row['fecha']);
    $calSellerTime = normalizeTimeValue($row['hora']);
    $calClientDate = normalizeDateValue($row['fecha_cliente']);
    $calClientTime = normalizeTimeValue($row['hora_cliente']);

    $srcSellerDate = normalizeDateValue($row['cf_fecha_vendedor']);
    $srcSellerTime = normalizeTimeValue($row['cf_hora_vendedor']);
    $srcClientDate = normalizeDateValue($row['cf_fecha_cliente']);
    $srcClientTime = normalizeTimeValue($row['cf_hora_cliente']);

    if ($srcSellerDate === '' || $srcSellerTime === '' || $srcClientDate === '' || $srcClientTime === '') {
        return false;
    }

    if ($srcSellerDate === $srcClientDate && $srcSellerTime === $srcClientTime) {
        return false;
    }

    return (
        $calSellerDate === $srcClientDate &&
        $calSellerTime === $srcClientTime &&
        $calClientDate === $srcSellerDate &&
        $calClientTime === $srcSellerTime
    );
}

$apply = isset($_GET['apply']) && $_GET['apply'] === '1';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 200;
if ($limit <= 0) {
    $limit = 200;
}

$migrationTag = 'swap_cliente_vendedor_horas_20260604';

$sql = "
    SELECT
        c.id,
        c.idclie,
        c.fecha,
        c.hora,
        c.fecha_cliente,
        c.hora_cliente,
        cf.date_appointment AS cf_fecha_vendedor,
        cf.time_appointment AS cf_hora_vendedor,
        cf.date_appointment_cust AS cf_fecha_cliente,
        cf.time_appointment_cust AS cf_hora_cliente
    FROM calendario c
    INNER JOIN contact_form cf ON cf.id = c.idclie
    WHERE c.eliminado = 0
      AND c.idclie IS NOT NULL
      AND c.idclie > 0
    ORDER BY c.id ASC
";

$res = $conn->query($sql);
if (!$res) {
    echo '<pre>Error en consulta base: ' . $conn->error . '</pre>';
    $conn->close();
    exit;
}

$allRows = [];
while ($row = $res->fetch_assoc()) {
    if (rowIsSwapped($row)) {
        $allRows[] = $row;
    }
}

$totalDetected = count($allRows);
$rowsToProcess = array_slice($allRows, 0, $limit);
$toProcessCount = count($rowsToProcess);

if (!$apply) {
    echo "<pre>";
    echo "MODE: DRY RUN\n";
    echo "Detected swapped rows: $totalDetected\n";
    echo "Rows in preview (limit=$limit): $toProcessCount\n\n";
    echo "Sample IDs:\n";
    $sampleIds = array_slice(array_map(function ($r) { return $r['id']; }, $rowsToProcess), 0, 30);
    echo implode(', ', $sampleIds) . "\n\n";
    echo "Run with ?apply=1 to execute.\n";
    echo "</pre>";
    $conn->close();
    exit;
}

if ($toProcessCount === 0) {
    echo "<pre>MODE: APPLY\nNo rows to update.</pre>";
    $conn->close();
    exit;
}

$createBackupTableSql = "
    CREATE TABLE IF NOT EXISTS calendario_hora_swap_backup (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_tag VARCHAR(80) NOT NULL,
        calendario_id INT NOT NULL,
        idclie INT NULL,
        old_fecha DATE NULL,
        old_hora TIME NULL,
        old_fecha_cliente DATE NULL,
        old_hora_cliente TIME NULL,
        new_fecha DATE NULL,
        new_hora TIME NULL,
        new_fecha_cliente DATE NULL,
        new_hora_cliente TIME NULL,
        migrated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_migration_tag (migration_tag),
        KEY idx_calendario_id (calendario_id)
    )
";

if (!$conn->query($createBackupTableSql)) {
    echo '<pre>Error creando tabla de respaldo: ' . $conn->error . '</pre>';
    $conn->close();
    exit;
}

$conn->begin_transaction();

$insertBackup = $conn->prepare("
    INSERT INTO calendario_hora_swap_backup
    (migration_tag, calendario_id, idclie, old_fecha, old_hora, old_fecha_cliente, old_hora_cliente, new_fecha, new_hora, new_fecha_cliente, new_hora_cliente)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$updateCalendario = $conn->prepare("
    UPDATE calendario
    SET fecha = ?, hora = ?, fecha_cliente = ?, hora_cliente = ?
    WHERE id = ?
");

if (!$insertBackup || !$updateCalendario) {
    $conn->rollback();
    echo '<pre>Error preparando sentencias: ' . $conn->error . '</pre>';
    $conn->close();
    exit;
}

$updatedIds = [];

try {
    foreach ($rowsToProcess as $row) {
        $id = intval($row['id']);
        $idclie = intval($row['idclie']);

        $oldFecha = normalizeDateValue($row['fecha']);
        $oldHora = normalizeTimeValue($row['hora']);
        $oldFechaCliente = normalizeDateValue($row['fecha_cliente']);
        $oldHoraCliente = normalizeTimeValue($row['hora_cliente']);

        $newFecha = $oldFechaCliente;
        $newHora = $oldHoraCliente;
        $newFechaCliente = $oldFecha;
        $newHoraCliente = $oldHora;

        $insertBackup->bind_param(
            'siissssssss',
            $migrationTag,
            $id,
            $idclie,
            $oldFecha,
            $oldHora,
            $oldFechaCliente,
            $oldHoraCliente,
            $newFecha,
            $newHora,
            $newFechaCliente,
            $newHoraCliente
        );
        if (!$insertBackup->execute()) {
            throw new Exception('Error guardando respaldo para ID ' . $id . ': ' . $insertBackup->error);
        }

        $updateCalendario->bind_param('ssssi', $newFecha, $newHora, $newFechaCliente, $newHoraCliente, $id);
        if (!$updateCalendario->execute()) {
            throw new Exception('Error actualizando calendario ID ' . $id . ': ' . $updateCalendario->error);
        }

        $updatedIds[] = $id;
    }

    $conn->commit();

    echo "<pre>";
    echo "MODE: APPLY\n";
    echo "Detected swapped rows: $totalDetected\n";
    echo "Processed rows: " . count($updatedIds) . " (limit=$limit)\n";
    echo "Updated IDs:\n";
    echo implode(', ', $updatedIds) . "\n";
    echo "Migration tag: $migrationTag\n";
    echo "</pre>";
} catch (Exception $e) {
    $conn->rollback();
    echo '<pre>Rollback ejecutado. Error: ' . $e->getMessage() . '</pre>';
}

$insertBackup->close();
$updateCalendario->close();
$conn->close();
?>
