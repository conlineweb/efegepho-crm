<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

// (Removed formatCreatedTime — not used by the simplified table)

// Obtener todas las tablas de leads
$tablas = [];
$sqlTablas = "SELECT nombre FROM tablas_leads ORDER BY nombre";
$resultTablas = $conn->query($sqlTablas);

if ($resultTablas && $resultTablas->num_rows > 0) {
    while ($row = $resultTablas->fetch_assoc()) {
        $tablas[] = $row['nombre'];
    }
}

// Consultar todos los leads de todas las tablas
$allLeads = [];

foreach ($tablas as $tableName) {
    // Verificar que la tabla existe
    $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($checkTable->num_rows > 0) {
        // Verificar qué columnas existen en la tabla
        $columns = [];
        $columnsResult = $conn->query("SHOW COLUMNS FROM `$tableName`");
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }

        // Construir la consulta según las columnas disponibles ORDENANDOLO DEL MAS RECIENTE AL MAS ANTIGUIO
        $sqlLeads = "SELECT *, '$tableName' as tabla_origen FROM `$tableName` WHERE " .
            (in_array('descartado', $columns) ? "descartado = 0 OR descartado IS NULL" : "1=1") . " ORDER BY created_time DESC";

        $resultLeads = $conn->query($sqlLeads);
        if ($resultLeads && $resultLeads->num_rows > 0) {
            while ($lead = $resultLeads->fetch_assoc()) {
                $allLeads[] = $lead;
            }
        }
    }
}

// Nota: no cerrar la conexión aquí porque la usaremos más adelante
// $conn->close();

// Filter: only leads assigned to IA agents (call = 99, whatsapp = 100) and NOT scheduled in contact_form
$filteredLeads = [];
$leadIdsByTableAssigned = [];
$iaAgents = [99, 100];
foreach ($allLeads as $lead) {
    if (!isset($lead['usuario_asignado'])) continue;
    $ua = intval($lead['usuario_asignado']);
    if (!in_array($ua, $iaAgents, true)) continue;
    $t = isset($lead['tabla_origen']) ? $lead['tabla_origen'] : (isset($lead['form_name']) ? $lead['form_name'] : '');
    $id = isset($lead['id']) ? intval($lead['id']) : 0;
    if ($t === '' || $id <= 0) continue;
    if (!isset($leadIdsByTableAssigned[$t])) $leadIdsByTableAssigned[$t] = [];
    $leadIdsByTableAssigned[$t][] = $id;
} 

// Query contact_form to determine which assigned leads are already scheduled (original_lead_id present)
$scheduledMap = [];
foreach ($leadIdsByTableAssigned as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '') continue;
    $sql = "SELECT original_lead_id FROM contact_form WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') AND original_lead_id IN (" . $idsList . ")";
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $scheduledMap[$t . '|' . intval($r['original_lead_id'])] = true;
        }
    }
}

// Build final filtered array: assigned to IA and not scheduled
foreach ($allLeads as $lead) {
    if (!isset($lead['usuario_asignado'])) continue;
    $ua = intval($lead['usuario_asignado']);
    if (!in_array($ua, $iaAgents, true)) continue;
    $t = isset($lead['tabla_origen']) ? $lead['tabla_origen'] : (isset($lead['form_name']) ? $lead['form_name'] : '');
    $id = isset($lead['id']) ? intval($lead['id']) : 0;
    if ($t === '' || $id <= 0) continue;
    $key = $t . '|' . $id;
    if (isset($scheduledMap[$key])) continue; // skip scheduled
    $filteredLeads[] = $lead;
}

// Clasificar leads por agente (99 = llamada IA, 100 = whatsapp IA) y por idioma (en/es)
$leadsByAgent = [];
foreach ($filteredLeads as $lead) {
    $agentId = intval($lead['usuario_asignado'] ?? 0);
    $rawPhone = isset($lead['phone']) ? $lead['phone'] : '';
    $phoneClean = preg_replace('/^\s*p:\s*/i', '', trim($rawPhone));
    $lang = (stripos($phoneClean, '+52') !== false || preg_match('/^\+?52/', ltrim($phoneClean))) ? 'es' : 'en';
    if (!isset($leadsByAgent[$agentId])) {
        $leadsByAgent[$agentId] = ['en' => [], 'es' => []];
    }
    $leadsByAgent[$agentId][$lang][] = $lead;
}


// Read export log so we can show export status per lead
$exportLogFile = __DIR__ . '/export_logs/exported_leads.json';
$exportLog = [];
if (file_exists($exportLogFile)) {
    $raw = file_get_contents($exportLogFile);
    $exportLog = json_decode($raw, true) ?: [];
}

// helper to compute same lead key used by the exporter
function _lead_key_for_table($lead) {
    $tid = isset($lead['tabla_origen']) ? $lead['tabla_origen'] : (isset($lead['form_name']) ? $lead['form_name'] : 'unknown');
    $id = isset($lead['id']) ? $lead['id'] : '';
    return $tid . ':' . $id;
}

// (Keeping the original per-table SQL ordering; no global sorting requested)

// (Removed chart series/date-count code — this page now shows only the simplified leads table.)
?>
<?php
// (Removed vendedores query — assignment UI removed)
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar  pre-qualified leads</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
</head>
<style>
    /* modal removed — no extra top-level styles needed */

    table {
        width: 100% !important;
    }

    /* Ocultar el botón "Export new" superior; los botones por idioma permanecen */
    #exportNewBtn { display: none !important; }



    @media (max-width: 767px) {
        /* responsive tweaks for table */
    }
</style>
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="order-last order-md-1 col-12 col-md-12">
                <h3>Exportar  pre-qualified leads</h3>
                <br>
            </div>
            <div class="order-first order-md-2 col-12 col-md-12">

            </div>
        </div>
    </div>



    <div class="row mb-3">
        <!-- Mostrando solo la tabla (sin filtros, estadísticas ni gráficas) -->
        <div class="col-12">
            <p class="small text-muted">Solo se mostrarán los pre-qualified leads asignados a agentes IA (99 = llamada, 100 = whatsapp) que aún no hayan agendado.</p>
        </div>
    </div>

    <section class="section">
        <div class="card">

            <div class="card-body">
                <div class="d-flex justify-content-start mb-2 gap-2">
                    <a id="exportNewBtn" href="export_unexported_csv.php" class="btn btn-primary btn-sm">Export new (not exported yet)</a>
                </div>
                <!-- Tables separated by detected language: Español (+52) and Inglés (others) -->
                <p class="small text-muted">Nota: la clasificación se hace buscando '+52' o números que empiezan con '52' (con o sin '+') para Español; cualquier otro número se considera Inglés.</p>

                <!-- Tabs for IA agents: 99 = Agente llamada (IA), 100 = Agente whatsapp (IA) -->
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-agent-99" data-bs-toggle="tab" data-bs-target="#agent-99" type="button" role="tab" aria-controls="agent-99" aria-selected="true">Agente llamada (IA)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-agent-100" data-bs-toggle="tab" data-bs-target="#agent-100" type="button" role="tab" aria-controls="agent-100" aria-selected="false">Agente whatsapp (IA)</button>
                    </li>
                </ul>

                <div class="tab-content mt-3">
                    <!-- Agent 99: llamada -->
                    <div class="tab-pane fade show active" id="agent-99" role="tabpanel" aria-labelledby="tab-agent-99">

                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <div><h5>Inglés — <?php echo count($leadsByAgent[99]['en'] ?? []); ?> leads</h5></div>
                            <div class="btn-group">
                                <a href="export_unexported_csv.php?lang=en&agent=99" class="btn btn-primary btn-sm">Export new (English)</a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="leadsTableEn_99" class="table table-hover table-striped leadsTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Estatus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leadsByAgent[99]['en'] ?? [] as $lead): ?>
                                        <tr id="lead-row-en-99-<?php echo htmlspecialchars($lead['id'] ?? ''); ?>">
                                            <td><?php echo htmlspecialchars($lead['full_name'] ?? ''); ?></td>
                                            <?php
                                                $rawPhone = isset($lead['phone']) ? $lead['phone'] : '';
                                                $phoneClean = preg_replace('/^\s*p:\s*/i', '', trim($rawPhone));
                                            ?>
                                            <td><?php echo htmlspecialchars($phoneClean); ?></td>
                                            <td><?php echo htmlspecialchars($lead['email'] ?? ''); ?></td>
                                            <?php
                                                $key = _lead_key_for_table($lead);
                                                if (isset($exportLog[$key])) {
                                                    $entry = $exportLog[$key];
                                                    if (is_array($entry)) {
                                                        $ts = isset($entry['exported_at']) ? $entry['exported_at'] : json_encode($entry);
                                                    } else {
                                                        $ts = (string)$entry;
                                                    }
                                                    echo '<td class="text-success">Exportado';
                                                    if (!empty($ts)) echo ' (' . htmlspecialchars($ts) . ')';
                                                    echo '</td>';
                                                } else {
                                                    echo '<td class="text-danger">No exportado</td>';
                                                }
                                            ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 mb-3 d-flex justify-content-between align-items-center">
                            <div><h5>Español (+52) — <?php echo count($leadsByAgent[99]['es'] ?? []); ?> leads</h5></div>
                            <div class="btn-group">
                                <a href="export_unexported_csv.php?lang=es&agent=99" class="btn btn-primary btn-sm">Export new (Español)</a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="leadsTableEs_99" class="table table-hover table-striped leadsTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Estatus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leadsByAgent[99]['es'] ?? [] as $lead): ?>
                                        <tr id="lead-row-es-99-<?php echo htmlspecialchars($lead['id'] ?? ''); ?>">
                                            <td><?php echo htmlspecialchars($lead['full_name'] ?? ''); ?></td>
                                            <?php
                                                $rawPhone = isset($lead['phone']) ? $lead['phone'] : '';
                                                $phoneClean = preg_replace('/^\s*p:\s*/i', '', trim($rawPhone));
                                            ?>
                                            <td><?php echo htmlspecialchars($phoneClean); ?></td>
                                            <td><?php echo htmlspecialchars($lead['email'] ?? ''); ?></td>
                                            <?php
                                                $key = _lead_key_for_table($lead);
                                                if (isset($exportLog[$key])) {
                                                    $entry = $exportLog[$key];
                                                    if (is_array($entry)) {
                                                        $ts = isset($entry['exported_at']) ? $entry['exported_at'] : json_encode($entry);
                                                    } else {
                                                        $ts = (string)$entry;
                                                    }
                                                    echo '<td class="text-success">Exportado';
                                                    if (!empty($ts)) echo ' (' . htmlspecialchars($ts) . ')';
                                                    echo '</td>';
                                                } else {
                                                    echo '<td class="text-danger">No exportado</td>';
                                                }
                                            ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <!-- Agent 100: whatsapp -->
                    <div class="tab-pane fade" id="agent-100" role="tabpanel" aria-labelledby="tab-agent-100">

                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <div><h5>Inglés — <?php echo count($leadsByAgent[100]['en'] ?? []); ?> leads</h5></div>
                            <div class="btn-group">
                                <a href="export_unexported_csv.php?lang=en&agent=100" class="btn btn-primary btn-sm">Export new (English)</a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="leadsTableEn_100" class="table table-hover table-striped leadsTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Estatus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leadsByAgent[100]['en'] ?? [] as $lead): ?>
                                        <tr id="lead-row-en-100-<?php echo htmlspecialchars($lead['id'] ?? ''); ?>">
                                            <td><?php echo htmlspecialchars($lead['full_name'] ?? ''); ?></td>
                                            <?php
                                                $rawPhone = isset($lead['phone']) ? $lead['phone'] : '';
                                                $phoneClean = preg_replace('/^\s*p:\s*/i', '', trim($rawPhone));
                                            ?>
                                            <td><?php echo htmlspecialchars($phoneClean); ?></td>
                                            <td><?php echo htmlspecialchars($lead['email'] ?? ''); ?></td>
                                            <?php
                                                $key = _lead_key_for_table($lead);
                                                if (isset($exportLog[$key])) {
                                                    $entry = $exportLog[$key];
                                                    if (is_array($entry)) {
                                                        $ts = isset($entry['exported_at']) ? $entry['exported_at'] : json_encode($entry);
                                                    } else {
                                                        $ts = (string)$entry;
                                                    }
                                                    echo '<td class="text-success">Exportado';
                                                    if (!empty($ts)) echo ' (' . htmlspecialchars($ts) . ')';
                                                    echo '</td>';
                                                } else {
                                                    echo '<td class="text-danger">No exportado</td>';
                                                }
                                            ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 mb-3 d-flex justify-content-between align-items-center">
                            <div><h5>Español (+52) — <?php echo count($leadsByAgent[100]['es'] ?? []); ?> leads</h5></div>
                            <div class="btn-group">
                                <a href="export_unexported_csv.php?lang=es&agent=100" class="btn btn-primary btn-sm">Export new (Español)</a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="leadsTableEs_100" class="table table-hover table-striped leadsTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Estatus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leadsByAgent[100]['es'] ?? [] as $lead): ?>
                                        <tr id="lead-row-es-100-<?php echo htmlspecialchars($lead['id'] ?? ''); ?>">
                                            <td><?php echo htmlspecialchars($lead['full_name'] ?? ''); ?></td>
                                            <?php
                                                $rawPhone = isset($lead['phone']) ? $lead['phone'] : '';
                                                $phoneClean = preg_replace('/^\s*p:\s*/i', '', trim($rawPhone));
                                            ?>
                                            <td><?php echo htmlspecialchars($phoneClean); ?></td>
                                            <td><?php echo htmlspecialchars($lead['email'] ?? ''); ?></td>
                                            <?php
                                                $key = _lead_key_for_table($lead);
                                                if (isset($exportLog[$key])) {
                                                    $entry = $exportLog[$key];
                                                    if (is_array($entry)) {
                                                        $ts = isset($entry['exported_at']) ? $entry['exported_at'] : json_encode($entry);
                                                    } else {
                                                        $ts = (string)$entry;
                                                    }
                                                    echo '<td class="text-success">Exportado';
                                                    if (!empty($ts)) echo ' (' . htmlspecialchars($ts) . ')';
                                                    echo '</td>';
                                                } else {
                                                    echo '<td class="text-danger">No exportado</td>';
                                                }
                                            ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>


            </div>
        </div>
    </section>
</div>

<!-- modal removed (no per-row actions required in the simplified table) -->

<?php

include 'footer.php';

?>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>


<!-- Bootstrap JS y Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

<script>
    $(document).ready(function () {
        // Initialize DataTable for each language table while preserving server-side order
        $('table.leadsTable').each(function () {
            $(this).DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                order: [],
                paging: false,
                info: false,
                responsive: true,
                columnDefs: [
                    { orderable: true, targets: '_all' }
                ]
            });
        });

        // Page only shows the tables — Highcharts and other chart logic removed.
    });

    /* Removed unused user/email/assignment JS (no actions on simplified table) */

    // No per-row action functions left — page only initializes the DataTable.

    // Export now handled server-side via export_unexported_csv.php
</script>

</body>

</html>