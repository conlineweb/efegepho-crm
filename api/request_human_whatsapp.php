<?php
// API: request_human_whatsapp.php
// Marks a WhatsApp conversation as "human handoff requested" by writing a flag to whatsapp/sessions/*_meta.json

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$response = ['success' => false, 'message' => '', 'data' => []];

$rawBody = file_get_contents('php://input');
$decodedJson = null;
if (!empty($rawBody)) {
    $json = json_decode($rawBody, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $decodedJson = $json;
    }
}

$requestParams = [];
if (!empty($_GET)) {
    $requestParams = $_GET;
} elseif (!empty($_POST)) {
    $requestParams = $_POST;
} elseif (!empty($decodedJson)) {
    $requestParams = $decodedJson;
}

$client_phone = null;
if (isset($requestParams['payload']['client_phone'])) {
    $client_phone = $requestParams['payload']['client_phone'];
} elseif (isset($requestParams['call_metadata']['user_phone_number'])) {
    $client_phone = $requestParams['call_metadata']['user_phone_number'];
} elseif (isset($requestParams['client_phone'])) {
    $client_phone = $requestParams['client_phone'];
} elseif (isset($requestParams['phone'])) {
    $client_phone = $requestParams['phone'];
} elseif (isset($requestParams['telefono'])) {
    $client_phone = $requestParams['telefono'];
}

$reason = '';
if (isset($requestParams['payload']['reason'])) {
    $reason = (string)$requestParams['payload']['reason'];
} elseif (isset($requestParams['reason'])) {
    $reason = (string)$requestParams['reason'];
}

$client_phone = is_string($client_phone) ? trim(urldecode($client_phone)) : '';

function normalize_phone_digits($phone): string {
    if (empty($phone)) return '';
    return preg_replace('/\D+/', '', $phone);
}

$digits = normalize_phone_digits($client_phone);
if ($digits === '') {
    $response['success'] = false;
    $response['message'] = 'No se proporcionó un número de teléfono válido. Se esperaba payload.client_phone o campos equivalentes.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Store meta alongside whatsapp_webhook sessions
$sessionDir = realpath(__DIR__ . '/../whatsapp/sessions');
if ($sessionDir === false) {
    $response['success'] = false;
    $response['message'] = 'No se encontró la carpeta de sesiones.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$metaFile = $sessionDir . DIRECTORY_SEPARATOR . 'whatsapp_' . $digits . '_meta.json';
$meta = [];
if (is_file($metaFile)) {
    $raw = @file_get_contents($metaFile);
    $meta = json_decode((string)$raw, true);
    if (!is_array($meta)) $meta = [];
}

$meta['human_handoff'] = true;
$meta['human_handoff_requested_at'] = date('c');
if ($reason !== '') {
    $meta['human_handoff_reason'] = $reason;
}

$ok = @file_put_contents($metaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
if ($ok === false) {
    $response['success'] = false;
    $response['message'] = 'No se pudo guardar el meta de la sesión.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$response['success'] = true;
$response['message'] = 'Handoff a agente marcado.';
$response['data'] = [
    'digits' => $digits,
    'meta_file' => basename($metaFile),
    'human_handoff' => true,
    'human_handoff_requested_at' => $meta['human_handoff_requested_at'],
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
