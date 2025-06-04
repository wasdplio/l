<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Resto del código...
require 'conexion.php';
session_start();

if (!isset($_SESSION['usuario_id']) || ($_SESSION['rol'] !== 'admin' && $_SESSION['rol'] !== 'tecnico')) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php?mostrar_incidencias=1");
    exit;
}

$id = $_GET['id'];

// Obtener la incidencia actual
try {
    $stmt = $conn->prepare("
        SELECT i.*, c.codigo_patrimonio 
        FROM incidencias i
        LEFT JOIN computadores c ON i.computador_id = c.id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $incidencia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incidencia) {
        header("Location: dashboard.php?mostrar_incidencias=1");
        exit;
    }
} catch(PDOException $e) {
    die("Error al obtener la incidencia: " . $e->getMessage());
}

// Obtener datos para los selects
try {
    $stmt = $conn->query("SELECT id, codigo_patrimonio, marca, modelo FROM computadores ORDER BY codigo_patrimonio");
    $computadoras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->query("SELECT id, nombre FROM usuarios WHERE rol = 'tecnico' AND activo = 1 ORDER BY nombre");
    $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

// Procesar el formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $computador_id = $_POST['computador_id'] ?: null;
    $estado = $_POST['estado'];
    $prioridad = $_POST['prioridad'];
    $usuario_asignado_id = $_POST['usuario_asignado_id'] ?: null;
    $solucion = $_POST['solucion'] ?: null;
    
    try {
        $stmt = $conn->prepare("
            UPDATE incidencias SET
                titulo = ?,
                descripcion = ?,
                computador_id = ?,
                estado = ?,
                prioridad = ?,
                usuario_asignado_id = ?,
                solucion = ?,
                fecha_asignacion = CASE WHEN ? IS NOT NULL AND fecha_asignacion IS NULL THEN NOW() ELSE fecha_asignacion END,
                fecha_resolucion = CASE WHEN ? IN ('resuelta', 'cerrada') AND fecha_resolucion IS NULL THEN NOW() ELSE fecha_resolucion END
            WHERE id = ?
        ");
        
        $stmt->execute([
            $titulo, $descripcion, $computador_id, $estado, $prioridad, 
            $usuario_asignado_id, $solucion, $usuario_asignado_id, $estado, $id
        ]);
        
        header("Location: ver_incidencia.php?id=$id");
        exit;
    } catch(PDOException $e) {
        $error = "Error al actualizar la incidencia: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Incidencia - PeopleHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f6fa;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #6c5ce7;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        input, select, textarea {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        textarea {
            min-height: 120px;
        }
        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            margin-right: 0.5rem;
        }
        .btn-primary {
            background: #6c5ce7;
            color: white;
        }
        .btn-secondary {
            background: #e0e0e0;
        }
        .error {
            color: #d63031;
            background: #ffebee;
            padding: 0.8rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-edit"></i> Editar Incidencia #<?= $incidencia['id'] ?></h1>
        
        <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="titulo">Título *</label>
                <input type="text" id="titulo" name="titulo" value="<?= htmlspecialchars($incidencia['titulo']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="descripcion">Descripción *</label>
                <textarea id="descripcion" name="descripcion" required><?= htmlspecialchars($incidencia['descripcion']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="computador_id">Computadora relacionada</label>
                <select id="computador_id" name="computador_id">
                    <option value="">-- Ninguna --</option>
                    <?php foreach ($computadoras as $pc): ?>
                    <option value="<?= $pc['id'] ?>" <?= $pc['id'] == $incidencia['computador_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pc['codigo_patrimonio'] . ' - ' . $pc['marca'] . ' ' . $pc['modelo']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="estado">Estado *</label>
                <select id="estado" name="estado" required>
                    <option value="reportada" <?= $incidencia['estado'] == 'reportada' ? 'selected' : '' ?>>Reportada</option>
                    <option value="asignada" <?= $incidencia['estado'] == 'asignada' ? 'selected' : '' ?>>Asignada</option>
                    <option value="en_proceso" <?= $incidencia['estado'] == 'en_proceso' ? 'selected' : '' ?>>En Proceso</option>
                    <option value="resuelta" <?= $incidencia['estado'] == 'resuelta' ? 'selected' : '' ?>>Resuelta</option>
                    <option value="cerrada" <?= $incidencia['estado'] == 'cerrada' ? 'selected' : '' ?>>Cerrada</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="prioridad">Prioridad *</label>
                <select id="prioridad" name="prioridad" required>
                    <option value="baja" <?= $incidencia['prioridad'] == 'baja' ? 'selected' : '' ?>>Baja</option>
                    <option value="media" <?= $incidencia['prioridad'] == 'media' ? 'selected' : '' ?>>Media</option>
                    <option value="alta" <?= $incidencia['prioridad'] == 'alta' ? 'selected' : '' ?>>Alta</option>
                    <option value="critica" <?= $incidencia['prioridad'] == 'critica' ? 'selected' : '' ?>>Crítica</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="usuario_asignado_id">Asignar a técnico</label>
                <select id="usuario_asignado_id" name="usuario_asignado_id">
                    <option value="">-- Ninguno --</option>
                    <?php foreach ($tecnicos as $tecnico): ?>
                    <option value="<?= $tecnico['id'] ?>" <?= $tecnico['id'] == $incidencia['usuario_asignado_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tecnico['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="solucion">Solución (para estados Resuelta/Cerrada)</label>
                <textarea id="solucion" name="solucion"><?= htmlspecialchars($incidencia['solucion'] ?? '') ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            <a href="ver_incidencia.php?id=<?= $incidencia['id'] ?>" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</body>
</html>