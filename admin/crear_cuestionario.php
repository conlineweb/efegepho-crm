<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Mexico/General');
include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexi贸n a la base de datos

$tipoUsuario = $_SESSION['tus'];
$idUsu = $_SESSION['uid'];


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

// Realizar la consulta SQL para obtener el registro con id = 0 de la tabla 'cuestionario'
$sqlCuestionario = "SELECT * FROM cuestionario WHERE id = 1";
$resultCuestionario = $conn->query($sqlCuestionario);

// Verificar si se obtuvo un resultado
if ($resultCuestionario->num_rows > 0) {
    // Si se obtiene el registro, lo almacenamos en un array
    $row = $resultCuestionario->fetch_assoc();
    
    // Decodificar el JSON del campo 'cuestionario' para obtener los datos como un array de PHP
    $cuestionario = json_decode($row['preguntas'], true); // El 'true' convierte el JSON en un array asociativo
    
    
    // Aquí podrías hacer algo más con los datos del cuestionario, como mostrarlos en una vista
} else {
   $cuestionario = [];
}










// Cerrar la conexión
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuestionario</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
   <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<style>
/* Cambia solo la altura del modal */
.modal-dialog {
    max-height: 80vh; /* Limita la altura máxima al 80% de la ventana del navegador */
    margin-top: 10vh; /* Centra el modal verticalmente con un pequeño margen superior */
}

/* Limita la altura del cuerpo del modal (donde va el contenido) */
.modal-body {
   font-family: 'Cormorant Garamond', serif;
    background-color: #ebebeb;
    color: white;
    max-height: 70vh;  /* Asegura que el contenido del modal tenga un máximo de 70% de la altura de la ventana */
    overflow-y: auto;  /* Habilita el scroll solo si el contenido excede */
    padding: 0 110px;
}

</style>
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-12 order-md-1 order-last">
                <h3>Cuestionario (Español)</h3>
               <br>
            </div>
            <div class="col-12 col-md-12 order-md-2 order-first">
           
            </div>
        </div>
    </div>
    <div class="card">
            <!--<div class="card-header">-->
            <!--    <h4 class="card-title">Crear Cuestionario</h4>-->
            <!--</div>-->
            <div class="card-body">
      
    <div id="contenedor-preguntas"></div>
         <div id="botones-preguntas">
            <button id="btn-file" class="btn btn-primary">Subir Archivo</button>
            <button id="btn-radio" class="btn btn-primary">Opciones (Radio)</button>
            <button id="btn-checkbox" class="btn btn-primary">Múltiples Opciones (Checkbox)</button>
            <button id="btn-texto" class="btn btn-primary">Pregunta Abierta (Texto)</button>
        </div>
         <button id="btn-guardar" class="btn btn-success mt-3">Guardar Cuestionario</button>
            </div>
        </div>
  
    
    <!-- Modal -->
<!-- Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg"> <!-- Se mantiene el tamaño predeterminado -->
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewModalLabel">Vista previa del cuestionario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
       
        <!-- Aquí se insertará el contenido generado -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" id="guardarCuestionario">Guardar encuesta</button>
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
$(document).ready(function() {
    
    
    let cuestionarioBD = <?php echo json_encode($cuestionario); ?>;
    let cuestionarioGenerado = []

    // Generar la estructura del cuestionario al cargar la página
    if(cuestionarioBD.length > 0 )generarCuestionario(cuestionarioBD);

  function generarCuestionario(cuestionario) {
    $('#contenedor-preguntas').empty(); // Limpiar el contenedor de preguntas

    cuestionario.forEach(function(pregunta, index) {
        agregarPregunta(pregunta.tipo, pregunta.pregunta, pregunta.opciones, pregunta.requerida); // Pasar pregunta.pregunta como segundo argumento
    });
}
    
    
    
    
    
    $('#btn-file').click(function() {
        agregarPregunta('file');
    });

    $('#btn-radio').click(function() {
        agregarPregunta('radio');
    });

    $('#btn-checkbox').click(function() {
        agregarPregunta('checkbox');
    });

    $('#btn-texto').click(function() {
        agregarPregunta('texto');
    });

     function agregarPregunta(tipo, preguntaTexto = '', opciones = [], requerida = false) { 
    let bloque = $('<div class="bloque-pregunta card mb-3"></div>');
    let cardBody = $('<div class="card-body"></div>');

    let numPregunta = $('.bloque-pregunta').length + 1;
    let tipoPregunta = tipo === 'file' ? 'Archivo' :
        tipo === 'radio' ? 'Opciones (Radio)' :
            tipo === 'checkbox' ? 'Múltiples Opciones (Checkbox)' : 'Texto';
    let etiquetaPregunta = $('<p class="mb-1">Pregunta ' + numPregunta + ' (' + tipoPregunta + '):</p>');
    cardBody.append(etiquetaPregunta);

    let preguntaInput = $('<input type="text" class="form-control mb-2" placeholder="Escribe la pregunta..." value="' + preguntaTexto + '">');
    cardBody.append(preguntaInput);

    // Agregar checkbox "requerida"
    let requeridaInput = $('<div class="form-check"><input type="checkbox" class="form-check-input" ' + (requerida ? 'checked' : '') + '> <label class="form-check-label">Pregunta requerida</label></div>');
    cardBody.append(requeridaInput);

    // Resto de los tipos de preguntas...
    switch (tipo) {
        case 'file':
            let archivoInput = $('<input type="file" class="form-control" disabled>');
            cardBody.append(archivoInput);
            break;
        case 'radio':
        case 'checkbox':
            agregarOpciones(cardBody, tipo, opciones);
            break;
        case 'texto':
            let textoInput = $('<input type="text" class="form-control" disabled>');
            cardBody.append(textoInput);
            break;
    }

    let eliminarPreguntaBtn = $('<button class="btn btn-danger btn-sm mt-2">Eliminar Pregunta</button>');
    cardBody.append(eliminarPreguntaBtn);

    eliminarPreguntaBtn.click(function() {
        bloque.remove();

        // Reorganizar los números de pregunta
        $('.bloque-pregunta').each(function(index) {
            let tipoPregunta = $(this).find('button[data-tipo-pregunta]').data('tipo-pregunta') || 'Texto';
            tipoPregunta = tipoPregunta === 'file' ? 'Archivo' : 
                           tipoPregunta === 'radio' ? 'Opciones (Radio)' :
                           tipoPregunta === 'checkbox' ? 'Múltiples Opciones (Checkbox)' : 'Texto';
            $(this).find('p:first').text('Pregunta ' + (index + 1) + ' (' + tipoPregunta + '):');
        });
    });

    bloque.append(cardBody);
    $('#contenedor-preguntas').append(bloque);
}


  function agregarOpciones(cardBody, tipo, opciones = []) {
    let divOpciones = $('<div class="opciones"></div>');
    cardBody.append(divOpciones);

    let agregarBoton = $('<button class="btn btn-secondary btn-sm mt-2 fw-bold " data-tipo-pregunta="' + tipo + '" style="margin-right:10px">Agregar Opción</button>');
    cardBody.append(agregarBoton);
    let numOpcion = 0;

    // Agregar opciones existentes
    opciones.forEach(function(opcion) {
        numOpcion++;
        let opcionInput = $('<input type="text" class="form-control mt-1" placeholder="Escribe la opción..." style="width: 80%;" value="' + opcion + '">');
        let etiquetaOpcion = $('<p class="mb-1 me-2">Opción ' + numOpcion + ':</p>');
        let eliminarBoton = $('<button class="btn btn-danger btn-sm ms-1">Eliminar</button>');
        let divOpcion = $('<div class="mt-1 d-flex align-items-center"></div>');
        divOpcion.append(etiquetaOpcion).append(opcionInput).append(eliminarBoton);
        divOpciones.append(divOpcion); // Agregar al contenedor de opciones

        eliminarBoton.click(function() {
            divOpcion.remove();
            numOpcion--;
            $(this).closest('.opciones').find('.mt-1').each(function(index) {
                $(this).find('p:first').text('Opción ' + (index + 1) + ':');
            });
        });
    });

    agregarBoton.click(function() {
        numOpcion++;
        let opcionInput = $('<input type="text" class="form-control mt-1" placeholder="Escribe la opción..." style="width: 80%;">');
        let etiquetaOpcion = $('<p class="mb-1 me-2">Opción ' + numOpcion + ':</p>');
        let eliminarBoton = $('<button class="btn btn-danger btn-sm ms-1">Eliminar</button>');
        let divOpcion = $('<div class="mt-1 d-flex align-items-center"></div>');
        divOpcion.append(etiquetaOpcion).append(opcionInput).append(eliminarBoton);
        divOpciones.append(divOpcion); // Agregar al contenedor de opciones

        eliminarBoton.click(function() {
            divOpcion.remove();
            numOpcion--;
            $(this).closest('.opciones').find('.mt-1').each(function(index) {
                $(this).find('p:first').text('Opción ' + (index + 1) + ':');
            });
        });
    });
}
    
$('#btn-guardar').click(function() {
    let cuestionario = [];
    cuestionarioGenerado = [];
    $('#previewModal .modal-body').html('');
    
    $('.bloque-pregunta').each(function() {
        let pregunta = $(this).find('input[type="text"]:first').val();
        let tipo = $(this).find('input[type="file"]').length ? 'file' :
            ($(this).find('button[data-tipo-pregunta]').data('tipo-pregunta') || 'text');
        let opciones = [];
        $(this).find('input[type="text"]:not(:first)').each(function() {
            opciones.push($(this).val());
        });
        let requerida = $(this).find('input[type="checkbox"]').prop('checked'); // Estado del checkbox de "requerida"
        
        cuestionario.push({ pregunta: pregunta, tipo: tipo, opciones: opciones, requerida: requerida });
    });
    
    cuestionarioGenerado = cuestionario;
    // Muestra un modal con la vista previa de la encuesta
    let encuestaHTML = $('<div id="encuesta" class="mt-4"></div>');
    cuestionario.forEach(function(pregunta, index) {
        let preguntaDiv = $('<div class="bloque-pregunta card"></div>');
        let cardBody = $('<div class="m-3"></div>');
        cardBody.append('<p class="fw-bold">' + pregunta.pregunta + '</p>');

        if (pregunta.tipo === 'file') {
            cardBody.append('<input type="file" class="form-control">');
        } else if (pregunta.tipo === 'radio' || pregunta.tipo === 'checkbox') {
            pregunta.opciones.forEach(function(opcion, indexOpcion) {
                let inputType = pregunta.tipo === 'radio' ? 'radio' : 'checkbox';
                let opcionDiv = $('<div class="form-check"></div>');
                opcionDiv.append('<input type="' + inputType + '" class="form-check-input" name="pregunta' + index + '" id="opcion' + index + '-' + indexOpcion + '">');
                opcionDiv.append('<label class="form-check-label" for="opcion' + index + '-' + indexOpcion + '">' + opcion + '</label>');
                cardBody.append(opcionDiv);
            });
        } else if (pregunta.tipo === 'text') {
            cardBody.append('<input type="text" class="form-control">');
        }

        // Mostrar si la pregunta es requerida
        if (pregunta.requerida) {
            cardBody.append('<p class="text-danger">* Esta pregunta es requerida.</p>');
        }

        preguntaDiv.append(cardBody);
        encuestaHTML.append(preguntaDiv);
    });

    // Inserta el contenido generado dentro del modal
    $('#previewModal .modal-body').html(encuestaHTML);

    // Muestra el modal
    $('#previewModal').modal('show');
});


// Capturar el evento del botón "Guardar encuesta"
$('#guardarCuestionario').click(function() {
    // Obtener los datos del cuestionario desde el modal
    

    // Mostrar los datos del cuestionario en la consola o hacer algo con ellos
    $.ajax({
    url: 'guardar_cuestionario.php',  // Archivo PHP que recibirá los datos
    type: 'POST',
    data: { cuestionario: JSON.stringify(cuestionarioGenerado), id:1 },  // Convertir el array a JSON
    success: function(res) {
        // Manejar la respuesta del servidor
        let response = JSON.parse(res);
                console.log('Respuesta del servidor:', response);

        // Si la respuesta es 'success', mostramos un mensaje de éxito
        if (response.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: 'Cuestionario guardado correctamente.',
                confirmButtonText: 'OK'
            });
        } else {
            // Si la respuesta es 'error', mostramos un mensaje de error
            Swal.fire({
                icon: 'error',
                title: '¡Error!',
                text: 'Hubo un error al guardar el cuestionario.',
                confirmButtonText: 'Reintentar'
            });
        }

        // Cerrar el modal después de guardar
        $('#previewModal').modal('hide');
    },
    error: function(xhr, status, error) {
        console.log('Error en la solicitud AJAX:', error);
        
        // Si ocurre un error con la solicitud AJAX, mostramos un SweetAlert de error
        Swal.fire({
            icon: 'error',
            title: '¡Error de conexión!',
            text: 'Ocurrió un error al guardar el cuestionario.',
            confirmButtonText: 'OK'
        });
    }
});

   
    
    // Cerrar el modal después de guardar
    $('#previewModal').modal('hide');
        });

    });



    </script>
</body>
</html>
