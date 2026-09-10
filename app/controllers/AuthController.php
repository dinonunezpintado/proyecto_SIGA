<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/../../config/database.php';


class AuthController
{


    public function login($email, $contrasena)
    {

        try {

           
            $database = new Database();

            $conexion = $database->conectar();


            $sql = "
                SELECT
                    u.id_usuario,
                    u.nombre,
                    u.apellido,
                    u.email,
                    u.rol
                FROM usuarios u
                WHERE u.email = :email
                AND u.contrasena = :contrasena
                AND u.activo = 1
                LIMIT 1
            ";


            $stmt = $conexion->prepare($sql);



            $stmt->execute([

                ':email' => trim($email),

                ':contrasena' => $contrasena

            ]);


            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);


            if ($usuario) {


                session_regenerate_id(true);

                $_SESSION['usuario'] = [

                    'id_usuario' => $usuario['id_usuario'],

                    'nombre' => $usuario['nombre'],

                    'apellido' => $usuario['apellido'],

                    'email' => $usuario['email'],

                    'rol' => $usuario['rol']

                ];


                return [

                    'success' => true,

                    'usuario' => $usuario

                ];

            }



            return [

                'success' => false,

                'error' => 'credenciales'

            ];


        } catch (PDOException $e) {


            return [

                'success' => false,

                'error' => 'database'

            ];

        }

    }


    public function logout()
    {


        $_SESSION = [];


        if (ini_get("session.use_cookies")) {

            $params = session_get_cookie_params();


            setcookie(

                session_name(),

                '',

                time() - 42000,

                $params["path"],

                $params["domain"],

                $params["secure"],

                $params["httponly"]

            );

        }


        session_destroy();


        header(
            'Location: /SIGA/index.php'
        );

        exit;

    }

}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    if (

        isset($_POST['accion'])

        &&

        $_POST['accion'] === 'login'

    ) {


        $email = $_POST['email'] ?? '';

        $contrasena = $_POST['contrasena'] ?? '';


        $authController = new AuthController();


        $resultado = $authController->login(

            $email,

            $contrasena

        );


        if ($resultado['success']) {


            header(

                'Location: /SIGA/app/views/dashboard/index.php'

            );

            exit;

        }


        header(

            'Location: /SIGA/index.php?error=credenciales'

        );

        exit;

    }

}



if (

    $_SERVER['REQUEST_METHOD'] === 'GET'

    &&

    isset($_GET['accion'])

    &&

    $_GET['accion'] === 'logout'

) {


    $authController = new AuthController();


    $authController->logout();

}