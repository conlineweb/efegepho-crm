<?php
include 'autoload_session.php';
include 'conn.php';
require_once __DIR__ . '/usuario_roles_helper.php';

header('Content-Type: application/json; charset=utf-8');

$sessionUserId = (int) ($_SESSION['uid'] ?? 0);
$tipoUsuario = $_SESSION['tus'] ?? '';

$isVendedoraLocked = !usuarioTipoVeTodoEnVistasComerciales($tipoUsuario)
    && (int) $tipoUsuario === USUARIO_ROL_VENDEDOR;

if ($isVendedoraLocked) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para cambiar vendedor']);
    exit;
}

$tablaOrigen = trim((string) ($_POST['tabla_origen'] ?? ''));
$leadId = (int) ($_POST['lead_id'] ?? 0);
$cfId = (int) ($_POST['cf_id'] ?? 0);
$calendarioId = (int) ($_POST['calendario_id'] ?? 0);
$idVendedor = (int) ($_POST['id_vendedor_asignado'] ?? 0);

if ($tablaOrigen === '' || $leadId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Lead inválido']);
    exit;
}

$vendedorNombre = '';
$vendedorApepat = '';

if ($idVendedor > 0) {
    $stmt = $conn->prepare('SELECT id, nombre, apepat, tipoUsu FROM usuarios WHERE id = ? LIMIT 1');
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Error al validar vendedora']);
        exit;
    }
    $stmt->bind_param('i', $idVendedor);
    $stmt->execute();
    $resu = $stmt->get_result();
    $stmt->close();

    if (!$resu || $resu->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Vendedora no encontrada']);
        exit;
    }

    $rowu = $resu->fetch_assoc();
    if (!usuarioEsAsignableEnLeadBoard($rowu['tipoUsu'] ?? -1, $sessionUserId, $idVendedor)) {
        echo json_encode(['success' => false, 'message' => 'El usuario seleccionado no es una vendedora válida']);
        exit;
    }

    $vendedorNombre = trim((string) ($rowu['nombre'] ?? ''));
    $vendedorApepat = trim((string) ($rowu['apepat'] ?? ''));
}

function mlbAssignUpdateCalendarioVendor($conn, int $calendarioId, int $eventId, int $idVendedor): void
{
    if ($calendarioId > 0) {
        $stmt = $conn->prepare('UPDATE calendario SET idusu = ? WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ii', $idVendedor, $calendarioId);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    if ($eventId > 0) {
        $stmt = $conn->prepare('UPDATE calendario SET idusu = ? WHERE idclie = ? AND tipo = 1 LIMIT 50');
        if ($stmt) {
            $stmt->bind_param('ii', $idVendedor, $eventId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function mlbAssignUpdateContactFormVendor($conn, int $cfId, int $eventId, int $idVendedor): void
{
    if ($cfId > 0) {
        $stmt = $conn->prepare('UPDATE contact_form SET id_vendedor_asignado = ?, manual = 0 WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ii', $idVendedor, $cfId);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    if ($eventId > 0) {
        $stmt = $conn->prepare(
            "UPDATE contact_form
             SET id_vendedor_asignado = ?, manual = 0
             WHERE evento_wp_id = ?
                OR (LOWER(TRIM(COALESCE(tabla_origen, ''))) = 'eventos_wp' AND original_lead_id = ?)
             LIMIT 20"
        );
        if ($stmt) {
            $stmt->bind_param('iii', $idVendedor, $eventId, $eventId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function mlbAssignLeadTableHasColumn($conn, string $tableName, string $columnName): bool
{
    static $cache = [];
    $key = strtolower($tableName . '|' . $columnName);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $safeTable = str_replace('`', '', $tableName);
    $safeTable = $conn->real_escape_string($safeTable);
    $safeColumn = $conn->real_escape_string($columnName);
    $res = $conn->query("SHOW COLUMNS FROM `" . $safeTable . "` LIKE '" . $safeColumn . "'");
    $cache[$key] = ($res && $res->num_rows > 0);

    return $cache[$key];
}

function mlbAssignUpdateLeadTableVendor($conn, string $tableName, int $leadId, int $idVendedor): void
{
    $stmtCheck = $conn->prepare('SELECT 1 FROM tablas_leads WHERE nombre = ? LIMIT 1');
    if (!$stmtCheck) {
        throw new RuntimeException('Error al validar tabla de lead');
    }
    $stmtCheck->bind_param('s', $tableName);
    $stmtCheck->execute();
    $exists = $stmtCheck->get_result()->num_rows > 0;
    $stmtCheck->close();

    if (!$exists) {
        throw new RuntimeException('Tabla de leads no válida');
    }

    $safeTable = str_replace('`', '', $tableName);
    $safeTable = $conn->real_escape_string($safeTable);

    if (mlbAssignLeadTableHasColumn($conn, $tableName, 'usuario_asignado')) {
        if ($idVendedor > 0) {
            $sql = "UPDATE `" . $safeTable . "` SET usuario_asignado = ? WHERE id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('ii', $idVendedor, $leadId);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $sql = "UPDATE `" . $safeTable . "` SET usuario_asignado = NULL WHERE id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $leadId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if (mlbAssignLeadTableHasColumn($conn, $tableName, 'id_vendedor_asignado')) {
        $stmt = $conn->prepare("UPDATE `" . $safeTable . "` SET id_vendedor_asignado = ? WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ii', $idVendedor, $leadId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$conn->begin_transaction();

try {
    if (strcasecmp($tablaOrigen, 'eventos_wp') === 0) {
        $stmt = $conn->prepare('UPDATE eventos_wp SET id_asesor = ? WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Error al preparar actualización del evento');
        }
        $stmt->bind_param('ii', $idVendedor, $leadId);
        if (!$stmt->execute()) {
            throw new RuntimeException('No se pudo actualizar el evento: ' . $stmt->error);
        }
        $stmt->close();

        mlbAssignUpdateContactFormVendor($conn, $cfId, $leadId, $idVendedor);
        mlbAssignUpdateCalendarioVendor($conn, $calendarioId, $leadId, $idVendedor);
    } elseif ($cfId > 0) {
        mlbAssignUpdateContactFormVendor($conn, $cfId, 0, $idVendedor);
        mlbAssignUpdateCalendarioVendor($conn, $calendarioId, 0, $idVendedor);
        if (strcasecmp($tablaOrigen, 'eventos_wp') !== 0) {
            mlbAssignUpdateLeadTableVendor($conn, $tablaOrigen, $leadId, $idVendedor);
        }
    } else {
        mlbAssignUpdateLeadTableVendor($conn, $tablaOrigen, $leadId, $idVendedor);
        mlbAssignUpdateCalendarioVendor($conn, $calendarioId, 0, $idVendedor);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $idVendedor > 0 ? 'Vendedora asignada' : 'Vendedora removida',
        'id_vendedor_asignado' => $idVendedor,
        'vendedor_nombre' => $vendedorNombre,
        'vendedor_apepat' => $vendedorApepat,
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
