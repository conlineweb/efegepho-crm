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
$galeria = obtenerDatos($conn, 'galeria');

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

if (!empty($galeria)) {
    foreach ($galeria as $contact) {
        $galeria_indexados[$contact['id_clie']] = $contact;
    }
}

// Cerrar la conexión
// $conn->close();
function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida, $dias, $idioma, $idcliente,$datosCliente) { 
     $mail = new PHPMailer(true);
    // Contenido del correo HTML
    $mensajeWhatsApp = $idioma == 8 ? "Hola, quiero comprar mi galería. Soy " . $datosCliente['names'] . " y mi id es ".$idcliente : "Hello, I want to buy my gallery.%0AI'm " . $datosCliente['names'] . " and my ID is ".$idcliente;
    $showDaysRemaining = $idioma == 8 ?" <p>Solo te quedan: <strong>$dias días restantes</strong></p>" :  "<p>You only have: <strong>$dias days left</strong></p>";

$linkWhatsapp = "https://wa.me/5218115795265?text=" . urlencode($mensajeWhatsApp);
$textLinkWhatsapp = $idioma == 8 ? "Comprar ahora" : "Buy now";
$mensaje = "
<html>
<head>
  <title>$asunto</title>
  <style>
    /* Importar la fuente desde Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond&display=swap');

    .bg {
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
      margin-top: 13px;
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
          $showDaysRemaining
          <p>$despedida</p>

          <!-- Botón con estilo inline para compatibilidad con correos -->
          <a href='$linkWhatsapp' 
             style='
               display: block;
               width: 50%;
               margin: 40px auto 20px auto;
               padding: 16px 24px;
               background-color: #eee8dc;
               border: 1.5px solid #3B3B3B;
               border-radius: 15px;
               color: #3B3B3B;
               text-align: center;
               text-decoration: none;
               font-size: 16px;
               font-weight: 600;
               font-family: Cormorant Garamond, serif;
               box-sizing: border-box;
               cursor: pointer;
             ' 
             role='button'>
            $textLinkWhatsapp
          </a>
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


// echo $mensaje;
    // Encabezados para enviar el correo como HTML
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
    
       $correoRemitente = "posventa@efegepho.com.mx";
    $nombreRemitente = "posventa";
    
    try {
        // Servidor SMTP
        $mail->isSMTP();  // Usar SMTP
       
        $mail->SMTPAuth = true; // Usar autenticación SMTP
        $mail->SMTPSecure = 'starttls'; // Usar encriptación TLS
        $mail->Port = 587; // Puerto del servidor SMTP (587 para STARTTLS)
        
        $mail->Host = 'smtp.gmail.com';
        $mail->Username = $correoRemitente; // Tu correo de Gmail
        $mail->Password = 'hnyrwanoqzfabqta'; 

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
        if($mail->send()){
            return true;
        }else{
            return false;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo]);
    }
        
    // Enviar correo
    // mail($correo_destino, $asunto, $mensaje, $headers);
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

 // Incluye el archivo de conexión a la base de datos

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibir datos del formulario
        $idclie =  $_POST['idclie'];

    $fechaRegistroGaleria =  $_POST['fechaRegistroGaleria'];
    $horaRegistroGaleria =  $_POST['horaRegistroGaleria'];
    $fechaVencimientoGaleria = $_POST['fechaVencimientoGaleria'];
    $whatsapp = $_POST['whatsapp'];
    $manual = $_POST['manual'];
    $estatus = 0;
    // Preparar la consulta SQL (usando sentencias preparadas para prevenir inyección SQL)
    $stmt = $conn->prepare("INSERT INTO galeria (id_clie,fecha_registro_galeria,hora_registro_galeria, fecha_vencimiento_galeria, id_whatsapp, manual, estatus) 
                           VALUES (?,?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssiii",$idclie, $fechaRegistroGaleria, $horaRegistroGaleria, $fechaVencimientoGaleria, $whatsapp,$manual,$estatus);

    // Ejecutar la consulta
    if ($stmt->execute()) {
                $cliente = $contact_form_indexados[$idclie] ?? null;

        // Obtener el ID generado
        $id_generado = $conn->insert_id; // ID de la última fila insertada
        $idGaleria =$id_generado;
        //  $indexCorreo = ($cliente['country_code'] == '+52') ? 8 : 9; //6 español - 7 ingles
        $indexCorreo = 9;
        $asunto = ($indexCorreo == 8)? "Compra tu Galeria" :"Buy your Gallery";
        $customer = $cliente['names'];  // Nombre del cliente
        $expiration_date_gallery = $fechaVencimientoGaleria . " " .  $horaRegistroGaleria;
                // Crear un objeto DateTime para la fecha de vencimiento
                $fecha_vencimiento_obj = new DateTime($expiration_date_gallery);
                
                // Crear un objeto DateTime para la fecha actual
                $fecha_actual = new DateTime();
                
                // Calcular la diferencia entre la fecha de hoy y la fecha de vencimiento
                $diferencia = $fecha_actual->diff($fecha_vencimiento_obj);
                // Mostrar la cantidad de días restantes
             
                $date = $fechaVencimientoGaleria . " " .  $horaRegistroGaleria;
                $dateObject = new DateTime($date);
                $formattedDate = null;
                
                if($indexCorreo == 8 ){
                 $dateObject = new DateTime($date);

                    // Configurar el formateo de la fecha en español
                $fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, "eeee dd 'de' MMMM 'del' yyyy");
                
                // Formatear la fecha con el objeto IntlDateFormatter
                $formattedDate = $fmt->format($dateObject);
                                  

                }else{
                      $dateObject = new DateTime($date);
                    
                    // Configurar la localización a inglés
                    $locale = 'en_US.UTF-8'; // Asegúrate de que este locale está disponible en tu servidor
                     $fmt = new IntlDateFormatter($locale, IntlDateFormatter::LONG, IntlDateFormatter::NONE);
                    $fmt->setPattern('d MMMM yyyy'); // Formato: 1 January 2025 12:30 AM
                    
                    // Formatear la fecha y hora
                    $formattedDate = $fmt->format($dateObject);
                }
        
                
                
                
                    $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                    $titulo = str_replace('$customer', $customer, $titulo);
                    $titulo = str_replace('$expiration_date_gallery', $formattedDate, $titulo);

                    $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                    
                    $cuerpo = str_replace('$customer', $customer, $cuerpo);
                    $cuerpo = str_replace('$expiration_date_gallery', $formattedDate, $cuerpo);
                 

                    
                    $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                    
                    $despedida = str_replace('$customer', $customer, $despedida);
                    $despedida = str_replace('$expiration_date_gallery', $formattedDate, $despedida);
                  if($diferencia->days >= 0){
                   $mail_enviado = enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida,$diferencia->days, $indexCorreo,$idclie,$cliente);
                  }
        
                    
                    if($mail_enviado){
                          // Registro exitoso, devuelve un JSON de éxito con el ID generado
        echo json_encode([
            "status" => "success", 
            "message" => "Registro exitoso", 
            'id' => $id_generado, 
            'diferencia' => $diferencia, // Aquí pasas el id generado
        ]);
                    }else{
                        echo json_encode(["status" => "error", "message" => "Error al enviar correo"]);
        error_log("Error en la inserción: " . $stmt->error); // Log del error para depuración
                    }
        
  
    } else {
        // Error en el registro, devuelve un JSON de error
        echo json_encode(["status" => "error", "message" => "Error en la inserción de datos"]);
        error_log("Error en la inserción: " . $stmt->error); // Log del error para depuración
    }

    $stmt->close();
    $conn->close();
} else {
    // Si no es una petición POST, responde con error
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
}
?>
