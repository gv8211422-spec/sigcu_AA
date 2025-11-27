<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_rol('docente');

$titulo_pagina = 'Gestión de Actividades';
include 'includes/header.php';
include 'includes/navbar.php';

$id_docente = $_SESSION['usuario_id'];
$filtro_grupo = intval($_GET['grupo'] ?? 0);
$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario de nueva actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'crear') {
        $id_grupo = intval($_POST['id_grupo']);
        $titulo = limpiar_entrada($_POST['titulo']);
        $descripcion = limpiar_entrada($_POST['descripcion']);
        $tipo_actividad = limpiar_entrada($_POST['tipo_actividad']);
        $fecha_entrega = $_POST['fecha_entrega'];
        $valor_maximo = floatval($_POST['valor_maximo']);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO actividades_academicas 
                (id_grupo, id_docente, titulo, descripcion, tipo_actividad, fecha_entrega, valor_maximo)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id_grupo, $id_docente, $titulo, $descripcion, $tipo_actividad, $fecha_entrega, $valor_maximo]);
            
            registrar_historial($pdo, $id_docente, 'Crear actividad', "Actividad: $titulo en grupo $id_grupo");
            $mensaje = 'Actividad creada exitosamente.';
            $tipo_mensaje = 'success';
        } catch (PDOException $e) {
            $mensaje = 'Error al crear actividad: ' . $e->getMessage();
            $tipo_mensaje = 'error';
            error_log($e->getMessage());
        }
    } elseif ($_POST['accion'] === 'eliminar') {
        $id_actividad = intval($_POST['id_actividad']);
        try {
            $stmt = $pdo->prepare("UPDATE actividades_academicas SET estado = 'cancelado' WHERE id_actividad = ?");
            $stmt->execute([$id_actividad]);
            
            registrar_historial($pdo, $id_docente, 'Cancelar actividad', "Actividad ID: $id_actividad");
            $mensaje = 'Actividad cancelada exitosamente.';
            $tipo_mensaje = 'success';
        } catch (PDOException $e) {
            $mensaje = 'Error al cancelar actividad.';
            $tipo_mensaje = 'error';
            error_log($e->getMessage());
        }
    }
}

// Obtener grupos del docente
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT g.id_grupo, g.nombre_grupo, m.nombre_materia
        FROM grupos g
        INNER JOIN materias m ON g.id_materia = m.id_materia
        WHERE g.id_grupo IN (
            SELECT DISTINCT id_grupo FROM actividades_academicas WHERE id_docente = ?
        )
        ORDER BY m.nombre_materia, g.nombre_grupo
    ");
    $stmt->execute([$id_docente]);
    $grupos = $stmt->fetchAll();
    
    // Obtener actividades
    $sql = "
        SELECT aa.*, g.nombre_grupo, m.nombre_materia,
               COUNT(ca.id_calificacion_actividad) as alumnos_calificados,
               (SELECT COUNT(*) FROM inscripciones WHERE id_grupo = aa.id_grupo AND estado = 'activa') as total_alumnos
        FROM actividades_academicas aa
        INNER JOIN grupos g ON aa.id_grupo = g.id_grupo
        INNER JOIN materias m ON g.id_materia = m.id_materia
        LEFT JOIN calificaciones_actividades ca ON aa.id_actividad = ca.id_actividad
        WHERE aa.id_docente = ?
    ";
    
    $params = [$id_docente];
    
    if ($filtro_grupo > 0) {
        $sql .= " AND aa.id_grupo = ?";
        $params[] = $filtro_grupo;
    }
    
    $sql .= " GROUP BY aa.id_actividad ORDER BY aa.fecha_entrega DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $actividades = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    $grupos = [];
    $actividades = [];
}
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Gestión de Actividades</h1>
        <p>Cree y administre actividades académicas</p>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <!-- Formulario Nueva Actividad -->
    <div class="card">
        <h2>Nueva Actividad</h2>
        <form method="POST" action="">
            <input type="hidden" name="accion" value="crear">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="id_grupo">Grupo *</label>
                    <select id="id_grupo" name="id_grupo" required>
                        <option value="">Seleccione un grupo</option>
                        <?php foreach ($grupos as $g): ?>
                            <option value="<?php echo $g['id_grupo']; ?>" 
                                    <?php echo $g['id_grupo'] == $filtro_grupo ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g['nombre_materia'] . ' - ' . $g['nombre_grupo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="tipo_actividad">Tipo de Actividad *</label>
                    <select id="tipo_actividad" name="tipo_actividad" required>
                        <option value="tarea">Tarea</option>
                        <option value="practica">Práctica</option>
                        <option value="proyecto">Proyecto</option>
                        <option value="exposicion">Exposición</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="titulo">Título de la Actividad *</label>
                <input type="text" id="titulo" name="titulo" required>
            </div>
            
            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="3"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="fecha_entrega">Fecha de Entrega *</label>
                    <input type="datetime-local" id="fecha_entrega" name="fecha_entrega" required>
                </div>
                
                <div class="form-group">
                    <label for="valor_maximo">Valor Máximo (Puntos) *</label>
                    <input type="number" id="valor_maximo" name="valor_maximo" 
                           min="0" max="100" step="0.01" value="10" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Crear Actividad</button>
        </form>
    </div>
    
    <!-- Filtro -->
    <div class="card">
        <form method="GET" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="grupo">Filtrar por Grupo</label>
                    <select id="grupo" name="grupo" onchange="this.form.submit()">
                        <option value="0">Todos los grupos</option>
                        <?php foreach ($grupos as $g): ?>
                            <option value="<?php echo $g['id_grupo']; ?>" 
                                    <?php echo $g['id_grupo'] == $filtro_grupo ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g['nombre_materia'] . ' - ' . $g['nombre_grupo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Lista de Actividades -->
    <div class="card">
        <h2>Actividades Registradas (<?php echo count($actividades); ?>)</h2>
        
        <?php if (empty($actividades)): ?>
            <p>No hay actividades registradas.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Grupo</th>
                        <th>Tipo</th>
                        <th>Fecha Entrega</th>
                        <th>Valor</th>
                        <th>Calificados</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actividades as $act): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($act['titulo']); ?></td>
                            <td><?php echo htmlspecialchars($act['nombre_grupo']); ?></td>
                            <td><?php echo ucfirst($act['tipo_actividad']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($act['fecha_entrega'])); ?></td>
                            <td><?php echo number_format($act['valor_maximo'], 2); ?></td>
                            <td><?php echo $act['alumnos_calificados']; ?> / <?php echo $act['total_alumnos']; ?></td>
                            <td>
                                <span class="badge badge-estado-<?php echo $act['estado']; ?>">
                                    <?php echo ucfirst($act['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="actividad_calificar.php?id=<?php echo $act['id_actividad']; ?>" 
                                   class="btn btn-sm btn-primary">Calificar</a>
                                <?php if ($act['estado'] === 'activo'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_actividad" value="<?php echo $act['id_actividad']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                                data-confirm="¿Cancelar esta actividad?">Cancelar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
