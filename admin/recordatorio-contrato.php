<?php
include 'conn.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';

// Función para ejecutar consultas y obtener los resultados
function obtenerDatos($conn, $tabla) {
    if($tabla == "contact_form"){
        $sql = "SELECT * FROM $tabla WHERE cliente = 1";
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

// Obtener datos de las tablas
$usuarios = obtenerDatos($conn, 'usuarios');
$contact_form = obtenerDatos($conn, 'contact_form');
$txt_correo = obtenerDatos($conn, 'txtCorreo');
$contratos = obtenerDatos($conn, 'contratos');

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

if (!empty($contratos)) {
    foreach ($contratos as $contact) {
        $contratos_indexados[$contact['id_clie']] = $contact;
    }
}

// Cerrar la conexión
// $conn->close();
function enviarCorreo($correoRemitente,$correo_destino, $asunto, $titulo, $cuerpo, $despedida,$fileUrl) { 
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
    $nombreRemitente = "InfoEfegepho";
     $contraseñaRemitente = '';
    if ($correoRemitente == 'ventas@efegepho.com.mx') {
            $contraseñaRemitente = 'innmrwrztxefiugg';
        } elseif ($correoRemitente == 'ventas1@efegepho.com.mx') { // Cambié 'else' por 'elseif'
            $contraseñaRemitente = 'cyoiqkeoebgderoz';
        } else {
        $contraseñaRemitente = 'glhewzgjzdnsbuvj';
    }
// $correoRemitente = 'info@efegepho.com.mx';
// $contraseñaRemitente = 'glhewzgjzdnsbuvj';
    try {
        // Servidor SMTP
        $mail->isSMTP();  // Usar SMTP
        $mail->SMTPAuth = true; // Usar autenticación SMTP
        $mail->SMTPSecure = 'starttls'; // Usar encriptación TLS
        $mail->Port = 587; // Puerto del servidor SMTP (587 para STARTTLS)

        $mail->Host = 'smtp.gmail.com';
        $mail->Username =  $correoRemitente; // Tu correo de Gmail
        $mail->Password = $contraseñaRemitente; 

        // Receptor del correo
        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->addAddress($correo_destino, 'Cliente'); // Reemplaza por el correo del destinatario

        // Asunto y contenido del correo
        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje; // Convierte saltos de línea en <br> para formato HTML
         // URL del archivo PDF
           //UTF-8
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->ContentType = 'text/html; charset=UTF-8';
        if($fileUrl != ''){
            
    // Crear un archivo temporal para descargar el archivo desde la URL
    $tempFile = tempnam(sys_get_temp_dir(), 'PHPMail');
    
    // Descargar el archivo desde la URL
    $fileContent = file_get_contents($fileUrl);
    file_put_contents($tempFile, $fileContent);

    // Agregar el archivo adjunto
    $mail->addAttachment($tempFile, 'EFEGE-contract.pdf');
        }
        // Enviar correo
        $mail_enviado = $mail->send();
        return $mail_enviado;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo]);
        return false;
    }
}
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

    // Invertir el signo de la diferencia
    if ($esNegativo) {
        // Si el evento ya pasó, convierte a positivo
        $resultado['dias'] = abs($resultado['dias']);
        $resultado['horas'] = abs($resultado['horas']);
        $resultado['minutos'] = abs($resultado['minutos']);
        $resultado['segundos'] = abs($resultado['segundos']);
    } else {
        // Si el evento es futuro, convierte a negativo
        $resultado['dias'] = -$resultado['dias'];
        $resultado['horas'] = -$resultado['horas'];
        $resultado['minutos'] = -$resultado['minutos'];
        $resultado['segundos'] = -$resultado['segundos'];
    }

    return $resultado;
}



foreach ($contact_form as $i => $clienteData) {
     $idCliente = (int)$clienteData['id'];
    $fecha_evento = $clienteData['wedding_date'];
    if (isset($contratos_indexados[$clienteData['id']])) {
    $contratoCliente = $contratos_indexados[$clienteData['id']];
    $id_vendedor_asignado = $clienteData['id_vendedor_asignado'];
} else{
    $contratoCliente = null;
}
    $contratoFirmado = false;

    // Buscar vendedor y cliente usando los índices
    $cliente = $contact_form_indexados[$clienteData['id']] ?? null;
   
    $hora_evento = "00:00:00";
    if (!$cliente) {
        echo "Error: Cliente no encontrado para el evento en la fila $i.<br>";
        continue; // Saltar al siguiente evento
    }

    // Calcular la diferencia de tiempo
    $diferencia = calcularDiferencia($fecha_evento, $hora_evento);

    // Crear objeto DateTime para el evento
    $evento_datetime = new DateTime("$fecha_evento $hora_evento");
              $hora_actual = new DateTime();

    echo "el cliente con id " . $clienteData['id'] . "<br>";
        if($contratoCliente){
            echo "<br>tiene un contrato adjuntado, veremos si ya esta firmado<br><br>";
            $contratoFirmado = $contratoCliente['estatus'] == '3' || $contratoCliente['estatus'] == 3;
            
             if(!$contratoFirmado){
             echo "<br>no firmado, empezar a enviar correos<br><br>";
             
             
               echo "<br><strong>¡Atención! El evento con ID " . $clienteData['id'] . " es el " . $fecha_evento . " y falta solo " . $diferencia['dias'] . " días.</strong><br><br>";   
             
            
                 if($hora_actual->format('H:i') == '10:00'){
                     
                     
                    //actualizar a correo enviado
                     
              // Actualizar datos en la base de datos
            $query = "UPDATE contratos SET estatus = 2
                      WHERE id_clie = '$idCliente'";

            // Ejecutar consulta
            if (mysqli_query($conn, $query)) {
                 $rutaContratoCliente = $contratoCliente['ruta_contrato'];
                  $vendedor= $usuarios_indexados[$id_vendedor_asignado] ?? null;
                   $customer = $cliente['names'];
                    $assigned_seller_email= $vendedor['correo'];
                    // $indexCorreo = ($cliente['country_code'] == '+52') ? 16 : 17;
                    $indexCorreo = 17;
                    $link = 'https://sandbox.efegepho.com.mx/'.$rutaContratoCliente;
                    $contract_download_link = '<a href="' . $link . '" target="_blank">Descargar Contrato</a>';
                       echo "enviando correos<br>";   

                 for ($i = 0; $i < 3; $i++) {
                          if($i == 0){
                               $asunto = ($indexCorreo == 16)?"EFEGE Contrato":"EFEGE Contract";
                                $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                                
                                $titulo = str_replace('$customer', $customer, $titulo);
                                $titulo = str_replace('$contract_download_link', $contract_download_link, $titulo);
                                $titulo = str_replace('$assigned_seller_email', $assigned_seller_email, $titulo);
                
                                  $asunto = $titulo;
                                $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                                
                                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                                $cuerpo = str_replace('$contract_download_link', $contract_download_link, $cuerpo);
                                $cuerpo = str_replace('$assigned_seller_email', $assigned_seller_email, $cuerpo);

                                $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                                
                                $despedida = str_replace('$customer', $customer, $despedida);
                                $despedida = str_replace('$contract_download_link', $contract_download_link, $despedida);
                                $despedida = str_replace('$assigned_seller_email', $assigned_seller_email, $despedida);
                                 $mail_enviado_customer = enviarCorreo($assigned_seller_email, $cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida,$link);
                          } if($i == 1){
                               $asunto = ($indexCorreo == 16)?"Contrato enviado al cliente":"Contract sent to the client";
                                $titulo = $txt_correo[$indexCorreo]['txttitulovend'];
                                
                                $titulo = str_replace('$customer', $customer, $titulo);
                                $titulo = str_replace('$contract_download_link', $contract_download_link, $titulo);
                                $titulo = str_replace('$assigned_seller_email', $assigned_seller_email, $titulo);
                
                                 $asunto = $titulo;
                                $cuerpo = $txt_correo[$indexCorreo]['txtvend'];
                                
                                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                                $cuerpo = str_replace('$contract_download_link', $contract_download_link, $cuerpo);
                                $cuerpo = str_replace('$assigned_seller_email', $assigned_seller_email, $cuerpo);

                                $despedida = $txt_correo[$indexCorreo]['txtdespvend'];
                                
                                $despedida = str_replace('$customer', $customer, $despedida);
                                $despedida = str_replace('$contract_download_link', $contract_download_link, $despedida);
                                $despedida = str_replace('$assigned_seller_email', $assigned_seller_email, $despedida);
                                //  $mail_enviado_vendedor = enviarCorreo('info@efegepho.com.mx', $assigned_seller_email, $asunto, $titulo, $cuerpo, $despedida,'');
                                 $mail_enviado_vendedor =true;

                          }
                          if($i == 2){
                               $asunto = ($indexCorreo == 16)?"Contrato enviado al cliente":"Contract sent to the client";
                                $titulo = $txt_correo[$indexCorreo]['txttituloadmin'];
                                
                                $titulo = str_replace('$customer', $customer, $titulo);
                                $titulo = str_replace('$contract_download_link', $contract_download_link, $titulo);
                                $titulo = str_replace('$assigned_seller_email', $assigned_seller_email, $titulo);
                                $asunto = $titulo;
                                
                                $cuerpo = $txt_correo[$indexCorreo]['txtadmin'];
                                
                                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                                $cuerpo = str_replace('$contract_download_link', $contract_download_link, $cuerpo);
                                $cuerpo = str_replace('$assigned_seller_email', $assigned_seller_email, $cuerpo);

                                $despedida = $txt_correo[$indexCorreo]['txtdespadmin'];
                                
                                $despedida = str_replace('$customer', $customer, $despedida);
                                $despedida = str_replace('$contract_download_link', $contract_download_link, $despedida);
                                $despedida = str_replace('$assigned_seller_email', $assigned_seller_email, $despedida);
                                  foreach ($admins as $admin) {
                                        $mail_enviado_admin = enviarCorreo('info@efegepho.com.mx',$admin['correo'], $asunto, $titulo, $cuerpo, $despedida,'');
                                    }
                          }
                      }
                      
                      if ($mail_enviado_customer && $mail_enviado_admin &&$mail_enviado_vendedor) {
                          echo "Contrato enviado con éxito <hr>";
                        } else {
                            // Si el correo al cliente falla
                            if (!$mail_enviado_customer) {
                                echo "Error al enviar el correo al cliente.<hr>";
                            }
                             if (!$mail_enviado_vendedor) {
                               echo "Error al enviar el correo al vendedor.<hr>";

                            }
                            // Si el correo al administrador falla
                            if (!$mail_enviado_admin) {
                              echo "Error al enviar el correo al admin.<hr>";

                            }
                        }
                
            }
            else{
                //  echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el estatus a enviado.']);
            }
                     
                 }else{
                      echo "aun no llega la hora<br>";
                 }
                 
            
             
             
             
             
              echo "<hr>";
            }else{
                 echo "<br>ya esta firmado, no hacer nada <br><br><hr>";
            }
        }else{
                 echo "<br>aun no han adjuntado el contrato, no hacer nada <br><br><hr>";
            }
       
             // Mostrar el mensaje si la fecha actual es 3 meses despues de su evento
//     if ($diferencia['dias'] <= 180 && $diferencia['dias'] >= 0) {
//         //   echo "<br><strong>¡Atención! El evento con ID " . $clienteData['id'] . " es el " . $fecha_evento . " y falta solo " . $diferencia['dias'] . " días.</strong><hr><br><br>";   
//         //     echo "hora actual ".$hora_actual->format('H:i') . "<br>";
//             if($hora_actual->format('H:i') == '11:16'){
//                   echo "<strong>subir datos</strong><br>";
//                   echo $idCliente . "<br>";

//           // Variables iniciales
// $fecha = "0000-00-00";  // Fecha vacía
// $hora = "00:00:00";     // Hora vacía
// $respuestas = "{}";     // JSON vacío como cadena
// $estatus = 1;           // Estatus de la respuesta

// // Verificar si ya existe un registro con id_clie igual a $idCliente
// $sql_check = "SELECT COUNT(*) FROM respuestas_cuestionario WHERE id_clie = ?";
// $stmt_check = $conn->prepare($sql_check);

// // Verificar si la preparación fue exitosa
// if ($stmt_check === false) {
//     die('Error en la preparación de la consulta: ' . $conn->error);
// }

// // Vinculamos el parámetro de verificación
// $stmt_check->bind_param("i", $idCliente);

// // Ejecutamos la consulta de verificación
// $stmt_check->execute();
// $stmt_check->bind_result($count);
// $stmt_check->fetch();
// $stmt_check->close();

// // Si no existe ningún registro, insertamos uno nuevo
// if ($count == 0) {
//     // Preparar la consulta para insertar el nuevo registro
//     $sql = "INSERT INTO respuestas_cuestionario (respuestas, id_clie, fecha, hora, estatus) 
//             VALUES (?, ?, ?, ?, ?)";
    
//     $stmt = $conn->prepare($sql);

//     // Verificar si la preparación fue exitosa
//     if ($stmt === false) {
//         die('Error en la preparación de la consulta: ' . $conn->error);
//     }

//     // Vincular los parámetros con la consulta (en este caso, todos son cadenas o enteros)
//     $stmt->bind_param("sissi", $respuestas , $idCliente, $fecha, $hora, $estatus);

//     // Ejecutar la consulta
//     if ($stmt->execute()) {
//         // Si la inserción es exitosa, hacer algo (por ejemplo, respuesta positiva)
       
//     } else {
//         // Si la inserción falla, manejar el error
       
//     }

//     // Cerrar el statement de inserción
   
// } else {
//     // Si ya existe el registro, no hacer nada
  
// }

          
                   
        
//         echo "<br><strong>Mandar correo</strong><br><hr>";
        
        
        
//         $indexCorreo = ($cliente['country_code'] == '+52') ? 10 : 11; //11 español - 12 ingles
//         $asunto = ($indexCorreo == 10)? "Responde el cuestionario" :"Answer the questionnaire";
//         $customer = $cliente['names'];  // Nombre del clien
        
//                     $link_questionnaire_esp = "<br>Español: <br> https://sandbox.efegepho.com.mx/cuestionario.php?id=" . $idCliente . "&lang=es <br><br>";
//                     $link_questionnaire_eng = "English: <br>https://sandbox.efegepho.com.mx/cuestionario.php?id=" . $idCliente . "&lang=eng <br>";        
//                     $link_questionnaire = $link_questionnaire_esp . $link_questionnaire_eng;
//                     $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                
//                     $titulo = str_replace('$customer', $customer, $titulo);
//                     $titulo = str_replace('$link_questionnaire', $link_questionnaire, $titulo);
                    
//                     $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                    
//                     $cuerpo = str_replace('$customer', $customer, $cuerpo);
//                     $cuerpo = str_replace('$link_questionnaire', $link_questionnaire, $cuerpo);
    
                    
//                     $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                    
//                     $despedida = str_replace('$customer', $customer, $despedida);
//                     $despedida = str_replace('$link_questionnaire', $link_questionnaire, $despedida);
    
                   
//                     if(enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida)){
//                         echo 'Correo enviado exitosamente.';
//                     }else{
//                          echo 'Correo no enviado.';
//                     }
        
//             }
//                     // Datos a insertar en la base de datos
           
        
        
//     }else{
//          echo "<br>No es necesario mandar aun el cuestionario<br><br><hr>";
//     }
            
        
       

    

}
?>
