
<?php
// Registrar clicks de emails y redirigir al URL destino
include 'conn.php';

// Parámetros
$lead_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$tabla_origen = isset($_GET['tabla_origen']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla_origen']) : null;
$correo_num = isset($_GET['correo']) ? intval($_GET['correo']) : null; // opcional, 1 o 2
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : null;
$url = isset($_GET['url']) ? trim($_GET['url']) : '';

$baseInquiryUrl = 'https://www.citas.efegepho.com.mx/inquire-form.php';

// Decodificar y validar URL destino
$decodedUrl = '';
if ($url !== '') {
	// Puede venir URL-encoded desde el correo
	$decodedUrl = urldecode($url);
}

// Reconstruir destino si el parámetro url falta o llega inválido.
$reconstructedUrl = '';
if (!empty($tabla_origen) && $lead_id > 0) {
	$separator = (strpos($baseInquiryUrl, '?') !== false) ? '&' : '?';
	$reconstructedUrl = $baseInquiryUrl . $separator
		. 'tabla_origen=' . rawurlencode($tabla_origen)
		. '&id=' . rawurlencode((string) $lead_id);
}

$redirectUrl = $decodedUrl;
if (!filter_var($redirectUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $redirectUrl)) {
	$redirectUrl = $reconstructedUrl !== '' ? $reconstructedUrl : $baseInquiryUrl;
}

// Crear tabla `email_clicks` si no existe
$createSql = "CREATE TABLE IF NOT EXISTS `email_clicks` (
	`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`lead_id` INT DEFAULT NULL,
	`tabla_origen` VARCHAR(255) DEFAULT NULL,
	`correo` TINYINT DEFAULT NULL,
	`template_id` INT DEFAULT NULL,
	`url` TEXT,
	`clicked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
	`ip` VARCHAR(45) DEFAULT NULL,
	`user_agent` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
$conn->query($createSql);

// Compatibilidad con instalaciones anteriores sin columna template_id
$ck = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_clicks' AND COLUMN_NAME = 'template_id'");
if ($ck && $ck->num_rows === 0) {
	$conn->query("ALTER TABLE `email_clicks` ADD COLUMN `template_id` INT NULL AFTER `correo`");
}

// Índice para consultas por plantilla/lead
$idxCheck = $conn->query("SHOW INDEX FROM `email_clicks` WHERE Key_name = 'idx_template_click'");
if ($idxCheck && $idxCheck->num_rows === 0) {
	$conn->query("ALTER TABLE `email_clicks` ADD INDEX `idx_template_click` (`template_id`, `tabla_origen`(100), `lead_id`, `clicked_at`)");
}

// Datos del visitante
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

// Insertar registro (prepared statement)
$stmt = $conn->prepare("INSERT INTO `email_clicks` (lead_id, tabla_origen, correo, template_id, url, clicked_at, ip, user_agent) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
if ($stmt) {
	// tipos: i (lead_id), s (tabla_origen), i (correo), i (template_id), s (url), s (ip), s (ua)
	$stmt->bind_param('isiisss', $lead_id, $tabla_origen, $correo_num, $template_id, $redirectUrl, $ip, $ua);
	$stmt->execute();
	$stmt->close();
}

// Finalmente redirigir al URL destino (302)
header('Location: ' . $redirectUrl);
exit;


?>