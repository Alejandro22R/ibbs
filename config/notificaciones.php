<?php
/**
 * IBBS — Notificaciones en tiempo real.
 *
 * Insertar una fila acá es TODO lo que un módulo tiene que hacer para
 * "empujar" una notificación: api/notificaciones_stream.php sondea la
 * tabla cada ~1s por usuario conectado y la entrega apenas aparece,
 * sin que el módulo que la generó tenga que saber nada de SSE.
 */

if (!function_exists('notificar_usuario')) {
    /** Notifica a un usuario puntual (ej. "tu tarea fue calificada"). */
    function notificar_usuario($con, $usuario_id, $tipo, $titulo, $mensaje, $materia_id = null) {
        if (!$usuario_id) return;
        $st = mysqli_prepare($con, "INSERT INTO notificaciones(tipo,titulo,mensaje,usuario_id,materia_id) VALUES(?,?,?,?,?)");
        mysqli_stmt_bind_param($st, 'sssii', $tipo, $titulo, $mensaje, $usuario_id, $materia_id);
        mysqli_stmt_execute($st);
        // La fila en BD es la fuente de verdad (así la ve quien no
        // tenga el WebSocket activo); esto solo evita la espera del
        // próximo sondeo de SSE/polling si hay un VPS con Node conectado.
        if (function_exists('ws_broadcast_user')) {
            ws_broadcast_user($usuario_id, 'notificacion', [
                'id' => mysqli_insert_id($con), 'tipo' => $tipo, 'titulo' => $titulo,
                'mensaje' => $mensaje, 'materia_id' => $materia_id, 'creado_en' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}

if (!function_exists('notif_roles_aceptados')) {
    /**
     * Para qué valores de para_rol debe ver un usuario con este rol las
     * notificaciones "broadcast" (usuario_id IS NULL) — superadmin ve
     * también las dirigidas a 'admin'. Siempre devuelve 3 valores
     * (repitiendo el último si hace falta) para poder bindear un
     * IN (?,?,?) de tamaño fijo en las consultas.
     */
    function notif_roles_aceptados($rol) {
        $roles = $rol === 'superadmin' ? ['admin', 'superadmin', 'todos'] : [$rol, 'todos'];
        while (count($roles) < 3) $roles[] = end($roles);
        return $roles;
    }
}

if (!function_exists('notificar_materia')) {
    /**
     * Notifica a todos los docentes y alumnos inscritos en una materia
     * (vía materia_docente / materia_alumno), sin duplicar destinatarios
     * y sin notificar a quien disparó el evento.
     */
    function notificar_materia($con, $materia_id, $tipo, $titulo, $mensaje, $excluir_uid = null) {
        $destinatarios = [];

        $st = mysqli_prepare($con, "SELECT d.usuario_id FROM materia_docente md JOIN docentes d ON d.id=md.docente_id WHERE md.materia_id=? AND d.usuario_id IS NOT NULL");
        mysqli_stmt_bind_param($st, 'i', $materia_id);
        mysqli_stmt_execute($st);
        $r = mysqli_stmt_get_result($st);
        while ($f = mysqli_fetch_assoc($r)) $destinatarios[(int)$f['usuario_id']] = true;

        $st2 = mysqli_prepare($con, "SELECT a.usuario_id FROM materia_alumno ma JOIN alumnos a ON a.id=ma.alumno_id WHERE ma.materia_id=? AND a.usuario_id IS NOT NULL");
        mysqli_stmt_bind_param($st2, 'i', $materia_id);
        mysqli_stmt_execute($st2);
        $r2 = mysqli_stmt_get_result($st2);
        while ($f = mysqli_fetch_assoc($r2)) $destinatarios[(int)$f['usuario_id']] = true;

        if ($excluir_uid) unset($destinatarios[(int)$excluir_uid]);

        foreach (array_keys($destinatarios) as $uid) {
            notificar_usuario($con, $uid, $tipo, $titulo, $mensaje, $materia_id);
        }
    }
}
