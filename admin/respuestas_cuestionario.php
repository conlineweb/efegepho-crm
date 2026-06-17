<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexi贸n a la base de datos

$tipoUsuario = $_SESSION['tus'];
$idUsu = $_SESSION['uid'];



$sql = "SELECT * FROM respuestas_cuestionario WHERE estatus = 2";
$result = $conn->query($sql);

$respuestas = array(); // Array para almacenar los resultados

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Obtener el id del cliente (id_clie) del registro actual
        $idclie = $row['id_clie'];

        // Obtener el id del cuestionario
        $idCuestionario = $row['id_cuestionario'];

        // Obtener información del cliente
        $sql_cliente = "SELECT * FROM contact_form WHERE id = $idclie";
        $result_cliente = $conn->query($sql_cliente);

        if ($result_cliente->num_rows > 0) {
            $cliente = $result_cliente->fetch_assoc();
            $row['cliente'] = $cliente;
        }

        // Obtener el título del cuestionario
        $sql_cuestionario = "SELECT titulo,introduccion FROM cuestionario WHERE id = $idCuestionario";
        $result_cuestionario = $conn->query($sql_cuestionario);

        if ($result_cuestionario->num_rows > 0) {
            $cuestionario = $result_cuestionario->fetch_assoc();
            $row['titulo_cuestionario'] = $cuestionario['titulo'];
        $row['introduccion_cuestionario'] = $cuestionario['introduccion'];

        } else {
            $row['titulo_cuestionario'] = null;
        }

        // Agregar la fila al array de respuestas
        $respuestas[] = $row;
    }
}


// Ahora $respuestas contiene los datos de la tabla 'respuestas_cuestionario' con la información adicional del cliente.



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

.notification-bubble {
        position: absolute;
        top: -3px;
        right: -1px;
        width: 20px;
        height: 20px;
        background-color: #bba985;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
        display:none;
        z-index:1050;
    }
    .nav-item {
        position: relative;
    }
#verMasModal p{
    font-size:1.15rem;
}
table{
    
    width:100% !important;
    
}
.btn-cancel {
    background-color: #d3d3d3; /* Gris claro */
    border-color: #a9a9a9;     /* Gris oscuro para el borde */
    color: #707070;            /* Color del texto */
}

.btn-cancel:hover {
    background-color: #b0b0b0; /* Gris oscuro en hover */
    border-color: #808080;     /* Gris más oscuro en hover */
    color: #404040;            /* Color del texto más oscuro */
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
      <section class="section">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Citas pendientes</h4>
            </div>
            <div class="card-body">
        <table id="respuestasTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    
                    <th>No. Cliente</th>
                     <th>Cuestionario</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Acciones</th>

                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
   </div>
        </div>
    </section>
<!-- Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="z-index:53!important">
      <div class="modal-header">
        <h5 class="modal-title" id="detailsModalLabel">Detalles de las Respuestas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="modal-questions-container"></div> <!-- Aquí se mostrarán las preguntas y respuestas -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
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
<!-- Incluir jQuery desde el CDN -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Incluir DataTables CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

<!-- Incluir DataTables JS -->
<script type="text/javascript" charset="utf-8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>



<!-- Bootstrap JS y Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script>
   const respuestas  = <?php echo json_encode($respuestas); ?>;
console.log("respuestas ", respuestas);

$(document).ready(function () {
    $('#respuestasTable').DataTable({
        data: respuestas, // Asignamos los datos obtenidos
        columns: [
            { data: 'id_clie' },
               { data: 'titulo_cuestionario' },
            { 
                data: null, // No usamos un campo directo, vamos a concatenar
                render: function(data, type, row) {
                    return data.cliente.names;
                },
                title: 'Nombres' // Título para la columna combinada
            },
            { 
                data: null, // No usamos un campo directo, vamos a concatenar
                render: function(data, type, row) {
                    return data.cliente.telephone;
                },
                title: 'Telefono' // Título para la columna combinada
            },
            { 
                data: null, // No usamos un campo directo, vamos a concatenar
                render: function(data, type, row) {
                    return data.cliente.email_address;
                },
                title: 'Email' // Título para la columna combinada
            },
            { 
                data: null, // No usamos un campo directo, vamos a concatenar
                render: function(data, type, row) {
                    moment.locale('es');
                    return moment(data.fecha, 'YYYY-MM-DD').format('dddd D [de] MMMM [del] YY');
                },
                title: 'Fecha' // Título para la columna combinada
            },
            { 
                data: null, // No usamos un campo directo, vamos a concatenar
                render: function(data, type, row) {
                    return moment(data.fecha + ' ' + data.hora).format('HH:mm a');
                },
                title: 'Hora' // Título para la columna combinada
            },
            { 
                data: null, // No usamos un campo directo, vamos a agregar los botones
                render: function(data, type, row) {
                    return '<button class="btn btn-primary btn-sm btn-vermas mx-2 my-1" ' +
                        'data-id="' + data.id + '" ' +
                        'data-idclie="' + data.id_clie + '" ' +
                        '>Ver más info</button>';
                },
                title: 'Acciones' // Título para la columna de acciones
            }
        ],
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-MX.json' // Traducción al español
        },
        order: [], // No ordenar ninguna columna por defecto
        columnDefs: [
            {
                targets: [0], // Desactivar la ordenación de la columna `id`
                orderable: false // No se puede ordenar por la columna `id`
            }
        ]
    });
    
    
$('#respuestasTable').on('click', '.btn-vermas', function() {
    var id = $(this).data('id');
    var respuesta = respuestas.find(r => r.id == id);
    let tituloCuestionario = respuesta.titulo_cuestionario;
    let introduccionCuestionario = respuesta.introduccion_cuestionario;

    if (respuesta) {
        $('#modal-questions-container').empty();

        // Agregar el título e introducción del cuestionario
        let headerHtml = `
            <div class="modal-cuestionario-header">
                <h3 class="cuestionario-title">${tituloCuestionario}</h3>
                <p class="cuestionario-intro">${introduccionCuestionario}</p>
                <hr>
            </div>
        `;
        $('#modal-questions-container').append(headerHtml);

        var preguntasRespuestas = JSON.parse(respuesta.respuestas);
        console.log(preguntasRespuestas);
        
        preguntasRespuestas.forEach(function(item, index) {
            var pregunta = item.pregunta;
            var respuestaTexto = item.respuesta.res;
            var tipoPregunta = item.tipo;
            var tipoRespuesta = item.respuesta.tipo;

            let questionHtml = '';

            if (tipoPregunta === 'titulo') {
                questionHtml = `
                    <div class="modal-section-title">
                        <h4 class="section-title">${pregunta}</h4>
                        <div class="title-divider"></div>
                    </div>
                `;
            } else if (tipoPregunta === 'introduccion') {
                questionHtml = `
                    <div class="modal-introduction">
                        <p class="intro-text">${pregunta}</p>
                    </div>
                `;
            } else {
                questionHtml = `
                    <div class="modal-question-item ${index % 2 === 0 ? 'even' : 'odd'}">
                        <div class="question-header">
                            <h6 class="question-text">${pregunta}</h6>
                        </div>
                        <div class="answer-content">
                `;

                if (tipoRespuesta === "text") {
                    questionHtml += `<p class="answer-text">${respuestaTexto}</p>`;
                } else if (["jpg", "png", "jpeg"].includes(tipoRespuesta)) {
                    questionHtml += `
                        <div class="file-actions">
                            <button class="btn btn-outline-primary btn-sm file-btn" onclick="verImagen('/${respuestaTexto}')">
                                <i class="fas fa-eye me-1"></i>Ver imagen
                            </button>
                            <a href="/${respuestaTexto}" download class="btn btn-outline-secondary btn-sm file-btn">
                                <i class="fas fa-download me-1"></i>Descargar
                            </a>
                        </div>
                    `;
                } else if (tipoRespuesta === "pdf") {
                    questionHtml += `
                        <div class="file-actions">
                            <button class="btn btn-outline-primary btn-sm file-btn" onclick="verPdf('/${respuestaTexto}')">
                                <i class="fas fa-file-pdf me-1"></i>Ver PDF
                            </button>
                            <a href="/${respuestaTexto}" download class="btn btn-outline-secondary btn-sm file-btn">
                                <i class="fas fa-download me-1"></i>Descargar
                            </a>
                        </div>
                    `;
                } else if (Array.isArray(respuestaTexto)) {
                    questionHtml += `<div class="answer-list">`;
                    respuestaTexto.forEach(function(itemInArray) {
                        questionHtml += `<div class="list-item">${itemInArray}</div>`;
                    });
                    questionHtml += `</div>`;
                } else {
                    questionHtml += `
                        <div class="file-actions">
                            <span class="text-muted">Archivo adjunto</span>
                            <a href="/${respuestaTexto}" download class="btn btn-outline-secondary btn-sm file-btn">
                                <i class="fas fa-download me-1"></i>Descargar archivo
                            </a>
                        </div>
                    `;
                }

                questionHtml += `
                        </div>
                    </div>
                `;
            }

            $('#modal-questions-container').append(questionHtml);
        });

        // Mostrar el modal
        $('#detailsModal').modal('show');
    }
});




});



// Función para mostrar la imagen en un modal
function verImagen(imagenUrl) {
    // Eliminar modal existente antes de crear uno nuevo
    $('#modal-imagen').remove();

    var modalHtml = `
        <div class="modal fade" id="modal-imagen" tabindex="-1" aria-labelledby="modal-imagenLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-imagenLabel">Vista previa de la imagen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <img src="${imagenUrl}" alt="Vista previa" class="img-fluid" style="max-width: 100%;">
                    </div>
                    <div class="modal-footer">
                        <a href="${imagenUrl}" download class="btn btn-secondary">Descargar imagen</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    $('body').append(modalHtml);
    $('#modal-imagen').modal('show');
}

// Función para mostrar el PDF en un modal
function verPdf(pdfUrl) {
    // Eliminar modal existente antes de crear uno nuevo
    $('#modal-pdf').remove();

    var modalHtml = `
        <div class="modal fade" id="modal-pdf" tabindex="-1" aria-labelledby="modal-pdfLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal-pdfLabel">Vista previa del PDF</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <iframe src="${pdfUrl}" width="100%" height="500px"></iframe>
                    </div>
                    <div class="modal-footer">
                        <a href="${pdfUrl}" download class="btn btn-secondary">Descargar PDF</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    $('body').append(modalHtml);
    $('#modal-pdf').modal('show');
}





    </script>
</body>
</html>
