<?php
header('Content-Type: application/json; charset=utf-8');
include 'conn.php';

$logFile = __DIR__ . '/cron_send_marketing_templates.log';
function log_sched($msg, $logFile) {
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$ts] $msg\n", FILE_APPEND);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userid = $_SESSION['uid'] ?? null;

date_default_timezone_set('Mexico/General');

// Read raw inputs and validate strictly
$tplRaw = isset($_POST['template_id']) ? trim((string)$_POST['template_id']) : '';
$tablaRaw = isset($_POST['tabla_origen']) ? trim((string)$_POST['tabla_origen']) : '';
$leadRaw = isset($_POST['lead_id']) ? trim((string)$_POST['lead_id']) : '';

if ($tplRaw === '' || $tablaRaw === '' || $leadRaw === '') {
    log_sched('Invalid params - missing. POST: ' . json_encode($_POST), $logFile);
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros.']);
    exit;
}

// Require numeric template and lead ids
if (!ctype_digit($tplRaw) || !ctype_digit($leadRaw)) {
    log_sched('Invalid params - non-numeric ids. POST: ' . json_encode($_POST), $logFile);
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos: template_id y lead_id deben ser numéricos.']);
    exit;
}

$templateId = intval($tplRaw);
$leadId = intval($leadRaw);

// Validate table exists; allow minor cleaning if needed
$tablaOrigen = trim($tablaRaw);
$checkTable = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tablaOrigen) . "'");
if (!($checkTable && $checkTable->num_rows > 0)) {
    // try safe fallback removing non-alphanumeric/underscore
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $tablaRaw);
    if ($safe !== '') {
        $check2 = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($safe) . "'");
        if ($check2 && $check2->num_rows > 0) {
            $tablaOrigen = $safe;
        } else {
            log_sched("Invalid tabla_origen: raw='$tablaRaw' safe='$safe'", $logFile);
            echo json_encode(['success' => false, 'message' => 'Tabla inválida: ' . $tablaRaw]);
            exit;
        }
    } else {
        log_sched("Invalid tabla_origen: raw='$tablaRaw'", $logFile);
        echo json_encode(['success' => false, 'message' => 'Tabla inválida: ' . $tablaRaw]);
        exit;
    }
}

if ($templateId <= 0 || $leadId <= 0) {
    log_sched('Invalid params after parsing: templateId=' . $templateId . ' leadId=' . $leadId, $logFile);
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}
log_sched("schedule request template_id=$templateId tabla=$tablaOrigen lead_id=$leadId", $logFile);

// fetch template schedule
$stmt = $conn->prepare("SELECT id, nombre, schedule_enabled, schedule_every_days, schedule_time, schedule_repeat FROM marketing_templates WHERE id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta de plantilla.']);
    exit;
}
$stmt->bind_param('i', $templateId);
$stmt->execute();
$res = $stmt->get_result();
$template = $res->fetch_assoc();
$stmt->close();

if (!$template) {
    echo json_encode(['success' => false, 'message' => 'Plantilla no encontrada.']);
    exit;
}

$scheduleEnabled = intval($template['schedule_enabled'] ?? 0);
$scheduleDays = max(1, intval($template['schedule_every_days'] ?? 0));
$scheduleTime = $template['schedule_time'] ?? '09:00:00';
$scheduleRepeat = intval($template['schedule_repeat'] ?? 0);

if ($scheduleEnabled !== 1) {
    echo json_encode(['success' => false, 'message' => 'La plantilla no tiene programación activa.']);
    exit;
}

// compute next scheduled_at
$now = new DateTime('now');
list($hh, $mm, $ss) = array_pad(explode(':', $scheduleTime), 3, '00');
$target = new DateTime('now');
$target->setTime((int) $hh, (int) $mm, (int) $ss);

$target->modify('+' . $scheduleDays . ' days');

$scheduledAt = $target->format('Y-m-d H:i:s');
log_sched("computed scheduled_at=$scheduledAt days=$scheduleDays time=$scheduleTime", $logFile);

// Do NOT remove other scheduled templates for this lead — allow multiple templates per lead.
// Prevent duplicate same-template entries for the same lead.
$chk = $conn->prepare("SELECT id FROM marketing_template_queue WHERE tabla_origen = ? AND lead_id = ? AND template_id = ? LIMIT 1");
if ($chk) {
    $chk->bind_param('sii', $tablaOrigen, $leadId, $templateId);
    $chk->execute();
    $rchk = $chk->get_result();
    $exists = $rchk->fetch_assoc();
    $chk->close();
    if ($exists) {
        echo json_encode(['success' => false, 'message' => 'La plantilla ya está asignada a este lead.']);
        exit;
    }
}

$createdBy = $userid ? intval($userid) : null;
$status = 'pending';

$stmt2 = $conn->prepare("INSERT INTO marketing_template_queue (template_id, tabla_origen, lead_id, status, scheduled_at, created_by) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt2) {
    echo json_encode(['success' => false, 'message' => 'Error preparando actualización de cola.']);
    exit;
}
$stmt2->bind_param('isissi', $templateId, $tablaOrigen, $leadId, $status, $scheduledAt, $createdBy);
$ok = $stmt2->execute();
$stmt2->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'No se pudo programar el correo.']);
    exit;
}

log_sched("queued template_id=$templateId tabla=$tablaOrigen lead_id=$leadId scheduled_at=$scheduledAt", $logFile);

echo json_encode([
    'success' => true,
    'message' => 'Correo programado',
    'scheduled_at' => $scheduledAt,
    'repeat' => $scheduleRepeat ? 1 : 0
]);

$conn->close();
