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
</style>

<body>

    <div class=" container-sm mt-4">
         <div class="d-flex mt-5">
       <a href="https://www.efegepho.com/"> <!-- Cambia el href al destino que desees -->
  <img src="https://cdn.prod.website-files.com/606278172c257c8e5ae0463c/62e1beaf3e61f6ff41a188e7_efege-bk.svg" alt="EFEGE" height="45">
</a>

    </div>
       <h1 class=" mt-5 text-center">INQUIRE</h1>
        <form id="weddingForm" class="form-wrapper">
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
                        <label class="form-label">Email address</label>
                        <input type="email" name="email" placeholder="email address" value="<?php echo $pref_email ?? ''; ?>" required>
                    </div>
                </div>
                <div class="col-12 col-md-6 mb-4">
                    <div class="d-flex flex-column h-100 justify-content-end">
                        <label class="form-label">Confirm email address</label>
                        <input type="email" name="confirm_email" placeholder="confirm email address" required>
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
                        <?php
                        $pref_how_long_raw = !empty($lead_data) ? ($lead_data['how_long_known_us'] ?? '') : '';
                        $pref_how_long_is_not_asked = in_array($pref_how_long_raw, ['Not asked', 'Sin dato', 'Todavía no sabemos'], true);
                        ?>
                        <option value="" disabled <?php echo ($pref_how_long_raw === '') ? 'selected' : ''; ?>>Select...</option>
                        <option value="Less than 6 months" <?php echo ($pref_how_long_raw === 'Less than 6 months') ? 'selected' : ''; ?>>Less than 6 months</option>
                        <option value="More than 6 months" <?php echo ($pref_how_long_raw === 'More than 6 months') ? 'selected' : ''; ?>>More than 6 months</option>
                        <option value="Not asked" <?php echo $pref_how_long_is_not_asked ? 'selected' : ''; ?>>We still don't know</option>
                    </select>
                </div>
            </div>
            <!-- removed: Where did you hear about us? (moved to metadata/source) -->




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

            <!-- Contenedor para el botón NEXT con margen adecuado -->
            <div class="text-center mt-5 mb-5" style="padding: 20px;">
                <div class="mb-3" style="font-size:12px; text-align:center; max-width:800px; margin:0 auto; color:#666;">
                    <p>By continuing, you agree to the <a href="https://www.efegepho.com/terms-conditions-and-privacy-policy-provided" target="_blank" rel="noopener">Terms &amp; Conditions and Privacy Policy provided by Efegepho</a>. By providing your phone number, you consent to receive text messages and phone calls from Efegepho.</p>
                </div>
                <button type="submit" class="btn-submit" id="nextButton">NEXT</button>
            </div>
        </form> 
    </div>

</body>

</html>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script src="admin/assets/extensions/@fortawesome/fontawesome-free/js/all.js" data-auto-replace-svg="nest"></script>
<script src="admin/assets/extensions/jquery/jquery.js"></script>
<script src="admin/assets/extensions/jquery-ui/jquery-ui.min.js"></script>

<script src='https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/moment-timezone@0.5.45/builds/moment-timezone-with-data-1970-2030.min.js"></script>
<script>
    let lead_data = <?php echo json_encode($lead_data); ?>;

    console.log("lead_data desde php", lead_data);





    const timezoneOffsetMinutes = new Date().getTimezoneOffset();
    const clientUTCOffset = -timezoneOffsetMinutes / 60; // Offset del cliente respecto a UTC
    
    // Detectar nombre de zona horaria IANA (ej. "America/Mexico_City")
    const timezoneName = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    
    // Vendedor está en US Central Time (UTC-6)
    const vendorUTCOffset = -6;
    
    // Calcular diferencia relativa: cliente - vendedor
    const timezone = clientUTCOffset - vendorUTCOffset;

    // Para pruebas: Forzar una zona horaria diferente (ej. UTC+1, comenta esta línea en producción)
    // Ejemplo: UTC+1 (Europa)

    console.log('Cliente UTC offset:', clientUTCOffset);
    console.log('Vendedor UTC offset:', vendorUTCOffset);
    console.log('Diferencia de zona horaria (cliente - vendedor):', timezone);
    console.log('Nombre de zona horaria:', timezoneName);

    const SELLER_TIMEZONE = 'Etc/GMT+6';
    const SELLER_UTC_OFFSET_MINUTES = -360;

    function isValidIanaTimeZone(tzName) {
        if (!tzName || typeof tzName !== 'string') {
            return false;
        }
        try {
            Intl.DateTimeFormat('en-US', { timeZone: tzName }).format();
            return true;
        } catch (error) {
            return false;
        }
    }

    function detectClientTimezone() {
        let resolvedName = '';
        try {
            resolvedName = (typeof Intl !== 'undefined' && Intl.DateTimeFormat)
                ? Intl.DateTimeFormat().resolvedOptions().timeZone
                : '';
        } catch (error) {
            resolvedName = '';
        }

        if (!isValidIanaTimeZone(resolvedName)) {
            resolvedName = isValidIanaTimeZone(timezoneName) ? timezoneName : '';
        }

        return {
            timezone_name: resolvedName,
            timezone_offset_min: timezoneOffsetMinutes,
            timezone_offset_hours: clientUTCOffset
        };
    }

    function normalizeSlotTime(timeText) {
        const raw = String(timeText || '').trim();
        if (raw === '') {
            return '';
        }

        const match = raw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
        if (match) {
            return match[1].padStart(2, '0') + ':' + match[2].padStart(2, '0');
        }

        if (/^\d{1,2}$/.test(raw)) {
            return raw.padStart(2, '0') + ':00';
        }

        return '';
    }

    function parseVendorHorarios(raw) {
        if (Array.isArray(raw)) {
            return raw;
        }
        if (typeof raw !== 'string' || raw.trim() === '') {
            return [];
        }
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function isSlotOccupied(slotHorario, occupiedTimes) {
        const slotNormalized = normalizeSlotTime(slotHorario);
        if (!slotNormalized || !Array.isArray(occupiedTimes) || occupiedTimes.length === 0) {
            return false;
        }

        return occupiedTimes.some(function (entry) {
            return normalizeSlotTime(entry.hora) === slotNormalized;
        });
    }

    function collectAvailableSlots(vendorHorarios, occupiedTimes) {
        const hrs = [];

        vendorHorarios.forEach(function (horario) {
            const slotTime = normalizeSlotTime(horario);
            if (!slotTime || isSlotOccupied(horario, occupiedTimes) || hrs.includes(slotTime)) {
                return;
            }
            hrs.push(slotTime);
        });

        hrs.sort(function (a, b) {
            const partsA = a.split(':');
            const partsB = b.split(':');
            return (parseInt(partsA[0], 10) * 60 + parseInt(partsA[1], 10)) - (parseInt(partsB[0], 10) * 60 + parseInt(partsB[1], 10));
        });

        return hrs;
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

    function getDatePartsForTimezone(dateObj, tzName) {
        const formatter = new Intl.DateTimeFormat('en-CA', {
            timeZone: tzName,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
        const parts = formatter.formatToParts(dateObj).reduce(function (acc, part) {
            if (part.type !== 'literal') {
                acc[part.type] = part.value;
            }
            return acc;
        }, {});

        return {
            date: parts.year + '-' + parts.month + '-' + parts.day,
            time: parts.hour + ':' + parts.minute
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
            const sellerMoment = window.moment.tz(dateText + ' ' + normalizedSellerTime, 'YYYY-MM-DD HH:mm', SELLER_TIMEZONE);
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
        const fallbackDateText = fallbackDate.getUTCFullYear() + '-' + String(fallbackDate.getUTCMonth() + 1).padStart(2, '0') + '-' + String(fallbackDate.getUTCDate()).padStart(2, '0');
        const fallbackTimeText = String(fallbackDate.getUTCHours()).padStart(2, '0') + ':' + String(fallbackDate.getUTCMinutes()).padStart(2, '0');
        return {
            clientDate: fallbackDateText,
            clientTime: fallbackTimeText,
            isSameAsSeller: fallbackDateText === dateText && fallbackTimeText === normalizedSellerTime
        };
    }

    document.addEventListener("DOMContentLoaded", function () {
        // Real-time validation for email confirmation
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

        // Also validate when the main email changes
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

            let currentDate = new Date();

            function generateCalendar(month, year) {
                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                const daysInMonth = lastDay.getDate();
                const startDay = firstDay.getDay(); // 0 = Domingo, 1 = Lunes, ...

                // Fecha actual
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const minDate = new Date(today);
                minDate.setDate(today.getDate() + 1); // Primer día habilitado: mañana
                const maxDate = new Date(today);
                maxDate.setDate(today.getDate() + 3); // Último día habilitado: 3 días después de hoy
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
                            const currentDate = new Date(year, month, date);
                            currentDate.setHours(0, 0, 0, 0);
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

                const $dayCells = $calendar.find('td[data-day]:not(.disabled):not(.blocked)');
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
                    schedule.forEach(function (time) {
                        const sellerTime = normalizeSlotTime(time);
                        if (!sellerTime) {
                            return;
                        }
                        const convertedSlot = convertSellerSlotToClientLocal(selecteddate, sellerTime, clientTzInfo);
                        const displayTime = convertedSlot.clientTime + ' (Local Time)';
                        const $listItem = $('<li>')
                            .attr('data-hour', sellerTime)
                            .attr('data-hourcust', convertedSlot.clientTime)
                            .attr('data-datecust', convertedSlot.clientDate)
                            .text(displayTime);
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
                                    let horarios = typeof horariosJson === 'string' ? JSON.parse(horariosJson) : horariosJson;
                                    horarios.forEach(function (vendor) {
                                        const vendorHorarios = parseVendorHorarios(vendor.horarios);
                                        const vendorOccupied = Array.isArray(times)
                                            ? times.filter(function (time) {
                                                return String(time.idusu) === String(vendor.idusu);
                                            })
                                            : [];

                                        collectAvailableSlots(vendorHorarios, vendorOccupied).forEach(function (slotTime) {
                                            if (!hrs.includes(slotTime)) {
                                                hrs.push(slotTime);
                                            }
                                        });
                                    });

                                    hrs.sort(function (a, b) {
                                        const partsA = a.split(':');
                                        const partsB = b.split(':');
                                        return (parseInt(partsA[0], 10) * 60 + parseInt(partsA[1], 10)) - (parseInt(partsB[0], 10) * 60 + parseInt(partsB[1], 10));
                                    });

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

            form.addEventListener("submit", function (event) {
                event.preventDefault();
                // Lista de fields que quieres validar (incluye email y confirm_email)
                const campos = [
                    'email',
                    'confirm_email',
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

                // Validar que los correos coincidan
                const email = $('[name="email"]').val();
                const confirmEmail = $('[name="confirm_email"]').val();
                if (email !== confirmEmail) {
                    $('[name="confirm_email"]').css("border", "1px solid red");
                    $('#email-error').show();
                    valido = false;
                }

                if (!valido) {
                    return;
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
                // timezone ya representa la diferencia: cliente - vendedor
                // Si timezone = 0, entonces misma zona, no ajustar
                // Si timezone = 1, entonces cliente está 1 hora adelante
                const dif = timezone;
                const dateWithTimezone = new Date(selectedDateObj.getTime() + (dif * 60 * 60 * 1000));
                const year = dateWithTimezone.getFullYear();
                const month = String(dateWithTimezone.getMonth() + 1).padStart(2, '0');
                const day = String(dateWithTimezone.getDate()).padStart(2, '0');
                const dateCustFormatted = `${year}-${month}-${day}`;

                console.log('selecteddate:', selecteddate);
                console.log('timezone:', timezone);
                console.log('dif (horas):', dif);
                console.log('dateCustFormatted:', dateCustFormatted);

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
                const dateCustFromSlot = String(selectedSlot.data('datecust') || '');

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

                formData.append('date_appointment', selecteddate);
                formData.append('time_appointment', selectedHourVendor);
                formData.append('time_appointment_cust', selectedHourClient);
                formData.append('date_appointment_cust', dateCustFromSlot || dateCustFormatted);
                formData.append('desde_publicidad', 0);
                formData.append('zona_horaria', timezone);
                formData.append('timezone_offset_min', timezoneOffsetMinutes); // Enviar offset en minutos para cálculo UTC
                formData.append('timezone_name', timezoneName); // Nombre IANA de la zona horaria
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


                            // Segunda solicitud Ajax
                            $.ajax({
                                url: 'admin/enviaCorreo.php',
                                type: 'POST',
                                data: data,
                                success: function (resu) {
                                    console.log(resu)
                                    loadingSwal.close();

                                    let res = JSON.parse(resu);
                                    console.log(res);

                                    // Verifica si el estado es 'success'
                                    if (res.status === 'success') {
                                        const vendedor = res.data.vendedor || 'N/A';
                                        const nombreCliente = res.data.nombre_cliente || $('[name="names"]').val() || 'N/A';
                                        const fecha = res.data.fecha;
                                        const hora = res.data.hora;
                                        const meetLink = res.data.enlace_meet || '';
                                        var formattedDateHora = (hora && hora.trim()) ? moment(hora, 'HH:mm:ss').format('hh:mm A') : 'N/A';
                                        var formattedDateFecha = (fecha && fecha.trim()) ? moment(fecha).format('MMMM D, YYYY') : 'N/A';

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
                                                // window.location.href = 'https://www.efegepho.com/'; // Reemplaza 'tu_pagina.html' por la URL que desees
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