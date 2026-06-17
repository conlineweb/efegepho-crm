<?php
$duracionSesion = 60 * 60 * 24 * 30;

session_set_cookie_params([
    'lifetime' => $duracionSesion,
    'path' => '/',
    'domain' => '.efegepho.com.mx',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

ini_set('session.gc_maxlifetime', $duracionSesion);
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] === false) {
    header('Location: ingreso.php');
    exit();
}

$tipoUsuario = $_SESSION['tus'];
$isMarketingOnly = ($tipoUsuario === '4');

$marketingAllowedPages = [
    'consulta_leads.php',
    'consulta_agendados_leads.php',
    'consulta_post_leads.php',
    'consulta_post_leads_trazabilidad.php',
    'clientes.php',
    'consulta.php',
    'plantillas_marketing.php',
    'consulta_leads_marketing.php',
    'tasa_apertura_plantillas_marketing.php',
    'cerrarSesion.php',
];

if ($isMarketingOnly) {
    $currentPageGuard = basename($_SERVER['PHP_SELF']);
    if (!in_array($currentPageGuard, $marketingAllowedPages, true)) {
        header('Location: consulta_leads.php');
        exit();
    }
}

function pageIsActive(string $currentPage, array $pages): bool
{
    return in_array($currentPage, $pages, true);
}

function submenuExpandedAttr(bool $isExpanded): string
{
    return $isExpanded ? 'true' : 'false';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   
    <link rel="shortcut icon" href="./assets/compiled/png/icono.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="./assets/compiled/css/app.css">
    <link rel="stylesheet" href="./assets/compiled/css/app-dark.css">
    
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --sidebar-bg: #faf9f7;
            --sidebar-border: #e8e5df;
            --section-label: #b0876a;
            --text-main: #2c2c2a;
            --text-muted: #888780;
            --item-hover: #f0ede7;
            --item-active: #e8e2d8;
            --body-bg: #f2efe9;
        }

        body { 
            background: var(--body-bg) !important;
            font-family: 'DM Sans', system-ui, sans-serif !important;
        }

        /* ── New Sidebar Styles ─────────────────────────────── */
        .new-sidebar {
            width: 264px;
            background: var(--sidebar-bg);
            border: 0.5px solid var(--sidebar-border);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            position: fixed;
            top: 16px;
            left: 16px;
            height: calc(100vh - 32px);
            font-size: 13.5px;
            z-index: 1000;
        }

        /* Brand */
        .new-brand {
            padding: 18px 16px 14px;
            border-bottom: 0.5px solid var(--sidebar-border);
            position: relative;
            flex-shrink: 0;
        }
        .new-brand-name {
            font-size: 17px;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: var(--text-main);
        }
        .new-brand-sub {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
            letter-spacing: 0.02em;
        }

        /* Theme toggle in brand */
        .new-theme-toggle {
            position: absolute;
            top: 18px;
            right: 16px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Scroll area - AHORA INCLUYE TODO EL CONTENIDO */
        .new-scroll-area {
            flex: 1;
            overflow-y: auto;
            padding: 12px 0 20px;
        }
        .new-scroll-area::-webkit-scrollbar { width: 3px; }
        .new-scroll-area::-webkit-scrollbar-track { background: transparent; }
        .new-scroll-area::-webkit-scrollbar-thumb {
            background: var(--sidebar-border);
            border-radius: 2px;
        }

        /* Section label */
        .new-section-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.10em;
            color: var(--section-label);
            text-transform: uppercase;
            padding: 20px 16px 8px;
        }
        
        .new-section-label:first-child {
            padding-top: 8px;
        }

        /* Nav item */
        .new-nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            cursor: pointer;
            color: var(--text-main);
            border-radius: 8px;
            margin: 0 6px 2px;
            transition: background 0.12s;
            user-select: none;
            text-decoration: none;
        }
        .new-nav-item:hover { 
            background: var(--item-hover); 
            text-decoration: none;
            color: var(--text-main);
        }
        .new-nav-item.active { background: var(--item-active); }

        .new-nav-item .icon {
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: var(--text-muted);
        }
        .new-nav-item.active .icon,
        .new-nav-item:hover .icon { color: var(--text-main); }

        .new-nav-item .label {
            flex: 1;
            font-weight: 500;
            font-size: 13.5px;
        }

        .new-nav-item .chevron {
            width: 14px;
            height: 14px;
            transition: transform 0.18s;
            color: var(--text-muted);
            flex-shrink: 0;
        }
        .new-nav-item .chevron.open { transform: rotate(180deg); }

        /* Sub items */
        .new-sub-items {
            overflow: hidden;
            max-height: 0;
            transition: max-height 0.22s ease;
            margin-bottom: 2px;
        }
        .new-sub-items.open { max-height: 600px; }

        .new-sub-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5.5px 16px 5.5px 44px;
            color: var(--text-muted);
            font-size: 13px;
            cursor: pointer;
            border-radius: 8px;
            margin: 0 6px 1px;
            transition: background 0.12s, color 0.12s;
            text-decoration: none;
        }
        .new-sub-item:hover {
            background: var(--item-hover);
            color: var(--text-main);
            text-decoration: none;
        }
        .new-sub-item.active {
            background: var(--item-active);
            color: var(--text-main);
        }
        .new-sub-item.has-dot::before {
            content: '';
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: var(--sidebar-border);
            flex-shrink: 0;
            margin-left: -12px;
            margin-right: 8px;
        }

        /* Nested sub-items */
        .new-nested-sub { padding-left: 16px; }
        .new-nested-sub .new-sub-item { padding-left: 56px; }

        /* Divider */
        .new-divider {
            height: 0.5px;
            background: var(--sidebar-border);
            margin: 20px 12px;
        }

        /* Bottom section - AHORA DENTRO DEL SCROLL */
        .new-bottom-section {
            border-top: 0.5px solid var(--sidebar-border);
            padding: 16px 0 8px;
            margin-top: 8px;
        }

        .new-nav-item.logout { color: #c0392b; }
        .new-nav-item.logout .icon { color: #c0392b; }
        .new-nav-item.logout:hover { color: #c0392b; }

        /* SVG icon helper */
        svg.new-nav-icon {
            width: 16px;
            height: 16px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* Main content adjustment */
        #main, .main-content {
            margin-left: 296px;
            min-height: 100vh;
            padding: 0;
            background: transparent;
        }

        /* Mobile responsiveness */
        @media (max-width: 1199px) {
            .new-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .new-sidebar.active {
                transform: translateX(0);
            }
            
            #main, .main-content {
                margin-left: 0;
            }
        }

        /* Hide original sidebar */
        .sidebar-wrapper, #sidebar {
            display: none !important;
        }

        /* Google Translate positioning adjustment */
        #google_translate_wrapper {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 9999;
            background: #fff;
            border-radius: 6px;
            padding: 6px 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }

        @media (max-width: 1199px) {
            #google_translate_wrapper {
                right: 10px;
            }
        }

        /* Burger button for mobile */
        .burger-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--sidebar-bg);
            border: 1px solid var(--sidebar-border);
            border-radius: 8px;
            padding: 8px 12px;
            color: var(--text-main);
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .burger-btn:hover {
            background: var(--item-hover);
            color: var(--text-main);
            text-decoration: none;
        }

        @media (min-width: 1200px) {
            .burger-btn {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <script src="assets/static/js/initTheme.js"></script>
    
    <!-- Google Translate -->
    <div id="google_translate_wrapper">
        <div id="google_translate_element"></div>
    </div>
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'es',
                includedLanguages: 'en,es',
                layout: google.translate.TranslateElement.InlineLayout.SIMPLE
            }, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
    
    <!-- Mobile burger button -->
    <a href="#" class="burger-btn d-block d-xl-none" onclick="toggleSidebar(); return false;">
        <i class="bi bi-justify fs-5"></i>
    </a>
    
    <!-- New Sidebar -->
    <nav class="new-sidebar" aria-label="Navegación principal EFEGE">
        <!-- Brand -->
        <div class="new-brand">
            <div class="new-brand-name">EFEGE</div>
            <div class="new-brand-sub">Menú de Navegación</div>
            
            <!-- Theme toggle -->
            <div class="new-theme-toggle">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 21 21" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M10.5 14.5c2.219 0 4-1.763 4-3.982a4.003 4.003 0 0 0-4-4.018c-2.219 0-4 1.781-4 4c0 2.219 1.781 4 4 4z" opacity=".3"></path>
                </svg>
                <div class="form-check form-switch" style="margin: 0;">
                    <input class="form-check-input" type="checkbox" id="toggle-dark" style="cursor: pointer; margin: 0; width: 24px; height: 12px;">
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="m17.75 4.09l-2.53 1.94l.91 3.06l-2.63-1.81l-2.63 1.81l.91-3.06l-2.53-1.94L12.44 4l1.06-3l1.06 3l3.19.09m3.5 6.91l-1.64 1.25l.59 1.98l-1.7-1.17l-1.7 1.17l.59-1.98L15.75 11l2.06-.05L18.5 9l.69 1.95l2.06.05m-2.28 4.95c.83-.08 1.72 1.1 1.19 1.85c-.32.45-.66.87-1.08 1.27C15.17 23 8.84 23 4.94 19.07c-3.91-3.9-3.91-10.24 0-14.14c.4-.4.82-.76 1.27-1.08c.75-.53 1.93.36 1.85 1.19c-.27 2.86.69 5.83 2.89 8.02a9.96 9.96 0 0 0 8.02 2.89m-1.64 2.02a12.08 12.08 0 0 1-7.8-3.47c-2.17-2.19-3.33-5-3.49-7.82c-2.81 3.14-2.7 7.96.31 10.98c3.02 3.01 7.84 3.12 10.98.31Z"></path>
                </svg>
            </div>
        </div>

        <!-- Scroll area - AHORA INCLUYE TODO EL CONTENIDO -->
        <div class="new-scroll-area">
            <?php
            $currentPage = basename($_SERVER['PHP_SELF']);
            
            $leadsPages = ['consulta_leads.php', 'consulta_agendados_leads.php', 'consulta_post_leads.php', 'consulta_post_leads_trazabilidad.php', 'clientes.php'];
            $mailingPages = ['plantillas_marketing.php', 'consulta_leads_marketing.php', 'tasa_apertura_plantillas_marketing.php'];
            $citasPages = ['consulta.php', 'citas-sin-asesor.php', 'horarios.php', 'bloquear-dias.php', 'bloquear-dias-eventos.php', 'plantillas.php', 'tutoriales.php', 'formulario-registro-manual.php', 'alta-usuarios.php'];
            $bloqueoPages = ['bloquear-dias.php', 'bloquear-dias-eventos.php'];
            $clientesPages = ['consulta-clientes.php', 'registro_cuestionario.php', 'consulta_cuestionario.php', 'respuestas_cuestionario.php', 'formulario-datos-transferencia.php', 'subir_nextstep.php', 'clientes-editar.php'];
            $cuestionariosPages = ['registro_cuestionario.php', 'consulta_cuestionario.php', 'respuestas_cuestionario.php'];
            $wpPages = ['pendientes_wp.php', 'consulta_wp.php', 'eventos_wp.php'];

            $leadsActive = pageIsActive($currentPage, $leadsPages);
            $mailingActive = pageIsActive($currentPage, $mailingPages);
            $citasActive = pageIsActive($currentPage, $citasPages);
            $bloqueoActive = pageIsActive($currentPage, $bloqueoPages);
            $clientesActive = pageIsActive($currentPage, $clientesPages);
            $cuestionariosActive = pageIsActive($currentPage, $cuestionariosPages);
            $wpActive = pageIsActive($currentPage, $wpPages);
            $myLeadBoardActive = ($currentPage === 'my_lead_board.php');
            ?>

            <?php if (!$isMarketingOnly): ?>
            <!-- ── DASHBOARDS ──────────────────────── -->
            <div class="new-section-label">Dashboards</div>

            <a href="index.php" class="new-nav-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                <div class="icon">
                    <svg class="new-nav-icon" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                    </svg>
                </div>
                <span class="label">Mi dashboard</span>
            </a>

            <a href="dashboard_comercial.php" class="new-nav-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard_comercial.php' ? 'active' : '' ?>">
                <div class="icon">
                    <svg class="new-nav-icon" viewBox="0 0 24 24">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                </div>
                <span class="label">Dashboard Comercial</span>
            </a>

            <div class="new-divider"></div>
            <?php endif; ?>

            <!-- ── MARKETING & LQ ──────────────────── -->
            <div class="new-section-label">Marketing &amp; LQ</div>

            <div class="new-nav-item <?php echo $leadsActive ? 'active' : ''; ?>" onclick="toggleNewSubmenu('funnel', this)">
                <div class="icon">
                    <svg class="new-nav-icon" viewBox="0 0 24 24">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                </div>
                <span class="label">Lead Funnel</span>
                <svg class="new-nav-icon chevron <?php echo $leadsActive ? 'open' : ''; ?>" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="new-sub-items <?php echo $leadsActive ? 'open' : ''; ?>" id="funnel">
                <a href="consulta_leads.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'consulta_leads.php' ? 'active' : '' ?>">Pre qualified leads</a>
                <a href="consulta_agendados_leads.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'consulta_agendados_leads.php' ? 'active' : '' ?>">Agendados</a>

                <a href="consulta_post_leads.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'consulta_post_leads.php' ? 'active' : '' ?>">Post qualified leads</a>
                <a href="consulta_post_leads_trazabilidad.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'consulta_post_leads_trazabilidad.php' ? 'active' : '' ?>">Trazabilidad de leads</a>
                <a href="clientes.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'active' : '' ?>">Cliente final</a>
            </div>

            <div class="new-nav-item <?php echo $citasActive ? 'active' : ''; ?>" onclick="toggleNewSubmenu('agendas', this)">
                <div class="icon">
                    <svg class="new-nav-icon" viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
                <span class="label">Agendas / Booker</span>
                <svg class="new-nav-icon chevron <?php echo $citasActive ? 'open' : ''; ?>" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="new-sub-items <?php echo $citasActive ? 'open' : ''; ?>" id="agendas">
                <?php if ($tipoUsuario != "3" && $tipoUsuario != "2"): ?>
                    <a href="consulta.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'consulta.php' ? 'active' : '' ?>">Citas agendadas</a>
                <?php endif; ?>
                
                <?php if ($tipoUsuario == "0"): ?>
                    <a href="citas-sin-asesor.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'citas-sin-asesor.php' ? 'active' : '' ?>">Citas sin asesor</a>
                <?php endif; ?>
                

                
                <?php if ($tipoUsuario == "0" || $tipoUsuario == "1"): ?>
                    <a href="horarios.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'horarios.php' ? 'active' : '' ?>">Horarios</a>
                <?php endif; ?>
                
                <?php if ($tipoUsuario == "0"): ?>
                    <div class="new-sub-item" style="justify-content:space-between;cursor:pointer" onclick="toggleNewSubmenu('bloquear', this)">
                        <span style="display:flex;align-items:center;gap:8px">
                            <span style="width:4px;height:4px;border-radius:50%;background:var(--sidebar-border);display:inline-block;margin-right:4px"></span>
                            Bloquear días
                        </span>
                        <svg class="new-nav-icon chevron <?php echo $bloqueoActive ? 'open' : ''; ?>" style="width:12px;height:12px" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <div class="new-sub-items new-nested-sub <?php echo $bloqueoActive ? 'open' : ''; ?>" id="bloquear">
                        <a href="bloquear-dias.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'bloquear-dias.php' ? 'active' : '' ?>">Citas</a>
                        <a href="bloquear-dias-eventos.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'bloquear-dias-eventos.php' ? 'active' : '' ?>">Eventos</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="new-nav-item <?php echo $mailingActive ? 'active' : ''; ?>" onclick="toggleNewSubmenu('mailing', this)">
                <div class="icon">
                    <svg class="new-nav-icon" viewBox="0 0 24 24">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </div>
                <span class="label">Mailing</span>
                <svg class="new-nav-icon chevron <?php echo $mailingActive ? 'open' : ''; ?>" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="new-sub-items <?php echo $mailingActive ? 'open' : ''; ?>" id="mailing">
                <a href="plantillas_marketing.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'plantillas_marketing.php' ? 'active' : '' ?>">Email Templates</a>
                <a href="consulta_leads_marketing.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'consulta_leads_marketing.php' ? 'active' : '' ?>">Gestión</a>
                <a href="tasa_apertura_plantillas_marketing.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'tasa_apertura_plantillas_marketing.php' ? 'active' : '' ?>">Tasa de apertura / clics</a>
            </div>

            <?php if (!$isMarketingOnly): ?>
            <div class="new-divider"></div>

            <!-- ── LEADS ACTIVOS ───────────────────── -->
            <div class="new-section-label">Leads Activos</div>

            <a href="my_lead_board.php" class="new-nav-item <?= $myLeadBoardActive ? 'active' : '' ?>">
                <div class="icon">
                    <svg class="new-nav-icon" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                </div>
                <span class="label">My Lead Board</span>
            </a>

            <a href="consulta_wp.php" class="new-nav-item <?= basename($_SERVER['PHP_SELF']) == 'consulta_wp.php' ? 'active' : '' ?>">
                <div class="icon">
                    <svg class="new-nav-icon" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                </div>
                <span class="label">Planners</span>
            </a>

            

          

            <div class="new-divider"></div>

            <!-- ── CLIENTES ACTIVOS ────────────────── -->
            <div class="new-section-label">Clientes Activos</div>

            <div class="new-nav-item <?php echo $clientesActive ? 'active' : ''; ?>" onclick="toggleNewSubmenu('cs', this)">
                <div class="icon">
                    <svg class="new-nav-icon" viewBox="0 0 24 24">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                </div>
                <span class="label">Customer Service</span>
                <svg class="new-nav-icon chevron <?php echo $clientesActive ? 'open' : ''; ?>" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="new-sub-items <?php echo $clientesActive ? 'open' : ''; ?>" id="cs">
                <?php if ($tipoUsuario == "0" || $tipoUsuario == "1" || $tipoUsuario == "2" || $tipoUsuario == "3"): ?>
                    <a href="consulta-clientes.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'consulta-clientes.php' ? 'active' : '' ?>">Clientes</a>
                    <a href="clientes-editar.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'clientes-editar.php' ? 'active' : '' ?>">Editar clientes</a>
                    
                    <?php if ($tipoUsuario == "0" || $tipoUsuario == "3"): ?>
                        <a href="formulario-datos-transferencia.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'formulario-datos-transferencia.php' ? 'active' : '' ?>">Datos para transferencia</a>
                        <a href="subir_nextstep.php" class="new-sub-item has-dot <?= basename($_SERVER['PHP_SELF']) == 'subir_nextstep.php' ? 'active' : '' ?>">Subir nextstep</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="new-nav-item" onclick="toggleNewSubmenu('finanzas', this)">
                <div class="icon">
                    <svg class="new-nav-icon" viewBox="0 0 24 24">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
                <span class="label">Finanzas / Admin</span>
                <svg class="new-nav-icon chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="new-sub-items" id="finanzas">
                <?php if ($tipoUsuario == "0"): ?>
                    <div class="new-sub-item has-dot" style="font-style:italic;color:var(--text-muted)">submodulos por definir</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($isMarketingOnly): ?>
            <div class="new-divider"></div>
            <?php endif; ?>

            <!-- ── Bottom section - AHORA DENTRO DEL SCROLL ──────── -->
            <div class="new-bottom-section">
                <?php if (!$isMarketingOnly): ?>
                <a href="tutoriales.php" class="new-nav-item <?= basename($_SERVER['PHP_SELF']) == 'tutoriales.php' ? 'active' : '' ?>">
                    <div class="icon">
                        <svg class="new-nav-icon" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/>
                            <polygon points="10 8 16 12 10 16 10 8"/>
                        </svg>
                    </div>
                    <span class="label">Tutoriales</span>
                </a>

                <?php if ($tipoUsuario == "0"): ?>
                    <a href="alta-usuarios.php" class="new-nav-item <?= basename($_SERVER['PHP_SELF']) == 'alta-usuarios.php' ? 'active' : '' ?>">
                        <div class="icon">
                            <svg class="new-nav-icon" viewBox="0 0 24 24">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="8.5" cy="7" r="4"/>
                                <line x1="20" y1="8" x2="20" y2="14"/>
                                <line x1="23" y1="11" x2="17" y2="11"/>
                            </svg>
                        </div>
                        <span class="label">Alta usuarios</span>
                    </a>
                <?php endif; ?>

                <?php endif; ?>

                <?php if (!$isMarketingOnly): ?>
                <a href="perfil.php" class="new-nav-item <?= basename($_SERVER['PHP_SELF']) == 'perfil.php' ? 'active' : '' ?>">
                    <div class="icon">
                        <svg class="new-nav-icon" viewBox="0 0 24 24">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <span class="label">Perfil</span>
                </a>
                <?php endif; ?>
                <a href="cerrarSesion.php" class="new-nav-item logout">
                    <div class="icon">
                        <svg class="new-nav-icon" viewBox="0 0 24 24" style="stroke:#c0392b">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </div>
                    <span class="label">Cerrar sesión</span>
                </a>
            </div>

        </div><!-- end scroll-area -->
    </nav>

    <!-- Main content wrapper -->
    <div id="main" class="main-content">
        <!-- El contenido de las páginas se insertará aquí -->

    <script>
        function toggleNewSubmenu(id, trigger) {
            const el = document.getElementById(id);
            if (!el) return;
            const chevron = trigger ? trigger.querySelector('.chevron') : null;
            
            if (el.classList.contains('open')) {
                el.classList.remove('open');
                if (chevron) chevron.classList.remove('open');
            } else {
                el.classList.add('open');
                if (chevron) chevron.classList.add('open');
            }
        }

        function toggleSidebar() {
            const sidebar = document.querySelector('.new-sidebar');
            sidebar.classList.toggle('active');
        }

        // Initialize sidebar state based on screen size
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.new-sidebar');
            if (window.innerWidth <= 1199) {
                sidebar.classList.remove('active');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.querySelector('.new-sidebar');
            if (window.innerWidth > 1199) {
                sidebar.classList.remove('active');
            }
        });
    </script>