<?php

if (!defined('USUARIO_ROL_ADMIN')) {
    define('USUARIO_ROL_ADMIN', 0);
    define('USUARIO_ROL_VENDEDOR', 1);
    define('USUARIO_ROL_GESTOR_GALERIA', 2);
    define('USUARIO_ROL_GESTOR_PAGOS', 3);
    define('USUARIO_ROL_MARKETING', 4);
    define('USUARIO_ROL_LIDER_PLANNERS', 5);
}

if (!function_exists('usuarioTipoEsAdminLike')) {
    function usuarioTipoEsAdminLike($tipoUsu): bool
    {
        $tipoUsu = (int) $tipoUsu;
        return $tipoUsu === USUARIO_ROL_ADMIN || $tipoUsu === USUARIO_ROL_LIDER_PLANNERS;
    }
}

if (!function_exists('usuarioRolLabel')) {
    function usuarioRolLabel($tipoUsu): string
    {
        $map = [
            USUARIO_ROL_ADMIN => 'Admin',
            USUARIO_ROL_VENDEDOR => 'Vendedor',
            USUARIO_ROL_GESTOR_GALERIA => 'Gestor de galería',
            USUARIO_ROL_GESTOR_PAGOS => 'Gestor de pagos',
            USUARIO_ROL_MARKETING => 'Marketing',
            USUARIO_ROL_LIDER_PLANNERS => 'Líder de Planners',
        ];

        return $map[(int) $tipoUsu] ?? 'Desconocido';
    }
}

if (!function_exists('usuarioTipoPuedeAsignarSesionWp')) {
    function usuarioTipoPuedeAsignarSesionWp($tipoUsu): bool
    {
        return (int) $tipoUsu === USUARIO_ROL_LIDER_PLANNERS;
    }
}

if (!function_exists('usuarioSqlInTiposAsignacionSesionWp')) {
    function usuarioSqlInTiposAsignacionSesionWp(): string
    {
        return (string) USUARIO_ROL_LIDER_PLANNERS;
    }
}
