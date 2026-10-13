<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: /SIGA/index.php');
    exit;
}

require_once __DIR__ . '/../../../config/database.php';

$nombreUsuario = $_SESSION['usuario']['nombre'];
$apellidoUsuario = $_SESSION['usuario']['apellido'];

$recordatorios = [];

$errorConsulta = false;

try {

    $database = new Database();
    $conexion = $database->conectar();

    $sqlRecordatorios = "
        SELECT
        a.id_actividad,
        a.nombre,
        a.responsable,
        a.telefono_responsable,
        r.fecha,
        r.hora_inicio,
        r.hora_fin,
        e.nombre AS espacio
    FROM actividades a
    INNER JOIN reservas r
        ON a.id_reserva = r.id_reserva
    INNER JOIN espacios e
        ON r.id_espacio = e.id_espacio
    WHERE r.fecha >= CURDATE()
    AND a.estado = 'Confirmada'
    AND r.estado = 'Confirmada'
    ORDER BY
        r.fecha ASC,
        r.hora_inicio ASC
";

    $stmt = $conexion->prepare($sqlRecordatorios);
    $stmt->execute();

    $recordatorios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorConsulta = true;
}

$totalRecordatorios = count($recordatorios);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>SIGA - Recordatorios</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <link rel="icon" type="image/png" href="/SIGA/assets/img/logo/favicon.png">

    <link
        rel="stylesheet"
        href="/SIGA/assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="/SIGA/assets/css/recordatorios.css?v=20261013"
    >

</head>

<body>

<div class="app-container">

    <aside class="sidebar">

        <div class="sidebar-logo">
            <a href="/SIGA/app/views/dashboard/index.php" class="sidebar-logo-link">
                <img src="/SIGA/assets/img/logo/logo_SIGA.png" alt="SIGA - Sistema Institucional de Gestión de Actividades" class="sidebar-logo-image sidebar-logo-image-full">
                <img src="/SIGA/assets/img/logo/logo_SIGA_mini.png" alt="SIGA" class="sidebar-logo-image sidebar-logo-image-mini">
            </a>
        </div>

        <nav class="sidebar-menu">
            <a href="/SIGA/app/views/dashboard/index.php" class="sidebar-link">
                <i class="bi bi-house-door"></i>
                <span>Inicio</span>
            </a>

            <a href="/SIGA/app/views/actividades/actividades.php" class="sidebar-link">
                <i class="bi bi-calendar-plus"></i>
                <span>Actividades y Reservas</span>
            </a>

            <a href="/SIGA/app/views/calendario/index.php" class="sidebar-link">
                <i class="bi bi-calendar-week"></i>
                <span>Calendario</span>
            </a>

            <a href="/SIGA/app/views/reportes/index.php" class="sidebar-link">
                <i class="bi bi-bar-chart"></i>
                <span>Reportes</span>
            </a>

            <a href="/SIGA/app/views/auditoria/index.php" class="sidebar-link">
                <i class="bi bi-clock-history"></i>
                <span>Historial de Acciones</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="/SIGA/app/helpers/logout.php" class="sidebar-logout">
                <i class="bi bi-box-arrow-right"></i>
                <span>Cerrar sesión</span>
            </a>
        </div>

    </aside>


    <main class="main-content">

        <header class="topbar">

            <div class="topbar-left">

                <button
                    type="button"
                    class="topbar-menu"
                    id="menuToggle"
                    aria-label="Mostrar u ocultar menú"
                    title="Mostrar u ocultar menú"
                >

                    <i class="bi bi-list"></i>

                </button>

            </div>


            <div class="topbar-right">

                <div class="user-info">

                    <i class="bi bi-person"></i>

                    <span>

                        <?= htmlspecialchars(
                            $nombreUsuario . ' ' . $apellidoUsuario
                        ) ?>

                    </span>

                </div>


                <a
                    href="/SIGA/app/controllers/AuthController.php?accion=logout"
                    class="logout-link"
                >

                    <i class="bi bi-box-arrow-right"></i>

                    Salir

                </a>

            </div>

        </header>


        <div class="reminders-page">

            <div class="reminders-header">

                <div class="reminders-title">

                    <div class="reminders-title-icon">

                        <i class="bi bi-bell"></i>

                    </div>

                    <div>

                        <h1>
                            Recordatorios
                        </h1>

                        <p>
                            Consulte las actividades confirmadas pendientes de realización y envíe el recordatorio por WhatsApp.
                        </p>

                    </div>

                </div>

            </div>


            <?php if ($errorConsulta): ?>

                <div class="alert alert-danger reminders-alert">

                    <i class="bi bi-exclamation-circle"></i>

                    No fue posible consultar los recordatorios. Intente nuevamente.

                </div>

            <?php endif; ?>


            <section class="reminders-list-card">

                <div class="reminders-list-card-header">

                    <div>

                        <h2 class="reminders-list-card-title">

                            Recordatorios

                        </h2>

                        <p class="reminders-list-card-subtitle">

                            Actividades confirmadas desde la fecha actual.

                        </p>

                    </div>

                    <span class="reminders-list-count">

                        <?= $totalRecordatorios ?>

                        <?= $totalRecordatorios === 1
                            ? 'recordatorio'
                            : 'recordatorios' ?>

                    </span>

                </div>


                <?php if (empty($recordatorios) && !$errorConsulta): ?>

                    <div class="reminders-empty">

                        <i class="bi bi-bell-slash"></i>

                        <h3>
                            No hay recordatorios pendientes
                        </h3>

                        <p>
                            No existen actividades confirmadas desde la fecha actual.
                        </p>

                    </div>

                <?php elseif (!$errorConsulta): ?>

                    <div class="reminders-table-wrapper">

                        <table class="reminders-table">

                            <thead>

                                <tr>

                                    <th>Fecha</th>

                                    <th>Hora</th>

                                    <th>Actividad</th>

                                    <th>Responsable</th>

                                    <th>Teléfono</th>

                                    <th>Espacio</th>

                                    <th>Acción</th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($recordatorios as $recordatorio): ?>

                                    <?php

                                    $telefonoWhatsApp = preg_replace(
                                        '/\D+/',
                                        '',
                                        $recordatorio['telefono_responsable']
                                    );

                                    if (
                                        strlen($telefonoWhatsApp) > 0 &&
                                        substr($telefonoWhatsApp, 0, 3) !== '598'
                                    ) {

                                        $telefonoWhatsApp =
                                            '598' .
                                            ltrim(
                                                $telefonoWhatsApp,
                                                '0'
                                            );
                                    }

                                    $mensajeWhatsApp =
                                        'Desde la Departamental de Salud de Rivera le hacemos acuerdo de su reserva para la actividad: ' .
                                        $recordatorio['nombre'] .
                                        ', a realizarse el: ' .
                                        date(
                                            'd/m/Y',
                                            strtotime(
                                                $recordatorio['fecha']
                                            )
                                        ) .
                                        ' a la hora: ' .
                                        substr(
                                            $recordatorio['hora_inicio'],
                                            0,
                                            5
                                        ) .
                                        '.';

                                    $enlaceWhatsApp =
                                        'https://wa.me/' .
                                        $telefonoWhatsApp .
                                        '?text=' .
                                        rawurlencode(
                                            $mensajeWhatsApp
                                        );

                                    ?>

                                    <tr>

                                        <td class="reminder-date">

                                            <?= date(
                                                'd/m/Y',
                                                strtotime(
                                                    $recordatorio['fecha']
                                                )
                                            ) ?>

                                        </td>


                                        <td class="reminder-time">

                                            <?= htmlspecialchars(
                                                substr(
                                                    $recordatorio['hora_inicio'],
                                                    0,
                                                    5
                                                )
                                            ) ?>

                                            -

                                            <?= htmlspecialchars(
                                                substr(
                                                    $recordatorio['hora_fin'],
                                                    0,
                                                    5
                                                )
                                            ) ?>

                                        </td>


                                        <td class="reminder-activity">

                                            <?= htmlspecialchars(
                                                $recordatorio['nombre']
                                            ) ?>

                                        </td>


                                        <td>

                                            <div class="reminder-contact">

                                                <i class="bi bi-person"></i>

                                                <?= htmlspecialchars(
                                                    $recordatorio['responsable']
                                                ) ?>

                                            </div>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $recordatorio[
                                                    'telefono_responsable'
                                                ]
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $recordatorio['espacio']
                                            ) ?>

                                        </td>


                                        <td class="reminder-action">

                                            <?php if (
                                                $telefonoWhatsApp !== ''
                                            ): ?>

                                                <a
                                                    href="<?= htmlspecialchars(
                                                        $enlaceWhatsApp
                                                    ) ?>"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="whatsapp-button"
                                                    title="Enviar recordatorio por WhatsApp"
                                                >

                                                    <i class="bi bi-whatsapp"></i>

                                                </a>

                                            <?php else: ?>

                                                <span
                                                    class="whatsapp-disabled"
                                                    title="No hay teléfono registrado"
                                                >

                                                    <i class="bi bi-telephone-x"></i>

                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </main>

</div>


<script>

const menuToggle = document.getElementById('menuToggle');

if (menuToggle) {

    menuToggle.addEventListener('click', function () {

        document.body.classList.toggle('sidebar-collapsed');

    });

}

</script>

</body>

</html>