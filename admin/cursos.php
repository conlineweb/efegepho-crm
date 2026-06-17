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

  </head>

  <body>

    <div class="page-heading">

      <div class="page-title">

            <div class="row">

                <div class="col-12 col-md-6 order-md-1 order-last">

                    <h3>Registrar Curso</h3>
                  
                </div>

                <div class="col-12 col-md-6 order-md-2 order-first">

                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">

                        <ol class="breadcrumb">

                            <!-- <li class="breadcrumb-item"><a href="index.html">Dashboard</a></li> -->

                            <li class="breadcrumb-item active" aria-current="page">Registrar Curso</li>

                        </ol>

                    </nav>

                </div>

            </div> <!-- </ROW>  -->

        </div> <!-- </PAGE TITLE>  -->

    </div> <!-- </PAGE HEADING>  -->

    <div class="col-md-12 col-xs-12" id="formulario">

      <section id="input-sizing">

          <div class="row match-height">

              <div class="col-12">

                  <div class="card">

                      <div class="card-header">

                          <!-- <h4 class="card-title">Control Sizing Option</h4> -->

                      </div>

                      <div class="card-body">

                        <form action="" method="post" enctype="multipart/form-data">

                          <div class="row">

                            <!-- ENTRADA PARA NOMBRE -->

                            <div class="col-sm-4">

                                <h6>Nombre</h6>

                                <input class="form-control form-control-lg" type="text" id="nuevoCurso" name="nuevoCurso" placeholder="Nombre del curso" required>

                            </div>

                            <!-- ENTRADA PARA DESCRIPCIÓN -->

                            <div class="col-sm-4">

                                <h6>Descripción</h6>

                                <textarea class="form-control" id="nuevaDescripcionCurso" name="nuevaDescripcionCurso" rows="1" placeholder="Descripción del curso" required></textarea>

                            </div>

                            <!-- ENTRADA PARA CATEGORÍA -->

                            <div class="col-sm-4">

                                <h6>Categoría</h6>

                                <select class="form-control form-control-lg" id="nuevaCategoria" name="nuevaCategoria" required>
                  
                                  <option value="">Selecionar categoría</option>

                                  <option value="Categoría 1">Categoría 1</option>

                                  <option value="Categoría 2">Categoría 2</option>

                                </select>

                            </div>

                          </div> <!-- </END ROW>  -->

                          <br>

                          <div class="row">
                            
                            <!-- ENTRADA PARA PRECIO -->

                            <div class="col-sm-4">

                                <h6>Precio</h6>

                                <input class="form-control form-control-lg" type="number" id="nuevoPrecio" name="nuevoPrecio" step="any" min="0" placeholder="Precio del curso" required>

                            </div>

                            <!-- ENTRADA PARA REQUISITOS -->

                            <div class="col-sm-4">

                                <h6>Requisitos</h6>

                                <textarea class="form-control form-control-lg" id="nuevoRequisitoCurso" name="nuevoRequisitoCurso" rows="1" placeholder="Requisitos del curso" required></textarea>

                            </div>

                            <!-- ENTRADA PARA MODALIDAD -->

                            <div class="col-sm-4">

                                <h6>Modalidad</h6>

                                <select class="form-control input-lg" id="nuevaModalidad" name="nuevaModalidad" required>
                    
                                  <option value="">Selecionar modalidad</option>

                                  <option value="Modalidad 1">Modalidad 1</option>

                                  <option value="Modalidad 2">Modalidad 2</option>

                                </select>

                            </div>

                          </div> <!-- </END ROW>  -->

                          <br>

                          <div class="row">

                            <!-- ENTRADA PARA OBJETIVO -->

                            <div class="col-sm-4">

                              <h6>Objetivo</h6>

                              <textarea class="form-control" id="nuevoObjetivoCurso" name="nuevoObjetivoCurso" rows="1" placeholder="Objetivo del curso" required></textarea>

                            </div>

                            <!-- ENTRADA PARA OBJETIVO -->

                            <div class="col-sm-4">

                              <div class="panel">SUBIR FOTO</div>

                              <input type="file" class="nuevaFoto" name="nuevaFoto" required>

                              <p class="help-block">Peso máximo de la foto 2MB</p>

                              <img src="vistas/img/cursos/default/anonymous.png" class="img-thumbnail previsualizar" width="100px">

                            </div>

                          </div><!-- </END ROW>  -->

                          <div class="col-sm-12 d-flex justify-content-end">
                            
                            <button type="submit" class="btn btn-primary me-1 mb-1">Guardar Curso</button>
                                              
                          </div>

                            <?php 

                              $crearCurso = new ControladorCursos();
                              $crearCurso -> ctrCrearCurso();
                            ?>
                          
                        </form> <!-- </END FORM>  -->

                      </div> <!-- </END CARD BODY>  -->

                  </div> <!-- </END CARD>  -->

              </div> <!-- </END COL-12>  -->

          </div> <!-- </END MATCH-HEIGHT>  -->

      </section> <!-- </END SECTION>  -->

    </div> <!-- </END FORMULARIO>  -->

        <?php

          include 'footer.php';
        ?>    
    
<script src="vistas/js/cursos.js"></script><!-- 
    <script src="vistas/js/subirImagen.js"></script> -->

  </body>
</html>
