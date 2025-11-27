<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_autenticacion();

$titulo_pagina = 'Mensajes';
include 'includes/header.php';
include 'includes/navbar.php';

$id_usuario = $_SESSION['usuario_id'];
$vista = $_GET['vista'] ?? 'recibidos'; // recibidos, enviados, nuevo
$mensaje_id = $_GET['id'] ?? null;

// Procesar envío de mensaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_mensaje'])) {
    $destinatario = limpiar_entrada($_POST['destinatario']);
    $asunto = limpiar_entrada($_POST['asunto']);
    $contenido = limpiar_entrada($_POST['contenido']);
    
    try {
        // Verificar que el destinatario existe
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$destinatario]);
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO mensajes (id_remitente, id_destinatario, asunto, contenido, fecha_envio, estado_mensaje)
                VALUES (?, ?, ?, ?, NOW(), 'no_leido')
            ");
            $stmt->execute([$id_usuario, $destinatario, $asunto, $contenido]);
            
            registrar_historial($pdo, $id_usuario, 'envio_mensaje', "Envió mensaje a usuario ID: $destinatario");
            
            $_SESSION['mensaje'] = "Mensaje enviado correctamente.";
            $_SESSION['tipo_mensaje'] = 'success';
            header("Location: mensajes.php?vista=enviados");
            exit;
        } else {
            $_SESSION['mensaje'] = "El destinatario seleccionado no existe.";
            $_SESSION['tipo_mensaje'] = 'error';
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['mensaje'] = "Error al enviar el mensaje.";
        $_SESSION['tipo_mensaje'] = 'error';
    }
}

// Marcar mensaje como leído
if (isset($_GET['accion']) && $_GET['accion'] === 'marcar_leido' && $mensaje_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE mensajes 
            SET estado_mensaje = 'leido', fecha_lectura = NOW() 
            WHERE id_mensaje = ? AND id_destinatario = ?
        ");
        $stmt->execute([$mensaje_id, $id_usuario]);
        header("Location: mensajes.php?vista=recibidos&id=$mensaje_id");
        exit;
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// Eliminar mensaje
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && $mensaje_id) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM mensajes 
            WHERE id_mensaje = ? AND (id_remitente = ? OR id_destinatario = ?)
        ");
        $stmt->execute([$mensaje_id, $id_usuario, $id_usuario]);
        
        registrar_historial($pdo, $id_usuario, 'eliminacion_mensaje', "Eliminó mensaje ID: $mensaje_id");
        
        $_SESSION['mensaje'] = "Mensaje eliminado correctamente.";
        $_SESSION['tipo_mensaje'] = 'success';
        header("Location: mensajes.php?vista=$vista");
        exit;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['mensaje'] = "Error al eliminar el mensaje.";
        $_SESSION['tipo_mensaje'] = 'error';
    }
}

// Obtener conteo de mensajes no leídos
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM mensajes 
        WHERE id_destinatario = ? AND estado_mensaje = 'no_leido'
    ");
    $stmt->execute([$id_usuario]);
    $mensajes_no_leidos = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $mensajes_no_leidos = 0;
}
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Mensajería Interna</h1>
        <a href="mensajes.php?vista=nuevo" class="btn btn-primary">+ Nuevo Mensaje</a>
    </div>
    
    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="alert alert-<?php echo $_SESSION['tipo_mensaje']; ?>">
            <?php 
            echo $_SESSION['mensaje'];
            unset($_SESSION['mensaje']);
            unset($_SESSION['tipo_mensaje']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="mensajes-container">
        <!-- Menú lateral -->
        <div class="mensajes-sidebar">
            <nav class="mensajes-nav">
                <a href="mensajes.php?vista=recibidos" class="<?php echo $vista === 'recibidos' ? 'active' : ''; ?>">
                    📥 Recibidos
                    <?php if ($mensajes_no_leidos > 0): ?>
                        <span class="badge badge-primary"><?php echo $mensajes_no_leidos; ?></span>
                    <?php endif; ?>
                </a>
                <a href="mensajes.php?vista=enviados" class="<?php echo $vista === 'enviados' ? 'active' : ''; ?>">
                    📤 Enviados
                </a>
                <a href="mensajes.php?vista=nuevo" class="<?php echo $vista === 'nuevo' ? 'active' : ''; ?>">
                    ✏️ Nuevo Mensaje
                </a>
            </nav>
        </div>
        
        <!-- Contenido principal -->
        <div class="mensajes-content">
            <?php if ($vista === 'nuevo'): ?>
                <!-- Formulario para nuevo mensaje -->
                <div class="card">
                    <h2>Redactar Mensaje</h2>
                    <form method="POST" action="mensajes.php">
                        <div class="form-group">
                            <label for="destinatario">Destinatario:</label>
                            <select id="destinatario" name="destinatario" class="form-control" required>
                                <option value="">Seleccione un usuario...</option>
                                <?php
                                try {
                                    // Obtener todos los usuarios excepto el actual
                                    $stmt = $pdo->prepare("
                                        SELECT id_usuario, nombre, apellido_paterno, apellido_materno, matricula, rol
                                        FROM usuarios 
                                        WHERE id_usuario != ? AND estado_cuenta = 'activo'
                                        ORDER BY rol, apellido_paterno, nombre
                                    ");
                                    $stmt->execute([$id_usuario]);
                                    $usuarios = $stmt->fetchAll();
                                    
                                    $rol_actual = '';
                                    foreach ($usuarios as $usuario) {
                                        if ($rol_actual !== $usuario['rol']) {
                                            if ($rol_actual !== '') echo '</optgroup>';
                                            $rol_actual = $usuario['rol'];
                                            echo "<optgroup label='" . ucfirst($rol_actual) . "s'>";
                                        }
                                        $nombre_completo = $usuario['nombre'] . ' ' . 
                                                          $usuario['apellido_paterno'] . ' ' . 
                                                          $usuario['apellido_materno'];
                                        echo "<option value='{$usuario['id_usuario']}'>" . 
                                             htmlspecialchars($nombre_completo) . 
                                             " ({$usuario['matricula']})</option>";
                                    }
                                    if ($rol_actual !== '') echo '</optgroup>';
                                } catch (PDOException $e) {
                                    error_log($e->getMessage());
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="asunto">Asunto:</label>
                            <input type="text" id="asunto" name="asunto" class="form-control" required maxlength="200">
                        </div>
                        
                        <div class="form-group">
                            <label for="contenido">Mensaje:</label>
                            <textarea id="contenido" name="contenido" class="form-control" rows="10" required></textarea>
                        </div>
                        
                        <button type="submit" name="enviar_mensaje" class="btn btn-primary">Enviar Mensaje</button>
                        <a href="mensajes.php?vista=recibidos" class="btn btn-secondary">Cancelar</a>
                    </form>
                </div>
                
            <?php elseif ($vista === 'recibidos'): ?>
                <!-- Lista de mensajes recibidos -->
                <h2>Mensajes Recibidos</h2>
                <?php
                try {
                    if ($mensaje_id) {
                        // Ver mensaje específico
                        $stmt = $pdo->prepare("
                            SELECT m.*, 
                                   u.nombre, u.apellido_paterno, u.apellido_materno, u.matricula
                            FROM mensajes m
                            INNER JOIN usuarios u ON m.id_remitente = u.id_usuario
                            WHERE m.id_mensaje = ? AND m.id_destinatario = ?
                        ");
                        $stmt->execute([$mensaje_id, $id_usuario]);
                        $mensaje = $stmt->fetch();
                        
                        if ($mensaje):
                            // Marcar como leído si no lo está
                            if ($mensaje['estado_mensaje'] === 'no_leido') {
                                $stmt = $pdo->prepare("
                                    UPDATE mensajes 
                                    SET estado_mensaje = 'leido', fecha_lectura = NOW() 
                                    WHERE id_mensaje = ?
                                ");
                                $stmt->execute([$mensaje_id]);
                            }
                        ?>
                            <div class="card">
                                <div class="mensaje-detalle">
                                    <div class="mensaje-header-detalle">
                                        <h3><?php echo htmlspecialchars($mensaje['asunto']); ?></h3>
                                        <a href="mensajes.php?vista=recibidos" class="btn btn-secondary">← Volver</a>
                                    </div>
                                    
                                    <div class="mensaje-meta">
                                        <p><strong>De:</strong> 
                                            <?php echo htmlspecialchars($mensaje['nombre'] . ' ' . 
                                                      $mensaje['apellido_paterno'] . ' ' . 
                                                      $mensaje['apellido_materno']); ?>
                                            (<?php echo htmlspecialchars($mensaje['matricula']); ?>)
                                        </p>
                                        <p><strong>Fecha:</strong> 
                                            <?php echo date('d/m/Y H:i', strtotime($mensaje['fecha_envio'])); ?>
                                        </p>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="mensaje-contenido">
                                        <?php echo nl2br(htmlspecialchars($mensaje['contenido'])); ?>
                                    </div>
                                    
                                    <div class="mensaje-acciones">
                                        <a href="mensajes.php?vista=nuevo&responder=<?php echo $mensaje['id_remitente']; ?>" 
                                           class="btn btn-primary">Responder</a>
                                        <a href="mensajes.php?vista=recibidos&accion=eliminar&id=<?php echo $mensaje['id_mensaje']; ?>" 
                                           class="btn btn-danger" 
                                           data-confirm="¿Está seguro de eliminar este mensaje?">Eliminar</a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <p>Mensaje no encontrado.</p>
                            </div>
                        <?php endif;
                    } else {
                        // Lista de mensajes recibidos
                        $stmt = $pdo->prepare("
                            SELECT m.*, 
                                   u.nombre, u.apellido_paterno, u.matricula
                            FROM mensajes m
                            INNER JOIN usuarios u ON m.id_remitente = u.id_usuario
                            WHERE m.id_destinatario = ?
                            ORDER BY m.fecha_envio DESC
                        ");
                        $stmt->execute([$id_usuario]);
                        $mensajes = $stmt->fetchAll();
                        
                        if (empty($mensajes)):
                        ?>
                            <div class="card">
                                <p>No tiene mensajes recibidos.</p>
                            </div>
                        <?php else: ?>
                            <div class="mensajes-lista">
                                <?php foreach ($mensajes as $msg): ?>
                                    <div class="mensaje-item <?php echo $msg['estado_mensaje'] === 'no_leido' ? 'no-leido' : ''; ?>">
                                        <div class="mensaje-remitente">
                                            <strong><?php echo htmlspecialchars($msg['nombre'] . ' ' . $msg['apellido_paterno']); ?></strong>
                                            <span class="mensaje-matricula">(<?php echo htmlspecialchars($msg['matricula']); ?>)</span>
                                        </div>
                                        <a href="mensajes.php?vista=recibidos&id=<?php echo $msg['id_mensaje']; ?>" class="mensaje-asunto">
                                            <?php echo htmlspecialchars($msg['asunto']); ?>
                                        </a>
                                        <div class="mensaje-fecha">
                                            <?php echo date('d/m/Y H:i', strtotime($msg['fecha_envio'])); ?>
                                        </div>
                                        <?php if ($msg['estado_mensaje'] === 'no_leido'): ?>
                                            <span class="badge badge-primary">Nuevo</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif;
                    }
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    echo "<div class='card'><p>Error al cargar los mensajes.</p></div>";
                }
                ?>
                
            <?php elseif ($vista === 'enviados'): ?>
                <!-- Lista de mensajes enviados -->
                <h2>Mensajes Enviados</h2>
                <?php
                try {
                    if ($mensaje_id) {
                        // Ver mensaje específico
                        $stmt = $pdo->prepare("
                            SELECT m.*, 
                                   u.nombre, u.apellido_paterno, u.apellido_materno, u.matricula
                            FROM mensajes m
                            INNER JOIN usuarios u ON m.id_destinatario = u.id_usuario
                            WHERE m.id_mensaje = ? AND m.id_remitente = ?
                        ");
                        $stmt->execute([$mensaje_id, $id_usuario]);
                        $mensaje = $stmt->fetch();
                        
                        if ($mensaje):
                        ?>
                            <div class="card">
                                <div class="mensaje-detalle">
                                    <div class="mensaje-header-detalle">
                                        <h3><?php echo htmlspecialchars($mensaje['asunto']); ?></h3>
                                        <a href="mensajes.php?vista=enviados" class="btn btn-secondary">← Volver</a>
                                    </div>
                                    
                                    <div class="mensaje-meta">
                                        <p><strong>Para:</strong> 
                                            <?php echo htmlspecialchars($mensaje['nombre'] . ' ' . 
                                                      $mensaje['apellido_paterno'] . ' ' . 
                                                      $mensaje['apellido_materno']); ?>
                                            (<?php echo htmlspecialchars($mensaje['matricula']); ?>)
                                        </p>
                                        <p><strong>Fecha de envío:</strong> 
                                            <?php echo date('d/m/Y H:i', strtotime($mensaje['fecha_envio'])); ?>
                                        </p>
                                        <?php if ($mensaje['estado_mensaje'] === 'leido'): ?>
                                            <p><strong>Fecha de lectura:</strong> 
                                                <?php echo date('d/m/Y H:i', strtotime($mensaje['fecha_lectura'])); ?>
                                            </p>
                                        <?php else: ?>
                                            <p><span class="badge badge-warning">No leído</span></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="mensaje-contenido">
                                        <?php echo nl2br(htmlspecialchars($mensaje['contenido'])); ?>
                                    </div>
                                    
                                    <div class="mensaje-acciones">
                                        <a href="mensajes.php?vista=enviados&accion=eliminar&id=<?php echo $mensaje['id_mensaje']; ?>" 
                                           class="btn btn-danger" 
                                           data-confirm="¿Está seguro de eliminar este mensaje?">Eliminar</a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <p>Mensaje no encontrado.</p>
                            </div>
                        <?php endif;
                    } else {
                        // Lista de mensajes enviados
                        $stmt = $pdo->prepare("
                            SELECT m.*, 
                                   u.nombre, u.apellido_paterno, u.matricula
                            FROM mensajes m
                            INNER JOIN usuarios u ON m.id_destinatario = u.id_usuario
                            WHERE m.id_remitente = ?
                            ORDER BY m.fecha_envio DESC
                        ");
                        $stmt->execute([$id_usuario]);
                        $mensajes = $stmt->fetchAll();
                        
                        if (empty($mensajes)):
                        ?>
                            <div class="card">
                                <p>No ha enviado mensajes.</p>
                            </div>
                        <?php else: ?>
                            <div class="mensajes-lista">
                                <?php foreach ($mensajes as $msg): ?>
                                    <div class="mensaje-item">
                                        <div class="mensaje-remitente">
                                            <strong><?php echo htmlspecialchars($msg['nombre'] . ' ' . $msg['apellido_paterno']); ?></strong>
                                            <span class="mensaje-matricula">(<?php echo htmlspecialchars($msg['matricula']); ?>)</span>
                                        </div>
                                        <a href="mensajes.php?vista=enviados&id=<?php echo $msg['id_mensaje']; ?>" class="mensaje-asunto">
                                            <?php echo htmlspecialchars($msg['asunto']); ?>
                                        </a>
                                        <div class="mensaje-fecha">
                                            <?php echo date('d/m/Y H:i', strtotime($msg['fecha_envio'])); ?>
                                        </div>
                                        <?php if ($msg['estado_mensaje'] === 'leido'): ?>
                                            <span class="badge badge-success">Leído</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">No leído</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif;
                    }
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    echo "<div class='card'><p>Error al cargar los mensajes.</p></div>";
                }
                ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.mensajes-container {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.mensajes-sidebar {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    height: fit-content;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.mensajes-nav {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.mensajes-nav a {
    padding: 0.75rem 1rem;
    text-decoration: none;
    color: #374151;
    border-radius: 6px;
    transition: background-color 0.2s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mensajes-nav a:hover {
    background-color: #f3f4f6;
}

.mensajes-nav a.active {
    background-color: #2563eb;
    color: white;
}

.mensajes-content {
    min-height: 400px;
}

.mensajes-lista {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.mensaje-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: grid;
    grid-template-columns: 200px 1fr auto auto;
    gap: 1rem;
    align-items: center;
    transition: background-color 0.2s;
}

.mensaje-item:hover {
    background-color: #f9fafb;
}

.mensaje-item:last-child {
    border-bottom: none;
}

.mensaje-item.no-leido {
    background-color: #eff6ff;
    font-weight: 600;
}

.mensaje-remitente {
    display: flex;
    flex-direction: column;
}

.mensaje-matricula {
    font-size: 0.875rem;
    color: #6b7280;
}

.mensaje-asunto {
    text-decoration: none;
    color: #1f2937;
    font-weight: 500;
}

.mensaje-asunto:hover {
    color: #2563eb;
    text-decoration: underline;
}

.mensaje-fecha {
    font-size: 0.875rem;
    color: #6b7280;
}

.mensaje-detalle {
    padding: 1rem;
}

.mensaje-header-detalle {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.mensaje-meta {
    background-color: #f9fafb;
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.mensaje-meta p {
    margin: 0.5rem 0;
}

.mensaje-contenido {
    padding: 1.5rem 0;
    line-height: 1.6;
}

.mensaje-acciones {
    margin-top: 1.5rem;
    display: flex;
    gap: 0.5rem;
}

@media (max-width: 768px) {
    .mensajes-container {
        grid-template-columns: 1fr;
    }
    
    .mensaje-item {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
