<?php
/**
 * MIGRATION: marketing_template_queue → marketing_template_schedules
 *
 * Qué hace este script:
 *  1. Crea la tabla marketing_template_schedules (si no existe).
 *  2. Lee los registros PENDING/ERROR de marketing_template_queue y genera
 *     entradas en marketing_template_schedules (1 por combinación única
 *     template_id + fecha + hora).  INSERT IGNORE → idempotente.
 *  3. (Opcional, Paso 3) Elimina las columnas de scheduling antiguas de
 *     marketing_templates: schedule_every_days, schedule_time, schedule_repeat.
 *
 * USO:
 *  - Abrir en el navegador sin parámetros → dry-run con preview.
 *  - Pasar ?run=1&step=1  → Ejecutar solo pasos 1 y 2 (estructura + datos).
 *  - Pasar ?run=1&step=2  → Ejecutar solo paso 3 (eliminar columnas antiguas).
 *  - Pasar ?run=1&step=all → Ejecutar todos los pasos.
 *
 * RESTRICCIÓN: No toca ni elimina registros de marketing_template_queue.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'conn.php';

// ─── helpers ──────────────────────────────────────────────────────────────────
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function columnExists(mysqli $c, string $table, string $col): bool {
    $r = $c->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
    return ($r && $r->num_rows > 0);
}

function tableExists(mysqli $c, string $table): bool {
    $db = $c->query("SELECT DATABASE()")->fetch_row()[0];
    $sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
    $s = $c->prepare($sql);
    $s->bind_param('ss', $db, $table);
    $s->execute();
    return (bool) $s->get_result()->fetch_row()[0];
}

// ─── recolectar información de estado actual ──────────────────────────────────
$schedulesExists   = tableExists($conn, 'marketing_template_schedules');
$colDaysExists     = columnExists($conn, 'marketing_templates', 'schedule_every_days');
$colTimeExists     = columnExists($conn, 'marketing_templates', 'schedule_time');
$colRepeatExists   = columnExists($conn, 'marketing_templates', 'schedule_repeat');

// Filas pendientes/error en la queue
$pendingRows = [];
$r = $conn->query("SELECT id, template_id, tabla_origen, lead_id, status,
                          scheduled_at
                   FROM marketing_template_queue
                   WHERE status IN ('pending','error')
                   ORDER BY scheduled_at ASC");
while ($row = $r->fetch_assoc()) {
    $pendingRows[] = $row;
}

// Schedules únicos que se insertarían
$uniqueSchedules = [];
foreach ($pendingRows as $row) {
    $dt    = new DateTime($row['scheduled_at']);
    $date  = $dt->format('Y-m-d');
    $time  = $dt->format('H:i:s');
    $key   = $row['template_id'] . '|' . $date;
    if (!isset($uniqueSchedules[$key])) {
        $uniqueSchedules[$key] = [
            'template_id'   => (int) $row['template_id'],
            'schedule_date' => $date,
            'send_time'     => $time,
            'leads'         => 0,
        ];
    }
    $uniqueSchedules[$key]['leads']++;
}

// Schedules ya existentes en la tabla destino (si ya existe)
$existingSchedules = [];
if ($schedulesExists) {
    $r2 = $conn->query("SELECT template_id, schedule_date, send_time FROM marketing_template_schedules");
    while ($row = $r2->fetch_assoc()) {
        $existingSchedules[$row['template_id'] . '|' . $row['schedule_date']] = true;
    }
}

$newSchedules    = array_filter($uniqueSchedules, fn($k) => !isset($existingSchedules[$k]), ARRAY_FILTER_USE_KEY);
$alreadyScheduled = array_filter($uniqueSchedules, fn($k) => isset($existingSchedules[$k]), ARRAY_FILTER_USE_KEY);

// Columnas antiguas que existen y se eliminarían
$oldColsToRemove = [];
if ($colDaysExists)   $oldColsToRemove[] = 'schedule_every_days';
if ($colTimeExists)   $oldColsToRemove[] = 'schedule_time';
if ($colRepeatExists) $oldColsToRemove[] = 'schedule_repeat';

// Nombre de templates para display
$templateNames = [];
$r3 = $conn->query("SELECT id, nombre FROM marketing_templates");
while ($row = $r3->fetch_assoc()) {
    $templateNames[(int)$row['id']] = $row['nombre'];
}

// ─── ejecución ────────────────────────────────────────────────────────────────
$run  = isset($_GET['run']) && $_GET['run'] === '1';
$step = $_GET['step'] ?? 'all';  // 'all' | '1' (estructura+datos) | '2' (old cols)

$log  = [];
$errors = [];

if ($run) {
    $conn->begin_transaction();

    try {
        // ── PASO 1 ── Crear tabla marketing_template_schedules ─────────────────
        if (in_array($step, ['all', '1'])) {
            if (!$schedulesExists) {
                $sql = "CREATE TABLE `marketing_template_schedules` (
                    `id`            INT         NOT NULL AUTO_INCREMENT,
                    `template_id`   INT         NOT NULL,
                    `schedule_date` DATE        NOT NULL DEFAULT '2000-01-01',
                    `send_time`     TIME        NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `ux_template_date` (`template_id`, `schedule_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3";
                if (!$conn->query($sql)) throw new RuntimeException("Error creando tabla: " . $conn->error);
                $log[] = ['ok', 'Tabla <strong>marketing_template_schedules</strong> creada.'];
            } else {
                $log[] = ['info', 'Tabla <strong>marketing_template_schedules</strong> ya existía.'];
            }

            // ── PASO 2 ── Insertar schedules únicos ────────────────────────────
            $inserted = 0;
            $skipped  = 0;
            $stmt = $conn->prepare(
                "INSERT IGNORE INTO marketing_template_schedules (template_id, schedule_date, send_time)
                 VALUES (?, ?, ?)"
            );
            foreach ($uniqueSchedules as $key => $sched) {
                $stmt->bind_param('iss', $sched['template_id'], $sched['schedule_date'], $sched['send_time']);
                if (!$stmt->execute()) throw new RuntimeException("Error insertando schedule: " . $stmt->error);
                if ($stmt->affected_rows > 0) $inserted++;
                else $skipped++;
            }
            $stmt->close();

            if ($inserted > 0) {
                $log[] = ['ok', "Insertados <strong>{$inserted}</strong> schedule(s) en <em>marketing_template_schedules</em>."];
            }
            if ($skipped > 0) {
                $log[] = ['info', "Omitidos <strong>{$skipped}</strong> schedule(s) que ya existían (INSERT IGNORE)."];
            }
            if ($inserted === 0 && $skipped === 0) {
                $log[] = ['info', 'No había registros pending/error en marketing_template_queue. Nada que migrar.'];
            }
        }

        // ── PASO 3 ── Eliminar columnas antiguas de marketing_templates ────────
        if (in_array($step, ['all', '2'])) {
            $removed = [];
            foreach (['schedule_every_days', 'schedule_time', 'schedule_repeat'] as $col) {
                if (columnExists($conn, 'marketing_templates', $col)) {
                    if (!$conn->query("ALTER TABLE `marketing_templates` DROP COLUMN `{$col}`")) {
                        throw new RuntimeException("Error eliminando columna {$col}: " . $conn->error);
                    }
                    $removed[] = $col;
                }
            }
            if ($removed) {
                $log[] = ['ok', 'Columnas eliminadas de <em>marketing_templates</em>: <strong>' . implode(', ', $removed) . '</strong>.'];
            } else {
                $log[] = ['info', 'Las columnas antiguas de scheduling ya no existían en <em>marketing_templates</em>.'];
            }
        }

        $conn->commit();
        $log[] = ['ok', '<strong>Migración completada con éxito.</strong>'];

    } catch (Throwable $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Migración: Queue → Schedules</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #f0f4f8; color: #1e293b; padding: 24px; }
        h1  { font-size: 1.5rem; margin-bottom: 4px; }
        .subtitle { color: #64748b; font-size: 0.875rem; margin-bottom: 24px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 20px 24px; margin-bottom: 20px; }
        h2  { font-size: 1rem; font-weight: 600; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        th, td { padding: 7px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th  { background: #f8fafc; font-weight: 600; color: #475569; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
        .badge-new  { background: #dcfce7; color: #166534; }
        .badge-dup  { background: #fef3c7; color: #92400e; }
        .badge-pending { background: #dbeafe; color: #1d4ed8; }
        .badge-error   { background: #fee2e2; color: #991b1b; }
        .stat  { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 0; }
        .stat-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 18px; min-width: 130px; }
        .stat-item .num { font-size: 1.8rem; font-weight: 700; color: #0f172a; }
        .stat-item .lbl { font-size: 0.75rem; color: #64748b; }
        .btn { display: inline-block; padding: 10px 24px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; text-decoration: none; border: none; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-danger  { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn-secondary:hover { background: #cbd5e1; }
        .btn-sm { padding: 6px 14px; font-size: 0.8rem; }
        .log-ok   { background: #f0fdf4; border-left: 3px solid #22c55e; color: #166534; padding: 8px 14px; border-radius: 4px; margin-bottom: 8px; }
        .log-info { background: #eff6ff; border-left: 3px solid #3b82f6; color: #1e40af; padding: 8px 14px; border-radius: 4px; margin-bottom: 8px; }
        .log-err  { background: #fef2f2; border-left: 3px solid #ef4444; color: #991b1b; padding: 8px 14px; border-radius: 4px; margin-bottom: 8px; }
        .col-tag  { background: #fdf4ff; border: 1px solid #e9d5ff; color: #7e22ce; border-radius: 6px; padding: 2px 8px; font-size: 0.78rem; font-family: monospace; display: inline-block; margin: 2px; }
        .warn { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 12px 16px; color: #78350f; font-size: 0.85rem; margin-bottom: 16px; }
        .warn strong { display: block; margin-bottom: 4px; }
        .action-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .section-sep { border: none; border-top: 1px dashed #e2e8f0; margin: 16px 0; }
        details summary { cursor: pointer; font-weight: 600; font-size: 0.85rem; color: #475569; }
        details[open] summary { margin-bottom: 10px; }
    </style>
</head>
<body>

<h1>Migración: Queue → Schedules</h1>
<p class="subtitle">
    <code>marketing_template_queue</code> (cron) →
    <code>marketing_template_schedules</code> (calendario)
</p>

<?php if ($run && $errors): ?>
<div class="card">
    <h2>❌ Errores durante la migración</h2>
    <?php foreach ($errors as $err): ?>
        <div class="log-err"><?= e($err) ?></div>
    <?php endforeach; ?>
    <p style="margin-top:12px;"><a href="migrate_queue_to_schedules.php" class="btn btn-secondary">← Volver al preview</a></p>
</div>
<?php elseif ($run && $log): ?>
<div class="card">
    <h2>✅ Resultado de la ejecución</h2>
    <?php foreach ($log as [$type, $msg]): ?>
        <div class="log-<?= $type === 'ok' ? 'ok' : 'info' ?>"><?= $msg ?></div>
    <?php endforeach; ?>
    <p style="margin-top:16px;"><a href="migrate_queue_to_schedules.php" class="btn btn-secondary btn-sm">← Nueva revisión</a></p>
</div>
<?php else: ?>

<!-- ── ESTADO ACTUAL ─────────────────────────────────────────────────────── -->
<div class="card">
    <h2>Estado actual de la base de datos</h2>
    <div class="stat">
        <div class="stat-item">
            <div class="num"><?= count($pendingRows) ?></div>
            <div class="lbl">Filas pending/error en queue</div>
        </div>
        <div class="stat-item">
            <div class="num"><?= count($newSchedules) ?></div>
            <div class="lbl">Schedules nuevos a insertar</div>
        </div>
        <div class="stat-item">
            <div class="num"><?= count($alreadyScheduled) ?></div>
            <div class="lbl">Schedules ya existentes</div>
        </div>
        <div class="stat-item">
            <div class="num"><?= $schedulesExists ? '✓' : '✗' ?></div>
            <div class="lbl">Tabla schedules existe</div>
        </div>
        <div class="stat-item">
            <div class="num"><?= count($oldColsToRemove) ?></div>
            <div class="lbl">Columnas antiguas a eliminar</div>
        </div>
    </div>
</div>

<!-- ── PASO 1+2: SCHEDULES A MIGRAR ─────────────────────────────────────── -->
<div class="card">
    <h2>Paso 1 &amp; 2 — Crear tabla y migrar schedules</h2>

    <?php if (!$schedulesExists): ?>
        <div class="log-info" style="margin-bottom:12px;">
            La tabla <strong>marketing_template_schedules</strong> <em>no existe</em> en producción. Se creará automáticamente.
        </div>
    <?php else: ?>
        <div class="log-ok" style="margin-bottom:12px;">
            La tabla <strong>marketing_template_schedules</strong> ya existe.
        </div>
    <?php endif; ?>

    <?php if (empty($uniqueSchedules)): ?>
        <p style="color:#64748b;">No hay registros pending/error en <code>marketing_template_queue</code>. No hay nada que migrar.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>template_id</th>
                    <th>Nombre plantilla</th>
                    <th>schedule_date</th>
                    <th>send_time</th>
                    <th>Leads afectados</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($uniqueSchedules as $key => $sched):
                $isNew = !isset($existingSchedules[$key]);
                $name  = $templateNames[$sched['template_id']] ?? '(desconocida)';
            ?>
                <tr>
                    <td><?= $sched['template_id'] ?></td>
                    <td><?= e($name) ?></td>
                    <td><?= e($sched['schedule_date']) ?></td>
                    <td><?= e($sched['send_time']) ?></td>
                    <td><?= $sched['leads'] ?></td>
                    <td><span class="badge <?= $isNew ? 'badge-new' : 'badge-dup' ?>"><?= $isNew ? 'NUEVO' : 'YA EXISTE' ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($pendingRows)): ?>
    <hr class="section-sep">
    <details>
        <summary>Ver detalle de las <?= count($pendingRows) ?> filas en queue que se migrarán (view only)</summary>
        <table style="margin-top:8px;">
            <thead>
                <tr>
                    <th>queue.id</th><th>template_id</th><th>tabla_origen</th>
                    <th>lead_id</th><th>status</th><th>scheduled_at</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingRows as $pr): ?>
                <tr>
                    <td><?= $pr['id'] ?></td>
                    <td><?= $pr['template_id'] ?></td>
                    <td><?= e($pr['tabla_origen']) ?></td>
                    <td><?= $pr['lead_id'] ?></td>
                    <td><span class="badge <?= $pr['status'] === 'pending' ? 'badge-pending' : 'badge-error' ?>"><?= e($pr['status']) ?></span></td>
                    <td><?= e($pr['scheduled_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </details>
    <?php endif; ?>

    <hr class="section-sep">
    <div class="action-row">
        <?php if (count($newSchedules) > 0 || !$schedulesExists): ?>
            <a href="?run=1&step=1" class="btn btn-primary"
               onclick="return confirm('¿Ejecutar Paso 1+2? Esto creará la tabla (si no existe) e insertará <?= count($newSchedules) ?> schedule(s) nuevos.')">
               ▶ Ejecutar Paso 1 + 2
            </a>
        <?php else: ?>
            <span class="btn btn-secondary" style="cursor:default;opacity:.6;">Nada nuevo que migrar</span>
        <?php endif; ?>
    </div>
</div>

<!-- ── PASO 3: COLUMNAS ANTIGUAS ─────────────────────────────────────────── -->
<div class="card">
    <h2>Paso 3 — Eliminar columnas de scheduling antiguas de <code>marketing_templates</code></h2>

    <?php if (empty($oldColsToRemove)): ?>
        <div class="log-ok">
            Las columnas antiguas (<code>schedule_every_days</code>, <code>schedule_time</code>, <code>schedule_repeat</code>) ya no existen. Nada que hacer.
        </div>
    <?php else: ?>
        <div class="warn">
            <strong>⚠️ Asegúrate antes de ejecutar este paso:</strong>
            El cron antiguo (<code>cron_send_marketing_templates.php</code>) usa estas columnas. Si aún está activo,
            eliminándolas causará errores en el cron. Desactívalo primero o asegúrate de que ya no se ejecuta.
        </div>
        <p style="margin-bottom:12px;font-size:.875rem;">Se eliminarán las siguientes columnas de <code>marketing_templates</code>:</p>
        <?php foreach ($oldColsToRemove as $col): ?>
            <span class="col-tag"><?= e($col) ?></span>
        <?php endforeach; ?>
        <hr class="section-sep">
        <div class="action-row">
            <a href="?run=1&step=2" class="btn btn-danger"
               onclick="return confirm('¿Eliminar las columnas: <?= implode(', ', $oldColsToRemove) ?>?\n\nEsta operación NO se puede deshacer fácilmente.')">
               🗑 Ejecutar Paso 3 (eliminar columnas)
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- ── EJECUTAR TODO ─────────────────────────────────────────────────────── -->
<?php if ((count($newSchedules) > 0 || !$schedulesExists) && !empty($oldColsToRemove)): ?>
<div class="card">
    <h2>Ejecutar todo en un solo paso</h2>
    <div class="warn">
        <strong>⚠️ Este botón ejecuta los 3 pasos en una sola transacción.</strong>
        Revisa las advertencias del Paso 3 antes de continuar.
    </div>
    <a href="?run=1&step=all" class="btn btn-danger"
       onclick="return confirm('¿Ejecutar TODOS los pasos?\n\n1. Crear tabla marketing_template_schedules\n2. Migrar <?= count($newSchedules) ?> schedule(s)\n3. Eliminar columnas antiguas\n\nEsta operación no se puede deshacer fácilmente.')">
       ⚡ Ejecutar TODO (pasos 1+2+3)
    </a>
</div>
<?php endif; ?>

<?php endif; // end not $run ?>

<p style="margin-top:20px;font-size:.75rem;color:#94a3b8;">
    migrate_queue_to_schedules.php — Solo usa GET params para ejecutar (run=1&amp;step=1|2|all).
    Los registros de <code>marketing_template_queue</code> no se modifican ni eliminan.
</p>

</body>
</html>
