<?php

session_start();


define('BASE_URL', '/SIGA');

if (isset($_SESSION['usuario'])) {

    header('Location: ' . BASE_URL . '/app/views/dashboard/index.php');
    exit;

}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['accion']) && $_POST['accion'] === 'login') {

        require_once __DIR__ . '/app/controllers/AuthController.php';

        $authController = new AuthController();

        $resultado = $authController->login(
            $_POST['email'] ?? '',
            $_POST['contrasena'] ?? ''
        );


        if ($resultado['success']) {

            $_SESSION['usuario'] = $resultado['usuario'];

            header(
                'Location: ' .
                BASE_URL .
                '/app/views/dashboard/index.php'
            );

            exit;

        } else {

            header(
                'Location: ' .
                BASE_URL .
                '/index.php?error=credenciales'
            );

            exit;

        }

    }

}


require_once __DIR__ . '/app/views/auth/login.php';