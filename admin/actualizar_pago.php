<?php
// Habilitar la visualización de errores
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

// Incluir el archivo de conexión a la base de datos
include "conn.php";

// Cargar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require "./PHPMailer/src/Exception.php";
require "./PHPMailer/src/PHPMailer.php";
require "./PHPMailer/src/SMTP.php";

// Función para ejecutar consultas y obtener los resultados
function obtenerDatos($conn, $tabla)
{
    $sql = "SELECT * FROM $tabla";
    $result = $conn->query($sql);

    if (!$result) {
        die("Error en la consulta: " . $conn->error);
    }

    return $result->num_rows > 0 ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Obtener datos de las tablas
$usuarios = obtenerDatos($conn, "usuarios");
$contact_form = obtenerDatos($conn, "contact_form");
$txt_correo = obtenerDatos($conn, "txtCorreo");

// Buscar administradores
$admins = [];
foreach ($usuarios as $usuario) {
    if ($usuario["tipoUsu"] == 0) {
        $admins[] = $usuario;
    }
}

// Indexar usuarios y contactos por ID
$usuarios_indexados = [];
foreach ($usuarios as $usuario) {
    $usuarios_indexados[$usuario["id"]] = $usuario;
}

$contact_form_indexados = [];
foreach ($contact_form as $contact) {
    $contact_form_indexados[$contact["id"]] = $contact;
}

// Función para enviar el correo
function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida)
{
    $mail = new PHPMailer(true);
    $mensaje = "
    <html>
    <head>
      <title>$asunto</title>
      <style>
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

    $mail_enviado = false;
    $correoRemitente = "info@efegepho.com.mx";
    $nombreRemitente = "InfoEfegepho";

    try {
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = "starttls";
        $mail->Port = 587;
        $mail->Host = "smtp.gmail.com";
        $mail->Username = $correoRemitente;
        $mail->Password = "glhewzgjzdnsbuvj";

        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->addAddress($correo_destino, "Cliente");
        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;

        $mail_enviado = $mail->send();
        return $mail_enviado;
    } catch (Exception $e) {
        error_log("Error al enviar correo: " . $e->getMessage());
        return false;
    }
}

// Verificar si se ha enviado el idPago y el estatus
if (
    isset($_POST["idPago"]) &&
    isset($_POST["estatus"]) &&
    isset($_POST["forma_pago"])
) {
    $idPago = $_POST["idPago"];
    $estatus = $_POST["estatus"];
    $forma_pago = $_POST["forma_pago"];

    // Iniciar transacción
    $conn->begin_transaction();
    $mail_enviado_customer = false;
    $mail_enviado_admin = false;

    try {
        // 1. Actualizar el pago
        $query = "UPDATE pagos SET 
                    estatus = ?, 
                    forma_pago = ?,
                    fecha_pago = CURDATE(), 
                    hora_pago = CURTIME() 
                  WHERE id = ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception(
                "Error al preparar la consulta SQL: " . $conn->error
            );
        }

        $stmt->bind_param("iii", $estatus, $forma_pago, $idPago);
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar el pago: " . $stmt->error);
        }

        // 2. Obtener datos del pago y cliente
        $sqlQuery = "SELECT p.*, c.email_address, c.names, c.country_code 
                     FROM pagos p
                     JOIN contact_form c ON p.id_clie = c.id
                     WHERE p.id = ?";

        $stmt2 = $conn->prepare($sqlQuery);
        if (!$stmt2) {
            throw new Exception(
                "Error al preparar la consulta SQL: " . $conn->error
            );
        }

        $stmt2->bind_param("i", $idPago);
        if (!$stmt2->execute()) {
            throw new Exception(
                "Error al obtener datos del pago: " . $stmt2->error
            );
        }

        $result = $stmt2->get_result();
        if ($result->num_rows == 0) {
            throw new Exception(
                "No se encontró el pago con el ID proporcionado."
            );
        }

        $registroPago = $result->fetch_assoc();
        $idcliente = $registroPago["id_clie"];
        $customer = $registroPago["names"];

        // 3. Si el pago es aprobado, insertar en nextstep_table
        if ($estatus == 1) {
            $checkQuery =
                "SELECT COUNT(*) FROM nextstep_table WHERE id_cliente = ?";
            $stmtCheck = $conn->prepare($checkQuery);
            if (!$stmtCheck) {
                throw new Exception(
                    "Error al preparar la consulta de verificación: " .
                        $conn->error
                );
            }

            $stmtCheck->bind_param("i", $idcliente);
            $stmtCheck->execute();
            $stmtCheck->bind_result($count);
            $stmtCheck->fetch();
            $stmtCheck->close();

            // Si no existe, hacer el INSERT
            if ($count == 0) {
                $insertQuery = "INSERT INTO nextstep_table 
                    (id_cliente, fecha_registro, hora_registro, documento, nombre_archivo) 
                    VALUES (?, CURDATE(), NOW(), 'NEXTSTEPSEFEGE.pdf', '../uploads_nextstep/NEXTSTEPSEFEGE.pdf')";

                $stmt3 = $conn->prepare($insertQuery);
                if (!$stmt3) {
                    throw new Exception(
                        "Error al preparar la inserción: " . $conn->error
                    );
                }

                $stmt3->bind_param("i", $idcliente);
                if (!$stmt3->execute()) {
                    throw new Exception(
                        "Error al insertar en nextstep_table: " . $stmt3->error
                    );
                } else {
                    //mandar correo nextstep
                    // Obtener los datos del cliente desde contact_form
                    $sqlCliente =
                        "SELECT email_address, names, country_code FROM contact_form WHERE id = ?";
                    $stmtCliente = $conn->prepare($sqlCliente);
                    if (!$stmtCliente) {
                        throw new Exception(
                            "Error al preparar la consulta del cliente para nextstep: " .
                                $conn->error
                        );
                    }

                    $stmtCliente->bind_param("i", $idcliente);
                    $stmtCliente->execute();
                    $resultCliente = $stmtCliente->get_result();

                    if ($resultCliente->num_rows == 0) {
                        throw new Exception(
                            "No se encontró el cliente con ID: " . $idcliente
                        );
                    }

                    $cliente = $resultCliente->fetch_assoc();
                    $stmtCliente->close();
                    $indexCorreo = 21; // Forzar correo en inglés
                    $nextstepruta =
                        "https://citas.efegepho.com.mx/uploads_nextstep/NEXTSTEPSEFEGE.pdf";
                    $link_next =
                        "<a href='" .
                        $nextstepruta .
                        "'>Download PDF File</a>";
                    // Ahora sí, usar esos datos para armar el correo
                    $titulo_cliente =
                        $txt_correo[$indexCorreo]["txttituloclie"];
                    $asunto_cliente = $titulo_cliente;

                    $titulo_cliente = str_replace(
                        ['$customer'],
                        [$cliente["names"]],
                        $titulo_cliente
                    );
                    $txt_cliente = $txt_correo[$indexCorreo]["txtcli"];
                    $txt_cliente = str_replace(
                        ['$customer'],
                        [$cliente["names"]],
                        $txt_cliente
                    );
                    $txt_cliente = str_replace(
                        ['$linkNextstep'],
                        [$link_next],
                        $txt_cliente
                    );

                    $titulo_cliente_despedida =
                        $txt_correo[$indexCorreo]["txtdespclie"];
                    $titulo_cliente_despedida = str_replace(
                        ['$customer'],
                        [$cliente["names"]],
                        $titulo_cliente_despedida
                    );

                    $mail_enviado = enviarCorreo(
                        $cliente["email_address"],
                        $asunto_cliente,
                        $titulo_cliente,
                        $txt_cliente,
                        $titulo_cliente_despedida
                    );
                    if ($mail_enviado) {
                        echo json_encode([
                            "success" => true,
                            "message" =>
                                "Pago actualizado y notificaciones enviadas",
                        ]);
                    } else {
                        echo json_encode([
                            "success" => true,
                            "message" =>
                                "Pago actualizado pero hubo problemas con las notificaciones",
                        ]);
                    }
                }
            }
        }
        
        
    function reenviarCorreoNextStep($conn, $idcliente, $txt_correo) {
    // Obtener los datos del cliente desde contact_form
    $sqlCliente = "SELECT email_address, names, country_code FROM contact_form WHERE id = ?";
    $stmtCliente = $conn->prepare($sqlCliente);
    if (!$stmtCliente) {
        throw new Exception("Error al preparar la consulta del cliente para nextstep: " . $conn->error);
    }

    $stmtCliente->bind_param("i", $idcliente);
    $stmtCliente->execute();
    $resultCliente = $stmtCliente->get_result();

    if ($resultCliente->num_rows == 0) {
        throw new Exception("No se encontró el cliente con ID: " . $idcliente);
    }

    $cliente = $resultCliente->fetch_assoc();
    $stmtCliente->close();

    $nextstepruta = "https://citas.efegepho.com.mx/uploads_nextstep/NEXTSTEPSEFEGE.pdf";
    $link_next = "<a href='" . $nextstepruta . "'>Download PDF File</a>";

    // Determinar el índice correcto del contenido del correo
    $indexCorreo = 21; // Forzar correo en inglés

    // Preparar datos del correo
    $titulo_cliente = str_replace(['$customer'], [$cliente["names"]], $txt_correo[$indexCorreo]["txttituloclie"]);
    $asunto_cliente = $titulo_cliente;

    $txt_cliente = str_replace(['$customer', '$linkNextstep'], [$cliente["names"], $link_next], $txt_correo[$indexCorreo]["txtcli"]);
    $titulo_cliente_despedida = str_replace(['$customer'], [$cliente["names"]], $txt_correo[$indexCorreo]["txtdespclie"]);

    // Enviar correo
    $mail_enviado = enviarCorreo(
        $cliente["email_address"],
        $asunto_cliente,
        $titulo_cliente,
        $txt_cliente,
        $titulo_cliente_despedida
    );

    return $mail_enviado;
}


        // 4. Enviar correos electrónicos
        $indexCorreo = 15; // Forzar correo en inglés
        $amount =
            "$" .
            number_format($registroPago["monto"], 2, ".", ",") .
            " " .
            strtoupper($registroPago["currency"]);

        switch ($forma_pago) {
            case 1:
                $payment_method = "Tarjeta";
                break;
            case 3:
                $payment_method = "Transferencia";
                break;
            default:
                $payment_method = "Efectivo";
                break;
        }

        // Correo al cliente
        $asunto_cliente =
            $indexCorreo == 14 ? "Pago exitoso" : "Successful payment";
        $titulo_cliente = $txt_correo[$indexCorreo]["txttituloclie"];
        $titulo_cliente = str_replace(
            ['$customer', '$payment_method', '$amount'],
            [$customer, $payment_method, $amount],
            $titulo_cliente
        );

        $cuerpo_cliente = $txt_correo[$indexCorreo]["txtcli"];
        $cuerpo_cliente = str_replace(
            ['$customer', '$payment_method', '$amount'],
            [$customer, $payment_method, $amount],
            $cuerpo_cliente
        );

        $despedida_cliente = $txt_correo[$indexCorreo]["txtdespclie"];
        $despedida_cliente = str_replace(
            ['$customer', '$payment_method', '$amount'],
            [$customer, $payment_method, $amount],
            $despedida_cliente
        );

        $mail_enviado_customer = enviarCorreo(
            $registroPago["email_address"],
            $asunto_cliente,
            $titulo_cliente,
            $cuerpo_cliente,
            $despedida_cliente
        );

        // Correo a administradores
        $asunto_admin = $asunto_cliente;
        $titulo_admin = $txt_correo[$indexCorreo]["txttituloadmin"];
        $titulo_admin = str_replace(
            ['$customer', '$payment_method', '$amount'],
            [$customer, $payment_method, $amount],
            $titulo_admin
        );

        $cuerpo_admin = $txt_correo[$indexCorreo]["txtadmin"];
        $cuerpo_admin = str_replace(
            ['$customer', '$payment_method', '$amount'],
            [$customer, $payment_method, $amount],
            $cuerpo_admin
        );

        $despedida_admin = $txt_correo[$indexCorreo]["txtdespadmin"];
        $despedida_admin = str_replace(
            ['$customer', '$payment_method', '$amount'],
            [$customer, $payment_method, $amount],
            $despedida_admin
        );

        foreach ($admins as $admin) {
            $mail_enviado_admin = enviarCorreo(
                $admin["correo"],
                $asunto_admin,
                $titulo_admin,
                $cuerpo_admin,
                $despedida_admin
            );
            if (!$mail_enviado_admin) {
                error_log(
                    "Error al enviar correo al administrador: " .
                        $admin["correo"]
                );
            }
        }

        // Confirmar transacción si todo salió bien
        $conn->commit();

        // Responder al cliente
        if ($mail_enviado_customer && $mail_enviado_admin) {
            echo json_encode([
                "success" => true,
                "message" => "Pago actualizado y notificaciones enviadas",
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "message" =>
                    "Pago actualizado pero hubo problemas con las notificaciones",
            ]);
        }
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        error_log("Error en el proceso: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    } finally {
        // Cerrar conexiones
        if (isset($stmt)) {
            $stmt->close();
        }
        if (isset($stmt2)) {
            $stmt2->close();
        }
        if (isset($stmt3)) {
            $stmt3->close();
        }
        $conn->close();
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Parámetros no enviados correctamente.",
    ]);
}
