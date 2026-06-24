<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$isEmbed = (($_GET['embed'] ?? $_POST['embed'] ?? '') === '1');

if ($isEmbed) {
    $duracionSesion = 60 * 60 * 24 * 30;
    session_set_cookie_params([
        'lifetime' => $duracionSesion,
        'path' => '/',
        'domain' => '.efegepho.com.mx',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    ini_set('session.gc_maxlifetime', $duracionSesion);
    session_start();
    if (!isset($_SESSION['login']) || $_SESSION['login'] === false) {
        http_response_code(401);
        echo '<!DOCTYPE html><html lang="es"><body style="font-family:sans-serif;padding:24px;">Sesión no válida.</body></html>';
        exit;
    }
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
    echo '<style>body{margin:0;background:#f8fafc;}</style></head><body>';
} else {
    include 'menu.php';
}

include 'conn.php';
date_default_timezone_set('America/Mexico_City');
require_once __DIR__ . '/calendario_estatus_historial_helper.php';

function ensureLeadInteractionsTable($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS `lead_interactions` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `tabla_origen` VARCHAR(120) NOT NULL,
        `lead_id` INT NOT NULL,
        `original_lead_id` INT DEFAULT NULL,
        `interaction_type` VARCHAR(40) DEFAULT NULL,
        `interaction_date` DATE DEFAULT NULL,
        `interaction_time` VARCHAR(20) DEFAULT NULL,
        `notes` TEXT,
        `outcome` VARCHAR(40) DEFAULT NULL,
        `next_action` VARCHAR(255) DEFAULT NULL,
        `next_action_date` DATE DEFAULT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_lead_interactions_lead` (`tabla_origen`, `lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->query($sql);

    $checkOriginalLeadId = $conn->query("SHOW COLUMNS FROM `lead_interactions` LIKE 'original_lead_id'");
    if ($checkOriginalLeadId && $checkOriginalLeadId->num_rows === 0) {
        $conn->query("ALTER TABLE `lead_interactions` ADD COLUMN `original_lead_id` INT DEFAULT NULL AFTER `lead_id`");
    }

    $checkInteractionType = $conn->query("SHOW COLUMNS FROM `lead_interactions` LIKE 'interaction_type'");
    if ($checkInteractionType && $checkInteractionType->num_rows > 0) {
        $col = $checkInteractionType->fetch_assoc();
        if (($col['Null'] ?? '') === 'NO') {
            $conn->query("ALTER TABLE `lead_interactions` MODIFY COLUMN `interaction_type` VARCHAR(40) DEFAULT NULL");
        }
    }

    $checkCompleted = $conn->query("SHOW COLUMNS FROM `lead_interactions` LIKE 'next_action_completed'");
    if ($checkCompleted && $checkCompleted->num_rows === 0) {
        $conn->query("ALTER TABLE `lead_interactions` ADD COLUMN `next_action_completed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `next_action_date`");
    }
}

function safeTableName($name)
{
    $name = trim((string) $name);
    if ($name === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        return '';
    }
    return $name;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function normalizeTextKey($value)
{
    return mb_strtolower(trim((string) $value), 'UTF-8');
}

function normalizeOptionalDate($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    // Acepta formato ISO (Y-m-d).
    $iso = DateTime::createFromFormat('Y-m-d', $value);
    if ($iso && $iso->format('Y-m-d') === $value) {
        return $value;
    }

    // Acepta formato comun en MX/ES (d/m/Y) y lo convierte a ISO.
    $mx = DateTime::createFromFormat('d/m/Y', $value);
    if ($mx && $mx->format('d/m/Y') === $value) {
        return $mx->format('Y-m-d');
    }

    // Si viene incompleta o invalida, no bloquea el guardado.
    return '';
}

function interactionTypeIconClass($type)
{
    $key = normalizeTextKey($type);
    if ($key === 'llamada') {
        return 'fas fa-phone-alt';
    }
    if ($key === 'whatsapp') {
        return 'fab fa-whatsapp';
    }
    if ($key === 'email') {
        return 'far fa-envelope';
    }
    if ($key === 'reunión' || $key === 'reunion') {
        return 'fas fa-handshake';
    }
    if ($key === 'nota interna') {
        return 'fas fa-edit';
    }
    if ($key === 'sin respuesta') {
        return 'fas fa-ellipsis-h';
    }

    return 'far fa-comment-dots';
}

function interactionTypeEmoji($type)
{
    $key = normalizeTextKey($type);
    if ($key === 'llamada') {
        return '📞';
    }
    if ($key === 'whatsapp') {
        return '💬';
    }
    if ($key === 'email') {
        return '✉️';
    }
    if ($key === 'reunión' || $key === 'reunion') {
        return '🤝';
    }
    if ($key === 'nota interna') {
        return '📝';
    }
    if ($key === 'sin respuesta') {
        return '…';
    }

    return '💭';
}

function interactionTypeClass($type)
{
    $key = normalizeTextKey($type);
    if ($key === 'llamada') {
        return 'type-llamada';
    }
    if ($key === 'whatsapp') {
        return 'type-whatsapp';
    }
    if ($key === 'email') {
        return 'type-email';
    }
    if ($key === 'reunión' || $key === 'reunion') {
        return 'type-reunion';
    }
    if ($key === 'nota interna') {
        return 'type-nota';
    }
    if ($key === 'sin respuesta') {
        return 'type-sinrespuesta';
    }

    return 'type-default';
}

function outcomeClass($outcome)
{
    $key = normalizeTextKey($outcome);
    if ($key === 'positivo') {
        return 'outcome-positivo';
    }
    if ($key === 'neutral') {
        return 'outcome-neutral';
    }
    if ($key === 'negativo') {
        return 'outcome-negativo';
    }
    if ($key === 'listo para cerrar') {
        return 'outcome-cerrar';
    }
    if ($key === 'requiere seguimiento') {
        return 'outcome-seguimiento';
    }
    if ($key === 'sin respuesta') {
        return 'outcome-sinrespuesta';
    }

    return 'outcome-default';
}

function outcomeIcon($outcome)
{
    $key = normalizeTextKey($outcome);
    if ($key === 'positivo') {
        return '✅';
    }
    if ($key === 'neutral') {
        return '⚪';
    }
    if ($key === 'negativo') {
        return '❌';
    }
    if ($key === 'listo para cerrar') {
        return '🔥';
    }
    if ($key === 'requiere seguimiento') {
        return '⏳';
    }
    if ($key === 'sin respuesta') {
        return '…';
    }

    return '•';
}

function resolveOriginTableName($conn, $name)
{
    $safe = safeTableName($name);
    if ($safe === '') {
        return '';
    }

    $stmt = $conn->prepare('SELECT nombre FROM tablas_leads WHERE LOWER(nombre) = LOWER(?) LIMIT 1');
    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('s', $safe);
    $validName = '';
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $validName = trim((string) ($row['nombre'] ?? ''));
        }
    }
    $stmt->close();

    if ($validName !== '') {
        return $validName;
    }

    // Fallback: si no existe en tablas_leads, permite el nombre solo si la tabla existe realmente.
    $existsStmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    if (!$existsStmt) {
        return '';
    }

    $existsStmt->bind_param('s', $safe);
    $exists = false;
    if ($existsStmt->execute()) {
        $existsRes = $existsStmt->get_result();
        $exists = ($existsRes && $existsRes->num_rows > 0);
    }
    $existsStmt->close();

    return $exists ? $safe : '';
}

function formatLeadInteractionVendorName($nombre, $apepat)
{
    return trim(trim((string) $nombre) . ' ' . trim((string) $apepat));
}

function resolveLeadInteractionVendedorId(array $lead, $appointment, array $appointmentsByCalendarioId)
{
    if (is_array($appointment)) {
        $fromAppointment = intval($appointment['idusu'] ?? 0);
        if ($fromAppointment > 0) {
            return $fromAppointment;
        }
    }

    $calendarioId = intval($lead['id_calendario'] ?? 0);
    if ($calendarioId > 0 && isset($appointmentsByCalendarioId[$calendarioId])) {
        $fromCalendario = intval($appointmentsByCalendarioId[$calendarioId]['idusu'] ?? 0);
        if ($fromCalendario > 0) {
            return $fromCalendario;
        }
    }

    $fromContactForm = intval($lead['cf_vendedor_asignado'] ?? 0);
    if ($fromContactForm > 0) {
        return $fromContactForm;
    }

    return intval($lead['id_vendedor_asignado'] ?? $lead['usuario_asignado'] ?? 0);
}

function loadLeadInteractionVendorName($conn, $vendorId)
{
    $vendorId = intval($vendorId);
    if ($vendorId <= 0) {
        return '';
    }

    $stmt = $conn->prepare('SELECT nombre, apepat FROM usuarios WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('i', $vendorId);
    $vendorName = '';
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $vendorName = formatLeadInteractionVendorName($row['nombre'] ?? '', $row['apepat'] ?? '');
        }
    }
    $stmt->close();

    return $vendorName;
}

ensureLeadInteractionsTable($conn);

$tablaOrigen = resolveOriginTableName($conn, $_GET['tabla_origen'] ?? '');
$leadId = intval($_GET['id'] ?? 0);

if ($tablaOrigen === '' || $leadId <= 0) {
    $invalidMsg = 'Parámetros inválidos o tabla de origen no permitida.';
    if ($isEmbed) {
        echo '<div style="padding:24px;font-family:Arial,sans-serif;">' . htmlspecialchars($invalidMsg, ENT_QUOTES, 'UTF-8') . '</div></body></html>';
    } else {
        echo '<div style="padding:24px;font-family:Arial,sans-serif;">' . htmlspecialchars($invalidMsg, ENT_QUOTES, 'UTF-8') . '</div></div></body></html>';
    }
    exit;
}

$saveSuccess = false;
$saveError = '';
$showSaved = (($_GET['saved'] ?? '') === '1');

$lead = [
    'full_name' => 'Lead #' . $leadId,
    'city' => '',
    'campaign_name' => '',
    'id_contact_form' => 0,
    'id_calendario' => 0,
    'cf_vendedor_asignado' => 0,
    'id_vendedor_asignado' => 0,
    'usuario_asignado' => 0,
    'vendor_name' => 'Sin asignar',
    'package_name' => 'Sin paquete',
];

$leadExists = false;

$leadSql = "SELECT * FROM `" . $tablaOrigen . "` WHERE id = ? LIMIT 1";
$leadStmt = $conn->prepare($leadSql);
if ($leadStmt) {
    $leadStmt->bind_param('i', $leadId);
    if ($leadStmt->execute()) {
        $res = $leadStmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $leadExists = true;
            $lead['full_name'] = trim((string) ($row['full_name'] ?? $row['names'] ?? ('Lead #' . $leadId)));
            $lead['city'] = trim((string) ($row['city'] ?? ''));
            $lead['campaign_name'] = trim((string) ($row['campaign_name'] ?? ''));
            $lead['id_calendario'] = intval($row['id_calendario'] ?? 0);
            $lead['id_vendedor_asignado'] = intval($row['id_vendedor_asignado'] ?? 0);
            $lead['usuario_asignado'] = intval($row['usuario_asignado'] ?? 0);
        }
    }
    $leadStmt->close();
}

if (!$leadExists) {
    $missingMsg = 'No existe un registro con ese ID en la tabla de origen seleccionada.';
    if ($isEmbed) {
        echo '<div style="padding:24px;font-family:Arial,sans-serif;">' . htmlspecialchars($missingMsg, ENT_QUOTES, 'UTF-8') . '</div></body></html>';
    } else {
        echo '<div style="padding:24px;font-family:Arial,sans-serif;">' . htmlspecialchars($missingMsg, ENT_QUOTES, 'UTF-8') . '</div></div></body></html>';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $interactionType = trim((string) ($_POST['interaction_type'] ?? ''));
    if ($interactionType === '') {
        $interactionType = 'Sin respuesta';
    }
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $outcome = trim((string) ($_POST['outcome'] ?? ''));
    if ($outcome === '') {
        $outcome = 'Sin respuesta';
    }
    $nextAction = trim((string) ($_POST['next_action'] ?? ''));
    $nextActionDate = normalizeOptionalDate($_POST['next_action_date'] ?? '');

    $interactionDate = date('Y-m-d');
    $interactionTime = date('H:i:s');

    $createdBy = isset($_SESSION['uid']) ? intval($_SESSION['uid']) : null;

    $stmt = $conn->prepare("INSERT INTO lead_interactions
        (tabla_origen, lead_id, original_lead_id, interaction_type, interaction_date, interaction_time, notes, outcome, next_action, next_action_date, created_by)
        VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)");

    if ($stmt) {
        $stmt->bind_param(
            'siisssssssi',
            $tablaOrigen,
            $leadId,
            $leadId,
            $interactionType,
            $interactionDate,
            $interactionTime,
            $notes,
            $outcome,
            $nextAction,
            $nextActionDate,
            $createdBy
        );

        if ($stmt->execute()) {
            $saveSuccess = true;

            $redirectUrl = 'lead_interaction.php?tabla_origen=' . urlencode($tablaOrigen) . '&id=' . intval($leadId) . '&saved=1';
            if ($isEmbed) {
                $redirectUrl .= '&embed=1';
            }
            if (!headers_sent()) {
                header('Location: ' . $redirectUrl);
                exit;
            }

            $redirectUrlJs = json_encode($redirectUrl);
            echo "<script>window.location.href=" . $redirectUrlJs . ";</script>";
            echo "<noscript><meta http-equiv='refresh' content='0;url=" . h($redirectUrl) . "'></noscript>";
            exit;
        } else {
            $saveError = 'No se pudo guardar la interacción.';
        }
        $stmt->close();
    } else {
        $saveError = 'Error preparando el guardado de interacción.';
    }
}

$cfSql = "SELECT cf.id, cf.id_vendedor_asignado, cf.paquete, p.nombre AS paquete_nombre
          FROM contact_form cf
          LEFT JOIN paquetes p ON p.id = cf.paquete
          WHERE LOWER(cf.tabla_origen) = LOWER(?) AND cf.original_lead_id = ?
          ORDER BY cf.id DESC
          LIMIT 1";
$cfStmt = $conn->prepare($cfSql);
if ($cfStmt) {
    $cfStmt->bind_param('si', $tablaOrigen, $leadId);
    if ($cfStmt->execute()) {
        $res = $cfStmt->get_result();
        if ($res && $res->num_rows > 0) {
            $cf = $res->fetch_assoc();
            $lead['id_contact_form'] = intval($cf['id'] ?? 0);
            $lead['cf_vendedor_asignado'] = intval($cf['id_vendedor_asignado'] ?? 0);
            $packageName = trim((string) ($cf['paquete_nombre'] ?? ''));
            if ($packageName !== '') {
                $lead['package_name'] = $packageName;
            }
        }
    }
    $cfStmt->close();
}

$appointment = null;
$cfId = intval($lead['id_contact_form'] ?? 0);
if ($cfId > 0) {
    $apptStmt = $conn->prepare('SELECT id, idusu, fecha, hora FROM calendario WHERE idclie = ? ORDER BY fecha DESC, hora DESC, id DESC LIMIT 1');
    if ($apptStmt) {
        $apptStmt->bind_param('i', $cfId);
        if ($apptStmt->execute()) {
            $apptRes = $apptStmt->get_result();
            if ($apptRes && $apptRes->num_rows > 0) {
                $appointment = $apptRes->fetch_assoc();
            }
        }
        $apptStmt->close();
    }
}

$appointmentsByCalendarioId = [];
$calendarioId = intval($lead['id_calendario'] ?? 0);
if ($calendarioId > 0) {
    $calStmt = $conn->prepare('SELECT id, idusu FROM calendario WHERE id = ? LIMIT 1');
    if ($calStmt) {
        $calStmt->bind_param('i', $calendarioId);
        if ($calStmt->execute()) {
            $calRes = $calStmt->get_result();
            if ($calRes && $calRes->num_rows > 0) {
                $calRow = $calRes->fetch_assoc();
                $appointmentsByCalendarioId[$calendarioId] = $calRow;
            }
        }
        $calStmt->close();
    }
}

$resolvedVendorId = resolveLeadInteractionVendedorId($lead, $appointment, $appointmentsByCalendarioId);
$resolvedVendorName = loadLeadInteractionVendorName($conn, $resolvedVendorId);
if ($resolvedVendorName !== '') {
    $lead['vendor_name'] = $resolvedVendorName;
}

$history = [];
$historyStmt = $conn->prepare("SELECT * FROM lead_interactions WHERE tabla_origen = ? AND lead_id = ? ORDER BY created_at DESC, id DESC LIMIT 50");
if ($historyStmt) {
    $historyStmt->bind_param('si', $tablaOrigen, $leadId);
    if ($historyStmt->execute()) {
        $res = $historyStmt->get_result();
        while ($res && $row = $res->fetch_assoc()) {
            $history[] = $row;
        }
    }
    $historyStmt->close();
}
?>

<style>
    .li-page {
        padding: 12px 20px 36px;
        font-family: 'DM Sans', system-ui, sans-serif;
        color: #1f2937;
    }

    .li-page--embed {
        padding: 12px 16px 20px;
    }

    .wp-accounts-topbar {
        background: #ffffff;
        border-bottom: 1px solid #e2e5ea;
        padding: 14px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 82px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07), 0 1px 2px rgba(0, 0, 0, 0.04);
        margin-bottom: 14px;
    }

    .wp-accounts-topbar-left h1 {
        font-size: 17px;
        font-weight: 700;
        margin: 0;
        color: #111827;
    }

    .li-header-title {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 8px;
    }

    .li-header-lead {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
        line-height: 1.2;
    }

    .li-header-vendor {
        font-size: 20px;
        font-weight: 600;
        color: #4b5563;
        line-height: 1.2;
    }

    .li-header-vendor::before {
        content: '·';
        margin-right: 8px;
        color: #9ca3af;
        font-weight: 400;
    }

    .wp-accounts-topbar-left p {
        font-size: 12px;
        color: #9ca3af;
        margin: 2px 0 0;
    }

    .wp-accounts-action-chip {
        background: #111827;
        color: #fff;
        border-radius: 8px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 700;
    }

    .li-panel {
        background: #fff;
        border: 1px solid #e4e7ec;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 12px;
    }

    .li-panel h3 {
        margin: 0 0 12px;
        font-size: 18px;
        font-weight: 700;
        color: #111827;
    }

    .li-label {
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 6px;
        font-weight: 600;
    }

    .li-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .li-input,
    .li-textarea {
        width: 100%;
        border: 1px solid #d8dce4;
        border-radius: 8px;
        padding: 9px 11px;
        font-size: 13px;
        color: #111827;
        outline: none;
        background: #fff;
    }

    .li-textarea {
        min-height: 88px;
        resize: vertical;
    }

    .li-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .li-pill {
        position: relative;
    }

    .li-pill input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .li-pill > span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 8px;
        border: 1px solid #d8dce4;
        background: #fff;
        color: #4b5563;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .li-pill input:checked + span {
        border-color: #3b82f6;
        background: #eff6ff;
        color: #1d4ed8;
    }

    .li-pill.interaction-pill > span i {
        font-size: 12px;
        opacity: 0.9;
    }

    .li-emoji {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        line-height: 1;
        font-style: normal;
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0;
    }

    .li-pill.outcome-pill > span {
        border-color: #e3e7ee;
    }

    .li-pill.outcome-pill input:checked + span.outcome-positivo {
        background: #eaf7e8;
        border-color: #8ecf7d;
        color: #2f7f2e;
    }

    .li-pill.outcome-pill input:checked + span.outcome-neutral {
        background: #f2f4f7;
        border-color: #d7dce5;
        color: #556070;
    }

    .li-pill.outcome-pill input:checked + span.outcome-negativo {
        background: #fdeceb;
        border-color: #f1b6b1;
        color: #aa2b1f;
    }

    .li-pill.outcome-pill input:checked + span.outcome-cerrar {
        background: #fff2df;
        border-color: #f4cd8f;
        color: #a86206;
    }

    .li-pill.outcome-pill input:checked + span.outcome-seguimiento {
        background: #e9f1ff;
        border-color: #b9d0ff;
        color: #2a62c9;
    }

    .li-pill.outcome-pill input:checked + span.outcome-sinrespuesta {
        background: #fff8e7;
        border-color: #ecd99b;
        color: #9f7712;
    }

    .li-pill.outcome-pill input:checked + span.outcome-default {
        background: #f2f4f7;
        border-color: #d7dce5;
        color: #556070;
    }

    .li-actions {
        margin-top: 10px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .li-btn {
        border: 1px solid #d8dce4;
        background: #fff;
        color: #374151;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
    }

    .li-btn.primary {
        background: #111827;
        border-color: #111827;
        color: #fff;
    }

    .li-history-item {
        padding: 12px 8px;
        margin: 0;
        border-bottom: 1px solid #edf0f4;
        background: transparent;
    }

    .li-history-item:last-child {
        border-bottom: none;
    }

    .li-history-top {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 4px;
    }

    .li-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: #eaf0ff;
        color: #2b4fa8;
    }

    .li-outcome {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: #eef7e8;
        color: #447e23;
    }

    .li-badge.type-llamada {
        background: #eaf2ff;
        color: #2555a8;
    }

    .li-badge.type-whatsapp {
        background: #e8f7ee;
        color: #1f7c42;
    }

    .li-badge.type-email {
        background: #ececff;
        color: #4f46b8;
    }

    .li-badge.type-reunion {
        background: #fff2e8;
        color: #a25419;
    }

    .li-badge.type-nota {
        background: #f0eefc;
        color: #5f46b0;
    }

    .li-badge.type-sinrespuesta {
        background: #fff8e7;
        color: #9f7712;
    }

    .li-outcome.outcome-positivo {
        background: #eaf7e8;
        color: #2f7f2e;
    }

    .li-outcome.outcome-neutral {
        background: #f2f4f7;
        color: #556070;
    }

    .li-outcome.outcome-negativo {
        background: #fdeceb;
        color: #aa2b1f;
    }

    .li-outcome.outcome-cerrar {
        background: #fff2df;
        color: #a86206;
    }

    .li-outcome.outcome-seguimiento {
        background: #e9f1ff;
        color: #2a62c9;
    }

    .li-outcome.outcome-sinrespuesta {
        background: #fff8e7;
        color: #9f7712;
    }

    .li-note {
        font-size: 13px;
        color: #1f2937;
    }

    .li-meta {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 3px;
    }

    .li-alert {
        border: 1px solid #cbe7cf;
        background: #f3fbf4;
        color: #2f7a38;
        border-radius: 8px;
        padding: 10px 12px;
        font-size: 12px;
        margin-bottom: 10px;
    }

    .li-alert.error {
        border-color: #f3c9cf;
        background: #fff5f6;
        color: #a62332;
    }

    .li-completed-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 999px;
        background: #dcfce7;
        color: #166534;
        font-size: 11px;
        font-weight: 700;
        margin-left: 6px;
    }

    .li-btn-complete {
        margin-left: 6px;
        padding: 2px 8px;
        font-size: 11px;
        border-radius: 999px;
        cursor: pointer;
        background: #f0fdf4;
        border-color: #86efac;
        color: #166534;
    }

    .li-btn-complete:hover {
        background: #dcfce7;
    }

    @media (max-width: 900px) {
        .li-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="li-page<?php echo $isEmbed ? ' li-page--embed' : ''; ?>">
    <header class="wp-accounts-topbar">
        <div class="wp-accounts-topbar-left">
            <h1 class="li-header-title">
                <span class="li-header-lead"><?php echo h($lead['full_name']); ?></span>
                <?php if (!$isEmbed): ?>
                <span class="li-header-vendor">Vendedor: <?php echo h($lead['vendor_name']); ?></span>
                <?php endif; ?>
            </h1>
            <p><?php echo $isEmbed ? 'Registrar interacción desde el chat' : 'Registrar interacción'; ?> · <?php echo h($lead['city'] !== '' ? $lead['city'] : 'Sin ciudad'); ?> · Lead #<?php echo intval($leadId); ?> · Origen: <?php echo h($tablaOrigen); ?><?php echo $isEmbed ? '' : (' · Paquete: ' . h($lead['package_name'])); ?></p>
        </div>
    </header>

    <section class="li-panel">
        <h3>Nueva interacción</h3>

        <?php if ($saveSuccess || $showSaved): ?>
            <div class="li-alert">Interacción guardada correctamente.</div>
        <?php endif; ?>
        <?php if ($saveError !== ''): ?>
            <div class="li-alert error"><?php echo h($saveError); ?></div>
        <?php endif; ?>

        <form method="post">
            <?php if ($isEmbed): ?>
                <input type="hidden" name="embed" value="1">
            <?php endif; ?>
            <div class="li-label">Tipo de interacción</div>
            <div class="li-pills" style="margin-bottom:10px;">
                <?php
                $currentType = trim((string) ($_POST['interaction_type'] ?? 'Sin respuesta'));
                if ($currentType === '') {
                    $currentType = 'Sin respuesta';
                }
                ?>
                <?php foreach (['Llamada','WhatsApp','Email','Reunión','Nota interna','Sin respuesta'] as $type): ?>
                    <label class="li-pill interaction-pill">
                        <input type="radio" name="interaction_type" value="<?php echo h($type); ?>" <?php echo $currentType === $type ? 'checked' : ''; ?>>
                        <span><i class="li-emoji" aria-hidden="true"><?php echo h(interactionTypeEmoji($type)); ?></i><?php echo h($type); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="li-note" style="margin-bottom:10px;color:#6b7280;">La fecha y hora de interacción se guardan automáticamente al registrar.</div>

            <div style="margin-bottom:10px;">
                <div class="li-label">Notas de la interacción</div>
                <textarea class="li-textarea" name="notes"><?php echo h($_POST['notes'] ?? ''); ?></textarea>
            </div>

            <div style="margin-bottom:10px;">
                <div class="li-label">Resultado</div>
                <div class="li-pills">
                    <?php
                    $currentOutcome = trim((string) ($_POST['outcome'] ?? 'Sin respuesta'));
                    if ($currentOutcome === '') {
                        $currentOutcome = 'Sin respuesta';
                    }
                    ?>
                    <?php foreach (['Positivo','Neutral','Negativo','Listo para cerrar','Requiere seguimiento','Sin respuesta'] as $outcome): ?>
                        <?php $outcomeClassName = outcomeClass($outcome); ?>
                        <label class="li-pill outcome-pill">
                            <input type="radio" name="outcome" value="<?php echo h($outcome); ?>" <?php echo $currentOutcome === $outcome ? 'checked' : ''; ?>>
                            <span class="<?php echo h($outcomeClassName); ?>"><i class="li-emoji" aria-hidden="true"><?php echo h(outcomeIcon($outcome)); ?></i><?php echo h($outcome); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="li-grid">
                <div>
                    <div class="li-label">Próxima acción programada</div>
                    <input class="li-input" type="text" name="next_action" value="<?php echo h($_POST['next_action'] ?? ''); ?>" placeholder="Llamar para confirmar decisión de cierre">
                </div>
                <div>
                    <div class="li-label">Fecha próxima acción</div>
                    <input class="li-input" type="date" name="next_action_date" value="<?php echo h($_POST['next_action_date'] ?? ''); ?>">
                </div>
            </div>

            <div class="li-actions">
                <?php if ($isEmbed): ?>
                    <button type="button" class="li-btn" id="liEmbedCancelBtn">Cancelar</button>
                <?php else: ?>
                    <a class="li-btn" href="my_lead_board.php">Cancelar</a>
                <?php endif; ?>
                <button type="submit" class="li-btn primary">Guardar interacción</button>
            </div>
        </form>
    </section>

    <?php if (!$isEmbed): ?>
    <section class="li-panel">
        <h3>Historial de interacciones <span style="font-size:12px;color:#9ca3af;font-weight:600;"><?php echo count($history); ?> registros</span></h3>

        <?php if (empty($history)): ?>
            <div class="li-note" style="color:#6b7280;">Sin interacciones registradas todavía.</div>
        <?php else: ?>
            <?php foreach ($history as $item): ?>
                <?php
                $itemType = trim((string) ($item['interaction_type'] ?? ''));
                if ($itemType === '') {
                    $itemType = 'Sin respuesta';
                }
                $itemOutcome = trim((string) ($item['outcome'] ?? ''));
                if ($itemOutcome === '') {
                    $itemOutcome = 'Sin respuesta';
                }
                $itemTypeClass = interactionTypeClass($itemType);
                $itemOutcomeClass = outcomeClass($itemOutcome);
                $itemCreatedByName = tracerResolveUsuarioNombre($conn, $item['created_by'] ?? 0) ?? '';
                ?>
                <article class="li-history-item">
                    <div class="li-history-top">
                        <span class="li-badge <?php echo h($itemTypeClass); ?>"><i class="li-emoji" aria-hidden="true"><?php echo h(interactionTypeEmoji($itemType)); ?></i><?php echo h($itemType); ?></span>
                        <span class="li-outcome <?php echo h($itemOutcomeClass); ?>"><i class="li-emoji" aria-hidden="true"><?php echo h(outcomeIcon($itemOutcome)); ?></i><?php echo h($itemOutcome); ?></span>
                    </div>
                    <div class="li-note"><?php echo nl2br(h($item['notes'] ?? '')); ?></div>
                    <div class="li-meta">
                        <?php echo h(($item['interaction_date'] ?? '') . ' ' . ($item['interaction_time'] ?? '')); ?>
                        <?php if ($itemCreatedByName !== ''): ?>
                            · Registrado por: <?php echo h($itemCreatedByName); ?>
                        <?php endif; ?>
                        <?php if (!empty($item['next_action'])): ?>
                            · Próxima acción: <?php echo h($item['next_action']); ?>
                            <?php if (!empty($item['next_action_date'])): ?>
                                (<?php echo h($item['next_action_date']); ?>)
                            <?php endif; ?>
                            <?php if (intval($item['next_action_completed'] ?? 0) === 1): ?>
                                <span class="li-completed-badge">✓ Completada</span>
                            <?php else: ?>
                                <button type="button" class="li-btn li-btn-complete" data-interaction-id="<?php echo intval($item['id']); ?>">Marcar completada</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>

<?php if (!$isEmbed): ?>
</div>
<?php endif; ?>

<script>
<?php if ($isEmbed): ?>
(function () {
    function notifyParent(type) {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: type }, window.location.origin);
        }
    }

    var cancelBtn = document.getElementById('liEmbedCancelBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            notifyParent('pltrace_interaction_close');
        });
    }

    <?php if ($saveSuccess || $showSaved): ?>
    notifyParent('pltrace_interaction_saved');
    <?php endif; ?>
})();
<?php endif; ?>

document.addEventListener('click', function (e) {
    var btn = e.target.closest('.li-btn-complete');
    if (!btn) return;
    var id = parseInt(btn.dataset.interactionId, 10);
    if (!id) return;
    btn.disabled = true;
    btn.textContent = 'Guardando…';
    fetch('complete_interaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.ok) {
            var meta = btn.closest('.li-meta');
            btn.remove();
            var badge = document.createElement('span');
            badge.className = 'li-completed-badge';
            badge.textContent = '✓ Completada';
            meta.appendChild(badge);
        } else {
            btn.disabled = false;
            btn.textContent = 'Marcar completada';
            alert('No se pudo marcar como completada.');
        }
    })
    .catch(function () {
        btn.disabled = false;
        btn.textContent = 'Marcar completada';
        alert('Error de red.');
    });
});
</script>

</body>
</html>
