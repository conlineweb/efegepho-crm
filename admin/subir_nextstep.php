<?php

include "menu.php";
include "conn.php";

$tipoUsuario = $_SESSION["tus"];
$idUsu = $_SESSION["uid"];

$sql = "SELECT * FROM nextstep";
$result = $conn->query($sql);

$respuestas = [];

// Array para almacenar los resultados
?>           
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento NextStep</title>
</head>

<!-- HTML para la tabla -->
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                  <h2>Sube el documento NEXTSTEP</h2>
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
                   </div>
                     <div class="subirDoc">
                    <p>Elige un archivo para continuar</p>
                      <form id="formSubida" enctype="multipart/form-data">
                        <div class="mb-3">
                          <input type="file" name="archivo" id="archivo" class="form-control" required>
                        </div>
                        <p id="archivobd"></p>
                        <button type="submit" class="btn btn-primary">Subir archivo</button>
                        <button type="button" class="btn btn-primary" id="verDocumento">Ver documento</button> 
                      </form>
                      <div id="respuesta" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include "footer.php"; ?>

<!-- Incluir librerías de jQuery y DataTables -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">

<script>

$(document).ready(function() {
  // Mostrar el nombre del archivo al cargar la página
   $.ajax({
    url: 'get_nextstep.php',
    type: 'GET',
    dataType: 'json',
    success: function(response) {
      console.log(response); // Muestra los datos en la consola

      if (response.success && response.nombre) {
    $('#archivobd').html("<strong>Archivo actual:</strong> " + response.nombre);

} else {
    $('#archivobd').text("No hay archivo subido.");
}

    },
    error: function() {
      $('#archivobd').text("Error al cargar el nombre del archivo.");
    }
  });


  // Subir archivo
  $('#formSubida').on('submit', function(e) {
    e.preventDefault();

    var formData = new FormData(this);
    
    $.ajax({
      url: 'upload_nextstep.php',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      dataType: 'json',
      beforeSend: function() {
        Swal.fire({
          title: 'Subiendo archivo...',
          text: 'Por favor espera',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });
      },
      success: function(response) {
        Swal.close();
        if (response.success) {
          Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: response.message
          });
          // Actualiza el nombre del archivo en el <p>
          $('#archivobd').text("Archivo actual: " + response.nombre);
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: response.message
          });
        }
      },
      error: function() {
        Swal.close();
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Error al comunicarse con el servidor.'
        });
      }
    });
  });

  // Ver documento
  $('#verDocumento').on('click', function() {
    Swal.fire({
      title: 'Abriendo documento...',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
        $.ajax({
          url: 'get_nextstep.php',
          type: 'GET',
          dataType: 'json',
          success: function(response) {
            Swal.close();
            if (response.success && response.ruta) {
              $('#archivobd').text("Archivo actual: " + response.nombre); // Actualiza el nombre en el <p>
              window.open('../' + response.ruta, '_blank');
            } else {
              Swal.fire('Error', response.message || 'Documento no encontrado', 'error');
            }
          },
          error: function() {
            Swal.fire('Error', 'Error al comunicarse con el servidor', 'error');
          }
        });
      }
    });
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
