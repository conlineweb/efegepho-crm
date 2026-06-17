<?php
/**
 * migration_how_did_you_meet.php
 * Migración única: asigna how_did_you_meet según tipo_cliente + how_long_known_us
 *
 * Reglas:
 *   tipo_cliente = 1 (Wedding Planner)                               → '1' Wedding Planner
 *   tipo_cliente = 0 (Cliente Final) + 'Less than 6 months'         → '3' New Audience
 *   tipo_cliente = 0 (Cliente Final) + 'More than 6 months'         → '2' Community
 *
 * Ejecutar una sola vez. Eliminar o restringir acceso después de usarlo.
 */
declare(strict_types=1);

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/autoload_session.php';

// Sólo accesible para administradores autenticados
if (empty($_SESSION['uid'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$results = [];

// ─────────────────────────────────────────────────────────────────
// 1. organic_leads  (tipo_cliente es INT: 1=WP, 0=CF)
// ─────────────────────────────────────────────────────────────────
$col_organic = $conn->query("SHOW COLUMNS FROM organic_leads LIKE 'tipo_cliente'");

// Crear la columna si no existe (DEFAULT 0 = Cliente Final)
if ($col_organic && $col_organic->num_rows === 0) {
    $conn->query("ALTER TABLE `organic_leads` ADD COLUMN `tipo_cliente` TINYINT(1) NOT NULL DEFAULT 0");
    $results[] = [
        'tabla'   => 'organic_leads',
        'estado'  => 'columna_creada',
        'mensaje' => "Columna 'tipo_cliente' añadida con DEFAULT 0 (Cliente Final)",
    ];
    // Re-check after creation
    $col_organic = $conn->query("SHOW COLUMNS FROM organic_leads LIKE 'tipo_cliente'");
}

if ($col_organic && $col_organic->num_rows > 0) {
    $sql_organic = "UPDATE organic_leads
    SET how_did_you_meet = CASE
        WHEN tipo_cliente = 1                                                  THEN '1'
        WHEN tipo_cliente = 0 AND how_long_known_us = 'Less than 6 months'    THEN '3'
        WHEN tipo_cliente = 0 AND how_long_known_us = 'More than 6 months'    THEN '2'
        ELSE how_did_you_meet
    END
    WHERE
        tipo_cliente = 1
        OR (tipo_cliente = 0 AND how_long_known_us IN ('Less than 6 months', 'More than 6 months'))";

    if ($conn->query($sql_organic)) {
        $results[] = [
            'tabla'        => 'organic_leads',
            'actualizados' => $conn->affected_rows,
            'estado'       => 'ok',
        ];
    } else {
        $results[] = [
            'tabla'  => 'organic_leads',
            'estado' => 'error',
            'error'  => $conn->error,
        ];
    }
} else {
    $results[] = [
        'tabla'   => 'organic_leads',
        'estado'  => 'omitido',
        'mensaje' => "Columna 'tipo_cliente' no existe en organic_leads",
    ];
}

// ─────────────────────────────────────────────────────────────────
// 2. contact_form  (verificar que exista la columna tipo_cliente)
// ─────────────────────────────────────────────────────────────────
$col_check = $conn->query("SHOW COLUMNS FROM contact_form LIKE 'tipo_cliente'");

if ($col_check && $col_check->num_rows > 0) {
    $sql_contact = "UPDATE contact_form
    SET how_did_you_meet = CASE
        WHEN tipo_cliente = 1                                                  THEN '1'
        WHEN tipo_cliente = 0 AND how_long_known_us = 'Less than 6 months'    THEN '3'
        WHEN tipo_cliente = 0 AND how_long_known_us = 'More than 6 months'    THEN '2'
        ELSE how_did_you_meet
    END
    WHERE
        tipo_cliente = 1
        OR (tipo_cliente = 0 AND how_long_known_us IN ('Less than 6 months', 'More than 6 months'))";

    if ($conn->query($sql_contact)) {
        $results[] = [
            'tabla'        => 'contact_form',
            'actualizados' => $conn->affected_rows,
            'estado'       => 'ok',
        ];
    } else {
        $results[] = [
            'tabla'  => 'contact_form',
            'estado' => 'error',
            'error'  => $conn->error,
        ];
    }
} else {
    $results[] = [
        'tabla'   => 'contact_form',
        'estado'  => 'omitido',
        'mensaje' => "Columna 'tipo_cliente' no existe en contact_form",
    ];
}

$conn->close();

echo json_encode([
    'success' => true,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
