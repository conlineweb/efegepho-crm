<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Same auth check used by admin/menu.php
if (!isset($_SESSION['login']) || $_SESSION['login'] === false) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../conn.php';

function json_fail(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$leadId = isset($_POST['leadId']) ? (int)$_POST['leadId'] : 0;
$tablaOrigen = isset($_POST['tablaOrigen']) ? trim((string)$_POST['tablaOrigen']) : '';
$message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';

if ($leadId <= 0 || $tablaOrigen === '' || $message === '') {
    json_fail(400, 'Parámetros inválidos');
}

// Validate table name against tablas_leads to avoid SQL injection.
try {
    $checkStmt = $conn->prepare('SELECT nombre FROM tablas_leads WHERE nombre = ? LIMIT 1');
    if (!$checkStmt) {
        json_fail(500, 'No se pudo validar la tabla');
    }
    $checkStmt->bind_param('s', $tablaOrigen);
    $checkStmt->execute();
    $checkRes = $checkStmt->get_result();
    $found = $checkRes ? $checkRes->fetch_assoc() : null;
    $checkStmt->close();
} catch (Throwable $e) {
    json_fail(500, 'Error al validar la tabla');
}

if (!$found) {
    json_fail(400, 'Tabla no permitida');
}

// Detect optional columns safely.
$hasCountryCode = false;
try {
    $colRes = $conn->query("SHOW COLUMNS FROM `{$tablaOrigen}` LIKE 'country_code'");
    if ($colRes && $colRes->num_rows > 0) {
        $hasCountryCode = true;
    }
} catch (Throwable $e) {
    $hasCountryCode = false;
}

$selectFields = 'phone, full_name';
if ($hasCountryCode) {
    $selectFields .= ', country_code';
}

$sql = "SELECT {$selectFields} FROM `{$tablaOrigen}` WHERE id = ? LIMIT 1";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        json_fail(500, 'No se pudo consultar el lead');
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
} catch (Throwable $e) {
    json_fail(500, 'Error al consultar el lead');
}

if (!$row) {
    json_fail(404, 'Lead no encontrado');
}

$rawPhone = (string)($row['phone'] ?? '');
$rawCountry = (string)($row['country_code'] ?? '');
$leadName = (string)($row['full_name'] ?? '');

$digitsPhone = preg_replace('/\D+/', '', $rawPhone);
$digitsCountry = preg_replace('/\D+/', '', $rawCountry);

$digits = $digitsPhone;
if ($digitsCountry !== '' && $digitsPhone !== '') {
    if (strpos($digitsPhone, $digitsCountry) !== 0 && strlen($digitsPhone) <= 11) {
        $digits = $digitsCountry . $digitsPhone;
    }
}

// Enforce Mexico WhatsApp format: 52 + 1 + 10 digits (521XXXXXXXXXX)
if (preg_match('/^52\d{10}$/', $digits)) {
    $digits = '521' . substr($digits, 2);
} elseif (preg_match('/^52(?!1)\d{10,}$/', $digits)) {
    $digits = '521' . substr($digits, -10);
}

if ($digits === '') {
    json_fail(400, 'No se encontró teléfono válido para el lead');
}

$toE164 = '+' . $digits;

$TWILIO_ACCOUNT_SID = getenv('TWILIO_ACCOUNT_SID') ?: 'AC15ac8ca17a42aa5546b1a7fc6485a485';
$TWILIO_AUTH_TOKEN = getenv('TWILIO_AUTH_TOKEN') ?: '428434eee0884451fbc2d0817c525c86';
// Debe incluir el prefijo whatsapp: ejemplo: 'whatsapp:+1415XXXXXXX'
$TWILIO_FROM = getenv('TWILIO_FROM') ?: 'whatsapp:+15558252950';

if ($TWILIO_ACCOUNT_SID === '' || $TWILIO_AUTH_TOKEN === '' || $TWILIO_FROM === '') {
    json_fail(500, 'Twilio no está configurado (TWILIO_ACCOUNT_SID/TWILIO_AUTH_TOKEN/TWILIO_FROM)');
}

function twilio_send_whatsapp_text(string $sid, string $token, string $from, string $to, string $body): array {
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
    $post = http_build_query([
        'From' => $from,
        'To' => 'whatsapp:' . $to,
        'Body' => $body,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $http,
        'error' => $err,
        'raw' => $resp,
    ];
}

$send = twilio_send_whatsapp_text($TWILIO_ACCOUNT_SID, $TWILIO_AUTH_TOKEN, $TWILIO_FROM, $toE164, $message);
if ($send['http_code'] < 200 || $send['http_code'] >= 300) {
    json_fail(502, 'No se pudo enviar el mensaje por Twilio');
}

// Append to local conversation log so the modal shows it.
$sessionDir = realpath(__DIR__ . '/../../whatsapp/sessions');
if ($sessionDir !== false) {
    $sessionFile = $sessionDir . DIRECTORY_SEPARATOR . 'whatsapp_' . $digits . '.json';
    $metaFile = $sessionDir . DIRECTORY_SEPARATOR . 'whatsapp_' . $digits . '_meta.json';

    $messages = [];
    if (is_file($sessionFile)) {
        $messages = json_decode((string)@file_get_contents($sessionFile), true);
        if (!is_array($messages)) $messages = [];
    }

    // Ensure system prompt is present as first message for consistency.
    if (empty($messages) || !isset($messages[0]['role']) || $messages[0]['role'] !== 'system') {
        $promptPath = realpath(__DIR__ . '/../../whatsapp/prompt_efege.txt');
        $systemPrompt = ($promptPath && is_file($promptPath)) ? (string)@file_get_contents($promptPath) : 'Eres Alexa de Efege...';
        array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);
    }

    // Outbound message from an agent/admin
    $messages[] = ['role' => 'assistant', 'content' => $message];
    @file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

    // Update meta so the UI can decide whether the lead still needs attention.
    $lastInboundIdx = -1;
    foreach ($messages as $i => $item) {
        if (!is_array($item)) continue;
        $role = isset($item['role']) ? (string)$item['role'] : '';
        $content = isset($item['content']) ? trim((string)$item['content']) : '';
        if ($content === '') continue;
        if ($role === 'user') {
            $lastInboundIdx = (int)$i;
        }
    }

    $meta = [];
    if (is_file($metaFile)) {
        $mraw = @file_get_contents($metaFile);
        $meta = json_decode((string)$mraw, true);
        if (!is_array($meta)) $meta = [];
    }
    $meta['agent_last_sent_inbound_idx'] = $lastInboundIdx;
    $meta['agent_last_sent_at'] = gmdate('c');
    @file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

echo json_encode([
    'success' => true,
    'message' => 'Mensaje enviado',
    'data' => [
        'lead' => [
            'id' => $leadId,
            'tabla_origen' => $tablaOrigen,
            'full_name' => $leadName,
        ],
        'to' => $toE164,
    ]
], JSON_UNESCAPED_UNICODE);
