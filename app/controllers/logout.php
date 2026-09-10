<?php

session_start();

require_once 'AuthController.php';

$authController = new AuthController();

$authController->logout();

header('Location: ../../index.php');

exit;