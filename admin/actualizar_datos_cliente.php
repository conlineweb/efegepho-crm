<?php
include 'conn.php';

header('Content-Type: application/json');

$data = isset($_POST['updatedData']) && is_array($_POST['updatedData']) ? $_POST['updatedData'] : [];
$idclie = isset($_POST['idclie']) ? (int) $_POST['idclie'] : 0;

if ($idclie <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de cliente inválido.']);
    exit;
}

if (array_key_exists('fecha_atencion', $data)) {
    require_once __DIR__ . '/calendario_estatus_historial_helper.php';
    $calendarioId = isset($_POST['calendario_id']) ? (int) $_POST['calendario_id'] : 0;
    $result = calendarioHistorialUpdateFechaAtencionByContactForm(
        $conn,
        $idclie,
        (string) $data['fecha_atencion'],
        $calendarioId
    );
    echo json_encode($result);
    $conn->close();
    exit;
}

function refValues(array &$values)
{
    $refs = [];
    foreach ($values as $key => &$value) {
        $refs[$key] = &$value;
    }
    return $refs;
}

$data = isset($_POST['updatedData']) && is_array($_POST['updatedData']) ? $_POST['updatedData'] : [];
$idclie = isset($_POST['idclie']) ? (int) $_POST['idclie'] : 0;

if ($idclie <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de cliente inválido.']);
    exit;
}

$fieldMap = [
    'nombre' => 'names',
    'email' => 'email_address',
    'telefono' => 'telephone',
    'country_code' => 'country_code',
    'wedding_date' => 'wedding_date',
    'wedding_location' => 'wedding_location',
    'guests_count' => 'guests_count',
    'service_interest' => 'service_interest',
    'look_preference' => 'look_preference',
    'wedding_planner' => 'wedding_planner',
    'hear_about_us' => 'hear_about_us',
    'how_did_you_meet' => 'how_did_you_meet',
    'tipo_cliente' => 'tipo_cliente',
    'couple_activities' => 'couple_activities',
    'favorite_movie_or_song' => 'favorite_movie_or_song',
    'instagram_handle' => 'instagram_handle',
    'additional_details' => 'additional_details',
    'paquete' => 'paquete',
    'que_se_les_vendio' => 'que_se_les_vendio',
    'monto_venta' => 'monto_venta',
    'engagement' => 'engagement',
    'how_long_known_us' => 'how_long_known_us',
    'fecha_cambio_cliente' => 'fecha_cambio_cliente'
];

$updateFields = [];
$bindValues = [];

foreach ($fieldMap as $requestKey => $columnName) {
    if (!array_key_exists($requestKey, $data)) {
        continue;
    }

    $value = $data[$requestKey];

    if (is_string($value)) {
        $value = trim($value);
    }

    if ($requestKey === 'how_did_you_meet' && !in_array((string) $value, ['1', '2', '3'], true)) {
        continue;
    }

    if ($requestKey === 'hear_about_us' && ($value === 0 || $value === '0')) {
        $value = '';
    }

    $updateFields[] = "{$columnName} = ?";
    $bindValues[] = $value;
}

if (count($updateFields) === 0) {
    echo json_encode(['success' => false, 'message' => 'No se proporcionaron datos válidos para actualizar.']);
    exit;
}

$sql = 'UPDATE contact_form SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'No se pudo preparar la actualización.']);
    exit;
}

$bindValues[] = $idclie;
$types = str_repeat('s', count($bindValues) - 1) . 'i';
$params = array_merge([$types], $bindValues);

call_user_func_array([$stmt, 'bind_param'], refValues($params));

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Datos actualizados correctamente.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Hubo un error al actualizar los datos.']);
}

$stmt->close();
$conn->close();
?>
