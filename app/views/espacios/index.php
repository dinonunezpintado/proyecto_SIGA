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

$fechaSeleccionada = $_GET['fecha'] ?? date('Y-m-d');
$espacioSeleccionado = $_GET['espacio'] ?? '';

$espacios = [];
$reservas = [];

$errorConsulta = false;

try {

    $database = new Database();
    $conexion = $database->conectar();

    $sqlEspacios = "
        SELECT
            id_espacio,
            nombre,
            capacidad
        FROM espacios
        WHERE activo = 1
        ORDER BY nombre ASC
    ";

    $stmtEspacios = $conexion->prepare($sqlEspacios);
    $stmtEspacios->execute();

    $espacios = $stmtEspacios->fetchAll(PDO::FETCH_ASSOC);

    $sqlReservas = "
        SELECT
            r.id_reserva,
            r.id_espacio,
            r.fecha,
            r.hora_inicio,
            r.hora_fin,
            r.estado,
            a.nombre AS actividad,
            a.responsable,
            e.nombre AS espacio
        FROM reservas r
        INNER JOIN actividades a
            ON a.id_reserva = r.id_reserva
        INNER JOIN espacios e
            ON e.id_espacio = r.id_espacio
        WHERE r.fecha = :fecha
        AND r.estado = 'Confirmada'
        AND a.estado = 'Confirmada'
    ";

    $parametros = [
        ':fecha' => $fechaSeleccionada
    ];

    if ($espacioSeleccionado !== '') {

        $sqlReservas .= "
            AND r.id_espacio = :id_espacio
        ";

        $parametros[':id_espacio'] = $espacioSeleccionado;
    }

    $sqlReservas .= "
        ORDER BY
            e.nombre ASC,
            r.hora_inicio ASC
    ";

    $stmtReservas = $conexion->prepare($sqlReservas);
    $stmtReservas->execute($parametros);

    $reservas = $stmtReservas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorConsulta = true;
}

$reservasPorEspacio = [];

foreach ($reservas as $reserva) {

    $idEspacio = $reserva['id_espacio'];

    if (!isset($reservasPorEspacio[$idEspacio])) {
        $reservasPorEspacio[$idEspacio] = [];
    }

    $reservasPorEspacio[$idEspacio][] = $reserva;
}

$espaciosMostrar = $espacios;

if ($espacioSeleccionado !== '') {

    $espaciosMostrar = array_filter(
        $espacios,
        function ($espacio) use ($espacioSeleccionado) {
            return (string) $espacio['id_espacio'] === (string) $espacioSeleccionado;
        }
    );
}

$fechaFormateada = date(
    'd/m/Y',
    strtotime($fechaSeleccionada)
);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>SIGA - Disponibilidad de Espacios</title>
    <link rel="icon" type="image/png" href="/SIGA/assets/img/logo/favicon.png">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="/SIGA/assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="/SIGA/assets/css/espacios.css?v=20261013"
    >

</head>

<body>

<div class="app-container">

    <aside class="sidebar">
        <div class="sidebar-logo">
            <a href="/SIGA/app/views/dashboard/index.php" class="sidebar-logo-link" aria-label="SIGA - Inicio">
                <img src="/SIGA/assets/img/logo/logo_SIGA.png" alt="SIGA" class="sidebar-logo-image sidebar-logo-image-full">
                <img src="/SIGA/assets/img/logo/logo_SIGA_mini.png" alt="SIGA" class="sidebar-logo-image sidebar-logo-image-mini">
            </a>
        </div>

        <nav class="sidebar-menu">
            <a href="/SIGA/app/views/dashboard/index.php" class="sidebar-link">
                <i class="bi bi-house"></i>
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


        <div class="spaces-page">

            <div class="spaces-header">

                <div class="spaces-title">

                    <div class="spaces-title-icon">

                        <i class="bi bi-building-check"></i>

                    </div>

                    <div>

                        <h1>
                            Disponibilidad de espacios
                        </h1>

                        <p>
                            Consulte la disponibilidad y las reservas de los espacios institucionales.
                        </p>

                    </div>

                </div>

            </div>


            <?php if ($errorConsulta): ?>

                <div class="alert alert-danger spaces-alert">

                    <i class="bi bi-exclamation-circle"></i>

                    No fue posible consultar la disponibilidad de los espacios. Intente nuevamente.

                </div>

            <?php endif; ?>


            <section class="spaces-filter-card">

                <form
                    method="GET"
                    action="/SIGA/app/views/espacios/index.php"
                    class="spaces-filter-form"
                >

                    <div class="spaces-filter-field">

                        <label for="fecha">
                            Fecha
                        </label>

                        <input
                            type="date"
                            id="fecha"
                            name="fecha"
                            value="<?= htmlspecialchars($fechaSeleccionada) ?>"
                            required
                        >

                    </div>


                    <div class="spaces-filter-field">

                        <label for="espacio">
                            Espacio
                        </label>

                        <select
                            id="espacio"
                            name="espacio"
                        >

                            <option value="">
                                Todos los espacios
                            </option>

                            <?php foreach ($espacios as $espacio): ?>

                                <option
                                    value="<?= htmlspecialchars($espacio['id_espacio']) ?>"
                                    <?= (string) $espacio['id_espacio'] === (string) $espacioSeleccionado ? 'selected' : '' ?>
                                >

                                    <?= htmlspecialchars($espacio['nombre']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="spaces-filter-actions">

                        <button
                            type="submit"
                            class="btn-spaces-filter"
                        >

                            <i class="bi bi-search"></i>

                            Consultar disponibilidad

                        </button>

                    </div>

                </form>

            </section>


            <section class="spaces-list-card">

                <div class="spaces-list-card-header">

                    <div>

                        <h2 class="spaces-list-card-title">

                            Disponibilidad para
                            <?= htmlspecialchars($fechaFormateada) ?>

                        </h2>

                        <p class="spaces-list-card-subtitle">

                            Reservas confirmadas para la fecha seleccionada.

                        </p>

                    </div>

                    <span class="spaces-list-count">

                        <?= count($espaciosMostrar) ?>

                        <?= count($espaciosMostrar) === 1 ? 'espacio' : 'espacios' ?>

                    </span>

                </div>


                <?php if (empty($espaciosMostrar) && !$errorConsulta): ?>

                    <div class="spaces-empty">

                        <i class="bi bi-building-x"></i>

                        <h3>
                            No hay espacios disponibles para consultar
                        </h3>

                        <p>
                            No se encontraron espacios activos para los filtros seleccionados.
                        </p>

                    </div>

                <?php elseif (!$errorConsulta): ?>

                    <div class="spaces-table-wrapper">

                        <table class="spaces-table">

                            <thead>

                                <tr>

                                    <th>Espacio</th>

                                    <th>Capacidad</th>

                                    <th>Horario</th>

                                    <th>Actividad</th>

                                    <th>Responsable</th>

                                    <th>Estado</th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($espaciosMostrar as $espacio): ?>

                                    <?php

                                    $reservasEspacio =
                                        $reservasPorEspacio[$espacio['id_espacio']]
                                        ?? [];

                                    ?>

                                    <?php if (empty($reservasEspacio)): ?>

                                        <tr>

                                            <td class="space-table-name">

                                                <i class="bi bi-building"></i>

                                                <?= htmlspecialchars(
                                                    $espacio['nombre']
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= htmlspecialchars(
                                                    $espacio['capacidad']
                                                ) ?>

                                                personas

                                            </td>

                                            <td>
                                                —
                                            </td>

                                            <td>
                                                —
                                            </td>

                                            <td>
                                                —
                                            </td>

                                            <td>

                                                <span class="space-status-badge disponible">

                                                    <span class="space-status-dot"></span>

                                                    Disponible

                                                </span>

                                            </td>

                                        </tr>

                                    <?php else: ?>

                                        <?php foreach ($reservasEspacio as $reserva): ?>

                                            <tr>

                                                <td class="space-table-name">

                                                    <i class="bi bi-building"></i>

                                                    <?= htmlspecialchars(
                                                        $reserva['espacio']
                                                    ) ?>

                                                </td>

                                                <td>

                                                    <?= htmlspecialchars(
                                                        $espacio['capacidad']
                                                    ) ?>

                                                    personas

                                                </td>

                                                <td class="space-table-time">

                                                    <?= htmlspecialchars(
                                                        substr(
                                                            $reserva['hora_inicio'],
                                                            0,
                                                            5
                                                        )
                                                    ) ?>

                                                    -

                                                    <?= htmlspecialchars(
                                                        substr(
                                                            $reserva['hora_fin'],
                                                            0,
                                                            5
                                                        )
                                                    ) ?>

                                                </td>

                                                <td class="space-table-activity">

                                                    <?= htmlspecialchars(
                                                        $reserva['actividad']
                                                    ) ?>

                                                </td>

                                                <td>

                                                    <?= htmlspecialchars(
                                                        $reserva['responsable']
                                                    ) ?>

                                                </td>

                                                <td>

                                                    <span class="space-status-badge ocupado">

                                                        <span class="space-status-dot"></span>

                                                        Ocupado

                                                    </span>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

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