<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_rol('docente');

$titulo_pagina = 'Calificaciones - Exámenes';
include 'includes/header.php';
include 'includes/navbar.php';

$id_docente = $_SESSION['usuario_id'];
$filtro_grupo = intval($_GET['grupo'] ?? 0);
$mensaje = '';
$tipo_mensaje = '';

// Procesar calificaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_grupo = intval($_POST['id_grupo']);
    $tipo_evaluacion = limpiar_entrada($_POST['tipo_evaluacion']);
    $calificaciones = $_POST['calificacion'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Obtener id_materia del grupo
        $stmt = $pdo->prepare("SELECT id_materia FROM grupos WHERE id_grupo = ?");
        $stmt->execute([$id_grupo]);
        $id_materia = $stmt->fetchColumn();
        
        foreach ($calificaciones as $id_alumno => $calificacion) {
            $calificacion = floatval($calificacion);
            
            if ($calificacion >= 0 && $calificacion <= 100) {
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
                        INSERT INTO calificaciones (id_alumno, id_materia, periodo_escolar)
                        VALUES (?, ?, '2025-1')
                    ");
                    $stmt->execute([$id_alumno, $id_materia]);
                    $id_calificacion = $pdo->lastInsertId();
                }
                
                // Insertar o actualizar examen
                $stmt = $pdo->prepare("
                    SELECT id_examen FROM examenes
                    WHERE id_calificacion = ? AND tipo_evaluacion = ?
                ");
                $stmt->execute([$id_calificacion, $tipo_evaluacion]);
                
                if ($stmt->fetchColumn()) {
                    // Actualizar
                    $stmt = $pdo->prepare("
                        UPDATE examenes 
                        SET calificacion = ?, id_docente_registro = ?, fecha_registro = NOW()
                        WHERE id_calificacion = ? AND tipo_evaluacion = ?
                    ");
                    $stmt->execute([$calificacion, $id_docente, $id_calificacion, $tipo_evaluacion]);
                } else {
                    // Insertar
                    $stmt = $pdo->prepare("
                        INSERT INTO examenes (id_calificacion, tipo_evaluacion, calificacion, id_docente_registro)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$id_calificacion, $tipo_evaluacion, $calificacion, $id_docente]);
                }
            }
        }
        
        $pdo->commit();
        registrar_historial($pdo, $id_docente, 'Registrar calificaciones', "Tipo: $tipo_evaluacion, Grupo: $id_grupo");
        $mensaje = 'Calificaciones guardadas exitosamente.';
        $tipo_mensaje = 'success';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensaje = 'Error al guardar calificaciones: ' . $e->getMessage();
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
            // Obtener alumnos y sus calificaciones
            $stmt = $pdo->prepare("
                SELECT u.id_usuario, u.nombre, u.apellido_paterno, u.apellido_materno, u.matricula,
                       c.id_calificacion,
                       (SELECT calificacion FROM examenes WHERE id_calificacion = c.id_calificacion AND tipo_evaluacion = 'primer_parcial') as primer_parcial,
                       (SELECT calificacion FROM examenes WHERE id_calificacion = c.id_calificacion AND tipo_evaluacion = 'segundo_parcial') as segundo_parcial,
                       (SELECT calificacion FROM examenes WHERE id_calificacion = c.id_calificacion AND tipo_evaluacion = 'tercer_parcial') as tercer_parcial,
                       (SELECT calificacion FROM examenes WHERE id_calificacion = c.id_calificacion AND tipo_evaluacion = 'examen_final') as examen_final,
                       (SELECT calificacion FROM examenes WHERE id_calificacion = c.id_calificacion AND tipo_evaluacion = 'extraordinario') as extraordinario
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
        <h1>Calificaciones de Exámenes</h1>
        <p>Registre las calificaciones de los exámenes</p>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <!-- Selector de Grupo -->
    <div class="card">
        <h2>Seleccione un Grupo</h2>
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
            </div>
        </form>
    </div>
    
    <?php if ($filtro_grupo > 0 && $grupo_seleccionado): ?>
        <!-- Formulario de Calificaciones -->
        <div class="card">
            <h2>Registrar Calificaciones - <?php echo htmlspecialchars($grupo_seleccionado['nombre_materia']); ?></h2>
            
            <form method="POST" action="">
                <input type="hidden" name="id_grupo" value="<?php echo $filtro_grupo; ?>">
                
                <div class="form-group">
                    <label for="tipo_evaluacion">Tipo de Evaluación</label>
                    <select id="tipo_evaluacion" name="tipo_evaluacion" required>
                        <option value="">Seleccione el tipo de examen</option>
                        <option value="primer_parcial">Primer Parcial</option>
                        <option value="segundo_parcial">Segundo Parcial</option>
                        <option value="tercer_parcial">Tercer Parcial</option>
                        <option value="examen_final">Examen Final</option>
                        <option value="extraordinario">Examen Extraordinario</option>
                        <option value="titulo_suficiencia">Título de Suficiencia</option>
                    </select>
                </div>
                
                <?php if (empty($alumnos)): ?>
                    <p>No hay alumnos inscritos en este grupo.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Matrícula</th>
                                <th>Nombre Completo</th>
                                <th>1er Parcial</th>
                                <th>2do Parcial</th>
                                <th>3er Parcial</th>
                                <th>Final</th>
                                <th>Extraord.</th>
                                <th>Calificación</th>
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
                                    <td><?php echo $alumno['primer_parcial'] ?? '-'; ?></td>
                                    <td><?php echo $alumno['segundo_parcial'] ?? '-'; ?></td>
                                    <td><?php echo $alumno['tercer_parcial'] ?? '-'; ?></td>
                                    <td><?php echo $alumno['examen_final'] ?? '-'; ?></td>
                                    <td><?php echo $alumno['extraordinario'] ?? '-'; ?></td>
                                    <td>
                                        <input type="number" 
                                               name="calificacion[<?php echo $alumno['id_usuario']; ?>]" 
                                               min="0" 
                                               max="100" 
                                               step="0.01" 
                                               placeholder="0-100"
                                               style="width: 100px;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Calificaciones</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
