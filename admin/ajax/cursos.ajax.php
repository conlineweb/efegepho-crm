<?php

require_once "../controladores/cursos.controlador.php";
require_once "../modelos/cursos.modelo.php";

class AjaxCursos{

	/*=============================================
	EDITAR CURSOS
	=============================================*/	

	public $idCurso;

	public function ajaxEditarCurso(){

		$item = "id";
		$valor = $this->idCurso;

		$respuesta = ControladorCursos::ctrMostrarCursos($item, $valor);

		echo json_encode($respuesta);

	}

	/*=============================================
	VALIDAR NO REPETIR CURSO
	=============================================*/	

	public $validarCurso;

	public function ajaxValidarCurso(){

		$item = "curso";
		$valor = $this->validarCurso;

		$respuesta = ControladorCursos::ctrMostrarCursos($item, $valor);

		echo json_encode($respuesta);

	}
}

/*=============================================
EDITAR CURSOS
=============================================*/	
if(isset($_POST["idCurso"])){

	$curso = new AjaxCursos();
	$curso -> idCurso = $_POST["idCurso"];
	$curso -> ajaxEditarCurso();
}

/*=============================================
VALIDAR NO REPETIR CURSO
=============================================*/

if(isset( $_POST["validarCurso"])){

	$valUsuario = new AjaxCursos();
	$valUsuario -> validarCurso = $_POST["validarCurso"];
	$valUsuario -> ajaxValidarCurso();

}