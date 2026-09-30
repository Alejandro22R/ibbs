<?php
/**
 * IBBS — Helper compartido de asistencias.
 *
 * Usado por api/ajax.php (asistencia_register / asistencia_register_lote)
 * y por api/asistencia_ocr.php (guardado de la hoja escaneada) — un solo
 * lugar para la lógica de "guardar la asistencia de una persona en una
 * fecha", para no duplicarla entre los dos endpoints.
 */

if (!function_exists('asistencia_upsert')) {
    // UPSERT real por (materia_id,tipo,fecha,persona) — antes cada
    // guardado era un INSERT ciego: volver a guardar el mismo día (o
    // re-procesar la misma hoja escaneada) iba acumulando filas
    // duplicadas en vez de corregir la existente.
    function asistencia_upsert($con, $mid, $tipo, $pid, $fecha, $estado, $obs, $registradoPor, $hojaId = null) {
        $mid = (int)$mid; $pid = (int)$pid;
        $tipo = $tipo === 'docente' ? 'docente' : 'alumno';
        $col  = $tipo === 'docente' ? 'docente_id' : 'alumno_id';

        $st = mysqli_prepare($con, "SELECT id FROM asistencias WHERE materia_id=? AND tipo=? AND fecha=? AND $col=? LIMIT 1");
        mysqli_stmt_bind_param($st, 'issi', $mid, $tipo, $fecha, $pid);
        mysqli_stmt_execute($st);
        $ex = mysqli_fetch_assoc(mysqli_stmt_get_result($st));

        if ($ex) {
            $st2 = mysqli_prepare($con, "UPDATE asistencias SET estado=?,observacion=?,registrado_por=?,hoja_id=COALESCE(?,hoja_id) WHERE id=?");
            mysqli_stmt_bind_param($st2, 'ssiii', $estado, $obs, $registradoPor, $hojaId, $ex['id']);
            return mysqli_stmt_execute($st2);
        }

        $st3 = mysqli_prepare($con, "INSERT INTO asistencias(materia_id,$col,tipo,fecha,estado,observacion,registrado_por,hoja_id) VALUES(?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($st3, 'iissssii', $mid, $pid, $tipo, $fecha, $estado, $obs, $registradoPor, $hojaId);
        return mysqli_stmt_execute($st3);
    }
}
