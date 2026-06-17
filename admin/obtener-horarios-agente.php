<?php
include 'conn.php';
header('Content-Type: application/json');
ini_set('display_errors', 1); error_reporting(E_ALL);

$idusu = isset($_POST['idusu']) ? intval($_POST['idusu']) : 0;
$date = isset($_POST['date_appointment']) ? trim($_POST['date_appointment']) : '';

if ($idusu <= 0 || $date === '') {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos', 'available' => []]);
    exit;
}

$available = [];
// Try to fetch horarios from atencion table for that agent and date
$stmt = $conn->prepare("SELECT horarios FROM atencion WHERE idusu = ? AND dia = ? LIMIT 1");
$stmt->bind_param('is', $idusu, $date);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $row = $res->fetch_assoc()) {
    $horarios = json_decode($row['horarios'], true);
    if (is_array($horarios)) $available = $horarios;
}
$stmt->close();

// If we didn't find horarios by exact date, try by weekday name (e.g., 'Monday' or numeric day)
if (empty($available)) {
    $ts = strtotime($date);
    if ($ts !== false) {
        $weekday = date('w', $ts); // 0 (Sunday) - 6 (Saturday)
        $stmt = $conn->prepare("SELECT horarios FROM atencion WHERE idusu = ? AND dia = ? LIMIT 1");
        $stmt->bind_param('is', $idusu, $weekday);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $horarios = json_decode($row['horarios'], true);
            if (is_array($horarios)) $available = $horarios;
        }
        $stmt->close();
    }
}

// If still empty, return empty available
if (empty($available)) {
    echo json_encode(['success'=>true, 'available' => []]);
    $conn->close();
    exit;
}

// Fetch already booked times for that agent & date
$stmt = $conn->prepare("SELECT hora FROM calendario WHERE fecha = ? AND idusu = ? AND eliminado = 0");
$stmt->bind_param('si', $date, $idusu);
$stmt->execute();
$res = $stmt->get_result();
$taken = [];
while ($r = $res->fetch_assoc()) {
    $taken[] = $r['hora'];
}
$stmt->close();

$final = [];
foreach ($available as $h) {
    // normalize format e.g. "09:00" or "09:00:00"
    $hnorm = preg_replace('/[^0-9:\.]/','', $h);
    $hnorm = strlen($hnorm) == 5 ? $hnorm : (strlen($hnorm) == 8 ? $hnorm : $h);
    // if not in taken add
    $found = false;
    foreach ($taken as $t) {
        if (strpos($t, $hnorm) !== false || strpos($hnorm, $t) !== false) {
            $found = true; break;
        }
    }
    if (!$found) $final[] = $h;
}

echo json_encode(['success'=>true, 'available' => array_values($final)]);
$conn->close();
?>