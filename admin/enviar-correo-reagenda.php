<?php
include 'conn.php';

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
$calendario = obtenerDatos($conn, 'calendario');
$txt_correo = obtenerDatos($conn, 'txtCorreo');


// Indexar usuarios y contactos por ID para búsquedas rápidas
$usuarios_indexados = [];
foreach ($usuarios as $usuario) {
    $usuarios_indexados[$usuario['id']] = $usuario;
}

$contact_form_indexados = [];
foreach ($contact_form as $contact) {
    $contact_form_indexados[$contact['id']] = $contact;
}

// Cerrar la conexión
$conn->close();

// Función para enviar el correo
function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida) { 
    $mail = new PHPMailer(true);
    // Contenido del correo HTML
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
      <img style='width: 140px; margin: 0 auto;'  alt='efegephologo' src='https://citas.efegepho.com.mx/admin/assets/img/logofgep.png'/>
    </div>
  </div>
</body>
</html>
";

    // Encabezados para enviar el correo como HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";

    // Enviar correo
   $mailSent = false; //mail($correo_destino, $asunto, $mensaje, $headers);
   
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
        $mailSent = $mail->send();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo]);
    }
  

    // Responder con éxito si el correo se envió correctamente
    if ($mailSent) {
        echo json_encode(['status' => 'success', 'message' => 'Correo enviado exitosamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Hubo un problema al enviar el correo.']);
    }
}


// Verifica que los datos fueron enviados
if (isset($_POST['id']) && isset($_POST['idclie'])) {
    // Obtén los valores de los parámetros enviados por AJAX
    $id = $_POST['id'];
    $idcliente = $_POST['idclie'];
   
   if(isset($_POST['idusu'])){
        $idusu = $_POST['idusu'];

   }
    
     $registro_calendario = array_filter($calendario, function($evento) use ($id) {
        return $evento['id'] == $id;
    });
    
    $registro_calendario = reset($registro_calendario); // Si hay resultados, obtiene el primer registro
        if (empty($registro_calendario)) {
    echo json_encode(['status' => 'error', 'message' => 'No se encontró el evento en el calendario.']);
    exit;
}

$idvendedor = $registro_calendario['idusu'];



$vendedor = $usuarios_indexados[$idvendedor] ?? null;
$cliente = $contact_form_indexados[$idcliente] ?? null;


$fecha_evento = $registro_calendario['fecha'];

$hora_evento = $registro_calendario['hora'];
$hora_cte = $registro_calendario['hora_cliente'];


        

            // $indexCorreo = ($cliente['country_code'] == '+52') ? 6 : 7; //6 español - 7 ingles
           $indexCorreo = 7;
            $asunto = ($indexCorreo == 6)? "Reagendar cita" :"Reschedule appointment";
            
            
            
            
            
               
                $customer = $cliente['names'];  // Nombre del cliente
                $meet = "<a href='" . $vendedor['enlace_meet'] . "'>" . $vendedor['enlace_meet'] . "</a>";
                $seller = $vendedor['nombre'];
                if (isset($_POST['idusu']) && (int)$_POST['idusu'] !== 0) {
                $link_reschedule = "https://citas.efegepho.com.mx/formulario.php?id=" . $id . "&idusu=" . $idusu;

               }else{
                     $link_reschedule = "https://citas.efegepho.com.mx/formulario.php?id=" . $id;
               }
                $date = $fecha_evento . " " .  $hora_cte;
                $formattedDate_cte = null;
                $dateObject_cte = new DateTime($date);
              
                $date = $fecha_evento . " " .  $hora_evento;
                $dateObject = new DateTime($date);
                $formattedDate = null;
                
                // Arreglos de traducción para días y meses
                $dias_es = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
                $meses_es = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                
                $dias_en = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $meses_en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                
                if ($indexCorreo == 6) {
                    // Formateo en español
                    $dia = $dias_es[$dateObject->format('w')]; // Día de la semana en español
                    $mes = $meses_es[$dateObject->format('n') - 1]; // Mes en español
                    $formattedDate_cte = $dia . ' ' . $dateObject_cte->format('d') . ' de ' . $mes . ' del ' . $dateObject_cte->format('Y') . ' a las ' . $dateObject_cte->format('h:i A');
                    $formattedDate = $dia . ' ' . $dateObject->format('d') . ' de ' . $mes . ' del ' . $dateObject->format('Y') . ' a las ' . $dateObject->format('h:i A');
                } else {
                    // Formateo en inglés
                    $dia = $dias_en[$dateObject->format('w')]; // Día de la semana en inglés
                    $mes = $meses_en[$dateObject->format('n') - 1]; // Mes en inglés
                    $formattedDate_cte = $dateObject_cte->format('d') . ' ' . $mes . ' ' . $dateObject_cte->format('Y') . ' at ' . $dateObject_cte->format('h:i A');
                    $formattedDate = $dateObject->format('d') . ' ' . $mes . ' ' . $dateObject->format('Y') . ' at ' . $dateObject->format('h:i A');
                }
            
                
            
            
                     $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                
                    $titulo = str_replace('$customer', $customer, $titulo);
                    $titulo = str_replace('$meet', $meet, $titulo);
                    $titulo = str_replace('$date', $formattedDate_cte, $titulo);
                    $titulo = str_replace('$seller', $seller, $titulo);
                    $titulo = str_replace('$link_reschedule', $link_reschedule, $titulo);
                    
                    $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                    
                    $cuerpo = str_replace('$customer', $customer, $cuerpo);
                    $cuerpo = str_replace('$meet', $meet, $cuerpo);
                    $cuerpo = str_replace('$date', $formattedDate_cte, $cuerpo);
                    $cuerpo = str_replace('$seller', $seller, $cuerpo);
                    $cuerpo = str_replace('$link_reschedule', $link_reschedule, $cuerpo);
    
                    
                    $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                    
                    $despedida = str_replace('$customer', $customer, $despedida);
                    $despedida = str_replace('$meet', $meet, $despedida);
                    $despedida = str_replace('$date', $formattedDate_cte, $despedida);
                    $despedida = str_replace('$seller', $seller, $despedida);
                    $despedidda = str_replace('$link_reschedule', $link_reschedule, $despedida);
    
                   
                    enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida);
            
         
   

  
} else {
    // Si los datos no fueron enviados correctamente, responder con un error
    echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros para procesar la solicitud.']);
}
?>
