<?php
// Debugging helpers - show errors while debugging. Remove or guard in production.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Mexico_City');
header('Content-Type: application/json; charset=utf-8');
// Endpoint to update simple fields of a lead (contact_form)
// Expects POST: id (int), field (string), value (string)

include 'conn.php';

function getClientRelatedComments($conn, $clientId, $clientRow = null)
{
    $comments = [
        'wp_initial' => '',
        'wp_client' => '',
        'agenda_initial' => '',
        'agenda_client' => ''
    ];

    if (!$clientRow) {
        $sel = $conn->prepare("SELECT tabla_origen, original_lead_id FROM contact_form WHERE id = ? LIMIT 1");
        if ($sel) {
            $sel->bind_param('i', $clientId);
            $sel->execute();
            $res = $sel->get_result();
            $clientRow = $res ? $res->fetch_assoc() : null;
            $sel->close();
        }
    }

    if ($clientRow) {
        $tablaOrigen = strtolower(trim((string)($clientRow['tabla_origen'] ?? '')));
        $originalLeadId = intval($clientRow['original_lead_id'] ?? 0);
        $eventId = 0;

        if ($tablaOrigen === 'eventos_wp') {
            $eventId = $originalLeadId;
        } elseif ($tablaOrigen === 'wp_eventos_afianzados' && $originalLeadId > 0) {
            $selAf = $conn->prepare("SELECT id_evento FROM wp_eventos_afianzados WHERE id = ? LIMIT 1");
            if ($selAf) {
                $selAf->bind_param('i', $originalLeadId);
                $selAf->execute();
                $resAf = $selAf->get_result();
                $rowAf = $resAf ? $resAf->fetch_assoc() : null;
                $selAf->close();
                $eventId = intval($rowAf['id_evento'] ?? 0);
            }
        }

        if ($eventId > 0) {
            $selWp = $conn->prepare("SELECT comentario, comentario_a_cliente FROM eventos_wp WHERE id = ? LIMIT 1");
            if ($selWp) {
                $selWp->bind_param('i', $eventId);
                $selWp->execute();
                $resWp = $selWp->get_result();
                $rowWp = $resWp ? $resWp->fetch_assoc() : null;
                $selWp->close();
                if ($rowWp) {
                    $comments['wp_initial'] = trim((string)($rowWp['comentario'] ?? ''));
                    $comments['wp_client'] = trim((string)($rowWp['comentario_a_cliente'] ?? ''));
                }
            }
        }
    }

    $selCalInitial = $conn->prepare("SELECT comentario FROM calendario WHERE idclie = ? AND comentario IS NOT NULL AND TRIM(comentario) <> '' ORDER BY id DESC LIMIT 1");
    if ($selCalInitial) {
        $selCalInitial->bind_param('i', $clientId);
        $selCalInitial->execute();
        $resCalInitial = $selCalInitial->get_result();
        $rowCalInitial = $resCalInitial ? $resCalInitial->fetch_assoc() : null;
        $selCalInitial->close();
        if ($rowCalInitial) {
            $comments['agenda_initial'] = trim((string)($rowCalInitial['comentario'] ?? ''));
        }
    }

    $selCalClient = $conn->prepare("SELECT comentario_a_cliente FROM calendario WHERE idclie = ? AND comentario_a_cliente IS NOT NULL AND TRIM(comentario_a_cliente) <> '' ORDER BY id DESC LIMIT 1");
    if ($selCalClient) {
        $selCalClient->bind_param('i', $clientId);
        $selCalClient->execute();
        $resCalClient = $selCalClient->get_result();
        $rowCalClient = $resCalClient ? $resCalClient->fetch_assoc() : null;
        $selCalClient->close();
        if ($rowCalClient) {
            $comments['agenda_client'] = trim((string)($rowCalClient['comentario_a_cliente'] ?? ''));
        }
    }

    return $comments;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$field = isset($_POST['field']) ? $_POST['field'] : '';
$value = isset($_POST['value']) ? $_POST['value'] : null;

// Optional action: add_comment / get_comments
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Action: check_existing - check if a client exists with the same name and/or email
if ($action === 'check_existing') {
    // Accept POST params: name, email
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if ($name === '' && $email === '') {
        echo json_encode(['success' => false, 'message' => 'Falta nombre o email para verificar']);
        $conn->close();
        exit;
    }

    $conds = [];
    $params = [];
    $types = '';

    // Only check clients (cliente = 1). Also allow checking for leads too if needed by adjusting condition.
    if ($name !== '') {
        // case-insensitive match using LOWER
        $conds[] = "LOWER(names) = LOWER(?)";
        $params[] = $name;
        $types .= 's';
    }
    if ($email !== '') {
        $conds[] = "LOWER(email_address) = LOWER(?)";
        $params[] = $email;
        $types .= 's';
    }

    $sql = "SELECT id, names, email_address, cliente FROM contact_form WHERE cliente = 1 AND (" . implode(' OR ', $conds) . ") LIMIT 10";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error en preparación de consulta: ' . $conn->error]);
        $conn->close();
        exit;
    }
    // Bind params if any
    if ($types !== '') {
        $bindParams = array_merge([$types], $params);
        $refs = [];
        foreach ($bindParams as $k => $v) {
            $refs[$k] = &$bindParams[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $matches = [];
    while ($r = $res->fetch_assoc()) {
        $matches[] = $r;
    }
    $stmt->close();

    $exists_name = false;
    $exists_email = false;
    foreach ($matches as $m) {
        if ($name !== '' && strcasecmp(trim($m['names']), $name) === 0) $exists_name = true;
        if ($email !== '' && strcasecmp(trim($m['email_address']), $email) === 0) $exists_email = true;
    }

    echo json_encode(['success' => true, 'exists_name' => $exists_name, 'exists_email' => $exists_email, 'matches' => $matches]);
    $conn->close();
    exit;
}

if ($action === 'add_comment') {
    // Expect: id, comment, optional author
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    $author = isset($_POST['author']) ? trim($_POST['author']) : null;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    if ($comment === '') {
        echo json_encode(['success' => false, 'message' => 'Comentario vacío']);
        exit;
    }

    // Fetch existing comments JSON
    $sel = $conn->prepare("SELECT comentarios_vendedor FROM contact_form WHERE id = ? LIMIT 1");
    if (!$sel) {
        echo json_encode(['success' => false, 'message' => 'Error en preparación SELECT: ' . $conn->error]);
        exit;
    }
    $sel->bind_param('i', $id);
    $sel->execute();
    $res = $sel->get_result();
    $row = $res->fetch_assoc();
    $sel->close();

    $existing = [];
    if (!empty($row['comentarios_vendedor'])) {
        $decoded = json_decode($row['comentarios_vendedor'], true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }

    // Build new comment object
    $new = [
        'text' => $comment,
        'author' => $author ?: '',
        'created_at' => gmdate('Y-m-d H:i:s')
    ];

    $existing[] = $new;
    $json = json_encode($existing, JSON_UNESCAPED_UNICODE);

    $up = $conn->prepare("UPDATE contact_form SET comentarios_vendedor = ? WHERE id = ? LIMIT 1");
    if (!$up) {
        echo json_encode(['success' => false, 'message' => 'Error en preparación UPDATE: ' . $conn->error]);
        exit;
    }
    $up->bind_param('si', $json, $id);
    $ok = $up->execute();
    if ($ok) {
        echo json_encode(['success' => true, 'comments' => $existing]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $up->error]);
    }
    $up->close();
    $conn->close();
    exit;
}

if ($action === 'get_comments') {
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    $sel = $conn->prepare("SELECT comentarios_vendedor FROM contact_form WHERE id = ? LIMIT 1");
    if (!$sel) {
        echo json_encode(['success' => false, 'message' => 'Error en preparación SELECT: ' . $conn->error]);
        exit;
    }
    $sel->bind_param('i', $id);
    $sel->execute();
    $res = $sel->get_result();
    $row = $res->fetch_assoc();
    $sel->close();

    $existing = [];
    if (!empty($row['comentarios_vendedor'])) {
        $decoded = json_decode($row['comentarios_vendedor'], true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }

    echo json_encode(['success' => true, 'comments' => $existing]);
    $conn->close();
    exit;
}

if ($action === 'get_client') {
    // Return client fields for editing modal
    $sel = $conn->prepare("SELECT id, names, email_address, telephone, wedding_date, campaign_name, wedding_location, city, paquete, puntos, monto_venta, que_se_les_vendio, fecha_cambio_cliente, desde_publicidad, how_did_you_meet, hear_about_us, engagement, how_long_known_us, submission_date, tabla_origen, original_lead_id, id_vendedor_asignado, first_contact_channel, tipo_cliente FROM contact_form WHERE id = ? LIMIT 1");
    if (!$sel) {
        echo json_encode(['success' => false, 'message' => 'Error en preparación SELECT: ' . $conn->error]);
        exit;
    }
    $sel->bind_param('i', $id);
    $sel->execute();
    $res = $sel->get_result();
    $row = $res->fetch_assoc();
    $sel->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
        exit;
    }

    $row['related_comments'] = getClientRelatedComments($conn, $id, $row);

    echo json_encode(['success' => true, 'client' => $row]);
    $conn->close();
    exit;
}

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

// Whitelist of allowed fields to update to avoid SQL injection
$allowed = [
    'sesion_oficial',
    'contacto_celular',
    'paquete_cotizado',
    'desde_publicidad',
    'compromiso_cliente',
    'tecnica_cierre',
    'comentarios_vendedor',
    'comision',
    'comision_pagada'
];

// Allowed fields for bulk update through the new 'update_client' action
$allowedUpdate = [
    'names', 'email_address', 'telephone', 'wedding_date',
    'campaign_name', 'wedding_location', 'city', 'paquete',
    'how_did_you_meet', 'hear_about_us', 'engagement', 'how_long_known_us',
    'puntos', 'monto_venta', 'que_se_les_vendio',
    'fecha_cambio_cliente', 'desde_publicidad', 'id_vendedor_asignado',
    'first_contact_channel', 'tipo_cliente'
];

// Allow updating submission_date (datetime) via update_client
$allowedUpdate[] = 'submission_date';
$allowedUpdate[] = 'comision';
$allowedUpdate[] = 'comision_pagada';

// Single-field update path (the previous behavior). Only allow this when not using the
// multi-field `update_client` action — otherwise the check below would block update_client
// because `field` is typically empty when using `update_client`.
if ($action !== 'update_client') {
    if (!in_array($field, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Campo no permitido']);
        exit;
    }

    // Prepare and execute update
    $stmt = $conn->prepare("UPDATE contact_form SET `$field` = ? WHERE id = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error en la preparación: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('si', $value, $id);
    $ok = $stmt->execute();

    if ($ok) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Action: update_client - accept multiple fields
if ($action === 'update_client') {
    // Accept fields as a JSON string in POST 'fields' or as flattened POST values
    $fieldsJson = isset($_POST['fields']) ? trim($_POST['fields']) : '';
    $inputFields = [];

    if ($fieldsJson !== '') {
        $decoded = json_decode($fieldsJson, true);
        if (is_array($decoded)) {
            $inputFields = $decoded;
        }
    } else {
        // Collect known fields from POST
        foreach ($allowedUpdate as $f) {
            if (isset($_POST[$f])) $inputFields[$f] = $_POST[$f];
        }
    }

    if (empty($inputFields)) {
        echo json_encode(['success' => false, 'message' => 'No se recibieron campos para actualizar']);
        exit;
    }

    // Filter to allowed keys only
    $updatePairs = [];
    $updateValues = [];
    foreach ($inputFields as $k => $v) {
        if (in_array($k, $allowedUpdate, true)) {
            if ($k === 'how_did_you_meet') {
                $normalizedHowDidYouMeet = is_string($v) ? trim($v) : (string) $v;
                if (!in_array($normalizedHowDidYouMeet, ['1', '2', '3'], true)) {
                    continue;
                }
                $v = $normalizedHowDidYouMeet;
            }

            $updatePairs[] = "`$k` = ?";
            // Normalize certain fields to allow empty -> NULL
            if ($k === 'fecha_cambio_cliente' && $v === '') {
                $updateValues[] = null;
            } elseif ($k === 'desde_publicidad' && ($v === '' || $v === null)) {
                $updateValues[] = null;
            } elseif ($k === 'submission_date' && ($v === '' || $v === null)) {
                // allow clearing submission_date
                $updateValues[] = null;
            } else {
                $updateValues[] = $v;
            }
        }
    }

    if (empty($updatePairs)) {
        echo json_encode(['success' => false, 'message' => 'No hay campos válidos para actualizar']);
        exit;
    }

    $sql = 'UPDATE contact_form SET ' . implode(', ', $updatePairs) . ' WHERE id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error en preparación: ' . $conn->error]);
        exit;
    }

    // Build types string
    $types = '';
    $bindValues = [];
    foreach ($updateValues as $val) {
        $types .= 's';
        $bindValues[] = $val;
    }
    $types .= 'i'; // id
    $bindValues[] = $id;

    // Bind parameters dynamically
    // bind_param requires references: use call_user_func_array to bind dynamic parameters
    $bindParams = array_merge([$types], $bindValues);
    $refs = [];
    foreach ($bindParams as $key => $value) {
        $refs[$key] = &$bindParams[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $ok = $stmt->execute();

    if ($ok) {
        // Return updated client record
$sel = $conn->prepare("SELECT id, names, email_address, telephone, wedding_date, campaign_name, wedding_location, city, paquete, puntos, monto_venta, que_se_les_vendio, fecha_cambio_cliente, desde_publicidad, how_did_you_meet, hear_about_us, engagement, how_long_known_us, submission_date, tabla_origen, original_lead_id, id_vendedor_asignado, first_contact_channel, tipo_cliente FROM contact_form WHERE id = ? LIMIT 1");
        $sel->bind_param('i', $id);
        $sel->execute();
        $res = $sel->get_result();
        $row = $res->fetch_assoc();
        $sel->close();

        $row['related_comments'] = getClientRelatedComments($conn, $id, $row);
        echo json_encode(['success' => true, 'client' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();
$conn->close();

?>
