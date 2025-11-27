<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_rol(['alumno', 'docente']);

// Para alumnos, mostrar sus propias calificaciones
// Para docentes, mostrar estadísticas generales

$titulo_pagina = 'Mis Calificaciones';
include 'includes/header.php';
include 'includes/navbar.php';

$es_alumno = $_SESSION['rol'] === 'alumno';
$id_usuario = $_SESSION['usuario_id'];

if ($es_alumno) {
    // Vista de alumno - sus propias calificaciones
    try {
        $stmt = $pdo->prepare("
            SELECT m.nombre_materia, m.clave_materia, m.creditos,
                   m.porcentaje_examenes, m.porcentaje_actividades,
                   g.nombre_grupo, g.semestre,
                   c.calificacion_final, c.estado_materia
            FROM calificaciones c
            INNER JOIN materias m ON c.id_materia = m.id_materia
            INNER JOIN inscripciones i ON i.id_alumno = c.id_alumno
            INNER JOIN grupos g ON i.id_grupo = g.id_grupo AND g.id_materia = m.id_materia
            WHERE c.id_alumno = ?
            ORDER BY m.nombre_materia
        ");
        $stmt->execute([$id_usuario]);
        $materias = $stmt->fetchAll();
        
        // Obtener calificaciones detalladas por materia
        $detalles_por_materia = [];
        
        foreach ($materias as $materia) {
            // Obtener exámenes
            $stmt = $pdo->prepare("
                SELECT e.tipo_evaluacion, e.calificacion, e.fecha_registro
                FROM examenes e
                INNER JOIN calificaciones c ON e.id_calificacion = c.id_calificacion
                WHERE c.id_alumno = ? AND c.id_materia = ?
                ORDER BY FIELD(e.tipo_evaluacion, 'primer_parcial', 'segundo_parcial', 'tercer_parcial', 'examen_final', 'extraordinario', 'titulo_suficiencia')
            ");
            $stmt->execute([$id_usuario, $materia['id_materia']]);
            $examenes = $stmt->fetchAll();
            
            // Obtener actividades calificadas
            $stmt = $pdo->prepare("
                SELECT aa.titulo, aa.tipo_actividad, aa.valor_maximo,
                       ca.calificacion_obtenida, ca.comentarios_docente, ca.fecha_calificacion
                FROM calificaciones_actividades ca
                INNER JOIN actividades_academicas aa ON ca.id_actividad = aa.id_actividad
                INNER JOIN grupos g ON aa.id_grupo = g.id_grupo
                WHERE ca.id_alumno = ? AND g.id_materia = ?
                ORDER BY ca.fecha_calificacion DESC
            ");
            $stmt->execute([$id_usuario, $materia['id_materia']]);
            $actividades = $stmt->fetchAll();
            
            $detalles_por_materia[$materia['id_materia']] = [
                'examenes' => $examenes,
                'actividades' => $actividades
            ];
        }
        
        // Calcular promedio general
        $stmt = $pdo->prepare("
            SELECT AVG(calificacion_final) as promedio 
            FROM calificaciones 
            WHERE id_alumno = ? AND calificacion_final > 0
        ");
        $stmt->execute([$id_usuario]);
        $promedio_general = $stmt->fetchColumn();
        
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $materias = [];
    }
    ?>
    
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Mis Calificaciones</h1>
            <p><?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido_paterno']; ?></p>
            <p><strong>Matrícula:</strong> <?php echo $_SESSION['matricula']; ?></p>
        </div>
        
        <?php if ($promedio_general): ?>
            <div class="card">
                <h2>Promedio General</h2>
                <div class="promedio-general">
                    <?php echo number_format($promedio_general, 2); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($materias)): ?>
            <div class="card">
                <p>No tiene calificaciones registradas.</p>
            </div>
        <?php else: ?>
            <?php foreach ($materias as $materia): ?>
                <div class="card">
                    <div class="materia-header">
                        <h2><?php echo htmlspecialchars($materia['nombre_materia']); ?></h2>
                        <span class="badge badge-estado-<?php echo $materia['estado_materia']; ?>">
                            <?php echo ucfirst($materia['estado_materia']); ?>
                        </span>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Clave:</strong> <?php echo htmlspecialchars($materia['clave_materia']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Grupo:</strong> <?php echo htmlspecialchars($materia['nombre_grupo']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Créditos:</strong> <?php echo $materia['creditos']; ?>
                        </div>
                        <div class="info-item">
                            <strong>Calificación Final:</strong> 
                            <span class="calificacion-final">
                                <?php echo $materia['calificacion_final'] > 0 ? 
                                          number_format($materia['calificacion_final'], 2) : 'N/A'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Exámenes -->
                    <h3>Exámenes (<?php echo $materia['porcentaje_examenes']; ?>%)</h3>
                    <?php 
                    $examenes = $detalles_por_materia[$materia['id_materia']]['examenes'];
                    if (empty($examenes)): 
                    ?>
                        <p>No hay exámenes registrados.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tipo de Examen</th>
                                    <th>Calificación</th>
                                    <th>Fecha de Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($examenes as $examen): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            $tipos = [
                                                'primer_parcial' => '1er Parcial',
                                                'segundo_parcial' => '2do Parcial',
                                                'tercer_parcial' => '3er Parcial',
                                                'examen_final' => 'Examen Final',
                                                'extraordinario' => 'Extraordinario',
                                                'titulo_suficiencia' => 'Título de Suficiencia'
                                            ];
                                            echo $tipos[$examen['tipo_evaluacion']] ?? $examen['tipo_evaluacion'];
                                            ?>
                                        </td>
                                        <td><strong><?php echo number_format($examen['calificacion'], 2); ?></strong></td>
                                        <td><?php echo date('d/m/Y', strtotime($examen['fecha_registro'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <!-- Actividades -->
                    <h3>Actividades (<?php echo $materia['porcentaje_actividades']; ?>%)</h3>
                    <?php 
                    $actividades = $detalles_por_materia[$materia['id_materia']]['actividades'];
                    if (empty($actividades)): 
                    ?>
                        <p>No hay actividades calificadas.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Actividad</th>
                                    <th>Tipo</th>
                                    <th>Calificación</th>
                                    <th>Comentarios</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($actividades as $actividad): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($actividad['titulo']); ?></td>
                                        <td><?php echo ucfirst($actividad['tipo_actividad']); ?></td>
                                        <td>
                                            <strong><?php echo number_format($actividad['calificacion_obtenida'], 2); ?></strong>
                                            / <?php echo number_format($actividad['valor_maximo'], 2); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($actividad['comentarios_docente'] ?? '-'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($actividad['fecha_calificacion'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <style>
    .promedio-general {
        font-size: 3rem;
        font-weight: bold;
        color: #2563eb;
        text-align: center;
        padding: 2rem;
    }
    
    .calificacion-final {
        font-size: 1.25rem;
        font-weight: bold;
        color: #16a34a;
    }
    
    .materia-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    </style>
    
    <?php
} else {
    // Vista de docente - estadísticas generales de sus grupos
    echo "<div class='dashboard-container'>";
    echo "<div class='dashboard-header'><h1>Estadísticas de Calificaciones</h1></div>";
    echo "<div class='card'><p>Módulo de estadísticas para docentes en desarrollo.</p></div>";
    echo "</div>";
}
?>

<?php include 'includes/footer.php'; ?>
