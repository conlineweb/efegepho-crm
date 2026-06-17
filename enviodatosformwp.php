<?php
header('Content-Type: application/json; charset=utf-8');

include 'conn.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './admin/PHPMailer/src/Exception.php';
require './admin/PHPMailer/src/PHPMailer.php';
require './admin/PHPMailer/src/SMTP.php';

function enviarCorreo($correo_destino, $asunto, $titulo, $cuerpo, $despedida, $usuario) {
        $mail = new PHPMailer(true);
        $bccInfo = [];

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

        $correoRemitente = "info@efegepho.com.mx";
        $nombreRemitente = "InfoEfegepho";
        $correo_destino = "proyectos@conlineweb.com";
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'starttls';
        $mail->Port = 587;
        $mail->Host = 'smtp.gmail.com';
        $mail->Username = $correoRemitente;
        $mail->Password = 'glhewzgjzdnsbuvj';

        $mail->setFrom($correoRemitente, $nombreRemitente);
        $mail->addAddress($correo_destino, 'Cliente');

        // if ($usuario === 'admin') {
        //         $mail->addBCC("juanpablo.ggomez@gmail.com");
        //         $mail->addBCC("fernandagomezca@gmail.com");
        //         $bccInfo['added'] = true;
        //         $bccInfo['emails'] = ["juanpablo.ggomez@gmail.com", "fernandagomezca@gmail.com"];
        //         $bccInfo['usuario'] = $usuario;
        // } else {
        //         $bccInfo['added'] = false;
        //         $bccInfo['reason'] = "El usuario no es 'admin', es: '$usuario'. BCC solo se añade para usuarios admin.";
        // }

        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->ContentType = 'text/html; charset=UTF-8';

        $mail->send();
}

function obtenerAdministradores($conn) {
        $sql = "SELECT correo FROM usuarios WHERE tipoUsu = 0";
        $result = $conn->query($sql);

        if (!$result) {
                return [];
        }

        $admins = [];
        while ($row = $result->fetch_assoc()) {
                $correo = trim($row['correo'] ?? '');
                if (filter_var($correo, FILTER_VALIDATE_EMAIL) && strpos($correo, '-') === false) {
                        $admins[] = $correo;
                }
        }

        return $admins;
}

// helper to create column if missing (compatible with older MySQL)
function ensureColumnExists($conn, $table, $columnName, $columnDef) {
    $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS " .
                "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" .
                $conn->real_escape_string($table) . "' AND COLUMN_NAME = '" .
                $conn->real_escape_string($columnName) . "'";
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

function backfillWeddingPlannerCoordinatorData($conn, $idWeddingPlanner) {
    $sql = "UPDATE coordinadores_wp c
            INNER JOIN wedding_planners wp ON wp.id = c.id_wp
            SET
                c.telefono = CASE
                    WHEN (c.telefono IS NULL OR TRIM(c.telefono) = '') AND wp.phone IS NOT NULL AND TRIM(wp.phone) <> '' THEN wp.phone
                    ELSE c.telefono
                END,
                c.correo = CASE
                    WHEN (c.correo IS NULL OR TRIM(c.correo) = '') AND wp.email IS NOT NULL AND TRIM(wp.email) <> '' THEN wp.email
                    ELSE c.correo
                END,
                c.planner_reason = CASE
                    WHEN (c.planner_reason IS NULL OR TRIM(c.planner_reason) = '') AND wp.planner_reason IS NOT NULL AND TRIM(wp.planner_reason) <> '' THEN wp.planner_reason
                    ELSE c.planner_reason
                END,
                c.how_long_known_us = CASE
                    WHEN (c.how_long_known_us IS NULL OR TRIM(c.how_long_known_us) = '') AND wp.how_long_known_us IS NOT NULL AND TRIM(wp.how_long_known_us) <> '' THEN wp.how_long_known_us
                    ELSE c.how_long_known_us
                END,
                c.first_contact_channel = CASE
                    WHEN (c.first_contact_channel IS NULL OR TRIM(c.first_contact_channel) = '') AND wp.first_contact_channel IS NOT NULL AND TRIM(wp.first_contact_channel) <> '' THEN wp.first_contact_channel
                    ELSE c.first_contact_channel
                END
            WHERE c.id_wp = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $idWeddingPlanner);
        $stmt->execute();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido.'
    ]);
    exit;
}

$companyName = trim($_POST['planner_company_name'] ?? '');
$coordinatorName = trim($_POST['planner_coordinator_name'] ?? '');
$countryCode = trim($_POST['planner_country_code'] ?? '');
$phoneNumber = trim($_POST['planner_phone'] ?? '');
$email = trim($_POST['planner_email'] ?? '');
$plannerReason = trim($_POST['planner_reason'] ?? '');
$howLongKnownUs = trim($_POST['planner_how_long_known_us'] ?? '');
$firstContactChannel = trim($_POST['first_contact_channel'] ?? '');
$referrerUrl = trim($_POST['referrer_url'] ?? '');

if (
    $companyName === '' ||
    $coordinatorName === '' ||
    $countryCode === '' ||
    $phoneNumber === '' ||
    $email === '' ||
    $plannerReason === '' ||
    $howLongKnownUs === '' ||
    $firstContactChannel === ''
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Todos los campos requeridos deben estar completos.'
    ]);
    exit;
}

$phone = preg_replace('/\s+/', '', $countryCode . $phoneNumber);
$createdTime = date('Y-m-d H:i:s');

$conn->begin_transaction();

try {
    // ensure new columns exist (works with older MySQL)
    ensureColumnExists($conn, 'wedding_planners', 'empresa_wp', "`empresa_wp` VARCHAR(255) DEFAULT NULL AFTER `where_is_your_marriage_taking_place_`");
    ensureColumnExists($conn, 'wedding_planners', 'first_contact_channel', "`first_contact_channel` VARCHAR(100) DEFAULT ''");
    ensureColumnExists($conn, 'coordinadores_wp', 'planner_reason', "`planner_reason` VARCHAR(255) DEFAULT NULL AFTER `correo`");
    ensureColumnExists($conn, 'coordinadores_wp', 'how_long_known_us', "`how_long_known_us` VARCHAR(150) DEFAULT NULL AFTER `planner_reason`");
    ensureColumnExists($conn, 'coordinadores_wp', 'first_contact_channel', "`first_contact_channel` VARCHAR(255) DEFAULT NULL AFTER `how_long_known_us`");
    ensureColumnExists($conn, 'coordinadores_wp', 'referrer_url', "`referrer_url` VARCHAR(1000) DEFAULT '' AFTER `first_contact_channel`");

    $sqlWeddingPlanner = 'INSERT INTO wedding_planners (created_time, empresa_wp, full_name) VALUES (?, ?, ?)';
    $stmtWeddingPlanner = $conn->prepare($sqlWeddingPlanner);

    if ($stmtWeddingPlanner === false) {
        throw new Exception('Error preparando inserción en wedding_planners: ' . $conn->error);
    }

    $stmtWeddingPlanner->bind_param('sss', $createdTime, $companyName, $companyName);

    if (!$stmtWeddingPlanner->execute()) {
        throw new Exception('Error insertando en wedding_planners: ' . $stmtWeddingPlanner->error);
    }

    $idWeddingPlanner = $stmtWeddingPlanner->insert_id;
    $stmtWeddingPlanner->close();

    $sqlCoordinator = 'INSERT INTO coordinadores_wp (id_wp, nombre, telefono, correo, planner_reason, how_long_known_us, first_contact_channel, referrer_url, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmtCoordinator = $conn->prepare($sqlCoordinator);

    if ($stmtCoordinator === false) {
        throw new Exception('Error preparando inserción en coordinadores_wp: ' . $conn->error);
    }

    $stmtCoordinator->bind_param('issssssss', $idWeddingPlanner, $coordinatorName, $phone, $email, $plannerReason, $howLongKnownUs, $firstContactChannel, $referrerUrl, $createdTime);

    if (!$stmtCoordinator->execute()) {
        throw new Exception('Error insertando en coordinadores_wp: ' . $stmtCoordinator->error);
    }

    $stmtCoordinator->close();

    backfillWeddingPlannerCoordinatorData($conn, $idWeddingPlanner);

    $conn->commit();

    $plannerTitle = 'Thank you for your wedding planner registration';
    $plannerBody = 'Thank you for registering with Efegepho. We have received your information and our team will contact you soon.';
    $plannerFarewell = 'Regards,';
    enviarCorreo($email, 'Wedding Planner Registration', $plannerTitle, $plannerBody, $plannerFarewell, 'cliente');

    $adminTitle = 'A new wedding planner has been registered';
    $adminBody = "A new wedding planner registration has been submitted:<br><br>"
        . "Wedding Planner Company Name: " . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . "<br>"
        . "Coordinator Name (Main Contact): " . htmlspecialchars($coordinatorName, ENT_QUOTES, 'UTF-8') . "<br>"
        . "Phone Number: " . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . "<br>"
        . "Email Address: " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "<br>"        . "First contact channel: " . htmlspecialchars($firstContactChannel, ENT_QUOTES, 'UTF-8') . "<br>"        . "What brings you here today?: " . htmlspecialchars($plannerReason, ENT_QUOTES, 'UTF-8') . "<br>"
        . "How long have you known us?: " . htmlspecialchars($howLongKnownUs, ENT_QUOTES, 'UTF-8') . "<br>"
        . "Wedding Planner ID: " . htmlspecialchars((string)$idWeddingPlanner, ENT_QUOTES, 'UTF-8') . "<br>"
        . "Created Time: " . htmlspecialchars($createdTime, ENT_QUOTES, 'UTF-8');
    $adminFarewell = 'Regards,';

    $admins = obtenerAdministradores($conn);
    foreach ($admins as $adminEmail) {
        enviarCorreo($adminEmail, 'New Wedding Planner Registration', $adminTitle, $adminBody, $adminFarewell, 'admin');
    }

    echo json_encode([
        'success' => true,
        'id_wp' => $idWeddingPlanner,
        'message' => 'Wedding planner guardado correctamente.'
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('enviodatosformwp.php: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'No fue posible guardar la información del wedding planner.'
    ]);
}
