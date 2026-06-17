<?php
include 'menu.php';
include 'conn.php';

function ensureColumnExists($conn, $table, $columnName, $columnDef)
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($columnName);
    $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safeTable}' AND COLUMN_NAME = '{$safeColumn}'";
    $res = $conn->query($checkSql);
    if ($res) {
        $row = $res->fetch_assoc();
        if (intval($row['c'] ?? 0) === 0) {
            $conn->query("ALTER TABLE `{$table}` ADD COLUMN {$columnDef}");
        }
    }
}

function normalizeEventoStatusMeta($rawStatus)
{
    $normalized = trim((string) $rawStatus);
    if ($normalized === '1' || strcasecmp($normalized, 'atendido') === 0) {
        return ['key' => 'atendido', 'class' => 'status-atendido', 'label' => 'Atendido'];
    }
    if ($normalized === '2' || strcasecmp($normalized, 'cliente') === 0) {
        return ['key' => 'cliente', 'class' => 'status-cliente', 'label' => 'Cliente'];
    }
    if ($normalized === '3' || strcasecmp($normalized, 'muerto') === 0) {
        return ['key' => 'muerto', 'class' => 'status-muerto', 'label' => 'Muerto'];
    }

    return ['key' => 'pendiente', 'class' => 'status-pendiente', 'label' => 'Pendiente'];
}

function formatEventoDateDisplay($rawValue)
{
    $normalized = trim((string) $rawValue);
    if ($normalized === '') {
        return '';
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return $normalized;
    }

    return date('d/m/Y H:i', $timestamp);
}

function formatEventoDateInputValue($rawValue)
{
    $normalized = trim((string) $rawValue);
    if ($normalized === '') {
        return '';
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp);
}

// Get wedding planners for select
$wps = [];
ensureColumnExists($conn, 'wedding_planners', 'empresa_wp', "`empresa_wp` VARCHAR(255) DEFAULT NULL AFTER `where_is_your_marriage_taking_place_`");
ensureColumnExists($conn, 'wedding_planners', 'afianzado', "`afianzado` TINYINT(1) NOT NULL DEFAULT 0");
ensureColumnExists($conn, 'eventos_wp', 'estatus', "`estatus` VARCHAR(20) DEFAULT NULL AFTER `created_time`");
ensureColumnExists($conn, 'eventos_wp', 'fecha_actualizacion_estatus', "`fecha_actualizacion_estatus` DATETIME DEFAULT NULL AFTER `estatus`");
$conn->query("UPDATE wedding_planners SET empresa_wp = full_name WHERE (empresa_wp IS NULL OR TRIM(empresa_wp) = '') AND full_name IS NOT NULL AND TRIM(full_name) <> ''");

$resWp = $conn->query("SELECT id, campaign_name, full_name, empresa_wp FROM wedding_planners ORDER BY COALESCE(NULLIF(TRIM(empresa_wp), ''), NULLIF(TRIM(full_name), ''), NULLIF(TRIM(campaign_name), ''), id)");
if ($resWp) {
    while ($r = $resWp->fetch_assoc()) $wps[] = $r;
}

// Get agents for select
$agentes = [];
$resA = $conn->query("SELECT id, nombre, apepat FROM usuarios WHERE tipoUsu = 1 ORDER BY nombre, apepat");
if ($resA) {
    while ($ra = $resA->fetch_assoc()) $agentes[] = $ra;
}

// Get packages for select
$paquetes = [];
$resPaq = $conn->query("SELECT id, nombre FROM paquetes ORDER BY nombre");
if ($resPaq) {
    while ($rp = $resPaq->fetch_assoc()) $paquetes[] = $rp;
}

// Fetch events
$events = [];
$sql = "SELECT e.*, COALESCE(NULLIF(TRIM(wp.empresa_wp), ''), NULLIF(TRIM(wp.full_name), ''), NULLIF(TRIM(wp.campaign_name), '')) AS wp_nombre, wp.afianzado,
              u.nombre AS asesor_nombre, u.apepat AS asesor_apepat,
           c.nombre AS coordinador_nombre, p.nombre AS paquete_nombre,
           e.tipo_paquete, e.id_paquete, e.paquete_personalizado
        FROM eventos_wp e
        LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id
        LEFT JOIN coordinadores_wp c ON c.id = e.id_coordinador
        LEFT JOIN paquetes p ON p.id = e.id_paquete
        LEFT JOIN usuarios u ON u.id = e.id_asesor
        ORDER BY e.created_time DESC";
$resE = $conn->query($sql);
if ($resE) {
    while ($row = $resE->fetch_assoc()) $events[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Eventos WP</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .reportes-dashboard {
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
            --emerald-600: #059669;
            --emerald-700: #047857;
            --sky-600: #0284c7;
            --sky-700: #0369a1;
            --blue-600: #2563eb;
            --white: #ffffff;
        }

        .reportes-dashboard * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .reportes-dashboard {
            background-color: var(--slate-50);
            color: var(--slate-900);
            line-height: 1.5;
            min-height: 100vh;
        }

        .reportes-dashboard .card {
            border-radius: 1rem;
            background-color: var(--white);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .rd-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 9999px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background-color: #8d774a;
            color: var(--white);
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
        }

        .reportes-dashboard .rd-btn i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .reportes-dashboard .rd-btn:hover {
            background-color: #7b6840;
        }

        .reportes-dashboard .rd-btn-primary {
            background-color: #464646;
            color: var(--white);
        }

        .reportes-dashboard .rd-btn-primary:hover {
            background-color: #2f2f2f;
        }

        .reportes-dashboard .btn,
        .reportes-dashboard .btn-sm {
            border-radius: 9999px;
            font-weight: 600;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: background-color 0.2s, border-color 0.2s, color 0.2s;
            /* Ajuste: más relleno interno para que el texto no quede pegado */
            padding: 0.35rem 0.75rem;
        }

        /* Relleno levemente menor para botones pequeños, igualando aspecto de consulta_wp */
        .reportes-dashboard .btn-sm {
            padding: 0.25rem 0.6rem;
        }

        /* Separación entre botones dentro de celdas de acciones */
        .reportes-dashboard td [class*="btn"] {
            margin-right: 0.5rem;
        }

        .reportes-dashboard .btn-success {
            background-color: var(--emerald-600);
            border-color: var(--emerald-600);
            color: var(--white);
        }

        .reportes-dashboard .btn-success:hover {
            background-color: var(--emerald-700);
            border-color: var(--emerald-700);
            color: var(--white);
        }

        .reportes-dashboard .table-responsive,
        .reportes-dashboard .overflow-x-auto {
            border-radius: 1rem;
        }

        .reportes-dashboard .custom-scroll {
            scrollbar-width: thin;
            scrollbar-color: var(--slate-300) transparent;
        }

        .reportes-dashboard .custom-scroll::-webkit-scrollbar {
            height: 6px;
        }

        .reportes-dashboard .custom-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .reportes-dashboard .custom-scroll::-webkit-scrollbar-thumb {
            background-color: var(--slate-300);
            border-radius: 20px;
        }

        .reportes-dashboard table.table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
            font-size: 0.875rem;
            color: var(--slate-900);
        }

        .reportes-dashboard table.table thead th {
            background-color: var(--slate-50);
            color: var(--slate-600);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--slate-200);
            padding: 0.75rem 1rem;
            white-space: nowrap;
        }

        .reportes-dashboard table.table tbody td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--slate-100);
            vertical-align: middle;
        }

        .reportes-dashboard table.table tbody tr:hover {
            background-color: var(--slate-50);
        }

        .reportes-dashboard table.table-striped tbody tr:nth-of-type(odd) {
            background-color: #fbfdff;
        }

        .reportes-dashboard table.table-striped tbody tr:nth-of-type(odd):hover {
            background-color: var(--slate-50);
        }

        .reportes-dashboard .card-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--slate-200);
        }

        .reportes-dashboard .card-title {
            font-weight: 600;
            color: var(--slate-900);
            font-size: 0.95rem;
        }

        .reportes-dashboard .card-subtitle {
            margin-top: 0.25rem;
            color: var(--slate-500);
            font-size: 0.75rem;
        }

        .reportes-dashboard .card-body {
            padding: 1.25rem;
        }

        .reportes-dashboard .page-section {
            padding: 1.5rem 0;
        }

        .rlm-modal .modal-content {
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
            border: none;
        }

        .rlm-modal .modal-header {
            background: #eee8dc;
            border-bottom: none;
            padding: 22px 28px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .rlm-modal .modal-header h5 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #464646;
            margin: 0 0 4px;
        }

        .rlm-modal .modal-header .rlm-subtitle {
            font-size: 0.8rem;
            color: #464646;
            opacity: 0.72;
            margin: 0;
        }

        .rlm-modal .modal-body {
            padding: 28px 32px;
            overflow-y: auto;
            max-height: 60vh;
        }

        .rlm-section {
            margin-bottom: 28px;
        }

        .rlm-section-title {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
        }

        .rlm-section-number {
            width: 32px;
            height: 32px;
            background: #464646;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .rlm-section-heading h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1rem;
            color: #464646;
        }

        .rlm-section-heading p {
            margin: 2px 0 0;
            font-size: 0.8rem;
            color: #777;
        }

        .rlm-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .rlm-field {
            margin-bottom: 14px;
        }

        .rlm-field label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 4px;
            display: block;
        }

        .rlm-tag {
            display: inline-block;
            background: #464646;
            color: #eee8dc;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 4px;
            margin-left: 6px;
        }

        .rlm-optional {
            font-size: 0.7rem;
            color: #999;
            margin-left: 6px;
        }

        .rlm-field input[type="text"],
        .rlm-field input[type="datetime-local"],
        .rlm-field select,
        .rlm-field textarea {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 0.9rem;
            color: #333;
            outline: none;
            transition: border-color 0.2s;
            background: #fff;
        }

        .rlm-field input:focus,
        .rlm-field select:focus,
        .rlm-field textarea:focus {
            border-color: #464646;
        }

        .rlm-field .form-text {
            font-size: 0.76rem;
            color: #777;
            margin-top: 6px;
        }

        .rlm-choice-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .rlm-choice-btn {
            flex: 1;
            min-width: 180px;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            padding: 14px 12px;
            background: #fff;
            text-align: left;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .rlm-choice-btn:hover {
            border-color: #464646;
        }

        .rlm-choice-btn.active {
            border-color: #464646;
            background: #464646;
            color: #eee8dc;
        }

        .rlm-choice-btn strong {
            display: block;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }

        .rlm-choice-btn span {
            display: block;
            font-size: 0.76rem;
            color: inherit;
            opacity: 0.82;
        }

        .rlm-package-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .rlm-package-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            padding: 13px 16px;
            cursor: pointer;
            transition: all 0.15s ease;
            background: #fff;
        }

        .rlm-package-item:hover {
            border-color: #464646;
        }

        .rlm-package-item.active {
            border-color: #464646;
            background: #464646;
            box-shadow: 0 10px 24px rgba(70, 70, 70, 0.18);
        }

        .rlm-package-name {
            text-align: left;
            font-weight: 700;
            font-size: 0.9rem;
            color: #222;
        }

        .rlm-package-label {
            text-align: left;
            font-size: 0.78rem;
            color: #888;
        }

        .rlm-package-item.active .rlm-package-name,
        .rlm-package-item.active .rlm-package-label {
            color: #eee8dc;
        }

        .rlm-package-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #ccc;
            background: #fff;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .rlm-package-item.active .rlm-package-dot {
            border-color: #eee8dc;
            background: #eee8dc;
        }

        .rlm-package-item.active .rlm-package-dot::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #464646;
        }

        .rlm-modal .modal-footer {
            display: flex;
            gap: 12px;
            padding: 20px 32px 28px;
            border-top: 1px solid #eee;
            background: #fff;
        }

        .rlm-modal .modal-footer .rlm-btn-cancel {
            flex: 1;
            padding: 12px;
            border: 1.5px solid #ddd;
            border-radius: 10px;
            background: #fff;
            color: #555;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.15s;
        }

        .rlm-modal .modal-footer .rlm-btn-cancel:hover {
            background: #f5f5f5;
        }

        .rlm-modal .modal-footer .rlm-btn-submit {
            flex: 3;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: #eee8dc;
            color: #464646;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.15s;
        }

        .rlm-modal .modal-footer .rlm-btn-submit:hover {
            background: #464646;
            color: #eee8dc;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 88px;
            padding: 0.35rem 0.8rem;
            border-radius: 9999px;
            font-size: 0.78rem;
            font-weight: 700;
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .status-pill.status-atendido {
            background: #ecfdf5;
            color: #047857;
        }

        .status-pill.status-cliente {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .status-pill.status-muerto {
            background: #fef2f2;
            color: #b91c1c;
        }

        .status-pill.status-pendiente {
            background: #f8fafc;
            color: #64748b;
        }

        .status-modal-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 8px;
        }

        .status-modal-btn {
            border: 1px solid #ddd;
            border-radius: 12px;
            background: #fff;
            padding: 18px 16px;
            text-align: center;
            font-weight: 700;
            color: #464646;
            transition: all 0.18s ease;
        }

        .status-modal-btn:hover {
            border-color: #464646;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }

        .status-modal-btn.status-atendido-btn:hover {
            background: #ecfdf5;
            color: #047857;
        }

        .status-modal-btn.status-muerto-btn:hover {
            background: #fef2f2;
            color: #b91c1c;
        }

        .editable-date-cell {
            cursor: pointer;
            color: #7b6840;
            font-weight: 600;
            text-decoration: underline;
            text-decoration-style: dotted;
            text-underline-offset: 3px;
        }

        .editable-date-cell:hover {
            color: #464646;
        }

        .editable-date-cell.is-empty {
            color: #94a3b8;
        }

        @media (max-width: 600px) {
            .rlm-modal .modal-body {
                padding: 20px 16px;
            }

            .rlm-modal .modal-footer {
                padding: 16px;
            }

            .rlm-modal .modal-header {
                padding: 18px 16px;
            }

            .rlm-grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="reportes-dashboard">
    <div class="page-section">
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Eventos (WP)</div>
                    <div class="card-subtitle">Registro y seguimiento de eventos</div>
                </div>
                <button class="rd-btn rd-btn-primary" data-bs-toggle="modal" data-bs-target="#registroEventoModal">
                    <i class="bi bi-calendar-plus"></i> Registrar evento
                </button>
            </div>
            <div class="card-body">
                <div class="overflow-x-auto custom-scroll">
                    <table class="table table-striped" id="eventsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha de registro</th>
                                <th>Wedding Planner</th>
                                <th>Afianzado</th>
                                <th>Estatus</th>
                                <th>Fecha actualización</th>
                                <th>Coordinador</th>
                                <th>Lugar del evento</th>
                                <th>Fecha del evento</th>
                                <th>Novios</th>
                                <th>Asesor</th>
                                <th>Paquete</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td><?php echo intval($ev['id']); ?></td>
                                    <td>
                                        <span
                                            class="editable-date-cell js-edit-event-date <?php echo trim((string)($ev['fecha_registro'] ?? '')) === '' ? 'is-empty' : ''; ?>"
                                            data-event-id="<?php echo intval($ev['id']); ?>"
                                            data-date-field="fecha_registro"
                                            data-date-label="Fecha de registro"
                                            data-date-raw="<?php echo htmlspecialchars((string)($ev['fecha_registro'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-date-input="<?php echo htmlspecialchars(formatEventoDateInputValue($ev['fecha_registro'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        ><?php echo htmlspecialchars(formatEventoDateDisplay($ev['fecha_registro'] ?? '') ?: 'Sin fecha'); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ev['wp_nombre'] ?? ''); ?></td>
                                    <td><?php echo intval($ev['afianzado'] ?? 0) === 1 ? 'Sí' : 'No'; ?></td>
                                    <td>
                                        <?php $estatusEventoMeta = normalizeEventoStatusMeta($ev['estatus'] ?? ''); ?>
                                        <span class="status-pill <?php echo $estatusEventoMeta['class']; ?>">
                                            <?php echo $estatusEventoMeta['label']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            class="editable-date-cell js-edit-event-date <?php echo trim((string)($ev['fecha_actualizacion_estatus'] ?? '')) === '' ? 'is-empty' : ''; ?>"
                                            data-event-id="<?php echo intval($ev['id']); ?>"
                                            data-date-field="fecha_actualizacion_estatus"
                                            data-date-label="Fecha de actualización"
                                            data-date-raw="<?php echo htmlspecialchars((string)($ev['fecha_actualizacion_estatus'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-date-input="<?php echo htmlspecialchars(formatEventoDateInputValue($ev['fecha_actualizacion_estatus'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        ><?php echo htmlspecialchars(formatEventoDateDisplay($ev['fecha_actualizacion_estatus'] ?? '') ?: 'Sin fecha'); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ev['coordinador_nombre'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($ev['lugar'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($ev['fecha'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($ev['novios'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars((($ev['asesor_nombre'] ?? '') . ' ' . ($ev['asesor_apepat'] ?? ''))); ?></td>
                                    <td><?php echo htmlspecialchars(($ev['tipo_paquete'] ?? '') === 'estandar' ? ($ev['paquete_nombre'] ?? '') : ($ev['paquete_personalizado'] ?? '')); ?></td>
                                    <td style="white-space:nowrap;">
                                        <?php if ($estatusEventoMeta['key'] === 'cliente'): ?>
                                            <span class="status-pill status-cliente">Cliente</span>
                                        <?php else: ?>
                                            <button class="btn btn-sm <?php echo $estatusEventoMeta['key'] === 'atendido' ? 'btn-success' : 'btn-warning'; ?>" title="<?php echo $estatusEventoMeta['key'] === 'atendido' ? 'Pasar a cliente' : 'Cambiar estatus'; ?>" onclick='openStatusEventoModal(<?php echo json_encode($ev); ?>)'><?php echo $estatusEventoMeta['key'] === 'atendido' ? 'Pasar a cliente' : 'Cambiar de estatus'; ?></button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-primary" title="Editar evento" onclick='openEditEvento(<?php echo json_encode($ev); ?>)'>Editar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Registrar Evento -->
<div class="modal fade rlm-modal" id="registroEventoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Registrar Evento</h5>
                    <p class="rlm-subtitle">Captura la información del evento con el mismo lenguaje visual del registro manual.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEventoWP">
                    <div class="rlm-section">
                        <div class="rlm-section-title">
                            <div class="rlm-section-number">1</div>
                            <div class="rlm-section-heading">
                                <h5>Datos principales</h5>
                                <p>Relaciona el evento con el wedding planner, coordinador y asesor responsable.</p>
                            </div>
                        </div>

                        <div class="rlm-field">
                            <label for="evtWpId">Wedding Planner <span class="rlm-tag">Requerido</span></label>
                            <select id="evtWpId" name="wedding_planner_id" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($wps as $wp): ?>
                                    <?php $wpLabel = trim((string)($wp['empresa_wp'] ?? '')) !== '' ? trim((string)$wp['empresa_wp']) : (trim((string)($wp['full_name'] ?? '')) !== '' ? trim((string)$wp['full_name']) : trim((string)($wp['campaign_name'] ?? ''))); ?>
                                    <option value="<?php echo intval($wp['id']); ?>"><?php echo htmlspecialchars($wpLabel); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Si el Wedding Planner que busca no aparece en la lista, deberá registrarlo previamente.</div>
                        </div>

                        <div class="rlm-field">
                            <label for="evtCoordinador">Coordinador <span class="rlm-tag">Requerido</span></label>
                            <select id="evtCoordinador" name="id_coordinador" required>
                                <option value="">Seleccione un Wedding Planner primero...</option>
                            </select>
                            <div class="form-text">Si el coordinador que busca no aparece en la lista, deberá registrarlo y asignarlo previamente al Wedding Planner correspondiente.</div>
                        </div>

                        <div class="rlm-grid-2">
                            <div class="rlm-field">
                                <label for="evtNovios">Nombre de los novios <span class="rlm-tag">Requerido</span></label>
                                <input type="text" id="evtNovios" name="novios" required>
                            </div>
                            <div class="rlm-field">
                                <label for="evtAsesor">Asesor <span class="rlm-tag">Requerido</span></label>
                                <select id="evtAsesor" name="id_asesor" required>
                                    <option value="">Seleccionar asesor</option>
                                    <?php foreach ($agentes as $ag): ?>
                                        <option value="<?php echo intval($ag['id']); ?>"><?php echo htmlspecialchars($ag['nombre'] . ' ' . $ag['apepat']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="rlm-section">
                        <div class="rlm-section-title">
                            <div class="rlm-section-number">2</div>
                            <div class="rlm-section-heading">
                                <h5>Fecha y lugar</h5>
                                <p>Captura la sede y la fecha programada del evento.</p>
                            </div>
                        </div>

                        <div class="rlm-grid-2">
                            <div class="rlm-field">
                                <label for="evtLugar">Lugar del evento <span class="rlm-optional">(opcional)</span></label>
                                <input type="text" id="evtLugar" name="lugar">
                            </div>
                            <div class="rlm-field">
                                <label for="evtFecha">Fecha del evento <span class="rlm-optional">(opcional)</span></label>
                                <input type="datetime-local" id="evtFecha" name="fecha">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" style="display:none;">
                        <label class="form-label">Modo de registro <span class="text-danger">*</span></label>
                        <select class="form-select" id="evtModo" name="modo">
                            <option value="asistencia_post_q" selected>Asistencia Post-Q</option>
                            <option value="intencion_compra_pre_q">Intención de compra (Pre-Q)</option>
                        </select>
                    </div>
                    <input type="hidden" id="evtId" name="id" value="0">
                    <input type="hidden" id="evtFechaRegistro" name="fecha_registro">

                    <div id="evtPaqueteWrapper" class="rlm-section" style="margin-bottom:0;">
                        <div class="rlm-section-title">
                            <div class="rlm-section-number">3</div>
                            <div class="rlm-section-heading">
                                <h5>Paquete</h5>
                                <p>Selecciona uno de los paquetes disponibles en el catalogo actual.</p>
                            </div>
                        </div>

                        <input type="hidden" id="evtTipoPaquete" name="tipo_paquete" value="estandar">
                        <div class="rlm-field" id="evtPaqueteEstandarWrap">
                            <label>Paquete</label>
                            <input type="hidden" id="evtPaqueteId" name="id_paquete" value="">
                            <div class="rlm-package-list" id="evtPaqueteList">
                                <?php foreach ($paquetes as $pq): ?>
                                    <button type="button" class="rlm-package-item" data-paq-id="<?php echo intval($pq['id']); ?>">
                                        <div>
                                            <div class="rlm-package-name"><?php echo htmlspecialchars($pq['nombre']); ?></div>
                                            <div class="rlm-package-label">Paquete disponible en el catálogo actual</div>
                                        </div>
                                        <div class="rlm-package-dot"></div>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="rlm-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="rlm-btn-submit" id="btnGuardarEventoWP">Guardar evento</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade rlm-modal" id="estatusEventoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Cambiar estatus</h5>
                    <p class="rlm-subtitle">Selecciona el nuevo estatus del evento.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="estatusEventoId" value="0">
                <div class="rlm-section" style="margin-bottom:0;">
                    <div class="rlm-section-title">
                        <div class="rlm-section-number">1</div>
                        <div class="rlm-section-heading">
                            <h5>Estatus del evento</h5>
                            <p>Este cambio se guardará inmediatamente sobre el registro seleccionado.</p>
                        </div>
                    </div>
                    <div class="status-modal-actions">
                        <button type="button" class="status-modal-btn status-atendido-btn" data-status="atendido">Atendido</button>
                        <button type="button" class="status-modal-btn status-muerto-btn" data-status="muerto">Muerto</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="rlm-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade rlm-modal" id="comentarioEventoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="comentarioEventoModalTitle">Agregar comentario</h5>
                    <p class="rlm-subtitle" id="comentarioEventoModalSubtitle">Escribe el comentario antes de guardar el cambio de estatus.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="comentarioEventoId" value="0">
                <input type="hidden" id="comentarioEventoStatus" value="">
                <input type="hidden" id="comentarioEventoReturnToStatus" value="0">
                <div class="rlm-section" style="margin-bottom:0;">
                    <div class="rlm-section-title">
                        <div class="rlm-section-number">2</div>
                        <div class="rlm-section-heading">
                            <h5 id="comentarioEventoSectionTitle">Comentario del cambio</h5>
                            <p id="comentarioEventoSectionSubtitle">Este comentario se guardará en el evento seleccionado.</p>
                        </div>
                    </div>
                    <div class="rlm-field" style="margin-bottom:0;">
                        <label for="comentarioEventoTexto" id="comentarioEventoLabel">Comentario <span class="rlm-tag">Requerido</span></label>
                        <textarea id="comentarioEventoTexto" rows="5" placeholder="Escribe aquí el comentario..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="rlm-btn-cancel" id="comentarioEventoBackBtn">Volver</button>
                <button type="button" class="rlm-btn-submit" id="comentarioEventoSaveBtn">Guardar cambio</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade rlm-modal" id="editarFechaEventoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="editarFechaEventoModalTitle">Editar fecha</h5>
                    <p class="rlm-subtitle">Actualiza la fecha seleccionada sin recargar la vista.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editarFechaEventoId" value="0">
                <input type="hidden" id="editarFechaEventoCampo" value="">
                <div class="rlm-section" style="margin-bottom:0;">
                    <div class="rlm-section-title">
                        <div class="rlm-section-number">1</div>
                        <div class="rlm-section-heading">
                            <h5 id="editarFechaEventoSectionTitle">Nueva fecha</h5>
                            <p id="editarFechaEventoSectionSubtitle">Selecciona la nueva fecha y hora.</p>
                        </div>
                    </div>
                    <div class="rlm-field" style="margin-bottom:0;">
                        <label for="editarFechaEventoInput" id="editarFechaEventoLabel">Fecha <span class="rlm-tag">Requerido</span></label>
                        <input type="datetime-local" id="editarFechaEventoInput" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="rlm-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="rlm-btn-submit" id="guardarFechaEventoBtn">Actualizar fecha</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/extensions/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function () {
        var activeDateCell = null;

        $('#eventsTable').DataTable({
            paging: false,
            info: false,
            lengthChange: false
        });

        function formatDateForTableDisplay(rawValue) {
            if (!rawValue) return 'Sin fecha';

            var normalized = rawValue.toString().replace(' ', 'T');
            var date = new Date(normalized);
            if (isNaN(date.getTime())) return rawValue;

            var day = String(date.getDate()).padStart(2, '0');
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var year = date.getFullYear();
            var hours = String(date.getHours()).padStart(2, '0');
            var minutes = String(date.getMinutes()).padStart(2, '0');

            return day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
        }

        function setFechaRegistroNow() {
            var now = new Date();
            var local = new Date(now.getTime() - (now.getTimezoneOffset() * 60000));
            $('#evtFechaRegistro').val(local.toISOString().slice(0, 16));
        }

        function cargarCoordinadores(wpId, selectedCoordId) {
            var $select = $('#evtCoordinador');
            $select.empty().append('<option value="">Cargando...</option>');

            if (!wpId) {
                $select.empty().append('<option value="">Seleccione un Wedding Planner primero...</option>');
                return;
            }

            $.ajax({
                url: 'obtener_coordinadores_wp.php',
                type: 'GET',
                dataType: 'json',
                data: { id_wp: wpId },
                success: function (resp) {
                    $select.empty();
                    if (resp && resp.success && resp.coordinadores && resp.coordinadores.length) {
                        $select.append('<option value="">Seleccionar...</option>');
                        resp.coordinadores.forEach(function (c) {
                            var label = (c.nombre || '').trim();
                            if (!label) label = 'Coordinador #' + (c.id || '');
                            $select.append('<option value="' + c.id + '">' + label + '</option>');
                        });
                        if (selectedCoordId) $select.val(selectedCoordId.toString());
                    } else {
                        $select.append('<option value="">No hay coordinadores disponibles</option>');
                    }
                },
                error: function () {
                    $select.empty().append('<option value="">Error al cargar coordinadores</option>');
                }
            });
        }

        $('#evtWpId').on('change', function () {
            cargarCoordinadores(($(this).val() || '').trim());
        });

        function setEvtTipoPaquete() {
            $('#evtTipoPaquete').val('estandar');
        }

        $(document).on('click', '#evtPaqueteList .rlm-package-item', function () {
            $('#evtPaqueteList .rlm-package-item').removeClass('active');
            $(this).addClass('active');
            $('#evtPaqueteId').val($(this).data('paq-id') || '');
        });

        $('#registroEventoModal').on('shown.bs.modal', function () {
            setFechaRegistroNow();
        });

        // Open modal for creating new event
        $('[data-bs-target="#registroEventoModal"]').on('click', function () {
            $('#evtId').val('0');
            $('#formEventoWP')[0].reset();
            $('#evtPaqueteId').val('');
            $('#evtPaqueteList .rlm-package-item').removeClass('active');
            setEvtTipoPaquete();
        });

        window.openStatusEventoModal = function (ev) {
            if (!ev || !ev.id) return;

            var currentStatus = (ev.estatus || '').toString().trim().toLowerCase();
            if (currentStatus === '1' || currentStatus === 'atendido') {
                openComentarioEventoModal(ev.id, 'cliente', {
                    returnToStatus: false,
                    comentario: (ev.comentario_a_cliente || '').toString().trim()
                });
                return;
            }

            $('#estatusEventoId').val(ev.id || 0);
            var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('estatusEventoModal'));
            modal.show();
        };

        function configureComentarioEventoModal(status, returnToStatus) {
            var isClientStatus = status === 'cliente';

            $('#comentarioEventoModalTitle').text(isClientStatus ? 'Pasar a cliente' : 'Agregar comentario');
            $('#comentarioEventoModalSubtitle').text(isClientStatus
                ? 'Escribe el comentario antes de pasar el evento a cliente.'
                : 'Escribe el comentario antes de guardar el cambio de estatus.');
            $('#comentarioEventoSectionTitle').text(isClientStatus ? 'Comentario al pasar a cliente' : 'Comentario del cambio');
            $('#comentarioEventoSectionSubtitle').text(isClientStatus
                ? 'Este comentario se guardará como comentario al pasar a cliente.'
                : 'Este comentario se guardará en el evento seleccionado.');
            $('#comentarioEventoLabel').html((isClientStatus ? 'Comentario para cliente' : 'Comentario') + ' <span class="rlm-tag">Requerido</span>');
            $('#comentarioEventoTexto').attr('placeholder', isClientStatus ? 'Escribe aquí el comentario al pasar a cliente...' : 'Escribe aquí el comentario...');
            $('#comentarioEventoSaveBtn').text(isClientStatus ? 'Pasar a cliente' : 'Guardar cambio');
            $('#comentarioEventoBackBtn').text(returnToStatus ? 'Volver' : 'Cancelar');
        }

        function openComentarioEventoModal(eventId, status, options) {
            options = options || {};
            $('#comentarioEventoId').val(eventId || 0);
            $('#comentarioEventoStatus').val(status || '');
            $('#comentarioEventoReturnToStatus').val(options.returnToStatus ? '1' : '0');
            $('#comentarioEventoTexto').val((options.comentario || '').toString().trim());
            configureComentarioEventoModal(status, options.returnToStatus === true);

            var statusModalEl = document.getElementById('estatusEventoModal');
            var statusModal = bootstrap.Modal.getInstance(statusModalEl);
            var commentModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('comentarioEventoModal'));

            if (statusModal) {
                $(statusModalEl).one('hidden.bs.modal', function () {
                    commentModal.show();
                });
                statusModal.hide();
                return;
            }

            commentModal.show();
        }

        function reopenStatusEventoModal() {
            var commentModalEl = document.getElementById('comentarioEventoModal');
            var commentModal = bootstrap.Modal.getInstance(commentModalEl);
            var statusModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('estatusEventoModal'));

            if (commentModal) {
                $(commentModalEl).one('hidden.bs.modal', function () {
                    statusModal.show();
                });
                commentModal.hide();
                return;
            }

            statusModal.show();
        }

        function actualizarEstatusEvento(eventId, status, comentario) {
            if (!eventId || !status) {
                Swal.fire('Error', 'No se pudo identificar el evento o el estatus.', 'error');
                return;
            }

            if ((status === 'atendido' || status === 'muerto' || status === 'cliente') && !comentario) {
                Swal.fire('Error', 'Debes agregar un comentario antes de continuar.', 'warning');
                return;
            }

            $('.status-modal-btn').prop('disabled', true);

            Swal.fire({
                title: 'Actualizando...',
                text: 'Espera mientras se guarda el cambio de estatus.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'actualizar_estatus_evento_wp.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: eventId,
                    estatus: status,
                    comentario: status === 'cliente' ? '' : comentario,
                    comentario_a_cliente: status === 'cliente' ? comentario : ''
                },
                success: function (resp) {
                    Swal.close();
                    if (resp && resp.success) {
                        var modal = bootstrap.Modal.getInstance(document.getElementById('estatusEventoModal'));
                        if (modal) modal.hide();
                        Swal.fire({ icon: 'success', title: 'Actualizado', text: resp.message || 'El estatus del evento se actualizó correctamente.' }).then(function () {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo actualizar el estatus', 'error');
                    }
                },
                error: function () {
                    Swal.close();
                    Swal.fire('Error', 'Ocurrió un error al actualizar el estatus', 'error');
                },
                complete: function () {
                    $('.status-modal-btn').prop('disabled', false);
                }
            });
        }

        $(document).off('click.eventoStatus').on('click.eventoStatus', '.status-modal-btn[data-status]', function () {
            var status = ($(this).data('status') || '').toString().trim();
            var eventId = parseInt($('#estatusEventoId').val() || '0', 10);
            openComentarioEventoModal(eventId, status, {
                returnToStatus: true,
                comentario: ''
            });
        });

        $('#comentarioEventoBackBtn').on('click', function () {
            if (($('#comentarioEventoReturnToStatus').val() || '') === '1') {
                reopenStatusEventoModal();
                return;
            }

            var commentModal = bootstrap.Modal.getInstance(document.getElementById('comentarioEventoModal'));
            if (commentModal) commentModal.hide();
        });

        $('#comentarioEventoSaveBtn').on('click', function () {
            var eventId = parseInt($('#comentarioEventoId').val() || '0', 10);
            var status = ($('#comentarioEventoStatus').val() || '').toString().trim();
            var comentario = ($('#comentarioEventoTexto').val() || '').toString().trim();

            if (!comentario) {
                Swal.fire('Error', 'Debes agregar un comentario antes de continuar.', 'warning');
                return;
            }

            var commentModal = bootstrap.Modal.getInstance(document.getElementById('comentarioEventoModal'));
            if (commentModal) commentModal.hide();

            actualizarEstatusEvento(eventId, status, comentario);
        });

        $(document).on('click', '.js-edit-event-date', function () {
            activeDateCell = $(this);

            $('#editarFechaEventoId').val(activeDateCell.data('event-id') || 0);
            $('#editarFechaEventoCampo').val(activeDateCell.data('date-field') || '');
            $('#editarFechaEventoInput').val((activeDateCell.data('date-input') || '').toString());

            var label = (activeDateCell.data('date-label') || 'Fecha').toString();
            $('#editarFechaEventoModalTitle').text('Editar ' + label.toLowerCase());
            $('#editarFechaEventoSectionTitle').text(label);
            $('#editarFechaEventoLabel').html(label + ' <span class="rlm-tag">Requerido</span>');

            bootstrap.Modal.getOrCreateInstance(document.getElementById('editarFechaEventoModal')).show();
        });

        $('#guardarFechaEventoBtn').on('click', function () {
            var eventId = parseInt($('#editarFechaEventoId').val() || '0', 10);
            var field = ($('#editarFechaEventoCampo').val() || '').toString().trim();
            var newValue = ($('#editarFechaEventoInput').val() || '').toString().trim();
            var $btn = $(this);

            if (!eventId || !field) {
                Swal.fire('Error', 'No se pudo identificar el evento o el campo a actualizar.', 'error');
                return;
            }

            if (!newValue) {
                Swal.fire('Error', 'Selecciona una fecha válida.', 'warning');
                return;
            }

            $btn.prop('disabled', true).text('Actualizando...');

            $.ajax({
                url: 'actualizar_fecha_evento_wp.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: eventId,
                    field: field,
                    value: newValue
                },
                success: function (resp) {
                    if (resp && resp.success) {
                        if (activeDateCell && activeDateCell.length) {
                            activeDateCell
                                .text(resp.display_value || formatDateForTableDisplay(resp.raw_value || newValue))
                                .attr('data-date-raw', resp.raw_value || newValue)
                                .attr('data-date-input', resp.input_value || newValue)
                                .data('date-raw', resp.raw_value || newValue)
                                .data('date-input', resp.input_value || newValue)
                                .removeClass('is-empty');
                        }

                        var modal = bootstrap.Modal.getInstance(document.getElementById('editarFechaEventoModal'));
                        if (modal) modal.hide();

                        Swal.fire({
                            icon: 'success',
                            title: 'Fecha actualizada',
                            text: resp.message || 'La fecha se actualizó correctamente.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo actualizar la fecha.', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Ocurrió un error al actualizar la fecha.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Actualizar fecha');
                }
            });
        });

        window.openEditEvento = function (ev) {
            if (!ev || !ev.id) return;
            // Prefill fields
            $('#evtId').val(ev.id || 0);
            $('#evtWpId').val(ev.wedding_planner_id || '');
            // load coordinators and select current
            cargarCoordinadores(ev.wedding_planner_id || 0, ev.id_coordinador || 0);
            $('#evtLugar').val(ev.lugar || '');
            if (ev.fecha) {
                var d = new Date(ev.fecha);
                if (!isNaN(d.getTime())) {
                    var local = new Date(d.getTime() - (d.getTimezoneOffset() * 60000));
                    $('#evtFecha').val(local.toISOString().slice(0,16));
                }
            }
            if (ev.fecha_registro) {
                var dr = new Date(ev.fecha_registro);
                if (!isNaN(dr.getTime())) {
                    var localr = new Date(dr.getTime() - (dr.getTimezoneOffset() * 60000));
                    $('#evtFechaRegistro').val(localr.toISOString().slice(0,16));
                }
            }
            $('#evtNovios').val(ev.novios || '');
            $('#evtAsesor').val(ev.id_asesor || '');
            $('#evtModo').val(ev.modo || 'asistencia_post_q');

            // Fill package controls
            $('#evtPaqueteList .rlm-package-item').removeClass('active');
            setEvtTipoPaquete();
            $('#evtPaqueteId').val(ev.id_paquete || '');
            $('#evtPaqueteList .rlm-package-item[data-paq-id="' + (ev.id_paquete || '') + '"]').addClass('active');

            var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('registroEventoModal'));
            modal.show();
        };

        $('#btnGuardarEventoWP').on('click', function () {
            setFechaRegistroNow();
            var wpId = ($('#evtWpId').val() || '').trim();
            var novios = ($('#evtNovios').val() || '').trim();
            var asesor = ($('#evtAsesor').val() || '').trim();
            var modo = ($('#evtModo').val() || 'asistencia_post_q').trim();
            var coordinador = ($('#evtCoordinador').val() || '').trim();
            var fechaRegistro = ($('#evtFechaRegistro').val() || '').trim();
            var paqueteId = ($('#evtPaqueteId').val() || '').trim();
            var tipoPaquete = 'estandar';

            if (!wpId) { Swal.fire('Error', 'Seleccione un Wedding Planner', 'warning'); return; }
            if (!coordinador) { Swal.fire('Error', 'Seleccione un coordinador', 'warning'); return; }
            if (!novios) { Swal.fire('Error', 'Ingrese el nombre de los novios', 'warning'); return; }
            if (!asesor) { Swal.fire('Error', 'Seleccione un asesor', 'warning'); return; }
            if (!fechaRegistro) { Swal.fire('Error', 'Seleccione la fecha de registro', 'warning'); return; }
            if (!paqueteId) { Swal.fire('Error', 'Seleccione un paquete', 'warning'); return; }
            paqueteId = paqueteId || 0;
            $('#evtTipoPaquete').val(tipoPaquete);
            $('#evtPaqueteId').val(paqueteId);

            var data = {
                id: ($('#evtId').val() || '').trim(),
                wedding_planner_id: wpId,
                id_coordinador: coordinador,
                lugar: $('#evtLugar').val(),
                fecha: $('#evtFecha').val(),
                fecha_registro: fechaRegistro,
                novios: novios,
                id_asesor: asesor,
                modo: modo,
                tipo_paquete: tipoPaquete,
                id_paquete: paqueteId,
                paquete_personalizado: ''
            };

            var $btn = $(this);
            $btn.prop('disabled', true).text('Guardando...');

            $.ajax({
                url: 'guardar_evento_wp.php',
                type: 'POST',
                dataType: 'json',
                data: data,
                success: function (resp) {
                    if (resp && resp.success) {
                        $('#registroEventoModal').modal('hide');
                        Swal.fire({ icon: 'success', title: 'Guardado', text: 'Evento registrado' }).then(function () { location.reload(); });
                    } else {
                        Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo guardar', 'error');
                    }
                },
                error: function (xhr) { console.error(xhr); Swal.fire('Error', 'Ocurrió un error', 'error'); },
                complete: function () { $btn.prop('disabled', false).text('Guardar evento'); }
            });
        });
    });
</script>
</body>
</html>