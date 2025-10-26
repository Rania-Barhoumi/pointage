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

$user_id = $_SESSION['user_id'];
$type_attestation = $_POST['type_attestation'];
$annee = $_POST['annee'];
$current_year = date('Y');

// Vérification des limites
if ($type_attestation === 'travail') {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attestations_travail WHERE user_id = ? AND annee = ?");
    $stmt->execute([$user_id, $current_year]);
    $count = $stmt->fetch()['count'];
    
    if ($count >= 5) {
        $_SESSION['message'] = "Vous avez atteint la limite de 5 demandes d'attestation de travail pour cette année.";
        header("Location: employee_dashboard.php");
        exit;
    }
    
    // Insertion de la demande
    $stmt = $pdo->prepare("INSERT INTO attestations_travail (user_id, annee) VALUES (?, ?)");
    $stmt->execute([$user_id, $annee]);
    
} elseif ($type_attestation === 'salaire') {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attestations_salaire WHERE user_id = ? AND annee = ?");
    $stmt->execute([$user_id, $current_year]);
    $count = $stmt->fetch()['count'];
    
    if ($count >= 3) {
        $_SESSION['message'] = "Vous avez atteint la limite de 3 demandes d'attestation de salaire pour cette année.";
        header("Location: employee_dashboard.php");
        exit;
    }
    
    // Insertion de la demande
    $stmt = $pdo->prepare("INSERT INTO attestations_salaire (user_id, annee) VALUES (?, ?)");
    $stmt->execute([$user_id, $annee]);
}

$_SESSION['message'] = "Votre demande d'attestation a été enregistrée avec succès.";
header("Location: employee_dashboard.php");
exit;
?>