<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Mexico/General');
include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexi贸n a la base de datos

$tipoUsuario = $_SESSION['tus'];
$idUsu = $_SESSION['uid'];

$datos_cuentas = [];
$sqlCuentas = "SELECT id, nombre_titular, nombre_banco FROM datos_transferencia ORDER BY id ASC";
$resultCuentas = $conn->query($sqlCuentas);

if ($resultCuentas->num_rows > 0) {
    while ($row = $resultCuentas->fetch_assoc()) {
        $datos_cuentas[] = $row;
    }
}

$cuestionarios = [];
$sqlCuestionarios = "SELECT * FROM cuestionario";
$resultCuestionarios = $conn->query($sqlCuestionarios);

if ($resultCuestionarios->num_rows > 0) {
    while ($row = $resultCuestionarios->fetch_assoc()) {
        $cuestionarios[] = $row;
    }
}


// Realizar la consulta SQL para obtener el registro de la tabla respuestas_cuestionario
$sqlCuestionario = "SELECT * FROM respuestas_cuestionario";
$resultCuestionario = $conn->query($sqlCuestionario);
$respuestas_cuestionario = array(); // Array para almacenar los resultados
if ($resultCuestionario->num_rows > 0) {
    // Si hay resultados, los almacenamos en el array
    while ($row = $resultCuestionario->fetch_assoc()) {
        $respuestas_cuestionario[] = $row; // Agregar cada fila al array
    }
}





// Realizar la consulta SQL para obtener todos los usuarios
$sql = "SELECT * FROM usuarios WHERE tipoUsu = 1";
$result = $conn->query($sql);

$usuarios = array(); // Array para almacenar los resultados

if ($result->num_rows > 0) {
    // Si hay resultados, los almacenamos en el array
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = $row; // Agregar cada fila al array
    }
}

$paquetesArr = [];
$paquetesResult = $conn->query("SELECT id, nombre, que_se_les_vendio FROM paquetes ORDER BY id");
if ($paquetesResult) {
    while ($row = $paquetesResult->fetch_assoc()) {
        $paquetesArr[] = $row;
    }
}

$sqlPagos = "SELECT * FROM pagos";
$resultPagos = $conn->query($sqlPagos);

$pagos = array(); // Array para almacenar los resultados

if ($resultPagos->num_rows > 0) {
    // Si hay resultados, los almacenamos en el array
    while ($row = $resultPagos->fetch_assoc()) {
        $pagos[] = $row; // Agregar cada fila al array
    }
}



// En lugar de filtrar por id_cliente, obtén todos los registros
$sqlNextstape = "SELECT * FROM nextstep_table";
$resultNextstape = $conn->query($sqlNextstape);

$nextstape = array();
if ($resultNextstape->num_rows > 0) {
    while ($row = $resultNextstape->fetch_assoc()) {
        $nextstape[] = $row;
    }
}

$sqlGaleria = "SELECT * FROM galeria";
$resultGaleria = $conn->query($sqlGaleria);

$galerias = array(); // Array para almacenar los resultados

if ($resultGaleria->num_rows > 0) {
    // Si hay resultados, los almacenamos en el array
    while ($row = $resultGaleria->fetch_assoc()) {
        $galerias[] = $row; // Agregar cada fila al array
    }
}

$sqlContratos = "SELECT * FROM contratos";
$resultContratos = $conn->query($sqlContratos);

$contratos = array(); // Array para almacenar los resultados

if ($resultContratos->num_rows > 0) {
    // Si hay resultados, los almacenamos en el array
    while ($row = $resultContratos->fetch_assoc()) {
        $contratos[] = $row; // Agregar cada fila al array
    }
}








$queryWhatsapp = "SELECT id, numero FROM whatsapp"; // Incluye el id para cada cuenta

$resultWhatsapp = $conn->query($queryWhatsapp);

// Guardamos los números en un array
$numerosWhatsapp = [];

if ($resultWhatsapp->num_rows > 0) {
    while ($row = $resultWhatsapp->fetch_assoc()) {
        $numerosWhatsapp[] = $row; // Almacena id y número
    }
}





// Crear un objeto DateTime para obtener la fecha y hora actual
$date = new DateTime();

// Configurar el formateo de la fecha en español
$fmt = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, "eeee dd 'de' MMMM 'del' yyyy  hh:mm a");

// Formatear la fecha con el objeto IntlDateFormatter
$fecha = $fmt->format($date);


$queryBloqueoDias = "SELECT * FROM dias_bloqueados";
$resultBloqueoDias = mysqli_query($conn, $queryBloqueoDias);

// Verificar si la consulta tuvo resultados
if ($resultBloqueoDias) {
    $dias_bloqueados = [];
    while ($row = mysqli_fetch_assoc($resultBloqueoDias)) {
        // Guardar todo el registro (todos los campos)
        $dias_bloqueados[] = $row['fecha'];
    }

    // Devolver los datos en formato JSON

}
function obtenerDatos($conn, $tabla)
{
    if ($tabla == "contact_form") {
        $sql = "SELECT cf.*, 
                       uv.nombre as vendedor_asignado_nombre, 
                       uv.apePat as vendedor_asignado_apePat, 
                       uv.apeMat as vendedor_asignado_apeMat
                FROM $tabla cf
                LEFT JOIN usuarios uv ON cf.id_vendedor_asignado = uv.id
                WHERE cf.cliente = 1";
        $result = $conn->query($sql);

        // Verificar si la consulta se ejecutó correctamente
        if (!$result) {
            die("Error en la consulta: " . $conn->error);
        }

        return ($result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        $sql = "SELECT * FROM $tabla";
        $result = $conn->query($sql);

        // Verificar si la consulta se ejecutó correctamente
        if (!$result) {
            die("Error en la consulta: " . $conn->error);
        }

        return ($result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
$txt_correo = obtenerDatos($conn, 'txtCorreo');

// Cerrar la conexión
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Clientes</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">

</head>
<style>
    .btn-enviarWhatsapp {
        color: white;
        background-color: #128c7e;
        border: solid 1px #128c7e;
    }

    .btn-enviarWhatsapp :hover {

        background-color: #128c7e;

    }

    .notification-bubble {
        position: absolute;
        top: -3px;
        right: -1px;
        width: 20px;
        height: 20px;
        background-color: #bba985;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
        display: none;
        z-index: 1050;
    }

    .nav-item {
        position: relative;
    }

    #verMasModal p {
        font-size: 1.15rem;
        margin: 0;
    }

    table {

        width: 100% !important;

    }

    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border: 1px solid rgba(0, 0, 0, 0.125);
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .form-select-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .table-responsive {
        border-radius: 0.375rem;
        overflow-x: auto;
    }

    .modal-custom {
        max-width: 90% !important;
        /* 90% del ancho de la ventana */
    }

    .form-group {
        margin: 0;
    }

    .text-verde {
        color: #46ba46 !important;
    }

    .nombre-asesor {
        cursor: pointer;

        text-decoration: underline dotted transparent;
        transition: text-decoration-color 0.3s ease;
    }

    .nombre-asesor:hover {
        text-decoration-color: #007bff;
        /* Hace visible el subrayado punteado al hacer hover */
        text-decoration: underline;
    }

    .table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    .clie-status-pill {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }

    .clie-status-agendado { background: rgba(59,130,246,0.10); color: #1d4ed8; }
    .clie-status-atendido { background: rgba(16,185,129,0.10); color: #047857; }
    .clie-status-fantasma { background: rgba(239,68,68,0.10); color: #b91c1c; }
    .clie-status-muerto { background: rgba(100,116,139,0.10); color: #475569; }
    .clie-status-cliente { background: rgba(197,160,40,0.12); color: #92700c; }
    .clie-status-default { background: rgba(197,160,40,0.12); color: #92700c; }

    .clie-eng-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }

    .clie-eng-low { background: rgba(245,158,11,0.12); color: #B45309; }
    .clie-eng-mid { background: rgba(59,130,246,0.12); color: #1d4ed8; }
    .clie-eng-high { background: rgba(16,185,129,0.12); color: #047857; }

    .clie-td-name {
        font-weight: 600;
        font-size: 13px;
    }

    #editClientModal .modal-dialog {
        max-width: 760px;
    }

    #editClientModal .modal-content {
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
        border: none;
    }

    #editClientModal .modal-header {
        background: #eee8dc;
        color: #464646;
        border-bottom: none;
        padding: 22px 28px;
        display: block;
    }

    #editClientModal .modal-header h5 {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 700;
    }

    #editClientModal #sfSessionBody {
        padding: 28px 32px;
        overflow-y: auto;
        max-height: calc(100vh - 260px);
    }

    .ecm-section {
        margin-bottom: 24px;
    }

    .ecm-section-title {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 14px;
    }

    .ecm-section-number {
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

    .ecm-section-heading h5 {
        margin: 0;
        font-weight: 700;
        font-size: 1rem;
        color: #464646;
    }

    .ecm-section-heading p {
        margin: 2px 0 0;
        font-size: 0.8rem;
        color: #777;
    }

    .ecm-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .ecm-grid-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 12px;
    }

    .ecm-field label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #555;
        margin-bottom: 4px;
        display: block;
    }

    .ecm-field .form-control,
    .ecm-field .form-select {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 0.9rem;
        color: #333;
        outline: none;
        box-shadow: none;
    }

    .ecm-field .form-control:focus,
    .ecm-field .form-select:focus {
        border-color: #464646;
        box-shadow: none;
    }

    .ecm-origin-box {
        border: 1.5px solid #464646;
        border-radius: 12px;
        padding: 16px 18px;
    }

    .ecm-origin-badge {
        display: inline-block;
        background: #464646;
        color: #eee8dc;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        padding: 3px 10px;
        border-radius: 4px;
        margin-right: 10px;
        letter-spacing: 0.6px;
    }

    .ecm-origin-title {
        font-weight: 700;
        font-size: 1rem;
        color: #333;
    }

    .ecm-category-btns {
        display: flex;
        gap: 12px;
        margin: 14px 0 16px;
        flex-wrap: wrap;
    }

    .ecm-category-btn {
        flex: 1;
        min-width: 120px;
        border: 1.5px solid #e0e0e0;
        border-radius: 10px;
        padding: 14px 10px;
        text-align: center;
        cursor: pointer;
        background: #fff;
        font-size: 0.85rem;
        font-weight: 600;
        color: #444;
        transition: all 0.15s;
    }

    .ecm-category-btn .ecm-cat-icon {
        font-size: 1.5rem;
        display: block;
        margin-bottom: 6px;
    }

    .ecm-category-btn:hover {
        border-color: #464646;
    }

    .ecm-category-btn.active {
        border-color: #464646;
        background: #464646;
        color: #eee8dc;
    }

    .ecm-tipo-cliente-btn {
        flex: 1; min-width: 140px; border: 1.5px solid #e0e0e0; border-radius: 10px;
        padding: 14px 10px; text-align: center; cursor: pointer; background: #fff;
        font-size: 0.85rem; font-weight: 600; color: #444; transition: all 0.15s;
    }
    .ecm-tipo-cliente-btn .ecm-cat-icon { font-size: 1.5rem; display: block; margin-bottom: 6px; }
    .ecm-tipo-cliente-btn:hover { border-color: #464646; }
    .ecm-tipo-cliente-btn.active { border-color: #464646; background: #464646; color: #eee8dc; }

    .ecm-how-to-ask {
        margin-top: 12px;
        font-size: 0.78rem;
        color: #666;
        font-style: italic;
    }

    .ecm-package-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .ecm-package-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1.5px solid #e0e0e0;
        border-radius: 10px;
        padding: 13px 16px;
        cursor: pointer;
        transition: all 0.15s;
        background: #fff;
    }

    .ecm-package-item:hover {
        border-color: #464646;
    }

    .ecm-package-item.active {
        border-color: #eee8dc;
        background: #f5f5f3;
    }

    .ecm-package-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: #222;
    }

    .ecm-package-dot {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid #ccc;
        background: #fff;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
    }

    .ecm-package-item.active .ecm-package-dot {
        border-color: #eee8dc;
        background: #eee8dc;
    }

    .ecm-package-item.active .ecm-package-dot::after {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #fff;
    }

    .ecm-engagement-btns {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .ecm-engagement-btn {
        flex: 1;
        min-width: 130px;
        border: 1.5px solid #e0e0e0;
        border-radius: 10px;
        padding: 16px 10px;
        text-align: center;
        cursor: pointer;
        background: #fff;
        transition: all 0.15s;
    }

    .ecm-engagement-btn .ecm-eng-icon {
        font-size: 1.8rem;
        display: block;
        margin-bottom: 6px;
    }

    .ecm-engagement-btn .ecm-eng-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: #424242;
        display: block;
    }

    .ecm-engagement-btn .ecm-eng-desc {
        font-size: 0.75rem;
        color: #888;
        margin-top: 4px;
    }

    .ecm-engagement-btn:hover {
        border-color: #464646;
    }

    .ecm-engagement-btn.active {
        border-color: #464646;
        background: #464646;
    }

    .ecm-engagement-btn.active .ecm-eng-name {
        color: #fff;
    }

    .ecm-known-us-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 8px;
    }

    .ecm-known-us-btn {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        border: 1.5px solid #e0e0e0;
        border-radius: 10px;
        padding: 12px 14px;
        cursor: pointer;
        background: #fff;
        transition: all 0.15s;
        text-align: left;
        font-family: inherit;
    }

    .ecm-known-us-btn:hover {
        border-color: #464646;
    }

    .ecm-known-us-btn.active {
        border-color: #464646;
        background: #f5f1e8;
    }

    .ecm-known-us-btn .ecmku-title {
        font-weight: 700;
        font-size: 0.88rem;
        color: #222;
        margin-bottom: 4px;
        display: block;
    }

    .ecm-known-us-btn .ecmku-desc {
        font-size: 0.76rem;
        color: #888;
        line-height: 1.35;
        display: block;
    }

    .ecm-comments-group {
        margin-bottom: 24px;
    }

    .ecm-comments-group h6 {
        font-weight: 700;
        color: #464646;
        margin-bottom: 12px;
    }

    .ecm-comment-card {
        border: 1px solid #ddd;
        border-radius: 10px;
        background: #f8f8f8;
        padding: 12px 14px;
        min-height: 84px;
    }

    .ecm-comment-card-title {
        font-size: 0.8rem;
        font-weight: 700;
        color: #555;
        margin-bottom: 6px;
    }

    .ecm-comment-card-text {
        font-size: 0.9rem;
        color: #333;
        white-space: pre-wrap;
        word-break: break-word;
    }

    #editClientModal .modal-footer {
        display: flex;
        gap: 12px;
        padding: 20px 32px 28px;
        border-top: 1px solid #eee;
    }

    #editClientModal .ecm-btn-cancel {
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

    #editClientModal .ecm-btn-cancel:hover {
        background: #f5f5f5;
    }

    #editClientModal .ecm-btn-submit {
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

    #editClientModal .ecm-btn-submit:hover {
        background: #5a5a5a;
        color: #eee8dc;
    }

    @media (max-width: 600px) {
        #editClientModal #sfSessionBody {
            padding: 20px 16px;
        }

        #editClientModal .modal-header {
            padding: 18px 16px;
        }

        #editClientModal .modal-footer {
            padding: 16px;
        }

        .ecm-grid-2,
        .ecm-grid-3,
        .ecm-known-us-grid {
            grid-template-columns: 1fr;
        }
    }

    /* ── VENTAS DASHBOARD ── */
    .ventas-dashboard {
        --gold: #C5A028;
        --panel: #FFFFFF;
        --surface: #F8FAFC;
        --ink: #1E293B;
        --muted: #64748B;
        --border: #E2E8F0;
        --radius: 12px;
        margin-bottom: 24px;
    }
    .ventas-dashboard .filter-bar {
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 14px 20px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .ventas-dashboard .filter-label {
        font-size: 11px;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: var(--muted);
        margin-right: 4px;
        flex-shrink: 0;
        font-weight: 600;
    }
    .ventas-dashboard .efege-filter-input,
    .ventas-dashboard .efege-filter-select {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 7px;
        color: var(--ink);
        font-size: 12px;
        padding: 7px 12px;
        outline: none;
        transition: border-color 0.2s;
        appearance: none;
        -webkit-appearance: none;
    }
    .ventas-dashboard .efege-filter-select {
        padding-right: 28px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748B' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        cursor: pointer;
    }
    .ventas-dashboard .efege-filter-input:focus,
    .ventas-dashboard .efege-filter-select:focus { border-color: var(--gold); }
    .ventas-dashboard .efege-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: 7px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--ink);
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .ventas-dashboard .efege-btn:hover { border-color: var(--gold); color: var(--gold); }
    .ventas-dashboard .efege-btn-primary { background: var(--gold); border-color: var(--gold); color: #fff; }
    .ventas-dashboard .efege-btn-primary:hover { background: #b8921f; border-color: #b8921f; color: #fff; }
    .ventas-dashboard .kpi-strip {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }
    .ventas-dashboard .kpi-card {
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px 20px;
    }
    .ventas-dashboard .kpi-label {
        font-size: 10px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: 8px;
    }
    .ventas-dashboard .kpi-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--ink);
        line-height: 1;
        margin-bottom: 6px;
    }
    .ventas-dashboard .kpi-sub { font-size: 11px; color: var(--muted); }
    .ventas-dashboard .kpi-card.card-pending .kpi-value { color: #dc2626; }
    .ventas-dashboard .kpi-card.card-paid .kpi-value { color: #059669; }
    .ventas-dashboard .kpi-card.card-abonos .kpi-value { color: #2563eb; }
    @media (max-width: 1100px) { .ventas-dashboard .kpi-strip { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 700px) { .ventas-dashboard .kpi-strip { grid-template-columns: repeat(2, 1fr); } }
</style>
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-12 order-md-1 order-last">

                <h3>Consulta de clientes</h3>
                <br>
            </div>
            <div class="col-12 col-md-12 order-md-2 order-first">

            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between aling-items-center">

        <div class="mr-5">
            <p><?php echo $fecha; ?></p>


        </div>
    </div>
    <section class="section">
        <!-- ── Dashboard de Ventas y Comisiones ─────────────────────────── -->
        <div class="ventas-dashboard" id="ventasDashboard">
            <!-- Filtros -->
            <div class="filter-bar">
                <span class="filter-label">Filtrar</span>
                <input type="date" id="vdFechaDesde" class="efege-filter-input" placeholder="Desde" title="Fecha cierre desde">
                <input type="date" id="vdFechaHasta" class="efege-filter-input" placeholder="Hasta" title="Fecha cierre hasta">
                <select id="vdVendedor" class="efege-filter-select" style="min-width:160px;">
                    <option value="">Todos los vendedores</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= intval($u['id']) ?>"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apePat']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="vdEstatus" class="efege-filter-select">
                    <option value="">Todos los estatus</option>
                    <option value="cliente">Cliente</option>
                    <option value="atendido">Atendido</option>
                    <option value="fantasma">Fantasma</option>
                    <option value="muerto">Muerto</option>
                </select>
                <button class="efege-btn efege-btn-primary" id="vdBtnFiltrar">Aplicar</button>
                <button class="efege-btn" id="vdBtnReset">Limpiar</button>
            </div>
            <!-- KPI Strip -->
            <div class="kpi-strip">
                <article class="kpi-card">
                    <div class="kpi-label">Monto total de ventas</div>
                    <div class="kpi-value" id="kpiTotalVenta">—</div>
                    <div class="kpi-sub" id="kpiTotalVentaCnt">— registros</div>
                </article>
                <article class="kpi-card card-abonos">
                    <div class="kpi-label">Saldo de abonos</div>
                    <div class="kpi-value" id="kpiAbonos">—</div>
                    <div class="kpi-sub">Pagos parciales acumulados</div>
                </article>
                <article class="kpi-card card-pending">
                    <div class="kpi-label">Saldo pendiente</div>
                    <div class="kpi-value" id="kpiSaldoPendiente">—</div>
                    <div class="kpi-sub">Venta − abonos</div>
                </article>
                <article class="kpi-card card-paid">
                    <div class="kpi-label">Comisiones pagadas</div>
                    <div class="kpi-value" id="kpiComisionPagada">—</div>
                    <div class="kpi-sub" id="kpiComisionPagadaCnt">— liquidadas</div>
                </article>
                <article class="kpi-card card-pending">
                    <div class="kpi-label">Comisiones pendientes</div>
                    <div class="kpi-value" id="kpiComisionPendiente">—</div>
                    <div class="kpi-sub" id="kpiComisionPendienteCnt">— por pagar</div>
                </article>
            </div>
        </div>
        <!-- ──────────────────────────────────────────────────────────────── -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Lista de Leads</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Origen</label>
                        <select id="filterOrigenClientes" class="form-select">
                            <option value="">Todos</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="clientesTable" class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>¿Dónde se casa?</th>
                                <th>¿Cuándo se casa?</th>
                                <th>Ciudad de origen del cliente</th>
                                <th>¿Cuándo llegó?</th>
                                <th>Fecha que se cerró</th>
                                <th>Monto de la venta</th>
                                <th>Puntos</th>
                                <th>Abonos</th>
                                <th>Saldo pendiente</th>
                                <th>Comisión</th>
                                <th>Vendedor asignado</th>
                                <th>¿Qué se les vendió?</th>
                                <th style="display:none">Sesión Oficial</th>
                                <?php if ($tipoUsuario != 4): ?>
                                    <th>Paquete</th>
                                <?php endif; ?>
                                <th style="display:none">Compromiso</th>
                                <th style="display:none">Técnica Cierre</th>
                                <?php if ($tipoUsuario != 4): ?>
                                    <th>Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>




</div>
<!-- Modal para Ver Más Información -->
<div class="modal fade" id="verMasModal" tabindex="-1" aria-labelledby="verMasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-custom" type="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verMasModalLabel">Más Información del Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <!-- Aquí se mostrarán los detalles del evento -->
                <div id="modalContent">
                    <!-- El contenido se llenará dinámicamente con JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- Modal -->






<!-- Modal para Reagendar -->
<div id="modal-reagendar" class="modal fade" tabindex="-1" aria-labelledby="reagendarModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" type="document">
        <div class="modal-content">
            <div class="modal-header">
                <?php if ($tipoUsuario == "0"): ?>
                    <h5 class="modal-title" id="reagendarModal">Reagendar Cita</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                <?php endif; ?>


            </div>
            <div class="modal-body" id="reagenda-modal">
                <p>Seleccione un asesor para la cita:</p>
                <!-- Aquí creamos el select vacío donde se agregarán los usuarios -->
                <select id="select-usuarios" class="form-control mb-3">
                    <option value="">Seleccionar usuario</option> <!-- Opción inicial vacía -->
                </select>

                <div class="mt-1" id="show_inputs" style="display: none;">
                    <div class="mb-3">
                        <p id="last_date" class="m-0"> </p>
                        <label for="date_appointment" class="form-label">Nueva fecha de cita</label>
                        <input id="date_appointment" name="date_appointment" placeholder="Selecciona una fecha"
                            autocomplete="off" type="date" class="form-control">
                    </div>

                    <div class="mb-3">
                        <p id="last_time" class="m-0"></p>
                        <label for="time_appointment" class="form-label">Nueva hora de cita</label>
                        <select id="time_appointment" name="time_appointment" autocomplete="off" class="form-control">
                            <!-- Opciones de tiempo dinámicas aquí -->
                        </select>
                    </div>
                    <div class="d-flex flex-row-reverse ">
                        <button type="button" class="btn btn-primary" id="confirmar-reagendar">Guardar reagenda
                            manual</button>
                    </div>
                </div>
                <div class="my-2">

                    <hr>
                </div>
                <div id="modal-reagendar-body" class="text-center mb-5">
                    <p class="h5 mb-3">¿Quieres mandar un correo para que el mismo cliente reagende su cita?</p>
                    <div id="btnReag"></div>

                </div>
            </div>
            <div class="modal-footer" id="modal-reagendar-footer">
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>

                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="z-index:53!important">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Detalles de las Respuestas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modal-questions-container"></div> <!-- Aquí se mostrarán las preguntas y respuestas -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editClientModalLabel">Editar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="sfSessionBody">
                <ul class="nav nav-pills mb-3" id="editClientTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="edit-client-data-tab" data-bs-toggle="pill" data-bs-target="#edit-client-data-pane" type="button" role="tab" aria-controls="edit-client-data-pane" aria-selected="true">Datos</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="edit-client-comments-tab" data-bs-toggle="pill" data-bs-target="#edit-client-comments-pane" type="button" role="tab" aria-controls="edit-client-comments-pane" aria-selected="false">Comentarios</button>
                    </li>
                </ul>

                <div class="tab-content" id="editClientTabContent">
                    <div class="tab-pane fade show active" id="edit-client-data-pane" role="tabpanel" aria-labelledby="edit-client-data-tab">
                        <form id="editClientForm">
                            <input type="hidden" id="editClientId" name="id" value="">
                            <input type="hidden" id="edit_tipo_cliente" name="tipo_cliente" value="">
                            <input type="hidden" id="edit_how_did_you_meet" name="how_did_you_meet" value="">
                            <input type="hidden" id="edit_engagement" name="engagement" value="">
                            <input type="hidden" id="edit_how_long_known_us" name="how_long_known_us" value="">

                            <div class="ecm-section">
                                <div class="ecm-section-title">
                                    <div class="ecm-section-number">1</div>
                                    <div class="ecm-section-heading">
                                        <h5>Datos del cliente</h5>
                                        <p>Actualiza los datos principales y de contacto del cliente.</p>
                                    </div>
                                </div>

                                <div class="ecm-field" style="margin-bottom: 12px;">
                                    <label>Nombre</label>
                                    <input type="text" id="edit_name" name="names" class="form-control">
                                </div>

                                <div class="ecm-grid-2">
                                    <div class="ecm-field">
                                        <label>Correo electrónico</label>
                                        <input type="email" id="edit_email_address" name="email_address" class="form-control" placeholder="correo@ejemplo.com">
                                    </div>
                                    <div class="ecm-field">
                                        <label>WhatsApp / Teléfono</label>
                                        <input type="text" id="edit_telephone" name="telephone" class="form-control" placeholder="+52 000 000 0000">
                                    </div>
                                    <div class="ecm-field">
                                        <label>Fecha de la boda</label>
                                        <input type="date" id="edit_wedding_date" name="wedding_date" class="form-control">
                                    </div>
                                    <div class="ecm-field">
                                        <label>Campaña (opcional)</label>
                                        <input type="text" id="edit_campaign_name" name="campaign_name" class="form-control" placeholder="Opcional (dejar vacío si no aplica)">
                                    </div>
                                    <div class="ecm-field">
                                        <label>Origen</label>
                                        <select id="edit_desde_publicidad" name="desde_publicidad" class="form-select">
                                            <option value="0">Website</option>
                                            <option value="1">Publicidad</option>
                                            <option value="2">Instagram Orgánico</option>
                                            <option value="3">Whatsapp</option>
                                            <option value="4">Correo electrónico</option>
                                        </select>
                                    </div>
                                    <div class="ecm-field">
                                        <label>Ubicación de la boda</label>
                                        <input type="text" id="edit_wedding_location" name="wedding_location" class="form-control">
                                    </div>
                                    <div class="ecm-field">
                                        <label>Ciudad de origen del cliente</label>
                                        <input type="text" id="edit_city" name="city" class="form-control" placeholder="Ej. Ciudad de México">
                                    </div>
                                    <div class="ecm-field">
                                        <label>Vendedor asignado</label>
                                        <select id="edit_id_vendedor_asignado" name="id_vendedor_asignado" class="form-select">
                                            <option value="0">-- Sin asignar --</option>
                                            <?php foreach ($usuarios as $v): ?>
                                                <option value="<?php echo intval($v['id']); ?>"><?php echo htmlspecialchars($v['nombre'] . ' ' . $v['apePat'] . ' ' . $v['apeMat']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="ecm-field">
                                        <label>Método de contacto (Primer canal)</label>
                                        <select id="edit_first_contact_channel" name="first_contact_channel" class="form-select">
                                            <option value="">Seleccionar...</option>
                                            <option value="WhatsApp">WhatsApp</option>
                                            <option value="IG">IG</option>
                                            <option value="Facebook">Facebook</option>
                                            <option value="Email">Correo electrónico</option>
                                            <option value="Phone call">Llamada telefónica</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="ecm-section">
                                <div class="ecm-section-title">
                                    <div class="ecm-section-number">2</div>
                                    <div class="ecm-section-heading">
                                        <h5>Tipo de cliente</h5>
                                        <p>¿El cliente es un Wedding Planner o un Cliente Final?</p>
                                    </div>
                                </div>
                                <div class="ecm-category-btns">
                                    <button type="button" class="ecm-tipo-cliente-btn" data-tipo="1"><span class="ecm-cat-icon">💼</span>Wedding Planner</button>
                                    <button type="button" class="ecm-tipo-cliente-btn" data-tipo="0"><span class="ecm-cat-icon">👤</span>Cliente Final</button>
                                </div>
                            </div>

                            <div class="ecm-section">
                                <div class="ecm-section-title">
                                    <div class="ecm-section-number">3</div>
                                    <div class="ecm-section-heading">
                                        <h5>Confirmar origen del cliente</h5>
                                        <p>Este es el campo más importante. Pregúntalo directamente en la llamada.</p>
                                    </div>
                                </div>

                                <div class="ecm-origin-box">
                                    <div style="margin-bottom: 14px;">
                                        <span class="ecm-origin-badge">CAMPO CLAVE</span>
                                        <span class="ecm-origin-title">¿De dónde nos conocieron?</span>
                                    </div>
                                    <div class="ecm-category-btns">
                                        <button type="button" class="ecm-category-btn" data-cat="1"><span class="ecm-cat-icon">💼</span>Wedding Planner</button>
                                        <button type="button" class="ecm-category-btn" data-cat="2"><span class="ecm-cat-icon">🤝</span>Community</button>
                                        <button type="button" class="ecm-category-btn" data-cat="3"><span class="ecm-cat-icon">🚀</span>New Market</button>
                                    </div>

                                    <div class="ecm-field" id="edit_hear_about_us_wrapper" style="display:none;">
                                        <label>¿Cómo llegaron exactamente?</label>
                                        <select id="edit_hear_about_us" name="hear_about_us" class="form-select">
                                            <option value="">¿Cómo llegaron exactamente?</option>
                                            <option value="1">Meta Ads — anuncio en Instagram / Facebook</option>
                                            <option value="2">SEO — buscaron en Google</option>
                                            <option value="3">Colaboración / Influencer / Famoso</option>
                                            <option value="4">Publicación / Prensa / Revista</option>
                                            <option value="5">Otro</option>
                                        </select>
                                    </div>

                                    <div class="ecm-how-to-ask">
                                        Cómo preguntar: <em>"¿Recuerdan cómo nos encontraron? ¿Ya nos conocían de antes, o fue la primera vez que vieron nuestro trabajo?"</em>
                                    </div>
                                </div>
                            </div>

                            <div class="ecm-section">
                                <div class="ecm-section-title">
                                    <div class="ecm-section-number">4</div>
                                    <div class="ecm-section-heading">
                                        <h5>Engagement del cliente</h5>
                                        <p>Tu percepción después de la sesión. Sé honesta.</p>
                                    </div>
                                </div>

                                <div class="ecm-engagement-btns">
                                    <button type="button" class="ecm-engagement-btn" data-eng="1"><span class="ecm-eng-icon">😑</span><span class="ecm-eng-name">Bajo</span><div class="ecm-eng-desc">Poco entusiasmo, muchas dudas o precio</div></button>
                                    <button type="button" class="ecm-engagement-btn" data-eng="2"><span class="ecm-eng-icon">😃</span><span class="ecm-eng-name">Medio</span><div class="ecm-eng-desc">Interés real, pero falta de decisión</div></button>
                                    <button type="button" class="ecm-engagement-btn" data-eng="3"><span class="ecm-eng-icon">🔥</span><span class="ecm-eng-name">Alto</span><div class="ecm-eng-desc">Muy interesados, listos para cerrar</div></button>
                                </div>
                            </div>

                            <div class="ecm-section">
                                <div class="ecm-section-title">
                                    <div class="ecm-section-number">5</div>
                                    <div class="ecm-section-heading">
                                        <h5>¿Desde hace cuánto nos conoce el cliente? (refuerzo)</h5>
                                        <p>Confirma directamente con el cliente durante la sesión.</p>
                                    </div>
                                </div>
                                <div class="ecm-known-us-grid" id="ecm-known-us-grid">
                                    <button type="button" class="ecm-known-us-btn" data-val="Less than 3 months"><span class="ecmku-title">Menos de 3 meses</span><span class="ecmku-desc">Nos conociste recientemente.</span></button>
                                    <button type="button" class="ecm-known-us-btn" data-val="Between 3 months and 1 year"><span class="ecmku-title">Entre 3 meses y 1 año</span><span class="ecmku-desc">Ya tenías un tiempo siguiéndonos.</span></button>
                                    <button type="button" class="ecm-known-us-btn" data-val="More than 1 year"><span class="ecmku-title">Más de 1 año</span><span class="ecmku-desc">Nos conocías desde hace bastante tiempo.</span></button>
                                </div>
                            </div>

                            <div class="ecm-section">
                                <div class="ecm-section-title">
                                    <div class="ecm-section-number">6</div>
                                    <div class="ecm-section-heading">
                                        <h5>Datos de cierre de venta</h5>
                                        <p>Información del cierre y monto de venta.</p>
                                    </div>
                                </div>

                                <div class="ecm-field" style="margin-bottom: 12px;">
                                    <label>Paquete de interés</label>
                                    <div id="edit_package_list" class="ecm-package-list"></div>
                                </div>
                                <input type="hidden" id="edit_paquete" name="paquete" value="">

                                <div class="ecm-field" style="margin-top: 12px;">
                                    <label>¿Qué se les vendió?</label>
                                    <input type="text" id="edit_que_se_les_vendio" name="que_se_les_vendio" class="form-control" placeholder="Ej. 1 Foto 1 Video">
                                </div>

                                <div class="ecm-grid-3" style="margin-top: 12px;">
                                    <div class="ecm-field">
                                        <label>Monto de venta</label>
                                        <input type="number" step="0.01" id="edit_monto_venta" name="monto_venta" class="form-control">
                                    </div>
                                    <div class="ecm-field">
                                        <label>Puntos</label>
                                        <input type="number" step="any" id="edit_puntos" name="puntos" class="form-control">
                                    </div>
                                    <div class="ecm-field">
                                        <label>Fecha que se cerró</label>
                                        <input type="date" id="edit_fecha_cambio_cliente" name="fecha_cambio_cliente" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="ecm-section" style="margin-bottom: 0;">
                                <div class="ecm-section-title">
                                    <div class="ecm-section-number">7</div>
                                    <div class="ecm-section-heading">
                                        <h5>Control interno</h5>
                                        <p>Fecha de registro del cliente.</p>
                                    </div>
                                </div>
                                <div class="ecm-field">
                                    <label>Fecha de registro (fecha y hora)</label>
                                    <input type="datetime-local" id="edit_submission_date" name="submission_date" class="form-control">
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="edit-client-comments-pane" role="tabpanel" aria-labelledby="edit-client-comments-tab">
                        <div id="editCommentsContainer" class="pt-2">
                            <p class="text-muted mb-0">Sin comentarios disponibles.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="ecm-btn-cancel" data-bs-dismiss="modal">Cerrar</button>
                <button id="saveEditClientBtn" type="button" class="ecm-btn-submit">Guardar cambios</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar Fecha que se cerró -->
<div class="modal fade" id="editCloseDateModal" tabindex="-1" aria-labelledby="editCloseDateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCloseDateModalLabel">Editar Fecha que se cerró</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editCloseDateId" value="">
                <div class="mb-3">
                    <label for="editCloseDateInput" class="form-label">Fecha de cierre</label>
                    <input type="date" id="editCloseDateInput" class="form-control" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveCloseDateBtn">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar Comisión -->
<div class="modal fade" id="editComisionModal" tabindex="-1" aria-labelledby="editComisionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editComisionModalLabel">Editar Comisión</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editComisionId" value="">
                <div class="mb-3">
                    <label for="editComisionInput" class="form-label">Monto de comisión</label>
                    <input type="number" step="0.01" id="editComisionInput" class="form-control" placeholder="0.00" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveComisionBtn">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para cambiar Vendedor asignado -->
<div class="modal fade" id="editVendedorModal" tabindex="-1" aria-labelledby="editVendedorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editVendedorModalLabel">Cambiar vendedor asignado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editVendedorId" value="">
                <div class="mb-3">
                    <label for="editVendedorSelect" class="form-label">Vendedor</label>
                    <select id="editVendedorSelect" class="form-select"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveVendedorBtn">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar Monto de la venta -->
<div class="modal fade" id="editAmountModal" tabindex="-1" aria-labelledby="editAmountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAmountModalLabel">Editar Monto de la venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editAmountId" value="">
                <div class="mb-3">
                    <label for="editAmountInput" class="form-label">Monto de la venta</label>
                    <input type="number" step="0.01" id="editAmountInput" class="form-control" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveAmountBtn">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar Paquete -->
<div class="modal fade" id="editPaqueteModal" tabindex="-1" aria-labelledby="editPaqueteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPaqueteModalLabel">Editar Paquete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editPaqueteId" value="">
                <div class="mb-3">
                    <label for="editPaqueteInput" class="form-label">Selecciona paquete</label>
                    <select id="editPaqueteInput" class="form-select"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="savePaqueteBtn">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar Qué se les vendió -->
<div class="modal fade" id="editQueSeLesVendioModal" tabindex="-1" aria-labelledby="editQueSeLesVendioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editQueSeLesVendioModalLabel">Editar Qué se les vendió</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editQueSeLesVendioId" value="">
                <div class="mb-3">
                    <label for="editQueSeLesVendioInput" class="form-label">Qué se les vendió</label>
                    <input type="text" id="editQueSeLesVendioInput" class="form-control" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveQueSeLesVendioBtn">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/locale/es.js"></script>


<!-- Bootstrap JS y Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
<script>
    const blockedDates = <?php echo json_encode($dias_bloqueados); ?>;
    let wcuentas = <? echo json_encode($numerosWhatsapp) ?>;

    const usuarios = <?php echo json_encode($usuarios); ?>;
    const paquetesData = <?php echo json_encode($paquetesArr); ?>;
    const respuestas_cuestionario = <?php echo json_encode($respuestas_cuestionario); ?>;
    console.log("Datos de nextstep_table:", <?php echo json_encode($nextstape); ?>);


    // Obtener la fecha de hoy
    let today = new Date();

    // Establecer el atributo "min" para bloquear días antes de hoy
    document.getElementById('date_appointment').setAttribute('min', today.toISOString().split('T')[0]);

    // Calcular la fecha 20 días después de hoy
    today.setDate(today.getDate() + 20);

    // Establecer el atributo "max" para bloquear días después de 20 días
    document.getElementById('date_appointment').setAttribute('max', today.toISOString().split('T')[0]);


    // Obtener el elemento input
    let dateInput = document.getElementById('date_appointment');

    // Convertir las fechas bloqueadas a un set para hacer la búsqueda más eficiente
    let blockedDatesSet = new Set(blockedDates);

    // Función que verifica si una fecha está bloqueada
    function isBlocked(date) {
        return blockedDatesSet.has(date);
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function toDateInput(mysqlDate) {
        if (!mysqlDate) return '';
        var dpart = mysqlDate.split(' ')[0];
        return /^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(dpart) ? dpart : '';
    }

    function toDateTimeInput(mysqlDatetime) {
        if (!mysqlDatetime) return '';
        if (typeof mysqlDatetime === 'string' && mysqlDatetime.indexOf('0000-00-00') === 0) return '';
        var s = mysqlDatetime.replace(' ', 'T');
        var m = s.match(/^([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2})(:[0-9]{2})?$/);
        if (m) return m[1];
        var d = new Date(s);
        if (isNaN(d.getTime())) return '';
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function fromDateInputToMysql(dateInput) {
        return dateInput || '';
    }

    function fromDateTimeInputToMysql(datetimeLocal) {
        if (!datetimeLocal) return '';
        var v = datetimeLocal.replace('T', ' ');
        if (/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$/.test(v)) v += ':00';
        return v;
    }

    function getPointsDisplayValue(row) {
        var rawPoints = row && row.puntos !== null && typeof row.puntos !== 'undefined'
            ? String(row.puntos).trim()
            : '';
        var rawAmount = row && row.monto_venta !== null && typeof row.monto_venta !== 'undefined'
            ? String(row.monto_venta).trim()
            : '';

        if (!rawPoints && !rawAmount) return '';

        var amountNumeric = rawAmount ? parseFloat(rawAmount.replace(/[^0-9.\-]/g, '')) : NaN;
        var normalizedPoints = rawPoints.replace(/[^0-9.\-]/g, '');
        var pointDecimals = 0;

        if (normalizedPoints.indexOf('.') !== -1) {
            pointDecimals = normalizedPoints.split('.')[1].replace(/0+$/, '').length;
        }

        // If stored points only keep a single decimal, recompute from amount to recover visible precision.
        if (!isNaN(amountNumeric) && amountNumeric > 0 && (!rawPoints || pointDecimals <= 1)) {
            return String(amountNumeric / 77000);
        }

        return rawPoints;
    }

    function renderEditPackageList(selectedPackageName) {
        var list = $('#edit_package_list');
        if (!list.length) return;
        list.empty();

        var selected = (selectedPackageName || '').toString().trim().toLowerCase();
        if (!paquetesData || !paquetesData.length) {
            list.append('<div class="text-muted">Sin paquetes disponibles.</div>');
            return;
        }

        paquetesData.forEach(function (p) {
            var nombre = (p && p.nombre ? String(p.nombre) : '').trim();
            var venta = (p && p.que_se_les_vendio ? String(p.que_se_les_vendio) : '').trim();
            if (!nombre) return;
            var active = selected && selected === nombre.toLowerCase() ? ' active' : '';
            var item = '';
            item += '<div class="ecm-package-item' + active + '" data-paq-name="' + escapeHtml(nombre) + '" data-paq-venta="' + escapeHtml(venta) + '">';
            item += '<div class="ecm-package-name">' + escapeHtml(nombre) + '</div>';
            item += '<div class="ecm-package-dot"></div>';
            item += '</div>';
            list.append(item);
        });
    }

    function renderEditCommentsTab(client) {
        var container = $('#editCommentsContainer');
        if (!container.length) return;

        var comments = (client && client.related_comments) ? client.related_comments : {};
        var groups = [
            {
                title: 'Wedding Planner',
                items: [
                    { label: 'Comentario al pasar a atendido', value: comments.wp_initial || '' },
                    { label: 'Comentario al pasar a cliente', value: comments.wp_client || '' }
                ]
            },
            {
                title: 'Agenda',
                items: [
                    { label: 'Comentario al pasar a atendido', value: comments.agenda_initial || '' },
                    { label: 'Comentario al pasar a cliente', value: comments.agenda_client || '' }
                ]
            }
        ];

        var html = '';
        var hasAnyComment = false;

        groups.forEach(function (group) {
            var validItems = group.items.filter(function (item) {
                return item.value && String(item.value).trim() !== '';
            });

            if (!validItems.length) return;
            hasAnyComment = true;
            html += '<div class="ecm-comments-group">';
            html += '<h6>' + escapeHtml(group.title) + '</h6>';
            html += '<div class="ecm-grid-2">';
            validItems.forEach(function (item) {
                html += '<div class="ecm-comment-card">';
                html += '<div class="ecm-comment-card-title">' + escapeHtml(item.label) + '</div>';
                html += '<div class="ecm-comment-card-text">' + escapeHtml(item.value) + '</div>';
                html += '</div>';
            });
            html += '</div>';
            html += '</div>';
        });

        if (!hasAnyComment) {
            html = '<p class="text-muted mb-0">Sin comentarios disponibles.</p>';
        }

        container.html(html);
    }


    $(document).ready(function () {

        var tipoUsuario = "<?php echo $_SESSION['tus']; ?>";
        var idUsu = "<?php echo $_SESSION['uid']; ?>";
        var pagos = <?php echo json_encode($pagos); ?>;





        let clientes = []
       $.ajax({
    url: 'obtener_clientes.php',
    type: 'GET',
    dataType: 'json',
    success: function (response) {
        console.log(response);
        console.log('obtener_clientes: data length =', (response && response.data) ? response.data.length : 0);
        console.log('obtener_clientes: primeros 3 registros =', (response && response.data) ? response.data.slice(0, 3) : []);
        if (!response || !response.data || response.data.length === 0) {
            console.warn('obtener_clientes: response.data vacío o no existente', response);
        }

        if (response.data && response.data.length > 0) {

            clientes = tipoUsuario == "0" || tipoUsuario == "2" || tipoUsuario == "3"
                ? response.data.filter(item => item.is_cliente == "1")
                : response.data.filter(item => item.id_vendedor_asignado == idUsu && item.is_cliente == "1");

            const now = new Date();

            var origenesSet = new Set();
            clientes.forEach(function (item) {
                var cname = item.campaign_name || '';
                var origenNorm = '';
                if (cname && cname.toString().trim() !== '') {
                    var m = cname.toString().match(/^c(\d+)\./i);
                    if (m) origenNorm = 'c' + m[1];
                    else origenNorm = cname.toString().trim();
                } else {
                    origenNorm = 'N/A';
                }
                origenesSet.add(origenNorm);
            });

            var origenes = Array.from(origenesSet).sort();
            var $selOrigen = $('#filterOrigenClientes');
            $selOrigen.empty().append('<option value="">Todos</option>');
            origenes.forEach(function (o) {
                $selOrigen.append('<option value="' + o + '">' + o + '</option>');
            });

            function formatLeadDate(dateString) {
                if (!dateString) return '—';
                var m = moment(dateString);
                return m.isValid() ? m.format('DD/MM/YYYY') : '—';
            }

            function formatCreatedTime(dateString) {
                if (!dateString) return '—';
                var m = moment(dateString);
                if (!m.isValid()) return escapeHtml(dateString);
                var months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                var day = m.date();
                var month = months[m.month()];
                var year = m.year();
                var hour = m.format('h');
                var minute = m.format('mm');
                var ampm = m.format('A') === 'AM' ? 'a.m.' : 'p.m.';
                return day + ' de ' + month + ' de ' + year + ' a las ' + hour + ':' + minute + ' ' + ampm;
            }

            function normalizeFirstContactChannelLabel(value) {
                var normalized = (value || '').toString().trim();
                if (!normalized) return 'Sin dato';
                var key = normalized.toLowerCase().replace(/[–—]/g, '-').replace(/\s+/g, ' ');
                var map = {
                    'whatsapp': 'WhatsApp',
                    'instagram dm - campaign': 'Instagram DM - Campaña',
                    'instagram dm campaign': 'Instagram DM - Campaña',
                    'instagram dm - organic': 'Instagram DM - Orgánico',
                    'instagram dm organic': 'Instagram DM - Orgánico',
                    'email': 'Correo electrónico',
                    'correo electronico': 'Correo electrónico',
                    'correo electrónico': 'Correo electrónico',
                    'mail': 'Correo electrónico',
                    'phone call': 'Phone call',
                    'llamada telefonica': 'Phone call',
                    'llamada telefónica': 'Phone call'
                };
                return map[key] || normalized;
            }

            function getOrigenCategoriaLabel(row) {
                var howRaw = (row.cliente_how_did_you_meet || row.how_did_you_meet || '').toString().trim();
                var howMap = { '1': 'Wedding Planner', '2': 'Community', '3': 'New Market' };
                return (howRaw !== '' && howMap[howRaw]) ? howMap[howRaw] : 'N/A';
            }

            function getKnownUsLabel(value) {
                var raw = (value || '').toString().trim();
                if (!raw) return '—';
                var map = {
                    'less than 3 months': 'Menos de 3 meses',
                    'between 3 months and 1 year': 'Entre 3 meses y 1 año',
                    'more than 1 year': 'Más de 1 año',
                    'not asked': 'No se preguntó'
                };
                var key = raw.toLowerCase();
                return map[key] || raw;
            }

            function getEngagementLabelWithEmoji(row) {
                var raw = (row.engagement || '').toString().trim();
                if (!raw) return 'N/A';
                var n = raw.toLowerCase();
                if (n === '0') return 'Sin dato';
                if (n === '1' || n === 'bajo') return '😑 Bajo';
                if (n === '2' || n === 'medio') return '😃 Medio';
                if (n === '3' || n === 'alto') return '🔥 Alto';
                return raw;
            }

            function getEngagementPill(row) {
                var raw = (row.engagement || '').toString().trim().toLowerCase();
                var label = getEngagementLabelWithEmoji(row);
                if (label === 'N/A' || label === 'Sin dato') {
                    return '<span style="color:#94a3b8;">' + escapeHtml(label) + '</span>';
                }
                var cls = 'clie-eng-low';
                if (raw === '3' || raw === 'alto') cls = 'clie-eng-high';
                else if (raw === '2' || raw === 'medio') cls = 'clie-eng-mid';
                return '<span class="clie-eng-pill ' + cls + '">' + escapeHtml(label) + '</span>';
            }

            function getStatusInfo(row) {
                var statusRaw = '';
                if (String(row.is_cliente) === '1') {
                    statusRaw = 'cliente';
                } else if (row.estatus !== undefined && row.estatus !== null && row.estatus !== '') {
                    var intStatus = !isNaN(row.estatus) ? parseInt(row.estatus, 10) : null;
                    if (intStatus === 1) statusRaw = 'atendido';
                    else if (intStatus === 3) statusRaw = 'muerto';
                    else if (intStatus === 0) statusRaw = 'agendado';
                    else if (intStatus === 2) statusRaw = 'fantasma';
                    else statusRaw = row.estatus.toString();
                }

                var lower = statusRaw ? statusRaw.toLowerCase() : '';
                var display = lower ? lower.charAt(0).toUpperCase() + lower.slice(1) : '—';
                var cls = 'clie-status-default';
                if (lower === 'agendado') cls = 'clie-status-agendado';
                else if (lower === 'atendido') cls = 'clie-status-atendido';
                else if (lower === 'fantasma') cls = 'clie-status-fantasma';
                else if (lower === 'muerto') cls = 'clie-status-muerto';
                else if (lower === 'cliente') cls = 'clie-status-cliente';

                return {
                    display: display,
                    html: '<span class="clie-status-pill ' + cls + '">' + escapeHtml(display) + '</span>'
                };
            }

            function renderClientesTable() {
                var selected = $('#filterOrigenClientes').val();
                var filtered = clientes.filter(function (item) {
                    var cname = item.campaign_name || '';
                    var origenNorm = '';
                    if (cname && cname.toString().trim() !== '') {
                        var m = cname.toString().match(/^c(\d+)\./i);
                        if (m) origenNorm = 'c' + m[1];
                        else origenNorm = cname.toString().trim();
                    } else {
                        origenNorm = 'N/A';
                    }
                    if (!selected || selected === '') return true;
                    return origenNorm === selected;
                });

                if ($.fn.dataTable.isDataTable('#clientesTable')) {
                    $('#clientesTable').DataTable().clear().destroy();
                    $('#clientesTable tbody').empty();
                }

                var columns = [
                    {
                        data: null,
                        title: 'Nombre',
                        render: function (data, type) {
                            var name = data.cliente_names || '';
                            return type === 'display'
                                ? '<div class="clie-td-name">' + escapeHtml(name) + '</div>'
                                : name;
                        }
                    },
                    {
                        data: null,
                        title: '¿Dónde se casa?',
                        render: function (data, type) {
                            var location = (data.cliente_wedding_location || '').toString().trim();
                            var value = location && location !== 'N/A' ? location : '—';
                            return type === 'display' ? escapeHtml(value) : value;
                        }
                    },
                    {
                        data: null,
                        title: '¿Cuándo se casa?',
                        render: function (data, type) {
                            var raw = data.cliente_wedding_date || '';
                            if (type === 'sort' || type === 'type') {
                                return raw ? moment(raw).valueOf() || 0 : 0;
                            }
                            return escapeHtml(formatLeadDate(raw));
                        }
                    },
                    {
                        data: null,
                        title: 'Ciudad de origen del cliente',
                        render: function (data, type) {
                            var city = (data.city || data.cliente_city || '').toString().trim() || '—';
                            return type === 'display' ? escapeHtml(city) : city;
                        }
                    },
                    {
                        data: null,
                        title: '¿Cuándo llegó?',
                        render: function (data, type) {
                            var raw = data.created_time || data.cliente_submission_date || '';
                            var ts = raw ? moment(raw).valueOf() : 0;
                            if (type === 'sort' || type === 'type') return ts || 0;
                            return escapeHtml(formatCreatedTime(raw));
                        }
                    },
                    {
                        data: null,
                        title: 'Fecha que se cerró',
                        render: function (data, type) {
                            var raw = data.fecha_cambio_cliente || '';
                            var ts = raw ? moment(raw).valueOf() : 0;
                            if (type === 'sort' || type === 'type') return ts || 0;
                            if (!raw) return '—';
                            var dateValue = moment(raw).isValid() ? moment(raw).format('YYYY-MM-DD') : raw;
                            return '<button type="button" class="btn btn-link p-0 editar-fecha-cierre" data-id="' + escapeHtml(data.id || '') + '" data-fecha="' + escapeHtml(dateValue) + '">' + escapeHtml(formatCreatedTime(raw)) + '</button>';
                        }
                    },
                    {
                        data: null,
                        title: 'Monto de la venta',
                        render: function (data, type) {
                            var raw = (data.monto_venta || '').toString().trim();
                            var numeric = raw ? parseFloat(raw.toString().replace(/[^0-9.\-]/g, '')) : 0;
                            if (type === 'sort' || type === 'type') return isNaN(numeric) ? 0 : numeric;
                            if (!raw) return '—';
                            var n = isNaN(numeric) ? 0 : numeric;
                            var formatted = '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            return '<button type="button" class="btn btn-link p-0 editar-monto-venta" data-id="' + escapeHtml(data.id || '') + '" data-monto="' + escapeHtml(raw) + '">' + escapeHtml(formatted) + '</button>';
                        }
                    },
                    {
                        data: null,
                        title: 'Puntos',
                        render: function (data, type) {
                            var raw = getPointsDisplayValue(data);
                            var numeric = raw ? parseFloat(raw.toString().replace(/[^0-9.\-]/g, '')) : 0;
                            if (type === 'sort' || type === 'type') return isNaN(numeric) ? 0 : numeric;
                            return raw ? escapeHtml(raw) : '—';
                        }
                    },
                    {
                        data: null,
                        title: 'Abonos',
                        render: function (data, type) {
                            var abonos = 0;
                            if (Array.isArray(pagos) && data && data.id) {
                                abonos = pagos.reduce(function (sum, pago) {
                                    if (!pago) return sum;
                                    var pagoClienteId = pago.id_clie || pago.idcliente || pago.id_cliente;
                                    if (String(pagoClienteId) === String(data.id)) {
                                        var monto = parseFloat(String(pago.monto || pago.monto_pago || pago.amount || 0).replace(/[^0-9.\-]/g, ''));
                                        if (!isNaN(monto)) sum += monto;
                                    }
                                    return sum;
                                }, 0);
                            }
                            if (type === 'sort' || type === 'type') return isNaN(abonos) ? 0 : abonos;
                            return !isNaN(abonos) ? '$' + abonos.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
                        }
                    },
                    {
                        data: null,
                        title: 'Saldo pendiente',
                        render: function (data, type) {
                            var abonos = 0;
                            if (Array.isArray(pagos) && data && data.id) {
                                abonos = pagos.reduce(function (sum, pago) {
                                    if (!pago) return sum;
                                    var pagoClienteId = pago.id_clie || pago.idcliente || pago.id_cliente;
                                    if (String(pagoClienteId) === String(data.id)) {
                                        var monto = parseFloat(String(pago.monto || pago.monto_pago || pago.amount || 0).replace(/[^0-9.\-]/g, ''));
                                        if (!isNaN(monto)) sum += monto;
                                    }
                                    return sum;
                                }, 0);
                            }
                            var montoVenta = parseFloat(String(data.monto_venta || '').replace(/[^0-9.\-]/g, ''));
                            var saldo = (!isNaN(montoVenta) ? montoVenta : 0) - (!isNaN(abonos) ? abonos : 0);
                            if (type === 'sort' || type === 'type') return isNaN(saldo) ? 0 : saldo;
                            return !isNaN(saldo) ? '$' + saldo.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
                        }
                    },
                    {
                        data: null,
                        title: 'Comisión',
                        render: function (data, type) {
                            var raw = (data.comision !== null && data.comision !== undefined && data.comision !== '') ? data.comision.toString().trim() : '0';
                            var numeric = parseFloat(raw.replace(/[^0-9.\-]/g, ''));
                            if (type === 'sort' || type === 'type') return isNaN(numeric) ? 0 : numeric;
                            var n = isNaN(numeric) ? 0 : numeric;
                            var formatted = '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            var pagada = parseInt(data.comision_pagada) === 1;
                            var montoHtml = '<button type="button" class="btn btn-link p-0 comision-display" data-id="' + escapeHtml(data.id) + '" data-comision="' + escapeHtml(raw) + '">' + escapeHtml(formatted) + '</button>'
                                + '<div class="mt-1">';
                            if (pagada) {
                                montoHtml += '<span class="text-success fw-semibold comision-pagada-label" data-id="' + escapeHtml(data.id) + '">Pagado</span>';
                            } else {
                                montoHtml += '<button type="button" class="btn btn-outline-success btn-sm btn-pagar-comision" data-id="' + escapeHtml(data.id) + '">Marcar pagado</button>';
                            }
                            montoHtml += '</div>';
                            return montoHtml;
                        }
                    },
                    {
                        data: null,
                        title: 'Vendedor asignado',
                        render: function (data, type) {
                            var vendedorId = data.id_vendedor_asignado;
                            var vendedor = usuarios.find(function(u) { return u.id == vendedorId; });
                            var nombre = vendedor ? ((vendedor.nombre || '') + ' ' + (vendedor.apePat || '')).trim() : 'Sin asignar';
                            if (type !== 'display') return nombre;
                            return '<button type="button" class="btn btn-link p-0 cambiar-vendedor-tabla" data-id="' + escapeHtml(data.id) + '" data-vendedor-id="' + escapeHtml(vendedorId || '') + '">' + escapeHtml(nombre) + '</button>';
                        }
                    },
                    {
                        data: null,
                        title: '¿Qué se les vendió?',
                        render: function (data, type) {
                            var sold = (data.que_se_les_vendio || '').toString().trim();
                            var displayValue = sold || '—';
                            return type === 'display'
                                ? '<button type="button" class="btn btn-link p-0 editar-que-se-les-vendio" data-id="' + escapeHtml(data.id || '') + '" data-que="' + escapeHtml(sold) + '">' + escapeHtml(displayValue) + '</button>'
                                : sold;
                        }
                    },
                    {
                        data: null,
                        title: 'Sesión Oficial',
                        visible: false,
                        render: function (data, type) {
                            var value = (data.sesion_oficial || '').toString().trim();
                            value = value || '—';
                            return type === 'display' ? escapeHtml(value) : value;
                        }
                    }
                ];

                <?php if ($tipoUsuario != 4): ?>
                columns.push({
                    data: null,
                    title: 'Paquete',
                    render: function (data, type) {
                        var value = (data.paquete || '').toString().trim();
                        if (!value) return '—';
                        return type === 'display'
                            ? '<button type="button" class="btn btn-link p-0 editar-paquete" data-id="' + escapeHtml(data.id || '') + '" data-paquete="' + escapeHtml(value) + '">' + escapeHtml(value) + '</button>'
                            : value;
                    }
                });
                <?php endif; ?>

                columns.push(
                    {
                        data: null,
                        title: 'Compromiso',
                        visible: false,
                        render: function (data, type) {
                            var value = (data.compromiso_cliente || '').toString().trim() || '—';
                            return type === 'display' ? escapeHtml(value) : value;
                        }
                    },
                    {
                        data: null,
                        title: 'Técnica Cierre',
                        visible: false,
                        render: function (data, type) {
                            var value = (data.tecnica_cierre || '').toString().trim() || '—';
                            return type === 'display' ? escapeHtml(value) : value;
                        }
                    }
                );

                <?php if ($tipoUsuario != 4): ?>
                columns.push({
                    data: null,
                    title: 'Acciones',
                    orderable: false,
                    searchable: false,
                    render: function (data) {
                        return '<button class="btn btn-outline-primary btn-sm btn-edit-client mx-1 my-1" data-id="' + data.id + '">Editar</button>' +
                            '<button class="btn btn-primary btn-sm btn-vermas mx-1 my-1" data-id="' + data.id + '">Ver más info</button>';
                    }
                });
                <?php endif; ?>

                $('#clientesTable').DataTable({
                    data: filtered,
                    columns: columns,
                    responsive: true,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-MX.json'
                    },
                    order: [[5, 'desc']],
                    columnDefs: [
                        {
                            targets: '_all',
                            orderable: true
                        }
                    ]
                });
            }

            $('#filterOrigenClientes').off('change').on('change', function () { renderClientesTable(); });

            renderClientesTable();

            // ── Dashboard de Ventas y Comisiones ─────────────────────────────
            var pagosGlobal = pagos; // pagos ya viene de PHP

            function fmtMoney(n) {
                return '$' + (isNaN(n) ? 0 : n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function renderDashboard() {
                var desde    = $('#vdFechaDesde').val();
                var hasta    = $('#vdFechaHasta').val();
                var vendedor = $('#vdVendedor').val();
                var estatus  = $('#vdEstatus').val().toLowerCase();

                var filtered = clientes.filter(function (c) {
                    // Filtro vendedor
                    if (vendedor && String(c.id_vendedor_asignado) !== String(vendedor)) return false;
                    // Filtro estatus calendario
                    if (estatus) {
                        var st = (c.estatus || '').toString().toLowerCase();
                        if (st !== estatus) return false;
                    }
                    // Filtro fecha cierre
                    if (desde || hasta) {
                        var fcierre = c.fecha_cambio_cliente ? c.fecha_cambio_cliente.split(' ')[0] : '';
                        if (!fcierre) return false;
                        if (desde && fcierre < desde) return false;
                        if (hasta && fcierre > hasta) return false;
                    }
                    return true;
                });

                var totalVenta = 0, totalAbonos = 0, comPagada = 0, comPagadaCnt = 0, comPendiente = 0, comPendienteCnt = 0;

                filtered.forEach(function (c) {
                    var mv = parseFloat(String(c.monto_venta || '').replace(/[^0-9.\-]/g, ''));
                    if (!isNaN(mv)) totalVenta += mv;

                    // abonos de este cliente
                    var abonosCliente = pagosGlobal.reduce(function (sum, p) {
                        if (!p) return sum;
                        var pid = p.id_clie || p.idcliente || p.id_cliente;
                        if (String(pid) !== String(c.id)) return sum;
                        var m = parseFloat(String(p.monto || p.monto_pago || p.amount || 0).replace(/[^0-9.\-]/g, ''));
                        return sum + (isNaN(m) ? 0 : m);
                    }, 0);
                    totalAbonos += abonosCliente;

                    // comisión
                    var com = parseFloat(String(c.comision || '0').replace(/[^0-9.\-]/g, ''));
                    if (isNaN(com)) com = 0;
                    if (parseInt(c.comision_pagada) === 1) {
                        comPagada += com; comPagadaCnt++;
                    } else {
                        comPendiente += com; comPendienteCnt++;
                    }
                });

                var saldoPendiente = totalVenta - totalAbonos;

                $('#kpiTotalVenta').text(fmtMoney(totalVenta));
                $('#kpiTotalVentaCnt').text(filtered.length + ' registro' + (filtered.length !== 1 ? 's' : ''));
                $('#kpiAbonos').text(fmtMoney(totalAbonos));
                $('#kpiSaldoPendiente').text(fmtMoney(saldoPendiente));
                $('#kpiComisionPagada').text(fmtMoney(comPagada));
                $('#kpiComisionPagadaCnt').text(comPagadaCnt + ' liquidada' + (comPagadaCnt !== 1 ? 's' : ''));
                $('#kpiComisionPendiente').text(fmtMoney(comPendiente));
                $('#kpiComisionPendienteCnt').text(comPendienteCnt + ' por pagar');
            }

            renderDashboard();

            $('#vdBtnFiltrar').on('click', function () { renderDashboard(); });
            $('#vdBtnReset').on('click', function () {
                $('#vdFechaDesde, #vdFechaHasta').val('');
                $('#vdVendedor, #vdEstatus').val('');
                renderDashboard();
            });

            // ── Comisión: editar vía modal ────────────────────────────────────
            var editComisionModal = new bootstrap.Modal(document.getElementById('editComisionModal'));

            $(document).off('click', '.comision-display').on('click', '.comision-display', function () {
                var id = $(this).data('id');
                var val = $(this).data('comision') || '0';
                $('#editComisionId').val(id);
                $('#editComisionInput').val(val);
                editComisionModal.show();
            });

            $(document).off('click', '#saveComisionBtn').on('click', '#saveComisionBtn', function () {
                var id = $('#editComisionId').val();
                var newVal = $('#editComisionInput').val().trim();
                if (!id) return;
                $.ajax({
                    url: 'actualizar_lead.php',
                    method: 'POST',
                    dataType: 'json',
                    data: { id: id, field: 'comision', value: newVal },
                    success: function (resp) {
                        if (resp && resp.success) {
                            editComisionModal.hide();
                            var fmtVal = '$' + parseFloat(newVal || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            var $btn = $('.comision-display[data-id="' + id + '"]');
                            $btn.text(fmtVal).data('comision', newVal).attr('data-comision', newVal);
                            var idx = clientes.findIndex(function (c) { return String(c.id) === String(id); });
                            if (idx !== -1) clientes[idx].comision = newVal;
                        } else {
                            Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo guardar', 'error');
                        }
                    },
                    error: function () { Swal.fire('Error', 'Error al guardar la comisión', 'error'); }
                });
            });

            // ── Comisión: marcar como pagada ─────────────────────────────────
            $(document).off('click', '.btn-pagar-comision').on('click', '.btn-pagar-comision', function () {
                var $btn = $(this);
                var id = $btn.data('id');
                $.ajax({
                    url: 'actualizar_lead.php',
                    method: 'POST',
                    dataType: 'json',
                    data: { id: id, field: 'comision_pagada', value: 1 },
                    success: function (resp) {
                        if (resp && resp.success) {
                            $btn.replaceWith('<span class="text-success fw-semibold comision-pagada-label" data-id="' + id + '">Pagado</span>');
                            var idx = clientes.findIndex(function (c) { return String(c.id) === String(id); });
                            if (idx !== -1) clientes[idx].comision_pagada = '1';
                        } else {
                            Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo guardar', 'error');
                        }
                    },
                    error: function () { Swal.fire('Error', 'Error al marcar comisión como pagada', 'error'); }
                });
            });

            // ── Vendedor asignado: cambiar vía modal ──────────────────────────
            var editVendedorModal = new bootstrap.Modal(document.getElementById('editVendedorModal'));

            $(document).off('click', '.cambiar-vendedor-tabla').on('click', '.cambiar-vendedor-tabla', function () {
                var id = $(this).data('id');
                var currentVendedorId = $(this).data('vendedor-id');
                var vendedores = usuarios.filter(function (u) { return String(u.tipoUsu) === '1'; });
                var $sel = $('#editVendedorSelect');
                $sel.empty().append('<option value="">Sin asignar</option>');
                vendedores.forEach(function (v) {
                    var nombre = ((v.nombre || '') + ' ' + (v.apePat || '') + ' ' + (v.apeMat || '')).trim();
                    var selected = String(v.id) === String(currentVendedorId) ? ' selected' : '';
                    $sel.append('<option value="' + v.id + '"' + selected + '>' + nombre + '</option>');
                });
                $('#editVendedorId').val(id);
                editVendedorModal.show();
            });

            $(document).off('click', '#saveVendedorBtn').on('click', '#saveVendedorBtn', function () {
                var id = $('#editVendedorId').val();
                var nuevoVendedorId = $('#editVendedorSelect').val();
                if (!id) return;
                $.ajax({
                    url: 'cambiar_vendedor.php',
                    method: 'POST',
                    dataType: 'json',
                    data: { id_cliente: id, nuevo_asesor_id: nuevoVendedorId },
                    success: function (resp) {
                        if (resp && resp.success) {
                            editVendedorModal.hide();
                            var vendedor = usuarios.find(function (u) { return String(u.id) === String(nuevoVendedorId); });
                            var nombre = vendedor ? ((vendedor.nombre || '') + ' ' + (vendedor.apePat || '')).trim() : 'Sin asignar';
                            var $btn = $('.cambiar-vendedor-tabla[data-id="' + id + '"]');
                            $btn.text(nombre).data('vendedor-id', nuevoVendedorId).attr('data-vendedor-id', nuevoVendedorId);
                            var idx = clientes.findIndex(function (c) { return String(c.id) === String(id); });
                            if (idx !== -1) clientes[idx].id_vendedor_asignado = nuevoVendedorId;
                            Swal.fire({ icon: 'success', title: 'Guardado', timer: 1200, showConfirmButton: false });
                        } else {
                            Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo guardar', 'error');
                        }
                    },
                    error: function () { Swal.fire('Error', 'Error al cambiar el vendedor', 'error'); }
                });
            });

            // ── Modales ──────────────────────────────────────────────────────────

            var editCloseDateModal = new bootstrap.Modal(document.getElementById('editCloseDateModal'));

            $(document).off('click', '.editar-fecha-cierre').on('click', '.editar-fecha-cierre', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                var fecha = $(this).data('fecha') || '';
                $('#editCloseDateId').val(id);
                $('#editCloseDateInput').val(fecha);
                editCloseDateModal.show();
            });

            $(document).off('click', '#saveCloseDateBtn').on('click', '#saveCloseDateBtn', function (e) {
                e.preventDefault();
                var id = $('#editCloseDateId').val();
                var fecha = $('#editCloseDateInput').val().trim();
                if (!id) { Swal.fire('Error', 'ID de cliente no encontrado.', 'error'); return; }
                $.ajax({
                    url: 'actualizar_datos_cliente.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { idclie: id, updatedData: { fecha_cambio_cliente: fecha } },
                    success: function (response) {
                        if (response && response.success) {
                            Swal.fire('Guardado', 'Fecha que se cerró actualizada.', 'success');
                            editCloseDateModal.hide();
                            var table = $('#clientesTable').DataTable();
                            var row = table.row($('#clientesTable').find('.editar-fecha-cierre[data-id="' + id + '"]').closest('tr'));
                            if (row.node()) {
                                var rowData = row.data();
                                rowData.fecha_cambio_cliente = fecha;
                                row.data(rowData).invalidate().draw(false);
                            }
                        } else {
                            Swal.fire('Error', response.message || 'No se pudo actualizar la fecha.', 'error');
                        }
                    },
                    error: function () { Swal.fire('Error', 'Error en la petición AJAX.', 'error'); }
                });
            });

            var editAmountModal = new bootstrap.Modal(document.getElementById('editAmountModal'));

            $(document).off('click', '.editar-monto-venta').on('click', '.editar-monto-venta', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                var monto = $(this).data('monto') || '';
                $('#editAmountId').val(id);
                $('#editAmountInput').val(monto);
                editAmountModal.show();
            });

            $(document).off('click', '#saveAmountBtn').on('click', '#saveAmountBtn', function (e) {
                e.preventDefault();
                var id = $('#editAmountId').val();
                var monto = $('#editAmountInput').val().trim();
                if (!id) { Swal.fire('Error', 'ID de cliente no encontrado.', 'error'); return; }
                $.ajax({
                    url: 'actualizar_datos_cliente.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { idclie: id, updatedData: { monto_venta: monto } },
                    success: function (response) {
                        if (response && response.success) {
                            Swal.fire('Guardado', 'Monto de la venta actualizado.', 'success');
                            editAmountModal.hide();
                            var table = $('#clientesTable').DataTable();
                            var row = table.row($('#clientesTable').find('.editar-monto-venta[data-id="' + id + '"]').closest('tr'));
                            if (row.node()) {
                                var rowData = row.data();
                                rowData.monto_venta = monto;
                                row.data(rowData).invalidate().draw(false);
                            }
                        } else {
                            Swal.fire('Error', response.message || 'No se pudo actualizar el monto.', 'error');
                        }
                    },
                    error: function () { Swal.fire('Error', 'Error en la petición AJAX.', 'error'); }
                });
            });

            var editPaqueteModal = new bootstrap.Modal(document.getElementById('editPaqueteModal'));
            var editQueSeLesVendioModal = new bootstrap.Modal(document.getElementById('editQueSeLesVendioModal'));

            $(document).off('click', '.editar-paquete').on('click', '.editar-paquete', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                var paquete = $(this).data('paquete') || '';
                $('#editPaqueteId').val(id);
                $('#editPaqueteInput').empty().append('<option value="">Seleccione paquete</option>');
                paquetesData.forEach(function (paqueteItem) {
                    var nombre = paqueteItem.nombre || paqueteItem.que_se_les_vendio || '';
                    if (!nombre) return;
                    var selected = nombre === paquete ? ' selected' : '';
                    var que = paqueteItem.que_se_les_vendio || '';
                    $('#editPaqueteInput').append('<option value="' + escapeHtml(nombre) + '" data-que="' + escapeHtml(que) + '"' + selected + '>' + escapeHtml(nombre) + '</option>');
                });
                editPaqueteModal.show();
            });

            $(document).off('click', '#savePaqueteBtn').on('click', '#savePaqueteBtn', function (e) {
                e.preventDefault();
                var id = $('#editPaqueteId').val();
                var paquete = $('#editPaqueteInput').val().trim();
                var selectedOption = $('#editPaqueteInput option:selected');
                var queSeLesVendido = selectedOption.length ? selectedOption.data('que') || '' : '';
                if (!id) { Swal.fire('Error', 'ID de cliente no encontrado.', 'error'); return; }
                $.ajax({
                    url: 'actualizar_datos_cliente.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { idclie: id, updatedData: { paquete: paquete, que_se_les_vendio: queSeLesVendido } },
                    success: function (response) {
                        if (response && response.success) {
                            Swal.fire('Guardado', 'Paquete actualizado.', 'success');
                            editPaqueteModal.hide();
                            var table = $('#clientesTable').DataTable();
                            var row = table.row($('#clientesTable').find('.editar-paquete[data-id="' + id + '"]').closest('tr'));
                            if (row.node()) {
                                var rowData = row.data();
                                rowData.paquete = paquete;
                                rowData.que_se_les_vendio = queSeLesVendido;
                                row.data(rowData).invalidate().draw(false);
                            }
                        } else {
                            Swal.fire('Error', response.message || 'No se pudo actualizar el paquete.', 'error');
                        }
                    },
                    error: function () { Swal.fire('Error', 'Error en la petición AJAX.', 'error'); }
                });
            });

            $(document).off('click', '.editar-que-se-les-vendio').on('click', '.editar-que-se-les-vendio', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                var que = $(this).data('que') || '';
                $('#editQueSeLesVendioId').val(id);
                $('#editQueSeLesVendioInput').val(que);
                editQueSeLesVendioModal.show();
            });

            $(document).off('click', '#saveQueSeLesVendioBtn').on('click', '#saveQueSeLesVendioBtn', function (e) {
                e.preventDefault();
                var id = $('#editQueSeLesVendioId').val();
                var que = $('#editQueSeLesVendioInput').val().trim();
                if (!id) { Swal.fire('Error', 'ID de cliente no encontrado.', 'error'); return; }
                $.ajax({
                    url: 'actualizar_datos_cliente.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { idclie: id, updatedData: { que_se_les_vendio: que } },
                    success: function (response) {
                        if (response && response.success) {
                            Swal.fire('Guardado', 'Texto actualizado.', 'success');
                            editQueSeLesVendioModal.hide();
                            var table = $('#clientesTable').DataTable();
                            var row = table.row($('#clientesTable').find('.editar-que-se-les-vendio[data-id="' + id + '"]').closest('tr'));
                            if (row.node()) {
                                var rowData = row.data();
                                rowData.que_se_les_vendio = que;
                                row.data(rowData).invalidate().draw(false);
                            }
                        } else {
                            Swal.fire('Error', response.message || 'No se pudo actualizar el texto.', 'error');
                        }
                    },
                    error: function () { Swal.fire('Error', 'Error en la petición AJAX.', 'error'); }
                });
            });

            // ── CORRECCIÓN: click handler para btn-edit-client ────────────────
            $(document).off('click', '.btn-edit-client').on('click', '.btn-edit-client', function () {
                var id = $(this).data('id');

                Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                $.ajax({
                    url: 'actualizar_lead.php',
                    method: 'POST',
                    dataType: 'json',
                    data: { action: 'get_client', id: id },
                    success: function (resp) {
                        Swal.close();
                        if (!resp || !resp.success) {
                            Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo obtener el cliente', 'error');
                            return;
                        }

                        var client = resp.client || {};
                        $('#editClientId').val(client.id || '');
                        $('#edit_name').val(client.names || '');
                        $('#edit_email_address').val(client.email_address || '');
                        $('#edit_telephone').val(client.telephone || '');
                        $('#edit_wedding_date').val(toDateInput(client.wedding_date || ''));
                        $('#edit_campaign_name').val(client.campaign_name || '');
                        $('#edit_how_did_you_meet').val(client.how_did_you_meet || '');
                        $('#edit_hear_about_us').val(client.hear_about_us || '');
                        try {
                            $('#edit_desde_publicidad').val(typeof client.desde_publicidad !== 'undefined' ? String(client.desde_publicidad) : '0');
                        } catch (e) {}
                        $('#edit_wedding_location').val(client.wedding_location || '');
                        $('#edit_city').val(client.city || '');
                        $('#edit_id_vendedor_asignado').val(client.id_vendedor_asignado || '0');
                        $('#edit_first_contact_channel').val(client.first_contact_channel || '');

                        var selectedPack = client.que_se_les_vendio || client.paquete || '';
                        $('#edit_paquete').val(client.paquete || selectedPack || '');
                        $('#edit_que_se_les_vendio').val(client.que_se_les_vendio || selectedPack || '');
                        renderEditPackageList(selectedPack || '');

                        $('.ecm-category-btn').removeClass('active');
                        if (client.how_did_you_meet !== null && typeof client.how_did_you_meet !== 'undefined' && String(client.how_did_you_meet) !== '') {
                            $('.ecm-category-btn[data-cat="' + String(client.how_did_you_meet) + '"]').addClass('active');
                        }
                        if (String(client.how_did_you_meet) === '3') {
                            $('#edit_hear_about_us_wrapper').show();
                        } else {
                            $('#edit_hear_about_us_wrapper').hide();
                            $('#edit_hear_about_us').val('');
                        }

                        $('#edit_engagement').val(client.engagement || '');
                        $('.ecm-engagement-btn').removeClass('active');
                        if (client.engagement !== null && typeof client.engagement !== 'undefined' && String(client.engagement) !== '') {
                            $('.ecm-engagement-btn[data-eng="' + String(client.engagement) + '"]').addClass('active');
                        }

                        $('#edit_how_long_known_us').val(client.how_long_known_us || '');
                        $('.ecm-known-us-btn').removeClass('active');
                        if (client.how_long_known_us) {
                            $('.ecm-known-us-btn[data-val="' + String(client.how_long_known_us) + '"]').addClass('active');
                        }

                        $('#edit_tipo_cliente').val(String(client.tipo_cliente !== null && client.tipo_cliente !== undefined ? client.tipo_cliente : ''));
                        $('.ecm-tipo-cliente-btn').removeClass('active');
                        if (client.tipo_cliente !== null && client.tipo_cliente !== undefined && client.tipo_cliente !== '') {
                            $('.ecm-tipo-cliente-btn[data-tipo="' + String(client.tipo_cliente) + '"]').addClass('active');
                        }

                        $('#edit_puntos').val(client.puntos || '');
                        $('#edit_monto_venta').val(client.monto_venta || '');
                        $('#edit_fecha_cambio_cliente').val(toDateInput(client.fecha_cambio_cliente || ''));
                        $('#edit_submission_date').val(toDateTimeInput(client.submission_date || ''));
                        renderEditCommentsTab(client);

                        var dataTabTrigger = document.getElementById('edit-client-data-tab');
                        if (dataTabTrigger) {
                            bootstrap.Tab.getOrCreateInstance(dataTabTrigger).show();
                        }

                        bootstrap.Modal.getOrCreateInstance(document.getElementById('editClientModal')).show();
                    },
                    error: function () {
                        Swal.close();
                        Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                    }
                });
            });

        } // fin if response.data
    }, // fin success
    error: function () {
        console.error('Error al obtener clientes');
    }
}); // fin $.ajax obtener_clientes

        $(document).on('click', '.ecm-tipo-cliente-btn', function () {
            $('.ecm-tipo-cliente-btn').removeClass('active');
            $(this).addClass('active');
            $('#edit_tipo_cliente').val(String($(this).data('tipo')));
        });

        $('#saveEditClientBtn').on('click', function () {
            var id = parseInt($('#editClientId').val(), 10);
            if (!id) {
                Swal.fire('Error', 'Falta el ID del cliente', 'error');
                return;
            }

            var fields = {
                names: $('#edit_name').val().trim(),
                email_address: $('#edit_email_address').val().trim(),
                telephone: $('#edit_telephone').val().trim(),
                wedding_date: fromDateInputToMysql($('#edit_wedding_date').val().trim()),
                campaign_name: $('#edit_campaign_name').val().trim(),
                desde_publicidad: $('#edit_desde_publicidad').val().trim(),
                how_did_you_meet: $('#edit_how_did_you_meet').val().trim(),
                hear_about_us: $('#edit_hear_about_us').val().trim(),
                engagement: $('#edit_engagement').val().trim(),
                how_long_known_us: $('#edit_how_long_known_us').val().trim(),
                wedding_location: $('#edit_wedding_location').val().trim(),
                city: $('#edit_city').val().trim(),
                paquete: $('#edit_paquete').val().trim(),
                puntos: $('#edit_puntos').val().trim(),
                monto_venta: $('#edit_monto_venta').val().trim(),
                que_se_les_vendio: $('#edit_que_se_les_vendio').val().trim(),
                fecha_cambio_cliente: fromDateInputToMysql($('#edit_fecha_cambio_cliente').val().trim()),
                submission_date: fromDateTimeInputToMysql($('#edit_submission_date').val().trim()),
                id_vendedor_asignado: $('#edit_id_vendedor_asignado').val() || '0',
                first_contact_channel: $('#edit_first_contact_channel').val().trim(),
                tipo_cliente: $('#edit_tipo_cliente').val()
            };

            if (!['1', '2', '3'].includes(fields.how_did_you_meet)) {
                delete fields.how_did_you_meet;
            }

            Swal.fire({ title: 'Guardando cambios...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            $.ajax({
                url: 'actualizar_lead.php',
                method: 'POST',
                dataType: 'json',
                data: { action: 'update_client', id: id, fields: JSON.stringify(fields) },
                success: function (resp) {
                    Swal.close();
                    if (!resp || !resp.success) {
                        Swal.fire('Error', resp && resp.message ? resp.message : 'No se pudo guardar', 'error');
                        return;
                    }

                    var modal = bootstrap.Modal.getInstance(document.getElementById('editClientModal'));
                    if (modal) modal.hide();

                    Swal.fire({ title: 'Guardado', text: 'Cliente actualizado correctamente', icon: 'success', timer: 1500, showConfirmButton: false })
                        .then(function () { location.reload(); });
                },
                error: function () {
                    Swal.close();
                    Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                }
            });
        });

        (function () {
            var $monto = $('#edit_monto_venta');
            var $puntos = $('#edit_puntos');
            var timeoutId = null;

            function calcularPuntos() {
                var v = $monto.val();
                if (!v || v === '') {
                    $puntos.val('');
                    return;
                }
                var num = parseFloat(String(v).replace(/[^0-9.\-]/g, ''));
                if (isNaN(num) || num <= 0) {
                    $puntos.val('');
                    return;
                }
                $puntos.val(String(num / 77000));
            }

            $monto.on('input', function () {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(calcularPuntos, 250);
            });
            $monto.on('change', calcularPuntos);
        })();






        $('#clientesTable').on('click', '.nombre-asesor', function () {
            const asesorId = $(this).data('id');
            const idCliente = $(this).data('idcliente'); // 👈 obtener ID del cliente
            const asesor = usuarios.find(u => u.id == asesorId);

            const vendedores = usuarios.filter(u => u.tipoUsu == 1);

            let selectHTML = `<select id="nuevoAsesor" class="swal2-select">`;
            vendedores.forEach(v => {
                const nombreCompleto = `${v.nombre} ${v.apePat} ${v.apeMat}`;
                const selected = v.id == asesorId ? 'selected' : '';
                selectHTML += `<option value="${v.id}" ${selected}>${nombreCompleto}</option>`;
            });
            selectHTML += `</select>`;

            Swal.fire({
                title: 'Cambiar asesor',
                html: `
            <p>Asesor actual: <strong>${asesor ? asesor.nombre + ' ' + asesor.apePat + ' ' + asesor.apeMat : 'Sin asignar'}</strong></p>
            <label for="nuevoAsesor">Selecciona nuevo asesor:</label><br>
            ${selectHTML}
        `,
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    return $('#nuevoAsesor').val();
                }
            }).then(result => {
                if (result.isConfirmed) {
                    const nuevoAsesorId = result.value;

                    // 🔁 Enviar datos por AJAX a cambiar_vendedor.php
                    console.log("id_cliente", idCliente);
                    console.log("nuevo_asesor_id", nuevoAsesorId)

                    $.ajax({
                        url: 'cambiar_vendedor.php',
                        method: 'POST',
                        dataType: 'json', // ✅ Esperamos respuesta JSON
                        data: {
                            id_cliente: idCliente,
                            nuevo_asesor_id: nuevoAsesorId
                        },
                        success: function (response) {
                            console.log('Respuesta del servidor:', response);

                            if (response.success) {
                                Swal.fire('Actualizado', 'El asesor ha sido cambiado con éxito.', 'success')
                                    .then(() => {
                                        location.reload(); // o actualizar tabla dinámicamente
                                    });
                            } else {
                                Swal.fire('Error', response.message || 'Error en la actualización.', 'error');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Error AJAX:', status, error);
                            console.error('Respuesta completa:', xhr.responseText);

                            let mensaje = 'Ocurrió un error inesperado al cambiar el asesor.';
                            try {
                                const json = JSON.parse(xhr.responseText);
                                if (json.message) mensaje = json.message;
                            } catch (e) {
                                // Si no es JSON, se mantiene el mensaje por defecto
                            }

                            Swal.fire('Error', mensaje, 'error');
                        }
                    });

                }
            });
        });









        // Delegación del evento click para los botones "Ver más info"
        $('#clientesTable').on('click', '.btn-vermas', function () {
            var id = $(this).data('id');
            // Buscar el cliente de forma segura
            let cliente = clientes.find(item => item.id == id);
            if (!cliente) {
                console.error('Cliente no encontrado para id', id);
                Swal.fire('Error', 'Cliente no encontrado', 'error');
                return; // Salir si no encontramos el cliente
            }

            let id_vendedor_asignado = cliente.id_vendedor_asignado;
            // Buscar el vendedor de forma segura
            let vendedor = usuarios.find(usuario => usuario.id == id_vendedor_asignado);

            var idusu = id_vendedor_asignado;
            var idclie = cliente.id;
            let ismanual = cliente.manual;
            console.log("cliente ", cliente)
            var enlace_meet = cliente.enlace_meet;
            var country_code = cliente.country_code;
            var nombre = cliente.nombre;
            var telefono = cliente.telefono;
            var email = cliente.cliente_email_address;
            var fecha = cliente.fecha;
            var hora = cliente.hora;
            // Usar un fallback si no existe vendedor o está en modo manual
            var asesor = (ismanual == "1" || !vendedor) ? "Sin asesor asignado" : ((vendedor.nombre || '') + " " + (vendedor.apePat || '')).trim();
            var nota = cliente.nota;
            var titulo = cliente.titulo;
            var cliente_names = cliente.cliente_names;
            var cliente_telefono = cliente.cliente_telefono;
            var cliente_email_address = cliente.cliente_email_address;
            var cliente_additional_details = cliente.cliente_additional_details;
            var cliente_couple_activities = cliente.cliente_couple_activities;
            var cliente_date_appointment = cliente.cliente_date_appointment;
            var cliente_favorite_movie_or_song = cliente.cliente_favorite_movie_or_song;
            var cliente_guests_count = cliente.cliente_guests_count;
            var cliente_hear_about_us = cliente.cliente_hear_about_us;
            var cliente_how_did_you_meet = cliente.cliente_how_did_you_meet;
            var cliente_instagram_handle = cliente.cliente_instagram_handle;
            var cliente_look_preference = cliente.cliente_look_preference;
            var cliente_service_interest = cliente.cliente_service_interest;
            var cliente_submission_date = cliente.cliente_submission_date;
            var cliente_time_appointment = cliente.cliente_time_appointment;
            var cliente_wedding_date = cliente.cliente_wedding_date;
            var cliente_wedding_location = cliente.cliente_wedding_location;
            var cliente_wedding_planner = cliente.cliente_wedding_planner;
            var comentario_agenda_inicial = cliente.comentario_agenda_inicial;
            var comentario_agenda_cliente = cliente.comentario_agenda_cliente;
            var comentario_wp_inicial = cliente.comentario_wp_inicial;
            var comentario_wp_cliente = cliente.comentario_wp_cliente;

            function escapeHtml(value) {
                return $('<div>').text(value == null ? '' : String(value)).html();
            }

            var formattedDateFecha = (fecha && fecha.trim()) ? moment(fecha).format('DD-MM-YYYY') : "No disponible";
            var formattedDateHora = (hora && hora.trim()) ? moment(hora, 'HH:mm:ss').format('hh:mm a') : "No disponible";

            let telefonoCliente = country_code + " " + cliente_telefono;



            //obtener los pagos del cliente 
            let pagos = <?php echo json_encode($pagos); ?>;
            let galerias = <?php echo json_encode($galerias); ?>;
            let contratos = <?php echo json_encode($contratos); ?>;
            let pagosCliente = pagos.filter((pago => pago.id_clie == idclie));
            let galeriasCliente = galerias.filter((galeria => galeria.id_clie == idclie));
            let nextstape = <?php echo json_encode($nextstape); ?>;
            let nextstapesCliente = nextstape.filter(item => item.id_cliente == idclie);



            var htmlContent = '<div>';
            htmlContent += '<div class="">';
            htmlContent += '<div class="d-flex flex-row justify-content-between"><div><h4><strong>Datos del cliente</strong></h4></div></div>';

            // Iniciar el contenedor del grid
            htmlContent += '<div class="row mt-3">';
            moment.locale('es');
            var eventoFecha = moment(cliente_wedding_date).format('dddd, D [de] MMMM [de] YYYY');
            var eventoFechaInput = moment(cliente_wedding_date).format('YYYY-MM-DD'); // Formato para input tipo date

            // Datos del cliente
            const datosCliente = [
                { label: 'Nombre del cliente:', value: cliente_names },
                { label: 'Teléfono:', value: telefonoCliente },
                { label: 'Correo:', value: cliente_email_address },
                { label: 'Fecha del evento:', value: eventoFecha, editable: true },  // Añadido flag editable
            ]

            // Iterar sobre los datos y crear las columnas del grid
            datosCliente.forEach(dato => {
                htmlContent += '<div class="col-md-3 mb-2">'; // 4 columnas por fila en pantallas medianas y grandes
                htmlContent += '<div class="d-flex flex-column">';
                htmlContent += `<p class="m-0"><strong>${dato.label}</strong></p>`;

                // Si el campo es editable, agregar "Editar" y un input para cambiar la fecha
                if (dato.editable) {
                    htmlContent += `<p class="m-0" id="eventoFechaDisplay">${dato.value} <span class="text-primary" style="cursor: pointer;" id="editarFecha">Editar</span></p>`;
                    htmlContent += '<div id="inputFecha" style="display: none;">';
                    htmlContent += `<input type="date" id="fechaEventoInput" class="form-control" value="${eventoFechaInput}">`;  // Asignamos la fecha al input
                    htmlContent += '<button class="btn btn-primary mt-2" id="actualizarFechaBtn">Actualizar fecha de evento</button>';
                    htmlContent += '</div>';
                } else {
                    htmlContent += `<p class="m-0">${dato.value}</p>`;
                }

                htmlContent += '</div>';
                htmlContent += '</div>';
            });

            // Cerrar el contenedor del grid
            htmlContent += '</div>';
            htmlContent += '</div><hr>';
            htmlContent += '</div>';

            var tieneComentarioWpInicial = comentario_wp_inicial && comentario_wp_inicial.toString().trim() !== '';
            var tieneComentarioWpCliente = comentario_wp_cliente && comentario_wp_cliente.toString().trim() !== '';

            if (tieneComentarioWpInicial || tieneComentarioWpCliente) {
                htmlContent += '<div class="mb-4">';
                htmlContent += '<div class="d-flex flex-row justify-content-between"><div><h4><strong>Comentarios desde Wedding Planner</strong></h4></div></div>';
                htmlContent += '<div class="row mt-3">';

                if (tieneComentarioWpInicial) {
                    htmlContent += '<div class="col-md-6 mb-3">';
                    htmlContent += '<div class="d-flex flex-column">';
                    htmlContent += '<p class="m-0"><strong>Comentario al pasar a atendido</strong></p>';
                    htmlContent += '<p class="m-0">' + escapeHtml(comentario_wp_inicial) + '</p>';
                    htmlContent += '</div>';
                    htmlContent += '</div>';
                }

                if (tieneComentarioWpCliente) {
                    htmlContent += '<div class="col-md-6 mb-3">';
                    htmlContent += '<div class="d-flex flex-column">';
                    htmlContent += '<p class="m-0"><strong>Comentario al pasar a cliente</strong></p>';
                    htmlContent += '<p class="m-0">' + escapeHtml(comentario_wp_cliente) + '</p>';
                    htmlContent += '</div>';
                    htmlContent += '</div>';
                }

                htmlContent += '</div>';
                htmlContent += '<hr>';
                htmlContent += '</div>';
            }

            var tieneComentarioAgendaInicial = comentario_agenda_inicial && comentario_agenda_inicial.toString().trim() !== '';
            var tieneComentarioAgendaCliente = comentario_agenda_cliente && comentario_agenda_cliente.toString().trim() !== '';

            if (tieneComentarioAgendaInicial || tieneComentarioAgendaCliente) {
                htmlContent += '<div class="mb-4">';
                htmlContent += '<div class="d-flex flex-row justify-content-between"><div><h4><strong>Comentarios desde agenda</strong></h4></div></div>';
                htmlContent += '<div class="row mt-3">';

                if (tieneComentarioAgendaInicial) {
                    htmlContent += '<div class="col-md-6 mb-3">';
                    htmlContent += '<div class="d-flex flex-column">';
                    htmlContent += '<p class="m-0"><strong>Comentario al pasar a atendido</strong></p>';
                    htmlContent += '<p class="m-0">' + escapeHtml(comentario_agenda_inicial) + '</p>';
                    htmlContent += '</div>';
                    htmlContent += '</div>';
                }

                if (tieneComentarioAgendaCliente) {
                    htmlContent += '<div class="col-md-6 mb-3">';
                    htmlContent += '<div class="d-flex flex-column">';
                    htmlContent += '<p class="m-0"><strong>Comentario al pasar a cliente</strong></p>';
                    htmlContent += '<p class="m-0">' + escapeHtml(comentario_agenda_cliente) + '</p>';
                    htmlContent += '</div>';
                    htmlContent += '</div>';
                }

                htmlContent += '</div>';
                htmlContent += '<hr>';
                htmlContent += '</div>';
            }




            // TABS
            //tabs admin
            let tabPagos = ` <div class="tab-pane fade" id="pills-pagos" role="tabpanel" aria-labelledby="pills-pagos-tab">`;
            let tabContratos = `<div class="tab-pane fade" id="pills-contrato" role="tabpanel" aria-labelledby="pills-contrato-tab">`;
            let tabGalerias = ` <div class="tab-pane fade" id="pills-galeria" role="tabpanel" aria-labelledby="pills-galeria-tab">`;
            let tabNextstep = ` <div class="tab-pane fade" id="pills-nextstape" role="tabpanel" aria-labelledby="pills-nextstape-tab">`;

            if (tipoUsuario == '0') {
                tabPagos = ` <div class="tab-pane fade show active" id="pills-pagos" role="tabpanel" aria-labelledby="pills-pagos-tab">`;
                htmlContent += `<div class="d-flex justify-content-between aling-items-center">
                   <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                      <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pills-pagos-tab" data-bs-toggle="pill" data-bs-target="#pills-pagos" type="button" role="tab" aria-controls="pills-pagos" aria-selected="true">Pagos</button>
                      </li>
                     
                      <li class="nav-item" role="presentation">
                        <button class="nav-link " id="pills-contrato-tab" data-bs-toggle="pill" data-bs-target="#pills-contrato" type="button" role="tab" aria-controls="pills-contrato" aria-selected="false">Contrato</button>
                      </li>
                       <li class="nav-item" role="presentation">
                        <button class="nav-link " id="pills-cuestionario-tab" data-bs-toggle="pill" data-bs-target="#pills-cuestionario" type="button" role="tab" aria-controls="pills-cuestionario" aria-selected="false">Cuestionario</button>
                      </li>
                       <li class="nav-item" role="presentation">
                        <button class="nav-link " id="pills-galeria-tab" data-bs-toggle="pill" data-bs-target="#pills-galeria" type="button" role="tab" aria-controls="pills-galeria" aria-selected="false">Galería</button>
                      </li>
                       <li class="nav-item" role="presentation">
                        <button class="nav-link " id="pills-adicional-tab" data-bs-toggle="pill" data-bs-target="#pills-adicional" type="button" role="tab" aria-controls="pills-adicional" aria-selected="false">Datos adicionales</button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link " id="pills-nextstape-tab" data-bs-toggle="pill" data-bs-target="#pills-nextstape" type="button" role="tab" aria-controls="pills-adicional" aria-selected="false">Next Step</button>
                      </li>
                  </ul>
                </div>`;

            } else if (tipoUsuario == '1') {
                // tabs vendedor
                tabContratos = `<div class="tab-pane fade show active" id="pills-contrato" role="tabpanel" aria-labelledby="pills-contrato-tab">`;
                tabPagos = ` <div class="tab-pane fade" id="pills-pagos" role="tabpanel" aria-labelledby="pills-pagos-tab">`;
                tabNextstep = ` <div class="tab-pane fade" id="pills-nextstape" role="tabpanel" aria-labelledby="pills-nextstape-tab">`;

                htmlContent += `<div class="d-flex justify-content-between aling-items-center">
                   <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                     <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pills-pagos-tab" data-bs-toggle="pill" data-bs-target="#pills-pagos" type="button" role="tab" aria-controls="pills-pagos" aria-selected="false">Pagos</button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link  active" id="pills-contrato-tab" data-bs-toggle="pill" data-bs-target="#pills-contrato" type="button" role="tab" aria-controls="pills-contrato" aria-selected="true">Contrato</button>
                      </li>
                       <li class="nav-item" role="presentation">
                        <button class="nav-link " id="pills-cuestionario-tab" data-bs-toggle="pill" data-bs-target="#pills-cuestionario" type="button" role="tab" aria-controls="pills-cuestionario" aria-selected="false">Cuestionario</button>
                      </li>
                       <li class="nav-item" role="presentation">
                        <button class="nav-link " id="pills-adicional-tab" data-bs-toggle="pill" data-bs-target="#pills-adicional" type="button" role="tab" aria-controls="pills-adicional" aria-selected="false">Datos adicionales</button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link " id="pills-nextstape-tab" data-bs-toggle="pill" data-bs-target="#pills-nextstape" type="button" role="tab" aria-controls="pills-nextstape" aria-selected="false">Next Step</button>
                      </li>
                  </ul>
                </div>`;

                // Mostrar contenido de Pagos y Next Step para tipoUsuario 1
                htmlContent += `\n <div class=\"tab-content\" id=\"pills-tabContent\">\n ` + tabPagos + `\n  <div class=\"my-2\">\n    <div class=\"d-flex flex-row justify-content-between\">\n      <h4><strong>Pagos</strong></h4>\n      <div class=\"col-md-auto\">\n        <div class=\"input-group mb-3 items-aling-center\">\n         <select class=\"form-control \" id=\"cuentaTransferenciaSelect\">\n            <option value=\"\">Seleccione una cuenta</option>\n            <?php foreach ($datos_cuentas as $cuenta): ?>\n              <option value=\"<?= $cuenta['id'] ?>\">\n                <?= htmlspecialchars($cuenta['nombre_titular']) ?> - <?= htmlspecialchars($cuenta['nombre_banco']) ?>\n              </option>\n            <?php endforeach; ?>\n          </select>\n          <input type=\"number\" class=\"form-control\" id=\"montoInput\" placeholder=\"Ingrese el monto\">\n          <input type=\"text\" class=\"form-control\" id=\"conceptoInput\" placeholder=\"Ingrese el concepto\">\n          <select class=\"form-control\" id=\"currencyInput\">\n            <option value=\"\">Seleccione la moneda</option>\n            <option value=\"mxn\">MXN</option>\n            <option value=\"usd\">USD</option>\n          </select>\n          <div class=\"input-group-append d-flex items-aling-center\">\n            <button class=\"btn btn-outline-secondary btn-sm\" type=\"button\" id=\"agregarMonto\">Agregar Pago</button>\n          </div>\n        </div>\n      </div>\n    </div>\n    <table class=\"table\" id=\"tablaPagos\">\n      <thead>\n        <tr>\n          <th scope=\"col\">ID Pago</th>\n          <th scope=\"col\">Monto</th>\n          <th scope=\"col\">Fecha Asignación</th>\n          <th scope=\"col\">Hora Asignación</th>\n          <th scope=\"col\">Fecha Pago</th>\n          <th scope=\"col\">Hora Pago</th>\n          <th scope=\"col\">Concepto</th>\n          <th scope=\"col\">Forma de Pago</th>\n          <th scope=\"col\">Estatus</th>\n          <th scope=\"col\">Acciones</th>\n        </tr>\n      </thead>\n      <tbody></tbody>\n    </table>\n    <hr class=\"mt-3\">\n  </div>\n </div>\n`;

                htmlContent += `\n   ${tabNextstep}\n  <div class=\"my-2\">\n    <div class=\"d-flex flex-row justify-content-between\">\n      <h4><strong>Galería</strong></h4>\n      <div class=\"col-md-auto\">\n    </div>\n      </div>\n    \n    <table class=\"table\" id=\"tablaNext\">\n      <thead>\n        <tr>\n        \n          <th scope=\"col\">ID Nextstape</th>\n          <th scope=\"col\">ID Cliente</th>\n          <th scope=\"col\">Fecha Registro</th>\n          <th scope=\"col\">Hora Registro</th>\n          <th scope=\"col\">Nombre Archivo</th>\n          <th scope=\"col\">Acciones</th>\n        </tr>\n      </thead>\n      <tbody></tbody>\n    </table>\n    <hr class=\"mt-3\">\n  </div>\n </div>\n`;

            }
            else if (tipoUsuario == '2') {
                tabGalerias = ` <div class="tab-pane fade show active" id="pills-galeria" role="tabpanel" aria-labelledby="pills-galeria-tab">`;
                // tabs galeria
                htmlContent += `<div class="d-flex justify-content-between aling-items-center">
                   <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                    
                       <li class="nav-item" role="presentation">
                        <button class="nav-link  active" id="pills-galeria-tab" data-bs-toggle="pill" data-bs-target="#pills-galeria" type="button" role="tab" aria-controls="pills-galeria" aria-selected="true">Galería</button>
                      </li>
                      
                  </ul>
                </div>`;

            } else if (tipoUsuario == '3') {
                // tabs pagos
                tabPagos = ` <div class="tab-pane fade show active" id="pills-pagos" role="tabpanel" aria-labelledby="pills-pagos-tab">`;

                htmlContent += `<div class="d-flex justify-content-between aling-items-center">
                   <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                    
                        <li class="nav-item" role="presentation">
                        <button class="nav-link show active" id="pills-pagos-tab" data-bs-toggle="pill" data-bs-target="#pills-pagos" type="button" role="tab" aria-controls="pills-pagos" aria-selected="true">Pagos</button>
                      </li>
                      
                  </ul>
                </div>`;

            }
            // PAGOS

            // Valores actuales de los campos nuevos (si existen)
            let paqueteVal = (cliente.paquete !== undefined && cliente.paquete !== null) ? cliente.paquete : '';
            let puntosVal = (cliente.puntos !== undefined && cliente.puntos !== null) ? cliente.puntos : '';
            let montoVentaVal = (cliente.monto_venta !== undefined && cliente.monto_venta !== null) ? cliente.monto_venta : '';
            let queSeLesVendidoVal = (cliente.que_se_les_vendio !== undefined && cliente.que_se_les_vendio !== null) ? cliente.que_se_les_vendio : '';

            htmlContent += `
 <div class="tab-content" id="pills-tabContent">
 `+ tabPagos + `
    <div class="my-2">
        <div class="d-flex flex-row justify-content-between">
            <h4><strong>Pagos</strong></h4>
            <div class="col-md-auto">
                <div class="input-group mb-3 items-aling-center">
                 <select class="form-control " id="cuentaTransferenciaSelect">
                        <option value="">Seleccione una cuenta</option>
                        <?php foreach ($datos_cuentas as $cuenta): ?>
                            <option value="<?= $cuenta['id'] ?>">
                                <?= htmlspecialchars($cuenta['nombre_titular']) ?> - <?= htmlspecialchars($cuenta['nombre_banco']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" class="form-control" id="montoInput" placeholder="Ingrese el monto">
                    <input type="text" class="form-control" id="conceptoInput" placeholder="Ingrese el concepto">
                    <select class="form-control" id="currencyInput">
                        <option value="">Seleccione la moneda</option>
                        <option value="mxn">MXN</option>
                        <option value="usd">USD</option>
                    </select>
                    <div class="input-group-append d-flex items-aling-center">
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="agregarMonto">Agregar Pago</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mostrar / editar campos añadidos a contact_form -->
        <div id="pagosInfo" class="mb-3 mt-4">
            <div class="row">
                <div class="col-md-3">
                    <p class="m-0"><strong>Paquete</strong></p>
                    ${paqueteVal ? `<p class="m-0" id="paqueteDisplay">${paqueteVal} <button class="btn btn-sm btn-link edit-pago" data-field="paquete">Editar</button></p>` : `<select id="paqueteInput" class="form-control"><option value="">Seleccione paquete</option><option value="1 Foto">1 Foto</option><option value="2 Foto">2 Foto</option><option value="2 Foto 1 Video">2 Foto 1 Video</option><option value="2 Foto y 2 Video">2 Foto y 2 Video</option><option value="3 Foto y 2 Video">3 Foto y 2 Video</option><option value="Mini Evento">Mini Evento</option><option value="1 Foto 1 Video">1 Foto 1 Video</option><option value="3 foto">3 foto</option><option value="1 Video">1 Video</option><option value="2 video">2 video</option></select>`}
                </div>
                <div class="col-md-3">
                    <p class="m-0"><strong>Puntos</strong></p>
                    ${puntosVal !== '' && puntosVal !== 0 ? `<p class="m-0" id="puntosDisplay">${puntosVal} <button class="btn btn-sm btn-link edit-pago" data-field="puntos">Editar</button></p>` : `<input type="number" id="puntosInput" class="form-control" value="${puntosVal}">`}
                </div>
                <div class="col-md-3">
                    <p class="m-0"><strong>Monto de venta</strong></p>
                    ${montoVentaVal !== '' && montoVentaVal !== 0 ? `<p class="m-0" id="montoVentaDisplay">${montoVentaVal} <button class="btn btn-sm btn-link edit-pago" data-field="monto_venta">Editar</button></p>` : `<input type="number" step="0.01" id="montoVentaInput" class="form-control" value="${montoVentaVal}">`}
                </div>
                <div class="col-md-3">
                    <p class="m-0"><strong>¿Qué se les vendió?</strong></p>
                    ${queSeLesVendidoVal ? `<p class="m-0" id="queSeLesVendioDisplay">${queSeLesVendidoVal} <button class="btn btn-sm btn-link edit-pago" data-field="que_se_les_vendio">Editar</button></p>` : `<input type="text" id="queSeLesVendioInput" class="form-control" value="${queSeLesVendidoVal}">`}
                </div>
                
            </div>
            <div class="row">
            <div class="col-md-3 mt-3">
                    <p class="m-0"><strong>Fecha que se cerró</strong></p>
                    ${cliente.fecha_cambio_cliente ? `<p class="m-0" id="fechaCambioDisplay">${moment(cliente.fecha_cambio_cliente).format('DD/MM/YYYY')} <button class="btn btn-sm btn-link edit-pago" data-field="fecha_cambio_cliente">Editar</button></p>` : `<input type="date" id="fechaCambioInput" class="form-control" value="${cliente.fecha_cambio_cliente ? cliente.fecha_cambio_cliente.split(' ')[0] : ''}">`}
                </div>
            </div>
            <div class="mt-2">
                <button class="btn btn-primary btn-sm" id="guardarPagosBtn" data-id="${idclie}" style="display:none;">Guardar datos de paquete</button>
            </div>
        </div>

        <table class="table" id="tablaPagos">
            <thead>
                <tr>
                    <th scope="col">ID Pago</th>
                    <th scope="col">Monto</th>
                    <th scope="col">Fecha Asignación</th>
                    <th scope="col">Hora Asignación</th>
                    <th scope="col">Fecha Pago</th>
                    <th scope="col">Hora Pago</th>
                    <th scope="col">Concepto</th>
                    <th scope="col">Forma de Pago</th>
                    <th scope="col">Estatus</th>
                    <th scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <hr class="mt-3">
    </div>
 </div>
`;

            // Netxstape
            htmlContent += `
   ${tabNextstep}
  <div class="my-2">
    <div class="d-flex flex-row justify-content-between">
      <h4><strong>Galería</strong></h4>
      <div class="col-md-auto">
    </div>
      </div>
    
    <table class="table" id="tablaNext">
      <thead>
        <tr>
        
          <th scope="col">ID Nextstape</th>
          <th scope="col">ID Cliente</th>
          <th scope="col">Fecha Registro</th>
          <th scope="col">Hora Registro</th>
          <th scope="col">Nombre Archivo</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <hr class="mt-3">
  </div>
 </div>
`;






            // GALERIA
            htmlContent += `
   ${tabGalerias}
  <div class="my-2">
    <div class="d-flex flex-row justify-content-between">
      <h4><strong>Galería</strong></h4>
      <div class="col-md-auto">
        <div class="input-group mb-3 items-aling-center">
          <div class="d-flex flex-column" style="margin-right:10px">
          <label>Fecha de registro</label>
          <input type="date" class="form-control" id="fechaRegistroGalInput" placeholder="Fecha de registro">
          </div>
           <div class="d-flex flex-column" style="margin-right:10px">
          <label>Fecha de vencimiento</label>
          <input type="date" class="form-control" id="fechaVencimientoGalInput" placeholder="Fecha de vencimiento">
          </div>
          <div class=" d-flex items-aling-end">
          
            <button class="btn btn-outline-secondary btn-sm" type="button" id="agregarGaleriaManual" style="margin-top: 25px;">Agregar Galería</button>
          </div>
        </div>
    </div>
      </div>
    
    <table class="table" id="tablaGaleria">
      <thead>
        <tr>
          <th scope="col">ID Galeria</th>
          <th scope="col">Fecha Registro</th>
          <th scope="col">Hora Registro</th>
          <th scope="col">Fecha Vencimiento</th>
          <th scope="col">Estatus</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <hr class="mt-3">
  </div>
 </div>
`;

            // CONTRATO

            // Filtramos los contratos del cliente
            let contratosCliente = contratos.filter(contrato => contrato.id_clie == idclie);

            // Aquí cambiamos el contenido del HTML
            htmlContent += `
    ${tabContratos}
        <div class="my-2">
            <div class="d-flex flex-row justify-content-between">
                <h4><strong>Contrato</strong></h4>
                <div class="col-md-auto">
                    <div class="input-group mb-3 align-items-end" id="divInputsContrato">
                        <div class="form-group">
                            <!-- Input de archivo para subir el contrato -->
                           <div class="d-flex flex-column">
                           <label id="labelContrato" >Mandar contrato   <br>
                           Envía el contrato al cliente para su revisión y firma.</label>
                           <input type="file" class="form-control" id="contratoCliente" name="contratoCliente">
                           </div>
                        </div>
                        <br>
                          <div class="input-group-append d-flex align-items-center">
                            <!-- Aquí creamos dos botones y mostramos uno u otro dependiendo del estado del array -->
                            ${contratosCliente.length === 0
                    ? '<button class="btn btn-outline-secondary " type="button" id="subirContrato"  data-nuevo="1">Subir Contrato</button>'
                    : contratosCliente[0].estatus == "1" || contratosCliente[0].estatus == "2"
                        ? `<button class="btn btn-outline-secondary " type="button" id="subirContrato" data-id="${contratosCliente[0].id}" data-nuevo="0">Subir Contrato</button>`
                        : ''
                }
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de contratos -->
            <table class="table" id="tablaContrato">
                <thead>
                    <tr>
                        <th scope="col">ID Contrato</th>
                        <th scope="col">Fecha Asignación</th>
                        <th scope="col">Hora Asignación</th>
                        <th scope="col">Fecha Firma</th>
                        <th scope="col">Hora Firma</th>
                        <th scope="col">Estatus</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                   
                </tbody>
            </table>

            <hr class="mt-3">
        </div>
    </div>
`;



            // CUESTIONARIO
            // Aquí cambiamos el contenido del HTML

            let respuestasCuestionarioCliente = respuestas_cuestionario.filter((respuesta) => respuesta.id_clie == idclie);

            htmlContent += `
    <div class="tab-pane fade" id="pills-cuestionario" role="tabpanel" aria-labelledby="pills-cuestionario-tab">
  <div class="my-2">
    <div class="d-flex flex-row justify-content-between align-items-center mb-3">
      <h4><strong>Cuestionario</strong></h4>
      <div class="d-flex align-items-end" id="divInputsCuestionario">
        <!-- Select del cuestionario -->
        <select class="form-control me-2" id="cuestionarioSelect" style="max-width: 300px;">
          <option value="">Seleccione un cuestionario</option>
          <?php foreach ($cuestionarios as $cuestionario): ?>
            <option value="<?= $cuestionario['id'] ?>">
              <?= htmlspecialchars($cuestionario['titulo']) ?> - 
              <?= $cuestionario['idioma'] == 0 ? 'Español' : 'Inglés' ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Botón de enviar -->
        <button class="btn btn-outline-secondary" type="button" id="mandarCuestionario" data-idcuestionario="0" data-enviado="0">
          Mandar cuestionario
        </button>
      </div>
    </div>

    <!-- Tabla de cuestionario -->
    <table class="table" id="tablaCuestionario">
      <thead>
        <tr>
          <th scope="col">ID</th>
          <th scope="col">Fecha del Evento</th>
          <th scope="col">Fecha Respuestas</th>
          <th scope="col">Hora Respuestas</th>
          <th scope="col">Estatus</th>
          <th scope="col">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <!-- Aquí se llenarán las filas con JS o PHP -->
      </tbody>
`;

            if (respuestasCuestionarioCliente.length === 0) {
                htmlContent += `
        <tr id="divCuestionarioNoEnviado">
            <td colspan="7" class="text-center"><p class="m-2">Aún no se han mandado el cuestionario</p></td>
        </tr>
    `;
            } else {
                // Si hay respuestas, mostramos cada una en una fila de la tabla
                respuestasCuestionarioCliente.forEach(respuesta => {
                    console.log("respuestasCuestionarioCliente ", respuesta)
                    if (respuesta.estatus == "1") {
                        htmlContent += `
            <tr>
                <td>${respuesta.id}</td>
                <td>${cliente_wedding_date}</td>
                <td>Pendiente</td>
                <td>Pendiente</td>
                <td>Enviado</td>
                <td>
                   <button  class="btn btn-outline-secondary btn-sm" type="button"  data-enviado="${1}" data-idrescuestionario="${respuesta.id}" data-idcuestionario="${respuesta.id_cuestionario}" id="mandarCuestionario">Mandar cuestionario</button>
                   
                </td>
            </tr>
        `;
                    } else {
                        if (respuesta.estatus == "2") {
                            let fechaRespuesta = moment(respuesta.fecha).format('DD-MM-YYYY');
                            let horaRespuesta = moment(respuesta.hora, 'HH:mm:ss').format('hh:mm a');
                            htmlContent += `
            <tr>
                <td>${respuesta.id}</td>
                  <td>${cliente_wedding_date}</td>

                <td>${fechaRespuesta}</td>
                <td>${horaRespuesta}</td>
                <td>Respondido</td>
                <td>
                   
                    <button class="btn btn-primary btn-vermas" data-id="${respuesta.id}" data-idclie="${idclie}">Ver respuestas</button>
                </td>
            </tr>
        `;
                        }
                    }

                });
            }

            htmlContent += `
                </tbody>
            </table>

            <hr class="mt-3">
        </div>
    </div>
`;





            // DATOS CITA
            htmlContent += `
    <div class="tab-pane fade" id="pills-adicional" role="tabpanel" aria-labelledby="pills-adicional-tab">

  <div>
    <div class="my-2">
      <h4><strong>Datos de la cita</strong></h4>
      <div class="row mt-3">
        ${[
                    { label: 'Fecha de la cita:', value: formattedDateFecha },
                    { label: 'Hora de la cita:', value: formattedDateHora },
                    { label: 'Asesor asignado:', value: ismanual == "1" ? "Sin asesor asignado" : asesor }
                ].map(dato => `
          <div class="col-md-3 mb-2">
            <div class="d-flex flex-column">
              <p class="m-0"><strong>${dato.label}</strong></p>
              <p class="m-0">${dato.value}</p>
            </div>
          </div>
        `).join('')}
      </div>
    </div>
    <hr>
  </div>
`;

            var formattedWeddingDate = moment(cliente_wedding_date).format('DD-MM-YYYY');
            var formattedWeddingHour = moment(cliente_wedding_date).format('hh:mm a');
            var formattedGuestsCount = cliente_guests_count; // Para el campo número

            htmlContent += `
  <div class="my-3">
    <h4><strong>Datos del evento</strong></h4>
    <div class="row">
      ${[
                    { label: 'Fecha del evento:', value: cliente_wedding_date ? cliente_wedding_date : "", id: 'weddingDate', type: 'date' },
                    { label: 'Lugar del evento:', value: cliente_wedding_location ? cliente_wedding_location : "", id: 'weddingLocation', type: 'text' },
                    { label: 'Total de invitados:', value: formattedGuestsCount ? formattedGuestsCount : "", id: 'guestsCount', type: 'number' },
                    { label: 'Servicio de interés:', value: cliente_service_interest ? cliente_service_interest : "", id: 'serviceInterest', type: 'text' },
                    { label: 'Estilo de fotografía:', value: cliente_look_preference ? cliente_look_preference : "", id: 'lookPreference', type: 'text' },
                    { label: 'Planificador de boda:', value: cliente_wedding_planner ? cliente_wedding_planner : "", id: 'weddingPlanner', type: 'text' },
                    { label: '¿Cómo nos conoció?:', value: cliente_hear_about_us ? cliente_hear_about_us : "", id: 'hearAboutUs', type: 'select' },
                    { label: '¿Cómo se conocieron?:', value: cliente_how_did_you_meet ? cliente_how_did_you_meet : "", id: 'howDidYouMeet', type: 'text' },
                    { label: 'Actividades de pareja:', value: cliente_couple_activities ? cliente_couple_activities : "", id: 'coupleActivities', type: 'text' },
                    { label: 'Película o canción favorita:', value: cliente_favorite_movie_or_song ? cliente_favorite_movie_or_song : "", id: 'favoriteMovieOrSong', type: 'text' },
                    { label: 'Instagram:', value: cliente_instagram_handle ? cliente_instagram_handle : "", id: 'instagramHandle', type: 'text' },
                    { label: 'Detalles adicionales:', value: cliente_additional_details ? cliente_additional_details : "", id: 'additionalDetails', type: 'text' }
                ].map(dato => {
                    // Generar HTML según el tipo de input
                    if (dato.type === 'select') {
                        // Si es un select, generamos las opciones
                        return `
            <div class="col-md-3 mb-2">
              <div class="d-flex flex-column">
                <p class="m-0"><strong>${dato.label}</strong></p>
                <select id="${dato.id}" class="form-select mb-2">
                 <option value="0" disabled ${dato.value === '0' ? 'selected' : ''}>how did you hear about us?</option>
<option value="1" ${dato.value === '1' ? 'selected' : ''}>Instagram</option>
<option value="2" ${dato.value === '2' ? 'selected' : ''}>Google ads</option>
<option value="3" ${dato.value === '3' ? 'selected' : ''}>Website</option>
<option value="4" ${dato.value === '4' ? 'selected' : ''}>Wedding Planner</option>
<option value="5" ${dato.value === '5' ? 'selected' : ''}>Recommendation</option>
<option value="6" ${dato.value === '6' ? 'selected' : ''}>Referral from a bride / planner</option>
<option value="7" ${dato.value === '7' ? 'selected' : ''}>Article</option>
<option value="8" ${dato.value === '8' ? 'selected' : ''}>Search Engine</option>
<option value="9" ${dato.value === '9' ? 'selected' : ''}>Other</option>
                </select>
              </div>
            </div>
          `;
                    } else if (dato.type === 'date') {
                        // Para inputs de tipo 'date', lo manejamos específicamente
                        return `
            <div class="col-md-3 mb-2">
              <div class="d-flex flex-column">
                <p class="m-0"><strong>${dato.label}</strong></p>
                <input type="date" id="${dato.id}" class="form-control mb-2" value="${dato.value}" />
              </div>
            </div>
          `;
                    } else {
                        // Para otros inputs de tipo 'text', 'number', etc.
                        return `
            <div class="col-md-3 mb-2">
              <div class="d-flex flex-column">
                <p class="m-0"><strong>${dato.label}</strong></p>
                <input type="${dato.type}" id="${dato.id}" class="form-control mb-2" value="${dato.value}" />
              </div>
            </div>
          `;
                    }
                }).join('')}
    </div>

    <!-- Botón para actualizar los datos -->
    <div class="text-right mt-3">
      <button class="btn btn-primary" id="updateEventBtn">Actualizar</button>
    </div>
  </div>
</div>
`;

            htmlContent += '</div>';
            $('#modalContent').html(htmlContent);
            $('#verMasModal').modal('show');
            // Handler para guardar los datos nuevos de pagos (paquete, puntos, monto_venta, que_se_les_vendio)
            $(document).off('click', '#guardarPagosBtn').on('click', '#guardarPagosBtn', function (e) {
                e.preventDefault();
                var idcl = $(this).data('id');
                var paquete = $('#paqueteInput').length ? $('#paqueteInput').val() : ($('#paqueteDisplay').length ? $('#paqueteDisplay').clone().children().remove().end().text().trim() : '');
                var puntos = $('#puntosInput').length ? $('#puntosInput').val() : ($('#puntosDisplay').length ? $('#puntosDisplay').clone().children().remove().end().text().trim() : '');
                var monto_venta = $('#montoVentaInput').length ? $('#montoVentaInput').val() : ($('#montoVentaDisplay').length ? $('#montoVentaDisplay').clone().children().remove().end().text().trim() : '');
                var que_se_les_vendio = $('#queSeLesVendioInput').length ? $('#queSeLesVendioInput').val() : ($('#queSeLesVendioDisplay').length ? $('#queSeLesVendioDisplay').clone().children().remove().end().text().trim() : '');

                // calcular fecha a enviar (YYYY-MM-DD) desde input o display
                var fecha_cambio_cliente = '';
                if ($('#fechaCambioInput').length) {
                    fecha_cambio_cliente = $('#fechaCambioInput').val();
                } else if ($('#fechaCambioDisplay').length) {
                    var disp = $('#fechaCambioDisplay').clone().children().remove().end().text().trim();
                    var parts = disp.split(' ')[0].split('/');
                    if (parts.length === 3) {
                        fecha_cambio_cliente = parts[2] + '-' + parts[1] + '-' + parts[0];
                    }
                }

                $.ajax({
                    url: 'guardar_datos_paquete.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        idcliente: idcl,
                        paquete: paquete,
                        puntos: puntos,
                        monto_venta: monto_venta,
                        que_se_les_vendio: que_se_les_vendio,
                        fecha_cambio_cliente: fecha_cambio_cliente
                    },
                    success: function (resp) {
                        if (resp && resp.success) {
                            var data = resp.data;
                            // Actualizar la UI: reemplazar inputs por displays
                            if ($('#paqueteInput').length) {
                                $('#paqueteInput').replaceWith('<p class="m-0" id="paqueteDisplay">' + (data.paquete || '') + ' <button class="btn btn-sm btn-link edit-pago" data-field="paquete">Editar</button></p>');
                            } else if ($('#paqueteDisplay').length) {
                                $('#paqueteDisplay').html((data.paquete || '') + ' <button class="btn btn-sm btn-link edit-pago" data-field="paquete">Editar</button>');
                            }
                            if ($('#puntosInput').length) {
                                $('#puntosInput').replaceWith('<p class="m-0" id="puntosDisplay">' + (data.puntos || '') + ' <button class="btn btn-sm btn-link edit-pago" data-field="puntos">Editar</button></p>');
                            } else if ($('#puntosDisplay').length) {
                                $('#puntosDisplay').html((data.puntos || '') + ' <button class="btn btn-sm btn-link edit-pago" data-field="puntos">Editar</button>');
                            }
                            if ($('#montoVentaInput').length) {
                                $('#montoVentaInput').replaceWith('<p class="m-0" id="montoVentaDisplay">' + (data.monto_venta || '') + ' <button class="btn btn-sm btn-link edit-pago" data-field="monto_venta">Editar</button></p>');
                            } else if ($('#montoVentaDisplay').length) {
                                $('#montoVentaDisplay').html((data.monto_venta || '') + ' <button class="btn btn-sm btn-link edit-pago" data-field="monto_venta">Editar</button>');
                            }
                            if ($('#queSeLesVendioInput').length) {
                                $('#queSeLesVendioInput').replaceWith('<p class="m-0" id="queSeLesVendioDisplay">' + (data.que_se_les_vendio || '') + ' <button class="btn btn-sm btn-link edit-pago" data-field="que_se_les_vendio">Editar</button></p>');
                            } else if ($('#queSeLesVendioDisplay').length) {
                                $('#queSeLesVendioDisplay').html((data.que_se_les_vendio || '') + ' <button class="btn btn-sm btn-link edit-pago" data-field="que_se_les_vendio">Editar</button>');
                            }

                            // fecha de cierre
                            var formattedFecha = data.fecha_cambio_cliente ? moment(data.fecha_cambio_cliente).format('DD/MM/YYYY') : '';
                            if ($('#fechaCambioInput').length) {
                                $('#fechaCambioInput').replaceWith('<p class="m-0" id="fechaCambioDisplay">' + formattedFecha + ' <button class="btn btn-sm btn-link edit-pago" data-field="fecha_cambio_cliente">Editar</button></p>');
                            } else if ($('#fechaCambioDisplay').length) {
                                $('#fechaCambioDisplay').html(formattedFecha + ' <button class="btn btn-sm btn-link edit-pago" data-field="fecha_cambio_cliente">Editar</button>');
                            }

                            Swal.fire('Guardado', 'Datos de paquete actualizados.', 'success');
                            // Ocultar el botón después de guardar
                            $('#guardarPagosBtn').hide();
                        } else {
                            Swal.fire('Error', (resp.message || 'No se pudo guardar la información'), 'error');
                        }
                    },
                    error: function (xhr, status, err) {
                        console.error(xhr.responseText);
                        Swal.fire('Error', 'Error en la petición AJAX', 'error');
                    }
                });
            });

            // Permitir editar campos desde el display
            $(document).off('click', '.edit-pago').on('click', '.edit-pago', function (e) {
                e.preventDefault();
                var field = $(this).data('field');
                if (field === 'puntos') {
                    var val = $('#puntosDisplay').clone().children().remove().end().text().trim();
                    $('#puntosDisplay').replaceWith('<input type="number" step="any" id="puntosInput" class="form-control" value="' + val + '">');
                } else if (field === 'monto_venta') {
                    var val = $('#montoVentaDisplay').clone().children().remove().end().text().trim();
                    $('#montoVentaDisplay').replaceWith('<input type="number" step="0.01" id="montoVentaInput" class="form-control" value="' + val + '">');
                } else if (field === 'que_se_les_vendio') {
                    var val = $('#queSeLesVendioDisplay').clone().children().remove().end().text().trim();
                    $('#queSeLesVendioDisplay').replaceWith('<input type="text" id="queSeLesVendioInput" class="form-control" value="' + val + '">');
                } else if (field === 'fecha_cambio_cliente') {
                    // Obtener el valor actual y convertir a YYYY-MM-DD para el input
                    var val = $('#fechaCambioDisplay').clone().children().remove().end().text().trim().split(' ')[0] || '';
                    if (val.indexOf('/') !== -1) {
                        var p = val.split('/');
                        val = (p[2] ? p[2] : '') + '-' + (p[1] ? p[1] : '') + '-' + (p[0] ? p[0] : '');
                    }
                    $('#fechaCambioDisplay').replaceWith('<input type="date" id="fechaCambioInput" class="form-control" value="' + val + '">');
                }
                if (field === 'paquete') {
                    var val = $('#paqueteDisplay').clone().children().remove().end().text().trim();
                    // Reemplazamos por un select y lo poblamos con las opciones solicitadas
                    $('#paqueteDisplay').replaceWith('<select id="paqueteInput" class="form-control"></select>');
                    var options = ['1 Foto', '2 Foto', '2 Foto 1 Video', '2 Foto y 2 Video', '3 Foto y 2 Video', 'Mini Evento', '1 Foto 1 Video', '3 foto', '1 Video', '2 video'];
                    var select = $('#paqueteInput');
                    select.empty().append('<option value="">Seleccione paquete</option>');
                    options.forEach(function (opt) {
                        select.append('<option value="' + opt + '">' + opt + '</option>');
                    });
                    select.val(val);
                    // Mostrar el botón de guardar cuando el usuario entra en modo edición
                    $('#guardarPagosBtn').show();
                }
                else {
                    // Para cualquier otra edición, aseguramos mostrar el botón
                    $('#guardarPagosBtn').show();
                }
            });

            function generarIdPago(longitud) {
                const caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; // Caracteres válidos para el ID
                let id = '';
                for (let i = 0; i < longitud; i++) {
                    const randomIndex = Math.floor(Math.random() * caracteres.length); // Índice aleatorio
                    id += caracteres[randomIndex]; // Concatenar el carácter aleatorio
                }
                return id;
            }

            // DATOS DEL EVENTO
            var formattedWeddingDate = moment(cliente_wedding_date).format('DD-MM-YYYY');
            var formattedWeddingHour = moment(cliente_wedding_date).format('hh:mm a');

            var htmlContent = `
  <div class="my-3">
    <h4><strong>Datos del evento</strong></h4>
    <div class="row">
      ${[
                    { label: 'Fecha del evento:', value: formattedWeddingDate, id: 'weddingDate' },
                    // { label: 'Hora del evento:', value: formattedWeddingHour, id: 'weddingHour' },
                    { label: 'Lugar del evento:', value: cliente_wedding_location, id: 'weddingLocation' },
                    { label: 'Total de invitados:', value: cliente_guests_count, id: 'guestsCount' },
                    { label: 'Servicio de interés:', value: cliente_service_interest, id: 'serviceInterest' },
                    { label: 'Estilo de fotografía:', value: cliente_look_preference, id: 'lookPreference' },
                    { label: 'Planificador de boda:', value: cliente_wedding_planner, id: 'weddingPlanner' },
                    { label: '¿Cómo nos conoció?:', value: cliente_hear_about_us, id: 'hearAboutUs' },
                    { label: '¿Cómo se conocieron?:', value: cliente_how_did_you_meet, id: 'howDidYouMeet' },
                    { label: 'Actividades de pareja:', value: cliente_couple_activities, id: 'coupleActivities' },
                    { label: 'Película o canción favorita:', value: cliente_favorite_movie_or_song, id: 'favoriteMovieOrSong' },
                    { label: 'Instagram:', value: cliente_instagram_handle, id: 'instagramHandle' },
                    { label: 'Detalles adicionales:', value: cliente_additional_details, id: 'additionalDetails' }
                ].map(dato => `
        <div class="col-md-3 mb-2">
          <div class="d-flex flex-column">
            <p class="m-0"><strong>${dato.label}</strong></p>
            <input type="text" id="${dato.id}" class="form-control mb-2" value="${dato.value}" />
          </div>
        </div>
      `).join('')}
    </div>

    <!-- Botón para actualizar los datos -->
    <div class="text-right mt-3">
      <button class="btn btn-primary" id="updateEventBtn">Actualizar</button>
    </div>
  </div>
</div>
`;

            // Insertar el contenido HTML generado
            $('#contenido').html(htmlContent);

            // Manejar la actualización de los datos cuando se hace clic en el botón
            $('#updateEventBtn').on('click', function () {
                // Obtener los nuevos valores de los inputs usando jQuery
                var updatedData = {
                    wedding_date: $('#weddingDate').val(),
                    // wedding_hour: $('#weddingHour').val(),
                    wedding_location: $('#weddingLocation').val(),
                    guests_count: $('#guestsCount').val(),
                    service_interest: $('#serviceInterest').val(),
                    look_preference: $('#lookPreference').val(),
                    wedding_planner: $('#weddingPlanner').val(),
                    hear_about_us: $('#hearAboutUs').val(),
                    how_did_you_meet: $('#howDidYouMeet').val(),
                    couple_activities: $('#coupleActivities').val(),
                    favorite_movie_or_song: $('#favoriteMovieOrSong').val(),
                    instagram_handle: $('#instagramHandle').val(),
                    additional_details: $('#additionalDetails').val()
                };

                if (!['1', '2', '3'].includes(String(updatedData.how_did_you_meet || '').trim())) {
                    delete updatedData.how_did_you_meet;
                }

                $.ajax({
                    url: 'actualizar_datos_cliente.php',
                    type: 'POST',
                    dataType: 'json', // Esperamos una respuesta en formato JSON
                    data: {
                        updatedData: updatedData,
                        idclie: idclie
                    },
                    success: function (response) {
                        if (response.success) {
                            // Mostrar mensaje de éxito
                            Swal.fire({
                                title: 'Éxito!',
                                text: 'Los datos se han actualizado correctamente.',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            });
                        } else {
                            // Mostrar mensaje de error
                            Swal.fire({
                                title: 'Error!',
                                text: 'Hubo un problema al actualizar la fecha.',
                                icon: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    },
                    error: function () {
                        // Mostrar mensaje si algo sale mal con la solicitud
                        Swal.fire({
                            title: 'Error!',
                            text: 'No se pudo conectar con el servidor.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });


            });

            // Manejar la lógica de mostrar y actualizar la fecha
            $('#editarFecha').click(function () {
                $('#eventoFechaDisplay').hide();
                $('#inputFecha').show();
            });

            // Lógica para actualizar la fecha
            $('#actualizarFechaBtn').click(function () {
                var nuevaFecha = $('#fechaEventoInput').val();
                var updatedData = {
                    wedding_date: nuevaFecha,

                };
                if (nuevaFecha && idclie) {
                    // Formatear la fecha
                    var fechaFormateada = moment(nuevaFecha).format('dddd, D [de] MMMM [de] YYYY');

                    // Mostrar la fecha formateada y el botón de "editar"
                    $('#eventoFechaDisplay').html(`${fechaFormateada} <span class="text-primary" style="cursor: pointer;" id="editarFecha">Editar</span>`);

                    // Ocultar el input y mostrar la fecha actualizada
                    $('#eventoFechaDisplay').show();
                    $('#inputFecha').hide();

                    // Enviar la solicitud AJAX para actualizar la fecha en la base de datos
                    $.ajax({
                        url: 'actualizar_datos_cliente.php',
                        type: 'POST',
                        dataType: 'json', // Esperamos una respuesta en formato JSON
                        data: {
                            updatedData: updatedData,
                            idclie: idclie
                        },
                        success: function (response) {
                            if (response.success) {
                                // Mostrar mensaje de éxito
                                Swal.fire({
                                    title: 'Éxito!',
                                    text: 'La fecha se ha actualizado correctamente.',
                                    icon: 'success',
                                    confirmButtonText: 'Aceptar'
                                });
                            } else {
                                // Mostrar mensaje de error
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'Hubo un problema al actualizar la fecha.',
                                    icon: 'error',
                                    confirmButtonText: 'Aceptar'
                                });
                            }
                        },
                        error: function () {
                            // Mostrar mensaje si algo sale mal con la solicitud
                            Swal.fire({
                                title: 'Error!',
                                text: 'No se pudo conectar con el servidor.',
                                icon: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    });
                } else {
                    Swal.fire({
                        title: 'Advertencia!',
                        text: 'Por favor, asegúrate de que la fecha y el ID de cliente estén completos.',
                        icon: 'warning',
                        confirmButtonText: 'Aceptar'
                    });
                }
            });

            $('#tablaCuestionario').on('click', '.btn-vermas', function () {
                // Obtener el id de la respuesta desde los atributos del botón
                var id = $(this).data('id');

                // Buscar la respuesta correspondiente
                var respuesta = respuestas_cuestionario.find(r => r.id == id);
                const cuestionarios = <?php echo json_encode($cuestionarios) ?>;

                let cuestionarioCliente = cuestionarios.filter((c) => c.id = respuesta.id_cuestionario)[0];
                console.log("cuestionarioCliente ", cuestionarioCliente)
                if (respuesta) {
                    // Limpiar el contenedor del modal antes de agregar nuevas respuestas
                    $('#modal-questions-container').empty();
                    let tituloCuestionario = cuestionarioCliente.titulo;
                    let introduccionCuestionario = cuestionarioCliente.introduccion;
                    // Obtener el array de preguntas y respuestas
                    var preguntasRespuestas = JSON.parse(respuesta.respuestas);
                    // Agregar el título e introducción del cuestionario
                    let headerHtml = `
            <div class="modal-cuestionario-header">
                <h3 class="cuestionario-title">${tituloCuestionario}</h3>
                <p class="cuestionario-intro">${introduccionCuestionario}</p>
                <hr>
            </div>
        `;
                    $('#modal-questions-container').append(headerHtml);
                    // Recorrer las preguntas y respuestas
                    preguntasRespuestas.forEach(function (item, index) {
                        var pregunta = item.pregunta;
                        var respuestaTexto = item.respuesta.res;
                        var tipoPregunta = item.tipo;
                        var tipoRespuesta = item.respuesta.tipo;

                        let questionHtml = '';

                        if (tipoPregunta === 'titulo') {
                            questionHtml = `
                    <div class="modal-section-title">
                        <h4 class="section-title">${pregunta}</h4>
                        <div class="title-divider"></div>
                    </div>
                `;
                        } else if (tipoPregunta === 'introduccion') {
                            questionHtml = `
                    <div class="modal-introduction">
                        <p class="intro-text">${pregunta}</p>
                    </div>
                `;
                        } else {
                            questionHtml = `
                    <div class="modal-question-item ${index % 2 === 0 ? 'even' : 'odd'}">
                        <div class="question-header">
                            <h6 class="question-text">${pregunta}</h6>
                        </div>
                        <div class="answer-content">
                `;

                            if (tipoRespuesta === "text") {
                                questionHtml += `<p class="answer-text">${respuestaTexto}</p>`;
                            } else if (["jpg", "png", "jpeg"].includes(tipoRespuesta)) {
                                questionHtml += `
                        <div class="file-actions">
                            <button class="btn btn-outline-primary btn-sm file-btn" onclick="verImagen('/${respuestaTexto}')">
                                <i class="fas fa-eye me-1"></i>Ver imagen
                            </button>
                            <a href="/${respuestaTexto}" download class="btn btn-outline-secondary btn-sm file-btn">
                                <i class="fas fa-download me-1"></i>Descargar
                            </a>
                        </div>
                    `;
                            } else if (tipoRespuesta === "pdf") {
                                questionHtml += `
                        <div class="file-actions">
                            <button class="btn btn-outline-primary btn-sm file-btn" onclick="verPdf('/${respuestaTexto}')">
                                <i class="fas fa-file-pdf me-1"></i>Ver PDF
                            </button>
                            <a href="/${respuestaTexto}" download class="btn btn-outline-secondary btn-sm file-btn">
                                <i class="fas fa-download me-1"></i>Descargar
                            </a>
                        </div>
                    `;
                            } else if (Array.isArray(respuestaTexto)) {
                                questionHtml += `<div class="answer-list">`;
                                respuestaTexto.forEach(function (itemInArray) {
                                    questionHtml += `<div class="list-item">${itemInArray}</div>`;
                                });
                                questionHtml += `</div>`;
                            } else {
                                questionHtml += `
                        <div class="file-actions">
                            <span class="text-muted">Archivo adjunto</span>
                            <a href="/${respuestaTexto}" download class="btn btn-outline-secondary btn-sm file-btn">
                                <i class="fas fa-download me-1"></i>Descargar archivo
                            </a>
                        </div>
                    `;
                            }

                            questionHtml += `
                        </div>
                    </div>
                `;
                        }

                        $('#modal-questions-container').append(questionHtml);
                    });

                    // Mostrar el modal
                    $('#detailsModal').modal('show');
                }



                // Función para mostrar la imagen en un modal

            });


            // Delegar el evento click con $(document).on()
            $(document).on('click', '.btn-aprobarpago', function () {

                var idPago = $(this).data('id');  // Obtener el idPago del botón

                console.log("idpago ", idPago);

                // Mostrar el Swal con una confirmación
                Swal.fire({
                    title: 'Aprobar el pago',
                    text: "¿Cómo recibiste el pago?",
                    icon: 'warning',
                    confirmButtonText: 'Transferencia',
                    cancelButtonText: 'Cancelar',
                    // reverseButtons: true,
                    showDenyButton: true,
                    showCancelButton: true,
                    denyButtonText: 'Efectivo',
                    //   customClass: {
                    //     actions: 'my-actions',
                    //     cancelButton: 'order-1 right-gap',
                    //     confirmButton: 'order-2',
                    //     denyButton: 'order-3',
                    //   },
                }).then((result) => {
                    console.log("result aprobacion pago ", result);
                    let forma_pago_seleccionada = 0;
                    if (result.isConfirmed) {
                        const loadingSwal = Swal.fire({
                            title: 'Aprobando...',
                            text: 'Please wait while we process your request.',
                            allowOutsideClick: false, // Prevenir que el usuario cierre el alerta
                            didOpen: () => {
                                Swal.showLoading(); // Mostrar el spinner de carga
                            }
                        });

                        forma_pago_seleccionada = 2
                        console.log("forma_pago_seleccionada ", forma_pago_seleccionada)
                        $.ajax({
                            url: 'actualizar_pago.php',  // Archivo PHP que realizará la actualización
                            type: 'POST',
                            data: {
                                idPago: idPago,  // Enviar el ID del pago
                                estatus: 1,       // Marcar como aprobado (estatus 1)
                                forma_pago: forma_pago_seleccionada
                            },
                            dataType: 'json',  // Esperamos una respuesta JSON
                            success: function (response) {
                                loadingSwal.close()
                                console.log("res ", response)
                                // Si la actualización fue exitosa, mostrar el mensaje de éxito
                                if (response.success) {
                                    Swal.fire(
                                        '¡Aprobado!',
                                        'El pago ha sido marcado como aprobado.',
                                        'success'
                                    );
                                    let forma_pago = forma_pago_seleccionada == 2 ? "Transferencia" : "Efectivo"
                                    // Cambiar el texto del botón y deshabilitarlo
                                    $('#tablaPagos tbody tr').each(function () {
                                        var row = $(this);
                                        var id = row.find('.btn-aprobarpago').data('id');
                                        if (id == idPago) {
                                            let fechaActual = moment().format('YYYY-MM-DD');
                                            let horaActual = moment().format('hh:mm a');
                                            row.find('td').eq(4).text(fechaActual);
                                            row.find('td').eq(5).text(horaActual);
                                            row.find('td').eq(7).text(forma_pago);
                                            row.find('td').eq(8).text('aprobado').css('color', '#46ba46');
                                            row.find('.btn-aprobarpago').text('Aprobado').prop('disabled', true);
                                            row.find('.btn-reenviarcorreopago').text('').css('display', 'none');
                                        }
                                    });
                                } else {
                                    // Si hubo un error en la actualización
                                    Swal.fire(
                                        'Error',
                                        'Hubo un problema al marcar el pago como aprobado.',
                                        'error'
                                    );
                                }
                            },
                            error: function (e) {
                                console.log(e);
                                loadingSwal.close()

                                // Si la petición AJAX falla
                                Swal.fire(
                                    'Error',
                                    'Hubo un error al realizar la acción.',
                                    'error'
                                );
                            }
                        });
                    } else if (result.isDenied) {
                        const loadingSwal = Swal.fire({
                            title: 'Aprobando...',
                            text: 'Please wait while we process your request.',
                            allowOutsideClick: false, // Prevenir que el usuario cierre el alerta
                            didOpen: () => {
                                Swal.showLoading(); // Mostrar el spinner de carga
                            }
                        });

                        forma_pago_seleccionada = 3;
                        console.log("forma_pago_seleccionada ", forma_pago_seleccionada)
                        $.ajax({
                            url: 'actualizar_pago.php',  // Archivo PHP que realizará la actualización
                            type: 'POST',
                            data: {
                                idPago: idPago,  // Enviar el ID del pago
                                estatus: 1,       // Marcar como aprobado (estatus 1)
                                forma_pago: forma_pago_seleccionada
                            },
                            dataType: 'json',  // Esperamos una respuesta JSON
                            success: function (response) {
                                loadingSwal.close()

                                console.log("res ", response)
                                // Si la actualización fue exitosa, mostrar el mensaje de éxito
                                if (response.success) {
                                    Swal.fire(
                                        '¡Aprobado!',
                                        'El pago ha sido marcado como aprobado.',
                                        'success'
                                    );
                                    let forma_pago = forma_pago_seleccionada == 2 ? "Transferencia" : "Efectivo"
                                    // Cambiar el texto del botón y deshabilitarlo
                                    $('#tablaPagos tbody tr').each(function () {
                                        var row = $(this);
                                        var id = row.find('.btn-aprobarpago').data('id');
                                        if (id == idPago) {
                                            let fechaActual = moment().format('YYYY-MM-DD');
                                            let horaActual = moment().format('hh:mm a');
                                            row.find('td').eq(4).text(fechaActual);
                                            row.find('td').eq(5).text(horaActual);
                                            row.find('td').eq(7).text(forma_pago);
                                            row.find('td').eq(8).text('aprobado').css('color', '#46ba46');
                                            row.find('.btn-aprobarpago').text('Aprobado').prop('disabled', true);
                                            row.find('.btn-reenviarcorreopago').text('').css('display', 'none');
                                            // Cambiar el texto del botón y deshabilitarlo
                                        }
                                    });
                                } else {
                                    loadingSwal.close()

                                    // Si hubo un error en la actualización
                                    Swal.fire(
                                        'Error',
                                        'Hubo un problema al marcar el pago como aprobado.',
                                        'error'
                                    );
                                }
                            },
                            error: function (e) {
                                console.log(e);
                                // Si la petición AJAX falla
                                Swal.fire(
                                    'Error',
                                    'Hubo un error al realizar la acción.',
                                    'error'
                                );
                            }
                        });

                    }

                });
            });
            $(document).on('click', '.btn-cambiaestatusgaleria', function () {
                let id = $(this).data('id');
                let estatus = $(this).data('estatus');
                let nuevoEstatus = ''; // Asegúrate de inicializar la variable con un valor por defecto

                switch (estatus) {
                    case 2:
                        nuevoEstatus = "Comprado";
                        break;
                    case 3:
                        nuevoEstatus = "Entregado";
                        break;
                    case 4:
                        nuevoEstatus = "Rechazado";
                        break;
                }

                Swal.fire({
                    title: '¿Estás seguro?',
                    text: `¿Deseas cambiar el estatus a ${nuevoEstatus}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, cambiar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('isConfirmed');
                        const loadingSwal = Swal.fire({
                            title: 'Submitting...',
                            text: 'Please wait while we process your request.',
                            allowOutsideClick: false, // Prevenir que el usuario cierre el alerta
                            customClass: {
                                popup: 'swal2-popup-center'
                            },
                            didOpen: () => {
                                Swal.showLoading(); // Mostrar el spinner de carga
                            }
                        });

                        // Aquí puedes agregar el código para realizar una solicitud AJAX si es necesario
                        $.ajax({
                            url: 'cambiar_estatus_galeria.php',
                            method: 'POST',
                            data: { id: id, estatus: estatus },
                            success: function (response) {
                                loadingSwal.close();  // Cerrar el Swal de carga
                                Swal.fire('Éxito', 'El estatus se ha actualizado correctamente', 'success');
                            },
                            error: function () {
                                loadingSwal.close();  // Cerrar el Swal de carga
                                Swal.fire('Error', 'Hubo un problema al actualizar el estatus', 'error');
                            }
                        }).then(() => {

                            $('#tablaGaleria tbody tr').each(function () {


                                var row = $(this);  // Esta es la fila actual
                                var idRowGal = row.find('.rowGal').data('id');
                                if (id == idRowGal) {
                                    // var estatus = row.find('.btn-cambiaestatusgaleria').data('estatus');  // Acceder al data-estatus de un botón dentro de la fila

                                    console.log("estatus: ", estatus);  // Verificar el valor de estatus

                                    // Ahora puedes seguir con tu lógica para cambiar el contenido de la fila
                                    if (estatus == 3) {
                                        row.find('td').eq(4).text("Entregado");

                                        row.find('td').eq(5).html('');  // Limpiar si el estatus es 3 o 4
                                    } else
                                        if (estatus == 4) {
                                            row.find('td').eq(4).text("Rechazado");

                                            row.find('td').eq(5).html('');  // Limpiar si el estatus es 3 o 4
                                        } else if (estatus == 2) {
                                            row.find('td').eq(4).text("Comprado");
                                            row.find('td').eq(5).html("<button class='btn btn-cambiaestatusgaleria btn-success' data-estatus='" + 3 + "' data-id='" + id + "'>Cambiar a entregado</button>");
                                        }
                                }
                            });





                        })

                    }
                });
            });







            $(document).on('click', '.btn-enviarWhatsapp', function () {
                let gid_clie = $(this).data('id_clie');
                let gtelefono = $(this).data('telefono');
                let gcountry_code = $(this).data('country_code');
                let vencimiento = $(this).data('vencimiento');
                let horaRegistroGaleria = $(this).data('horaRegistroGaleria');

                console.log("id_clie ", gid_clie)
                console.log("telefono ", gtelefono)
                console.log("country_code ", gcountry_code)
                let txt_correo = <?php echo json_encode($txt_correo); ?>;

                console.log("txt_correo ", txt_correo)

                let customer = cliente_names;
                let expiration_date = vencimiento + " " + horaRegistroGaleria;
                // Obtener la fecha actual
                let currentDate = moment();
                // Crear el objeto moment para la fecha de vencimiento + hora
                let expirationDate = moment(expiration_date, "YYYY-MM-DD HH:mm");
                let daysLeft = expirationDate.diff(currentDate, 'days');
                console.log("Días restantes:", daysLeft);
                let indexCorreo = gcountry_code == "+52" ? 8 : 9;

                let expiration_date_gallery = ''
                if (indexCorreo == 8) {
                    moment.locale('es');
                    expiration_date_gallery = expirationDate.format("D [de] MMMM [de] YYYY");
                } else {
                    moment.locale('en');
                    expiration_date_gallery = expirationDate.format("MMMM D, YYYY");
                }

                let titulo = txt_correo[indexCorreo].txttituloclie;
                titulo = titulo.replace('$customer', customer);
                titulo = titulo.replace('$expiration_date_gallery', expiration_date_gallery);

                let cuerpo = txt_correo[indexCorreo].txtcli;
                cuerpo = cuerpo.replace('$customer', customer);
                cuerpo = cuerpo.replace('$expiration_date_gallery', expiration_date_gallery);

                let despedida = txt_correo[indexCorreo].txtdespclie;
                despedida = despedida.replace('$customer', customer);
                despedida = despedida.replace('$expiration_date_gallery', expiration_date_gallery);


                console.log("idclie ", idclie)
                // Convertir contenido HTML a texto plano
                let tituloTextoPlano = new DOMParser().parseFromString(titulo, 'text/html').body.textContent || "";
                let cuerpoTextoPlano = new DOMParser().parseFromString(cuerpo, 'text/html').body.textContent || "";
                let despedidaTextoPlano = new DOMParser().parseFromString(despedida, 'text/html').body.textContent || "";

                // Opcionalmente, puedes eliminar saltos de línea y otros caracteres que quieras no incluir
                function convertHtmlToTextWithLineBreaks(html) {
                    // Primero reemplazamos los <br> con saltos de línea
                    html = html.replace(/<br\s*\/?>/gi, '\n');

                    // Luego, si es un párrafo <p>, lo convertimos en texto con salto de línea al final
                    html = html.replace(/<p>/gi, '').replace(/<\/p>/gi, '\n\n');

                    // Limpiar el texto resultante de posibles etiquetas HTML no deseadas
                    html = new DOMParser().parseFromString(html, 'text/html').body.textContent || "";

                    return html;
                }

                // Aplicar la función a los textos
                tituloTextoPlano = convertHtmlToTextWithLineBreaks(tituloTextoPlano);
                cuerpoTextoPlano = convertHtmlToTextWithLineBreaks(cuerpoTextoPlano);
                despedidaTextoPlano = convertHtmlToTextWithLineBreaks(despedidaTextoPlano);


                // Formatear el mensaje
                let mensaje = `${tituloTextoPlano}\n\n${cuerpoTextoPlano}\n\n${despedidaTextoPlano}`;
                let mensajeEncoded = encodeURIComponent(mensaje);

                // Número de teléfono al que se enviará el mensaje (ejemplo: "+521234567890")
                let telefono = gcountry_code + gtelefono;

                // Crear el link de WhatsApp
                let linkWhatsApp = `https://wa.me/${telefono}?text=${mensajeEncoded}`;

                //abrir
                window.open(linkWhatsApp, '_blank');


            });



            $(document).on('click', '.btn-reenviarcorreopago', function () {
                let id = $(this).data('id');  // Obtener el idPago del botón
                let idPago = $(this).data('idpago');  // Obtener el idPago del botón
                let currency = $(this).data('currency');  // Obtener el idPago del botón
                let concepto = $(this).data('concepto');  // Obtener el idPago del botón
                let monto = $(this).data('monto');  // Obtener el idPago del botón
                let idclie = $(this).data('idclie');  // Obtener el idPago del botón
                let cuenta = $(this).data('cuenta');  // Obtener el idPago del botón
                console.log("idPago que se manda:", idPago);


                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "¿Deseas reenviar el link de pago?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, enviar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        console.log('isConfirmed')
                        const loadingSwal = Swal.fire({
                            title: 'Submitting...',
                            text: 'Please wait while we process your request.',
                            allowOutsideClick: false, // Prevenir que el usuario cierre el alerta

                            customClass: {
                                popup: 'swal2-popup-center'
                            },
                            didOpen: () => {
                                Swal.showLoading(); // Mostrar el spinner de carga
                            }
                        });
                        $.ajax({
                            url: 'reenviar_link_pago.php',  // Archivo PHP que realizará la actualización
                            type: 'POST',
                            data: {
                                idPago: idPago,  // Enviar el ID del pago
                            },
                            dataType: 'json',  // Esperamos una respuesta JSON
                            success: function (response) {
                                loadingSwal.close();
                                console.log("Respuesta del servidor:", response); // 👈 Agrega esto
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Link reenviado',
                                        text: 'El link de pago ha sido reenviado correctamente.',
                                        showConfirmButton: true,
                                        confirmButtonText: 'Aceptar'
                                    });
                                } else {
                                    // Si hubo un error en la actualización
                                    Swal.fire(
                                        'Error',
                                        'Hubo un problema al reenviar el link.',
                                        'error'
                                    );
                                }
                            },
                            error: function (xhr, status, error) {

                                loadingSwal.close();

                                //         // Aquí puedes capturar el código de error o mensaje específico
                                let errorMessage = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Hubo un error al realizar la acción.';

                                // Mostrar el error obtenido del servidor
                                Swal.fire(
                                    'Error',
                                    errorMessage,
                                    'error'
                                );
                            }
                        });
                        let data = {
                            fechaActual: moment().format('YYYY-MM-DD'),
                            horaActual: moment().format('hh:mm a'),
                            fechaPago: "Pendiente",
                            horaPago: "Pendiente",
                            estatus: 0,
                            idclie: idclie,
                            forma_pago: 0,
                            id_pago: idPago,
                            cuenta,
                            // email,
                            currency,
                            monto,
                            concepto,
                            resend: 1
                        };
                        console.log("data pago", data)

                        $.ajax({
                            url: 'procesar_pago.php',  // Este es el archivo PHP que manejará la sesión de Stripe
                            method: 'POST',
                            data: data,
                            success: function (res) {
                                console.log("response pp ", res)

                                let response = JSON.parse(res);
                                // El servidor devuelve la URL de Stripe si todo sale bien
                                if (response.success) {
                                    // Redirigir a Stripe para completar el pago
                                    // window.location.href = response.url;
                                    data.payment_url = response.session_url;
                                    data.session_id = response.session_id;
                                    console.log(data)
                                    $.ajax({
                                        url: 'agregar_monto.php',
                                        method: 'POST',
                                        data: data,
                                        success: function (response) {
                                            console.log("response am ", response)
                                            var res = JSON.parse(response);
                                            loadingSwal.close();
                                            if (res.status === 'success') {
                                                let idPago = res.id_pago;
                                                let idPagoGenerado = res.id_generado;

                                                // Mostrar un Swal de éxito
                                                Swal.fire({
                                                    title: 'Éxito',
                                                    text: res.message,
                                                    icon: 'success',
                                                    confirmButtonText: 'Aceptar'
                                                }).then(() => {
                                                    // fechaActual = moment().format('DD-MM-YYYY');
                                                    // var montoFormateado = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(monto) + " "+currency.toUpperCase();

                                                    // // Aquí también puedes traducir el valor de formaPago si lo necesitas
                                                    // let formaPagoTexto ="pendiente";
                                                    // let estatusPago = data.estatus == 0 ? "pendiente" : "aprobado";

                                                    // //   var botonAprobado = data.estatus == 0 && (data.forma_pago  == 2 || data.forma_pago == 3)
                                                    // //     ? "<button class='btn btn-success btn-aprobarpago' data-id='" + idPago + "'>Aprobar</button>" 
                                                    // //     : data.estatus == 1 && (data.forma_pago  == 2 || data.forma_pago == 3) ?"<button class='btn btn-success' disabled>Aprobado</button>": "";
                                                    // //   var botonReenviarLink = data.estatus == 0 && data.forma_pago  == 1
                                                    // //     ? "<button class='btn  btn-outline-secondary btn-reenviarcorreopago' data-id='" + idPago + "'>Reenviar link</button>" 
                                                    // //     :"";            

                                                    // //     if( data.estatus == 0 && data.forma_pago  == 1){
                                                    // //          $("#tablaPagos tbody").prepend("<tr><td>" + idPago + "</td><td>" +
                                                    // //     montoFormateado + "</td><td>" + fechaActual + "</td><td>" + horaActual +
                                                    // //     "</td><td>" + data.fechaPago + "</td><td>" + data.horaPago + "</td><td>" +
                                                    // //     data.concepto + "</td><td>" + formaPagoTexto + "</td><td>" + estatusPago + "</td><td>"+botonReenviarLink + "</td></tr>");
                                                    // //     }
                                                    // //     else{
                                                    // //         $("#tablaPagos tbody").prepend("<tr><td>" + idPago + "</td><td>" +
                                                    // //     montoFormateado + "</td><td>" + fechaActual + "</td><td>" + horaActual +
                                                    // //     "</td><td>" + data.fechaPago + "</td><td>" + data.horaPago + "</td><td>" +
                                                    // //     data.concepto + "</td><td>" + formaPagoTexto + "</td><td>" + estatusPago + "</td><td>"+botonAprobado + "</td></tr>");
                                                    // //     }
                                                    //     var botonAprobado = "";
                                                    //     if (data.estatus == 0 &&data.forma_pago == 0) {
                                                    //         botonAprobado = "<button class='btn btn-success btn-aprobarpago' data-id='" + idPago + "'>Aprobar</button>";
                                                    //     } else if (data.estatus == 1 && (data.forma_pago == 2 || data.forma_pago == 3)) {
                                                    //         botonAprobado = "<button class='btn btn-success' disabled>Aprobado</button>";
                                                    //     }

                                                    //     // Crear el botón "Reenviar link" dependiendo de la condición
                                                    //     var botonReenviarLink = "";
                                                    //     if (data.estatus == 0) {
                                                    //         botonReenviarLink = "<button class='btn btn-outline-secondary btn-reenviarcorreopago' data-monto='" + data.monto + "' data-currency='" + data.currency + "' data-concepto='" + data.concepto + "' data-idpago='" + idPago + "' data-id='" + idPagoGenerado + "'>Reenviar Correo</button>";
                                                    //     }

                                                    //     // Construir la fila de la tabla
                                                    //     var fila = "<tr><td>" + idPago + "</td><td>" + montoFormateado + "</td><td>" + fechaActual + "</td><td>" + horaActual +
                                                    //         "</td><td>" + data.fechaPago + "</td><td>" + data.horaPago + "</td><td>" + data.concepto + "</td><td>" +
                                                    //         formaPagoTexto + "</td><td>" + estatusPago + "</td>";

                                                    //     // Añadir el botón adecuado basado en las condiciones
                                                    //      fila += "<td>";
                                                    // if (data.estatus == 0) {
                                                    //   fila += botonReenviarLink;
                                                    // }
                                                    // fila += botonAprobado + "</td>";
                                                    // fila += "</tr>";


                                                    //     // Insertar la fila en la tabla
                                                    //     $("#tablaPagos tbody").prepend(fila);
                                                    // // Limpiar los campos
                                                    // $("#montoInput").val("");
                                                    // $("#conceptoInput").val("");
                                                    // // $("#formaPagoInput").val("1"); // Restablecer el valor del select a "Tarjeta"
                                                    // $('#modalAgregarMonto').modal('hide'); // Cerrar el modal
                                                });
                                            } else {
                                                // Mostrar un Swal de error
                                                Swal.fire({
                                                    title: 'Error',
                                                    text: res.message,
                                                    icon: 'error',
                                                    confirmButtonText: 'Aceptar'
                                                });
                                            }
                                        },
                                        error: function (xhr, status, error) {
                                            loadingSwal.close();
                                            // Si hubo un error en la solicitud AJAX
                                            Swal.fire({
                                                title: 'Error',
                                                text: 'Hubo un problema al procesar la solicitud1. Intenta nuevamente.',
                                                icon: 'error',
                                                confirmButtonText: 'Aceptar'
                                            });
                                        }
                                    });
                                } else {
                                    loadingSwal.close();
                                    // Mostrar mensaje de error si hubo algún problema
                                    Swal.fire({
                                        title: 'Error',
                                        text: 'Hubo un problema al procesar el pago2. Intenta de nuevo.',
                                        icon: 'error',
                                        confirmButtonText: 'Aceptar'
                                    });
                                }
                            },
                            error: function (xhr, status, error) {
                                loadingSwal.close();
                                console.error('Error en la solicitud AJAX4:', error);
                            }
                        });
                    }
                });
            });








            $(document).off('click', '#agregarGaleria').on('click', '#agregarGaleria', function () {
                let selectWhatsapp = $("#selectWhatsapp").val();
                console.log('selectWhatsapp ', selectWhatsapp)
                if (!selectWhatsapp) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Por favor, seleccione una cuenta de WhatsApp',
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                    return;
                }
                let fechaRegistroGaleria = moment().format('YYYY-MM-DD'); // Fecha actual
                let horaRegistroGaleria = moment().format('hh:mm a'); // Hora actual
                let fechaVencimiento = moment().add(6, 'months').format('YYYY-MM-DD'); // Fecha de vencimiento dentro de 6 meses

                let data = {
                    idclie,
                    fechaRegistroGaleria,
                    horaRegistroGaleria,
                    fechaVencimiento,
                    whatsapp: selectWhatsapp
                }
                $.ajax({
                    url: 'guardar_galeria.php',
                    method: 'POST',
                    data: data,
                    success: function (response) {
                        console.log(response);
                        var res = JSON.parse(response);
                        if (res.status === 'success') {
                            let idGaleria = res.id;

                            // Mostrar un Swal de éxito
                            Swal.fire({
                                title: 'Éxito',
                                text: res.message,
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {


                                let estatusGaleria = 'pendiente';

                                //   var botonAprobado = data.estatus == 0 && (data.forma_pago  == 2 || data.forma_pago == 3)
                                //     ? "<button class='btn btn-success btn-aprobarpago' data-id='" + idPago + "'>Aprobar</button>" 
                                //     : data.estatus == 1 && (data.forma_pago  == 2 || data.forma_pago == 3) ?"<button class='btn btn-success' disabled>Aprobado</button>": "";

                                let cuentaWhatsapp = wcuentas.filter((cuenta) => cuenta.id == selectWhatsapp);

                                $("#tablaGaleria tbody").prepend("<tr><td class='rowGal'  data-id='" + idGaleria + "'>" + idGaleria + "</td><td>" +
                                    fechaRegistroGaleria + "</td><td>" + horaRegistroGaleria +
                                    "</td><td>" + fechaVencimiento + "</td><td>" +
                                    estatusGaleria + "</td><td>" + '' + "</td></tr>");

                                // Limpiar los campos
                                $("#selectWhatsapp").val("1");
                                $('#modalAgregarGaleria').modal('hide'); // Cerrar el modal
                            });
                        } else {
                            // Mostrar un Swal de error
                            Swal.fire({
                                title: 'Error',
                                text: res.message,
                                icon: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        // Si hubo un error en la solicitud AJAX
                        Swal.fire({
                            title: 'Error',
                            text: 'Hubo un problema al procesar la solicitud. Intenta nuevamente.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });

            })
            $(document).off('click', '#mandarCuestionario').on('click', '#mandarCuestionario', function () {
                // Obtener datos necesarios
                const enviado = $(this).data("enviado") || 0;
                const idcuestionario = $(this).data("idcuestionario") || 0;
                const idrescuestionario = $(this).data("idrescuestionario") || 0;

                // Verificar que idclie esté definido
                if (typeof idclie === 'undefined' || idclie === null) {
                    console.error('Error: idclie no está definido');
                    Swal.fire('Error', 'No se pudo identificar el cliente', 'error');
                    return;
                }

                // Log para diagnóstico
                console.log('Enviando cuestionario:', {
                    idCliente: idclie,
                    idCuestionario: idcuestionario,
                    idRespuestaCuestionario: idrescuestionario,
                    estadoEnviado: enviado
                });

                // Determinar el ID del cuestionario a enviar
                let selectedCuestionarioId;

                if (enviado !== 1) {
                    // Si no ha sido enviado antes, verificar la selección
                    selectedCuestionarioId = idcuestionario || $('#cuestionarioSelect').val();

                    // Validar que se haya seleccionado un cuestionario
                    if (!selectedCuestionarioId || selectedCuestionarioId == '0') {
                        Swal.fire({
                            title: 'Advertencia',
                            text: 'Elige un cuestionario para continuar',
                            icon: 'warning',
                            confirmButtonText: 'Aceptar'
                        });
                        return;
                    }
                } else {
                    // Si ya fue enviado, usar el ID proporcionado
                    selectedCuestionarioId = idcuestionario;
                }

                // Obtener datos del cuestionario
                const cuestionarios = <?php echo json_encode($cuestionarios) ?>;
                const cuestionarioEncontrado = cuestionarios.find(cuest => cuest.id == selectedCuestionarioId);

                // Confirmar envío
                Swal.fire({
                    title: 'Enviar cuestionario',
                    text: '¿Estás seguro de enviar el cuestionario?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Enviar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#aaa'
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    // Mostrar indicador de carga
                    Swal.fire({
                        title: 'Enviando...',
                        text: 'Por favor espere mientras procesamos su solicitud.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Enviar datos al servidor
                    $.ajax({
                        url: 'enviar_cuestionario.php',
                        type: 'POST',
                        data: {
                            id_cliente: idclie,
                            id: selectedCuestionarioId,
                            idrescuestionario: idrescuestionario,
                            enviado: enviado
                        },
                        success: function (res) {
                            console.log(res)
                            try {
                                const response = JSON.parse(res);
                                Swal.close();

                                if (response.status === 'success') {
                                    Swal.fire('¡Enviado!', 'Tu cuestionario ha sido enviado.', 'success')
                                        .then(() => {
                                            // Ocultar mensaje de "no enviado"
                                            $("#divCuestionarioNoEnviado").hide();

                                            // Agregar fila a la tabla para primer envío o reenvío con nuevo id
                                            if (response.id) {
                                                agregarFilaTabla(response.id, selectedCuestionarioId);
                                            }
                                        });
                                } else {
                                    Swal.fire('¡Error!', response.message, 'error');
                                }
                            } catch (e) {
                                console.error('Error al procesar respuesta:', e);
                                Swal.fire('¡Error!', 'Error al procesar la respuesta del servidor.', 'error');
                            }
                        },
                        error: function () {
                            Swal.close();
                            Swal.fire('¡Error!', 'No se pudo conectar al servidor.', 'error');
                        }
                    });
                });
            });

            // Función para agregar una nueva fila a la tabla de cuestionarios
            function agregarFilaTabla(idGenerado, idCuestionario) {
                // Verificar si ya existe una fila con este ID para evitar duplicados
                if ($("#fila-cuestionario-" + idGenerado).length === 0) {
                    const nuevaFila = `
            <tr id="fila-cuestionario-${idGenerado}">
                <td>${idGenerado}</td>
                <td>${cliente_wedding_date || ''}</td>
                <td>Pendiente</td>
                <td>Pendiente</td>
                <td>Enviado</td>
                <td>
                    <button class="btn btn-outline-secondary btn-sm" 
                        id="mandarCuestionario"
                        type="button"
                        data-enviado="1"
                        data-idcuestionario="${idCuestionario}">
                        Mandar cuestionario
                    </button>
                </td>
            </tr>
        `;
                    $("#tablaCuestionario tbody").prepend(nuevaFila);
                }
            }



            $(document).off('click', '#subirContrato').on('click', '#subirContrato', function () {
                // Obtener el archivo que se ha seleccionado en el input
                let contratoCliente = $("#contratoCliente")[0].files[0]; // Usamos [0] para acceder al primer archivo (en caso de ser un solo archivo)
                let nuevo = $(this).data('nuevo');
                console.log('nuevo ', nuevo)
                // Verificar si se ha seleccionado un archivo
                if (!contratoCliente) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Por favor, suba el contrato.',
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                    return; // Si no hay archivo, no hacemos la solicitud
                }

                const loadingSwal = Swal.fire({
                    title: 'Subiendo el contrato...',
                    text: 'Please wait while we process your request.',
                    allowOutsideClick: false, // Prevenir que el usuario cierre la alerta
                    customClass: {
                        popup: 'swal2-popup-center'
                    },
                    didOpen: () => {
                        Swal.showLoading(); // Mostrar el spinner de carga
                    }
                });

                // Crear un objeto FormData para enviar el archivo junto con otros datos
                let formData = new FormData();
                formData.append('contrato', contratoCliente); // Agregar el archivo
                formData.append('firmado', 0); // 0 porque es contrato sin firma
                formData.append('idclie', idclie); // Agregar el id del cliente
                formData.append('id_vendedor_asignado', id_vendedor_asignado); // Agregar el id del cliente
                formData.append('mandarcorreo', 0); // Agregar el id del cliente


                // Enviar la solicitud AJAX
                $.ajax({
                    url: 'agregar_contrato.php',
                    method: 'POST',
                    data: formData,
                    contentType: false, // Esto es importante para enviar el archivo correctamente
                    processData: false, // Esto también es necesario para manejar correctamente el FormData
                    success: function (response) {
                        var res = JSON.parse(response);

                        console.log("agregar_contrato ", res)

                        loadingSwal.close(); // Cerrar el spinner de carga
                        if (res.status === 'success') {
                            // Si la respuesta es éxito, mostrar el mensaje de éxito
                            Swal.fire({
                                title: 'Éxito',
                                text: res.message,
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {

                            });
                            let ruta_contrato = res.ruta_contrato;
                            let id_contrato = res.id_contrato;

                            if (nuevo == 1) {
                                $("#labelContrato").text("Adjuntar contrato firmado Sube el archivo del contrato ya firmado");
                                // Limpiar el campo de archivo
                                let fechaRegistroContrato = moment().format('YYYY-MM-DD'); // Fecha actual
                                let horaRegistroContrato = moment().format('hh:mm a'); // Hora actual
                                // Limpiar el campo de archivo
                                $("#tablaContrato tbody").prepend("<tr><td>" + id_contrato + "</td><td>" +
                                    fechaRegistroContrato + "</td><td>" + horaRegistroContrato + "</td><td>" +
                                    'Pendiente' + "</td><td>" + 'Pendiente' + "</td><td>" +
                                    'Contrato subido' + "</td><td>" +
                                    '<button style="margin-right:10px" class="btn btn-outline-secondary btn-vercontrato" data-ruta="' + ruta_contrato + '" data-id="' + id_contrato + '">Ver contrato</button>' +
                                    '<button style="margin-right:10px" id="divMandarCorreoContrato" class="btn btn-outline-secondary btn-mandarcorreocontrato" data-ruta="' + ruta_contrato + '" data-id="' + id_contrato + '">Mandar correo</button>' +
                                    '<button id="divCambiarEstatusContrato" class="btn btn-success btn-contratofirmado" data-id="' + id_contrato + '">Cambiar a firmado</button>' +
                                    "</td></tr>");

                            } else {
                                $('#tablaContrato tbody tr').each(function () {
                                    var row = $(this);

                                    let fechaRegistroContrato = moment().format('YYYY-MM-DD'); // Fecha actual
                                    let horaRegistroContrato = moment().format('hh:mm a'); // Hora actual
                                    let ruta_contrato = res.ruta_contrato;
                                    let id_contrato = res.id_contrato;

                                    $("#labelContrato").text("Adjuntar contrato firmado Sube el archivo del contrato ya firmado");


                                    row.find('td').eq(6).html('<button style="margin-right:10px" class="btn btn-outline-secondary btn-vercontrato" data-ruta="' + ruta_contrato + '" data-id="' + id_contrato + '">Ver contrato</button>' +
                                        '<button style="margin-right:10px" id="divMandarCorreoContrato" class="btn btn-outline-secondary btn-mandarcorreocontrato" data-ruta="' + ruta_contrato + '" data-id="' + id_contrato + '">Mandar correo</button>' +
                                        '<button id="divCambiarEstatusContrato" class="btn btn-success btn-contratofirmado" data-id="' + id_contrato + '">Cambiar a firmado</button>');



                                });


                                //  let fechaRegistroContrato= moment().format('YYYY-MM-DD'); // Fecha actual
                                //  let horaRegistroContrato = moment().format('hh:mm a'); // Hora actual
                                //  let ruta_contrato = res.ruta_contrato;
                                //  let id_contrato = res.id_contrato;

                                //  $("#labelContrato").text("Adjuntar contrato firmado"); 



                                //         var $tablaTbody = $("#tablaContrato tbody");

                                //         if ($tablaTbody.length > 0 && $tablaTbody.find("tr").length > 0) {
                                //             $tablaTbody.find("tr:first").remove(); // Eliminar solo la primera fila
                                //         }

                                // // Agrega la nueva fila al principio del tbody
                                // var nuevaFila = "<tr><td>" + id_contrato + "</td><td>" +
                                //     fechaRegistroContrato + "</td><td>" + horaRegistroContrato + "</td><td>" +
                                //     'Pendiente' + "</td><td>" + 'Pendiente' + "</td><td>" +
                                //     'Contrato subido' + "</td><td>" + 
                                //     '<button style="margin-right:10px" class="btn btn-outline-secondary btn-vercontrato" data-ruta="' + ruta_contrato + '" data-id="' + id_contrato + '">Ver contrato</button>' +
                                //     '<button style="margin-right:10px" id="divMandarCorreoContrato" class="btn btn-outline-secondary btn-mandarcorreocontrato" data-ruta="'+ruta_contrato+'" data-id="' + id_contrato + '">Mandar correo</button>' +
                                //     '<button id="divCambiarEstatusContrato" class="btn btn-success btn-contratofirmado" data-id="' + id_contrato + '">Cambiar a firmado</button>' +

                                //     "</td></tr>";

                                //     // Asegúrate de agregar la nueva fila al tbody
                                //     $tablaTbody.prepend(nuevaFila);


                            }


                            $("#contratoCliente").val("");
                        } else {
                            // Si hay algún error, mostrar el mensaje de error
                            Swal.fire({
                                title: 'Error',
                                text: res.message,
                                icon: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        loadingSwal.close();
                        // Si hubo un error en la solicitud AJAX
                        Swal.fire({
                            title: 'Error',
                            text: 'Hubo un problema al procesar la solicitud. Intenta nuevamente.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });
            });
            $(document).on('click', '.btn-mandarcorreocontrato, #btn-mandarcorreocontrato', function () {
                let contratoId = $(this).data('id');
                let rutaContrato = $(this).data('ruta');
                console.log('rutaContratomandarcorreocontrato ', rutaContrato)
                const loadingSwal = Swal.fire({
                    title: 'Mandando Correo...',
                    text: 'Please wait while we process your request.',
                    allowOutsideClick: false, // Prevenir que el usuario cierre la alerta
                    customClass: {
                        popup: 'swal2-popup-center'
                    },
                    didOpen: () => {
                        Swal.showLoading(); // Mostrar el spinner de carga
                    }
                });

                let formData = new FormData();
                formData.append('firmado', 1); // 0 porque es contrato sin firma
                formData.append('idclie', idclie); // Agregar el id del cliente
                formData.append('idcontrato', contratoId); // Agregar el id del contrato
                formData.append('rutacontrato', rutaContrato); // Agregar el id del contrato
                formData.append('id_vendedor_asignado', id_vendedor_asignado); // Agregar el id del cliente
                formData.append('mandarcorreo', 1); // Agregar el id del cliente

                $.ajax({
                    url: 'agregar_contrato.php',
                    method: 'POST',
                    data: formData,
                    contentType: false, // Esto es importante para enviar el archivo correctamente
                    processData: false, // Esto también es necesario para manejar correctamente el FormData
                    success: function (response) {
                        console.log(response)
                        var res = JSON.parse(response);
                        loadingSwal.close(); // Cerrar el spinner de carga
                        if (res.status === 'success') {
                            // Si la respuesta es éxito, mostrar el mensaje de éxito


                            Swal.fire({
                                title: 'Éxito',
                                text: res.message,
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {

                            });
                            $('#tablaContrato tbody tr').each(function () {
                                var row = $(this);
                                var id = row.find('.btn-aprobarpago').data('id');



                                row.find('td').eq(5).text("Contrato enviado");



                            });

                        } else {
                            // Si hay algún error, mostrar el mensaje de error
                            Swal.fire({
                                title: 'Error',
                                text: res.message,
                                icon: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        loadingSwal.close();
                        // Si hubo un error en la solicitud AJAX
                        Swal.fire({
                            title: 'Error',
                            text: 'Hubo un problema al procesar la solicitud. Intenta nuevamente.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });

            })
            $(document).on('click', '.btn-contratofirmado, #btn-contratofirmado', function () {

                let contratoId = $(this).data('id');

                if (!contratoId) {
                    Swal.fire({
                        title: 'Error',
                        text: 'No se obtuvo la información del contrato.',
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                    return;
                }

                Swal.fire({
                    title: '¿Estás seguro?',
                    html: `
            <label style="font-weight: normal; font-size: 20px;">
            <input type="checkbox" id="chkContratoFirmado" style="transform: scale(2); margin-right: 4px;">
            Confirmo que este es el contrato firmado
        </label>
        `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, continuar',
                    cancelButtonText: 'Cancelar',
                    preConfirm: () => {
                        const check = document.getElementById('chkContratoFirmado').checked;
                        if (!check) {
                            Swal.showValidationMessage('Debes confirmar que es el contrato firmado');
                            return false;
                        }
                        return true;
                    }
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const loadingSwal = Swal.fire({
                        title: 'Cambiando estatus...',
                        text: 'Please wait while we process your request.',
                        allowOutsideClick: false,
                        customClass: {
                            popup: 'swal2-popup-center'
                        },
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    let formData = new FormData();
                    formData.append('firmado', 1);
                    formData.append('idclie', idclie);
                    formData.append('idcontrato', contratoId);
                    formData.append('id_vendedor_asignado', id_vendedor_asignado);
                    formData.append('mandarcorreo', 0);

                    $.ajax({
                        url: 'agregar_contrato.php',
                        method: 'POST',
                        data: formData,
                        contentType: false,
                        processData: false,
                        success: function (response) {
                            var res = JSON.parse(response);
                            loadingSwal.close();
                            if (res.status === 'success') {
                                Swal.fire({
                                    title: 'Éxito',
                                    text: res.message,
                                    icon: 'success',
                                    confirmButtonText: 'Aceptar'
                                }).then(() => {
                                    let fechaFirmaContrato = moment().format('YYYY-MM-DD');
                                    let horaFirmaContrato = moment().format('hh:mm a');
                                    $('#tablaContrato tbody tr').each(function () {
                                        var row = $(this);
                                        row.find('td').eq(3).text(fechaFirmaContrato);
                                        row.find('td').eq(4).text(horaFirmaContrato);
                                        row.find('td').eq(5).text("Contrato firmado");
                                    });

                                    $("#divCambiarEstatusContrato").hide();
                                    $("#divInputsContrato").hide();
                                    $("#divMandarCorreoContrato").hide();
                                    $("#contratoCliente").val("");
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: res.message,
                                    icon: 'error',
                                    confirmButtonText: 'Aceptar'
                                });
                            }
                        },
                        error: function (xhr, status, error) {
                            loadingSwal.close();
                            Swal.fire({
                                title: 'Error',
                                text: 'Hubo un problema al procesar la solicitud. Intenta nuevamente.',
                                icon: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    });
                });
            });

            $(document).on('click', '.btn-vercontrato, #btn-vercontrato', function () {
                console.log("vercontrato");

                // Verificar si el modal ya está abierto
                if ($('#modal-pdf').length > 0) {
                    // Si el modal ya existe, no hacer nada
                    return;
                }

                // Obtener los datos del botón
                let contratoId = $(this).data('id');
                let rutacontrato = $(this).data('ruta');

                // Destruir el modal anterior si ya existe para evitar duplicados
                $('#modal-pdf').remove();

                // Crear el contenido del modal
                var modalHtml = `
        <div class="modal fade" id="modal-pdf" tabindex="-1" aria-labelledby="modal-pdfLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-pdfLabel">Vista previa del PDF</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <iframe src="https://citas.efegepho.com.mx/${rutacontrato}" width="100%" height="500px"></iframe>
                    </div>
                    <div class="modal-footer">
                        <a href="https://citas.efegepho.com.mx/${rutacontrato}" download class="btn btn-secondary">Descargar PDF</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    `;

                // Agregar el modal al body
                $('body').append(modalHtml);

                // Mostrar el modal
                $('#modal-pdf').modal('show');

                // Opcionalmente, agregar un evento para cerrar el modal y limpiar después
                $('#modal-pdf').on('hidden.bs.modal', function () {
                    // Eliminar el modal cuando se cierre
                    $(this).remove();
                });
            });

            $(document).off('click', '#agregarGaleriaManual').on('click', '#agregarGaleriaManual', function () {
                let fechaVencimientoGaleria = $("#fechaVencimientoGalInput").val();
                let fechaRegistroGaleria = $("#fechaRegistroGalInput").val();
                let horaRegistroGaleria = moment().format('HH:mm:ss');
                if (!fechaVencimientoGaleria || !fechaRegistroGaleria) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Por favor, ingrese todos los campos.',
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                    return;
                }

                let data = {
                    idclie,
                    fechaRegistroGaleria,
                    horaRegistroGaleria,
                    fechaVencimientoGaleria,
                    whatsapp: 0,
                    manual: 2,
                }

                const loadingSwal = Swal.fire({
                    title: 'Submitting...',
                    text: 'Please wait while we process your request.',
                    allowOutsideClick: false, // Prevenir que el usuario cierre el alerta

                    customClass: {
                        popup: 'swal2-popup-center'
                    },
                    didOpen: () => {
                        Swal.showLoading(); // Mostrar el spinner de carga
                    }
                });
                $.ajax({
                    url: 'guardar_galeria.php',
                    method: 'POST',
                    data: data,
                    success: function (response) {
                        console.log(response);
                        loadingSwal.close();
                        var res = JSON.parse(response);
                        if (res.status === 'success') {
                            let idGaleria = res.id;

                            // Mostrar un Swal de éxito
                            Swal.fire({
                                title: 'Éxito',
                                text: res.message,
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {


                                let estatusGaleria = "Aún no comprado";
                                var botonCambiarEstatus =
                                    "<button class='btn btn-outline-secondary btn-cambiaestatusgaleria' data-estatus='1' data-id='" + idGaleria + "' style='margin-right:10px'>Cambiar a comprado</button>" +
                                    "<button class='btn btn-outline-secondary btn-cambiaestatusgaleria' data-estatus='2' data-id='" + idGaleria + "'>Marcar como no lo compró</button>";

                                let botonWhatsapp =
                                    "<button style='margin-left:8px;' class='btn btn-outline-secondary btn-enviarWhatsapp' " +
                                    "data-idclie='" + idclie + "' " +
                                    "data-telefono='" + cliente_telefono + "' " +
                                    "data-country_code='" + country_code + "' " +
                                    "data-vencimiento='" + fechaVencimientoGaleria + "' " +
                                    "data-horaRegistroGaleria='" + horaRegistroGaleria + "'>Mandar WhatsApp</button>";

                                //   var botonAprobado = data.estatus == 0 && (data.forma_pago  == 2 || data.forma_pago == 3)
                                //     ? "<button class='btn btn-success btn-aprobarpago' data-id='" + idPago + "'>Aprobar</button>" 
                                //     : data.estatus == 1 && (data.forma_pago  == 2 || data.forma_pago == 3) ?"<button class='btn btn-success' disabled>Aprobado</button>": "";

                                // let cuentaWhatsapp = wcuentas.filter((cuenta) => cuenta.id == selectWhatsapp);

                                $("#tablaGaleria tbody").prepend("<tr><td class='rowGal'  data-id='" + idGaleria + "'>" + idGaleria + "</td><td>" +
                                    fechaRegistroGaleria + "</td><td>" + horaRegistroGaleria +
                                    "</td><td>" + fechaVencimientoGaleria + "</td><td>" +
                                    estatusGaleria + "</td><td>" + botonCambiarEstatus + botonWhatsapp + "</td></tr>");

                                // Limpiar los campos

                                $('#modalAgregarGaleria').modal('hide'); // Cerrar el modal
                            });
                        } else {

                            // Mostrar un Swal de error
                            Swal.fire({
                                title: 'Error',
                                text: res.message,
                                icon: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        loadingSwal.close();
                        // Si hubo un error en la solicitud AJAX
                        Swal.fire({
                            title: 'Error',
                            text: 'Hubo un problema al procesar la solicitud. Intenta nuevamente.',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });


            })
            $(document).off('click', '#agregarMonto').on('click', '#agregarMonto', function () {
                // Obtener los valores de los inputs
                var monto = $("#montoInput").val();
                var concepto = $("#conceptoInput").val();
                var cuenta = $("#cuentaTransferenciaSelect").val();
                //var formaPagoSelect = $("#formaPagoInput").val(); // Obtener el valor de la forma de pago seleccionada
                // var formaPago = formaPagoSelect;
                var currency = $("#currencyInput").val(); // Obtener el valor de la forma de pago seleccionada
                // Validar si el monto, concepto y forma de pago están completos
                if (!monto || !concepto) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Por favor, ingrese todos los campos.',
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                    return;
                }

                var fechaActual = moment().format('YYYY-MM-DD');
                var horaActual = moment().format('hh:mm a');
                var estatus = 0; // El estado es 'pendiente'
                var id_pago = generarIdPago(12); // Aquí puedes cambiar por un valor real si es necesario

                let data = {
                    fechaActual,
                    horaActual,
                    fechaPago: "Pendiente",
                    horaPago: "Pendiente",
                    estatus,
                    idclie,
                    cuenta,
                    monto,
                    concepto,
                    forma_pago: 0,
                    id_pago,
                    // email,
                    currency,
                    resend: 0
                };

                const loadingSwal = Swal.fire({
                    title: 'Submitting...',
                    text: 'Please wait while we process your request.',
                    allowOutsideClick: false, // Prevenir que el usuario cierre el alerta

                    customClass: {
                        popup: 'swal2-popup-center'
                    },
                    didOpen: () => {
                        Swal.showLoading(); // Mostrar el spinner de carga
                    }
                });

                $.ajax({
                    url: 'procesar_pago.php',  // Este es el archivo PHP que manejará la sesión de Stripe
                    method: 'POST',
                    data: data,
                    success: function (res) {
                        let response = JSON.parse(res);
                        // El servidor devuelve la URL de Stripe si todo sale bien
                        if (response.success) {
                            // Redirigir a Stripe para completar el pago
                            // window.location.href = response.url;
                            data.payment_url = response.session_url;
                            data.session_id = response.session_id;
                            console.log(data)
                            $.ajax({
                                url: 'agregar_monto.php',
                                method: 'POST',
                                data: data,
                                success: function (response) {
                                    console.log(response)
                                    var res = JSON.parse(response);
                                    loadingSwal.close();
                                    if (res.status === 'success') {
                                        let idPago = res.id_pago;
                                        let idPagoGenerado = res.id_generado;

                                        // Mostrar un Swal de éxito
                                        Swal.fire({
                                            title: 'Éxito',
                                            text: res.message,
                                            icon: 'success',
                                            confirmButtonText: 'Aceptar'
                                        })
                                        fechaActual = moment().format('DD-MM-YYYY');
                                        var montoFormateado = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(monto) + " " + currency.toUpperCase();

                                        // Aquí también puedes traducir el valor de formaPago si lo necesitas
                                        let formaPagoTexto = "pendiente";
                                        let estatusPago = data.estatus == 0 ? "pendiente" : "aprobado";

                                        var botonAprobado = "";
                                        if (data.estatus == 0 && data.forma_pago == 0) {
                                            botonAprobado = "<button class='btn btn-warning btn-aprobarpago' data-id='" + idPagoGenerado + "'>Aprobar</button>";
                                        } else if (data.estatus == 1 && (data.forma_pago == 2 || data.forma_pago == 3)) {
                                            botonAprobado = "<button class='btn btn-success' disabled>Aprobado</button>";
                                        }

                                        // Crear el botón "Reenviar link" dependiendo de la condición
                                        var botonReenviarLink = "";
                                        if (data.estatus == 0) {
                                            botonReenviarLink = "<button style='margin-right:8px' class='btn btn-outline-secondary btn-reenviarcorreopago' data-cuenta='" + data.cuenta + "'  data-idclie='" + data.id_clie + "' data-monto='" + data.monto + "' data-currency='" + data.currency + "' data-concepto='" + data.concepto + "' data-idpago='" + idPago + "' data-id='" + idPagoGenerado + "'>Reenviar Correo</button>";
                                        }

                                        // Construir la fila de la tabla
                                        var fila = "<tr><td>" + idPago + "</td><td>" + montoFormateado + "</td><td>" + fechaActual + "</td><td>" + horaActual +
                                            "</td><td>" + data.fechaPago + "</td><td>" + data.horaPago + "</td><td>" + data.concepto + "</td><td>" +
                                            formaPagoTexto + "</td><td>" + estatusPago + "</td>";

                                        // Añadir el botón adecuado basado en las condiciones
                                        fila += "<td>";
                                        if (data.estatus == 0) {
                                            fila += botonReenviarLink;
                                        }
                                        fila += botonAprobado + "</td>";
                                        fila += "</tr>";


                                        // Insertar la fila en la tabla
                                        $("#tablaPagos tbody").prepend(fila);
                                        // Limpiar los campos
                                        $("#montoInput").val("");
                                        $("#conceptoInput").val("");
                                        // $("#formaPagoInput").val("1"); // Restablecer el valor del select a "Tarjeta"
                                        $('#modalAgregarMonto').modal('hide'); // Cerrar el modal
                                    } else {
                                        // Mostrar un Swal de error
                                        Swal.fire({
                                            title: 'Error',
                                            text: res.message,
                                            icon: 'error',
                                            confirmButtonText: 'Aceptar'
                                        });
                                    }
                                },
                                error: function (xhr, status, error) {
                                    loadingSwal.close();
                                    // Si hubo un error en la solicitud AJAX
                                    Swal.fire({
                                        title: 'Error',
                                        text: 'Hubo un problema al procesar la solicitud1. Intenta nuevamente.',
                                        icon: 'error',
                                        confirmButtonText: 'Aceptar'
                                    });
                                }
                            });
                        } else {
                            loadingSwal.close();
                            // Mostrar mensaje de error si hubo algún problema
                            Swal.fire({
                                title: 'Error',
                                text: 'Hubo un problema al procesar el pago2. Intenta de nuevo.',
                                icon: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        loadingSwal.close();
                        console.error('Error en la solicitud AJAX4:', error);
                    }
                });
                // if(formaPagoSelect == 1){

                // }else {
                // //     data.payment_url = '';
                // // console.log("formaPagoSelect ",formaPagoSelect)
                // // console.log("id_pago ",id_pago)
                // // console.log("currency ",currency)
                // //   $.ajax({
                // //     url: 'agregar_monto.php',
                // //     method: 'POST',
                // //     data: data,
                // //     success: function(response) {
                // //         console.log(response)
                // //           loadingSwal.close();
                // //         var res = JSON.parse(response);
                // //         if (res.status === 'success') {
                // //             let idPago = res.id_pago;

                // //             // Mostrar un Swal de éxito
                // //             Swal.fire({
                // //                 title: 'Éxito',
                // //                 text: res.message,
                // //                 icon: 'success',
                // //                 confirmButtonText: 'Aceptar'
                // //             }).then(() => {
                // //                 fechaActual = moment().format('DD-MM-YYYY');
                // //                 var montoFormateado = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(monto)+" "+data.currency.toUpperCase();

                // //                 // Aquí también puedes traducir el valor de formaPago si lo necesitas
                // //                 let formaPagoTexto = formaPagoSelect == 1 ? "Tarjeta" : formaPagoSelect == 2 ? "Transferencia" : "Efectivo";
                // //                 let estatusPago = data.estatus == 0 ? "pendiente" : "aprobado";

                // //                   var botonAprobado = data.estatus == 0 && (data.forma_pago  == 2 || data.forma_pago == 3)
                // //                     ? "<button class='btn btn-success btn-aprobarpago' data-id='" + idPago + "'>Aprobar</button>" 
                // //                     : data.estatus == 1 && (data.forma_pago  == 2 || data.forma_pago == 3) ?"<button class='btn btn-success' disabled>Aprobado</button>": "";


                // //                 $("#tablaPagos tbody").prepend("<tr><td>" + idPago + "</td><td>" +
                // //                     montoFormateado + "</td><td>" + fechaActual + "</td><td>" + horaActual +
                // //                     "</td><td>" + data.fechaPago + "</td><td>" + data.horaPago + "</td><td>" +
                // //                     data.concepto + "</td><td>" + formaPagoTexto + "</td><td>" + estatusPago + "</td><td>"+botonAprobado + "</td></tr>");

                // //                 // Limpiar los campos
                // //                 $("#montoInput").val("");
                // //                 $("#conceptoInput").val("");
                // //                 $("#formaPagoInput").val("1"); // Restablecer el valor del select a "Tarjeta"
                // //                 $('#modalAgregarMonto').modal('hide'); // Cerrar el modal
                // //             });
                // //         } else {
                // //              loadingSwal.close();
                // //             // Mostrar un Swal de error
                // //             Swal.fire({
                // //                 title: 'Error',
                // //                 text: res.message,
                // //                 icon: 'error',
                // //                 confirmButtonText: 'Aceptar'
                // //             });
                // //         }
                // //     },
                // //     error: function(xhr, status, error) {
                // //          loadingSwal.close();
                // //         // Si hubo un error en la solicitud AJAX
                // //         Swal.fire({
                // //             title: 'Error',
                // //             text: 'Hubo un problema al procesar la solicitud3. Intenta nuevamente.',
                // //             icon: 'error',
                // //             confirmButtonText: 'Aceptar'
                // //         });
                // //     }
                // // });
                // }

            });
            $(document).off('click', '#cerrarModalGaleria').on('click', '#cerrarModalGaleria', function () {
                $('#modalAgregarGaleria').modal('hide');
            })
            $(document).off('click', '#cerrarModalMonto').on('click', '#cerrarModalMonto', function () {
                $('#modalAgregarMonto').modal('hide');

            })
            // Función para mostrar el SweetAlert cuando se da clic en el botón
            galeriasCliente.forEach(galeria => {
                let idGaleria = galeria.id;

                // Validación para asegurarse de que la fecha está en un formato correcto
                let fechaRegistroGaleria = galeria.fecha_registro_galeria
                    ? moment(galeria.fecha_registro_galeria).format('DD-MM-YYYY')
                    : 'Fecha no disponible'; // Si no está presente, asigna un valor por defecto

                let horaRegistroGaleria = moment(galeria.hora_registro_galeria, 'HH:mm:ss').format('hh:mm a');
                let fechaVencimientoGaleria = moment(galeria.fecha_vencimiento_galeria).format('DD-MM-YYYY');

                // Filtrar el array de cuentas de WhatsApp
                let cuentaWhatsapp = wcuentas.filter((cuenta) => cuenta.id == galeria.id_whatsapp);

                // Verificar si se encontró una cuenta de WhatsApp
                let numeroWhatsapp = cuentaWhatsapp.length > 0 ? cuentaWhatsapp[0].numero : 'No asignado';

                let estatusGaleria =
                    galeria.estatus == 0 ? "Aún no comprado" :
                        galeria.estatus == 1 ? "Comprado" :
                            galeria.estatus == 2 ? "No lo compró" :
                                "Eliminado";

                var botonCambiarEstatus = galeria.estatus == 0
                    ? "<button class='btn btn-outline-secondary btn-cambiaestatusgaleria' data-estatus='1' data-id='" + idGaleria + "' style='margin-right:10px'>Cambiar a comprado</button>" +
                    "<button class='btn btn-outline-secondary btn-cambiaestatusgaleria' data-estatus='2' data-id='" + idGaleria + "'>Marcar como no lo compró</button>"
                    : galeria.estatus == 1
                        ? "<button class='btn btn-cambiaestatusgaleria btn-success' data-estatus='3' data-id='" + idGaleria + "'>Eliminar</button>"
                        : "";

                let botonWhatsapp = galeria.estatus == 0
                    ? "<button style='margin-left:8px;' class='btn btn-outline-secondary btn-enviarWhatsapp' data-idclie='" + idclie + "' data-telefono='" + cliente_telefono + "' data-country_code='" + country_code + "' data-vencimiento='" + galeria.fecha_vencimiento_galeria + "' data-horaRegistroGaleria='" + galeria.hora_registro_galeria + "'>Mandar WhatsApp</button>"
                    : '';

                // Crear la fila de la tabla
                $("#tablaGaleria tbody").prepend("<tr><td class='rowGal' data-id='" + idGaleria + "'>" + idGaleria + "</td><td>" +
                    fechaRegistroGaleria + "</td><td>" + horaRegistroGaleria +
                    "</td><td>" + fechaVencimientoGaleria + "</td><td>" +
                    estatusGaleria + "</td><td>" + botonCambiarEstatus + botonWhatsapp + "</td></tr>");
            });

            pagosCliente.forEach(pago => {
                let idPago = pago.id_pago
                let id_cuenta = pago.id_cuenta
                console.log("id_cuenta ", id_cuenta)
                var fechaAsing = moment(pago.fecha).format('DD-MM-YYYY');
                var horaAsing = moment(pago.hora, 'HH:mm:ss').format('hh:mm a');
                var fechaPago = pago.fecha_pago != "0000-00-00" ? moment(pago.fecha_pago).format('DD-MM-YYYY') : "Pendiente";
                var horaPago = pago.hora_pago != "00:00:00" ? moment(pago.hora_pago, 'HH:mm:ss').format('hh:mm a') : "Pendiente";
                let concepto = pago.concepto;
                let forma_pago = pago.forma_pago == 1 ? "Tarjeta" : pago.forma_pago == 2 ? "Transferencia" : pago.forma_pago == 3 ? "Efectivo" : "Pendiente";
                let currency = pago.currency.toUpperCase();


                let estatusPago = pago.estatus == 0 ? "Pendiente" : "Aprobado";
                var montoFormateado = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(pago.monto) + " " + currency; // Formatear el monto como precio en pesos mexicanos

                // Crear el botón dependiendo del estatus
                var botonAprobado = pago.estatus == 0 && pago.forma_pago == 0
                    ? "<button class='btn btn-warning btn-aprobarpago' data-id='" + pago.id + "'>Aprobar</button>"
                    : pago.estatus == 1 && pago.forma_pago != 0 ? "<button class='btn btn-success' disabled>Aprobado</button>" : "";
                var botonReenviarLink = pago.estatus == 0 && pago.forma_pago == 0
                    ? "<button style='margin-right:8px' class='btn btn-outline-secondary btn-reenviarcorreopago' data-cuenta='" + pago.id_cuenta + "' data-idclie='" + pago.id_clie + "'data-monto='" + pago.monto + "' data-currency='" + pago.currency + "' data-concepto='" + concepto + "' data-idpago='" + idPago + "' data-id='" + pago.id + "'>Reenviar correo</button>"

                    : "";
                // Agregar la fila a la tabla
                $("#tablaPagos tbody").append("<tr>" +
                    "<td>" + idPago + "</td>" +
                    "<td>" + montoFormateado + "</td>" +
                    "<td>" + fechaAsing + "</td>" +
                    "<td>" + horaAsing + "</td>" +
                    "<td>" + fechaPago + "</td>" +
                    "<td>" + horaPago + "</td>" +
                    "<td>" + concepto + "</td>" +
                    "<td>" + forma_pago + "</td>" +

                    // Aquí, agregamos la condición para estatusPago
                    "<td class='" + (pago.estatus == 1 ? 'text-verde' : '') + "'>" + estatusPago + "</td>" +

                    "<td>" + (pago.estatus == 0 ? botonReenviarLink : '') + botonAprobado + "</td>" +

                    "</tr>");


            });

            $("#tablaNext tbody").empty();

            if (nextstapesCliente && nextstapesCliente.length > 0) {
                // Ordenamos los registros por fecha y hora (del más antiguo al más reciente)
                const registrosOrdenados = nextstapesCliente.sort((a, b) => {
                    const fechaA = new Date(`${a.fecha_registro}T${a.hora_registro}`);
                    const fechaB = new Date(`${b.fecha_registro}T${b.hora_registro}`);
                    return fechaA - fechaB;
                });

                // Tomamos el primer registro (el más antiguo)
                const primerRegistro = registrosOrdenados[0];

                if (primerRegistro) {
                    let fechaRegistro = moment(primerRegistro.fecha_registro).format('DD-MM-YYYY');
                    let horaRegistro = moment(primerRegistro.hora_registro, 'HH:mm:ss').format('hh:mm a');

                    $("#tablaNext tbody").append(`
            <tr>
                <td>${primerRegistro.id}</td>
                <td>${primerRegistro.id_cliente}</td>
                <td>${fechaRegistro}</td>
                <td>${horaRegistro}</td>
                <td>${primerRegistro.documento}</td>
                <td>
                    <button class="btn btn-primary btn-sm verDocumento" data-documento="${primerRegistro.nombre_archivo}">Ver</button>
                    <button class="btn btn-primary btn-sm reenviarCorreoPdf" data-id="${primerRegistro.id_cliente}">Reenviar</button>
                </td>
            </tr>
        `);
                }
            } else {
                $("#tablaNext tbody").append(`
        <tr>
            <td colspan="6" class="text-center">No hay registros para este cliente</td>
        </tr>
    `);
            }



            console.log("contratosCliente ", contratosCliente)
            contratosCliente.forEach(contrato => {
                console.log("contrato ", contrato)
                let idContrato = contrato.id
                var fechaRegistro = moment(contrato.fecha_registro).format('DD-MM-YYYY');
                var horaRegistro = moment(contrato.hora_registro, 'HH:mm:ss').format('hh:mm a');
                let estatusContrato = contrato.estatus == 1 ? "Contrato subido" : contrato.estatus == 2 ? "Contrato enviado" : "Firmado"
                let fechaFirma = contrato.fecha_firma != '0000-00-00' ? moment(contrato.fecha_firma).format('DD-MM-YYYY') : 'Pendiente';
                let horaFirma = contrato.hora_firma != '00:00:00' ? moment(contrato.hora_firma, 'HH:mm:ss').format('hh:mm a') : 'Pendiente';
                let rutaContrato = contrato.ruta_contrato;
                $("#labelContrato")
                    .text("Adjuntar contrato firmado. Sube el archivo del contrato ya firmado")
                    .css({
                        "font-size": "16px"
                    });
                // Crear el botón dependiendo del estatus
                var botonVerContrato =
                    "<button class='btn btn-outline-secondary btn-vercontrato'  data-ruta='" + rutaContrato + "' data-id='" + idContrato + "' style='margin-right:10px'>Ver contrato</button>";
                var botonCambiarFirmadoContrato =
                    contrato.estatus == 1 || contrato.estatus == 2 ? "<button id='divCambiarEstatusContrato' class='btn btn-success btn-contratofirmado' data-id='" + idContrato + "'>Cambiar a firmado</button>" : '';
                var botonMandarCorreoContrato =
                    contrato.estatus == 1 || contrato.estatus == 2 ? "<button id='divMandarCorreoContrato' style='margin-right:10px' class='btn btn-outline-secondary btn-mandarcorreocontrato' data-ruta='" + rutaContrato + "' data-id='" + idContrato + "'>Mandar correo</button>" : '';

                if (contrato.estatus == 3) {
                    $("#divCambiarEstatusContrato").hide();
                    $("#divInputsContrato").hide();
                    $("#divMandarCorreoContrato").hide();
                }

                // Agregar la fila a la tabla
                $("#tablaContrato tbody").append("<tr>" +
                    "<td>" + idContrato + "</td>" +
                    "<td>" + fechaRegistro + "</td>" +
                    "<td>" + horaRegistro + "</td>" +
                    "<td>" + fechaFirma + "</td>" +
                    "<td>" + horaFirma + "</td>" +
                    "<td>" + estatusContrato + "</td>" +
                    "<td>" + botonVerContrato + botonMandarCorreoContrato + botonCambiarFirmadoContrato + "</td>" +


                    "</tr>");

            });

        });

        $(document).on('click', '.btn-reagendar, #btn-reagendar', function () {
            var id = $(this).data('id'); // id del meet
            var idusu = $(this).data('idusu'); // id de usuario
            var idclie = $(this).data('idclie'); // id de cliente
            var fecha = $(this).data('fecha');
            var hora = $(this).data('hora');

            if (idusu == 0 || idusu == "0") {
                // cambiar el título
                $('#reagendarModal').text('Asignar nuevo vendedor');
                $('#confirmar-reagendar').text('Asignar');
                $('#last_date').text('Fecha anterior: ' + fecha);
                $('#last_time').text('Hora anterior: ' + hora);



            }

            if (idusu != 0) {
                // Mostrar el nuevo botón de reagendar
                if ($('#reagendarBtn').length === 0) { // Verifica si el botón con el ID "reagendarBtn" ya existe
                    // Mostrar el nuevo botón de reagendar solo si no existe
                    $('#modal-reagendar-body #btnReag').append('<button type="button" class="btn btn-dark" id="reagendarBtn">Mandar correo de reagenda al cliente</button>');
                }

                // Acción al hacer clic en el botón de reagendar
                $('#reagendarBtn').on('click', function () {
                    // Mostrar la alerta de confirmación
                    Swal.fire({
                        title: '¿Estás seguro de mandar el correo?',
                        text: "Una vez confirmado, se enviará el correo de reagendamiento.",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, enviar correo',
                        cancelButtonText: 'No, cancelar',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const loadingSwal = Swal.fire({
                                title: 'Enviando...',
                                text: 'Please wait while we process your request.',
                                allowOutsideClick: false, // Prevenir que el usuario cierre el alerta
                                didOpen: () => {
                                    Swal.showLoading(); // Mostrar el spinner de carga
                                }
                            });
                            // Si el usuario confirma, hacer la solicitud AJAX
                            $.ajax({
                                url: 'enviar-correo-reagenda.php',
                                method: 'POST',
                                data: {
                                    id: id,
                                    idclie: idclie
                                },
                                success: function (response) {
                                    console.log(response)
                                    // Parsear la respuesta JSON
                                    var res = JSON.parse(response);
                                    loadingSwal.close();
                                    // Verificar el estado de la respuesta
                                    if (res.status === 'success') {
                                        // Mostrar un Swal de éxito si el correo fue enviado correctamente
                                        Swal.fire({
                                            title: 'Éxito',
                                            text: res.message,
                                            icon: 'success',
                                            confirmButtonText: 'Aceptar'
                                        });
                                    } else {
                                        // Mostrar un Swal de error si hubo un problema
                                        Swal.fire({
                                            title: 'Error',
                                            text: res.message,
                                            icon: 'error',
                                            confirmButtonText: 'Aceptar'
                                        });
                                    }
                                },
                                error: function (xhr, status, error) {
                                    // Si hubo un error en la solicitud AJAX
                                    Swal.fire({
                                        title: 'Error',
                                        text: 'Hubo un problema al procesar la solicitud. Intenta nuevamente.',
                                        icon: 'error',
                                        confirmButtonText: 'Aceptar'
                                    });
                                }
                            });

                        } else {
                            // Si el usuario cancela la acción
                            Swal.fire({
                                title: 'Cancelado',
                                text: 'El envío del correo ha sido cancelado.',
                                icon: 'info'
                            });
                        }
                    });
                });
            }



            console.log(fecha);
            console.log(hora);

            // Obtener los usuarios desde PHP (JSON)

            // Limpiar el select antes de agregar los usuarios
            $('#select-usuarios').empty();

            // Añadir la opción predeterminada
            $('#select-usuarios').append('<option value="">Seleccionar usuario</option>');

            // Recorrer los usuarios y añadirlos como opciones en el select
            usuarios.forEach(function (usuario) {
                $('#select-usuarios').append('<option value="' + usuario.id + '">' + usuario.nombre + '</option>');
            });

            // Seleccionar el usuario con el id correspondiente
            $('#select-usuarios').val(idusu);

            // Disparar el evento "change" después de seleccionar el usuario automáticamente
            $('#select-usuarios').trigger('change');  // Esto simula que el usuario ha seleccionado una opción


            $('#reagenda-modal').append('<input type="hidden" name="reagendaId" id="reagendaId" value="' + id + '">');
            $('#reagenda-modal').append('<input type="hidden" name="customerId" id="customerId" value="' + idclie + '">');
            $('#reagenda-modal').append('<input type="hidden" name="hasSeller" id="hasSeller" value="' + idusu + '">');

            // Cerrar el modal de "Ver más"
            $('#verMasModal').modal('hide');

            // Mostrar el modal de "Reagendar"
            $('#modal-reagendar').modal('show');
        });






    });

    // Función para mostrar la imagen en un modal
    function verImagen(imagenUrl) {
        // Eliminar modal existente antes de crear uno nuevo
        $('#modal-imagen').remove();

        var modalHtml = `
        <div class="modal fade" id="modal-imagen" tabindex="-1" aria-labelledby="modal-imagenLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-imagenLabel">Vista previa de la imagen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <img src="${imagenUrl}" alt="Vista previa" class="img-fluid" style="max-width: 100%;">
                    </div>
                    <div class="modal-footer">
                        <a href="${imagenUrl}" download class="btn btn-secondary">Descargar imagen</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    `;
        $('body').append(modalHtml);
        $('#modal-imagen').modal('show');
    }

    // Función para mostrar el PDF en un modal
    function verPdf(pdfUrl) {
        console.log("pdfUrl ", pdfUrl)
        // Eliminar modal existente antes de crear uno nuevo
        $('#modal-pdf').remove();

        var modalHtml = `
        <div class="modal fade" id="modal-pdf" tabindex="-1" aria-labelledby="modal-pdfLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-pdfLabel">Vista previa del PDF</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <iframe src="${pdfUrl}" width="100%" height="500px"></iframe>
                    </div>
                    <div class="modal-footer">
                        <a href="${pdfUrl}" download class="btn btn-secondary">Descargar PDF</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    `;
        $('body').append(modalHtml);
        $('#modal-pdf').modal('show');
    }


    $(document).on('click', '.verDocumento', function () {
        const rutaDocumento = $(this).data('documento');

        // Asegúrate de que la ruta esté bien formada (puedes ajustar si es relativa o absoluta)
        if (rutaDocumento) {
            window.open(rutaDocumento, '_blank');
        } else {
            alert("No se encontró el documento.");
        }
    });



    $(document).on('click', '.reenviarCorreoPdf', function () {
        // Obtener el ID del cliente (asumiendo que está en un atributo data-id del botón o fila)
        var idCliente = $(this).data('id'); // O usa $(this).closest('tr').data('id') si está en la fila

        // Mostrar confirmación
        Swal.fire({
            title: '¿Reenviar PDF?',
            text: "¿Estás seguro que deseas reenviar el enlace del PDF al cliente?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, reenviar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar loading
                Swal.fire({
                    title: 'Enviando...',
                    html: 'Por favor espera mientras se reenvía el correo',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Hacer la petición AJAX
                $.ajax({
                    url: 'reenviar_link_pdf.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { idcliente: idCliente },
                    success: function (response) {
                        Swal.close();
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Ocurrió un error al reenviar el correo'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ocurrió un error al comunicarse con el servidor: ' + error
                        });
                    }
                });
            }
        });
    });


</script>
</body>

</html>