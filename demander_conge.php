<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=pointage", "root", "");

// Fonction pour initialiser ou récupérer le solde d'un mois
function getSoldeMois($pdo, $user_id, $month, $year) {
    $stmt = $pdo->prepare("
        SELECT solde_jours, solde_utilise 
        FROM conges_solde 
        WHERE user_id = ? AND mois = ? AND annee = ?
    ");
    $stmt->execute([$user_id, $month, $year]);
    $solde = $stmt->fetch();
    
    if (!$solde) {
        // Initialiser le solde pour ce mois
        $stmt = $pdo->prepare("
            INSERT INTO conges_solde (user_id, annee, mois, solde_jours, solde_utilise)
            VALUES (?, ?, ?, 1.5, 0)
        ");
        $stmt->execute([$user_id, $year, $month]);
        return ['solde_jours' => 1.5, 'solde_utilise' => 0];
    }
    
    return $solde;
}

// Fonction pour calculer le solde cumulé
function getSoldeCumule($pdo, $user_id, $current_month, $current_year) {
    $stmt = $pdo->prepare("
        SELECT SUM(solde_jours - solde_utilise) as solde_cumule
        FROM conges_solde 
        WHERE user_id = ? 
        AND (annee < ? OR (annee = ? AND mois <= ?))
        AND (solde_jours - solde_utilise) > 0
    ");
    $stmt->execute([$user_id, $current_year, $current_year, $current_month]);
    $result = $stmt->fetch();
    return $result['solde_cumule'] ?? 0;
}

$user_id = $_SESSION['user_id'];
$current_month = date('m');
$current_year = date('Y');

// Calculer la durée du congé demandé
$date_debut = new DateTime($_POST['date_debut']);
$date_fin = new DateTime($_POST['date_fin']);
$duree_conge = $date_debut->diff($date_fin)->days + 1; // +1 pour inclure le jour de début

// Récupérer le solde du mois en cours
$solde_mois_courant = getSoldeMois($pdo, $user_id, $current_month, $current_year);
$solde_cumule = getSoldeCumule($pdo, $user_id, $current_month, $current_year);

// Déterminer combien de jours seront avec solde
$jours_avec_solde = 0;
$jours_sans_solde = 0;

if ($solde_cumule >= $duree_conge) {
    // Tous les jours peuvent être avec solde
    $jours_avec_solde = $duree_conge;
    $jours_sans_solde = 0;
} else {
    // Seulement une partie peut être avec solde
    $jours_avec_solde = $solde_cumule;
    $jours_sans_solde = $duree_conge - $solde_cumule;
}

// Mettre à jour le solde utilisé
if ($jours_avec_solde > 0) {
    // Distribuer les jours avec solde sur les mois disponibles (du plus ancien au plus récent)
    $jours_restants_a_utiliser = $jours_avec_solde;
    
    $stmt = $pdo->prepare("
        SELECT id, mois, annee, (solde_jours - solde_utilise) as solde_disponible
        FROM conges_solde 
        WHERE user_id = ? 
        AND (solde_jours - solde_utilise) > 0
        ORDER BY annee ASC, mois ASC
    ");
    $stmt->execute([$user_id]);
    $soldes_disponibles = $stmt->fetchAll();
    
    foreach ($soldes_disponibles as $solde) {
        if ($jours_restants_a_utiliser <= 0) break;
        
        $a_utiliser = min($jours_restants_a_utiliser, $solde['solde_disponible']);
        
        $stmt_update = $pdo->prepare("
            UPDATE conges_solde 
            SET solde_utilise = solde_utilise + ?
            WHERE id = ?
        ");
        $stmt_update->execute([$a_utiliser, $solde['id']]);
        
        $jours_restants_a_utiliser -= $a_utiliser;
    }
}

// Déterminer si c'est avec ou sans solde (pour la compatibilité avec l'ancien système)
$avec_solde = ($jours_sans_solde == 0) ? 1 : 0;

// Insérer la demande - CORRIGÉ : retirer la colonne duree_jours qui n'existe pas
$stmt = $pdo->prepare("
    INSERT INTO conges (user_id, date_debut, date_fin, type_conge, cause, avec_solde, date_demande, statut, jours_avec_solde, jours_sans_solde)
    VALUES (?, ?, ?, ?, ?, ?, NOW(), 'en_attente', ?, ?)
");
$stmt->execute([
    $user_id,
    $_POST['date_debut'],
    $_POST['date_fin'],
    $_POST['type_conge'],
    $_POST['cause'],
    $avec_solde,
    $jours_avec_solde,
    $jours_sans_solde
]);

// Message de confirmation
if ($jours_sans_solde > 0) {
    $_SESSION['message'] = "Demande de congé soumise. $jours_avec_solde jour(s) avec solde et $jours_sans_solde jour(s) sans solde.";
} else {
    $_SESSION['message'] = "Demande de congé avec solde soumise avec succès ($jours_avec_solde jour(s)).";
}

header("Location: employee_dashboard.php");
exit;
?>