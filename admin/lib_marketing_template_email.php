<?php
// Shared logic to send a marketing template email to a lead

// Ensure PHPMailer classes are available for direct function calls
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function send_marketing_template_email(mysqli $conn, int $templateId, string $tablaOrigen, int $leadId, ?int $userid = null): array
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    date_default_timezone_set('Mexico/General');

    // PHPMailer
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    // sanitize table name
    $tablaOrigen = preg_replace('/[^a-zA-Z0-9_]/', '', $tablaOrigen);
    if ($tablaOrigen === '') {
        return ['success' => false, 'message' => 'Tabla inválida'];
    }

    // fetch template
    $stmt = $conn->prepare("SELECT id, nombre, asunto, titulo, cuerpo, despedida FROM marketing_templates WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Error preparando consulta de plantilla'];
    }
    $stmt->bind_param('i', $templateId);
    $stmt->execute();
    $res = $stmt->get_result();
    $template = $res->fetch_assoc();
    $stmt->close();

    if (!$template) {
        return ['success' => false, 'message' => 'Plantilla no encontrada'];
    }

    // fetch lead
    if ($stmt2 = $conn->prepare("SELECT * FROM `$tablaOrigen` WHERE id = ? LIMIT 1")) {
        $stmt2->bind_param('i', $leadId);
        $stmt2->execute();
        $resultLead = $stmt2->get_result();
        $lead = $resultLead->fetch_assoc();
        $stmt2->close();
    } else {
        return ['success' => false, 'message' => 'Error preparando consulta de lead'];
    }

    if (!$lead) {
        return ['success' => false, 'message' => 'Lead no encontrado'];
    }

    // prepare replacements
    $replacements = [];
    if (strpos($template['asunto'] ?? '', '$full_name') !== false || strpos($template['titulo'] ?? '', '$full_name') !== false || strpos($template['cuerpo'] ?? '', '$full_name') !== false || strpos($template['despedida'] ?? '', '$full_name') !== false) {
        if (array_key_exists('full_name', $lead)) {
            $fullNameRaw = trim((string) $lead['full_name']);
            $firstName = $fullNameRaw;
            if ($fullNameRaw !== '') {
                $parts = preg_split('/\s+/', $fullNameRaw);
                if (!empty($parts[0])) {
                    $firstName = $parts[0];
                }
            }
            $replacements['$full_name'] = $firstName;
        } else {
            return ['success' => false, 'message' => 'La plantilla requiere $full_name pero el campo no existe en la tabla del lead'];
        }
    }
    if (strpos($template['asunto'] ?? '', '$wedding_date') !== false || strpos($template['titulo'] ?? '', '$wedding_date') !== false || strpos($template['cuerpo'] ?? '', '$wedding_date') !== false || strpos($template['despedida'] ?? '', '$wedding_date') !== false) {
        if (array_key_exists('when_are_you_getting_married_', $lead)) {
            $replacements['$wedding_date'] = $lead['when_are_you_getting_married_'];
        } else {
            return ['success' => false, 'message' => 'La plantilla requiere $wedding_date pero el campo when_are_you_getting_married_ no existe en la tabla del lead'];
        }
    }

    // $schedule_button: build a tracked schedule button when template requires it
    if (strpos($template['asunto'] ?? '', '$schedule_button') !== false || strpos($template['titulo'] ?? '', '$schedule_button') !== false || strpos($template['cuerpo'] ?? '', '$schedule_button') !== false || strpos($template['despedida'] ?? '', '$schedule_button') !== false) {
        $isUsaForm = false;
        $tablaLower = strtolower($tablaOrigen);
        if (strpos($tablaLower, 'usa') !== false || strpos($tablaLower, 'andromeda') !== false) {
            $isUsaForm = true;
        }
        if (!empty($lead['campaign_name']) && stripos($lead['campaign_name'], 'E10') === 0) {
            $isUsaForm = true;
        }

        $linkFormRegister = "https://www.citas.efegepho.com.mx/inquire-form.php";
        $separator = (strpos($linkFormRegister, '?') !== false) ? '&' : '?';
        $linkWithParams = $linkFormRegister . $separator . 'tabla_origen=' . rawurlencode($tablaOrigen) . '&id=' . rawurlencode($leadId);
        $buttonText = 'Schedule now';
        $trackUrl = 'https://citas.efegepho.com.mx/pixel/click.php?id=' . rawurlencode($leadId)
            . '&tabla_origen=' . rawurlencode($tablaOrigen)
            . '&template_id=' . rawurlencode((string)$templateId)
            . '&correo=1&url=' . rawurlencode($linkWithParams);

        $link_form = "<a class='btn-agenda' href='" . $trackUrl . "' style='display: block; margin: 40px auto 20px auto; padding: 16px 24px; background-color: #eee8dc; border: 1.5px solid #3B3B3B; border-radius: 15px; color: #3B3B3B; text-align: center; text-decoration: none; font-weight: 600; font-family: \"Open Sans\", sans-serif; box-sizing: border-box; cursor: pointer;' role='button'>" . $buttonText . "</a>";
        $replacements['$schedule_button'] = $link_form;
    }

    // email
    if (array_key_exists('email', $lead) && filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
        $email = $lead['email'];
    } else {
        return ['success' => false, 'message' => 'No se encontró un email válido en el lead (campo email requerido)'];
    }

    // replace placeholders in the template parts
    $asunto = $template['asunto'] ?? '';
    $titulo = $template['titulo'] ?? '';
    $cuerpo = $template['cuerpo'] ?? '';
    $despedida = $template['despedida'] ?? '';

    foreach ($replacements as $key => $val) {
        $asunto = str_replace($key, $val, $asunto);
        $titulo = str_replace($key, $val, $titulo);
        $cuerpo = str_replace($key, $val, $cuerpo);
        $despedida = str_replace($key, $val, $despedida);
    }

    // load enviarCorreo function (copied from enviar_primer_correo_lead to ensure identical design)
    $mailError = null;
    $sent = enviarCorreo_marketing($email, $asunto, $titulo, $cuerpo, $despedida, null, $tablaOrigen, $leadId, $templateId, $mailError);

    // prepare JSON log entry and append to marketing_templates.sent_log
    $logged = false;
    $logIndex = null;
    $now = date('Y-m-d H:i:s');
    $created_by = isset($userid) ? intval($userid) : 0;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $successInt = $sent ? 1 : 0;
    $mailErrorNormalized = $mailError ?? '';

    $entryArr = [
        'sent_at' => $now,
        'tabla_origen' => $tablaOrigen,
        'lead_id' => $leadId,
        'email' => $email,
        'success' => $successInt,
        'mail_error' => $mailErrorNormalized,
        'created_by' => $created_by,
        'ip' => $ip,
        'template_id' => $templateId
    ];
    $logEntry = json_encode($entryArr, JSON_UNESCAPED_UNICODE);

    if ($stmtUpd = $conn->prepare("UPDATE marketing_templates SET sent_log = JSON_ARRAY_APPEND(COALESCE(sent_log, JSON_ARRAY()), '$', CAST(? AS JSON)) WHERE id = ?")) {
        $stmtUpd->bind_param('si', $logEntry, $templateId);
        if ($stmtUpd->execute()) {
            $logged = true;
            // get the new length to return a log index
            if ($stmtLen = $conn->prepare("SELECT JSON_LENGTH(sent_log) AS cnt FROM marketing_templates WHERE id = ? LIMIT 1")) {
                $stmtLen->bind_param('i', $templateId);
                $stmtLen->execute();
                $resLen = $stmtLen->get_result();
                $rowLen = $resLen->fetch_assoc();
                $cnt = isset($rowLen['cnt']) ? intval($rowLen['cnt']) : null;
                if ($cnt !== null) {
                    $logIndex = $cnt - 1; // zero-based index of appended entry
                }
                $stmtLen->close();
            }
        }
        $stmtUpd->close();
    }

    if ($sent) {
        return [
            'success' => true,
            'message' => 'Correo enviado a ' . $email,
            'mail_error' => $mailError,
            'logged' => $logged,
            'log_index' => $logIndex,
            'email' => $email
        ];
    }

    return [
        'success' => false,
        'message' => 'Error enviando correo',
        'mail_error' => $mailError,
        'logged' => $logged,
        'log_index' => $logIndex,
        'email' => $email
    ];
}

function enviarCorreo_marketing($correo_destino, $asunto, $titulo, $cuerpo, $despedida, $archivoAdjunto = null, $tablaOrigen = '', $leadId = '', $templateId = null, &$mailError = null)
{
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // ensure safe values for URL
    $leadIdEsc = rawurlencode($leadId);
    $tablaEsc = rawurlencode($tablaOrigen);
    $templateEsc = rawurlencode($templateId);
    $templatePart = ($templateEsc !== '') ? "&template_id={$templateEsc}" : '';

    $mensaje = "\n<html>\n<head>\n  <title>$asunto</title>\n  <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n  <style>\n    @import url('https://fonts.googleapis.com/css2?family=Open+Sans&display=swap');\n    body { margin: 0; padding: 0; font-size: 16px; }\n    .bg { width: 100%; margin: 0 auto; padding: 20px 0; background-color: #e8e8e8; box-sizing: border-box; }\n    p { margin: 10px 0; }\n    .container { width: 94%; max-width: 600px; margin: 0 auto; border-radius: 20px; background-color: #fff; line-height: 1.5; font-size: 1rem; box-shadow: 0px 4px 6px rgba(0,0,0,0.1); }\n    .card-container { padding: 10px 15px; margin: 5px; overflow: hidden; }\n    .card-container img { max-width: 100%; height: auto; display: block; }\n    .btn-agenda { font-size: 1rem; width: 100%; }\n    .header { text-align: left; padding: 10px 15px; font-size: 1rem; background-color: #eee8dc; color: #3B3B3B; font-weight: 600; margin-top: 10px; }\n    .content { padding: 10px 0 0 0; margin: 0; }\n    .logo { width: 100px; margin: 0 auto; display: block; }\n    @media screen and (min-width: 600px) {\n      .bg { padding: 40px 0; }\n      .container { font-size: 1.1rem; border-radius: 30px; }\n      .header { font-size: 1.3rem; padding: 10px 30px; }\n      .card-container { padding: 10px 30px; }\n      .logo { width: 130px; }\n      .btn-agenda { font-size: 1.3rem; width: 50%; }\n    }\n    @media screen and (min-width: 768px) {\n      .container { max-width: 750px; font-size: 1.2rem; }\n      .header { font-size: 1.5rem; padding: 10px 50px; }\n      .card-container { padding: 10px 40px; }\n      .logo { width: 140px; }\n      .btn-agenda { font-size: 1.5rem; }\n    }\n  </style>\n</head>\n<body>\n  <div class='bg'>\n    <div class='container'>\n      <div class='content'>\n        <div style='text-align: center; padding: 10px 0;'>\n          <img class='logo' alt='efegephologo' src='https://citas.efegepho.com.mx/admin/assets/img/logofgep.png'/>\n        </div>\n        <div class='header'>\n          $titulo\n        </div>\n        <div class='card-container'>\n          $cuerpo\n          <p>$despedida</p>\n        </div>\n      </div>\n    </div>\n    <div style='text-align: center; margin-top: 15px;'>\n      <img class='logo' alt='efegephologo' src='https://citas.efegepho.com.mx/admin/assets/img/logofgep.png'/>\n    </div>\n    <img src='https://citas.efegepho.com.mx/pixel/open.php?id={$leadIdEsc}&tabla_origen={$tablaEsc}&correo=1{$templatePart}' width='1' height='1' style='display:none'/>\n  </div>\n</body>\n</html>\n";
    $correoRemitente = "info@efegepho.com.mx";
    $nombreRemitente = "InfoEfegepho";
    try {
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Host = 'smtp.gmail.com';
        $mail->Username = $correoRemitente;
        $mail->Password = 'behfyqrcjbrwcqgi';

        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->addAddress($correo_destino, 'Cliente');

        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->ContentType = 'text/html; charset=UTF-8';

        if ($archivoAdjunto && file_exists($archivoAdjunto)) {
            $mail->addAttachment($archivoAdjunto, 'EFEGE_packages.pdf');
        }

        // Log the outgoing HTML and metadata for debugging pixel inclusion and attribution
        $logData = "[" . date('c') . "] TO: {$correo_destino} | SUBJECT: {$asunto} | TEMPLATE_ID: {$templateId} | LEAD: {$leadId} | TABLA: {$tablaOrigen}\n";
        $logData .= $mensaje . "\n\n";
        @file_put_contents(__DIR__ . '/marketing_pixel_debug.log', $logData, FILE_APPEND | LOCK_EX);

        $mail->send();
        return true;
    } catch (Exception $e) {
        $mailError = $mail->ErrorInfo ?? $e->getMessage();
        return false;
    }
}
