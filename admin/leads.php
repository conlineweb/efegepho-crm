<?php
// --------------------------
// Mostrar errores para depuración
// --------------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Cargar las clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require './PHPMailer/src/Exception.php';
require './PHPMailer/src/PHPMailer.php';
require './PHPMailer/src/SMTP.php';

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
   $mail_enviado = false ;// mail($correo_destino, $asunto, $mensaje, $headers);
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
        $mail->addAddress($correo_destino, 'Customer'); // Reemplaza por el correo del destinatario

        // Asunto y contenido del correo
        $mail->Subject = $asunto;
        $mail->isHTML(true);
        $mail->Body = $mensaje; // Convierte saltos de línea en <br> para formato HTML
         $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->ContentType = 'text/html; charset=UTF-8';
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
        // $mail_enviado = $mail->send();
        $mail_enviado = true;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage() . ' | ' . $mail->ErrorInfo]);
    }

    
}



// --------------------------
// 1️⃣ Cargar autoload de Google API
// --------------------------
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("❌ autoload.php no encontrado en $autoloadPath");
}
require $autoloadPath;

// --------------------------
// 2️⃣ Conectar a Google Sheets
// --------------------------
$client = new \Google_Client();
$client->setApplicationName('bd excel');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS_READONLY]);
$client->setAuthConfig(__DIR__ . '/keys/service_key.json');

$service = new \Google_Service_Sheets($client);

$spreadsheetId = '1Kslx-M1zJH526yCl-j1OJJp_R5hOTUR85hVCTRkGFM4';

// Definir las hojas a procesar
$sheetNames = ['FORMUSA1', 'FORMUSA2', 'FORMS3', "ANDROMEDA LEADFORM", "Andromeda Lead Form - Jan 6", "EFEGE FORM 10 MARZO"];

// Modo seguro: dry-run para ver qué filas se actualizarían sin escribir en la DB.
// Ejecuta: admin/leads.php?dry=1  -> dry-run (no escribe)
// Ejecuta: admin/leads.php?apply=1 -> aplica las actualizaciones (haz backup antes)
$dryRun = isset($_GET['dry']) && ($_GET['dry'] === '1' || $_GET['dry'] === 'true');
$applyUpdates = isset($_GET['apply']) && ($_GET['apply'] === '1' || $_GET['apply'] === 'true');
// Optional parameter to limit processing to a single sheet name (case-insensitive)
$onlySheet = isset($_GET['sheet']) ? trim($_GET['sheet']) : '';

// Obtener información del spreadsheet
try {
    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
} catch (\Exception $e) {
    die("Error al acceder al Sheet: " . $e->getMessage());
}

// --------------------------
// 3️⃣ Conectar a MySQL
// --------------------------
include 'conn.php';

// Establecer charset y collation para evitar conflictos
$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");

// Crear tabla de control para guardar nombres de tablas generadas
$sqlCreateControl = "CREATE TABLE IF NOT EXISTS `tablas_leads` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL UNIQUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if (!$conn->query($sqlCreateControl)) {
    die("❌ Error creando tabla de control 'tablas_leads': " . $conn->error);
}

// --------------------------
// 4️⃣ Procesar cada hoja
// --------------------------
$allResults = []; // Almacenar resultados de cada hoja

foreach ($sheetNames as $sheetName) {
    // If an explicit ?sheet=NAME is provided, only process that sheet (case-insensitive)
    if (!empty($onlySheet) && strcasecmp($onlySheet, $sheetName) !== 0) {
        // skip this sheet
        continue;
    }
    echo "<h2>Procesando hoja: $sheetName</h2>";

    // Obtener datos de la hoja
    try {
        $range = $sheetName . '!A1:ZZ1000';
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();

        if (empty($values)) {
            echo "<p>⚠️ No hay datos en la hoja '$sheetName'</p>";
            continue;
        }
    } catch (\Exception $e) {
        echo "<p>❌ Error al acceder a la hoja '$sheetName': " . $e->getMessage() . "</p>";
        continue;
    }

    // Buscar la primera fila no vacía para usarla como encabezados
    $headerRowIndex = 0;
    $headers = [];

    foreach ($values as $index => $row) {
        // Verificar si la fila tiene al menos una celda no vacía
        $hasContent = false;
        foreach ($row as $cell) {
            if (!empty(trim($cell ?? ''))) {
                $hasContent = true;
                break;
            }
        }

        if ($hasContent) {
            $headers = $row;
            $headerRowIndex = $index;
            break;
        }
    }

    // Si no se encontraron encabezados válidos
    if (empty($headers)) {
        echo "<p>⚠️ No se encontraron encabezados válidos en la hoja '$sheetName'</p>";
        continue;
    }

    echo "<p>ℹ️ Encabezados encontrados en la fila " . ($headerRowIndex + 1) . "</p>";

    // Limpiar el nombre de la hoja para usarlo como nombre de tabla
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '_', str_replace(' ', '_', $sheetName));
    $tableName = strtolower($tableName); // Convertir a minúsculas

    // Limpiar encabezados para MySQL y crear columnas
    $columnsSQL = [];
    $cleanHeaders = [];
    $columnIndexMap = []; // Mapeo de columna limpia a índice original
    $originalToClean = []; // Mapeo: índice original -> ['orig' => original header, 'clean' => columna limpia]
    $hasIdColumn = false;
    $idColumnIndex = -1;
    $hasEmailColumn = false;
    $emailColumnIndex = -1;
    // Detect phone column and map 'phone_number' -> 'phone' for compatibility
    $hasPhoneColumn = false;
    $phoneColumnIndex = -1;

    foreach ($headers as $index => $h) {
        // Limpiar el nombre de la columna
        $col = trim($h ?? '');

        // Si la columna está vacía, saltarla
        if (empty($col)) {
            continue;
        }

        $col = preg_replace('/[^a-zA-Z0-9_]/', '_', str_replace(' ', '_', $col));
        $col = strtolower($col);

        // Ignorar columna específica de ANDROMEDA LEADFORM que no se usa
        // (ejemplo: "are_you_ok_with_us_contacting_you__or__did_you_want_to_contact_us_")
        

        // Si después de limpiar está vacío o solo contiene guiones bajos, saltarla
        if (empty($col) || preg_match('/^_+$/', $col)) {
            continue;
        }

        // Si la columna es 'id', renombrarla a 'id_excel'
        if ($col === 'id') {
            $col = 'id_excel';
            $hasIdColumn = true;
            $idColumnIndex = $index;
        }

        // Detectar si existe columna email
        if ($col === 'email' || $col === 'correo' || $col === 'e_mail') {
            $hasEmailColumn = true;
            $emailColumnIndex = $index;
            $col = 'email'; // Normalizar el nombre
        }

        // Detectar y mapear variantes de teléfono -> phone (ej: phone, phone_number, phone-number, telefono)
        if ($col === 'phone' || $col === 'phone_number' || $col === 'phone-number' || $col === 'phone number' || $col === 'telefono') {
            // Map the spreadsheet header phone_number to DB column phone
            $col = 'phone';
            $hasPhoneColumn = true;
            $phoneColumnIndex = $index;
        }

        // Evitar nombres de columna duplicados — generar sufijo si ya existe
        // Además, truncar nombres demasiado largos para ajustarse al límite de identificador de MySQL (64 chars)
        $maxIdentifierLength = 64;
        // Truncar si es necesario (seguro para multibyte)
        if (mb_strlen($col, 'UTF-8') > $maxIdentifierLength) {
            $origColFull = $col;
            $col = mb_substr($col, 0, $maxIdentifierLength, 'UTF-8');
            $col = rtrim($col, '_'); // evitar guiones bajos al final
            if ($col === '') {
                $col = 'col_' . $index;
            }
            if ($dryRun) {
                echo "🔧 DRY-RUN: nombre de columna truncado: " . htmlspecialchars($origColFull) . " → " . htmlspecialchars($col) . "<br>";
            }
        }

        $originalCol = $col;
        $suffix = 1;
        while (in_array($col, $cleanHeaders)) {
            // Añadir sufijo respetando el límite de 64 caracteres
            $suffixSuffix = '_' . $suffix;
            $available = $maxIdentifierLength - strlen($suffixSuffix);
            $base = $originalCol;
            if (strlen($base) > $available) {
                $base = substr($base, 0, $available);
                $base = rtrim($base, '_');
                if ($base === '') {
                    $base = 'col';
                }
            }
            $col = $base . $suffixSuffix;
            $suffix++;
        }

        $cleanHeaders[] = $col;
        $columnsSQL[] = "`$col` VARCHAR(255)";
        $columnIndexMap[] = $index; // Guardar el índice original
        // Guardar mapeo original -> limpio para posibles migraciones si el nombre cambió
        $originalToClean[$index] = ['orig' => $headers[$index], 'clean' => $col];
    }

    // Validar que haya al menos una columna válida
    if (empty($columnsSQL)) {
        echo "<p>⚠️ No hay columnas válidas en la hoja '$sheetName'</p>";
        continue;
    }

    $columnsSQLString = implode(",", $columnsSQL);

    // Detectar si existe columna de consentimiento (are_you_ok_with_us_contacting... truncated)
    $consentColName = null;
    $consentOrigIndex = null;
    foreach ($cleanHeaders as $ci => $colName) {
        if (strpos($colName, 'are_you_ok_with_us_contacting_you') === 0) {
            $consentColName = $colName;
            $consentOrigIndex = isset($columnIndexMap[$ci]) ? $columnIndexMap[$ci] : null;
            break;
        }
    }
    if ($consentColName) {
        echo "<p>ℹ️ Columna de consentimiento detectada: <code>" . htmlspecialchars($consentColName) . "</code></p>";
    }

    // Crear tabla si no existe (con el nombre de la hoja)
    // Siempre crear un AUTO_INCREMENT para evitar problemas con duplicados
    $sqlCreate = "CREATE TABLE IF NOT EXISTS `$tableName` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        $columnsSQLString,
        fecha_importacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        correo_uno_enviado INT DEFAULT 0,
    correo_dos_enviado INT DEFAULT 0,
    -- Fecha de envío de los correos (puede ser NULL si aún no se envió)
    fecha_envio_correo_uno TIMESTAMP NULL DEFAULT NULL,
    fecha_envio_correo_dos TIMESTAMP NULL DEFAULT NULL,
        usuario_asignado INT DEFAULT 0,
        descartado INT DEFAULT 0,
        notified TINYINT(1) DEFAULT 0,
        first_contact_channel VARCHAR(50) DEFAULT 'leadform',
        how_did_you_meet VARCHAR(10) DEFAULT '3',
        hear_about_us VARCHAR(10) DEFAULT '1'";

    // Si existe columna email, crear índice único
    if ($hasEmailColumn) {
        $sqlCreate .= ",
        UNIQUE KEY `idx_email` (`email`)";
    }

    $sqlCreate .= "
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if (!$conn->query($sqlCreate)) {
        echo "<p>❌ Error creando tabla '$tableName': " . $conn->error . "</p>";
        continue;
    }

    // Asegurar que tablas existentes también tengan los campos de fecha y la columna 'notified' si se crearon antes
    // (CREATE TABLE IF NOT EXISTS no modifica tablas ya existentes)
    $ensureCols = [
        'fecha_envio_correo_uno' => "ALTER TABLE `$tableName` ADD COLUMN `fecha_envio_correo_uno` TIMESTAMP NULL DEFAULT NULL",
        'fecha_envio_correo_dos' => "ALTER TABLE `$tableName` ADD COLUMN `fecha_envio_correo_dos` TIMESTAMP NULL DEFAULT NULL",
        'notified' => "ALTER TABLE `$tableName` ADD COLUMN `notified` TINYINT(1) DEFAULT 0",
        'first_contact_channel' => "ALTER TABLE `$tableName` ADD COLUMN `first_contact_channel` VARCHAR(50) DEFAULT 'leadform'",
        'how_did_you_meet'    => "ALTER TABLE `$tableName` ADD COLUMN `how_did_you_meet` VARCHAR(10) DEFAULT '3'",
        'hear_about_us'       => "ALTER TABLE `$tableName` ADD COLUMN `hear_about_us` VARCHAR(10) DEFAULT '1'",
    ];

    foreach ($ensureCols as $colName => $alterSQL) {
        $check = $conn->query("SHOW COLUMNS FROM `$tableName` LIKE '$colName'");
        if ($check && $check->num_rows === 0) {
            if (!$conn->query($alterSQL)) {
                echo "<p>⚠️ Error agregando columna '$colName' a '$tableName': " . $conn->error . "</p>";
            } else {
                echo "<p>✅ Columna agregada: $colName en $tableName</p>";
            }
        }
    }

    // Actualizar registros existentes que no tengan first_contact_channel asignado
    $conn->query("UPDATE `$tableName` SET `first_contact_channel` = 'leadform' WHERE `first_contact_channel` IS NULL OR `first_contact_channel` = ''");
    $conn->query("UPDATE `$tableName` SET `how_did_you_meet` = '3' WHERE `how_did_you_meet` IS NULL OR `how_did_you_meet` = ''");
    $conn->query("UPDATE `$tableName` SET `hear_about_us` = '1' WHERE `hear_about_us` IS NULL OR `hear_about_us` = ''");

    // Registrar el nombre de la tabla en tablas_leads
    $stmtControl = $conn->prepare("INSERT IGNORE INTO `tablas_leads` (nombre) VALUES (?)");
    $stmtControl->bind_param('s', $tableName);
    $stmtControl->execute();
    $stmtControl->close();

    // --------------------------
    // Migración de columnas existentes con nombres largos -> copiar datos a la nueva columna limpia
    // Uso:
    //  - ?dry=1           => mostraría lo que se migraría sin escribir
    //  - ?migrate=1       => ejecutar la migración (copia valores donde la nueva columna esté vacía)
    //  - ?migrate=1&drop_old=1 => además elimina la columna antigua si la migración fue exitosa
    // --------------------------
    $migrate = isset($_GET['migrate']) && ($_GET['migrate'] === '1' || $_GET['migrate'] === 'true');
    $dropOld = isset($_GET['drop_old']) && ($_GET['drop_old'] === '1' || $_GET['drop_old'] === 'true');

    // Obtener columnas existentes de la tabla
    $colsRes = $conn->query("SHOW COLUMNS FROM `$tableName`");
    $existingCols = [];
    if ($colsRes) {
        while ($r = $colsRes->fetch_assoc()) {
            $existingCols[] = $r['Field'];
        }
    }

    // Opción para listar columnas actualmente existentes en la tabla (útil para depuración y ver el nombre exacto)
    $listColumns = isset($_GET['list_columns']) && ($_GET['list_columns'] === '1' || $_GET['list_columns'] === 'true');
    if ($listColumns) {
        echo "<p><strong>🔎 Columnas actuales en la tabla `$tableName`:</strong></p><ul>";
        foreach ($existingCols as $ec) {
            echo "<li>" . htmlspecialchars($ec) . "</li>";
        }
        echo "</ul>";
        // Después de listar, continuar con la siguiente hoja (no hacemos migraciones ni inserciones aquí)
        continue;
    }

    // Revisar cada encabezado original que se mapeó a una columna limpia
    foreach ($originalToClean as $info) {
        $orig = trim($info['orig']);
        $newCol = $info['clean'];
        if ($orig === '' || $newCol === '') continue;

        // Sanear el nombre original como se hizo anteriormente
        $san = preg_replace('/[^a-zA-Z0-9_]/', '_', str_replace(' ', '_', $orig));
        $san = strtolower($san);

        // Generar candidatos: nombre completo saneado y versión truncada a 64 chars
        $full = $san;
        $trunc = $full;
        $maxIdentifierLength = 64;
        if (mb_strlen($trunc, 'UTF-8') > $maxIdentifierLength) {
            $trunc = mb_substr($trunc, 0, $maxIdentifierLength, 'UTF-8');
            $trunc = rtrim($trunc, '_');
        }

        // Buscar columnas antiguas que podrían contener los datos
        $candidates = [];
        if (in_array($full, $existingCols)) $candidates[] = $full;
        if (in_array($trunc, $existingCols) && $trunc !== $newCol) $candidates[] = $trunc;

        // También detectar columnas parecidas (ej: versiones truncadas con sufijos)
        foreach ($existingCols as $ec) {
            if ($ec === $newCol) continue;
            if (strpos($ec, $san) === 0 || strpos($san, $ec) === 0) {
                $candidates[] = $ec;
            }
        }

        $candidates = array_unique($candidates);
        foreach ($candidates as $oldCol) {
            if ($oldCol === $newCol) continue;

            // Contar filas que serían migradas (nueva vacía y antigua no vacía)
            $countQ = "SELECT COUNT(*) AS cnt FROM `$tableName` WHERE (`$newCol` IS NULL OR `$newCol` = '') AND (`$oldCol` IS NOT NULL AND `$oldCol` <> '')";
            $countRes = $conn->query($countQ);
            $cnt = 0;
            if ($countRes && ($rowC = $countRes->fetch_assoc())) {
                $cnt = (int)$rowC['cnt'];
            }

            if ($cnt === 0) {
                if ($dryRun) {
                    echo "⏭️ DRY-RUN: no hay filas a migrar desde `$oldCol` → `$newCol` en `$tableName`<br>";
                }
                continue;
            }

            if ($dryRun) {
                echo "🔧 DRY-RUN: se migrarían $cnt filas desde `$oldCol` → `$newCol` en `$tableName`<br>";
                continue;
            }

            if ($migrate || $applyUpdates) {
                $u = "UPDATE `$tableName` SET `$newCol` = `$oldCol` WHERE (`$newCol` IS NULL OR `$newCol` = '') AND (`$oldCol` IS NOT NULL AND `$oldCol` <> '')";
                if ($conn->query($u)) {
                    $affected = $conn->affected_rows;
                    echo "✅ Migradas $affected filas desde `$oldCol` → `$newCol` en `$tableName`<br>";

                    if ($dropOld) {
                        $d = "ALTER TABLE `$tableName` DROP COLUMN `$oldCol`";
                        if ($conn->query($d)) {
                            echo "✅ Columna antigua `$oldCol` eliminada de `$tableName`<br>";
                        } else {
                            echo "⚠️ Error al eliminar columna `$oldCol`: " . $conn->error . "<br>";
                        }
                    }
                } else {
                    echo "⚠️ Error al migrar `$oldCol` → `$newCol`: " . $conn->error . "<br>";
                }
            } else {
                echo "⚠️ Para ejecutar la migración activa ?migrate=1 (o usa ?migrate=1&drop_old=1 para eliminar la columna antigua).<br>";
            }
        }
    }

    // Insertar datos en la tabla
    $columnsNames = implode(',', array_map(function ($col) {
        return "`$col`";
    }, $cleanHeaders));

    $placeholders = implode(',', array_fill(0, count($cleanHeaders), '?'));
    $sqlInsert = "INSERT INTO `$tableName` ($columnsNames, `first_contact_channel`, `how_did_you_meet`, `hear_about_us`) VALUES ($placeholders, 'leadform', '3', '1')";

    $stmt = $conn->prepare($sqlInsert);
    if (!$stmt) {
        echo "<p>❌ Error en prepare para tabla '$tableName': " . $conn->error . "</p>";
        continue;
    }

    $insertedRows = 0;
    $skippedRows = 0;
    $duplicateEmails = 0;
    $updatedPhones = 0; // contador de filas que se actualizaron para agregar teléfono
    $candidateUpdates = 0; // filas candidatas a actualizar (dry-run)
    $updatedConsent = 0; // contador de campos de consentimiento actualizados
    $candidateConsentUpdates = 0; // candidatos a actualizar campo de consentimiento (dry-run)
    $testEmails = 0; // Contador para emails de prueba
    $skippedNoEmail = 0; // Filas omitidas por no tener email válido
    // Detalles de inserciones para notificación por correo
    $insertedDetails = [];
    // IDs de las filas insertadas en esta ejecución (para marcar como notificadas)
    $insertedIds = []; 

    foreach ($values as $index => $row) {
        // Saltar todas las filas hasta e incluyendo la fila de encabezados
        if ($index <= $headerRowIndex)
            continue;

        // Verificar si la fila es un título repetido (comparar con el encabezado original)
        $isHeaderRow = true;
        for ($i = 0; $i < count($headers); $i++) {
            $cellValue = isset($row[$i]) ? trim($row[$i]) : '';
            $headerValue = trim($headers[$i]);

            // Si al menos una celda no coincide con el encabezado, no es fila de título
            if (strcasecmp($cellValue, $headerValue) !== 0) {
                $isHeaderRow = false;
                break;
            }
        }

        // Si es una fila de título repetido, saltarla
        if ($isHeaderRow) {
            $skippedRows++;
            if ($dryRun)
                echo "⏭️ DRY-RUN: fila $index saltada porque coincide con encabezado\n<br>";
            continue;
        }

        // Verificar si la fila está completamente vacía
        $isEmpty = true;
        foreach ($row as $cell) {
            if (!empty(trim($cell ?? ''))) {
                $isEmpty = false;
                break;
            }
        }

        // Si la fila está vacía, saltarla
        if ($isEmpty) {
            $skippedRows++;
            if ($dryRun)
                echo "⏭️ DRY-RUN: fila $index saltada porque está vacía\n<br>";
            continue;
        }

        // Ignorar filas con el email de prueba 'test@fb.com' (robusto: mayúsculas/espacios y buscar en toda la fila si no hay columna email)
        $foundTestEmail = false;
        if ($hasEmailColumn && $emailColumnIndex >= 0) {
            $emailValue = isset($row[$emailColumnIndex]) ? trim(strtolower($row[$emailColumnIndex])) : '';
            if ($emailValue === 'test@fb.com' || stripos($emailValue, 'test@fb.com') !== false) {
                $foundTestEmail = true;
            }
        } else {
            // Buscar en cualquier celda de la fila por si el encabezado no fue detectado
            foreach ($row as $cell) {
                if (!empty($cell) && stripos(trim($cell), 'test@fb.com') !== false) {
                    $foundTestEmail = true;
                    break;
                }
            }
        }

        if ($foundTestEmail) {
            $testEmails++;
            if ($dryRun)
                echo "⏭️ DRY-RUN: fila $index con email de prueba omitida (test@fb.com)<br>";
            continue; // Saltar este registro
        }

        // Validar que exista y sea un email válido; si no hay columna email, buscar en la fila
        $foundEmailValue = '';
        if ($hasEmailColumn && $emailColumnIndex >= 0) {
            $foundEmailValue = isset($row[$emailColumnIndex]) ? trim($row[$emailColumnIndex]) : '';
        } else {
            foreach ($row as $cell) {
                if (!empty($cell) && filter_var(trim($cell, "' \t\n\r"), FILTER_VALIDATE_EMAIL)) {
                    $foundEmailValue = trim($cell);
                    break;
                }
            }
        }
        // Normalizar y limpiar comillas y espacios
        $foundEmailValue = trim($foundEmailValue, "\"' ");

        // Si no hay email o no es válido, omitir fila
        if (empty($foundEmailValue) || !filter_var($foundEmailValue, FILTER_VALIDATE_EMAIL)) {
            $skippedRows++;
            $skippedNoEmail++;
            if ($dryRun)
                echo "⏭️ DRY-RUN: fila $index omitida por no tener email válido (" . htmlspecialchars($foundEmailValue) . ")<br>";
            continue;
        }

        // Verificar si el email ya existe en la base de datos
        if ($hasEmailColumn && $emailColumnIndex >= 0) {
            $emailValue = isset($row[$emailColumnIndex]) ? trim($row[$emailColumnIndex]) : '';

            if (!empty($emailValue)) {
                // Si existe el email, en lugar de saltar siempre, intentar actualizar el teléfono
                $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM `$tableName` WHERE `email` = ?");
                $checkStmt->bind_param('s', $emailValue);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $emailExists = $result->fetch_assoc()['count'] > 0;
                $checkStmt->close();

                if ($emailExists) {
                    // Si la tabla tiene una columna de teléfono y el valor entrante no está vacío,
                    // intentar actualizar la fila existente si el campo phone está vacío.
                    $incomingPhone = '';
                    if ($hasPhoneColumn && $phoneColumnIndex >= 0) {
                        // Encontrar la posición del índice original dentro del mapeo columnIndexMap
                        $pos = array_search($phoneColumnIndex, $columnIndexMap);
                        if ($pos !== false && isset($row[$phoneColumnIndex])) {
                            $incomingPhone = trim($row[$phoneColumnIndex]);
                        }
                    }

                    if (!empty($incomingPhone)) {
                        // Obtener el teléfono existente en la DB
                        $phoneSel = $conn->prepare("SELECT `phone` FROM `$tableName` WHERE `email` = ? LIMIT 1");
                        $phoneSel->bind_param('s', $emailValue);
                        $phoneSel->execute();
                        $phoneRes = $phoneSel->get_result();
                        $existingPhone = '';
                        if ($rowr = $phoneRes->fetch_assoc()) {
                            $existingPhone = trim($rowr['phone'] ?? '');
                        }
                        $phoneSel->close();

                        if (empty($existingPhone)) {
                            // Tenemos candidato para actualizar teléfono
                            if ($dryRun) {
                                // dry-run: show the full row data values for debugging
                                $display = [];
                                foreach ($columnIndexMap as $cidx) {
                                    $display[] = isset($row[$cidx]) ? $row[$cidx] : '';
                                }
                                echo "🟡 DRY-RUN (fila $index): candidato a actualización (email=$emailValue) incomingPhone=" . htmlspecialchars($incomingPhone) . " — row values: " . htmlspecialchars(json_encode($display, JSON_UNESCAPED_UNICODE)) . "<br>";
                                $candidateUpdates++;
                            } elseif ($applyUpdates) {
                                // Actualizar el teléfono para la fila existente
                                $update = $conn->prepare("UPDATE `$tableName` SET `phone` = ? WHERE `email` = ?");
                                $update->bind_param('ss', $incomingPhone, $emailValue);
                                if ($update->execute()) {
                                    $updatedPhones++;
                                } else {
                                    echo "⚠️ Error al actualizar teléfono para $emailValue: " . $update->error . "<br>";
                                }
                                $update->close();
                            } else {
                                // Neither dry-run nor apply -> just count as candidate and skip
                                $candidateUpdates++;
                            }

                            // Después de considerar/actualizar, no insertar un duplicado
                            continue;
                        }

                        // --- Nuevo: actualizar campo de consentimiento si viene en la fila y está vacío en DB ---
                        if (!empty($consentColName) && $consentOrigIndex !== null) {
                            $incomingConsent = isset($row[$consentOrigIndex]) ? trim($row[$consentOrigIndex]) : '';
                            if ($incomingConsent !== '') {
                                // Obtener el valor existente
                                $consSel = $conn->prepare("SELECT `$consentColName` FROM `$tableName` WHERE `email` = ? LIMIT 1");
                                $consSel->bind_param('s', $emailValue);
                                $consSel->execute();
                                $consRes = $consSel->get_result();
                                $existingConsent = '';
                                if ($rcons = $consRes->fetch_assoc()) {
                                    $existingConsent = trim($rcons[$consentColName] ?? '');
                                }
                                $consSel->close();

                                if ($existingConsent === '') {
                                    if ($dryRun) {
                                        echo "🟡 DRY-RUN (fila $index): candidato a actualizar `$consentColName` para <strong>$emailValue</strong> -> <code>" . htmlspecialchars($incomingConsent) . "</code><br>";
                                        $candidateConsentUpdates++;
                                    } elseif ($applyUpdates) {
                                        $u = $conn->prepare("UPDATE `$tableName` SET `$consentColName` = ? WHERE `email` = ?");
                                        $u->bind_param('ss', $incomingConsent, $emailValue);
                                        if ($u->execute()) {
                                            $updatedConsent++;
                                            echo "✅ Consentimiento actualizado para <strong>$emailValue</strong> -> <code>" . htmlspecialchars($incomingConsent) . "</code><br>";
                                        } else {
                                            echo "⚠️ Error al actualizar `$consentColName` para $emailValue: " . $u->error . "<br>";
                                        }
                                        $u->close();
                                    } else {
                                        $candidateConsentUpdates++;
                                    }

                                    // Después de considerar/actualizar, no insertar un duplicado
                                    continue;
                                }
                            }
                        }
                    }

                    // Si no hay teléfono entrante o ya existe teléfono, lo contamos como duplicado y saltamos
                    $duplicateEmails++;
                    if ($dryRun)
                        echo "⏭️ DRY-RUN: fila $index duplicada o sin teléfono entrante (email=$emailValue)<br>";
                    continue;
                }
            }
        }

        // Completar celdas vacías y reemplazar null por ''
        // Solo tomar los valores de las columnas válidas usando el mapeo
        $validRow = [];
        foreach ($columnIndexMap as $origIndex) {
            $validRow[] = isset($row[$origIndex]) ? $row[$origIndex] : '';
        }

        $validRow = array_map(function ($v) {
            // Convertir a string y limpiar caracteres problemáticos
            $value = $v ?? '';
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }, $validRow);

        // Para ANDROMEDA LEADFORM: asegurar que el campo de campaña (campaign_name) comience con "E10-"
        if (strtolower($sheetName) === 'andromeda leadform') {
            $campaignPos = null;
            foreach ($cleanHeaders as $ci => $colName) {
                $col = strtolower($colName);
                // Considerar variantes comunes y posibles errores tipográficos
                if (in_array($col, ['campaign_name', 'campaing_name', 'campaignname', 'campaingname', 'utm_campaign'])) {
                    $campaignPos = $ci;
                    break;
                }
            }

            if ($campaignPos !== null) {
                $orig = trim($validRow[$campaignPos]);
                $orig = trim($orig, "\"' ");
                if ($orig !== '' && stripos($orig, 'E10-') !== 0) {
                    $validRow[$campaignPos] = 'E10-' . $orig;
                    if ($dryRun)
                        echo "🔧 DRY-RUN: fila $index - campaign_name modificado a " . htmlspecialchars($validRow[$campaignPos]) . "<br>";
                }
            }
        }

        $types = str_repeat('s', count($validRow));
        $stmt->bind_param($types, ...$validRow);

        if ($stmt->execute()) {
            $insertedRows++;
            // Capturar nombre y campaña para notificación
            $leadName = '';
            $leadEmail = '';
            $campaignVal = '';

            // Buscar email en cleanHeaders
            $emailPos = array_search('email', $cleanHeaders);
            if ($emailPos !== false && isset($validRow[$emailPos])) {
                $leadEmail = trim($validRow[$emailPos]);
            }

            // Buscar nombre - posibles columnas
            $nameCandidates = ['name','nombre','full_name','fullname','first_name','first','last_name','last','nombre_completo','lead_name'];
            foreach ($nameCandidates as $nc) {
                $pos = array_search($nc, $cleanHeaders);
                if ($pos !== false && isset($validRow[$pos]) && trim($validRow[$pos]) !== '') {
                    $leadName = trim($validRow[$pos]);
                    break;
                }
            }

            // Si no hay nombre, intentar combinar first_name + last_name
            if ($leadName === '') {
                $posF = array_search('first_name', $cleanHeaders);
                $posL = array_search('last_name', $cleanHeaders);
                if ($posF !== false && $posL !== false) {
                    $first = trim($validRow[$posF] ?? '');
                    $last = trim($validRow[$posL] ?? '');
                    if ($first !== '' || $last !== '') {
                        $leadName = trim($first . ' ' . $last);
                    }
                }
            }

            // Buscar campaña por columnas que contengan 'campaign' o 'utm'
            foreach ($cleanHeaders as $ci => $cn) {
                if (stripos($cn, 'campaign') !== false || stripos($cn, 'utm') !== false) {
                    if (isset($validRow[$ci]) && trim($validRow[$ci]) !== '') {
                        $campaignVal = trim($validRow[$ci]);
                        break;
                    }
                }
            }

            // Registrar ID de la fila insertada y los detalles para notificación
            $lastId = $conn->insert_id;
            $insertedIds[] = $lastId;
            // Store campaign under multiple keys to be compatible with different consumers (campaign, campaign_name, campain_name)
            $insertedDetails[] = [
                'id' => $lastId,
                'name' => $leadName,
                'email' => $leadEmail,
                'campaign' => $campaignVal,
                'campaign_name' => $campaignVal,
                'campain_name' => $campaignVal,
                'when' => date('Y-m-d H:i:s')
            ];
        } else {
            // Provide detailed diagnostics about the failing insert
            $debugValues = json_encode($validRow, JSON_UNESCAPED_UNICODE);
            echo "⚠️ Error insertando fila $index en '$tableName': " . $stmt->error . "<br>";
            echo "   SQL values (count=" . count($validRow) . "): " . htmlspecialchars($debugValues) . "<br>";
            // If in dry-run, also show the mapped column names to help debugging
            if ($dryRun) {
                echo "   Mapped columnas: " . htmlspecialchars(json_encode($cleanHeaders, JSON_UNESCAPED_UNICODE)) . "<br>";
            }
        }
    }

    $stmt->close();

    // Enviar notificación por correo si hubo nuevas inserciones en esta hoja
    if (!empty($insertedDetails) && $insertedRows > 0) {
        $timestamp = date('Y-m-d H:i:s');
        $subject = "New leads imported: {$insertedRows} into {$tableName}";
        $titulo = "New leads imported into {$tableName}";
        $despedida = "Best regards,";
        // Build email body (HTML)
        $body = "<p><strong>{$insertedRows}</strong> new records were inserted into the table <strong>{$tableName}</strong> on <strong>{$timestamp}</strong>.</p>";
        $body .= "<p><strong>Details (name — campaign):</strong></p><ul>";
        foreach ($insertedDetails as $d) {
            // Safe name fallback (use email if name empty)
            $nameRaw = $d['name'] ?? ($d['email'] ?? 'No name');
            $name = htmlspecialchars($nameRaw !== '' ? $nameRaw : ($d['email'] ?? 'No name'));
            // Prefer legacy typo 'campain_name', then 'campaign_name', then 'campaign'
            $campaignRaw = $d['campain_name'] ?? $d['campaign_name'] ?? $d['campaign'] ?? '';
            $campaign = htmlspecialchars($campaignRaw !== '' ? $campaignRaw : 'Not specified');
            $body .= "<li><strong>{$name}</strong> — Campaign: <em>{$campaign}</em></li>";
        }
        $body .= "</ul>";
        // No enviar si estamos en modo dry-run
        if ($dryRun) {
            $idsPreview = !empty($insertedIds) ? implode(',', array_map('intval', $insertedIds)) : 'N/A';
            echo "<p>✉️ DRY-RUN: notificación a juanpablo.ggomez@gmail.com preparada ({$insertedRows} registros). IDs: {$idsPreview}</p>";
        } else {
            enviarCorreo('juanpablo.ggomez@gmail.com', $subject, $titulo, $body, $despedida, 'system');
            echo "<p>✉️ Notificación enviada a juanpablo.ggomez@gmail.com: {$insertedRows} registros</p>";

            // Marcar las filas insertadas como notificadas para que no se vuelvan a enviar en futuras ejecuciones
            if (!empty($insertedIds)) {
                $ids = implode(',', array_map('intval', $insertedIds));
                $u = "UPDATE `$tableName` SET `notified` = 1 WHERE id IN ($ids)";
                if ($conn->query($u)) {
                    echo "<p>✅ Filas marcadas como notificadas: $ids</p>";
                } else {
                    echo "<p>⚠️ Error marcando notified: " . $conn->error . "</p>";
                }
            }
        }
    }

    // Guardar resultados de esta hoja
    $allResults[] = [
        'sheetName' => $sheetName,
        'tableName' => $tableName,
        'headers' => $headers,
        'values' => $values,
        'headerRowIndex' => $headerRowIndex,
        'insertedRows' => $insertedRows,
        'duplicateEmails' => $duplicateEmails,
        'testEmails' => $testEmails,
        'updatedPhones' => $updatedPhones,
        'candidateUpdates' => $candidateUpdates,
        'skippedRows' => $skippedRows,
        'skippedNoEmail' => $skippedNoEmail,
        'updatedConsent' => $updatedConsent,
        'candidateConsentUpdates' => $candidateConsentUpdates,
        // Indicate whether we detected / mapped a phone column
        'hasPhoneColumn' => $hasPhoneColumn,
        'phoneColumnIndex' => $phoneColumnIndex,
        'totalRows' => count($values) - $headerRowIndex - 1 // Total desde después del encabezado
    ];
}

// --------------------------
// 5️⃣ Mostrar datos en tabla HTML
// --------------------------
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importación de Leads - Múltiples Hojas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 100%;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        h2 {
            color: #4CAF50;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }

        .info {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #4caf50;
        }

        .summary {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #2196F3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }

        th {
            background-color: #4CAF50;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            position: sticky;
            top: 0;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .table-wrapper {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }

        .sheet-section {
            margin-bottom: 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #fafafa;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>📊 Importación de Leads desde Google Sheets</h1>
        <?php if ($dryRun): ?>
            <div
                style="background:#fff3cd;border-left:4px solid #ffeeba;padding:10px;margin-bottom:12px;border-radius:4px;">
                <strong>Modo DRY-RUN:</strong> No se escribirá en la base de datos. Consulta los candidatos a actualizar y
                si todo está bien, ejecuta <code>?apply=1</code> para aplicar los cambios. (Haz backup antes)
            </div>
        <?php elseif ($applyUpdates): ?>
            <div
                style="background:#f8d7da;border-left:4px solid #f5c6cb;padding:10px;margin-bottom:12px;border-radius:4px;">
                <strong>Modo APPLY:</strong> Se actualizarán los teléfonos en la base de datos. ASEGÚRATE de tener una copia
                de seguridad antes de continuar.
            </div>
        <?php endif; ?>
        <div
            style="background:#e9ecef;border-left:4px solid #dee2e6;padding:10px;margin-bottom:12px;border-radius:4px;">
            <strong>Ayuda rápida:</strong>
            <ul style="margin:6px 0;padding-left:18px;">
                <li>Preview (sin escribir): <code>?dry=1</code></li>
                <li>Aplicar actualizaciones (solo teléfonos vacíos): <code>?apply=1</code> — recuerda hacer backup antes
                </li>
                <li>Procesar sólo una hoja para pruebas: <code>?sheet=FORMUSA1</code></li>
                <li>Ejemplo combinado (previsualizar FORMUSA1): <code>?sheet=FORMUSA1&amp;dry=1</code></li>
            </ul>
        </div>

        <div class="summary">
            <strong>📋 Resumen General</strong><br>
            <strong>Total de hojas procesadas:</strong> <?php echo count($allResults); ?><br>
            <?php
            $totalInserted = 0;
            $totalDuplicates = 0;
            $totalSkipped = 0;
            $totalUpdatedPhones = 0;
            $totalCandidates = 0;
            $totalNoEmail = 0;
            $totalUpdatedConsent = 0;
            $totalConsentCandidates = 0;
            foreach ($allResults as $result) {
                $totalInserted += $result['insertedRows'];
                $totalDuplicates += $result['duplicateEmails'];
                $totalSkipped += $result['skippedRows'];
                $totalUpdatedPhones += $result['updatedPhones'] ?? 0;
                $totalCandidates += $result['candidateUpdates'] ?? 0;
                $totalNoEmail += $result['skippedNoEmail'] ?? 0;
                $totalUpdatedConsent += $result['updatedConsent'] ?? 0;
                $totalConsentCandidates += $result['candidateConsentUpdates'] ?? 0;
            }
            ?>
            <strong>Total registros insertados:</strong> <?php echo $totalInserted; ?><br>
            <strong>Total emails duplicados:</strong> <?php echo $totalDuplicates; ?><br>
            <strong>Total teléfonos actualizados:</strong> <?php echo $totalUpdatedPhones; ?><br>
            <strong>Total consentimientos actualizados:</strong> <?php echo $totalUpdatedConsent; ?><br>
            <strong>Total candidatos a actualizar (si ejecutas ?apply=1):</strong> <?php echo $totalCandidates; ?><br>
            <strong>Total candidatos a actualizar (consentimiento):</strong> <?php echo $totalConsentCandidates; ?><br>
            <strong>Total filas sin email válido:</strong> <?php echo $totalNoEmail; ?><br>
            <strong>Total filas omitidas:</strong> <?php echo $totalSkipped; ?>
        </div>

        <?php foreach ($allResults as $result): ?>
            <div class="sheet-section">
                <h2>📄 Hoja: <?php echo htmlspecialchars($result['sheetName']); ?></h2>

                <div class="info">
                    <strong>✅ Importación exitosa</strong><br>
                    <strong>Tabla MySQL:</strong> <?php echo htmlspecialchars($result['tableName']); ?><br>
                    <strong>Registros insertados:</strong> <?php echo $result['insertedRows']; ?><br>
                    <strong>Emails duplicados:</strong> <?php echo $result['duplicateEmails']; ?> (omitidos)<br>
                    <strong>Teléfonos actualizados:</strong> <?php echo $result['updatedPhones']; ?> (se completaron para
                    registros existentes)<br>
                    <strong>Consentimientos actualizados:</strong> <?php echo $result['updatedConsent']; ?> (se completaron para registros existentes)<br>
                    <strong>Candidatos a actualizar (teléfono):</strong> <?php echo $result['candidateUpdates']; ?> (si ejecutas
                    <code>?apply=1</code> o en modo dry-run verás detalles)<br>
                    <strong>Candidatos a actualizar (consentimiento):</strong> <?php echo $result['candidateConsentUpdates']; ?> (si ejecutas
                    <code>?apply=1</code> o en modo dry-run verás detalles)<br>
                    <strong>Filas omitidas:</strong> <?php echo $result['skippedRows']; ?> (títulos repetidos o vacías)<br>
                    <strong>Filas sin email válido:</strong> <?php echo $result['skippedNoEmail']; ?> (omitidas)<br>
                    <?php if (!empty($result['hasPhoneColumn'])): ?>
                        <strong>Nota:</strong> La columna <code>phone_number</code> del Excel fue detectada y se mapea a
                        <code>phone</code> en la base de datos.<br>
                    <?php endif; ?>
                    <strong>Total filas procesadas:</strong> <?php echo $result['totalRows']; ?>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <?php foreach ($result['headers'] as $header): ?>
                                    <th><?php echo htmlspecialchars($header); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['values'] as $index => $row): ?>
                                <?php
                                // Saltar todas las filas hasta e incluyendo la fila de encabezados
                                if ($index <= $result['headerRowIndex'])
                                    continue;

                                // Verificar si es fila de título repetido
                                $isHeaderRow = true;
                                for ($i = 0; $i < count($result['headers']); $i++) {
                                    $cellValue = isset($row[$i]) ? trim($row[$i]) : '';
                                    $headerValue = trim($result['headers'][$i]);
                                    if (strcasecmp($cellValue, $headerValue) !== 0) {
                                        $isHeaderRow = false;
                                        break;
                                    }
                                }
                                if ($isHeaderRow)
                                    continue; // Saltar títulos repetidos
                        
                                // Verificar si la fila está vacía
                                $isEmpty = true;
                                foreach ($row as $cell) {
                                    if (!empty(trim($cell ?? ''))) {
                                        $isEmpty = false;
                                        break;
                                    }
                                }
                                if ($isEmpty)
                                    continue; // Saltar filas vacías
                                ?>
                                <tr>
                                    <?php
                                    // Completar celdas vacías para la vista
                                    $row = array_pad($row, count($result['headers']), '');
                                    foreach ($row as $cell):
                                        ?>
                                        <td><?php echo htmlspecialchars($cell ?? ''); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>

</html>
<?php
$conn->close();
