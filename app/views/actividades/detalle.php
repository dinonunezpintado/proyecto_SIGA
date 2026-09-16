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

$idActividad = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

$actividad = null;
$equipamiento = [];
$auditoria = [];
$errorConsulta = false;
$modificacionExitosa = ($_GET['success'] ?? '') === 'modificado';
$cancelacionExitosa = ($_GET['success'] ?? '') === 'cancelado';
$errorCancelacion = ($_GET['error'] ?? '') === 'cancelar';
$yaCancelada = ($_GET['error'] ?? '') === 'ya_cancelada';

if (!$idActividad) {
    header('Location: /SIGA/app/views/actividades/actividades.php');
    exit;
}

$database = new Database();
$conexion = $database->conectar();

try {

    $stmt = $conexion->prepare("
        SELECT
            a.id_actividad,
            a.nombre AS actividad,
            a.descripcion,
            a.responsable,
            a.telefono_responsable,
            a.estado AS estado_actividad,
            ta.nombre AS tipo_actividad,
            r.id_reserva,
            r.fecha,
            r.hora_inicio,
            r.hora_fin,
            r.estado AS estado_reserva,
            r.observaciones,
            e.nombre AS espacio,
            e.capacidad,
            u.nombre AS usuario_nombre,
            u.apellido AS usuario_apellido
        FROM actividades a
        INNER JOIN reservas r
            ON r.id_reserva = a.id_reserva
        INNER JOIN tipos_actividad ta
            ON ta.id_tipo_actividad = a.id_tipo_actividad
        INNER JOIN espacios e
            ON e.id_espacio = r.id_espacio
        INNER JOIN usuarios u
            ON u.id_usuario = r.id_usuario
        WHERE a.id_actividad = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $idActividad
    ]);

    $actividad = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$actividad) {
        header('Location: /SIGA/app/views/actividades/actividades.php');
        exit;
    }

    $stmt = $conexion->prepare("
        SELECT
            re.id_equipamiento,
            re.cantidad,
            eq.nombre AS equipamiento
        FROM reserva_equipamiento re
        INNER JOIN equipamiento eq
            ON eq.id_equipamiento = re.id_equipamiento
        WHERE re.id_reserva = :id_reserva
        ORDER BY eq.nombre ASC
    ");

    $stmt->execute([
        ':id_reserva' => $actividad['id_reserva']
    ]);

    $equipamiento = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conexion->prepare("
        SELECT
            au.id_auditoria,
            au.entidad,
            au.id_registro,
            au.accion,
            au.fecha_hora,
            au.detalle,
            u.nombre AS usuario_nombre,
            u.apellido AS usuario_apellido
        FROM auditoria au
        INNER JOIN usuarios u
            ON u.id_usuario = au.id_usuario
        WHERE
            (
                au.entidad = 'actividades'
                AND au.id_registro = :id_actividad
            )
            OR
            (
                au.entidad = 'reservas'
                AND au.id_registro = :id_reserva
            )
        ORDER BY au.fecha_hora DESC, au.id_auditoria DESC
    ");

    $stmt->execute([
        ':id_actividad' => $actividad['id_actividad'],
        ':id_reserva' => $actividad['id_reserva']
    ]);

    $auditoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorConsulta = true;
}

$estado = $actividad['estado_actividad'] ?? 'Confirmada';

$claseEstado =
    strtolower($estado) === 'cancelada'
        ? 'cancelada'
        : 'confirmada';

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Detalle de actividad - SIGA</title>

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

</head>

<body>

<div class="siga-layout">

    <!--menu lateral-->

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
                href="/SIGA/app/views/dashboard/index.php"
                class="sidebar-link"
            >
                <i class="bi bi-house-door"></i>
                <span>Inicio</span>
            </a>

            <div class="sidebar-section">

                <a
                    href="/SIGA/app/views/actividades/actividades.php"
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
                        Actividades registradas
                    </a>

                </div>

            </div>

            <a href="#" class="sidebar-link">
                <i class="bi bi-calendar3"></i>
                <span>Calendario</span>
            </a>

            <a href="#" class="sidebar-link">
                <i class="bi bi-building"></i>
                <span>Disponibilidad de Espacios</span>
            </a>

            <a href="#" class="sidebar-link">
                <i class="bi bi-display"></i>
                <span>Equipamiento</span>
                <i class="bi bi-chevron-right sidebar-arrow"></i>
            </a>

            <a href="#" class="sidebar-link">
                <i class="bi bi-bar-chart"></i>
                <span>Reportes</span>
                <i class="bi bi-chevron-right sidebar-arrow"></i>
            </a>

            <a href="#" class="sidebar-link">
                <i class="bi bi-clock-history"></i>
                <span>Historial de Acciones</span>
            </a>

            <a href="#" class="sidebar-link">
                <i class="bi bi-bell"></i>
                <span>Recordatorios</span>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <a href="#" class="sidebar-link">
                <i class="bi bi-question-circle"></i>
                <span>Ayuda</span>
            </a>

        </div>

    </aside>


    <!--contenido principal-->

    <main class="main-content">

        <!--barra superior-->

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


        <div class="activity-detail-page">

            <div class="activity-detail-header">

                <div class="activity-detail-title">

                    <div class="activity-detail-title-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>

                    <div>

                        <h1>
                            Detalle de la actividad
                        </h1>

                        <p>
                            Consulte la información de la actividad y su reserva asociada.
                        </p>

                    </div>

                </div>

                <div class="activity-detail-actions">

                    <?php if ($estado !== 'Cancelada'): ?>

                        <a
                            href="/SIGA/app/views/actividades/editar.php?id=<?= (int) $actividad['id_actividad'] ?>"
                            class="activity-detail-edit"
                        >
                            <i class="bi bi-pencil-square"></i>
                            Modificar
                        </a>

                        <form
                            method="POST"
                            action="/SIGA/app/controllers/ActividadController.php"
                            class="activity-detail-cancel-form"
                            onsubmit="return confirm('¿Está seguro de que desea cancelar esta actividad? La actividad no será eliminada y quedará registrada como Cancelada.');"
                        >
                            <input type="hidden" name="accion" value="cancelar">
                            <input
                                type="hidden"
                                name="id_actividad"
                                value="<?= (int) $actividad['id_actividad'] ?>"
                            >

                            <button
                                type="submit"
                                class="activity-detail-cancel"
                            >
                                <i class="bi bi-x-circle"></i>
                                Cancelar actividad
                            </button>
                        </form>

                    <?php else: ?>

                        <a
                            href="/SIGA/app/views/actividades/nueva.php?reagendar=<?= (int) $actividad['id_actividad'] ?>"
                            class="activity-detail-reschedule"
                        >
                            <i class="bi bi-arrow-repeat"></i>
                            Volver a agendar
                        </a>

                    <?php endif; ?>

                    <a
                        href="/SIGA/app/views/actividades/actividades.php"
                        class="activity-detail-back"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Volver a actividades
                    </a>

                </div>

            </div>


            <?php if ($modificacionExitosa): ?>

                <div class="alert alert-success activity-detail-success">

                    <i class="bi bi-check-circle"></i>

                    Los cambios de la actividad se guardaron correctamente.

                </div>

            <?php endif; ?>


            <?php if ($cancelacionExitosa): ?>

                <div class="alert alert-success activity-detail-success">

                    <i class="bi bi-check-circle"></i>

                    La actividad y su reserva fueron canceladas correctamente.

                </div>

            <?php endif; ?>


            <?php if ($errorCancelacion): ?>

                <div class="alert alert-danger activity-detail-success">

                    <i class="bi bi-exclamation-circle"></i>

                    No fue posible cancelar la actividad.

                </div>

            <?php endif; ?>


            <?php if ($yaCancelada): ?>

                <div class="alert alert-info activity-detail-success">

                    <i class="bi bi-info-circle"></i>

                    Esta actividad ya se encuentra cancelada.

                </div>

            <?php endif; ?>


            <?php if ($errorConsulta): ?>

                <div class="alert alert-danger activity-detail-error">

                    <i class="bi bi-exclamation-circle"></i>

                    No fue posible consultar la información de la actividad.

                </div>

            <?php else: ?>


                <section class="activity-detail-card">

                    <div class="activity-detail-card-header">
                        Datos de la actividad
                    </div>

                    <div class="activity-detail-card-body">

                        <div class="activity-detail-grid">

                            <div class="activity-detail-item">

                                <div class="activity-detail-label">
                                    Nombre de la actividad
                                </div>

                                <div class="activity-detail-value strong">
                                    <?= htmlspecialchars($actividad['actividad']) ?>
                                </div>

                            </div>

                            <div class="activity-detail-item">

                                <div class="activity-detail-label">
                                    Tipo de actividad
                                </div>

                                <div class="activity-detail-value">
                                    <?= htmlspecialchars($actividad['tipo_actividad']) ?>
                                </div>

                            </div>

                            <div class="activity-detail-item">

                                <div class="activity-detail-label">
                                    Responsable
                                </div>

                                <div class="activity-detail-value">
                                    <?= htmlspecialchars($actividad['responsable']) ?>
                                </div>

                            </div>

                            <div class="activity-detail-item">

                                <div class="activity-detail-label">
                                    Teléfono del responsable
                                </div>

                                <div class="activity-detail-value">
                                    <?= htmlspecialchars($actividad['telefono_responsable']) ?>
                                </div>

                            </div>

                            <div class="activity-detail-item">

                                <div class="activity-detail-label">
                                    Estado
                                </div>

                                <div class="activity-detail-value">

                                    <span class="activity-detail-status <?= $claseEstado ?>">

                                        <span class="activity-status-dot"></span>

                                        <?= htmlspecialchars($estado) ?>

                                    </span>

                                </div>

                            </div>

                            <div class="activity-detail-item">

                                <div class="activity-detail-label">
                                    Registrada por
                                </div>

                                <div class="activity-detail-value">
                                    <?= htmlspecialchars(
                                        $actividad['usuario_nombre'] . ' ' .
                                        $actividad['usuario_apellido']
                                    ) ?>
                                </div>

                            </div>

                            <div class="activity-detail-item activity-detail-full">

                                <div class="activity-detail-label">
                                    Descripción / Observaciones
                                </div>

                                <div class="activity-detail-description">

                                    <?php if (
                                        trim((string) $actividad['descripcion']) !== ''
                                    ): ?>

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $actividad['descripcion']
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        Sin observaciones.

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>


                <section class="activity-detail-card">

                    <div class="activity-detail-card-header">
                        Datos de la reserva
                    </div>

                    <div class="activity-detail-card-body">

                        <div class="activity-detail-grid">

                            <div class="activity-detail-item">

                                <div class="activity-detail-label">
                                    Espacio
                                </div>

                                <div class="activity-detail-value strong">
                                    <?= htmlspecialchars($actividad['espacio']) ?>
                                </div>

                            </div>

                            <div class="activity-detail-item">

                                <div class="activity-detail-label">
                                    Capacidad del espacio
                                </div>

                                <div class="activity-detail-value">
                                    <?= (int) $actividad['capacidad'] ?> personas
                                </div>

                            </div>

                            <div class="activity-detail-item">

                                <div class="activity-detail-label">
                                    Fecha
                                </div>

                                <div class="activity-detail-value">
                                    <?= htmlspecialchars(
                                        date(
                                            'd/m/Y',
                                            strtotime($actividad['fecha'])
                                        )
                                    ) ?>
                                </div>

                            </div>

                            <div class="activity-detail-item">

                                <div class="activity-detail-label">
                                    Horario
                                </div>

                                <div class="activity-detail-value">
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

                            <div class="activity-detail-item">

                                <div class="activity-detail-label">
                                    Estado de la reserva
                                </div>

                                <div class="activity-detail-value">
                                    <?= htmlspecialchars($actividad['estado_reserva']) ?>
                                </div>

                            </div>

                            <div class="activity-detail-item">

                                <div class="activity-detail-label">
                                    Observaciones de la reserva
                                </div>

                                <div class="activity-detail-value">

                                    <?php if (
                                        trim((string) $actividad['observaciones']) !== ''
                                    ): ?>

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $actividad['observaciones']
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        Sin observaciones.

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>


                <section class="activity-detail-card">

                    <div class="activity-detail-card-header">
                        Equipamiento solicitado
                    </div>

                    <div class="activity-detail-card-body">

                        <?php if (empty($equipamiento)): ?>

                            <div class="activity-detail-empty">

                                <i class="bi bi-info-circle"></i>

                                Esta actividad no tiene equipamiento solicitado.

                            </div>

                        <?php else: ?>

                            <div class="activities-table-wrapper">

                                <table class="activity-detail-equipment">

                                    <thead>

                                        <tr>
                                            <th>Equipamiento</th>
                                            <th>Cantidad solicitada</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($equipamiento as $equipo): ?>

                                            <tr>

                                                <td>
                                                    <?= htmlspecialchars(
                                                        $equipo['equipamiento']
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <?= (int) $equipo['cantidad'] ?>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php endif; ?>

                    </div>

                </section>


                <section class="activity-detail-card">

                    <div class="activity-detail-card-header">
                        Trazabilidad de la actividad
                    </div>

                    <div class="activity-detail-card-body">

                        <div class="activity-trace-intro">

                            <i class="bi bi-clock-history"></i>

                            <div>
                                <strong>Historial de acciones</strong>
                                <p>
                                    Aquí se registran las acciones realizadas sobre la actividad y su reserva asociada.
                                </p>
                            </div>

                        </div>


                        <?php if (empty($auditoria)): ?>

                            <div class="activity-detail-empty">
                                No existen registros de trazabilidad para esta actividad.
                            </div>

                        <?php else: ?>

                            <div class="activity-trace-list">

                                <?php foreach ($auditoria as $registro): ?>

                                    <?php
                                    $accion = $registro['accion'];

                                    if ($accion === 'CREACION') {
                                        $claseAccion = 'creacion';
                                        $iconoAccion = 'bi-plus-circle';
                                        $textoAccion = 'Creación';
                                    } elseif ($accion === 'MODIFICACION') {
                                        $claseAccion = 'modificacion';
                                        $iconoAccion = 'bi-pencil-square';
                                        $textoAccion = 'Modificación';
                                    } else {
                                        $claseAccion = 'cancelacion';
                                        $iconoAccion = 'bi-x-circle';
                                        $textoAccion = 'Cancelación';
                                    }
                                    ?>

                                    <div class="activity-trace-item">

                                        <div class="activity-trace-icon <?= $claseAccion ?>">
                                            <i class="bi <?= $iconoAccion ?>"></i>
                                        </div>

                                        <div class="activity-trace-content">

                                            <div class="activity-trace-top">

                                                <div>

                                                    <strong>
                                                        <?= htmlspecialchars($textoAccion) ?>
                                                    </strong>

                                                    <span class="activity-trace-entity">
                                                        <?= $registro['entidad'] === 'reservas'
                                                            ? 'Reserva'
                                                            : 'Actividad' ?>
                                                    </span>

                                                </div>

                                                <time>
                                                    <?= htmlspecialchars(
                                                        date(
                                                            'd/m/Y H:i',
                                                            strtotime($registro['fecha_hora'])
                                                        )
                                                    ) ?>
                                                </time>

                                            </div>

                                            <div class="activity-trace-user">

                                                <i class="bi bi-person"></i>

                                                <?= htmlspecialchars(
                                                    $registro['usuario_nombre'] . ' ' .
                                                    $registro['usuario_apellido']
                                                ) ?>

                                            </div>

                                            <?php if (trim((string) $registro['detalle']) !== ''): ?>

                                                <div class="activity-trace-detail">

                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            $registro['detalle']
                                                        )
                                                    ) ?>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>

                </section>

            <?php endif; ?>

        </div>

    </main>

</div>

</body>

</html>
