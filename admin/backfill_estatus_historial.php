<?php
/**
 * backfill_estatus_historial.php
 *
 * Reconstruye un HISTÓRICO SEMILLA APROXIMADO de los cambios de estatus de las citas
 * (`calendario`) usando las fechas que sí tenemos en la base de datos. Todos los
 * registros generados se marcan con `es_estimado = 1` para distinguirlos de los
 * cambios reales capturados en tiempo real.
 *
 * Precisión por tipo de evento:
 *   - agendado (0): fecha = calendario.fecha_registro, o si es nula, contact_form.created_time
 *                   / submission_date, o como último recurso la fecha de la cita.  (ALTA/MEDIA)
 *   - atendido/fantasma/muerto (estatus actual 1/2/3): fecha = fecha de la cita (proxy).  (MEDIA/BAJA)
 *       OJO: solo se reconstruye el estatus ACTUAL. Si un lead fue atendido y luego pasó a
 *       muerto, el "atendido" intermedio NO es recuperable (ese dato nunca se guardó).
 *   - cliente (4): fecha = contact_form.fecha_cambio_cliente.  (ALTA)
 *
 * Idempotente: omite las citas que ya tengan registros estimados, de modo que volver a
 * ejecutarlo no duplica datos.
 *
 * Uso:
 *   - CLI:       php backfill_estatus_historial.php
 *   - Navegador: backfill_estatus_historial.php?confirm=1   (evita ejecuciones accidentales)
 */

require __DIR__ . '/conn.php';
require_once __DIR__ . '/calendario_estatus_historial_helper.php';

$esCli = (php_sapi_name() === 'cli');
if (!$esCli) {
    header('Content-Type: text/plain; charset=UTF-8');
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== '1') {
        echo "Este script reconstruye el historial estimado de estatus.\n";
        echo "Agrega ?confirm=1 a la URL para ejecutarlo.\n";
        exit;
    }
}

/**
 * Normaliza una fecha/datetime a 'Y-m-d H:i:s' o null si es inválida.
 */
function bfNormalizeDateTime($value)
{
    if ($value === null) {
        return null;
    }
    $value = trim((string) $value);
    if ($value === '' || strpos($value, '0000-00-00') === 0) {
        return null;
    }
    // Soportar ISO 8601 (con 'T') y datetime normal
    $candidate = (strpos($value, 'T') !== false) ? substr($value, 0, 19) : $value;
    $ts = strtotime($candidate);
    if ($ts === false || $ts <= 0) {
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

ensureCalendarioEstatusHistorialTable($conn);

// Citas que ya tienen historial estimado -> se omiten para no duplicar
$yaProcesadas = [];
$resPrev = $conn->query("SELECT DISTINCT id_calendario FROM calendario_estatus_historial WHERE es_estimado = 1");
if ($resPrev) {
    while ($r = $resPrev->fetch_assoc()) {
        $yaProcesadas[intval($r['id_calendario'])] = true;
    }
}

$sql = "SELECT c.id, c.idclie, c.fecha AS cita_fecha, c.fecha_registro, c.estatus,
               cf.created_time, cf.submission_date, cf.cliente, cf.fecha_cambio_cliente
        FROM calendario c
        LEFT JOIN contact_form cf ON cf.id = c.idclie";
$res = $conn->query($sql);

if (!$res) {
    echo "Error al consultar calendario: " . $conn->error . "\n";
    exit(1);
}

$citasProcesadas = 0;
$citasOmitidas   = 0;
$eventosAgendado = 0;
$eventosAtendido = 0;
$eventosFantasma = 0;
$eventosMuerto   = 0;
$eventosCliente  = 0;

while ($row = $res->fetch_assoc()) {
    $idCal = intval($row['id']);
    if ($idCal <= 0) {
        continue;
    }
    if (isset($yaProcesadas[$idCal])) {
        $citasOmitidas++;
        continue;
    }

    $estatusActual = intval($row['estatus']);

    // Fecha base de "agendado"
    $fechaAgendado = bfNormalizeDateTime($row['fecha_registro']);
    if ($fechaAgendado === null) {
        $fechaAgendado = bfNormalizeDateTime($row['created_time']);
    }
    if ($fechaAgendado === null) {
        $fechaAgendado = bfNormalizeDateTime($row['submission_date']);
    }
    if ($fechaAgendado === null) {
        $fechaAgendado = bfNormalizeDateTime($row['cita_fecha']);
    }

    // Fecha proxy del estatus actual (atendido/fantasma/muerto): la fecha de la cita
    $fechaCita = bfNormalizeDateTime($row['cita_fecha']);
    $fechaEstatusActual = $fechaCita !== null ? $fechaCita : $fechaAgendado;

    // 1) Evento "agendado" (0)
    if ($fechaAgendado !== null) {
        if (registrarCambioEstatusCalendario($conn, $idCal, 0, [
            'estatus_anterior' => null,
            'fecha_cambio'     => $fechaAgendado,
            'origen'           => 'backfill',
            'observaciones'    => 'Agendado (reconstruido)',
            'es_estimado'      => true,
            'skip_lookup_anterior' => true,
        ])) {
            $eventosAgendado++;
        }
    }

    // 2) Evento del estatus actual si es 1/2/3
    if (in_array($estatusActual, [1, 2, 3], true) && $fechaEstatusActual !== null) {
        $etiqueta = ($estatusActual === 1) ? 'Atendido' : (($estatusActual === 2) ? 'Fantasma' : 'Muerto');
        if (registrarCambioEstatusCalendario($conn, $idCal, $estatusActual, [
            'estatus_anterior' => 0,
            'fecha_cambio'     => $fechaEstatusActual,
            'origen'           => 'backfill',
            'observaciones'    => $etiqueta . ' (reconstruido a partir del estatus actual)',
            'es_estimado'      => true,
            'skip_lookup_anterior' => true,
        ])) {
            if ($estatusActual === 1) $eventosAtendido++;
            elseif ($estatusActual === 2) $eventosFantasma++;
            else $eventosMuerto++;
        }
    }

    // 3) Evento "cliente" (4) si el contacto está cerrado
    if (intval($row['cliente']) === 1) {
        $fechaCliente = bfNormalizeDateTime($row['fecha_cambio_cliente']);
        if ($fechaCliente === null) {
            $fechaCliente = $fechaEstatusActual !== null ? $fechaEstatusActual : $fechaAgendado;
        }
        if ($fechaCliente !== null) {
            if (registrarCambioEstatusCalendario($conn, $idCal, 4, [
                'estatus_anterior' => $estatusActual,
                'fecha_cambio'     => $fechaCliente,
                'origen'           => 'backfill',
                'observaciones'    => 'Cliente (reconstruido)',
                'es_estimado'      => true,
                'skip_lookup_anterior' => true,
            ])) {
                $eventosCliente++;
            }
        }
    }

    $citasProcesadas++;
}

echo "Backfill de historial de estatus completado.\n";
echo "------------------------------------------------\n";
echo "Citas procesadas:        $citasProcesadas\n";
echo "Citas omitidas (previas): $citasOmitidas\n";
echo "Eventos agendado (0):    $eventosAgendado\n";
echo "Eventos atendido (1):    $eventosAtendido\n";
echo "Eventos fantasma (2):    $eventosFantasma\n";
echo "Eventos muerto (3):      $eventosMuerto\n";
echo "Eventos cliente (4):     $eventosCliente\n";
echo "------------------------------------------------\n";
echo "NOTA: los eventos 'atendido' solo reflejan a quienes HOY siguen en atendido.\n";
echo "Los que pasaron de atendido a muerto/fantasma no son recuperables hacia atras.\n";

$conn->close();
