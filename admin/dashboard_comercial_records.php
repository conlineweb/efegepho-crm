<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function dashRecordsJsonResponse(int $code, array $payload): void
{
    http_response_code($code);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $json = json_encode(['error' => 'No se pudo serializar la respuesta']);
    }
    echo $json;
    exit;
}

if (!isset($_SESSION['login']) || $_SESSION['login'] === false) {
    dashRecordsJsonResponse(403, ['error' => 'No autorizado']);
}

$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$vendorId = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : null;
if ($vendorId !== null && $vendorId <= 0) {
    $vendorId = null;
}

$allowed = ['clientes', 'atendidos', 'agendas', 'todos', 'leads'];
if (!in_array($type, $allowed, true)) {
    dashRecordsJsonResponse(400, ['error' => 'Tipo de registro no válido']);
}

try {
    require_once __DIR__ . '/dashboard_comercial_period_helper.php';
    require_once __DIR__ . '/conn.php';
    require_once __DIR__ . '/dashboard_comercial_helper.php';

    list($startDate, $endDate) = dashComercialResolvePeriodDates($startDate, $endDate);

    if ($vendorId !== null && in_array($type, ['todos', 'agendas'], true)) {
        $vendorModal = dashComercialFetchVendorModalSections($conn, $startDate, $endDate, $vendorId);
        $payload = [
            'type'     => $type,
            'total'    => count($vendorModal['rows']),
            'rows'     => $vendorModal['rows'],
            'sections' => $vendorModal['sections'],
            'summary'  => $vendorModal['summary'],
        ];
    } else {
        $rows = dashComercialFetchRecordsByType($conn, $type, $startDate, $endDate, $vendorId);
        $payload = [
            'type'  => $type,
            'total' => count($rows),
            'rows'  => $rows,
        ];
    }

    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

    dashRecordsJsonResponse(200, $payload);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    dashRecordsJsonResponse(500, [
        'error' => 'Error al cargar registros: ' . $e->getMessage(),
    ]);
}
