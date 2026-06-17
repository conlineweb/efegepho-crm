<?php

class Conexion{

	static public function conectar(){

		try {
    		$link = new PDO("mysql:host=cpanel.cursonkenarm.com;dbname=cursonkenarm_admincursos",
			            "cursonkenarm_admincursos",
			            "eYhCJzV4y-XL");
			} catch (PDOException $e) {
				
		    echo 'error';

		    return;
		}
		return $link;
	}
}