<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

if (
    !isset($_SESSION['usuario']) ||
    !is_array($_SESSION['usuario']) ||
    empty($_SESSION['usuario']['id_usuario'])
) {
    header('Location: /SIGA/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /SIGA/app/views/actividades/actividades.php');
    exit;
}

$accion = $_POST['accion'] ?? '';

$nombre = trim($_POST['nombre'] ?? '');
$responsable = trim($_POST['responsable'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$telefono = trim($_POST['telefono_responsable'] ?? '');

$idTipoActividad = filter_var(
    $_POST['id_tipo_actividad'] ?? null,
    FILTER_VALIDATE_INT
);

$idEspacio = filter_var(
    $_POST['id_espacio'] ?? null,
    FILTER_VALIDATE_INT
);

$fecha = $_POST['fecha'] ?? '';
$horaInicio = $_POST['hora_inicio'] ?? '';
$horaFin = $_POST['hora_fin'] ?? '';

$horaInicio = substr($horaInicio, 0, 5);
$horaFin = substr($horaFin, 0, 5);

$equipamientoSolicitado = $_POST['equipamiento'] ?? [];

$idActividad = filter_var(
    $_POST['id_actividad'] ?? null,
    FILTER_VALIDATE_INT
);

$idUsuario = (int) $_SESSION['usuario']['id_usuario'];

$idActividadOrigen = filter_var(
    $_POST['id_actividad_origen'] ?? null,
    FILTER_VALIDATE_INT
);

if ($accion === 'cancelar') {

    if (!$idActividad) {
        header(
            'Location: /SIGA/app/views/actividades/actividades.php?error=actividad'
        );
        exit;
    }

    $database = new Database();
    $conexion = $database->conectar();

    try {

        $conexion->beginTransaction();

        /*obtener actividad y reserva actual*/

        $stmt = $conexion->prepare("
            SELECT
                a.id_actividad,
                a.id_reserva,
                a.nombre,
                a.estado AS estado_actividad,
                r.estado AS estado_reserva
            FROM actividades a
            INNER JOIN reservas r
                ON r.id_reserva = a.id_reserva
            WHERE a.id_actividad = :id_actividad
            FOR UPDATE
        ");

        $stmt->execute([
            ':id_actividad' => $idActividad
        ]);

        $actividadCancelar = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$actividadCancelar) {
            $conexion->rollBack();
            header(
                'Location: /SIGA/app/views/actividades/actividades.php?error=actividad'
            );
            exit;
        }

        $idReservaCancelar = (int) $actividadCancelar['id_reserva'];

        if (
            $actividadCancelar['estado_actividad'] === 'Cancelada' &&
            $actividadCancelar['estado_reserva'] === 'Cancelada'
        ) {
            $conexion->rollBack();
            header(
                'Location: /SIGA/app/views/actividades/detalle.php?id=' .
                $idActividad .
                '&error=ya_cancelada'
            );
            exit;
        }

        /*cancelar reserva*/

        $stmt = $conexion->prepare("
            UPDATE reservas
            SET estado = 'Cancelada'
            WHERE id_reserva = :id_reserva
        "
        );

        $stmt->execute([
            ':id_reserva' => $idReservaCancelar
        ]);

        /*cancelar actividad*/

        $stmt = $conexion->prepare("
            UPDATE actividades
            SET estado = 'Cancelada'
            WHERE id_actividad = :id_actividad
        "
        );

        $stmt->execute([
            ':id_actividad' => $idActividad
        ]);

        /*registrar auditoria de reserva*/

        $stmt = $conexion->prepare("
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
                'CANCELACION',
                :detalle
            )
        "
        );

        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':id_registro' => $idReservaCancelar,
            ':detalle' =>
                'Cancelación de reserva asociada a la actividad ' .
                $idActividad .
                '. El espacio y el equipamiento quedan disponibles para nuevas reservas.'
        ]);

        /*registrar auditoria de actividad*/

        $stmt = $conexion->prepare("
            INSERT INTO auditoria (
                id_usuario,
                entidad,
                id_registro,
                accion,
                detalle
            )
            VALUES (
                :id_usuario,
                'actividades',
                :id_registro,
                'CANCELACION',
                :detalle
            )
        "
        );

        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':id_registro' => $idActividad,
            ':detalle' =>
                'Cancelación de actividad y reserva asociada. Reserva: ' .
                $idReservaCancelar .
                '. El espacio y el equipamiento quedan disponibles para nuevas reservas.'
        ]);

        $conexion->commit();

        header(
            'Location: /SIGA/app/views/actividades/detalle.php?id=' .
            $idActividad .
            '&success=cancelado'
        );
        exit;

    } catch (PDOException $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        header(
            'Location: /SIGA/app/views/actividades/detalle.php?id=' .
            $idActividad .
            '&error=cancelar'
        );
        exit;
    }
}

if (
    $nombre === '' ||
    $responsable === '' ||
    $telefono === '' ||
    !$idTipoActividad ||
    !$idEspacio ||
    $fecha === '' ||
    $horaInicio === '' ||
    $horaFin === ''
) {
    if ($accion === 'modificar' && $idActividad) {
        header(
            'Location: /SIGA/app/views/actividades/editar.php?id=' .
            $idActividad .
            '&error=campos'
        );
    } else {
        header(
            'Location: /SIGA/app/views/actividades/nueva.php?error=campos'
        );
    }

    exit;
}

if ($horaFin <= $horaInicio) {
    if ($accion === 'modificar' && $idActividad) {
        header(
            'Location: /SIGA/app/views/actividades/editar.php?id=' .
            $idActividad .
            '&error=horario'
        );
    } else {
        header(
            'Location: /SIGA/app/views/actividades/nueva.php?error=horario'
        );
    }

    exit;
}

if (mb_strlen($descripcion) > 250) {
    $descripcion = mb_substr(
        $descripcion,
        0,
        250
    );
}

$equipamiento = [];

if (is_array($equipamientoSolicitado)) {
    foreach ($equipamientoSolicitado as $idEquipo => $cantidad) {
        $idEquipo = filter_var(
            $idEquipo,
            FILTER_VALIDATE_INT
        );

        $cantidad = filter_var(
            $cantidad,
            FILTER_VALIDATE_INT
        );

        if (
            $idEquipo &&
            $cantidad &&
            $cantidad > 0
        ) {
            $equipamiento[$idEquipo] = $cantidad;
        }
    }
}

$database = new Database();
$conexion = $database->conectar();

function redireccionarErrorActividad($accion, $idActividad, $error)
{
    if ($accion === 'modificar' && $idActividad) {
        header(
            'Location: /SIGA/app/views/actividades/editar.php?id=' .
            $idActividad .
            '&error=' .
            $error
        );
    } else {
        header(
            'Location: /SIGA/app/views/actividades/nueva.php?error=' .
            $error
        );
    }

    exit;
}

function formatoAuditoria($valor)
{
    if ($valor === null || $valor === '') {
        return 'vacío';
    }

    return (string) $valor;
}

try {

    if ($accion === 'crear') {

        $conexion->beginTransaction();

        /*validar tipo de actividad*/

        $stmt = $conexion->prepare("
            SELECT id_tipo_actividad
            FROM tipos_actividad
            WHERE id_tipo_actividad = :id
            AND activo = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $idTipoActividad
        ]);

        if (!$stmt->fetchColumn()) {
            $conexion->rollBack();
            redireccionarErrorActividad(
                'crear',
                null,
                'tipo'
            );
        }

        /*validar espacio*/

        $stmt = $conexion->prepare("
            SELECT id_espacio
            FROM espacios
            WHERE id_espacio = :id
            AND activo = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $idEspacio
        ]);

        if (!$stmt->fetchColumn()) {
            $conexion->rollBack();
            redireccionarErrorActividad(
                'crear',
                null,
                'espacio_invalido'
            );
        }

        /*validar disponibilidad del espacio*/

        $stmt = $conexion->prepare("
            SELECT COUNT(*)
            FROM reservas
            WHERE id_espacio = :id_espacio
            AND fecha = :fecha
            AND estado = 'Confirmada'
            AND hora_inicio < :hora_fin
            AND hora_fin > :hora_inicio
        ");

        $stmt->execute([
            ':id_espacio' => $idEspacio,
            ':fecha' => $fecha,
            ':hora_inicio' => $horaInicio,
            ':hora_fin' => $horaFin
        ]);

        $reservasSuperpuestas = (int) $stmt->fetchColumn();

        if ($reservasSuperpuestas > 0) {
            $conexion->rollBack();
            redireccionarErrorActividad(
                'crear',
                null,
                'espacio'
            );
        }

        /*validar equipamiento*/

        foreach ($equipamiento as $idEquipo => $cantidadSolicitada) {

            $stmt = $conexion->prepare("
                SELECT
                    cantidad,
                    nombre
                FROM equipamiento
                WHERE id_equipamiento = :id
                AND activo = 1
                FOR UPDATE
            ");

            $stmt->execute([
                ':id' => $idEquipo
            ]);

            $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$equipo) {
                $conexion->rollBack();
                redireccionarErrorActividad(
                    'crear',
                    null,
                    'equipamiento'
                );
            }

            $cantidadTotal = (int) $equipo['cantidad'];

            $stmt = $conexion->prepare("
                SELECT
                    COALESCE(SUM(re.cantidad), 0)
                FROM reserva_equipamiento re
                INNER JOIN reservas r
                    ON r.id_reserva = re.id_reserva
                WHERE re.id_equipamiento = :id_equipo
                AND r.fecha = :fecha
                AND r.estado = 'Confirmada'
                AND r.hora_inicio < :hora_fin
                AND r.hora_fin > :hora_inicio
            ");

            $stmt->execute([
                ':id_equipo' => $idEquipo,
                ':fecha' => $fecha,
                ':hora_inicio' => $horaInicio,
                ':hora_fin' => $horaFin
            ]);

            $cantidadReservada = (int) $stmt->fetchColumn();

            $cantidadDisponible =
                $cantidadTotal - $cantidadReservada;

            if ($cantidadSolicitada > $cantidadDisponible) {
                $conexion->rollBack();
                redireccionarErrorActividad(
                    'crear',
                    null,
                    'equipamiento'
                );
            }
        }

        /*crear reserva*/

        $stmt = $conexion->prepare("
            INSERT INTO reservas (
                id_usuario,
                id_espacio,
                fecha,
                hora_inicio,
                hora_fin,
                estado,
                observaciones
            )
            VALUES (
                :id_usuario,
                :id_espacio,
                :fecha,
                :hora_inicio,
                :hora_fin,
                'Confirmada',
                NULL
            )
        ");

        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':id_espacio' => $idEspacio,
            ':fecha' => $fecha,
            ':hora_inicio' => $horaInicio,
            ':hora_fin' => $horaFin
        ]);

        $idReserva = (int) $conexion->lastInsertId();

        /*crear actividad*/

        $stmt = $conexion->prepare("
            INSERT INTO actividades (
                id_reserva,
                id_tipo_actividad,
                nombre,
                descripcion,
                responsable,
                telefono_responsable,
                estado
            )
            VALUES (
                :id_reserva,
                :id_tipo_actividad,
                :nombre,
                :descripcion,
                :responsable,
                :telefono_responsable,
                'Confirmada'
            )
        ");

        $stmt->execute([
            ':id_reserva' => $idReserva,
            ':id_tipo_actividad' => $idTipoActividad,
            ':nombre' => $nombre,
            ':descripcion' =>
                $descripcion !== ''
                    ? $descripcion
                    : null,
            ':responsable' => $responsable,
            ':telefono_responsable' => $telefono
        ]);

        $idActividad = (int) $conexion->lastInsertId();

        /*guardar equipamiento*/

        if (!empty($equipamiento)) {

            $stmt = $conexion->prepare("
                INSERT INTO reserva_equipamiento (
                    id_reserva,
                    id_equipamiento,
                    cantidad
                )
                VALUES (
                    :id_reserva,
                    :id_equipamiento,
                    :cantidad
                )
            ");

            foreach (
                $equipamiento
                as $idEquipo => $cantidad
            ) {
                $stmt->execute([
                    ':id_reserva' => $idReserva,
                    ':id_equipamiento' => $idEquipo,
                    ':cantidad' => $cantidad
                ]);
            }
        }

        /*registrar auditoria de reserva*/

        $stmt = $conexion->prepare("
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
                'CREACION',
                :detalle
            )
        ");

        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':id_registro' => $idReserva,
            ':detalle' =>
                'Creación de reserva asociada a la actividad ' .
                $idActividad
        ]);

        /*registrar auditoria de actividad*/

        $stmt = $conexion->prepare("
            INSERT INTO auditoria (
                id_usuario,
                entidad,
                id_registro,
                accion,
                detalle
            )
            VALUES (
                :id_usuario,
                'actividades',
                :id_registro,
                'CREACION',
                :detalle
            )
        ");

        $detalleCreacionActividad =
            'Creación de actividad y reserva asociada. Reserva: ' .
            $idReserva;

        if ($idActividadOrigen) {
            $detalleCreacionActividad .=
                '. Nueva actividad generada a partir de la actividad cancelada ' .
                $idActividadOrigen;
        }

        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':id_registro' => $idActividad,
            ':detalle' => $detalleCreacionActividad
        ]);

        $conexion->commit();

        header(
            'Location: /SIGA/app/views/actividades/nueva.php?success=1'
        );

        exit;
    }

    if ($accion === 'modificar') {

        if (!$idActividad) {
            header(
                'Location: /SIGA/app/views/actividades/actividades.php?error=actividad'
            );
            exit;
        }

        $conexion->beginTransaction();

        /*obtener actividad y reserva actual*/

        $stmt = $conexion->prepare("
            SELECT
                a.id_actividad,
                a.id_reserva,
                a.id_tipo_actividad,
                a.nombre,
                a.descripcion,
                a.responsable,
                a.telefono_responsable,
                a.estado AS estado_actividad,
                r.id_usuario,
                r.id_espacio,
                r.fecha,
                r.hora_inicio,
                r.hora_fin,
                r.estado AS estado_reserva
            FROM actividades a
            INNER JOIN reservas r
                ON r.id_reserva = a.id_reserva
            WHERE a.id_actividad = :id_actividad
            FOR UPDATE
        ");

        $stmt->execute([
            ':id_actividad' => $idActividad
        ]);

        $actual = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$actual) {
            $conexion->rollBack();

            header(
                'Location: /SIGA/app/views/actividades/actividades.php?error=actividad'
            );

            exit;
        }

        $idReserva = (int) $actual['id_reserva'];

        /*obtener equipamiento actual*/

        $stmt = $conexion->prepare("
            SELECT
                re.id_equipamiento,
                re.cantidad,
                e.nombre
            FROM reserva_equipamiento re
            INNER JOIN equipamiento e
                ON e.id_equipamiento = re.id_equipamiento
            WHERE re.id_reserva = :id_reserva
            ORDER BY e.nombre ASC
        ");

        $stmt->execute([
            ':id_reserva' => $idReserva
        ]);

        $equipamientoActual = [];

        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $equipamientoActual[
                (int) $fila['id_equipamiento']
            ] = [
                'cantidad' => (int) $fila['cantidad'],
                'nombre' => $fila['nombre']
            ];
        }

        /*validar tipo de actividad*/

        $stmt = $conexion->prepare("
            SELECT id_tipo_actividad, nombre
            FROM tipos_actividad
            WHERE id_tipo_actividad = :id
            AND activo = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $idTipoActividad
        ]);

        $nuevoTipo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$nuevoTipo) {
            $conexion->rollBack();
            redireccionarErrorActividad(
                'modificar',
                $idActividad,
                'tipo'
            );
        }

        /*validar espacio*/

        $stmt = $conexion->prepare("
            SELECT id_espacio, nombre
            FROM espacios
            WHERE id_espacio = :id
            AND activo = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $idEspacio
        ]);

        $nuevoEspacio = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$nuevoEspacio) {
            $conexion->rollBack();
            redireccionarErrorActividad(
                'modificar',
                $idActividad,
                'espacio_invalido'
            );
        }

        /*validar disponibilidad del espacio excluyendo la reserva actual*/

        $stmt = $conexion->prepare("
            SELECT COUNT(*)
            FROM reservas
            WHERE id_espacio = :id_espacio
            AND fecha = :fecha
            AND estado = 'Confirmada'
            AND id_reserva <> :id_reserva
            AND hora_inicio < :hora_fin
            AND hora_fin > :hora_inicio
        ");

        $stmt->execute([
            ':id_espacio' => $idEspacio,
            ':fecha' => $fecha,
            ':id_reserva' => $idReserva,
            ':hora_inicio' => $horaInicio,
            ':hora_fin' => $horaFin
        ]);

        $reservasSuperpuestas = (int) $stmt->fetchColumn();

        if ($reservasSuperpuestas > 0) {
            $conexion->rollBack();
            redireccionarErrorActividad(
                'modificar',
                $idActividad,
                'espacio'
            );
        }

        /*validar equipamiento excluyendo la reserva actual*/

        foreach ($equipamiento as $idEquipo => $cantidadSolicitada) {

            $stmt = $conexion->prepare("
                SELECT
                    cantidad,
                    nombre
                FROM equipamiento
                WHERE id_equipamiento = :id
                AND activo = 1
                FOR UPDATE
            ");

            $stmt->execute([
                ':id' => $idEquipo
            ]);

            $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$equipo) {
                $conexion->rollBack();
                redireccionarErrorActividad(
                    'modificar',
                    $idActividad,
                    'equipamiento'
                );
            }

            $cantidadTotal = (int) $equipo['cantidad'];

            $stmt = $conexion->prepare("
                SELECT
                    COALESCE(SUM(re.cantidad), 0)
                FROM reserva_equipamiento re
                INNER JOIN reservas r
                    ON r.id_reserva = re.id_reserva
                WHERE re.id_equipamiento = :id_equipo
                AND r.fecha = :fecha
                AND r.estado = 'Confirmada'
                AND r.id_reserva <> :id_reserva
                AND r.hora_inicio < :hora_fin
                AND r.hora_fin > :hora_inicio
            ");

            $stmt->execute([
                ':id_equipo' => $idEquipo,
                ':fecha' => $fecha,
                ':id_reserva' => $idReserva,
                ':hora_inicio' => $horaInicio,
                ':hora_fin' => $horaFin
            ]);

            $cantidadReservada = (int) $stmt->fetchColumn();

            $cantidadDisponible =
                $cantidadTotal - $cantidadReservada;

            if ($cantidadSolicitada > $cantidadDisponible) {
                $conexion->rollBack();
                redireccionarErrorActividad(
                    'modificar',
                    $idActividad,
                    'equipamiento'
                );
            }
        }

        /*obtener nombres actuales de tipo y espacio*/

        $stmt = $conexion->prepare("
            SELECT nombre
            FROM tipos_actividad
            WHERE id_tipo_actividad = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $actual['id_tipo_actividad']
        ]);

        $tipoActualNombre = $stmt->fetchColumn();

        $stmt = $conexion->prepare("
            SELECT nombre
            FROM espacios
            WHERE id_espacio = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $actual['id_espacio']
        ]);

        $espacioActualNombre = $stmt->fetchColumn();

        /*preparar diferencias de actividad*/

        $cambiosActividad = [];

        if (
            (string) $actual['nombre'] !==
            (string) $nombre
        ) {
            $cambiosActividad[] =
                'Nombre: "' .
                formatoAuditoria($actual['nombre']) .
                '" → "' .
                formatoAuditoria($nombre) .
                '"';
        }

        if (
            (int) $actual['id_tipo_actividad'] !==
            (int) $idTipoActividad
        ) {
            $cambiosActividad[] =
                'Tipo de actividad: "' .
                formatoAuditoria($tipoActualNombre) .
                '" → "' .
                formatoAuditoria($nuevoTipo['nombre']) .
                '"';
        }

        if (
            (string) ($actual['descripcion'] ?? '') !==
            (string) $descripcion
        ) {
            $cambiosActividad[] =
                'Descripción: "' .
                formatoAuditoria($actual['descripcion']) .
                '" → "' .
                formatoAuditoria($descripcion) .
                '"';
        }

        if (
            (string) $actual['responsable'] !==
            (string) $responsable
        ) {
            $cambiosActividad[] =
                'Responsable: "' .
                formatoAuditoria($actual['responsable']) .
                '" → "' .
                formatoAuditoria($responsable) .
                '"';
        }

        if (
            (string) $actual['telefono_responsable'] !==
            (string) $telefono
        ) {
            $cambiosActividad[] =
                'Teléfono: "' .
                formatoAuditoria($actual['telefono_responsable']) .
                '" → "' .
                formatoAuditoria($telefono) .
                '"';
        }

        /*preparar diferencias de reserva*/

        $cambiosReserva = [];

        if (
            (int) $actual['id_espacio'] !==
            (int) $idEspacio
        ) {
            $cambiosReserva[] =
                'Espacio: "' .
                formatoAuditoria($espacioActualNombre) .
                '" → "' .
                formatoAuditoria($nuevoEspacio['nombre']) .
                '"';
        }

        if (
            (string) $actual['fecha'] !==
            (string) $fecha
        ) {
            $cambiosReserva[] =
                'Fecha: "' .
                formatoAuditoria($actual['fecha']) .
                '" → "' .
                formatoAuditoria($fecha) .
                '"';
        }

        if (
            substr((string) $actual['hora_inicio'], 0, 5) !==
            (string) $horaInicio
        ) {
            $cambiosReserva[] =
                'Hora de inicio: "' .
                formatoAuditoria(substr((string) $actual['hora_inicio'], 0, 5)) .
                '" → "' .
                formatoAuditoria($horaInicio) .
                '"';
        }

        if (
            substr((string) $actual['hora_fin'], 0, 5) !==
            (string) $horaFin
        ) {
            $cambiosReserva[] =
                'Hora de fin: "' .
                formatoAuditoria(substr((string) $actual['hora_fin'], 0, 5)) .
                '" → "' .
                formatoAuditoria($horaFin) .
                '"';
        }

        /*preparar diferencias de equipamiento*/

        $cambiosEquipamiento = [];

        $idsEquipamiento = array_unique(
            array_merge(
                array_keys($equipamientoActual),
                array_keys($equipamiento)
            )
        );

        foreach ($idsEquipamiento as $idEquipo) {

            $cantidadAnterior =
                $equipamientoActual[$idEquipo]['cantidad'] ?? 0;

            $cantidadNueva =
                $equipamiento[$idEquipo] ?? 0;

            if ($cantidadAnterior === $cantidadNueva) {
                continue;
            }

            $nombreEquipo =
                $equipamientoActual[$idEquipo]['nombre'] ??
                null;

            if ($nombreEquipo === null) {
                $stmt = $conexion->prepare("
                    SELECT nombre
                    FROM equipamiento
                    WHERE id_equipamiento = :id
                    LIMIT 1
                ");

                $stmt->execute([
                    ':id' => $idEquipo
                ]);

                $nombreEquipo = $stmt->fetchColumn();
            }

            if ($cantidadAnterior === 0) {
                $cambiosEquipamiento[] =
                    'Agregado ' .
                    formatoAuditoria($nombreEquipo) .
                    ': cantidad ' .
                    $cantidadNueva;
            } elseif ($cantidadNueva === 0) {
                $cambiosEquipamiento[] =
                    'Quitado ' .
                    formatoAuditoria($nombreEquipo) .
                    ': cantidad anterior ' .
                    $cantidadAnterior;
            } else {
                $cambiosEquipamiento[] =
                    formatoAuditoria($nombreEquipo) .
                    ': cantidad ' .
                    $cantidadAnterior .
                    ' → ' .
                    $cantidadNueva;
            }
        }

        /*actualizar reserva*/

        $stmt = $conexion->prepare("
            UPDATE reservas
            SET
                id_espacio = :id_espacio,
                fecha = :fecha,
                hora_inicio = :hora_inicio,
                hora_fin = :hora_fin
            WHERE id_reserva = :id_reserva
        ");

        $stmt->execute([
            ':id_espacio' => $idEspacio,
            ':fecha' => $fecha,
            ':hora_inicio' => $horaInicio,
            ':hora_fin' => $horaFin,
            ':id_reserva' => $idReserva
        ]);

        /*actualizar actividad*/

        $stmt = $conexion->prepare("
            UPDATE actividades
            SET
                id_tipo_actividad = :id_tipo_actividad,
                nombre = :nombre,
                descripcion = :descripcion,
                responsable = :responsable,
                telefono_responsable = :telefono_responsable
            WHERE id_actividad = :id_actividad
        ");

        $stmt->execute([
            ':id_tipo_actividad' => $idTipoActividad,
            ':nombre' => $nombre,
            ':descripcion' =>
                $descripcion !== ''
                    ? $descripcion
                    : null,
            ':responsable' => $responsable,
            ':telefono_responsable' => $telefono,
            ':id_actividad' => $idActividad
        ]);

        /*reemplazar equipamiento de la reserva*/

        $stmt = $conexion->prepare("
            DELETE FROM reserva_equipamiento
            WHERE id_reserva = :id_reserva
        ");

        $stmt->execute([
            ':id_reserva' => $idReserva
        ]);

        if (!empty($equipamiento)) {

            $stmt = $conexion->prepare("
                INSERT INTO reserva_equipamiento (
                    id_reserva,
                    id_equipamiento,
                    cantidad
                )
                VALUES (
                    :id_reserva,
                    :id_equipamiento,
                    :cantidad
                )
            ");

            foreach (
                $equipamiento
                as $idEquipo => $cantidad
            ) {
                $stmt->execute([
                    ':id_reserva' => $idReserva,
                    ':id_equipamiento' => $idEquipo,
                    ':cantidad' => $cantidad
                ]);
            }
        }

        /*registrar auditoria de reserva si hubo cambios*/

        if (!empty($cambiosReserva) || !empty($cambiosEquipamiento)) {

            $detalleReserva =
                'Modificación de reserva asociada a la actividad ' .
                $idActividad .
                '.';

            if (!empty($cambiosReserva)) {
                $detalleReserva .=
                    ' Cambios de reserva: ' .
                    implode('; ', $cambiosReserva) .
                    '.';
            }

            if (!empty($cambiosEquipamiento)) {
                $detalleReserva .=
                    ' Cambios de equipamiento: ' .
                    implode('; ', $cambiosEquipamiento) .
                    '.';
            }

            $stmt = $conexion->prepare("
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

            $stmt->execute([
                ':id_usuario' => $idUsuario,
                ':id_registro' => $idReserva,
                ':detalle' => $detalleReserva
            ]);
        }

        /*registrar auditoria de actividad si hubo cambios*/

        if (!empty($cambiosActividad)) {

            $detalleActividad =
                'Modificación de actividad ' .
                $idActividad .
                '. Cambios: ' .
                implode('; ', $cambiosActividad) .
                '.';

            $stmt = $conexion->prepare("
                INSERT INTO auditoria (
                    id_usuario,
                    entidad,
                    id_registro,
                    accion,
                    detalle
                )
                VALUES (
                    :id_usuario,
                    'actividades',
                    :id_registro,
                    'MODIFICACION',
                    :detalle
                )
            ");

            $stmt->execute([
                ':id_usuario' => $idUsuario,
                ':id_registro' => $idActividad,
                ':detalle' => $detalleActividad
            ]);
        }

        $conexion->commit();

        header(
            'Location: /SIGA/app/views/actividades/detalle.php?id=' .
            $idActividad .
            '&success=modificado'
        );

        exit;
    }

    header(
        'Location: /SIGA/app/views/actividades/actividades.php'
    );

    exit;

} catch (PDOException $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    if ($accion === 'modificar' && $idActividad) {
        header(
            'Location: /SIGA/app/views/actividades/editar.php?id=' .
            $idActividad .
            '&error=guardar'
        );
    } else {
        header(
            'Location: /SIGA/app/views/actividades/nueva.php?error=guardar'
        );
    }

    exit;
}
