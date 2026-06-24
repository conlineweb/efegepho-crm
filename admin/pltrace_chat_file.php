<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['login']) || $_SESSION['login'] === false) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No autorizado';
    exit;
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/pltrace_chat_helper.php';

$messageId = isset($_GET['msg_id']) ? (int) $_GET['msg_id'] : 0;
$forceDownload = isset($_GET['download']) && (string) $_GET['download'] === '1';
$inline = isset($_GET['inline']) && (string) $_GET['inline'] === '1';

if ($messageId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Parámetros inválidos';
    exit;
}

$result = pltraceChatGetFileRecordByMessageId($conn, $messageId);
$conn->close();

if (empty($result['success'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo $result['error'] ?? 'Archivo no encontrado';
    exit;
}

$row = $result['row'];
$filePath = $result['absolute_path'];
$mimeType = (string) ($row['archivo_mime_type'] ?? 'application/octet-stream');
$categoria = (string) ($row['archivo_categoria'] ?? 'other');
$originalName = (string) ($row['archivo_nombre_original'] ?? 'archivo');
$sizeBytes = (int) ($row['archivo_tamano_bytes'] ?? 0);

$disposition = 'attachment';
if (!$forceDownload && ($inline || in_array($categoria, ['image', 'video', 'pdf'], true))) {
    $disposition = 'inline';
}

$safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $originalName);
if ($safeName === '') {
    $safeName = 'archivo';
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

readfile($filePath);
exit;
