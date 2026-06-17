<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Mexico/General');
include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

$tipoUsuario = $_SESSION['tus'];
$idUsu = $_SESSION['uid'];
$idCuestionario = isset($_GET['id']) ? intval($_GET['id']) : 0;



// Realizar la consulta SQL para obtener el registro con id = 0 de la tabla 'cuestionario'
$sqlCuestionario = "SELECT * FROM cuestionario WHERE id = ?";
$stmt = $conn->prepare($sqlCuestionario);
$stmt->bind_param("i", $idCuestionario);  // "i" = entero
$stmt->execute();

// Obtener resultado
$resultCuestionario = $stmt->get_result();

if ($resultCuestionario->num_rows > 0) {
    $row = $resultCuestionario->fetch_assoc();
    $cuestionario = json_decode($row['preguntas'], true);
    $titulo = $row['titulo'];
    $introduccion = $row['introduccion'];
    $idioma = $row['idioma'];
} else {
    $cuestionario = [];
    $titulo = '';
    $introduccion = '';
    $idioma = '0';
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

.titulo-idioma-container {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

#idioma {
    min-width: 200px;
}

#idioma label {
    font-size: 0.9rem;
    margin-bottom: 3px;
    color: #555;
}



.input-group {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    width: 100%;
}

#contenedorTitulo {
    margin-bottom: 5px;
}


</style>
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-12 order-md-1 order-last">
                <h3>Cuestionario</h3>
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
      
        <div class="row">
    <div class="col-12 col-md-9 mb-3">
        <div class="d-flex flex-column h-100 justify-content-end">
            <label for="titulo" class="form-label">Título</label>
            <input id="titulo" class="form-control" value="<?php echo htmlspecialchars($titulo); ?>">
        </div>
    </div>
    <div class="col-12 col-md-3 mb-3">
        <div class="d-flex flex-column h-100 justify-content-end">
            <label for="opcionIdioma" class="form-label">Selecciona un idioma</label>
            <select class="form-select" id="opcionIdioma">
                <option value="" disabled>Seleccione una opción</option>
                <option value="0" <?php echo $idioma == '0' ? 'selected' : ''; ?>>Español</option>
                <option value="1" <?php echo $idioma == '1' ? 'selected' : ''; ?>>Inglés</option>
            </select>
        </div>
    </div>
</div>
<div id="contenedorIntroduccion"></div>
<br>
<label for="introduccion" class="form-label">Introducción</label>
<textarea id="introduccion" rows="3" cols="90" class="form-control mb-2"><?php echo htmlspecialchars($introduccion); ?></textarea>
             <div id="contenedor-preguntas"></div>
         <div id="botones-preguntas">
             
             
         <br><br>
         
         
         
       
            
         
          </div>
          <br>
             
            <button id="btn-file" class="btn btn-primary">Subir Archivo</button>
            <button id="btn-radio" class="btn btn-primary">Opciones (Radio)</button>
            <button id="btn-checkbox" class="btn btn-primary">Múltiples Opciones (Checkbox)</button>
            <button id="btn-texto" class="btn btn-primary">Pregunta Abierta (Texto)</button>
            <button id="btn-titulo-introduccion" class="btn btn-primary">Título e Introducción</button>
        </div>
         <button id="btn-guardar" class="btn btn-dark mt-3">Guardar Cuestionario</button>
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
    let idCuestionario = <?php echo $idCuestionario; ?>;
    
    // Contadores para identificadores únicos
    let contadorTitulo = 0;
    let contadorIntroduccion = 0;
    
    let cuestionarioGenerado = {
        titulo: '<?php echo addslashes($titulo); ?>',
        introduccion: '<?php echo addslashes($introduccion); ?>',
        idioma: '<?php echo $idioma; ?>',
        preguntas: <?php echo json_encode($cuestionario); ?>
    };
    
    if(cuestionarioBD.length > 0) {
        // Inicializar contadores basados en datos existentes
        inicializarContadores(cuestionarioBD);
        generarCuestionario(cuestionarioBD);
    }
    
    function inicializarContadores(cuestionario) {
        cuestionario.forEach(function(pregunta) {
            if (pregunta.tipo === 'titulo') {
                // Si ya tiene identifier, usarlo para actualizar contador
                if (pregunta.identifier && pregunta.identifier > contadorTitulo) {
                    contadorTitulo = pregunta.identifier;
                } else if (!pregunta.identifier) {
                    // Si no tiene identifier, incrementar contador para compatibilidad
                    contadorTitulo++;
                }
            } else if (pregunta.tipo === 'introduccion') {
                if (pregunta.identifier && pregunta.identifier > contadorIntroduccion) {
                    contadorIntroduccion = pregunta.identifier;
                } else if (!pregunta.identifier) {
                    contadorIntroduccion++;
                }
            }
        });
    }
  
    function generarCuestionario(cuestionario) {
        $('#contenedor-preguntas').empty();
        cuestionario.forEach(function(pregunta, index) {
            agregarPregunta(
                pregunta.tipo, 
                pregunta.pregunta, 
                pregunta.opciones, 
                pregunta.requerida,
                pregunta.identifier // Pasar el identifier existente
            );
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

    $('#btn-titulo-introduccion').click(function() {
        // Agregar título con identifier
        agregarPregunta('titulo');
        // Agregar introducción inmediatamente después con identifier
        setTimeout(function() {
            agregarPregunta('introduccion');
        }, 100);
    });
    
    function agregarPregunta(tipo, preguntaTexto = '', opciones = [], requerida = false, identifier = null) {
        let bloque = $('<div class="bloque-pregunta card mb-3"></div>');
        bloque.attr('data-tipo-pregunta', tipo);
        
        // Asignar o generar identifier para títulos e introducciones
        let currentIdentifier = identifier;
        if (tipo === 'titulo') {
            if (currentIdentifier === null) {
                contadorTitulo++;
                currentIdentifier = contadorTitulo;
            }
            bloque.attr('data-identifier', currentIdentifier);
        } else if (tipo === 'introduccion') {
            if (currentIdentifier === null) {
                contadorIntroduccion++;
                currentIdentifier = contadorIntroduccion;
            }
            bloque.attr('data-identifier', currentIdentifier);
        }

        let cardBody = $('<div class="mt-5"></div>');

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

        // Checkbox de requerida (no para título e introducción)
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
            divOpciones.append(divOpcion);

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
            divOpciones.append(divOpcion);

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

        let titulo = $('#titulo').val();
        let introduccion = $('#introduccion').val();
        let idioma = $('#opcionIdioma').val();

        let encuestaHTML = $('<div id="encuesta" class="mt-4"></div>');
        encuestaHTML.append('<h3 class="mb-4 text-center">' + titulo + '</h3>');
        encuestaHTML.append('<div class="mb-4 text-center text-black">' + introduccion + '</div>');
        encuestaHTML.append('<div class="mb-3 text-center text-black"><strong>Idioma:</strong> ' + (idioma === '0' ? 'Español' : 'Inglés') + '</div>');

        $('.bloque-pregunta').each(function() {
            let tipo = $(this).data('tipo-pregunta');
            let identifier = $(this).data('identifier'); // Obtener identifier si existe
            let pregunta;
            let opciones = [];
            let requerida = false;

            if (tipo === 'introduccion') {
                pregunta = $(this).find('textarea').val();
            } else if (tipo === 'titulo') {
                pregunta = $(this).find('input.form-control.mb-2').first().val();
            } else {
                pregunta = $(this).find('input.form-control.mb-2').first().val();
                $(this).find('.opciones input[type="text"]').each(function() {
                    opciones.push($(this).val());
                });
                requerida = $(this).find('input[type="checkbox"]').prop('checked');
            }

            let preguntaObj = {
                pregunta: pregunta,
                tipo: tipo,
                opciones: opciones,
                requerida: requerida
            };

            // Agregar identifier solo para títulos e introducciones
            if ((tipo === 'titulo' || tipo === 'introduccion') && identifier) {
                preguntaObj.identifier = identifier;
            }

            cuestionario.push(preguntaObj);
        });

        console.log("Cuestionario:", cuestionario);

        // Construir vista previa
        cuestionario.forEach(function(pregunta, index) {
            let preguntaDiv = $('<div class="bloque-pregunta card mb-3"></div>');
            let cardBody = $('<div class="card-body"></div>');

            if (pregunta.tipo === 'introduccion' || pregunta.tipo === 'titulo') {
                let etiqueta = pregunta.tipo === 'titulo' ? 'h4' : 'h5';
                let textoMostrar = pregunta.pregunta || '(sin texto)';
                // Mostrar identifier en vista previa para debug (opcional)
                if (pregunta.identifier) {
                    textoMostrar += ' [ID: ' + pregunta.identifier + ']';
                }
                cardBody.append('<' + etiqueta + ' class="fw-bold">' + textoMostrar + '</' + etiqueta + '>');
                preguntaDiv.append(cardBody);
                encuestaHTML.append(preguntaDiv);
                return;
            }

            cardBody.append('<p class="fw-bold">' + pregunta.pregunta + '</p>');

            if (pregunta.tipo === 'file') {
                cardBody.append('<input type="file" class="form-control">');
            } else if (pregunta.tipo === 'radio' || pregunta.tipo === 'checkbox') {
                pregunta.opciones.forEach(function(opcion, idx) {
                    let inputType = pregunta.tipo;
                    let opcionDiv = $('<div class="form-check"></div>');
                    opcionDiv.append('<input class="form-check-input" type="' + inputType + '" name="pregunta' + index + '" id="opcion' + index + idx + '">');
                    opcionDiv.append('<label class="form-check-label" for="opcion' + index + idx + '">' + opcion + '</label>');
                    cardBody.append(opcionDiv);
                });
            } else if (pregunta.tipo === 'textarea') {
                cardBody.append('<textarea class="form-control" rows="4">' + (pregunta.pregunta || '') + '</textarea>');
            } else {
                cardBody.append('<input type="text" class="form-control" value="' + (pregunta.pregunta || '') + '">');
            }
            
            cuestionarioGenerado = {
                titulo: titulo,
                introduccion: introduccion,
                idioma: idioma,
                preguntas: cuestionario
            };

            preguntaDiv.append(cardBody);
            encuestaHTML.append(preguntaDiv);
        });

        $('#previewModal .modal-body').append(encuestaHTML);
        $('#previewModal').modal('show');
    });

    $('#guardarCuestionario').off('click').on('click', function() {
        // Validar datos básicos
        if (!cuestionarioGenerado || 
            !cuestionarioGenerado.titulo || 
            !cuestionarioGenerado.preguntas || 
            !cuestionarioGenerado.idioma ||
            cuestionarioGenerado.preguntas.length === 0) {
            
            Swal.fire({
                icon: 'error',
                title: 'Datos incompletos',
                text: 'Debes agregar un título, seleccionar el idioma y agregar al menos una pregunta',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Validar cada pregunta individualmente
        const hayPreguntasVacias = cuestionarioGenerado.preguntas.some(pregunta => 
            !pregunta.pregunta || pregunta.pregunta.trim() === '');

        const hayCheckboxesInvalidos = cuestionarioGenerado.preguntas.some(pregunta => 
            pregunta.tipo === 'checkbox' && (!pregunta.opciones || pregunta.opciones.length < 2));

        if (hayPreguntasVacias) {
            Swal.fire({
                icon: 'error',
                title: 'Datos incompletos',
                text: 'No puedes dejar preguntas vacías',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (hayCheckboxesInvalidos) {
            Swal.fire({
                icon: 'error',
                title: 'Datos incompletos',
                text: 'Las preguntas de checkbox deben tener al menos 2 opciones',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Mostrar loader
        Swal.fire({
            title: 'Guardando...',
            html: 'Por favor espera mientras guardamos tu cuestionario',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        // Preparar datos para enviar
        const datosEnvio = {
            titulo: cuestionarioGenerado.titulo,
            introduccion: cuestionarioGenerado.introduccion || '',
            idioma: cuestionarioGenerado.idioma || '0',
            preguntas: cuestionarioGenerado.preguntas.map(p => {
                let preguntaObj = {
                    tipo: p.tipo,
                    pregunta: p.pregunta,
                    opciones: p.opciones || [],
                    requerida: p.requerida || false
                };
                
                // Incluir identifier solo si existe
                if (p.identifier) {
                    preguntaObj.identifier = p.identifier;
                }
                
                return preguntaObj;
            }),
            id: idCuestionario
        };

        console.log("Datos a enviar:", datosEnvio);

        // Aquí iría tu código AJAX para enviar al servidor
            $.ajax({
            url: 'guardar_cuestionario.php',
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(datosEnvio),
            success: function(response) {
                Swal.close();
                
                if (response && response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.message || 'Cuestionario guardado correctamente.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        $('#previewModal').modal('hide');
                        // Recargar la página o actualizar la vista
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response?.message || 'Error al guardar el cuestionario',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                let errorMsg = 'Error en la conexión con el servidor';
                
                try {
                    const errResponse = JSON.parse(xhr.responseText);
                    errorMsg = errResponse.message || errorMsg;
                } catch(e) {
                    errorMsg = xhr.statusText || errorMsg;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMsg,
                    confirmButtonText: 'OK'
                });
            }
        });
        Swal.close();
        $('#previewModal').modal('hide');
        
        // Mostrar éxito temporal
        Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: 'Cuestionario procesado correctamente (datos preparados para envío)',
            confirmButtonText: 'OK'
        });
    });
});



    </script>
</body>
</html>