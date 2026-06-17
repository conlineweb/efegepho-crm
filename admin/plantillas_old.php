<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'menu.php';
include 'conn.php'; // Incluye el archivo de conexión a la base de datos
$tipoUsuario = $_SESSION['tus'];
$userid = $_SESSION['uid'];

// Load users to allow choosing test recipient
$sqlUsers = "SELECT id, nombre, apePat, correo FROM usuarios WHERE correo IS NOT NULL AND correo != ''";
$resultUsers = $conn->query($sqlUsers);
$usersList = $resultUsers ? $resultUsers->fetch_all(MYSQLI_ASSOC) : [];

// Consultar los textos para Registro (ID = 1)
$sqlRegistroEsp = "SELECT * FROM txtCorreo WHERE id = 1";
$resultRegistroEsp = $conn->query($sqlRegistroEsp);
$dataRegistroEsp = $resultRegistroEsp->fetch_assoc();

// Consultar los textos para Reagendar (ID = 2)
$sqlReagendarEsp = "SELECT * FROM txtCorreo WHERE id = 2";
$resultReagendarEsp = $conn->query($sqlReagendarEsp);
$dataReagendarEsp = $resultReagendarEsp->fetch_assoc();


// Consultar los textos para Reagendar (ID = 7)
$sqlReagendarAutoEsp = "SELECT * FROM txtCorreo WHERE id = 7";
$resultReagendarAutoEsp = $conn->query($sqlReagendarAutoEsp);
$dataReagendarAutoEsp = $resultReagendarAutoEsp->fetch_assoc();

// Consultar los textos para Notificaciones (ID = 3)
$sqlNotificacionesEsp = "SELECT * FROM txtCorreo WHERE id = 3";
$resultNotificacionesEsp = $conn->query($sqlNotificacionesEsp);
$dataNotificacionesEsp = $resultNotificacionesEsp->fetch_assoc();

// Consultar los textos para Galeria (ID = 9)
$sqlGaleriaEsp = "SELECT * FROM txtCorreo WHERE id = 9";
$resultGaleriaEsp = $conn->query($sqlGaleriaEsp);
$dataGaleriaEsp = $resultGaleriaEsp->fetch_assoc();


// Consultar los textos para Galeria (ID = 11)
$sqlCuestionarioEsp = "SELECT * FROM txtCorreo WHERE id = 11";
$resultCuestionarioEsp = $conn->query($sqlCuestionarioEsp);
$dataCuestionarioEsp = $resultCuestionarioEsp->fetch_assoc();


// Consultar los textos para Galeria (ID = 13)
$sqlCobroEsp = "SELECT * FROM txtCorreo WHERE id = 13";
$resultCobroEsp = $conn->query($sqlCobroEsp);
$dataCobroEsp = $resultCobroEsp->fetch_assoc();

$sqlExitosoEsp = "SELECT * FROM txtCorreo WHERE id = 15";
$resultExitosoEsp = $conn->query($sqlExitosoEsp);
$dataExitosoEsp = $resultExitosoEsp->fetch_assoc();

$sqlContratoEnviadoEsp = "SELECT * FROM txtCorreo WHERE id = 17";
$resultContratoEnviadoEsp = $conn->query($sqlContratoEnviadoEsp);
$dataContratoEnviadoEsp = $resultContratoEnviadoEsp->fetch_assoc();

$sqlContratoFirmadoEsp = "SELECT * FROM txtCorreo WHERE id = 19";
$resultContratoFirmadoEsp = $conn->query($sqlContratoFirmadoEsp);
$dataContratoFirmadoEsp = $resultContratoFirmadoEsp->fetch_assoc();

$sqlNexstepEsp = "SELECT * FROM txtCorreo WHERE id = 21";
$resultNexstepEsp = $conn->query($sqlNexstepEsp);
$dataNexstepEsp = $resultNexstepEsp->fetch_assoc();

// Consultar los textos para Cambio Estatus (ID = 23)
$sqlCambioEstatusEsp = "SELECT * FROM txtCorreo WHERE id = 23";
$resultCambioEstatusEsp = $conn->query($sqlCambioEstatusEsp);
$dataCambioEstatusEsp = $resultCambioEstatusEsp->fetch_assoc();

// Consultar los textos para Leads - Correo 1 (ID = 25)
$sqlLeadsCorreo1Esp = "SELECT * FROM txtCorreo WHERE id = 25";
$resultLeadsCorreo1Esp = $conn->query($sqlLeadsCorreo1Esp);
$dataLeadsCorreo1Esp = $resultLeadsCorreo1Esp->fetch_assoc();

// Consultar los textos para Leads - Correo 2 (ID = 26)
$sqlLeadsCorreo2Esp = "SELECT * FROM txtCorreo WHERE id = 26";
$resultLeadsCorreo2Esp = $conn->query($sqlLeadsCorreo2Esp);
$dataLeadsCorreo2Esp = $resultLeadsCorreo2Esp->fetch_assoc();











//ingles
// Consultar los textos para Registro (ID = 1)
$sqlRegistroEng = "SELECT * FROM txtCorreo WHERE id = 4";
$resultRegistroEng = $conn->query($sqlRegistroEng);
$dataRegistroEng = $resultRegistroEng->fetch_assoc();

// Consultar los textos para Reagendar (ID = 2)
$sqlReagendarEng = "SELECT * FROM txtCorreo WHERE id = 5";
$resultReagendarEng = $conn->query($sqlReagendarEng);
$dataReagendarEng = $resultReagendarEng->fetch_assoc();

// Consultar los textos para Reagendar (ID = 8)
$sqlReagendarAutoEng = "SELECT * FROM txtCorreo WHERE id = 8";
$resultReagendarAutoEng = $conn->query($sqlReagendarAutoEng);
$dataReagendarAutoEng = $resultReagendarAutoEng->fetch_assoc();

// Consultar los textos para Notificaciones (ID = 3)
$sqlNotificacionesEng = "SELECT * FROM txtCorreo WHERE id = 6";
$resultNotificacionesEng = $conn->query($sqlNotificacionesEng);
$dataNotificacionesEng = $resultNotificacionesEng->fetch_assoc();

// Consultar los textos para Galeria (ID = 10)
$sqlGaleriaEng = "SELECT * FROM txtCorreo WHERE id = 10";
$resultGaleriaEng = $conn->query($sqlGaleriaEng);
$dataGaleriaEng = $resultGaleriaEng->fetch_assoc();

// Consultar los textos para Galeria (ID = 12)
$sqlCuestionarioEng = "SELECT * FROM txtCorreo WHERE id = 12";
$resultCuestionarioEng = $conn->query($sqlCuestionarioEng);
$dataCuestionarioEng = $resultCuestionarioEng->fetch_assoc();

// Consultar los textos para Galeria (ID = 14)
$sqlCobroEng = "SELECT * FROM txtCorreo WHERE id = 14";
$resultCobroEng = $conn->query($sqlCobroEng);
$dataCobroEng = $resultCobroEng->fetch_assoc();

// Consultar los textos para Galeria (ID = 16)
$sqlExitosoEng = "SELECT * FROM txtCorreo WHERE id = 16";
$resultExitosoEng = $conn->query($sqlExitosoEng);
$dataExitosoEng = $resultExitosoEng->fetch_assoc();

$sqlContratoEnviadoEng = "SELECT * FROM txtCorreo WHERE id = 18";
$resultContratoEnviadoEng = $conn->query($sqlContratoEnviadoEng);
$dataContratoEnviadoEng = $resultContratoEnviadoEng->fetch_assoc();

$sqlContratoFirmadoEng = "SELECT * FROM txtCorreo WHERE id = 20";
$resultContratoFirmadoEng = $conn->query($sqlContratoFirmadoEng);
$dataContratoFirmadoEng = $resultContratoFirmadoEng->fetch_assoc();

$sqlNexstepEng = "SELECT * FROM txtCorreo WHERE id = 22";
$resultNexstepEng = $conn->query($sqlNexstepEng);
$dataNexstepEng = $resultNexstepEng->fetch_assoc();

// Consultar los textos para Cambio Estatus (ID = 24)
$sqlCambioEstatusEng = "SELECT * FROM txtCorreo WHERE id = 24";
$resultCambioEstatusEng = $conn->query($sqlCambioEstatusEng);
$dataCambioEstatusEng = $resultCambioEstatusEng->fetch_assoc();

// Consultar los textos para Leads - Email 1 (ID = 27)
$sqlLeadsCorreo1Eng = "SELECT * FROM txtCorreo WHERE id = 27";
$resultLeadsCorreo1Eng = $conn->query($sqlLeadsCorreo1Eng);
$dataLeadsCorreo1Eng = $resultLeadsCorreo1Eng->fetch_assoc();

// Consultar los textos para Leads - Email 2 (ID = 28)
$sqlLeadsCorreo2Eng = "SELECT * FROM txtCorreo WHERE id = 28";
$resultLeadsCorreo2Eng = $conn->query($sqlLeadsCorreo2Eng);
$dataLeadsCorreo2Eng = $resultLeadsCorreo2Eng->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configuración de Textos</title>
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/47.4.0/ckeditor5.css" crossorigin>
    <link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5-premium-features/47.4.0/ckeditor5-premium-features.css" crossorigin>
    <style>#pills-language-tabs{display:none !important;}</style>
  <style>
    .card {
      margin-bottom: 20px;
    }

    h3 {
      text-align: left;
    }

    .form-section-title {
      background-color: #14141e;
      color: white;
      padding: 10px;
      text-align: center;
      border-radius: 5px;
      margin-bottom: 20px;
    }

    .btn-update {
      display: block;
      margin: 10px auto;
    }
    /* Positioning for test buttons: move visible test button to top-right of each card */
    .p-3.card { position: relative; }
    /* hide any old inline test buttons we added previously */
    .btn-test { display: none !important; }
    .btn-test-top { position: absolute; top: 10px; right: 10px; z-index: 10; }
  </style>
</head>

<body>
  <div class="py-2 container">
    <h3 class="mb-4">Configuración de textos por evento</h3>
    <div class="d-flex aling-items-center justify-content-between">
      <ul class="mb-3 nav nav-pills" id="pills-language-tabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="pills-spanish-tab" data-bs-toggle="pill" data-bs-target="#pills-spanish"
            type="button" role="tab" aria-controls="pills-spanish" aria-selected="false">Español</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="pills-english-tab" data-bs-toggle="pill" data-bs-target="#pills-english"
            type="button" role="tab" aria-controls="pills-english" aria-selected="true">Inglés</button>
        </li>
      </ul>


    </div>

    <div class="tab-content" id="pills-tabContent">
      <div class="tab-pane fade" id="pills-spanish" role="tabpanel" aria-labelledby="pills-spanish-tab">

        <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
          <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="pills-leadsesp-tab" data-bs-toggle="pill"
                data-bs-target="#pills-leadsesp" type="button" role="tab" aria-controls="pills-leadsesp"
                aria-selected="true">Leads</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-reagendaresp-tab" data-bs-toggle="pill"
                data-bs-target="#pills-reagendaresp" type="button" role="tab" aria-controls="pills-reagendaresp"
                aria-selected="false">Reagendar</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-reagendaautesp-tab" data-bs-toggle="pill"
                data-bs-target="#pills-reagendaautesp" type="button" role="tab" aria-controls="pills-reagendaautesp"
                aria-selected="false">Reagenda automatica</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-notesp-tab" data-bs-toggle="pill" data-bs-target="#pills-notesp"
                type="button" role="tab" aria-controls="pills-notesp" aria-selected="false">Notificaciones</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-cuestesp-tab" data-bs-toggle="pill" data-bs-target="#pills-cuestesp"
                type="button" role="tab" aria-controls="pills-cuestesp" aria-selected="false">Cuestionario</button>
            </li>

            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-galeriaesp-tab" data-bs-toggle="pill" data-bs-target="#pills-galeriaesp"
                type="button" role="tab" aria-controls="pills-galeriaesp" aria-selected="false">Galer&iacute;a</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-pagosesp-tab" data-bs-toggle="pill" data-bs-target="#pills-pagosesp"
                type="button" role="tab" aria-controls="pills-pagosesp" aria-selected="false">Pagos</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-contratosesp-tab" data-bs-toggle="pill"
                data-bs-target="#pills-contratosesp" type="button" role="tab" aria-controls="pills-contratosesp"
                aria-selected="false">Contratos</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-nextstepesp-tab" data-bs-toggle="pill"
                data-bs-target="#pills-nextstepesp" type="button" role="tab" aria-controls="pills-nextstepesp"
                aria-selected="false">Nextstep</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-cambioestusuesp-tab" data-bs-toggle="pill"
                data-bs-target="#pills-cambioestusuesp" type="button" role="tab" aria-controls="pills-cambioestusuesp"
                aria-selected="false">Cambio Estatus</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-registroesp-tab" data-bs-toggle="pill"
                data-bs-target="#pills-registroesp" type="button" role="tab" aria-controls="pills-registroesp"
                aria-selected="false">Registro</button>
            </li>
          <?php endif; ?>
          <?php if ($tipoUsuario == "2"): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="pills-galeriaesp-tab" data-bs-toggle="pill"
                data-bs-target="#pills-galeriaesp" type="button" role="tab" aria-controls="pills-galeriaesp"
                aria-selected="true">Galer&iacute;a</button>
            </li>
          <?php endif; ?>
          <?php if ($tipoUsuario == "3"): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="pills-pagosesp-tab" data-bs-toggle="pill"
                data-bs-target="#pills-pagosesp" type="button" role="tab" aria-controls="pills-pagosesp"
                aria-selected="true">Pagos</button>
            </li>
          <?php endif; ?>
        </ul>

        <form id="formTextos">
          <div class="row">
            <!--Columna: Registro -->
            <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>
              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade" id="pills-registroesp" role="tabpanel"
                  aria-labelledby="pills-registroesp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>
                    <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>
                      <li class="mx-3">Date = $date</li>
                      <li class="mx-3">Meet = $meet</li>
                      <li class="mx-3">Seller = $seller </li>

                    <?php endif; ?>



                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Registro</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idRegistro" value="1">
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(1)">Enviar prueba</button> -->
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-1"
                        placeholder="Título para Cliente" value="<?php echo $dataRegistroEsp['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEsp-1"
                        placeholder="Texto para Cliente"><?php echo $dataRegistroEsp['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-1"
                        placeholder="Despedida para Cliente" value="<?php echo $dataRegistroEsp['txtdespclie']; ?>" />
                      <hr>
                      <div class="form-section-title">Admin</div>
                      <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEsp-1"
                        placeholder="Título para Admin" value="<?php echo $dataRegistroEsp['txttituloadmin']; ?>">
                      <textarea class="mb-3 form-control" id="txtAdminRegEsp-1"
                        placeholder="Texto para Admin"><?php echo $dataRegistroEsp['txtadmin']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespAdminRegEsp-1"
                        placeholder="Despedida para Admin" value="<?php echo $dataRegistroEsp['txtdespadmin']; ?>" />
                      <hr>
                      <div class="form-section-title">Vendedor</div>
                      <input type="text" class="mb-3 form-control" id="txtTituloVendRegEsp-1"
                        placeholder="Título para Vendedor" value="<?php echo $dataRegistroEsp['txttitulovend']; ?>">
                      <textarea class="mb-3 form-control" id="txtVendRegEsp-1"
                        placeholder="Texto para Vendedor"><?php echo $dataRegistroEsp['txtvend']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespVendRegEsp-1"
                        placeholder="Despedida para Vendedor" value="<?php echo $dataRegistroEsp['txtdespvend']; ?>" />
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(1)">Actualizar
                        Registro</button>
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(1)">Enviar prueba</button> -->
                    </div>
                  </div>
                </div>
              </div>


              <!--NEXTSTEP-->
              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade" id="pills-nextstepesp" role="tabpanel" aria-labelledby="pills-nextstepesp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>


                    <li class="mx-3">Link Dowload PDF = $linkNextstep </li>





                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">NextStep</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idReagendar" value="21">
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(21)">Enviar prueba</button> -->
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-21"
                        placeholder="Título para Cliente" value="<?php echo $dataNexstepEsp['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEsp-21"
                        placeholder="Texto para Cliente"><?php echo $dataNexstepEsp['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-21"
                        placeholder="Despedida para Cliente" value="<?php echo $dataNexstepEsp['txtdespclie']; ?>" />

                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(21)">Actualizar
                        Nextstep</button>
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(21)">Enviar prueba</button> -->
                    </div>
                  </div>
                </div>
              </div>

              <!--Columna: Cambio Estatus -->
              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade" id="pills-cambioestusuesp" role="tabpanel"
                  aria-labelledby="pills-cambioestusuesp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>
                    <li class="mx-3">Date = $date</li>
                    <li class="mx-3">Seller = $seller</li>
                     <li class="mx-3">Customer Number = $customer_number</li>
                    <li class="mx-3">Rejection Reason = $rejection_reason</li>
                    <li class="mx-3">Estatus = $estatus</li>
                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Cambio Estatus</h3>
                      <div class="form-section-title">Admin</div>
                      <input type="hidden" id="idCambioEstatus" value="23">
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(23)">Enviar prueba</button> -->
                      <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEsp-23"
                        placeholder="Título para Admin" value="<?php echo $dataCambioEstatusEsp['txttituloadmin']; ?>">
                      <textarea class="mb-3 form-control" id="txtAdminRegEsp-23"
                        placeholder="Texto para Admin"><?php echo $dataCambioEstatusEsp['txtadmin']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespAdminRegEsp-23"
                        placeholder="Despedida para Admin" value="<?php echo $dataCambioEstatusEsp['txtdespadmin']; ?>" />
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(23)">Actualizar Cambio
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(23)">Enviar prueba</button> -->
                        Estatus</button>
                    </div>
                  </div>
                </div>
              </div>

              <!--Columna: Leads -->
              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="pills-leadsesp" role="tabpanel"
                  aria-labelledby="pills-leadsesp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" id="pills-leadscorreo1esp-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-leadscorreo1esp" type="button" role="tab" aria-controls="pills-leadscorreo1esp"
                        aria-selected="true">Correo 1</button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="pills-leadscorreo2esp-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-leadscorreo2esp" type="button" role="tab" aria-controls="pills-leadscorreo2esp"
                        aria-selected="false">Correo 2</button>
                    </li>
                  </ul>

                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="pills-leadscorreo1esp" role="tabpanel"
                      aria-labelledby="pills-leadscorreo1esp-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Full Name = <span>$full_name</span> </li>
                        <li class="mx-3">When Are You Getting Married = $when_are_you_getting_married</li>
                        <li class="mx-3">Link form = $link_form</li>
                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Leads - Correo 1</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idLeadsCorreo1" value="25">
                          <!-- <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(25)">Enviar prueba</button> -->
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-25"
                            placeholder="Título para Cliente" value="<?php echo $dataLeadsCorreo1Esp['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEsp-25"
                            placeholder="Texto para Cliente"><?php echo $dataLeadsCorreo1Esp['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-25"
                            placeholder="Despedida para Cliente" value="<?php echo $dataLeadsCorreo1Esp['txtdespclie']; ?>" />
                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(25)">Actualizar Correo 1</button>
                          <!-- <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(25)">Enviar prueba</button> -->
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade" id="pills-leadscorreo2esp" role="tabpanel"
                      aria-labelledby="pills-leadscorreo2esp-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Full Name = <span>$full_name</span> </li>
                        <li class="mx-3">When Are You Getting Married = $when_are_you_getting_married</li>
                         <li class="mx-3">Link form = $link_form</li>
                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Leads - Correo 2</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idLeadsCorreo2" value="26">
                          <!-- <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(26)">Enviar prueba</button> -->
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-26"
                            placeholder="Título para Cliente" value="<?php echo $dataLeadsCorreo2Esp['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEsp-26"
                            placeholder="Texto para Cliente"><?php echo $dataLeadsCorreo2Esp['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-26"
                            placeholder="Despedida para Cliente" value="<?php echo $dataLeadsCorreo2Esp['txtdespclie']; ?>" />
                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(26)">Actualizar Correo 2</button>
                          <!-- <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(26)">Enviar prueba</button> -->
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!--Columna: Reagendar -->
              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade" id="pills-reagendaresp" role="tabpanel"
                  aria-labelledby="pills-reagendaresp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>
                    <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>
                      <li class="mx-3">Date = $date</li>
                      <li class="mx-3">Meet = $meet</li>
                      <li class="mx-3">Seller = $seller </li>

                    <?php endif; ?>



                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Reagendar</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idReagendar" value="2">
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(2)">Enviar prueba</button> -->
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(2)">Enviar prueba</button> -->
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-2"
                        placeholder="Título para Cliente" value="<?php echo $dataReagendarEsp['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEsp-2"
                        placeholder="Texto para Cliente"><?php echo $dataReagendarEsp['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-2"
                        placeholder="Despedida para Cliente" value="<?php echo $dataReagendarEsp['txtdespclie']; ?>" />
                      <hr>
                      <div class="form-section-title">Admin</div>
                      <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEsp-2"
                        placeholder="Título para Admin" value="<?php echo $dataReagendarEsp['txttituloadmin']; ?>">
                      <textarea class="mb-3 form-control" id="txtAdminRegEsp-2"
                        placeholder="Texto para Admin"><?php echo $dataReagendarEsp['txtadmin']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespAdminRegEsp-2"
                        placeholder="Despedida para Admin" value="<?php echo $dataReagendarEsp['txtdespadmin']; ?>" />
                      <hr>
                      <div class="form-section-title">Vendedor</div>
                      <input type="text" class="mb-3 form-control" id="txtTituloVendRegEsp-2"
                        placeholder="Título para Vendedor" value="<?php echo $dataReagendarEsp['txttitulovend']; ?>">
                      <textarea class="mb-3 form-control" id="txtVendRegEsp-2"
                        placeholder="Texto para Vendedor"><?php echo $dataReagendarEsp['txtvend']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespVendRegEsp-2"
                        placeholder="Despedida para Vendedor" value="<?php echo $dataReagendarEsp['txtdespvend']; ?>" />
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(2)">Actualizar
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(2)">Enviar prueba</button> -->
                        Reagendar</button>
                    </div>
                  </div>
                </div>
              </div>

              <!--Columna: Reagenda automaica -->
              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade" id="pills-reagendaautesp" role="tabpanel"
                  aria-labelledby="pills-reagendaautesp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>
                    <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>
                      <li class="mx-3">Date = $date</li>
                      <li class="mx-3">Meet = $meet</li>
                      <li class="mx-3">Seller = $seller </li>
                      <li class="mx-3">Reschedule = $link_reschedule</li>
                    <?php endif; ?>



                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Reagenda automática</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idReagendar" value="2">
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-7"
                        placeholder="Título para Cliente" value="<?php echo $dataReagendarAutoEsp['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEsp-7"
                        placeholder="Texto para Cliente"><?php echo $dataReagendarAutoEsp['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-7"
                        placeholder="Despedida para Cliente"
                        value="<?php echo $dataReagendarAutoEsp['txtdespclie']; ?>" />
                      <hr>
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(7)">Actualizar
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(7)">Enviar prueba</button> -->
                        Reagendar</button>
                    </div>
                  </div>
                </div>
              </div>

              <!--Columna: Notificaciones -->
              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade" id="pills-notesp" role="tabpanel" aria-labelledby="pills-notesp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>
                    <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>
                      <li class="mx-3">Date = $date</li>
                      <li class="mx-3">Meet = $meet</li>
                      <li class="mx-3">Seller = $seller </li>

                    <?php endif; ?>



                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Notificaciones</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idNotificaciones" value="3">
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(3)">Enviar prueba</button> -->
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-3"
                        placeholder="Título para Cliente" value="<?php echo $dataNotificacionesEsp['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEsp-3"
                        placeholder="Texto para Cliente"><?php echo $dataNotificacionesEsp['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-3"
                        placeholder="Despedida para Cliente"
                        value="<?php echo $dataNotificacionesEsp['txtdespclie']; ?>" />
                      <hr>
                      <div class="form-section-title">Admin</div>
                      <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEsp-3"
                        placeholder="Título para Admin" value="<?php echo $dataNotificacionesEsp['txttituloadmin']; ?>">
                      <textarea class="mb-3 form-control" id="txtAdminRegEsp-3"
                        placeholder="Texto para Admin"><?php echo $dataNotificacionesEsp['txtadmin']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespAdminRegEsp-3"
                        placeholder="Despedida para Admin"
                        value="<?php echo $dataNotificacionesEsp['txtdespadmin']; ?>" />
                      <hr>
                      <div class="form-section-title">Vendedor</div>
                      <input type="text" class="mb-3 form-control" id="txtTituloVendRegEsp-3"
                        placeholder="Título para Vendedor" value="<?php echo $dataNotificacionesEsp['txttitulovend']; ?>">
                      <textarea class="mb-3 form-control" id="txtVendRegEsp-3"
                        placeholder="Texto para Vendedor"><?php echo $dataNotificacionesEsp['txtvend']; ?></textarea>

                      <input type="text" class="my-3 form-control" id="txtDespVendRegEsp-3"
                        placeholder="Despedida para Vendedor"
                        value="<?php echo $dataNotificacionesEsp['txtdespvend']; ?>" />


                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(3)">Actualizar
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(3)">Enviar prueba</button> -->
                        Notificaciones</button>
                    </div>
                  </div>
                </div>
              </div>


              <!--Columna: Cuestionario -->
              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade" id="pills-cuestesp" role="tabpanel" aria-labelledby="pills-cuestesp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>
                    <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>

                      <li class="mx-3">Link questionnaire = $link_questionnaire</li>

                    <?php endif; ?>



                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Cuestionario</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idCuestionario" value="11">
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(11)">Enviar prueba</button> -->
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-11"
                        placeholder="Título para Cliente" value="<?php echo $dataCuestionarioEsp['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEsp-11"
                        placeholder="Texto para Cliente"><?php echo $dataCuestionarioEsp['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-11"
                        placeholder="Despedida para Cliente" value="<?php echo $dataCuestionarioEsp['txtdespclie']; ?>" />
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(11)">Actualizar
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(11)">Enviar prueba</button> -->
                        Cuestionario</button>
                    </div>
                  </div>
                </div>
              </div>
              <!--Galeria-->
              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show" id="pills-galeriaesp" role="tabpanel"
                  aria-labelledby="pills-galeriaesp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>

                    <li class="mx-3"> Gallery Expiration Date = $expiration_date_gallery</li>


                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Galer&iacute;a</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idGaleria" value="4">
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(4)">Enviar prueba</button> -->
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-9"
                        placeholder="Título para Cliente" value="<?php echo $dataGaleriaEsp['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEsp-9"
                        placeholder="Texto para Cliente"><?php echo $dataGaleriaEsp['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-9"
                        placeholder="Despedida para Cliente" value="<?php echo $dataGaleriaEsp['txtdespclie']; ?>" />
                      <hr>
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(9)">Actualizar
                      <!-- <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(9)">Enviar prueba</button> -->
                        Galería</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade" id="pills-pagosesp" role="tabpanel" aria-labelledby="pills-pagosesp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" id="pills-cobroesp-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-cobroesp" type="button" role="tab" aria-controls="pills-cobroesp"
                        aria-selected="true">Cobro</button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="pills-exitosoesp-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-exitosoesp" type="button" role="tab" aria-controls="pills-exitosoesp"
                        aria-selected="false">Exitoso</button>
                    </li>
                  </ul>

                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="pills-cobroesp" role="tabpanel"
                      aria-labelledby="pills-cobroesp-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">

                        <li class="mx-3">Customer = <span>$customer</span> </li>
                        <!--<li class="mx-3">Card Payment = $payment_method</li>-->
                        <li class="mx-3">Amount = $amount</li>
                        <li class="mx-3">Bank Transfer Details= $bank_transfer_details</li>
                        <li class="mx-3">Link Card Payment = $link_card_payment</li>




                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Cobro</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idRegistro" value="13">
                          <!-- <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(13)">Enviar prueba</button> -->
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-13"
                            placeholder="Título para Cliente" value="<?php echo $dataCobroEsp['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEsp-13"
                            placeholder="Texto para Cliente"><?php echo $dataCobroEsp['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-13"
                            placeholder="Despedida para Cliente" value="<?php echo $dataCobroEsp['txtdespclie']; ?>" />
                          <hr>
                          <div class="form-section-title">Admin</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEsp-13"
                            placeholder="Título para Admin" value="<?php echo $dataCobroEsp['txttituloadmin']; ?>">
                          <textarea class="mb-3 form-control" id="txtAdminRegEsp-13"
                            placeholder="Texto para Admin"><?php echo $dataCobroEsp['txtadmin']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespAdminRegEsp-13"
                            placeholder="Despedida para Admin" value="<?php echo $dataCobroEsp['txtdespadmin']; ?>" />
                          <hr>
                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(13)">Actualizar
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(13)">Enviar prueba</button>
                            Cobro</button>

                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade" id="pills-exitosoesp" role="tabpanel"
                      aria-labelledby="pills-exitosoesp-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Customer = <span>$customer</span> </li>
                        <li class="mx-3">Amount = $amount</li>
                        <!-- <li class="mx-3">Bank Transfer Details= $bank_transfer_details</li>-->
                        <!--<li class="mx-3">Link Card Payment = $link_card_payment</li>-->
                        <li class="mx-3">Payment Method = $payment_method</li>
                        <li class="mx-3">ID payment = $id_payment</li>


                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Exitoso</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idRegistro" value="15">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(15)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-15"
                            placeholder="Título para Cliente" value="<?php echo $dataExitosoEsp['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEsp-15"
                            placeholder="Texto para Cliente"><?php echo $dataExitosoEsp['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-15"
                            placeholder="Despedida para Cliente" value="<?php echo $dataExitosoEsp['txtdespclie']; ?>" />
                          <hr>
                          <div class="form-section-title">Admin</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEsp-15"
                            placeholder="Título para Admin" value="<?php echo $dataExitosoEsp['txttituloadmin']; ?>">
                          <textarea class="mb-3 form-control" id="txtAdminRegEsp-15"
                            placeholder="Texto para Admin"><?php echo $dataExitosoEsp['txtadmin']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespAdminRegEsp-15"
                            placeholder="Despedida para Admin" value="<?php echo $dataExitosoEsp['txtdespadmin']; ?>" />
                          <hr>
                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(15)">Actualizar
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(15)">Enviar prueba</button>
                            Exitoso</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade" id="pills-contratosesp" role="tabpanel"
                  aria-labelledby="pills-contratosesp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" id="pills-contratoenviadoesp-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-contratoenviadoesp" type="button" role="tab"
                        aria-controls="pills-contratoenviadoesp" aria-selected="true">Enviado</button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="pills-contratofirmadoesp-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-contratofirmadoesp" type="button" role="tab"
                        aria-controls="pills-contratofirmadoesp" aria-selected="false">Exitoso</button>
                    </li>
                  </ul>

                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="pills-contratoenviadoesp" role="tabpanel"
                      aria-labelledby="pills-contratoenviadoesp-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">

                        <li class="mx-3">Customer = <span>$customer</span> </li>
                        <li class="mx-3"> Contract Download Link = $contract_download_link</li>
                        <li class="mx-3">Assigned Seller Email = $assigned_seller_email</li>
                        <!--<li class="mx-3">Card Payment = $payment_method</li>-->
                        <!--<li class="mx-3">Amount = $amount</li>-->
                        <!-- <li class="mx-3">Bank Transfer Details= $bank_transfer_details</li>-->
                        <!--<li class="mx-3">Link Card Payment = $link_card_payment</li>-->




                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Contrato Enviado</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idRegistro" value="17">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(17)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-17"
                            placeholder="Título para Cliente"
                            value="<?php echo $dataContratoEnviadoEsp['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEsp-17"
                            placeholder="Texto para Cliente"><?php echo $dataContratoEnviadoEsp['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-17"
                            placeholder="Despedida para Cliente"
                            value="<?php echo $dataContratoEnviadoEsp['txtdespclie']; ?>" />
                          <hr>
                          <div class="form-section-title">Admin</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEsp-17"
                            placeholder="Título para Admin"
                            value="<?php echo $dataContratoEnviadoEsp['txttituloadmin']; ?>">
                          <textarea class="mb-3 form-control" id="txtAdminRegEsp-17"
                            placeholder="Texto para Admin"><?php echo $dataContratoEnviadoEsp['txtadmin']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespAdminRegEsp-17"
                            placeholder="Despedida para Admin"
                            value="<?php echo $dataContratoEnviadoEsp['txtdespadmin']; ?>" />
                          <hr>
                          <div class="form-section-title">Vendedor</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloVendRegEsp-17"
                            placeholder="Título para Vendedor"
                            value="<?php echo $dataContratoEnviadoEsp['txttitulovend']; ?>">
                          <textarea class="mb-3 form-control" id="txtVendRegEsp-17"
                            placeholder="Texto para Vendedor"><?php echo $dataContratoEnviadoEsp['txtvend']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespVendRegEsp-17"
                            placeholder="Despedida para Vendedor"
                            value="<?php echo $dataContratoEnviadoEsp['txtdespvend']; ?>" />

                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(17)">Actualizar
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(17)">Enviar prueba</button>
                            Contrato Enviado</button>

                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade" id="pills-contratofirmadoesp" role="tabpanel"
                      aria-labelledby="pills-contratofirmadoesp-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Customer = <span>$customer</span> </li>
                        <li class="mx-3"> Contract Download Link = $contract_download_link</li>
                        <li class="mx-3">Assigned Seller Email = $assigned_seller_email</li>


                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Contrato Firmado</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idRegistro" value="19">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(19)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-19"
                            placeholder="Título para Cliente"
                            value="<?php echo $dataContratoFirmadoEsp['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEsp-19"
                            placeholder="Texto para Cliente"><?php echo $dataContratoFirmadoEsp['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-19"
                            placeholder="Despedida para Cliente"
                            value="<?php echo $dataContratoFirmadoEsp['txtdespclie']; ?>" />
                          <hr>
                          <div class="form-section-title">Admin</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEsp-19"
                            placeholder="Título para Admin"
                            value="<?php echo $dataContratoFirmadoEsp['txttituloadmin']; ?>">
                          <textarea class="mb-3 form-control" id="txtAdminRegEsp-19"
                            placeholder="Texto para Admin"><?php echo $dataContratoFirmadoEsp['txtadmin']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespAdminRegEsp-19"
                            placeholder="Despedida para Admin"
                            value="<?php echo $dataContratoFirmadoEsp['txtdespadmin']; ?>" />
                          <hr>
                          <div class="form-section-title">Vendedor</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloVendRegEsp-19"
                            placeholder="Título para Vendedor"
                            value="<?php echo $dataContratoFirmadoEsp['txttitulovend']; ?>">
                          <textarea class="mb-3 form-control" id="txtVendRegEsp-19"
                            placeholder="Texto para Vendedor"><?php echo $dataContratoFirmadoEsp['txtvend']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespVendRegEsp-19"
                            placeholder="Despedida para Vendedor"
                            value="<?php echo $dataContratoFirmadoEsp['txtdespvend']; ?>" />

                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(19)">Actualizar
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(19)">Enviar prueba</button>
                            Contrato Firmado</button>

                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            <?php endif; ?>


            <!--Galeria-->
            <?php if ($tipoUsuario == "2"): ?>

              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="pills-galeriaesp" role="tabpanel"
                  aria-labelledby="pills-galeriaesp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>

                    <li class="mx-3"> Gallery Expiration Date = $expiration_date_gallery</li>


                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Galer&iacute;a</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idGaleria" value="4">
                      <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(4)">Enviar prueba</button>
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-9"
                        placeholder="Título para Cliente" value="<?php echo $dataGaleriaEsp['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEsp-9"
                        placeholder="Texto para Cliente"><?php echo $dataGaleriaEsp['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-9"
                        placeholder="Despedida para Cliente" value="<?php echo $dataGaleriaEsp['txtdespclie']; ?>" />
                      <hr>
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(9)">Actualizar
                      <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(9)">Enviar prueba</button>
                        Reagendar</button>
                    </div>
                  </div>
                </div>
              </div>

            <?php endif; ?>

            <?php if ($tipoUsuario == "3"): ?>
              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade show active" id="pills-pagosesp" role="tabpanel"
                  aria-labelledby="pills-pagosesp-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" id="pills-cobroesp-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-cobroesp" type="button" role="tab" aria-controls="pills-cobroesp"
                        aria-selected="true">Cobro</button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="pills-exitosoesp-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-exitosoesp" type="button" role="tab" aria-controls="pills-exitosoesp"
                        aria-selected="false">Exitoso</button>
                    </li>
                  </ul>

                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="pills-cobroesp" role="tabpanel"
                      aria-labelledby="pills-cobroesp-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">

                        <li class="mx-3">Customer = <span>$customer</span> </li>
                        <!--<li class="mx-3">Card Payment = $payment_method</li>-->
                        <li class="mx-3">Amount = $amount</li>
                        <li class="mx-3">Bank Transfer Details= $bank_transfer_details</li>
                        <li class="mx-3">Link Card Payment = $link_card_payment</li>




                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Cobro</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idRegistro" value="13">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(13)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-13"
                            placeholder="Título para Cliente" value="<?php echo $dataCobroEsp['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEsp-13"
                            placeholder="Texto para Cliente"><?php echo $dataCobroEsp['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-13"
                            placeholder="Despedida para Cliente" value="<?php echo $dataCobroEsp['txtdespclie']; ?>" />
                          <hr>
                          <div class="form-section-title">Admin</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEsp-13"
                            placeholder="Título para Admin" value="<?php echo $dataCobroEsp['txttituloadmin']; ?>">
                          <textarea class="mb-3 form-control" id="txtAdminRegEsp-13"
                            placeholder="Texto para Admin"><?php echo $dataCobroEsp['txtadmin']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespAdminRegEsp-13"
                            placeholder="Despedida para Admin" value="<?php echo $dataCobroEsp['txtdespadmin']; ?>" />
                          <hr>
                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(13)">Actualizar
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(13)">Enviar prueba</button>
                            Cobro</button>

                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade" id="pills-exitosoesp" role="tabpanel"
                      aria-labelledby="pills-exitosoesp-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Customer = <span>$customer</span> </li>
                        <li class="mx-3">Amount = $amount</li>
                        <!-- <li class="mx-3">Bank Transfer Details= $bank_transfer_details</li>-->
                        <!--<li class="mx-3">Link Card Payment = $link_card_payment</li>-->
                        <li class="mx-3">Payment Method = $payment_method</li>
                        <li class="mx-3">ID payment = $id_payment</li>


                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Exitoso</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idRegistro" value="15">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(15)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEsp-15"
                            placeholder="Título para Cliente" value="<?php echo $dataExitosoEsp['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEsp-15"
                            placeholder="Texto para Cliente"><?php echo $dataExitosoEsp['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEsp-15"
                            placeholder="Despedida para Cliente" value="<?php echo $dataExitosoEsp['txtdespclie']; ?>" />
                          <hr>
                          <div class="form-section-title">Admin</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEsp-15"
                            placeholder="Título para Admin" value="<?php echo $dataExitosoEsp['txttituloadmin']; ?>">
                          <textarea class="mb-3 form-control" id="txtAdminRegEsp-15"
                            placeholder="Texto para Admin"><?php echo $dataExitosoEsp['txtadmin']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespAdminRegEsp-15"
                            placeholder="Despedida para Admin" value="<?php echo $dataExitosoEsp['txtdespadmin']; ?>" />
                          <hr>
                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(15)">Actualizar
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(15)">Enviar prueba</button>
                            Exitoso</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>

          </div>
        </form>
      </div>
      <!--====================INGLEEES==============-->
      <div class="tab-pane fade show active" id="pills-english" role="tabpanel" aria-labelledby="pills-english-tab">

        <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
          <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="pills-leadseng-tab" data-bs-toggle="pill"
                data-bs-target="#pills-leadseng" type="button" role="tab" aria-controls="pills-leadseng"
                aria-selected="true">Leads</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-reagendareng-tab" data-bs-toggle="pill"
                data-bs-target="#pills-reagendareng" type="button" role="tab" aria-controls="pills-reagendareng"
                aria-selected="false">Reagendar</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-reagendaauteng-tab" data-bs-toggle="pill"
                data-bs-target="#pills-reagendaauteng" type="button" role="tab" aria-controls="pills-reagendaauteng"
                aria-selected="false">Reagenda automatica</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-noteng-tab" data-bs-toggle="pill" data-bs-target="#pills-noteng"
                type="button" role="tab" aria-controls="pills-noteng" aria-selected="false">Notificaciones</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-noteng-tab" data-bs-toggle="pill" data-bs-target="#pills-cuesteng"
                type="button" role="tab" aria-controls="pills-cuesteng" aria-selected="false">Cuestionario</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-galeriaeng-tab" data-bs-toggle="pill" data-bs-target="#pills-galeriaeng"
                type="button" role="tab" aria-controls="pills-galeriaeng" aria-selected="false">Galer&iacute;a</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-pagoseng-tab" data-bs-toggle="pill" data-bs-target="#pills-pagoseng"
                type="button" role="tab" aria-controls="pills-pagoseng" aria-selected="false">Pagos</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-contratosseng-tab" data-bs-toggle="pill"
                data-bs-target="#pills-contratosseng" type="button" role="tab" aria-controls="pills-contratosseng"
                aria-selected="false">Contratos</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-nextstepeng-tab" data-bs-toggle="pill"
                data-bs-target="#pills-nextstepeng" type="button" role="tab" aria-controls="pills-nextstepeng"
                aria-selected="false">Nextstep</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-cambioestusueeng-tab" data-bs-toggle="pill"
                data-bs-target="#pills-cambioestusueeng" type="button" role="tab" aria-controls="pills-cambioestusueeng"
                aria-selected="false">Cambio Estatus</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="pills-registroeng-tab" data-bs-toggle="pill"
                data-bs-target="#pills-registroeng" type="button" role="tab" aria-controls="pills-registroeng"
                aria-selected="false">Registro</button>
            </li>
          <?php endif; ?>
          <?php if ($tipoUsuario == "2"): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="pills-galeriaeng-tab" data-bs-toggle="pill"
                data-bs-target="#pills-galeriaeng" type="button" role="tab" aria-controls="pills-galeriaeng"
                aria-selected="true">Galer&iacute;a</button>
            </li>
          <?php endif; ?>
          <?php if ($tipoUsuario == "3"): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="pills-pagoseng-tab" data-bs-toggle="pill"
                data-bs-target="#pills-pagoseng" type="button" role="tab" aria-controls="pills-pagoseng"
                aria-selected="true">Pagos</button>
            </li>
          <?php endif; ?>

        </ul>
        <form id="formTextos">
          <div class="row">
            <!--Columna: Registro -->

            <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>
              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade" id="pills-registroeng" role="tabpanel"
                  aria-labelledby="pills-registroeng-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>
                    <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>
                      <li class="mx-3">Date = $date</li>
                      <li class="mx-3">Meet = $meet</li>
                      <li class="mx-3">Seller = $seller </li>
                    <?php endif; ?>


                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Registro</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idRegistro" value="4">
                      <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(4)">Enviar prueba</button>
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-4"
                        placeholder="Título para Cliente" value="<?php echo $dataRegistroEng['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEng-4"
                        placeholder="Texto para Cliente"><?php echo $dataRegistroEng['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEng-4"
                        placeholder="Despedida para Cliente" value="<?php echo $dataRegistroEng['txtdespclie']; ?>" />
                      <hr>
                      <div class="form-section-title">Admin</div>
                      <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEng-4"
                        placeholder="Título para Admin" value="<?php echo $dataRegistroEng['txttituloadmin']; ?>">
                      <textarea class="mb-3 form-control" id="txtAdminRegEng-4"
                        placeholder="Texto para Admin"><?php echo $dataRegistroEng['txtadmin']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespAdminRegEng-4"
                        placeholder="Despedida para Admin" value="<?php echo $dataRegistroEng['txtdespadmin']; ?>" />
                      <hr>
                      <div class="form-section-title">Vendedor</div>
                      <input type="text" class="mb-3 form-control" id="txtTituloVendRegEng-4"
                        placeholder="Título para Vendedor" value="<?php echo $dataRegistroEng['txttitulovend']; ?>">
                      <textarea class="mb-3 form-control" id="txtVendRegEng-4"
                        placeholder="Texto para Vendedor"><?php echo $dataRegistroEng['txtvend']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespVendRegEng-4"
                        placeholder="Despedida para Vendedor" value="<?php echo $dataRegistroEng['txtdespvend']; ?>" />
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(4)">Actualizar
                      <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(4)">Enviar prueba</button>
                        Registro</button>
                    </div>
                  </div>
                </div>
              </div>





              <!--Columna: Reagendar -->
              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade" id="pills-reagendareng" role="tabpanel"
                  aria-labelledby="pills-reagendareng-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>
                    <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>
                      <li class="mx-3">Date = $date</li>
                      <li class="mx-3">Meet = $meet</li>
                      <li class="mx-3">Seller = $seller </li>
                    <?php endif; ?>


                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Reagendar</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idReagendar" value="5">
                      <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(5)">Enviar prueba</button>
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-5"
                        placeholder="Título para Cliente" value="<?php echo $dataReagendarEng['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEng-5"
                        placeholder="Texto para Cliente"><?php echo $dataReagendarEng['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEng-5"
                        placeholder="Despedida para Cliente" value="<?php echo $dataReagendarEng['txtdespclie']; ?>" />
                      <hr>
                      <div class="form-section-title">Admin</div>
                      <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEng-5"
                        placeholder="Título para Admin" value="<?php echo $dataReagendarEng['txttituloadmin']; ?>">
                      <textarea class="mb-3 form-control" id="txtAdminRegEng-5"
                        placeholder="Texto para Admin"><?php echo $dataReagendarEng['txtadmin']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespAdminRegEng-5"
                        placeholder="Despedida para Admin" value="<?php echo $dataReagendarEng['txtdespadmin']; ?>" />
                      <hr>
                      <div class="form-section-title">Vendedor</div>
                      <input type="text" class="mb-3 form-control" id="txtTituloVendRegEng-5"
                        placeholder="Título para Vendedor" value="<?php echo $dataReagendarEng['txttitulovend']; ?>">
                      <textarea class="mb-3 form-control" id="txtVendRegEng-5"
                        placeholder="Texto para Vendedor"><?php echo $dataReagendarEng['txtvend']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespVendRegEng-5"
                        placeholder="Despedida para Vendedor" value="<?php echo $dataReagendarEng['txtdespvend']; ?>" />
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(5)">Actualizar
                      <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(5)">Enviar prueba</button>
                        Reagendar</button>
                    </div>
                  </div>

                </div>
              </div>
              <!--Columna: Reagenda automaica -->
              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade" id="pills-reagendaauteng" role="tabpanel"
                  aria-labelledby="pills-reagendaauteng-tab">


                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>
                    <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>
                      <li class="mx-3">Date = $date</li>
                      <li class="mx-3">Meet = $meet</li>
                      <li class="mx-3">Seller = $seller </li>
                      <li class="mx-3">Reschedule = $link_reschedule</li>
                    <?php endif; ?>


                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Reagenda automática</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idReagendar" value="2">
                      <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(2)">Enviar prueba</button>
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-8"
                        placeholder="Título para Cliente" value="<?php echo $dataReagendarAutoEng['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEng-8"
                        placeholder="Texto para Cliente"><?php echo $dataReagendarAutoEng['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEng-8"
                        placeholder="Despedida para Cliente"
                        value="<?php echo $dataReagendarAutoEng['txtdespclie']; ?>" />
                      <hr>
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(8)">Actualizar
                      <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(8)">Enviar prueba</button>
                        Reagendar</button>
                    </div>
                  </div>
                </div>
              </div>
              <!--Columna: Notificaciones -->
              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade" id="pills-noteng" role="tabpanel" aria-labelledby="pills-noteng-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>
                    <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>
                      <li class="mx-3">Date = $date</li>
                      <li class="mx-3">Meet = $meet</li>
                      <li class="mx-3">Seller = $seller </li>
                    <?php endif; ?>


                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Notificaciones</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idNotificaciones" value="6">
                      <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(6)">Enviar prueba</button>
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-6"
                        placeholder="Título para Cliente" value="<?php echo $dataNotificacionesEng['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEng-6"
                        placeholder="Texto para Cliente"><?php echo $dataNotificacionesEng['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEng-6"
                        placeholder="Despedida para Cliente"
                        value="<?php echo $dataNotificacionesEng['txtdespclie']; ?>" />
                      <hr>
                      <div class="form-section-title">Admin</div>
                      <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEng-6"
                        placeholder="Título para Admin" value="<?php echo $dataNotificacionesEng['txttituloadmin']; ?>">
                      <textarea class="mb-3 form-control" id="txtAdminRegEng-6"
                        placeholder="Texto para Admin"><?php echo $dataNotificacionesEng['txtadmin']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespAdminRegEng-6"
                        placeholder="Despedida para Admin"
                        value="<?php echo $dataNotificacionesEng['txtdespadmin']; ?>" />
                      <hr>
                      <div class="form-section-title">Vendedor</div>
                      <input type="text" class="mb-3 form-control" id="txtTituloVendRegEng-6"
                        placeholder="Título para Vendedor" value="<?php echo $dataNotificacionesEng['txttitulovend']; ?>">
                      <textarea class="mb-3 form-control" id="txtVendRegEng-6"
                        placeholder="Texto para Vendedor"><?php echo $dataNotificacionesEng['txtvend']; ?></textarea>

                      <input type="text" class="my-3 form-control" id="txtDespVendRegEng-6"
                        placeholder="Despedida para Vendedor"
                        value="<?php echo $dataNotificacionesEng['txtdespvend']; ?>" />


                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(6)">Actualizar
                      <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(6)">Enviar prueba</button>
                        Notificaciones</button>
                    </div>
                  </div>
                </div>
              </div>

              <!--Columna: Cuestionario -->
              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade" id="pills-cuesteng" role="tabpanel" aria-labelledby="pills-cuesteng-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>
                    <?php if ($tipoUsuario != "2" && $tipoUsuario != "3"): ?>

                      <li class="mx-3">Link questionnaire = $link_questionnaire</li>
                    <?php endif; ?>


                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Cuestionario</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idCuestionario" value="12">
                      <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(12)">Enviar prueba</button>
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-12"
                        placeholder="Título para Cliente" value="<?php echo $dataCuestionarioEng['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEng-12"
                        placeholder="Texto para Cliente"><?php echo $dataCuestionarioEng['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEng-12"
                        placeholder="Despedida para Cliente" value="<?php echo $dataCuestionarioEng['txtdespclie']; ?>" />

                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(12)">Actualizar
                      <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(12)">Enviar prueba</button>
                        Cuestionario</button>
                    </div>
                  </div>
                </div>
              </div>
              <!--Galeria-->
              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show" id="pills-galeriaeng" role="tabpanel"
                  aria-labelledby="pills-galeriaeng-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>

                    <li class="mx-3"> Gallery Expiration Date = $expiration_date_gallery</li>


                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Galer&iacute;a</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idGaleria" value="4">
                      <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(4)">Enviar prueba</button>
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-10"
                        placeholder="Título para Cliente" value="<?php echo $dataGaleriaEng['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEng-10"
                        placeholder="Texto para Cliente"><?php echo $dataGaleriaEng['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEng-10"
                        placeholder="Despedida para Cliente" value="<?php echo $dataGaleriaEng['txtdespclie']; ?>" />
                      <hr>
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(10)">Actualizar
                      <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(10)">Enviar prueba</button>
                        Galeria</button>
                    </div>
                  </div>
                </div>
              </div>

              <!--Columna: Pagos -->
              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade" id="pills-pagoseng" role="tabpanel" aria-labelledby="pills-pagoseng-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" id="pills-cobroeng-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-cobroeng" type="button" role="tab" aria-controls="pills-cobroeng"
                        aria-selected="true">Cobro</button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="pills-exitosoeng-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-exitosoeng" type="button" role="tab" aria-controls="pills-exitosoeng"
                        aria-selected="false">Exitoso</button>
                    </li>
                  </ul>


                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="pills-cobroeng" role="tabpanel"
                      aria-labelledby="pills-cobroeng-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Customer = <span>$customer</span> </li>
                        <li class="mx-3">Amount = $amount</li>
                        <li class="mx-3">Bank Transfer Details= $bank_transfer_details</li>
                        <li class="mx-3">Link Card Payment = $link_card_payment</li>



                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Cobro</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idRegistro" value="14">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(14)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-14"
                            placeholder="Título para Cliente" value="<?php echo $dataCobroEng['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEng-14"
                            placeholder="Texto para Cliente"><?php echo $dataCobroEng['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEng-14"
                            placeholder="Despedida para Cliente" value="<?php echo $dataCobroEng['txtdespclie']; ?>" />
                          <hr>
                          <div class="form-section-title">Admin</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEng-14"
                            placeholder="Título para Admin" value="<?php echo $dataCobroEng['txttituloadmin']; ?>">
                          <textarea class="mb-3 form-control" id="txtAdminRegEng-14"
                            placeholder="Texto para Admin"><?php echo $dataCobroEng['txtadmin']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespAdminRegEng-14"
                            placeholder="Despedida para Admin" value="<?php echo $dataCobroEng['txtdespadmin']; ?>" />
                          <hr>

                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(14)">Actualizar
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(14)">Enviar prueba</button>
                            Cobro</button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade" id="pills-exitosoeng" role="tabpanel"
                      aria-labelledby="pills-exitosoeng-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Customer = <span>$customer</span> </li>
                        <li class="mx-3">Amount = $amount</li>
                        <!-- <li class="mx-3">Bank Transfer Details= $bank_transfer_details</li>-->
                        <!--<li class="mx-3">Link Card Payment = $link_card_payment</li>-->
                        <li class="mx-3">Payment Method = $payment_method</li>
                        <li class="mx-3">ID payment = $id_payment</li>



                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Exitoso</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idRegistro" value="16">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(16)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-16"
                            placeholder="Título para Cliente" value="<?php echo $dataExitosoEng['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEng-16"
                            placeholder="Texto para Cliente"><?php echo $dataExitosoEng['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEng-16"
                            placeholder="Despedida para Cliente" value="<?php echo $dataExitosoEng['txtdespclie']; ?>" />
                          <hr>
                          <div class="form-section-title">Admin</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEng-16"
                            placeholder="Título para Admin" value="<?php echo $dataExitosoEng['txttituloadmin']; ?>">
                          <textarea class="mb-3 form-control" id="txtAdminRegEng-16"
                            placeholder="Texto para Admin"><?php echo $dataExitosoEng['txtadmin']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespAdminRegEng-16"
                            placeholder="Despedida para Admin" value="<?php echo $dataExitosoEng['txtdespadmin']; ?>" />
                          <hr>
                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(16)">Actualizar
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(16)">Enviar prueba</button>
                            Exitoso</button>

                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade" id="pills-contratosseng" role="tabpanel"
                  aria-labelledby="pills-contratosseng-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" id="pills-contratoenviadoeng-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-contratoenviadoeng" type="button" role="tab"
                        aria-controls="pills-contratoenviadoeng" aria-selected="true">Enviado</button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="pills-contratofirmadoeng-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-contratofirmadoeng" type="button" role="tab"
                        aria-controls="pills-contratofirmadoeng" aria-selected="false">Firmado</button>
                    </li>
                  </ul>


                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="pills-contratoenviadoeng" role="tabpanel"
                      aria-labelledby="pills-contratoenviadoeng-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Customer = <span>$customer</span> </li>
                        <li class="mx-3"> Contract Download Link = $contract_download_link</li>
                        <li class="mx-3">Assigned Seller Email = $assigned_seller_email</li>



                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Contrato Enviado</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idRegistro" value="18">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(18)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-18"
                            placeholder="Título para Cliente"
                            value="<?php echo $dataContratoEnviadoEng['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEng-18"
                            placeholder="Texto para Cliente"><?php echo $dataContratoEnviadoEng['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEng-18"
                            placeholder="Despedida para Cliente"
                            value="<?php echo $dataContratoEnviadoEng['txtdespclie']; ?>" />
                          <hr>
                          <div class="form-section-title">Admin</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEng-18"
                            placeholder="Título para Admin"
                            value="<?php echo $dataContratoEnviadoEng['txttituloadmin']; ?>">
                          <textarea class="mb-3 form-control" id="txtAdminRegEng-18"
                            placeholder="Texto para Admin"><?php echo $dataContratoEnviadoEng['txtadmin']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespAdminRegEng-18"
                            placeholder="Despedida para Admin"
                            value="<?php echo $dataContratoEnviadoEng['txtdespadmin']; ?>" />
                          <hr>
                          <div class="form-section-title">Vendedor</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloVendRegEng-18"
                            placeholder="Título para Vendedor"
                            value="<?php echo $dataContratoEnviadoEng['txttitulovend']; ?>">
                          <textarea class="mb-3 form-control" id="txtVendRegEng-18"
                            placeholder="Texto para Vendedor"><?php echo $dataContratoEnviadoEng['txtvend']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespVendRegEng-18"
                            placeholder="Despedida para Vendedor"
                            value="<?php echo $dataContratoEnviadoEng['txtdespvend']; ?>" />

                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(18)">Actualizar
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(18)">Enviar prueba</button>
                            Contrato Enviado</button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade" id="pills-contratofirmadoeng" role="tabpanel"
                      aria-labelledby="pills-contratofirmadoeng-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Customer = <span>$customer</span> </li>
                        <li class="mx-3"> Contract Download Link = $contract_download_link</li>
                        <li class="mx-3">Assigned Seller Email = $assigned_seller_email</li>



                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Exitoso</h3>
                          <div class="form-section-title">Contrato Firmado</div>
                          <input type="hidden" id="idRegistro" value="20">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(20)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-20"
                            placeholder="Título para Cliente"
                            value="<?php echo $dataContratoFirmadoEng['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEng-20"
                            placeholder="Texto para Cliente"><?php echo $dataContratoFirmadoEng['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEng-20"
                            placeholder="Despedida para Cliente"
                            value="<?php echo $dataContratoFirmadoEng['txtdespclie']; ?>" />
                          <hr>
                          <div class="form-section-title">Admin</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEng-20"
                            placeholder="Título para Admin"
                            value="<?php echo $dataContratoFirmadoEng['txttituloadmin']; ?>">
                          <textarea class="mb-3 form-control" id="txtAdminRegEng-20"
                            placeholder="Texto para Admin"><?php echo $dataContratoFirmadoEng['txtadmin']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespAdminRegEng-20"
                            placeholder="Despedida para Admin"
                            value="<?php echo $dataContratoFirmadoEng['txtdespadmin']; ?>" />
                          <hr>
                          <div class="form-section-title">Vendedor</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloVendRegEng-20"
                            placeholder="Título para Vendedor"
                            value="<?php echo $dataContratoFirmadoEng['txttitulovend']; ?>">
                          <textarea class="mb-3 form-control" id="txtVendRegEng-20"
                            placeholder="Texto para Vendedor"><?php echo $dataContratoFirmadoEng['txtvend']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespVendRegEng-20"
                            placeholder="Despedida para Vendedor"
                            value="<?php echo $dataContratoFirmadoEng['txtdespvend']; ?>" />

                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(20)">Actualizar
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(20)">Enviar prueba</button>
                            Contrato Firmado</button>

                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>


              <!--nextstep-->
              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show" id="pills-nextstepeng" role="tabpanel"
                  aria-labelledby="pills-nextstepeng-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>

                    <li class="mx-3">Link Dowload PDF = $linkNextstep </li>


                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Nextstep</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idGaleria" value="22">
                      <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(22)">Enviar prueba</button>
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-22"
                        placeholder="Título para Cliente" value="<?php echo $dataNexstepEng['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEng-22"
                        placeholder="Texto para Cliente"><?php echo $dataNexstepEng['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEng-22"
                        placeholder="Despedida para Cliente" value="<?php echo $dataNexstepEng['txtdespclie']; ?>" />
                      <hr>
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(22)">Actualizar
                      <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(22)">Enviar prueba</button>
                        Nextstep</button>
                    </div>
                  </div>
                </div>
              </div>

              <!--Cambio Estatus-->
              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show" id="pills-cambioestusueeng" role="tabpanel"
                  aria-labelledby="pills-cambioestusueeng-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="mx-3">Customer = <span>$customer</span> </li>
                    <li class="mx-3">Date = $date</li>
                    <li class="mx-3">Seller = $seller</li>
                    <li class="mx-3">Customer Number = $customer_number</li>
                    <li class="mx-3">Rejection Reason = $rejection_reason</li>
                    <li class="mx-3">Estatus = $estatus</li>


                  </ul>
                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Cambio Estatus</h3>
                      <div class="form-section-title">Admin</div>
                      <input type="hidden" id="idCambioEstatus" value="24">
                      <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(24)">Enviar prueba</button>
                      <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEng-24"
                        placeholder="Título para Admin" value="<?php echo $dataCambioEstatusEng['txttituloadmin']; ?>">
                      <textarea class="mb-3 form-control" id="txtAdminRegEng-24"
                        placeholder="Texto para Admin"><?php echo $dataCambioEstatusEng['txtadmin']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespAdminRegEng-24"
                        placeholder="Despedida para Admin" value="<?php echo $dataCambioEstatusEng['txtdespadmin']; ?>" />
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(24)">Actualizar Cambio
                      <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(24)">Enviar prueba</button>
                        Estatus</button>
                    </div>
                  </div>
                </div>
              </div>

              <!--Columna: Leads -->
              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="pills-leadseng" role="tabpanel"
                  aria-labelledby="pills-leadseng-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" id="pills-leadsemail1eng-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-leadsemail1eng" type="button" role="tab" aria-controls="pills-leadsemail1eng"
                        aria-selected="true">Email 1</button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="pills-leadsemail2eng-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-leadsemail2eng" type="button" role="tab" aria-controls="pills-leadsemail2eng"
                        aria-selected="false">Email 2</button>
                    </li>
                  </ul>

                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="pills-leadsemail1eng" role="tabpanel"
                      aria-labelledby="pills-leadsemail1eng-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Full Name = <span>$full_name</span> </li>
                        <li class="mx-3">When Are You Getting Married = $when_are_you_getting_married</li>
                         <li class="mx-3">Link form = $link_form</li>
                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Leads - Email 1</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idLeadsCorreo1" value="27">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(27)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-27"
                            placeholder="Título para Cliente" value="<?php echo $dataLeadsCorreo1Eng['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEng-27"
                            placeholder="Texto para Cliente"><?php echo $dataLeadsCorreo1Eng['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEng-27"
                            placeholder="Despedida para Cliente" value="<?php echo $dataLeadsCorreo1Eng['txtdespclie']; ?>" />
                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(27)">Update Email 1</button>
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(27)">Enviar prueba</button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade" id="pills-leadsemail2eng" role="tabpanel"
                      aria-labelledby="pills-leadsemail2eng-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Full Name = <span>$full_name</span> </li>
                        <li class="mx-3">When Are You Getting Married = $when_are_you_getting_married</li>
                         <li class="mx-3">Link form = $link_form</li>
                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Leads - Email 2</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idLeadsCorreo2" value="28">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(28)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-28"
                            placeholder="Título para Cliente" value="<?php echo $dataLeadsCorreo2Eng['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEng-28"
                            placeholder="Texto para Cliente"><?php echo $dataLeadsCorreo2Eng['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEng-28"
                            placeholder="Despedida para Cliente" value="<?php echo $dataLeadsCorreo2Eng['txtdespclie']; ?>" />
                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(28)">Update Email 2</button>
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(28)">Enviar prueba</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>





            <?php endif; ?>
            <?php if ($tipoUsuario == "2"): ?>

              <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="pills-galeriaeng" role="tabpanel"
                  aria-labelledby="pills-galeriaeng-tab">

                  <div class="col-md-12">
                    <div class="p-3 card">
                      <h3 class="mb-4">Galer&iacute;</h3>
                      <div class="form-section-title">Cliente</div>
                      <input type="hidden" id="idGaleria" value="4">
                      <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(4)">Enviar prueba</button>
                      <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-10"
                        placeholder="Título para Cliente" value="<?php echo $dataGaleriaEng['txttituloclie']; ?>">
                      <textarea class="mb-3 form-control" id="txtCliRegEng-10"
                        placeholder="Texto para Cliente"><?php echo $dataGaleriaEng['txtcli']; ?></textarea>
                      <input type="text" class="my-3 form-control" id="txtDespCliRegEng-10"
                        placeholder="Despedida para Cliente" value="<?php echo $dataGaleriaEng['txtdespclie']; ?>" />
                      <hr>
                      <button type="button" class="btn btn-primary btn-update" onclick="submitForm(10)">Actualizar
                      <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(10)">Enviar prueba</button>
                        Galeria</button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($tipoUsuario == "3"): ?>
              <div class="tab-content" id="pills-tabContent">

                <div class="tab-pane fade show active" id="pills-pagoseng" role="tabpanel"
                  aria-labelledby="pills-pagoseng-tab">
                  <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" id="pills-cobroeng-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-cobroeng" type="button" role="tab" aria-controls="pills-cobroeng"
                        aria-selected="true">Cobro</button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="pills-exitosoeng-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-exitosoeng" type="button" role="tab" aria-controls="pills-exitosoeng"
                        aria-selected="false">Exitoso</button>
                    </li>
                  </ul>


                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="pills-cobroeng" role="tabpanel"
                      aria-labelledby="pills-cobroeng-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Customer = <span>$customer</span> </li>
                        <li class="mx-3">Amount = $amount</li>
                        <li class="mx-3">Bank Transfer Details= $bank_transfer_details</li>
                        <li class="mx-3">Link Card Payment = $link_card_payment</li>



                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Cobro</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idRegistro" value="14">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(14)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-14"
                            placeholder="Título para Cliente" value="<?php echo $dataCobroEng['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEng-14"
                            placeholder="Texto para Cliente"><?php echo $dataCobroEng['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEng-14"
                            placeholder="Despedida para Cliente" value="<?php echo $dataCobroEng['txtdespclie']; ?>" />
                          <hr>
                          <div class="form-section-title">Admin</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEng-14"
                            placeholder="Título para Admin" value="<?php echo $dataCobroEng['txttituloadmin']; ?>">
                          <textarea class="mb-3 form-control" id="txtAdminRegEng-14"
                            placeholder="Texto para Admin"><?php echo $dataCobroEng['txtadmin']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespAdminRegEng-14"
                            placeholder="Despedida para Admin" value="<?php echo $dataCobroEng['txtdespadmin']; ?>" />
                          <hr>

                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(14)">Actualizar
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(14)">Enviar prueba</button>
                            Cobro</button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade" id="pills-exitosoeng" role="tabpanel"
                      aria-labelledby="pills-exitosoeng-tab">
                      <ul class="mb-3 nav nav-pills" id="pills-tab" role="tablist">
                        <li class="mx-3">Customer = <span>$customer</span> </li>
                        <li class="mx-3">Amount = $amount</li>
                        <!-- <li class="mx-3">Bank Transfer Details= $bank_transfer_details</li>-->
                        <!--<li class="mx-3">Link Card Payment = $link_card_payment</li>-->
                        <li class="mx-3">Payment Method = $payment_method</li>
                        <li class="mx-3">ID payment = $id_payment</li>



                      </ul>
                      <div class="col-md-12">
                        <div class="p-3 card">
                          <h3 class="mb-4">Exitoso</h3>
                          <div class="form-section-title">Cliente</div>
                          <input type="hidden" id="idRegistro" value="16">
                          <button type="button" class="btn btn-outline-secondary btn-test-top" onclick="sendTest(16)">Enviar prueba</button>
                          <input type="text" class="mb-3 form-control" id="txtTituloCliRegEng-16"
                            placeholder="Título para Cliente" value="<?php echo $dataExitosoEng['txttituloclie']; ?>">
                          <textarea class="mb-3 form-control" id="txtCliRegEng-16"
                            placeholder="Texto para Cliente"><?php echo $dataExitosoEng['txtcli']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespCliRegEng-16"
                            placeholder="Despedida para Cliente" value="<?php echo $dataExitosoEng['txtdespclie']; ?>" />
                          <hr>
                          <div class="form-section-title">Admin</div>
                          <input type="text" class="mb-3 form-control" id="txtTituloAdminRegEng-16"
                            placeholder="Título para Admin" value="<?php echo $dataExitosoEng['txttituloadmin']; ?>">
                          <textarea class="mb-3 form-control" id="txtAdminRegEng-16"
                            placeholder="Texto para Admin"><?php echo $dataExitosoEng['txtadmin']; ?></textarea>
                          <input type="text" class="my-3 form-control" id="txtDespAdminRegEng-16"
                            placeholder="Despedida para Admin" value="<?php echo $dataExitosoEng['txtdespadmin']; ?>" />
                          <hr>
                          <button type="button" class="btn btn-primary btn-update" onclick="submitForm(16)">Actualizar
                          <button type="button" class="btn btn-outline-secondary btn-test" onclick="sendTest(16)">Enviar prueba</button>
                            Exitoso</button>

                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

  </div>
  <?php include 'footer.php'; ?>

  <!-- Modal: Previsualización de plantilla -->
  <div class="modal fade" id="modalTemplatePreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTemplatePreviewTitle">Previsualización de plantilla</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body" style="overflow:auto; max-height:75vh;">
          <div id="modalPreviewVars" class="mb-2 text-muted small"></div>
          <div id="modalPreviewBody"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          <button type="button" class="btn btn-info" id="btnSendTestFromPreview">Enviar prueba</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.ckeditor.com/ckeditor5/47.4.0/ckeditor5.umd.js" crossorigin></script>
  <script src="https://cdn.ckeditor.com/ckeditor5-premium-features/47.4.0/ckeditor5-premium-features.umd.js" crossorigin></script>
  <script src="https://cdn.ckbox.io/ckbox/2.9.2/ckbox.js" crossorigin></script>



  <script>
    // Initialize premium CKEditor for plantilla pages (same options as plantillas_marketing.php)
    const editors = {};

    // Convert single-line title/despedida inputs into textareas so CKEditor can attach to them
    function convertTitleDespInputs() {
        const inputs = document.querySelectorAll('input[type="text"][id^="txtTitulo"], input[type="text"][id^="txtDesp"]');
        inputs.forEach(inp => {
            // avoid converting if already converted
            if (inp.tagName.toLowerCase() !== 'input') return;
            const ta = document.createElement('textarea');
            ta.className = inp.className;
            ta.id = inp.id;
            if (inp.placeholder) ta.placeholder = inp.placeholder;
            ta.value = inp.value || '';

            // create label based on id
            let labelText = '';
            if (ta.id.startsWith('txtTitulo')) labelText = 'Título';
            else if (ta.id.startsWith('txtDesp')) labelText = 'Despedida';

            if (labelText) {
                const lbl = document.createElement('label');
                lbl.className = 'ck-editor-label';
                lbl.setAttribute('for', ta.id);
                lbl.style.fontWeight = '600';
                lbl.style.display = 'block';
                lbl.style.marginBottom = '6px';
                lbl.innerText = labelText;
                inp.parentNode.insertBefore(lbl, inp);
            }

            inp.parentNode.replaceChild(ta, inp);

            // insert a <br> after the textarea for spacing if not present
            const br = document.createElement('br');
            if (!ta.nextSibling || ta.nextSibling.nodeName.toLowerCase() !== 'br') {
                ta.parentNode.insertBefore(br, ta.nextSibling);
            }
        });
    }

    async function initPremiumEditors() {
        if (!window.CKEDITOR) {
            console.warn('CKEditor not loaded yet.');
            return;
        }
        // Replace one-line inputs with textareas before querying them
        convertTitleDespInputs();
        // Ensure existing textareas also have labels and spacing
        function ensureLabelsForTextareas() {
            const tas = document.querySelectorAll('textarea[id^="txt"], textarea[id^="txtTitulo"], textarea[id^="txtDesp"]');
            tas.forEach(ta => {
                // add label if missing
                const prev = ta.previousElementSibling;
                if (!prev || !prev.classList || !prev.classList.contains('ck-editor-label')) {
                    let labelText = '';
                    if (ta.id.startsWith('txtTitulo')) labelText = 'Título';
                    else if (ta.id.startsWith('txtDesp')) labelText = 'Despedida';
                    else labelText = 'Cuerpo';
                    const lbl = document.createElement('label');
                    lbl.className = 'ck-editor-label';
                    lbl.setAttribute('for', ta.id);
                    lbl.style.fontWeight = '600';
                    lbl.style.display = 'block';
                    lbl.style.marginBottom = '6px';
                    lbl.innerText = labelText;
                    ta.parentNode.insertBefore(lbl, ta);
                }
                // add a <br> after for spacing if not present
                if (!ta.nextSibling || ta.nextSibling.nodeName.toLowerCase() !== 'br') {
                    const br = document.createElement('br');
                    ta.parentNode.insertBefore(br, ta.nextSibling);
                }
            });
        }
        ensureLabelsForTextareas();

        const textareas = document.querySelectorAll('textarea[id^="txtCliRegEsp"], textarea[id^="txtAdminRegEsp"], textarea[id^="txtCliRegAutoEsp"], textarea[id^="txtCliRegAutoEng"], textarea[id^="txtVendRegEsp"], textarea[id^="txtCliRegEng"], textarea[id^="txtAdminRegEng"], textarea[id^="txtVendRegEng"], textarea[id^="txtTitulo"], textarea[id^="txtDesp"]');

        const {
            ClassicEditor,
            Autosave,
            Essentials,
            Paragraph,
            Alignment,
            AutoImage,
            Autoformat,
            AutoLink,
            BlockQuote,
            Bold,
            CKBox,
            CKBoxImageEdit,
            CloudServices,
            Code,
            CodeBlock,
            Emoji,
            FindAndReplace,
            FontBackgroundColor,
            FontColor,
            FontFamily,
            FontSize,
            Fullscreen,
            GeneralHtmlSupport,
            Heading,
            Highlight,
            HorizontalLine,
            HtmlEmbed,
            ImageBlock,
            ImageCaption,
            ImageEditing,
            ImageInsert,
            ImageInsertViaUrl,
            ImageResize,
            ImageStyle,
            ImageTextAlternative,
            ImageToolbar,
            ImageUpload,
            ImageUtils,
            ImageInline,
            Indent,
            IndentBlock,
            Italic,
            Link,
            LinkImage,
            List,
            ListProperties,
            MediaEmbed,
            Mention,
            PageBreak,
            PasteFromOffice,
            PictureEditing,
            PlainTableOutput,
            RemoveFormat,
            ShowBlocks,
            SpecialCharacters,
            SpecialCharactersArrows,
            SpecialCharactersCurrency,
            SpecialCharactersEssentials,
            SpecialCharactersLatin,
            SpecialCharactersMathematical,
            SpecialCharactersText,
            Strikethrough,
            Subscript,
            Superscript,
            Table,
            TableCaption,
            TableCellProperties,
            TableColumnResize,
            TableLayout,
            TableProperties,
            TableToolbar,
            TextPartLanguage,
            TextTransformation,
            TodoList,
            Underline,
            WordCount,
            Undo,
            Font
        } = window.CKEDITOR;

        const {
            CaseChange,
            Comments,
            ExportPdf,
            ExportWord,
            ExportInlineStyles,
            Footnotes,
            FormatPainter,
            ImportWord,
            LineHeight,
            MergeFields,
            MultiLevelList,
            PasteFromOfficeEnhanced,
            TableOfContents,
            Template
        } = window.CKEDITOR_PREMIUM_FEATURES || {};

        const LICENSE_KEY = 'eyJhbGciOiJFUzI1NiJ9.eyJleHAiOjE3NzE5Nzc1OTksImp0aSI6IjZlYTYzYTRiLWQzMDYtNDczYy1hODJjLTNiNDViMTVmYmExMSIsInVzYWdlRW5kcG9pbnQiOiJodHRwczovL3Byb3h5LWV2ZW50LmNrZWRpdG9yLmNvbSIsImRpc3RyaWJ1dGlvbkNoYW5uZWwiOlsiY2xvdWQiLCJkcnVwYWwiLCJzaCJdLCJ3aGl0ZUxhYmVsIjp0cnVlLCJsaWNlbnNlVHlwZSI6InRyaWFsIiwiZmVhdHVyZXMiOlsiKiJdLCJ2YyI6IjAxNmMxYTFmIn0.FLUJzDsoToVazpaTFGyqREVQfVIEmgLUqBWTzOJAQ7XGkmmYS6yLGSSrUdHLt8NIJ5RAVh5LYhUjbPOqlTVU0w';
        const CLOUD_SERVICES_TOKEN_URL = 'https://q6xr11lo86kj.cke-cs.com/token/dev/8a1715f0d7c6c1d76b63cd8dd154347b6703703e413be3a67ce5a6a9f2cf?limit=10';

        const cuerpoConfig = {
            licenseKey: LICENSE_KEY,
            toolbar: { items: ['undo','redo','|','comment','|','importWord','exportWord','exportPdf','showBlocks','formatPainter','caseChange','findAndReplace','fullscreen','|','heading','|','fontSize','fontFamily','fontColor','fontBackgroundColor','highlight','|','bold','italic','underline','strikethrough','subscript','superscript','code','removeFormat','|','emoji','specialCharacters','horizontalLine','pageBreak','link','insertImage','insertImageViaUrl','ckbox','mediaEmbed','insertTable','tableOfContents','insertTemplate','blockQuote','codeBlock','htmlEmbed','|','alignment','lineHeight','|','bulletedList','numberedList','multiLevelList','todoList','outdent','indent'], shouldNotGroupWhenFull: false },
            plugins: [ CloudServices, Alignment,Autoformat,AutoImage,AutoLink,Autosave,BlockQuote,Bold,CaseChange,CKBox,CKBoxImageEdit,Code,CodeBlock,Comments,Emoji,Essentials,ExportInlineStyles,ExportPdf,ExportWord,FindAndReplace,FontBackgroundColor,FontColor,FontFamily,FontSize,Footnotes,FormatPainter,Fullscreen,GeneralHtmlSupport,Heading,Highlight,HorizontalLine,HtmlEmbed,ImageBlock,ImageCaption,ImageEditing,ImageInline,ImageInsert,ImageInsertViaUrl,ImageResize,ImageStyle,ImageTextAlternative,ImageToolbar,ImageUpload,ImageUtils,ImportWord,Indent,IndentBlock,Italic,LineHeight,Link,LinkImage,List,ListProperties,MediaEmbed,Mention,MergeFields,MultiLevelList,PageBreak,Paragraph,PasteFromOffice,PasteFromOfficeEnhanced,PictureEditing,PlainTableOutput,RemoveFormat,ShowBlocks,SpecialCharacters,SpecialCharactersArrows,SpecialCharactersCurrency,SpecialCharactersEssentials,SpecialCharactersLatin,SpecialCharactersMathematical,SpecialCharactersText,Strikethrough,Subscript,Superscript,Table,TableCaption,TableCellProperties,TableColumnResize,TableLayout,TableProperties,TableToolbar,Template,TextPartLanguage,TextTransformation,TodoList,Underline,WordCount,Undo,Font ],
            cloudServices: { tokenUrl: CLOUD_SERVICES_TOKEN_URL },
            exportPdf: { stylesheets: ['https://cdn.ckeditor.com/ckeditor5/47.4.0/ckeditor5.css','https://cdn.ckeditor.com/ckeditor5-premium-features/47.4.0/ckeditor5-premium-features.css'] },
            exportWord: { stylesheets: ['https://cdn.ckeditor.com/ckeditor5/47.4.0/ckeditor5.css','https://cdn.ckeditor.com/ckeditor5-premium-features/47.4.0/ckeditor5-premium-features.css'] },
            fontFamily: { supportAllValues: true },
            fontSize: { options: [8,10,12,14,'default',16,18,20,22,24,26,28,30,32,36], supportAllValues: true },
            heading: { options: [{ model: 'paragraph', title: 'Párrafo', class: 'ck-heading_paragraph' },{ model: 'heading1', view: 'h1', title: 'Encabezado 1' },{ model: 'heading2', view: 'h2', title: 'Encabezado 2' },{ model: 'heading3', view: 'h3', title: 'Encabezado 3' }] },
            htmlSupport: { allow: [{ name: /^.*$/, styles: true, attributes: true, classes: true }] },
            image: { toolbar: ['toggleImageCaption','imageTextAlternative','|','imageStyle:inline','imageStyle:wrapText','imageStyle:breakText','|','resizeImage','|','ckboxImageEdit'] },
            link: { addTargetToExternalLinks: true, defaultProtocol: 'https://' },
            list: { properties: { styles: true, startIndex: true, reversed: true } },
            table: { contentToolbar: ['tableColumn','tableRow','mergeTableCells','tableProperties','tableCellProperties'] },
            menuBar: { isVisible: true },
            placeholder: 'Escribe o pega tu contenido aquí...',
            language: 'es'
        };

        const simpleConfig = {
            licenseKey: LICENSE_KEY,
            plugins: [ Essentials, Paragraph, Bold, Italic, Underline, Font, List, Alignment, Link, Undo ],
            toolbar: [ 'undo', 'redo', '|', 'bold', 'italic', 'underline', '|', 'fontSize', 'fontFamily', '|', 'bulletedList', 'numberedList', 'alignment', '|', 'link' ],
            language: 'es',
            placeholder: 'Escribe aquí...',
            fontFamily: { supportAllValues: true },
            fontSize: { options: [8,10,12,14,'default',16,18,20,22,24,26,28,30,32,36], supportAllValues: true },
            link: { addTargetToExternalLinks: true, defaultProtocol: 'https://' }
        };

        // Initialize editors and preserve existing textarea content
        for (const ta of textareas) {
            try {
                const useSimple = ta.id.startsWith('txtTitulo') || ta.id.startsWith('txtDesp');
                const baseConfig = useSimple ? simpleConfig : cuerpoConfig;
                const config = Object.assign({}, baseConfig, { initialData: (ta.value || ta.innerHTML || '') });
                const editor = await ClassicEditor.create(ta, config);
                editors[ta.id] = editor;
            } catch (err) {
                console.error('Error al inicializar CKEditor en', ta.id, err);
            }
        }
    }

    // Start initialization
    initPremiumEditors().catch(err => console.error(err));

      // users list from server (id, nombre, apePat, correo)
      const usersList = <?php echo json_encode($usersList, JSON_HEX_TAG); ?> || [];


    function submitForm(eventID) {
      const isSpanish = $('#pills-spanish-tab').hasClass('active');
      const suffix = isSpanish ? 'Esp' : 'Eng';

      const formData = {
        id: eventID,
        txtTituloCli: $(`#txtTituloCliReg${suffix}-${eventID}`).val() || "",
        txtDespCli: $(`#txtDespCliReg${suffix}-${eventID}`).val() || "",
        txtTituloAdmin: $(`#txtTituloAdminReg${suffix}-${eventID}`).val() || "",
        txtDespAdmin: $(`#txtDespAdminReg${suffix}-${eventID}`).val() || "",
        txtTituloVend: $(`#txtTituloVendReg${suffix}-${eventID}`).val() || "",
        txtDespVend: $(`#txtDespVendReg${suffix}-${eventID}`).val() || "",
      };

      // If CKEditor instances exist for title/despedida fields, take content from them
      const tituloCliId = `txtTituloCliReg${suffix}-${eventID}`;
      if (editors[tituloCliId]) formData.txtTituloCli = editors[tituloCliId].getData();
      const despCliId = `txtDespCliReg${suffix}-${eventID}`;
      if (editors[despCliId]) formData.txtDespCli = editors[despCliId].getData();
      const tituloAdminId = `txtTituloAdminReg${suffix}-${eventID}`;
      if (editors[tituloAdminId]) formData.txtTituloAdmin = editors[tituloAdminId].getData();
      const despAdminId = `txtDespAdminReg${suffix}-${eventID}`;
      if (editors[despAdminId]) formData.txtDespAdmin = editors[despAdminId].getData();
      const tituloVendId = `txtTituloVendReg${suffix}-${eventID}`;
      if (editors[tituloVendId]) formData.txtTituloVend = editors[tituloVendId].getData();
      const despVendId = `txtDespVendReg${suffix}-${eventID}`;
      if (editors[despVendId]) formData.txtDespVend = editors[despVendId].getData();


      // Get CKEditor data using the stored instances:
      formData.txtCli = editors[`txtCliReg${suffix}-${eventID}`] !== undefined
        ? editors[`txtCliReg${suffix}-${eventID}`].getData() || ""
        : "";

      formData.txtAdmin = editors[`txtAdminReg${suffix}-${eventID}`] !== undefined
        ? editors[`txtAdminReg${suffix}-${eventID}`].getData() || ""
        : "";

      formData.txtVend = editors[`txtVendReg${suffix}-${eventID}`] !== undefined
        ? editors[`txtVendReg${suffix}-${eventID}`].getData() || ""
        : "";



      console.log(formData)


      $.ajax({
        url: 'guarda_plantilla.php', // Cambia por tu ruta PHP
        method: 'POST',
        data: formData,
        success: function (response) {
          console.log("response ", response)
          try {
            const res = JSON.parse(response); // Parseamos la respuesta del servidor

            if (res.status === 'success') {
              Swal.fire('¡Guardado!', res.message, 'success').then((result) => {
                if (result.isConfirmed) {
                  // Recargar la página después de que el usuario haga clic en "OK"
                  location.reload();
                }
              });
            } else {
              Swal.fire('Error', res.message, 'error');
            }
          } catch (error) {
            Swal.fire('Error', 'Hubo un problema al procesar la respuesta del servidor.', 'error');
            console.error('Error al parsear la respuesta del servidor:', error);
          }
        },
        error: function (xhr, status, error) {
          // Mostrar el error de la solicitud AJAX en consola y en el alert de SweetAlert
          Swal.fire('Error', `Hubo un problema al guardar: ${error}.`, 'error');
          console.error('Error en la solicitud AJAX:', error);
          console.error('Detalles de la solicitud:', xhr, status, error);
        }
      });
    }

    // Send a test email for templateId. Ask which recipient (select from usuarios + proyectos@conlineweb.com)
    function sendTest(templateId) {
      // build options: default 'proyectos@conlineweb.com' plus users
      let options = `<option value="proyectos@conlineweb.com">proyectos@conlineweb.com (Proyectos)</option>`;
      usersList.forEach(u => {
        const label = `${u.nombre} ${u.apePat} — ${u.correo}`;
        const email = u.correo;
        options += `<option value="${email}">${label}</option>`;
      });

      Swal.fire({
        title: 'Enviar correo de prueba',
        width: '680px', // make modal wider to avoid horizontal scroll
        html: `
          <div style="display:flex;flex-direction:column;gap:8px;align-items:center;justify-content:center;">
            <div style="width:100%;text-align:left;font-weight:600;">Enviar prueba a:</div>
            <div style="width:100%;max-width:560px;padding:0;margin:0;">
              <select id="testTarget" class="swal2-select" style="width:80%;padding:10px;border-radius:6px;border:1px solid #dcdcdc;box-sizing:border-box;">${options}</select>
            </div>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        preConfirm: () => {
          const sel = document.getElementById('testTarget');
          if (!sel || !sel.value) {
            Swal.showValidationMessage('Selecciona un destinatario');
            return false;
          }
          // simple email validation
          const val = sel.value.trim();
          const emailRe = /^\S+@\S+\.\S+$/;
          if (!emailRe.test(val)) {
            Swal.showValidationMessage('Selecciona un correo válido');
            return false;
          }
          return val;
        }
      }).then((result) => {
        if (!result.isConfirmed) return;
        const targetEmail = result.value;

        Swal.fire({
          title: 'Confirmar envío',
          text: `Se enviará el correo de prueba a ${targetEmail}`,
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Sí, enviar'
        }).then((confirmResult) => {
          if (!confirmResult.isConfirmed) return;

          $.ajax({
            url: 'enviar_prueba.php',
            method: 'POST',
            data: { id: templateId, to: targetEmail },
            success: function(response) {
              try {
                const res = (typeof response === 'string') ? JSON.parse(response) : response;
                if (res.status === 'success') {
                  Swal.fire('Enviado', res.message, 'success');
                } else {
                  Swal.fire('Error', res.message || 'No se pudo enviar el correo de prueba', 'error');
                }
              } catch (err) {
                Swal.fire('Error', 'Respuesta inesperada del servidor.', 'error');
                console.error(err);
              }
            },
            error: function(xhr, status, err) {
              Swal.fire('Error', `Error al enviar: ${err}`, 'error');
              console.error(xhr, status, err);
            }
          });
        });
      });

    // ---------- Preview helpers ----------
    function getSampleReplacements() {
      return {
        '$full_name': 'María Pérez',
        '$wedding_date': '2026-06-15',
        '$schedule_button': "<a href='#' style='background:#2563eb;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none;'>Reservar cita</a>",
        '$customer': 'Cliente Ejemplo',
        '$date': '2026-06-15 10:00',
        '$seller': 'Vendedor Ejemplo',
        '$link_questionnaire': 'https://example.com/cuestionario',
        '$link_reschedule': 'https://example.com/reagenda',
        '$amount': '$2,500.00',
        '$expiration_date_gallery': '2026-07-01',
        '$schedule_every_days': '3',
        '$linkNextstep': 'https://example.com/nextstep'
      };
    }

    function replacePlaceholders(text, replacements) {
      if (!text) return '';
      let out = String(text);
      for (const key in replacements) {
        const re = new RegExp(escapeRegExp(key), 'g');
        out = out.replace(re, replacements[key]);
      }
      return out;
    }

    function escapeRegExp(string) {
      return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function escapeHtml(text) {
      if (text === null || text === undefined) return '';
      return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function buildEmailHtml({ asunto = '', titulo = '', cuerpo = '', despedida = '', tablaOrigen = '', leadId = '', templateId = null }) {
      const templatePart = templateId ? `&template_id=${encodeURIComponent(templateId)}` : '';
      return `
<html>
<head>
  <meta charset="utf-8">
  <title>${escapeHtml(asunto)}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Open+Sans&display=swap');
    body { margin:0; padding:0; }
    .bg{ width:96%; margin:0 auto; padding:50px 0; background-color:#e8e8e8; }
    p { margin:15px; }
    .container { width:90%; max-width:600px; margin:0 auto; border-radius:30px; background-color:#fff; line-height:1.5; font-size:1.5rem; box-shadow:0px 4px 6px rgba(0,0,0,0.1); font-family: 'Open Sans', sans-serif; }
    .card-container { padding:10px 20px; margin:10px; overflow:hidden; }
    .card-container img { max-width:100%; height:auto; display:block; }
    .image-size-small,
    .image-size-small img { width:25%; height:auto; }
    .image-size-medium,
    .image-size-medium img { width:50%; height:auto; }
    .image-size-large,
    .image-size-large img { width:75%; height:auto; }
    .btn-agenda { font-size:1.5rem; width:100%; }
    .header { text-align:left; padding:10px 30px; font-size:1.5rem; background-color:#eee8dc; color:#3B3B3B; font-weight:600; margin-top:13px; }
    .content { padding:20px 0 0 0; margin:0; }
    .logo { width:120px; margin:0 auto; display:block; }
    @media screen and (min-width:768px) {
      .container { max-width:750px; font-size:1.5rem; }
      .header { font-size:1.7rem; padding:10px 50px; }
      .card-container { padding:10px 30px; }
      .logo { width:140px; }
      .btn-agenda { font-size:1.7rem; width:50%; }
    }
  </style>
</head>
<body>
  <div class='bg'>
    <div class='container'>
      <div class='content'>
        <div style='text-align:center;'>
          ${titulo}
        </div>
        <div class='header'>${titulo}</div>
        <div class='card-container'>
          ${cuerpo}
        </div>
      </div>
    </div>
    <div style='text-align:center; margin-top:20px;'>
      <img class='logo' alt='efegephologo' src='https://citas.efegepho.com.mx/admin/assets/img/logofgep.png'/>
    </div>
    <img src='https://citas.efegepho.com.mx/pixel/open.php?id=${encodeURIComponent(leadId)}&tabla_origen=${encodeURIComponent(tablaOrigen)}&correo=1${templatePart}' width='1' height='1' style='display:none' />
  </div>
</body>
</html>
`;
    }

    function addPreviewButtons() {
      document.querySelectorAll('.btn-test-top').forEach(btn => {
        const parent = btn.parentNode;
        if (parent.querySelector('.btn-preview-top')) return; // already added
        const previewBtn = document.createElement('button');
        previewBtn.type = 'button';
        previewBtn.className = 'btn btn-outline-secondary btn-preview-top';
        previewBtn.style.marginLeft = '8px';
        previewBtn.innerText = 'Previsualizar';
        parent.appendChild(previewBtn);
        previewBtn.addEventListener('click', function () {
          // find closest card and id
          const card = btn.closest('.p-3.card') || btn.closest('.card') || btn.parentNode;
          let hid = card.querySelector('input[type="hidden"]');
          const templateId = hid ? hid.value : null;
          previewTemplate(templateId, card);
        });
      });
    }

    function previewTemplate(templateId, card) {
      const replacements = getSampleReplacements();

      // Find title / cuerpo / despedida inside the card
      let titulo = '';
      let cuerpo = '';
      let despedida = '';

      // Prefer CKEditor instances if present
      const inputs = card.querySelectorAll('textarea[id^="txt"], textarea');
      inputs.forEach(el => {
        const id = el.id || '';
        const val = (window.editors && window.editors[id]) ? window.editors[id].getData() : (el.value || el.innerHTML || '');
        if (id.toLowerCase().includes('titulo')) titulo = val;
        else if (id.toLowerCase().includes('desp')) despedida = val;
        else cuerpo += val;
      });

      // Fallback: look for inputs (if not converted)
      if (!titulo) {
        const inp = card.querySelector('input[id*=titul]');
        if (inp) titulo = inp.value || '';
      }
      if (!despedida) {
        const inp = card.querySelector('input[id*=desp]');
        if (inp) despedida = inp.value || '';
      }

      // Replace placeholders
      titulo = replacePlaceholders(titulo, replacements);
      cuerpo = replacePlaceholders(cuerpo, replacements);
      despedida = replacePlaceholders(despedida, replacements);

      // Build HTML and show modal
      const html = buildEmailHtml({ titulo, cuerpo, despedida });
      document.getElementById('modalPreviewBody').innerHTML = html;
      const visibleKeys = Object.keys(replacements).filter(k => replacements[k] !== undefined);
      document.getElementById('modalPreviewVars').innerText = visibleKeys.map(k => `${k} = ${replacements[k]}`).join(' | ');
      document.getElementById('modalTemplatePreviewTitle').innerText = 'Previsualización — ' + (templateId ? `ID ${templateId}` : 'Sin ID');
      const previewModal = new bootstrap.Modal(document.getElementById('modalTemplatePreview'));
      // store id for sending test later
      document.getElementById('btnSendTestFromPreview').dataset.templateId = templateId || '';
      previewModal.show();
    }

    // Hook send from preview to existing sendTest (if template has id)
    document.addEventListener('click', function (e) {
      if (e.target && e.target.id === 'btnSendTestFromPreview') {
        const tid = e.target.dataset.templateId;
        if (!tid) {
          Swal.fire('Aviso', 'La plantilla no está guardada. Guarda antes de enviar una prueba.', 'warning');
          return;
        }
        sendTest(tid);
      }
    });

    // Add preview buttons after a short delay (ensures layout is ready)
    setTimeout(addPreviewButtons, 200);

    }



  </script>
</body>

</html>