<?php
// Debugging helpers - show errors while debugging. Remove or guard in production.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Mexico_City');
header('Content-Type: application/json; charset=utf-8');

include 'conn.php'; // Incluye el archivo de conexión a la base de datos

// Verifica si la conexión a la base de datos fue exitosa
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibir los datos en formato JSON
    $data = json_decode(file_get_contents('php://input'), true);

    // Verifica si se recibió el JSON correctamente
    if (!$data) {
        echo json_encode(["success" => false, "message" => "Error en los datos enviados.", 'debug' => 'JSON parse failed or empty body']);
        exit;
    }

    // Asignar los datos a las variables
    $nombre = $data['names'];
    $correo = $data['email_address'];
    $telefono = $data['telephone'];
    $country_code = $data['country_code'];
    $wedding_date = $data['wedding_date'];
    // Normalize vendor ID - if missing or not numeric, default to 0
    $id_vendedor_asignado = isset($data['id_vendedor_asignado']) && is_numeric($data['id_vendedor_asignado']) ? intval($data['id_vendedor_asignado']) : 0;
    $fecha_cambio_cliente = date('Y-m-d');
    // Accept optional submission_date (datetime) from client; otherwise use now
    $submission_date = isset($data['submission_date']) && trim($data['submission_date']) !== '' ? trim($data['submission_date']) : date('Y-m-d H:i:s');
    $cliente = 1;
    $manual = 1;

    // Campos adicionales para registro manual: form_name y campaign_name
    $form_name = trim($data['form_name'] ?? '');
    $campaign_name = trim($data['campaign_name'] ?? '');

    // Normalizar campaign_name: si viene vacío, nulo o 'undefined'/'null' usar 'reg manual'
    if ($campaign_name === '' || strtolower($campaign_name) === 'undefined' || strtolower($campaign_name) === 'null') {
        $campaign_name = 'reg manual';
    }

    // Normalizar form_name: asegurar que contiene 'reg manual'
    $needle = 'reg manual';
    if ($form_name === '' || strtolower($form_name) === 'undefined' || strtolower($form_name) === 'null') {
        $form_name = $needle;
    } else {
        if (stripos($form_name, $needle) === false) {
            $form_name = trim($form_name . ' ' . $needle);
        }
    }

    error_log('Manual Normalize - campaign_name: ' . $campaign_name . ' | form_name: ' . $form_name);

    // Asegurarnos que las columnas existen (intentar crearlas si no existen)
    function ensureColumnExists($conn, $table, $columnName, $columnDef) {
        $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $conn->real_escape_string($table) . "' AND COLUMN_NAME = '" . $conn->real_escape_string($columnName) . "'";
        $res = $conn->query($checkSql);
        if ($res) {
            $row = $res->fetch_assoc();
            if ((int)$row['c'] === 0) {
                $alter = "ALTER TABLE `" . $conn->real_escape_string($table) . "` ADD COLUMN " . $columnDef;
                if (!$conn->query($alter)) {
                    error_log('No se pudo crear columna ' . $columnName . ': ' . $conn->error);
                } else {
                    error_log('Columna creada: ' . $columnName);
                }
            }
        }
    }

    ensureColumnExists($conn, 'contact_form', 'form_name', "`form_name` VARCHAR(255) DEFAULT ''");
    ensureColumnExists($conn, 'contact_form', 'campaign_name', "`campaign_name` VARCHAR(255) DEFAULT ''");

    // Antes de insertar, verificar si ya existe un cliente con el mismo nombre o correo
    $checkStmt = $conn->prepare("SELECT id, names, email_address FROM contact_form WHERE cliente = 1 AND (LOWER(names) = LOWER(?) OR LOWER(email_address) = LOWER(?)) LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param('ss', $nombre, $correo);
        $checkStmt->execute();
        $resCheck = $checkStmt->get_result();
        $existing = $resCheck->fetch_assoc();
        $checkStmt->close();
        if ($existing) {
            $exists_name = (strcasecmp(trim($existing['names']), trim($nombre)) === 0);
            $exists_email = (strcasecmp(trim($existing['email_address']), trim($correo)) === 0);
            $msgParts = [];
            if ($exists_name) $msgParts[] = 'nombre';
            if ($exists_email) $msgParts[] = 'email';
            echo json_encode(["success" => false, "message" => 'Ya existe cliente con el mismo ' . implode(' y ', $msgParts), 'exists_name' => $exists_name, 'exists_email' => $exists_email, 'existing' => $existing]);
            exit;
        }
    }

    // Preparar la consulta SQL (usando sentencias preparadas para prevenir inyección SQL)
    $stmt = $conn->prepare("INSERT INTO contact_form (names, email_address, telephone, country_code, wedding_date, cliente, id_vendedor_asignado, fecha_cambio_cliente, manual, submission_date, form_name, campaign_name) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        // Si la preparación falla, mostrar el error
        error_log("Error al preparar la consulta: " . $conn->error);
        echo json_encode(["success" => false, "message" => "Error al preparar la consulta."]);
        exit;
    }

    // Vincular los parámetros
    if (!$stmt->bind_param("sssssiisisss", $nombre, $correo, $telefono, $country_code, $wedding_date, $cliente, $id_vendedor_asignado, $fecha_cambio_cliente, $manual, $submission_date, $form_name, $campaign_name)) {
        // Si falla el bind_param
        error_log("Error al vincular los parámetros: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Error al vincular los parámetros."]);
        exit;
    }

    // Ejecutar la consulta
    if ($stmt->execute()) {
        // Registro exitoso
        echo json_encode(["success" => true, "message" => "Registro exitoso", 'id' => $stmt->insert_id]);
    } else {
        // Error al ejecutar la consulta
        error_log("Error al ejecutar la consulta: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Error en la inserción de datos", 'debug' => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    // Si no es una petición POST, responde con error
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
}
?>
