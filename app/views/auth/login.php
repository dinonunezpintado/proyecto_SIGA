<?php

$error = $_GET['error'] ?? '';

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>SIGA - Iniciar sesión</title>


    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <link
        rel="stylesheet"
        href="/SIGA/assets/css/login.css"
    >

</head>


<body>


<div class="login-container">

<div class="logo-area">

    <img 
        src="/SIGA/assets/img/logo/logo_dds.png"
        alt="Ministerio de Salud Pública - Dirección Departamental de Salud de Rivera"
        class="logo-msp"
    >

    <h2>Iniciar sesión</h2>

    <p class="text-muted mb-0">
        Ingresa tus credenciales para continuar
    </p>

</div>


    <?php if ($error === 'credenciales'): ?>

        <div class="alert alert-danger py-2 mb-3">

            Usuario o contraseña incorrectos.

        </div>

    <?php endif; ?>


    <form
    method="POST"
    action="/SIGA/index.php"
    >

        <input
            type="hidden"
            name="accion"
            value="login"
        >

        <div class="mb-3">


            <label
                for="email"
                class="form-label"
            >

                Usuario

            </label>


            <div class="input-group">


                <span class="input-group-text">

                    <i class="bi bi-person"></i>

                </span>


                <input
                    type="email"
                    id="email"
                    class="form-control"
                    name="email"
                    placeholder="tu_usuario@msp.gub.uy"
                    autocomplete="email"
                    required
                >


            </div>


        </div>

        <div class="mb-4">


            <label
                for="contrasena"
                class="form-label"
            >

                Contraseña

            </label>


            <div class="input-group">


                <span class="input-group-text">

                    <i class="bi bi-lock"></i>

                </span>


                <input
                    type="password"
                    id="contrasena"
                    class="form-control"
                    name="contrasena"
                    placeholder="Ingrese su contraseña"
                    autocomplete="current-password"
                    required
                >


            </div>


        </div>

        <button
            type="submit"
            class="btn btn-login"
        >

            INICIAR SESIÓN

        </button>


    </form>

    <div class="info-box">


        <div class="d-flex gap-2">


            <i class="bi bi-info-circle"></i>


            <div>

                Acceso exclusivo para usuarios autorizados del MSP.

                <br>

                No disponible registro de usuarios.

            </div>


        </div>


    </div>


</div>


<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>