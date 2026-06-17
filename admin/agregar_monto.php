<?php

include 'conn.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Cargar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';
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
          <img style='width: 140px; margin: 0 auto;' alt='efegephologo' src='https://sandbox.efegepho.com.mx/admin/assets/img/logofgep.png'/>
        </div>
      </div>
    </body>
    </html>
    ";

    // Encabezados para enviar el correo como HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";

    // Enviar correo
    $mail_enviado = false ;// mail($correo_destino, $asunto, $mensaje, $headers);
    $correoRemitente = "pagos@efegepho.com.mx";
    $nombreRemitente = "InfoEfegepho";
    try {
        // Servidor SMTP
        $mail->isSMTP();  // Usar SMTP
        $mail->SMTPAuth = true; // Usar autenticación SMTP
        $mail->SMTPSecure = 'starttls'; // Usar encriptación TLS
        $mail->Port = 587; // Puerto del servidor SMTP (587 para STARTTLS)

        $mail->Host = 'smtp.gmail.com';
        $mail->Username = $correoRemitente; // Tu correo de Gmail
        $mail->Password = 'cuegumftavsludsy'; 

        // Receptor del correo
        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->addAddress($correo_destino, 'Cliente'); // Reemplaza por el correo del destinatario

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
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo]);
        return false;
    }
}

// Obtener los datos del POST
$idclie = isset($_POST['idclie']) ? $_POST['idclie'] : null;
$fechaActual = isset($_POST['fechaActual']) ? $_POST['fechaActual'] : null;
$horaActual = isset($_POST['horaActual']) ? $_POST['horaActual'] : null;
$estatus = isset($_POST['estatus']) ? $_POST['estatus'] : null;
$monto = isset($_POST['monto']) ? $_POST['monto'] : null; // Obtener el monto
$concepto = isset($_POST['concepto']) ? $_POST['concepto'] : null; // Obtener el concepto
$forma_pago = isset($_POST['forma_pago']) ? $_POST['forma_pago'] : null; // Obtener el forma_pago
$id_pago = isset($_POST['id_pago']) ? $_POST['id_pago'] : null; // Obtener el id_pago
// $email= isset($_POST['email']) ? $_POST['email'] : null; // Obtener el email del cliente
$payment_url = isset($_POST['payment_url']) ? $_POST['payment_url'] : null; // Obtener el payment_url
$session_id = isset($_POST['session_id']) ? $_POST['session_id'] : null; // Obtener el checkout_id 
$currency = isset($_POST['currency']) ? $_POST['currency'] : null; // Obtener el payment_url
$resend = isset($_POST['resend']) ? $_POST['resend'] : null; // Obtener el payment_url
$cuenta= isset($_POST['cuenta']) ? $_POST['cuenta'] : null;

// Lista de los datos recibidos
$missingData = [];

// Validación de datos
if ($idclie === null) {
    $missingData[] = 'idclie';
}
if ($fechaActual === null) {
    $missingData[] = 'fechaActual';
}
if ($horaActual === null) {
    $missingData[] = 'horaActual';
}
if ($estatus === null) {
    $missingData[] = 'estatus';
}
if ($monto === null) {
    $missingData[] = 'monto';
}
if ($concepto === null) {
    $missingData[] = 'concepto';
}
if ($forma_pago === null) {
    $missingData[] = 'forma_pago';
}
if ($id_pago === null) {
    $missingData[] = 'id_pago';
}
// if ($email === null) {
//     $missingData[] = 'email';
// }
if ($payment_url === null) {
    $missingData[] = 'payment_url';
}
if ($currency === null) {
    $missingData[] = 'currency';
}
if ($session_id === null) {
    $missingData[] = 'session_id';
}
if ($resend === null) {
    $missingData[] = 'resend';
}

// Si hay datos faltantes, devolver un mensaje con los nombres de los parámetros faltantes
if (count($missingData) > 0) {
    $missingFields = implode(', ', $missingData);
    echo json_encode([
        'status' => 'error',
        'message' => 'Faltan los siguientes datos en la solicitud: ' . $missingFields
    ]);
    $conn->close();
    exit;
}

// Escapar los datos para evitar inyección SQL
$idclie = $conn->real_escape_string($idclie);
$fechaActual = $conn->real_escape_string($fechaActual);
$horaActual = $conn->real_escape_string($horaActual);
$estatus = $conn->real_escape_string($estatus);
$monto = $conn->real_escape_string($monto);
$concepto = $conn->real_escape_string($concepto);
$forma_pago = $conn->real_escape_string($forma_pago);
$id_pago = $conn->real_escape_string($id_pago);
// $email = $conn->real_escape_string($email);
$currency = $conn->real_escape_string($currency);
$fecha_null = "0000-00-00";
$hora_null = '00:00:00';


$cuenta = $conn->real_escape_string($cuenta); // Protege el valor de $cuenta contra inyecciones SQL

// Consulta para obtener los datos de la cuenta seleccionada
$sqldatatransf = "SELECT * FROM datos_transferencia WHERE id = '$cuenta'";
$resultdatatransf = $conn->query($sqldatatransf);

if ($resultdatatransf->num_rows > 0) {
    // Extrae los datos como un array asociativo
    $dataTransf = $resultdatatransf->fetch_assoc();
} else {
    // Si no existen datos con el id correspondiente, asignamos valores vacíos a las variables
    $dataTransf = [
        'clabe' => '',
        'tarjeta' => '',
        'nombre_banco' => '',
        'nombre_titular' => ''
    ];
}




if ($resend == '1' ||$resend == 1) {
    $sql = "UPDATE pagos SET session_id = ? WHERE id_pago = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Vincular parámetros
        $stmt->bind_param("si", $session_id, $id_pago);

        // Ejecutar la consulta
        if ($stmt->execute()) {
        // Obtener el ID generado
        $id_generado = $stmt->insert_id;
        
        // if ($forma_pago == 1) {
            // Mandar la URL con el link de pago
             $cliente = $contact_form_indexados[$idclie] ?? null;
             $customer = $cliente['names'];
             $indexCorreo = 13; // Forzar correo en inglés
             $amount = "$" . number_format($monto, 2, '.', ',') ." " .  strtoupper($currency);

             $link_card_payment = "<a href='" . $payment_url . "'>Pay by card</a>";
             if($dataTransf['clabe'] != ''){
                                $bank_transfer_details = "CLABE: " . $dataTransf['clabe'] . "<br> " .
                         "Account No: " . $dataTransf['tarjeta'] . "<br> " .
                         "Bank name: " . $dataTransf['nombre_banco'] . "<br> " .
                         "Account holder: " . $dataTransf['nombre_titular'] . "<br> ";
            }else{
                $bank_transfer_details = "No transfer details available";
            }
             
            for ($i = 0; $i < 2; $i++) {
                if ($i == 0) {
                                   
                $asunto = "Make the payment";
                // Enviar correo al cliente
                $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                
                $titulo = str_replace('$customer', $customer, $titulo);
                $titulo = str_replace('$amount', $amount, $titulo);
                $titulo = str_replace('$link_card_payment', $link_card_payment, $titulo);
                $titulo = str_replace('$bank_transfer_details', $bank_transfer_details, $titulo);

                
                $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                
                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                $cuerpo = str_replace('$amount', $amount, $cuerpo);
                $cuerpo = str_replace('$link_card_payment', $link_card_payment, $cuerpo);
                $cuerpo = str_replace('$bank_transfer_details', $bank_transfer_details, $cuerpo);
                
                $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                
                $despedida = str_replace('$customer', $customer, $despedida);
                $despedida = str_replace('$amount', $amount, $despedida);
                $despedida = str_replace('$link_card_payment', $link_card_payment, $despedida);
                $despedida = str_replace('$bank_transfer_details', $bank_transfer_details, $despedida);

              $mail_enviado_customer =   enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida);
            }
            if ($i == 1) {
                $asunto = "New payment assigned";

                // Enviar correo al administrador
                $titulo = $txt_correo[$indexCorreo]['txttituloadmin'];
                  
                $titulo = str_replace('$customer', $customer, $titulo);
                $titulo = str_replace('$amount', $amount, $titulo);
                 $titulo = str_replace('$link_card_payment', $link_card_payment, $titulo);
                $titulo = str_replace('$bank_transfer_details', $bank_transfer_details, $titulo);

                
                $cuerpo = $txt_correo[$indexCorreo]['txtadmin'];
                
                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                $cuerpo = str_replace('$amount', $amount, $cuerpo);
                $cuerpo = str_replace('$link_card_payment', $link_card_payment, $cuerpo);
                $cuerpo = str_replace('$bank_transfer_details', $bank_transfer_details, $cuerpo);

                
                $despedida = $txt_correo[$indexCorreo]['txtdespadmin'];
                
                $despedida = str_replace('$customer', $customer, $despedida);
                $despedida = str_replace('$amount', $amount, $despedida);
                $despedida = str_replace('$link_card_payment', $link_card_payment, $despedida);
                $despedida = str_replace('$bank_transfer_details', $bank_transfer_details, $despedida);

                foreach ($admins as $admin) {
              $mail_enviado_admin =   enviarCorreo($admin['correo'], $asunto, $titulo, $cuerpo, $despedida);
                            }
            }

                
            }
            
          
            
           if ($mail_enviado_customer && $mail_enviado_admin) {
                    echo json_encode(['status' => 'success', 'message' => 'Correo al cliente y al administrador enviados correctamente.','id_generado' => $id_generado, 'id_pago' => $id_pago]);
                } else {
                    // Si el correo al cliente falla
                    if (!$mail_enviado_customer) {
                        echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al cliente.',]);
                    }
                    // Si el correo al administrador falla
                    if (!$mail_enviado_admin) {
                        echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al administrador.']);
                    }
                }

        // } else {
        //     // Si la forma de pago no es 1, solo respondemos con éxito
        //     echo json_encode(['status' => 'success', 'message' => 'Pago agregado correctamente.', 'id_generado' => $id_generado, 'id_pago' => $id_pago]);
        // }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al agregar el pago: ' . $stmt->error]);
    }

        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al preparar la consulta de actualización: ' . $conn->error]);
    }
}
else {
    // Preparar la consulta SQL
$sql = "INSERT INTO pagos (id_clie, fecha, hora, fecha_pago, hora_pago, monto, estatus, concepto, forma_pago, id_pago,currency, session_id,id_cuenta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,?,?)";
$stmt = $conn->prepare($sql);

if ($stmt) {
    // Vincular parámetros
    $stmt->bind_param("issdssssssssi", $idclie, $fechaActual, $horaActual,$fecha_null,$hora_null, $monto, $estatus, $concepto, $forma_pago, $id_pago,$currency,$session_id,$cuenta);

    // Ejecutar la consulta
    if ($stmt->execute()) {
        // Obtener el ID generado
        $id_generado = $stmt->insert_id;
        
        // if ($forma_pago == 1) {
            // Mandar la URL con el link de pago
             $cliente = $contact_form_indexados[$idclie] ?? null;
             $customer = $cliente['names'];
             $indexCorreo = 13; // Forzar correo en inglés
             $amount = "$" . number_format($monto, 2, '.', ',') ." " .  strtoupper($currency);

               $link_card_payment = "<a href='" . $payment_url . "'>Pay by card</a>";
             if($dataTransf['clabe'] != ''){
                                $bank_transfer_details = "CLABE: " . $dataTransf['clabe'] . "<br> " .
                         "Card No: " . $dataTransf['tarjeta'] . "<br> " .
                         "Bank name: " . $dataTransf['nombre_banco'] . "<br> " .
                         "Account holder: " . $dataTransf['nombre_titular'] . "<br> ";
            }else{
                $bank_transfer_details = "No transfer details available";
            }
            for ($i = 0; $i < 2; $i++) {
                if ($i == 0) {
                                   
                $asunto = "Make the payment";
                // Enviar correo al cliente
                $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                
                $titulo = str_replace('$customer', $customer, $titulo);
                $titulo = str_replace('$amount', $amount, $titulo);
                $titulo = str_replace('$link_card_payment', $link_card_payment, $titulo);
                $titulo = str_replace('$bank_transfer_details', $bank_transfer_details, $titulo);

                
                $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                
                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                $cuerpo = str_replace('$amount', $amount, $cuerpo);
                $cuerpo = str_replace('$link_card_payment', $link_card_payment, $cuerpo);
                $cuerpo = str_replace('$bank_transfer_details', $bank_transfer_details, $cuerpo);

                
                $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                
                $despedida = str_replace('$customer', $customer, $despedida);
                $despedida = str_replace('$amount', $amount, $despedida);
                $despedida = str_replace('$link_card_payment', $link_card_payment, $despedida);
                $despedida = str_replace('$bank_transfer_details', $bank_transfer_details, $despedida);

              $mail_enviado_customer =   enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida);
            }
            if ($i == 1) {
                $asunto = "New payment assigned";

                // Enviar correo al administrador
                $titulo = $txt_correo[$indexCorreo]['txttituloadmin'];
                  
                $titulo = str_replace('$customer', $customer, $titulo);
                $titulo = str_replace('$amount', $amount, $titulo);
                 $titulo = str_replace('$link_card_payment', $link_card_payment, $titulo);
                $titulo = str_replace('$bank_transfer_details', $bank_transfer_details, $titulo);

                
                $cuerpo = $txt_correo[$indexCorreo]['txtadmin'];
                
                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                $cuerpo = str_replace('$amount', $amount, $cuerpo);
                $cuerpo = str_replace('$link_card_payment', $link_card_payment, $cuerpo);
                $cuerpo = str_replace('$bank_transfer_details', $bank_transfer_details, $cuerpo);

                
                $despedida = $txt_correo[$indexCorreo]['txtdespadmin'];
                
                $despedida = str_replace('$customer', $customer, $despedida);
                $despedida = str_replace('$amount', $amount, $despedida);
                $despedida = str_replace('$link_card_payment', $link_card_payment, $despedida);
                $despedida = str_replace('$bank_transfer_details', $bank_transfer_details, $despedida);

                foreach ($admins as $admin) {
              $mail_enviado_admin =   enviarCorreo($admin['correo'], $asunto, $titulo, $cuerpo, $despedida);
                            }
            }

                
            }
            
          
            
           if ($mail_enviado_customer && $mail_enviado_admin) {
                    echo json_encode(['status' => 'success', 'message' => 'Correo al cliente y al administrador enviados correctamente.','id_generado' => $id_generado, 'id_pago' => $id_pago]);
                } else {
                    // Si el correo al cliente falla
                    if (!$mail_enviado_customer) {
                        echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al cliente.',]);
                    }
                    // Si el correo al administrador falla
                    if (!$mail_enviado_admin) {
                        echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al administrador.']);
                    }
                }

        // } else {
        //     // Si la forma de pago no es 1, solo respondemos con éxito
        //     echo json_encode(['status' => 'success', 'message' => 'Pago agregado correctamente.', 'id_generado' => $id_generado, 'id_pago' => $id_pago]);
        // }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al agregar el pago: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al preparar la consulta: ' . $conn->error]);
}
}

// Cerrar la conexión
$conn->close();

?>
