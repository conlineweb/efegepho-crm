<?php
header('Content-Type: application/json; charset=utf-8');

include 'conn.php';

// Simple helper for JSON response
function res($success, $msg = '', $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $msg], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    res(false, 'Método no permitido');
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$tabla = isset($_POST['tabla_origen']) ? trim($_POST['tabla_origen']) : '';

if ($id <= 0 || $tabla === '') {
    res(false, 'Parámetros inválidos');
}

// Check if table is a valid lead table (tablas_leads)
$safeTableName = $conn->real_escape_string($tabla);
$chk = $conn->query("SELECT 1 FROM tablas_leads WHERE nombre = '" . $safeTableName . "' LIMIT 1");
if (!$chk || $chk->num_rows === 0) {
    // Some installations may not have tablas_leads populated; however for security
    // we'll still check that the referenced table exists in the DB.
    $tbExists = $conn->query("SHOW TABLES LIKE '" . $safeTableName . "'");
    if (!$tbExists || $tbExists->num_rows === 0) {
        res(false, 'Tabla de origen no existe');
    }
}

// Determine which columns exist in the target table
$colsRes = $conn->query("SHOW COLUMNS FROM `" . $safeTableName . "`");
if (!$colsRes) {
    res(false, 'No se pudo obtener la estructura de la tabla');
}
$cols = [];
while ($r = $colsRes->fetch_assoc()) {
    $cols[] = $r['Field'];
}

$updates = [];
$updatedCols = [];
if (in_array('correo_uno_enviado', $cols)) { $updates[] = "correo_uno_enviado = 0"; $updatedCols[] = 'correo_uno_enviado'; }
if (in_array('fecha_envio_correo_uno', $cols)) { $updates[] = "fecha_envio_correo_uno = NULL"; $updatedCols[] = 'fecha_envio_correo_uno'; }
if (in_array('correo_dos_enviado', $cols)) { $updates[] = "correo_dos_enviado = 0"; $updatedCols[] = 'correo_dos_enviado'; }
if (in_array('fecha_envio_correo_dos', $cols)) { $updates[] = "fecha_envio_correo_dos = NULL"; $updatedCols[] = 'fecha_envio_correo_dos'; }

if (empty($updates)) {
    res(false, 'La tabla no contiene las columnas de correo a reestablecer');
}

$sql = "UPDATE `" . $safeTableName . "` SET " . implode(', ', $updates) . " WHERE id = " . intval($id);

if ($conn->query($sql) === TRUE) {
    res(true, 'Reestablecido con éxito', ['updated' => $updatedCols]);
} else {
    res(false, 'Error al actualizar: ' . $conn->error);
}

?>