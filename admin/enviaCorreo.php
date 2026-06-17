<?php
include "conn.php";

// Cargar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';


function obtenerDatos($conn, $tabla) {
    if ($tabla != "contact_form")
        $sql = "SELECT * FROM $tabla";
    else
        $sql = "SELECT cf.*, c.hora_cliente, c.fecha_cliente FROM `contact_form` cf INNER JOIN calendario c ON cf.id=c.idclie";
    $result = $conn->query($sql);
    
    // Verificar si la consulta se ejecut�� correctamente
    if (!$result) {
        die("Error en la consulta: " . $conn->error);
    }

    return ($result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
}


function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida, $usuario) {
    $mail = new PHPMailer(true);
    $bccInfo = []; // Array para almacenar información del BCC
      
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
    //  $mail_enviado = mail($correo_destino, $asunto, $mensaje, $headers);
    $correoRemitente = "info@efegepho.com.mx";
    $nombreRemitente = "InfoEfegepho";
    try {
        // Servidor SMTP
        $mail->isSMTP();  // Usar SMTP
       
        $mail->SMTPAuth = true; // Usar autenticaci��n SMTP
        $mail->SMTPSecure = 'starttls'; // Usar encriptaci��n TLS
        $mail->Port = 587; // Puerto del servidor SMTP (587 para STARTTLS)
        
        $mail->Host = 'smtp.gmail.com';
        $mail->Username = $correoRemitente; // Tu correo de Gmail
        $mail->Password = 'glhewzgjzdnsbuvj'; 

        // Receptor del correo
        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->addAddress($correo_destino, 'Cliente'); // Reemplaza por el correo del destinatario
        
        
        if ($usuario === 'admin') {
            $mail->addBCC("juanpablo.ggomez@gmail.com");
            $mail->addBCC("fernandagomezca@gmail.com");
            $bccInfo['added'] = true;
            $bccInfo['emails'] = ["juanpablo.ggomez@gmail.com", "fernandagomezca@gmail.com"];
            $bccInfo['usuario'] = $usuario;
        } else {
            $bccInfo['added'] = false;
            $bccInfo['reason'] = "El usuario no es 'admin', es: '$usuario'. BCC solo se añade para usuarios admin.";
        }
        
        // Asunto y contenido del correo
        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje; // Convierte saltos de l��nea en <br> para formato HTML
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
        
        // Log de éxito - información del BCC (se guarda en archivo)
        $logData = [
            'status' => 'success',
            'correo_destino' => $correo_destino,
            'usuario_tipo' => $usuario,
            'bcc_info' => $bccInfo,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($bccInfo['added']) && $bccInfo['added'] === true) {
            $logData['bcc_status'] = 'BCC ENVIADO CORRECTAMENTE';
            $logData['bcc_emails'] = $bccInfo['emails'];
            $logData['bcc_message'] = "La copia del correo se envió exitosamente a: " . implode(", ", $bccInfo['emails']);
        } else {
            $logData['bcc_status'] = 'BCC NO ENVIADO';
            $logData['bcc_reason'] = $bccInfo['reason'] ?? 'No se configuró BCC para este tipo de usuario';
        }
        
        // Guardar log en archivo (revisar en admin/logs/bcc_correos.log)
        $logFile = __DIR__ . '/logs/bcc_correos.log';
        if (!file_exists(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
        $logEntry = "📧 CORREO ENVIADO - " . date('Y-m-d H:i:s') . "\n" . json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n" . str_repeat("-", 50) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
    } catch (Exception $e) {
        // Log de error - información del BCC
        $errorLogData = [
            'status' => 'error',
            'correo_destino' => $correo_destino,
            'usuario_tipo' => $usuario,
            'bcc_info' => $bccInfo,
            'error_message' => $e->getMessage(),
            'mailer_error' => $mail->ErrorInfo,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($bccInfo['added']) && $bccInfo['added'] === true) {
            $errorLogData['bcc_status'] = 'BCC FALLÓ - El correo no se pudo enviar';
            $errorLogData['bcc_emails'] = $bccInfo['emails'];
            $errorLogData['bcc_message'] = "La copia del correo a " . implode(", ", $bccInfo['emails']) . " FALLÓ porque el correo principal falló";
        } else {
            $errorLogData['bcc_status'] = 'BCC NO CONFIGURADO';
            $errorLogData['bcc_reason'] = $bccInfo['reason'] ?? 'No se configuró BCC para este tipo de usuario';
        }
        
        // Guardar error en archivo log
        $logFile = __DIR__ . '/logs/bcc_correos.log';
        if (!file_exists(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0755, true);
        }
        $logEntry = "❌ ERROR CORREO - " . date('Y-m-d H:i:s') . "\n" . json_encode($errorLogData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n" . str_repeat("-", 50) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo]);
    }

    
}

// Obtener datos de las tablas
$usuarios = obtenerDatos($conn, 'usuarios');
$contact_form = obtenerDatos($conn, 'contact_form');
$txt_correo = obtenerDatos($conn, 'txtCorreo');

$admins = []; // Inicializamos un array vac��o para almacenar a todos los administradores

foreach ($usuarios as $usuario) {
    if ($usuario['tipoUsu'] == 0) {  // Verifica si es administrador
        $admins[] = $usuario; // A�0�9ade el administrador al array de administradores
    }
}
// Indexar usuarios y contactos por ID para b��squedas r��pidas
$usuarios_indexados = [];
foreach ($usuarios as $usuario) {
    $usuarios_indexados[$usuario['id']] = $usuario;
}

$contact_form_indexados = [];
foreach ($contact_form as $contact) {
    $contact_form_indexados[$contact['id']] = $contact;
}


$cliente = $contact_form_indexados[$_POST['cust']] ?? null;
$vendedor = $usuarios_indexados[$_POST['vendedor']] ?? null;


$indexCorreo = 3; // Forzar correo en inglés


$asunto = ($indexCorreo == 0)? "Tu cita se ha agendado con exito" :"Your appointment has been successfully scheduled";
        
        //variables
                $customer = $cliente['names'];  // Nombre del cliente
                $meet = "<a href='" . $vendedor['enlace_meet'] . "'>" . $vendedor['enlace_meet'] . "</a>";
                $seller = $vendedor['nombre'];

      // Formatear fechas basadas en los campos guardados en BD
      // PRIORIDAD: usar fecha_cliente/hora_cliente directamente (ya están en la zona correcta del cliente)
      // - Vendedor/admin: siempre CDMX
      $tzSeller = new DateTimeZone('America/Mexico_City');

      // Obtener fecha/hora del vendedor (servidor/CDMX)
      $sellerDateTimeStr = trim(($cliente['date_appointment'] ?? '') . ' ' . ($cliente['time_appointment'] ?? ''));
      
      try {
        $dateObject = new DateTime($sellerDateTimeStr, $tzSeller);
      } catch (Exception $e) {
        // Parsing fallback: crear en UTC y forzar la zona vendedor
        $dateObject = new DateTime($sellerDateTimeStr);
        $dateObject->setTimezone($tzSeller);
      }

      // Obtener fecha/hora del CLIENTE: USAR DIRECTAMENTE fecha_cliente + hora_cliente
      // Estos campos ya contienen la hora correcta en la zona horaria del cliente
      $clientDateStr = trim($cliente['fecha_cliente'] ?? '');
      $clientTimeStr = trim($cliente['hora_cliente'] ?? '');
      
      if (!empty($clientDateStr) && !empty($clientTimeStr)) {
        // Tenemos fecha_cliente y hora_cliente: usarlos DIRECTAMENTE sin conversiones
        $clientDateTimeStr = $clientDateStr . ' ' . $clientTimeStr;
        try {
          // No especificamos timezone porque estos campos ya representan la hora local del cliente
          $dateObject_cte = new DateTime($clientDateTimeStr);
        } catch (Exception $e) {
          // Fallback: usar la misma hora del vendedor si hay error
          $dateObject_cte = clone $dateObject;
        }
      } else {
        // Fallback: si no hay fecha_cliente/hora_cliente, usar los campos del vendedor
        $dateObject_cte = clone $dateObject;
      }

      $formattedDate_cte = null;
      $formattedDate = null;
            
                            // Arreglos de traducci��n para d��as y meses
                $dias_es = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
                $meses_es = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                
                $dias_en = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $meses_en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                
                if ($indexCorreo == 0) {
                    // Formateo en espa�0�9ol
                    $dia = $dias_es[$dateObject->format('w')]; // D��a de la semana en espa�0�9ol
                    $mes = $meses_es[$dateObject->format('n') - 1]; // Mes en espa�0�9ol
                    $formattedDate_cte = $dia . ' ' . $dateObject_cte->format('d') . ' de ' . $mes . ' del ' . $dateObject_cte->format('Y') . ' a las ' . $dateObject_cte->format('h:i A');
                    $formattedDate = $dia . ' ' . $dateObject->format('d') . ' de ' . $mes . ' del ' . $dateObject->format('Y') . ' a las ' . $dateObject->format('h:i A');
                } else {
                    // Formateo en ingl��s
                    $dia = $dias_en[$dateObject->format('w')]; // D��a de la semana en ingl��s
                    $mes = $meses_en[$dateObject->format('n') - 1]; // Mes en ingl��s
                    $formattedDate_cte = $dateObject_cte->format('d') . ' ' . $mes . ' ' . $dateObject_cte->format('Y') . ' at ' . $dateObject_cte->format('h:i A');
                    $formattedDate = $dateObject->format('d') . ' ' . $mes . ' ' . $dateObject->format('Y') . ' at ' . $dateObject->format('h:i A');
                }
                   

                    
                    
                  for ($i = 0; $i < 3; $i++) {
                        if ($i == 0) {
                            // Enviar correo al cliente
                            $titulo = $txt_correo[$indexCorreo]['txttituloclie'];

                            // Añadir etiqueta de hora local con ciudad si existe
                            $cityLabel = '';
                            if (!empty(trim($cliente['city'] ?? ''))) {
                              $cityNameEscaped = htmlspecialchars(trim($cliente['city']), ENT_QUOTES, 'UTF-8');
                              $cityLabel = ($indexCorreo == 0) ? ' hora local (' . $cityNameEscaped . ')' : ' local time (' . $cityNameEscaped . ')';
                            }
                            // También mostrar la zona horaria IANA confirmada si está disponible
                            $tzLabel = '';
                            if (!empty($cliente['timezone_name'])) {
                              $tzEscaped = htmlspecialchars(trim($cliente['timezone_name']), ENT_QUOTES, 'UTF-8');
                              $tzLabel = ($indexCorreo == 0) ? ' (' . $tzEscaped . ')' : ' (' . $tzEscaped . ')';
                            }
                            $formattedDate_cte_with_city = $formattedDate_cte . $cityLabel . " US Central Time";

                            $titulo = str_replace('$customer', $customer, $titulo);
                            $titulo = str_replace('$meet', $meet, $titulo);
                            $titulo = str_replace('$date', $formattedDate_cte_with_city, $titulo);
                            $titulo = str_replace('$seller', $seller, $titulo);
                
                $cuerpo = $txt_correo[$indexCorreo]['txtcli'];

                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                $cuerpo = str_replace('$meet', $meet, $cuerpo);
                $cuerpo = str_replace('$date', $formattedDate_cte_with_city, $cuerpo);
                $cuerpo = str_replace('$seller', $seller, $cuerpo);

                
                $despedida = $txt_correo[$indexCorreo]['txtdespclie'];

                $despedida = str_replace('$customer', $customer, $despedida);
                $despedida = str_replace('$meet', $meet, $despedida);
                $despedida = str_replace('$date', $formattedDate_cte_with_city, $despedida);
                $despedida = str_replace('$seller', $seller, $despedida);

               
                enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida, "cliente");
                        }

                        if ($i == 1) {
                            // Enviar correo a todos los administradores
                             $titulo = $txt_correo[$indexCorreo]['txttituloadmin'];
                  
                                $titulo = str_replace('$customer', $customer, $titulo);
                                $titulo = str_replace('$meet', $meet, $titulo);
                                $titulo = str_replace('$date', $formattedDate, $titulo);
                                $titulo = str_replace('$seller', $seller, $titulo);
                                
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
                                // Validar que el correo sea válido (evitar guiones u otros caracteres inválidos)
                                $correoAdmin = $admin['correo'];
                                if (filter_var($correoAdmin, FILTER_VALIDATE_EMAIL) && strpos($correoAdmin, '-') === false) {
                                    enviarCorreo($correoAdmin, $asunto, $titulo, $cuerpo, $despedida, "admin");
                                } else {
                                    // Log de correo inválido
                                    $logFile = __DIR__ . '/logs/bcc_correos.log';
                                    if (!file_exists(__DIR__ . '/logs')) {
                                        mkdir(__DIR__ . '/logs', 0755, true);
                                    }
                                    $logEntry = "⚠️ CORREO ADMIN OMITIDO - " . date('Y-m-d H:i:s') . "\n";
                                    $logEntry .= "Correo inválido o con guiones: '$correoAdmin'\n";
                                    $logEntry .= "Admin: " . ($admin['nombre'] ?? 'Sin nombre') . "\n";
                                    $logEntry .= str_repeat("-", 50) . "\n";
                                    file_put_contents($logFile, $logEntry, FILE_APPEND);
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
            
            
                            enviarCorreo($vendedor['correo'], $asunto, $titulo, $cuerpo, $despedida, "vendedor");
                        }
                    }
                    
                  if($indexCorreo == 0){
                          echo json_encode([
                            'status' => 'success',
                            'message' => 'Tu cita se ha agendado con exito.',
                            'data' => [

                                'vendedor' => $vendedor['nombre'], 
                                'fecha' =>  $cliente['date_appointment'],
                                'hora' => $cliente['hora_cliente'],
                                'lastMessage' =>"Las indicaciones se enviaron via correo electronico"
                            ]
                        ]);
                    }
                    if($indexCorreo == 3){
                          echo json_encode([
                            'status' => 'success',
                            'message' => 'Your appointment has been successfully scheduled.',
                            'data' => [

                                'vendedor' => $vendedor['nombre'], 
                                 'fecha' =>  $cliente['date_appointment'],
                                'hora' => $cliente['hora_cliente'],
                                  'lastMessage' =>"The instructions were sent via email."

                            ]
                        ]);
                    }
                    
    $conn->close();
// }
?>