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
$mensajeExito = '';
$mensajeError = '';
$totalActividades = 0;
$totalUnidades = 0;
$totalDevueltas = 0;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['accion'])
    &&
    $_POST['accion'] === 'marcar_devolucion'
) {
    $idReserva = filter_input(
        INPUT_POST,
        'id_reserva',
        FILTER_VALIDATE_INT
    );

    $idEquipamiento = filter_input(
        INPUT_POST,
        'id_equipamiento',
        FILTER_VALIDATE_INT
    );

    $devuelto = isset($_POST['devuelto']) && $_POST['devuelto'] === '1';

    if (!$idReserva || !$idEquipamiento) {
        $mensajeError = 'No fue posible identificar el equipamiento seleccionado.';
    } else {
        try {
            $database = new Database();
            $conexion = $database->conectar();

            $conexion->beginTransaction();

            $sqlCantidad = "
                SELECT
                    re.cantidad,
                    e.nombre AS equipamiento
                FROM reserva_equipamiento re
                INNER JOIN reservas r
                    ON r.id_reserva = re.id_reserva
                INNER JOIN actividades a
                    ON a.id_reserva = r.id_reserva
                INNER JOIN equipamiento e
                    ON e.id_equipamiento = re.id_equipamiento
                WHERE re.id_reserva = :id_reserva
                  AND re.id_equipamiento = :id_equipamiento
                  AND r.fecha = CURDATE()
                  AND r.estado = 'Confirmada'
                  AND a.estado = 'Confirmada'
                LIMIT 1
                FOR UPDATE
            ";

            $stmtCantidad = $conexion->prepare($sqlCantidad);

            $stmtCantidad->execute([
                ':id_reserva' => $idReserva,
                ':id_equipamiento' => $idEquipamiento
            ]);

            $registro = $stmtCantidad->fetch(PDO::FETCH_ASSOC);

            if (!$registro) {
                throw new Exception(
                    'El equipamiento seleccionado ya no está disponible para registrar la devolución.'
                );
            }

            $cantidadDevuelta = $devuelto
                ? (int) $registro['cantidad']
                : 0;

            $idUsuario = (int) $_SESSION['usuario']['id_usuario'];

            $sqlActualizar = "
                UPDATE reserva_equipamiento
                SET
                    cantidad_devuelta = :cantidad_devuelta,
                    fecha_devolucion = CASE
                        WHEN :cantidad_devuelta_fecha > 0
                        THEN NOW()
                        ELSE NULL
                    END,
                    id_usuario_devolucion = CASE
                        WHEN :cantidad_devuelta_usuario > 0
                        THEN :id_usuario
                        ELSE NULL
                    END
                WHERE id_reserva = :id_reserva
                  AND id_equipamiento = :id_equipamiento
            ";

            $stmtActualizar = $conexion->prepare($sqlActualizar);

            $stmtActualizar->execute([
                ':cantidad_devuelta' => $cantidadDevuelta,
                ':cantidad_devuelta_fecha' => $cantidadDevuelta,
                ':cantidad_devuelta_usuario' => $cantidadDevuelta,
                ':id_usuario' => $idUsuario,
                ':id_reserva' => $idReserva,
                ':id_equipamiento' => $idEquipamiento
            ]);

            $detalleDevolucion = $devuelto
                ? 'Devolución de equipamiento: ' .
                    $registro['equipamiento'] .
                    ' × ' .
                    $cantidadDevuelta .
                    ' devuelto.'
                : 'Se desmarcó la devolución de ' .
                    $registro['equipamiento'] .
                    ' × ' .
                    $registro['cantidad'] .
                    '.';

            $stmtAuditoria = $conexion->prepare("
                INSERT INTO auditoria (
                    id_usuario,
                    entidad,
                    id_registro,
                    accion,
                    detalle
                )
                VALUES (
                    :id_usuario,
                    'reservas',
                    :id_registro,
                    'MODIFICACION',
                    :detalle
                )
            ");

            $stmtAuditoria->execute([
                ':id_usuario' => $idUsuario,
                ':id_registro' => $idReserva,
                ':detalle' => $detalleDevolucion
            ]);

            $conexion->commit();

            $mensajeExito = $devuelto
                ? 'La devolución del equipamiento fue registrada correctamente.'
                : 'La devolución fue desmarcada correctamente.';

        } catch (Throwable $e) {

            if (isset($conexion) && $conexion->inTransaction()) {
                $conexion->rollBack();
            }

            $mensajeError = 'No fue posible registrar la devolución. Intente nuevamente.';
        }
    }
}

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
            e.id_equipamiento,
            e.nombre AS equipamiento,
            re.cantidad AS cantidad_equipamiento,
            re.cantidad_devuelta,
            re.fecha_devolucion,
            re.id_usuario_devolucion,
            es.nombre AS espacio,
            CONCAT(
                COALESCE(u.nombre, ''),
                ' ',
                COALESCE(u.apellido, '')
            ) AS usuario_devolucion
        FROM reservas r
        INNER JOIN actividades a
            ON a.id_reserva = r.id_reserva
        INNER JOIN espacios es
            ON es.id_espacio = r.id_espacio
        INNER JOIN reserva_equipamiento re
            ON re.id_reserva = r.id_reserva
        INNER JOIN equipamiento e
            ON e.id_equipamiento = re.id_equipamiento
        LEFT JOIN usuarios u
            ON u.id_usuario = re.id_usuario_devolucion
        WHERE r.fecha = CURDATE()
          AND r.estado = 'Confirmada'
          AND a.estado = 'Confirmada'
        ORDER BY
            r.hora_inicio ASC,
            a.nombre ASC,
            e.nombre ASC
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($filas as $fila) {

        $idActividad = (int) $fila['id_actividad'];

        if (!isset($actividadesHoy[$idActividad])) {
            $actividadesHoy[$idActividad] = [
                'id_actividad' => $idActividad,
                'id_reserva' => (int) $fila['id_reserva'],
                'fecha' => $fila['fecha'],
                'hora_inicio' => $fila['hora_inicio'],
                'hora_fin' => $fila['hora_fin'],
                'actividad' => $fila['actividad'],
                'espacio' => $fila['espacio'],
                'equipamiento' => []
            ];
        }

        $cantidad = (int) $fila['cantidad_equipamiento'];
        $cantidadDevuelta = (int) $fila['cantidad_devuelta'];

        $actividadesHoy[$idActividad]['equipamiento'][] = [
            'id_equipamiento' => (int) $fila['id_equipamiento'],
            'nombre' => $fila['equipamiento'],
            'cantidad' => $cantidad,
            'cantidad_devuelta' => $cantidadDevuelta,
            'fecha_devolucion' => $fila['fecha_devolucion'],
            'usuario_devolucion' => trim($fila['usuario_devolucion'] ?? '')
        ];

        $totalUnidades += $cantidad;
        $totalDevueltas += $cantidadDevuelta;
    }

    foreach ($actividadesHoy as &$actividad) {

        $actividad['equipamiento_total'] = count(
            $actividad['equipamiento']
        );

        $actividad['equipamiento_devuelto'] = 0;

        foreach ($actividad['equipamiento'] as $item) {

            if (
                $item['cantidad_devuelta'] >=
                $item['cantidad']
            ) {
                $actividad['equipamiento_devuelto']++;
            }
        }

        $actividad['todo_devuelto'] =
            $actividad['equipamiento_devuelto'] ===
            $actividad['equipamiento_total'];
    }

    unset($actividad);

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
    $nombreNormalizado = strtolower(trim($nombre));

    if (strpos($nombreNormalizado, 'notebook') !== false) {
        return 'bi-laptop';
    }

    if (strpos($nombreNormalizado, 'proyector') !== false) {
        return 'bi-projector';
    }

    if (strpos($nombreNormalizado, 'pantalla') !== false) {
        return 'bi-file-easel';
    }

    if (strpos($nombreNormalizado, 'tv') !== false) {
        return 'bi-tv';
    }

    if (
        strpos($nombreNormalizado, 'micrófono') !== false ||
        strpos($nombreNormalizado, 'microfono') !== false
    ) {
        return 'bi-mic';
    }

    if (
        strpos($nombreNormalizado, 'equipo de audio') !== false ||
        strpos($nombreNormalizado, 'parlante') !== false
    ) {
        return 'bi-volume-up';
    }

    if (
        strpos($nombreNormalizado, 'alargue') !== false ||
        strpos($nombreNormalizado, 'cable') !== false ||
        strpos($nombreNormalizado, 'zapatilla') !== false
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

    if (strpos($nombreNormalizado, 'mouse') !== false) {
        return 'bi-mouse';
    }

    if (strpos($nombreNormalizado, 'teclado') !== false) {
        return 'bi-keyboard';
    }

    if (strpos($nombreNormalizado, 'pendrive') !== false) {
        return 'bi-usb-drive';
    }

    if (
        strpos($nombreNormalizado, 'adaptador hdmi - vga') !== false ||
        strpos($nombreNormalizado, 'adaptador hdmi-vga') !== false ||
        strpos($nombreNormalizado, 'hdmi - vga') !== false ||
        strpos($nombreNormalizado, 'hdmi-vga') !== false
    ) {
        return 'bi-hdmi';
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
    <link rel="icon" type="image/png" href="/SIGA/assets/img/logo/favicon.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/SIGA/assets/css/dashboard.css">
    <link rel="stylesheet" href="/SIGA/assets/css/equipamiento.css?v=20261013">
</head>
<body>

<div class="siga-layout">

    <!--menu lateral-->
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
                <div class="user-info">
                    <i class="bi bi-person"></i>
                    <span>
                        <?= htmlspecialchars(
                            $_SESSION['usuario']['nombre'] .
                            ' ' .
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
        <div class="equipment-list-page">

            <div class="equipment-list-header">

                <div class="equipment-list-title">

                    <div class="equipment-list-title-icon">
                        <i class="bi bi-display"></i>
                    </div>

                    <div>
                        <h1>Equipamiento solicitado hoy</h1>
                        <p>
                            Equipamiento requerido para las actividades
                            programadas durante el día.
                        </p>
                    </div>

                </div>

            </div>

            <?php if ($mensajeExito): ?>

                <div class="alert alert-success equipment-alert">
                    <i class="bi bi-check-circle"></i>
                    <?= htmlspecialchars($mensajeExito) ?>
                </div>

            <?php endif; ?>

            <?php if ($mensajeError): ?>

                <div class="alert alert-danger equipment-alert">
                    <i class="bi bi-exclamation-circle"></i>
                    <?= htmlspecialchars($mensajeError) ?>
                </div>

            <?php endif; ?>

            <?php if ($errorConsulta): ?>

                <div class="alert alert-danger equipment-alert">
                    <i class="bi bi-exclamation-circle"></i>
                    No fue posible consultar el equipamiento solicitado.
                    Intente nuevamente.
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

                        <p>
                            Las actividades confirmadas con equipamiento
                            aparecerán en este listado.
                        </p>

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
                                    <th>Devolución</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($actividadesHoy as $actividad): ?>

                                    <tr>

                                        <td class="equipment-time">

                                            <i class="bi bi-clock"></i>

                                            <span>
                                                <?= formatearHora(
                                                    $actividad['hora_inicio']
                                                ) ?>
                                                -
                                                <?= formatearHora(
                                                    $actividad['hora_fin']
                                                ) ?>
                                            </span>

                                        </td>

                                        <td class="equipment-activity">

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $actividad['actividad']
                                                ) ?>
                                            </strong>

                                            <span class="equipment-status">
                                                <span
                                                    class="equipment-status-dot"
                                                ></span>
                                                Confirmada
                                            </span>

                                        </td>

                                        <td class="equipment-space">

                                            <i class="bi bi-geo-alt"></i>

                                            <?= htmlspecialchars(
                                                $actividad['espacio']
                                            ) ?>

                                        </td>

                                        <td>

                                            <div class="equipment-request-box">

                                                <?php foreach (
                                                    $actividad['equipamiento']
                                                    as $item
                                                ): ?>

                                                    <div
                                                        class="equipment-badge
                                                        <?= $item['cantidad_devuelta'] >= $item['cantidad']
                                                            ? 'equipment-badge-returned'
                                                            : '' ?>"
                                                    >

                                                        <span
                                                            class="equipment-badge-icon"
                                                        >
                                                            <i
                                                                class="bi <?= htmlspecialchars(
                                                                    iconoEquipamiento(
                                                                        $item['nombre']
                                                                    )
                                                                ) ?>"
                                                            ></i>
                                                        </span>

                                                        <span
                                                            class="equipment-badge-name"
                                                        >
                                                            <?= htmlspecialchars(
                                                                $item['nombre']
                                                            ) ?>
                                                        </span>

                                                        <strong>
                                                            × <?= $item['cantidad'] ?>
                                                        </strong>

                                                    </div>

                                                <?php endforeach; ?>

                                            </div>

                                        </td>

                                        <td class="equipment-return-cell">

                                            <div class="equipment-return-summary">

                                                <?php if ($actividad['todo_devuelto']): ?>

                                                    <span
                                                        class="equipment-return-status equipment-return-complete"
                                                    >
                                                        <i class="bi bi-check-circle-fill"></i>
                                                        Todo devuelto
                                                    </span>

                                                <?php else: ?>

                                                    <span
                                                        class="equipment-return-status equipment-return-pending"
                                                    >
                                                        <i class="bi bi-clock-history"></i>
                                                        Pendiente
                                                    </span>

                                                <?php endif; ?>

                                                <span class="equipment-return-count">
                                                    <?= $actividad['equipamiento_devuelto'] ?>
                                                    de
                                                    <?= $actividad['equipamiento_total'] ?>
                                                </span>

                                            </div>

                                            <div class="equipment-checklist">

                                                <?php foreach (
                                                    $actividad['equipamiento']
                                                    as $item
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        class="equipment-check-form"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="accion"
                                                            value="marcar_devolucion"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="id_reserva"
                                                            value="<?= $actividad['id_reserva'] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="id_equipamiento"
                                                            value="<?= $item['id_equipamiento'] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="devuelto"
                                                            value="0"
                                                            class="equipment-return-value"
                                                        >

                                                        <label
                                                            class="equipment-check-label
                                                            <?= $item['cantidad_devuelta'] >= $item['cantidad']
                                                                ? 'is-returned'
                                                                : '' ?>"
                                                        >

                                                            <input
                                                                type="checkbox"
                                                                class="equipment-check-input"
                                                                <?= $item['cantidad_devuelta'] >= $item['cantidad']
                                                                    ? 'checked'
                                                                    : '' ?>
                                                            >

                                                            <span
                                                                class="equipment-check-custom"
                                                            >
                                                                <i class="bi bi-check"></i>
                                                            </span>

                                                            <span
                                                                class="equipment-check-text"
                                                            >
                                                                <?= htmlspecialchars(
                                                                    $item['nombre']
                                                                ) ?>
                                                                ×
                                                                <?= $item['cantidad'] ?>
                                                            </span>

                                                        </label>

                                                        <button
                                                            type="submit"
                                                            class="equipment-check-submit"
                                                        >
                                                            Guardar
                                                        </button>

                                                        <?php if (
                                                            $item['fecha_devolucion']
                                                        ): ?>

                                                            <small
                                                                class="equipment-return-meta"
                                                            >
                                                                <?= formatearFecha(
                                                                    $item['fecha_devolucion']
                                                                ) ?>
                                                                <?= date(
                                                                    'H:i',
                                                                    strtotime(
                                                                        $item['fecha_devolucion']
                                                                    )
                                                                ) ?>
                                                                <?php if (
                                                                    $item['usuario_devolucion']
                                                                ): ?>
                                                                    ·
                                                                    <?= htmlspecialchars(
                                                                        $item['usuario_devolucion']
                                                                    ) ?>
                                                                <?php endif; ?>
                                                            </small>

                                                        <?php endif; ?>

                                                    </form>

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

                        <span>
                            Unidades devueltas:
                            <strong><?= $totalDevueltas ?></strong>
                        </span>

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

    document.querySelectorAll('.equipment-check-input').forEach(function (checkbox) {

        checkbox.addEventListener('change', function () {

            const form = checkbox.closest('.equipment-check-form');
            const valueInput = form.querySelector('.equipment-return-value');
            const label = checkbox.closest('.equipment-check-label');

            valueInput.value = checkbox.checked ? '1' : '0';

            if (checkbox.checked) {
                label.classList.add('is-returned');
            } else {
                label.classList.remove('is-returned');
            }
        });

    });
</script>

</body>
</html>
