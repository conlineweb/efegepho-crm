<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Conexión a la base de datos

$userid = $_SESSION['uid'];


// Consulta SQL para obtener todos los campos de la tabla dias_bloqueados
$query = "SELECT * FROM dias_bloqueados";
$result = mysqli_query($conn, $query);

// Verificar si la consulta tuvo resultados
if ($result) {
    $dias_bloqueados = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Guardar todo el registro (todos los campos)
        $dias_bloqueados[] = $row['fecha'];
    }
    
    // Devolver los datos en formato JSON
   
} else {
    // Si no hay resultados o hubo un error
   
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloquear d&iacute;as de citas</title>
     <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
</head>
<body>
<style>
    

.calendar-container {
   display: flex;
    width: 800px;
    margin: 20px auto;
    overflow: hidden;
    justify-content: center;
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

 td.blocked {
            background-color: red !important;
            color: white;
        }

</style>

    <div class="container py-5">
    <h3 class="">Bloqueo de d&iacute;as de citas</h3><br>
    <section class="section">
        <div class="row mb-2 ">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="container">
                          
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
<!-- SweetAlert CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
   const diasBloqueados = <?php echo json_encode($dias_bloqueados); ?>;
console.log(diasBloqueados);

const $calendar = $('#calendar');
const $currentMonth = $('#current-month');
const $prevMonthButton = $('#prev-month');
const $nextMonthButton = $('#next-month');
const $scheduleContainer = $('#schedule-container');
const $scheduleList = $('#schedule-list');
const $selectedDate = $('#selected-date');

// Mantén una variable separada para la fecha actual real
const today = new Date();

// Usa esta variable para realizar un seguimiento del mes y año mostrados
let displayedDate = new Date();

const minDate = today;

function generateCalendar(month, year) {
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startDay = firstDay.getDay();

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
                const currentDatee = new Date(year, month, date);
                const isBeforeMinDate = currentDatee < minDate;
                const currentDateStr = `${year}-${month + 1 < 10 ? '0' + (month + 1) : month + 1}-${date < 10 ? '0' + date : date}`;
                const isBlocked = diasBloqueados.includes(currentDateStr);
                const blockedClass = isBlocked ? 'blocked' : '';
                const disabledClass = isBeforeMinDate ? 'disabled' : '';

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

    // Resaltar el día actual (usando la variable 'today')
    if (displayedDate.getMonth() === today.getMonth() && displayedDate.getFullYear() === today.getFullYear()) {
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

    // Verificar si la fecha está bloqueada
    const selectedDate = await getFixDate(year, month, day);
    const isBlocked = diasBloqueados.includes(selectedDate);

    // Mostrar la alerta con SweetAlert
    if (isBlocked) {
        Swal.fire({
            title: "Esta fecha está bloqueada",
            text: "¿Quieres desbloquearla?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: 'Sí, desbloquearla',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((willUnlock) => {
            if (willUnlock.isConfirmed) {
                $.ajax({
                    url: "bloqueo_fechas.php",
                    method: "POST",
                    data: { fecha: selectedDate },
                    success: function (response) {
                        const data = JSON.parse(response);
                        if (data.status === "success") {
                            if (data.action === "blocked") {
                                Swal.fire({
                                    title: "Fecha bloqueada",
                                    text: "La fecha ha sido bloqueada correctamente.",
                                    icon: "success",
                                }).then(() => {
                                    location.reload();
                                });
                            } else if (data.action === "unblocked") {
                                Swal.fire({
                                    title: "Fecha desbloqueada",
                                    text: "La fecha ha sido desbloqueada.",
                                    icon: "success",
                                }).then(() => {
                                    location.reload();
                                });
                            }
                        } else {
                            Swal.fire({
                                title: "Error",
                                text: data.message,
                                icon: "error",
                            });
                        }
                    }
                });
            }
        });
    } else {
        Swal.fire({
            title: "Esta fecha no está bloqueada",
            text: "¿Quieres bloquearla?",
            icon: "info",
            showCancelButton: true,
            confirmButtonText: 'Sí, bloquearla',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((willBlock) => {
            if (willBlock.isConfirmed) {
                $.ajax({
                    url: "bloqueo_fechas.php",
                    method: "POST",
                    data: { fecha: selectedDate },
                    success: function (response) {
                        const data = JSON.parse(response);
                        if (data.status === "success") {
                            if (data.action === "blocked") {
                                Swal.fire({
                                    title: "Fecha bloqueada",
                                    text: "La fecha ha sido bloqueada correctamente.",
                                    icon: "success",
                                }).then(() => {
                                    location.reload();
                                });
                            } else if (data.action === "unblocked") {
                                Swal.fire({
                                    title: "Fecha desbloqueada",
                                    text: "La fecha ha sido desbloqueada.",
                                    icon: "success",
                                }).then(() => {
                                    location.reload();
                                });
                            }
                        } else {
                            Swal.fire({
                                title: "Error",
                                text: data.message,
                                icon: "error",
                            });
                        }
                    }
                });
            }
        });
    }
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
    let prevMonth = displayedDate.getMonth() - 1;
    let prevYear = displayedDate.getFullYear();
    if (prevMonth < 0) {
        prevMonth = 11;
        prevYear--;
    }
    displayedDate = new Date(prevYear, prevMonth, 1);
    generateCalendar(prevMonth, prevYear);
});

$nextMonthButton.click(function (e) {
    e.preventDefault();
    let nextMonth = displayedDate.getMonth() + 1;
    let nextYear = displayedDate.getFullYear();
    if (nextMonth > 11) {
        nextMonth = 0;
        nextYear++;
    }
    displayedDate = new Date(nextYear, nextMonth, 1);
    generateCalendar(nextMonth, nextYear);
});

// Inicializa el calendario con el mes y año actuales
generateCalendar(displayedDate.getMonth(), displayedDate.getFullYear());
</script>
<?php

include 'footer.php';

?>
</body>
</html>
