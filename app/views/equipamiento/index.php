<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: /SIGA/index.php');
    exit;
}

require_once __DIR__ . '/../../../config/database.php';

$actividadesHoy = [];
$errorConsulta = false;
$totalActividades = 0;
$totalUnidades = 0;

try {

    $database = new Database();
    $conexion = $database->conectar();

    $sql = "
        SELECT
            r.id_reserva,
            r.fecha,
            r.hora_inicio,
            r.hora_fin,
            a.id_actividad,
            a.nombre AS actividad,
            a.estado AS estado_actividad,
            e.nombre AS equipamiento,
            re.cantidad AS cantidad_equipamiento,
            es.nombre AS espacio
        FROM reservas r
        INNER JOIN actividades a
            ON a.id_reserva = r.id_reserva
        INNER JOIN espacios es
            ON es.id_espacio = r.id_espacio
        INNER JOIN reserva_equipamiento re
            ON re.id_reserva = r.id_reserva
        INNER JOIN equipamiento e
            ON e.id_equipamiento = re.id_equipamiento
        WHERE r.fecha = CURDATE()
          AND r.estado = 'Confirmada'
          AND a.estado = 'Confirmada'
        ORDER BY r.hora_inicio ASC, a.nombre ASC, e.nombre ASC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($filas as $fila) {

        $idActividad = (int) $fila['id_actividad'];

        if (!isset($actividadesHoy[$idActividad])) {
            $actividadesHoy[$idActividad] = [
                'id_actividad' => $idActividad,
                'fecha' => $fila['fecha'],
                'hora_inicio' => $fila['hora_inicio'],
                'hora_fin' => $fila['hora_fin'],
                'actividad' => $fila['actividad'],
                'espacio' => $fila['espacio'],
                'equipamiento' => []
            ];
        }

        $actividadesHoy[$idActividad]['equipamiento'][] = [
            'nombre' => $fila['equipamiento'],
            'cantidad' => (int) $fila['cantidad_equipamiento']
        ];

        $totalUnidades += (int) $fila['cantidad_equipamiento'];
    }

    $actividadesHoy = array_values($actividadesHoy);
    $totalActividades = count($actividadesHoy);

} catch (PDOException $e) {
    $errorConsulta = true;
}

function formatearHora($hora)
{
    return date('H:i', strtotime($hora));
}

function formatearFecha($fecha)
{
    return date('d/m/Y', strtotime($fecha));
}

function iconoEquipamiento($nombre)
{
    $nombreNormalizado = strtolower($nombre);

    if (strpos($nombreNormalizado, 'notebook') !== false) {
        return 'bi-laptop';
    }

    if (
        strpos($nombreNormalizado, 'proyector') !== false ||
        strpos($nombreNormalizado, 'pantalla') !== false ||
        strpos($nombreNormalizado, 'tv') !== false
    ) {
        return 'bi-projector';
    }

    if (
        strpos($nombreNormalizado, 'micrófono') !== false ||
        strpos($nombreNormalizado, 'microfono') !== false
    ) {
        return 'bi-mic';
    }

    if (
        strpos($nombreNormalizado, 'audio') !== false ||
        strpos($nombreNormalizado, 'parlante') !== false
    ) {
        return 'bi-volume-up';
    }

    if (
        strpos($nombreNormalizado, 'alargue') !== false ||
        strpos($nombreNormalizado, 'cable') !== false
    ) {
        return 'bi-plug';
    }

    if (
        strpos($nombreNormalizado, 'puntero') !== false ||
        strpos($nombreNormalizado, 'láser') !== false ||
        strpos($nombreNormalizado, 'laser') !== false
    ) {
        return 'bi-cursor';
    }

    if (
        strpos($nombreNormalizado, 'teclado') !== false ||
        strpos($nombreNormalizado, 'mouse') !== false
    ) {
        return 'bi-keyboard';
    }

    return 'bi-box-seam';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipamiento solicitado hoy - SIGA</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/SIGA/assets/css/dashboard.css">
    <link rel="stylesheet" href="/SIGA/assets/css/equipamiento.css?v=1">
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

            <a href="/SIGA/app/views/dashboard/index.php" class="sidebar-link">
                <i class="bi bi-house-door"></i>
                <span>Inicio</span>
            </a>

            <div class="sidebar-section">

                <a href="#" class="sidebar-link">
                    <i class="bi bi-calendar-event"></i>
                    <span>Actividades y Reservas</span>
                    <i class="bi bi-chevron-down sidebar-arrow"></i>
                </a>

                <div class="sidebar-submenu">
                    <a href="/SIGA/app/views/actividades/nueva.php" class="sidebar-sublink">
                        <span class="submenu-dot"></span>
                        Nueva actividad
                    </a>

                    <a href="/SIGA/app/views/actividades/actividades.php" class="sidebar-sublink">
                        <span class="submenu-dot"></span>
                        Actividades registradas
                    </a>
                </div>

            </div>

            <a href="/SIGA/app/views/calendario/index.php">
                <i class="bi bi-calendar-week"></i>
                <span>Calendario</span>
            </a>

            <a href="/SIGA/app/views/reportes/index.php">
                <i class="bi bi-bar-chart"></i>
                <span>Reportes</span>
            </a>

            <a href="/SIGA/app/views/auditoria/index.php">
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

        <!--barra superior-->
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="topbar-menu" id="menuToggle" aria-label="Mostrar u ocultar menú" title="Mostrar u ocultar menú">
                    <i class="bi bi-list"></i>
                </button>
            </div>

            <div class="topbar-right">
                <div class="user-info">
                    <i class="bi bi-person"></i>
                    <span>
                        <?= htmlspecialchars($_SESSION['usuario']['nombre'] . ' ' . $_SESSION['usuario']['apellido']) ?>
                    </span>
                </div>

                <a href="/SIGA/app/controllers/AuthController.php?accion=logout" class="logout-link">
                    <i class="bi bi-box-arrow-right"></i>
                    Salir
                </a>
            </div>
        </header>

        <!--contenido-->
        <div class="equipment-list-page">

            <div class="equipment-list-header">
                <div class="equipment-list-title">
                    <div class="equipment-list-title-icon">
                        <i class="bi bi-display"></i>
                    </div>

                    <div>
                        <h1>Equipamiento solicitado hoy</h1>
                        <p>Equipamiento requerido para las actividades programadas durante el día.</p>
                    </div>
                </div>

<!--                <div class="equipment-date">
                    <i class="bi bi-calendar3"></i>
                    <?= formatearFecha(date('Y-m-d')) ?>
                </div>-->
            </div>

            <?php if ($errorConsulta): ?>
                <div class="alert alert-danger equipment-alert">
                    <i class="bi bi-exclamation-circle"></i>
                    No fue posible consultar el equipamiento solicitado. Intente nuevamente.
                </div>
            <?php endif; ?>

            <section class="equipment-list-card">

                <div class="equipment-list-card-header">
                    <div class="equipment-list-card-title">
                        <i class="bi bi-calendar-check"></i>
                        <h2>Actividades de hoy con equipamiento</h2>
                    </div>

                    <div class="equipment-list-card-date">
                        <i class="bi bi-calendar3"></i>
                        <?= formatearFecha(date('Y-m-d')) ?>
                    </div>
                </div>

                <?php if (empty($actividadesHoy) && !$errorConsulta): ?>
                    <div class="equipment-empty">
                        <i class="bi bi-calendar2-check"></i>
                        <h3>No hay equipamiento solicitado para hoy</h3>
                        <p>Las actividades confirmadas con equipamiento aparecerán en este listado.</p>
                    </div>
                <?php elseif (!empty($actividadesHoy)): ?>

                    <div class="equipment-table-wrapper">
                        <table class="equipment-table">
                            <thead>
                                <tr>
                                    <th>Hora</th>
                                    <th>Actividad</th>
                                    <th>Espacio</th>
                                    <th>Equipamiento solicitado</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($actividadesHoy as $actividad): ?>
                                    <tr>
                                        <td class="equipment-time">
                                            <i class="bi bi-clock"></i>
                                            <span>
                                                <?= formatearHora($actividad['hora_inicio']) ?>
                                                -
                                                <?= formatearHora($actividad['hora_fin']) ?>
                                            </span>
                                        </td>

                                        <td class="equipment-activity">
                                            <strong><?= htmlspecialchars($actividad['actividad']) ?></strong>
                                            <span class="equipment-status">
                                                <span class="equipment-status-dot"></span>
                                                Confirmada
                                            </span>
                                        </td>

                                        <td class="equipment-space">
                                            <i class="bi bi-geo-alt"></i>
                                            <?= htmlspecialchars($actividad['espacio']) ?>
                                        </td>

                                        <td>
                                            <div class="equipment-request-box">
                                                <?php foreach ($actividad['equipamiento'] as $item): ?>
                                                    <div class="equipment-badge">
                                                        <span class="equipment-badge-icon">
                                                            <i class="bi <?= htmlspecialchars(iconoEquipamiento($item['nombre'])) ?>"></i>
                                                        </span>
                                                        <span class="equipment-badge-name">
                                                            <?= htmlspecialchars($item['nombre']) ?>
                                                        </span>
                                                        <strong>× <?= $item['cantidad'] ?></strong>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="equipment-list-footer">
                        <span>
                            Total de actividades de hoy:
                            <strong><?= $totalActividades ?></strong>
                        </span>

                        <span>
                            Unidades de equipamiento solicitadas:
                            <strong><?= $totalUnidades ?></strong>
                        </span>
                    </div>

                <?php endif; ?>

            </section>
        </div>
    </main>
</div>

<script>
    const menuToggle = document.getElementById('menuToggle');

    menuToggle.addEventListener('click', function () {
        document.body.classList.toggle('sidebar-collapsed');
    });
</script>

</body>
</html>
