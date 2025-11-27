<?php
require_once 'config/config.php';
require_once 'includes/session.php';

// Si está autenticado, redirigir a dashboard
if (esta_autenticado()) {
    header('Location: dashboard.php');
    exit();
}

// Si no está autenticado, redirigir a login
header('Location: login.php');
exit();
?>

// PENDEJO TU VE PROBANDO LA APLICACION PUTA MADRE QUE ESA MADRE HAGA TODO YA QUE VERGAS 
//NO TE DECIDES

// YA ESTA EL PANEL DE administrador

//NI ME DIJISTE SI SI JALA BIEN LA BD AL REGISTARSE WE
// YA ESTA LA FUNCION DE REGISTRO Y LOGIN

Desde el Panel del Administrador:
1. Ver lista completa de usuarios
2. Filtrar por rol (admin/docente/alumno/administrativo)
3. Filtrar por estado (activo/inactivo/pendiente)
4. Buscar por nombre, correo o matrícula
5. Aprobar/rechazar usuarios pendientes
6. Editar cualquier usuario (nombre, rol, estado, etc.)
7. Activar/desactivar cuentas
8. Eliminar usuarios (excepto a sí mismo)
9. Cambiar contraseñas de usuarios