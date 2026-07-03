<?php

if (!function_exists('wpEventosCfResolvePlannerDisplayName')) {
    function wpEventosCfResolvePlannerDisplayName(array $row)
    {
        foreach (['wedding_planner_name', 'wp_empresa', 'wp_planner_full_name', 'empresa_wp', 'planner_contact_name'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $names = trim((string) ($row['names'] ?? ''));
        if ($names !== '' && strcasecmp((string) ($row['tabla_origen'] ?? ''), 'eventos_wp') === 0) {
            $novios = trim((string) ($row['novios'] ?? ''));
            if ($novios === '' || strcasecmp($names, $novios) !== 0) {
                return $names;
            }
        }

        return '';
    }
}

if (!function_exists('wpEventosCfResolveLeadNameDisplay')) {
    /**
     * Nombre principal + subtítulo para tablas: WP arriba, novios abajo.
     *
     * @return array{primary: string, secondary: string|null}
     */
    function wpEventosCfResolveLeadNameDisplay(array $lead)
    {
        $tabla = strtolower(trim((string) ($lead['tabla_origen'] ?? '')));
        $isWpEvent = wpEventosCfIsContactForm($lead) || $tabla === 'eventos_wp';

        if (!$isWpEvent) {
            $primary = trim((string) ($lead['full_name'] ?? $lead['names'] ?? ''));

            return [
                'primary' => $primary !== '' ? $primary : 'N/A',
                'secondary' => null,
            ];
        }

        $plannerName = wpEventosCfResolvePlannerDisplayName($lead);
        $novios = trim((string) ($lead['novios'] ?? ''));
        if ($novios === '') {
            $fullName = trim((string) ($lead['full_name'] ?? ''));
            if ($fullName !== '' && ($plannerName === '' || strcasecmp($fullName, $plannerName) !== 0)) {
                $novios = $fullName;
            }
        }

        $primary = $plannerName !== ''
            ? $plannerName
            : trim((string) ($lead['full_name'] ?? $lead['names'] ?? 'N/A'));

        $secondary = null;
        if ($novios !== '' && strcasecmp($novios, $primary) !== 0) {
            $secondary = $novios;
        }

        return [
            'primary' => $primary !== '' ? $primary : 'N/A',
            'secondary' => $secondary,
        ];
    }
}

if (!function_exists('wpEventosCfBuildSearchHaystack')) {
    function wpEventosCfBuildSearchHaystack(array $lead, $excludeClientName = false)
    {
        $parts = [];
        if (!$excludeClientName) {
            $parts[] = $lead['full_name'] ?? '';
            $parts[] = $lead['names'] ?? '';
            $parts[] = $lead['novios'] ?? '';
        }
        $parts = array_merge($parts, [
            $lead['wedding_planner_name'] ?? '',
            wpEventosCfResolvePlannerDisplayName($lead),
            $lead['email'] ?? '',
            $lead['email_address'] ?? '',
            $lead['phone'] ?? '',
            $lead['telephone'] ?? '',
            $lead['campaign_name'] ?? '',
            $lead['form_name'] ?? '',
            $lead['platform'] ?? '',
            $lead['tabla_origen'] ?? '',
            $lead['city'] ?? '',
            $lead['wedding_location'] ?? '',
        ]);

        return mb_strtolower(trim(implode(' ', array_filter($parts, static function ($part) {
            return trim((string) $part) !== '';
        }))), 'UTF-8');
    }
}

if (!function_exists('wpEventosCfLeadMatchesSearch')) {
    function wpEventosCfLeadMatchesSearch(array $lead, $searchQuery, $excludeClientName = false)
    {
        $searchQuery = trim((string) $searchQuery);
        if ($searchQuery === '') {
            return true;
        }

        return mb_stripos(
            wpEventosCfBuildSearchHaystack($lead, $excludeClientName),
            mb_strtolower($searchQuery, 'UTF-8'),
            0,
            'UTF-8'
        ) !== false;
    }
}

if (!function_exists('wpEventosCfIsWpLeadTable')) {
    function wpEventosCfIsWpLeadTable($tablaOrigen)
    {
        return in_array(strtolower(trim((string) $tablaOrigen)), [
            'eventos_wp',
            'wp_citas_leads',
            'wp_eventos_afianzados',
            'wedding_planners',
            'wedding_planner',
        ], true);
    }
}

if (!function_exists('wpEventosCfResolveEstatusForAgendados')) {
    /**
     * Estatus en consulta_agendados_leads: respeta calendario si hay cita; sin cita = atendido.
     */
    function wpEventosCfResolveEstatusForAgendados(array $cf, ?array $appt)
    {
        $plannerEstatus = wpEventosCfResolvePlannerTipoClienteEstatus($cf);
        if ($plannerEstatus !== null) {
            return $plannerEstatus;
        }

        if (!wpEventosCfIsContactForm($cf)) {
            if (is_array($appt) && isset($appt['estatus'])) {
                return wpEventosCfMapCalendarioEstatus($appt['estatus']);
            }

            return '';
        }

        if ($appt === null) {
            return 'atendido';
        }

        if (isset($appt['estatus'])) {
            $mapped = wpEventosCfMapCalendarioEstatus($appt['estatus']);

            return $mapped !== '' ? $mapped : 'agendado';
        }

        return 'atendido';
    }
}

if (!function_exists('wpEventosCfLeadPassesPlatformFilter')) {
    function wpEventosCfLeadPassesPlatformFilter(array $lead, $filterPlataforma, array $tablaTipoMap)
    {
        if ($filterPlataforma === '') {
            return true;
        }

        $tablaOrigen = strtolower(trim((string) ($lead['tabla_origen'] ?? '')));
        if (wpEventosCfIsContactForm($lead) || wpEventosCfIsWpLeadTable($tablaOrigen)) {
            return true;
        }

        $tipoTabla = isset($tablaTipoMap[$lead['tabla_origen'] ?? ''])
            ? (int) $tablaTipoMap[$lead['tabla_origen'] ?? '']
            : -1;
        $campaignNameLower = strtolower(trim((string) ($lead['campaign_name'] ?? '')));
        $isB1B2 = function_exists('isB1B2CampaignName') && isB1B2CampaignName($campaignNameLower);

        if ($filterPlataforma === 'organico') {
            if ($isB1B2) {
                return false;
            }

            return $tipoTabla === 0
                || $campaignNameLower === 'website'
                || $campaignNameLower === 'reg manual';
        }

        if ($filterPlataforma === 'campania') {
            return $tipoTabla === 1 || $isB1B2;
        }

        return true;
    }
}

if (!function_exists('wpEventosCfIsContactForm')) {
    function wpEventosCfIsContactForm(array $cf)
    {
        $form = strtolower(trim((string) ($cf['form_name'] ?? '')));
        $tabla = strtolower(trim((string) ($cf['tabla_origen'] ?? '')));

        return $form === 'eventos_wp' || $tabla === 'eventos_wp';
    }
}

if (!function_exists('wpEventosCfIsWpEventLead')) {
    function wpEventosCfIsWpEventLead(array $lead)
    {
        return wpEventosCfIsContactForm($lead)
            || strcasecmp((string) ($lead['tabla_origen'] ?? ''), 'eventos_wp') === 0;
    }
}

if (!function_exists('wpEventosCfResolveEventIdFromLead')) {
    function wpEventosCfResolveEventIdFromLead(array $lead)
    {
        $eventId = (int) ($lead['original_lead_id'] ?? $lead['evento_wp_id'] ?? 0);
        if ($eventId <= 0 && strcasecmp((string) ($lead['tabla_origen'] ?? ''), 'eventos_wp') === 0) {
            $eventId = (int) ($lead['id'] ?? 0);
        }

        return $eventId;
    }
}

if (!function_exists('wpEventosCfResolvePlannerVendorId')) {
    function wpEventosCfResolvePlannerVendorId($conn, array $lead)
    {
        foreach (['wp_id_vendedor_asignado', 'planner_id_vendedor_asignado'] as $field) {
            $id = (int) ($lead[$field] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        $wpId = (int) ($lead['wp_id'] ?? $lead['id_wedding_planner'] ?? $lead['wedding_planner_id'] ?? 0);
        if ($wpId <= 0 || !$conn) {
            return 0;
        }

        static $cache = [];
        if (!array_key_exists($wpId, $cache)) {
            try {
                $stmt = $conn->prepare('SELECT id_vendedor_asignado FROM wedding_planners WHERE id = ? LIMIT 1');
                if (!$stmt) {
                    $cache[$wpId] = 0;
                } else {
                    $stmt->bind_param('i', $wpId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $stmt->close();
                    $cache[$wpId] = (int) ($row['id_vendedor_asignado'] ?? 0);
                }
            } catch (\Throwable $e) {
                error_log('wpEventosCfResolvePlannerVendedorId error for wp #' . $wpId . ': ' . $e->getMessage());
                $cache[$wpId] = 0;
            }
        }

        return (int) $cache[$wpId];
    }
}

if (!function_exists('wpEventosCfResolveEventVendedorId')) {
    /**
     * Vendedora del evento WP: cita → evento/contact_form → vendedora general del planner.
     */
    function wpEventosCfResolveEventVendedorId($conn, array $lead, ?array $appt = null, ?array $appointmentsByEventId = null)
    {
        if (!wpEventosCfIsWpEventLead($lead)) {
            return 0;
        }

        if (is_array($appt)) {
            $fromAppt = (int) ($appt['idusu'] ?? $appt['id_vendedor_asignado'] ?? 0);
            if ($fromAppt > 0) {
                return $fromAppt;
            }
        }

        $eventId = wpEventosCfResolveEventIdFromLead($lead);
        if ($eventId > 0 && is_array($appointmentsByEventId) && isset($appointmentsByEventId[$eventId])) {
            $mappedAppt = $appointmentsByEventId[$eventId];
            $fromMapped = (int) ($mappedAppt['idusu'] ?? $mappedAppt['id_vendedor_asignado'] ?? 0);
            if ($fromMapped > 0) {
                return $fromMapped;
            }
        }

        foreach (['id_vendedor_asignado', 'usuario_asignado', 'id_asesor'] as $field) {
            $id = (int) ($lead[$field] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return wpEventosCfResolvePlannerVendorId($conn, $lead);
    }
}

if (!function_exists('wpEventosCfResolveAsesorDisplayName')) {
    /**
     * Nombre visible del asesor/vendedora (incluye Líder de Planners, tipoUsu = 5).
     */
    function wpEventosCfResolveAsesorDisplayName($conn, int $asesorId, ?array $nameById = null)
    {
        if ($asesorId <= 0) {
            return '';
        }

        if (is_array($nameById)) {
            if (!empty($nameById[$asesorId]) && is_string($nameById[$asesorId])) {
                return trim($nameById[$asesorId]);
            }
            if (isset($nameById[$asesorId]) && is_array($nameById[$asesorId])) {
                $full = trim((string) (($nameById[$asesorId]['nombre'] ?? '') . ' ' . ($nameById[$asesorId]['apepat'] ?? '')));
                if ($full !== '') {
                    return $full;
                }
            }
        }

        static $userNameCache = [];
        if (isset($userNameCache[$asesorId])) {
            return $userNameCache[$asesorId];
        }

        $label = '';
        if ($conn) {
            try {
                $stmt = $conn->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $asesorId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $stmt->close();
                    $label = trim((string) (
                        ($row['nombre'] ?? '') . ' ' .
                        ($row['apepat'] ?? $row['apePat'] ?? '') . ' ' .
                        ($row['apemat'] ?? $row['apeMat'] ?? '')
                    ));
                    if ($label === '' && !empty($row['usuario'])) {
                        $label = trim((string) $row['usuario']);
                    }
                }
            } catch (\Throwable $e) {
                error_log('wpEventosCfResolveAsesorDisplayName error for asesor #' . $asesorId . ': ' . $e->getMessage());
            }
        }

        $userNameCache[$asesorId] = $label;

        return $label;
    }
}

if (!function_exists('wpEventosCfGetAppointmentsByEventId')) {
    function wpEventosCfGetAppointmentsByEventId($conn)
    {
        $map = [];
        $res = $conn->query('SELECT * FROM calendario WHERE tipo = 1 AND eliminado = 0');
        if (!$res) {
            return $map;
        }

        while ($ar = $res->fetch_assoc()) {
            $eventId = isset($ar['idclie']) ? (int) $ar['idclie'] : 0;
            if ($eventId <= 0) {
                continue;
            }

            if (!isset($map[$eventId])) {
                $map[$eventId] = $ar;
                continue;
            }

            $prev = $map[$eventId];
            $replace = false;
            if (!empty($ar['fecha']) && !empty($prev['fecha'])) {
                $t1 = strtotime($ar['fecha'] . ' ' . ($ar['hora'] ?? '')) ?: 0;
                $t2 = strtotime($prev['fecha'] . ' ' . ($prev['hora'] ?? '')) ?: 0;
                if ($t1 > $t2) {
                    $replace = true;
                }
            } elseif (!empty($ar['id']) && !empty($prev['id'])) {
                if ((int) $ar['id'] > (int) $prev['id']) {
                    $replace = true;
                }
            }

            if ($replace) {
                $map[$eventId] = $ar;
            }
        }

        return $map;
    }
}

if (!function_exists('wpEventosCfResolveAppointment')) {
    function wpEventosCfResolveAppointment(array $cf, array $appointmentsByClient, array $appointmentsByEventId)
    {
        $cid = (int) ($cf['id'] ?? 0);
        if ($cid > 0 && isset($appointmentsByClient[$cid])) {
            return $appointmentsByClient[$cid];
        }

        if (!wpEventosCfIsContactForm($cf)) {
            return null;
        }

        $eventId = (int) ($cf['original_lead_id'] ?? 0);
        if ($eventId > 0 && isset($appointmentsByEventId[$eventId])) {
            return $appointmentsByEventId[$eventId];
        }

        return null;
    }
}

if (!function_exists('wpEventosCfAppointmentDateIsValid')) {
    function wpEventosCfAppointmentDateIsValid(?array $appt)
    {
        if (!is_array($appt)) {
            return true;
        }

        $apptFechaRaw = trim($appt['fecha'] ?? '');
        $apptHoraRaw = trim($appt['hora'] ?? '');
        $apptTs = ($apptFechaRaw !== '') ? strtotime($apptFechaRaw . ' ' . $apptHoraRaw) : false;

        return !($apptFechaRaw === '' || $apptFechaRaw === '0000-00-00' || $apptTs === false || $apptTs <= 0);
    }
}

if (!function_exists('wpEventosCfMapCalendarioEstatus')) {
    function wpEventosCfMapCalendarioEstatus($rawStatus)
    {
        $intStatus = is_numeric($rawStatus) ? (int) $rawStatus : null;
        if ($intStatus === 1) {
            return 'atendido';
        }
        if ($intStatus === 3) {
            return 'muerto';
        }
        if ($intStatus === 0) {
            return 'agendado';
        }
        if ($intStatus === 2) {
            return 'fantasma';
        }

        return is_string($rawStatus) ? trim($rawStatus) : '';
    }
}

if (!function_exists('wpEventosCfHydratePlannerContactFormFields')) {
    /**
     * Completa tipo_cliente / cliente desde contact_form o eventos_wp antes de resolver estatus.
     */
    function wpEventosCfHydratePlannerContactFormFields($conn, array $cf)
    {
        if (!wpEventosCfIsContactForm($cf)) {
            return $cf;
        }

        $eventId = (int) ($cf['original_lead_id'] ?? $cf['evento_wp_id'] ?? 0);
        if ($eventId > 0) {
            if (!function_exists('wpPlannerResolveEventStatusKey')) {
                $plannerHelper = __DIR__ . '/evento_wp_post_helper.php';
                if (is_file($plannerHelper)) {
                    require_once $plannerHelper;
                }
            }

            $leadRes = $conn->query(
                "SELECT tipo_cliente, estatus
                 FROM eventos_wp
                 WHERE id = $eventId
                 LIMIT 1"
            );
            if ($leadRes && $leadRes->num_rows > 0) {
                $leadRow = $leadRes->fetch_assoc();
                foreach (['tipo_cliente', 'fecha_cambio_cliente'] as $field) {
                    $current = $cf[$field] ?? null;
                    if (($current === null || $current === '' || ($field !== 'tipo_cliente' && (int) $current === 0))
                        && isset($leadRow[$field]) && $leadRow[$field] !== '' && $leadRow[$field] !== null) {
                        $cf[$field] = $leadRow[$field];
                    }
                }

                $cf['eventos_wp_estatus'] = trim((string) ($leadRow['estatus'] ?? ''));
                $eventStatusKey = function_exists('wpPlannerResolveEventStatusKey')
                    ? wpPlannerResolveEventStatusKey($cf['eventos_wp_estatus'], $cf['cliente'] ?? null)
                    : 'pendiente';
                $clienteByStatusKey = [
                    'cliente' => 1,
                    'cotizado' => 2,
                    'atendido' => 2,
                    'rechazado' => 3,
                    'muerto' => 3,
                    'cliente_inminente' => 4,
                ];
                if (isset($clienteByStatusKey[$eventStatusKey])) {
                    $cf['cliente'] = $clienteByStatusKey[$eventStatusKey];
                }
            }
        }

        if (!isset($cf['tipo_cliente']) || $cf['tipo_cliente'] === '' || $cf['tipo_cliente'] === null) {
            $cf['tipo_cliente'] = 1;
        }

        return $cf;
    }
}

if (!function_exists('wpEventosCfMapPlannerStatusKeyToConsultaEstatus')) {
    /**
     * Mapea estatus interno del flujo planner a etiquetas de consulta_leads / post / agendados.
     */
    function wpEventosCfMapPlannerStatusKeyToConsultaEstatus(string $statusKey): ?string
    {
        switch ($statusKey) {
            case 'cliente':
                return 'cliente';
            case 'cotizado':
            case 'pendiente':
                return 'agendado';
            case 'atendido':
            case 'cliente_inminente':
                return 'atendido';
            case 'rechazado':
            case 'muerto':
                return 'muerto';
            default:
                return null;
        }
    }
}

if (!function_exists('wpEventosCfResolvePlannerTipoClienteEstatus')) {
    /**
     * Estatus en vistas comerciales para eventos_wp (tipo_cliente = 1).
     * Usa eventos_wp.estatus: NULL=Pendiente, 1=Cliente, 2=Cotizado, 3=Muerto, 4=Atendido, 5=Cliente inminente.
     */
    function wpEventosCfResolvePlannerTipoClienteEstatus(array $cf)
    {
        if (!wpEventosCfIsContactForm($cf) || (int) ($cf['tipo_cliente'] ?? 0) !== 1) {
            return null;
        }

        if (!function_exists('wpPlannerResolveEventStatusKey')) {
            $plannerHelper = __DIR__ . '/evento_wp_post_helper.php';
            if (is_file($plannerHelper)) {
                require_once $plannerHelper;
            }
        }

        $statusKey = function_exists('wpPlannerResolveEventStatusKey')
            ? wpPlannerResolveEventStatusKey($cf['eventos_wp_estatus'] ?? '', $cf['cliente'] ?? null)
            : 'pendiente';

        return wpEventosCfMapPlannerStatusKeyToConsultaEstatus($statusKey);
    }
}

if (!function_exists('wpEventosCfResolveEstatusForContactFormRow')) {
    function wpEventosCfResolveEstatusForContactFormRow($conn, array $cf, ?array $appt, callable $resolver = null)
    {
        $cf = wpEventosCfHydratePlannerContactFormFields($conn, $cf);

        if ($resolver !== null && is_callable($resolver)) {
            return call_user_func($resolver, $cf, $appt);
        }

        return wpEventosCfResolveEstatus($cf, $appt);
    }
}

if (!function_exists('wpEventosCfResolveContactFormEstatus')) {
    /**
     * Estatus unificado para contact_form eventos_wp (WP / Contact Form).
     */
    function wpEventosCfResolveContactFormEstatus(array $cf, ?array $appt)
    {
        $plannerEstatus = wpEventosCfResolvePlannerTipoClienteEstatus($cf);
        if ($plannerEstatus !== null) {
            return $plannerEstatus;
        }

        if (is_array($appt) && isset($appt['estatus'])) {
            $mapped = wpEventosCfMapCalendarioEstatus($appt['estatus']);
            if ($mapped === 'agendado') {
                return 'atendido';
            }
            if ($mapped !== '') {
                return $mapped;
            }
        }

        return 'atendido';
    }
}

if (!function_exists('wpEventosCfResolveEstatus')) {
    function wpEventosCfResolveEstatus(array $cf, ?array $appt)
    {
        if (wpEventosCfIsContactForm($cf)) {
            return wpEventosCfResolveContactFormEstatus($cf, $appt);
        }

        if ((int) ($cf['cliente'] ?? 0) === 1) {
            return 'cliente';
        }
        if (is_array($appt) && isset($appt['estatus'])) {
            return wpEventosCfMapCalendarioEstatus($appt['estatus']);
        }

        return '';
    }
}

if (!function_exists('wpEventosCfResolveDateField')) {
    function wpEventosCfResolveDateField(array $lead)
    {
        if (wpEventosCfIsContactForm($lead)) {
            $clienteField = (int) ($lead['cliente'] ?? 0);
            if ($clienteField >= 2) {
                $fechaCambio = trim((string) ($lead['fecha_cambio_cliente'] ?? ''));
                if ($fechaCambio !== '' && strpos($fechaCambio, '0000-00-00') !== 0) {
                    return $fechaCambio;
                }
            }

            if (!empty($lead['created_time'])) {
                return $lead['created_time'];
            }
            return $lead['submission_date'] ?? '';
        }

        if (!empty($lead['created_time'])) {
            return $lead['created_time'];
        }

        return $lead['submission_date'] ?? '';
    }
}

if (!function_exists('wpEventosCfEnrichMergedLead')) {
    function wpEventosCfEnrichMergedLead($conn, array $merged, array $cf)
    {
        $eventId = (int) ($cf['original_lead_id'] ?? 0);
        if ($eventId <= 0) {
            return $merged;
        }

        $leadRes = $conn->query(
            "SELECT e.novios, e.id_asesor, e.modo, e.wedding_planner_id, e.estatus AS eventos_wp_estatus,
                    e.fecha_registro, e.fecha_agendado, e.fecha_cotizado, e.fecha_muerto,
                    e.fecha_atendido, e.created_time, e.ciudad_novios, e.lugar, e.fecha,
                    wp.full_name AS wp_planner_full_name, wp.empresa_wp AS wp_empresa,
                    wp.id_vendedor_asignado AS wp_id_vendedor_asignado
             FROM eventos_wp e
             LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id
             WHERE e.id = $eventId
             LIMIT 1"
        );
        if (!$leadRes || $leadRes->num_rows === 0) {
            return $merged;
        }

        $leadRow = $leadRes->fetch_assoc();
        $plannerName = wpEventosCfResolvePlannerDisplayName(array_merge($merged, $leadRow, [
            'planner_contact_name' => trim((string) ($cf['names'] ?? '')),
        ]));
        if ($plannerName !== '') {
            $merged['wedding_planner_name'] = $plannerName;
        }

        if (!empty($leadRow['novios'])) {
            $merged['novios'] = trim((string) $leadRow['novios']);
            $merged['full_name'] = $merged['novios'];
            $merged['names'] = $merged['novios'];
        }

        foreach ($leadRow as $k => $v) {
            if (is_string($v)) {
                $v = trim($v);
            }
            if (!isset($merged[$k]) || $merged[$k] === '' || $merged[$k] === null) {
                $merged[$k] = $v;
            }
        }

        if (!empty($leadRow['fecha_agendado'])) {
            $merged['fecha_agendado'] = trim((string) $leadRow['fecha_agendado']);
        }
        if (!empty($leadRow['fecha_cotizado'])) {
            $merged['fecha_cotizado'] = trim((string) $leadRow['fecha_cotizado']);
        }
        if (!empty($leadRow['fecha_muerto'])) {
            $merged['fecha_muerto'] = trim((string) $leadRow['fecha_muerto']);
        }
        if (!empty($leadRow['fecha_atendido'])) {
            $merged['fecha_atendido'] = trim((string) $leadRow['fecha_atendido']);
            $merged['evento_fecha_atendido'] = $merged['fecha_atendido'];
        }
        if (!empty($leadRow['created_time'])) {
            $merged['evento_created_time'] = trim((string) $leadRow['created_time']);
        }
        if (!empty($leadRow['fecha_registro'])) {
            $merged['evento_fecha_registro'] = trim((string) $leadRow['fecha_registro']);
        }

        if ((int) ($merged['id_vendedor_asignado'] ?? 0) <= 0 && (int) ($leadRow['id_asesor'] ?? 0) > 0) {
            $merged['id_vendedor_asignado'] = (int) $leadRow['id_asesor'];
        }
        if ((int) ($merged['id_vendedor_asignado'] ?? 0) <= 0) {
            $plannerVendorId = (int) ($leadRow['wp_id_vendedor_asignado'] ?? 0);
            if ($plannerVendorId > 0) {
                $merged['id_vendedor_asignado'] = $plannerVendorId;
                $merged['wp_id_vendedor_asignado'] = $plannerVendorId;
            }
        }

        return $merged;
    }
}

if (!function_exists('wpEventosCfAppendToAllLeads')) {
    /**
     * Añade contact_form eventos_wp que aún no están en $allLeads (eventos sin cita o sin calendario espejo).
     *
     * @param array<int, true> $loadedCfIds
     */
    function wpEventosCfAppendToAllLeads(
        $conn,
        array &$allLeads,
        array $appointmentsByClient,
        array $appointmentsByEventId,
        array $wpEngagementByEventId = [],
        array &$loadedCfIds = [],
        callable $engagementResolver = null,
        array $options = []
    ) {
        $skipAgendado = array_key_exists('skip_agendado', $options) ? (bool) $options['skip_agendado'] : true;
        $estatusResolver = $options['estatus_resolver'] ?? 'wpEventosCfResolveEstatus';
        $wpCfResult = $conn->query(
            "SELECT * FROM contact_form
             WHERE LOWER(TRIM(COALESCE(form_name, ''))) = 'eventos_wp'
                OR LOWER(TRIM(COALESCE(tabla_origen, ''))) = 'eventos_wp'
             ORDER BY submission_date DESC"
        );
        if (!$wpCfResult) {
            return;
        }

        while ($cf = $wpCfResult->fetch_assoc()) {
            $cfId = (int) ($cf['id'] ?? 0);
            if ($cfId <= 0 || isset($loadedCfIds[$cfId])) {
                continue;
            }

            $cf = wpEventosCfHydratePlannerContactFormFields($conn, $cf);
            $appt = wpEventosCfResolveAppointment($cf, $appointmentsByClient, $appointmentsByEventId);
            if ($appt !== null && !wpEventosCfAppointmentDateIsValid($appt)) {
                continue;
            }

            $estatus = wpEventosCfResolveEstatusForContactFormRow(
                $conn,
                $cf,
                $appt,
                is_callable($estatusResolver) ? $estatusResolver : null
            );
            if ($skipAgendado && $estatus === 'agendado') {
                continue;
            }

            $merged = $cf;
            if ($engagementResolver !== null && is_callable($engagementResolver)) {
                $merged['engagement'] = call_user_func($engagementResolver, $merged, $appointmentsByClient, $wpEngagementByEventId);
            }

            $merged['tabla_origen'] = $cf['tabla_origen'] ?? 'eventos_wp';
            $merged['original_lead_id'] = $cf['original_lead_id'] ?? null;
            $merged['full_name'] = $cf['names'] ?? 'N/A';
            $merged['submission_date'] = $cf['submission_date'] ?? '';
            $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';
            $merged['wedding_location'] = (isset($cf['wedding_location']) && trim($cf['wedding_location']) !== '') ? $cf['wedding_location'] : 'N/A';
            $merged['has_appointment'] = $appt !== null ? 1 : 0;
            $merged['estatus'] = $estatus;
            $merged = wpEventosCfEnrichMergedLead($conn, $merged, $cf);

            $cfCampaign = trim((string) ($cf['campaign_name'] ?? ''));
            if ($cfCampaign === '') {
                $cfCampaign = trim((string) ($merged['campaign_name'] ?? ''));
            }
            $merged['campaign_name'] = $cfCampaign;

            $leadRow = null;
            $formName = $merged['tabla_origen'] ?? '';
            $origId = (int) ($cf['original_lead_id'] ?? 0);
            if ($formName !== '' && $origId > 0) {
                $escapedForm = $conn->real_escape_string($formName);
                $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
                    if ($leadRes && $leadRes->num_rows > 0) {
                        $leadRow = $leadRes->fetch_assoc();
                    }
                }
            }
            if (function_exists('resolveFirstContactChannelForLead')) {
                $merged['first_contact_channel'] = resolveFirstContactChannelForLead($merged, $leadRow);
            }

            if (is_array($appt)) {
                if (isset($appt['idusu']) && $appt['idusu'] !== '') {
                    if (empty($merged['id_vendedor_asignado'])) {
                        $merged['id_vendedor_asignado'] = (int) $appt['idusu'];
                    }
                    if (empty($merged['usuario_asignado'])) {
                        $merged['usuario_asignado'] = (int) $appt['idusu'];
                    }
                }
                if (empty($merged['id_vendedor_asignado']) && isset($appt['id_vendedor_asignado']) && $appt['id_vendedor_asignado'] !== '') {
                    $merged['id_vendedor_asignado'] = (int) $appt['id_vendedor_asignado'];
                }
            }

            $howDidYouMeetRaw = trim((string) ($merged['how_did_you_meet'] ?? ''));
            $merged['how_did_you_meet_raw'] = $howDidYouMeetRaw;
            if (function_exists('applyResolvedHowDidYouMeetToLead')) {
                applyResolvedHowDidYouMeetToLead($merged);
            }

            $allLeads[] = $merged;
            $loadedCfIds[$cfId] = true;
        }
    }
}

if (!function_exists('wpEventosCfResolveConsultaLeadsRegistroDate')) {
    /**
     * Fecha de registro para consulta_leads (eventos_wp): eventos_wp.fecha_registro primero.
     */
    function wpEventosCfResolveConsultaLeadsRegistroDate(array $row)
    {
        foreach (['fecha_registro', 'evento_fecha_registro', 'created_time', 'submission_date'] as $field) {
            $raw = trim((string) ($row[$field] ?? ''));
            if ($raw !== '' && strpos($raw, '0000-00-00') !== 0) {
                return $raw;
            }
        }

        return '';
    }
}

if (!function_exists('wpEventosCfBuildLeadsForConsultaLeads')) {
    /**
     * Filas estilo tabla para consulta_leads.php (eventos registrados en contact_form).
     */
    function wpEventosCfBuildLeadsForConsultaLeads($conn)
    {
        $leads = [];
        $existingKeys = [];

        $sql = "SELECT cf.*, e.novios, e.modo, e.id_asesor, e.wedding_planner_id, e.fecha_registro, e.fecha_agendado, e.fecha_muerto,
                       e.ciudad_novios, e.lugar, e.fecha,
                       wp.full_name AS wp_planner_full_name, wp.empresa_wp AS wp_empresa,
                       wp.id_vendedor_asignado AS wp_id_vendedor_asignado
                FROM contact_form cf
                INNER JOIN eventos_wp e ON e.id = cf.original_lead_id
                LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id
                WHERE LOWER(TRIM(COALESCE(cf.tabla_origen, ''))) = 'eventos_wp'
                ORDER BY cf.submission_date DESC";
        $res = $conn->query($sql);
        if (!$res) {
            return $leads;
        }

        while ($row = $res->fetch_assoc()) {
            $eventId = (int) ($row['original_lead_id'] ?? 0);
            if ($eventId <= 0) {
                continue;
            }

            $key = 'eventos_wp|' . $eventId;
            if (isset($existingKeys[$key])) {
                continue;
            }
            $existingKeys[$key] = true;

            $displayName = trim((string) ($row['novios'] ?? ''));
            if ($displayName === '') {
                $displayName = trim((string) ($row['names'] ?? ''));
            }
            $plannerName = wpEventosCfResolvePlannerDisplayName(array_merge($row, [
                'planner_contact_name' => trim((string) ($row['names'] ?? '')),
            ]));
            $registroDate = wpEventosCfResolveConsultaLeadsRegistroDate($row);

            $leads[] = [
                'id' => $eventId,
                'tabla_origen' => 'eventos_wp',
                'full_name' => $displayName !== '' ? $displayName : ($plannerName !== '' ? $plannerName : 'Evento WP'),
                'names' => $displayName !== '' ? $displayName : ($row['names'] ?? ''),
                'novios' => trim((string) ($row['novios'] ?? '')),
                'wedding_planner_name' => $plannerName,
                'planner_contact_name' => trim((string) ($row['names'] ?? '')),
                'created_time' => $registroDate,
                'fecha_registro' => trim((string) ($row['fecha_registro'] ?? '')),
                'submission_date' => $row['submission_date'] ?? '',
                'campaign_name' => $row['campaign_name'] ?? '',
                'platform' => $row['platform'] ?? '',
                'tipo_ig' => $row['tipo_ig'] ?? '',
                'how_did_you_meet' => $row['how_did_you_meet'] ?? '1',
                'tipo_cliente' => $row['tipo_cliente'] ?? 1,
                'first_contact_channel' => $row['first_contact_channel'] ?? '',
                'how_long_known_us' => $row['how_long_known_us'] ?? '',
                'email' => $row['email_address'] ?? '',
                'phone' => $row['telephone'] ?? '',
                'city' => $row['city'] ?? ($row['ciudad_novios'] ?? ''),
                'wedding_location' => $row['wedding_location'] ?? ($row['lugar'] ?? ''),
                'wedding_date' => $row['wedding_date'] ?? ($row['fecha'] ?? ''),
                'id_wedding_planner' => (int) ($row['wedding_planner_id'] ?? 0),
                'wp_id' => (int) ($row['wedding_planner_id'] ?? 0),
                'wp_id_vendedor_asignado' => (int) ($row['wp_id_vendedor_asignado'] ?? 0),
                'id_vendedor_asignado' => (int) (
                    ($row['id_vendedor_asignado'] ?? 0)
                    ?: ($row['id_asesor'] ?? 0)
                    ?: ($row['wp_id_vendedor_asignado'] ?? 0)
                ),
                'modo' => $row['modo'] ?? '',
                'fecha_agendado' => $row['fecha_agendado'] ?? '',
                'fecha_muerto' => $row['fecha_muerto'] ?? '',
                'contact_form_id' => (int) ($row['id'] ?? 0),
            ];
            $lastIdx = count($leads) - 1;
            if ($lastIdx >= 0 && function_exists('resolveFirstContactChannelForLead')) {
                $leads[$lastIdx]['first_contact_channel'] = resolveFirstContactChannelForLead($leads[$lastIdx]);
            }
        }

        return $leads;
    }
}

if (!function_exists('wpEventosCfBuildDashboardCohortItems')) {
    /**
     * Ítems estilo dashboard_comercial_helper (cf + appt + estatus + created_time).
     *
     * @param array<int, true> $excludeCfIds
     * @return array<int, array{cf: array, appt: array, estatus: string, created_time: string}>
     */
    function wpEventosCfBuildDashboardCohortItems(
        $conn,
        array $appointmentsByClient,
        array $appointmentsByEventId,
        array $excludeCfIds = [],
        callable $vendorFilter = null,
        array $options = []
    ) {
        $skipAgendadoStrict = !empty($options['skip_agendado']);
        $items = [];
        $wpCfResult = $conn->query(
            "SELECT * FROM contact_form
             WHERE LOWER(TRIM(COALESCE(form_name, ''))) = 'eventos_wp'
                OR LOWER(TRIM(COALESCE(tabla_origen, ''))) = 'eventos_wp'
             ORDER BY submission_date DESC"
        );
        if (!$wpCfResult) {
            return $items;
        }

        while ($cf = $wpCfResult->fetch_assoc()) {
            $cfId = (int) ($cf['id'] ?? 0);
            if ($cfId <= 0 || isset($excludeCfIds[$cfId])) {
                continue;
            }

            $cf = wpEventosCfHydratePlannerContactFormFields($conn, $cf);
            $appt = wpEventosCfResolveAppointment($cf, $appointmentsByClient, $appointmentsByEventId);
            if ($appt !== null && !wpEventosCfAppointmentDateIsValid($appt)) {
                continue;
            }

            $estatus = wpEventosCfResolveEstatus($cf, $appt);
            if ($estatus === 'agendado') {
                if ($skipAgendadoStrict) {
                    continue;
                }
                $clienteField = (int) ($cf['cliente'] ?? 0);
                if (!($clienteField === 2 && (int) ($cf['tipo_cliente'] ?? 0) === 1)) {
                    continue;
                }
            }

            $merged = wpEventosCfEnrichMergedLead($conn, $cf, $cf);
            $plannerName = wpEventosCfResolvePlannerDisplayName(array_merge($merged, $cf, [
                'planner_contact_name' => trim((string) ($cf['names'] ?? '')),
            ]));

            $cfForItem = array_merge($cf, $merged, [
                'tabla_origen' => $cf['tabla_origen'] ?? 'eventos_wp',
                'form_name' => $cf['form_name'] ?? 'eventos_wp',
                'wedding_planner_name' => $plannerName,
                'planner_contact_name' => trim((string) ($cf['names'] ?? '')),
                'novios' => trim((string) ($merged['novios'] ?? $merged['full_name'] ?? '')),
            ]);
            if (!empty($merged['full_name'])) {
                $cfForItem['names'] = $merged['full_name'];
            }

            if ($vendorFilter !== null && $vendorFilter($cfForItem, $appt) === false) {
                continue;
            }

            $items[$cfId] = [
                'cf'           => $cfForItem,
                'appt'         => is_array($appt) ? $appt : [],
                'estatus'      => $estatus,
                'created_time' => wpEventosCfResolveDateField($cfForItem),
            ];
        }

        return $items;
    }
}
