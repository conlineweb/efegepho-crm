<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tipoUsuario = $_SESSION['tipo_usuario'] ?? 0;

include 'menu.php';
include 'conn.php';

// ======================== HELPER FUNCTIONS ========================
function normalizeFirstContactChannelLabel_vp($value)
{
    $normalized = trim((string) $value);
    if ($normalized === '') return 'Sin dato';

    $key = mb_strtolower($normalized, 'UTF-8');
    $key = str_replace(['–', '—'], '-', $key);
    $key = preg_replace('/\s+/', ' ', $key);

    $map = [
        'whatsapp' => 'WhatsApp',
        'instagram dm - campaign' => 'Instagram DM - Campaña',
        'instagram dm campaign' => 'Instagram DM - Campaña',
        'instagram dm - organic' => 'Instagram DM - Orgánico',
        'ig' => 'Instagram',
        'facebook' => 'Facebook',
        'email' => 'Correo electrónico',
        'correo electronico' => 'Correo electrónico',
        'correo electrónico' => 'Correo electrónico',
        'mail' => 'Correo electrónico',
        'phone call' => 'Llamada telefónica',
        'llamada telefonica' => 'Llamada telefónica',
        'llamada telefónica' => 'Llamada telefónica',
    ];
    return $map[$key] ?? $normalized;
}

function getHowDidYouMeetLabel($value)
{
    $raw = trim((string) $value);
    if ($raw === '') return '—';
    $howMap = [
        '1' => 'Wedding Planner',
        '2' => 'Community',
        '3' => 'New Market',
    ];
    // Solo mostrar valores categorizados (1/2/3); texto libre de formularios se ignora
    return $howMap[$raw] ?? 'Sin dato';
}

function getHearAboutUsLabel($value)
{
    $raw = trim((string) $value);
    if ($raw === '') return '—';
    $hearMap = [
        '1' => 'Meta Ads — anuncio en Instagram / Facebook',
        '2' => 'SEO — buscaron en Google',
        '3' => 'Colaboración / Influencer / Famoso',
        '4' => 'Publicación / Prensa / Revista',
        '5' => 'Otro',
    ];
    return $hearMap[$raw] ?? $raw;
}

// ======================== OBTENER TABLAS DE LEADS ========================
$tablas = [];
$sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo != 2 ORDER BY nombre";
$resultTablas = $conn->query($sqlTablas);
if ($resultTablas && $resultTablas->num_rows > 0) {
    while ($row = $resultTablas->fetch_assoc()) {
        $tablas[] = $row['nombre'];
    }
}

// ======================== OBTENER TODOS LOS LEADS DE tablas_leads ========================
$allPreLeads = [];
foreach ($tablas as $tableName) {
    $checkTable = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    if ($checkTable->num_rows === 0) continue;

    $columns = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tableName) . "`");
    while ($col = $columnsResult->fetch_assoc()) {
        $columns[] = $col['Field'];
    }

    $whereParts = [];
    if (in_array('descartado', $columns)) {
        $whereParts[] = "(descartado = 0 OR descartado IS NULL)";
    }

    $whereSql = !empty($whereParts) ? implode(' AND ', $whereParts) : '1=1';
    $orderCol = in_array('created_time', $columns) ? 'created_time' : (in_array('id', $columns) ? 'id' : null);
    $orderSql = $orderCol ? " ORDER BY `$orderCol` DESC" : '';
    $sqlLeads = "SELECT *, '" . $conn->real_escape_string($tableName) . "' as tabla_origen FROM `" . $conn->real_escape_string($tableName) . "` WHERE " . $whereSql . $orderSql;

    $resultLeads = $conn->query($sqlLeads);
    if ($resultLeads && $resultLeads->num_rows > 0) {
        while ($lead = $resultLeads->fetch_assoc()) {
            $allPreLeads[] = $lead;
        }
    }
}

// ======================== CRUZAR CON contact_form PARA OBTENER DATOS EXTRA Y FILTRAR CLIENTES ========================

// Construir mapa de leads por tabla
$leadIdsByTable = [];
foreach ($allPreLeads as $lead) {
    $t = $lead['tabla_origen'] ?? '';
    $id = intval($lead['id'] ?? 0);
    if ($t === '' || $id <= 0) continue;
    if (!isset($leadIdsByTable[$t])) $leadIdsByTable[$t] = [];
    $leadIdsByTable[$t][] = $id;
}

// Consultar contact_form para estos leads
$contactFormByLead = [];
foreach ($leadIdsByTable as $t => $ids) {
    $safeTable = $conn->real_escape_string($t);
    $idsList = implode(',', array_map('intval', $ids));
    if (trim($idsList) === '') continue;

    $sql = "SELECT id, original_lead_id, cliente, how_did_you_meet, hear_about_us, engagement, 
                   first_contact_channel, campaign_name, names
            FROM contact_form 
            WHERE LOWER(tabla_origen) = LOWER('" . $safeTable . "') 
            AND original_lead_id IN (" . $idsList . ")";
    $resCf = $conn->query($sql);
    if ($resCf) {
        while ($row = $resCf->fetch_assoc()) {
            $key = $t . '|' . intval($row['original_lead_id']);
            $contactFormByLead[$key] = $row;
        }
    }
}

// También obtener registros de contact_form que NO vienen de tablas_leads (registros directos)
// pero que no sean clientes
$directContactForms = [];
$sqlDirect = "SELECT * FROM contact_form ORDER BY submission_date DESC";
$resDirect = $conn->query($sqlDirect);
if ($resDirect && $resDirect->num_rows > 0) {
    while ($row = $resDirect->fetch_assoc()) {
        $directContactForms[intval($row['id'])] = $row;
    }
}

// ======================== OBTENER CITAS DE CALENDARIO PARA DETERMINAR ESTATUS ========================
$allCfIds = [];
foreach ($contactFormByLead as $cfData) {
    $allCfIds[] = intval($cfData['id']);
}
foreach ($directContactForms as $cfId => $cf) {
    $allCfIds[] = intval($cfId);
}
$allCfIds = array_unique(array_filter($allCfIds));

$appointmentsByCFId = [];
if (!empty($allCfIds)) {
    $cfList = implode(',', array_map('intval', $allCfIds));
    $apptRes = $conn->query("SELECT idclie, estatus FROM calendario WHERE idclie IN (" . $cfList . ")");
    if ($apptRes && $apptRes->num_rows > 0) {
        while ($ar = $apptRes->fetch_assoc()) {
            $appointmentsByCFId[intval($ar['idclie'])] = $ar['estatus'];
        }
    }
}

// Obtener mapa de cliente=1 de contact_form (para todos los CF IDs)
$clienteCfIds = [];
if (!empty($allCfIds)) {
    $cfListCliente = implode(',', array_map('intval', $allCfIds));
    $resCliente = $conn->query("SELECT id FROM contact_form WHERE id IN (" . $cfListCliente . ") AND cliente = 1");
    if ($resCliente && $resCliente->num_rows > 0) {
        while ($rc = $resCliente->fetch_assoc()) {
            $clienteCfIds[intval($rc['id'])] = true;
        }
    }
}

// ======================== CONSTRUIR LISTA FINAL (excluir clientes) ========================
$displayRows = [];
$seenCfIds = []; // Para evitar duplicados

foreach ($allPreLeads as $lead) {
    $t = $lead['tabla_origen'] ?? '';
    $lid = intval($lead['id'] ?? 0);
    $key = $t . '|' . $lid;

    $cfRow = $contactFormByLead[$key] ?? null;

    // Enriquecer lead con datos de contact_form
    $metodoContacto = '';
    $dondeConocen = '';
    $hearAboutUs = '';
    $campaignName = $lead['campaign_name'] ?? '';

    if ($cfRow) {
        $metodoContacto = $cfRow['first_contact_channel'] ?? '';
        $dondeConocen = $cfRow['how_did_you_meet'] ?? '';
        $hearAboutUs = $cfRow['hear_about_us'] ?? '';
        if (empty($campaignName)) $campaignName = $cfRow['campaign_name'] ?? '';
        $seenCfIds[] = intval($cfRow['id']);
    }

    // Usar datos del lead si contact_form no tiene
    if (empty($metodoContacto)) $metodoContacto = $lead['first_contact_channel'] ?? '';
    if (empty($dondeConocen)) $dondeConocen = $lead['how_did_you_meet'] ?? '';
    if (empty($hearAboutUs)) $hearAboutUs = $lead['hear_about_us'] ?? '';

    // Determinar estatus: Cliente > Agendado > Lead
    $estatus = 'Lead';
    if ($cfRow) {
        $cfId = intval($cfRow['id']);
        if (isset($clienteCfIds[$cfId]) || intval($cfRow['cliente'] ?? 0) === 1) {
            $estatus = 'Cliente';
        } elseif (isset($appointmentsByCFId[$cfId])) {
            $estatus = 'Agendado';
        }
    }

    $displayRows[] = [
        'nombre'           => $lead['full_name'] ?? ($lead['name'] ?? ''),
        'email'            => $lead['email'] ?? '',
        'metodo_contacto'  => $metodoContacto,
        'donde_conocen'    => $dondeConocen,
        'hear_about_us'    => $hearAboutUs,
        'campaign_name'    => $campaignName,
        'estatus'          => $estatus,
        'origen'           => 'Pre-Q (' . htmlspecialchars($t) . ')',
    ];
}

// Ahora agregar registros de contact_form que no fueron ya cruzados y no son clientes
foreach ($directContactForms as $cfId => $cf) {
    if (in_array($cfId, $seenCfIds)) continue;

    // Excluir wedding planners
    $tablaOrigen = strtolower(trim($cf['tabla_origen'] ?? ''));
    if ($tablaOrigen === 'wedding_planners' || $tablaOrigen === 'wedding_planner') continue;

    $displayRows[] = [
        'nombre'           => $cf['names'] ?? '',
        'email'            => $cf['email_address'] ?? ($cf['email'] ?? ''),
        'metodo_contacto'  => $cf['first_contact_channel'] ?? '',
        'donde_conocen'    => $cf['how_did_you_meet'] ?? '',
        'hear_about_us'    => $cf['hear_about_us'] ?? '',
        'campaign_name'    => $cf['campaign_name'] ?? '',
        'estatus'          => (isset($clienteCfIds[intval($cfId)]) || intval($cf['cliente'] ?? 0) === 1) ? 'Cliente' : (isset($appointmentsByCFId[intval($cfId)]) ? 'Agendado' : 'Lead'),
        'origen'           => 'Contact Form',
    ];
}

$totalRows = count($displayRows);
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista provisional — Leads completos</title>

    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        body { background: #F8FAFC !important; }
        .vp-wrapper { margin: 30px auto; padding: 0 20px; }
        .vp-header { margin-bottom: 24px; }
        .vp-header h2 { font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 22px; color: #1E293B; margin: 0 0 4px; }
        .vp-header p { color: #64748B; font-size: 14px; margin: 0; }
        .vp-count { display: inline-block; background: #3B82F6; color: #fff; font-size: 13px; font-weight: 500; padding: 3px 10px; border-radius: 20px; margin-left: 8px; vertical-align: middle; }
        .vp-table-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.06); padding: 20px; }
        .vp-table { width: 100% !important; font-family: 'DM Sans', sans-serif; font-size: 13px; }
        .vp-table thead th { background: #F1F5F9; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; padding: 10px 12px; border-bottom: 2px solid #E2E8F0; white-space: nowrap; }
        .vp-table tbody td { padding: 10px 12px; color: #334155; vertical-align: middle; border-bottom: 1px solid #F1F5F9; }
        .vp-table tbody tr:hover { background: #F8FAFC; }
        .vp-name { font-weight: 500; color: #1E293B; }
        .vp-email { font-size: 11px; color: #94A3B8; }
        .vp-badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 500; }
        .vp-badge-pre { background: #DBEAFE; color: #1D4ED8; }
        .vp-badge-cf { background: #F0FDF4; color: #16A34A; }
        .vp-badge-agendado { background: #FEF3C7; color: #B45309; }
        .vp-badge-cliente { background: #D1FAE5; color: #065F46; }
        .vp-level { display: inline-block; background: #6366F1; color: #fff; font-size: 9px; font-weight: 700; padding: 1px 6px; border-radius: 4px; vertical-align: middle; margin-left: 4px; letter-spacing: .3px; text-transform: uppercase; }
        .vp-na { color: #CBD5E1; }
    </style>
</head>

<body>
    <div class="">
        <div class="vp-header">
            <h2>Vista provisional de leads <span class="vp-count"><?php echo number_format($totalRows); ?> registros</span></h2>
            <p>Todos los leads (pre-Q + contact_form) que <strong>no</strong> son clientes. Campos: Método de contacto, De dónde nos conocen, Cómo llegaron (hear_about_us), Campaign name.</p>
        </div>

        <div class="vp-table-card">
            <table id="vpTable" class="vp-table table table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Método de contacto <span class="vp-level">Nivel 1</span></th>
                        <th>De dónde nos conocen <span class="vp-level">Nivel 2</span></th>
                        <th>Cómo llegaron (hear_about_us) <span class="vp-level">Nivel 3</span></th>
                        <th>Campaign name <span class="vp-level">Nivel 4</span></th>
                        <th>Estatus</th>
                        <th>Origen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($displayRows as $row): ?>
                        <tr>
                            <td>
                                <div class="vp-name"><?php echo htmlspecialchars($row['nombre'] ?: '—'); ?></div>
                                <?php if (!empty($row['email'])): ?>
                                    <div class="vp-email"><?php echo htmlspecialchars($row['email']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(normalizeFirstContactChannelLabel_vp($row['metodo_contacto'])); ?></td>
                            <td><?php echo htmlspecialchars(getHowDidYouMeetLabel($row['donde_conocen'])); ?></td>
                            <td><?php
                                $hearLabel = getHearAboutUsLabel($row['hear_about_us']);
                                echo htmlspecialchars($hearLabel);
                            ?></td>
                            <td><?php
                                $cn = trim($row['campaign_name']);
                                echo $cn !== '' ? htmlspecialchars($cn) : '<span class="vp-na">—</span>';
                            ?></td>
                            <td><?php
                                $est = $row['estatus'] ?? 'Lead';
                                $estClass = 'vp-badge-pre';
                                if ($est === 'Agendado') $estClass = 'vp-badge-agendado';
                                elseif ($est === 'Cliente') $estClass = 'vp-badge-cliente';
                                echo '<span class="vp-badge ' . $estClass . '">' . htmlspecialchars($est) . '</span>';
                            ?></td>
                            <td><?php
                                $isPreQ = strpos($row['origen'], 'Pre-Q') === 0;
                                $badgeClass = $isPreQ ? 'vp-badge-pre' : 'vp-badge-cf';
                                echo '<span class="vp-badge ' . $badgeClass . '">' . $row['origen'] . '</span>';
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

    <script>
    $(document).ready(function() {
        $('#vpTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            ordering: true,
            paging: true,
            pageLength: 50,
            searching: true,
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel'],
            scrollX: true,
            columnDefs: [
                { orderable: true, targets: '_all' }
            ]
        });
    });
    </script>
</body>
</html>
