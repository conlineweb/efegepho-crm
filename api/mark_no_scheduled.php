<?php
// API: mark_no_scheduled.php
// Marks a lead as "estatus_llamada = 1" to indicate they did not schedule an appointment
// Finds the lead by phone number across all lead tables

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

include __DIR__ . '/../conn.php';

$response = ['success' => false, 'message' => '', 'data' => []];

// Build debug data
$debug = [];
$debug['timestamp'] = date('c');
$debug['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$debug['request_method'] = $_SERVER['REQUEST_METHOD'] ?? 'unknown';

// GET, POST, and JSON body
$requestParams = [];
$rawBody = file_get_contents('php://input');
$decodedJson = null;

$debug['raw_body'] = $rawBody;

if (!empty($rawBody)) {
    $json = json_decode($rawBody, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $decodedJson = $json;
        $debug['json_decoded'] = true;
    } else {
        $debug['json_error'] = json_last_error_msg();
    }
}

// prefer GET, otherwise POST, otherwise JSON
if (!empty($_GET)) {
    $requestParams = $_GET;
    $debug['source'] = 'GET';
} elseif (!empty($_POST)) {
    $requestParams = $_POST;
    $debug['source'] = 'POST';
} elseif (!empty($decodedJson)) {
    $requestParams = $decodedJson;
    $debug['source'] = 'JSON';
}

$debug['request_params'] = $requestParams;

// ========================================================================
// EXTRAER TELÉFONO DE LA NUEVA ESTRUCTURA
// La nueva estructura es: json.payload.client_phone
// También buscamos en json.call_metadata.user_phone_number como fallback
// ========================================================================
$client_phone = null;

// Intentar obtener de la estructura nueva (json.payload.client_phone)
if (isset($requestParams['payload']['client_phone'])) {
    $client_phone = $requestParams['payload']['client_phone'];
} 
// Fallback a call_metadata.user_phone_number
elseif (isset($requestParams['call_metadata']['user_phone_number'])) {
    $client_phone = $requestParams['call_metadata']['user_phone_number'];
}
// Fallback a estructura antigua por compatibilidad
elseif (isset($requestParams['client_phone'])) {
    $client_phone = $requestParams['client_phone'];
}
elseif (isset($requestParams['phone'])) {
    $client_phone = $requestParams['phone'];
}
elseif (isset($requestParams['telefono'])) {
    $client_phone = $requestParams['telefono'];
}

if (!empty($client_phone)) {
    $client_phone = urldecode($client_phone);
    // Double-decode if necessary
    if (strpos($client_phone, '%') !== false) {
        $client_phone = urldecode($client_phone);
    }
    $client_phone = trim($client_phone);
}

// Función para normalizar teléfono (solo dígitos)
function normalize_phone_for_search($phone) {
    if (empty($phone)) return '';
    // Quitar todo excepto dígitos
    return preg_replace('/\D+/', '', $phone);
}

$searchPhone = normalize_phone_for_search($client_phone);
$debug['original_phone'] = $client_phone;
$debug['search_phone_normalized'] = $searchPhone;

if (empty($searchPhone)) {
    $response['success'] = false;
    $response['message'] = 'No se proporcionó un número de teléfono válido. Se esperaba en payload.client_phone o call_metadata.user_phone_number';
    $response['debug'] = $debug;
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ========================================================================
// BUSCAR LEAD POR TELÉFONO EN TODAS LAS TABLAS DE tablas_leads
// ========================================================================
$foundLead = null;
$foundLeadTable = null;

try {
    // Obtener todas las tablas de leads
    $tablas = [];
    $sqlTablas = "SELECT nombre FROM tablas_leads ORDER BY nombre";
    $resultTablas = $conn->query($sqlTablas);
    if ($resultTablas && $resultTablas->num_rows > 0) {
        while ($row = $resultTablas->fetch_assoc()) {
            $tablas[] = $row['nombre'];
        }
    }
    $debug['tablas_leads'] = $tablas;
    
    if (empty($tablas)) {
        $response['success'] = false;
        $response['message'] = 'No se encontraron tablas de leads configuradas';
        $response['debug'] = $debug;
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // Buscar en cada tabla el lead con ese teléfono
    foreach ($tablas as $tableName) {
        // Verificar que la tabla existe
        $checkTable = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
        if ($checkTable->num_rows > 0) {
            // Verificar que la columna 'phone' existe
            $columnsResult = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tableName) . "` LIKE 'phone'");
            if ($columnsResult && $columnsResult->num_rows > 0) {
                // Buscar el lead: comparamos quitando caracteres no numéricos
                $stmt = $conn->prepare("SELECT * FROM `" . $tableName . "` WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), '(', ''), ')', '') LIKE ? ORDER BY created_time DESC LIMIT 1");
                $searchPattern = '%' . $searchPhone;
                // También buscar los últimos 10 dígitos para mayor flexibilidad
                if (strlen($searchPhone) > 10) {
                    $searchPattern = '%' . substr($searchPhone, -10);
                }
                $stmt->bind_param('s', $searchPattern);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $foundLead = $res->fetch_assoc();
                    $foundLeadTable = $tableName;
                    $stmt->close();
                    break; // Encontramos el lead, salimos del loop
                }
                $stmt->close();
            }
        }
    }

    $debug['found_lead'] = $foundLead ? true : false;
    $debug['found_lead_table'] = $foundLeadTable;
    
    if (!$foundLead) {
        $response['success'] = false;
        $response['message'] = 'No se encontró ningún lead con el teléfono proporcionado: ' . $client_phone;
        $response['debug'] = $debug;
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // ========================================================================
    // VERIFICAR QUE EXISTE LA COLUMNA estatus_llamada EN LA TABLA
    // ========================================================================
    $columnExists = false;
    $checkColumn = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($foundLeadTable) . "` LIKE 'estatus_llamada'");
    if ($checkColumn && $checkColumn->num_rows > 0) {
        $columnExists = true;
    }
    
    $debug['column_estatus_llamada_exists'] = $columnExists;
    
    // Si la columna no existe, intentar crearla
    if (!$columnExists) {
        $alterQuery = "ALTER TABLE `" . $conn->real_escape_string($foundLeadTable) . "` ADD COLUMN `estatus_llamada` TINYINT(3) DEFAULT 0";
        $alterResult = $conn->query($alterQuery);
        if ($alterResult) {
            $debug['column_created'] = true;
            $columnExists = true;
        } else {
            $response['success'] = false;
            $response['message'] = 'No se pudo crear la columna estatus_llamada en la tabla ' . $foundLeadTable . ': ' . $conn->error;
            $response['debug'] = $debug;
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }
    
    // ========================================================================
    // ACTUALIZAR EL CAMPO estatus_llamada = 1
    // ========================================================================
    $leadId = $foundLead['id'] ?? null;
    if (empty($leadId)) {
        $response['success'] = false;
        $response['message'] = 'El lead encontrado no tiene un ID válido';
        $response['debug'] = $debug;
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    $updateStmt = $conn->prepare("UPDATE `" . $foundLeadTable . "` SET estatus_llamada = 3 WHERE id = ?");
    $updateStmt->bind_param('i', $leadId);
    $updateResult = $updateStmt->execute();
    $affectedRows = $updateStmt->affected_rows;
    $updateStmt->close();
    
    $debug['update_result'] = $updateResult;
    $debug['affected_rows'] = $affectedRows;
    
    if ($updateResult) {
        $response['success'] = true;
        $response['message'] = 'Lead marcado como "no agendó" exitosamente';
        $response['data'] = [
            'lead_id' => $leadId,
            'lead_table' => $foundLeadTable,
            'lead_name' => $foundLead['full_name'] ?? '',
            'lead_phone' => $foundLead['phone'] ?? '',
            'lead_email' => $foundLead['email'] ?? '',
            'estatus_llamada' => 1,
            'affected_rows' => $affectedRows
        ];
    } else {
        $response['success'] = false;
        $response['message'] = 'Error al actualizar el lead: ' . $conn->error;
    }
    
    $response['debug'] = $debug;
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
    $response['debug'] = $debug;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>