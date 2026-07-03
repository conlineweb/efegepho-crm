<?php

function leadInteractionsEnsureTable($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS `lead_interactions` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `tabla_origen` VARCHAR(120) NOT NULL,
        `lead_id` INT NOT NULL,
        `original_lead_id` INT DEFAULT NULL,
        `lead_name` VARCHAR(255) DEFAULT NULL,
        `interaction_type` VARCHAR(40) DEFAULT NULL,
        `interaction_date` DATE DEFAULT NULL,
        `interaction_time` VARCHAR(20) DEFAULT NULL,
        `notes` TEXT,
        `outcome` VARCHAR(40) DEFAULT NULL,
        `next_action` VARCHAR(255) DEFAULT NULL,
        `next_action_date` DATE DEFAULT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_lead_interactions_lead` (`tabla_origen`, `lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->query($sql);

    $columns = [
        'original_lead_id' => "ALTER TABLE `lead_interactions` ADD COLUMN `original_lead_id` INT DEFAULT NULL AFTER `lead_id`",
        'lead_name' => "ALTER TABLE `lead_interactions` ADD COLUMN `lead_name` VARCHAR(255) DEFAULT NULL AFTER `original_lead_id`",
        'next_action_completed' => "ALTER TABLE `lead_interactions` ADD COLUMN `next_action_completed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `next_action_date`",
    ];

    foreach ($columns as $column => $alterSql) {
        $check = $conn->query("SHOW COLUMNS FROM `lead_interactions` LIKE '" . $conn->real_escape_string($column) . "'");
        if ($check && $check->num_rows === 0) {
            $conn->query($alterSql);
        }
    }

    $checkInteractionType = $conn->query("SHOW COLUMNS FROM `lead_interactions` LIKE 'interaction_type'");
    if ($checkInteractionType && $checkInteractionType->num_rows > 0) {
        $col = $checkInteractionType->fetch_assoc();
        if (($col['Null'] ?? '') === 'NO') {
            $conn->query("ALTER TABLE `lead_interactions` MODIFY COLUMN `interaction_type` VARCHAR(40) DEFAULT NULL");
        }
    }
}

function leadInteractionsResolveLeadName($conn, $tablaOrigen, $leadId)
{
    $origen = strtolower(trim((string) $tablaOrigen));
    $leadId = intval($leadId);
    if ($origen === '' || $leadId <= 0) {
        return '';
    }

    if (in_array($origen, ['wedding_planners', 'wedding_planner'], true)) {
        $stmt = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(empresa_wp), ''), NULLIF(TRIM(full_name), ''), NULLIF(TRIM(campaign_name), ''), CONCAT('WP #', id)) AS label
            FROM wedding_planners WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $leadId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && ($row = $res->fetch_assoc())) {
                    $label = trim((string) ($row['label'] ?? ''));
                    $stmt->close();
                    return $label;
                }
            }
            $stmt->close();
        }
        return 'WP #' . $leadId;
    }

    if ($origen === 'contact_form') {
        $stmt = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(names), ''), NULLIF(TRIM(campaign_name), ''), CONCAT('Lead #', id)) AS label
            FROM contact_form WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $leadId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && ($row = $res->fetch_assoc())) {
                    $label = trim((string) ($row['label'] ?? ''));
                    $stmt->close();
                    return $label;
                }
            }
            $stmt->close();
        }
        return 'Lead #' . $leadId;
    }

    if ($origen === 'eventos_wp') {
        $stmt = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(wp.empresa_wp), ''), NULLIF(TRIM(wp.full_name), ''), NULLIF(TRIM(wp.campaign_name), ''), CONCAT('WP #', wp.id)) AS label
            FROM eventos_wp e
            LEFT JOIN wedding_planners wp ON wp.id = e.wedding_planner_id
            WHERE e.id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $leadId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && ($row = $res->fetch_assoc())) {
                    $label = trim((string) ($row['label'] ?? ''));
                    $stmt->close();
                    return $label;
                }
            }
            $stmt->close();
        }
        return 'Evento WP #' . $leadId;
    }

    if ($origen === 'wp_citas_leads') {
        $stmt = $conn->prepare("SELECT COALESCE(NULLIF(TRIM(wp.empresa_wp), ''), NULLIF(TRIM(wp.full_name), ''), NULLIF(TRIM(wp.campaign_name), ''), CONCAT('WP #', wp.id)) AS label
            FROM wp_citas_leads wcl
            LEFT JOIN wedding_planners wp ON wp.id = wcl.id_wedding_planner
            WHERE wcl.id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $leadId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && ($row = $res->fetch_assoc())) {
                    $label = trim((string) ($row['label'] ?? ''));
                    $stmt->close();
                    return $label;
                }
            }
            $stmt->close();
        }
        return 'WP Lead #' . $leadId;
    }

    if (preg_match('/^[a-zA-Z0-9_]+$/', $origen)) {
        $sql = "SELECT * FROM `" . $origen . "` WHERE id = " . intval($leadId) . " LIMIT 1";
        $res = $conn->query($sql);
        if ($res && ($row = $res->fetch_assoc())) {
            foreach (['names', 'full_name', 'name', 'nombre', 'campaign_name', 'empresa_wp'] as $field) {
                $value = trim((string) ($row[$field] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }
    }

    return ucfirst(str_replace('_', ' ', $origen)) . ' #' . $leadId;
}

function leadInteractionsNormalizeLeadName($leadName)
{
    $leadName = trim((string) $leadName);
    if ($leadName === '') {
        return null;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($leadName, 0, 255, 'UTF-8');
    }
    return substr($leadName, 0, 255);
}
