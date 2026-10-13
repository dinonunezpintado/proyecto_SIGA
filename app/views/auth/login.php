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

    <h2>SIGA</h2>

    <h5>Sistema Institucional de Gestión de Actividades</h5>

    <br>

    <h3>Iniciar sesión</h3>

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


                <button
                    type="button"
                    class="password-toggle"
                    id="togglePassword"
                    aria-label="Mostrar contraseña"
                    title="Mostrar contraseña"
                >

                    <i
                        class="bi bi-eye"
                        id="togglePasswordIcon"
                    ></i>

                </button>

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


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<script>

const passwordInput = document.getElementById('contrasena');
const togglePassword = document.getElementById('togglePassword');
const togglePasswordIcon = document.getElementById('togglePasswordIcon');

togglePassword.addEventListener('click', function () {

    const passwordVisible =
        passwordInput.type === 'text';

    if (passwordVisible) {

        passwordInput.type = 'password';

        togglePasswordIcon.classList.remove('bi-eye-slash');

        togglePasswordIcon.classList.add('bi-eye');

        togglePassword.setAttribute(
            'aria-label',
            'Mostrar contraseña'
        );

        togglePassword.setAttribute(
            'title',
            'Mostrar contraseña'
        );

    } else {

        passwordInput.type = 'text';

        togglePasswordIcon.classList.remove('bi-eye');

        togglePasswordIcon.classList.add('bi-eye-slash');

        togglePassword.setAttribute(
            'aria-label',
            'Ocultar contraseña'
        );

        togglePassword.setAttribute(
            'title',
            'Ocultar contraseña'
        );

    }

});

</script>


<style>

.password-toggle {
    border: 1px solid #dee2e6;
    border-left: 0;
    background: #ffffff;
    color: #1f5fbf;
    width: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
}

.password-toggle:hover {
    background: #f5f8fc;
    color: #18345f;
}

.password-toggle:focus {
    outline: none;
    box-shadow: none;
}

.password-toggle i {
    font-size: 18px;
}

</style>


</body>

</html>