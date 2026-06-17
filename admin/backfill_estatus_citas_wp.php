<?php
include 'conn.php';
require_once __DIR__ . '/wp_citas_leads_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function mapWpLeadStatusFromCalendarBackfill($estatus)
{
    if ($estatus === 1) {
        return 'atendido';
    }
    if ($estatus === 3) {
        return 'muerto';
    }

    return 'agendado';
}

try {
    $rows = [];
    $result = $conn->query("SELECT id, idclie, estatus FROM calendario WHERE tipo = 1 AND eliminado = 0 AND estatus IN (1, 3)");
    if (!$result) {
        throw new Exception('No se pudieron consultar las citas WP para backfill: ' . $conn->error);
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $hasWpCitasTable = false;
    $wpCitasTableExists = $conn->query("SHOW TABLES LIKE 'wp_citas_leads'");
    if ($wpCitasTableExists && $wpCitasTableExists->num_rows > 0) {
        $hasWpCitasTable = true;
    }

    $conn->begin_transaction();

    $processed = 0;
    $eventsUpdated = 0;
    $mirrorsUpdated = 0;
    $warnings = [];

    foreach ($rows as $row) {
        $calendarioId = intval($row['id'] ?? 0);
        $eventId = intval($row['idclie'] ?? 0);
        $estatus = intval($row['estatus'] ?? -1);
        $leadStatus = mapWpLeadStatusFromCalendarBackfill($estatus);

        if ($calendarioId <= 0) {
            continue;
        }

        $processed++;

        if ($eventId > 0) {
            $eventStmt = $conn->prepare('UPDATE eventos_wp SET estatus = ? WHERE id = ? LIMIT 1');
            if ($eventStmt) {
                $eventStatusValue = (string) $estatus;
                $eventStmt->bind_param('si', $eventStatusValue, $eventId);
                if ($eventStmt->execute()) {
                    $eventsUpdated += max(0, intval($eventStmt->affected_rows));
                } else {
                    $warnings[] = 'Evento #' . $eventId . ': ' . $eventStmt->error;
                    error_log('WP status backfill event warning for evento #' . $eventId . ': ' . $eventStmt->error);
                }
                $eventStmt->close();
            } else {
                $warnings[] = 'Evento #' . $eventId . ': ' . $conn->error;
                error_log('WP status backfill event prepare warning for evento #' . $eventId . ': ' . $conn->error);
            }
        }

        if ($hasWpCitasTable) {
            $mirrorStmt = $conn->prepare('UPDATE wp_citas_leads SET lead_status = ?, estatus_cita = ?, descartado = 0 WHERE id_calendario = ?');
            if ($mirrorStmt) {
                $mirrorStmt->bind_param('ssi', $leadStatus, $leadStatus, $calendarioId);
                if ($mirrorStmt->execute()) {
                    $mirrorsUpdated += max(0, intval($mirrorStmt->affected_rows));
                } else {
                    $warnings[] = 'Calendario #' . $calendarioId . ': ' . $mirrorStmt->error;
                    error_log('WP status backfill mirror warning for calendario #' . $calendarioId . ': ' . $mirrorStmt->error);
                }
                $mirrorStmt->close();
            } else {
                $warnings[] = 'Calendario #' . $calendarioId . ': ' . $conn->error;
                error_log('WP status backfill mirror prepare warning for calendario #' . $calendarioId . ': ' . $conn->error);
            }
        }

        try {
            wpCitasSyncLeadByCalendarId($conn, $calendarioId);
        } catch (Exception $syncError) {
            $warnings[] = 'Sync calendario #' . $calendarioId . ': ' . $syncError->getMessage();
            error_log('WP status backfill sync warning for calendario #' . $calendarioId . ': ' . $syncError->getMessage());
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Backfill de estatus WP completado.',
        'processed' => $processed,
        'events_updated' => $eventsUpdated,
        'mirrors_updated' => $mirrorsUpdated,
        'warnings_count' => count($warnings),
        'warnings' => $warnings
    ]);
    exit;
} catch (Exception $e) {
    error_log('WP status backfill error: ' . $e->getMessage());
    @ $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
