<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

try {
    // Récupérer le PDG
    $pdg = $pdo->query("SELECT id FROM users WHERE role = 'pdg'")->fetch();
    
    $result = [
        'pdg' => 0,
        'employees' => [],
        'total' => 0
    ];
    
    // Messages non lus du PDG
    if ($pdg) {
        $stmt_pdg = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE");
        $stmt_pdg->execute([$_SESSION['user_id'], $pdg['id']]);
        $result['pdg'] = $stmt_pdg->fetch()['count'];
    }
    
    // Messages non lus des employés
    $stmt_employees = $pdo->prepare("
        SELECT sender_id, COUNT(*) as unread_count 
        FROM messages 
        WHERE receiver_id = ? AND is_read = FALSE 
        GROUP BY sender_id
    ");
    $stmt_employees->execute([$_SESSION['user_id']]);
    $employee_counts = $stmt_employees->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $result['employees'] = $employee_counts;
    $result['total'] = $result['pdg'] + array_sum($employee_counts);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}
?>