<?php
require_once 'config/config.php';
require_once 'includes/session.php';

// Si ya está autenticado, redirigir
if (esta_autenticado()) {
    header('Location: ' . obtener_dashboard_por_rol($_SESSION['rol']));
    exit();
}

$titulo_pagina = 'Registro';
include 'includes/header.php';

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpiar_entrada($_POST['nombre'] ?? '');
    $apellido_paterno = limpiar_entrada($_POST['apellido_paterno'] ?? '');
    $apellido_materno = limpiar_entrada($_POST['apellido_materno'] ?? '');
    $email = limpiar_entrada($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    $matricula = limpiar_entrada($_POST['matricula'] ?? '');
    $telefono = limpiar_entrada($_POST['telefono'] ?? '');
    $rol = 'alumno'; // Por defecto, los registros nuevos son alumnos
    
    // Validaciones
    if (empty($nombre) || empty($apellido_paterno) || empty($email) || empty($password) || empty($matricula)) {
        $error = 'Por favor, complete todos los campos obligatorios.';
    } elseif (!es_email_valido($email)) {
        $error = 'El correo electrónico no es válido.';
    } elseif ($password !== $confirmar_password) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe contener al menos 8 caracteres.';
    } else {
        try {
            // Verificar si el email ya existe
            $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'El correo electrónico ya está registrado.';
            } else {
                // Verificar si la matrícula ya existe
                $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE matricula = ?");
                $stmt->execute([$matricula]);
                if ($stmt->fetch()) {
                    $error = 'La matrícula ya está registrada.';
                } else {
                    // Crear usuario
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO usuarios 
                        (nombre, apellido_paterno, apellido_materno, email, password, rol, matricula, telefono, estado_cuenta) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
                    ");
                    $stmt->execute([
                        $nombre, $apellido_paterno, $apellido_materno, 
                        $email, $password_hash, $rol, $matricula, $telefono
                    ]);
                    
                    $exito = 'Registro exitoso. Su cuenta está pendiente de aprobación.';
                    
                    // Limpiar campos
                    $nombre = $apellido_paterno = $apellido_materno = $email = $matricula = $telefono = '';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error al registrar. Intente más tarde.';
            error_log($e->getMessage());
        }
    }
}
?>

<div class="register-container">
    <div class="register-box">
        <div class="register-header">
            <h1>Registro de Usuario</h1>
            <p>Complete el formulario para crear su cuenta</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($exito): ?>
            <div class="alert alert-success"><?php echo $exito; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="register-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" required 
                           value="<?php echo htmlspecialchars($nombre ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="apellido_paterno">Apellido Paterno *</label>
                    <input type="text" id="apellido_paterno" name="apellido_paterno" required 
                           value="<?php echo htmlspecialchars($apellido_paterno ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="apellido_materno">Apellido Materno</label>
                <input type="text" id="apellido_materno" name="apellido_materno" 
                       value="<?php echo htmlspecialchars($apellido_materno ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="matricula">Matrícula *</label>
                    <input type="text" id="matricula" name="matricula" required 
                           value="<?php echo htmlspecialchars($matricula ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" 
                           value="<?php echo htmlspecialchars($telefono ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Correo Electrónico *</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Contraseña * (mínimo 8 caracteres)</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirmar_password">Confirmar Contraseña *</label>
                    <input type="password" id="confirmar_password" name="confirmar_password" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Registrarse</button>
        </form>
        
        <div class="register-footer">
            <p>¿Ya tiene cuenta? <a href="login.php">Iniciar Sesión</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
