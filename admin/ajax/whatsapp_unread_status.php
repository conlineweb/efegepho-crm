<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

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

if ($leadId <= 0 || $tablaOrigen === '') {
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

$sessionDir = realpath(__DIR__ . '/../../whatsapp/sessions');
if ($sessionDir === false) {
    json_fail(500, 'No se encontró la carpeta de sesiones');
}

$preferredDigits = $digits;
$preferredFile = "whatsapp_{$preferredDigits}.json";
$preferredPath = $sessionDir . DIRECTORY_SEPARATOR . $preferredFile;
$preferredMetaPath = $sessionDir . DIRECTORY_SEPARATOR . "whatsapp_{$preferredDigits}_meta.json";

$chosenPath = null;
$chosenFile = null;
$matchedDigits = null;
$metaPath = null;

if (is_file($preferredPath)) {
    $chosenPath = $preferredPath;
    $chosenFile = $preferredFile;
    $matchedDigits = $preferredDigits;
    if (is_file($preferredMetaPath)) {
        $metaPath = $preferredMetaPath;
    }
} else {
    $files = glob($sessionDir . DIRECTORY_SEPARATOR . 'whatsapp_*.json');
    if ($files !== false) {
        $fallbackPath = null;
        $fallbackFile = null;
        $fallbackDigits = null;

        foreach ($files as $path) {
            $base = basename($path);
            if (substr($base, -9) === '_meta.json') {
                continue;
            }
            if (!preg_match('/^whatsapp_(\d+)\.json$/', $base, $m)) {
                continue;
            }
            $fileDigits = $m[1];

            // Strict preference: for Mexico numbers, ignore files without the "1" after 52.
            if (strpos($fileDigits, '52') === 0 && strpos($fileDigits, '521') !== 0) {
                continue;
            }

            if ($fileDigits === $preferredDigits) {
                $chosenPath = $path;
                $chosenFile = $base;
                $matchedDigits = $fileDigits;
                $candidateMeta = $sessionDir . DIRECTORY_SEPARATOR . "whatsapp_{$fileDigits}_meta.json";
                if (is_file($candidateMeta)) {
                    $metaPath = $candidateMeta;
                }
                break;
            }

            if ($fallbackPath === null && strlen($fileDigits) >= 10 && substr($fileDigits, -10) === substr($preferredDigits, -10)) {
                $fallbackPath = $path;
                $fallbackFile = $base;
                $fallbackDigits = $fileDigits;
            }
        }

        if ($chosenPath === null && $fallbackPath !== null) {
            $chosenPath = $fallbackPath;
            $chosenFile = $fallbackFile;
            $matchedDigits = $fallbackDigits;
            $candidateMeta = $sessionDir . DIRECTORY_SEPARATOR . "whatsapp_{$fallbackDigits}_meta.json";
            if (is_file($candidateMeta)) {
                $metaPath = $candidateMeta;
            }
        }
    }
}

// Read meta if available
$meta = [];
if ($metaPath === null && is_file($preferredMetaPath)) {
    $metaPath = $preferredMetaPath;
}
if ($metaPath !== null && is_file($metaPath)) {
    $mraw = @file_get_contents($metaPath);
    $meta = json_decode((string)$mraw, true);
    if (!is_array($meta)) $meta = [];
}

$humanHandoff = !empty($meta['human_handoff']);
$humanHandoffAt = isset($meta['human_handoff_requested_at']) ? (string)$meta['human_handoff_requested_at'] : '';

$messages = [];
if ($chosenPath !== null && is_file($chosenPath)) {
    $raw = @file_get_contents($chosenPath);
    $data = json_decode((string)$raw, true);
    if (is_array($data)) {
        $messages = $data;
    }
}

$inboundIndices = [];
if (is_array($messages)) {
    foreach ($messages as $i => $item) {
        if (!is_array($item)) continue;
        $role = isset($item['role']) ? (string)$item['role'] : '';
        $content = isset($item['content']) ? trim((string)$item['content']) : '';
        if ($content === '') continue;
        if ($role === 'user') {
            $inboundIndices[] = (int)$i;
        }
    }
}

$lastInboundIdx = !empty($inboundIndices) ? (int)$inboundIndices[count($inboundIndices) - 1] : -1;
$adminLastReadInboundIdx = isset($meta['admin_last_read_inbound_idx']) ? (int)$meta['admin_last_read_inbound_idx'] : -1;
$agentLastSentInboundIdx = isset($meta['agent_last_sent_inbound_idx']) ? (int)$meta['agent_last_sent_inbound_idx'] : -1;

$unreadCount = 0;
if (!empty($inboundIndices)) {
    foreach ($inboundIndices as $idx) {
        if ($idx > $adminLastReadInboundIdx) {
            $unreadCount++;
        }
    }
}

$needsAttention = ($humanHandoff && $lastInboundIdx > $agentLastSentInboundIdx);

echo json_encode([
    'success' => true,
    'lead' => [
        'id' => $leadId,
        'tabla_origen' => $tablaOrigen,
        'full_name' => $leadName,
        'phone' => $matchedDigits ?: $preferredDigits,
    ],
    'handoff' => [
        'enabled' => $humanHandoff,
        'requested_at' => $humanHandoffAt,
    ],
    'file' => $chosenFile,
    'found' => $chosenPath !== null,
    'unreadCount' => $unreadCount,
    'lastInboundIdx' => $lastInboundIdx,
    'adminLastReadInboundIdx' => $adminLastReadInboundIdx,
    'needsAttention' => $needsAttention,
], JSON_UNESCAPED_UNICODE);
