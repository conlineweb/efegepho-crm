<?php
include 'conn.php';
require_once __DIR__ . '/wp_citas_leads_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $calendarIds = [];
    $result = $conn->query('SELECT id FROM calendario WHERE tipo = 1');
    if (!$result) {
        throw new Exception('No se pudieron consultar las citas WP existentes: ' . $conn->error);
    }

    while ($row = $result->fetch_assoc()) {
        $calendarId = intval($row['id'] ?? 0);
        if ($calendarId > 0) {
            $calendarIds[] = $calendarId;
        }
    }

    $conn->begin_transaction();

    $synced = 0;
    foreach ($calendarIds as $calendarId) {
        $mirrorId = wpCitasSyncLeadByCalendarId($conn, $calendarId);
        if ($mirrorId !== false) {
            $synced++;
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Backfill completado correctamente.',
        'calendar_rows' => count($calendarIds),
        'mirrored_rows' => $synced
    ]);
    exit;
} catch (Exception $e) {
    @ $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
