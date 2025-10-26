<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([]);
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=pointage", "root", "");
$stmt = $pdo->prepare("
    SELECT sender_id, COUNT(*) as unread_count 
    FROM messages 
    WHERE receiver_id = ? AND is_read = FALSE 
    GROUP BY sender_id
");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo json_encode($result);
?>