<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Mexico/General');
include 'admin/conn.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int) $_GET['id'];
    $lang = $_GET['lang'];
    $idres= $_GET['idres'];

    $query = "SELECT * FROM contact_form WHERE id = ?";
    $stmt = $conn->prepare($query);

    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $cliente = $resultado->fetch_assoc();
            
            $stmt = $conn->prepare("SELECT * FROM cuestionario WHERE id = ?");
            $stmt->bind_param("i", $lang);
            $stmt->execute();
            $resultCuestionario = $stmt->get_result();
            
            if ($resultCuestionario->num_rows > 0) {
                $row = $resultCuestionario->fetch_assoc();
                
                $cuestionarioData = [
                    'titulo' => $row['titulo'] ?? '',
                    'introduccion' => $row['introduccion'] ?? '',
                    'preguntas' => json_decode($row['preguntas'], true) ?? []
                ];
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $cuestionarioData['preguntas'] = [];
                    error_log("Error decodificando JSON: " . json_last_error_msg());
                }
            } else {
                $cuestionarioData = [
                    'titulo' => 'Cuestionario no disponible',
                    'introduccion' => '',
                    'preguntas' => []
                ];
            }
        } else {
            echo "No se encontró el usuario con ese ID.";
        }
        $stmt->close();
    } else {
        echo "Error al preparar la consulta.";
    }
} else {
    echo "ID inválido o no proporcionado.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuestionario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
   <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;700&display=swap" rel="stylesheet">

</head>
<style>
/* Limita la altura del cuerpo del modal (donde va el contenido) */
body {
    font-family: 'Cormorant Garamond', serif;
    background-color: #ebebeb;
    color: white;
    margin: 0;  /* Asegura que no haya márgenes predeterminados */
}
.bg-image{
    background-image: url(https://lh6.googleusercontent.com/B4nwqeL6pW_eK6uG1b72m3WNDUnjzb0HLEMWb_aGjGLQSNCh1S8ge8X5mOv8wlmr29KGrBDAE5uzGemAAzO4dTOUxjzoLAzk6g3brbMcLt9s9RrPbTrE3ZXYzfF6GXiiNFBXuVal5UM=w1920);
    background-size: cover;
    background-position: center;
    height: 160px;
    
}
.bloque-titulo p {
    font-size:15px;
    margin:0;
    padding:0;
}
#cuestionario{
    margin: 10px 0 ;
}
/* Contenedor de preguntas */
#contenedor-preguntas {
    color: black;
}

/* Estilo para el encabezado, con padding adaptado */
.page-heading {
    padding: 0 35%; /* Añadido para pantallas más grandes */
   
}

@media (max-width: 1260px) {
    .page-heading {
    padding: 0 15%; /* Añadido para pantallas más grandes */
   
}
}
/* Media query para pantallas pequeñas (móviles) */
@media (max-width: 768px) {
    /* Para la página en pantallas más pequeñas */
 
    .page-heading {
        padding: 0 5%; /* Reduce el padding en móviles */
    }

    #contenedor-preguntas {
        /* Añadir algo de espacio a los lados */
        font-size: 17px;  /* Ajusta el tamaño de la fuente para pantallas más pequeñas */
    }
    
   
}

/* Media query para pantallas aún más pequeñas (móviles muy pequeños) */
@media (max-width: 480px) {
    .page-heading {
       /* Aún más pequeño en dispositivos muy pequeños */
        font-size: 18px; /* Tamaño de fuente ajustado */
    }
    
    #contenedor-preguntas {
       
        font-size: 17px; /* Ajusta el tamaño de fuente aún más */
    }
    
    

    /* Se puede ajustar más elementos según el diseño */
}
</style>

<div class="page-heading">
    
    <section id="cuestionario">
        <div class="bloque-pregunta bg-image card mb-4 ">
            <div class=" mb-4 m-3">
        </div>
        </div>
        <div id="contenedor-preguntas" class="mb-5"></div>
    </section>
 
</div>
</body>

</html>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/locale/es.js"></script>


<!-- Bootstrap JS y Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script>
$(document).ready(function() {
    let cuestionarioBD = <?php echo json_encode($cuestionarioData); ?>;
    console.log("Datos del cuestionario:", cuestionarioBD);
   
    if(cuestionarioBD.preguntas && cuestionarioBD.preguntas.length > 0) {
        generarCuestionario(cuestionarioBD);
    }

 function generarCuestionario(cuestionario) {
    $('#contenedor-preguntas').empty();
    
    // Crear header con título e introducción
    let encuestaHTML = `
        <div class="encuesta-header card mb-4">
            <div class="card-body">
                <h2 class="text-center">${cuestionario.titulo}</h2>
                <p class="text-center">${cuestionario.introduccion}</p>
            </div>
        </div>
        <form id="encuesta" class="mt-4">
    `;
    
    let i = 0;
    while (i < cuestionario.preguntas.length) {
        let pregunta = cuestionario.preguntas[i];
        
        // Si la pregunta tiene identifier, buscar todas las preguntas con el mismo identifier
        if (pregunta.identifier !== undefined) {
            encuestaHTML += `<div class="bloque-pregunta card mb-4"><div class="m-3">`;
            
            let identifier = pregunta.identifier;
            let j = i;
            
            // Procesar todas las preguntas consecutivas con el mismo identifier
            while (j < cuestionario.preguntas.length && 
                   cuestionario.preguntas[j].identifier === identifier) {
                let preguntaGrupo = cuestionario.preguntas[j];
                
                if (preguntaGrupo.tipo === 'titulo') {
                    encuestaHTML += `<h3 class="text-center">${preguntaGrupo.pregunta}</h3>`;
                } else if (preguntaGrupo.tipo === 'introduccion') {
                    encuestaHTML += `<p class="text-center">${preguntaGrupo.pregunta}</p>`;
                }
                j++;
            }
            
            encuestaHTML += `</div></div>`;
            i = j; // Avanzar el índice principal
        } else {
            // Pregunta sin identifier, procesarla normalmente
            encuestaHTML += `<div class="bloque-pregunta card mb-4"><div class="m-3">`;

            if (pregunta.tipo === 'titulo') {
                encuestaHTML += `<h3 class="text-center">${pregunta.pregunta}</h3>`;
            } else if (pregunta.tipo === 'introduccion') {
                encuestaHTML += `<p class="text-center">${pregunta.pregunta}</p>`;
            } else {
                // Mostrar el texto de la pregunta
                encuestaHTML += `
                    <p class="fw-bold q-title">${pregunta.pregunta}${pregunta.requerida ? '<span class="text-danger">*</span>' : ''}</p>
                `;

                // Tipos de campo
                if (pregunta.tipo === 'file') {
                    encuestaHTML += `<input type="file" class="form-control" name="pregunta${i}" id="pregunta${i}">`;
                } else if (pregunta.tipo === 'radio' || pregunta.tipo === 'checkbox') {
                    pregunta.opciones.forEach((opcion, opcionIndex) => {
                        encuestaHTML += `
                            <div class="form-check">
                                <input type="${pregunta.tipo}" class="form-check-input" 
                                       name="pregunta${i}${pregunta.tipo === 'checkbox' ? '[]' : ''}" 
                                       id="opcion${i}_${opcionIndex}" 
                                       value="${opcion}">
                                <label class="form-check-label" for="opcion${i}_${opcionIndex}">
                                    ${opcion}
                                </label>
                            </div>
                        `;
                    });
                } else {
                    encuestaHTML += `<input type="text" class="form-control" name="pregunta${i}">`;
                }
            }

            encuestaHTML += `</div></div>`;
            i++; // Avanzar normalmente
        }
    }
    
    // Botón de enviar
    encuestaHTML += `
        <button type="button" class="btn btn-primary mt-4 text-center enviar-btn" style="width: 100%;
            border: 1px solid #817e7e;
            background-color: #bbb199;
            color: #212529;">
            Send responses
        </button>
        </form>
    `;
    
    $('#contenedor-preguntas').html(encuestaHTML);

    // Manejador de evento para el botón
    $(document).on('click', '.enviar-btn', function() {
        enviarRespuestas(cuestionario);
    });
}

  function enviarRespuestas(cuestionario) {
    let respuestas = [];
    let formData = new FormData();
    let contadorArchivos = 1;

    cuestionario.preguntas.forEach((preguntaObj, preguntaIndex) => {
        let respuesta = {
            pregunta: preguntaObj.pregunta,
            respuesta: [],
            tipo: preguntaObj.tipo,
            requerida: preguntaObj.requerida
        };

        if (preguntaObj.tipo === 'titulo' || preguntaObj.tipo === 'introduccion') {
            respuesta.respuesta = ''; // Solo guarda la estructura, sin intentar leer input
        } 
        else if (preguntaObj.tipo === 'file') {
            let input = $(`#pregunta${preguntaIndex}`)[0];
            if (input && input.files.length > 0) {
                formData.append('archivo_' + contadorArchivos, input.files[0]);
                respuesta.respuesta = 'archivo_' + contadorArchivos;
                contadorArchivos++;
            } else {
                respuesta.respuesta = 'No se seleccionó archivo';
            }
        } 
        else if (preguntaObj.tipo === 'checkbox') {
            respuesta.respuesta = [];
            $(`input[name="pregunta${preguntaIndex}[]"]:checked`).each(function() {
                respuesta.respuesta.push($(this).val());
            });
            if (respuesta.respuesta.length === 0) {
                respuesta.respuesta = 'No seleccionado';
            }
        } 
        else if (preguntaObj.tipo === 'radio') {
            let selected = $(`input[name="pregunta${preguntaIndex}"]:checked`).val();
            respuesta.respuesta = selected || 'No seleccionado';
        } 
        else {
            respuesta.respuesta = $(`input[name="pregunta${preguntaIndex}"]`).val()?.trim() || '';
        }

        respuestas.push(respuesta);
    });



    let seguir = validarRespuestas(respuestas, cuestionario.preguntas);

    if(seguir) {
        let idclie = <?php echo $id; ?>;
        let idcuestionario = <?php echo $lang; ?>;
        let idres = <?php echo $idres; ?>;

        formData.append('idclie', idclie);
        formData.append('idcuestionario', idcuestionario);
        formData.append('respuestas', JSON.stringify(respuestas));
        formData.append('idres', idres);

        const loadingSwal = Swal.fire({
            title: 'Enviando...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.ajax({
            url: 'guardar_respuestas_cuestionario.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                loadingSwal.close();
                let response = JSON.parse(res);
                if (response.status == 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Respuestas enviadas correctamente',
                        confirmButtonText: 'Aceptar'
                    }).then(() => window.location.reload());
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Error al guardar respuestas'
                    });
                }
            },
            error: function(xhr) {
                loadingSwal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error en la conexión: ' + xhr.statusText
                });
            }
        });
    } else {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Debes responder todas las preguntas requeridas'
        });
    }
}

    function validarRespuestas(respuestas, preguntas) {
        for (let i = 0; i < preguntas.length; i++) {
            let pregunta = preguntas[i];
            if (pregunta.requerida) {
                let respuesta = respuestas.find(r => r.pregunta === pregunta.pregunta);
                if (!respuesta || 
                    (Array.isArray(respuesta.respuesta) && respuesta.respuesta.length === 0) ||
                    respuesta.respuesta === '' || 
                    respuesta.respuesta === "No se seleccionó archivo") {
                    return false;
                }
            }
        }
        return true;
    }
});


    </script>
</body>
</html>
