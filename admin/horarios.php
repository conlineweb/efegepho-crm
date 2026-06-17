<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

$userid = $_SESSION['uid'];
$tipoUsuario = $_SESSION['tus'];



if($tipoUsuario == "0"){
    
// Consulta los usuarios de la tabla "usuarios"
$sqlUsuarios = "SELECT * FROM usuarios where tipoUsu = 1;"; // Aquí puedes seleccionar los campos que necesites
$resultUsuarios = $conn->query($sqlUsuarios);

// Almacena los datos de los usuarios en un arreglo
$usuarios = [];
if ($resultUsuarios->num_rows > 0) {
    while ($row = $resultUsuarios->fetch_assoc()) {
        $usuarios[] = $row;
    }
}
$queryHorarios = "SELECT * FROM atencion";
$resultHorarios = mysqli_query($conn, $queryHorarios);

// Verificar si la consulta tuvo resultados
if ($resultHorarios) {
    $horarios = [];
    while ($row = mysqli_fetch_assoc($resultHorarios)) {
        // Guardar todo el registro (todos los campos)
        $horarios[] = $row;
    }
    
    // Devolver los datos en formato JSON
    $horariosJson = json_encode($horarios);

   
}
}
if($tipoUsuario == "1"){
// Prepara la consulta con un parámetro para idusu
$queryHorarios = "SELECT * FROM atencion WHERE idusu = ?";
$stmt = $conn->prepare($queryHorarios);

// Enlaza el parámetro idusu con el valor de $userid
$stmt->bind_param("i", $userid); // 'i' para entero

// Ejecuta la consulta
$stmt->execute();

// Obtiene el resultado
$resultHorarios = $stmt->get_result();

// Almacena los datos en un arreglo asociativo
if ($resultHorarios->num_rows > 0) { // Verifica si hay resultados
    $horarios = [];
    while ($row = $resultHorarios->fetch_assoc()) { // Usamos fetch_assoc() directamente
        // Guardar todo el registro (todos los campos)
        $horarios[] = $row;
    }

    // Devolver los datos en formato JSON
    $horariosJson = json_encode($horarios);
} else {
    $horariosJson = json_encode([]); // En caso de que no haya resultados
}




// Consulta los usuarios de la tabla "usuarios"
$sqlUsuarios = "SELECT * FROM usuarios WHERE tipoUsu != 0 AND id = ?"; // Aquí puedes seleccionar los campos que necesites

// Prepara la consulta
$stmtUsuarios = $conn->prepare($sqlUsuarios);

// Vincula el parámetro id
$stmtUsuarios->bind_param("i", $userid); // 'i' para entero (puedes usar la variable $userid que ya tienes)

$stmtUsuarios->execute(); // Ejecuta la consulta

// Obtiene el resultado
$resultUsuarios = $stmtUsuarios->get_result();

// Almacena los datos de los usuarios en un arreglo
$usuarios = [];
if ($resultUsuarios->num_rows > 0) {
    while ($row = $resultUsuarios->fetch_assoc()) {
        $usuarios[] = $row;
    }
}



}

// Consulta SQL para obtener todos los campos de la tabla dias_bloqueados
$queryDiasBloqueados = "SELECT * FROM dias_bloqueados";
$resultDiasBloqueado = mysqli_query($conn, $queryDiasBloqueados);

// Verificar si la consulta tuvo resultados
if ($resultDiasBloqueado) {
    $dias_bloqueados = [];
    while ($row = mysqli_fetch_assoc($resultDiasBloqueado)) {
        // Guardar todo el registro (todos los campos)
        $dias_bloqueados[] = $row['fecha'];
    }
    
    // Devolver los datos en formato JSON
   
}







?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programación de Horarios</title>
     <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
</head>
<style>
#calendar-box{
    display:none;
}
    .calendar-container {
   display: flex;
    width: 100%;
    margin: 20px auto;
    overflow: hidden;
    justify-content: center;
}

.left-panel {
    width: 50%; /* Ajusta la proporción según tus necesidades */
    background-color: #fff;
    padding: 20px;
}

.right-panel, .right-right-panel {
    width: 25%; /* Ajusta la proporción según tus necesidades */
    background-color: #f9f9f9;
    padding: 20px;
    border-left: 1px solid #eee;
    display:none;
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

#schedule-list-pm {
    list-style: none;
    padding: 0;
}

#schedule-list-pm li {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
#schedule-list-am {
    list-style: none;
    padding: 0;
}

#schedule-list-am li {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
/* Estilos para días deshabilitados */
td.disabled {
    color: #ccc;
    cursor: default;
    pointer-events: none;
}

 td.blocked {
      cursor: default;
    pointer-events: none;
            background-color: red !important;
            color: white;
        }
        
        .lead {
    font-size: 1.05rem !important;
}
#date-list li.assigned {
    text-decoration: line-through; /* Tachar la fecha */
    color: gray; /* Color gris para las fechas ocupadas */
}
.date-blocked {
    text-decoration: line-through; /* Tachar la fecha */
    color: red; /* Color gris para las fechas ocupadas */
}
#date-list li {
   padding-bottom:10px;
}

</style>
<body>
    <!-- Modal para seleccionar horarios -->
<div class="modal" id="horarioModal" tabindex="-1" aria-labelledby="horarioModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="horarioModalLabel">Seleccionar Horarios</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="horarios-form-dia">
          <div class="row">
            <!-- Primera columna: de 0 a 12 -->
            <div class="col-6">
              <?php for ($i = 0; $i < 12; $i++): ?>
                <div class="col-12">
                    <input class="form-check-input" type="checkbox" id="hora-<?php echo $i; ?>" name="hora[<?php echo $i; ?>]">
                    <label class="form-check-label"  for="hora-<?php echo $i; ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>:00</label>
                </div>
              <?php endfor; ?>
            </div>

            <!-- Segunda columna: de 13 a 23 -->
            <div class="col-6">
              <?php for ($i = 12; $i < 24; $i++): ?>
                <div class="col-12">
                    <input class="form-check-input" type="checkbox" id="hora-<?php echo $i; ?>" name="hora[<?php echo $i; ?>]">
                    <label class="form-check-label"  for="hora-<?php echo $i; ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>:00</label>
                </div>
              <?php endfor; ?>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" id="guardar-horas-dia">Guardar Horas</button>
      </div>
    </div>
  </div>
</div>


    <div class="container py-5">
    <h3 class="">Programación de Horarios</h3><br>
    <section class="section">
        <div class="row mb-2 ">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body mt-2">
                        <h4>Selecciona un Vendedor</h4>
                        <div class="col-md-4 mb-3">
                            <select id="usuarioSelect" class="form-control">
                                <option value="">Selecciona un vendedor</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?php echo htmlspecialchars($usuario['id']); ?>">
                                        <?php echo htmlspecialchars($usuario['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="lead">
                        *Al momento de seleccionar el día y elegir el horario, no olvides guardar los cambios para asegurar que se actualce correctamente.
                        </p>
                        <div class="container">
                          
                              <div class="calendar-container" id="calendar-box">
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
                            
   <div class="d-flex flex-row justify-content-between mb-4 align-items-center">
        <h6 class="mr-3">Horarios para el d&iacute;a <span id="selected-date"></span></h6>
           <button class=" btn btn-success" onclick="revisarHorarios()">Guardar</button>
   </div>
    <div style="display: flex;">
        <div style="flex: 1;">
          
            <ul id="schedule-list-am"></ul>
        </div>
        <div style="flex: 1;">
           
            <ul id="schedule-list-pm"></ul>
        </div>
    </div>
    <div class="d-flex  mt-3">
                                                             
</div>
</div>
<div class="right-right-panel">
                            
   <h6>Fechas faltantes</h6>
    <ul id="date-list" class="mt-3"></ul>
  
</div>

                    </div>
                             


                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let horarios = <?php echo $horariosJson; ?>;
let userId = null;

let selectedTimes = []; // Array donde almacenaremos las horas seleccionadas
$('#usuarioSelect').on('change', function() {
    let getUserId = $(this).val();
    userId = getUserId;
    $('#calendar-box').css('display', 'inline-flex'); // Hacer visible el calendario
    $('.right-right-panel').css('display', 'block'); // Hacer visible el calendario

    // Limpiar los horarios seleccionados y desmarcar los checkboxes
    selectedTimes = [];
    $('.schedule-checkbox').prop('checked', false); // Desmarcar todos los checkboxes


    // Cargar los horarios del usuario seleccionado
    cargarHorariosDelUsuario(userId);
    mostrarFechasDisponibles();
});


async function mostrarFechasDisponibles() {
    // Obtener las 14 fechas disponibles
    const availableDates = [];
    for (let i = 0; i < 15; i++) {
        let date = new Date();
        date.setDate(date.getDate() + i);  // Aumentar el día por cada iteración
        let dateString = date.toISOString().split('T')[0]; // Formato YYYY-MM-DD
        
        availableDates.push(dateString);
    }

    // Limpiar la lista de fechas antes de mostrar las nuevas
    $('#date-list').empty();

    availableDates.forEach(date => {
         const isBlocked = diasBloqueados.includes(date);
                const blockedClass = isBlocked ? 'date-blocked' : '';
      // Convertir la fecha a formato dd-mm-yyyy
const formattedDate = date.split('-').reverse().join('-');  // Se invierte el array y se vuelve a unir con guiones

// Crear el <li> con la fecha formateada
const $listItem = $(`<li class="${blockedClass}">`).text(formattedDate);

        // Verificar si esa fecha ya tiene horarios asignados en selectedTimes
        const isDateSelected = selectedTimes.some(item => item.date === date);

        // Si la fecha ya tiene horarios, agregarla con una clase "assigned"
        if (isDateSelected) {
            $listItem.addClass('assigned'); // Puedes agregar una clase CSS para indicar que está ocupada
        }

        // Añadir el <li> a la lista de fechas
        $('#date-list').append($listItem);
    });
}












async function cargarHorariosDelUsuario(userId) {
    // Encontrar los horarios del usuario seleccionado
    const horariosUsuario = horarios.filter(item => item.idusu == userId);

    // Crear un objeto para mapear los horarios por fecha
    let horariosPorFecha = {};


    horariosUsuario.forEach(item => {
        // Parsear los horarios desde la cadena JSON
        let horariosArray = JSON.parse(item.horarios);
        
        // Convertir la fecha al formato "YYYY-MM-DD"
        let formattedDateStr = moment(item.dia).format('YYYY-MM-DD');
        
        // Asegurarse que el array para esa fecha exista
        if (!horariosPorFecha[formattedDateStr]) {
            horariosPorFecha[formattedDateStr] = [];
        }
        
        // Añadir los horarios a la fecha correspondiente
        horariosPorFecha[formattedDateStr] = horariosPorFecha[formattedDateStr].concat(horariosArray);
    });


    // Ahora actualizamos `selectedTimes` con los horarios de ese usuario
    selectedTimes = []; // Limpiar el array de seleccionados
    for (const date in horariosPorFecha) {
        horariosPorFecha[date].forEach(hour => {
            selectedTimes.push({ userId: userId, hour: hour, date: date });
        });
    }


    // Actualizar los checkboxes con los horarios correspondientes
    actualizarCheckboxes();
}

// Esta función actualizará los checkboxes según los horarios seleccionados para el usuario
function actualizarCheckboxes() {
    // Recorrer todos los horarios seleccionados y marcar los checkboxes correspondientes
    selectedTimes.forEach(selectedTime => {
        // Buscar el checkbox correspondiente
        const $checkbox = $(`li[data-hour="${selectedTime.hour}"][data-date="${selectedTime.date}"] .schedule-checkbox`);
        
        // Marcar el checkbox si el horario y la fecha coinciden
        if ($checkbox.length) {
            $checkbox.prop('checked', true);
        }
    });

}


async function revisarHorarios() {
// Las horas disponibles de 00:00 a 23:30 (cada 30 minutos)
 const horasDisponibles = [];
    for (let i = 0; i < 24; i++) {
        let h = i < 10 ? `0${i}` : `${i}`;
        horasDisponibles.push(`${h}:00`, `${h}:30`);
    }

    // Objeto para almacenar los horarios no seleccionados por fecha
    const horariosNoSeleccionadosPorFecha = [];

    // Para cada fecha, encontramos las horas seleccionadas y las no seleccionadas
    selectedTimes.forEach((item) => {
        // Buscar si ya existe un objeto para esa fecha en el array
        let fechaObj = horariosNoSeleccionadosPorFecha.find(f => f.fecha === item.date);
        
        // Si no existe, creamos un nuevo objeto para esa fecha
        if (!fechaObj) {
            fechaObj = {
                fecha: item.date,
                horariosNoSeleccionados: [...horasDisponibles]  // Copiamos todas las horas disponibles
            };
            horariosNoSeleccionadosPorFecha.push(fechaObj);
        }

        // Filtramos las horas seleccionadas de las horas disponibles para esa fecha
        fechaObj.horariosNoSeleccionados = fechaObj.horariosNoSeleccionados.filter(h => h !== item.hour);
    });

     $.ajax({
        url: 'verificar_horas.php',  // Este archivo debe manejar la consulta a la base de datos
        method: 'POST',
         data: {
            userId: userId,
            fechasSeleccionadas: horariosNoSeleccionadosPorFecha
        },
        success: function(res) {
            var response = JSON.parse(res);
            if (response.status === 'found') {
                 let citasEncontradas = response.data;
                 let tableContent = `<table class="table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Fecha</th>
                                    <th>No. Cita</th>
                                    <th>Nombre</th>
                                    <th>Teléfono</th>
                                </tr>
                            </thead>
                            <tbody>`;

    // Recorrer las citas y agregar las filas correspondientes a la tabla
    citasEncontradas.forEach(cita => {
        // Verificar si hay un cliente en el registro
        if (cita.cliente) {
            var time = new Date(`${cita.fecha} ${cita.hora}`);
            time = new Intl.DateTimeFormat("es-MX", {
                dateStyle: "full",
                timeStyle: "short",
                timeZone: "America/Chicago",
              }).format(time);
            tableContent += `<tr>
                                <td><input type='checkbox' value='${cita.id}' class="chkCita form-check-input" checked ></td>
                                <td>${time}</td>
                                <td>${cita.id}</td>
                                <td>${cita.cliente.names}</td>
                                <td>${cita.cliente.telephone}</td>
                             </tr>`;
        }
    });

    // Cerrar la tabla
    tableContent += `</tbody></table>`;
                // Si hay un registro en la base de datos con la hora no seleccionada
            citasActivas = [];
    Swal.fire({
        icon: 'warning',
        title: '¡Alerta!',
        width:'90%',
        html: `Hay citas en las horas no seleccionadas. Deshabilita las citas que desees reasignar.<br><br>${tableContent}`,
        showCancelButton: true,
        confirmButtonText: 'Sí, actualizar',
        cancelButtonText: 'No, cancelar',
        preConfirm:()=>{
            chkCitas = document.querySelectorAll('.chkCita');
            for(i=0;i<chkCitas.length;i++){
                if (!chkCitas[i].checked){
                    citasActivas.push({id:chkCitas[i].value});
                }
            }
        },
                }).then((result) => {
                    if (result.isConfirmed) {
                      if(citasActivas.length > 0){
                             $.ajax({
                                url: 'actualizar_calendario.php',  // Archivo PHP para actualizar los registros
                                method: 'POST',
                                data: {
                                    citas: citasActivas  // Enviar la variable citasActivas como datos
                                },
                              success: function(res) {
                                var response = JSON.parse(res);  // Parseamos la respuesta
                            
                                if(response.status === "success") {  // Comparamos la propiedad status de response
                                    guardarHorarios();  // Llamamos la función si la respuesta es "success"
                                }
                            },
                                error: function(xhr, status, error) {
                                    // Manejar cualquier error en la solicitud AJAX
                                    console.error('Error al actualizar los registros:', error);
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: 'Hubo un problema al actualizar los registros.'
                                    });
                                }
                            });
                      }else{
                             guardarHorarios();
                      }
                    } else {
                        // Salir sin hacer nada
                        
                    }
                });
            } else {
                // Si no se encontró ningún registro con la hora no seleccionada
                guardarHorarios();
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo verificar las horas en el calendario.'
            });
        }
    });
    
}
async function guardarHorarios() {
    const usuarioId = $('#usuarioSelect').val(); // ID del usuario seleccionado

    // Agrupar los horarios seleccionados por fecha
    let horariosPorFecha = {};

    // Recorremos los horarios seleccionados y los agrupamos por fecha
    
    selectedTimes.forEach(item => {
        // Convertir la fecha al formato "DD:MM:YY"
     let formattedDateStr = moment(item.date).format('YYYY-MM-DD');


        // Verificar que la fecha está en el formato correcto

        // Si no existe una entrada para esa fecha, la creamos
        if (!horariosPorFecha[formattedDateStr]) {
            horariosPorFecha[formattedDateStr] = [];
        }
        // Añadimos la hora seleccionada a esa fecha
        horariosPorFecha[formattedDateStr].push(item.hour);
    });

    // Incluir fechas que antes tenían horarios pero ahora quedaron vacías (el usuario las desactivó todas)
    const horariosUsuario = horarios.filter(item => item.idusu == userId);
    horariosUsuario.forEach(item => {
        let formattedDateStr = moment(item.dia).format('YYYY-MM-DD');
        if (!(formattedDateStr in horariosPorFecha)) {
            horariosPorFecha[formattedDateStr] = [];
        }
    });
    
      const loadingSwal = Swal.fire({
        title: 'Guardando...',
        text: 'Please wait while we process your request.',
        allowOutsideClick: false, // Prevenir que el usuario cierre el alerta
        
        customClass: {
        popup: 'swal2-popup-center'
    },
        didOpen: () => {
            Swal.showLoading(); // Mostrar el spinner de carga
        }
    });

    // Enviar los datos al servidor con AJAX
     $.ajax({
    url: 'guardar_horarios_dinamico.php', // El archivo PHP que recibirá la solicitud
    type: 'POST',
    data: {
        horarios: JSON.stringify(horariosPorFecha), // Convertir el objeto a JSON
        usuarioId: userId
    },
    success: function(response) {

        // Parsear la respuesta JSON
        let data = JSON.parse(response);
                  loadingSwal.close();

        // Iterar sobre cada item de la respuesta para mostrar un Swal
        data.forEach(function(item) {
            if (item.status === 'success') {
                // Si la operación fue exitosa, mostrar un Swal de éxito
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: "Horarios guardados exitosamente",  // Mostrar el mensaje de éxito
                });
            } else {
                // Si hubo un error, mostrar un Swal de error
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: item.message,  // Mostrar el mensaje de error
                });
            }
        });
    },
    error: function(xhr, status, error) {
        console.error("Error al guardar los horarios:", error);
        // Mostrar un Swal de error genérico si ocurre un error en la petición AJAX
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Hubo un problema al guardar los horarios. Intenta nuevamente.',
        });
    }
});
}


   const diasBloqueados = <?php echo json_encode($dias_bloqueados); ?>;
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
const minDate = new Date(today); // Copiar la fecha actual
minDate.setDate(today.getDate() - 1); // Restar un día para que sea ayer

const maxDate = new Date(today);
maxDate.setDate(today.getDate() + 14); // Fecha máxima (14 días después)


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

    let selecteddate = "";
// Aquí manejamos el evento cuando se selecciona una hora
async function showSchedule(event) {
    $('.right-panel').css('display', 'block'); // Hacer visible el calendario

    const $cell = $(event.target);
    const day = $cell.data('day');
    const month = $cell.data('month');
    const year = $cell.data('year');
    const selectedDateStr = `${day}/${month + 1}/${year}`;
        const selectedDate = await getFixDate(year, month, day);

 const isBlocked = diasBloqueados.includes(selectedDate);
 console.log("isBlocked ",isBlocked)
 if(isBlocked){
        // $('.right-panel').css('display', 'none'); // Hacer visible el calendario

 }else{
     // Mostrar la fecha seleccionada
    $('#selected-date').text(selectedDateStr);

    // Limpiar la clase selected de todos los días antes de agregarla al seleccionado
    $('#calendar td').removeClass('selected');
    // Añadir la clase selected al día seleccionado
    $cell.addClass('selected');

    // Llamar a la función que obtiene los horarios para el día seleccionado
    const schedule = await getSchedule(year, month, day);

    // Limpiar los contenedores de horarios existentes
    $('#schedule-list-am').empty();
    $('#schedule-list-pm').empty();

    if (schedule && schedule.length > 0) {
        schedule.forEach(time => {
            // Crea el elemento <li>
            const $listItem = $(`<li data-hour="${time}" data-date="${year}-${month + 1 < 10 ? '0' + (month + 1) : month + 1}-${day < 10 ? '0' + day : day}">`);

            // Crea el checkbox y lo coloca dentro del <li>
            const $checkbox = $('<input type="checkbox" class="form-check-input schedule-checkbox">');

            // Verificar si el horario está disponible para ese día y usuario
            const selectedDate = `${year}-${month + 1 < 10 ? '0' + (month + 1) : month + 1}-${day < 10 ? '0' + day : day}`;
            const isChecked = selectedTimes.some(item => item.userId === $('#usuarioSelect').val() && item.hour === time && item.date === selectedDate);

            // Marcar el checkbox si ya está seleccionado
            if (isChecked) {
                $checkbox.prop('checked', true);
            }

            // Agregar el checkbox y el texto (hora) dentro del <li>
            $listItem.append($checkbox).append(` ${time}`);

            // Determinar a qué lista agregar el elemento
            const hour = parseInt(time.split(':')[0]);
            if (hour < 12) {
                $('#schedule-list-am').append($listItem);
            } else {
                $('#schedule-list-pm').append($listItem);
            }

            // Evento de clic en el checkbox
            $checkbox.change(function () {
                const userId = $('#usuarioSelect').val(); // Obtener el ID del usuario seleccionado
                const hour = time;
                const isChecked = $(this).prop('checked');
                const selectedDate = `${year}-${month + 1 < 10 ? '0' + (month + 1) : month + 1}-${day < 10 ? '0' + day : day}`; // Formato YYYY-MM-DD

                // Si está marcado, agregamos el objeto al array, si no, lo eliminamos
                if (isChecked) {
                    selectedTimes.push({ userId, hour, date: selectedDate });
                } else {
                    // Eliminar del array si ya está marcado como false
                    selectedTimes = selectedTimes.filter(item => item.userId !== userId || item.hour !== hour || item.date !== selectedDate);
                }
                  // Después de actualizar selectedTimes, actualizar la lista de fechas disponibles
                    mostrarFechasDisponibles();
            });
        });
    } else {
        const $listItem = $('<li>').text('No hay horarios para este día.');
        $('#schedule-list-am').append($listItem);
        $('#schedule-list-pm').append($('<li>')); // Agrega un elemento vacío para mantener la alineación
    }
 }
    
}

async function getSchedule(year, month, day) {
    let fixDay = day;
    let mes = month + 1;
    // Asegurarse de que mes y día siempre tengan 2 dígitos
    let formattedMonth = mes < 10 ? '0' + mes : mes;
    let formattedDay = fixDay < 10 ? '0' + fixDay : fixDay;

    // Crear la fecha en formato yyyy-mm-dd
    let date = `${year}-${formattedMonth}-${formattedDay}`;
    selecteddate = date;
    const fechaComoCadena = date + " 00:00:00";
    const numeroDia = new Date(fechaComoCadena).getDay();
    
    let horas = [];
    for (let i = 0; i < 24; i++) {
        let h = i < 10 ? `0${i}` : `${i}`;
        horas.push(`${h}:00`, `${h}:30`);
    }

    return horas;
}
function getFixDate(year, month, day) {
    let fixDay = day;
    let mes = month + 1;
    let formattedMonth = mes < 10 ? '0' + mes : mes;
    let formattedDay = fixDay < 10 ? '0' + fixDay : fixDay;

    // Crear la fecha en formato yyyy-mm-dd
    let date = `${year}-${formattedMonth}-${formattedDay}`;
    return date;
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
    
    
    
    
    



</script>
<?php

include 'footer.php';

?>
</body>
</html>




