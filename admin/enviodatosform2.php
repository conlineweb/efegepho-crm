<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'conn.php';

// Verificar la conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$response = [];

$stmt=$conn->prepare("SELECT hora,idusu FROM `calendario` where fecha=? AND hora=? ORDER BY hora ASC;");
$stmt->bind_param('ss',$_POST['date_appointment'],$_POST['time_appointment']);
$stmt->execute();
$res=$stmt->get_result();
$citas = $res->fetch_all(MYSQLI_ASSOC);

$timestamp = strtotime($_POST['date_appointment']);
$dia_semana = date('w', $timestamp);

$stmt=$conn->prepare("SELECT horarios, idusu FROM `atencion` WHERE dia = ?");
$stmt->bind_param('s',$_POST['date_appointment']);
$stmt->execute();
$res=$stmt->get_result();
$hrs = $res->fetch_all(MYSQLI_ASSOC);

$vendedores = [];

foreach($hrs as $vendor){

$vendor_horarios = json_decode($vendor['horarios'], true); // true to return as array

    foreach($vendor_horarios as $horario){

            if ($_POST['time_appointment'] == $horario) {
        if (count($citas)>0){
            foreach($citas as $cita){
                if ($vendor['idusu'] != $cita['idusu']) {
                    $vendedores[]=$vendor['idusu'];
                }
                
                
                // $vendor = array_search(,$vendedores);
                // if ($vendor !== false){
                //     array_splice($vendedores,$vendor,1);
                // }
            }
        } else {
            $vendedores[]=$vendor['idusu'];
        }
        
    }
        
}

}



if(count($vendedores) > 0){
    $sql = "SELECT IFNULL(MAX(id), 0) + 1 AS next_id FROM contact_form";
    $result = $conn->query($sql);
    $id = "0";
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $id = $row['next_id'];
        // echo "El próximo ID será: " . $next_id;
    } else {
        $response['success'] = false;
    }
    
    // Obtener los datos del formulario
    
    $names = trim($_POST['names'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email_address = trim($_POST['email'] ?? '');
    $wedding_date = trim($_POST['wedding_date'] ?? '');
    $wedding_location = trim($_POST['wedding_location'] ?? '');
    $wedding_planner = trim($_POST['wedding_planner'] ?? '');
    $guests_count = intval($_POST['guests'] ?? 0); // Convertir a entero
    $how_did_you_meet = trim($_POST['meet'] ?? '');
    $couple_activities = trim($_POST['couple_activities'] ?? '');
    $favorite_movie_or_song = trim($_POST['favorite_movie_song'] ?? '');
    $look_preference = trim($_POST['photo_style'] ?? '');
    $hear_about_us = trim($_POST['how_did_hear'] ?? '');
    $instagram_handle = trim($_POST['instagram'] ?? '');
    $service_interest = trim($_POST['service'] ?? '');
    $additional_details = trim($_POST['details'] ?? '');
    $submission_date = date("Y-m-d H:i:s");
    $country_code = trim($_POST['country_code'] ?? '');
    $time_appointment = trim($_POST['time_appointment'] ?? '');
    $date_appointment = trim($_POST['date_appointment'] ?? '');
    
    
    
    // Campos adicionales para registros manuales
    $form_name = trim($_POST['form_name'] ?? '');
    $campaign_name = trim($_POST['campaign_name'] ?? '');

    // Normalizar campaign_name: si viene vacío, nulo o 'undefined'/'null' usar 'reg manual'
    if ($campaign_name === '' || strtolower($campaign_name) === 'undefined' || strtolower($campaign_name) === 'null') {
        $campaign_name = 'reg manual';
    }

    // Normalizar form_name: asegurar que contiene 'reg manual'
    $needle = 'reg manual';
    if ($form_name === '' || strtolower($form_name) === 'undefined' || strtolower($form_name) === 'null') {
        $form_name = $needle;
    } else {
        if (stripos($form_name, $needle) === false) {
            $form_name = trim($form_name . ' ' . $needle);
        }
    }

    error_log('Normalized campaign_name: ' . $campaign_name . ' | form_name: ' . $form_name);

    // Asegurarnos que las columnas existen (intentar crearlas si no existen)
    function ensureColumnExists($conn, $table, $columnName, $columnDef) {
        $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $conn->real_escape_string($table) . "' AND COLUMN_NAME = '" . $conn->real_escape_string($columnName) . "'";
        $res = $conn->query($checkSql);
        if ($res) {
            $row = $res->fetch_assoc();
            if ((int)$row['c'] === 0) {
                $alter = "ALTER TABLE `" . $conn->real_escape_string($table) . "` ADD COLUMN " . $columnDef;
                if (!$conn->query($alter)) {
                    error_log('No se pudo crear columna ' . $columnName . ': ' . $conn->error);
                } else {
                    error_log('Columna creada: ' . $columnName);
                }
            }
        }
    }

    ensureColumnExists($conn, 'contact_form', 'form_name', "`form_name` VARCHAR(255) DEFAULT ''");
    ensureColumnExists($conn, 'contact_form', 'campaign_name', "`campaign_name` VARCHAR(255) DEFAULT ''");

    // Preparar la declaración SQL (incluyendo form_name y campaign_name)
    $sql = "INSERT INTO contact_form 
        (names, telephone,country_code, email_address, wedding_date, wedding_location, wedding_planner, guests_count, how_did_you_meet, couple_activities, favorite_movie_or_song, look_preference, instagram_handle, hear_about_us, service_interest, additional_details, submission_date, time_appointment, date_appointment, form_name, campaign_name) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    // Validar preparación de la consulta
    if ($stmt === false) {
        die("Error al preparar la consulta: " . $conn->error);
    }
    
    // Asociar parámetros
    $stmt->bind_param(
        "sssssssisssssssssssss",
        $names, $telephone,$country_code, $email_address, $wedding_date, $wedding_location, $wedding_planner, $guests_count, 
        $how_did_you_meet, $couple_activities, $favorite_movie_or_song, $look_preference, $instagram_handle, 
        $hear_about_us, $service_interest, $additional_details, $submission_date, $time_appointment, $date_appointment, $form_name, $campaign_name
    );
    
    // Ejecutar la declaración y enviar la respuesta
    
    if ($stmt->execute()) {
            $idGenerated = $stmt->insert_id;  // This is the crucial part

        $idusu = $vendedores[array_rand($vendedores,1)];
    
        $response['success'] = true;
        $response['cust']=$idGenerated;
        $response['alert']=1;
        $response['vendedor'] =$idusu;
        
        //mandar a la tabla calendario
        
        $sql = "INSERT INTO calendario 
        (idusu, fecha, hora, titulo, nota, idclie) 
        VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
     
    // Asignar valores a las variables
    // $idusu = 2; // Valor literal 0 asignado a una variable
    $titulo = ''; // Título vacío
    $nota = ''; // Nota vacía
    
    // Asociar parámetros
    $stmt->bind_param(
        "issssi", 
        $idusu, $date_appointment, $time_appointment, $titulo, $nota, $idGenerated
    );
    
    // Ejecutar la consulta
    $stmt->execute();
    } else
        $response['success'] = false;
} else
$response['success'] = 'full';
    
//     //hacer la consulta a usuarios para mandar datos del vendedor al correo
    
//     $sql = "SELECT * FROM usuarios WHERE id = ?";
//     $stmt = $conn->prepare($sql);

//     if ($stmt === false) {
//         die("Error al preparar la consulta: " . $conn->error);
//     }
    
//     // Asociar el parámetro
//     $stmt->bind_param("i", $idusu);
    
//     // Ejecutar la consulta
//     if (!$stmt->execute()) {
//         die("Error al ejecutar la consulta: " . $stmt->error);
//     }
    
//     // Obtener los resultados
//     $result = $stmt->get_result();
    
        
//      $nombrevendedor = '';
    
//     if ($result->num_rows === 0) {
//     echo "No se encontró ningún usuario con ese ID.";
//     } else {
//         // Procesar los resultados
//         while ($row = $result->fetch_assoc()) {
//           $nombrevendedor = $row['nombre'];
//         }
//     }
        
   
    
    
    
//     // Enviar correo de confirmación
//     $subject = "Wedding Inquiry - Confirmation";
//     $to = "solicitudes@efegepho.com.mx"; // Correo del usuario
//     $from = "solicitudes@efegepho.com.mx"; // Correo desde el cual se enviará el mensaje
//     $headers = "MIME-Version: 1.0" . "\r\n";
//     $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
//     $headers .= "From: " . $from . "\r\n";

//     // Plantilla HTML para el correo
//     $message = "
//     <html>
//     <head>
//         <title>Wedding Inquiry - Confirmation</title>
//     </head>
//     <body>
//     <div class='container'>
//         <h2>Website form submission record. $names!</h2>
//         <p>You have received the wedding details from the website. Below are the details submitted:</p>
        
//         <div class='container' background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); overflow: hidden;'>
//         <div class='header' style='background-color: #EEE8DC; padding: 20px; text-align: center;'>
//             <h2 style='color: #000; margin: 0;'>You have a record of $names!</h2>
//         </div>
//         <table>
//             <tr>
//                 <td><strong>Your Names:</strong></td>
//                 <td>$names</td>
//             </tr>
//             <tr>
//                 <td><strong>Telephone:</strong></td>
//                 <td> $country_code $telephone</td>
//             </tr>
//             <tr>
//                 <td><strong>Email:</strong></td>
//                 <td>$email_address</td>
//             </tr>
//             <tr>
//                 <td><strong>Wedding Date:</strong></td>
//                 <td>$wedding_date</td>
//             </tr>
//             <tr>
//                 <td><strong>Wedding Location:</strong></td>
//                 <td>$wedding_location</td>
//             </tr>
//             <tr>
//                 <td><strong>Wedding Planner:</strong></td>
//                 <td>$wedding_planner</td>
//             </tr>
//             <tr>
//                 <td><strong>Guests Count:</strong></td>
//                 <td>$guests_count</td>
//             </tr>
//             <tr>
//                 <td><strong>How did you meet?</strong></td>
//                 <td>$how_did_you_meet</td>
//             </tr>
//             <tr>
//                 <td><strong>Couple Activities:</strong></td>
//                 <td>$couple_activities</td>
//             </tr>
//             <tr>
//                 <td><strong>Favorite Movie/Song:</strong></td>
//                 <td>$favorite_movie_or_song</td>
//             </tr>
//             <tr>
//                 <td><strong>Photo Style Preference:</strong></td>
//                 <td>$look_preference</td>
//             </tr>
//             <tr>
//                 <td><strong>Instagram Handle:</strong></td>
//                 <td>$instagram_handle</td>
//             </tr>
//             <tr>
//                 <td><strong>How did you hear about us?</strong></td>
//                 <td>$hear_about_us</td>
//             </tr>
//             <tr>
//                 <td><strong>Service Interested In:</strong></td>
//                 <td>$service_interest</td>
//             </tr>
//             <tr>
//                 <td><strong>Additional Details:</strong></td>
//                 <td>$additional_details</td>
//             </tr>
          
//         </table>
//         <p>We look forward to working with you and making your special day unforgettable!</p>
//         <p>Best regards,<br>Your Wedding Team</p>
//         </div>
//         <style>
        
//       .container {
//         max-width: 600px;
//         margin: 20px auto;
//         background-color: #fff;
//         border-radius: 8px;
//         box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
//         padding: 20px;
//     }
//     h2 {
//         color: #000;
//         text-align: center;
//     }
//     p {
//         font-size: 16px;
//         color: #555;
//     }
//     table {
//         width: 100%;
//         border-collapse: collapse;
//         margin-top: 20px;
//     }
//     table td {
//         padding: 10px;
//         border-bottom: 1px solid #eee;
//         word-wrap: break-word; /* Esto hará que el texto se ajuste dentro de las celdas */
//         word-break: break-word; /* Alternativamente, esto permite dividir el texto en cualquier parte */
//     }
//     table td:first-child {
//         font-weight: bold;
//         color: #000;
//         width: 40%;
//     }
//     .footer {
//         text-align: center;
//         margin-top: 20px;
//         font-size: 14px;
//         color: #888;
//     }  
//         </style>
//     </body>
//     </html>
//     ";

//     // Enviar el correo
//     if (mail($to, $subject, $message, $headers)) {
//         $response['email_sent'] = true;
//     } else {
//         $response['email_sent'] = false;
//     }
// } else {
//     $response['success'] = false;
// }

// // Correo de confirmación para el cliente
// $subject_client = "Wedding Inquiry Confirmation - Thank You for Your Submission";
// $to_client = $email_address; // Correo del cliente

// // Cuerpo del correo para el cliente
// $message_client = "
// <html>
// <head>
//     <title>Wedding Inquiry Confirmation</title>
// </head>
// <body>
//     <h2>Thank you for submitting your wedding inquiry, $names!</h2>
//     <p>We have received your wedding details, and our team will get in touch with you soon. Here is a summary of your submission:</p>
//     <ul>
//         <li><strong>Your Names:</strong> $names</li>
//         <li><strong>Wedding Date:</strong> $wedding_date</li>
//         <li><strong>Wedding Location:</strong> $wedding_location</li>
//         <li><strong>Guests Count:</strong> $guests_count</li>
//         <li><strong>Service Interested In:</strong> $service_interest</li>
//         <li><strong>Vendedor:</strong> $nombrevendedor</li>
//     </ul>
//     <p><strong>We are excited to help you make your special day unforgettable. <p style='font-size: 20px;'><strong>To proceed with your quotation, please schedule an appointment with one of our team members using our calendar link: https://calendar.app.google/iycBoL79EuUswbDSA<br>

// Best regards,<br>Your Wedding Team</strong></p>
// </body>
// </html>
// ";

// // Enviar correo de confirmación al cliente
// $headers_client = "MIME-Version: 1.0" . "\r\n";
// $headers_client .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
// $headers_client .= "From: " . $from . "\r\n";

// if (mail($to_client, $subject_client, $message_client, $headers_client)) {
//     $response['email_sent_client'] = true;
// } else {
//     $response['email_sent_client'] = false;
// }

// Cerrar conexión y enviar respuesta JSON
$stmt->close();
$conn->close();


header('Content-Type: application/json');
echo json_encode($response);
?>
