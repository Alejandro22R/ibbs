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
