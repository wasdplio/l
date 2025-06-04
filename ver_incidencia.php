<?php
require 'conexion.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$id = $_GET['id'];

try {
    $stmt = $conn->prepare("
        SELECT i.*, c.codigo_patrimonio, c.marca, c.modelo, 
               u1.nombre as reportante, u1.email as email_reportante,
               u2.nombre as asignado, u2.email as email_asignado
        FROM incidencias i
        LEFT JOIN computadores c ON i.computador_id = c.id
        LEFT JOIN usuarios u1 ON i.usuario_reporte_id = u1.id
        LEFT JOIN usuarios u2 ON i.usuario_asignado_id = u2.id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $incidencia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incidencia) {
        header("Location: dashboard.php");
        exit;
    }

    // Obtener técnicos disponibles para asignar
    $tecnicos = [];
    $stmt = $conn->query("SELECT id, nombre FROM usuarios WHERE rol = 'tecnico' AND activo = 1");
    $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Error al obtener la incidencia: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Incidencia - PeopleHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos similares a dashboard.php */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f6fa;
            padding: 20px;
        }
        .incidencia-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
            max-width: 800px;
            margin: 0 auto;
        }
        .incidencia-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .incidencia-title {
            font-size: 1.5rem;
            color: #6c5ce7;
            font-weight: 600;
        }
        .incidencia-body {
            margin-bottom: 1.5rem;
        }
        .incidencia-field {
            margin-bottom: 1rem;
        }
        .incidencia-field label {
            display: block;
            font-weight: 500;
            color: #2d3436;
            margin-bottom: 0.3rem;
        }
        .incidencia-field .value {
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 6px;
            min-height: 20px;
        }
        .badge {
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }
        .badge-success { background: #e8f7f0; color: #2ecc71; }
        .badge-warning { background: #fff8e6; color: #e67e22; }
        .badge-danger { background: #ffebee; color: #d63031; }
        .badge-primary { background: #e3f2fd; color: #1976d2; }
        .badge-secondary { background: #e0e0e0; color: #424242; }
        .badge-info { background: #e1f5fe; color: #0288d1; }
        .badge-dark { background: #e0e0e0; color: #212121; }
        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #6c5ce7;
            color: white;
        }
        .btn-primary:hover {
            background: #5a4abf;
        }
        .btn-secondary {
            background: #f1f1f1;
            color: #2d3436;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2d3436;
        }
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .form-group textarea {
            min-height: 100px;
        }
        .form-group select:focus, 
        .form-group textarea:focus {
            border-color: #6c5ce7;
            outline: none;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
        }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #6c5ce7;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="incidencia-container">
        <div class="incidencia-header">
            <h1 class="incidencia-title">Incidencia #<?php echo htmlspecialchars($incidencia['id']); ?></h1>
            <span class="badge 
                <?php 
                switch($incidencia['estado']) {
                    case 'reportada': echo 'badge-warning'; break;
                    case 'asignada': echo 'badge-primary'; break;
                    case 'en_proceso': echo 'badge-info'; break;
                    case 'resuelta': echo 'badge-success'; break;
                    case 'cerrada': echo 'badge-secondary'; break;
                    default: echo 'badge-light';
                }
                ?>">
                <?php echo ucfirst(str_replace('_', ' ', $incidencia['estado'])); ?>
            </span>
        </div>

        <div class="incidencia-body">
            <div class="incidencia-field">
                <label>Descripcion</label>
                <div class="value"><?php echo htmlspecialchars($incidencia['titulo']); ?></div>
            </div>

            <div class="incidencia-field">
                <label>Computadora</label>
                <div class="value">
                    <?php if ($incidencia['computador_id']): ?>
                        <?php echo htmlspecialchars($incidencia['marca'] . ' ' . $incidencia['modelo']); ?>
                        (<?php echo htmlspecialchars($incidencia['codigo_patrimonio']); ?>)
                    <?php else: ?>
                        No especificada
                    <?php endif; ?>
                </div>
            </div>

            <div class="incidencia-field">
                <label>Descripción</label>
                <div class="value"><?php echo nl2br(htmlspecialchars($incidencia['descripcion'])); ?></div>
            </div>

            <div class="form-row" style="display: flex; gap: 1rem;">
                <div class="incidencia-field" style="flex: 1;">
                    <label>Reportado por</label>
                    <div class="value">
                        <?php echo htmlspecialchars($incidencia['reportante']); ?><br>
                        <small><?php echo htmlspecialchars($incidencia['email_reportante']); ?></small>
                    </div>
                </div>

                <div class="incidencia-field" style="flex: 1;">
                    <label>Asignado a</label>
                    <div class="value">
                        <?php if ($incidencia['asignado']): ?>
                            <?php echo htmlspecialchars($incidencia['asignado']); ?><br>
                            <small><?php echo htmlspecialchars($incidencia['email_asignado']); ?></small>
                        <?php else: ?>
                            No asignado
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-row" style="display: flex; gap: 1rem;">
                <div class="incidencia-field" style="flex: 1;">
                    <label>Prioridad</label>
                    <div class="value">
                        <span class="badge 
                            <?php 
                            switch($incidencia['prioridad']) {
                                case 'baja': echo 'badge-success'; break;
                                case 'media': echo 'badge-warning'; break;
                                case 'alta': echo 'badge-danger'; break;
                                case 'critica': echo 'badge-dark'; break;
                                default: echo 'badge-light';
                            }
                            ?>">
                            <?php echo ucfirst($incidencia['prioridad']); ?>
                        </span>
                    </div>
                </div>

                <div class="incidencia-field" style="flex: 1;">
                    <label>Fecha de Reporte</label>
                    <div class="value">
                        <?php echo date('d/m/Y H:i', strtotime($incidencia['fecha_reporte'])); ?>
                    </div>
                </div>
            </div>

            <?php if ($incidencia['fecha_asignacion']): ?>
            <div class="incidencia-field">
                <label>Fecha de Asignación</label>
                <div class="value">
                    <?php echo date('d/m/Y H:i', strtotime($incidencia['fecha_asignacion'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($incidencia['fecha_resolucion']): ?>
            <div class="incidencia-field">
                <label>Fecha de Resolución</label>
                <div class="value">
                    <?php echo date('d/m/Y H:i', strtotime($incidencia['fecha_resolucion'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($incidencia['solucion']): ?>
            <div class="incidencia-field">
                <label>Solución</label>
                <div class="value"><?php echo nl2br(htmlspecialchars($incidencia['solucion'])); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($_SESSION['rol'] === 'admin' || $_SESSION['rol'] === 'tecnico'): ?>
        <form action="actualizar_incidencia.php" method="POST">
            <input type="hidden" name="id" value="<?php echo $incidencia['id']; ?>">
            
            <div class="form-group">
                <label for="estado">Estado</label>
                <select id="estado" name="estado" required>
                    <option value="reportada" <?php echo $incidencia['estado'] === 'reportada' ? 'selected' : ''; ?>>Reportada</option>
                    <option value="asignada" <?php echo $incidencia['estado'] === 'asignada' ? 'selected' : ''; ?>>Asignada</option>
                    <option value="en_proceso" <?php echo $incidencia['estado'] === 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                    <option value="resuelta" <?php echo $incidencia['estado'] === 'resuelta' ? 'selected' : ''; ?>>Resuelta</option>
                    <option value="cerrada" <?php echo $incidencia['estado'] === 'cerrada' ? 'selected' : ''; ?>>Cerrada</option>
                </select>
            </div>

            <div class="form-group">
                <label for="usuario_asignado_id">Asignar a técnico</label>
                <select id="usuario_asignado_id" name="usuario_asignado_id">
                    <option value="">-- Seleccionar técnico --</option>
                    <?php foreach ($tecnicos as $tecnico): ?>
                    <option value="<?php echo $tecnico['id']; ?>" 
                        <?php echo $incidencia['usuario_asignado_id'] == $tecnico['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tecnico['nombre']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="solucion">Solución</label>
                <textarea id="solucion" name="solucion" placeholder="Describa la solución aplicada..."><?php echo htmlspecialchars($incidencia['solucion'] ?? ''); ?></textarea>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">Actualizar Incidencia</button>
                <a href="dashboard.php?mostrar_incidencias=1" class="btn btn-secondary">Volver al listado</a>
            </div>
        </form>
        <?php else: ?>
        <div style="margin-top: 1.5rem;">
            <a href="dashboard.php?mostrar_incidencias=1" class="btn btn-secondary">Volver al listado</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>