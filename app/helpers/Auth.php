<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function requireLogin()
{
    if (
        !isset($_SESSION['usuario']) ||
        empty($_SESSION['usuario']['id_usuario'])
    ) {

        
        session_unset();
        session_destroy();

        
        header("Location: /SIGA/index.php");
        exit;
    }
}


function getUsuarioSesion()
{
    return $_SESSION['usuario'] ?? null;
}

?>