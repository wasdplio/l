<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Resto del código...
require 'conexion.php';
session_start();

if (!isset($_SESSION['usuario_id']) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$id = $_POST['id'];
$estado = $_POST['estado'];
$usuario_asignado_id = !empty($_POST['usuario_asignado_id']) ? $_POST['usuario_asignado_id'] : null;
$solucion = $_POST['solucion'] ?? null;

try {
    // Determinar si se debe actualizar la fecha de asignación o resolución
    $fecha_asignacion = null;
    $fecha_resolucion = null;
    
    if ($estado === 'asignada' && $usuario_asignado_id) {
        $fecha_asignacion = date('Y-m-d H:i:s');
    }
    
    if (($estado === 'resuelta' || $estado === 'cerrada') && $solucion) {
        $fecha_resolucion = date('Y-m-d H:i:s');
    }
    
    $stmt = $conn->prepare("
        UPDATE incidencias 
        SET estado = ?, 
            usuario_asignado_id = ?, 
            solucion = ?,
            fecha_asignacion = COALESCE(?, fecha_asignacion),
            fecha_resolucion = COALESCE(?, fecha_resolucion)
        WHERE id = ?
    ");
    
    $stmt->execute([
        $estado,
        $usuario_asignado_id,
        $solucion,
        $fecha_asignacion,
        $fecha_resolucion,
        $id
    ]);
    
    header("Location: ver_incidencia.php?id=$id");
    exit;
    
} catch(PDOException $e) {
    die("Error al actualizar la incidencia: " . $e->getMessage());
}
?>