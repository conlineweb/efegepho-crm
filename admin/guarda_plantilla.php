<?php
// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'conn.php'; // Conexión a la base de datos

// Verificar si la conexión a la base de datos fue exitosa
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => "Conexión fallida: " . $conn->connect_error]));
}

// Verificar si se recibieron los datos
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método de solicitud no permitido.']);
    $conn->close();
    exit;
}

// Log de los datos recibidos
error_log("Datos POST recibidos: " . print_r($_POST, true));

// Obtener y validar el ID
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0; // Obtener ID como entero, 0 si no existe

if ($id <= 0) { // Validar que el ID sea mayor que 0
    echo json_encode(['status' => 'error', 'message' => 'El ID es obligatorio y debe ser un número válido.']);
    $conn->close();
    exit;
}

// Verificar si el registro existe
$checkSql = "SELECT id FROM txtCorreo WHERE id = ?";
if ($checkStmt = $conn->prepare($checkSql)) {
    $checkStmt->bind_param('i', $id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows === 0) {
        // El registro no existe, intentar insertarlo
        $insertSql = "INSERT INTO txtCorreo (id, txttituloclie, txtcli, txtdespclie, txttituloadmin, txtadmin, txtdespadmin, txttitulovend, txtvend, txtdespvend) VALUES (?, '', '', '', '', '', '', '', '', '')";
        if ($insertStmt = $conn->prepare($insertSql)) {
            $insertStmt->bind_param('i', $id);
            if (!$insertStmt->execute()) {
                echo json_encode(['status' => 'error', 'message' => 'No se pudo crear el registro. Error: ' . $insertStmt->error]);
                $insertStmt->close();
                $checkStmt->close();
                $conn->close();
                exit;
            }
            $insertStmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo preparar la consulta de inserción. Error: ' . $conn->error]);
            $checkStmt->close();
            $conn->close();
            exit;
        }
    }
    $checkStmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo verificar el registro. Error: ' . $conn->error]);
    $conn->close();
    exit;
}

// Obtener los demás campos, permitiendo que estén vacíos
$campos = [
    'txtTituloCli' => isset($_POST['txtTituloCli']) ? $_POST['txtTituloCli'] : '',
    'txtCli' => isset($_POST['txtCli']) ? $_POST['txtCli'] : '',
    'txtDespCli' => isset($_POST['txtDespCli']) ? $_POST['txtDespCli'] : '',
    'txtTituloAdmin' => isset($_POST['txtTituloAdmin']) ? $_POST['txtTituloAdmin'] : '',
    'txtAdmin' => isset($_POST['txtAdmin']) ? $_POST['txtAdmin'] : '',
    'txtDespAdmin' => isset($_POST['txtDespAdmin']) ? $_POST['txtDespAdmin'] : '',
    'txtTituloVend' => isset($_POST['txtTituloVend']) ? $_POST['txtTituloVend'] : '',
    'txtVend' => isset($_POST['txtVend']) ? $_POST['txtVend'] : '',
    'txtDespVend' => isset($_POST['txtDespVend']) ? $_POST['txtDespVend'] : '',
];

// Construir la consulta SQL con un único UPDATE
$sql = "UPDATE txtCorreo SET 
            txttituloclie = ?, 
            txtcli = ?, 
            txtdespclie = ?, 
            txttituloadmin = ?, 
            txtadmin = ?, 
            txtdespadmin = ?, 
            txttitulovend = ?, 
            txtvend = ?, 
            txtdespvend = ? 
        WHERE id = ?";

error_log("SQL Query: " . $sql);
error_log("Campos: " . print_r($campos, true));

// Preparar y ejecutar la consulta SQL
if ($stmt = $conn->prepare($sql)) {
    // Bind de los parámetros
    $stmt->bind_param('sssssssssi', 
        $campos['txtTituloCli'], 
        $campos['txtCli'], 
        $campos['txtDespCli'], 
        $campos['txtTituloAdmin'], 
        $campos['txtAdmin'], 
        $campos['txtDespAdmin'], 
        $campos['txtTituloVend'], 
        $campos['txtVend'], 
        $campos['txtDespVend'],
        $id
    );

    // Ejecutar la consulta
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Los textos se han actualizado correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Hubo un error al actualizar los datos. Error: ' . $stmt->error . ' - SQL: ' . $sql]);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo preparar la consulta. Error: ' . $conn->error . ' - SQL: ' . $sql]);
}

$conn->close();
?>