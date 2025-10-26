<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$user_id = $_SESSION['user_id'];

// Compter les messages non lus
$stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = FALSE");
$stmt->execute([$user_id]);
$result = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode(['unread_count' => $result['unread_count']]);
?>