<?php
// Buffer output so any accidental echoes / PHP warnings don't break JSON
ob_start();
ini_set('display_errors', 0); // No mostrar errores en pantalla
error_reporting(E_ALL);
include 'conn.php';

function normalizeAppointmentTime($time) {
    $time = trim((string)$time);
    if ($time === '') {
        return '';
    }

    $parts = explode(':', $time);
    if (count($parts) < 2) {
        return $time;
    }

    $hour = str_pad((string)((int)$parts[0]), 2, '0', STR_PAD_LEFT);
    $minute = str_pad((string)((int)$parts[1]), 2, '0', STR_PAD_LEFT);

    return $hour . ':' . $minute;
}

function getAvailableVendorsForSlot($conn, $date, $time) {
    $normalizedTime = normalizeAppointmentTime($time);
    if ($date === '' || $normalizedTime === '') {
        return [];
    }

    $occupiedVendorIds = [];
    $stmtBusy = $conn->prepare("SELECT hora, idusu FROM calendario WHERE fecha = ?");
    if ($stmtBusy) {
        $stmtBusy->bind_param('s', $date);
        $stmtBusy->execute();
        $busyRows = $stmtBusy->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtBusy->close();

        foreach ($busyRows as $busyRow) {
            if (normalizeAppointmentTime($busyRow['hora'] ?? '') === $normalizedTime) {
                $occupiedVendorIds[(int)$busyRow['idusu']] = true;
            }
        }
    }

    $availableVendorIds = [];
    $stmtAttention = $conn->prepare("SELECT horarios, idusu FROM atencion WHERE dia = ?");
    if ($stmtAttention) {
        $stmtAttention->bind_param('s', $date);
        $stmtAttention->execute();
        $attentionRows = $stmtAttention->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtAttention->close();

        foreach ($attentionRows as $vendor) {
            $vendorId = (int)($vendor['idusu'] ?? 0);
            if ($vendorId <= 0 || isset($occupiedVendorIds[$vendorId])) {
                continue;
            }

            $vendorSchedules = json_decode($vendor['horarios'] ?? '[]', true);
            if (!is_array($vendorSchedules)) {
                continue;
            }

            foreach ($vendorSchedules as $scheduleTime) {
                if (normalizeAppointmentTime($scheduleTime) === $normalizedTime) {
                    $availableVendorIds[$vendorId] = $vendorId;
                    break;
                }
            }
        }
    }

    return array_values($availableVendorIds);
}

function tableHasColumn($conn, $table, $columnName) {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($columnName);
    $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $safeTable . "' AND COLUMN_NAME = '" . $safeColumn . "'";
    $res = $conn->query($checkSql);
    if (!$res) {
        error_log('No se pudo verificar columna ' . $columnName . ' en ' . $table . ': ' . $conn->error);
        return false;
    }

    $row = $res->fetch_assoc();
    return ((int)($row['c'] ?? 0)) > 0;
}

function ensureTextColumnForTracking($conn, $table, $columnName, $afterColumn = '') {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $columnName)) {
        error_log('Nombre de tabla/columna invalido para tracking: ' . $table . '.' . $columnName);
        return;
    }

    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($columnName);
    $checkSql = "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $safeTable . "' AND COLUMN_NAME = '" . $safeColumn . "' LIMIT 1";
    $res = $conn->query($checkSql);

    if (!$res) {
        error_log('No se pudo verificar columna tracking ' . $columnName . ' en ' . $table . ': ' . $conn->error);
        return;
    }

    if ($res->num_rows === 0) {
        $afterSql = '';
        if ($afterColumn !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $afterColumn)) {
            $afterSql = " AFTER `" . $conn->real_escape_string($afterColumn) . "`";
        }

        $alter = "ALTER TABLE `" . $safeTable . "` ADD COLUMN `" . $safeColumn . "` TEXT" . $afterSql;
        if (!$conn->query($alter)) {
            error_log('No se pudo crear columna tracking ' . $columnName . ' en ' . $table . ': ' . $conn->error);
        }

        return;
    }

    $row = $res->fetch_assoc();
    $dataType = strtolower((string)($row['DATA_TYPE'] ?? ''));
    if (!in_array($dataType, ['text', 'mediumtext', 'longtext'], true)) {
        $alter = "ALTER TABLE `" . $safeTable . "` MODIFY COLUMN `" . $safeColumn . "` TEXT";
        if (!$conn->query($alter)) {
            error_log('No se pudo ampliar columna tracking ' . $columnName . ' en ' . $table . ': ' . $conn->error);
        }
    }
}

/**
 * Convierte errores técnicos de MySQL a mensajes amigables para el usuario
 * y registra el error completo en el log
 * Retorna un array con 'message' e 'icon' (warning para validación, error para sistema)
 */
function handleDatabaseError($technicalError, $context = '') {
    // Registrar el error técnico completo para debugging
    error_log("DB Error [$context]: " . $technicalError);
    
    // Detectar tipos específicos de error y devolver mensajes amigables con el icono apropiado
    if (stripos($technicalError, 'Duplicate entry') !== false) {
        // Errores de duplicado son de VALIDACIÓN (warning)
        if (stripos($technicalError, 'email') !== false || stripos($technicalError, 'idx_email') !== false) {
            return [
                'message' => 'Este correo electrónico ya ha sido registrado previamente. Si ya agendaste una cita, revisa tu bandeja de entrada. Si necesitas hacer cambios, contáctanos directamente.',
                'icon' => 'warning'
            ];
        } elseif (stripos($technicalError, 'phone') !== false || stripos($technicalError, 'telephone') !== false) {
            return [
                'message' => 'Este número de teléfono ya está registrado en nuestro sistema. Por favor verifica tus datos o contáctanos para asistencia.',
                'icon' => 'warning'
            ];
        } else {
            return [
                'message' => 'Esta información ya existe en nuestros registros. Por favor, verifica tus datos o contáctanos si necesitas ayuda.',
                'icon' => 'warning'
            ];
        }
    }
    
    // Errores de sistema (error)
    if (stripos($technicalError, 'foreign key') !== false) {
        return [
            'message' => 'Hubo un problema al procesar tu información. Por favor, intenta nuevamente.',
            'icon' => 'error'
        ];
    }
    
    if (stripos($technicalError, 'syntax error') !== false) {
        return [
            'message' => 'Hubo un error al procesar tu solicitud. Nuestro equipo ha sido notificado.',
            'icon' => 'error'
        ];
    }
    
    if (stripos($technicalError, 'connection') !== false || stripos($technicalError, 'server') !== false) {
        return [
            'message' => 'No pudimos conectar con nuestro servidor. Por favor, intenta nuevamente en unos momentos.',
            'icon' => 'error'
        ];
    }
    
    // Error genérico de sistema
    return [
        'message' => 'Hubo un problema al procesar tu solicitud. Por favor, intenta nuevamente o contáctanos para asistencia.',
        'icon' => 'error'
    ];
}

// Verificar la conexión
if ($conn->connect_error) {
    $technicalError = 'Database connection failed: ' . $conn->connect_error;
    error_log($technicalError);
    $response = [
        'success' => false, 
        'error' => 'No pudimos conectar con nuestro servidor. Por favor, intenta nuevamente en unos momentos.',
        'icon' => 'error'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
$response = [];

$date_appointment = trim($_POST['date_appointment'] ?? '');
$time_appointment = normalizeAppointmentTime($_POST['time_appointment'] ?? '');
$vendedores = getAvailableVendorsForSlot($conn, $date_appointment, $time_appointment);



if (count($vendedores) > 0) {
    $sql = "SELECT IFNULL(MAX(id), 0) + 1 AS next_id FROM contact_form";
    $result = $conn->query($sql);
    $id = "0";

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id = $row['next_id'];
        // echo "El próximo ID será: " . $next_id;
    } else {
        $response['success'] = false;
    }

    // Obtener los datos del formulario

    $names = trim($_POST['names'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email_address = trim($_POST['email'] ?? '');
    $wedding_date = trim($_POST['wedding_date'] ?? '');
    $wedding_location = trim($_POST['wedding_location'] ?? '');
    $wedding_planner = trim($_POST['wedding_planner'] ?? '');
    $guests_count = intval($_POST['guests'] ?? 0); // Convertir a entero
    $how_did_you_meet = trim($_POST['meet'] ?? '');
    $couple_activities = trim($_POST['couple_activities'] ?? '');
    $favorite_movie_or_song = trim($_POST['favorite_movie_song'] ?? '');
    $look_preference = trim($_POST['photo_style'] ?? '');
    $hear_about_us = trim($_POST['how_did_hear'] ?? '');
    $instagram_handle = trim($_POST['instagram'] ?? '');
    $service_interest = trim($_POST['service'] ?? '');
    $additional_details = trim($_POST['details'] ?? '');
    $submission_date = date("Y-m-d H:i:s");
    $country_code = trim($_POST['country_code'] ?? '');
    $time_appointment = normalizeAppointmentTime($_POST['time_appointment'] ?? '');
    $time_appointment_cust = trim($_POST['time_appointment_cust'] ?? '');
    $date_appointment = trim($_POST['date_appointment'] ?? '');
    $date_appointment_cust = trim($_POST['date_appointment_cust'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $how_long_known_us = trim($_POST['how_long_known_us'] ?? '');
    $first_contact_channel = trim($_POST['first_contact_channel'] ?? '');
    $referrer_url = trim($_POST['referrer_url'] ?? '');
    $tabla_origen = trim($_POST['tabla_origen'] ?? '');

    // Leer tipo_ig directamente de la BD para evitar manipulación del campo hidden
    $tipo_ig = '';
    $raw_tabla = $tabla_origen;
    $raw_orig_id = intval($_POST['original_lead_id'] ?? 0);
    if (
        $raw_orig_id > 0 &&
        $raw_tabla !== '' &&
        preg_match('/^[a-zA-Z0-9_]+$/', $raw_tabla) &&
        tableHasColumn($conn, $raw_tabla, 'tipo_ig')
    ) {
        $stmt_tig = $conn->prepare("SELECT tipo_ig FROM `$raw_tabla` WHERE id = ? LIMIT 1");
        if ($stmt_tig) {
            $stmt_tig->bind_param('i', $raw_orig_id);
            $stmt_tig->execute();
            $res_tig = $stmt_tig->get_result();
            if ($row_tig = $res_tig->fetch_assoc()) {
                $tipo_ig = trim($row_tig['tipo_ig'] ?? '');
            }
            $stmt_tig->close();
        }
    } elseif ($raw_orig_id > 0 && $raw_tabla !== '') {
        error_log('Se omite lectura de tipo_ig para tabla_origen=' . $raw_tabla . ' porque la columna no existe o el nombre no es valido');
    }

    // Auto-asignar hear_about_us y how_did_you_meet según canal de origen
    if (
        $first_contact_channel === 'leadform' ||
        ($first_contact_channel === 'IG' && strtolower($tipo_ig) === 'campana')
    ) {
        $hear_about_us    = '1';
        $how_did_you_meet = '3';
    }
    // IG organico: sin origen asignado -> queda vacio -> 'Origen por confirmar'

    // Fallback: si how_did_you_meet sigue vacío, inferir desde tabla_origen
    if ($how_did_you_meet === '') {
        $tabla_origen_normalized = strtolower((string)$tabla_origen);
        if (in_array($tabla_origen_normalized, ['wedding_planners', 'eventos_wp', 'wp_eventos_afianzados'], true)) {
            $how_did_you_meet = '1'; // Wedding Planner
        }
    }

    // Validación de fecha_cliente: si está vacía, usar la misma fecha de date_appointment
    if (empty($date_appointment_cust) || $date_appointment_cust === '0000-00-00') {
        error_log('fecha_cliente vacía, usando date_appointment como fallback');
        $date_appointment_cust = $date_appointment;
    }

    error_log('Valor recibido de desde_publicidad: ' . ($_POST['desde_publicidad'] ?? 'No recibido'));
    $desde_publicidad = (int) ($_POST['desde_publicidad'] ?? 0);
    error_log('Valor convertido de desde_publicidad: ' . $desde_publicidad);
    $zona_horaria = intval($_POST['zona_horaria'] ?? 0);

    error_log('date_appointment_cust final: ' . $date_appointment_cust);

    // Información adicional de timezone y cliente para depuración
    $timezone_name = trim($_POST['timezone_name'] ?? '');
    $timezone_offset_min = intval($_POST['timezone_offset_min'] ?? 0); // Valor tal como viene de JS getTimezoneOffset()
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $forwarded_for = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Verificar si viene desde formulario_website.php
    $from_website_form = isset($_POST['from_website_form']) && $_POST['from_website_form'] == '1';

    // === PASO 1: INSERTAR PRIMERO EN form_website (SOLO SI VIENE DE formulario_website.php) ===
    $form_website_id = 0;
    $original_lead_id = 0;
    $campaign_name = '';
    $form_name = '';
    $tabla_origen = '';

    if ($from_website_form) {
        // Crear created_time en formato ISO 8601 con zona horaria
        $created_time_utc = new DateTime('now', new DateTimeZone('UTC'));
    // Ajustar a la zona horaria del servidor o la del cliente
    // Usando la zona del servidor como ejemplo (puedes ajustar según necesidad)
    if (!empty($timezone_name) && in_array($timezone_name, timezone_identifiers_list())) {
        $created_time_utc->setTimezone(new DateTimeZone($timezone_name));
    } else {
        // Usar zona horaria por defecto del servidor
        $created_time_utc->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }
    $created_time = $created_time_utc->format('c'); // Formato ISO 8601 (ej: 2025-11-05T16:31:40-05:00)
    
    // Preparar los valores para form_website
    $fw_campaign_name = 'website';
    $fw_form_name = 'form_website';
    $fw_when_married = $wedding_date; // Fecha de la boda
    $fw_email = $email_address;
    $fw_full_name = $names;
    $fw_phone = $country_code . $telephone;
    $fw_fecha_importacion = date('Y-m-d H:i:s');
    ensureTextColumnForTracking($conn, 'form_website', 'referrer_url', 'phone');
    $form_website_has_referrer_url = tableHasColumn($conn, 'form_website', 'referrer_url');
    
    // Insertar en form_website
    if ($form_website_has_referrer_url) {
        $sql_fw = "INSERT INTO form_website 
            (created_time, campaign_name, form_name, when_are_you_getting_married_, email, full_name, phone, referrer_url, fecha_importacion) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    } else {
        $sql_fw = "INSERT INTO form_website 
            (created_time, campaign_name, form_name, when_are_you_getting_married_, email, full_name, phone, fecha_importacion) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    }
    
    $stmt_fw = $conn->prepare($sql_fw);
    
    if ($stmt_fw === false) {
        $errorInfo = handleDatabaseError($conn->error, 'form_website prepare');
        $response['success'] = false;
        $response['error'] = $errorInfo['message'];
        $response['icon'] = $errorInfo['icon'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    if ($form_website_has_referrer_url) {
        $stmt_fw->bind_param("sssssssss", 
            $created_time, 
            $fw_campaign_name, 
            $fw_form_name, 
            $fw_when_married, 
            $fw_email, 
            $fw_full_name, 
            $fw_phone, 
            $referrer_url,
            $fw_fecha_importacion
        );
    } else {
        $stmt_fw->bind_param("ssssssss", 
            $created_time, 
            $fw_campaign_name, 
            $fw_form_name, 
            $fw_when_married, 
            $fw_email, 
            $fw_full_name, 
            $fw_phone, 
            $fw_fecha_importacion
        );
    }
    
    try {
        if (!$stmt_fw->execute()) {
            $errorInfo = handleDatabaseError($stmt_fw->error, 'form_website insert');
            $response['success'] = false;
            $response['error'] = $errorInfo['message'];
            $response['icon'] = $errorInfo['icon'];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    } catch (mysqli_sql_exception $e) {
        $errorInfo = handleDatabaseError($e->getMessage(), 'form_website insert exception');
        $response['success'] = false;
        $response['error'] = $errorInfo['message'];
        $response['icon'] = $errorInfo['icon'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
        // Obtener el ID insertado en form_website
        $form_website_id = $stmt_fw->insert_id;
        $stmt_fw->close();
        
        error_log('Registro insertado en form_website con ID: ' . $form_website_id);
        
        // Ahora asignar los valores para contact_form
        $original_lead_id = $form_website_id;
        $campaign_name = 'website';
        $form_name = 'form_website';
        $tabla_origen = 'form_website';
    } else {
        // Si no viene de formulario_website.php, usar los valores del POST o valores por defecto
        $original_lead_id = trim($_POST['original_lead_id'] ?? 0);
        $campaign_name = trim($_POST['campaign_name'] ?? '');
        $form_name = trim($_POST['form_name'] ?? '');
        $tabla_origen = trim($_POST['tabla_origen'] ?? '');
    }
    
    // === FIN PASO 1 ===

    // Helper: asegurarnos que las columnas existen (intentamos añadirlas si no existen)
    function ensureColumnExists($conn, $table, $columnName, $columnDef) {
        $db = $conn->real_escape_string($conn->query("SELECT DATABASE() AS db")->fetch_assoc()['db'] ?? '');
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

    // Intentar crear columnas de depuración si no existen
    ensureColumnExists($conn, 'contact_form', 'timezone_name', "`timezone_name` VARCHAR(100) DEFAULT ''");
    ensureColumnExists($conn, 'contact_form', 'timezone_offset_minutes', "`timezone_offset_minutes` INT DEFAULT 0");
    ensureColumnExists($conn, 'contact_form', 'client_ip', "`client_ip` VARCHAR(45) DEFAULT ''");
    ensureColumnExists($conn, 'contact_form', 'forwarded_for', "`forwarded_for` VARCHAR(255) DEFAULT ''");
    ensureColumnExists($conn, 'contact_form', 'user_agent', "`user_agent` TEXT");
    ensureColumnExists($conn, 'contact_form', 'appointment_utc', "`appointment_utc` DATETIME NULL");
    ensureColumnExists($conn, 'contact_form', 'how_long_known_us', "`how_long_known_us` VARCHAR(100) DEFAULT ''");
    ensureColumnExists($conn, 'contact_form', 'first_contact_channel', "`first_contact_channel` VARCHAR(100) DEFAULT ''");
    ensureTextColumnForTracking($conn, 'contact_form', 'referrer_url', 'first_contact_channel');
    ensureColumnExists($conn, 'contact_form', 'marketing_template_id', "`marketing_template_id` INT NULL");
    ensureColumnExists($conn, 'contact_form', 'marketing_email_click_id', "`marketing_email_click_id` INT NULL");
    ensureColumnExists($conn, 'contact_form', 'marketing_click_at', "`marketing_click_at` DATETIME NULL");
    // calendario
    ensureColumnExists($conn, 'calendario', 'appointment_utc', "`appointment_utc` DATETIME NULL");

    $marketingTemplateId = null;
    $marketingEmailClickId = null;
    $marketingClickAt = null;
    $marketingEmailClickIdInput = intval($_POST['marketing_email_click_id'] ?? 0);

    if ($marketingEmailClickIdInput > 0) {
        $stmtClickAttr = $conn->prepare("SELECT id, lead_id, tabla_origen, template_id, clicked_at FROM email_clicks WHERE id = ? LIMIT 1");
        if ($stmtClickAttr) {
            $stmtClickAttr->bind_param('i', $marketingEmailClickIdInput);
            $stmtClickAttr->execute();
            $resClickAttr = $stmtClickAttr->get_result();
            $rowClickAttr = $resClickAttr ? $resClickAttr->fetch_assoc() : null;
            $stmtClickAttr->close();

            if ($rowClickAttr) {
                $clickLeadId = intval($rowClickAttr['lead_id'] ?? 0);
                $clickTablaOrigen = trim((string)($rowClickAttr['tabla_origen'] ?? ''));
                $clickTemplateId = intval($rowClickAttr['template_id'] ?? 0);
                if (
                    $clickLeadId > 0 &&
                    $clickTemplateId > 0 &&
                    $clickLeadId === intval($original_lead_id) &&
                    mb_strtolower($clickTablaOrigen, 'UTF-8') === mb_strtolower($tabla_origen, 'UTF-8')
                ) {
                    $marketingTemplateId = $clickTemplateId;
                    $marketingEmailClickId = intval($rowClickAttr['id'] ?? 0);
                    $marketingClickAt = !empty($rowClickAttr['clicked_at']) ? $rowClickAttr['clicked_at'] : null;
                }
            }
        }
    }

    $bookingLockAcquired = false;
    $bookingLockName = '';
    if ($date_appointment !== '' && $time_appointment !== '') {
        $bookingLockName = 'booking_slot_' . md5($date_appointment . '|' . $time_appointment);
        $stmtLock = $conn->prepare("SELECT GET_LOCK(?, 10) AS lock_status");

        if ($stmtLock === false) {
            $errorInfo = handleDatabaseError($conn->error, 'booking lock prepare');
            $response['success'] = false;
            $response['error'] = $errorInfo['message'];
            $response['icon'] = $errorInfo['icon'];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        $stmtLock->bind_param('s', $bookingLockName);
        $stmtLock->execute();
        $lockRow = $stmtLock->get_result()->fetch_assoc();
        $stmtLock->close();

        if ((int)($lockRow['lock_status'] ?? 0) !== 1) {
            $response['success'] = false;
            $response['error'] = 'No pudimos confirmar la disponibilidad de la cita. Por favor, intenta nuevamente.';
            $response['icon'] = 'warning';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        $bookingLockAcquired = true;
        $vendedores = getAvailableVendorsForSlot($conn, $date_appointment, $time_appointment);
        if (count($vendedores) === 0) {
            $response['success'] = 'full';
            if ($bookingLockAcquired) {
                $stmtUnlock = $conn->prepare("SELECT RELEASE_LOCK(?)");
                if ($stmtUnlock) {
                    $stmtUnlock->bind_param('s', $bookingLockName);
                    $stmtUnlock->execute();
                    $stmtUnlock->close();
                }
            }
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }

    $transactionStarted = $conn->begin_transaction();

    // Calcular UTC de la cita a partir de la fecha/hora del SERVIDOR (siempre México City = UTC-6).
    // IMPORTANTE: $time_appointment es la hora del VENDEDOR (zona México, UTC-6 fijo desde que México
    // eliminó el horario de verano en 2023). Se usa Etc/GMT+6 en lugar de America/Mexico_City para
    // garantizar UTC-6 fijo sin importar si el tzdata del servidor está desactualizado.
    $appointment_utc = null;
    $date_time_str = trim($date_appointment . ' ' . $time_appointment);
    try {
        $dt = new DateTime($date_time_str, new DateTimeZone('Etc/GMT+6')); // UTC-6 fijo, sin DST
        $dt->setTimezone(new DateTimeZone('UTC'));
        $appointment_utc = $dt->format('Y-m-d H:i:s');
        error_log("appointment_utc calculado (servidor Etc/GMT+6): {$date_time_str} -> UTC={$appointment_utc}");
    } catch (Exception $e) {
        error_log('Error calculando appointment_utc: ' . $e->getMessage());
        $appointment_utc = null;
    }

    // --- NUEVO: Calcular la fecha y hora en la zona horaria del cliente usando el mismo UTC ---
    // De esta forma nos aseguramos de guardar en la base de datos exactamente lo que se le envía al cliente
    $zona_horaria_manual = trim($_POST['zona_horaria_manual'] ?? '0');
    if ($zona_horaria_manual === '1') {
        // La vendedora ingresó manualmente la hora del cliente: respetar el valor sin recalcular
        // time_appointment_cust ya viene seteado correctamente desde el formulario (línea 204)
        // Solo normalizamos el formato para asegurar HH:MM
        $time_appointment_cust = normalizeAppointmentTime($time_appointment_cust !== '' ? $time_appointment_cust : $time_appointment);
        $date_appointment_cust = !empty($date_appointment_cust) ? $date_appointment_cust : $date_appointment;
        error_log('zona_horaria_manual=1: usando hora_cliente manual sin recalcular timezone. time_appointment_cust=' . $time_appointment_cust);
    } elseif (!empty($appointment_utc)) {
        try {
            $dtUtc = new DateTime($appointment_utc, new DateTimeZone('UTC'));

            if (!empty($timezone_name) && in_array($timezone_name, timezone_identifiers_list())) {
                $tzClient = new DateTimeZone($timezone_name);
                $dtClient = clone $dtUtc;
                $dtClient->setTimezone($tzClient);
            } else {
                // Fallback: no IANA tz. usamos timezone_offset_min para calcular hora local:
                $offsetMinutes = intval($timezone_offset_min);
                $dtClient = clone $dtUtc;
                if ($offsetMinutes !== 0) {
                    // local = UTC - offset_minutes
                    $minutesToApply = -$offsetMinutes;
                    $dtClient->modify(($minutesToApply > 0 ? '+' : '') . $minutesToApply . ' minutes');
                }
                // No tenemos un nombre IANA real; mantenemos timezone UTC pero con hora ajustada
                $tzClient = new DateTimeZone('UTC');
                error_log("Usando offset fallback para cliente local: offset_min={$offsetMinutes}, local=" . $dtClient->format('Y-m-d H:i:s'));
            }

            // Sobrescribimos los valores "cliente" que se guardan en BD para que coincidan
            // exactamente con lo que se envía al cliente en el correo
            $date_appointment_cust = $dtClient->format('Y-m-d');
            $time_appointment_cust = $dtClient->format('H:i');

            error_log('Computed client local datetime from appointment_utc: ' . $date_appointment_cust . ' ' . $time_appointment_cust . ' tz=' . $tzClient->getName());
        } catch (Exception $e) {
            error_log('Error calculando fecha/hora cliente desde appointment_utc: ' . $e->getMessage());
            // En caso de error mantenemos los valores enviados por el cliente (fallback)
        }
    } else {
        error_log('appointment_utc null, manteniendo valores cliente tal como fueron enviados (fallback)');
    }
    // --- FIN NUEVO ---


    // Preparar la declaración SQL (añadimos columnas de depuración)
    $sql = "INSERT INTO contact_form 
        (names, telephone,country_code, email_address, wedding_date, wedding_location, wedding_planner, guests_count, how_did_you_meet, couple_activities, favorite_movie_or_song, look_preference, instagram_handle, hear_about_us, service_interest,
        additional_details, submission_date, time_appointment, date_appointment,desde_publicidad, zona_horaria, city,original_lead_id, form_name, campaign_name, tabla_origen, referrer_url, timezone_name, timezone_offset_minutes, client_ip, forwarded_for, user_agent, appointment_utc, how_long_known_us, first_contact_channel, marketing_template_id, marketing_email_click_id, marketing_click_at) 
        VALUES (" . rtrim(str_repeat('?, ', 38), ', ') . ")";

    $stmt = $conn->prepare($sql);

    // Validar preparación de la consulta
    if ($stmt === false) {
        $errorInfo = handleDatabaseError($conn->error, 'contact_form prepare');
        $response['success'] = false;
        $response['error'] = $errorInfo['message'];
        $response['icon'] = $errorInfo['icon'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Asociar parámetros: para simplificar usamos todos como strings y dejamos que MySQL haga la conversión donde aplique
    $types = str_repeat('s', 38);
    $params = [$types,
        $names,
        $telephone,
        $country_code,
        $email_address,
        $wedding_date,
        $wedding_location,
        $wedding_planner,
        (string)$guests_count,
        $how_did_you_meet,
        $couple_activities,
        $favorite_movie_or_song,
        $look_preference,
        $instagram_handle,
        $hear_about_us,
        $service_interest,
        $additional_details,
        $submission_date,
        $time_appointment,
        $date_appointment,
        (string)$desde_publicidad,
        (string)$zona_horaria,
        $city,
        (string)$original_lead_id,
        $form_name,
        $campaign_name,
        $tabla_origen,
        $referrer_url,
        $timezone_name,
        (string)$timezone_offset_min,
        $client_ip,
        $forwarded_for,
        $user_agent,
        $appointment_utc,
        $how_long_known_us,
        $first_contact_channel,
        $marketingTemplateId,
        $marketingEmailClickId,
        $marketingClickAt
    ];

    // Helper para pasar referencias a bind_param
    function refValues($arr){
        $refs = [];
        foreach($arr as $key => $value) $refs[$key] = &$arr[$key];
        return $refs;
    }

    // Bind parameters and capture the result for error checking
    $bind_result = call_user_func_array([$stmt, 'bind_param'], refValues($params));
    if (!$bind_result) {
        $errorInfo = handleDatabaseError($stmt->error, 'contact_form bind_param');
        $response['success'] = false;
        $response['error'] = $errorInfo['message'];
        $response['icon'] = $errorInfo['icon'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Ejecutar la declaración y enviar la respuesta
    try {
        $executeSuccess = $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        $errorInfo = handleDatabaseError($e->getMessage(), 'contact_form insert exception');
        $response['success'] = false;
        $response['error'] = $errorInfo['message'];
        $response['icon'] = $errorInfo['icon'];
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if ($executeSuccess) {
        $idGenerated = $stmt->insert_id;  // This is the crucial part

        // If caller provided an idusu (force vendor) and it's in the available list, prefer it
        $incomingVendor = null;
        if (isset($_POST['idusu'])) {
            $incomingVendor = intval($_POST['idusu']);
        }
        if ($incomingVendor && in_array($incomingVendor, $vendedores)) {
            $idusu = $incomingVendor;
        } else {
            $idusu = $vendedores[array_rand($vendedores, 1)];
        }

        //mandar a la tabla calendario

        $sql_cal = "INSERT INTO calendario 
        (idusu, fecha, hora, hora_cliente, fecha_cliente, titulo, nota, idclie, appointment_utc) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_cal = $conn->prepare($sql_cal);

        if ($stmt_cal === false) {
            $errorInfo = handleDatabaseError($conn->error, 'calendario prepare');
            $response['success'] = false;
            $response['error'] = $errorInfo['message'];
            $response['icon'] = $errorInfo['icon'];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // Asignar valores a las variables
        $titulo = ''; // Título vacío
        $nota = ''; // Nota vacía

        // Asociar parámetros
        // Usamos strings para mayor compatibilidad
        $bind_cal_result = $stmt_cal->bind_param(
            "issssssis",
            $idusu,
            $date_appointment,
            $time_appointment,
            $time_appointment_cust,
            $date_appointment_cust,
            $titulo,
            $nota,
            $idGenerated,
            $appointment_utc
        );

        if (!$bind_cal_result) {
            $errorInfo = handleDatabaseError($stmt_cal->error, 'calendario bind_param');
            $response['success'] = false;
            $response['error'] = $errorInfo['message'];
            $response['icon'] = $errorInfo['icon'];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // Ejecutar la consulta
        try {
            if ($stmt_cal->execute()) {
                $response['success'] = true;
                $response['cust'] = $idGenerated;
                $response['alert'] = 1;
                $response['vendedor'] = $idusu;
            } else {
                $errorInfo = handleDatabaseError($stmt_cal->error, 'calendario insert');
                $response['success'] = false;
                $response['error'] = $errorInfo['message'];
                $response['icon'] = $errorInfo['icon'];
            }
        } catch (mysqli_sql_exception $e) {
            $errorInfo = handleDatabaseError($e->getMessage(), 'calendario insert exception');
            $response['success'] = false;
            $response['error'] = $errorInfo['message'];
            $response['icon'] = $errorInfo['icon'];
        }

        $stmt_cal->close();
    } else {
        $errorInfo = handleDatabaseError($stmt->error, 'contact_form insert');
        $response['success'] = false;
        $response['error'] = $errorInfo['message'];
        $response['icon'] = $errorInfo['icon'];
    }

    if (!empty($response['success']) && $response['success'] === true) {
        $conn->commit();
    } else {
        $conn->rollback();
    }

    if ($bookingLockAcquired) {
        $stmtUnlock = $conn->prepare("SELECT RELEASE_LOCK(?)");
        if ($stmtUnlock) {
            $stmtUnlock->bind_param('s', $bookingLockName);
            $stmtUnlock->execute();
            $stmtUnlock->close();
        }
    }
} else
    $response['success'] = 'full';

if ($stmt instanceof mysqli_stmt) {
    $stmt->close();
}
$conn->close();


// Capture any raw output (warnings, notices, accidental echoes)
$rawOutput = trim(ob_get_clean());
if ($rawOutput !== '') {
    // Include raw output for debugging so client can show it
    $response['raw_output'] = $rawOutput;
}

// Registrar información de depuración en un archivo para análisis: fechas, TZ, IP y UA
$debug_entry = [
    'timestamp' => date('c'),
    'names' => $names,
    'date_appointment' => $date_appointment,
    'time_appointment' => $time_appointment,
    'date_appointment_cust' => $date_appointment_cust,
    'time_appointment_cust' => $time_appointment_cust,
    'timezone_name' => $timezone_name,
    'timezone_offset_min' => $timezone_offset_min,
    'zona_horaria' => $zona_horaria,
    'appointment_utc' => $appointment_utc,
    'client_ip' => $client_ip,
    'forwarded_for' => $forwarded_for,
    'user_agent' => substr($user_agent, 0, 400),
    'referrer_url' => $referrer_url,
    'response' => $response,
    'first_contact_channel' => $first_contact_channel,
    'raw_output' => $rawOutput
];
file_put_contents(__DIR__ . '/appointment_debug.log', json_encode($debug_entry) . PHP_EOL, FILE_APPEND | LOCK_EX);

header('Content-Type: application/json');
echo json_encode($response);
?>
