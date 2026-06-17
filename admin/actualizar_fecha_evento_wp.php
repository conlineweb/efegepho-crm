<?php
include 'conn.php';

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

function respondJson($payload, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function normalizeDateTimeValue($rawValue)
{
    $normalized = trim((string) $rawValue);
    if ($normalized === '') {
        return null;
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function formatDateTimeDisplay($rawValue)
{
    $normalized = trim((string) $rawValue);
    if ($normalized === '') {
        return '';
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return $normalized;
    }

    return date('d/m/Y H:i', $timestamp);
}

function formatDateTimeInput($rawValue)
{
    $normalized = trim((string) $rawValue);
    if ($normalized === '') {
        return '';
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp);
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$field = isset($_POST['field']) ? trim((string) $_POST['field']) : '';
$value = isset($_POST['value']) ? trim((string) $_POST['value']) : '';

$allowedFields = [
    'fecha_registro' => 'Fecha de registro',
    'fecha_actualizacion_estatus' => 'Fecha de actualización'
];

if ($id <= 0) {
    respondJson(['success' => false, 'message' => 'Evento inválido.'], 400);
}

if (!isset($allowedFields[$field])) {
    respondJson(['success' => false, 'message' => 'Campo no permitido.'], 400);
}

$normalizedDate = normalizeDateTimeValue($value);
if ($normalizedDate === null) {
    respondJson(['success' => false, 'message' => 'Fecha inválida.'], 400);
}

try {
    $conn->begin_transaction();

    $sql = "UPDATE eventos_wp SET {$field} = ? WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualización del evento: ' . $conn->error);
    }

    $stmt->bind_param('si', $normalizedDate, $id);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar la fecha del evento: ' . $stmt->error);
    }
    $stmt->close();

    if ($field === 'fecha_registro') {
        $tablesResult = $conn->query("SHOW TABLES LIKE 'wp_eventos_afianzados'");
        if ($tablesResult && $tablesResult->num_rows > 0) {
            $callDate = date('Y-m-d', strtotime($normalizedDate));
            $mirrorStmt = $conn->prepare('UPDATE wp_eventos_afianzados SET fecha_registro_evento = ?, choose_your_call_date_ = ? WHERE id_evento = ?');
            if (!$mirrorStmt) {
                throw new Exception('No se pudo preparar la actualización del lead afianzado: ' . $conn->error);
            }

            $mirrorStmt->bind_param('ssi', $normalizedDate, $callDate, $id);
            if (!$mirrorStmt->execute()) {
                throw new Exception('No se pudo sincronizar la fecha de registro del lead afianzado: ' . $mirrorStmt->error);
            }
            $mirrorStmt->close();
        }
    }

    $conn->commit();

    respondJson([
        'success' => true,
        'message' => $allowedFields[$field] . ' actualizada correctamente.',
        'field' => $field,
        'raw_value' => $normalizedDate,
        'display_value' => formatDateTimeDisplay($normalizedDate),
        'input_value' => formatDateTimeInput($normalizedDate)
    ]);
} catch (Exception $e) {
    $conn->rollback();
    respondJson(['success' => false, 'message' => $e->getMessage()], 500);
}
