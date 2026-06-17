<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos


$query = "SELECT id, numero FROM whatsapp"; // Incluye el id para cada cuenta

$result = $conn->query($query);

// Guardamos los números en un array
$numerosWhatsapp = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $numerosWhatsapp[] = $row; // Almacena id y número
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario de Registro</title>
    <style>
        body {
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container py-2">
        <h3 class="mb-4">Formulario de Registro</h3>

        <div class="card">
            <div class="card-body">
                <form id="registroForm">
                    <div class="form-group">
                        <label for="nombre">Nombre Completo:</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label for="correo">Correo Electrónico:</label>
                        <input type="email" class="form-control" id="correo" name="correo" required>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono:</label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" required>
                    </div>
                    <div class="form-group">
                        <label for="fechaRegistro">Fecha de Registro de Cliente en el Sistema:</label>
                        <input type="date" class="form-control" id="fechaRegistro" name="fechaRegistro" required>
                    </div>
                    <div class="form-group">
                        <label for="fechaGaleria">Fecha de Registro de Galería:</label>
                        <input type="date" class="form-control" id="fechaGaleria" name="fechaGaleria" required> 
                    </div>
                    <div class="form-group">
                        <label for="fechaVencimientoGaleria">Fecha de Vencimiento de Galería:</label>
                        <input type="date" class="form-control" id="fechaVencimientoGaleria" name="fechaVencimientoGaleria" readonly>
                    </div>

                    <!-- Aquí agregamos el select para la cuenta de WhatsApp -->
                    <div class="form-group">
                        <label for="whatsapp">Seleccionar Cuenta de WhatsApp:</label>
                        <select class="form-control" id="whatsapp" name="whatsapp" required>
                            <option value="">Seleccione una cuenta</option>
                            <?php foreach ($numerosWhatsapp as $whatsapp): ?>
                                <option value="<?= $whatsapp['id'] ?>"><?= $whatsapp['numero'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Registrar</button>
                </form>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
       $(document).ready(function() {
    //tomar la fecha de hoy
    $('#fechaGaleria').val(new Date().toISOString().slice(0, 10));

    // Calculate and set expiry date
    function calculateExpiryDate() {
        let registrationDate = $('#fechaGaleria').val();
        if (registrationDate) {
            // Sumar 6 meses a la fecha de registro de galería
            let expiryDate = moment(registrationDate).add(6, 'months').format('YYYY-MM-DD');
            $('#fechaVencimientoGaleria').val(expiryDate);
        } else {
            $('#fechaVencimientoGaleria').val(''); // Clear if no registration date
        }
    }

    // Initial calculation on page load
    calculateExpiryDate();

    // Recalculate expiry date when registration date changes
    $('#fechaGaleria').change(calculateExpiryDate);

    $('#registroForm').submit(function(event) {
        event.preventDefault();

        // Get form data (you'll need to adapt this based on how you want to send data)
        var formData = $(this).serialize();

        $.ajax({
            type: 'POST', // Or GET, depending on your server-side script
            url: 'guardar_cliente_galeria.php', // Replace with your server-side script URL
            data: formData,
            success: function(response) {
                // Analiza la respuesta JSON que llega del servidor
                var jsonResponse = JSON.parse(response);
                
                if (jsonResponse.status === "success") {
                    // Maneja el éxito
                    Swal.fire('¡Éxito!', 'Registro guardado correctamente', 'success').then(() => {
                        window.location.reload(); // Recarga la página
                    });
                    $('#registroForm')[0].reset(); // Limpia el formulario
                    calculateExpiryDate(); // Vuelve a calcular la fecha de vencimiento
                } else {
                    // Maneja el error
                    Swal.fire('¡Error!', jsonResponse.message, 'error');
                }
            },
            error: function(error) {
                // Handle errors (e.g., display an error message)
                Swal.fire('¡Error!', 'Hubo un problema al guardar el registro', 'error');
                console.error("Error:", error); // Log the error for debugging
            }
        });
    });
});

    </script>

</body>
</html>
