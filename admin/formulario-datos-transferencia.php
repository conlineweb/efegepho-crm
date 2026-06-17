<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$datos_cuentas = [];

for ($i = 1; $i <= 4; $i++) {
    $sql = "SELECT * FROM datos_transferencia WHERE id = $i";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $datos_cuentas[$i] = $result->fetch_assoc();
    } else {
        $datos_cuentas[$i] = [
            'clabe' => '',
            'tarjeta' => '',
            'nombre_banco' => '',
            'nombre_titular' => ''
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulario Datos Transferencia</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        body {
            padding: 20px;
        }
 
    </style>
</head>
<body>
        <div class="container py-2">
            <h3 class="mb-4">Formulario de Datos Para Transferencia</h3>

<ul class="nav nav-tabs" id="myTab" role="tablist">
    <?php for ($i = 1; $i <= 4; $i++): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $i == 1 ? 'active' : '' ?>" id="tab<?= $i ?>-tab" data-bs-toggle="tab" data-bs-target="#tab<?= $i ?>" type="button" role="tab">
                Cuenta <?= $i ?>
            </button>
        </li>
    <?php endfor; ?>
</ul>

<!-- Tab content -->
<div class="tab-content pt-3" id="myTabContent">
    <?php for ($i = 1; $i <= 4; $i++): ?>
        <?php $cuenta = $datos_cuentas[$i]; ?>
        <div class="tab-pane fade <?= $i == 1 ? 'show active' : '' ?>" id="tab<?= $i ?>" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <form id="dataBankForm<?= $i ?>">
                        <input type="hidden" name="cuenta" value="<?= $i ?>">
                        <div class="mb-3">
                            <label class="form-label">CLABE Interbancaria</label>
                            <input type="text" name="clabe" class="form-control" placeholder="CLABE" value="<?= htmlspecialchars($cuenta['clabe']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No. Cuenta</label>
                            <input type="text" name="tarjeta" class="form-control" placeholder="NO.Cuenta" value="<?= htmlspecialchars($cuenta['tarjeta']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre del banco</label>
                            <input type="text" name="nombre_banco" class="form-control" placeholder="Banco" value="<?= htmlspecialchars($cuenta['nombre_banco']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre del titular</label>
                            <input type="text" class="form-control" name="nombre_titular" placeholder="Titular" value="<?= htmlspecialchars($cuenta['nombre_titular']) ?>" required>
                        </div>

                        <div class="text-center mt-3">
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endfor; ?>
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
$(document).ready(function() {
            <?php for ($i = 1; $i <= 4; $i++): ?>
    $("#dataBankForm<?= $i ?>").on("submit", function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.ajax({
            url: 'guardar_datos_transferencia.php',
            type: 'POST',
            data: formData + '&cuenta=<?= $i ?>', // Puedes usar esto para identificar cada cuenta
            dataType: 'json',
            success: function(response) {
                Swal.fire({
                    icon: response.status == 'success' ? 'success' : 'error',
                    title: response.status == 'success' ? '¡Éxito!' : 'Error',
                    text: response.message
                });
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Hubo un error al procesar los datos.'
                });
            }
        });
    });
    <?php endfor; ?>
        });
</script>

</body>
</html>