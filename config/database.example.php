<?php

class Database {

    private $host = "localhost";
    private $dbname = "siga";
    private $username = "root";
    private $password = "AQUI VA LA CONTRASEÑA";

    public function conectar() {

        try {

            $conexion = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->dbname . ";charset=utf8mb4",
                $this->username,
                $this->password
            );

            
            $conexion->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            return $conexion;

        } catch (PDOException $e) {

            die(
                "Error de conexión a la base de datos: " .
                $e->getMessage()
            );
        }
    }
}