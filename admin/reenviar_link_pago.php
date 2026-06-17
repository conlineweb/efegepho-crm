<?php
ob_start(); // Limpiar cualquier salida previa
header('Content-Type: application/json');
require_once "stripe-php/init.php";
include "conn.php";
error_reporting(E_ALL);

// Cargar clases PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require "./PHPMailer/src/Exception.php";
require "./PHPMailer/src/PHPMailer.php";
require "./PHPMailer/src/SMTP.php";

// Validar y obtener el idPago desde POST
$idpago = isset($_POST["idPago"]) && $_POST["idPago"] !== '' ? $_POST["idPago"] : null;

if (!$idpago) {
    echo json_encode([
        "success" => false,
        "message" => "ID de pago no válido",
    ]);
    exit();
}

// Obtener los datos del pago de la base de datos
$sql = "SELECT * FROM pagos WHERE id_pago = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $idpago);
$stmt->execute();
$result = $stmt->get_result();
$pago = $result->fetch_assoc();

if (!$pago) {
    echo json_encode([
        "success" => false,
        "error" => "Pago no encontrado",
    ]);
    exit();
}


function obtenerDatos($conn, $tabla)
{
    $sql = "SELECT * FROM $tabla";
    $result = $conn->query($sql);

    // Verificar si la consulta se ejecutó correctamente
    if (!$result) {
        die("Error en la consulta: " . $conn->error);
    }

    return $result->num_rows > 0 ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Establecer tu clave secreta de Stripe
\Stripe\Stripe::setApiKey(
    "sk_test_51R4pIeQohDnOGQCj1kxKJ33Mm5OocuovZIzbtqe0wXTQrT9RLfatlrJxzbSSKePzbVo6M1YoaYmWspGHpECgPG2F00t3SNkbCw"
);

// Obtener datos de las tablas
$usuarios = obtenerDatos($conn, "usuarios");
$contact_form = obtenerDatos($conn, "contact_form");
$txt_correo = obtenerDatos($conn, "txtCorreo");

// Buscar al administrador en el array de usuarios
$admins = []; // Inicializamos un array vacío para almacenar a todos los administradores

foreach ($usuarios as $usuario) {
    if ($usuario["tipoUsu"] == 0) {
        // Verifica si es administrador
        $admins[] = $usuario; // Añade el administrador al array de administradores
    }
}

// Indexar usuarios y contactos por ID para búsquedas rápidas
$usuarios_indexados = [];
foreach ($usuarios as $usuario) {
    $usuarios_indexados[$usuario["id"]] = $usuario;
}

$contact_form_indexados = [];
foreach ($contact_form as $contact) {
    $contact_form_indexados[$contact["id"]] = $contact;
}

// Función para enviar el correo
function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida)
{
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
          <img style='width: 140px; margin: 0 auto;' alt='efegephologo' src='https://citas.efegepho.com.mx/admin/assets/img/logofgep.png'/>
        </div>
      </div>
    </body>
    </html>
    ";

    // Encabezados para enviar el correo como HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";

    // Enviar correo
    $mail_enviado = false; // mail($correo_destino, $asunto, $mensaje, $headers);
    $correoRemitente = "info@efegepho.com.mx";
    $nombreRemitente = "InfoEfegepho";
    try {
        // Servidor SMTP
        $mail->isSMTP(); // Usar SMTP
        $mail->SMTPAuth = true; // Usar autenticación SMTP
        $mail->SMTPSecure = "starttls"; // Usar encriptación TLS
        $mail->Port = 587; // Puerto del servidor SMTP (587 para STARTTLS)

        $mail->Host = "smtp.gmail.com";
        $mail->Username = $correoRemitente; // Tu correo de Gmail
        $mail->Password = "glhewzgjzdnsbuvj";

        // Receptor del correo
        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->addAddress($correo_destino, "Cliente"); // Reemplaza por el correo del destinatario

        // Asunto y contenido del correo
        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje; // Convierte saltos de línea en <br> para formato HTML
  //UTF-8
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->ContentType = 'text/html; charset=UTF-8';
        // Enviar correo
        $mail_enviado = $mail->send();
        return $mail_enviado;
    } catch (Exception $e) {
        return ["error" => "Mailer Error: " . $mail->ErrorInfo];
    }
}
// Obtener los datos del pago de la base de datos
$sql = "SELECT * FROM pagos WHERE id_pago = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $idpago);  // si es varchar
$stmt->execute();


$result = $stmt->get_result();
$pago = $result->fetch_assoc();
$idclie = $pago["id_clie"];
$cliente = $contact_form_indexados[$idclie] ?? null;
$mail_enviado_admin = true; // Para evitar error si no entra al foreach
if ($pago) {
    $currency = $pago["currency"]; // Por ejemplo: 'usd' o 'mxn'
    $concepto = $pago["concepto"]; // Descripción del producto o servicio
    $monto_centavos = $pago["monto"] * 100; // Asegúrate de que el monto esté en centavos
    $email = $cliente["email_address"]; // El email del cliente

    // // Crear la sesión de Checkout en Stripe
    try {
        $session = \Stripe\Checkout\Session::create([
            "payment_method_types" => ["card"],
            "line_items" => [
                [
                    "price_data" => [
                        "currency" => $currency, // Moneda
                        "product_data" => [
                            "name" => $concepto,
                        ],
                        "unit_amount" => $monto_centavos, // Monto en centavos
                    ],
                    "quantity" => 1,
                ],
            ],
            "mode" => "payment",
            "customer_email" => $email, // Si tienes el correo del cliente
            "success_url" =>
                "https://citas.efegepho.com.mx/payment_success.php?session_id={CHECKOUT_SESSION_ID}", // URL de éxito
            "cancel_url" =>
                "https://citas.efegepho.com.mx/payment_cancel.php", // URL de cancelación
        ]);

        // Ahora guardamos el session_id en la base de datos
        $session_id = $session->id;
        $payment_url = $session->url;
        // Actualizar la tabla pagos con el session_id
        $update_sql = "UPDATE pagos SET session_id = ? WHERE id_pago = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $session_id, $idpago);

        $update_stmt->execute();

        //mandar_correo
        $monto = $pago["monto"];
        $customer = $cliente["names"];
        $payment_method = "Tarjeta";
        // $indexCorreo = $cliente["country_code"] == "+52" ? 12 : 13; //12 español - 13 ingles
        $indexCorreo =13;
        $amount =
            "$" .
            number_format($monto, 2, ".", ",") .
            " " .
            strtoupper($currency);

        $link_payment = "<a href='" . $payment_url . "'>Ir al pago</a>";
        for ($i = 0; $i < 2; $i++) {
            if ($i == 0) {
                $asunto =
                    $indexCorreo == 12
                        ? "Efectuar el pago"
                        : "Make the payment";
                // Enviar correo al cliente
                $titulo = $txt_correo[$indexCorreo]["txttituloclie"];

                $titulo = str_replace('$customer', $customer, $titulo);
                $titulo = str_replace(
                    '$payment_method',
                    $payment_method,
                    $titulo
                );
                $titulo = str_replace('$amount', $amount, $titulo);
                $titulo = str_replace('$link_payment', $link_payment, $titulo);

                $cuerpo = $txt_correo[$indexCorreo]["txtcli"];

                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                $cuerpo = str_replace(
                    '$payment_method',
                    $payment_method,
                    $cuerpo
                );
                $cuerpo = str_replace('$amount', $amount, $cuerpo);
                $cuerpo = str_replace('$link_payment', $link_payment, $cuerpo);

                $despedida = $txt_correo[$indexCorreo]["txtdespclie"];

                $despedida = str_replace('$customer', $customer, $despedida);
                $despedida = str_replace(
                    '$payment_method',
                    $payment_method,
                    $despedida
                );
                $despedida = str_replace('$amount', $amount, $despedida);
                $despedida = str_replace(
                    '$link_payment',
                    $link_payment,
                    $despedida
                );

                $mail_enviado_customer = enviarCorreo(
                    $cliente["email_address"],
                    $asunto,
                    $titulo,
                    $cuerpo,
                    $despedida
                );
            }
            if ($i == 1) {
                $asunto =
                    $indexCorreo == 12
                        ? "Nuevo pago asignado"
                        : "New payment assigned";

                // Enviar correo al administrador
                $titulo = $txt_correo[$indexCorreo]["txttituloadmin"];

                $titulo = str_replace('$customer', $customer, $titulo);
                $titulo = str_replace(
                    '$payment_method',
                    $payment_method,
                    $titulo
                );
                $titulo = str_replace('$amount', $amount, $titulo);
                $titulo = str_replace('$link_payment', $link_payment, $titulo);

                $cuerpo = $txt_correo[$indexCorreo]["txtadmin"];

                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                $cuerpo = str_replace(
                    '$payment_method',
                    $payment_method,
                    $cuerpo
                );
                $cuerpo = str_replace('$amount', $amount, $cuerpo);
                $cuerpo = str_replace('$link_payment', $link_payment, $cuerpo);

                $despedida = $txt_correo[$indexCorreo]["txtdespadmin"];

                $despedida = str_replace('$customer', $customer, $despedida);
                $despedida = str_replace(
                    '$payment_method',
                    $payment_method,
                    $despedida
                );
                $despedida = str_replace('$amount', $amount, $despedida);
                $despedida = str_replace(
                    '$link_payment',
                    $link_payment,
                    $despedida
                );

                foreach ($admins as $admin) {
                    $mail_enviado_admin = enviarCorreo(
                        $admin["correo"],
                        $asunto,
                        $titulo,
                        $cuerpo,
                        $despedida
                    );
                }
            }
        }

        if ($mail_enviado_customer && $mail_enviado_admin) {
            echo json_encode([
                "success" => true,
                "message" =>
                    "Correo al cliente y al administrador enviados correctamente.",
            ]);
            exit();
        }

        if (!$mail_enviado_customer) {
            echo json_encode([
                "success" => false,
                "message" => "Error al enviar el correo al cliente.",
            ]);
            exit();
        }

        if (!$mail_enviado_admin) {
            echo json_encode([
                "success" => false,
                "message" => "Error al enviar el correo al administrador.",
            ]);
            exit();
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Manejar errores si la sesión de Checkout no se crea
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage(),
        ]);
    }
} else {
    // Si no se encuentra el pago con el idpago, enviar error
    echo json_encode([
        "success" => false,
        "error" => "Pago no encontrado",
    ]);
}
?>
