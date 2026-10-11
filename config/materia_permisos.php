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

if (!function_exists('materia_puede_ver_aula')) {
    /**
     * Candado específico de Aula Virtual / Clases en Vivo / Clases
     * Grabadas (NO para Foro ni Calificaciones, que siguen usando
     * materia_puede_ver() solo) — distingue alumno "virtual" (diplomado,
     * cursa 100% online, siempre tiene acceso) de alumno "presencial"
     * (solo entra a una materia puntual si su profesor prendió
     * materias.aula_virtual_presencial para esa materia; el resto del
     * tiempo no tiene necesidad de entrar porque ve la clase en
     * persona). Docente/admin/superadmin no cambian: si ya podían
     * gestionar o ver la materia, siguen pudiendo.
     */
    function materia_puede_ver_aula($con, $uid, $rol, $materia_id) {
        if (!materia_puede_ver($con, $uid, $rol, $materia_id)) return false;
        if ($rol !== 'alumno') return true;
        mysqli_query($con, "ALTER TABLE alumnos ADD COLUMN IF NOT EXISTS modalidad ENUM('presencial','virtual') DEFAULT 'presencial'");
        mysqli_query($con, "ALTER TABLE materias ADD COLUMN IF NOT EXISTS aula_virtual_presencial TINYINT(1) DEFAULT 0");
        $st = mysqli_prepare($con, "SELECT modalidad FROM alumnos WHERE usuario_id=? LIMIT 1");
        mysqli_stmt_bind_param($st, 'i', $uid);
        mysqli_stmt_execute($st);
        $al = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        if (!$al) return false;
        if (($al['modalidad'] ?? 'presencial') === 'virtual') return true;
        $st2 = mysqli_prepare($con, "SELECT aula_virtual_presencial FROM materias WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($st2, 'i', $materia_id);
        mysqli_stmt_execute($st2);
        $mat = mysqli_fetch_assoc(mysqli_stmt_get_result($st2));
        return $mat ? (bool)$mat['aula_virtual_presencial'] : false;
    }
}

if (!function_exists('materias_asignadas')) {
    /**
     * "Mis materias" para los selectores de cada módulo (Aula, Clases
     * en Vivo, Clases Grabadas, Calificaciones...): admin/superadmin
     * ve todas, profesor solo las suyas (vía materia_docente), y
     * alumno solo las que tiene inscritas (vía materia_alumno) — así
     * nunca se ofrece una materia sobre la que después no podría hacer
     * nada. El permiso fino de cada acción lo sigue resolviendo
     * materia_puede_ver()/materia_puede_gestionar() en cada request,
     * esto es solo para no mostrar opciones inútiles en el <select>.
     *
     * $soloAula=true (lo pasan api/aula.php, api/clases_vivo.php y
     * api/clases_grabadas.php) suma el candado de materia_puede_ver_aula():
     * para un alumno "presencial", solo entran las materias donde el
     * profesor activó materias.aula_virtual_presencial. Calificaciones
     * y cualquier otro llamador sigue viendo la lista completa — no
     * tiene nada que ver con si puede entrar al Aula Virtual o no.
     */
    function materias_asignadas($con, $uid, $rol, $soloAula = false) {
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
        } elseif ($rol === 'alumno') {
            $st = mysqli_prepare($con, "SELECT m.id,m.nombre,m.codigo,m.estado
                                         FROM materias m
                                         JOIN materia_alumno ma ON ma.materia_id=m.id
                                         JOIN alumnos a ON a.id=ma.alumno_id
                                         WHERE a.usuario_id=? AND m.activo=1 ORDER BY m.nombre");
            mysqli_stmt_bind_param($st, 'i', $uid);
            mysqli_stmt_execute($st);
            $r = mysqli_stmt_get_result($st);
            if ($soloAula) {
                $todas = []; while ($f = mysqli_fetch_assoc($r)) $todas[] = $f;
                $filtradas = array_values(array_filter($todas, fn($m) => materia_puede_ver_aula($con, $uid, $rol, $m['id'])));
                return $filtradas;
            }
        } else {
            return [];
        }
        $rows = []; while ($f = mysqli_fetch_assoc($r)) $rows[] = $f;
        return $rows;
    }
}
