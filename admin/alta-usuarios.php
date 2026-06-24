<?php

 
include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['login']) || $_SESSION['login'] === false) {
    header('Location: ingreso.php');
   exit(); 
 }
 $tipoUsuario = $_SESSION['tus'];
 
 if (!usuarioTipoEsAdminLike($tipoUsuario)) {
    header('Location: index.php');
    exit();
}
 

$userid = $_SESSION['uid'];

// Consulta SQL para traer todos los datos de la tabla usuarios
$sql = "SELECT * FROM usuarios";
$stmt = $conn->prepare($sql); // Prepara la consulta
$stmt->execute(); // Ejecuta la consulta

$result = $stmt->get_result(); // Obtiene el resultado

// Verifica si hay resultados
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row; // Guarda los usuarios en un array
    }
} else {
    echo "No se encontraron usuarios.";
}
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alta de usuarios</title>
   
   
</head>
<!-- HTML para la tabla -->
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Alta de usuarios</h3>
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
                   <div class ="d-flex">
                        <button class="btn btn-primary" onclick="agregarUsuario()">Agregar</button>
                   </div><br><br>
                       <table id="tablaUsuarios" class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Apellido Paterno</th>
                                <th>Apellido Materno</th>
                                <th>Usuario</th>
                                <th>Contraseña</th>

                                <th>Correo</th>
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
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" required>
                    </div>
                    <div class="mb-3">
                        <label for="contrasena" class="form-label">Contraseña</label>
                        <input type="text" class="form-control" id="contrasena" name="contrasena" required>
                    </div>
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
                        <label for="correo" class="form-label">Correo</label>
                        <input type="text" class="form-control" id="correo" name="correo" required>
                    </div>
                    <div class="mb-3">
                        <label for="enlace_meet" class="form-label">Enlace Google Meet</label>
                        <input type="text" class="form-control" id="enlace_meet" name="enlace_meet" required>
                    </div>
                    <div class="mb-3">
                        <label for="rol" class="form-label">Rol de Usuario</label>
                        <select class="form-select" id="rol" name="rol" required>
                            <option value="0">Admin</option>
                            <option value="1">Vendedor</option>
                            <option value="2">Gestor de galer&iacute;a</option>
                            <option value="3">Gestor de pagos</option>
                            <option value="4">Marketing</option>
                            <option value="5">L&iacute;der de Planners</option>

                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para agregar usuario -->
<div class="modal fade" id="agregarUsuarioModal" tabindex="-1" aria-labelledby="agregarUsuarioModalLabel" aria-hidden="true">
    <input type="hidden" name="accion" value="agregar">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="agregarUsuarioModalLabel">Agregar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="agregarUsuarioForm">
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
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" required>
                    </div>
                    <div class="mb-3">
                        <label for="contrasena" class="form-label">Contraseña</label>
                        <input type="text" class="form-control" id="contrasena" name="contrasena" required>
                    </div>
                    <div class="mb-3">
                        <label for="rol" class="form-label">Rol de Usuario</label>
                        <select class="form-select" id="rol" name="rol" required>
                            <option value="0">Admin</option>
                            <option value="1">Vendedor</option>
                            <option value="2">Gestor de galer&iacute;a</option>
                            <option value="3">Gestor de pagos</option>
                            <option value="4">Marketing</option>
                            <option value="5">L&iacute;der de Planners</option>

                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" required>
                    </div>
                    <div class="mb-3">
                        <label for="correo" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="correo" name="correo" required>
                    </div>
                    <div class="mb-3">
                        <label for="enlace_meet" class="form-label">Enlace Google Meet</label>
                        <input type="text" class="form-control" id="enlace_meet" name="enlace_meet" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Agregar Usuario</button>
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
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">

<script>
// Pasa los datos PHP a JavaScript
const usuarios = <?php echo json_encode($users); ?>;
const rolLabels = <?php
    $labels = [];
    for ($i = 0; $i <= 5; $i++) {
        $labels[$i] = usuarioRolLabel($i);
    }
    echo json_encode($labels);
?>;
console.log(usuarios)

// Llenar la tabla con los datos de usuarios
$(document).ready(function() {
    // Primero, destruimos cualquier instancia de DataTables si existe
    if ($.fn.dataTable.isDataTable('#tablaUsuarios')) {
        $('#tablaUsuarios').DataTable().clear().destroy();
    }

    // Agregar filas a la tabla
    usuarios.forEach(user => {
        $('#tablaUsuarios tbody').append(`
            <tr>
                <td>${user.id}</td>
                <td>${user.nombre}</td>
                <td>${user.apePat}</td>
                <td>${user.apeMat}</td>
                <td>${user.usuario}</td>
                <td>${user.contrasena}</td>
                <td>${user.correo}</td>
                <td>${rolLabels[user.tipoUsu] || 'Desconocido'}</td>
                <td>${user.enlace_meet}</td>
                <td>
                    
                    <button class="btn btn-primary  mx-2 my-1" onclick="editarUsuario(${user.id})">Editar</button>
                      <button class="btn btn-dark  mx-2 my-1" onclick="eliminarUsuario(${user.id})">Eliminar</button>
                </td>
            </tr>
        `);
    });

    // Inicializar DataTables
    $('#tablaUsuarios').DataTable();
});

function agregarUsuario() {
    // Buscar el usuario por id
 
    // Mostrar el modal
    $('#agregarUsuarioModal').modal('show');
    

}

function editarUsuario(id) {
    // Buscar el usuario por id
    const usuario = usuarios.find(u => u.id === id);
        console.log(usuario)
    // Rellenar el formulario con los datos del usuario
    $('#usuarioId').val(usuario.id);
        $('#usuario').val(usuario.usuario);
    $('#contrasena').val(usuario.contrasena);

    $('#nombre').val(usuario.nombre);
    $('#apePat').val(usuario.apePat);
    $('#apeMat').val(usuario.apeMat);
    $('#correo').val(usuario.correo);
    $('#rol').val(usuario.tipoUsu);

    $('#enlace_meet').val(usuario.enlace_meet);
    
    // Mostrar el modal
    $('#editarUsuarioModal').modal('show');
    

}


// Enviar los datos del formulario al archivo PHP usando AJAX solo cuando se haga clic en "Guardar cambios"
$('#editarUsuarioForm').on('submit', function(e) {
    e.preventDefault(); // Prevenir el envío del formulario por defecto

    var formData = $(this).serialize();
    formData += '&accion=editar';

    //Realizar la solicitud AJAX
    $.ajax({
        type: 'POST',
        url: 'administrar-usuarios.php', // Archivo PHP para procesar la edición
        data: formData,
           success: function(response) {
            // Usamos SweetAlert para mostrar el resultado
            if (response.status === 'success') {
                Swal.fire({
                    title: 'Editado correctamente',
                    text: response.message,
                    icon: 'success'
                }).then(() => {
                    location.reload(); // Recargar la página para ver los cambios
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.message,
                    icon: 'error'
                });
            }
        },
        error: function(xhr, status, error) {
            // Si hubo un error en la solicitud AJAX
            console.error("Error AJAX:", status, error);
            Swal.fire({
                title: 'Error',
                text: 'Hubo un error al editar el usuario.',
                icon: 'error'
            });
        }
    });
});


$('#agregarUsuarioForm').on('submit', function(e) {
    e.preventDefault(); // Evitar el comportamiento por defecto del formulario

    // Obtener los datos del formulario
    var formData = $(this).serialize();
        formData += '&accion=agregar';
      
        console.log(formData)
    // Enviar la solicitud AJAX
$.ajax({
        type: 'POST',
        url: 'administrar-usuarios.php', // Archivo PHP para procesar la edición
        data: formData, // Los datos del formulario
        success: function(response) {
            // Usamos SweetAlert para mostrar el resultado
            if (response.status === 'success') {
                Swal.fire({
                    title: 'Agregado correctamente',
                    text: response.message,
                    icon: 'success'
                }).then(() => {
                    location.reload(); // Recargar la página para ver los cambios
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.message,
                    icon: 'error'
                });
            }
        },
        error: function(xhr, status, error) {
            // Si hubo un error en la solicitud AJAX
            console.error("Error AJAX:", status, error);
            Swal.fire({
                title: 'Error',
                text: 'Hubo un error al eliminar al usuario.',
                icon: 'error'
            });
        }
    });
});




function eliminarUsuario(id) {
    // Usamos SweetAlert para preguntar si el usuario está seguro de eliminar
    Swal.fire({
        title: '¿Estás seguro?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'dark',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Si el usuario confirma, realizamos la solicitud AJAX
            $.ajax({
                type: 'POST',
                url: 'administrar-usuarios.php', // El archivo PHP que procesará la eliminación
                data: {
                    accion: 'eliminar', // Indicamos que la acción es "eliminar"
                    id: id // Pasamos el ID del usuario a eliminar
                },
                success: function(response) {
                    // Usamos SweetAlert para mostrar el resultado
                    if (response.status === 'success') {
                        Swal.fire({
                            title: 'Eliminado',
                            text: response.message,
                            icon: 'success'
                        }).then(() => {
                            location.reload(); // Recargar la página para ver los cambios
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error'
                        });
                    }
                },
                error: function() {
                    // Si hubo un error en la solicitud AJAX
                    Swal.fire({
                        title: 'Error',
                        text: 'Hubo un error al eliminar el usuario.',
                        icon: 'error'
                    });
                }
            });
        }
    });
}




</script>
<!-- Bootstrap CSS -->


<!-- Bootstrap JS y Popper.js -->
<!-- Incluyendo SweetAlert desde CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

