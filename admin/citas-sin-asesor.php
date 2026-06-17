<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexi贸n a la base de datos


// Realizar la consulta SQL para obtener todos los usuarios
$sql = "SELECT * FROM usuarios WHERE tipoUsu = 1";
$result = $conn->query($sql);

$usuarios = array(); // Array para almacenar los resultados

if ($result->num_rows > 0) {
    // Si hay resultados, los almacenamos en el array
    while($row = $result->fetch_assoc()) {
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

// Cerrar la conexión
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de citas</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">

</head>
<style>

#verMasModal p{
    font-size:1.15rem;
}
table{
    
    width:100% !important;
    
}
</style>
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-12 order-md-1 order-last">
                <h3>Consulta de citas</h3>
               <br>
            </div>
            <div class="col-12 col-md-12 order-md-2 order-first">
           
            </div>
        </div>
    </div>
   
     
   <!-- <section class="section">-->
   <!--     <div class="card">-->
   <!--         <div class="card-header">-->
   <!--             <h4 class="card-title">Registros</h4>-->
   <!--         </div>-->
   <!--         <div class="card-body">-->
   <!--     <table id="contactTable" class="table table-striped table-bordered">-->
   <!--         <thead>-->
   <!--             <tr>-->
   <!--                 <th>No. Cliente</th>-->
   <!--                 <th>Fecha de registro</th>-->
   <!--                 <th>Nombre</th>-->
   <!--                 <th>Teléfono</th>-->
   <!--                 <th>Correo</th>-->
   <!--                 <th>Fecha de la cita</th>-->
   <!--                 <th>Asesor asignado</th>-->
   <!--                 <th>Estatus</th>-->

   <!--                 <th>Acciones</th>-->

   <!--             </tr>-->
   <!--         </thead>-->
   <!--         <tbody>-->
   <!--         </tbody>-->
   <!--     </table>-->
   <!--</div>-->
   <!--     </div>-->
   <!-- </section>-->
    
    
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Citas sin vendedor asignado</h4>
            </div>
            <div class="card-body">
        <table id="contactTable1" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>No. Cliente</th>
                    <th>Fecha de registro</th>
                     <th>Hora de registro</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                    <th>Fecha de la cita</th>
                     <th>Hora de la cita</th>
                    <th>Asesor asignado</th>
                    <th>Acciones</th>

                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
   </div>
        </div>
    </section>
</div>
<!-- Modal para Ver Más Información -->
<div class="modal fade" id="verMasModal" tabindex="-1" aria-labelledby="verMasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" type="document">
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

<!-- Modal para Reagendar -->
<div id="modal-reagendar" class="modal fade" tabindex="-1"  aria-labelledby="reagendarModal" aria-hidden="true">
  <div class="modal-dialog modal-lg" type="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"  id="reagendarModal">Reagendar Cita</h5>
                               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>

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
                    <input id="date_appointment" name="date_appointment" placeholder="Selecciona una fecha" autocomplete="off" type="date" class="form-control">
                </div>
                
                <div class="mb-3">
                        <p id="last_time" class="m-0"></p>
                    <label for="time_appointment" class="form-label">Nueva hora de cita</label>
                    <select id="time_appointment" name="time_appointment" autocomplete="off" class="form-control">
                        <!-- Opciones de tiempo dinámicas aquí -->
                    </select>
                </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between" id="modal-reagendar-footer">
                <div id="btnReag"></div>
               <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="confirmar-reagendar">Reagendar</button>
               </div>
            </div>
        </div>
    </div>
</div>


<?php

include 'footer.php';

?>
    
    
</body>

</html>
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
         const blockedDates  = <?php echo json_encode($dias_bloqueados); ?>;

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


  $(document).ready(function () {
      
      
      // Función para convertir la fecha y hora en un objeto Date
function parseFechaHora(item) {
  const [fecha, hora] = item.fecha.split(' '); // Separa la fecha de la hora
  const [año, mes, dia] = fecha.split('-'); // Asume que la fecha está en formato yyyy-mm-dd
  const [horaStr, minutos] = hora.split(':'); // Separa la hora de los minutos

  // Retorna un objeto Date que podemos comparar
  return new Date(año, mes - 1, dia, horaStr, minutos);
}
      
      
 $('#confirmar-reagendar').click(function() {
     const loadingSwal = Swal.fire({
        title: 'Reagendando...',
        text: 'Please wait while we process your request.',
        allowOutsideClick: false, // Prevenir que el usuario cierre el alerta
        didOpen: () => {
            Swal.showLoading(); // Mostrar el spinner de carga
        }
    });
    // Obtener los valores de los inputs
    var usuarioSeleccionado = $('#select-usuarios').val();
    var nuevaFecha = $('#date_appointment').val();
    var nuevaHora = $('#time_appointment').val();
    var reagendarId = $('#reagendaId').val();
    var customerId = $('#customerId').val();
     var hasSeller = $('#hasSeller').val();

    // Verificar si todos los campos están completos
    if (usuarioSeleccionado && nuevaFecha && nuevaHora) {
        // Datos que se enviarán
        var data = {
            id: reagendarId,
            vendedor: usuarioSeleccionado,
            fecha: nuevaFecha,
            hora: nuevaHora,
            cliente:customerId,
            hasSeller: hasSeller
            
        };
        
        // Enviar la solicitud AJAX
        $.ajax({
            url: 'reagendar.php', // Ruta al archivo PHP
            type: 'POST',
            data: data,
            success: function(response) {
             var result = JSON.parse(response); // Parsear la respuesta JSON
                  loadingSwal.close();
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
            error: function(xhr, status, error) {
                // Si ocurre un error con la solicitud AJAX
                Swal.fire({
                    icon: 'error',
                    title: 'Hubo un error',
                    text: 'No se pudo actualizar la cita. Por favor, intenta nuevamente.',
                    confirmButtonText: 'Aceptar'
                });
            }
        });
    } else {
        // Si falta algún campo, mostrar un mensaje
        Swal.fire({
            icon: 'warning',
            title: 'Campos incompletos',
            text: 'Por favor, completa todos los campos antes de continuar.',
            confirmButtonText: 'Aceptar'
        });
    }
});
     
      
      
      
      
$('#select-usuarios').on('change', function() {
    var usuarioSeleccionadoId = $(this).val();
    if (usuarioSeleccionadoId) {
        // Mostrar los campos de fecha y hora
        $('#show_inputs').show();

        // Vaciar los campos de fecha y hora
        $('#date_appointment').val('');
        $('#time_appointment').val('');

        // Vaciar las opciones del select de horarios
        $('#time_appointment').html('<option value="">Selecciona una hora</option>');

        // Obtener el nombre del usuario seleccionado
        var usuarioSeleccionadoNombre = $(this).find("option:selected").text();

        // Cuando se cambia la fecha en el campo #date_appointment
        $("#date_appointment").on('change', function(e) {
            
            
                    let selectedDate = event.target.value; // Fecha seleccionada por el usuario

    if (isBlocked(selectedDate)) {
        alert("La fecha seleccionada está bloqueada.");
        // Opcional: restablecer el valor del input si la fecha está bloqueada
        event.target.value = '';
         $('#time_appointment').html('');
    }else{
         const fechaSeleccionada = $("#date_appointment").val() + " 00:00:00"; // Fecha completa
            const numeroDia = new Date(fechaSeleccionada).getDay(); // Obtener el número del día de la semana

            const formData = new FormData();
            formData.append('dia', $("#date_appointment").val());
            formData.append('idusu', usuarioSeleccionadoId);

            // Limpiar la lista de horarios anteriores
            let select = "";

            // Obtener los horarios disponibles para el día seleccionado
            fetch("chkDisponible.php", {
                method: "POST",
                body: formData,
            })
            .then(response => response.json())
            .then(horariosJson => {
                const form = new FormData();
                form.append('fecha', $("#date_appointment").val()); // Fecha seleccionada
                form.append('idusu', usuarioSeleccionadoId);

                // Obtener los horarios ocupados para esa fecha
                fetch("chkDisponible.php", {
                    method: "POST",
                    body: form,
                })
                .then(response => response.json())
                .then(times => {
                      if (horariosJson.length > 0){
                        let horarios =  JSON.parse(horariosJson[0].horarios)
                        var hrs = [];
                            horarios.forEach((horario) => {
                            if (times.length > 0) {
                                    let hr = horario;
                                    // console.log("horario ",horario)
                                    times.forEach((time) => {
                                        if (time.hora == `${horario}:00`) {
                                            hr = 0;
                                        }
                                    });
                                    if (hr != 0 && !hrs.includes(horario)) {
                                        hrs.push(horario);
                                    }
                                } else {
                                    if (!hrs.includes(horario)) {
                                        hrs.push(horario);
                                    }
                                }
                        });
                        // // Ordenar las horas disponibles
                         function convertToMinutes(timeStr) {
                            let [hrs, mins] = timeStr.split(":");
                            return parseInt(hrs) * 60 + parseInt(mins);
                        }
                        
                        // Ordena el array de horas
                        hrs.sort(function(a, b) {
                            return convertToMinutes(a) - convertToMinutes(b);
                        });
    
    
                        // Construir las opciones para el select de horas
                        hrs.forEach((hora) => {
                            select += `<option value="${hora}:00">${hora}</option>`;
                        });
    
                        $('#time_appointment').html(select); // Actualizar el dropdown con horarios disponibles
                    }
                    else
                        $('#time_appointment').html('<option value="">Sin Horarios Disponibles</option>'); // Actualizar el dropdown con horarios disponibles
                //      console.log("times ",times)
                //     // console.log("horariosJson ",horariosJson[0].horarios)

                //     let hrs = [];
                //   let horarios =  JSON.parse(horariosJson[0].horarios)
                //     console.log("horarios ",horarios)
                //     horarios.forEach((horario) => {
                //         if (times.length > 0) {
                //                 let hr = horario;
                //                 times.forEach((time) => {
                //                       if (time.hora == `${horario}:00`) {
                //                         hr = 0;
                //                     }
                //                 });
                //                 if (hr != 0 && !hrs.includes(horario)) {
                //                     hrs.push(horario);
                //                 }
                //             } else {
                //                 if (!hrs.includes(horario)) {
                //                     hrs.push(horario);
                //                 }
                //             }
                        
                        
                //     });

                // //     // Ordenamos las horas
                //     function convertToMinutes(timeStr) {
                //         let [hrs, mins] = timeStr.split(":");
                //         return parseInt(hrs) * 60 + parseInt(mins);
                //     }
                    
                //     // Ordena el array de horas
                //     hrs.sort(function(a, b) {
                //         return convertToMinutes(a) - convertToMinutes(b);
                //     });

                //     // Creamos el HTML para las tarjetas
                //     for (let i = 0; i < hrs.length; i++) {
                //         // horas.push(hrs[i]);
                //         select += `<option value="${hrs[i]}:00">${hrs[i]}</option>`;
                //     }
                //     $('#time_appointment').html(select);
                    
                })
                .catch(error => {
                    console.error("Error al obtener horarios ocupados:", error);
                });
            })
            .catch(error => {
                console.error("Error al obtener disponibilidad de días:", error);
            });
    }
            
           
        });
    } else {
        // Si no se selecciona un usuario, ocultar los campos
        $('#show_inputs').hide();
        console.log('No se ha seleccionado ningún usuario.');
    }
});



     
    $.ajax({
        url: 'obtener-datos-formulario.php', // Ruta del archivo PHP
        type: 'GET', // Método de solicitud
        dataType: 'json', // Esperamos que nos devuelvan un JSON
        success: function(response) {
            // Verificar si hay datos en la respuesta
            if (response.data && response.data.length > 0) {
                
                             // Filtrar los objetos con idusu igual a 0
                const registroSinVendedor = response.data.filter(item => item.idusu === "0");
            
            // Filtrar los objetos con idusu diferente de 0
const registroConVendedor = response.data.filter(item => item.idusu !== "0");

// Obtener la fecha y hora actual
const now = new Date();

// Ordenar registros por fecha y luego por hora en orden ascendente (primero los más cercanos)
registroConVendedor.sort((a, b) => {
  // Crear objetos Date solo con la fecha para cada registro
  const fechaA = new Date(a.fecha + "T" + a.hora); // Combina fecha y hora en un solo string
  const fechaB = new Date(b.fecha + "T" + b.hora); // Lo mismo para el segundo registro

  // Comparar las fechas y horas directamente con la fecha actual
  // Si la fecha de A es posterior a la fecha de B, A debe ir después de B
  if (fechaA < now && fechaB >= now) {
    return 1; // A ya pasó, B está por llegar, por lo que B debe ir antes
  }
  if (fechaB < now && fechaA >= now) {
    return -1; // B ya pasó, A está por llegar, por lo que A debe ir antes
  }

  // Si ambos son futuros o ambos son pasados, los ordenamos por fecha y hora
  if (fechaA - fechaB !== 0) {
    return fechaA - fechaB; // Ordenar por fecha (los más cercanos primero)
  }

  // Si las fechas son iguales, ordenar por hora
  const horaA = a.hora.split(':').map(num => parseInt(num, 10)); // Dividir hora en [hora, minutos]
  const horaB = b.hora.split(':').map(num => parseInt(num, 10)); // Dividir hora en [hora, minutos]

  // Comparar las horas: primero por hora, luego por minutos
  if (horaA[0] !== horaB[0]) {
    return horaA[0] - horaB[0]; // Ordenar por hora
  } else {
    return horaA[1] - horaB[1]; // Ordenar por minutos
  }
});

// Filtrar los objetos con estatus '0' (pendientes)
let pendientes = registroConVendedor.filter(item => item.estatus === '0' || item.estatus === '2' );

// Filtrar los objetos con estatus '1' (atendidas)
let atendidas = registroConVendedor.filter(item => item.estatus === '1');


                //tabla con vendedor
             
                    //tabla sin vendedor
                   $('#contactTable1').DataTable({
                    data: registroSinVendedor, // Asignamos los datos obtenidos
                    columns: [
                        { data: 'id' },
                        { 
                            data: null, // No usamos un campo directo, vamos a concatenar
                            render: function(data, type, row) {
                                return moment(data.cliente_submission_date).format('DD/MM/YYYY');
                            },
                            title: 'Fecha de registro' // Título para la columna combinada
                        },{ 
                            data: null, // No usamos un campo directo, vamos a concatenar
                            render: function(data, type, row) {
                                return moment(data.cliente_submission_date).format('HH:mm a');
                            },
                            title: 'Hora de registro' // Título para la columna combinada
                        },
                        { data: 'cliente_names' },
                        { data: 'cliente_telefono' },
                        { data: 'cliente_email_address' },
                        { 
                            data: null, // No usamos un campo directo, vamos a concatenar
                            render: function(data, type, row) {
                                  moment.locale('es');

                               return moment(data.fecha, 'YYYY-MM-DD').format('dddd D [de] MMMM');
                            },
                            title: 'Fecha de la cita' // Título para la columna combinada
                        }, { 
                            data: null, // No usamos un campo directo, vamos a concatenar
                            render: function(data, type, row) {
                                return moment(data.fecha + ' ' + data.hora).format('HH:mm a');
                            },
                            title: 'Hora de la cita' // Título para la columna combinada
                        },
                         { 
                            data: null, // No usamos un campo directo, vamos a concatenar
                            render: function(data, type, row) {
                                return "Sin asesor asignado"
                            },
                            title: 'Nombre del asesor' // Título para la columna combinada
                        },
                        { 
                            data: null, // No usamos un campo directo, vamos a agregar los botones
                            render: function(data, type, row) {
                                return '<button class="btn btn-warning btn-sm btn-reagendar" data-fecha="' + data.fecha + '"data-hora="' + data.hora + '" data-idclie="' + data.idclie + '" data-id="' + data.id + '"  data-idusu="' + data.idusu + '"  >Asignar vendedor</button>'
                                // return '<button class="btn btn-info btn-sm btn-vermas" ' +
                                //       'data-id="' + data.id + '" ' +
                                //         'data-idusu="' + data.idusu + '" ' +
                                //          'data-idclie="' + data.idclie + '" ' +
                                //       'data-nombre="' + (data.cliente_names || 'No disponible') + '" ' +
                                //       'data-telefono="' + (data.cliente_telefono || 'No disponible') + '" ' +
                                //       'data-email="' + (data.cliente_email_address || 'No disponible') + '" ' +
                                //       'data-fecha="' + (data.fecha || 'No disponible') + '" ' +
                                //       'data-hora="' + (data.hora || 'No disponible') + '" ' +
                                //       'data-asesor="' + (data.usuario_nombre + ' ' + data.usuario_apePat + ' ' + data.usuario_apeMat || 'No disponible') + '" ' +
                                //       'data-nota="' + (data.nota || 'No disponible') + '" ' +
                                //       'data-titulo="' + (data.titulo || 'No disponible') + '" ' +
                                //         'data-cliente_names="' + (data.cliente_names || 'No disponible') + '" ' +
                                //         'data-cliente_telefono="' + (data.cliente_telefono || 'No disponible') + '" ' +
                                //         'data-email_address="' + (data.email_address || 'No disponible') + '" ' +
                                //       'data-cliente_additional_details="' + (data.cliente_additional_details || 'No disponible') + '" ' +
                                //       'data-cliente_couple_activities="' + (data.cliente_couple_activities || 'No disponible') + '" ' +
                                //       'data-cliente_date_appointment="' + (data.cliente_date_appointment || 'No disponible') + '" ' +
                                //       'data-cliente_favorite_movie_or_song="' + (data.cliente_favorite_movie_or_song || 'No disponible') + '" ' +
                                //       'data-cliente_guests_count="' + (data.cliente_guests_count || 'No disponible') + '" ' +
                                //       'data-cliente_hear_about_us="' + (data.cliente_hear_about_us || 'No disponible') + '" ' +
                                //       'data-cliente_how_did_you_meet="' + (data.cliente_how_did_you_meet || 'No disponible') + '" ' +
                                //       'data-cliente_instagram_handle="' + (data.cliente_instagram_handle || 'No compartido') + '" ' +
                                //       'data-cliente_look_preference="' + (data.cliente_look_preference || 'No disponible') + '" ' +
                                //       'data-cliente_service_interest="' + (data.cliente_service_interest || 'No disponible') + '" ' +
                                //       'data-cliente_submission_date="' + (data.cliente_submission_date || 'No disponible') + '" ' +
                                //       'data-cliente_time_appointment="' + (data.cliente_time_appointment || 'No disponible') + '" ' +
                                //       'data-cliente_wedding_date="' + (data.cliente_wedding_date || 'No disponible') + '" ' +
                                //       'data-cliente_wedding_location="' + (data.cliente_wedding_location || 'No disponible') + '" ' +
                                //       'data-cliente_wedding_planner="' + (data.cliente_wedding_planner || 'No disponible') + '" ' +
                                //       '>Ver más info</button>' +
                                //       '<button class="btn btn-warning btn-sm btn-reagendar" data-idclie="' + data.idclie + '" data-id="' + data.id + '"  data-idusu="' + data.idusu + '"  >Reagendar</button>';
                            },
                            title: 'Acciones' // Título para la columna de acciones
                        }
                    ],
                    responsive: true,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-MX.json' // Traducción al español
                    }
                });
            } else {
                // Si no hay datos, mostramos un mensaje o hacemos alguna acción
                $('#pendingAppointmentsTable').html('<tr><td colspan="6">No hay datos disponibles</td></tr>');
                $('#attendedAppointmentsTable').html('<tr><td colspan="6">No hay datos disponibles</td></tr>');

            }
        },
        error: function(xhr, status, error) {
            // Manejo de errores si la llamada Ajax falla
            console.error("Error en la solicitud Ajax: " + error);
        }
    });

    $('#pendingAppointmentsTable').on('click', '.btn-cambiar-estatus', function() {
    var id = $(this).data('id');
    var idusu = $(this).data('idusu');
    var idclie = $(this).data('idclie');
    var estatus = $(this).data('estatus');

    console.log("Estatus ",estatus);
  

    // Mostrar el modal SweetAlert
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¿Quieres cambiar el estatus a atendido?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cambiar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Realizar la petición AJAX si el usuario confirma
            $.ajax({
                url: 'cambiar-estatus.php', // Ruta al archivo PHP
                type: 'POST',
                data: {
                    id: id
                },
                success: function(response) {
                    // Convertir la respuesta a JSON
                    var res = JSON.parse(response);
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
                        // Aquí podrías actualizar la tabla o la interfaz según sea necesario
                    } else {
                        // Si hay un error, mostrar un mensaje de error
                        Swal.fire(
                            'Error!',
                            res.message,
                            'error'
                        );
                    }
                },
                error: function() {
                    Swal.fire(
                        'Error!',
                        'Hubo un problema al realizar la solicitud.',
                        'error'
                    );
                }
            });
        }
    });
});


    // Delegación del evento click para los botones "Ver más info"
    $('#pendingAppointmentsTable, #attendedAppointmentsTable').on('click', '.btn-vermas', function() {
        // Obtener los valores directamente desde los atributos data-*
        var id = $(this).data('id');
        var idusu = $(this).data('idusu');
        var idclie = $(this).data('idclie');

        var nombre = $(this).data('nombre');
        var telefono = $(this).data('telefono');
        var email = $(this).data('email');
        var fecha = $(this).data('fecha');
        var hora = $(this).data('hora');
        var asesor = $(this).data('asesor');
        var nota = $(this).data('nota');
        var titulo = $(this).data('titulo');      
        var cliente_names = $(this).data('cliente_names');
        var cliente_telefono = $(this).data('cliente_telefono');
        var cliente_email_address = $(this).data('email_address');
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

        
        
        
        
        var formattedDate = moment(fecha + ' ' + hora).format('DD-MM-YYYY hh:mm a');

        // Formatear los datos para mostrarlos en el modal
        var htmlContent = '<div>';
         htmlContent += '<div class="my-2">';
         htmlContent += '<h3><strong>Datos de la cita</strong></h3>';
        htmlContent += '<div class="d-flex justify-content-between align-items-center">' +
    '<p class="m-1"><strong>Día de la cita:</strong> ' + formattedDate + '</p>' +
    '<div class="btn btn-primary btn-reagendar"  data-idclie="' + idclie + '" data-id="' + id + '" data-idusu="' + idusu + '">Reagendar</div>' +
    '</div>';
          htmlContent += '<div class="d-flex justify-content-between mt-1">'+
        '<p class="m-1"><strong>Nombre del cliente:</strong> '+cliente_names+'</p></div>';
         htmlContent += '<div class="d-flex justify-content-between mt-1">'+
        '<p class="m-1"><strong>Teléfono:</strong> '+cliente_telefono+'</p></div>';
         htmlContent += '<div class="d-flex justify-content-between mt-1">'+
        '<p class="m-1"><strong>Correo:</strong> '+cliente_email_address+'</p></div>';
        htmlContent += '<div class="d-flex justify-content-between mt-1">'+
        '<p class="m-1"><strong>Asesor asignado:</strong> '+asesor+'</p></div>';
        htmlContent += '<div class="d-flex justify-content-between mt-1">'+
        '<p class="m-1"><strong>Nota:</strong> '+nota+'</p></div>';
         htmlContent +='</div><hr>'
         
         
        var formattedWedding = moment(cliente_wedding_date).format('DD-MM-YYYY hh:mm a');

         htmlContent += '<div class="my-3">';
         htmlContent += '<h3><strong>Datos del evento</strong></h3>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Día del evento:</strong> ' + formattedWedding + '</p>'
         +'</div>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Lugar del evento:</strong> ' + cliente_wedding_location + '</p>'
         +'</div>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Total de invitados:</strong> ' + cliente_guests_count + '</p>'
         +'</div>';
        htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Servicio de interés:</strong> ' + cliente_service_interest + '</p>'
         +'</div>';
          htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Estilo de fotografía:</strong> ' + cliente_look_preference + '</p>'
         +'</div>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Planificador de boda:</strong> ' + cliente_wedding_planner + '</p>'
         +'</div>';
          htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>¿Cómo nos conoció?:</strong> ' + cliente_hear_about_us + '</p>'
         +'</div>';
          htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>¿Cómo se conocieron?:</strong> ' + cliente_how_did_you_meet + '</p>'
         +'</div>';
           htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Actividades de pareja:</strong> ' + cliente_couple_activities + '</p>'
         +'</div>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Película o canción favorita:</strong> ' + cliente_favorite_movie_or_song + '</p>'
         +'</div>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Instagram:</strong> ' + cliente_instagram_handle + '</p>'
         +'</div>';
         htmlContent += '<div class="d-flex justify-content-between align-items-center">'+
         '<p class="m-1"><strong>Detalles adicionales:</strong> ' + cliente_additional_details + '</p>'
         +'</div>';
      
        htmlContent += '</div>';

        // Mostrar los datos en el modal
        $('#modalContent').html(htmlContent);
        $('#verMasModal').modal('show');
    });
    
$(document).on('click', '.btn-reagendar, #btn-reagendar', function() {
    var id = $(this).data('id'); // id del meet
    var idusu = $(this).data('idusu'); // id de usuario
    var idclie = $(this).data('idclie'); // id de cliente
      var fecha = $(this).data('fecha');
    var hora = $(this).data('hora');
    
    if(idusu == 0 || idusu == "0"){
        // cambiar el título
        $('#reagendarModal').text('Asignar nuevo vendedor');
        $('#confirmar-reagendar').text('Asignar');
                $('#last_date').text('Fecha anterior: '+fecha);
        $('#last_time').text('Hora anterior: '+hora);

        
        
    }
    
    if (idusu != 0) {
    // Mostrar el nuevo botón de reagendar
  if ($('#reagendarBtn').length === 0) { // Verifica si el botón con el ID "reagendarBtn" ya existe
    // Mostrar el nuevo botón de reagendar solo si no existe
    $('#modal-reagendar-footer #btnReag').append('<button type="button" class="btn btn-warning" id="reagendarBtn">Mandar correo de reagenda</button>');
  }

    // Acción al hacer clic en el botón de reagendar
    $('#reagendarBtn').on('click', function() {
        // Mostrar la alerta de confirmación
        Swal.fire({
            title: '¿Estás seguro de mandar el correo?',
            text: "Una vez confirmado, se enviará el correo de reagendamiento.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, enviar correo',
            cancelButtonText: 'No, cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Si el usuario confirma, hacer la solicitud AJAX
               $.ajax({
    url: 'enviar-correo-reagenda.php',
    method: 'POST',
    data: {
        id: id,
        idclie: idclie
    },
    success: function(response) {
        console.log(response)
        // Parsear la respuesta JSON
        var res = JSON.parse(response);

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
    error: function(xhr, status, error) {
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
    usuarios.forEach(function(usuario) {
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






    </script>
</body>
</html>
