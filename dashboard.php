<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_autenticacion();

$titulo_pagina = 'Panel de Control';
include 'includes/header.php';
include 'includes/navbar.php';

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

// ============================================
// DASHBOARD ADMINISTRADOR
// ============================================
if ($rol === 'administrador') {
    try {
        $stmt = $pdo->query("SELECT rol, COUNT(*) as total FROM usuarios GROUP BY rol");
        $usuarios_por_rol = $stmt->fetchAll();
        
        $total_materias = $pdo->query("SELECT COUNT(*) FROM materias")->fetchColumn();
        $total_grupos = $pdo->query("SELECT COUNT(*) FROM grupos")->fetchColumn();
        $usuarios_pendientes = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado_cuenta = 'pendiente'")->fetchColumn();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
    ?>
    
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Panel de Administración</h1>
            <p>Bienvenido, <?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido_paterno']; ?></p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Materias</h3>
                <div class="stat-number"><?php echo $total_materias ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Grupos</h3>
                <div class="stat-number"><?php echo $total_grupos ?? 0; ?></div>
            </div>
            
            <div class="stat-card alert">
                <h3>Usuarios Pendientes</h3>
                <div class="stat-number"><?php echo $usuarios_pendientes ?? 0; ?></div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="card">
                <h2>Usuarios por Rol</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rol</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($usuarios_por_rol)): ?>
                            <?php foreach ($usuarios_por_rol as $rol_data): ?>
                                <tr>
                                    <td><?php echo ucfirst($rol_data['rol']); ?></td>
                                    <td><?php echo $rol_data['total']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="quick-actions">
                <h2>Acciones Rápidas</h2>
                <a href="usuarios.php" class="btn btn-primary">Gestionar Usuarios</a>
                <a href="usuarios.php?estado=pendiente" class="btn btn-warning">Ver Pendientes</a>
            </div>
        </div>
    </div>
    
    <?php
}

// ============================================
// DASHBOARD DOCENTE
// ============================================
elseif ($rol === 'docente') {
    try {
        $stmt = $pdo->prepare("
            SELECT g.id_grupo, g.nombre_grupo, m.nombre_materia, m.clave_materia, g.semestre,
                   COUNT(DISTINCT i.id_alumno) as total_alumnos
            FROM grupos g
            INNER JOIN materias m ON g.id_materia = m.id_materia
            LEFT JOIN inscripciones i ON g.id_grupo = i.id_grupo AND i.estado = 'activa'
            WHERE g.id_grupo IN (
                SELECT DISTINCT id_grupo FROM actividades_academicas WHERE id_docente = ?
            )
            GROUP BY g.id_grupo
        ");
        $stmt->execute([$usuario_id]);
        $grupos = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM actividades_academicas 
            WHERE id_docente = ? AND estado = 'activo' AND fecha_entrega >= NOW()
        ");
        $stmt->execute([$usuario_id]);
        $actividades_activas = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
    ?>
    
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Panel Docente</h1>
            <p>Bienvenido, <?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido_paterno']; ?></p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Mis Grupos</h3>
                <div class="stat-number"><?php echo count($grupos ?? []); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Actividades Activas</h3>
                <div class="stat-number"><?php echo $actividades_activas ?? 0; ?></div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="card">
                <h2>Mis Grupos</h2>
                <?php if (!empty($grupos)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Grupo</th>
                                <th>Materia</th>
                                <th>Semestre</th>
                                <th>Alumnos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grupos as $grupo): ?>
                                <tr>
                                    <td><?php echo $grupo['nombre_grupo']; ?></td>
                                    <td><?php echo $grupo['nombre_materia']; ?></td>
                                    <td><?php echo $grupo['semestre']; ?></td>
                                    <td><?php echo $grupo['total_alumnos']; ?></td>
                                    <td>
                                        <a href="grupo_detalle.php?id=<?php echo $grupo['id_grupo']; ?>" class="btn btn-sm">Ver</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No tiene grupos asignados.</p>
                <?php endif; ?>
            </div>
            
            <div class="quick-actions">
                <h2>Acciones Rápidas</h2>
                <a href="actividades.php" class="btn btn-primary">Gestionar Actividades</a>
                <a href="calificaciones.php" class="btn btn-primary">Calificaciones</a>
                <a href="asistencia.php" class="btn btn-primary">Tomar Asistencia</a>
            </div>
        </div>
    </div>
    
    <?php
}

// ============================================
// DASHBOARD ALUMNO
// ============================================
elseif ($rol === 'alumno') {
    try {
        $stmt = $pdo->prepare("
            SELECT m.nombre_materia, m.clave_materia, g.nombre_grupo, g.semestre,
                   c.calificacion_final, c.estado_materia
            FROM inscripciones i
            INNER JOIN grupos g ON i.id_grupo = g.id_grupo
            INNER JOIN materias m ON g.id_materia = m.id_materia
            LEFT JOIN calificaciones c ON c.id_alumno = i.id_alumno AND c.id_materia = m.id_materia
            WHERE i.id_alumno = ? AND i.estado = 'activa'
            ORDER BY m.nombre_materia
        ");
        $stmt->execute([$usuario_id]);
        $materias = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("
            SELECT AVG(calificacion_final) as promedio 
            FROM calificaciones 
            WHERE id_alumno = ? AND calificacion_final > 0
        ");
        $stmt->execute([$usuario_id]);
        $promedio = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM actividades_academicas aa
            INNER JOIN inscripciones i ON aa.id_grupo = i.id_grupo
            LEFT JOIN calificaciones_actividades ca ON aa.id_actividad = ca.id_actividad AND ca.id_alumno = ?
            WHERE i.id_alumno = ? AND aa.estado = 'activo' 
            AND aa.fecha_entrega >= NOW() AND ca.id_calificacion_actividad IS NULL
        ");
        $stmt->execute([$usuario_id, $usuario_id]);
        $actividades_pendientes = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
    ?>
    
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Panel del Alumno</h1>
            <p>Bienvenido, <?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido_paterno']; ?></p>
            <p><strong>Matrícula:</strong> <?php echo $_SESSION['matricula']; ?></p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Materias Inscritas</h3>
                <div class="stat-number"><?php echo count($materias ?? []); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Promedio General</h3>
                <div class="stat-number"><?php echo $promedio ? number_format($promedio, 2) : 'N/A'; ?></div>
            </div>
            
            <div class="stat-card alert">
                <h3>Actividades Pendientes</h3>
                <div class="stat-number"><?php echo $actividades_pendientes ?? 0; ?></div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="card">
                <h2>Mis Materias</h2>
                <?php if (!empty($materias)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Materia</th>
                                <th>Grupo</th>
                                <th>Calificación</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materias as $materia): ?>
                                <tr>
                                    <td><?php echo $materia['nombre_materia']; ?></td>
                                    <td><?php echo $materia['nombre_grupo']; ?></td>
                                    <td><?php echo $materia['calificacion_final'] > 0 ? number_format($materia['calificacion_final'], 2) : 'N/A'; ?></td>
                                    <td><?php echo ucfirst($materia['estado_materia']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No tiene materias inscritas.</p>
                <?php endif; ?>
            </div>
            
            <div class="quick-actions">
                <h2>Acciones Rápidas</h2>
                <a href="calificaciones.php" class="btn btn-primary">Ver Calificaciones</a>
                <a href="asistencia.php" class="btn btn-primary">Ver Asistencia</a>
                <a href="actividades.php" class="btn btn-primary">Mis Actividades</a>
            </div>
        </div>
    </div>
    
    <?php
}

// ============================================
// DASHBOARD ADMINISTRATIVO
// ============================================
elseif ($rol === 'administrativo') {
    try {
        $total_grupos = $pdo->query("SELECT COUNT(*) FROM grupos")->fetchColumn();
        $total_horarios = $pdo->query("SELECT COUNT(DISTINCT id_grupo) FROM horarios")->fetchColumn();
        $grupos_sin_horario = $total_grupos - $total_horarios;
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
    ?>
    
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Panel Administrativo</h1>
            <p>Bienvenido, <?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido_paterno']; ?></p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total de Grupos</h3>
                <div class="stat-number"><?php echo $total_grupos ?? 0; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Grupos con Horario</h3>
                <div class="stat-number"><?php echo $total_horarios ?? 0; ?></div>
            </div>
            
            <div class="stat-card alert">
                <h3>Sin Horario</h3>
                <div class="stat-number"><?php echo $grupos_sin_horario ?? 0; ?></div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="quick-actions">
                <h2>Acciones Rápidas</h2>
                <a href="horarios.php" class="btn btn-primary">Gestionar Horarios</a>
                <a href="grupos.php" class="btn btn-primary">Ver Grupos</a>
            </div>
        </div>
    </div>
    
    <?php
}

include 'includes/footer.php';
?>
