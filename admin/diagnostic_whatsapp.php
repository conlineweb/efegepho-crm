<?php
// diagnostic_whatsapp.php
// Ejecuta este archivo para diagnosticar problemas con el envío de WhatsApp

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Diagnóstico del Bot de WhatsApp</h2>\n";
echo "<pre>\n";

// 1. Verificar credenciales de Twilio
echo "=== 1. VERIFICACIÓN DE CREDENCIALES ===\n";
$TWILIO_ACCOUNT_SID = getenv('TWILIO_ACCOUNT_SID') ?: 'ACe545cc9bfdfb41f417c8e1cc34062678';
$TWILIO_AUTH_TOKEN = getenv('TWILIO_AUTH_TOKEN') ?: '6068c519f69979eefe00f63d61ad25a8';
// Debe incluir el prefijo whatsapp: ejemplo: 'whatsapp:+1415XXXXXXX'
$TWILIO_FROM = getenv('TWILIO_FROM') ?: 'whatsapp:+15557419621';

echo "TWILIO_ACCOUNT_SID: " . ($TWILIO_ACCOUNT_SID === 'xxx' ? "❌ NO CONFIGURADO" : "✓ Configurado") . "\n";
echo "TWILIO_AUTH_TOKEN: " . ($TWILIO_AUTH_TOKEN === 'xxx' ? "❌ NO CONFIGURADO" : "✓ Configurado") . "\n";
echo "TWILIO_FROM: $TWILIO_FROM\n\n";

// 2. Verificar conexión a base de datos
echo "=== 2. VERIFICACIÓN DE BASE DE DATOS ===\n";
if (file_exists('conn.php')) {
    include 'conn.php';
    if (isset($conn) && $conn->ping()) {
        echo "✓ Conexión a base de datos exitosa\n\n";
    } else {
        echo "❌ Error en conexión a base de datos\n\n";
        exit;
    }
} else {
    echo "❌ Archivo conn.php no encontrado\n\n";
    exit;
}

// 3. Verificar tablas de leads
echo "=== 3. VERIFICACIÓN DE TABLAS ===\n";
$tables = [];
$res = $conn->query("SELECT nombre FROM tablas_leads ORDER BY nombre");
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        $tables[] = $r['nombre'];
    }
    echo "Tablas encontradas: " . implode(", ", $tables) . "\n\n";
} else {
    echo "❌ No se encontraron tablas en tablas_leads\n\n";
}

// 4. Verificar leads pendientes por tabla
echo "=== 4. LEADS PENDIENTES DE ENVÍO ===\n";
$totalPending = 0;
foreach ($tables as $table) {
    // Verificar que exista la columna phone
    $cols = $conn->query("SHOW COLUMNS FROM `{$conn->real_escape_string($table)}` LIKE 'phone'");
    if (!$cols || $cols->num_rows === 0) {
        echo "⚠ Tabla '$table' no tiene columna 'phone'\n";
        continue;
    }
    
    // Verificar columna whatsapp_bot_sent
    $colCheck = $conn->query("SHOW COLUMNS FROM `{$conn->real_escape_string($table)}` LIKE 'whatsapp_bot_sent'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        echo "⚠ Tabla '$table' no tiene columna 'whatsapp_bot_sent' (se agregará automáticamente)\n";
    }
    
    // Contar leads pendientes
    $sql = "SELECT COUNT(*) as total FROM `{$conn->real_escape_string($table)}` 
            WHERE usuario_asignado = 100 
            AND (whatsapp_bot_sent IS NULL OR whatsapp_bot_sent = 0) 
            AND phone IS NOT NULL";
    $q = $conn->query($sql);
    if ($q) {
        $row = $q->fetch_assoc();
        $count = $row['total'];
        $totalPending += $count;
        if ($count > 0) {
            echo "✓ Tabla '$table': $count leads pendientes\n";
        }
    }
}
echo "\nTotal leads pendientes: $totalPending\n\n";

if ($totalPending === 0) {
    echo "❌ NO HAY LEADS PENDIENTES DE ENVÍO\n";
    echo "Verifica:\n";
    echo "- Que existan leads con usuario_asignado = 100\n";
    echo "- Que tengan phone no nulo\n";
    echo "- Que whatsapp_bot_sent sea 0 o NULL\n\n";
}

// 5. Verificar muestra de leads
echo "=== 5. MUESTRA DE LEADS (primeros 3) ===\n";
foreach ($tables as $table) {
    $sql = "SELECT id, full_name, phone, usuario_asignado, whatsapp_bot_sent 
            FROM `{$conn->real_escape_string($table)}` 
            WHERE usuario_asignado = 100 
            AND (whatsapp_bot_sent IS NULL OR whatsapp_bot_sent = 0) 
            AND phone IS NOT NULL 
            LIMIT 3";
    $q = $conn->query($sql);
    if ($q && $q->num_rows > 0) {
        echo "\nTabla: $table\n";
        while ($lead = $q->fetch_assoc()) {
            echo "  - ID: {$lead['id']}, Nombre: {$lead['full_name']}, Phone: {$lead['phone']}\n";
        }
    }
}
echo "\n";

// 6. Verificar rutas de archivos
echo "=== 6. VERIFICACIÓN DE RUTAS ===\n";
echo "Directorio actual: " . __DIR__ . "\n";

$sessionDir = '../whatsapp/sessions';
$fullPath = realpath(__DIR__ . '/' . $sessionDir);
echo "Ruta relativa sesiones: $sessionDir\n";
echo "Ruta absoluta sesiones: " . ($fullPath ?: "❌ NO EXISTE") . "\n";

if (!is_dir($sessionDir)) {
    echo "⚠ Directorio de sesiones no existe, intentando crear...\n";
    if (mkdir($sessionDir, 0777, true)) {
        echo "✓ Directorio creado exitosamente\n";
    } else {
        echo "❌ No se pudo crear el directorio\n";
    }
} else {
    echo "✓ Directorio de sesiones existe\n";
    echo "Permisos: " . substr(sprintf('%o', fileperms($sessionDir)), -4) . "\n";
}

// Verificar archivo de log
$logFile = __DIR__ . '/cron_send_whatsapp.log';
echo "\nArchivo de log: $logFile\n";
if (file_exists($logFile)) {
    echo "✓ Archivo de log existe\n";
    echo "Últimas 10 líneas:\n";
    $lines = file($logFile);
    $last10 = array_slice($lines, -10);
    foreach ($last10 as $line) {
        echo "  " . $line;
    }
} else {
    echo "⚠ Archivo de log no existe (se creará al ejecutar)\n";
}

echo "\n";

// 7. Test de normalización de teléfono
echo "=== 7. TEST DE NORMALIZACIÓN DE TELÉFONO ===\n";
function normalize_phone($raw) {
    $p = preg_replace('/\D+/', '', (string) $raw);
    if (empty($p)) return null;
    if (strpos($p, '00') === 0) {
        $p = ltrim($p, '0');
    }
    if (strpos($p, '52') === 0) {
        $rest = substr($p, 2);
        if (strlen($rest) === 10) {
            return '+521' . $rest;
        }
        if (strlen($rest) === 11 && substr($rest, 0, 1) === '1') {
            return '+' . $p;
        }
        return '+' . $p;
    }
    if (strlen($p) == 10) {
        return '+521' . $p;
    }
    if (strlen($p) == 11 && substr($p, 0, 1) === '1') {
        return '+' . $p;
    }
    return '+' . $p;
}

$testPhones = [
    '5214775579264',
  
];

foreach ($testPhones as $test) {
    echo "$test => " . normalize_phone($test) . "\n";
}

echo "\n";

// 8. Verificar que el cron puede ejecutarse
echo "=== 8. RECOMENDACIONES ===\n";
if ($TWILIO_ACCOUNT_SID === 'xxx' || $TWILIO_AUTH_TOKEN === 'xxx') {
    echo "❌ CRÍTICO: Configura las credenciales de Twilio\n";
    echo "   Agrega al inicio de cron_send_whatsapp.php:\n";
    echo "   \$TWILIO_ACCOUNT_SID = 'tu_account_sid';\n";
    echo "   \$TWILIO_AUTH_TOKEN = 'tu_auth_token';\n";
    echo "   \$TWILIO_FROM = 'whatsapp:+tu_numero';\n\n";
}

if ($totalPending === 0) {
    echo "⚠ No hay leads pendientes de envío\n\n";
}

echo "Para ejecutar manualmente:\n";
echo "  php " . __DIR__ . "/cron_send_whatsapp.php --dry\n\n";

echo "Para ver mensajes de error en tiempo real:\n";
echo "  tail -f $logFile\n\n";

echo "</pre>";
?>