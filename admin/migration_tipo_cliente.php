<?php
/**
 * migration_tipo_cliente.php
 * Migración única: rellena tipo_cliente solo cuando hay señal clara.
 *
 * Reglas (inversas a guardar_lead_manual / consulta.php):
 *   how_did_you_meet = 1        → 1 (Wedding Planner)
 *   how_did_you_meet IN (2, 3)  → 0 (Cliente Final)
 *   tablas espejo WP              → 0 (Cliente Final)
 *
 * No infiere por canal digital ni por how_long_known_us aislado.
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

$results = [];

function ensureTipoClienteColumn(mysqli $conn, string $table): bool
{
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'tipo_cliente'");
    if ($check && $check->num_rows > 0) {
        return true;
    }

    return (bool) $conn->query("ALTER TABLE `$table` ADD COLUMN `tipo_cliente` TINYINT(1) NULL DEFAULT NULL COMMENT '1=Wedding Planner, 0=Cliente Final'");
}

function backfillOrganicLeads(mysqli $conn): array
{
    if (!ensureTipoClienteColumn($conn, 'organic_leads')) {
        return ['tabla' => 'organic_leads', 'estado' => 'error', 'error' => $conn->error];
    }

    $sql = "UPDATE organic_leads
        SET tipo_cliente = CASE
            WHEN how_did_you_meet = '1' THEN 1
            WHEN how_did_you_meet IN ('2', '3') THEN 0
            ELSE tipo_cliente
        END
        WHERE tipo_cliente IS NULL
           OR (tipo_cliente = 0 AND how_did_you_meet = '1')
           OR (tipo_cliente = 1 AND how_did_you_meet IN ('2', '3'))";

    if (!$conn->query($sql)) {
        return ['tabla' => 'organic_leads', 'estado' => 'error', 'error' => $conn->error];
    }

    return [
        'tabla'        => 'organic_leads',
        'actualizados' => $conn->affected_rows,
        'estado'       => 'ok',
    ];
}

function backfillContactForm(mysqli $conn): array
{
    if (!ensureTipoClienteColumn($conn, 'contact_form')) {
        return ['tabla' => 'contact_form', 'estado' => 'error', 'error' => $conn->error];
    }

    $sqlWpMirror = "UPDATE contact_form
        SET tipo_cliente = 0
        WHERE tipo_cliente IS NULL
          AND LOWER(COALESCE(tabla_origen, '')) IN ('eventos_wp', 'wp_eventos_afianzados', 'wp_citas_leads')";

    if (!$conn->query($sqlWpMirror)) {
        return ['tabla' => 'contact_form (espejo WP)', 'estado' => 'error', 'error' => $conn->error];
    }

    $wpMirrorUpdated = $conn->affected_rows;

    $sql = "UPDATE contact_form
        SET tipo_cliente = CASE
            WHEN LOWER(COALESCE(tabla_origen, '')) = 'wedding_planners' THEN 1
            WHEN how_did_you_meet = '1' THEN 1
            WHEN how_did_you_meet IN ('2', '3') THEN 0
            ELSE tipo_cliente
        END
        WHERE (
            tipo_cliente IS NULL
            OR (tipo_cliente = 0 AND how_did_you_meet = '1')
            OR (tipo_cliente = 1 AND how_did_you_meet IN ('2', '3'))
        )
        AND LOWER(COALESCE(tabla_origen, '')) NOT IN ('eventos_wp', 'wp_eventos_afianzados', 'wp_citas_leads')";

    if (!$conn->query($sql)) {
        return ['tabla' => 'contact_form', 'estado' => 'error', 'error' => $conn->error];
    }

    return [
        'tabla'        => 'contact_form',
        'actualizados' => $conn->affected_rows + $wpMirrorUpdated,
        'estado'       => 'ok',
    ];
}

$results[] = backfillOrganicLeads($conn);
$results[] = backfillContactForm($conn);

$conn->close();

echo json_encode([
    'success' => true,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
