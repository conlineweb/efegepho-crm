<?php

require_once __DIR__ . '/calendario_estatus_historial_helper.php';

if (!function_exists('ensureWpPlannerOpenNotesTable')) {
    function ensureWpPlannerOpenNotesTable($conn)
    {
        $sql = "CREATE TABLE IF NOT EXISTS `wp_planner_open_notes` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `wedding_planner_id` INT NOT NULL,
            `note_type` VARCHAR(30) NOT NULL DEFAULT 'nota',
            `content` TEXT NOT NULL,
            `created_by` INT DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_wp_planner_open_notes_planner` (`wedding_planner_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $conn->query($sql);
    }
}

if (!function_exists('plannerOpenNoteTypeLabel')) {
    function plannerOpenNoteTypeLabel($type)
    {
        $normalized = strtolower(trim((string) $type));
        if ($normalized === 'acuerdo') {
            return 'Acuerdo comercial';
        }

        return 'Nota abierta';
    }
}

if (!function_exists('plannerOpenNoteTypeClass')) {
    function plannerOpenNoteTypeClass($type)
    {
        $normalized = strtolower(trim((string) $type));
        if ($normalized === 'acuerdo') {
            return 'open-note-type-acuerdo';
        }

        return 'open-note-type-nota';
    }
}

if (!function_exists('loadWpPlannerOpenNotes')) {
    function loadWpPlannerOpenNotes($conn, $plannerId)
    {
        $plannerId = intval($plannerId);
        if ($plannerId <= 0) {
            return [];
        }

        ensureWpPlannerOpenNotesTable($conn);

        $notes = [];
        $stmt = $conn->prepare(
            'SELECT id, wedding_planner_id, note_type, content, created_by, created_at
             FROM wp_planner_open_notes
             WHERE wedding_planner_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 200'
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $plannerId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['created_by_name'] = tracerResolveUsuarioNombre($conn, $row['created_by'] ?? 0) ?? '';
                $notes[] = $row;
            }
        }

        $stmt->close();

        return $notes;
    }
}
