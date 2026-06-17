<?php
    include 'conn.php';
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    header('Content-Type: application/json'); // Indicar que la respuesta es JSON

    // Consulta SQL para obtener los datos de la tabla calendario, uniendo con las tablas usuarios y contact_form
   $sql = "
    SELECT 
        c.id, c.idusu, c.fecha, c.hora, c.titulo, c.nota, c.comentario,
        COALESCE(NULLIF(TRIM(c.comentario), ''), c.nota) AS nota_sesion,
        c.idclie, c.estatus,
        c.fecha_cliente, c.hora_cliente, c.appointment_utc,
        u.nombre AS usuario_nombre, u.apePat AS usuario_apePat, u.apeMat AS usuario_apeMat, 
        u.telefono AS usuario_telefono, u.correo AS usuario_correo, u.enlace_meet,
        cf.names AS cliente_names, cf.telephone AS cliente_telefono, cf.email_address AS cliente_email_address,  cf.desde_publicidad,
        cf.wedding_date AS cliente_wedding_date, cf.wedding_location AS cliente_wedding_location, 
        cf.wedding_planner AS cliente_wedding_planner, cf.guests_count AS cliente_guests_count, cf.country_code,
        cf.how_did_you_meet AS cliente_how_did_you_meet, cf.tipo_cliente,
        cf.couple_activities AS cliente_couple_activities,
        cf.favorite_movie_or_song AS cliente_favorite_movie_or_song, cf.look_preference AS cliente_look_preference, 
        cf.instagram_handle AS cliente_instagram_handle, cf.hear_about_us AS cliente_hear_about_us,
        cf.service_interest AS cliente_service_interest, cf.additional_details AS cliente_additional_details,
        cf.paquete AS cliente_paquete_id, cf.engagement AS cliente_engagement,
        cf.how_long_known_us AS cliente_how_long_known_us,
        cf.submission_date AS cliente_submission_date, cf.time_appointment AS cliente_time_appointment, 
        cf.date_appointment AS cliente_date_appointment, cf.cliente as is_cliente, cf.zona_horaria, cf.timezone_name, cf.timezone_offset_minutes, cf.city as cliente_city, cf.tabla_origen AS table_origen, cf.campaign_name, cf.form_name, cf.original_lead_id, cf.id_vendedor_asignado,
        uv.nombre AS vendedor_asignado_nombre, uv.apePat AS vendedor_asignado_apePat, uv.apeMat AS vendedor_asignado_apeMat,
            p.nombre AS cliente_paquete_nombre,
            COALESCE(ev_direct.comentario, ev_afianzado.comentario) AS comentario_desde_wp
    FROM calendario c 
    LEFT JOIN usuarios u ON c.idusu = u.id
    LEFT JOIN contact_form cf ON c.idclie = cf.id
    LEFT JOIN usuarios uv ON cf.id_vendedor_asignado = uv.id
    LEFT JOIN paquetes p ON cf.paquete = p.id
        LEFT JOIN eventos_wp ev_direct ON LOWER(cf.tabla_origen) = 'eventos_wp' AND ev_direct.id = cf.original_lead_id
        LEFT JOIN wp_eventos_afianzados wpa ON LOWER(cf.tabla_origen) = 'wp_eventos_afianzados' AND wpa.id = cf.original_lead_id
        LEFT JOIN eventos_wp ev_afianzado ON ev_afianzado.id = wpa.id_evento
    WHERE c.eliminado = 0  -- Solo registros no eliminados
";

    // Ejecutar la consulta
    $result = $conn->query($sql);

    // Array para almacenar los resultados
    $data = array();

    // Verificar si se encontraron resultados
    if ($result->num_rows > 0) {
        // Obtener cada fila y agregarla al array $data
        while ($row = $result->fetch_assoc()) {
            // Imprimir los resultados para depuración (solo para ver qué datos estás obteniendo, se puede eliminar en producción)
            // error_log(print_r($row, true)); // Se usa para loguear los datos, no interferir con JSON

            $data[] = $row;
        }
    } else {
        // Mensaje si no se encuentran resultados
        $data = ["message" => "No se encontraron resultados en la consulta."];
    }

    // Devolver los datos en formato JSON
    echo json_encode(array("data" => $data));

    // Cerrar la conexión
    $conn->close();
?>
