<?php

class ControladorCursos{

	/*=============================================
	CREAR CURSO
	=============================================*/
		
	static public function ctrCrearCurso(){

		if(isset($_POST["nuevoCurso"])){

			/*=============================================
			VALIDAR IMAGEN
			=============================================*/

		   	$ruta = "";

				if(isset($_FILES["nuevaFoto"]["tmp_name"])){

					list($ancho, $alto) = getimagesize($_FILES["nuevaFoto"]["tmp_name"]);

					$nuevoAncho = 800;
					$nuevoAlto = 800;

					/*=============================================
					CREAMOS EL DIRECTORIO DONDE VAMOS A GUARDAR LA FOTO DEL USUARIO
					=============================================*/

					$directorio = "vistas/img/cursos/".$_POST["nuevoCurso"];

					mkdir($directorio, 0755);

					/*=============================================
					DE ACUERDO AL TIPO DE IMAGEN APLICAMOS LAS FUNCIONES POR DEFECTO DE PHP
					=============================================*/

					if($_FILES["nuevaFoto"]["type"] == "image/jpeg"){

						/*=============================================
						GUARDAMOS LA IMAGEN EN EL DIRECTORIO
						=============================================*/

						$aleatorio = mt_rand(100,999);

						$ruta = "vistas/img/cursos/".$_POST["nuevoCurso"]."/".$aleatorio.".jpg";

						$origen = imagecreatefromjpeg($_FILES["nuevaFoto"]["tmp_name"]);						

						$destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

						imagecopyresized($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

						imagejpeg($destino, $ruta);

					}

					if($_FILES["nuevaFoto"]["type"] == "image/png"){

						/*=============================================
						GUARDAMOS LA IMAGEN EN EL DIRECTORIO
						=============================================*/

						$aleatorio = mt_rand(100,999);

						$ruta = "vistas/img/cursos/".$_POST["nuevoCurso"]."/".$aleatorio.".png";

						$origen = imagecreatefrompng($_FILES["nuevaFoto"]["tmp_name"]);						

						$destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

						imagecopyresized($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

						imagepng($destino, $ruta);
					}
				}

			$tabla = "cursos";

			$datos = array("curso" => $_POST["nuevoCurso"],
							"categoria" => $_POST["nuevaCategoria"],
							"descripcion" => $_POST["nuevaDescripcionCurso"],
							"precio" => $_POST["nuevoPrecio"],
							"requisitos" => $_POST["nuevoRequisitoCurso"],
							"modalidad" => $_POST["nuevaModalidad"],
							"objetivo" => $_POST["nuevoObjetivoCurso"],
							"portada" => $ruta
							);

			$respuesta = ModeloCursos::mdlCrearCurso($tabla, $datos);

			if($respuesta == "ok"){

				echo'<script>

				swal({
					  type: "success",
					  title: "El curso ha sido guardada correctamente",
					  showConfirmButton: true,
					  confirmButtonText: "Cerrar"
					  }).then(function(result){
								if (result.value) {

								window.location = "cursos.php";

								}
							})

				</script>';
			}
		}
	}

	/*=============================================
	MOSTRAR CURSOS
	=============================================*/

	static public function ctrMostrarCursos($item, $valor){

		$tabla = "cursos";

		$respuesta = ModeloCursos::mdlMostrarCursos($tabla, $item, $valor);

		return $respuesta;
	}

	/*=============================================
	EDITAR CURSO
	=============================================*/

	static public function ctrEditarCurso(){

		if(isset($_POST["editarCurso"])){

			/*=============================================
			VALIDAR IMAGEN
			=============================================*/

			$ruta = $_POST["fotoActual"];

			if(isset($_FILES["editarFoto"]["tmp_name"]) && !empty($_FILES["editarFoto"]["tmp_name"])){

				list($ancho, $alto) = getimagesize($_FILES["editarFoto"]["tmp_name"]);

				$nuevoAncho = 800;
				$nuevoAlto = 800;

				/*=============================================
				CREAMOS EL DIRECTORIO DONDE VAMOS A GUARDAR LA FOTO DEL USUARIO
				=============================================*/

				$directorio = "vistas/img/cursos/".$_POST["editarCurso"];

				/*=============================================
				PRIMERO PREGUNTAMOS SI EXISTE OTRA IMAGEN EN LA BD
				=============================================*/

				if(!empty($_POST["fotoActual"]) && $_POST["fotoActual"] != "vistas/img/cursos/default/anonymous.png"){

					unlink($_POST["fotoActual"]);

				}else{

					mkdir($directorio, 0755);
				}	

				/*=============================================
				DE ACUERDO AL TIPO DE IMAGEN APLICAMOS LAS FUNCIONES POR DEFECTO DE PHP
				=============================================*/

				if($_FILES["editarFoto"]["type"] == "image/jpeg"){

					/*=============================================
					GUARDAMOS LA IMAGEN EN EL DIRECTORIO
					=============================================*/

					$aleatorio = mt_rand(100,999);

					$ruta = "vistas/img/cursos/".$_POST["editarCurso"]."/".$aleatorio.".jpg";

					$origen = imagecreatefromjpeg($_FILES["editarFoto"]["tmp_name"]);						

					$destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

					imagecopyresized($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

					imagejpeg($destino, $ruta);
				}

				if($_FILES["editarFoto"]["type"] == "image/png"){

					/*=============================================
					GUARDAMOS LA IMAGEN EN EL DIRECTORIO
					=============================================*/

					$aleatorio = mt_rand(100,999);

					$ruta = "vistas/img/cursos/".$_POST["editarCurso"]."/".$aleatorio.".png";

					$origen = imagecreatefrompng($_FILES["editarFoto"]["tmp_name"]);						

					$destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

					imagecopyresized($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

					imagepng($destino, $ruta);
				}
			}

			$tabla = "cursos";

			$datos = array("curso" => $_POST["editarCurso"],
							"categoria" => $_POST["editarCategoria"],
							"descripcion" => $_POST["editarDescripcionCurso"],
							"precio" => $_POST["editarPrecio"],
							"requisitos" => $_POST["editarRequisitoCurso"],
							"modalidad" => $_POST["editarModalidad"],
							"objetivo" => $_POST["editarObjetivoCurso"],
							"portada" => $ruta,
							 "id"=>$_POST["idCurso"]

							);

			$respuesta = ModeloCursos::mdlEditarCurso($tabla, $datos);

			if($respuesta == "ok"){

				echo'<script>

				swal({
					  type: "success",
					  title: "El curso ha sido editado correctamente",
					  showConfirmButton: true,
					  confirmButtonText: "Cerrar"
					  }).then(function(result){
								if (result.value) {

								window.location = "consultar-cursos.php";

								}
							})

				</script>';

			}
		}
	}

	/*=============================================
	ELIMINAR CURSO
	=============================================*/
	
	static public function ctrEliminarCurso(){

		if(isset($_GET["idCursoEli"])){

			$tabla = "cursos";
			$datos = $_GET["idCursoEli"];

			if($_GET["imagenCursoEli"] != "" && $_GET["imagenCursoEli"] != "vistas/img/cursos/default/anonymous.png"){

				unlink($_GET["imagenCursoEli"]);
				rmdir('vistas/img/cursos/'.$_GET["nombreCursoEli"]);

			}

			$respuesta = ModeloCursos::mdlEliminarCurso($tabla, $datos);

			if($respuesta == "ok"){

				echo'<script>

				swal({
					  type: "success",
					  title: "El curso ha sido borrado correctamente",
					  showConfirmButton: true,
					  confirmButtonText: "Cerrar"
					  }).then(function(result){
								if (result.value) {

								window.location = "consultar-cursos.php";

								}
							})

				</script>';

			}	

		}
	}
	
}