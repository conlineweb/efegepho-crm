<?php

function normalizeAppointmentTime($time) {
    $time = trim((string)$time);
    if ($time === '') {
        return '';
    }

    $parts = explode(':', $time);
    if (count($parts) < 2) {
        return $time;
    }

    $hour = str_pad((string)((int)$parts[0]), 2, '0', STR_PAD_LEFT);
    $minute = str_pad((string)((int)$parts[1]), 2, '0', STR_PAD_LEFT);

    return $hour . ':' . $minute;
}

function vendorHasActivatedSlot($conn, $vendorId, $date, $time) {
    $vendorId = (int)$vendorId;
    $normalizedTime = normalizeAppointmentTime($time);
    if ($vendorId <= 0 || $date === '' || $normalizedTime === '') {
        return false;
    }

    $stmt = $conn->prepare('SELECT horarios FROM atencion WHERE idusu = ? AND dia = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $vendorId, $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return false;
    }

    $schedules = json_decode($row['horarios'] ?? '[]', true);
    if (!is_array($schedules)) {
        return false;
    }

    foreach ($schedules as $scheduleTime) {
        if (normalizeAppointmentTime($scheduleTime) === $normalizedTime) {
            return true;
        }
    }

    return false;
}

function isVendorSlotOccupied($conn, $vendorId, $date, $time, $excludeCalendarId = null) {
    $vendorId = (int)$vendorId;
    $excludeCalendarId = $excludeCalendarId !== null ? (int)$excludeCalendarId : 0;
    $normalizedTime = normalizeAppointmentTime($time);
    if ($vendorId <= 0 || $date === '' || $normalizedTime === '') {
        return true;
    }

    $sql = 'SELECT id, hora FROM calendario WHERE fecha = ? AND idusu = ? AND eliminado = 0';
    if ($excludeCalendarId > 0) {
        $sql .= ' AND id <> ?';
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return true;
    }

    if ($excludeCalendarId > 0) {
        $stmt->bind_param('sii', $date, $vendorId, $excludeCalendarId);
    } else {
        $stmt->bind_param('si', $date, $vendorId);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        if (normalizeAppointmentTime($row['hora'] ?? '') === $normalizedTime) {
            return true;
        }
    }

    return false;
}

function isVendorSlotAvailableForBooking($conn, $vendorId, $date, $time, $excludeCalendarId = null) {
    if (!vendorHasActivatedSlot($conn, $vendorId, $date, $time)) {
        return false;
    }

    return !isVendorSlotOccupied($conn, $vendorId, $date, $time, $excludeCalendarId);
}

function getAvailableVendorsForSlot($conn, $date, $time, $excludeCalendarId = null) {
    $normalizedTime = normalizeAppointmentTime($time);
    if ($date === '' || $normalizedTime === '') {
        return [];
    }

    $availableVendorIds = [];
    $stmtAttention = $conn->prepare('SELECT horarios, idusu FROM atencion WHERE dia = ?');
    if (!$stmtAttention) {
        return [];
    }

    $stmtAttention->bind_param('s', $date);
    $stmtAttention->execute();
    $attentionRows = $stmtAttention->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtAttention->close();

    foreach ($attentionRows as $vendor) {
        $vendorId = (int)($vendor['idusu'] ?? 0);
        if ($vendorId <= 0) {
            continue;
        }

        if (isVendorSlotAvailableForBooking($conn, $vendorId, $date, $normalizedTime, $excludeCalendarId)) {
            $availableVendorIds[$vendorId] = $vendorId;
        }
    }

    return array_values($availableVendorIds);
}
