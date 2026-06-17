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
    if($tabla == "calendario"){
        $sql = "SELECT * FROM $tabla WHERE estatus = 0 AND fecha != 0000-00-00";
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

// Cerrar la conexión
$conn->close();

// Función para calcular la diferencia de tiempo
function calcularDiferencia($fecha_evento, $hora_evento) {
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

// Función para enviar el correo
function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida, $usuario) { 
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
   $mail_enviado = false ;// mail($correo_destino, $asunto, $mensaje, $headers);
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
        $mail_enviado = $mail->send();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo]);
    }

    
}

// Recorrer el calendario
foreach ($calendario as $i => $evento) {
    $fecha_evento = $evento['fecha'];
    $hora_evento = $evento['hora'];
    $hora_cte = $evento['hora_cliente'];
    $idvendedor = $evento['idusu'];
    $idcliente = $evento['idclie'];
    $estatusCita = $evento['estatus'];
    

    
    // Buscar vendedor y cliente usando los índices
    $vendedor = $usuarios_indexados[$idvendedor] ?? null;
    $cliente = $contact_form_indexados[$idcliente] ?? null;

    // Verificar si el vendedor y el cliente existen
    if (!$vendedor) {
        echo "Error: Vendedor no encontrado para el evento en la fila $i.<br>";
        continue; // Saltar al siguiente evento
    }

    if (!$cliente) {
        echo "Error: Cliente no encontrado para el evento en la fila $i.<br>";
        continue; // Saltar al siguiente evento
    }

    // Calcular la diferencia de tiempo
    $diferencia = calcularDiferencia($fecha_evento, $hora_evento);

    // Crear objeto DateTime para el evento
    $evento_datetime = new DateTime("$fecha_evento $hora_evento");

    // Restar 5 minutos al evento (para el caso del último correo)
    $cinco_minutos_antes = $evento_datetime->modify('-5 minutes');

    // Obtener la hora actual
    $hora_actual = new DateTime();
    
  
        echo "<br>la fecha del evento con id: " . $evento['id'] . " es el dia " . $evento['fecha'] . " a las " . $evento['hora'] . "faltan". $diferencia['dias']. " con " . "". $diferencia['horas']. " con " . "". $diferencia['minutos'] .  "<hr>";
        echo " <br>".$hora_actual->format('H:i');
        echo " <br>".$diferencia['dias'];
        echo " <br>".$diferencia['horas'];
        echo " <br>".$diferencia['minutos'];

    
    if (
        ($diferencia['dias'] == 0 && $hora_actual->format('H:i') == '10:00')||
        ($diferencia['dias'] == 2 && $hora_actual->format('H:i') == '10:00')|| 
        ($diferencia['dias'] == 5 && $hora_actual->format('H:i') == '10:00')||
        ($diferencia['dias'] == 0 && $diferencia['horas'] == 0 && $diferencia['minutos'] == 5)
        
    ) {
        echo "mandando correos";
        // Preparar el asunto del correo

        
        // $indexCorreo = ($cliente['country_code'] == '+52') ? 2 : 5; //0 español - 3 ingles
        $indexCorreo = 5;
        
            //   $asunto = ($indexCorreo == 2) ? 
            // ($diferencia['dias'] == 0 ? "Recordatorio: La cita es hoy" : "Recordatorio: El evento es en {$diferencia['dias']} días") : 
            // ($diferencia['dias'] == 0 ? "Reminder: Event coming today" : "Reminder: Event coming in {$diferencia['dias']} days");
        
        //variables
                $customer = $cliente['names'];  // Nombre del cliente
                $meet = "<a href='" . $vendedor['enlace_meet'] . "'>" . $vendedor['enlace_meet'] . "</a>";
                $seller = $vendedor['nombre'];
                $date = $fecha_evento . " " .  $hora_cte;
                $formattedDate_cte = null;
                $dateObject_cte = new DateTime($date);
                
                $date = $fecha_evento . " " .  $hora_evento;
                $formattedDate = null;
                $dateObject = new DateTime($date);
              // Arreglos de traducción para días y meses
                $dias_es = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
                $meses_es = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                
                $dias_en = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $meses_en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                
                if ($indexCorreo == 2) {
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
                    
                
                
       
        
        
        for ($i = 0; $i < 3; $i++) {

            if ($i == 0) {
                                   

                // Enviar correo al cliente
                $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                $titulo = str_replace('$customer', $customer, $titulo);
                $titulo = str_replace('$meet', $meet, $titulo);
                $titulo = str_replace('$date', $formattedDate_cte, $titulo);
                $titulo = str_replace('$seller', $seller, $titulo);
                
                $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                
                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                $cuerpo = str_replace('$meet', $meet, $cuerpo);
                $cuerpo = str_replace('$date', $formattedDate_cte, $cuerpo);
                $cuerpo = str_replace('$seller', $seller, $cuerpo);

                
                $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                
                $despedida = str_replace('$customer', $customer, $despedida);
                $despedida = str_replace('$meet', $meet, $despedida);
                $despedida = str_replace('$date', $formattedDate_cte, $despedida);
                $despedida = str_replace('$seller', $seller, $despedida);

               if($estatusCita == 0 || $estatusCita == "0"){
                    echo "mandando correos al cliente";
                enviarCorreo($cliente['email_address'], $titulo, $titulo, $cuerpo, $despedida, "cliente");
               }
            }

            if ($i == 1) {
                 
                // Enviar correo al administrador
                $titulo = $txt_correo[$indexCorreo]['txttituloadmin'];
               
                $titulo = str_replace('$customer', $customer, $titulo);
                $titulo = str_replace('$meet', $meet, $titulo);
                $titulo = str_replace('$date', $formattedDate, $titulo);
                $titulo = str_replace('$seller', $seller, $titulo);
                $asunto = $titulo;
                $cuerpo = $txt_correo[$indexCorreo]['txtadmin'];
                
                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                $cuerpo = str_replace('$meet', $meet, $cuerpo);
                $cuerpo = str_replace('$date', $formattedDate, $cuerpo);
                $cuerpo = str_replace('$seller', $seller, $cuerpo);
                
                
               
                $despedida = $txt_correo[$indexCorreo]['txtdespadmin'];
                
                $despedida = str_replace('$customer', $customer, $despedida);
                $despedida = str_replace('$meet', $meet, $despedida);
                $despedida = str_replace('$date', $formattedDate, $despedida);
                $despedida = str_replace('$seller', $seller, $despedida);

                foreach ($admins as $admin) {
                                   if($estatusCita == 0 || $estatusCita == "0"){
 echo "mandando correos administrador";
                               enviarCorreo($admin['correo'], $asunto, $titulo, $cuerpo, $despedida, "admin");
                                   }
                            }
            }

            if ($i == 2) {
                // Enviar correo al vendedor
                $titulo = $txt_correo[$indexCorreo]['txttitulovend'];
                $titulo = str_replace('$customer', $customer, $titulo);
                $titulo = str_replace('$meet', $meet, $titulo);
                $titulo = str_replace('$date', $formattedDate, $titulo);
                $titulo = str_replace('$seller', $seller, $titulo);
                $asunto = $titulo;

                $cuerpo = $txt_correo[$indexCorreo]['txtvend'];
                 
                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                $cuerpo = str_replace('$meet', $meet, $cuerpo);
                $cuerpo = str_replace('$date', $formattedDate, $cuerpo);
                $cuerpo = str_replace('$seller', $seller, $cuerpo);

                $despedida = $txt_correo[$indexCorreo]['txtdespvend'];
                
                $despedida = str_replace('$customer', $customer, $despedida);
                $despedida = str_replace('$meet', $meet, $despedida);
                $despedida = str_replace('$date', $formattedDate, $despedida);
                $despedida = str_replace('$seller', $seller, $despedida);

             if($estatusCita == 0 || $estatusCita == "0"){
                  echo "mandando correos vendedor";
                            enviarCorreo($vendedor['correo'], $asunto, $titulo, $cuerpo, $despedida, "vendedor");
             }
            }
        }
    }

}
?>
