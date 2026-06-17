<?php
header('Content-Type: application/json');
include 'conn.php';
session_start();

$id = intval($_POST['id'] ?? 0);
$nombre = $_POST['nombre'] ?? null;
$asunto = $_POST['asunto'] ?? null;
$titulo = $_POST['titulo'] ?? null; // Avoid accidental label usage
$cuerpo = $_POST['cuerpo'] ?? null;
$despedida = $_POST['despedida'] ?? null;
$scheduleEnabled = isset($_POST['schedule_enabled']) ? intval($_POST['schedule_enabled']) : 0;
$scheduleEveryDays = isset($_POST['schedule_every_days']) ? intval($_POST['schedule_every_days']) : 0;
$scheduleTime = $_POST['schedule_time'] ?? '09:00';
$scheduleRepeat = isset($_POST['schedule_repeat']) ? intval($_POST['schedule_repeat']) : 0;
$scheduleEnabled = ($scheduleEnabled === 1) ? 1 : 0;
$scheduleRepeat = ($scheduleRepeat === 1) ? 1 : 0;
$scheduleEveryDays = max(1, $scheduleEveryDays);
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $scheduleTime)) {
  $scheduleTime = '09:00';
}
$creador = $_SESSION['uid'] ?? null;

if(!$nombre || !$titulo){
  echo json_encode(['status' => 'error', 'message' => 'Nombre y t��tulo son obligatorios']);
  exit;
}

if ($id > 0) {
  // UPDATE existing
  $stmt = $conn->prepare("UPDATE marketing_templates SET nombre = ?, asunto = ?, titulo = ?, cuerpo = ?, despedida = ?, schedule_enabled = ?, schedule_every_days = ?, schedule_time = ?, schedule_repeat = ? WHERE id = ?");
  if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Error en la preparaci��n (UPDATE): ' . $conn->error]);
    exit;
  }
  $stmt->bind_param('sssssiisii', $nombre, $asunto, $titulo, $cuerpo, $despedida, $scheduleEnabled, $scheduleEveryDays, $scheduleTime, $scheduleRepeat, $id);
  if ($stmt->execute()) {
    $stmt->close();
    $created_at = null;
    $res = $conn->query("SELECT created_at FROM marketing_templates WHERE id = " . intval($id) . " LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
      $created_at = $row['created_at'];
    }
    echo json_encode(['status' => 'success', 'message' => 'Plantilla actualizada', 'id' => $id, 'created_at' => $created_at, 'isUpdate' => true]);
    $conn->close();
    exit;
  } else {
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar: ' . $stmt->error]);
    $conn->close();
    exit;
  }
}

// prepared statement (include asunto) for INSERT
$stmt = $conn->prepare("INSERT INTO marketing_templates (nombre, asunto, titulo, cuerpo, despedida, creador_id, schedule_enabled, schedule_every_days, schedule_time, schedule_repeat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if(!$stmt){
  echo json_encode(['status' => 'error', 'message' => 'Error en la preparaci��n: '.$conn->error]);
  exit;
}
$stmt->bind_param('sssssiiisi', $nombre, $asunto, $titulo, $cuerpo, $despedida, $creador, $scheduleEnabled, $scheduleEveryDays, $scheduleTime, $scheduleRepeat);
if($stmt->execute()){
  $inserted_id = $stmt->insert_id;
  $stmt->close();
  // obtener created_at para devolver al cliente y mostrar sin recargar
  $created_at = null;
  $res = $conn->query("SELECT created_at FROM marketing_templates WHERE id = " . intval($inserted_id) . " LIMIT 1");
  if ($res && $row = $res->fetch_assoc()) {
    $created_at = $row['created_at'];
  }
  echo json_encode(['status' => 'success', 'message' => 'Plantilla guardada', 'id' => $inserted_id, 'created_at' => $created_at]);
} else {
  $stmt->close();
  echo json_encode(['status' => 'error', 'message' => 'Error al guardar: '.$stmt->error]);
}
$conn->close();
