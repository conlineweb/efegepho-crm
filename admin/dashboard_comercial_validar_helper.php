<?php

require_once __DIR__ . '/wp_eventos_contact_form_helper.php';

if (!function_exists('isB1B2CampaignName')) {
    function isB1B2CampaignName($campaignName)
    {
        if ($campaignName === null) {
            return false;
        }
        $v = strtolower(trim((string) $campaignName));
        if ($v === '') {
            return false;
        }
        $v = preg_replace('/\s+/', ' ', $v);

        return in_array($v, ['b1', 'b1 (usa)', 'b1 usa', 'b2', 'b2 (mx)', 'b2 mx'], true);
    }
}

if (!function_exists('dashValidarBuildLeadKeyIndex')) {
    function dashValidarBuildLeadKeyIndex(array $fetchLeads)
    {
        $index = [];
        foreach ($fetchLeads as $row) {
            $origen = trim((string) ($row['origen'] ?? ''));
            $id = (int) ($row['id'] ?? 0);
            if ($origen !== '' && $id > 0) {
                $index[strtolower($origen) . '|' . $id] = true;
            }
        }

        return $index;
    }
}

if (!function_exists('dashValidarBuildCfIdIndex')) {
    function dashValidarBuildCfIdIndex(array $cohort)
    {
        $index = [];
        foreach ($cohort as $cid => $item) {
            $id = (int) $cid;
            if ($id > 0) {
                $index[$id] = $item;
            }
        }

        return $index;
    }
}

if (!function_exists('dashValidarLoadTablaTipoMap')) {
    function dashValidarLoadTablaTipoMap($conn)
    {
        $map = [];
        $res = $conn->query('SELECT nombre, tipo FROM tablas_leads');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $name = trim((string) ($row['nombre'] ?? ''));
                if ($name !== '') {
                    $map[$name] = (int) ($row['tipo'] ?? -1);
                }
            }
        }

        return $map;
    }
}

if (!function_exists('dashValidarClassifyCommercialOrigin')) {
    function dashValidarClassifyCommercialOrigin(array $cf, array $tablaTipoMap)
    {
        $campaign = mb_strtolower(trim((string) ($cf['campaign_name'] ?? '')), 'UTF-8');
        $tabla = trim((string) ($cf['tabla_origen'] ?? ''));
        $tipo = isset($tablaTipoMap[$tabla]) ? (int) $tablaTipoMap[$tabla] : -1;

        if ($campaign === 'website') {
            return 'sitio_web';
        }
        if ($campaign === 'reg manual' || mb_strtolower(trim((string) ($cf['manual'] ?? '')), 'UTF-8') === '1') {
            return 'registro_manual';
        }
        if ($tipo === 1 || (function_exists('isB1B2CampaignName') && isB1B2CampaignName($campaign))) {
            return 'facebook_lead_form';
        }
        if ($tipo === 0) {
            return 'sitio_web';
        }

        return 'comercial_otro';
    }
}

if (!function_exists('dashValidarRecordStageFlags')) {
    function dashValidarRecordStageFlags(array $expected, array $actual, array $context = [])
    {
        $flags = [
            'leads'     => !empty($expected['leads']) ? !empty($actual['leads']) : null,
            'agendas'   => !empty($expected['agendas']) ? !empty($actual['agendas']) : null,
            'atendidos' => !empty($expected['atendidos']) ? !empty($actual['atendidos']) : null,
            'clientes'  => !empty($expected['clientes']) ? !empty($actual['clientes']) : null,
        ];

        $ok = true;
        foreach (['leads', 'agendas', 'atendidos', 'clientes'] as $stage) {
            if ($flags[$stage] === false) {
                $ok = false;
                break;
            }
        }

        return [
            'expected' => $expected,
            'actual'   => $actual,
            'flags'    => $flags,
            'ok'       => $ok,
            'notas'    => (string) ($context['notas'] ?? ''),
        ];
    }
}

if (!function_exists('dashValidarExpectedStagesForEventosWp')) {
    function dashValidarExpectedStagesForEventosWp(array $cf)
    {
        $cliente = (int) ($cf['cliente'] ?? 0);

        $expected = [
            'leads'     => true,
            'agendas'   => true,
            'atendidos' => false,
            'clientes'  => false,
        ];

        if ($cliente === 1) {
            $expected['atendidos'] = true;
            $expected['clientes']  = true;
        } elseif ($cliente >= 2) {
            $expected['atendidos'] = true;
        }

        return $expected;
    }
}

if (!function_exists('dashValidarExpectedStagesForCommercialCf')) {
    function dashValidarExpectedStagesForCommercialCf(array $cf, ?array $cohortItem = null)
    {
        $cliente = (int) ($cf['cliente'] ?? 0);
        $estatus = '';
        if (is_array($cohortItem)) {
            $estatus = dashComercialResolveCohortItemEstatusKey($cohortItem);
        }

        $expected = [
            'leads'     => true,
            'agendas'   => true,
            'atendidos' => false,
            'clientes'  => false,
        ];

        if ($cliente === 1 || $estatus === 'cliente') {
            $expected['atendidos'] = true;
            $expected['clientes']  = true;
        } elseif (in_array($estatus, ['atendido', 'muerto'], true) || $cliente > 0) {
            $expected['atendidos'] = true;
        }

        return $expected;
    }
}

if (!function_exists('dashValidarCheckEventosWpRecord')) {
    function dashValidarCheckEventosWpRecord(
        array $cf,
        array $leadIndex,
        array $agendaIndex,
        array $atendidoIndex,
        array $clienteIndex
    ) {
        $eventId = (int) ($cf['original_lead_id'] ?? 0);
        $cfId = (int) ($cf['id'] ?? 0);
        $expected = dashValidarExpectedStagesForEventosWp($cf);

        $actual = [
            'leads'     => $eventId > 0 && isset($leadIndex['eventos_wp|' . $eventId]),
            'agendas'   => $cfId > 0 && isset($agendaIndex[$cfId]),
            'atendidos' => $cfId > 0 && isset($atendidoIndex[$cfId]),
            'clientes'  => $cfId > 0 && isset($clienteIndex[$cfId]),
        ];

        $notas = 'tipo_cliente=' . (int) ($cf['tipo_cliente'] ?? 0) . ', cliente=' . (int) ($cf['cliente'] ?? 0);
        if (!$actual['leads'] && $eventId > 0) {
            $notas .= ' · Falta en Leads (eventos_wp|' . $eventId . ')';
        }
        if ($expected['agendas'] && !$actual['agendas']) {
            $notas .= ' · Fuera de Agendas (fecha/estatus/filtro)';
        }
        if ($expected['atendidos'] && !$actual['atendidos']) {
            $notas .= ' · Fuera de Atendidos (estatus modal: atendido/cliente/muerto)';
        }
        if ($expected['clientes'] && !$actual['clientes']) {
            $notas .= ' · Fuera de Clientes (cliente=1 + fecha_cambio en periodo)';
        }

        return dashValidarRecordStageFlags($expected, $actual, ['notas' => $notas]);
    }
}

if (!function_exists('dashValidarCheckCommercialRecord')) {
    function dashValidarCheckCommercialRecord(
        array $cf,
        array $leadIndex,
        array $agendaIndex,
        array $atendidoIndex,
        array $clienteIndex,
        ?array $cohortItem = null
    ) {
        $origId = (int) ($cf['original_lead_id'] ?? 0);
        $tabla = trim((string) ($cf['tabla_origen'] ?? ''));
        $cfId = (int) ($cf['id'] ?? 0);
        $expected = dashValidarExpectedStagesForCommercialCf($cf, $cohortItem);

        $leadKey = ($tabla !== '' && $origId > 0) ? strtolower($tabla) . '|' . $origId : '';

        $actual = [
            'leads'     => $leadKey !== '' && isset($leadIndex[$leadKey]),
            'agendas'   => $cfId > 0 && isset($agendaIndex[$cfId]),
            'atendidos' => $cfId > 0 && isset($atendidoIndex[$cfId]),
            'clientes'  => $cfId > 0 && isset($clienteIndex[$cfId]),
        ];

        $notas = 'tabla=' . $tabla . ', cliente=' . (int) ($cf['cliente'] ?? 0);
        if ($expected['leads'] && !$actual['leads']) {
            $notas .= ' · Lead precalificado no encontrado (' . $leadKey . ')';
        }
        if ($expected['agendas'] && !$actual['agendas']) {
            $notas .= ' · Fuera de Agendas';
        }
        if ($expected['atendidos'] && !$actual['atendidos']) {
            $notas .= ' · Fuera de Atendidos';
        }
        if ($expected['clientes'] && !$actual['clientes']) {
            $notas .= ' · Fuera de Clientes';
        }

        return dashValidarRecordStageFlags($expected, $actual, ['notas' => $notas]);
    }
}

if (!function_exists('dashValidarCheckWpCitasLeadRecord')) {
    function dashValidarCheckWpCitasLeadRecord(array $row, array $leadIndex, array $agendaIndex, array $atendidoIndex, array $clienteIndex)
    {
        $wclId = (int) ($row['wcl_id'] ?? 0);
        $cfId = (int) ($row['cf_id'] ?? 0);
        $cliente = (int) ($row['cf_cliente'] ?? 0);

        $expected = [
            'leads'     => true,
            'agendas'   => $cfId > 0,
            'atendidos' => $cfId > 0 && ($cliente === 1 || $cliente === 2),
            'clientes'  => $cliente === 1,
        ];

        $actual = [
            'leads'     => $wclId > 0 && isset($leadIndex['wp_citas_leads|' . $wclId]),
            'agendas'   => $cfId > 0 && isset($agendaIndex[$cfId]),
            'atendidos' => $cfId > 0 && isset($atendidoIndex[$cfId]),
            'clientes'  => $cfId > 0 && isset($clienteIndex[$cfId]),
        ];

        $notas = $cfId > 0
            ? 'Sesión intro con evento contact_form #' . $cfId
            : 'Sesión intro sin evento contact_form aún';

        if (!$actual['leads']) {
            $notas .= ' · Falta en Leads precalificados (wp_citas_leads)';
        }

        return dashValidarRecordStageFlags($expected, $actual, ['notas' => $notas]);
    }
}

if (!function_exists('dashComercialValidarFunnelFlows')) {
    function dashComercialValidarFunnelFlows(
        $conn,
        $startDate,
        $endDate,
        array $fetchLeads,
        array $agendadosModal,
        array $atendidosModal,
        array $clientesModal
    ) {
        $leadIndex = dashValidarBuildLeadKeyIndex($fetchLeads);
        $agendaIndex = dashValidarBuildCfIdIndex($agendadosModal);
        $atendidoIndex = dashValidarBuildCfIdIndex($atendidosModal);
        $clienteIndex = dashValidarBuildCfIdIndex($clientesModal);
        $tablaTipoMap = dashValidarLoadTablaTipoMap($conn);

        $sd = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) : null;
        $ed = $endDate !== '' ? date('Y-m-d', strtotime($endDate)) : null;

        $flows = [];

        $introRows = [];
        $introOk = 0;
        $introGap = 0;
        $introSql = "SELECT wcl.id AS wcl_id, wcl.full_name, wcl.created_time, wcl.id_evento,
                            cf.id AS cf_id, cf.cliente AS cf_cliente, cf.tipo_cliente
                     FROM wp_citas_leads wcl
                     LEFT JOIN eventos_wp e ON e.id = wcl.id_evento
                     LEFT JOIN contact_form cf ON cf.original_lead_id = e.id
                        AND LOWER(TRIM(COALESCE(cf.tabla_origen, ''))) = 'eventos_wp'
                     WHERE 1=1";
        if ($sd) {
            $introSql .= " AND DATE(wcl.created_time) >= '" . $conn->real_escape_string($sd) . "'";
        }
        if ($ed) {
            $introSql .= " AND DATE(wcl.created_time) <= '" . $conn->real_escape_string($ed) . "'";
        }
        $introSql .= ' ORDER BY wcl.id DESC LIMIT 200';
        $introRes = $conn->query($introSql);
        if ($introRes) {
            while ($row = $introRes->fetch_assoc()) {
                $check = dashValidarCheckWpCitasLeadRecord($row, $leadIndex, $agendaIndex, $atendidoIndex, $clienteIndex);
                if ($check['ok']) {
                    $introOk++;
                } else {
                    $introGap++;
                }
                $introRows[] = array_merge($row, ['audit' => $check]);
            }
        }
        $flows['wp_sesion_intro'] = [
            'titulo' => 'Sesión introductoria WP (wp_citas_leads)',
            'descripcion' => 'Al agendar sesión intro debe existir Lead precalificado. Si ya hay evento en contact_form, debe reflejarse en Agendas/Atendidos/Clientes según cliente.',
            'total' => count($introRows),
            'ok' => $introOk,
            'gaps' => $introGap,
            'rows' => $introRows,
        ];

        $eventRows = [];
        $eventOk = 0;
        $eventGap = 0;
        $eventSql = "SELECT cf.*
                     FROM contact_form cf
                     WHERE (LOWER(TRIM(COALESCE(cf.form_name, ''))) = 'eventos_wp'
                         OR LOWER(TRIM(COALESCE(cf.tabla_origen, ''))) = 'eventos_wp')
                       AND COALESCE(cf.tipo_cliente, 1) = 1";
        if ($sd) {
            $eventSql .= " AND DATE(COALESCE(cf.created_time, cf.submission_date)) >= '" . $conn->real_escape_string($sd) . "'";
        }
        if ($ed) {
            $eventSql .= " AND DATE(COALESCE(cf.created_time, cf.submission_date)) <= '" . $conn->real_escape_string($ed) . "'";
        }
        $eventSql .= ' ORDER BY cf.id DESC LIMIT 200';
        $eventRes = $conn->query($eventSql);
        if ($eventRes) {
            while ($cf = $eventRes->fetch_assoc()) {
                $cf = wpEventosCfHydratePlannerContactFormFields($conn, $cf);
                $check = dashValidarCheckEventosWpRecord($cf, $leadIndex, $agendaIndex, $atendidoIndex, $clienteIndex);
                if ($check['ok']) {
                    $eventOk++;
                } else {
                    $eventGap++;
                }
                $eventRows[] = [
                    'id' => (int) ($cf['id'] ?? 0),
                    'nombre' => trim((string) ($cf['names'] ?? '')),
                    'cliente' => (int) ($cf['cliente'] ?? 0),
                    'event_id' => (int) ($cf['original_lead_id'] ?? 0),
                    'audit' => $check,
                ];
            }
        }
        $flows['wp_evento'] = [
            'titulo' => 'Evento WP (contact_form eventos_wp)',
            'descripcion' => 'Registro directo o conversión de sesión: Leads + Agendas; Cotizado (cliente=2) en Atendidos; Cliente (cliente=1) en Clientes cerrados.',
            'total' => count($eventRows),
            'ok' => $eventOk,
            'gaps' => $eventGap,
            'rows' => $eventRows,
        ];

        $commercialGroups = [
            'sitio_web' => [
                'titulo' => 'Sitio web (website / orgánico)',
                'filter' => static function ($origin) {
                    return $origin === 'sitio_web';
                },
            ],
            'registro_manual' => [
                'titulo' => 'Registro manual',
                'filter' => static function ($origin) {
                    return $origin === 'registro_manual';
                },
            ],
            'facebook_lead_form' => [
                'titulo' => 'Facebook Lead Form (campaña)',
                'filter' => static function ($origin) {
                    return $origin === 'facebook_lead_form';
                },
            ],
        ];

        foreach ($commercialGroups as $key => $meta) {
            $commercialRows = [];
            $cOk = 0;
            $cGap = 0;

            $cfSql = "SELECT cf.* FROM contact_form cf
                      INNER JOIN calendario c ON c.idclie = cf.id
                      WHERE LOWER(COALESCE(cf.tabla_origen, '')) NOT IN ('wedding_planners', 'wedding_planner', 'eventos_wp')
                        AND COALESCE(c.eliminado, 0) = 0";
            if ($sd) {
                $cfSql .= " AND DATE(COALESCE(cf.created_time, cf.submission_date)) >= '" . $conn->real_escape_string($sd) . "'";
            }
            if ($ed) {
                $cfSql .= " AND DATE(COALESCE(cf.created_time, cf.submission_date)) <= '" . $conn->real_escape_string($ed) . "'";
            }
            $cfSql .= ' ORDER BY cf.id DESC LIMIT 500';

            $cfRes = $conn->query($cfSql);
            if ($cfRes) {
                while ($cf = $cfRes->fetch_assoc()) {
                    $origin = dashValidarClassifyCommercialOrigin($cf, $tablaTipoMap);
                    if (!$meta['filter']($origin)) {
                        continue;
                    }

                    $cfId = (int) ($cf['id'] ?? 0);
                    $cohortItem = $atendidoIndex[$cfId] ?? $agendaIndex[$cfId] ?? $clienteIndex[$cfId] ?? null;
                    $check = dashValidarCheckCommercialRecord($cf, $leadIndex, $agendaIndex, $atendidoIndex, $clienteIndex, $cohortItem);
                    if ($check['ok']) {
                        $cOk++;
                    } else {
                        $cGap++;
                    }
                    $commercialRows[] = [
                        'id' => $cfId,
                        'nombre' => trim((string) ($cf['names'] ?? '')),
                        'tabla' => trim((string) ($cf['tabla_origen'] ?? '')),
                        'campaign' => trim((string) ($cf['campaign_name'] ?? '')),
                        'cliente' => (int) ($cf['cliente'] ?? 0),
                        'audit' => $check,
                    ];
                }
            }

            $flows[$key] = [
                'titulo' => $meta['titulo'],
                'descripcion' => 'Lead precalificado (tabla origen) → Agendas (con cita) → Atendidos → Clientes cerrados.',
                'total' => count($commercialRows),
                'ok' => $cOk,
                'gaps' => $cGap,
                'rows' => array_slice($commercialRows, 0, 100),
            ];
        }

        $totalChecked = 0;
        $totalOk = 0;
        $totalGaps = 0;
        foreach ($flows as $flow) {
            $totalChecked += (int) ($flow['total'] ?? 0);
            $totalOk += (int) ($flow['ok'] ?? 0);
            $totalGaps += (int) ($flow['gaps'] ?? 0);
        }

        return [
            'flows' => $flows,
            'summary' => [
                'total_checked' => $totalChecked,
                'ok' => $totalOk,
                'gaps' => $totalGaps,
            ],
            'rules' => [
                'leads' => 'tablas_leads + eventos_wp (created_time en periodo)',
                'agendas' => 'consulta_agendados + merge WP/clientes; estatus agendado/fantasma/muerto/cliente/atendido',
                'atendidos' => 'post-leads + eventos_wp + clientes; estatus atendido/cliente/muerto',
                'clientes' => 'contact_form.cliente=1; fecha_cambio_cliente en periodo',
                'exclusiones' => 'wedding_planners excluido; how_did_you_meet=1+cliente excluido en comercial; agendado excluido en post-leads',
            ],
        ];
    }
}

if (!function_exists('dashValidarRenderStageCell')) {
    function dashValidarRenderStageCell($expected, $flag)
    {
        if (empty($expected)) {
            return '—';
        }
        if ($flag === true) {
            return 'OK';
        }

        return 'FALTA';
    }
}
