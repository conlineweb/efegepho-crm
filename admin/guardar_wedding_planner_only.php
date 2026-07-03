<?php
include 'autoload_session.php';
include 'conn.php';
require_once __DIR__ . '/usuario_roles_helper.php';
header('Content-Type: application/json');
ini_set('display_errors', 1); error_reporting(E_ALL);

$sessionUserId = intval($_SESSION['uid'] ?? 0);

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

$data = $_POST;
$id = isset($data['id']) ? intval($data['id']) : 0;
$campaign = isset($data['campaign_name']) ? normalize_text(trim($data['campaign_name'])) : '';
$where_is = isset($data['where_is']) ? normalize_text(trim($data['where_is'])) : '';
$when_are = isset($data['when_are']) ? trim($data['when_are']) : '';
$full_name = isset($data['full_name']) ? normalize_text(trim($data['full_name'])) : '';
$empresa_wp = isset($data['empresa_wp']) ? normalize_text(trim($data['empresa_wp'])) : '';
$city = isset($data['city']) ? normalize_text(trim($data['city'])) : '';
$phone = isset($data['phone']) ? trim($data['phone']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$how_long_known_us = isset($data['how_long_known_us']) ? trim($data['how_long_known_us']) : '';
$first_contact_channel = isset($data['first_contact_channel']) ? trim($data['first_contact_channel']) : '';
$platform = isset($data['platform']) ? trim($data['platform']) : '';
$afianzado = isset($data['afianzado']) ? intval($data['afianzado']) : 0;
$id_vendedor_asignado = isset($data['id_vendedor_asignado']) ? intval($data['id_vendedor_asignado']) : 0;
 $estatusWP = 1;
// Only campaign_name (nombre del Wedding Planner) is required for the "only" endpoint
if ($campaign === '') {
    echo json_encode(['success' => false, 'message' => 'El nombre del Wedding Planner es obligatorio']);
    exit;
}

// If vendor provided, verify it's an agent (tipoUsu = 1). If not provided, ignore.
if ($id_vendedor_asignado > 0) {
    $stmt = $conn->prepare("SELECT tipoUsu FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id_vendedor_asignado);
    $stmt->execute();
    $resu = $stmt->get_result();
    $stmt->close();
    if (!$resu || $resu->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Asesor no encontrado.']);
        exit;
    }
    $rowu = $resu->fetch_assoc();
    if (!usuarioEsVendedoraAsignableEnConsultaWp($rowu['tipoUsu'] ?? -1, $sessionUserId, $id_vendedor_asignado)) {
        echo json_encode(['success' => false, 'message' => 'El usuario seleccionado no es un asesor válido.']);
        exit;
    }
}

try {
    ensureColumnExists($conn, 'wedding_planners', 'empresa_wp', "`empresa_wp` VARCHAR(255) DEFAULT NULL AFTER `where_is_your_marriage_taking_place_`");
    ensureColumnExists($conn, 'wedding_planners', 'email', "`email` VARCHAR(255) DEFAULT NULL AFTER `empresa_wp`");
    ensureColumnExists($conn, 'wedding_planners', 'city', "`city` VARCHAR(255) DEFAULT NULL AFTER `email`");
    ensureColumnExists($conn, 'wedding_planners', 'phone', "`phone` VARCHAR(255) DEFAULT NULL AFTER `id_vendedor_asignado`");
    ensureColumnExists($conn, 'wedding_planners', 'how_long_known_us', "`how_long_known_us` VARCHAR(150) DEFAULT NULL AFTER `whatsapp_bot_sent`");
    ensureColumnExists($conn, 'wedding_planners', 'first_contact_channel', "`first_contact_channel` VARCHAR(255) DEFAULT NULL AFTER `estatus`");
    ensureColumnExists($conn, 'wedding_planners', 'afianzado', "`afianzado` TINYINT(1) NOT NULL DEFAULT 0");

    $now = date('Y-m-d H:i:s');
    $safeWhere = $where_is !== '' ? $where_is : '';
    $safeWhen = $when_are !== '' ? $when_are : '';
    $safeName = $full_name !== '' ? $full_name : $campaign;
    $safeCompany = $empresa_wp !== '' ? $empresa_wp : $safeName;

    if ($id > 0) {
        // Update existing wedding planner
        $stmt = $conn->prepare("UPDATE wedding_planners SET campaign_name = ?, empresa_wp = ?, where_is_your_marriage_taking_place_ = ?, when_are_you_getting_married_ = ?, full_name = ?, city = ?, phone = ?, email = ?, how_long_known_us = ?, first_contact_channel = ?, platform = ?, afianzado = ?, id_vendedor_asignado = ? WHERE id = ?");
        $stmt->bind_param('sssssssssssiii', $campaign, $safeCompany, $safeWhere, $safeWhen, $safeName, $city, $phone, $email, $how_long_known_us, $first_contact_channel, $platform, $afianzado, $id_vendedor_asignado, $id);
        if (!$stmt->execute()) throw new Exception('Error al actualizar wedding_planners: ' . $stmt->error);
        $stmt->close();
        echo json_encode(['success' => true, 'updated_id' => $id]);
        exit;
    } else {
        // Insert new wedding planner
        $stmt = $conn->prepare("INSERT INTO wedding_planners (campaign_name, empresa_wp, where_is_your_marriage_taking_place_, when_are_you_getting_married_, full_name, city, phone, email, how_long_known_us, first_contact_channel, platform, afianzado, id_vendedor_asignado, created_time, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssssssssiisi', $campaign, $safeCompany, $safeWhere, $safeWhen, $safeName, $city, $phone, $email, $how_long_known_us, $first_contact_channel, $platform, $afianzado, $id_vendedor_asignado, $now, $estatusWP);
        if (!$stmt->execute()) throw new Exception('Error al insertar en wedding_planners: ' . $stmt->error);
        $wp_id = $stmt->insert_id;
        $stmt->close();

        echo json_encode(['success' => true, 'wedding_planner_id' => $wp_id]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>