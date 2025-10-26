<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pdg') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$admin_id = $_GET['admin_id'] ?? 0;

try {
    // Récupérer les messages entre le PDG et l'admin sélectionné
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.timestamp ASC
    ");
    $stmt->execute([$_SESSION['user_id'], $admin_id, $admin_id, $_SESSION['user_id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Marquer les messages comme lus
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = TRUE 
        WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$_SESSION['user_id'], $admin_id]);
    
    // Formater les dates pour l'affichage
    foreach ($messages as &$message) {
        $message['timestamp'] = date('d/m/Y H:i', strtotime($message['timestamp']));
    }
    
    header('Content-Type: application/json');
    echo json_encode($messages);
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}
?>