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
    } else if($tabla == "galeria"){
          $sql = "SELECT * FROM $tabla WHERE estatus = 0";
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
function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida, $dias, $idioma,$idcliente,$datosCliente) { 
     $mail = new PHPMailer(true);
    // Contenido del correo HTML
    $mensajeWhatsApp = $idioma == 8 ? "Hola, quiero comprar mi galería. Soy " . $datosCliente['names'] . " y mi id es ".$idcliente : "Hello, I want to buy my gallery.%0AI'm " . $datosCliente['names'] . " and my ID is ".$idcliente;
    $showDaysRemaining = $idioma == 8 ?" <p>Solo te quedan: <strong>$dias días restantes</strong></p>" :  "<p>You only have: <strong>$dias days left</strong></p>";

$linkWhatsapp = "https://wa.me/5218115795265?text=" . urlencode($mensajeWhatsApp);
$textLinkWhatsapp =  "Buy now";
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
     
       $mail->send(); 
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo]);
    }
        
    
}

// Función para calcular la diferencia de tiempo



foreach ($contact_form as $i => $clienteData) {
        $idCliente = (int)$clienteData['id'];
        $hora_evento = "00:00:00";

         $fecha_evento = $clienteData['wedding_date']; // La fecha en formato Y-m-d (por ejemplo: "2025-06-15")
        // Crear un objeto DateTime a partir de la fecha del evento
        $fechaEvento = new DateTime($fecha_evento); // Crear el objeto DateTime desde la fecha original
        
        // Sumar 3 meses a la fecha
        $fechaEvento->modify('+3 months');
        
        // Obtener la nueva fecha después de agregar los 3 meses
        $fecha_creacion = clone $fechaEvento; // Usamos clone para evitar modificar el objeto original
        $fecha_creacion_galeria = $fecha_creacion->format('Y-m-d'); // La fecha en formato Y-m-d
        
        // Ahora, sumar 6 meses a la fecha actual (fecha_mas_3_meses)
        $fecha_creacion->modify('+6 months');
        // Obtener la nueva fecha después de agregar los 6 meses
        $fecha_vencimiento_galeria = $fecha_creacion->format('Y-m-d'); // La fecha en formato Y-m-d
        
        // Usamos la fecha resultante de los 3 meses
        $fecha_vencimiento = new DateTime($fecha_vencimiento_galeria); // Aquí se convierte nuevamente en DateTime
        
        $fecha_hoy = new DateTime();

    

    // Buscar vendedor y cliente usando los índices
    $cliente = $contact_form_indexados[$clienteData['id']] ?? null;
    
    $galeriasCliente = []; // Inicializamos un array vacío para almacenar a todos los administradores

        foreach ($galeria as $galeriac) {
            if ($galeriac['id_clie'] == $clienteData['id']) {  // Verifica si es administrador
                $galeriasCliente[] = $galeriac; // Añade el administrador al array de administradores
            }
        }

 
   
   
    if (!$cliente) {
        echo "Error: Cliente no encontrado para el evento en la fila $i.<br>";
        continue; // Saltar al siguiente evento
    }

    // Calcular la diferencia de tiempo

    // Crear objeto DateTime para el evento
    $fecha_actual = new DateTime();

    echo "el cliente con id " . $clienteData['id'] . "<br>";
    echo "La fecha del evento es el " . $clienteData['wedding_date']. "<br>";
    echo "la fecha de crea es " .$fecha_creacion_galeria . "<br>";
    echo "la fecha de venci es " .$fecha_vencimiento_galeria. "<br>";
   echo "la fecha de hoy es " .$fecha_hoy->format('Y-m-d'). "<br>";
    echo "el cliente tiene estas galerias: " . count($galeriasCliente) . "<br>";
 


$fecha_creacion_dt = new DateTime($fecha_creacion_galeria);
$fecha_vencimiento_dt = new DateTime($fecha_vencimiento_galeria);

// Nueva validación: no mandar correos si la fecha actual es mayor a la fecha de vencimiento

        // Comentado temporalmente para ver previsualizaciones: if($fecha_actual->format('H:i') == '10:19' ){
      if($fecha_actual->format('H:i') == '10:19' ){
  if (!empty($galeriasCliente)) {
    
    // El array tiene datos
    echo "Ya tiene galerias.<br>";
    if ($fecha_hoy > $fecha_vencimiento_dt) {
    echo "No se envía correo porque la fecha actual es mayor a la fecha de vencimiento.<br>";
    echo "<br><hr>";
} else if ($fecha_hoy >= $fecha_creacion_dt && $fecha_hoy <= $fecha_vencimiento_dt) {
      
       foreach ($galeriasCliente as $galeriacliente) {
            $estatusGaleria = (int)$galeriacliente['estatus'];
            $esManual =  (int)$galeriacliente['manual'] == 2;
            $fechavencimiento = $galeriacliente['fecha_vencimiento_galeria'];
             echo "Estatus: $estatusGaleria<br>";

        if($estatusGaleria == 0 || $estatusGaleria == "0"){
               echo "Empezar a mandar correos<br>";
        $idGaleria = $galeriacliente['id'];
        //  $indexCorreo = ($cliente['country_code'] == '+52') ? 8 : 9; //6 español - 7 ingles
        $indexCorreo = 9;
        $asunto = ($indexCorreo == 8)? "Compra tu Galeria" :"Buy your Gallery";
        
                // DEBUG: Mostrar las fechas para comparar
                echo "<strong>DEBUG - Cliente ID $idCliente:</strong><br>";
                echo "Fecha del evento: " . $clienteData['wedding_date'] . "<br>";
                echo "Fecha vencimiento calculada: $fecha_vencimiento_galeria<br>";
                echo "Fecha vencimiento en DB: $fechavencimiento<br>";

                // Usar la fecha de vencimiento calculada en lugar de la de la DB
                $fechavencimiento_corregida = $fecha_vencimiento_galeria;
                
                $customer = $cliente['names'];  // Nombre del cliente
                $expiration_date_gallery = $fechavencimiento_corregida . " " .  $fecha_actual->format('H:i');
                // Crear un objeto DateTime para la fecha de vencimiento
                $fecha_vencimiento_obj = new DateTime($expiration_date_gallery);
                
                // Crear un objeto DateTime para la fecha actual
                $fecha_actual = new DateTime();
                
                // Calcular la diferencia entre la fecha de hoy y la fecha de vencimiento
                $diferencia = $fecha_actual->diff($fecha_vencimiento_obj);
                // Mostrar la cantidad de días restantes
             
                $date = $fechavencimiento_corregida . " " .  $fecha_actual->format('H:i');
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
                    $asunto = $titulo;
                    $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                    
                    $cuerpo = str_replace('$customer', $customer, $cuerpo);
                    $cuerpo = str_replace('$expiration_date_gallery', $formattedDate, $cuerpo);
                 

                    
                    $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                    
                    $despedida = str_replace('$customer', $customer, $despedida);
                    $despedida = str_replace('$expiration_date_gallery', $formattedDate, $despedida);
                    
                    // Previsualización del correo
                    echo "<h3>Previsualización del correo para cliente ID: $idCliente</h3>";
                    echo "<strong>Para:</strong> " . $cliente['email_address'] . "<br>";
                    echo "<strong>Asunto:</strong> $asunto<br>";
                    echo "<strong>Título:</strong> $titulo<br>";
                    echo "<strong>Cuerpo:</strong> $cuerpo<br>";
                    echo "<strong>Despedida:</strong> $despedida<br>";
                    echo "<strong>Días restantes:</strong> " . $diferencia->days . "<br><br>";
                    
                   enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida,$diferencia->days, $indexCorreo,$idCliente,$cliente);
                   // ;
     
            
        } else{
            echo "No se envía correo porque el estatus de la galería es $estatusGaleria.<br>";
        }
        }
    } else {
        echo "No se envía correo porque la fecha actual no está dentro del rango permitido.<br>";
    }
    } else {



    //aqui se crea la galeria si no tiene,
    // El array está vacío
    echo "El array está vacío.<br>";
    
    // Validar que la fecha actual sea exactamente 3 meses después de la fecha del evento
    $fecha_evento_original = new DateTime($fecha_evento);
    $fecha_tres_meses_despues = clone $fecha_evento_original;
    $fecha_tres_meses_despues->modify('+3 months');
    
    // Comparar solo las fechas (sin horas)
    $fecha_hoy_solo_fecha = $fecha_hoy->format('Y-m-d');
    $fecha_creacion_exacta = $fecha_tres_meses_despues->format('Y-m-d');
    
    if ($fecha_hoy_solo_fecha == $fecha_creacion_exacta) {
        echo "¡Fecha exacta! Creando galería exactamente 3 meses después del evento.<br>";
        
        $hora_hoy = date('H:i:s');   // Hora en formato 00:00:00
        $query_insert = "INSERT INTO galeria (id_clie, fecha_registro_galeria, hora_registro_galeria, fecha_vencimiento_galeria, estatus, manual) 
                               VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($query_insert);
            
            // Bind de los parámetros para la inserción
            $estatus = 0;
            $manual = 1;
            $stmt_insert->bind_param('isssii', $idCliente, $fecha_creacion_galeria, $hora_hoy, $fecha_vencimiento_galeria, $estatus, $manual);
            
            // Ejecutar la inserción
            if ($stmt_insert->execute()) {
                echo "Nuevo registro creado correctamente.<br><br>";
             
        $indexCorreo = 8; //8 esp - 9 ing
        $asunto = ($indexCorreo == 8)? "Compra tu Galeria" :"Buy your Gallery";

    
                $customer = $cliente['names'];  // Nombre del cliente
                $expiration_date_gallery = $fecha_vencimiento_galeria . " " .  $fecha_actual->format('H:i');
                // Crear un objeto DateTime para la fecha de vencimiento
                $fecha_vencimiento_obj = new DateTime($expiration_date_gallery);
                
                // Crear un objeto DateTime para la fecha actual
                $fecha_actual = new DateTime();
                
                // Calcular la diferencia entre la fecha de hoy y la fecha de vencimiento
                $diferencia = $fecha_actual->diff($fecha_vencimiento_obj);
                
                // Mostrar la cantidad de días restantes
             
                $date = $fecha_vencimiento_galeria . " " .  $fecha_actual->format('H:i');
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
                    $asunto = $titulo;
                    $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                    
                    $cuerpo = str_replace('$customer', $customer, $cuerpo);
                    $cuerpo = str_replace('$expiration_date_gallery', $formattedDate, $cuerpo);
                 

                    
                    $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                    
                    $despedida = str_replace('$customer', $customer, $despedida);
                    $despedida = str_replace('$expiration_date_gallery', $formattedDate, $despedida);
                  
                    // Previsualización del correo
                    echo "<h3>Previsualización del correo para cliente ID: $idCliente (nuevo registro)</h3>";
                    echo "<strong>Para:</strong> " . $cliente['email_address'] . "<br>";
                    echo "<strong>Asunto:</strong> $asunto<br>";
                    echo "<strong>Título:</strong> $titulo<br>";
                    echo "<strong>Cuerpo:</strong> $cuerpo<br>";
                    echo "<strong>Despedida:</strong> $despedida<br>";
                    echo "<strong>Días restantes:</strong> " . $diferencia->days . "<br><br>";
                  
                    enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida,$diferencia->days, $indexCorreo,$idCliente,$cliente);
            
            } else {
                echo "Error al crear el registro: " . $stmt_insert->error . "<br><br>";
            }
    } else {
        echo "No es la fecha exacta para crear la galería. Fecha actual: $fecha_hoy_solo_fecha, Fecha requerida: $fecha_creacion_exacta<br>";
    }
}
     

    }
 
 
  echo "<br><hr>";
      
        
       

    

}
?>







