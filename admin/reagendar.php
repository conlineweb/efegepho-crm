<?php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'conn.php';
require_once __DIR__ . '/scheduling_helpers.php';
require_once __DIR__ . '/calendario_estatus_historial_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$usuarioReagenda = isset($_SESSION['uid']) && $_SESSION['uid'] !== '' ? intval($_SESSION['uid']) : null;

// Cargar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';

function sendJsonResponse(array $payload, int $statusCode = 200): void {
    if (ob_get_length() > 0) {
        ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

function obtenerDatos($conn, $tabla) {
    $sql = "SELECT * FROM $tabla";
    $result = $conn->query($sql);
    
    // Verificar si la consulta se ejecutó correctamente
    if (!$result) {
        die("Error en la consulta: " . $conn->error);
    }

    return ($result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
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
    // return mail($correo_destino, $asunto, $mensaje, $headers);
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
        return $mail->send();
    } catch (Exception $e) {
        error_log('Error al enviar correo en reagendar.php: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo);
        return false;
    }
}

// Verificar si los datos han sido enviados
if (isset($_POST['id']) && isset($_POST['cliente']) && isset($_POST['vendedor']) && isset($_POST['fecha']) && isset($_POST['hora'])) {
    // Obtener los datos enviados desde el frontend
    $id = $_POST['id'];
    $vendedor = $_POST['vendedor'];
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $cliente = $_POST['cliente'];
    $hasSeller= $_POST['hasSeller'];
    $estatus = 0;
    
    // Obtener fecha y hora del cliente (si no se proporcionan, usar las del vendedor)
    $fecha_cliente = isset($_POST['fecha_cliente']) && $_POST['fecha_cliente'] != '' ? $_POST['fecha_cliente'] : $fecha;
    $hora_cliente = isset($_POST['hora_cliente']) && $_POST['hora_cliente'] != '' ? $_POST['hora_cliente'] : $hora;

    $vendorId = (int)$vendedor;
    $calendarId = (int)$id;
    $normalizedVendorTime = normalizeAppointmentTime($hora);

    if ($vendorId <= 0 || $fecha === '' || $normalizedVendorTime === '') {
        sendJsonResponse(['status' => 'error', 'message' => 'La fecha, hora o vendedor no son validos.'], 400);
    }

    if (!vendorHasActivatedSlot($conn, $vendorId, $fecha, $normalizedVendorTime)) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'La vendedora no tiene activo ese horario para la fecha seleccionada. Activalo en Horarios o elige otro horario.'
        ], 400);
    }

    if (isVendorSlotOccupied($conn, $vendorId, $fecha, $normalizedVendorTime, $calendarId)) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Ese horario ya esta ocupado para la vendedora seleccionada. Elige otro horario disponible.'
        ], 400);
    }

    $hora = strlen($hora) === 5 ? $hora . ':00' : $hora;
    if ($hora_cliente !== '' && strlen($hora_cliente) === 5) {
        $hora_cliente = $hora_cliente . ':00';
    }

    // Capturar el estatus anterior ANTES de sobreescribirlo, para el historial
    $estatusAnteriorReagenda = null;
    if ($stmtPrevRe = $conn->prepare("SELECT estatus FROM calendario WHERE id = ? LIMIT 1")) {
        $stmtPrevRe->bind_param("i", $id);
        if ($stmtPrevRe->execute()) {
            $resPrevRe = $stmtPrevRe->get_result();
            if ($resPrevRe && ($rowPrevRe = $resPrevRe->fetch_assoc())) {
                $estatusAnteriorReagenda = intval($rowPrevRe['estatus']);
            }
        }
        $stmtPrevRe->close();
    }

    // Preparar la consulta SQL para actualizar incluyendo fecha_cliente y hora_cliente
    $sql = "UPDATE calendario SET idusu = ?, fecha = ?, fecha_cliente = ?, hora = ?, hora_cliente = ?, estatus = ? WHERE id = ?";

    // Usar una sentencia preparada para evitar inyecciones SQL
    if ($stmt = $conn->prepare($sql)) {
        // Enlazar los parámetros a la consulta preparada
        $stmt->bind_param("sssssii", $vendedor, $fecha, $fecha_cliente, $hora, $hora_cliente, $estatus, $id);

        // Ejecutar la consulta
        if ($stmt->execute()) {
            // Registrar la transición a "agendado" (0) por reagenda en el historial
            registrarCambioEstatusCalendario($conn, $id, 0, [
                'estatus_anterior' => $estatusAnteriorReagenda,
                'usuario'          => $usuarioReagenda,
                'origen'           => 'reagendar',
                'observaciones'    => 'Reagenda de cita',
            ]);

            //si hasSeller es diferente a  0 significa que ya tiene vendedor y se esta reagendando
           
         
                
                
                 // Consultas conjuntas para obtener usuarios admins, vendedor y cliente para mandar correo de reagenda
                 $txt_correo = obtenerDatos($conn, 'txtCorreo');
            $query = "
                SELECT u.*, c.*, v.* 
                FROM usuarios u 
                LEFT JOIN contact_form c ON c.id = ? 
                LEFT JOIN usuarios v ON v.id = ? 
                WHERE u.tipoUsu = 0
            ";
            if ($adminStmt = $conn->prepare($query)) {
                // Enlazar parámetros
                $adminStmt->bind_param("ii", $cliente, $vendedor);

                // Ejecutar la consulta
                if ($adminStmt->execute()) {
                    $result = $adminStmt->get_result();
                    $admins = [];
                    $vendedorDetails = null;
                    $clienteDetails = null;

                    while ($row = $result->fetch_assoc()) {
                        if (empty($vendedorDetails)) {
                            $vendedorDetails = [
                                'id' => $row['id'],
                                'nombre' => $row['nombre'],
                                'email' => $row['correo'],
                                'enlace_meet'  => $row['enlace_meet'],
                            ];
                        }

                        if (empty($clienteDetails)) {
                            $clienteDetails = [
                                'id' => $row['id'],
                                'nombre' => $row['names'],
                                'email' => $row['email_address'],
                                'zona_horaria' => $row['zona_horaria']
                            ];
                        }

                        $admins[] = [
                            'id' => $row['id'],
                            'nombre' => $row['nombre'],
                            'email' => $row['correo']
                        ];
                    }

                    $indexCorreo = 4;
                   if (is_array($cliente) && isset($cliente['country_code'])) {
                        $indexCorreo = ($cliente['country_code'] == '+52') ? 1 : 4;
                    } 
                     $indexCorreo = 4;
                    $asunto = ($indexCorreo == 1)? "Tu cita ha sido reagendada." :"Your appointment has been rescheduled.";
                    
                    
                    
                    // Variables
                    $customer = $clienteDetails['nombre'];  // Nombre del cliente
                    $meet = "<a href='" . $vendedorDetails['enlace_meet'] . "'>" . $vendedorDetails['enlace_meet'] . "</a>";
                    $seller = $vendedorDetails['nombre'];
                    $fechaHora = $fecha_cliente . ' ' . $hora_cliente;
                     $formattedDate = null;
                   $dateObject = new DateTime($fechaHora);

                // Arreglos de traducción para días y meses
                $dias_es = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
                $meses_es = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                
                $dias_en = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $meses_en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                
                if ($indexCorreo == 1) {
                    // Formateo en español
                    $dia = $dias_es[$dateObject->format('w')]; // Día de la semana en español
                    $mes = $meses_es[$dateObject->format('n') - 1]; // Mes en español
                    $formattedDate = $dia . ' ' . $dateObject->format('d') . ' de ' . $mes . ' del ' . $dateObject->format('Y') . ' a las ' . $dateObject->format('h:i A');
                } else {
                    // Formateo en inglés
                    $dia = $dias_en[$dateObject->format('w')]; // Día de la semana en inglés
                    $mes = $meses_en[$dateObject->format('n') - 1]; // Mes en inglés
                    $formattedDate = $dateObject->format('d') . ' ' . $mes . ' ' . $dateObject->format('Y') . ' at ' . $dateObject->format('h:i A');
                }
                                    
                    
                    $resultadosCorreo = [
                        'cliente' => false,
                        'admin' => false,
                        'vendedor' => false,
                    ];

                    for ($i = 0; $i < 3; $i++) {
                        if ($i == 0) {
                            // Enviar correo al cliente
                            $titulo = $txt_correo[$indexCorreo]['txttituloclie'];
                            $titulo = str_replace('$customer', $customer, $titulo);
                            $titulo = str_replace('$meet', $meet, $titulo);
                            $titulo = str_replace('$date', $formattedDate, $titulo);
                            $titulo = str_replace('$seller', $seller, $titulo);
                            $asunto = $titulo;
                            $cuerpo = $txt_correo[$indexCorreo]['txtcli'];
                            $cuerpo = str_replace('$customer', $customer, $cuerpo);
                            $cuerpo = str_replace('$meet', $meet, $cuerpo);
                            $cuerpo = str_replace('$date', $formattedDate, $cuerpo);
                            $cuerpo = str_replace('$seller', $seller, $cuerpo);

                            $despedida = $txt_correo[$indexCorreo]['txtdespclie'];
                            $despedida = str_replace('$customer', $customer, $despedida);
                            $despedida = str_replace('$meet', $meet, $despedida);
                            $despedida = str_replace('$date', $formattedDate, $despedida);
                            $despedida = str_replace('$seller', $seller, $despedida);

                            $resultadosCorreo['cliente'] = enviarCorreo($clienteDetails['email'], $asunto, $titulo, $cuerpo, $despedida, "cliente");
                        }

                        if ($i == 1) {
                            // Enviar correo a todos los administradores
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
                                $resultadosCorreo['admin'] = enviarCorreo($admin['email'], $asunto, $titulo, $cuerpo, $despedida, "admin");
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

                            $resultadosCorreo['vendedor'] = enviarCorreo($vendedorDetails['email'], $asunto, $titulo, $cuerpo, $despedida, "vendedor");
                        }
                    }
                    
                    // Verificar si todos los correos se enviaron correctamente
                    if ($resultadosCorreo['cliente'] && $resultadosCorreo['admin'] && $resultadosCorreo['vendedor']) {
                        sendJsonResponse([
                            'status' => 'success',
                            'message' => 'Tu cita se ha reprogramado existosamente.',
                            'data' => [
                                'cliente' => $clienteDetails['email'],
                                'vendedor' => $vendedorDetails['email'],
                                'administradores' => array_column($admins, 'email')
                            ]
                        ]);
                    } else {
                        sendJsonResponse([
                            'status' => 'success',
                            'message' => 'La cita se actualizo correctamente, pero no se pudieron enviar todos los correos de notificacion.',
                            'email_warnings' => $resultadosCorreo
                        ]);
                    }
                    
                } else {
                    sendJsonResponse(['status' => 'error', 'message' => 'Error al ejecutar la consulta de admins, vendedor o cliente: ' . $adminStmt->error], 500);
                }
                $adminStmt->close();
            } else {
                sendJsonResponse(['status' => 'error', 'message' => 'Error al preparar la consulta conjunta: ' . $conn->error], 500);
            }
           
           
        } else {
            sendJsonResponse(['status' => 'error', 'message' => 'Error al ejecutar la consulta: ' . $stmt->error], 500);
        }

        // Cerrar la sentencia
        $stmt->close();
    } else {
        sendJsonResponse(['status' => 'error', 'message' => 'Error al preparar la consulta: ' . $conn->error], 500);
    }
} else {
    sendJsonResponse(['status' => 'error', 'message' => 'Faltan datos en la solicitud'], 400);
}

// Cerrar la conexión
$conn->close();
?>
