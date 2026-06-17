<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'conn.php';

function normalizeAppointmentTime($time) {
    $time = trim((string)$time);
    if ($time === '') {
        return '';
    }

    $parts = explode(':', $time);
    if (count($parts) < 2) {
        return $time;
    }

    $hour = str_pad((string)((int)$parts[0]), 2, '0', STR_PAD_LEFT);
    $minute = str_pad((string)((int)$parts[1]), 2, '0', STR_PAD_LEFT);

    return $hour . ':' . $minute;
}

function getAvailableVendorsForSlot($conn, $date, $time) {
    $normalizedTime = normalizeAppointmentTime($time);
    if ($date === '' || $normalizedTime === '') {
        return [];
    }

    $occupiedVendorIds = [];
    $stmtBusy = $conn->prepare("SELECT hora, idusu FROM calendario WHERE fecha = ?");
    $stmtBusy->bind_param('s', $date);
    $stmtBusy->execute();
    $busyRows = $stmtBusy->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtBusy->close();

    foreach ($busyRows as $busyRow) {
        if (normalizeAppointmentTime($busyRow['hora'] ?? '') === $normalizedTime) {
            $occupiedVendorIds[(int)$busyRow['idusu']] = true;
        }
    }

    $availableVendorIds = [];
    $stmtAttention = $conn->prepare("SELECT horarios, idusu FROM atencion WHERE dia = ?");
    $stmtAttention->bind_param('s', $date);
    $stmtAttention->execute();
    $attentionRows = $stmtAttention->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtAttention->close();

    foreach ($attentionRows as $vendor) {
        $vendorId = (int)($vendor['idusu'] ?? 0);
        if ($vendorId <= 0 || isset($occupiedVendorIds[$vendorId])) {
            continue;
        }

        $vendorSchedules = json_decode($vendor['horarios'] ?? '[]', true);
        if (!is_array($vendorSchedules)) {
            continue;
        }

        foreach ($vendorSchedules as $scheduleTime) {
            if (normalizeAppointmentTime($scheduleTime) === $normalizedTime) {
                $availableVendorIds[$vendorId] = $vendorId;
                break;
            }
        }
    }

    return array_values($availableVendorIds);
}

// Verificar la conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$response = [];
$dateAppointment = trim($_POST['date_appointment'] ?? '');
$timeAppointment = normalizeAppointmentTime($_POST['time_appointment'] ?? '');
$vendedores = getAvailableVendorsForSlot($conn, $dateAppointment, $timeAppointment);


// Si hay vendedores disponibles, asignar uno aleatorio
if (count($vendedores) > 0) {
    $idusu = $vendedores[array_rand($vendedores, 1)]; // Vendedor aleatorio

    // Devolver el id del vendedor como parte de la respuesta
    $response['success'] = true;
    $response['vendedor'] = $idusu;  // Aquí va el ID del vendedor seleccionado
} else {
    $response['success'] = 'full';  // No hay vendedores disponibles
}

// Cerrar conexión y enviar respuesta JSON
$conn->close();

header('Content-Type: application/json');
echo json_encode($response);
?>
