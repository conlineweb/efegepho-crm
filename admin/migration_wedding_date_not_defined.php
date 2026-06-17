<?php
/**
 * migration_wedding_date_not_defined.php
 * Agrega la columna wedding_date_not_defined a organic_leads.
 *
 * Ejecutar una sola vez. Eliminar o restringir acceso después de usarlo.
 */
declare(strict_types=1);

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/autoload_session.php';

if (empty($_SESSION['uid'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$check = $conn->query("SHOW COLUMNS FROM `organic_leads` LIKE 'wedding_date_not_defined'");
if ($check && $check->num_rows > 0) {
    echo json_encode([
        'success' => true,
        'message' => 'La columna wedding_date_not_defined ya existe',
        'estado'  => 'ok',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$ok = $conn->query("ALTER TABLE `organic_leads` ADD COLUMN `wedding_date_not_defined` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=cliente sin fecha de evento definida' AFTER `wedding_date`");

if (!$ok) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear la columna',
        'error'   => $conn->error,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Columna wedding_date_not_defined creada en organic_leads',
    'estado'  => 'ok',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$conn->close();
