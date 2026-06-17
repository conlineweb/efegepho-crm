<?php
header("Content-Type: text/xml");

// ⚠️ RECUERDA: Usa tu API KEY nueva aquí.
$OPENAI_API_KEY = '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'; 

// Obtenemos el teléfono real de quien escribe por WhatsApp
$from_whatsapp_number_raw = preg_replace('/[^0-9]/', '', $_POST['From'] ?? '');

// Normaliza números de WhatsApp a E.164 (en MX fuerza +521 cuando aplique)
function normalize_whatsapp_phone_e164($digits) {
    $digits = preg_replace('/\D+/', '', (string)$digits);
    if ($digits === '') return '';

    // MX: 52 + 10 dígitos -> +521 + 10 dígitos
    if (strpos($digits, '52') === 0 && strlen($digits) === 12) {
        return '+521' . substr($digits, 2);
    }
    // MX ya correcto: 521 + 10 dígitos
    if (strpos($digits, '521') === 0 && strlen($digits) === 13) {
        return '+' . $digits;
    }
    // MX local 10 dígitos
    if (strlen($digits) === 10) {
        return '+521' . $digits;
    }
    // US/CA típico
    if (strlen($digits) === 11 && strpos($digits, '1') === 0) {
        return '+' . $digits;
    }

    // Fallback: anteponer +
    return '+' . $digits;
}

function e164_to_digits($e164) {
    return preg_replace('/\D+/', '', (string)$e164);
}

$from_whatsapp_e164 = normalize_whatsapp_phone_e164($from_whatsapp_number_raw);
$from_whatsapp_number = e164_to_digits($from_whatsapp_e164); // para nombres de archivo/compatibilidad
$rawUserMessage = trim($_POST['Body'] ?? '');
$userMessage = $rawUserMessage;

$sessionDir = __DIR__ . '/sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}

// Directorio para logs de debugging
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Preferimos usar el número normalizado (con 1 si aplica). Si existe una sesión vieja sin el 1, la cargamos.
$sessionFile = "{$sessionDir}/whatsapp_{$from_whatsapp_number}.json";
$legacyDigits = $from_whatsapp_number_raw;
$legacySessionFile = "{$sessionDir}/whatsapp_{$legacyDigits}.json";
$loadedFromLegacySession = false;

// 1. CARGAR HISTORIAL
$messages = [];
if (file_exists($sessionFile)) {
    $messages = json_decode(file_get_contents($sessionFile), true) ?: [];
} elseif ($legacyDigits !== '' && $legacyDigits !== $from_whatsapp_number && file_exists($legacySessionFile)) {
    $messages = json_decode(file_get_contents($legacySessionFile), true) ?: [];
    $loadedFromLegacySession = true;
}

// --- HANDOFF A AGENTE HUMANO ---
// Si el lead pide "hablar con agente", marcamos la sesión para takeover humano y contestamos una sola vez.
$handoffRegex = '/\b(hablar\s+con\s+agente|agente\s+humano|quiero\s+un\s+agente|pasame\s+con\s+un\s+agente|pasame\s+con\s+alguien)\b/i';
if ($rawUserMessage !== '' && preg_match($handoffRegex, $rawUserMessage)) {
    // Guardar meta de handoff
    $sessionMetaFile = __DIR__ . "/sessions/whatsapp_" . preg_replace('/\D+/', '', $from_whatsapp_number) . "_meta.json";
    $meta = [];
    if (file_exists($sessionMetaFile)) {
        $mraw = @file_get_contents($sessionMetaFile);
        $meta = json_decode($mraw, true) ?: [];
    }
    $meta['human_handoff'] = true;
    $meta['human_handoff_requested_at'] = date('c');
    @file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

    // Disparar webhook interno (por si otra parte del sistema quiere reaccionar)
    $webhookUrl = "https://citas.efegepho.com.mx/api/request_human_whatsapp.php";
    $payload = [
        'payload' => [
            'client_phone' => $from_whatsapp_e164,
            'reason' => 'lead_requested_agent'
        ]
    ];
    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);
    curl_exec($ch);
    curl_close($ch);

    // Guardar el mensaje del usuario y responder con un acuse
    // Asegurar que el system prompt exista al inicio
    $isMexico = (strpos($from_whatsapp_e164, '+521') === 0 || strpos($from_whatsapp_e164, '+52') === 0);
    $promptFile = $isMexico ? './prompt_efege.txt' : './prompt_efege_en.txt';
    $systemPrompt = file_exists($promptFile) ? file_get_contents($promptFile) : ($isMexico ? "Eres Alexa de Efege..." : "You are Alexa from Efege...");
    if (empty($messages) || $messages[0]['role'] !== 'system') {
        array_unshift($messages, ["role" => "system", "content" => $systemPrompt]);
    }
    $messages[] = ["role" => "user", "content" => $rawUserMessage];
    $responseText = $isMexico ? "claro! ya te paso con una agente, en un momento te escribimos 😊" : "of course! i'll connect you with an agent, we'll write you in a moment 😊";
    $messages[] = ["role" => "assistant", "content" => $responseText];

    // Guardar sesión
    $tmp = $sessionFile . '.tmp';
    $fh = @fopen($tmp, 'wb');
    if ($fh) {
        if (flock($fh, LOCK_EX)) {
            fwrite($fh, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        @rename($tmp, $sessionFile);
    } else {
        file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
    echo "<Response>";
    echo "<Message>" . htmlspecialchars($responseText, ENT_XML1, 'UTF-8') . "</Message>";
    echo "</Response>";
    exit;
}

// --- CORRECCIÓN 1: ASEGURAR QUE EL SYSTEM PROMPT ESTÉ SIEMPRE AL INICIO ---
// Detectar idioma según código de país del número de WhatsApp
$isMexico = (strpos($from_whatsapp_e164, '+521') === 0 || strpos($from_whatsapp_e164, '+52') === 0);
$promptFile = $isMexico ? './prompt_efege.txt' : './prompt_efege_en.txt';
$systemPrompt = file_exists($promptFile) ? file_get_contents($promptFile) : ($isMexico ? "Eres Alexa de Efege..." : "You are Alexa from Efege...");

if (empty($messages) || $messages[0]['role'] !== 'system') {
    array_unshift($messages, ["role" => "system", "content" => $systemPrompt]);
}

// 2. DETECTAR SI YA SE PRESENTÓ (Alexa o Efege)
$yaSaludó = false;
foreach ($messages as $m) {
    if ($m['role'] === 'assistant' && (stripos($m['content'], 'habla Alexa') !== false || stripos($m['content'], 'Efege') !== false)) {
        $yaSaludó = true;
        break;
    }
}

// 3. INYECTAR REGLA DE ORO (CORRECCIÓN 2: Inyectar como sistema ANTES del mensaje del usuario)
if ($yaSaludó) {
    $messages[] = [
        "role" => "system", 
        "content" => "IMPORTANTE: Ya te presentaste. NO repitas el saludo inicial. El usuario envió un mensaje breve o confuso; NO reinicies el guion, solo pregunta amablemente si desea la introducción o en qué puedes ayudarle."
    ];
}

// --- CORRECCIÓN 3: GESTIÓN DE MENSAJES VACÍOS O "?" ---
// Si el mensaje es solo un signo o está vacío, le damos una pista a la IA
if ($userMessage === '?' || empty($userMessage)) {
    $userMessage = "[El usuario parece confundido o envió un mensaje vacío. No repitas tu saludo, intenta retomar la conversación donde quedó]";
}

$messages[] = ["role" => "user", "content" => $userMessage];

// Extraer entidades básicas del mensaje del usuario (nombre, teléfono, fecha y hora)
function parse_spanish_date($text) {
    $months = [
        'enero'=>1,'ene'=>1,'febrero'=>2,'feb'=>2,'marzo'=>3,'mar'=>3,'abril'=>4,'abr'=>4,
        'mayo'=>5,'may'=>5,'junio'=>6,'jun'=>6,'julio'=>7,'jul'=>7,'agosto'=>8,'ago'=>8,
        'septiembre'=>9,'sep'=>9,'setiembre'=>9,'octubre'=>10,'oct'=>10,'noviembre'=>11,'nov'=>11,'diciembre'=>12,'dic'=>12
    ];
    $text = mb_strtolower($text);
    // Buscar patrones como '23 de oct del 2026' o '23 de octubre de 2026' o '19 de dic'
    if (preg_match('/(\b\d{1,2})\s*(?:de)?\s*(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre|ene|feb|mar|abr|may|jun|jul|ago|sep|set|oct|nov|dic)\s*(?:del|de)?\s*(\d{2,4})?/i', $text, $m)) {
        $day = intval($m[1]);
        $monName = $m[2];
        $year = isset($m[3]) && !empty($m[3]) ? intval($m[3]) : intval(date('Y'));
        $mon = $months[mb_strtolower($monName)] ?? null;
        if ($mon && checkdate($mon, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $mon, $day);
        }
    }
    return null;
}

function parse_time($text) {
    // Buscar hora como '9 am' '9:00' '09:00' '9 am' '9 a.m.'
    if (preg_match('/(\b\d{1,2})(?::(\d{2}))?\s*(am|pm|a\.m\.|p\.m\.)?/i', $text, $tm)) {
        $h = intval($tm[1]);
        $min = isset($tm[2]) && $tm[2] !== '' ? intval($tm[2]) : 0;
        $ampm = isset($tm[3]) ? strtolower($tm[3]) : '';
        if (strpos($ampm, 'pm') !== false && $h < 12) $h += 12;
        if ((strpos($ampm, 'am') !== false || strpos($ampm, 'a.') !== false) && $h == 12) $h = 0;
        return sprintf('%02d:%02d', $h, $min);
    }
    return null;
}

function extract_basic_entities($text, $fromNumber = '') {
    $data = [];
    // Telefono: buscar secuencia de 8-15 digitos
    if (preg_match('/(\+?\d[\d\s\-\(\)\.]{7,}\d)/', $text, $p)) {
        $phoneDigits = preg_replace('/\D+/', '', $p[1]);
        // Quitar prefijo 00 si existe
        if (strpos($phoneDigits, '00') === 0) $phoneDigits = ltrim($phoneDigits, '0');
        // Normalizar para México: insertar '1' después de 52 cuando falte
        if (strpos($phoneDigits, '52') === 0) {
            $rest = substr($phoneDigits, 2);
            if (strlen($rest) === 10) {
                $phone = '+521' . $rest;
            } elseif (strlen($rest) === 11 && substr($rest,0,1) === '1') {
                $phone = '+' . $phoneDigits;
            } else {
                $phone = '+' . $phoneDigits;
            }
        } elseif (strlen($phoneDigits) == 10) {
            // Número local de 10 dígitos en MX -> +521XXXXXXXXXX
            $phone = '+521' . $phoneDigits;
        } elseif (strlen($phoneDigits) == 11 && substr($phoneDigits,0,1) === '1') {
            // Posible formato US
            $phone = '+' . $phoneDigits;
        } else {
            $phone = '+' . $phoneDigits;
        }
        $data['client_phone'] = $phone;
    }
    // Nombre: heurística - si el texto tiene dos palabras con mayúscula inicial o contiene 'mi nombre es' o 'soy'
    if (preg_match('/(?:mi nombre es|soy|me llamo)\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)+)/i', $text, $n)) {
        $data['client_name'] = trim($n[1]);
    } else {
        // Alternativa: si el mensaje es corto y 2-3 palabras, considerar nombre
        $words = preg_split('/\s+/', trim($text));
        if (count($words) >= 2 && count($words) <= 4 && mb_strlen($text) < 40 && preg_match('/^[A-Za-zÁÉÍÓÚÑáéíóúñ\-\']+$/u', $words[0])) {
            // Probablemente sea un nombre si no contiene verbos comunes
            $commonVerbs = ['tengo','estoy','es','va','dime','hola','si','claro','gracias','ok','okey'];
            $lower = mb_strtolower($text);
            $hasVerb = false;
            foreach ($commonVerbs as $v) if (strpos($lower,$v) !== false) $hasVerb = true;
            if (!$hasVerb) $data['client_name'] = trim($text);
        }
    }
    // Fecha y hora
    $date = parse_spanish_date($text);
    if ($date) $data['wedding_date'] = $date;
    $time = parse_time($text);
    if ($time) $data['appointment_time'] = $time;
    return $data;
}

$entities = extract_basic_entities($userMessage, $from_whatsapp_number);
// Cargamos o inicializamos meta en sesión (archivo separado) para evitar preguntar de nuevo
$sessionMetaFile = __DIR__ . "/sessions/whatsapp_" . preg_replace('/\D+/', '', $from_whatsapp_number) . "_meta.json";
$legacySessionMetaFile = __DIR__ . "/sessions/whatsapp_" . preg_replace('/\D+/', '', $legacyDigits) . "_meta.json";
$loadedFromLegacyMeta = false;
$meta = [];
if (file_exists($sessionMetaFile)) {
    $mraw = @file_get_contents($sessionMetaFile);
    $meta = json_decode($mraw, true) ?: [];
} elseif ($legacyDigits !== '' && $legacyDigits !== $from_whatsapp_number && file_exists($legacySessionMetaFile)) {
    $mraw = @file_get_contents($legacySessionMetaFile);
    $meta = json_decode($mraw, true) ?: [];
    $loadedFromLegacyMeta = true;
}
$metaChanged = false;

// Si no hemos guardado aún teléfono del cliente, usar el del remitente ya normalizado.
if (empty($meta['client_phone']) && !empty($from_whatsapp_e164)) {
    $meta['client_phone'] = $from_whatsapp_e164;
    $metaChanged = true;
}

foreach (['client_name','client_phone','wedding_date','appointment_time'] as $k) {
    if (!empty($entities[$k]) && (empty($meta[$k]) || $meta[$k] != $entities[$k])) {
        $meta[$k] = $entities[$k];
        $metaChanged = true;
    }
}
if ($metaChanged) {
    file_put_contents($sessionMetaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    // Añadimos un mensaje de sistema para que OpenAI conozca los datos ya obtenidos
    $known = [];
    if (!empty($meta['client_name'])) $known[] = "client_name: " . $meta['client_name'];
    if (!empty($meta['client_phone'])) $known[] = "client_phone: " . $meta['client_phone'];
    if (!empty($meta['wedding_date'])) $known[] = "wedding_date: " . $meta['wedding_date'];
    if (!empty($meta['appointment_time'])) $known[] = "appointment_time: " . $meta['appointment_time'];
    if (!empty($known)) {
        $messages[] = ["role" => "system", "content" => "Datos ya obtenidos del lead: " . implode('; ', $known) . ". No preguntes por ellos de nuevo."];
    }
}

// Agregamos el mensaje del usuario al historial
$messages[] = ["role" => "user", "content" => $userMessage];

// Si la sesión está en handoff humano, no llamamos a OpenAI ni respondemos automáticamente.
// Guardamos el mensaje en el archivo de sesión y devolvemos una respuesta vacía (sin mensaje)
// para que el agente humano sea quien atienda.
if (!empty($meta['human_handoff'])) {
    // Guardar en archivo de sesión para registro
    $sessionDir = __DIR__ . '/sessions';
    $sessionFile = $sessionDir . '/whatsapp_' . preg_replace('/\D+/', '', $from_whatsapp_number) . '.json';
    $tmp = $sessionFile . '.tmp';
    $fh = @fopen($tmp, 'wb');
    if ($fh) {
        if (flock($fh, LOCK_EX)) {
            fwrite($fh, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        @rename($tmp, $sessionFile);
    } else {
        @file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // Responder vacío (no enviar mensaje automatizado)
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
    echo "<Response></Response>";
    exit;
}

// =======================
// 2. DEFINICIÓN DE TOOLS (ACTUALIZADO SEGÚN TU IMAGEN)
// =======================
$tools = [
   [
    "type" => "function",
    "function" => [
        "name" => "consultar_disponibilidad_dias",
        "description" => "Cuando el cliente acepte agendar la sesión creativa de quince minutos o pregunte por horarios. Después de recibir la respuesta de la API, analiza el arreglo data y selecciona únicamente las primeras dos fechas donde el arreglo slots no esté vacío. Para cada una de esas dos fechas, lee solo los primeros dos horarios disponibles del campo time. Presenta las fechas y horarios en lenguaje natural, sin listas y sin mencionar más de dos fechas. Después de leerlas, pregunta exactamente: ¿Qué día y hora le gustaría reservar?"
    ]
],
    [
        "type" => "function",
        "function" => [
            "name" => "agendar_cita_efege",
            "description" => "Se ejecuta cuando el usuario confirma fecha y hora, y ya se tienen sus datos personales y del evento.",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "appointment_date" => [
                        "type" => "string", 
                        "description" => "Fecha exacta de la cita confirmada (Formato YYYY-MM-DD)"
                    ],
                    "appointment_time" => [
                        "type" => "string", 
                        "description" => "Hora exacta de la cita confirmada (Formato HH:MM)"
                    ],
                    "client_name" => [
                        "type" => "string", 
                        "description" => "Nombre completo del cliente."
                    ],
                    "client_phone" => [
                        "type" => "string", 
                        "description" => "Número de teléfono del cliente."
                    ],
                    "wedding_date" => [
                        "type" => "string", 
                        "description" => "Fecha exacta de la boda (Formato YYYY-MM-DD)."
                    ],
                    "wedding_venue" => [
                        "type" => "string", 
                        "description" => "El lugar o sede donde será la boda."
                    ]
                ],
                // Aquí definimos qué datos son OBLIGATORIOS para poder llamar a la función
                "required" => ["appointment_date", "appointment_time", "client_name", "wedding_date"]
            ]
        ]
],
    [
        "type" => "function",
        "function" => [
            "name" => "cita_no_agendada",
            "description" => "Se ejecuta cuando la llamada termina sin que se haya concretado una cita. Debe enviar silenciosamente el teléfono del lead al endpoint que marca el lead como no agendado.",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "client_phone" => ["type" => "string", "description" => "Número de teléfono del lead (opcional, se usa el de WhatsApp si no se provee)."],
                    "reason" => ["type" => "string", "description" => "Motivo por el que no agendó (opcional)."]
                ]
            ]
        ]
    ]
];

// Función auxiliar para llamar a OpenAI
function callOpenAI($messages, $tools, $apiKey) {
    $payload = [
        "model" => "gpt-4o-mini",
        "messages" => $messages,
        "tools" => $tools,
        "tool_choice" => "auto",
        "temperature" => 0.2
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer {$apiKey}"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// =======================
// 3. PRIMERA LLAMADA A OPENAI
// =======================
$data = callOpenAI($messages, $tools, $OPENAI_API_KEY);
$assistantMessage = $data['choices'][0]['message'] ?? [];

$responseText = '';
$skipFinalOpenAI = false; // Si se ejecuta cita_no_agendada, evitamos la segunda llamada a OpenAI y respondemos con despedida
$farewellMessage = '';
$appointmentBooked = false; // Rastrear si ya se agendó exitosamente

// =======================
// 4. PROCESAR TOOL CALLS
// =======================
if (!empty($assistantMessage['tool_calls'])) {
    
    // Guardamos la intención de llamar a la herramienta
    $messages[] = $assistantMessage;

    foreach ($assistantMessage['tool_calls'] as $toolCall) {
        $functionName = $toolCall['function']['name'];
        $functionArgs = json_decode($toolCall['function']['arguments'], true);
        $toolCallId = $toolCall['id'];
        
        $toolOutput = "Error desconocido.";

        // --- A) CONSULTAR DISPONIBILIDAD ---
        // --- A) CONSULTAR DISPONIBILIDAD ---
        if ($functionName === 'consultar_disponibilidad_dias') {
            $apiUrl = "https://citas.efegepho.com.mx/api/available_slots.php";
            
            // Usamos cURL para mayor fiabilidad que file_get_contents
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5, // Timeout corto para no colgar WhatsApp
                CURLOPT_FOLLOWLOCATION => true
            ]);
            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $apiResponse) {
                $data = json_decode($apiResponse, true);
                
                // FILTRADO: Solo queremos días con slots > 0
                $diasDisponibles = [];
                
                // Helper: normalizar fecha a YYYY-MM-DD si es posible
                $normalizeDate = function($dstr) {
                    if (empty($dstr)) return null;
                    // Intentar parsear directamente
                    $dt = date_create($dstr);
                    if ($dt) return $dt->format('Y-m-d');
                    // Intentar con strtotime
                    $ts = strtotime($dstr);
                    if ($ts !== false) {
                        $dt = date_create("@".$ts);
                        return $dt->format('Y-m-d');
                    }
                    return null;
                };

                // Helper: normalizar hora a HH:MM
                $normalizeTime = function($tstr) {
                    if (empty($tstr)) return null;
                    $dt = date_create($tstr);
                    if ($dt) return $dt->format('H:i');
                    $ts = strtotime($tstr);
                    if ($ts !== false) {
                        $dt = date_create("@".$ts);
                        return $dt->format('H:i');
                    }
                    return null;
                };

                // Helper: formato de fecha en español legible (ej: 10 de diciembre de 2025)
                $spanishMonths = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
                $formatSpanishDate = function($iso) use ($spanishMonths) {
                    if (empty($iso)) return null;
                    $dt = date_create($iso);
                    if (!$dt) return null;
                    return $dt->format('j') . ' de ' . $spanishMonths[(int)$dt->format('n') - 1] . ' de ' . $dt->format('Y');
                };
                
                if (isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $dia) {
                        // Si el día no está bloqueado y tiene slots
                        if (!$dia['blocked'] && !empty($dia['slots'])) {
                            $horas = [];
                            foreach ($dia['slots'] as $slot) {
                                $time_iso = $normalizeTime($slot['time']) ?? $slot['time'];
                                $horas[] = [
                                    'time' => $time_iso,
                                    'display' => date('H:i', strtotime($time_iso))
                                ];
                            }
                            
                            $date_iso = $normalizeDate($dia['date']) ?? $dia['date'];
                            
                            $diasDisponibles[] = [
                                "fecha" => $date_iso,
                                "fecha_display" => $formatSpanishDate($date_iso) ?? $dia['date'],
                                "horas" => $horas
                            ];
                        }
                    }
                }

                // Limitamos a los primeros 5 días con disponibilidad para no saturar al bot
                $resumen = array_slice($diasDisponibles, 0, 5);
                
                if (empty($resumen)) {
                    $toolOutput = json_encode(["info" => "La API respondió correctamente pero no hay slots disponibles en las fechas próximas."]);
                } else {
                    $toolOutput = json_encode($resumen);
                }
                
            } else {
                $toolOutput = json_encode(["error" => "Error conectando al calendario. Código HTTP: $httpCode"]);
            }
        } elseif ($functionName === 'agendar_cita_efege') {
            
            $webhookUrl = "https://citas.efegepho.com.mx/api/book_slot_whatsapp.php";
            
            // Si el usuario no dio teléfono en el chat, usamos el de WhatsApp por defecto
            if (empty($functionArgs['client_phone'])) {
                $functionArgs['client_phone'] = $from_whatsapp_e164;
            } else {
                // Normalizar por si llegó sin el 1 o sin +
                $functionArgs['client_phone'] = normalize_whatsapp_phone_e164($functionArgs['client_phone']);
            }

            // Preparamos los datos para enviar por POST
            $postData = [
                'appointment_date' => $functionArgs['appointment_date'],
                'appointment_time' => $functionArgs['appointment_time'],
                'client_name'      => $functionArgs['client_name'],
                'client_phone'     => $functionArgs['client_phone'],
                'wedding_date'     => $functionArgs['wedding_date'] ?? 'No especificada',
                'wedding_venue'    => $functionArgs['wedding_venue'] ?? 'No especificado'
            ];

            // LOG DETALLADO ANTES DE HACER LA PETICIÓN
            $logFile = $logDir . '/booking_attempts_' . date('Y-m-d') . '.log';
            $logEntry = [
                'timestamp' => date('c'),
                'phone' => $from_whatsapp_number,
                'phone_e164' => $from_whatsapp_e164,
                'action' => 'agendar_cita_efege_called',
                'function_args' => $functionArgs,
                'post_data' => $postData,
                'webhook_url' => $webhookUrl
            ];
            file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND | LOCK_EX);

            // Hacemos la petición POST con cURL (más robusto que file_get_contents para POST)
            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postData), // O json_encode($postData) si tu API espera JSON body
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_VERBOSE => false
            ]);
            
            $responseApi = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlInfo = curl_getinfo($ch);
            curl_close($ch);

            // LOG DETALLADO DE LA RESPUESTA
            $responseDecoded = json_decode($responseApi, true);
            $logEntry = [
                'timestamp' => date('c'),
                'phone' => $from_whatsapp_number,
                'action' => 'api_response_received',
                'http_code' => $httpCode,
                'curl_error' => $curlError ?: null,
                'curl_info' => $curlInfo,
                'response_raw' => $responseApi,
                'response_decoded' => $responseDecoded,
                'post_data_sent' => $postData
            ];
            file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND | LOCK_EX);

            // VALIDACIÓN ESTRICTA DEL ÉXITO
            $bookingSuccess = false;
            $bookingMessage = '';
            $bookingDetails = [];
            
            if ($httpCode == 200 || $httpCode == 201) {
                if ($responseDecoded && is_array($responseDecoded)) {
                    // Verificar múltiples posibles indicadores de éxito
                    $success = $responseDecoded['success'] ?? false;
                    $bookingResult = $responseDecoded['booking'] ?? [];
                    $bookingSuccessFlag = $bookingResult['success'] ?? false;
                    
                    // PRIORIDAD 1: Verificar booking.success (respuesta de book_slot_whatsapp.php)
                    if ($bookingSuccessFlag === true || $bookingSuccessFlag === 'true' || $bookingSuccessFlag === 1) {
                        $bookingSuccess = true;
                        
                        // Extraer información detallada del agendamiento
                        if (isset($bookingResult['enviodatos_res']['cust'])) {
                            $bookingDetails['cust_id'] = $bookingResult['enviodatos_res']['cust'];
                        }
                        if (isset($bookingResult['enviodatos_res']['vendedor'])) {
                            $bookingDetails['vendedor_id'] = $bookingResult['enviodatos_res']['vendedor'];
                        }
                        if (isset($bookingResult['enviaCorreo_res']['data']['vendedor'])) {
                            $bookingDetails['vendedor_nombre'] = $bookingResult['enviaCorreo_res']['data']['vendedor'];
                        }
                        
                        $bookingMessage = 'Cita agendada exitosamente en el sistema';
                        
                        // Log de éxito detallado
                        $logEntry = [
                            'timestamp' => date('c'),
                            'phone' => $from_whatsapp_number,
                            'action' => 'booking_validation_success',
                            'reason' => 'booking.success_true',
                            'details' => $bookingDetails,
                            'booking_result' => $bookingResult
                        ];
                        file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND | LOCK_EX);
                        
                    } elseif ($success === true || $success === 'true' || $success === 1) {
                        // PRIORIDAD 2: Verificar success general
                        $bookingSuccess = true;
                        $bookingMessage = $responseDecoded['message'] ?? 'Cita agendada exitosamente';
                        
                        $logEntry = [
                            'timestamp' => date('c'),
                            'phone' => $from_whatsapp_number,
                            'action' => 'booking_validation_success',
                            'reason' => 'success_true',
                            'response' => $responseDecoded
                        ];
                        file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND | LOCK_EX);
                        
                    } else {
                        // La API respondió pero sin indicador de éxito claro
                        $bookingMessage = $responseDecoded['message'] ?? 'Error: La API no confirmó el agendamiento';
                        
                        // Revisar si hay mensajes de error en booking
                        if (isset($bookingResult['messages']) && is_array($bookingResult['messages'])) {
                            $bookingMessage .= ' - Mensajes: ' . implode('; ', $bookingResult['messages']);
                        }
                        
                        $logEntry = [
                            'timestamp' => date('c'),
                            'phone' => $from_whatsapp_number,
                            'action' => 'booking_validation_failed',
                            'reason' => 'no_success_indicator',
                            'success_value' => $success,
                            'booking_success_value' => $bookingSuccessFlag,
                            'response' => $responseDecoded
                        ];
                        file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND | LOCK_EX);
                    }
                } else {
                    // Respuesta no es JSON válido
                    $bookingMessage = 'Error: Respuesta inválida de la API';
                    $logEntry = [
                        'timestamp' => date('c'),
                        'phone' => $from_whatsapp_number,
                        'action' => 'booking_validation_failed',
                        'reason' => 'invalid_json_response',
                        'response_raw' => substr($responseApi, 0, 1000)
                    ];
                    file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND | LOCK_EX);
                }
            } else {
                $bookingMessage = "Error HTTP: No se pudo contactar al servidor de citas. Código: $httpCode";
                if ($curlError) {
                    $bookingMessage .= " | cURL Error: $curlError";
                }
                $logEntry = [
                    'timestamp' => date('c'),
                    'phone' => $from_whatsapp_number,
                    'action' => 'booking_http_error',
                    'http_code' => $httpCode,
                    'curl_error' => $curlError
                ];
                file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND | LOCK_EX);
            }

            // Construir toolOutput con información clara para la IA
            if ($bookingSuccess) {
                // MARCAR QUE LA CITA YA FUE AGENDADA
                $appointmentBooked = true;
                
                $toolOutput = json_encode([
                    "status" => "success",
                    "message" => $bookingMessage,
                    "booked" => true,
                    "appointment_date" => $functionArgs['appointment_date'],
                    "appointment_time" => $functionArgs['appointment_time'],
                    "client_name" => $functionArgs['client_name'],
                    "details" => $bookingDetails
                ]);
            } else {
                $toolOutput = json_encode([
                    "status" => "error",
                    "message" => $bookingMessage,
                    "booked" => false,
                    "http_code" => $httpCode,
                    "details" => $responseDecoded ?? ['raw' => substr($responseApi, 0, 500)]
                ]);
            }

            // LOG FINAL
            $logEntry = [
                'timestamp' => date('c'),
                'phone' => $from_whatsapp_number,
                'action' => 'booking_final_result',
                'success' => $bookingSuccess,
                'message' => $bookingMessage,
                'booking_details' => $bookingDetails,
                'tool_output' => json_decode($toolOutput, true),
                'raw_api_response_summary' => [
                    'http_code' => $httpCode,
                    'success_flag' => $responseDecoded['success'] ?? null,
                    'booking_success_flag' => ($responseDecoded['booking']['success'] ?? null),
                    'has_cust' => isset($responseDecoded['booking']['enviodatos_res']['cust']),
                    'has_email_sent' => isset($responseDecoded['booking']['enviaCorreo_res']['status'])
                ]
            ];
            file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n===END===\n\n", FILE_APPEND | LOCK_EX);
        } elseif ($functionName === 'cita_no_agendada') {
            // REGLA DE SEGURIDAD: NO ejecutar si ya se agendó una cita en esta conversación
            if ($appointmentBooked) {
                // Ya se agendó, NO marcar como "no agendó"
                $toolOutput = json_encode([
                    "status" => "ignored",
                    "message" => "La herramienta cita_no_agendada fue bloqueada porque ya se agendó una cita exitosamente en esta conversación.",
                    "booked_already" => true
                ]);
                
                // Log para debugging
                $logFile = $logDir . '/cita_no_agendada_blocked_' . date('Y-m-d') . '.log';
                $logEntry = [
                    'timestamp' => date('c'),
                    'phone' => $from_whatsapp_number,
                    'action' => 'cita_no_agendada_blocked',
                    'reason' => 'appointment_already_booked'
                ];
                file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND | LOCK_EX);
                
                // NO establecer skipFinalOpenAI ni farewellMessage
                // Dejar que la conversación continúe normalmente
            } else {
                // Esta herramienta se usa cuando el lead se rehusa a agendar o la llamada termina sin reservar.
                // Llama al endpoint que marca el lead como no agendado: mark_no_scheduled_whatsapp.php
                $webhookUrl = "https://citas.efegepho.com.mx/api/mark_no_scheduled_whatsapp.php";

            // Si no se proporciona teléfono explícitamente, usar el número de WhatsApp
            if (empty($functionArgs['client_phone'])) {
                $functionArgs['client_phone'] = $from_whatsapp_e164;
            } else {
                $functionArgs['client_phone'] = normalize_whatsapp_phone_e164($functionArgs['client_phone']);
            }

            // Construimos payload con la estructura esperada: payload.client_phone
            $payload = [
                'payload' => [
                    'client_phone' => $functionArgs['client_phone']
                ]
            ];
            if (!empty($functionArgs['reason'])) {
                $payload['payload']['reason'] = $functionArgs['reason'];
            }

            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8
            ]);
            $responseApi = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 200 || $httpCode == 201) {
                $toolOutput = $responseApi;
            } else {
                $toolOutput = json_encode(["status" => "error", "message" => "No se pudo marcar como no agendado. Código: $httpCode"]);
            }

            // En todos los casos, enviar un mensaje de cierre al usuario y evitar hacer
            // la segunda llamada a OpenAI (respondemos inmediatamente con la despedida).
            $farewellMessage = "Gracias por su tiempo. Si desea conocer más sobre nuestro proceso creativo, nos encantaría ayudarle a dar vida a su visión. Que tenga un excelente día.";
            $messages[] = ["role" => "assistant", "content" => $farewellMessage];
            $skipFinalOpenAI = true;
            } // Cierre del else de la validación appointmentBooked
        }
        
        // Agregamos el resultado al historial
        $messages[] = [
            "role" => "tool",
            "tool_call_id" => $toolCallId,
            "content" => $toolOutput
        ];

        // Si fue intento de agendamiento, agregar instrucción clara
        if ($functionName === 'agendar_cita_efege') {
            $toolResult = json_decode($toolOutput, true);
            if (is_array($toolResult)) {
                if (isset($toolResult['booked']) && $toolResult['booked'] === false) {
                    // FALLÓ EL AGENDAMIENTO
                    $messages[] = [
                        "role" => "system",
                        "content" => "IMPORTANTE: El agendamiento FALLÓ. NO confirmes al usuario que la cita fue agendada. Debes informarle que hubo un problema técnico y ofrecerle alternativas (llamar al +52 1 442 127 9400 o reintentar más tarde). Razón del fallo: " . ($toolResult['message'] ?? 'desconocida')
                    ];
                } elseif (isset($toolResult['booked']) && $toolResult['booked'] === true) {
                    // ÉXITO EN EL AGENDAMIENTO
                    $messages[] = [
                        "role" => "system",
                        "content" => "CONFIRMADO: La cita fue agendada exitosamente en el sistema. Ahora puedes confirmar al usuario que su cita está reservada."
                    ];
                }
            }
        }

        // Si la herramienta de disponibilidad devolvió opciones válidas, añadimos una instrucción
        // para que el asistente presente también el formato técnico YYYY-MM-DD y HH:MM
        if ($functionName === 'consultar_disponibilidad_dias') {
            $decoded = json_decode($toolOutput, true);
            if (is_array($decoded) && !empty($decoded) && empty($decoded['error'])) {
                $messages[] = [
                    "role" => "system",
                    "content" => "Al presentar las opciones de fecha y hora, muéstrelas cada una en una línea separada (una opción por línea), sin viñetas, sin números y sin caracteres especiales. Para cada opción incluye la versión en lenguaje natural y entre paréntesis la fecha en formato YYYY-MM-DD y la hora en formato HH:MM. Ejemplo: el 19 de diciembre de 2025 (2025-12-19) a las 04:00. Después de leerlas, pregunte: ¿Qué día y hora le gustaría reservar?"
                ];
            }
        }
    }

    // =======================
    // 5. SEGUNDA LLAMADA A OPENAI (se puede saltar si ya enviamos despedida)
    // =======================
    if ($skipFinalOpenAI && !empty($farewellMessage)) {
        // Ya añadimos y enviamos la despedida, así que usamos eso como respuesta final
        $responseText = $farewellMessage;
    } else {
        $finalResponse = callOpenAI($messages, $tools, $OPENAI_API_KEY);
        $responseText = $finalResponse['choices'][0]['message']['content'] ?? 'Ocurrió un error finalizando la solicitud.';
        $messages[] = ["role" => "assistant", "content" => $responseText];
    }

} else {
    // Respuesta normal sin herramientas
    $responseText = $assistantMessage['content'] ?? '';
    $messages[] = ["role" => "assistant", "content" => $responseText];
}

// =======================
// 6. GUARDAR Y RESPONDER
// =======================
$tmp = $sessionFile . '.tmp';
$fh = @fopen($tmp, 'wb');
if ($fh) {
    if (flock($fh, LOCK_EX)) {
        fwrite($fh, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    @rename($tmp, $sessionFile);
} else {
    file_put_contents($sessionFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Si cargamos desde sesión vieja (sin el 1), ya guardamos en el archivo nuevo; limpiamos el legacy para evitar confusión.
if ($loadedFromLegacySession && $legacyDigits !== '' && $legacyDigits !== $from_whatsapp_number && file_exists($legacySessionFile)) {
    @unlink($legacySessionFile);
}
// Si cargamos meta viejo, lo migramos al nombre nuevo y limpiamos legacy.
if ($loadedFromLegacyMeta && $legacyDigits !== '' && $legacyDigits !== $from_whatsapp_number && file_exists($legacySessionMetaFile)) {
    @unlink($legacySessionMetaFile);
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
echo "<Response>";
echo "<Message>" . htmlspecialchars($responseText, ENT_XML1, 'UTF-8') . "</Message>";
echo "</Response>";
?>