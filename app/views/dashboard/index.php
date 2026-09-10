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

$idUsuario = $usuario['id_usuario'];

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

    $stmt = $conexion->prepare(
        $sqlActividadesHoy
    );

    $stmt->execute([
        ':fecha' => $fechaActual
    ]);

    $resultado = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    $actividadesHoy =
        $resultado['total'] ?? 0;



    $sqlActividadesSemana = "

        SELECT COUNT(*) AS total

        FROM actividades a

        INNER JOIN reservas r
            ON a.id_reserva = r.id_reserva

        WHERE YEARWEEK(
            r.fecha,
            1
        ) = YEARWEEK(
            CURDATE(),
            1
        )

        AND a.estado = 'Confirmada'

        AND r.estado = 'Confirmada'

    ";

    $stmt = $conexion->prepare(
        $sqlActividadesSemana
    );

    $stmt->execute();

    $resultado = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    $actividadesSemana =
        $resultado['total'] ?? 0;



    $sqlActividadesMes = "

        SELECT COUNT(*) AS total

        FROM actividades a

        INNER JOIN reservas r
            ON a.id_reserva = r.id_reserva

        WHERE MONTH(
            r.fecha
        ) = MONTH(
            CURDATE()
        )

        AND YEAR(
            r.fecha
        ) = YEAR(
            CURDATE()
        )

        AND a.estado = 'Confirmada'

        AND r.estado = 'Confirmada'

    ";

    $stmt = $conexion->prepare(
        $sqlActividadesMes
    );

    $stmt->execute();

    $resultado = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    $actividadesMes =
        $resultado['total'] ?? 0;



    $sqlEquiposHoy = "

        SELECT
            COALESCE(
                SUM(re.cantidad),
                0
            ) AS total

        FROM reserva_equipamiento re

        INNER JOIN reservas r
            ON re.id_reserva = r.id_reserva

        WHERE r.fecha = :fecha

        AND r.estado = 'Confirmada'

    ";

    $stmt = $conexion->prepare(
        $sqlEquiposHoy
    );

    $stmt->execute([
        ':fecha' => $fechaActual
    ]);

    $resultado = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    $equiposHoy =
        $resultado['total'] ?? 0;


    
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

        ORDER BY
            r.hora_inicio ASC

    ";

    $stmt = $conexion->prepare(
        $sqlAgendaHoy
    );

    $stmt->execute([
        ':fecha' => $fechaActual
    ]);

    $agendaHoy = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    $sqlProximasActividades = "

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

        WHERE r.fecha >= CURDATE()

        AND a.estado = 'Confirmada'

        AND r.estado = 'Confirmada'

        ORDER BY

            r.fecha ASC,

            r.hora_inicio ASC

        LIMIT 5

    ";

    $stmt = $conexion->prepare(
        $sqlProximasActividades
    );

    $stmt->execute();

    $proximasActividades =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {


    $actividadesHoy = 0;

    $actividadesSemana = 0;

    $actividadesMes = 0;

    $equiposHoy = 0;

    $agendaHoy = [];

    $proximasActividades = [];

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


$timestampActual = strtotime(
    $fechaActual
);


$nombreDia = $diasSemana[
    date(
        'l',
        $timestampActual
    )
];


$diaNumero = date(
    'd',
    $timestampActual
);


$mesNumero = date(
    'n',
    $timestampActual
);


$anio = date(
    'Y',
    $timestampActual
);


$fechaTexto =

    $nombreDia .

    ', ' .

    $diaNumero .

    ' de ' .

    $meses[$mesNumero] .

    ' de ' .

    $anio;



$anioCalendario = (int) date('Y');

$mesCalendario = (int) date('n');


$nombreMesCalendario =

    ucfirst(
        $meses[
            $mesCalendario
        ]
    );


$primerDiaMes = new DateTime(
    $anioCalendario .
    '-' .
    str_pad(
        $mesCalendario,
        2,
        '0',
        STR_PAD_LEFT
    ) .
    '-01'
);



$primerDiaSemana =
    (int) $primerDiaMes->format('N');


$diasDelMes =
    (int) $primerDiaMes->format('t');


$diaActual =
    (int) date('j');


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
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <link rel="stylesheet" href="../../../assets/css/dashboard.css">
</head>


<body>


<div class="app-container">


    <aside class="sidebar">


        <div class="sidebar-logo">


            <h1>

                <i class="bi bi-calendar3"></i>

                SIGA

            </h1>


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


            <a href="../actividades/index.php">

                <i class="bi bi-calendar-plus"></i>

                <span>Actividades y Reservas</span>

            </a>


            <a href="../calendario/index.php">

                <i class="bi bi-calendar-week"></i>

                <span>Calendario</span>

            </a>


            <a href="../espacios/index.php">

                <i class="bi bi-building"></i>

                <span>Disponibilidad de Espacios</span>

            </a>


            <a href="../equipamiento/index.php">

                <i class="bi bi-display"></i>

                <span>Equipamiento</span>

            </a>


            <a href="../reportes/index.php">

                <i class="bi bi-bar-chart"></i>

                <span>Reportes</span>

            </a>


            <a href="../auditoria/index.php">

                <i class="bi bi-clock-history"></i>

                <span>Historial de Acciones</span>

            </a>


            <a href="../recordatorios/index.php">

                <i class="bi bi-bell"></i>

                <span>Recordatorios</span>

            </a>


        </nav>


    </aside>



    <main class="main-content">


        <header class="topbar">


            <div class="topbar-left">

                <i class="bi bi-list"></i>

            </div>


            <div class="topbar-right">


                <div>

                    <i class="bi bi-bell"></i>

                </div>


                <div class="user-info">

                    <i class="bi bi-person"></i>


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


            <!-- BIENVENIDA -->

            <div class="mb-4">


                <h2 class="welcome-title">

                    Bienvenido,

                    <?= htmlspecialchars(
                        $nombreUsuario .
                        ' ' .
                        $apellidoUsuario
                    ) ?>

                </h2>


                <div class="welcome-role">

                    <?= htmlspecialchars(
                        $rolUsuario
                    ) ?>

                </div>


            </div>



            <section class="row g-4 mb-4">


                <!-- ACTIVIDADES HOY -->

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

                                <?= htmlspecialchars(
                                    $fechaTexto
                                ) ?>

                            </div>


                            <?php if (
                                !empty($agendaHoy)
                            ): ?>


                                <div class="px-4">


                                    <?php foreach (
                                        $agendaHoy
                                        as
                                        $actividad
                                    ): ?>


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


                                                <span
                                                    class="badge text-bg-success"
                                                >

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


                        <div class="panel-body">


                            <div class="calendar-header">


                                <i class="bi bi-chevron-left"></i>


                                <div class="calendar-title">

                                    <?= $nombreMesCalendario ?>

                                    <?= $anioCalendario ?>

                                </div>


                                <i class="bi bi-chevron-right"></i>


                            </div>


                            <div class="calendar-grid">



                                <div class="calendar-day-name">
                                    Lu
                                </div>

                                <div class="calendar-day-name">
                                    Ma
                                </div>

                                <div class="calendar-day-name">
                                    Mi
                                </div>

                                <div class="calendar-day-name">
                                    Ju
                                </div>

                                <div class="calendar-day-name">
                                    Vi
                                </div>

                                <div class="calendar-day-name">
                                    Sá
                                </div>

                                <div class="calendar-day-name">
                                    Do
                                </div>



                                <?php for (
                                    $i = 1;
                                    $i < $primerDiaSemana;
                                    $i++
                                ): ?>


                                    <div class="calendar-empty"></div>


                                <?php endfor; ?>



                                <?php for (
                                    $dia = 1;
                                    $dia <= $diasDelMes;
                                    $dia++
                                ): ?>


                                    <div
                                        class="calendar-day
                                        <?=

                                        $dia === $diaActual
                                        ?

                                        'today'
                                        :

                                        ''

                                        ?>"
                                    >

                                        <?= $dia ?>

                                    </div>


                                <?php endfor; ?>


                            </div>


                        </div>


                    </div>


                </div>


            </section>


            <section class="row g-4">


                <div class="col-xl-7">


                    <div class="panel">


                        <div class="panel-header">

                            Próximas actividades

                        </div>


                        <div class="panel-body">


                            <?php if (
                                !empty(
                                    $proximasActividades
                                )
                            ): ?>


                                <?php foreach (
                                    $proximasActividades
                                    as
                                    $actividad
                                ): ?>


                                    <?php

                                    $timestampActividad =
                                        strtotime(
                                            $actividad['fecha']
                                        );

                                    $diaActividad =
                                        date(
                                            'd',
                                            $timestampActividad
                                        );

                                    $mesActividad =
                                        strtoupper(
                                            substr(
                                                $meses[
                                                    (int) date(
                                                        'n',
                                                        $timestampActividad
                                                    )
                                                ],
                                                0,
                                                3
                                            )
                                        );

                                    ?>


                                    <div class="upcoming-item">


                                        <div class="upcoming-date">


                                            <div class="upcoming-day">

                                                <?= $diaActividad ?>

                                            </div>


                                            <div class="upcoming-month">

                                                <?= $mesActividad ?>

                                            </div>


                                        </div>


                                        <div class="upcoming-info">


                                            <div class="upcoming-name">

                                                <?= htmlspecialchars(
                                                    $actividad['nombre']
                                                ) ?>

                                            </div>


                                            <div class="upcoming-details">


                                                <?= htmlspecialchars(
                                                    $actividad['espacio']
                                                ) ?>


                                                &nbsp;•&nbsp;


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


                                        </div>


                                        <span
                                            class="badge text-bg-success"
                                        >

                                            Confirmada

                                        </span>


                                    </div>


                                <?php endforeach; ?>


                            <?php else: ?>


                                <div class="empty-state">


                                    <i class="bi bi-calendar-event"></i>


                                    No hay próximas actividades programadas.


                                </div>


                            <?php endif; ?>


                        </div>


                    </div>


                </div>


                <div class="col-xl-5">


                    <div class="panel">


                        <div class="panel-header">

                            Avisos y recordatorios

                        </div>


                        <div class="panel-body">


                            <div
                                class="alert alert-warning mb-3"
                            >

                                <i
                                    class="bi bi-bell me-2"
                                ></i>

                                Los recordatorios de actividades
                                serán gestionados por SIGA.


                            </div>


                            <div
                                class="alert alert-info mb-3"
                            >

                                <i
                                    class="bi bi-info-circle me-2"
                                ></i>

                                Verifica la disponibilidad de espacios
                                antes de registrar nuevas actividades.


                            </div>


                            <div
                                class="alert alert-success mb-0"
                            >

                                <i
                                    class="bi bi-check-circle me-2"
                                ></i>

                                La información del dashboard se actualiza
                                automáticamente a partir de las reservas
                                registradas.


                            </div>


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


</body>


</html>