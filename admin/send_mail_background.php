<?php
// Background script to send notification email when a lead is assigned.
// Usage (CLI): php -f send_mail_background.php --id=123 --tabla=formusa1 --usuario=7
// Designed to be executed in background via shell (nohup ... &)

// Allow being called via HTTP POST for debugging if necessary
$cli = php_sapi_name() === 'cli';
$options = [];
if ($cli) {
    // Parse CLI-style --key=value arguments
    foreach ($argv as $arg) {
        if (substr($arg, 0, 2) === '--') {
            $parts = explode('=', substr($arg, 2), 2);
            $options[$parts[0]] = $parts[1] ?? '';
        }
    }
} else {
    $options['id'] = $_POST['id'] ?? $_GET['id'] ?? '';
    $options['tabla'] = $_POST['tabla'] ?? $_GET['tabla'] ?? '';
    $options['usuario'] = $_POST['usuario'] ?? $_GET['usuario'] ?? '';
}

// Minimal validation
$leadId = isset($options['id']) ? intval($options['id']) : 0;
$safeTable = isset($options['tabla']) ? $options['tabla'] : '';
$usuario_id = isset($options['usuario']) ? intval($options['usuario']) : 0;

if ($leadId <= 0 || $safeTable === '' || $usuario_id <= 0) {
    error_log("send_mail_background.php called with insufficient params: " . json_encode($options));
    exit(0);
}

// Ensure environment is same directory
require_once 'conn.php';

// PHPMailer includes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';

// Query lead & vendor info again (safely)
$leadRes = $conn->query("SELECT * FROM `" . $conn->real_escape_string($safeTable) . "` WHERE id = " . intval($leadId) . " LIMIT 1");
$lead = ($leadRes && $leadRes->num_rows > 0) ? $leadRes->fetch_assoc() : null;
$vendorRes = $conn->query("SELECT nombre, apepat, correo FROM usuarios WHERE id = " . intval($usuario_id) . " LIMIT 1");
$vendor = ($vendorRes && $vendorRes->num_rows > 0) ? $vendorRes->fetch_assoc() : null;

$leadName = $lead['full_name'] ?? ($lead['names'] ?? 'Lead sin nombre');
$leadId = intval($lead['id'] ?? $leadId);
$vendorName = trim(($vendor['nombre'] ?? '') . ' ' . ($vendor['apepat'] ?? '')) ?: ($vendor['nombre'] ?? 'Vendedor');
$vendorEmail = $vendor['correo'] ?? '';
$asignDate = date('Y-m-d H:i:s');
$campaignName = $lead['campaign_name'] ?? $lead['campaign'] ?? $lead['form_name'] ?? $safeTable;

$subject = "Lead asignado: {$leadName} (ID {$leadId}) - Vendedora: {$vendorName}";
$title = "Se asignó un lead";
$body = "El lead <strong>{$leadName}</strong> (ID: {$leadId}) ha sido asignado al vendedor <strong>{$vendorName}</strong>.<br>Campaña: <strong>{$campaignName}</strong>.<br>Fecha y hora de asignación: <strong>{$asignDate}</strong>.<br>Correo del vendedor: <strong>{$vendorEmail}</strong>.<br>";

// Enviar correo a proyectos@conlineweb.com para notificación
$correo_destino = 'proyectos@conlineweb.com';
$correoRemitente = "info@efegepho.com.mx";
$nombreRemitente = "InfoEfegepho";
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'starttls';
    $mail->Port = 587;
    $mail->Host = 'smtp.gmail.com';
    $mail->Username = $correoRemitente;
    $mail->Password = 'glhewzgjzdnsbuvj';
    $mail->setFrom($correoRemitente, $nombreRemitente);
    $mail->addAddress($correo_destino, 'Proyectos');
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body = "<h3>{$title}</h3>" . $body;
    if (!$mail->send()) {
        error_log("send_mail_background: mail send failed: " . $mail->ErrorInfo);
    } else {
        error_log("send_mail_background: mail sent success for lead {$leadId}");
    }
} catch (Exception $e) {
    error_log('send_mail_background: exception sending email: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo);
}
exit(0);
