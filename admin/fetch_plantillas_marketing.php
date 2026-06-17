<?php
header('Content-Type: application/json');
include 'conn.php';

// if id param provided, return single record
if(isset($_GET['id'])){
  $id = intval($_GET['id']);
  $sql = "SELECT id, nombre, asunto, titulo, cuerpo, despedida, schedule_enabled, schedule_every_days, schedule_time, schedule_repeat, created_at FROM marketing_templates WHERE id = ? LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  echo json_encode(['data' => $row ? [$row] : []]);
  exit;
}

$sql = "SELECT id, nombre, asunto, titulo, despedida, schedule_enabled, schedule_every_days, schedule_time, schedule_repeat, created_at FROM marketing_templates ORDER BY id DESC";
$result = $conn->query($sql);
if ($result === false) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error en consulta', 'db_error' => $conn->error]);
  $conn->close();
  exit;
}
$out = [];
while($r = $result->fetch_assoc()){
  $out[] = $r;
}

echo json_encode(['data' => $out]);
$conn->close();
