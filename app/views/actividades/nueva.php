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

$tiposActividad = [];
$espacios = [];
$equipamientos = [];

try {

    $stmt = $conexion->query("
        SELECT
            id_tipo_actividad,
            nombre
        FROM tipos_actividad
        WHERE activo = 1
        ORDER BY nombre
    ");

    $tiposActividad = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conexion->query("
        SELECT
            id_espacio,
            nombre,
            capacidad
        FROM espacios
        WHERE activo = 1
        ORDER BY nombre
    ");

    $espacios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conexion->query("
        SELECT
            id_equipamiento,
            nombre,
            cantidad
        FROM equipamiento
        WHERE activo = 1
        ORDER BY id_equipamiento
    ");

    $equipamientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die('Error al cargar los datos del sistema.');
}

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$idActividadReagendar = filter_var(
    $_GET['reagendar'] ?? null,
    FILTER_VALIDATE_INT
);

$datosReagendar = null;
$equipamientoReagendar = [];

if ($idActividadReagendar) {

    try {

        $stmt = $conexion->prepare("
            SELECT
                a.id_actividad,
                a.id_tipo_actividad,
                a.nombre,
                a.descripcion,
                a.responsable,
                a.telefono_responsable,
                a.estado AS estado_actividad,
                r.id_espacio,
                r.fecha,
                r.hora_inicio,
                r.hora_fin,
                r.estado AS estado_reserva
            FROM actividades a
            INNER JOIN reservas r
                ON r.id_reserva = a.id_reserva
            WHERE a.id_actividad = :id_actividad
            AND a.estado = 'Cancelada'
            AND r.estado = 'Cancelada'
            LIMIT 1
        ");

        $stmt->execute([
            ':id_actividad' => $idActividadReagendar
        ]);

        $datosReagendar = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($datosReagendar) {

            $stmt = $conexion->prepare("
                SELECT
                    id_equipamiento,
                    cantidad
                FROM reserva_equipamiento
                WHERE id_reserva = (
                    SELECT id_reserva
                    FROM actividades
                    WHERE id_actividad = :id_actividad
                )
            ");

            $stmt->execute([
                ':id_actividad' => $idActividadReagendar
            ]);

            while ($equipo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $equipamientoReagendar[(int) $equipo['id_equipamiento']] =
                    (int) $equipo['cantidad'];
            }
        }

    } catch (PDOException $e) {

        $datosReagendar = null;
        $equipamientoReagendar = [];
    }
}

$esReagendar = $datosReagendar !== null;

$mensajesError = [
    'campos' => 'Complete todos los campos obligatorios.',
    'horario' => 'La hora de fin debe ser posterior a la hora de inicio.',
    'espacio' => 'El espacio seleccionado no está disponible para el horario indicado.',
    'equipamiento' => 'La cantidad solicitada de uno o más equipos supera la disponibilidad.',
    'tipo' => 'El tipo de actividad seleccionado no es válido.',
    'espacio_invalido' => 'El espacio seleccionado no es válido.',
    'guardar' => 'No fue posible guardar la actividad. Intente nuevamente.'
];

$mensajeError = $mensajesError[$error] ?? '';
$mensajeExito = $success === '1';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= $esReagendar ? 'Volver a agendar actividad' : 'Nueva actividad y reserva' ?> - SIGA</title>

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
                        class="sidebar-sublink active-sub"
                    >
                        <span class="submenu-dot"></span>
                        Nueva actividad
                    </a>

                    <a
                        href="#"
                        class="sidebar-sublink"
                    >
                        <span class="submenu-dot"></span>
                        Ver actividades
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


        <!--contenido-->

        <div class="activity-page">

            <div class="activity-page-header">

                <div class="activity-title-area">

                    <div class="activity-title-icon">
                        <i class="bi bi-calendar-event"></i>
                    </div>

                    <div>

                        <h1>
                            <?= $esReagendar ? 'Volver a agendar actividad' : 'Nueva actividad y reserva' ?>
                        </h1>

                        <p>
                            <?= $esReagendar
                                ? 'Los datos de la actividad cancelada fueron cargados. Revise la información y confirme la nueva reserva.'
                                : 'Complete los datos para agendar una nueva actividad. La reserva se generará automáticamente.'
                            ?>
                        </p>

                    </div>

                </div>

                <div class="activity-header-actions">

                    <a
                        href="<?= $mensajeExito
                            ? '/SIGA/app/views/actividades/actividades.php'
                            : '/SIGA/app/views/dashboard/index.php'
                        ?>"
                        class="btn-cancel"
                    >
                        <?= $mensajeExito
                            ? 'Volver a actividades'
                            : 'Cancelar'
                        ?>
                    </a>

                    <button
                        type="submit"
                        form="formActividad"
                        class="btn-save"
                    >
                        <i class="bi bi-check-lg"></i>
                        <?= $esReagendar ? 'Confirmar nueva reserva' : 'Guardar actividad' ?>
                    </button>

                </div>

            </div>


            <?php if ($mensajeExito): ?>

                <div class="alert alert-success activity-alert">

                    <i class="bi bi-check-circle"></i>

                    La actividad fue agendada correctamente.

                </div>

            <?php endif; ?>


            <?php if ($esReagendar): ?>

                <div class="alert alert-info activity-alert">

                    <i class="bi bi-arrow-repeat"></i>

                    Está creando una nueva reserva a partir de la actividad cancelada. La actividad anterior conservará su historial.

                </div>

            <?php endif; ?>


            <?php if ($mensajeError !== ''): ?>

                <div class="alert alert-danger activity-alert">

                    <i class="bi bi-exclamation-circle"></i>

                    <?= htmlspecialchars($mensajeError) ?>

                </div>

            <?php endif; ?>


            <form
                id="formActividad"
                method="POST"
                action="/SIGA/app/controllers/ActividadController.php"
            >

                <input
                    type="hidden"
                    name="accion"
                    value="crear"
                >

                <?php if ($esReagendar): ?>

                    <input
                        type="hidden"
                        name="id_actividad_origen"
                        value="<?= (int) $datosReagendar['id_actividad'] ?>"
                    >

                <?php endif; ?>


                <!--datos de actividad-->

                <section class="activity-card">

                    <div class="activity-card-header">

                        <span>1. Datos de la actividad</span>

                    </div>

                    <div class="activity-card-body">

                        <div class="activity-form-grid">

                            <div class="form-group">

                                <label for="nombre">
                                    Nombre de la actividad
                                    <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    id="nombre"
                                    name="nombre"
                                    class="form-control"
                                    maxlength="150"
                                    placeholder="Ingrese el nombre de la actividad"
                                    value="<?= htmlspecialchars($datosReagendar['nombre'] ?? '') ?>"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="responsable">
                                    Responsable de la actividad
                                    <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    id="responsable"
                                    name="responsable"
                                    class="form-control"
                                    maxlength="100"
                                    placeholder="Ingrese nombre y apellido del responsable"
                                    value="<?= htmlspecialchars($datosReagendar['responsable'] ?? '') ?>"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="id_tipo_actividad">
                                    Tipo de actividad
                                    <span>*</span>
                                </label>

                                <select
                                    id="id_tipo_actividad"
                                    name="id_tipo_actividad"
                                    class="form-select"
                                    required
                                >

                                    <option value="">
                                        Seleccione un tipo de actividad
                                    </option>

                                    <?php foreach ($tiposActividad as $tipo): ?>

                                        <option
                                            value="<?= (int) $tipo['id_tipo_actividad'] ?>"
                                            <?= isset($datosReagendar['id_tipo_actividad']) && (int) $datosReagendar['id_tipo_actividad'] === (int) $tipo['id_tipo_actividad'] ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($tipo['nombre']) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="form-group">

                                <label for="telefono_responsable">
                                    Teléfono del responsable
                                    <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    id="telefono_responsable"
                                    name="telefono_responsable"
                                    class="form-control"
                                    maxlength="20"
                                    placeholder="Ingrese número de teléfono de contacto"
                                    value="<?= htmlspecialchars($datosReagendar['telefono_responsable'] ?? '') ?>"
                                    required
                                >

                                <div class="form-help">
                                    Se utilizará para el recordatorio por WhatsApp.
                                </div>

                            </div>


                            <div class="form-group">

                                <label for="descripcion">
                                    Descripción / Observaciones
                                </label>

                                <textarea
                                    id="descripcion"
                                    name="descripcion"
                                    class="form-control"
                                    maxlength="250"
                                    rows="3"
                                    placeholder="Observaciones adicionales (opcional)"
                                ><?= htmlspecialchars($datosReagendar['descripcion'] ?? '') ?></textarea>

                                <div class="character-counter">
                                    Máximo 250 caracteres
                                </div>

                            </div>


                            <div class="form-group">

                                <label for="estado">
                                    Estado de la actividad
                                </label>

                                <select
                                    id="estado"
                                    class="form-select"
                                    disabled
                                >
                                    <option selected>
                                        Confirmada
                                    </option>
                                </select>

                                <input
                                    type="hidden"
                                    name="estado"
                                    value="Confirmada"
                                >

                                <div class="form-help">
                                    El estado inicial es Confirmada.
                                </div>

                            </div>

                        </div>

                    </div>

                </section>


                <div class="activity-lower-grid">


                    <div class="activity-left-column">


                        <!--datos reserva-->

                        <section class="activity-card">

                            <div class="activity-card-header">

                                <span>2. Datos de la reserva</span>

                            </div>

                            <div class="activity-card-body">

                                <div class="reservation-grid">

                                    <div class="form-group">

                                        <label for="id_espacio">
                                            Espacio
                                            <span>*</span>
                                        </label>

                                        <select
                                            id="id_espacio"
                                            name="id_espacio"
                                            class="form-select"
                                            required
                                        >

                                            <option value="">
                                                Seleccione un espacio
                                            </option>

                                            <?php foreach ($espacios as $espacio): ?>

                                                <option
                                                    value="<?= (int) $espacio['id_espacio'] ?>"
                                                    <?= isset($datosReagendar['id_espacio']) && (int) $datosReagendar['id_espacio'] === (int) $espacio['id_espacio'] ? 'selected' : '' ?>
                                                >
                                                    <?= htmlspecialchars($espacio['nombre']) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>


                                    <div class="form-group">

                                        <label for="fecha">
                                            Fecha
                                            <span>*</span>
                                        </label>

                                        <div class="input-icon">

                                            <i class="bi bi-calendar3"></i>

                                            <input
                                                type="date"
                                                id="fecha"
                                                name="fecha"
                                                class="form-control"
                                                value="<?= htmlspecialchars($datosReagendar['fecha'] ?? '') ?>"
                                                required
                                            >

                                        </div>

                                    </div>


                                    <div class="form-group">

                                        <label for="hora_inicio">
                                            Hora de inicio
                                            <span>*</span>
                                        </label>

                                        <div class="input-icon">

                                            <i class="bi bi-clock"></i>

                                            <input
                                                type="time"
                                                id="hora_inicio"
                                                name="hora_inicio"
                                                class="form-control"
                                                value="<?= htmlspecialchars(isset($datosReagendar['hora_inicio']) ? substr($datosReagendar['hora_inicio'], 0, 5) : '') ?>"
                                                required
                                            >

                                        </div>

                                    </div>


                                    <div class="form-group">

                                        <label for="hora_fin">
                                            Hora de fin
                                            <span>*</span>
                                        </label>

                                        <div class="input-icon">

                                            <i class="bi bi-clock"></i>

                                            <input
                                                type="time"
                                                id="hora_fin"
                                                name="hora_fin"
                                                class="form-control"
                                                value="<?= htmlspecialchars(isset($datosReagendar['hora_fin']) ? substr($datosReagendar['hora_fin'], 0, 5) : '') ?>"
                                                required
                                            >

                                        </div>

                                    </div>

                                </div>


                                <div
                                    id="horarioInfo"
                                    class="reservation-info"
                                >
                                    <i class="bi bi-info-circle"></i>
                                    La hora de fin debe ser posterior a la hora de inicio.
                                </div>

                            </div>

                        </section>


                        <!--equipamiento-->

                        <section class="activity-card">

                            <div class="activity-card-header">

                                <span>3. Equipamiento requerido</span>

                            </div>

                            <div class="activity-card-body equipment-body">

                                <div class="equipment-layout">

                                    <div class="equipment-table-wrapper">

                                        <table class="equipment-table">

                                            <thead>

                                                <tr>

                                                    <th>
                                                        Equipamiento
                                                    </th>

                                                    <th>
                                                        Disponible
                                                    </th>

                                                    <th>
                                                        Cantidad solicitada
                                                    </th>

                                                </tr>

                                            </thead>

                                            <tbody>

                                                <?php foreach ($equipamientos as $equipo): ?>

                                                    <tr>

                                                        <td>

                                                            <div class="equipment-name">

                                                                <i class="bi bi-display"></i>

                                                                <?= htmlspecialchars($equipo['nombre']) ?>

                                                            </div>

                                                        </td>

                                                        <td>

                                                            <span class="equipment-available">
                                                                <?= (int) $equipo['cantidad'] ?>
                                                            </span>

                                                            <input
                                                                type="hidden"
                                                                class="equipment-total"
                                                                data-equipment-id="<?= (int) $equipo['id_equipamiento'] ?>"
                                                                value="<?= (int) $equipo['cantidad'] ?>"
                                                            >

                                                        </td>

                                                        <td>

                                                            <div class="quantity-control">

                                                                <button
                                                                    type="button"
                                                                    class="quantity-btn quantity-minus"
                                                                    data-id="<?= (int) $equipo['id_equipamiento'] ?>"
                                                                >
                                                                    −
                                                                </button>

                                                                <input
                                                                    type="number"
                                                                    name="equipamiento[<?= (int) $equipo['id_equipamiento'] ?>]"
                                                                    value="<?= (int) ($equipamientoReagendar[(int) $equipo['id_equipamiento']] ?? 0) ?>"
                                                                    min="0"
                                                                    max="<?= (int) $equipo['cantidad'] ?>"
                                                                    class="quantity-input"
                                                                    data-id="<?= (int) $equipo['id_equipamiento'] ?>"
                                                                >

                                                                <button
                                                                    type="button"
                                                                    class="quantity-btn quantity-plus"
                                                                    data-id="<?= (int) $equipo['id_equipamiento'] ?>"
                                                                >
                                                                    +
                                                                </button>

                                                            </div>

                                                        </td>

                                                    </tr>

                                                <?php endforeach; ?>

                                            </tbody>

                                        </table>

                                    </div>


                                    <div class="equipment-information">

                                        <div class="equipment-info-box">

                                            <div class="equipment-info-title">

                                                <i class="bi bi-clipboard-check"></i>

                                                Información

                                            </div>

                                            <p>
                                                La disponibilidad corresponde a la
                                                cantidad total disponible para la
                                                fecha y horario seleccionados.
                                            </p>

                                            <p>
                                                Puede solicitar varios equipos.
                                            </p>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </section>

                    </div>


                    <!--resumen-->

                    <aside class="reservation-summary">

                        <div class="summary-header">
                            Resumen de la reserva
                        </div>


                        <div class="summary-item">

                            <div class="summary-icon">
                                <i class="bi bi-calendar3"></i>
                            </div>

                            <div>

                                <strong>Fecha</strong>

                                <span id="summaryFecha">
                                    Sin seleccionar
                                </span>

                            </div>

                        </div>


                        <div class="summary-item">

                            <div class="summary-icon">
                                <i class="bi bi-clock"></i>
                            </div>

                            <div>

                                <strong>Horario</strong>

                                <span id="summaryHorario">
                                    Sin seleccionar
                                </span>

                            </div>

                        </div>


                        <div class="summary-item">

                            <div class="summary-icon">
                                <i class="bi bi-geo-alt"></i>
                            </div>

                            <div>

                                <strong>Espacio</strong>

                                <span id="summaryEspacio">
                                    Sin seleccionar
                                </span>

                            </div>

                        </div>


                        <div class="summary-divider"></div>


                        <div class="summary-equipment-title">
                            Equipos solicitados
                        </div>

                        <div
                            id="summaryEquipment"
                            class="summary-equipment"
                        >
                            Ninguno
                        </div>


                        <div class="summary-reminder">

                            <div class="reminder-title">

                                <i class="bi bi-clock-history"></i>

                                Recordatorio

                            </div>

                            <p>
                                El responsable recibirá un recordatorio
                                por WhatsApp 24 horas antes de la actividad.
                            </p>

                        </div>

                    </aside>

                </div>


                <div class="form-required-note">

                    Los campos marcados con
                    <span>*</span>
                    son obligatorios.

                </div>

            </form>

        </div>

    </main>

</div>


<script>

const fecha = document.getElementById('fecha');
const horaInicio = document.getElementById('hora_inicio');
const horaFin = document.getElementById('hora_fin');
const espacio = document.getElementById('id_espacio');

const summaryFecha = document.getElementById('summaryFecha');
const summaryHorario = document.getElementById('summaryHorario');
const summaryEspacio = document.getElementById('summaryEspacio');
const summaryEquipment = document.getElementById('summaryEquipment');
const horarioInfo = document.getElementById('horarioInfo');


function actualizarFecha() {

    if (!fecha.value) {

        summaryFecha.textContent = 'Sin seleccionar';
        return;

    }

    const partes = fecha.value.split('-');

    if (partes.length === 3) {

        summaryFecha.textContent =
            partes[2] + '/' +
            partes[1] + '/' +
            partes[0];

    }

}


function actualizarHorario() {

    if (!horaInicio.value || !horaFin.value) {

        summaryHorario.textContent = 'Sin seleccionar';

        horarioInfo.classList.remove('reservation-error');

        return;

    }

    summaryHorario.textContent =
        horaInicio.value + ' - ' +
        horaFin.value;

    if (horaFin.value <= horaInicio.value) {

        horarioInfo.classList.add('reservation-error');

        horarioInfo.innerHTML =
            '<i class="bi bi-exclamation-circle"></i>' +
            'La hora de fin debe ser posterior a la hora de inicio.';

    } else {

        horarioInfo.classList.remove('reservation-error');

        horarioInfo.innerHTML =
            '<i class="bi bi-info-circle"></i>' +
            'La hora de fin debe ser posterior a la hora de inicio.';

    }

}


function actualizarEspacio() {

    if (!espacio.value) {

        summaryEspacio.textContent = 'Sin seleccionar';

        return;

    }

    const opcion =
        espacio.options[espacio.selectedIndex];

    summaryEspacio.textContent =
        opcion.textContent;

}


function actualizarEquipamiento() {

    const cantidades =
        document.querySelectorAll('.quantity-input');

    const seleccionados = [];

    cantidades.forEach(function(input) {

        const cantidad =
            parseInt(input.value, 10) || 0;

        if (cantidad > 0) {

            const fila =
                input.closest('tr');

            const nombre =
                fila.querySelector('.equipment-name')
                    .textContent
                    .trim();

            seleccionados.push(
                nombre + ' (' + cantidad + ')'
            );

        }

    });

    if (seleccionados.length === 0) {

        summaryEquipment.textContent =
            'Ninguno';

    } else {

        summaryEquipment.innerHTML =
            seleccionados
                .map(function(item) {
                    return '<div>' + item + '</div>';
                })
                .join('');

    }

}


document.querySelectorAll('.quantity-minus')
    .forEach(function(button) {

        button.addEventListener('click', function() {

            const id =
                this.dataset.id;

            const input =
                document.querySelector(
                    '.quantity-input[data-id="' + id + '"]'
                );

            let valor =
                parseInt(input.value, 10) || 0;

            if (valor > 0) {

                valor--;

                input.value = valor;

                actualizarEquipamiento();

            }

        });

    });


document.querySelectorAll('.quantity-plus')
    .forEach(function(button) {

        button.addEventListener('click', function() {

            const id =
                this.dataset.id;

            const input =
                document.querySelector(
                    '.quantity-input[data-id="' + id + '"]'
                );

            const max =
                parseInt(input.max, 10);

            let valor =
                parseInt(input.value, 10) || 0;

            if (valor < max) {

                valor++;

                input.value = valor;

                actualizarEquipamiento();

            }

        });

    });


document.querySelectorAll('.quantity-input')
    .forEach(function(input) {

        input.addEventListener('input', function() {

            let valor =
                parseInt(this.value, 10) || 0;

            const max =
                parseInt(this.max, 10);

            if (valor < 0) {
                valor = 0;
            }

            if (valor > max) {
                valor = max;
            }

            this.value = valor;

            actualizarEquipamiento();

        });

    });


fecha.addEventListener(
    'change',
    actualizarFecha
);

horaInicio.addEventListener(
    'change',
    actualizarHorario
);

horaFin.addEventListener(
    'change',
    actualizarHorario
);

espacio.addEventListener(
    'change',
    actualizarEspacio
);


document.getElementById('formActividad')
    .addEventListener('submit', function(event) {

        if (
            horaInicio.value &&
            horaFin.value &&
            horaFin.value <= horaInicio.value
        ) {

            event.preventDefault();

            horarioInfo.classList.add(
                'reservation-error'
            );

            horarioInfo.innerHTML =
                '<i class="bi bi-exclamation-circle"></i>' +
                'La hora de fin debe ser posterior a la hora de inicio.';

            horaFin.focus();

        }

    });


actualizarFecha();
actualizarHorario();
actualizarEspacio();
actualizarEquipamiento();

</script>

</body>

</html>