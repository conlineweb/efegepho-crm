<?php
// Incluir el archivo de conexión y los modelos y controladores necesarios
require_once "modelos/conexion.php";
require_once "modelos/temas.modelo.php"; // Asegúrate de que la ruta sea correcta
require_once "controladores/temas.controlador.php"; // Asegúrate de que la ruta sea correcta

// Verificar si se envían los datos mediante el método POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Aquí se reciben los datos del formulario
    $titulo = isset($_POST['titulo']) ? $_POST['titulo'] : '';
    $contenido = isset($_POST['text']) ? $_POST['text'] : '';
    $idCurso = isset($_POST['idCurso']) ? $_POST['idCurso'] : '';
    $ordenAsk = isset($_POST['OrdenAsk']) ? $_POST['OrdenAsk'] : '';
    $consulta = isset($_POST['consulta']) ? $_POST['consulta'] : '';
    $editar = isset($_POST['editar']) ? $_POST['editar'] : '';
    $eliminar = isset($_POST['eliminar']) ? $_POST['eliminar'] : '';

    // Log de datos recibidos
    error_log("Datos recibidos: " . print_r($_POST, true));

    // Verificar la acción solicitada
    if ($consulta) {
        // Lógica para consultar datos por ID
        error_log("Entrando en consulta por ID: " . $consulta);
        $tema = ControladorTemas::ctrMostrarTemas("id_curso", $consulta);
        if ($tema) {
            error_log("Datos de consulta encontrados: " . print_r($tema, true));
            echo json_encode($tema); // Devolver los datos en formato JSON
        } else {
            error_log("No se encontraron datos para el ID: " . $consulta);
            echo json_encode(["status" => "error", "message" => "No se encontraron datos para el ID proporcionado."]);
        }
    } elseif ($editar) {
        // Lógica para editar tema
        error_log("Entrando en edición de tema con datos: " . $editar);
        $datosEdicion = json_decode($editar, true); // Convertir JSON a array
        $idTema = $datosEdicion['id'] ?? null; // Suponiendo que 'id' está presente en los datos

        // Verificar que los campos requeridos no estén vacíos
        if (!empty($idTema) && !empty($titulo) && !empty($contenido)) {
            $datos = array(
                "id" => $idTema,
                "titulo" => $titulo,
                "contenido" => $contenido,
                "id_curso" => $idCurso,
                "preguntas" => $ordenAsk
            );
            error_log("Datos para editar: " . print_r($datos, true));
            $resultado = ControladorTemas::ctrEditarTema($datos);
            if ($resultado) {
                echo json_encode(["status" => "success", "message" => "Tema actualizado correctamente."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error al actualizar el tema."]);
            }
        } else {
            error_log("Faltan datos para editar. ID: $idTema, Título: $titulo, Contenido: $contenido");
            echo json_encode(["status" => "error", "message" => "Faltan datos para editar."]);
        }
    } elseif ($eliminar) {
        // Lógica para eliminar tema
        error_log("Entrando en eliminación de tema con ID: " . $eliminar);
        $idTema = $eliminar;
        if (!empty($idTema)) {
            $resultado = ControladorTemas::ctrEliminarTema($idTema);
            if ($resultado) {
                echo json_encode(["status" => "success", "message" => "Tema eliminado correctamente."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error al eliminar el tema."]);
            }
        } else {
            error_log("ID de tema no proporcionado para eliminación.");
            echo json_encode(["status" => "error", "message" => "ID de tema no proporcionado."]);
        }
    } else {
        // Lógica para crear tema
        error_log("Entrando en creación de tema.");
        if (!empty($titulo) && !empty($contenido) && !empty($idCurso) && !empty($ordenAsk)) {
            $resultado = ControladorTemas::ctrCrearTema();
            if ($resultado) {
                echo json_encode(["status" => "success", "message" => "Tema creado correctamente."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error al crear el tema."]);
            }
        } else {
            error_log("Datos incompletos para creación de tema: Título: $titulo, Contenido: $contenido, ID Curso: $idCurso, Preguntas: $ordenAsk");
            echo json_encode(["status" => "error", "message" => "Todos los campos son requeridos."]);
        }
    }
} else {
    // Si no se recibió una solicitud POST, redirigir o mostrar un mensaje
    error_log("Método no permitido: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
}
