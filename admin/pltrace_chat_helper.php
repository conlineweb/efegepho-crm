<?php
/**
 * Chat interno de leads integrado en la trazabilidad (pltrace).
 * Autocontenido: no requiere calendario_estatus_historial_helper.php al cargar.
 */

if (!function_exists('pltraceChatDisplayTimezone')) {
    function pltraceChatDisplayTimezone()
    {
        static $tz = null;
        if ($tz === null) {
            $tz = new DateTimeZone('America/Mexico_City');
        }
        return $tz;
    }
}

if (!function_exists('pltraceChatNormalizeDateTime')) {
    function pltraceChatNormalizeDateTime($value)
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '' || strpos($value, '0000-00-00') === 0) {
            return null;
        }

        $displayTz = pltraceChatDisplayTimezone();

        if (strpos($value, 'T') !== false) {
            try {
                $hasOffset = (bool) preg_match('/([Zz]|[+-]\d{2}:?\d{2})$/', $value);
                $dt = $hasOffset
                    ? new DateTime($value)
                    : new DateTime($value, $displayTz);
                $dt->setTimezone($displayTz);
                return $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Continúa con el fallback inferior.
            }
        }

        try {
            $dt = new DateTime($value, $displayTz);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $ts = strtotime($value);
            if ($ts === false || $ts <= 0) {
                return null;
            }
            $dt = new DateTime('@' . $ts);
            $dt->setTimezone($displayTz);
            return $dt->format('Y-m-d H:i:s');
        }
    }
}

if (!function_exists('pltraceChatFormatDisplayDateTime')) {
    function pltraceChatFormatDisplayDateTime($value)
    {
        $normalized = pltraceChatNormalizeDateTime($value);
        if ($normalized === null) {
            return '—';
        }
        try {
            $dt = new DateTime((string) $normalized, pltraceChatDisplayTimezone());
            return $dt->format('d/m/Y h:i a');
        } catch (Exception $e) {
            return '—';
        }
    }
}

if (!function_exists('ensurePltraceChatTables')) {
    function ensurePltraceChatTables($conn)
    {
        static $verificada = false;
        if ($verificada) {
            return true;
        }

        $sqlConv = "CREATE TABLE IF NOT EXISTS `pltrace_lead_conversaciones` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `tabla_origen` VARCHAR(100) NOT NULL,
            `orig_id` INT NOT NULL,
            `contact_form_id` INT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_tabla_orig` (`tabla_origen`, `orig_id`),
            KEY `idx_contact_form` (`contact_form_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3";

        $sqlMsg = "CREATE TABLE IF NOT EXISTS `pltrace_lead_mensajes` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `conversacion_id` INT NOT NULL,
            `usuario_id` INT NOT NULL,
            `usuario_nombre` VARCHAR(200) NOT NULL,
            `usuario_rol` VARCHAR(50) NOT NULL,
            `mensaje` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_conv_created` (`conversacion_id`, `created_at`),
            KEY `idx_conv_id` (`conversacion_id`, `id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3";

        $sqlRead = "CREATE TABLE IF NOT EXISTS `pltrace_lead_mensaje_lecturas` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `mensaje_id` BIGINT NOT NULL,
            `usuario_id` INT NOT NULL,
            `leido_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mensaje_usuario` (`mensaje_id`, `usuario_id`),
            KEY `idx_usuario` (`usuario_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3";

        $sqlArchivos = "CREATE TABLE IF NOT EXISTS `pltrace_lead_archivos` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `mensaje_id` BIGINT NOT NULL,
            `nombre_original` VARCHAR(255) NOT NULL,
            `stored_name` VARCHAR(255) NOT NULL,
            `ruta_relativa` VARCHAR(500) NOT NULL,
            `mime_type` VARCHAR(150) NOT NULL,
            `extension` VARCHAR(20) NOT NULL,
            `tamano_bytes` BIGINT NOT NULL DEFAULT 0,
            `categoria` VARCHAR(30) NOT NULL DEFAULT 'other',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_mensaje` (`mensaje_id`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3";

        $ok = $conn->query($sqlConv) && $conn->query($sqlMsg) && $conn->query($sqlRead) && $conn->query($sqlArchivos);
        if ($ok) {
            $colTipo = $conn->query("SHOW COLUMNS FROM `pltrace_lead_mensajes` LIKE 'tipo_mensaje'");
            if ($colTipo && $colTipo->num_rows === 0) {
                $conn->query("ALTER TABLE `pltrace_lead_mensajes` ADD COLUMN `tipo_mensaje` VARCHAR(20) NOT NULL DEFAULT 'texto' AFTER `mensaje`");
            }
            $verificada = true;
        }
        return (bool) $ok;
    }
}

if (!function_exists('pltraceChatMaxUploadBytes')) {
    function pltraceChatMaxUploadBytes()
    {
        return 50 * 1024 * 1024;
    }
}

if (!function_exists('pltraceChatUploadBaseDir')) {
    function pltraceChatUploadBaseDir()
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pltrace_chat';
    }
}

if (!function_exists('pltraceChatSanitizePathSegment')) {
    function pltraceChatSanitizePathSegment($value)
    {
        $value = trim((string) $value);
        $value = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $value);
        return $value !== '' ? $value : 'unknown';
    }
}

if (!function_exists('pltraceChatClassifyFile')) {
    /**
     * @return array{categoria:string,extension:string,mime_type:string}
     */
    function pltraceChatClassifyFile($originalName, $mimeType = '')
    {
        $originalName = trim((string) $originalName);
        $mimeType = strtolower(trim((string) $mimeType));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = 'bin';
        }

        $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        $videoExt = ['mp4', 'webm', 'ogg', 'mov', 'm4v', 'avi'];
        $pdfExt = ['pdf'];
        $docExt = ['doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'rtf', 'ppt', 'pptx'];
        $archiveExt = ['zip', 'rar', '7z', 'tar', 'gz'];

        $categoria = 'other';
        if (strpos($mimeType, 'image/') === 0 || in_array($extension, $imageExt, true)) {
            $categoria = 'image';
        } elseif (strpos($mimeType, 'video/') === 0 || in_array($extension, $videoExt, true)) {
            $categoria = 'video';
        } elseif ($mimeType === 'application/pdf' || in_array($extension, $pdfExt, true)) {
            $categoria = 'pdf';
        } elseif (in_array($extension, $archiveExt, true)) {
            $categoria = 'archive';
        } elseif (in_array($extension, $docExt, true)) {
            $categoria = 'document';
        }

        return [
            'categoria'  => $categoria,
            'extension'  => $extension,
            'mime_type'  => $mimeType !== '' ? $mimeType : 'application/octet-stream',
        ];
    }
}

if (!function_exists('pltraceChatFormatFileSize')) {
    function pltraceChatFormatFileSize($bytes)
    {
        $bytes = (int) $bytes;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }
        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
}

if (!function_exists('pltraceChatBuildArchivoPayload')) {
    function pltraceChatBuildArchivoPayload(array $row)
    {
        $messageId = (int) ($row['id'] ?? 0);
        $archivoId = (int) ($row['archivo_id'] ?? 0);
        if ($messageId <= 0 || $archivoId <= 0) {
            return null;
        }

        $categoria = (string) ($row['archivo_categoria'] ?? 'other');
        $mimeType = (string) ($row['archivo_mime_type'] ?? 'application/octet-stream');
        $extension = (string) ($row['archivo_extension'] ?? '');
        $nombreOriginal = (string) ($row['archivo_nombre_original'] ?? 'archivo');
        $tamanoBytes = (int) ($row['archivo_tamano_bytes'] ?? 0);

        $baseUrl = 'pltrace_chat_file.php?msg_id=' . $messageId;
        $inlineUrl = $baseUrl . '&inline=1';
        $downloadUrl = $baseUrl . '&download=1';

        return [
            'archivo_id'       => $archivoId,
            'nombre'           => $nombreOriginal,
            'mime_type'        => $mimeType,
            'extension'        => $extension,
            'categoria'        => $categoria,
            'tipo_label'       => pltraceChatCategoriaLabel($categoria, $extension),
            'tamano_bytes'     => $tamanoBytes,
            'tamano_display'   => pltraceChatFormatFileSize($tamanoBytes),
            'url'              => $inlineUrl,
            'download_url'     => $downloadUrl,
            'preview_url'      => in_array($categoria, ['image', 'video', 'pdf'], true) ? $inlineUrl : '',
        ];
    }
}

if (!function_exists('pltraceChatCategoriaLabel')) {
    function pltraceChatCategoriaLabel($categoria, $extension = '')
    {
        $map = [
            'image'    => 'Imagen',
            'video'    => 'Video',
            'pdf'      => 'PDF',
            'document' => 'Documento',
            'archive'  => 'Archivo comprimido',
            'other'    => 'Archivo',
        ];
        $label = $map[(string) $categoria] ?? 'Archivo';
        $extension = strtoupper(trim((string) $extension));
        if ($extension !== '') {
            return $label . ' · ' . $extension;
        }
        return $label;
    }
}

if (!function_exists('pltraceChatResolveRoleLabel')) {
    function pltraceChatResolveRoleLabel($tipoUsu)
    {
        $map = [
            0 => 'Admin',
            1 => 'Vendedor',
            2 => 'Proyectos',
            3 => 'Pagos',
            4 => 'Consulta',
            5 => 'Líder de Planners',
        ];
        $tipoUsu = (int) $tipoUsu;
        return $map[$tipoUsu] ?? 'Usuario';
    }
}

if (!function_exists('pltraceChatValidateTablaOrigen')) {
    /**
     * @return array{ok:bool,message?:string}
     */
    function pltraceChatValidateTablaOrigen($conn, $tablaOrigen)
    {
        $tablaOrigen = trim((string) $tablaOrigen);
        if ($tablaOrigen === '') {
            return ['ok' => false, 'message' => 'Tabla de origen requerida'];
        }

        $stmt = $conn->prepare('SELECT nombre FROM tablas_leads WHERE nombre = ? LIMIT 1');
        if (!$stmt) {
            return ['ok' => false, 'message' => 'No se pudo validar la tabla'];
        }
        $stmt->bind_param('s', $tablaOrigen);
        $stmt->execute();
        $res = $stmt->get_result();
        $found = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$found) {
            return ['ok' => false, 'message' => 'Tabla no permitida'];
        }

        return ['ok' => true];
    }
}

if (!function_exists('pltraceChatGetOrCreateConversation')) {
    /**
     * @return array{success:bool,conversacion_id?:int,error?:string}
     */
    function pltraceChatGetOrCreateConversation($conn, $tablaOrigen, $origId, $cfId = 0)
    {
        if (!($conn instanceof mysqli)) {
            return ['success' => false, 'error' => 'Conexión inválida'];
        }

        ensurePltraceChatTables($conn);

        $tablaOrigen = trim((string) $tablaOrigen);
        $origId = (int) $origId;
        $cfId = (int) $cfId;

        if ($tablaOrigen === '' || $origId <= 0) {
            return ['success' => false, 'error' => 'Parámetros inválidos'];
        }

        $validation = pltraceChatValidateTablaOrigen($conn, $tablaOrigen);
        if (empty($validation['ok'])) {
            return ['success' => false, 'error' => $validation['message'] ?? 'Tabla no permitida'];
        }

        $stmt = $conn->prepare(
            'SELECT id FROM pltrace_lead_conversaciones WHERE tabla_origen = ? AND orig_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return ['success' => false, 'error' => 'No se pudo consultar la conversación'];
        }
        $stmt->bind_param('si', $tablaOrigen, $origId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            return ['success' => true, 'conversacion_id' => (int) $row['id']];
        }

        $now = date('Y-m-d H:i:s');
        if ($cfId > 0) {
            $ins = $conn->prepare(
                'INSERT INTO pltrace_lead_conversaciones (tabla_origen, orig_id, contact_form_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?)'
            );
            if (!$ins) {
                return ['success' => false, 'error' => 'No se pudo crear la conversación'];
            }
            $ins->bind_param('siiss', $tablaOrigen, $origId, $cfId, $now, $now);
        } else {
            $ins = $conn->prepare(
                'INSERT INTO pltrace_lead_conversaciones (tabla_origen, orig_id, contact_form_id, created_at, updated_at)
                 VALUES (?, ?, NULL, ?, ?)'
            );
            if (!$ins) {
                return ['success' => false, 'error' => 'No se pudo crear la conversación'];
            }
            $ins->bind_param('siss', $tablaOrigen, $origId, $now, $now);
        }
        if (!$ins->execute()) {
            $ins->close();
            return ['success' => false, 'error' => 'No se pudo crear la conversación'];
        }
        $newId = (int) $ins->insert_id;
        $ins->close();

        return ['success' => true, 'conversacion_id' => $newId];
    }
}

if (!function_exists('pltraceChatBuildMessageEvent')) {
    function pltraceChatBuildMessageEvent(array $row, $currentUserId)
    {
        $createdAt = pltraceChatNormalizeDateTime($row['created_at'] ?? null) ?? date('Y-m-d H:i:s');
        $usuarioId = (int) ($row['usuario_id'] ?? 0);
        $messageId = (int) ($row['id'] ?? 0);
        $tipoMensaje = trim((string) ($row['tipo_mensaje'] ?? 'texto'));
        if ($tipoMensaje === '') {
            $tipoMensaje = 'texto';
        }

        $archivoPayload = null;
        if ($tipoMensaje === 'archivo') {
            $archivoPayload = pltraceChatBuildArchivoPayload($row);
        }

        $detalle = (string) ($row['mensaje'] ?? '');
        if ($tipoMensaje === 'archivo' && $archivoPayload) {
            $detalle = $archivoPayload['nombre'];
        }

        return [
            'sort_ts'       => strtotime($createdAt),
            'sort_order'    => 30,
            'fecha'         => $createdAt,
            'fecha_display' => pltraceChatFormatDisplayDateTime($createdAt),
            'tipo'          => 'chat_mensaje',
            'detalle'       => $detalle,
            'chat'          => [
                'message_id'     => $messageId,
                'usuario_id'     => $usuarioId,
                'usuario_nombre' => (string) ($row['usuario_nombre'] ?? ''),
                'usuario_rol'    => (string) ($row['usuario_rol'] ?? ''),
                'is_mine'        => ($usuarioId > 0 && $usuarioId === (int) $currentUserId),
                'tipo_contenido' => $tipoMensaje,
                'archivo'        => $archivoPayload,
            ],
            'es_estimado'   => false,
        ];
    }
}

if (!function_exists('pltraceChatMessageSelectSql')) {
    function pltraceChatMessageSelectSql()
    {
        return 'SELECT m.id, m.conversacion_id, m.usuario_id, m.usuario_nombre, m.usuario_rol, m.mensaje, m.tipo_mensaje, m.created_at,
                       a.id AS archivo_id, a.nombre_original AS archivo_nombre_original, a.mime_type AS archivo_mime_type,
                       a.extension AS archivo_extension, a.tamano_bytes AS archivo_tamano_bytes, a.categoria AS archivo_categoria,
                       a.ruta_relativa AS archivo_ruta_relativa
                FROM pltrace_lead_mensajes m
                LEFT JOIN pltrace_lead_archivos a ON a.mensaje_id = m.id';
    }
}

if (!function_exists('pltraceChatFetchMessagesForLead')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function pltraceChatFetchMessagesForLead($conn, $tablaOrigen, $origId, $currentUserId = 0, $sinceId = 0)
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }

        ensurePltraceChatTables($conn);

        $tablaOrigen = trim((string) $tablaOrigen);
        $origId = (int) $origId;
        $sinceId = (int) $sinceId;

        if ($tablaOrigen === '' || $origId <= 0) {
            return [];
        }

        $convStmt = $conn->prepare(
            'SELECT id FROM pltrace_lead_conversaciones WHERE tabla_origen = ? AND orig_id = ? LIMIT 1'
        );
        if (!$convStmt) {
            return [];
        }
        $convStmt->bind_param('si', $tablaOrigen, $origId);
        $convStmt->execute();
        $convRes = $convStmt->get_result();
        $convRow = $convRes ? $convRes->fetch_assoc() : null;
        $convStmt->close();

        if (!$convRow) {
            return [];
        }

        $conversacionId = (int) $convRow['id'];

        if ($sinceId > 0) {
            $msgStmt = $conn->prepare(
                pltraceChatMessageSelectSql() . '
                 WHERE m.conversacion_id = ? AND m.id > ?
                 ORDER BY m.created_at ASC, m.id ASC'
            );
            if (!$msgStmt) {
                return [];
            }
            $msgStmt->bind_param('ii', $conversacionId, $sinceId);
        } else {
            $msgStmt = $conn->prepare(
                pltraceChatMessageSelectSql() . '
                 WHERE m.conversacion_id = ?
                 ORDER BY m.created_at ASC, m.id ASC'
            );
            if (!$msgStmt) {
                return [];
            }
            $msgStmt->bind_param('i', $conversacionId);
        }

        $msgStmt->execute();
        $msgRes = $msgStmt->get_result();
        $events = [];
        while ($msgRes && ($row = $msgRes->fetch_assoc())) {
            $events[] = pltraceChatBuildMessageEvent($row, $currentUserId);
        }
        $msgStmt->close();

        return $events;
    }
}

if (!function_exists('tracerAppendChatMessagesToEvents')) {
    function tracerAppendChatMessagesToEvents($conn, array &$events, $tablaOrigen, $origId, $currentUserId = 0)
    {
        $chatEvents = pltraceChatFetchMessagesForLead($conn, $tablaOrigen, $origId, $currentUserId, 0);
        foreach ($chatEvents as $ev) {
            $events[] = $ev;
        }
    }
}

if (!function_exists('pltraceChatSendMessage')) {
    /**
     * @return array{success:bool,event?:array,message_id?:int,error?:string}
     */
    function pltraceChatSendMessage($conn, $tablaOrigen, $origId, $usuarioId, $mensaje, $cfId = 0)
    {
        if (!($conn instanceof mysqli)) {
            return ['success' => false, 'error' => 'Conexión inválida'];
        }

        $tablaOrigen = trim((string) $tablaOrigen);
        $origId = (int) $origId;
        $usuarioId = (int) $usuarioId;
        $mensaje = trim((string) $mensaje);
        $cfId = (int) $cfId;

        if ($tablaOrigen === '' || $origId <= 0) {
            return ['success' => false, 'error' => 'Parámetros inválidos'];
        }
        if ($usuarioId <= 0) {
            return ['success' => false, 'error' => 'Usuario no autenticado'];
        }
        if ($mensaje === '') {
            return ['success' => false, 'error' => 'El mensaje no puede estar vacío'];
        }
        if (mb_strlen($mensaje, 'UTF-8') > 5000) {
            return ['success' => false, 'error' => 'El mensaje es demasiado largo'];
        }

        $conv = pltraceChatGetOrCreateConversation($conn, $tablaOrigen, $origId, $cfId);
        if (empty($conv['success'])) {
            return ['success' => false, 'error' => $conv['error'] ?? 'No se pudo obtener la conversación'];
        }

        $conversacionId = (int) $conv['conversacion_id'];

        $userStmt = $conn->prepare('SELECT nombre, apePat, tipoUsu FROM usuarios WHERE id = ? LIMIT 1');
        if (!$userStmt) {
            return ['success' => false, 'error' => 'No se pudo consultar el usuario'];
        }
        $userStmt->bind_param('i', $usuarioId);
        $userStmt->execute();
        $userRes = $userStmt->get_result();
        $userRow = $userRes ? $userRes->fetch_assoc() : null;
        $userStmt->close();

        if (!$userRow) {
            return ['success' => false, 'error' => 'Usuario no encontrado'];
        }

        $usuarioNombre = trim(($userRow['nombre'] ?? '') . ' ' . ($userRow['apePat'] ?? ''));
        if ($usuarioNombre === '') {
            $usuarioNombre = 'Usuario #' . $usuarioId;
        }
        $usuarioRol = pltraceChatResolveRoleLabel($userRow['tipoUsu'] ?? 0);

        $now = date('Y-m-d H:i:s');
        $tipoMensaje = 'texto';
        $ins = $conn->prepare(
            'INSERT INTO pltrace_lead_mensajes
                (conversacion_id, usuario_id, usuario_nombre, usuario_rol, mensaje, tipo_mensaje, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$ins) {
            return ['success' => false, 'error' => 'No se pudo guardar el mensaje'];
        }
        $ins->bind_param('iisssss', $conversacionId, $usuarioId, $usuarioNombre, $usuarioRol, $mensaje, $tipoMensaje, $now);
        if (!$ins->execute()) {
            $ins->close();
            return ['success' => false, 'error' => 'No se pudo guardar el mensaje'];
        }
        $messageId = (int) $ins->insert_id;
        $ins->close();

        $upd = $conn->prepare('UPDATE pltrace_lead_conversaciones SET updated_at = ? WHERE id = ?');
        if ($upd) {
            $upd->bind_param('si', $now, $conversacionId);
            $upd->execute();
            $upd->close();
        }

        $event = pltraceChatBuildMessageEvent([
            'id'             => $messageId,
            'usuario_id'     => $usuarioId,
            'usuario_nombre' => $usuarioNombre,
            'usuario_rol'    => $usuarioRol,
            'mensaje'        => $mensaje,
            'tipo_mensaje'   => 'texto',
            'created_at'     => $now,
        ], $usuarioId);

        return [
            'success'    => true,
            'message_id' => $messageId,
            'event'      => $event,
        ];
    }
}

if (!function_exists('pltraceChatMarkConversationRead')) {
    /**
     * @return array{success:bool,marked?:int,error?:string}
     */
    function pltraceChatMarkConversationRead($conn, $tablaOrigen, $origId, $usuarioId)
    {
        if (!($conn instanceof mysqli)) {
            return ['success' => false, 'error' => 'Conexión inválida'];
        }

        ensurePltraceChatTables($conn);

        $tablaOrigen = trim((string) $tablaOrigen);
        $origId = (int) $origId;
        $usuarioId = (int) $usuarioId;

        if ($tablaOrigen === '' || $origId <= 0 || $usuarioId <= 0) {
            return ['success' => false, 'error' => 'Parámetros inválidos'];
        }

        $convStmt = $conn->prepare(
            'SELECT id FROM pltrace_lead_conversaciones WHERE tabla_origen = ? AND orig_id = ? LIMIT 1'
        );
        if (!$convStmt) {
            return ['success' => false, 'error' => 'No se pudo consultar la conversación'];
        }
        $convStmt->bind_param('si', $tablaOrigen, $origId);
        $convStmt->execute();
        $convRes = $convStmt->get_result();
        $convRow = $convRes ? $convRes->fetch_assoc() : null;
        $convStmt->close();

        if (!$convRow) {
            return ['success' => true, 'marked' => 0];
        }

        $conversacionId = (int) $convRow['id'];
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO pltrace_lead_mensaje_lecturas (mensaje_id, usuario_id, leido_at)
                SELECT m.id, ?, ?
                FROM pltrace_lead_mensajes m
                LEFT JOIN pltrace_lead_mensaje_lecturas r
                    ON r.mensaje_id = m.id AND r.usuario_id = ?
                WHERE m.conversacion_id = ?
                  AND m.usuario_id != ?
                  AND r.id IS NULL";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return ['success' => false, 'error' => 'No se pudo marcar como leído'];
        }
        $stmt->bind_param('isiii', $usuarioId, $now, $usuarioId, $conversacionId, $usuarioId);
        $ok = $stmt->execute();
        $marked = (int) $stmt->affected_rows;
        $stmt->close();

        return ['success' => (bool) $ok, 'marked' => $marked];
    }
}

if (!function_exists('pltraceChatGetUnreadCounts')) {
    /**
     * @param array<int, string> $traceKeys  e.g. ["organic_leads|42", "wp_citas_leads|7"]
     * @return array<string, int>
     */
    function pltraceChatGetUnreadCounts($conn, $usuarioId, array $traceKeys)
    {
        if (!($conn instanceof mysqli)) {
            return [];
        }

        ensurePltraceChatTables($conn);

        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0 || empty($traceKeys)) {
            return [];
        }

        $pairs = [];
        foreach ($traceKeys as $key) {
            $key = trim((string) $key);
            if ($key === '' || strpos($key, '|') === false) {
                continue;
            }
            $parts = explode('|', $key, 2);
            $tabla = trim((string) ($parts[0] ?? ''));
            $origId = (int) ($parts[1] ?? 0);
            if ($tabla === '' || $origId <= 0) {
                continue;
            }
            $pairs[] = ['tabla' => $tabla, 'orig_id' => $origId, 'key' => $tabla . '|' . $origId];
        }

        if (empty($pairs)) {
            return [];
        }

        $counts = [];
        foreach ($pairs as $pair) {
            $counts[$pair['key']] = 0;
        }

        $placeholders = [];
        $types = 'ii';
        $params = [$usuarioId, $usuarioId];

        foreach ($pairs as $pair) {
            $placeholders[] = '(c.tabla_origen = ? AND c.orig_id = ?)';
            $types .= 'si';
            $params[] = $pair['tabla'];
            $params[] = $pair['orig_id'];
        }

        $sql = 'SELECT c.tabla_origen, c.orig_id, COUNT(*) AS unread_count
                FROM pltrace_lead_conversaciones c
                INNER JOIN pltrace_lead_mensajes m ON m.conversacion_id = c.id
                LEFT JOIN pltrace_lead_mensaje_lecturas r
                    ON r.mensaje_id = m.id AND r.usuario_id = ?
                WHERE m.usuario_id != ?
                  AND r.id IS NULL
                  AND (' . implode(' OR ', $placeholders) . ')
                GROUP BY c.tabla_origen, c.orig_id';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $counts;
        }

        $bindArgs = [$types];
        foreach ($params as $idx => $value) {
            $bindArgs[] = &$params[$idx];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindArgs);

        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($res && ($row = $res->fetch_assoc())) {
                $k = trim((string) ($row['tabla_origen'] ?? '')) . '|' . (int) ($row['orig_id'] ?? 0);
                $counts[$k] = (int) ($row['unread_count'] ?? 0);
            }
        }
        $stmt->close();

        return $counts;
    }
}

if (!function_exists('pltraceChatGetLastMessageId')) {
    function pltraceChatGetLastMessageId($conn, $tablaOrigen, $origId)
    {
        if (!($conn instanceof mysqli)) {
            return 0;
        }

        ensurePltraceChatTables($conn);

        $tablaOrigen = trim((string) $tablaOrigen);
        $origId = (int) $origId;
        if ($tablaOrigen === '' || $origId <= 0) {
            return 0;
        }

        $sql = 'SELECT MAX(m.id) AS last_id
                FROM pltrace_lead_mensajes m
                INNER JOIN pltrace_lead_conversaciones c ON c.id = m.conversacion_id
                WHERE c.tabla_origen = ? AND c.orig_id = ?';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('si', $tablaOrigen, $origId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return (int) ($row['last_id'] ?? 0);
    }
}

if (!function_exists('pltraceChatGetUserInfo')) {
    /**
     * @return array{success:bool,usuario_nombre?:string,usuario_rol?:string,error?:string}
     */
    function pltraceChatGetUserInfo($conn, $usuarioId)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return ['success' => false, 'error' => 'Usuario no autenticado'];
        }

        $userStmt = $conn->prepare('SELECT nombre, apePat, tipoUsu FROM usuarios WHERE id = ? LIMIT 1');
        if (!$userStmt) {
            return ['success' => false, 'error' => 'No se pudo consultar el usuario'];
        }
        $userStmt->bind_param('i', $usuarioId);
        $userStmt->execute();
        $userRes = $userStmt->get_result();
        $userRow = $userRes ? $userRes->fetch_assoc() : null;
        $userStmt->close();

        if (!$userRow) {
            return ['success' => false, 'error' => 'Usuario no encontrado'];
        }

        $usuarioNombre = trim(($userRow['nombre'] ?? '') . ' ' . ($userRow['apePat'] ?? ''));
        if ($usuarioNombre === '') {
            $usuarioNombre = 'Usuario #' . $usuarioId;
        }

        return [
            'success'         => true,
            'usuario_nombre'  => $usuarioNombre,
            'usuario_rol'     => pltraceChatResolveRoleLabel($userRow['tipoUsu'] ?? 0),
        ];
    }
}

if (!function_exists('pltraceChatBlockedExtensions')) {
    function pltraceChatBlockedExtensions()
    {
        return ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'bat', 'cmd', 'com', 'sh', 'js', 'html', 'htm', 'svg'];
    }
}

if (!function_exists('pltraceChatUploadFile')) {
    /**
     * @param array<string, mixed> $file  $_FILES entry
     * @return array{success:bool,event?:array,message_id?:int,error?:string}
     */
    function pltraceChatUploadFile($conn, $tablaOrigen, $origId, $usuarioId, array $file, $cfId = 0)
    {
        if (!($conn instanceof mysqli)) {
            return ['success' => false, 'error' => 'Conexión inválida'];
        }

        ensurePltraceChatTables($conn);

        $tablaOrigen = trim((string) $tablaOrigen);
        $origId = (int) $origId;
        $usuarioId = (int) $usuarioId;
        $cfId = (int) $cfId;

        if ($tablaOrigen === '' || $origId <= 0) {
            return ['success' => false, 'error' => 'Parámetros inválidos'];
        }

        $userInfo = pltraceChatGetUserInfo($conn, $usuarioId);
        if (empty($userInfo['success'])) {
            return ['success' => false, 'error' => $userInfo['error'] ?? 'Usuario no válido'];
        }

        if (empty($file) || !isset($file['error'])) {
            return ['success' => false, 'error' => 'No se recibió ningún archivo'];
        }

        $uploadError = (int) $file['error'];
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => 'Selecciona un archivo para enviar'];
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Error al subir el archivo (código ' . $uploadError . ')'];
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return ['success' => false, 'error' => 'Archivo inválido'];
        }

        $originalName = trim((string) ($file['name'] ?? 'archivo'));
        if ($originalName === '') {
            $originalName = 'archivo';
        }
        $originalName = basename(str_replace('\\', '/', $originalName));

        $sizeBytes = (int) ($file['size'] ?? 0);
        if ($sizeBytes <= 0) {
            return ['success' => false, 'error' => 'El archivo está vacío'];
        }
        if ($sizeBytes > pltraceChatMaxUploadBytes()) {
            return ['success' => false, 'error' => 'El archivo supera el límite de ' . pltraceChatFormatFileSize(pltraceChatMaxUploadBytes())];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? finfo_file($finfo, $tmpPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $clientMime = trim((string) ($file['type'] ?? ''));
        $mimeType = $detectedMime !== '' ? $detectedMime : ($clientMime !== '' ? $clientMime : 'application/octet-stream');

        $classInfo = pltraceChatClassifyFile($originalName, $mimeType);
        $extension = $classInfo['extension'];
        if (in_array($extension, pltraceChatBlockedExtensions(), true)) {
            return ['success' => false, 'error' => 'Tipo de archivo no permitido'];
        }

        $conv = pltraceChatGetOrCreateConversation($conn, $tablaOrigen, $origId, $cfId);
        if (empty($conv['success'])) {
            return ['success' => false, 'error' => $conv['error'] ?? 'No se pudo obtener la conversación'];
        }
        $conversacionId = (int) $conv['conversacion_id'];

        $now = date('Y-m-d H:i:s');
        $usuarioNombre = $userInfo['usuario_nombre'];
        $usuarioRol = $userInfo['usuario_rol'];
        $tipoMensaje = 'archivo';
        $mensajeVacio = '';

        $ins = $conn->prepare(
            'INSERT INTO pltrace_lead_mensajes
                (conversacion_id, usuario_id, usuario_nombre, usuario_rol, mensaje, tipo_mensaje, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$ins) {
            return ['success' => false, 'error' => 'No se pudo registrar el archivo'];
        }
        $ins->bind_param('iisssss', $conversacionId, $usuarioId, $usuarioNombre, $usuarioRol, $mensajeVacio, $tipoMensaje, $now);
        if (!$ins->execute()) {
            $ins->close();
            return ['success' => false, 'error' => 'No se pudo registrar el archivo'];
        }
        $messageId = (int) $ins->insert_id;
        $ins->close();

        $storedName = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        if (strlen($storedName) > 200) {
            $storedName = substr($storedName, 0, 200);
        }
        $storedName .= '.' . $extension;

        $relativeDir = pltraceChatSanitizePathSegment($tablaOrigen) . DIRECTORY_SEPARATOR . $origId;
        $absoluteDir = pltraceChatUploadBaseDir() . DIRECTORY_SEPARATOR . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            $conn->query('DELETE FROM pltrace_lead_mensajes WHERE id = ' . (int) $messageId);
            return ['success' => false, 'error' => 'No se pudo crear la carpeta de almacenamiento'];
        }

        $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
        $relativePath = str_replace('\\', '/', $relativeDir . '/' . $storedName);

        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            $conn->query('DELETE FROM pltrace_lead_mensajes WHERE id = ' . (int) $messageId);
            return ['success' => false, 'error' => 'No se pudo guardar el archivo en el servidor'];
        }

        $archivoIns = $conn->prepare(
            'INSERT INTO pltrace_lead_archivos
                (mensaje_id, nombre_original, stored_name, ruta_relativa, mime_type, extension, tamano_bytes, categoria, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$archivoIns) {
            @unlink($absolutePath);
            $conn->query('DELETE FROM pltrace_lead_mensajes WHERE id = ' . (int) $messageId);
            return ['success' => false, 'error' => 'No se pudo registrar el archivo'];
        }
        $categoria = $classInfo['categoria'];
        $archivoIns->bind_param(
            'isssssiss',
            $messageId,
            $originalName,
            $storedName,
            $relativePath,
            $mimeType,
            $extension,
            $sizeBytes,
            $categoria,
            $now
        );
        if (!$archivoIns->execute()) {
            $archivoIns->close();
            @unlink($absolutePath);
            $conn->query('DELETE FROM pltrace_lead_mensajes WHERE id = ' . (int) $messageId);
            return ['success' => false, 'error' => 'No se pudo registrar el archivo'];
        }
        $archivoId = (int) $archivoIns->insert_id;
        $archivoIns->close();

        $upd = $conn->prepare('UPDATE pltrace_lead_conversaciones SET updated_at = ? WHERE id = ?');
        if ($upd) {
            $upd->bind_param('si', $now, $conversacionId);
            $upd->execute();
            $upd->close();
        }

        $event = pltraceChatBuildMessageEvent([
            'id'                      => $messageId,
            'usuario_id'              => $usuarioId,
            'usuario_nombre'          => $usuarioNombre,
            'usuario_rol'             => $usuarioRol,
            'mensaje'                 => '',
            'tipo_mensaje'            => 'archivo',
            'created_at'              => $now,
            'archivo_id'              => $archivoId,
            'archivo_nombre_original' => $originalName,
            'archivo_mime_type'       => $mimeType,
            'archivo_extension'       => $extension,
            'archivo_tamano_bytes'    => $sizeBytes,
            'archivo_categoria'       => $categoria,
            'archivo_ruta_relativa'   => $relativePath,
        ], $usuarioId);

        return [
            'success'    => true,
            'message_id' => $messageId,
            'event'      => $event,
        ];
    }
}

if (!function_exists('pltraceChatGetFileRecordByMessageId')) {
    /**
     * @return array{success:bool,row?:array,absolute_path?:string,error?:string}
     */
    function pltraceChatGetFileRecordByMessageId($conn, $messageId)
    {
        if (!($conn instanceof mysqli)) {
            return ['success' => false, 'error' => 'Conexión inválida'];
        }

        ensurePltraceChatTables($conn);

        $messageId = (int) $messageId;
        if ($messageId <= 0) {
            return ['success' => false, 'error' => 'Archivo no encontrado'];
        }

        $stmt = $conn->prepare(
            pltraceChatMessageSelectSql() . '
             WHERE m.id = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return ['success' => false, 'error' => 'No se pudo consultar el archivo'];
        }
        $stmt->bind_param('i', $messageId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row || (string) ($row['tipo_mensaje'] ?? '') !== 'archivo' || empty($row['archivo_id'])) {
            return ['success' => false, 'error' => 'Archivo no encontrado'];
        }

        $relativePath = str_replace('\\', '/', (string) ($row['archivo_ruta_relativa'] ?? ''));
        $baseDir = realpath(pltraceChatUploadBaseDir());
        $absolutePath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $realFile = realpath($absolutePath);

        if ($baseDir === false || $realFile === false || strpos($realFile, $baseDir) !== 0 || !is_file($realFile)) {
            return ['success' => false, 'error' => 'Archivo no disponible'];
        }

        return [
            'success'       => true,
            'row'           => $row,
            'absolute_path' => $realFile,
        ];
    }
}
