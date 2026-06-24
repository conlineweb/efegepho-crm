<?php
// If user already has session or valid remember cookie, redirect them to the appropriate page
session_start();
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/persistent_login.php';

// If session present, redirect
if (!empty($_SESSION['uid'])) {
  if ((int)$_SESSION['uid'] === 27) {
    header('Location: dashboard_comercial.php?start_date=2026-06-15&end_date=2026-06-16');
    exit;
  }
  if (!empty($_SESSION['tus']) && ($_SESSION['tus'] == "2" || $_SESSION['tus'] == "3")) {
    header('Location: consulta-clientes.php');
    exit;
  }
  if (!empty($_SESSION['tus']) && $_SESSION['tus'] == "4") {
    header('Location: consulta_leads.php');
    exit;
  }
  header('Location: index.php');
  exit;
}




//iniciar sesion con los datos de localStorage si existen (test purposes only)

?>
<!DOCTYPE html>
<html>
<head><meta charset="gb18030">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Acceso sistema  </title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300&display=swap" rel="stylesheet">
<link rel="shortcut icon" href="img/icono-joblink.png">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.js"></script>
<script type="text/javascript"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@10"></script>

</head>
<body>
 
  <div class="container ">
      <div class="row">
          
          <div class="col-md-6 col-xs-12" id="imagen">
              
             
              
          </div>
          
          <div class="col-md-6 col-xs-12" id="formulario">
              
             <center>
    <img class="logo" src="vistas/img/login/62e1beaf3e61f6ff41a188e7_efege-bk.svg" alt="efegepho.com">
</center>
<br><br>

<style>
    .logo {
        width: 30%; /* Reduce el tama���o al 70% */
    }
</style>
               <h3 class="text-center mb-4">Iniciar sesi&oacute;n</h3>
              <form>
                  <input type="text" name="txusuario" placeholder="Usuario" id="usr" autofocus/><br>
 
                   <input type="password" name="txpassword" placeholder="Contrase&ntilde;a" id="myInput"/>

                   <input type="checkbox" onclick="myFunction()" /><label> Mostrar Contrase&ntilde;a  </label><br>

                   <button class="input" type="submit" id="enviar_btn">Ingresar</button><br>
              </form>
              
          </div>
          
          
      </div>
      
  </div>
    
    
    
 

      
      


     

<style>
* {
      box-sizing: border-box;
      margin: 0px;
      padding: 0px;
    }
    
    
    body {
            font-family: 'Montserrat', sans-serif;
        }
        #formulario h3 {
            font-weight: 600;
        }
        #formulario input::placeholder {
            font-family: 'Montserrat', sans-serif;
            font-weight: 100;
        }
        #formulario input, #formulario label, #formulario button {
            font-family: 'Montserrat', sans-serif;
            font-weight: 100;
        }
    
    label {
    width: 25%;
    display: inline;
    padding-left: 0px;
    text-indent: 5px;
    height: 100%;
    padding: 0;
    margin-bottom:10px;
    /*vertical-align: bottom; */
    position: relative;
    top: -1px;
    *overflow: hidden;
    font-family: Helvetica;
    font-weight: 100;
    font-size: 10px;
}

input[type=text], input[type=password] {
width: 100%;
height:50px;
border-radius: 5px;
padding: 5px 5px;
margin: 5px 0;
border: 2px solid #000000;
box-sizing: border-box;


font-size: 15px;
}   

html {
    height: 100%;
}

body {
    font-family: Arial, Helvetica, sans-serif;
  
    background-size: cover; 
    background-image: 
    /* top, transparent red */
    linear-gradient(
      rgba(0, 0, 0, 0.45), 
      rgba(0, 0, 0, 0.45)
    ),
    /* your image */
   url(vistas/img/login/dark-blue-gradient-s6b0i75a8th7ko62.jpg);
    margin:0; padding:0;}




/* style the container */
.row{
    height:78vh;
}
.container {
 margin-top: 102px;
    margin-bottom: 102px;
    background-color:#fff;
   -webkit-box-shadow: 10px 13px 33px 5px rgba(0,0,0,0.58);
-moz-box-shadow: 10px 13px 33px 5px rgba(0,0,0,0.58);
box-shadow: 10px 13px 33px 5px rgba(0,0,0,0.58);
    
} 

#imagen {
    background-image: url('vistas/img/login/pexels-214377531-18005576.jpg');
    background-size: cover; /* Asegura que la imagen cubra todo el ���rea del contenedor */
    background-position: center; /* Centra la imagen dentro del contenedor */
    background-repeat: no-repeat; /* Evita que la imagen se repita */
    width: 50%; /* Asegura que el contenedor ocupe el 100% del ancho */
    /* Asegura que el contenedor ocupe el 100% de la altura de la ventana */
}
#formulario{
    padding: 5% 70px 40px 70px;
}

/* style inputs and link buttons */

button {
  width: 50%;
  padding: 12px;
  border: none;
  border-radius: 4px;
  margin: 5px 0;
  opacity: 0.85;
  font-family: Helvetica; 
  display: inline-block;
  font-size: 20px;
  line-height: 20px;
  text-decoration: none; /* remove underline from anchors */
}

input:hover,
.btn:hover {
  opacity: 1;
}

/* add appropriate colors to fb, twitter and google buttons */

/* style the submit button */


button[type=submit]  {
  background-color: #000000;
  color: white;
  margin-top: 15px;;
}

/* Two-column layout */
.col {
  float: left;
  width: 50%;
  margin: auto;
  padding: 0 0px;
  margin-top: 0px;
}


.col1 {
  float: center;
  background: url() no-repeat center center fixed;
   background-size: cover;
  -moz-background-size: cover;
  -webkit-background-size: cover;
  -o-background-size: cover;
   background-attachment: fixed;
 
  height: 100%;
  
}

/* Clear floats after the columns */
.row:after {
  content: "";
  display: table;
  clear: both;
}

/* vertical line */
.vl {
  position: absolute;
  left: 50%;
  transform: translate(-50%);
  border: 2px solid #ddd;
  height: 655px;
}

/* text inside the vertical line */
.vl-innertext {
  position: absolute;
  top: 655px;
  transform: translate(-50%, -50%);
  background-color: #f1f1f1;
  border: 1px solid #ccc;
  border-radius: 30%;
  padding: 0px 0px 0px 0px;
}


/* Responsive layout - when the screen is less than 650px wide, make the two columns stack on top of each other instead of next to each other */
@media screen and (max-width:767px) {
 
  #imagen{
          background-position-y: 40%;
  }
  #formulario {
    padding: 15% 70px 25px 75px;
  }
  .container{
      margin-top: 67px;
      
     
  }
}

</style>

<script>
function myFunction() {
  var x = document.getElementById("myInput");
  if (x.type === "password") {
    x.type = "text";
  } else {
    x.type = "password";
  }
}

function login(user, pass) {
    if(user.length == 0 || pass.length == 0){
      Swal.fire('Ingrese usuario y contraseña','','warning');
      return false;
    }
    else{
       console.log("intentando iniciar sesion desde LS");
        $.ajax({
            url:"iniciarSesion.php",
            type:"POST",
            dataType:"json",
            data:{txusuario:user, txpassword:pass},
            success:function(data){
                console.log(data)
                if(data=="null"){
                    Swal.fire('Usuario o password incorrecto','','error');
                }
                else if(data == 27){
                    Swal.fire({
                      title:'Bienvenido',
                      icon:'success',
                      showConfirmButton:false,
                      timer:1500,
                      willClose: () => {
                      window.location.href="dashboard_comercial.php?start_date=2026-06-15&end_date=2026-06-16";
                      }
                    })
                }
                else if(data=="1"){
                    Swal.fire({
                      title:'Bienvenido',
                      icon:'success',
                      showConfirmButton:false,
                      timer:1500,
                      willClose: () => {
                      window.location.href="index.php";
                      }
                    })
                }else if(data=="2" || data=="3"){
                    Swal.fire({
                      title:'Bienvenido',
                      icon:'success',
                      showConfirmButton:false,
                      timer:1500,
                      willClose: () => {
                      window.location.href="consulta-clientes.php";
                      }
                    })
                }else if(data=="4"){
                    Swal.fire({
                      title:'Bienvenido',
                      icon:'success',
                      showConfirmButton:false,
                      timer:1500,
                      willClose: () => {
                      window.location.href="consulta_leads.php";
                      }
                    })
                }
            }
        });
    }
}

$(document).ready(function(){
    // Cargar usuario y contraseña desde localStorage para pruebas
    var savedUser = localStorage.getItem('username');
    var savedPass = localStorage.getItem('password');
    if(savedUser){
        $('#usr').val(savedUser);
    }
    if(savedPass){
        $('#myInput').val(savedPass);
    }
    // Si ambos existen, iniciar sesión automáticamente (solo para pruebas)
    if(savedUser && savedPass){
     
      
        login(savedUser, savedPass);
    }
});

$("#enviar_btn").on('click',function(e){
    e.preventDefault();
    var user=$('#usr').val(),pass=$('#myInput').val();
    // Guardar en localStorage para pruebas
    localStorage.setItem('username', user);
    localStorage.setItem('password', pass);
    login(user, pass);
}); 
</script>
</body>
</html>