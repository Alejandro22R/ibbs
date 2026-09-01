<?php
/**
 * IBBS — Permisos por materia, compartidos por todos los módulos que
 * cuelgan de una materia (aula virtual, clases grabadas, clases en
 * vivo, y cualquier otro que se agregue). Requiere una conexión $con
 * ya abierta (ver config/database.php).
 */

if (!function_exists('materia_puede_gestionar')) {
    /** true si el usuario puede crear/editar/borrar contenido de esta materia. */
    function materia_puede_gestionar($con, $uid, $rol, $materia_id) {
        if (in_array($rol, ['superadmin','admin'])) return true;
        if ($rol !== 'profesor') return false;
        $st = mysqli_prepare($con, "SELECT 1 FROM materia_docente md JOIN docentes d ON d.id=md.docente_id WHERE md.materia_id=? AND d.usuario_id=? LIMIT 1");
        mysqli_stmt_bind_param($st, 'ii', $materia_id, $uid);
        mysqli_stmt_execute($st);
        return (bool) mysqli_fetch_row(mysqli_stmt_get_result($st));
    }
}

if (!function_exists('materia_puede_ver')) {
    /**
     * Además de poder gestionar, solo-lectura para un alumno inscrito.
     * El rol 'alumno' todavía no tiene sesión propia en el sistema (ver
     * módulo "Portal del alumno"), así que esta rama queda lista y sin
     * usar hasta que se active.
     */
    function materia_puede_ver($con, $uid, $rol, $materia_id) {
        if (materia_puede_gestionar($con, $uid, $rol, $materia_id)) return true;
        if ($rol === 'alumno') {
            $st = mysqli_prepare($con, "SELECT 1 FROM materia_alumno ma JOIN alumnos a ON a.id=ma.alumno_id WHERE ma.materia_id=? AND a.usuario_id=? LIMIT 1");
            mysqli_stmt_bind_param($st, 'ii', $materia_id, $uid);
            mysqli_stmt_execute($st);
            return (bool) mysqli_fetch_row(mysqli_stmt_get_result($st));
        }
        return false;
    }
}

if (!function_exists('materias_asignadas')) {
    /**
     * Materias que el usuario puede administrar: todas si es
     * admin/superadmin, o solo las suyas (vía materia_docente) si es
     * profesor. Usado por los selectores "elige una materia" de cada
     * módulo — así nunca se ofrece una sobre la que después no podría
     * hacer nada.
     */
    function materias_asignadas($con, $uid, $rol) {
        if (in_array($rol, ['superadmin','admin'])) {
            $r = mysqli_query($con, "SELECT id,nombre,codigo,estado FROM materias WHERE activo=1 ORDER BY nombre");
        } elseif ($rol === 'profesor') {
            $st = mysqli_prepare($con, "SELECT m.id,m.nombre,m.codigo,m.estado
                                         FROM materias m
                                         JOIN materia_docente md ON md.materia_id=m.id
                                         JOIN docentes d ON d.id=md.docente_id
                                         WHERE d.usuario_id=? AND m.activo=1 ORDER BY m.nombre");
            mysqli_stmt_bind_param($st, 'i', $uid);
            mysqli_stmt_execute($st);
            $r = mysqli_stmt_get_result($st);
        } else {
            return [];
        }
        $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
        return $rows;
    }
}
