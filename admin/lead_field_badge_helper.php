<?php

require_once __DIR__ . '/campaign_badge_helper.php';

if (!function_exists('getDefaultFieldBadgeColors')) {
    function getDefaultFieldBadgeColors()
    {
        return [
            'bg' => 'rgba(148,163,184,0.12)',
            'border' => 'rgba(148,163,184,0.28)',
            'color' => '#94a3b8',
        ];
    }
}

if (!function_exists('getFieldBadgeStyleAttr')) {
    function getFieldBadgeStyleAttr(array $colors)
    {
        return sprintf(
            'background:%s;border:1px solid %s;color:%s;',
            $colors['bg'],
            $colors['border'],
            $colors['color']
        );
    }
}

if (!function_exists('normalizeContactMethodColorKey')) {
    function normalizeContactMethodColorKey($label)
    {
        $key = mb_strtolower(trim((string) $label), 'UTF-8');
        $key = str_replace(['–', '—'], '-', $key);
        $key = preg_replace('/\s+/', ' ', $key);

        if ($key === '' || $key === '—' || $key === '-' || $key === 'n/a' || $key === 'sin dato' || $key === 'not asked') {
            return '__empty__';
        }

        $aliases = [
            'whatsapp' => 'whatsapp',
            'instagram dm - campaign' => 'instagram dm - campaña',
            'instagram dm campaign' => 'instagram dm - campaña',
            'instagram dm - organic' => 'instagram dm - orgánico',
            'instagram dm organic' => 'instagram dm - orgánico',
            'ig campaign' => 'instagram dm - campaña',
            'ig organic' => 'instagram dm - orgánico',
            'ig' => 'ig',
            'email' => 'correo electrónico',
            'correo electronico' => 'correo electrónico',
            'correo electrónico' => 'correo electrónico',
            'mail' => 'correo electrónico',
            'phone call' => 'llamada telefónica',
            'llamada telefonica' => 'llamada telefónica',
            'llamada telefónica' => 'llamada telefónica',
            'tiktok' => 'tiktok',
            'facebook' => 'facebook',
            'fb' => 'facebook',
            'website' => 'website',
            'leadform' => 'leadform',
        ];

        return $aliases[$key] ?? $key;
    }
}

if (!function_exists('getContactMethodBadgeColorOverrides')) {
    function getContactMethodBadgeColorOverrides()
    {
        return [
            'whatsapp' => ['bg' => 'rgba(37,211,102,0.14)', 'border' => 'rgba(37,211,102,0.32)', 'color' => '#16a34a'],
            'instagram dm - campaña' => ['bg' => 'rgba(236,72,153,0.14)', 'border' => 'rgba(236,72,153,0.32)', 'color' => '#db2777'],
            'instagram dm - orgánico' => ['bg' => 'rgba(168,85,247,0.14)', 'border' => 'rgba(168,85,247,0.32)', 'color' => '#9333ea'],
            'ig' => ['bg' => 'rgba(131,58,180,0.14)', 'border' => 'rgba(131,58,180,0.32)', 'color' => '#833ab4'],
            'correo electrónico' => ['bg' => 'rgba(59,130,246,0.14)', 'border' => 'rgba(59,130,246,0.32)', 'color' => '#2563eb'],
            'llamada telefónica' => ['bg' => 'rgba(245,158,11,0.14)', 'border' => 'rgba(245,158,11,0.32)', 'color' => '#d97706'],
            'tiktok' => ['bg' => 'rgba(6,182,212,0.14)', 'border' => 'rgba(6,182,212,0.32)', 'color' => '#0891b2'],
            'facebook' => ['bg' => 'rgba(24,119,242,0.14)', 'border' => 'rgba(24,119,242,0.32)', 'color' => '#1877f2'],
            'website' => ['bg' => 'rgba(16,185,129,0.14)', 'border' => 'rgba(16,185,129,0.32)', 'color' => '#059669'],
            'leadform' => ['bg' => 'rgba(99,102,241,0.14)', 'border' => 'rgba(99,102,241,0.32)', 'color' => '#4f46e5'],
            '__empty__' => getDefaultFieldBadgeColors(),
        ];
    }
}

if (!function_exists('getContactMethodBadgeColors')) {
    function getContactMethodBadgeColors($normalizedLabel)
    {
        $key = normalizeContactMethodColorKey($normalizedLabel);
        $overrides = getContactMethodBadgeColorOverrides();

        return $overrides[$key] ?? getDefaultFieldBadgeColors();
    }
}

if (!function_exists('getContactMethodBadgeStyleAttr')) {
    function getContactMethodBadgeStyleAttr($normalizedLabel)
    {
        return getFieldBadgeStyleAttr(getContactMethodBadgeColors($normalizedLabel));
    }
}

if (!function_exists('renderContactMethodBadge')) {
    function renderContactMethodBadge($normalizedLabel, $displayLabel = null)
    {
        $normalized = trim((string) $normalizedLabel);
        if ($normalized === '') {
            $normalized = 'Sin dato';
        }

        $label = $displayLabel !== null ? trim((string) $displayLabel) : $normalized;
        if ($label === '') {
            $label = '—';
        }

        return '<span class="badge-contact-method" style="' . htmlspecialchars(getContactMethodBadgeStyleAttr($normalized), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
}

if (!function_exists('normalizeTipoClienteColorKey')) {
    function normalizeTipoClienteColorKey($label)
    {
        $key = mb_strtolower(trim((string) $label), 'UTF-8');

        if ($key === '' || $key === '—' || $key === '-' || $key === 'n/a' || $key === 'sin dato') {
            return '__empty__';
        }

        if ($key === '1' || $key === 'wedding planner') {
            return 'wedding planner';
        }

        if ($key === '0' || $key === 'cliente final') {
            return 'cliente final';
        }

        return $key;
    }
}

if (!function_exists('getTipoClienteBadgeColorOverrides')) {
    function getTipoClienteBadgeColorOverrides()
    {
        return [
            'wedding planner' => ['bg' => 'rgba(168,85,247,0.14)', 'border' => 'rgba(168,85,247,0.32)', 'color' => '#9333ea'],
            'cliente final' => ['bg' => 'rgba(59,130,246,0.14)', 'border' => 'rgba(59,130,246,0.32)', 'color' => '#2563eb'],
            '__empty__' => getDefaultFieldBadgeColors(),
        ];
    }
}

if (!function_exists('getTipoClienteBadgeColors')) {
    function getTipoClienteBadgeColors($label)
    {
        $key = normalizeTipoClienteColorKey($label);
        $overrides = getTipoClienteBadgeColorOverrides();

        return $overrides[$key] ?? getDefaultFieldBadgeColors();
    }
}

if (!function_exists('getTipoClienteBadgeStyleAttr')) {
    function getTipoClienteBadgeStyleAttr($label)
    {
        return getFieldBadgeStyleAttr(getTipoClienteBadgeColors($label));
    }
}

if (!function_exists('renderTipoClienteBadge')) {
    function renderTipoClienteBadge($label)
    {
        $display = trim((string) $label);
        if ($display === '') {
            $display = '—';
        }

        return '<span class="badge-tipo-cliente" style="' . htmlspecialchars(getTipoClienteBadgeStyleAttr($display), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($display, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
}

if (!function_exists('renderLeadFieldBadgeJsFunctions')) {
    function renderLeadFieldBadgeJsFunctions()
    {
        return <<<'JS'
function normalizeContactMethodColorKey(label) {
    var key = (label || '').trim().toLowerCase().replace(/[–—]/g, '-').replace(/\s+/g, ' ');
    if (!key || key === '—' || key === '-' || key === 'n/a' || key === 'sin dato' || key === 'not asked') {
        return '__empty__';
    }
    var aliases = {
        'whatsapp': 'whatsapp',
        'instagram dm - campaign': 'instagram dm - campaña',
        'instagram dm campaign': 'instagram dm - campaña',
        'instagram dm - organic': 'instagram dm - orgánico',
        'instagram dm organic': 'instagram dm - orgánico',
        'ig campaign': 'instagram dm - campaña',
        'ig organic': 'instagram dm - orgánico',
        'ig': 'ig',
        'email': 'correo electrónico',
        'correo electronico': 'correo electrónico',
        'correo electrónico': 'correo electrónico',
        'mail': 'correo electrónico',
        'phone call': 'llamada telefónica',
        'llamada telefonica': 'llamada telefónica',
        'llamada telefónica': 'llamada telefónica',
        'tiktok': 'tiktok',
        'facebook': 'facebook',
        'fb': 'facebook',
        'website': 'website',
        'leadform': 'leadform'
    };
    return Object.prototype.hasOwnProperty.call(aliases, key) ? aliases[key] : key;
}

function getContactMethodBadgeColors(normalizedLabel) {
    var key = normalizeContactMethodColorKey(normalizedLabel);
    if (window.CONTACT_METHOD_BADGE_OVERRIDES && window.CONTACT_METHOD_BADGE_OVERRIDES[key]) {
        return window.CONTACT_METHOD_BADGE_OVERRIDES[key];
    }
    return window.DEFAULT_FIELD_BADGE_COLORS || { bg: 'rgba(148,163,184,0.12)', border: 'rgba(148,163,184,0.28)', color: '#94a3b8' };
}

function getContactMethodBadgeStyleAttr(normalizedLabel) {
    var colors = getContactMethodBadgeColors(normalizedLabel);
    return 'background:' + colors.bg + ';border:1px solid ' + colors.border + ';color:' + colors.color + ';';
}

function jsRenderContactMethodBadge(normalizedLabel, displayLabel) {
    var normalized = (normalizedLabel || '').trim() || 'Sin dato';
    var label = (displayLabel !== undefined && displayLabel !== null) ? String(displayLabel).trim() : normalized;
    if (!label) {
        label = '—';
    }
    return '<span class="badge-contact-method" style="' + getContactMethodBadgeStyleAttr(normalized) + '">' + escapeHtml(label) + '</span>';
}

function normalizeTipoClienteColorKey(label) {
    var key = (label || '').trim().toLowerCase();
    if (!key || key === '—' || key === '-' || key === 'n/a' || key === 'sin dato') {
        return '__empty__';
    }
    if (key === '1' || key === 'wedding planner') {
        return 'wedding planner';
    }
    if (key === '0' || key === 'cliente final') {
        return 'cliente final';
    }
    return key;
}

function getTipoClienteBadgeColors(label) {
    var key = normalizeTipoClienteColorKey(label);
    if (window.TIPO_CLIENTE_BADGE_OVERRIDES && window.TIPO_CLIENTE_BADGE_OVERRIDES[key]) {
        return window.TIPO_CLIENTE_BADGE_OVERRIDES[key];
    }
    return window.DEFAULT_FIELD_BADGE_COLORS || { bg: 'rgba(148,163,184,0.12)', border: 'rgba(148,163,184,0.28)', color: '#94a3b8' };
}

function getTipoClienteBadgeStyleAttr(label) {
    var colors = getTipoClienteBadgeColors(label);
    return 'background:' + colors.bg + ';border:1px solid ' + colors.border + ';color:' + colors.color + ';';
}

function jsRenderTipoClienteBadge(label) {
    var display = (label || '').trim() || '—';
    return '<span class="badge-tipo-cliente" style="' + getTipoClienteBadgeStyleAttr(display) + '">' + escapeHtml(display) + '</span>';
}
JS;
    }
}

if (!function_exists('renderLeadFieldBadgeJsSnippet')) {
    function renderLeadFieldBadgeJsSnippet()
    {
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
        $defaultColors = getDefaultFieldBadgeColors();

        return '<script>'
            . 'window.DEFAULT_FIELD_BADGE_COLORS = ' . json_encode($defaultColors, $jsonFlags) . ';'
            . 'window.CONTACT_METHOD_BADGE_OVERRIDES = ' . json_encode(getContactMethodBadgeColorOverrides(), $jsonFlags) . ';'
            . 'window.TIPO_CLIENTE_BADGE_OVERRIDES = ' . json_encode(getTipoClienteBadgeColorOverrides(), $jsonFlags) . ';'
            . renderLeadFieldBadgeJsFunctions()
            . '</script>';
    }
}
