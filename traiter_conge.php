<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = new PDO("mysql:host=localhost;dbname=pointage", "root", "");
    
    $conge_id = $_POST['conge_id'];
    $action = $_POST['action'];
    $admin_id = $_SESSION['user_id'];
    
    // Récupérer les informations de la demande
    $stmt = $pdo->prepare("SELECT c.*, u.name as employe_nom, u.id as user_id 
                          FROM conges c 
                          JOIN users u ON c.user_id = u.id 
                          WHERE c.id = ?");
    $stmt->execute([$conge_id]);
    $demande = $stmt->fetch();
    
    if (!$demande) {
        $_SESSION['admin_message'] = "Demande de congé introuvable";
        header("Location: admin_dashboard.php?page=conges");
        exit;
    }
    
    // Mettre à jour le statut
    $nouveau_statut = $action === 'approuver' ? 'approuve' : 'refuse';
    $stmt = $pdo->prepare("UPDATE conges SET statut = ?, date_traitement = NOW(), traite_par = ? WHERE id = ?");
    $stmt->execute([$nouveau_statut, $admin_id, $conge_id]);
    
    // Notifier l'employé
    $message = "Votre demande de congé du {$demande['date_debut']} au {$demande['date_fin']} a été " . 
               ($action === 'approuver' ? 'approuvée' : 'refusée');
    
    $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'reponse_conge')");
    $stmt_notif->execute([$demande['user_id'], $message]);
    
    $_SESSION['admin_message'] = "Demande de congé traitée avec succès";
    header("Location: admin_dashboard.php?page=conges");
    exit;
}