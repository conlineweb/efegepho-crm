<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Mexico/General');
include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexi贸n a la base de datos

$tipoUsuario = $_SESSION['tus'];
$idUsu = $_SESSION['uid'];

$startDate = '';
$endDate = '';
if (empty($_GET['show_all'])) {
    $startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
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


// Obtener paquetes para el formulario post-sesión
$paquetesArr = [];
$paquetesResult = $conn->query("SELECT id, nombre FROM paquetes ORDER BY id");
if ($paquetesResult) {
    while ($rp = $paquetesResult->fetch_assoc()) $paquetesArr[] = $rp;
}

// Cerrar la conexión
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citas agendadas</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">

</head>
<style>
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
        font-size: inherit;
    }

    table {

        width: 100% !important;

    }

    .btn-cancel {
        background-color: #d3d3d3;
        /* Gris claro */
        border-color: #a9a9a9;
        /* Gris oscuro para el borde */
        color: #707070;
        /* Color del texto */
    }

    .btn-cancel:hover {
        background-color: #b0b0b0;
        /* Gris oscuro en hover */
        border-color: #808080;
        /* Gris más oscuro en hover */
        color: #404040;
        /* Color del texto más oscuro */
    }

    /* ===== POST-SESSION FORM MODAL ===== */
    #sfSessionOverlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.55);
        z-index: 10000;
        overflow-y: auto;
        padding: 30px 16px;
    }
    #sfSessionPanel {
        max-width: 680px;
        margin: 0 auto;
        background: #fff;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 24px 80px rgba(0,0,0,0.35);
        font-family: inherit;
    }
    #sfSessionHeader {
        background: #eee8dc;
        color:#464646;;
        padding: 22px 28px;
        position: relative;
    }
    #sfSessionHeader h2 {
        margin: 0 0 6px;
        font-size: 1.45rem;
        font-weight: 700;
        color: #464646;
    }
    #sfSessionHeader .sfm-meta {
        display: flex;
        gap: 18px;
        font-size: 0.82rem;
        opacity: 0.75;
        flex-wrap: wrap;
    }
    #sfSessionHeader .sfm-vendedor-box {
        position: absolute;
        top: 22px; right: 28px;
        text-align: center;
    }
    #sfSessionHeader .sfm-vendedor-label {
        background: #464646;
        color: #eee8dc;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        padding: 2px 10px;
        border-radius: 4px;
        letter-spacing: 0.5px;
    }
    #sfSessionHeader .sfm-vendedor-name {
        color: #464646;
        font-weight: 700;
        font-size: 0.95rem;
        margin-top: 4px;
    }
    #sfSessionBody {
        padding: 28px 32px;
        overflow-y: auto;
        max-height: calc(100vh - 220px);
    }
    .sfm-section {
        margin-bottom: 32px;
    }
    .sfm-section-title {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 16px;
    }
    .sfm-section-number {
        width: 32px; height: 32px;
        background: #464646;
        color: #fff;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .sfm-section-number.star-icon {
        background: #464646;
        color: #eee8dc;
        font-size: 1rem;
    }
    .sfm-section-heading h5 {
        margin: 0;
        font-weight: 700;
        font-size: 1rem;
        color: #464646;
    }
    .sfm-section-heading p {
        margin: 2px 0 0;
        font-size: 0.8rem;
        color: #777;
    }
    .sfm-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .sfm-field label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #555;
        margin-bottom: 4px;
        display: block;
    }
    .sfm-field .sfm-tag {
        display: inline-block;
        background: #464646;
        color: #eee8dc;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 1px 6px;
        border-radius: 4px;
        margin-left: 6px;
    }
    .sfm-field input[type="text"],
    .sfm-field input[type="email"],
    .sfm-field input[type="date"] {
        width: 100%;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 0.9rem;
        color: #333;
        outline: none;
        transition: border-color 0.2s;
    }
    .sfm-field input:focus {
        border-color: #464646;
    }
    .sfm-field .sfm-confirm-note {
        font-size: 0.75rem;
        color: #777;
        margin-top: 4px;
    }
    /* Origin section */
    .sfm-origin-box {
        border: 1.5px solid #464646;
        border-radius: 12px;
        padding: 18px 20px;
    }
    .sfm-origin-badge {
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
    .sfm-origin-title {
        font-weight: 700;
        font-size: 1rem;
        color: #333;
    }
    .sfm-category-btns {
        display: flex;
        gap: 12px;
        margin: 14px 0 16px;
        flex-wrap: wrap;
    }
    .sfm-category-btn {
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
    .sfm-category-btn .sfm-cat-icon {
        font-size: 1.5rem;
        display: block;
        margin-bottom: 6px;
    }
    .sfm-category-btn:hover {
        border-color: #464646;
    }
    .sfm-category-btn.active {
        border-color: #464646;
        background: #464646;
        color: #eee8dc;
    }
    .sfm-tipo-cliente-btn {
        flex: 1; min-width: 140px; border: 1.5px solid #e0e0e0; border-radius: 10px;
        padding: 14px 10px; text-align: center; cursor: pointer; background: #fff;
        font-size: 0.85rem; font-weight: 600; color: #444; transition: all 0.15s;
    }
    .sfm-tipo-cliente-btn .sfm-cat-icon { font-size: 1.5rem; display: block; margin-bottom: 6px; }
    .sfm-tipo-cliente-btn:hover { border-color: #464646; }
    .sfm-tipo-cliente-btn.active { border-color: #464646; background: #464646; color: #eee8dc; }
    .sfm-subcategory-select {
        width: 100%;
        border: 1.5px solid #ddd;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 0.9rem;
        color: #444;
        outline: none;
        background: #fff;
        cursor: pointer;
    }
    .sfm-subcategory-select:focus {
        border-color: #464646;
    }
    .sfm-how-to-ask {
        margin-top: 12px;
        font-size: 0.78rem;
        color: #666;
        font-style: italic;
    }
    /* Package radio buttons */
    .sfm-package-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .sfm-package-item {
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
    .sfm-package-item:hover {
        border-color: #464646;
    }
    .sfm-package-item.active {
        border-color: #eee8dc;
        background: #f5f5f3;
    }
    .sfm-package-item input[type="radio"] {
        display: none;
    }
    .sfm-package-left .sfm-package-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: #222;
    }
    .sfm-package-left .sfm-package-label {
        font-size: 0.78rem;
        color: #888;
    }
    .sfm-package-dot {
        width: 20px; height: 20px;
        border-radius: 50%;
        border: 2px solid #ccc;
        background: #fff;
        flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.15s;
    }
    .sfm-package-item.active .sfm-package-dot {
        border-color: #eee8dc;
        background: #eee8dc;
    }
    .sfm-package-item.active .sfm-package-dot::after {
        content: '';
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #fff;
    }
    /* Engagement buttons */
    .sfm-engagement-btns {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    .sfm-engagement-btn {
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
    .sfm-engagement-btn .sfm-eng-icon {
        font-size: 1.8rem;
        display: block;
        margin-bottom: 6px;
    }
    .sfm-engagement-btn .sfm-eng-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: #424242;
        display: block;
    }
    .sfm-engagement-btn.active .sfm-eng-name {
        color: #fff;
    }
    .sfm-engagement-btn .sfm-eng-desc {
        font-size: 0.75rem;
        color: #888;
        margin-top: 4px;
    }
    .sfm-engagement-btn:hover {
        border-color: #464646;
    }
    .sfm-engagement-btn.active {
        border-color: #464646;
        background: #464646;
    }
    /* ¿Desde cuánto nos conoces? choice cards */
    .sfm-known-us-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-top: 8px;
    }
    @media (max-width: 600px) {
        .sfm-known-us-grid { grid-template-columns: repeat(2, 1fr); }
    }
    .sfm-known-us-btn {
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
    .sfm-known-us-btn:hover { border-color: #464646; }
    .sfm-known-us-btn.active {
        border-color: #464646;
        background: #f5f1e8;
    }
    .sfm-known-us-btn .sfku-title {
        font-weight: 700;
        font-size: 0.88rem;
        color: #222;
        margin-bottom: 4px;
        display: block;
    }
    .sfm-known-us-btn .sfku-desc {
        font-size: 0.76rem;
        color: #888;
        line-height: 1.35;
        display: block;
    }
    .sfm-textarea {
        width: 100%;
        border: 1.5px solid #ddd;
        border-radius: 10px;
        padding: 12px 14px;
        font-size: 0.88rem;
        color: #444;
        resize: vertical;
        min-height: 100px;
        outline: none;
        transition: border-color 0.2s;
    }
    .sfm-textarea:focus {
        border-color: #464646;
    }
    /* Footer buttons */
    #sfSessionFooter {
        display: flex;
        gap: 12px;
        padding: 20px 32px 28px;
        border-top: 1px solid #eee;
    }
    #sfSessionFooter .sfm-btn-cancel {
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
    #sfSessionFooter .sfm-btn-cancel:hover {
        background: #f5f5f5;
    }
    #sfSessionFooter .sfm-btn-submit {
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
    #sfSessionFooter .sfm-btn-submit:hover {
        background: #5a5a5a;
         color: #eee8dc;
    }
    @media (max-width: 600px) {
        #sfSessionBody { padding: 20px 16px; }
        #sfSessionFooter { padding: 16px; }
        #sfSessionHeader { padding: 18px 16px; }
        .sfm-grid-2 { grid-template-columns: 1fr; }
        #sfSessionHeader .sfm-vendedor-box { position: static; margin-top: 12px; display: inline-block; }
    }
    
    /* ===== EFEGE MODERN TABLE STYLES ===== */
    :root {
      --gold: #C5A028;
      --gold-dim: rgba(197,160,40,0.12);
      --dark: #F8FAFC;
      --panel: #FFFFFF;
      --surface: #F1F5F9;
      --ink: #1E293B;
      --muted: #64748B;
      --border: #E2E8F0;
      --white: #FFFFFF;
      --planner: #3B82F6;
      --planner-bg: rgba(59,130,246,0.12);
      --community: #10B981;
      --community-bg: rgba(16,185,129,0.12);
      --newmarket: #A855F7;
      --newmarket-bg: rgba(168,85,247,0.12);
      --eng-low: #F59E0B;
      --eng-mid: #3B82F6;
      --eng-high: #10B981;
      --radius: 12px;
      
      /* Colores del menú sidebar */
      --menu-section: #b0876a;
      --menu-item-hover: #f0ede7;
      --menu-item-active: #e8e2d8;
      --menu-bg: #faf9f7;
      --menu-border: #e8e5df;
      --menu-text: #2c2c2a;
      --menu-text-muted: #888780;
    }
    
    body { background: var(--dark, #F8FAFC) !important; }
    
    .table-wrap {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      margin-bottom: 20px;
      margin-right: 20px;
    }
    
    .table-header-custom {
      padding: 14px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .table-title {
      font-size: 15px;
      font-weight: 700;
      color: var(--ink);
    }
    
    .table-count {
      font-size: 11px;
      color: var(--muted);
      margin-left: 8px;
    }

    .filter-bar {
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

    .filter-label {
      font-size: 11px;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: var(--muted);
      margin-right: 4px;
      flex-shrink: 0;
    }

    .filter-date {
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 7px 12px;
      font-size: 13px;
      color: var(--ink);
      background: var(--white);
      min-width: 150px;
    }

    .filter-date:focus {
      outline: none;
      border-color: var(--gold, #C5A028);
    }

    .filter-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      font-size: 13px;
      font-weight: 600;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: var(--white);
      color: var(--ink);
      text-decoration: none;
      cursor: pointer;
    }

    .filter-btn-primary {
      background: var(--ink);
      border-color: var(--ink);
      color: #fff;
    }

    .filter-btn-reset:hover,
    .filter-btn-primary:hover {
      opacity: 0.9;
    }

    .filter-range-hint {
      font-size: 12px;
      color: var(--muted);
      margin-left: auto;
    }

    .citas-kpi-strip {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
      margin-bottom: 20px;
    }

    .citas-kpi-card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 18px 20px;
      cursor: pointer;
      transition: box-shadow 0.15s ease, border-color 0.15s ease;
    }

    .citas-kpi-card:hover {
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
    }

    .citas-kpi-card.is-active {
      border-color: var(--gold);
      box-shadow: 0 0 0 1px rgba(197, 160, 40, 0.25);
    }

    .citas-kpi-card--pending .citas-kpi-value { color: var(--eng-low); }
    .citas-kpi-card--attended .citas-kpi-value { color: var(--eng-high); }
    .citas-kpi-card--dead .citas-kpi-value { color: var(--muted); }

    .citas-kpi-label {
      font-size: 10px;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 8px;
    }

    .citas-kpi-value {
      font-size: 28px;
      font-weight: 700;
      color: var(--ink);
      line-height: 1;
      margin-bottom: 6px;
    }

    .citas-kpi-sub {
      font-size: 11px;
      color: var(--muted);
    }

    @media (max-width: 768px) {
      .citas-kpi-strip {
        grid-template-columns: 1fr;
      }
    }
    
    .efege-table-scroll {
      overflow-x: auto;
      overflow-y: auto;
      max-height: 700px;
      width: 100%;
      scrollbar-width: thin;
      scrollbar-color: var(--border, #e2e8f0) transparent;
    }
    
    .efege-table-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
    .efege-table-scroll::-webkit-scrollbar-track { background: transparent; }
    .efege-table-scroll::-webkit-scrollbar-thumb { background: var(--border, #e2e8f0); border-radius: 3px; }
    
    .efege-table { 
      font-size: 13px; 
      font-family: 'DM Sans', system-ui, sans-serif;
      margin-bottom: 0 !important;
    }
    
    .efege-table thead th {
      background: var(--surface) !important;
      color: var(--muted) !important;
      font-size: 10px !important;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      border-bottom: 1px solid var(--border) !important;
      padding: 12px 16px !important;
      white-space: nowrap;
      font-weight: 700 !important;
      position: sticky;
      top: 0;
      z-index: 2;
      vertical-align: middle !important;
    }
    
    .efege-table tbody tr { 
      border-bottom: 1px solid var(--border); 
      transition: background 0.15s; 
    }
    
    .efege-table tbody tr:hover { 
      background: var(--surface) !important; 
    }
    
    .efege-table tbody td {
      padding: 12px 16px !important;
      vertical-align: middle !important;
      border-bottom: 1px solid var(--border) !important;
      color: var(--ink);
    }
    
    /* Padding extra para la columna de acciones */
    .efege-table tbody td:last-child,
    .efege-table thead th:last-child {
      padding-right: 24px !important;
    }
    
    .efege-table.table-striped tbody tr:nth-of-type(odd) { 
      background-color: rgba(248,250,252,0.5); 
    }
    
    .badge-status {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      white-space: nowrap;
    }
    
    .badge-status.status-pending { 
      background: rgba(245,158,11,0.12); 
      color: var(--eng-low); 
    }
    
    .badge-status.status-scheduled { 
      background: rgba(59,130,246,0.12); 
      color: var(--eng-mid); 
    }
    
    .badge-status.status-attended { 
      background: rgba(16,185,129,0.12); 
      color: var(--community); 
    }
    
    .badge-status.status-closed { 
      background: rgba(197,160,40,0.15); 
      color: var(--gold); 
    }
    
    .badge-status.status-dead { 
      background: rgba(220, 53, 69, 0.12); 
      color: #dc3545; 
    }
    
    .td-name { 
      font-weight: 600; 
      color: var(--ink); 
      font-size: 13px; 
    }
    
    .td-sub { 
      font-size: 11px; 
      color: var(--muted); 
      margin-top: 2px;
    }
    
    .td-inline-muted {
      font-size: 11px;
      color: var(--muted);
    }
    
    .td-emphasis {
      font-weight: 600;
      color: var(--ink);
    }
    
    .badge-origin {
      display: inline-flex;
      align-items: center;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .badge-origin.from-website {
      background: rgba(59,130,246,0.12);
      color: var(--planner);
    }
    
    .badge-origin.from-wp {
      background: rgba(168,85,247,0.12);
      color: var(--newmarket);
    }
    
    .badge-origin.from-campaign {
      background: rgba(197,160,40,0.10);
      border: 1px solid rgba(197,160,40,0.18);
      color: var(--gold);
    }
    
    /* Nav pills modernos - Exactamente como el menú */
    .nav-pills .nav-link {
      background: transparent;
      border: none;
      color: var(--menu-text);
      transition: background 0.12s;
      font-weight: 500;
      border-radius: 8px;
      padding: 7px 16px;
    }
    
    .nav-pills .nav-link:hover {
      background: var(--menu-item-hover);
      color: var(--menu-text);
    }
    
    .nav-pills .nav-link.active {
      background: var(--menu-item-active);
      color: var(--menu-text);
      font-weight: 500;
    }
    
    /* Modal de Reagendar - Solo bordes y textos de secciones */
    #modal-reagendar .border-success {
      border-color: var(--menu-section) !important;
    }
    
    #modal-reagendar .border-primary {
      border-color: var(--menu-section) !important;
    }
    
    #modal-reagendar .text-success,
    #modal-reagendar .text-primary {
      color: var(--menu-section) !important;
    }
    
    #modal-reagendar .alert-info {
      background-color: rgba(176, 135, 106, 0.1);
      border-color: var(--menu-section) !important;
      color: var(--menu-text) !important;
    }
    
    #modal-reagendar .alert-info strong {
      color: var(--menu-section) !important;
    }

    #reagenda-vendor-schedule .reagenda-calendar-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-bottom: 10px;
    }

    #reagenda-vendor-schedule .reagenda-calendar-header h6 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 600;
    }

    #reagenda-vendor-calendar {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      font-size: 0.85rem;
    }

    #reagenda-vendor-calendar th,
    #reagenda-vendor-calendar td {
      padding: 8px 4px;
      text-align: center;
      border: 1px solid #eee;
      cursor: pointer;
      vertical-align: middle;
    }

    #reagenda-vendor-calendar th {
      font-weight: 600;
      color: #555;
      cursor: default;
    }

    #reagenda-vendor-calendar td:hover:not(.disabled):not(.blocked) {
      background-color: #f0f0f0;
    }

    #reagenda-vendor-calendar td.disabled,
    #reagenda-vendor-calendar td.blocked {
      color: #ccc;
      cursor: default;
      pointer-events: none;
      background-color: #fafafa;
    }

    #reagenda-vendor-calendar td.blocked {
      background-color: #fdecea;
    }

    #reagenda-vendor-calendar td.has-hours:not(.selected) {
      background-color: #eef6ee;
      font-weight: 600;
    }

    #reagenda-vendor-calendar td.selected {
      background-color: #edd896;
      font-weight: 700;
    }

    #reagenda-vendor-calendar td.current-day:not(.selected) {
      background-color: #e0f2f7;
    }

    #reagenda-vendor-hour-panel {
      border-left: 1px solid #eee;
      padding-left: 16px;
      min-height: 260px;
    }

    @media (max-width: 767px) {
      #reagenda-vendor-hour-panel {
        border-left: none;
        border-top: 1px solid #eee;
        padding-left: 0;
        padding-top: 16px;
        margin-top: 16px;
      }
    }
    
    .efege-page-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
    }
    
    .efege-page-header-left {
      flex: 1;
      min-width: 0;
    }
    
    .efege-page-header-right {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-shrink: 0;
    }
    
    /* Validation error states */
    .sfm-field input.sfm-input-error,
    .sfm-field select.sfm-input-error,
    textarea.sfm-input-error {
        border-color: #e53935 !important;
        background-color: #fff5f5;
    }
    .sfm-group-error {
        border: 2px solid #e53935 !important;
        border-radius: 12px;
        padding: 8px;
    }
    .sfm-error-msg {
        color: #e53935;
        font-size: 0.75rem;
        margin-top: 5px;
        display: block;
    }
    .sfm-subcategory-select.sfm-input-error {
        border-color: #e53935 !important;
        background-color: #fff5f5;
    }
    /* Read-only cards for Ver Más modal with sfSessionBody look */
    .sfm-readonly-input {
        width: 100%;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 0.9rem;
        color: #333;
        background: #f8f8f8;
        min-height: 42px;
        display: flex;
        align-items: center;
        word-break: break-word;
    }
    .sfm-readonly-input a {
        color: inherit;
        text-decoration: underline;
    }
    .sfm-readonly-actions {
        margin-top: 10px;
    }
    .sfm-static-grid .sfm-category-btn,
    .sfm-static-grid .sfm-engagement-btn {
        cursor: default;
        pointer-events: none;
    }
    /* ===== VER MAS MODAL (same look as sfSessionPanel) ===== */
    #verMasModal .modal-dialog {
        max-width: 680px;
    }
    #verMasModal .modal-content {
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 24px 80px rgba(0,0,0,0.35);
        border: none;
    }
    #verMasModal .modal-body {
        padding: 0;
    }
    #verMasModal .modal-header {
        background: #eee8dc;
        color: #464646;
        padding: 22px 28px;
        position: relative;
        border-bottom: none;
        display: block;
    }
    #verMasModalHeader h2 {
        margin: 0 0 6px;
        font-size: 1.45rem;
        font-weight: 700;
        color: #464646;
    }
    #verMasModalHeader .sfm-meta {
        display: flex;
        gap: 18px;
        font-size: 0.82rem;
        opacity: 0.75;
        flex-wrap: wrap;
    }
    #verMasModalHeader .sfm-vendedor-box {
        position: absolute;
        top: 22px;
        right: 56px;
        text-align: center;
    }
    #verMasModalHeader .sfm-vendedor-label {
        background: #464646;
        color: #eee8dc;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        padding: 2px 10px;
        border-radius: 4px;
        letter-spacing: 0.5px;
    }
    #verMasModalHeader .sfm-vendedor-name {
        color: #464646;
        font-weight: 700;
        font-size: 0.95rem;
        margin-top: 4px;
    }
    #verMasModalHeader .vmm-close {
        position: absolute;
        top: 16px;
        right: 16px;
        z-index: 2;
    }
    .vmm-session-body {
        padding: 28px 32px;
        overflow-y: auto;
        max-height: calc(100vh - 260px);
    }
    #verMasModal .modal-footer {
        display: flex;
        gap: 12px;
        padding: 20px 32px 28px;
        border-top: 1px solid #eee;
    }
    #verMasModal .modal-footer .sfm-btn-cancel {
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
    #verMasModal .modal-footer .sfm-btn-cancel:hover {
        background: #f5f5f5;
    }
</style>

<!-- ===== POST-SESSION SUMMARY MODAL ===== -->
<script>
    var paquetesData = <?php echo json_encode($paquetesArr); ?>;
</script>

<div id="sfSessionOverlay">
    <div id="sfSessionPanel">
        <!-- Header -->
        <div id="sfSessionHeader">
            <h2 id="sfm-client-name">—</h2>
            <div class="sfm-meta">
                <span id="sfm-location">📍 —</span>
                <span id="sfm-wedding-date-display">📅 —</span>
                <span>Lead #<span id="sfm-lead-id">—</span></span>
            </div>
            <div class="sfm-vendedor-box">
                <div class="sfm-vendedor-label">Vendedor/a</div>
                <div id="sfm-vendedor-name" class="sfm-vendedor-name">—</div>
            </div>
        </div>

        <!-- Body -->
        <div id="sfSessionBody">

            <!-- Section 1: Verify client data -->
            <div class="sfm-section">
                <div class="sfm-section-title">
                    <div class="sfm-section-number">1</div>
                    <div class="sfm-section-heading">
                        <h5>Verificar datos del cliente</h5>
                        <p>Confirma que la información es correcta. Corrígela si es necesario.</p>
                    </div>
                </div>
                <div class="sfm-field" style="margin-bottom:14px;">
                    <label>Nombre completo <span class="sfm-tag">+ Pre-llenado</span></label>
                    <input type="text" id="sfm-nombre" placeholder="Nombre completo">
                    <div class="sfm-confirm-note">✓ Confirma con el cliente durante la llamada</div>
                </div>
                <div class="sfm-grid-2">
                    <div class="sfm-field">
                        <label>Correo electrónico <span class="sfm-tag">+ Pre-llenado</span></label>
                        <input type="email" id="sfm-email" placeholder="correo@ejemplo.com">
                    </div>
                    <div class="sfm-field">
                        <label>WhatsApp / Teléfono <span class="sfm-tag">+ Pre-llenado</span></label>
                        <input type="text" id="sfm-telefono" placeholder="+52 477 000 0000">
                    </div>
                    <div class="sfm-field">
                        <label>Lugar de la boda <span class="sfm-tag">+ Pre-llenado</span></label>
                        <input type="text" id="sfm-wedding-location" placeholder="Ciudad, País">
                    </div>
                    <div class="sfm-field">
                        <label>Fecha de la boda <span class="sfm-tag">+ Pre-llenado</span></label>
                        <input type="date" id="sfm-wedding-date">
                    </div>
                </div>
            </div>

            <!-- Tipo de cliente -->
            <div class="sfm-section">
                <div class="sfm-section-title">
                    <div class="sfm-section-number">TC</div>
                    <div class="sfm-section-heading">
                        <h5>Tipo de cliente</h5>
                        <p>¿El cliente es un Wedding Planner o un Cliente Final?</p>
                    </div>
                </div>
                <input type="hidden" id="sfm-tipo-cliente">
                <input type="hidden" id="sfm-how-did-you-meet">
                <div class="sfm-category-btns">
                    <button type="button" class="sfm-tipo-cliente-btn" data-tipo="1"><span class="sfm-cat-icon">💼</span>Wedding Planner</button>
                    <button type="button" class="sfm-tipo-cliente-btn" data-tipo="0"><span class="sfm-cat-icon">👤</span>Cliente Final</button>
                </div>
            </div>

            <!-- Section 4: Package of interest -->
            <div class="sfm-section">
                <div class="sfm-section-title">
                    <div class="sfm-section-number">2</div>
                    <div class="sfm-section-heading">
                        <h5>Paquete de interés</h5>
                        <p>¿Qué paquete mencionaron o hacia cuál los orientaste?</p>
                    </div>
                </div>
                <div class="sfm-package-list" id="sfm-package-list">
                    <!-- Populated by JS from paquetesData -->
                </div>
            </div>

            <!-- Section 4: Engagement -->
            <div class="sfm-section">
                <div class="sfm-section-title">
                    <div class="sfm-section-number">3</div>
                    <div class="sfm-section-heading">
                        <h5>Engagement del cliente</h5>
                        <p>Tu percepción después de la sesión. Sé honesta.</p>
                    </div>
                </div>
                <div class="sfm-engagement-btns">
                    <button type="button" class="sfm-engagement-btn" data-eng="1">
                        <span class="sfm-eng-icon">😑</span>
                        <span class="sfm-eng-name">Bajo</span>
                        <div class="sfm-eng-desc">Poco entusiasmo, muchas dudas o precio</div>
                    </button>
                    <button type="button" class="sfm-engagement-btn" data-eng="2">
                        <span class="sfm-eng-icon">😃</span>
                        <span class="sfm-eng-name">Medio</span>
                        <div class="sfm-eng-desc">Interés real, pero falta de decisión</div>
                    </button>
                    <button type="button" class="sfm-engagement-btn" data-eng="3">
                        <span class="sfm-eng-icon">🔥</span>
                        <span class="sfm-eng-name">Alto</span>
                        <div class="sfm-eng-desc">Muy interesados, listos para cerrar</div>
                    </button>
                </div>
            </div>

            <!-- Section 3: ¿Desde cuánto nos conoces? -->
            <div class="sfm-section">
                <div class="sfm-section-title">
                    <div class="sfm-section-number">4</div>
                    <div class="sfm-section-heading">
                        <h5>¿Desde hace cuánto nos conoce el cliente? (refuerzo)</h5>
                        <p>Confirma directamente con el cliente durante la sesión.</p>
                    </div>
                </div>
                <input type="hidden" id="sfm-how-long-known-us">
                <div class="sfm-known-us-grid" id="sfm-known-us-grid">
                    <button type="button" class="sfm-known-us-btn" data-val="Less than 6 months">
                        <span class="sfku-title">Menos de 6 meses</span>
                        <span class="sfku-desc">Nos conociste recientemente.</span>
                    </button>
                    <button type="button" class="sfm-known-us-btn" data-val="More than 6 months">
                        <span class="sfku-title">Más de 6 meses</span>
                        <span class="sfku-desc">Ya tenías un tiempo siguiéndonos.</span>
                    </button>
                </div>
            </div>

            <!-- Section 5: Session comments -->
            <div class="sfm-section">
                <div class="sfm-section-title">
                    <div class="sfm-section-number">5</div>
                    <div class="sfm-section-heading">
                        <h5>Comentarios de la sesión</h5>
                        <p>Notas internas, objeciones, contexto relevante.</p>
                    </div>
                </div>
                <textarea id="sfm-comentarios" class="sfm-textarea"
                    placeholder="Ej: La pareja está comparando con otro fotógrafo. Preguntaron mucho por video. La novia es muy visual, le encantaron los reels del Instagram. Presupuesto ajustado pero hay disposición…"></textarea>
            </div>

        </div><!-- /sfSessionBody -->

        <!-- Footer -->
        <div id="sfSessionFooter">
            <button type="button" class="sfm-btn-cancel" id="sfm-cancel-btn">Cancelar</button>
            <button type="button" class="sfm-btn-submit" id="sfm-submit-btn">Guardar resumen de sesión</button>
        </div>
    </div><!-- /sfSessionPanel -->
</div><!-- /sfSessionOverlay -->
<!-- ===== END POST-SESSION MODAL ===== -->

<div class="efege-page">
    <!-- PAGE HEADER -->
    <div class="efege-page-header" style="border-bottom: 1px solid var(--border); padding: 20px 0 16px; margin-bottom: 20px;">
        <div class="efege-page-header-left">
            <div class="efege-page-title" style="font-size: 26px; font-weight: 600; letter-spacing: 2px; color: var(--ink); margin-bottom: 4px;">
                <span class="efege-page-title-sub" style="font-size: 14px; letter-spacing: 1px; color: var(--muted);">Post Qualified Leads</span>
            </div>
            <div class="efege-page-subtitle" style="font-size: 12px; color: var(--muted);">Gestión de citas agendadas · Sistema de seguimiento</div>
        </div>
        <div class="efege-page-header-right">
            <div class="efege-live-badge" style="font-size: 11px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 6px 12px; color: var(--muted);">🔴 Live</div>
        </div>
    </div>

    <div class="filter-bar">
        <span class="filter-label">Fecha de cita</span>
        <form method="get" id="appointmentDateFilterForm" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <input type="date" name="start_date" class="filter-date" id="filterStartDate"
                value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>"
                title="Desde">
            <span style="font-size:12px;color:var(--muted);">a</span>
            <input type="date" name="end_date" class="filter-date" id="filterEndDate"
                value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>"
                title="Hasta">
            <button type="submit" class="filter-btn filter-btn-primary">Filtrar</button>
            <a href="consulta.php?show_all=1" class="filter-btn filter-btn-reset">Limpiar</a>
        </form>
        <?php if ($startDate || $endDate): ?>
            <span class="filter-range-hint">
                Mostrando citas
                <?php if ($startDate && $endDate): ?>
                    del <?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>
                    al <?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>
                <?php elseif ($startDate): ?>
                    desde el <?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>
                <?php else: ?>
                    hasta el <?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="citas-kpi-strip" id="citasKpiStrip">
        <article class="citas-kpi-card citas-kpi-card--pending is-active" id="kpi-card-pending" data-tab-target="#pills-pending-tab" role="button" tabindex="0" aria-label="Ver citas pendientes">
            <div class="citas-kpi-label">Pendientes</div>
            <div class="citas-kpi-value" id="kpi-citas-pendientes">0</div>
            <div class="citas-kpi-sub" id="kpi-citas-pendientes-sub">Citas por atender</div>
        </article>
        <article class="citas-kpi-card citas-kpi-card--attended" id="kpi-card-attended" data-tab-target="#pills-attended-tab" role="button" tabindex="0" aria-label="Ver citas atendidas">
            <div class="citas-kpi-label">Atendidas</div>
            <div class="citas-kpi-value" id="kpi-citas-atendidas">0</div>
            <div class="citas-kpi-sub" id="kpi-citas-atendidas-sub">Sesiones completadas</div>
        </article>
        <article class="citas-kpi-card citas-kpi-card--dead" id="kpi-card-dead" data-tab-target="#pills-no-response-tab" role="button" tabindex="0" aria-label="Ver citas muertas">
            <div class="citas-kpi-label">Muertos</div>
            <div class="citas-kpi-value" id="kpi-citas-muertos">0</div>
            <div class="citas-kpi-sub" id="kpi-citas-muertos-sub">Sin respuesta o descartadas</div>
        </article>
    </div>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="order-last order-md-1 col-12 col-md-12">
                <!-- <h3>Post qualified leads</h3> -->
                <br>
            </div>
            <div class="order-first order-md-2 col-12 col-md-12">

            </div>
        </div>
    </div>
    <div class="d-flex aling-items-center justify-content-between" style="margin-bottom: 20px;">
        <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist" style="gap: 8px;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pills-pending-tab" data-bs-toggle="pill"
                    data-bs-target="#pills-pending" type="button" role="tab" aria-controls="pills-pending"
                    aria-selected="true" style="border-radius: 8px; font-size: 13px; font-weight: 600; padding: 8px 16px;">
                    Pendientes
                    <span class="notification-bubble" id="notification-bubble-pendientes"></span>

                </button>

            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-attended-tab" data-bs-toggle="pill" data-bs-target="#pills-attended"
                    type="button" role="tab" aria-controls="pills-attended" aria-selected="false" style="border-radius: 8px; font-size: 13px; font-weight: 600; padding: 8px 16px;">
                    Atendidos
                    <span class="notification-bubble" id="notification-bubble-atendidos"></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-no-response-tab" data-bs-toggle="pill"
                    data-bs-target="#pills-no-response" type="button" role="tab" aria-controls="pills-no-response"
                    aria-selected="false" style="border-radius: 8px; font-size: 13px; font-weight: 600; padding: 8px 16px;">
                    Muertos
                    <span class="notification-bubble" id="notification-bubble-muertos"></span>
                </button>
            </li>

        </ul>
        <div class="mr-5" style="font-size: 12px; color: var(--muted);">
            <p style="margin: 0;"><?php echo $fecha; ?></p>


        </div>
    </div>
    <div class="tab-content" id="pills-tabContent">
        <div class="tab-pane fade show active" id="pills-pending" role="tabpanel" aria-labelledby="pills-pending-tab">
            <section class="section">
                <div class="table-wrap">
                    <div class="table-header-custom">
                        <div>
                            <span class="table-title">Citas pendientes</span>
                            <span class="table-count" id="pending-count"></span>
                        </div>
                    </div>
                    <div class="efege-table-scroll">
                        <table id="pendingAppointmentsTable" class="efege-table table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">No. Cita</th>
                                    <th style="width: 70px;">No. Cliente</th>
                                    <th style="width: 80px;">Origen</th>
                                    <th style="width: 90px;">F. Registro</th>
                                    <th style="width: 80px;">H. Registro</th>
                                    <th>Nombre</th>
                                    <th style="width: 100px;">Tel.</th>
                                    <th style="width: 150px;">Correo</th>
                                    <th style="width: 110px;">Hora (Cliente)</th>
                                    <th style="width: 110px;">Hora (Vendedor)</th>
                                    <th style="width: 100px;">Asesor</th>
                                    <th style="width: 80px;">Estatus</th>
                                    <th style="width: 180px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <div class="tab-pane fade" id="pills-attended" role="tabpanel" aria-labelledby="pills-attended-tab">
            <section class="section">
                <div class="table-wrap">
                    <div class="table-header-custom">
                        <div>
                            <span class="table-title">Citas atendidas</span>
                            <span class="table-count" id="attended-count"></span>
                        </div>
                    </div>
                    <div class="efege-table-scroll">
                        <table id="attendedAppointmentsTable" class="efege-table table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Origen</th>
                                    <th style="width: 90px;">F. Registro</th>
                                    <th style="width: 80px;">H. Registro</th>
                                    <th>Nombre</th>
                                    <th style="width: 100px;">Tel.</th>
                                    <th style="width: 110px;">Hora (Cliente)</th>
                                    <th style="width: 110px;">Hora (Vendedor)</th>
                                    <th style="width: 100px;">Asesor</th>
                                    <th style="width: 80px;">Estatus</th>
                                    <th style="width: 180px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <div class="tab-pane fade" id="pills-no-response" role="tabpanel" aria-labelledby="pills-no-response-tab">
            <section class="section">
                <div class="table-wrap">
                    <div class="table-header-custom">
                        <div>
                            <span class="table-title">Muertos</span>
                            <span class="table-count" id="dead-count"></span>
                        </div>
                    </div>
                    <div class="efege-table-scroll">
                        <table id="noResponseAppointmentsTable" class="efege-table table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Origen</th>
                                    <th style="width: 90px;">F. Registro</th>
                                    <th style="width: 80px;">H. Registro</th>
                                    <th>Nombre</th>
                                    <th style="width: 100px;">Tel.</th>
                                    <th style="width: 110px;">Hora (Cliente)</th>
                                    <th style="width: 110px;">Hora (Vendedor)</th>
                                    <th style="width: 100px;">Asesor</th>
                                    <th style="width: 80px;">Estatus</th>
                                    <th style="width: 180px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>

</div><!-- /efege-page -->


</div>
<!-- Modal para Ver Más Información -->
<div class="modal fade" id="verMasModal" tabindex="-1" aria-labelledby="verMasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" type="document">
        <div class="modal-content">
            <div class="modal-header" id="verMasModalHeader">
                <h2 id="vmm-client-name">—</h2>
                <div class="sfm-meta">
                    <span id="vmm-location">📍 —</span>
                    <span id="vmm-wedding-date-display">📅 —</span>
                    <span>Lead #<span id="vmm-lead-id">—</span></span>
                </div>
                <div class="sfm-vendedor-box">
                    <div class="sfm-vendedor-label">Vendedor/a</div>
                    <div id="vmm-vendedor-name" class="sfm-vendedor-name">—</div>
                </div>
                <button type="button" class="btn-close vmm-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <!-- Aquí se mostrarán los detalles del evento -->
                <div id="modalContent">
                    <!-- El contenido se llenará dinámicamente con JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="sfm-btn-cancel" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Reagendar -->
<div id="modal-reagendar" class="modal fade" tabindex="-1" aria-labelledby="reagendarModal" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" type="document">
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
                <select id="select-usuarios" class="mb-3 form-control">
                    <option value="">Seleccionar usuario</option> <!-- Opción inicial vacía -->
                </select>

                <div class="mt-1" id="show_inputs" style="display: none;">
                    <!-- Horario del vendedor (Principal) -->
                    <div class="mb-4 p-3 border border-primary rounded" id="reagenda-vendor-schedule">
                        <h6 class="text-primary mb-3">Hora del Vendedor (Principal) *</h6>
                        <p id="last_datetime" class="m-0 small text-muted mb-3"></p>
                        <input type="hidden" id="reagenda-vendor-date" value="">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <div class="reagenda-calendar-header">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="reagenda-prev-month">&laquo;</button>
                                    <h6 id="reagenda-current-month"></h6>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="reagenda-next-month">&raquo;</button>
                                </div>
                                <table id="reagenda-vendor-calendar">
                                    <thead>
                                        <tr>
                                            <th>Dom</th>
                                            <th>Lun</th>
                                            <th>Mar</th>
                                            <th>Mie</th>
                                            <th>Jue</th>
                                            <th>Vie</th>
                                            <th>Sab</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                                <small class="text-muted d-block mt-2">
                                    Verde = la vendedora tiene horarios activos ese dia. Gris = fuera de rango o bloqueado.
                                </small>
                            </div>
                            <div class="col-md-5" id="reagenda-vendor-hour-panel">
                                <label class="form-label" for="reagenda-vendor-hour">Horario disponible</label>
                                <p class="small text-muted mb-2">Fecha: <strong id="reagenda-selected-date-label">Selecciona un dia</strong></p>
                                <select id="reagenda-vendor-hour" class="form-select" disabled>
                                    <option value="">Primero selecciona un dia en el calendario</option>
                                </select>
                                <small class="text-muted d-block mt-2">Solo se muestran horarios activos y libres de la vendedora.</small>
                            </div>
                        </div>
                        <div class="text-muted small mt-3" id="sellerTimePreview"></div>
                    </div>

                    <!-- Hora del cliente (referencia) -->
                    <div class="mb-4 p-3 border border-success rounded">
                        <h6 class="text-success mb-3">Hora del Cliente</h6>
                        <div class="mb-3">
                            <label for="datetime_appointment" class="form-label">Fecha y Hora (Cliente) *</label>
                            <input id="datetime_appointment" name="datetime_appointment" 
                                autocomplete="off" type="datetime-local" class="form-control" required>
                            <small class="text-muted">Hora en la zona horaria del cliente, para referencia y correo.</small>
                        </div>
                        <div class="text-muted small" id="clientTimePreview"></div>
                    </div>

                    <!-- Diferencia de Zona Horaria -->
                    <div class="alert alert-info" id="timeDifferenceAlert" style="display:none;">
                        <strong>Diferencia de zona horaria:</strong> <span id="timeDiffText"></span>
                    </div>

                    <div class="d-flex flex-row-reverse">
                        <button type="button" class="btn btn-primary" id="confirmar-reagendar">Guardar reagenda
                            manual</button>
                    </div>
                </div>
                <div class="my-5">

                    <hr>
                </div>
                <div id="modal-reagendar-body" class="mb-5 text-center">
                    <p class="mb-3 h5">¿Quieres mandar un correo para que el mismo cliente reagende su cita?</p>
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


<?php
// include 'footer.php'; // Comentado para evitar conflictos con scripts duplicados (dark.js, simple-datatables)
?>


</body>

</html>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/locale/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment-timezone@0.5.45/builds/moment-timezone-with-data-1970-2030.min.js"></script>
<script src="assets/static/js/components/dark.js"></script>


<!-- Bootstrap JS y Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
<script>
    // Bloques de validación de fechas removidos temporalmente para datetime-local
    // Si se necesitan restricciones, se pueden agregar después

    $(document).ready(function () {

        var tipoUsuario = "<?php echo $_SESSION['tus']; ?>";
        var idUsu = "<?php echo $_SESSION['uid']; ?>";
        var isAdminUser = tipoUsuario === '0';
        var isMarketingUser = tipoUsuario === '4';
        var canViewAllAppointments = isAdminUser || isMarketingUser;
        var filterStartDate = <?php echo json_encode($startDate); ?>;
        var filterEndDate = <?php echo json_encode($endDate); ?>;
        let allRegistroConVendedor = [];
        const diasBloqueadosReagenda = <?php echo json_encode($dias_bloqueados ?? []); ?>;
        let reagendaSelectedDate = '';
        let reagendaCurrentFecha = '';
        let reagendaCurrentHora = '';
        let reagendaVendorDaysWithHours = new Set();
        let reagendaCalendarCursor = new Date();
        reagendaCalendarCursor.setHours(0, 0, 0, 0);

        function normalizeReagendaHour(timeText) {
            const raw = String(timeText || '').trim();
            const match = raw.match(/^(\d{1,2}):(\d{2})/);
            if (!match) {
                return '';
            }
            return String(match[1]).padStart(2, '0') + ':' + String(match[2]).padStart(2, '0');
        }

        function getReagendaDateLimits() {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const maxDate = new Date(today);
            maxDate.setDate(today.getDate() + 14);
            return { today, maxDate };
        }

        function isReagendaDateBlocked(dateStr) {
            return diasBloqueadosReagenda.includes(dateStr);
        }

        function formatReagendaDateLabel(dateStr) {
            if (!dateStr) {
                return 'Selecciona un dia';
            }
            return moment(dateStr, 'YYYY-MM-DD').format('DD/MM/YYYY');
        }

        function updateReagendaSellerPreview() {
            const vendorDate = $('#reagenda-vendor-date').val();
            const vendorHour = $('#reagenda-vendor-hour').val();
            if (vendorDate && vendorHour) {
                $('#sellerTimePreview').html('Vista previa vendedora: ' + moment(vendorDate + ' ' + vendorHour, 'YYYY-MM-DD HH:mm').format('DD/MM/YYYY hh:mm A'));
            } else {
                $('#sellerTimePreview').html('');
            }
            calcularDiferenciaZonaHoraria();
        }

        function loadReagendaVendorDays(vendorId) {
            reagendaVendorDaysWithHours = new Set();
            if (!vendorId) {
                generateReagendaCalendar(reagendaCalendarCursor.getMonth(), reagendaCalendarCursor.getFullYear());
                return $.Deferred().resolve().promise();
            }

            return $.ajax({
                url: 'chkDisponible.php',
                type: 'POST',
                dataType: 'json',
                data: { idusu: vendorId, listar_dias: 1 }
            }).done(function (response) {
                const dates = (response && response.dates) ? response.dates : [];
                reagendaVendorDaysWithHours = new Set(dates);
                generateReagendaCalendar(reagendaCalendarCursor.getMonth(), reagendaCalendarCursor.getFullYear());
            }).fail(function () {
                reagendaVendorDaysWithHours = new Set();
                generateReagendaCalendar(reagendaCalendarCursor.getMonth(), reagendaCalendarCursor.getFullYear());
            });
        }

        function loadReagendaVendorHours(vendorId, date, preselectHour) {
            const $hourSelect = $('#reagenda-vendor-hour');
            if (!vendorId || !date) {
                $hourSelect.prop('disabled', true).html('<option value="">Primero selecciona un dia en el calendario</option>');
                updateReagendaSellerPreview();
                return $.Deferred().resolve().promise();
            }

            $hourSelect.prop('disabled', true).html('<option value="">Cargando horarios...</option>');

            const schedulesRequest = $.ajax({
                url: 'chkDisponible.php',
                type: 'POST',
                dataType: 'json',
                data: { dia: date, idusu: vendorId }
            });

            const excludeCalendarId = $('#reagendaId').val() || '';
            const occupiedRequest = $.ajax({
                url: 'chkDisponible.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    fecha: date,
                    idusu: vendorId,
                    exclude_id: excludeCalendarId
                }
            });

            return $.when(schedulesRequest, occupiedRequest).done(function (schedulesRes, occupiedRes) {
                const schedulesRows = schedulesRes[0] || [];
                const occupiedRows = occupiedRes[0] || [];
                const availableHours = [];
                const normalizedPreselect = normalizeReagendaHour(preselectHour);

                if (Array.isArray(schedulesRows) && schedulesRows.length > 0 && schedulesRows[0].horarios) {
                    let vendorHours = [];
                    try {
                        vendorHours = JSON.parse(schedulesRows[0].horarios);
                    } catch (error) {
                        vendorHours = [];
                    }

                    vendorHours.forEach(function (hourValue) {
                        const normalizedHour = normalizeReagendaHour(hourValue);
                        if (!normalizedHour) {
                            return;
                        }

                        let isOccupied = false;
                        occupiedRows.forEach(function (occupiedRow) {
                            const occupiedHour = normalizeReagendaHour(occupiedRow.hora);
                            if (occupiedHour === normalizedHour) {
                                isOccupied = true;
                            }
                        });

                        if (!isOccupied && !availableHours.includes(normalizedHour)) {
                            availableHours.push(normalizedHour);
                        }
                    });
                }

                availableHours.sort(function (a, b) {
                    const partsA = a.split(':').map(Number);
                    const partsB = b.split(':').map(Number);
                    return (partsA[0] * 60 + partsA[1]) - (partsB[0] * 60 + partsB[1]);
                });

                let optionsHtml = '';
                if (availableHours.length > 0) {
                    optionsHtml += '<option value="">Selecciona una hora</option>';
                    availableHours.forEach(function (hourValue) {
                        const selected = normalizedPreselect === hourValue ? ' selected' : '';
                        optionsHtml += '<option value="' + hourValue + '"' + selected + '>' + hourValue + '</option>';
                    });
                    $hourSelect.prop('disabled', false).html(optionsHtml);
                } else {
                    $hourSelect.prop('disabled', true).html('<option value="">Sin horarios disponibles</option>');
                }

                updateReagendaSellerPreview();
            }).fail(function () {
                $hourSelect.prop('disabled', true).html('<option value="">No se pudieron cargar los horarios</option>');
                updateReagendaSellerPreview();
            });
        }

        function selectReagendaDate(year, month, day, preselectHour) {
            const formattedMonth = String(month + 1).padStart(2, '0');
            const formattedDay = String(day).padStart(2, '0');
            const dateStr = year + '-' + formattedMonth + '-' + formattedDay;
            const vendorId = $('#select-usuarios').val();

            reagendaSelectedDate = dateStr;
            $('#reagenda-vendor-date').val(dateStr);
            $('#reagenda-selected-date-label').text(formatReagendaDateLabel(dateStr));

            $('#reagenda-vendor-calendar td[data-day]').removeClass('selected');
            $('#reagenda-vendor-calendar td[data-date="' + dateStr + '"]').addClass('selected');

            return loadReagendaVendorHours(vendorId, dateStr, preselectHour || '');
        }

        function generateReagendaCalendar(month, year) {
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startDay = firstDay.getDay();
            const limits = getReagendaDateLimits();
            let html = '';
            let day = 1;

            for (let row = 0; row < 6; row++) {
                html += '<tr>';
                for (let col = 0; col < 7; col++) {
                    if (row === 0 && col < startDay) {
                        html += '<td></td>';
                    } else if (day > daysInMonth) {
                        html += '<td></td>';
                    } else {
                        const formattedMonth = String(month + 1).padStart(2, '0');
                        const formattedDay = String(day).padStart(2, '0');
                        const dateStr = year + '-' + formattedMonth + '-' + formattedDay;
                        const currentDate = new Date(year, month, day);
                        currentDate.setHours(0, 0,  0, 0);
                        const isBeforeMin = currentDate < limits.today;
                        const isAfterMax = currentDate > limits.maxDate;
                        const isBlocked = isReagendaDateBlocked(dateStr);
                        const hasHours = reagendaVendorDaysWithHours.has(dateStr);
                        const isSelected = reagendaSelectedDate === dateStr;
                        const isToday = limits.today.getTime() === currentDate.getTime();
                        const classes = [];

                        if (isBeforeMin || isAfterMax) {
                            classes.push('disabled');
                        }
                        if (isBlocked) {
                            classes.push('blocked');
                        }
                        if (hasHours && !isBlocked && !(isBeforeMin || isAfterMax)) {
                            classes.push('has-hours');
                        }
                        if (isSelected) {
                            classes.push('selected');
                        }
                        if (isToday) {
                            classes.push('current-day');
                        }

                        html += '<td data-day="' + day + '" data-month="' + month + '" data-year="' + year + '" data-date="' + dateStr + '" class="' + classes.join(' ') + '">' + day + '</td>';
                        day++;
                    }
                }
                html += '</tr>';
                if (day > daysInMonth) {
                    break;
                }
            }

            $('#reagenda-vendor-calendar tbody').html(html);
            $('#reagenda-current-month').text(new Date(year, month).toLocaleDateString('es-MX', { month: 'long', year: 'numeric' }));
        }

        function resetReagendaVendorSchedule() {
            reagendaSelectedDate = '';
            $('#reagenda-vendor-date').val('');
            $('#reagenda-selected-date-label').text('Selecciona un dia');
            $('#reagenda-vendor-hour').prop('disabled', true).html('<option value="">Primero selecciona un dia en el calendario</option>');
            $('#sellerTimePreview').html('');
            generateReagendaCalendar(reagendaCalendarCursor.getMonth(), reagendaCalendarCursor.getFullYear());
        }

        function initializeReagendaVendorSchedule(vendorId, presetDate, presetHour) {
            reagendaCalendarCursor = new Date();
            reagendaCalendarCursor.setHours(0, 0, 0, 0);

            if (presetDate) {
                const presetParts = presetDate.split('-');
                if (presetParts.length === 3) {
                    reagendaCalendarCursor = new Date(parseInt(presetParts[0], 10), parseInt(presetParts[1], 10) - 1, 1);
                }
            }

            return loadReagendaVendorDays(vendorId).then(function () {
                if (!presetDate) {
                    resetReagendaVendorSchedule();
                    return;
                }

                const presetParts = presetDate.split('-');
                if (presetParts.length !== 3) {
                    resetReagendaVendorSchedule();
                    return;
                }

                return selectReagendaDate(
                    parseInt(presetParts[0], 10),
                    parseInt(presetParts[1], 10) - 1,
                    parseInt(presetParts[2], 10),
                    presetHour || ''
                );
            });
        }

        $(document).on('click', '#reagenda-vendor-calendar td[data-day]', function () {
            if ($(this).hasClass('disabled') || $(this).hasClass('blocked') || !$(this).hasClass('has-hours')) {
                return;
            }
            selectReagendaDate(
                parseInt($(this).data('year'), 10),
                parseInt($(this).data('month'), 10),
                parseInt($(this).data('day'), 10)
            );
        });

        $('#reagenda-prev-month').on('click', function () {
            let month = reagendaCalendarCursor.getMonth() - 1;
            let year = reagendaCalendarCursor.getFullYear();
            if (month < 0) {
                month = 11;
                year--;
            }
            reagendaCalendarCursor = new Date(year, month, 1);
            generateReagendaCalendar(month, year);
        });

        $('#reagenda-next-month').on('click', function () {
            let month = reagendaCalendarCursor.getMonth() + 1;
            let year = reagendaCalendarCursor.getFullYear();
            if (month > 11) {
                month = 0;
                year++;
            }
            reagendaCalendarCursor = new Date(year, month, 1);
            generateReagendaCalendar(month, year);
        });

        $('#reagenda-vendor-hour').on('change', updateReagendaSellerPreview);

        console.log("tipoUsuario ", tipoUsuario);
        console.log("idUsu ", idUsu);
        // Función para convertir la fecha y hora en un objeto Date
        function parseFechaHora(item) {
            const [fecha, hora] = item.fecha.split(' '); // Separa la fecha de la hora
            const [año, mes, dia] = fecha.split('-'); // Asume que la fecha está en formato yyyy-mm-dd
            const [horaStr, minutos] = hora.split(':'); // Separa la hora de los minutos

            // Retorna un objeto Date que podemos comparar
            return new Date(año, mes - 1, dia, horaStr, minutos);
        }

        // Helper global del script para parsear respuestas AJAX
        // Soporta respuestas como objeto, JSON string y texto con basura alrededor.
        function parseAjaxJson(response) {
            if (typeof response === 'object' && response !== null) {
                return response;
            }

            try {
                return JSON.parse(response);
            } catch (error) {
                var firstJson = String(response || '').match(/(\{[\s\S]*\})/);
                if (firstJson) {
                    return JSON.parse(firstJson[1]);
                }
                throw error;
            }
        }

        function getAjaxErrorMessage(xhr, fallbackMessage) {
            var message = fallbackMessage || 'No se pudo completar la solicitud. Por favor, intenta nuevamente.';
            if (!xhr) {
                return message;
            }

            try {
                var result = parseAjaxJson(xhr.responseText);
                if (result && result.message) {
                    return result.message;
                }
            } catch (error) {
                var raw = String(xhr.responseText || '').trim();
                if (raw) {
                    return message + ' (' + raw.substring(0, 300) + ')';
                }
            }

            if (xhr.status) {
                return message + ' (HTTP ' + xhr.status + ')';
            }

            return message;
        }

        const SELLER_BASE_TIMEZONE = 'Etc/GMT+6';
        moment.locale('es');

        function hasValidDate(dateText) {
            return !!String(dateText || '').match(/^\d{4}-\d{2}-\d{2}$/);
        }

        function getAppointmentDateForFilter(row) {
            const customerDate = String(row.fecha_cliente || '').trim();
            if (hasValidDate(customerDate) && customerDate !== '0000-00-00') {
                return customerDate;
            }

            const sellerDate = String(row.fecha || '').trim();
            if (hasValidDate(sellerDate) && sellerDate !== '0000-00-00') {
                return sellerDate;
            }

            if (row.appointment_utc) {
                const utcMoment = moment.utc(row.appointment_utc);
                if (utcMoment.isValid()) {
                    return utcMoment.local().format('YYYY-MM-DD');
                }
            }

            return '';
        }

        function matchesAppointmentDateFilter(row, startDate, endDate) {
            if (!startDate && !endDate) {
                return true;
            }

            const appointmentDate = getAppointmentDateForFilter(row);
            if (!appointmentDate) {
                return false;
            }

            if (startDate && appointmentDate < startDate) {
                return false;
            }

            if (endDate && appointmentDate > endDate) {
                return false;
            }

            return true;
        }

        function filterRegistrosByAppointmentDate(registros, startDate, endDate) {
            return registros.filter(function (item) {
                return matchesAppointmentDateFilter(item, startDate, endDate);
            });
        }

        function updateAppointmentTableCounts(pendientes, atendidas, sinRespuesta) {
            const pendingCount = pendientes.length;
            const attendedCount = atendidas.length;
            const deadCount = sinRespuesta.length;

            $('#pending-count').text(pendingCount ? '(' + pendingCount + ')' : '');
            $('#attended-count').text(attendedCount ? '(' + attendedCount + ')' : '');
            $('#dead-count').text(deadCount ? '(' + deadCount + ')' : '');

            $('#kpi-citas-pendientes').text(pendingCount);
            $('#kpi-citas-atendidas').text(attendedCount);
            $('#kpi-citas-muertos').text(deadCount);

            const filterHint = (filterStartDate || filterEndDate)
                ? 'Según filtro de fechas'
                : 'Total visible';
            $('#kpi-citas-pendientes-sub').text(filterHint);
            $('#kpi-citas-atendidas-sub').text(filterHint);
            $('#kpi-citas-muertos-sub').text(filterHint);
        }

        function activateCitasKpiCard(cardId) {
            $('.citas-kpi-card').removeClass('is-active');
            if (cardId) {
                $('#' + cardId).addClass('is-active');
            }
        }

        $('#citasKpiStrip .citas-kpi-card').on('click keydown', function (event) {
            if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            if (event.type === 'keydown') {
                event.preventDefault();
            }

            const tabSelector = $(this).data('tab-target');
            if (!tabSelector) {
                return;
            }

            const tabTrigger = document.querySelector(tabSelector);
            if (tabTrigger && window.bootstrap && bootstrap.Tab) {
                bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
            } else if (tabTrigger) {
                tabTrigger.click();
            }

            activateCitasKpiCard(this.id);
        });

        $('#pills-tab button[data-bs-toggle="pill"]').on('shown.bs.tab', function (event) {
            const tabId = event.target.id;
            if (tabId === 'pills-pending-tab') {
                activateCitasKpiCard('kpi-card-pending');
            } else if (tabId === 'pills-attended-tab') {
                activateCitasKpiCard('kpi-card-attended');
            } else if (tabId === 'pills-no-response-tab') {
                activateCitasKpiCard('kpi-card-dead');
            }
        });

        function sortRegistrosByAppointment(registros) {
            const now = new Date();

            return registros.slice().sort(function (a, b) {
                const fechaA = new Date(a.fecha + "T" + a.hora);
                const fechaB = new Date(b.fecha + "T" + b.hora);

                if (fechaA < now && fechaB >= now) {
                    return 1;
                }
                if (fechaB < now && fechaA >= now) {
                    return -1;
                }

                if (fechaA - fechaB !== 0) {
                    return fechaA - fechaB;
                }

                const horaA = a.hora.split(':').map(function (num) { return parseInt(num, 10); });
                const horaB = b.hora.split(':').map(function (num) { return parseInt(num, 10); });

                if (horaA[0] !== horaB[0]) {
                    return horaA[0] - horaB[0];
                }

                return horaA[1] - horaB[1];
            });
        }

        function splitRegistrosByStatus(registros) {
            return {
                pendientes: registros.filter(function (item) { return item.estatus === '0' || item.estatus === '2'; }),
                atendidas: registros.filter(function (item) { return item.estatus === '1'; }),
                sinRespuesta: registros.filter(function (item) { return item.estatus === '3'; })
            };
        }

        function updateNotificationBubbles(pendientes, atendidas, sinRespuesta) {
            const notificationBubblePendientes = document.getElementById('notification-bubble-pendientes');
            const notificationBubbleAtendidas = document.getElementById('notification-bubble-atendidos');
            const notificationBubbleMuertos = document.getElementById('notification-bubble-muertos');

            if (notificationBubblePendientes) {
                notificationBubblePendientes.textContent = pendientes.length;
                notificationBubblePendientes.style.display = 'none';
            }
            if (notificationBubbleAtendidas) {
                notificationBubbleAtendidas.textContent = atendidas.length;
                notificationBubbleAtendidas.style.display = 'none';
            }
            if (notificationBubbleMuertos) {
                notificationBubbleMuertos.textContent = sinRespuesta.length;
                notificationBubbleMuertos.style.display = 'none';
            }
        }

        function hasValidTime(timeText) {
            return !!String(timeText || '').match(/^\d{2}:\d{2}(:\d{2})?$/);
        }

        function normalizeTimeForMoment(timeText) {
            const raw = String(timeText || '').trim();
            if (!hasValidTime(raw)) {
                return '';
            }
            return raw.length === 5 ? `${raw}:00` : raw;
        }

        function isValidMomentTimezone(timezoneName) {
            return !!(timezoneName && window.moment && typeof window.moment.tz === 'function' && window.moment.tz.zone(timezoneName));
        }

        function getSellerMomentFromRow(row) {
            const dateText = String(row.fecha || '').trim();
            const timeText = normalizeTimeForMoment(row.hora);
            if (!hasValidDate(dateText) || !timeText) {
                return null;
            }

            if (isValidMomentTimezone(SELLER_BASE_TIMEZONE)) {
                return moment.tz(`${dateText} ${timeText}`, 'YYYY-MM-DD HH:mm:ss', SELLER_BASE_TIMEZONE);
            }
            const localMoment = moment(`${dateText} ${timeText}`, 'YYYY-MM-DD HH:mm:ss');
            return localMoment.isValid() ? localMoment : null;
        }

        function getClientMomentFromRow(row) {
            const customerDate = String(row.fecha_cliente || '').trim();
            const customerTime = normalizeTimeForMoment(row.hora_cliente);

            if (hasValidDate(customerDate) && customerTime && customerTime !== '00:00:00') {
                const storedMoment = moment(`${customerDate} ${customerTime}`, 'YYYY-MM-DD HH:mm:ss');
                if (storedMoment.isValid()) {
                    return storedMoment;
                }
            }

            if (row.appointment_utc) {
                const utcMoment = moment.utc(String(row.appointment_utc), 'YYYY-MM-DD HH:mm:ss');
                if (utcMoment.isValid()) {
                    if (isValidMomentTimezone(row.timezone_name)) {
                        return utcMoment.clone().tz(row.timezone_name);
                    }
                    const offsetMinutes = parseInt(row.timezone_offset_minutes, 10);
                    if (!isNaN(offsetMinutes)) {
                        return utcMoment.clone().utcOffset(-offsetMinutes);
                    }
                }
            }

            const sellerMoment = getSellerMomentFromRow(row);
            if (!sellerMoment) {
                return null;
            }

            const legacyOffsetHours = parseFloat(row.zona_horaria);
            if (!isNaN(legacyOffsetHours)) {
                return sellerMoment.clone().add(legacyOffsetHours, 'hours');
            }

            return null;
        }

        function formatMomentForDisplay(dateMoment) {
            if (!dateMoment || !dateMoment.isValid()) {
                return { date: 'Pendiente', time: 'Pendiente' };
            }
            return {
                date: dateMoment.format('dddd D [de] MMMM [del] YY'),
                time: dateMoment.format('hh:mm a')
            };
        }

        function renderSellerAppointmentCell(data, type) {
            if (data.table_origen === 'wedding_planners') {
                return 'Sin dato';
            }
            const sellerMoment = getSellerMomentFromRow(data);
            if (type === 'sort' || type === 'filter') {
                return sellerMoment && sellerMoment.isValid() ? sellerMoment.format('YYYY-MM-DD HH:mm:ss') : '';
            }
            const formatted = formatMomentForDisplay(sellerMoment);
            return `${formatted.date} ${formatted.time}`;
        }

        function renderClientAppointmentCell(data, type, includeCity) {
            if (data.table_origen === 'wedding_planners') {
                return 'Sin dato';
            }
            const clientMoment = getClientMomentFromRow(data);
            if (type === 'sort' || type === 'filter') {
                return clientMoment && clientMoment.isValid() ? clientMoment.format('YYYY-MM-DD HH:mm:ss') : '';
            }
            const formatted = formatMomentForDisplay(clientMoment);
            const city = includeCity && data.cliente_city && data.cliente_city.toString().trim() !== ''
                ? `<br>(${data.cliente_city})`
                : '';
            return `${formatted.date} ${formatted.time}${city}`;
        }


        $('#confirmar-reagendar').click(function () {
            const loadingSwal = Swal.fire({
                title: 'Reagendando...',
                text: 'Por favor espere mientras procesamos su solicitud.',
                allowOutsideClick: false, // Prevenir que el usuario cierre el alerta
                didOpen: () => {
                    Swal.showLoading(); // Mostrar el spinner de carga
                }
            });
            // Obtener los valores de los inputs
            var usuarioSeleccionado = $('#select-usuarios').val();
            var datetimeCliente = $('#datetime_appointment').val(); // Hora del CLIENTE
            var fechaVendedor = $('#reagenda-vendor-date').val();
            var horaVendedor = $('#reagenda-vendor-hour').val();
            var reagendarId = $('#reagendaId').val();
            var customerId = $('#customerId').val();
            var hasSeller = $('#hasSeller').val();

            // Separar fecha y hora del CLIENTE (obligatorio)
            var fechaCliente = '';
            var horaCliente = '';
            if (datetimeCliente) {
                var partsCliente = datetimeCliente.split('T');
                fechaCliente = partsCliente[0]; // YYYY-MM-DD
                horaCliente = partsCliente[1] + ':00'; // HH:MM:SS
            }

            if (horaVendedor && horaVendedor.length === 5) {
                horaVendedor = horaVendedor + ':00';
            }

            // Verificar si todos los campos están completos
            if (usuarioSeleccionado && fechaCliente && horaCliente && fechaVendedor && horaVendedor) {
                // Datos que se enviarán:
                // - fecha/hora = VENDEDOR
                // - fecha_cliente/hora_cliente = CLIENTE
                var data = {
                    id: reagendarId,
                    vendedor: usuarioSeleccionado,
                    fecha: fechaVendedor, // Hora del VENDEDOR (va a calendario.fecha)
                    hora: horaVendedor, // Hora del VENDEDOR (va a calendario.hora)
                    fecha_cliente: fechaCliente, // Hora del CLIENTE (va a calendario.fecha_cliente)
                    hora_cliente: horaCliente, // Hora del CLIENTE (va a calendario.hora_cliente)
                    cliente: customerId,
                    hasSeller: hasSeller

                };
                console.log("data ", data)

                // Enviar la solicitud AJAX
                $.ajax({
                    url: 'reagendar.php', // Ruta al archivo PHP
                    type: 'POST',
                    data: data,
                    success: function (response) {
                        console.log(response)
                        loadingSwal.close();

                        var result = parseAjaxJson(response); // Parsear la respuesta JSON
                        if (result.status === 'success') {
                            // Mostrar SweetAlert de éxito
                            Swal.fire({
                                icon: 'success',
                                title: '¡Cita actualizada!',
                                text: result.message,
                                confirmButtonText: 'Aceptar'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    // Recargar la página cuando se hace clic en "Aceptar"
                                    location.reload();
                                }
                            });
                        } else {
                            // Mostrar SweetAlert de error
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: result.message,
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        loadingSwal.close();
                        console.error('Error al reagendar:', status, error, xhr && xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: getAjaxErrorMessage(xhr, 'No se pudo actualizar la cita. Por favor, intenta nuevamente.'),
                            confirmButtonText: 'Aceptar'
                        });
                    }
                });
            } else {
                loadingSwal.close();
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos incompletos',
                    text: 'Selecciona la vendedora, la hora del cliente y un dia/horario disponible de la vendedora.',
                    confirmButtonText: 'Aceptar'
                });
            }
        });

        $('#select-usuarios').on('change', function () {
            const usuarioSeleccionadoId = $(this).val();
            if (usuarioSeleccionadoId) {
                $('#show_inputs').show();
                initializeReagendaVendorSchedule(usuarioSeleccionadoId, reagendaCurrentFecha, reagendaCurrentHora);
            } else {
                $('#show_inputs').hide();
                resetReagendaVendorSchedule();
                console.log('No se ha seleccionado ningún usuario.');
            }
        });

        // Función para calcular diferencia de zonas horarias
        function calcularDiferenciaZonaHoraria() {
            const datetimeCliente = $('#datetime_appointment').val(); // CLIENTE
            const vendorDate = $('#reagenda-vendor-date').val();
            const vendorHour = $('#reagenda-vendor-hour').val();

            if (datetimeCliente) {
                const momentCliente = moment(datetimeCliente);
                $('#clientTimePreview').html(`Vista previa: ${momentCliente.format('DD/MM/YYYY hh:mm A')}`);

                if (vendorDate && vendorHour) {
                    const momentVendedor = moment(vendorDate + 'T' + vendorHour);
                    $('#sellerTimePreview').html(`Vista previa vendedora: ${momentVendedor.format('DD/MM/YYYY hh:mm A')}`);

                    const diffHours = momentVendedor.diff(momentCliente, 'hours', true);

                    if (Math.abs(diffHours) > 0.1) {
                        let diffText = '';
                        if (diffHours > 0) {
                            diffText = `El vendedor esta ${Math.abs(diffHours).toFixed(1)} horas adelante del cliente`;
                        } else {
                            diffText = `El vendedor esta ${Math.abs(diffHours).toFixed(1)} horas atras del cliente`;
                        }
                        $('#timeDiffText').text(diffText);
                        $('#timeDifferenceAlert').show();
                    } else {
                        $('#timeDifferenceAlert').hide();
                    }
                } else {
                    $('#sellerTimePreview').html('');
                    $('#timeDifferenceAlert').hide();
                }
            }
        }

        // Event listeners para calcular diferencia cuando cambian los campos
        $('#datetime_appointment').on('change', calcularDiferenciaZonaHoraria);

        // Código antiguo de validación de fechas bloqueadas - comentado porque ahora usamos datetime-local
        // Si se necesita validación de fechas bloqueadas, se puede agregar aquí

        $('#appointmentDateFilterForm').on('submit', function (event) {
            const startDate = $('#filterStartDate').val();
            const endDate = $('#filterEndDate').val();

            if (startDate && endDate && startDate > endDate) {
                event.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Rango inválido',
                    text: 'La fecha inicial no puede ser mayor que la fecha final.'
                });
                return;
            }

            if (!allRegistroConVendedor.length) {
                return;
            }

            event.preventDefault();
            filterStartDate = startDate;
            filterEndDate = endDate;

            const registrosFiltrados = filterRegistrosByAppointmentDate(
                allRegistroConVendedor,
                filterStartDate,
                filterEndDate
            );
            const registrosOrdenados = sortRegistrosByAppointment(registrosFiltrados);
            const grupos = splitRegistrosByStatus(registrosOrdenados);

            updateNotificationBubbles(grupos.pendientes, grupos.atendidas, grupos.sinRespuesta);
            updateAppointmentTableCounts(grupos.pendientes, grupos.atendidas, grupos.sinRespuesta);

            if ($.fn.DataTable.isDataTable('#pendingAppointmentsTable')) {
                $('#pendingAppointmentsTable').DataTable().clear().rows.add(grupos.pendientes).draw();
            }
            if ($.fn.DataTable.isDataTable('#attendedAppointmentsTable')) {
                $('#attendedAppointmentsTable').DataTable().clear().rows.add(grupos.atendidas).draw();
            }
            if ($.fn.DataTable.isDataTable('#noResponseAppointmentsTable')) {
                $('#noResponseAppointmentsTable').DataTable().clear().rows.add(grupos.sinRespuesta).draw();
            }

            const params = new URLSearchParams();
            if (filterStartDate) {
                params.set('start_date', filterStartDate);
            }
            if (filterEndDate) {
                params.set('end_date', filterEndDate);
            }
            const query = params.toString();
            const nextUrl = query ? ('consulta.php?' + query) : 'consulta.php';
            window.history.replaceState({}, '', nextUrl);
        });

        $.ajax({
            url: 'obtener-datos-formulario.php', // Ruta del archivo PHP
            type: 'GET', // Método de solicitud
            dataType: 'json', // Esperamos que nos devuelvan un JSON
            success: function (response) {
                // Verificar si hay datos en la respuesta
                if (response.data && response.data.length > 0) {






                    const registroConVendedorBase = canViewAllAppointments
                        ? response.data.filter(item => item.is_cliente != "1")
                        : response.data.filter(item => ((item.idusu !== "0" && item.idusu == idUsu) || item.idusu === "0") && item.is_cliente != "1")
                    allRegistroConVendedor = registroConVendedorBase.slice();
                    const registroConVendedor = filterRegistrosByAppointmentDate(
                        allRegistroConVendedor,
                        filterStartDate,
                        filterEndDate
                    );
                    console.log("registroConVendedor ", registroConVendedor)

                    const registrosOrdenados = sortRegistrosByAppointment(registroConVendedor);
                    const grupos = splitRegistrosByStatus(registrosOrdenados);
                    let pendientes = grupos.pendientes;
                    let atendidas = grupos.atendidas;
                    let sin_respuesta = grupos.sinRespuesta;
                    console.log("pendientes ", pendientes);

                    updateNotificationBubbles(pendientes, atendidas, sin_respuesta);
                    updateAppointmentTableCounts(pendientes, atendidas, sin_respuesta);

                    //tabla con vendedor
                    $('#pendingAppointmentsTable').DataTable({
                        data: pendientes, // Asignamos los datos obtenidos
                        columns: [
                            { data: 'id' },
                            { data: 'idclie' },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    // Mostrar 'WP' si proviene de wedding_planners
                                    if (data.table_origen === 'wedding_planners') {
                                        return 'WP';
                                    }
                                    console.log("data campanin", data)
                                    // Si tiene campaign_name, mostrar el nombre de la campaña
                                    if (data.campaign_name && data.campaign_name.trim() !== '') {
                                        var cname = data.campaign_name;
                                        // Verificar si empieza con c + número + punto
                                        var match = cname.match(/^c(\d+)\./i);
                                        if (match) {
                                            // Mostrar solo "c" + número
                                            return 'c' + match[1];
                                        } else {
                                            // Mostrar el nombre completo
                                            return cname;
                                        }
                                    }
                                    // No campaign_name -> no usar desde_publicidad
                                    return '';
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    return moment(data.cliente_submission_date).format('DD/MM/YYYY');
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    return moment(data.cliente_submission_date).format('hh:mm a');
                                }
                            },
                            { data: 'cliente_names' },
                            {
                                data: null,
                                render: function (data) {
                                    if (!data.cliente_telefono || data.cliente_telefono.toString().trim() === '') return 'Sin dato';
                                    return data.cliente_telefono;
                                }
                            },
                            {
                                data: null,
                                render: function (data) {
                                    if (!data.cliente_email_address || data.cliente_email_address.toString().trim() === '') return 'Sin dato';
                                    return data.cliente_email_address;
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    return renderClientAppointmentCell(data, type, true);
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    return renderSellerAppointmentCell(data, type);
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    if (data.table_origen === 'wedding_planners') {
                                        // Mostrar vendedor asignado desde contact_form si existe
                                        if (data.vendedor_asignado_nombre) {
                                            return data.vendedor_asignado_nombre + ' ' + (data.vendedor_asignado_apePat || '') + ' ' + (data.vendedor_asignado_apeMat || '');
                                        }
                                        return 'Sin asesor asignado';
                                    }
                                    return data.usuario_nombre + ' ' + data.usuario_apePat + ' ' + data.usuario_apeMat;
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    // Verificar si el estatus es expirado
                                    if (data.estatus == 0 || data.estatus == "0") {
                                        return '<span >Pendiente</span>';
                                    } else if (data.estatus == 2 || data.estatus == "2") {  // Aquí puedes poner el valor o la condición para "expirado"
                                        return '<span style="color: red;">Expirado</span>';
                                    } else {
                                        return 'Atendida';
                                    }
                                }
                            },

                            {
                                data: null,
                                render: function (data, type, row) {
                                    console.log("data acciones ", data);

                                    const botonVerMas = '<button class="mx-2 my-1 btn btn-primary btn-sm btn-vermas" ' +
                                        'data-id="' + data.id + '" ' +
                                        'data-idusu="' + data.idusu + '" ' +
                                        'data-idclie="' + data.idclie + '" ' +
                                        'data-country_code="' + data.country_code + '" ' +
                                        'data-enlace="' + data.enlace_meet + '" ' +
                                        'data-nombre="' + (data.cliente_names || '') + '" ' +
                                        'data-telefono="' + (data.cliente_telefono || '') + '" ' +
                                        'data-email="' + (data.cliente_email_address || '') + '" ' +
                                        'data-fecha="' + (data.fecha || '') + '" ' +
                                        'data-hora="' + (data.hora || '') + '" ' +
                                        'data-fecha_cliente="' + (data.fecha_cliente || '') + '" ' +
                                        'data-hora_cliente="' + (data.hora_cliente || '') + '" ' +
                                        'data-appointment_utc="' + (data.appointment_utc || '') + '" ' +
                                        'data-timezone_name="' + (data.timezone_name || '') + '" ' +
                                        'data-timezone_offset_minutes="' + (data.timezone_offset_minutes || '') + '" ' +
                                        'data-asesor="' + (data.usuario_nombre + ' ' + data.usuario_apePat + ' ' + data.usuario_apeMat || '') + '" ' +
                                        'data-vendedor_asignado_nombre="' + (data.vendedor_asignado_nombre || '') + '" ' +
                                        'data-vendedor_asignado_apepat="' + (data.vendedor_asignado_apePat || '') + '" ' +
                                        'data-vendedor_asignado_apemat="' + (data.vendedor_asignado_apeMat || '') + '" ' +
                                        'data-table_origen="' + (data.table_origen || '') + '" ' +
                                        'data-nota="' + (data.nota_sesion || data.comentario || data.nota || '') + '" ' +
                                        'data-titulo="' + (data.titulo || '') + '" ' +
                                        'data-cliente_names="' + (data.cliente_names || '') + '" ' +
                                        'data-cliente_telefono="' + (data.cliente_telefono || '') + '" ' +
                                        'data-email_address="' + (data.email_address || '') + '" ' +
                                        'data-cliente_additional_details="' + (data.cliente_additional_details || '') + '" ' +
                                        'data-cliente_couple_activities="' + (data.cliente_couple_activities || '') + '" ' +
                                        'data-cliente_date_appointment="' + (data.cliente_date_appointment || '') + '" ' +
                                        'data-cliente_favorite_movie_or_song="' + (data.cliente_favorite_movie_or_song || '') + '" ' +
                                        'data-cliente_guests_count="' + (data.cliente_guests_count || '') + '" ' +
                                        'data-cliente_hear_about_us="' + (data.cliente_hear_about_us || '') + '" ' +
                                        'data-cliente_how_did_you_meet="' + (data.cliente_how_did_you_meet || '') + '" ' +
                                        'data-cliente_instagram_handle="' + (data.cliente_instagram_handle || 'No compartido') + '" ' +
                                        'data-cliente_look_preference="' + (data.cliente_look_preference || '') + '" ' +
                                        'data-cliente_service_interest="' + (data.cliente_service_interest || '') + '" ' +
                                        'data-cliente_submission_date="' + (data.cliente_submission_date || '') + '" ' +
                                        'data-cliente_time_appointment="' + (data.cliente_time_appointment || '') + '" ' +
                                        'data-cliente_wedding_date="' + (data.cliente_wedding_date || '') + '" ' +
                                        'data-cliente_wedding_location="' + (data.cliente_wedding_location || '') + '" ' +
                                        'data-cliente_wedding_planner="' + (data.cliente_wedding_planner || '') + '" ' +
                                        'data-cliente_campaign_name="' + (data.campaign_name || '') + '" ' +
                                        'data-cliente_form_name="' + (data.form_name || '') + '" ' +
                                        'data-comentario_desde_wp="' + ((data.comentario_desde_wp || '').replace(/"/g, '&quot;')) + '" ' +
                                        'data-desde_publicidad="' + data.desde_publicidad + '">' +
                                        'Ver más info ' + (data.desde_publicidad == '1' ? '📢' : '') +
                                        '</button>';

                                    const botonCambiarEstatus = '<button class="mx-2 my-1 btn btn-secondary btn-sm btn-cambiar-estatus" ' +
                                        'data-estatus="' + data.estatus + '" ' +
                                        'data-idclie="' + data.idclie + '" ' +
                                        'data-id="' + data.id + '" ' +
                                        'data-idusu="' + data.idusu + '" ' +
                                        'data-nombre="' + (data.cliente_names || '').replace(/"/g, '&quot;') + '" ' +
                                        'data-email="' + (data.cliente_email_address || '').replace(/"/g, '&quot;') + '" ' +
                                        'data-telefono="' + (data.cliente_telefono || '') + '" ' +
                                        'data-country_code="' + (data.country_code || '') + '" ' +
                                        'data-wedding_date="' + (data.cliente_wedding_date || '') + '" ' +
                                        'data-wedding_location="' + (data.cliente_wedding_location || '').replace(/"/g, '&quot;') + '" ' +
                                        'data-vendedor="' + (data.vendedor_asignado_nombre || data.usuario_nombre || '').replace(/"/g, '&quot;') + '" ' +
                                        'data-how_did_you_meet="' + (data.cliente_how_did_you_meet || '') + '" ' +
                                        'data-hear_about_us="' + (data.cliente_hear_about_us || '') + '" ' +
                                        'data-how_long_known_us="' + (data.cliente_how_long_known_us || '') + '" ' +
                                        'data-tipo_cliente="' + (data.tipo_cliente !== null && data.tipo_cliente !== undefined ? data.tipo_cliente : '') + '">' +
                                        'Cambiar estatus</button>';

                                    const botonReagendar = '<button class="mx-2 my-1 btn btn-dark btn-sm btn-reagendar" ' +
                                        'data-fecha="' + data.fecha + '" ' +
                                        'data-hora="' + data.hora + '" ' +
                                        'data-fecha_cliente="' + (data.fecha_cliente || '') + '" ' +
                                        'data-hora_cliente="' + (data.hora_cliente || '') + '" ' +
                                        'data-idclie="' + data.idclie + '" ' +
                                        'data-id="' + data.id + '" ' +
                                        'data-idusu="' + data.idusu + '">' +
                                        'Reagendar</button>';

                                    const botonEliminar = '<button class="mx-2 my-1 btn btn-danger btn-sm btn-eliminar" ' +
                                        'data-id="' + data.id + '">' +
                                        'Eliminar</button>';

                                    // Solo admin gestiona citas; marketing solo consulta
                                    if (isAdminUser) {
                                        return botonVerMas + botonCambiarEstatus + botonReagendar + botonEliminar;
                                    } else if (isMarketingUser) {
                                        return botonVerMas;
                                    } else {
                                        return botonVerMas + botonCambiarEstatus;
                                    }
                                }
                            }

                        ],
                        responsive: true,
                        language: {
                            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-MX.json' // Traducción al español
                        }, order: [], // No ordenar ninguna columna por defecto
                        columnDefs: [
                            {
                                targets: [0], // Desactivar la ordenación de la columna `id`
                                orderable: false // No se puede ordenar por la columna `id`
                            },
                            {
                                targets: [2], // Columna Origen
                                className: 'text-center'
                            }
                        ]
                    });

                    // Normalize placeholder times (12:00 a.m./00:00) to 'Pendiente' after tables render
                    function normalizePlaceholderTimes(selector) {
                        try {
                            $(selector).find('td').each(function() {
                                var txt = $(this).text().trim();
                                if (txt === '12:00 a.m.' || txt === '12:00 AM' || txt === '12:00 am' || txt === '00:00' || txt === '00:00:00' || txt === '00:00 a.m.') {
                                    $(this).text('Pendiente');
                                }
                            });
                        } catch (e) { console.warn('normalizePlaceholderTimes error', e); }
                    }
                    // Run immediately for pending table and bind to redraw to keep it in sync
                    normalizePlaceholderTimes('#pendingAppointmentsTable');
                    $('#pendingAppointmentsTable').on('draw.dt', function () { 
                        normalizePlaceholderTimes('#pendingAppointmentsTable');
                        var dtPend = $('#pendingAppointmentsTable').DataTable();
                        dtPend.rows().every(function(){
                            var d = this.data();
                            var tr = $(this.node());
                            // Phone (col 6) and email (col 7)
                            if (!d || !d.cliente_telefono || d.cliente_telefono.toString().trim() === '' || d.table_origen === 'wedding_planners'){
                                tr.find('td').eq(6).text('Sin dato');
                            }
                            if (!d || !d.cliente_email_address || d.cliente_email_address.toString().trim() === '' || d.table_origen === 'wedding_planners'){
                                tr.find('td').eq(7).text('Sin dato');
                            }
                        });
                    });

                    $('#attendedAppointmentsTable').DataTable({
                        data: atendidas, // Asignamos los datos obtenidos
                        columns: [
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    // Mostrar 'WP' si proviene de wedding_planners
                                    if (data.table_origen === 'wedding_planners') {
                                        return 'WP';
                                    }
                                    // Si tiene campaign_name, mostrar el nombre de la campaña
                                    if (data.campaign_name && data.campaign_name.trim() !== '') {
                                        var cname = data.campaign_name;
                                        // Verificar si empieza con c + número + punto
                                        var match = cname.match(/^c(\d+)\./i);
                                        if (match) {
                                            // Mostrar solo "c" + número
                                            return 'c' + match[1];
                                        } else {
                                            // Mostrar el nombre completo
                                            return cname;
                                        }
                                    }
                                    // No campaign_name -> no usar desde_publicidad
                                    return '';
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    return moment(data.cliente_submission_date).format('DD/MM/YYYY');
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    return moment(data.cliente_submission_date).format('hh:mm a');
                                }
                            },
                            { data: 'cliente_names' },
                            { data: 'cliente_telefono' },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    return renderClientAppointmentCell(data, type, false);
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    return renderSellerAppointmentCell(data, type);
                                }
                            },

                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    if (data.table_origen === 'wedding_planners') {
                                        // Mostrar vendedor asignado desde contact_form si existe
                                        if (data.vendedor_asignado_nombre) {
                                            return data.vendedor_asignado_nombre + ' ' + (data.vendedor_asignado_apePat || '') + ' ' + (data.vendedor_asignado_apeMat || '');
                                        }
                                        return 'Sin asesor asignado';
                                    }
                                    return data.usuario_nombre + ' ' + data.usuario_apePat + ' ' + data.usuario_apeMat;
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    // Verificar si el estatus es expirado
                                    if (data.estatus == 0 || data.estatus == "0") {
                                        return '<span >Pendiente</span>';
                                    } else if (data.estatus == 2 || data.estatus == "2") {  // Aquí puedes poner el valor o la condición para "expirado"
                                        return '<span style="color: red;">Expirado</span>';
                                    } else {
                                        return 'Atendida';
                                    }
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a agregar los botones
                                render: function (data, type, row) {

                                    const botonVerMas = '<button class="mx-2 my-1 btn btn-primary btn-sm btn-vermas" ' +
                                        'data-id="' + data.id + '" ' +
                                        'data-idusu="' + data.idusu + '" ' +
                                        'data-idclie="' + data.idclie + '" ' +
                                        'data-country_code="' + data.country_code + '" ' +
                                        'data-enlace="' + data.enlace_meet + '" ' +
                                        'data-nombre="' + (data.cliente_names || '') + '" ' +
                                        'data-telefono="' + (data.cliente_telefono || '') + '" ' +
                                        'data-email="' + (data.cliente_email_address || '') + '" ' +
                                        'data-fecha="' + (data.fecha || '') + '" ' +
                                        'data-hora="' + (data.hora || '') + '" ' +
                                        'data-fecha_cliente="' + (data.fecha_cliente || '') + '" ' +
                                        'data-hora_cliente="' + (data.hora_cliente || '') + '" ' +
                                        'data-appointment_utc="' + (data.appointment_utc || '') + '" ' +
                                        'data-timezone_name="' + (data.timezone_name || '') + '" ' +
                                        'data-timezone_offset_minutes="' + (data.timezone_offset_minutes || '') + '" ' +
                                        'data-asesor="' + (data.usuario_nombre + ' ' + data.usuario_apePat + ' ' + data.usuario_apeMat || '') + '" ' +
                                        'data-nota="' + (data.nota_sesion || data.comentario || data.nota || '') + '" ' +
                                        'data-titulo="' + (data.titulo || '') + '" ' +
                                        'data-cliente_names="' + (data.cliente_names || '') + '" ' +
                                        'data-cliente_telefono="' + (data.cliente_telefono || '') + '" ' +
                                        'data-email_address="' + (data.email_address || '') + '" ' +
                                        'data-cliente_additional_details="' + (data.cliente_additional_details || '') + '" ' +
                                        'data-cliente_couple_activities="' + (data.cliente_couple_activities || '') + '" ' +
                                        'data-cliente_date_appointment="' + (data.cliente_date_appointment || '') + '" ' +
                                        'data-cliente_favorite_movie_or_song="' + (data.cliente_favorite_movie_or_song || '') + '" ' +
                                        'data-cliente_guests_count="' + (data.cliente_guests_count || '') + '" ' +
                                        'data-cliente_hear_about_us="' + (data.cliente_hear_about_us || '') + '" ' +
                                        'data-cliente_how_did_you_meet="' + (data.cliente_how_did_you_meet || '') + '" ' +
                                        'data-cliente_instagram_handle="' + (data.cliente_instagram_handle || 'No compartido') + '" ' +
                                        'data-cliente_look_preference="' + (data.cliente_look_preference || '') + '" ' +
                                        'data-cliente_service_interest="' + (data.cliente_service_interest || '') + '" ' +
                                        'data-cliente_submission_date="' + (data.cliente_submission_date || '') + '" ' +
                                        'data-cliente_time_appointment="' + (data.cliente_time_appointment || '') + '" ' +
                                        'data-cliente_wedding_date="' + (data.cliente_wedding_date || '') + '" ' +
                                        'data-cliente_wedding_location="' + (data.cliente_wedding_location || '') + '" ' +
                                        'data-cliente_wedding_planner="' + (data.cliente_wedding_planner || '') + '" ' +
                                        'data-cliente_campaign_name="' + (data.campaign_name || '') + '" ' +
                                        'data-cliente_form_name="' + (data.form_name || '') + '" ' +
                                        'data-desde_publicidad="' + data.desde_publicidad + '">Ver más info</button>';

                                    const botonCambiarEstatus = '<button class="mx-2 my-1 btn btn-secondary btn-sm btn-cambiar-estatus" ' +
                                        'data-estatus="' + data.estatus + '" ' +
                                        'data-idclie="' + data.idclie + '" ' +
                                        'data-id="' + data.id + '" ' +
                                        'data-idusu="' + data.idusu + '" ' +
                                        'data-nombre="' + (data.cliente_names || '').replace(/"/g, '&quot;') + '" ' +
                                        'data-email="' + (data.cliente_email_address || '').replace(/"/g, '&quot;') + '" ' +
                                        'data-telefono="' + (data.cliente_telefono || '') + '" ' +
                                        'data-country_code="' + (data.country_code || '') + '" ' +
                                        'data-wedding_date="' + (data.cliente_wedding_date || '') + '" ' +
                                        'data-wedding_location="' + (data.cliente_wedding_location || '').replace(/"/g, '&quot;') + '" ' +
                                        'data-vendedor="' + (data.vendedor_asignado_nombre || data.usuario_nombre || '').replace(/"/g, '&quot;') + '" ' +
                                        'data-how_did_you_meet="' + (data.cliente_how_did_you_meet || '') + '" ' +
                                        'data-hear_about_us="' + (data.cliente_hear_about_us || '') + '" ' +
                                        'data-how_long_known_us="' + (data.cliente_how_long_known_us || '') + '" ' +
                                        'data-tipo_cliente="' + (data.tipo_cliente !== null && data.tipo_cliente !== undefined ? data.tipo_cliente : '') + '">Cambiar estatus</button>';

                                    if (isMarketingUser) {
                                        return botonVerMas;
                                    }
                                    return botonVerMas + botonCambiarEstatus;

                                }
                            }
                        ],
                        responsive: true,
                        language: {
                            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-MX.json' // Traducción al español
                        }, order: [], // No ordenar ninguna columna por defecto
                        columnDefs: [
                            {
                                targets: [0], // Columna Origen
                                className: 'text-center'
                            }
                        ]
                    });
                    // Run normalization and bind to draw for attended
                    normalizePlaceholderTimes('#attendedAppointmentsTable');
                    $('#attendedAppointmentsTable').on('draw.dt', function () { 
                        normalizePlaceholderTimes('#attendedAppointmentsTable');
                        // Marcar como 'Sin dato' las celdas correspondientes cuando el origen sea wedding_planners o falten datos
                        var dtAtt = $('#attendedAppointmentsTable').DataTable();
                        dtAtt.rows().every(function(){
                            var d = this.data();
                            var tr = $(this.node());
                            if (d && d.table_origen === 'wedding_planners'){
                                tr.find('td').eq(5).text('Sin dato'); // Hora vendedor
                                tr.find('td').eq(6).text('Sin dato'); // Hora cliente
                                // NO sobrescribir asesor - ya se maneja en el render
                            }
                            // Phone column (index 4) mark Sin dato if missing
                            if (!d || !d.cliente_telefono || d.cliente_telefono.toString().trim() === ''){
                                tr.find('td').eq(4).text('Sin dato');
                            }
                        });
                    });
                    // Also normalize for no-response table
                    normalizePlaceholderTimes('#noResponseAppointmentsTable');
                    $('#noResponseAppointmentsTable').on('draw.dt', function () { 
                        normalizePlaceholderTimes('#noResponseAppointmentsTable');
                        var dtNo = $('#noResponseAppointmentsTable').DataTable();
                        dtNo.rows().every(function(){
                            var d = this.data();
                            if (d && d.table_origen === 'wedding_planners'){
                                var tr = $(this.node());
                                tr.find('td').eq(5).text('Sin dato'); // Hora vendedor
                                tr.find('td').eq(6).text('Sin dato'); // Hora cliente
                                // NO sobrescribir asesor - ya se maneja en el render
                            }
                        });
                    });

                    $('#noResponseAppointmentsTable').DataTable({
                        data: sin_respuesta, // Asignamos los datos obtenidos
                        columns: [
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    // Mostrar 'WP' si proviene de wedding_planners
                                    if (data.table_origen === 'wedding_planners') {
                                        return 'WP';
                                    }
                                    // Si tiene campaign_name, mostrar el nombre de la campaña
                                    if (data.campaign_name && data.campaign_name.trim() !== '') {
                                        var cname = data.campaign_name;
                                        // Verificar si empieza con c + número + punto
                                        var match = cname.match(/^c(\d+)\./i);
                                        if (match) {
                                            // Mostrar solo "c" + número
                                            return 'c' + match[1];
                                        } else {
                                            // Mostrar el nombre completo
                                            return cname;
                                        }
                                    }
                                    // No campaign_name -> no usar desde_publicidad
                                    return '';
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    return moment(data.cliente_submission_date).format('DD/MM/YYYY');
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    return moment(data.cliente_submission_date).format('hh:mm a');
                                }
                            },
                            { data: 'cliente_names' },
                            { data: 'cliente_telefono' },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    return renderClientAppointmentCell(data, type, false);
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    return renderSellerAppointmentCell(data, type);
                                }
                            },

                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    if (data.table_origen === 'wedding_planners') {
                                        // Mostrar vendedor asignado desde contact_form si existe
                                        if (data.vendedor_asignado_nombre) {
                                            return data.vendedor_asignado_nombre + ' ' + (data.vendedor_asignado_apePat || '') + ' ' + (data.vendedor_asignado_apeMat || '');
                                        }
                                        return 'Sin asesor asignado';
                                    }
                                    return data.usuario_nombre + ' ' + data.usuario_apePat + ' ' + data.usuario_apeMat;
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a concatenar
                                render: function (data, type, row) {
                                    // Verificar si el estatus es expirado
                                    if (data.estatus == 0 || data.estatus == "0") {
                                        return '<span >Pendiente</span>';
                                    } else if (data.estatus == 3 || data.estatus == "3") {  // Aquí puedes poner el valor o la condición para "expirado"
                                        return '<span style="color: red;">Muerto</span>';
                                    } else {
                                        return 'Atendida';
                                    }
                                }
                            },
                            {
                                data: null, // No usamos un campo directo, vamos a agregar los botones
                                render: function (data, type, row) {

                                    const botonVerMas = '<button class="mx-2 my-1 btn btn-primary btn-sm btn-vermas" ' +
                                        'data-id="' + data.id + '" ' +
                                        'data-idusu="' + data.idusu + '" ' +
                                        'data-idclie="' + data.idclie + '" ' +
                                        'data-country_code="' + data.country_code + '" ' +
                                        'data-enlace="' + data.enlace_meet + '" ' +
                                        'data-nombre="' + (data.cliente_names || '') + '" ' +
                                        'data-telefono="' + (data.cliente_telefono || '') + '" ' +
                                        'data-email="' + (data.cliente_email_address || '') + '" ' +
                                        'data-fecha="' + (data.fecha || '') + '" ' +
                                        'data-hora="' + (data.hora || '') + '" ' +
                                        'data-fecha_cliente="' + (data.fecha_cliente || '') + '" ' +
                                        'data-hora_cliente="' + (data.hora_cliente || '') + '" ' +
                                        'data-appointment_utc="' + (data.appointment_utc || '') + '" ' +
                                        'data-timezone_name="' + (data.timezone_name || '') + '" ' +
                                        'data-timezone_offset_minutes="' + (data.timezone_offset_minutes || '') + '" ' +
                                        'data-asesor="' + (data.usuario_nombre + ' ' + data.usuario_apePat + ' ' + data.usuario_apeMat || '') + '" ' +
                                        'data-nota="' + (data.nota_sesion || data.comentario || data.nota || '') + '" ' +
                                        'data-titulo="' + (data.titulo || '') + '" ' +
                                        'data-cliente_names="' + (data.cliente_names || '') + '" ' +
                                        'data-cliente_telefono="' + (data.cliente_telefono || '') + '" ' +
                                        'data-email_address="' + (data.email_address || '') + '" ' +
                                        'data-cliente_additional_details="' + (data.cliente_additional_details || '') + '" ' +
                                        'data-cliente_couple_activities="' + (data.cliente_couple_activities || '') + '" ' +
                                        'data-cliente_date_appointment="' + (data.cliente_date_appointment || '') + '" ' +
                                        'data-cliente_favorite_movie_or_song="' + (data.cliente_favorite_movie_or_song || '') + '" ' +
                                        'data-cliente_guests_count="' + (data.cliente_guests_count || '') + '" ' +
                                        'data-cliente_hear_about_us="' + (data.cliente_hear_about_us || '') + '" ' +
                                        'data-cliente_how_did_you_meet="' + (data.cliente_how_did_you_meet || '') + '" ' +
                                        'data-cliente_instagram_handle="' + (data.cliente_instagram_handle || 'No compartido') + '" ' +
                                        'data-cliente_look_preference="' + (data.cliente_look_preference || '') + '" ' +
                                        'data-cliente_service_interest="' + (data.cliente_service_interest || '') + '" ' +
                                        'data-cliente_submission_date="' + (data.cliente_submission_date || '') + '" ' +
                                        'data-cliente_time_appointment="' + (data.cliente_time_appointment || '') + '" ' +
                                        'data-cliente_wedding_date="' + (data.cliente_wedding_date || '') + '" ' +
                                        'data-cliente_wedding_location="' + (data.cliente_wedding_location || '') + '" ' +
                                        'data-cliente_wedding_planner="' + (data.cliente_wedding_planner || '') + '" ' +
                                        'data-cliente_campaign_name="' + (data.campaign_name || '') + '" ' +
                                        'data-cliente_form_name="' + (data.form_name || '') + '" ' +
                                        'data-desde_publicidad="' + data.desde_publicidad + '">Ver más info</button>';

                                    const botonCambiarEstatus = '<button class="mx-2 my-1 btn btn-secondary btn-sm btn-cambiar-estatus" ' +
                                        'data-estatus="' + data.estatus + '" ' +
                                        'data-idclie="' + data.idclie + '" ' +
                                        'data-id="' + data.id + '" ' +
                                        'data-idusu="' + data.idusu + '" ' +
                                        'data-nombre="' + (data.cliente_names || '').replace(/"/g, '&quot;') + '" ' +
                                        'data-email="' + (data.cliente_email_address || '').replace(/"/g, '&quot;') + '" ' +
                                        'data-telefono="' + (data.cliente_telefono || '') + '" ' +
                                        'data-country_code="' + (data.country_code || '') + '" ' +
                                        'data-wedding_date="' + (data.cliente_wedding_date || '') + '" ' +
                                        'data-wedding_location="' + (data.cliente_wedding_location || '').replace(/"/g, '&quot;') + '" ' +
                                        'data-vendedor="' + (data.vendedor_asignado_nombre || data.usuario_nombre || '').replace(/"/g, '&quot;') + '" ' +
                                        'data-how_did_you_meet="' + (data.cliente_how_did_you_meet || '') + '" ' +
                                        'data-hear_about_us="' + (data.cliente_hear_about_us || '') + '" ' +
                                        'data-how_long_known_us="' + (data.cliente_how_long_known_us || '') + '" ' +
                                        'data-tipo_cliente="' + (data.tipo_cliente !== null && data.tipo_cliente !== undefined ? data.tipo_cliente : '') + '">Cambiar estatus</button>';

                                    if (isMarketingUser) {
                                        return botonVerMas;
                                    }
                                    return botonVerMas + botonCambiarEstatus;

                                }
                            }
                        ],
                        responsive: true,
                        language: {
                            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-MX.json' // Traducción al español
                        }, order: [], // No ordenar ninguna columna por defecto
                        columnDefs: [
                            {
                                targets: [0], // Columna Origen
                                className: 'text-center'
                            }
                        ]
                    });


                } else {
                    // Si no hay datos, mostramos un mensaje o hacemos alguna acción
                    $('#pendingAppointmentsTable').html('<tr><td colspan="6">No hay datos disponibles</td></tr>');
                    $('#attendedAppointmentsTable').html('<tr><td colspan="6">No hay datos disponibles</td></tr>');

                }
            },
            error: function (xhr, status, error) {
                // Manejo de errores si la llamada Ajax falla
                console.error("Error en la solicitud Ajax: " + error);
            }
        });

        $('#pendingAppointmentsTable').on('click', '.btn-eliminar', function () {
            var id = $(this).data('id');
            console.log("id ", id);

            // SweetAlert: confirmación de eliminación
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Si el usuario confirma la eliminación
                    // Aquí deberías hacer la llamada para eliminar el item, por ejemplo, enviando el id al servidor.


                    // Aquí iría tu lógica para eliminar el elemento
                    // Por ejemplo, una llamada Ajax:
                    $.ajax({
                        url: 'eliminar-cita.php',
                        method: 'POST',
                        data: { id: id },
                        success: function (res) {
                            let response = JSON.parse(res)
                            console.log("response ", response)
                            if (response.status === 'success') {
                                Swal.fire('Eliminado!', response.message, 'success').then(() => {
                                    window.location.reload()
                                })
                                // Aquí puedes realizar cualquier acción adicional, como eliminar la fila de la tabla
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function (xhr, status, error) {
                            Swal.fire('Error', 'Hubo un problema al procesar la solicitud.', 'error');
                        }
                    });

                    // Ejemplo para eliminar la fila del DOM (si es necesario)
                    // $(this).closest('tr').remove();
                }
            });
        });

        $('#pendingAppointmentsTable, #attendedAppointmentsTable, #noResponseAppointmentsTable').on('click', '.btn-cambiar-estatus', function () {

            var id = $(this).data('id');
            var idusu = $(this).data('idusu');
            var idclie = $(this).data('idclie');
            var estatus = $(this).data('estatus');
            var desdePublicidad = $(this).data('desde_publicidad');
            // Client data for the post-session form
            var clienteNombre = $(this).data('nombre') || '';
            var clienteEmail = $(this).data('email') || '';
            var clienteTelefono = $(this).data('telefono') || '';
            var clienteCountryCode = $(this).data('country_code') || '';
            var clienteWeddingDate = $(this).data('wedding_date') || '';
            var clienteWeddingLocation = $(this).data('wedding_location') || '';
            var clienteVendedor = $(this).data('vendedor') || '';
            var clienteHowDidYouMeet = String($(this).data('how_did_you_meet') || '');
            var clienteHearAboutUs = String($(this).data('hear_about_us') || '');
            var clienteHowLongKnownUs = String($(this).data('how_long_known_us') || '');
            var clienteTipoCliente = String($(this).data('tipo_cliente') !== undefined && $(this).data('tipo_cliente') !== null ? $(this).data('tipo_cliente') : '');

            console.log("Estatus ", estatus);
            let buttons = "";

            switch (estatus) {
                case 0:
                    buttons = `
                <button class="opcion-btn swal2-confirm swal2-styled" data-opcion="1">Atendida</button>
                <button class="opcion-btn swal2-confirm swal2-styled" data-opcion="3">Muerto</button>
                <button class="opcion-btn swal2-confirm swal2-styled btn-cancel" data-opcion="4">Cancelar</button>
            `;
                    break;
                case 1:
                    buttons = `
                <button class="opcion-btn swal2-confirm swal2-styled" data-opcion="0">Pendiente</button>
                <button class="opcion-btn swal2-confirm swal2-styled" data-opcion="2">Cliente</button>
                <button class="opcion-btn swal2-confirm swal2-styled" data-opcion="3">Muerto</button>
                <button class="opcion-btn swal2-confirm swal2-styled btn-cancel" data-opcion="4">Cancelar</button>
            `;
                    break;
                case 2:
                    buttons = `
                <button class="opcion-btn swal2-confirm swal2-styled" data-opcion="1">Atendida</button>
                <button class="opcion-btn swal2-confirm swal2-styled" data-opcion="3">Muerto</button>
                <button class="opcion-btn swal2-confirm swal2-styled btn-cancel" data-opcion="4">Cancelar</button>
            `;
                    break;
                case 3:
                    buttons = `
                <button class="opcion-btn swal2-confirm swal2-styled" data-opcion="0">Pendiente</button>
                <button class="opcion-btn swal2-confirm swal2-styled btn-cancel" data-opcion="4">Cancelar</button>
            `;
                    break;
                default:
                    'No hay estatus para mostrar';
            }

            // Mostrar el modal SweetAlert
            Swal.fire({
                title: 'Elige una opción',
                html: buttons,
                showConfirmButton: false,
                showCancelButton: false
            });

            $('.opcion-btn').click(function () {
                var opcion = $(this).data('opcion');


                if (opcion === 4) { // Si la opción es "Cancelar"
                    Swal.close(); // Cierra el modal de SweetAlert
                    return; // Sale de la función para evitar ejecutar selEstatus
                }
                selEstatus(opcion, id);
            });

            function selEstatus(estatus, id) {
                let comentario = "";
                if (estatus == 1 || estatus == 3 || estatus == 0) {


                    if (estatus == 1) {
                        // Estatus 1 = Atendida → mostrar formulario post-sesión
                        Swal.close();

                        // Guardar contexto para el submit del modal
                        window._sfmContext = {
                            id: id,
                            idusu: idusu,
                            idclie: idclie,
                            estatus: estatus,
                            countryCode: clienteCountryCode
                        };

                        // Poblar cabecera del modal
                        $('#sfm-client-name').text(clienteNombre || '—');
                        $('#sfm-location').text('📍 ' + (clienteWeddingLocation || '—'));
                        var wDateDisplay = clienteWeddingDate ? clienteWeddingDate.substring(0, 10) : '—';
                        $('#sfm-wedding-date-display').text('📅 ' + wDateDisplay);
                        $('#sfm-lead-id').text(idclie || id || '—');
                        $('#sfm-vendedor-name').text(clienteVendedor || '—');

                        // Pre-llenar campos editables
                        $('#sfm-nombre').val(clienteNombre);
                        $('#sfm-email').val(clienteEmail);
                        $('#sfm-telefono').val((clienteCountryCode ? clienteCountryCode + ' ' : '') + clienteTelefono);
                        $('#sfm-wedding-location').val(clienteWeddingLocation);
                        $('#sfm-wedding-date').val(clienteWeddingDate ? clienteWeddingDate.substring(0, 10) : '');

                        // Limpiar selecciones previas y errores de validación
                        $('.sfm-package-item, .sfm-engagement-btn, .sfm-known-us-btn, .sfm-tipo-cliente-btn').removeClass('active');
                        $('#sfm-how-long-known-us').val('');
                        $('#sfm-how-did-you-meet').val('');
                        $('#sfm-comentarios').val('');
                        $('.sfm-input-error').removeClass('sfm-input-error');
                        $('.sfm-group-error').removeClass('sfm-group-error');
                        $('.sfm-error-msg').remove();

                        // Pre-seleccionar ¿Desde cuánto nos conoces? si ya está guardado; si no, marcar el primero por defecto
                        if (clienteHowLongKnownUs !== '') {
                            var $knownBtn = $('.sfm-known-us-btn[data-val="' + clienteHowLongKnownUs + '"]');
                            $knownBtn.addClass('active');
                            $('#sfm-how-long-known-us').val(clienteHowLongKnownUs);
                        } else {
                            var $firstKnownBtn = $('.sfm-known-us-btn').first();
                            $firstKnownBtn.addClass('active');
                            $('#sfm-how-long-known-us').val($firstKnownBtn.data('val'));
                        }

                        // Pre-seleccionar tipo de cliente si ya está guardado
                        if (clienteTipoCliente !== '') {
                            $('.sfm-tipo-cliente-btn[data-tipo="' + clienteTipoCliente + '"]').addClass('active');
                            $('#sfm-tipo-cliente').val(clienteTipoCliente);
                        }

                        // Auto-derivar origen según tipo_cliente + how_long_known_us
                        updateSfmAutoOrigin();

                        // Construir lista de paquetes
                        var pkgHtml = '';
                        if (typeof paquetesData !== 'undefined' && paquetesData.length > 0) {
                            paquetesData.forEach(function(paq, idx) {
                                pkgHtml += '<div class="sfm-package-item" data-paq-id="' + paq.id + '">' +
                                    '<div class="sfm-package-left">' +
                                    '<div class="sfm-package-name">' + $('<span>').text(paq.nombre).html() + '</div>' +
                                    '<div class="sfm-package-label">Paquete ' + (idx + 1) + '</div>' +
                                    '</div><div class="sfm-package-dot"></div></div>';
                            });
                        } else {
                            pkgHtml = '<p style="color:#888;font-size:0.85rem;">No hay paquetes disponibles.</p>';
                        }
                        $('#sfm-package-list').html(pkgHtml);

                        // Mostrar overlay
                        $('#sfSessionOverlay').fadeIn(200);

                    } else if (estatus == 3) {
                        // Estatus 3 = Muerto → pedir motivo de rechazo
                        var motivosRechazo = {
                            'A': 'A) al Cliente no le alcanza su presupuesto',
                            'B': 'B) el cliente busca otro estilo o producto',
                            'C': 'C) no tenemos disponibilidad de fecha',
                            'D': 'D) otra razón ( especificar contexto cual)',
                            'E': 'E) el cliente no respondió la encuesta'
                        };

                        Swal.fire({
                            title: "Comentar motivo de rechazo",
                            input: 'select',
                            inputOptions: motivosRechazo,
                            inputPlaceholder: 'Seleccione un motivo de rechazo',
                            showCancelButton: true,
                            confirmButtonText: 'Enviar',
                            cancelButtonText: 'Cancelar',
                            didOpen: function () {
                                var select = Swal.getInput();
                                var container = select.parentElement;
                                var otro = document.createElement('textarea');
                                otro.id = 'swal-motivo-otro';
                                otro.className = 'swal2-textarea';
                                otro.placeholder = 'Especifique el contexto...';
                                otro.style.display = 'none';
                                otro.style.marginTop = '10px';
                                container.appendChild(otro);

                                select.addEventListener('change', function () {
                                    otro.style.display = this.value === 'D' ? 'block' : 'none';
                                    if (this.value !== 'D') {
                                        otro.value = '';
                                    }
                                });
                            },
                            preConfirm: function () {
                                var key = Swal.getInput().value;
                                if (!key) {
                                    Swal.showValidationMessage('Seleccione un motivo de rechazo');
                                    return false;
                                }
                                var comentarioMotivo = motivosRechazo[key];
                                if (key === 'D') {
                                    var detalle = document.getElementById('swal-motivo-otro').value.trim();
                                    if (!detalle) {
                                        Swal.showValidationMessage('Especifique el contexto para otra razón');
                                        return false;
                                    }
                                    comentarioMotivo += ': ' + detalle;
                                }
                                return comentarioMotivo;
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                comentario = result.value;

                                $.ajax({
                                    url: 'cambiar-estatus.php',
                                    type: 'POST',
                                    data: {
                                        id: id,
                                        estatus: estatus,
                                        comentario: comentario,
                                        idusu: idusu,
                                        idclie: idclie
                                    },
                                    success: function (response) {
                                        var res = parseAjaxJson(response);
                                        if (res.success) {
                                            Swal.fire('Cambio realizado!', res.message, 'success').then(() => { location.reload(); });
                                        } else {
                                            Swal.fire('Error!', res.message, 'error');
                                        }
                                    },
                                    error: function () {
                                        Swal.fire('Error!', 'Hubo un problema al realizar la solicitud.', 'error');
                                    }
                                });
                            }
                        });

                    } else {
                        // Si estatus es 0 (sin comentario), ejecutar AJAX directamente
                        $.ajax({
                            url: 'cambiar-estatus.php', // Ruta al archivo PHP
                            type: 'POST',
                            data: {
                                id: id,  // Se debe pasar el id correspondiente
                                estatus: estatus,  // Enviar el estatus elegido (1 o 3)
                                comentario: comentario,
                                idusu: idusu,
                                idclie: idclie
                            },
                            success: function (response) {
                                // Convertir la respuesta a JSON
                                var res = parseAjaxJson(response);
                                if (res.success) {
                                    // Si la respuesta es positiva, mostrar un mensaje de éxito
                                    Swal.fire(
                                        'Cambio realizado!',
                                        res.message,
                                        'success'
                                    ).then(() => {
                                        // Recargar la página después de un segundo (opcional)
                                        location.reload();
                                    });
                                } else {
                                    // Si hay un error, mostrar un mensaje de error
                                    Swal.fire(
                                        'Error!',
                                        res.message,
                                        'error'
                                    );
                                }
                            },
                            error: function () {
                                Swal.fire(
                                    'Error!',
                                    'Hubo un problema al realizar la solicitud.',
                                    'error'
                                );
                            }
                        });
                    }
                } else if (estatus == 2) {
                    //pasar a cliente
                    Swal.fire({
                        title: 'Comentario para pasar a cliente',
                        input: 'textarea',
                        inputPlaceholder: 'Escribe tu comentario aquí...',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, pasarlo a Cliente',
                        cancelButtonText: 'Cancelar',
                        preConfirm: (value) => {
                            if (!value || !value.trim()) {
                                Swal.showValidationMessage('Debes agregar un comentario para pasarlo a cliente');
                                return false;
                            }
                            return value.trim();
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            var comentarioCliente = result.value;

                            $.ajax({
                                url: 'cambiar-estatus.php',
                                type: 'POST',
                                data: {
                                    id: idclie,
                                    idcal: id,
                                    estatus: 4,
                                    idusu: idusu,
                                    comentario_a_cliente: comentarioCliente
                                },
                                success: function (response) {
                                    console.log("response ", response)
                                    var res = parseAjaxJson(response);
                                    if (res.success) {
                                        Swal.fire(
                                            'Cambio realizado!',
                                            res.message,
                                            'success'
                                        ).then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        Swal.fire(
                                            'Error!',
                                            res.message,
                                            'error'
                                        );
                                    }
                                },
                                error: function () {
                                    Swal.fire(
                                        'Error!',
                                        'Hubo un problema al realizar la solicitud.',
                                        'error'
                                    );
                                }
                            });
                        } else {
                            console.log("Operación cancelada. Estatus sigue igual.");
                        }
                    });
                } else {
                    //cerrar el modal
                }
            }
        });

        // ===== POST-SESSION FORM MODAL HANDLERS =====

        // Auto-derivar how_did_you_meet según tipo_cliente + how_long_known_us
        function updateSfmAutoOrigin() {
            var tipoCliente = String($('#sfm-tipo-cliente').val() || '');
            var howLong     = String($('#sfm-how-long-known-us').val() || '');
            var origin = '';
            if (tipoCliente === '1') {
                origin = '1'; // Wedding Planner
            } else if (tipoCliente === '0') {
                if (howLong === 'Less than 6 months') {
                    origin = '3'; // New Audience
                } else if (howLong === 'More than 6 months') {
                    origin = '2'; // Community
                }
            }
            $('#sfm-how-did-you-meet').val(origin);
        }

        function toggleSfmSubcategoria() { /* no-op: origin section removed */ }

        // Package selection (event delegation for dynamically added items)
        $(document).on('click', '.sfm-package-item', function() {
            $('.sfm-package-item').removeClass('active');
            $(this).addClass('active');
            $('#sfm-package-list').removeClass('sfm-group-error');
            $('#sfm-package-list').next('.sfm-error-msg').remove();
        });

        // ¿Desde cuánto nos conoces? button toggle
        $(document).on('click', '.sfm-known-us-btn', function() {
            $('.sfm-known-us-btn').removeClass('active');
            $(this).addClass('active');
            $('#sfm-how-long-known-us').val($(this).data('val'));
            $('#sfm-known-us-grid').removeClass('sfm-group-error');
            $('#sfm-known-us-grid').next('.sfm-error-msg').remove();
            updateSfmAutoOrigin();
        });

        // Tipo de cliente button toggle
        $(document).on('click', '.sfm-tipo-cliente-btn', function() {
            $('.sfm-tipo-cliente-btn').removeClass('active');
            $(this).addClass('active');
            $('#sfm-tipo-cliente').val(String($(this).data('tipo')));
            updateSfmAutoOrigin();
        });

        // Engagement button toggle
        $(document).on('click', '.sfm-engagement-btn', function() {
            $('.sfm-engagement-btn').removeClass('active');
            $(this).addClass('active');
            $('.sfm-engagement-btns').removeClass('sfm-group-error');
            $('.sfm-engagement-btns').next('.sfm-error-msg').remove();
        });

        // Clear error on input change
        $(document).on('input change', '#sfm-nombre, #sfm-email, #sfm-telefono, #sfm-wedding-location, #sfm-wedding-date, #sfm-subcategoria, #sfm-comentarios', function() {
            $(this).removeClass('sfm-input-error');
            $(this).next('.sfm-error-msg').remove();
        });

        // Cancel button - close modal
        $(document).on('click', '#sfm-cancel-btn', function() {
            $('#sfSessionOverlay').fadeOut(150);
        });

        // Click outside panel to close
        $(document).on('click', '#sfSessionOverlay', function(e) {
            if ($(e.target).is('#sfSessionOverlay')) {
                $('#sfSessionOverlay').fadeOut(150);
            }
        });

        // Submit button - collect data and console.log
        $(document).on('click', '#sfm-submit-btn', function() {
            var ctx = window._sfmContext || {};

            function parsePhoneValue(phoneValue, fallbackCountryCode) {
                var rawPhone = (phoneValue || '').trim();
                var rawCountryCode = (fallbackCountryCode || '').trim();
                var match = rawPhone.match(/^(\+\d+)\s*(.*)$/);

                if (match) {
                    return {
                        countryCode: match[1].trim(),
                        phone: (match[2] || '').trim()
                    };
                }

                return {
                    countryCode: rawCountryCode,
                    phone: rawPhone
                };
            }

            function parseAjaxJson(response) {
                if (typeof response === 'object') {
                    return response;
                }

                try {
                    return JSON.parse(response);
                } catch (error) {
                    var firstJson = String(response || '').match(/(\{[\s\S]*\})/);
                    if (firstJson) {
                        return JSON.parse(firstJson[1]);
                    }
                    throw error;
                }
            }

            // ===== VALIDATION =====
            var hasError = false;

            // Clear previous errors
            $('.sfm-input-error').removeClass('sfm-input-error');
            $('.sfm-group-error').removeClass('sfm-group-error');
            $('.sfm-error-msg').remove();

            // Helper: mark input error
            function markError($el, msg) {
                $el.addClass('sfm-input-error');
                if (msg) $el.after('<span class="sfm-error-msg">' + msg + '</span>');
                hasError = true;
            }

            // Helper: mark group error (button containers)
            function markGroupError($container, msg) {
                $container.addClass('sfm-group-error');
                if (msg) $container.after('<span class="sfm-error-msg">' + msg + '</span>');
                hasError = true;
            }

            // Section 1
            if (!$('#sfm-nombre').val().trim())       markError($('#sfm-nombre'), 'Campo obligatorio');
            if (!$('#sfm-email').val().trim())         markError($('#sfm-email'), 'Campo obligatorio');
            if (!$('#sfm-telefono').val().trim())      markError($('#sfm-telefono'), 'Campo obligatorio');
            if (!$('#sfm-wedding-location').val().trim()) markError($('#sfm-wedding-location'), 'Campo obligatorio');
            if (!$('#sfm-wedding-date').val())         markError($('#sfm-wedding-date'), 'Campo obligatorio');

            // Section 3: package
            if (!$('.sfm-package-item.active').length) {
                markGroupError($('#sfm-package-list'), 'Selecciona un paquete');
            }

            // Section 4: engagement
            if (!$('.sfm-engagement-btn.active').length) {
                markGroupError($('.sfm-engagement-btns'), 'Selecciona el nivel de engagement');
            }

            // Section 3: ¿Desde cuánto nos conoces?
            if (!$('#sfm-how-long-known-us').val()) {
                markGroupError($('#sfm-known-us-grid'), 'Selecciona una opción');
            }

            // Section 5: comments
            if (!$('#sfm-comentarios').val().trim()) {
                markError($('#sfm-comentarios'), 'Agrega comentarios de la sesión');
            }

            if (hasError) {
                // Scroll to first error inside the modal body
                var $firstError = $('#sfSessionBody .sfm-input-error, #sfSessionBody .sfm-group-error').first();
                if ($firstError.length) {
                    $('#sfSessionBody').animate({ scrollTop: $firstError.closest('.sfm-section').position().top + $('#sfSessionBody').scrollTop() - 20 }, 350);
                }
                return;
            }
            // ===== END VALIDATION =====

            // Section 1: Client data
            var selectedCategoria = String($('#sfm-how-did-you-meet').val() || '');
            var subcategoriaToSend = 0;
            var phoneData = parsePhoneValue($('#sfm-telefono').val(), ctx.countryCode || '');

            var formData = {
                seccion1_datos_cliente: {
                    nombre: $('#sfm-nombre').val().trim(),
                    email: $('#sfm-email').val().trim(),
                    telefono: phoneData.phone,
                    country_code: phoneData.countryCode,
                    lugar_boda: $('#sfm-wedding-location').val().trim(),
                    fecha_boda: $('#sfm-wedding-date').val()
                },
                // Section 2: Origin
                seccion2_origen: {
                    categoria: $('.sfm-category-btn.active').data('cat') || null,
                    subcategoria: subcategoriaToSend
                },
                // Section 3: Package
                seccion3_paquete: {
                    id_paquete: $('.sfm-package-item.active').data('paq-id') || null,
                    nombre_paquete: $('.sfm-package-item.active .sfm-package-name').text() || null
                },
                // Section 4: Engagement
                seccion4_engagement: $('.sfm-engagement-btn.active').data('eng') || null,
                // Section how_long: ¿Desde cuánto nos conoces?
                seccion_how_long: $('#sfm-how-long-known-us').val() || '',
                // Section tipo_cliente
                seccion_tipo_cliente: $('#sfm-tipo-cliente').val(),
                // Section 5: Comments
                seccion5_comentarios: $('#sfm-comentarios').val().trim(),
                // Context
                id_cita: ctx.id,
                idusu: ctx.idusu,
                idclie: ctx.idclie
            };

            console.log('=== Resumen Post-Sesión ===', formData);

            // Deshabilitar botón mientras se guarda
            $('#sfm-submit-btn').prop('disabled', true).text('Guardando…');

            // 1) Guardar campos en contact_form
            $.ajax({
                url: 'actualizar_datos_cliente.php',
                type: 'POST',
                data: {
                    idclie: ctx.idclie,
                    updatedData: {
                        nombre: formData.seccion1_datos_cliente.nombre,
                        email: formData.seccion1_datos_cliente.email,
                        telefono: formData.seccion1_datos_cliente.telefono,
                        country_code: formData.seccion1_datos_cliente.country_code,
                        wedding_location: formData.seccion1_datos_cliente.lugar_boda,
                        wedding_date: formData.seccion1_datos_cliente.fecha_boda,
                        how_did_you_meet: formData.seccion2_origen.categoria,
                        hear_about_us: formData.seccion2_origen.subcategoria,
                        paquete: formData.seccion3_paquete.id_paquete,
                        engagement: formData.seccion4_engagement,
                        how_long_known_us: formData.seccion_how_long,
                        tipo_cliente: formData.seccion_tipo_cliente
                    }
                },
                success: function(res1) {
                    var r1;

                    try {
                        r1 = parseAjaxJson(res1);
                    } catch (error) {
                        $('#sfm-submit-btn').prop('disabled', false).text('Guardar resumen de sesión');
                        Swal.fire('Error', 'La respuesta al guardar los datos del cliente no fue válida.', 'error');
                        return;
                    }

                    if (!r1 || !r1.success) {
                        $('#sfm-submit-btn').prop('disabled', false).text('Guardar resumen de sesión');
                        Swal.fire('Error', (r1 && r1.message) ? r1.message : 'No se pudieron guardar los datos del cliente.', 'error');
                        return;
                    }

                    // 2) Cambiar estatus de la cita a Atendida + guardar comentario
                    var catLabels = {1: 'Wedding Planner', 2: 'Community', 3: 'New Audience'};
                    var subLabels = {1: 'Meta Ads — Instagram / Facebook', 2: 'SEO — Google', 3: 'Colaboración / Influencer / Famoso', 4: 'Publicación / Prensa / Revista', 5: 'Otro'};
                    var engLabels = {1: 'Bajo', 2: 'Medio', 3: 'Alto'};
                    $.ajax({
                        url: 'cambiar-estatus.php',
                        type: 'POST',
                        data: {
                            id: ctx.id,
                            estatus: 1,
                            comentario: formData.seccion5_comentarios,
                            idusu: ctx.idusu,
                            idclie: ctx.idclie,
                            // Post-session summary fields
                            sfm_nombre: formData.seccion1_datos_cliente.nombre,
                            sfm_email: formData.seccion1_datos_cliente.email,
                            sfm_telefono: ((formData.seccion1_datos_cliente.country_code ? formData.seccion1_datos_cliente.country_code + ' ' : '') + formData.seccion1_datos_cliente.telefono).trim(),
                            sfm_wedding_location: formData.seccion1_datos_cliente.lugar_boda,
                            sfm_wedding_date: formData.seccion1_datos_cliente.fecha_boda,
                            sfm_how_did_you_meet: formData.seccion2_origen.categoria || '',
                            sfm_how_did_you_meet_label: catLabels[formData.seccion2_origen.categoria] || '',
                            sfm_hear_about_us: formData.seccion2_origen.subcategoria || '',
                            sfm_hear_about_us_label: subLabels[formData.seccion2_origen.subcategoria] || '',
                            sfm_paquete_id: formData.seccion3_paquete.id_paquete || '',
                            sfm_paquete_nombre: formData.seccion3_paquete.nombre_paquete || '',
                            sfm_engagement: formData.seccion4_engagement || '',
                            sfm_engagement_label: engLabels[formData.seccion4_engagement] || ''
                        },
                        success: function(res2) {
                            var r2;
                            try {
                                r2 = parseAjaxJson(res2);
                            } catch (error) {
                                $('#sfm-submit-btn').prop('disabled', false).text('Guardar resumen de sesión');
                                Swal.fire('Error', 'La respuesta al cambiar el estatus no fue válida.', 'error');
                                return;
                            }
                            $('#sfSessionOverlay').fadeOut(150);
                            if (r2.success) {
                                Swal.fire('¡Guardado!', 'Resumen de sesión guardado correctamente.', 'success').then(() => { location.reload(); });
                            } else {
                                Swal.fire('Aviso', 'Datos del cliente guardados, pero: ' + (r2.message || 'error al cambiar estatus.'), 'warning').then(() => { location.reload(); });
                            }
                        },
                        error: function() {
                            $('#sfm-submit-btn').prop('disabled', false).text('Guardar resumen de sesión');
                            Swal.fire('Error', 'No se pudo cambiar el estatus de la cita.', 'error');
                        },
                        complete: function() {
                            $('#sfm-submit-btn').prop('disabled', false).text('Guardar resumen de sesión');
                        }
                    });
                },
                error: function() {
                    $('#sfm-submit-btn').prop('disabled', false).text('Guardar resumen de sesión');
                    Swal.fire('Error', 'No se pudieron guardar los datos del cliente.', 'error');
                }
            });
        });

        // ===== END POST-SESSION FORM MODAL HANDLERS =====

        // Delegación del evento click para los botones "Ver más info"
        $('#pendingAppointmentsTable, #attendedAppointmentsTable, #noResponseAppointmentsTable').on('click', '.btn-vermas', function () {
            var desde_publicidad = $(this).data('desde_publicidad');
            var cliente_campaign_name = $(this).data('cliente_campaign_name');
            console.log('campaign_name:', cliente_campaign_name);
            var cliente_form_name = $(this).data('cliente_form_name');
            console.log('desde_publicidad:', desde_publicidad);
            var id = $(this).data('id');
            var idusu = $(this).data('idusu');
            var idclie = $(this).data('idclie');
            var enlace_meet = $(this).data('enlace');
            var country_code = $(this).data('country_code');

            var nombre = $(this).data('nombre');
            var telefono = $(this).data('telefono');
            var email = $(this).data('email');
            var fecha = $(this).data('fecha');
            var hora = $(this).data('hora');
            var fecha_cliente = $(this).data('fecha_cliente') || '';
            var hora_cliente = $(this).data('hora_cliente') || '';
            var appointment_utc = $(this).data('appointment_utc') || '';
            var timezone_name = $(this).data('timezone_name') || '';
            var timezone_offset_minutes = $(this).data('timezone_offset_minutes') || '';
            var asesor = $(this).data('asesor');
            var nota = $(this).data('nota');
            var titulo = $(this).data('titulo');
            var cliente_names = $(this).data('cliente_names');
            var cliente_telefono = $(this).data('cliente_telefono');
            var cliente_email_address = $(this).data('email');
            var cliente_additional_details = $(this).data('cliente_additional_details');
            var cliente_couple_activities = $(this).data('cliente_couple_activities');
            var cliente_date_appointment = $(this).data('cliente_date_appointment');
            var cliente_favorite_movie_or_song = $(this).data('cliente_favorite_movie_or_song');
            var cliente_guests_count = $(this).data('cliente_guests_count');
            var cliente_hear_about_us = $(this).data('cliente_hear_about_us');
            var cliente_how_did_you_meet = $(this).data('cliente_how_did_you_meet');
            var cliente_instagram_handle = $(this).data('cliente_instagram_handle');
            var cliente_look_preference = $(this).data('cliente_look_preference');
            var cliente_service_interest = $(this).data('cliente_service_interest');
            var cliente_submission_date = $(this).data('cliente_submission_date');
            var cliente_time_appointment = $(this).data('cliente_time_appointment');
            var cliente_wedding_date = $(this).data('cliente_wedding_date');
            var cliente_wedding_location = $(this).data('cliente_wedding_location');
            var cliente_wedding_planner = $(this).data('cliente_wedding_planner');
            var desde_publicidad = $(this).data('desde_publicidad');
            var vendedor_asignado_nombre = $(this).data('vendedor_asignado_nombre');
            var vendedor_asignado_apepat = $(this).data('vendedor_asignado_apepat');
            var vendedor_asignado_apemat = $(this).data('vendedor_asignado_apemat');
            var comentario_desde_wp = $(this).data('comentario_desde_wp');
            var table_origen = $(this).data('table_origen');
            // Obtener de forma segura la fila original del DataTable (incluye filas responsive child)
            var $btn = $(this);
            var $table = $btn.closest('table');
            var $tr = $btn.closest('tr');
            if ($tr.hasClass('child')) {
                $tr = $tr.prev();
            }

            var rowData = null;
            if ($table.length && $.fn.DataTable.isDataTable($table)) {
                var dt = $table.DataTable();
                rowData = dt.row($tr).data() || null;
            }

            if (!table_origen && rowData) table_origen = rowData.table_origen;
            if ((!comentario_desde_wp || comentario_desde_wp.toString().trim() === '') && rowData) comentario_desde_wp = rowData.comentario_desde_wp || '';
            if (!appointment_utc && rowData) appointment_utc = rowData.appointment_utc || '';
            if (!timezone_name && rowData) timezone_name = rowData.timezone_name || '';
            if ((timezone_offset_minutes === '' || timezone_offset_minutes === null || timezone_offset_minutes === undefined) && rowData) {
                timezone_offset_minutes = rowData.timezone_offset_minutes || '';
            }

            // Respuestas guardadas desde sfSessionBody
            var cliente_paquete_id = $(this).data('cliente_paquete_id') || (rowData ? rowData.cliente_paquete_id : '');
            var cliente_paquete_nombre = $(this).data('cliente_paquete_nombre') || (rowData ? rowData.cliente_paquete_nombre : '');
            var cliente_engagement = $(this).data('cliente_engagement') || (rowData ? rowData.cliente_engagement : '');

            var catLabels = { '1': 'Wedding Planner', '2': 'Community', '3': 'New Audience' };
            var subLabels = {
                '1': 'Meta Ads — Instagram / Facebook',
                '2': 'SEO — Google',
                '3': 'Colaboración / Influencer / Famoso',
                '4': 'Publicación / Prensa / Revista',
                '5': 'Otro'
            };
            var engLabels = { '1': 'Bajo', '2': 'Medio', '3': 'Alto' };
            function safeValue(val) {
                return (val === undefined || val === null || (val + '').trim() === '') ? 'Sin dato' : val;
            }
            function esc(val) {
                return $('<div>').text(safeValue(val)).html();
            }

            const modalAppointmentData = {
                fecha: fecha,
                hora: hora,
                fecha_cliente: fecha_cliente,
                hora_cliente: hora_cliente,
                appointment_utc: appointment_utc,
                timezone_name: timezone_name,
                timezone_offset_minutes: timezone_offset_minutes,
                zona_horaria: rowData ? rowData.zona_horaria : 0,
                table_origen: table_origen
            };
            var formattedClientAppointment = renderClientAppointmentCell(modalAppointmentData, 'display', false);
            var formattedSellerAppointment = renderSellerAppointmentCell(modalAppointmentData, 'display');
            let telefonoCliente = (!cliente_telefono || cliente_telefono.toString().trim() === '') ? 'Sin dato' : ((country_code ? country_code + ' ' : '') + cliente_telefono);
            var displayEmail = (cliente_email_address && cliente_email_address.toString().trim() !== '') ? cliente_email_address : 'Sin dato';

            var htmlContent = '<div class="vmm-session-body">';

            // Si viene de wedding_planners, ocultar fecha/hora reales y mostrar Sin dato
            if (table_origen === 'wedding_planners') {
                formattedClientAppointment = 'Sin dato';
                formattedSellerAppointment = 'Sin dato';
                // Mostrar vendedor asignado si existe
                if (vendedor_asignado_nombre) {
                    asesor = vendedor_asignado_nombre + ' ' + (vendedor_asignado_apepat || '') + ' ' + (vendedor_asignado_apemat || '');
                } else {
                    asesor = 'Sin asesor asignado';
                }
            }

            // Sección 1: Datos del cliente
            htmlContent += '<div class="sfm-section">';
            htmlContent += '<div class="sfm-section-title"><div class="sfm-section-number">1</div><div class="sfm-section-heading"><h5>Datos del cliente</h5><p>Información de contacto registrada.</p></div></div>';
            htmlContent += '<div class="sfm-grid-2">';
            htmlContent += '<div class="sfm-field"><label>Nombre del cliente</label><div class="sfm-readonly-input">' + esc(cliente_names) + '</div></div>';
            htmlContent += '<div class="sfm-field"><label>Teléfono</label><div class="sfm-readonly-input">' + esc(telefonoCliente) + '</div></div>';
            htmlContent += '<div class="sfm-field"><label>Correo</label><div class="sfm-readonly-input">' + esc(displayEmail) + '</div></div>';
            htmlContent += '</div></div>';

            // Sección 2: Datos de ingreso
            var ingresoDesde = 'N/A';
            var ingresoCampania = '';
            var ingresoFormulario = '';
            if (table_origen === 'wedding_planners') {
                ingresoDesde = 'WP';
            } else if (cliente_campaign_name && cliente_campaign_name.trim() !== '') {
                ingresoDesde = 'Campaña';
                ingresoCampania = cliente_campaign_name;
                ingresoFormulario = cliente_form_name;
            }
            htmlContent += '<div class="sfm-section">';
            htmlContent += '<div class="sfm-section-title"><div class="sfm-section-number">2</div><div class="sfm-section-heading"><h5>Datos de ingreso</h5><p>Origen y canal de captación del lead.</p></div></div>';
            htmlContent += '<div class="sfm-grid-2">';
            htmlContent += '<div class="sfm-field"><label>Desde</label><div class="sfm-readonly-input">' + esc(ingresoDesde) + '</div></div>';
            htmlContent += '<div class="sfm-field"><label>Nombre de la campaña</label><div class="sfm-readonly-input">' + esc(ingresoCampania || 'Sin dato') + '</div></div>';
            htmlContent += '<div class="sfm-field"><label>Nombre del formulario</label><div class="sfm-readonly-input">' + esc(ingresoFormulario || 'Sin dato') + '</div></div>';
            htmlContent += '</div></div>';

            // Sección 3: Datos de la cita
            htmlContent += '<div class="sfm-section">';
            htmlContent += '<div class="sfm-section-title"><div class="sfm-section-number">3</div><div class="sfm-section-heading"><h5>Datos de la cita</h5><p>Estado y detalles operativos de la sesión.</p></div></div>';
            htmlContent += '<div class="sfm-grid-2">';
            htmlContent += '<div class="sfm-field"><label>Hora de la cita (Cliente)</label><div class="sfm-readonly-input">' + formattedClientAppointment + '</div></div>';
            htmlContent += '<div class="sfm-field"><label>Hora de la cita (Vendedora)</label><div class="sfm-readonly-input">' + formattedSellerAppointment + '</div></div>';
            htmlContent += '<div class="sfm-field"><label>Asesor asignado</label><div class="sfm-readonly-input">' + esc(asesor) + '</div></div>';
            htmlContent += '<div class="sfm-field"><label>Enlace de llamada</label><div class="sfm-readonly-input">' + (enlace_meet ? '<a href="' + $('<div>').text(enlace_meet).html() + '" target="_blank">' + $('<div>').text(enlace_meet).html() + '</a>' : 'Sin dato') + '</div></div>';
            htmlContent += '</div>';
            htmlContent += '<div class="sfm-field" style="margin-top:12px;"><label>Nota</label><div class="sfm-readonly-input">' + esc(nota) + '</div></div>';
            if (tipoUsuario == '0') {
                htmlContent += '<div class="sfm-readonly-actions"><div class="btn btn-primary btn-reagendar" data-idclie="' + idclie + '" data-id="' + id + '" data-idusu="' + idusu + '" data-fecha="' + fecha + '" data-hora="' + hora + '" data-fecha_cliente="' + fecha_cliente + '" data-hora_cliente="' + hora_cliente + '">Reagendar</div></div>';
            }
            htmlContent += '</div>';

            // Sección 4: Resumen post-sesión (idéntica en estilo de secciones sfSessionBody)
            var selectedCat = String(cliente_how_did_you_meet || '');
            var selectedEng = String(cliente_engagement || '');
            if (selectedEng !== '0' && selectedEng !== '') {
                htmlContent += '<div class="sfm-section">';
                htmlContent += '<div class="sfm-section-title"><div class="sfm-section-number">4</div><div class="sfm-section-heading"><h5>Resumen post-sesión</h5><p>Información capturada en el cierre de la llamada.</p></div></div>';
                htmlContent += '<div class="sfm-grid-2">';
                htmlContent += '<div class="sfm-field"><label>Lugar de la boda</label><div class="sfm-readonly-input">' + esc(cliente_wedding_location) + '</div></div>';
                htmlContent += '<div class="sfm-field"><label>Fecha de la boda</label><div class="sfm-readonly-input">' + esc(cliente_wedding_date) + '</div></div>';
                htmlContent += '<div class="sfm-field"><label>Paquete de interés</label><div class="sfm-readonly-input">' + esc(cliente_paquete_nombre || cliente_paquete_id) + '</div></div>';
                htmlContent += '<div class="sfm-field"><label>Subcategoría origen</label><div class="sfm-readonly-input">' + esc(subLabels[String(cliente_hear_about_us)] || cliente_hear_about_us) + '</div></div>';
                htmlContent += '</div>';
                htmlContent += '<div class="sfm-field" style="margin-top:12px;">';
                htmlContent += '<label>Categoría origen</label>';
                if (selectedCat === '1') {
                    htmlContent += '<div class="sfm-static-grid sfm-category-btns"><div class="sfm-category-btn active"><span class="sfm-cat-icon">💼</span>Wedding Planner</div></div>';
                } else if (selectedCat === '2') {
                    htmlContent += '<div class="sfm-static-grid sfm-category-btns"><div class="sfm-category-btn active"><span class="sfm-cat-icon">🤝</span>Community</div></div>';
                } else if (selectedCat === '3') {
                    htmlContent += '<div class="sfm-static-grid sfm-category-btns"><div class="sfm-category-btn active"><span class="sfm-cat-icon">🚀</span>New Audience</div></div>';
                } else {
                    htmlContent += '<div class="sfm-readonly-input">Sin dato</div>';
                }
                htmlContent += '</div>';
                htmlContent += '<div class="sfm-field" style="margin-top:12px;">';
                htmlContent += '<label>Engagement</label>';
                if (selectedEng === '1') {
                    htmlContent += '<div class="sfm-static-grid sfm-engagement-btns"><div class="sfm-engagement-btn active"><span class="sfm-eng-icon">😑</span><span class="sfm-eng-name">Bajo</span><div class="sfm-eng-desc">Poco entusiasmo, muchas dudas o precio</div></div></div>';
                } else if (selectedEng === '2') {
                    htmlContent += '<div class="sfm-static-grid sfm-engagement-btns"><div class="sfm-engagement-btn active"><span class="sfm-eng-icon">😃</span><span class="sfm-eng-name">Medio</span><div class="sfm-eng-desc">Interés real, pero falta de decisión</div></div></div>';
                } else if (selectedEng === '3') {
                    htmlContent += '<div class="sfm-static-grid sfm-engagement-btns"><div class="sfm-engagement-btn active"><span class="sfm-eng-icon">🔥</span><span class="sfm-eng-name">Alto</span><div class="sfm-eng-desc">Muy interesados, listos para cerrar</div></div></div>';
                } else {
                    htmlContent += '<div class="sfm-readonly-input">Sin dato</div>';
                }
                htmlContent += '</div>';
                htmlContent += '<div class="sfm-field" style="margin-top:12px;"><label>Comentarios de sesión</label><div class="sfm-readonly-input">' + esc(nota) + '</div></div>';
                htmlContent += '</div>';
            }

            if (comentario_desde_wp && comentario_desde_wp.toString().trim() !== '') {
                var wpCommentSectionNumber = (selectedEng !== '0' && selectedEng !== '') ? '5' : '4';
                htmlContent += '<div class="sfm-section">';
                htmlContent += '<div class="sfm-section-title"><div class="sfm-section-number">' + wpCommentSectionNumber + '</div><div class="sfm-section-heading"><h5>Comentario desde Wedding Planner</h5><p>Comentario capturado cuando el evento se marcó como atendido.</p></div></div>';
                htmlContent += '<div class="sfm-field"><div class="sfm-readonly-input">' + esc(comentario_desde_wp) + '</div></div>';
                htmlContent += '</div>';
            }

            htmlContent += '</div>';

            $('#vmm-client-name').text(cliente_names || '—');
            $('#vmm-location').text('📍 ' + (cliente_wedding_location || '—'));
            $('#vmm-wedding-date-display').text('📅 ' + (cliente_wedding_date || '—'));
            $('#vmm-lead-id').text(idclie || id || '—');
            $('#vmm-vendedor-name').text(asesor || '—');

            $('#modalContent').html(htmlContent);
            $('#verMasModal').modal('show');

        });


        $('#pendingAppointmentsTable, #attendedAppointmentsTable, #noResponseAppointmentsTable').on('click', '.btn-enviar-encuesta', function () {

            var idclie = $(this).data('idclie');

            Swal.fire({
                title: '¿Estás seguro?',
                text: '¿Qué versión quieres enviar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Español',
                cancelButtonText: 'Cancelar',
                showDenyButton: true,
                denyButtonText: 'Inglés',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#aaa',
                denyButtonColor: '#3085d6', // Puedes cambiar el color del botón de cancelar si lo deseas
            }).then((result) => {
                if (result.isConfirmed) {
                    // Español
                    Swal.fire({
                        title: 'Enviando...',
                        text: 'Por favor espere mientras procesamos su solicitud.',
                        allowOutsideClick: false, // Prevenir que el usuario cierre el alerta
                        didOpen: () => {
                            Swal.showLoading(); // Mostrar el spinner de carga
                        }
                    });

                    // Realizar la petición AJAX
                    $.ajax({
                        url: 'enviar_cuestionario.php',
                        type: 'POST',
                        data: { id_cliente: idclie, id: 1 },
                        success: function (res) {
                            console.log(res);
                            let response = JSON.parse(res);
                            Swal.close(); // Cerrar la carga
                            if (response.status === 'success') {
                                Swal.fire('¡Enviado!', 'Tu cuestionario ha sido enviado.', 'success');
                            } else {
                                Swal.fire('¡Error!', 'Hubo un problema al enviar el cuestionario.', 'error');
                            }
                        },
                        error: function () {
                            Swal.fire('¡Error!', 'No se pudo conectar al servidor.', 'error');
                        }
                    });
                } else if (result.isDenied) {
                    Swal.fire({
                        title: 'Sending...',
                        text: 'Please wait while we process your request.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading(); // Show loading spinner
                        }
                    });

                    // AJAX call here for English action if needed, similar to the one above
                    $.ajax({
                        url: 'enviar_cuestionario.php',
                        type: 'POST',
                        data: { id_cliente: idclie, id: 2 },
                        success: function (res) {
                            console.log(res);
                            let response = JSON.parse(res);
                            Swal.close(); // Close the loading swal
                            if (response.status === 'success') {
                                Swal.fire('Sent!', 'Your questionnaire has been sent.', 'success');
                            } else {
                                Swal.fire('Error!', 'There was a problem sending the questionnaire.', 'error');
                            }
                        },
                        error: function () {
                            Swal.fire('Error!', 'Could not connect to the server.', 'error');
                        }
                    });
                } else if (result.isDismissed) {
                    // Inglés


                }
            });




        });

        $(document).on('click', '.btn-reagendar, #btn-reagendar', function () {
            var id = $(this).data('id'); // id del meet
            var idusu = $(this).data('idusu') || 0; // Si es undefined, null o vacío, se asigna 0
            // id de usuario
            var idclie = $(this).data('idclie'); // id de cliente
            var fecha = $(this).data('fecha');
            var hora = $(this).data('hora');
            var fecha_cliente = $(this).data('fecha_cliente') || '';
            var hora_cliente = $(this).data('hora_cliente') || '';

            reagendaCurrentFecha = fecha || '';
            reagendaCurrentHora = hora || '';

            if (idusu == 0 || idusu == "0") {
                // cambiar el título
                $('#reagendarModal').text('Asignar nuevo vendedor');
                $('#confirmar-reagendar').text('Asignar');
                if (fecha && hora) {
                    $('#last_datetime').text('Fecha y hora anterior: ' + moment(fecha + ' ' + hora).format('DD/MM/YYYY hh:mm A'));
                }
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
                        console.log("result ", result)
                        if (result.isConfirmed) {
                            const loadingSwal = Swal.fire({
                                title: 'Reagendando...',
                                text: 'Por favor espere mientras procesamos su solicitud.',
                                allowOutsideClick: false, // Prevenir que el usuario cierre el alerta
                                didOpen: () => {
                                    Swal.showLoading(); // Mostrar el spinner de carga
                                }
                            });
                            // Si el usuario confirma, hacer la solicitud AJAX
                            console.log("idusu ", idusu)
                            $.ajax({
                                url: 'enviar-correo-reagenda.php',
                                method: 'POST',
                                data: {
                                    id: id,
                                    idclie: idclie,
                                    idusu: idusu
                                },
                                success: function (response) {
                                    console.log(response)
                                    // Parsear la respuesta JSON
                                    var res = parseAjaxJson(response);
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
            const usuarios = <?php echo json_encode($usuarios); ?>;

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
            $('#show_inputs').show();

            // Limpiar campos ocultos anteriores para evitar duplicados
            $('#reagendaId, #customerId, #hasSeller').remove();

            // Llenar campos considerando que en la BD:
            // - calendario.fecha + hora = VENDEDOR
            // - calendario.fecha_cliente + hora_cliente = CLIENTE

            // Hora del cliente (campo principal #datetime_appointment)
            if (fecha_cliente && hora_cliente) {
                var horaClienteFormateada = hora_cliente.substring(0, 5); // HH:MM
                var datetimeCliente = fecha_cliente + 'T' + horaClienteFormateada;
                $('#datetime_appointment').val(datetimeCliente);
            } else if (fecha && hora) {
                var horaFallbackCliente = hora.substring(0, 5);
                $('#datetime_appointment').val(fecha + 'T' + horaFallbackCliente);
            }

            $('#reagenda-modal').append('<input type="hidden" name="reagendaId" id="reagendaId" value="' + id + '">');
            $('#reagenda-modal').append('<input type="hidden" name="customerId" id="customerId" value="' + idclie + '">');
            $('#reagenda-modal').append('<input type="hidden" name="hasSeller" id="hasSeller" value="' + idusu + '">');

            initializeReagendaVendorSchedule(idusu, fecha || '', (hora || '').substring(0, 5));

            // Cerrar el modal de "Ver más"
            $('#verMasModal').modal('hide');

            // Mostrar el modal de "Reagendar"
            $('#modal-reagendar').modal('show');
        });






    });






</script>
</body>

</html>