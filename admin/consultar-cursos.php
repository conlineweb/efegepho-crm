<?php

include_once "controladores/cursos.controlador.php";

include_once "modelos/cursos.modelo.php";

include 'menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
      <meta charset="UTF-8">

      <meta name="viewport" content="width=device-width, initial-scale=1.0">

      <title>Form Layout - Mazer Admin Dashboard</title>
      
      <link rel="shortcut icon" href="./assets/compiled/svg/favicon.svg" type="image/x-icon">
      <link rel="shortcut icon" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACEAAAAiCAYAAADRcLDBAAAEs2lUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4KPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iWE1QIENvcmUgNS41LjAiPgogPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4KICA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIgogICAgeG1sbnM6ZXhpZj0iaHR0cDovL25zLmFkb2JlLmNvbS9leGlmLzEuMC8iCiAgICB4bWxuczp0aWZmPSJodHRwOi8vbnMuYWRvYmUuY29tL3RpZmYvMS4wLyIKICAgIHhtbG5zOnBob3Rvc2hvcD0iaHR0cDovL25zLmFkb2JlLmNvbS9waG90b3Nob3AvMS4wLyIKICAgIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIKICAgIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIgogICAgeG1sbnM6c3RFdnQ9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZUV2ZW50IyIKICAgZXhpZjpQaXhlbFhEaW1lbnNpb249IjMzIgogICBleGlmOlBpeGVsWURpbWVuc2lvbj0iMzQiCiAgIGV4aWY6Q29sb3JTcGFjZT0iMSIKICAgdGlmZjpJbWFnZVdpZHRoPSIzMyIKICAgdGlmZjpJbWFnZUxlbmd0aD0iMzQiCiAgIHRpZmY6UmVzb2x1dGlvblVuaXQ9IjIiCiAgIHRpZmY6WFJlc29sdXRpb249Ijk2LjAiCiAgIHRpZmY6WVJlc29sdXRpb249Ijk2LjAiCiAgIHBob3Rvc2hvcDpDb2xvck1vZGU9IjMiCiAgIHBob3Rvc2hvcDpJQ0NQcm9maWxlPSJzUkdCIElFQzYxOTY2LTIuMSIKICAgeG1wOk1vZGlmeURhdGU9IjIwMjItMDMtMzFUMTA6NTA6MjMrMDI6MDAiCiAgIHhtcDpNZXRhZGF0YURhdGU9IjIwMjItMDMtMzFUMTA6NTA6MjMrMDI6MDAiPgogICA8eG1wTU06SGlzdG9yeT4KICAgIDxyZGY6U2VxPgogICAgIDxyZGY6bGkKICAgICAgc3RFdnQ6YWN0aW9uPSJwcm9kdWNlZCIKICAgICAgc3RFdnQ6c29mdHdhcmVBZ2VudD0iQWZmaW5pdHkgRGVzaWduZXIgMS4xMC4xIgogICAgICBzdEV2dDp3aGVuPSIyMDIyLTAzLTMxVDEwOjUwOjIzKzAyOjAwIi8+CiAgICA8L3JkZjpTZXE+CiAgIDwveG1wTU06SGlzdG9yeT4KICA8L3JkZjpEZXNjcmlwdGlvbj4KIDwvcmRmOlJERj4KPC94OnhtcG1ldGE+Cjw/eHBhY2tldCBlbmQ9InIiPz5V57uAAAABgmlDQ1BzUkdCIElFQzYxOTY2LTIuMQAAKJF1kc8rRFEUxz9maORHo1hYKC9hISNGTWwsRn4VFmOUX5uZZ36oeTOv954kW2WrKLHxa8FfwFZZK0WkZClrYoOe87ypmWTO7dzzud97z+nec8ETzaiaWd4NWtYyIiNhZWZ2TvE946WZSjqoj6mmPjE1HKWkfdxR5sSbgFOr9Ll/rXoxYapQVik8oOqGJTwqPL5i6Q5vCzeo6dii8KlwpyEXFL519LjLLw6nXP5y2IhGBsFTJ6ykijhexGra0ITl5bRqmWU1fx/nJTWJ7PSUxBbxJkwijBBGYYwhBgnRQ7/MIQIE6ZIVJfK7f/MnyUmuKrPOKgZLpEhj0SnqslRPSEyKnpCRYdXp/9++msneoFu9JgwVT7b91ga+LfjetO3PQ9v+PgLvI1xkC/m5A+h7F32zoLXug38dzi4LWnwHzjeg8UGPGbFfySvuSSbh9QRqZ6H+Gqrm3Z7l9zm+h+iafNUV7O5Bu5z3L/wAdthn7QIme0YAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAJTSURBVFiF7Zi9axRBGIefEw2IdxFBRQsLWUTBaywSK4ubdSGVIY1Y6HZql8ZKCGIqwX/AYLmCgVQKfiDn7jZeEQMWfsSAHAiKqPiB5mIgELWYOW5vzc3O7niHhT/YZvY37/swM/vOzJbIqVq9uQ04CYwCI8AhYAlYAB4Dc7HnrOSJWcoJcBS4ARzQ2F4BZ2LPmTeNuykHwEWgkQGAet9QfiMZjUSt3hwD7psGTWgs9pwH1hC1enMYeA7sKwDxBqjGnvNdZzKZjqmCAKh+U1kmEwi3IEBbIsugnY5avTkEtIAtFhBrQCX2nLVehqyRqFoCAAwBh3WGLAhbgCRIYYinwLolwLqKUwwi9pxV4KUlxKKKUwxC6ZElRCPLYAJxGfhSEOCz6m8HEXvOB2CyIMSk6m8HoXQTmMkJcA2YNTHm3congOvATo3tE3A29pxbpnFzQSiQPcB55IFmFNgFfEQeahaAGZMpsIJIAZWAHcDX2HN+2cT6r39GxmvC9aPNwH5gO1BOPFuBVWAZue0vA9+A12EgjPadnhCuH1WAE8ivYAQ4ohKaagV4gvxi5oG7YSA2vApsCOH60WngKrA3R9IsvQUuhIGY00K4flQG7gHH/mLytB4C42EgfrQb0mV7us8AAMeBS8mGNMR4nwHamtBB7B4QRNdaS0M8GxDEog7iyoAguvJ0QYSBuAOcAt71Kfl7wA8DcTvZ2KtOlJEr+ByyQtqqhTyHTIeB+ONeqi3brh+VgIN0fohUgWGggizZFTplu12yW8iy/YLOGWMpDMTPXnl+Az9vj2HERYqPAAAAAElFTkSuQmCC" type="image/png">
      
    <link rel="stylesheet" href="./assets/compiled/css/app.css">

    <link rel="stylesheet" href="./assets/compiled/css/app-dark.css">

    <!-- SweetAlert 2 -->
  <script src="vistas/plugins/sweetalert2/sweetalert2.all.js"></script>
  <!-- By default SweetAlert2 doesn't support IE. To enable IE 11 support, include Promise polyfill:-->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/core-js/2.4.1/core.js"></script>

  <!-- iCheck 1.0.1 -->
  <script src="vistas/plugins/iCheck/icheck.min.js"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"></script>

</head>

<body>

  <div class="page-heading">

    <div class="page-title">

          <div class="row">

              <div class="col-12 col-md-6 order-md-1 order-last">

                  <h3>Consultar Cursos</h3>
                
              </div>

              <div class="col-12 col-md-6 order-md-2 order-first">

                  <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">

                      <ol class="breadcrumb">

                          <!-- <li class="breadcrumb-item"><a href="index.html">Dashboard</a></li> -->

                          <li class="breadcrumb-item active" aria-current="page">Consultar Cursos</li>

                      </ol>

                  </nav>

              </div>

          </div>

      </div>

    <section class="section">

      <div class="card">

      <!--         <div class="card-header">

              <h5 class="card-title">Vista de Cursos</h5>

          </div> -->

          <div class="card-body">

              <table class="table table-striped table-bordered tablaCursos tablas" >

                  <thead>

                       <tr>
                          <th style="width:100px">#</th>
                          <th>Curso</th>
                          <th>Categoría</th>
                          <th>Precio</th>
                          <th >Acciones</th>
                        </tr>

                  </thead>

  <!--                 <tfoot>

                       <tr>
                          <th>#</th>
                          <th>Curso</th>
                          <th>Categoría</th>
                          <th>Precio</th>
                          <th>Acciones</th>
                        </tr>

                  </tfoot> -->

                  <tbody>

                  </tbody>

              </table>

          </div>

      </div>

  </section>

</div>

<!--=====================================
MODAL EDITAR CURSO
======================================-->

<div id="modalEditarCurso" class="modal fade" role="dialog">
  
  <div class="modal-dialog modal-dialog modal-xl">

    <div class="modal-content">

      <form action="" method="post" enctype="multipart/form-data">

        <!--=====================================
        CABEZA DEL MODAL
        ======================================-->

        <div class="modal-header" style="background:#3c8dbc; color:white">

        <h5 class="modal-title">Editar Curso</h5>

        <button type="button" class="close" data-dismiss="modal">&times;</button>

      </div>

        <!--=====================================
        CUERPO DEL MODAL
        ======================================-->

        <div class="modal-body">

          <div class="box-body">
            
            <div class="row">

            <!-- ENTRADA PARA NOMBRE -->

              <div class="col-md-4">

                  <div class="mb-3">

                      <label for="nombre-empresa" class="form-label">Nombre</label>

                      <input class="form-control" type="text" id="editarCurso" name="editarCurso" placeholder="Nombre del curso" required>

                      <input type="hidden" name="idCurso" id="idCurso" required>

                  </div>

              </div>

              <!-- ENTRADA PARA DESCRIPCIÓN -->

              <div class="col-md-4">

                  <div class="mb-3">

                      <label for="razon-social" class="form-label">Descripción</label>

                      <textarea class="form-control" id="editarDescripcionCurso" name="editarDescripcionCurso" rows="1" placeholder="Descripción del curso" required></textarea>

                  </div>

              </div>

              <!-- ENTRADA PARA CATEGORÍA -->

              <div class="col-md-4">
                  
                  <div class="mb-3">

                      <label for="razon-social" class="form-label">Categoría</label>
                        
                      <!-- <input type="text" class="form-control input-lg" id="editarCategoria" name="editarCategoria" readonly required> -->

                      <select class="form-control input-lg" id="editarCategoria" name="editarCategoria" required>
                  
                          <option value="">Selecionar categoría</option>

                          <option value="Categoría 1">Categoría 1</option>

                          <option value="Categoría 2">Categoría 2</option>

                        </select>

                  </div>

              </div>

      </div> <!-- </END ROW 1>  -->

      <hr>

      <div class="row">

        <!-- ENTRADA PARA PRECIO -->

        <div class="col-md-4">

            <div class="mb-3">

                <label for="nombre-empresa" class="form-label">Precio</label>

                <input class="form-control" type="number" id="editarPrecio" name="editarPrecio" step="any" min="0" placeholder="Precio del curso" required>

            </div>

        </div>

        <!-- ENTRADA PARA REQUISITOS -->

        <div class="col-md-4">

            <div class="mb-3">

                <label for="razon-social" class="form-label">Requisitos</label>

                <textarea class="form-control" id="editarRequisitoCurso" name="editarRequisitoCurso" rows="1" placeholder="Requisitos del curso" required></textarea>

            </div>

        </div>

        <!-- ENTRADA PARA MODALIDAD -->

        <div class="col-md-4">
            
            <div class="mb-3">

                <label for="razon-social" class="form-label">Modalidad</label>

                <!-- <input type="text" class="form-control input-lg" id="editarModalidad" name="editarModalidad" readonly required> -->

                <select class="form-control input-lg" id="editarModalidad" name="editarModalidad" required>
                    
                  <option value="">Selecionar modalidad</option>

                  <option value="Modalidad 1">Modalidad 1</option>

                  <option value="Modalidad 2">Modalidad 2</option>

                </select>

            </div>

        </div>

      </div> <!-- </END ROW 2>  -->

      <hr>

      <div class="row">

        <!-- ENTRADA PARA OBJETIVO -->

        <div class="col-md-4">

            <div class="mb-3">

                <label for="nombre-empresa" class="form-label">Objetivo</label>

                <textarea class="form-control" id="editarObjetivoCurso" name="editarObjetivoCurso" rows="1" placeholder="Objetivo del curso" required></textarea>

            </div>

        </div>

        <div class="col-md-4">

            <!-- ENTRADA PARA SUBIR FOTO -->

            <div class="form-group">
              
              <div class="panel">SUBIR IMAGEN</div>

              <input type="file" class="nuevaFoto" name="editarFoto">

              <p class="help-block">Peso máximo de la foto 2MB</p>

              <img src="vistas/img/cursos/default/anonymous.png" class="img-thumbnail previsualizar" width="100px">

              <input type="hidden" name="fotoActual" id="fotoActual">

            </div>

        </div>

      </div> <!-- </END ROW 3>  -->
  
          </div>

        </div>

        <!--=====================================
        PIE DEL MODAL
        ======================================-->

        <div class="modal-footer">

          <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Salir</button>

          <button type="submit" class="btn btn-primary me-1 mb-1">Guardar cambios</button>

          <button type="button" class="btn btn-danger btnEliminar">Eliminar</button>

        </div>

        <?php 

          $editarCurso = new ControladorCursos();
          $editarCurso -> ctrEditarCurso();
         ?>

      </form>

    </div>

  </div>

</div>

<?php   

  $eliminarCurso = new ControladorCursos();
  $eliminarCurso -> ctrEliminarCurso();
    
    include "footer.php";

 ?>

<script src="vistas/js/cursos.js"></script>
  
</body>
</html>


