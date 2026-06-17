<?php
/**
 * Migration: backfill/fix eventos_wp for Wedding Planner appointments.
 *
 * Fixes:
 * 1) wp_citas_leads.id_evento points to missing eventos_wp row.
 * 2) wp_citas_leads.id_evento exists but wedding_planner_id mismatch.
 * 3) calendario.idclie (tipo=1) is out of sync with wp_citas_leads.id_evento.
 *
 * Usage:
 * - Dry run (default): migration_backfill_wp_eventos.php
 * - Apply fixes: migration_backfill_wp_eventos.php?apply=1
 * - Limit rows: migration_backfill_wp_eventos.php?apply=1&limit=500
 */

include 'conn.php';

function migrationWpNormalizeText($value)
{
    if ($value === null) {
        return '';
    }
    return trim((string) $value);
}

function migrationWpNormalizeDate($value)
{
    $v = migrationWpNormalizeText($value);
    if ($v === '' || $v === '0000-00-00') {
        return null;
    }
    $ts = strtotime($v);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d', $ts);
}

function migrationWpNormalizeTime($value)
{
    $v = migrationWpNormalizeText($value);
    if ($v === '' || $v === '00:00:00') {
        return null;
    }
    $ts = strtotime('1970-01-01 ' . $v);
    if ($ts === false) {
        return null;
    }
    return date('H:i:s', $ts);
}

function migrationWpBuildDateTime($dateValue, $timeValue)
{
    $d = migrationWpNormalizeDate($dateValue);
    if ($d === null) {
        return null;
    }
    $t = migrationWpNormalizeTime($timeValue);
    if ($t === null) {
        $t = '00:00:00';
    }
    return $d . ' ' . $t;
}

function migrationWpMapStatus($leadStatus)
{
    $v = mb_strtolower(migrationWpNormalizeText($leadStatus), 'UTF-8');
    if ($v === 'cliente') {
        return 2;
    }
    if ($v === 'atendido') {
        return 1;
    }
    if ($v === 'muerto' || $v === 'fantasma') {
        return 3;
    }
    return 0;
}

function migrationWpTableHasColumn($conn, $table, $column)
{
    static $cache = [];
    $key = strtolower($table . '|' . $column);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $sql = "SELECT COUNT(*) AS c
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$safeTable}'
              AND COLUMN_NAME = '{$safeColumn}'";

    $res = $conn->query($sql);
    $exists = false;
    if ($res) {
        $row = $res->fetch_assoc();
        $exists = intval($row['c'] ?? 0) > 0;
    }
    $cache[$key] = $exists;
    return $exists;
}

function migrationWpEventInsertPayload($row)
{
    $createdAt = migrationWpNormalizeText($row['created_time'] ?? '');
    if ($createdAt === '') {
        $createdAt = date('Y-m-d H:i:s');
    }

    $calendarDateTime = migrationWpBuildDateTime($row['calendario_fecha'] ?? null, $row['calendario_hora'] ?? null);
    $clientDateTime = migrationWpBuildDateTime($row['fecha_cliente'] ?? null, $row['hora_cliente'] ?? null);

    $eventDateTime = $calendarDateTime;
    if ($eventDateTime === null) {
        $eventDateTime = $clientDateTime;
    }
    if ($eventDateTime === null && migrationWpNormalizeDate($row['wedding_date'] ?? null) !== null) {
        $eventDateTime = migrationWpBuildDateTime($row['wedding_date'], '00:00:00');
    }

    return [
        'wedding_planner_id' => intval($row['id_wedding_planner'] ?? 0),
        'lugar' => migrationWpNormalizeText($row['wedding_location'] ?? ''),
        'fecha' => $eventDateTime,
        'novios' => migrationWpNormalizeText($row['full_name'] ?? ''),
        'id_asesor' => intval($row['id_vendedor_asignado'] ?? 0),
        'modo' => 'asistencia_post_q',
        'created_time' => $createdAt,
        'estatus' => migrationWpMapStatus($row['lead_status'] ?? ''),
        'fecha_actualizacion_estatus' => $createdAt,
        'id_coordinador' => 0,
        'fecha_registro' => $createdAt,
        'tipo_paquete' => 'estandar',
        'id_paquete' => 0,
        'paquete_personalizado' => '',
        'comentario' => migrationWpNormalizeText($row['comentario'] ?? ''),
        'comentario_a_cliente' => ''
    ];
}

function migrationWpPrepareInsertEvent($conn, array $payload)
{
    $columnOrder = [
        'wedding_planner_id',
        'lugar',
        'fecha',
        'novios',
        'id_asesor',
        'modo',
        'created_time',
        'estatus',
        'fecha_actualizacion_estatus',
        'id_coordinador',
        'fecha_registro',
        'tipo_paquete',
        'id_paquete',
        'paquete_personalizado',
        'comentario',
        'comentario_a_cliente'
    ];

    $columns = [];
    $placeholders = [];
    $values = [];
    $types = '';

    foreach ($columnOrder as $column) {
        if (!migrationWpTableHasColumn($conn, 'eventos_wp', $column)) {
            continue;
        }
        $columns[] = '`' . $column . '`';
        $placeholders[] = '?';
        $value = $payload[$column];
        if (in_array($column, ['wedding_planner_id', 'id_asesor', 'estatus', 'id_coordinador', 'id_paquete'], true)) {
            $types .= 'i';
            $values[] = intval($value);
        } else {
            $types .= 's';
            $values[] = $value === null ? null : (string) $value;
        }
    }

    if (empty($columns)) {
        throw new Exception('No se pudieron detectar columnas para insertar en eventos_wp.');
    }

    $sql = 'INSERT INTO eventos_wp (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('No se pudo preparar inserción de eventos_wp: ' . $conn->error);
    }

    return [$stmt, $types, $values];
}

function migrationWpBindDynamic($stmt, $types, array &$values)
{
    $params = [];
    $params[] = &$types;
    foreach ($values as $i => $v) {
        $params[] = &$values[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $params);
}

$apply = isset($_GET['apply']) && $_GET['apply'] === '1';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 300;
if ($limit <= 0) {
    $limit = 300;
}

$migrationTag = 'backfill_wp_eventos_20260604';

$sql = "
    SELECT
        wcl.id,
        wcl.id_calendario,
        wcl.id_evento,
        wcl.id_wedding_planner,
        wcl.created_time,
        wcl.fecha_importacion,
        wcl.full_name,
        wcl.email,
        wcl.phone,
        wcl.city,
        wcl.wedding_location,
        wcl.wedding_date,
        wcl.lead_status,
        wcl.id_vendedor_asignado,
        wcl.calendario_fecha,
        wcl.calendario_hora,
        wcl.fecha_cliente,
        wcl.hora_cliente,
        wcl.comentario,
        ev.id AS ev_id,
        ev.wedding_planner_id AS ev_wedding_planner_id,
        c.id AS cal_id,
        c.idclie AS cal_idclie,
        c.tipo AS cal_tipo
    FROM wp_citas_leads wcl
    LEFT JOIN eventos_wp ev ON ev.id = wcl.id_evento
    LEFT JOIN calendario c ON c.id = wcl.id_calendario
    INNER JOIN wedding_planners wp ON wp.id = wcl.id_wedding_planner
    WHERE COALESCE(wcl.descartado, 0) = 0
    ORDER BY wcl.id ASC
";

$res = $conn->query($sql);
if (!$res) {
    echo '<pre>Error consultando wp_citas_leads: ' . $conn->error . '</pre>';
    $conn->close();
    exit;
}

$candidates = [];
while ($row = $res->fetch_assoc()) {
    $idEvento = intval($row['id_evento'] ?? 0);
    $eventExists = intval($row['ev_id'] ?? 0) > 0;
    $plannerMismatch = $eventExists && intval($row['ev_wedding_planner_id'] ?? 0) !== intval($row['id_wedding_planner'] ?? 0);
    $missingEvent = ($idEvento <= 0) || !$eventExists;
    $calendarMismatch = intval($row['cal_id'] ?? 0) > 0
        && intval($row['cal_tipo'] ?? 0) === 1
        && intval($row['cal_idclie'] ?? 0) !== $idEvento;

    if ($missingEvent || $plannerMismatch || $calendarMismatch) {
        $row['__missing_event'] = $missingEvent ? 1 : 0;
        $row['__planner_mismatch'] = $plannerMismatch ? 1 : 0;
        $row['__calendar_mismatch'] = $calendarMismatch ? 1 : 0;
        $candidates[] = $row;
    }
}

$totalCandidates = count($candidates);
$rowsToProcess = array_slice($candidates, 0, $limit);
$processCount = count($rowsToProcess);

if (!$apply) {
    $missingCount = 0;
    $plannerMismatchCount = 0;
    $calendarMismatchCount = 0;
    foreach ($rowsToProcess as $row) {
        $missingCount += intval($row['__missing_event'] ?? 0);
        $plannerMismatchCount += intval($row['__planner_mismatch'] ?? 0);
        $calendarMismatchCount += intval($row['__calendar_mismatch'] ?? 0);
    }

    echo "<pre>";
    echo "MODE: DRY RUN\n";
    echo "Detected rows requiring sync: {$totalCandidates}\n";
    echo "Rows in preview (limit={$limit}): {$processCount}\n\n";
    echo "Preview counts:\n";
    echo "- missing event: {$missingCount}\n";
    echo "- planner mismatch: {$plannerMismatchCount}\n";
    echo "- calendario mismatch: {$calendarMismatchCount}\n\n";
    echo "Sample wp_citas_leads IDs:\n";
    $sampleIds = [];
    foreach (array_slice($rowsToProcess, 0, 40) as $r) {
        $sampleIds[] = intval($r['id']);
    }
    echo implode(', ', $sampleIds) . "\n\n";
    echo "Run with ?apply=1 to execute.\n";
    echo "</pre>";
    $conn->close();
    exit;
}

if ($processCount === 0) {
    echo '<pre>MODE: APPLY' . "\n" . 'No rows to process.</pre>';
    $conn->close();
    exit;
}

$createBackupSql = "
    CREATE TABLE IF NOT EXISTS wp_eventos_backfill_backup (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_tag VARCHAR(80) NOT NULL,
        wp_citas_id INT NOT NULL,
        id_calendario INT NULL,
        old_id_evento INT NULL,
        old_event_planner_id INT NULL,
        old_cal_idclie INT NULL,
        action_taken VARCHAR(40) NOT NULL,
        new_id_evento INT NULL,
        migrated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_wp_eventos_backfill_tag (migration_tag),
        KEY idx_wp_eventos_backfill_wp_citas (wp_citas_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if (!$conn->query($createBackupSql)) {
    echo '<pre>Error creando tabla de respaldo: ' . $conn->error . '</pre>';
    $conn->close();
    exit;
}

$conn->begin_transaction();

$insertBackupStmt = $conn->prepare("
    INSERT INTO wp_eventos_backfill_backup
    (migration_tag, wp_citas_id, id_calendario, old_id_evento, old_event_planner_id, old_cal_idclie, action_taken, new_id_evento)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$updateLeadEventStmt = $conn->prepare('UPDATE wp_citas_leads SET id_evento = ? WHERE id = ? LIMIT 1');
$updateCalEventStmt = $conn->prepare('UPDATE calendario SET idclie = ? WHERE id = ? AND tipo = 1 LIMIT 1');
$updateEventPlannerStmt = $conn->prepare('UPDATE eventos_wp SET wedding_planner_id = ? WHERE id = ? LIMIT 1');

if (!$insertBackupStmt || !$updateLeadEventStmt || !$updateCalEventStmt || !$updateEventPlannerStmt) {
    $conn->rollback();
    echo '<pre>Error preparando sentencias: ' . $conn->error . '</pre>';
    $conn->close();
    exit;
}

$processed = 0;
$createdEvents = 0;
$relinkedLeads = 0;
$relinkedCalendars = 0;
$fixedPlannerMismatch = 0;
$processedIds = [];

try {
    foreach ($rowsToProcess as $row) {
        $wpCitasId = intval($row['id']);
        $calId = intval($row['id_calendario'] ?? 0);
        $oldEventId = intval($row['id_evento'] ?? 0);
        $oldEventPlannerId = intval($row['ev_wedding_planner_id'] ?? 0);
        $oldCalIdclie = intval($row['cal_idclie'] ?? 0);

        $targetEventId = $oldEventId;
        $action = 'noop';

        $isMissingEvent = intval($row['__missing_event'] ?? 0) === 1;
        if ($isMissingEvent) {
            $payload = migrationWpEventInsertPayload($row);
            list($insertEventStmt, $insertTypes, $insertValues) = migrationWpPrepareInsertEvent($conn, $payload);
            migrationWpBindDynamic($insertEventStmt, $insertTypes, $insertValues);
            if (!$insertEventStmt->execute()) {
                throw new Exception('Error creando evento para wp_citas_leads #' . $wpCitasId . ': ' . $insertEventStmt->error);
            }
            $targetEventId = intval($insertEventStmt->insert_id);
            $insertEventStmt->close();
            $createdEvents++;
            $action = 'create_event';
        } else {
            if (intval($row['__planner_mismatch'] ?? 0) === 1) {
                $plannerId = intval($row['id_wedding_planner'] ?? 0);
                $updateEventPlannerStmt->bind_param('ii', $plannerId, $targetEventId);
                if (!$updateEventPlannerStmt->execute()) {
                    throw new Exception('Error corrigiendo planner en eventos_wp #' . $targetEventId . ': ' . $updateEventPlannerStmt->error);
                }
                $fixedPlannerMismatch++;
                $action = 'fix_event_planner';
            }
        }

        if ($targetEventId > 0 && $targetEventId !== $oldEventId) {
            $updateLeadEventStmt->bind_param('ii', $targetEventId, $wpCitasId);
            if (!$updateLeadEventStmt->execute()) {
                throw new Exception('Error actualizando wp_citas_leads #' . $wpCitasId . ': ' . $updateLeadEventStmt->error);
            }
            $relinkedLeads++;
            if ($action === 'noop') {
                $action = 'relink_lead';
            }
        }

        if ($calId > 0 && $targetEventId > 0 && $oldCalIdclie !== $targetEventId) {
            $updateCalEventStmt->bind_param('ii', $targetEventId, $calId);
            if (!$updateCalEventStmt->execute()) {
                throw new Exception('Error actualizando calendario #' . $calId . ': ' . $updateCalEventStmt->error);
            }
            $relinkedCalendars++;
            if ($action === 'noop') {
                $action = 'relink_calendar';
            }
        }

        if ($action === 'noop') {
            $action = 'touch';
        }

        $insertBackupStmt->bind_param(
            'siiiiisi',
            $migrationTag,
            $wpCitasId,
            $calId,
            $oldEventId,
            $oldEventPlannerId,
            $oldCalIdclie,
            $action,
            $targetEventId
        );
        if (!$insertBackupStmt->execute()) {
            throw new Exception('Error guardando respaldo para wp_citas_leads #' . $wpCitasId . ': ' . $insertBackupStmt->error);
        }

        $processed++;
        $processedIds[] = $wpCitasId;
    }

    $conn->commit();

    echo "<pre>";
    echo "MODE: APPLY\n";
    echo "Processed rows: {$processed} (limit={$limit})\n";
    echo "Created eventos_wp: {$createdEvents}\n";
    echo "Fixed planner mismatches: {$fixedPlannerMismatch}\n";
    echo "Updated wp_citas_leads.id_evento: {$relinkedLeads}\n";
    echo "Updated calendario.idclie: {$relinkedCalendars}\n";
    echo "Migration tag: {$migrationTag}\n\n";
    echo "Processed wp_citas_leads IDs:\n";
    echo implode(', ', $processedIds) . "\n";
    echo "</pre>";
} catch (Exception $e) {
    $conn->rollback();
    echo '<pre>Rollback ejecutado. Error: ' . $e->getMessage() . '</pre>';
}

$insertBackupStmt->close();
$updateLeadEventStmt->close();
$updateCalEventStmt->close();
$updateEventPlannerStmt->close();
$conn->close();
?>
