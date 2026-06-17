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
$cuestionarios = obtenerDatos($conn, 'respuestas_cuestionario');

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

if (!empty($cuestionarios)) {
    foreach ($cuestionarios as $contact) {
        $cuestionarios_indexados[$contact['id_clie']] = $contact;
    }
}

// Cerrar la conexión
// $conn->close();
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
      
       
      
         return $mail->send();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo]);
    }
  

   
}


// Recorrer el calendario
foreach ($contact_form as $i => $clienteData) {
     $idCliente = (int)$clienteData['id'];
    $fecha_evento = $clienteData['wedding_date'];
    if (isset($cuestionarios_indexados[$clienteData['id']])) {
    $cuestionario = $cuestionarios_indexados[$clienteData['id']];
} else {
    // Si no existe, manejar el caso de alguna forma
    // Ejemplo: devolver un error o asignar un valor por defecto
    $cuestionario = null; // O lo que consideres adecuado
}
    // Buscar vendedor y cliente usando los índices
    $cliente = $contact_form_indexados[$clienteData['id']] ?? null;
   
    $hora_evento = "00:00:00";
    if (!$cliente) {
        echo "Error: Cliente no encontrado para el evento en la fila $i.<br>";
        continue; // Saltar al siguiente evento
    }
    
    
    //obtener cuando es 6 meses antes de la fecha del evento

    // Crear un objeto DateTime con la fecha del evento
    $fechaEvento = new DateTime($fecha_evento);
    
    // Restar 6 meses a la fecha
    $fechaEvento->modify('-6 months');
    
    // Obtener la fecha resultante en formato Y-m-d
    $fecha_seis_meses_antes = $fechaEvento->format('Y-m-d');
     echo "<hr>el cliente con id " . $clienteData['id'] . "<br>";
    echo "empezar a mandar en la fecha: " . $fecha_seis_meses_antes . "<br>";
    

    $fecha_hoy = new DateTime(); // Esto obtiene la fecha actual

 
       
        if($cuestionario){
            echo "<br>tiene el cuestionario respondido<br><br>";
        }else {
        
          echo "son las " . $fecha_hoy->format('H:i') ." <br><br>";

             // Mostrar el mensaje si faltan 6 meses o menos para el evento
    if ($fecha_hoy >= $fechaEvento) {
            echo "<strong>Ya se empezara a enviar correos</strong><br>";
            if($fecha_hoy->format('H:i') == '10:00'){
                  echo "<strong>subir datos</strong><br>";
                 

           // Variables iniciales
$fecha = "0000-00-00";  // Fecha vacía
$hora = "00:00:00";     // Hora vacía
$respuestas = "{}";     // JSON vacío como cadena
$estatus = 1;           // Estatus de la respuesta

// Verificar si ya existe un registro con id_clie igual a $idCliente
$sql_check = "SELECT COUNT(*) FROM respuestas_cuestionario WHERE id_clie = ?";
$stmt_check = $conn->prepare($sql_check);

// Verificar si la preparación fue exitosa
if ($stmt_check === false) {
    die('Error en la preparación de la consulta: ' . $conn->error);
}

// Vinculamos el parámetro de verificación
$stmt_check->bind_param("i", $idCliente);

// Ejecutamos la consulta de verificación
$stmt_check->execute();
$stmt_check->bind_result($count);
$stmt_check->fetch();
$stmt_check->close();

// Si no existe ningún registro, insertamos uno nuevo
if ($count == 0) {
    // Preparar la consulta para insertar el nuevo registro
    $sql = "INSERT INTO respuestas_cuestionario (respuestas, id_clie, fecha, hora, estatus) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);

    // Verificar si la preparación fue exitosa
    if ($stmt === false) {
        die('Error en la preparación de la consulta: ' . $conn->error);
    }

    // Vincular los parámetros con la consulta (en este caso, todos son cadenas o enteros)
    $stmt->bind_param("sissi", $respuestas , $idCliente, $fecha, $hora, $estatus);

    // Ejecutar la consulta
    if ($stmt->execute()) {
        // Si la inserción es exitosa, hacer algo (por ejemplo, respuesta positiva)
       
    } else {
        // Si la inserción falla, manejar el error
       
    }

    // Cerrar el statement de inserción
   
} else {
    // Si ya existe el registro, no hacer nada
  
}

          
                   
        
        echo "<br><strong>Mandar correo</strong><br>";
        
        
        
        // $indexCorreo = ($cliente['country_code'] == '+52') ? 10 : 11; //11 español - 12 ingles
        $indexCorreo = 11;
        $asunto = ($indexCorreo == 10)? "Responde el cuestionario" :"Answer the questionnaire";
        $customer = $cliente['names'];  // Nombre del clien
        
                    $link_questionnaire_esp = "<br>Español: <br> https://citas.efegepho.com.mx/cuestionario.php?id=" . $idCliente . "&lang=es <br><br>";
                    $link_questionnaire_eng = "English: <br>https://citas.efegepho.com.mx/cuestionario.php?id=" . $idCliente . "&lang=eng <br>";        
                    $link_questionnaire = $link_questionnaire_esp . $link_questionnaire_eng;
                    $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                  
                    $titulo = str_replace('$customer', $customer, $titulo);
                    $titulo = str_replace('$link_questionnaire', $link_questionnaire, $titulo);
                      $asunto = $titulo;
                    $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                    
                    $cuerpo = str_replace('$customer', $customer, $cuerpo);
                    $cuerpo = str_replace('$link_questionnaire', $link_questionnaire, $cuerpo);
    
                    
                    $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                    
                    $despedida = str_replace('$customer', $customer, $despedida);
                    $despedida = str_replace('$link_questionnaire', $link_questionnaire, $despedida);
    
                   
                    if(enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida)){
                        echo 'Correo enviado exitosamente.';
                    }else{
                         echo 'Correo no enviado.';
                    }
        
            }
                    // Datos a insertar en la base de datos
           
        
        
    }else{
         echo "<br>No es necesario mandar aun el cuestionario<br><br>";
    }
            
        }
       

    

}
?>
