<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

include __DIR__ . '/conn.php';
require_once __DIR__ . '/autoload_session.php';
require_once __DIR__ . '/lead_interactions_helper.php';

function ensureLeadInteractionsTable($conn)
{
    leadInteractionsEnsureTable($conn);
}

$response = ['success' => false, 'message' => 'No se pudo registrar la interaccion'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo no permitido');
    }

    ensureLeadInteractionsTable($conn);

    $plannerId = intval($_POST['planner_id'] ?? 0);
    $interactionType = trim((string) ($_POST['interaction_type'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $outcome = trim((string) ($_POST['outcome'] ?? ''));
    $nextAction = trim((string) ($_POST['next_action'] ?? ''));
    $nextActionDate = trim((string) ($_POST['next_action_date'] ?? ''));

    if ($plannerId <= 0) {
        throw new Exception('Planner invalido');
    }

    if ($interactionType === '' && $nextAction !== '') {
        $interactionType = 'Sin respuesta';
    }

    if ($interactionType === '') {
        throw new Exception('Selecciona un tipo de interaccion');
    }

    $allowedTypes = ['Llamada', 'WhatsApp', 'Email', 'Reunion', 'Nota interna', 'Sin respuesta'];
    if (!in_array($interactionType, $allowedTypes, true)) {
        throw new Exception('Tipo de interaccion no valido');
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

    if ($outcome === '') {
        $outcome = 'Sin respuesta';
    }

    $allowedOutcomes = ['Positivo', 'Neutral', 'Negativo', 'Listo para cerrar', 'Requiere seguimiento', 'Sin respuesta'];
    if (!in_array($outcome, $allowedOutcomes, true)) {
        throw new Exception('Resultado no valido');
    }

    if ($nextActionDate !== '') {
        $dateObj = DateTime::createFromFormat('Y-m-d', $nextActionDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $nextActionDate) {
            throw new Exception('Fecha de proxima accion no valida');
        }
    } else {
        $nextActionDate = null;
    }

    if ($nextAction === '') {
        $nextAction = null;
    }

    $interactionDate = date('Y-m-d');
    $interactionTime = date('H:i:s');
    $createdBy = isset($_SESSION['uid']) ? intval($_SESSION['uid']) : null;
    $tablaOrigen = 'wedding_planners';
    $leadName = leadInteractionsResolveLeadName($conn, $tablaOrigen, $plannerId);
    if ($leadName === '') {
        $leadName = leadInteractionsNormalizeLeadName($_POST['lead_name'] ?? '') ?? '';
    } else {
        $leadName = leadInteractionsNormalizeLeadName($leadName) ?? '';
    }

    $insertSql = 'INSERT INTO lead_interactions (tabla_origen, lead_id, original_lead_id, lead_name, interaction_type, interaction_date, interaction_time, notes, outcome, next_action, next_action_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        throw new Exception('No se pudo preparar el guardado');
    }

    $stmt->bind_param(
        'siissssssssi',
        $tablaOrigen,
        $plannerId,
        $plannerId,
        $leadName,
        $interactionType,
        $interactionDate,
        $interactionTime,
        $notes,
        $outcome,
        $nextAction,
        $nextActionDate,
        $createdBy
    );

    if (!$stmt->execute()) {
        throw new Exception('No se pudo guardar la interaccion: ' . $stmt->error);
    }

    $newId = intval($stmt->insert_id);
    $stmt->close();

    $response = [
        'success' => true,
        'message' => 'Interaccion registrada',
        'id' => $newId,
    ];
} catch (Throwable $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
    ];
}

echo json_encode($response);
