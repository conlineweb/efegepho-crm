<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

$userid = $_SESSION['uid'];

// Consulta SQL para traer todos los datos de la tabla usuarios
$sql = "SELECT * FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql); // Prepara la consulta
$stmt->bind_param("i", $userid);

$stmt->execute(); // Ejecuta la consulta

$result = $stmt->get_result(); // Obtiene el resultado


// Verifica si la consulta devuelve una fila
if ($result->num_rows > 0) {
    // Asigna los datos de la fila a la variable $user
    $user = $result->fetch_assoc();
 
} else {
    echo "No se encontró ningún usuario con ese ID.";
}

?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de usuario</title>
   
   
</head>
<!-- HTML para la tabla -->
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Perfil</h3>
              <br>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                
            </div>
        </div>
    </div>
    <section class="section">
        <div class="card">
            <!--<div class="card-header">-->
            <!--    <div class="calendar-controls">-->
            <!--        <button id="prevMonth" class="btn btn-secondary">↘</button>-->
            <!--        <h3 id="currentMonthYear"></h3>-->
            <!--        <button id="nextMonth" class="btn btn-secondary">↙</button>-->
            <!--    </div>-->
            <!--</div>-->
            <div class="card-body">
                <div class="container">
                   
                       <table id="tablaUsuarios" class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Apellido Paterno</th>
                                <th>Apellido Materno</th>
                                <th>Rol</th>
                                <th>Enlace Meet</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
    
<!-- Modal para editar usuario -->
<!-- Modal para editar usuario -->
<div class="modal fade" id="editarUsuarioModal" tabindex="-1" aria-labelledby="editarUsuarioModalLabel" aria-hidden="true">
     <input type="hidden" name="id" id="userId">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editarUsuarioModalLabel">Editar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editarUsuarioForm">
                    <input type="hidden" id="usuarioId" name="id">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="apePat" class="form-label">Apellido Paterno</label>
                        <input type="text" class="form-control" id="apePat" name="apePat" required>
                    </div>
                    <div class="mb-3">
                        <label for="apeMat" class="form-label">Apellido Materno</label>
                        <input type="text" class="form-control" id="apeMat" name="apeMat" required>
                    </div>
                    <div class="mb-3">
                        <label for="enlace_meet" class="form-label">Enlace Google Meet</label>
                        <input type="text" class="form-control" id="enlace_meet" name="enlace_meet" required>
                    </div>
                   
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </form>
            </div>
        </div>
    </div>
</div>




<?php

include 'footer.php';

?>
   

<!-- Incluir librerías de jQuery y DataTables -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Asegúrate de que esta línea esté antes -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

<script>
// Pasa los datos PHP a JavaScript
const user = <?php echo json_encode($user); ?>;

// Llenar la tabla con los datos de usuarios
$(document).ready(function() {
    // Primero, destruimos cualquier instancia de DataTables si existe
    if ($.fn.dataTable.isDataTable('#tablaUsuarios')) {
        $('#tablaUsuarios').DataTable().clear().destroy();
    }

    // Agregar filas a la tabla
  
    $('#tablaUsuarios tbody').append(`
            <tr>
                <td>${user.id}</td>
                <td>${user.nombre}</td>
                <td>${user.apePat}</td>
                <td>${user.apeMat}</td>
                
                <td>${user.tipoUsu === 0 ? 'Admin' : user.tipoUsu === 1 ? 'Ventas' : ''}</td>
                <td>${user.enlace_meet}</td>
                <td>
                    
                    <button class="btn btn-primary" onclick="editarUsuario()">Editar</button>
                
                </td>
            </tr>
        `);

    // // Inicializar DataTables
    // $('#tablaUsuarios').DataTable();
});


function editarUsuario() {
        console.log(user)
    // Rellenar el formulario con los datos del usuario
    $('#usuarioId').val(user.id);
    $('#nombre').val(user.nombre);
    $('#apePat').val(user.apePat);
    $('#apeMat').val(user.apeMat);
    $('#enlace_meet').val(user.enlace_meet);
    
    // Mostrar el modal
    $('#editarUsuarioModal').modal('show');
    

}


// Enviar los datos del formulario al archivo PHP usando AJAX solo cuando se haga clic en "Guardar cambios"
$('#editarUsuarioForm').on('submit', function(e) {
    e.preventDefault(); // Prevenir el envío del formulario por defecto

    var formData = $(this).serialize();
    formData += '&accion=editar';
     formData += '&rol='+user.tipoUsu
    console.log(formData)
    // Obtener los datos del formulario

    
    //Realizar la solicitud AJAX
    $.ajax({
        type: 'POST',
        url: 'administrar-usuarios.php', // Archivo PHP para procesar la edición
        data: formData,
        success: function(response) {
            alert("editar "+'Usuario actualizado exitosamente.');
            $('#editarUsuarioModal').modal('hide'); // Cerrar el modal
            // Recargar la tabla con los nuevos datos (opcional)
            location.reload();
        },
        error: function() {
            alert('Hubo un error al actualizar el usuario.');
        }
    });
});








</script>
<!-- Bootstrap CSS -->


<!-- Bootstrap JS y Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

