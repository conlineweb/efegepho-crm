<?php
// cron_send_whatsapp.php
// Envía mensajes salientes por WhatsApp (Twilio) a leads asignados al usuario 100
// Uso: php cron_send_whatsapp.php [--dry]

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);

date_default_timezone_set('Mexico/General');

// ---------------------------------------------------------
// CONFIG: sustituye con tus credenciales o exporta como env vars
// ---------------------------------------------------------
$TWILIO_ACCOUNT_SID = getenv('TWILIO_ACCOUNT_SID') ?: 'AC15ac8ca17a42aa5546b1a7fc6485a485';
$TWILIO_AUTH_TOKEN = getenv('TWILIO_AUTH_TOKEN') ?: '428434eee0884451fbc2d0817c525c86';
// Debe incluir el prefijo whatsapp: ejemplo: 'whatsapp:+1415XXXXXXX'
$TWILIO_FROM = getenv('TWILIO_FROM') ?: 'whatsapp:+15558252950';

// Plantillas aprobadas en Twilio (ContentSid) - Español e Inglés
$TWILIO_TEMPLATE_SID_ES = getenv('TWILIO_TEMPLATE_SID_ES') ?: 'HXcf4bbc6db49e4e4862d97706f44866d7'; // Español
$TWILIO_TEMPLATE_SID_EN = getenv('TWILIO_TEMPLATE_SID_EN') ?: 'HX5185644f9b79d1ea0b0fd42517b19be7'; // Inglés
// Clave de variable de la plantilla (por defecto "1" para {{1}})
$TWILIO_TEMPLATE_VAR_KEY = getenv('TWILIO_TEMPLATE_VAR_KEY') ?: '1';

// Control de ejecución
$DELAY_BETWEEN_MESSAGES = 5; // segundos entre envíos (ajusta según tu plan Twilio)
$MAX_PER_RUN = 200; // máximo de leads por ejecución

$isCLI = (PHP_SAPI === 'cli');
$dryRun = false;
if ($isCLI) {
    foreach ($argv as $a) {
        if (strpos($a, '--dry') === 0)
            $dryRun = true;
    }
} else {
    if (isset($_GET['dry']) && $_GET['dry'] == '1')
        $dryRun = true;
    // Para permitir que el script siga ejecutándose aun cuando el navegador cierre
    ignore_user_abort(true);
    // Preparamos la respuesta JSON y enviamos las cabeceras ANTES de imprimir para evitar "headers already sent"
    $payload = json_encode(['status' => 'started', 'dry' => $dryRun]);
    if (ob_get_level() > 0)
        ob_end_clean();
    header('Connection: close');
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($payload));
    echo $payload;
    flush();
    if (function_exists('fastcgi_finish_request'))
        fastcgi_finish_request();
}

$logFile = __DIR__ . '/cron_send_whatsapp.log';
function logMsg($m)
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

logMsg('=== START cron_send_whatsapp (dry=' . ($dryRun ? '1' : '0') . ') ===');

include 'conn.php';

// Funciones auxiliares
function normalize_phone($raw)
{
    $p = preg_replace('/\D+/', '', (string) $raw);
    if (empty($p)) return null;

    if (strpos($p, '00') === 0) $p = ltrim($p, '0');

    // Lógica para México (52)
    if (strpos($p, '52') === 0) {
        $rest = substr($p, 2);
        // Si tiene el '1' extra (11 dígitos después del 52), lo quitamos
        if (strlen($rest) === 11 && substr($rest, 0, 1) === '1') {
            return '+52' . substr($rest, 1);
        }
        // Si tiene 10 dígitos después del 52, lo dejamos tal cual
        if (strlen($rest) === 10) {
            return '+52' . $rest;
        }
    }

    // Si tiene 10 dígitos sin prefijo, agregamos solo +52
    if (strlen($p) == 10) {
        return '+52' . $p;
    }

    return '+' . $p;
}

function twilio_send_whatsapp($sid, $token, $from, $to, $templateSid, $templateVars)
{
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

    // Normalizar ContentVariables: Twilio espera un objeto JSON con claves de texto "1", "2", etc.
    $varsArray = is_array($templateVars) ? $templateVars : [$templateVars];
    $contentVars = [];
    $i = 1;
    foreach ($varsArray as $value) {
        $contentVars[(string)$i] = $value;
        $i++;
    }

    // Twilio usa 'ContentSid' para la plantilla y 'ContentVariables' para los datos dinámicos como el nombre
    $contentVarsJson = json_encode($contentVars);
    
    // Construir el POST body manualmente para evitar doble encoding de ContentVariables
    $postFields = 'From=' . urlencode($from) 
                . '&To=' . urlencode($to)
                . '&ContentSid=' . urlencode($templateSid)
                . '&ContentVariables=' . urlencode($contentVarsJson);

    // Registrar payload para depuración (no incluya tokens)
    if (function_exists('logMsg')) {
        logMsg("Twilio payload To={$to} ContentSid={$templateSid} ContentVariables=" . $contentVarsJson);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields, // POST body construido manualmente
        CURLOPT_TIMEOUT => 30
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['http_code' => $code, 'body' => $res, 'error' => $err, 'request' => ['ContentSid' => $templateSid, 'ContentVariables' => $contentVarsJson]];
}

// Obtener tablas de leads
$tables = [];
$res = $conn->query("SELECT nombre FROM tablas_leads ORDER BY nombre");
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc())
        $tables[] = $r['nombre'];
}

if (empty($tables)) {
    logMsg('No se encontraron tablas en tablas_leads. Abortando');
    exit;
}

$totalSent = 0;
$errorCount = 0;
$processedPhones = []; // Lista de números (solo dígitos) procesados para el resumen


foreach ($tables as $table) {
    if ($totalSent >= $MAX_PER_RUN)
        break;

    // Asegurarnos que la tabla existe y que tiene columna phone
    $check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    if (!$check || $check->num_rows === 0)
        continue;

    $cols = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "` LIKE 'phone'");
    if (!$cols || $cols->num_rows === 0)
        continue;

    // Asegurarnos que existe la columna whatsapp_bot_sent
    $colCheck = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "` LIKE 'whatsapp_bot_sent'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        $alter = "ALTER TABLE `" . $conn->real_escape_string($table) . "` ADD COLUMN `whatsapp_bot_sent` TINYINT(1) DEFAULT 0";
        $conn->query($alter);
        logMsg("Se agregó whatsapp_bot_sent a {$table}");
    }

    // Seleccionar leads asignados a usuario 100 sin whatsapp_bot_sent
    $sql = "SELECT id, full_name, phone, email, usuario_asignado, estatus_whatsapp FROM `" . $conn->real_escape_string($table) . "` WHERE usuario_asignado = 100 AND (whatsapp_bot_sent IS NULL OR whatsapp_bot_sent = 0) AND phone IS NOT NULL LIMIT 100";
    $q = $conn->query($sql);
    if (!$q) {
        logMsg("Error al consultar $table: " . $conn->error);
        continue;
    }

    while ($lead = $q->fetch_assoc()) {
        if ($totalSent >= $MAX_PER_RUN)
            break 2;

        $leadId = intval($lead['id']);
        $rawPhone = $lead['phone'];
        // $rawPhone = "+52 1 81 2009 4766"; // Para pruebas fijas
        $cleanPhone = normalize_phone($rawPhone);

        // Registrar en logs el lead y el número (raw y normalizado) — útil para auditoría del usuario asignado
        $assignedUser = intval($lead['usuario_asignado']);
        logMsg("Procesando lead {$table}#{$leadId} asignado={$assignedUser} - phone_raw='{$rawPhone}' - phone_normalized='" . ($cleanPhone ?: 'NULL') . "'");

        if (empty($cleanPhone)) {
            logMsg("Lead {$table}#{$leadId} sin teléfono válido: '{$rawPhone}'");
            // marcar como problemático para no volverlo a intentar (opcional)
            $conn->query("UPDATE `" . $conn->real_escape_string($table) . "` SET whatsapp_bot_sent = 9 WHERE id = " . $leadId);
            continue;
        }

        // Construir mensaje (puedes personalizarlo)
        $name = trim($lead['full_name'] ?: '');
        
        // Detectar idioma según código de país del teléfono normalizado
        $isMexico = (strpos($cleanPhone, '+52') === 0);
        $lang = $isMexico ? 'es' : 'en';
        
        // Seleccionar plantilla según idioma
        $templateSid = $isMexico ? $TWILIO_TEMPLATE_SID_ES : $TWILIO_TEMPLATE_SID_EN;
        
        // Nombre de saludo según idioma
        $greetName = $name ? explode(' ', $name)[0] : ($isMexico ? 'novia/o' : 'bride/groom');
        
        $templateVars = [
            $TWILIO_TEMPLATE_VAR_KEY => $greetName  // Esto llenará la variable de tu plantilla
        ];

        // Forzamos que el texto sea idéntico a la plantilla aprobada según idioma
        if ($isMexico) {
            $bodyForSession = "Hola " . $greetName . ", habla Alexa de EFEGE estudio de fotografía y video para bodas. ¿Cómo te encuentras hoy?";
        } else {
            $bodyForSession = "Hi " . $greetName . ", this is Alexa from EFEGE, a wedding photography and video studio. How are you today?";
        }
        
        logMsg("Idioma detectado: {$lang} - Template: {$templateSid}");

        // Añadir número procesado al resumen (guardamos sólo dígitos)
        $digitsOnly = preg_replace('/\D+/', '', $cleanPhone);
        if ($digitsOnly !== '' && !in_array($digitsOnly, $processedPhones)) {
            $processedPhones[] = $digitsOnly;
        }


        if ($dryRun) {
            logMsg("[DRY RUN] Mensaje a {$cleanPhone}: {$bodyForSession}");
            // No actualizamos la base de datos en dry run
        } else {
            $resp = twilio_send_whatsapp($TWILIO_ACCOUNT_SID, $TWILIO_AUTH_TOKEN, $TWILIO_FROM, 'whatsapp:' . $cleanPhone, $templateSid, $templateVars);
    
    if ($resp['http_code'] === 201 || $resp['http_code'] === 200) {
        // El mensaje ya fue construido correctamente según el idioma antes del envío
        // No lo sobrescribimos aquí
           
                // Success: actualizar lead
                $stmt = $conn->prepare("UPDATE `" . $conn->real_escape_string($table) . "` SET whatsapp_bot_sent = 1, whatsapp_bot_sent_at = NOW() WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $leadId);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    logMsg("No se pudo preparar update para {$table}#{$leadId}: " . $conn->error);
                }

                logMsg("Envío OK a {$cleanPhone} lead {$table}#{$leadId} (HTTP {$resp['http_code']})");

                // Guardar el mensaje enviado en el archivo de sesión para que el webhook tenga contexto
                try {
                    $sessionDir = '../whatsapp/sessions';
                    if (!is_dir($sessionDir))
                        mkdir($sessionDir, 0777, true);
                    // Normalizar a sólo dígitos para el nombre de archivo (igual que en webhook)
                    $digits = preg_replace('/\D+/', '', $cleanPhone);
                    $sessionFile = "$sessionDir/whatsapp_{$digits}.json";
                    $messages = [];
                    if (file_exists($sessionFile)) {
                        $fh = @fopen($sessionFile, 'rb');
                        if ($fh) {
                            if (flock($fh, LOCK_SH)) {
                                $contents = stream_get_contents($fh);
                                flock($fh, LOCK_UN);
                                $messages = json_decode($contents, true) ?: [];
                            }
                            fclose($fh);
                        }
                    }
                    // Agregamos el mensaje del asistente
                    $messages[] = ["role" => "assistant", "content" => $bodyForSession];
                    // Guardado atómico
                    $tmp = $sessionFile . '.tmp';
                    $fh = @fopen($tmp, 'wb');
                    if ($fh) {
                        if (flock($fh, LOCK_EX)) {
                            fwrite($fh, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            fflush($fh);
                            flock($fh, LOCK_UN);
                        }
                        fclose($fh);
                        @rename($tmp, $sessionFile);
                    } else {
                        file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }

                    logMsg("Sesión actualizada para {$digits}");

                    // Guardar el nombre en el meta para que no lo vuelva a pedir
                    $metaFile = "$sessionDir/whatsapp_{$digits}_meta.json";
                    $meta = [];
                    if (file_exists($metaFile)) {
                        $meta = json_decode(file_get_contents($metaFile), true) ?: [];
                    }
                    if (!empty($greetName) && $greetName !== 'novia/o' && $greetName !== 'bride/groom') {
                        $meta['client_name'] = $greetName;
                        @file_put_contents($metaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
                        logMsg("Nombre '{$greetName}' guardado en meta para {$digits}");
                    }
                } catch (Exception $e) {
                    logMsg("Error guardando sesión para {$cleanPhone}: " . $e->getMessage());
                }

                $totalSent++;
            } else {
                $reqInfo = isset($resp['request']) ? json_encode($resp['request']) : '';
                logMsg("ERROR enviando a {$cleanPhone} lead {$table}#{$leadId} - HTTP {$resp['http_code']} - curl_error: {$resp['error']} - body: {$resp['body']} - request: {$reqInfo}");
                $errorCount++;
                // No marcar como enviado; se reintentará en ejecuciones futuras
            }

            // Respetar tasa
            sleep($DELAY_BETWEEN_MESSAGES);
        }
    }
}

if (!empty($processedPhones)) {
    logMsg("Números procesados asignados a usuario 100: " . implode(', ', $processedPhones));
} else {
    logMsg("No se procesaron números para usuario 100 en esta ejecución.");
}

logMsg("Finished. total_sent={$totalSent}, errors={$errorCount}");

// Mensaje final en modo CLI
if ($isCLI) {
    echo "Done. total_sent={$totalSent}, errors={$errorCount}\n";
}

?>