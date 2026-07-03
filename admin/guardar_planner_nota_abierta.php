<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

include __DIR__ . '/conn.php';
require_once __DIR__ . '/autoload_session.php';
require_once __DIR__ . '/calendario_estatus_historial_helper.php';
require_once __DIR__ . '/wp_planner_open_notes_helper.php';

$response = ['success' => false, 'message' => 'No se pudo guardar la nota'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo no permitido');
    }

    ensureWpPlannerOpenNotesTable($conn);

    $plannerId = intval($_POST['planner_id'] ?? 0);
    $noteType = strtolower(trim((string) ($_POST['note_type'] ?? 'nota')));
    $content = trim((string) ($_POST['content'] ?? ''));

    if ($plannerId <= 0) {
        throw new Exception('Planner invalido');
    }

    if ($content === '') {
        throw new Exception('Escribe el contenido de la nota');
    }

    if (!in_array($noteType, ['nota', 'acuerdo'], true)) {
        throw new Exception('Tipo de nota no valido');
    }

    $checkPlanner = $conn->prepare('SELECT id FROM wedding_planners WHERE id = ? LIMIT 1');
    if (!$checkPlanner) {
        throw new Exception('No se pudo validar el planner');
    }

    $checkPlanner->bind_param('i', $plannerId);
    $checkPlanner->execute();
    $plannerRes = $checkPlanner->get_result();
    $plannerExists = ($plannerRes && $plannerRes->num_rows > 0);
    $checkPlanner->close();

    if (!$plannerExists) {
        throw new Exception('Wedding planner no encontrado');
    }

    $createdBy = isset($_SESSION['uid']) ? intval($_SESSION['uid']) : null;

    $stmt = $conn->prepare(
        'INSERT INTO wp_planner_open_notes (wedding_planner_id, note_type, content, created_by)
         VALUES (?, ?, ?, ?)'
    );

    if (!$stmt) {
        throw new Exception('No se pudo preparar el guardado');
    }

    $stmt->bind_param('issi', $plannerId, $noteType, $content, $createdBy);

    if (!$stmt->execute()) {
        throw new Exception('No se pudo guardar la nota: ' . $stmt->error);
    }

    $newId = intval($stmt->insert_id);
    $stmt->close();

    $response = [
        'success' => true,
        'message' => 'Nota guardada',
        'id' => $newId,
    ];
} catch (Throwable $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
    ];
}

echo json_encode($response);
