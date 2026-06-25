<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'conn.php';

function ensureColumnExists($conn, $table, $columnName, $columnDef) {
    $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $conn->real_escape_string($table) . "' AND COLUMN_NAME = '" . $conn->real_escape_string($columnName) . "'";
    $res = $conn->query($checkSql);
    if ($res) {
        $row = $res->fetch_assoc();
        if ((int) ($row['c'] ?? 0) === 0) {
            $alter = "ALTER TABLE `" . $conn->real_escape_string($table) . "` ADD COLUMN " . $columnDef;
            if (!$conn->query($alter)) {
                throw new Exception('No se pudo crear la columna ' . $columnName . ': ' . $conn->error);
            }
        }
    }
}

// Normalizar texto para evitar caracteres problemáticos (ej: símbolos Unicode raros)
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

$response = ['success' => false, 'message' => ''];

try {
    // Validar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Obtener y sanitizar datos
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    // Normalizar nombre para evitar caracteres problemáticos (ej: 𝕱𝖚𝖊𝖓𝖙𝖊𝖘...)
    $nombre = normalize_text($nombre);
    $correo = isset($_POST['correo']) ? trim($_POST['correo']) : '';
    $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : null;
    $country_code = isset($_POST['country_code']) ? trim($_POST['country_code']) : null;
    $telefono_local = isset($_POST['telefono_local']) ? trim($_POST['telefono_local']) : null;
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $wedding_location = isset($_POST['wedding_location']) ? trim($_POST['wedding_location']) : '';
    $wedding_date = isset($_POST['wedding_date']) ? trim($_POST['wedding_date']) : '';
    $campaign_name = isset($_POST['campaign_name']) ? trim($_POST['campaign_name']) : '';
    $platform = isset($_POST['platform']) ? trim($_POST['platform']) : '';
    $how_long_known_us = isset($_POST['how_long_known_us']) ? trim($_POST['how_long_known_us']) : '';
    $first_contact_channel = isset($_POST['first_contact_channel']) ? trim($_POST['first_contact_channel']) : '';
    $tipo_ig = isset($_POST['tipo_ig']) ? trim($_POST['tipo_ig']) : '';
    $fecha_registro = isset($_POST['fecha_registro']) ? trim($_POST['fecha_registro']) : '';
    $comentarios = isset($_POST['comentarios']) ? trim($_POST['comentarios']) : '';

    // Para IG orgánico, asignar valores por defecto cuando vienen vacíos
    $is_ig_organico = ($first_contact_channel === 'IG' && $tipo_ig === 'organico');
    if ($is_ig_organico) {
        if (empty($campaign_name)) $campaign_name = 'ig organico';
        if (empty($platform))      $platform      = 'ig';
    }

    // Validar campos obligatorios
    if (empty($nombre)) {
        throw new Exception('El nombre es obligatorio');
    }
    if (empty($campaign_name)) {
        throw new Exception('El origen (campaign_name) es obligatorio');
    }
    if (empty($platform)) {
        throw new Exception('El medio (platform) es obligatorio');
    }
    if (empty($how_long_known_us)) {
        throw new Exception('How long have you known us? es obligatorio');
    }
    if (empty($first_contact_channel)) {
        throw new Exception('First contact channel es obligatorio');
    }

    // Validar que campaign_name sea uno de los valores permitidos (alineado con consulta_leads.php)
    $campaign_names_permitidos = [
        'E10', 'b1 (USA)', 'b2 (MX)', 'b3 (Mex 2)', 'b4 (Latam)',
        'ig organico', 'prospectos', 'wp', 'whatsapp', 'mail', 'phone call', 'tiktok', 'fb',
    ];
    if (!in_array($campaign_name, $campaign_names_permitidos, true)) {
        throw new Exception('Valor de origen no válido: ' . $campaign_name);
    }

    // Validar que platform sea uno de los valores permitidos
    $platforms_permitidas = [
        'ig usa', 'ig mexico', 'ig mex 2', 'ig latam', 'ig',
        'prospectos', 'wp', 'fb', 'whatsapp', 'mail', 'phone call', 'tiktok',
    ];
    if (!in_array($platform, $platforms_permitidas)) {
        throw new Exception('Valor de medio no válido: ' . $platform);
    }

    // Valores del formulario web; aceptar también etiquetas legacy del modal anterior
    $how_long_legacy_map = [
        'Less than 3 months'           => 'Less than 6 months',
        'Between 3 months and 1 year'  => 'More than 6 months',
        'More than 1 year'             => 'More than 6 months',
    ];
    if (isset($how_long_legacy_map[$how_long_known_us])) {
        $how_long_known_us = $how_long_legacy_map[$how_long_known_us];
    }

    $how_long_known_us_permitidos = ['Less than 6 months', 'More than 6 months', 'Not asked'];
    if (!in_array($how_long_known_us, $how_long_known_us_permitidos, true)) {
        throw new Exception('Valor de How long have you known us? no válido: ' . $how_long_known_us);
    }

    $first_contact_channel_permitidos = ['WhatsApp', 'Instagram DM – Campaign', 'Instagram DM – Organic', 'IG', 'Email', 'Phone call', 'TikTok'];
    if (!in_array($first_contact_channel, $first_contact_channel_permitidos, true)) {
        throw new Exception('Valor de First contact channel no válido: ' . $first_contact_channel);
    }

    // Validar tipo_ig si el canal es IG
    if ($first_contact_channel === 'IG') {
        $tipo_ig_permitidos = ['organico', 'campana'];
        if (!in_array($tipo_ig, $tipo_ig_permitidos, true)) {
            throw new Exception('Tipo de IG no válido: ' . $tipo_ig);
        }
    }

    // Validar formato de email si se proporciona
    if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El formato del correo no es válido');
    }

    if ($city !== '') {
        $city = normalize_text($city);
    }

    if ($wedding_location !== '') {
        $wedding_location = normalize_text($wedding_location);
    }

    if ($wedding_date !== '') {
        $weddingDateTs = strtotime($wedding_date);
        if ($weddingDateTs === false) {
            throw new Exception('La fecha del evento no es válida');
        }
        $wedding_date = date('Y-m-d', $weddingDateTs);
    } else {
        $wedding_date = null;
    }

    // Validar telefono local y country code: si se proporciona telefono_local, debe tener exactamente 10 dígitos
    if (!empty($telefono_local)) {
        // telefono_local debe ser solo dígitos
        if (!ctype_digit($telefono_local) || strlen($telefono_local) !== 10) {
            throw new Exception('El teléfono local debe contener exactamente 10 dígitos');
        }
        // country_code ahora es obligatorio si telefono_local está presente
        if (empty($country_code)) {
            throw new Exception('El código de país es obligatorio si se proporciona un teléfono');
        }
        // Normalizar country_code y componer telefono final sin '+'
        $country_digits = preg_replace('/\D/', '', $country_code);
        $telefono = $country_digits . $telefono_local;
    } else {
        // Si solo recibimos telefono (campo `telefono`), limpiarlo para que contenga solo dígitos
        if (!empty($telefono)) {
            $telefono = preg_replace('/\D/', '', $telefono);
        } else {
            $telefono = null;
        }
    }

    // Preparar la fecha - usar la seleccionada o la actual si no se proporciona
    if (!empty($fecha_registro)) {
        // Convertir de formato datetime-local (YYYY-MM-DDTHH:MM) a formato ISO (YYYY-MM-DD HH:MM:SS)
        $created_time = date('Y-m-d H:i:s', strtotime($fecha_registro));
    } else {
        $created_time = date('Y-m-d H:i:s');
    }

    ensureColumnExists($conn, 'organic_leads', 'how_long_known_us', "`how_long_known_us` VARCHAR(100) DEFAULT ''");
    ensureColumnExists($conn, 'organic_leads', 'first_contact_channel', "`first_contact_channel` VARCHAR(100) DEFAULT ''");
    ensureColumnExists($conn, 'organic_leads', 'tipo_ig', "`tipo_ig` VARCHAR(50) DEFAULT ''");
    ensureColumnExists($conn, 'organic_leads', 'city', "`city` VARCHAR(150) DEFAULT ''");
    ensureColumnExists($conn, 'organic_leads', 'wedding_location', "`wedding_location` VARCHAR(255) DEFAULT ''");
    ensureColumnExists($conn, 'organic_leads', 'wedding_date', "`wedding_date` DATE NULL DEFAULT NULL");
    ensureColumnExists($conn, 'organic_leads', 'registro_notas', "`registro_notas` TEXT NULL DEFAULT NULL");

    // Detectar si es una actualización (lead_id presente) o un nuevo registro
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;

    if ($lead_id > 0) {
        // ── MODO EDICIÓN: actualizar lead existente en organic_leads ──
        // Verificar que el lead exista y sea de tipo 'manual'
        $checkStmt = $conn->prepare("SELECT id FROM organic_leads WHERE id = ? AND lead_status = 'manual' LIMIT 1");
        if (!$checkStmt) {
            throw new Exception('Error al verificar el lead: ' . $conn->error);
        }
        $checkStmt->bind_param('i', $lead_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows === 0) {
            $checkStmt->close();
            throw new Exception('Lead no encontrado o no es un lead manual');
        }
        $checkStmt->close();

        $sql = "UPDATE organic_leads SET
            full_name            = ?,
            email                = ?,
            phone                = ?,
            city                 = ?,
            wedding_location     = ?,
            wedding_date         = ?,
            campaign_name        = ?,
            platform             = ?,
            created_time         = ?,
            how_long_known_us    = ?,
            first_contact_channel= ?,
            tipo_ig              = ?,
            registro_notas       = ?
        WHERE id = ? AND lead_status = 'manual'";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar la actualización: ' . $conn->error);
        }

        $stmt->bind_param('sssssssssssssi', $nombre, $correo, $telefono, $city, $wedding_location, $wedding_date, $campaign_name, $platform, $created_time, $how_long_known_us, $first_contact_channel, $tipo_ig, $comentarios, $lead_id);

        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar el lead: ' . $stmt->error);
        }
        $stmt->close();

        $response['success'] = true;
        $response['message'] = 'Lead actualizado correctamente';
        $response['id'] = $lead_id;
        $response['mode'] = 'update';
        $response['tipo_ig'] = $tipo_ig;
    } else {
        // ── MODO CREACIÓN: insertar nuevo lead en organic_leads ──
        $sql = "INSERT INTO organic_leads (
            full_name,
            email,
            phone,
            city,
            wedding_location,
            wedding_date,
            campaign_name,
            platform,
            created_time,
            how_long_known_us,
            first_contact_channel,
            tipo_ig,
            registro_notas,
            is_organic,
            lead_status,
            correo_uno_enviado,
            correo_dos_enviado,
            usuario_asignado,
            descartado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'true', 'manual', 0, 0, 0, 0)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }

        $stmt->bind_param('sssssssssssss', $nombre, $correo, $telefono, $city, $wedding_location, $wedding_date, $campaign_name, $platform, $created_time, $how_long_known_us, $first_contact_channel, $tipo_ig, $comentarios);

        if (!$stmt->execute()) {
            // Verificar si es error de duplicado de email
            if ($conn->errno == 1062) {
                throw new Exception('Ya existe un lead con ese correo electrónico');
            }
            throw new Exception('Error al guardar el lead: ' . $stmt->error);
        }

        $newId = $conn->insert_id;
        $stmt->close();

        $response['success'] = true;
        $response['message'] = 'Lead registrado correctamente';
        $response['id'] = $newId;
        $response['mode'] = 'insert';
        $response['tipo_ig'] = $tipo_ig;
        $response['link'] = 'https://citas.efegepho.com.mx/formulario_organic.php?tabla_origen=organic_leads&id=' . $newId;
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>
