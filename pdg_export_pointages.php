<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pdg') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Construire la requête avec les filtres
    $sql = "SELECT p.*, u.name as user_name, u.role as user_role, u.email as user_email
           FROM pointages p 
           JOIN users u ON p.user_id = u.id 
           WHERE 1=1";
    
    $params = [];
    
    if (isset($_GET['user']) && !empty($_GET['user'])) {
        $sql .= " AND p.user_id = ?";
        $params[] = $_GET['user'];
    }
    
    if (isset($_GET['date_custom']) && !empty($_GET['date_custom'])) {
        $sql .= " AND DATE(p.timestamp) = ?";
        $params[] = $_GET['date_custom'];
    } elseif (isset($_GET['date']) && !empty($_GET['date'])) {
        $today = date('Y-m-d');
        switch ($_GET['date']) {
            case 'today':
                $sql .= " AND DATE(p.timestamp) = ?";
                $params[] = $today;
                break;
            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $sql .= " AND DATE(p.timestamp) = ?";
                $params[] = $yesterday;
                break;
            case 'week':
                $weekStart = date('Y-m-d', strtotime('monday this week'));
                $sql .= " AND DATE(p.timestamp) >= ?";
                $params[] = $weekStart;
                break;
            case 'month':
                $monthStart = date('Y-m-01');
                $sql .= " AND DATE(p.timestamp) >= ?";
                $params[] = $monthStart;
                break;
        }
    }
    
    $sql .= " ORDER BY p.timestamp DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pointages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Générer un simple CSV pour l'instant (vous pouvez utiliser une librairie PDF comme TCPDF)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=pointages_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Utilisateur', 'Rôle', 'Type', 'Date et Heure', 'Email']);
    
    foreach ($pointages as $pointage) {
        fputcsv($output, [
            $pointage['user_name'],
            $pointage['user_role'],
            $pointage['type'],
            $pointage['timestamp'],
            $pointage['user_email']
        ]);
    }
    
    fclose($output);
    exit;
}
?>