<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

include __DIR__ . '/conn.php';
require_once __DIR__ . '/wp_eventos_contact_form_helper.php';

$response = ['success' => false, 'message' => 'No se pudo actualizar la informacion'];

function plannerTableExists($conn, $tableName)
{
    $safeTableName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTableName}'");
    return $result && $result->num_rows > 0;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo no permitido');
    }

    $plannerId = intval($_POST['planner_id'] ?? 0);
    $empresaWp = trim((string) ($_POST['empresa_wp'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $afianzado = intval($_POST['afianzado'] ?? 0);
    $idVendedorAsignado = array_key_exists('id_vendedor_asignado', $_POST) ? intval($_POST['id_vendedor_asignado']) : -1;

    if ($plannerId <= 0) {
        throw new Exception('Planner invalido');
    }

    if ($empresaWp === '') {
        throw new Exception('La empresa es obligatoria');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Correo invalido');
    }

    if (!in_array($afianzado, [0, 1, 2, 3], true)) {
        $afianzado = 0;
    }

    $stmtCheck = $conn->prepare('SELECT id FROM wedding_planners WHERE id = ? LIMIT 1');
    if (!$stmtCheck) {
        throw new Exception('No se pudo validar el planner');
    }

    $stmtCheck->bind_param('i', $plannerId);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    $exists = $resultCheck && $resultCheck->num_rows > 0;
    $stmtCheck->close();

    if (!$exists) {
        throw new Exception('Planner no encontrado');
    }

    $conn->begin_transaction();

    $updateSql = 'UPDATE wedding_planners SET empresa_wp = ?, full_name = ?, city = ?, email = ?, phone = ?, afianzado = ? WHERE id = ? LIMIT 1';
    $updateTypes = 'sssssii';
    $updateParams = [$empresaWp, $empresaWp, $city, $email, $phone, $afianzado, $plannerId];

    // Only update id_vendedor_asignado when the field was explicitly sent
    if ($idVendedorAsignado >= 0) {
        $updateSql = 'UPDATE wedding_planners SET empresa_wp = ?, full_name = ?, city = ?, email = ?, phone = ?, afianzado = ?, id_vendedor_asignado = ? WHERE id = ? LIMIT 1';
        $updateTypes = 'sssssiii';
        $updateParams = [$empresaWp, $empresaWp, $city, $email, $phone, $afianzado, $idVendedorAsignado, $plannerId];
    }

    $stmt = $conn->prepare($updateSql);
    if (!$stmt) {
        throw new Exception('No se pudo preparar la actualizacion');
    }

    $stmt->bind_param($updateTypes, ...$updateParams);

    if (!$stmt->execute()) {
        throw new Exception('No se pudo actualizar: ' . $stmt->error);
    }

    $stmt->close();

    // Pre-qualified espejo: wp_citas_leads
    if (plannerTableExists($conn, 'wp_citas_leads')) {
        $stmt = $conn->prepare('UPDATE wp_citas_leads SET full_name = ? WHERE id_wedding_planner = ?');
        if (!$stmt) {
            throw new Exception('No se pudo preparar la sincronizacion de wp_citas_leads');
        }

        $stmt->bind_param('si', $empresaWp, $plannerId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo sincronizar wp_citas_leads: ' . $stmt->error);
        }
        $stmt->close();
    }

    // Contact forms directos desde wedding_planners.
    if (plannerTableExists($conn, 'contact_form')) {
        $stmt = $conn->prepare("UPDATE contact_form SET names = ? WHERE original_lead_id = ? AND LOWER(COALESCE(tabla_origen, '')) IN ('wedding_planners', 'wedding_planner')");
        if (!$stmt) {
            throw new Exception('No se pudo preparar la sincronizacion de contact_form para wedding_planners');
        }

        $stmt->bind_param('si', $empresaWp, $plannerId);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo sincronizar contact_form (wedding_planners): ' . $stmt->error);
        }
        $stmt->close();

        // Post-qualified desde wp_citas_leads vinculados a este planner.
        if (plannerTableExists($conn, 'wp_citas_leads')) {
            $stmt = $conn->prepare("UPDATE contact_form cf INNER JOIN wp_citas_leads wcl ON wcl.id = cf.original_lead_id SET cf.names = ? WHERE LOWER(COALESCE(cf.tabla_origen, '')) = 'wp_citas_leads' AND wcl.id_wedding_planner = ?");
            if (!$stmt) {
                throw new Exception('No se pudo preparar la sincronizacion de contact_form para wp_citas_leads');
            }

            $stmt->bind_param('si', $empresaWp, $plannerId);
            if (!$stmt->execute()) {
                throw new Exception('No se pudo sincronizar contact_form (wp_citas_leads): ' . $stmt->error);
            }
            $stmt->close();
        }

        // Post-qualified desde eventos asociados al planner.
        if (plannerTableExists($conn, 'eventos_wp')) {
            $stmt = $conn->prepare("UPDATE contact_form cf INNER JOIN eventos_wp ev ON ev.id = cf.original_lead_id SET cf.names = ? WHERE LOWER(COALESCE(cf.tabla_origen, '')) = 'eventos_wp' AND ev.wedding_planner_id = ?");
            if (!$stmt) {
                throw new Exception('No se pudo preparar la sincronizacion de contact_form para eventos_wp');
            }

            $stmt->bind_param('si', $empresaWp, $plannerId);
            if (!$stmt->execute()) {
                throw new Exception('No se pudo sincronizar contact_form (eventos_wp): ' . $stmt->error);
            }
            $stmt->close();
        }
    }

    $conn->commit();

    $response = [
        'success' => true,
        'message' => 'Datos actualizados',
    ];
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // no-op
        }
    }

    $response = [
        'success' => false,
        'message' => $e->getMessage(),
    ];
}

echo json_encode($response);
