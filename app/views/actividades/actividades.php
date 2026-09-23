<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (
    !isset($_SESSION['usuario']) ||
    !is_array($_SESSION['usuario']) ||
    empty($_SESSION['usuario']['id_usuario'])
) {
    header('Location: /SIGA/index.php');
    exit;
}

require_once __DIR__ . '/../../../config/database.php';

$database = new Database();
$conexion = $database->conectar();

$actividades = [];

try {

    $stmt = $conexion->prepare("
        SELECT
            a.id_actividad,
            a.nombre AS actividad,
            a.responsable,
            a.telefono_responsable,
            a.estado AS estado_actividad,
            ta.nombre AS tipo_actividad,
            r.id_reserva,
            r.fecha,
            r.hora_inicio,
            r.hora_fin,
            r.estado AS estado_reserva,
            e.nombre AS espacio
        FROM actividades a
        INNER JOIN reservas r
            ON r.id_reserva = a.id_reserva
        INNER JOIN tipos_actividad ta
            ON ta.id_tipo_actividad = a.id_tipo_actividad
        INNER JOIN espacios e
            ON e.id_espacio = r.id_espacio
        ORDER BY
            r.fecha ASC,
            r.hora_inicio ASC,
            a.id_actividad ASC
    ");

    $stmt->execute();

    $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorConsulta = true;
}

$totalActividades = count($actividades);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Actividades registradas - SIGA</title>

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
        href="/SIGA/assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="/SIGA/assets/css/actividades.css"
    >
    
    <style>
        .sidebar-logo-link {
            color: inherit;
            text-decoration: none;
            display: block;
        }

        .sidebar-logo-link h1 {
            color: inherit;
        }
    </style>
</head>

<body>

<div class="siga-layout">

    <!--menu lateral-->

    <aside class="sidebar">

        <div class="sidebar-logo">

            <a href="/SIGA/app/views/dashboard/index.php" class="sidebar-logo-link">
                <h1>
                    <i class="bi bi-calendar3"></i>
                    SIGA
                </h1>
            </a>

            <p>
                Sistema Institucional<br>
                de Gestión de Actividades
            </p>

        </div>

        <nav class="sidebar-menu">

            <a
                href="/SIGA/app/views/dashboard/index.php"
                class="sidebar-link"
            >
                <i class="bi bi-house-door"></i>
                <span>Inicio</span>
            </a>

            <div class="sidebar-section">

                <a
                    href="#"
                    class="sidebar-link active"
                >
                    <i class="bi bi-calendar-event"></i>
                    <span>Actividades y Reservas</span>
                    <i class="bi bi-chevron-down sidebar-arrow"></i>
                </a>

                <div class="sidebar-submenu">

                    <a
                        href="/SIGA/app/views/actividades/nueva.php"
                        class="sidebar-sublink"
                    >
                        <span class="submenu-dot"></span>
                        Nueva actividad
                    </a>

                    <a
                        href="/SIGA/app/views/actividades/actividades.php"
                        class="sidebar-sublink active-sub"
                    >
                        <span class="submenu-dot"></span>
                        Ver actividades
                    </a>

                </div>

            </div>

            <a href="../calendario/index.php">
                <i class="bi bi-calendar-week"></i>
                <span>Calendario</span>
            </a>

            <a href="../reportes/index.php">
                <i class="bi bi-bar-chart"></i>
                <span>Reportes</span>
            </a>

            <a href="../auditoria/index.php">
                <i class="bi bi-clock-history"></i>
                <span>Historial de Acciones</span>
            </a>

        </nav>

        <div class="sidebar-footer">

            <a
                href="/SIGA/app/helpers/logout.php"
                class="sidebar-logout"
            >
                <i class="bi bi-box-arrow-right"></i>
                <span>Cerrar sesión</span>
            </a>

        </div>

    </aside>


    <!--contenido principal-->

    <main class="main-content">

        <!--barra superior-->

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

                <div>
                    <i class="bi bi-bell"></i>
                </div>

                <div class="user-info">

                    <i class="bi bi-person"></i>

                    <span>
                        <?= htmlspecialchars(
                            $_SESSION['usuario']['nombre'] . ' ' .
                            $_SESSION['usuario']['apellido']
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


        <!--contenido-->

        <div class="activities-list-page">

            <div class="activities-list-header">

                <div class="activities-list-title">

                    <div class="activities-list-title-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>

                    <div>

                        <h1>
                            Actividades registradas
                        </h1>

                        <p>
                            Consulte todas las actividades y reservas registradas en SIGA.
                        </p>

                    </div>

                </div>

                <div class="activities-list-actions">

                    <a
                        href="/SIGA/app/views/actividades/nueva.php"
                        class="btn-new-activity"
                    >
                        <i class="bi bi-plus-lg"></i>
                        Nueva actividad
                    </a>

                </div>

            </div>


            <?php if (isset($errorConsulta)): ?>

                <div class="alert alert-danger activities-alert">

                    <i class="bi bi-exclamation-circle"></i>

                    No fue posible consultar las actividades. Intente nuevamente.

                </div>

            <?php endif; ?>


            <section class="activities-list-card">

                <div class="activities-list-card-header">

                    <h2 class="activities-list-card-title">
                        Actividades registradas
                    </h2>

                    <span class="activities-list-count">
                        <?= $totalActividades ?>
                        <?= $totalActividades === 1 ? 'actividad' : 'actividades' ?>
                    </span>

                </div>


                <?php if (empty($actividades) && !isset($errorConsulta)): ?>

                    <div class="activities-empty">

                        <i class="bi bi-calendar-x"></i>

                        <h3>
                            No hay actividades registradas
                        </h3>

                        <p>
                            Las actividades que registre aparecerán en este listado.
                        </p>

                    </div>

                <?php elseif (!empty($actividades)): ?>

                    <div class="activities-table-wrapper">

                        <table class="activities-table">

                            <thead>

                                <tr>
                                    <th>Fecha</th>
                                    <th>Horario</th>
                                    <th>Actividad</th>
                                    <th>Tipo</th>
                                    <th>Espacio</th>
                                    <th>Responsable</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($actividades as $actividad): ?>

                                    <?php
                                    $estado = $actividad['estado_actividad'];
                                    $claseEstado =
                                        strtolower($estado) === 'cancelada'
                                            ? 'cancelada'
                                            : 'confirmada';
                                    ?>

                                    <tr>

                                        <td class="activity-table-date">

                                            <?= htmlspecialchars(
                                                date(
                                                    'd/m/Y',
                                                    strtotime($actividad['fecha'])
                                                )
                                            ) ?>

                                        </td>

                                        <td class="activity-table-time">

                                            <?= htmlspecialchars(
                                                substr(
                                                    $actividad['hora_inicio'],
                                                    0,
                                                    5
                                                )
                                            ) ?>

                                            -

                                            <?= htmlspecialchars(
                                                substr(
                                                    $actividad['hora_fin'],
                                                    0,
                                                    5
                                                )
                                            ) ?>

                                        </td>

                                        <td class="activity-table-name">

                                            <?= htmlspecialchars(
                                                $actividad['actividad']
                                            ) ?>

                                        </td>

                                        <td>

                                            <span class="activity-type-badge">

                                                <?= htmlspecialchars(
                                                    $actividad['tipo_actividad']
                                                ) ?>

                                            </span>

                                        </td>

                                        <td class="activity-table-space">

                                            <?= htmlspecialchars(
                                                $actividad['espacio']
                                            ) ?>

                                        </td>

                                        <td class="activity-table-responsible">

                                            <?= htmlspecialchars(
                                                $actividad['responsable']
                                            ) ?>

                                        </td>

                                        <td>

                                            <span
                                                class="activity-status-badge <?= $claseEstado ?>"
                                            >

                                                <span
                                                    class="activity-status-dot"
                                                ></span>

                                                <?= htmlspecialchars($estado) ?>

                                            </span>

                                        </td>

                                        <td>

                                            <a
                                                href="/SIGA/app/views/actividades/detalle.php?id=<?= (int) $actividad['id_actividad'] ?>"
                                                class="activity-action-btn"
                                                title="Ver detalle"
                                            >
                                                <i class="bi bi-eye"></i>
                                                Ver
                                            </a>

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
