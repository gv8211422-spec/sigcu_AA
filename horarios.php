<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_rol('administrativo');

$titulo_pagina = 'Gestión de Horarios';
include 'includes/header.php';
include 'includes/navbar.php';

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        if ($_POST['accion'] === 'agregar') {
            $id_grupo = intval($_POST['id_grupo']);
            $dia_semana = limpiar_entrada($_POST['dia_semana']);
            $hora_inicio = $_POST['hora_inicio'];
            $hora_fin = $_POST['hora_fin'];
            $aula = limpiar_entrada($_POST['aula']);
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO horarios (id_grupo, dia_semana, hora_inicio, hora_fin, aula)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$id_grupo, $dia_semana, $hora_inicio, $hora_fin, $aula]);
                
                registrar_historial($pdo, $_SESSION['usuario_id'], 'Agregar horario', "Grupo: $id_grupo, Día: $dia_semana");
                $mensaje = 'Horario agregado exitosamente.';
                $tipo_mensaje = 'success';
            } catch (PDOException $e) {
                $mensaje = 'Error al agregar horario: ' . $e->getMessage();
                $tipo_mensaje = 'error';
                error_log($e->getMessage());
            }
        } elseif ($_POST['accion'] === 'eliminar') {
            $id_horario = intval($_POST['id_horario']);
            
            try {
                $stmt = $pdo->prepare("DELETE FROM horarios WHERE id_horario = ?");
                $stmt->execute([$id_horario]);
                
                registrar_historial($pdo, $_SESSION['usuario_id'], 'Eliminar horario', "Horario ID: $id_horario");
                $mensaje = 'Horario eliminado exitosamente.';
                $tipo_mensaje = 'success';
            } catch (PDOException $e) {
                $mensaje = 'Error al eliminar horario.';
                $tipo_mensaje = 'error';
                error_log($e->getMessage());
            }
        }
    }
}

// Obtener todos los grupos
try {
    $stmt = $pdo->query("
        SELECT g.id_grupo, g.nombre_grupo, m.nombre_materia, g.semestre,
               COUNT(h.id_horario) as total_horarios
        FROM grupos g
        INNER JOIN materias m ON g.id_materia = m.id_materia
        LEFT JOIN horarios h ON g.id_grupo = h.id_grupo
        GROUP BY g.id_grupo
        ORDER BY m.nombre_materia, g.nombre_grupo
    ");
    $grupos = $stmt->fetchAll();
    
    // Obtener todos los horarios
    $stmt = $pdo->query("
        SELECT h.*, g.nombre_grupo, m.nombre_materia
        FROM horarios h
        INNER JOIN grupos g ON h.id_grupo = g.id_grupo
        INNER JOIN materias m ON g.id_materia = m.id_materia
        ORDER BY m.nombre_materia, g.nombre_grupo, 
                 FIELD(h.dia_semana, 'lunes', 'martes', 'miércoles', 'jueves', 'viernes'),
                 h.hora_inicio
    ");
    $horarios = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    $grupos = [];
    $horarios = [];
}
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Gestión de Horarios</h1>
        <p>Asigne horarios a los grupos</p>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <!-- Formulario Agregar Horario -->
    <div class="card">
        <h2>Agregar Nuevo Horario</h2>
        <form method="POST" action="">
            <input type="hidden" name="accion" value="agregar">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="id_grupo">Grupo *</label>
                    <select id="id_grupo" name="id_grupo" required>
                        <option value="">Seleccione un grupo</option>
                        <?php foreach ($grupos as $g): ?>
                            <option value="<?php echo $g['id_grupo']; ?>">
                                <?php echo htmlspecialchars($g['nombre_materia'] . ' - ' . $g['nombre_grupo'] . ' (' . $g['semestre'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="dia_semana">Día de la Semana *</label>
                    <select id="dia_semana" name="dia_semana" required>
                        <option value="">Seleccione un día</option>
                        <option value="lunes">Lunes</option>
                        <option value="martes">Martes</option>
                        <option value="miércoles">Miércoles</option>
                        <option value="jueves">Jueves</option>
                        <option value="viernes">Viernes</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="hora_inicio">Hora de Inicio *</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" required>
                </div>
                
                <div class="form-group">
                    <label for="hora_fin">Hora de Fin *</label>
                    <input type="time" id="hora_fin" name="hora_fin" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="aula">Aula *</label>
                <input type="text" id="aula" name="aula" placeholder="Ej: Aula 101, Lab 2, etc." required>
            </div>
            
            <button type="submit" class="btn btn-primary">Agregar Horario</button>
        </form>
    </div>
    
    <!-- Lista de Grupos -->
    <div class="card">
        <h2>Grupos y sus Horarios (<?php echo count($grupos); ?>)</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Materia</th>
                    <th>Grupo</th>
                    <th>Semestre</th>
                    <th>Horarios Asignados</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grupos as $grupo): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($grupo['nombre_materia']); ?></td>
                        <td><?php echo htmlspecialchars($grupo['nombre_grupo']); ?></td>
                        <td><?php echo htmlspecialchars($grupo['semestre']); ?></td>
                        <td><?php echo $grupo['total_horarios']; ?></td>
                        <td>
                            <?php if ($grupo['total_horarios'] > 0): ?>
                                <span class="badge badge-estado-activo">Con Horario</span>
                            <?php else: ?>
                                <span class="badge badge-estado-pendiente">Sin Horario</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Horarios Detallados -->
    <div class="card">
        <h2>Horarios Registrados (<?php echo count($horarios); ?>)</h2>
        <?php if (empty($horarios)): ?>
            <p>No hay horarios registrados.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Materia</th>
                        <th>Grupo</th>
                        <th>Día</th>
                        <th>Hora Inicio</th>
                        <th>Hora Fin</th>
                        <th>Aula</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($horarios as $horario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($horario['nombre_materia']); ?></td>
                            <td><?php echo htmlspecialchars($horario['nombre_grupo']); ?></td>
                            <td><?php echo ucfirst($horario['dia_semana']); ?></td>
                            <td><?php echo date('H:i', strtotime($horario['hora_inicio'])); ?></td>
                            <td><?php echo date('H:i', strtotime($horario['hora_fin'])); ?></td>
                            <td><?php echo htmlspecialchars($horario['aula']); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id_horario" value="<?php echo $horario['id_horario']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"
                                            data-confirm="¿Eliminar este horario?">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
