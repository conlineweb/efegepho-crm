<?php
include 'conn.php';

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Error desconocido'];

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if(!$data) {
        throw new Exception('Datos JSON inválidos');
    }

    $titulo = $data['titulo'] ?? '';
    $introduccion = $data['introduccion'] ?? '';
    $idioma = $data['idioma'] ?? '';    
    $preguntas = $data['preguntas'] ?? [];
    $id = (int)($data['id'] ?? 0);
    
    if(empty($titulo)) {
        throw new Exception('El título es requerido');
    }

    $json_preguntas = json_encode($preguntas);

    if ($id > 0) {
        // Verificar si ya existe
        $checkStmt = $conn->prepare("SELECT id FROM cuestionario WHERE id = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            // Ya existe: hacer UPDATE
            $stmt = $conn->prepare("UPDATE cuestionario SET titulo = ?, introduccion = ?, idioma = ?, preguntas = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $titulo, $introduccion, $idioma, $json_preguntas, $id);
        } else {
            // No existe: hacer INSERT
            $stmt = $conn->prepare("INSERT INTO cuestionario (titulo, introduccion, idioma, preguntas) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $titulo, $introduccion, $idioma, $json_preguntas);
        }

        $checkStmt->close();
    } else {
        // Nuevo cuestionario (INSERT)
        $stmt = $conn->prepare("INSERT INTO cuestionario (titulo, introduccion, idioma, preguntas) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $titulo, $introduccion, $idioma, $json_preguntas);
    }

    if($stmt->execute()) {
        $response = [
            'status' => 'success',
            'message' => $id > 0 ? 'Cuestionario actualizado' : 'Cuestionario guardado',
            'id' => $id > 0 ? $id : $conn->insert_id
        ];
    } else {
        throw new Exception('Error al guardar/actualizar: '.$stmt->error);
    }

} catch(Exception $e) {
    $response['message'] = $e->getMessage();
} finally {
    echo json_encode($response);
    if(isset($stmt)) $stmt->close();
    $conn->close();
}
?>
