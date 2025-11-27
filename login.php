<?php
require_once 'config/config.php';
require_once 'includes/session.php';

// Si ya está autenticado, redirigir a su dashboard
if (esta_autenticado()) {
    header('Location: ' . obtener_dashboard_por_rol($_SESSION['rol']));
    exit();
}

$titulo_pagina = 'Iniciar Sesión';
include 'includes/header.php';

$error = '';
$exito = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = limpiar_entrada($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM usuarios 
                WHERE email = ? AND estado_cuenta = 'activo'
            ");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if ($usuario && password_verify($password, $usuario['password'])) {
                // Inicio de sesión exitoso
                iniciar_sesion($usuario);
                
                // Actualizar último acceso
                $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?");
                $stmt->execute([$usuario['id_usuario']]);
                
                // Registrar en historial
                registrar_historial($pdo, $usuario['id_usuario'], 'Inicio de sesión', 'Acceso exitoso al sistema');
                
                // Redirigir según rol
                header('Location: ' . obtener_dashboard_por_rol($usuario['rol']));
                exit();
            } else {
                $error = 'Credenciales incorrectas o cuenta inactiva.';
            }
        } catch (PDOException $e) {
            $error = 'Error en el sistema. Intente más tarde.';
            error_log($e->getMessage());
        }
    }
}

// Mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $exito = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
?>

<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <h1>SIGCU_AA</h1>
            <p>Sistema Integral de Gestión de Comunidad Universitaria</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($exito): ?>
            <div class="alert alert-success"><?php echo $exito; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="login-form">
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Iniciar Sesión</button>
        </form>
        
        <div class="login-footer">
            <a href="recover_password.php">¿Olvidó su contraseña?</a>
            <a href="register.php">Registrarse</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
