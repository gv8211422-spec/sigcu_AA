<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_rol('docente');

$titulo_pagina = 'Mis Grupos';
include 'includes/header.php';
include 'includes/navbar.php';

$id_docente = $_SESSION['usuario_id'];

// Obtener grupos del docente
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT g.id_grupo, g.nombre_grupo, m.nombre_materia, m.clave_materia, 
               g.semestre, g.cupo_maximo,
               COUNT(DISTINCT i.id_alumno) as total_alumnos
        FROM grupos g
        INNER JOIN materias m ON g.id_materia = m.id_materia
        LEFT JOIN inscripciones i ON g.id_grupo = i.id_grupo AND i.estado = 'activa'
        WHERE g.id_grupo IN (
            SELECT DISTINCT id_grupo FROM actividades_academicas WHERE id_docente = ?
        )
        GROUP BY g.id_grupo
        ORDER BY m.nombre_materia, g.nombre_grupo
    ");
    $stmt->execute([$id_docente]);
    $grupos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $grupos = [];
}
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Mis Grupos</h1>
        <p>Grupos asignados a <?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido_paterno']; ?></p>
    </div>
    
    <?php if (empty($grupos)): ?>
        <div class="card">
            <p>No tiene grupos asignados actualmente.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <h2>Grupos Activos (<?php echo count($grupos); ?>)</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Materia</th>
                        <th>Clave</th>
                        <th>Semestre</th>
                        <th>Alumnos</th>
                        <th>Cupo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grupos as $grupo): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($grupo['nombre_grupo']); ?></td>
                            <td><?php echo htmlspecialchars($grupo['nombre_materia']); ?></td>
                            <td><?php echo htmlspecialchars($grupo['clave_materia']); ?></td>
                            <td><?php echo htmlspecialchars($grupo['semestre']); ?></td>
                            <td><?php echo $grupo['total_alumnos']; ?></td>
                            <td><?php echo $grupo['cupo_maximo']; ?></td>
                            <td>
                                <a href="grupo_detalle.php?id=<?php echo $grupo['id_grupo']; ?>" class="btn btn-sm btn-primary">
                                    Ver Detalles
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
