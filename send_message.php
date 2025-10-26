<?php
session_start();
require_once 'config.php'; // Votre fichier de configuration de base de données

$user_id = $_SESSION['user_id'];
$message = $_POST['message'];

// Déterminer le destinataire (l'admin)
$stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin) {
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $success = $stmt->execute([$user_id, $admin['id'], $message]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $success]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Admin non trouvé']);
}
?>