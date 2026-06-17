<?php
// Script mejorado para enviar correos de leads de 1 en 1 con monitoreo en tiempo real
// Puede ejecutarse por cron O manualmente desde el navegador con vista en tiempo real

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0); // Sin límite de tiempo para procesos largos

date_default_timezone_set('Mexico/General');

// Configuración
$DELAY_BETWEEN_EMAILS = 60; // segundos entre cada correo (60 = 1 minuto)
$MAX_PER_RUN = 500;

// Detectar si es CLI o navegador
$isCLI = (PHP_SAPI === 'cli');

// Parámetros
$dryRun = false;
$realTimeMode = false;

if ($isCLI) {
    foreach ($argv as $a) {
        if (strpos($a, '--dry') === 0)
            $dryRun = true;
    }
} else {
    if (isset($_GET['dry']) && $_GET['dry'] == '1')
        $dryRun = true;
    if (isset($_GET['realtime']) && $_GET['realtime'] == '1')
        $realTimeMode = true;
}

// Note: When $dryRun is true this script will simulate sending by
// actually sending a copy to the designated test email (proyectos@conlineweb.com)
// but it will not persist changes to the original lead records (it reverts claims)

$logPath = __DIR__ . '/cron_send_leads_emails.log';
$statusFile = __DIR__ . '/email_sending_status.json';

function logMsg($msg)
{
    global $logPath;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
}

function updateStatus($data)
{
    global $statusFile;
    file_put_contents($statusFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

function sendRealTimeUpdate($data)
{
    global $realTimeMode;
    if ($realTimeMode) {
        echo "data: " . json_encode($data) . "\n\n";
        if (ob_get_level() > 0)
            ob_flush();
        flush();
    }
}

// Si es modo tiempo real, configurar SSE
if ($realTimeMode && !$isCLI) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no'); // para nginx
    if (ob_get_level() > 0)
        ob_end_clean();
}

// Si es llamada HTTP normal (no realtime, no CLI), cerrar conexión pero seguir ejecutando
// Esto permite que wget/curl terminen inmediatamente mientras el script sigue en background
if (!$isCLI && !$realTimeMode) {
    ignore_user_abort(true);  // No detenerse si el cliente cierra conexión
    
    // Enviar respuesta vacía inmediatamente para liberar al cliente
    if (ob_get_level() > 0) ob_end_clean();
    header('Connection: close');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'started', 'message' => 'Email sending process started in background']);
    header('Content-Length: ' . ob_get_length());
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();  // Para servidores FastCGI (más efectivo)
    }
}

logMsg('=== START cron_send_leads_emails_v2.php (dryRun=' . ($dryRun ? '1' : '0') . ', realTime=' . ($realTimeMode ? '1' : '0') . ') ===');

include 'conn.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';

function obtenerDatos($conn, $tabla)
{
    if ($tabla == "calendario") {
        // Ensure date literal is quoted and avoid silent failure with unquoted constant
        $sql = "SELECT * FROM $tabla WHERE fecha != '0000-00-00' AND estatus != 3";
    } else {
        $sql = "SELECT * FROM $tabla";
    }
    $result = $conn->query($sql);
    if (!$result) {
        throw new \Exception("Error en la consulta: " . $conn->error);
    }
    return ($result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function enviarCorreoSMTP($correo_destino, $asunto, $titulo, $cuerpo, $despedida, $archivoAdjunto = null, $tablaOrigen = '', $leadId = 0, $numCorreoEnviado = 1)
{
    $mail = new PHPMailer(true);
    $correoRemitente = "info@efegepho.com.mx";
    $nombreRemitente = "InfoEfegepho";

    try {
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'starttls';
        $mail->Port = 587;
        $mail->Host = 'smtp.gmail.com';
        $mail->Username = $correoRemitente;
        $mail->Password = 'glhewzgjzdnsbuvj';
        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->addAddress($correo_destino);
           // Enviar copia (CC) a Juan Pablo
    $mail->addCC('juanpablo.ggomez@gmail.com', 'Juan Pablo');
    // Enviar copia (CC) a Alexia
    // $mail->addCC('Alexiazdaa1@gmail.com', 'Alexia');
        $mail->Subject = $asunto;
        $mail->isHTML(true);

        $mensaje = "
<html>
<head>
  <title>$asunto</title>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <style>
    /* Importar la fuente desde Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Open+Sans&display=swap');
    
    body {
      margin: 0;
      padding: 0;
    }
    
    .bg{
      width: 96%;
      margin: 0 auto;
      padding: 50px 0px;
      background-color: #e8e8e8;
     
    }
    
    p {
      margin: 15px;
    }
    
    .container {
      width: 90%;
      max-width: 600px;
      margin: 0 auto;
      border-radius: 30px;
      background-color: #fff;
      line-height: 1.5;
      font-size: 1.5rem;
      box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .card-container {
      padding: 10px 20px;
      margin: 10px;
    }
    
    .btn-agenda {
         font-size: 1.5rem;
         width: 100%;
    }
    .header {
      text-align: left;
      padding: 10px 30px;
      font-size: 1.5rem;
      background-color: #eee8dc;
      color: #3B3B3B; 
      font-weight: 600;
      margin-top: 13px;
    }
    
    .content {
      padding: 20px 0px 0px 0px;
      margin: 0px;
    }
    
    .logo {
      width: 120px;
      margin: 0 auto;
    }
    
    /* Media query para pantallas grandes (PC) */
    @media screen and (min-width: 768px) {
      .container {
        max-width: 750px;
        font-size: 1.5rem;
      }
      
      .header {
        font-size: 1.7rem;
        padding: 10px 50px;
      }
      
      .card-container {
        padding: 10px 30px;
      }
      
      .logo {
        width: 140px;
      }
      .btn-agenda {
          font-size: 1.7rem;
         width: 50%;
    }
    }
  </style>
</head>
<body>
  <div class='bg'>
    <div class='container'>
      <div class='content'>
        <div style='text-align: center;'>
          <img class='logo' alt='efegephologo' src='https://citas.efegepho.com.mx/admin/assets/img/logofgep.png'/>
        </div>
        <div class='header'>
          $titulo
        </div>
        <div class='card-container'>
          <p>$cuerpo</p>
          <p>$despedida</p>
        </div>
      </div>
    </div>
    <div style='text-align: center; margin-top: 20px;'>
      <img class='logo' alt='efegephologo' src='https://citas.efegepho.com.mx/admin/assets/img/logofgep.png'/>
    </div>
    <img src='https://citas.efegepho.com.mx/pixel/open.php?id={$leadId}&tabla_origen={$tablaOrigen}&correo={$numCorreoEnviado}' width='1' height='1'/>
  </div>
</body>
</html>";

        $mail->Body = $mensaje;
        $mail->AltBody = trim(strip_tags($titulo . "\n\n" . $cuerpo . "\n\n" . $despedida));

        // Log para debug del PDF
        if ($archivoAdjunto && file_exists($archivoAdjunto)) {
            $mail->addAttachment($archivoAdjunto);
            logMsg("PDF ADJUNTADO: correo=$numCorreoEnviado, archivo=$archivoAdjunto, destino=$correo_destino");
        } else {
            logMsg("SIN PDF: correo=$numCorreoEnviado, archivoAdjunto=" . ($archivoAdjunto ?? 'NULL') . ", destino=$correo_destino");
        }

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        logMsg('Mail error to ' . $correo_destino . ': ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Cargar templates
try {
    $txt_correo = obtenerDatos($conn, 'txtCorreo');
} catch (Exception $e) {
    logMsg('ERROR loading txtCorreo: ' . $e->getMessage());
    sendRealTimeUpdate(['type' => 'error', 'message' => 'Error cargando templates']);
    exit(1);
}

// Variables de seguimiento
$sentCount = 0;
$errorCount = 0;
$successEmails = [];
$errorEmails = [];
$queue = [];

// Recopilar todos los correos pendientes
$tablesRes = $conn->query("SELECT nombre FROM tablas_leads ORDER BY nombre");
if (!$tablesRes) {
    logMsg('ERROR: cannot read tablas_leads: ' . $conn->error);
    exit(1);
}

while ($tbl = $tablesRes->fetch_assoc()) {
    $tableName = $tbl['nombre'];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName))
        continue;

    // Primer correo: exclude leads that already scheduled (exist in contact_form)
    $escapedTable = $conn->real_escape_string($tableName);
    $tableIdRef = "`" . $escapedTable . "`.id";
    $sql1 = "SELECT id, email, full_name, when_are_you_getting_married_ FROM `" . $escapedTable . "` 
             WHERE (correo_uno_enviado = 0 OR correo_uno_enviado IS NULL) 
             AND (email IS NOT NULL AND email != '') 
             AND (descartado = 0 OR descartado IS NULL)
             AND NOT EXISTS (SELECT 1 FROM contact_form cf WHERE cf.original_lead_id = " . $tableIdRef . " AND cf.tabla_origen = '" . $escapedTable . "')";

    $res1 = $conn->query($sql1);
    if ($res1 && $res1->num_rows > 0) {
        while ($lead = $res1->fetch_assoc()) {
            // Double-check in PHP (safety) in case of unexpected schema
            $leadIdCheck = intval($lead['id']);
            $stmtChk = $conn->prepare("SELECT COUNT(*) AS c FROM contact_form WHERE original_lead_id = ? AND tabla_origen = ? LIMIT 1");
            if ($stmtChk) {
                $stmtChk->bind_param('is', $leadIdCheck, $tableName);
                $stmtChk->execute();
                $rChk = $stmtChk->get_result();
                $rowChk = $rChk->fetch_assoc();
                $stmtChk->close();
                if ($rowChk && intval($rowChk['c']) > 0) {
                    logMsg("Skipping primer correo - already scheduled table=$tableName id=$leadIdCheck");
                    continue; // skip this lead
                }
            }
            $queue[] = [
                'type' => 'primer',
                'table' => $tableName,
                'lead' => $lead
            ];
        }
    }

    // Segundo correo: exclude leads that already scheduled (exist in contact_form)
    $sql2 = "SELECT id, email, full_name, when_are_you_getting_married_, 
                  fecha_envio_correo_uno, created_time, fecha_importacion 
              FROM `" . $escapedTable . "` 
              WHERE correo_uno_enviado = 1 
              AND (correo_dos_enviado = 0 OR correo_dos_enviado IS NULL) 
              AND (descartado = 0 OR descartado IS NULL)
              AND NOT EXISTS (SELECT 1 FROM contact_form cf WHERE cf.original_lead_id = " . $tableIdRef . " AND cf.tabla_origen = '" . $escapedTable . "')";

    $res2 = $conn->query($sql2);
    if ($res2 && $res2->num_rows > 0) {
        while ($lead = $res2->fetch_assoc()) {
            // Double-check in PHP (safety) in case of unexpected schema
            $leadIdCheck = intval($lead['id']);
            $stmtChk2 = $conn->prepare("SELECT COUNT(*) AS c FROM contact_form WHERE original_lead_id = ? AND tabla_origen = ? LIMIT 1");
            if ($stmtChk2) {
                $stmtChk2->bind_param('is', $leadIdCheck, $tableName);
                $stmtChk2->execute();
                $rChk2 = $stmtChk2->get_result();
                $rowChk2 = $rChk2->fetch_assoc();
                $stmtChk2->close();
                if ($rowChk2 && intval($rowChk2['c']) > 0) {
                    logMsg("Skipping segundo correo - already scheduled table=$tableName id=$leadIdCheck");
                    continue; // skip this lead
                }
            }
            $queue[] = [
                'type' => 'segundo',
                'table' => $tableName,
                'lead' => $lead
            ];
        }
    }
}

// ORDENAR la cola: primero todos los "primer", luego todos los "segundo"
usort($queue, function($a, $b) {
    // Si ambos son del mismo tipo, mantener orden original
    if ($a['type'] === $b['type']) {
        return 0;
    }
    // Los "primer" van antes que los "segundo"
    return ($a['type'] === 'primer') ? -1 : 1;
});

$totalQueue = count($queue);
logMsg("Total de correos en cola: $totalQueue (ordenados: primer correos primero)");

// En modo dry run, solo enviamos UN correo de cada tipo (primer y segundo) como prueba
if ($dryRun) {
    $filteredQueue = [];
    $hasPrimer = false;
    $hasSegundo = false;
    
    // Primero intentamos con la cola normal
    foreach ($queue as $item) {
        if ($item['type'] === 'primer' && !$hasPrimer) {
            $filteredQueue[] = $item;
            $hasPrimer = true;
            logMsg("DRY RUN: Seleccionado primer correo - tabla=" . $item['table'] . " id=" . $item['lead']['id'] . " email=" . $item['lead']['email']);
        } elseif ($item['type'] === 'segundo' && !$hasSegundo) {
            $filteredQueue[] = $item;
            $hasSegundo = true;
            logMsg("DRY RUN: Seleccionado segundo correo - tabla=" . $item['table'] . " id=" . $item['lead']['id'] . " email=" . $item['lead']['email']);
        }
        
        if ($hasPrimer && $hasSegundo) {
            break;
        }
    }
    
    // Si no encontramos leads en la cola, buscar CUALQUIER lead para prueba
    if (!$hasPrimer || !$hasSegundo) {
        logMsg("DRY RUN: Buscando leads adicionales para prueba...");
        
        $tablesRes2 = $conn->query("SELECT nombre FROM tablas_leads ORDER BY nombre LIMIT 5");
        while ($tbl = $tablesRes2->fetch_assoc()) {
            $tableName = $tbl['nombre'];
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) continue;
            
            // Buscar cualquier lead con email válido
            $sqlAny = "SELECT id, email, full_name, when_are_you_getting_married_ 
                       FROM `$tableName` 
                       WHERE email IS NOT NULL AND email != '' 
                       ORDER BY id DESC LIMIT 2";
            $resAny = $conn->query($sqlAny);
            
            if ($resAny && $resAny->num_rows > 0) {
                while ($lead = $resAny->fetch_assoc()) {
                    if (!$hasPrimer) {
                        $filteredQueue[] = [
                            'type' => 'primer',
                            'table' => $tableName,
                            'lead' => $lead
                        ];
                        $hasPrimer = true;
                        logMsg("DRY RUN: Forzando primer correo de prueba - tabla=$tableName id=" . $lead['id'] . " email=" . $lead['email']);
                    } elseif (!$hasSegundo) {
                        $filteredQueue[] = [
                            'type' => 'segundo',
                            'table' => $tableName,
                            'lead' => $lead
                        ];
                        $hasSegundo = true;
                        logMsg("DRY RUN: Forzando segundo correo de prueba - tabla=$tableName id=" . $lead['id'] . " email=" . $lead['email']);
                    }
                    
                    if ($hasPrimer && $hasSegundo) break;
                }
            }
            
            if ($hasPrimer && $hasSegundo) break;
        }
    }
    
    if (!$hasPrimer) {
        logMsg("DRY RUN: NO se encontró ningún lead para PRIMER correo de prueba");
    }
    if (!$hasSegundo) {
        logMsg("DRY RUN: NO se encontró ningún lead para SEGUNDO correo de prueba");
    }
    
    $queue = $filteredQueue;
    $totalQueue = count($queue);
    logMsg("Modo DRY RUN: Cola final con $totalQueue correos de prueba");
}

sendRealTimeUpdate([
    'type' => 'init',
    'total' => $totalQueue,
    'delay' => $DELAY_BETWEEN_EMAILS,
    'dry' => $dryRun ? true : false
]);


// Procesar cola de correos UNO POR UNO
logMsg("Iniciando procesamiento de cola con $totalQueue elementos");

foreach ($queue as $index => $item) {
    logMsg("=== Procesando item $index de la cola ===");
    
    if ($sentCount >= $MAX_PER_RUN) {
        logMsg("Alcanzado MAX_PER_RUN ($MAX_PER_RUN), deteniendo");
        break;
    }

    $type = $item['type'];
    $tableName = $item['table'];
    $lead = $item['lead'];
    $leadId = intval($lead['id']);
    $email = trim($lead['email']);
    $fullName = $lead['full_name'] ?? '';
    $whenMarried = $lead['when_are_you_getting_married_'] ?? '';
    
    logMsg("Procesando: type=$type, table=$tableName, id=$leadId, email=$email");

    // Validaciones
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logMsg("Skipping invalid email: $email");
        continue;
    }

    // Para segundo correo, verificar tiempo y si ya agendó (SALTAR en modo dry run)
    if ($type === 'segundo' && !$dryRun) {
        $sentDate = $lead['fecha_envio_correo_uno'] ?? null;
        if ($sentDate === '0000-00-00' || $sentDate === '0000-00-00 00:00:00' || trim($sentDate) === '') {
            $sentDate = null;
        }

        $ageDays = null;
        if (!empty($sentDate) && strtotime($sentDate) !== false) {
            $ageDays = (time() - strtotime($sentDate)) / 86400.0;
        } else {
            $fallback = $lead['created_time'] ?? ($lead['fecha_importacion'] ?? null);
            if (!empty($fallback) && strtotime($fallback) !== false) {
                $ageDays = (time() - strtotime($fallback)) / 86400.0;
            }
        }

        if ($ageDays === null || $ageDays < 3.0) {
            logMsg("Skipping segundo correo - not enough days: " . ($ageDays ?? 'unknown'));
            continue;
        }

        // Verificar si ya agendó
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM contact_form WHERE original_lead_id = ? AND tabla_origen = ? LIMIT 1");
        $stmt->bind_param('is', $leadId, $tableName);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $alreadyScheduled = ($row && intval($row['c']) > 0);
        $stmt->close();

        if ($alreadyScheduled) {
            logMsg("Skipping segundo correo - already scheduled");
            continue;
        }

        // Verificar si el cliente ya abrió el primer correo — si lo vio, no enviar el segundo
        $stmtOpen = $conn->prepare("SELECT id FROM email_opens WHERE tabla_origen = ? AND lead_id = ? AND correo = 1 LIMIT 1");
        if ($stmtOpen) {
            $stmtOpen->bind_param('si', $tableName, $leadId);
            $stmtOpen->execute();
            $stmtOpen->store_result();
            $primerVisto = ($stmtOpen->num_rows > 0);
            $stmtOpen->close();
            if ($primerVisto) {
                logMsg("Skipping segundo correo - el cliente ya abrió el primer correo table=$tableName id=$leadId");
                // Marcar correo_dos_enviado=1 para no volver a procesar en futuros ciclos
                $conn->query("UPDATE `" . $conn->real_escape_string($tableName) . "` SET correo_dos_enviado = 1 WHERE id = " . intval($leadId));
                continue;
            }
        }
    }

    // Determinar template
   $isUsaForm = (
    stripos($tableName, 'usa') !== false ||
    stripos($tableName, 'andromeda') !== false
);


    // También marcar como USA si campaign_name del lead comienza con 'E10'
    $campaignName = $lead['campaign_name'] ?? '';
    if (empty($campaignName)) {
        // Intentar obtener campaign_name desde la DB si no viene en la selección inicial
        if ($stmtC = $conn->prepare("SELECT campaign_name FROM `$tableName` WHERE id = ? LIMIT 1")) {
            $stmtC->bind_param("i", $leadId);
            if ($stmtC->execute()) {
                $stmtC->bind_result($campaignName);
                $stmtC->fetch();
            }
            $stmtC->close();
        }
    }
    if (!empty($campaignName) && stripos($campaignName, 'E10') === 0) {
        $isUsaForm = true;
        logMsg("Marked as USA by campaign_name E10: table=$tableName id=$leadId campaign_name=$campaignName");
    }

    // $indexCorreo = ($type === 'primer')
    //     ? (!$isUsaForm ? 24 : 25)
    //     : (!$isUsaForm ? 26 : 27);
    
    $indexCorreo = ($type === 'primer') ? 25 : 27;

    if (!isset($txt_correo[$indexCorreo])) {
        logMsg("Template index $indexCorreo not found. Total templates: " . count($txt_correo));
        // Listar indices disponibles
        logMsg("Indices disponibles: " . implode(', ', array_keys($txt_correo)));
        continue;
    }
    logMsg("Template $indexCorreo encontrado OK");

    // Preparar contenido
    $linkFormRegister = "https://www.citas.efegepho.com.mx/inquire-form.php";
    $separator = (strpos($linkFormRegister, '?') !== false) ? '&' : '?';
    $linkWithParams = $linkFormRegister . $separator . 'tabla_origen=' . urlencode($tableName) . '&id=' . urlencode($leadId);
    // Definir el texto del botón según el idioma (se reinicia en cada iteración)
    $buttonText =  'Schedule now';
    $link_form = "<a class='btn-agenda' href='" . $linkWithParams . "' style='display: block; margin: 40px auto 20px auto; padding: 16px 24px; background-color: #eee8dc; border: 1.5px solid #3B3B3B; border-radius: 15px; color: #3B3B3B; text-align: center; text-decoration: none; font-weight: 600; font-family: \"Open Sans\", sans-serif; box-sizing: border-box; cursor: pointer;' role='button'>" . $buttonText . "</a>";


    // NOTE: strip trailing space from placeholder name, templates likely use '$link_form'
    $titulo = str_replace(
        ['$full_name', '$when_are_you_getting_married', '$link_form'],
        [$fullName, $whenMarried, $link_form],
        $txt_correo[$indexCorreo]['txttituloclie'] ?? ''
    );

    $cuerpo = str_replace(
        ['$full_name', '$when_are_you_getting_married', '$link_form'],
        [$fullName, $whenMarried, $link_form],
        $txt_correo[$indexCorreo]['txtcli'] ?? ''
    );

    $despedida = str_replace(
        ['$full_name', '$when_are_you_getting_married', '$link_form'],
        [$fullName, $whenMarried, $link_form],
        $txt_correo[$indexCorreo]['txtdespclie'] ?? ''
    );

    // IMPORTANTE: El PDF solo se adjunta en el PRIMER correo, NO en el segundo
    $archivoPDF = null; // Reset explícito
    if ($type === 'primer') {
        $archivoPDF = '../files/EFEGE_packages.pdf';
        logMsg("Adjuntando PDF para correo tipo: $type, archivo: $archivoPDF");
    } else {
        logMsg("Sin PDF para correo tipo: $type (segundo correo)");
    }

    // Reclamar el registro (en dry run, forzar el claim aunque ya esté enviado)
    $claimField = ($type === 'primer') ? 'correo_uno_enviado' : 'correo_dos_enviado';
    
    if ($dryRun) {
        // En dry run, guardamos el valor original para restaurarlo después
        $getOriginal = $conn->query("SELECT $claimField FROM `$tableName` WHERE id = $leadId");
        $originalValue = 0;
        if ($getOriginal && $row = $getOriginal->fetch_assoc()) {
            $originalValue = $row[$claimField] ?? 0;
        }
        
        // Forzar claim temporal
        $conn->query("UPDATE `$tableName` SET $claimField = 2 WHERE id = $leadId");
        $claimed = true;
        logMsg("DRY RUN: Claim forzado para lead $leadId (valor original: $originalValue)");
    } else {
        $claim = $conn->prepare("UPDATE `$tableName` SET $claimField = 2 WHERE id = ? AND ($claimField = 0 OR $claimField IS NULL)");
        if ($claim) {
            $claim->bind_param('i', $leadId);
            $claim->execute();
            $claimed = ($conn->affected_rows > 0);
            $claim->close();
        } else {
            $claimed = false;
        }
    }

    if (!$claimed) {
        logMsg("Could not claim lead $leadId");
        continue;
    }

    // Actualizar status ANTES de enviar
    $currentStatus = [
        'current' => [
            'index' => $index + 1,
            'total' => $totalQueue,
            'type' => $type,
            'table' => $tableName,
            'id' => $leadId,
            'email' => $email,
            'status' => 'sending',
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'stats' => [
            'sent' => $sentCount,
            'errors' => $errorCount,
            // Remaining should be the number of items still pending after the current one
            'remaining' => max(0, $totalQueue - ($index + 1))
        ]
    ];
    // Include dry flag in real-time status so UI knows this is a dry run
    $currentStatus['dry'] = $dryRun ? true : false;

    updateStatus($currentStatus);
    sendRealTimeUpdate(array_merge(['type' => 'sending'], $currentStatus));

    // ENVIAR CORREO (o simular)
    if ($dryRun) {
        // In dry-run, we actually *send* a copy to the test email but DO NOT
        // persist any change to the original lead record. We also emit the
        // same SSE events so the UI shows per-email logs.
        $testEmail = 'proyectos@conlineweb.com';
        $drySubjectPrefix = '[DRY RUN] ';
        $dryTitulo = $drySubjectPrefix . $titulo . ' (original: ' . $email . ')';

        // send the email to the test email address; attach PDF if primer
        $numCorreoEnviado = ($type === 'primer') ? 1 : 2;
        $result = enviarCorreoSMTP($testEmail, $dryTitulo, $titulo, $cuerpo . "\n\n[Original recipient: " . $email . "]", $despedida, $archivoPDF, $tableName, $leadId, $numCorreoEnviado);

        if ($result['success']) {
            // revert claim on original record so we leave DB unchanged
            // Restaurar al valor original (no necesariamente 0)
            $revert = $conn->prepare("UPDATE `$tableName` SET $claimField = ? WHERE id = ?");
            if ($revert) {
                $restoreValue = isset($originalValue) ? $originalValue : 0;
                $revert->bind_param('ii', $restoreValue, $leadId);
                $revert->execute();
                $revert->close();
                logMsg("DRY RUN: Restaurado $claimField a $restoreValue para lead $leadId");
            }

            logMsg("DRY send OK: $type correo -> original_table=$tableName id=$leadId orig_email=$email sent_to=$testEmail");
            $successEmails[] = ['type' => $type, 'table' => $tableName, 'id' => $leadId, 'original_email' => $email, 'sent_to' => $testEmail];
            $sentCount++;

            sendRealTimeUpdate([
                'type' => 'success',
                'email' => $email,
                'emailType' => $type,
                'id' => $leadId,
                'table' => $tableName,
                'dry' => true,
                'info' => 'sent_to:' . $testEmail
            ]);
        } else {
            logMsg("DRY send FAIL: $type correo -> original_table=$tableName id=$leadId orig_email=$email error=" . $result['error']);
            // Restaurar valor original
            $restoreValue = isset($originalValue) ? $originalValue : 0;
            $conn->query("UPDATE `$tableName` SET $claimField = $restoreValue WHERE id = $leadId");
            $errorEmails[] = ['type' => $type, 'table' => $tableName, 'id' => $leadId, 'email' => $email, 'error' => $result['error']];
            $errorCount++;

            sendRealTimeUpdate([
                'type' => 'error',
                'email' => $email,
                'emailType' => $type,
                'id' => $leadId,
                'table' => $tableName,
                'error' => $result['error'],
                'dry' => true
            ]);
        }
    } else {
        $numCorreoEnviado = ($type === 'primer') ? 1 : 2;
        $result = enviarCorreoSMTP($email, $titulo, $titulo, $cuerpo, $despedida, $archivoPDF, $tableName, $leadId, $numCorreoEnviado);

        if ($result['success']) {
            $dateField = ($type === 'primer') ? 'fecha_envio_correo_uno' : 'fecha_envio_correo_dos';
            $u = $conn->prepare("UPDATE `$tableName` SET $claimField = 1, $dateField = NOW() WHERE id = ?");
            $u->bind_param('i', $leadId);

            if ($u->execute()) {
                logMsg("Sent $type correo table=$tableName id=$leadId email=$email");
                $successEmails[] = ['type' => $type, 'table' => $tableName, 'id' => $leadId, 'email' => $email];
                $sentCount++;

                sendRealTimeUpdate([
                    'type' => 'success',
                    'email' => $email,
                    'emailType' => $type,
                    'id' => $leadId,
                    'table' => $tableName
                ]);
            } else {
                logMsg("ERROR updating status: " . $u->error);
                $conn->query("UPDATE `$tableName` SET $claimField = 0 WHERE id = $leadId");
                $errorEmails[] = ['type' => $type, 'table' => $tableName, 'id' => $leadId, 'email' => $email, 'error' => $u->error];
                $errorCount++;

                sendRealTimeUpdate([
                    'type' => 'error',
                    'email' => $email,
                    'emailType' => $type,
                    'id' => $leadId,
                    'table' => $tableName,
                    'error' => $u->error
                ]);
            }
            $u->close();
        } else {
            logMsg("Failed to send $type correo: " . $result['error']);
            $conn->query("UPDATE `$tableName` SET $claimField = 0 WHERE id = $leadId");
            $errorEmails[] = ['type' => $type, 'table' => $tableName, 'id' => $leadId, 'email' => $email, 'error' => $result['error']];
            $errorCount++;

            sendRealTimeUpdate([
                'type' => 'error',
                'email' => $email,
                'emailType' => $type,
                'id' => $leadId,
                'table' => $tableName,
                'error' => $result['error']
            ]);
        }
    }

    // ESPERAR antes del siguiente correo (excepto el último) - NO esperar en dry run
    if (!$dryRun && $index < $totalQueue - 1 && $sentCount < $MAX_PER_RUN) {
        logMsg("Esperando $DELAY_BETWEEN_EMAILS segundos antes del siguiente correo...");
        sleep($DELAY_BETWEEN_EMAILS);
    }
}

// Status final
$finalStatus = [
    'completed' => true,
    'stats' => [
        'total_processed' => $sentCount + $errorCount,
        'sent' => $sentCount,
        'errors' => $errorCount
    ],
    'success_emails' => $successEmails,
    'error_emails' => $errorEmails
];

$finalStatus['dry'] = $dryRun ? true : false;

updateStatus($finalStatus);
sendRealTimeUpdate(array_merge(['type' => 'complete'], $finalStatus));

logMsg('=== END cron run, sent=' . $sentCount . ', errors=' . $errorCount . ' ===');

$conn->close();

if (!$isCLI && !$realTimeMode) {
    header('Content-Type: application/json');
    echo json_encode($finalStatus);
}
?>