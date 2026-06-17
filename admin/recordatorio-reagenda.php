<?php 
include 'conn.php';
 date_default_timezone_set('Mexico/General');

// Cargar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';

// Función para ejecutar consultas y obtener los resultados 
function obtenerDatos($conn, $tabla) {
    if($tabla == "calendario"){
              $sql = "SELECT * FROM $tabla WHERE fecha != 0000-00-00 AND estatus != 3";
    $result = $conn->query($sql);
    
    // Verificar si la consulta se ejecutó correctamente
    if (!$result) {
        die("Error en la consulta: " . $conn->error);
    }

    return ($result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }else{
        $sql = "SELECT * FROM $tabla";
    $result = $conn->query($sql);
    
    // Verificar si la consulta se ejecutó correctamente
    if (!$result) {
        die("Error en la consulta: " . $conn->error);
    }

    return ($result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
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


function nuevoCalcularDiferencia($fecha_evento, $hora_evento) {
    $fecha_hoy = new DateTime();
    $evento = new DateTime("$fecha_evento $hora_evento");

    // Calcular la diferencia, puede ser positiva o negativa
    $diferencia = $fecha_hoy->diff($evento);

    // Determinar si la diferencia es negativa (evento ya pasó)
    $esNegativo = ($fecha_hoy > $evento);

    $resultado = [
        'dias' => $diferencia->days,
        'horas' => $diferencia->h,
        'minutos' => $diferencia->i,
        'segundos' => $diferencia->s,
        'negativo' => $esNegativo, // Indica si la diferencia es negativa
    ];

    // Aplicar signo negativo si el evento ya pasó
    if ($esNegativo) {
        $resultado['dias'] = -$resultado['dias'];
        $resultado['horas'] = -$resultado['horas'];
        $resultado['minutos'] = -$resultado['minutos'];
        $resultado['segundos'] = -$resultado['segundos'];
    }

    return $resultado;
}

function calcularDiferencia($fecha_evento, $hora_evento) {
    // Obtener la fecha y hora actual
    $fecha_hoy = new DateTime();  // Fecha actual
    $evento = new DateTime("$fecha_evento $hora_evento"); // Fecha del evento

    // Calcular la diferencia entre las fechas
    $diferencia = $fecha_hoy->diff($evento);

    return $diferencia;
}


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
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo]);
    }
}

// Recorrer el calendario
foreach ($calendario as $i => $evento) {
    echo "evento id ". $evento['id'] . "<br>";
    $fecha_evento = $evento['fecha'];
    $hora_evento = $evento['hora'];
    $hora_cte = $evento['hora_cliente'];
    $idvendedor = $evento['idusu'];
    $idcliente = $evento['idclie'];
    $estatusEvento =  $evento['estatus'];
    $idEvento = $evento['id'];

    // Obtener la hora actual
    $hora_actual = new DateTime();
    
    // Buscar vendedor y cliente usando los índices
    $vendedor = $usuarios_indexados[$idvendedor] ?? null;
    $cliente = $contact_form_indexados[$idcliente] ?? null;
    

    // Verificar si el vendedor y el cliente existen
    if (!$vendedor || !$cliente) {
        continue; // Saltar al siguiente evento si no existe vendedor o cliente
    }
  
    // Calcular la diferencia de tiempo
    $diferencia = calcularDiferencia($fecha_evento, $hora_evento);
        $nuevoDiferencia = nuevoCalcularDiferencia($fecha_evento, $hora_evento);

        //  $indexCorreo = ($cliente['country_code'] == '+52') ? 6 : 7; //6 español - 7 ingles
              $indexCorreo = 7;

         $asunto = ($indexCorreo == 6)? "Reagendar cita" :"Reschedule appointment";

    
                $customer = $cliente['names'];  // Nombre del cliente
                $meet = "<a href='" . $vendedor['enlace_meet'] . "'>" . $vendedor['enlace_meet'] . "</a>";
                $seller = $vendedor['nombre'];
                $date = $fecha_evento . " " .  $hora_cte;
                $formattedDate_cte = null;
                $dateObject_cte = new DateTime($date);
                
                $date = $fecha_evento . " " .  $hora_evento;
                $dateObject = new DateTime($date);
                $link_reschedule = "https://citas.efegepho.com.mx/formulario.php?id=" . $idEvento;
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
                   
 echo "hora actual " . $hora_actual->format('H:i') . "<br>";
 echo "estatus " . $estatusEvento . "<br>";
    echo " <br>".$hora_actual->format('H:i');
echo " <br>".$nuevoDiferencia['dias'];
echo " <br>".$nuevoDiferencia['horas'];
echo " <br>".$nuevoDiferencia['minutos'];    
        // Verificar si la hora actual es 2 horas después de la hora del evento
    $hora_evento_plus_2 = (new DateTime($hora_evento))->modify('+2 hours')->format('H:i');
        
  if ($diferencia->days == 0 && $diferencia->invert == 1) {
        
        // echo "esperando a que sean las...: " . $hora_evento_plus_2 . "<br>";
        
        if ($hora_actual->format('H:i') >= $hora_evento_plus_2 && $estatusEvento == 0 ) {
            
            // echo "cambiando estatus a expirado ya que no se marco como atendido " . $hora_evento_plus_2 . "<br>";

            //cambiar estatus a expirado
            $queryy = "UPDATE calendario SET estatus = 2 WHERE id = ?";
    
            // Preparar la consulta
            $stmtt = mysqli_prepare($conn, $queryy);
            
            // Verificar si la preparación fue exitosa
            if ($stmtt === false) {
                die("Error al preparar la consulta: " . mysqli_error($conn));
            }
            
            // Enlazar el parámetro de la consulta
            mysqli_stmt_bind_param($stmtt, "i", $idEvento);
            
            // Ejecutar la consulta
            mysqli_stmt_execute($stmtt);
            
            echo "mandar correo <br>";
                    $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                    $titulo = str_replace('$customer', $customer, $titulo);
                    $titulo = str_replace('$meet', $meet, $titulo);
                    $titulo = str_replace('$date', $formattedDate, $titulo);
                    $titulo = str_replace('$seller', $seller, $titulo);
                    $titulo = str_replace('$link_reschedule', $link_reschedule, $titulo);
                    $asunto = $titulo;
                    $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                    
                    $cuerpo = str_replace('$customer', $customer, $cuerpo);
                    $cuerpo = str_replace('$meet', $meet, $cuerpo);
                    $cuerpo = str_replace('$date', $formattedDate, $cuerpo);
                    $cuerpo = str_replace('$seller', $seller, $cuerpo);
                    $cuerpo = str_replace('$link_reschedule', $link_reschedule, $cuerpo);

                    
                    $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                    
                    $despedida = str_replace('$customer', $customer, $despedida);
                    $despedida = str_replace('$meet', $meet, $despedida);
                    $despedida = str_replace('$date', $formattedDate, $despedida);
                    $despedida = str_replace('$seller', $seller, $despedida);
                    $despedida = str_replace('$link_reschedule', $link_reschedule, $despedida);
                    enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida);


        }
    
    // Caso cuando la diferencia de días es menor a 0 y la hora actual es la misma que la hora del evento
    //   if ($diferencia['dias'] < 0 && $hora_actual->format('H:i') == "10:00" && ($estatusEvento == 2 || $estatusEvento == "2" || $estatusEvento == 3 || $estatusEvento == "3")) {
    //                 echo "mandar correo <br>";

    //     // Preparar el asunto del correo
    //                 $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
    //                 $titulo = str_replace('$customer', $customer, $titulo);
    //                 $titulo = str_replace('$meet', $meet, $titulo);
    //                 $titulo = str_replace('$date', $formattedDate, $titulo);
    //                 $titulo = str_replace('$seller', $seller, $titulo);
    //                 $titulo = str_replace('$link_reschedule', $link_reschedule, $titulo);

    //                 $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                    
    //                 $cuerpo = str_replace('$customer', $customer, $cuerpo);
    //                 $cuerpo = str_replace('$meet', $meet, $cuerpo);
    //                 $cuerpo = str_replace('$date', $formattedDate, $cuerpo);
    //                 $cuerpo = str_replace('$seller', $seller, $cuerpo);
    //                 $cuerpo = str_replace('$link_reschedule', $link_reschedule, $cuerpo);

                    
    //                 $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                    
    //                 $despedida = str_replace('$customer', $customer, $despedida);
    //                 $despedida = str_replace('$meet', $meet, $despedida);
    //                 $despedida = str_replace('$date', $formattedDate, $despedida);
    //                 $despedida = str_replace('$seller', $seller, $despedida);
    //                 $despedida = str_replace('$link_reschedule', $link_reschedule, $despedida);
    //                 enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida);
    // }
}
        if ($nuevoDiferencia['dias'] < 0 && $hora_actual->format('H:i') == "10:00" && ($estatusEvento == 2 || $estatusEvento == "2" || $estatusEvento == 3 || $estatusEvento == "3")) {
             echo "mandar correo <br>";
                    $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                    $titulo = str_replace('$customer', $customer, $titulo);
                    $titulo = str_replace('$meet', $meet, $titulo);
                    $titulo = str_replace('$date', $formattedDate, $titulo);
                    $titulo = str_replace('$seller', $seller, $titulo);
                    $titulo = str_replace('$link_reschedule', $link_reschedule, $titulo);
                    $asunto = $titulo;
                    $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                    
                    $cuerpo = str_replace('$customer', $customer, $cuerpo);
                    $cuerpo = str_replace('$meet', $meet, $cuerpo);
                    $cuerpo = str_replace('$date', $formattedDate, $cuerpo);
                    $cuerpo = str_replace('$seller', $seller, $cuerpo);
                    $cuerpo = str_replace('$link_reschedule', $link_reschedule, $cuerpo);

                    
                    $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                    
                    $despedida = str_replace('$customer', $customer, $despedida);
                    $despedida = str_replace('$meet', $meet, $despedida);
                    $despedida = str_replace('$date', $formattedDate, $despedida);
                    $despedida = str_replace('$seller', $seller, $despedida);
                    $despedida = str_replace('$link_reschedule', $link_reschedule, $despedida);
                    enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida);
        }

    


   

}
?>
