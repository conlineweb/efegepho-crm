<?php
header('Content-Type: application/json; charset=utf-8');
include 'conn.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_POST['tabla_origen']) || !isset($_POST['lead_id'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

$tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['tabla_origen']);
$leadId = intval($_POST['lead_id']);

if ($tabla === '' || $leadId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit;
}

// Fetch templates that have a sent_log (could be NULL)
$sql = "SELECT id, nombre, sent_log FROM marketing_templates WHERE sent_log IS NOT NULL";
$res = $conn->query($sql);
$out = [];
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $tplId = $row['id'];
        $tplNombre = $row['nombre'];
        $sentLogRaw = $row['sent_log'];
        if (!$sentLogRaw) continue;
        $arr = json_decode($sentLogRaw, true);
        if (!is_array($arr)) continue;
        foreach ($arr as $entry) {
            // each entry expected to have tabla_origen and lead_id
            if (!isset($entry['lead_id'])) continue;
            if (!isset($entry['tabla_origen'])) continue;
            if (intval($entry['lead_id']) === $leadId && $entry['tabla_origen'] === $tabla) {
                $out[] = [
                    'template_id' => $tplId,
                    'template_nombre' => $tplNombre,
                    'sent_at' => $entry['sent_at'] ?? null,
                    'success' => isset($entry['success']) ? intval($entry['success']) : 0,
                    'mail_error' => $entry['mail_error'] ?? null,
                    'created_by' => isset($entry['created_by']) ? intval($entry['created_by']) : null,
                    'ip' => $entry['ip'] ?? null
                ];
            }
        }
    }
}

// sort by sent_at desc if present
usort($out, function ($a, $b) {
    $ta = !empty($a['sent_at']) ? strtotime($a['sent_at']) : 0;
    $tb = !empty($b['sent_at']) ? strtotime($b['sent_at']) : 0;
    return $tb <=> $ta;
});

echo json_encode(['success' => true, 'data' => $out]);
$conn->close();
