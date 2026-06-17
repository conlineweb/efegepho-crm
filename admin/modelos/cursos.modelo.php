<?php

require_once "conexion.php";

class ModeloCursos{

	/*=============================================
	CREAR CURSO
	=============================================*/
	
	static public function mdlCrearCurso($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("INSERT INTO $tabla(categoria, curso, descripcion, precio, portada, modalidad, requisitos, objetivo) VALUES (:categoria, :curso, :descripcion, :precio, :portada, :modalidad, :requisitos, :objetivo)");

		$stmt->bindParam(":categoria", $datos["categoria"], PDO::PARAM_STR);
		$stmt->bindParam(":curso", $datos["curso"], PDO::PARAM_STR);
		$stmt->bindParam(":descripcion", $datos["descripcion"], PDO::PARAM_STR);
		$stmt->bindParam(":precio", $datos["precio"], PDO::PARAM_STR);
		$stmt->bindParam(":portada", $datos["portada"], PDO::PARAM_STR);
		$stmt->bindParam(":modalidad", $datos["modalidad"], PDO::PARAM_STR);
		$stmt->bindParam(":requisitos", $datos["requisitos"], PDO::PARAM_STR);
		$stmt->bindParam(":objetivo", $datos["objetivo"], PDO::PARAM_STR);

		if($stmt->execute()){

			return "ok";

		}else{

			return "error";
		
		}

		$stmt->close();
		$stmt = null;
	}

	/*=============================================
	MOSTRAR CURSOS
	=============================================*/

	static public function mdlMostrarCursos($tabla, $item, $valor){

		if($item != null){

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");

			$stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt -> execute();

			return $stmt -> fetch();

		}else{

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla" );

			$stmt -> execute();

			return $stmt -> fetchAll();
		}

		$stmt -> close();

		$stmt = null;
	}

	/*=============================================
	EDITAR CURSO
	=============================================*/
	
	static public function mdlEditarCurso($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("UPDATE $tabla SET curso = :curso, categoria = :categoria, descripcion = :descripcion, precio = :precio, portada = :portada, modalidad = :modalidad, requisitos = :requisitos, objetivo = :objetivo WHERE id = :id");

		$stmt -> bindParam(":curso", $datos["curso"], PDO::PARAM_STR);
		$stmt -> bindParam(":categoria", $datos["categoria"], PDO::PARAM_STR);
		$stmt -> bindParam(":descripcion", $datos["descripcion"], PDO::PARAM_STR);
		$stmt -> bindParam(":precio", $datos["precio"], PDO::PARAM_STR);
		$stmt -> bindParam(":portada", $datos["portada"], PDO::PARAM_STR);
		$stmt -> bindParam(":modalidad", $datos["modalidad"], PDO::PARAM_STR);
		$stmt -> bindParam(":requisitos", $datos["requisitos"], PDO::PARAM_STR);
		$stmt -> bindParam(":objetivo", $datos["objetivo"], PDO::PARAM_STR);
		$stmt -> bindParam(":id", $datos["id"], PDO::PARAM_STR);

		if($stmt -> execute()){

			return "ok";
		
		}else{

			return "error";	

		}

		$stmt -> close();

		$stmt = null;
	}

	/*=============================================
	ELIMINAR CURSO
	=============================================*/
	
	static public function mdlEliminarCurso($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE id = :id");

		$stmt -> bindParam(":id", $datos, PDO::PARAM_INT);

		if($stmt -> execute()){

			return "ok";
		
		}else{

			return "error";	

		}

		$stmt -> close();

		$stmt = null;
	}
	
}