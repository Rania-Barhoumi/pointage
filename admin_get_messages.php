<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$admin_id = $_SESSION['user_id'];
$employee_id = $_GET['employee_id'] ?? '';

if (!$employee_id) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([]);
    exit;
}

// Récupérer les messages entre l'admin et l'employé
$stmt = $pdo->prepare("
    SELECT m.*, u.name as sender_name 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE (m.sender_id = ? AND m.receiver_id = ?) 
       OR (m.sender_id = ? AND m.receiver_id = ?)
    ORDER BY m.timestamp ASC
");
$stmt->execute([$admin_id, $employee_id, $employee_id, $admin_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marquer les messages comme lus
$stmt = $pdo->prepare("
    UPDATE messages SET is_read = TRUE 
    WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE
");
$stmt->execute([$employee_id, $admin_id]);

header('Content-Type: application/json');
echo json_encode($messages);
?>