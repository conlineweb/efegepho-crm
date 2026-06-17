<?php
// Conexión a la base de datos
$servername = "localhost";
$username = "efegephocom_sistema";
$password = "t)sXFfUEN13k";
$dbname = "efegephocom_sistema";

$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Obtener los datos del formulario
$names = $_POST['names'];
$telephone = $_POST['telephone'];
$email_address = $_POST['email'];
$wedding_date = $_POST['wedding_date'];
$wedding_location = $_POST['wedding_location'];
$wedding_planner = $_POST['wedding_planner'];
$guests_count = $_POST['guests'];
$how_did_you_meet = $_POST['meet'];
$couple_activities = $_POST['couple_activities'];
$favorite_movie_or_song = $_POST['favorite_movie_song'];
$look_preference = $_POST['photo_style'];
$hear_about_us = $_POST['how_did_hear'];
$instagram_handle = $_POST['instagram'];
$service_interest = $_POST['service'];
$additional_details = $_POST['details'];
$submission_date = date("Y-m-d H:i:s");
$country_code = $_POST['country_code'];
// Preparar la declaración SQL
$sql = "INSERT INTO contact_form_submissions (names, telephone, email_address, wedding_date, wedding_location, wedding_planner, guests_count, how_did_you_meet, couple_activities, favorite_movie_or_song, look_preference, instagram_handle, hear_about_us, service_interest, additional_details, submission_date) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssisssssssss", $names, $telephone, $email_address, $wedding_date, $wedding_location, $wedding_planner, $guests_count, $how_did_you_meet, $couple_activities, $favorite_movie_or_song, $look_preference, $instagram_handle, $hear_about_us, $service_interest, $additional_details, $submission_date);

// Ejecutar la declaración y enviar la respuesta
$response = [];
if ($stmt->execute()) {
    $response['success'] = true;
    
    // Enviar correo de confirmación
    $subject = "Wedding Inquiry - Confirmation";
    $to = "solicitudes@efegepho.com.mx"; // Correo del usuario
    $from = "solicitudes@efegepho.com.mx"; // Correo desde el cual se enviará el mensaje
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: " . $from . "\r\n";

    // Plantilla HTML para el correo
    $message = "
    <html>
    <head>
        <title>Wedding Inquiry - Confirmation</title>
    </head>
    <body>
    <div class='container'>
        <h2>Website form submission record. $names!</h2>
        <p>You have received the wedding details from the website. Below are the details submitted:</p>
        
        <div class='container' background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); overflow: hidden;'>
        <div class='header' style='background-color: #EEE8DC; padding: 20px; text-align: center;'>
            <h2 style='color: #000; margin: 0;'>You have a record of $names!</h2>
        </div>
        <table>
            <tr>
                <td><strong>Your Names:</strong></td>
                <td>$names</td>
            </tr>
            <tr>
                <td><strong>Telephone:</strong></td>
                <td> $country_code $telephone</td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td>$email_address</td>
            </tr>
            <tr>
                <td><strong>Wedding Date:</strong></td>
                <td>$wedding_date</td>
            </tr>
            <tr>
                <td><strong>Wedding Location:</strong></td>
                <td>$wedding_location</td>
            </tr>
            <tr>
                <td><strong>Wedding Planner:</strong></td>
                <td>$wedding_planner</td>
            </tr>
            <tr>
                <td><strong>Guests Count:</strong></td>
                <td>$guests_count</td>
            </tr>
            <tr>
                <td><strong>How did you meet?</strong></td>
                <td>$how_did_you_meet</td>
            </tr>
            <tr>
                <td><strong>Couple Activities:</strong></td>
                <td>$couple_activities</td>
            </tr>
            <tr>
                <td><strong>Favorite Movie/Song:</strong></td>
                <td>$favorite_movie_or_song</td>
            </tr>
            <tr>
                <td><strong>Photo Style Preference:</strong></td>
                <td>$look_preference</td>
            </tr>
            <tr>
                <td><strong>Instagram Handle:</strong></td>
                <td>$instagram_handle</td>
            </tr>
            <tr>
                <td><strong>How did you hear about us?</strong></td>
                <td>$hear_about_us</td>
            </tr>
            <tr>
                <td><strong>Service Interested In:</strong></td>
                <td>$service_interest</td>
            </tr>
            <tr>
                <td><strong>Additional Details:</strong></td>
                <td>$additional_details</td>
            </tr>
        </table>
        <p>We look forward to working with you and making your special day unforgettable!</p>
        <p>Best regards,<br>Your Wedding Team</p>
        </div>
        <style>
        
      .container {
        max-width: 600px;
        margin: 20px auto;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        padding: 20px;
    }
    h2 {
        color: #000;
        text-align: center;
    }
    p {
        font-size: 16px;
        color: #555;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    table td {
        padding: 10px;
        border-bottom: 1px solid #eee;
        word-wrap: break-word; /* Esto hará que el texto se ajuste dentro de las celdas */
        word-break: break-word; /* Alternativamente, esto permite dividir el texto en cualquier parte */
    }
    table td:first-child {
        font-weight: bold;
        color: #000;
        width: 40%;
    }
    .footer {
        text-align: center;
        margin-top: 20px;
        font-size: 14px;
        color: #888;
    }  
        </style>
    </body>
    </html>
    ";

    // Enviar el correo
    if (mail($to, $subject, $message, $headers)) {
        $response['email_sent'] = true;
    } else {
        $response['email_sent'] = false;
    }
} else {
    $response['success'] = false;
}

// Correo de confirmación para el cliente
$subject_client = "Wedding Inquiry Confirmation - Thank You for Your Submission";
$to_client = $email_address; // Correo del cliente

// Cuerpo del correo para el cliente
$message_client = "
<html>
<head>
    <title>Wedding Inquiry Confirmation</title>
</head>
<body>
    <h2>Thank you for submitting your wedding inquiry, $names!</h2>
    <p>We have received your wedding details, and our team will get in touch with you soon. Here is a summary of your submission:</p>
    <ul>
        <li><strong>Your Names:</strong> $names</li>
        <li><strong>Wedding Date:</strong> $wedding_date</li>
        <li><strong>Wedding Location:</strong> $wedding_location</li>
        <li><strong>Guests Count:</strong> $guests_count</li>
        <li><strong>Service Interested In:</strong> $service_interest</li>
    </ul>
    <p><strong>We are excited to help you make your special day unforgettable. <p style='font-size: 20px;'><strong>To proceed with your quotation, please schedule an appointment with one of our team members using our calendar link: https://calendar.app.google/iycBoL79EuUswbDSA<br>

Best regards,<br>Your Wedding Team</strong></p>
</body>
</html>
";

// Enviar correo de confirmación al cliente
$headers_client = "MIME-Version: 1.0" . "\r\n";
$headers_client .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
$headers_client .= "From: " . $from . "\r\n";

if (mail($to_client, $subject_client, $message_client, $headers_client)) {
    $response['email_sent_client'] = true;
} else {
    $response['email_sent_client'] = false;
}

// Cerrar conexión y enviar respuesta JSON
$stmt->close();
$conn->close();


header('Content-Type: application/json');
echo json_encode($response);
?>
