<?php
session_start();
require_once 'config.php'; // Votre fichier de configuration de base de données

$user_id = $_SESSION['user_id'];

// Récupérer les messages
$stmt = $pdo->prepare("
    SELECT m.*, u.name as sender_name 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.receiver_id = ? OR m.sender_id = ? 
    ORDER BY m.timestamp ASC
");
$stmt->execute([$user_id, $user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($messages);
?>