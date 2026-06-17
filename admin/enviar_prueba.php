<?php
// Endpoint to send a test email for a template (sends to proyectos@conlineweb.com)
include 'conn.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

$sql = "SELECT * FROM txtCorreo WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Plantilla no encontrada']);
    exit;
}

// Build a sample message replacing placeholders with test values
$placeholders = [
    '$customer' => 'Prueba Proyecto',
    '$meet' => '<a href="https://meet.google.com">https://meet.google.com</a>',
    '$date' => (new DateTime())->format('d M Y \a\t h:i A'),
    '$seller' => 'Test Seller',
    '$link_reschedule' => 'https://sandbox.efegepho.com.mx/reschedule',
    '$link_questionnaire' => 'https://sandbox.efegepho.com.mx/questionnaire',
    '$contract_download_link' => 'https://sandbox.efegepho.com.mx/contract.pdf',
    '$assigned_seller_email' => 'seller@example.com',
    '$amount' => '$100',
    '$link_card_payment' => 'https://sandbox.efegepho.com.mx/pay',
    '$bank_transfer_details' => 'TRANS: 123456',
    '$linkNextstep' => 'https://sandbox.efegepho.com.mx/nextstep',
    '$full_name' => 'Prueba Fullname',
    '$expiration_date_gallery' => (new DateTime('+7 days'))->format('d M Y'),
    '$when_are_you_getting_married' => '2026',
    '$link_form' => 'https://sandbox.efegepho.com.mx/inquire'
];

// include rejection reason for templates that show it
$placeholders['$rejection_reason'] = 'Prueba motivo';

$asunto = $row['txttituloclie'] ?: $row['txttituloadmin'] ?: $row['txttitulovend'] ?: 'Prueba de plantilla';
$cuerpo = $row['txtcli'] ?: $row['txtadmin'] ?: $row['txtvend'] ?: '';
$despedida = $row['txtdespclie'] ?: $row['txtdespadmin'] ?: $row['txtdespvend'] ?: '';

foreach ($placeholders as $k => $v) {
    $asunto = str_replace($k, $v, $asunto);
    $cuerpo = str_replace($k, $v, $cuerpo);
    $despedida = str_replace($k, $v, $despedida);
}

// Optional: allow testing admin appointment messages with different statuses
// POST 'estatus' can be 1 (atendido) or 3 (no respondido/fantasma). Default to 1.
$estatus_test = isset($_POST['estatus']) ? intval($_POST['estatus']) : 1;

// If testing an admin-template for appointment status (id 23 = Spanish admin, 24 = English admin),
// prepend the appropriate main line so the test email matches the real notification content.
if (in_array($id, [23, 24])) {
    $isSpanish = ($id == 23);
    if ($estatus_test == 1) {
        $mainLine = $isSpanish
            ? '<p>Un nuevo cliente potencial acaba de completar su primera reunión con nuestro equipo de ventas.</p>'
            : '<p>A new lead just completed their first meeting with our sales team.</p>';
    } elseif ($estatus_test == 3) {
        $mainLine = $isSpanish
            ? '<p>Un nuevo cliente potencial tenía programada su primera reunión con nuestro equipo de ventas, pero no pudimos llevarla a cabo.</p>'
            : '<p>A new lead had a meeting scheduled with our sales team, but we couldn\'t carry it out.</p>';
    } else {
        // Fall back to attended sentence
        $mainLine = $isSpanish
            ? '<p>Un nuevo cliente potencial acaba de completar su primera reunión con nuestro equipo de ventas.</p>'
            : '<p>A new lead just completed their first meeting with our sales team.</p>';
    }

    $cuerpo = $mainLine . $cuerpo;
}

$correoDestino = 'proyectos@conlineweb.com';
// allow overriding the destination via POST 'to' or 'email' parameter
if (!empty($_POST['to'])) {
    $correoDestino = filter_var($_POST['to'], FILTER_SANITIZE_EMAIL);
} elseif (!empty($_POST['email'])) {
    $correoDestino = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
}

// Create the same HTML structure used elsewhere in the project
$mensaje = "<html><head><title>" . htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8') . "</title>\n<style>\n@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond&display=swap');\n.bg{width:96%;margin:0 auto;padding:50px 0px;background-color:#e8e8e8;font-family:'Cormorant Garamond',serif;}\np{margin:15px}.container{width:450px;margin:0 auto;border-radius:30px;background-color:#fff;line-height:1.5;font-size:1.2rem;box-shadow:0 4px 6px rgba(0,0,0,.1)}.card-container{padding:10px 30px;margin:10px}.header{text-align:left;padding:10px 50px;font-size:1.5rem;background-color:#eee8dc;color:black;font-weight:500;margin-top:13px}.content{padding:20px 0 0 0;margin:0}</style></head><body><div class='bg'><div class='container'><div class='content'><div class='header'>" . $asunto . "</div><div class='card-container'><p>" . $cuerpo . "</p><p>" . $despedida . "</p></div></div></div><div style='text-align:center;'><img style='width:140px;margin:0 auto;' alt='efegephologo' src='https://sandbox.efegepho.com.mx/admin/assets/img/logofgep.png'/></div></div></body></html>";

// Send via PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'starttls';
    $mail->Port = 587;
    $mail->Host = 'smtp.gmail.com';

    // NOTE: this mirrors the working SMTP config used elsewhere in the project
    $mail->Username = 'info@efegepho.com.mx';
    $mail->Password = 'glhewzgjzdnsbuvj'; // existing password in project (consider using env vars)
    $mail->setFrom('info@efegepho.com.mx', 'InfoEfegepho');
    $mail->addAddress($correoDestino);
    $mail->isHTML(true);
    $mail->Subject = $asunto;
    $mail->Body = $mensaje;
    //utf-8
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->ContentType = 'text/html; charset=UTF-8';
    

    if ($mail->send()) {
        echo json_encode(['status' => 'success', 'message' => 'Correo de prueba enviado a ' . $correoDestino]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar el correo de prueba']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error al enviar: ' . $e->getMessage()]);
}

$stmt->close();
$conn->close();

?>