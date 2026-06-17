<?php
header('Content-Type: application/json; charset=utf-8');
include 'conn.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$userid = $_SESSION['uid'] ?? null;

// Expect template_ids[] (array), tabla_origen, lead_id
$templateIds = [];
if (isset($_POST['template_ids']) && is_array($_POST['template_ids'])) {
    foreach ($_POST['template_ids'] as $v) {
        $v = trim((string)$v);
        if ($v !== '' && ctype_digit($v)) $templateIds[] = intval($v);
    }
} elseif (isset($_POST['template_ids'])) {
    // single value
    $v = trim((string)$_POST['template_ids']);
    if ($v !== '' && ctype_digit($v)) $templateIds[] = intval($v);
}

$tabla = isset($_POST['tabla_origen']) ? trim((string)$_POST['tabla_origen']) : '';
$leadIdRaw = isset($_POST['lead_id']) ? trim((string)$_POST['lead_id']) : '';

if (empty($tabla) || empty($leadIdRaw) || !ctype_digit($leadIdRaw) || empty($templateIds)) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}
$leadId = intval($leadIdRaw);

// validate table exists
$check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tabla) . "'");
if (!($check && $check->num_rows > 0)) {
    echo json_encode(['success' => false, 'message' => 'Tabla origen inválida.']);
    exit;
}

$added = [];
foreach ($templateIds as $templateId) {
    // validate template exists and is scheduled
    $stmt = $conn->prepare("SELECT id, schedule_enabled, schedule_every_days, schedule_time, schedule_repeat, nombre FROM marketing_templates WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $templateId);
    $stmt->execute();
    $res = $stmt->get_result();
    $tpl = $res->fetch_assoc();
    $stmt->close();
    if (!$tpl) continue;
    // Note: assign regardless of schedule_enabled so manual bulk-assign always works

    // compute scheduled_at (same logic as schedule_template_email.php)
    $scheduleDays = max(1, intval($tpl['schedule_every_days'] ?? 0));
    $scheduleTime = $tpl['schedule_time'] ?? '09:00:00';
    list($hh, $mm, $ss) = array_pad(explode(':', $scheduleTime), 3, '00');
    $now = new DateTime('now');
    $target = new DateTime('now');
    $target->setTime((int)$hh, (int)$mm, (int)$ss);
    $target->modify('+' . $scheduleDays . ' days');
    $scheduledAt = $target->format('Y-m-d H:i:s');

    // avoid duplicate queue entry for same template+lead
    $chk = $conn->prepare("SELECT id FROM marketing_template_queue WHERE tabla_origen = ? AND lead_id = ? AND template_id = ? LIMIT 1");
    $chk->bind_param('sii', $tabla, $leadId, $templateId);
    $chk->execute();
    $rchk = $chk->get_result();
    $exists = $rchk->fetch_assoc();
    $chk->close();
    if ($exists) continue;

    $status = 'pending';
    $createdBy = $userid ? intval($userid) : null;
    $ins = $conn->prepare("INSERT INTO marketing_template_queue (template_id, tabla_origen, lead_id, status, scheduled_at, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$ins) continue;
    $ins->bind_param('isissi', $templateId, $tabla, $leadId, $status, $scheduledAt, $createdBy);
    $ok = $ins->execute();
    $ins->close();
    if ($ok) $added[] = $templateId;
}

echo json_encode(['success' => true, 'added' => $added]);
$conn->close();
