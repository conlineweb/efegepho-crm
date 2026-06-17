<?php
// twilio_debug.php - Diagnóstico detallado de envío de WhatsApp
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Diagnóstico Detallado de Twilio WhatsApp</h2>\n";
echo "<pre>\n";

// Configuración (sustituye con tus datos reales)
// ---------------------------------------------------------
$TWILIO_ACCOUNT_SID = getenv('TWILIO_ACCOUNT_SID') ?: 'ACe545cc9bfdfb41f417c8e1cc34062678';
$TWILIO_AUTH_TOKEN = getenv('TWILIO_AUTH_TOKEN') ?: '6068c519f69979eefe00f63d61ad25a8';
// Debe incluir el prefijo whatsapp: ejemplo: 'whatsapp:+1415XXXXXXX'
$TWILIO_FROM = getenv('TWILIO_FROM') ?: '+15557419621';

// Número de prueba (usa TU número para recibir el test)
$TEST_TO = 'whatsapp:+5214775579264'; // CAMBIA ESTO por tu número de celular

echo "=== CONFIGURACIÓN ===\n";
echo "Account SID: " . substr($TWILIO_ACCOUNT_SID, 0, 10) . "...\n";
echo "From: $TWILIO_FROM\n";
echo "To (prueba): $TEST_TO\n\n";

// Test 1: Verificar credenciales con API de Twilio
echo "=== TEST 1: VERIFICAR CUENTA ===\n";
$url = "https://api.twilio.com/2010-04-01/Accounts/{$TWILIO_ACCOUNT_SID}.json";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $TWILIO_ACCOUNT_SID . ':' . $TWILIO_AUTH_TOKEN,
    CURLOPT_TIMEOUT => 30
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 200) {
    $data = json_decode($res, true);
    echo "✓ Credenciales válidas\n";
    echo "  Nombre cuenta: " . ($data['friendly_name'] ?? 'N/A') . "\n";
    echo "  Status: " . ($data['status'] ?? 'N/A') . "\n\n";
} else {
    echo "❌ ERROR: Credenciales inválidas (HTTP $code)\n";
    echo "Respuesta: $res\n\n";
    exit;
}

// Test 2: Verificar el número FROM
echo "=== TEST 2: VERIFICAR NÚMERO FROM ===\n";
$phoneNumber = str_replace('whatsapp:', '', $TWILIO_FROM);
$phoneNumber = str_replace('+', '', $phoneNumber);

$url = "https://api.twilio.com/2010-04-01/Accounts/{$TWILIO_ACCOUNT_SID}/IncomingPhoneNumbers.json";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $TWILIO_ACCOUNT_SID . ':' . $TWILIO_AUTH_TOKEN,
    CURLOPT_TIMEOUT => 30
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 200) {
    $data = json_decode($res, true);
    $found = false;
    
    if (isset($data['incoming_phone_numbers'])) {
        foreach ($data['incoming_phone_numbers'] as $num) {
            $cleanNum = str_replace('+', '', $num['phone_number']);
            echo "  - Número: {$num['phone_number']} (SID: {$num['sid']})\n";
            
            if ($cleanNum === $phoneNumber) {
                $found = true;
                echo "    ✓ ESTE es tu número FROM\n";
                
                // Verificar capacidades
                if (isset($num['capabilities'])) {
                    echo "    Capacidades:\n";
                    echo "      SMS: " . ($num['capabilities']['sms'] ? 'Sí' : 'No') . "\n";
                    echo "      Voice: " . ($num['capabilities']['voice'] ? 'Sí' : 'No') . "\n";
                    echo "      MMS: " . ($num['capabilities']['mms'] ? 'Sí' : 'No') . "\n";
                }
            }
        }
    }
    
    if (!$found) {
        echo "\n❌ PROBLEMA: El número $TWILIO_FROM NO está en tu cuenta de Twilio\n";
        echo "   Revisa que el número esté correctamente escrito\n";
    }
    echo "\n";
} else {
    echo "❌ Error al consultar números (HTTP $code)\n\n";
}

// Test 3: Verificar WhatsApp Sender
echo "=== TEST 3: VERIFICAR WHATSAPP SENDER ===\n";
$url = "https://api.twilio.com/2010-04-01/Accounts/{$TWILIO_ACCOUNT_SID}/Messages/WhatsAppSenders.json";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $TWILIO_ACCOUNT_SID . ':' . $TWILIO_AUTH_TOKEN,
    CURLOPT_TIMEOUT => 30
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 200) {
    $data = json_decode($res, true);
    if (isset($data['senders']) && count($data['senders']) > 0) {
        echo "Senders de WhatsApp configurados:\n";
        foreach ($data['senders'] as $sender) {
            echo "  - {$sender['from_number']} (Status: {$sender['status']})\n";
        }
    } else {
        echo "⚠️  No hay WhatsApp Senders configurados\n";
        echo "   Esto significa que tu número NO está habilitado para WhatsApp\n";
    }
} else {
    echo "⚠️  No se pudo verificar WhatsApp Senders (esto es normal para algunos tipos de cuenta)\n";
}
echo "\n";

// Test 4: ENVÍO REAL de mensaje
echo "=== TEST 4: ENVÍO REAL DE MENSAJE ===\n";
echo "Enviando mensaje de prueba a $TEST_TO...\n\n";

$url = "https://api.twilio.com/2010-04-01/Accounts/{$TWILIO_ACCOUNT_SID}/Messages.json";
$body = "🧪 Mensaje de prueba desde script de diagnóstico - " . date('H:i:s');

$post = [
    'From' => $TWILIO_FROM,
    'To' => $TEST_TO,
    'Body' => $body
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $TWILIO_ACCOUNT_SID . ':' . $TWILIO_AUTH_TOKEN,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_VERBOSE => true,
    CURLOPT_STDERR => fopen('php://temp', 'rw+')
]);

$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);

// Obtener detalles del verbose
rewind(curl_getinfo($ch, CURLOPT_STDERR));
$verbose = stream_get_contents(curl_getinfo($ch, CURLOPT_STDERR));

curl_close($ch);

echo "HTTP Code: $code\n";
if ($err) {
    echo "CURL Error: $err\n";
}

echo "\nRespuesta de Twilio:\n";
echo "-------------------\n";
$formatted = json_decode($res, true);
if ($formatted) {
    echo json_encode($formatted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo $res . "\n";
}
echo "-------------------\n\n";

// Analizar la respuesta
if ($code === 201 || $code === 200) {
    if ($formatted && isset($formatted['sid'])) {
        echo "✓ Mensaje enviado exitosamente!\n";
        echo "  Message SID: {$formatted['sid']}\n";
        echo "  Status: {$formatted['status']}\n";
        echo "  Precio: {$formatted['price']} {$formatted['price_unit']}\n\n";
        
        echo "🔍 Verifica tu celular ($TEST_TO)\n";
        echo "   Si no llega, revisa:\n";
        echo "   1. https://console.twilio.com/us1/monitor/logs/messages/{$formatted['sid']}\n";
        echo "   2. El número TO está correcto y puede recibir WhatsApp\n";
        echo "   3. Tu plantilla de mensaje está aprobada (si usas Twilio con Meta)\n";
    } else {
        echo "⚠️  Respuesta exitosa pero sin SID\n";
    }
} else {
    echo "❌ ERROR AL ENVIAR\n\n";
    
    if ($formatted && isset($formatted['code'])) {
        echo "Código de error Twilio: {$formatted['code']}\n";
        echo "Mensaje: {$formatted['message']}\n\n";
        
        // Errores comunes
        switch ($formatted['code']) {
            case 21211:
                echo "💡 El número TO es inválido o no existe\n";
                break;
            case 21608:
                echo "💡 El número FROM no está habilitado para WhatsApp\n";
                echo "   Solución: Ve a Twilio Console > Messaging > Senders > Habilita WhatsApp\n";
                break;
            case 21610:
                echo "💡 El mensaje requiere una plantilla pre-aprobada\n";
                echo "   Solución: Crea plantillas en Twilio Console > Messaging > Templates\n";
                break;
            case 63016:
                echo "💡 El número TO no ha optado para recibir mensajes de tu número\n";
                echo "   Para números de clientes: necesitas usar plantillas aprobadas\n";
                break;
            case 20003:
                echo "💡 Credenciales inválidas o expiradas\n";
                break;
            default:
                echo "💡 Busca el código {$formatted['code']} en: https://www.twilio.com/docs/api/errors\n";
        }
    }
}

echo "\n=== RECOMENDACIONES ===\n";
echo "1. Revisa los logs de Twilio: https://console.twilio.com/us1/monitor/logs/messages\n";
echo "2. Verifica que tu número tiene WhatsApp habilitado\n";
echo "3. Para mensajes a clientes (no sandbox), necesitas plantillas aprobadas\n";
echo "4. El número TO debe poder recibir WhatsApp\n";

echo "\n</pre>";
?>