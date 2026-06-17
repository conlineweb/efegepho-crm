    <?php
    include 'autoload_session.php';
    include 'conn.php';
    header('Content-Type: application/json');
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    $tipoUsu = intval($_SESSION['tus'] ?? -1);
    $userId = intval($_SESSION['uid'] ?? 0);
    $canDelete = ($tipoUsu === 0 || $userId === 1);

    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para ocultar wedding planners']);
        exit;
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    try {
        $colCheck = $conn->query("SHOW COLUMNS FROM `wedding_planners` LIKE 'eliminado'");
        if (!$colCheck || $colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE `wedding_planners` ADD COLUMN `eliminado` TINYINT(1) NOT NULL DEFAULT 0 AFTER `afianzado`");
        }

        $stmt = $conn->prepare('UPDATE wedding_planners SET eliminado = 1 WHERE id = ? AND (eliminado = 0 OR eliminado IS NULL)');
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            throw new Exception('Error al ocultar wedding planner: ' . $stmt->error);
        }
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Wedding Planner no encontrado o ya oculto']);
        }
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    ?>
