<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_rol('alumno');

$titulo_pagina = 'Mis Materias';
include 'includes/header.php';
include 'includes/navbar.php';

$id_alumno = $_SESSION['usuario_id'];

// Obtener materias inscritas
try {
    $stmt = $pdo->prepare("
        SELECT m.id_materia, m.nombre_materia, m.clave_materia, m.creditos, m.departamento, m.carrera,
               g.id_grupo, g.nombre_grupo, g.semestre,
               c.calificacion_final, c.estado_materia, c.total_clases_periodo, c.total_asistencias_periodo,
               CASE 
                   WHEN c.total_clases_periodo > 0 
                   THEN ROUND((c.total_asistencias_periodo * 100.0 / c.total_clases_periodo), 2)
                   ELSE 0
               END as porcentaje_asistencia,
               i.periodo_escolar, i.estado as estado_inscripcion
        FROM inscripciones i
        INNER JOIN grupos g ON i.id_grupo = g.id_grupo
        INNER JOIN materias m ON g.id_materia = m.id_materia
        LEFT JOIN calificaciones c ON c.id_alumno = i.id_alumno AND c.id_materia = m.id_materia
        WHERE i.id_alumno = ? AND i.estado = 'activa'
        ORDER BY m.nombre_materia
    ");
    $stmt->execute([$id_alumno]);
    $materias = $stmt->fetchAll();
    
    // Obtener horarios de las materias
    $horarios_por_materia = [];
    foreach ($materias as $materia) {
        $stmt = $pdo->prepare("
            SELECT dia_semana, hora_inicio, hora_fin, aula
            FROM horarios
            WHERE id_grupo = ?
            ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miércoles', 'jueves', 'viernes')
        ");
        $stmt->execute([$materia['id_grupo']]);
        $horarios_por_materia[$materia['id_grupo']] = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    $materias = [];
}
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Mis Materias</h1>
        <p>Materias inscritas - <?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido_paterno']; ?></p>
        <p><strong>Matrícula:</strong> <?php echo $_SESSION['matricula']; ?></p>
    </div>
    
    <?php if (empty($materias)): ?>
        <div class="card">
            <p>No tiene materias inscritas actualmente.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <h2>Materias Activas (<?php echo count($materias); ?>)</h2>
            
            <?php foreach ($materias as $materia): ?>
                <div class="materia-card">
                    <div class="materia-header">
                        <h3><?php echo htmlspecialchars($materia['nombre_materia']); ?></h3>
                        <span class="badge badge-estado-<?php echo $materia['estado_materia']; ?>">
                            <?php echo ucfirst($materia['estado_materia']); ?>
                        </span>
                    </div>
                    
                    <div class="materia-info">
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
                                <strong>Semestre:</strong> <?php echo htmlspecialchars($materia['semestre']); ?>
                            </div>
                            <div class="info-item">
                                <strong>Departamento:</strong> <?php echo htmlspecialchars($materia['departamento']); ?>
                            </div>
                            <div class="info-item">
                                <strong>Carrera:</strong> <?php echo htmlspecialchars($materia['carrera']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="materia-stats">
                        <div class="stat-box">
                            <div class="stat-label">Calificación</div>
                            <div class="stat-value">
                                <?php echo $materia['calificacion_final'] > 0 ? 
                                          number_format($materia['calificacion_final'], 2) : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Asistencia</div>
                            <div class="stat-value">
                                <?php echo number_format($materia['porcentaje_asistencia'], 2); ?>%
                            </div>
                            <div class="stat-detail">
                                <?php echo $materia['total_asistencias_periodo']; ?> / <?php echo $materia['total_clases_periodo']; ?> clases
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($horarios_por_materia[$materia['id_grupo']])): ?>
                        <div class="materia-horarios">
                            <h4>Horarios:</h4>
                            <table class="table-horarios">
                                <thead>
                                    <tr>
                                        <th>Día</th>
                                        <th>Hora</th>
                                        <th>Aula</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($horarios_por_materia[$materia['id_grupo']] as $horario): ?>
                                        <tr>
                                            <td><?php echo ucfirst($horario['dia_semana']); ?></td>
                                            <td>
                                                <?php echo date('H:i', strtotime($horario['hora_inicio'])); ?> - 
                                                <?php echo date('H:i', strtotime($horario['hora_fin'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($horario['aula']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.materia-card {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    background: white;
}

.materia-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e5e7eb;
}

.materia-header h3 {
    margin: 0;
    color: #1f2937;
}

.materia-info {
    margin-bottom: 1rem;
}

.materia-stats {
    display: flex;
    gap: 1rem;
    margin: 1rem 0;
}

.stat-box {
    flex: 1;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 8px;
    text-align: center;
}

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #2563eb;
}

.stat-detail {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 0.25rem;
}

.materia-horarios {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.materia-horarios h4 {
    margin-bottom: 0.5rem;
    color: #374151;
}

.table-horarios {
    width: 100%;
    font-size: 0.875rem;
}

.table-horarios th {
    background: #f3f4f6;
    padding: 0.5rem;
    text-align: left;
}

.table-horarios td {
    padding: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}
</style>

<?php include 'includes/footer.php'; ?>
