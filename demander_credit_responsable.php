<?php
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'responsable') {
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
$name = $_SESSION['name'];

// Vérification des données du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et validation des données
    $montant = filter_input(INPUT_POST, 'montant', FILTER_VALIDATE_FLOAT);
    $nombre_mensualites = filter_input(INPUT_POST, 'nombre_mensualites', FILTER_VALIDATE_INT);
    $motif = trim($_POST['motif'] ?? '');
    
    // Validation des données
    $errors = [];
    
    // Validation du montant
    if ($montant === false || $montant <= 0) {
        $errors[] = "Le montant doit être un nombre positif.";
    }
    
    // Validation du nombre de mensualités
    if ($nombre_mensualites === false || $nombre_mensualites < 1 || $nombre_mensualites > 12) {
        $errors[] = "Le nombre de mensualités doit être compris entre 1 et 12.";
    }
    
    // Validation du motif
    if (empty($motif) || strlen($motif) < 10) {
        $errors[] = "Le motif doit contenir au moins 10 caractères.";
    }
    
    // Vérification de l'ancienneté
    $stmt_anciennete = $pdo->prepare("SELECT date_embauche FROM users WHERE id = ?");
    $stmt_anciennete->execute([$user_id]);
    $result_anciennete = $stmt_anciennete->fetch();
    $date_embauche = $result_anciennete ? $result_anciennete['date_embauche'] : null;
    
    $anciennete_suffisante = false;
    if ($date_embauche) {
        $date_embauche_obj = new DateTime($date_embauche);
        $aujourdhui = new DateTime();
        $difference = $date_embauche_obj->diff($aujourdhui);
        $anciennete_mois = $difference->y * 12 + $difference->m;
        $anciennete_suffisante = $anciennete_mois >= 12;
    }
    
    if (!$anciennete_suffisante) {
        $errors[] = "Vous devez justifier d'une ancienneté minimale d'un an pour demander un crédit.";
    }
    
    // Vérification des crédits en cours
    $stmt_credit_en_cours = $pdo->prepare("SELECT COUNT(*) as count FROM credits_salaire WHERE user_id = ? AND statut = 'approuve' AND solde_restant > 0");
    $stmt_credit_en_cours->execute([$user_id]);
    $credit_en_cours = $stmt_credit_en_cours->fetch()['count'] > 0;
    
    if ($credit_en_cours) {
        $errors[] = "Vous avez déjà un crédit en cours de remboursement.";
    }
    
    // Vérification du délai de 6 mois après remboursement complet
    $stmt_dernier_credit = $pdo->prepare("SELECT date_remboursement_complet FROM credits_salaire WHERE user_id = ? AND statut = 'approuve' AND solde_restant = 0 ORDER BY date_remboursement_complet DESC LIMIT 1");
    $stmt_dernier_credit->execute([$user_id]);
    $dernier_credit = $stmt_dernier_credit->fetch();
    
    $peut_redemander = true;
    if ($dernier_credit && $dernier_credit['date_remboursement_complet']) {
        $date_remboursement = new DateTime($dernier_credit['date_remboursement_complet']);
        $aujourdhui = new DateTime();
        $difference = $date_remboursement->diff($aujourdhui);
        $mois_ecoules = $difference->y * 12 + $difference->m;
        $peut_redemander = $mois_ecoules >= 6;
    }
    
    if (!$peut_redemander) {
        $errors[] = "Un délai de 6 mois doit s'écouler après le remboursement complet d'un crédit avant de pouvoir en demander un nouveau.";
    }
    
    // Récupération du salaire mensuel pour vérification du montant maximum
    $stmt_salaire = $pdo->prepare("SELECT salaire_mensuel FROM salaires_employes WHERE user_id = ? ORDER BY date_effet DESC LIMIT 1");
    $stmt_salaire->execute([$user_id]);
    $result_salaire = $stmt_salaire->fetch();
    $salaire_mensuel = $result_salaire ? $result_salaire['salaire_mensuel'] : 1500.00;
    
    if ($montant > $salaire_mensuel) {
        $errors[] = "Le montant du crédit ne peut pas dépasser votre salaire mensuel (" . number_format($salaire_mensuel, 2, ',', ' ') . " DT).";
    }
    
    // Si aucune erreur, enregistrement de la demande
    if (empty($errors)) {
        try {
            // Calcul de la mensualité
            $montant_mensualite = $montant / $nombre_mensualites;
            
            // Insertion de la demande
            $stmt = $pdo->prepare("
                INSERT INTO credits_salaire 
                (user_id, montant, nombre_mensualites, montant_mensualite, motif, statut, solde_restant, date_demande) 
                VALUES (?, ?, ?, ?, ?, 'en_attente', ?, NOW())
            ");
            
            $stmt->execute([
                $user_id,
                $montant,
                $nombre_mensualites,
                $montant_mensualite,
                $motif,
                $montant // Solde restant initial = montant total
            ]);
            
            // Message de succès
            $_SESSION['message'] = "Votre demande de crédit de " . number_format($montant, 2, ',', ' ') . " DT a été soumise avec succès. Elle sera traitée par le service RH.";
            
            // Redirection vers le tableau de bord
            header("Location: responsable_dashboard.php?section=credits");
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de l'enregistrement de la demande: " . $e->getMessage();
        }
    }
    
    // Si erreurs, les stocker en session pour affichage
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = [
            'montant' => $montant,
            'nombre_mensualites' => $nombre_mensualites,
            'motif' => $motif
        ];
        header("Location: responsable_dashboard.php?section=credits");
        exit;
    }
    
} else {
    // Si la méthode n'est pas POST, redirection
    header("Location: responsable_dashboard.php?section=credits");
    exit;
}
?>