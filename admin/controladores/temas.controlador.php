<?php

class ControladorTemas {

    /*=============================================
    CREAR TEMA
    =============================================*/

    static public function ctrCrearTema() {
        // Verificar que existan los datos requeridos
        if (isset($_POST["titulo"]) && isset($_POST["text"]) && isset($_POST["idCurso"]) && isset($_POST["OrdenAsk"])) {

            $tabla = "temas";

            // Preparar los datos recibidos de FormData
            $datos = array(
                "id_curso" => $_POST["idCurso"],
                "titulo" => $_POST["titulo"],
                "contenido" => $_POST["text"],
                "preguntas" => $_POST["OrdenAsk"] // Guardar JSON de preguntas tal cual
            );

            // Llamar al modelo para guardar el tema
            $respuesta = ModeloTemas::mdlCrearTema($tabla, $datos);

            // Mostrar el mensaje de confirmación o error
            if ($respuesta == "ok") {
                echo '<script>
                        Swal.fire({
                              icon: "success",
                              title: "El tema ha sido guardado correctamente",
                              showConfirmButton: true,
                              confirmButtonText: "Cerrar"
                              }).then(function(result){
                                    if (result.isConfirmed) {
                                        window.location = "temas.php";
                                    }
                                });
                      </script>';
            } else {
                echo '<script>
                        Swal.fire({
                              icon: "error",
                              title: "Hubo un error al guardar el tema",
                              text: "Por favor, intenta nuevamente",
                              confirmButtonText: "Cerrar"
                        });
                      </script>';
            }
        }
    }

    /*=============================================
    MOSTRAR TEMAS
    =============================================*/

    static public function ctrMostrarTemas($item, $valor) {
        $tabla = "temas";
        $respuesta = ModeloTemas::mdlMostrarTemas($tabla, $item, $valor);
        return $respuesta;
    }

    /*=============================================
    EDITAR TEMA
    =============================================*/

    static public function ctrEditarTema() {
        if (isset($_POST["idTema"])) {

            $tabla = "temas";

            $datos = array(
                "id" => $_POST["idTema"],
                "id_curso" => $_POST["id_curso"],
                "titulo" => $_POST["titulo"],
                "contenido" => $_POST["contenido"],
                "preguntas" => $_POST["preguntas"]
            );

            $respuesta = ModeloTemas::mdlEditarTema($tabla, $datos);

            if ($respuesta == "ok") {
                echo '<script>
                        Swal.fire({
                              icon: "success",
                              title: "El tema ha sido editado correctamente",
                              showConfirmButton: true,
                              confirmButtonText: "Cerrar"
                              }).then(function(result){
                                    if (result.isConfirmed) {
                                        window.location = "temas.php";
                                    }
                                });
                      </script>';
            }
        }
    }

    /*=============================================
    ELIMINAR TEMA
    =============================================*/

    static public function ctrEliminarTema() {
        if (isset($_GET["idTemaEli"])) {

            $tabla = "temas";
            $datos = $_GET["idTemaEli"];

            $respuesta = ModeloTemas::mdlEliminarTema($tabla, $datos);

            if ($respuesta == "ok") {
                echo '<script>
                        Swal.fire({
                              icon: "success",
                              title: "El tema ha sido borrado correctamente",
                              showConfirmButton: true,
                              confirmButtonText: "Cerrar"
                              }).then(function(result){
                                    if (result.isConfirmed) {
                                        window.location = "temas.php";
                                    }
                                });
                      </script>';
            }
        }
    }
}
