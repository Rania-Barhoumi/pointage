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

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $montant = floatval($_POST['montant']);
    $motif = trim($_POST['motif']);
    
    // Validation des données
    if ($montant <= 0 || $montant > 1000) {
        $_SESSION['message'] = "Le montant doit être compris entre 0 et 1000 DT";
        header("Location: employee_dashboard.php");
        exit;
    }
    
    if (empty($motif)) {
        $_SESSION['message'] = "Veuillez saisir un motif pour votre demande";
        header("Location: employee_dashboard.php");
        exit;
    }
    
    // Vérifier la limite de demandes du mois
    $current_month = date('m');
    $current_year = date('Y');
    $stmt_count = $pdo->prepare("SELECT COUNT(*) as count FROM avances_salaire WHERE user_id = ? AND MONTH(date_demande) = ? AND YEAR(date_demande) = ?");
    $stmt_count->execute([$user_id, $current_month, $current_year]);
    $count = $stmt_count->fetch()['count'];
    
    if ($count >= 2) {
        $_SESSION['message'] = "Vous avez atteint la limite de 2 demandes d'avance par mois";
        header("Location: employee_dashboard.php");
        exit;
    }
    
    // Insérer la demande dans la base de données
    try {
        $stmt = $pdo->prepare("INSERT INTO avances_salaire (user_id, montant, motif) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $montant, $motif]);
        
        $_SESSION['message'] = "Votre demande d'avance de salaire a été soumise avec succès";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Erreur lors de l'enregistrement de la demande: " . $e->getMessage();
    }
    
    header("Location: employee_dashboard.php");
    exit;
} else {
    header("Location: employee_dashboard.php");
    exit;
}
?>