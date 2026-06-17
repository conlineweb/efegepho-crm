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
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

$leadId = isset($_POST['leadId']) ? (int)$_POST['leadId'] : 0;
$tablaOrigen = isset($_POST['tablaOrigen']) ? trim((string)$_POST['tablaOrigen']) : '';

if ($leadId <= 0 || $tablaOrigen === '') {
    json_fail(400, 'Parámetros inválidos');
}

try {
    // Validate table name against tablas_leads to avoid SQL injection.
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
    // If this fails, just proceed without country_code.
    $hasCountryCode = false;
}

// Fetch lead phone and name.
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

// Build E.164-ish digits. Prefer stored phone if it already includes a country code.
$digits = $digitsPhone;
if ($digitsCountry !== '' && $digitsPhone !== '') {
    // If phone doesn't start with country_code and seems like a local number, prepend it.
    if (strpos($digitsPhone, $digitsCountry) !== 0 && strlen($digitsPhone) <= 11) {
        $digits = $digitsCountry . $digitsPhone;
    }
}

// Enforce Mexico WhatsApp format: 52 + 1 + 10 digits (521XXXXXXXXXX)
if (preg_match('/^52\d{10}$/', $digits)) {
    // Missing the required "1" after country code.
    $digits = '521' . substr($digits, 2);
} elseif (preg_match('/^52(?!1)\d{10,}$/', $digits)) {
    // Defensive: if it starts with 52 but not 521, force last 10 digits.
    $digits = '521' . substr($digits, -10);
}

$sessionDir = realpath(__DIR__ . '/../../whatsapp/sessions');
if ($sessionDir === false) {
    json_fail(500, 'No se encontró la carpeta de sesiones');
}

$preferredDigits = $digits;
$preferredFile = "whatsapp_{$preferredDigits}.json";
$preferredPath = $sessionDir . DIRECTORY_SEPARATOR . $preferredFile;

// Meta file (handoff / entities) lives next to the session JSON
$preferredMetaPath = $sessionDir . DIRECTORY_SEPARATOR . "whatsapp_{$preferredDigits}_meta.json";

$chosenPath = null;
$chosenFile = null;
$matchedDigits = null;
$metaPath = null;

// Primary: exact match to preferred digits.
if (is_file($preferredPath)) {
    $chosenPath = $preferredPath;
    $chosenFile = $preferredFile;
    $matchedDigits = $preferredDigits;
    if (is_file($preferredMetaPath)) {
        $metaPath = $preferredMetaPath;
    }
} else {
    // Fallback: scan sessions for a match. Exclude *_meta.json and prefer Mexico numbers with the "1".
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

            // Fallback candidate if we didn't find exact: allow match on last 10 digits.
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

// Read meta if available (handoff flag)
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

if ($chosenPath === null) {
    echo json_encode([
        'success' => true,
        'found' => false,
        'lead' => [
            'id' => $leadId,
            'tabla_origen' => $tablaOrigen,
            'full_name' => $leadName,
            'phone' => $preferredDigits,
        ],
        'handoff' => [
            'enabled' => $humanHandoff,
            'requested_at' => $humanHandoffAt,
        ],
        'message' => 'No se encontró historial para este número'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = @file_get_contents($chosenPath);
if ($raw === false) {
    json_fail(500, 'No se pudo leer el archivo de sesión');
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    json_fail(500, 'El archivo de sesión no es JSON válido');
}

$messages = [];
$__last_wa_key = null; // avoid consecutive duplicates
foreach ($data as $item) {
    if (!is_array($item)) {
        continue;
    }
    $role = isset($item['role']) ? (string)$item['role'] : '';
    $content = $item['content'] ?? '';

    if ($role === 'tool') {
        continue; // never shown as chat
    }

    if ($content === null) {
        continue;
    }

    $contentStr = trim((string)$content);
    if ($contentStr === '') {
        continue;
    }

    // server-side dedupe for consecutive identical entries
    $key = $role . '|' . $contentStr;
    if ($key === $__last_wa_key) {
        continue;
    }
    $__last_wa_key = $key;

    $messages[] = [
        'role' => $role,
        'content' => $contentStr,
    ];
}

echo json_encode([
    'success' => true,
    'found' => true,
    'lead' => [
        'id' => $leadId,
        'tabla_origen' => $tablaOrigen,
        'full_name' => $leadName,
        'phone' => $matchedDigits,
    ],
    'handoff' => [
        'enabled' => $humanHandoff,
        'requested_at' => $humanHandoffAt,
    ],
    'file' => $chosenFile,
    'messages' => $messages,
], JSON_UNESCAPED_UNICODE);
