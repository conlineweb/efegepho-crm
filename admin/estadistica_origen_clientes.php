<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

$userid = $_SESSION['uid'];

// Obtenemos las fechas de inicio y fin, si están definidas
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Comenzamos la consulta básica
$sql = "SELECT * FROM contact_form WHERE 1";

// Si hay una fecha de inicio, la agregamos a la consulta
if (!empty($start_date)) {
    // Asegurémonos de que solo comparamos las fechas sin la hora
    $sql .= " AND DATE(submission_date) >= '$start_date'";
}

// Si hay una fecha de fin, la agregamos a la consulta
if (!empty($end_date)) {
    // Asegurémonos de que solo comparamos las fechas sin la hora
    $sql .= " AND DATE(submission_date) <= '$end_date'";
}

// Preparamos y ejecutamos la consulta
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

// Inicializamos el contador para cada fuente
$fuentes = [
    'Instagram' => 0,
    'Google Ads' => 0,
    'Website' => 0,
    'Wedding Planner' => 0,
    'Recomendación' => 0
];

// Recorremos los resultados
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Verificamos si el campo 'hear_about_us' tiene un valor numérico
        if (is_numeric($row['hear_about_us']) && $row['hear_about_us'] >= 1 && $row['hear_about_us'] <= 5) {
            // Incrementamos el contador correspondiente
            switch ($row['hear_about_us']) {
                case 1:
                    $fuentes['Instagram']++;
                    break;
                case 2:
                    $fuentes['Google Ads']++;
                    break;
                case 3:
                    $fuentes['Website']++;
                    break;
                case 4:
                    $fuentes['Wedding Planner']++;
                    break;
                case 5:
                    $fuentes['Recomendación']++;
                    break;
            }
        }
    }
} else {
    echo "No se encontraron registros en las fechas seleccionadas.";
}

?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadística Origen Clientes</title>
</head>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Estadística Origen Clientes</h3>
                <br>
            </div>
        </div>
    </div>
    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="container">
                    <!-- Formulario de selección de fechas -->
                   <div class="d-flex flex-row align-items-end mb-3">
    <form method="get" action="" class="d-flex align-items-end">
        <div class="me-2">
            <label for="start_date" class="form-label">Fecha de inicio:</label>
            <input type="date" id="start_date" name="start_date" value="<?= $start_date ?>" class="form-control">
        </div>

        <div class="me-2">
            <label for="end_date" class="form-label">Fecha de fin:</label>
            <input type="date" id="end_date" name="end_date" value="<?= $end_date ?>" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary me-2">Filtrar</button>
    </form>

    <form method="get" action="" style="margin-left: 10px;">
        <button type="submit" name="reset" value="true" class="btn btn-secondary">Mostrar Todo</button>
    </form>
</div>
                    <!-- Tabla de resultados -->
                    <table id="tablaOrigen" class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Fuente</th>
                                <th>Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Los datos se llenarán aquí desde PHP -->
                            <?php foreach ($fuentes as $fuente => $cantidad): ?>
                                <tr>
                                    <td><?= $fuente ?></td>
                                    <td><?= $cantidad ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
include 'footer.php';

// Si se presionó el botón "Mostrar Todo", reiniciamos las fechas
if (isset($_GET['reset'])) {
    // Redirigimos sin los parámetros de fecha
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}
?>

<!-- Incluir librerías de jQuery y DataTables -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

<script>
    // Inicializar DataTables para una mejor visualización
    $(document).ready(function() {
        $('#tablaOrigen').DataTable();
    });
</script>

<!-- Bootstrap JS y Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
