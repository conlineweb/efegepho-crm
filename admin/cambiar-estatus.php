<?php
// Incluir conexión
include 'conn.php';
require_once __DIR__ . '/calendario_estatus_historial_helper.php';

// Asegurar sesión para identificar al usuario que ejecuta el cambio (uid)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$usuarioCambioEstatus = isset($_SESSION['uid']) && $_SESSION['uid'] !== '' ? intval($_SESSION['uid']) : null;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';

function ensureColumnExists($conn, $table, $columnName, $columnDef)
{
  $safeTable = $conn->real_escape_string($table);
  $safeColumn = $conn->real_escape_string($columnName);
  $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safeTable}' AND COLUMN_NAME = '{$safeColumn}'";
  $res = $conn->query($checkSql);
  if ($res) {
    $row = $res->fetch_assoc();
    if (intval($row['c'] ?? 0) === 0) {
      $conn->query("ALTER TABLE `{$table}` ADD COLUMN {$columnDef}");
    }
  }
}

function ensureLeadInteractionsTable($conn)
{
  $sql = "CREATE TABLE IF NOT EXISTS `lead_interactions` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `tabla_origen` VARCHAR(120) NOT NULL,
        `lead_id` INT NOT NULL,
        `original_lead_id` INT DEFAULT NULL,
        `interaction_type` VARCHAR(40) DEFAULT NULL,
        `interaction_date` DATE DEFAULT NULL,
        `interaction_time` VARCHAR(20) DEFAULT NULL,
        `notes` TEXT,
        `outcome` VARCHAR(40) DEFAULT NULL,
        `next_action` VARCHAR(255) DEFAULT NULL,
        `next_action_date` DATE DEFAULT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_lead_interactions_lead` (`tabla_origen`, `lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
  $conn->query($sql);

  $checkOriginalLeadId = $conn->query("SHOW COLUMNS FROM `lead_interactions` LIKE 'original_lead_id'");
  if ($checkOriginalLeadId && $checkOriginalLeadId->num_rows === 0) {
    $conn->query("ALTER TABLE `lead_interactions` ADD COLUMN `original_lead_id` INT DEFAULT NULL AFTER `lead_id`");
  }
}

function guardarMotivoRechazoComoInteraccion($conn, $idclie, $comentario, $createdBy)
{
  $comentario = trim((string) $comentario);
  $idclie = intval($idclie);
  if ($comentario === '' || $idclie <= 0) {
    return;
  }

  ensureLeadInteractionsTable($conn);

  $targets = [];
  $seen = [];
  $addTarget = function ($tablaOrigen, $leadId, $originalLeadId) use (&$targets, &$seen) {
    $tablaOrigen = trim((string) $tablaOrigen);
    $leadId = intval($leadId);
    $originalLeadId = intval($originalLeadId);
    if ($tablaOrigen === '' || $leadId <= 0) {
      return;
    }
    $key = strtolower($tablaOrigen) . ':' . $leadId;
    if (isset($seen[$key])) {
      return;
    }
    $seen[$key] = true;
    $targets[] = [
      'tabla_origen' => $tablaOrigen,
      'lead_id' => $leadId,
      'original_lead_id' => $originalLeadId > 0 ? $originalLeadId : $leadId,
    ];
  };

  $addTarget('contact_form', $idclie, $idclie);

  if ($cfStmt = $conn->prepare("SELECT tabla_origen, original_lead_id FROM contact_form WHERE id = ? LIMIT 1")) {
    $cfStmt->bind_param('i', $idclie);
    if ($cfStmt->execute()) {
      $res = $cfStmt->get_result();
      if ($res && ($row = $res->fetch_assoc())) {
        $origTabla = trim((string) ($row['tabla_origen'] ?? ''));
        $origLeadId = intval($row['original_lead_id'] ?? 0);
        if ($origTabla !== '' && strtolower($origTabla) !== 'contact_form' && $origLeadId > 0) {
          $addTarget($origTabla, $origLeadId, $origLeadId);
        }
      }
    }
    $cfStmt->close();
  }

  $interactionType = 'Nota interna';
  $outcome = 'Sin respuesta';
  $interactionDate = date('Y-m-d');
  $interactionTime = date('H:i:s');
  $nextAction = '';
  $nextActionDate = null;

  $stmt = $conn->prepare("INSERT INTO lead_interactions
        (tabla_origen, lead_id, original_lead_id, interaction_type, interaction_date, interaction_time, notes, outcome, next_action, next_action_date, created_by)
        VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)");
  if (!$stmt) {
    return;
  }

  foreach ($targets as $target) {
    $tablaOrigen = $target['tabla_origen'];
    $leadId = $target['lead_id'];
    $originalLeadId = $target['original_lead_id'];
    $stmt->bind_param(
      'siisssssssi',
      $tablaOrigen,
      $leadId,
      $originalLeadId,
      $interactionType,
      $interactionDate,
      $interactionTime,
      $comentario,
      $outcome,
      $nextAction,
      $nextActionDate,
      $createdBy
    );
    $stmt->execute();
  }

  $stmt->close();
}


function obtenerDatos($conn, $tabla)
{
  if ($tabla != "contact_form")
    $sql = "SELECT * FROM $tabla";
  else
    $sql = "SELECT cf.*,c.hora_cliente FROM `contact_form` cf INNER JOIN calendario c ON cf.id=c.idclie";
  $result = $conn->query($sql);

  // Verificar si la consulta se ejecut�� correctamente
  if (!$result) {
    die("Error en la consulta: " . $conn->error);
  }

  return ($result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
}


function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida, $usuario)
{
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
  //  $mail_enviado = mail($correo_destino, $asunto, $mensaje, $headers);
  $correoRemitente = "info@efegepho.com.mx";
  $nombreRemitente = "InfoEfegepho";
  try {
    // Servidor SMTP
    $mail->isSMTP();  // Usar SMTP

    $mail->SMTPAuth = true; // Usar autenticaci��n SMTP
    $mail->SMTPSecure = 'starttls'; // Usar encriptaci��n TLS
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
    $mail->Body = $mensaje; // Convierte saltos de l��nea en <br> para formato HTML

    // // Adjuntar archivo (si hay uno)
    // if ($fileAttached) {
    //     $mail->addAttachment($destPath, $fileName); // Adjuntar el archivo
    // }

    // Enviar correo
    // if ($mail->send()) {
    //     // Respuesta exitosa
    //     echo json_encode(['status' => 'success', 'message' => 'Correo enviado con ��xito.']);
    // } else {
    //     echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar el correo.']);
    // }
    $mail->send();
  } catch (Exception $e) {
    // Log silently - do not echo, as the JSON response was already sent
    error_log('enviarCorreo error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo);
  }


}

// Obtener datos de las tablas
$usuarios = obtenerDatos($conn, 'usuarios');
$contact_form = obtenerDatos($conn, 'contact_form');//datos del cliente
$txt_correo = obtenerDatos($conn, 'txtCorreo');
$citas = obtenerDatos($conn, 'calendario');
$vendedores = obtenerDatos($conn, 'usuarios');

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

$citas_indexadas = [];
foreach ($citas as $cita) {
  $citas_indexadas[$cita['id']] = $cita;
}

// Indexar vendedores por ID para búsquedas rápidas
$vendedores_indexados = [];
foreach ($vendedores as $vendedor) {
  $vendedores_indexados[$vendedor['id']] = $vendedor;
}

// Verificar si se ha enviado el ID y el estatus
if (isset($_POST['id']) && isset($_POST['estatus']) && isset($_POST['idusu'])) {
  $id = $_POST['id'];
  $estatus = $_POST['estatus'];
  $idusu = $_POST['idusu'];
  $comentario = isset($_POST['comentario']) ? $_POST['comentario'] : '';
  $comentarioACliente = isset($_POST['comentario_a_cliente']) ? trim((string) $_POST['comentario_a_cliente']) : '';
  $idclie = isset($_POST['idclie']) ? $_POST['idclie'] : 0;
  $idcal = isset($_POST['idcal']) ? intval($_POST['idcal']) : 0;

  // Post-session modal summary fields
  $sfm_nombre             = isset($_POST['sfm_nombre']) ? htmlspecialchars($_POST['sfm_nombre']) : '';
  $sfm_email              = isset($_POST['sfm_email']) ? htmlspecialchars($_POST['sfm_email']) : '';
  $sfm_telefono           = isset($_POST['sfm_telefono']) ? htmlspecialchars($_POST['sfm_telefono']) : '';
  $sfm_wedding_location   = isset($_POST['sfm_wedding_location']) ? htmlspecialchars($_POST['sfm_wedding_location']) : '';
  $sfm_wedding_date       = isset($_POST['sfm_wedding_date']) ? htmlspecialchars($_POST['sfm_wedding_date']) : '';
  $sfm_how_meet_label     = isset($_POST['sfm_how_did_you_meet_label']) ? htmlspecialchars($_POST['sfm_how_did_you_meet_label']) : '';
  $sfm_hear_label         = isset($_POST['sfm_hear_about_us_label']) ? htmlspecialchars($_POST['sfm_hear_about_us_label']) : '';
  $sfm_paquete_nombre     = isset($_POST['sfm_paquete_nombre']) ? htmlspecialchars($_POST['sfm_paquete_nombre']) : '';
  $sfm_engagement_label   = isset($_POST['sfm_engagement_label']) ? htmlspecialchars($_POST['sfm_engagement_label']) : '';

  if ($estatus != 4) {
    // Capturar el estatus anterior ANTES de sobreescribirlo, para el historial
    $estatusAnteriorCal = null;
    if ($stmtPrev = $conn->prepare("SELECT estatus FROM calendario WHERE id = ? LIMIT 1")) {
      $stmtPrev->bind_param("i", $id);
      if ($stmtPrev->execute()) {
        $resPrev = $stmtPrev->get_result();
        if ($resPrev && ($rowPrev = $resPrev->fetch_assoc())) {
          $estatusAnteriorCal = intval($rowPrev['estatus']);
        }
      }
      $stmtPrev->close();
    }

    // Realizar la consulta para actualizar el estatus con el valor dinámico
    $sql = "UPDATE calendario SET estatus = ?, comentario = ? WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
      $stmt->bind_param("isi", $estatus, $comentario, $id);  // Vinculamos los parámetros estatus (entero), comentario (string) e id (entero)
      if ($stmt->execute()) {
        // Registrar la transición en el historial de estatus (trazabilidad completa)
        registrarCambioEstatusCalendario($conn, $id, $estatus, [
          'estatus_anterior' => $estatusAnteriorCal,
          'usuario'          => $usuarioCambioEstatus,
          'origen'           => 'cambiar-estatus',
          'observaciones'    => $comentario,
        ]);
        if ($estatus == 3) {
          guardarMotivoRechazoComoInteraccion($conn, intval($idclie), $comentario, $usuarioCambioEstatus);
        }
        // Si la actualización fue exitosa
        if ($estatus == 1) {
          $message = 'Estatus cambiado a atendido.';
        } elseif ($estatus == 3) {
          $message = 'Estatus cambiado a no respondido.';
        } else {
          $message = 'Estatus cambiado.';
        }
        echo json_encode(['success' => true, 'message' => $message]);

        //


        //enviar correo de notificación
        //estatus: 1=atendido, 3= rechazado/muerto
        $estatus_texto = ($estatus == 1) ? 'Attended' : (($estatus == 3) ? 'No Response' : 'Unknown');
        $asunto = ($estatus == 1) ? 'Lead attended successfully' : (($estatus == 3) ? 'Dead lead' : 'Unknown');
        $cliente = $contact_form_indexados[$idclie] ?? null;
        $cita = $citas_indexadas[$id] ?? null;
        $vendedorid = $citas_indexadas[$id]['idusu'] ?? null;

        $customer = $cliente ? $cliente['names'] : 'Unknown Customer';
        $seller = $vendedores_indexados[$vendedorid]['nombre'] . ' ' . $vendedores_indexados[$vendedorid]['apePat'];
        $date_appointment_seller = $cita ? $cita['fecha'] . ' ' . $cita['hora_cliente'] : 'Unknown Date';
        $date_appointment_client = $cita ? $cita['fecha'] . ' ' . $cita['hora_cliente'] : 'Unknown Date';
        $customer_number = $cliente ? $cliente['telephone'] : 'Unknown Number';
        $rejection_reason = $comentario ?: 'Not specified';
        // seleccionar plantilla: para México (+52) usamos el template de español (índice 22 -> id 23 en la tabla),
        // para otros países usamos el template en inglés (índice 23 -> id 24 en la tabla)
        $indexCorreo = 23; // Forzar correo en inglés

        $titulo = $txt_correo[$indexCorreo]['txttituladmin'];
        $titulo = str_replace('$customer', $customer, $titulo);
        $titulo = str_replace('$date', '<br>Client date: ' . $date_appointment_client . ' | Seller date: ' . $date_appointment_seller, $titulo);
        $titulo = str_replace('$seller', $seller, $titulo);
        $titulo = str_replace('$customer_number', $customer_number, $titulo);
        $titulo = str_replace('$rejection_reason', $rejection_reason, $titulo);
        $titulo = str_replace('$estatus', $estatus_texto, $titulo);
        
        // Por defecto obtenemos el cuerpo desde las plantillas
        $cuerpo = $txt_correo[$indexCorreo]['txtadmin'];
        $cuerpo = str_replace('$customer', $customer, $cuerpo);
        $cuerpo = str_replace('$date', '<br>Client date: ' . $date_appointment_client . ' | Seller date: ' . $date_appointment_seller, $cuerpo);
        $cuerpo = str_replace('$seller', $seller, $cuerpo);
        $cuerpo = str_replace('$customer_number', $customer_number, $cuerpo);
        $cuerpo = str_replace('$rejection_reason', $rejection_reason, $cuerpo);
        $cuerpo = str_replace('$estatus', $estatus_texto, $cuerpo);

        // Asegurar los textos solicitados según el estatus
        $isSpanish = false; // Forzar inglés

        if ($estatus == 1) {
          // atendido -> solo agregar la línea principal en español (el resto ya lo cubre el template con str_replace)
          if ($isSpanish) {
            $mainLine = '<p>Un nuevo cliente potencial acaba de completar su primera reunión con nuestro equipo de ventas.</p>';
          } else {
            // para otros países mantener la equivalencia en inglés
            $mainLine = '<p>A new lead just completed their first meeting with our sales team.</p>';
          }
          // Append session summary block
          $sessionSummary = "
<br><hr style='border:1px solid #ddd;margin:16px 0;'>
<p><strong>Resumen de sesi&oacute;n</strong></p>
<table style='width:100%;border-collapse:collapse;font-size:1rem;'>
  <tr><td style='padding:4px 8px;color:#666;width:45%;'>Nombre</td><td style='padding:4px 8px;'><strong>" . ($sfm_nombre ?: $customer) . "</strong></td></tr>
  <tr><td style='padding:4px 8px;color:#666;'>Tel&eacute;fono</td><td style='padding:4px 8px;'>" . ($sfm_telefono ?: $customer_number) . "</td></tr>
  <tr><td style='padding:4px 8px;color:#666;'>Correo</td><td style='padding:4px 8px;'>" . ($sfm_email ?: 'N/A') . "</td></tr>
  <tr><td style='padding:4px 8px;color:#666;'>Lugar de la boda</td><td style='padding:4px 8px;'>" . ($sfm_wedding_location ?: 'N/A') . "</td></tr>
  <tr><td style='padding:4px 8px;color:#666;'>Fecha de la boda</td><td style='padding:4px 8px;'>" . ($sfm_wedding_date ?: 'N/A') . "</td></tr>
  <tr><td style='padding:4px 8px;color:#666;'>&iquest;De d&oacute;nde nos conocieron?</td><td style='padding:4px 8px;'>" . ($sfm_how_meet_label ?: 'N/A') . "</td></tr>
  <tr><td style='padding:4px 8px;color:#666;'>&iquest;C&oacute;mo llegaron exactamente?</td><td style='padding:4px 8px;'>" . ($sfm_hear_label ?: 'N/A') . "</td></tr>
  <tr><td style='padding:4px 8px;color:#666;'>Paquete de inter&eacute;s</td><td style='padding:4px 8px;'>" . ($sfm_paquete_nombre ?: 'N/A') . "</td></tr>
  <tr><td style='padding:4px 8px;color:#666;'>Engagement</td><td style='padding:4px 8px;'>" . ($sfm_engagement_label ?: 'N/A') . "</td></tr>
  <tr><td style='padding:4px 8px;color:#666;'>Comentarios</td><td style='padding:4px 8px;'>" . ($comentario ?: 'N/A') . "</td></tr>
</table>
";
          // prepend mainLine to the already-processed template body
          $cuerpo = $mainLine . $cuerpo . $sessionSummary;
        } elseif ($estatus == 3) {
          // rechazado/fantasma -> solo agregar la línea principal (el resto lo maneja el template con str_replace)
          if ($isSpanish) {
            $mainLine = '<p>Un nuevo cliente potencial tenía programada su primera reunión con nuestro equipo de ventas, pero no pudimos llevarla a cabo.</p>';
          } else {
            $mainLine = '<p>A new lead had a meeting scheduled with our sales team, but we couldn\'t carry it out.</p>';
          }
          // prepend mainLine to the already-processed template body
          $cuerpo = $mainLine . $cuerpo;
        }
        $despedida = $txt_correo[$indexCorreo]['txtdespadmin'];
        $despedida = str_replace('$customer', $customer, $despedida);
        $despedida = str_replace('$date', '<br>Client date: ' . $date_appointment_client . ' | Seller date: ' . $date_appointment_seller, $despedida);
        $despedida = str_replace('$seller', $seller, $despedida);
        $despedida = str_replace('$customer_number', $customer_number, $despedida);
        $despedida = str_replace('$rejection_reason', $rejection_reason, $despedida);
        $despedida = str_replace('$estatus', $estatus_texto, $despedida);
        // enviarCorreo("proyectos@conlineweb.com", $asunto, $titulo, $cuerpo, $despedida, 'Admin');

        //enviar a juanpablo.ggomez@gmail.com y fernandagomezca@gmail.com
         $correos_admins = ["juanpablo.ggomez@gmail.com", "fernandagomezca@gmail.com"];
      
        foreach ($correos_admins as $correo_admin) {
          enviarCorreo($correo_admin, $asunto, $titulo, $cuerpo, $despedida, 'Admin');
        }





      } else {
        // Si hubo un error en la ejecución
        echo json_encode(['success' => false, 'message' => 'Error al cambiar el estatus.']);
      }
      $stmt->close();
    } else {
      // Si la consulta no se preparó correctamente
      echo json_encode(['success' => false, 'message' => 'Error en la consulta SQL.']);
    }
  } else {
    $fecha_actual = date("Y-m-d");
    ensureColumnExists($conn, 'calendario', 'comentario_a_cliente', "`comentario_a_cliente` TEXT DEFAULT NULL AFTER `comentario`");

    if ($comentarioACliente === '') {
      echo json_encode(['success' => false, 'message' => 'Debes agregar un comentario para pasarlo a cliente.']);
      $conn->close();
      exit;
    }

    $estatus = 1;
    $guardarComentarioClienteEnCalendario = function () use ($conn, $comentarioACliente, $idcal, $id) {
      $updated = false;

      if ($idcal > 0) {
        $stmtCal = $conn->prepare("UPDATE calendario SET comentario_a_cliente = ? WHERE id = ? LIMIT 1");
        if ($stmtCal) {
          $stmtCal->bind_param("si", $comentarioACliente, $idcal);
          $stmtCal->execute();
          $updated = $stmtCal->affected_rows > 0;
          $stmtCal->close();
        }
      }

      if (!$updated) {
        $stmtCal = $conn->prepare("UPDATE calendario SET comentario_a_cliente = ? WHERE idclie = ? ORDER BY id DESC LIMIT 1");
        if ($stmtCal) {
          $stmtCal->bind_param("si", $comentarioACliente, $id);
          $stmtCal->execute();
          $updated = $stmtCal->affected_rows > 0;
          $stmtCal->close();
        }
      }

      return $updated;
    };
    
    // Solo actualizar id_vendedor_asignado si el nuevo valor no es 0 (tiene vendedor en la cita)
    // Para wedding planners que ya tienen id_vendedor_asignado, no lo sobrescribimos con 0
    if ($idusu != 0 && $idusu != "0") {
      $sql = "UPDATE contact_form SET cliente = ?, id_vendedor_asignado = ?, fecha_cambio_cliente = ? WHERE id = ?";
      if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iisi", $estatus, $idusu, $fecha_actual, $id);
        if ($stmt->execute()) {
          $guardarComentarioClienteEnCalendario();
          // Registrar el cierre (cliente) en el historial de estatus de la cita
          registrarCambioEstatusCalendario($conn, $idcal, 4, [
            'usuario'       => $usuarioCambioEstatus,
            'origen'        => 'cambiar-estatus:cliente',
            'observaciones' => $comentarioACliente,
          ]);
          $message = "Se ha cambiado a cliente exitosamente";
          echo json_encode(['success' => true, 'message' => $message]);
        } else {
          echo json_encode(['success' => false, 'message' => 'Error al cambiar el estatus.']);
        }
        $stmt->close();
      } else {
        echo json_encode(['success' => false, 'message' => 'Error en la consulta SQL.']);
      }
    } else {
      // Si idusu es 0, no actualizar id_vendedor_asignado (mantener el existente para wedding planners)
      $sql = "UPDATE contact_form SET cliente = ?, fecha_cambio_cliente = ? WHERE id = ?";
      if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("isi", $estatus, $fecha_actual, $id);
        if ($stmt->execute()) {
          $guardarComentarioClienteEnCalendario();
          // Registrar el cierre (cliente) en el historial de estatus de la cita
          registrarCambioEstatusCalendario($conn, $idcal, 4, [
            'usuario'       => $usuarioCambioEstatus,
            'origen'        => 'cambiar-estatus:cliente',
            'observaciones' => $comentarioACliente,
          ]);
          $message = "Se ha cambiado a cliente exitosamente";
          echo json_encode(['success' => true, 'message' => $message]);
        } else {
          echo json_encode(['success' => false, 'message' => 'Error al cambiar el estatus.']);
        }
        $stmt->close();
      } else {
        echo json_encode(['success' => false, 'message' => 'Error en la consulta SQL.']);
      }
    }
  }

  // Cerrar la conexión
  $conn->close();
} else {
  // Si no se recibe el ID o el estatus
  echo json_encode(['success' => false, 'message' => 'ID o estatus no proporcionado.']);
}
?>