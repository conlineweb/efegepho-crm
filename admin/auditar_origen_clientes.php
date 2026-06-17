<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

include __DIR__ . '/../conn.php';

$days = 30;

if (PHP_SAPI === 'cli') {
    global $argv;
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if (preg_match('/^--days=(\d+)$/', (string) $arg, $matches)) {
            $days = max(1, (int) $matches[1]);
        }
    }
} elseif (isset($_GET['days'])) {
    $days = max(1, (int) $_GET['days']);
}

function mapOriginCategory(?string $value): string
{
    $normalized = trim((string) $value);
    if ($normalized === '1') {
        return 'Wedding Planner';
    }
    if ($normalized === '2') {
        return 'Community';
    }
    if ($normalized === '3') {
        return 'New Market';
    }
    return 'Por confirmar';
}

function mapCalendarStatus($value): string
{
    $normalized = trim((string) $value);
    if ($normalized === '0') {
        return 'Pendiente';
    }
    if ($normalized === '1') {
        return 'Atendida';
    }
    if ($normalized === '2') {
        return 'Cliente';
    }
    if ($normalized === '3') {
        return 'Muerto';
    }
    return 'Sin cita';
}

$sql = <<<SQL
SELECT
    cf.id,
    cf.cliente,
    cf.names,
    cf.email_address,
    cf.telephone,
    cf.country_code,
    cf.tabla_origen,
    cf.original_lead_id,
    cf.campaign_name,
    cf.form_name,
    cf.submission_date,
    cf.created_time,
    cf.fecha_cambio_cliente,
    cf.how_did_you_meet,
    cf.hear_about_us,
    cf.how_long_known_us,
    cf.first_contact_channel,
    cf.manual,
    cf.desde_publicidad,
    cf.id_vendedor_asignado,
    cal.id AS last_calendar_id,
    cal.estatus AS last_calendar_status,
    cal.fecha AS last_calendar_date,
    cal.hora AS last_calendar_time,
    cal.comentario AS last_calendar_comment,
    cal.comentario_a_cliente AS last_calendar_client_comment
FROM contact_form cf
LEFT JOIN (
    SELECT c1.*
    FROM calendario c1
    INNER JOIN (
        SELECT idclie, MAX(id) AS max_id
        FROM calendario
        WHERE eliminado = 0
        GROUP BY idclie
    ) latest ON latest.idclie = c1.idclie AND latest.max_id = c1.id
) cal ON cal.idclie = cf.id
WHERE cf.cliente = 1
  AND cf.fecha_cambio_cliente >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
  AND LOWER(COALESCE(cf.tabla_origen, '')) NOT IN ('wedding_planners', 'wedding_planner')
ORDER BY cf.fecha_cambio_cliente DESC, cf.id DESC
SQL;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo preparar la consulta.',
        'error' => $conn->error,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param('i', $days);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$summary = [
    'total_clients' => 0,
    'missing_origin' => 0,
    'with_origin' => 0,
    'cliente_values_found' => [],
    'by_table' => [],
    'by_calendar_status' => [],
];

while ($row = $result->fetch_assoc()) {
    $howDidYouMeet = trim((string) ($row['how_did_you_meet'] ?? ''));
    $calendarStatusLabel = mapCalendarStatus($row['last_calendar_status'] ?? null);
    $tableName = trim((string) ($row['tabla_origen'] ?? ''));
    $originMissing = !in_array($howDidYouMeet, ['1', '2', '3'], true);

    $auditRow = [
        'id' => (int) $row['id'],
        'cliente' => isset($row['cliente']) ? (int) $row['cliente'] : null,
        'names' => $row['names'],
        'email_address' => $row['email_address'],
        'telephone' => $row['telephone'],
        'country_code' => $row['country_code'],
        'tabla_origen' => $tableName,
        'original_lead_id' => isset($row['original_lead_id']) ? (int) $row['original_lead_id'] : null,
        'campaign_name' => $row['campaign_name'],
        'form_name' => $row['form_name'],
        'submission_date' => $row['submission_date'],
        'created_time' => $row['created_time'],
        'fecha_cambio_cliente' => $row['fecha_cambio_cliente'],
        'how_did_you_meet' => $row['how_did_you_meet'],
        'origin_category_label' => mapOriginCategory($row['how_did_you_meet'] ?? null),
        'origin_missing' => $originMissing,
        'hear_about_us' => $row['hear_about_us'],
        'how_long_known_us' => $row['how_long_known_us'],
        'first_contact_channel' => $row['first_contact_channel'],
        'manual' => isset($row['manual']) ? (int) $row['manual'] : null,
        'desde_publicidad' => isset($row['desde_publicidad']) ? (int) $row['desde_publicidad'] : null,
        'id_vendedor_asignado' => isset($row['id_vendedor_asignado']) ? (int) $row['id_vendedor_asignado'] : null,
        'last_calendar_id' => isset($row['last_calendar_id']) ? (int) $row['last_calendar_id'] : null,
        'last_calendar_status' => $row['last_calendar_status'],
        'last_calendar_status_label' => $calendarStatusLabel,
        'last_calendar_date' => $row['last_calendar_date'],
        'last_calendar_time' => $row['last_calendar_time'],
        'last_calendar_comment' => $row['last_calendar_comment'],
        'last_calendar_client_comment' => $row['last_calendar_client_comment'],
    ];

    $rows[] = $auditRow;

    $summary['total_clients']++;
    if ($originMissing) {
        $summary['missing_origin']++;
    } else {
        $summary['with_origin']++;
    }

    $clienteValue = isset($row['cliente']) ? (string) ((int) $row['cliente']) : 'null';
    if (!isset($summary['cliente_values_found'][$clienteValue])) {
        $summary['cliente_values_found'][$clienteValue] = 0;
    }
    $summary['cliente_values_found'][$clienteValue]++;

    if (!isset($summary['by_table'][$tableName])) {
        $summary['by_table'][$tableName] = [
            'total' => 0,
            'missing_origin' => 0,
            'with_origin' => 0,
        ];
    }

    $summary['by_table'][$tableName]['total']++;
    if ($originMissing) {
        $summary['by_table'][$tableName]['missing_origin']++;
    } else {
        $summary['by_table'][$tableName]['with_origin']++;
    }

    if (!isset($summary['by_calendar_status'][$calendarStatusLabel])) {
        $summary['by_calendar_status'][$calendarStatusLabel] = 0;
    }
    $summary['by_calendar_status'][$calendarStatusLabel]++;
}

$response = [
    'success' => true,
    'generated_at' => date('c'),
    'window_days' => $days,
    'filters' => [
        'cliente' => 1,
        'fecha_cambio_cliente_from' => date('Y-m-d', strtotime('-' . $days . ' days')),
        'exclude_tabla_origen' => ['wedding_planners', 'wedding_planner'],
    ],
    'summary' => $summary,
    'rows' => $rows,
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$stmt->close();
$conn->close();
?>