<?php
// Incluir la conexión a la base de datos
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
// Verificar si el ID del cliente está presente
if (isset($_POST['id_cliente'])&& isset($_POST['id'])) {
    $idCliente = $_POST['id_cliente'];
    $idCuestionario = (int)$_POST['id'];
    $idrescuestionario = (int)$_POST['idrescuestionario'];
    $enviado = (int)$_POST['enviado'];

                     // Variables iniciales
    $fecha = "0000-00-00";  // Fecha vacía
    $hora = "00:00:00";     // Hora vacía
    $respuestas = "{}";     // JSON vacío como cadena
    $estatus = 1;           // Estatus de la respuesta
     $inserted_id = '1';  // Obtiene el último ID insertado
    // Verificar si ya existe un registro con id_clie igual a $idCliente
$sql_check = "SELECT COUNT(*) FROM respuestas_cuestionario WHERE id_clie = ? AND id_cuestionario = ?";
    $stmt_check = $conn->prepare($sql_check);
    
    // Verificar si la preparación fue exitosa
    if ($stmt_check === false) {
        die('Error en la preparación de la consulta: ' . $conn->error);
    }

    // Vinculamos el parámetro de verificación
    $stmt_check->bind_param("ii", $idCliente,$idCuestionario);
    
    // Ejecutamos la consulta de verificación
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

// Si no existe ningún registro, insertamos uno nuevo
if ($count == 0) {
    // Preparar la consulta para insertar el nuevo registro
    $sql = "INSERT INTO respuestas_cuestionario (respuestas, id_clie, fecha, hora, estatus,id_cuestionario) 
            VALUES (?, ?, ?, ?, ?,?)";
    
    $stmt = $conn->prepare($sql);

    // Verificar si la preparación fue exitosa
    if ($stmt === false) {
        die('Error en la preparación de la consulta: ' . $conn->error);
    }

    // Vincular los parámetros con la consulta (en este caso, todos son cadenas o enteros)
    $stmt->bind_param("sissii", $respuestas , $idCliente, $fecha, $hora, $estatus,$idCuestionario);

    // Ejecutar la consulta
    if ($stmt->execute()) {
        // Si la inserción es exitosa, hacer algo (por ejemplo, respuesta positiva)
         $inserted_id = $conn->insert_id;  // Obtiene el último ID insertado
    } else {
        // Si la inserción falla, manejar el error
       
    }

    // Cerrar el statement de inserción
   
} else {
    // Ya existe al menos un registro para este cliente y cuestionario
    if($enviado == 0){
         echo json_encode(['status' => 'error', 'message' => 'Ya se envió este cuestionario .']);
         exit;
    }
    // Reenvío: insertar un nuevo placeholder para que la nueva respuesta no sobreescriba la anterior
    $sql_new = "INSERT INTO respuestas_cuestionario (respuestas, id_clie, fecha, hora, estatus, id_cuestionario) 
                VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_new = $conn->prepare($sql_new);
    if ($stmt_new) {
        $stmt_new->bind_param("sissii", $respuestas, $idCliente, $fecha, $hora, $estatus, $idCuestionario);
        if ($stmt_new->execute()) {
            $inserted_id = $conn->insert_id;
        }
        $stmt_new->close();
    }
}

        
            
    
    //  $lang = ($idCuestionario == 1) ? "es" : "eng";   
    $cliente = $contact_form_indexados[$idCliente] ?? null;
    $indexCorreo = 11; // Forzar correo en inglés
    $asunto = "Answer the questionnaire";
                $customer = $cliente['names'];  // Nombre del cliente
                // $meet = "<a href='" . $vendedor['enlace_meet'] . "'>" . $vendedor['enlace_meet'] . "</a>";
                // $seller = $vendedor['nombre'];
                    // Siempre usamos el ID del nuevo placeholder insertado
                    $idRespuestaCuestionario = $inserted_id;
                    $link_questionnaire_esp = "<br>Spanish: <br> https://citas.efegepho.com.mx/cuestionario.php?id=" . $idCliente . "&lang=es <br><br>";
                    $link_questionnaire_eng = "English: <br>https://citas.efegepho.com.mx/cuestionario.php?id=" . $idCliente . "&lang=eng <br>";        
                    $link_questionnaire = "<br>https://citas.efegepho.com.mx/cuestionario.php?id=" . $idCliente . "&lang=" .$idCuestionario."&idres=".$idRespuestaCuestionario." <br><br>";
                    $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                
                    $titulo = str_replace('$customer', $customer, $titulo);
                    $titulo = str_replace('$link_questionnaire', $link_questionnaire, $titulo);
                    
                    $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                    
                    $cuerpo = str_replace('$customer', $customer, $cuerpo);
                    $cuerpo = str_replace('$link_questionnaire', $link_questionnaire, $cuerpo);
    
                    
                    $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                    
                    $despedida = str_replace('$customer', $customer, $despedida);
                    $despedidda = str_replace('$link_questionnaire', $link_questionnaire, $despedida);
    
                   
                    if(enviarCorreo($cliente['email_address'], $asunto, $titulo, $cuerpo, $despedida)){
                        echo json_encode(['status' => 'success', 'message' => 'Correo enviado exitosamente.', 'id'=>  $inserted_id]);
                    }else{
                           echo json_encode(['status' => 'error', 'message' => 'Correo no enviado .']);
                    }

    // Aquí puedes realizar cualquier operación con el ID del cliente, como guardarlo en la base de datos
    // Ejemplo:
    // $sql = "INSERT INTO encuestas (cliente_id) VALUES ('$idCliente')";
    // if ($conn->query($sql) === TRUE) {
    //     echo "success";
    // } else {
    //     echo "error";
    // }

    // Para este caso, solo vamos a devolver 'success'
           

} else {
            echo json_encode(['status' => 'error', 'message' => 'Correo no enviado .']);

}
?>
