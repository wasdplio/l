<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require 'conexion.php';

// Obtener el nombre del usuario para mostrar en el header
$nombreUsuario = $_SESSION['nombre'];

// Obtener las sedes de la base de datos
$sedes = [];
try {
    $stmt = $conn->query("SELECT * FROM sedes WHERE activa = TRUE");
    $sedes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Manejar error
    die("Error al obtener sedes: " . $e->getMessage());
}

// Obtener los salones para mostrar en la tabla
$salones = [];
if (isset($_GET['sede_id'])) {
    $sede_id = $_GET['sede_id'];
    try {
        $stmt = $conn->prepare("SELECT * FROM salones WHERE sede_id = :sede_id");
        $stmt->bindParam(':sede_id', $sede_id);
        $stmt->execute();
        $salones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // Manejar error
        die("Error al obtener salones: " . $e->getMessage());
    }
}

// Obtener las computadoras si se seleccionó un salón
$computadoras = [];
if (isset($_GET['salon_id'])) {
    $salon_id = $_GET['salon_id'];
    try {
        $stmt = $conn->prepare("SELECT * FROM computadores WHERE salon_id = :salon_id");
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->execute();
        $computadoras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // Manejar error
        die("Error al obtener computadoras: " . $e->getMessage());
    }
}

// Obtener las incidencias si se seleccionó el menú de incidentes
$incidencias = [];
$computadoras_para_incidencias = [];
if (isset($_GET['mostrar_incidencias'])) {
    try {
        // Obtener incidencias con información relacionada
        $stmt = $conn->query("
            SELECT i.*, c.codigo_patrimonio, u1.nombre as reportante, u2.nombre as asignado 
            FROM incidencias i
            LEFT JOIN computadores c ON i.computador_id = c.id
            LEFT JOIN usuarios u1 ON i.usuario_reporte_id = u1.id
            LEFT JOIN usuarios u2 ON i.usuario_asignado_id = u2.id
            ORDER BY i.fecha_reporte DESC
        ");
        $incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener computadoras para el select de nueva incidencia
        $stmt = $conn->query("SELECT id, codigo_patrimonio, marca, modelo FROM computadores ORDER BY codigo_patrimonio");
        $computadoras_para_incidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        die("Error al obtener datos: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PeopleHub | Sistema de Gestión de Computadoras</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
            --color-primary: #6c5ce7;      /* Morado */
            --color-secondary: #0984e3;    /* Azul */
            --color-accent: #00cec9;       /* Turquesa */
            --color-orange: #e67e22;       /* Naranja */
            --color-green: #2ecc71;        /* Verde */
            --color-light: #f8f9fa;
            --color-dark: #2d3436;
            --color-success: #00b894;
            --color-warning: #fdcb6e;
            --color-danger: #d63031;
            --color-dark-blue: #1d00ff;    /* Azul oscuro */
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f6fa;
            color: var(--color-dark);
            display: flex;
            min-height: 100vh;
        }

        /* 🎀 Menú Lateral */
        .sidebar {
            width: 250px;
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 30px;
        }

        .sidebar-header i {
            font-size: 1.5rem;
            color: var(--color-primary);
        }

        .sidebar-header span {
            font-weight: 600;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .menu-item {
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .menu-item:hover {
            background: #f8f9fa;
            border-left: 4px solid var(--color-primary);
        }

        .menu-item.active {
            background: #f0f2ff;
            border-left: 4px solid var(--color-primary);
            color: var(--color-primary);
        }

        .menu-item i {
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }

        .menu-item span {
            font-weight: 500;
        }

        /* 🎀 Header Estilo Moderno */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: linear-gradient(135deg, var(--color-dark-blue), var(--color-primary));
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .logo i {
            font-size: 1.8rem;
            color: var(--color-accent);
        }

        .search-bar {
            display: flex;
            width: 280px;
            position: relative;
            margin-right: 15px;
        }

        .search-bar input {
            width: 100%;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            border: none;
            border-radius: 30px;
            font-size: 0.9rem;
            outline: none;
            background: #f1f3f5;
            transition: all 0.3s;
            height: 42px;
        }

        .search-bar button {
            background: none;
            color: #7f8c8d;
            border: none;
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            transition: all 0.3s;
            z-index: 1;
            font-size: 0.9rem;
        }

        .search-bar button:hover {
            color: var(--color-dark);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
        }

        .user-menu img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid white;
            object-fit: cover;
        }

        /* 📊 Tarjetas Dashboard con nuevos colores */
        .dashboard {
            padding: 2rem;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            border-top: 4px solid;
            cursor: pointer;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        /* Asignación de colores específicos a cada tarjeta */
        .card:nth-child(1) { border-color: var(--color-orange); }  /* Naranja */
        .card:nth-child(2) { border-color: var(--color-green); }   /* Verde */
        .card:nth-child(3) { border-color: var(--color-primary); } /* Morado */
        .card:nth-child(4) { border-color: var(--color-secondary); } /* Azul */

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .card-header i {
            font-size: 1.8rem;
        }

        /* Iconos con el color de cada tarjeta */
        .card:nth-child(1) .card-header i { color: var(--color-orange); }
        .card:nth-child(2) .card-header i { color: var(--color-green); }
        .card:nth-child(3) .card-header i { color: var(--color-primary); }
        .card:nth-child(4) .card-header i { color: var(--color-secondary); }

        .card h3 {
            font-size: 1.2rem;
            color: var(--color-dark);
            margin-bottom: 0.5rem;
        }

        .card p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .progress-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            margin: 1rem 0;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            border-radius: 4px;
        }

        /* Barras de progreso con colores de tarjeta */
        .card:nth-child(1) .progress { background: var(--color-orange); width: 75%; }
        .card:nth-child(2) .progress { background: var(--color-green); width: 45%; }
        .card:nth-child(3) .progress { background: var(--color-primary); width: 90%; }
        .card:nth-child(4) .progress { background: var(--color-secondary); width: 30%; }

        /* 📜 Tabla de Salones/Computadoras */
        .data-section {
            padding: 0 2rem 2rem;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .table-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--color-dark);
        }

        .table-actions button {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--color-primary);
            color: white;
        }

        .btn-primary:hover {
            background: #5a4abf;
        }

        .btn-secondary {
            background: var(--color-light);
            color: var(--color-dark);
            margin-left: 0.5rem;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--color-dark);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #f5f6fa;
        }

        .pc-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pc-info img {
            width: 40px;
            height: 40px;
            border-radius: 4px;
            object-fit: cover;
        }

        .badge {
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-success {
            background: #e8f7f0;
            color: var(--color-green);
        }

        .badge-warning {
            background: #fff8e6;
            color: var(--color-orange);
        }

        .badge-danger {
            background: #ffebee;
            color: var(--color-danger);
        }

        .badge-primary {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-secondary {
            background: #e0e0e0;
            color: #424242;
        }

        .badge-info {
            background: #e1f5fe;
            color: #0288d1;
        }

        .badge-dark {
            background: #e0e0e0;
            color: #212121;
        }

        .badge-light {
            background: #f5f5f5;
            color: #616161;
        }

        .actions button {
            background: none;
            border: none;
            cursor: pointer !important;
            font-size: 1rem;
            margin-right: 0.5rem;
            transition: all 0.3s;
        }

        .actions button:hover {
            opacity: 0.8;
        }

        .view-btn { color: var(--color-primary); }
        .edit-btn { color: var(--color-secondary); }
        .delete-btn { color: var(--color-danger); }

        /* Modal para agregar salones */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 12px;
            width: auto;
            min-width: 500px;
            max-width: 90%;
            max-height: 90vh;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            animation: modalFadeIn 0.3s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .modal-title {
            font-size: 1.5rem;
            color: var(--color-primary);
            font-weight: 600;
        }

        .modal-body {
            max-height: 60vh;
            overflow-y: auto;
            padding-right: 10px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--color-dark);
            font-size: 0.9rem;
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
            min-height: 80px;
        }

        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            border-color: var(--color-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
        }

        .btn-cancel {
            background: #f1f1f1;
            color: var(--color-dark);
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .btn-submit {
            background: var(--color-primary);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            background: #5a4abf;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: #7f8c8d;
            transition: all 0.3s;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            line-height: 1;
            padding: 0;
        }

        .close-modal:hover {
            background-color: #f5f5f5;
            color: var(--color-danger);
            transform: rotate(90deg);
        }

        /* 📱 Responsive */
        @media (max-width: 1200px) {
            .dashboard {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }

            .sidebar-header span, .menu-item span {
                display: none;
            }

            .menu-item {
                justify-content: center;
            }

            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            .search-bar {
                width: 100%;
            }
            
            .modal-content {
                min-width: 90%;
            }
        }
        
        /* Estilos para SweetAlert2 */
        .swal2-container {
            background: rgba(0, 0, 0, 0.4) !important;
        }
        
        .swal2-popup {
            border: none !important;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15) !important;
            border-radius: 12px !important;
            overflow: hidden !important;
            padding: 2rem !important;
        }
        
        .swal2-confirm {
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary)) !important;
            color: white !important;
            border: none !important;
            border-radius: 8px !important;
            padding: 12px 24px !important;
            font-weight: 500 !important;
            font-size: 15px !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 8px rgba(67, 97, 238, 0.3) !important;
            margin: 0 8px !important;
            outline: none !important;
        }
        
        .swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 12px rgba(67, 97, 238, 0.4) !important;
        }
        
        .swal2-cancel {
            background: white !important;
            color: var(--color-dark) !important;
            border: 2px solid #e0e0e0 !important;
            border-radius: 8px !important;
            padding: 12px 24px !important;
            font-weight: 500 !important;
            font-size: 15px !important;
            transition: all 0.3s ease !important;
            margin: 0 8px !important;
            outline: none !important;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1) !important;
        }
        
        .swal2-cancel:hover {
            background: #f5f5f5 !important;
            border-color: #d0d0d0 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15) !important;
        }
        
        .swal2-icon.swal2-question {
            border-color: var(--color-primary) !important;
            color: var(--color-primary) !important;
        }

        /* Para evitar que los botones dentro de las celdas cambien el cursor */
        table tbody tr .actions button {
            cursor: default;
        }
    </style>
</head>
<body>
    <!-- 🎀 Menú Lateral -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-laptop"></i>
            <span>PeopleHub</span>
        </div>

        <div class="sidebar-menu">
            <div class="menu-item <?php echo !isset($_GET['mostrar_incidencias']) ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Inicio</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-building"></i>
                <span>Sedes</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-laptop-house"></i>
                <span>Salones</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-laptop"></i>
                <span>Computadoras</span>
            </div>
            <div class="menu-item <?php echo isset($_GET['mostrar_incidencias']) ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Incidentes</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Configuración</span>
            </div>
        </div>
    </aside>

    <!-- Contenido Principal -->
    <div class="main-content">
        <!-- 🎀 Header -->
        <header class="header">
            <div class="logo">
                <i class="fas fa-laptop"></i>
                <span>PeopleHub</span>
            </div>
            
            <div class="search-bar">
                <input type="text" placeholder="Buscar salones o computadoras...">
                <button><i class="fas fa-search"></i></button>
            </div>
            
            <div class="user-menu" onclick="confirmLogout()">
                <span><?php echo htmlspecialchars($nombreUsuario); ?></span>
                <img src="https://randomuser.me/api/portraits/women/65.jpg" alt="Usuario">
            </div>
        </header>

        <!-- 📊 Tarjetas Dashboard con las sedes -->
        <?php if (!isset($_GET['mostrar_incidencias'])): ?>
        <section class="dashboard">
            <?php foreach ($sedes as $sede): ?>
            <div class="card" onclick="window.location.href='dashboard.php?sede_id=<?php echo $sede['id']; ?>'">
                <div class="card-header">
                    <h3><?php echo htmlspecialchars($sede['nombre']); ?></h3>
                    <i class="fas fa-building"></i>
                </div>
                <p><?php echo htmlspecialchars($sede['direccion']); ?></p>
                <div class="progress-bar">
                    <div class="progress"></div>
                </div>
                <p>Responsable: <?php echo htmlspecialchars($sede['responsable']); ?></p>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- 📜 Tabla de Salones o Computadoras -->
        <section class="data-section">
            <div class="table-container">
                <div class="table-header">
                    <?php if (isset($_GET['sede_id'])): ?>
                    <h2 class="table-title">Salones de la Sede</h2>
                    <div class="table-actions">
                        <button class="btn-primary" id="btnAgregarSalon"><i class="fas fa-plus"></i> Agregar Salón</button>
                    </div>
                    <?php elseif (isset($_GET['salon_id'])): ?>
                    <h2 class="table-title">Computadoras del Salón</h2>
                    <div class="table-actions">
                        <button class="btn-primary" id="btnAgregarComputadora"><i class="fas fa-plus"></i> Agregar Computadora</button>
                    </div>
                    <?php elseif (isset($_GET['mostrar_incidencias'])): ?>
                    <h2 class="table-title">Gestión de Incidentes</h2>
                    <div class="table-actions">
                        <button class="btn-primary" id="btnNuevaIncidencia"><i class="fas fa-plus"></i> Nueva Incidencia</button>
                    </div>
                    <?php else: ?>
                    <h2 class="table-title">Seleccione una sede para ver los salones</h2>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($_GET['sede_id']) && !empty($salones)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Piso</th>
                            <th>Capacidad</th>
                            <th>Computadoras</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salones as $salon): ?>
                        <tr onclick="window.location.href='dashboard.php?salon_id=<?php echo $salon['id']; ?>'">
                            <td><?php echo htmlspecialchars($salon['codigo_salon']); ?></td>
                            <td><?php echo htmlspecialchars($salon['descripcion']); ?></td>
                            <td><?php echo htmlspecialchars($salon['piso']); ?></td>
                            <td><?php echo htmlspecialchars($salon['capacidad']); ?></td>
                            <td><?php echo htmlspecialchars($salon['numero_computadores']); ?></td>
                            <td class="actions">
                                <button class="view-btn" onclick="event.stopPropagation(); verSalon(<?php echo $salon['id']; ?>)"><i class="fas fa-eye"></i></button>
                                <button class="edit-btn" onclick="event.stopPropagation(); editarSalon(<?php echo $salon['id']; ?>)"><i class="fas fa-edit"></i></button>
                                <button class="delete-btn" onclick="event.stopPropagation(); eliminarSalon(<?php echo $salon['id']; ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php elseif (isset($_GET['salon_id']) && !empty($computadoras)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Marca/Modelo</th>
                            <th>Especificaciones</th>
                            <th>Estado</th>
                            <th>Últ. Mantenimiento</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($computadoras as $pc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pc['codigo_patrimonio']); ?></td>
                            <td>
                                <div class="pc-info">
                                    <i class="fas fa-laptop" style="font-size: 2rem; color: #6c5ce7;"></i>
                                    <div>
                                        <strong><?php echo htmlspecialchars($pc['marca']); ?></strong>
                                        <p><?php echo htmlspecialchars($pc['modelo']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong>SO:</strong> <?php echo htmlspecialchars($pc['sistema_operativo']); ?><br>
                                <strong>RAM:</strong> <?php echo htmlspecialchars($pc['ram_gb']); ?>GB<br>
                                <strong>Alm.:</strong> <?php echo htmlspecialchars($pc['almacenamiento_gb']); ?>GB <?php echo htmlspecialchars($pc['tipo_almacenamiento']); ?>
                            </td>
                            <td>
                                <?php 
                                $badge_class = '';
                                if ($pc['estado'] == 'operativo') {
                                    $badge_class = 'badge-success';
                                } elseif ($pc['estado'] == 'mantenimiento') {
                                    $badge_class = 'badge-warning';
                                } else {
                                    $badge_class = 'badge-danger';
                                }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($pc['estado']); ?></span>
                            </td>
                            <td><?php echo $pc['ultimo_mantenimiento'] ? date('d/m/Y', strtotime($pc['ultimo_mantenimiento'])) : 'Nunca'; ?></td>
                            <td class="actions">
                                <button class="view-btn" onclick="verComputadora(<?php echo $pc['id']; ?>)"><i class="fas fa-eye"></i></button>
                                <button class="edit-btn" onclick="editarComputadora(<?php echo $pc['id']; ?>)"><i class="fas fa-edit"></i></button>
                                <button class="edit-btn" onclick="editarIncidencia(<?= $incidencia['id'] ?>)"><i class="fas fa-edit"></i></button>
                                <button class="delete-btn" onclick="eliminarComputadora(<?php echo $pc['id']; ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php elseif (isset($_GET['mostrar_incidencias']) && !empty($incidencias)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Computadora</th>
                            <th>Descripcion</th>
                            <th>Reportado por</th>
                            <th>Asignado a</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Fecha Reporte</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incidencias as $incidencia): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($incidencia['id']); ?></td>
                            <td><?php echo htmlspecialchars($incidencia['codigo_patrimonio'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($incidencia['titulo']); ?></td>
                            <td><?php echo htmlspecialchars($incidencia['reportante']); ?></td>
                            <td><?php echo htmlspecialchars($incidencia['asignado'] ?? 'No asignado'); ?></td>
                            <td>
                                <?php 
                                $badge_class = '';
                                switch($incidencia['estado']) {
                                    case 'reportada': $badge_class = 'badge-warning'; break;
                                    case 'asignada': $badge_class = 'badge-primary'; break;
                                    case 'en_proceso': $badge_class = 'badge-info'; break;
                                    case 'resuelta': $badge_class = 'badge-success'; break;
                                    case 'cerrada': $badge_class = 'badge-secondary'; break;
                                    default: $badge_class = 'badge-light';
                                }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $incidencia['estado'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $priority_class = '';
                                switch($incidencia['prioridad']) {
                                    case 'baja': $priority_class = 'badge-success'; break;
                                    case 'media': $priority_class = 'badge-warning'; break;
                                    case 'alta': $priority_class = 'badge-danger'; break;
                                    case 'critica': $priority_class = 'badge-dark'; break;
                                    default: $priority_class = 'badge-light';
                                }
                                ?>
                                <span class="badge <?php echo $priority_class; ?>">
                                    <?php echo ucfirst($incidencia['prioridad']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($incidencia['fecha_reporte'])); ?></td>
                            <td class="actions">
                                <button class="view-btn" onclick="verIncidencia(<?php echo $incidencia['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="edit-btn" onclick="editarIncidencia(<?php echo $incidencia['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="delete-btn" onclick="eliminarIncidencia(<?php echo $incidencia['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
                <?php elseif (isset($_GET['sede_id']) && empty($salones)): ?>
                <p>No hay salones registrados en esta sede.</p>
                
                <?php elseif (isset($_GET['sede_id']) && empty($salones)): ?>
                <p>No hay salones registrados en esta sede.</p>
                <?php endif; ?>
            </div> <!-- Cierra table-container -->
        </section> <!-- Cierra data-section -->
    </div> <!-- Cierra main-content -->
    <script>
    // Manejar clic en el ítem de incidentes del menú
    document.querySelector('.menu-item:nth-child(5)').addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = 'dashboard.php?mostrar_incidencias=1';
    });

    // Función para ver incidencias
    function verIncidencia(id) {
        window.location.href = 'ver_incidencia.php?id=' + id;
    }

    function editarIncidencia(id) {
    window.location.href = 'editar_incidencia.php?id=' + id;
    }

    // Función para elimina incidencia
    document.getElementById('btnEliminarIncidencia').addEventListener('click', function() {
        window.location.href = 'eliminar_incidencia.php';
    })
    // Función para nueva incidencia
    document.getElementById('btnNuevaIncidencia').addEventListener('click', function() {
        window.location.href = 'nueva_incidencia.php';
    });
    </script>
</body>
</html>