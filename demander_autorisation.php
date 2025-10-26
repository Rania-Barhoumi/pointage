<?php
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit;
}

// Configuration de la base de données
$host = 'localhost';
$dbname = 'pointage';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $date = $_POST['date'];
    $heure_sortie = $_POST['heure_sortie'];
    $heure_retour = $_POST['heure_retour'];
    $motif = $_POST['motif'];
    
    // Validation des données
    if (empty($date) || empty($heure_sortie) || empty($heure_retour) || empty($motif)) {
        $_SESSION['message'] = "Tous les champs sont obligatoires.";
        header("Location: employee_dashboard.php");
        exit;
    }
    
    // Vérifier que l'heure de retour est après l'heure de sortie
    if (strtotime($heure_retour) <= strtotime($heure_sortie)) {
        $_SESSION['message'] = "L'heure de retour doit être après l'heure de sortie.";
        header("Location: employee_dashboard.php");
        exit;
    }
    
    // Insérer la demande dans la base de données
    try {
        $stmt = $pdo->prepare("INSERT INTO autorisations (user_id, date, heure_sortie, heure_retour, motif, statut, date_demande) VALUES (?, ?, ?, ?, ?, 'en_attente', NOW())");
        $stmt->execute([$user_id, $date, $heure_sortie, $heure_retour, $motif]);
        
        $_SESSION['message'] = "Votre demande d'autorisation a été soumise avec succès.";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Erreur lors de la soumission de la demande: " . $e->getMessage();
    }
    
    header("Location: employee_dashboard.php");
    exit;
}
?>