<?php
/**
 * weekly_leads_report.php
 *
 * Cron script to send a weekly leads report (Saturday -> Friday).
 * - Generates three metrics for the period:
 *   1) Leads generated (contact_form.submission_date)
 *   2) Leads agendados (unique contact_form ids with calendario.fecha in range)
 *   3) Clientes cerrados (contact_form.cliente = 1 AND fecha_cambio_cliente in range)
 *
 * Configure the recipient and sender below.
 * Cron should execute this file every Friday (no further cron configuration required here).
 *
 * Usage (manual test):
 *  php admin/weekly_leads_report.php            # sends email
 *  php admin/weekly_leads_report.php debug      # prints report to stdout and still sends email
 *  php admin/weekly_leads_report.php --no-mail  # prints report but doesn't send email
 */

// PHPMailer imports
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

// --- CONFIGURATION: edit these values for your environment ---
$toEmail = 'juanpablo.ggomez@gmail.com'; // <-- change to the recipient for weekly reports
$logFile = __DIR__ . '/weekly_leads_report.log';
// -------------------------------------------------------------

// Allow simple CLI args for debugging
$sendMail = true;
$debug = false;
if (PHP_SAPI === 'cli') {
    global $argv;
    if (isset($argv[1])) {
        if ($argv[1] === '--no-mail' || $argv[1] === 'no-mail')
            $sendMail = false;
        if ($argv[1] === 'debug')
            $debug = true;
    }
} else {
    // If run via web (wget/cURL), allow ?debug=1 or ?no_mail=1
    if (isset($_GET['debug']) && $_GET['debug'])
        $debug = true;
    if (isset($_GET['no_mail']) && $_GET['no_mail'])
        $sendMail = false;
}

// Include DB connection (expects $conn as mysqli)
require_once 'conn.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    $err = "Database connection not available (expected \$conn as mysqli).";
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $err . PHP_EOL, FILE_APPEND);
    if ($debug)
        echo $err . PHP_EOL;
    exit(1);
}



/**
 * enviarCorreo: envía un correo HTML usando PHPMailer y la plantilla proporcionada.
 * Retorna true si se envió con éxito, false en caso contrario.
 */
function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida, $usuario)
{
    global $logFile; // para registrar errores
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
          $cuerpo
          <p style='margin-top:12px;font-weight:700;color:#444;'>$despedida</p>
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

    // Enviar correo via SMTP (ajusta credenciales si es necesario)
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
        $mail->addAddress($correo_destino, 'Customer');

        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;
        //UTF-8
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->ContentType = 'text/html; charset=UTF-8';

        return $mail->send();
    } catch (Exception $e) {
        $err = 'Error sending mail: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo;
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $err . PHP_EOL, FILE_APPEND);
        return false;
    }
}

// Determine the report period: Saturday -> Friday (inclusive)
$end = new DateTime('now');
// Prefer to use today's date at 23:59:59 as end (inclusive)
$end->setTime(23, 59, 59);
// Find the most recent Saturday on or before $end (loop max 7 days)
$start = clone $end;
for ($i = 0; $i < 7; $i++) {
    // date('N'): 1 = Monday .. 6 = Saturday .. 7 = Sunday
    if ((int) $start->format('N') === 6)
        break; // Saturday
    $start->modify('-1 day');
}
$start->setTime(0, 0, 0);

$startDateTime = $start->format('Y-m-d H:i:s');
$endDateTime = $end->format('Y-m-d H:i:s');
$startDateOnly = $start->format('Y-m-d');
$endDateOnly = $end->format('Y-m-d');

// Normalization helper (campaign_name / platform / is_organic -> readable origin)
function normalize_origin_from_row($row)
{
    $campaign = trim((string) ($row['campaign_name'] ?? ''));
    $platform = trim((string) ($row['platform'] ?? ''));
    $isOrganic = strtolower((string) ($row['is_organic'] ?? ''));

    if ($campaign !== '')
        return $campaign;

    if (stripos($platform, 'fb') !== false || stripos($platform, 'facebook') !== false)
        return 'Facebook';
    if (stripos($platform, 'ig') !== false || stripos($platform, 'instagram') !== false)
        return 'Instagram';
    if ($isOrganic === 'true' || $isOrganic === '1')
        return 'Orgánico';
    if ($platform !== '')
        return $platform;
    return 'Otros';
}

// Fetch list of lead tables
$tables = [];
$res = $conn->query("SELECT nombre FROM tablas_leads ORDER BY nombre");
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        $tables[] = $r['nombre'];
    }
}

// Prepare counters
$generated_by_origin = [];
$agendado_by_origin = [];
$cliente_by_origin = [];
$total_generated = 0;
$total_agendado = 0;
$total_cliente = 0;

// Cache for original leads (table => id => row) to avoid repeated queries
$leadCache = [];

// 1) Leads generados: recorrer tablas y contar created_time en el rango
foreach ($tables as $table) {
    $safeTable = $conn->real_escape_string($table);
    // Check table exists
    $chk = $conn->query("SHOW TABLES LIKE '" . $safeTable . "'");
    if (!($chk && $chk->num_rows > 0))
        continue;

    $sql = "SELECT id, campaign_name, platform, is_organic FROM `" . $safeTable . "` WHERE created_time BETWEEN '" . $conn->real_escape_string($startDateTime) . "' AND '" . $conn->real_escape_string($endDateTime) . "'";
    $r = $conn->query($sql);
    if (!($r && $r->num_rows > 0))
        continue;

    while ($row = $r->fetch_assoc()) {
        $origin = normalize_origin_from_row($row);
        if (!isset($generated_by_origin[$origin]))
            $generated_by_origin[$origin] = 0;
        $generated_by_origin[$origin]++;
        $total_generated++;
        // cache the lead row for possible later lookups
        if (!isset($leadCache[$table]))
            $leadCache[$table] = [];
        $leadCache[$table][intval($row['id'])] = $row;
    }
}

// 2) Leads agendados: contact_form -> calendario.fecha in range, count unique contact_form ids
$cfWithAppt = [];
$sql = "SELECT cf.id as cf_id, cf.tabla_origen, cf.original_lead_id, cf.campaign_name as cf_campaign
        FROM contact_form cf
        INNER JOIN calendario cal ON cal.idclie = cf.id
        WHERE cal.fecha BETWEEN '" . $conn->real_escape_string($startDateOnly) . "' AND '" . $conn->real_escape_string($endDateOnly) . "'
        GROUP BY cf.id";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while ($cf = $res->fetch_assoc()) {
        $cf_id = intval($cf['cf_id']);
        if ($cf_id <= 0)
            continue;
        // Determine origin: prefer original lead table campaign_name when available
        $origin = 'Otros';
        $tablaOrigen = $cf['tabla_origen'] ?? '';
        $origId = intval($cf['original_lead_id'] ?? 0);
        $found = false;
        if ($tablaOrigen !== '' && $origId > 0) {
            // Try cache first
            if (isset($leadCache[$tablaOrigen]) && isset($leadCache[$tablaOrigen][$origId])) {
                $origin = normalize_origin_from_row($leadCache[$tablaOrigen][$origId]);
                $found = true;
            } else {
                // Fetch lead row from original table
                $safeT = $conn->real_escape_string($tablaOrigen);
                $leadRes = $conn->query("SELECT campaign_name, platform, is_organic FROM `" . $safeT . "` WHERE id = " . intval($origId) . " LIMIT 1");
                if ($leadRes && $leadRes->num_rows > 0) {
                    $leadRow = $leadRes->fetch_assoc();
                    $origin = normalize_origin_from_row($leadRow);
                    // cache it
                    if (!isset($leadCache[$tablaOrigen]))
                        $leadCache[$tablaOrigen] = [];
                    $leadCache[$tablaOrigen][$origId] = $leadRow;
                    $found = true;
                }
            }
        }
        if (!$found) {
            // fallback to contact_form campaign
            $origin = normalize_origin_from_row(['campaign_name' => $cf['cf_campaign']]);
        }
        if (!isset($agendado_by_origin[$origin]))
            $agendado_by_origin[$origin] = 0;
        $agendado_by_origin[$origin]++;
        $total_agendado++;
    }
}

// 3) Clientes cerrados: contact_form.cliente = 1 AND fecha_cambio_cliente BETWEEN start/end
$sql = "SELECT id, tabla_origen, original_lead_id, campaign_name as cf_campaign FROM contact_form WHERE cliente = 1 AND fecha_cambio_cliente BETWEEN '" . $conn->real_escape_string($startDateOnly) . "' AND '" . $conn->real_escape_string($endDateOnly) . "'";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while ($cf = $res->fetch_assoc()) {
        $cf_id = intval($cf['id']);
        if ($cf_id <= 0)
            continue;
        $origin = 'Otros';
        $tablaOrigen = $cf['tabla_origen'] ?? '';
        $origId = intval($cf['original_lead_id'] ?? 0);
        $found = false;
        if ($tablaOrigen !== '' && $origId > 0) {
            if (isset($leadCache[$tablaOrigen]) && isset($leadCache[$tablaOrigen][$origId])) {
                $origin = normalize_origin_from_row($leadCache[$tablaOrigen][$origId]);
                $found = true;
            } else {
                $safeT = $conn->real_escape_string($tablaOrigen);
                $leadRes = $conn->query("SELECT campaign_name, platform, is_organic FROM `" . $safeT . "` WHERE id = " . intval($origId) . " LIMIT 1");
                if ($leadRes && $leadRes->num_rows > 0) {
                    $leadRow = $leadRes->fetch_assoc();
                    $origin = normalize_origin_from_row($leadRow);
                    if (!isset($leadCache[$tablaOrigen]))
                        $leadCache[$tablaOrigen] = [];
                    $leadCache[$tablaOrigen][$origId] = $leadRow;
                    $found = true;
                }
            }
        }
        if (!$found) {
            $origin = normalize_origin_from_row(['campaign_name' => $cf['cf_campaign']]);
        }
        if (!isset($cliente_by_origin[$origin]))
            $cliente_by_origin[$origin] = 0;
        $cliente_by_origin[$origin]++;
        $total_cliente++;
    }
}

// Enhanced renderer: metric as a styled table card
function render_metric_table($title, $total, $byOrigin)
{
    // Sort by count desc
    if (!empty($byOrigin))
        arsort($byOrigin);

    $html = "<div style='background:#ffffff;border-radius:8px;padding:12px;margin-bottom:12px;border:1px solid #e6eef6;'>";
    $html .= "<div style='display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;'>";
    $html .= "<h3 style='margin:0;font-size:16px;color:#1a202c;font-weight:700;'>$title</h3>&nbsp;";
    // Show total as bold plain number (removed blue badge background)
    $html .= "<div style='font-weight:800;font-size:18px;color:#1f2937;'>" . intval($total) . "</div>";
    $html .= "</div>";

    if (empty($byOrigin)) {
        $html .= "<p style='margin:0;color:#6b7280;'>No data for this period</p>";
    } else {
        $html .= "<table style='width:100%;border-collapse:collapse;font-size:14px;color:#374151;'>";
        $html .= "<thead><tr><th style='text-align:left;padding:6px;border-bottom:2px solid #e6eef6;'>Origen</th><th style='text-align:right;padding:6px;border-bottom:2px solid #e6eef6;'>Conteo</th></tr></thead>";
        $html .= "<tbody>";
        foreach ($byOrigin as $origin => $count) {
            $html .= "<tr><td style='padding:8px 6px;border-bottom:1px solid #f3f4f6;'>" . htmlspecialchars($origin) . "</td><td style='padding:8px 6px;border-bottom:1px solid #f3f4f6;text-align:right;'><strong>" . intval($count) . "</strong></td></tr>";
        }
        $html .= "</tbody></table>";
    }

    $html .= "</div>";
    return $html;
}

// Build the email body with a clear header and three metric cards
// Tasa de calificación = leads agendados / leads generados (manejar división por cero)
$tasa_calificacion = ($total_generated > 0) ? ($total_agendado / $total_generated) : 0;
// Tasa de cierre = clientes cerrados / leads generados (manejar división por cero)
$tasa_cierre = ($total_generated > 0) ? ($total_cliente / $total_generated) : 0;
$cuerpo = "";
$cuerpo .= "<div style='font-family:Arial,Helvetica,sans-serif;color:#111827;'>";
$cuerpo .= "<div style='background:#f1f5f9;padding:14px;border-radius:8px;margin-bottom:12px;border:1px solid #e2e8f0;'>";
$cuerpo .= "<h2 style='margin:0 0 6px 0;font-size:18px;color:#0f172a;'>Weekly leads report</h2>";
$cuerpo .= "<div style='color:#475569;font-size:14px;'>Period: <strong>$startDateOnly</strong> to <strong>$endDateOnly</strong></div>";

// Quick summary (compact list) - placed directly below the header card
$cuerpo .= "<div style='margin-top:10px;'>";
$cuerpo .= "<ul style='margin:8px 0 0 18px;color:#374151;font-size:14px;padding:0;'>";
$cuerpo .= "<li style='margin:6px 0;list-style:disc;'><strong>Leads generated:&nbsp;</strong> " . intval($total_generated) . "</li>";
$cuerpo .= "<li style='margin:6px 0;list-style:disc;'><strong>Leads scheduled:&nbsp;</strong> " . intval($total_agendado) . "</li>";
$cuerpo .= "<li style='margin:6px 0;list-style:disc;'><strong>Qualification rate:&nbsp;</strong> " . number_format($tasa_calificacion, 2) . "</li>";
$cuerpo .= "<li style='margin:6px 0;list-style:disc;'><strong>Clients closed:&nbsp;</strong> " . intval($total_cliente) . "</li>";
$cuerpo .= "<li style='margin:6px 0;list-style:disc;'><strong>Closing rate:&nbsp;</strong> " . number_format($tasa_cierre, 2) . "</li>";
$cuerpo .= "</ul>";
$cuerpo .= "</div>";

$cuerpo .= "</div>";

$cuerpo .= render_metric_table('Leads generated', $total_generated, $generated_by_origin);
$cuerpo .= render_metric_table('Leads scheduled', $total_agendado, $agendado_by_origin);
$cuerpo .= render_metric_table('Clients closed', $total_cliente, $cliente_by_origin);

// signature intentionally removed as per request
$cuerpo .= "</div>";

$asunto = "Weekly leads report: $startDateOnly to $endDateOnly";
$titulo = "Weekly leads report\n$startDateOnly — $endDateOnly";
$despedida = "";

// If debug, print to stdout
if ($debug) {
    echo "PERIOD: $startDateOnly to $endDateOnly\n";
    echo "Leads generated: $total_generated\n";
    foreach ($generated_by_origin as $o => $c)
        echo "  $o: $c\n";
    echo "\nLeads scheduled: $total_agendado\n";
    foreach ($agendado_by_origin as $o => $c)
        echo "  $o: $c\n";
    echo "\nQualification rate: " . number_format($tasa_calificacion, 2) . "\n";
    echo "\nClosing rate: " . number_format($tasa_cierre, 2) . "\n";
    echo "\nClients closed: $total_cliente\n";
    foreach ($cliente_by_origin as $o => $c)
        echo "  $o: $c\n";
}

// Send email
if ($sendMail) {
    $sent = enviarCorreo($toEmail, $asunto, $titulo, $cuerpo, $despedida, 'Sistema');
    $logMsg = date('[Y-m-d H:i:s] ') . "Weekly report sent: " . ($sent ? "OK" : "FAIL") . "; totals: generated=$total_generated, agendado=$total_agendado, cliente=$total_cliente" . PHP_EOL;
    file_put_contents($logFile, $logMsg, FILE_APPEND);
    if ($debug)
        echo $logMsg;
} else {
    $logMsg = date('[Y-m-d H:i:s] ') . "Weekly report prepared but not sent (sendMail=false); totals: generated=$total_generated, agendado=$total_agendado, cliente=$total_cliente" . PHP_EOL;
    file_put_contents($logFile, $logMsg, FILE_APPEND);
    if ($debug)
        echo $logMsg;
}
