<?php
/**
 * IBBS — Registro de auditoría compartido por todos los endpoints.
 * Requiere una conexión $con ya abierta (ver config/database.php).
 */

if (!function_exists('log_audit')) {
    function log_audit($con, $uid, $accion, $detalle = '') {
        static $created = false;
        if (!$created) {
            mysqli_query($con, "CREATE TABLE IF NOT EXISTS audit_log (id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT, accion VARCHAR(100), detalle TEXT, ip VARCHAR(45), creado_en DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            // Sin esto, audit_log solo tenía índice en el id. Esta
            // tabla crece con cada acción que modifica algo en toda la
            // app (pasa por acá), y las pantallas que la leen (el
            // Historial de acciones y el historial unificado) siempre
            // ordenan por creado_en — sin índice, cada lectura es un
            // filesort sobre la tabla completa.
            mysqli_query($con, "ALTER TABLE audit_log ADD INDEX IF NOT EXISTS idx_creado_en (creado_en)");
            $created = true;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $st = mysqli_prepare($con, "INSERT INTO audit_log(usuario_id,accion,detalle,ip) VALUES(?,?,?,?)");
        if ($st) { mysqli_stmt_bind_param($st, 'isss', $uid, $accion, $detalle, $ip); mysqli_stmt_execute($st); }
    }
}

/**
 * IBBS — Traduce un código de auditoría crudo (ej. "AULA_ANUNCIO_CREATE")
 * y su detalle (ej. "materia=160") a una frase en español que una
 * persona sin conocimientos técnicos pueda entender — el Historial de
 * Actividad (solo superadmin) antes mostraba estos códigos tal cual,
 * mezclados con ids numéricos internos ("materia=160", "alumno=40")
 * que no significan nada para quien no conoce la base de datos.
 */
if (!function_exists('ibbs_accion_label')) {
    function ibbs_accion_label($accion) {
        static $labels = [
            'ALUMNO_APROBAR' => 'Aprobó una solicitud de ingreso de alumno',
            'ALUMNO_CREAR_DESDE_MATRIZ' => 'Creó un alumno nuevo desde la sábana de notas',
            'ALUMNO_DELETE' => 'Eliminó un alumno',
            'ALUMNO_MODALIDAD' => 'Cambió la modalidad (presencial/virtual) de un alumno',
            'ALUMNO_RECHAZAR' => 'Rechazó una solicitud de ingreso de alumno',
            'ALUMNO_REGULAR' => 'Cambió el estado regular/condicional de un alumno',
            'ALUMNO_AUTOREGISTRO' => 'Un alumno se registró solo desde el login',
            'ALUMNO_PERFIL_ACTUALIZAR' => 'Un alumno actualizó sus datos de perfil',
            'ASISTENCIA_ELIMINAR_LOTE' => 'Eliminó asistencias en lote',
            'ASISTENCIA_LOTE' => 'Registró asistencia en lote',
            'ASISTENCIA_OCR' => 'Registró asistencia leyendo una foto (OCR)',
            'AULA_ACTIVIDAD_CREATE' => 'Creó una actividad en el Aula Virtual',
            'AULA_ACTIVIDAD_DELETE' => 'Eliminó una actividad del Aula Virtual',
            'AULA_ANUNCIO_CREATE' => 'Publicó un anuncio en el Aula Virtual',
            'AULA_ANUNCIO_DELETE' => 'Eliminó un anuncio del Aula Virtual',
            'AULA_CALIFICAR' => 'Calificó una actividad del Aula Virtual',
            'AULA_MATERIAL_CREATE' => 'Subió material al Aula Virtual',
            'AULA_MATERIAL_DELETE' => 'Eliminó un material del Aula Virtual',
            'AULA_SECCION_CREATE' => 'Creó una sección en el Aula Virtual',
            'AULA_SECCION_DELETE' => 'Eliminó una sección del Aula Virtual',
            'AUTOINSCRIPCION_SEMESTRAL' => 'Un alumno se autoinscribió en una materia',
            'BOLETIN_GENERAR' => 'Generó un Boletín de Calificaciones',
            'CHAT_STAFF_BORRAR' => 'Borró un mensaje del Chat del Staff',
            'CHAT_STAFF_MENSAJE' => 'Envió un mensaje en el Chat del Staff',
            'CLASE_GRABADA_CREATE' => 'Subió una clase grabada',
            'CLASE_GRABADA_DELETE' => 'Eliminó una clase grabada',
            'CLASE_VIVO_CREATE' => 'Programó una clase en vivo',
            'CLASE_VIVO_DELETE' => 'Eliminó una clase en vivo',
            'CLASE_VIVO_ESTADO' => 'Cambió el estado de una clase en vivo',
            'CLASE_VIVO_GRABACION' => 'Adjuntó la grabación de una clase en vivo',
            'DATOS_PAGO_ACTUALIZAR' => 'Actualizó los datos de pago del instituto',
            'DEVOCIONAL_CREAR' => 'Publicó un devocional',
            'DEVOCIONAL_ELIMINAR' => 'Eliminó un devocional',
            'DOCENTE_CREATE' => 'Registró un docente nuevo',
            'DOCENTE_DELETE' => 'Eliminó un docente',
            'DOCENTE_ELIMINAR_LOTE' => 'Eliminó docentes en lote',
            'ENTREGA_CALIFICAR' => 'Calificó la entrega de una tarea',
            'ENTREGA_SUBIR' => 'Un alumno entregó una tarea',
            'FORO_MENSAJE' => 'Envió un mensaje en el Foro de Clase',
            'FORO_MENSAJE_BORRAR' => 'Borró un mensaje del Foro de Clase',
            'LIBRO_ACTIVAR_GRATIS' => 'Activó un libro como gratuito en la Biblioteca',
            'LIBRO_COMPRA_APROBAR' => 'Aprobó la compra de un libro',
            'LIBRO_COMPRA_CREAR' => 'Registró la compra de un libro',
            'LIBRO_COMPRA_RECHAZAR' => 'Rechazó la compra de un libro',
            'LIBRO_CREAR' => 'Agregó un libro a la Biblioteca',
            'LIBRO_DESCARGAR' => 'Descargó un libro de la Biblioteca',
            'LIBRO_EDITAR' => 'Editó un libro de la Biblioteca',
            'LIBRO_ELIMINAR' => 'Eliminó un libro de la Biblioteca',
            'LIBRO_TOGGLE' => 'Activó/desactivó un libro de la Biblioteca',
            'MATERIA_ALUMNO_ASIGNAR' => 'Inscribió un alumno en una materia',
            'MATERIA_ALUMNO_QUITAR' => 'Quitó a un alumno de una materia',
            'MATERIA_AULA_PRESENCIAL_TOGGLE' => 'Activó/desactivó el Aula Virtual para presenciales en una materia',
            'MATERIA_DELETE' => 'Eliminó una materia',
            'MATERIA_DESBLOQUEO_OTORGAR' => 'Dio acceso anticipado a una materia (sin cumplir el pensum)',
            'MATERIA_DESBLOQUEO_REVOCAR' => 'Quitó el acceso anticipado a una materia',
            'MATERIA_DOCENTE_ASIGNAR' => 'Asignó un docente a una materia',
            'MATERIA_DOCENTE_QUITAR' => 'Quitó a un docente de una materia',
            'MATERIA_INSCRIBIR_HISTORICO' => 'Inscribió a un alumno con una nota histórica',
            'MATERIA_INSCRIPCION_TOGGLE' => 'Abrió/cerró la autoinscripción de una materia',
            'MATERIA_PREREQUISITO_LIBRE_TOGGLE' => 'Cambió si una materia exige pensum previo',
            'MATERIA_SOLICITUD_APROBAR' => 'Aprobó una solicitud de inscripción a materia',
            'MATERIA_SOLICITUD_CREAR' => 'Un alumno solicitó inscribirse en una materia',
            'MATERIA_SOLICITUD_RECHAZAR' => 'Rechazó una solicitud de inscripción a materia',
            'NOTAS_ENVIO_APROBAR' => 'Aprobó las notas enviadas por un docente',
            'NOTAS_ENVIO_CREAR' => 'Un docente envió notas para revisión',
            'NOTAS_ENVIO_RECHAZAR' => 'Rechazó las notas enviadas por un docente',
            'NOTAS_IMPORTAR_CSV' => 'Importó notas desde un archivo CSV',
            'NOTAS_IMPORTAR_MATRIZ' => 'Importó notas desde una sábana/matriz Excel',
            'NOTA_BORRAR' => 'Borró una nota',
            'NOTA_GUARDAR' => 'Guardó una nota',
            'NOTA_GUARDAR_LOTE' => 'Guardó notas en lote',
            'PAGO_CREAR' => 'Registró un cobro',
            'PAGO_ELIMINAR' => 'Eliminó un cobro',
            'PAGO_MARCAR_PAGADO' => 'Marcó un cobro como pagado',
            'PAGO_SUBIR_COMPROBANTE' => 'Subió un comprobante de pago',
            'PASSWORD_RESET_COMPLETADO' => 'Completó la recuperación de su contraseña',
            'PASSWORD_RESET_SOLICITADO' => 'Solicitó recuperar su contraseña',
            'REPORTE_ASISTENCIAS_PDF' => 'Generó un reporte PDF de asistencias',
            'REPORTE_PAGOS_PDF' => 'Generó un reporte PDF de pagos',
            'RESET_BD' => 'Restableció la base de datos',
            'TAREA_CALIFICAR' => 'Calificó una tarea',
            'TAREA_CREAR' => 'Creó una tarea',
            'TAREA_CREATE' => 'Creó una tarea',
            'TAREA_DELETE' => 'Eliminó una tarea',
            'TAREA_ENTREGAR' => 'Un alumno entregó una tarea',
            'USUARIO_DELETE' => 'Eliminó un usuario',
        ];
        if (isset($labels[$accion])) return $labels[$accion];
        // Prefijos dinámicos: CONSTANCIA_<TIPO>_GENERAR, PAGO_<ACCION>,
        // USUARIO_LOTE_<SUB> — no tienen una entrada exacta en el mapa
        // de arriba porque la parte variable cambia el string guardado.
        if (preg_match('/^CONSTANCIA_(.+)_GENERAR$/', $accion, $m)) return 'Generó una Constancia de '.ucfirst(strtolower(str_replace('_',' ',$m[1])));
        if (strpos($accion, 'USUARIO_LOTE_') === 0) return 'Hizo una acción en lote sobre usuarios';
        if ($accion === 'PAGO_APROBAR') return 'Aprobó un comprobante de pago';
        if ($accion === 'PAGO_RECHAZAR') return 'Rechazó un comprobante de pago';
        // Nunca visto: en vez de mostrar el código crudo en mayúsculas
        // con guiones bajos, al menos se "humaniza" el formato.
        return ucfirst(strtolower(str_replace('_', ' ', $accion)));
    }
}

/**
 * Traduce el "detalle" crudo de una entrada de auditoría (ej.
 * "materia=160 alumno=40") reemplazando los ids por el nombre real del
 * registro al que apuntan, cuando se puede resolver — en vez de números
 * internos que solo tienen sentido mirando la base de datos.
 */
if (!function_exists('ibbs_detalle_legible')) {
    function ibbs_detalle_legible($con, $detalle) {
        if (!$detalle) return '';
        if (!preg_match_all('/([a-z_]+)=([^\s]+)/i', $detalle, $m, PREG_SET_ORDER)) return $detalle;
        $etiquetas = ['materia'=>'Materia','materia_id'=>'Materia','alumno'=>'Alumno','alumno_id'=>'Alumno',
            'docente'=>'Docente','docente_id'=>'Docente','usuario'=>'Usuario','usuario_id'=>'Usuario'];
        $tablas = [
            'materia'=>['materias','nombre'], 'materia_id'=>['materias','nombre'],
            'alumno'=>['alumnos',"CONCAT(nombre,' ',apellido)"], 'alumno_id'=>['alumnos',"CONCAT(nombre,' ',apellido)"],
            'docente'=>['docentes',"CONCAT(nombre,' ',apellido)"], 'docente_id'=>['docentes',"CONCAT(nombre,' ',apellido)"],
            'usuario'=>['usuarios','usuario'], 'usuario_id'=>['usuarios','usuario'],
        ];
        $partes = [];
        foreach ($m as $tok) {
            $clave = strtolower($tok[1]); $valor = $tok[2];
            // "id=11" por sí solo es el id interno de LO QUE sea que la
            // acción haya creado/borrado (un devocional, una tarea...) —
            // sin saber a qué tabla pertenece no se puede resolver a un
            // nombre, y mostrar el número crudo no aporta nada a un
            // lector sin conocimientos técnicos — se omite.
            if ($clave === 'id') continue;
            $nombre = null;
            if (isset($tablas[$clave]) && ctype_digit($valor)) {
                [$tabla, $campo] = $tablas[$clave];
                $row = mysqli_fetch_assoc(mysqli_query($con, "SELECT $campo AS n FROM $tabla WHERE id=".(int)$valor." LIMIT 1"));
                if ($row && $row['n'] !== null) $nombre = $row['n'];
            }
            $etq = $etiquetas[$clave] ?? ucfirst(str_replace('_',' ',$clave));
            $partes[] = $nombre ? "$etq: $nombre" : "$etq $valor";
        }
        return implode(' · ', $partes);
    }
}
