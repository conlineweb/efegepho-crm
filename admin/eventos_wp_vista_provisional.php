<?php
include 'menu.php';
include 'conn.php';
require_once __DIR__ . '/evento_wp_post_helper.php';
require_once __DIR__ . '/planner_event_display_helper.php';

$allRecords = provisionalFetchAllRecordsForList($conn, true);
$recordCount = count($allRecords);

function provisionalNormalizeFilterDateYmd($raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
        return '';
    }
    $timestamp = strtotime($raw);

    return $timestamp !== false ? date('Y-m-d', $timestamp) : '';
}

function provisionalResolveFilterDate(array $recordItem): string
{
    foreach (['registro_date', 'agenda_date', 'sort_date'] as $field) {
        $raw = trim((string) ($recordItem[$field] ?? ''));
        if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
            continue;
        }
        $timestamp = strtotime($raw);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
    }

    return '';
}

function provisionalBuildExportRecord(array $recordItem): array
{
    $recordType = (string) ($recordItem['record_type'] ?? 'evento_wp');
    $eventId = (int) ($recordItem['event_id'] ?? 0);
    $contactFormId = (int) ($recordItem['contact_form_id'] ?? 0);
    $eventStatus = $recordItem['event_status'] ?? ['label' => '—', 'class' => 'status-pendiente'];

    return [
        'row_key' => (string) ($recordItem['row_key'] ?? ''),
        'record_type' => $recordType,
        'id' => $recordType === 'evento_wp' ? $eventId : $contactFormId,
        'event_id' => $eventId > 0 ? $eventId : null,
        'contact_form_id' => $contactFormId > 0 ? $contactFormId : null,
        'tipo_cliente' => (string) ($recordItem['tipo_cliente_label'] ?? ''),
        'wedding_planner' => (string) ($recordItem['wp_label'] ?? ''),
        'nombre' => (string) ($recordItem['novios_label'] ?? ''),
        'estatus' => (string) ($eventStatus['label'] ?? '—'),
        'fecha_registro' => (string) ($recordItem['registro_date'] ?? ''),
        'fecha_agenda' => (string) ($recordItem['agenda_date'] ?? ''),
        'fecha_atencion' => (string) ($recordItem['attended_date'] ?? ''),
        'fecha_cliente' => (string) ($recordItem['cliente_date'] ?? ''),
        'asesor' => (string) ($recordItem['asesor_label'] ?? ''),
        'filter_date' => provisionalResolveFilterDate($recordItem),
    ];
}

$recordsExportByKey = [];
foreach ($allRecords as $recordItem) {
    $rowKey = (string) ($recordItem['row_key'] ?? '');
    if ($rowKey !== '') {
        $recordsExportByKey[$rowKey] = provisionalBuildExportRecord($recordItem);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registros WP — Vista provisional</title>
    <style>
        body { font-family: 'DM Sans', Arial, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; }
        .prov-page { padding: 24px 20px 48px; max-width: 100%; }
        .prov-header { margin-bottom: 20px; }
        .prov-title { font-size: 1.75rem; font-weight: 700; margin: 0 0 6px; }
        .prov-subtitle { color: #64748b; margin: 0; font-size: 0.95rem; }
        .prov-note { margin-top: 14px; padding: 12px 14px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; font-size: 0.86rem; color: #92400e; }
        .prov-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .prov-card-head { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; font-size: 0.88rem; color: #64748b; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .prov-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; flex: 1; min-width: 240px; }
        .prov-search { flex: 1; min-width: 220px; max-width: 420px; padding: 9px 12px 9px 36px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242 1.106a5 5 0 1 1 0-10 5 5 0 0 1 0 10z'/%3E%3C/svg%3E") no-repeat 12px center; }
        .prov-search:focus { outline: none; border-color: #94a3b8; box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.2); }
        .prov-search-clear { border: 1px solid #e2e8f0; background: #fff; color: #64748b; border-radius: 8px; padding: 8px 12px; font-size: 0.84rem; cursor: pointer; }
        .prov-search-clear:hover { background: #f8fafc; color: #0f172a; }
        .prov-filters { padding: 12px 16px 0; border-bottom: 1px solid #e2e8f0; background: #fafbfc; }
        .prov-filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 12px; }
        .prov-filter-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
        .prov-filter-card-title { font-size: 0.82rem; font-weight: 700; color: #334155; margin: 0 0 10px; }
        .prov-filter-card-dates { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .prov-filter-field { display: flex; flex-direction: column; gap: 4px; }
        .prov-filter-field label { font-size: 0.74rem; font-weight: 600; color: #64748b; }
        .prov-filter-field input[type="date"] { padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.86rem; width: 100%; box-sizing: border-box; }
        .prov-filter-field input[type="date"]:focus { outline: none; border-color: #94a3b8; box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.2); }
        .prov-filter-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 0 0 12px; }
        .prov-btn { border: 1px solid #e2e8f0; background: #fff; color: #334155; border-radius: 8px; padding: 8px 12px; font-size: 0.84rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .prov-btn:hover { background: #f8fafc; color: #0f172a; }
        .prov-btn-primary { background: #0f172a; border-color: #0f172a; color: #fff; }
        .prov-btn-primary:hover { background: #1e293b; color: #fff; }
        .prov-btn:disabled { opacity: 0.65; cursor: wait; }
        .prov-count { white-space: nowrap; font-weight: 600; color: #334155; }
        .prov-table-footer { padding: 12px 16px; border-top: 1px solid #e2e8f0; background: #f8fafc; font-size: 0.88rem; color: #475569; display: flex; justify-content: flex-end; }
        .prov-table-footer strong { color: #0f172a; font-weight: 700; }
        .events-table-wrap { width: 100%; overflow-x: auto; }
        .events-table { width: 100%; border-collapse: collapse; min-width: 1320px; }
        .events-table th, .events-table td { padding: 9px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 12px; vertical-align: top; white-space: nowrap; }
        .events-table th { background: #f8fafc; color: #334155; font-weight: 700; position: sticky; top: 0; z-index: 1; }
        .events-date-edit { border: none; background: transparent; color: #2563eb; font: inherit; padding: 0; cursor: pointer; text-decoration: underline; text-decoration-style: dotted; text-underline-offset: 3px; white-space: nowrap; }
        .events-date-edit:hover { color: #1d4ed8; }
        .events-date-edit.is-empty { color: #64748b; font-style: italic; }
        .cf-missing { color: #94a3b8; font-style: italic; }
        .cf-create-btn { border: 1px solid #bfdbfe; background: #eff6ff; color: #1d4ed8; border-radius: 8px; padding: 4px 10px; font-size: 0.78rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .cf-create-btn:hover { background: #dbeafe; }
        .cf-create-btn:disabled { opacity: 0.6; cursor: wait; }
        .status-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 3px 10px; font-size: 0.72rem; font-weight: 600; }
        .status-afianzado { background: #dcfce7; color: #166534; }
        .status-pendiente { background: #f1f5f9; color: #334155; }
        .status-rechazado { background: #fee2e2; color: #b91c1c; }
        .status-inminente { background: #ffedd5; color: #c2410c; }
        .tipo-wp { background: #dbeafe; color: #1e40af; }
        .tipo-cf { background: #f3e8ff; color: #6b21a8; }
        .empty-card { padding: 32px; text-align: center; color: #64748b; }
        .empty-card[hidden] { display: none !important; }
        .events-table tbody tr[hidden] { display: none; }
        .schedule-modal .schedule-field { margin-bottom: 14px; }
        .schedule-modal label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; }
        .schedule-modal input[type="datetime-local"] { width: 100%; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 8px; }
    </style>
</head>
<body>
<div class="prov-page">
    <header class="prov-header">
        <h1 class="prov-title">Registros WP — Vista provisional</h1>
        <p class="prov-subtitle">Listado global de eventos WP y clientes finales con cita. Clic en las fechas de atención o cliente para editarlas (solo eventos WP).</p>
        <div class="prov-note"><strong>Provisional:</strong> incluye <code>eventos_wp</code> y clientes finales en <code>contact_form</code> con calendario. Las fechas editables aplican solo a eventos WP.</div>
    </header>

    <div class="prov-card">
        <div class="prov-card-head">
            <div class="prov-toolbar">
                <input type="search" id="eventsSearchInput" class="prov-search" placeholder="Buscar por ID, contact_form, tipo, planner, nombre, estatus o asesor…" autocomplete="off">
                <button type="button" class="prov-search-clear" id="eventsSearchClear" hidden>Limpiar</button>
            </div>
            <span class="prov-count" id="eventsVisibleCount"><?php echo number_format($recordCount); ?> de <?php echo number_format($recordCount); ?> registros</span>
        </div>
        <div class="prov-filters">
            <div class="prov-filter-grid">
                <div class="prov-filter-card">
                    <h3 class="prov-filter-card-title">Fecha de registro</h3>
                    <div class="prov-filter-card-dates">
                        <div class="prov-filter-field">
                            <label for="filterRegistroFrom">Desde</label>
                            <input type="date" id="filterRegistroFrom" data-filter-key="registro" data-filter-bound="from">
                        </div>
                        <div class="prov-filter-field">
                            <label for="filterRegistroTo">Hasta</label>
                            <input type="date" id="filterRegistroTo" data-filter-key="registro" data-filter-bound="to">
                        </div>
                    </div>
                </div>
                <div class="prov-filter-card">
                    <h3 class="prov-filter-card-title">Fecha de agenda</h3>
                    <div class="prov-filter-card-dates">
                        <div class="prov-filter-field">
                            <label for="filterAgendaFrom">Desde</label>
                            <input type="date" id="filterAgendaFrom" data-filter-key="agenda" data-filter-bound="from">
                        </div>
                        <div class="prov-filter-field">
                            <label for="filterAgendaTo">Hasta</label>
                            <input type="date" id="filterAgendaTo" data-filter-key="agenda" data-filter-bound="to">
                        </div>
                    </div>
                </div>
                <div class="prov-filter-card">
                    <h3 class="prov-filter-card-title">Fecha de atendido</h3>
                    <div class="prov-filter-card-dates">
                        <div class="prov-filter-field">
                            <label for="filterAtendidoFrom">Desde</label>
                            <input type="date" id="filterAtendidoFrom" data-filter-key="atendido" data-filter-bound="from">
                        </div>
                        <div class="prov-filter-field">
                            <label for="filterAtendidoTo">Hasta</label>
                            <input type="date" id="filterAtendidoTo" data-filter-key="atendido" data-filter-bound="to">
                        </div>
                    </div>
                </div>
                <div class="prov-filter-card">
                    <h3 class="prov-filter-card-title">Fecha que se pasó a cliente</h3>
                    <div class="prov-filter-card-dates">
                        <div class="prov-filter-field">
                            <label for="filterClienteFrom">Desde</label>
                            <input type="date" id="filterClienteFrom" data-filter-key="cliente" data-filter-bound="from">
                        </div>
                        <div class="prov-filter-field">
                            <label for="filterClienteTo">Hasta</label>
                            <input type="date" id="filterClienteTo" data-filter-key="cliente" data-filter-bound="to">
                        </div>
                    </div>
                </div>
            </div>
            <div class="prov-filter-actions">
                <button type="button" class="prov-btn prov-btn-primary" id="filterApplyBtn">Filtrar</button>
                <button type="button" class="prov-btn" id="filterDateClear" hidden>Limpiar fechas</button>
                <button type="button" class="prov-btn" id="exportJsonBtn">Exportar JSON</button>
            </div>
        </div>
        <?php if (empty($allRecords)): ?>
            <div class="empty-card">No hay registros.</div>
        <?php else: ?>
            <div class="empty-card" id="eventsNoResults" hidden>No hay registros que coincidan con la búsqueda.</div>
            <div class="events-table-wrap" id="eventsTableWrap">
                <table class="events-table" id="eventsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ID contact_form</th>
                            <th>Tipo de cliente</th>
                            <th>Wedding Planner</th>
                            <th>Nombre</th>
                            <th>Estatus</th>
                            <th>Fecha de registro</th>
                            <th>Fecha de agenda</th>
                            <th>Fecha en la que se atendió</th>
                            <th>Fecha en la que se pasó a cliente</th>
                            <th>Asesor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allRecords as $recordItem): ?>
                            <?php
                            $recordType = (string) ($recordItem['record_type'] ?? 'evento_wp');
                            $eventId = (int) ($recordItem['event_id'] ?? 0);
                            $contactFormId = (int) ($recordItem['contact_form_id'] ?? 0);
                            $tipoClienteLabel = (string) ($recordItem['tipo_cliente_label'] ?? '');
                            $tipoClienteClass = strcasecmp($tipoClienteLabel, 'Cliente Final') === 0 ? 'tipo-cf' : 'tipo-wp';
                            $statusKey = mb_strtolower(trim((string) ($recordItem['status_key'] ?? '')), 'UTF-8');
                            $eventStatus = $recordItem['event_status'] ?? ['label' => '—', 'class' => 'status-pendiente'];
                            $registroDate = (string) ($recordItem['registro_date'] ?? '');
                            $agendaDate = (string) ($recordItem['agenda_date'] ?? '');
                            $eventAttendedDate = (string) ($recordItem['attended_date'] ?? '');
                            $eventCanEditAttendedDate = !empty($recordItem['can_edit_attended']);
                            $eventAttendedDateInput = (string) ($recordItem['attended_date_input'] ?? '');
                            $eventClienteDate = (string) ($recordItem['cliente_date'] ?? '');
                            $eventCanEditClienteDate = !empty($recordItem['can_edit_cliente']);
                            $eventClienteDateInput = (string) ($recordItem['cliente_date_input'] ?? '');
                            $wpLabel = (string) ($recordItem['wp_label'] ?? '—');
                            $noviosLabel = (string) ($recordItem['novios_label'] ?? '—');
                            $asesorLabel = (string) ($recordItem['asesor_label'] ?? 'Sin asignar');
                            $eventLabel = (string) ($recordItem['event_label'] ?? '');
                            $displayId = $recordType === 'evento_wp' ? $eventId : $contactFormId;
                            $contactFormLabel = $contactFormId > 0 ? (string) $contactFormId : 'no existe';
                            $filterDateRegistro = provisionalNormalizeFilterDateYmd($registroDate);
                            $filterDateAgenda = provisionalNormalizeFilterDateYmd($agendaDate);
                            $filterDateAtendido = provisionalNormalizeFilterDateYmd($eventAttendedDate);
                            $filterDateCliente = provisionalNormalizeFilterDateYmd($eventClienteDate);
                            $searchBlob = mb_strtolower(implode(' ', [
                                (string) $displayId,
                                $contactFormLabel,
                                $tipoClienteLabel,
                                $wpLabel,
                                $noviosLabel,
                                $eventStatus['label'] ?? '',
                                $asesorLabel,
                                plannerProfileFormatDateTime($registroDate, ''),
                                $agendaDate !== '' ? plannerProfileFormatDateTime($agendaDate, '') : '',
                                $eventAttendedDate !== '' ? plannerProfileFormatDateTime($eventAttendedDate, '') : '',
                                $eventClienteDate !== '' ? plannerProfileFormatDateTime($eventClienteDate, '') : '',
                            ]), 'UTF-8');
                            ?>
                            <tr class="js-event-row"
                                data-row-key="<?php echo plannerProfileEscapeHtml($recordItem['row_key'] ?? ''); ?>"
                                data-record-type="<?php echo plannerProfileEscapeHtml($recordType); ?>"
                                data-event-id="<?php echo $eventId; ?>"
                                data-status-key="<?php echo plannerProfileEscapeHtml($statusKey); ?>"
                                data-date-registro="<?php echo plannerProfileEscapeHtml($filterDateRegistro); ?>"
                                data-date-agenda="<?php echo plannerProfileEscapeHtml($filterDateAgenda); ?>"
                                data-date-atendido="<?php echo plannerProfileEscapeHtml($filterDateAtendido); ?>"
                                data-date-cliente="<?php echo plannerProfileEscapeHtml($filterDateCliente); ?>"
                                data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>">
                                <td><?php echo $displayId; ?></td>
                                <td class="js-cell-contact-form<?php echo $contactFormId <= 0 ? ' cf-missing' : ''; ?>">
                                    <?php if ($contactFormId > 0): ?>
                                        <?php echo plannerProfileEscapeHtml($contactFormLabel); ?>
                                    <?php elseif ($recordType === 'evento_wp'): ?>
                                        <span class="cf-missing">no existe</span>
                                        <button type="button" class="cf-create-btn js-create-contact-form" data-event-id="<?php echo $eventId; ?>" data-event-label="<?php echo plannerProfileEscapeHtml($eventLabel); ?>" title="Crear registro en contact_form con los mismos datos del flujo WP">Crear</button>
                                    <?php else: ?>
                                        <span class="cf-missing">no existe</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge <?php echo plannerProfileEscapeHtml($tipoClienteClass); ?>"><?php echo plannerProfileEscapeHtml($tipoClienteLabel); ?></span></td>
                                <td><?php echo plannerProfileEscapeHtml($wpLabel); ?></td>
                                <td><?php echo plannerProfileEscapeHtml($noviosLabel); ?></td>
                                <td><span class="status-badge <?php echo plannerProfileEscapeHtml($eventStatus['class'] ?? 'status-pendiente'); ?>"><?php echo plannerProfileEscapeHtml($eventStatus['label'] ?? '—'); ?></span></td>
                                <td class="js-cell-registro notranslate"><?php echo plannerProfileFormatDateTimeDisplay($registroDate, ''); ?></td>
                                <td class="js-cell-agenda notranslate"><?php echo plannerProfileFormatDateTimeDisplay($agendaDate, ''); ?></td>
                                <td class="notranslate">
                                    <?php if ($eventCanEditAttendedDate): ?>
                                        <button type="button" class="events-date-edit js-edit-attended-date<?php echo $eventAttendedDate === '' ? ' is-empty' : ''; ?>"
                                            data-event-id="<?php echo $eventId; ?>"
                                            data-attended-date="<?php echo plannerProfileEscapeHtml($eventAttendedDateInput); ?>"
                                            data-event-label="<?php echo plannerProfileEscapeHtml($eventLabel); ?>"
                                            title="Clic para editar la fecha de atención"><?php echo $eventAttendedDate !== '' ? plannerProfileFormatDateTimeDisplay($eventAttendedDate, '') : 'Sin fecha'; ?></button>
                                    <?php elseif ($eventAttendedDate !== ''): ?>
                                        <?php echo plannerProfileFormatDateTimeDisplay($eventAttendedDate, ''); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="notranslate">
                                    <?php if ($eventCanEditClienteDate): ?>
                                        <button type="button" class="events-date-edit js-edit-cliente-date<?php echo $eventClienteDate === '' ? ' is-empty' : ''; ?>"
                                            data-event-id="<?php echo $eventId; ?>"
                                            data-cliente-date="<?php echo plannerProfileEscapeHtml($eventClienteDateInput); ?>"
                                            data-event-label="<?php echo plannerProfileEscapeHtml($eventLabel); ?>"
                                            title="Clic para editar la fecha de cliente"><?php echo $eventClienteDate !== '' ? plannerProfileFormatDateTimeDisplay($eventClienteDate, '') : 'Sin fecha'; ?></button>
                                    <?php elseif ($eventClienteDate !== ''): ?>
                                        <?php echo plannerProfileFormatDateTimeDisplay($eventClienteDate, ''); ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo plannerProfileEscapeHtml($asesorLabel); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="prov-table-footer" id="eventsTableFooter">
                <span id="eventsVisibleCountFooter"><?php echo number_format($recordCount); ?> de <?php echo number_format($recordCount); ?> registros</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade schedule-modal" id="editarFechaAtendidoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar fecha de atención</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p id="editarFechaAtendidoModalSubtitle" style="font-size:0.85rem;color:#666;margin:0 0 12px;"></p>
                <input type="hidden" id="editarFechaAtendidoEventId" value="">
                <div class="schedule-field">
                    <label for="editarFechaAtendidoInput">Fecha y hora de atención</label>
                    <input type="datetime-local" id="editarFechaAtendidoInput" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-dark" id="btnGuardarFechaAtendido">Guardar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade schedule-modal" id="editarFechaClienteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar fecha de cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p id="editarFechaClienteModalSubtitle" style="font-size:0.85rem;color:#666;margin:0 0 12px;"></p>
                <input type="hidden" id="editarFechaClienteEventId" value="">
                <div class="schedule-field">
                    <label for="editarFechaClienteInput">Fecha y hora en que pasó a cliente</label>
                    <input type="datetime-local" id="editarFechaClienteInput" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-dark" id="btnGuardarFechaCliente">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const TOTAL_EVENTS = <?php echo (int) $recordCount; ?>;
    const RECORDS_BY_KEY = <?php echo json_encode($recordsExportByKey, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); ?>;

    const MONTHS_ES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    function parseDbDateTime(value) {
        const raw = String(value || '').trim();
        if (!raw || raw.indexOf('0000-00-00') === 0) {
            return null;
        }
        const normalized = raw.indexOf('T') !== -1 ? raw : raw.replace(' ', 'T');
        const date = new Date(normalized);
        return isNaN(date.getTime()) ? null : date;
    }

    function formatDatetimeLocalEs(localValue) {
        const raw = String(localValue || '').trim();
        if (!raw) {
            return 'Sin fecha';
        }
        const parts = raw.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
        if (!parts) {
            return formatDateTimeEs(raw);
        }
        const day = parseInt(parts[3], 10);
        const month = parseInt(parts[2], 10);
        const year = parseInt(parts[1], 10);
        const hours24 = parseInt(parts[4], 10);
        const minutes = parts[5];
        const hours12 = hours24 % 12 || 12;
        const ampm = hours24 >= 12 ? 'pm' : 'am';
        return day + ' ' + MONTHS_ES[month - 1] + ' ' + year + ' - ' + hours12 + ':' + minutes + ' ' + ampm;
    }

    function formatDateTimeEs(value) {
        const date = parseDbDateTime(value);
        if (!date) {
            return 'Sin fecha';
        }
        const hours24 = date.getHours();
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const hours12 = hours24 % 12 || 12;
        const ampm = hours24 >= 12 ? 'pm' : 'am';
        return date.getDate() + ' ' + MONTHS_ES[date.getMonth()] + ' ' + date.getFullYear() + ' - ' + hours12 + ':' + minutes + ' ' + ampm;
    }

    function formatDisplayDate(dbValue, localFallback) {
        const fromDb = formatDateTimeEs(dbValue);
        if (fromDb !== 'Sin fecha') {
            return fromDb;
        }
        return formatDatetimeLocalEs(localFallback);
    }

    function datetimeLocalFromDb(value) {
        const date = parseDbDateTime(value);
        if (!date) {
            return '';
        }
        const pad = function (n) { return String(n).padStart(2, '0'); };
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    function dateYmdFromDb(value) {
        const date = parseDbDateTime(value);
        if (!date) {
            return '';
        }
        const pad = function (n) { return String(n).padStart(2, '0'); };
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function updateRowDateAttribute(row, attrName, dbValue, localFallback) {
        if (!row) {
            return;
        }
        const ymd = dateYmdFromDb(dbValue) || dateYmdFromDb(localFallback);
        row.setAttribute(attrName, ymd);
    }

    function updateContactFormInRow(eventId, contactFormId) {
        const row = findEventRowById(eventId);
        if (!row) {
            return;
        }
        const cell = row.querySelector('.js-cell-contact-form');
        if (!cell) {
            return;
        }
        cell.classList.remove('cf-missing');
        cell.innerHTML = String(contactFormId);
        rebuildRowSearch(row);
        applyEventsSearch();
    }

    function findEventRowById(eventId) {
        return document.querySelector('.js-event-row[data-record-type="evento_wp"][data-event-id="' + eventId + '"]');
    }

    function rebuildRowSearch(row) {
        if (!row) {
            return;
        }
        const cells = row.querySelectorAll('td');
        const attendedBtn = row.querySelector('.js-edit-attended-date');
        const clienteBtn = row.querySelector('.js-edit-cliente-date');
        const parts = [
            cells[0] ? cells[0].textContent : '',
            cells[1] ? cells[1].textContent : '',
            cells[2] ? cells[2].textContent : '',
            cells[3] ? cells[3].textContent : '',
            cells[4] ? cells[4].textContent : '',
            cells[5] ? cells[5].textContent : '',
            cells[10] ? cells[10].textContent : '',
            cells[6] ? cells[6].textContent : '',
            cells[7] ? cells[7].textContent : '',
            attendedBtn ? attendedBtn.textContent : '',
            clienteBtn ? clienteBtn.textContent : ''
        ];
        row.setAttribute('data-search', normalizeSearchTerm(parts.join(' ')));
    }

    function updateAttendedDateInRow(eventId, fechaDb, localFallback) {
        const row = findEventRowById(eventId);
        if (!row) {
            return;
        }
        const formatted = formatDisplayDate(fechaDb, localFallback);
        const localValue = datetimeLocalFromDb(fechaDb) || String(localFallback || '').trim();
        const btn = row.querySelector('.js-edit-attended-date');
        if (btn) {
            btn.textContent = formatted;
            btn.setAttribute('data-attended-date', localValue);
            btn.classList.toggle('is-empty', formatted === 'Sin fecha');
        }
        updateRowDateAttribute(row, 'data-date-atendido', fechaDb, localFallback);
        rebuildRowSearch(row);
        applyEventsSearch();
    }

    function updateClienteDateInRow(eventId, fechaDb, localFallback) {
        const row = findEventRowById(eventId);
        if (!row) {
            return;
        }
        const formatted = formatDisplayDate(fechaDb, localFallback);
        const localValue = datetimeLocalFromDb(fechaDb) || String(localFallback || '').trim();
        const btn = row.querySelector('.js-edit-cliente-date');
        if (btn) {
            btn.textContent = formatted;
            btn.setAttribute('data-cliente-date', localValue);
            btn.classList.toggle('is-empty', formatted === 'Sin fecha');
        }
        updateRowDateAttribute(row, 'data-date-cliente', fechaDb, localFallback);
        rebuildRowSearch(row);
        applyEventsSearch();
    }

    function currentDatetimeLocal() {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        return now.toISOString().slice(0, 16);
    }

    function requestAsJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: payload.toString()
        }).then(function (response) {
            return response.text().then(function (rawText) {
                let parsed = null;
                if (rawText && rawText.trim() !== '') {
                    try {
                        parsed = JSON.parse(rawText);
                    } catch (parseError) {
                        throw new Error('El servidor devolvió una respuesta inválida.');
                    }
                }
                if (!response.ok) {
                    throw new Error(parsed && parsed.message ? parsed.message : 'Error en la petición');
                }
                return parsed;
            });
        });
    }

    function showAlert(message, type) {
        const icon = type === 'success' ? 'success' : (type === 'warning' ? 'warning' : (type === 'error' ? 'error' : 'info'));
        const title = type === 'success' ? 'Listo' : (type === 'warning' ? 'Atención' : (type === 'error' ? 'Error' : 'Aviso'));
        return Swal.fire({
            icon: icon,
            title: title,
            text: message,
            confirmButtonText: 'Aceptar'
        });
    }

    const searchInput = document.getElementById('eventsSearchInput');
    const searchClearBtn = document.getElementById('eventsSearchClear');
    const filterApplyBtn = document.getElementById('filterApplyBtn');
    const filterDateClearBtn = document.getElementById('filterDateClear');
    const exportJsonBtn = document.getElementById('exportJsonBtn');
    const visibleCountEl = document.getElementById('eventsVisibleCount');
    const visibleCountFooterEl = document.getElementById('eventsVisibleCountFooter');
    const eventsTableFooter = document.getElementById('eventsTableFooter');
    const noResultsEl = document.getElementById('eventsNoResults');
    const eventRows = Array.prototype.slice.call(document.querySelectorAll('.js-event-row'));
    const eventsTableWrap = document.getElementById('eventsTableWrap');

    const FILTER_KEYS = ['registro', 'agenda', 'atendido', 'cliente'];
    const FILTER_ATTRS = {
        registro: 'data-date-registro',
        agenda: 'data-date-agenda',
        atendido: 'data-date-atendido',
        cliente: 'data-date-cliente'
    };
    const activeDateFilters = {
        registro: { from: '', to: '' },
        agenda: { from: '', to: '' },
        atendido: { from: '', to: '' },
        cliente: { from: '', to: '' }
    };

    function readDateFilterInputs() {
        const next = {
            registro: { from: '', to: '' },
            agenda: { from: '', to: '' },
            atendido: { from: '', to: '' },
            cliente: { from: '', to: '' }
        };
        document.querySelectorAll('[data-filter-key][data-filter-bound]').forEach(function (input) {
            const key = String(input.getAttribute('data-filter-key') || '').trim();
            const bound = String(input.getAttribute('data-filter-bound') || '').trim();
            if (!next[key] || (bound !== 'from' && bound !== 'to')) {
                return;
            }
            next[key][bound] = String(input.value || '').trim();
        });
        return next;
    }

    function syncActiveDateFiltersFromInputs() {
        const next = readDateFilterInputs();
        FILTER_KEYS.forEach(function (key) {
            activeDateFilters[key].from = next[key].from;
            activeDateFilters[key].to = next[key].to;
        });
    }

    function hasActiveDateFilter() {
        return FILTER_KEYS.some(function (key) {
            return activeDateFilters[key].from !== '' || activeDateFilters[key].to !== '';
        });
    }

    function dateMatchesRange(rowDate, fromValue, toValue) {
        if (fromValue === '' && toValue === '') {
            return true;
        }
        const normalizedRowDate = String(rowDate || '').trim();
        if (normalizedRowDate === '') {
            return false;
        }
        if (fromValue !== '' && normalizedRowDate < fromValue) {
            return false;
        }
        if (toValue !== '' && normalizedRowDate > toValue) {
            return false;
        }
        return true;
    }

    function rowStatusCountsForAgendaFilter(row) {
        const statusKey = String(row.getAttribute('data-status-key') || '').trim().toLowerCase();
        return ['agendado', 'pendiente', 'atendido', 'fantasma'].indexOf(statusKey) !== -1;
    }

    function rowMatchesDateFilters(row) {
        return FILTER_KEYS.every(function (key) {
            const filter = activeDateFilters[key];
            if (key === 'agenda' && (filter.from !== '' || filter.to !== '') && !rowStatusCountsForAgendaFilter(row)) {
                return false;
            }
            const rowDate = row.getAttribute(FILTER_ATTRS[key]) || '';
            return dateMatchesRange(rowDate, filter.from, filter.to);
        });
    }

    function normalizeSearchTerm(value) {
        return String(value || '').trim().toLowerCase();
    }

    function getVisibleRows() {
        return eventRows.filter(function (row) {
            return !row.hidden;
        });
    }

    function formatRecordsCountLabel(visible, total, filtersActive) {
        const visibleLabel = visible.toLocaleString('es-MX');
        const totalLabel = total.toLocaleString('es-MX');
        if (!filtersActive || visible === total) {
            return '<strong>' + totalLabel + '</strong> registros en total';
        }
        return '<strong>' + visibleLabel + '</strong> de <strong>' + totalLabel + '</strong> registros';
    }

    function updateRecordsCount(visible, filtersActive) {
        const countHtml = formatRecordsCountLabel(visible, TOTAL_EVENTS, filtersActive);
        if (visibleCountEl) {
            visibleCountEl.innerHTML = countHtml;
        }
        if (visibleCountFooterEl) {
            visibleCountFooterEl.innerHTML = countHtml;
        }
        if (eventsTableFooter) {
            eventsTableFooter.hidden = TOTAL_EVENTS === 0;
        }
    }

    function applyEventsSearch() {
        const term = normalizeSearchTerm(searchInput ? searchInput.value : '');
        const tokens = term === '' ? [] : term.split(/\s+/).filter(Boolean);
        const dateFilterActive = hasActiveDateFilter();
        const filtersActive = tokens.length > 0 || dateFilterActive;
        let visible = 0;

        eventRows.forEach(function (row) {
            const haystack = normalizeSearchTerm(row.getAttribute('data-search') || '');
            const matchesSearch = tokens.length === 0 || tokens.every(function (token) {
                return haystack.indexOf(token) !== -1;
            });
            const matchesDate = rowMatchesDateFilters(row);
            const matches = matchesSearch && matchesDate;
            row.hidden = !matches;
            if (matches) {
                visible += 1;
            }
        });

        updateRecordsCount(visible, filtersActive);
        if (searchClearBtn) {
            searchClearBtn.hidden = term === '';
        }
        if (filterDateClearBtn) {
            filterDateClearBtn.hidden = !dateFilterActive;
        }
        if (noResultsEl) {
            noResultsEl.hidden = visible > 0;
        }
        if (eventsTableWrap) {
            eventsTableWrap.hidden = visible === 0 && (tokens.length > 0 || dateFilterActive);
        }
    }

    function setExportLoading(isLoading, recordCount) {
        if (!exportJsonBtn) {
            return;
        }
        exportJsonBtn.disabled = isLoading;
        if (isLoading) {
            const countLabel = recordCount > 0
                ? (' (' + recordCount.toLocaleString('es-MX') + ' registros)')
                : '';
            exportJsonBtn.textContent = 'Exportando…' + countLabel;
        } else {
            exportJsonBtn.textContent = 'Exportar JSON';
        }
    }

    function exportVisibleRecordsJson() {
        const visibleRows = getVisibleRows();

        if (visibleRows.length === 0) {
            showAlert('No hay registros visibles para exportar.', 'warning');
            return;
        }

        setExportLoading(true, visibleRows.length);
        Swal.fire({
            title: 'Preparando exportación',
            html: 'Generando archivo JSON con <strong>' + visibleRows.length.toLocaleString('es-MX') + '</strong> registros…',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        window.setTimeout(function () {
            try {
                const records = [];

                visibleRows.forEach(function (row) {
                    const rowKey = String(row.getAttribute('data-row-key') || '').trim();
                    if (rowKey && RECORDS_BY_KEY[rowKey]) {
                        records.push(RECORDS_BY_KEY[rowKey]);
                    }
                });

                if (records.length === 0) {
                    throw new Error('No se pudieron preparar los registros para exportar.');
                }

                const payload = {
                    exported_at: new Date().toISOString(),
                    filters: {
                        search: searchInput ? searchInput.value.trim() : '',
                        fecha_registro: { from: activeDateFilters.registro.from, to: activeDateFilters.registro.to },
                        fecha_agenda: { from: activeDateFilters.agenda.from, to: activeDateFilters.agenda.to },
                        fecha_atendido: { from: activeDateFilters.atendido.from, to: activeDateFilters.atendido.to },
                        fecha_cliente: { from: activeDateFilters.cliente.from, to: activeDateFilters.cliente.to }
                    },
                    total: records.length,
                    records: records
                };

                const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
                link.href = url;
                link.download = 'registros_wp_' + stamp + '.json';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

                Swal.fire({
                    icon: 'success',
                    title: 'Exportación lista',
                    text: 'Se descargó el archivo con ' + records.length.toLocaleString('es-MX') + ' registros.',
                    timer: 2200,
                    showConfirmButton: false
                });
            } catch (err) {
                Swal.close();
                showAlert(err.message || 'No se pudo exportar el archivo.', 'error');
            } finally {
                setExportLoading(false);
            }
        }, 80);
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyEventsSearch);
    }
    if (searchClearBtn) {
        searchClearBtn.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            applyEventsSearch();
        });
    }
    if (filterApplyBtn) {
        filterApplyBtn.addEventListener('click', function () {
            syncActiveDateFiltersFromInputs();
            applyEventsSearch();
        });
    }
    if (filterDateClearBtn) {
        filterDateClearBtn.addEventListener('click', function () {
            document.querySelectorAll('[data-filter-key][data-filter-bound]').forEach(function (input) {
                input.value = '';
            });
            syncActiveDateFiltersFromInputs();
            applyEventsSearch();
        });
    }
    if (exportJsonBtn) {
        exportJsonBtn.addEventListener('click', exportVisibleRecordsJson);
    }

    const attendedModal = document.getElementById('editarFechaAtendidoModal');
    const attendedEventId = document.getElementById('editarFechaAtendidoEventId');
    const attendedInput = document.getElementById('editarFechaAtendidoInput');
    const attendedSubtitle = document.getElementById('editarFechaAtendidoModalSubtitle');
    const saveAttendedBtn = document.getElementById('btnGuardarFechaAtendido');

    const clienteModal = document.getElementById('editarFechaClienteModal');
    const clienteEventId = document.getElementById('editarFechaClienteEventId');
    const clienteInput = document.getElementById('editarFechaClienteInput');
    const clienteSubtitle = document.getElementById('editarFechaClienteModalSubtitle');
    const saveClienteBtn = document.getElementById('btnGuardarFechaCliente');

    document.addEventListener('click', function (event) {
        const createCfTrigger = event.target.closest('.js-create-contact-form');
        if (createCfTrigger) {
            const eventId = parseInt(createCfTrigger.getAttribute('data-event-id') || '0', 10);
            const eventLabel = String(createCfTrigger.getAttribute('data-event-label') || '').trim() || ('Evento #' + eventId);
            if (!eventId) {
                return;
            }

            Swal.fire({
                icon: 'question',
                title: 'Crear contact_form',
                text: 'Se creará un registro en contact_form para ' + eventLabel + ' con el flujo estándar WP (tabla_origen = eventos_wp). No se modifica el evento ni se duplica si ya existe.',
                showCancelButton: true,
                confirmButtonText: 'Crear',
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                createCfTrigger.disabled = true;
                const originalText = createCfTrigger.textContent;
                createCfTrigger.textContent = 'Creando…';

                const payload = new URLSearchParams();
                payload.append('event_id', String(eventId));

                requestAsJson('crear_contact_form_evento_wp.php', payload)
                    .then(function (data) {
                        if (!data || !data.success) {
                            throw new Error(data && data.message ? data.message : 'No se pudo crear contact_form');
                        }
                        if (data.contact_form_id) {
                            updateContactFormInRow(eventId, data.contact_form_id);
                        }
                        return showAlert(data.message || 'contact_form creado', data.created === false ? 'info' : 'success');
                    })
                    .catch(function (err) {
                        showAlert(err.message || 'Error', 'error');
                    })
                    .finally(function () {
                        createCfTrigger.disabled = false;
                        createCfTrigger.textContent = originalText;
                    });
            });
            return;
        }

        const attendedTrigger = event.target.closest('.js-edit-attended-date');
        if (attendedTrigger) {
            const id = parseInt(attendedTrigger.getAttribute('data-event-id') || '0', 10);
            if (!id) return;
            attendedEventId.value = String(id);
            attendedInput.value = String(attendedTrigger.getAttribute('data-attended-date') || '').trim() || currentDatetimeLocal();
            attendedSubtitle.textContent = attendedTrigger.getAttribute('data-event-label') || ('Evento #' + id);
            if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(attendedModal).show();
            return;
        }

        const clienteTrigger = event.target.closest('.js-edit-cliente-date');
        if (clienteTrigger) {
            const id = parseInt(clienteTrigger.getAttribute('data-event-id') || '0', 10);
            if (!id) return;
            clienteEventId.value = String(id);
            clienteInput.value = String(clienteTrigger.getAttribute('data-cliente-date') || '').trim() || currentDatetimeLocal();
            clienteSubtitle.textContent = clienteTrigger.getAttribute('data-event-label') || ('Evento #' + id);
            if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(clienteModal).show();
        }
    });

    if (saveAttendedBtn) {
        saveAttendedBtn.addEventListener('click', function () {
            const eventId = parseInt(attendedEventId.value || '0', 10);
            const fecha = attendedInput.value.trim();
            if (!eventId || !fecha) {
                showAlert('Selecciona una fecha válida', 'warning');
                return;
            }
            saveAttendedBtn.disabled = true;
            saveAttendedBtn.textContent = 'Guardando…';
            const payload = new URLSearchParams();
            payload.append('event_id', String(eventId));
            payload.append('fecha_atendido', fecha);
            requestAsJson('actualizar_fecha_atendido_evento_wp.php', payload)
                .then(function (data) {
                    if (!data || !data.success) throw new Error(data && data.message ? data.message : 'No se pudo guardar');
                    if (window.bootstrap) bootstrap.Modal.getInstance(attendedModal).hide();
                    updateAttendedDateInRow(eventId, data.fecha_atendido || '', fecha);
                    return showAlert(data.message || 'Guardado', 'success');
                })
                .catch(function (err) { showAlert(err.message || 'Error', 'error'); })
                .finally(function () {
                    saveAttendedBtn.disabled = false;
                    saveAttendedBtn.textContent = 'Guardar';
                });
        });
    }

    if (saveClienteBtn) {
        saveClienteBtn.addEventListener('click', function () {
            const eventId = parseInt(clienteEventId.value || '0', 10);
            const fecha = clienteInput.value.trim();
            if (!eventId || !fecha) {
                showAlert('Selecciona una fecha válida', 'warning');
                return;
            }
            saveClienteBtn.disabled = true;
            saveClienteBtn.textContent = 'Guardando…';
            const payload = new URLSearchParams();
            payload.append('event_id', String(eventId));
            payload.append('fecha_cliente', fecha);
            requestAsJson('actualizar_fecha_cliente_evento_wp.php', payload)
                .then(function (data) {
                    if (!data || !data.success) throw new Error(data && data.message ? data.message : 'No se pudo guardar');
                    if (window.bootstrap) bootstrap.Modal.getInstance(clienteModal).hide();
                    updateClienteDateInRow(eventId, data.fecha_cliente || '', fecha);
                    return showAlert(data.message || 'Guardado', 'success');
                })
                .catch(function (err) { showAlert(err.message || 'Error', 'error'); })
                .finally(function () {
                    saveClienteBtn.disabled = false;
                    saveClienteBtn.textContent = 'Guardar';
                });
        });
    }

    applyEventsSearch();
})();
</script>
<?php include 'footer.php'; ?>
</body>
</html>
