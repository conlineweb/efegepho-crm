<?php
include 'conn.php';



// Verificar si 'id' está presente en la URL
$tabla_origen = '';
$mostrarFormulario = true;
$mostrarMensaje = false;
$lead_data = null;
$marketingEmailClickId = isset($_GET['marketing_email_click_id']) ? intval($_GET['marketing_email_click_id']) : 0;
$marketingTemplateId = isset($_GET['marketing_template_id']) ? intval($_GET['marketing_template_id']) : 0;


if (isset($_GET['id'])) {
    $id = $_GET['id'];  // Obtener el valor de 'id' desde la URL



} else {
    $mostrarFormulario = false;  // Mostrar el formulario completo
    $mostrarMensaje = false;
}

if (isset($_GET['tabla_origen'])) {
    $tabla_origen = $_GET['tabla_origen'];
}


//veririficar que la tabla exista
$checkTable = $conn->query("SHOW TABLES LIKE '$tabla_origen'");
if ($checkTable->num_rows === 0) {
    $mostrarFormulario = false;  // Mostrar el formulario completo
    exit;
}

//obtener los datos de la tabla
$sql = "SELECT * FROM $tabla_origen WHERE id = $id";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $lead_data = $result->fetch_assoc();
} else {
    $mostrarFormulario = false;  // Mostrar el formulario completo
    exit;
}








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

$queryBloqueoDiasEventos = "SELECT * FROM dias_bloqueados_eventos";
$resultBloqueoDiasEventos = mysqli_query($conn, $queryBloqueoDiasEventos);

// Verificar si la consulta tuvo resultados
if ($resultBloqueoDiasEventos) {
    $dias_bloqueados_eventos = [];
    while ($row = mysqli_fetch_assoc($resultBloqueoDiasEventos)) {
        // Guardar todo el registro (todos los campos)
        $dias_bloqueados_eventos[] = $row['fecha'];
    }

    // Devolver los datos en formato JSON

}

// Vendedoras disponibles para asignación (solo tipoUsu = 1)
$vendedoras = [];
$resVendedoras = $conn->query("SELECT id, nombre, apepat, enlace_meet FROM usuarios WHERE tipoUsu = 1 ORDER BY nombre, apepat");
if ($resVendedoras && $resVendedoras->num_rows > 0) {
    while ($rowV = $resVendedoras->fetch_assoc()) {
        $vendedoras[] = $rowV;
    }
}

$vendedoras_js = [];
foreach ($vendedoras as $v) {
    $vId = intval($v['id'] ?? 0);
    if ($vId <= 0) {
        continue;
    }
    $vNombre = trim(($v['nombre'] ?? '') . ' ' . ($v['apepat'] ?? ''));
    $vendedoras_js[$vId] = [
        'nombre' => $vNombre !== '' ? $vNombre : ('Vendedora #' . $vId),
        'enlace_meet' => trim((string)($v['enlace_meet'] ?? '')),
    ];
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Inquire Form - Registro por Vendedora</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

</head>
<style>
    /* Wrapper to keep form nicely centered and constrained */
    .form-wrapper {
        max-width: 1100px;
        margin: 0 auto;
        padding: 0 16px;
    }

    #wedding_date {
        width: 100% !important;
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: 'Cormorant Garamond', serif;
        background: none;

        margin: 0;
    }


    select {
        padding: 10px;
        border: none;
        border-bottom: 1px solid #ccc;
        font-size: 18px;
        outline: none;
        background: none;
        font-family: 'Cormorant Garamond', serif;
        appearance: none;
        /* Elimina el estilo por defecto del select */
        -webkit-appearance: none;
        /* Compatibilidad con navegadores WebKit */
        -moz-appearance: none;
        /* Compatibilidad con navegadores Mozilla */
        color: #000;
        /* Color del texto */
    }

    select:focus {
        border-bottom: 1px solid #000;
        /* Cambia el color del borde al enfocar */
    }

    /* Para que el placeholder se muestre al inicio */
    select option[value=""][disabled] {
        display: none;
    }

    .form-label {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 6px;
        display: block;
        /* Forzar que el label esté en su propia línea */
    }

    .country_code {
        width: 15%;
    }

    form {

        font-family: 'Cormorant Garamond', serif;
    }

    input,
    textarea,
    button {

        border: none;
        border-bottom: 1px solid #ccc;
        font-size: 18px;
        outline: none;
        background: none;
        font-family: 'Cormorant Garamond', serif;
    }

    /* Asegurar que inputs y selects ocupen el ancho y queden debajo del label */
    input,
    select,
    textarea {
        width: 100%;
        display: block;
        margin-bottom: 6px;
    }

    /* Focus polish for inputs/selects */
    input:focus,
    select:focus,
    textarea:focus {
        border-bottom-color: #000;
    }

    /* Softer placeholder */
    input::placeholder,
    textarea::placeholder {
        color: #9a9a9a;
    }

    button {
        background-color: none;
        color: #000;
        font-size: 20px;
        cursor: pointer;
        border-radius: 5px;
    }

    button:hover {
        background-color: none;
        font-size: 22px;
    }

    /* Estilo específico para el botón NEXT */
    .btn-submit {
        background-color: transparent;
        color: #000;
        font-size: 20px;
        cursor: pointer;
        border: none;
        border-bottom: 1px solid #ccc;
        padding: 10px 20px;
        font-family: 'Cormorant Garamond', serif;
        width: auto;
        min-width: 200px;
        transition: all 0.3s ease;
    }

    .btn-submit:hover {
        font-size: 22px;
        border-bottom: 1px solid #000;
    }



    @media (max-width: 600px) {

        input::placeholder,
        textarea::placeholder {

            font-size: 12px;
            /* Tamaño de la fuente */
        }

        .wedding_date_label {

            width: 300px !important;
            margin: 25px;
            font-size: 12px !important;
            /* Tamaño de la fuente */
        }

        .wedding_date {

            width: 50% !important;


            font-size: 12px !important;
            /* Tamaño de la fuente */
        }

        select {
            padding: 10px;
            border: none;
            border-bottom: 1px solid #ccc;
            font-size: 12px !important;
            /* Tamaño de la fuente */
            outline: none;
            background: none;
            font-family: 'Cormorant Garamond', serif;
            appearance: none;
            /* Elimina el estilo por defecto del select */
            -webkit-appearance: none;
            /* Compatibilidad con navegadores WebKit */
            -moz-appearance: none;
            /* Compatibilidad con navegadores Mozilla */
            color: #909090;
            /* Color del texto */
        }

    }

    .calendar-container {
        display: flex;
        width: 100%;
        max-width: 800px;
        /* Ancho máximo para pantallas grandes */
        margin: 20px auto;
        border-radius: 5px;
        overflow: hidden;
        /* Para que el borde redondeado se aplique correctamente */
        min-height: 400px;
        /* Altura mínima para evitar saltos */
    }

    .left-panel {
        width: 60%;
        /* Ajusta la proporción según tus necesidades */
        background-color: #fff;

    }

    .right-panel {
        width: 40%;
        /* Ajusta la proporción según tus necesidades */
        background-color: #f9f9f9;

        border-left: 1px solid #eee;
        max-height: 500px;
        /* Altura máxima para evitar que se expanda demasiado */
        overflow-y: auto;
        /* Scroll vertical si es necesario */
    }

    /* Encabezado del calendario */
    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .calendar-header h2 {
        font-size: 24px;
        font-weight: 600;
        color: #333;
    }

    .calendar-header button {
        background-color: transparent;
        border: none;
        font-size: 16px;
        cursor: pointer;
        color: #555;
        transition: color 0.3s ease;
    }

    .calendar-header button:hover {
        color: #333;
    }

    /* Tabla del calendario */
    table {
        width: 100%;
        border-collapse: collapse;
        height: -webkit-fill-available;
    }

    th,
    td {
        padding: 12px;
        text-align: center;
        border: 1px solid #eee;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    th {
        font-weight: 600;
        color: #555;
    }

    td:hover {
        background-color: #f0f0f0;
    }

    /* Día actual */
    td.current-day {
        background-color: #e0f2f7;

    }

    td.selected {
        background-color: #edd896;

    }

    /* Contenedor de horarios */
    #schedule-container {
        padding: 20px;

        /* Altura máxima para el contenedor de horarios */
        overflow-y: auto;
        /* Scroll si hay muchos horarios */
    }

    #schedule-container h3 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 10px;
        position: sticky;
        /* Para que el título se mantenga visible */
        top: 0;
        background-color: #f9f9f9;
        z-index: 1;
    }

    #schedule-list {
        list-style: none;
        padding: 0;
        font-size: 1.1rem;
        max-height: 300px;
        /* Altura máxima para la lista */
        overflow-y: auto;
        /* Scroll si hay muchos horarios */
    }

    #schedule-list li {
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }

    /* Estilos para días deshabilitados */
    td.disabled {
        color: #ccc;
        cursor: default;
        pointer-events: none;
    }

    /* Estilos para horarios */
    #schedule-list li {
        padding: 10px;
        border-bottom: 1px solid #eee;
        display: flex;
        /* Para alinear hora e icono */
        align-items: center;
        /* Alinear verticalmente */
        gap: 10px;
        /* Espacio entre hora e icono */
    }

    #schedule-list li span {
        /* Estilo para la hora */
        font-size: 16px;
        color: #555;
    }

    /* Icono para los horarios (usando Font Awesome) */
    #schedule-list li::before {
        font-family: "Font Awesome 5 Free";
        content: "\f017";
        /* Icono de reloj */
        font-weight: 900;
        /* Para que se vea el icono */
    }

    /* Opcional: Hover effect en los horarios */
    #schedule-list li:hover {
        background-color: #f0f0f0;
        cursor: pointer;
    }

    /* Estilos para horarios seleccionados */
    #schedule-list li.selected {
        background-color: #e0f2f7;
        /* Color de fondo claro */
        color: #007bff;
        /* Color de texto azul */
        font-weight: bold;
        /* Texto en negrita (opcional) */
    }



    td.blocked {
        cursor: default;
        pointer-events: none;
        background-color: #bbbbbb !important;
        color: white;
    }

    /* Media queries para pantallas pequeñas */

    @media (max-width: 768px) {
        .calendar-container {
            flex-direction: column;
            width: 100%;
            margin: 10px 0;
            min-height: auto;
            /* Remover altura mínima en móvil */
        }

        .left-panel,
        .right-panel {
            width: 100%;

            max-height: none;
            /* Remover altura máxima en móvil */
        }

        .right-panel {
            border-left: none;
            border-top: 1px solid #eee;
        }

        #schedule-container {
            max-height: 300px;
            margin-top: 20px;
            /* Altura máxima reducida para móvil */
        }

        #schedule-list {
            max-height: 200px;
            /* Altura máxima reducida para móvil */
        }

        /* Encabezado */
        .calendar-header h2 {
            font-size: 20px;
        }

        .calendar-header button {
            font-size: 14px;
        }

        /* Tabla del calendario */
        th,
        td {
            padding: 10px;
            font-size: 14px;
        }

        td:hover {
            background-color: #f0f0f0;
        }

        td.current-day {
            background-color: #e0f2f7;
        }

        td.selected {
            background-color: #edd896;
        }

        td.disabled {
            color: #ccc;
            cursor: default;
            pointer-events: none;
        }

        /* Ajustes para el contenedor de horarios */
        #schedule-container {
            padding: 15px;
        }

        #schedule-list li {
            padding: 8px 0;
        }

        #schedule-list li span {
            font-size: 14px;
        }

        /* Ajustar iconos de Font Awesome */
        #schedule-list li::before {
            font-size: 16px;
        }

        /* Botón NEXT en móvil */
        .btn-submit {
            font-size: 18px;
            width: 100%;
            max-width: 300px;
        }

        .btn-submit:hover {
            font-size: 20px;
        }
    }

    /* Media queries para pantallas extra pequeñas (móviles en modo retrato) */
    @media (max-width: 480px) {
        .calendar-header h2 {
            font-size: 18px;
        }

        .calendar-header button {
            font-size: 12px;
        }

        th,
        td {
            padding: 8px;
            font-size: 19px;
        }

        #schedule-container {
            padding: 10px;
        }

        #schedule-list li {
            font-size: 19px;
            padding: 6px 0;
        }

        #schedule-list li span {
            font-size: 12px;
        }

        /* Redimensionar iconos */
        #schedule-list li::before {
            font-size: 14px;
        }
    }

    /* Extra spacing fix for SweetAlert in small viewports */
    @media (max-width: 768px) {
        .swal2-popup {
            margin-bottom: 200px !important;
        }
    }

    /* Country code select should fill its column */
    #country_code {
        width: 100%;
        max-width: 100%;
    }

    /* iOS select polish and smooth scrolling */
    @supports (-webkit-touch-callout: none) {
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            padding-right: 30px;
            /* Espacio para la flecha */
            background-image: url('data:image/svg+xml;utf8,<svg fill="black" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
            background-repeat: no-repeat;
            background-position: right center;
        }

        .calendar-container {
            -webkit-overflow-scrolling: touch;
        }
    }


    .error-message {
        color: red;
        font-size: 12px;
        margin-top: 5px;
        display: none;
    }

    /* Banner vendedora */
    .vendedora-banner {
        background: linear-gradient(135deg, #8a4a0f 0%, #c47020 100%);
        color: #fff;
        padding: 12px 20px;
        border-radius: 6px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 15px;
        font-family: 'Cormorant Garamond', serif;
    }
    .vendedora-banner i {
        font-size: 20px;
    }
    /* Campo hora cliente vendedora */
    .hora-cliente-vendedora {
        margin-top: 24px;
        padding: 16px 20px;
        background: #fffaf4;
        border: 1px solid #e0c898;
        border-radius: 6px;
    }
    .hora-cliente-vendedora label.form-label {
        font-size: 0.85rem;
        color: #7a5210;
        font-weight: 600;
        margin-bottom: 6px;
    }
    .hora-cliente-vendedora .hint-text {
        font-size: 0.75rem;
        color: #999;
        margin-top: 4px;
    }
    .hora-cliente-vendedora input[type="text"] {
        max-width: 180px;
        border: 1px solid #c8a050;
        border-radius: 4px;
        padding: 6px 10px;
        font-size: 17px;
    }
    .hora-cliente-vendedora input.is-invalid {
        border-color: #dc3545 !important;
    }

    /* Selector de vendedora */
    .vendedora-select-section {
        max-width: 480px;
        margin: 0 auto 24px;
        padding: 0 8px;
    }

    .vendedora-select-section .form-label {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 6px;
        display: block;
    }

    .calendar-section-wrapper {
        position: relative;
    }

    .calendar-placeholder,
    .vendor-no-availability {
        text-align: center;
        padding: 40px 20px;
        color: #666;
        font-size: 1.1rem;
        background: #fafafa;
        border: 1px dashed #ddd;
        border-radius: 6px;
        margin: 0 auto;
        max-width: 900px;
    }

    .vendor-no-availability i {
        display: block;
        font-size: 2rem;
        color: #c47020;
        margin-bottom: 12px;
    }

    .calendar-loading-overlay {
        display: none;
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, 0.85);
        z-index: 10;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 12px;
        font-size: 1rem;
        color: #555;
    }

    .calendar-loading-overlay.is-visible {
        display: flex;
    }

    .calendar-loading-spinner {
        width: 36px;
        height: 36px;
        border: 3px solid #e0c898;
        border-top-color: #8a4a0f;
        border-radius: 50%;
        animation: vendor-spin 0.8s linear infinite;
    }

    @keyframes vendor-spin {
        to { transform: rotate(360deg); }
    }

    .schedule-loading {
        text-align: center;
        padding: 24px;
        color: #666;
    }

    .schedule-loading .calendar-loading-spinner {
        margin: 0 auto 10px;
    }

    .meet-link-box {
        margin-top: 16px;
        padding: 14px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        text-align: left;
    }

    .meet-link-box label {
        display: block;
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 8px;
        font-weight: 600;
    }

    .meet-link-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: stretch;
    }

    .meet-link-url {
        flex: 1 1 200px;
        min-width: 0;
        padding: 10px 12px;
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 0.9rem;
        word-break: break-all;
        color: #0d6efd;
    }

    .btn-copy-meet {
        flex: 0 0 auto;
        padding: 10px 16px;
        background: #8a4a0f;
        color: #fff;
        border: none;
        border-radius: 4px;
        font-size: 0.9rem;
        cursor: pointer;
        white-space: nowrap;
        font-family: 'Cormorant Garamond', serif;
        transition: background 0.2s;
    }

    .btn-copy-meet:hover {
        background: #6d3a0c;
    }

    .copy-toast {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%) translateY(80px);
        background: #333;
        color: #fff;
        padding: 12px 24px;
        border-radius: 6px;
        font-size: 0.95rem;
        z-index: 10000;
        opacity: 0;
        transition: transform 0.3s ease, opacity 0.3s ease;
        pointer-events: none;
    }

    .copy-toast.is-visible {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }

    .vendor-confirm-popup .swal2-html-container {
        overflow: visible;
    }

    .vendor-confirm-tabs {
        margin-bottom: 12px;
    }

    .vendor-confirm-tabs .nav-link {
        font-size: 0.95rem;
        color: #555;
    }

    .vendor-confirm-tabs .nav-link.active {
        color: #8a4a0f;
        font-weight: 600;
    }

    .vendor-confirm-preview {
        white-space: pre-wrap;
        text-align: left;
        font-size: 0.92rem;
        line-height: 1.65;
        background: #fafafa;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 14px;
        max-height: 340px;
        overflow-y: auto;
        color: #333;
    }

    .vendor-confirm-copy-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 14px;
    }

    .btn-copy-confirm-msg {
        flex: 1 1 220px;
        padding: 10px 14px;
        background: #8a4a0f;
        color: #fff;
        border: none;
        border-radius: 4px;
        font-size: 0.9rem;
        cursor: pointer;
        font-family: 'Cormorant Garamond', serif;
        transition: background 0.2s;
    }

    .btn-copy-confirm-msg:hover {
        background: #6d3a0c;
    }

    .btn-copy-confirm-msg.btn-copy-en {
        background: #5e543f;
    }

    .btn-copy-confirm-msg.btn-copy-en:hover {
        background: #4a4332;
    }
</style>

<body>

    <div class=" container-sm mt-4">
         <div class="d-flex mt-5">
       <a href="https://www.efegepho.com/"> <!-- Cambia el href al destino que desees -->
  <img src="https://cdn.prod.website-files.com/606278172c257c8e5ae0463c/62e1beaf3e61f6ff41a188e7_efege-bk.svg" alt="EFEGE" height="45">
</a>

    </div>
    <div class="vendedora-banner">
        <i class="fas fa-user-cog"></i>
        <span><strong>Vista para vendedora</strong> &mdash; Registra la cita en nombre del cliente. Ingresa la hora del cliente en su zona horaria en el campo correspondiente.</span>
    </div>
       <h1 class=" mt-5 text-center">INQUIRE</h1>
        <form id="weddingForm" class="form-wrapper">
            <input type="hidden" name="from_vendedor_form" value="1">
            <?php if (!empty($lead_data)):
                $ld = $lead_data;
                $pref_names = htmlspecialchars($ld['full_name'] ?? '', ENT_QUOTES);
                $pref_email = htmlspecialchars($ld['email'] ?? '', ENT_QUOTES);
                $pref_wedding_location = htmlspecialchars($ld['wedding_location'] ?? ($ld['where_is_your_marriage_taking_place_'] ?? ''), ENT_QUOTES);
                $pref_wedding_date = htmlspecialchars($ld['wedding_date'] ?? ($ld['when_are_you_getting_married?'] ?? ''), ENT_QUOTES);
                $pref_how_long_known_us = htmlspecialchars($ld['how_long_known_us'] ?? '', ENT_QUOTES);
                $metadata = json_encode([
                    'lead_id' => $ld['id'] ?? null,
                    'created_time' => $ld['created_time'] ?? null,
                    'ad_id' => $ld['ad_id'] ?? null,
                    'ad_name' => $ld['ad_name'] ?? null,
                    'adset_id' => $ld['adset_id'] ?? null,
                    'adset_name' => $ld['adset_name'] ?? null,
                    'campaign_id' => $ld['campaign_id'] ?? null,
                    'campaign_name' => $ld['campaign_name'] ?? null,
                    'form_id' => $ld['form_id'] ?? null,
                    'form_name' => $ld['form_name'] ?? null,
                    'platform' => $ld['platform'] ?? null,
                    'lead_status' => $ld['lead_status'] ?? null,
                    'services_interest' => $ld['services_you_are_interested_in_'] ?? null,
                    'where_is_marriage' => $ld['where_is_your_marriage_taking_place_'] ?? null
                ], JSON_UNESCAPED_UNICODE);
                $desde_publicidad = 1;
                ?>
                <input type="hidden" name="names" value="<?php echo $pref_names; ?>">
                <!-- visible email field added below; removed duplicate hidden email -->
                <input type="hidden" name="wedding_date" value="<?php echo $pref_wedding_date; ?>">
                <input type="hidden" name="form_name" value="<?php echo $tabla_origen; ?>">
                <input type="hidden" name="campaign_name" value="<?php echo $ld['campaign_name'] ?? ''; ?>">
                <input type="hidden" name="service" value="<?php echo $ld['services_you_are_interested_in_'] ?? ''; ?>">

                <input type="hidden" name="desde_publicidad" value="1">
                <input type="hidden" name="original_lead_id"
                    value="<?php echo htmlspecialchars($ld['id'] ?? '', ENT_QUOTES); ?>">

                <input type="hidden" name="wedding_location"
                    value="<?php echo $pref_wedding_location; ?>">
                <input type="hidden" name="first_contact_channel"
                    value="<?php echo htmlspecialchars($ld['first_contact_channel'] ?? '', ENT_QUOTES); ?>">

                <input type="hidden" name="tabla_origen" value='<?php echo $tabla_origen; ?>'>
                <input type="hidden" name="marketing_email_click_id" value="<?php echo $marketingEmailClickId > 0 ? intval($marketingEmailClickId) : ''; ?>">
                <input type="hidden" name="marketing_template_id" value="<?php echo $marketingTemplateId > 0 ? intval($marketingTemplateId) : ''; ?>">
            <?php endif; ?>
            <h3 class="text-center m-5"> For the wildly in love</h3>

            <!-- Row 1: Email and Confirm Email -->
            <div class="row mb-4">
                <div class="col-12 col-md-6 mb-4">
                    <div class="d-flex flex-column h-100 justify-content-end">
                        <label class="form-label">Email address <span style="color:#999; font-size:0.75rem;">(optional)</span></label>
                        <input type="email" name="email" placeholder="email address" value="<?php echo $pref_email ?? ''; ?>">
                    </div>
                </div>
                <div class="col-12 col-md-6 mb-4">
                    <div class="d-flex flex-column h-100 justify-content-end">
                        <label class="form-label">Confirm email address <span style="color:#999; font-size:0.75rem;">(optional)</span></label>
                        <input type="email" name="confirm_email" placeholder="confirm email address">
                        <div class="error-message" id="email-error">Los correos electrónicos no coinciden</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Event location + Date of Event -->
                <div class="col-12 col-md-6 mb-5">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-7">
                            <label class="form-label">Event location</label>
                            <input type="text" name="wedding_location" id="wedding_location" placeholder="Event location"
                                value="<?php echo $pref_wedding_location ?? ''; ?>" required>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label">Date of Event</label>
                            <input type="date" name="wedding_date" id="wedding_date" placeholder="Date of Event"
                                value="<?php echo $pref_wedding_date ?? ''; ?>" required>
                        </div>
                    </div>
                </div>
            </div> 
            <div class="row g-4">
                <!-- Country code + Telephone (fila completa) -->
                <div class="col-12">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-6">
                            <label class="form-label">Country code</label>
                            <select id="country_code" name="country_code">
                                <option value="" disabled selected>Select your country code</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label">Telephone</label>
                            <input type="tel" name="telephone" placeholder="telephone">
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-4 mb-5 mt-3">
                <div class="col-12">
                    <label class="form-label">City</label>
                    <input type="text" name="city" placeholder="Your city">
                </div>
            </div>
            <div class="row g-4 mb-5 align-items-end">
                <div class="col-12 col-md-6">
                    <label class="form-label">How long have you known us?</label>
                    <select name="how_long_known_us" required>
                        <option value="" disabled <?php echo (($pref_how_long_known_us ?? '') === '') ? 'selected' : ''; ?>>Select...</option>
                        <option value="Less than 6 months" <?php echo (($pref_how_long_known_us ?? '') === 'Less than 6 months') ? 'selected' : ''; ?>>Less than 6 months</option>
                        <option value="More than 6 months" <?php echo (($pref_how_long_known_us ?? '') === 'More than 6 months') ? 'selected' : ''; ?>>More than 6 months</option>
                    </select>
                </div>
            </div>
            <!-- removed: Where did you hear about us? (moved to metadata/source) -->




            <div style="width:100%">
                <h3 class="text-center m-5">Schedule your personalized event consultation</h3>

                <div class="vendedora-select-section">
                    <label class="form-label" for="vendedora_select">Seleccionar vendedora</label>
                    <select id="vendedora_select" name="vendedora_select" required>
                        <option value="" disabled selected>Selecciona una vendedora...</option>
                        <?php foreach ($vendedoras as $v): ?>
                            <?php
                            $vNombre = trim(($v['nombre'] ?? '') . ' ' . ($v['apepat'] ?? ''));
                            $vLabel = $vNombre !== '' ? $vNombre : ('Vendedora #' . intval($v['id']));
                            ?>
                            <option value="<?php echo intval($v['id']); ?>"><?php echo htmlspecialchars($vLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="calendar-placeholder" class="calendar-placeholder">
                    Selecciona una vendedora para ver los días y horarios disponibles.
                </div>

                <div id="vendor-no-availability" class="vendor-no-availability" style="display:none;">
                    <i class="fas fa-calendar-times"></i>
                    <p>La vendedora seleccionada no tiene horarios disponibles en las próximas dos semanas.</p>
                    <p style="font-size:0.95rem; color:#888;">Por favor elige otra vendedora o intenta más tarde.</p>
                </div>

                <div class="mt-1 mb-1 calendar-section-wrapper" id="meet" style="display:none;">
                    <div class="calendar-loading-overlay" id="calendar-loading">
                        <div class="calendar-loading-spinner"></div>
                        <span>Cargando disponibilidad...</span>
                    </div>
                    <div class="calendar-container">
                        <div class="left-panel">
                            <div class="calendar-header">
                                <button id="prev-month">Previous Month</button>
                                <h2 id="current-month"></h2>
                                <button id="next-month">Next Month</button>
                            </div>
                            <table id="calendar">
                                <thead>
                                    <tr>
                                        <th>Sun</th>
                                        <th>Mon</th>
                                        <th>Tue</th>
                                        <th>Wed</th>
                                        <th>Thu</th>
                                        <th>Fri</th>
                                        <th>Sat</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="right-panel">
                            <div id="schedule-container">
                                <h3>Schedule for <span id="selected-date"></span></h3>
                                <p class="text-center muted-note">Time Zone: US Central Time (UTC-6)</p>
                                <ul id="schedule-list"></ul>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div> <!-- Cierre de la sección de calendario -->

            <!-- Campo hora del vendedor (zona horaria diferente) - SOLO PARA VENDEDORA -->
            <div class="hora-cliente-vendedora">
                <label class="form-label" for="hora_cliente_manual">
                    <i class="fas fa-clock" style="color:#8a4a0f;"></i>
                    Tu hora de referencia (opcional)
                </label>
                <input
                    type="text"
                    id="hora_cliente_manual"
                    name="hora_cliente_manual"
                    placeholder="HH:MM (ej: 14:30)"
                    maxlength="5"
                    autocomplete="off"
                >
                <div class="hint-text">Solo si tú estás en otra zona horaria. La hora del schedule arriba es la hora del CLIENTE. Aquí puedes ingresar TU hora equivalente para referencia. Dejar en blanco si ambos están en la misma zona.</div>
                <div class="error-message" id="hora-cliente-error">Formato inválido. Use HH:MM (ej: 09:00 o 14:30).</div>
            </div>

            <!-- Contenedor para el botón NEXT con margen adecuado -->
            <div class="text-center mt-5 mb-5" style="padding: 20px;">
                <div class="mb-3" style="font-size:12px; text-align:center; max-width:800px; margin:0 auto; color:#666;">
                    <p>By continuing, you agree to the <a href="https://www.efegepho.com/terms-conditions-and-privacy-policy-provided" target="_blank" rel="noopener">Terms &amp; Conditions and Privacy Policy provided by Efegepho</a>. By providing your phone number, you consent to receive text messages and phone calls from Efegepho.</p>
                </div>
                <button type="submit" class="btn-submit" id="nextButton">NEXT</button>
            </div>
        </form>
    </div>

    <div id="copy-toast" class="copy-toast" role="status" aria-live="polite">Enlace copiado al portapapeles</div>

</body>

</html>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script src="admin/assets/extensions/@fortawesome/fontawesome-free/js/all.js" data-auto-replace-svg="nest"></script>
<script src="admin/assets/extensions/jquery/jquery.js"></script>
<script src="admin/assets/extensions/jquery-ui/jquery-ui.min.js"></script>

<script src='https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js'></script>
<script>
    let lead_data = <?php echo json_encode($lead_data); ?>;
    const vendedorasById = <?php echo json_encode($vendedoras_js, JSON_UNESCAPED_UNICODE); ?>;

    console.log("lead_data desde php", lead_data);





    const timezoneOffsetMinutes = new Date().getTimezoneOffset();
    const timezone = -timezoneOffsetMinutes / 60; // Convertir a horas con signo opuesto

    // Para pruebas: Forzar una zona horaria diferente (ej. UTC+1, comenta esta línea en producción)
    // Ejemplo: UTC+1 (Europa)

    console.log('Zona horaria detectada:', timezone);

    let vendorConfirmMessages = { es: '', en: '' };

    function showCopyToast(message) {
        const $toast = $('#copy-toast');
        $toast.text(message || 'Copiado al portapapeles');
        $toast.addClass('is-visible');
        setTimeout(function () {
            $toast.removeClass('is-visible');
        }, 2500);
    }

    function copyTextToClipboard(text) {
        if (!text) {
            return Promise.reject(new Error('empty'));
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                document.body.removeChild(textarea);
                resolve();
            } catch (err) {
                document.body.removeChild(textarea);
                reject(err);
            }
        });
    }

    function escapeHtmlText(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    function formatSessionTimeDisplay(hora) {
        const raw = (hora == null ? '' : String(hora)).trim();
        if (!raw) {
            return 'N/A';
        }
        const fmt = raw.length === 5 ? 'HH:mm' : 'HH:mm:ss';
        const parsed = moment(raw, fmt, true);
        return parsed.isValid() ? parsed.format('hh:mm A') : raw;
    }

    function formatSessionDateSpanish(dateStr) {
        const raw = (dateStr == null ? '' : String(dateStr)).trim();
        if (!raw) {
            return 'N/A';
        }
        const parsed = moment(raw, ['YYYY-MM-DD', 'DD-MM-YYYY'], true);
        if (!parsed.isValid()) {
            return raw;
        }
        const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return parsed.date() + ' de ' + meses[parsed.month()] + ' de ' + parsed.year();
    }

    function formatSessionDateEnglish(dateStr) {
        const raw = (dateStr == null ? '' : String(dateStr)).trim();
        if (!raw) {
            return 'N/A';
        }
        const parsed = moment(raw, ['YYYY-MM-DD', 'DD-MM-YYYY'], true);
        return parsed.isValid() ? parsed.format('MMMM D, YYYY') : raw;
    }

    function buildVendorConfirmMessageEs(data) {
        return (
            '✅ Tu sesión se ha agendado con éxito\n\n' +
            'Nombre del cliente: ' + data.nombreCliente + '\n' +
            'Día de la sesión: ' + data.fechaEs + '\n' +
            'Hora de la sesión: ' + data.hora + '\n' +
            'Asesora asignada: ' + data.vendedor + '\n\n' +
            'Este es el enlace de tu videollamada:\n\n' +
            data.meetLinkEs + '\n\n' +
            'Te esperamos en la fecha y hora programadas. ¡Será un placer atenderte!'
        );
    }

    function buildVendorConfirmMessageEn(data) {
        return (
            '✅ Your session has been successfully scheduled\n\n' +
            'Client Name: ' + data.nombreCliente + '\n' +
            'Session Date: ' + data.fechaEn + '\n' +
            'Session Time: ' + data.hora + '\n' +
            'Assigned Advisor: ' + data.vendedor + '\n\n' +
            'Here is the link for your video call:\n\n' +
            data.meetLinkEn + '\n\n' +
            'We look forward to meeting with you at the scheduled date and time. It will be our pleasure to assist you!'
        );
    }

    function copyVendorConfirmMessage(lang) {
        const text = vendorConfirmMessages[lang] || '';
        if (!text) {
            return;
        }
        copyTextToClipboard(text).then(function () {
            showCopyToast(lang === 'es' ? 'Mensaje en español copiado' : 'English message copied');
        }).catch(function () {
            showCopyToast('No se pudo copiar el mensaje');
        });
    }

    function showVendorAppointmentConfirmation(payload) {
        const meetLinkRaw = (payload.meetLink || '').trim();
        const messageData = {
            nombreCliente: payload.nombreCliente || 'N/A',
            vendedor: payload.vendedor || 'N/A',
            meetLinkEs: meetLinkRaw || 'No disponible',
            meetLinkEn: meetLinkRaw || 'Not available',
            fechaEs: formatSessionDateSpanish(payload.fecha),
            fechaEn: formatSessionDateEnglish(payload.fecha),
            hora: formatSessionTimeDisplay(payload.hora)
        };

        vendorConfirmMessages.es = buildVendorConfirmMessageEs(messageData);
        vendorConfirmMessages.en = buildVendorConfirmMessageEn(messageData);

        return Swal.fire({
            icon: 'success',
            title: 'Sesión agendada',
            width: '760px',
            html:
                '<ul class="nav nav-tabs vendor-confirm-tabs" id="vendorConfirmTabs" role="tablist">' +
                    '<li class="nav-item" role="presentation">' +
                        '<button class="nav-link active" id="vendor-confirm-es-tab" data-bs-toggle="tab" data-bs-target="#vendor-confirm-es" type="button" role="tab">Español</button>' +
                    '</li>' +
                    '<li class="nav-item" role="presentation">' +
                        '<button class="nav-link" id="vendor-confirm-en-tab" data-bs-toggle="tab" data-bs-target="#vendor-confirm-en" type="button" role="tab">English</button>' +
                    '</li>' +
                '</ul>' +
                '<div class="tab-content" id="vendorConfirmTabContent">' +
                    '<div class="tab-pane fade show active" id="vendor-confirm-es" role="tabpanel">' +
                        '<div class="vendor-confirm-preview">' + escapeHtmlText(vendorConfirmMessages.es) + '</div>' +
                        '<div class="vendor-confirm-copy-row">' +
                            '<button type="button" class="btn-copy-confirm-msg" id="copyVendorMsgEs">' +
                                '<i class="fas fa-copy"></i> Copiar mensaje (Español)' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="tab-pane fade" id="vendor-confirm-en" role="tabpanel">' +
                        '<div class="vendor-confirm-preview">' + escapeHtmlText(vendorConfirmMessages.en) + '</div>' +
                        '<div class="vendor-confirm-copy-row">' +
                            '<button type="button" class="btn-copy-confirm-msg btn-copy-en" id="copyVendorMsgEn">' +
                                '<i class="fas fa-copy"></i> Copy message (English)' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>',
            confirmButtonText: 'Cerrar',
            customClass: {
                popup: 'swal2-popup-center vendor-confirm-popup'
            },
            didOpen: function () {
                const btnEs = document.getElementById('copyVendorMsgEs');
                const btnEn = document.getElementById('copyVendorMsgEn');
                if (btnEs) {
                    btnEs.addEventListener('click', function () {
                        copyVendorConfirmMessage('es');
                    });
                }
                if (btnEn) {
                    btnEn.addEventListener('click', function () {
                        copyVendorConfirmMessage('en');
                    });
                }
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        // Real-time validation for email confirmation
        $('[name="confirm_email"]').on('input', function () {
            const email = ($('[name="email"]').val() || '').trim();
            const confirmEmail = ($(this).val() || '').trim();

            if (confirmEmail && email !== confirmEmail) {
                $(this).css("border", "1px solid red");
                $('#email-error').show();
            } else {
                $(this).css("border", "");
                $('#email-error').hide();
            }
        });

        // Also validate when the main email changes
        $('[name="email"]').on('input', function () {
            const email = ($(this).val() || '').trim();
            const confirmEmail = ($('[name="confirm_email"]').val() || '').trim();

            if (email && confirmEmail && email !== confirmEmail) {
                $('[name="confirm_email"]').css("border", "1px solid red");
                $('#email-error').show();
            } else {
                $('[name="confirm_email"]').css("border", "");
                $('#email-error').hide();
            }
        });

        // Versión mejorada con manejo de errores y caché
        fetch('admin/JS/countries_codes.json?' + new Date().getTime(), { // Evita caché
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            cache: 'no-store' // Indica que no queremos cachear
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Countries data loaded:', data);
                const selectElement = document.getElementById('country_code');

                // Reset del select
                selectElement.innerHTML = '<option value="" disabled selected>Select your country code</option>';

                // Estilos para iOS
                selectElement.style.webkitAppearance = 'none';
                selectElement.style.borderRadius = '0';

                if (data && data.countries) {
                    data.countries.sort((a, b) => a.name.localeCompare(b.name))
                        .forEach(country => {
                            const option = new Option(`${country.code} (${country.name})`, country.code);
                            selectElement.add(option);
                        });
                } else {
                    console.error('Invalid countries data structure');
                    // Cargar datos de respaldo si hay error
                    loadFallbackCountries();
                }
            })
            .catch(error => {
                console.error('Error loading countries:', error);
                // Cargar versión de respaldo
                loadFallbackCountries();
            });

        // Función de respaldo si falla el fetch
        function loadFallbackCountries() {
            const basicCountries = [
                { code: '+1', name: 'United States' },
                { code: '+44', name: 'United Kingdom' },
                // Añade más países importantes como respaldo
            ];

            const selectElement = document.getElementById('country_code');
            basicCountries.forEach(country => {
                const option = new Option(`${country.code} (${country.name})`, country.code);
                selectElement.add(option);
            });
        }




        const diasBloqueados = <?php echo json_encode($dias_bloqueados); ?>;
        const diasBloqueadosEventos = <?php echo json_encode($dias_bloqueados_eventos); ?>;


        console.log("diasBloqueados ", diasBloqueados)
        // Convertir las fechas bloqueadas a un set para hacer la búsqueda más eficiente
        let blockedDatesSet = new Set(diasBloqueadosEventos);

        // Función que verifica si una fecha está bloqueada
        function isBlocked(date) {
            return blockedDatesSet.has(date);
        }



        $("#wedding_date").on('change', function (e) {
            let selectedDate = e.target.value; // Fecha seleccionada por el usuario

            if (isBlocked(selectedDate)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Lo sentimos.<br>No hay disponibilidad en esa fecha.',
                    confirmButtonText: 'Aceptar',
                    customClass: {
                        popup: 'swal2-popup-center'
                    }
                });
                // Opcional: restablecer el valor del input si la fecha está bloqueada
                e.target.value = '';

            } else { }
        })


        var fechaHoy = new Date();
        fechaHoy.setHours(0, 0, 0, 0);
        let selectedHour = 0;


        //aqui empieza el codigo del form normal para agendar la cita

        const weddingForm = document.getElementById("weddingForm");
        if (weddingForm) {

            // Agregar validación de campos requeridos




            //  document.getElementById('wedding_date').setAttribute('min', new Date().toISOString().split('T')[0]);

            //aqui se muestra el calendario

            const $calendar = $('#calendar');
            const $currentMonth = $('#current-month');
            const $prevMonthButton = $('#prev-month');
            const $nextMonthButton = $('#next-month');
            const $scheduleContainer = $('#schedule-container');
            const $scheduleList = $('#schedule-list');
            const $selectedDate = $('#selected-date');
            const $vendedoraSelect = $('#vendedora_select');
            const $meetSection = $('#meet');
            const $calendarPlaceholder = $('#calendar-placeholder');
            const $vendorNoAvailability = $('#vendor-no-availability');
            const $calendarLoading = $('#calendar-loading');

            let currentDate = new Date();
            let selectedVendorId = '';
            let vendorAvailableDays = new Set();
            let vendorDaysLoading = false;

            function formatDateYMD(dateObj) {
                const y = dateObj.getFullYear();
                const m = String(dateObj.getMonth() + 1).padStart(2, '0');
                const d = String(dateObj.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            }

            function setCalendarLoading(isLoading) {
                if (isLoading) {
                    $calendarLoading.addClass('is-visible');
                } else {
                    $calendarLoading.removeClass('is-visible');
                }
            }

            function resetSchedulePanel() {
                $selectedDate.text('');
                $scheduleList.empty();
            }

            function updateCalendarVisibility() {
                if (!selectedVendorId) {
                    $calendarPlaceholder.show();
                    $vendorNoAvailability.hide();
                    $meetSection.hide();
                    return;
                }
                $calendarPlaceholder.hide();
                if (vendorAvailableDays.size === 0 && !vendorDaysLoading) {
                    $vendorNoAvailability.show();
                    $meetSection.hide();
                } else {
                    $vendorNoAvailability.hide();
                    $meetSection.show();
                }
            }

            async function vendorHasAvailableSlots(vendorId, dateStr) {
                if (diasBloqueados.includes(dateStr) || isBlocked(dateStr)) {
                    return false;
                }

                const formData = new FormData();
                formData.append('dia', dateStr);
                formData.append('idusu', vendorId);

                try {
                    const horariosResponse = await fetch('chkDisponible.php', { method: 'POST', body: formData });
                    const horariosJson = await horariosResponse.json();

                    if (!Array.isArray(horariosJson) || horariosJson.length === 0 || !horariosJson[0].horarios) {
                        return false;
                    }

                    const occupiedForm = new FormData();
                    occupiedForm.append('fecha', dateStr);
                    occupiedForm.append('idusu', vendorId);
                    const occupiedResponse = await fetch('chkDisponible.php', { method: 'POST', body: occupiedForm });
                    const occupiedTimes = await occupiedResponse.json();

                    const vendorHorarios = JSON.parse(horariosJson[0].horarios);
                    for (let i = 0; i < vendorHorarios.length; i++) {
                        const horario = vendorHorarios[i];
                        let isOccupied = false;
                        if (Array.isArray(occupiedTimes)) {
                            occupiedTimes.forEach(function (time) {
                                if (time.hora === horario + ':00') {
                                    isOccupied = true;
                                }
                            });
                        }
                        if (!isOccupied) {
                            return true;
                        }
                    }
                } catch (error) {
                    console.error('Error checking vendor availability for', dateStr, error);
                }
                return false;
            }

            async function loadVendorAvailableDays(vendorId) {
                vendorDaysLoading = true;
                vendorAvailableDays = new Set();
                setCalendarLoading(true);
                updateCalendarVisibility();

                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const maxDate = new Date(today);
                maxDate.setDate(today.getDate() + 14);

                const checks = [];
                for (let d = new Date(today); d <= maxDate; d.setDate(d.getDate() + 1)) {
                    const dateStr = formatDateYMD(d);
                    checks.push(
                        vendorHasAvailableSlots(vendorId, dateStr).then(function (hasSlots) {
                            if (hasSlots) {
                                vendorAvailableDays.add(dateStr);
                            }
                        })
                    );
                }

                await Promise.all(checks);

                vendorDaysLoading = false;
                setCalendarLoading(false);
                updateCalendarVisibility();

                if (vendorAvailableDays.size > 0) {
                    generateCalendar(currentDate.getMonth(), currentDate.getFullYear());
                } else {
                    resetSchedulePanel();
                }
            }

            function generateCalendar(month, year) {
                if (!selectedVendorId) {
                    return;
                }

                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                const daysInMonth = lastDay.getDate();
                const startDay = firstDay.getDay();

                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const minDate = new Date(today);
                const maxDate = new Date(today);
                maxDate.setDate(today.getDate() + 14);
                maxDate.setHours(23, 59, 59, 999);

                let date = 1;
                let html = '';

                for (let i = 0; i < 6; i++) {
                    html += '<tr>';
                    for (let j = 0; j < 7; j++) {
                        if (i === 0 && j < startDay) {
                            html += '<td></td>';
                        } else if (date > daysInMonth) {
                            html += '<td></td>';
                        } else {
                            const cellDate = new Date(year, month, date);
                            cellDate.setHours(0, 0, 0, 0);
                            const currentDateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
                            const isBeforeMinDate = cellDate < minDate;
                            const isAfterMaxDate = cellDate > maxDate;
                            const isBlockedDay = diasBloqueados.includes(currentDateStr);
                            const hasVendorAvailability = vendorAvailableDays.has(currentDateStr);
                            const blockedClass = isBlockedDay ? 'blocked' : '';
                            const disabledClass = isBeforeMinDate || isAfterMaxDate || !hasVendorAvailability ? 'disabled' : '';

                            html += `<td data-day="${date}" data-month="${month}" data-year="${year}" class="${disabledClass} ${blockedClass}">${date}</td>`;
                            date++;
                        }
                    }
                    html += '</tr>';
                }

                $calendar.find('tbody').html(html);
                $currentMonth.text(new Date(year, month).toLocaleDateString('en-US', { month: 'long', year: 'numeric' }));

                const $dayCells = $calendar.find('td[data-day]:not(.disabled):not(.blocked)');
                $dayCells.each(function () {
                    $(this).click(showSchedule);
                });

                if (month === today.getMonth() && year === today.getFullYear()) {
                    $calendar.find('td[data-day]').each(function () {
                        if (parseInt($(this).data('day'), 10) === today.getDate()) {
                            $(this).addClass('current-day');
                        }
                    });
                }
            }


            async function showSchedule(event) {
                if (!selectedVendorId) {
                    return;
                }

                const $cell = $(event.target);
                const day = $cell.data('day');
                const month = $cell.data('month');
                const year = $cell.data('year');
                const selectedDateStr = `${day}/${month + 1}/${year}`;

                $selectedDate.text(selectedDateStr);
                $('#calendar td').removeClass('selected');
                $cell.addClass('selected');

                $scheduleList.html(
                    '<li class="schedule-loading"><div class="calendar-loading-spinner"></div>Cargando horarios...</li>'
                );

                const schedule = await getSchedule(year, month, day);

                $scheduleList.empty();

                if (schedule && schedule.length > 0) {
                    let dif = 0;
                    const local = 6;
                    if (timezone != -6) {
                        dif = local + timezone;
                    }
                    schedule.forEach(function (time) {
                        let custTime = time;
                        let displayTime = time + ' (US Central Time)';
                        if (timezone != -6) {
                            const tz = time.split(':');
                            custTime = (parseInt(tz[0], 10) + dif) + ':' + tz[1];
                            displayTime = custTime + ' (Local Time)';
                        }
                        const $listItem = $(`<li data-hour="${time}" data-hourcust="${custTime}">`).text(displayTime);
                        $scheduleList.append($listItem);

                        $listItem.click(function () {
                            $scheduleList.find('li').removeClass('selected');
                            $(this).addClass('selected');
                        });
                    });
                } else {
                    $scheduleList.append($('<li>').text('No hay horarios disponibles para este día.'));
                }
            }

            let selecteddate = '';

            async function getSchedule(year, month, day) {
                if (!selectedVendorId) {
                    return [];
                }

                const fixDay = day;
                const mes = month + 1;
                const formattedMonth = mes < 10 ? '0' + mes : mes;
                const formattedDay = fixDay < 10 ? '0' + fixDay : fixDay;
                const date = `${year}-${formattedMonth}-${formattedDay}`;

                selecteddate = date;

                const formData = new FormData();
                formData.append('dia', date);
                formData.append('idusu', selectedVendorId);

                const horas = [];

                try {
                    const horariosResponse = await fetch('chkDisponible.php', { method: 'POST', body: formData });
                    const horariosJson = await horariosResponse.json();

                    const occupiedForm = new FormData();
                    occupiedForm.append('fecha', date);
                    occupiedForm.append('idusu', selectedVendorId);
                    const occupiedResponse = await fetch('chkDisponible.php', { method: 'POST', body: occupiedForm });
                    const times = await occupiedResponse.json();

                    if (Array.isArray(horariosJson) && horariosJson.length > 0 && horariosJson[0].horarios) {
                        const vendorHorarios = JSON.parse(horariosJson[0].horarios);
                        const hrs = [];

                        vendorHorarios.forEach(function (horario) {
                            let hr = horario;
                            if (Array.isArray(times) && times.length > 0) {
                                times.forEach(function (time) {
                                    if (time.hora === `${horario}:00`) {
                                        hr = 0;
                                    }
                                });
                            }
                            if (hr !== 0 && !hrs.includes(horario)) {
                                hrs.push(horario);
                            }
                        });

                        function convertToMinutes(timeStr) {
                            const parts = timeStr.split(':');
                            return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
                        }

                        hrs.sort(function (a, b) {
                            return convertToMinutes(a) - convertToMinutes(b);
                        });

                        hrs.forEach(function (h) {
                            horas.push(h);
                        });
                    }
                } catch (error) {
                    console.error('Error al obtener horarios:', error);
                }

                return horas;
            }


            $prevMonthButton.click(function (e) {
                e.preventDefault();
                if (!selectedVendorId) {
                    return;
                }
                let prevMonth = currentDate.getMonth() - 1;
                let prevYear = currentDate.getFullYear();
                if (prevMonth < 0) {
                    prevMonth = 11;
                    prevYear--;
                }
                currentDate = new Date(prevYear, prevMonth, 1);
                generateCalendar(prevMonth, prevYear);
            });

            $nextMonthButton.click(function (e) {
                e.preventDefault();
                if (!selectedVendorId) {
                    return;
                }
                let nextMonth = currentDate.getMonth() + 1;
                let nextYear = currentDate.getFullYear();
                if (nextMonth > 11) {
                    nextMonth = 0;
                    nextYear++;
                }
                currentDate = new Date(nextYear, nextMonth, 1);
                generateCalendar(nextMonth, nextYear);
            });

            $vendedoraSelect.on('change', function () {
                $(this).css('border-bottom', '');
                selectedVendorId = ($(this).val() || '').trim();
                selecteddate = '';
                resetSchedulePanel();
                $('#calendar td').removeClass('selected');

                if (!selectedVendorId) {
                    vendorAvailableDays = new Set();
                    updateCalendarVisibility();
                    return;
                }

                loadVendorAvailableDays(selectedVendorId);
            });

            updateCalendarVisibility();








            //aqui se mandan los datos
            const form = document.getElementById("weddingForm");

            form.addEventListener("submit", function (event) {
                event.preventDefault();
                // Lista de fields que quieres validar (incluye email y confirm_email)
                const campos = [
                    'country_code',
                    'telephone',
                    'city',
                    'wedding_date',
                    'how_long_known_us',
                ];

                let valido = true;

                // Validar que los campos no estén vacíos
                // IMPORTANT: exclude hidden inputs (there's a hidden prefills input with the same name)
                campos.forEach(function (name) {
                    // prefer the visible / non-hidden field when multiple inputs share the same name
                    const campo = $('[name="' + name + '"]').filter(function () {
                        return $(this).attr('type') !== 'hidden' && $(this).is(':visible');
                    }).first();

                    const valor = campo.length ? campo.val() : '';

                    if (!valor || valor.toString().trim() === "") {
                        // if it's the wedding_date field and the calendar selection exists, don't mark as invalid here
                        campo.css("border", "1px solid red");
                        valido = false;
                    } else {
                        campo.css("border", "");
                    }

                    // Escuchar cambios para quitar el borde rojo cuando el campo se llena
                    campo.off("input change").on("input change", function () {
                        const newVal = $(this).val();
                        if (newVal && newVal.toString().trim() !== "") {
                            $(this).css("border", "");
                        }
                    });
                });

                // Validar correos solo si el usuario ingresó alguno
                const email = ($('[name="email"]').val() || '').trim();
                const confirmEmail = ($('[name="confirm_email"]').val() || '').trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if ((email && !confirmEmail) || (!email && confirmEmail)) {
                    $('[name="email"], [name="confirm_email"]').css('border', '1px solid red');
                    $('#email-error').text('Debes completar ambos campos de correo o dejarlos vacíos.').show();
                    valido = false;
                } else if (email && confirmEmail && email !== confirmEmail) {
                    $('[name="confirm_email"]').css('border', '1px solid red');
                    $('#email-error').text('Los correos electrónicos no coinciden').show();
                    valido = false;
                } else if (email && !emailRegex.test(email)) {
                    $('[name="email"]').css('border', '1px solid red');
                    $('#email-error').text('Ingresa un correo electrónico válido.').show();
                    valido = false;
                } else {
                    $('[name="email"], [name="confirm_email"]').css('border', '');
                    $('#email-error').hide();
                }

                if (!valido) {
                    return;
                }

                if (!selectedVendorId) {
                    $vendedoraSelect.css('border-bottom', '1px solid red');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Selecciona una vendedora',
                        text: 'Debes elegir una vendedora antes de agendar la cita.',
                        customClass: { popup: 'swal2-popup-center' }
                    });
                    return;
                }

                var horaScheduleSelected = $('li.selected').data('hour') || '';
                if (!horaScheduleSelected) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Selecciona un horario',
                        text: 'Por favor elige un día y horario disponible en el calendario.',
                        customClass: { popup: 'swal2-popup-center' }
                    });
                    return;
                }

                // Validar formato de hora manual del cliente (si fue llenado)
                var horaClienteManualVal = $('#hora_cliente_manual').val().trim();
                if (horaClienteManualVal !== '') {
                    var horaRegex = /^([01]?\d|2[0-3]):[0-5]\d$/;
                    if (!horaRegex.test(horaClienteManualVal)) {
                        $('#hora_cliente_manual').addClass('is-invalid');
                        $('#hora-cliente-error').show();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Hora del cliente inválida',
                            text: 'Por favor ingresa la hora del cliente en formato HH:MM (ej: 09:00 o 14:30).',
                            confirmButtonText: 'OK',
                            customClass: { popup: 'swal2-popup-center' }
                        });
                        return;
                    } else {
                        $('#hora_cliente_manual').removeClass('is-invalid');
                        $('#hora-cliente-error').hide();
                    }
                }


                // Recolectar los datos del formulario
                const formData = new FormData(form);

                // Validar que se haya seleccionado una fecha
                // If calendar wasn't used, fallback to the visible wedding_date input value
                if (!selecteddate || selecteddate === "") {
                    const visibleWeddingDate = $('[name="wedding_date"]').filter(function () {
                        return $(this).attr('type') !== 'hidden' && $(this).is(':visible');
                    }).first().val();

                    if (visibleWeddingDate) {
                        // Use the visible input value (expected format: yyyy-mm-dd)
                        selecteddate = visibleWeddingDate;
                    }
                }

                if (!selecteddate || selecteddate === "") {
                    Swal.fire({
                        icon: 'error',
                        title: 'Por favor selecciona una fecha',
                        customClass: {
                            popup: 'swal2-popup-center'
                        }
                    });
                    return;
                }

                // Calcular la fecha del cliente basada en la zona horaria
                const selectedDateObj = new Date(selecteddate + 'T00:00:00');

                // Validar que la fecha sea válida
                if (isNaN(selectedDateObj.getTime())) {
                    Swal.fire({
                        icon: 'error',
                        title: 'La fecha seleccionada no es válida',
                        customClass: {
                            popup: 'swal2-popup-center'
                        }
                    });
                    return;
                }

                // Calcular la diferencia en horas entre la zona horaria del usuario y la zona del servidor (UTC-6 / US Central Time)
                // Si timezone = -5, entonces dif = -5 - (-6) = 1 (1 hora adelante)
                // Si timezone = -6, entonces dif = -6 - (-6) = 0 (misma zona)
                const dif = timezone - (-6);
                const dateWithTimezone = new Date(selectedDateObj.getTime() + (dif * 60 * 60 * 1000));
                const year = dateWithTimezone.getFullYear();
                const month = String(dateWithTimezone.getMonth() + 1).padStart(2, '0');
                const day = String(dateWithTimezone.getDate()).padStart(2, '0');
                const dateCustFormatted = `${year}-${month}-${day}`;

                console.log('selecteddate:', selecteddate);
                console.log('timezone:', timezone);
                console.log('dif (horas):', dif);
                console.log('dateCustFormatted:', dateCustFormatted);

                formData.append('date_appointment', selecteddate);
                
                // --- LÓGICA HORA VENDEDOR/CLIENTE ---
                // El SCHEDULE es la hora del CLIENTE (principal)
                // El campo manual es para la hora del VENDEDOR (si es diferente)
                var horaVendedorManual = $('#hora_cliente_manual').val().trim();
                var horaVendedorValida = /^([01]?\d|2[0-3]):[0-5]\d$/.test(horaVendedorManual);
                
                var horaSchedule = $('li.selected').data('hour') || '';
                
                if (horaVendedorManual !== '' && horaVendedorValida) {
                    // Hay hora manual del vendedor: usarla como time_appointment (vendedor)
                    // Y la del schedule como time_appointment_cust (cliente)
                    var partes = horaVendedorManual.split(':');
                    var hh = String(parseInt(partes[0], 10)).padStart(2, '0');
                    var mm = String(parseInt(partes[1], 10)).padStart(2, '0');
                    formData.append('time_appointment_cust', horaSchedule); // CLIENTE = schedule
                    formData.append('time_appointment', hh + ':' + mm); // VENDEDOR = manual
                    formData.append('zona_horaria_manual', '1');
                    formData.append('timezone_name', '');
                    formData.append('timezone_offset_min', '0');
                } else {
                    // Sin hora manual: ambos usan la misma hora del schedule
                    formData.append('time_appointment_cust', horaSchedule); // CLIENTE = schedule
                    formData.append('time_appointment', horaSchedule); // VENDEDOR = schedule (misma)
                    formData.append('zona_horaria_manual', '1');
                    formData.append('timezone_name', '');
                    formData.append('timezone_offset_min', '0');
                }
                // --- FIN LÓGICA HORA VENDEDOR/CLIENTE ---
                formData.append('date_appointment_cust', dateCustFormatted);
                formData.append('desde_publicidad', 0);
                formData.append('zona_horaria', timezone);
                formData.append('idusu', selectedVendorId);
                // Nuevos campos
                formData.append('wedding_date', $('#wedding_date').val());
                console.log('Valor de desde_publicidad en FormData:', formData.get('desde_publicidad'));

                const formDataObj = {};
                formData.forEach((value, key) => {
                    formDataObj[key] = value;
                });

                // Imprimir el objeto con los datos del formulario
                console.log('formDataObj (summary):', formDataObj);

                // Log detallado: cada entrada del FormData (útil para ver hidden fields y valores reales)
                for (let pair of formData.entries()) {
                    console.log('FormData entry ->', pair[0], ':', pair[1]);
                }

                // Log de selección de fecha/hora antes de enviar
                console.log('Selected raw date:', selecteddate);
                console.log('Selected appointment (server tz):', formData.get('date_appointment'));
                console.log('Selected appointment time (vendor):', formData.get('time_appointment'));
                console.log('Selected appointment time (cust):', formData.get('time_appointment_cust'));

                // Debug preview guard: if ?debug_preview=1 then show debug info and DO NOT send to server
                const debugNoSave = new URLSearchParams(window.location.search).get('debug_preview') === '1';
                const vendorTime = formData.get('time_appointment') || 'N/A';

                // small helper: convert "HH:MM" -> {hours, minutes}
                function parseTimeHM(t) {
                    if (!t || t === 'N/A') return null;
                    const parts = t.split(':');
                    if (parts.length < 2) return null;
                    return { h: parseInt(parts[0], 10), m: parseInt(parts[1], 10) };
                }

                function to12Hour(time24) {
                    if (!time24) return 'N/A';
                    const [hh, mm] = time24.split(':').map(n => parseInt(n, 10));
                    const ampm = hh >= 12 ? 'PM' : 'AM';
                    const h12 = ((hh + 11) % 12) + 1;
                    return `${String(h12).padStart(2, '0')}:${String(mm).padStart(2, '0')} ${ampm}`;
                }

                // Test timezone list to cycle through on each click (offset in hours, can be fractional)
                const TZ_TESTS = [
                    { offset: -8, label: 'UTC-8', country: 'United States (Los Angeles)' },
                    { offset: -5, label: 'UTC-5', country: 'United States (New York)' },
                    { offset: 0, label: 'UTC+0', country: 'United Kingdom' },
                    { offset: 1, label: 'UTC+1', country: 'Germany' },
                    { offset: 3, label: 'UTC+3', country: 'United Arab Emirates' },
                    { offset: 5.5, label: 'UTC+5:30', country: 'India' },
                    { offset: 8, label: 'UTC+8', country: 'China' },
                    { offset: 9, label: 'UTC+9', country: 'Japan' },
                    { offset: 10, label: 'UTC+10', country: 'Australia (Sydney)' }
                ];

                // If debug mode, compute a simulated client timezone (cycle through list each click)
                if (debugNoSave) {
                    // get current index from sessionStorage
                    let idx = parseInt(sessionStorage.getItem('debugPreviewTzIndex') || '0', 10);
                    const tz = TZ_TESTS[idx % TZ_TESTS.length];
                    // advance index for next click
                    sessionStorage.setItem('debugPreviewTzIndex', String((idx + 1) % TZ_TESTS.length));

                    console.log('[DEBUG] Using test timezone:', tz);

                    // compute client time from vendor time (vendor = US Central Time UTC-6)
                    const parsed = parseTimeHM(vendorTime);
                    let simulatedClientTime = 'N/A';
                    if (parsed) {
                        const vendorMinutes = parsed.h * 60 + parsed.m;
                        // delta = simulated offset - vendor offset(-6)
                        const deltaMinutes = Math.round((tz.offset - (-6)) * 60);
                        let clientMinutesTotal = vendorMinutes + deltaMinutes;
                        // normalize to 0-1439
                        clientMinutesTotal = ((clientMinutesTotal % 1440) + 1440) % 1440;
                        const ch = Math.floor(clientMinutesTotal / 60);
                        const cm = clientMinutesTotal % 60;
                        simulatedClientTime = `${String(ch).padStart(2, '0')}:${String(cm).padStart(2, '0')}`;
                    }

                    const vendorAMPM = vendorTime === 'N/A' ? 'N/A' : to12Hour(vendorTime);
                    const clientAMPM = simulatedClientTime === 'N/A' ? 'N/A' : to12Hour(simulatedClientTime);

                    console.log('[DEBUG] Vendor time (server/MX):', vendorTime, vendorAMPM);
                    console.log('[DEBUG] Simulated client time:', simulatedClientTime, clientAMPM);
                    console.log('[DEBUG] Simulated timezone offset:', tz.offset, tz.label, tz.country);

                    Swal.fire({
                        icon: 'info',
                        title: 'Debug mode: timezone simulation',
                        html: `
                          <strong>Vendor (US Central Time / server)</strong>: ${vendorTime} (${vendorAMPM})<br>
                          <strong>Client (simulated)</strong>: ${simulatedClientTime} (${clientAMPM})<br>
                          <strong>Timezone</strong>: ${tz.label} (UTC ${tz.offset >= 0 ? '+' + tz.offset : tz.offset})<br>
                          <strong>Example country</strong>: ${tz.country}
                        `,
                        confirmButtonText: 'OK',
                        customClass: { popup: 'swal2-popup-center' }
                    });

                    console.log('[DEBUG] Prevented submission because debug_preview=1');
                    return; // stop submission
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
                // Enviar datos vía AJAX falta agregarle la hora seleccionada desde los cards

                $.ajax({
                    url: "enviodatosform.php",
                    type: "POST",
                    data: formData,
                    processData: false,  // No procesar los datos
                    contentType: false,  // No cambiar el tipo de contenido
                    success: function (data) {
                        if (data.success == "full") {
                            // Mostrar mensaje de cita no disponible
                            Swal.fire({
                                title: "Time unavailable",
                                text: "The hour you've selected has already been occupied. Select another hour. Sorry for the inconvenience.",
                                icon: "error",
                                confirmButtonText: "OK",
                                customClass: {
                                    popup: 'swal2-popup-center'
                                }
                            });
                        } else if (data.success) {
                            console.log("data form", data);

                            const clientEmail = ($('[name="email"]').val() || '').trim();
                            const vendorInfo = vendedorasById[selectedVendorId] || {};
                            const confirmationPayload = {
                                vendedor: vendorInfo.nombre || $vendedoraSelect.find('option:selected').text(),
                                nombreCliente: $('[name="names"]').val() || 'N/A',
                                fecha: formData.get('date_appointment_cust') || selecteddate,
                                hora: formData.get('time_appointment_cust') || '',
                                meetLink: vendorInfo.enlace_meet || ''
                            };

                            if (!clientEmail) {
                                loadingSwal.close();
                                showVendorAppointmentConfirmation(confirmationPayload);
                                return;
                            }

                            // Segunda solicitud Ajax (solo si hay correo del cliente)
                            $.ajax({
                                url: 'admin/enviaCorreo.php',
                                type: 'POST',
                                data: data,
                                success: function (resu) {
                                    console.log(resu)
                                    loadingSwal.close();

                                    let res = null;
                                    try {
                                        res = JSON.parse(resu);
                                    } catch (parseError) {
                                        console.error(parseError);
                                        showVendorAppointmentConfirmation(confirmationPayload);
                                        return;
                                    }
                                    console.log(res);

                                    if (res.status === 'success' && res.data) {
                                        showVendorAppointmentConfirmation({
                                            vendedor: res.data.vendedor || confirmationPayload.vendedor,
                                            nombreCliente: res.data.nombre_cliente || confirmationPayload.nombreCliente,
                                            fecha: res.data.fecha || confirmationPayload.fecha,
                                            hora: res.data.hora || confirmationPayload.hora,
                                            meetLink: res.data.enlace_meet || confirmationPayload.meetLink
                                        });
                                    } else {
                                        showVendorAppointmentConfirmation(confirmationPayload);
                                    }
                                },
                                error: function (e) {
                                    loadingSwal.close();
                                    console.log(e);
                                    showVendorAppointmentConfirmation(confirmationPayload);
                                }
                            });
                        } else {
                            // Mostrar mensaje de error
                            const errorMsg = data.error || "There was an issue submitting your registration. Please try again.";
                            const errorIcon = data.icon || "error";
                            Swal.fire({
                                title: errorIcon === "warning" ? "Atención" : "Error",
                                text: errorMsg,
                                icon: errorIcon,
                                confirmButtonText: "OK", customClass: {
                                    popup: 'swal2-popup-center'
                                }
                            });
                        }
                    },
                    error: function (error) {
                        loadingSwal.close();

                        console.error("Error:", error);
                        
                        // Intentar parsear la respuesta del servidor incluso en caso de error
                        let errorMessage = "An unexpected error occurred. Please try again later.";
                        let errorIcon = "error";
                        
                        try {
                            // Si hay responseText, intentar parsearlo
                            if (error.responseText) {
                                const errorData = JSON.parse(error.responseText);
                                if (errorData.error) {
                                    errorMessage = errorData.error;
                                    errorIcon = errorData.icon || "error";
                                }
                            }
                        } catch (e) {
                            // Si no se puede parsear, usar mensaje genérico
                            console.error("Could not parse error response:", e);
                        }
                        
                        Swal.fire({
                            title: errorIcon === "warning" ? "Atención" : "Error",
                            text: errorMessage,
                            icon: errorIcon,
                            confirmButtonText: "OK", 
                            customClass: {
                                popup: 'swal2-popup-center'
                            }
                        });
                    }
                });
            });


        }

    });

</script>