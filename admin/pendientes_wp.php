<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

ensureColumnExists($conn, 'wedding_planners', 'empresa_wp', "`empresa_wp` VARCHAR(255) DEFAULT NULL AFTER `where_is_your_marriage_taking_place_`");
ensureColumnExists($conn, 'coordinadores_wp', 'planner_reason', "`planner_reason` VARCHAR(255) DEFAULT NULL AFTER `correo`");
ensureColumnExists($conn, 'coordinadores_wp', 'how_long_known_us', "`how_long_known_us` VARCHAR(150) DEFAULT NULL AFTER `planner_reason`");
ensureColumnExists($conn, 'coordinadores_wp', 'first_contact_channel', "`first_contact_channel` VARCHAR(255) DEFAULT NULL AFTER `how_long_known_us`");

$conn->query("UPDATE wedding_planners SET empresa_wp = full_name WHERE (empresa_wp IS NULL OR TRIM(empresa_wp) = '') AND full_name IS NOT NULL AND TRIM(full_name) <> ''");

function formatCreatedTime($dateString)
{
    if (empty($dateString)) {
        return '';
    }
    $timestamp = strtotime($dateString);
    if ($timestamp === false) {
        return $dateString;
    }

    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $month = $months[date('n', $timestamp) - 1];
    $day = date('j', $timestamp);
    $year = date('Y', $timestamp);
    $hour = date('g', $timestamp);
    $min = date('i', $timestamp);
    $ampm = date('A', $timestamp) === 'AM' ? 'a.m.' : 'p.m.';

    return "$day de $month de $year a las $hour:$min $ampm";
}

function getWeddingPlannerDisplayName(array $lead)
{
    $empresa = trim((string)($lead['empresa_wp'] ?? ''));
    if ($empresa !== '') {
        return $empresa;
    }
    $fullName = trim((string)($lead['full_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }
    $campaign = trim((string)($lead['campaign_name'] ?? ''));
    if ($campaign !== '') {
        return $campaign;
    }
    return 'WP #' . intval($lead['id'] ?? 0);
}

$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

$whereParts = ['estatus = 0'];

if ($startDate !== '') {
    $safeStartDate = $conn->real_escape_string(date('Y-m-d', strtotime($startDate)));
    $whereParts[] = "DATE(created_time) >= '{$safeStartDate}'";
}

if ($endDate !== '') {
    $safeEndDate = $conn->real_escape_string(date('Y-m-d', strtotime($endDate)));
    $whereParts[] = "DATE(created_time) <= '{$safeEndDate}'";
}

if ($searchQuery !== '') {
    $search = $conn->real_escape_string(mb_strtolower($searchQuery, 'UTF-8'));
    $whereParts[] = "(
        LOWER(CONCAT_WS(' ', IFNULL(empresa_wp, ''), IFNULL(full_name, ''), IFNULL(campaign_name, ''))) LIKE '%{$search}%'
        OR EXISTS (
            SELECT 1 FROM coordinadores_wp c
            WHERE c.id_wp = wedding_planners.id
              AND LOWER(CONCAT_WS(' ', IFNULL(c.nombre, ''), IFNULL(c.telefono, ''), IFNULL(c.correo, ''), IFNULL(c.planner_reason, ''), IFNULL(c.how_long_known_us, ''), IFNULL(c.first_contact_channel, ''))) LIKE '%{$search}%'
        )
    )";
}

$whereSql = implode(' AND ', $whereParts);

$filteredLeads = [];
$sqlLeads = "SELECT *, 'wedding_planners' AS tabla_origen, COALESCE(NULLIF(TRIM(empresa_wp), ''), NULLIF(TRIM(full_name), ''), NULLIF(TRIM(campaign_name), ''), CONCAT('WP #', id)) AS wp_display_name FROM wedding_planners WHERE {$whereSql} ORDER BY created_time DESC, id DESC";
$resLeads = $conn->query($sqlLeads);
if ($resLeads && $resLeads->num_rows > 0) {
    while ($lead = $resLeads->fetch_assoc()) {
        $filteredLeads[] = $lead;
    }
}

$coordinatorCounts = [];
$wpIds = [];
foreach ($filteredLeads as $lead) {
    $idWp = isset($lead['id']) ? intval($lead['id']) : 0;
    if ($idWp > 0) {
        $wpIds[] = $idWp;
    }
}

$wpIds = array_values(array_unique($wpIds));
if (!empty($wpIds)) {
    $idsList = implode(',', array_map('intval', $wpIds));
    $resCnt = $conn->query("SELECT id_wp, COUNT(*) AS cnt FROM coordinadores_wp WHERE id_wp IN ({$idsList}) GROUP BY id_wp");
    if ($resCnt) {
        while ($row = $resCnt->fetch_assoc()) {
            $coordinatorCounts[intval($row['id_wp'])] = intval($row['cnt']);
        }
    }
}

$weddingPlannersActivos = [];
$resWp = $conn->query("SELECT id, campaign_name, full_name, empresa_wp FROM wedding_planners WHERE estatus = 1 ORDER BY COALESCE(NULLIF(TRIM(empresa_wp), ''), NULLIF(TRIM(full_name), ''), NULLIF(TRIM(campaign_name), ''), id), id");
if ($resWp && $resWp->num_rows > 0) {
    while ($rowWp = $resWp->fetch_assoc()) {
        $weddingPlannersActivos[] = $rowWp;
    }
}

$totalPendientes = count($filteredLeads);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendientes Wedding Planners</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .reportes-dashboard {
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-500: #64748b;
            --slate-600: #475569;
            --slate-900: #0f172a;
            --white: #ffffff;
        }

        .reportes-dashboard {
            background-color: var(--slate-50);
            color: var(--slate-900);
            min-height: 100vh;
            line-height: 1.5;
        }

        .reportes-dashboard .card {
            border-radius: 1rem;
            background-color: var(--white);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .reportes-dashboard .border-b {
            border-bottom: 1px solid var(--slate-200);
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
            color: #fff;
            cursor: pointer;
            text-decoration: none;
        }

        .reportes-dashboard .rd-btn:hover {
            background-color: #7b6840;
        }

        .reportes-dashboard .rd-btn-primary {
            background-color: #464646;
            color: #fff;
        }

        .reportes-dashboard .rd-btn-primary:hover {
            background-color: #2f2f2f;
        }

        .reportes-dashboard .search-input,
        .reportes-dashboard .custom-input {
            border-radius: 9999px;
            background-color: var(--slate-50);
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            color: var(--slate-900);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .reportes-dashboard .search-input {
            width: 20rem;
            max-width: 100%;
        }

        .reportes-dashboard .btn,
        .reportes-dashboard .btn-sm {
            border-radius: 9999px;
            font-weight: 600;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: background-color 0.2s, border-color 0.2s, color 0.2s;
        }

        .reportes-dashboard .btn-success {
            background-color: #198754;
            border-color: #198754;
            color: #ffffff;
        }

        .reportes-dashboard .btn-success:hover {
            background-color: #157347;
            border-color: #157347;
            color: #ffffff;
        }

        .reportes-dashboard .btn-danger {
            background-color: #dc2626;
            border-color: #dc2626;
            color: #ffffff;
        }

        .reportes-dashboard .btn-danger:hover {
            background-color: #b91c1c;
            border-color: #b91c1c;
            color: #ffffff;
        }

        .reportes-dashboard table.table .btn,
        .reportes-dashboard table.table .btn-sm,
        .reportes-dashboard table.table button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 9999px;
            padding: 0.4rem 0.9rem;
            font-weight: 600;
            font-size: 0.8125rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background-color: var(--slate-50);
            color: var(--slate-900);
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s, color 0.2s;
        }

        .reportes-dashboard table.table .btn:hover,
        .reportes-dashboard table.table .btn-sm:hover,
        .reportes-dashboard table.table button:hover {
            background-color: var(--slate-100);
        }

        .reportes-dashboard table.table .btn-success,
        .reportes-dashboard table.table .btn-success:hover {
            background-color: #198754;
            border-color: #198754;
            color: #ffffff;
        }

        .reportes-dashboard table.table .btn-danger,
        .reportes-dashboard table.table .btn-danger:hover {
            background-color: #dc2626;
            border-color: #dc2626;
            color: #ffffff;
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

        .origen-col {
            width: 90px;
            min-width: 70px;
            text-align: center;
            font-size: 0.85rem;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #6c757d;
        }

        .table-actions {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }

        @media (max-width: 767px) {
            .reportes-dashboard .search-input {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="reportes-dashboard">
        <div class="py-4 px-3 px-md-4">
            <div class="card mb-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 px-4 py-3 border-b">
                    <div>
                        <div class="fw-semibold">Wedding Planners Pendientes</div>
                        <div class="text-muted small">Gestión de registros con estatus pendiente (0)</div>
                    </div>
                    <div class="text-muted small">Total pendientes: <span class="fw-semibold text-dark"><?php echo intval($totalPendientes); ?></span></div>
                </div>
                <div class="px-4 py-3">
                    <form method="get" class="d-flex flex-wrap align-items-center gap-2" id="filterForm">
                        <input type="date" name="start_date" class="custom-input" value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="date" name="end_date" class="custom-input" value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="text" name="search" class="search-input" placeholder="Buscar por nombre, correo o teléfono" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="rd-btn rd-btn-primary">Filtrar</button>
                        <a href="<?php echo htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES, 'UTF-8'); ?>" class="rd-btn">Limpiar</a>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-b">
                    <div>
                        <div class="fw-semibold">Listado de pendientes</div>
                        <div class="text-muted small">Acciones: Pasar a Post, Descartar y Ver Coordinador</div>
                    </div>
                </div>
                <div class="p-4">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="pendientesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre del Wedding Planner</th>
                                    <th class="text-center">Coordinadores</th>
                                    <th>Fecha de registro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($filteredLeads)): ?>
                                    <?php foreach ($filteredLeads as $lead): ?>
                                        <?php
                                        $wpId = intval($lead['id'] ?? 0);
                                        $rowId = 'lead-row-' . $wpId;
                                        $campaignName = $lead['campaign_name'] ?? '';
                                        ?>
                                        <tr id="<?php echo htmlspecialchars($rowId, ENT_QUOTES, 'UTF-8'); ?>">
                                            <td><?php echo htmlspecialchars($lead['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(getWeddingPlannerDisplayName($lead), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-secondary"
                                                    onclick='verMas(<?php echo json_encode([
                                                        'id' => $wpId,
                                                        'campaign_name' => $lead['campaign_name'] ?? '',
                                                        'full_name' => $lead['full_name'] ?? '',
                                                        'empresa_wp' => $lead['empresa_wp'] ?? '',
                                                        'wp_display_name' => $lead['wp_display_name'] ?? ''
                                                    ], JSON_UNESCAPED_UNICODE); ?>)'>
                                                    <?php echo intval($coordinatorCounts[$wpId] ?? 0); ?>
                                                </button>
                                            </td>
                                            <td><?php echo htmlspecialchars(formatCreatedTime($lead['created_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <div class="table-actions">
                                                    <button class="mb-2 btn btn-info btn-sm" style="background-color: #5e543f; color: white; border: 1px solid #5e543f; margin-right: 5px;" onclick='verMas(<?php echo json_encode([
                                                        'id' => $wpId,
                                                        'campaign_name' => $lead['campaign_name'] ?? '',
                                                        'full_name' => $lead['full_name'] ?? '',
                                                        'empresa_wp' => $lead['empresa_wp'] ?? '',
                                                        'wp_display_name' => $lead['wp_display_name'] ?? ''
                                                    ], JSON_UNESCAPED_UNICODE); ?>)'>Ver Coordinador</button>
                                                    <button class="mb-2 btn btn-success btn-sm" style="margin-right:5px;" onclick="confirmStatusChange(<?php echo $wpId; ?>, 1)">Pasar a Post</button>
                                                    <button class="mb-2 btn btn-danger btn-sm" onclick="confirmStatusChange(<?php echo $wpId; ?>, 2)">Descartar</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No hay Wedding Planners pendientes con los filtros actuales.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="verMasModal" tabindex="-1" aria-labelledby="verMasModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verMasModalLabel">Coordinadores asignados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBody"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pasarCoordinadorModal" tabindex="-1" aria-labelledby="pasarCoordinadorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pasarCoordinadorModalLabel">Pasar coordinador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="pasarCoordinadorForm">
                        <input type="hidden" id="pasarCoordId" value="">
                        <input type="hidden" id="pasarCoordWpOrigen" value="">
                        <div class="mb-2">
                            <div class="small text-muted" id="pasarCoordNombre"></div>
                        </div>
                        <div class="mb-3">
                            <label for="pasarCoordWpDestino" class="form-label">¿A qué WP lo quieres pasar?</label>
                            <select id="pasarCoordWpDestino" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <?php if (!empty($weddingPlannersActivos)): ?>
                                    <?php foreach ($weddingPlannersActivos as $wp): ?>
                                        <?php
                                        $wpName = trim(($wp['empresa_wp'] ?? '') !== '' ? $wp['empresa_wp'] : (($wp['full_name'] ?? '') !== '' ? $wp['full_name'] : ($wp['campaign_name'] ?? '')));
                                        $wpLabel = $wpName !== '' ? $wpName : ('WP #' . intval($wp['id']));
                                        ?>
                                        <option value="<?php echo intval($wp['id']); ?>"><?php echo htmlspecialchars($wpLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmarPasarCoordinador">Pasar coordinador</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function adjustCoordinatorCount(wpId, delta) {
            var row = document.getElementById('lead-row-' + wpId);
            if (!row) {
                return;
            }
            var countButton = row.querySelector('td.text-center button');
            if (!countButton) {
                return;
            }
            var current = parseInt((countButton.textContent || '').trim(), 10);
            if (isNaN(current)) {
                return;
            }
            var next = current + delta;
            if (next < 0) {
                next = 0;
            }
            countButton.textContent = String(next);
        }

        function removeLeadRow(id) {
            var row = document.getElementById('lead-row-' + id);
            if (row) {
                row.remove();
            }
        }

        function updateStatus(id, estatus, doneCb) {
            $.ajax({
                url: 'actualizar_estatus_wedding_planner.php',
                method: 'POST',
                dataType: 'json',
                data: { id: id, estatus: estatus },
                success: function(resp) {
                    if (resp && resp.success) {
                        removeLeadRow(id);
                        if (typeof doneCb === 'function') {
                            doneCb();
                        }
                        Swal.fire({ icon: 'success', title: 'Actualizado', text: resp.message || 'Estatus actualizado' });
                    } else {
                        Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo actualizar el estatus', 'error');
                    }
                },
                error: function(xhr) {
                    var msg = 'Error al actualizar estatus';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    Swal.fire('Error', msg, 'error');
                }
            });
        }

        function confirmStatusChange(id, estatus) {
            var text = estatus === 1 ? 'Se enviará este Wedding Planner a Post.' : 'Se descartará este Wedding Planner.';
            Swal.fire({
                title: '¿Confirmar acción?',
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then(function(result) {
                if (result.isConfirmed) {
                    updateStatus(id, estatus);
                }
            });
        }

        function renderCoordinadoresList(coordinadores, idWp) {
            var actions = `
                <div class="mb-3 d-flex flex-wrap gap-2">
                    <button class="btn btn-danger btn-sm" onclick="confirmStatusChangeFromModal(${idWp}, 2)">Descartar</button>
                </div>
            `;

            if (!coordinadores || !coordinadores.length) {
                return actions + '<div class="text-muted">No hay coordinadores asignados.</div>';
            }

            var rows = coordinadores.map(function(c) {
                return `
                    <tr>
                        <td>${escapeHtml(c.nombre || 'N/A')}</td>
                        <td>${escapeHtml(c.telefono || 'N/A')}</td>
                        <td>${escapeHtml(c.correo || 'N/A')}</td>
                        <td>${escapeHtml(c.planner_reason || 'N/A')}</td>
                        <td>${escapeHtml(c.how_long_known_us || 'N/A')}</td>
                        <td>${escapeHtml(c.first_contact_channel || 'N/A')}</td>
                        <td>${escapeHtml(c.fecha_creacion || 'N/A')}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick='openPasarCoordinadorModal(${JSON.stringify(c)}, ${idWp})'>Pasar coordinador</button>
                        </td>
                    </tr>
                `;
            }).join('');

            return `
                ${actions}
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Teléfono</th>
                                <th>Correo</th>
                                <th>Motivo</th>
                                <th>Nos conoce desde</th>
                                <th>Canal</th>
                                <th>Fecha creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;
        }

        function openPasarCoordinadorModal(coordinador, wpOrigenId) {
            var coordId = coordinador && coordinador.id ? parseInt(coordinador.id, 10) : 0;
            if (!coordId || !wpOrigenId) {
                Swal.fire('Error', 'Coordinador o Wedding Planner inválido', 'error');
                return;
            }

            document.getElementById('pasarCoordId').value = String(coordId);
            document.getElementById('pasarCoordWpOrigen').value = String(parseInt(wpOrigenId, 10));
            document.getElementById('pasarCoordNombre').textContent = 'Coordinador: ' + (coordinador.nombre || ('#' + coordId));

            var select = document.getElementById('pasarCoordWpDestino');
            select.value = '';
            Array.prototype.forEach.call(select.options, function(opt) {
                if (!opt.value) {
                    opt.disabled = false;
                    return;
                }
                opt.disabled = parseInt(opt.value, 10) === parseInt(wpOrigenId, 10);
            });

            var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('pasarCoordinadorModal'));
            modal.show();
        }

        function submitPasarCoordinador() {
            var idCoordinador = parseInt(document.getElementById('pasarCoordId').value || '0', 10);
            var idWpOrigen = parseInt(document.getElementById('pasarCoordWpOrigen').value || '0', 10);
            var idWpDestino = parseInt(document.getElementById('pasarCoordWpDestino').value || '0', 10);

            if (!idCoordinador || !idWpOrigen || !idWpDestino) {
                Swal.fire('Aviso', 'Selecciona el Wedding Planner de destino', 'warning');
                return;
            }

            var $btn = $('#btnConfirmarPasarCoordinador');
            Swal.fire({
                title: '¿Confirmar cambio?',
                text: 'El coordinador se reasignará al Wedding Planner seleccionado.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, pasar coordinador',
                cancelButtonText: 'Cancelar'
            }).then(function(result) {
                if (!result.isConfirmed) {
                    return;
                }

                $btn.prop('disabled', true).text('Moviendo...');

                $.ajax({
                    url: 'pasar_coordinador_wp.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        id_coordinador: idCoordinador,
                        id_wp_origen: idWpOrigen,
                        id_wp_destino: idWpDestino
                    },
                    success: function(resp) {
                        if (resp && resp.success) {
                            var modalPasar = bootstrap.Modal.getInstance(document.getElementById('pasarCoordinadorModal'));
                            if (modalPasar) {
                                modalPasar.hide();
                            }

                            $.getJSON('obtener_coordinadores_wp.php', {
                                id_wp: idWpOrigen
                            }, function(listResp) {
                                if (listResp && listResp.success) {
                                    document.getElementById('modalBody').innerHTML = renderCoordinadoresList(listResp.coordinadores, idWpOrigen);
                                }
                            });

                            adjustCoordinatorCount(idWpOrigen, -1);

                            Swal.fire({
                                icon: 'success',
                                title: 'Coordinador movido',
                                text: resp.message || 'El coordinador fue asignado al nuevo Wedding Planner'
                            });
                        } else {
                            Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo mover el coordinador', 'error');
                        }
                    },
                    error: function(xhr) {
                        var msg = 'Error al mover coordinador';
                        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        Swal.fire('Error', msg, 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Pasar coordinador');
                    }
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            var btnPasar = document.getElementById('btnConfirmarPasarCoordinador');
            if (btnPasar) {
                btnPasar.addEventListener('click', submitPasarCoordinador);
            }
        });

        function confirmStatusChangeFromModal(idWp, estatus) {
            var text = estatus === 1 ? 'Se enviará este Wedding Planner a Post.' : 'Se descartará este Wedding Planner.';
            Swal.fire({
                title: '¿Confirmar acción?',
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then(function(result) {
                if (!result.isConfirmed) {
                    return;
                }

                updateStatus(idWp, estatus, function() {
                    var modalEl = document.getElementById('verMasModal');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) {
                        modal.hide();
                    }
                });
            });
        }

        function verMas(lead) {
            var wpId = lead && lead.id ? parseInt(lead.id, 10) : 0;
            var wpName = (lead && (lead.wp_display_name || lead.empresa_wp || lead.full_name || lead.campaign_name)) ? (lead.wp_display_name || lead.empresa_wp || lead.full_name || lead.campaign_name) : ('WP #' + wpId);

            document.getElementById('verMasModalLabel').textContent = 'Coordinadores asignados - ' + wpName;
            document.getElementById('modalBody').innerHTML = '<div class="text-muted">Cargando...</div>';

            var modal = new bootstrap.Modal(document.getElementById('verMasModal'));
            modal.show();

            if (!wpId) {
                document.getElementById('modalBody').innerHTML = '<div class="text-danger">Wedding Planner no válido.</div>';
                return;
            }

            $.ajax({
                url: 'obtener_coordinadores_wp.php',
                type: 'GET',
                dataType: 'json',
                data: { id_wp: wpId },
                success: function(resp) {
                    if (resp && resp.success) {
                        document.getElementById('modalBody').innerHTML = renderCoordinadoresList(resp.coordinadores, wpId);
                    } else {
                        document.getElementById('modalBody').innerHTML = '<div class="text-danger">No se pudo cargar la lista de coordinadores.</div>';
                    }
                },
                error: function() {
                    document.getElementById('modalBody').innerHTML = '<div class="text-danger">Error al consultar coordinadores.</div>';
                }
            });
        }
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>
