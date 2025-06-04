<?php
require 'conexion.php';
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if (!isset($_GET['id']) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
    exit;
}

$id = $_GET['id'];

try {
    $stmt = $conn->prepare("DELETE FROM incidencias WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Incidencia no encontrada']);
    }
} catch(PDOException $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>