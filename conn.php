<?php
// Archivo conn.php

// Configuración de conexión a la base de datos
$servername = "localhost";
$username = "efegephocom_sistema";
$password = "t)sXFfUEN13k";
$dbname = "efegephocom_sistema";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Establecer el conjunto de caracteres (opcional pero recomendado)
$conn->set_charset("utf8");
?>
