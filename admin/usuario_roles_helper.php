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
        return usuarioTipoEsAsesorEventoWp($tipoUsu);
    }
}

if (!function_exists('usuarioSqlInTiposAsignacionSesionWp')) {
    function usuarioSqlInTiposAsignacionSesionWp(): string
    {
        return usuarioSqlInTiposAsesorEventoWp();
    }
}

if (!function_exists('usuarioSqlInTiposAsesorEventoWp')) {
    function usuarioSqlInTiposAsesorEventoWp(): string
    {
        return USUARIO_ROL_VENDEDOR . ',' . USUARIO_ROL_LIDER_PLANNERS;
    }
}

if (!function_exists('usuarioTipoEsAsesorEventoWp')) {
    function usuarioTipoEsAsesorEventoWp($tipoUsu): bool
    {
        $tipoUsu = (int) $tipoUsu;

        return $tipoUsu === USUARIO_ROL_VENDEDOR || $tipoUsu === USUARIO_ROL_LIDER_PLANNERS;
    }
}

if (!function_exists('usuarioTipoVeTodoEnVistasComerciales')) {
    /**
     * Vendedor (1) y gestor de galería (2): ven todas las cuentas en My Lead Board y consulta WP.
     */
    function usuarioTipoVeTodoEnVistasComerciales($tipoUsu): bool
    {
        $tipoUsu = (int) $tipoUsu;

        return $tipoUsu === USUARIO_ROL_VENDEDOR || $tipoUsu === USUARIO_ROL_GESTOR_GALERIA;
    }
}

if (!function_exists('consultaWpViewAdminUserIds')) {
    function consultaWpViewAdminUserIds(): array
    {
        return [20];
    }
}

if (!function_exists('usuarioEsAdminVistaConsultaWp')) {
    function usuarioEsAdminVistaConsultaWp($userId): bool
    {
        return in_array((int) $userId, consultaWpViewAdminUserIds(), true);
    }
}

if (!function_exists('usuarioPuedeOcultarWeddingPlanner')) {
    function usuarioPuedeOcultarWeddingPlanner($tipoUsu, $userId): bool
    {
        return usuarioTipoEsAdminLike($tipoUsu) || (int) $userId === 1 || usuarioEsAdminVistaConsultaWp($userId);
    }
}

if (!function_exists('usuarioEsVendedoraAsignableEnConsultaWp')) {
    function usuarioEsVendedoraAsignableEnConsultaWp($targetTipoUsu, int $sessionUserId, int $targetUserId): bool
    {
        if ($targetUserId > 0 && $targetUserId === $sessionUserId) {
            return true;
        }

        return usuarioTipoEsAsesorEventoWp($targetTipoUsu);
    }
}

if (!function_exists('usuarioSqlInTiposAsignacionLeadBoard')) {
    /**
     * Usuarios asignables en My Lead Board: vendedoras, líderes de planners y admins.
     */
    function usuarioSqlInTiposAsignacionLeadBoard(): string
    {
        return USUARIO_ROL_ADMIN . ',' . USUARIO_ROL_VENDEDOR . ',' . USUARIO_ROL_LIDER_PLANNERS;
    }
}

if (!function_exists('usuarioEsAsignableEnLeadBoard')) {
    function usuarioEsAsignableEnLeadBoard($targetTipoUsu, int $sessionUserId, int $targetUserId): bool
    {
        if ($targetUserId > 0 && $targetUserId === $sessionUserId) {
            return true;
        }

        $tipoUsu = (int) $targetTipoUsu;

        return $tipoUsu === USUARIO_ROL_ADMIN || usuarioTipoEsAsesorEventoWp($tipoUsu);
    }
}
