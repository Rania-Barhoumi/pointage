<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pdg') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

try {
    // Récupérer le nombre de messages non lus par administrateur
    $stmt = $pdo->prepare("
        SELECT sender_id, COUNT(*) as unread_count 
        FROM messages 
        WHERE receiver_id = ? AND is_read = FALSE 
        GROUP BY sender_id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    header('Content-Type: application/json');
    echo json_encode($unread_counts);
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}
?>