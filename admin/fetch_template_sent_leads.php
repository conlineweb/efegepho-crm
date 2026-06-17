<?php
header('Content-Type: application/json; charset=utf-8');
include 'conn.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_POST['template_id'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

$templateId = intval($_POST['template_id']);
if ($templateId <= 0) {
    echo json_encode(['success' => false, 'message' => 'template_id inválido']);
    exit;
}

// Ensure click table/column exist for environments not yet migrated
$conn->query("CREATE TABLE IF NOT EXISTS `email_clicks` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `lead_id` INT DEFAULT NULL,
    `tabla_origen` VARCHAR(255) DEFAULT NULL,
    `correo` TINYINT DEFAULT NULL,
    `template_id` INT DEFAULT NULL,
    `url` TEXT,
    `clicked_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `ip` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

$ckClickTpl = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_clicks' AND COLUMN_NAME = 'template_id'");
if ($ckClickTpl && $ckClickTpl->num_rows === 0) {
    $conn->query("ALTER TABLE `email_clicks` ADD COLUMN `template_id` INT NULL AFTER `correo`");
}

// fetch sent_log for the template
if ($stmt = $conn->prepare("SELECT sent_log FROM marketing_templates WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $templateId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta']);
    exit;
}

$sentLogRaw = $row['sent_log'] ?? null;
if (!$sentLogRaw) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$entries = json_decode($sentLogRaw, true);
if (!is_array($entries)) {
    echo json_encode(['success' => false, 'message' => 'Formato inválido de sent_log']);
    exit;
}

$out = [];

// common name and email candidates
$nameCandidates = ['full_name', 'fullname', 'name', 'first_name', 'nombre', 'nombre_completo', 'firstName'];
$emailCandidates = ['email', 'correo', 'mail', 'email_address'];

foreach ($entries as $entry) {
    if (!isset($entry['tabla_origen']) || !isset($entry['lead_id'])) continue;
    $tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $entry['tabla_origen']);
    $leadId = intval($entry['lead_id']);
    $sentAt = $entry['sent_at'] ?? null;
    $success = isset($entry['success']) ? intval($entry['success']) : 0;
    $mail_error = $entry['mail_error'] ?? null;

    $leadName = null;
    $leadEmail = $entry['email'] ?? null;

    // try to fetch lead details if table exists
    if ($tabla !== '') {
        // check table exists
        $check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tabla) . "'");
        if ($check && $check->num_rows > 0) {
            // fetch columns to detect name/email
            $colsRes = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tabla) . "`");
            $cols = [];
            while ($c = $colsRes->fetch_assoc()) {
                $cols[] = $c['Field'];
            }
            $nameCol = null; $emailCol = null;
            foreach ($nameCandidates as $nc) if (in_array($nc, $cols)) { $nameCol = $nc; break; }
            foreach ($emailCandidates as $ec) if (in_array($ec, $cols)) { $emailCol = $ec; break; }

            if ($stmtLead = $conn->prepare("SELECT id" . ($nameCol ? ", `" . $nameCol . "`" : "") . ($emailCol ? ", `" . $emailCol . "`" : "") . " FROM `" . $conn->real_escape_string($tabla) . "` WHERE id = ? LIMIT 1")) {
                $stmtLead->bind_param('i', $leadId);
                $stmtLead->execute();
                $rL = $stmtLead->get_result();
                if ($rL && $rL->num_rows > 0) {
                    $leadRow = $rL->fetch_assoc();
                    if ($nameCol && isset($leadRow[$nameCol])) $leadName = $leadRow[$nameCol];
                    if ($emailCol && isset($leadRow[$emailCol])) $leadEmail = $leadRow[$emailCol];
                }
                $stmtLead->close();
            }
        }
    }

    // determine if this sent entry has an associated open (prefer template_id when available)
    $opened = 0;
    $opened_at = null;
    $clicked = 0;
    $clicked_at = null;
    $click_count = 0;
    $templateIdEntry = isset($entry['template_id']) ? intval($entry['template_id']) : null;

    if ($tabla !== '') {
        if ($templateIdEntry !== null && !empty($sentAt)) {
            if ($qOpen = $conn->prepare("SELECT opened_at FROM email_opens WHERE tabla_origen = ? AND lead_id = ? AND template_id = ? AND opened_at >= ? ORDER BY opened_at ASC LIMIT 1")) {
                $qOpen->bind_param('siis', $tabla, $leadId, $templateIdEntry, $sentAt);
                $qOpen->execute();
                $resOpen = $qOpen->get_result();
                if ($resOpen && $resOpen->num_rows > 0) {
                    $ro = $resOpen->fetch_assoc();
                    $opened = 1;
                    $opened_at = $ro['opened_at'];
                }
                $qOpen->close();
            }
        }

        // if not found and template_id exists but no sentAt or still not found, try any open with template
        if ($opened === 0 && $templateIdEntry !== null) {
            if ($qOpen = $conn->prepare("SELECT opened_at FROM email_opens WHERE tabla_origen = ? AND lead_id = ? AND template_id = ? ORDER BY opened_at ASC LIMIT 1")) {
                $qOpen->bind_param('sii', $tabla, $leadId, $templateIdEntry);
                $qOpen->execute();
                $resOpen = $qOpen->get_result();
                if ($resOpen && $resOpen->num_rows > 0) {
                    $ro = $resOpen->fetch_assoc();
                    $opened = 1;
                    $opened_at = $ro['opened_at'];
                }
                $qOpen->close();
            }
        }

        // fallback to original logic (no template_id available)
        if ($opened === 0 && !empty($sentAt)) {
            if ($qOpen = $conn->prepare("SELECT opened_at FROM email_opens WHERE tabla_origen = ? AND lead_id = ? AND opened_at >= ? ORDER BY opened_at ASC LIMIT 1")) {
                $qOpen->bind_param('sis', $tabla, $leadId, $sentAt);
                $qOpen->execute();
                $resOpen = $qOpen->get_result();
                if ($resOpen && $resOpen->num_rows > 0) {
                    $ro = $resOpen->fetch_assoc();
                    $opened = 1;
                    $opened_at = $ro['opened_at'];
                }
                $qOpen->close();
            }
        }

        // last resort: any open for this lead/table
        if ($opened === 0) {
            if ($qOpen = $conn->prepare("SELECT opened_at FROM email_opens WHERE tabla_origen = ? AND lead_id = ? ORDER BY opened_at ASC LIMIT 1")) {
                $qOpen->bind_param('si', $tabla, $leadId);
                $qOpen->execute();
                $resOpen = $qOpen->get_result();
                if ($resOpen && $resOpen->num_rows > 0) {
                    $ro = $resOpen->fetch_assoc();
                    $opened = 1;
                    $opened_at = $ro['opened_at'];
                }
                $qOpen->close();
            }
        }

        // clicks: prefer template_id + sent_at
        if ($templateIdEntry !== null && !empty($sentAt)) {
            if ($qClick = $conn->prepare("SELECT clicked_at FROM email_clicks WHERE tabla_origen = ? AND lead_id = ? AND template_id = ? AND clicked_at >= ? ORDER BY clicked_at ASC LIMIT 1")) {
                $qClick->bind_param('siis', $tabla, $leadId, $templateIdEntry, $sentAt);
                $qClick->execute();
                $resClick = $qClick->get_result();
                if ($resClick && $resClick->num_rows > 0) {
                    $rc = $resClick->fetch_assoc();
                    $clicked = 1;
                    $clicked_at = $rc['clicked_at'];
                }
                $qClick->close();
            }

            if ($qCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM email_clicks WHERE tabla_origen = ? AND lead_id = ? AND template_id = ? AND clicked_at >= ?")) {
                $qCount->bind_param('siis', $tabla, $leadId, $templateIdEntry, $sentAt);
                $qCount->execute();
                $resCount = $qCount->get_result();
                if ($resCount && $resCount->num_rows > 0) {
                    $rowCount = $resCount->fetch_assoc();
                    $click_count = intval($rowCount['cnt'] ?? 0);
                }
                $qCount->close();
            }
        }

        // clicks fallback: template_id without sent_at restriction
        if ($templateIdEntry !== null && $clicked === 0) {
            if ($qClick = $conn->prepare("SELECT clicked_at FROM email_clicks WHERE tabla_origen = ? AND lead_id = ? AND template_id = ? ORDER BY clicked_at ASC LIMIT 1")) {
                $qClick->bind_param('sii', $tabla, $leadId, $templateIdEntry);
                $qClick->execute();
                $resClick = $qClick->get_result();
                if ($resClick && $resClick->num_rows > 0) {
                    $rc = $resClick->fetch_assoc();
                    $clicked = 1;
                    $clicked_at = $rc['clicked_at'];
                }
                $qClick->close();
            }

            if ($click_count === 0) {
                if ($qCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM email_clicks WHERE tabla_origen = ? AND lead_id = ? AND template_id = ?")) {
                    $qCount->bind_param('sii', $tabla, $leadId, $templateIdEntry);
                    $qCount->execute();
                    $resCount = $qCount->get_result();
                    if ($resCount && $resCount->num_rows > 0) {
                        $rowCount = $resCount->fetch_assoc();
                        $click_count = intval($rowCount['cnt'] ?? 0);
                    }
                    $qCount->close();
                }
            }
        }

        // clicks fallback: no template_id available
        if ($clicked === 0 && !empty($sentAt)) {
            if ($qClick = $conn->prepare("SELECT clicked_at FROM email_clicks WHERE tabla_origen = ? AND lead_id = ? AND clicked_at >= ? ORDER BY clicked_at ASC LIMIT 1")) {
                $qClick->bind_param('sis', $tabla, $leadId, $sentAt);
                $qClick->execute();
                $resClick = $qClick->get_result();
                if ($resClick && $resClick->num_rows > 0) {
                    $rc = $resClick->fetch_assoc();
                    $clicked = 1;
                    $clicked_at = $rc['clicked_at'];
                }
                $qClick->close();
            }

            if ($click_count === 0) {
                if ($qCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM email_clicks WHERE tabla_origen = ? AND lead_id = ? AND clicked_at >= ?")) {
                    $qCount->bind_param('sis', $tabla, $leadId, $sentAt);
                    $qCount->execute();
                    $resCount = $qCount->get_result();
                    if ($resCount && $resCount->num_rows > 0) {
                        $rowCount = $resCount->fetch_assoc();
                        $click_count = intval($rowCount['cnt'] ?? 0);
                    }
                    $qCount->close();
                }
            }
        }

        if ($clicked === 0) {
            if ($qClick = $conn->prepare("SELECT clicked_at FROM email_clicks WHERE tabla_origen = ? AND lead_id = ? ORDER BY clicked_at ASC LIMIT 1")) {
                $qClick->bind_param('si', $tabla, $leadId);
                $qClick->execute();
                $resClick = $qClick->get_result();
                if ($resClick && $resClick->num_rows > 0) {
                    $rc = $resClick->fetch_assoc();
                    $clicked = 1;
                    $clicked_at = $rc['clicked_at'];
                }
                $qClick->close();
            }

            if ($click_count === 0) {
                if ($qCount = $conn->prepare("SELECT COUNT(*) AS cnt FROM email_clicks WHERE tabla_origen = ? AND lead_id = ?")) {
                    $qCount->bind_param('si', $tabla, $leadId);
                    $qCount->execute();
                    $resCount = $qCount->get_result();
                    if ($resCount && $resCount->num_rows > 0) {
                        $rowCount = $resCount->fetch_assoc();
                        $click_count = intval($rowCount['cnt'] ?? 0);
                    }
                    $qCount->close();
                }
            }
        }
    }

    $out[] = [
        'tabla_origen' => $entry['tabla_origen'],
        'lead_id' => $leadId,
        'lead_name' => $leadName,
        'email' => $leadEmail,
        'sent_at' => $sentAt,
        'success' => $success,
        'mail_error' => $mail_error,
        'template_id' => isset($entry['template_id']) ? intval($entry['template_id']) : null,
        'opened' => $opened,
        'opened_at' => $opened_at,
        'clicked' => $clicked,
        'clicked_at' => $clicked_at,
        'click_count' => $click_count
    ];
}

// sort by sent_at desc
usort($out, function ($a, $b) {
    $ta = !empty($a['sent_at']) ? strtotime($a['sent_at']) : 0;
    $tb = !empty($b['sent_at']) ? strtotime($b['sent_at']) : 0;
    return $tb <=> $ta;
});

echo json_encode(['success' => true, 'data' => $out]);
$conn->close();
