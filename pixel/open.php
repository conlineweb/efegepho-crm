<?php
// Incluir la conexión (archivo conn.php en la carpeta padre) de forma robusta
$parentConn = __DIR__ . '/../conn.php';
if (file_exists($parentConn)) {
    include_once $parentConn;
} else {
    // fallback a include simple (por compatibilidad) y log
    @include_once 'conn.php';
    error_log('pixel/open.php: no se encontró __DIR__/../conn.php, intento incluir conn.php sin ruta');
}

// Obtener parámetros del pixel
$lead_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$tabla_origen = isset($_GET['tabla_origen']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['tabla_origen']) : '';
$correo_num = isset($_GET['correo']) ? intval($_GET['correo']) : 0; // 1 o 2
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : null;

// Registro de depuración: guardar headers y parámetros para diagnosticar clientes que no llaman el pixel
$debugLine = '[' . date('Y-m-d H:i:s') . '] GET=' . json_encode($_GET) . ' | UA=' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . ' | IP=' . ($_SERVER['REMOTE_ADDR'] ?? '') . ' | REF=' . ($_SERVER['HTTP_REFERER'] ?? '') . PHP_EOL;
@file_put_contents(__DIR__ . '/pixel_debug.log', $debugLine, FILE_APPEND | LOCK_EX);

// Registrar apertura en nueva tabla `email_opens` (crearla si no existe)
if ($lead_id > 0 && isset($conn)) {
    // Create table if not exists with template_id column
    $createSql = "CREATE TABLE IF NOT EXISTS `email_opens` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT NOT NULL,
    `tabla_origen` VARCHAR(255) DEFAULT NULL,
    `correo` TINYINT DEFAULT NULL,
    `template_id` INT DEFAULT NULL,
    `opened_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

    // Ejecutar con mysqli (si la conexión existe)
    $conn->query($createSql);

    // Ensure column exists (for older installs)
    $ck = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_opens' AND COLUMN_NAME = 'template_id'");
    if ($ck && $ck->num_rows === 0) {
        $conn->query("ALTER TABLE `email_opens` ADD COLUMN `template_id` INT NULL AFTER `correo`");
    }

    // Ensure index exists for faster lookups
    $idxCheck = $conn->query("SHOW INDEX FROM `email_opens` WHERE Key_name = 'idx_template_open'");
    if ($idxCheck && $idxCheck->num_rows === 0) {
        // create an index that covers tabla_origen, lead_id, template_id, opened_at
        $conn->query("ALTER TABLE `email_opens` ADD INDEX `idx_template_open` (`tabla_origen`(100), `lead_id`, `template_id`, `opened_at`)");
    }

    // Insertar registro usando prepared statement (include template_id)
    $stmt = $conn->prepare("INSERT INTO `email_opens` (lead_id, tabla_origen, correo, template_id, opened_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param('isii', $lead_id, $tabla_origen, $correo_num, $template_id);
        if (!$stmt->execute()) {
            error_log('pixel/open.php: INSERT failed: ' . $stmt->error);
            // attempt to log connection error if available
            if (isset($conn) && $conn->error) error_log('pixel/open.php: mysqli error: ' . $conn->error);
        }
        $stmt->close();
    } else {
        error_log('pixel/open.php: prepare failed: ' . ($conn->error ?? 'no $conn')); 
    }
}

// Devolver imagen 1x1 transparente
header('Content-Type: image/png');
echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=');
?>