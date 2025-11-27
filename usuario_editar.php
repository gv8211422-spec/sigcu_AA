<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_rol('administrador');

$titulo_pagina = 'Editar Usuario';
include 'includes/header.php';
include 'includes/navbar.php';

$mensaje = '';
$tipo_mensaje = '';
$id_usuario = intval($_GET['id'] ?? 0);

if ($id_usuario === 0) {
    header('Location: usuarios.php');
    exit();
}

// Obtener datos del usuario
try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        header('Location: usuarios.php');
        exit();
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: usuarios.php');
    exit();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpiar_entrada($_POST['nombre'] ?? '');
    $apellido_paterno = limpiar_entrada($_POST['apellido_paterno'] ?? '');
    $apellido_materno = limpiar_entrada($_POST['apellido_materno'] ?? '');
    $email = limpiar_entrada($_POST['email'] ?? '');
    $matricula = limpiar_entrada($_POST['matricula'] ?? '');
    $telefono = limpiar_entrada($_POST['telefono'] ?? '');
    $rol = limpiar_entrada($_POST['rol'] ?? '');
    $estado_cuenta = limpiar_entrada($_POST['estado_cuenta'] ?? '');
    $nueva_password = $_POST['nueva_password'] ?? '';
    
    // Validaciones
    if (empty($nombre) || empty($apellido_paterno) || empty($email)) {
        $mensaje = 'Por favor, complete todos los campos obligatorios.';
        $tipo_mensaje = 'error';
    } elseif (!es_email_valido($email)) {
        $mensaje = 'El correo electrónico no es válido.';
        $tipo_mensaje = 'error';
    } else {
        try {
            // Verificar si el email ya existe (excepto el actual)
            $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
            $stmt->execute([$email, $id_usuario]);
            if ($stmt->fetch()) {
                $mensaje = 'El correo electrónico ya está registrado por otro usuario.';
                $tipo_mensaje = 'error';
            } else {
                // Verificar si la matrícula ya existe (excepto el actual)
                if (!empty($matricula)) {
                    $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE matricula = ? AND id_usuario != ?");
                    $stmt->execute([$matricula, $id_usuario]);
                    if ($stmt->fetch()) {
                        $mensaje = 'La matrícula ya está registrada por otro usuario.';
                        $tipo_mensaje = 'error';
                    }
                }
                
                if (empty($mensaje)) {
                    // Actualizar usuario
                    if (!empty($nueva_password)) {
                        // Actualizar con nueva contraseña
                        $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE usuarios SET 
                            nombre = ?, apellido_paterno = ?, apellido_materno = ?,
                            email = ?, matricula = ?, telefono = ?,
                            rol = ?, estado_cuenta = ?, password = ?
                            WHERE id_usuario = ?
                        ");
                        $stmt->execute([
                            $nombre, $apellido_paterno, $apellido_materno,
                            $email, $matricula, $telefono,
                            $rol, $estado_cuenta, $password_hash, $id_usuario
                        ]);
                    } else {
                        // Actualizar sin cambiar contraseña
                        $stmt = $pdo->prepare("
                            UPDATE usuarios SET 
                            nombre = ?, apellido_paterno = ?, apellido_materno = ?,
                            email = ?, matricula = ?, telefono = ?,
                            rol = ?, estado_cuenta = ?
                            WHERE id_usuario = ?
                        ");
                        $stmt->execute([
                            $nombre, $apellido_paterno, $apellido_materno,
                            $email, $matricula, $telefono,
                            $rol, $estado_cuenta, $id_usuario
                        ]);
                    }
                    
                    registrar_historial($pdo, $_SESSION['usuario_id'], 'Editar usuario', "Usuario ID: $id_usuario actualizado");
                    
                    $mensaje = 'Usuario actualizado exitosamente.';
                    $tipo_mensaje = 'success';
                    
                    // Recargar datos actualizados
                    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
                    $stmt->execute([$id_usuario]);
                    $usuario = $stmt->fetch();
                }
            }
        } catch (PDOException $e) {
            $mensaje = 'Error al actualizar usuario: ' . $e->getMessage();
            $tipo_mensaje = 'error';
            error_log($e->getMessage());
        }
    }
}
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Editar Usuario</h1>
        <p>Modifique los datos del usuario</p>
    </div>
    
    <div class="breadcrumb">
        <a href="dashboard.php">Panel de Control</a> / 
        <a href="usuarios.php">Usuarios</a> / 
        <span>Editar</span>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <form method="POST" action="" class="edit-form">
            <h3>Información Personal</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="nombre">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" required 
                           value="<?php echo htmlspecialchars($usuario['nombre']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="apellido_paterno">Apellido Paterno *</label>
                    <input type="text" id="apellido_paterno" name="apellido_paterno" required 
                           value="<?php echo htmlspecialchars($usuario['apellido_paterno']); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="apellido_materno">Apellido Materno</label>
                <input type="text" id="apellido_materno" name="apellido_materno" 
                       value="<?php echo htmlspecialchars($usuario['apellido_materno']); ?>">
            </div>
            
            <hr>
            <h3>Información de Contacto</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Correo Electrónico *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($usuario['email']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" 
                           value="<?php echo htmlspecialchars($usuario['telefono']); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="matricula">Matrícula</label>
                <input type="text" id="matricula" name="matricula" 
                       value="<?php echo htmlspecialchars($usuario['matricula']); ?>">
            </div>
            
            <hr>
            <h3>Configuración de Cuenta</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="rol">Rol *</label>
                    <select id="rol" name="rol" required>
                        <option value="administrador" <?php echo $usuario['rol'] === 'administrador' ? 'selected' : ''; ?>>Administrador</option>
                        <option value="docente" <?php echo $usuario['rol'] === 'docente' ? 'selected' : ''; ?>>Docente</option>
                        <option value="alumno" <?php echo $usuario['rol'] === 'alumno' ? 'selected' : ''; ?>>Alumno</option>
                        <option value="administrativo" <?php echo $usuario['rol'] === 'administrativo' ? 'selected' : ''; ?>>Administrativo</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="estado_cuenta">Estado de Cuenta *</label>
                    <select id="estado_cuenta" name="estado_cuenta" required>
                        <option value="activo" <?php echo $usuario['estado_cuenta'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $usuario['estado_cuenta'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                        <option value="pendiente" <?php echo $usuario['estado_cuenta'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    </select>
                </div>
            </div>
            
            <hr>
            <h3>Cambiar Contraseña (Opcional)</h3>
            <p class="text-muted">Deje en blanco si no desea cambiar la contraseña</p>
            
            <div class="form-group">
                <label for="nueva_password">Nueva Contraseña</label>
                <input type="password" id="nueva_password" name="nueva_password" 
                       placeholder="Mínimo 8 caracteres">
            </div>
            
            <hr>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
    
    <!-- Información adicional -->
    <div class="card">
        <h3>Información del Sistema</h3>
        <div class="info-grid">
            <div class="info-item">
                <strong>Fecha de Registro:</strong>
                <?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'])); ?>
            </div>
            <div class="info-item">
                <strong>Último Acceso:</strong>
                <?php echo $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
