<?php

if (!function_exists('normalizeCampaignColorKey')) {
    function normalizeCampaignColorKey($label)
    {
        $key = mb_strtolower(trim((string) $label), 'UTF-8');
        if ($key === '' || $key === '—' || $key === '-' || $key === 'n/a' || $key === 'sin dato') {
            return '__empty__';
        }
        return $key;
    }
}

if (!function_exists('campaignHashValue')) {
    function campaignHashValue($key)
    {
        $hash = 5381;
        $len = strlen($key);
        for ($i = 0; $i < $len; $i++) {
            $hash = (($hash << 5) + $hash + ord($key[$i]));
            $hash &= 0xFFFFFFFF;
        }
        return $hash & 0x7FFFFFFF;
    }
}

if (!function_exists('getCampaignHueFromKey')) {
    function getCampaignHueFromKey($key)
    {
        return campaignHashValue($key) % 360;
    }
}

if (!function_exists('getCampaignBadgeColorOverrides')) {
    function getCampaignBadgeColorOverrides()
    {
        return [
            'website - orgánico' => ['bg' => 'rgba(16,185,129,0.14)', 'border' => 'rgba(16,185,129,0.32)', 'color' => '#059669'],
            'website - organico' => ['bg' => 'rgba(16,185,129,0.14)', 'border' => 'rgba(16,185,129,0.32)', 'color' => '#059669'],
            'website - google ads' => ['bg' => 'rgba(245,158,11,0.14)', 'border' => 'rgba(245,158,11,0.32)', 'color' => '#d97706'],
            'ig - orgánico' => ['bg' => 'rgba(236,72,153,0.14)', 'border' => 'rgba(236,72,153,0.32)', 'color' => '#db2777'],
            'ig - organico' => ['bg' => 'rgba(236,72,153,0.14)', 'border' => 'rgba(236,72,153,0.32)', 'color' => '#db2777'],
            'mail - orgánico' => ['bg' => 'rgba(100,116,139,0.14)', 'border' => 'rgba(100,116,139,0.32)', 'color' => '#475569'],
            'mail - organico' => ['bg' => 'rgba(100,116,139,0.14)', 'border' => 'rgba(100,116,139,0.32)', 'color' => '#475569'],
            'whatsapp - orgánico' => ['bg' => 'rgba(37,211,102,0.14)', 'border' => 'rgba(37,211,102,0.32)', 'color' => '#16a34a'],
            'whatsapp - organico' => ['bg' => 'rgba(37,211,102,0.14)', 'border' => 'rgba(37,211,102,0.32)', 'color' => '#16a34a'],
            'cierres retroactivos' => ['bg' => 'rgba(148,163,184,0.16)', 'border' => 'rgba(148,163,184,0.34)', 'color' => '#64748b'],
            'ig - b2 (mx)' => ['bg' => 'rgba(59,130,246,0.14)', 'border' => 'rgba(59,130,246,0.32)', 'color' => '#2563eb'],
            'ig - b1 (mx)' => ['bg' => 'rgba(99,102,241,0.14)', 'border' => 'rgba(99,102,241,0.32)', 'color' => '#4f46e5'],
            'ig - b1' => ['bg' => 'rgba(99,102,241,0.14)', 'border' => 'rgba(99,102,241,0.32)', 'color' => '#4f46e5'],
            'ig - b2' => ['bg' => 'rgba(59,130,246,0.14)', 'border' => 'rgba(59,130,246,0.32)', 'color' => '#2563eb'],
            '__empty__' => ['bg' => 'rgba(148,163,184,0.12)', 'border' => 'rgba(148,163,184,0.28)', 'color' => '#94a3b8'],
        ];
    }
}

if (!function_exists('getHslCampaignBadgeColors')) {
    function getHslCampaignBadgeColors($hue)
    {
        $hue = (int) $hue % 360;
        $satBg = 68;
        $satText = 58;
        $lightBg = 44;
        $lightBorder = 38;
        $lightText = 30;

        return [
            'bg' => "hsla($hue, {$satBg}%, {$lightBg}%, 0.15)",
            'border' => "hsla($hue, {$satBg}%, {$lightBorder}%, 0.38)",
            'color' => "hsl($hue, {$satText}%, {$lightText}%)",
        ];
    }
}

if (!function_exists('getCampaignBadgeColors')) {
    function getCampaignBadgeColors($displayLabel)
    {
        $key = normalizeCampaignColorKey($displayLabel);
        $overrides = getCampaignBadgeColorOverrides();
        if (isset($overrides[$key])) {
            return $overrides[$key];
        }

        return getHslCampaignBadgeColors(getCampaignHueFromKey($key));
    }
}

if (!function_exists('getCampaignBadgeStyleAttr')) {
    function getCampaignBadgeStyleAttr($displayLabel)
    {
        $colors = getCampaignBadgeColors($displayLabel);
        return sprintf(
            'background:%s;border:1px solid %s;color:%s;',
            $colors['bg'],
            $colors['border'],
            $colors['color']
        );
    }
}

if (!function_exists('getCampaignBreakdownBarStyleAttr')) {
    function getCampaignBreakdownBarStyleAttr($displayLabel)
    {
        $colors = getCampaignBadgeColors($displayLabel);
        $solid = $colors['color'];

        if (preg_match('/^hsl\((\d+),\s*([\d.]+)%,\s*([\d.]+)%\)$/', $solid, $matches)) {
            return sprintf(
                'background:linear-gradient(90deg,hsla(%d,%s%%,%s%%,0.85),hsla(%d,%s%%,%s%%,0.55));',
                $matches[1],
                $matches[2],
                $matches[3],
                $matches[1],
                $matches[2],
                $matches[3]
            );
        }

        if (strpos($solid, '#') === 0 && strlen($solid) === 7) {
            return 'background:linear-gradient(90deg,' . $solid . 'd9,' . $solid . '99);';
        }

        return 'background:' . $colors['border'] . ';';
    }
}

if (!function_exists('renderCampaignBadge')) {
    function renderCampaignBadge($displayLabel)
    {
        $label = trim((string) $displayLabel);
        if ($label === '') {
            $label = '—';
        }

        return '<span class="badge-campaign" style="' . htmlspecialchars(getCampaignBadgeStyleAttr($label), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
}

if (!function_exists('renderCampaignBadgeJsSnippet')) {
    function renderCampaignBadgeJsFunctions()
    {
        return <<<'JS'
function normalizeCampaignColorKey(label) {
    var key = (label || '').trim().toLowerCase();
    if (!key || key === '—' || key === '-' || key === 'n/a' || key === 'sin dato') {
        return '__empty__';
    }
    return key;
}

function campaignHashValue(key) {
    var hash = 5381;
    for (var i = 0; i < key.length; i++) {
        hash = ((hash << 5) + hash + key.charCodeAt(i)) >>> 0;
        hash &= 0x7FFFFFFF;
    }
    return hash;
}

function getCampaignHueFromKey(key) {
    return campaignHashValue(key) % 360;
}

function getHslCampaignBadgeColors(hue) {
    hue = ((hue % 360) + 360) % 360;
    return {
        bg: 'hsla(' + hue + ', 68%, 44%, 0.15)',
        border: 'hsla(' + hue + ', 68%, 38%, 0.38)',
        color: 'hsl(' + hue + ', 58%, 30%)'
    };
}

function getCampaignBadgeColors(displayLabel) {
    var key = normalizeCampaignColorKey(displayLabel);
    if (window.CAMPAIGN_BADGE_OVERRIDES && window.CAMPAIGN_BADGE_OVERRIDES[key]) {
        return window.CAMPAIGN_BADGE_OVERRIDES[key];
    }
    return getHslCampaignBadgeColors(getCampaignHueFromKey(key));
}

function getCampaignBadgeStyleAttr(displayLabel) {
    var colors = getCampaignBadgeColors(displayLabel);
    return 'background:' + colors.bg + ';border:1px solid ' + colors.border + ';color:' + colors.color + ';';
}

function jsRenderCampaignBadge(displayLabel) {
    var label = (displayLabel || '').trim();
    if (!label) {
        label = '—';
    }
    return '<span class="badge-campaign" style="' + getCampaignBadgeStyleAttr(label) + '">' + escapeHtml(label) + '</span>';
}
JS;
    }

    function renderCampaignBadgeJsSnippet()
    {
        $overrides = getCampaignBadgeColorOverrides();
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

        return '<script>'
            . 'window.CAMPAIGN_BADGE_OVERRIDES = ' . json_encode($overrides, $jsonFlags) . ';'
            . renderCampaignBadgeJsFunctions()
            . '</script>';
    }
}

// Compatibilidad con vistas que aún inyectan la paleta antigua.
if (!function_exists('getCampaignBadgePalette')) {
    function getCampaignBadgePalette()
    {
        return [];
    }
}
