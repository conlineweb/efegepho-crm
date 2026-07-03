<?php

require_once __DIR__ . '/wp_eventos_contact_form_helper.php';

if (!function_exists('isPostLeadEventosWpContactForm')) {
    function isPostLeadEventosWpContactForm(array $cf)
    {
        return wpEventosCfIsContactForm($cf);
    }
}

if (!function_exists('postLeadResolveAppointmentForLead')) {
    function postLeadResolveAppointmentForLead(array $cf, array $appointmentsByClient, array $appointmentsByEventId)
    {
        return wpEventosCfResolveAppointment($cf, $appointmentsByClient, $appointmentsByEventId);
    }
}

if (!function_exists('postLeadMapCalendarioEstatus')) {
    function postLeadMapCalendarioEstatus($rawStatus)
    {
        return wpEventosCfMapCalendarioEstatus($rawStatus);
    }
}

if (!function_exists('postLeadResolveEstatusForLead')) {
    function postLeadResolveEstatusForLead(array $cf, ?array $appt = null)
    {
        global $conn;

        if (isPostLeadEventosWpContactForm($cf)) {
            if (isset($conn)) {
                $cf = wpEventosCfHydratePlannerContactFormFields($conn, $cf);
            }

            $plannerEstatus = wpEventosCfResolvePlannerTipoClienteEstatus($cf);
            if ($plannerEstatus !== null) {
                return $plannerEstatus;
            }

            if (is_array($appt) && isset($appt['estatus'])) {
                $mapped = postLeadMapCalendarioEstatus($appt['estatus']);
                if ($mapped === 'agendado') {
                    return 'atendido';
                }
                if ($mapped !== '') {
                    return $mapped;
                }
            }

            return 'atendido';
        }

        if ((int) ($cf['cliente'] ?? 0) === 1) {
            return 'cliente';
        }
        if (is_array($appt) && isset($appt['estatus'])) {
            return postLeadMapCalendarioEstatus($appt['estatus']);
        }

        return '';
    }
}

if (!function_exists('consultaSessionLeadsResolveEstatus')) {
    function consultaSessionLeadsResolveEstatus(array $cf, ?array $appt, bool $postLeadsMode)
    {
        if ($postLeadsMode) {
            return postLeadResolveEstatusForLead($cf, $appt);
        }

        return wpEventosCfResolveEstatusForAgendados($cf, $appt);
    }
}

if (!function_exists('postLeadResolveDateFieldForLead')) {
    /**
     * Fecha de cohorte genérica (llegada / created_time).
     * Para consulta_agendados_leads usar postLeadResolveAgendaCohortDateForLead().
     */
    function postLeadResolveDateFieldForLead(array $lead)
    {
        return wpEventosCfResolveDateField($lead);
    }
}

if (!function_exists('postLeadResolveAgendaCohortDateForLead')) {
    /**
     * Fecha en que el lead agendó (consulta_agendados_leads).
     */
    function postLeadResolveAgendaCohortDateForLead(array $lead, array $appointmentsByClient, array $appointmentsByEventId)
    {
        $appt = postLeadResolveAppointmentForLead($lead, $appointmentsByClient, $appointmentsByEventId);

        return plannerProfileResolveAgendaCohortDate($lead, $appt);
    }
}

if (!function_exists('postLeadAppointmentDateIsValid')) {
    function postLeadAppointmentDateIsValid(?array $appt)
    {
        return wpEventosCfAppointmentDateIsValid($appt);
    }
}

if (!function_exists('postLeadResolveEngagementValue')) {
    function postLeadResolveEngagementValue(array $lead, array $appointmentsByClient, array $wpEngagementByEventId)
    {
        $tablaOrigen = strtolower(trim((string) ($lead['tabla_origen'] ?? '')));
        $origId = (int) ($lead['original_lead_id'] ?? 0);
        $cfId = (int) ($lead['id'] ?? 0);

        if ($tablaOrigen === 'eventos_wp' && $origId > 0 && isset($wpEngagementByEventId[$origId])) {
            return (int) $wpEngagementByEventId[$origId];
        }

        $current = (int) ($lead['engagement'] ?? 0);
        if ($current >= 1 && $current <= 3) {
            return $current;
        }

        if ($cfId > 0 && isset($appointmentsByClient[$cfId])) {
            $fromCalendar = (int) ($appointmentsByClient[$cfId]['cliente_engagement'] ?? 0);
            if ($fromCalendar >= 1 && $fromCalendar <= 3) {
                return $fromCalendar;
            }
        }

        return $current;
    }
}

if (!function_exists('consultaSessionLeadsBuildAllLeads')) {
    /**
     * Carga contact_form + eventos_wp (misma consulta que consulta_post_leads.php).
     *
     * @param bool $postLeadsMode true = post-leads (excluye agendado en bucle principal)
     * @return array{
     *   allLeads: array,
     *   appointmentsByClient: array,
     *   appointmentsByEventId: array,
     *   wpEngagementByEventId: array,
     *   appointmentIds: array
     * }
     */
    function consultaSessionLeadsBuildAllLeads($conn, bool $postLeadsMode = true)
    {
        $appointmentIds = [];
        $appointmentQuery = $conn->query('SELECT DISTINCT idclie FROM calendario');
        if ($appointmentQuery && $appointmentQuery->num_rows > 0) {
            while ($row = $appointmentQuery->fetch_assoc()) {
                $appointmentIds[] = (int) $row['idclie'];
            }
        }

        $appointmentsByClient = [];
        if (!empty($appointmentIds)) {
            $idsList = implode(',', array_map('intval', $appointmentIds));
            $apptRes = $conn->query("SELECT * FROM calendario WHERE idclie IN ($idsList)");
            if ($apptRes && $apptRes->num_rows > 0) {
                while ($ar = $apptRes->fetch_assoc()) {
                    $idclie = (int) ($ar['idclie'] ?? 0);
                    if ($idclie <= 0) {
                        continue;
                    }

                    if (!isset($appointmentsByClient[$idclie])) {
                        $appointmentsByClient[$idclie] = $ar;
                        continue;
                    }

                    $prev = $appointmentsByClient[$idclie];
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
                        $appointmentsByClient[$idclie] = $ar;
                    }
                }
            }
        }

        $wpEngagementByEventId = [];
        $wpEngagementRes = $conn->query(
            "SELECT idclie, cliente_engagement FROM calendario
             WHERE tipo = 1 AND eliminado = 0 AND cliente_engagement IS NOT NULL AND cliente_engagement > 0
             ORDER BY id DESC"
        );
        if ($wpEngagementRes && $wpEngagementRes->num_rows > 0) {
            while ($wpEngRow = $wpEngagementRes->fetch_assoc()) {
                $eventId = (int) ($wpEngRow['idclie'] ?? 0);
                if ($eventId > 0 && !isset($wpEngagementByEventId[$eventId])) {
                    $wpEngagementByEventId[$eventId] = (int) $wpEngRow['cliente_engagement'];
                }
            }
        }

        $appointmentsByEventId = wpEventosCfGetAppointmentsByEventId($conn);
        $allLeads = [];

        if (!empty($appointmentIds)) {
            $idsList = implode(',', array_map('intval', $appointmentIds));
            $result = $conn->query("SELECT * FROM contact_form WHERE id IN ($idsList) ORDER BY submission_date DESC");
            if ($result && $result->num_rows > 0) {
                while ($cf = $result->fetch_assoc()) {
                    $tablaOrigenRaw = strtolower(trim($cf['tabla_origen'] ?? ''));
                    if ($tablaOrigenRaw === 'wedding_planners' || $tablaOrigenRaw === 'wedding_planner') {
                        continue;
                    }

                    $merged = $cf;
                    $merged['engagement'] = postLeadResolveEngagementValue($merged, $appointmentsByClient, $wpEngagementByEventId);
                    $merged['tabla_origen'] = $cf['tabla_origen'] ?? '';
                    $merged['original_lead_id'] = $cf['original_lead_id'] ?? null;
                    $merged['full_name'] = $cf['names'] ?? 'N/A';
                    $merged['submission_date'] = $cf['submission_date'] ?? '';
                    $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';
                    $merged['wedding_location'] = (isset($cf['wedding_location']) && trim($cf['wedding_location']) !== '')
                        ? $cf['wedding_location']
                        : 'N/A';
                    $merged['has_appointment'] = in_array((int) $cf['id'], $appointmentIds, true) ? 1 : 0;

                    $formName = $cf['tabla_origen'] ?? '';
                    $origId = (int) ($cf['original_lead_id'] ?? 0);
                    $leadRow = null;
                    if ($formName !== '' && $origId > 0) {
                        $escapedForm = $conn->real_escape_string($formName);
                        $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
                        if ($checkTable && $checkTable->num_rows > 0) {
                            $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
                            if ($leadRes && $leadRes->num_rows > 0) {
                                $leadRow = $leadRes->fetch_assoc();
                                if (isPostLeadEventosWpContactForm($cf)) {
                                    $merged = wpEventosCfEnrichMergedLead($conn, $merged, $cf);
                                } elseif (!empty($leadRow['full_name'])) {
                                    $merged['full_name'] = $leadRow['full_name'];
                                } elseif (!empty($leadRow['names'])) {
                                    $merged['full_name'] = $leadRow['names'];
                                } elseif (!empty($leadRow['name'])) {
                                    $merged['full_name'] = $leadRow['name'];
                                }

                                if (!isPostLeadEventosWpContactForm($cf)) {
                                    if (!empty($leadRow['created_time'])) {
                                        $merged['created_time'] = $leadRow['created_time'];
                                    } elseif (!empty($leadRow['created_at'])) {
                                        $merged['created_time'] = $leadRow['created_at'];
                                    }
                                }

                                foreach ($leadRow as $k => $v) {
                                    if (is_string($v)) {
                                        $v = trim($v);
                                    }
                                    if (!isset($merged[$k]) || $merged[$k] === '' || $merged[$k] === null) {
                                        $merged[$k] = $v;
                                    }
                                }
                            }
                        }
                    }

                    $cfCampaign = trim((string) ($cf['campaign_name'] ?? ''));
                    if ($cfCampaign === '') {
                        $cfCampaign = trim((string) ($merged['campaign_name'] ?? ''));
                    }
                    $merged['campaign_name'] = $cfCampaign;

                    if (function_exists('resolveFirstContactChannelForLead')) {
                        $merged['first_contact_channel'] = resolveFirstContactChannelForLead($merged, $leadRow);
                    }

                    $appt = postLeadResolveAppointmentForLead($cf, $appointmentsByClient, $appointmentsByEventId);
                    if ($appt !== null && !postLeadAppointmentDateIsValid($appt)) {
                        continue;
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

                    if (isPostLeadEventosWpContactForm($cf)) {
                        $cf = wpEventosCfHydratePlannerContactFormFields($conn, $cf);
                        foreach (['tipo_cliente', 'cliente', 'fecha_cambio_cliente'] as $plannerField) {
                            if (isset($cf[$plannerField])) {
                                $merged[$plannerField] = $cf[$plannerField];
                            }
                        }
                    }

                    $merged['estatus'] = consultaSessionLeadsResolveEstatus($cf, $appt, $postLeadsMode);

                    if ($postLeadsMode && $merged['estatus'] === 'agendado') {
                        continue;
                    }

                    $howDidYouMeetRaw = trim((string) ($merged['how_did_you_meet'] ?? ''));
                    $merged['how_did_you_meet_raw'] = $howDidYouMeetRaw;
                    if (!isPostLeadEventosWpContactForm($cf) && $howDidYouMeetRaw === '1' && $merged['estatus'] === 'cliente') {
                        continue;
                    }

                    if (function_exists('applyResolvedHowDidYouMeetToLead')) {
                        applyResolvedHowDidYouMeetToLead($merged);
                    }

                    $allLeads[] = $merged;
                }
            }
        }

        $loadedCfIds = [];
        foreach ($allLeads as $loadedLead) {
            $loadedId = (int) ($loadedLead['id'] ?? 0);
            if ($loadedId > 0) {
                $loadedCfIds[$loadedId] = true;
            }
        }

        wpEventosCfAppendToAllLeads(
            $conn,
            $allLeads,
            $appointmentsByClient,
            $appointmentsByEventId,
            $wpEngagementByEventId,
            $loadedCfIds,
            'postLeadResolveEngagementValue',
            [
                'skip_agendado' => $postLeadsMode,
                'estatus_resolver' => 'postLeadResolveEstatusForLead',
            ]
        );

        return [
            'allLeads' => $allLeads,
            'appointmentsByClient' => $appointmentsByClient,
            'appointmentsByEventId' => $appointmentsByEventId,
            'wpEngagementByEventId' => $wpEngagementByEventId,
            'appointmentIds' => $appointmentIds,
        ];
    }
}
