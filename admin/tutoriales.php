<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos

$tipoUsuario = $_SESSION['tus'];
$idUsu = $_SESSION['uid'];

// Lista de videos en formato MP4 (cambia los enlaces a los archivos MP4 reales)
$videos = [
    ['id'=>1, 'nombre' => 'Administrar usuarios', 'url' => 'assets/videos/video2.mp4'],
    ['id'=>2,'nombre' => 'Asignar/Actualizar horarios a vendedores.', 'url' => 'assets/videos/video5.mp4'],
    ['id'=>3,'nombre' => 'Configurar plantillas de correos', 'url' => 'assets/videos/video3.mp4'],
    ['id'=>4,'nombre' => 'Administrar citas', 'url' => 'assets/videos/video4.mp4'],
               
    ['id'=>5,'nombre' => 'Agregar cliente', 'url' => 'assets/videos/video6.mp4'],

    ['id'=>6,'nombre' => 'Agregar datos transferencia', 'url' => 'assets/videos/video7.mp4'],

    ['id'=>7,'nombre' => 'Agregar pagos a clientes', 'url' => 'assets/videos/video8.mp4'],

    ['id'=>8,'nombre' => 'Crear cuestionarios', 'url' => 'assets/videos/video9.mp4'],

    ['id'=>9,'nombre' => 'Agregar galería a clientes', 'url' => 'assets/videos/video10.mp4'],

    ['id'=>10,'nombre' => 'Agregar contratos y cuestionario a clientes', 'url' => 'assets/videos/video11.mp4'],
    
    ['id'=>11,'nombre' => 'Clonar cuestionarios', 'url' => 'assets/videos/video12.mp4'],
    
    ['id'=>12,'nombre' => 'NEXTSTEPS', 'url' => 'assets/videos/video13.mp4'],
        ['id'=>13,'nombre' => 'Cambio de asesor', 'url' => 'assets/videos/video14.mp4'],

    
];



// Filtra los videos dependiendo del tipo de usuario
$videosMostrar = [];
if ($tipoUsuario == 1) {
    // Si el tipo de usuario es 1, mostrar solo los videos con ID 1 y 3
    $videosMostrar = array_filter($videos, function($video) {
        return in_array($video['id'], [4]);
    });
} else {
    // Si el tipo de usuario es 0, mostrar todos los videos
    $videosMostrar = $videos;
}

// Reindexamos el array para que los índices sean consecutivos
$videosMostrar = array_values($videosMostrar);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutoriales</title>
    
    <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-2">
        <h3 class="mb-4">Tutoriales</h3>

        <!-- Tabla de videos -->
        <div class="card">
           <div class="card-body">
                <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Video</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($videosMostrar as $video): ?>
                <tr>
                    <td><?php echo htmlspecialchars($video['nombre']); ?></td>
                    <td>
                        <!-- Miniatura del video -->
                        <a href="#" data-bs-toggle="modal" data-bs-target="#videoModal" data-url="<?php echo htmlspecialchars($video['url']); ?>" data-titulo="<?php echo htmlspecialchars($video['nombre']); ?>">
                            Ver video
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
           </div>
        </div>

    </div>

    <!-- Modal para mostrar el video -->
    <div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="videoModalLabel">Ver Video</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <video id="videoPlayer" class="w-100" controls>
                        <source src="" id="videoSource" type="video/mp4">
                        Tu navegador no soporta la etiqueta de video.
                    </video>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>

    <!-- Bootstrap JS y Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
        // Cuando se haga clic en una miniatura, establecer la fuente del video en el modal
       // Cuando se haga clic en una miniatura, establecer la fuente del video y el título en el modal
$('#videoModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget); // Elemento que activó el modal
    var videoUrl = button.data('url'); // Obtener el URL del video
    var videoTitulo = button.data('titulo'); // Obtener el título del video

    var modal = $(this);
    var videoPlayer = modal.find('#videoPlayer')[0]; // Obtén el elemento del video

    modal.find('#videoSource').attr('src', videoUrl);
    modal.find('.modal-title').text(videoTitulo); // Establecer el título del modal
    videoPlayer.load(); // Cargar el video
    videoPlayer.play(); // Reproducir el video automáticamente
});

// Detener el video cuando se cierre el modal
$('#videoModal').on('hidden.bs.modal', function () {
    var videoPlayer = $(this).find('#videoPlayer')[0]; // Obtén el elemento del video
    videoPlayer.pause(); // Detener el video
    videoPlayer.currentTime = 0; // Reiniciar el video al principio
});

    </script>
</body>
</html>
