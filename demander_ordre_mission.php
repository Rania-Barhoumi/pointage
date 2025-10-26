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
    $date_mission = $_POST['date_mission'];
    $heure_depart = $_POST['heure_depart'];
    $heure_arrivee = $_POST['heure_arrivee'];
    $destination = $_POST['destination'];
    $objet_mission = $_POST['objet_mission'];
    $moyens_transport = $_POST['moyens_transport'] ?? null;
    $frais_estimes = $_POST['frais_estimes'] ?? null;

    // Validation des heures
    if ($heure_arrivee <= $heure_depart) {
        $_SESSION['message'] = "L'heure d'arrivée doit être postérieure à l'heure de départ.";
        header("Location: employee_dashboard.php");
        exit;
    }

    // Validation de la date (ne pas permettre les dates passées)
    $today = date('Y-m-d');
    if ($date_mission < $today) {
        $_SESSION['message'] = "La date de mission ne peut pas être dans le passé.";
        header("Location: employee_dashboard.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO ordres_mission (user_id, date_mission, heure_depart, heure_arrivee, destination, objet_mission, moyens_transport, frais_estimes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $date_mission, $heure_depart, $heure_arrivee, $destination, $objet_mission, $moyens_transport, $frais_estimes]);
        
        $_SESSION['message'] = "Votre demande d'ordre de mission a été soumise avec succès.";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Erreur lors de la soumission de la demande: " . $e->getMessage();
    }
    
    header("Location: employee_dashboard.php");
    exit;
}
?>