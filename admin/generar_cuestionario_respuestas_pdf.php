<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Mexico/General');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['login']) || $_SESSION['login'] === false) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

include 'conn.php';

$idRes = isset($_GET['idres']) ? (int) $_GET['idres'] : 0;

if ($idRes <= 0) {
    http_response_code(400);
    echo 'ID de respuesta inválido';
    exit;
}

$stmt = $conn->prepare('SELECT respuestas, id_cuestionario, estatus FROM respuestas_cuestionario WHERE id = ?');
$stmt->bind_param('i', $idRes);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo 'Respuesta no encontrada';
    $stmt->close();
    $conn->close();
    exit;
}

$rowRespuesta = $result->fetch_assoc();
$stmt->close();

if ((string) ($rowRespuesta['estatus'] ?? '') !== '2') {
    http_response_code(400);
    echo 'El cuestionario aún no ha sido respondido';
    $conn->close();
    exit;
}

$idCuestionario = (int) ($rowRespuesta['id_cuestionario'] ?? 0);
$respuestasGuardadas = json_decode($rowRespuesta['respuestas'] ?? '[]', true);

if (!is_array($respuestasGuardadas)) {
    $respuestasGuardadas = [];
}

$stmt = $conn->prepare('SELECT titulo, introduccion, preguntas FROM cuestionario WHERE id = ?');
$stmt->bind_param('i', $idCuestionario);
$stmt->execute();
$resultCuestionario = $stmt->get_result();

if ($resultCuestionario->num_rows === 0) {
    http_response_code(404);
    echo 'Cuestionario no encontrado';
    $stmt->close();
    $conn->close();
    exit;
}

$row = $resultCuestionario->fetch_assoc();
$stmt->close();
$conn->close();

$titulo = $row['titulo'] ?? '';
$introduccion = $row['introduccion'] ?? '';
$preguntas = json_decode($row['preguntas'] ?? '[]', true);

if (!is_array($preguntas)) {
    $preguntas = [];
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$cuestionarioData = [
    'titulo' => $titulo,
    'introduccion' => $introduccion,
    'preguntas' => $preguntas,
];
$autoDownload = isset($_GET['download']) && $_GET['download'] === '1';

$bgImageUrl = 'https://lh6.googleusercontent.com/B4nwqeL6pW_eK6uG1b72m3WNDUnjzb0HLEMWb_aGjGLQSNCh1S8ge8X5mOv8wlmr29KGrBDAE5uzGemAAzO4dTOUxjzoLAzk6g3brbMcLt9s9RrPbTrE3ZXYzfF6GXiiNFBXuVal5UM=w1920';
$bgImageDataUri = $bgImageUrl;
$bgImageBytes = false;

if (ini_get('allow_url_fopen')) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'follow_location' => 1,
            'user_agent' => 'Mozilla/5.0 (PDF Generator)',
        ],
        'https' => [
            'timeout' => 15,
            'follow_location' => 1,
            'user_agent' => 'Mozilla/5.0 (PDF Generator)',
        ],
    ]);
    $bgImageBytes = @file_get_contents($bgImageUrl, false, $ctx);
}

if (($bgImageBytes === false || $bgImageBytes === '') && function_exists('curl_init')) {
    $ch = curl_init($bgImageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (PDF Generator)',
    ]);
    $curlResult = curl_exec($ch);
    if ($curlResult !== false && $curlResult !== '') {
        $bgImageBytes = $curlResult;
    }
    curl_close($ch);
}

if ($bgImageBytes !== false && $bgImageBytes !== '') {
    $mime = 'image/jpeg';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_buffer($finfo, $bgImageBytes);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
            finfo_close($finfo);
        }
    }
    $bgImageDataUri = 'data:' . $mime . ';base64,' . base64_encode($bgImageBytes);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($titulo) ?> - Respuestas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<style>
html,
body {
    font-family: 'Cormorant Garamond', serif;
    background-color: #ebebeb;
    color: white;
    margin: 0;
}
* {
    box-sizing: border-box;
}
.bg-image{
    height: 160px;
    overflow: hidden;
    padding: 0 !important;
    position: relative;
}
.bg-image img.bg-image-photo {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}
#cuestionario{
    margin: 10px 0 ;
}
#contenedor-preguntas {
    color: black;
}
.page-heading,
#cuestionario-pdf-root {
    padding: 8px 24px 16px;
    width: 794px;
    max-width: 794px;
    margin: 0 auto;
    background-color: #ebebeb;
}
#cuestionario,
#contenedor-preguntas,
.bloque-pregunta,
.encuesta-header,
#encuesta {
    width: 100%;
}
@media (max-width: 1260px) {
    .page-heading,
    #cuestionario-pdf-root {
        padding: 8px 24px 16px;
    }
}
@media (max-width: 768px) {
    .page-heading,
    #cuestionario-pdf-root {
        padding: 8px 24px 16px;
    }
    #contenedor-preguntas {
        font-size: 17px;
    }
}
.pdf-page {
    width: 100%;
    box-sizing: border-box;
    background-color: #ebebeb;
    overflow: hidden;
}
.respuesta-imagen {
    max-height: 280px;
    object-fit: contain;
}
.respuesta-texto {
    display: block;
    width: 100%;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    padding: 0.375rem 0.75rem;
    color: #212529;
    font-size: 1rem;
    line-height: 1.5;
    white-space: pre-wrap;
    word-wrap: break-word;
    overflow-wrap: break-word;
    word-break: break-word;
    min-height: 38px;
}
</style>
<body>
    <div class="page-heading" id="cuestionario-pdf-root">
        <section id="cuestionario">
            <div class="bloque-pregunta bg-image card mb-4 ">
                <img src="<?= h($bgImageDataUri) ?>" alt="" class="bg-image-photo" crossorigin="anonymous">
            </div>
            <div id="contenedor-preguntas" class="mb-5"></div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
$(document).ready(function() {
    let cuestionarioBD = <?php echo json_encode($cuestionarioData); ?>;
    let respuestasBD = <?php echo json_encode($respuestasGuardadas); ?>;
    const autoDownload = <?php echo $autoDownload ? 'true' : 'false'; ?>;
    const nombreArchivo = 'cuestionario_respuestas_' + (cuestionarioBD.titulo || 'cuestionario')
        .replace(/[^a-z0-9áéíóúñü\s-]/gi, '')
        .trim()
        .replace(/\s+/g, '_') + '_<?= (int) $idRes ?>.pdf';

    const mapaRespuestas = {};
    respuestasBD.forEach((item) => {
        mapaRespuestas[item.pregunta] = item.respuesta || null;
    });

    function escHtml(texto) {
        if (texto === null || texto === undefined) return '';
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr(texto) {
        return escHtml(texto).replace(/'/g, '&#39;');
    }

    function obtenerSeleccionados(respuesta) {
        if (!respuesta) return [];
        const valor = respuesta.res;
        if (respuesta.tipo === 'array' || Array.isArray(valor)) {
            return Array.isArray(valor) ? valor : [];
        }
        if (valor === null || valor === undefined || valor === '') return [];
        return [valor];
    }

    function renderCampoRespuesta(pregunta, indice, respuesta) {
        if (pregunta.tipo === 'file') {
            if (!respuesta || !respuesta.res) {
                return '<p class="mb-0 text-muted">Sin archivo</p>';
            }

            const ruta = String(respuesta.res).replace(/^\/+/, '');
            const tipo = String(respuesta.tipo || '').toLowerCase();

            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(tipo)) {
                return `<img src="${escAttr(ruta)}" class="img-fluid respuesta-imagen" alt="Respuesta">`;
            }

            return `<p class="mb-0">${escHtml(ruta)}</p>`;
        }

        if (pregunta.tipo === 'radio' || pregunta.tipo === 'checkbox') {
            const seleccionados = obtenerSeleccionados(respuesta);
            const opciones = Array.isArray(pregunta.opciones) ? pregunta.opciones : seleccionados;

            return opciones.map((opcion, opcionIndex) => {
                const checked = seleccionados.includes(opcion) ? 'checked' : '';
                return `
                    <div class="form-check">
                        <input type="${pregunta.tipo}" class="form-check-input"
                               name="pregunta${indice}${pregunta.tipo === 'checkbox' ? '[]' : ''}"
                               id="opcion${indice}_${opcionIndex}"
                               value="${escAttr(opcion)}" ${checked} disabled>
                        <label class="form-check-label" for="opcion${indice}_${opcionIndex}">
                            ${escHtml(opcion)}
                        </label>
                    </div>
                `;
            }).join('');
        }

        const valor = respuesta && respuesta.res !== undefined && respuesta.res !== null
            ? respuesta.res
            : '';

        return `<div class="respuesta-texto">${escHtml(valor)}</div>`;
    }

    if (cuestionarioBD.preguntas && cuestionarioBD.preguntas.length > 0) {
        generarCuestionario(cuestionarioBD);
    }

    function generarCuestionario(cuestionario) {
        $('#contenedor-preguntas').empty();

        let encuestaHTML = `
            <div class="encuesta-header card mb-4">
                <div class="card-body">
                    <h2 class="text-center">${escHtml(cuestionario.titulo)}</h2>
                    <p class="text-center">${escHtml(cuestionario.introduccion)}</p>
                </div>
            </div>
            <form id="encuesta" class="mt-4">
        `;

        let i = 0;
        while (i < cuestionario.preguntas.length) {
            let pregunta = cuestionario.preguntas[i];

            if (pregunta.identifier !== undefined) {
                encuestaHTML += `<div class="bloque-pregunta card mb-4"><div class="m-3">`;
                let identifier = pregunta.identifier;
                let j = i;

                while (j < cuestionario.preguntas.length &&
                       cuestionario.preguntas[j].identifier === identifier) {
                    let preguntaGrupo = cuestionario.preguntas[j];

                    if (preguntaGrupo.tipo === 'titulo') {
                        encuestaHTML += `<h3 class="text-center">${escHtml(preguntaGrupo.pregunta)}</h3>`;
                    } else if (preguntaGrupo.tipo === 'introduccion') {
                        encuestaHTML += `<p class="text-center">${escHtml(preguntaGrupo.pregunta)}</p>`;
                    }
                    j++;
                }

                encuestaHTML += `</div></div>`;
                i = j;
            } else {
                encuestaHTML += `<div class="bloque-pregunta card mb-4"><div class="m-3">`;

                if (pregunta.tipo === 'titulo') {
                    encuestaHTML += `<h3 class="text-center">${escHtml(pregunta.pregunta)}</h3>`;
                } else if (pregunta.tipo === 'introduccion') {
                    encuestaHTML += `<p class="text-center">${escHtml(pregunta.pregunta)}</p>`;
                } else {
                    const respuesta = mapaRespuestas[pregunta.pregunta];
                    encuestaHTML += `
                        <p class="fw-bold q-title">${escHtml(pregunta.pregunta)}${pregunta.requerida ? '<span class="text-danger">*</span>' : ''}</p>
                    `;
                    encuestaHTML += renderCampoRespuesta(pregunta, i, respuesta);
                }

                encuestaHTML += `</div></div>`;
                i++;
            }
        }

        encuestaHTML += `</form>`;
        $('#contenedor-preguntas').html(encuestaHTML);
    }

    function esperarRecursos() {
        const promesasImagenes = Array.from(document.images).map((img) => {
            if (img.complete) return Promise.resolve();
            return new Promise((resolve) => {
                img.onload = resolve;
                img.onerror = resolve;
            });
        });

        const promesaFuentes = document.fonts && document.fonts.ready
            ? document.fonts.ready
            : Promise.resolve();

        return Promise.all([promesaFuentes, ...promesasImagenes]);
    }

    function paginarParaPdf(rootEl) {
        const PAGE_HEIGHT = 1122;
        const PADDING_X = 24;
        const section = rootEl.querySelector('#cuestionario');
        if (!section) return;

        rootEl.style.padding = '0';

        const bloques = Array.from(rootEl.querySelectorAll('.encuesta-header, .bloque-pregunta'));
        bloques.forEach((bloque) => bloque.remove());

        const contenedorPreguntas = section.querySelector('#contenedor-preguntas');
        if (contenedorPreguntas) {
            contenedorPreguntas.remove();
        }

        let paginaActual = null;

        function cerrarPagina(pagina) {
            if (!pagina) return;
            const espacioLibre = PAGE_HEIGHT - pagina.scrollHeight;
            if (espacioLibre > 0) {
                const relleno = document.createElement('div');
                relleno.setAttribute('aria-hidden', 'true');
                relleno.style.height = espacioLibre + 'px';
                pagina.appendChild(relleno);
            }
            pagina.style.height = PAGE_HEIGHT + 'px';
        }

        function crearPagina() {
            if (paginaActual && paginaActual.children.length > 0) {
                cerrarPagina(paginaActual);
            }
            paginaActual = document.createElement('div');
            paginaActual.className = 'pdf-page';
            paginaActual.style.padding = '8px ' + PADDING_X + 'px 0';
            section.appendChild(paginaActual);
            return paginaActual;
        }

        bloques.forEach((bloque) => {
            if (!paginaActual) {
                crearPagina();
            }

            paginaActual.appendChild(bloque);

            if (paginaActual.scrollHeight > PAGE_HEIGHT) {
                paginaActual.removeChild(bloque);
                crearPagina();
                paginaActual.appendChild(bloque);
            }
        });

        if (paginaActual) {
            cerrarPagina(paginaActual);
        }
    }

    if (autoDownload) {
        esperarRecursos().then(function() {
            return new Promise((resolve) => setTimeout(resolve, 800));
        }).then(function() {
            const contenido = document.getElementById('cuestionario-pdf-root');
            paginarParaPdf(contenido);
            return new Promise((resolve) => setTimeout(resolve, 100));
        }).then(function() {
            const contenido = document.getElementById('cuestionario-pdf-root');

            html2pdf().set({
                margin: [0, 0, 0, 0],
                filename: nombreArchivo,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    width: 794,
                    windowWidth: 794,
                    backgroundColor: '#ebebeb',
                    useCORS: true,
                    allowTaint: true,
                    logging: false
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            }).from(contenido).save()
                .then(function() {
                    notificarPadrePdf('cuestionario-respuestas-pdf-listo');
                })
                .catch(function() {
                    notificarPadrePdf('cuestionario-respuestas-pdf-error');
                });
        });
    }

    function notificarPadrePdf(tipo) {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: tipo }, '*');
        }
    }
});
    </script>
</body>
</html>
