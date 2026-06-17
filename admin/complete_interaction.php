<?php
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['login']) || $_SESSION['login'] === false) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

try {
    include __DIR__ . '/conn.php';

    $interactionId = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($interactionId <= 0) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'ID inválido']);
        exit;
    }

    // Ensure the column exists before updating
    $colCheck = $conn->query("SHOW COLUMNS FROM `lead_interactions` LIKE 'next_action_completed'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE `lead_interactions` ADD COLUMN `next_action_completed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `next_action_date`");
    }

    $stmt = $conn->prepare("UPDATE lead_interactions SET next_action_completed = 1 WHERE id = ?");
    if (!$stmt) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Prepare failed: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('i', $interactionId);
    $ok = $stmt->execute();
    $stmt->close();

    ob_clean();
    echo json_encode(['ok' => $ok]);
} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
