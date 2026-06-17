<?php 

// tu_script_php.php

include 'conn.php';

$response = array(); // Inicializa un array para la respuesta JSON

if (isset($_POST['cuentas'])) {

  $cuentas = $_POST['cuentas'];
  $errores = 0; // Contador de errores

  // Recorremos todas las cuentas
  foreach ($cuentas as $cuenta) {

    // Escapamos el número para evitar problemas con SQL Injection
    $numero = mysqli_real_escape_string($conn, $cuenta['number']);
    $id = $cuenta['id'];
    $isFromDB = $cuenta['isFromDB'];
    $isUpdated = $cuenta['isUpdated'];
    $isDeleted = $cuenta['isDeleted'];

    // Si el registro no es de la base de datos (isFromDB es false), agregamos el número
    if (!$isFromDB) {
      $sql_insert = "INSERT INTO whatsapp (numero) VALUES ('$numero')";
      if (!mysqli_query($conn, $sql_insert)) {
        $errores++;
        $response['errores'][] = "Error al insertar el número $numero: " . mysqli_error($conn);
      }
    } else {
      // Si el registro es de la base de datos (isFromDB es true)
      if (!$isUpdated && !$isDeleted) {
        // Si isUpdated y isDeleted son ambos falsos, no hacemos nada y saltamos al siguiente
        continue;
      }
      
      // Si isUpdated es true, actualizamos el número
      if ($isUpdated) {
        $sql_update = "UPDATE whatsapp SET numero = '$numero' WHERE id = $id";
        if (!mysqli_query($conn, $sql_update)) {
          $errores++;
          $response['errores'][] = "Error al actualizar el número con id $id: " . mysqli_error($conn);
        }
      } 

      // Si isDeleted es true, eliminamos el registro
      elseif ($isDeleted) {
        $sql_delete = "DELETE FROM whatsapp WHERE id = $id";
        if (!mysqli_query($conn, $sql_delete)) {
          $errores++;
          $response['errores'][] = "Error al eliminar el número con id $id: " . mysqli_error($conn);
        }
      }
    }
  }

  // Después de procesar todas las cuentas, verificamos si hubo errores
  if ($errores === 0) {
    $response['estado'] = 'ok';
    $response['mensaje'] = 'Operaciones realizadas correctamente.';
  } else {
    $response['estado'] = 'error';
    // Si hubo errores, se puede enviar un mensaje general o específico
    $response['mensaje'] = 'Hubo errores al procesar algunas cuentas.';
  }

} else {
  $response['estado'] = 'error';
  $response['mensaje'] = 'No se recibieron cuentas.';
}

header('Content-Type: application/json'); // Define el encabezado para JSON
echo json_encode($response); // Envía la respuesta en formato JSON

mysqli_close($conn);

?>
