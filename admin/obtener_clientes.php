<?php
    include 'conn.php';
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    header('Content-Type: application/json');

    $sql = "
        SELECT
            cf.id, cf.names AS cliente_names, cf.telephone AS cliente_telefono,
            cf.email_address AS cliente_email_address, cf.wedding_date AS cliente_wedding_date,
            cf.wedding_location AS cliente_wedding_location, cf.wedding_planner AS cliente_wedding_planner,
            cf.city AS city, cf.city AS cliente_city, cf.created_time,
            cf.guests_count AS cliente_guests_count, cf.country_code,
            cf.how_did_you_meet AS cliente_how_did_you_meet,
            cf.tipo_cliente,
            cf.how_long_known_us, cf.first_contact_channel, cf.engagement,
            cf.couple_activities AS cliente_couple_activities,
            cf.favorite_movie_or_song AS cliente_favorite_movie_or_song,
            cf.look_preference AS cliente_look_preference,
            cf.instagram_handle AS cliente_instagram_handle,
            cf.hear_about_us AS cliente_hear_about_us,
            cf.service_interest AS cliente_service_interest,
            cf.additional_details AS cliente_additional_details,
            cf.submission_date AS cliente_submission_date,
            cf.sesion_oficial, cf.compromiso_cliente, cf.tecnica_cierre,
            cf.time_appointment AS cliente_time_appointment,
            cf.date_appointment AS cliente_date_appointment,
            cf.cliente as is_cliente,
            cf.fecha_cambio_cliente as fecha_cambio_cliente,
            cf.id_vendedor_asignado as id_vendedor_asignado, cf.manual as manual,
            cf.paquete, cf.puntos, cf.monto_venta, cf.que_se_les_vendio,
            cf.original_lead_id, cf.tabla_origen,
            cf.comision, cf.comision_pagada,
            cf.campaign_name AS campaign_name, cf.form_name AS form_name,
            c.id AS calendario_id, c.idusu, c.fecha, c.hora, c.titulo, c.nota,
            c.comentario AS comentario_agenda_inicial,
            c.comentario_a_cliente AS comentario_agenda_cliente,
            c.idclie, c.estatus,
            u.nombre AS usuario_nombre, u.apePat AS usuario_apePat, u.apeMat AS usuario_apeMat,
            u.telefono AS usuario_telefono, u.correo AS usuario_correo, u.enlace_meet,
            uv.nombre as vendedor_asignado_nombre, 
            uv.apePat as vendedor_asignado_apePat, 
            uv.apeMat as vendedor_asignado_apeMat,
            COALESCE(ev_direct.comentario, ev_afianzado.comentario) AS comentario_wp_inicial,
            COALESCE(ev_direct.comentario_a_cliente, ev_afianzado.comentario_a_cliente) AS comentario_wp_cliente
        FROM contact_form cf
        LEFT JOIN calendario c ON cf.id = c.idclie AND c.eliminado = 0
        LEFT JOIN usuarios u ON c.idusu = u.id
        LEFT JOIN usuarios uv ON cf.id_vendedor_asignado = uv.id
        LEFT JOIN eventos_wp ev_direct ON LOWER(cf.tabla_origen) = 'eventos_wp' AND ev_direct.id = cf.original_lead_id
        LEFT JOIN wp_eventos_afianzados wpa ON LOWER(cf.tabla_origen) = 'wp_eventos_afianzados' AND wpa.id = cf.original_lead_id
        LEFT JOIN eventos_wp ev_afianzado ON ev_afianzado.id = wpa.id_evento
        WHERE cf.cliente = 1
    ";

    $result = $conn->query($sql);

    $data = array();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    } else {
        $data = ["message" => "No se encontraron resultados en la consulta."];
    }

    echo json_encode(array("data" => $data));

    $conn->close();
?>