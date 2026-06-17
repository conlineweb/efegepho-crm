<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

include 'conn.php';
date_default_timezone_set('Mexico/General');

// Catch fatal errors and return JSON so the client can show details.
register_shutdown_function(function () {
  $err = error_get_last();
  if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
    http_response_code(500);
    $payload = [
      'success' => false,
      'message' => 'Error fatal en el servidor',
      'php_error' => $err
    ];
    error_log("enviar_segundo_correo_lead.php fatal: " . print_r($err, true));
    echo json_encode($payload);
    flush();
    exit;
  }
});

// Log incoming POST for debugging (avoid leaking logs publicly!)
error_log("enviar_segundo_correo_lead.php POST: " . print_r(array_intersect_key($_POST, array_flip(['id','tabla_origen','email','full_name'])), true));

// Cargar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';

// Verificar que se recibieron los datos necesarios
if (!isset($_POST['id']) || !isset($_POST['tabla_origen']) || !isset($_POST['full_name']) || !isset($_POST['email'])) {
  echo json_encode([
    'success' => false,
    'message' => 'Faltan datos requeridos'
  ]);
  exit;
}

$id = intval($_POST['id']);
$tablaOrigen = $_POST['tabla_origen'];
$fullName = $_POST['full_name'];
$whenMarried = isset($_POST['when_are_you_getting_married']) ? $_POST['when_are_you_getting_married'] : '';
$email = $_POST['email'];

// Sanitizar el nombre de la tabla para evitar SQL injection
$tablaOrigen = preg_replace('/[^a-zA-Z0-9_]/', '', $tablaOrigen);

// Verificar que el email no esté vacío
if (empty($email)) {
  echo json_encode([
    'success' => false,
    'message' => 'El email es requerido'
  ]);
  exit;
}

// Verificar que la tabla existe
$checkTable = $conn->query("SHOW TABLES LIKE '$tablaOrigen'");
if ($checkTable->num_rows === 0) {
  echo json_encode([
    'success' => false,
    'message' => 'La tabla especificada no existe'
  ]);
  exit;
}

// Función para ejecutar consultas y obtener los resultados 
function obtenerDatos($conn, $tabla)
{
  if ($tabla == "calendario") {
    $sql = "SELECT * FROM $tabla WHERE fecha != 0000-00-00 AND estatus != 3";
    $result = $conn->query($sql);

    // Verificar si la consulta se ejecutó correctamente
    if (!$result) {
      die("Error en la consulta: " . $conn->error);
    }

    return ($result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
  } else {
    $sql = "SELECT * FROM $tabla";
    $result = $conn->query($sql);

    // Verificar si la consulta se ejecutó correctamente
    if (!$result) {
      die("Error en la consulta: " . $conn->error);
    }

    return ($result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
  }
}


$txt_correo = obtenerDatos($conn, 'txtCorreo');




// Función para enviar el correo
function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida, $tablaOrigen = '', $leadId = '', &$mailError = null)
{
  $mail = new PHPMailer(true);

  // Contenido del correo HTML
  $mensaje = "
<html>
<head>
  <title>$asunto</title>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <style>
    /* Importar la fuente desde Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Open+Sans&display=swap');
    
    body {
      margin: 0;
      padding: 0;
    }
    
    .bg{
      width: 96%;
      margin: 0 auto;
      padding: 50px 0px;
      background-color: #e8e8e8;
      font-family: 'Open Sans', sans-serif;
    }
    
    p {
      margin: 15px;
    }
    
    .container {
      width: 90%;
      max-width: 600px;
      margin: 0 auto;
      border-radius: 30px;
      background-color: #fff;
      line-height: 1.5;
      font-size: 1.5rem;
      box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .card-container {
      padding: 10px 20px;
      margin: 10px;
    }
    
    .btn-agenda {
         font-size: 1.5rem;
         width: 100%;
    }
    .header {
      text-align: left;
      padding: 10px 30px;
      font-size: 1.5rem;
      background-color: #eee8dc;
       color: #3B3B3B; 
      font-weight: 500;
      margin-top: 13px;
    }
    
    .content {
      padding: 20px 0px 0px 0px;
      margin: 0px;
    }
    
    .logo {
      width: 120px;
      margin: 0 auto;
    }
    
    /* Media query para pantallas grandes (PC) */
    @media screen and (min-width: 768px) {
      .container {
        max-width: 750px;
        font-size: 1.5rem;
      }
      
      .header {
        font-size: 1.7rem;
        padding: 10px 50px;
      }
      
      .card-container {
        padding: 10px 30px;
      }
      
      .logo {
        width: 140px;
      }
      .btn-agenda {
          font-size: 1.7rem;
         width: 50%;
    }
    }
  </style>
</head>
<body>
  <div class='bg'>
    <div class='container'>
      <div class='content'>
        <div style='text-align: center;'>
          <img class='logo' alt='efegephologo' src='https://citas.efegepho.com.mx/admin/assets/img/logofgep.png'/>
        </div>
        <div class='header'>
          $titulo
        </div>
        <div class='card-container'>
          <p>$cuerpo</p>
          <p>$despedida</p>
        </div>
      </div>
    </div>
    <div style='text-align: center; margin-top: 20px;'>
      <img class='logo' alt='efegephologo' src='https://citas.efegepho.com.mx/admin/assets/img/logofgep.png'/>
    </div>
    <img src='https://citas.efegepho.com.mx/pixel/open.php?id={$leadId}&tabla_origen={$tablaOrigen}&correo=1' width='1' height='1'/>
  </div>
</body>
</html>
";

  // Encabezados para enviar el correo como HTML
  $headers = "MIME-Version: 1.0" . "\r\n";
  $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";

  // Enviar correo
  //  mail($correo_destino, $asunto, $mensaje, $headers);
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
   // Enviar copia (CC) a Juan Pablo
    $mail->addCC('juanpablo.ggomez@gmail.com', 'Juan Pablo');
    // Enviar copia (CC) a Alexia
    // $mail->addCC('Alexiazdaa1@gmail.com', 'Alexia');
    // Asunto y contenido del correo
    $mail->Subject = $asunto;
    $mail->isHTML(true);
    $mail->Body = $mensaje; // Convierte saltos de línea en <br> para formato HTML

    //UTF-8
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->ContentType = 'text/html; charset=UTF-8';

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
    $mail->send();
    return true;
  } catch (Exception $e) {
    $mailError = $mail->ErrorInfo ?? $e->getMessage();
    return false;
  }
}


$isUsaForm = false;

// convertir a minúsculas para evitar problemas de mayúsculas
$tablaOrigenLower = strtolower($tablaOrigen);

if (strpos($tablaOrigenLower, 'usa') !== false || strpos($tablaOrigenLower, 'andromeda') !== false) {
    $isUsaForm = true;
}


// También marcar como USA si el campo campaign_name del registro comienza con 'E10'
$campaignName = '';
if ($stmt = $conn->prepare("SELECT campaign_name FROM `$tablaOrigen` WHERE id = ? LIMIT 1")) {
  $stmt->bind_param("i", $id);
  if ($stmt->execute()) {
    $stmt->bind_result($campaignName);
    $stmt->fetch();
  }
  $stmt->close();
}
if (!empty($campaignName) && stripos($campaignName, 'E10') === 0) {
  $isUsaForm = true;
}


// $indexCorreo = (!$isUsaForm) ? 25 : 27; //25 español - 27 ingles
$indexCorreo = 27;
$asunto = ($indexCorreo == 25) ? "Seguimiento" : "Follow up";

$linkFormRegister = "https://www.citas.efegepho.com.mx/inquire-form.php";
// Añadir tabla de origen e id por GET al enlace
$separator = (strpos($linkFormRegister, '?') !== false) ? '&' : '?';
$linkWithParams = $linkFormRegister . $separator . 'tabla_origen=' . urlencode($tablaOrigen) . '&id=' . urlencode($id);
$buttonText = (!$isUsaForm) ? 'Schedule now' : 'Schedule now';

// URL de tracking: redirige al destino real y registra el click
$trackUrl = 'https://citas.efegepho.com.mx/pixel/click.php?id=' . urlencode($id)
  . '&tabla_origen=' . urlencode($tablaOrigen)
  . '&correo=2&url=' . urlencode($linkWithParams);

$link_form = "<a class='btn-agenda' href='" . $trackUrl . "' style='display: block; margin: 40px auto 20px auto; padding: 16px 24px; background-color: #eee8dc; border: 1.5px solid #3B3B3B; border-radius: 15px; color: #3B3B3B; text-align: center; text-decoration: none; font-weight: 600; font-family: \"Open Sans\", sans-serif; box-sizing: border-box; cursor: pointer;' role='button'>" . $buttonText . "</a>";

$titulo = $txt_correo[$indexCorreo]['txttituloclie'];
$titulo = str_replace('$full_name', $fullName, $titulo);
$titulo = str_replace('$when_are_you_getting_married', $whenMarried, $titulo);
$titulo = str_replace('$link_form', $link_form, $titulo);

$asunto = $titulo;
$cuerpo = $txt_correo[$indexCorreo]['txtcli'];
$cuerpo = str_replace('$full_name', $fullName, $cuerpo);
$cuerpo = str_replace('$when_are_you_getting_married', $whenMarried, $cuerpo);
$cuerpo = str_replace('$link_form', $link_form, $cuerpo);



$despedida = $txt_correo[$indexCorreo]['txtdespclie'];
$despedida = str_replace('$full_name', $fullName, $despedida);
$despedida = str_replace('$when_are_you_getting_married', $whenMarried, $despedida);
$despedida = str_replace('$link_form', $link_form, $despedida);



// Intentar enviar el correo
  try {
  $mailError = null;
  $correoEnviado = enviarCorreo($email, $asunto, $titulo, $cuerpo, $despedida, $tablaOrigen, $id, $mailError);

  if ($correoEnviado) {
    // Actualizar la base de datos para marcar el correo como enviado
    // Marcar como enviado y registrar la fecha de envío
    $stmt = $conn->prepare("UPDATE `$tablaOrigen` SET correo_dos_enviado = 1, fecha_envio_correo_dos = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
      echo json_encode([
        'success' => true,
        'message' => 'Segundo correo enviado exitosamente a ' . $email
      ]);
    } else {
      http_response_code(500);
      $payload = [
        'success' => false,
        'message' => 'Correo enviado pero no se pudo actualizar la base de datos'
      ];
      if (isset($stmt) && $stmt->error) {
        $payload['db_error'] = $stmt->error;
        error_log("enviar_segundo_correo_lead.php DB error for lead {$id} in {$tablaOrigen}: " . $stmt->error);
      }
      echo json_encode($payload);
    }
  } else {
    http_response_code(500);
    $payload = [
      'success' => false,
      'message' => 'No se pudo enviar el correo'
    ];
    if (!empty($mailError)) {
      $payload['mail_error'] = $mailError;
      error_log("enviar_segundo_correo_lead.php: PHPMailer error for lead {$id} in {$tablaOrigen}: " . $mailError);
    }
    echo json_encode($payload);
  }
} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'message' => 'Error al enviar el correo: ' . $e->getMessage()
  ]);
}

$conn->close();
