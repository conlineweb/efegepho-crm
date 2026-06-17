<?php
// enviar_whatsapp_lead.php
// Envía mensaje de WhatsApp a un lead específico usando Twilio
// Similar a la lógica de cron_send_whatsapp.php pero para envío individual

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

    $contentVarsJson = json_encode($contentVars);
    
    // Construir el POST body manualmente para evitar doble encoding de ContentVariables
    $postFields = 'From=' . urlencode($from) 
                . '&To=' . urlencode($to)
                . '&ContentSid=' . urlencode($templateSid)
                . '&ContentVariables=' . urlencode($contentVarsJson);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_TIMEOUT => 30
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['http_code' => $code, 'body' => $res, 'error' => $err, 'request' => ['ContentSid' => $templateSid, 'ContentVariables' => $contentVarsJson]];
}

function enviarWhatsappALead($conn, $leadId, $tablaOrigen, $logFile = null)
{
    // CONFIG: Credenciales de Twilio
    $TWILIO_ACCOUNT_SID = getenv('TWILIO_ACCOUNT_SID') ?: 'AC15ac8ca17a42aa5546b1a7fc6485a485';
    $TWILIO_AUTH_TOKEN = getenv('TWILIO_AUTH_TOKEN') ?: '428434eee0884451fbc2d0817c525c86';
    $TWILIO_FROM = getenv('TWILIO_FROM') ?: 'whatsapp:+15558252950';
    
    // Plantillas aprobadas en Twilio
    $TWILIO_TEMPLATE_SID_ES = getenv('TWILIO_TEMPLATE_SID_ES') ?: 'HXcf4bbc6db49e4e4862d97706f44866d7';
    $TWILIO_TEMPLATE_SID_EN = getenv('TWILIO_TEMPLATE_SID_EN') ?: 'HX5185644f9b79d1ea0b0fd42517b19be7';
    $TWILIO_TEMPLATE_VAR_KEY = getenv('TWILIO_TEMPLATE_VAR_KEY') ?: '1';

    if (!$logFile) {
        $logFile = __DIR__ . '/enviar_whatsapp_lead.log';
    }

    function logMsg($m, $file)
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL;
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    // Asegurar que la tabla tiene la columna whatsapp_bot_sent
    $safeTable = $conn->real_escape_string($tablaOrigen);
    $colCheck = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE 'whatsapp_bot_sent'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        $alter = "ALTER TABLE `{$safeTable}` ADD COLUMN `whatsapp_bot_sent` TINYINT(1) DEFAULT 0";
        $conn->query($alter);
        logMsg("Se agregó whatsapp_bot_sent a {$safeTable}", $logFile);
    }
    
    $colCheck2 = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE 'whatsapp_bot_sent_at'");
    if (!$colCheck2 || $colCheck2->num_rows === 0) {
        $alter2 = "ALTER TABLE `{$safeTable}` ADD COLUMN `whatsapp_bot_sent_at` DATETIME NULL";
        $conn->query($alter2);
        logMsg("Se agregó whatsapp_bot_sent_at a {$safeTable}", $logFile);
    }

    // Obtener datos del lead
    $sql = "SELECT id, full_name, phone, email FROM `{$safeTable}` WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logMsg("Error preparando consulta para lead {$tablaOrigen}#{$leadId}: " . $conn->error, $logFile);
        return ['success' => false, 'message' => 'Error en la base de datos'];
    }

    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows === 0) {
        logMsg("Lead {$tablaOrigen}#{$leadId} no encontrado", $logFile);
        return ['success' => false, 'message' => 'Lead no encontrado'];
    }

    $lead = $result->fetch_assoc();
    $rawPhone = $lead['phone'];
    $cleanPhone = normalize_phone($rawPhone);

    logMsg("Procesando envío inmediato para lead {$tablaOrigen}#{$leadId} - phone_raw='{$rawPhone}' - phone_normalized='" . ($cleanPhone ?: 'NULL') . "'", $logFile);

    if (empty($cleanPhone)) {
        logMsg("Lead {$tablaOrigen}#{$leadId} sin teléfono válido: '{$rawPhone}'", $logFile);
        
        // Marcar como problemático
        $conn->query("UPDATE `{$safeTable}` SET whatsapp_bot_sent = 9 WHERE id = {$leadId}");
        
        return ['success' => false, 'message' => 'Teléfono no válido'];
    }

    // Verificar si ya se envió mensaje
    $checkSent = $conn->query("SELECT whatsapp_bot_sent FROM `{$safeTable}` WHERE id = {$leadId}");
    if ($checkSent && $checkSent->num_rows > 0) {
        $row = $checkSent->fetch_assoc();
        if ($row['whatsapp_bot_sent'] == 1) {
            logMsg("Lead {$tablaOrigen}#{$leadId} ya tiene whatsapp_bot_sent = 1, omitiendo envío", $logFile);
            return ['success' => false, 'message' => 'Ya se envió mensaje de WhatsApp a este lead'];
        }
    }

    // Construir mensaje
    $name = trim($lead['full_name'] ?: '');
    
    // Detectar idioma según código de país
    $isMexico = (strpos($cleanPhone, '+52') === 0);
    $lang = $isMexico ? 'es' : 'en';
    
    // Seleccionar plantilla según idioma
    $templateSid = $isMexico ? $TWILIO_TEMPLATE_SID_ES : $TWILIO_TEMPLATE_SID_EN;
    
    // Nombre de saludo según idioma
    $greetName = $name ? explode(' ', $name)[0] : ($isMexico ? 'novia/o' : 'bride/groom');
    
    $templateVars = [
        $TWILIO_TEMPLATE_VAR_KEY => $greetName
    ];

    // Texto completo del mensaje según idioma
    if ($isMexico) {
        $bodyForSession = "Hola " . $greetName . ", habla Alexa de EFEGE estudio de fotografía y video para bodas. ¿Cómo te encuentras hoy?";
    } else {
        $bodyForSession = "Hi " . $greetName . ", this is Alexa from EFEGE, a wedding photography and video studio. How are you today?";
    }
    
    logMsg("Idioma detectado: {$lang} - Template: {$templateSid}", $logFile);

    // Enviar mensaje por Twilio
    $resp = twilio_send_whatsapp($TWILIO_ACCOUNT_SID, $TWILIO_AUTH_TOKEN, $TWILIO_FROM, 'whatsapp:' . $cleanPhone, $templateSid, $templateVars);

    if ($resp['http_code'] === 201 || $resp['http_code'] === 200) {
        // Actualizar lead
        $stmt = $conn->prepare("UPDATE `{$safeTable}` SET whatsapp_bot_sent = 1, whatsapp_bot_sent_at = NOW() WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $leadId);
            $stmt->execute();
            $stmt->close();
        }

        logMsg("Envío OK a {$cleanPhone} lead {$tablaOrigen}#{$leadId} (HTTP {$resp['http_code']})", $logFile);

        // Guardar el mensaje en el archivo de sesión
        try {
            $sessionDir = __DIR__ . '/../whatsapp/sessions';
            if (!is_dir($sessionDir))
                mkdir($sessionDir, 0777, true);
            
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
            
            // Agregar mensaje del asistente
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

            logMsg("Sesión actualizada para {$digits}", $logFile);

            // Guardar el nombre en el meta para que no lo vuelva a pedir
            $metaFile = __DIR__ . "/../whatsapp/sessions/whatsapp_{$digits}_meta.json";
            $meta = [];
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true) ?: [];
            }
            if (!empty($greetName) && $greetName !== 'novia/o' && $greetName !== 'bride/groom') {
                $meta['client_name'] = $greetName;
                @file_put_contents($metaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
                logMsg("Nombre '{$greetName}' guardado en meta para {$digits}", $logFile);
            }
        } catch (Exception $e) {
            logMsg("Error guardando sesión para {$cleanPhone}: " . $e->getMessage(), $logFile);
        }

        return [
            'success' => true, 
            'message' => 'Mensaje de WhatsApp enviado exitosamente',
            'phone' => $cleanPhone,
            'lang' => $lang
        ];
    } else {
        $reqInfo = isset($resp['request']) ? json_encode($resp['request']) : '';
        logMsg("ERROR enviando a {$cleanPhone} lead {$tablaOrigen}#{$leadId} - HTTP {$resp['http_code']} - curl_error: {$resp['error']} - body: {$resp['body']} - request: {$reqInfo}", $logFile);
        
        return [
            'success' => false, 
            'message' => 'Error al enviar WhatsApp: ' . ($resp['error'] ?: 'HTTP ' . $resp['http_code']),
            'twilio_response' => $resp['body']
        ];
    }
}
?>
