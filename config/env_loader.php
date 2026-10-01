<?php
/**
 * IBBS — Carga opcional de un archivo `.env` en la raíz del proyecto.
 *
 * El `.gitignore` ya reservaba `.env`/`.env.local` desde hace tiempo,
 * pero nunca existió el código que los lee — las variables de entorno
 * (IBBS_DB_HOST, etc.) dependían de que alguien las configurara a mano
 * en Apache/Nginx/systemd. Esto agrega la opción más simple para quien
 * no tiene esa configuración a mano: crear un archivo `.env` de texto
 * plano junto a `login.php`, subirlo por FTP/SFTP/administrador de
 * archivos del hosting, y listo — nunca se commitea (está en
 * .gitignore), así que ninguna contraseña real llega al repositorio.
 *
 * Si la variable YA está definida en el entorno real del servidor
 * (Apache SetEnv, systemd Environment=, etc.), esta carga NO la pisa —
 * el `.env` es solo un método más, no tiene prioridad sobre lo que el
 * servidor ya tenga configurado.
 */

if (!function_exists('ibbs_load_env')) {
    function ibbs_load_env() {
        static $cargado = false;
        if ($cargado) return;
        $cargado = true;

        $file = __DIR__ . '/../.env';
        if (!is_file($file) || !is_readable($file)) return;

        $lineas = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lineas) return;

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#') continue;
            if (strpos($linea, '=') === false) continue;
            [$clave, $valor] = explode('=', $linea, 2);
            $clave = trim($clave);
            $valor = trim($valor);
            // Permite comillas simples/dobles alrededor del valor, por si
            // alguien copia el formato típico de otros frameworks.
            if (strlen($valor) >= 2 && (
                ($valor[0] === '"' && substr($valor, -1) === '"') ||
                ($valor[0] === "'" && substr($valor, -1) === "'")
            )) {
                $valor = substr($valor, 1, -1);
            }
            if ($clave === '' || getenv($clave) !== false) continue; // no pisar lo que ya esté seteado
            putenv("$clave=$valor");
            $_ENV[$clave] = $valor;
        }
    }
}

ibbs_load_env();
