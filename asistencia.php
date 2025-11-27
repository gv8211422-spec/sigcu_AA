<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_rol('docente');

$titulo_pagina = 'Control de Asistencia';
include 'includes/header.php';
include 'includes/navbar.php';

$id_docente = $_SESSION['usuario_id'];
$filtro_grupo = intval($_GET['grupo'] ?? 0);
$fecha_clase = $_GET['fecha'] ?? date('Y-m-d');
$mensaje = '';
$tipo_mensaje = '';

// Procesar asistencia
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_grupo = intval($_POST['id_grupo']);
    $fecha = $_POST['fecha'];
    $asistencias = $_POST['asistencia'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Obtener id_materia del grupo
        $stmt = $pdo->prepare("SELECT id_materia FROM grupos WHERE id_grupo = ?");
        $stmt->execute([$id_grupo]);
        $id_materia = $stmt->fetchColumn();
        
        foreach ($asistencias as $id_alumno => $asistio) {
            // Obtener o crear registro en calificaciones
            $stmt = $pdo->prepare("
                SELECT id_calificacion FROM calificaciones
                WHERE id_alumno = ? AND id_materia = ?
            ");
            $stmt->execute([$id_alumno, $id_materia]);
            $id_calificacion = $stmt->fetchColumn();
            
            if (!$id_calificacion) {
                // Crear registro de calificación
                $stmt = $pdo->prepare("
                    INSERT INTO calificaciones (id_alumno, id_materia, periodo_escolar, total_clases_periodo, total_asistencias_periodo)
                    VALUES (?, ?, '2025-1', 1, ?)
                ");
                $stmt->execute([$id_alumno, $id_materia, $asistio === 'si' ? 1 : 0]);
            } else {
                // Actualizar contadores
                if ($asistio === 'si') {
                    $stmt = $pdo->prepare("
                        UPDATE calificaciones 
                        SET total_clases_periodo = total_clases_periodo + 1,
                            total_asistencias_periodo = total_asistencias_periodo + 1
                        WHERE id_calificacion = ?
                    ");
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE calificaciones 
                        SET total_clases_periodo = total_clases_periodo + 1
                        WHERE id_calificacion = ?
                    ");
                }
                $stmt->execute([$id_calificacion]);
            }
        }
        
        $pdo->commit();
        registrar_historial($pdo, $id_docente, 'Registrar asistencia', "Fecha: $fecha, Grupo: $id_grupo");
        $mensaje = 'Asistencia registrada exitosamente.';
        $tipo_mensaje = 'success';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensaje = 'Error al registrar asistencia: ' . $e->getMessage();
        $tipo_mensaje = 'error';
        error_log($e->getMessage());
    }
}

// Obtener grupos del docente
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT g.id_grupo, g.nombre_grupo, m.nombre_materia, m.id_materia
        FROM grupos g
        INNER JOIN materias m ON g.id_materia = m.id_materia
        WHERE g.id_grupo IN (
            SELECT DISTINCT id_grupo FROM actividades_academicas WHERE id_docente = ?
        )
        ORDER BY m.nombre_materia, g.nombre_grupo
    ");
    $stmt->execute([$id_docente]);
    $grupos = $stmt->fetchAll();
    
    $alumnos = [];
    $grupo_seleccionado = null;
    
    if ($filtro_grupo > 0) {
        // Obtener info del grupo
        $stmt = $pdo->prepare("
            SELECT g.*, m.nombre_materia, m.id_materia
            FROM grupos g
            INNER JOIN materias m ON g.id_materia = m.id_materia
            WHERE g.id_grupo = ?
        ");
        $stmt->execute([$filtro_grupo]);
        $grupo_seleccionado = $stmt->fetch();
        
        if ($grupo_seleccionado) {
            // Obtener alumnos y estadísticas de asistencia
            $stmt = $pdo->prepare("
                SELECT u.id_usuario, u.nombre, u.apellido_paterno, u.apellido_materno, u.matricula,
                       COALESCE(c.total_clases_periodo, 0) as total_clases,
                       COALESCE(c.total_asistencias_periodo, 0) as total_asistencias,
                       CASE 
                           WHEN c.total_clases_periodo > 0 
                           THEN ROUND((c.total_asistencias_periodo * 100.0 / c.total_clases_periodo), 2)
                           ELSE 0
                       END as porcentaje_asistencia
                FROM inscripciones i
                INNER JOIN usuarios u ON i.id_alumno = u.id_usuario
                LEFT JOIN calificaciones c ON c.id_alumno = u.id_usuario AND c.id_materia = ?
                WHERE i.id_grupo = ? AND i.estado = 'activa'
                ORDER BY u.apellido_paterno, u.apellido_materno, u.nombre
            ");
            $stmt->execute([$grupo_seleccionado['id_materia'], $filtro_grupo]);
            $alumnos = $stmt->fetchAll();
        }
    }
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    $grupos = [];
}
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Control de Asistencia</h1>
        <p>Registre la asistencia de sus alumnos</p>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <!-- Selector de Grupo y Fecha -->
    <div class="card">
        <h2>Seleccione Grupo y Fecha</h2>
        <form method="GET" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="grupo">Grupo</label>
                    <select id="grupo" name="grupo" onchange="this.form.submit()">
                        <option value="0">Seleccione un grupo</option>
                        <?php foreach ($grupos as $g): ?>
                            <option value="<?php echo $g['id_grupo']; ?>" 
                                    <?php echo $g['id_grupo'] == $filtro_grupo ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g['nombre_materia'] . ' - ' . $g['nombre_grupo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($filtro_grupo > 0): ?>
                    <div class="form-group">
                        <label for="fecha">Fecha de la Clase</label>
                        <input type="date" id="fecha" name="fecha" value="<?php echo $fecha_clase; ?>" onchange="this.form.submit()">
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <?php if ($filtro_grupo > 0 && $grupo_seleccionado): ?>
        <!-- Formulario de Asistencia -->
        <div class="card">
            <h2>Pasar Lista - <?php echo htmlspecialchars($grupo_seleccionado['nombre_materia']); ?></h2>
            <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($fecha_clase)); ?></p>
            
            <?php if (empty($alumnos)): ?>
                <p>No hay alumnos inscritos en este grupo.</p>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="id_grupo" value="<?php echo $filtro_grupo; ?>">
                    <input type="hidden" name="fecha" value="<?php echo $fecha_clase; ?>">
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Matrícula</th>
                                <th>Nombre Completo</th>
                                <th>Total Clases</th>
                                <th>Asistencias</th>
                                <th>% Asistencia</th>
                                <th>¿Asistió Hoy?</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alumnos as $alumno): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($alumno['matricula']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($alumno['apellido_paterno'] . ' ' . 
                                                                    $alumno['apellido_materno'] . ' ' . 
                                                                    $alumno['nombre']); ?>
                                    </td>
                                    <td><?php echo $alumno['total_clases']; ?></td>
                                    <td><?php echo $alumno['total_asistencias']; ?></td>
                                    <td><?php echo number_format($alumno['porcentaje_asistencia'], 2); ?>%</td>
                                    <td>
                                        <label>
                                            <input type="radio" name="asistencia[<?php echo $alumno['id_usuario']; ?>]" 
                                                   value="si" checked> Sí
                                        </label>
                                        <label style="margin-left: 15px;">
                                            <input type="radio" name="asistencia[<?php echo $alumno['id_usuario']; ?>]" 
                                                   value="no"> No
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Asistencia</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
