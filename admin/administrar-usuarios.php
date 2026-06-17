<?php 
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'conn.php'; // Conexión a la base de datos

header('Content-Type: application/json'); // Aseguramos que la respuesta sea en formato JSON

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $response = array('status' => 'error', 'message' => 'Ocurrió un error inesperado.');

    // Comprobar si la acción es "editar"
    if (isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    // Obtener los datos del formulario para editar
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];
    $apePat = $_POST['apePat'];
    $apeMat = $_POST['apeMat'];
    $correo = $_POST['correo'];
    $rol = $_POST['rol'];
    $enlace_meet = $_POST['enlace_meet'];

    // Consulta SQL para actualizar el usuario
    $sql = "UPDATE usuarios SET nombre = ?, apePat = ?, apeMat = ?, correo = ?, tipoUsu = ?, enlace_meet = ?, usuario = ?, contrasena = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    // Usamos los tipos correctos: "sssssisisi" 
    $stmt->bind_param("ssssisssi", $nombre, $apePat, $apeMat, $correo, $rol, $enlace_meet, $usuario, $contrasena, $id);

    if ($stmt->execute()) {
        $response = array('status' => 'success', 'message' => 'Usuario actualizado exitosamente.');
    } else {
        $response = array('status' => 'error', 'message' => 'Error al actualizar el usuario.');
    }
}
 else if (isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
        // Comprobar si la acción es "eliminar"
        $id = $_POST['id'];

        // Consulta SQL para eliminar el usuario
        $sql = "DELETE FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Eliminar los registros de la tabla atencion donde isusu sea igual a $id
            $sql_atencion = "DELETE FROM atencion WHERE idusu = ?";
            $stmt_atencion = $conn->prepare($sql_atencion);
            $stmt_atencion->bind_param("i", $id);

            if ($stmt_atencion->execute()) {
                // Actualizar la tabla calendario, poniendo idusu a 0 donde idusu sea igual a $id
                $sql_calendario = "UPDATE calendario SET idusu = 0 WHERE idusu = ?";
                $stmt_calendario = $conn->prepare($sql_calendario);
                $stmt_calendario->bind_param("i", $id);

                if ($stmt_calendario->execute()) {
                    $response = array('status' => 'success', 'message' => 'Usuario, registros relacionados y calendario actualizados correctamente.');
                } else {
                    $response = array('status' => 'error', 'message' => 'Error al actualizar los registros en calendario.');
                }
            } else {
                $response = array('status' => 'error', 'message' => 'Error al eliminar los registros de atencion.');
            }
        } else {
            $response = array('status' => 'error', 'message' => 'Error al eliminar el usuario.');
        }
    } else if (isset($_POST['accion']) && $_POST['accion'] == 'agregar') {
        // Comprobar si la acción es "agregar"
        $nombre = $_POST['nombre'];
        $apePat = $_POST['apePat'];
        $apeMat = $_POST['apeMat'];
        $usuario = $_POST['usuario'];
        $contrasena = $_POST['contrasena'];
        $rol = $_POST['rol'];
        $correo = $_POST['correo']; 
        $telefono = $_POST['telefono']; 
        $enlace_meet = $_POST['enlace_meet'];

        // Consulta SQL para insertar un nuevo usuario
        $sql = "INSERT INTO usuarios (usuario, contrasena, nombre, apePat, apeMat, telefono, correo, tipoUsu, enlace_meet) VALUES (?,?,?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssisis", $usuario, $contrasena, $nombre, $apePat, $apeMat,  $telefono, $correo, $rol, $enlace_meet);

        if ($stmt->execute()) {
            $response = array('status' => 'success', 'message' => 'Usuario agregado exitosamente.');
        } else {
            $response = array('status' => 'error', 'message' => 'Error al agregar el usuario.');
        }
    }

    // Devolver la respuesta en formato JSON
    echo json_encode($response);
}
?>
