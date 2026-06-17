<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php';

function plannerProfileFormatDate($dateString)
{
    if (empty($dateString)) {
        return 'Sin registro';
    }

    $timestamp = strtotime($dateString);
    if ($timestamp === false) {
        return $dateString;
    }

    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $month = $months[date('n', $timestamp) - 1];
    $day = date('j', $timestamp);
    $year = date('Y', $timestamp);

    return $day . ' ' . $month . ' ' . $year;
}

function plannerProfileDisplayName(array $planner)
{
    $empresa = trim((string) ($planner['empresa_wp'] ?? ''));
    if ($empresa !== '') {
        return $empresa;
    }

    $fullName = trim((string) ($planner['full_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    $campaign = trim((string) ($planner['campaign_name'] ?? ''));
    if ($campaign !== '') {
        return $campaign;
    }

    return 'WP #' . intval($planner['id'] ?? 0);
}

function plannerProfileFirstNonEmpty(...$values)
{
  foreach ($values as $value) {
    $candidate = plannerProfileSanitizeValue($value);
    if ($candidate !== '') {
      return $candidate;
    }
  }

  return '';
}

function plannerProfileFirstNonEmptyFromRows(array $rows, $field)
{
  foreach ($rows as $row) {
    $value = plannerProfileSanitizeValue($row[$field] ?? '');
    if ($value !== '') {
      return $value;
    }
  }

  return '';
}

function plannerProfileSanitizeValue($value)
{
  $candidate = trim((string) $value);
  if ($candidate === '') {
    return '';
  }

  $lower = strtolower($candidate);
  $invalidValues = ['null', 'undefined', 'n/a', 'na', 'sin dato', 's/d'];
  if (in_array($lower, $invalidValues, true)) {
    return '';
  }

  return $candidate;
}

function plannerProfileNormalizeTextKey($value)
{
  $value = trim((string) $value);
  if ($value === '') {
    return '';
  }

  $replacements = [
    'á' => 'a',
    'é' => 'e',
    'í' => 'i',
    'ó' => 'o',
    'ú' => 'u',
    'Á' => 'a',
    'É' => 'e',
    'Í' => 'i',
    'Ó' => 'o',
    'Ú' => 'u',
    'ñ' => 'n',
    'Ñ' => 'n',
  ];

  $value = strtr($value, $replacements);
  return strtolower($value);
}

function plannerProfileInteractionTypeClass($type)
{
  $key = plannerProfileNormalizeTextKey($type);
  if ($key === 'llamada') {
    return 'type-llamada';
  }
  if ($key === 'whatsapp') {
    return 'type-whatsapp';
  }
  if ($key === 'email') {
    return 'type-email';
  }
  if ($key === 'reunion') {
    return 'type-reunion';
  }
  if ($key === 'nota interna') {
    return 'type-nota';
  }
  if ($key === 'sin respuesta') {
    return 'type-sinrespuesta';
  }

  return 'type-default';
}

function plannerProfileInteractionTypeIcon($type)
{
  $key = plannerProfileNormalizeTextKey($type);
  if ($key === 'llamada') {
    return '📞';
  }
  if ($key === 'whatsapp') {
    return '💬';
  }
  if ($key === 'email') {
    return '✉️';
  }
  if ($key === 'reunion') {
    return '🤝';
  }
  if ($key === 'nota interna') {
    return '📝';
  }
  if ($key === 'sin respuesta') {
    return '…';
  }

  return '💭';
}

function plannerProfileOutcomeClass($outcome)
{
  $key = plannerProfileNormalizeTextKey($outcome);
  if ($key === 'positivo') {
    return 'outcome-positivo';
  }
  if ($key === 'neutral') {
    return 'outcome-neutral';
  }
  if ($key === 'negativo') {
    return 'outcome-negativo';
  }
  if ($key === 'listo para cerrar') {
    return 'outcome-cerrar';
  }
  if ($key === 'requiere seguimiento') {
    return 'outcome-seguimiento';
  }
  if ($key === 'sin respuesta') {
    return 'outcome-sinrespuesta';
  }

  return 'outcome-default';
}

function plannerProfileOutcomeIcon($outcome)
{
  $key = plannerProfileNormalizeTextKey($outcome);
  if ($key === 'positivo') {
    return '✅';
  }
  if ($key === 'neutral') {
    return '⚪';
  }
  if ($key === 'negativo') {
    return '❌';
  }
  if ($key === 'listo para cerrar') {
    return '🔥';
  }
  if ($key === 'requiere seguimiento') {
    return '⏳';
  }
  if ($key === 'sin respuesta') {
    return '…';
  }

  return '•';
}

function plannerProfileInteractionDateTime($dateValue, $timeValue = '', $createdAt = '')
{
  $datePart = trim((string) $dateValue);
  $timePart = trim((string) $timeValue);

  if ($datePart === '' && trim((string) $createdAt) !== '') {
    $timestamp = strtotime((string) $createdAt);
    if ($timestamp !== false) {
      $datePart = date('Y-m-d', $timestamp);
      $timePart = date('H:i:s', $timestamp);
    }
  }

  return plannerProfileFormatDateTime($datePart, $timePart);
}

function plannerProfileInitials($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return 'WP';
    }

    $parts = preg_split('/\s+/', $name);
    $initials = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($initials, 'UTF-8') >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'WP';
}

function plannerProfileAfianzadoLabel($value)
{
    $status = intval($value);
    if ($status === 1) {
        return 'Afianzado';
    }
    if ($status === 2) {
        return 'En proceso';
    }
    if ($status === 3) {
        return 'Nuevo';
    }

    return 'No afianzado';
}

  function plannerProfileFormatDateTime($dateValue, $timeValue = '')
  {
    $raw = trim((string) $dateValue . ' ' . (string) $timeValue);
    if ($raw === '') {
      return 'Sin fecha';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
      return trim((string) $dateValue . ' ' . (string) $timeValue);
    }

    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $month = $months[date('n', $timestamp) - 1];
    $day = date('j', $timestamp);
    $year = date('Y', $timestamp);
    $hour = date('g:i a', $timestamp);

    return $day . ' ' . $month . ' ' . $year . ' · ' . $hour;
  }

  function plannerProfileCalendarStatusLabel($value)
  {
    $status = is_numeric($value) ? intval($value) : null;
    if ($status === 1) {
      return 'Atendido';
    }
    if ($status === 2) {
      return 'Fantasma';
    }
    if ($status === 3) {
      return 'Muerto';
    }
    if ($status === 0) {
      return 'Agendado';
    }

    $raw = trim((string) $value);
    return $raw !== '' ? $raw : 'Sin estatus';
  }

  function plannerProfileCalendarStatusClass($value)
  {
    $status = is_numeric($value) ? intval($value) : null;
    if ($status === 1) {
      return 'status-afianzado';
    }

    return 'status-pendiente';
  }

  function plannerProfileEventStatusMeta($value, $contactFormCliente = null)
  {
    $raw = trim((string) $value);
    $cliente = is_numeric($contactFormCliente) ? intval($contactFormCliente) : null;

    if ($cliente === 1 || strcasecmp($raw, 'cliente') === 0) {
      return ['label' => 'Cliente', 'class' => 'status-afianzado'];
    }
    if ($cliente === 3 || $raw === '3' || strcasecmp($raw, 'rechazado') === 0) {
      return ['label' => 'Rechazado', 'class' => 'status-rechazado'];
    }
    if ($cliente === 2 || $raw === '2' || strcasecmp($raw, 'cotizado') === 0) {
      return ['label' => 'Cotizado', 'class' => 'status-pendiente'];
    }
    if ($cliente === 4 || $raw === '4' || strcasecmp($raw, 'cliente inminente') === 0) {
      return ['label' => 'Cliente inminente', 'class' => 'status-inminente'];
    }
    if ($raw === '1' || strcasecmp($raw, 'atendido') === 0) {
      return ['label' => 'Atendido', 'class' => 'status-afianzado'];
    }

    return ['label' => 'Pendiente', 'class' => 'status-pendiente'];
  }

  function plannerProfileNextEventStatusMeta($value, $contactFormCliente = null)
  {
    $current = plannerProfileEventStatusMeta($value, $contactFormCliente);
    $label = strtolower((string) ($current['label'] ?? ''));

    if ($label === 'atendido') {
      return [
        'can_change' => true,
        'mode' => 'direct',
        'target' => 2,
        'button_label' => 'Pasar a Cotizado',
      ];
    }

    if ($label === 'cotizado') {
      return [
        'can_change' => true,
        'mode' => 'direct',
        'target' => 4,
        'button_label' => 'Pasar a Cliente inminente',
      ];
    }

    if ($label === 'cliente inminente') {
      return [
        'can_change' => true,
        'mode' => 'inminente_options',
        'target' => 0,
        'button_label' => 'Cambiar de estatus',
      ];
    }

    return [
      'can_change' => false,
      'mode' => 'none',
      'target' => 0,
      'button_label' => 'Sin cambios',
    ];
  }

$plannerId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$planner = null;
$plannerWpLeadRows = [];
$plannerLatestWpLead = [];
$plannerLatestContactForm = [];
$coordinators = [];
$appointments = [];
$plannerEvents = [];
$interactions = [];
$coordinatorInteractions = [];
$scheduledActions = [];
$hasConvertiblePendingAppointment = false;
$agents = [];
$packages = [];
$blockedDates = [];

if ($plannerId > 0) {
    $stmt = $conn->prepare('SELECT * FROM wedding_planners WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $plannerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $planner = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }

    $stmt = $conn->prepare('SELECT * FROM wp_citas_leads WHERE id_wedding_planner = ? AND (descartado = 0 OR descartado IS NULL) ORDER BY id DESC LIMIT 50');
    if ($stmt) {
      $stmt->bind_param('i', $plannerId);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result) {
          while ($row = $result->fetch_assoc()) {
              $plannerWpLeadRows[] = $row;
          }
      }
      $plannerLatestWpLead = !empty($plannerWpLeadRows) ? $plannerWpLeadRows[0] : [];
      $stmt->close();
    }

    $stmt = $conn->prepare("SELECT * FROM contact_form WHERE LOWER(tabla_origen) = 'wedding_planners' AND original_lead_id = ? ORDER BY id DESC LIMIT 1");
    if ($stmt) {
      $stmt->bind_param('i', $plannerId);
      $stmt->execute();
      $result = $stmt->get_result();
      $plannerLatestContactForm = $result ? ($result->fetch_assoc() ?: []) : [];
      $stmt->close();
    }

    $stmt = $conn->prepare('SELECT id, nombre, telefono, correo, fecha_creacion FROM coordinadores_wp WHERE id_wp = ? ORDER BY fecha_creacion DESC, id DESC');
    if ($stmt) {
        $stmt->bind_param('i', $plannerId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $coordinators[] = $row;
            }
        }
        $stmt->close();
    }

      $interactionWhereParts = ["(LOWER(tabla_origen) = 'wedding_planners' AND (lead_id = ? OR original_lead_id = ?))"];
      $interactionParams = [$plannerId, $plannerId];
      $interactionTypes = 'ii';

      $wpLeadIds = [];
      foreach ($plannerWpLeadRows as $wpLeadRow) {
        $wpLeadId = intval($wpLeadRow['id'] ?? 0);
        if ($wpLeadId > 0) {
          $wpLeadIds[] = $wpLeadId;
        }
      }

      if (!empty($wpLeadIds)) {
        $placeholders = implode(',', array_fill(0, count($wpLeadIds), '?'));
        $interactionWhereParts[] = "(LOWER(tabla_origen) = 'wp_citas_leads' AND (lead_id IN ({$placeholders}) OR original_lead_id = ?))";
        foreach ($wpLeadIds as $wpLeadId) {
          $interactionParams[] = $wpLeadId;
          $interactionTypes .= 'i';
        }
        $interactionParams[] = $plannerId;
        $interactionTypes .= 'i';
      } else {
        $interactionWhereParts[] = "(LOWER(tabla_origen) = 'wp_citas_leads' AND original_lead_id = ?)";
        $interactionParams[] = $plannerId;
        $interactionTypes .= 'i';
      }

      $interactionSql = "SELECT id, tabla_origen, lead_id, original_lead_id, interaction_type, interaction_date, interaction_time, notes, outcome, next_action, next_action_date, next_action_completed, created_at\n        FROM lead_interactions\n        WHERE " . implode(' OR ', $interactionWhereParts) . "\n        ORDER BY COALESCE(interaction_date, DATE(created_at)) DESC, COALESCE(interaction_time, TIME(created_at)) DESC, id DESC\n        LIMIT 100";
      $stmt = $conn->prepare($interactionSql);
      if ($stmt) {
        $bindArgs = [$interactionTypes];
        for ($i = 0; $i < count($interactionParams); $i++) {
          $bindArgs[] = &$interactionParams[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindArgs);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
          while ($row = $result->fetch_assoc()) {
            $interactions[] = $row;
          }
        }
        $stmt->close();
      }

      foreach ($interactions as $interaction) {
        $interactionType = trim((string) ($interaction['interaction_type'] ?? ''));
        $isSinRespuestaType = plannerProfileNormalizeTextKey($interactionType) === 'sin respuesta';
        $hasNotes = trim((string) ($interaction['notes'] ?? '')) !== '';
        $hasNextAction = trim((string) ($interaction['next_action'] ?? '')) !== '' || trim((string) ($interaction['next_action_date'] ?? '')) !== '';

        // Si hay notas, siempre se considera interaccion del coordinador aunque el tipo sea "Sin respuesta".
        if (!$isSinRespuestaType || $hasNotes) {
          $coordinatorInteractions[] = $interaction;
        }

        if ($hasNextAction || $isSinRespuestaType) {
          $scheduledActions[] = $interaction;
        }
      }

      if (!empty($scheduledActions)) {
        usort($scheduledActions, function ($a, $b) {
          $aDate = trim((string) ($a['next_action_date'] ?? ''));
          $bDate = trim((string) ($b['next_action_date'] ?? ''));

          if ($aDate === '' && $bDate === '') {
            return 0;
          }
          if ($aDate === '') {
            return 1;
          }
          if ($bDate === '') {
            return -1;
          }

          $aTs = strtotime($aDate) ?: PHP_INT_MAX;
          $bTs = strtotime($bDate) ?: PHP_INT_MAX;
          return $aTs <=> $bTs;
        });
      }

      $stmt = $conn->prepare("SELECT
          c.id AS calendario_id,
          c.fecha,
          c.hora,
          c.comentario,
          c.estatus,
          c.idusu AS vendedor_id,
          c.cliente_city,
          e.id AS evento_wp_id,
          e.estatus AS evento_estatus,
          e.created_time AS evento_created_time,
          u.nombre AS vendedor_nombre,
          u.apepat AS vendedor_apepat
        FROM eventos_wp e
        INNER JOIN calendario c ON c.idclie = e.id
        LEFT JOIN usuarios u ON u.id = c.idusu
        WHERE e.wedding_planner_id = ?
          AND c.eliminado = 0
          AND c.tipo = 1
        ORDER BY c.fecha DESC, c.hora DESC, c.id DESC");
      if ($stmt) {
        $stmt->bind_param('i', $plannerId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
          while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
          }
        }
        $stmt->close();
      }

      foreach ($appointments as $appointmentRow) {
        $appointmentCalendarStatus = is_numeric($appointmentRow['estatus'] ?? null) ? intval($appointmentRow['estatus']) : null;
        $appointmentEventStatusRaw = trim((string) ($appointmentRow['evento_estatus'] ?? ''));
        $appointmentHasEvent = $appointmentEventStatusRaw !== '' && $appointmentEventStatusRaw !== '0';

        if ($appointmentCalendarStatus !== 1 && !$appointmentHasEvent) {
          $hasConvertiblePendingAppointment = true;
          break;
        }
      }

      $eventCitySelect = "NULL AS ciudad_novios";
      $eventCityColumnResult = $conn->query("SHOW COLUMNS FROM eventos_wp LIKE 'ciudad_novios'");
      if ($eventCityColumnResult && $eventCityColumnResult->num_rows > 0) {
        $eventCitySelect = "e.ciudad_novios AS ciudad_novios";
      }

      $stmt = $conn->prepare("SELECT
          e.id,
          e.wedding_planner_id,
          e.id_coordinador,
          e.id_asesor,
          e.id_paquete,
          {$eventCitySelect},
          e.fecha_registro,
          COALESCE(NULLIF(TRIM(wp.empresa_wp), ''), NULLIF(TRIM(wp.full_name), ''), NULLIF(TRIM(wp.campaign_name), ''), CONCAT('WP #', e.wedding_planner_id)) AS wedding_planner,
          e.novios,
          wp.afianzado,
          e.estatus,
          e.fecha_actualizacion_estatus,
          COALESCE(NULLIF(TRIM(cw.nombre), ''), 'Sin asignar') AS coordinador_nombre,
          e.lugar,
          wp.city AS city,
          e.fecha AS fecha_evento,
          COALESCE(NULLIF(TRIM(CONCAT(u.nombre, ' ', u.apepat)), ''), 'Sin asignar') AS asesor_nombre,
          COALESCE(NULLIF(TRIM(p.nombre), ''), 'Sin paquete') AS paquete_nombre,
           cf.cliente AS contact_form_cliente,
           cf.monto_venta AS monto_venta
        FROM eventos_wp e
        LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id
        LEFT JOIN coordinadores_wp cw ON cw.id = e.id_coordinador
        LEFT JOIN usuarios u ON u.id = e.id_asesor
        LEFT JOIN paquetes p ON p.id = e.id_paquete
        LEFT JOIN (
          SELECT original_lead_id, MAX(id) AS latest_id
          FROM contact_form
          WHERE LOWER(COALESCE(tabla_origen, '')) = 'eventos_wp'
          GROUP BY original_lead_id
        ) cf_latest ON cf_latest.original_lead_id = e.id
        LEFT JOIN contact_form cf ON cf.id = cf_latest.latest_id
        WHERE e.wedding_planner_id = ?
        ORDER BY COALESCE(e.created_time, e.fecha_registro, e.fecha) DESC, e.id DESC");
      if ($stmt) {
        $stmt->bind_param('i', $plannerId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
          while ($row = $result->fetch_assoc()) {
            $plannerEvents[] = $row;
          }
        }
        $stmt->close();
      }
}

    $resultAgents = $conn->query("SELECT id, nombre, apepat FROM usuarios WHERE tipoUsu = 1 ORDER BY nombre, apepat");
    if ($resultAgents) {
      while ($row = $resultAgents->fetch_assoc()) {
        $agents[] = $row;
      }
    }

    $resultPackages = $conn->query("SELECT id, nombre FROM paquetes ORDER BY nombre ASC");
    if ($resultPackages) {
      while ($row = $resultPackages->fetch_assoc()) {
        $packages[] = $row;
      }
    }

    $resultBlockedDates = $conn->query("SELECT fecha FROM dias_bloqueados");
    if ($resultBlockedDates) {
      while ($row = $resultBlockedDates->fetch_assoc()) {
        if (!empty($row['fecha'])) {
          $blockedDates[] = $row['fecha'];
        }
      }
    }

$plannerName = $planner ? plannerProfileDisplayName($planner) : 'Wedding Planner';
$plannerInitials = plannerProfileInitials($plannerName);
$coordinatorCount = count($coordinators);
$plannerTotalQuotedAmount = 0.0;
foreach ($plannerEvents as $plannerEventRow) {
  if (is_numeric($plannerEventRow['monto_venta'] ?? null)) {
    $plannerTotalQuotedAmount += (float) $plannerEventRow['monto_venta'];
  }
}
$statusLabel = $planner ? plannerProfileAfianzadoLabel($planner['afianzado'] ?? 0) : 'Sin registro';
$plannerVendedorId = $planner ? intval($planner['id_vendedor_asignado'] ?? 0) : 0;
$plannerVendedorName = '';
if ($plannerVendedorId > 0) {
  foreach ($agents as $agent) {
    if (intval($agent['id'] ?? 0) === $plannerVendedorId) {
      $plannerVendedorName = trim((string) (($agent['nombre'] ?? '') . ' ' . ($agent['apepat'] ?? '')));
      break;
    }
  }
}
$plannerCompany = plannerProfileFirstNonEmpty(
  $planner['empresa_wp'] ?? '',
  $planner['full_name'] ?? '',
  plannerProfileFirstNonEmptyFromRows($coordinators, 'nombre'),
  $plannerLatestWpLead['full_name'] ?? '',
  plannerProfileFirstNonEmptyFromRows($plannerWpLeadRows, 'full_name'),
  $plannerLatestContactForm['names'] ?? '',
  $planner['campaign_name'] ?? '',
  $plannerLatestWpLead['campaign_name'] ?? ''
);
$plannerCity = plannerProfileFirstNonEmpty(
  $planner['city'] ?? '',
  $plannerLatestWpLead['city'] ?? '',
  plannerProfileFirstNonEmptyFromRows($plannerWpLeadRows, 'city'),
  $plannerLatestContactForm['city'] ?? ''
);
$plannerEmail = plannerProfileFirstNonEmpty(
  $planner['email'] ?? '',
  $planner['correo'] ?? '',
  $planner['email_address'] ?? '',
  $plannerLatestWpLead['email'] ?? '',
  $plannerLatestWpLead['correo'] ?? '',
  $plannerLatestWpLead['email_address'] ?? '',
  plannerProfileFirstNonEmptyFromRows($plannerWpLeadRows, 'email'),
  plannerProfileFirstNonEmptyFromRows($plannerWpLeadRows, 'correo'),
  plannerProfileFirstNonEmptyFromRows($plannerWpLeadRows, 'email_address'),
  plannerProfileFirstNonEmptyFromRows($coordinators, 'correo'),
  $plannerLatestContactForm['email_address'] ?? '',
  $plannerLatestContactForm['correo'] ?? '',
  $plannerLatestContactForm['email'] ?? ''
);
$plannerPhone = plannerProfileFirstNonEmpty(
  $planner['phone'] ?? '',
  $planner['telefono'] ?? '',
  $planner['telephone'] ?? '',
  $planner['contacto_celular'] ?? '',
  $plannerLatestWpLead['phone'] ?? '',
  $plannerLatestWpLead['telefono'] ?? '',
  $plannerLatestWpLead['telephone'] ?? '',
  $plannerLatestWpLead['contacto_celular'] ?? '',
  plannerProfileFirstNonEmptyFromRows($plannerWpLeadRows, 'phone'),
  plannerProfileFirstNonEmptyFromRows($plannerWpLeadRows, 'telefono'),
  plannerProfileFirstNonEmptyFromRows($plannerWpLeadRows, 'telephone'),
  plannerProfileFirstNonEmptyFromRows($plannerWpLeadRows, 'contacto_celular'),
  plannerProfileFirstNonEmptyFromRows($coordinators, 'telefono'),
  $plannerLatestContactForm['telephone'] ?? '',
  $plannerLatestContactForm['telefono'] ?? '',
  $plannerLatestContactForm['phone'] ?? '',
  $plannerLatestContactForm['contacto_celular'] ?? ''
);
$plannerContactChannel = plannerProfileFirstNonEmpty(
  $planner['first_contact_channel'] ?? '',
  $plannerLatestWpLead['first_contact_channel'] ?? '',
  $plannerLatestContactForm['first_contact_channel'] ?? '',
  $planner['platform'] ?? '',
  $plannerLatestWpLead['platform'] ?? '',
  $planner['form_name'] ?? '',
  $plannerLatestWpLead['form_name'] ?? '',
  $planner['campaign_name'] ?? '',
  $plannerLatestWpLead['campaign_name'] ?? ''
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($plannerName, ENT_QUOTES, 'UTF-8'); ?> - CRM</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #f8fafc;
    --surface: #ffffff;
    --border: #e2e8f0;
    --text-primary: #1a1d23;
    --text-secondary: #64748b;
    --text-muted: #9ca3af;
    --accent: #111827;
    --green: #10b981;
    --green-bg: #dcfce7;
    --orange: #f59e0b;
    --orange-bg: #fef3c7;
    --pending-bg: #f8fafc;
    --pending-text: #6b7280;
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: #f8fafc;
    color: var(--text-primary);
    min-height: 100vh;
    padding: 24px 12px;
    font-size: 14px;
    line-height: 1.5;
  }

  .page {
    max-width: 100%;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-top: 40px;
  }

  .header, .stats, .card {
    background: var(--surface);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.2s ease;
  }

  .card {
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07), 0 1px 2px rgba(0, 0, 0, 0.04);
     margin-bottom: 0rem !important;
  }

  .card:hover {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.06);
  }

  .header {
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: #ffffff;
    border: 1px solid #e2e5ea;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07), 0 1px 2px rgba(0, 0, 0, 0.04);
    position: relative;
    overflow: hidden;
    min-height: 82px;
  }

  .header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #10b981 0%, #059669 50%, #10b981 100%);
    opacity: 1;
  }

  .header-left {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .avatar, .coord-avatar {
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
    transition: all 0.3s ease;
  }

  .avatar {
    width: 48px;
    height: 48px;
    background: #d1fae5;
    color: #065f46;
    font-size: 16px;
    box-shadow: none;
    border: 1px solid #e2e8f0;
  }

  .avatar:hover {
    opacity: 0.9;
  }

  .header-info h1 {
    font-size: 17px;
    font-weight: 700;
    letter-spacing: -0.3px;
    color: #1a1d23;
    margin-bottom: 0;
    line-height: 1.2;
  }

  .header-meta {
    font-size: 12px;
    color: #9ca3af;
    margin-top: 4px;
    font-weight: 500;
    line-height: 1.35;
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .badge {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid;
    letter-spacing: 0;
    text-transform: none;
    transition: all 0.15s ease;
    box-shadow: none;
  }

  .badge:hover {
    opacity: 0.9;
  }

  .badge-green {
    background: #dcfce7;
    color: #166534;
    border-color: #bbf7d0;
  }

  .badge-orange {
    background: #fef3c7;
    color: #92400e;
    border-color: #fde68a;
  }

  .btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    font-family: inherit;
    transition: all 0.15s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: none;
  }

  .btn:hover { 
    opacity: 0.9;
  }

  .btn:active {
    transform: translateY(0);
  }

  .btn-dark {
    background: #111827;
    color: #fff;
  }

  .btn-dark:hover {
    background: #374151;
  }

  .btn-outline {
    background: transparent;
    color: #1a1d23;
    border: 1.5px solid #e2e5ea;
  }

  .btn-outline:hover {
    background: #f4f5f7;
    border-color: #c5cad4;
  }

  .btn-sm {
    padding: 5px 12px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
    box-shadow: none;
  }

  .btn-sm:hover {
    opacity: 0.9;
  }

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
  }

  .stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 16px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.07), 0 1px 2px rgba(0, 0, 0, 0.04);
    transition: all 0.2s ease;
  }

  .stat-card:hover {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.06);
  }

  .stat-label {
    font-size: 13px;
    font-weight: 500;
    letter-spacing: 0;
    text-transform: none;
    color: #64748b;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .stat-value {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: 0;
    line-height: 1;
    color: #1e293b;
  }

  .stat-sub {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 4px;
    font-weight: 400;
  }

  .main-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    align-items: stretch;
    min-height: clamp(680px, calc(100vh - 210px), 980px);
  }

  .left-col, .right-col {
    display: grid;
    gap: 16px;
    min-height: 0;
  }

  .left-col {
    grid-template-rows: repeat(3, minmax(0, 1fr));
  }

  .right-col {
    grid-template-rows: repeat(2, minmax(0, 1fr));
  }

  .dashboard-card {
    display: flex;
    flex-direction: column;
    min-height: 0;
  }

  .dashboard-card .card-body,
  .dashboard-card .coord-body {
    flex: 1;
    min-height: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }

  .dashboard-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 4px;
  }

  .dashboard-scroll.max-four-items {
    flex: 1;
    min-height: 0;
  }

  .events-full-width {
    width: 100%;
  }

  .card-header, .coord-header {
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e2e8f0;
    background: transparent;
  }

  .card-title {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    letter-spacing: 0;
    text-transform: none;
  }

  .card-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .card-body, .coord-body {
    padding: 12px 16px;
  }

  .field-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e2e8f0;
    gap: 16px;
    transition: all 0.15s ease;
  }

  .field-row:hover {
    background: #f8fafc;
  }

  .field-row:last-child { border-bottom: none; }

  .field-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
    letter-spacing: 0;
  }

  .field-value {
    font-size: 13px;
    font-weight: 500;
    color: #1e293b;
    text-align: right;
  }

  .status-badge {
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    text-transform: none;
    letter-spacing: 0;
    box-shadow: none;
    transition: all 0.15s ease;
  }

  .status-badge:hover {
    opacity: 0.9;
  }

  .status-afianzado {
    background: #dcfce7;
    color: #166534;
  }

  .status-pendiente {
    background: #fef3c7;
    color: #92400e;
  }

  .status-rechazado {
    background: #fee2e2;
    color: #b91c1c;
  }

  .status-inminente {
    background: #ede9fe;
    color: #5b21b6;
  }

  .coord-list {
    display: flex;
    flex-direction: column;
    gap: 0;
  }

  .coord-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid #e2e8f0;
    transition: background 0.15s ease;
  }

  .coord-main {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
  }

  .coord-item:hover {
    background: #f8fafc;
  }

  .coord-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
  }

  .coord-avatar {
    width: 36px;
    height: 36px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    box-shadow: none;
    border: 1px solid #e2e8f0;
  }

  .coord-name {
    font-size: 13px;
    font-weight: 500;
    color: #1e293b;
  }

  .coord-meta {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 2px;
    font-weight: 400;
  }

  .appointment-list {
    display: flex;
    flex-direction: column;
    gap: 0;
  }

  .appointment-item {
    padding: 10px 0;
    border-bottom: 1px solid #e2e8f0;
    transition: background 0.15s ease;
  }

  .appointment-item:hover {
    background: #f8fafc;
  }

  .appointment-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
  }

  .appointment-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 4px;
  }

  .appointment-date {
    font-size: 13px;
    font-weight: 500;
    color: #1e293b;
  }

  .appointment-meta,
  .appointment-comment {
    font-size: 12px;
    color: #64748b;
    font-weight: 400;
  }

  .appointment-comment {
    margin-top: 6px;
    padding: 8px 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    line-height: 1.5;
  }

  .events-table-wrap {
    width: 100%;
    overflow-x: auto;
  }

  .events-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1350px;
  }

  .events-table th,
  .events-table td {
    padding: 9px 10px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 12px;
    vertical-align: top;
    white-space: nowrap;
  }

  .events-table th {
    background: #f8fafc;
    color: #334155;
    font-weight: 700;
    position: sticky;
    top: 0;
    z-index: 1;
  }

  .interaction-type {
    font-size: 12px;
    font-weight: 600;
    color: #1e293b;
  }

  .interaction-type-badge,
  .outcome-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 999px;
    padding: 4px 9px;
    border: 1px solid transparent;
  }

  .type-llamada { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
  .type-whatsapp { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
  .type-email { background: #ede9fe; color: #6d28d9; border-color: #ddd6fe; }
  .type-reunion { background: #fef3c7; color: #92400e; border-color: #fde68a; }
  .type-nota { background: #e2e8f0; color: #334155; border-color: #cbd5e1; }
  .type-sinrespuesta { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }
  .type-default { background: #f8fafc; color: #475569; border-color: #e2e8f0; }

  .outcome-positivo { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
  .outcome-neutral { background: #f1f5f9; color: #334155; border-color: #e2e8f0; }
  .outcome-negativo { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
  .outcome-cerrar { background: #ffedd5; color: #c2410c; border-color: #fed7aa; }
  .outcome-seguimiento { background: #fef3c7; color: #92400e; border-color: #fde68a; }
  .outcome-sinrespuesta { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }
  .outcome-default { background: #f8fafc; color: #475569; border-color: #e2e8f0; }

  .interaction-note {
    font-size: 12px;
    color: #475569;
    margin-top: 6px;
    line-height: 1.45;
    white-space: pre-wrap;
  }

  .schedule-modal .modal-content {
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
    border: none;
  }

  .schedule-modal .modal-header {
    background: #eee8dc;
    border-bottom: none;
    padding: 22px 28px;
  }

  .schedule-modal .modal-header h5 {
    font-size: 1.2rem;
    font-weight: 700;
    color: #464646;
    margin: 0;
  }

  .schedule-modal .modal-body {
    padding: 24px 28px;
  }

  .schedule-modal .modal-footer {
    padding: 18px 28px 24px;
    border-top: 1px solid #eee;
  }

  .schedule-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .schedule-field {
    margin-bottom: 14px;
  }

  .schedule-field label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: #555;
    margin-bottom: 4px;
  }

  .schedule-field input,
  .schedule-field select,
  .schedule-field textarea {
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 9px 12px;
    font-size: 0.9rem;
    color: #333;
    outline: none;
    transition: border-color 0.2s;
    background: #fff;
  }

  .schedule-field textarea {
    resize: vertical;
    min-height: 100px;
  }

  .schedule-engagement-btns {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 8px;
  }

  .interaction-chip-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
  }

  .interaction-chip-group.is-invalid {
    padding: 10px;
    border: 1px solid #dc2626;
    border-radius: 12px;
    background: rgba(220, 38, 38, 0.04);
  }

  .schedule-engagement-btns.is-invalid {
    padding: 10px;
    border: 1px solid #dc2626;
    border-radius: 12px;
    background: rgba(220, 38, 38, 0.04);
  }

  .schedule-engagement-card {
    border: 1px solid #ddd;
    border-radius: 12px;
    background: #fff;
    padding: 16px 14px;
    min-height: 96px;
    cursor: pointer;
    text-align: left;
    transition: all 0.18s ease;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  }

  .schedule-engagement-card:hover {
    border-color: #464646;
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
  }

  .schedule-engagement-card.active {
    border-color: #464646;
    background: #f5f1e8;
    box-shadow: 0 10px 24px rgba(70, 70, 70, 0.12);
  }

  .planner-interaction-type-btn {
    min-height: auto;
    padding: 7px 12px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    box-shadow: none;
  }

  .planner-interaction-type-btn:hover {
    border-color: #60a5fa;
    background: #eff6ff;
    transform: none;
    box-shadow: none;
  }

  .planner-interaction-type-btn.active {
    border-color: #3b82f6;
    background: #eff6ff;
    box-shadow: none;
  }

  .planner-interaction-type-btn .schedule-engagement-name {
    margin-bottom: 0;
    font-size: 0.86rem;
    color: #334155;
    font-weight: 600;
  }

  .planner-action-outcome-btn {
    min-height: auto;
    padding: 7px 12px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    box-shadow: none;
    border-color: #cbd5e1;
    background: #f8fafc;
  }

  .planner-action-outcome-btn:hover {
    transform: none;
    box-shadow: none;
    filter: brightness(0.98);
  }

  .planner-action-outcome-btn.active {
    box-shadow: inset 0 0 0 1px currentColor;
  }

  .planner-action-outcome-btn .schedule-engagement-name {
    margin-bottom: 0;
    font-size: 0.86rem;
    font-weight: 600;
  }

  .action-chip--positivo { background: #f0fdf4; border-color: #86efac; color: #166534; }
  .action-chip--neutral { background: #f5f3ff; border-color: #c4b5fd; color: #5b21b6; }
  .action-chip--negativo { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }
  .action-chip--cerrar { background: #fff7ed; border-color: #fdba74; color: #c2410c; }
  .action-chip--seguimiento { background: #f8fafc; border-color: #cbd5e1; color: #334155; }
  .action-chip--sinrespuesta { background: #fefce8; border-color: #fcd34d; color: #a16207; }

  .interaction-chip-icon {
    font-size: 0.78rem;
    line-height: 1;
  }

  .schedule-engagement-icon {
    display: block;
    font-size: 1.4rem;
    margin-bottom: 8px;
  }

  .schedule-engagement-name {
    display: block;
    font-size: 0.95rem;
    font-weight: 700;
    color: #464646;
    margin-bottom: 6px;
  }

  .schedule-engagement-desc {
    display: block;
    font-size: 0.8rem;
    line-height: 1.45;
    color: #777;
  }

  .schedule-field input:focus,
  .schedule-field select:focus,
  .schedule-field textarea:focus {
    border-color: #464646;
  }

  .is-invalid {
    border-color: #dc2626 !important;
  }

  .empty-card {
    min-height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #9ca3af;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px dashed #cbd5e1;
    font-size: 12px;
    font-weight: 400;
  }

  .not-found {
    padding: 24px;
    text-align: center;
  }

  @media (max-width: 900px) {
    .stats, .main-grid {
      grid-template-columns: 1fr;
    }

    .main-grid {
      min-height: auto;
    }

    .left-col,
    .right-col {
      grid-template-rows: none;
    }

    .stat-item {
      border-right: none;
      border-bottom: 1px solid var(--border);
      padding: 0 0 16px;
      margin-bottom: 16px;
    }

    .stat-item:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-left: 0;
    }

    .stat-item:not(:first-child):not(:last-child) {
      padding-left: 0;
    }

    .header {
      flex-direction: column;
      align-items: flex-start;
    }

    .header-right {
      justify-content: flex-start;
    }

    .schedule-grid,
    .schedule-engagement-btns {
      grid-template-columns: 1fr;
    }
  }

  .pp-completed-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
    font-size: 11px;
    font-weight: 700;
    margin-left: 6px;
  }

  .pp-btn-complete {
    margin-left: 6px;
    padding: 2px 8px;
    font-size: 11px;
    border-radius: 999px;
    cursor: pointer;
    background: #f0fdf4;
    border: 1px solid #86efac;
    color: #166534;
    font-weight: 600;
  }

  .pp-btn-complete:hover {
    background: #dcfce7;
  }
</style>
</head>
<body>
<div class="page">
  <?php if (!$planner): ?>
    <div class="card not-found">
      <div>
        <h1 style="font-size: 20px; margin-bottom: 8px;">Wedding Planner no encontrado</h1>
        <p style="color: #6b7280; margin-bottom: 16px;">No se encontró un registro válido para el ID solicitado.</p>
        <a href="consulta_wp.php" class="btn btn-dark">Volver a Wedding Planners</a>
      </div>
    </div>
  <?php else: ?>
  <div class="header" style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:16px;">
    <div class="header-left">
      <div class="avatar"><?php echo htmlspecialchars($plannerInitials, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="header-info">
        <h1><?php echo htmlspecialchars($plannerName, ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="header-meta">
          ID <?php echo intval($planner['id'] ?? 0); ?> &middot; <?php echo $coordinatorCount; ?> coordinador<?php echo $coordinatorCount === 1 ? '' : 'es'; ?> &middot; Registro: <?php echo htmlspecialchars(plannerProfileFormatDate($planner['created_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center;">
      <div style="display:flex;align-items:center;gap:10px;padding:10px 24px;background:rgba(255,255,255,0.55);border:1px solid rgba(0,0,0,0.08);border-radius:12px;backdrop-filter:blur(4px);">
        <div style="display:flex;flex-direction:column;align-items:center;padding-right:14px;border-right:1px solid #e5e7eb;">
          <span style="font-size:0.68rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#9ca3af;">Vendedor asignado</span>
          <span id="plannerHeaderVendorName" style="font-size:0.97rem;font-weight:700;color:#1e293b;margin-top:2px;"><?php echo $plannerVendedorName !== '' ? htmlspecialchars($plannerVendedorName, ENT_QUOTES, 'UTF-8') : 'Sin asignar'; ?></span>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;padding-left:4px;">
          <span style="font-size:0.68rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#9ca3af;">Afianzado</span>
          <span id="plannerHeaderAfianzadoBadge" style="font-size:0.97rem;font-weight:700;margin-top:2px;<?php echo intval($planner['afianzado'] ?? 0) === 1 ? 'color:#15803d;' : 'color:#b45309;'; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      </div>
    </div>
    <div class="header-right">
      <a href="consulta_wp.php" class="btn btn-outline">Volver</a>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Coordinadores</div>
      <div class="stat-value"><?php echo $coordinatorCount; ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Monto de venta Total</div>
      <div class="stat-value" style="font-size: 22px;"><?php echo htmlspecialchars('$' . number_format($plannerTotalQuotedAmount, 2), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Ciudad</div>
      <div class="stat-value"><?php echo htmlspecialchars($plannerCity !== '' ? $plannerCity : 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Correo</div>
      <div class="stat-value" style="font-size: 16px;"><?php echo htmlspecialchars($plannerEmail !== '' ? $plannerEmail : 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Teléfono</div>
      <div class="stat-value" style="font-size: 18px;"><?php echo htmlspecialchars($plannerPhone !== '' ? $plannerPhone : 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="stat-sub"><?php echo htmlspecialchars($plannerContactChannel !== '' ? $plannerContactChannel : 'Sin canal', ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </div>

  <div class="main-grid">
    <div class="left-col">
      <div class="card dashboard-card">
        <div class="card-header">
          <span class="card-title">Datos del planner</span>
          <div class="card-actions">
            <button class="btn btn-outline btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#editarDatosPlannerModal">Editar</button>
          </div>
        </div>
        <div class="card-body">
          <div class="field-row">
            <span class="field-label">Nombre</span>
            <span class="field-value"><?php echo htmlspecialchars($plannerCompany !== '' ? $plannerCompany : $plannerName, ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Ciudad</span>
            <span class="field-value"><?php echo htmlspecialchars($plannerCity !== '' ? $plannerCity : 'Sin dato', ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Correo</span>
            <span class="field-value"><?php echo htmlspecialchars($plannerEmail !== '' ? $plannerEmail : 'Sin dato', ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Teléfono</span>
            <span class="field-value"><?php echo htmlspecialchars($plannerPhone !== '' ? $plannerPhone : 'Sin dato', ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="field-row">
            <span class="field-label">Estado</span>
            <span class="field-value"><span class="status-badge <?php echo intval($planner['afianzado'] ?? 0) === 1 ? 'status-afianzado' : 'status-pendiente'; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span></span>
          </div>
          <div class="field-row">
            <span class="field-label">Vendedor/a</span>
            <span class="field-value" id="plannerVendorFieldValue"><?php echo htmlspecialchars($plannerVendedorName !== '' ? $plannerVendedorName : 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
        </div>
      </div>

      <div class="card dashboard-card">
        <div class="card-header">
          <span class="card-title">Citas</span>
          <div class="card-actions">
            <a class="btn btn-outline btn-sm" href="post_qualified_wedding_planners.php">Ver todas las citas</a>
            <button class="btn btn-dark btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#agendarCitaPlannerModal">Agendar cita</button>
          </div>
        </div>
        <div class="card-body">
          <div class="dashboard-scroll">
            <?php if (!empty($appointments)): ?>
              <div class="appointment-list">
                <?php foreach ($appointments as $appointment): ?>
                <?php $sellerName = trim((string) (($appointment['vendedor_nombre'] ?? '') . ' ' . ($appointment['vendedor_apepat'] ?? ''))); ?>
                <?php
                  $appointmentEventStatusRaw = trim((string) ($appointment['evento_estatus'] ?? ''));
                  $appointmentHasEvent = $appointmentEventStatusRaw !== '' && $appointmentEventStatusRaw !== '0';
                  $appointmentCalStatus = is_numeric($appointment['estatus'] ?? null) ? intval($appointment['estatus']) : 0;
                  $appointmentPayload = [
                    'calendario_id' => intval($appointment['calendario_id'] ?? 0),
                    'evento_wp_id' => intval($appointment['evento_wp_id'] ?? 0),
                    'id_asesor' => intval($appointment['vendedor_id'] ?? 0),
                    'city' => trim((string) ($appointment['cliente_city'] ?? '')),
                  ];
                ?>
                <div class="appointment-item" data-calendario-id="<?php echo intval($appointment['calendario_id'] ?? 0); ?>" data-appointment-payload="<?php echo htmlspecialchars(json_encode($appointmentPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="coord-item" style="padding: 0; border-bottom: none;">
                    <div class="coord-main">
                      <div class="coord-avatar"><?php echo htmlspecialchars(plannerProfileInitials($sellerName !== '' ? $sellerName : 'Cita'), ENT_QUOTES, 'UTF-8'); ?></div>
                      <div>
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                          <div class="coord-name"><?php echo htmlspecialchars(plannerProfileFormatDateTime($appointment['fecha'] ?? '', $appointment['hora'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                          <span class="js-cita-status-badge status-badge <?php echo plannerProfileCalendarStatusClass($appointment['estatus'] ?? null); ?>"><?php echo htmlspecialchars(plannerProfileCalendarStatusLabel($appointment['estatus'] ?? null), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="coord-meta">Vendedor: <?php echo htmlspecialchars($sellerName !== '' ? $sellerName : 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?> · Evento WP #<?php echo intval($appointment['evento_wp_id'] ?? 0); ?></div>
                      </div>
                    </div>
                    <div class="card-actions">
                      <?php if ($appointmentHasEvent): ?>
                        <span class="status-badge status-afianzado">Convertida a evento</span>
                      <?php else: ?>
                        <?php if ($appointmentCalStatus !== 3): ?>
                          <button
                            class="btn btn-outline btn-sm js-abrir-cambiar-estatus"
                            type="button"
                            data-calendario-id="<?php echo intval($appointment['calendario_id'] ?? 0); ?>"
                            data-estatus-actual="<?php echo $appointmentCalStatus; ?>"
                          >Cambiar estatus</button>
                        <?php endif; ?>
                        <?php if ($appointmentCalStatus === 1): ?>
                          <button
                            class="btn btn-dark btn-sm js-pasar-evento"
                            type="button"
                            data-appointment="<?php echo htmlspecialchars(json_encode($appointmentPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#pasarEventoModal"
                          >Pasar a evento</button>
                        <?php endif; ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php if (trim((string) ($appointment['comentario'] ?? '')) !== ''): ?>
                    <div class="appointment-comment"><?php echo nl2br(htmlspecialchars($appointment['comentario'], ENT_QUOTES, 'UTF-8')); ?></div>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-card">Este wedding planner aún no tiene citas registradas.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card dashboard-card">
        <div class="coord-header">
          <span class="card-title">Coordinadores asignados</span>
          <button class="btn btn-dark btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#agregarCoordinadorModal">+ Agregar</button>
        </div>
        <div class="coord-body">
          <div class="dashboard-scroll">
            <?php if (!empty($coordinators)): ?>
              <div class="coord-list">
                <?php foreach ($coordinators as $coordinator): ?>
                <?php $coordinatorName = trim((string) ($coordinator['nombre'] ?? '')); ?>
                <?php
                  $coordinatorPayload = [
                    'id' => intval($coordinator['id'] ?? 0),
                    'nombre' => $coordinatorName,
                    'correo' => trim((string) ($coordinator['correo'] ?? '')),
                    'telefono' => trim((string) ($coordinator['telefono'] ?? '')),
                  ];
                ?>
                <div class="coord-item">
                  <div class="coord-main">
                    <div class="coord-avatar"><?php echo htmlspecialchars(plannerProfileInitials($coordinatorName), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div>
                      <div class="coord-name"><?php echo htmlspecialchars($coordinatorName !== '' ? $coordinatorName : ('Coordinador #' . intval($coordinator['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></div>
                      <div class="coord-meta">Registrado: <?php echo htmlspecialchars(plannerProfileFormatDate($coordinator['fecha_creacion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                  </div>
                  <button
                    class="btn btn-outline btn-sm js-coordinator-details"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#agregarCoordinadorModal"
                    data-coordinator="<?php echo htmlspecialchars(json_encode($coordinatorPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                  >Ver detalles</button>
                </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-card">Este wedding planner aún no tiene coordinadores registrados.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="right-col">
      <div class="card dashboard-card">
        <div class="card-header">
          <span class="card-title">Interacciones</span>
          <div class="card-actions">
            <button class="btn btn-dark btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#registrarInteraccionModal">+ Registrar interacción</button>
          </div>
        </div>
        <div class="card-body">
          <div class="dashboard-scroll max-four-items">
            <?php if (!empty($interactions)): ?>
              <div class="appointment-list">
                <?php foreach ($interactions as $interaction): ?>
                <?php
                  $interactionType = trim((string) ($interaction['interaction_type'] ?? ''));
                  $interactionNotes = trim((string) ($interaction['notes'] ?? ''));
                  $nextAction = trim((string) ($interaction['next_action'] ?? ''));
                  $nextDate = trim((string) ($interaction['next_action_date'] ?? ''));
                  $actionOutcome = trim((string) ($interaction['outcome'] ?? ''));
                  if ($interactionType === '') {
                      $interactionType = 'Sin respuesta';
                  }
                ?>
                <div class="appointment-item">
                  <div class="appointment-top">
                    <span class="interaction-type-badge <?php echo plannerProfileInteractionTypeClass($interactionType); ?>">
                      <span><?php echo htmlspecialchars(plannerProfileInteractionTypeIcon($interactionType), ENT_QUOTES, 'UTF-8'); ?></span>
                      <span><?php echo htmlspecialchars($interactionType, ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                    <?php if ($actionOutcome !== ''): ?>
                      <span class="outcome-badge <?php echo plannerProfileOutcomeClass($actionOutcome); ?>">
                        <span><?php echo htmlspecialchars(plannerProfileOutcomeIcon($actionOutcome), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo htmlspecialchars($actionOutcome, ENT_QUOTES, 'UTF-8'); ?></span>
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="appointment-meta"><?php echo htmlspecialchars(plannerProfileInteractionDateTime($interaction['interaction_date'] ?? '', $interaction['interaction_time'] ?? '', $interaction['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php if ($interactionNotes !== ''): ?>
                    <div class="interaction-note"><?php echo nl2br(htmlspecialchars($interactionNotes, ENT_QUOTES, 'UTF-8')); ?></div>
                  <?php endif; ?>
                  <?php if ($nextAction !== '' || $nextDate !== ''): ?>
                    <div class="appointment-meta">Próxima acción: <?php echo htmlspecialchars($nextAction !== '' ? $nextAction : 'Seguimiento', ENT_QUOTES, 'UTF-8'); ?><?php echo $nextDate !== '' ? ' · ' . htmlspecialchars(plannerProfileFormatDate($nextDate), ENT_QUOTES, 'UTF-8') : ''; ?>
                      <?php if (intval($interaction['next_action_completed'] ?? 0) === 1): ?>
                        <span class="pp-completed-badge">✓ Completada</span>
                      <?php elseif ($nextAction !== '' || $nextDate !== ''): ?>
                        <button type="button" class="pp-btn-complete" data-interaction-id="<?php echo intval($interaction['id']); ?>">Marcar completada</button>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-card">Sin interacciones registradas.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card events-full-width">
    <div class="card-header">
      <span class="card-title">Eventos</span>
      <div class="card-actions">
        <button class="btn btn-dark btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#crearEventoSinCitaModal">Crear evento</button>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($plannerEvents)): ?>
        <div class="events-table-wrap">
          <table class="events-table">
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
                <th>Monto de la venta</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($plannerEvents as $eventItem): ?>
                <?php
                  $eventStatus = plannerProfileEventStatusMeta($eventItem['estatus'] ?? '', $eventItem['contact_form_cliente'] ?? null);
                  $eventNextStep = plannerProfileNextEventStatusMeta($eventItem['estatus'] ?? '', $eventItem['contact_form_cliente'] ?? null);
                  $eventActionPayload = [
                    'event_id' => intval($eventItem['id'] ?? 0),
                    'action_mode' => (string) ($eventNextStep['mode'] ?? 'direct'),
                    'target_status' => intval($eventNextStep['target'] ?? 0),
                  ];
                  $eventEditPayload = [
                    'id' => intval($eventItem['id'] ?? 0),
                    'wedding_planner_id' => intval($eventItem['wedding_planner_id'] ?? 0),
                    'id_coordinador' => intval($eventItem['id_coordinador'] ?? 0),
                    'id_asesor' => intval($eventItem['id_asesor'] ?? 0),
                    'id_paquete' => intval($eventItem['id_paquete'] ?? 0),
                    'novios' => trim((string) ($eventItem['novios'] ?? '')),
                    'city' => trim((string) ($eventItem['ciudad_novios'] ?? $eventItem['city'] ?? '')),
                    'lugar' => trim((string) ($eventItem['lugar'] ?? '')),
                    'fecha' => trim((string) ($eventItem['fecha_evento'] ?? '')),
                    'monto_venta' => $eventItem['monto_venta'] !== null ? (string) $eventItem['monto_venta'] : '',
                  ];
                ?>
                <tr>
                  <td><?php echo intval($eventItem['id'] ?? 0); ?></td>
                  <td><?php echo htmlspecialchars(plannerProfileFormatDateTime($eventItem['fecha_registro'] ?? '', ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(trim((string) ($eventItem['wedding_planner'] ?? '')) !== '' ? $eventItem['wedding_planner'] : ('WP #' . intval($eventItem['wedding_planner_id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(trim((string) ($eventItem['novios'] ?? '')) !== '' ? $eventItem['novios'] : 'Sin novios', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo intval($eventItem['afianzado'] ?? 0) === 1 ? 'Sí' : 'No'; ?></td>
                  <td><span class="status-badge <?php echo htmlspecialchars($eventStatus['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($eventStatus['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                  <td><?php echo htmlspecialchars(plannerProfileFormatDateTime($eventItem['fecha_actualizacion_estatus'] ?? '', ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(trim((string) ($eventItem['coordinador_nombre'] ?? '')) !== '' ? $eventItem['coordinador_nombre'] : 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(trim((string) ($eventItem['lugar'] ?? '')) !== '' ? $eventItem['lugar'] : 'Sin lugar', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(plannerProfileFormatDateTime($eventItem['fecha_evento'] ?? '', ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(trim((string) ($eventItem['asesor_nombre'] ?? '')) !== '' ? $eventItem['asesor_nombre'] : 'Sin asignar', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(trim((string) ($eventItem['paquete_nombre'] ?? '')) !== '' ? $eventItem['paquete_nombre'] : 'Sin paquete', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <?php
                      $eventSaleAmount = is_numeric($eventItem['monto_venta'] ?? null) ? (float) $eventItem['monto_venta'] : null;
                      echo htmlspecialchars($eventSaleAmount !== null ? ('$' . number_format($eventSaleAmount, 2)) : 'Sin monto', ENT_QUOTES, 'UTF-8');
                    ?>
                  </td>
                  <td>
                    <button
                      class="btn btn-outline btn-sm js-edit-event"
                      type="button"
                      data-event-edit="<?php echo htmlspecialchars(json_encode($eventEditPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                      data-bs-toggle="modal"
                      data-bs-target="#editarEventoPlannerModal"
                    >Editar</button>
                    <?php if (!empty($eventNextStep['can_change'])): ?>
                      <button
                        class="btn btn-dark btn-sm js-event-status-change"
                        type="button"
                        data-event-status="<?php echo htmlspecialchars(json_encode($eventActionPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                      ><?php echo htmlspecialchars($eventNextStep['button_label'], ENT_QUOTES, 'UTF-8'); ?></button>
                    <?php else: ?>
                      <span class="appointment-meta">Sin cambios</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-card">Este wedding planner aún no tiene eventos registrados.</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($planner): ?>
<div class="modal fade schedule-modal" id="agregarCoordinadorModal" tabindex="-1" aria-labelledby="agregarCoordinadorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="agregarCoordinadorModalLabel">Agregar coordinador</h5>
          <div id="agregarCoordinadorModalSubtitle" style="font-size: 0.85rem; color: #666; margin-top: 4px;">Asigna un coordinador a <?php echo htmlspecialchars($plannerName, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="plannerCoordinatorForm">
          <input type="hidden" id="plannerCoordinatorId" name="id" value="">
          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="plannerCoordinatorName">Nombre <span style="color:#b45309;">*</span></label>
              <input type="text" id="plannerCoordinatorName" name="nombre" placeholder="Nombre del coordinador">
              <div class="invalid-feedback">Escribe el nombre del coordinador</div>
            </div>
            <div class="schedule-field">
              <label for="plannerCoordinatorEmail">Correo <span style="color:#b45309;">*</span></label>
              <input type="email" id="plannerCoordinatorEmail" name="correo" placeholder="correo@dominio.com">
              <div class="invalid-feedback">Escribe un correo valido</div>
            </div>
          </div>
          <div class="schedule-field">
            <label for="plannerCoordinatorPhone">Telefono <span style="color:#b45309;">*</span></label>
            <input type="text" id="plannerCoordinatorPhone" name="telefono" placeholder="Telefono de contacto">
            <div class="invalid-feedback">Escribe el telefono del coordinador</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-dark" id="btnGuardarCoordinator">Guardar coordinador</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade schedule-modal" id="editarDatosPlannerModal" tabindex="-1" aria-labelledby="editarDatosPlannerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="editarDatosPlannerModalLabel">Editar datos del planner</h5>
          <div style="font-size: 0.85rem; color: #666; margin-top: 4px;">Actualiza la informacion principal de <?php echo htmlspecialchars($plannerName, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="plannerProfileEditForm">
          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="plannerEditCompany">Nombre del wp</label>
              <input type="text" id="plannerEditCompany" name="empresa_wp" value="<?php echo htmlspecialchars($plannerCompany, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre de empresa o planner">
            </div>
            <div class="schedule-field">
              <label for="plannerEditCity">Ciudad</label>
              <input type="text" id="plannerEditCity" name="city" value="<?php echo htmlspecialchars($plannerCity, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ciudad">
            </div>
          </div>
          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="plannerEditEmail">Correo</label>
              <input type="email" id="plannerEditEmail" name="email" value="<?php echo htmlspecialchars($plannerEmail, ENT_QUOTES, 'UTF-8'); ?>" placeholder="correo@dominio.com">
            </div>
            <div class="schedule-field">
              <label for="plannerEditPhone">Telefono</label>
              <input type="text" id="plannerEditPhone" name="phone" value="<?php echo htmlspecialchars($plannerPhone, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Telefono">
            </div>
          </div>
          <div class="schedule-field">
            <label for="plannerEditAfianzado">Estado</label>
            <select id="plannerEditAfianzado" name="afianzado">
              <option value="0" <?php echo intval($planner['afianzado'] ?? 0) === 0 ? 'selected' : ''; ?>>No afianzado</option>
              <option value="3" <?php echo intval($planner['afianzado'] ?? 0) === 3 ? 'selected' : ''; ?>>Nuevo</option>
              <option value="2" <?php echo intval($planner['afianzado'] ?? 0) === 2 ? 'selected' : ''; ?>>En proceso</option>
              <option value="1" <?php echo intval($planner['afianzado'] ?? 0) === 1 ? 'selected' : ''; ?>>Afianzado</option>
            </select>
          </div>
          <div class="schedule-field">
            <label for="plannerEditVendor">Vendedor/a asignado/a</label>
            <select id="plannerEditVendor" name="id_vendedor_asignado">
              <option value="0">Sin asignar</option>
              <?php foreach ($agents as $agent): ?>
                <?php $agentName = trim((string)(($agent['nombre'] ?? '') . ' ' . ($agent['apepat'] ?? ''))); ?>
                <option value="<?php echo intval($agent['id'] ?? 0); ?>" <?php echo intval($agent['id'] ?? 0) === $plannerVendedorId ? 'selected' : ''; ?>><?php echo htmlspecialchars($agentName !== '' ? $agentName : ('Usuario #' . intval($agent['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
            <div style="font-size:0.8rem;color:#6b7280;margin-top:4px;">Solo actualiza el vendedor del planner. No modifica los vendedores de sus citas.</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-dark" id="btnGuardarPlannerProfile">Guardar cambios</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade schedule-modal" id="registrarInteraccionModal" tabindex="-1" aria-labelledby="registrarInteraccionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="registrarInteraccionModalLabel">Nueva interaccion</h5>
          <div style="font-size: 0.85rem; color: #666; margin-top: 4px;">Registra contacto para <?php echo htmlspecialchars($plannerName, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="plannerInteractionForm">
          <div class="schedule-field">
            <label>Tipo de interaccion <span style="color:#b45309;">*</span></label>
            <input type="hidden" id="plannerInteractionType" name="interaction_type" value="">
            <div class="interaction-chip-group" id="plannerInteractionTypeGroup">
              <button type="button" class="schedule-engagement-card planner-interaction-type-btn" data-value="Llamada" aria-pressed="false">
                <span class="interaction-chip-icon">📞</span>
                <span class="schedule-engagement-name">Llamada</span>
              </button>
              <button type="button" class="schedule-engagement-card planner-interaction-type-btn" data-value="WhatsApp" aria-pressed="false">
                <span class="interaction-chip-icon">💬</span>
                <span class="schedule-engagement-name">WhatsApp</span>
              </button>
              <button type="button" class="schedule-engagement-card planner-interaction-type-btn" data-value="Email" aria-pressed="false">
                <span class="interaction-chip-icon">✉️</span>
                <span class="schedule-engagement-name">Email</span>
              </button>
              <button type="button" class="schedule-engagement-card planner-interaction-type-btn" data-value="Reunion" aria-pressed="false">
                <span class="interaction-chip-icon">🤝</span>
                <span class="schedule-engagement-name">Reunion</span>
              </button>
              <button type="button" class="schedule-engagement-card planner-interaction-type-btn" data-value="Nota interna" aria-pressed="false">
                <span class="interaction-chip-icon">📝</span>
                <span class="schedule-engagement-name">Nota interna</span>
              </button>
              <button type="button" class="schedule-engagement-card planner-interaction-type-btn" data-value="Sin respuesta" aria-pressed="false">
                <span class="interaction-chip-icon">…</span>
                <span class="schedule-engagement-name">Sin respuesta</span>
              </button>
            </div>
            <div class="invalid-feedback" id="plannerInteractionTypeFeedback" style="display:none;">Selecciona el tipo de interaccion</div>
          </div>
          <div class="schedule-field">
            <label for="plannerInteractionNotes">Notas de la interaccion</label>
            <textarea id="plannerInteractionNotes" name="notes" placeholder="Describe lo que paso en la interaccion"></textarea>
          </div>

          <div style="border-top:1px solid #e5e7eb;margin:16px 0 12px;padding-top:14px;">
            <div style="font-size:0.78rem;font-weight:600;letter-spacing:0.07em;text-transform:uppercase;color:#9ca3af;margin-bottom:12px;">Seguimiento <span style="font-weight:400;text-transform:none;letter-spacing:0;">(opcional)</span></div>
            <div class="schedule-field">
              <label>Resultado</label>
              <input type="hidden" id="plannerActionOutcome" name="outcome" value="">
              <div class="interaction-chip-group" id="plannerActionOutcomeGroup">
                <button type="button" class="schedule-engagement-card planner-action-outcome-btn action-chip--positivo" data-value="Positivo" aria-pressed="false">
                  <span class="interaction-chip-icon">✅</span>
                  <span class="schedule-engagement-name">Positivo</span>
                </button>
                <button type="button" class="schedule-engagement-card planner-action-outcome-btn action-chip--neutral" data-value="Neutral" aria-pressed="false">
                  <span class="interaction-chip-icon">⚪</span>
                  <span class="schedule-engagement-name">Neutral</span>
                </button>
                <button type="button" class="schedule-engagement-card planner-action-outcome-btn action-chip--negativo" data-value="Negativo" aria-pressed="false">
                  <span class="interaction-chip-icon">❌</span>
                  <span class="schedule-engagement-name">Negativo</span>
                </button>
                <button type="button" class="schedule-engagement-card planner-action-outcome-btn action-chip--cerrar" data-value="Listo para cerrar" aria-pressed="false">
                  <span class="interaction-chip-icon">🔥</span>
                  <span class="schedule-engagement-name">Listo para cerrar</span>
                </button>
                <button type="button" class="schedule-engagement-card planner-action-outcome-btn action-chip--seguimiento" data-value="Requiere seguimiento" aria-pressed="false">
                  <span class="interaction-chip-icon">⏳</span>
                  <span class="schedule-engagement-name">Requiere seguimiento</span>
                </button>
                <button type="button" class="schedule-engagement-card planner-action-outcome-btn action-chip--sinrespuesta" data-value="Sin respuesta" aria-pressed="false">
                  <span class="interaction-chip-icon">…</span>
                  <span class="schedule-engagement-name">Sin respuesta</span>
                </button>
              </div>
              <div class="invalid-feedback" id="plannerActionOutcomeFeedback" style="display:none;">Selecciona un resultado</div>
            </div>
            <div class="schedule-grid">
              <div class="schedule-field">
                <label for="plannerActionText">Proxima accion programada</label>
                <input type="text" id="plannerActionText" name="next_action" placeholder="Ej. Llamar para confirmar">
                <div class="invalid-feedback">Escribe la proxima accion</div>
              </div>
              <div class="schedule-field">
                <label for="plannerActionDate">Fecha proxima accion</label>
                <input type="date" id="plannerActionDate" name="next_action_date">
                <div class="invalid-feedback">Selecciona la fecha</div>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-dark" id="btnGuardarPlannerInteraction">Registrar interacción</button>
      </div>
    </div>
  </div>
</div>


<div class="modal fade schedule-modal" id="agendarCitaPlannerModal" tabindex="-1" aria-labelledby="agendarCitaPlannerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="agendarCitaPlannerModalLabel">Agendar cita</h5>
          <div style="font-size: 0.85rem; color: #666; margin-top: 4px;"><?php echo htmlspecialchars($plannerName, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="plannerAppointmentForm">
          <div class="schedule-field">
            <label for="plannerAssignedVendor">Vendedor <span style="color:#b45309;">*</span></label>
            <select id="plannerAssignedVendor" name="override_vendedor_id">
              <option value="">Seleccionar...</option>
              <?php foreach ($agents as $agent): ?>
                <?php $agentName = trim((string) (($agent['nombre'] ?? '') . ' ' . ($agent['apepat'] ?? ''))); ?>
                <option value="<?php echo intval($agent['id'] ?? 0); ?>" <?php echo intval($agent['id'] ?? 0) === $plannerVendedorId ? 'selected' : ''; ?>><?php echo htmlspecialchars($agentName !== '' ? $agentName : ('Usuario #' . intval($agent['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="plannerDateAppointment">Día <span style="color:#b45309;">*</span></label>
              <input type="date" id="plannerDateAppointment" name="fecha_cliente">
            </div>
            <div class="schedule-field">
              <label for="plannerTimeAppointment">Hora <span style="color:#b45309;">*</span></label>
              <select id="plannerTimeAppointment" name="hora_cliente">
                <option value="">Selecciona una hora</option>
              </select>
            </div>
          </div>
          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="plannerClientDateAppointment">Día para el cliente <span style="color:#b45309;">*</span></label>
              <input type="date" id="plannerClientDateAppointment" name="fecha">
              <div class="invalid-feedback">Selecciona la fecha para el cliente</div>
            </div>
            <div class="schedule-field">
              <label for="plannerClientTimeAppointment">Hora para el cliente <span style="color:#b45309;">*</span></label>
              <input type="time" id="plannerClientTimeAppointment" name="hora" step="60">
              <div class="invalid-feedback">Selecciona la hora para el cliente</div>
            </div>
          </div>
          <div style="font-size: 0.8rem; color: #666; margin-top: -10px; margin-bottom: 14px;">Estos campos se llenan con la fecha y hora elegidas para la vendedora, pero puedes ajustarlos si el cliente está en otra zona horaria.</div>
          <div class="schedule-field">
            <label for="plannerAppointmentComment">Comentario</label>
            <textarea id="plannerAppointmentComment" name="comentario" placeholder="Escribe aquí notas para el vendedor"></textarea>
          </div>
          <div class="schedule-field">
            <label for="plannerClientCity">Ciudad del cliente <span style="color:#6b7280;font-weight:400;font-size:0.85em;">(opcional)</span></label>
            <input type="text" id="plannerClientCity" name="cliente_city" placeholder="Ej. Monterrey, Nuevo León">
          </div>
          <div class="schedule-field">
            <label>Engagement del cliente <span style="color:#b45309;">*</span></label>
            <input type="hidden" id="plannerClientEngagement" name="cliente_engagement">
            <div class="schedule-engagement-btns" id="plannerEngagementGroup">
              <button type="button" class="schedule-engagement-card" data-target="plannerClientEngagement" data-value="1" aria-pressed="false">
                <span class="schedule-engagement-icon">😑</span>
                <span class="schedule-engagement-name">Bajo</span>
                <span class="schedule-engagement-desc">Poco entusiasmo, muchas dudas o precio</span>
              </button>
              <button type="button" class="schedule-engagement-card" data-target="plannerClientEngagement" data-value="2" aria-pressed="false">
                <span class="schedule-engagement-icon">😃</span>
                <span class="schedule-engagement-name">Medio</span>
                <span class="schedule-engagement-desc">Interés real, pero falta de decisión</span>
              </button>
              <button type="button" class="schedule-engagement-card" data-target="plannerClientEngagement" data-value="3" aria-pressed="false">
                <span class="schedule-engagement-icon">🔥</span>
                <span class="schedule-engagement-name">Alto</span>
                <span class="schedule-engagement-desc">Muy interesados, listos para cerrar</span>
              </button>
            </div>
            <div class="invalid-feedback" id="plannerClientEngagementFeedback" style="display:none;">Selecciona el engagement del cliente</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-dark" id="btnGuardarPlannerAppointment">Guardar cita</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade schedule-modal" id="cambiarEstatusCitaModal" tabindex="-1" aria-labelledby="cambiarEstatusCitaModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cambiarEstatusCitaModalLabel">Cambiar estatus de cita</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="cambiarEstatusCalendarioId" value="">
        <input type="hidden" id="cambiarEstatusActual" value="">
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <button id="btnCitaAtendido" class="btn btn-outline btn-sm" type="button" style="flex:1;padding:14px 8px;font-size:0.95rem;">✅ Atendido</button>
          <button id="btnCitaMuerto" class="btn btn-outline btn-sm" type="button" style="flex:1;padding:14px 8px;font-size:0.95rem;">💀 Muerto</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade schedule-modal" id="pasarEventoModal" tabindex="-1" aria-labelledby="pasarEventoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="pasarEventoModalLabel">Pasar cita a evento</h5>
          <div id="pasarEventoModalSubtitle" style="font-size: 0.85rem; color: #666; margin-top: 4px;">Completa los datos para convertir la cita en evento.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="pasarEventoForm">
          <input type="hidden" id="eventoDesdeCitaCalendarioId" name="calendario_id" value="">
          <input type="hidden" id="eventoDesdeCitaAsesorId" name="id_asesor" value="">
          <input type="hidden" id="eventoDesdeCitaFechaRegistro" name="fecha_registro" value="">
          <input type="hidden" id="eventoDesdeCitaModo" name="modo" value="asistencia_post_q">
          <input type="hidden" id="eventoDesdeCitaTipoPaquete" name="tipo_paquete" value="estandar">

          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="eventoDesdeCitaNovios">Nombre de los novios <span style="color:#b45309;">*</span></label>
              <input type="text" id="eventoDesdeCitaNovios" name="novios" placeholder="Nombre de los novios">
              <div class="invalid-feedback">Escribe el nombre de los novios</div>
            </div>
            <div class="schedule-field">
              <label for="eventoDesdeCitaAsesorNombre">Asesor de la cita</label>
              <input type="text" id="eventoDesdeCitaAsesorNombre" value="" readonly>
            </div>
          </div>

          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="eventoDesdeCitaCoordinador">Coordinador <span style="color:#64748b;">(opcional)</span></label>
              <select id="eventoDesdeCitaCoordinador" name="id_coordinador">
                <option value="0">Sin asignar</option>
                <?php foreach ($coordinators as $coordinator): ?>
                  <?php $coordinatorName = trim((string) ($coordinator['nombre'] ?? '')); ?>
                  <option value="<?php echo intval($coordinator['id'] ?? 0); ?>"><?php echo htmlspecialchars($coordinatorName !== '' ? $coordinatorName : ('Coordinador #' . intval($coordinator['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="schedule-field">
              <label for="eventoDesdeCitaCity">Ciudad <span style="color:#b45309;">*</span></label>
              <input type="text" id="eventoDesdeCitaCity" name="city" placeholder="Ciudad del evento o de los novios">
              <div class="invalid-feedback">Escribe la ciudad</div>
            </div>
          </div>

          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="eventoDesdeCitaLugar">Lugar del evento</label>
              <input type="text" id="eventoDesdeCitaLugar" name="lugar" placeholder="Lugar del evento">
            </div>
            <div class="schedule-field">
              <label for="eventoDesdeCitaFecha">Fecha del evento</label>
              <input type="datetime-local" id="eventoDesdeCitaFecha" name="fecha">
            </div>
          </div>

          <div class="schedule-field">
            <label for="eventoDesdeCitaPaquete">Paquete <span style="color:#b45309;">*</span></label>
            <select id="eventoDesdeCitaPaquete" name="id_paquete">
              <option value="">Seleccionar paquete...</option>
              <?php foreach ($packages as $package): ?>
                <option value="<?php echo intval($package['id'] ?? 0); ?>"><?php echo htmlspecialchars(trim((string) ($package['nombre'] ?? '')) !== '' ? $package['nombre'] : ('Paquete #' . intval($package['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Selecciona un paquete</div>
          </div>

          <div class="schedule-field">
            <label for="eventoDesdeCitaMontoVenta">Monto de la venta <span style="color:#64748b;">(opcional)</span></label>
            <input type="number" step="0.01" min="0" id="eventoDesdeCitaMontoVenta" name="monto_venta" placeholder="Ej. 25000.00">
            <div class="invalid-feedback">Escribe un monto válido</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-dark" id="btnPasarEventoDesdeCita">Guardar evento</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade schedule-modal" id="crearEventoSinCitaModal" tabindex="-1" aria-labelledby="crearEventoSinCitaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="crearEventoSinCitaModalLabel">Crear evento sin cita</h5>
          <div id="crearEventoSinCitaModalSubtitle" style="font-size: 0.85rem; color: #666; margin-top: 4px;">Registra un evento directo para este wedding planner.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="crearEventoSinCitaForm">
          <input type="hidden" id="eventoSinCitaFechaRegistro" name="fecha_registro" value="">
          <input type="hidden" id="eventoSinCitaModo" name="modo" value="sin_cita_directa">
          <input type="hidden" id="eventoSinCitaTipoPaquete" name="tipo_paquete" value="estandar">

          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="eventoSinCitaNovios">Nombre de los novios <span style="color:#b45309;">*</span></label>
              <input type="text" id="eventoSinCitaNovios" name="novios" placeholder="Nombre de los novios">
              <div class="invalid-feedback">Escribe el nombre de los novios</div>
            </div>
            <div class="schedule-field">
              <label for="eventoSinCitaAsesor">Asesor <span style="color:#b45309;">*</span></label>
              <select id="eventoSinCitaAsesor" name="id_asesor">
                <option value="">Seleccionar asesor...</option>
                <?php foreach ($agents as $agent): ?>
                  <?php $agentName = trim((string) (($agent['nombre'] ?? '') . ' ' . ($agent['apepat'] ?? ''))); ?>
                  <option value="<?php echo intval($agent['id'] ?? 0); ?>"><?php echo htmlspecialchars($agentName !== '' ? $agentName : ('Usuario #' . intval($agent['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Selecciona un asesor</div>
            </div>
          </div>

          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="eventoSinCitaCoordinador">Coordinador <span style="color:#64748b;">(opcional)</span></label>
              <select id="eventoSinCitaCoordinador" name="id_coordinador">
                <option value="0">Sin asignar</option>
                <?php foreach ($coordinators as $coordinator): ?>
                  <?php $coordinatorName = trim((string) ($coordinator['nombre'] ?? '')); ?>
                  <option value="<?php echo intval($coordinator['id'] ?? 0); ?>"><?php echo htmlspecialchars($coordinatorName !== '' ? $coordinatorName : ('Coordinador #' . intval($coordinator['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="schedule-field">
              <label for="eventoSinCitaCity">Ciudad <span style="color:#b45309;">*</span></label>
              <input type="text" id="eventoSinCitaCity" name="city" placeholder="Ciudad del evento o de los novios">
              <div class="invalid-feedback">Escribe la ciudad</div>
            </div>
          </div>

          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="eventoSinCitaLugar">Lugar del evento</label>
              <input type="text" id="eventoSinCitaLugar" name="lugar" placeholder="Lugar del evento">
            </div>
            <div class="schedule-field">
              <label for="eventoSinCitaFecha">Fecha del evento</label>
              <input type="datetime-local" id="eventoSinCitaFecha" name="fecha">
            </div>
          </div>

          <div class="schedule-field">
            <label for="eventoSinCitaPaquete">Paquete <span style="color:#b45309;">*</span></label>
            <select id="eventoSinCitaPaquete" name="id_paquete">
              <option value="">Seleccionar paquete...</option>
              <?php foreach ($packages as $package): ?>
                <option value="<?php echo intval($package['id'] ?? 0); ?>"><?php echo htmlspecialchars(trim((string) ($package['nombre'] ?? '')) !== '' ? $package['nombre'] : ('Paquete #' . intval($package['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Selecciona un paquete</div>
          </div>

          <div class="schedule-field">
            <label for="eventoSinCitaMontoVenta">Monto de la venta <span style="color:#64748b;">(opcional)</span></label>
            <input type="number" step="0.01" min="0" id="eventoSinCitaMontoVenta" name="monto_venta" placeholder="Ej. 25000.00">
            <div class="invalid-feedback">Escribe un monto válido</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-dark" id="btnCrearEventoSinCita">Guardar evento sin cita</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade schedule-modal" id="editarEventoPlannerModal" tabindex="-1" aria-labelledby="editarEventoPlannerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="editarEventoPlannerModalLabel">Editar evento</h5>
          <div style="font-size: 0.85rem; color: #666; margin-top: 4px;">Actualiza la informacion del evento seleccionado.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="plannerEditEventForm">
          <input type="hidden" id="plannerEditEventId" name="event_id" value="">
          <input type="hidden" id="plannerEditEventPlannerId" name="wedding_planner_id" value="<?php echo intval($plannerId); ?>">
          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="plannerEditEventNovios">Nombre de los novios <span style="color:#b45309;">*</span></label>
              <input type="text" id="plannerEditEventNovios" name="novios" placeholder="Nombre de los novios">
              <div class="invalid-feedback">Escribe el nombre de los novios</div>
            </div>
            <div class="schedule-field">
              <label for="plannerEditEventCity">Ciudad <span style="color:#b45309;">*</span></label>
              <input type="text" id="plannerEditEventCity" name="city" placeholder="Ciudad del evento">
              <div class="invalid-feedback">Escribe la ciudad</div>
            </div>
          </div>

          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="plannerEditEventCoordinador">Coordinador <span style="color:#b45309;">*</span></label>
              <select id="plannerEditEventCoordinador" name="id_coordinador">
                <option value="">Seleccionar coordinador...</option>
                <?php foreach ($coordinators as $coordinator): ?>
                  <?php $coordinatorName = trim((string) ($coordinator['nombre'] ?? '')); ?>
                  <option value="<?php echo intval($coordinator['id'] ?? 0); ?>"><?php echo htmlspecialchars($coordinatorName !== '' ? $coordinatorName : ('Coordinador #' . intval($coordinator['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Selecciona un coordinador</div>
            </div>
            <div class="schedule-field">
              <label for="plannerEditEventAsesor">Asesor <span style="color:#b45309;">*</span></label>
              <select id="plannerEditEventAsesor" name="id_asesor">
                <option value="">Seleccionar asesor...</option>
                <?php foreach ($agents as $agent): ?>
                  <?php $agentName = trim((string) (($agent['nombre'] ?? '') . ' ' . ($agent['apepat'] ?? ''))); ?>
                  <option value="<?php echo intval($agent['id'] ?? 0); ?>"><?php echo htmlspecialchars($agentName !== '' ? $agentName : ('Usuario #' . intval($agent['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Selecciona un asesor</div>
            </div>
          </div>

          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="plannerEditEventPaquete">Paquete <span style="color:#b45309;">*</span></label>
              <select id="plannerEditEventPaquete" name="id_paquete">
                <option value="">Seleccionar paquete...</option>
                <?php foreach ($packages as $package): ?>
                  <option value="<?php echo intval($package['id'] ?? 0); ?>"><?php echo htmlspecialchars(trim((string) ($package['nombre'] ?? '')) !== '' ? $package['nombre'] : ('Paquete #' . intval($package['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Selecciona un paquete</div>
            </div>
            <div class="schedule-field">
              <label for="plannerEditEventMontoVenta">Monto de la venta <span style="color:#64748b;">(opcional)</span></label>
              <input type="number" step="0.01" min="0" id="plannerEditEventMontoVenta" name="monto_venta" placeholder="Ej. 25000.00">
              <div class="invalid-feedback">Escribe un monto válido</div>
            </div>
          </div>

          <div class="schedule-grid">
            <div class="schedule-field">
              <label for="plannerEditEventLugar">Lugar del evento</label>
              <input type="text" id="plannerEditEventLugar" name="lugar" placeholder="Lugar del evento">
            </div>
            <div class="schedule-field">
              <label for="plannerEditEventFecha">Fecha del evento</label>
              <input type="datetime-local" id="plannerEditEventFecha" name="fecha">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-dark" id="btnGuardarEventoEditadoPlanner">Guardar cambios</button>
      </div>
    </div>
  </div>
</div>

<script>
  const plannerBlockedDates = <?php echo json_encode($blockedDates, JSON_UNESCAPED_UNICODE); ?>;
  const plannerId = <?php echo intval($plannerId); ?>;
  const plannerDefaultCity = <?php echo json_encode($plannerCity, JSON_UNESCAPED_UNICODE); ?>;
  const plannerHasPendingConvertibleAppointment = <?php echo $hasConvertiblePendingAppointment ? 'true' : 'false'; ?>;
  const plannerDefaultVendorId = <?php echo $plannerVendedorId; ?>;

  function plannerShowAlert(message, type) {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      return window.Swal.fire({
        icon: type || 'info',
        title: message,
        confirmButtonText: 'Aceptar'
      });
    }

    console.error('SweetAlert2 no esta disponible para mostrar el mensaje:', message);
    return Promise.resolve();
  }

  function plannerIsBlockedDate(dateValue) {
    return plannerBlockedDates.indexOf(dateValue) !== -1;
  }

  function plannerApplyScheduleDateBounds() {
    const dateInput = document.getElementById('plannerDateAppointment');
    const clientDateInput = document.getElementById('plannerClientDateAppointment');
    if (!dateInput) {
      return;
    }

    const today = new Date();
    dateInput.setAttribute('min', today.toISOString().split('T')[0]);
    if (clientDateInput) {
      clientDateInput.setAttribute('min', today.toISOString().split('T')[0]);
    }
    today.setDate(today.getDate() + 20);
    dateInput.setAttribute('max', today.toISOString().split('T')[0]);
    if (clientDateInput) {
      clientDateInput.setAttribute('max', today.toISOString().split('T')[0]);
    }
  }

  function plannerCurrentDatetimeLocal() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    return now.toISOString().slice(0, 16);
  }

  function plannerResetScheduleFields() {
    const vendor = document.getElementById('plannerAssignedVendor');
    const date = document.getElementById('plannerDateAppointment');
    const time = document.getElementById('plannerTimeAppointment');
    const clientDate = document.getElementById('plannerClientDateAppointment');
    const clientTime = document.getElementById('plannerClientTimeAppointment');
    const comment = document.getElementById('plannerAppointmentComment');
    const clientCity = document.getElementById('plannerClientCity');
    const clientEngagement = document.getElementById('plannerClientEngagement');
    const engagementGroup = document.getElementById('plannerEngagementGroup');
    const engagementFeedback = document.getElementById('plannerClientEngagementFeedback');

    [vendor, date, time, clientDate, clientTime, clientCity].forEach(function (field) {
      if (field) {
        field.classList.remove('is-invalid');
      }
    });

    if (date) {
      date.value = '';
    }
    if (time) {
      time.innerHTML = '<option value="">Selecciona una hora</option>';
    }
    if (clientDate) {
      clientDate.value = '';
      delete clientDate.dataset.lastSourceValue;
    }
    if (clientTime) {
      clientTime.value = '';
      delete clientTime.dataset.lastSourceValue;
    }
    if (comment) {
      comment.value = '';
    }
    if (clientCity) {
      clientCity.value = String(plannerDefaultCity || '').trim();
    }
    if (clientEngagement) {
      clientEngagement.value = '';
    }
    if (engagementGroup) {
      engagementGroup.classList.remove('is-invalid');
    }
    if (engagementFeedback) {
      engagementFeedback.style.display = 'none';
    }
    plannerSyncEngagementCards();
  }

  function plannerSyncClientAppointmentFields() {
    const sellerDate = (document.getElementById('plannerDateAppointment')?.value || '').trim();
    const sellerTime = (document.getElementById('plannerTimeAppointment')?.value || '').trim();
    const clientDate = document.getElementById('plannerClientDateAppointment');
    const clientTime = document.getElementById('plannerClientTimeAppointment');
    const sellerTimeShort = sellerTime ? sellerTime.slice(0, 5) : '';

    if (clientDate) {
      const previousSellerDate = String(clientDate.dataset.lastSourceValue || '');
      const currentClientDate = String(clientDate.value || '').trim();
      if (!currentClientDate || currentClientDate === previousSellerDate) {
        clientDate.value = sellerDate;
      }
      clientDate.dataset.lastSourceValue = sellerDate;
    }

    if (clientTime) {
      const previousSellerTime = String(clientTime.dataset.lastSourceValue || '');
      const currentClientTime = String(clientTime.value || '').trim();
      if (!currentClientTime || currentClientTime === previousSellerTime) {
        clientTime.value = sellerTimeShort;
      }
      clientTime.dataset.lastSourceValue = sellerTimeShort;
    }
  }

  function plannerSyncEngagementCards() {
    const input = document.getElementById('plannerClientEngagement');
    const group = document.getElementById('plannerEngagementGroup');
    const feedback = document.getElementById('plannerClientEngagementFeedback');
    const value = input ? String(input.value || '').trim() : '';

    document.querySelectorAll('.schedule-engagement-card[data-target="plannerClientEngagement"]').forEach(function (card) {
      const isActive = card.getAttribute('data-value') === value;
      card.classList.toggle('active', isActive);
      card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    if (value && group) {
      group.classList.remove('is-invalid');
    }
    if (value && feedback) {
      feedback.style.display = 'none';
    }
  }

  function plannerSyncInteractionTypeCards() {
    const input = document.getElementById('plannerInteractionType');
    const group = document.getElementById('plannerInteractionTypeGroup');
    const feedback = document.getElementById('plannerInteractionTypeFeedback');
    const value = input ? String(input.value || '').trim() : '';

    document.querySelectorAll('.planner-interaction-type-btn').forEach(function (card) {
      const isActive = card.getAttribute('data-value') === value;
      card.classList.toggle('active', isActive);
      card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    if (value && group) {
      group.classList.remove('is-invalid');
    }
    if (value && feedback) {
      feedback.style.display = 'none';
    }
  }

  function plannerResetInteractionFields() {
    const typeInput = document.getElementById('plannerInteractionType');
    const notesInput = document.getElementById('plannerInteractionNotes');
    const typeGroup = document.getElementById('plannerInteractionTypeGroup');
    const typeFeedback = document.getElementById('plannerInteractionTypeFeedback');

    if (typeInput) {
      typeInput.value = '';
    }
    if (notesInput) {
      notesInput.value = '';
      notesInput.classList.remove('is-invalid');
    }
    if (typeGroup) {
      typeGroup.classList.remove('is-invalid');
    }
    if (typeFeedback) {
      typeFeedback.style.display = 'none';
    }
    plannerSyncInteractionTypeCards();
  }

  function plannerSyncActionOutcomeCards() {
    const input = document.getElementById('plannerActionOutcome');
    const group = document.getElementById('plannerActionOutcomeGroup');
    const feedback = document.getElementById('plannerActionOutcomeFeedback');
    const value = input ? String(input.value || '').trim() : '';

    document.querySelectorAll('.planner-action-outcome-btn').forEach(function (card) {
      const isActive = card.getAttribute('data-value') === value;
      card.classList.toggle('active', isActive);
      card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    if (value && group) {
      group.classList.remove('is-invalid');
    }
    if (value && feedback) {
      feedback.style.display = 'none';
    }
  }

  function plannerResetActionFields() {
    const outcomeInput = document.getElementById('plannerActionOutcome');
    const actionTextInput = document.getElementById('plannerActionText');
    const actionDateInput = document.getElementById('plannerActionDate');
    const outcomeGroup = document.getElementById('plannerActionOutcomeGroup');
    const outcomeFeedback = document.getElementById('plannerActionOutcomeFeedback');

    if (outcomeInput) outcomeInput.value = '';
    if (actionTextInput) {
      actionTextInput.value = '';
      actionTextInput.classList.remove('is-invalid');
    }
    if (actionDateInput) {
      actionDateInput.value = '';
      actionDateInput.classList.remove('is-invalid');
    }
    if (outcomeGroup) outcomeGroup.classList.remove('is-invalid');
    if (outcomeFeedback) outcomeFeedback.style.display = 'none';

    plannerSyncActionOutcomeCards();
  }

  function plannerLoadAvailableTimes() {
    const vendor = document.getElementById('plannerAssignedVendor');
    const date = document.getElementById('plannerDateAppointment');
    const time = document.getElementById('plannerTimeAppointment');
    const vendorId = vendor ? vendor.value.trim() : '';
    const selectedDate = date ? date.value.trim() : '';

    if (!time) {
      return;
    }

    if (!vendorId || !selectedDate) {
      time.innerHTML = '<option value="">Selecciona una hora</option>';
      return;
    }

    if (plannerIsBlockedDate(selectedDate)) {
      if (date) {
        date.value = '';
        date.classList.add('is-invalid');
      }
      time.innerHTML = '<option value="">Sin horarios disponibles</option>';
      plannerShowAlert('La fecha seleccionada está bloqueada', 'warning');
      return;
    }

    const formData = new FormData();
    formData.append('dia', selectedDate);
    formData.append('idusu', vendorId);

    fetch('chkDisponible.php', {
      method: 'POST',
      body: formData
    })
      .then(function (response) { return response.json(); })
      .then(function (horariosJson) {
        const occupiedForm = new FormData();
        occupiedForm.append('fecha', selectedDate);
        occupiedForm.append('idusu', vendorId);

        return fetch('chkDisponible.php', {
          method: 'POST',
          body: occupiedForm
        })
          .then(function (response) { return response.json(); })
          .then(function (occupiedTimes) {
            let selectHtml = '';
            const available = [];

            if (Array.isArray(horariosJson) && horariosJson.length > 0 && horariosJson[0].horarios) {
              const horarios = JSON.parse(horariosJson[0].horarios);
              horarios.forEach(function (horario) {
                let current = horario;
                if (Array.isArray(occupiedTimes) && occupiedTimes.length > 0) {
                  occupiedTimes.forEach(function (currentTime) {
                    if (currentTime.hora === horario + ':00') {
                      current = null;
                    }
                  });
                }

                if (current && available.indexOf(current) === -1) {
                  available.push(current);
                }
              });
            }

            available.sort(function (a, b) {
              const partsA = a.split(':');
              const partsB = b.split(':');
              return (parseInt(partsA[0], 10) * 60 + parseInt(partsA[1], 10)) - (parseInt(partsB[0], 10) * 60 + parseInt(partsB[1], 10));
            });

            if (available.length > 0) {
              selectHtml += '<option value="">Selecciona una hora</option>';
              available.forEach(function (hour) {
                selectHtml += '<option value="' + hour + ':00">' + hour + '</option>';
              });
            } else {
              selectHtml = '<option value="">Sin Horarios Disponibles</option>';
            }

            time.innerHTML = selectHtml;
          });
      })
      .catch(function (error) {
        console.error('Error al obtener horarios disponibles:', error);
        time.innerHTML = '<option value="">Sin Horarios Disponibles</option>';
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    plannerApplyScheduleDateBounds();

    const coordinatorModalElement = document.getElementById('agregarCoordinadorModal');
    const profileModalElement = document.getElementById('editarDatosPlannerModal');
    const modalElement = document.getElementById('agendarCitaPlannerModal');
    const interactionModalElement = document.getElementById('registrarInteraccionModal');
    const actionModalElement = document.getElementById('registrarAccionModal');
    const coordinatorIdInput = document.getElementById('plannerCoordinatorId');
    const coordinatorNameInput = document.getElementById('plannerCoordinatorName');
    const coordinatorEmailInput = document.getElementById('plannerCoordinatorEmail');
    const coordinatorPhoneInput = document.getElementById('plannerCoordinatorPhone');
    const coordinatorModalTitle = document.getElementById('agregarCoordinadorModalLabel');
    const coordinatorModalSubtitle = document.getElementById('agregarCoordinadorModalSubtitle');
    const saveCoordinatorButton = document.getElementById('btnGuardarCoordinator');
    const profileCompanyInput = document.getElementById('plannerEditCompany');
    const profileCityInput = document.getElementById('plannerEditCity');
    const profileEmailInput = document.getElementById('plannerEditEmail');
    const profilePhoneInput = document.getElementById('plannerEditPhone');
    const profileAfianzadoInput = document.getElementById('plannerEditAfianzado');
    const saveProfileButton = document.getElementById('btnGuardarPlannerProfile');
    const vendor = document.getElementById('plannerAssignedVendor');

    function plannerUpdateVendorDisplay() {
      const headerEl = document.getElementById('plannerHeaderVendorName');
      const cardEl = document.getElementById('plannerVendorFieldValue');
      const displayName = (vendor && vendor.value) ? vendor.options[vendor.selectedIndex].text : 'Sin asignar';
      if (headerEl) headerEl.textContent = displayName;
      if (cardEl) cardEl.textContent = displayName;
    }

    if (vendor) {
      vendor.addEventListener('change', plannerUpdateVendorDisplay);
    }

    const editVendor = document.getElementById('plannerEditVendor');
    function plannerUpdateVendorDisplayFromEdit() {
      const headerEl = document.getElementById('plannerHeaderVendorName');
      const cardEl = document.getElementById('plannerVendorFieldValue');
      const displayName = (editVendor && editVendor.value && editVendor.value !== '0') ? editVendor.options[editVendor.selectedIndex].text : 'Sin asignar';
      if (headerEl) headerEl.textContent = displayName;
      if (cardEl) cardEl.textContent = displayName;
      // keep appointment vendor select in sync
      if (vendor && editVendor) {
        vendor.value = editVendor.value;
      }
    }
    if (editVendor) {
      editVendor.addEventListener('change', plannerUpdateVendorDisplayFromEdit);
    }

    const date = document.getElementById('plannerDateAppointment');
    const time = document.getElementById('plannerTimeAppointment');
    const clientDate = document.getElementById('plannerClientDateAppointment');
    const clientTime = document.getElementById('plannerClientTimeAppointment');
    const comment = document.getElementById('plannerAppointmentComment');
    const clientCity = document.getElementById('plannerClientCity');
    const clientEngagement = document.getElementById('plannerClientEngagement');
    const engagementGroup = document.getElementById('plannerEngagementGroup');
    const engagementFeedback = document.getElementById('plannerClientEngagementFeedback');
    const saveButton = document.getElementById('btnGuardarPlannerAppointment');
    const interactionTypeInput = document.getElementById('plannerInteractionType');
    const interactionNotesInput = document.getElementById('plannerInteractionNotes');
    const interactionTypeGroup = document.getElementById('plannerInteractionTypeGroup');
    const interactionTypeFeedback = document.getElementById('plannerInteractionTypeFeedback');
    const saveInteractionButton = document.getElementById('btnGuardarPlannerInteraction');
    const actionOutcomeInput = document.getElementById('plannerActionOutcome');
    const actionTextInput = document.getElementById('plannerActionText');
    const actionDateInput = document.getElementById('plannerActionDate');
    const actionOutcomeGroup = document.getElementById('plannerActionOutcomeGroup');
    const actionOutcomeFeedback = document.getElementById('plannerActionOutcomeFeedback');
    const saveActionButton = document.getElementById('btnGuardarPlannerAction');
    const pasarEventoModalElement = document.getElementById('pasarEventoModal');
    const pasarEventoSubtitle = document.getElementById('pasarEventoModalSubtitle');
    const pasarEventoCalendarioIdInput = document.getElementById('eventoDesdeCitaCalendarioId');
    const pasarEventoAsesorIdInput = document.getElementById('eventoDesdeCitaAsesorId');
    const pasarEventoAsesorNombreInput = document.getElementById('eventoDesdeCitaAsesorNombre');
    const pasarEventoFechaRegistroInput = document.getElementById('eventoDesdeCitaFechaRegistro');
    const pasarEventoNoviosInput = document.getElementById('eventoDesdeCitaNovios');
    const pasarEventoCoordinadorInput = document.getElementById('eventoDesdeCitaCoordinador');
    const pasarEventoCityInput = document.getElementById('eventoDesdeCitaCity');
    const pasarEventoLugarInput = document.getElementById('eventoDesdeCitaLugar');
    const pasarEventoFechaInput = document.getElementById('eventoDesdeCitaFecha');
    const pasarEventoPaqueteInput = document.getElementById('eventoDesdeCitaPaquete');
    const pasarEventoMontoVentaInput = document.getElementById('eventoDesdeCitaMontoVenta');
    const saveEventoDesdeCitaButton = document.getElementById('btnPasarEventoDesdeCita');
    const crearEventoSinCitaModalElement = document.getElementById('crearEventoSinCitaModal');
    const crearEventoSinCitaFechaRegistroInput = document.getElementById('eventoSinCitaFechaRegistro');
    const crearEventoSinCitaNoviosInput = document.getElementById('eventoSinCitaNovios');
    const crearEventoSinCitaAsesorInput = document.getElementById('eventoSinCitaAsesor');
    const crearEventoSinCitaCoordinadorInput = document.getElementById('eventoSinCitaCoordinador');
    const crearEventoSinCitaCityInput = document.getElementById('eventoSinCitaCity');
    const crearEventoSinCitaLugarInput = document.getElementById('eventoSinCitaLugar');
    const crearEventoSinCitaFechaInput = document.getElementById('eventoSinCitaFecha');
    const crearEventoSinCitaPaqueteInput = document.getElementById('eventoSinCitaPaquete');
    const crearEventoSinCitaMontoVentaInput = document.getElementById('eventoSinCitaMontoVenta');
    const saveEventoSinCitaButton = document.getElementById('btnCrearEventoSinCita');
    const editarEventoPlannerModalElement = document.getElementById('editarEventoPlannerModal');
    const editEventIdInput = document.getElementById('plannerEditEventId');
    const editEventPlannerIdInput = document.getElementById('plannerEditEventPlannerId');
    const editEventNoviosInput = document.getElementById('plannerEditEventNovios');
    const editEventCityInput = document.getElementById('plannerEditEventCity');
    const editEventCoordinadorInput = document.getElementById('plannerEditEventCoordinador');
    const editEventAsesorInput = document.getElementById('plannerEditEventAsesor');
    const editEventPaqueteInput = document.getElementById('plannerEditEventPaquete');
    const editEventMontoVentaInput = document.getElementById('plannerEditEventMontoVenta');
    const editEventLugarInput = document.getElementById('plannerEditEventLugar');
    const editEventFechaInput = document.getElementById('plannerEditEventFecha');
    const saveEditedEventButton = document.getElementById('btnGuardarEventoEditadoPlanner');
    const editEventButtons = document.querySelectorAll('.js-edit-event');
    const eventStatusButtons = document.querySelectorAll('.js-event-status-change');

    function plannerAdvisorNameById(advisorId) {
      if (!advisorId || !vendor || !vendor.options) {
        return '';
      }

      for (let index = 0; index < vendor.options.length; index += 1) {
        const option = vendor.options[index];
        if (String(option.value || '').trim() === String(advisorId).trim()) {
          return String(option.textContent || '').trim();
        }
      }

      return '';
    }

    function plannerResetPasarEventoForm() {
      [
        pasarEventoNoviosInput,
        pasarEventoCityInput,
        pasarEventoPaqueteInput,
        pasarEventoMontoVentaInput
      ].forEach(function (field) {
        if (field) {
          field.classList.remove('is-invalid');
        }
      });

      if (pasarEventoCalendarioIdInput) pasarEventoCalendarioIdInput.value = '';
      if (pasarEventoAsesorIdInput) pasarEventoAsesorIdInput.value = '';
      if (pasarEventoAsesorNombreInput) pasarEventoAsesorNombreInput.value = '';
      if (pasarEventoFechaRegistroInput) pasarEventoFechaRegistroInput.value = plannerCurrentDatetimeLocal();
      if (pasarEventoNoviosInput) pasarEventoNoviosInput.value = '';
      if (pasarEventoCoordinadorInput) pasarEventoCoordinadorInput.value = '0';
      if (pasarEventoCityInput) pasarEventoCityInput.value = String(plannerDefaultCity || '').trim();
      if (pasarEventoLugarInput) pasarEventoLugarInput.value = '';
      if (pasarEventoFechaInput) pasarEventoFechaInput.value = '';
      if (pasarEventoPaqueteInput) pasarEventoPaqueteInput.value = '';
      if (pasarEventoMontoVentaInput) pasarEventoMontoVentaInput.value = '';
      if (pasarEventoSubtitle) pasarEventoSubtitle.textContent = 'Completa los datos para convertir la cita en evento.';
    }

    function plannerResetProfileInvalidStates() {
      [profileCompanyInput, profileCityInput, profileEmailInput, profilePhoneInput].forEach(function (field) {
        if (field) {
          field.classList.remove('is-invalid');
        }
      });
    }

    function plannerResetCrearEventoSinCitaForm() {
      [
        crearEventoSinCitaNoviosInput,
        crearEventoSinCitaAsesorInput,
        crearEventoSinCitaCityInput,
        crearEventoSinCitaPaqueteInput,
        crearEventoSinCitaMontoVentaInput
      ].forEach(function (field) {
        if (field) {
          field.classList.remove('is-invalid');
        }
      });

      if (crearEventoSinCitaFechaRegistroInput) crearEventoSinCitaFechaRegistroInput.value = plannerCurrentDatetimeLocal();
      if (crearEventoSinCitaNoviosInput) crearEventoSinCitaNoviosInput.value = '';
      if (crearEventoSinCitaAsesorInput) crearEventoSinCitaAsesorInput.value = '';
      if (crearEventoSinCitaCoordinadorInput) crearEventoSinCitaCoordinadorInput.value = '0';
      if (crearEventoSinCitaCityInput) crearEventoSinCitaCityInput.value = String(plannerDefaultCity || '').trim();
      if (crearEventoSinCitaLugarInput) crearEventoSinCitaLugarInput.value = '';
      if (crearEventoSinCitaFechaInput) crearEventoSinCitaFechaInput.value = '';
      if (crearEventoSinCitaPaqueteInput) crearEventoSinCitaPaqueteInput.value = '';
      if (crearEventoSinCitaMontoVentaInput) crearEventoSinCitaMontoVentaInput.value = '';
    }

    function plannerNormalizeDateTimeLocal(value) {
      const raw = String(value || '').trim();
      if (!raw) {
        return '';
      }

      const parsed = new Date(raw);
      if (Number.isNaN(parsed.getTime())) {
        return '';
      }

      const local = new Date(parsed.getTime() - (parsed.getTimezoneOffset() * 60000));
      return local.toISOString().slice(0, 16);
    }

    function plannerResetEditEventForm() {
      [
        editEventIdInput,
        editEventPlannerIdInput,
        editEventNoviosInput,
        editEventCityInput,
        editEventCoordinadorInput,
        editEventAsesorInput,
        editEventPaqueteInput,
        editEventMontoVentaInput,
        editEventLugarInput,
        editEventFechaInput
      ].forEach(function (field) {
        if (!field) {
          return;
        }

        field.classList.remove('is-invalid');
      });

      if (editEventIdInput) editEventIdInput.value = '';
      if (editEventPlannerIdInput) editEventPlannerIdInput.value = String(plannerId);
      if (editEventNoviosInput) editEventNoviosInput.value = '';
      if (editEventCityInput) editEventCityInput.value = String(plannerDefaultCity || '').trim();
      if (editEventCoordinadorInput) editEventCoordinadorInput.value = '';
      if (editEventAsesorInput) editEventAsesorInput.value = '';
      if (editEventPaqueteInput) editEventPaqueteInput.value = '';
      if (editEventMontoVentaInput) editEventMontoVentaInput.value = '';
      if (editEventLugarInput) editEventLugarInput.value = '';
      if (editEventFechaInput) editEventFechaInput.value = '';
    }

    function plannerRequestAsJson(url, payload) {
      return fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: payload.toString()
      }).then(function (response) {
        return response.text().then(function (rawText) {
          var parsed = null;
          if (rawText && rawText.trim() !== '') {
            try {
              parsed = JSON.parse(rawText);
            } catch (parseError) {
              var invalidJsonError = new Error('El servidor devolvió una respuesta inválida.');
              invalidJsonError.url = url;
              invalidJsonError.status = response.status;
              invalidJsonError.statusText = response.statusText;
              invalidJsonError.rawResponse = rawText;
              console.error('[plannerRequestAsJson] JSON parse error', {
                url: url,
                status: response.status,
                statusText: response.statusText,
                parseError: parseError,
                rawResponse: rawText
              });
              throw invalidJsonError;
            }
          }

          if (!response.ok) {
            var httpError = new Error(parsed && parsed.message ? parsed.message : 'Error en la petición');
            httpError.url = url;
            httpError.status = response.status;
            httpError.statusText = response.statusText;
            httpError.rawResponse = rawText;
            console.error('[plannerRequestAsJson] HTTP error response', {
              url: url,
              status: response.status,
              statusText: response.statusText,
              parsed: parsed,
              rawResponse: rawText
            });
            throw httpError;
          }

          if (parsed === null) {
            console.warn('[plannerRequestAsJson] Empty response body', {
              url: url,
              status: response.status,
              statusText: response.statusText,
              rawResponse: rawText
            });

            var emptyBodyError = new Error('El servidor devolvio una respuesta vacia.');
            emptyBodyError.url = url;
            emptyBodyError.status = response.status;
            emptyBodyError.statusText = response.statusText;
            emptyBodyError.rawResponse = rawText;
            throw emptyBodyError;
          }

          return parsed;
        });
      });
    }

    function plannerResetCoordinatorFields() {
      if (coordinatorIdInput) {
        coordinatorIdInput.value = '';
      }

      [coordinatorNameInput, coordinatorEmailInput, coordinatorPhoneInput].forEach(function (field) {
        if (field) {
          field.value = '';
          field.classList.remove('is-invalid');
        }
      });

      if (coordinatorModalTitle) {
        coordinatorModalTitle.textContent = 'Agregar coordinador';
      }
      if (coordinatorModalSubtitle) {
        coordinatorModalSubtitle.textContent = 'Asigna un coordinador a <?php echo htmlspecialchars($plannerName, ENT_QUOTES, 'UTF-8'); ?>';
      }
      if (saveCoordinatorButton) {
        saveCoordinatorButton.textContent = 'Guardar coordinador';
      }
    }

    if (coordinatorModalElement) {
      coordinatorModalElement.addEventListener('show.bs.modal', function (event) {
        plannerResetCoordinatorFields();

        const trigger = event.relatedTarget;
        if (!trigger) {
          return;
        }

        const serialized = trigger.getAttribute('data-coordinator');
        if (!serialized) {
          return;
        }

        try {
          const coordinator = JSON.parse(serialized);
          if (coordinatorIdInput) {
            coordinatorIdInput.value = String(coordinator.id || '');
          }
          if (coordinatorNameInput) {
            coordinatorNameInput.value = String(coordinator.nombre || '');
          }
          if (coordinatorEmailInput) {
            coordinatorEmailInput.value = String(coordinator.correo || '');
          }
          if (coordinatorPhoneInput) {
            coordinatorPhoneInput.value = String(coordinator.telefono || '');
          }

          if (coordinatorModalTitle) {
            coordinatorModalTitle.textContent = 'Detalle del coordinador';
          }
          if (coordinatorModalSubtitle) {
            coordinatorModalSubtitle.textContent = 'Edita la informacion del coordinador asignado a <?php echo htmlspecialchars($plannerName, ENT_QUOTES, 'UTF-8'); ?>';
          }
          if (saveCoordinatorButton) {
            saveCoordinatorButton.textContent = 'Guardar cambios';
          }
        } catch (error) {
          console.error('No se pudieron cargar los datos del coordinador:', error);
        }
      });

      coordinatorModalElement.addEventListener('hidden.bs.modal', function () {
        plannerResetCoordinatorFields();
      });
    }

    [coordinatorNameInput, coordinatorEmailInput, coordinatorPhoneInput].forEach(function (field) {
      if (field) {
        field.addEventListener('input', function () {
          field.classList.remove('is-invalid');
        });
      }
    });

    if (profileModalElement) {
      profileModalElement.addEventListener('hidden.bs.modal', plannerResetProfileInvalidStates);
    }

    [profileCompanyInput, profileCityInput, profileEmailInput, profilePhoneInput].forEach(function (field) {
      if (field) {
        field.addEventListener('input', function () {
          field.classList.remove('is-invalid');
        });
      }
    });

    if (modalElement) {
      modalElement.addEventListener('show.bs.modal', function () {
        if (clientCity && !clientCity.value.trim()) {
          clientCity.value = String(plannerDefaultCity || '').trim();
        }
      });
      modalElement.addEventListener('hidden.bs.modal', function () {
        plannerResetScheduleFields();
      });
    }

    if (interactionModalElement) {
      interactionModalElement.addEventListener('hidden.bs.modal', function () {
        plannerResetInteractionFields();
        plannerResetActionFields();
      });
    }

    if (pasarEventoModalElement) {
      pasarEventoModalElement.addEventListener('show.bs.modal', function (event) {
        plannerResetPasarEventoForm();

        const trigger = event.relatedTarget;
        if (!trigger) {
          return;
        }

        const serialized = trigger.getAttribute('data-appointment');
        if (!serialized) {
          return;
        }

        try {
          const appointment = JSON.parse(serialized);
          const calendarioId = String(appointment.calendario_id || '').trim();
          const advisorId = String(appointment.id_asesor || '').trim();
          const advisorName = plannerAdvisorNameById(advisorId);

          if (pasarEventoCalendarioIdInput) pasarEventoCalendarioIdInput.value = calendarioId;
          if (pasarEventoAsesorIdInput) pasarEventoAsesorIdInput.value = advisorId;
          if (pasarEventoAsesorNombreInput) {
            pasarEventoAsesorNombreInput.value = advisorName || (advisorId ? ('Asesor #' + advisorId) : 'Sin asesor');
          }

          const appointmentCity = String(appointment.city || '').trim();
          if (pasarEventoCityInput) {
            pasarEventoCityInput.value = appointmentCity || String(plannerDefaultCity || '').trim();
          }

          if (pasarEventoSubtitle) {
            const eventoId = String(appointment.evento_wp_id || '').trim();
            pasarEventoSubtitle.textContent = 'Cita #' + calendarioId + (eventoId ? (' · Evento WP #' + eventoId) : '') + ' seleccionada.';
          }
        } catch (error) {
          console.error('No se pudieron cargar los datos de la cita:', error);
        }
      });

      pasarEventoModalElement.addEventListener('hidden.bs.modal', function () {
        plannerResetPasarEventoForm();
      });
    }

    if (crearEventoSinCitaModalElement) {
      crearEventoSinCitaModalElement.addEventListener('show.bs.modal', function () {
        plannerResetCrearEventoSinCitaForm();
      });

      crearEventoSinCitaModalElement.addEventListener('hidden.bs.modal', function () {
        plannerResetCrearEventoSinCitaForm();
      });
    }

    if (editarEventoPlannerModalElement) {
      editarEventoPlannerModalElement.addEventListener('hidden.bs.modal', function () {
        plannerResetEditEventForm();
      });
    }

    [pasarEventoNoviosInput, pasarEventoCityInput, pasarEventoMontoVentaInput].forEach(function (field) {
      if (field) {
        field.addEventListener('input', function () {
          field.classList.remove('is-invalid');
        });
      }
    });

    if (pasarEventoPaqueteInput) {
      pasarEventoPaqueteInput.addEventListener('change', function () {
        pasarEventoPaqueteInput.classList.remove('is-invalid');
      });
    }

    [crearEventoSinCitaNoviosInput, crearEventoSinCitaCityInput, crearEventoSinCitaMontoVentaInput].forEach(function (field) {
      if (field) {
        field.addEventListener('input', function () {
          field.classList.remove('is-invalid');
        });
      }
    });

    [crearEventoSinCitaAsesorInput, crearEventoSinCitaPaqueteInput].forEach(function (field) {
      if (field) {
        field.addEventListener('change', function () {
          field.classList.remove('is-invalid');
        });
      }
    });

    [
      editEventNoviosInput,
      editEventCityInput,
      editEventMontoVentaInput,
      editEventLugarInput,
      editEventFechaInput
    ].forEach(function (field) {
      if (!field) {
        return;
      }

      field.addEventListener('input', function () {
        field.classList.remove('is-invalid');
      });
    });

    [editEventCoordinadorInput, editEventAsesorInput, editEventPaqueteInput].forEach(function (field) {
      if (!field) {
        return;
      }

      field.addEventListener('change', function () {
        field.classList.remove('is-invalid');
      });
    });

    if (vendor) {
      vendor.addEventListener('change', function () {
        vendor.classList.remove('is-invalid');
        if (date) {
          date.value = '';
        }
        if (time) {
          time.innerHTML = '<option value="">Selecciona una hora</option>';
        }
        if (clientDate) {
          clientDate.value = '';
          delete clientDate.dataset.lastSourceValue;
        }
        if (clientTime) {
          clientTime.value = '';
          delete clientTime.dataset.lastSourceValue;
        }
      });
    }

    if (date) {
      date.addEventListener('change', function () {
        date.classList.remove('is-invalid');
        if (time) {
          time.classList.remove('is-invalid');
        }

        if (date.value && plannerIsBlockedDate(date.value)) {
          date.value = '';
          date.classList.add('is-invalid');
          if (time) {
            time.innerHTML = '<option value="">Selecciona una hora</option>';
          }
          plannerShowAlert('La fecha seleccionada está bloqueada', 'warning');
          return;
        }

        plannerSyncClientAppointmentFields();
        plannerLoadAvailableTimes();
      });
    }

    if (time) {
      time.addEventListener('change', function () {
        time.classList.remove('is-invalid');
        plannerSyncClientAppointmentFields();
      });
    }

    [clientDate, clientTime].forEach(function (field) {
      if (field) {
        field.addEventListener('input', function () {
          field.classList.remove('is-invalid');
        });
        field.addEventListener('change', function () {
          field.classList.remove('is-invalid');
        });
      }
    });

    if (clientCity) {
      clientCity.addEventListener('input', function () {
        clientCity.classList.remove('is-invalid');
      });
    }

    if (clientEngagement) {
      clientEngagement.addEventListener('change', plannerSyncEngagementCards);
    }

    if (interactionNotesInput) {
      interactionNotesInput.addEventListener('input', function () {
        interactionNotesInput.classList.remove('is-invalid');
      });
    }

    if (actionTextInput) {
      actionTextInput.addEventListener('input', function () {
        actionTextInput.classList.remove('is-invalid');
      });
    }

    if (actionDateInput) {
      actionDateInput.addEventListener('change', function () {
        actionDateInput.classList.remove('is-invalid');
      });
    }

    document.querySelectorAll('.planner-interaction-type-btn').forEach(function (card) {
      card.addEventListener('click', function () {
        if (!interactionTypeInput) {
          return;
        }

        const nextValue = String(card.getAttribute('data-value') || '');
        const currentValue = String(interactionTypeInput.value || '').trim();
        interactionTypeInput.value = currentValue === nextValue ? '' : nextValue;
        plannerSyncInteractionTypeCards();
      });
    });

    document.querySelectorAll('.planner-action-outcome-btn').forEach(function (card) {
      card.addEventListener('click', function () {
        if (!actionOutcomeInput) {
          return;
        }

        const nextValue = String(card.getAttribute('data-value') || '');
        const currentValue = String(actionOutcomeInput.value || '').trim();
        actionOutcomeInput.value = currentValue === nextValue ? '' : nextValue;
        plannerSyncActionOutcomeCards();
      });
    });

    document.querySelectorAll('.schedule-engagement-card[data-target="plannerClientEngagement"]').forEach(function (card) {
      card.addEventListener('click', function () {
        if (!clientEngagement) {
          return;
        }

        const nextValue = String(card.getAttribute('data-value') || '');
        const currentValue = String(clientEngagement.value || '').trim();
        clientEngagement.value = currentValue === nextValue ? '' : nextValue;
        plannerSyncEngagementCards();
      });
    });

    if (saveButton) {
      saveButton.addEventListener('click', function () {
        const vendorId = vendor ? vendor.value.trim() : '';
        const selectedDate = date ? date.value.trim() : '';
        const selectedTime = time ? time.value.trim() : '';
        const selectedClientDate = clientDate ? clientDate.value.trim() : '';
        const selectedClientTime = clientTime ? clientTime.value.trim() : '';
        const note = comment ? comment.value.trim() : '';
        const selectedCity = clientCity ? clientCity.value.trim() : '';
        const selectedEngagement = clientEngagement ? clientEngagement.value.trim() : '';

        if (vendor) vendor.classList.remove('is-invalid');
        if (date) date.classList.remove('is-invalid');
        if (time) time.classList.remove('is-invalid');
        if (clientDate) clientDate.classList.remove('is-invalid');
        if (clientTime) clientTime.classList.remove('is-invalid');
        if (clientCity) clientCity.classList.remove('is-invalid');
        if (engagementGroup) engagementGroup.classList.remove('is-invalid');
        if (engagementFeedback) engagementFeedback.style.display = 'none';

        if (!vendorId) {
          if (vendor) vendor.classList.add('is-invalid');
          plannerShowAlert('Selecciona un vendedor', 'warning');
          return;
        }

        if (!selectedDate) {
          if (date) date.classList.add('is-invalid');
          plannerShowAlert('Selecciona una fecha para la cita', 'warning');
          return;
        }

        if (!selectedTime) {
          if (time) time.classList.add('is-invalid');
          plannerShowAlert('Selecciona un horario disponible', 'warning');
          return;
        }

        if (!selectedClientDate) {
          if (clientDate) clientDate.classList.add('is-invalid');
          plannerShowAlert('Selecciona la fecha de la cita para el cliente', 'warning');
          return;
        }

        if (!selectedClientTime) {
          if (clientTime) clientTime.classList.add('is-invalid');
          plannerShowAlert('Selecciona la hora de la cita para el cliente', 'warning');
          return;
        }

        if (!selectedEngagement) {
          if (engagementGroup) engagementGroup.classList.add('is-invalid');
          if (engagementFeedback) engagementFeedback.style.display = 'block';
          plannerShowAlert('Selecciona el engagement del cliente', 'warning');
          return;
        }

        const payload = new URLSearchParams();
        payload.append('wedding_planner_id', String(plannerId));
        payload.append('override_vendedor_id', vendorId);
        payload.append('fecha', selectedDate);
        payload.append('hora', selectedTime);
        payload.append('fecha_cliente', selectedClientDate);
        payload.append('hora_cliente', selectedClientTime);
        payload.append('comentario', note);
        payload.append('cliente_city', selectedCity);
        payload.append('cliente_engagement', selectedEngagement);

        saveButton.disabled = true;
        saveButton.textContent = 'Guardando...';

        fetch('agendar_wedding_planner.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload.toString()
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (!data || !data.success) {
              plannerShowAlert(data && data.message ? data.message : 'No se pudo crear la cita', 'error');
              return;
            }

            if (modalElement && window.bootstrap) {
              const modal = bootstrap.Modal.getInstance(modalElement) || bootstrap.Modal.getOrCreateInstance(modalElement);
              modal.hide();
            }

            plannerShowAlert('La cita se registró correctamente', 'success').then(function () {
              window.location.reload();
            });
          })
          .catch(function (error) {
            console.error('Error creando cita:', error);
            plannerShowAlert('No se pudo crear la cita para el wedding planner', 'error');
          })
          .finally(function () {
            saveButton.disabled = false;
            saveButton.textContent = 'Guardar cita';
          });
      });
    }

    if (saveProfileButton) {
      saveProfileButton.addEventListener('click', function () {
        const empresa = profileCompanyInput ? profileCompanyInput.value.trim() : '';
        const ciudad = profileCityInput ? profileCityInput.value.trim() : '';
        const correo = profileEmailInput ? profileEmailInput.value.trim() : '';
        const telefono = profilePhoneInput ? profilePhoneInput.value.trim() : '';
        const afianzado = profileAfianzadoInput ? profileAfianzadoInput.value.trim() : '0';

        plannerResetProfileInvalidStates();

        if (!empresa) {
          if (profileCompanyInput) profileCompanyInput.classList.add('is-invalid');
          plannerShowAlert('Escribe el nombre', 'warning');
          return;
        }

        if (correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
          if (profileEmailInput) profileEmailInput.classList.add('is-invalid');
          plannerShowAlert('Escribe un correo valido', 'warning');
          return;
        }

        const profileVendorInput = document.getElementById('plannerEditVendor');
        const idVendedorAsignado = profileVendorInput ? profileVendorInput.value : '0';

        const payload = new URLSearchParams();
        payload.append('planner_id', String(plannerId));
        payload.append('empresa_wp', empresa);
        payload.append('city', ciudad);
        payload.append('email', correo);
        payload.append('phone', telefono);
        payload.append('afianzado', afianzado);
        payload.append('id_vendedor_asignado', idVendedorAsignado);

        saveProfileButton.disabled = true;
        saveProfileButton.textContent = 'Guardando...';

        fetch('actualizar_datos_planner.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload.toString()
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (!data || !data.success) {
              plannerShowAlert(data && data.message ? data.message : 'No se pudo actualizar la informacion', 'error');
              return;
            }

            if (profileModalElement && window.bootstrap) {
              const modal = bootstrap.Modal.getInstance(profileModalElement) || bootstrap.Modal.getOrCreateInstance(profileModalElement);
              modal.hide();
            }

            plannerShowAlert('Datos actualizados correctamente', 'success').then(function () {
              window.location.reload();
            });
          })
          .catch(function (error) {
            console.error('Error actualizando datos del planner:', error);
            plannerShowAlert('No se pudo actualizar la informacion del planner', 'error');
          })
          .finally(function () {
            saveProfileButton.disabled = false;
            saveProfileButton.textContent = 'Guardar cambios';
          });
      });
    }

    if (saveEventoDesdeCitaButton) {
      saveEventoDesdeCitaButton.addEventListener('click', function () {
        const calendarioId = pasarEventoCalendarioIdInput ? pasarEventoCalendarioIdInput.value.trim() : '';
        const asesorId = pasarEventoAsesorIdInput ? pasarEventoAsesorIdInput.value.trim() : '';
        const fechaRegistro = pasarEventoFechaRegistroInput ? pasarEventoFechaRegistroInput.value.trim() : '';
        const novios = pasarEventoNoviosInput ? pasarEventoNoviosInput.value.trim() : '';
        const coordinadorId = pasarEventoCoordinadorInput ? pasarEventoCoordinadorInput.value.trim() : '0';
        const city = pasarEventoCityInput ? pasarEventoCityInput.value.trim() : '';
        const lugar = pasarEventoLugarInput ? pasarEventoLugarInput.value.trim() : '';
        const fecha = pasarEventoFechaInput ? pasarEventoFechaInput.value.trim() : '';
        const paqueteId = pasarEventoPaqueteInput ? pasarEventoPaqueteInput.value.trim() : '';
        const montoVenta = pasarEventoMontoVentaInput ? pasarEventoMontoVentaInput.value.trim() : '';

        if (pasarEventoNoviosInput) pasarEventoNoviosInput.classList.remove('is-invalid');
        if (pasarEventoCityInput) pasarEventoCityInput.classList.remove('is-invalid');
        if (pasarEventoPaqueteInput) pasarEventoPaqueteInput.classList.remove('is-invalid');
        if (pasarEventoMontoVentaInput) pasarEventoMontoVentaInput.classList.remove('is-invalid');

        if (!calendarioId) {
          plannerShowAlert('No se pudo identificar la cita seleccionada', 'error');
          return;
        }

        if (!asesorId) {
          plannerShowAlert('La cita seleccionada no tiene asesor asignado', 'warning');
          return;
        }

        if (!novios) {
          if (pasarEventoNoviosInput) pasarEventoNoviosInput.classList.add('is-invalid');
          plannerShowAlert('Escribe el nombre de los novios', 'warning');
          return;
        }

        if (!city) {
          if (pasarEventoCityInput) pasarEventoCityInput.classList.add('is-invalid');
          plannerShowAlert('Escribe la ciudad del evento', 'warning');
          return;
        }

        if (!paqueteId) {
          if (pasarEventoPaqueteInput) pasarEventoPaqueteInput.classList.add('is-invalid');
          plannerShowAlert('Selecciona un paquete', 'warning');
          return;
        }

        if (montoVenta !== '') {
          const parsedMonto = Number(montoVenta);
          if (!Number.isFinite(parsedMonto) || parsedMonto < 0) {
            if (pasarEventoMontoVentaInput) pasarEventoMontoVentaInput.classList.add('is-invalid');
            plannerShowAlert('Escribe un monto de venta válido', 'warning');
            return;
          }
        }

        const payload = new URLSearchParams();
        payload.append('calendario_id', calendarioId);
        payload.append('id_asesor', asesorId);
        payload.append('fecha_registro', fechaRegistro || plannerCurrentDatetimeLocal());
        payload.append('novios', novios);
        payload.append('id_coordinador', coordinadorId || '0');
        payload.append('city', city);
        payload.append('lugar', lugar);
        payload.append('fecha', fecha);
        payload.append('modo', 'asistencia_post_q');
        payload.append('tipo_paquete', 'estandar');
        payload.append('id_paquete', paqueteId);
        payload.append('paquete_personalizado', '');
        payload.append('monto_venta', montoVenta);

        saveEventoDesdeCitaButton.disabled = true;
        saveEventoDesdeCitaButton.textContent = 'Guardando...';

        plannerRequestAsJson('guardar_evento_desde_cita_wp.php', payload)
          .then(function (data) {
            if (!data || !data.success) {
              console.warn('[guardar_evento_desde_cita_wp] Unexpected success payload', data);
              plannerShowAlert(data && data.message ? data.message : 'No se pudo guardar el evento', 'error');
              return;
            }

            if (pasarEventoModalElement && window.bootstrap) {
              const modal = bootstrap.Modal.getInstance(pasarEventoModalElement) || bootstrap.Modal.getOrCreateInstance(pasarEventoModalElement);
              modal.hide();
            }

            plannerShowAlert('El evento se registró y se pasó directo a Cotizado', 'success').then(function () {
              window.location.reload();
            });
          })
          .catch(function (error) {
            console.error('Error al guardar evento desde cita:', {
              message: error && error.message ? error.message : null,
              status: error && error.status ? error.status : null,
              statusText: error && error.statusText ? error.statusText : null,
              url: error && error.url ? error.url : null,
              rawResponse: error && error.rawResponse ? error.rawResponse : null,
              error: error
            });
            var errorMessage = (error && error.message) ? error.message : 'No se pudo convertir la cita en evento';
            plannerShowAlert(errorMessage, 'error');
          })
          .finally(function () {
            saveEventoDesdeCitaButton.disabled = false;
            saveEventoDesdeCitaButton.textContent = 'Guardar evento';
          });
      });
    }

    if (saveEventoSinCitaButton) {
      saveEventoSinCitaButton.addEventListener('click', function () {
        const novios = crearEventoSinCitaNoviosInput ? crearEventoSinCitaNoviosInput.value.trim() : '';
        const asesorId = crearEventoSinCitaAsesorInput ? crearEventoSinCitaAsesorInput.value.trim() : '';
        const coordinadorId = crearEventoSinCitaCoordinadorInput ? crearEventoSinCitaCoordinadorInput.value.trim() : '0';
        const city = crearEventoSinCitaCityInput ? crearEventoSinCitaCityInput.value.trim() : '';
        const lugar = crearEventoSinCitaLugarInput ? crearEventoSinCitaLugarInput.value.trim() : '';
        const fecha = crearEventoSinCitaFechaInput ? crearEventoSinCitaFechaInput.value.trim() : '';
        const paqueteId = crearEventoSinCitaPaqueteInput ? crearEventoSinCitaPaqueteInput.value.trim() : '';
        const montoVenta = crearEventoSinCitaMontoVentaInput ? crearEventoSinCitaMontoVentaInput.value.trim() : '';
        const fechaRegistro = crearEventoSinCitaFechaRegistroInput ? crearEventoSinCitaFechaRegistroInput.value.trim() : plannerCurrentDatetimeLocal();

        if (crearEventoSinCitaNoviosInput) crearEventoSinCitaNoviosInput.classList.remove('is-invalid');
        if (crearEventoSinCitaAsesorInput) crearEventoSinCitaAsesorInput.classList.remove('is-invalid');
        if (crearEventoSinCitaCityInput) crearEventoSinCitaCityInput.classList.remove('is-invalid');
        if (crearEventoSinCitaPaqueteInput) crearEventoSinCitaPaqueteInput.classList.remove('is-invalid');
        if (crearEventoSinCitaMontoVentaInput) crearEventoSinCitaMontoVentaInput.classList.remove('is-invalid');

        if (!novios) {
          if (crearEventoSinCitaNoviosInput) crearEventoSinCitaNoviosInput.classList.add('is-invalid');
          plannerShowAlert('Escribe el nombre de los novios', 'warning');
          return;
        }

        if (!asesorId) {
          if (crearEventoSinCitaAsesorInput) crearEventoSinCitaAsesorInput.classList.add('is-invalid');
          plannerShowAlert('Selecciona un asesor', 'warning');
          return;
        }

        if (!city) {
          if (crearEventoSinCitaCityInput) crearEventoSinCitaCityInput.classList.add('is-invalid');
          plannerShowAlert('Escribe la ciudad del evento', 'warning');
          return;
        }

        if (!paqueteId) {
          if (crearEventoSinCitaPaqueteInput) crearEventoSinCitaPaqueteInput.classList.add('is-invalid');
          plannerShowAlert('Selecciona un paquete', 'warning');
          return;
        }

        if (montoVenta !== '') {
          const parsedMonto = Number(montoVenta);
          if (!Number.isFinite(parsedMonto) || parsedMonto < 0) {
            if (crearEventoSinCitaMontoVentaInput) crearEventoSinCitaMontoVentaInput.classList.add('is-invalid');
            plannerShowAlert('Escribe un monto de venta válido', 'warning');
            return;
          }
        }

        const payload = new URLSearchParams();
        payload.append('wedding_planner_id', String(plannerId));
        payload.append('fecha_registro', fechaRegistro);
        payload.append('novios', novios);
        payload.append('id_asesor', asesorId);
        payload.append('id_coordinador', coordinadorId || '0');
        payload.append('city', city);
        payload.append('lugar', lugar);
        payload.append('fecha', fecha);
        payload.append('modo', 'sin_cita_directa');
        payload.append('tipo_paquete', 'estandar');
        payload.append('id_paquete', paqueteId);
        payload.append('paquete_personalizado', '');
        payload.append('monto_venta', montoVenta);

        const continueSave = function () {
          saveEventoSinCitaButton.disabled = true;
          saveEventoSinCitaButton.textContent = 'Guardando...';

          plannerRequestAsJson('guardar_evento_sin_cita_wp.php', payload)
            .then(function (data) {
              if (!data || !data.success) {
                plannerShowAlert(data && data.message ? data.message : 'No se pudo guardar el evento sin cita', 'error');
                return;
              }

              if (crearEventoSinCitaModalElement && window.bootstrap) {
                const modal = bootstrap.Modal.getInstance(crearEventoSinCitaModalElement) || bootstrap.Modal.getOrCreateInstance(crearEventoSinCitaModalElement);
                modal.hide();
              }

              plannerShowAlert('El evento sin cita se registró correctamente', 'success').then(function () {
                window.location.reload();
              });
            })
            .catch(function (error) {
              console.error('Error al guardar evento sin cita:', error);
              plannerShowAlert(error && error.message ? error.message : 'No se pudo guardar el evento sin cita', 'error');
            })
            .finally(function () {
              saveEventoSinCitaButton.disabled = false;
              saveEventoSinCitaButton.textContent = 'Guardar evento sin cita';
            });
        };

        if (plannerHasPendingConvertibleAppointment && window.Swal && typeof window.Swal.fire === 'function') {
          window.Swal.fire({
            icon: 'warning',
            title: 'Tienes una cita pendiente',
            text: 'Hay una cita pendiente que se puede convertir a evento desde la sección de citas. ¿Quieres crear el evento sin cita de todas formas?',
            showCancelButton: true,
            confirmButtonText: 'Sí, crear sin cita',
            cancelButtonText: 'Cancelar'
          }).then(function (result) {
            if (result && result.isConfirmed) {
              continueSave();
            }
          });
          return;
        }

        continueSave();
      });
    }

    if (saveCoordinatorButton) {
      saveCoordinatorButton.addEventListener('click', function () {
        const coordinatorId = coordinatorIdInput ? coordinatorIdInput.value.trim() : '';
        const nombre = coordinatorNameInput ? coordinatorNameInput.value.trim() : '';
        const correo = coordinatorEmailInput ? coordinatorEmailInput.value.trim() : '';
        const telefono = coordinatorPhoneInput ? coordinatorPhoneInput.value.trim() : '';

        if (coordinatorNameInput) coordinatorNameInput.classList.remove('is-invalid');
        if (coordinatorEmailInput) coordinatorEmailInput.classList.remove('is-invalid');
        if (coordinatorPhoneInput) coordinatorPhoneInput.classList.remove('is-invalid');

        if (!nombre) {
          if (coordinatorNameInput) coordinatorNameInput.classList.add('is-invalid');
          plannerShowAlert('Escribe el nombre del coordinador', 'warning');
          return;
        }

        if (!correo) {
          if (coordinatorEmailInput) coordinatorEmailInput.classList.add('is-invalid');
          plannerShowAlert('Escribe el correo del coordinador', 'warning');
          return;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
          if (coordinatorEmailInput) coordinatorEmailInput.classList.add('is-invalid');
          plannerShowAlert('Escribe un correo valido', 'warning');
          return;
        }

        if (!telefono) {
          if (coordinatorPhoneInput) coordinatorPhoneInput.classList.add('is-invalid');
          plannerShowAlert('Escribe el telefono del coordinador', 'warning');
          return;
        }

        const payload = new URLSearchParams();
        payload.append('id_wp', String(plannerId));
        payload.append('nombre', nombre);
        payload.append('correo', correo);
        payload.append('telefono', telefono);
        if (coordinatorId) {
          payload.append('id', coordinatorId);
        }

        saveCoordinatorButton.disabled = true;
        saveCoordinatorButton.textContent = 'Guardando...';

        fetch('guardar_coordinador_wp.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload.toString()
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (!data || !data.success) {
              plannerShowAlert(data && data.message ? data.message : 'No se pudo guardar el coordinador', 'error');
              return;
            }

            if (coordinatorModalElement && window.bootstrap) {
              const modal = bootstrap.Modal.getInstance(coordinatorModalElement) || bootstrap.Modal.getOrCreateInstance(coordinatorModalElement);
              modal.hide();
            }

            const successMessage = coordinatorId ? 'Coordinador actualizado correctamente' : 'Coordinador agregado correctamente';
            plannerShowAlert(successMessage, 'success').then(function () {
              window.location.reload();
            });
          })
          .catch(function (error) {
            console.error('Error guardando coordinador:', error);
            plannerShowAlert('No se pudo guardar el coordinador', 'error');
          })
          .finally(function () {
            saveCoordinatorButton.disabled = false;
            saveCoordinatorButton.textContent = 'Guardar coordinador';
          });
      });
    }

    if (saveInteractionButton) {
      saveInteractionButton.addEventListener('click', function () {
        const interactionType = interactionTypeInput ? interactionTypeInput.value.trim() : '';
        const notes = interactionNotesInput ? interactionNotesInput.value.trim() : '';
        const outcome = actionOutcomeInput ? actionOutcomeInput.value.trim() : '';
        const nextAction = actionTextInput ? actionTextInput.value.trim() : '';
        const nextActionDate = actionDateInput ? actionDateInput.value.trim() : '';

        if (interactionTypeGroup) interactionTypeGroup.classList.remove('is-invalid');
        if (interactionTypeFeedback) interactionTypeFeedback.style.display = 'none';

        if (!interactionType) {
          if (interactionTypeGroup) interactionTypeGroup.classList.add('is-invalid');
          if (interactionTypeFeedback) interactionTypeFeedback.style.display = 'block';
          plannerShowAlert('Selecciona el tipo de interaccion', 'warning');
          return;
        }

        const payload = new URLSearchParams();
        payload.append('planner_id', String(plannerId));
        payload.append('interaction_type', interactionType);
        payload.append('notes', notes);
        if (outcome) payload.append('outcome', outcome);
        if (nextAction) payload.append('next_action', nextAction);
        if (nextActionDate) payload.append('next_action_date', nextActionDate);

        saveInteractionButton.disabled = true;
        saveInteractionButton.textContent = 'Registrando...';

        fetch('guardar_interaccion_wp.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload.toString()
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (!data || !data.success) {
              plannerShowAlert(data && data.message ? data.message : 'No se pudo registrar la interaccion', 'error');
              return;
            }

            if (interactionModalElement && window.bootstrap) {
              const modal = bootstrap.Modal.getInstance(interactionModalElement) || bootstrap.Modal.getOrCreateInstance(interactionModalElement);
              modal.hide();
            }

            plannerShowAlert('Interaccion registrada correctamente', 'success').then(function () {
              window.location.reload();
            });
          })
          .catch(function (error) {
            console.error('Error guardando interaccion:', error);
            plannerShowAlert('No se pudo registrar la interaccion', 'error');
          })
          .finally(function () {
            saveInteractionButton.disabled = false;
            saveInteractionButton.textContent = 'Registrar interacción';
          });
      });
    }

    editEventButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        const serialized = button.getAttribute('data-event-edit') || '';
        let eventData = null;

        try {
          eventData = JSON.parse(serialized);
        } catch (parseError) {
          plannerShowAlert('No se pudieron leer los datos del evento', 'error');
          return;
        }

        plannerResetEditEventForm();

        if (editEventIdInput) editEventIdInput.value = String(eventData.id || '');
        if (editEventPlannerIdInput) editEventPlannerIdInput.value = String(eventData.wedding_planner_id || plannerId);
        if (editEventNoviosInput) editEventNoviosInput.value = String(eventData.novios || '');
        if (editEventCityInput) editEventCityInput.value = String(eventData.city || plannerDefaultCity || '').trim();
        if (editEventCoordinadorInput) editEventCoordinadorInput.value = String(eventData.id_coordinador || '');
        if (editEventAsesorInput) editEventAsesorInput.value = String(eventData.id_asesor || '');
        if (editEventPaqueteInput) editEventPaqueteInput.value = String(eventData.id_paquete || '');
        if (editEventMontoVentaInput) editEventMontoVentaInput.value = String(eventData.monto_venta || '');
        if (editEventLugarInput) editEventLugarInput.value = String(eventData.lugar || '');
        if (editEventFechaInput) editEventFechaInput.value = plannerNormalizeDateTimeLocal(eventData.fecha || '');
      });
    });

    if (saveEditedEventButton) {
      saveEditedEventButton.addEventListener('click', function () {
        const eventId = editEventIdInput ? String(editEventIdInput.value || '').trim() : '';
        const eventPlannerId = editEventPlannerIdInput ? String(editEventPlannerIdInput.value || '').trim() : String(plannerId);
        const novios = editEventNoviosInput ? String(editEventNoviosInput.value || '').trim() : '';
        const city = editEventCityInput ? String(editEventCityInput.value || '').trim() : '';
        const coordinadorId = editEventCoordinadorInput ? String(editEventCoordinadorInput.value || '').trim() : '';
        const asesorId = editEventAsesorInput ? String(editEventAsesorInput.value || '').trim() : '';
        const paqueteId = editEventPaqueteInput ? String(editEventPaqueteInput.value || '').trim() : '';
        const montoVenta = editEventMontoVentaInput ? String(editEventMontoVentaInput.value || '').trim() : '';
        const lugar = editEventLugarInput ? String(editEventLugarInput.value || '').trim() : '';
        const fecha = editEventFechaInput ? String(editEventFechaInput.value || '').trim() : '';

        if (editEventNoviosInput) editEventNoviosInput.classList.remove('is-invalid');
        if (editEventCityInput) editEventCityInput.classList.remove('is-invalid');
        if (editEventCoordinadorInput) editEventCoordinadorInput.classList.remove('is-invalid');
        if (editEventAsesorInput) editEventAsesorInput.classList.remove('is-invalid');
        if (editEventPaqueteInput) editEventPaqueteInput.classList.remove('is-invalid');
        if (editEventMontoVentaInput) editEventMontoVentaInput.classList.remove('is-invalid');

        if (!eventId) {
          plannerShowAlert('No se pudo identificar el evento', 'error');
          return;
        }

        if (!novios) {
          if (editEventNoviosInput) editEventNoviosInput.classList.add('is-invalid');
          plannerShowAlert('Escribe el nombre de los novios', 'warning');
          return;
        }

        if (!city) {
          if (editEventCityInput) editEventCityInput.classList.add('is-invalid');
          plannerShowAlert('Escribe la ciudad del evento', 'warning');
          return;
        }

        if (!coordinadorId) {
          if (editEventCoordinadorInput) editEventCoordinadorInput.classList.add('is-invalid');
          plannerShowAlert('Selecciona un coordinador', 'warning');
          return;
        }

        if (!asesorId) {
          if (editEventAsesorInput) editEventAsesorInput.classList.add('is-invalid');
          plannerShowAlert('Selecciona un asesor', 'warning');
          return;
        }

        if (!paqueteId) {
          if (editEventPaqueteInput) editEventPaqueteInput.classList.add('is-invalid');
          plannerShowAlert('Selecciona un paquete', 'warning');
          return;
        }

        if (montoVenta !== '') {
          const parsedMonto = Number(montoVenta);
          if (!Number.isFinite(parsedMonto) || parsedMonto < 0) {
            if (editEventMontoVentaInput) editEventMontoVentaInput.classList.add('is-invalid');
            plannerShowAlert('Escribe un monto de venta válido', 'warning');
            return;
          }
        }

        const requestPayload = new URLSearchParams();
        requestPayload.append('event_id', eventId);
        requestPayload.append('wedding_planner_id', eventPlannerId);
        requestPayload.append('novios', novios);
        requestPayload.append('city', city);
        requestPayload.append('id_coordinador', coordinadorId);
        requestPayload.append('id_asesor', asesorId);
        requestPayload.append('id_paquete', paqueteId);
        requestPayload.append('monto_venta', montoVenta);
        requestPayload.append('lugar', lugar);
        requestPayload.append('fecha', fecha);

        saveEditedEventButton.disabled = true;
        saveEditedEventButton.textContent = 'Guardando...';

        plannerRequestAsJson('editar_evento_wp_planner.php', requestPayload)
          .then(function (responseData) {
            if (!responseData || !responseData.success) {
              plannerShowAlert(responseData && responseData.message ? responseData.message : 'No se pudo actualizar el evento', 'error');
              return;
            }

            if (editarEventoPlannerModalElement && window.bootstrap) {
              const modal = bootstrap.Modal.getInstance(editarEventoPlannerModalElement) || bootstrap.Modal.getOrCreateInstance(editarEventoPlannerModalElement);
              modal.hide();
            }

            plannerShowAlert(responseData.message || 'Evento actualizado correctamente', 'success').then(function () {
              window.location.reload();
            });
          })
          .catch(function (requestError) {
            console.error('Error al editar el evento:', requestError);
            plannerShowAlert(requestError && requestError.message ? requestError.message : 'No se pudo actualizar el evento', 'error');
          })
          .finally(function () {
            saveEditedEventButton.disabled = false;
            saveEditedEventButton.textContent = 'Guardar cambios';
          });
      });
    }

    eventStatusButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        const serialized = button.getAttribute('data-event-status') || '';
        let payloadData = null;

        try {
          payloadData = JSON.parse(serialized);
        } catch (parseError) {
          plannerShowAlert('No se pudo leer el estatus del evento', 'error');
          return;
        }

        const eventId = String(payloadData.event_id || '').trim();
        const actionMode = String(payloadData.action_mode || 'direct').trim();
        const targetStatus = String(payloadData.target_status || '').trim();

        if (!eventId) {
          plannerShowAlert('No se pudo identificar el evento', 'error');
          return;
        }

        const applyEventStatusChange = function (finalTargetStatus) {
          const finalTargetStatusValue = String(finalTargetStatus || '').trim();
          if (!finalTargetStatusValue) {
            plannerShowAlert('No se pudo identificar el estatus destino', 'error');
            return;
          }

          const requestPayload = new URLSearchParams();
          requestPayload.append('event_id', eventId);
          requestPayload.append('target_status', finalTargetStatusValue);

          button.disabled = true;
          const originalText = button.textContent;
          button.textContent = 'Actualizando...';

          plannerRequestAsJson('cambiar_estatus_evento_wp_planner.php', requestPayload)
            .then(function (responseData) {
              if (!responseData || !responseData.success) {
                plannerShowAlert(responseData && responseData.message ? responseData.message : 'No se pudo actualizar el estatus', 'error');
                return;
              }

              plannerShowAlert(responseData.message || 'Estatus actualizado correctamente', 'success').then(function () {
                window.location.reload();
              });
            })
            .catch(function (requestError) {
              console.error('Error al actualizar estatus del evento:', requestError);
              plannerShowAlert(requestError && requestError.message ? requestError.message : 'No se pudo actualizar el estatus', 'error');
            })
            .finally(function () {
              button.disabled = false;
              button.textContent = originalText;
            });
        };

        if (actionMode === 'inminente_options') {
          if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
              icon: 'question',
              title: 'Cambiar de estatus',
              text: 'Selecciona el siguiente estatus para este evento.',
              showCancelButton: true,
              showDenyButton: true,
              confirmButtonText: 'Pasar a Cliente',
              denyButtonText: 'Rechazar',
              cancelButtonText: 'Cancelar'
            }).then(function (result) {
              if (!result) {
                return;
              }

              if (result.isConfirmed) {
                applyEventStatusChange('1');
                return;
              }

              if (result.isDenied) {
                applyEventStatusChange('3');
              }
            });
            return;
          }

          const fallbackSelection = window.prompt('Escribe 1 para Pasar a Cliente o 3 para Rechazar.');
          if (fallbackSelection === '1' || fallbackSelection === '3') {
            applyEventStatusChange(fallbackSelection);
          }
          return;
        }

        if (!targetStatus) {
          plannerShowAlert('No se pudo identificar el estatus destino', 'error');
          return;
        }

        const targetLabel = targetStatus === '2' ? 'Cotizado' : (targetStatus === '1' ? 'Cliente' : (targetStatus === '3' ? 'Rechazado' : (targetStatus === '4' ? 'Cliente inminente' : 'Estatus seleccionado')));
        const confirmPromise = (window.Swal && typeof window.Swal.fire === 'function')
          ? window.Swal.fire({
              icon: 'question',
              title: 'Cambiar estatus',
              text: 'Este evento se actualizará a ' + targetLabel + '. ¿Deseas continuar?',
              showCancelButton: true,
              confirmButtonText: 'Sí, actualizar',
              cancelButtonText: 'Cancelar'
            })
          : Promise.resolve({ isConfirmed: window.confirm('Este evento se actualizará a ' + targetLabel + '. ¿Deseas continuar?') });

        confirmPromise.then(function (result) {
          if (!result || !result.isConfirmed) {
            return;
          }

          applyEventStatusChange(targetStatus);
        });
      });
    });

    // Cita status change modal
    const cambiarEstatusModal = document.getElementById('cambiarEstatusCitaModal');
    const cambiarEstatusCalendarioIdInput = document.getElementById('cambiarEstatusCalendarioId');
    const cambiarEstatusActualInput = document.getElementById('cambiarEstatusActual');

    document.querySelectorAll('.js-abrir-cambiar-estatus').forEach(function (btn) {
      btn.addEventListener('click', function () {
        cambiarEstatusCalendarioIdInput.value = btn.dataset.calendarioId || '';
        cambiarEstatusActualInput.value = btn.dataset.estatusActual || '0';
        if (cambiarEstatusModal && window.bootstrap) {
          bootstrap.Modal.getOrCreateInstance(cambiarEstatusModal).show();
        }
      });
    });

    function applyCitaEstatus(estatus) {
      const calendarioId = intval(cambiarEstatusCalendarioIdInput.value);
      const label = estatus === 1 ? 'Atendido' : 'Muerto';
      const triggerBtn = document.querySelector('.js-abrir-cambiar-estatus[data-calendario-id="' + calendarioId + '"]');

      if (cambiarEstatusModal && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(cambiarEstatusModal).hide();
      }

      const fd = new FormData();
      fd.append('calendario_id', calendarioId);
      fd.append('estatus', estatus);

      fetch('actualizar_estatus_cita_wp.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.success) {
            plannerShowAlert(data && data.message ? data.message : 'No se pudo actualizar la cita', 'error');
            return;
          }

          const item = triggerBtn ? triggerBtn.closest('.appointment-item') : null;
          if (item) {
            const badge = item.querySelector('.js-cita-status-badge');
            if (badge) {
              badge.textContent = label;
              badge.className = 'js-cita-status-badge status-badge ' + (estatus === 1 ? 'status-afianzado' : 'status-rechazado');
            }

            const actions = item.querySelector('.card-actions');
            if (actions) {
              if (estatus === 1) {
                // Muerto button gone, Cambiar estatus remains but update its data, show Pasar a evento
                if (triggerBtn) triggerBtn.dataset.estatusActual = '1';
                // Build Pasar a evento button from existing payload if available
                const existingPasar = item.querySelector('.js-pasar-evento');
                if (!existingPasar) {
                  const pasarBtn = document.createElement('button');
                  pasarBtn.className = 'btn btn-dark btn-sm js-pasar-evento';
                  pasarBtn.type = 'button';
                  pasarBtn.setAttribute('data-bs-toggle', 'modal');
                  pasarBtn.setAttribute('data-bs-target', '#pasarEventoModal');
                  pasarBtn.textContent = 'Pasar a evento';
                  // copy data-appointment from the trigger's sibling if it existed before, or reconstruct
                  // The payload was stored in a JS var on the original button; re-read from item
                  const storedPayload = item.dataset.appointmentPayload || '';
                  if (storedPayload) pasarBtn.setAttribute('data-appointment', storedPayload);
                  actions.appendChild(pasarBtn);
                  // Re-attach listener
                  // Bootstrap handles the modal open via data-bs-toggle/target attributes automatically
                }
              } else {
                // Muerto: hide Cambiar estatus
                if (triggerBtn) triggerBtn.remove();
              }
            }
          }
        })
        .catch(function () {
          plannerShowAlert('No se pudo actualizar la cita', 'error');
        });
    }

    const btnCitaAtendido = document.getElementById('btnCitaAtendido');
    const btnCitaMuerto = document.getElementById('btnCitaMuerto');
    if (btnCitaAtendido) btnCitaAtendido.addEventListener('click', function () { applyCitaEstatus(1); });
    if (btnCitaMuerto) btnCitaMuerto.addEventListener('click', function () { applyCitaEstatus(3); });

    function intval(v) { return parseInt(v, 10) || 0; }
  });
</script>

<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.pp-btn-complete');
    if (!btn) return;
    var id = parseInt(btn.dataset.interactionId, 10);
    if (!id) return;
    btn.disabled = true;
    btn.textContent = 'Guardando…';
    fetch('complete_interaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.ok) {
            var meta = btn.closest('.appointment-meta');
            btn.remove();
            var badge = document.createElement('span');
            badge.className = 'pp-completed-badge';
            badge.textContent = '✓ Completada';
            meta.appendChild(badge);
        } else {
            btn.disabled = false;
            btn.textContent = 'Marcar completada';
            alert('No se pudo marcar como completada.');
        }
    })
    .catch(function () {
        btn.disabled = false;
        btn.textContent = 'Marcar completada';
        alert('Error de red.');
    });
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
</body>
</html>