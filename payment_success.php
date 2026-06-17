<?php
require_once('admin/stripe-php/init.php');
include 'conn.php';

// Cargar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './admin/PHPMailer/src/Exception.php';
require './admin/PHPMailer/src/PHPMailer.php';
require './admin/PHPMailer/src/SMTP.php';



require_once __DIR__ . '/../vendor/autoload.php'; // sube una carpeta hasta /home/usuario/vendor/

use Dotenv\Dotenv;

// Cargar .env que está en tu proyecto
$dotenv = Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();


// Función para ejecutar consultas y obtener los resultados
function obtenerDatos($conn, $tabla) {
    $sql = "SELECT * FROM $tabla";
    $result = $conn->query($sql);
    
    // Verificar si la consulta se ejecutó correctamente
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
$admins = []; // Inicializamos un array vacío para almacenar a todos los administradores

foreach ($usuarios as $usuario) {
    if ($usuario['tipoUsu'] == 0) {  // Verifica si es administrador
        $admins[] = $usuario; // Añade el administrador al array de administradores
    }
}

// Indexar usuarios y contactos por ID para búsquedas rápidas
$usuarios_indexados = [];
foreach ($usuarios as $usuario) {
    $usuarios_indexados[$usuario['id']] = $usuario;
}

$contact_form_indexados = [];
foreach ($contact_form as $contact) {
    $contact_form_indexados[$contact['id']] = $contact;
}

// Función para enviar el correo
function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida) { 
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

    // Enviar correo
    $mail_enviado = false;
    $correoRemitente = "info@efegepho.com.mx";
    $nombreRemitente = "InfoEfegepho";
    try {
        // Servidor SMTP
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'starttls';
        $mail->Port = 587;

        $mail->Host = 'smtp.gmail.com';
        $mail->Username = $correoRemitente;
        $mail->Password = 'glhewzgjzdnsbuvj';

        // Receptor del correo
        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->addAddress($correo_destino, 'Cliente');

        // Asunto y contenido del correo
        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;

        // Enviar correo
        $mail_enviado = $mail->send();
        return $mail_enviado;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo]);
    }
}

// Configura tu clave secreta de Stripe
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

// Obtén el session_id de la URL
$session_id = $_GET['session_id'];

try {
    // Recupera la sesión de Checkout utilizando el session_id
    $session = \Stripe\Checkout\Session::retrieve($session_id);

    // Verifica el estado de la sesión
    if ($session->payment_status == 'paid') {
    // El pago fue exitoso
    $fechaPago = date('Y-m-d');  // Fecha actual en formato YYYY-MM-DD
    $horaPago = date('H:i:s');   // Hora actual en formato HH:MM:SS

     echo "<div class='message-container'>";
     echo " <img style='width: 240px; margin: 0 auto; padding-bottom:30px'  alt='efegephologo' src='https://sandbox.efegepho.com.mx/admin/assets/img/logofgep.png'/>";
        echo "<p class='message m-title'>¡Gracias por tu pago! El pago ha sido exitoso.</p>";

    // Primero, buscamos el registro del pago
    $sqlQuery = "SELECT * FROM pagos WHERE session_id = ?";
    
    // Preparamos la consulta para obtener los datos del pago
    $stmt = $conn->prepare($sqlQuery);
    if ($stmt === false) {
        die("Error al preparar la consulta SQL: " . $conn->error);
    }

    // Enlazamos el parámetro session_id
    $stmt->bind_param("s", $session_id);
    
    // Ejecutamos la consulta
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Si encontramos el registro, almacenamos los datos en la variable
        $registroPago = $result->fetch_assoc();
        $idcliente = $registroPago['id_clie'];
        $id_payment = $registroPago['id_pago'];

        // Verificamos el estatus del pago
        if ($registroPago['estatus'] == 0) {
            // Si el estatus es 0, actualizamos el registro con la fecha y hora del pago
            $sqlUpdate = "UPDATE pagos SET forma_pago = 1, estatus = 1, fecha_pago = ?, hora_pago = ? WHERE session_id = ?";
            
            // Preparamos la consulta para actualizar
            $stmtUpdate = $conn->prepare($sqlUpdate);
            
            if ($stmtUpdate === false) {
                die("Error al preparar la consulta SQL de actualización: " . $conn->error);
            }
            
            // Vinculamos los parámetros de la consulta
            $stmtUpdate->bind_param("sss", $fechaPago, $horaPago, $session_id);
            
            // Ejecutamos la actualización
            if ($stmtUpdate->execute()) {
                // Si la actualización fue exitosa, procedemos a enviar el correo
                $cliente = $contact_form_indexados[$idcliente] ?? null;

                // Verificamos si encontramos al cliente
                if ($cliente) {
                     $customer = $cliente['names'];
                   
                   switch ($registroPago['forma_pago']) {
                        case 1:
                            $payment_method = "Tarjeta";
                            break;
                        case 3:
                            $payment_method = "Transferencia";
                            break;
                        default:
                            $payment_method = "Efectivo";
                            break;
                    }

                    
                    
                    //ENVIAR CORREO DE NEXTSTEP
                    
                    
                    
                        $nextstepruta = "https://sandbox.efegepho.com.mx/uploads_nextstep/NEXTSTEPSEFEGE.pdf";
                        // $indexCorreo1 = $cliente["country_code"] == "+52" ? 20 : 21;
                        $indexCorreo1 = 21;
                        // Verificar si el cliente ya tiene registro en nextstep_table
                        $sqlCheckNextstep = "SELECT id FROM nextstep_table WHERE id_cliente = ?";
                        $stmtCheck = $conn->prepare($sqlCheckNextstep);
                        $stmtCheck->bind_param("i", $idcliente);
                        $stmtCheck->execute();
                        $resultCheck = $stmtCheck->get_result();
                        
                        if ($resultCheck->num_rows == 0) {
                            // No existe registro, procedemos a insertar
                            $fechaRegistro = date('Y-m-d');
                            $horaRegistro = date('H:i:s');
                            $documento = "NEXTSTEPSEFEGE.pdf";
                            $nombreArchivo = "../uploads_nextstep/NEXTSTEPSEFEGE.pdf";
                            
                            $sqlInsertNextstep = "INSERT INTO nextstep_table 
                                                 (id_cliente, fecha_registro, hora_registro, documento, nombre_archivo) 
                                                 VALUES (?, ?, ?, ?, ?)";
                            
                            $stmtInsert = $conn->prepare($sqlInsertNextstep);
                            $stmtInsert->bind_param("issss", $idcliente, $fechaRegistro, $horaRegistro, $documento, $nombreArchivo);
                            
                            if ($stmtInsert->execute()) {
                                // Insert exitoso, ahora enviamos el correo
                                $link_next = $indexCorreo1 == 20 
                                    ? "<a href='".$nextstepruta."'>Descarga Archivo PDF</a>" 
                                    : "<a href='".$nextstepruta."'>Download PDF File</a>";
                                
                                $titulo_cliente = $txt_correo[$indexCorreo1]["txttituloclie"];
                                $asunto_cliente = $titulo_cliente;
                                $titulo_cliente = str_replace(
                                    ['$customer'],
                                    [$cliente["names"]],
                                    $titulo_cliente
                                );
                                
                                $txt_cliente = $txt_correo[$indexCorreo1]["txtcli"];
                                $txt_cliente = str_replace(
                                    ['$customer', '$linkNextstep'],
                                    [$cliente["names"], $link_next],
                                    $txt_cliente
                                );
                                
                                $titulo_cliente_despedida = $txt_correo[$indexCorreo1]["txtdespclie"];
                                $titulo_cliente_despedida = str_replace(
                                    ['$customer'],
                                    [$cliente["names"]],
                                    $titulo_cliente_despedida
                                );
                                
                                $mail_enviado_nextstep = enviarCorreo(
                                    $cliente["email_address"],
                                    $asunto_cliente,
                                    $titulo_cliente,
                                    $txt_cliente,
                                    $titulo_cliente_despedida
                                );
                                
                                if (!$mail_enviado_nextstep) {
                                    // Opcional: manejar error de envío de correo
                                }
                            } else {
                                // Opcional: manejar error de inserción
                            }
                            
                          
                        }
                        
                      
                                            
                    
                    
                    
                    
                    
                    //ENVIO CORREO PAGO APROBADO

                    //  $indexCorreo = ($cliente['country_code'] == '+52') ? 14 : 15; //12 español - 13 ingles
                    $indexCorreo = 15;
                     $amount = "$" . number_format($registroPago['monto'], 2, '.', ',') . " " .strtoupper($registroPago['currency']);
                     for ($i = 0; $i < 2; $i++) {
                             if ($i == 0) {
                                                                   
                            $asunto = ($indexCorreo == 14)?"Pago exitoso":"Successful payment";
                            // Enviar correo al cliente
                            $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                            
                            $titulo = str_replace('$customer', $customer, $titulo);
                            $titulo = str_replace('$payment_method', $payment_method, $titulo);
                            $titulo = str_replace('$amount', $amount, $titulo);
                            $titulo = str_replace('$id_payment', $id_payment, $titulo);

                            // $titulo = str_replace('$link_payment', $link_payment, $titulo);
            
                            
                            $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                            
                            $cuerpo = str_replace('$customer', $customer, $cuerpo);
                            $cuerpo = str_replace('$payment_method', $payment_method, $cuerpo);
                            $cuerpo = str_replace('$amount', $amount, $cuerpo);
                            // $cuerpo = str_replace('$link_payment', $link_payment, $cuerpo);
                                        $cuerpo = str_replace('$id_payment', $id_payment, $cuerpo);

                            
                            $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                            
                            $despedida = str_replace('$customer', $customer, $despedida);
                            $despedida = str_replace('$payment_method', $payment_method, $despedida);
                            $despedida = str_replace('$amount', $amount, $despedida);
                            // $despedida = str_replace('$link_payment', $link_payment, $despedida);
                          $despedida = str_replace('$id_payment', $id_payment, $despedida);

                          $mail_enviado_customer =   enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida);
                             }
                                  if ($i == 1) {
                            $asunto = ($indexCorreo == 14)?"Pago exitoso":"Successful payment";

                // Enviar correo al administrador
                $titulo = $txt_correo[$indexCorreo]['txttituloadmin'];
                  
                $titulo = str_replace('$customer', $customer, $titulo);
                $titulo = str_replace('$payment_method', $payment_method, $titulo);
                $titulo = str_replace('$amount', $amount, $titulo);
                //  $titulo = str_replace('$link_payment', $link_payment, $titulo);
                 $titulo = str_replace('$id_payment', $id_payment, $titulo);

                
                $cuerpo = $txt_correo[$indexCorreo]['txtadmin'];
                
                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                $cuerpo = str_replace('$payment_method', $payment_method, $cuerpo);
                $cuerpo = str_replace('$amount', $amount, $cuerpo);
                // $cuerpo = str_replace('$link_payment', $link_payment, $cuerpo);
                 $cuerpo = str_replace('$id_payment', $id_payment, $cuerpo);

                
                $despedida = $txt_correo[$indexCorreo]['txtdespadmin'];
                
                $despedida = str_replace('$customer', $customer, $despedida);
                $despedida = str_replace('$payment_method', $payment_method, $despedida);
                $despedida = str_replace('$amount', $amount, $despedida);
                // $despedida = str_replace('$link_payment', $link_payment, $despedida);
                 $despedida = str_replace('$id_payment', $id_payment, $despedida);

                foreach ($admins as $admin) {
              $mail_enviado_admin =   enviarCorreo($admin['correo'], $asunto, $titulo, $cuerpo, $despedida);
                            }
            }
                     }
                     
                     if ($mail_enviado_customer && $mail_enviado_admin) {
                 echo "<p class='message'>Se ha enviado un correo de confirmación.</p>";
        echo "</div>";
                } else {
                    // Si el correo al cliente falla
                    if (!$mail_enviado_customer) {
                      //  echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al cliente.']);
                    }
                    // Si el correo al administrador falla
                    if (!$mail_enviado_admin) {
                        
//echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al administrador.']);
                    }
                }
                     
                    
                    // // Preparar y enviar el correo
                    // $asunto = "Pago exitoso";
                    // $titulo = "Tu pago acreditado";
                    // $cuerpo = "Tu pago ha sido procesado de forma correcta.";
                    // $despedida = "Gracias por tu confianza.";
                    // $mail_enviado = enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida);

                    // if ($mail_enviado) {
                    //     echo "<br>Se ha enviado un correo de confirmación.";
                    // } else {
                    //     //echo "<br>Hubo un problema al enviar el correo.";
                    // }
                } else {
                    //echo "<br>No se encontró al cliente asociado al pago.";
                }
            } else {
                echo "<br>Error al actualizar el estado del pago.";
            }

            // Cerramos la consulta de actualización
            $stmtUpdate->close();
        } else {
            echo "<br>El pago ya ha sido procesado anteriormente.";
        }
    } else {
        echo "<br>No se encontró el pago con el session_id proporcionado.";
    }

    // Cerramos la consulta de búsqueda
    $stmt->close();
} else {
    echo "El pago no se completó correctamente. Por favor, intente nuevamente.";
}

} catch (\Stripe\Exception\ApiErrorException $e) {
    // Manejar errores en caso de que no se pueda recuperar la sesión
    echo "Hubo un error al verificar el pago: " . $e->getMessage();
}
?>

<style>
 @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond&display=swap');
    * {
        font-size: 1.5rem;
    }
    /* Estilo para centrar los mensajes */
    .message-container {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 90vh; /* Ocupa toda la altura de la pantalla */
        text-align: center;
       font-family: 'Cormorant Garamond', serif;

    }
    .m-title{
          font-weight: bold;
           font-size: 1.7rem;
    }
    .message {
        /*font-size: 1.5rem;*/
      
        margin: 10px 0;
        color: #2d2d2d;
         font-family: 'Cormorant Garamond', serif;

    }
</style>
