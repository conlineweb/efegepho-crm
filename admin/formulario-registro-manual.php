<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos
// Verificar si 'id' está presente en la URL
if (isset($_GET['id'])) {
    $id = $_GET['id'];  // Obtener el valor de 'id' desde la URL
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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario de Registro</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        body {
            padding: 20px;
        }
        #next-month:hover{
            color:white;
        }
        #prev-month:hover{
            color:white;
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
    </style>
</head>
<body>
   <div class="container py-2">
    <h3 class="mb-4">Formulario de registro manual</h3>

    <div class="card">
        <div class="card-body">
            <form id="weddingForm">
                <div class="mb-3">
                    <label for="names" class="form-label">Your names</label>
                    <input type="text" name="names" class="form-control" placeholder="Your names" required>
                </div>
                <div class="mb-3">
                    <label for="wedding_location" class="form-label">Wedding location</label>
                    <input type="text" name="wedding_location" class="form-control" placeholder="Wedding location" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" name="email" class="form-control" placeholder="Email address" required>
                </div>
                <div class="mb-3">
                    <label for="wedding_date" class="form-label">Wedding Date</label>
                    <input type="date" class="form-control" id="wedding_date" name="wedding_date" onfocus="(this.type='date')" onblur="(this.type='text')" required>
                </div>
                <div class="mb-3">
                    <label for="country_code" class="form-label">Country Code</label>
<!--           <select id="country_code" name="country_code" class="form-select" required>-->
<!--  <option value="" disabled selected>Choose your country code</option>-->
<!--  <option value="+1">+1 (Estados Unidos)</option>-->
<!--  <option value="+52">+52 (México)</option>-->
<!--  <option value="+44">+44 (Reino Unido)</option>-->
<!--  <option value="+49">+49 (Alemania)</option>-->
<!--  <option value="+34">+34 (España)</option>-->
<!--  <option value="+33">+33 (Francia)</option>-->
<!--  <option value="+39">+39 (Italia)</option>-->
<!--  <option value="+81">+81 (Japón)</option>-->
<!--  <option value="+86">+86 (China)</option>-->
<!--  <option value="+91">+91 (India)</option>-->
<!--  <option value="+54">+54 (Argentina)</option>-->
<!--  <option value="+55">+55 (Brasil)</option>-->
<!--  <option value="+56">+56 (Chile)</option>-->
<!--  <option value="+57">+57 (Colombia)</option>-->
<!--  <option value="+58">+58 (Venezuela)</option>-->
<!--  <option value="+61">+61 (Australia)</option>-->
<!--  <option value="+64">+64 (Nueva Zelanda)</option>-->
<!--  <option value="+27">+27 (Sudáfrica)</option>-->
<!--  <option value="+20">+20 (Egipto)</option>-->
<!--  <option value="+972">+972 (Israel)</option>-->
<!--  <option value="+966">+966 (Arabia Saudita)</option>-->
<!--  <option value="+971">+971 (Emiratos Árabes Unidos)</option>-->
<!--</select>-->
            <select id="country_code" name="country_code" class="form-select" required>
            <option value="" disabled selected>Choose your country code</option>
          </select>
                </div>
                <div class="mb-3">
                    <label for="telephone" class="form-label">Telephone</label>
                    <input type="tel" name="telephone" class="form-control" placeholder="Telephone" required>
                </div>
                <div class="mb-3">
                    <label for="wedding_planner" class="form-label">Wedding Planner</label>
                    <input type="text" name="wedding_planner" class="form-control" placeholder="Wedding planner">
                </div>
                <div class="mb-3">
                    <label for="guests" class="form-label">How many guests will there be?</label>
                    <input type="number" name="guests" class="form-control" pattern="[0-9]{10}" placeholder="How many guests will there be?" required>
                </div>
                <div class="mb-3">
                    <label for="meet" class="form-label">How did you meet?</label>
                    <input type="text" name="meet" class="form-control" placeholder="How did you meet?">
                </div>
                <div class="mb-3">
                    <label for="couple_activities" class="form-label">What do you like to do as a couple?</label>
                    <input type="text" name="couple_activities" class="form-control" placeholder="What do you like to do as a couple?">
                </div>
                <div class="mb-3">
                    <label for="favorite_movie_song" class="form-label">Do you have a favorite movie or song as a couple?</label>
                    <input type="text" name="favorite_movie_song" class="form-control" placeholder="Do you have a favorite movie or song as a couple?">
                </div>
                <div class="mb-3">
                    <label for="photo_style" class="form-label">Do you like a documentary or editorial look?</label>
                    <input type="text" name="photo_style" class="form-control" placeholder="Do you like a documentary or editorial look?">
                </div>
                <!--<div class="mb-3">-->
                <!--    <label for="how_did_hear" class="form-label">How did you hear about us?</label>-->
                <!--    <input type="text" name="how_did_hear" class="form-control" placeholder="How did you hear about us?">-->
                <!--</div>-->
                <div class="mb-3">
                    <label for="how_did_hear" class="form-label">how did you hear about us?</label>
                   <select name="how_did_hear"  class="form-select" required>
                <option value="0" disabled selected>how did you hear about us?</option>
                <option value="1">Instagram</option>
                <option value="2">Google ads</option>
                <option value="3">Website</option>
                <option value="4">Wedding Planner</option>
                <option value="5">Recommendation</option>
            </select> 
                </div>
                <div class="mb-3">
                    <label for="instagram" class="form-label">Can you share your Instagram handles with us?</label>
                    <input type="text" name="instagram" class="form-control" placeholder="Can you share your Instagram handles with us?">
                </div>
                <div class="mb-3">
                    <label for="service" class="form-label">What service are you interested?</label>
                    <select name="service" class="form-select" required>
                        <option value="" disabled selected>What service are you interested?</option>
                        <option value="Social Media">Social Media</option>
                        <option value="Wedding Planner">Wedding Planner</option>
                        <option value="Personal Reference">Personal Reference</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="details" class="form-label">We would love to hear about your plans in as much detail as possible.</label>
                    <textarea name="details" class="form-control" placeholder="We would love to hear about your plans in as much detail as possible."></textarea>
                </div>

                <div class="text-center mb-5">
                    <h3>Schedule a call with one of our consultants to receive a quote</h3>
                </div>
                <div id="meet" class="mt-3">
                    <div class="calendar-container row">
                        <div class="left-panel col-12 col-md-6">
                            <div class="calendar-header d-flex justify-content-between align-items-center">
                                <button id="prev-month" class="btn btn-outline-primary">Previous Month</button>
                                <h2 id="current-month"></h2>
                                <button id="next-month" class="btn btn-outline-primary">Next Month</button>
                            </div>
                            <table id="calendar" class="table table-bordered">
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
                        <div class="right-panel col-12 col-md-6">
                            <div id="schedule-container">
                                <h3>Schedules for <span id="selected-date"></span></h3>
                                <ul id="schedule-list"></ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <button type="submit" class="btn btn-primary">Next</button>
                </div>
            </form>
        </div>
    </div>
</div>


    <?php include 'footer.php'; ?>
        <script src="admin/assets/extensions/@fortawesome/fontawesome-free/js/all.js" data-auto-replace-svg="nest"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
       <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/locale/es.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

 <script>
document.addEventListener("DOMContentLoaded", function() {
fetch('JS/countries_codes.json')
  .then(response => response.json())
  .then(data => {
      console.log(data)
      let countries = data.countries;
     const selectElement = document.getElementById('country_code');

    // // Ordenar los países alfabéticamente por nombre
    countries.sort((a, b) => a.name.localeCompare(b.name));

    // // Variable para asegurarse de que solo agregamos +1 una vez
    // let addedUS = false;
countries.forEach(country => {
     const option = document.createElement('option');
          option.value = country.code;
          option.textContent = `${country.code} (${country.name})`;
          selectElement.appendChild(option);
})
    // data.forEach(country => {
    //   const countryName = country.name.common;
    //   const countryCode = country.idd ? country.idd.root : undefined; // Verificar si `idd` existe
    //   const phoneCode = countryCode + (country.idd && country.idd.suffixes ? country.idd.suffixes.join('') : ''); // Concatenar solo si `idd` y `suffixes` existen

    //   // Filtrar países sin código telefónico o con valores indefinidos
    //   if (!phoneCode || phoneCode === 'undefined' || phoneCode === '') return;

    //   // Si encontramos Estados Unidos y no se ha añadido ya, lo agregamos solo con +1
    //   if (countryName === 'United States' && !addedUS) {
    //     const option = document.createElement('option');
    //     option.value = '+1';
    //     option.textContent = '+1 (United States)';
    //     selectElement.appendChild(option);
    //     addedUS = true;
    //   } else if (countryName !== 'United States') {
    //     // Para otros países, añadir solo el código principal y evitar subcódigos largos
    //     if (phoneCode.indexOf(countryCode) === 0) { // Solo incluir el código principal
        //   const option = document.createElement('option');
        //   option.value = phoneCode;
        //   option.textContent = `${phoneCode} (${countryName})`;
        //   selectElement.appendChild(option);
    //     }
    //   }
    // });
  })
  .catch(error => {
    console.error('Error al obtener los datos de los países:', error);
  });


    
 const diasBloqueados = <?php echo json_encode($dias_bloqueados); ?>;
  const diasBloqueadosEventos = <?php echo json_encode($dias_bloqueados_eventos); ?>;



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
                        confirmButtonText: 'Aceptar'
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
                
                 //document.getElementById('wedding_date').setAttribute('min', new Date().toISOString().split('T')[0]);
                 
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
        schedule.forEach(time => {
            const $listItem = $(`<li data-hour="${time}">`).text(time);
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
    formData.append('dia', selecteddate);
 
   let horas = [];

try {
    // Primer solicitud Ajax
    $.ajax({
        url: "chkDisponible2.php",
        type: "POST",
        data: formData,
        async:false,
         processData: false,  // No proceses los datos del FormData (importante para mantener el tipo FormData)
        contentType: false,  // No establezcas un content-type, ya que se manejará automáticamente
        success: function(horariosJson) {
          
            // Ahora hacemos otra solicitud con la fecha
            let form = new FormData();
            form.append('fecha', date);

            // Segunda solicitud Ajax
            $.ajax({
                url: "chkDisponible2.php",
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

                        vendor_horarios.forEach((horario)=>{
                            
                            if (times.length > 0) {
                                let hr = horario

                                times.forEach((time) => {
                                    console.log("time ",time);
                                        console.log("vendor ",vendor)

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
    // let formData = new FormData();
    // formData.append('dia', numeroDia);
    // let horas = [];

    // try {
    //     // Usamos await para esperar la respuesta de esta llamada
    //     const getAllHorasResponse = await fetch("chkDisponible.php", {
    //         method: "POST",
    //         body: formData,
    //     });

    //     const data = await getAllHorasResponse.json();

    //     // Ahora, hacemos otra petición con la fecha
    //     let form = new FormData();
    //     form.append('fecha', date);

    //     const getHorasResponse = await fetch("chkDisponible.php", {
    //         method: "POST",
    //         body: form,
    //     });

    //     const times = await getHorasResponse.json();
    //     let hrs = [];

    //     // Procesamos los datos
    //     data.forEach((vendor) => {
    //       vendor.forEach(()=>{})
    //         for (let i = inicio; i < fin; i++) {
    //             if (times.length > 0) {
    //                 let hr = i;
    //                 times.forEach((time) => {
    //                     if (time.horas == i && time.idusu == vendor.idusu) {
    //                         hr = 0;
    //                     }
    //                 });
    //                 if (hr > 0 && !hrs.includes(i)) {
    //                     hrs.push(i);
    //                 }
    //             } else {
    //                 if (!hrs.includes(i)) {
    //                     hrs.push(i);
    //                 }
    //             }
    //         }
    //     });

    //     // Ordenamos las horas
    //     hrs = hrs.sort(function(a, b) { return a - b; });

    //     // Creamos el HTML para las tarjetas
    //     for (let i = 0; i < hrs.length; i++) {
    //         horas.push(`${hrs[i]}:00`);
    //     }

    //     console.log("horas ", horas);
    //     return horas;
    // } catch (error) {
    //     console.error("Error:", error);
    // }
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

    form.addEventListener("submit", function(event) {
            
            //aqui se mandan los datos
        
        event.preventDefault();

        // Recolectar los datos del formulario
        const formData = new FormData(form);
        
            // formData.append('date_appointment', info.startStr);
       

const timestamp = new Date();
// const soloFecha = timestamp.toLocaleDateString(calendar.state.dateSelection.range.end);
// console.log(soloFecha);
        formData.append('date_appointment', selecteddate);
        formData.append('time_appointment', $('li.selected').data('hour'));
        // Identificadores para registro manual
        formData.append('form_name', 'reg manual');
        formData.append('campaign_name', 'reg manual');
    const loadingSwal = Swal.fire({
        title: 'Submitting...',
        text: 'Please wait while we process your request.',
        allowOutsideClick: false, // Prevenir que el usuario cierre el alerta
        didOpen: () => {
            Swal.showLoading(); // Mostrar el spinner de carga
        }
    });
        // Enviar datos vía AJAX falta agregarle la hora seleccionada desde los cards
        
 $.ajax({
    url: "enviodatosform2.php",
    type: "POST",
    data: formData,
    processData: false,  // No procesar los datos
    contentType: false,  // No cambiar el tipo de contenido
    success: function(data) {
        // Cerrar el spinner de carga
        if (data.success == "full") {
            // Mostrar mensaje de cita no disponible
            Swal.fire({
                title: "Time unavailable",
                text: "The hour you've selected has already been occupied. Select another hour. Sorry for the inconvenience.",
                icon: "error",
                confirmButtonText: "OK"
            });
        } else if (data.success) {
 
            console.log("data form", data);

            // Segunda solicitud Ajax
            $.ajax({
                url: 'enviaCorreo.php',
                type: 'POST',
                data: data,
                success: function(resu) {
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
                        console.log('hora ',hora)
                        var formattedDateHora = (hora && hora.trim()) ? moment(hora, 'HH:mm:ss').format('hh:mm a') : "No disponible";
                        var formattedDateFecha = (fecha && fecha.trim()) ? moment(fecha).format('DD-MM-YYYY') : "No disponible";

                        // Muestra un SweetAlert con el mensaje, la fecha y el nombre del vendedor
                        Swal.fire({
                            icon: 'success',
                            title: res.message,  // Muestra el mensaje principal
                            html: `Vendedor: ${vendedor}<br>Fecha: ${formattedDateFecha}<br>Hora: ${formattedDateHora}<br><br><h3>${lastMessage}</h3>`, // Muestra la fecha, hora, nombre del vendedor y el último mensaje
                            confirmButtonText: 'Ir a la página', // Cambia el texto del botón
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Si el usuario da clic en el botón 'Ir a la página', redirige a la URL que quieras
                                window.location.href = 'formulario-registro-manual.php'; // Reemplaza 'tu_pagina.html' por la URL que desees
                            }
                        });

                    } else {
                        // En caso de que no sea exitoso
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Hubo un problema al procesar la solicitud.',
                        });
                    }
                },
                error: function(e) {
                    // Muestra un mensaje de error en caso de fallo en la solicitud
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al enviar el correo.',
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
                confirmButtonText: "OK"
            });
        }
    },
    error: function(error) {
         loadingSwal.close(); // Cerrar el spinner de carga
        console.error("Error:", error);
        Swal.fire({
            title: "Error",
            text: "An unexpected error occurred. Please try again later.",
            icon: "error",
            confirmButtonText: "OK"
        });
    }
});

    });
    

            }

});

</script>

</body>
</html>