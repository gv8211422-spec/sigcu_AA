<?php
require_once 'config/config.php';
require_once 'includes/session.php';

// Cerrar sesión
if (esta_autenticado()) {
    registrar_historial($pdo, $_SESSION['usuario_id'], 'Cierre de sesión', 'Sesión cerrada correctamente');
    cerrar_sesion();
}

$_SESSION['mensaje'] = 'Sesión cerrada correctamente.';
header('Location: login.php');
exit();
?>
