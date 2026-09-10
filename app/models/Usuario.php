<?php

require_once __DIR__ . '/../../config/database.php';

class Usuario {

    private $conexion;

    public function __construct() {

        $database = new Database();

        $this->conexion = $database->conectar();
    }


    public function buscarPorEmail($email) {

        $sql = "
            SELECT
                id_usuario,
                nombre,
                apellido,
                email,
                contrasena,
                rol,
                activo
            FROM usuarios
            WHERE email = :email
            LIMIT 1
        ";

        $stmt = $this->conexion->prepare($sql);

        $stmt->bindParam(
            ':email',
            $email,
            PDO::PARAM_STR
        );

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function obtenerPorId($id_usuario) {

        $sql = "
            SELECT
                id_usuario,
                nombre,
                apellido,
                email,
                rol,
                activo
            FROM usuarios
            WHERE id_usuario = :id_usuario
            LIMIT 1
        ";

        $stmt = $this->conexion->prepare($sql);

        $stmt->bindParam(
            ':id_usuario',
            $id_usuario,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function validarCredenciales($email, $contrasena) {

        $usuario = $this->buscarPorEmail($email);

        if (!$usuario) {
            return false;
        }


        if (!$usuario['activo']) {
            return false;
        }

        // por ahora es texto plano, luego sera hash!!!1
        if ($usuario['contrasena'] !== $contrasena) {
            return false;
        }

        unset($usuario['contrasena']);

        return $usuario;
    }
}