<?php
include 'conn.php';
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

function normalize_text($texto) {
    if ($texto === null || $texto === '') return '';
    $texto = trim($texto);
    if (class_exists('Normalizer')) {
        $texto = Normalizer::normalize($texto, Normalizer::NFKD);
        $texto = preg_replace('/\p{Mn}+/u', '', $texto);
    }
    if (function_exists('mb_convert_encoding')) {
        $ascii = @mb_convert_encoding($texto, 'ASCII', 'UTF-8');
        if ($ascii !== false && $ascii !== '') $texto = $ascii;
    }
    $texto = preg_replace('/[^\x20-\x7EáéíóúÁÉÍÓÚñÑüÜ]/u', '', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return trim($texto);
}

function first_non_empty_value() {
    foreach (func_get_args() as $value) {
        if ($value === null) {
            continue;
        }

        $normalized = trim((string) $value);
        if ($normalized !== '') {
            return $normalized;
        }
    }

    return '';
}

function ensureColumnExists($conn, $table, $columnName, $columnDef) {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($columnName);
    $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safeTable}' AND COLUMN_NAME = '{$safeColumn}'";
    $res = $conn->query($checkSql);
    if ($res) {
        $row = $res->fetch_assoc();
        if (intval($row['c'] ?? 0) === 0) {
            $conn->query("ALTER TABLE `{$table}` ADD COLUMN {$columnDef}");
        }
    }
}

function syncCoordinatorToAfianzadoLeads($conn, $idWp, $coordId, $nombre, $telefono, $correo, $howLongKnownUs, $firstContactChannel, $campaignName, $platform, $engagement) {
    if ($coordId <= 0 || $idWp <= 0) {
        return;
    }

    $tableCheck = $conn->query("SHOW TABLES LIKE 'wp_eventos_afianzados'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    $wpCampaignName = '';
    $wpPlatform = '';
    $stmtWp = $conn->prepare('SELECT campaign_name, platform FROM wedding_planners WHERE id = ? LIMIT 1');
    if ($stmtWp) {
        $stmtWp->bind_param('i', $idWp);
        $stmtWp->execute();
        $resWp = $stmtWp->get_result();
        $wpRow = $resWp ? $resWp->fetch_assoc() : null;
        $stmtWp->close();
        if ($wpRow) {
            $wpCampaignName = trim((string) ($wpRow['campaign_name'] ?? ''));
            $wpPlatform = trim((string) ($wpRow['platform'] ?? ''));
        }
    }

    $resolvedCampaignName = first_non_empty_value($campaignName, $wpCampaignName, 'Wedding Planner Afianzado');
    $resolvedPlatform = first_non_empty_value($platform, $wpPlatform, 'Wedding Planner');

    $stmt = $conn->prepare('UPDATE wp_eventos_afianzados SET full_name = ?, coordinador_nombre = ?, phone = ?, email = ?, how_long_known_us = ?, first_contact_channel = ?, campaign_name = ?, platform = ? WHERE id_wedding_planner = ? AND id_coordinador = ?');
    if (!$stmt) {
        throw new Exception('No se pudo sincronizar coordinador con leads afianzados: ' . $conn->error);
    }

    $stmt->bind_param('ssssssssii', $nombre, $nombre, $telefono, $correo, $howLongKnownUs, $firstContactChannel, $resolvedCampaignName, $resolvedPlatform, $idWp, $coordId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar leads afianzados del coordinador: ' . $stmt->error);
    }
    $stmt->close();

    $tablaOrigen = 'wp_eventos_afianzados';
    ensureColumnExists($conn, 'contact_form', 'engagement', "`engagement` INT DEFAULT 0");

    $stmt = $conn->prepare('UPDATE contact_form cf INNER JOIN wp_eventos_afianzados wpa ON wpa.id = cf.original_lead_id SET cf.names = ?, cf.telephone = ?, cf.email_address = ?, cf.how_long_known_us = ?, cf.first_contact_channel = ?, cf.campaign_name = ?, cf.engagement = ? WHERE cf.tabla_origen = ? AND wpa.id_wedding_planner = ? AND wpa.id_coordinador = ?');
    if (!$stmt) {
        throw new Exception('No se pudo sincronizar contact_form con leads afianzados: ' . $conn->error);
    }

    $stmt->bind_param('ssssssisii', $nombre, $telefono, $correo, $howLongKnownUs, $firstContactChannel, $resolvedCampaignName, $engagement, $tablaOrigen, $idWp, $coordId);
    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar contact_form del coordinador afianzado: ' . $stmt->error);
    }
    $stmt->close();
}

$nombre = isset($_POST['nombre']) ? normalize_text($_POST['nombre']) : '';
$idWp = isset($_POST['id_wp']) ? intval($_POST['id_wp']) : 0;
$telefono = isset($_POST['telefono']) ? normalize_text($_POST['telefono']) : '';
$correo = isset($_POST['correo']) ? trim($_POST['correo']) : '';
$plannerReason = isset($_POST['planner_reason']) ? normalize_text($_POST['planner_reason']) : '';
$howLongKnownUs = isset($_POST['how_long_known_us']) ? normalize_text($_POST['how_long_known_us']) : '';
$firstContactChannel = isset($_POST['first_contact_channel']) ? normalize_text($_POST['first_contact_channel']) : '';
$campaignName = isset($_POST['campaign_name']) ? normalize_text($_POST['campaign_name']) : '';
$platform = isset($_POST['platform']) ? normalize_text($_POST['platform']) : '';
$howDidYouMeet = 1;
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($nombre === '') {
    echo json_encode(['success' => false, 'message' => 'El nombre del coordinador es obligatorio']);
    exit;
}

if ($idWp <= 0) {
    echo json_encode(['success' => false, 'message' => 'Wedding Planner inválido']);
    exit;
}

if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Correo inválido']);
    exit;
}

try {
    ensureColumnExists($conn, 'coordinadores_wp', 'planner_reason', "`planner_reason` VARCHAR(255) DEFAULT NULL AFTER `correo`");
    ensureColumnExists($conn, 'coordinadores_wp', 'how_long_known_us', "`how_long_known_us` VARCHAR(150) DEFAULT NULL AFTER `planner_reason`");
    ensureColumnExists($conn, 'coordinadores_wp', 'first_contact_channel', "`first_contact_channel` VARCHAR(255) DEFAULT NULL AFTER `how_long_known_us`");
    ensureColumnExists($conn, 'coordinadores_wp', 'campaign_name', "`campaign_name` VARCHAR(255) DEFAULT NULL AFTER `first_contact_channel`");
    ensureColumnExists($conn, 'coordinadores_wp', 'platform', "`platform` VARCHAR(255) DEFAULT NULL AFTER `campaign_name`");
    ensureColumnExists($conn, 'coordinadores_wp', 'how_did_you_meet', "`how_did_you_meet` TINYINT(1) NOT NULL DEFAULT 1 AFTER `platform`");

    $stmt = $conn->prepare('SELECT id FROM wedding_planners WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $idWp);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if (!$res || $res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Wedding Planner no encontrado']);
        exit;
    }

    if ($id > 0) {
        // Update existing coordinator
        $stmt = $conn->prepare('UPDATE coordinadores_wp SET nombre = ?, telefono = ?, correo = ?, planner_reason = ?, how_long_known_us = ?, first_contact_channel = ?, campaign_name = ?, platform = ?, how_did_you_meet = ? WHERE id = ? AND id_wp = ?');
        $stmt->bind_param('ssssssssiii', $nombre, $telefono, $correo, $plannerReason, $howLongKnownUs, $firstContactChannel, $campaignName, $platform, $howDidYouMeet, $id, $idWp);
        if (!$stmt->execute()) throw new Exception('Error al actualizar coordinador: ' . $stmt->error);
        $stmt->close();
        syncCoordinatorToAfianzadoLeads($conn, $idWp, $id, $nombre, $telefono, $correo, $howLongKnownUs, $firstContactChannel, $campaignName, $platform, $howDidYouMeet);
        echo json_encode(['success' => true, 'updated_id' => $id]);
        exit;
    }
$estatusCoordinador = 1;
    $stmt = $conn->prepare('INSERT INTO coordinadores_wp (id_wp, nombre, telefono, correo, planner_reason, how_long_known_us, first_contact_channel, campaign_name, platform, how_did_you_meet, fecha_creacion, estatus) VALUES (?,?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('issssssssii', $idWp, $nombre, $telefono, $correo, $plannerReason, $howLongKnownUs, $firstContactChannel, $campaignName, $platform, $howDidYouMeet,$estatusCoordinador);
    if (!$stmt->execute()) {
        throw new Exception('Error al insertar coordinador: ' . $stmt->error);
    }
    $newId = $stmt->insert_id;
    $stmt->close();

    syncCoordinatorToAfianzadoLeads($conn, $idWp, $newId, $nombre, $telefono, $correo, $howLongKnownUs, $firstContactChannel, $campaignName, $platform, $howDidYouMeet);

    echo json_encode(['success' => true, 'id' => $newId]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>