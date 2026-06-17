<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

include 'conn.php';
require_once __DIR__ . '/lib_marketing_template_email.php';

// Accept either template_id OR raw template fields + test_email
$testEmail = $_POST['test_email'] ?? null;
$templateId = isset($_POST['template_id']) ? intval($_POST['template_id']) : null;

if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Correo de prueba inválido']);
    exit;
}

// If template_id provided, fetch template
if ($templateId) {
    $stmt = $conn->prepare("SELECT id, nombre, asunto, titulo, cuerpo, despedida FROM marketing_templates WHERE id = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error preparando consulta']);
        exit;
    }
    $stmt->bind_param('i', $templateId);
    $stmt->execute();
    $res = $stmt->get_result();
    $template = $res->fetch_assoc();
    $stmt->close();
    if (!$template) {
        echo json_encode(['success' => false, 'message' => 'Plantilla no encontrada']);
        exit;
    }
    $asunto = $template['asunto'] ?? '';
    $titulo = $template['titulo'] ?? '';
    $cuerpo = $template['cuerpo'] ?? '';
    $despedida = $template['despedida'] ?? '';
} else {
    // raw fields from modal
    $asunto = $_POST['asunto'] ?? '';
    $titulo = $_POST['titulo'] ?? '';
    $cuerpo = $_POST['cuerpo'] ?? '';
    $despedida = $_POST['despedida'] ?? '';
}

// Prepare sample replacements
$replacements = [
    '$full_name' => 'Prueba Usuario',
    '$wedding_date' => date('j \d\e F \d\e Y'),
];

// Build schedule button if template contains $schedule_button
if (strpos($asunto, '$schedule_button') !== false || strpos($titulo, '$schedule_button') !== false || strpos($cuerpo, '$schedule_button') !== false || strpos($despedida, '$schedule_button') !== false) {
    $tablaOrigen = 'test_template';
    $leadId = 0;
    $linkFormRegister = "https://www.citas.efegepho.com.mx/inquire-form.php";
    $linkWithParams = $linkFormRegister;
    $buttonText = 'Schedule now';
    $trackUrl = 'https://citas.efegepho.com.mx/pixel/click.php?id=' . rawurlencode($leadId) . '&tabla_origen=' . rawurlencode($tablaOrigen) . '&correo=1&url=' . rawurlencode($linkWithParams);
    $link_form = "<a class='btn-agenda' href='" . $trackUrl . "' style='display: block; margin: 40px auto 20px auto; padding: 16px 24px; background-color: #eee8dc; border: 1.5px solid #3B3B3B; border-radius: 15px; color: #3B3B3B; text-align: center; text-decoration: none; font-weight: 600; font-family: \"Open Sans\", sans-serif; box-sizing: border-box; cursor: pointer;' role='button'>" . $buttonText . "</a>";
    $replacements['$schedule_button'] = $link_form;
}

// perform replacements (insert schedule_button raw, escape other values safe for HTML)
foreach ($replacements as $key => $val) {
    if ($key === '$schedule_button') {
        $asunto = str_replace($key, $val, $asunto);
        $titulo = str_replace($key, $val, $titulo);
        $cuerpo = str_replace($key, $val, $cuerpo);
        $despedida = str_replace($key, $val, $despedida);
    } else {
        $safe = htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $asunto = str_replace($key, $safe, $asunto);
        $titulo = str_replace($key, $safe, $titulo);
        $cuerpo = str_replace($key, $safe, $cuerpo);
        $despedida = str_replace($key, $safe, $despedida);
    }
}

// Send using enviarCorreo_marketing function (keeps same design)
$mailError = null;
$sent = enviarCorreo_marketing($testEmail, $asunto, $titulo, $cuerpo, $despedida, null, 'test', 'test', $templateId ?? null, $mailError);

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Correo de prueba enviado a ' . $testEmail]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error enviando correo de prueba', 'mail_error' => $mailError]);
}

$conn->close();
