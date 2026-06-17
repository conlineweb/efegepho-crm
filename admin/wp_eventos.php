<?php
include 'menu.php';
include 'conn.php';

function ensureColumnExists($conn, $table, $columnName, $columnDef)
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($columnName);
    $checkSql = "SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safeTable}' AND COLUMN_NAME = '{$safeColumn}'";
    $res = $conn->query($checkSql);
    if ($res) {
        $row = $res->fetch_assoc();
        if (intval($row['c'] ?? 0) === 0) {
            $conn->query("ALTER TABLE `{$table}` ADD COLUMN {$columnDef}");
        }
    }
}

function formatDateTimeCell($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'Sin fecha';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date('d/m/Y H:i', $timestamp);
}

function statusMeta($value)
{
    $raw = trim((string) $value);
    if ($raw === '1' || strcasecmp($raw, 'atendido') === 0) {
        return ['label' => 'Atendido', 'class' => 'status-ok'];
    }
    if ($raw === '2' || strcasecmp($raw, 'muerto') === 0) {
        return ['label' => 'Muerto', 'class' => 'status-bad'];
    }
    if ($raw === '3' || strcasecmp($raw, 'cliente') === 0) {
        return ['label' => 'Cliente', 'class' => 'status-client'];
    }

    return ['label' => 'Pendiente', 'class' => 'status-pending'];
}

ensureColumnExists($conn, 'wedding_planners', 'empresa_wp', "`empresa_wp` VARCHAR(255) DEFAULT NULL AFTER `where_is_your_marriage_taking_place_`");
ensureColumnExists($conn, 'wedding_planners', 'afianzado', "`afianzado` TINYINT(1) NOT NULL DEFAULT 0");
ensureColumnExists($conn, 'eventos_wp', 'estatus', "`estatus` VARCHAR(20) DEFAULT NULL AFTER `created_time`");
ensureColumnExists($conn, 'eventos_wp', 'fecha_actualizacion_estatus', "`fecha_actualizacion_estatus` DATETIME DEFAULT NULL AFTER `estatus`");

$rows = [];
$sql = "SELECT
            e.id,
            e.fecha_registro,
            e.wedding_planner_id,
            COALESCE(NULLIF(TRIM(wp.empresa_wp), ''), NULLIF(TRIM(wp.full_name), ''), NULLIF(TRIM(wp.campaign_name), ''), CONCAT('WP #', e.wedding_planner_id)) AS wedding_planner,
            e.novios,
            wp.afianzado,
            e.estatus,
            e.fecha_actualizacion_estatus,
            COALESCE(NULLIF(TRIM(cw.nombre), ''), 'Sin asignar') AS coordinador,
            e.lugar,
            e.fecha AS fecha_evento,
            COALESCE(NULLIF(TRIM(CONCAT(u.nombre, ' ', u.apepat)), ''), 'Sin asignar') AS asesor,
            COALESCE(NULLIF(TRIM(p.nombre), ''), 'Sin paquete') AS paquete
        FROM eventos_wp e
        LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id
        LEFT JOIN coordinadores_wp cw ON cw.id = e.id_coordinador
        LEFT JOIN usuarios u ON u.id = e.id_asesor
        LEFT JOIN paquetes p ON p.id = e.id_paquete
        ORDER BY COALESCE(e.created_time, e.fecha_registro, e.fecha) DESC, e.id DESC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WP Eventos</title>
  <style>
    :root {
      --bg: #f8fafc;
      --surface: #ffffff;
      --text: #1e293b;
      --muted: #64748b;
      --border: #e2e8f0;
      --ok-bg: #dcfce7;
      --ok-tx: #166534;
      --bad-bg: #fee2e2;
      --bad-tx: #991b1b;
      --pending-bg: #fef3c7;
      --pending-tx: #92400e;
      --client-bg: #dbeafe;
      --client-tx: #1e40af;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      padding: 24px 12px;
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
    }

    .page {
      margin-top: 40px;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }

    .card-header {
      padding: 12px 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid var(--border);
    }

    .card-title {
      font-size: 14px;
      font-weight: 700;
    }

    .btn {
      border: 1px solid #cbd5e1;
      background: #fff;
      color: #0f172a;
      border-radius: 8px;
      padding: 6px 10px;
      text-decoration: none;
      font-size: 12px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    .btn:hover { opacity: 0.92; }

    .btn-dark {
      background: #111827;
      color: #fff;
      border-color: #111827;
    }

    .table-wrap {
      width: 100%;
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 1500px;
    }

    th, td {
      padding: 10px 12px;
      border-bottom: 1px solid var(--border);
      text-align: left;
      font-size: 12px;
      vertical-align: top;
      white-space: nowrap;
    }

    th {
      background: #f8fafc;
      color: #334155;
      font-weight: 700;
      position: sticky;
      top: 0;
    }

    .status-pill {
      display: inline-flex;
      padding: 3px 9px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 700;
    }

    .status-ok { background: var(--ok-bg); color: var(--ok-tx); }
    .status-bad { background: var(--bad-bg); color: var(--bad-tx); }
    .status-pending { background: var(--pending-bg); color: var(--pending-tx); }
    .status-client { background: var(--client-bg); color: var(--client-tx); }

    .actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .empty {
      padding: 22px;
      text-align: center;
      color: var(--muted);
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="card">
      <div class="card-header">
        <span class="card-title">Todos los eventos</span>
        <a class="btn" href="eventos_wp.php">Ir a Eventos WP</a>
      </div>
      <div class="table-wrap">
        <?php if (!empty($rows)): ?>
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Fecha de registro</th>
                <th>Wedding Planner</th>
                <th>Nombre de los novios</th>
                <th>Afianzado</th>
                <th>Estatus</th>
                <th>Fecha de actualización</th>
                <th>Coordinador</th>
                <th>Lugar del evento</th>
                <th>Fecha del evento</th>
                <th>Asesor</th>
                <th>Paquete</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <?php $status = statusMeta($row['estatus'] ?? ''); ?>
                <tr>
                  <td><?php echo intval($row['id'] ?? 0); ?></td>
                  <td><?php echo htmlspecialchars(formatDateTimeCell($row['fecha_registro'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(trim((string) ($row['wedding_planner'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(trim((string) ($row['novios'] ?? '')) !== '' ? $row['novios'] : 'Sin novios', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo intval($row['afianzado'] ?? 0) === 1 ? 'Sí' : 'No'; ?></td>
                  <td><span class="status-pill <?php echo htmlspecialchars($status['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                  <td><?php echo htmlspecialchars(formatDateTimeCell($row['fecha_actualizacion_estatus'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(trim((string) ($row['coordinador'] ?? '')) !== '' ? $row['coordinador'] : 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(trim((string) ($row['lugar'] ?? '')) !== '' ? $row['lugar'] : 'Sin lugar', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(formatDateTimeCell($row['fecha_evento'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(trim((string) ($row['asesor'] ?? '')) !== '' ? $row['asesor'] : 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(trim((string) ($row['paquete'] ?? '')) !== '' ? $row['paquete'] : 'Sin paquete', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <div class="actions">
                      <a class="btn" href="planner_profile.php?id=<?php echo intval($row['wedding_planner_id'] ?? 0); ?>">Ver planner</a>
                      <a class="btn btn-dark" href="eventos_wp.php">Abrir eventos</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty">No hay eventos registrados.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php include 'footer.php'; ?>
</body>
</html>
