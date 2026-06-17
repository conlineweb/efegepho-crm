<?php
// API Webhook Debug: book_slot.php
// This endpoint logs and returns the entire incoming request for debugging.

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// Basic security: if you call ?download_logs=1 and the server is not local, block it
$allow_download_logs = true; // Set to false in production if you don't want log downloads

// Build debug data
$debug = [];
$debug['timestamp'] = date('c');
$debug['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$debug['request_method'] = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
$debug['uri'] = $_SERVER['REQUEST_URI'] ?? '';
$debug['server'] = [
    'server_name' => $_SERVER['SERVER_NAME'] ?? '',
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
    'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? '',
];

// GET and POST
$debug['get'] = $_GET;
$debug['post'] = $_POST;

// Files
$debug['files'] = [];
foreach ($_FILES as $k => $file) {
    $debug['files'][$k] = [
        'name' => $file['name'],
        'type' => $file['type'],
        'size' => $file['size'],
        'tmp_name' => $file['tmp_name'],
        'error' => $file['error'],
    ];
}

// Raw body
$rawBody = file_get_contents('php://input');
$debug['raw_body'] = $rawBody;

// Try to parse JSON body
$decodedJson = null;
if (!empty($rawBody)) {
    $json = json_decode($rawBody, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $decodedJson = $json;
    }
}
if ($decodedJson) {
    $debug['json'] = $decodedJson;
}

// Headers
if (function_exists('getallheaders')) {
    $debug['headers'] = getallheaders();
} else {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
    $debug['headers'] = $headers;
}

// Optionally, parse CGI style form (application/x-www-form-urlencoded) from raw body if POST and content-type doesn't trigger PHP parsing
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (empty($debug['post']) && stripos($contentType, 'application/x-www-form-urlencoded') !== false && !empty($rawBody)) {
    parse_str($rawBody, $parsed);
    $debug['post_from_raw'] = $parsed;
}

// You might want to include server variables
$debug['server_vars'] = [
    'REQUEST_TIME' => $_SERVER['REQUEST_TIME'] ?? '',
    'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? '',
];

// --- Availability check: reuse the logic from available_slots.php / obtener-vendedor.php to determine
// if requested date and time are available and which vendor would be assigned.
// Parse parameters (prefer GET for webhook sample, but accept POST/JSON too).
include __DIR__ . '/../conn.php';

// Safety: Default to read-only mode => webhook will NOT write to the DB.
// To enable writes later, set this to false and add guarded write logic.
$READ_ONLY_MODE = false; // Always allow writes now — booking automatic when available

$requestDate = null;
$requestTime = null;
$requestParams = [];
// prefer GET, otherwise POST, otherwise JSON
if (!empty($_GET)) {
    $requestParams = $_GET;
} elseif (!empty($_POST)) {
    $requestParams = $_POST;
} elseif (!empty($decodedJson)) {
    if (isset($decodedJson['payload']) && is_array($decodedJson['payload'])) {
        $requestParams = $decodedJson['payload'];
    } else {
        $requestParams = $decodedJson;
    }
}

// Get and decode fields safely
if (!empty($requestParams['appointment_date'])) {
    $requestDate = urldecode($requestParams['appointment_date']);
}
if (!empty($requestParams['appointment_time'])) {
    $requestTime = urldecode($requestParams['appointment_time']);
    // Double decode if necessary (%253A => %3A => : )
    if (strpos($requestTime, '%') !== false) {
        $requestTime = urldecode($requestTime);
    }
}
if (empty($requestDate) && !empty($requestParams['fecha'])) {
    $requestDate = $requestParams['fecha'];
}
if (empty($requestTime) && !empty($requestParams['hora'])) {
    $requestTime = $requestParams['hora'];
}

// Normalize time to HH:MM
function normalize_time($t) {
    if (empty($t)) return null;
    // attempt multiple common formats
    $t = trim($t);
    // strip seconds
    if (strlen($t) > 5) {
        $t = substr($t, 0, 5);
    }
    // Fix single digit hour like 8:00 -> 08:00
    $parts = explode(':', $t);
    if (count($parts) >= 2) {
        $h = str_pad((int)$parts[0], 2, '0', STR_PAD_LEFT);
        $m = str_pad((int)$parts[1], 2, '0', STR_PAD_LEFT);
        return "$h:$m";
    }
    return null;
}

$availability = ['available' => false, 'vendor' => null, 'vendor_name' => null, 'reason' => null];
if ($requestDate) {
    $requestDate = trim($requestDate);
    // date normalization: expect Y-m-d
    $dt = DateTime::createFromFormat('Y-m-d', $requestDate);
    if ($dt === false) {
        // try other formats
        $dt = date_create($requestDate);
    }
    if ($dt !== false) {
        $requestDate = $dt->format('Y-m-d');
    }
}
if ($requestTime) {
    $requestTime = normalize_time($requestTime);
}

// If we have a date, check blocked days
if (!empty($requestDate)) {
    // Check in dias_bloqueados and dias_bloqueados_eventos
    $blocked = false;
    $q1 = $conn->query("SELECT fecha FROM dias_bloqueados");
    $diasBloqueados = [];
    if ($q1) {
        while ($r = $q1->fetch_assoc()) { $diasBloqueados[] = $r['fecha']; }
    }
    $q2 = $conn->query("SELECT fecha FROM dias_bloqueados_eventos");
    $diasBloqueadosEventos = [];
    if ($q2) {
        while ($r = $q2->fetch_assoc()) { $diasBloqueadosEventos[] = $r['fecha']; }
    }
    if (in_array($requestDate, $diasBloqueados) || in_array($requestDate, $diasBloqueadosEventos)) {
        $availability['available'] = false;
        $availability['reason'] = 'date_blocked';
        $availability['failed_type'] = 'fecha';
    } else {
        // Get atencion rows for that date
        $stmt = $conn->prepare("SELECT id, dia, horarios, idusu FROM atencion WHERE dia = ?");
        $stmt->bind_param('s', $requestDate);
        $stmt->execute();
        $res = $stmt->get_result();
        $atenciones = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (count($atenciones) === 0) {
            // No vendors assigned to the date
            $availability['available'] = false;
            $availability['reason'] = 'no_vendor_for_date';
            $availability['failed_type'] = 'fecha';
        } else {
            // If no time provided, just say date has vendors
            if (empty($requestTime)) {
                $availability['available'] = true;
                $availability['vendor'] = null;
                $availability['vendor_name'] = null;
                $availability['reason'] = 'date_has_vendors';
            } else {
                // Determine if any vendor can accept the time
                $timeOfferedVendors = [];
                $timeReservedVendors = [];
                $vendorCandidates = [];
                foreach ($atenciones as $row) {
                    $vendor_id = (int)$row['idusu'];
                    $horarios = json_decode($row['horarios'], true);
                    if (!is_array($horarios)) continue;
                    foreach ($horarios as $h) {
                        $time_norm = normalize_time($h);
                        if ($time_norm === $requestTime) {
                            // vendor offers the requested time
                            $timeOfferedVendors[] = $vendor_id;
                            // Check if reserved
                            $stmt2 = $conn->prepare("SELECT hora FROM calendario WHERE fecha = ? AND idusu = ?");
                            $stmt2->bind_param('si', $requestDate, $vendor_id);
                            $stmt2->execute();
                            $res2 = $stmt2->get_result();
                            $reserved = $res2->fetch_all(MYSQLI_ASSOC);
                            $stmt2->close();

                            $reserved_set = [];
                            foreach ($reserved as $r) {
                                $h2 = isset($r['hora']) ? substr($r['hora'], 0, 5) : '';
                                if ($h2) $reserved_set[$h2] = true;
                            }
                            if (!isset($reserved_set[$requestTime])) {
                                // vendor free
                                $vendorCandidates[] = $vendor_id;
                            } else {
                                $timeReservedVendors[] = $vendor_id;
                            }
                        }
                    }
                }

                if (count($vendorCandidates) > 0) {
                    // pick random vendor (similar to obtener-vendedor.php)
                    $assigned_vendor = $vendorCandidates[array_rand($vendorCandidates)];
                    // Get vendor name
                    $stmt3 = $conn->prepare("SELECT nombre, apepat, apemat FROM usuarios WHERE id = ? LIMIT 1");
                    $stmt3->bind_param('i', $assigned_vendor);
                    $stmt3->execute();
                    $res3 = $stmt3->get_result();
                    $vendorInfo = $res3->fetch_assoc();
                    $stmt3->close();
                    $vendorName = null;
                    if ($vendorInfo) {
                        $vendorName = trim($vendorInfo['nombre'] . ' ' . $vendorInfo['apepat'] . ' ' . $vendorInfo['apemat']);
                        $vendorName = preg_replace('/\s+/', ' ', $vendorName);
                    }
                    $availability['available'] = true;
                    $availability['vendor'] = $assigned_vendor;
                    $availability['vendor_name'] = $vendorName;
                    $availability['reason'] = 'available';
                } else {
                    // there were vendors for the date, but none free
                    if (count($timeOfferedVendors) > 0) {
                        // vendors offer the time, but all reserved
                        $availability['available'] = false;
                        $availability['reason'] = 'time_reserved';
                        $availability['failed_type'] = 'hora';
                        $availability['vendors_offered'] = $timeOfferedVendors;
                        $availability['vendors_reserved'] = $timeReservedVendors;
                    } else {
                        // no vendor offers that time on the day
                        $availability['available'] = false;
                        $availability['reason'] = 'time_not_offered';
                        $availability['failed_type'] = 'hora';
                    }
                }
            }
        }
    }
}

$debug['availability'] = $availability;
$debug['read_only_mode'] = $READ_ONLY_MODE;
$client_phone = $requestParams['client_phone'] ?? ($requestParams['phone'] ?? ($requestParams['telefono'] ?? null));
if (!empty($client_phone)) {
    $client_phone = urldecode($client_phone);
    // Double-decode if necessary
    if (strpos($client_phone, '%') !== false) {
        $client_phone = urldecode($client_phone);
    }
    $client_phone = trim($client_phone);
}

// ========================================================================
// BUSCAR LEAD POR TELÉFONO EN TODAS LAS TABLAS DE tablas_leads
// ========================================================================
$foundLead = null;
$foundLeadTable = null;

// Función para normalizar teléfono (solo dígitos)
function normalize_phone_for_search($phone) {
    if (empty($phone)) return '';
    // Quitar todo excepto dígitos
    return preg_replace('/\D+/', '', $phone);
}

$searchPhone = normalize_phone_for_search($client_phone);
$debug['search_phone_normalized'] = $searchPhone;

if (!empty($searchPhone)) {
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
    
    // Buscar en cada tabla el lead con ese teléfono
    foreach ($tablas as $tableName) {
        // Verificar que la tabla existe
        $checkTable = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
        if ($checkTable->num_rows > 0) {
            // Verificar que la columna 'phone' existe
            $columnsResult = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tableName) . "` LIKE 'phone'");
            if ($columnsResult && $columnsResult->num_rows > 0) {
                // Buscar el lead: comparamos quitando caracteres no numéricos
                // Usamos REPLACE para quitar algunos caracteres comunes, o buscamos con LIKE
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
}

$debug['found_lead'] = $foundLead ? true : false;
$debug['found_lead_table'] = $foundLeadTable;
$debug['found_lead_data'] = $foundLead; // Para debugging, incluir los datos del lead encontrado

// Fecha de boda SIEMPRE del webhook (no se guarda en la BD de leads)
$wedding_date = $requestParams['wedding_date'] ?? ($requestParams['weddingDate'] ?? ($requestParams['fecha_boda'] ?? null));
if (!empty($wedding_date)) {
    $wdt = DateTime::createFromFormat('Y-m-d', $wedding_date);
    if ($wdt === false) {
        $wdt = date_create($wedding_date);
    }
    if ($wdt !== false) {
        $wedding_date = $wdt->format('Y-m-d');
    }
}

// Lugar de boda SIEMPRE del webhook (no se guarda en la BD de leads)
$wedding_venue = $requestParams['wedding_venue'] ?? ($requestParams['weddingVenue'] ?? ($requestParams['venue'] ?? null));
if (!empty($wedding_venue)) {
    $wedding_venue = urldecode($wedding_venue);
    if (strpos($wedding_venue, '%') !== false) {
        $wedding_venue = urldecode($wedding_venue);
    }
    $wedding_venue = trim($wedding_venue);
}

// Si encontramos el lead, usar sus datos en lugar de los del webhook (excepto wedding_date y wedding_venue)
if ($foundLead) {
    // Mapear campos del lead a las variables que usaremos
    $lead_full_name = $foundLead['full_name'] ?? '';
    $lead_phone = $foundLead['phone'] ?? '';
    
    // Otros campos del lead
    $lead_form_name = $foundLead['form_name'] ?? '';
    $lead_campaign_name = $foundLead['campaign_name'] ?? '';
    $lead_id = $foundLead['id'] ?? 0;
    // IMPORTANTE: detectar si es un lead creado manualmente
    $lead_status = $foundLead['lead_status'] ?? '';
    $is_manual_lead = ($lead_status === 'manual');
    
    // Extraer country_code y telephone del phone del lead
    $lead_country_code = '';
    $lead_telephone_only = '';
    if (!empty($lead_phone)) {
        $phone_temp = $lead_phone;
        if (preg_match('/^\+?(\d{1,3})[\s\-\.]*(.*)$/', $phone_temp, $m)) {
            $lead_country_code = '+' . $m[1];
            $lead_telephone_only = preg_replace('/\D+/', '', $m[2]);
            if (empty($lead_telephone_only)) {
                $lead_telephone_only = preg_replace('/\D+/', '', $phone_temp);
            }
        } else {
            $lead_telephone_only = preg_replace('/\D+/', '', $phone_temp);
        }
    }
    
    // Usar los datos del lead encontrado (wedding_date y wedding_venue ya vienen del webhook arriba)
    $country_code = $lead_country_code ?: '+52';
    $telephone_only = $lead_telephone_only;
    
    $debug['using_lead_data'] = true;
    $debug['is_manual_lead'] = $is_manual_lead;
} else {
    // No se encontró lead, usar datos del webhook (comportamiento anterior)
    // wedding_date y wedding_venue ya se procesaron arriba
    
    // Normalizar phone del webhook
    $country_code = $requestParams['country_code'] ?? ($requestParams['country'] ?? '');
    $telephone_only = '';
    if (!empty($client_phone)) {
        $phone_temp = $client_phone;
        if (preg_match('/^\+?(\d{1,3})[\s\-\.]*(.*)$/', $phone_temp, $m)) {
            if (empty($country_code)) {
                $country_code = '+' . $m[1];
            }
            $telephone_only = preg_replace('/\D+/', '', $m[2]);
            if (empty($telephone_only)) {
                $telephone_only = preg_replace('/\D+/', '', $phone_temp);
            }
        } else {
            $telephone_only = preg_replace('/\D+/', '', $phone_temp);
        }
    }
    
    $debug['using_lead_data'] = false;
    $is_manual_lead = false; // No es lead manual si no se encontró en BD
}

$debug['parsed_request'] = [
    'date' => $requestDate,
    'time' => $requestTime,
    'client_name' => $foundLead ? ($foundLead['full_name'] ?? '') : ($requestParams['client_name'] ?? ($requestParams['name'] ?? null)),
    'client_phone' => $client_phone,
    'country_code' => $country_code ?? null,
    'telephone' => $telephone_only ?? null,
    'wedding_date' => $wedding_date,
    'wedding_venue' => $wedding_venue,
];

// For backward compat we keep empty commit info since booking is automatic
$debug['commit'] = ['requested' => false, 'API_KEY_in_use' => false];

// Booking flow: if available and commit requested, use existing endpoints to create contact + calendar and send emails
$response['booking'] = ['attempted' => false, 'success' => false, 'messages' => [], 'enviodatos_res' => null, 'enviaCorreo_res' => null];
if ($availability['available']) {
    $response['booking']['attempted'] = true;
    
    // Construir los datos para el POST según si encontramos el lead o no
    if ($foundLead) {
        // VERIFICAR SI ES LEAD MANUAL - Si sí, procesar como formulario_organic.php
        if ($is_manual_lead) {
            // LEAD CREADO MANUALMENTE - Procesar como formulario_organic.php
            // Esto asegura que la información se guarde con la estructura esperada
            $postData = [
                'names' => $foundLead['full_name'] ?? '',
                'telephone' => $telephone_only ?: '',
                'country_code' => $country_code ?: '+52',
                'email' => $foundLead['email'] ?? '',
                'wedding_date' => $wedding_date ?? '',
                'wedding_location' => $wedding_venue ?? '',
                'time_appointment' => $requestTime,
                'date_appointment' => $requestDate,
                'time_appointment_cust' => $requestParams['time_appointment_cust'] ?? ($requestTime ?? ''),
                'date_appointment_cust' => $requestParams['date_appointment_cust'] ?? ($requestDate ?? ''),
                // desde_publicidad = 0 para leads orgánicos (formulario_organic.php usa 0)
                'desde_publicidad' => 0,
                'zona_horaria' => $requestParams['zona_horaria'] ?? 0,
                'city' => $requestParams['city'] ?? '',
                // Guardar referencia al lead original
                'original_lead_id' => $foundLead['id'] ?? 0,
                // form_name debe contener 'form webiste' para coincidir con formulario_organic.php
                'form_name' => 'form webiste',
                'campaign_name' => $foundLead['campaign_name'] ?? 'website',
                'tabla_origen' => $foundLeadTable ?? 'organic_leads',
                // Campos adicionales del formulario orgánico (vacíos si no se proporcionaron)
                'wedding_planner' => '',
                'guests' => 0,
                'meet' => '',
                'couple_activities' => '',
                'favorite_movie_song' => '',
                'photo_style' => '',
                'how_did_hear' => 'WhatsApp Bot',
                'instagram' => '',
                'service' => '',
                'details' => 'Cita agendada vía WhatsApp Bot'
            ];
            $response['booking']['messages'][] = 'Manual lead found in table: ' . $foundLeadTable . ', processing as organic form';
        } else {
            // LEAD NORMAL (NO MANUAL) - usar datos del lead como antes
            $postData = [
                'names' => $foundLead['full_name'] ?? '',
                'telephone' => $telephone_only ?: '',
                'country_code' => $country_code ?: '+52',
                'email' => $foundLead['email'] ?? '',
                'wedding_date' => $wedding_date ?? '',
                'wedding_location' => $wedding_venue ?? '',
                'time_appointment' => $requestTime,
                'date_appointment' => $requestDate,
                'time_appointment_cust' => $requestParams['time_appointment_cust'] ?? ($requestTime ?? ''),
                'date_appointment_cust' => $requestParams['date_appointment_cust'] ?? ($requestDate ?? ''),
                // Marcar como desde agente IA (valor 5)
                'desde_publicidad' => 5,
                'zona_horaria' => $requestParams['zona_horaria'] ?? 0,
                'city' => $requestParams['city'] ?? '',
                // Guardar referencia al lead original
                'original_lead_id' => $foundLead['id'] ?? 0,
                'form_name' => $foundLead['form_name'] ?? 'webhook_from_lead',
                'campaign_name' => $foundLead['campaign_name'] ?? '',
                'tabla_origen' => $foundLeadTable ?? ''
            ];
            $response['booking']['messages'][] = 'Lead found in table: ' . $foundLeadTable . ', using lead data';
        }
    } else {
        // NO SE ENCONTRÓ LEAD - usar datos del webhook (comportamiento anterior)
        // Nota: el email ya no se recibe del webhook, se asignará después
        $postData = [
            'names' => $requestParams['client_name'] ?? ($requestParams['name'] ?? ''),
            'telephone' => $telephone_only ?: ($requestParams['telephone'] ?? ''),
            'country_code' => $country_code ?: '+52',
            'email' => '',
            'wedding_date' => $wedding_date ?? '',
            'wedding_location' => $wedding_venue ?? '',
            'time_appointment' => $requestTime,
            'date_appointment' => $requestDate,
            'time_appointment_cust' => $requestParams['time_appointment_cust'] ?? ($requestTime ?? ''),
            'date_appointment_cust' => $requestParams['date_appointment_cust'] ?? ($requestDate ?? ''),
            'desde_publicidad' => $requestParams['desde_publicidad'] ?? 0,
            'zona_horaria' => $requestParams['zona_horaria'] ?? 0,
            'city' => $requestParams['city'] ?? '',
            'original_lead_id' => $requestParams['original_lead_id'] ?? 0,
            'form_name' => $requestParams['form_name'] ?? 'book_slot_api',
            'campaign_name' => $requestParams['campaign_name'] ?? '',
            'tabla_origen' => $requestParams['tabla_origen'] ?? 'contact_form'
        ];
        $response['booking']['messages'][] = 'Lead NOT found by phone, using webhook data';
    }
    
    // Ensure vendor is the one we selected in availability; pass idusu to enviodatosform
    if (!empty($availability['vendor'])) {
        $postData['idusu'] = $availability['vendor'];
    }

    // Also add other user-supplied fields if present (only if not using lead data)
    $optionalFields = ['wedding_planner', 'guests', 'meet', 'names', 'details', 'service', 'user_message'];
    foreach ($optionalFields as $f) {
        if (!$foundLead && isset($requestParams[$f])) $postData[$f] = $requestParams[$f];
    }

    // Send POST to enviodatosform.php using HTTP request to our host to reuse server logic
    $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $baseUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $envUrl = rtrim($baseUrl, '/') . '/enviodatosform.php';

    $debug['post_to_enviodatos'] = $postData; // record the POST we will send (for debugging)
    // Use cURL to POST to enviodatosform if available, else have a fallback
    function http_post_request($url, $data, &$out_info = null, $timeout = 30) {
        $out_info = ['http_code' => null, 'error' => null, 'raw' => null];
        $payload = http_build_query($data);
        if (function_exists('curl_version')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $out_info['http_code'] = $code;
            $out_info['error'] = $err ?: null;
            $out_info['raw'] = $resp;
            return $resp;
        }
        // fallback to file_get_contents if allow_url_fopen is true
        if (ini_get('allow_url_fopen')) {
            $opts = ['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $payload, 'timeout' => $timeout]];
            $context = stream_context_create($opts);
            $resp = @file_get_contents($url, false, $context);
            $out_info['raw'] = $resp;
            return $resp;
        }
        $out_info['error'] = 'no_transport_available';
        return false;
    }

    try {
        $envInfo = null;
        $envRespRaw = http_post_request($envUrl, $postData, $envInfo, 30);
        $envResp = json_decode($envRespRaw, true);
        $response['booking']['enviodatos_res'] = $envResp;
        // Add raw/HTTP info into debug for diagnosis
        $response['booking']['enviodatos_raw'] = $envRespRaw;
        $response['booking']['enviodatos_info'] = $envInfo;
        if ($envResp && isset($envResp['success']) && ($envResp['success'] === true || $envResp['success'] === 'full' || $envResp['success'] === 1)) {
            // enviodatosform returned success (it uses true or 'full'), check 'cust' and 'vendedor'.
            $cust = $envResp['cust'] ?? null;
            $vendedor = $envResp['vendedor'] ?? null;
            $response['booking']['messages'][] = 'contact and calendario insert attempted';
            // Now call admin/enviaCorreo.php to send emails if we have cust and vendedor
            if ($cust && $vendedor) {
                $mailUrl = rtrim($baseUrl, '/') . '/admin/enviaCorreo.php';
                $mailPost = ['cust' => $cust, 'vendedor' => $vendedor];
                $debug['post_to_enviaCorreo'] = $mailPost;
                $mailInfo = null;
                $mailRespRaw = http_post_request($mailUrl, $mailPost, $mailInfo, 30);
                $mailResp = json_decode($mailRespRaw, true);
                $response['booking']['enviaCorreo_res'] = $mailResp ?: $mailRespRaw;
                $response['booking']['enviaCorreo_raw'] = $mailRespRaw;
                $response['booking']['enviaCorreo_info'] = $mailInfo;
                $response['booking']['success'] = true;
            } else {
                $response['booking']['messages'][] = 'enviodatosform did not return cust/vendedor';
                $response['booking']['success'] = false;
            }
        } else {
            $response['booking']['messages'][] = 'enviodatosform reported error or not available';
            $response['booking']['success'] = false;
            // Try admin/enviodatosform.php endpoint as a fallback (use absolute URL)
            $adminEnvUrl = rtrim($baseUrl, '/') . '/enviodatosform.php';
            $adminEnvInfo = null;
            $adminEnvRespRaw = http_post_request($adminEnvUrl, $postData, $adminEnvInfo, 30);
            $adminEnvResp = json_decode($adminEnvRespRaw, true);
            $response['booking']['admin_enviodatos_raw'] = $adminEnvRespRaw;
            $response['booking']['admin_enviodatos_info'] = $adminEnvInfo;
            if ($adminEnvResp && isset($adminEnvResp['success']) && ($adminEnvResp['success'] === true || $adminEnvResp['success'] === 'full' || $adminEnvResp['success'] === 1)) {
                $cust = $adminEnvResp['cust'] ?? null;
                $vendedor = $adminEnvResp['vendedor'] ?? null;
                if ($cust && $vendedor) {
                    $mailInfo = null;
                    $mailRespRaw = http_post_request($mailUrl, ['cust'=>$cust, 'vendedor'=>$vendedor], $mailInfo, 30);
                    $mailResp = json_decode($mailRespRaw, true);
                    $response['booking']['enviaCorreo_res'] = $mailResp ?: $mailRespRaw;
                    $response['booking']['enviaCorreo_raw'] = $mailRespRaw;
                    $response['booking']['enviaCorreo_info'] = $mailInfo;
                    $response['booking']['success'] = true;
                }
            }
        }
    } catch (Exception $e) {
        $response['booking']['messages'][] = 'error contacting enviodatosform: ' . $e->getMessage();
        $response['booking']['success'] = false;
    }

    // If booking succeeded and we have a lead, update its estatus_llamada to 2 (agendó)
    if (!empty($response['booking']['success']) && $response['booking']['success'] === true && !empty($foundLead) && !empty($foundLeadTable)) {
        $tableSafe = $conn->real_escape_string($foundLeadTable);
        $leadId = intval($foundLead['id'] ?? 0);
        if ($leadId > 0) {
            $updSql = "UPDATE `" . $tableSafe . "` SET estatus_whatsapp = ? WHERE id = ? LIMIT 1";
            $stmtUpd = $conn->prepare($updSql);
            if ($stmtUpd) {
                $newStatus = 2;
                $stmtUpd->bind_param('ii', $newStatus, $leadId);
                $execOk = $stmtUpd->execute();
                $affected = $stmtUpd->affected_rows;
                $stmtUpd->close();
                $debug['updated_lead_status'] = $execOk ? true : false;
                $debug['updated_lead_status_rows'] = $affected;
                $response['booking']['updated_lead_status'] = $execOk ? true : false;
                $response['booking']['updated_lead_status_rows'] = $affected;
            } else {
                $debug['updated_lead_status'] = 'prepare_failed:' . $conn->error;
                $response['booking']['updated_lead_status'] = false;
                $response['booking']['updated_lead_status_error'] = $conn->error;
            }
        } else {
            $debug['updated_lead_status'] = 'invalid_lead_id';
            $response['booking']['updated_lead_status'] = false;
        }
    }
}

// Put booking results into debug for logging BEFORE writing log
$debug['booking'] = $response['booking'] ?? null;

// Write to log file (append). Use daily rotation
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/webhook_' . date('Y-m-d') . '.log';
$line = date('c') . ' ' . json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

// If the request includes a param to download logs, return the log
if (isset($_GET['download_logs']) && $_GET['download_logs'] && $allow_download_logs) {
    if (file_exists($logFile)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo file_get_contents($logFile);
        exit;
    }
}

// If the request has 'show_headers' param, return only headers and server vars
if (isset($_GET['show_headers']) && $_GET['show_headers']) {
    echo json_encode(['success' => true, 'headers' => $debug['headers'], 'server' => $debug['server_vars']]);
    exit;
}

// Build final response - PRESERVE booking info, don't overwrite $response completely
$response['success'] = true;
$response['debug'] = $debug;
$response['message'] = 'Received webhook and logged.';
$response['available'] = $availability['available'] ?? false;
$response['availability'] = $availability;
$response['read_only_mode'] = $READ_ONLY_MODE;
$response['parsed_request'] = $debug['parsed_request'];

// If prefered, you can also return a smaller summary rather than all content
if (isset($_GET['summary']) && $_GET['summary']) {
    $summary = [
        'method' => $debug['request_method'],
        'remote_addr' => $debug['remote_addr'],
        'uri' => $debug['uri'],
        'content_type' => $contentType,
        'get_keys' => array_keys($debug['get']),
        'post_keys' => array_keys($debug['post']),
    ];
    echo json_encode(['success' => true, 'summary' => $summary], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Output full data
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
?>
