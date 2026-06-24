<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['login']) || $_SESSION['login'] === false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/pltrace_chat_helper.php';

if (!function_exists('pltraceChatGetUnreadCounts') || !function_exists('tracerAppendChatMessagesToEvents')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo cargar pltrace_chat_helper.php. Verifique que el archivo esté completo en el servidor.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/calendario_estatus_historial_helper.php';
date_default_timezone_set('America/Mexico_City');
$usuarioId = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
$action = isset($_REQUEST['action']) ? trim((string) $_REQUEST['action']) : '';

function pltrace_tracer_json_fail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'chat_send') {
    $tablaOrigen = isset($_POST['tabla']) ? trim((string) $_POST['tabla']) : '';
    $origId = isset($_POST['orig_id']) ? (int) $_POST['orig_id'] : 0;
    $cfId = isset($_POST['cf_id']) ? (int) $_POST['cf_id'] : 0;
    $mensaje = isset($_POST['mensaje']) ? trim((string) $_POST['mensaje']) : '';

    if ($tablaOrigen === '' || $origId <= 0) {
        pltrace_tracer_json_fail(400, 'Parámetros inválidos');
    }

    $result = pltraceChatSendMessage($conn, $tablaOrigen, $origId, $usuarioId, $mensaje, $cfId);
    $conn->close();

    if (empty($result['success'])) {
        pltrace_tracer_json_fail(400, $result['error'] ?? 'No se pudo enviar el mensaje');
    }

    echo json_encode([
        'success'    => true,
        'message_id' => $result['message_id'] ?? 0,
        'event'      => $result['event'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'chat_upload') {
    $tablaOrigen = isset($_POST['tabla']) ? trim((string) $_POST['tabla']) : '';
    $origId = isset($_POST['orig_id']) ? (int) $_POST['orig_id'] : 0;
    $cfId = isset($_POST['cf_id']) ? (int) $_POST['cf_id'] : 0;
    $file = isset($_FILES['archivo']) ? $_FILES['archivo'] : null;

    if ($tablaOrigen === '' || $origId <= 0) {
        pltrace_tracer_json_fail(400, 'Parámetros inválidos');
    }
    if (!is_array($file)) {
        pltrace_tracer_json_fail(400, 'No se recibió ningún archivo');
    }

    $result = pltraceChatUploadFile($conn, $tablaOrigen, $origId, $usuarioId, $file, $cfId);
    $conn->close();

    if (empty($result['success'])) {
        pltrace_tracer_json_fail(400, $result['error'] ?? 'No se pudo cargar el archivo');
    }

    echo json_encode([
        'success'    => true,
        'message_id' => $result['message_id'] ?? 0,
        'event'      => $result['event'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'chat_mark_read') {
    $tablaOrigen = isset($_POST['tabla']) ? trim((string) $_POST['tabla']) : '';
    $origId = isset($_POST['orig_id']) ? (int) $_POST['orig_id'] : 0;

    if ($tablaOrigen === '' || $origId <= 0 || $usuarioId <= 0) {
        pltrace_tracer_json_fail(400, 'Parámetros inválidos');
    }

    $result = pltraceChatMarkConversationRead($conn, $tablaOrigen, $origId, $usuarioId);
    $conn->close();

    if (empty($result['success'])) {
        pltrace_tracer_json_fail(400, $result['error'] ?? 'No se pudo marcar como leído');
    }

    echo json_encode([
        'success' => true,
        'marked'  => (int) ($result['marked'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'chat_unread_batch') {
    $traceKeys = [];
    if (isset($_POST['trace_keys']) && is_array($_POST['trace_keys'])) {
        $traceKeys = $_POST['trace_keys'];
    } elseif (isset($_POST['trace_keys']) && is_string($_POST['trace_keys'])) {
        $decoded = json_decode($_POST['trace_keys'], true);
        if (is_array($decoded)) {
            $traceKeys = $decoded;
        }
    }

    $counts = pltraceChatGetUnreadCounts($conn, $usuarioId, $traceKeys);
    $conn->close();

    echo json_encode([
        'success' => true,
        'counts'  => $counts,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'chat_poll') {
    $tablaOrigen = isset($_GET['tabla']) ? trim((string) $_GET['tabla']) : '';
    $origId = isset($_GET['orig_id']) ? (int) $_GET['orig_id'] : 0;
    $sinceId = isset($_GET['since_id']) ? (int) $_GET['since_id'] : 0;

    if ($tablaOrigen === '' || $origId <= 0) {
        pltrace_tracer_json_fail(400, 'Parámetros inválidos');
    }

    $validation = pltraceChatValidateTablaOrigen($conn, $tablaOrigen);
    if (empty($validation['ok'])) {
        pltrace_tracer_json_fail(400, $validation['message'] ?? 'Tabla no permitida');
    }

    $newEvents = pltraceChatFetchMessagesForLead($conn, $tablaOrigen, $origId, $usuarioId, $sinceId);
    $lastId = pltraceChatGetLastMessageId($conn, $tablaOrigen, $origId);

    $cleanEvents = [];
    foreach ($newEvents as $ev) {
        unset($ev['sort_ts'], $ev['sort_order']);
        $cleanEvents[] = $ev;
    }

    $conn->close();

    echo json_encode([
        'success'  => true,
        'events'   => $cleanEvents,
        'last_id'  => $lastId,
        'since_id' => $sinceId,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$contactFormId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$tablaOrigen   = isset($_GET['tabla']) ? trim((string) $_GET['tabla']) : '';
$origId        = isset($_GET['orig_id']) ? (int) $_GET['orig_id'] : 0;

if ($contactFormId <= 0 && ($tablaOrigen === '' || $origId <= 0)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = obtenerTrazabilidadLead($conn, [
    'contact_form_id' => $contactFormId,
    'tabla_origen'    => $tablaOrigen,
    'orig_id'         => $origId,
    'current_user_id' => $usuarioId,
]);
$conn->close();

if (empty($result['success'])) {
    http_response_code(404);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
