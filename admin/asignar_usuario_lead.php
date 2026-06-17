<?php
header('Content-Type: application/json');
include 'conn.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Incluir PHPMailer localmente (misma estructura que en otros archivos admin/*)
require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';


function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida, $usuario = '')
{
  // Variable global para reportar mensaje de error más adelante
  global $lastMailError;
  $lastMailError = null;
  // Evitar fallo fatal si PHPMailer no está disponible
  if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    error_log("PHPMailer class not found: cannot send email to $correo_destino");
    return false;
  }
  $mail = new PHPMailer(true);
  $mensaje = "
<html>
<head>
  <title>$asunto</title>
  <style>
    /* Importar la fuente desde Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond&display=swap');

    .bg{
      width: 96%;
      margin: 0 auto;
      padding: 50px 0px;
      background-color: #e8e8e8;
      font-family: 'Cormorant Garamond', serif;
    }

    p {
      margin: 15px;
    }

    .container {
      width: 450px;
      margin: 0 auto;
      border-radius: 30px;
      background-color: #fff;
      line-height: 1.5;
      font-size: 1.2rem;
      box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    }

    .card-container {
      padding: 10px 30px;
      margin: 10px;
    }

    .header {
      text-align: left;
      padding: 10px 50px;
      font-size: 1.5rem;
      background-color: #eee8dc;
      color: black;
      font-weight: 500;
      margin-top: 13px; /* Ajusta el margen superior para bajar el encabezado */
    }

    .content {
      padding: 20px 0px 0px 0px;
      margin: 0px;
    }

  </style>
</head>
<body>
  <div class='bg'>
    <div class='container'>
      <div class='content'>
        <div class='header'>
          $titulo
        </div>
        <div class='card-container'>
          <p>$cuerpo</p>
          <p>$despedida</p>
        </div>
      </div>
    </div>
    <div style='text-align: center;'>
          <img style='width: 140px; margin: 0 auto;'  alt='efegephologo' src='https://sandbox.efegepho.com.mx/admin/assets/img/logofgep.png'/>

    </div>
  </div>
</body>
</html>
";
  // Encabezados para enviar el correo como HTML
  $headers = "MIME-Version: 1.0" . "\r\n";
  $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";

  // Enviar correo
  // return mail($correo_destino, $asunto, $mensaje, $headers);
  $correoRemitente = "info@efegepho.com.mx";
  $nombreRemitente = "InfoEfegepho";

  try {
    // Servidor SMTP
    $mail->isSMTP();  // Usar SMTP

    $mail->SMTPAuth = true; // Usar autenticación SMTP
    $mail->SMTPSecure = 'starttls'; // Usar encriptación TLS
    $mail->Port = 587; // Puerto del servidor SMTP (587 para STARTTLS)

    $mail->Host = 'smtp.gmail.com';
    $mail->Username = $correoRemitente; // Tu correo de Gmail
    $mail->Password = 'glhewzgjzdnsbuvj';

    // Receptor del correo
    $mail->setFrom($correoRemitente, $nombreRemitente);
    $mail->addAddress($correo_destino, 'Cliente'); // Reemplaza por el correo del destinatario
    // Enviar copia (CC) a la dirección de info
    $mail->addCC($correoRemitente, $nombreRemitente);

    // Asunto y contenido del correo
    $mail->Subject = $asunto;
    $mail->isHTML(true);
    $mail->Body = $mensaje; // Convierte saltos de línea en <br> para formato HTML

    // // Adjuntar archivo (si hay uno)
    // if ($fileAttached) {
    //     $mail->addAttachment($destPath, $fileName); // Adjuntar el archivo
    // }

    // Enviar correo
    // if ($mail->send()) {
    //     // Respuesta exitosa
    //     echo json_encode(['status' => 'success', 'message' => 'Correo enviado con éxito.']);
    // } else {
    //     echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar el correo.']);
    // }
    return $mail->send();
  } catch (Throwable $e) {
    // Registrar el error y almacenar el mensaje para que el llamador pueda reaccionar.
    $lastMailError = $e->getMessage() . ' | ' . $mail->ErrorInfo;
    error_log("Error enviando correo a $correo_destino: " . $lastMailError);
    return false;
  }
}

$id = isset($_POST['id']) ? trim($_POST['id']) : '';
$tabla = isset($_POST['tabla_origen']) ? trim($_POST['tabla_origen']) : '';
// normalize usuario_id
$usuario_id = isset($_POST['usuario_id']) ? trim($_POST['usuario_id']) : '';
$usuario_id_int = $usuario_id === '' ? null : intval($usuario_id);

if ($id === '' || $tabla === '') {
  echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
  exit;
}

// Validar que la tabla sea una tabla de leads conocida
$stmt = $conn->prepare('SELECT 1 FROM tablas_leads WHERE nombre = ? LIMIT 1');
if (!$stmt) {
  echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
  exit;
}
$stmt->bind_param('s', $tabla);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
  echo json_encode(['success' => false, 'message' => 'Tabla de leads no válida']);
  exit;
}

// Si se proporcionó usuario_id no vacío, validar que exista y que tipoUsu sea 0 o 1
// Excepción: permitir la asignación al agente IA con id 99 aunque no exista en la tabla `usuarios`.
if ($usuario_id !== '' && $usuario_id_int !== 99) {
  $stmtU = $conn->prepare('SELECT tipoUsu FROM usuarios WHERE id = ? LIMIT 1');
  if (!$stmtU) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
    exit;
  }
  $stmtU->bind_param('i', $usuario_id_int);
  $stmtU->execute();
  $resU = $stmtU->get_result();
  if (!$resU || $resU->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit;
  }
  $rowU = $resU->fetch_assoc();
  if (!in_array((int) $rowU['tipoUsu'], [0, 1], true)) {
    echo json_encode(['success' => false, 'message' => 'Usuario no es vendedor válido']);
    exit;
  }
}

// Preparar y ejecutar la actualización. No se puede parametrizar el nombre de la tabla, así que lo escapamos
$safeTable = str_replace('`', '', $tabla);
$safeTable = $conn->real_escape_string($safeTable);

if ($usuario_id === '') {
  $sql = "UPDATE `" . $safeTable . "` SET usuario_asignado = NULL WHERE id = ?";
  $stmtUp = $conn->prepare($sql);
  if (!$stmtUp) {
    echo json_encode(['success' => false, 'message' => 'Error preparando la consulta']);
    exit;
  }
  $stmtUp->bind_param('i', $id);
} else {
  $sql = "UPDATE `" . $safeTable . "` SET usuario_asignado = ? WHERE id = ?";
  $stmtUp = $conn->prepare($sql);
  if (!$stmtUp) {
    echo json_encode(['success' => false, 'message' => 'Error preparando la consulta']);
    exit;
  }
  // bind integer usuario id
  $uid = $usuario_id === '' ? null : intval($usuario_id);
  $stmtUp->bind_param('ii', $uid, $id);
}

if ($stmtUp->execute()) {
  // Enviar email a proyectos@conlineweb.com cuando se asigna un vendedor (usuario_id no vacío)
  if ($usuario_id !== '') {
    // Obtener datos del lead y del vendedor para el correo
    $leadRes = $conn->query("SELECT * FROM `" . $safeTable . "` WHERE id = " . intval($id) . " LIMIT 1");
    $lead = ($leadRes && $leadRes->num_rows > 0) ? $leadRes->fetch_assoc() : null;

    // If the assigned user is the special IA agent (99) and not present in `usuarios`, emulate vendor info
    if ($usuario_id_int === 99) {
      $vendor = ['nombre' => 'Agente llamada', 'apepat' => '(IA)', 'correo' => ''];
    } else {
      $vendorRes = $conn->query("SELECT nombre, apepat, correo FROM usuarios WHERE id = " . intval($usuario_id_int) . " LIMIT 1");
      $vendor = ($vendorRes && $vendorRes->num_rows > 0) ? $vendorRes->fetch_assoc() : null;
    }

    // Preparar información
    $leadName = $lead['full_name'] ?? ($lead['names'] ?? 'Lead sin nombre');
    $leadId = intval($lead['id'] ?? $id);
    $vendorName = trim(($vendor['nombre'] ?? '') . ' ' . ($vendor['apepat'] ?? '')) ?: ($vendor['nombre'] ?? 'Vendedor');
    $vendorEmail = $vendor['correo'] ?? '';
    $asignDate = date('Y-m-d H:i:s');

    // Construir asunto y cuerpo
    // Incluir el nombre de la vendedora en el asunto
    $subject = "Lead assigned: {$leadName} - Seller: {$vendorName}";
    $title = "A lead was assigned to {$vendorName}";
    // Usar campaign_name si existe (en lugar del nombre de la tabla)
    $campaignName = $lead['campaign_name'] ?? $lead['campaign'] ?? $lead['form_name'] ?? $safeTable;
    $body = "The lead <strong>{$leadName}</strong> (ID: {$leadId}) has been assigned to seller <strong>{$vendorName}</strong>.<br>Campaign: <strong>{$campaignName}</strong>.<br>Assignment date/time: <strong>{$asignDate}</strong>.<br>Seller email: <strong>{$vendorEmail}</strong>.<br>";

    // Intentar enviar correo usando PHPMailer si está disponible
    $mailOK = enviarCorreo('juanpablo.ggomez@gmail.com', $subject, "<h3>{$title}</h3>", $body, 'Regards,', "");
        if (!$mailOK) {
          // Registrar para depuración; el código API aún responde con success=true
          global $lastMailError;
          error_log("asignar_usuario_lead.php: No se pudo enviar correo de notificación para lead {$leadId} (campana {$campaignName}) asignado a usuario {$usuario_id}. Error: " . ($lastMailError ?? ''));
        }
  }

  $resp = ['success' => true];
  if (isset($mailOK))
    $resp['mail_sent'] = $mailOK ? true : false;
  if (!$mailOK) {
    global $lastMailError;
    if (!empty($lastMailError)) $resp['mail_error'] = $lastMailError;
  }
  echo json_encode($resp);
} else {
  echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el lead']);
}

?>