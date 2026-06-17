<?php
// Cron script to send scheduled marketing template emails
// Run every minute

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conn.php';
require_once __DIR__ . '/lib_marketing_template_email.php';

$logFile = __DIR__ . '/cron_send_marketing_templates.log';
function log_cron($msg, $logFile) {
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$ts] $msg\n", FILE_APPEND);
}

date_default_timezone_set('Mexico/General');

$MAX_PER_RUN = 100;
$RETRY_MINUTES = 60;

$now = date('Y-m-d H:i:s');
log_cron('cron start: now=' . $now, $logFile);

$sql = "SELECT q.id, q.template_id, q.tabla_origen, q.lead_id, q.sent_count, t.schedule_every_days, t.schedule_time, t.schedule_repeat
        FROM marketing_template_queue q
        INNER JOIN marketing_templates t ON t.id = q.template_id
        WHERE q.status IN ('pending','error') AND q.scheduled_at <= ?
        ORDER BY q.scheduled_at ASC
        LIMIT {$MAX_PER_RUN}";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "Error preparando consulta: " . $conn->error;
    exit;
}
$stmt->bind_param('s', $now);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

log_cron('cron run: now=' . $now . ' rows=' . count($rows), $logFile);
if (count($rows) === 0) {
    log_cron('cron run: no pending rows (status pending/error <= now).', $logFile);
}

$sentCount = 0;
$errorCount = 0;

foreach ($rows as $row) {
    $queueId = intval($row['id']);
    $templateId = intval($row['template_id']);
    $tablaOrigen = $row['tabla_origen'];
    $leadId = intval($row['lead_id']);
    $sentCountRow = intval($row['sent_count'] ?? 0);
    $scheduleDays = max(1, intval($row['schedule_every_days'] ?? 0));
    $scheduleTime = $row['schedule_time'] ?? '09:00:00';
    $scheduleRepeat = intval($row['schedule_repeat'] ?? 0);

    // Si el cliente ya abrió un correo de esta plantilla, cancelar y no enviar más
    $stmtOpen = $conn->prepare("SELECT id FROM email_opens WHERE tabla_origen = ? AND lead_id = ? AND template_id = ? LIMIT 1");
    if ($stmtOpen) {
        $stmtOpen->bind_param('sii', $tablaOrigen, $leadId, $templateId);
        $stmtOpen->execute();
        $stmtOpen->store_result();
        $alreadyOpened = ($stmtOpen->num_rows > 0);
        $stmtOpen->close();
        if ($alreadyOpened) {
            log_cron("Skipping - cliente ya vio el correo. template_id=$templateId tabla=$tablaOrigen lead_id=$leadId", $logFile);
            $updDone = $conn->prepare("UPDATE marketing_template_queue SET status = 'done', error_message = 'Cliente ya vio el correo' WHERE id = ?");
            $updDone->bind_param('i', $queueId);
            $updDone->execute();
            $updDone->close();
            continue;
        }
    }

    $result = send_marketing_template_email($conn, $templateId, $tablaOrigen, $leadId, null);
    $resultEmail = $result['email'] ?? '';

    if (!empty($result['success'])) {
        log_cron("sent OK template_id=$templateId tabla=$tablaOrigen lead_id=$leadId email=$resultEmail", $logFile);
        $sentCount++;
        $sentCountRow++;
        $lastSent = date('Y-m-d H:i:s');

        if ($scheduleRepeat === 1) {
            // compute next schedule
            $next = new DateTime('now');
            $next->modify('+' . $scheduleDays . ' days');
            list($hh, $mm, $ss) = array_pad(explode(':', $scheduleTime), 3, '00');
            $next->setTime((int) $hh, (int) $mm, (int) $ss);
            $nextAt = $next->format('Y-m-d H:i:s');

            $upd = $conn->prepare("UPDATE marketing_template_queue SET status = 'pending', scheduled_at = ?, last_sent_at = ?, sent_count = ?, error_message = NULL WHERE id = ?");
            $upd->bind_param('ssii', $nextAt, $lastSent, $sentCountRow, $queueId);
            $upd->execute();
            $upd->close();
        } else {
            $upd = $conn->prepare("UPDATE marketing_template_queue SET status = 'sent', last_sent_at = ?, sent_count = ?, error_message = NULL WHERE id = ?");
            $upd->bind_param('sii', $lastSent, $sentCountRow, $queueId);
            $upd->execute();
            $upd->close();
        }
    } else {
        $errorCount++;
        $lastError = $result['mail_error'] ?? ($result['message'] ?? 'Error enviando correo');
        log_cron("sent FAIL template_id=$templateId tabla=$tablaOrigen lead_id=$leadId email=$resultEmail error=" . $lastError, $logFile);
        $retryAt = new DateTime('now');
        $retryAt->modify('+' . $RETRY_MINUTES . ' minutes');
        $retryAtStr = $retryAt->format('Y-m-d H:i:s');

        $upd = $conn->prepare("UPDATE marketing_template_queue SET status = 'error', error_message = ?, scheduled_at = ? WHERE id = ?");
        $upd->bind_param('ssi', $lastError, $retryAtStr, $queueId);
        $upd->execute();
        $upd->close();
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'processed' => count($rows),
    'sent' => $sentCount,
    'errors' => $errorCount
]);

$conn->close();
