<?php
// API: available_slots.php
// Returns a JSON structure with available time slots and vendor names for the next 30 days

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include __DIR__ . '/../conn.php';

$response = ['success' => false, 'data' => [], 'message' => ''];

try {
    // Load blocked days (global)
    $diasBloqueados = [];
    $diasBloqueadosEventos = [];

    $qBd = $conn->query("SELECT fecha FROM dias_bloqueados");
    if ($qBd) {
        while ($r = $qBd->fetch_assoc()) {
            $diasBloqueados[] = $r['fecha'];
        }
    }

    $qBe = $conn->query("SELECT fecha FROM dias_bloqueados_eventos");
    if ($qBe) {
        while ($r = $qBe->fetch_assoc()) {
            $diasBloqueadosEventos[] = $r['fecha'];
        }
    }

    // Helper: check if a date is blocked
    function dateIsBlocked($date, $bd, $be) {
        return in_array($date, $bd) || in_array($date, $be);
    }

    // Loop the next 30 days
    // Read optional GET parameters: days (number of days) and start (YYYY-MM-DD)
    $numDays = 30;
    if (isset($_GET['days']) && is_numeric($_GET['days'])) {
        $numDays = min(90, max(1, intval($_GET['days']))); // bound between 1 and 90
    }
    // Ensure we don't return today's date: default to today and then shift to tomorrow if needed
    $today = date('Y-m-d');
    $startDate = $today;
    if (!empty($_GET['start'])) {
        $sd = DateTime::createFromFormat('Y-m-d', $_GET['start']);
        if ($sd !== false) {
            $startDate = $sd->format('Y-m-d');
        }
    }
    // If start is today or earlier, move to tomorrow so today's date is not returned
    if ($startDate <= $today) {
        $startDate = date('Y-m-d', strtotime('+1 day'));
    }

    $days = [];
    for ($i = 0; $i < $numDays; $i++) {
        $date = date('Y-m-d', strtotime($startDate . " +{$i} day"));

        // Check if the date is blocked (global)
        $isBlocked = dateIsBlocked($date, $diasBloqueados, $diasBloqueadosEventos);
        if ($isBlocked) {
            $days[] = [
                'date' => $date,
                'blocked' => true,
                'slots' => []
            ];
            continue;
        }

        // Get atencion rows for that date
        $stmt = $conn->prepare("SELECT id, dia, horarios, idusu FROM atencion WHERE dia = ?");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $res = $stmt->get_result();
        $atenciones = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Gather a map: time => [vendors]
        $slots_map = [];

        foreach ($atenciones as $row) {
            $vendor_id = (int)$row['idusu'];
            $horarios = json_decode($row['horarios'], true);
            if (!is_array($horarios)) {
                continue; // skip if invalid
            }

            // Get vendor name
            $vendorName = '';
            $stmt2 = $conn->prepare("SELECT nombre, apepat, apemat FROM usuarios WHERE id = ? LIMIT 1");
            $stmt2->bind_param('i', $vendor_id);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            $vendorInfo = $res2->fetch_assoc();
            $stmt2->close();

            if ($vendorInfo) {
                $vendorName = trim($vendorInfo['nombre'] . ' ' . $vendorInfo['apepat'] . ' ' . $vendorInfo['apemat']);
                $vendorName = preg_replace('/\s+/', ' ', $vendorName);
            } else {
                $vendorName = 'Vendor ' . $vendor_id;
            }

            // Get reserved times for that vendor and date
            $stmt3 = $conn->prepare("SELECT hora FROM calendario WHERE fecha = ? AND idusu = ?");
            $stmt3->bind_param('si', $date, $vendor_id);
            $stmt3->execute();
            $res3 = $stmt3->get_result();
            $reserved = $res3->fetch_all(MYSQLI_ASSOC);
            $stmt3->close();

            $reserved_set = [];
            foreach ($reserved as $r) {
                // Normalize to HH:MM
                $h = isset($r['hora']) ? substr($r['hora'], 0, 5) : '';
                if ($h) {
                    $reserved_set[$h] = true;
                }
            }

            // For every horario in vendor schedule, if not reserved add to slot
            foreach ($horarios as $h) {
                // normalize e.g., "8:00" to "08:00"
                $time_norm = $h;
                // If it contains seconds, convert to HH:MM
                if (strlen($time_norm) > 5) {
                    $time_norm = substr($time_norm, 0, 5);
                }
                if (preg_match('/^\d{1,2}:\d{2}$/', $time_norm)) {
                    if (!isset($reserved_set[$time_norm])) {
                        if (!isset($slots_map[$time_norm])) {
                            $slots_map[$time_norm] = [];
                        }
                        $slots_map[$time_norm][] = ['vendor_id' => $vendor_id, 'vendor_name' => $vendorName];
                    }
                }
            }
        }

        // Convert map to array
        $slots_arr = [];
        foreach ($slots_map as $time => $vendorsArr) {
            $slots_arr[] = [
                'time' => $time,
                'vendors' => $vendorsArr
            ];
        }

        // Sort slots by time
        usort($slots_arr, function ($a, $b) {
            return strtotime('1970-01-01 ' . $a['time']) - strtotime('1970-01-01 ' . $b['time']);
        });

        $days[] = [
            'date' => $date,
            'blocked' => false,
            'slots' => $slots_arr
        ];
    }

    $response['success'] = true;
    $response['data'] = $days;
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>