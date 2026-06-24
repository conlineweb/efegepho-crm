<?php
include 'conn.php';



// Verificar si 'id' está presente en la URL
$idVendedor = 0;
$origen = 0;

//saber de donde viene el lead, si es ig_organic es 2, si es google_ads es 3 y si es email es 4

if (isset($_GET['origin'])) {
    if ($_GET['origin'] == 'ig') {
        $origen = 2;
    } elseif ($_GET['origin'] == 'whtsp') {
        $origen = 3;
    } elseif ($_GET['origin'] == 'email') {
        $origen = 4;
    } else {
        $origen = 0; //default
    }
}



if (isset($_GET['id'])) {
    $id = $_GET['id'];  // Obtener el valor de 'id' desde la URL
    if (isset($_GET['idusu'])) {
        $idVendedor = $_GET['idusu'];
    }
    $mostrarFormulario = false;  // No mostrar el formulario completo
    $mostrarMensaje = false; //mensaje de que no existe

    // Realizar la consulta a la base de datos para obtener el registro con el 'id' igual a $id
    $query = "SELECT * FROM calendario WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);  // Enlazamos el parámetro 'id' como un entero
    $stmt->execute();
    $resultado = $stmt->get_result();

    // Verificar si se encontró un registro
    if ($resultado->num_rows > 0) {
        $mostrarFormulario = true;
        $registro = $resultado->fetch_assoc();
        // Convertir el registro PHP a formato JSON
        $registroJSON = json_encode($registro);
    } else {
        $mostrarMensaje = true;
        $mostrarFormulario = false;
    }

} else {
    $mostrarFormulario = false;  // Mostrar el formulario completo
    $mostrarMensaje = false;
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
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wedding Inquiry Form Simplified</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
     <!-- Google Tag Manager -->
     <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
     new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
     j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
     'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
     })(window,document,'script','dataLayer','GTM-TGMQW3J9');</script>
     <!-- End Google Tag Manager -->
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
        font-size: 1rem;
        color: #5e5e5e;
        margin-bottom: 8px;
        display: block;
        /* Forzar que el label esté en su propia línea */
    }

    .country_code {
        width: 15%;
    }

    form {

        font-family: 'Cormorant Garamond', serif;
    }

    h3 {
        font-size: 2rem;
        line-height: 1.2;
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
        margin: 20px 0;
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
        table-layout: fixed;
    }

    th,
    td {
        padding: 10px 5px;
        text-align: center;
        border: 1px solid #eee;
        cursor: pointer;
        transition: background-color 0.3s ease;
        width: 14.28%;
        height: 45px;
        vertical-align: middle;
        box-sizing: border-box;
    }

    th {
        font-weight: 600;
        color: #555;
        height: 35px;
    }

    td:hover:not(.disabled):not(.blocked) {
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
            padding: 8px 4px;
            font-size: 14px;
            height: 40px;
        }

        td:hover:not(.disabled):not(.blocked) {
            background-color: #f0f0f0;
        }

        th {
            height: 30px;
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
            padding: 6px 3px;
            font-size: 14px;
            height: 36px;
        }

        th {
            height: 28px;
        }

        #schedule-container {
            padding: 10px;
        }

        #schedule-list li {
            font-size: 16px;
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

    .entry-choice {
       
        display: flex;
        align-items: center;
        justify-content: center;
        transition: opacity 0.25s ease;
        margin-top: 50px;
        margin-bottom: 70px;
    }

    .entry-choice.fade-out {
        opacity: 0;
    }

    .entry-choice-panel {
        width: 100%;
        max-width: 760px;
        text-align: center;
    }

    .entry-intro {
        font-size: 22px;
        line-height: 1.3;
        margin-bottom: 18px;
    }

    .entry-question {
        font-size: 30px;
        margin-bottom: 28px;
    }

    .entry-cards {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) auto minmax(220px, 1fr);
        gap: 16px;
        align-items: center;
    }

    .entry-or {
        font-size: 24px;
        color: #666;
        user-select: none;
    }

    .entry-card-btn {
        border: 1px solid #d6d6d6;
        border-radius: 10px;
        padding: 20px 16px;
        background: #fff;
        font-size: 20px;
        color: #111;
        transition: border-color 0.2s ease, transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
    }

    .entry-card-btn.active {
        border: 2px solid #000;
        background: #f0f0f0;
        box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.08);
        transform: translateY(-1px);
        font-weight: 600;
    }

    .entry-card-btn:hover {
        border-color: #000;
        transform: translateY(-2px);
    }

    #formContainer,
    #plannerFormContainer {
        display: none;
        margin-top: 10px;
    }

    #formContainer.tab-active,
    #plannerFormContainer.tab-active {
        display: block;
    }

    @keyframes fadeUpIn {
        from {
            opacity: 0;
            transform: translateY(24px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeDownOut {
        from {
            opacity: 1;
            transform: translateY(0);
        }

        to {
            opacity: 0;
            transform: translateY(24px);
        }
    }

    @media (max-width: 768px) {
        h3 {
            font-size: 1.6rem;
        }

        .form-label {
            font-size: 0.95rem;
        }

        .entry-intro {
            font-size: 18px;
        }

        .entry-question {
            font-size: 24px;
        }

        .entry-card-btn {
            font-size: 18px;
            padding: 16px 14px;
        }

        .entry-cards {
            grid-template-columns: 1fr;
        }

        .entry-or {
            margin: 4px 0;
        }
    }
</style>

<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TGMQW3J9"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <div class=" container-sm mt-4">
        <div class="d-flex mt-5">


        </div>

        <section id="entryChoice" class="entry-choice form-wrapper">
            <div class="entry-choice-panel">
                <p class="entry-intro mb-4">Before continuing, please select the option that best describes your profile. This will allow us to provide you with a personalized experience.</p>
                <h2 class="entry-question">Are you a</h2>
                <div class="entry-cards">
                    <button type="button" id="btnFinalClient" class="entry-card-btn">Final Client</button>
                    <span class="entry-or">or</span>
                    <button type="button" id="btnWeddingPlanner" class="entry-card-btn">Wedding Planner</button>
                </div>
            </div>
        </section>

        <div id="formContainer" class="tab-active">
        <form id="weddingForm" class="form-wrapper">
            <h3 class="text-center m-5"> For the wildly in love</h3>

            <div class="row">
                <div class="col-12 col-md-6 mb-5">
                    <div class="d-flex flex-column h-100 justify-content-end">
                        <input type="text" name="names" placeholder="your names">
                    </div>
                </div>
                <div class="col-12 col-md-6 mb-5">
                    <div class="d-flex flex-column h-100 justify-content-end">
                        <input type="email" name="email" placeholder="email address">

                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-5">
                    <div class="d-flex flex-column h-100 justify-content-end">
                        <input type="email" name="confirm_email" placeholder="confirm email address">
                        <div class="error-message" id="email-error">Los correos electrónicos no coinciden</div>
                    </div>
                </div>
                <!-- A la derecha de Confirm email: Event location + Date of Event -->
                <div class="col-12 col-md-6 mb-5">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-7">
                            <label class="form-label">Event location</label>
                            <input type="text" name="wedding_location" id="wedding_location"
                                placeholder="Event location" required>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label">Date of Event</label>
                            <input type="date" name="wedding_date" id="wedding_date" placeholder="Date of Event"
                                required>
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
            <input type="hidden" name="how_did_hear" value="">
            <input type="hidden" name="referrer_url" id="referrer_url" value="">

            <div class="row g-4 mb-5 align-items-end">
                <div class="col-12 col-md-6">
                    <label class="form-label">How long have you known us?</label>
                    <select name="how_long_known_us" required>
                        <option value="" disabled selected>Select...</option>
                        <option value="Less than 6 months">Less than 6 months</option>
                        <option value="More than 6 months">More than 6 months</option>
                    </select>
                </div>
                <input type="hidden" id="first_contact_channel" name="first_contact_channel" value="website">
            </div>

            <div style="width:100%">
                <h3 class="text-center m-5">Schedule your personalized event consultation</h3>
                <div class="mt-1 mb-1" id="meet">
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
                        <div class="right-panel ">
                            <div id="schedule-container">
                                <h3>Schedule for <span id="selected-date"></span></h3>
                                <p class="text-center muted-note">Time Zone: US Central Time (UTC-6)</p>
                                <ul id="schedule-list"></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- Cierre de la sección de calendario -->

            <!-- Contenedor para el botón NEXT con margen adecuado -->
            <div class="text-center mt-5 mb-5" style="padding: 20px;">
                <div class="mb-3" style="font-size:12px; text-align:center; max-width:800px; margin:0 auto; color:#666;">
                    <p>By continuing, you agree to the <a href="https://www.efegepho.com/terms-conditions-and-privacy-policy-provided" target="_blank" rel="noopener">Terms &amp; Conditions and Privacy Policy provided by Efegepho</a>. By providing your phone number, you consent to receive text messages and phone calls from Efegepho.</p>
                </div>
                <!-- Campo oculto para identificar que viene de formulario_website.php -->
                <input type="hidden" name="from_website_form" value="1">
                <button type="submit" class="btn-submit" id="nextButton">NEXT</button>
            </div>
        </form>
        </div>

        <div id="plannerFormContainer">
            <form id="weddingPlannerForm" class="form-wrapper">
                <h3 class="text-center m-5">Wedding Planner Inquiry</h3>

                <div class="row g-4">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Wedding Planner Company Name *</label>
                        <input type="text" name="planner_company_name" placeholder="Wedding Planner Company Name" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Coordinator Name (Main Contact) *</label>
                        <input type="text" name="planner_coordinator_name" placeholder="Coordinator Name (Main Contact)" required>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Country code *</label>
                        <select id="planner_country_code" name="planner_country_code" required>
                            <option value="" disabled selected>Select your country code</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" name="planner_phone" placeholder="Phone Number" required>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="planner_email" placeholder="Email Address" required>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-12">
                        <label class="form-label">What brings you here today? *</label>
                        <select name="planner_reason" required>
                            <option value="" disabled selected>Select...</option>
                            <option value="High Urgency – I need a quote for a client immediately.">High Urgency – I need a quote for a client immediately.</option>
                            <option value="Active Client – I have a couple interested in your services.">Active Client – I have a couple interested in your services.</option>
                            <option value="Partnership Interest – I’d like to understand how you work.">Partnership Interest – I’d like to understand how you work.</option>
                            <option value="Both – I have a client and would also like to explore a partnership.">Both – I have a client and would also like to explore a partnership.</option>
                        </select>
                    </div>
                </div>

                <div class="row g-4 mt-1 mb-5">
                    <div class="col-12">
                        <label class="form-label">How long have you known us?</label>
                        <select name="planner_how_long_known_us" required>
                            <option value="" disabled selected>Select...</option>
                            <option value="Less than 6 months">Less than 6 months</option>
                            <option value="More than 6 months">More than 6 months</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" id="planner_first_contact_channel" name="first_contact_channel" value="website">
                <input type="hidden" name="referrer_url" id="planner_referrer_url" value="">

                <div class="text-center mt-5 mb-5" style="padding: 20px;">
                    <button type="submit" class="btn-submit">NEXT</button>
                </div>
            </form>
        </div>
    </div>

</body>

</html>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script src="admin/assets/extensions/@fortawesome/fontawesome-free/js/all.js" data-auto-replace-svg="nest"></script>
<script src="admin/assets/extensions/jquery/jquery.js"></script>
<script src="admin/assets/extensions/jquery-ui/jquery-ui.min.js"></script>

<!-- Removed geo_timezone.js: timezone is now obtained from the device runtime -->
<script src='https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/moment-timezone@0.5.45/builds/moment-timezone-with-data-1970-2030.min.js"></script>
<script>






    const SELLER_TIMEZONE = 'Etc/GMT+6'; // UTC-6 fijo para la hora base de la vendedora
    const SELLER_UTC_OFFSET_MINUTES = -360;

    function isValidIanaTimeZone(timezoneName) {
        if (!timezoneName || typeof timezoneName !== 'string') {
            return false;
        }
        try {
            Intl.DateTimeFormat('en-US', { timeZone: timezoneName }).format();
            return true;
        } catch (error) {
            return false;
        }
    }

    function detectClientTimezone() {
        const timezoneOffsetMinutes = new Date().getTimezoneOffset();
        const timezoneOffsetHours = -(timezoneOffsetMinutes / 60);
        let timezoneName = '';
        try {
            timezoneName = (typeof Intl !== 'undefined' && Intl.DateTimeFormat)
                ? Intl.DateTimeFormat().resolvedOptions().timeZone
                : '';
        } catch (error) {
            timezoneName = '';
        }

        if (!isValidIanaTimeZone(timezoneName)) {
            timezoneName = '';
        }

        return {
            timezone_name: timezoneName,
            timezone_offset_min: timezoneOffsetMinutes,
            timezone_offset_hours: timezoneOffsetHours
        };
    }

    function normalizeSlotTime(timeText) {
        const raw = String(timeText || '').trim();
        const match = raw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
        if (!match) {
            return '';
        }
        const hour = match[1].padStart(2, '0');
        const minute = match[2].padStart(2, '0');
        return `${hour}:${minute}`;
    }

    function getUtcDateFromSellerSlot(dateText, timeText) {
        const normalizedTime = normalizeSlotTime(timeText);
        const dateMatch = String(dateText || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
        const timeMatch = normalizedTime.match(/^(\d{2}):(\d{2})$/);
        if (!dateMatch || !timeMatch) {
            return null;
        }

        const year = parseInt(dateMatch[1], 10);
        const month = parseInt(dateMatch[2], 10);
        const day = parseInt(dateMatch[3], 10);
        const hour = parseInt(timeMatch[1], 10);
        const minute = parseInt(timeMatch[2], 10);

        const localAsUtcMillis = Date.UTC(year, month - 1, day, hour, minute, 0);
        const utcMillis = localAsUtcMillis - (SELLER_UTC_OFFSET_MINUTES * 60 * 1000);
        return new Date(utcMillis);
    }

    function getDatePartsForTimezone(dateObj, timezoneName) {
        const formatter = new Intl.DateTimeFormat('en-CA', {
            timeZone: timezoneName,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
        const parts = formatter.formatToParts(dateObj).reduce((acc, part) => {
            if (part.type !== 'literal') {
                acc[part.type] = part.value;
            }
            return acc;
        }, {});

        return {
            date: `${parts.year}-${parts.month}-${parts.day}`,
            time: `${parts.hour}:${parts.minute}`
        };
    }

    function convertSellerSlotToClientLocal(dateText, timeText, clientTzInfo) {
        const normalizedSellerTime = normalizeSlotTime(timeText);
        const utcDate = getUtcDateFromSellerSlot(dateText, normalizedSellerTime);
        if (!utcDate) {
            return {
                clientDate: dateText,
                clientTime: normalizedSellerTime || String(timeText || ''),
                isSameAsSeller: true
            };
        }

        if (window.moment && typeof window.moment.tz === 'function' && isValidIanaTimeZone(clientTzInfo.timezone_name)) {
            const sellerMoment = window.moment.tz(`${dateText} ${normalizedSellerTime}`, 'YYYY-MM-DD HH:mm', SELLER_TIMEZONE);
            const clientMoment = sellerMoment.clone().tz(clientTzInfo.timezone_name);
            return {
                clientDate: clientMoment.format('YYYY-MM-DD'),
                clientTime: clientMoment.format('HH:mm'),
                isSameAsSeller: clientMoment.format('YYYY-MM-DD HH:mm') === sellerMoment.format('YYYY-MM-DD HH:mm')
            };
        }

        if (isValidIanaTimeZone(clientTzInfo.timezone_name)) {
            const localParts = getDatePartsForTimezone(utcDate, clientTzInfo.timezone_name);
            return {
                clientDate: localParts.date,
                clientTime: localParts.time,
                isSameAsSeller: localParts.date === dateText && localParts.time === normalizedSellerTime
            };
        }

        const fallbackDate = new Date(utcDate.getTime() - (clientTzInfo.timezone_offset_min * 60 * 1000));
        const fallbackDateText = `${fallbackDate.getUTCFullYear()}-${String(fallbackDate.getUTCMonth() + 1).padStart(2, '0')}-${String(fallbackDate.getUTCDate()).padStart(2, '0')}`;
        const fallbackTimeText = `${String(fallbackDate.getUTCHours()).padStart(2, '0')}:${String(fallbackDate.getUTCMinutes()).padStart(2, '0')}`;
        return {
            clientDate: fallbackDateText,
            clientTime: fallbackTimeText,
            isSameAsSeller: fallbackDateText === dateText && fallbackTimeText === normalizedSellerTime
        };
    }

    const initialTimezoneInfo = detectClientTimezone();
    console.log('Zona horaria detectada:', initialTimezoneInfo);

    document.addEventListener("DOMContentLoaded", function () {
        const entryChoice = document.getElementById('entryChoice');
        const formContainer = document.getElementById('formContainer');
        const plannerFormContainer = document.getElementById('plannerFormContainer');
        const btnFinalClient = document.getElementById('btnFinalClient');
        const btnWeddingPlanner = document.getElementById('btnWeddingPlanner');

        function activateTab(tabKey) {
            if (!formContainer || !plannerFormContainer || !btnFinalClient || !btnWeddingPlanner) return;

            const isFinalClient = tabKey === 'final_client';

            formContainer.classList.toggle('tab-active', isFinalClient);
            plannerFormContainer.classList.toggle('tab-active', !isFinalClient);

            btnFinalClient.classList.toggle('active', isFinalClient);
            btnWeddingPlanner.classList.toggle('active', !isFinalClient);
        }

        if (btnFinalClient && formContainer) {
            btnFinalClient.addEventListener('click', function () {
                activateTab('final_client');
            });
        }

        if (btnWeddingPlanner && plannerFormContainer) {
            btnWeddingPlanner.addEventListener('click', function () {
                activateTab('wedding_planner');
            });
        }

        activateTab('final_client');

        const TRACKING_SOURCE_STORAGE_KEY = 'efege_full_source_url';
        const TRACKING_KEYS = [
            'gclid',
            'gbraid',
            'wbraid',
            'fbclid',
            'msclkid',
            'ttclid',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_content',
            'utm_term',
            'utm_id'
        ];

        function hasTrackingParameters(url) {
            try {
                const parsedUrl = new URL(url);
                for (const key of parsedUrl.searchParams.keys()) {
                    const normalizedKey = key.toLowerCase();
                    if (normalizedKey.indexOf('utm_') === 0 || TRACKING_KEYS.includes(normalizedKey)) {
                        return true;
                    }
                }
            } catch (error) {
                return false;
            }

            return false;
        }

        function getQueryParamUrl(paramName) {
            try {
                const value = new URL(window.location.href).searchParams.get(paramName);
                return value && value.trim() ? value.trim() : '';
            } catch (error) {
                return '';
            }
        }

        function getSameOriginParentUrl() {
            try {
                if (window.parent && window.parent !== window && window.parent.location.href) {
                    return window.parent.location.href;
                }
            } catch (error) {
                return '';
            }

            return '';
        }

        function readStoredSourceUrl() {
            try {
                return sessionStorage.getItem(TRACKING_SOURCE_STORAGE_KEY) || localStorage.getItem(TRACKING_SOURCE_STORAGE_KEY) || '';
            } catch (error) {
                return '';
            }
        }

        function storeSourceUrl(url) {
            if (!url || url === 'direct') {
                return;
            }

            try {
                sessionStorage.setItem(TRACKING_SOURCE_STORAGE_KEY, url);
                localStorage.setItem(TRACKING_SOURCE_STORAGE_KEY, url);
            } catch (error) {
                // Storage can be blocked; the hidden input still carries the URL for this submit.
            }
        }

        function getFullSourceUrl() {
            const explicitUrl = [
                'source_url',
                'origin_url',
                'landing_url',
                'referrer_url'
            ].map(getQueryParamUrl).find(Boolean) || '';
            const parentUrl = getSameOriginParentUrl();
            const currentUrl = window.location.href;
            const referrerUrl = document.referrer || '';
            const storedUrl = readStoredSourceUrl();
            const candidates = [
                explicitUrl,
                parentUrl,
                currentUrl,
                referrerUrl,
                storedUrl
            ].filter(Boolean);
            const trackedCandidate = candidates.find(hasTrackingParameters);
            const selectedUrl = trackedCandidate || explicitUrl || referrerUrl || parentUrl || currentUrl || 'direct';

            storeSourceUrl(selectedUrl);

            return selectedUrl;
        }

        // Permite parsear respuestas JSON válidas aunque el backend devuelva múltiples objetos concatenados.
        function parsePossiblyConcatenatedJson(rawValue) {
            if (typeof rawValue === 'object' && rawValue !== null) {
                return rawValue;
            }

            const text = String(rawValue || '').trim();
            if (!text) {
                throw new Error('Respuesta vacía');
            }

            try {
                return JSON.parse(text);
            } catch (error) {
                // Intentar extraer objetos JSON top-level concatenados y usar el último válido.
                const candidates = [];
                let depth = 0;
                let start = -1;
                let inString = false;
                let escaped = false;

                for (let i = 0; i < text.length; i++) {
                    const ch = text[i];

                    if (inString) {
                        if (escaped) {
                            escaped = false;
                        } else if (ch === '\\') {
                            escaped = true;
                        } else if (ch === '"') {
                            inString = false;
                        }
                        continue;
                    }

                    if (ch === '"') {
                        inString = true;
                        continue;
                    }

                    if (ch === '{') {
                        if (depth === 0) {
                            start = i;
                        }
                        depth++;
                        continue;
                    }

                    if (ch === '}') {
                        if (depth > 0) {
                            depth--;
                            if (depth === 0 && start !== -1) {
                                candidates.push(text.slice(start, i + 1));
                                start = -1;
                            }
                        }
                    }
                }

                for (let i = candidates.length - 1; i >= 0; i--) {
                    try {
                        return JSON.parse(candidates[i]);
                    } catch (innerError) {
                        // Continuar con el siguiente candidato.
                    }
                }

                throw error;
            }
        }

        const referrerValue = getFullSourceUrl();
        document.querySelectorAll('input[name="referrer_url"]').forEach(function (input) {
            input.value = referrerValue;
        });

        const weddingPlannerForm = document.getElementById('weddingPlannerForm');
        if (weddingPlannerForm) {
            weddingPlannerForm.addEventListener('submit', function (event) {
                event.preventDefault();

                const plannerFormData = new FormData(weddingPlannerForm);
                ['planner_company_name', 'planner_coordinator_name', 'planner_phone', 'planner_email', 'planner_reason', 'planner_how_long_known_us', 'first_contact_channel'].forEach(function (fieldName) {
                    if (plannerFormData.has(fieldName)) {
                        plannerFormData.set(fieldName, String(plannerFormData.get(fieldName) || '').trim());
                    }
                });

                const loadingSwal = Swal.fire({
                    title: 'Submitting...',
                    text: 'Please wait while we process your request.',
                    allowOutsideClick: false,
                    customClass: {
                        popup: 'swal2-popup-center'
                    },
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'enviodatosformwp.php',
                    type: 'POST',
                    data: plannerFormData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        let serverData = response;

                        if (typeof serverData === 'string') {
                            try {
                                serverData = JSON.parse(serverData);
                            } catch (e) {
                                loadingSwal.close();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'Server response inválida.',
                                    customClass: {
                                        popup: 'swal2-popup-center'
                                    }
                                });
                                return;
                            }
                        }

                        loadingSwal.close();

                        if (serverData.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: serverData.message || 'Information saved successfully.',
                                customClass: {
                                    popup: 'swal2-popup-center'
                                }
                            });
                            weddingPlannerForm.reset();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: serverData.error || 'No fue posible guardar la información.',
                                customClass: {
                                    popup: 'swal2-popup-center'
                                }
                            });
                        }
                    },
                    error: function (error) {
                        loadingSwal.close();

                        let errorMessage = 'An unexpected error occurred. Please try again later.';

                        try {
                            if (error.responseText) {
                                const errorData = JSON.parse(error.responseText);
                                if (errorData.error) {
                                    errorMessage = errorData.error;
                                }
                            }
                        } catch (e) {
                            console.error('Could not parse error response:', e);
                        }

                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: errorMessage,
                            customClass: {
                                popup: 'swal2-popup-center'
                            }
                        });
                    }
                });
            });
        }

        // No longer requesting location from geo_timezone.js; relying on device timezone via getTimezoneOffset()
        // (previous behavior used window.blockIfNoTimezone())

        // Validación en tiempo real para la confirmación de email
        $('[name="confirm_email"]').on('input', function () {
            const email = $('[name="email"]').val();
            const confirmEmail = $(this).val();

            if (confirmEmail && email !== confirmEmail) {
                $(this).css("border", "1px solid red");
                $('#email-error').show();
            } else {
                $(this).css("border", "");
                $('#email-error').hide();
            }
        });

        // También validar cuando se cambie el email principal
        $('[name="email"]').on('input', function () {
            const email = $(this).val();
            const confirmEmail = $('[name="confirm_email"]').val();

            if (confirmEmail && email !== confirmEmail) {
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
                const plannerSelectElement = document.getElementById('planner_country_code');

                function populateCountrySelect(targetSelect) {
                    if (!targetSelect) return;

                    targetSelect.innerHTML = '<option value="" disabled selected>Select your country code</option>';
                    targetSelect.style.webkitAppearance = 'none';
                    targetSelect.style.borderRadius = '0';

                    data.countries.sort((a, b) => a.name.localeCompare(b.name))
                        .forEach(country => {
                            const option = new Option(`${country.code} (${country.name})`, country.code);
                            targetSelect.add(option);
                        });
                }

                if (data && data.countries) {
                    populateCountrySelect(selectElement);
                    populateCountrySelect(plannerSelectElement);
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
            const plannerSelectElement = document.getElementById('planner_country_code');

            [selectElement, plannerSelectElement].forEach(targetSelect => {
                if (!targetSelect) return;
                basicCountries.forEach(country => {
                    const option = new Option(`${country.code} (${country.name})`, country.code);
                    targetSelect.add(option);
                });
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

            let currentDate = new Date();

            function generateCalendar(month, year) {
                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                const daysInMonth = lastDay.getDate();
                const startDay = firstDay.getDay(); // 0 = Domingo, 1 = Lunes, ...

                // Fecha actual
                const today = new Date();
                const minDate = today; // Fecha mínima (hoy)
                const maxDate = new Date(today);
                maxDate.setDate(today.getDate() + 14); // Fecha máxima (20 días después)

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
                            const currentDate = new Date(year, month, date);
                            const currentDateStr = `${year}-${month + 1 < 10 ? '0' + (month + 1) : month + 1}-${date < 10 ? '0' + date : date}`;
                            const isBeforeMinDate = currentDate < minDate;
                            const isAfterMaxDate = currentDate > maxDate;
                            const isBlocked = diasBloqueados.includes(currentDateStr);
                            const blockedClass = isBlocked ? 'blocked' : '';
                            // Deshabilitar días fuera del rango
                            const disabledClass = isBeforeMinDate || isAfterMaxDate ? 'disabled' : '';

                            html += `<td data-day="${date}" data-month="${month}" data-year="${year}" class="${disabledClass} ${blockedClass}">${date}</td>`;
                            date++;
                        }
                    }
                    html += '</tr>';
                }

                $calendar.find('tbody').html(html);
                $currentMonth.text(new Date(year, month).toLocaleDateString('en-US', { month: 'long', year: 'numeric' }));

                const $dayCells = $calendar.find('td[data-day]');
                $dayCells.each(function () {
                    $(this).click(showSchedule);
                });

                // Resaltar el día actual
                if (month === today.getMonth() && year === today.getFullYear()) {
                    const $dayCells = $calendar.find('td[data-day]');
                    $dayCells.each(function () {
                        if (parseInt($(this).data('day')) === today.getDate()) {
                            $(this).addClass('current-day');
                        }
                    });
                }
            }


            async function showSchedule(event) {
                const $cell = $(event.target);
                const day = $cell.data('day');
                console.log("showSchedule day", day)
                const month = $cell.data('month');
                const year = $cell.data('year');
                const selectedDateStr = `${day}/${month + 1}/${year}`;

                // Mostrar la fecha seleccionada
                $selectedDate.text(selectedDateStr);

                // Limpiar la clase selected de todos los días antes de agregarla al seleccionado
                $('#calendar td').removeClass('selected');

                // Añadir la clase selected al día seleccionado
                $cell.addClass('selected');

                // Llamar a la función que obtiene los horarios para el día seleccionado
                const schedule = await getSchedule(year, month, day);
                console.log("schedule ", schedule);

                $scheduleList.empty();

                if (schedule && schedule.length > 0) {
                    const clientTzInfo = detectClientTimezone();
                    schedule.forEach(time => {
                        const sellerTime = normalizeSlotTime(time);
                        const convertedSlot = convertSellerSlotToClientLocal(selecteddate, sellerTime, clientTzInfo);
                        const displayTime = `${convertedSlot.clientTime} (Local Time)`;
                        const $listItem = $(`<li data-hour="${sellerTime}" data-hourcust="${convertedSlot.clientTime}" data-datecust="${convertedSlot.clientDate}">`).text(displayTime);
                        $scheduleList.append($listItem);

                        $listItem.click(function () {
                            $scheduleList.find('li').removeClass('selected');
                            $(this).addClass('selected');
                        });
                    });
                } else {
                    const $listItem = $('<li>').text('There are no schedules for this day.');
                    $scheduleList.append($listItem);
                }
            }

            let selecteddate = ""


            async function getSchedule(year, month, day) {
                let fixDay = day;

                let mes = month + 1;
                // Asegurarse de que mes y día siempre tengan 2 dígitos
                let formattedMonth = mes < 10 ? '0' + mes : mes;
                let formattedDay = fixDay < 10 ? '0' + fixDay : fixDay;
                // Crear la fecha en formato yyyy-mm-dd
                let date = `${year}-${formattedMonth}-${formattedDay}`;
                console.log(date)

                selecteddate = date;
                const fechaComoCadena = date + " 00:00:00";
                const numeroDia = new Date(fechaComoCadena).getDay();

                let inicio = null;
                let fin = null;
                let formData = new FormData();
                console.log("date ", date)
                formData.append('dia', date);

                let horas = [];

                try {
                    // Primer solicitud Ajax
                    $.ajax({
                        url: "chkDisponible.php",
                        type: "POST",
                        data: formData,
                        async: false,
                        processData: false,  // No proceses los datos del FormData (importante para mantener el tipo FormData)
                        contentType: false,  // No establezcas un content-type, ya que se manejará automáticamente
                        success: function (horariosJson) {
                            console.log("horariosJson ", horariosJson)
                            // Ahora hacemos otra solicitud con la fecha
                            let form = new FormData();
                            form.append('fecha', date);

                            // Segunda solicitud Ajax
                            $.ajax({
                                url: "chkDisponible.php",
                                type: "POST",
                                data: form,
                                async: false,
                                dataType: "json",
                                processData: false,  // No proceses los datos del FormData (importante para mantener el tipo FormData)
                                contentType: false,  // No establezcas un content-type, ya que se manejará automáticamente
                                success: function (times) {
                                    let hrs = [];
                                    let horarios = JSON.parse(horariosJson)
                                    horarios.forEach((vendor) => {
                                        let vendor_horarios = JSON.parse(vendor.horarios);
                                        console.log("vendor_horarios ", vendor_horarios)

                                        vendor_horarios.forEach((horario) => {

                                            if (times.length > 0) {
                                                let hr = horario

                                                times.forEach((time) => {
                                                    if (time.hora == `${horario}:00` && time.idusu == vendor.idusu) {
                                                        console.log(time.hora + " es igual a " + `${horario}:00`)
                                                        hr = 0;
                                                    }
                                                });
                                                // console.log("hr ",hr)
                                                // console.log("hrs ",hrs)

                                                if (hr != 0 && !hrs.includes(horario)) {
                                                    hrs.push(horario);
                                                }

                                            } else {
                                                if (!hrs.includes(horario)) {
                                                    hrs.push(horario);
                                                }
                                            }
                                        })

                                    });

                                    // Ordenamos las horas
                                    // Función para convertir la hora en formato "HH:MM" a minutos totales
                                    function convertToMinutes(timeStr) {
                                        let [hrs, mins] = timeStr.split(":");
                                        return parseInt(hrs) * 60 + parseInt(mins);
                                    }

                                    // Ordena el array de horas
                                    hrs.sort(function (a, b) {
                                        return convertToMinutes(a) - convertToMinutes(b);
                                    });


                                    // Creamos el HTML para las tarjetas
                                    for (let i = 0; i < hrs.length; i++) {
                                        horas.push(hrs[i]);
                                    }

                                    console.log("horas ", horas);

                                },
                                error: function (xhr, status, error) {
                                    console.error("Error en la segunda solicitud Ajax:", error);
                                }
                            });
                        },
                        error: function (xhr, status, error) {
                            console.error("Error en la primera solicitud Ajax:", error);
                        }
                    });

                } catch (error) {
                    console.error("Error en el proceso:", error);
                }
                return horas;
            }


            $prevMonthButton.click(function (e) {
                e.preventDefault();
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
                let nextMonth = currentDate.getMonth() + 1;
                let nextYear = currentDate.getFullYear();
                if (nextMonth > 11) {
                    nextMonth = 0;
                    nextYear++;
                }
                currentDate = new Date(nextYear, nextMonth, 1);
                generateCalendar(nextMonth, nextYear);
            });

            generateCalendar(currentDate.getMonth(), currentDate.getFullYear());








            //aqui se mandan los datos
            const form = document.getElementById("weddingForm");

            form.addEventListener("submit", async function (event) {
                event.preventDefault();
                // Lista de names que quieres validar
                const campos = [
                    'names',
                    'email',
                    'confirm_email',
                    'country_code',
                    'telephone',
                    'city',
                    'wedding_location',
                    'wedding_date',
                    'how_long_known_us',
                ];

                let valido = true;

                // Validar que los campos no estén vacíos
                campos.forEach(function (name) {
                    const campo = $('[name="' + name + '"]');
                    const valor = campo.val();

                    if (!valor || valor.toString().trim() === "") {
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

                // Validar que los correos coincidan
                const email = $('[name="email"]').val();
                const confirmEmail = $('[name="confirm_email"]').val();

                if (email !== confirmEmail) {
                    $('[name="confirm_email"]').css("border", "1px solid red");
                    $('#email-error').show();
                    valido = false;
                } else {
                    $('[name="confirm_email"]').css("border", "");
                    $('#email-error').hide();
                }

                if (!valido) {
                    return;
                }




                const confirmedTZ = detectClientTimezone();

                // Recolectar los datos del formulario
                const formData = new FormData(form);

                // Validar que se haya seleccionado una fecha
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

                const selectedSlot = $('li.selected');
                if (!selectedSlot.length) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Por favor selecciona un horario',
                        customClass: {
                            popup: 'swal2-popup-center'
                        }
                    });
                    return;
                }

                const selectedHourVendor = normalizeSlotTime(selectedSlot.data('hour'));
                const selectedHourClient = normalizeSlotTime(selectedSlot.data('hourcust')) || selectedHourVendor;
                const dateCustFormatted = String(selectedSlot.data('datecust') || selecteddate);

                if (!selectedHourVendor) {
                    Swal.fire({
                        icon: 'error',
                        title: 'El horario seleccionado no es válido',
                        customClass: {
                            popup: 'swal2-popup-center'
                        }
                    });
                    return;
                }

                console.log('selecteddate:', selecteddate);
                console.log('selectedHourVendor:', selectedHourVendor);
                console.log('selectedHourClient:', selectedHourClient);
                console.log('dateCustFormatted:', dateCustFormatted);

                formData.append('date_appointment', selecteddate);
                formData.append('time_appointment', selectedHourVendor);
                formData.append('time_appointment_cust', selectedHourClient);
                formData.append('date_appointment_cust', dateCustFormatted);
                formData.append('desde_publicidad', <?php echo $origen; ?>);
                // Enviar TZ desde el dispositivo (Intl + offset); no depende de geo_timezone.js
                formData.append('timezone_name', confirmedTZ.timezone_name || '');
                formData.append('timezone_offset_min', (confirmedTZ.timezone_offset_min ?? 0));
                formData.append('zona_horaria', (confirmedTZ.timezone_offset_hours ?? 0));
                // Nuevos campos
                formData.append('wedding_location', $('#wedding_location').val());
                formData.append('wedding_date', $('#wedding_date').val());
                console.log('Valor de desde_publicidad en FormData:', formData.get('desde_publicidad'));

                const formDataObj = {};
                formData.forEach((value, key) => {
                    formDataObj[key] = value;
                });

                // Imprimir el objeto con los datos del formulario
                console.log(formDataObj);
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
                        let serverData = null;
                        try {
                            serverData = parsePossiblyConcatenatedJson(data);
                        } catch (e) {
                            // Couldn't parse JSON — show raw server response for debugging
                            loadingSwal.close();
                            Swal.fire({
                                title: "Server response (not JSON)",
                                html: `<pre style="text-align:left;white-space:pre-wrap;">${String(data || '')}</pre>`,
                                icon: "error",
                                confirmButtonText: "OK",
                                customClass: { popup: 'swal2-popup-center' }
                            });
                            return;
                        }

                        // If server captured raw output (warnings/notices), show it for debugging
                        if (serverData && serverData.raw_output) {
                            loadingSwal.close();
                            Swal.fire({
                                title: "Server output (warnings)",
                                html: `<pre style="text-align:left;white-space:pre-wrap;">${serverData.raw_output}</pre>`,
                                icon: "error",
                                confirmButtonText: "OK",
                                customClass: { popup: 'swal2-popup-center' }
                            });
                            return;
                        }

                        if (serverData.success == "full") {
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
                        } else if (serverData.success) {
                            console.log("data form", serverData);


                            // Segunda solicitud Ajax
                            $.ajax({
                                url: 'admin/enviaCorreo.php',
                                type: 'POST',
                                data: serverData,
                                success: function (resu) {
                                    console.log(resu)
                                    loadingSwal.close();

                                    let res = null;
                                    try {
                                        res = parsePossiblyConcatenatedJson(resu);
                                    } catch (e) {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: 'La respuesta del envío de correo no es válida.',
                                            customClass: {
                                                popup: 'swal2-popup-center'
                                            }
                                        });
                                        return;
                                    }
                                    console.log(res);

                                    // Verifica si el estado es 'success'
                                    if (res.status === 'success') {
                                        const vendedor = res.data.vendedor || 'N/A';
                                        const nombreCliente = res.data.nombre_cliente || $('[name="names"]').val() || 'N/A';
                                        const fecha = (res.data.fecha && String(res.data.fecha).trim()) ? String(res.data.fecha).trim() : dateCustFormatted;
                                        const hora = (res.data.hora && String(res.data.hora).trim()) ? String(res.data.hora).trim() : (selectedHourClient || selectedHourVendor || '');
                                        const meetLink = res.data.enlace_meet || '';
                                        const horaFormatoEntrada = hora && hora.length === 5 ? 'HH:mm' : 'HH:mm:ss';
                                        var formattedDateHora = (hora && hora.trim()) ? moment(hora, horaFormatoEntrada).format('hh:mm A') : 'N/A';
                                        var formattedDateFecha = (fecha && fecha.trim()) ? moment(fecha, 'YYYY-MM-DD').format('MMMM D, YYYY') : 'N/A';

                                        function escapeHtml(text) {
                                            return $('<div>').text(text || '').html();
                                        }

                                        const meetLinkHtml = meetLink
                                            ? `<a href="${escapeHtml(meetLink)}" target="_blank" rel="noopener" style="color:#0d6efd; word-break:break-all;">${escapeHtml(meetLink)}</a>`
                                            : 'N/A';

                                        Swal.fire({
                                            icon: 'success',
                                            title: '✅ Your session has been successfully scheduled',
                                            html: `
                                                <div style="text-align:left; font-size:1rem; line-height:1.7;">
                                                    <p><strong>Client Name:</strong> ${escapeHtml(nombreCliente)}</p>
                                                    <p><strong>Session Date:</strong> ${escapeHtml(formattedDateFecha)}</p>
                                                    <p><strong>Session Time:</strong> ${escapeHtml(formattedDateHora)}</p>
                                                    <p><strong>Assigned Advisor:</strong> ${escapeHtml(vendedor)}</p>
                                                    <p style="margin-top:16px;"><strong>Here is the link for your video call:</strong></p>
                                                    <p>${meetLinkHtml}</p>
                                                    <p style="margin-top:16px;">We look forward to meeting with you at the scheduled date and time. It will be our pleasure to assist you!</p>
                                                </div>
                                            `,
                                            confirmButtonText: 'Ir a la página',
                                            customClass: {
                                                popup: 'swal2-popup-center'
                                            }
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                window.open('https://www.efegepho.com/confirmation', '_blank');

                                                // Si el usuario da clic en el botón 'Ir a la página', redirige a la URL que quieras
                                                // window.location.href = 'https://www.efegepho.com/confirmation'; // Reemplaza 'tu_pagina.html' por la URL que desees
                                            }
                                        });

                                    } else {
                                        // En caso de que no sea exitoso
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: 'Hubo un problema al procesar la solicitud.',
                                            customClass: {
                                                popup: 'swal2-popup-center'
                                            }
                                        });
                                    }
                                },
                                error: function (e) {
                                    // Muestra un mensaje de error en caso de fallo en la solicitud
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: 'Hubo un problema al enviar el correo.', customClass: {
                                            popup: 'swal2-popup-center'
                                        }
                                    });
                                    console.log(e); // Mostrar el error en consola
                                }
                            });
                        } else {
                            // Mostrar mensaje de error específico si existe
                            const errorMsg = serverData.error || "There was an issue submitting your registration. Please try again.";
                            const errorIcon = serverData.icon || "error"; // warning para validación, error para sistema
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
                                const errorData = parsePossiblyConcatenatedJson(error.responseText);
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
