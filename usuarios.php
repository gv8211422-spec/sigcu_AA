<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_rol('administrador');

$titulo_pagina = 'Gestión de Usuarios';
include 'includes/header.php';
include 'includes/navbar.php';

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones (aprobar, rechazar, eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        $id_usuario = intval($_POST['id_usuario'] ?? 0);
        
        try {
            switch ($_POST['accion']) {
                case 'aprobar':
                    $stmt = $pdo->prepare("UPDATE usuarios SET estado_cuenta = 'activo' WHERE id_usuario = ?");
                    $stmt->execute([$id_usuario]);
                    registrar_historial($pdo, $_SESSION['usuario_id'], 'Aprobar usuario', "Usuario ID: $id_usuario aprobado");
                    $mensaje = 'Usuario aprobado exitosamente.';
                    $tipo_mensaje = 'success';
                    break;
                    
                case 'rechazar':
                    $stmt = $pdo->prepare("UPDATE usuarios SET estado_cuenta = 'inactivo' WHERE id_usuario = ?");
                    $stmt->execute([$id_usuario]);
                    registrar_historial($pdo, $_SESSION['usuario_id'], 'Rechazar usuario', "Usuario ID: $id_usuario rechazado");
                    $mensaje = 'Usuario rechazado.';
                    $tipo_mensaje = 'success';
                    break;
                    
                case 'activar':
                    $stmt = $pdo->prepare("UPDATE usuarios SET estado_cuenta = 'activo' WHERE id_usuario = ?");
                    $stmt->execute([$id_usuario]);
                    registrar_historial($pdo, $_SESSION['usuario_id'], 'Activar usuario', "Usuario ID: $id_usuario activado");
                    $mensaje = 'Usuario activado exitosamente.';
                    $tipo_mensaje = 'success';
                    break;
                    
                case 'desactivar':
                    $stmt = $pdo->prepare("UPDATE usuarios SET estado_cuenta = 'inactivo' WHERE id_usuario = ?");
                    $stmt->execute([$id_usuario]);
                    registrar_historial($pdo, $_SESSION['usuario_id'], 'Desactivar usuario', "Usuario ID: $id_usuario desactivado");
                    $mensaje = 'Usuario desactivado exitosamente.';
                    $tipo_mensaje = 'success';
                    break;
                    
                case 'eliminar':
                    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
                    $stmt->execute([$id_usuario]);
                    registrar_historial($pdo, $_SESSION['usuario_id'], 'Eliminar usuario', "Usuario ID: $id_usuario eliminado");
                    $mensaje = 'Usuario eliminado exitosamente.';
                    $tipo_mensaje = 'success';
                    break;
            }
        } catch (PDOException $e) {
            $mensaje = 'Error al procesar la acción: ' . $e->getMessage();
            $tipo_mensaje = 'error';
            error_log($e->getMessage());
        }
    }
}

// Filtros
$filtro_rol = $_GET['rol'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// Construir consulta con filtros
$sql = "SELECT * FROM usuarios WHERE 1=1";
$params = [];

if (!empty($filtro_rol)) {
    $sql .= " AND rol = ?";
    $params[] = $filtro_rol;
}

if (!empty($filtro_estado)) {
    $sql .= " AND estado_cuenta = ?";
    $params[] = $filtro_estado;
}

if (!empty($busqueda)) {
    $sql .= " AND (nombre LIKE ? OR apellido_paterno LIKE ? OR apellido_materno LIKE ? OR email LIKE ? OR matricula LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

$sql .= " ORDER BY fecha_registro DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $usuarios = [];
}
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Gestión de Usuarios</h1>
        <p>Administre los usuarios del sistema</p>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <!-- Filtros y búsqueda -->
    <div class="card">
        <form method="GET" action="" class="filters-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="busqueda">Buscar</label>
                    <input type="text" id="busqueda" name="busqueda" 
                           placeholder="Nombre, correo o matrícula..."
                           value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                
                <div class="form-group">
                    <label for="rol">Filtrar por Rol</label>
                    <select id="rol" name="rol">
                        <option value="">Todos los roles</option>
                        <option value="administrador" <?php echo $filtro_rol === 'administrador' ? 'selected' : ''; ?>>Administrador</option>
                        <option value="docente" <?php echo $filtro_rol === 'docente' ? 'selected' : ''; ?>>Docente</option>
                        <option value="alumno" <?php echo $filtro_rol === 'alumno' ? 'selected' : ''; ?>>Alumno</option>
                        <option value="administrativo" <?php echo $filtro_rol === 'administrativo' ? 'selected' : ''; ?>>Administrativo</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="estado">Filtrar por Estado</label>
                    <select id="estado" name="estado">
                        <option value="">Todos los estados</option>
                        <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                        <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="usuarios.php" class="btn btn-secondary">Limpiar</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Tabla de usuarios -->
    <div class="card">
        <div class="card-header">
            <h2>Usuarios Registrados (<?php echo count($usuarios); ?>)</h2>
        </div>
        
        <?php if (empty($usuarios)): ?>
            <p>No se encontraron usuarios con los filtros seleccionados.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre Completo</th>
                            <th>Matrícula</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?php echo $usuario['id_usuario']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido_paterno'] . ' ' . $usuario['apellido_materno']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($usuario['matricula'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $usuario['rol']; ?>">
                                        <?php echo ucfirst($usuario['rol']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-estado-<?php echo $usuario['estado_cuenta']; ?>">
                                        <?php echo ucfirst($usuario['estado_cuenta']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></td>
                                <td class="actions-cell">
                                    <a href="usuario_editar.php?id=<?php echo $usuario['id_usuario']; ?>" 
                                       class="btn btn-sm btn-primary" title="Editar">
                                        ✏️
                                    </a>
                                    
                                    <?php if ($usuario['estado_cuenta'] === 'pendiente'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="id_usuario" value="<?php echo $usuario['id_usuario']; ?>">
                                            <input type="hidden" name="accion" value="aprobar">
                                            <button type="submit" class="btn btn-sm btn-success" title="Aprobar">
                                                ✓
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="id_usuario" value="<?php echo $usuario['id_usuario']; ?>">
                                            <input type="hidden" name="accion" value="rechazar">
                                            <button type="submit" class="btn btn-sm btn-warning" title="Rechazar">
                                                ✗
                                            </button>
                                        </form>
                                    <?php elseif ($usuario['estado_cuenta'] === 'activo'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="id_usuario" value="<?php echo $usuario['id_usuario']; ?>">
                                            <input type="hidden" name="accion" value="desactivar">
                                            <button type="submit" class="btn btn-sm btn-warning" title="Desactivar"
                                                    data-confirm="¿Está seguro de desactivar este usuario?">
                                                🔒
                                            </button>
                                        </form>
                                    <?php elseif ($usuario['estado_cuenta'] === 'inactivo'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="id_usuario" value="<?php echo $usuario['id_usuario']; ?>">
                                            <input type="hidden" name="accion" value="activar">
                                            <button type="submit" class="btn btn-sm btn-success" title="Activar">
                                                🔓
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($usuario['id_usuario'] !== $_SESSION['usuario_id']): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="id_usuario" value="<?php echo $usuario['id_usuario']; ?>">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"
                                                    data-confirm="¿Está seguro de eliminar este usuario? Esta acción no se puede deshacer.">
                                                🗑️
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
