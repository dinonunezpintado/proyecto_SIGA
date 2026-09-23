<?php

session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
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

date_default_timezone_set('America/Montevideo');

require_once __DIR__ . '/../../../config/database.php';

$database = new Database();
$conexion = $database->conectar();

$usuario = $_SESSION['usuario'];

$nombreUsuario = $usuario['nombre'] ?? '';
$apellidoUsuario = $usuario['apellido'] ?? '';
$rolUsuario = $usuario['rol'] ?? 'Administrador';

$fechaActual = date('Y-m-d');

$actividadesHoy = 0;
$actividadesSemana = 0;
$actividadesMes = 0;
$equiposHoy = 0;

$agendaHoy = [];
$proximasActividades = [];
$proximosRecordatorios = [];
$espacios = [];

try {

    $sqlActividadesHoy = "
        SELECT COUNT(*) AS total
        FROM actividades a
        INNER JOIN reservas r
            ON a.id_reserva = r.id_reserva
        WHERE r.fecha = :fecha
        AND a.estado = 'Confirmada'
        AND r.estado = 'Confirmada'
    ";

    $stmt = $conexion->prepare($sqlActividadesHoy);
    $stmt->execute([
        ':fecha' => $fechaActual
    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $actividadesHoy = $resultado['total'] ?? 0;


    $sqlActividadesSemana = "
        SELECT COUNT(*) AS total
        FROM actividades a
        INNER JOIN reservas r
            ON a.id_reserva = r.id_reserva
        WHERE YEARWEEK(r.fecha, 1) = YEARWEEK(CURDATE(), 1)
        AND a.estado = 'Confirmada'
        AND r.estado = 'Confirmada'
    ";

    $stmt = $conexion->prepare($sqlActividadesSemana);
    $stmt->execute();

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $actividadesSemana = $resultado['total'] ?? 0;


    $sqlActividadesMes = "
        SELECT COUNT(*) AS total
        FROM actividades a
        INNER JOIN reservas r
            ON a.id_reserva = r.id_reserva
        WHERE MONTH(r.fecha) = MONTH(CURDATE())
        AND YEAR(r.fecha) = YEAR(CURDATE())
        AND a.estado = 'Confirmada'
        AND r.estado = 'Confirmada'
    ";

    $stmt = $conexion->prepare($sqlActividadesMes);
    $stmt->execute();

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $actividadesMes = $resultado['total'] ?? 0;


    $sqlEquiposHoy = "
        SELECT COALESCE(SUM(re.cantidad), 0) AS total
        FROM reserva_equipamiento re
        INNER JOIN reservas r
            ON re.id_reserva = r.id_reserva
        WHERE r.fecha = :fecha
        AND r.estado = 'Confirmada'
    ";

    $stmt = $conexion->prepare($sqlEquiposHoy);
    $stmt->execute([
        ':fecha' => $fechaActual
    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $equiposHoy = $resultado['total'] ?? 0;


    $sqlAgendaHoy = "
        SELECT
            a.id_actividad,
            a.nombre,
            a.estado AS estado_actividad,
            r.fecha,
            r.hora_inicio,
            r.hora_fin,
            r.estado AS estado_reserva,
            e.nombre AS espacio
        FROM actividades a
        INNER JOIN reservas r
            ON a.id_reserva = r.id_reserva
        INNER JOIN espacios e
            ON r.id_espacio = e.id_espacio
        WHERE r.fecha = :fecha
        AND a.estado = 'Confirmada'
        AND r.estado = 'Confirmada'
        ORDER BY r.hora_inicio ASC
    ";

    $stmt = $conexion->prepare($sqlAgendaHoy);
    $stmt->execute([
        ':fecha' => $fechaActual
    ]);

    $agendaHoy = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $sqlProximasActividades = "
        SELECT
            a.id_actividad,
            a.nombre,
            a.estado AS estado_actividad,
            r.fecha,
            r.hora_inicio,
            r.hora_fin,
            r.estado AS estado_reserva,
            e.nombre AS espacio,
            a.responsable
        FROM actividades a
        INNER JOIN reservas r
            ON a.id_reserva = r.id_reserva
        INNER JOIN espacios e
            ON r.id_espacio = e.id_espacio
        WHERE r.fecha >= CURDATE()
        AND a.estado = 'Confirmada'
        AND r.estado = 'Confirmada'
        ORDER BY r.fecha ASC, r.hora_inicio ASC
        LIMIT 5
    ";

    $stmt = $conexion->prepare($sqlProximasActividades);
    $stmt->execute();

    $proximasActividades = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $sqlProximosRecordatorios = "
        SELECT
            a.id_actividad,
            a.nombre,
            a.responsable,
            a.telefono_responsable,
            r.fecha,
            r.hora_inicio
        FROM actividades a
        INNER JOIN reservas r
            ON a.id_reserva = r.id_reserva
        WHERE r.fecha >= CURDATE()
        AND a.estado = 'Confirmada'
        AND r.estado = 'Confirmada'
        ORDER BY r.fecha ASC, r.hora_inicio ASC
        LIMIT 5
    ";

    $stmt = $conexion->prepare($sqlProximosRecordatorios);
    $stmt->execute();

    $proximosRecordatorios = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $sqlEspacios = "
        SELECT
            e.id_espacio,
            e.nombre,
            e.capacidad,
            r.id_reserva,
            r.hora_inicio,
            r.hora_fin
        FROM espacios e
        LEFT JOIN reservas r
            ON e.id_espacio = r.id_espacio
            AND r.fecha = CURDATE()
            AND r.estado = 'Confirmada'
            AND r.hora_inicio <= CURTIME()
            AND r.hora_fin > CURTIME()
        WHERE e.activo = 1
        ORDER BY e.id_espacio ASC
    ";

    $stmt = $conexion->prepare($sqlEspacios);
    $stmt->execute();

    $espacios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $actividadesHoy = 0;
    $actividadesSemana = 0;
    $actividadesMes = 0;
    $equiposHoy = 0;
    $agendaHoy = [];
    $proximasActividades = [];
    $proximosRecordatorios = [];
    $espacios = [];
}

$meses = [
    1 => 'enero',
    2 => 'febrero',
    3 => 'marzo',
    4 => 'abril',
    5 => 'mayo',
    6 => 'junio',
    7 => 'julio',
    8 => 'agosto',
    9 => 'septiembre',
    10 => 'octubre',
    11 => 'noviembre',
    12 => 'diciembre'
];

$diasSemana = [
    'Sunday' => 'Domingo',
    'Monday' => 'Lunes',
    'Tuesday' => 'Martes',
    'Wednesday' => 'Miércoles',
    'Thursday' => 'Jueves',
    'Friday' => 'Viernes',
    'Saturday' => 'Sábado'
];

$timestampActual = strtotime($fechaActual);

$nombreDia = $diasSemana[date('l', $timestampActual)];
$diaNumero = date('d', $timestampActual);
$mesNumero = date('n', $timestampActual);
$anio = date('Y', $timestampActual);

$fechaTexto =
    $nombreDia .
    ', ' .
    $diaNumero .
    ' de ' .
    $meses[$mesNumero] .
    ' de ' .
    $anio;

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>SIGA - Inicio</title>

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
        href="../../../assets/css/dashboard.css"
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

<div class="app-container">

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
                href="index.php"
                class="active"
            >
                <i class="bi bi-house"></i>
                <span>Inicio</span>
            </a>

            <a href="/SIGA/app/views/actividades/actividades.php">
                <i class="bi bi-calendar-plus"></i>
                <span>Actividades y Reservas</span>
            </a>

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
                    <span>
                        <?= htmlspecialchars(
                            $nombreUsuario .
                            ' ' .
                            $apellidoUsuario
                        ) ?>
                    </span>
                </div>

                <a
                    href="/SIGA/app/helpers/logout.php"
                    class="logout-link"
                >
                    <i class="bi bi-box-arrow-right"></i>
                    Salir
                </a>

            </div>

        </header>

        <div class="dashboard-content">

            <div class="dashboard-header">

                <div>
                    <h2 class="welcome-title">
                        Hola
                        <?= htmlspecialchars(
                            $nombreUsuario .
                            ' ' .
                            $apellidoUsuario
                        ) ?> 👋
                    </h2>

                    <div class="welcome-role">
                        <?= htmlspecialchars($rolUsuario) ?>
                    </div>
                </div>

                <div class="dashboard-date">
                    <?= htmlspecialchars($fechaTexto) ?>
                    <span>|</span>
                    <?= date('H:i') ?>
                </div>

            </div>


            <section class="row g-4 mb-4">

                <div class="col-xl-3 col-md-6">

                    <div class="stat-card">

                        <div class="stat-icon icon-blue">
                            <i class="bi bi-calendar-day"></i>
                        </div>

                        <div class="text-end">

                            <div class="stat-title">
                                Actividades hoy
                            </div>

                            <div class="stat-number">
                                <?= $actividadesHoy ?>
                            </div>

                            <a
                                href="../calendario/index.php"
                                class="stat-link"
                            >
                                Ver calendario
                                <i class="bi bi-arrow-right"></i>
                            </a>

                        </div>

                    </div>

                </div>


                <div class="col-xl-3 col-md-6">

                    <div class="stat-card">

                        <div class="stat-icon icon-green">
                            <i class="bi bi-calendar-check"></i>
                        </div>

                        <div class="text-end">

                            <div class="stat-title">
                                Actividades<br>
                                esta semana
                            </div>

                            <div class="stat-number">
                                <?= $actividadesSemana ?>
                            </div>

                            <a
                                href="../calendario/index.php"
                                class="stat-link"
                            >
                                Ver calendario
                                <i class="bi bi-arrow-right"></i>
                            </a>

                        </div>

                    </div>

                </div>


                <div class="col-xl-3 col-md-6">

                    <div class="stat-card">

                        <div class="stat-icon icon-purple">
                            <i class="bi bi-people"></i>
                        </div>

                        <div class="text-end">

                            <div class="stat-title">
                                Actividades<br>
                                este mes
                            </div>

                            <div class="stat-number">
                                <?= $actividadesMes ?>
                            </div>

                            <a
                                href="../reportes/index.php"
                                class="stat-link"
                            >
                                Ver reportes
                                <i class="bi bi-arrow-right"></i>
                            </a>

                        </div>

                    </div>

                </div>


                <div class="col-xl-3 col-md-6">

                    <div class="stat-card">

                        <div class="stat-icon icon-orange">
                            <i class="bi bi-display"></i>
                        </div>

                        <div class="text-end">

                            <div class="stat-title">
                                Equipos<br>
                                solicitados hoy
                            </div>

                            <div class="stat-number">
                                <?= $equiposHoy ?>
                            </div>

                            <a
                                href="../equipamiento/index.php"
                                class="stat-link"
                            >
                                Ver equipamiento
                                <i class="bi bi-arrow-right"></i>
                            </a>

                        </div>

                    </div>

                </div>

            </section>


            <section class="row g-4 mb-4">

                <div class="col-xl-7">

                    <div class="panel">

                        <div class="panel-header">
                            Agenda de hoy
                        </div>

                        <div class="panel-body p-0">

                            <div class="agenda-date">
                                <i class="bi bi-calendar3 me-2"></i>
                                <?= htmlspecialchars($fechaTexto) ?>
                            </div>

                            <?php if (!empty($agendaHoy)): ?>

                                <div class="px-4">

                                    <?php foreach ($agendaHoy as $actividad): ?>

                                        <div class="agenda-item">

                                            <div class="agenda-time">

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

                                            </div>

                                            <div class="agenda-details">

                                                <div class="agenda-name">
                                                    <?= htmlspecialchars(
                                                        $actividad['nombre']
                                                    ) ?>
                                                </div>

                                                <div class="agenda-space">
                                                    <?= htmlspecialchars(
                                                        $actividad['espacio']
                                                    ) ?>
                                                </div>

                                            </div>

                                            <div class="agenda-status">

                                                <span class="badge text-bg-success">
                                                    Confirmada
                                                </span>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php else: ?>

                                <div class="empty-state">
                                    <i class="bi bi-calendar-x"></i>
                                    No hay actividades programadas para hoy.
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <div class="col-xl-5">

                    <div class="panel">

                        <div class="panel-header panel-header-with-link">

                            <div>
                                <i class="bi bi-bell me-2"></i>
                                Próximos Recordatorios
                            </div>

                            <a href="../recordatorios/index.php">
                                Ver todos
                                <i class="bi bi-arrow-right"></i>
                            </a>

                        </div>

                        <div class="panel-body p-0">

                            <?php if (!empty($proximosRecordatorios)): ?>

                                <div class="reminder-table-wrapper">

                                    <table class="reminder-table">

                                        <thead>

                                            <tr>
                                                <th>Contacto</th>
                                                <th>Actividad</th>
                                                <th>Fecha y hora</th>
                                                <th>Acción</th>
                                            </tr>

                                        </thead>

                                        <tbody>

                                            <?php foreach ($proximosRecordatorios as $recordatorio): ?>

                                                <?php
                                                $telefonoWhatsApp =
                                                    preg_replace(
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

                                                    <td>
                                                        <div class="reminder-contact">
                                                            <?= htmlspecialchars(
                                                                $recordatorio['responsable']
                                                            ) ?>
                                                        </div>

                                                        <div class="reminder-activity-small">
                                                            <?= htmlspecialchars(
                                                                $recordatorio['nombre']
                                                            ) ?>
                                                        </div>
                                                    </td>

                                                    <td class="reminder-activity">
                                                        <?= htmlspecialchars(
                                                            $recordatorio['nombre']
                                                        ) ?>
                                                    </td>

                                                    <td class="reminder-date-time">

                                                        <?= date(
                                                            'd/m/Y',
                                                            strtotime(
                                                                $recordatorio['fecha']
                                                            )
                                                        ) ?>

                                                        <br>

                                                        <?= htmlspecialchars(
                                                            substr(
                                                                $recordatorio['hora_inicio'],
                                                                0,
                                                                5
                                                            )
                                                        ) ?>

                                                    </td>

                                                    <td class="reminder-action">

                                                        <a
                                                            href="<?= htmlspecialchars($enlaceWhatsApp) ?>"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="whatsapp-button"
                                                            title="Abrir WhatsApp Web"
                                                        >
                                                            <i class="bi bi-whatsapp"></i>
                                                        </a>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                </div>

                            <?php else: ?>

                                <div class="empty-state reminder-empty">
                                    <i class="bi bi-bell-slash"></i>
                                    No hay próximos recordatorios.
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </section>


            <section class="row g-4">

                <div class="col-xl-8">

                    <div class="panel">

                        <div class="panel-header panel-header-with-link">

                            <div>
                                Próximas actividades
                            </div>

                            <a href="/SIGA/app/views/actividades/actividades.php">
                                Ver todas
                                <i class="bi bi-arrow-right"></i>
                            </a>

                        </div>

                        <div class="panel-body p-0">

                            <?php if (!empty($proximasActividades)): ?>

                                <div class="upcoming-table-wrapper">

                                    <table class="upcoming-table">

                                        <thead>

                                            <tr>
                                                <th>Fecha</th>
                                                <th>Hora</th>
                                                <th>Actividad</th>
                                                <th>Espacio</th>
                                                <th>Responsable</th>
                                            </tr>

                                        </thead>

                                        <tbody>

                                            <?php foreach ($proximasActividades as $actividad): ?>

                                                <tr>

                                                    <td>
                                                        <span class="activity-dot"></span>

                                                        <?= date(
                                                            'd/m/Y',
                                                            strtotime(
                                                                $actividad['fecha']
                                                            )
                                                        ) ?>
                                                    </td>

                                                    <td>
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

                                                    <td class="activity-name">
                                                        <?= htmlspecialchars(
                                                            $actividad['nombre']
                                                        ) ?>
                                                    </td>

                                                    <td>
                                                        <?= htmlspecialchars(
                                                            $actividad['espacio']
                                                        ) ?>
                                                    </td>

                                                    <td>
                                                        <?= htmlspecialchars(
                                                            $actividad['responsable']
                                                        ) ?>
                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                </div>

                            <?php else: ?>

                                <div class="empty-state">
                                    <i class="bi bi-calendar-event"></i>
                                    No hay próximas actividades programadas.
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <div class="col-xl-4">

                    <div class="panel">

                        <div class="panel-header panel-header-with-link">

                            <div>
                                Espacios
                            </div>

                            <a href="../espacios/index.php">
                                Ver disponibilidad
                                <i class="bi bi-arrow-right"></i>
                            </a>

                        </div>

                        <div class="panel-body spaces-panel">

                            <?php if (!empty($espacios)): ?>

                                <?php foreach ($espacios as $espacio): ?>

                                    <div class="space-item">

                                        <div class="space-icon">
                                            <i class="bi bi-people"></i>
                                        </div>

                                        <div class="space-info">

                                            <div class="space-name">
                                                <?= htmlspecialchars(
                                                    $espacio['nombre']
                                                ) ?>
                                            </div>

                                            <div class="space-capacity">
                                                Capacidad:
                                                <?= htmlspecialchars(
                                                    $espacio['capacidad']
                                                ) ?>
                                                personas
                                            </div>

                                            <?php if (!empty($espacio['id_reserva'])): ?>

                                                <div class="space-schedule">
                                                    Hoy
                                                    <?= htmlspecialchars(
                                                        substr(
                                                            $espacio['hora_inicio'],
                                                            0,
                                                            5
                                                        )
                                                    ) ?>
                                                    -
                                                    <?= htmlspecialchars(
                                                        substr(
                                                            $espacio['hora_fin'],
                                                            0,
                                                            5
                                                        )
                                                    ) ?>
                                                </div>

                                            <?php endif; ?>

                                        </div>

                                        <?php if (!empty($espacio['id_reserva'])): ?>

                                            <span class="space-status occupied">
                                                Ocupado
                                            </span>

                                        <?php else: ?>

                                            <span class="space-status available">
                                                Disponible
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <div class="empty-state">
                                    <i class="bi bi-building"></i>
                                    No hay espacios disponibles para mostrar.
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </section>

        </div>

    </main>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

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
