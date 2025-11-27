<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_rol('docente');

$titulo_pagina = 'Detalle del Grupo';
include 'includes/header.php';
include 'includes/navbar.php';

$id_docente = $_SESSION['usuario_id'];
$id_grupo = intval($_GET['id'] ?? 0);

if ($id_grupo === 0) {
    header('Location: grupos.php');
    exit();
}

// Obtener información del grupo
try {
    $stmt = $pdo->prepare("
        SELECT g.*, m.nombre_materia, m.clave_materia, m.creditos
        FROM grupos g
        INNER JOIN materias m ON g.id_materia = m.id_materia
        WHERE g.id_grupo = ?
    ");
    $stmt->execute([$id_grupo]);
    $grupo = $stmt->fetch();
    
    if (!$grupo) {
        header('Location: grupos.php');
        exit();
    }
    
    // Obtener alumnos inscritos
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.apellido_paterno, u.apellido_materno, 
               u.matricula, u.email,
               c.calificacion_final, c.estado_materia
        FROM inscripciones i
        INNER JOIN usuarios u ON i.id_alumno = u.id_usuario
        LEFT JOIN calificaciones c ON c.id_alumno = u.id_usuario AND c.id_materia = ?
        WHERE i.id_grupo = ? AND i.estado = 'activa'
        ORDER BY u.apellido_paterno, u.apellido_materno, u.nombre
    ");
    $stmt->execute([$grupo['id_materia'], $id_grupo]);
    $alumnos = $stmt->fetchAll();
    
    // Obtener horarios
    $stmt = $pdo->prepare("
        SELECT dia_semana, hora_inicio, hora_fin, aula
        FROM horarios
        WHERE id_grupo = ?
        ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miércoles', 'jueves', 'viernes')
    ");
    $stmt->execute([$id_grupo]);
    $horarios = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: grupos.php');
    exit();
}
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Detalle del Grupo</h1>
        <p><?php echo htmlspecialchars($grupo['nombre_materia']); ?></p>
    </div>
    
    <div class="breadcrumb">
        <a href="dashboard.php">Panel de Control</a> / 
        <a href="grupos.php">Mis Grupos</a> / 
        <span><?php echo htmlspecialchars($grupo['nombre_grupo']); ?></span>
    </div>
    
    <!-- Información del Grupo -->
    <div class="card">
        <h2>Información del Grupo</h2>
        <div class="info-grid">
            <div class="info-item">
                <strong>Grupo:</strong> <?php echo htmlspecialchars($grupo['nombre_grupo']); ?>
            </div>
            <div class="info-item">
                <strong>Materia:</strong> <?php echo htmlspecialchars($grupo['nombre_materia']); ?>
            </div>
            <div class="info-item">
                <strong>Clave:</strong> <?php echo htmlspecialchars($grupo['clave_materia']); ?>
            </div>
            <div class="info-item">
                <strong>Semestre:</strong> <?php echo htmlspecialchars($grupo['semestre']); ?>
            </div>
            <div class="info-item">
                <strong>Créditos:</strong> <?php echo $grupo['creditos']; ?>
            </div>
            <div class="info-item">
                <strong>Alumnos Inscritos:</strong> <?php echo count($alumnos); ?> / <?php echo $grupo['cupo_maximo']; ?>
            </div>
        </div>
    </div>
    
    <!-- Horarios -->
    <?php if (!empty($horarios)): ?>
        <div class="card">
            <h2>Horarios</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Hora Inicio</th>
                        <th>Hora Fin</th>
                        <th>Aula</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($horarios as $horario): ?>
                        <tr>
                            <td><?php echo ucfirst($horario['dia_semana']); ?></td>
                            <td><?php echo date('H:i', strtotime($horario['hora_inicio'])); ?></td>
                            <td><?php echo date('H:i', strtotime($horario['hora_fin'])); ?></td>
                            <td><?php echo htmlspecialchars($horario['aula']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Lista de Alumnos -->
    <div class="card">
        <h2>Alumnos Inscritos (<?php echo count($alumnos); ?>)</h2>
        <?php if (empty($alumnos)): ?>
            <p>No hay alumnos inscritos en este grupo.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Matrícula</th>
                        <th>Nombre Completo</th>
                        <th>Correo</th>
                        <th>Calificación Final</th>
                        <th>Estado</th>
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
                            <td><?php echo htmlspecialchars($alumno['email']); ?></td>
                            <td>
                                <?php echo $alumno['calificacion_final'] > 0 ? 
                                          number_format($alumno['calificacion_final'], 2) : 'N/A'; ?>
                            </td>
                            <td><?php echo ucfirst($alumno['estado_materia']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Acciones Rápidas -->
    <div class="card">
        <h2>Acciones del Grupo</h2>
        <div class="quick-actions">
            <a href="actividades.php?grupo=<?php echo $id_grupo; ?>" class="btn btn-primary">
                Gestionar Actividades
            </a>
            <a href="calificaciones.php?grupo=<?php echo $id_grupo; ?>" class="btn btn-primary">
                Calificar Exámenes
            </a>
            <a href="asistencia.php?grupo=<?php echo $id_grupo; ?>" class="btn btn-primary">
                Tomar Asistencia
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
