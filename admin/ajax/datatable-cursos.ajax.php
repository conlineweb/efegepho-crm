<?php

require_once "../controladores/cursos.controlador.php";
require_once "../modelos/cursos.modelo.php";

class TablaCursos{

	public function mostrarTablaCursos(){


		$item = null;
		$valor = null;

		$cursos = ControladorCursos::ctrMostrarCursos($item, $valor);

		$datosJson = '{
		  "data": [';

		for($i = 0; $i < count($cursos); $i++){

			/*=============================================
 	 		TRAEMOS LA IMAGEN
  			=============================================*/ 

		  	$imagen = "<img src='".$cursos[$i]["portada"]."' width='50px'>";

			$botones =  "<div class='btn-group'><button type='button' class='btn btn-primary edit-btn btnEditarCurso' idCurso='".$cursos[$i]["id"]."' idCursoEli='".$cursos[$i]["id"]."' data-toggle='modal' data-target='#modalEditarCurso'>Editar</button></div><div class='btn-group'><button type='button' class='btn btn-warning edit-btn btnTemas' idCurso='".$cursos[$i]["id"]."' codigo='".$cursos[$i]["id"]."' portada='".$cursos[$i]["portada"]."'>Temas</button></div>"; 

			$datosJson .='[
				"'.($i+1).'",
		      	"'.$cursos[$i]["curso"].'",
		      	"'.$cursos[$i]["categoria"].'",
		      	"'.$cursos[$i]["precio"].'",
		      	"'.$botones.'"
		      	],';
		      }

		      $datosJson = substr($datosJson, 0, -1);
		      $datosJson .=   '] 
		  }';
		  
		  echo $datosJson;
	}
}

$mostrarCursos = new TablaCursos();
$mostrarCursos -> mostrarTablaCursos();

