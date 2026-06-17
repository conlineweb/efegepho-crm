<?php

require_once "conexion.php";

class ModeloTemas {

    /*=============================================
    CREAR TEMA
    =============================================*/
    
    static public function mdlCrearTema($tabla, $datos) {

        $stmt = Conexion::conectar()->prepare("INSERT INTO $tabla (id_curso, titulo, contenido, preguntas) VALUES (:id_curso, :titulo, :contenido, :preguntas)");

        $stmt->bindParam(":id_curso", $datos["id_curso"], PDO::PARAM_INT);
        $stmt->bindParam(":titulo", $datos["titulo"], PDO::PARAM_STR);
        $stmt->bindParam(":contenido", $datos["contenido"], PDO::PARAM_STR);
        $stmt->bindParam(":preguntas", $datos["preguntas"], PDO::PARAM_STR);

        if ($stmt->execute()) {
            return "ok";
        } else {
            return "error";
        }

        $stmt->close();
        $stmt = null;
    }

    /*=============================================
    MOSTRAR TEMAS
    =============================================*/
    
    static public function mdlMostrarTemas($tabla, $item, $valor) {

        if ($item != null) {
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");
            $stmt->bindParam(":".$item, $valor, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll();
        } else {
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla");
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt->close();
        $stmt = null;
    }

    /*=============================================
    EDITAR TEMA
    =============================================*/
    
    static public function mdlEditarTema($tabla, $datos) {

        $stmt = Conexion::conectar()->prepare("UPDATE $tabla SET id_curso = :id_curso, titulo = :titulo, contenido = :contenido, preguntas = :preguntas WHERE id = :id");

        $stmt->bindParam(":id_curso", $datos["id_curso"], PDO::PARAM_INT);
        $stmt->bindParam(":titulo", $datos["titulo"], PDO::PARAM_STR);
        $stmt->bindParam(":contenido", $datos["contenido"], PDO::PARAM_STR);
        $stmt->bindParam(":preguntas", $datos["preguntas"], PDO::PARAM_STR);
        $stmt->bindParam(":id", $datos["id"], PDO::PARAM_INT);

        if ($stmt->execute()) {
            return "ok";
        } else {
            return "error";
        }

        $stmt->close();
        $stmt = null;
    }

    /*=============================================
    ELIMINAR TEMA
    =============================================*/
    
    static public function mdlEliminarTema($tabla, $datos) {

        $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE id = :id");

        $stmt->bindParam(":id", $datos, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return "ok";
        } else {
            return "error";
        }

        $stmt->close();
        $stmt = null;
    }
}
