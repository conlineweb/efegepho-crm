<?php 

header('Content-Type: application/json'); // Esta línea es crucial

// Incluir la librería de Stripe
require_once('stripe-php/init.php');
include 'conn.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Cargar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';

function obtenerDatos($conn, $tabla) {
    $sql = "SELECT * FROM $tabla";
    $result = $conn->query($sql);
    
    if (!$result) {
        die("Error en la consulta: " . $conn->error);
    }

    return ($result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Obtener datos de las tablas
$usuarios = obtenerDatos($conn, 'usuarios');
$contact_form = obtenerDatos($conn, 'contact_form');
$txt_correo = obtenerDatos($conn, 'txtCorreo');

// Buscar al administrador en el array de usuarios
$admins = [];
foreach ($usuarios as $usuario) {
    if ($usuario['tipoUsu'] == 0) {
        $admins[] = $usuario;
    }
}

// Función para enviar el correo
function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida) { 
    $mail = new PHPMailer(true);
    $mensaje = "
    <html>
    <head>
      <title>$asunto</title>
      <style>
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
          margin-top: 13px;
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
          <img style='width: 140px; margin: 0 auto;' alt='efegephologo' src='https://citas.efegepho.com.mx/admin/assets/img/logofgep.png'/>
        </div>
      </div>
    </body>
    </html>
    ";

    $correoRemitente = "info@efegepho.com.mx";
    $nombreRemitente = "InfoEfegepho";
    try {
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'starttls';
        $mail->Port = 587;

        $mail->Host = 'smtp.gmail.com';
        $mail->Username = $correoRemitente;
        $mail->Password = 'glhewzgjzdnsbuvj'; 

        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->addAddress($correo_destino, 'Cliente');

        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;
  //UTF-8
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->ContentType = 'text/html; charset=UTF-8';
        return $mail->send();
    } catch (Exception $e) {
        error_log('Error al enviar correo: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo);
        return false;
    }
}

// Obtener el ID del cliente desde POST
$idcliente = isset($_POST['idcliente']) ? (int) $_POST['idcliente'] : null;
if (!$idcliente) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cliente no válido']);
    exit;
}

// Verificar si existe un registro en nextstep_table para este cliente
$checkQuery = "SELECT * FROM nextstep_table WHERE id_cliente = ?";
$stmt = $conn->prepare($checkQuery);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Error al preparar la consulta: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $idcliente);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'No se encontró registro NextStep para este cliente']);
    exit;
}

// Obtener datos del cliente
$sqlCliente = "SELECT email_address, names, country_code FROM contact_form WHERE id = ?";
$stmtCliente = $conn->prepare($sqlCliente);
if (!$stmtCliente) {
    echo json_encode(['status' => 'error', 'message' => 'Error al preparar consulta del cliente: ' . $conn->error]);
    exit;
}

$stmtCliente->bind_param("i", $idcliente);
$stmtCliente->execute();
$resultCliente = $stmtCliente->get_result();

if ($resultCliente->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'No se encontró el cliente con ID: ' . $idcliente]);
    exit;
}

$cliente = $resultCliente->fetch_assoc();
$stmtCliente->close();

// Preparar el enlace del PDF
$nextstepruta = "https://citas.efegepho.com.mx/uploads_nextstep/NEXTSTEPSEFEGE.pdf";
// $indexCorreo = $cliente["country_code"] == "+52" ? 20 : 21; // 20 español - 21 inglés
$indexCorreo == 21; // Forzar inglés para todos los correos
$link_next = $indexCorreo == 20 
    ? "<a href='" . $nextstepruta . "'>Descarga Archivo PDF</a>"
    : "<a href='" . $nextstepruta . "'>Download PDF File</a>";

// Preparar el correo
$titulo_cliente = $txt_correo[$indexCorreo]["txttituloclie"];
$asunto_cliente = $titulo_cliente;

$titulo_cliente = str_replace('$customer', $cliente["names"], $titulo_cliente);
$txt_cliente = $txt_correo[$indexCorreo]["txtcli"];
$txt_cliente = str_replace('$customer', $cliente["names"], $txt_cliente);
$txt_cliente = str_replace('$linkNextstep', $link_next, $txt_cliente);

$titulo_cliente_despedida = $txt_correo[$indexCorreo]["txtdespclie"];
$titulo_cliente_despedida = str_replace('$customer', $cliente["names"], $titulo_cliente_despedida);

// Enviar el correo
$mail_enviado = enviarCorreo(
    $cliente["email_address"],
    $asunto_cliente,
    $titulo_cliente,
    $txt_cliente,
    $titulo_cliente_despedida
);

if ($mail_enviado) {
    echo json_encode([
        "success" => true,
        "message" => "Enlace NextStep reenviado correctamente al cliente"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error al reenviar el enlace NextStep"
    ]);
}
?>