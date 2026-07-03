<?php
/** Tab embed: atendidos */
if (!defined('MLB_LEADS_SHELL')) { http_response_code(403); exit('Forbidden'); }

// Sesión y $tipoUsuario los provee menu.php en el shell

// embed: menu en shell
if (!isset($conn)) { include 'conn.php'; }
require_once __DIR__ . '/evento_wp_post_helper.php';
wpEventEnsureFechaAtendidoColumn($conn);
require_once __DIR__ . '/campaign_badge_helper.php';
require_once __DIR__ . '/lead_field_badge_helper.php';
require_once __DIR__ . '/lead_origin_helper.php';
require_once __DIR__ . '/calendario_estatus_historial_helper.php';
require_once __DIR__ . '/wp_eventos_contact_form_helper.php';
require_once __DIR__ . '/dashboard_comercial_period_helper.php';
require_once __DIR__ . '/dashboard_comercial_helper.php';
require_once __DIR__ . '/planner_event_display_helper.php';

function postLeadIsClienteStatus(array $lead)
{
    $st = mb_strtolower(trim((string) ($lead['estatus'] ?? '')), 'UTF-8');

    return $st === 'cliente' || (int) ($lead['cliente'] ?? 0) === 1;
}

function postLeadPassesAtendidosInclusion(array $lead, array $atendidoHistIds)
{
    if (isPostLeadEventosWpContactForm($lead)) {
        return true;
    }

    $lid = (int) ($lead['id'] ?? 0);
    if ($lid > 0 && isset($atendidoHistIds[$lid])) {
        return true;
    }

    return postLeadIsClienteStatus($lead);
}

function postLeadIsAtendidoForEngagement(array $lead)
{
    $st = mb_strtolower(trim((string) ($lead['estatus'] ?? '')), 'UTF-8');

    if ($st === 'atendido' || $st === '1' || (is_numeric($st) && (int) $st === 1)) {
        return true;
    }

    return postLeadIsClienteStatus($lead);
}

function isPostLeadEventosWpContactForm(array $cf)
{
    $form = strtolower(trim((string) ($cf['form_name'] ?? '')));
    $tabla = strtolower(trim((string) ($cf['tabla_origen'] ?? '')));

    return $form === 'eventos_wp' || $tabla === 'eventos_wp';
}

function postLeadResolveAppointmentForLead(array $cf, array $appointmentsByClient, array $appointmentsByEventId)
{
    $cid = intval($cf['id'] ?? 0);
    if ($cid > 0 && isset($appointmentsByClient[$cid])) {
        return $appointmentsByClient[$cid];
    }

    if (!isPostLeadEventosWpContactForm($cf)) {
        return null;
    }

    $eventId = intval($cf['original_lead_id'] ?? 0);
    if ($eventId > 0 && isset($appointmentsByEventId[$eventId])) {
        return $appointmentsByEventId[$eventId];
    }

    return null;
}

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

    if (intval($cf['cliente'] ?? 0) === 1) {
        return 'cliente';
    }
    if (is_array($appt) && isset($appt['estatus'])) {
        return postLeadMapCalendarioEstatus($appt['estatus']);
    }

    return '';
}

function postLeadResolveDateFieldForLead(array $lead)
{
    if (isPostLeadEventosWpContactForm($lead)) {
        return wpEventosCfResolveDateField($lead);
    }

    if (postLeadIsClienteStatus($lead)) {
        $fechaCambio = trim((string) ($lead['fecha_cambio_cliente'] ?? ''));
        if ($fechaCambio !== '' && strpos($fechaCambio, '0000-00-00') !== 0) {
            return $fechaCambio;
        }
    }

    return wpEventosCfResolveDateField($lead);
}

function postLeadResolvePostCohortDateForLead(
    array $lead,
    array $fechaAtencionByContactFormId,
    array $appointmentsByClient,
    array $appointmentsByEventId,
    array $fechaMuertoHistorialByCfId = []
) {
    $appt = postLeadResolveAppointmentForLead($lead, $appointmentsByClient, $appointmentsByEventId);
    $estatusKey = strtolower(trim((string) ($lead['estatus'] ?? '')));

    if (in_array($estatusKey, ['muerto', 'fantasma'], true)) {
        $tablaOrigen = strtolower(trim((string) ($lead['tabla_origen'] ?? '')));
        $isWpEventos = (
            $tablaOrigen === 'eventos_wp'
            || (function_exists('wpEventosCfIsContactForm') && wpEventosCfIsContactForm($lead))
        );
        if ($isWpEventos) {
            $fechaMuertoEvento = trim((string) ($lead['fecha_muerto'] ?? ''));
            if ($fechaMuertoEvento !== '' && strpos($fechaMuertoEvento, '0000-00-00') !== 0) {
                return $fechaMuertoEvento;
            }
        }

        $cfId = (int) ($lead['id'] ?? 0);
        if ($cfId > 0 && !empty($fechaMuertoHistorialByCfId[$cfId])) {
            return $fechaMuertoHistorialByCfId[$cfId];
        }
    }

    $dateField = plannerProfileResolvePostCohortDate($lead, $fechaAtencionByContactFormId, $appt);
    if ($dateField === '' && in_array($estatusKey, ['muerto', 'fantasma'], true)) {
        if (function_exists('dashComercialResolveAgendadosCohortDateField')) {
            $dateField = dashComercialResolveAgendadosCohortDateField($lead, $appointmentsByClient, $appointmentsByEventId);
        } elseif (function_exists('plannerProfileResolveAgendaCohortDate')) {
            $dateField = plannerProfileResolveAgendaCohortDate($lead, $appt);
        }
    }
    if ($dateField === '' && in_array($estatusKey, ['muerto', 'fantasma'], true)) {
        foreach (['created_time', 'submission_date'] as $field) {
            $raw = trim((string) ($lead[$field] ?? ''));
            if ($raw !== '' && strpos($raw, '0000-00-00') !== 0) {
                return $raw;
            }
        }
    }

    return $dateField;
}

function postLeadFetchLeadRowForContactForm($conn, array $cf)
{
    $formName = trim((string) ($cf['tabla_origen'] ?? ''));
    $origId = (int) ($cf['original_lead_id'] ?? 0);
    if ($formName === '' || $origId <= 0) {
        return null;
    }

    $escapedForm = $conn->real_escape_string($formName);
    $checkTable = $conn->query("SHOW TABLES LIKE '$escapedForm'");
    if (!$checkTable || $checkTable->num_rows === 0) {
        return null;
    }

    $leadRes = $conn->query("SELECT * FROM `$escapedForm` WHERE id = $origId LIMIT 1");
    if (!$leadRes || $leadRes->num_rows === 0) {
        return null;
    }

    return $leadRes->fetch_assoc();
}

/**
 * La cohorte del dashboard puede traer un cf parcial; recargar contact_form completo por id.
 */
function postLeadEnsureFullContactFormRow($conn, array $cf)
{
    $cfId = (int) ($cf['id'] ?? 0);
    if ($cfId <= 0) {
        return $cf;
    }

    $needsReload = !array_key_exists('original_lead_id', $cf)
        || !array_key_exists('cliente', $cf)
        || !array_key_exists('campaign_name', $cf);

    if (!$needsReload) {
        return $cf;
    }

    $res = $conn->query("SELECT * FROM contact_form WHERE id = $cfId LIMIT 1");
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }

    return $cf;
}

/**
 * Misma fusión contact_form + tabla origen que clientes.php (solo estatus Cliente).
 */
function postLeadBuildMergedLeadLikeClientes($conn, array $cf, array $appointmentsByClient)
{
    $cf = postLeadEnsureFullContactFormRow($conn, $cf);

    $merged = $cf;
    $merged['tabla_origen'] = $cf['tabla_origen'] ?? '';
    $merged['original_lead_id'] = $cf['original_lead_id'] ?? null;
    $merged['full_name'] = $cf['names'] ?? 'N/A';
    $merged['submission_date'] = $cf['submission_date'] ?? '';
    $merged['fecha_cambio_cliente'] = $cf['fecha_cambio_cliente'] ?? '';
    $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';
    $merged['wedding_location'] = (isset($cf['wedding_location']) && trim((string) $cf['wedding_location']) !== '')
        ? $cf['wedding_location']
        : '';

    $cid = (int) ($cf['id'] ?? 0);
    $merged['has_appointment'] = ($cid > 0 && isset($appointmentsByClient[$cid])) ? 1 : 0;

    $leadRow = postLeadFetchLeadRowForContactForm($conn, $cf);
    if (is_array($leadRow)) {
        if (!empty($leadRow['full_name'])) {
            $merged['full_name'] = $leadRow['full_name'];
        } elseif (!empty($leadRow['names'])) {
            $merged['full_name'] = $leadRow['names'];
        } elseif (!empty($leadRow['name'])) {
            $merged['full_name'] = $leadRow['name'];
        }

        if (!empty($leadRow['fecha_cambio_cliente'])) {
            $merged['fecha_cambio_cliente'] = $leadRow['fecha_cambio_cliente'];
        } elseif (!empty($leadRow['created_time'])) {
            $merged['created_time'] = $leadRow['created_time'];
        } elseif (!empty($leadRow['created_at'])) {
            $merged['created_time'] = $leadRow['created_at'];
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

    $merged['campaign_name'] = (isset($cf['campaign_name']) && trim((string) $cf['campaign_name']) !== '')
        ? $cf['campaign_name']
        : 'N/A';

    if (empty($merged['wedding_location']) || $merged['wedding_location'] === 'N/A') {
        $altWl = trim((string) ($merged['where_are_you_getting_married_'] ?? ''));
        if ($altWl !== '') {
            $merged['wedding_location'] = $altWl;
        } else {
            $merged['wedding_location'] = 'N/A';
        }
    }

    if ($cid > 0 && isset($appointmentsByClient[$cid])) {
        $appt = $appointmentsByClient[$cid];
        if (isset($appt['idusu']) && $appt['idusu'] !== '') {
            if (!isset($merged['id_vendedor_asignado']) || empty($merged['id_vendedor_asignado'])) {
                $merged['id_vendedor_asignado'] = (int) $appt['idusu'];
            }
            if (!isset($merged['usuario_asignado']) || empty($merged['usuario_asignado'])) {
                $merged['usuario_asignado'] = (int) $appt['idusu'];
            }
        }
        if (!isset($merged['id_vendedor_asignado']) || empty($merged['id_vendedor_asignado'])) {
            if (isset($appt['id_vendedor_asignado']) && $appt['id_vendedor_asignado'] !== '') {
                $merged['id_vendedor_asignado'] = (int) $appt['id_vendedor_asignado'];
            }
        }
    }

    if ((int) ($cf['cliente'] ?? 0) === 1) {
        $merged['estatus'] = 'cliente';
        $merged['cliente'] = 1;
    }

    $engRaw = trim((string) ($merged['engagement'] ?? ''));
    if ($engRaw === '' || $engRaw === '0') {
        $engRaw = trim((string) ($merged['sfm_engagement'] ?? ''));
    }
    if ($engRaw !== '') {
        $merged['engagement'] = $engRaw;
    }

    postLeadNormalizeWeddingDisplayFields($merged);

    $merged['how_did_you_meet_raw'] = trim((string) ($merged['how_did_you_meet'] ?? ''));
    applyResolvedHowDidYouMeetToLead($merged);

    return $merged;
}

function postLeadMergeLeadRowIntoMerged(array &$merged, ?array $leadRow, array $cf)
{
    if (!is_array($leadRow)) {
        return;
    }

    if (isPostLeadEventosWpContactForm($cf)) {
        $plannerName = wpEventosCfResolvePlannerDisplayName(array_merge($merged, $leadRow, [
            'planner_contact_name' => trim((string) ($cf['names'] ?? '')),
        ]));
        if ($plannerName !== '') {
            $merged['wedding_planner_name'] = $plannerName;
        }
        if (!empty($leadRow['novios'])) {
            $merged['full_name'] = $leadRow['novios'];
            $merged['novios'] = $leadRow['novios'];
        }
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

function postLeadNormalizeWeddingDisplayFields(array &$merged)
{
    $city = trim((string) ($merged['city'] ?? ''));
    if ($city === '') {
        $city = trim((string) ($merged['ciudad_novios'] ?? ''));
    }
    $merged['city'] = $city;

    $wl = trim((string) ($merged['wedding_location'] ?? ''));
    if ($wl === '' || strcasecmp($wl, 'N/A') === 0) {
        $wl = trim((string) ($merged['lugar'] ?? ''));
        if ($wl === '') {
            $wl = trim((string) ($merged['where_are_you_getting_married_'] ?? ''));
        }
    }
    $merged['wedding_location'] = $wl !== '' ? $wl : 'N/A';

    $wd = trim((string) ($merged['wedding_date'] ?? ''));
    if ($wd === '') {
        $wd = trim((string) ($merged['when_are_you_getting_married_'] ?? ''));
    }
    if ($wd === '') {
        $wd = trim((string) ($merged['fecha'] ?? ''));
    }
    if ($wd !== '') {
        $merged['wedding_date'] = $wd;
    }

    $email = trim((string) ($merged['email'] ?? ''));
    if ($email === '') {
        $email = trim((string) ($merged['email_address'] ?? ''));
    }
    if ($email !== '') {
        $merged['email'] = $email;
    }
}

function postLeadFinalizeLeadForDisplay(
    $conn,
    array $merged,
    array $cf,
    array $appointmentsByClient,
    array $appointmentsByEventId,
    array $wpEngagementByEventId,
    ?array $leadRow = null,
    ?array $appt = null
) {
    $cf = postLeadEnsureFullContactFormRow($conn, $cf);

    if (postLeadIsClienteStatus($merged) || (int) ($cf['cliente'] ?? 0) === 1) {
        $fechaCambioPreserve = trim((string) ($merged['fecha_cambio_cliente'] ?? ''));
        $result = postLeadBuildMergedLeadLikeClientes($conn, $cf, $appointmentsByClient);
        if ($fechaCambioPreserve !== '') {
            $result['fecha_cambio_cliente'] = $fechaCambioPreserve;
        }
        return $result;
    }

    if ($appt === null) {
        $appt = postLeadResolveAppointmentForLead($cf, $appointmentsByClient, $appointmentsByEventId);
    }

    if (isPostLeadEventosWpContactForm($cf)) {
        $merged = wpEventosCfEnrichMergedLead($conn, $merged, $cf);
    }

    if ($leadRow === null) {
        $leadRow = postLeadFetchLeadRowForContactForm($conn, $cf);
    }
    postLeadMergeLeadRowIntoMerged($merged, $leadRow, $cf);
    postLeadNormalizeWeddingDisplayFields($merged);

    $cfCampaign = trim((string) ($cf['campaign_name'] ?? ''));
    if ($cfCampaign === '') {
        $cfCampaign = trim((string) ($merged['campaign_name'] ?? ''));
    }
    $merged['campaign_name'] = $cfCampaign;
    $merged['first_contact_channel'] = resolveFirstContactChannelForLead($merged, $leadRow);
    $merged['engagement'] = postLeadResolveEngagementValue($merged, $appointmentsByClient, $wpEngagementByEventId);

    if (function_exists('enrichLeadHowLongKnownUsFromContactForm')) {
        enrichLeadHowLongKnownUsFromContactForm($merged, $cf);
    }

    return $merged;
}

function postLeadDisplayLeadFromCohortItem($conn, array $item, array $appointmentsByClient, array $appointmentsByEventId, array $wpEngagementByEventId)
{
    $cf = $item['cf'] ?? [];
    $appt = $item['appt'] ?? [];
    $estatus = (string) ($item['estatus'] ?? '');
    $createdTime = (string) ($item['created_time'] ?? '');

    if (isPostLeadEventosWpContactForm($cf)) {
        $merged = $cf;
        $merged['tabla_origen'] = $cf['tabla_origen'] ?? 'eventos_wp';
        $merged['form_name'] = $cf['form_name'] ?? 'eventos_wp';
        $merged['original_lead_id'] = $cf['original_lead_id'] ?? null;
        $merged['full_name'] = trim((string) ($cf['novios'] ?? $cf['names'] ?? $cf['full_name'] ?? 'N/A'));
        if ($merged['full_name'] === '') {
            $merged['full_name'] = 'N/A';
        }
        $merged['submission_date'] = $cf['submission_date'] ?? '';
        $merged['created_time'] = $createdTime !== '' ? $createdTime : wpEventosCfResolveDateField($merged);
        $merged['has_appointment'] = !empty($appt) ? 1 : 0;
        $merged['estatus'] = $estatus !== '' ? $estatus : postLeadResolveEstatusForLead($cf, !empty($appt) ? $appt : null);

        if (is_array($appt) && !empty($appt)) {
            if (!empty($appt['idusu'])) {
                $merged['id_vendedor_asignado'] = (int) $appt['idusu'];
                $merged['usuario_asignado'] = (int) $appt['idusu'];
            } elseif (!empty($appt['id_vendedor_asignado'])) {
                $merged['id_vendedor_asignado'] = (int) $appt['id_vendedor_asignado'];
            }
        }

        $merged = postLeadFinalizeLeadForDisplay(
            $conn,
            $merged,
            $cf,
            $appointmentsByClient,
            $appointmentsByEventId,
            $wpEngagementByEventId,
            null,
            !empty($appt) ? $appt : null
        );

        $merged['how_did_you_meet_raw'] = trim((string) ($merged['how_did_you_meet'] ?? ''));
        applyResolvedHowDidYouMeetToLead($merged);

        return $merged;
    }

    $cf = postLeadEnsureFullContactFormRow($conn, $cf);
    if ($estatus === 'cliente' || (int) ($cf['cliente'] ?? 0) === 1) {
        $merged = postLeadBuildMergedLeadLikeClientes($conn, $cf, $appointmentsByClient);
        $merged['estatus'] = 'cliente';
        $merged['cliente'] = 1;
        $fechaCambio = $createdTime !== '' ? $createdTime : dashComercialResolveClienteFechaCambioLikeClientes($conn, $cf);
        if ($fechaCambio !== '') {
            $merged['fecha_cambio_cliente'] = $fechaCambio;
        }
        return $merged;
    }

    $merged = postLeadBuildMergedLeadFromContactForm(
        $conn,
        $cf,
        $appointmentsByClient,
        $appointmentsByEventId,
        $wpEngagementByEventId
    );
    $merged['estatus'] = postLeadResolveEstatusForLead($cf, !empty($appt) ? $appt : null);
    if ($merged['estatus'] === '') {
        $merged['estatus'] = $estatus !== '' ? $estatus : ($merged['estatus'] ?? '');
    }

    if ($createdTime !== '') {
        $merged['created_time'] = $createdTime;
    }

    return $merged;
}

/**
 * Sincroniza allLeads con dashComercialBuildAtendidosCohort (WP eventos_wp + clientes + atendidos).
 */
function postLeadMergeAtendidosCohortIntoAllLeads($conn, array &$allLeads, array $atendidosCohort, array $appointmentsByClient, array $appointmentsByEventId, array $wpEngagementByEventId)
{
    $existingIds = [];
    foreach ($allLeads as $index => $lead) {
        $cfId = (int) ($lead['id'] ?? 0);
        if ($cfId > 0) {
            $existingIds[$cfId] = $index;
        }
    }

    foreach ($atendidosCohort as $cfId => $item) {
        $cfId = (int) $cfId;
        if ($cfId <= 0) {
            continue;
        }

        if (isset($existingIds[$cfId])) {
            $idx = $existingIds[$cfId];
            $refreshed = postLeadDisplayLeadFromCohortItem(
                $conn,
                $item,
                $appointmentsByClient,
                $appointmentsByEventId,
                $wpEngagementByEventId
            );
            $allLeads[$idx] = array_merge($allLeads[$idx], $refreshed);
            if (!empty($item['estatus'])) {
                $allLeads[$idx]['estatus'] = $item['estatus'];
            }
            if (($item['estatus'] ?? '') === 'cliente' || (int) (($item['cf']['cliente'] ?? 0)) === 1) {
                $allLeads[$idx]['cliente'] = 1;
            }
            continue;
        }

        $allLeads[] = postLeadDisplayLeadFromCohortItem(
            $conn,
            $item,
            $appointmentsByClient,
            $appointmentsByEventId,
            $wpEngagementByEventId
        );
        $existingIds[$cfId] = count($allLeads) - 1;
    }
}

function postLeadBuildMergedLeadFromContactForm($conn, array $cf, array $appointmentsByClient, array $appointmentsByEventId, array $wpEngagementByEventId)
{
    $merged = $cf;
    $merged['tabla_origen'] = $cf['tabla_origen'] ?? '';
    $merged['original_lead_id'] = $cf['original_lead_id'] ?? null;
    $merged['full_name'] = $cf['names'] ?? 'N/A';
    $merged['submission_date'] = $cf['submission_date'] ?? '';
    $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';
    $merged['has_appointment'] = isset($appointmentsByClient[(int) ($cf['id'] ?? 0)]) ? 1 : 0;

    $appt = postLeadResolveAppointmentForLead($cf, $appointmentsByClient, $appointmentsByEventId);
    if (is_array($appt)) {
        if (isset($appt['idusu']) && $appt['idusu'] !== '') {
            if (!isset($merged['id_vendedor_asignado']) || empty($merged['id_vendedor_asignado'])) {
                $merged['id_vendedor_asignado'] = (int) $appt['idusu'];
            }
            if (!isset($merged['usuario_asignado']) || empty($merged['usuario_asignado'])) {
                $merged['usuario_asignado'] = (int) $appt['idusu'];
            }
        }
        if (!isset($merged['id_vendedor_asignado']) || empty($merged['id_vendedor_asignado'])) {
            if (isset($appt['id_vendedor_asignado']) && $appt['id_vendedor_asignado'] !== '') {
                $merged['id_vendedor_asignado'] = (int) $appt['id_vendedor_asignado'];
            }
        }
    }

    $merged = postLeadFinalizeLeadForDisplay(
        $conn,
        $merged,
        $cf,
        $appointmentsByClient,
        $appointmentsByEventId,
        $wpEngagementByEventId,
        null,
        $appt
    );

    $howDidYouMeetRaw = trim((string) ($merged['how_did_you_meet'] ?? ''));
    $merged['how_did_you_meet_raw'] = $howDidYouMeetRaw;
    applyResolvedHowDidYouMeetToLead($merged);

    return $merged;
}

/**
 * Incorpora clientes cerrados de contact_form (cliente = 1) no presentes en allLeads.
 * @deprecated Usar postLeadMergeAtendidosCohortIntoAllLeads + dashComercialBuildAtendidosCohort.
 */
function postLeadMergeClientesContactFormIntoAllLeads($conn, array &$allLeads, array $appointmentsByClient, array $appointmentsByEventId, array $wpEngagementByEventId)
{
    $existingIds = [];
    foreach ($allLeads as $lead) {
        $cfId = (int) ($lead['id'] ?? 0);
        if ($cfId > 0) {
            $existingIds[$cfId] = true;
        }
    }

    $res = $conn->query(
        "SELECT * FROM contact_form
         WHERE cliente = 1
           AND LOWER(COALESCE(tabla_origen, '')) NOT IN ('wedding_planners', 'wedding_planner')
         ORDER BY submission_date DESC"
    );
    if (!$res) {
        return;
    }

    while ($cf = $res->fetch_assoc()) {
        $cfId = (int) ($cf['id'] ?? 0);
        if ($cfId <= 0 || isset($existingIds[$cfId])) {
            continue;
        }

        $fechaCambio = dashComercialResolveClienteFechaCambioLikeClientes($conn, $cf);
        if ($fechaCambio === '' || strpos($fechaCambio, '0000-00-00') === 0) {
            continue;
        }

        $merged = postLeadBuildMergedLeadFromContactForm(
            $conn,
            $cf,
            $appointmentsByClient,
            $appointmentsByEventId,
            $wpEngagementByEventId
        );
        $merged['fecha_cambio_cliente'] = $fechaCambio;
        $merged['cliente'] = 1;
        $merged['estatus'] = 'cliente';

        $allLeads[] = $merged;
        $existingIds[$cfId] = true;
    }
}

function postLeadLeadPassesPlataformaFilter(array $lead, $filterPlataforma, array $tablaTipoMap)
{
    if ($filterPlataforma === '') {
        return true;
    }

    // Wedding Planners (eventos_wp en contact_form): mismo criterio que Dashboard Comercial.
    if (isPostLeadEventosWpContactForm($lead)) {
        return true;
    }

    $tablaOrigen = $lead['tabla_origen'] ?? '';
    $tipoTabla = isset($tablaTipoMap[$tablaOrigen]) ? $tablaTipoMap[$tablaOrigen] : -1;
    $campaignNameLower = strtolower(trim($lead['campaign_name'] ?? ''));
    $isB1B2 = isB1B2CampaignName($campaignNameLower);

    if ($filterPlataforma === 'organico') {
        if ($isB1B2) {
            return false;
        }
        return ($tipoTabla === 0 || $campaignNameLower === 'website' || $campaignNameLower === 'reg manual');
    }

    if ($filterPlataforma === 'campania') {
        return ($tipoTabla === 1 || $isB1B2);
    }

    return true;
}

function postLeadLeadPassesDateFiltersForView(
    array $lead,
    $startDate,
    $endDate,
    $postLeadsMinDate,
    array $fechaAtencionByContactFormId,
    array $appointmentsByClient,
    array $appointmentsByEventId,
    array $fechaMuertoHistorialByCfId = []
) {
    $dateField = postLeadResolvePostCohortDateForLead(
        $lead,
        $fechaAtencionByContactFormId,
        $appointmentsByClient,
        $appointmentsByEventId,
        $fechaMuertoHistorialByCfId
    );
    if ($dateField === '') {
        return false;
    }

    $d = extractDateFromTimestamp($dateField);
    if ($d === null) {
        return false;
    }

    return postLeadPassesDateFilters($d, $startDate, $endDate, $postLeadsMinDate);
}

function postLeadAppointmentDateIsValid(?array $appt)
{
    if (!is_array($appt)) {
        return true;
    }

    $apptFechaRaw = trim($appt['fecha'] ?? '');
    $apptHoraRaw = trim($appt['hora'] ?? '');
    $apptTs = ($apptFechaRaw !== '') ? strtotime($apptFechaRaw . ' ' . $apptHoraRaw) : false;

    return !($apptFechaRaw === '' || $apptFechaRaw === '0000-00-00' || $apptTs === false || $apptTs <= 0);
}

function postLeadMapCalendarioEstatus($rawStatus)
{
    $intStatus = is_numeric($rawStatus) ? intval($rawStatus) : null;
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
    if ($intStatus === 4) {
        return 'cliente';
    }

    return is_string($rawStatus) ? $rawStatus : '';
}

function postLeadBatchLoadCurrentHistorialEstatusByLeads($conn, array $leads, array $appointmentsByClient, array $appointmentsByEventId)
{
    $calToCf = [];
    foreach ($leads as $lead) {
        $cfId = (int) ($lead['id'] ?? 0);
        if ($cfId <= 0) {
            continue;
        }
        $appt = postLeadResolveAppointmentForLead($lead, $appointmentsByClient, $appointmentsByEventId);
        if (!is_array($appt)) {
            continue;
        }
        $calId = (int) ($appt['id'] ?? 0);
        if ($calId > 0) {
            $calToCf[$calId] = $cfId;
        }
    }

    if (empty($calToCf)) {
        return [];
    }

    $byCal = plannerProfileBatchLoadCurrentHistorialEstatusByCalendarioIds($conn, array_keys($calToCf));
    $byCf = [];
    foreach ($byCal as $calId => $code) {
        if (isset($calToCf[$calId])) {
            $byCf[$calToCf[$calId]] = $code;
        }
    }

    return $byCf;
}

function postLeadResolveCurrentDisplayEstatus(array $lead, array $currentHistorialEstatusByCfId, array $appointmentsByClient, array $appointmentsByEventId)
{
    global $conn;

    if ((int) ($lead['cliente'] ?? 0) === 1) {
        return 'cliente';
    }

    if (isPostLeadEventosWpContactForm($lead)) {
        if (isset($conn)) {
            $cf = wpEventosCfHydratePlannerContactFormFields($conn, $lead);
            $plannerEstatus = wpEventosCfResolvePlannerTipoClienteEstatus($cf);
            if ($plannerEstatus !== null) {
                return $plannerEstatus;
            }
        }
    }

    $cfId = (int) ($lead['id'] ?? 0);
    if ($cfId > 0 && isset($currentHistorialEstatusByCfId[$cfId])) {
        $mapped = postLeadMapCalendarioEstatus($currentHistorialEstatusByCfId[$cfId]);
        if ($mapped !== '') {
            return $mapped;
        }
    }

    $appt = postLeadResolveAppointmentForLead($lead, $appointmentsByClient, $appointmentsByEventId);
    if (is_array($appt) && isset($appt['estatus'])) {
        $mapped = postLeadMapCalendarioEstatus($appt['estatus']);
        if ($mapped !== '') {
            return $mapped;
        }
    }

    $st = mb_strtolower(trim((string) ($lead['estatus'] ?? '')), 'UTF-8');
    if ($st !== '' && is_numeric($st)) {
        $mapped = postLeadMapCalendarioEstatus($st);
        if ($mapped !== '') {
            return $mapped;
        }
    }

    return $st !== '' ? $st : 'agendado';
}

function postLeadResolveEngagementValue(array $lead, array $appointmentsByClient, array $wpEngagementByEventId)
{
    $tablaOrigen = strtolower(trim((string) ($lead['tabla_origen'] ?? '')));
    $origId = intval($lead['original_lead_id'] ?? 0);
    $cfId = intval($lead['id'] ?? 0);

    if ($tablaOrigen === 'eventos_wp' && $origId > 0 && isset($wpEngagementByEventId[$origId])) {
        return $wpEngagementByEventId[$origId];
    }

    $current = intval($lead['engagement'] ?? 0);
    if ($current >= 1 && $current <= 3) {
        return $current;
    }

    if ($cfId > 0 && isset($appointmentsByClient[$cfId])) {
        $fromCalendar = intval($appointmentsByClient[$cfId]['cliente_engagement'] ?? 0);
        if ($fromCalendar >= 1 && $fromCalendar <= 3) {
            return $fromCalendar;
        }
    }

    return $current;
}

function formatCreatedTime($dateString)
{
    if (empty($dateString))
        return '';
    $timestamp = strtotime($dateString);
    // Tratar timestamps inválidos o no positivos como ausencia de fecha para evitar años extraños como "-0001"
    if ($timestamp === false || $timestamp <= 0)
        return '';
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $month = $months[date('n', $timestamp) - 1];
    $day = date('j', $timestamp);
    $year = date('Y', $timestamp);
    $hour = date('g', $timestamp);
    $min = date('i', $timestamp);
    $ampm = date('A', $timestamp) == 'AM' ? 'a.m.' : 'p.m.';
    return "$day de $month de $year a las $hour:$min $ampm";
}

function formatArrivalDateTime($dateString)
{
    if (empty($dateString))
        return '';
    $timestamp = strtotime($dateString);
    if ($timestamp === false || $timestamp <= 0)
        return '';
    return date('d/m/Y h:i a', $timestamp);
}

// Obtener todos los IDs de contact_form que tienen citas en calendario
$appointmentIds = [];
$appointmentQuery = $conn->query("SELECT DISTINCT idclie FROM calendario");
if ($appointmentQuery && $appointmentQuery->num_rows > 0) {
    while ($row = $appointmentQuery->fetch_assoc()) {
        $appointmentIds[] = intval($row['idclie']);
    }
}

// Si hay citas, obtener datos de las citas para extraer el estatus (mapear por idclie)
$appointmentsByClient = [];
if (!empty($appointmentIds)) {
    $idsList = implode(',', array_map('intval', $appointmentIds));
    $apptRes = $conn->query("SELECT * FROM calendario WHERE idclie IN ($idsList)");
    if ($apptRes && $apptRes->num_rows > 0) {
        while ($ar = $apptRes->fetch_assoc()) {
            $idclie = isset($ar['idclie']) ? intval($ar['idclie']) : 0;
            if ($idclie <= 0)
                continue;

            // Si ya existe uno, elegir el más reciente por fecha+h ora si están disponibles, sino por id
            if (!isset($appointmentsByClient[$idclie])) {
                $appointmentsByClient[$idclie] = $ar;
            } else {
                $prev = $appointmentsByClient[$idclie];
                $replace = false;
                if (!empty($ar['fecha']) && !empty($prev['fecha'])) {
                    $t1 = strtotime($ar['fecha'] . ' ' . ($ar['hora'] ?? '')) ?: 0;
                    $t2 = strtotime($prev['fecha'] . ' ' . ($prev['hora'] ?? '')) ?: 0;
                    if ($t1 > $t2)
                        $replace = true;
                } elseif (!empty($ar['id']) && !empty($prev['id'])) {
                    if (intval($ar['id']) > intval($prev['id']))
                        $replace = true;
                }

                if ($replace)
                    $appointmentsByClient[$idclie] = $ar;
            }
        }
    }
}

$wpEngagementByEventId = [];
$wpEngagementRes = $conn->query("SELECT idclie, cliente_engagement FROM calendario WHERE tipo = 1 AND eliminado = 0 AND cliente_engagement IS NOT NULL AND cliente_engagement > 0 ORDER BY id DESC");
if ($wpEngagementRes && $wpEngagementRes->num_rows > 0) {
    while ($wpEngRow = $wpEngagementRes->fetch_assoc()) {
        $eventId = intval($wpEngRow['idclie'] ?? 0);
        if ($eventId > 0 && !isset($wpEngagementByEventId[$eventId])) {
            $wpEngagementByEventId[$eventId] = intval($wpEngRow['cliente_engagement']);
        }
    }
}

$appointmentsByEventId = wpEventosCfGetAppointmentsByEventId($conn);

// Consultar registros de contact_form (solo los que tienen cita en calendario)
$allLeads = [];
if (!empty($appointmentIds)) {
    // Construir lista segura de IDs
    $idsList = implode(',', array_map('intval', $appointmentIds));
    $sql = "SELECT * FROM contact_form WHERE id IN ($idsList) ORDER BY submission_date DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($cf = $result->fetch_assoc()) {
            // Excluir registros provienen de Wedding Planners (no queremos nada de WP aquí)
            $tablaOrigenRaw = strtolower(trim($cf['tabla_origen'] ?? ''));
            if ($tablaOrigenRaw === 'wedding_planners' || $tablaOrigenRaw === 'wedding_planner') {
                // Saltar este registro
                continue;
            }

            // Datos básicos del contact_form
            $merged = $cf;
            $merged['tabla_origen'] = $cf['tabla_origen'] ?? '';
            $merged['original_lead_id'] = $cf['original_lead_id'] ?? null;
            $merged['full_name'] = $cf['names'] ?? 'N/A';
            $merged['submission_date'] = $cf['submission_date'] ?? '';
            $merged['created_time'] = $cf['created_time'] ?? $cf['submission_date'] ?? '';
            $merged['has_appointment'] = in_array(intval($cf['id']), $appointmentIds) ? 1 : 0;

            $appt = postLeadResolveAppointmentForLead($cf, $appointmentsByClient, $appointmentsByEventId);
            if ($appt !== null && !postLeadAppointmentDateIsValid($appt)) {
                continue;
            }
            if (is_array($appt)) {
                if (isset($appt['idusu']) && $appt['idusu'] !== '') {
                    if (!isset($merged['id_vendedor_asignado']) || empty($merged['id_vendedor_asignado'])) {
                        $merged['id_vendedor_asignado'] = intval($appt['idusu']);
                    }
                    if (!isset($merged['usuario_asignado']) || empty($merged['usuario_asignado'])) {
                        $merged['usuario_asignado'] = intval($appt['idusu']);
                    }
                }
                if (!isset($merged['id_vendedor_asignado']) || empty($merged['id_vendedor_asignado'])) {
                    if (isset($appt['id_vendedor_asignado']) && $appt['id_vendedor_asignado'] !== '') {
                        $merged['id_vendedor_asignado'] = intval($appt['id_vendedor_asignado']);
                    }
                }
            }

            $merged = postLeadFinalizeLeadForDisplay(
                $conn,
                $merged,
                $cf,
                $appointmentsByClient,
                $appointmentsByEventId,
                $wpEngagementByEventId,
                null,
                $appt
            );

            $merged['estatus'] = postLeadResolveEstatusForLead($cf, $appt);

            // Excluir registros con estatus 'agendado'
            if ($merged['estatus'] === 'agendado') {
                continue;
            }
            // Excluir leads comerciales WP+cliente; no aplica a eventos_wp (sesiones de planner)
            $howDidYouMeetRaw = trim((string)($merged['how_did_you_meet'] ?? ''));
            $merged['how_did_you_meet_raw'] = $howDidYouMeetRaw;
            if (!isPostLeadEventosWpContactForm($cf) && $howDidYouMeetRaw === '1' && $merged['estatus'] === 'cliente') {
                continue;
            }

            // Resolver how_did_you_meet: how_long_known_us prevalece en registro manual / website
            applyResolvedHowDidYouMeetToLead($merged);

            $allLeads[] = $merged;
        }
    }
}

// Wedding planners atendidos: contact_form eventos_wp (con cita tipo 1 o sin cita directa)
$loadedPostLeadCfIds = [];
foreach ($allLeads as $loadedLead) {
    $loadedId = intval($loadedLead['id'] ?? 0);
    if ($loadedId > 0) {
        $loadedPostLeadCfIds[$loadedId] = true;
    }
}

wpEventosCfAppendToAllLeads(
    $conn,
    $allLeads,
    $appointmentsByClient,
    $appointmentsByEventId,
    $wpEngagementByEventId,
    $loadedPostLeadCfIds,
    'postLeadResolveEngagementValue',
    ['estatus_resolver' => 'postLeadResolveEstatusForLead']
);

// Calcular conteo de registros en contact_form con campaign_name no vacío (para mostrar en un card)
$campaignCount = 0;
foreach ($allLeads as $lead) {
    $cn = isset($lead['campaign_name']) ? trim($lead['campaign_name']) : '';
    if ($cn === '' || mb_strtolower($cn, 'UTF-8') === 'n/a') continue;
    $campaignCount++;
}

// Leer filtro de plataforma desde GET
$filterPlataforma = isset($_GET['filter_plataforma']) ? trim($_GET['filter_plataforma']) : '';
// Etiqueta amigable para mostrar en los cards: (todo|Campañas digitales|organico)
$platformLabel = 'todo';
if ($filterPlataforma === 'campania') {
    $platformLabel = 'Campañas digitales';
} elseif ($filterPlataforma === 'organico') {
    $platformLabel = 'organico';
} 

// Obtener mapa de tabla_origen => tipo desde tablas_leads para filtro de plataforma
$tablaTipoMap = [];
$sqlTablasMap = "SELECT nombre, tipo FROM tablas_leads";
$resultTablasMap = $conn->query($sqlTablasMap);
if ($resultTablasMap && $resultTablasMap->num_rows > 0) {
    while ($row = $resultTablasMap->fetch_assoc()) {
        $tablaTipoMap[$row['nombre']] = intval($row['tipo']);
    }
}

// Leer filtros de fecha desde GET (se usan más abajo para conteos)
// Si el usuario no proporciona fechas, por defecto mostrar los últimos 14 días
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
// Persistir filtros de fecha en sesión para compartirlos entre consulta_leads, consulta_post_leads y clientes
if (isset($_GET['show_all'])) {
    unset($_SESSION['leads_filter_start'], $_SESSION['leads_filter_end']);
} elseif ($startDate !== '' || $endDate !== '') {
    $_SESSION['leads_filter_start'] = $startDate;
    $_SESSION['leads_filter_end']   = $endDate;
} elseif (!empty($_SESSION['leads_filter_start']) || !empty($_SESSION['leads_filter_end'])) {
    $startDate = $_SESSION['leads_filter_start'] ?? '';
    $endDate   = $_SESSION['leads_filter_end']   ?? '';
}
// Solo aplicar el default cuando NO se pida explícitamente "mostrar todo" (show_all=1)
if ($startDate === '' && $endDate === '' && empty($_GET['show_all'])) {
    // fecha de hoy como end_date y start_date 14 días antes
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-14 days', strtotime($endDate)));
}
$postLeadsMinDate = dashComercialGetMinPeriodDate();
if ($startDate !== '' || $endDate !== '') {
    list($startDate, $endDate) = dashComercialNormalizePeriodDates($startDate, $endDate);
}
// (Código de orígenes y medios removido - se usa filtro de Plataforma)

// (Código de estatus únicos, medios únicos y filtros origen/medio/estatus removido)

// Helper: Normalize origen (c1., c2. -> c1/c2) - ensure function available globally
if (!function_exists('normalizeOrigen')) {
    function normalizeOrigen($orig) {
        if (!$orig) return '';
        if (preg_match('/^c(\d+)\./i', $orig, $m)) return 'c' . $m[1];
        return $orig;
    }
}

// Helper: determina si un lead tiene estatus 'fantasma'.
// Acepta tanto la cadena "fantasma" en diferentes mayúsculas como el valor numérico 2.
if (!function_exists('isFantasmaLead')) {
    function isFantasmaLead($lead) {
        $st = $lead['estatus'] ?? '';
        if (is_numeric($st) && intval($st) === 2) return true;
        $stl = mb_strtolower(trim((string)$st), 'UTF-8');
        return $stl === 'fantasma';
    }
}

// Helper: extrae fecha de un timestamp que puede ser ISO 8601 o datetime
// Similar a la lógica SQL: CASE WHEN created_time LIKE '%T%' THEN ... ELSE ... END
if (!function_exists('extractDateFromTimestamp')) {
    function extractDateFromTimestamp($timestamp) {
        if (empty($timestamp)) return null;
        
        // Si contiene 'T', es formato ISO 8601 (2026-02-03T09:12:17-06:00)
        if (strpos($timestamp, 'T') !== false) {
            // Extraer los primeros 19 caracteres (YYYY-MM-DDTHH:MM:SS)
            $normalized = substr($timestamp, 0, 19);
            // Convertir a timestamp de PHP
            $ts = strtotime($normalized);
            if ($ts === false) return null;
            return date('Y-m-d', $ts);
        }
        
        // Si es datetime normal (2026-02-03 09:12:17), extraer fecha directamente
        $ts = strtotime($timestamp);
        if ($ts === false) return null;
        return date('Y-m-d', $ts);
    }
}

if (!function_exists('postLeadPassesDateFilters')) {
    function postLeadPassesDateFilters($dateYmd, $startDate, $endDate, $minDate)
    {
        if (!is_string($dateYmd) || $dateYmd === '' || $dateYmd < $minDate) {
            return false;
        }
        if ($startDate !== '' || $endDate !== '') {
            $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
            $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;
            if ($sd && $dateYmd < $sd) {
                return false;
            }
            if ($ed && $dateYmd > $ed) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('getDondeNosConocioLabel')) {
    function getDondeNosConocioLabel($lead) {
        $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
        $hearRaw = trim((string)($lead['hear_about_us'] ?? ''));

        $howMap = [
            '1' => 'Wedding Planner',
            '2' => 'Community',
            '3' => 'New Audience'
        ];

        $hearMap = [
            '1' => 'Meta Ads — anuncio en Instagram / Facebook',
            '2' => 'SEO — buscaron en Google',
            '3' => 'Colaboración / Influencer / Famoso',
            '4' => 'Publicación / Prensa / Revista',
            '5' => 'Otro'
        ];

        $howLabel = '';
        if ($howRaw !== '') {
            $howLabel = $howMap[$howRaw] ?? $howRaw;
        }

        $hearLabel = '';
        if ($hearRaw !== '') {
            $hearLabel = $hearMap[$hearRaw] ?? $hearRaw;
        }

        if ($howLabel !== '' && $hearLabel !== '') {
            return $howLabel . ' / ' . $hearLabel;
        }
        if ($howLabel !== '') {
            return $howLabel;
        }
        if ($hearLabel !== '') {
            return $hearLabel;
        }

        return 'N/A';
    }
}

if (!function_exists('normalizeFirstContactChannelLabel')) {
    function normalizeFirstContactChannelLabel($value) {
        $normalized = trim((string) $value);
        if ($normalized === '') return 'Sin dato';
        $key = mb_strtolower($normalized, 'UTF-8');
        $key = str_replace(['–', '—'], '-', $key);
        $key = preg_replace('/\s+/', ' ', $key);
        $map = [
            'ig'                           => 'IG',
            'whatsapp'                     => 'WhatsApp',
            'instagram dm - campaign'      => 'Instagram DM - Campaña',
            'instagram dm campaign'        => 'Instagram DM - Campaña',
            'instagram dm - organic'       => 'Instagram DM - Orgánico',
            'instagram dm organic'         => 'Instagram DM - Orgánico',
            'email'                        => 'Correo electrónico',
            'correo electronico'           => 'Correo electrónico',
            'correo electrónico'           => 'Correo electrónico',
            'mail'                         => 'Correo electrónico',
            'phone call'                   => 'Phone call',
            'llamada telefonica'           => 'Phone call',
            'llamada telefónica'           => 'Phone call',
        ];
        return $map[$key] ?? $normalized;
    }
}

if (!function_exists('getContactChannelBadgeLabel')) {
    function getContactChannelBadgeLabel($label) {
        $map = [
            'IG' => 'IG',
            'WhatsApp' => 'WhatsApp',
            'Instagram DM - Campaña' => 'IG',
            'Instagram DM - Orgánico' => 'IG',
            'Correo electrónico' => 'Email',
            'Llamada telefónica' => 'Phone Call',
            'Phone call' => 'Phone Call',
            'Sin dato' => 'Not asked'
        ];

        return $map[$label] ?? $label;
    }
}

if (!function_exists('getKnownUsBadgeLabel')) {
    function getKnownUsBadgeLabel($value) {
        $normalized = trim((string) $value);
        if ($normalized === '' || strcasecmp($normalized, 'Sin dato') === 0 || strcasecmp($normalized, 'Not asked') === 0) {
            return 'Not asked';
        }

        return $normalized;
    }
}

if (!function_exists('firstNonEmptyValue')) {
    function firstNonEmptyValue()
    {
        foreach (func_get_args() as $value) {
            if ($value === null) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }
}

if (!function_exists('mapTipoClienteValue')) {
    function mapTipoClienteValue($raw)
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return '';
        }

        if ($value === '1' || strcasecmp($value, 'Wedding Planner') === 0) {
            return 'Wedding Planner';
        }

        if ($value === '0' || strcasecmp($value, 'Cliente Final') === 0) {
            return 'Cliente Final';
        }

        return '';
    }
}

if (!function_exists('inferTipoClienteFromOriginData')) {
    function inferTipoClienteFromOriginData($howDidYouMeet, $tablaOrigen)
    {
        $tabla = strtolower(trim((string) $tablaOrigen));

        if (in_array($tabla, ['wp_eventos_afianzados', 'wp_citas_leads'], true)) {
            return 'Cliente Final';
        }

        if ($tabla === 'eventos_wp') {
            return 'Wedding Planner';
        }

        if ($tabla === 'wedding_planners') {
            return 'Wedding Planner';
        }

        $how = trim((string) $howDidYouMeet);
        if ($how === '1') {
            return 'Wedding Planner';
        }

        if (in_array($how, ['2', '3'], true)) {
            return 'Cliente Final';
        }

        return '';
    }
}

if (!function_exists('isTrustedClienteFinalTipo')) {
    function isTrustedClienteFinalTipo($tipoRaw, $lead, $howDidYouMeet)
    {
        if (mapTipoClienteValue($tipoRaw) !== 'Cliente Final') {
            return false;
        }

        $how = trim((string) $howDidYouMeet);
        if ($how === '1') {
            return false;
        }

        if (in_array($how, ['2', '3'], true)) {
            return true;
        }

        return strtolower(trim((string) ($lead['lead_status'] ?? ''))) === 'manual';
    }
}

if (!function_exists('getTipoClienteLabel')) {
    function getTipoClienteLabel($lead, $cfData = null)
    {
        $tablaOrigen = $lead['tabla_origen'] ?? '';

        $cfTipoCliente = null;
        $cfHowDidYouMeet = null;
        if (is_array($cfData)) {
            $cfTipoCliente = $cfData['tipo_cliente'] ?? null;
            $cfHowDidYouMeet = $cfData['how_did_you_meet'] ?? null;
        } elseif ($cfData !== null) {
            $cfTipoCliente = $cfData;
        }

        if (mapTipoClienteValue($cfTipoCliente) === 'Wedding Planner') {
            return 'Wedding Planner';
        }

        if (mapTipoClienteValue($lead['tipo_cliente'] ?? '') === 'Wedding Planner') {
            return 'Wedding Planner';
        }

        $howDidYouMeet = firstNonEmptyValue(
            $cfHowDidYouMeet,
            $lead['how_did_you_meet_raw'] ?? '',
            $lead['how_did_you_meet'] ?? ''
        );

        $inferred = inferTipoClienteFromOriginData($howDidYouMeet, $tablaOrigen);
        if ($inferred !== '') {
            return $inferred;
        }

        if (isTrustedClienteFinalTipo($cfTipoCliente, $lead, $howDidYouMeet)) {
            return 'Cliente Final';
        }

        if (isTrustedClienteFinalTipo($lead['tipo_cliente'] ?? '', $lead, $howDidYouMeet)) {
            return 'Cliente Final';
        }

        return '—';
    }
}

if (!function_exists('formatCampaignDisplayLabel')) {
    function formatCampaignDisplayLabel($value, $referrerUrl = '', $firstContactChannel = '') {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '—';
        }

        if (mb_strtolower($normalized, 'UTF-8') === 'reg manual') {
            return 'Cierres retroactivos';
        }

        // c-pattern: calcular base label pero NO retornar aún (puede necesitar sufijo)
        $baseLabel = preg_match('/^(c\d+)\b/i', $normalized, $matches)
            ? strtolower($matches[1])
            : $normalized;

        // Distinguir Website orgánico vs Website Google Ads
        if (strtolower($normalized) === 'website') {
            $hasTracking = false;
            if (trim((string)$referrerUrl) !== '') {
                $query = parse_url((string)$referrerUrl, PHP_URL_QUERY) ?? '';
                if ($query !== '') {
                    parse_str($query, $params);
                    $trackingKeys = ['gclid','gbraid','wbraid','fbclid','msclkid','ttclid',
                        'utm_source','utm_medium','utm_campaign','utm_content','utm_term','utm_id'];
                    foreach ($params as $k => $v) {
                        $kl = strtolower((string)$k);
                        if (strpos($kl, 'utm_') === 0 || in_array($kl, $trackingKeys, true)) {
                            $hasTracking = true;
                            break;
                        }
                    }
                }
            }
            return $hasTracking ? 'Website - Google Ads' : 'Website - Orgánico';
        }

        $lower = strtolower($normalized);

        if ($lower === 'mail') {
            return 'Mail - Orgánico';
        }

        if ($lower === 'whatsapp') {
            return 'WhatsApp - Orgánico';
        }

        if ($lower === 'ig organico') {
            return 'Ig - Orgánico';
        }

        // Prefijo según origen: b1/b2 → "IG - ", leadform/andromeda/c-pattern → "leadform - "
        $fccLower = strtolower(trim((string)$firstContactChannel));
        $normalizedLower = strtolower($normalized);
        $isCPattern = (bool) preg_match('/^(c\d+)\b/i', $normalized);
        if (isB1B2CampaignName($normalized)) {
            return 'IG - ' . $baseLabel;
        }
        $isLeadform = ($fccLower === 'leadform')
            || strpos($normalizedLower, 'andromeda') !== false
            || $isCPattern;
        $prefix = $isLeadform ? 'leadform - ' : '';
        return $prefix . $baseLabel;
    }
}

if (!function_exists('formatLeadDate')) {
    function formatLeadDate($dateString) {
        $value = trim((string) $dateString);
        if ($value === '') return '—';
        $timestamp = strtotime($value);
        if ($timestamp === false) return $value;
        return date('d/m/Y', $timestamp);
    }
}

if (!function_exists('formatPercentage')) {
    function formatPercentage($part, $total, $decimals = 1) {
        $total = floatval($total);
        if ($total <= 0) return '0%';
        $formatted = number_format((floatval($part) / $total) * 100, $decimals, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted . '%';
    }
}

if (!function_exists('getEngagementLabelWithEmoji')) {
    function getEngagementLabelWithEmoji($lead) {
        $raw = trim((string)($lead['engagement'] ?? ($lead['sfm_engagement'] ?? '')));
        if ($raw === '') return 'N/A';
        $normalized = mb_strtolower($raw, 'UTF-8');
        if ($normalized === '0') return 'Sin dato';
        if ($normalized === '1' || $normalized === 'bajo') return '😑 Bajo';
        if ($normalized === '2' || $normalized === 'medio') return '😃 Medio';
        if ($normalized === '3' || $normalized === 'alto') return '🔥 Alto';
        return $raw;
    }
}

// Calcular conteo total de leads que se muestran en la tabla (tienen cita) y conteo filtrado por fecha
$leadsCount = 0;
foreach ($allLeads as $lead) {
    $leadsCount++;
}
$leadsCountFiltered = 0;

$postLeadCfIdsForAtencion = [];
foreach ($allLeads as $lead) {
    $cfId = (int) ($lead['id'] ?? 0);
    if ($cfId > 0) {
        $postLeadCfIdsForAtencion[] = $cfId;
    }
}
$postLeadFechaAtencionByCfId = !empty($postLeadCfIdsForAtencion)
    ? plannerProfileBatchLoadFechaAtencionByContactFormIds($conn, $postLeadCfIdsForAtencion)
    : [];
$postLeadFechaMuertoHistorialByCfId = !empty($postLeadCfIdsForAtencion)
    ? plannerProfileBatchLoadFechaHistorialByContactFormIds($conn, $postLeadCfIdsForAtencion, [3])
    : [];

foreach ($allLeads as $lead) {
    $dateField = postLeadResolvePostCohortDateForLead(
        $lead,
        $postLeadFechaAtencionByCfId,
        $appointmentsByClient,
        $appointmentsByEventId,
        $postLeadFechaMuertoHistorialByCfId
    );
    if (empty($dateField)) {
        continue;
    }

    $d = extractDateFromTimestamp($dateField);
    if ($d === null) {
        continue;
    }

    if (!postLeadPassesDateFilters($d, $startDate, $endDate, $postLeadsMinDate)) {
        continue;
    }

    $leadsCountFiltered++;
}

// (Código de conteo de campaña filtrado removido - simplificado)

// Cohorte post-Q por fecha de atención (no mezcla clientes por fecha de cierre).
$postLeadsAtendidosCohort = dashComercialBuildPostLeadsCohort($conn, $startDate, $endDate);

// DEBUG TEMPORAL — remover después de diagnosticar
if (isset($_GET['debug_wp'])) {
    $debugCf84 = null;
    $cfRes84 = $conn->query("SELECT id, original_lead_id, tabla_origen, cliente, tipo_cliente, created_time FROM contact_form WHERE LOWER(TRIM(tabla_origen))='eventos_wp' AND original_lead_id=84 LIMIT 1");
    if ($cfRes84 && $cfRes84->num_rows > 0) { $debugCf84 = $cfRes84->fetch_assoc(); }
    $debugEv84 = null;
    $evRes84 = $conn->query("SELECT id, novios, estatus, fecha_atendido, tipo_cliente FROM eventos_wp WHERE id=84 LIMIT 1");
    if ($evRes84 && $evRes84->num_rows > 0) { $debugEv84 = $evRes84->fetch_assoc(); }
    $debugInCohort = isset($postLeadsAtendidosCohort[intval($debugCf84['id'] ?? 0)]);
    echo '<div style="background:#fef3c7;border:2px solid #d97706;padding:12px;margin:10px;font-family:monospace;font-size:13px;z-index:9999;position:relative">';
    echo '<strong>DEBUG WP (remover después)</strong><br>';
    echo 'Filtro fechas: <b>' . htmlspecialchars($startDate) . '</b> → <b>' . htmlspecialchars($endDate) . '</b><br>';
    echo 'Session start: <b>' . htmlspecialchars($_SESSION['leads_filter_start'] ?? '(vacío)') . '</b><br>';
    echo 'Session end: <b>' . htmlspecialchars($_SESSION['leads_filter_end'] ?? '(vacío)') . '</b><br>';
    echo 'eventos_wp #84: <b>' . ($debugEv84 ? 'estatus=' . $debugEv84['estatus'] . ' fecha_atendido=' . $debugEv84['fecha_atendido'] : 'NO ENCONTRADO') . '</b><br>';
    echo 'contact_form espejo: <b>' . ($debugCf84 ? 'id=' . $debugCf84['id'] . ' cliente=' . $debugCf84['cliente'] . ' tipo_cliente=' . ($debugCf84['tipo_cliente'] ?? 'NULL') : 'NO ENCONTRADO') . '</b><br>';
    echo 'En cohorte post-Q: <b>' . ($debugInCohort ? 'SÍ' : 'NO') . '</b><br>';
    echo 'Total en cohorte: <b>' . count($postLeadsAtendidosCohort) . '</b><br>';
    echo '</div>';
}
postLeadMergeAtendidosCohortIntoAllLeads(
    $conn,
    $allLeads,
    $postLeadsAtendidosCohort,
    $appointmentsByClient,
    $appointmentsByEventId,
    $wpEngagementByEventId
);

$cohortCfIds = array_map('intval', array_keys($postLeadsAtendidosCohort));
$missingAtencionIds = array_values(array_diff($cohortCfIds, array_map('intval', array_keys($postLeadFechaAtencionByCfId))));
if (!empty($missingAtencionIds)) {
    $postLeadFechaAtencionByCfId += plannerProfileBatchLoadFechaAtencionByContactFormIds($conn, $missingAtencionIds);
}
$missingMuertoHistorialIds = array_values(array_diff($cohortCfIds, array_map('intval', array_keys($postLeadFechaMuertoHistorialByCfId))));
if (!empty($missingMuertoHistorialIds)) {
    $postLeadFechaMuertoHistorialByCfId += plannerProfileBatchLoadFechaHistorialByContactFormIds($conn, $missingMuertoHistorialIds, [3]);
}

// Crear lista filtrada: cohorte Dashboard Comercial + filtros de plataforma/fecha de la vista.
$displayLeadsById = [];

foreach ($allLeads as $lead) {
    if (!postLeadLeadPassesDateFiltersForView(
        $lead,
        $startDate,
        $endDate,
        $postLeadsMinDate,
        $postLeadFechaAtencionByCfId,
        $appointmentsByClient,
        $appointmentsByEventId,
        $postLeadFechaMuertoHistorialByCfId
    )) {
        continue;
    }
    if (!postLeadLeadPassesPlataformaFilter($lead, $filterPlataforma, $tablaTipoMap)) {
        continue;
    }

    $cfId = (int) ($lead['id'] ?? 0);
    if ($cfId > 0) {
        $displayLeadsById[$cfId] = $lead;
    }
}

foreach ($postLeadsAtendidosCohort as $cfId => $item) {
    $cfId = (int) $cfId;
    if ($cfId <= 0 || isset($displayLeadsById[$cfId])) {
        continue;
    }

    $lead = postLeadDisplayLeadFromCohortItem(
        $conn,
        $item,
        $appointmentsByClient,
        $appointmentsByEventId,
        $wpEngagementByEventId
    );

    if (!postLeadLeadPassesPlataformaFilter($lead, $filterPlataforma, $tablaTipoMap)) {
        continue;
    }

    if (!postLeadLeadPassesDateFiltersForView(
        $lead,
        $startDate,
        $endDate,
        $postLeadsMinDate,
        $postLeadFechaAtencionByCfId,
        $appointmentsByClient,
        $appointmentsByEventId,
        $postLeadFechaMuertoHistorialByCfId
    )) {
        continue;
    }

    $displayLeadsById[$cfId] = $lead;
}

$displayLeads = [];
foreach (array_keys($postLeadsAtendidosCohort) as $cfId) {
    $cfId = (int) $cfId;
    if ($cfId > 0 && isset($displayLeadsById[$cfId])) {
        $displayLeads[] = $displayLeadsById[$cfId];
    }
}
foreach ($displayLeads as $idx => $lead) {
    $cfId = (int) ($lead['id'] ?? 0);
    $cfStub = ($cfId > 0 && isset($postLeadsAtendidosCohort[$cfId]['cf']))
        ? $postLeadsAtendidosCohort[$cfId]['cf']
        : $lead;
    $displayLeads[$idx] = postLeadFinalizeLeadForDisplay(
        $conn,
        $lead,
        $cfStub,
        $appointmentsByClient,
        $appointmentsByEventId,
        $wpEngagementByEventId
    );
    $displayLeads[$idx]['how_did_you_meet_raw'] = trim((string) ($displayLeads[$idx]['how_did_you_meet'] ?? ''));
    applyResolvedHowDidYouMeetToLead($displayLeads[$idx]);
}
$postLeadCurrentHistorialEstatusByCfId = postLeadBatchLoadCurrentHistorialEstatusByLeads(
    $conn,
    $displayLeads,
    $appointmentsByClient,
    $appointmentsByEventId
);
foreach ($displayLeads as $idx => $lead) {
    $displayLeads[$idx]['estatus'] = postLeadResolveCurrentDisplayEstatus(
        $lead,
        $postLeadCurrentHistorialEstatusByCfId,
        $appointmentsByClient,
        $appointmentsByEventId
    );
}
if ($searchQuery !== '') {
    $displayLeads = array_values(array_filter($displayLeads, static function ($lead) use ($searchQuery) {
        return wpEventosCfLeadMatchesSearch($lead, $searchQuery);
    }));
}
$postLeadsLlamadaRealizada = count($displayLeads);

$postFechaAtencionByContactFormId = $postLeadFechaAtencionByCfId;
$postFechaMuertoHistorialByContactFormId = $postLeadFechaMuertoHistorialByCfId;

// Índices de columnas ocultas para DataTables (evita desfase thead/tbody → tabla vacía)
$dtHiddenColumnTargets = [8, 9, 10];

// Asegurar que el conteo mostrado coincide con la lista que se muestra
$leadsCountFiltered = 0;
foreach ($displayLeads as $lead) {
    $leadsCountFiltered++;
}

// Contar leads con fecha_cambio_cliente
$leadsWithFechaCambio = 0;
foreach ($displayLeads as $lead) {
    $fechaCambio = isset($lead['fecha_cambio_cliente']) ? trim($lead['fecha_cambio_cliente']) : '';
    if (!empty($fechaCambio) && $fechaCambio !== '0000-00-00' && $fechaCambio !== '0000-00-00 00:00:00') {
        $leadsWithFechaCambio++;
    }
}

// Calcular ratio de conversión (leads totales / leads que se convirtieron a cliente)
$conversionRatio = 0;
if ($leadsWithFechaCambio > 0) {
    $conversionRatio =  $leadsWithFechaCambio/  $leadsCountFiltered;
}

// ============================================================================
// CÁLCULO DE MÉTRICAS PARA CARDS Y GRÁFICA (basado en datos filtrados)
// Estas métricas se actualizan automáticamente según los filtros aplicados:
// - Filtro de plataforma (organico/campania)
// - Filtro de fechas (start_date/end_date)
// ============================================================================

// ===== Conteo de post-leads en el día (por fecha de atención / cierre / evento) =====
$todayPostLeadsCount = 0;
$today = date('Y-m-d');
foreach ($displayLeads as $lead) {
    $dateField = postLeadResolvePostCohortDateForLead(
        $lead,
        $postLeadFechaAtencionByCfId,
        $appointmentsByClient,
        $appointmentsByEventId,
        $postLeadFechaMuertoHistorialByCfId
    );
    if (empty($dateField))
        continue;
    
    $d = extractDateFromTimestamp($dateField);
    if ($d === null)
        continue;
    
    if ($d === $today)
        $todayPostLeadsCount++;
} 

// Construir serie de Highcharts: contar leads post qualified por día (filtrados)
// Construir mapa diario con conteo y min/max de horas (para mostrar en tooltip)
$dayMap = []; // ['YYYY-MM-DD' => ['count'=>int, 'min'=>ts, 'max'=>ts]]
$globalMin = null;
$globalMax = null;
// Use $displayLeads to ensure the chart reflects any active date filters
foreach ($displayLeads as $lead) {
    $dateField = postLeadResolvePostCohortDateForLead(
        $lead,
        $postLeadFechaAtencionByCfId,
        $appointmentsByClient,
        $appointmentsByEventId,
        $postLeadFechaMuertoHistorialByCfId
    );
    if (empty($dateField))
        continue;
    
    $d = extractDateFromTimestamp($dateField);
    if ($d === null)
        continue;
    $ts = strtotime($dateField);
    if ($ts === false)
        continue;
    if (!isset($dayMap[$d])) {
        $dayMap[$d] = ['count' => 0, 'min' => $ts, 'max' => $ts];
    }
    $dayMap[$d]['count']++;
    if ($ts < $dayMap[$d]['min'])
        $dayMap[$d]['min'] = $ts;
    if ($ts > $dayMap[$d]['max'])
        $dayMap[$d]['max'] = $ts;

    if ($globalMin === null || $ts < $globalMin)
        $globalMin = $ts;
    if ($globalMax === null || $ts > $globalMax)
        $globalMax = $ts;
} 

if ($globalMin === null) {
    $seriesJson = json_encode([]);
    $datesJson = json_encode([]);
    $countsJson = json_encode([]);
    $seriesMetaJson = json_encode(new stdClass());
} else {
    // By default (no date filter) show last 60 days (including today).
    // If the user provided start_date / end_date via GET, respect those instead.
    $minChartTs = strtotime($postLeadsMinDate);
    if ($startDate === '' && $endDate === '') {
        $end = strtotime(date('Y-m-d')); // today at 00:00
        $start = strtotime(date('Y-m-d', strtotime('-59 days'))); // 60 days including today
        if ($start < $minChartTs) {
            $start = $minChartTs;
        }
    } else {
        // Start from dataset bounds but allow GET filters to override
        $start = strtotime(date('Y-m-d', $globalMin));
        $end = strtotime(date('Y-m-d', $globalMax));
        if ($startDate !== '') {
            $sd_ts = strtotime(date('Y-m-d', strtotime($startDate)));
            if ($sd_ts !== false)
                $start = $sd_ts;
        }
        if ($endDate !== '') {
            $ed_ts = strtotime(date('Y-m-d', strtotime($endDate)));
            if ($ed_ts !== false)
                $end = $ed_ts;
        }
    }

    // ensure valid range
    if ($start < $minChartTs) {
        $start = $minChartTs;
    }
    if ($end < $start)
        $end = $start;

    $series = [];
    $dates = [];
    $counts = [];
    $seriesMeta = [];
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $d = date('Y-m-d', $ts);
        $c = isset($dayMap[$d]) ? $dayMap[$d]['count'] : 0;
        // x value: start of day timestamp in ms (keeps x-axis per day)
        $x = $ts * 1000;
        $series[] = [$x, $c];
        $dates[] = $d;
        $counts[] = $c;

        if (isset($dayMap[$d])) {
            // guardar horas mín y máx en formato HH:MM para tooltip
            $metaMin = date('H:i', $dayMap[$d]['min']);
            $metaMax = date('H:i', $dayMap[$d]['max']);
            $seriesMeta[(string) $x] = ['min' => $metaMin, 'max' => $metaMax];
        }
    }
    $seriesJson = json_encode($series);
    $datesJson = json_encode($dates);
    $countsJson = json_encode($counts);
    $seriesMetaJson = json_encode($seriesMeta);
}

// ===== OBTENER LEADS PRE-CALIFICADOS (de tablas_leads) PARA LA GRÁFICA =====
$tablasLeads = [];
// Filtrar tablas según plataforma (igual que consulta_leads.php)
if ($filterPlataforma === 'organico') {
    $sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo = 0 ORDER BY nombre";
} elseif ($filterPlataforma === 'campania') {
    $sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo = 1 OR nombre = 'organic_leads' ORDER BY nombre";
} else {
    $sqlTablas = "SELECT nombre FROM tablas_leads WHERE tipo != 2 ORDER BY nombre";
}
$resultTablas = $conn->query($sqlTablas);
if ($resultTablas && $resultTablas->num_rows > 0) {
    while ($row = $resultTablas->fetch_assoc()) {
        $tablasLeads[] = $row['nombre'];
    }
}

$b1b2ValuesPreQ = ['b1', 'b1 (usa)', 'b1 usa', 'b2', 'b2 (mx)', 'b2 mx'];

// Consultar todos los leads de todas las tablas de leads pre-calificados
$allPreLeads = [];
foreach ($tablasLeads as $tableName) {
    $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $columns = [];
        $columnsResult = $conn->query("SHOW COLUMNS FROM `$tableName`");
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        $wherePre = [];
        if (in_array('descartado', $columns)) {
            $wherePre[] = "(descartado = 0 OR descartado IS NULL)";
        }
        // Aplicar filtro B1/B2 en organic_leads según plataforma
        if ($tableName === 'organic_leads' && in_array('campaign_name', $columns)) {
            $campaignColPre = "LOWER(TRIM(campaign_name))";
            $inListPre = "'" . implode("','", array_map([$conn, 'real_escape_string'], $b1b2ValuesPreQ)) . "'";
            if ($filterPlataforma === 'organico') {
                $wherePre[] = "($campaignColPre IS NULL OR $campaignColPre = '' OR $campaignColPre NOT IN ($inListPre))";
            } elseif ($filterPlataforma === 'campania') {
                $wherePre[] = "$campaignColPre IN ($inListPre)";
            }
        }
        $whereSqlPre = !empty($wherePre) ? implode(' AND ', $wherePre) : '1=1';
        $sqlLeads = "SELECT created_time FROM `$tableName` WHERE " . $whereSqlPre;
        $resultLeads = $conn->query($sqlLeads);
        if ($resultLeads && $resultLeads->num_rows > 0) {
            while ($lead = $resultLeads->fetch_assoc()) {
                $allPreLeads[] = $lead;
            }
        }
    }
}

// Construir mapa por día para leads pre-calificados
$preLeadsDayMap = [];
$totalPreLeadsFiltered = 0; // Contador de leads pre-qualified que pasan los filtros

foreach ($allPreLeads as $lead) {
    if (empty($lead['created_time']))
        continue;
    // Extraer la fecha local sin aplicar conversión de zona horaria (igual que el filtro SQL en consulta_leads.php).
    // created_time puede ser ISO 8601 (2026-02-03T09:12:17-06:00) o datetime (2026-02-03 09:12:17).
    $rawCT = $lead['created_time'];
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $rawCT, $_m)) {
        $d = $_m[1];
    } else {
        continue;
    }
    if (!postLeadPassesDateFilters($d, $startDate, $endDate, $postLeadsMinDate)) {
        continue;
    }
    if (!isset($preLeadsDayMap[$d]))
        $preLeadsDayMap[$d] = 0;
    $preLeadsDayMap[$d]++;
    $totalPreLeadsFiltered++; // Contar cada lead pre-qualified que pasa los filtros
}

// Generar serie de leads pre-calificados usando el mismo rango de fechas
if ($globalMin === null) {
    $preLeadsSeriesJson = json_encode([]);
} else {
    $preLeadsSeries = [];
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $d = date('Y-m-d', $ts);
        $c = isset($preLeadsDayMap[$d]) ? $preLeadsDayMap[$d] : 0;
        $x = $ts * 1000;
        $preLeadsSeries[] = [$x, $c];
    }
    $preLeadsSeriesJson = json_encode($preLeadsSeries);
}

// Realizar la consulta SQL para obtener todos los usuarios
$sql = "SELECT * FROM usuarios WHERE tipoUsu = 1";
$result = $conn->query($sql);

$vendedores = array(); // Array para almacenar los resultados

// Revisar que la consulta no falló (evitar ->num_rows sobre boolean false)
if ($result && $result->num_rows > 0) {
    // Si hay resultados, los almacenamos en el array
    while ($row = $result->fetch_assoc()) {
        $vendedores[] = $row; // Agregar cada fila al array
    }
}

$dashVendorMap = dashComercialBuildVendorMap($conn);

// ============================================================================
// CÁLCULO DE Tasa de Conversión PRE-Q (basado en datos filtrados)
// Fórmula: (clientes / agendas totales) * 100
// ============================================================================

// Calcular Tasa de Conversión POST-Q:
// - Numerador: clientes cerrados (cliente = 1)
// - Denominador: agendados totales (estatus agendado, atendido, fantasma, muerto, cliente)
$totalRegistrosFiltered = count($displayLeads); // Total de registros (leads con cita)

// Contar TODOS los clientes cerrados (cliente = 1) con fecha_cambio_cliente dentro del rango
// Consultar directamente de contact_form para incluir cierres sin cita en calendario
$totalAtendidosFiltered = 0;
$_sqlCierres = "SELECT fecha_cambio_cliente, tabla_origen FROM contact_form WHERE cliente = 1 AND LOWER(COALESCE(tabla_origen, '')) NOT IN ('wedding_planners','wedding_planner')";
$_resCierres = $conn->query($_sqlCierres);
if ($_resCierres && $_resCierres->num_rows > 0) {
    while ($_rowC = $_resCierres->fetch_assoc()) {
        $fc = trim($_rowC['fecha_cambio_cliente'] ?? '');
        if (empty($fc)) continue;
        $fcts = strtotime($fc);
        if ($fcts === false) continue;
        $fd = date('Y-m-d', $fcts);
        if (!postLeadPassesDateFilters($fd, $startDate, $endDate, $postLeadsMinDate)) {
            continue;
        }
        $totalAtendidosFiltered++;
    }
} 

// Tasa de Conversión Post-Q (Clientes cerrados / Agendados × 100)
// El grupo "agendados" incluye los estatus:
// 'agendado', 'atendido', 'fantasma', 'muerto' y 'cliente' — además de sus códigos numéricos 0,1,2,3.
$totalAgendadasFiltered = 0;
foreach ($displayLeads as $lead) {
    $st = isset($lead['estatus']) ? mb_strtolower(trim((string)$lead['estatus']), 'UTF-8') : '';

    // Considerar textos reconocidos O códigos numéricos (0..3) O cliente cerrado
    if (
        $st === 'agendado' ||
        $st === 'atendido' ||
        $st === 'fantasma' ||
        $st === 'muerto' ||
        $st === 'cliente' ||
        (is_numeric($st) && in_array(intval($st), [0,1,2,3], true)) ||
        (isset($lead['cliente']) && intval($lead['cliente']) === 1)
    ) {
        $totalAgendadasFiltered++;
    }
}
$asistenciaAtendidos = $totalAtendidosFiltered;
$asistenciaAgendadas = $totalAgendadasFiltered;
// Calcular Tasa de Conversión POST-Q como porcentaje: Clientes cerrados / Agendados × 100
$asistenciaRate = ($asistenciaAgendadas > 0) ? round(((float)$asistenciaAtendidos / (float)$asistenciaAgendadas) * 100, 2) : 0.0;

// Tasa de booking: agendados (booked) / leads pre-q × 100 (igual que consulta_leads.php)
$bookingRate = ($totalPreLeadsFiltered > 0) ? round(((float)$totalAgendadasFiltered / (float)$totalPreLeadsFiltered) * 100, 2) : 0.0;

// ============================================================================
// CONTEO DE ENGAGEMENT (solo entre atendidos)
// ============================================================================
$engHighCount = 0;
$engMidCount  = 0;
$engLowCount  = 0;
foreach ($displayLeads as $lead) {
    if (!postLeadIsAtendidoForEngagement($lead)) {
        continue;
    }
    $engRaw = trim((string)($lead['engagement'] ?? ''));
    $engNorm = mb_strtolower($engRaw, 'UTF-8');
    if ($engNorm === '3' || $engNorm === 'alto')  $engHighCount++;
    elseif ($engNorm === '2' || $engNorm === 'medio') $engMidCount++;
    elseif ($engNorm === '1' || $engNorm === 'bajo')  $engLowCount++;
}

// Variables para la UI (mantener compatibilidad con nombres anteriores)
$totalRegistros = $totalRegistrosFiltered;
$totalAtendidos = $totalAtendidosFiltered;
// Compatibilidad con variables previas si hay código que las espera
$totalAgendas = $totalRegistrosFiltered;
$totalClientes = $totalAtendidosFiltered;

// ============================================================================
// DATOS PARA SECCIÓN DE REPORTES (2. Apartado de Reportes)
// Todos los displayLeads son agendados (tienen cita en calendario)
// ============================================================================

// Etiqueta del periodo para el chip de rango
$reportRangeLabel = 'Todos los registros';
if (($startDate !== '' || $endDate !== '') && empty($_GET['show_all'])) {
    $reportRangeStart = $startDate !== '' ? date('d/m/Y', strtotime($startDate)) : '...';
    $reportRangeEnd   = $endDate   !== '' ? date('d/m/Y', strtotime($endDate))   : '...';
    $reportRangeLabel = $reportRangeStart . ' → ' . $reportRangeEnd;
}

// Gráfica 1: serie por día (ya tenemos $datesJson / $countsJson de la lógica de Highcharts anterior)

// Gráfica 2: Distribución de contacto (Método de contacto)
$postContactMethodCounts = [];
foreach ($displayLeads as $lead) {
    $label = getContactChannelBadgeLabel(normalizeFirstContactChannelLabel(resolveFirstContactChannelForLead($lead)));
    $postContactMethodCounts[$label] = ($postContactMethodCounts[$label] ?? 0) + 1;
}
arsort($postContactMethodCounts);
$postContactMethodPieData = [];
foreach ($postContactMethodCounts as $label => $count) {
    if ($count <= 0) continue;
    $postContactMethodPieData[] = ['name' => $label, 'y' => $count];
}
$postContactMethodPieJson = json_encode($postContactMethodPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Gráfica 3: Desde cuándo nos conoce (how_long_known_us)
$postKnownUsCounts = [];
$postKnownUsLabelMap = [
    'less than 3 months'          => 'Menos de 3 meses',
    'between 3 months and 1 year' => 'Entre 3 meses y 1 año',
    'more than 1 year'            => 'Más de 1 año',
    'not asked'                   => 'No se preguntó',
];
foreach ($displayLeads as $lead) {
    $raw = trim((string)($lead['how_long_known_us'] ?? ''));
    if ($raw === '' || $raw === '—') {
        $label = 'Sin dato';
    } else {
        $label = $postKnownUsLabelMap[mb_strtolower($raw, 'UTF-8')] ?? $raw;
    }
    $postKnownUsCounts[$label] = ($postKnownUsCounts[$label] ?? 0) + 1;
}
arsort($postKnownUsCounts);

// Gráfica 5: Campañas (campaign_name)
$postCampaignCounts = [];
foreach ($displayLeads as $lead) {
    $_refUrl = trim((string)($lead['referrer_url'] ?? ''));
    $_fcc    = trim((string)($lead['first_contact_channel'] ?? ''));
    $cn = formatCampaignDisplayLabel(trim((string)($lead['campaign_name'] ?? '')), $_refUrl, $_fcc);
    if ($cn === '' || $cn === '—') $cn = 'Sin dato';
    $postCampaignCounts[$cn] = ($postCampaignCounts[$cn] ?? 0) + 1;
}
arsort($postCampaignCounts);

$postKnownUsPieData = [];
foreach ($postKnownUsCounts as $label => $count) {
    if ($count <= 0) continue;
    $postKnownUsPieData[] = ['name' => $label, 'y' => $count];
}
$postKnownUsPieJson = json_encode($postKnownUsPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Gráfica 4: De dónde nos conocen (how_did_you_meet)
$postWhereKnowCounts = ['Wedding Planner' => 0, 'Community' => 0, 'New Audience' => 0, 'Sin dato' => 0];
foreach ($displayLeads as $lead) {
    $howRaw = trim((string)($lead['how_did_you_meet'] ?? ''));
    if ($howRaw === '1') $postWhereKnowCounts['Wedding Planner']++;
    elseif ($howRaw === '2') $postWhereKnowCounts['Community']++;
    elseif ($howRaw === '3') $postWhereKnowCounts['New Audience']++;
    else $postWhereKnowCounts['Sin dato']++;
}
$postWhereKnowColorMap = [
    'Wedding Planner' => '#3B82F6',
    'Community'       => '#10B981',
    'New Audience'    => '#A855F7',
    'Sin dato'        => '#94a3b8',
];
$postWhereKnowPieData = [];
foreach ($postWhereKnowCounts as $wlabel => $wcount) {
    if ($wcount <= 0) continue;
    $postWhereKnowPieData[] = ['name' => $wlabel, 'y' => $wcount, 'color' => ($postWhereKnowColorMap[$wlabel] ?? null)];
}
$postWhereKnowPieJson = json_encode($postWhereKnowPieData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$postLeadsConOrigenConocido = 0;
$postLeadsClosedCount = 0;
$postLeadsOriginPending = 0;
$postLeadsEngagementHigh = 0;

foreach ($displayLeads as $lead) {
    $originRaw = trim((string) ($lead['how_did_you_meet'] ?? ''));
    $statusRaw = mb_strtolower(trim((string) ($lead['estatus'] ?? '')), 'UTF-8');
    $engagementRaw = mb_strtolower(trim((string) ($lead['engagement'] ?? '')), 'UTF-8');

    if (in_array($originRaw, ['1', '2', '3'], true)) {
        $postLeadsConOrigenConocido++;
    } else {
        $postLeadsOriginPending++;
    }

    if ($statusRaw === 'cliente' || intval($lead['cliente'] ?? 0) === 1) {
        $postLeadsClosedCount++;
    }

    if ($engagementRaw === '3' || $engagementRaw === 'alto') {
        $postLeadsEngagementHigh++;
    }
}

$postEventWpIds = [];
foreach ($displayLeads as $lead) {
    if (strcasecmp((string) ($lead['tabla_origen'] ?? ''), 'eventos_wp') !== 0) {
        continue;
    }

    $eventId = intval($lead['original_lead_id'] ?? 0);
    if ($eventId > 0) {
        $postEventWpIds[$eventId] = $eventId;
    }
}

$postEventWpAfianzadosMap = [];
if (!empty($postEventWpIds)) {
    $postEventIdsList = implode(',', array_map('intval', array_values($postEventWpIds)));
    $sqlPostEventWpAfianzados = "SELECT e.id, COALESCE(wp.afianzado, 0) AS afianzado FROM eventos_wp e LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id WHERE e.id IN ($postEventIdsList)";
    $resPostEventWpAfianzados = $conn->query($sqlPostEventWpAfianzados);
    if ($resPostEventWpAfianzados) {
        while ($rowPostEventWp = $resPostEventWpAfianzados->fetch_assoc()) {
            $postEventWpAfianzadosMap[intval($rowPostEventWp['id'] ?? 0)] = intval($rowPostEventWp['afianzado'] ?? 0) === 1 ? 1 : 0;
        }
    }
}

// Conteo total de todos los eventos de wedding planners afianzados (sin filtro de fechas)
$resPostWpAfianzadosTotal = $conn->query("SELECT COUNT(e.id) AS total FROM eventos_wp e INNER JOIN wedding_planners wp ON wp.id = e.wedding_planner_id WHERE wp.afianzado = 1");
$postWpAfianzadosCount = 0;
if ($resPostWpAfianzadosTotal) {
    $rowPostWpAfianzadosTotal = $resPostWpAfianzadosTotal->fetch_assoc();
    $postWpAfianzadosCount = intval($rowPostWpAfianzadosTotal['total'] ?? 0);
}

$postWpAtendidosCount = 0;
foreach ($displayLeads as $lead) {
    if (isPostLeadEventosWpContactForm($lead)) {
        $postWpAtendidosCount++;
    }
}

// Cargar mapa de paquetes (id => nombre) para mostrar en la tabla
$paquetesMap = [];
$resPaq = $conn->query("SELECT id, nombre FROM paquetes ORDER BY id");
if ($resPaq && $resPaq->num_rows > 0) {
    while ($rowPaq = $resPaq->fetch_assoc()) {
        $paquetesMap[intval($rowPaq['id'])] = $rowPaq['nombre'];
    }
}


?>


<div class="mlb-leads-tab-content mlb-leads-tab-atendidos">
    <style>
        /* ══════════════════════════════════════════════
           EFEGE Post-Qualified Leads  —  Light Mode
           Design language: reports_dashboard_EFEGE.html
        ══════════════════════════════════════════════ */
            :root {
                --gold: #C5A028;
                --gold-dim: rgba(197,160,40,0.12);
                --dark: #F8FAFC;
                --panel: #FFFFFF;
                --surface: #F1F5F9;
                --ink: #1E293B;
                --muted: #64748B;
                --border: #E2E8F0;
                --planner: #3B82F6;
                --planner-bg: rgba(59,130,246,0.12);
                --community: #10B981;
                --community-bg: rgba(16,185,129,0.12);
                --newmarket: #A855F7;
                --newmarket-bg: rgba(168,85,247,0.12);
                --eng-low: #F59E0B;
                --eng-mid: #3B82F6;
                --eng-high: #10B981;
                --danger: #DC2626;
                --danger-bg: rgba(220,38,38,0.12);
                --radius: 12px;
            }

            * { box-sizing: border-box; }
            body { background: var(--dark) !important; }

            .efege-page {
                padding: 0 20px 60px;
                font-family: 'DM Sans', system-ui, sans-serif;
                color: var(--ink);
            }

            .efege-page-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                padding: 20px 0 16px;
                margin-bottom: 20px;
                border-bottom: 1px solid var(--border);
            }

            .efege-page-header-left { flex: 1; min-width: 0; }
            .efege-page-header-right { display: flex; align-items: center; gap: 10px; }

            .efege-page-title {
                font-family: 'Cormorant Garamond', Georgia, serif;
                font-size: 26px;
                font-weight: 600;
                letter-spacing: 2px;
                color: var(--ink);
                line-height: 1;
                margin-bottom: 4px;
            }

            .efege-page-title-sub {
                font-size: 14px;
                letter-spacing: 1px;
                font-weight: 400;
                color: var(--muted);
                font-family: 'DM Sans', system-ui, sans-serif;
            }

            .efege-page-subtitle { font-size: 12px; color: var(--muted); margin-top: 4px; }

            .efege-view-note {
                margin-bottom: 16px;
                padding: 14px 16px 14px 20px;
                background: #f0f9ff;
                border: 1px solid #bae6fd;
                border-left: 4px solid #2563eb;
                border-radius: 10px;
                font-size: 0.86rem;
                color: #0c4a6e;
                line-height: 1.5;
            }

            .efege-view-note p { margin: 0; }

            .efege-date-range,
            .efege-live-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 6px 12px;
                font-size: 12px;
                color: var(--muted);
            }

            .efege-date-range span { color: var(--ink); font-weight: 600; }

            .kpi-strip {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 12px;
                margin-bottom: 24px;
            }

            .kpi-card {
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                padding: 18px 20px;
            }

            .kpi-label {
                font-size: 10px;
                letter-spacing: 1.5px;
                text-transform: uppercase;
                color: var(--muted);
                margin-bottom: 8px;
            }

            .kpi-value {
                font-size: 28px;
                font-weight: 700;
                color: var(--ink);
                line-height: 1;
                margin-bottom: 6px;
            }

            .kpi-sub {
                font-size: 11px;
                color: var(--muted);
            }

            .filter-bar {
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                padding: 14px 20px;
                margin-bottom: 16px;
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                position: sticky;
                top: 20px;
                z-index: 100;
            }

            .filter-label {
                font-size: 11px;
                letter-spacing: 1px;
                text-transform: uppercase;
                color: var(--muted);
                margin-right: 4px;
                flex-shrink: 0;
            }

            .efege-filter-input,
            .efege-filter-search {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 7px;
                color: var(--ink);
                font-family: 'DM Sans', system-ui, sans-serif;
                font-size: 12px;
                padding: 7px 12px;
                outline: none;
                transition: border-color 0.2s;
            }

            .efege-filter-search { width: 220px; }
            .efege-filter-input:focus,
            .efege-filter-search:focus,
            .filter-search:focus,
            .lead-update:focus { border-color: var(--gold); }

            .efege-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 7px 14px;
                border-radius: 7px;
                font-size: 12px;
                font-weight: 600;
                border: 1px solid var(--border);
                background: var(--surface);
                color: var(--ink);
                cursor: pointer;
                transition: all 0.2s;
                text-decoration: none;
                font-family: 'DM Sans', system-ui, sans-serif;
                white-space: nowrap;
            }

            .efege-btn:hover { border-color: var(--gold); color: var(--gold); text-decoration: none; }
            .efege-btn-primary { background: #0f172a; border-color: #0f172a; color: #fff; }
            .efege-btn-primary:hover { background: #1e293b; border-color: #1e293b; color: #fff; }
            .efege-btn-success { background: #059669; border-color: #059669; color: #fff; }
            .efege-btn-success:hover { background: #047857; border-color: #047857; color: #fff; }

            .reports-section {
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                padding: 20px;
                margin-bottom: 16px;
            }

            .reports-section-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
                margin-bottom: 16px;
            }

            .reports-section-step {
                font-size: 11px;
                letter-spacing: 1.4px;
                text-transform: uppercase;
                color: var(--gold);
                font-weight: 700;
                margin-bottom: 6px;
            }

            .reports-section-title {
                font-size: 18px;
                font-weight: 700;
                color: var(--ink);
                margin: 0 0 4px;
            }

            .reports-section-subtitle {
                font-size: 12px;
                color: var(--muted);
                margin: 0;
            }

            .reports-range-chip {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 12px;
                border-radius: 999px;
                background: var(--surface);
                border: 1px solid var(--border);
                color: var(--muted);
                font-size: 12px;
            }

            .report-overview-grid,
            .report-breakdown-stack {
                display: grid;
                gap: 16px;
            }

            .report-chart-card,
            .report-card,
            .report-breakdown-card,
            .report-insight {
                background: var(--dark);
                border: 1px solid var(--border);
                border-radius: 14px;
                padding: 18px;
            }

            .report-chart-card-full { width: 100%; }

            .report-chart-header,
            .report-breakdown-row {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
            }

            .report-chart-title {
                font-size: 15px;
                font-weight: 700;
                color: var(--ink);
                margin: 0 0 4px;
            }

            .report-chart-subtitle,
            .report-card-note,
            .report-insight-detail {
                font-size: 12px;
                color: var(--muted);
                margin: 0;
            }

            .report-scope-hint {
                font-size: 11px;
                font-weight: 400;
                color: var(--muted);
                margin-left: 6px;
            }

            .report-chart { width: 100%; min-height: 240px; }

            .report-metric-grid,
            .report-breakdown-grid {
                display: grid;
                gap: 12px;
            }

            .report-metric-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
            .report-breakdown-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }

            .report-card-highlight,
            .report-insight {
                background: linear-gradient(135deg, rgba(197,160,40,0.10), rgba(59,130,246,0.05));
                border-color: rgba(197,160,40,0.22);
            }

            .report-card-label,
            .report-insight-kicker {
                font-size: 11px;
                letter-spacing: 1.2px;
                text-transform: uppercase;
                color: var(--muted);
                margin-bottom: 10px;
            }

            .report-card-value {
                font-size: 30px;
                line-height: 1;
                font-weight: 700;
                color: var(--ink);
                margin-bottom: 8px;
            }

            .report-breakdown-list { display: grid; gap: 10px; margin-top: 14px; }
            .report-breakdown-item { display: grid; gap: 8px; }
            .report-breakdown-label { font-size: 13px; font-weight: 600; color: var(--ink); min-width: 0; }
            .report-breakdown-meta { flex-shrink: 0; font-size: 12px; color: var(--muted); white-space: nowrap; }
            .report-breakdown-bar { width: 100%; height: 8px; border-radius: 999px; background: var(--surface); overflow: hidden; }
            .report-breakdown-fill { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, rgba(37,99,235,0.85), rgba(197,160,40,0.7)); }
            .report-breakdown-empty { margin: 0; font-size: 12px; color: var(--muted); }

            .report-origin-pending {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 16px;
                border-radius: 12px;
                background: #fdf0dc;
                border: 1px solid rgba(138,92,30,.12);
                color: #8a5c1e;
                font-size: 12px;
            }

            .report-origin-pending strong { font-weight: 700; }
            .report-insight-text { margin: 0; font-size: 18px; line-height: 1.4; color: var(--ink); font-weight: 600; }
            .report-insight-text span { color: var(--gold); }

            .table-wrap {
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                overflow: hidden;
            }

            .table-header-custom {
                padding: 14px 20px;
                border-bottom: 1px solid var(--border);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }

            .table-title { font-size: 13px; font-weight: 600; color: var(--ink); }
            .table-count { font-size: 11px; color: var(--muted); }

            .efege-table-scroll {
                overflow-x: auto;
                overflow-y: auto;
                max-height: 820px;
                width: 100%;
                scrollbar-width: thin;
                scrollbar-color: var(--border) transparent;
            }

            .efege-table-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
            .efege-table-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

            .efege-table {
                width: 100% !important;
                border-collapse: separate;
                border-spacing: 0;
                font-size: 13px;
                min-width: 1220px;
            }

            .efege-table thead th {
                background: var(--surface) !important;
                color: var(--muted) !important;
                font-size: 10px !important;
                letter-spacing: 1.5px;
                text-transform: uppercase;
                border-bottom: 1px solid var(--border) !important;
                padding: 10px 16px !important;
                white-space: nowrap;
                font-weight: 600 !important;
                position: sticky;
                top: 0;
                z-index: 2;
            }

            .efege-table tbody tr { border-bottom: 1px solid var(--border); }
            .efege-table tbody tr:hover { background: rgba(248,250,252,0.75) !important; }
            .efege-table tbody td { padding: 12px 16px !important; border-bottom: 1px solid var(--border) !important; color: var(--ink); vertical-align: middle !important; }

            .td-name { font-weight: 600; color: var(--ink); font-size: 13px; }
            .td-sub, .td-inline-muted { font-size: 11px; color: var(--muted); }
            .td-emphasis { font-weight: 600; color: var(--ink); }

            .badge,
            .ch-badge,
            .known,
            .eng,
            .status {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 10px;
                font-weight: 600;
                white-space: nowrap;
            }

            .badge-planner { background: var(--planner-bg); color: var(--planner); }
            .badge-community { background: var(--community-bg); color: var(--community); }
            .badge-newmarket { background: var(--newmarket-bg); color: var(--newmarket); }
            .badge-neutral, .ch-badge { background: var(--surface); color: var(--muted); border: 1px solid var(--border); }
            .badge-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
            .known { background: rgba(197,160,40,0.1); color: var(--gold); }
            .badge-campaign,
            .badge-contact-method,
            .badge-tipo-cliente {
                display: inline-flex;
                align-items: center;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 10px;
                font-weight: 700;
                letter-spacing: .05em;
                text-transform: uppercase;
                white-space: nowrap;
            }
            .eng-low { background: rgba(245,158,11,0.12); color: var(--eng-low); }
            .eng-mid { background: rgba(59,130,246,0.12); color: var(--eng-mid); }
            .eng-high { background: rgba(16,185,129,0.12); color: var(--eng-high); }
            .status-scheduled { background: rgba(59,130,246,0.12); color: var(--planner); }
            .status-attended { background: rgba(16,185,129,0.12); color: var(--community); }
            .status-pending { background: rgba(245,158,11,0.12); color: var(--eng-low); }
            .status-closed { background: rgba(197,160,40,0.15); color: var(--gold); }
            .status-danger { background: var(--danger-bg); color: var(--danger); }

            td[data-column="estatus"] .td-sub-fecha-atendido {
                margin-top: 4px;
                font-size: 10px;
                line-height: 1.2;
                white-space: normal;
                max-width: 140px;
                margin-left: auto;
                margin-right: auto;
            }

            .mlb-atendido-date-cell {
                cursor: pointer;
                color: var(--planner);
                font-weight: 600;
                text-decoration: underline dotted;
                text-underline-offset: 3px;
                transition: color 0.15s ease;
            }

            .mlb-atendido-date-cell:hover {
                color: #1d4ed8;
            }

            .mlb-atendido-date-cell.is-empty {
                color: var(--muted);
                font-weight: 500;
            }

            .mlb-atendido-date-hint {
                display: block;
                margin-top: 3px;
                font-size: 10px;
                font-weight: 500;
                color: var(--muted);
            }

            .action-stack {
                min-width: 120px;
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            .lead-update,
            .form-select {
                min-width: 150px;
                max-width: 190px;
                border: 1px solid var(--border);
                border-radius: 7px;
                padding: 6px 10px;
                background: var(--surface);
                color: var(--ink);
                font-size: 12px;
                font-family: 'DM Sans', sans-serif;
                outline: none;
            }

            .filter-icon {
                cursor: pointer;
                margin-left: 5px;
                font-size: 0.75rem;
                color: var(--muted);
                transition: color 0.2s;
            }

            .filter-icon:hover,
            .filter-icon.active { color: var(--planner); }

            .th-flex-label {
                display: flex;
                align-items: flex-start;
                gap: 3px;
            }

            .th-flex-label .th-compact-label { flex: 1; }
            .th-compact-label {
                display: block;
                max-width: 96px;
                white-space: normal !important;
                word-break: break-word;
                overflow-wrap: anywhere;
                line-height: 1.15;
            }

            .filter-dropdown {
                position: fixed;
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                box-shadow: 0 8px 28px rgba(0,0,0,0.12);
                z-index: 1050;
                min-width: 250px;
                max-width: 400px;
                display: none;
            }

            .filter-dropdown.show { display: block; }
            .filter-dropdown-header,
            .filter-dropdown-footer {
                padding: 12px 16px;
                background: var(--surface);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .filter-dropdown-header { border-bottom: 1px solid var(--border); border-radius: var(--radius) var(--radius) 0 0; font-size: 12px; font-weight: 600; }
            .filter-dropdown-footer { border-top: 1px solid var(--border); border-radius: 0 0 var(--radius) var(--radius); gap: 8px; }
            .filter-dropdown-body { padding: 12px 16px; max-height: 300px; overflow-y: auto; }
            .filter-search { width: 100%; padding: 6px 12px; margin-bottom: 10px; border: 1px solid var(--border); border-radius: 7px; font-size: 12px; background: var(--surface); color: var(--ink); outline: none; }
            .filter-option { display: flex; align-items: center; padding: 5px 4px; cursor: pointer; user-select: none; border-radius: 5px; }
            .filter-option:hover { background: var(--surface); }
            .filter-option input[type="checkbox"] { margin-right: 8px; }
            .filter-option label { cursor: pointer; margin: 0; flex: 1; font-size: 12px; color: var(--ink); }

            .filter-btn-sm {
                padding: 5px 14px;
                font-size: 11px;
                font-weight: 600;
                border-radius: 6px;
                cursor: pointer;
                border: 1px solid var(--border);
                transition: all 0.2s;
            }

            .filter-btn-sm-secondary { background: var(--surface); color: var(--muted); }
            .filter-btn-sm-primary { background: var(--ink); color: #fff; border-color: var(--ink); }

            .option1 { background-color: #059669; color: #fff; }
            .option2 { background-color: #dc2626; color: #fff; }
            .option3 { background-color: #D4EDBC; color: #11734B; }
            .option4 { background-color: #ffe5a0; color: #8c7850; }
            .option5 { background-color: #ffcfc9; color: #dc2626; }
            .option6 { background-color: #e4cff1; color: #795d8d; }
            .option7 { background-color: #b9e3fa; color: #417295; }
            .option8 { background-color: #e9eaee; color: #555; }

            #verMasModal p { font-size: 1.1rem; }

            @media (max-width: 1300px) {
                .report-breakdown-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }

            @media (max-width: 1100px) {
                .kpi-strip,
                .report-metric-grid,
                .report-breakdown-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }

            @media (max-width: 780px) {
                .efege-page { padding: 0 12px 40px; }
                .kpi-strip,
                .report-metric-grid,
                .report-breakdown-grid { grid-template-columns: 1fr; }
                .table-header-custom { align-items: flex-start; }
                .efege-filter-search { width: 100%; }
            }

            .post-actions-cell { display: flex; flex-wrap: wrap; gap: 6px; }
    </style>
        <div class="filter-bar">
            <span class="filter-label">Filtros</span>
            <form method="get" id="filterForm" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <input type="hidden" name="tab" value="atendidos">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="date" name="start_date" class="efege-filter-input"
                    min="<?php echo htmlspecialchars($postLeadsMinDate, ENT_QUOTES, 'UTF-8'); ?>"
                    value="<?php echo htmlspecialchars($startDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="date" name="end_date" class="efege-filter-input"
                    min="<?php echo htmlspecialchars($postLeadsMinDate, ENT_QUOTES, 'UTF-8'); ?>"
                    value="<?php echo htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <!--<select name="filter_plataforma" id="filterPlataforma" class="filter-select" style="min-width:180px;">
                    <option value="">Todas las plataformas</option>
                    <option value="campania" <?php echo ($filterPlataforma === 'campania') ? 'selected' : ''; ?>>Campañas digitales</option>
                    <option value="organico"  <?php echo ($filterPlataforma === 'organico')  ? 'selected' : ''; ?>>Orgánico</option>
                </select>-->
                <button type="submit" class="efege-btn efege-btn-primary">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <a href="my_lead_board_leads.php?tab=atendidos&show_all=1" class="efege-btn">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            </form>
        </div>
        <div class="table-wrap">
            <div class="table-header-custom">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="table-title">Post-Qualified Leads</span>
                    <span class="table-count" id="table-count-label"><?php echo number_format($leadsCountFiltered); ?> sesiones · <?php echo number_format($postLeadsClosedCount); ?> cierres</span>
                    <button id="exportExcelBtn" class="efege-btn efege-btn-success" type="button">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </button>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="position:relative;">
                        <i style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:11px;" class="fas fa-search"></i>
                        <input type="text" id="tableSearchInput" class="efege-filter-search" placeholder="Buscar WP, correo…" style="padding-left:26px;" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
            </div>
            <div class="efege-table-scroll">
                <table id="leadsTable" class="efege-table table table-hover">
                    <thead>
                        <tr>
                            <th data-column="nombre">Nombre</th>
                            <th data-column="metodo_contacto"><div class="th-flex-label"><span class="th-compact-label">Método contacto</span><i class="filter-icon bi bi-funnel" data-column="metodo_contacto"></i></div></th>
                            <th data-column="tipo_cliente"><div class="th-flex-label"><span class="th-compact-label">Tipo de cliente</span><i class="filter-icon bi bi-funnel" data-column="tipo_cliente"></i></div></th>
                            <th data-column="origen_cliente"><div class="th-flex-label"><span class="th-compact-label">Origen</span><i class="filter-icon bi bi-funnel" data-column="origen_cliente"></i></div></th>
                            <th data-column="campana"><div class="th-flex-label"><span class="th-compact-label">Campaña</span><i class="filter-icon bi bi-funnel" data-column="campana"></i></div></th>
                            <th data-column="estatus">Estatus <i class="filter-icon bi bi-funnel" data-column="estatus"></i></th>
                            <th data-column="vendedor"><div class="th-flex-label"><span class="th-compact-label">Vendedor/a</span><i class="filter-icon bi bi-funnel" data-column="vendedor"></i></div></th>
                            <th data-column="fecha">¿Cuándo atendió?</th>
                            <th style="display:none">Sesión Oficial</th>
                            <th style="display:none">Compromiso</th>
                            <th style="display:none">Técnica Cierre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayLeads as $lead): ?>
                            <?php
                                $originCategoryLabel = getOrigenCategoriaLabel($lead);
                                $originBadgeClass = 'badge badge-neutral';
                                if ($originCategoryLabel === 'Wedding Planner') {
                                    $originBadgeClass = 'badge badge-planner';
                                } elseif ($originCategoryLabel === 'Community') {
                                    $originBadgeClass = 'badge badge-community';
                                } elseif ($originCategoryLabel === 'New Audience') {
                                    $originBadgeClass = 'badge badge-newmarket';
                                }

                                $engRaw = trim((string)($lead['engagement'] ?? ($lead['sfm_engagement'] ?? '')));
                                $engNorm = mb_strtolower($engRaw, 'UTF-8');
                                $engClass = 'eng eng-low';
                                if ($engNorm === '3' || $engNorm === 'alto') {
                                    $engClass = 'eng eng-high';
                                } elseif ($engNorm === '2' || $engNorm === 'medio') {
                                    $engClass = 'eng eng-mid';
                                }

                                $statusRaw = isset($lead['estatus']) ? trim((string)$lead['estatus']) : '';
                                $statusLower = $statusRaw !== '' ? mb_strtolower($statusRaw, 'UTF-8') : 'agendado';
                                $statusDisplay = ucfirst($statusLower);
                                $statusClass = 'status status-scheduled';
                                if ($statusLower === 'atendido') {
                                    $statusClass = 'status status-attended';
                                } elseif ($statusLower === 'cliente') {
                                    $statusClass = 'status status-closed';
                                } elseif ($statusLower === 'fantasma') {
                                    $statusClass = 'status status-pending';
                                } elseif ($statusLower === 'muerto') {
                                    $statusClass = 'status status-danger';
                                }

                                $isOriginKnown = in_array(trim((string)($lead['how_did_you_meet'] ?? '')), ['1', '2', '3'], true) ? 1 : 0;
                                // Todos los displayLeads ya pasaron filtro de calendario_estatus_historial (estatus 1)
                                $isOfficialCall = 1;
                                $isClosed = ($statusLower === 'cliente' || intval($lead['cliente'] ?? 0) === 1) ? 1 : 0;
                                $isWpAfianzado = (
                                    strcasecmp((string)($lead['tabla_origen'] ?? ''), 'eventos_wp') === 0 &&
                                    intval($postEventWpAfianzadosMap[intval($lead['original_lead_id'] ?? 0)] ?? 0) === 1
                                ) ? 1 : 0;
                                $isWpAtendido = isPostLeadEventosWpContactForm($lead) ? 1 : 0;
                                $isEngagementHigh = ($engNorm === '3' || $engNorm === 'alto') ? 1 : 0;
                                if ($isClosed) {
                                    $contactMethodLabel = normalizeFirstContactChannelLabel($lead['first_contact_channel'] ?? '');
                                    $campaignDisplayLabel = formatCampaignDisplayLabel($lead['campaign_name'] ?? '');
                                } else {
                                    $resolvedContactChannel = resolveFirstContactChannelForLead($lead);
                                    $contactMethodLabel = normalizeFirstContactChannelLabel($resolvedContactChannel);
                                    $_refUrl = trim((string)($lead['referrer_url'] ?? ''));
                                    $_fcc = trim((string)($lead['first_contact_channel'] ?? ''));
                                    $campaignDisplayLabel = formatCampaignDisplayLabel($lead['campaign_name'] ?? '', $_refUrl, $_fcc);
                                }
                                $contactChannelBadgeLabel = getContactChannelBadgeLabel($contactMethodLabel);
                                $knownUsRaw = trim((string)($lead['how_long_known_us'] ?? ''));
                                $knuMap = [
                                    'less than 3 months' => 'Menos de 3 meses',
                                    'between 3 months and 1 year' => 'Entre 3 meses y 1 año',
                                    'more than 1 year' => 'Más de 1 año',
                                    'not asked' => 'No se preguntó',
                                ];
                                $knownUsDisplay = ($knownUsRaw !== '' && $knownUsRaw !== '—') ? ($knuMap[mb_strtolower($knownUsRaw, 'UTF-8')] ?? $knownUsRaw) : 'Sin dato';
                                $knownUsBadgeLabel = getKnownUsBadgeLabel($knownUsDisplay);
                                $tipoClienteLabel = getTipoClienteLabel($lead, [
                                    'tipo_cliente' => $lead['tipo_cliente'] ?? null,
                                    'how_did_you_meet' => $lead['how_did_you_meet_raw'] ?? ($lead['how_did_you_meet'] ?? ''),
                                ]);
                                $apptForVendor = postLeadResolveAppointmentForLead($lead, $appointmentsByClient, $appointmentsByEventId);
                                $vendedorLabel = dashComercialResolveLeadVendorLabel($conn, $lead, $apptForVendor, $dashVendorMap, $appointmentsByEventId);
                                $atendidoRaw = plannerProfileResolveClienteFinalAttendedDate($lead, $postFechaAtencionByContactFormId);
                                if ($atendidoRaw === '' && isPostLeadEventosWpContactForm($lead)) {
                                    $atendidoRaw = plannerProfileResolveEventAttendedDate($lead);
                                }
                                $atendidoDisplay = formatCreatedTime($atendidoRaw);
                                if ($atendidoDisplay === '') {
                                    $atendidoDisplay = formatArrivalDateTime($atendidoRaw);
                                }
                                if ($atendidoDisplay === '') {
                                    $atendidoDisplay = '—';
                                }
                            ?>
                            <tr id="lead-row-<?php echo $lead['id']; ?>-<?php echo htmlspecialchars($lead['tabla_origen']); ?>"
                                data-search-haystack="<?php echo htmlspecialchars(wpEventosCfBuildSearchHaystack($lead), ENT_QUOTES, 'UTF-8'); ?>"
                                data-origin-known="<?php echo $isOriginKnown; ?>"
                                data-llamada-oficial="<?php echo $isOfficialCall; ?>"
                                data-is-closed="<?php echo $isClosed; ?>"
                                data-wp-afianzado="<?php echo $isWpAfianzado; ?>"
                                data-wp-atendido="<?php echo $isWpAtendido; ?>"
                                data-engagement-high="<?php echo $isEngagementHigh; ?>"
                                data-status="<?php echo htmlspecialchars($statusLower, ENT_QUOTES, 'UTF-8'); ?>">

                                <!-- Nombre + ID -->
                                <td data-column="nombre">
                                    <?php $nameDisplay = wpEventosCfResolveLeadNameDisplay($lead); ?>
                                    <div class="td-name"><?php echo htmlspecialchars($nameDisplay['primary'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php if (!empty($nameDisplay['secondary'])): ?>
                                        <div class="td-sub"><?php echo htmlspecialchars($nameDisplay['secondary'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($lead['email'])): ?>
                                        <div class="td-sub"><?php echo htmlspecialchars($lead['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                </td>

                                <!-- Método de contacto -->
                                <td data-column="metodo_contacto"><?php echo renderContactMethodBadge($contactMethodLabel, $contactChannelBadgeLabel); ?></td>

                                <!-- Tipo de cliente -->
                                <td data-column="tipo_cliente"><?php echo renderTipoClienteBadge($tipoClienteLabel); ?></td>

                                <!-- Origen -->
                                <td data-column="origen_cliente">
                                    <span class="<?php echo htmlspecialchars($originBadgeClass, ENT_QUOTES, 'UTF-8'); ?>"><span class="badge-dot"></span><?php echo htmlspecialchars($originCategoryLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>

                                <!-- Campaña -->
                                <td data-column="campana"><?php echo renderCampaignBadge($campaignDisplayLabel); ?></td>

                                <!-- Estatus (pill) -->
                                <td class="text-center align-middle" data-column="estatus">
                                    <span class="<?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <div class="td-sub td-sub-fecha-atendido"><?php echo htmlspecialchars($atendidoDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>

                                <!-- Vendedor/a -->
                                <td data-column="vendedor"><?php echo htmlspecialchars($vendedorLabel, ENT_QUOTES, 'UTF-8'); ?></td>

                                <!-- ¿Cuándo atendió? / cierre / evento WP -->
                                <?php
                                $cohortRaw = postLeadResolvePostCohortDateForLead(
                                    $lead,
                                    $postFechaAtencionByContactFormId,
                                    $appointmentsByClient,
                                    $appointmentsByEventId,
                                    $postFechaMuertoHistorialByContactFormId
                                );
                                $cohortTs = 0;
                                $cohortDateInput = '';
                                $cohortTimeInput = '';
                                if (!empty($cohortRaw)) {
                                    $cohortTs = strtotime($cohortRaw) ?: 0;
                                    if ($cohortTs > 0) {
                                        $cohortDateInput = date('Y-m-d', $cohortTs);
                                        $cohortTimeInput = date('H:i', $cohortTs);
                                    }
                                }
                                $isWpEventLead = function_exists('isPostLeadEventosWpContactForm') && isPostLeadEventosWpContactForm($lead);
                                $atendidoEditKind = $isWpEventLead ? 'eventos_wp' : 'lead';
                                $atendidoContactFormId = (int) ($lead['id'] ?? 0);
                                $atendidoCalendarioId = (is_array($apptForVendor) ? (int) ($apptForVendor['id'] ?? 0) : 0);
                                $atendidoEventId = $isWpEventLead ? (int) ($lead['original_lead_id'] ?? 0) : 0;
                                $cohortDisplay = formatArrivalDateTime($cohortRaw);
                                if ($cohortDisplay === '') {
                                    $cohortDisplay = '—';
                                }
                                $leadNameForModal = wpEventosCfResolveLeadNameDisplay($lead);
                                $leadNamePrimary = is_array($leadNameForModal) ? ($leadNameForModal['primary'] ?? '') : '';
                                ?>
                                <td data-column="fecha"
                                    data-order="<?php echo intval($cohortTs); ?>"
                                    class="mlb-atendido-date-cell js-edit-atendido-date<?php echo $cohortDisplay === '—' ? ' is-empty' : ''; ?>"
                                    title="Clic para editar cuándo atendió"
                                    data-lead-kind="<?php echo htmlspecialchars($atendidoEditKind, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-contact-form-id="<?php echo $atendidoContactFormId; ?>"
                                    data-calendario-id="<?php echo $atendidoCalendarioId; ?>"
                                    data-event-id="<?php echo $atendidoEventId; ?>"
                                    data-fecha="<?php echo htmlspecialchars($cohortDateInput, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-hora="<?php echo htmlspecialchars($cohortTimeInput, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-lead-name="<?php echo htmlspecialchars($leadNamePrimary, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($cohortDisplay, ENT_QUOTES, 'UTF-8'); ?>
                                    <span class="mlb-atendido-date-hint">Editar</span>
                                </td>

                                <!-- Sesión oficial (hidden) -->
                                <td style="display:none">
                                    <select class="form-select-sm form-select lead-update"
                                        data-id="<?php echo intval($lead['id']); ?>" data-field="sesion_oficial"
                                        data-table="<?php echo htmlspecialchars($lead['tabla_origen']); ?>">
                                        <option value="">--Seleccionar--</option>
                                        <option value="Si" class="option1" <?php echo (isset($lead['sesion_oficial']) && $lead['sesion_oficial'] === 'Si') ? 'selected' : ''; ?>>Sí</option>
                                        <option value="aun no (no llegaron)" class="option2" <?php echo (isset($lead['sesion_oficial']) && $lead['sesion_oficial'] === 'aun no (no llegaron)') ? 'selected' : ''; ?>>Aún no (no llegaron)</option>
                                        <option value="aun no pero ya agendaron" class="option3" <?php echo (isset($lead['sesion_oficial']) && $lead['sesion_oficial'] === 'aun no pero ya agendaron') ? 'selected' : ''; ?>>Aún no pero ya agendaron</option>
                                        <option value="no, es relacion directa con planner" class="option4" <?php echo (isset($lead['sesion_oficial']) && $lead['sesion_oficial'] === 'no, es relacion directa con planner') ? 'selected' : ''; ?>>No, relación directa con planner</option>
                                    </select>
                                </td>

                                <!-- Compromiso cliente (hidden) -->
                                <td style="display:none">
                                    <select class="form-select-sm form-select lead-update"
                                        data-id="<?php echo intval($lead['id']); ?>" data-field="compromiso_cliente"
                                        data-table="<?php echo htmlspecialchars($lead['tabla_origen']); ?>">
                                        <option value="">--Seleccionar--</option>
                                        <option value="alto"     class="option3" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'alto')     ? 'selected' : ''; ?>>Alto</option>
                                        <option value="normal"   class="option4" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'normal')   ? 'selected' : ''; ?>>Normal</option>
                                        <option value="bajo"     class="option5" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'bajo')     ? 'selected' : ''; ?>>Bajo</option>
                                        <option value="muerto"   class="option2" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'muerto')   ? 'selected' : ''; ?>>Muerto</option>
                                        <option value="cerrado"  class="option1" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'cerrado')  ? 'selected' : ''; ?>>Cerrado</option>
                                        <option value="stand by" class="option7" <?php echo (isset($lead['compromiso_cliente']) && $lead['compromiso_cliente'] === 'stand by') ? 'selected' : ''; ?>>Stand by</option>
                                    </select>
                                </td>

                                <!-- Técnica cierre (hidden) -->
                                <td style="display:none">
                                    <select class="form-select-sm form-select lead-update"
                                        data-id="<?php echo intval($lead['id']); ?>" data-field="tecnica_cierre"
                                        data-table="<?php echo htmlspecialchars($lead['tabla_origen']); ?>">
                                        <option value="">--Seleccionar--</option>
                                        <option value="Aun no"            class="option2" <?php echo (isset($lead['tecnica_cierre']) && $lead['tecnica_cierre'] === 'Aun no')            ? 'selected' : ''; ?>>Aún no</option>
                                        <option value="Sí"                class="option1" <?php echo (isset($lead['tecnica_cierre']) && $lead['tecnica_cierre'] === 'Sí')                ? 'selected' : ''; ?>>Sí</option>
                                        <option value="aun no es momento" class="option8" <?php echo (isset($lead['tecnica_cierre']) && $lead['tecnica_cierre'] === 'aun no es momento') ? 'selected' : ''; ?>>Aún no es momento</option>
                                    </select>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div><!-- /.table-wrap -->

</div><!-- /.efege-page -->

<!-- ── Modal: editar fecha en que atendió ── -->
<div class="modal fade" id="editAtendidoDateModal" tabindex="-1" aria-labelledby="editAtendidoDateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAtendidoDateModalLabel">Editar fecha de atención</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" style="font-size:13px;color:var(--muted);">
                    <strong id="editAtendidoDateLeadName" style="color:var(--ink);"></strong>
                </p>
                <p class="mb-3" id="editAtendidoDateContext" style="font-size:12px;color:var(--muted);"></p>
                <form id="editAtendidoDateForm">
                    <input type="hidden" id="editAtendidoDateKind" value="lead">
                    <input type="hidden" id="editAtendidoDateContactFormId" value="0">
                    <input type="hidden" id="editAtendidoDateCalendarioId" value="0">
                    <input type="hidden" id="editAtendidoDateEventId" value="0">
                    <div class="mb-3">
                        <label for="editAtendidoDateFecha" class="form-label">Fecha en que atendió</label>
                        <input type="date" class="form-control" id="editAtendidoDateFecha" required>
                    </div>
                    <div class="mb-0">
                        <label for="editAtendidoDateHora" class="form-label">Hora</label>
                        <input type="time" class="form-control" id="editAtendidoDateHora">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSaveAtendidoDate">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: ver más detalles ── -->
<div class="modal fade" id="verMasModal" tabindex="-1" aria-labelledby="verMasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verMasModalLabel">Detalles del Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: comentarios ── -->
<div class="modal fade" id="comentariosModal" tabindex="-1" aria-labelledby="comentariosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="comentariosModalLabel">Comentarios del vendedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="comentariosModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script>
    const vendedores = <?php echo json_encode($vendedores); ?>;

    (function () {
        var editAtendidoDateModalEl = document.getElementById('editAtendidoDateModal');
        var editAtendidoDateModal = editAtendidoDateModalEl ? new bootstrap.Modal(editAtendidoDateModalEl) : null;
        var activeAtendidoDateCell = null;

        $(document).on('click', '.js-edit-atendido-date, .mlb-atendido-date-hint', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $cell = $(this).closest('td.js-edit-atendido-date');
            if (!$cell.length) {
                $cell = $(this).filter('.js-edit-atendido-date');
            }
            if (!$cell.length) {
                return;
            }
            activeAtendidoDateCell = $cell[0];
            var kind = String($cell.attr('data-lead-kind') || 'lead');
            $('#editAtendidoDateKind').val(kind);
            $('#editAtendidoDateContactFormId').val($cell.attr('data-contact-form-id') || '0');
            $('#editAtendidoDateCalendarioId').val($cell.attr('data-calendario-id') || '0');
            $('#editAtendidoDateEventId').val($cell.attr('data-event-id') || '0');
            $('#editAtendidoDateFecha').val($cell.attr('data-fecha') || '');
            $('#editAtendidoDateHora').val($cell.attr('data-hora') || '');
            $('#editAtendidoDateLeadName').text($cell.attr('data-lead-name') || 'Lead');
            if (kind === 'eventos_wp') {
                $('#editAtendidoDateContext').text('Evento de Wedding Planner: actualiza eventos_wp.fecha_atendido. No es el mismo campo que un lead comercial en calendario_estatus_historial.');
            } else {
                $('#editAtendidoDateContext').text('Lead comercial: actualiza la fecha de atención en calendario_estatus_historial (estatus atendido). No modifica eventos_wp.');
            }
            if (editAtendidoDateModal) {
                editAtendidoDateModal.show();
            }
        });

        $('#btnSaveAtendidoDate').on('click', function () {
            var fecha = $('#editAtendidoDateFecha').val();
            if (!fecha) {
                Swal.fire('Error', 'Indica la fecha.', 'error');
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.ajax({
                url: 'actualizar_fecha_atendido_lead.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    kind: $('#editAtendidoDateKind').val(),
                    contact_form_id: $('#editAtendidoDateContactFormId').val(),
                    calendario_id: $('#editAtendidoDateCalendarioId').val(),
                    event_id: $('#editAtendidoDateEventId').val(),
                    fecha: fecha,
                    hora: $('#editAtendidoDateHora').val()
                },
                success: function (resp) {
                    if (resp && resp.success) {
                        if (activeAtendidoDateCell && resp.display) {
                            var $cell = $(activeAtendidoDateCell);
                            $cell.empty();
                            $cell.append(document.createTextNode(resp.display));
                            $cell.append($('<span class="mlb-atendido-date-hint">Editar</span>'));
                            $cell.removeClass('is-empty');
                            if (resp.data_order) {
                                $cell.attr('data-order', resp.data_order);
                            }
                            $cell.attr('data-fecha', fecha);
                            $cell.attr('data-hora', $('#editAtendidoDateHora').val() || '');
                            if ($.fn.dataTable.isDataTable('#leadsTable')) {
                                $('#leadsTable').DataTable().row($cell.closest('tr')).invalidate();
                            }
                        }
                        if (editAtendidoDateModal) {
                            editAtendidoDateModal.hide();
                        }
                        Swal.fire('Guardado', resp.message || 'Fecha actualizada.', 'success');
                    } else {
                        Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo guardar.', 'error');
                    }
                },
                error: function (xhr) {
                    var msg = 'Error de comunicación.';
                    try {
                        var parsed = JSON.parse(xhr.responseText);
                        if (parsed.message) {
                            msg = parsed.message;
                        }
                    } catch (ignore) {}
                    Swal.fire('Error', msg, 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                }
            });
        });
    })();

    $(document).ready(function () {
        var dtHiddenCols = <?php echo json_encode($dtHiddenColumnTargets); ?>;

        $.fn.dataTable.ext.search.push(function (settings, _data, dataIndex) {
            if (settings.nTable.id !== 'leadsTable') {
                return true;
            }
            var term = ($('#tableSearchInput').val() || '').trim().toLowerCase();
            if (!term) {
                return true;
            }
            var api = new $.fn.dataTable.Api(settings);
            var row = api.row(dataIndex).node();
            if (!row) {
                return true;
            }
            var haystack = (row.getAttribute('data-search-haystack') || '').toLowerCase();
            var nameCell = row.querySelector('td[data-column="nombre"]');
            if (nameCell) {
                haystack += ' ' + nameCell.textContent.toLowerCase();
            }
            return haystack.indexOf(term) !== -1;
        });

        var table = $('#leadsTable').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
            ordering:  false,
            paging:    false,
            info: true,
            autoWidth: false,
            columnDefs: [
                { targets: 0, searchable: false },
                { targets: dtHiddenCols, visible: false, searchable: false }
            ],
            dom: '<""r>t<"d-flex justify-content-between align-items-center mt-2"il>',
            buttons: [{ extend: 'excelHtml5', title: 'Leads Post Qualified', exportOptions: { columns: ':visible:not(:last-child)' } }],
            drawCallback: function () { applyColumnFilters(); },
            initComplete: function () { updateDynamicCards(); }
        });

        $('#exportExcelBtn').on('click', function () {
            table.button(0).trigger();
        });

        $('#tableSearchInput').on('input', function () {
            var value = this.value || '';
            $('input[name="search"]', '#filterForm').val(value);
            table.draw();
        });

        updateDynamicCards();

        // ── REPORTES CHARTS (solo si el contenedor existe en esta vista) ──
        var postLeadsChartEl = document.getElementById('postLeadsChart');
        if (postLeadsChartEl && typeof Highcharts !== 'undefined') {
        var postChartDates  = <?php echo isset($datesJson)  ? $datesJson  : '[]'; ?>;
        var postChartCounts = <?php echo isset($countsJson) ? $countsJson : '[]'; ?>;
        var postMaxLeads = 0;
        postChartCounts.forEach(function(c) { if (c > postMaxLeads) postMaxLeads = c; });
        var postYAxisMax = Math.ceil(postMaxLeads * 1.15);
        if (postYAxisMax < 5) postYAxisMax = 5;
        var postTickInterval = Math.ceil(postYAxisMax / 5);
        if (postTickInterval < 1) postTickInterval = 1;

        Highcharts.chart('postLeadsChart', {
            chart: { type: 'line', backgroundColor: '#f8f9fa', borderRadius: 12,
                spacingTop: 10, spacingRight: 10, spacingBottom: 10, spacingLeft: 10 },
            title: { text: null },
            xAxis: {
                categories: postChartDates, crosshair: true,
                labels: { rotation: -45, style: { fontSize: '11px' } },
                lineColor: '#d9e2ec'
            },
            yAxis: {
                min: 0, max: postYAxisMax, tickInterval: postTickInterval,
                title: { text: 'Sesiones' }, allowDecimals: false, gridLineColor: '#e2e8f0'
            },
            legend: { enabled: false },
            plotOptions: {
                line: {
                    color: '#C5A028',
                    lineWidth: 2.5,
                    marker: {
                        enabled: true,
                        radius: 4,
                        fillColor: '#C5A028',
                        lineWidth: 2,
                        lineColor: '#fff'
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function() { return this.y > 0 ? this.y : ''; },
                        style: { fontSize: '10px', fontWeight: '600', textOutline: 'none' }
                    }
                }
            },
            series: [{ name: 'Sesiones', data: postChartCounts }],
            tooltip: {
                backgroundColor: 'rgba(15,23,42,0.92)', style: { color: '#fff' }, borderWidth: 0,
                formatter: function() { return '<b>' + this.x + '</b><br/>Sesiones agendadas: <b>' + this.y + '</b>'; }
            },
            credits: { enabled: false }
        });
        }

    });

    // ── Utility: escape HTML ─────────────────────────────
    function escapeHtml(unsafe) {
        if (!unsafe && unsafe !== 0) return '';
        return String(unsafe)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function showErrorModalFromResponse(response, xhr) {
        var message = (response && (response.message || response.msg)) ? (response.message || response.msg) : 'Hubo un error al procesar la solicitud';
        var html = '<div style="text-align:left;">' + escapeHtml(message);
        if (response && response.mail_error) {
            html += '<hr><div><strong>Detalle:</strong><pre style="white-space:pre-wrap;">' + escapeHtml(response.mail_error) + '</pre></div>';
        }
        if (xhr && xhr.status) {
            html += '<hr><div><strong>HTTP:</strong> ' + xhr.status + ' ' + escapeHtml(xhr.statusText || '') + '</div>';
        }
        if (xhr && xhr.responseText) {
            try {
                var parsed = JSON.parse(xhr.responseText);
                html += '<hr><div><strong>Body:</strong><pre style="white-space:pre-wrap;">' + escapeHtml(JSON.stringify(parsed, null, 2)) + '</pre></div>';
            } catch (e) {
                html += '<hr><div><strong>Body (raw):</strong><pre style="white-space:pre-wrap;">' + escapeHtml(xhr.responseText) + '</pre></div>';
            }
        } else if (xhr && xhr.status >= 500) {
            html += '<hr><div><strong>Nota:</strong> Revisa el log de errores del servidor.</div>';
        }
        html += '</div>';
        Swal.fire({ title: 'Error', html: html, icon: 'error', width: '70%' });
    }

    function applyOptionClass($el) {
        if (!$el || !$el.length) return;
        $el.removeClass('option1 option2 option3 option4 option5 option6 option7 option8');
        var cls = $el.find('option:selected').attr('class');
        if (cls) $el.addClass(cls);
    }

    // ── Ver comentarios ──────────────────────────────────
    function verComentarios(id) {
        Swal.fire({ title: 'Cargando comentarios...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        $.ajax({
            url: 'actualizar_lead.php',
            method: 'POST',
            dataType: 'json',
            data: { action: 'get_comments', id: id },
            success: function (resp) {
                Swal.close();
                if (resp && resp.success) {
                    const comments = resp.comments || [];
                    let html = '<div id="commentsList">';
                    if (!comments.length) {
                        html += '<p>No hay comentarios aún.</p>';
                    } else {
                        html += '<div class="list-group mb-3">';
                        comments.forEach(function (c) {
                            const when   = escapeHtml(c.created_at || '');
                            const author = escapeHtml(c.author || 'Vendedor');
                            const text   = escapeHtml(c.text || '');
                            html += '<div class="list-group-item">';
                            html += '<div class="d-flex justify-content-between w-100">';
                            html += '<h6 class="mb-1">' + author + '</h6>';
                            html += '<small>' + when + '</small></div>';
                            html += '<p class="mb-1">' + text + '</p></div>';
                        });
                        html += '</div>';
                    }
                    html += '</div>';
                    html += '<div class="mb-3"><label class="form-label">Agregar comentario</label>';
                    html += '<textarea id="newCommentText" class="form-control" rows="4" placeholder="Escribe un comentario..."></textarea></div>';
                    html += '<div class="text-end"><button id="saveCommentBtn" class="btn btn-primary">Guardar comentario</button></div>';

                    document.getElementById('comentariosModalBody').innerHTML = html;
                    var modal = new bootstrap.Modal(document.getElementById('comentariosModal'));
                    modal.show();

                    $('#saveCommentBtn').on('click', function () {
                        var comment = $('#newCommentText').val();
                        if (!comment || !comment.trim()) {
                            Swal.fire('Error', 'El comentario no puede estar vacío', 'error'); return;
                        }
                        var $btn = $(this);
                        $btn.prop('disabled', true);
                        Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                        $.ajax({
                            url: 'actualizar_lead.php',
                            method: 'POST',
                            dataType: 'json',
                            data: { action: 'add_comment', id: id, comment: comment, author: '' },
                            success: function (resp2) {
                                Swal.close();
                                if (resp2 && resp2.success) {
                                    Swal.fire('Guardado', 'Comentario agregado correctamente', 'success');
                                    const commentsNew = resp2.comments || [];
                                    let listHtml = '';
                                    if (!commentsNew.length) {
                                        listHtml = '<p>No hay comentarios aún.</p>';
                                    } else {
                                        listHtml = '<div class="list-group mb-3">';
                                        commentsNew.forEach(function (c) {
                                            const when   = escapeHtml(c.created_at || '');
                                            const author = escapeHtml(c.author || 'Vendedor');
                                            const text   = escapeHtml(c.text || '');
                                            listHtml += '<div class="list-group-item"><div class="d-flex justify-content-between w-100">';
                                            listHtml += '<h6 class="mb-1">' + author + '</h6><small>' + when + '</small></div>';
                                            listHtml += '<p class="mb-1">' + text + '</p></div>';
                                        });
                                        listHtml += '</div>';
                                    }
                                    $('#commentsList').html(listHtml);
                                    $('#newCommentText').val('');
                                } else {
                                    Swal.fire('Error', resp2.message || 'No se pudo guardar el comentario', 'error');
                                }
                            },
                            error: function () {
                                Swal.close();
                                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                            },
                            complete: function () { $btn.prop('disabled', false); }
                        });
                    });
                } else {
                    Swal.fire('Error', resp.message || 'No se pudieron obtener los comentarios', 'error');
                }
            },
            error: function () { Swal.fire('Error', 'Error de comunicación con el servidor', 'error'); }
        });
    }

    // ── Ver más (detalle completo del lead) ──────────────
    function verMas(lead) {
        let content = '<div class="row">';
        const fields = [
            { key: 'id',            label: 'ID' },
            { key: 'full_name',     label: 'Nombre Completo' },
            { key: 'email',         label: 'Email' },
            { key: 'phone',         label: 'Teléfono' },
            { key: 'created_time',  label: 'Fecha de Creación' },
            { key: 'campaign_name', label: 'Campaña' },
            { key: 'where_are_you_getting_married_', label: 'Ubicación de la Boda' },
            { key: 'form_name',     label: 'Formulario de Origen' },
            { key: 'has_appointment', label: 'Tiene Cita', format: value => value == 1 ? 'Sí' : 'No' }
        ];
        fields.forEach(field => {
            let value = lead[field.key] || 'N/A';
            if (field.format) value = field.format(value);
            if (field.key === 'where_are_you_getting_married_') {
                value = lead['where_are_you_getting_married_'] || lead['where_is_your_marriage_taking_place_'] || 'N/A';
            }
            content += `<div class="mb-3 col-md-6"><label class="form-label fw-bold">${field.label}:</label><p class="mb-0">${value}</p></div>`;
        });
        content += '</div>';
        document.getElementById('modalBody').innerHTML = content;
        var modal = new bootstrap.Modal(document.getElementById('verMasModal'));
        modal.show();
    }

    // ── Inline update (selects) ──────────────────────────
    $(document).ready(function () {
        $('.lead-update').each(function () { applyOptionClass($(this)); });

        $(document).on('change', '.lead-update', function () {
            var el    = $(this);
            var id    = el.data('id');
            var field = el.data('field');
            var value = el.val();
            el.prop('disabled', true);

            $.ajax({
                url: 'actualizar_lead.php',
                method: 'POST',
                dataType: 'json',
                data: { id: id, field: field, value: value },
                success: function (resp) {
                    if (resp && resp.success) {
                        el.closest('td').css('background', '#e6ffed');
                        setTimeout(function () { el.closest('td').css('background', ''); }, 800);
                        applyOptionClass(el);
                    } else {
                        Swal.fire('Error', resp.message || 'No se pudo actualizar', 'error');
                    }
                },
                error: function () { Swal.fire('Error', 'Error de comunicación con el servidor', 'error'); },
                complete: function () { el.prop('disabled', false); }
            });
        });

        var commentTimer = {};
        $(document).on('input', '.lead-update-text', function () {
            var el    = $(this);
            var id    = el.data('id');
            var field = el.data('field');
            var value = el.val();
            clearTimeout(commentTimer[id + field]);
            commentTimer[id + field] = setTimeout(function () {
                el.prop('disabled', true);
                $.ajax({
                    url: 'actualizar_lead.php',
                    method: 'POST',
                    dataType: 'json',
                    data: { id: id, field: field, value: value },
                    success: function (resp) {
                        if (resp && resp.success) {
                            el.closest('td').css('background', '#e6ffed');
                            setTimeout(function () { el.closest('td').css('background', ''); }, 800);
                        } else {
                            Swal.fire('Error', resp.message || 'No se pudo actualizar', 'error');
                        }
                    },
                    error: function () { Swal.fire('Error', 'Error de comunicación con el servidor', 'error'); },
                    complete: function () { el.prop('disabled', false); }
                });
            }, 700);
        });
    });

    // ════════════════════════════════════════════════════
    // SISTEMA DE FILTROS POR COLUMNA
    // ════════════════════════════════════════════════════
    var columnFilters = {};
    var currentFilterDropdown = null;

    function getUniqueColumnValues(columnName) {
        var values = new Set();
        getLeadsTableRows().each(function () {
            var cell = $(this).find('td[data-column="' + columnName + '"]');
            if (cell.length > 0) {
                var text = cell.text().trim();
                if (columnName === 'estatus') {
                    text = cell.find('.status').first().text().trim();
                }
                if (text !== '') values.add(text);
            }
        });
        return Array.from(values).sort();
    }

    function createFilterDropdown(columnName, iconElement) {
        closeFilterDropdown();
        var uniqueValues = getUniqueColumnValues(columnName);
        if (uniqueValues.length === 0) return;

        var dropdown = $('<div class="filter-dropdown show"></div>');
        var header   = $('<div class="filter-dropdown-header"></div>');
        header.append('<span>Filtrar por ' + columnName + '</span>');
        var closeBtn = $('<button type="button" class="btn-close btn-sm" style="font-size:0.7rem;"></button>');
        closeBtn.on('click', closeFilterDropdown);
        header.append(closeBtn);
        dropdown.append(header);

        var body          = $('<div class="filter-dropdown-body"></div>');
        var searchInput   = $('<input type="text" class="filter-search" placeholder="Buscar...">');
        body.append(searchInput);

        var optionsContainer = $('<div class="filter-options"></div>');
        var selectAllDiv     = $('<div class="filter-option mb-2"></div>');
        var selectAllCheckbox = $('<input type="checkbox" id="selectAll_' + columnName + '" checked>');
        var selectAllLabel    = $('<label for="selectAll_' + columnName + '"><strong>Seleccionar todo</strong></label>');
        selectAllDiv.append(selectAllCheckbox).append(selectAllLabel);
        optionsContainer.append(selectAllDiv);
        optionsContainer.append('<hr style="margin:0.5rem 0;">');

        var selectedValues = columnFilters[columnName] || uniqueValues.slice();
        uniqueValues.forEach(function (value, index) {
            var optionDiv = $('<div class="filter-option" data-value="' + escapeHtml(value) + '"></div>');
            var checkbox  = $('<input type="checkbox" id="filter_' + columnName + '_' + index + '">');
            if (selectedValues.includes(value)) checkbox.prop('checked', true);
            var label = $('<label for="filter_' + columnName + '_' + index + '">' + escapeHtml(value) + '</label>');
            optionDiv.append(checkbox).append(label);
            optionsContainer.append(optionDiv);
        });
        body.append(optionsContainer);
        dropdown.append(body);

        var footer   = $('<div class="filter-dropdown-footer"></div>');
        var clearBtn = $('<button type="button" class="filter-btn-sm filter-btn-sm-secondary">Limpiar</button>');
        var applyBtn = $('<button type="button" class="filter-btn-sm filter-btn-sm-primary">Aplicar</button>');

        clearBtn.on('click', function () {
            optionsContainer.find('input[type="checkbox"]').prop('checked', true);
            delete columnFilters[columnName];
            applyColumnFilters();
            closeFilterDropdown();
        });

        applyBtn.on('click', function () {
            var selected = [];
            optionsContainer.find('.filter-option:not(:first)').each(function () {
                var checkbox = $(this).find('input[type="checkbox"]');
                if (checkbox.prop('checked')) selected.push($(this).data('value'));
            });
            if (selected.length === 0) {
                Swal.fire({ icon: 'warning', title: 'Advertencia', text: 'Debe seleccionar al menos un valor', confirmButtonText: 'OK' });
                return;
            }
            columnFilters[columnName] = selected;
            applyColumnFilters();
            closeFilterDropdown();
        });

        footer.append(clearBtn).append(applyBtn);
        dropdown.append(footer);
        $('body').append(dropdown);

        var anchorRect     = iconElement.getBoundingClientRect();
        var dropdownWidth  = dropdown.outerWidth();
        var dropdownHeight = dropdown.outerHeight();
        var viewportWidth  = window.innerWidth;
        var viewportHeight = window.innerHeight;
        var gap = 5;
        var left = anchorRect.left;
        var top  = anchorRect.bottom + gap;
        if (left + dropdownWidth  > viewportWidth  - 10) left = Math.max(10, viewportWidth  - dropdownWidth  - 10);
        if (top  + dropdownHeight > viewportHeight - 10) top  = anchorRect.top - dropdownHeight - gap;
        dropdown.css({ position: 'fixed', left: left + 'px', top: top + 'px' });
        currentFilterDropdown = dropdown;
        $(iconElement).addClass('active');

        searchInput.on('input', function () {
            var searchTerm = $(this).val().toLowerCase();
            optionsContainer.find('.filter-option:not(:first)').each(function () {
                var text = $(this).find('label').text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
        });

        selectAllCheckbox.on('change', function () {
            var isChecked = $(this).prop('checked');
            optionsContainer.find('.filter-option:not(:first) input[type="checkbox"]:visible').prop('checked', isChecked);
        });

        optionsContainer.on('change', '.filter-option:not(:first) input[type="checkbox"]', function () {
            var visibleBoxes  = optionsContainer.find('.filter-option:not(:first):visible input[type="checkbox"]');
            var checkedBoxes  = visibleBoxes.filter(':checked');
            selectAllCheckbox.prop('checked', visibleBoxes.length === checkedBoxes.length);
        });
    }

    function closeFilterDropdown() {
        if (currentFilterDropdown) {
            currentFilterDropdown.remove();
            currentFilterDropdown = null;
            $('.filter-icon.active').removeClass('active');
        }
    }

    function getLeadsTableRows() {
        if ($.fn.dataTable.isDataTable('#leadsTable')) {
            return $($('#leadsTable').DataTable().rows({ search: 'applied' }).nodes());
        }
        return $('#leadsTable tbody tr');
    }

    function applyColumnFilters() {
        $('.filter-icon').removeClass('active');
        Object.keys(columnFilters).forEach(function (col) {
            $('.filter-icon[data-column="' + col + '"]').addClass('active');
        });
        getLeadsTableRows().each(function () {
            var row     = $(this);
            var showRow = true;
            for (var columnName in columnFilters) {
                var cell  = row.find('td[data-column="' + columnName + '"]');
                if (cell.length > 0) {
                    var cellValue = cell.text().trim();
                    if (columnName === 'estatus') {
                        cellValue = cell.find('.status').first().text().trim();
                    }
                    var allowedValues = columnFilters[columnName];
                    if (!allowedValues.includes(cellValue)) { showRow = false; break; }
                }
            }
            showRow ? row.show() : row.hide();
        });
        updateDynamicCards();
    }

    // ── Dynamic cards update ─────────────────────────────
    function updateDynamicCards() {
        var totalVisible = 0;
        var originKnownVisible = 0;
        var officialVisible = 0;
        var closedVisible = 0;
        var wpAfianzadosVisible = <?php echo intval($postWpAfianzadosCount); ?>;
        var wpAtendidosVisible = 0;
        var engagementHighVisible = 0;

        getLeadsTableRows().each(function () {
            if (!$(this).is(':visible')) {
                return;
            }
            totalVisible++;
            originKnownVisible += Number($(this).attr('data-origin-known') || 0);
            officialVisible += Number($(this).attr('data-llamada-oficial') || 0);
            closedVisible += Number($(this).attr('data-is-closed') || 0);
            wpAtendidosVisible += Number($(this).attr('data-wp-atendido') || 0);
            engagementHighVisible += Number($(this).attr('data-engagement-high') || 0);
        });

        var closeRate = totalVisible > 0 ? ((closedVisible / totalVisible) * 100).toFixed(1) : '0.0';
        var originPendingVisible = Math.max(totalVisible - originKnownVisible, 0);
        var originPendingRate = totalVisible > 0 ? ((originPendingVisible / totalVisible) * 100).toFixed(1) : '0.0';
        $('#kpi-post-total').text(totalVisible);
        $('#kpi-origin-known').text(originKnownVisible);
        $('#kpi-origin-pending').text(originPendingVisible);
        $('#kpi-origin-pending-detail').text(originPendingRate + '% del total sigue sin origen confirmado.');
        $('#kpi-official-call').text(officialVisible);
        $('#kpi-wp-afianzados').text(wpAfianzadosVisible.toLocaleString());
        $('#kpi-wp-afianzados-detail').text('Total de eventos de todos los wedding planners afianzados.');
        $('#kpi-wp-atendidos').text(wpAtendidosVisible.toLocaleString());
        $('#kpi-wp-atendidos-detail').text('Sesiones WP (tabla/form eventos_wp) atendidas en el periodo.');

        $('#report-close-rate').text(closeRate + '%');
        $('#report-close-rate-detail').text(closedVisible + ' cierres de ' + totalVisible + ' sesiones agendadas');
        $('#report-origin-pending-count').text(originPendingVisible + ' leads');
        $('#table-count-label').text(totalVisible + ' sesiones · ' + closedVisible + ' cierres');
    }

    // ── Column filter event wiring ───────────────────────
    $(document).on('click', '.filter-icon', function (e) {
        e.stopPropagation();
        createFilterDropdown($(this).data('column'), this);
    });

    $(document).on('click', function (e) {
        if (currentFilterDropdown && !$(e.target).closest('.filter-dropdown').length && !$(e.target).hasClass('filter-icon')) {
            closeFilterDropdown();
        }
    });

    $(document).on('click', '.filter-dropdown', function (e) { e.stopPropagation(); });
    $(document).on('keydown', function (e) { if (e.key === 'Escape' && currentFilterDropdown) closeFilterDropdown(); });

    // ════════════════════════════════════════════════════
    // FIN SISTEMA DE FILTROS POR COLUMNA
    // ════════════════════════════════════════════════════
</script>
<?php
// ── DIAGNÓSTICO: leads con origen "Sin dato" ──────────────────────────────────
$_sinDatoLeads = [];
foreach ($displayLeads as $_lead) {
    $_howRaw = trim((string)($_lead['how_did_you_meet'] ?? ''));
    if (!in_array($_howRaw, ['1', '2', '3'], true)) {
        $_motivo = $_howRaw === ''
            ? 'Campo how_did_you_meet está vacío en la BD'
            : 'Valor desconocido: "' . addslashes($_howRaw) . '" (se esperaba 1, 2 o 3)';
        $_sinDatoLeads[] = [
            'id'               => $_lead['id'] ?? '?',
            'nombre'           => $_lead['full_name'] ?? '',
            'how_did_you_meet' => $_howRaw === '' ? '(vacío)' : $_howRaw,
            'tabla_origen'     => $_lead['tabla_origen'] ?? '',
            'motivo'           => $_motivo,
        ];
    }
}
?>
<script>
(function () {
    var sinDatoLeads = <?php echo json_encode($_sinDatoLeads, JSON_UNESCAPED_UNICODE); ?>;
    if (sinDatoLeads.length === 0) {
        console.log('[consulta_post_leads] Origen del cliente: todos los registros tienen origen definido ✅');
        return;
    }
    console.group('[consulta_post_leads] ' + sinDatoLeads.length + ' registro(s) con origen "Sin dato":');
    sinDatoLeads.forEach(function (lead) {
        console.log(
            'ID: ' + lead.id +
            ' | Nombre: ' + (lead.nombre || '(sin nombre)') +
            ' | Tabla: ' + (lead.tabla_origen || '(sin tabla)') +
            ' → ' + lead.motivo
        );
    });
    console.groupEnd();
})();
</script>
</div>
<?php