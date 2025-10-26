<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pdg') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

if (isset($_GET['id'])) {
    $pointage_id = intval($_GET['id']);
    
    $sql = "SELECT p.*, u.name as user_name, u.role as user_role, u.email as user_email
           FROM pointages p 
           JOIN users u ON p.user_id = u.id 
           WHERE p.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pointage_id]);
    $pointage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($pointage ?: null);
}
?>