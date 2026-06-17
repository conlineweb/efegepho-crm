<?php  
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexi車n a la base de datos

// Realizamos la consulta para obtener los registros de la tabla "galeria"
$query = "SELECT * FROM galeria";
$result = mysqli_query($conn, $query);

// Creamos un array para almacenar los resultados
$clientes = [];

if (mysqli_num_rows($result) > 0) {
    // Recorremos los registros y los agregamos al array
    while ($row = mysqli_fetch_assoc($result)) {
        // Agregar los resultados al array
        $clientes[] = $row;
    }
}

// Cerramos la conexi車n a la base de datos
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta</title>
    <link rel="stylesheet" href="assets/extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            padding: 20px;
        }
        div:where(.swal2-container) button:where(.swal2-styled):where(.swal2-confirm) {
    border: 0;
    border-radius: .25em;
    background: initial;
    background-color: white;
    color: #fff;
    font-size: 1em;
}

    </style>
</head>
<body>
    <div class="container py-2">
         <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-12 order-md-1 order-last">
                <h3>Clientes</h3>
               <br>
            </div>
            <div class="col-12 col-md-12 order-md-2 order-first">
           
            </div>
        </div>
    </div>
     <div class="d-flex justify-content-between aling-items-center">
             <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pills-pending-tab" data-bs-toggle="pill" data-bs-target="#pills-pending" type="button" role="tab" aria-controls="pills-pending" aria-selected="true">
                    Pendientes
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link " id="pills-sold-tab" data-bs-toggle="pill" data-bs-target="#pills-sold" type="button" role="tab" aria-controls="pills-sold" aria-selected="false">
                    Vendidos
                </button>
              </li>
               <li class="nav-item" role="presentation">
                <button class="nav-link " id="pills-no-sold-tab" data-bs-toggle="pill" data-bs-target="#pills-no-sold" type="button" role="tab" aria-controls="pills-no-sold" aria-selected="false">
                   No Vendidos
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link " id="pills-deleted-tab" data-bs-toggle="pill" data-bs-target="#pills-deleted" type="button" role="tab" aria-controls="pills-deleted" aria-selected="false">
                  Eliminados
                </button>
              </li>
            </ul>
           
       </div>

        <div class="card">
            <div class="card-body">
                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="pills-pending" role="tabpanel" aria-labelledby="pills-pending-tab">
                        <!-- Tabla de clientes vac赤a, ya que los datos se llenar芍n desde JS -->
                        <table id="pendientesTable" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Tel&eacute;fono</th>
                                    <th>Email</th>
                                    <th>Fecha Registro Cliente</th>
                                    <th>Fecha Registro Galeria</th>
                                    <th>Fecha Vencimiento Galeria</th>
                                    <th>Estatus</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Los datos se llenar芍n con JS -->
                            </tbody>
                        </table>
                    </div>
                     <div class="tab-pane fade " id="pills-sold" role="tabpanel" aria-labelledby="pills-sold-tab">
                        <!-- Tabla de clientes vac赤a, ya que los datos se llenar芍n desde JS -->
                        <table id="vendidasTable" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Tel&eacute;fono</th>
                                    <th>Email</th>
                                    <th>Fecha Registro Cliente</th>
                                    <th>Fecha Registro Galeria</th>
                                    <th>Fecha Vencimiento Galeria</th>
                                    <th>Estatus</th>
                                                                        <th>Acciones</th>

                                </tr>
                            </thead>
                            <tbody>
                                <!-- Los datos se llenar芍n con JS -->
                            </tbody>
                        </table>
                    </div>
                     <div class="tab-pane fade " id="pills-no-sold" role="tabpanel" aria-labelledby="pills-no-sold-tab">
                        <!-- Tabla de clientes vac赤a, ya que los datos se llenar芍n desde JS -->
                        <table id="noVendidasTable" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Tel&eacute;fono</th>
                                    <th>Email</th>
                                    <th>Fecha Registro Cliente</th>
                                    <th>Fecha Registro Galeria</th>
                                    <th>Fecha Vencimiento Galeria</th>
                                    <th>Estatus</th>
                                                                        <th>Acciones</th>

                                </tr>
                            </thead>
                            <tbody>
                                <!-- Los datos se llenar芍n con JS -->
                            </tbody>
                        </table>
                    </div>
                     <div class="tab-pane fade " id="pills-deleted" role="tabpanel" aria-labelledby="pills-deleted-tab">
                        <!-- Tabla de clientes vac赤a, ya que los datos se llenar芍n desde JS -->
                        <table id="eliminadasTable" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Tel&eacute;fono</th>
                                    <th>Email</th>
                                    <th>Fecha Registro Cliente</th>
                                    <th>Fecha Registro Galeria</th>
                                    <th>Fecha Vencimiento Galeria</th>
                                    <th>Estatus</th>
                                     <th>Acciones</th>

                                </tr>
                            </thead>
                            <tbody>
                                <!-- Los datos se llenar芍n con JS -->
                            </tbody>
                        </table>
                    </div>
                  </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Carga los scripts necesarios -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
        $(document).ready(function() {
            // Convertimos el array PHP en un array JS
            const clientes = <?php echo json_encode($clientes); ?>;
            
            // Obtener la fecha y hora actual
            const now = new Date();

// Filtrar los objetos con estatus '0' (pendientes)
let pendientes = clientes.filter(item => item.estatus === '0' );
console.log(pendientes)
// Filtrar los objetos con estatus '1' (atendidas)
let vendidas = clientes.filter(item => item.estatus === '1' );

let no_vendidas = clientes.filter(item => item.estatus === '2' );
let eliminadas = clientes.filter(item => item.estatus === '3' );
            
 
            // Llenamos la tabla con los datos del array
            const pendientesBody = $('#pendientesTable tbody');
            if(pendientes.length > 0){
                pendientes.forEach(cliente => {
                const estatus = getEstatus(cliente.estatus); // Funci車n para obtener el estatus como texto

                const row = `
                    <tr>
                        <td>${cliente.id}</td>
                        <td>${cliente.nombre}</td>
                        <td>${cliente.phone}</td>
                        <td>${cliente.email}</td>
                        <td>${cliente.fecha_registro_cliente}</td>
                        <td>${cliente.fecha_registro_galeria}</td>
                        <td>${cliente.fecha_vencimiento_galeria}</td>
                        <td>${estatus}</td>
                        <td><button class="btn btn-warning btn-sm change-status" data-id="${cliente.id}"  data-idestatus="0">Cambiar Estatus</button></td>
                    </tr>
                `;

                pendientesBody.append(row);
            });
            }else{
                const row = `
                  <div style="width:100%;" class="text-center"> no hay datos</div>
                `;
                 pendientesBody.append(row);
            }
            
            
            
              const noVendidasBody = $('#noVendidasTable tbody');
            no_vendidas.forEach(cliente => {
                const estatus = getEstatus(cliente.estatus); // Funci車n para obtener el estatus como texto

                const row = `
                    <tr>
                        <td>${cliente.id}</td>
                        <td>${cliente.nombre}</td>
                        <td>${cliente.phone}</td>
                        <td>${cliente.email}</td>
                        <td>${cliente.fecha_registro_cliente}</td>
                        <td>${cliente.fecha_registro_galeria}</td>
                        <td>${cliente.fecha_vencimiento_galeria}</td>
                        <td>${estatus}</td>
                                                <td><button class="btn btn-warning btn-sm change-status" data-id="${cliente.id}"  data-idestatus="2">Cambiar Estatus</button></td>

                    </tr>
                `;

                noVendidasBody.append(row);
            });




             const vendidasBody = $('#vendidasTable tbody');
            vendidas.forEach(cliente => {
                const estatus = getEstatus(cliente.estatus); // Funci車n para obtener el estatus como texto

                const row = `
                    <tr>
                        <td>${cliente.id}</td>
                        <td>${cliente.nombre}</td>
                        <td>${cliente.phone}</td>
                        <td>${cliente.email}</td>
                        <td>${cliente.fecha_registro_cliente}</td>
                        <td>${cliente.fecha_registro_galeria}</td>
                        <td>${cliente.fecha_vencimiento_galeria}</td>
                        <td>${estatus}</td>
                                                <td><button class="btn btn-warning btn-sm change-status" data-id="${cliente.id}"  data-idestatus="1">Cambiar Estatus</button></td>

                    </tr>
                `;

                vendidasBody.append(row);
            });
            
            
             const eliminadasBody = $('#eliminadasTable tbody');
            eliminadas.forEach(cliente => {
                const estatus = getEstatus(cliente.estatus); // Funci車n para obtener el estatus como texto

                const row = `
                    <tr>
                        <td>${cliente.id}</td>
                        <td>${cliente.nombre}</td>
                        <td>${cliente.phone}</td>
                        <td>${cliente.email}</td>
                        <td>${cliente.fecha_registro_cliente}</td>
                        <td>${cliente.fecha_registro_galeria}</td>
                        <td>${cliente.fecha_vencimiento_galeria}</td>
                        <td>${estatus}</td>
                        <td><button class="btn btn-warning btn-sm change-status" data-id="${cliente.id}"  data-idestatus="3">Cambiar Estatus</button></td>

                    </tr>
                `;

                eliminadasBody.append(row);
            });
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            // Inicializamos el DataTable
            $('#clientesTable').DataTable({
                "responsive": true,
                "language": {
                    "url": '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-MX.json'
                }, 
                "order": [],
                "columnDefs": [
                    {
                        "targets": [0],
                        "orderable": false
                    }
                ]
            });

            // Funci車n para obtener el estatus como texto
            function getEstatus(estatus) {
                switch (estatus) {
                    case 0:
                        return 'Pendientes de vender';
                    case 1:
                        return 'Vendido';
                    case 2:
                        return 'No vendido';
                    case 3:
                        return 'Eliminado';
                        case "0":
                        return 'Pendientes de vender';
                    case "1":
                        return 'Vendido';
                    case "2":
                        return 'No vendido';
                    case "3":
                        return 'Eliminado';
                    default:
                        return 'Desconocido';
                }
            }

            // Manejo del evento clic en el bot車n "Cambiar Estatus"
            $('body').on('click', '.change-status', function() {
                const id = $(this).data('id'); // Obtener el id del cliente
                const idestatus = $(this).data('idestatus'); // Obtener el id del cliente
                console.log(idestatus)
                
let buttons = "";
switch (idestatus) {
  case 0: // Pendiente
    buttons = `
      <button class="btn btn-primary" onclick="changeStatus(${id}, 1)">Vendido</button>
      <button class="btn btn-secondary" onclick="changeStatus(${id}, 2)">No Vendido</button>
      <button class="btn btn-danger" onclick="changeStatus(${id}, 3)">Eliminado</button>
    `;
    break;
  case 1: // Vendido
    buttons = `
      <button class="btn btn-warning" onclick="changeStatus(${id}, 0)">Pendiente</button>
      <button class="btn btn-secondary" onclick="changeStatus(${id}, 2)">No Vendido</button>
      <button class="btn btn-danger" onclick="changeStatus(${id}, 3)">Eliminado</button>
    `;
    break;
  case 2: // No Vendido
    buttons = `
      <button class="btn btn-primary" onclick="changeStatus(${id}, 1)">Vendido</button>
      <button class="btn btn-warning" onclick="changeStatus(${id}, 0)">Pendiente</button>
      <button class="btn btn-danger" onclick="changeStatus(${id}, 3)">Eliminado</button>
    `;
    break;
  case 3: // Eliminado
 
    buttons = `
          <button class="btn btn-warning" onclick="changeStatus(${id}, 0)">Pendiente</button>
      <button class="btn btn-primary" onclick="changeStatus(${id}, 1)">Vendido</button>
           <button class="btn btn-secondary" onclick="changeStatus(${id}, 2)">No Vendido</button>

    `;
    break;
  default: // Handle unexpected statuses (optional)
    buttons = "<span>Status desconocido</span>";
}

                Swal.fire({
                title: 'Cambiar estatus',
                icon: 'question',
                showCancelButton: true, // Desactivamos el bot車n de cancelar
                confirmButtonText:buttons,
                showDenyButton: false, // Mostrar el bot車n de "No Respondi車"
                
                denyButtonText: '',
            });

            });

           
        });
        
         // Funci車n para actualizar el estatus
            function changeStatus(id, status) {
    console.log("ID:", id, "Estatus:", status); // Imprime los valores para verificar
                $.ajax({
                    url: 'cambiar_estatus_galeria.php',  // Ruta a tu archivo PHP que actualizar芍 el estatus
                    method: 'POST',
                    data: { id: id, estatus: status },
                    success: function(response) {
                        if (response.status === 'success') {
                          Swal.fire({
                            title: 'Estatus actualizado',
                            text: '', // Puedes agregar un texto aquí si lo deseas
                            icon: 'success',
                            confirmButtonText: 'OK', // Texto del botón de confirmación
                            allowOutsideClick: false, // Impide cerrar el modal haciendo clic fuera de él (opcional)
                              confirmButtonColor: '#008000' // Código de color verde (puedes usar hexadecimal, rgb, etc.)

                        }).then(() => {
                            window.location.reload();
                        });
                                                } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },error: function(jqXHR, textStatus, errorThrown) {
            console.error(textStatus, errorThrown); // Imprime el error
            Swal.fire('Error', 'Error al actualizar el estatus', 'error');
        }
                });
            }
    </script>
</body>
</html>
