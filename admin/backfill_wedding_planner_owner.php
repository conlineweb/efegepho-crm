<?php
include 'conn.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

function ensureColumnExists($conn, $table, $columnName, $columnDef)
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($columnName);
    $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safeTable}' AND COLUMN_NAME = '{$safeColumn}'";
    $res = $conn->query($checkSql);
    if ($res) {
        $row = $res->fetch_assoc();
        if (intval($row['c'] ?? 0) === 0) {
            if (!$conn->query("ALTER TABLE `{$table}` ADD COLUMN {$columnDef}")) {
                throw new Exception('No se pudo crear la columna ' . $columnName . ': ' . $conn->error);
            }
        }
    }
}

function columnExists($conn, $table, $columnName)
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($columnName);
    $sql = "SELECT COUNT(*) AS c
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$safeTable}'
              AND COLUMN_NAME = '{$safeColumn}'";
    $res = $conn->query($sql);
    if (!$res) {
        throw new Exception('No se pudo verificar la columna ' . $table . '.' . $columnName . ': ' . $conn->error);
    }

    $row = $res->fetch_assoc();
    return intval($row['c'] ?? 0) > 0;
}

function runBackfillForColumn($conn, $wpColumn, $coordColumn, $coordCondition = null)
{
    if (!columnExists($conn, 'coordinadores_wp', $coordColumn)) {
        return [
            'status' => 'skipped',
            'reason' => 'La columna coordinadores_wp.' . $coordColumn . ' no existe en esta base.',
            'affected_rows' => 0
        ];
    }

    if (!columnExists($conn, 'wedding_planners', $wpColumn)) {
        return [
            'status' => 'skipped',
            'reason' => 'La columna wedding_planners.' . $wpColumn . ' no existe en esta base.',
            'affected_rows' => 0
        ];
    }

    $condition = $coordCondition ?: "`{$coordColumn}` IS NOT NULL AND TRIM(`{$coordColumn}`) <> ''";
    $sql = "UPDATE wedding_planners wp
            INNER JOIN (
                SELECT c.id_wp, c.`{$coordColumn}` AS value_to_copy
                FROM coordinadores_wp c
                INNER JOIN (
                    SELECT id_wp, MAX(id) AS max_id
                    FROM coordinadores_wp
                    WHERE {$condition}
                    GROUP BY id_wp
                ) latest ON latest.id_wp = c.id_wp AND latest.max_id = c.id
            ) src ON src.id_wp = wp.id
            SET wp.`{$wpColumn}` = src.value_to_copy
            WHERE (wp.`{$wpColumn}` IS NULL OR TRIM(wp.`{$wpColumn}`) = '')";

    if (!$conn->query($sql)) {
        throw new Exception('Error haciendo backfill de ' . $wpColumn . ': ' . $conn->error);
    }

    return [
        'status' => 'ok',
        'affected_rows' => $conn->affected_rows
    ];
}

try {
    ensureColumnExists($conn, 'wedding_planners', 'phone', "`phone` VARCHAR(255) DEFAULT NULL AFTER `id_vendedor_asignado`");
    ensureColumnExists($conn, 'wedding_planners', 'email', "`email` VARCHAR(255) DEFAULT NULL AFTER `empresa_wp`");
    ensureColumnExists($conn, 'wedding_planners', 'how_long_known_us', "`how_long_known_us` VARCHAR(150) DEFAULT NULL AFTER `whatsapp_bot_sent`");
    ensureColumnExists($conn, 'wedding_planners', 'planner_reason', "`planner_reason` VARCHAR(255) DEFAULT NULL AFTER `how_long_known_us`");
    ensureColumnExists($conn, 'wedding_planners', 'first_contact_channel', "`first_contact_channel` VARCHAR(255) DEFAULT NULL AFTER `estatus`");
    ensureColumnExists($conn, 'wedding_planners', 'campaign_name', "`campaign_name` VARCHAR(255) DEFAULT NULL AFTER `first_contact_channel`");
    ensureColumnExists($conn, 'wedding_planners', 'platform', "`platform` VARCHAR(255) DEFAULT NULL AFTER `campaign_name`");
    ensureColumnExists($conn, 'wedding_planners', 'empresa_wp', "`empresa_wp` VARCHAR(255) DEFAULT NULL AFTER `where_is_your_marriage_taking_place_`");

    $conn->begin_transaction();

    $results = [
        'phone' => runBackfillForColumn($conn, 'phone', 'telefono'),
        'email' => runBackfillForColumn($conn, 'email', 'correo'),
        'planner_reason' => runBackfillForColumn($conn, 'planner_reason', 'planner_reason'),
        'how_long_known_us' => runBackfillForColumn($conn, 'how_long_known_us', 'how_long_known_us'),
        'first_contact_channel' => runBackfillForColumn($conn, 'first_contact_channel', 'first_contact_channel'),
        'campaign_name' => runBackfillForColumn($conn, 'campaign_name', 'campaign_name'),
        'platform' => runBackfillForColumn($conn, 'platform', 'platform'),
    ];

    $sqlEmpresa = "UPDATE wedding_planners
                   SET empresa_wp = full_name
                   WHERE (empresa_wp IS NULL OR TRIM(empresa_wp) = '')
                     AND full_name IS NOT NULL
                     AND TRIM(full_name) <> ''";
    if (!$conn->query($sqlEmpresa)) {
        throw new Exception('Error actualizando empresa_wp desde full_name: ' . $conn->error);
    }
    $results['empresa_wp_from_full_name'] = [
        'status' => 'ok',
        'affected_rows' => $conn->affected_rows
    ];

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Backfill completado.',
        'affected_rows' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($conn && $conn->errno !== null) {
        $conn->rollback();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
