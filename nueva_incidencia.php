<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Resto del código...
require 'conexion.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Obtener computadoras para el select
$computadoras = [];
$usuarios = [];

try {
    $stmt = $conn->query("SELECT id, codigo_patrimonio, marca, modelo FROM computadores ORDER BY codigo_patrimonio");
    $computadoras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->query("SELECT id, nombre FROM usuarios WHERE rol = 'tecnico' AND activo = 1 ORDER BY nombre");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $computador_id = !empty($_POST['computador_id']) ? $_POST['computador_id'] : null;
    $prioridad = $_POST['prioridad'];
    $usuario_reporte_id = $_SESSION['usuario_id'];
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO incidencias 
            (computador_id, usuario_reporte_id, titulo, descripcion, estado, prioridad, fecha_reporte)
            VALUES (?, ?, ?, ?, 'reportada', ?, NOW())
        ");
        
        $stmt->execute([
            $computador_id,
            $usuario_reporte_id,
            $titulo,
            $descripcion,
            $prioridad
        ]);
        
        $incidencia_id = $conn->lastInsertId();
        header("Location: ver_incidencia.php?id=$incidencia_id");
        exit;
        
    } catch(PDOException $e) {
        $error = "Error al crear la incidencia: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Incidencia - PeopleHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos similares a ver_incidencia.php */
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
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .incidencia-title {
            font-size: 1.5rem;
            color: #6c5ce7;
            font-weight: 600;
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
        .form-group input, 
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
            min-height: 150px;
        }
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            border-color: #6c5ce7;
            outline: none;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
        }
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
        .error-message {
            color: #d63031;
            background: #ffebee;
            padding: 0.8rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="incidencia-container">
        <div class="incidencia-header">
            <h1 class="incidencia-title">Nueva Incidencia</h1>
        </div>

        <?php if (isset($error)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form action="nueva_incidencia.php" method="POST">
            <div class="form-group">
                <label for="titulo">Título *</label>
                <input type="text" id="titulo" name="titulo" required placeholder="Ej: Computadora no enciende">
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción detallada *</label>
                <textarea id="descripcion" name="descripcion" required placeholder="Describa el problema con el mayor detalle posible..."></textarea>
            </div>

            <div class="form-group">
                <label for="computador_id">Computadora relacionada (opcional)</label>
                <select id="computador_id" name="computador_id">
                    <option value="">-- Seleccionar computadora --</option>
                    <?php foreach ($computadoras as $pc): ?>
                    <option value="<?php echo $pc['id']; ?>">
                        <?php echo htmlspecialchars($pc['codigo_patrimonio'] . ' - ' . $pc['marca'] . ' ' . $pc['modelo']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="prioridad">Prioridad *</label>
                <select id="prioridad" name="prioridad" required>
                    <option value="baja">Baja</option>
                    <option value="media" selected>Media</option>
                    <option value="alta">Alta</option>
                    <option value="critica">Crítica</option>
                </select>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">Crear Incidencia</button>
                <a href="dashboard.php?mostrar_incidencias=1" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>