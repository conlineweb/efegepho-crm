<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');

include 'conn.php';
require_once __DIR__ . '/wp_eventos_contact_form_helper.php';
require_once __DIR__ . '/evento_wp_post_helper.php';

function registroLeadJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function registroLeadFormatDisplay(int $fechaTs): string
{
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $month = $months[(int) date('n', $fechaTs) - 1];
    $day = date('j', $fechaTs);
    $year = date('Y', $fechaTs);
    $hour = date('g', $fechaTs);
    $min = date('i', $fechaTs);
    $ampm = date('A', $fechaTs) === 'AM' ? 'a.m.' : 'p.m.';

    return "$day de $month de $year a las $hour:$min $ampm";
}

function registroLeadTableHasColumn($conn, string $tableName, string $columnName): bool
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS c
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['c'] ?? 0) > 0;
}

function registroLeadValidateTableName(string $tableName): bool
{
    return $tableName !== '' && (bool) preg_match('/^[a-zA-Z0-9_]+$/', $tableName);
}

function registroLeadSyncContactForm($conn, int $originalLeadId, string $tablaOrigen, string $fechaRegistroDb, int $contactFormId = 0): void
{
    if ($originalLeadId <= 0 && $contactFormId <= 0) {
        return;
    }

    if ($contactFormId > 0) {
        $stmt = $conn->prepare('UPDATE contact_form SET created_time = ?, submission_date = ? WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ssi', $fechaRegistroDb, $fechaRegistroDb, $contactFormId);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    $stmt = $conn->prepare(
        "UPDATE contact_form
         SET created_time = ?, submission_date = ?
         WHERE original_lead_id = ?
           AND LOWER(TRIM(COALESCE(tabla_origen, ''))) = LOWER(?)
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('ssis', $fechaRegistroDb, $fechaRegistroDb, $originalLeadId, $tablaOrigen);
        $stmt->execute();
        $stmt->close();
    }
}

if (empty($_SESSION['login'])) {
    registroLeadJsonResponse(401, ['success' => false, 'message' => 'Sesión no válida.']);
}

$kind = strtolower(trim((string) ($_POST['kind'] ?? '')));
$tablaOrigen = trim((string) ($_POST['tabla_origen'] ?? ''));
$leadId = (int) ($_POST['lead_id'] ?? 0);
$eventId = (int) ($_POST['event_id'] ?? 0);
$contactFormId = (int) ($_POST['contact_form_id'] ?? 0);
$fecha = trim((string) ($_POST['fecha'] ?? ''));
$hora = trim((string) ($_POST['hora'] ?? ''));

if ($fecha === '') {
    registroLeadJsonResponse(400, ['success' => false, 'message' => 'La fecha es obligatoria.']);
}

$horaNormalized = $hora !== '' ? $hora : '00:00';
if (preg_match('/^\d{2}:\d{2}$/', $horaNormalized)) {
    $horaNormalized .= ':00';
}

$fechaTs = strtotime($fecha . ' ' . $horaNormalized);
if ($fechaTs === false || $fechaTs <= 0) {
    registroLeadJsonResponse(400, ['success' => false, 'message' => 'Fecha u hora inválida.']);
}

$fechaRegistroDb = date('Y-m-d H:i:s', $fechaTs);
$displayFormatted = registroLeadFormatDisplay($fechaTs);

try {
    if ($kind === 'eventos_wp') {
        $eventId = $eventId > 0 ? $eventId : $leadId;
        if ($eventId <= 0) {
            registroLeadJsonResponse(400, ['success' => false, 'message' => 'Evento de planner inválido.']);
        }

        wpEventEnsureTable($conn);
        wpEventEnsureStatusMilestoneColumns($conn);

        $stmt = $conn->prepare('SELECT id FROM eventos_wp WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new Exception('No se pudo validar el evento.');
        }
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $eventRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$eventRow) {
            registroLeadJsonResponse(404, ['success' => false, 'message' => 'Evento de Wedding Planner no encontrado.']);
        }

        $conn->begin_transaction();

        $sets = ['fecha_registro = ?'];
        $types = 's';
        $values = [$fechaRegistroDb];

        if (registroLeadTableHasColumn($conn, 'eventos_wp', 'created_time')) {
            $sets[] = 'created_time = ?';
            $types .= 's';
            $values[] = $fechaRegistroDb;
        }

        $types .= 'i';
        $values[] = $eventId;

        $sql = 'UPDATE eventos_wp SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('No se pudo preparar la actualización del evento.');
        }
        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo actualizar la fecha de registro del evento.');
        }
        $stmt->close();

        $tablesResult = $conn->query("SHOW TABLES LIKE 'wp_eventos_afianzados'");
        if ($tablesResult && $tablesResult->num_rows > 0) {
            $callDate = date('Y-m-d', $fechaTs);
            $mirrorStmt = $conn->prepare('UPDATE wp_eventos_afianzados SET fecha_registro_evento = ?, choose_your_call_date_ = ?, created_time = ? WHERE id_evento = ?');
            if ($mirrorStmt) {
                $mirrorStmt->bind_param('sssi', $fechaRegistroDb, $callDate, $fechaRegistroDb, $eventId);
                $mirrorStmt->execute();
                $mirrorStmt->close();
            }
        }

        registroLeadSyncContactForm($conn, $eventId, 'eventos_wp', $fechaRegistroDb, $contactFormId);

        $conn->commit();

        registroLeadJsonResponse(200, [
            'success' => true,
            'message' => 'Fecha de registro del evento actualizada.',
            'display' => $displayFormatted,
            'data_order' => $fechaTs,
        ]);
    }

    if ($kind !== 'lead') {
        registroLeadJsonResponse(400, ['success' => false, 'message' => 'Tipo de registro no soportado.']);
    }

    if ($leadId <= 0) {
        registroLeadJsonResponse(400, ['success' => false, 'message' => 'Lead inválido.']);
    }

    if (!registroLeadValidateTableName($tablaOrigen)) {
        registroLeadJsonResponse(400, ['success' => false, 'message' => 'Tabla de origen inválida.']);
    }

    if (strcasecmp($tablaOrigen, 'eventos_wp') === 0) {
        registroLeadJsonResponse(400, [
            'success' => false,
            'message' => 'Este registro pertenece a un evento de Wedding Planner. Edítalo como evento WP.',
        ]);
    }

    $escapedTable = $conn->real_escape_string($tablaOrigen);
    $checkTable = $conn->query("SHOW TABLES LIKE '$escapedTable'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        registroLeadJsonResponse(404, ['success' => false, 'message' => 'Tabla de origen no encontrada.']);
    }

    $dateColumn = null;
    foreach (['created_time', 'created_at'] as $candidate) {
        if (registroLeadTableHasColumn($conn, $tablaOrigen, $candidate)) {
            $dateColumn = $candidate;
            break;
        }
    }

    if ($dateColumn === null) {
        registroLeadJsonResponse(400, ['success' => false, 'message' => 'Esta tabla no tiene campo de fecha de registro editable.']);
    }

    $conn->begin_transaction();

    $sql = "UPDATE `{$tablaOrigen}` SET `{$dateColumn}` = ? WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualización del lead.');
    }
    $stmt->bind_param('si', $fechaRegistroDb, $leadId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar la fecha de registro.');
    }
    $stmt->close();

    if (registroLeadTableHasColumn($conn, $tablaOrigen, 'submission_date')) {
        $stmt = $conn->prepare("UPDATE `{$tablaOrigen}` SET submission_date = ? WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('si', $fechaRegistroDb, $leadId);
            $stmt->execute();
            $stmt->close();
        }
    }

    registroLeadSyncContactForm($conn, $leadId, $tablaOrigen, $fechaRegistroDb, $contactFormId);

    $conn->commit();

    registroLeadJsonResponse(200, [
        'success' => true,
        'message' => 'Fecha de registro actualizada.',
        'display' => $displayFormatted,
        'data_order' => $fechaTs,
    ]);
} catch (Exception $e) {
    @ $conn->rollback();
    registroLeadJsonResponse(500, ['success' => false, 'message' => $e->getMessage()]);
}
