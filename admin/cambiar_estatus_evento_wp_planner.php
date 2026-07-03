<?php
ob_start();
include 'conn.php';
require_once __DIR__ . '/evento_wp_post_helper.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function plannerEventStatusJsonResponse($statusCode, array $payload)
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
    }

    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode($payload);
    exit;
}

$eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$targetStatus = isset($_POST['target_status']) ? intval($_POST['target_status']) : 0;

if ($eventId <= 0) {
    plannerEventStatusJsonResponse(400, ['success' => false, 'message' => 'Evento inválido.']);
}

if (!in_array($targetStatus, [1, 2, 3, 4, 5], true)) {
    plannerEventStatusJsonResponse(400, ['success' => false, 'message' => 'Estatus objetivo inválido.']);
}

try {
    $contactFormClienteSelect = wpPlannerSqlContactFormClienteSelect('e');
    $stmt = $conn->prepare("SELECT e.id, e.estatus, {$contactFormClienteSelect} AS contact_form_cliente
        FROM eventos_wp e
        WHERE e.id = ?
        LIMIT 1");

    if (!$stmt) {
        throw new Exception('No se pudo consultar el evento: ' . $conn->error);
    }

    $stmt->bind_param('i', $eventId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo consultar el evento: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $eventRow = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$eventRow) {
        plannerEventStatusJsonResponse(404, ['success' => false, 'message' => 'Evento no encontrado.']);
    }

    $currentLabel = wpPlannerResolveEventStatusKey($eventRow['estatus'] ?? '', $eventRow['contact_form_cliente'] ?? null);
    $currentPresentation = wpPlannerEventStatusPresentation($currentLabel);
    $allowedTargets = wpPlannerAllowedEventStatusTargets($currentLabel);

    if ($currentLabel === 'cliente') {
        plannerEventStatusJsonResponse(400, ['success' => false, 'message' => 'Este evento ya está en Cliente.']);
    }

    if ($currentLabel === 'rechazado' || $currentLabel === 'muerto') {
        plannerEventStatusJsonResponse(400, ['success' => false, 'message' => 'Este evento ya está en Muerto.']);
    }

    if (empty($allowedTargets)) {
        plannerEventStatusJsonResponse(400, [
            'success' => false,
            'message' => 'El estatus actual (' . ($currentPresentation['label'] ?? $currentLabel) . ') no permite cambios.',
        ]);
    }

    if (!in_array($targetStatus, $allowedTargets, true)) {
        plannerEventStatusJsonResponse(400, [
            'success' => false,
            'message' => 'No se puede pasar de ' . ($currentPresentation['label'] ?? $currentLabel) . ' a ' . wpPlannerEventStatusTargetLabel($targetStatus) . '.',
        ]);
    }

    $conn->begin_transaction();

    wpPlannerApplyEventWorkflowStatus($conn, $eventId, $targetStatus);

    $conn->commit();

    $updatedStmt = $conn->prepare("SELECT e.estatus, {$contactFormClienteSelect} AS contact_form_cliente
        FROM eventos_wp e
        WHERE e.id = ?
        LIMIT 1");
    $newStatusKey = $currentLabel;
    $newPresentation = $currentPresentation;
    $nextAction = wpPlannerEventNextStatusAction($currentLabel);

    if ($updatedStmt) {
        $updatedStmt->bind_param('i', $eventId);
        if ($updatedStmt->execute()) {
            $updatedResult = $updatedStmt->get_result();
            $updatedRow = $updatedResult ? $updatedResult->fetch_assoc() : null;
            if ($updatedRow) {
                $newStatusKey = wpPlannerResolveEventStatusKey($updatedRow['estatus'] ?? '', $updatedRow['contact_form_cliente'] ?? null);
                $newPresentation = wpPlannerEventStatusPresentation($newStatusKey);
                $nextAction = wpPlannerEventNextStatusAction($newStatusKey);
            }
        }
        $updatedStmt->close();
    }

    plannerEventStatusJsonResponse(200, [
        'success' => true,
        'message' => 'El evento se actualizó a ' . wpPlannerEventStatusTargetLabel($targetStatus) . ' correctamente.',
        'new_status' => [
            'label' => $newPresentation['label'] ?? wpPlannerEventStatusTargetLabel($targetStatus),
            'class' => $newPresentation['class'] ?? 'status-pendiente',
        ],
        'next_action' => $nextAction,
    ]);
} catch (Exception $e) {
    @ $conn->rollback();
    plannerEventStatusJsonResponse(500, ['success' => false, 'message' => $e->getMessage()]);
}
