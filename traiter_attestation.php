<?php
// Activez le rapport d'erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=pointage", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attestation_id = $_POST['attestation_id'];
    $type_attestation = $_POST['type_attestation'];
    $statut = $_POST['statut'];
    
    // Déterminer la table en fonction du type d'attestation
    $table = ($type_attestation === 'travail') ? 'attestations_travail' : 'attestations_salaire';
    
    try {
        // Mettre à jour le statut de l'attestation
        $stmt = $pdo->prepare("UPDATE $table SET statut = ?, date_traitement = NOW() WHERE id = ?");
        $stmt->execute([$statut, $attestation_id]);
        
        // Récupérer les infos de l'attestation pour la notification
        $stmt_info = $pdo->prepare("
            SELECT a.*, u.name, u.id as user_id 
            FROM $table a 
            JOIN users u ON a.user_id = u.id 
            WHERE a.id = ?
        ");
        $stmt_info->execute([$attestation_id]);
        $attestation = $stmt_info->fetch();
        
        // Créer une notification pour l'employé
        $type_fr = ($type_attestation === 'travail') ? 'de travail' : 'de salaire';
        $message = "Votre demande d'attestation $type_fr pour l'année " . $attestation['annee'] . " a été " . 
                  ($statut == 'approuve' ? 'approuvée' : 'refusée');
        
        $stmt_notif = $pdo->prepare("
            INSERT INTO notifications (user_id, message, date_creation) 
            VALUES (?, ?, NOW())
        ");
        $stmt_notif->execute([$attestation['user_id'], $message]);
        
        $_SESSION['admin_message'] = "Demande d'attestation $type_fr " . 
            ($statut == 'approuve' ? 'approuvée' : 'refusée') . 
            " avec succès. L'employé a été notifié.";
    } catch (PDOException $e) {
        $_SESSION['admin_message'] = "Erreur: " . $e->getMessage();
    }
}

// Vérifiez le nom exact de votre fichier d'administration
header("Location: admin_dashboard.php?page=attestations");
exit;
?>