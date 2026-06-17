<?php
include 'conn.php';
header('Content-Type: application/json');
ini_set('display_errors', 1); error_reporting(E_ALL);

// Normalizar texto para evitar caracteres problemáticos en la BD (ej: símbolos Unicode raros)
function normalize_text($texto) {
    if ($texto === null || $texto === '') return '';
    $texto = trim($texto);
    
    // Intentar normalización Unicode si está disponible (descompone caracteres fancy)
    if (class_exists('Normalizer')) {
        $texto = Normalizer::normalize($texto, Normalizer::NFKD);
        // NFKD descompone caracteres de compatibilidad (fancy fonts → ASCII + marcas)
        // Luego eliminar marcas diacríticas excepto las que queremos
        $texto = preg_replace('/\p{Mn}+/u', '', $texto); // Elimina marcas no espaciadoras
    }
    
    // Intentar conversión a ASCII usando mb_convert_encoding
    if (function_exists('mb_convert_encoding')) {
        // Convertir desde UTF-8 a ASCII, ignorando caracteres que no se pueden convertir
        $ascii = @mb_convert_encoding($texto, 'ASCII', 'UTF-8');
        if ($ascii !== false && $ascii !== '') {
            $texto = $ascii;
        }
    }
    
    // Filtrado AGRESIVO: solo permitir ASCII básico (32-126) + acentos españoles específicos
    // Esto eliminará CUALQUIER carácter Unicode que no esté en esta lista
    $texto = preg_replace('/[^\x20-\x7EáéíóúÁÉÍÓÚñÑüÜ]/u', '', $texto);
    
    // Colapsar espacios múltiples
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
$campaign = isset($data['campaign_name']) ? normalize_text(trim($data['campaign_name'])) : '';
$where_is = isset($data['where_is']) ? normalize_text(trim($data['where_is'])) : '';
$when_are = isset($data['when_are']) ? trim($data['when_are']) : '';
$full_name = isset($data['full_name']) ? normalize_text(trim($data['full_name'])) : '';
$id_vendedor_asignado = isset($data['id_vendedor_asignado']) ? intval($data['id_vendedor_asignado']) : 0;
$modo = isset($data['modo']) ? trim($data['modo']) : '';
$allowedModes = ['asistencia_post_q', 'intencion_compra_pre_q'];
if ($modo !== '' && !in_array($modo, $allowedModes)) {
    echo json_encode(['success' => false, 'message' => 'Modo de registro inválido']);
    exit;
}
// No agent or date/time are provided by the modal anymore
$idusu = 0; // kept for backward compatibility but not used
$date_appointment = ''; 
$time_appointment = ''; 

if ($campaign === '' || $full_name === '') {
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
    exit;
}

if ($id_vendedor_asignado <= 0) {
    echo json_encode(['success' => false, 'message' => 'Debe seleccionar un asesor']);
    exit;
}

// Validate that id_vendedor_asignado is a valid agent (tipoUsu = 1)
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
if (intval($rowu['tipoUsu']) !== 1) {
    echo json_encode(['success' => false, 'message' => 'El usuario seleccionado no es un asesor válido.']);
    exit;
}

// Legacy validation for backward compatibility (if $idusu is ever used)
if ($idusu > 0) {
    $stmt = $conn->prepare("SELECT tipoUsu FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $idusu);
    $stmt->execute();
    $resu = $stmt->get_result();
    $stmt->close();
    if (!$resu || $resu->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Agente no encontrado.']);
        exit;
    }
    $rowu = $resu->fetch_assoc();
    if (intval($rowu['tipoUsu']) !== 1) {
        echo json_encode(['success' => false, 'message' => 'El usuario seleccionado no es un agente válido.']);
        exit;
    }
}

$conn->begin_transaction();
try {
    ensureColumnExists($conn, 'wedding_planners', 'empresa_wp', "`empresa_wp` VARCHAR(255) DEFAULT NULL AFTER `where_is_your_marriage_taking_place_`");
    $estatusWP = 1;
    // 1) Insert into wedding_planners
    $created_time = date('Y-m-d H:i:s');
$stmt = $conn->prepare("INSERT INTO wedding_planners (campaign_name, empresa_wp, where_is_your_marriage_taking_place_, when_are_you_getting_married_, full_name, modo, id_vendedor_asignado, created_time, estatus) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('ssssssisi', $campaign, $campaign, $where_is, $when_are, $full_name, $modo, $id_vendedor_asignado, $created_time,$estatusWP);
    if (!$stmt->execute()) throw new Exception('Error al insertar en wedding_planners: ' . $stmt->error);
    $wp_id = $stmt->insert_id;
    $stmt->close();

    // 2) Insert into contact_form (minimal fields + defaults for required
    $wedding_date = $when_are !== '' ? date('Y-m-d', strtotime($when_are)) : null;
    $cf_country_code = '';
    $cf_cliente = 0;
    $cf_fecha_cambio_cliente = date('Y-m-d');
    // Use the selected vendor from the form
    $cf_id_vendedor_asignado = $id_vendedor_asignado;
    $cf_manual = 1;
    $cf_desde_publicidad = 1; // since it comes from wedding_planners
    $cf_tabla_origen = 'wedding_planners';

    $stmt = $conn->prepare("INSERT INTO contact_form (names, telephone, country_code, email_address, wedding_date, wedding_location, campaign_name, original_lead_id, tabla_origen, date_appointment, time_appointment, cliente, fecha_cambio_cliente, id_vendedor_asignado, manual, desde_publicidad) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $telephone = '';
    $email = '';
    $date_app_db = $date_appointment ?: null;
    $time_app_db = $time_appointment ?: null;

    $stmt->bind_param('sssssssssssssiii', $full_name, $telephone, $cf_country_code, $email, $wedding_date, $where_is, $campaign, $wp_id, $cf_tabla_origen, $date_app_db, $time_app_db, $cf_cliente, $cf_fecha_cambio_cliente, $cf_id_vendedor_asignado, $cf_manual, $cf_desde_publicidad);

    if (!$stmt->execute()) throw new Exception('Error al insertar en contact_form: ' . $stmt->error);
    $cf_id = $stmt->insert_id;
    $stmt->close();

    // 3) Insert into calendario. If agent + date + time provided, schedule normally (with availability check).
    // Otherwise insert a placeholder appointment so the lead appears as 'agendado' but without real data.
    $tituloPrefix = 'Cita - Wedding Planner: ';
    $nota = '';
    $estatus = 0;
    $eliminado = 0;
    $estatusCalendario = 0;

    if ($date_appointment && $time_appointment) {
        // If an agent was provided, check availability for that agent
        if ($idusu > 0) {
            $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM calendario WHERE fecha = ? AND hora = ? AND idusu = ? AND eliminado = 0");
            $stmt->bind_param('ssi', $date_appointment, $time_appointment, $idusu);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            if ($row && intval($row['cnt']) > 0) {
                throw new Exception('El agente no está disponible en ese horario');
            }
        }

        $titulo = $tituloPrefix . substr($full_name,0,120);
        // include fecha_cliente and hora_cliente for schemas that require them
        $use_idusu = ($idusu > 0) ? $idusu : 0;
        $stmt = $conn->prepare("INSERT INTO calendario (idusu, fecha, fecha_cliente, hora, hora_cliente, titulo, nota, idclie, estatus, eliminado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssssiii', $use_idusu, $date_appointment, $date_appointment, $time_appointment, $time_appointment, $titulo, $nota, $cf_id, $estatusCalendario, $eliminado);
        if (!$stmt->execute()) throw new Exception('Error al insertar en calendario: ' . $stmt->error);
        $cal_id = $stmt->insert_id;
        $stmt->close();
    } else {
        // Placeholder appointment: mark as agendado but without real agent/date/time
        $fecha_default = date('Y-m-d');
        $hora_default = '00:00:00';
        $titulo = 'Cita pendiente - sin datos: ' . substr($full_name,0,80);
        $placeholder_idusu = 0;
 // estatus 'agendado' para que aparezca como cita programada, aunque no tenga fecha/hora real
        $stmt = $conn->prepare("INSERT INTO calendario (idusu, fecha, fecha_cliente, hora, hora_cliente, titulo, nota, idclie, estatus, eliminado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssssiii', $placeholder_idusu, $fecha_default, $fecha_default, $hora_default, $hora_default, $titulo, $nota, $cf_id, $estatusCalendario, $eliminado);
        if (!$stmt->execute()) throw new Exception('Error al insertar calendario placeholder: ' . $stmt->error);
        $cal_id = $stmt->insert_id;
        $stmt->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'wedding_planner_id' => $wp_id, 'contact_form_id' => $cf_id ?? null, 'calendario_id' => $cal_id ?? null]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>