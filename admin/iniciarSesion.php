<?php
session_start();
include "conn.php";
require_once __DIR__ . '/persistent_login.php';

$nombre = $_POST['txusuario'];
$pass =  $_POST['txpassword'];

$_SESSION['id']=null;
$_SESSION['login']=false;

$query = mysqli_query($conn, "SELECT * FROM usuarios WHERE usuario = '".$nombre."'and contrasena = BINARY '".$pass."'");
$nr = mysqli_num_rows($query);
$res=$query->fetch_assoc();

if($nr == 1)
{
    //$_SESSION['id']= session_id();
    $_SESSION['uid']=$res['id'];
    $_SESSION['tus']=$res['tipoUsu'];
    $_SESSION['login']=true;
    if ($res['tipoUsu'] == "2" || $res['tipoUsu'] == "3") {
        $data = 2;
    } elseif ($res['tipoUsu'] == "4") {
        $data = 4;
    } else {
        $data = $nr;
    }
    // Create a persistent 'remember me' token for this user
    // (This issues a cookie and adds a DB token record.)
    createRememberToken((int)$_SESSION['uid'], $conn);
}
else
{
    $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$secret&response=$captcha");
    $arr = json_decode($response, TRUE);
    $data="null";
}

header("Content-type: application/json; charset=utf-8"); //inform the browser we're sending JSON data
echo json_encode($data); //echoing JSON encoded data as the response for the AJAX call

?>