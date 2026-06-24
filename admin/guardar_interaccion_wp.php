<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

include __DIR__ . '/conn.php';
require_once __DIR__ . '/autoload_session.php';

function ensureLeadInteractionsTable($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS `lead_interactions` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `tabla_origen` VARCHAR(120) NOT NULL,
        `lead_id` INT NOT NULL,
        `original_lead_id` INT DEFAULT NULL,
        `interaction_type` VARCHAR(40) DEFAULT NULL,
        `interaction_date` DATE DEFAULT NULL,
        `interaction_time` VARCHAR(20) DEFAULT NULL,
        `notes` TEXT,
        `outcome` VARCHAR(40) DEFAULT NULL,
        `next_action` VARCHAR(255) DEFAULT NULL,
        `next_action_date` DATE DEFAULT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_lead_interactions_lead` (`tabla_origen`, `lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->query($sql);

    $checkOriginalLeadId = $conn->query("SHOW COLUMNS FROM `lead_interactions` LIKE 'original_lead_id'");
    if ($checkOriginalLeadId && $checkOriginalLeadId->num_rows === 0) {
        $conn->query("ALTER TABLE `lead_interactions` ADD COLUMN `original_lead_id` INT DEFAULT NULL AFTER `lead_id`");
    }

    $checkInteractionType = $conn->query("SHOW COLUMNS FROM `lead_interactions` LIKE 'interaction_type'");
    if ($checkInteractionType && $checkInteractionType->num_rows > 0) {
        $col = $checkInteractionType->fetch_assoc();
        if (($col['Null'] ?? '') === 'NO') {
            $conn->query("ALTER TABLE `lead_interactions` MODIFY COLUMN `interaction_type` VARCHAR(40) DEFAULT NULL");
        }
    }
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

    $insertSql = 'INSERT INTO lead_interactions (tabla_origen, lead_id, original_lead_id, interaction_type, interaction_date, interaction_time, notes, outcome, next_action, next_action_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        throw new Exception('No se pudo preparar el guardado');
    }

    $stmt->bind_param(
        'siisssssssi',
        $tablaOrigen,
        $plannerId,
        $plannerId,
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
