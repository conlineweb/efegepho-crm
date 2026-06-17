<?php
include 'conn.php';

$idVendedor = 0;
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
    }else{
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
   <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;700&display=swap" rel="stylesheet">
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

</head>
<style>
   #wedding_date {
    width: 100%!important;
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
            appearance: none; /* Elimina el estilo por defecto del select */
            -webkit-appearance: none; /* Compatibilidad con navegadores WebKit */
            -moz-appearance: none; /* Compatibilidad con navegadores Mozilla */
            color: #000; /* Color del texto */
        }

        select:focus {
            border-bottom: 1px solid #000; /* Cambia el color del borde al enfocar */
        }

        /* Estilo para la flechita */
        select::after {
            content: '\25BC'; /* Código Unicode para la flecha */
            position: absolute;
            color: #000;
            right: 10px;
            pointer-events: none; /* Evita que interfiera con el clic */
        }

        /* Para que el placeholder se muestre al inicio */
        select option[value=""][disabled] {
            display: none;
        }

.country_code {
  width: 15%;
}

form {
   
    font-family: 'Cormorant Garamond', serif;
}
input, textarea, button {
  
    border: none;
    border-bottom: 1px solid #ccc;
    font-size: 18px;
    outline: none;
     background: none;
    font-family: 'Cormorant Garamond', serif;
}

input[name="names"],
textarea,
button {
    width: 100%;
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

  

  @media (max-width: 600px) {
    input::placeholder,
    textarea::placeholder {
      
        font-size: 12px; /* Tamaño de la fuente */
    }
    
    .wedding_date_label{
    
    width: 300px !important;
    margin: 25px;
    font-size: 12px !important;  /* Tamaño de la fuente */
  } 
  
  .wedding_date {
    
    width: 50% !important;
     
    
    font-size: 12px !important;  /* Tamaño de la fuente */
  }
  
  .country_code {
  width: 30%;
}



 select {
            padding: 10px;
            border: none;
            border-bottom: 1px solid #ccc;
             font-size: 12px !important;  /* Tamaño de la fuente */
            outline: none;
            background: none;
            font-family: 'Cormorant Garamond', serif;
            appearance: none; /* Elimina el estilo por defecto del select */
            -webkit-appearance: none; /* Compatibilidad con navegadores WebKit */
            -moz-appearance: none; /* Compatibilidad con navegadores Mozilla */
            color: #909090; /* Color del texto */
        }

}

.calendar-container {
    display: flex;
    width: 800px; /* Ajusta el ancho según tus necesidades */
    margin: 20px auto;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    border-radius: 5px;
    overflow: hidden; /* Para que el borde redondeado se aplique correctamente */
}

.left-panel {
    width: 60%; /* Ajusta la proporción según tus necesidades */
    background-color: #fff;
    padding: 20px;
}

.right-panel {
    width: 40%; /* Ajusta la proporción según tus necesidades */
    background-color: #f9f9f9;
    padding: 20px;
    border-left: 1px solid #eee;
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
}

th, td {
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

td.selected{
    background-color: #edd896;
   
}

/* Contenedor de horarios */
#schedule-container {
    padding: 20px;
}

#schedule-container h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
}

#schedule-list {
    list-style: none;
    padding: 0;
     font-size:1.1rem;
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
    display: flex; /* Para alinear hora e icono */
    align-items: center; /* Alinear verticalmente */
    gap: 10px; /* Espacio entre hora e icono */
}

#schedule-list li span { /* Estilo para la hora */
    font-size: 16px;
    color: #555;
}

/* Icono para los horarios (usando Font Awesome) */
#schedule-list li::before {
    font-family: "Font Awesome 5 Free";
    content: "\f017"; /* Icono de reloj */
    font-weight: 900; /* Para que se vea el icono */
}

/* Opcional: Hover effect en los horarios */
#schedule-list li:hover {
    background-color: #f0f0f0;
    cursor: pointer;
}

/* Estilos para horarios seleccionados */
#schedule-list li.selected {
    background-color: #e0f2f7; /* Color de fondo claro */
    color: #007bff; /* Color de texto azul */
    font-weight: bold; /* Texto en negrita (opcional) */
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
    }

    .left-panel,
    .right-panel {
        width: 100%;
        padding: 15px;
    }

    /* Encabezado */
    .calendar-header h2 {
        font-size: 20px;
    }

    .calendar-header button {
        font-size: 14px;
    }

    /* Tabla del calendario */
    th, td {
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
}

/* Media queries para pantallas extra pequeñas (móviles en modo retrato) */
@media (max-width: 480px) {
    .calendar-header h2 {
        font-size: 18px;
    }

    .calendar-header button {
        font-size: 12px;
    }

    th, td {
        padding: 8px;
        font-size: 12px;
    }

    #schedule-container {
        padding: 10px;
    }

    #schedule-list li {
        font-size: 12px;
        padding: 6px 0;
    }

    #schedule-list li span {
        font-size: 12px;
    }

    /* Redimensionar iconos */
    #schedule-list li::before {
        font-size: 14px;
    }
    @media (max-width: 768px) {
    .swal2-popup {
        margin-bottom: 200px !important; 
    }
}
 #country_code {
      width: 250px; /* Establece un ancho fijo */
      max-width: 100%; /* Asegura que no se expanda más allá del contenedor */
      overflow-x: auto; /* Añade un desplazamiento horizontal si el contenido es demasiado largo */
    }
/* Añade esto a tu CSS */
@supports (-webkit-touch-callout: none) {
    /* Estilos específicos para iOS */
    select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        padding-right: 30px; /* Espacio para la flecha */
        background-image: url('data:image/svg+xml;utf8,<svg fill="black" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
        background-repeat: no-repeat;
        background-position: right center;
    }
    
    /* Ajustes para el calendario en iOS */
    .calendar-container {
        -webkit-overflow-scrolling: touch; /* Mejor scroll en iOS */
    }
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
       <h1 class="mb-5 mt-5 text-center">INQUIRE</h1>
     <form id="weddingForm" class="">
         <h3 class="text-center m-5"> For the wildly in love</h3>
         <div class="row">
             <div class="col-12 col-md-6 mb-5">
                            <div class="d-flex flex-column h-100 justify-content-end">
                                <input type="text" name="names" placeholder="your names" >
                            </div>
                        </div>
                         <div class="col-12 col-md-6 mb-5">
                            <div class="d-flex flex-column h-100 justify-content-end">
                                 <input type="email" name="email" placeholder="email address" >

                            </div>
                        </div>
         </div>
         <div class="row">
             <div class="col-12 col-md-6 mb-5">
                            <div class="d-flex flex-column h-100 justify-content-end">
                                   <label>Choose your country code</label>
            <select id="country_code" name="country_code" >
                <option value="" disabled selected>Select your country code &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; &#709;</option>
                <!--<option value="" disabled selected>Choose your country code</option>-->
            </select>
                            </div>
                        </div>
                         <div class="col-12 col-md-6 mb-5">
                            <div class="d-flex flex-column h-100 justify-content-end">
                              <input type="tel" name="telephone" placeholder="telephone" >

                            </div>
                        </div>
         </div>
         
<div class="row">
             <div class="col-12 col-md-6 mb-5">
                            <div class="d-flex flex-column h-100 justify-content-end">
                                   <select name="how_did_hear" >
            <option value="0" disabled selected>Where did you hear about us?</option>
             <option value="1">Instagram</option>
            <option value="2">Google ads</option>
            <option value="3">Website</option>
            <option value="4">Wedding Planner</option>
            <option value="5">Recommendation</option>
            <option value="6">Referral from a bride / planner</option>
            <option value="7">Article</option>
            <option value="8">Search Engine</option>
            <option value="9">Other</option>
        </select>
                            </div>
                        </div>
                         
         </div>
 
       
    
    <div style="width:100%">
              <h3 class="text-center m-5">Schedule your personalized event consultation</h3>

        <div class="mt-1" id="meet">
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
                         <p class="text-center">(Time Zone: America/Mexico_City) (UTC-6)</p>
                        <ul id="schedule-list"></ul>
                    </div>
                </div>
            </div>
        </div>
              

    </div> <!-- Cierre de la sección de calendario -->

    <br><br>
    <div class="mb-3" style="font-size:12px; text-align:center; max-width:800px; margin:0 auto; color:#666;">
        <p>By continuing, you agree to the <a href="https://www.efegepho.com/terms-conditions-and-privacy-policy-provided" target="_blank" rel="noopener">Terms &amp; Conditions and Privacy Policy provided by Efegepho</a>. By providing your phone number, you consent to receive text messages and phone calls from Efegepho.</p>
    </div>
    <button type="submit" id="nextButton">NEXT</button>
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
<script src="geo_timezone.js"></script>
<script>






 const timezoneOffsetMinutes = new Date().getTimezoneOffset();
    const timezone = -timezoneOffsetMinutes / 60; // Convertir a horas con signo opuesto
    
    console.log('Zona horaria detectada:', timezone);
// timezone = <?=$response->gmt_offset?>;
document.addEventListener("DOMContentLoaded", function() {
// Pedir ubicación al entrar a la vista (no esperar al envío)
(async () => {
    await window.blockIfNoTimezone();
})();

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
        {code: '+1', name: 'United States'},
        {code: '+44', name: 'United Kingdom'},
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


console.log("diasBloqueados ",diasBloqueados)
 // Convertir las fechas bloqueadas a un set para hacer la búsqueda más eficiente
let blockedDatesSet = new Set(diasBloqueadosEventos);

// Función que verifica si una fecha está bloqueada
function isBlocked(date) {
    return blockedDatesSet.has(date);
}



  $("#wedding_date").on('change', function(e) {
              let selectedDate = event.target.value; // Fecha seleccionada por el usuario
        
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
        event.target.value = '';
         
    }else{}
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
    console.log("showSchedule day",day)
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
    console.log("schedule ",schedule);

    $scheduleList.empty();

    if (schedule && schedule.length > 0) {
        const local = 6; // UTC-6 Mexico
        const dif = local + timezone;
        schedule.forEach(time => {
            let displayTime = time + " (Mexico City)";
            let custTime = time;
            if (timezone !== -6) {
                const tz = time.split(":");
                const hour = parseInt(tz[0]);
                const custHour = hour + dif;
                custTime = `${custHour}:00`;
                displayTime = `${custHour}:00 (Local Time)`;
            }
            const $listItem = $(`<li data-hour="${time}" data-hourcust="${custTime}">`).text(displayTime);
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
        console.log("date ",date)
    formData.append('dia', date);
 
   let horas = [];

try {
    // Primer solicitud Ajax
    $.ajax({
        url: "chkDisponible.php",
        type: "POST",
        data: formData,
        async:false,
         processData: false,  // No proceses los datos del FormData (importante para mantener el tipo FormData)
        contentType: false,  // No establezcas un content-type, ya que se manejará automáticamente
        success: function(horariosJson) {
          console.log("horariosJson ",horariosJson)
            // Ahora hacemos otra solicitud con la fecha
            let form = new FormData();
            form.append('fecha', date);

            // Segunda solicitud Ajax
            $.ajax({
                url: "chkDisponible.php",
                type: "POST",
                data: form,   
                async:false,
                dataType:"json",
                 processData: false,  // No proceses los datos del FormData (importante para mantener el tipo FormData)
                contentType: false,  // No establezcas un content-type, ya que se manejará automáticamente
                success: function(times) {
                   let hrs = [];
                   let horarios =  JSON.parse(horariosJson)
                    horarios.forEach((vendor) => {
                        let vendor_horarios = JSON.parse(vendor.horarios);
                        console.log("vendor_horarios ",vendor_horarios)

                        vendor_horarios.forEach((horario)=>{
                            
                            if (times.length > 0) {
                                let hr = horario

                                times.forEach((time) => {
                                    if (time.hora == `${horario}:00` && time.idusu == vendor.idusu) {
                                        console.log(time.hora +" es igual a "+`${horario}:00`)
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
                    hrs.sort(function(a, b) {
                        return convertToMinutes(a) - convertToMinutes(b);
                    });


                    // Creamos el HTML para las tarjetas
                    for (let i = 0; i < hrs.length; i++) {
                        horas.push(hrs[i]);
                    }

                    console.log("horas ", horas);
                   
                },
                error: function(xhr, status, error) {
                    console.error("Error en la segunda solicitud Ajax:", error);
                }
            });
        },
        error: function(xhr, status, error) {
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

    form.addEventListener("submit", async function(event) {
        event.preventDefault();
         // Lista de names que quieres validar
const campos = [
  
    'names',
 
    'email',
    'country_code',
    'telephone',
  
];

let valido = true;

campos.forEach(function(name) {
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

if (!valido) {
    return;
}


    

        // Ubicación requerida para confirmar zona horaria real
        const okTZ = await window.blockIfNoTimezone();
        if (!okTZ) return;
        const confirmedTZ = await window.requireConfirmedTimezone();

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
        
        // Calcular la diferencia en horas entre la zona horaria del usuario y la zona del servidor (UTC-6)
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
        formData.append('time_appointment', $('li.selected').data('hour'));
        formData.append('time_appointment_cust', $('li.selected').data('hourcust'));
        formData.append('date_appointment_cust', dateCustFormatted);
        formData.append('desde_publicidad',1);
                // Añadir zona horaria IANA y offset en minutos para mayor precisión
                formData.append('timezone_name', confirmedTZ.timezone_name || '');
                formData.append('timezone_offset_min', (confirmedTZ.timezone_offset_min ?? 0));
            formData.append('zona_horaria', (confirmedTZ.timezone_offset_hours ?? 0));
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
    success: function(data) {
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
                success: function(resu) {
                    console.log(resu)
                                          loadingSwal.close();

                    let res = JSON.parse(resu);
                    console.log(res);

                    // Verifica si el estado es 'success'
                    if (res.status === 'success') {
                        // Obtiene la fecha y el nombre del vendedor
                        const vendedor = res.data.vendedor;
                        const fecha = res.data.fecha;
                        const hora = res.data.hora;
                        const lastMessage = res.data.lastMessage;
                        var formattedDateHora = (hora && hora.trim()) ? moment(hora, 'HH:mm:ss').format('hh:mm a') : "No disponible";
                        var formattedDateFecha = (fecha && fecha.trim()) ? moment(fecha).format('DD-MM-YYYY') : "No disponible";

                        // Muestra un SweetAlert con el mensaje, la fecha y el nombre del vendedor
                        Swal.fire({
                            icon: 'success',
                            title: res.message,  // Muestra el mensaje principal
                            html: `Vendedor: ${vendedor}<br>Fecha: ${formattedDateFecha}<br>Hora: ${formattedDateHora}<br><br><h3>${lastMessage}</h3>`, // Muestra la fecha, hora, nombre del vendedor y el último mensaje
                            confirmButtonText: 'Ir a la página', // Cambia el texto del botón
                            
        customClass: {
        popup: 'swal2-popup-center'
    }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.open('https://www.efegepho.com/', '_blank');

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
                error: function(e) {
                    // Muestra un mensaje de error en caso de fallo en la solicitud
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al enviar el correo.',customClass: {
        popup: 'swal2-popup-center'
    }
                    });
                    console.log(e); // Mostrar el error en consola
                }
            });
        } else {
            // Mostrar mensaje de error
            Swal.fire({
                title: "Error",
                text: "There was an issue submitting your registration. Please try again.",
                icon: "error",
                confirmButtonText: "OK",customClass: {
        popup: 'swal2-popup-center'
    }
            });
        }
    },
    error: function(error) {
        loadingSwal.close();

        console.error("Error:", error);
        Swal.fire({
            title: "Error",
            text: "An unexpected error occurred. Please try again later.",
            icon: "error",
            confirmButtonText: "OK",customClass: {
        popup: 'swal2-popup-center'
    }
        });
    }
});

    });
    

}

});

</script>
 