<?php

// index.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';

$query = "SELECT * FROM whatsapp";
$result = $conn->query($query);

// Guardamos los números en un array
$numerosWhatsapp = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $numerosWhatsapp[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuentas de WhatsApp</title>
    <style>
        body {
            padding: 20px;
        }

        .cuenta-container {
            margin-bottom: 10px;
        }

        .cuenta-input {
            width: 70%;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        .eliminar-cuenta {
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="container py-2">
        <h3 class="mb-4">Cuentas WhatsApp</h3>

        <div class="card">
            <div class="card-body">
                <form id="registroForm">
                    <div id="cuentas-container">
                        <?php
                        foreach ($numerosWhatsapp as $index => $numero) {
                            $id = $numero['id'];
                            echo "
                                <div class='cuenta-container' data-from-db='true'>
                                    <label for='cuenta-{$index}'>Cuenta No. " . ($index + 1) . ":</label>
                                    <input type='text' class='form-control cuenta-input' id='cuenta-{$index}' name='cuenta[]' 
                                        value='{$numero['numero']}' data-id='{$id}' data-isUpdated='false' data-isDeleted='false' 
                                        data-isFromDB='true' required>
                                    <button type='button' class='btn btn-danger eliminar-cuenta'>Eliminar</button>
                                </div>
                            ";
                        }
                        ?>
                    </div>

                    <div class="d-flex flex-row justify-content-between mt-4">
                        <button type="button" id="agregarCuenta" class="btn btn-secondary">Agregar Nueva Cuenta</button>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
        $(document).ready(function () {
            let contadorCuentas = <?php echo count($numerosWhatsapp); ?>;
            let originalValues = [];

            // Guardar los valores originales de los inputs en el array originalValues
            $(".cuenta-input").each(function () {
                originalValues.push($(this).val());
            });

            // Agregar nueva cuenta
            $("#agregarCuenta").click(function () {
                $("#cuentas-container").append(`
                    <div class="cuenta-container" data-from-db="false">
                        <label for="cuenta-${contadorCuentas}">Cuenta No. ${contadorCuentas + 1}:</label>
                        <input type="text" class="form-control cuenta-input" id="cuenta-${contadorCuentas}" name="cuenta[]" 
                            data-id="${Math.floor(Math.random() * 10000)}" data-isUpdated="false" data-isDeleted="false" 
                            data-isFromDB="false" required>
                        <button type="button" class="btn btn-danger eliminar-cuenta">Eliminar</button>
                    </div>
                `);
                contadorCuentas++;
            });

            // Delegar el evento para el botón de eliminar
            $("#cuentas-container").on("click", ".eliminar-cuenta", function (e) {
                let cuentaContainer = $(this).parent();
                cuentaContainer.addClass('eliminada'); // Marcar la cuenta como eliminada
                cuentaContainer.hide(); // Ocultar la cuenta de la vista (opcional: usa .remove() si quieres eliminarla del DOM completamente)
            });

            // Enviar el formulario con los cambios
            $("#registroForm").submit(function (event) {
                event.preventDefault();

                let cuentas = [];

                $(".cuenta-input").each(function (index) {
                    let currentValue = $(this).val();
                    let isFromDB = $(this).attr('data-isFromDB'); // <--  Cambio clave aquí
                    let isUpdated = $(this).attr('data-isUpdated');
                    let isDeleted = $(this).attr('data-isDeleted');

                    // Si no es de la base de datos y ha sido eliminada, no la agregamos al array
                    if (isFromDB === "false" && $(this).parent().hasClass('eliminada')) {
                        return; // No agregamos la cuenta eliminada al array
                    }

                    let cuentaObj = {
                        number: currentValue,
                        id: $(this).data('id'),
                        isUpdated: $(this).parent().hasClass('eliminada') ? false : isFromDB === "true" ? currentValue !== originalValues[index] : false,
                        isDeleted: $(this).parent().hasClass('eliminada'),
                        isFromDB: isFromDB === "true" // Conversión explícita a booleano
                    };

                    cuentas.push(cuentaObj);
                });

                // Realizar la solicitud AJAX (descomentado en el código original)
                $.ajax({
                    type: "POST",
                    url: "gestion_whatsapp.php",
                    data: {
                        cuentas: cuentas
                    },
                    dataType: "json",
                    success: function (response) {
                        console.log("response ", response);
                        if (response.estado === "ok") {
                            Swal.fire("Éxito", response.mensaje, "success");
                            // Limpiar solo las cuentas nuevas si es exitoso
                            $("#registroForm")[0].reset();
                            contadorCuentas = <?php echo count($numerosWhatsapp); ?>; // No reiniciar contador
                            // No eliminar el contenido de #cuentas-container
                        } else if (response.estado === "error") {
                            Swal.fire("Error", response.mensaje, "error");
                        }
                    },
                    error: function (error) {
                        console.error("Error en la solicitud AJAX:", error);
                        Swal.fire("Error", "Error en la solicitud AJAX", "error");
                    }
                });
            });
        });
    </script>

</body>

</html>
