<?php

include 'menu.php';
include 'conn.php'; 

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['login']) || $_SESSION['login'] === false) {
    header('Location: ingreso.php');
   exit(); 
 }
 $tipoUsuario = $_SESSION['tus'];
 
 if ($tipoUsuario !== "0") { 
    header('Location: index.php');
    exit();
}
 
$userid = $_SESSION['uid'];

$sql = "SELECT * FROM cuestionario";
$stmt = $conn->prepare($sql); 
$stmt->execute(); 

$result = $stmt->get_result(); 

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cuestionarios[] = $row; 
    }
} else {
    echo "No se encontraron usuarios.";
}
?>           
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta</title>
</head>

<!-- HTML para la tabla -->
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Consulta Cuestionarios</h3>
              <br>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                
            </div>
        </div>
    </div>
    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="container">
                   <div class ="d-flex">
                   </div><br><br>
                       <table id="tablaCuestionarios" class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Numero Cuestionario</th>
                                <th>Titulo</th>
                                <th>Idioma</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
    
<!-- Modal para editar usuario -->
<div class="modal fade" id="editarCuestionarioModal" tabindex="-1" aria-labelledby="editarCuestionarioModalLabel" aria-hidden="true">
    <input type="hidden" name="id" id="id">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editarCuestionarioModalLabel">Editar Cuestionario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editarCuestionarioForm">
                    <input type="hidden" id="formulario_id" name="formulario_id">
                    <div class="mb-3">
                        <label for="cuestionario" class="form-label">Numero cuestionario</label>
                        <input type="text" class="form-control" id="id" name="id" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="contrasena" class="form-label">Titulo</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required>
                    </div> 
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Idioma</label>
                        <select class="form-select idioma-select" style="width: 160px;">
                        <option value="0" required>Español</option>
                        <option value="1" required>Inglés</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="introduccion" class="form-label">Introduccion</label>
                        <textarea id="introduccion" rows="3" cols="100" class="form-control mb-2" required></textarea>
                    </div>
                    <div id="contenedor-preguntas" class="mb-3"></div>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                    <button type="button" id="btn-file" class="btn btn-primary">Subir Archivo</button>
                    <button type="button" id="btn-radio" class="btn btn-primary">Opciones (Radio)</button>
                    <button type="button" id="btn-checkbox" class="btn btn-primary">Múltiples Opciones (Checkbox)</button>
                    <button type="button" id="btn-texto" class="btn btn-primary">Pregunta Abierta</button>
                    <button type="button" id="btn-titulo" class="btn btn-primary">Agrega un titulo</button>
                    <button type="button" id="btn-introduccion" class="btn btn-primary">Agrega una Introduccion </button>

                    <button type="button" class="btn btn-success mt-3" id="guardarCuestionario">Guardar encuesta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
      </div>
    </div>
  </div>
</div>



<?php

include 'footer.php';

?>
   

<!-- Incluir librerías de jQuery y DataTables -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Asegúrate de que esta línea esté antes -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">

<script>
// Pasa los datos PHP a JavaScript
const cuestionarios = <?php echo json_encode($cuestionarios); ?>;
console.log(cuestionarios)

// Llenar la tabla con los datos de cuestionarios
$(document).ready(function() {
    // Primero, destruimos cualquier instancia de DataTables si existe
    if ($.fn.dataTable.isDataTable('#tablaCuestionarios')) {
        $('#tablaCuestionarios').DataTable().clear().destroy();
    }

    // Agregar filas a la tabla
    cuestionarios.forEach(cuestionario => {
        $('#tablaCuestionarios tbody').append(`
            <tr>
                <td>${cuestionario.id}</td>
                <td>${cuestionario.titulo}</td>
                <td>${cuestionario.idioma === 0 ? 'Español' : 'Ingles'}</td>
                <td>
                    <button class="btn btn-primary" onclick="editarCuestionario(${cuestionario.id})">Editar</button>
                    <button class="btn btn-dark mx-2 my-1" onclick="eliminarCuestionario(${cuestionario.id})">Eliminar</button>
                    <button class="btn btn-secondary mx-2 my-1" onclick="vistaPrevia(${cuestionario.id})">Vista Previa</button>
                    <button class="btn btn-primary" onclick="copiarCuestionario(${cuestionario.id})">Copiar</button>
                </td>
            </tr>
        `);
    });

    // Inicializar DataTables
    $('#tablaCuestionarios').DataTable();
});

function editarCuestionario(id) {
window.location.href = "https://citas.efegepho.com.mx/admin/registro_cuestionario.php?id=" + id;
            // const cuestionario = cuestionarios.find(c => c.id === id);
            // console.log('Cuestionario a editar:', cuestionario);
            
            // if (typeof cuestionario.preguntas === 'string') {
            //     try {
            //         cuestionario.preguntas = JSON.parse(cuestionario.preguntas);
            //     } catch (e) {
            //         console.error("Error al parsear las preguntas:", e);
            //         cuestionario.preguntas = [];
            //     }
            // }
        
            // $('#editarCuestionarioModal #id').val(cuestionario.id);
            // $('#editarCuestionarioModal #titulo').val(cuestionario.titulo);
            // $('.idioma-select').val(cuestionario.idioma);
            // $('#editarCuestionarioModal #introduccion').val(cuestionario.introduccion);
            
            // $('#contenedor-preguntas').empty();
            
            // if (cuestionario.preguntas && cuestionario.preguntas.length > 0) {
            //     cuestionario.preguntas.forEach(pregunta => {
            //         agregarPregunta(
            //             pregunta.tipo, 
            //             pregunta.pregunta || '', 
            //             pregunta.opciones || [], 
            //             pregunta.requerida || false
            //         );
            //     });
            // }
        
            // $('#editarCuestionarioModal').modal('show');
}

function mostrarPreguntas(preguntas) {
    $('#contenedor-preguntas').empty();

    preguntas.forEach(function(pregunta) {
        agregarPregunta(
            pregunta.tipo, 
            pregunta.pregunta || '', 
            pregunta.opciones || [], 
            pregunta.requerida || false
        );
    });
}

function copiarCuestionario(id) {
    const cuestionario = cuestionarios.find(c => c.id == id);
    
    if (!cuestionario) {
        Swal.fire('Error', 'No se encontró el cuestionario', 'error');
        return;
    }

    Swal.fire({
        title: '¿Copiar cuestionario?',
        text: '¿Estás seguro que deseas copiar este cuestionario?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, copiar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar carga mientras se procesa
            Swal.fire({
                title: 'Copiando...',
                html: 'Por favor espera mientras se copia el cuestionario.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Preparar los datos para copiar
            let preguntas = [];
            try {
                preguntas = typeof cuestionario.preguntas === 'string' ? 
                           JSON.parse(cuestionario.preguntas) : 
                           cuestionario.preguntas;
            } catch (e) {
                console.error("Error al parsear preguntas:", e);
                preguntas = [];
            }

            // Enviar datos al servidor para crear la copia
            $.ajax({
                type: 'POST',
                url: 'copiar_cuestionario.php',
                contentType: 'application/json',
                data: JSON.stringify({
                    id_original: cuestionario.id,
                    titulo: cuestionario.titulo + ' (Copia)',
                    idioma: cuestionario.idioma,
                    introduccion: cuestionario.introduccion,
                    preguntas: preguntas
                }),
                success: function(response) {
                    Swal.close();
                    if (response.status === 'success') {
                        Swal.fire({
                            title: 'Éxito',
                            text: response.message,
                            icon: 'success'
                        }).then(() => {
                            location.reload(); // Recargar para ver la nueva copia
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function(xhr) {
                    Swal.close();
                    Swal.fire('Error', 'Hubo un error al copiar el cuestionario', 'error');
                    console.error(xhr.responseText);
                }
            });
        }
    });
}

function eliminarCuestionario(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: 'POST',
                url: 'eliminar_cuestionario.php',
                contentType: 'application/json', // 👈 importante
                data: JSON.stringify({ id: id }), // 👈 importante
                dataType: 'json', // 👈 Esto fuerza a jQuery a interpretar la respuesta como JSON
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            title: 'Eliminado',
                            text: response.message,
                            icon: 'success'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error'
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        title: 'Error',
                        text: 'Hubo un error al eliminar el cuestionario.',
                        icon: 'error'
                    });
                    console.error(xhr.responseText);
                }
            });
        }
    });
}


function guardarCuestionario() {
    const id = $('#editarCuestionarioModal #id').val();
    const titulo = $('#editarCuestionarioModal #titulo').val();
    const idioma = $('.idioma-select').val();
    const introduccion = $('#editarCuestionarioModal #introduccion').val();
    
    const preguntas = [];
    
    $('.bloque-pregunta').each(function() {
        let tipo = $(this).data('tipo-pregunta');
        let pregunta;
        let opciones = [];
        let requerida = false;

        if (tipo === 'introduccion') {
            // Introducción está en textarea
            pregunta = $(this).find('textarea').val();
        } else if (tipo === 'titulo') {
            pregunta = $(this).find('input.form-control.mb-2').first().val();
        } else {
            // Pregunta normal con input
            pregunta = $(this).find('input.form-control.mb-2').first().val();

            // Opciones (si las hay)
            $(this).find('.opciones input[type="text"]').each(function() {
                opciones.push($(this).val());
            });

            // Requerida
            requerida = $(this).find('input[type="checkbox"]').prop('checked');
        }
        
        const preguntaObj = {
            tipo: tipo, // 👈 Ahora siempre tendrá el tipo correcto
            pregunta: pregunta,
            requerida: requerida
        };
        
        if (tipo === 'radio' || tipo === 'checkbox') {
            preguntaObj.opciones = [];
            $(this).find('.opciones input[type="text"]').each(function() {
                preguntaObj.opciones.push($(this).val());
            });
        }
        
        preguntas.push(preguntaObj);
    });
    
    // Validar datos básicos
    if (!titulo || !introduccion) {
        Swal.fire('Error', 'El título y la introducción son requeridos', 'error');
        return;
    }
    
    // Validar preguntas
    if (preguntas.length === 0) {
        Swal.fire('Error', 'Debe agregar al menos una pregunta', 'error');
        return;
    }
    
    // Enviar datos al servidor
    $.ajax({
        type: 'POST',
        url: 'guardar_cuestionario.php', // Asegúrate de que este es el nombre correcto de tu archivo PHP
        contentType: 'application/json',
        data: JSON.stringify({
            id: id,
            titulo: titulo,
            idioma: idioma,
            introduccion: introduccion,
            preguntas: preguntas
        }),
        success: function(response) {
            if (response.status === 'success') {
                Swal.fire({
                    title: 'Éxito',
                    text: response.message,
                    icon: 'success'
                }).then(() => {
                    $('#editarCuestionarioModal').modal('hide');
                    location.reload(); // Recargar la página para ver los cambios
                });
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function(xhr) {
            Swal.fire('Error', 'Hubo un error al guardar el cuestionario', 'error');
            console.error(xhr.responseText);
        }
    });
}

$('#guardarCuestionario').click(guardarCuestionario);





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
     $('#btn-titulo').click(function() {
        agregarPregunta('titulo');
    });
     $('#btn-introduccion').click(function() {
        agregarPregunta('introduccion');
    });
    

    function agregarPregunta(tipo, preguntaTexto = '', opciones = [], requerida = false) {
    let bloque = $('<div class="bloque-pregunta card mb-3"></div>');
    bloque.attr('data-tipo-pregunta', tipo); // <-- Para identificar luego

    let cardBody = $('<div class="card-body"></div>');

    // Contar bloques existentes del mismo tipo
    let countTipo = $('.bloque-pregunta').filter(function () {
        return $(this).data('tipo-pregunta') === tipo;
    }).length + 1;
    
    
    
   // Definir etiqueta según tipo
    let tipoEtiqueta = tipo === 'file' ? 'Pregunta' :
                       tipo === 'radio' ? 'Pregunta' :
                       tipo === 'checkbox' ? 'Pregunta' :
                       tipo === 'texto' ? 'Pregunta' :
                       tipo === 'titulo' ? 'Título' :
                       tipo === 'introduccion' ? 'Introducción' : 'Pregunta';

    let tipoNombre = tipo === 'file' ? 'Archivo' :
                     tipo === 'radio' ? 'Opciones (Radio)' :
                     tipo === 'checkbox' ? 'Múltiples Opciones (Checkbox)' :
                     tipo === 'texto' ? 'Texto' :
                     tipo === 'titulo' ? 'Título' :
                     tipo === 'introduccion' ? 'Introducción' : 'Texto';

    let etiqueta = $('<p class="mb-1">' + tipoEtiqueta + ' ' + countTipo + ' (' + tipoNombre + '):</p>');
    cardBody.append(etiqueta);

    let placeholder = tipo === 'titulo' ? 'Escribe el título...' :
                  tipo === 'introduccion' ? 'Escribe la introducción...' :
                  'Escribe la pregunta...';

    let preguntaInput;

    if (tipo === 'introduccion') {
    preguntaInput = $('<textarea class="form-control mb-2" placeholder="' + placeholder + '">' + preguntaTexto + '</textarea>');
} else {
    preguntaInput = $('<input type="text" class="form-control mb-2" placeholder="' + placeholder + '" value="' + preguntaTexto + '">');
}

cardBody.append(preguntaInput);

    // Checkbox de requerida
    let requeridaInput = $('<div class="form-check"><input type="checkbox" class="form-check-input" ' + (requerida ? 'checked' : '') + '> <label class="form-check-label">Pregunta requerida</label></div>');
    if (tipo !== 'titulo' && tipo !== 'introduccion') {
        cardBody.append(requeridaInput);
    }

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
         reorganizarNumeros();
    });

    bloque.append(cardBody);
    $('#contenedor-preguntas').append(bloque);
}

function reorganizarNumeros() {
    let contadores = {
        titulo: 0,
        introduccion: 0,
        texto: 0,
        file: 0,
        radio: 0,
        checkbox: 0
    };

    $('.bloque-pregunta').each(function () {
        let tipo = $(this).data('tipo-pregunta');
        contadores[tipo]++;

        let tipoEtiqueta = tipo === 'titulo' ? 'Título' :
                           tipo === 'introduccion' ? 'Introducción' : 'Pregunta';

        let tipoNombre = tipo === 'file' ? 'Archivo' :
                         tipo === 'radio' ? 'Opciones (Radio)' :
                         tipo === 'checkbox' ? 'Múltiples Opciones (Checkbox)' :
                         tipo === 'texto' ? 'Texto' :
                         tipo === 'titulo' ? 'Título' :
                         tipo === 'introduccion' ? 'Introducción' : 'Texto';

        $(this).find('p:first').text(tipoEtiqueta + ' ' + contadores[tipo] + ' (' + tipoNombre + '):');
    });
}




  function agregarOpciones(cardBody, tipo, opciones = []) {
    let divOpciones = $('<div class="opciones"></div>');
    cardBody.append(divOpciones);
    
    
    
    let agregarBoton = $('<button type="button" class="btn btn-secondary btn-sm mt-2 fw-bold " data-tipo-pregunta="' + tipo + '" style="margin-right:10px">Agregar Opción</button>');
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

function vistaPrevia(id) {
    const cuestionario = cuestionarios.find(c => c.id == id);
    
    if (!cuestionario) {
        Swal.fire('Error', 'No se encontró el cuestionario', 'error');
        return;
    }
    let preguntas = [];
    try {
        preguntas = typeof cuestionario.preguntas === 'string' ? 
                    JSON.parse(cuestionario.preguntas) : 
                    cuestionario.preguntas;
    } catch (e) {
        console.error("Error al parsear preguntas:", e);
        preguntas = [];
    }

    // Limpiar el modal y preparar HTML
    $('#previewModal .modal-body').html('');
    let html = `
        <div class="encuesta-preview">
            <h3 class="text-center mb-4">${cuestionario.titulo}</h3>
            <div class="text-center mb-4">${cuestionario.introduccion}</div>
            <div class="text-center mb-3">
                <strong>Idioma:</strong> ${cuestionario.idioma == 0 ? 'Español' : 'Inglés'}
            </div>
            
    `;

    // Generar preguntas (solo lectura)
    preguntas.forEach((pregunta, index) => {
        // Aquí chequeamos tipo especial
        if (pregunta.tipo === 'titulo') {
            html += `<div class="mb-3">
                        <h3 class="text-center mb-4">${pregunta.pregunta}</h3>
                    </div>`;
            return; // continuar con siguiente pregunta
        }

        if (pregunta.tipo === 'introduccion') {
            html += `<div class="text-center mb-4>
                        <p class="fst-italic text-secondary">${pregunta.pregunta}</p>
                    </div>`;
            return;
        }

        // Preguntas normales
        html += `<div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">${pregunta.pregunta} <small class="text-muted">(${obtenerTipoPregunta(pregunta.tipo)})</small></h5>`;
        
        if (pregunta.tipo === 'radio' || pregunta.tipo === 'checkbox') {
            pregunta.opciones.forEach((opcion, i) => {
                const inputType = pregunta.tipo === 'radio' ? 'radio' : 'checkbox';
                html += `<div class="form-check">
                            <input class="form-check-input" type="${inputType}" 
                                   name="pregunta_${index}" id="opcion_${index}_${i}" 
                                   disabled> 
                            <label class="form-check-label" for="opcion_${index}_${i}">
                                ${opcion}
                            </label>
                         </div>`;
            });
        } else if (pregunta.tipo === 'file') {
            html += `<div class="mb-2">
                        <label class="form-label">Subir archivo</label>
                        <div class="input-group">
                            <input type="file" class="form-control" disabled style="opacity: 1; background-color: #f8f9fa; cursor: not-allowed;">
                            <span class="input-group-text"><i class="bi bi-upload"></i></span>
                        </div>
                        <small class="text-muted">(Pregunta de tipo archivo)</small>
                     </div>`;
        } else if (pregunta.tipo === 'texto') {
            html += `<div class="mb-2">
                        <input type="text" class="form-control" placeholder="Respuesta de texto" disabled>
                        <small class="text-muted">(Pregunta de tipo texto)</small>
                     </div>`;
        }

        html += `</div></div>`;
    });

    html += `</div>`;
    
    // Mostrar en el modal
    $('#previewModal .modal-body').html(html);
    $('#previewModal').modal('show');
}


// Función auxiliar para obtener el nombre del tipo de pregunta
function obtenerTipoPregunta(tipo) {
    switch(tipo) {
        case 'radio': return 'Selección única';
        case 'checkbox': return 'Selección múltiple';
        case 'file': return 'Archivo';
        case 'texto': return 'Texto abierto';
        default: return tipo;
    }
}





</script>
<!-- Bootstrap CSS -->


<!-- Bootstrap JS y Popper.js -->
<!-- Incluyendo SweetAlert desde CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

