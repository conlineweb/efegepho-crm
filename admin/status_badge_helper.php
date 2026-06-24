<?php

if (!function_exists('normalizeLeadStatusKey')) {
    function normalizeLeadStatusKey($raw)
    {
        $key = mb_strtolower(trim((string) $raw), 'UTF-8');
        if ($key === '' || $key === '—' || $key === '-') {
            return '';
        }

        return $key;
    }
}

if (!function_exists('getLeadStatusBadgeClass')) {
    function getLeadStatusBadgeClass($statusKeyOrLabel)
    {
        $key = normalizeLeadStatusKey($statusKeyOrLabel);

        switch ($key) {
            case 'agendado':
                return 'status status-scheduled';
            case 'atendido':
                return 'status status-attended';
            case 'cliente':
                return 'status status-closed';
            case 'fantasma':
                return 'status status-pending';
            case 'muerto':
                return 'status status-danger';
            case 'lead':
                return 'status status-pending';
            default:
                return 'status status-pending';
        }
    }
}

if (!function_exists('getLeadStatusDisplayLabel')) {
    function getLeadStatusDisplayLabel($statusKeyOrLabel)
    {
        $key = normalizeLeadStatusKey($statusKeyOrLabel);
        if ($key === '') {
            return '—';
        }

        return ucfirst($key);
    }
}

if (!function_exists('renderLeadStatusBadge')) {
    function renderLeadStatusBadge($statusKeyOrLabel, $displayLabel = null)
    {
        $display = $displayLabel !== null ? trim((string) $displayLabel) : getLeadStatusDisplayLabel($statusKeyOrLabel);
        if ($display === '') {
            $display = '—';
        }

        $class = getLeadStatusBadgeClass($statusKeyOrLabel);

        return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($display, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
}

if (!function_exists('getLeadStatusBadgeCss')) {
    function getLeadStatusBadgeCss()
    {
        return <<<'CSS'
.status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.status-scheduled { background: rgba(59,130,246,0.12); color: #3B82F6; }
.status-attended { background: rgba(16,185,129,0.12); color: #10B981; }
.status-pending { background: rgba(245,158,11,0.12); color: #F59E0B; }
.status-closed { background: rgba(197,160,40,0.15); color: #C5A028; }
.status-danger { background: rgba(220,38,38,0.12); color: #DC2626; }
CSS;
    }
}

if (!function_exists('renderLeadStatusBadgeJsFunctions')) {
    function renderLeadStatusBadgeJsFunctions()
    {
        return <<<'JS'
function normalizeLeadStatusKey(raw) {
    var key = (raw || '').trim().toLowerCase();
    if (!key || key === '—' || key === '-') {
        return '';
    }
    return key;
}

function getLeadStatusBadgeClass(statusKeyOrLabel) {
    var key = normalizeLeadStatusKey(statusKeyOrLabel);
    switch (key) {
        case 'agendado':
            return 'status status-scheduled';
        case 'atendido':
            return 'status status-attended';
        case 'cliente':
            return 'status status-closed';
        case 'fantasma':
            return 'status status-pending';
        case 'muerto':
            return 'status status-danger';
        case 'lead':
            return 'status status-pending';
        default:
            return 'status status-pending';
    }
}

function jsRenderLeadStatusBadge(statusKeyOrLabel, displayLabel) {
    var display = (displayLabel !== undefined && displayLabel !== null)
        ? String(displayLabel).trim()
        : (statusKeyOrLabel ? String(statusKeyOrLabel).trim() : '');
    if (!display) {
        display = '—';
    }
    var cls = getLeadStatusBadgeClass(statusKeyOrLabel || display);
    return '<span class="' + cls + '">' + escapeHtml(display) + '</span>';
}
JS;
    }
}
