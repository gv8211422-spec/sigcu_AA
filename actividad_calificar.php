<?php
require_once 'config/config.php';
require_once 'includes/session.php';

requiere_rol('docente');

$titulo_pagina = 'Calificar Actividad';
include 'includes/header.php';
include 'includes/navbar.php';

$id_docente = $_SESSION['usuario_id'];
$id_actividad = intval($_GET['id'] ?? 0);
$mensaje = '';
$tipo_mensaje = '';

if ($id_actividad === 0) {
    header('Location: actividades.php');
    exit();
}

// Procesar calificaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $calificaciones = $_POST['calificacion'] ?? [];
    $comentarios = $_POST['comentarios'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($calificaciones as $id_alumno => $calificacion) {
            $calificacion = floatval($calificacion);
            $comentario = limpiar_entrada($comentarios[$id_alumno] ?? '');
            
            if ($calificacion >= 0) {
                // Verificar si ya existe calificación
                $stmt = $pdo->prepare("
                    SELECT id_calificacion_actividad FROM calificaciones_actividades
                    WHERE id_actividad = ? AND id_alumno = ?
                ");
                $stmt->execute([$id_actividad, $id_alumno]);
                
                if ($stmt->fetch()) {
                    // Actualizar
                    $stmt = $pdo->prepare("
                        UPDATE calificaciones_actividades 
                        SET calificacion_obtenida = ?, comentarios_docente = ?, fecha_calificacion = NOW()
                        WHERE id_actividad = ? AND id_alumno = ?
                    ");
                    $stmt->execute([$calificacion, $comentario, $id_actividad, $id_alumno]);
                } else {
                    // Insertar
                    $stmt = $pdo->prepare("
                        INSERT INTO calificaciones_actividades 
                        (id_actividad, id_alumno, calificacion_obtenida, comentarios_docente, fecha_calificacion)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$id_actividad, $id_alumno, $calificacion, $comentario]);
                }
            }
        }
        
        $pdo->commit();
        registrar_historial($pdo, $id_docente, 'Calificar actividad', "Actividad ID: $id_actividad");
        $mensaje = 'Calificaciones guardadas exitosamente.';
        $tipo_mensaje = 'success';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensaje = 'Error al guardar calificaciones: ' . $e->getMessage();
        $tipo_mensaje = 'error';
        error_log($e->getMessage());
    }
}

// Obtener información de la actividad
try {
    $stmt = $pdo->prepare("
        SELECT aa.*, g.nombre_grupo, m.nombre_materia
        FROM actividades_academicas aa
        INNER JOIN grupos g ON aa.id_grupo = g.id_grupo
        INNER JOIN materias m ON g.id_materia = m.id_materia
        WHERE aa.id_actividad = ? AND aa.id_docente = ?
    ");
    $stmt->execute([$id_actividad, $id_docente]);
    $actividad = $stmt->fetch();
    
    if (!$actividad) {
        header('Location: actividades.php');
        exit();
    }
    
    // Obtener alumnos y sus calificaciones
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.apellido_paterno, u.apellido_materno, u.matricula,
               ca.calificacion_obtenida, ca.comentarios_docente, ca.fecha_calificacion
        FROM inscripciones i
        INNER JOIN usuarios u ON i.id_alumno = u.id_usuario
        LEFT JOIN calificaciones_actividades ca ON ca.id_actividad = ? AND ca.id_alumno = u.id_usuario
        WHERE i.id_grupo = ? AND i.estado = 'activa'
        ORDER BY u.apellido_paterno, u.apellido_materno, u.nombre
    ");
    $stmt->execute([$id_actividad, $actividad['id_grupo']]);
    $alumnos = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: actividades.php');
    exit();
}
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Calificar Actividad</h1>
        <p><?php echo htmlspecialchars($actividad['titulo']); ?></p>
    </div>
    
    <div class="breadcrumb">
        <a href="dashboard.php">Panel de Control</a> / 
        <a href="actividades.php">Actividades</a> / 
        <span>Calificar</span>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>
    
    <!-- Información de la Actividad -->
    <div class="card">
        <h2>Información de la Actividad</h2>
        <div class="info-grid">
            <div class="info-item">
                <strong>Título:</strong> <?php echo htmlspecialchars($actividad['titulo']); ?>
            </div>
            <div class="info-item">
                <strong>Tipo:</strong> <?php echo ucfirst($actividad['tipo_actividad']); ?>
            </div>
            <div class="info-item">
                <strong>Grupo:</strong> <?php echo htmlspecialchars($actividad['nombre_grupo']); ?>
            </div>
            <div class="info-item">
                <strong>Materia:</strong> <?php echo htmlspecialchars($actividad['nombre_materia']); ?>
            </div>
            <div class="info-item">
                <strong>Fecha Entrega:</strong> <?php echo date('d/m/Y H:i', strtotime($actividad['fecha_entrega'])); ?>
            </div>
            <div class="info-item">
                <strong>Valor Máximo:</strong> <?php echo number_format($actividad['valor_maximo'], 2); ?> puntos
            </div>
        </div>
        <?php if ($actividad['descripcion']): ?>
            <p><strong>Descripción:</strong> <?php echo nl2br(htmlspecialchars($actividad['descripcion'])); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Calificaciones -->
    <div class="card">
        <h2>Calificaciones de Alumnos (<?php echo count($alumnos); ?>)</h2>
        
        <?php if (empty($alumnos)): ?>
            <p>No hay alumnos inscritos en este grupo.</p>
        <?php else: ?>
            <form method="POST" action="">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Matrícula</th>
                            <th>Nombre Completo</th>
                            <th>Calificación (0 - <?php echo $actividad['valor_maximo']; ?>)</th>
                            <th>Comentarios</th>
                            <th>Fecha Calificación</th>
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
                                <td>
                                    <input type="number" 
                                           name="calificacion[<?php echo $alumno['id_usuario']; ?>]" 
                                           min="0" 
                                           max="<?php echo $actividad['valor_maximo']; ?>" 
                                           step="0.01" 
                                           value="<?php echo $alumno['calificacion_obtenida'] ?? ''; ?>"
                                           style="width: 100px;">
                                </td>
                                <td>
                                    <input type="text" 
                                           name="comentarios[<?php echo $alumno['id_usuario']; ?>]" 
                                           value="<?php echo htmlspecialchars($alumno['comentarios_docente'] ?? ''); ?>"
                                           placeholder="Comentarios opcionales">
                                </td>
                                <td>
                                    <?php echo $alumno['fecha_calificacion'] ? 
                                              date('d/m/Y H:i', strtotime($alumno['fecha_calificacion'])) : 'No calificado'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Guardar Calificaciones</button>
                    <a href="actividades.php" class="btn btn-secondary">Volver</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
