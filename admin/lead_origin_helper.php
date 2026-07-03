<?php

if (!function_exists('mapKnownUsToHowDidYouMeetCode')) {
    function mapKnownUsToHowDidYouMeetCode($howLongKnownUs)
    {
        $knownUs = mb_strtolower(trim((string) $howLongKnownUs), 'UTF-8');
        if (in_array($knownUs, ['less than 6 months', 'less than 3 months', 'between 3 months and 1 year'], true)) {
            return '3'; // New Audience
        }
        if (in_array($knownUs, ['more than 6 months', 'more than 1 year'], true)) {
            return '2'; // Community
        }

        return '';
    }
}

if (!function_exists('mapTipoClienteValueForOrigin')) {
    function mapTipoClienteValueForOrigin($raw)
    {
        $value = trim((string) $raw);
        if ($value === '1' || strcasecmp($value, 'Wedding Planner') === 0) {
            return 'Wedding Planner';
        }
        if ($value === '0' || strcasecmp($value, 'Cliente Final') === 0) {
            return 'Cliente Final';
        }

        return '';
    }
}

if (!function_exists('isOriginDrivenByKnownUs')) {
    /**
     * Registro manual o formulario website (Cliente Final): el origen lo define
     * "¿Desde hace cuánto nos conoce?" y debe prevalecer sobre how_did_you_meet almacenado.
     */
    function isOriginDrivenByKnownUs(array $lead)
    {
        $tabla = mb_strtolower(trim((string) ($lead['tabla_origen'] ?? '')), 'UTF-8');
        if (in_array($tabla, ['wedding_planners', 'wedding_planner', 'eventos_wp', 'wp_eventos_afianzados', 'wp_citas_leads'], true)) {
            return false;
        }

        if (mapTipoClienteValueForOrigin($lead['tipo_cliente'] ?? '') === 'Wedding Planner') {
            return false;
        }

        $fcc = mb_strtolower(trim((string) ($lead['first_contact_channel'] ?? '')), 'UTF-8');
        $campaign = mb_strtolower(trim((string) ($lead['campaign_name'] ?? '')), 'UTF-8');
        $tipoIg = mb_strtolower(trim((string) ($lead['tipo_ig'] ?? '')), 'UTF-8');
        $leadStatus = mb_strtolower(trim((string) ($lead['lead_status'] ?? '')), 'UTF-8');

        // Campañas digitales: el origen lo fija el canal, no la antigüedad.
        if ($fcc === 'leadform') {
            return false;
        }
        if ($fcc === 'ig' && $tipoIg === 'campana') {
            return false;
        }

        if (in_array($fcc, ['website', 'mail', 'email', 'whatsapp', 'phone call', 'phone', 'llamada telefónica'], true)) {
            return true;
        }
        if (in_array($campaign, ['reg manual', 'website'], true)) {
            return true;
        }
        if ($leadStatus === 'manual') {
            return true;
        }
        if (mapTipoClienteValueForOrigin($lead['tipo_cliente'] ?? '') === 'Cliente Final'
            && mapKnownUsToHowDidYouMeetCode($lead['how_long_known_us'] ?? '') !== '') {
            return true;
        }

        return false;
    }
}

if (!function_exists('resolveHowDidYouMeetCode')) {
    function resolveHowDidYouMeetCode($howRaw, $howLongKnownUs = '', $lead = null)
    {
        $mappedFromKnownUs = mapKnownUsToHowDidYouMeetCode($howLongKnownUs);

        if (is_array($lead) && isOriginDrivenByKnownUs($lead) && $mappedFromKnownUs !== '') {
            return $mappedFromKnownUs;
        }

        $howRaw = trim((string) $howRaw);
        if ($howRaw !== '') {
            return $howRaw;
        }

        return $mappedFromKnownUs;
    }
}

if (!function_exists('enrichLeadHowLongKnownUsFromContactForm')) {
    function enrichLeadHowLongKnownUsFromContactForm(array &$lead, $contactFormRow = null)
    {
        if (!is_array($contactFormRow)) {
            return;
        }

        foreach (['how_long_known_us', 'first_contact_channel', 'tipo_cliente'] as $field) {
            if (trim((string) ($lead[$field] ?? '')) !== '') {
                continue;
            }
            $value = trim((string) ($contactFormRow[$field] ?? ''));
            if ($value !== '') {
                $lead[$field] = $value;
            }
        }
    }
}

if (!function_exists('applyResolvedHowDidYouMeetToLead')) {
    function applyResolvedHowDidYouMeetToLead(array &$lead, $contactFormRow = null)
    {
        enrichLeadHowLongKnownUsFromContactForm($lead, $contactFormRow);
        $resolved = resolveHowDidYouMeetCode(
            $lead['how_did_you_meet'] ?? '',
            $lead['how_long_known_us'] ?? '',
            $lead
        );
        if ($resolved !== '') {
            $lead['how_did_you_meet'] = $resolved;
        }
    }
}

if (!function_exists('deriveHowDidYouMeetFromKnownUsAtSave')) {
    function deriveHowDidYouMeetFromKnownUsAtSave($howDidYouMeet, $howLongKnownUs, $firstContactChannel = '', $tipoCliente = null)
    {
        $lead = [
            'how_did_you_meet' => $howDidYouMeet,
            'how_long_known_us' => $howLongKnownUs,
            'first_contact_channel' => $firstContactChannel,
            'tipo_cliente' => $tipoCliente,
        ];

        return resolveHowDidYouMeetCode($howDidYouMeet, $howLongKnownUs, $lead);
    }
}

if (!function_exists('isWpPlannerLeadTable')) {
    function isWpPlannerLeadTable($tablaOrigen)
    {
        $tabla = strtolower(trim((string) $tablaOrigen));

        return in_array($tabla, ['wedding_planners', 'wedding_planner', 'wp_citas_leads'], true);
    }
}

if (!function_exists('isWpEventLeadTable')) {
    function isWpEventLeadTable($tablaOrigen)
    {
        $tabla = strtolower(trim((string) $tablaOrigen));

        return in_array($tabla, ['eventos_wp', 'wp_eventos_afianzados'], true);
    }
}

if (!function_exists('inferTipoClienteFromOriginData')) {
    function inferTipoClienteFromOriginData($howDidYouMeet, $tablaOrigen)
    {
        $tabla = strtolower(trim((string) $tablaOrigen));

        if (isWpPlannerLeadTable($tabla)) {
            return 'Wedding Planner';
        }

        $how = trim((string) $howDidYouMeet);
        if ($how === '1') {
            return 'Wedding Planner';
        }

        if (isWpEventLeadTable($tabla)) {
            return 'Cliente Final';
        }

        if (in_array($how, ['2', '3'], true)) {
            return 'Cliente Final';
        }

        return '';
    }
}

if (!function_exists('getOrigenCategoriaLabel')) {
    function getOrigenCategoriaLabel($lead)
    {
        $howRaw = resolveHowDidYouMeetCode(
            $lead['how_did_you_meet'] ?? '',
            $lead['how_long_known_us'] ?? '',
            $lead
        );
        $tablaOrigen = strtolower(trim((string) ($lead['tabla_origen'] ?? '')));
        $howMap = [
            '1' => 'Wedding Planner',
            '2' => 'Community',
            '3' => 'New Audience',
        ];

        if (isWpPlannerLeadTable($tablaOrigen)) {
            return 'Wedding Planner';
        }

        if (isWpEventLeadTable($tablaOrigen)) {
            $howStored = trim((string) ($lead['how_did_you_meet_raw'] ?? ($lead['how_did_you_meet'] ?? $howRaw)));
            if ($howStored === '' || $howStored === '1') {
                return 'Wedding Planner';
            }
            $howRaw = $howStored;
        }

        if ($howRaw !== '' && isset($howMap[$howRaw])) {
            return $howMap[$howRaw];
        }

        return 'N/A';
    }
}

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

if (!function_exists('resolveFirstContactChannelForLead')) {
    /**
     * Resuelve el método de contacto inicial (canal + inferencia por campaña/plataforma).
     */
    function resolveFirstContactChannelForLead(array $lead, ?array $originalLead = null)
    {
        $cfFcc = trim((string) ($lead['first_contact_channel'] ?? ''));
        $origFcc = $originalLead ? trim((string) ($originalLead['first_contact_channel'] ?? '')) : '';
        $origLower = mb_strtolower($origFcc, 'UTF-8');

        if ($origFcc !== '' && $origLower !== 'whatsapp') {
            return $origFcc;
        }

        $campaign = trim((string) ($lead['campaign_name'] ?? ($originalLead['campaign_name'] ?? '')));
        $platform = mb_strtolower(trim((string) ($lead['platform'] ?? ($originalLead['platform'] ?? ''))), 'UTF-8');
        $tipoIg = mb_strtolower(trim((string) ($lead['tipo_ig'] ?? ($originalLead['tipo_ig'] ?? ''))), 'UTF-8');
        $campaignLower = mb_strtolower($campaign, 'UTF-8');

        if (isB1B2CampaignName($campaign)) {
            return 'IG';
        }
        if (in_array($platform, ['ig', 'ig usa', 'ig mexico'], true)) {
            return 'IG';
        }
        if ($campaignLower === 'ig organico') {
            return 'IG';
        }
        if ($tipoIg === 'campana' || $tipoIg === 'organico') {
            return 'IG';
        }
        if ($origLower === 'ig' || strpos($origLower, 'instagram') !== false) {
            return $origFcc !== '' ? $origFcc : 'IG';
        }

        if ($origFcc !== '') {
            return $origFcc;
        }

        $cfLower = mb_strtolower($cfFcc, 'UTF-8');
        if ($cfLower === 'ig' || strpos($cfLower, 'instagram') !== false) {
            return $cfFcc;
        }

        return $cfFcc;
    }
}
