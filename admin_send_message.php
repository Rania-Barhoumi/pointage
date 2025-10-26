<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

$admin_id = $_SESSION['user_id'];
$receiver_id = $_POST['receiver_id'] ?? '';
$message = $_POST['message'] ?? '';

if (!$receiver_id || !$message) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'error' => 'Données manquantes']);
    exit;
}

// Vérifier que le destinataire est un employé
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'employee'");
$stmt->execute([$receiver_id]);
$employee = $stmt->fetch();

if (!$employee) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'error' => 'Destinataire invalide']);
    exit;
}

// Envoyer le message
$stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
$success = $stmt->execute([$admin_id, $receiver_id, $message]);

header('Content-Type: application/json');
echo json_encode(['success' => $success]);
?>