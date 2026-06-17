<?php
// Incluir el archivo de configuración de la base de datos si es necesario
include 'conn.php'; // Suponiendo que tienes un archivo config.php con la configuración de la base de datos
// Cargar las clases de PHPMailer

$id = 1;
$sql = "SELECT * FROM nextstep WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $nextstep = $resultado->fetch_assoc(); // Aquí tienes el registro como array asociativo
} else {
    $nextstep = null; // No se encontró el registro
}

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
    $mail_enviado = false ;// mail($correo_destino, $asunto, $mensaje, $headers);
    $nombreRemitente = "InfoEfegepho";
     $contraseñaRemitente = '';
   if($correoRemitente == "ventas@efegepho.com.mx" || $correoRemitente == "ventas1@efegepho.com.mx"){
        if ($correoRemitente == 'ventas@efegepho.com.mx') {
            $contraseñaRemitente = 'innmrwrztxefiugg';
        } elseif ($correoRemitente == 'ventas1@efegepho.com.mx') { // Cambié 'else' por 'elseif'
            $contraseñaRemitente = 'cyoiqkeoebgderoz';
        } else {
        $contraseñaRemitente = 'glhewzgjzdnsbuvj';
    }
   }else{
       $correoRemitente = "info@efegepho.com.mx";
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
// Verificar si los datos fueron enviados por AJAX
if (isset($_POST['firmado']) && isset($_POST['id_vendedor_asignado']) &&  isset($_POST['mandarcorreo'])) {
     $firmado = $_POST['firmado']; 
      $id_vendedor_asignado = $_POST['id_vendedor_asignado']; 
      $mandarcorreo = (int)$_POST['mandarcorreo'];
        $idclie = $_POST['idclie'];
        if($mandarcorreo == 1){
            
              // Actualizar datos en la base de datos
            $query = "UPDATE contratos SET estatus = 2
                      WHERE id_clie = '$idclie'";

            // Ejecutar consulta
            if (mysqli_query($conn, $query)) {}
            else{
                 echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el estatus a enviado.']);
            }
            
            $rutaContratoCliente = $_POST['rutacontrato'];
            $cliente = $contact_form_indexados[$idclie] ?? null;
            $vendedor= $usuarios_indexados[$id_vendedor_asignado] ?? null;
            $customer = $cliente['names'];
            $assigned_seller_email= $vendedor['correo'];
            $indexCorreo = 17; // Forzar correo en inglés
            $link = 'https://citas.efegepho.com.mx/'.$rutaContratoCliente;
            $contract_download_link = '<a href="' . $link . '" target="_blank">Download Contract</a>';

               
                      for ($i = 0; $i < 3; $i++) {
                          if($i == 0){
                               $asunto = ($indexCorreo == 16)?"EFEGE Contrato":"EFEGE Contract";
                                $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                                
                                $titulo = str_replace('$customer', $customer, $titulo);
                                $titulo = str_replace('$contract_download_link', $contract_download_link, $titulo);
                                $titulo = str_replace('$assigned_seller_email', $assigned_seller_email, $titulo);
                
                                
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
                          echo json_encode([
                        'status' => 'success',
                        'message' => 'Contrato enviado con éxito',
                        'id_contrato' => $idContrato // Aquí se manda el ID generado
                    ]);
                        } else {
                            // Si el correo al cliente falla
                            if (!$mail_enviado_customer) {
                                echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al cliente.','correo'=>$assigned_seller_email]);
                            }
                             if (!$mail_enviado_vendedor) {
                                echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al vendedor.','correo'=>$assigned_seller_email]);
                            }
                            // Si el correo al administrador falla
                            if (!$mail_enviado_admin) {
                                echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al administrador.']);
                            }
                        }
                      
            
            
            
        }
        else{
             if ($firmado == 0) {
  if (isset($_FILES['contrato']) && isset($_POST['firmado']) && isset($_POST['idclie'])) {
    $contrato = $_FILES['contrato']; // El archivo que se va a subir
    $idclie = $_POST['idclie']; // ID del cliente
    $id_vendedor = $_POST['id_vendedor_asignado'];
    // Verificar si el archivo fue subido correctamente
    if (isset($_FILES['contrato']) && $_FILES['contrato']['error'] == 0) {
        // Obtener el nombre y la extensión del archivo
        $nombreArchivo = $_FILES['contrato']['name'];
        $extension = pathinfo($nombreArchivo, PATHINFO_EXTENSION);

        // Generar un nombre único para evitar duplicados
        $nuevoNombre = 'contrato_' . uniqid() . '.' . $extension;

        // Definir la ruta donde se guardará el archivo
        $rutaDestino1 = '../contratos/' . $nuevoNombre;
            $rutaDestino = 'contratos/' . $nuevoNombre;
        // Mover el archivo a la carpeta "contratos"
        if (move_uploaded_file($_FILES['contrato']['tmp_name'], $rutaDestino1)) {
            // Verificar si ya existe un contrato para el cliente
            $queryCheck = "SELECT * FROM contratos WHERE id_clie = '$idclie'";
            $resultCheck = mysqli_query($conn, $queryCheck);

            if (mysqli_num_rows($resultCheck) > 0) {
               
                // Si el cliente ya tiene un contrato, solo actualizamos la ruta
                $queryUpdate = "UPDATE contratos SET ruta_contrato = '$rutaDestino' WHERE id_clie = '$idclie'";
                
                if (mysqli_query($conn, $queryUpdate)) {
                    // Obtener el id_contrato después de la actualización
                    $queryGetId = "SELECT id FROM contratos WHERE id_clie = '$idclie'";
                    $resultGetId = mysqli_query($conn, $queryGetId);
                    
                    if ($resultGetId && mysqli_num_rows($resultGetId) > 0) {
                        $row = mysqli_fetch_assoc($resultGetId);
                        $idContrato = $row['id']; // ID del contrato actualizado
                        
                        
                        
                          $rutaContratoCliente = $rutaDestino;
            $cliente = $contact_form_indexados[$idclie] ?? null;
            $vendedor= $usuarios_indexados[$id_vendedor] ?? null;
            $customer = $cliente['names'];
            $assigned_seller_email= $vendedor['correo'];
            $indexCorreo = 17; // Forzar correo en inglés
            $link = 'https://citas.efegepho.com.mx/'.$rutaContratoCliente;
            $contract_download_link = '<a href="' . $link . '" target="_blank">Download Contract</a>';

               
                      for ($i = 0; $i < 3; $i++) {
                          if($i == 0){
                               $asunto = ($indexCorreo == 16)?"EFEGE Contrato":"EFEGE Contract";
                                $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                                
                                $titulo = str_replace('$customer', $customer, $titulo);
                                $titulo = str_replace('$contract_download_link', $contract_download_link, $titulo);
                                $titulo = str_replace('$assigned_seller_email', $assigned_seller_email, $titulo);
                
                                
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
                          echo json_encode([
                       'status' => 'success',
                            'message' => 'Contrato actualizado con éxito',
                            'ruta_contrato' => $rutaDestino,
                            'id_contrato' => $idContrato, // Aquí mandamos el ID del contrato actualizado
                    ]);
                        } else {
                            // Si el correo al cliente falla
                            if (!$mail_enviado_customer) {
                                echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al cliente.','correo'=>$assigned_seller_email]);
                            }
                             if (!$mail_enviado_vendedor) {
                                echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al vendedor.','correo'=>$assigned_seller_email]);
                            }
                            // Si el correo al administrador falla
                            if (!$mail_enviado_admin) {
                                echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al administrador.']);
                            }
                        }
                      
            

                        // echo json_encode([
                            // 'status' => 'success',
                            // 'message' => 'Contrato actualizado con éxito',
                            // 'ruta_contrato' => $rutaDestino,
                            // 'id_contrato' => $idContrato, // Aquí mandamos el ID del contrato actualizado
                        // ]);
                        
                        
                        
                        
                        
                        
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'No se pudo obtener el id_contrato después de la actualización']);
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Hubo un error al actualizar en la base de datos']);
                }
            } else {
                // Si no existe el cliente en la base de datos, insertamos un nuevo contrato
                $fechaRegistro = date('Y-m-d');
                $horaRegistro = date('H:i:s');
                $estatus = 1; // Estatus 1 significa "activo"

                // Preparar la consulta para insertar el contrato en la base de datos
                $queryInsert = "INSERT INTO contratos (id_clie, ruta_contrato, fecha_registro, hora_registro, estatus)
                                VALUES ('$idclie', '$rutaDestino', '$fechaRegistro', '$horaRegistro', '$estatus')";

                if (mysqli_query($conn, $queryInsert)) {
                    $idContrato = mysqli_insert_id($conn); // ID generado por autoincremento al insertar
                    // echo json_encode([
                        // 'status' => 'success',
                        // 'message' => 'Contrato enviado con éxito',
                        // 'id_contrato' => $idContrato,
                        // 'ruta_contrato' => $rutaDestino // Aquí se manda el ID generado
                    // ]);
                    
                    
                     $rutaContratoCliente = $rutaDestino;
            $cliente = $contact_form_indexados[$idclie] ?? null;
            $vendedor= $usuarios_indexados[$id_vendedor] ?? null;
            $customer = $cliente['names'];
            $assigned_seller_email= $vendedor['correo'];
            $indexCorreo = 17; // Forzar correo en inglés
            $link = 'https://citas.efegepho.com.mx/'.$rutaContratoCliente;
            $contract_download_link = '<a href="' . $link . '" target="_blank">Download Contract</a>';

               
                      for ($i = 0; $i < 3; $i++) {
                          if($i == 0){
                               $asunto = ($indexCorreo == 16)?"EFEGE Contrato":"EFEGE Contract";
                                $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                                
                                $titulo = str_replace('$customer', $customer, $titulo);
                                $titulo = str_replace('$contract_download_link', $contract_download_link, $titulo);
                                $titulo = str_replace('$assigned_seller_email', $assigned_seller_email, $titulo);
                
                                
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
                          echo json_encode([
                            'status' => 'success',
                        'message' => 'Contrato enviado con éxito',
                        'id_contrato' => $idContrato,
                        'ruta_contrato' => $rutaDestino // Aquí se manda el ID generado
                    ]);
                        } else {
                            // Si el correo al cliente falla
                            if (!$mail_enviado_customer) {
                                echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al cliente.','correo'=>$assigned_seller_email]);
                            }
                             if (!$mail_enviado_vendedor) {
                                echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al vendedor.','correo'=>$assigned_seller_email]);
                            }
                            // Si el correo al administrador falla
                            if (!$mail_enviado_admin) {
                                echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al administrador.']);
                            }
                        }
                      
                } else {
                    // Error al insertar en la base de datos
                    echo json_encode(['status' => 'error', 'message' => 'Hubo un error al guardar en la base de datos']);
                }
            }
        } else {
            // Error al mover el archivo
            echo json_encode(['status' => 'error', 'message' => 'Error al subir el archivo']);
        }
    } else {
        // El archivo no se subió correctamente
        echo json_encode(['status' => 'error', 'message' => 'No se seleccionó un archivo o hubo un error al subirlo']);
    }
} else {
    // Datos no enviados correctamente
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
}
    }
    else if ($firmado == 1){
        

// Verificar si los datos fueron enviados
if (isset($_POST['idclie']) && isset($_POST['idcontrato'])) {
    $idclie = $_POST['idclie']; // ID del cliente
    $idcontrato = $_POST['idcontrato']; // ID del contrato

     $fechaFirma = date('Y-m-d');
            $horaFirma = date('H:i:s');
            
            // Actualizar datos en la base de datos
            $query = "UPDATE contratos SET  fecha_firma = '$fechaFirma', hora_firma = '$horaFirma', estatus = 3
                      WHERE id_clie = '$idclie' AND id = '$idcontrato'";

            // Ejecutar consulta
            if (mysqli_query($conn, $query)) {
                
                
                        $cliente = $contact_form_indexados[$idclie] ?? null;
                        $vendedor= $usuarios_indexados[$id_vendedor_asignado] ?? null;
                        $customer = $cliente['names'];
                        $assigned_seller_email= $vendedor['correo'];
                        $indexCorreo = 19; // Forzar correo en inglés
                        $link = 'https://citas.efegepho.com.mx/'.$rutaDestino;
                        $contract_download_link = '<a href="' . $link . '" target="_blank">Download Contract</a>';

                        $indexCorreoNextStep = 21; // Forzar correo en inglés
                       
                      for ($i = 0; $i < 3; $i++) {
                          if($i == 0){
                              
                                $asunto = ($indexCorreo == 16)?"Contrato Firmado Recibido":"Contract Signed Received";
                                $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                                
                                $titulo = str_replace('$customer', $customer, $titulo);
                                $titulo = str_replace('$contract_download_link', $contract_download_link, $titulo);
                                $titulo = str_replace('$assigned_seller_email', $assigned_seller_email, $titulo);
                
                                
                                $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                                
                                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                                $cuerpo = str_replace('$contract_download_link', $contract_download_link, $cuerpo);
                                $cuerpo = str_replace('$assigned_seller_email', $assigned_seller_email, $cuerpo);

                                $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                                
                                $despedida = str_replace('$customer', $customer, $despedida);
                                $despedida = str_replace('$contract_download_link', $contract_download_link, $despedida);
                                $despedida = str_replace('$assigned_seller_email', $assigned_seller_email, $despedida);
                                $mail_enviado_customer = enviarCorreo('info@efegepho.com.mx', $cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida,'');
                                
                                // enviar nextstep
                                if($nextstep != null){
                                    $linkNextStep = 'https://citas.efegepho.com.mx/'.$nextstep['ruta'];
                                    $nextstep_download_link = '<a href="' . $linkNextStep . '" target="_blank">Download nextstep</a>';
                               }else{
                                   $nextstep_download_link = null;
                               }
                                
                                $titulo = $txt_correo[$indexCorreoNextStep]['txttituloclie'];
                                 $asunto = $titulo;

                                $titulo = str_replace('$customer', $customer, $titulo);
                                $titulo = str_replace('$linkNextstep', $nextstep_download_link, $titulo);
                
                                
                                $cuerpo = $txt_correo[$indexCorreoNextStep]['txtcli'];
                                
                                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                                $cuerpo = str_replace('$linkNextstep', $nextstep_download_link, $cuerpo);

                                $despedida = $txt_correo[$indexCorreoNextStep]['txtdespclie'];
                                
                                $despedida = str_replace('$customer', $customer, $despedida);
                                $despedida = str_replace('$linkNextstep', $nextstep_download_link, $despedida);
                                $mail_enviado_customer2 = enviarCorreo('info@efegepho.com.mx', $cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida,'');
                               if($mail_enviado_customer2){
                                    $isNextstep = 1;
                                $queryInsert = "INSERT INTO contratos (id_clie, ruta_contrato, fecha_registro, hora_registro, estatus, nextstep)
                                VALUES ('$idclie', '{$nextstep['ruta']}', '$fechaRegistro', '$horaRegistro', '$estatus', '$isNextstep')";
                                
                               }
                                

                                
                          } if($i == 1){
                               $asunto = ($indexCorreo == 16)?"Contrato enviado al cliente":"Contract sent to the client";
                                $titulo = $txt_correo[$indexCorreo]['txttitulovend'];
                                
                                $titulo = str_replace('$customer', $customer, $titulo);
                                $titulo = str_replace('$contract_download_link', $contract_download_link, $titulo);
                                $titulo = str_replace('$assigned_seller_email', $assigned_seller_email, $titulo);
                
                                
                                $cuerpo = $txt_correo[$indexCorreo]['txtvend'];
                                
                                $cuerpo = str_replace('$customer', $customer, $cuerpo);
                                $cuerpo = str_replace('$contract_download_link', $contract_download_link, $cuerpo);
                                $cuerpo = str_replace('$assigned_seller_email', $assigned_seller_email, $cuerpo);

                                $despedida = $txt_correo[$indexCorreo]['txtdespvend'];
                                
                                $despedida = str_replace('$customer', $customer, $despedida);
                                $despedida = str_replace('$contract_download_link', $contract_download_link, $despedida);
                                $despedida = str_replace('$assigned_seller_email', $assigned_seller_email, $despedida);
                                //  $mail_enviado_vendedor = enviarCorreo('info@efegepho.com.mx', $assigned_seller_email, $asunto, $titulo, $cuerpo, $despedida,'');
                                $mail_enviado_vendedor = true;
                          }
                          if($i == 2){
                               $asunto = ($indexCorreo == 16)?"Contrato enviado al cliente":"Contract sent to the client";
                                $titulo = $txt_correo[$indexCorreo]['txttituloadmin'];
                                
                                $titulo = str_replace('$customer', $customer, $titulo);
                                $titulo = str_replace('$contract_download_link', $contract_download_link, $titulo);
                                $titulo = str_replace('$assigned_seller_email', $assigned_seller_email, $titulo);
                
                                
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
                           echo json_encode(['status' => 'success', 'message' => 'Contrato firmado y actualizado correctamente.','id_contrato' => $idcontrato, 'ruta_contrato'=>$rutaDestino]);
                        } else {
                            // Si el correo al cliente falla
                            if (!$mail_enviado_customer) {
                                echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al cliente.',]);
                            }
                             if (!$mail_enviado_vendedor) {
                                echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al vendedor.',]);
                            }
                            // Si el correo al administrador falla
                            if (!$mail_enviado_admin) {
                                echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo al administrador.']);
                            }
                        }
                
              
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error al actualizar la base de datos.']);
            }
} else {
    // Si los datos no están completos, mostrar mensaje de error
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos.']);
}


    }
        }
      
   
    
}





?>
