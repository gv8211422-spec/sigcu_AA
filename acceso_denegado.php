<?php
require_once 'config/config.php';
require_once 'includes/session.php';

$titulo_pagina = 'Acceso Denegado';
include 'includes/header.php';
?>

<div class="error-container">
    <div class="error-box">
        <h1>🚫 Acceso Denegado</h1>
        <p>No tiene permisos para acceder a esta página.</p>
        
        <?php if (esta_autenticado()): ?>
            <a href="<?php echo obtener_dashboard_por_rol($_SESSION['rol']); ?>" class="btn btn-primary">
                Ir a mi Panel de Control
            </a>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary">Iniciar Sesión</a>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
