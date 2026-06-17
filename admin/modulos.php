  <?php 
  	
  	include 'menu.php';
  	
   $Curso = $_GET['idCurso']
  	
   ?>

  <!DOCTYPE html>

  <html lang="en">

    <head>
        <meta charset="UTF-8">

        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Form Layout - Mazer Admin Dashboard</title>
        
        <link rel="shortcut icon" href="./assets/compiled/svg/favicon.svg" type="image/x-icon">
        <link rel="shortcut icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACEAAAAiCAYAAADRcLDBAAAEs2lUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4KPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iWE1QIENvcmUgNS41LjAiPgogPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4KICA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIgogICAgeG1sbnM6ZXhpZj0iaHR0cDovL25zLmFkb2JlLmNvbS9leGlmLzEuMC8iCiAgICB4bWxuczp0aWZmPSJodHRwOi8vbnMuYWRvYmUuY29tL3RpZmYvMS4wLyIKICAgIHhtbG5zOnBob3Rvc2hvcD0iaHR0cDovL25zLmFkb2JlLmNvbS9waG90b3Nob3AvMS4wLyIKICAgIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIKICAgIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIgogICAgeG1sbnM6c3RFdnQ9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZUV2ZW50IyIKICAgZXhpZjpQaXhlbFhEaW1lbnNpb249IjMzIgogICBleGlmOlBpeGVsWURpbWVuc2lvbj0iMzQiCiAgIGV4aWY6Q29sb3JTcGFjZT0iMSIKICAgdGlmZjpJbWFnZVdpZHRoPSIzMyIKICAgdGlmZjpJbWFnZUxlbmd0aD0iMzQiCiAgIHRpZmY6UmVzb2x1dGlvblVuaXQ9IjIiCiAgIHRpZmY6WFJlc29sdXRpb249Ijk2LjAiCiAgIHRpZmY6WVJlc29sdXRpb249Ijk2LjAiCiAgIHBob3Rvc2hvcDpDb2xvck1vZGU9IjMiCiAgIHBob3Rvc2hvcDpJQ0NQcm9maWxlPSJzUkdCIElFQzYxOTY2LTIuMSIKICAgeG1wOk1vZGlmeURhdGU9IjIwMjItMDMtMzFUMTA6NTA6MjMrMDI6MDAiCiAgIHhtcDpNZXRhZGF0YURhdGU9IjIwMjItMDMtMzFUMTA6NTA6MjMrMDI6MDAiPgogICA8eG1wTU06SGlzdG9yeT4KICAgIDxyZGY6U2VxPgogICAgIDxyZGY6bGkKICAgICAgc3RFdnQ6YWN0aW9uPSJwcm9kdWNlZCIKICAgICAgc3RFdnQ6c29mdHdhcmVBZ2VudD0iQWZmaW5pdHkgRGVzaWduZXIgMS4xMC4xIgogICAgICBzdEV2dDp3aGVuPSIyMDIyLTAzLTMxVDEwOjUwOjIzKzAyOjAwIi8+CiAgICA8L3JkZjpTZXE+CiAgIDwveG1wTU06SGlzdG9yeT4KICA8L3JkZjpEZXNjcmlwdGlvbj4KIDwvcmRmOlJERj4KPC94OnhtcG1ldGE+Cjw/eHBhY2tldCBlbmQ9InIiPz5V57uAAAABgmlDQ1BzUkdCIElFQzYxOTY2LTIuMQAAKJF1kc8rRFEUxz9maORHo1hYKC9hISNGTWwsRn4VFmOUX5uZZ36oeTOv954kW2WrKLHxa8FfwFZZK0WkZClrYoOe87ypmWTO7dzzud97z+nec8ETzaiaWd4NWtYyIiNhZWZ2TvE946WZSjqoj6mmPjE1HKWkfdxR5sSbgFOr9Ll/rXoxYapQVik8oOqGJTwqPL5i6Q5vCzeo6dii8KlwpyEXFL519LjLLw6nXP5y2IhGBsFTJ6ykijhexGra0ITl5bRqmWU1fx/nJTWJ7PSUxBbxJkwijBBGYYwhBgnRQ7/MIQIE6ZIVJfK7f/MnyUmuKrPOKgZLpEhj0SnqslRPSEyKnpCRYdXp/9++msneoFu9JgwVT7b91ga+LfjetO3PQ9v+PgLvI1xkC/m5A+h7F32zoLXug38dzi4LWnwHzjeg8UGPGbFfySvuSSbh9QRqZ6H+Gqrm3Z7l9zm+h+iafNUV7O5Bu5z3L/wAdthn7QIme0YAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAJTSURBVFiF7Zi9axRBGIefEw2IdxFBRQsLWUTBaywSK4ubdSGVIY1Y6HZql8ZKCGIqwX/AYLmCgVQKfiDn7jZeEQMWfsSAHAiKqPiB5mIgELWYOW5vzc3O7niHhT/YZvY37/swM/vOzJbIqVq9uQ04CYwCI8AhYAlYAB4Dc7HnrOSJWcoJcBS4ARzQ2F4BZ2LPmTeNuykHwEWgkQGAet9QfiMZjUSt3hwD7psGTWgs9pwH1hC1enMYeA7sKwDxBqjGnvNdZzKZjqmCAKh+U1kmEwi3IEBbIsugnY5avTkEtIAtFhBrQCX2nLVehqyRqFoCAAwBh3WGLAhbgCRIYYinwLolwLqKUwwi9pxV4KUlxKKKUwxC6ZElRCPLYAJxGfhSEOCz6m8HEXvOB2CyIMSk6m8HoXQTmMkJcA2YNTHm3congOvATo3tE3A29pxbpnFzQSiQPcB55IFmFNgFfEQeahaAGZMpsIJIAZWAHcDX2HN+2cT6r39GxmvC9aPNwH5gO1BOPFuBVWAZue0vA9+A12EgjPadnhCuH1WAE8ivYAQ4ohKaagV4gvxi5oG7YSA2vApsCOH60WngKrA3R9IsvQUuhIGY00K4flQG7gHH/mLytB4C42EgfrQb0mV7us8AAMeBS8mGNMR4nwHamtBB7B4QRNdaS0M8GxDEog7iyoAguvJ0QYSBuAOcAt71Kfl7wA8DcTvZ2KtOlJEr+ByyQtqqhTyHTIeB+ONeqi3brh+VgIN0fohUgWGggizZFTplu12yW8iy/YLOGWMpDMTPXnl+Az9vj2HERYqPAAAAAElFTkSuQmCC" type="image/png">
        
      <link rel="stylesheet" href="assets/extensions/quill/quill.snow.css">
  <link rel="stylesheet" href="assets/extensions/quill/quill.bubble.css">

    <link rel="stylesheet" href="./assets/compiled/css/app.css">
    <link rel="stylesheet" href="./assets/compiled/css/app-dark.css">

      <!-- SweetAlert 2 -->
      <script src="vistas/plugins/sweetalert2/sweetalert2.all.js"></script>

    </head>

  <body>

        <section class="section">
        <form id="form">
            <input id="idCurso" value="<?php echo $Curso;?>" hidden>
          <div class="card">

              <div class="card-header">

                  <h4 class="card-title">Contenido</h4>

              </div>

              <div class="card-body">
    <span>Título</span>
    
    <input class="form-control" id="titulo" placeholder="Titulo">
    <br>
        <textarea class="form-control" id="editor" ></textarea>

</div>

          </div>
          
         <div class="preguntas">
      <div class="mt-3" id="step11">
        <h1 class="txtform2">preguntas:</h1>
      </div>

      <div class=" mt-2 mb-2 preguntas2 row w-100" data-num="1">

        <div class="preguntosas col-6 d-flex justify-content-center align-items-center justify-items-center">
          <div class="hola col-12">
            <label class="txtform">Pregunta</label>
            <input type="text" class="form-control">
          </div>
        </div>
        <div class="respuestosas col-6 d-flex justify-content-center align-items-center justify-items-center">
          <div class="hola col-12 d-flex justify-content-center align-items-center flex-column">
            <div class="row w-100"><label class="txtform col-6">Respuesta</label>
              <!--<div class="col-6 d-flex"> <label for="check">Texarea</label> <input type="checkbox" name="textarea1" class="form-check-input"> </div>-->
            </div>
            <div class="form-group opciones">
              <label for="respuesta1">Opción 1:</label>
              <input type="text" name="respuesta1" class="form-control  mb-2 inputRes" id="respuesta1">
            </div>

            <button type="button" class="btn btn-primary" onclick="agrRespuestas(event)">Agregar Respuesta</button>
          </div>
        </div>
      </div>
      <div class="Botones d-flex justify-content-between ">
        <!--<button type="submit" class="btn btn-primary btn-lg col-12 mt-3 mb-3" style="background-color:<?php echo $P_COLOR ?>;">Generar enlace</button>-->
      </div>
</div>

<div class="Botones d-flex justify-content-between mt-3">
  <div class="agrPregunta">
    <i class="fa-light fa-plus fa-2xl"></i>
    <button class="btn btn-success" onclick="agrPregunta(event)">Agregar Pregunta</button>
  </div>
  <div class="eliPregunta">
    <i class="fa-light fa-minus fa-2xl"></i>
    <button class="btn btn-danger" onclick="eliPregunta(event)">Eliminar Respuesta</button>
  </div>


</div>
<div class="w-100 d-flex justify-content-center align-items-center ">
    <button class="btn btn-bg btn-primary" type="submit">Enviar</button>
</div>
 </form>
 
 
 
  <table id="temasTable" class="table table-striped table-bordered" style="width:100%">
      <thead>
        <tr>
          <th>ID</th>
          <th>Título</th>
          <th>Contenido</th>
          <th>Curso</th>
          <th>Orden</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
</div>



 

      </section>


<!-- Modal para editar tema -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel">Editar Tema</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="editForm">
            <div class="mb-3">
              <label for="tituloEdit" class="form-label">Título</label>
              <input type="text" class="form-control" id="tituloEdit" name="tituloEdit" required>
            </div>
            <div class="mb-3">
              <label for="textEdit" class="form-label">Contenido</label>
              <textarea class="form-control" id="textEdit" name="textEdit" rows="3" required></textarea>
            </div>
            <div class="mb-3">
              <label for="idCursoEdit" class="form-label">ID del Curso</label>
              <input type="text" class="form-control" id="idCursoEdit" name="idCursoEdit" required>
            </div>
            <div class="mb-3">
              <label for="OrdenAskEdit" class="form-label">Orden</label>
              <input type="text" class="form-control" id="OrdenAskEdit" name="OrdenAskEdit" required>
            </div>
            <input type="hidden" id="idTemaEdit" name="idTemaEdit">
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="saveEditBtn">Guardar cambios</button>
        </div>
      </div>
    </div>
  </div>
  	<?php

  	  include 'footer.php';
  	?> 

    <script src="assets/static/js/components/dark.js"></script>
    <script src="assets/extensions/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="assets/compiled/js/app.js"></script> 
    <script src="assets/extensions/quill/quill.min.js"></script>
    <script src="assets/static/js/pages/quill.js"></script>
<script src="https://cdn.ckeditor.com/4.20.1/standard/ckeditor.js"></script>


<script>
    // Inicializa CKEditor en el textarea con ID "editor"
    CKEDITOR.replace('editor');

    // Función para obtener el contenido del editor
    function obtenerContenidoEditor() {
        return CKEDITOR.instances.editor.getData();
    }
  function agrPregunta(event) {
    event.preventDefault()
    // Clona el div con la clase "preguntas2"
    var nuevaPregunta = $(".preguntas2").first().clone();

    // Cambia el texto de la pregunta a "Pregunta X" donde X es el número de pregunta correspondiente
    var numPregunta = $(".preguntas2").length + 1;
    nuevaPregunta.find("h1").text("Pregunta " + numPregunta);

    // Actualiza el atributo data-num
    nuevaPregunta.attr('data-num', numPregunta);

    // Agrega la nueva pregunta clonada al div con la clase "preguntas"
    $(".preguntas").append(nuevaPregunta);

    // Mueve el botón de agregar debajo del último div de pregunta
    $(".preguntas").append($(".Botones"));
  }

  function eliPregunta(event) {
    event.preventDefault()
    // Elimina el último div con la clase "preguntas2"
    $(".preguntas2").last().remove();

    // Actualiza el atributo data-num de todas las preguntas restantes
    $(".preguntas2").each(function(index) {
      $(this).attr('data-num', index + 1);
      $(this).find("h1").text("Pregunta " + (index + 1));
    });
  }

  function agrRespuestas(event) {
    // Encuentra el div contenedor del botón que se hizo clic
    var divContenedor = $(event.target).closest('.respuestosas');

    // Clona el primer input dentro del div contenedor y lo agrega al final
    var nuevoInput = divContenedor.find('.inputRes').first().clone();
    divContenedor.find('.opciones').append(nuevoInput);
  }



  $(document).ready(function() {
  $("form").submit(function(e) {
    e.preventDefault();

    // Mensaje de carga mejorado con SweetAlert
    // Swal.fire({
    //   title: 'Procesando...',
    //   text: 'Por favor espera mientras enviamos tu información.',
    //   allowOutsideClick: false,
    //   allowEscapeKey: false,
    //   didOpen: () => {
    //     Swal.showLoading();
    //   }
    // });

    var datos = {};
    
    // Recorre cada div con la clase "preguntas2"
    $(".preguntas2").each(function() {
      var numPregunta = $(this).attr('data-num');
      var textoPregunta = $(this).find(".preguntosas input").val();
      var respuestas = [];

      $(this).find(".respuestosas .opciones").each(function() {
        $(this).find("input[type='text']").each(function() {
          respuestas.push($(this).val());
        });
      });

      var checkboxEstado = $(this).find(".respuestosas input[type='checkbox']").is(':checked') ? 1 : 0;
      datos["Pregunta " + numPregunta] = {
        "pregunta": textoPregunta,
        "respuestas": respuestas,
        "checkbox": checkboxEstado
      };
    });

    var formData = new FormData(this);
    var titulo = $('#titulo').val();
    var idCurso = $('#idCurso').val();
    var Text = obtenerContenidoEditor();
    formData.append("text", Text);
    formData.append("titulo", titulo);
    formData.append("idCurso", idCurso);
    formData.append("OrdenAsk", JSON.stringify(datos));
          // Mostrar el contenido de FormData en la consola
for (var pair of formData.entries()) {
    console.log(pair[0] + ': ' + pair[1]);
}
    $.ajax({
      type: "POST",
      url: "Agregar_Temas.php",
      data: formData,
      processData: false,
      contentType: false,
      success: function(response) {
        Swal.close();  // Cierra el SweetAlert de carga
        Swal.fire({
          icon: 'success',
          title: '¡Perfecto!',
          text: 'Los datos se han enviado exitosamente.',
          showConfirmButton: true,
          confirmButtonText: 'Aceptar',
          allowOutsideClick: false
        }).then((result) => {
          if (result.isConfirmed || result.isDismissed) {
            location.reload();
          }
        });
        console.log(response);
      },
      error: function(xhr, status, error) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Ocurrió un error al enviar los datos. Inténtalo nuevamente.',
          showConfirmButton: true,
          confirmButtonText: 'Aceptar'
        });
        console.error(xhr.responseText);
      }
    });
  });
  
$.ajax({
  type: "POST",
  url: "Agregar_Temas.php",
  data: { consulta: 2 },
  success: function(response) {
    try {
      // Parsear la respuesta JSON
      let rawData = JSON.parse(response);

      // Filtrar las propiedades numéricas
      const cleanData = {};
      Object.keys(rawData).forEach(key => {
        if (isNaN(key)) { // Solo mantiene las claves no numéricas
          cleanData[key] = rawData[key];
        }
      });

      // Convertir en un array de un solo objeto si DataTables requiere un array
      const dataArray = [cleanData];

      console.log("Datos procesados para DataTable:", dataArray);

    //   // Inicializar o actualizar DataTable
    //   $('#temasTable').DataTable({
    //     data: dataArray,
    //     columns: [
    //       { data: 'id' },
    //       { data: 'titulo' },
    //       { data: 'contenido' },
    //       { data: 'id_curso' },
    //       { data: 'preguntas' },
    //       {
    //         data: null,
    //         render: function(data, type, row) {
    //           return `
    //             <button class="btn btn-primary btn-sm edit-btn" data-id="${row.id}">Editar</button>
    //             <button class="btn btn-danger btn-sm delete-btn" data-id="${row.id}">Eliminar</button>
    //           `;
    //         }
    //       }
    //     ],
    //     destroy: true,
    //     language: {
    //       lengthMenu: "Mostrar _MENU_ registros por página",
    //       zeroRecords: "No se encontraron registros",
    //       info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
    //       infoEmpty: "No hay registros disponibles",
    //       infoFiltered: "(filtrado de _MAX_ registros en total)",
    //       search: "Buscar:",
    //       paginate: {
    //         previous: "Anterior",
    //         next: "Siguiente"
    //       }
    //     }
    //   });
    } catch (error) {
      console.error("Error al parsear JSON:", error, "Respuesta recibida:", response);
    }
  },
  error: function(xhr, status, error) {
    console.error("Error en la solicitud:", xhr.responseText);
  }
});



// Manejar clic en el botón de editar
$('#temasTable').on('click', '.edit-btn', function() {
  var id = $(this).data('id');
  console.log('ID de tema a editar:', id);

  $.ajax({
    url: 'Agregar_Temas.php',
    type: 'POST',
    data: { editar: id },
    dataType: 'json',
    success: function(response) {
      console.log('Respuesta del servidor al editar:', response);

      // Llenar el formulario de edición con los datos del tema
      $('#tituloEdit').val(response.titulo);
      $('#textEdit').val(response.contenido);
      $('#idCursoEdit').val(response.id_curso);
      $('#OrdenAskEdit').val(response.preguntas);
      $('#idTemaEdit').val(response.id);
      $('#editModal').modal('show');
    }
  });
});

// Manejar clic en el botón de eliminar
$('#temasTable').on('click', '.delete-btn', function() {
  var id = $(this).data('id');
  console.log('ID de tema a eliminar:', id);

  if (confirm('¿Estás seguro de que quieres eliminar este tema?')) {
    $.ajax({
      url: 'Agregar_Temas.php',
      type: 'POST',
      data: { eliminar: id },
      dataType: 'json',
      success: function(response) {
        console.log('Respuesta del servidor al eliminar:', response);

        if (response.status === 'success') {
          alert(response.message);
          table.ajax.reload();
        } else {
          alert(response.message);
        }
      }
    });
  }
});

// Manejar el envío del formulario de edición
$('#saveEditBtn').click(function() {
  var formData = {
    titulo: $('#tituloEdit').val(),
    text: $('#textEdit').val(),
    idCurso: $('#idCursoEdit').val(),
    OrdenAsk: $('#OrdenAskEdit').val(),
    editar: JSON.stringify({
      id: $('#idTemaEdit').val()
    })
  };
  
  console.log('Datos enviados al servidor al guardar edición:', formData);

  $.ajax({
    url: 'Agregar_Temas.php',
    type: 'POST',
    data: formData,
    dataType: 'json',
    success: function(response) {
      console.log('Respuesta del servidor al guardar edición:', response);

      if (response.status === 'success') {
        alert(response.message);
        $('#editModal').modal('hide');
        $('#temasTable').DataTable().ajax.reload();
      } else {
        alert(response.message);
      }
    }
  });
});

});

</script>
  </body>
  </html>