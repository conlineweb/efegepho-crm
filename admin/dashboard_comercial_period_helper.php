<?php

if (!function_exists('dashComercialGetMinPeriodDate')) {
    function dashComercialGetMinPeriodDate()
    {
        return '2026-06-15';
    }
}

if (!function_exists('dashComercialNormalizePeriodDates')) {
    /**
     * Ajusta fechas al rango válido: no antes del 15/jun/2026.
     *
     * @return array{0: string, 1: string}
     */
    function dashComercialNormalizePeriodDates($startDate, $endDate)
    {
        $min = dashComercialGetMinPeriodDate();
        $minTs = strtotime($min);

        $startDate = trim((string) $startDate);
        $endDate = trim((string) $endDate);

        if ($startDate !== '') {
            $ts = strtotime($startDate);
            $startDate = ($ts === false || $ts < $minTs) ? $min : date('Y-m-d', $ts);
        }
        if ($endDate !== '') {
            $ts = strtotime($endDate);
            $endDate = ($ts === false || $ts < $minTs) ? $min : date('Y-m-d', $ts);
        }
        if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
            $endDate = $startDate;
        }

        return [$startDate, $endDate];
    }
}

if (!function_exists('dashComercialResolvePeriodDates')) {
    /**
     * Resuelve el periodo del dashboard. Sin fechas en GET: del 15/jun/2026 a hoy.
     *
     * @return array{0: string, 1: string}
     */
    function dashComercialResolvePeriodDates($startDate, $endDate, array $options = [])
    {
        $startDate = trim((string) $startDate);
        $endDate = trim((string) $endDate);

        if ($startDate === '' && $endDate === '') {
            $endDate = date('Y-m-d');
            $startDate = dashComercialGetMinPeriodDate();
        }

        return dashComercialNormalizePeriodDates($startDate, $endDate);
    }
}
