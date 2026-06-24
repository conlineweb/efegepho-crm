<?php
include 'conn.php';
require_once __DIR__ . '/usuario_roles_helper.php';
require_once __DIR__ . '/wp_citas_leads_helper.php';
header('Content-Type: application/json');
ini_set('display_errors', 1); error_reporting(E_ALL);

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

$wp_id = isset($_POST['wedding_planner_id']) ? intval($_POST['wedding_planner_id']) : 0;
$appointment_date = isset($_POST['fecha']) ? trim((string) $_POST['fecha']) : '';
$appointment_time = isset($_POST['hora']) ? trim((string) $_POST['hora']) : '';
$client_appointment_date = isset($_POST['fecha_cliente']) ? trim((string) $_POST['fecha_cliente']) : '';
$client_appointment_time = isset($_POST['hora_cliente']) ? trim((string) $_POST['hora_cliente']) : '';
$comentario = isset($_POST['comentario']) ? trim((string) $_POST['comentario']) : '';
$cliente_city = isset($_POST['cliente_city']) ? trim((string) $_POST['cliente_city']) : '';
$cliente_engagement = isset($_POST['cliente_engagement']) ? intval($_POST['cliente_engagement']) : 0;
if ($wp_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de Wedding Planner inválido']);
    exit;
}

if ($appointment_date === '' || $appointment_time === '') {
    echo json_encode(['success' => false, 'message' => 'La fecha y la hora de la cita son obligatorias']);
    exit;
}

$appointmentDateTime = strtotime($appointment_date . ' ' . $appointment_time);
if ($appointmentDateTime === false) {
    echo json_encode(['success' => false, 'message' => 'La fecha u hora de la cita no son válidas']);
    exit;
}

if ($client_appointment_date === '') {
    $client_appointment_date = $appointment_date;
}

if ($client_appointment_time === '') {
    $client_appointment_time = $appointment_time;
}

$clientAppointmentDateTime = strtotime($client_appointment_date . ' ' . $client_appointment_time);
if ($clientAppointmentDateTime === false) {
    echo json_encode(['success' => false, 'message' => 'La fecha u hora de la cita para el cliente no son válidas']);
    exit;
}

if ($cliente_city === '') {
    echo json_encode(['success' => false, 'message' => 'La ciudad del cliente es obligatoria']);
    exit;
}

if ($cliente_engagement < 1 || $cliente_engagement > 3) {
    echo json_encode(['success' => false, 'message' => 'El engagement del cliente es obligatorio']);
    exit;
}

$appointment_date_db = date('Y-m-d', $appointmentDateTime);
$appointment_time_db = date('H:i:s', $appointmentDateTime);
$client_appointment_date_db = date('Y-m-d', $clientAppointmentDateTime);
$client_appointment_time_db = date('H:i:s', $clientAppointmentDateTime);
$fecha_registro_db = date('Y-m-d H:i:s');

ensureColumnExists($conn, 'calendario', 'fecha_registro', "`fecha_registro` DATETIME DEFAULT NULL AFTER `hora_cliente`");
ensureColumnExists($conn, 'calendario', 'tipo', "`tipo` TINYINT(1) NOT NULL DEFAULT 0 AFTER `fecha_registro`");
ensureColumnExists($conn, 'calendario', 'cliente_city', "`cliente_city` VARCHAR(255) DEFAULT NULL AFTER `comentario`");
ensureColumnExists($conn, 'calendario', 'cliente_engagement', "`cliente_engagement` TINYINT(1) DEFAULT NULL AFTER `cliente_city`");

$conn->query("CREATE TABLE IF NOT EXISTS eventos_wp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wedding_planner_id INT NOT NULL,
    id_coordinador INT DEFAULT NULL,
    lugar VARCHAR(255) DEFAULT NULL,
    fecha DATETIME DEFAULT NULL,
    fecha_registro DATETIME DEFAULT NULL,
    novios VARCHAR(255) DEFAULT NULL,
    id_asesor INT DEFAULT NULL,
    modo VARCHAR(64) DEFAULT NULL,
    tipo_paquete VARCHAR(32) DEFAULT NULL,
    id_paquete INT DEFAULT NULL,
    paquete_personalizado TEXT DEFAULT NULL,
    created_time DATETIME DEFAULT NULL,
    estatus VARCHAR(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Fetch wedding_planner
$stmt = $conn->prepare("SELECT id, campaign_name, empresa_wp, where_is_your_marriage_taking_place_, when_are_you_getting_married_, full_name, id_vendedor_asignado FROM wedding_planners WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $wp_id);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Wedding Planner no encontrado']);
    exit;
}
$wp = $res->fetch_assoc();

$campaign = $wp['campaign_name'] ?? '';
$full_name = trim((string)($wp['empresa_wp'] ?? ''));
if ($full_name === '') {
    $full_name = trim((string)($wp['full_name'] ?? ''));
}
if ($full_name === '') {
    $full_name = trim((string)($wp['campaign_name'] ?? ''));
}
// Allow override from the client (when user selects an advisor in the modal)
$override_vendedor = isset($_POST['override_vendedor_id']) ? intval($_POST['override_vendedor_id']) : 0;
$id_vendedor_asignado = isset($wp['id_vendedor_asignado']) ? intval($wp['id_vendedor_asignado']) : 0;

if ($override_vendedor > 0) {
    // Validate override is an agent (tipoUsu = 1)
    $stmt = $conn->prepare("SELECT tipoUsu FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $override_vendedor);
    $stmt->execute();
    $resv = $stmt->get_result();
    $stmt->close();
    if (!$resv || $resv->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Asesor seleccionado no encontrado']);
        exit;
    }
    $rowv = $resv->fetch_assoc();
    if (!usuarioTipoPuedeAsignarSesionWp($rowv['tipoUsu'] ?? -1)) {
        echo json_encode(['success' => false, 'message' => 'El usuario seleccionado no es un asesor válido.']);
        exit;
    }
    $id_vendedor_asignado = $override_vendedor; // use override
}

if ($id_vendedor_asignado <= 0) {
    echo json_encode(['success' => false, 'message' => 'No hay asesor asignado en el registro, asigne un asesor y reintente']);
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO eventos_wp (wedding_planner_id) VALUES (?)");
    if (!$stmt) throw new Exception('Prepare eventos_wp failed: ' . $conn->error);
    $stmt->bind_param('i', $wp_id);
    if (!$stmt->execute()) throw new Exception('Error al insertar en eventos_wp: ' . $stmt->error);
    $evento_wp_id = intval($stmt->insert_id);
    $stmt->close();

    $tituloPrefix = 'Llamada WP: ';
    $nota = '';
    $estatus = 0;
    $eliminado = 0;
    $titulo = $tituloPrefix . substr($full_name,0,120);
    $use_idusu = $id_vendedor_asignado;
    $tipo = 1;

    // IMPORTANTE: fecha/hora = VENDEDOR, fecha_cliente/hora_cliente = CLIENTE
    $stmt = $conn->prepare("INSERT INTO calendario (idusu, fecha, fecha_cliente, hora, hora_cliente, fecha_registro, tipo, titulo, nota, comentario, cliente_city, cliente_engagement, idclie, estatus, eliminado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('Prepare calendario failed: ' . $conn->error);
    $stmt->bind_param('isssssissssiiii', $use_idusu, $appointment_date_db, $client_appointment_date_db, $appointment_time_db, $client_appointment_time_db, $fecha_registro_db, $tipo, $titulo, $nota, $comentario, $cliente_city, $cliente_engagement, $evento_wp_id, $estatus, $eliminado);
    if (!$stmt->execute()) throw new Exception('Error al insertar en calendario: ' . $stmt->error);
    $cal_id = $stmt->insert_id;
    $stmt->close();

    wpCitasSyncLeadByCalendarId($conn, intval($cal_id));

    // Persist the selected vendor as the WP's assigned vendor so it shows on reload
    if ($override_vendedor > 0) {
        $stmtUpd = $conn->prepare("UPDATE wedding_planners SET id_vendedor_asignado = ? WHERE id = ?");
        if ($stmtUpd) {
            $stmtUpd->bind_param('ii', $override_vendedor, $wp_id);
            $stmtUpd->execute();
            $stmtUpd->close();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'calendario_id' => $cal_id, 'evento_wp_id' => $evento_wp_id]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>