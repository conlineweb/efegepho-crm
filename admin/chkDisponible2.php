<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'conn.php';

if (isset($_POST['dia'])){
    $stmt=$conn->prepare("SELECT horarios, idusu FROM `atencion` WHERE dia = ?");
    $stmt->bind_param('s',$_POST['dia']);
    $stmt->execute();
    $res=$stmt->get_result();
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
}

if (isset($_POST['fecha'])){
    $stmt=$conn->prepare("SELECT hora ,idusu FROM `calendario` where fecha=? ORDER BY hora ASC;");
    $stmt->bind_param('s',$_POST['fecha']);
    $stmt->execute();
    $res=$stmt->get_result();
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
}
?>
