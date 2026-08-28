<?php
// cerrar_sesion.php
// RUTA: C:\xampp\htdocs\ibbs\inicio\cerrar_sesion.php
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;
