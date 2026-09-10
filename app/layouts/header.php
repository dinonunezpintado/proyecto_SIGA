<?php

if (session_status() === PHP_SESSION_NONE) {

    session_start();

}

if (!isset($_SESSION['usuario'])) {

    header('Location: /SIGA/index.php');

    exit;

}

$usuario = $_SESSION['usuario'];

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>SIGA</title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <link
        rel="stylesheet"
        href="/SIGA/assets/css/style.css"
    >
    
    <link
        rel="stylesheet"
        href="/SIGA/assets/css/dashboard.css"
    >
</head>


<body>

<div class="app-layout">