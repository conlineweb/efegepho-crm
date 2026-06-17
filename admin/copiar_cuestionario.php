<?php
include 'conn.php';
header('Content-Type: application/json');

// Verificar si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit();
}

// Leer los datos JSON del cuerpo de la solicitud
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validar datos básicos
if (empty($data['titulo']) || empty($data['introduccion'])) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos']);
    exit();
}

try {
    // Insertar el nuevo cuestionario
    $sql = "INSERT INTO cuestionario (titulo, idioma, introduccion, preguntas) 
            VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    // Convertir las preguntas a JSON si es un array
    $preguntas_json = is_array($data['preguntas']) ? json_encode($data['preguntas']) : $data['preguntas'];
    
    $stmt->bind_param("siss", 
        $data['titulo'], 
        $data['idioma'], 
        $data['introduccion'], 
        $preguntas_json
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Cuestionario copiado correctamente',
            'new_id' => $stmt->insert_id
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Error al copiar el cuestionario: ' . $conn->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Excepción al copiar el cuestionario: ' . $e->getMessage()
    ]);
}

$conn->close();
?>