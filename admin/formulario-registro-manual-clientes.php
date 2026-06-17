<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

$queryVendedores = "SELECT id, nombre FROM usuarios WHERE tipoUsu = 1";
$resultVendedores = mysqli_query($conn, $queryVendedores);

// Crear un array para almacenar los vendedores
$vendedores = array();

// Verificar si la consulta devuelve resultados
if ($resultVendedores) {
    // Recorrer los resultados y guardarlos en el array
    while ($row = mysqli_fetch_assoc($resultVendedores)) {
        $vendedores[] = $row;  // Agregar cada fila al array
    }
} else {
    echo "Error en la consulta: " . mysqli_error($conn);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario de Registro</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        body {
            padding: 20px;
        }
        #next-month:hover{
            color:white;
        }
        #prev-month:hover{
            color:white;
        }
        
.calendar-container {
    display: flex;
    width: 800px; /* Ajusta el ancho según tus necesidades */
    margin: 20px auto;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    border-radius: 5px;
    overflow: hidden; /* Para que el borde redondeado se aplique correctamente */
}

.left-panel {
    width: 60%; /* Ajusta la proporción según tus necesidades */
    background-color: #fff;
    padding: 20px;
}

.right-panel {
    width: 40%; /* Ajusta la proporción según tus necesidades */
    background-color: #f9f9f9;
    padding: 20px;
    border-left: 1px solid #eee;
}

/* Encabezado del calendario */
.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.calendar-header h2 {
    font-size: 24px;
    font-weight: 600;
    color: #333;
}

.calendar-header button {
    background-color: transparent;
    border: none;
    font-size: 16px;
    cursor: pointer;
    color: #555;
    transition: color 0.3s ease;
}

.calendar-header button:hover {
    color: #333;
}

/* Tabla del calendario */
table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    text-align: center;
    border: 1px solid #eee;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

th {
    font-weight: 600;
    color: #555;
}

td:hover {
    background-color: #f0f0f0;
}

/* Día actual */
td.current-day {
    background-color: #e0f2f7;
   
}

td.selected{
    background-color: #edd896;
   
}

/* Contenedor de horarios */
#schedule-container {
    padding: 20px;
}

#schedule-container h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
}

#schedule-list {
    list-style: none;
    padding: 0;
}

#schedule-list li {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

/* Estilos para días deshabilitados */
td.disabled {
    color: #ccc;
    cursor: default;
    pointer-events: none;
}

/* Estilos para horarios */
#schedule-list li {
    padding: 10px;
    border-bottom: 1px solid #eee;
    display: flex; /* Para alinear hora e icono */
    align-items: center; /* Alinear verticalmente */
    gap: 10px; /* Espacio entre hora e icono */
}

#schedule-list li span { /* Estilo para la hora */
    font-size: 16px;
    color: #555;
}

/* Icono para los horarios (usando Font Awesome) */
#schedule-list li::before {
    font-family: "Font Awesome 5 Free";
    content: "\f017"; /* Icono de reloj */
    font-weight: 900; /* Para que se vea el icono */
}

/* Opcional: Hover effect en los horarios */
#schedule-list li:hover {
    background-color: #f0f0f0;
    cursor: pointer;
}

/* Estilos para horarios seleccionados */
#schedule-list li.selected {
    background-color: #e0f2f7; /* Color de fondo claro */
    color: #007bff; /* Color de texto azul */
    font-weight: bold; /* Texto en negrita (opcional) */
}
   


 td.blocked {
      cursor: default;
    pointer-events: none;
            background-color: #bbbbbb !important;
            color: white;
        }
    </style>
</head>
<body>
   <div class="container py-2">
    <h3 class="mb-4">Formulario de registro manual</h3>

    <div class="card">
        <div class="card-body">
            <form id="weddingForm">
                <div class="mb-3">
                    <label for="names" class="form-label">Your names</label>
                    <input type="text" name="names" class="form-control" placeholder="Your names" required>
                </div>
                
                <div class="mb-3">
                    <label for="email_address" class="form-label">Email address</label>
                    <input type="email_address" name="email_address" class="form-control" placeholder="Email address" required>
                </div>
                <div class="mb-3">
                    <label for="wedding_date" class="form-label">Wedding Date</label>
                    <input type="date" class="form-control" id="wedding_date" name="wedding_date" onfocus="(this.type='date')" onblur="(this.type='text')" required>
                </div>
                <div class="mb-3">
                    <label for="country_code" class="form-label">Country Code</label>

            <select id="country_code" name="country_code" class="form-select" required>
            <option value="" disabled selected>Choose your country code</option>
          </select>
                </div>
                <div class="mb-3">
                    <label for="telephone" class="form-label">Telephone</label>
                    <input type="tel" name="telephone" class="form-control" placeholder="Telephone" required>
                </div>
                
                   <div class="mb-3">
      
    </div>

                <div class="text-center mt-3">
                    <button type="submit" class="btn btn-primary">Next</button>
                </div>
            </form>
        </div>
    </div>
</div>


    <?php include 'footer.php'; ?>
        <script src="admin/assets/extensions/@fortawesome/fontawesome-free/js/all.js" data-auto-replace-svg="nest"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

 <script>

   let vendedores = <?php echo json_encode($vendedores); ?>;
   console.log("vendedores ",vendedores)


$(document).ready(function() {
    fetch('JS/countries_codes.json')
  .then(response => response.json())
  .then(data => {
      console.log(data)
      let countries = data.countries;
     const selectElement = document.getElementById('country_code');

    // // Ordenar los países alfabéticamente por nombre
    countries.sort((a, b) => a.name.localeCompare(b.name));

    // // Variable para asegurarse de que solo agregamos +1 una vez
    // let addedUS = false;
countries.forEach(country => {
     const option = document.createElement('option');
          option.value = country.code;
          option.textContent = `${country.code} (${country.name})`;
          selectElement.appendChild(option);
})
   
  })
  .catch(error => {
    console.error('Error al obtener los datos de los países:', error);
  });

    // Capturamos el evento submit del formulario
   $(document).ready(function() {
    console.log("jQuery cargado correctamente");

    // Capturamos el evento submit del formulario
    $('#weddingForm').on('submit', function(event) {
        event.preventDefault(); // Evita que el formulario se envíe de la manera tradicional

        // Crear el objeto con los datos del formulario
        var formData = {
            names: $('input[name="names"]').val(),
            email_address: $('input[name="email_address"]').val(),
            wedding_date: $('input[name="wedding_date"]').val(),
            country_code: $('select[name="country_code"]').val(),
            telephone: $('input[name="telephone"]').val(),
            id_vendedor_asignado: 20,
            // Campos para identificar registro manual
            form_name: 'reg manual',
            campaign_name: 'reg manual'
        };
console.log("formData ",formData)
        // Hacemos la solicitud AJAX
        $.ajax({
            url: 'guardar_cliente.php', // El archivo PHP que procesará los datos
            type: 'POST', // Usamos POST para enviar los datos
            data: JSON.stringify(formData), // Convertimos el objeto en una cadena JSON
            contentType: 'application/json', // Indicamos que el cuerpo de la solicitud es JSON
            dataType: 'json', // Esperamos una respuesta en formato JSON
            success: function(response) {
                console.log(response)
                // Si la respuesta tiene "success: true", mostramos un Swal de éxito
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: response.message, // El mensaje de éxito
                        confirmButtonText: 'Ok'
                    });
                    $('#weddingForm')[0].reset(); // Limpiar el formulario
                } else {
                    // Si la respuesta tiene "success: false", mostramos un Swal de error
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message, // El mensaje de error
                        confirmButtonText: 'Intentar de nuevo'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.log("xhr ",xhr)
                                console.log("status ",status)
                                console.log("error ",error)

                // Si ocurre un error en la solicitud AJAX
                Swal.fire({
                    icon: 'error',
                    title: 'Error en la solicitud',
                    text: 'Hubo un problema al enviar los datos. Intenta de nuevo.',
                    confirmButtonText: 'Intentar de nuevo'
                });
            }
        });
    });
});

});


</script>

</body>
</html>


    