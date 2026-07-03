<?php

require_once __DIR__ . '/wp_eventos_contact_form_helper.php';
require_once __DIR__ . '/dashboard_comercial_helper.php';

if (!function_exists('plannerProfileFormatDateTime')) {
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

        return $day . ' ' . $month . ' ' . $year . ' - ' . $hour;
    }
}

if (!function_exists('plannerProfileEscapeHtml')) {
    function plannerProfileEscapeHtml($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('plannerProfilePickRegistroDateField')) {
    function plannerProfilePickRegistroDateField(array $eventItem, array $fields): string
    {
        foreach ($fields as $field) {
            $raw = trim((string) ($eventItem[$field] ?? ''));
            if ($raw !== '' && strpos($raw, '0000-00-00') !== 0) {
                return $raw;
            }
        }

        return '';
    }
}

if (!function_exists('plannerProfileResolveEventRegistroDate')) {
    /**
     * Fecha de registro alineada con consulta_leads.php:
     * - tablas_leads / Cliente Final: created_time (o created_at) del lead original, luego contact_form.
     * - eventos_wp: eventos_wp.fecha_registro primero, luego contact_form.created_time / submission_date.
     */
    function plannerProfileResolveEventRegistroDate(array $eventItem)
    {
        $tablaOrigen = strtolower(trim((string) ($eventItem['tabla_origen'] ?? '')));
        $isEventosWpContext = (
            $tablaOrigen === 'eventos_wp'
            || trim((string) ($eventItem['evento_fecha_registro'] ?? '')) !== ''
            || trim((string) ($eventItem['evento_created_time'] ?? '')) !== ''
        );

        if ($isEventosWpContext) {
            return plannerProfilePickRegistroDateField($eventItem, [
                'fecha_registro',
                'evento_fecha_registro',
                'created_time',
                'contact_form_created_time',
                'submission_date',
                'contact_form_submission_date',
                'evento_created_time',
            ]);
        }

        return plannerProfilePickRegistroDateField($eventItem, [
            'created_time',
            'created_at',
            'contact_form_created_time',
            'submission_date',
            'contact_form_submission_date',
        ]);
    }
}

if (!function_exists('plannerProfileResolveEventAgendaDate')) {
    function plannerProfileResolveEventAgendaDate(array $eventItem)
    {
        foreach (['evento_fecha', 'fecha'] as $field) {
            $raw = trim((string) ($eventItem[$field] ?? ''));
            if ($raw !== '' && strpos($raw, '0000-00-00') !== 0) {
                return $raw;
            }
        }

        return '';
    }
}

if (!function_exists('plannerProfileFormatDateTimeDisplay')) {
    function plannerProfileFormatDateTimeDisplay($dateValue, $timeValue = '')
    {
        return plannerProfileEscapeHtml(plannerProfileFormatDateTime($dateValue, $timeValue));
    }
}

if (!function_exists('plannerProfileEventStatusMeta')) {
    function plannerProfileEventStatusMeta($value, $contactFormCliente = null)
    {
        $statusKey = wpPlannerResolveEventStatusKey($value, $contactFormCliente);

        return wpPlannerEventStatusPresentation($statusKey);
    }
}

if (!function_exists('plannerProfileEventAttendedStatusKeys')) {
    function plannerProfileEventAttendedStatusKeys()
    {
        return ['atendido', 'cliente_inminente', 'cliente'];
    }
}

if (!function_exists('plannerProfileEventCountsAsAttended')) {
    function plannerProfileEventCountsAsAttended(array $eventItem)
    {
        $statusKey = wpPlannerResolveEventStatusKey(
            $eventItem['estatus'] ?? '',
            $eventItem['contact_form_cliente'] ?? null
        );
        if (in_array($statusKey, plannerProfileEventAttendedStatusKeys(), true)) {
            return true;
        }

        $rawStatus = mb_strtolower(trim((string) ($eventItem['estatus'] ?? '')), 'UTF-8');
        if (in_array($rawStatus, ['3', 'muerto', 'rechazado'], true)) {
            return true;
        }

        $contactFormCliente = (int) ($eventItem['contact_form_cliente'] ?? 0);
        return in_array($contactFormCliente, [1, 3], true);
    }
}

if (!function_exists('plannerProfileEventCotizadoStatusKeys')) {
    function plannerProfileEventCotizadoStatusKeys()
    {
        return ['cotizado', 'atendido', 'cliente_inminente', 'cliente'];
    }
}

if (!function_exists('plannerProfileResolveEventCotizadoDate')) {
    function plannerProfileResolveEventCotizadoDate(array $eventItem)
    {
        $statusKey = wpPlannerResolveEventStatusKey(
            $eventItem['estatus'] ?? '',
            $eventItem['contact_form_cliente'] ?? null
        );
        if (!in_array($statusKey, plannerProfileEventCotizadoStatusKeys(), true)) {
            return '';
        }

        $raw = trim((string) ($eventItem['fecha_cotizado'] ?? ''));
        if ($raw !== '' && strpos($raw, '0000-00-00') !== 0) {
            return $raw;
        }

        return '';
    }
}

if (!function_exists('plannerProfileEventCanEditCotizadoDate')) {
    function plannerProfileEventCanEditCotizadoDate(array $eventItem)
    {
        $statusKey = wpPlannerResolveEventStatusKey(
            $eventItem['estatus'] ?? '',
            $eventItem['contact_form_cliente'] ?? null
        );

        return in_array($statusKey, plannerProfileEventCotizadoStatusKeys(), true);
    }
}

if (!function_exists('plannerProfileResolveEventAttendedDate')) {
    function plannerProfileResolveEventAttendedDate(array $eventItem)
    {
        $statusKey = wpPlannerResolveEventStatusKey(
            $eventItem['estatus'] ?? '',
            $eventItem['contact_form_cliente'] ?? null
        );
        if (!in_array($statusKey, plannerProfileEventAttendedStatusKeys(), true)) {
            return '';
        }

        $raw = trim((string) ($eventItem['fecha_atendido'] ?? ''));
        if ($raw !== '' && strpos($raw, '0000-00-00') !== 0) {
            return $raw;
        }

        return '';
    }
}

if (!function_exists('plannerProfileEventCanEditAttendedDate')) {
    function plannerProfileEventCanEditAttendedDate(array $eventItem)
    {
        $statusKey = wpPlannerResolveEventStatusKey(
            $eventItem['estatus'] ?? '',
            $eventItem['contact_form_cliente'] ?? null
        );

        return in_array($statusKey, plannerProfileEventAttendedStatusKeys(), true);
    }
}

if (!function_exists('plannerProfileDateTimeLocalInputValue')) {
    function plannerProfileDateTimeLocalInputValue($rawValue)
    {
        $raw = trim((string) $rawValue);
        if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
            return '';
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d\TH:i', $timestamp);
    }
}

if (!function_exists('plannerProfileResolveEventClienteDate')) {
    function plannerProfileResolveEventClienteDate(array $eventItem)
    {
        $statusKey = wpPlannerResolveEventStatusKey(
            $eventItem['estatus'] ?? '',
            $eventItem['contact_form_cliente'] ?? null
        );
        if ($statusKey !== 'cliente') {
            return '';
        }

        $fechaCambio = trim((string) ($eventItem['contact_form_fecha_cambio_cliente'] ?? ''));
        if ($fechaCambio !== '' && strpos($fechaCambio, '0000-00-00') !== 0) {
            return $fechaCambio;
        }

        $fechaCliente = trim((string) ($eventItem['fecha_cliente'] ?? ''));
        if ($fechaCliente !== '' && strpos($fechaCliente, '0000-00-00') !== 0) {
            return $fechaCliente;
        }

        $fechaActualizacion = trim((string) ($eventItem['fecha_actualizacion_estatus'] ?? ''));
        if ($fechaActualizacion !== '' && strpos($fechaActualizacion, '0000-00-00') !== 0) {
            return $fechaActualizacion;
        }

        return '';
    }
}

if (!function_exists('plannerProfileEventCanEditClienteDate')) {
    function plannerProfileEventCanEditClienteDate(array $eventItem)
    {
        $statusKey = wpPlannerResolveEventStatusKey(
            $eventItem['estatus'] ?? '',
            $eventItem['contact_form_cliente'] ?? null
        );

        return $statusKey === 'cliente';
    }
}

if (!function_exists('plannerProfileResolveTipoClienteLabel')) {
    function plannerProfileResolveTipoClienteLabel(array $row)
    {
        require_once __DIR__ . '/lead_origin_helper.php';

        if (wpEventosCfIsContactForm($row) || strtolower(trim((string) ($row['tabla_origen'] ?? ''))) === 'eventos_wp') {
            $tipo = mapTipoClienteValueForOrigin($row['tipo_cliente'] ?? $row['contact_form_tipo_cliente'] ?? '');
            if ($tipo !== '') {
                return $tipo;
            }

            return 'Wedding Planner';
        }

        $tipo = mapTipoClienteValueForOrigin($row['tipo_cliente'] ?? '');
        if ($tipo !== '') {
            return $tipo;
        }

        $howDidYouMeet = trim((string) ($row['how_did_you_meet'] ?? ''));
        if ($howDidYouMeet === '1') {
            return 'Wedding Planner';
        }
        if (in_array($howDidYouMeet, ['2', '3'], true)) {
            return 'Cliente Final';
        }

        $tablaOrigen = strtolower(trim((string) ($row['tabla_origen'] ?? '')));
        if (in_array($tablaOrigen, ['wedding_planners', 'wedding_planner', 'wp_citas_leads'], true)) {
            return 'Wedding Planner';
        }

        return 'Cliente Final';
    }
}

if (!function_exists('plannerProfileLeadStatusMeta')) {
    function plannerProfileLeadStatusMeta($estatusKey)
    {
        $key = strtolower(trim((string) $estatusKey));
        $map = [
            'cliente' => ['label' => 'Cliente', 'class' => 'status-afianzado'],
            'rechazado' => ['label' => 'Rechazado', 'class' => 'status-rechazado'],
            'cotizado' => ['label' => 'Cotizado', 'class' => 'status-pendiente'],
            'cliente_inminente' => ['label' => 'Cliente inminente', 'class' => 'status-inminente'],
            'atendido' => ['label' => 'Atendido', 'class' => 'status-afianzado'],
            'agendado' => ['label' => 'Agendado', 'class' => 'status-pendiente'],
            'muerto' => ['label' => 'Muerto', 'class' => 'status-rechazado'],
            'fantasma' => ['label' => 'Fantasma', 'class' => 'status-pendiente'],
            'pendiente' => ['label' => 'Pendiente', 'class' => 'status-pendiente'],
        ];

        return $map[$key] ?? wpPlannerEventStatusPresentation($key);
    }
}

if (!function_exists('plannerProfileBatchLoadFechaAtencionByContactFormIds')) {
    /**
     * @param int[] $contactFormIds
     * @return array<int, string>
     */
    function plannerProfileBatchLoadFechaAtencionByContactFormIds($conn, array $contactFormIds)
    {
        $map = [];
        $contactFormIds = array_values(array_unique(array_filter(array_map('intval', $contactFormIds), function ($id) {
            return $id > 0;
        })));
        if (empty($contactFormIds)) {
            return $map;
        }

        $historialHelper = __DIR__ . '/calendario_estatus_historial_helper.php';
        if (is_file($historialHelper)) {
            require_once $historialHelper;
        }
        if (function_exists('ensureCalendarioEstatusHistorialTable')) {
            ensureCalendarioEstatusHistorialTable($conn);
        }

        foreach (array_chunk($contactFormIds, 500) as $chunk) {
            $idsList = implode(',', $chunk);
            $sql = "SELECT c.idclie, MIN(h.fecha_cambio) AS fecha_atencion
                    FROM calendario_estatus_historial h
                    INNER JOIN calendario c ON c.id = h.id_calendario
                    WHERE CAST(h.estatus AS SIGNED) = 1
                      AND c.idclie IN ($idsList)
                      AND COALESCE(c.eliminado, 0) = 0
                    GROUP BY c.idclie";
            $res = $conn->query($sql);
            if (!$res) {
                continue;
            }
            while ($row = $res->fetch_assoc()) {
                $id = (int) ($row['idclie'] ?? 0);
                $fecha = trim((string) ($row['fecha_atencion'] ?? ''));
                if ($id > 0 && $fecha !== '' && strpos($fecha, '0000-00-00') !== 0) {
                    $map[$id] = $fecha;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('plannerProfileBatchLoadCurrentHistorialEstatusByCalendarioIds')) {
    /**
     * Estatus actual de cada cita: último registro en calendario_estatus_historial.
     *
     * @param int[] $calendarioIds
     * @return array<int, int> id_calendario => código estatus
     */
    function plannerProfileBatchLoadCurrentHistorialEstatusByCalendarioIds($conn, array $calendarioIds)
    {
        $map = [];
        $calendarioIds = array_values(array_unique(array_filter(array_map('intval', $calendarioIds), static function ($id) {
            return $id > 0;
        })));
        if (empty($calendarioIds)) {
            return $map;
        }

        $historialHelper = __DIR__ . '/calendario_estatus_historial_helper.php';
        if (is_file($historialHelper)) {
            require_once $historialHelper;
        }
        if (function_exists('ensureCalendarioEstatusHistorialTable')) {
            ensureCalendarioEstatusHistorialTable($conn);
        }

        foreach (array_chunk($calendarioIds, 500) as $chunk) {
            $idsList = implode(',', $chunk);
            $sql = "SELECT h.id_calendario, CAST(h.estatus AS SIGNED) AS estatus_code
                    FROM calendario_estatus_historial h
                    INNER JOIN (
                        SELECT id_calendario, MAX(id) AS latest_id
                        FROM calendario_estatus_historial
                        WHERE id_calendario IN ($idsList)
                        GROUP BY id_calendario
                    ) latest ON latest.latest_id = h.id";
            $res = $conn->query($sql);
            if (!$res) {
                continue;
            }
            while ($row = $res->fetch_assoc()) {
                $calId = (int) ($row['id_calendario'] ?? 0);
                $code = (int) ($row['estatus_code'] ?? -1);
                if ($calId > 0 && $code >= 0) {
                    $map[$calId] = $code;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('plannerProfileBatchLoadFechaHistorialByContactFormIds')) {
    /**
     * Primera fecha_cambio en calendario_estatus_historial por contact_form y códigos de estatus.
     *
     * @param int[] $contactFormIds
     * @param int[] $estatusCodes
     * @return array<int, string>
     */
    function plannerProfileBatchLoadFechaHistorialByContactFormIds($conn, array $contactFormIds, array $estatusCodes)
    {
        $map = [];
        $contactFormIds = array_values(array_unique(array_filter(array_map('intval', $contactFormIds), static function ($id) {
            return $id > 0;
        })));
        $estatusCodes = array_values(array_unique(array_filter(array_map('intval', $estatusCodes), static function ($code) {
            return $code >= 0;
        })));
        if (empty($contactFormIds) || empty($estatusCodes)) {
            return $map;
        }

        $historialHelper = __DIR__ . '/calendario_estatus_historial_helper.php';
        if (is_file($historialHelper)) {
            require_once $historialHelper;
        }
        if (function_exists('ensureCalendarioEstatusHistorialTable')) {
            ensureCalendarioEstatusHistorialTable($conn);
        }

        $statusList = implode(',', $estatusCodes);
        foreach (array_chunk($contactFormIds, 500) as $chunk) {
            $idsList = implode(',', $chunk);
            $sql = "SELECT c.idclie, MIN(h.fecha_cambio) AS fecha_estatus
                    FROM calendario_estatus_historial h
                    INNER JOIN calendario c ON c.id = h.id_calendario
                    WHERE CAST(h.estatus AS SIGNED) IN ($statusList)
                      AND c.idclie IN ($idsList)
                      AND COALESCE(c.eliminado, 0) = 0
                    GROUP BY c.idclie";
            $res = $conn->query($sql);
            if (!$res) {
                continue;
            }
            while ($row = $res->fetch_assoc()) {
                $id = (int) ($row['idclie'] ?? 0);
                $fecha = trim((string) ($row['fecha_estatus'] ?? ''));
                if ($id > 0 && $fecha !== '' && strpos($fecha, '0000-00-00') !== 0) {
                    $map[$id] = $fecha;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('plannerProfileResolveClienteFinalAttendedDate')) {
    function plannerProfileResolveClienteFinalAttendedDate(array $row, array $fechaAtencionByContactFormId = [])
    {
        $cfId = (int) ($row['id'] ?? $row['contact_form_id'] ?? 0);
        if ($cfId > 0 && !empty($fechaAtencionByContactFormId[$cfId])) {
            return $fechaAtencionByContactFormId[$cfId];
        }

        $estatus = strtolower(trim((string) ($row['estatus'] ?? '')));
        $isCliente = ($estatus === 'cliente' || (int) ($row['cliente'] ?? 0) === 1);
        if ($isCliente) {
            return '';
        }

        if ($estatus !== 'atendido') {
            return '';
        }

        foreach (['created_time', 'submission_date'] as $field) {
            $raw = trim((string) ($row[$field] ?? ''));
            if ($raw !== '' && strpos($raw, '0000-00-00') !== 0) {
                return $raw;
            }
        }

        return '';
    }
}

if (!function_exists('plannerProfileResolveClienteFinalClienteDate')) {
    function plannerProfileResolveClienteFinalClienteDate(array $row)
    {
        $estatus = strtolower(trim((string) ($row['estatus'] ?? '')));
        $clienteField = (int) ($row['cliente'] ?? 0);
        $tablaOrigen = strtolower(trim((string) ($row['tabla_origen'] ?? '')));
        $isWpContext = (
            $tablaOrigen === 'eventos_wp'
            || (function_exists('wpEventosCfIsContactForm') && wpEventosCfIsContactForm($row))
        );
        $isCliente = ($clienteField === 1 || $estatus === 'cliente' || ($clienteField >= 2 && $isWpContext));
        if (!$isCliente) {
            return '';
        }

        $fechaCambio = trim((string) ($row['fecha_cambio_cliente'] ?? ''));
        if ($fechaCambio !== '' && strpos($fechaCambio, '0000-00-00') !== 0) {
            return $fechaCambio;
        }

        return '';
    }
}

if (!function_exists('plannerProfileResolveClienteFinalAgendaDate')) {
    function plannerProfileResolveClienteFinalAgendaDate(array $row, ?array $appt = null)
    {
        if (is_array($appt)) {
            $fecha = trim((string) ($appt['fecha'] ?? ''));
            $hora = trim((string) ($appt['hora'] ?? ''));
            if ($fecha !== '' && strpos($fecha, '0000-00-00') !== 0) {
                return trim($fecha . ' ' . $hora);
            }
        }

        $dateAppointment = trim((string) ($row['date_appointment'] ?? ''));
        $timeAppointment = trim((string) ($row['time_appointment'] ?? ''));
        if ($dateAppointment !== '' && strpos($dateAppointment, '0000-00-00') !== 0) {
            return trim($dateAppointment . ' ' . $timeAppointment);
        }

        return '';
    }
}

if (!function_exists('plannerProfileResolveAgendaCohortDate')) {
    /**
     * Fecha de cohorte para consulta_agendados_leads: cuándo se agendó la sesión.
     * Prioridad alineada con backfill_estatus_historial (estatus agendado).
     */
    function plannerProfileResolveAgendaCohortDate(array $row, ?array $appt = null)
    {
        $tablaOrigen = strtolower(trim((string) ($row['tabla_origen'] ?? '')));
        $isWpContext = (
            $tablaOrigen === 'eventos_wp'
            || (function_exists('wpEventosCfIsContactForm') && wpEventosCfIsContactForm($row))
            || trim((string) ($row['evento_fecha_registro'] ?? '')) !== ''
        );
        if ($isWpContext) {
            $fechaAgendado = trim((string) ($row['fecha_agendado'] ?? ''));
            if ($fechaAgendado !== '' && strpos($fechaAgendado, '0000-00-00') !== 0) {
                return $fechaAgendado;
            }

            if (function_exists('plannerProfileResolveEventCotizadoDate')) {
                $cotizadoDate = plannerProfileResolveEventCotizadoDate(array_merge($row, [
                    'estatus' => $row['eventos_wp_estatus'] ?? $row['estatus'] ?? '',
                    'contact_form_cliente' => $row['cliente'] ?? null,
                ]));
                if ($cotizadoDate !== '') {
                    return $cotizadoDate;
                }
            }
        }

        if (is_array($appt)) {
            $fechaRegistro = trim((string) ($appt['fecha_registro'] ?? ''));
            if ($fechaRegistro !== '' && strpos($fechaRegistro, '0000-00-00') !== 0) {
                return $fechaRegistro;
            }
        }

        $dateAppointment = trim((string) ($row['date_appointment'] ?? ''));
        $timeAppointment = trim((string) ($row['time_appointment'] ?? ''));
        if ($dateAppointment !== '' && strpos($dateAppointment, '0000-00-00') !== 0) {
            return trim($dateAppointment . ' ' . $timeAppointment);
        }

        $slotDate = plannerProfileResolveClienteFinalAgendaDate($row, $appt);
        if ($slotDate !== '') {
            return $slotDate;
        }

        if ($isWpContext) {
            $eventRegistro = plannerProfileResolveEventRegistroDate($row);
            if ($eventRegistro !== '') {
                return $eventRegistro;
            }
        }

        foreach (['submission_date', 'contact_form_submission_date'] as $field) {
            $raw = trim((string) ($row[$field] ?? ''));
            if ($raw !== '' && strpos($raw, '0000-00-00') !== 0) {
                return $raw;
            }
        }

        return '';
    }
}

if (!function_exists('plannerProfileResolvePostCohortDate')) {
    /**
     * Fecha de cohorte para consulta_post_leads: cuándo pasó a atendido (post-Q).
     * Aplica a todos los estatus, incluido Cliente (no usa fecha_cambio_cliente).
     * - Comercial: primera fecha atendido en calendario_estatus_historial
     * - WP / eventos_wp: fecha de atención o registro del evento
     */
    function plannerProfileResolvePostCohortDate(array $row, array $fechaAtencionByContactFormId = [], ?array $appt = null)
    {
        $estatus = strtolower(trim((string) ($row['estatus'] ?? '')));
        $clienteField = (int) ($row['cliente'] ?? 0);
        $tablaOrigen = strtolower(trim((string) ($row['tabla_origen'] ?? '')));
        $isWpContext = (
            $tablaOrigen === 'eventos_wp'
            || (function_exists('wpEventosCfIsContactForm') && wpEventosCfIsContactForm($row))
        );
        $isCliente = ($estatus === 'cliente' || $clienteField === 1 || ($clienteField >= 2 && $isWpContext));

        if ($isWpContext) {
            if (function_exists('wpEventResolveFechaAtendido')) {
                $fechaAtendido = wpEventResolveFechaAtendido($row);
                if ($fechaAtendido !== '') {
                    return $fechaAtendido;
                }
            }

            $attended = plannerProfileResolveClienteFinalAttendedDate($row, $fechaAtencionByContactFormId);
            if ($attended !== '') {
                return $attended;
            }

            $eventRegistro = plannerProfileResolveEventRegistroDate($row);
            if ($eventRegistro !== '') {
                return $eventRegistro;
            }

            if (function_exists('wpEventosCfResolveDateField')) {
                return wpEventosCfResolveDateField($row);
            }

            return '';
        }

        if (in_array($estatus, ['muerto', 'fantasma'], true)) {
            if (is_array($appt)) {
                $fecha = trim((string) ($appt['fecha'] ?? ''));
                $hora = trim((string) ($appt['hora'] ?? ''));
                if ($fecha !== '' && strpos($fecha, '0000-00-00') !== 0) {
                    return trim($fecha . ' ' . $hora);
                }
            }

            if (function_exists('plannerProfileResolveAgendaCohortDate')) {
                $agendaDate = plannerProfileResolveAgendaCohortDate($row, $appt);
                if ($agendaDate !== '') {
                    return $agendaDate;
                }
            }
        }

        $attended = plannerProfileResolveClienteFinalAttendedDate($row, $fechaAtencionByContactFormId);
        if ($attended !== '') {
            return $attended;
        }

        // Sin historial de atención: no usar cita como proxy si ya es cliente.
        if (!$isCliente && is_array($appt)) {
            $fecha = trim((string) ($appt['fecha'] ?? ''));
            $hora = trim((string) ($appt['hora'] ?? ''));
            if ($fecha !== '' && strpos($fecha, '0000-00-00') !== 0) {
                return trim($fecha . ' ' . $hora);
            }
        }

        return '';
    }
}

if (!function_exists('plannerProfileResolveAsesorNombre')) {
    function plannerProfileResolveAsesorNombre($conn, int $asesorId)
    {
        if ($asesorId <= 0) {
            return 'Sin asignar';
        }

        try {
            $stmt = $conn->prepare('SELECT nombre, apepat FROM usuarios WHERE id = ? LIMIT 1');
        } catch (\Throwable $e) {
            error_log('plannerProfileResolveAsesorNombre prepare error for asesor #' . $asesorId . ': ' . $e->getMessage());
            return 'Sin asignar';
        }
        if (!$stmt) {
            return 'Sin asignar';
        }
        $stmt->bind_param('i', $asesorId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        $label = trim((string) (($row['nombre'] ?? '') . ' ' . ($row['apepat'] ?? '')));

        return $label !== '' ? $label : 'Sin asignar';
    }
}

if (!function_exists('provisionalNormalizeEventoWpRow')) {
    function provisionalNormalizeEventoWpRow(array $eventItem, array $fechaAtencionByContactFormId = [])
    {
        $eventId = (int) ($eventItem['id'] ?? 0);
        $contactFormId = (int) ($eventItem['contact_form_id'] ?? 0);
        $statusKey = wpPlannerResolveEventStatusKey($eventItem['estatus'] ?? '', $eventItem['contact_form_cliente'] ?? null);
        $eventStatus = plannerProfileEventStatusMeta($eventItem['estatus'] ?? '', $eventItem['contact_form_cliente'] ?? null);
        $eventAttendedDate = plannerProfileResolveEventAttendedDate($eventItem);
        if ($contactFormId > 0 && !empty($fechaAtencionByContactFormId[$contactFormId])) {
            $eventAttendedDate = $fechaAtencionByContactFormId[$contactFormId];
        }
        $eventCanEditAttendedDate = plannerProfileEventCanEditAttendedDate($eventItem);
        $eventAttendedDateInput = plannerProfileDateTimeLocalInputValue($eventAttendedDate);
        $eventClienteDate = plannerProfileResolveEventClienteDate($eventItem);
        $eventCanEditClienteDate = plannerProfileEventCanEditClienteDate($eventItem);
        $eventClienteDateInput = plannerProfileDateTimeLocalInputValue($eventClienteDate);
        $registroDate = plannerProfileResolveEventRegistroDate($eventItem);
        $agendaDate = plannerProfileResolveEventAgendaDate($eventItem);
        $noviosLabel = trim((string) ($eventItem['novios'] ?? ''));
        if ($noviosLabel === '') {
            $noviosLabel = 'Sin novios';
        }
        $wpLabel = trim((string) ($eventItem['wedding_planner'] ?? ''));
        if ($wpLabel === '') {
            $wpLabel = 'WP #' . (int) ($eventItem['wedding_planner_id'] ?? 0);
        }
        $asesorLabel = trim((string) ($eventItem['asesor_nombre'] ?? ''));
        if ($asesorLabel === '') {
            $asesorLabel = 'Sin asignar';
        }
        $eventLabel = trim((string) ($eventItem['novios'] ?? '')) !== ''
            ? $eventItem['novios']
            : ('Evento #' . $eventId);
        $tipoClienteLabel = plannerProfileResolveTipoClienteLabel(array_merge($eventItem, [
            'tabla_origen' => 'eventos_wp',
            'tipo_cliente' => $eventItem['contact_form_tipo_cliente'] ?? $eventItem['tipo_cliente'] ?? null,
        ]));

        return [
            'record_type' => 'evento_wp',
            'row_key' => 'ev:' . $eventId,
            'event_id' => $eventId,
            'contact_form_id' => $contactFormId,
            'tipo_cliente_label' => $tipoClienteLabel,
            'wp_label' => $wpLabel,
            'novios_label' => $noviosLabel,
            'status_key' => $statusKey,
            'event_status' => $eventStatus,
            'registro_date' => $registroDate,
            'agenda_date' => $agendaDate,
            'attended_date' => $eventAttendedDate,
            'can_edit_attended' => $eventCanEditAttendedDate,
            'attended_date_input' => $eventAttendedDateInput,
            'cliente_date' => $eventClienteDate,
            'can_edit_cliente' => $eventCanEditClienteDate,
            'cliente_date_input' => $eventClienteDateInput,
            'asesor_label' => $asesorLabel,
            'event_label' => $eventLabel,
            'sort_date' => $registroDate !== '' ? $registroDate : ($agendaDate !== '' ? $agendaDate : ''),
        ];
    }
}

if (!function_exists('provisionalNormalizeClienteFinalRow')) {
    function provisionalNormalizeClienteFinalRow($conn, array $lead, ?array $appt, array $fechaAtencionByContactFormId = [])
    {
        $contactFormId = (int) ($lead['id'] ?? 0);
        $estatusKey = strtolower(trim((string) ($lead['estatus'] ?? '')));
        $eventStatus = plannerProfileLeadStatusMeta($estatusKey);
        $registroDate = plannerProfileResolveEventRegistroDate($lead);
        $agendaDate = plannerProfileResolveClienteFinalAgendaDate($lead, $appt);
        $attendedDate = plannerProfileResolveClienteFinalAttendedDate($lead, $fechaAtencionByContactFormId);
        $clienteDate = plannerProfileResolveClienteFinalClienteDate($lead);
        $nameLabel = trim((string) ($lead['full_name'] ?? $lead['names'] ?? ''));
        if ($nameLabel === '') {
            $nameLabel = 'Sin nombre';
        }
        $asesorId = (int) ($lead['id_vendedor_asignado'] ?? 0);
        if ($asesorId <= 0 && is_array($appt)) {
            $asesorId = (int) ($appt['idusu'] ?? 0);
        }

        return [
            'record_type' => 'cliente_final',
            'row_key' => 'cf:' . $contactFormId,
            'event_id' => 0,
            'contact_form_id' => $contactFormId,
            'tipo_cliente_label' => 'Cliente Final',
            'wp_label' => '—',
            'novios_label' => $nameLabel,
            'status_key' => $estatusKey,
            'event_status' => $eventStatus,
            'registro_date' => $registroDate,
            'agenda_date' => $agendaDate,
            'attended_date' => $attendedDate,
            'can_edit_attended' => false,
            'attended_date_input' => '',
            'cliente_date' => $clienteDate,
            'can_edit_cliente' => false,
            'cliente_date_input' => '',
            'asesor_label' => plannerProfileResolveAsesorNombre($conn, $asesorId),
            'event_label' => $nameLabel,
            'sort_date' => $registroDate !== '' ? $registroDate : ($agendaDate !== '' ? $agendaDate : ''),
        ];
    }
}

if (!function_exists('provisionalFetchAllRecordsForList')) {
    /**
     * Eventos WP (flujo planner) + clientes finales con cita en contact_form.
     *
     * @return array<int, array<string, mixed>>
     */
    function provisionalFetchAllRecordsForList($conn, $excludeDeletedPlanners = true)
    {
        require_once __DIR__ . '/consulta_session_leads_helper.php';

        $records = [];
        $seenCfIds = [];
        $fechaAtencionByCfId = [];

        if (function_exists('dashComercialBuildAtendidosCohort') && function_exists('dashComercialResolveDisplayFechaAtencionRaw')) {
            $atendidosCohort = dashComercialBuildAtendidosCohort($conn, '', '');
            $cohortCfIds = [];
            foreach ($atendidosCohort as $cfId => $item) {
                $cf = is_array($item['cf'] ?? null) ? $item['cf'] : [];
                $resolvedCfId = (int) ($cf['id'] ?? $cfId);
                if ($resolvedCfId > 0) {
                    $cohortCfIds[] = $resolvedCfId;
                }
            }
            $histFechaAtencionByCfId = function_exists('dashComercialBatchLoadFechaAtencionByContactFormIds')
                ? dashComercialBatchLoadFechaAtencionByContactFormIds($conn, $cohortCfIds)
                : [];
            foreach ($atendidosCohort as $cfId => $item) {
                $cf = is_array($item['cf'] ?? null) ? $item['cf'] : [];
                $resolvedCfId = (int) ($cf['id'] ?? $cfId);
                if ($resolvedCfId <= 0) {
                    continue;
                }
                $fechaAtencion = trim((string) dashComercialResolveDisplayFechaAtencionRaw($conn, $item, $histFechaAtencionByCfId));
                if ($fechaAtencion !== '' && strpos($fechaAtencion, '0000-00-00') !== 0) {
                    $fechaAtencionByCfId[$resolvedCfId] = $fechaAtencion;
                }
            }
        }

        foreach (plannerEventFetchAllForList($conn, false, $excludeDeletedPlanners) as $eventItem) {
            $cfId = (int) ($eventItem['contact_form_id'] ?? 0);
            if ($cfId > 0) {
                $seenCfIds[$cfId] = true;
            }
            $records[] = provisionalNormalizeEventoWpRow($eventItem, $fechaAtencionByCfId);
        }

        $ctx = consultaSessionLeadsBuildAllLeads($conn, false);
        $clienteFinalCfIds = [];
        $clienteFinalLeads = [];

        foreach ($ctx['allLeads'] as $lead) {
            $cfId = (int) ($lead['id'] ?? 0);
            if ($cfId <= 0 || isset($seenCfIds[$cfId])) {
                continue;
            }
            if (wpEventosCfIsContactForm($lead)) {
                continue;
            }

            $tablaOrigen = strtolower(trim((string) ($lead['tabla_origen'] ?? '')));
            if (in_array($tablaOrigen, ['wedding_planners', 'wedding_planner'], true)) {
                continue;
            }

            if (plannerProfileResolveTipoClienteLabel($lead) !== 'Cliente Final') {
                continue;
            }

            $clienteFinalCfIds[] = $cfId;
            $clienteFinalLeads[] = $lead;
        }

        $historialFechaAtencionByCfId = plannerProfileBatchLoadFechaAtencionByContactFormIds($conn, $clienteFinalCfIds);
        $fechaAtencionClienteFinalByCfId = $historialFechaAtencionByCfId + $fechaAtencionByCfId;
        foreach ($clienteFinalLeads as $lead) {
            $cfId = (int) ($lead['id'] ?? 0);
            $appt = postLeadResolveAppointmentForLead(
                $lead,
                $ctx['appointmentsByClient'],
                $ctx['appointmentsByEventId']
            );
            $records[] = provisionalNormalizeClienteFinalRow($conn, $lead, is_array($appt) ? $appt : null, $fechaAtencionClienteFinalByCfId);
            $seenCfIds[$cfId] = true;
        }

        usort($records, function ($a, $b) {
            $ta = strtotime((string) ($a['sort_date'] ?? '')) ?: 0;
            $tb = strtotime((string) ($b['sort_date'] ?? '')) ?: 0;
            if ($ta === $tb) {
                return strcmp((string) ($b['row_key'] ?? ''), (string) ($a['row_key'] ?? ''));
            }

            return $tb <=> $ta;
        });

        return $records;
    }
}

if (!function_exists('plannerEventFetchAllForList')) {
  /**
   * @return array<int, array<string, mixed>>
   */
    function plannerEventFetchAllForList($conn, $onlyPublished = false, $excludeDeletedPlanners = false)
    {
        $eventCitySelect = "NULL AS ciudad_novios";
        $eventCityColumnResult = $conn->query("SHOW COLUMNS FROM eventos_wp LIKE 'ciudad_novios'");
        if ($eventCityColumnResult && $eventCityColumnResult->num_rows > 0) {
            $eventCitySelect = "e.ciudad_novios AS ciudad_novios";
        }

        $contactFormClienteSelect = wpPlannerSqlContactFormClienteSelect('e') . ' AS contact_form_cliente';

        $whereParts = [];
        if ($onlyPublished) {
            $whereParts[] = wpPlannerSqlPublishedEventCondition('e');
        }
        if ($excludeDeletedPlanners) {
            $wpEliminadoColumnResult = $conn->query("SHOW COLUMNS FROM wedding_planners LIKE 'eliminado'");
            if ($wpEliminadoColumnResult && $wpEliminadoColumnResult->num_rows > 0) {
                $whereParts[] = '(wp.eliminado = 0 OR wp.eliminado IS NULL)';
            }
        }
        $whereSql = empty($whereParts) ? '' : ('WHERE ' . implode(' AND ', $whereParts));

        $sql = "SELECT
            e.id,
            e.wedding_planner_id,
            e.id_asesor,
            {$eventCitySelect},
            e.fecha_registro AS evento_fecha_registro,
            e.fecha AS evento_fecha,
            e.created_time AS evento_created_time,
            COALESCE(NULLIF(TRIM(wp.empresa_wp), ''), NULLIF(TRIM(wp.full_name), ''), NULLIF(TRIM(wp.campaign_name), ''), CONCAT('WP #', e.wedding_planner_id)) AS wedding_planner,
            e.novios,
            e.estatus,
            e.fecha_agendado,
            e.fecha_cotizado,
            e.fecha_atendido,
            e.fecha_cliente,
            e.fecha_muerto,
            e.fecha_actualizacion_estatus,
            COALESCE(
                NULLIF(TRIM(CONCAT(u.nombre, ' ', u.apepat)), ''),
                NULLIF(TRIM(CONCAT(u_wp.nombre, ' ', u_wp.apepat)), ''),
                'Sin asignar'
            ) AS asesor_nombre,
            {$contactFormClienteSelect},
            cf.id AS contact_form_id,
            cf.tipo_cliente AS contact_form_tipo_cliente,
            cf.created_time AS contact_form_created_time,
            cf.submission_date AS contact_form_submission_date,
            cf.fecha_cambio_cliente AS contact_form_fecha_cambio_cliente
          FROM eventos_wp e
          LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id
          LEFT JOIN usuarios u ON u.id = e.id_asesor
          LEFT JOIN usuarios u_wp ON u_wp.id = wp.id_vendedor_asignado
          LEFT JOIN (
            SELECT original_lead_id, MAX(id) AS latest_id
            FROM contact_form
            WHERE LOWER(TRIM(COALESCE(tabla_origen, ''))) = 'eventos_wp'
            GROUP BY original_lead_id
          ) cf_latest ON cf_latest.original_lead_id = e.id
          LEFT JOIN contact_form cf ON cf.id = cf_latest.latest_id
          {$whereSql}
          ORDER BY COALESCE(e.created_time, e.fecha_registro, e.fecha) DESC, e.id DESC";

        $events = [];
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
        }

        return $events;
    }
}
