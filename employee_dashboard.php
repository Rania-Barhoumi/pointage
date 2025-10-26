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

$name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];

// Récupération des pointages
$stmt = $pdo->prepare("SELECT type, timestamp FROM pointages WHERE user_id = ? ORDER BY timestamp DESC LIMIT 10");
$stmt->execute([$user_id]);
$pointages = $stmt->fetchAll();
$dernier_pointage = $pointages[0] ?? null;

// Récupération des demandes de congé
$stmt_conges = $pdo->prepare("SELECT date_debut, date_fin, type_conge, statut, date_demande FROM conges WHERE user_id = ? ORDER BY date_demande DESC");
$stmt_conges->execute([$user_id]);
$conges = $stmt_conges->fetchAll();

// Récupération des demandes d'autorisation
$stmt_autorisations = $pdo->prepare("SELECT * FROM autorisations WHERE user_id = ? ORDER BY date_demande DESC");
$stmt_autorisations->execute([$user_id]);
$autorisations = $stmt_autorisations->fetchAll();

// Récupération des notifications (CORRIGÉ)
$stmt_notifs = $pdo->prepare("
    SELECT 'conge' as type, CONCAT('Réponse à votre demande de congé: ', statut) as message, date_demande as date_creation 
    FROM conges 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'autorisation' as type, CONCAT('Réponse à votre demande d\'autorisation: ', statut) as message, date_demande as date_creation 
    FROM autorisations 
    WHERE user_id = ? AND statut != 'en_attente'
    ORDER BY date_creation DESC
");
$stmt_notifs->execute([$user_id, $user_id]);
$notifications = $stmt_notifs->fetchAll();

// Après la récupération des notifications, ajoutez:
// Récupération des demandes d'attestation de travail
$stmt_at = $pdo->prepare("SELECT * FROM attestations_travail WHERE user_id = ? ORDER BY date_demande DESC");
$stmt_at->execute([$user_id]);
$attestations_travail = $stmt_at->fetchAll();

// Récupération des demandes d'attestation de salaire
$stmt_as = $pdo->prepare("SELECT * FROM attestations_salaire WHERE user_id = ? ORDER BY date_demande DESC");
$stmt_as->execute([$user_id]);
$attestations_salaire = $stmt_as->fetchAll();

// Vérification des limites annuelles
$current_year = date('Y');
$stmt_count_at = $pdo->prepare("SELECT COUNT(*) as count FROM attestations_travail WHERE user_id = ? AND annee = ?");
$stmt_count_at->execute([$user_id, $current_year]);
$count_at = $stmt_count_at->fetch()['count'];

$stmt_count_as = $pdo->prepare("SELECT COUNT(*) as count FROM attestations_salaire WHERE user_id = ? AND annee = ?");
$stmt_count_as->execute([$user_id, $current_year]);
$count_as = $stmt_count_as->fetch()['count'];

$limite_atteinte_travail = $count_at >= 5;
$limite_atteinte_salaire = $count_as >= 3;

// Analyse des pointages pour les messages contextuels
$messages_contextuels = [];

if ($dernier_pointage) {
    $heure_pointee = new DateTime($dernier_pointage['timestamp']);
    $heure_actuelle = new DateTime();
    
    // Définir les heures de référence
    $heure_debut_normal = new DateTime('08:00:00');
    $heure_debut_min = new DateTime('07:30:00');
    $heure_fin_normal = new DateTime('17:00:00');
    
    // Vérification des écarts pour l'entrée
    if ($dernier_pointage['type'] === 'entrée') {
        // Vérifier si c'est un pointage du jour même
        if ($heure_pointee->format('Y-m-d') === $heure_actuelle->format('Y-m-d')) {
            // Cas 1: Pointage avant 7h30 (trop tôt)
            if ($heure_pointee < $heure_debut_min) {
                $interval = $heure_pointee->diff($heure_debut_min);
                $minutes_avance = $interval->h * 60 + $interval->i;
                
                $messages_contextuels[] = [
                    'type' => 'info',
                    'titre' => 'Arrivée anticipée',
                    'message' => "Vous avez pointé à " . $heure_pointee->format('H:i') . ", soit $minutes_avance minutes avant l'horaire minimum (07:30).",
                    'conseil' => "Merci de votre ponctualité ! Notez que les pointages avant 07:30 ne sont pas requis."
                ];
            } 
            // Cas 2: Pointage entre 7h30 et 8h00 (dans la plage autorisée)
            elseif ($heure_pointee >= $heure_debut_min && $heure_pointee < $heure_debut_normal) {
                $interval = $heure_pointee->diff($heure_debut_normal);
                $minutes_avance = $interval->h * 60 + $interval->i;
                
                $messages_contextuels[] = [
                    'type' => 'success',
                    'titre' => 'Arrivée dans les temps',
                    'message' => "Vous avez pointé à " . $heure_pointee->format('H:i') . ", $minutes_avance minutes avant l'horaire normal (08:00).",
                    'conseil' => "Parfait ! Vous êtes dans la plage horaire autorisée."
                ];
            }
            // Cas 3: Pointage après 8h00 (retard)
            elseif ($heure_pointee > $heure_debut_normal) {
                $interval = $heure_pointee->diff($heure_debut_normal);
                $minutes_retard = $interval->h * 60 + $interval->i;
                
                $messages_contextuels[] = [
                    'type' => 'warning',
                    'titre' => 'Retard',
                    'message' => "Vous avez pointé à " . $heure_pointee->format('H:i') . ", soit $minutes_retard minutes après l'horaire normal (08:00).",
                    'conseil' => "Merci de prévenir votre responsable si ce retard était imprévu."
                ];
            }
        }
    }
    
    // Vérification des écarts pour la sortie
    if ($dernier_pointage['type'] === 'sortie') {
        // Vérifier si c'est un pointage du jour même
        if ($heure_pointee->format('Y-m-d') === $heure_actuelle->format('Y-m-d')) {
            // Cas 1: Sortie avant 17h00 (départ anticipé)
            if ($heure_pointee < $heure_fin_normal) {
                $interval = $heure_pointee->diff($heure_fin_normal);
                $minutes_avance = $interval->h * 60 + $interval->i;
                
                $messages_contextuels[] = [
                    'type' => 'warning',
                    'titre' => 'Départ anticipé',
                    'message' => "Vous avez pointé à " . $heure_pointee->format('H:i') . ", soit $minutes_avance minutes avant l'horaire normal (17:00).",
                    'conseil' => "Assurez-vous que votre responsable est informé de ce départ anticipé."
                ];
            } 
            // Cas 2: Sortie après 17h00 (heures supplémentaires)
            elseif ($heure_pointee > $heure_fin_normal) {
                $interval = $heure_pointee->diff($heure_fin_normal);
                $minutes_supp = $interval->h * 60 + $interval->i;
                
                $messages_contextuels[] = [
                    'type' => 'info',
                    'titre' => 'Heures supplémentaires',
                    'message' => "Vous avez pointé à " . $heure_pointee->format('H:i') . ", soit $minutes_supp minutes après l'horaire normal (17:00).",
                    'conseil' => "Merci pour votre investissement ! N'oubliez pas de reporter ces heures supplémentaires."
                ];
            }
        }
    }
    
    // Vérification de l'oubli de pointer la sortie
    $stmt_last_entry = $pdo->prepare("SELECT timestamp FROM pointages WHERE user_id = ? AND type = 'entrée' ORDER BY timestamp DESC LIMIT 1");
    $stmt_last_entry->execute([$user_id]);
    $last_entry = $stmt_last_entry->fetch();
    
    if ($last_entry && $dernier_pointage['type'] === 'entrée') {
        $last_entry_time = new DateTime($last_entry['timestamp']);
        $now = new DateTime();
        
        // Si la dernière entrée date d'aujourd'hui et il n'y a pas de sortie enregistrée
        if ($last_entry_time->format('Y-m-d') === $now->format('Y-m-d')) {
            $stmt_today_exit = $pdo->prepare("SELECT COUNT(*) as count FROM pointages WHERE user_id = ? AND type = 'sortie' AND DATE(timestamp) = CURDATE()");
            $stmt_today_exit->execute([$user_id]);
            $today_exit_count = $stmt_today_exit->fetch()['count'];
            
            if ($today_exit_count == 0 && $now->format('H:i') > '17:30') {
                $messages_contextuels[] = [
                    'type' => 'warning',
                    'titre' => 'Sortie non pointée',
                    'message' => "Vous avez pointé l'entrée aujourd'hui mais pas la sortie.",
                    'conseil' => "N'oubliez pas de pointer votre sortie. Contactez les RH si besoin de régularisation."
                ];
            }
        }
    }
}

// Vérifier les heures supplémentaires hebdomadaires
$stmt_weekly_hours = $pdo->prepare("
    SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(timestamp_end) - TIME_TO_SEC(timestamp_start))) as total_extra 
    FROM (
        SELECT 
            MAX(CASE WHEN type = 'entrée' THEN timestamp END) as timestamp_start,
            MAX(CASE WHEN type = 'sortie' THEN timestamp END) as timestamp_end
        FROM pointages 
        WHERE user_id = ? 
        AND DATE(timestamp) BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
        GROUP BY DATE(timestamp)
    ) as sessions
    WHERE timestamp_start IS NOT NULL AND timestamp_end IS NOT NULL
    AND TIME(timestamp_start) < '08:00:00' OR TIME(timestamp_end) > '17:00:00'
");
$stmt_weekly_hours->execute([$user_id]);
$weekly_extra = $stmt_weekly_hours->fetch()['total_extra'];

if ($weekly_extra && $weekly_extra > '00:00:00') {
    $messages_contextuels[] = [
        'type' => 'info',
        'titre' => 'Heures supplémentaires cette semaine',
        'message' => "Vous avez effectué $weekly_extra heures supplémentaires cette semaine.",
        'conseil' => "Merci pour votre investissement ! Pensez à les faire valider par votre responsable."
    ];
}

// Récupération des notifications (CORRIGÉ et MIS À JOUR)
$stmt_notifs = $pdo->prepare("
    SELECT 'conge' as type, CONCAT('Réponse à votre demande de congé: ', statut) as message, date_demande as date_creation 
    FROM conges 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'autorisation' as type, CONCAT('Réponse à votre demande d\'autorisation: ', statut) as message, date_demande as date_creation 
    FROM autorisations 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'attestation_travail' as type, CONCAT('Réponse à votre demande d\'attestation de travail: ', statut) as message, date_demande as date_creation 
    FROM attestations_travail 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'attestation_salaire' as type, CONCAT('Réponse à votre demande d\'attestation de salaire: ', statut) as message, date_demande as date_creation 
    FROM attestations_salaire 
    WHERE user_id = ? AND statut != 'en_attente'
    ORDER BY date_creation DESC
");
$stmt_notifs->execute([$user_id, $user_id, $user_id, $user_id]);
$notifications = $stmt_notifs->fetchAll();

// Ajouter cette fonction pour vérifier les congés du mois
function getCongesMois($pdo, $user_id, $month, $year) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM conges 
        WHERE user_id = ? 
        AND MONTH(date_debut) = ? 
        AND YEAR(date_debut) = ? 
        AND statut = 'approuve'
        AND avec_solde = TRUE
    ");
    $stmt->execute([$user_id, $month, $year]);
    return $stmt->fetch()['count'];
}

// Vérifier les congés du mois actuel
$current_month = date('m');
$current_year = date('Y');
$conges_ce_mois = getCongesMois($pdo, $user_id, $current_month, $current_year);
$limite_conges_atteinte = $conges_ce_mois >= 1;

// Récupération des ordres de mission
$stmt_ordres_mission = $pdo->prepare("SELECT * FROM ordres_mission WHERE user_id = ? ORDER BY date_demande DESC");
$stmt_ordres_mission->execute([$user_id]);
$ordres_mission = $stmt_ordres_mission->fetchAll();

// Récupération des notifications (MISE À JOUR avec ordres_mission)
$stmt_notifs = $pdo->prepare("
    SELECT 'conge' as type, CONCAT('Réponse à votre demande de congé: ', statut) as message, date_demande as date_creation 
    FROM conges 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'autorisation' as type, CONCAT('Réponse à votre demande d\'autorisation: ', statut) as message, date_demande as date_creation 
    FROM autorisations 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'attestation_travail' as type, CONCAT('Réponse à votre demande d\'attestation de travail: ', statut) as message, date_demande as date_creation 
    FROM attestations_travail 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'attestation_salaire' as type, CONCAT('Réponse à votre demande d\'attestation de salaire: ', statut) as message, date_demande as date_creation 
    FROM attestations_salaire 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'ordre_mission' as type, CONCAT('Réponse à votre demande d\'ordre de mission: ', statut) as message, date_demande as date_creation 
    FROM ordres_mission 
    WHERE user_id = ? AND statut != 'en_attente'
    ORDER BY date_creation DESC
");
$stmt_notifs->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
$notifications = $stmt_notifs->fetchAll();

// Récupération des demandes d'avance de salaire
$stmt_avances = $pdo->prepare("SELECT * FROM avances_salaire WHERE user_id = ? ORDER BY date_demande DESC");
$stmt_avances->execute([$user_id]);
$avances_salaire = $stmt_avances->fetchAll();

// Vérification des limites d'avance (par exemple: maximum 2 demandes par mois)
$current_month = date('m');
$current_year = date('Y');
$stmt_count_avances = $pdo->prepare("SELECT COUNT(*) as count FROM avances_salaire WHERE user_id = ? AND MONTH(date_demande) = ? AND YEAR(date_demande) = ?");
$stmt_count_avances->execute([$user_id, $current_month, $current_year]);
$count_avances = $stmt_count_avances->fetch()['count'];
$limite_avances_atteinte = $count_avances >= 2;

// Récupération des notifications (MISE À JOUR avec avances_salaire)
$stmt_notifs = $pdo->prepare("
    SELECT 'conge' as type, CONCAT('Réponse à votre demande de congé: ', statut) as message, date_demande as date_creation 
    FROM conges 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'autorisation' as type, CONCAT('Réponse à votre demande d\'autorisation: ', statut) as message, date_demande as date_creation 
    FROM autorisations 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'attestation_travail' as type, CONCAT('Réponse à votre demande d\'attestation de travail: ', statut) as message, date_demande as date_creation 
    FROM attestations_travail 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'attestation_salaire' as type, CONCAT('Réponse à votre demande d\'attestation de salaire: ', statut) as message, date_demande as date_creation 
    FROM attestations_salaire 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'ordre_mission' as type, CONCAT('Réponse à votre demande d\'ordre de mission: ', statut) as message, date_demande as date_creation 
    FROM ordres_mission 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'avance_salaire' as type, CONCAT('Réponse à votre demande d\'avance de salaire: ', statut) as message, date_demande as date_creation 
    FROM avances_salaire 
    WHERE user_id = ? AND statut != 'en_attente'
    ORDER BY date_creation DESC
");
$stmt_notifs->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
$notifications = $stmt_notifs->fetchAll();

// ... code existant ...

// Récupération des notes de service pour l'employé
$sql_notes = "SELECT ns.*, 
                     CASE 
                         WHEN ns.destinataires = 'tous' THEN 1
                         WHEN ns.destinataires = 'employes' THEN 1
                         WHEN ns.destinataires = 'responsables' THEN 0
                         WHEN ns.destinataires = 'admin' THEN 0
                         ELSE 0
                     END as est_destinataire
              FROM notes_service ns
              WHERE ns.destinataires IN ('tous', 'employes')
              ORDER BY ns.date_creation DESC";

$stmt_notes = $pdo->query($sql_notes);
$notes_service = $stmt_notes->fetchAll(PDO::FETCH_ASSOC);

// Compter les notes non lues
$notes_non_lues = 0;
foreach ($notes_service as $note) {
    if ($note['est_destinataire']) {
        $stmt_lu = $pdo->prepare("SELECT id FROM notes_service_lus WHERE note_id = ? AND user_id = ?");
        $stmt_lu->execute([$note['id'], $_SESSION['user_id']]);
        if (!$stmt_lu->fetch()) {
            $notes_non_lues++;
        }
    }
}

// Marquer une note comme lue lorsqu'on accède à la section
if (isset($_GET['section']) && $_GET['section'] === 'notes_service' && isset($_GET['lire_note'])) {
    $note_id = $_GET['lire_note'];
    $stmt_marquer_lu = $pdo->prepare("INSERT IGNORE INTO notes_service_lus (note_id, user_id) VALUES (?, ?)");
    $stmt_marquer_lu->execute([$note_id, $_SESSION['user_id']]);
}

// Ajouter cette partie après la récupération des avances de salaire existante

// Récupération des demandes de crédit (avances exceptionnelles)
$stmt_credits = $pdo->prepare("SELECT * FROM credits_salaire WHERE user_id = ? ORDER BY date_demande DESC");
$stmt_credits->execute([$user_id]);
$credits_salaire = $stmt_credits->fetchAll();

// Récupération de la date d'embauche de l'employé
$stmt_anciennete = $pdo->prepare("SELECT date_embauche FROM users WHERE id = ?");
$stmt_anciennete->execute([$user_id]);
$result_anciennete = $stmt_anciennete->fetch();

// Vérification que le résultat existe et que date_embauche n'est pas null
$date_embauche = null;
if ($result_anciennete && isset($result_anciennete['date_embauche'])) {
    $date_embauche = $result_anciennete['date_embauche'];
}

$anciennete_suffisante = false;
if ($date_embauche) {
    try {
        $date_embauche_obj = new DateTime($date_embauche);
        $aujourdhui = new DateTime();
        $difference = $date_embauche_obj->diff($aujourdhui);
        $anciennete_mois = $difference->y * 12 + $difference->m;
        $anciennete_suffisante = $anciennete_mois >= 12;
    } catch (Exception $e) {
        // En cas d'erreur de format de date
        $anciennete_suffisante = false;
    }
}

// Pour l'instant, utilisation d'une valeur par défaut pour le salaire
$salaire_mensuel = 1500.00; // À adapter selon vos besoins

// Vérification si la colonne salaire_mensuel existe
try {
    $stmt_salaire = $pdo->prepare("SELECT salaire_mensuel FROM users WHERE id = ?");
    $stmt_salaire->execute([$user_id]);
    $result_salaire = $stmt_salaire->fetch();
    if ($result_salaire && isset($result_salaire['salaire_mensuel'])) {
        $salaire_mensuel = $result_salaire['salaire_mensuel'];
    }
} catch (PDOException $e) {
    // Si la colonne n'existe pas, on garde la valeur par défaut
    $salaire_mensuel = 1500.00;
}

// Récupération du salaire mensuel de l'employé
// Récupération du salaire mensuel actuel de l'employé
$stmt_salaire = $pdo->prepare("SELECT salaire_mensuel FROM salaires_employes WHERE user_id = ? ORDER BY date_effet DESC LIMIT 1");
$stmt_salaire->execute([$user_id]);
$result_salaire = $stmt_salaire->fetch();
$salaire_mensuel = $result_salaire ? $result_salaire['salaire_mensuel'] : 1500.00;

// Vérification si l'employé a un crédit en cours
$stmt_credit_en_cours = $pdo->prepare("SELECT COUNT(*) as count FROM credits_salaire WHERE user_id = ? AND statut = 'approuve' AND solde_restant > 0");
$stmt_credit_en_cours->execute([$user_id]);
$credit_en_cours = $stmt_credit_en_cours->fetch()['count'] > 0;

// Vérification du délai de 6 mois après remboursement complet
$peut_redemander = true;
$stmt_dernier_credit = $pdo->prepare("SELECT date_remboursement_complet FROM credits_salaire WHERE user_id = ? AND statut = 'approuve' AND solde_restant = 0 ORDER BY date_remboursement_complet DESC LIMIT 1");
$stmt_dernier_credit->execute([$user_id]);
$dernier_credit = $stmt_dernier_credit->fetch();

if ($dernier_credit && $dernier_credit['date_remboursement_complet']) {
    $date_remboursement = new DateTime($dernier_credit['date_remboursement_complet']);
    $aujourdhui = new DateTime();
    $difference = $date_remboursement->diff($aujourdhui);
    $mois_ecoules = $difference->y * 12 + $difference->m;
    $peut_redemander = $mois_ecoules >= 6;
}

// Mise à jour des notifications pour inclure les crédits
$stmt_notifs = $pdo->prepare("
    SELECT 'conge' as type, CONCAT('Réponse à votre demande de congé: ', statut) as message, date_demande as date_creation 
    FROM conges 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'autorisation' as type, CONCAT('Réponse à votre demande d\'autorisation: ', statut) as message, date_demande as date_creation 
    FROM autorisations 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'attestation_travail' as type, CONCAT('Réponse à votre demande d\'attestation de travail: ', statut) as message, date_demande as date_creation 
    FROM attestations_travail 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'attestation_salaire' as type, CONCAT('Réponse à votre demande d\'attestation de salaire: ', statut) as message, date_demande as date_creation 
    FROM attestations_salaire 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'ordre_mission' as type, CONCAT('Réponse à votre demande d\'ordre de mission: ', statut) as message, date_demande as date_creation 
    FROM ordres_mission 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'avance_salaire' as type, CONCAT('Réponse à votre demande d\'avance de salaire: ', statut) as message, date_demande as date_creation 
    FROM avances_salaire 
    WHERE user_id = ? AND statut != 'en_attente'
    UNION
    SELECT 'credit_salaire' as type, CONCAT('Réponse à votre demande de crédit: ', statut) as message, date_demande as date_creation 
    FROM credits_salaire 
    WHERE user_id = ? AND statut != 'en_attente'
    ORDER BY date_creation DESC
");
$stmt_notifs->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
$notifications = $stmt_notifs->fetchAll();
// Fonction pour récupérer le solde des congés
function getSoldeConges($pdo, $user_id) {
    $current_month = date('m');
    $current_year = date('Y');
    
    // Solde du mois en cours
    $stmt = $pdo->prepare("
        SELECT solde_jours, solde_utilise, (solde_jours - solde_utilise) as solde_restant
        FROM conges_solde 
        WHERE user_id = ? AND mois = ? AND annee = ?
    ");
    $stmt->execute([$user_id, $current_month, $current_year]);
    $solde_mois_courant = $stmt->fetch();
    
    if (!$solde_mois_courant) {
        $solde_mois_courant = [
            'solde_jours' => 1.5,
            'solde_utilise' => 0,
            'solde_restant' => 1.5
        ];
    }
    
    // Solde cumulé
    $stmt_cumule = $pdo->prepare("
        SELECT SUM(solde_jours - solde_utilise) as solde_cumule
        FROM conges_solde 
        WHERE user_id = ? 
        AND (annee < ? OR (annee = ? AND mois <= ?))
        AND (solde_jours - solde_utilise) > 0
    ");
    $stmt_cumule->execute([$user_id, $current_year, $current_year, $current_month]);
    $solde_cumule = $stmt_cumule->fetch()['solde_cumule'] ?? 0;
    
    return [
        'mois_courant' => $solde_mois_courant,
        'cumule' => $solde_cumule
    ];
}

// Récupérer le solde des congés
$solde_conges = getSoldeConges($pdo, $user_id);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tableau de bord Employé - <?= htmlspecialchars($name) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #4b6cb7;
      --secondary: #182848;
      --success: #2ecc71;
      --warning: #f39c12;
      --danger: #e74c3c;
      --info: #3498db;
      --light: #f8f9fa;
      --dark: #343a40;
      --gray: #6c757d;
      --bg-light: #f5f7fa;
      --text-light: #212529;
      --card-light: #ffffff;
      --bg-dark: #1a1a2e;
      --text-dark: #f8f9fa;
      --card-dark: #16213e;
      --border-radius: 12px;
      --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      --transition: all 0.3s ease;
    }

    .dark-mode {
      --bg-color: var(--bg-dark);
      --text-color: var(--text-dark);
      --card-bg: var(--card-dark);
      --header-bg: var(--secondary);
      --border-color: #2d3748;
    }

    .light-mode {
      --bg-color: var(--bg-light);
      --text-color: var(--text-light);
      --card-bg: var(--card-light);
      --header-bg: var(--primary);
      --border-color: #dee2e6;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background-color: var(--bg-color);
      color: var(--text-color);
      line-height: 1.6;
      transition: var(--transition);
      height: 100vh;
      overflow: hidden;
    }

    .app-container {
      display: flex;
      height: 100vh;
    }

    /* Sidebar Navigation */
    .sidebar {
      width: 260px;
      background: var(--header-bg);
      color: white;
      padding: 20px 0;
      height: 100%;
      display: flex;
      flex-direction: column;
      transition: var(--transition);
    }

    .sidebar-header {
      padding: 0 20px 20px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      margin-bottom: 20px;
    }

    .sidebar-header h1 {
      font-size: 1.5rem;
      font-weight: 700;
    }

    .user-info {
      display: flex;
      align-items: center;
      margin-top: 15px;
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 12px;
      font-weight: bold;
    }

    .user-details {
      flex: 1;
    }

    .user-name {
      font-weight: 600;
      font-size: 1rem;
    }

    .user-role {
      font-size: 0.8rem;
      opacity: 0.8;
    }

    .nav-menu {
      flex: 1;
      overflow-y: auto;
    }

    .nav-item {
      padding: 12px 20px;
      display: flex;
      align-items: center;
      cursor: pointer;
      transition: var(--transition);
      border-left: 4px solid transparent;
    }

    .nav-item:hover {
      background: rgba(255, 255, 255, 0.1);
    }

    .nav-item.active {
      background: rgba(255, 255, 255, 0.15);
      border-left-color: white;
    }

    .nav-item i {
      margin-right: 12px;
      width: 20px;
      text-align: center;
    }

    .sidebar-footer {
      padding: 15px 20px;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .logout-btn {
      display: flex;
      align-items: center;
      color: white;
      text-decoration: none;
      padding: 10px;
      border-radius: 6px;
      transition: var(--transition);
    }

    .logout-btn:hover {
      background: rgba(255, 255, 255, 0.1);
    }

    .logout-btn i {
      margin-right: 10px;
    }

    /* Main Content */
    .main-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      height: 100%;
      overflow: hidden;
    }

    .top-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 25px;
      background: var(--card-bg);
      border-bottom: 1px solid var(--border-color);
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .page-title {
      font-size: 1.5rem;
      font-weight: 600;
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .theme-toggle {
      background: none;
      border: none;
      color: var(--text-color);
      cursor: pointer;
      font-size: 1.2rem;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      transition: var(--transition);
    }

    .theme-toggle:hover {
      background: var(--bg-color);
    }

    .status-indicator {
      display: inline-flex;
      align-items: center;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 500;
    }

    .status-present {
      background-color: var(--success);
      color: white;
    }

    .status-absent {
      background-color: var(--danger);
      color: white;
    }

    .status-waiting {
      background-color: var(--warning);
      color: white;
    }

    .content-area {
      flex: 1;
      padding: 25px;
      overflow-y: auto;
    }

    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 25px;
      margin-bottom: 30px;
    }

    .card {
      background: var(--card-bg);
      border-radius: var(--border-radius);
      box-shadow: var(--box-shadow);
      overflow: hidden;
      transition: var(--transition);
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .card-header {
      padding: 18px 20px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .card-title {
      font-size: 1.1rem;
      font-weight: 600;
    }

    .card-action {
      color: var(--primary);
      font-size: 0.9rem;
      cursor: pointer;
    }

    .card-body {
      padding: 20px;
    }

    /* Pointage Section */
    .pointage-actions {
      display: flex;
      gap: 15px;
      margin-bottom: 20px;
    }

    .btn {
      padding: 12px 20px;
      border-radius: 8px;
      border: none;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .btn i {
      margin-right: 8px;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      opacity: 0.9;
      transform: translateY(-2px);
    }

    .btn-success {
      background: var(--success);
      color: white;
    }

    .btn-success:hover {
      opacity: 0.9;
      transform: translateY(-2px);
    }

    /* Table Styles */
    .table-container {
      overflow-x: auto;
      border-radius: var(--border-radius);
      border: 1px solid var(--border-color);
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid var(--border-color);
    }

    th {
      background-color: var(--bg-color);
      font-weight: 600;
      position: sticky;
      top: 0;
    }

    tr:last-child td {
      border-bottom: none;
    }

    tr:hover {
      background-color: var(--bg-color);
    }

    /* Notification Styles */
    .notification {
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 15px;
      border-left: 4px solid;
    }

    .notification.success {
      background-color: rgba(46, 204, 113, 0.15);
      color: var(--success);
      border-left-color: var(--success);
    }

    .notification.warning {
      background-color: rgba(243, 156, 18, 0.15);
      color: var(--warning);
      border-left-color: var(--warning);
    }

    .notification.info {
      background-color: rgba(52, 152, 219, 0.15);
      color: var(--info);
      border-left-color: var(--info);
    }

    .notification.danger {
      background-color: rgba(231, 76, 60, 0.15);
      color: var(--danger);
      border-left-color: var(--danger);
    }

    .notification h4 {
      margin-bottom: 8px;
      display: flex;
      align-items: center;
    }

    .notification h4 i {
      margin-right: 8px;
    }

    /* Form Styles */
    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
    }

    .form-control {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background: var(--card-bg);
      color: var(--text-color);
      transition: var(--transition);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(75, 108, 183, 0.2);
    }

    textarea.form-control {
      min-height: 100px;
      resize: vertical;
    }

    /* Toast Notification */
    .toast {
      position: fixed;
      top: 20px;
      right: 20px;
      color: white;
      padding: 15px 20px;
      border-radius: 8px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
      display: flex;
      align-items: center;
      z-index: 1000;
      opacity: 0;
      transform: translateX(100%);
      transition: all 0.3s ease;
    }
    
    .toast.success {
      background: var(--success);
    }
    
    .toast.error {
      background: var(--danger);
    }
    
    .toast.info {
      background: var(--info);
    }
    
    .toast.warning {
      background: var(--warning);
    }

    .toast.show {
      opacity: 1;
      transform: translateX(0);
    }

    .toast i {
      margin-right: 10px;
      font-size: 1.2rem;
    }

    /* Responsive Design */
    @media (max-width: 992px) {
      .app-container {
        flex-direction: column;
      }

      .sidebar {
        width: 100%;
        height: auto;
      }

      .nav-menu {
        display: flex;
        overflow-x: auto;
      }

      .nav-item {
        border-left: none;
        border-bottom: 3px solid transparent;
        white-space: nowrap;
      }

      .nav-item.active {
        border-left: none;
        border-bottom-color: white;
      }
    }

    @media (max-width: 768px) {
      .dashboard-grid {
        grid-template-columns: 1fr;
      }

      .pointage-actions {
        flex-direction: column;
      }

      .btn {
        width: 100%;
      }
    }
    /* Styles supplémentaires pour les ordres de mission */
.message-preview {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.table-container table td {
    vertical-align: top;
}

/* Styles pour la section avances */
.message-preview {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.form-text {
    font-size: 0.8rem;
    color: var(--gray);
    margin-top: 5px;
}
/* Ajouter dans la section CSS existante */
.message-preview {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.table-container table td {
    vertical-align: top;
}

/* Style pour les statistiques */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.stat-card {
    background: var(--card-bg);
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    border-left: 4px solid var(--primary);
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--gray);
}
/* Ajouter ces styles dans la section <style> existante */

.notes-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-top: 20px;
}

.note-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.note-card.note-non-lue {
    border-left: 4px solid var(--success);
    background: linear-gradient(135deg, var(--card-bg) 0%, rgba(46, 204, 113, 0.05) 100%);
}

.note-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.note-title-section {
    flex: 1;
}

.note-title {
    margin: 0 0 10px 0;
    color: var(--primary);
    font-size: 1.3em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.nouveau-badge {
    background: var(--success);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7em;
    font-weight: bold;
}

.note-meta {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.destinataires-badge, .note-date {
    background: var(--primary);
    color: white;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.8em;
}

.note-date {
    background: var(--secondary);
}

.note-actions {
    flex-shrink: 0;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.8rem;
}

.lu-badge {
    color: var(--success);
    font-size: 0.9em;
    display: flex;
    align-items: center;
    gap: 5px;
}

.note-content {
    margin-bottom: 15px;
}

.note-auteur {
    background: rgba(75, 108, 183, 0.1);
    padding: 10px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    color: var(--primary);
    font-size: 0.9em;
    display: flex;
    align-items: center;
    gap: 8px;
}

.note-contenu-text {
    line-height: 1.6;
    font-size: 1em;
    white-space: pre-wrap;
    padding: 10px 0;
}

.note-footer {
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
    font-size: 0.8em;
    color: var(--gray);
    font-style: italic;
    display: flex;
    align-items: center;
    gap: 8px;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--gray);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 20px;
    color: #ddd;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: var(--text-color);
}

/* Responsive */
@media (max-width: 768px) {
    .note-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .note-meta {
        flex-direction: column;
        gap: 5px;
    }
    
    .note-title {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
/* Ajouter ces styles dans la section CSS existante */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 10px;
    margin-bottom: 20px;
}

.stat-card {
    background: var(--card-bg);
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    border-left: 4px solid var(--primary);
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 8px;
    color: var(--primary);
}

.stat-label {
    font-size: 0.9rem;
    color: var(--gray);
    font-weight: 500;
}

/* Styles pour les indicateurs de statut améliorés */
.status-indicator {
    display: inline-flex;
    align-items: center;
    padding: 10px 20px;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 600;
    transition: var(--transition);
}

.status-present {
    background-color: var(--success);
    color: white;
    box-shadow: 0 2px 8px rgba(46, 204, 113, 0.3);
}

.status-absent {
    background-color: var(--danger);
    color: white;
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
}

.status-waiting {
    background-color: var(--warning);
    color: white;
    box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
}

.status-indicator i {
    margin-right: 8px;
    font-size: 1rem;
}
  </style>
</head>
<body class="light-mode">
<div class="app-container">
  <!-- Sidebar Navigation -->
  <div class="sidebar">
    <div class="sidebar-header">
      <h1>PointagePro</h1>
      <div class="user-info">
        <div class="user-avatar"><?= strtoupper(substr($name, 0, 1)) ?></div>
        <div class="user-details">
          <div class="user-name"><?= htmlspecialchars($name) ?></div>
          <div class="user-role">Employé</div>
        </div>
      </div>
    </div>

    <div class="nav-menu">
      <div class="nav-item active" data-target="pointage-section">
        <i class="fas fa-fingerprint"></i>
        <span>Pointage</span>
      </div>
      <div class="nav-item" data-target="historique-section">
        <i class="fas fa-history"></i>
        <span>Historique</span>
      </div>
      <div class="nav-item" data-target="conges-section">
        <i class="fas fa-umbrella-beach"></i>
        <span>Congés</span>
      </div>
      <div class="nav-item" data-target="autorisation-section">
        <i class="fas fa-clipboard-check"></i>
        <span>Autorisation</span>
      </div>
      <div class="nav-item" data-target="notifications-section">
        <i class="fas fa-bell"></i>
        <span>Notifications</span>
        <?php if (!empty($notifications)): ?>
          <span style="background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;"><?= count($notifications) ?></span>
        <?php endif; ?>
      </div>
      <!-- Dans la section de navigation de la sidebar, ajouter ceci après le menu Notifications -->
<div class="nav-item" data-target="discussion-section">
    <i class="fas fa-comments"></i>
    <span>Discussion</span>
</div>
<div class="nav-item" data-target="attestations-section">
    <i class="fas fa-file-contract"></i>
    <span>Attestations</span>
</div>
    </div>
    <div class="nav-item" data-target="ordres-mission-section">
    <i class="fas fa-plane"></i>
    <span>Ordres de Mission</span>
</div>

<!-- Dans la section de navigation de la sidebar, ajouter après Ordres de Mission -->
<div class="nav-item" data-target="avances-section">
    <i class="fas fa-hand-holding-usd"></i>
    <span>Avances de salaire</span>
</div>
<!-- Dans la sidebar, après la section Avances de salaire -->
<div class="nav-item" data-target="credits-section">
    <i class="fas fa-hand-holding-usd"></i>
    <span>Demande de crédit</span>
</div>
<!-- Dans la sidebar, ajouter cette option après Avances de salaire -->
<div class="nav-item" data-target="notes-service-section">
    <i class="fas fa-bullhorn"></i>
    <span>Notes de service</span>
    <?php if ($notes_non_lues > 0): ?>
        <span style="background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;"><?= $notes_non_lues ?></span>
    <?php endif; ?>
</div>

    <div class="sidebar-footer">
      <button class="theme-toggle" id="theme-toggle">
        <i class="fas fa-moon"></i>
      </button>
      <a href="logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Déconnexion</span>
      </a>
    </div>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="top-header">
      <h2 class="page-title" id="page-title">Pointage</h2>
      <div class="header-actions">
        <?php if ($dernier_pointage): ?>
          <div class="status-indicator <?= ($dernier_pointage['type'] === 'entrée' && (new DateTime($dernier_pointage['timestamp']))->format('Y-m-d') === (new DateTime())->format('Y-m-d')) ? 'status-present' : 'status-absent' ?>">
            <?= ($dernier_pointage['type'] === 'entrée' && (new DateTime($dernier_pointage['timestamp']))->format('Y-m-d') === (new DateTime())->format('Y-m-d')) ? 'En service' : 'Hors service' ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="content-area">
      <!-- Pointage Section -->
      <section id="pointage-section" class="content-section">
        <div class="dashboard-grid">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Pointage du jour</h3>
            </div>
            <div class="card-body">
              <form method="post" action="pointer.php" class="pointage-actions">
                <button type="submit" name="type" value="entrée" class="btn btn-primary">
                  <i class="fas fa-sign-in-alt"></i>Entrée
                </button>
                <button type="submit" name="type" value="sortie" class="btn btn-success">
                  <i class="fas fa-sign-out-alt"></i>Sortie
                </button>
              </form>
              
              <?php if ($dernier_pointage): ?>
                <p>Dernier pointage: <strong><?= $dernier_pointage['type'] ?></strong> à <strong><?= (new DateTime($dernier_pointage['timestamp']))->format('H:i') ?></strong></p>
              <?php else: ?>
                <p>Aucun pointage enregistré aujourd'hui.</p>
              <?php endif; ?>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Statistiques</h3>
            </div>
            <div class="card-body">
              <p>Heures supplémentaires cette semaine: <strong><?= $weekly_extra ?? '00:00:00' ?></strong></p>
              <p>Nombre de pointages ce mois: <strong><?= count($pointages) ?></strong></p>
            </div>
          </div>
        </div>

        <?php if (!empty($messages_contextuels)): ?>
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Notifications</h3>
          </div>
          <div class="card-body">
            <?php foreach ($messages_contextuels as $notification): ?>
            <div class="notification <?= $notification['type'] ?>">
              <h4><i class="fas fa-info-circle"></i><?= $notification['titre'] ?></h4>
              <p><?= $notification['message'] ?></p>
              <p><em><?= $notification['conseil'] ?></em></p>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </section>
      <!-- Après la section Notifications, ajouter cette nouvelle section -->
<section id="discussion-section" class="content-section" style="display: none;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Messagerie</h3>
        </div>
        <div class="card-body">
            <div id="chat-container" style="height: 400px; overflow-y: auto; margin-bottom: 20px; border: 1px solid var(--border-color); border-radius: 8px; padding: 15px;">
                <!-- Les messages seront chargés ici via JavaScript -->
                <div id="chat-messages"></div>
            </div>
            
            <form id="message-form">
                <div class="form-group">
                    <textarea class="form-control" id="message-text" rows="3" placeholder="Tapez votre message ici..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Envoyer
                </button>
            </form>
        </div>
    </div>
</section>
<!-- Attestations Section -->
<section id="attestations-section" class="content-section" style="display: none;">
    <!-- Attestation de travail -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Demande d'attestation de travail</h3>
        </div>
        <div class="card-body">
            <?php if ($limite_atteinte_travail): ?>
                <div class="notification warning">
                    <h4><i class="fas fa-exclamation-triangle"></i>Limite atteinte</h4>
                    <p>Vous avez déjà demandé 5 attestations de travail cette année (<?= $current_year ?>).</p>
                    <p><em>Vous ne pouvez pas faire de nouvelle demande cette année.</em></p>
                </div>
            <?php else: ?>
                <p>Il vous reste <strong><?= 5 - $count_at ?></strong> demandes d'attestation de travail pour <?= $current_year ?>.</p>
                <form method="post" action="demander_attestation.php">
                    <input type="hidden" name="type_attestation" value="travail">
                    <div class="form-group">
                        <label class="form-label">Année de référence</label>
                        <select class="form-control" name="annee" required>
                            <?php for ($i = $current_year; $i >= $current_year - 5; $i--): ?>
                                <option value="<?= $i ?>" <?= $i == $current_year ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Demander une attestation de travail</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Attestation de salaire -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Demande d'attestation de salaire</h3>
        </div>
        <div class="card-body">
            <?php if ($limite_atteinte_salaire): ?>
                <div class="notification warning">
                    <h4><i class="fas fa-exclamation-triangle"></i>Limite atteinte</h4>
                    <p>Vous avez déjà demandé 3 attestations de salaire cette année (<?= $current_year ?>).</p>
                    <p><em>Vous ne pouvez pas faire de nouvelle demande cette année.</em></p>
                </div>
            <?php else: ?>
                <p>Il vous reste <strong><?= 3 - $count_as ?></strong> demandes d'attestation de salaire pour <?= $current_year ?>.</p>
                <form method="post" action="demander_attestation.php">
                    <input type="hidden" name="type_attestation" value="salaire">
                    <div class="form-group">
                        <label class="form-label">Année de référence</label>
                        <select class="form-control" name="annee" required>
                            <?php for ($i = $current_year; $i >= $current_year - 5; $i--): ?>
                                <option value="<?= $i ?>" <?= $i == $current_year ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Demander une attestation de salaire</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Historique des demandes -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Mes demandes d'attestation</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Année</th>
                            <th>Date demande</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $all_attestations = array_merge(
                            array_map(function($item) { 
                                $item['type'] = 'Travail'; 
                                return $item; 
                            }, $attestations_travail),
                            array_map(function($item) { 
                                $item['type'] = 'Salaire'; 
                                return $item; 
                            }, $attestations_salaire)
                        );
                        
                        // Trier par date de demande décroissante
                        usort($all_attestations, function($a, $b) {
                            return strtotime($b['date_demande']) - strtotime($a['date_demande']);
                        });
                        
                        foreach ($all_attestations as $attestation): 
                            $status_class = '';
                            if ($attestation['statut'] == 'approuve') $status_class = 'status-present';
                            elseif ($attestation['statut'] == 'refuse') $status_class = 'status-absent';
                            else $status_class = 'status-waiting';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($attestation['type']) ?></td>
                                <td><?= htmlspecialchars($attestation['annee']) ?></td>
                                <td><?= htmlspecialchars($attestation['date_demande']) ?></td>
                                <td><span class="status-indicator <?= $status_class ?>"><?= htmlspecialchars($attestation['statut']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<!-- Section Crédits Salaire -->
<section id="credits-section" class="content-section" style="display: none;">
    <!-- Formulaire de demande de crédit -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Demande de crédit salaire</h3>
        </div>
        <div class="card-body">
            <?php if (!$anciennete_suffisante): ?>
                <div class="notification danger">
                    <h4><i class="fas fa-exclamation-triangle"></i>Ancienneté insuffisante</h4>
                    <p>Vous devez justifier d'une ancienneté minimale d'un an pour pouvoir demander un crédit.</p>
                    <p><em>Date d'embauche: <?= $date_embauche ? htmlspecialchars($date_embauche) : 'Non renseignée' ?></em></p>
                </div>
            <?php elseif ($credit_en_cours): ?>
                <div class="notification warning">
                    <h4><i class="fas fa-exclamation-triangle"></i>Crédit en cours</h4>
                    <p>Vous avez déjà un crédit en cours de remboursement.</p>
                    <p><em>Un nouveau crédit ne peut être accordé qu'après remboursement complet du crédit actuel.</em></p>
                </div>
            <?php elseif (!$peut_redemander): ?>
                <div class="notification warning">
                    <h4><i class="fas fa-exclamation-triangle"></i>Délai non respecté</h4>
                    <p>Un délai de 6 mois doit s'écouler après le remboursement complet d'un crédit avant de pouvoir en demander un nouveau.</p>
                </div>
            <?php else: ?>
                <div class="notification info">
                    <h4><i class="fas fa-info-circle"></i>Conditions du crédit</h4>
                    <ul>
                        <li>Montant maximum: <strong> (1 salaire mensuel)</li>
                        <li>Durée de remboursement: <strong>12 mois maximum</strong></li>
                        <li>Délai avant nouvelle demande: <strong>6 mois après remboursement complet</strong></li>
                        <li>Ancienneté requise: <strong>1 an minimum</strong> ✓</li>
                    </ul>
                </div>

                <form method="post" action="demander_credit.php" id="form-credit">
                    <div class="form-group">
                        <label class="form-label">Montant demandé (DT)</label>
                        <input type="number" class="form-control" name="montant" 
                               step="0.01" min="0" max="<?= $salaire_mensuel ?>" required 
                               placeholder="Ex: 1500.00" id="montant-credit">
                        <small class="form-text">Montant maximum: <?= number_format($salaire_mensuel, 2, ',', ' ') ?> DT</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nombre de mensualités</label>
                        <select class="form-control" name="nombre_mensualites" required id="nb-mensualites">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> mois</option>
                            <?php endfor; ?>
                        </select>
                        <small class="form-text">Durée maximum: 12 mois</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Mensualité estimée</label>
                        <input type="text" class="form-control" id="mensualite-estimee" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Motif de la demande</label>
                        <textarea class="form-control" name="motif" rows="4" required 
                                  placeholder="Veuillez détailler la raison de votre demande de crédit"></textarea>
                    </div>
                    
                    <div class="notification warning">
                        <h4><i class="fas fa-exclamation-triangle"></i>Engagement de remboursement</h4>
                        <p>En soumettant cette demande, je m'engage à rembourser le crédit selon les échéances convenues et j'accepte que les mensualités soient déduites de mon salaire.</p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Soumettre la demande de crédit</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Historique des demandes de crédit -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Mes demandes de crédit</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date demande</th>
                            <th>Montant</th>
                            <th>Mensualités</th>
                            <th>Mensualité</th>
                            <th>Motif</th>
                            <th>Statut</th>
                            <th>Solde restant</th>
                            <th>Prochaine échéance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($credits_salaire as $credit): 
                            $status_class = '';
                            if ($credit['statut'] == 'approuve') $status_class = 'status-present';
                            elseif ($credit['statut'] == 'refuse') $status_class = 'status-absent';
                            else $status_class = 'status-waiting';
                            
                            $mensualite = $credit['montant_mensualite'] ? number_format($credit['montant_mensualite'], 2, ',', ' ') . ' DT' : 'N/A';
                            $solde_restant = $credit['solde_restant'] ? number_format($credit['solde_restant'], 2, ',', ' ') . ' DT' : 'Remboursé';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($credit['date_demande']) ?></td>
                                <td><?= number_format($credit['montant'], 2, ',', ' ') ?> DT</td>
                                <td><?= htmlspecialchars($credit['nombre_mensualites']) ?> mois</td>
                                <td><?= $mensualite ?></td>
                                <td title="<?= htmlspecialchars($credit['motif']) ?>">
                                    <div class="message-preview">
                                        <?= strlen($credit['motif']) > 50 ? substr($credit['motif'], 0, 50) . '...' : htmlspecialchars($credit['motif']) ?>
                                    </div>
                                </td>
                                <td><span class="status-indicator <?= $status_class ?>"><?= htmlspecialchars($credit['statut']) ?></span></td>
                                <td><?= $solde_restant ?></td>
                                <td><?= $credit['prochaine_echeance'] ? htmlspecialchars($credit['prochaine_echeance']) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($credits_salaire)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">Aucune demande de crédit</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<!-- Ordres de Mission Section -->
<section id="ordres-mission-section" class="content-section" style="display: none;">
    <!-- Formulaire de demande d'ordre de mission -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Demander un ordre de mission</h3>
        </div>
        <div class="card-body">
            <form method="post" action="demander_ordre_mission.php">
                <div class="form-group">
                    <label class="form-label">Date de la mission</label>
                    <input type="date" class="form-control" name="date_mission" required 
                           min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Heure de départ</label>
                    <input type="time" class="form-control" name="heure_depart" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Heure d'arrivée prévue</label>
                    <input type="time" class="form-control" name="heure_arrivee" required 
                           onchange="calculerDureeMission()">
                </div>
                <div class="form-group">
                    <label class="form-label">Durée estimée</label>
                    <input type="text" class="form-control" id="duree_mission" readonly 
                           placeholder="La durée sera calculée automatiquement">
                </div>
                <div class="form-group">
                    <label class="form-label">Destination</label>
                    <input type="text" class="form-control" name="destination" required 
                           placeholder="Ville, pays ou lieu de la mission">
                </div>
                <div class="form-group">
                    <label class="form-label">Objet de la mission</label>
                    <textarea class="form-control" name="objet_mission" rows="4" required 
                              placeholder="Décrivez l'objectif et les activités prévues"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Moyen de transport</label>
                    <select class="form-control" name="moyens_transport">
                        <option value="">Sélectionnez...</option>
                        <option value="avion">Avion</option>
                        <option value="train">Train</option>
                        <option value="voiture">Voiture fonction</option>
                        <option value="transport_public">Transport public</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Frais estimés (DT) <small class="text-muted">- Optionnel</small></label>
                    <input type="number" class="form-control" name="frais_estimes" 
                           step="0.01" min="0" placeholder="0.00">
                    <small class="form-text">Ce champ est optionnel. Vous pouvez laisser vide si non applicable.</small>
                </div>
                <button type="submit" class="btn btn-primary">Soumettre la demande</button>
            </form>
        </div>
    </div>

    <!-- Historique des ordres de mission -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Mes demandes d'ordre de mission</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date mission</th>
                            <th>Heures</th>
                            <th>Durée</th>
                            <th>Destination</th>
                            <th>Objet</th>
                            <th>Transport</th>
                            <th>Frais estimés</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordres_mission as $ordre): 
                            $status_class = '';
                            if ($ordre['statut'] == 'approuve') $status_class = 'status-present';
                            elseif ($ordre['statut'] == 'refuse') $status_class = 'status-absent';
                            else $status_class = 'status-waiting';
                            
                            // Calculer la durée en heures et minutes
                            $heure_depart = new DateTime($ordre['heure_depart']);
                            $heure_arrivee = new DateTime($ordre['heure_arrivee']);
                            $duree = $heure_depart->diff($heure_arrivee);
                            $duree_texte = $duree->h . 'h' . ($duree->i > 0 ? $duree->i . 'min' : '');
                            
                            // Afficher "N/A" si les frais sont vides ou 0
                            $frais_texte = 'N/A';
                            if (!empty($ordre['frais_estimes']) && $ordre['frais_estimes'] > 0) {
                                $frais_texte = number_format($ordre['frais_estimes'], 2, ',', ' ') . ' DT';
                            }
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($ordre['date_mission']) ?></td>
                                <td>
                                    <?= $heure_depart->format('H:i') ?> - <?= $heure_arrivee->format('H:i') ?>
                                </td>
                                <td><?= $duree_texte ?></td>
                                <td><?= htmlspecialchars($ordre['destination']) ?></td>
                                <td title="<?= htmlspecialchars($ordre['objet_mission']) ?>">
                                    <?= strlen($ordre['objet_mission']) > 50 ? substr($ordre['objet_mission'], 0, 50) . '...' : htmlspecialchars($ordre['objet_mission']) ?>
                                </td>
                                <td><?= htmlspecialchars($ordre['moyens_transport']) ?></td>
                                <td><?= $frais_texte ?></td>
                                <td><span class="status-indicator <?= $status_class ?>"><?= htmlspecialchars($ordre['statut']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<!-- Avances de Salaire Section -->
<section id="avances-section" class="content-section" style="display: none;">
    <!-- Formulaire de demande d'avance -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Demander une avance sur salaire</h3>
        </div>
        <div class="card-body">
            <?php if ($limite_avances_atteinte): ?>
                <div class="notification warning">
                    <h4><i class="fas fa-exclamation-triangle"></i>Limite de demandes atteinte</h4>
                    <p>Vous avez déjà effectué <?= $count_avances ?> demande(s) d'avance de salaire ce mois-ci (<?= $current_month ?>/<?= $current_year ?>).</p>
                    <p><em>Vous ne pouvez pas faire de nouvelle demande ce mois-ci.</em></p>
                </div>
            <?php else: ?>
                <p>Il vous reste <strong><?= 2 - $count_avances ?></strong> demande(s) d'avance de salaire possible(s) pour ce mois.</p>
                <form method="post" action="demander_avance.php">
                    <div class="form-group">
                        <label class="form-label">Montant demandé (DT)</label>
                        <input type="number" class="form-control" name="montant" 
                               step="0.01" min="0" max="1000" required 
                               placeholder="Ex: 500.00">
                        <small class="form-text">Montant maximum: 25% de salaire</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Motif de la demande</label>
                        <textarea class="form-control" name="motif" rows="4" required 
                                  placeholder="Veuillez détailler la raison de votre demande d'avance"></textarea>
                    </div>
                    <div class="notification info">
                        <h4><i class="fas fa-info-circle"></i>Informations importantes</h4>
                        <ul>
                            <li>Le montant de l'avance sera déduit de votre prochain salaire</li>
                            <li>Le traitement de la demande prend généralement 2 à 3 jours ouvrables</li>
                            <li>Vous recevrez une notification dès que votre demande sera traitée</li>
                        </ul>
                    </div>
                    <button type="submit" class="btn btn-primary">Soumettre la demande</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Historique des demandes d'avance -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Mes demandes d'avance de salaire</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date demande</th>
                            <th>Montant</th>
                            <th>Motif</th>
                            <th>Statut</th>
                            <th>Date traitement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avances_salaire as $avance): 
                            $status_class = '';
                            if ($avance['statut'] == 'approuve') $status_class = 'status-present';
                            elseif ($avance['statut'] == 'refuse') $status_class = 'status-absent';
                            else $status_class = 'status-waiting';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($avance['date_demande']) ?></td>
                                <td><?= number_format($avance['montant'], 2, ',', ' ') ?> DT</td>
                                <td title="<?= htmlspecialchars($avance['motif']) ?>">
                                    <div class="message-preview">
                                        <?= strlen($avance['motif']) > 50 ? substr($avance['motif'], 0, 50) . '...' : htmlspecialchars($avance['motif']) ?>
                                    </div>
                                </td>
                                <td><span class="status-indicator <?= $status_class ?>"><?= htmlspecialchars($avance['statut']) ?></span></td>
                                <td><?= $avance['date_traitement'] ? htmlspecialchars($avance['date_traitement']) : 'En attente' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($avances_salaire)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">Aucune demande d'avance de salaire</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<!-- Ajouter cette section après la section Avances de salaire -->
<!-- Section Notes de service -->
<section id="notes-service-section" class="content-section" style="display: none;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📋 Notes de service - Communications du PDG</h3>
        </div>
        <div class="card-body">
            <div class="notification info">
                <h4><i class="fas fa-info-circle"></i> Informations importantes</h4>
                <p>Cette section contient les communications officielles de la Direction Générale destinées à tous les employés.</p>
            </div>

            <?php if (count($notes_service) > 0): ?>
                <div class="notes-container">
                    <?php foreach ($notes_service as $note): ?>
                        <?php if ($note['est_destinataire']): ?>
                            <?php
                            // Vérifier si la note a été lue
                            $stmt_lu = $pdo->prepare("SELECT date_lecture FROM notes_service_lus WHERE note_id = ? AND user_id = ?");
                            $stmt_lu->execute([$note['id'], $_SESSION['user_id']]);
                            $deja_lu = $stmt_lu->fetch();
                            ?>
                            
                            <div class="note-card <?= !$deja_lu ? 'note-non-lue' : '' ?>" data-note-id="<?= $note['id'] ?>">
                                <div class="note-header">
                                    <div class="note-title-section">
                                        <h3 class="note-title">
                                            <?= htmlspecialchars($note['titre']) ?>
                                            <?php if (!$deja_lu): ?>
                                                <span class="nouveau-badge">Nouveau</span>
                                            <?php endif; ?>
                                        </h3>
                                        <div class="note-meta">
                                            <span class="destinataires-badge">
                                                <i class="fas fa-users"></i>
                                                <?= htmlspecialchars(ucfirst($note['destinataires'])) ?>
                                            </span>
                                            <span class="note-date">
                                                <i class="fas fa-clock"></i>
                                                <?= date('d/m/Y à H:i', strtotime($note['date_creation'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="note-actions">
                                        <?php if (!$deja_lu): ?>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="marquerCommeLu(<?= $note['id'] ?>)">
                                                <i class="fas fa-eye"></i> Marquer comme lu
                                            </button>
                                        <?php else: ?>
                                            <span class="lu-badge">
                                                <i class="fas fa-check-circle"></i> Lu le <?= date('d/m/Y', strtotime($deja_lu['date_lecture'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="note-content">
                                    <div class="note-auteur">
                                        <i class="fas fa-user-tie"></i>
                                        <strong>Émetteur :</strong> <?= htmlspecialchars($note['auteur_nom']) ?> (PDG)
                                    </div>
                                    <div class="note-contenu-text">
                                        <?= nl2br(htmlspecialchars($note['contenu'])) ?>
                                    </div>
                                </div>
                                
                                <?php if ($note['date_modification'] != $note['date_creation']): ?>
                                    <div class="note-footer">
                                        <i class="fas fa-edit"></i>
                                        <em>Dernière modification : <?= date('d/m/Y à H:i', strtotime($note['date_modification'])) ?></em>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bullhorn" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                    <h3>Aucune note de service</h3>
                    <p>Il n'y a actuellement aucune note de service destinée aux employés.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

      <!-- Historique Section -->
      <section id="historique-section" class="content-section" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Historique des pointages</h3>
          </div>
          <div class="card-body">
            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Heure</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pointages as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['type']) ?></td>
                    <td><?= (new DateTime($row['timestamp']))->format('d/m/Y') ?></td>
                    <td><?= (new DateTime($row['timestamp']))->format('H:i') ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

<!-- Congés Section -->
<section id="conges-section" class="content-section" style="display: none;">
    <!-- Carte du solde des congés -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Mon solde de congés</h3>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($solde_conges['mois_courant']['solde_restant'], 1) ?> j</div>
                    <div class="stat-label">Solde ce mois (<?= date('m/Y') ?>)</div>
                </div>
            </div>
            
            <!-- Affichage du statut du solde -->
            <div style="margin-top: 20px; text-align: center;">
                <?php if ($solde_conges['mois_courant']['solde_restant'] > 0): ?>
                    <div class="status-indicator status-present" style="display: inline-flex; margin: 10px 0;">
                        <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                        Solde disponible
                    </div>
                <?php else: ?>
                    <div class="status-indicator status-absent" style="display: inline-flex; margin: 10px 0;">
                        <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
                        Solde épuisé
                    </div>
                <?php endif; ?>
                
                <!-- Message d'information sur le solde -->
                <div style="margin-top: 15px; padding: 12px; background: var(--bg-color); border-radius: 8px; border-left: 4px solid var(--info);">
                    <p style="margin: 0; font-size: 0.9rem;">
                        <i class="fas fa-info-circle" style="color: var(--info); margin-right: 8px;"></i>
                        <?php if ($solde_conges['mois_courant']['solde_restant'] > 0): ?>
                            Il vous reste <strong><?= number_format($solde_conges['mois_courant']['solde_restant'], 1) ?> jour(s)</strong> de congé avec solde pour ce mois.
                        <?php else: ?>
                            Votre solde de congés avec solde pour ce mois est épuisé. Les congés supplémentaires seront sans solde.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <div class="notification info" style="margin-top: 20px;">
                <h4><i class="fas fa-info-circle"></i> Système de congés</h4>
                <p>Vous accumulez <strong>1.5 jour de congé avec solde par mois</strong>. Les jours non utilisés sont reportés aux mois suivants.</p>
            </div>
        </div>
    </div>

    <!-- Le reste du code pour le formulaire de demande de congé reste inchangé -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Demander un congé</h3>
        </div>
        <div class="card-body">
            <form method="post" action="demander_conge.php" id="form-conge">
                <div class="form-group">
                    <label class="form-label">Date de début</label>
                    <input type="date" class="form-control" name="date_debut" required 
                           min="<?= date('Y-m-d') ?>" onchange="calculerDureeEtCout()">
                </div>
                <div class="form-group">
                    <label class="form-label">Date de fin</label>
                    <input type="date" class="form-control" name="date_fin" required 
                           min="<?= date('Y-m-d') ?>" onchange="calculerDureeEtCout()">
                </div>
                <div class="form-group">
                    <label class="form-label">Durée</label>
                    <input type="text" class="form-control" id="duree_conge" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Coût en jours</label>
                    <input type="text" class="form-control" id="cout_conge" readonly 
                           style="font-weight: bold;">
                </div>
                <div class="form-group">
                    <label class="form-label">Type de congé</label>
                    <select class="form-control" name="type_conge" required>
                        <option value="annuel">Congé annuel</option>
                        <option value="maternite">Congé maternité</option>
                        <option value="paternite">Congé paternité</option>
                        <option value="exceptionnel">Congé exceptionnel</option>
                        <option value="maladie">Congé maladie</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Motif</label>
                    <textarea class="form-control" name="cause" rows="3" required 
                              placeholder="Décrivez la raison de votre congé"></textarea>
                </div>
                
                <!-- Affichage du statut de disponibilité du solde pour la demande -->
                <div style="margin-bottom: 20px; padding: 15px; border-radius: 8px; background: <?= $solde_conges['mois_courant']['solde_restant'] > 0 ? 'rgba(46, 204, 113, 0.1)' : 'rgba(231, 76, 60, 0.1)' ?>; border-left: 4px solid <?= $solde_conges['mois_courant']['solde_restant'] > 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                        <?php if ($solde_conges['mois_courant']['solde_restant'] > 0): ?>
                            <i class="fas fa-check-circle" style="color: var(--success); margin-right: 10px; font-size: 1.2rem;"></i>
                            <strong style="color: var(--success);">Solde disponible</strong>
                        <?php else: ?>
                            <i class="fas fa-exclamation-triangle" style="color: var(--danger); margin-right: 10px; font-size: 1.2rem;"></i>
                            <strong style="color: var(--danger);">Solde épuisé</strong>
                        <?php endif; ?>
                    </div>
                    <p style="margin: 0; font-size: 0.9rem; color: var(--text-color);">
                        <?php if ($solde_conges['mois_courant']['solde_restant'] > 0): ?>
                            Vous pouvez utiliser vos <strong><?= number_format($solde_conges['mois_courant']['solde_restant'], 1) ?> jour(s)</strong> de congé avec solde.
                        <?php else: ?>
                            Les congés demandés seront sans solde jusqu'à l'accumulation de nouveaux jours.
                        <?php endif; ?>
                    </p>
                </div>
                
                <button type="submit" class="btn btn-primary">Soumettre la demande</button>
            </form>
        </div>
    </div>


    <!-- Dans la section congés, après le formulaire de demande -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Historique de mes congés</h3>
    </div>
    <div class="card-body">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Période</th>
                        <th>Durée</th>
                        <th>Type</th>
                        <th>Motif</th>
                        <th>Avec solde</th>
                        <th>Statut</th>
                        <th>Date demande</th>
                        <th>Date traitement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Récupérer tous les congés de l'utilisateur
                    $stmt_all_conges = $pdo->prepare("SELECT * FROM conges WHERE user_id = ? ORDER BY date_demande DESC");
                    $stmt_all_conges->execute([$user_id]);
                    $all_conges = $stmt_all_conges->fetchAll();
                    
                    foreach ($all_conges as $conge): 
                        $date_debut = new DateTime($conge['date_debut']);
                        $date_fin = new DateTime($conge['date_fin']);
                        $duree = $date_debut->diff($date_fin)->days + 1;
                        
                        $status_class = '';
                        if ($conge['statut'] == 'approuve') {
                            $status_class = 'status-present';
                            $status_text = 'Approuvé';
                        } elseif ($conge['statut'] == 'refuse') {
                            $status_class = 'status-absent';
                            $status_text = 'Refusé';
                        } else {
                            $status_class = 'status-waiting';
                            $status_text = 'En attente';
                        }
                        
                        $solde_text = $conge['avec_solde'] ? 'Oui' : 'Non';
                        $solde_class = $conge['avec_solde'] ? 'status-present' : 'status-waiting';
                    ?>
                        <tr>
                            <td>
                                <?= $date_debut->format('d/m/Y') ?> -<br>
                                <?= $date_fin->format('d/m/Y') ?>
                            </td>
                            <td><?= $duree ?> jour(s)</td>
                            <td>
                                <?php 
                                $types_conges = [
                                    'annuel' => 'Congé annuel',
                                    'maternite' => 'Congé maternité',
                                    'paternite' => 'Congé paternité',
                                    'exceptionnel' => 'Congé exceptionnel'
                                ];
                                echo htmlspecialchars($types_conges[$conge['type_conge']] ?? $conge['type_conge']);
                                ?>
                            </td>
                            <td title="<?= htmlspecialchars($conge['cause']) ?>">
                                <div class="message-preview">
                                    <?= strlen($conge['cause']) > 50 ? substr($conge['cause'], 0, 50) . '...' : htmlspecialchars($conge['cause']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-indicator <?= $solde_class ?>">
                                    <?= $solde_text ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-indicator <?= $status_class ?>">
                                    <?= $status_text ?>
                                </span>
                            </td>
                            <td><?= (new DateTime($conge['date_demande']))->format('d/m/Y H:i') ?></td>
                            <td>
                                <?php 
                                if ($conge['date_traitement']) {
                                    echo (new DateTime($conge['date_traitement']))->format('d/m/Y H:i');
                                } else {
                                    echo '<em>En attente</em>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($all_conges)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">
                                <p>Aucune demande de congé enregistrée.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Statistiques des congés -->
        <?php
        // Calculer les statistiques
        $stmt_stats = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN statut = 'approuve' THEN 1 ELSE 0 END) as approuves,
                SUM(CASE WHEN statut = 'refuse' THEN 1 ELSE 0 END) as refuses,
                SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente
            FROM conges 
            WHERE user_id = ?
        ");
        $stmt_stats->execute([$user_id]);
        $stats = $stmt_stats->fetch();
        ?>
        
        <div style="margin-top: 20px; padding: 15px; background: var(--bg-color); border-radius: 8px;">
            <h4 style="margin-bottom: 10px;">Statistiques des congés</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div style="text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary);"><?= $stats['total'] ?></div>
                    <div style="font-size: 0.9rem;">Total des demandes</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--success);"><?= $stats['approuves'] ?></div>
                    <div style="font-size: 0.9rem;">Congés approuvés</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--danger);"><?= $stats['refuses'] ?></div>
                    <div style="font-size: 0.9rem;">Congés refusés</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--warning);"><?= $stats['en_attente'] ?></div>
                    <div style="font-size: 0.9rem;">En attente</div>
                </div>
            </div>
        </div>
    </div>
</div>
</section>

      <!-- Autorisation Section -->
      <section id="autorisation-section" class="content-section" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Demande d'autorisation de sortie</h3>
          </div>
          <div class="card-body">
            <form method="post" action="demander_autorisation.php">
              <div class="form-group">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="date" value="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Heure de sortie</label>
                <input type="time" class="form-control" name="heure_sortie" required>
              </div>
              <div class="form-group">
                <label class="form-label">Heure de retour prévue</label>
                <input type="time" class="form-control" name="heure_retour" required>
              </div>
              <div class="form-group">
                <label class="form-label">Motif</label>
                <textarea class="form-control" name="motif" rows="3" required placeholder="Décrivez la raison de votre sortie exceptionnelle"></textarea>
              </div>
              <button type="submit" class="btn btn-primary">Soumettre la demande</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Mes demandes d'autorisation</h3>
          </div>
          <div class="card-body">
            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Heure sortie</th>
                    <th>Heure retour</th>
                    <th>Motif</th>
                    <th>Statut</th>
                    <th>Date demande</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($autorisations as $autorisation): 
                    $status_class = '';
                    if ($autorisation['statut'] == 'approuve') $status_class = 'status-present';
                    elseif ($autorisation['statut'] == 'refuse') $status_class = 'status-absent';
                    else $status_class = 'status-waiting';
                  ?>
                    <tr>
                      <td><?= htmlspecialchars($autorisation['date']) ?></td>
                      <td><?= htmlspecialchars($autorisation['heure_sortie']) ?></td>
                      <td><?= htmlspecialchars($autorisation['heure_retour']) ?></td>
                      <td><?= htmlspecialchars($autorisation['motif']) ?></td>
                      <td><span class="status-indicator <?= $status_class ?>"><?= htmlspecialchars($autorisation['statut']) ?></span></td>
                      <td><?= htmlspecialchars($autorisation['date_demande']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- Notifications Section -->
      <section id="notifications-section" class="content-section" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Mes notifications</h3>
          </div>
          <div class="card-body">
            <?php if (!empty($notifications)): ?>
              <?php foreach ($notifications as $notif): 
                $class_type = 'info';
                if (strpos($notif['message'], 'approuve') !== false) $class_type = 'success';
                elseif (strpos($notif['message'], 'refuse') !== false) $class_type = 'danger';
              ?>
                <div class="notification <?= $class_type ?>">
                  <h4><i class="fas fa-bell"></i>Notification</h4>
                  <p><?= htmlspecialchars($notif['message']) ?></p>
                  <small><?= htmlspecialchars($notif['date_creation']) ?></small>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p>Aucune notification.</p>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>

<div class="toast success" id="toast">
  <i class="fas fa-check-circle"></i>
  <span id="toast-message"></span>
</div>

<script>
  // Gestion du thème
  const themeToggle = document.getElementById('theme-toggle');
  const body = document.body;
  
  // Vérifier la préférence utilisateur
  if (localStorage.getItem('theme') === 'dark') {
    body.classList.replace('light-mode', 'dark-mode');
    themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
  }
  
  themeToggle.addEventListener('click', () => {
    if (body.classList.contains('light-mode')) {
      body.classList.replace('light-mode', 'dark-mode');
      localStorage.setItem('theme', 'dark');
      themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
    } else {
      body.classList.replace('dark-mode', 'light-mode');
      localStorage.setItem('theme', 'light');
      themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
    }
  });
  
  // Navigation entre les sections
  const navItems = document.querySelectorAll('.nav-item');
  const contentSections = document.querySelectorAll('.content-section');
  const pageTitle = document.getElementById('page-title');
  
  navItems.forEach(item => {
    item.addEventListener('click', () => {
      const target = item.getAttribute('data-target');
      
      // Mettre à jour la navigation
      navItems.forEach(nav => nav.classList.remove('active'));
      item.classList.add('active');
      
      // Afficher la section cible
      contentSections.forEach(section => {
        section.style.display = 'none';
      });
      document.getElementById(target).style.display = 'block';
      
      // Mettre à jour le titre de la page
      pageTitle.textContent = item.querySelector('span').textContent;
    });
  });
  
  // Toast notification
  <?php if (!empty($_SESSION['message'])): ?>
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    
    toastMessage.textContent = <?= json_encode($_SESSION['message']) ?>;
    toast.classList.add('show');
    
    // Masquer le toast après 5 secondes
    setTimeout(() => {
      toast.classList.remove('show');
    }, 5000);
    
    // Supprimer le message de la session après affichage
    <?php unset($_SESSION['message']); ?>
  <?php endif; ?>
  
  // Fonction pour afficher des toasts depuis n'importe où
  function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    
    // Changer le type de toast
    toast.className = 'toast ' + type;
    
    // Changer l'icône selon le type
    let icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';
    if (type === 'info') icon = 'fa-info-circle';
    if (type === 'warning') icon = 'fa-exclamation-triangle';
    
    toast.innerHTML = `<i class="fas ${icon}"></i> <span id="toast-message">${message}</span>`;
    toast.classList.add('show');
    
    // Masquer le toast après 5 secondes
    setTimeout(() => {
      toast.classList.remove('show');
    }, 5000);
  }
  
  // Validation du formulaire de congé
  document.addEventListener('DOMContentLoaded', function() {
    const congeForm = document.querySelector('form[action="demander_conge.php"]');
    
    if (congeForm) {
      congeForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validation basique des dates
        const dateDebut = new Date(this.elements['date_debut'].value);
        const dateFin = new Date(this.elements['date_fin'].value);
        
        if (dateFin < dateDebut) {
          showToast('La date de fin doit être postérieure à la date de début.', 'error');
          return;
        }
        
        // Confirmation avant envoi
        if (confirm('Êtes-vous sûr de vouloir soumettre cette demande de congé ?')) {
          this.submit();
        }
      });
    }
    
    // Validation du formulaire d'autorisation
    const autorisationForm = document.querySelector('form[action="demander_autorisation.php"]');
    
    if (autorisationForm) {
      autorisationForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validation des heures
        const heureSortie = this.elements['heure_sortie'].value;
        const heureRetour = this.elements['heure_retour'].value;
        
        if (heureRetour <= heureSortie) {
          showToast('L\'heure de retour doit être après l\'heure de sortie.', 'error');
          return;
        }
        
        // Confirmation avant envoi
        if (confirm('Êtes-vous sûr de vouloir soumettre cette demande d\'autorisation ?')) {
          this.submit();
        }
      });
    }
  });
  // Gestion de la messagerie
const messageForm = document.getElementById('message-form');
const messageText = document.getElementById('message-text');
const chatMessages = document.getElementById('chat-messages');
const chatContainer = document.getElementById('chat-container');

// Charger les messages
function loadMessages() {
    fetch('get_messages.php')
        .then(response => response.json())
        .then(messages => {
            chatMessages.innerHTML = '';
            messages.forEach(message => {
                const messageElement = document.createElement('div');
                messageElement.classList.add('message');
                
                // Style différent selon l'expéditeur
                if (message.sender_id == <?= $user_id ?>) {
                    messageElement.style.textAlign = 'right';
                    messageElement.innerHTML = `
                        <div style="background: var(--primary); color: white; padding: 8px 12px; border-radius: 12px; display: inline-block; margin-bottom: 8px; max-width: 70%;">
                            ${message.message}
                            <div style="font-size: 0.7rem; opacity: 0.8; margin-top: 4px;">${message.timestamp}</div>
                        </div>
                    `;
                } else {
                    messageElement.style.textAlign = 'left';
                    messageElement.innerHTML = `
                        <div style="background: var(--bg-color); padding: 8px 12px; border-radius: 12px; display: inline-block; margin-bottom: 8px; max-width: 70%;">
                            <strong>${message.sender_name}</strong><br>
                            ${message.message}
                            <div style="font-size: 0.7rem; opacity: 0.8; margin-top: 4px;">${message.timestamp}</div>
                        </div>
                    `;
                }
                
                chatMessages.appendChild(messageElement);
            });
            
            // Scroll to bottom
            chatContainer.scrollTop = chatContainer.scrollHeight;
        })
        .catch(error => console.error('Erreur:', error));
}

// Envoyer un message
if (messageForm) {
    messageForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = messageText.value.trim();
        if (message) {
            fetch('send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `message=${encodeURIComponent(message)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageText.value = '';
                    loadMessages();
                } else {
                    showToast('Erreur lors de l\'envoi du message', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showToast('Erreur de connexion', 'error');
            });
        }
    });
}

// Charger les messages quand on ouvre la section discussion
document.querySelector('[data-target="discussion-section"]').addEventListener('click', function() {
    loadMessages();
    
    // Actualiser les messages toutes les 10 secondes
    if (window.chatInterval) {
        clearInterval(window.chatInterval);
    }
    window.chatInterval = setInterval(loadMessages, 10000);
});

// Arrêter l'actualisation quand on quitte la section
document.querySelectorAll('.nav-item').forEach(item => {
    if (item.getAttribute('data-target') !== 'discussion-section') {
        item.addEventListener('click', function() {
            if (window.chatInterval) {
                clearInterval(window.chatInterval);
            }
        });
    }
});
// Ajouter cette fonction dans employee_dashboard.php
function calculerDuree() {
    const dateDebut = new Date(document.querySelector('[name="date_debut"]').value);
    const dateFin = new Date(document.querySelector('[name="date_fin"]').value);
    
    if (dateDebut && dateFin && dateFin >= dateDebut) {
        const diffTime = Math.abs(dateFin - dateDebut);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        document.getElementById('duree_conge').value = diffDays + ' jour(s)';
    } else {
        document.getElementById('duree_conge').value = '';
    }
}
// Calculer la durée d'une mission
function calculerDureeMission() {
    const dateDebut = new Date(document.querySelector('[name="date_debut"]').value);
    const dateFin = new Date(document.querySelector('[name="date_fin"]').value);
    
    if (dateDebut && dateFin && dateFin >= dateDebut) {
        const diffTime = Math.abs(dateFin - dateDebut);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        document.getElementById('duree_mission').value = diffDays + ' jour(s)';
    } else {
        document.getElementById('duree_mission').value = '';
    }
}
// Validation du formulaire d'avance de salaire
const avanceForm = document.querySelector('form[action="demander_avance.php"]');

if (avanceForm) {
    avanceForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const montant = parseFloat(this.elements['montant'].value);
        const motif = this.elements['motif'].value.trim();
        
        if (montant <= 0 || montant > 700) {
            showToast('Le montant doit être compris entre 0 et 700 DT', 'error');
            return;
        }
        
        if (motif.length < 10) {
            showToast('Veuillez détailler davantage le motif de votre demande (au moins 10 caractères)', 'error');
            return;
        }
        
        if (confirm('Êtes-vous sûr de vouloir soumettre cette demande d\'avance de salaire ?')) {
            this.submit();
        }
    });
}
// Ajouter cette fonction pour gérer le marquage des notes comme lues
function marquerCommeLu(noteId) {
    fetch(`?section=notes-service&lire_note=${noteId}`)
        .then(response => {
            if (response.ok) {
                const noteCard = document.querySelector(`[data-note-id="${noteId}"]`);
                if (noteCard) {
                    noteCard.classList.remove('note-non-lue');
                    
                    // Supprimer le badge "Nouveau"
                    const nouveauBadge = noteCard.querySelector('.nouveau-badge');
                    if (nouveauBadge) {
                        nouveauBadge.remove();
                    }
                    
                    // Mettre à jour les actions
                    const noteActions = noteCard.querySelector('.note-actions');
                    noteActions.innerHTML = '<span class="lu-badge"><i class="fas fa-check-circle"></i> Lu à l\'instant</span>';
                    
                    // Mettre à jour le compteur dans le menu
                    const pendingCount = document.querySelector('.nav-item[data-target="notes-service-section"] span');
                    if (pendingCount) {
                        const currentCount = parseInt(pendingCount.textContent) - 1;
                        if (currentCount > 0) {
                            pendingCount.textContent = currentCount;
                        } else {
                            pendingCount.remove();
                        }
                    }
                    
                    showToast('Note marquée comme lue', 'success');
                }
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showToast('Erreur lors du marquage de la note', 'error');
        });
}

// Gestion de la navigation pour les notes de service
document.querySelector('[data-target="notes-service-section"]').addEventListener('click', function() {
    // Recharger les notes pour s'assurer qu'elles sont à jour
    const notesSection = document.getElementById('notes-service-section');
    if (notesSection) {
        // On pourrait ici ajouter un système de rafraîchissement automatique si nécessaire
    }
});
// Dans la fonction de navigation existante, ajouter la gestion de la section notes-service
navItems.forEach(item => {
    item.addEventListener('click', () => {
        const target = item.getAttribute('data-target');
        
        // Mettre à jour la navigation
        navItems.forEach(nav => nav.classList.remove('active'));
        item.classList.add('active');
        
        // Afficher la section cible
        contentSections.forEach(section => {
            section.style.display = 'none';
        });
        document.getElementById(target).style.display = 'block';
        
        // Mettre à jour le titre de la page
        pageTitle.textContent = item.querySelector('span').textContent;
        
        // Gestion spéciale pour la section notes de service
        if (target === 'notes-service-section') {
            // On pourrait ajouter du code spécifique ici si nécessaire
        }
    });
});
// Calcul de la mensualité estimée pour les crédits
function calculerMensualite() {
    const montant = parseFloat(document.getElementById('montant-credit').value) || 0;
    const nbMois = parseInt(document.getElementById('nb-mensualites').value) || 1;
    
    if (montant > 0 && nbMois > 0) {
        const mensualite = montant / nbMois;
        document.getElementById('mensualite-estimee').value = mensualite.toFixed(2) + ' DT/mois';
    } else {
        document.getElementById('mensualite-estimee').value = '';
    }
}

// Écouteurs d'événements pour le calcul automatique
document.addEventListener('DOMContentLoaded', function() {
    const montantInput = document.getElementById('montant-credit');
    const nbMensualites = document.getElementById('nb-mensualites');
    
    if (montantInput && nbMensualites) {
        montantInput.addEventListener('input', calculerMensualite);
        nbMensualites.addEventListener('change', calculerMensualite);
    }
    
    // Validation du formulaire de crédit
    const creditForm = document.getElementById('form-credit');
    if (creditForm) {
        creditForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const montant = parseFloat(this.elements['montant'].value);
            const nbMensualites = parseInt(this.elements['nombre_mensualites'].value);
            const motif = this.elements['motif'].value.trim();
            
            if (montant <= 0 || montant > <?= $salaire_mensuel ?: 0 ?>) {
                showToast('Le montant doit être compris entre 0 et ' + <?= $salaire_mensuel ?: 0 ?> + ' DT', 'error');
                return;
            }
            
            if (nbMensualites < 1 || nbMensualites > 12) {
                showToast('Le nombre de mensualités doit être compris entre 1 et 12', 'error');
                return;
            }
            
            if (motif.length < 10) {
                showToast('Veuillez détailler davantage le motif de votre demande (au moins 10 caractères)', 'error');
                return;
            }
            
            if (confirm('Êtes-vous sûr de vouloir soumettre cette demande de crédit ?\n\nMontant: ' + montant.toFixed(2) + ' DT\nMensualités: ' + nbMensualites + ' mois\nMensualité estimée: ' + (montant/nbMensualites).toFixed(2) + ' DT')) {
                this.submit();
            }
        });
    }
});
// Calculer la durée d'une mission en heures et minutes
function calculerDureeMission() {
    const heureDepart = document.querySelector('[name="heure_depart"]').value;
    const heureArrivee = document.querySelector('[name="heure_arrivee"]').value;
    
    if (heureDepart && heureArrivee && heureArrivee > heureDepart) {
        const [departHeures, departMinutes] = heureDepart.split(':').map(Number);
        const [arriveeHeures, arriveeMinutes] = heureArrivee.split(':').map(Number);
        
        let totalMinutesDepart = departHeures * 60 + departMinutes;
        let totalMinutesArrivee = arriveeHeures * 60 + arriveeMinutes;
        
        let differenceMinutes = totalMinutesArrivee - totalMinutesDepart;
        
        let heures = Math.floor(differenceMinutes / 60);
        let minutes = differenceMinutes % 60;
        
        let dureeTexte = '';
        if (heures > 0) {
            dureeTexte += heures + 'h';
        }
        if (minutes > 0) {
            dureeTexte += minutes + 'min';
        }
        
        document.getElementById('duree_mission').value = dureeTexte || '0min';
    } else {
        document.getElementById('duree_mission').value = '';
    }
}

// Validation du formulaire d'ordre de mission
const ordreMissionForm = document.querySelector('form[action="demander_ordre_mission.php"]');

if (ordreMissionForm) {
    ordreMissionForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const dateMission = this.elements['date_mission'].value;
        const heureDepart = this.elements['heure_depart'].value;
        const heureArrivee = this.elements['heure_arrivee'].value;
        const destination = this.elements['destination'].value.trim();
        const objetMission = this.elements['objet_mission'].value.trim();
        
        if (heureArrivee <= heureDepart) {
            showToast('L\'heure d\'arrivée doit être après l\'heure de départ.', 'error');
            return;
        }
        
        if (destination.length < 2) {
            showToast('Veuillez saisir une destination valide.', 'error');
            return;
        }
        
        if (objetMission.length < 10) {
            showToast('Veuillez détailler davantage l\'objet de la mission (au moins 10 caractères).', 'error');
            return;
        }
        
        if (confirm('Êtes-vous sûr de vouloir soumettre cette demande d\'ordre de mission ?')) {
            this.submit();
        }
    });
}

// Ajouter les écouteurs d'événements pour le calcul automatique
document.addEventListener('DOMContentLoaded', function() {
    const heureDepartInput = document.querySelector('[name="heure_depart"]');
    const heureArriveeInput = document.querySelector('[name="heure_arrivee"]');
    
    if (heureDepartInput && heureArriveeInput) {
        heureDepartInput.addEventListener('change', calculerDureeMission);
        heureArriveeInput.addEventListener('change', calculerDureeMission);
    }
});
// Calculer la durée et le coût en jours de congé
function calculerDureeEtCout() {
    const dateDebut = new Date(document.querySelector('[name="date_debut"]').value);
    const dateFin = new Date(document.querySelector('[name="date_fin"]').value);
    
    if (dateDebut && dateFin && dateFin >= dateDebut) {
        const diffTime = Math.abs(dateFin - dateDebut);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        
        document.getElementById('duree_conge').value = diffDays + ' jour(s)';
        
        // Calculer le coût en jours (avec estimation)
        const soldeCumule = <?= $solde_conges['cumule'] ?>;
        let coutTexte = '';
        
        if (soldeCumule >= diffDays) {
            coutTexte = diffDays + ' jour(s) avec solde';
            document.getElementById('cout_conge').style.color = 'var(--success)';
        } else if (soldeCumule > 0) {
            coutTexte = soldeCumule + ' jour(s) avec solde + ' + (diffDays - soldeCumule) + ' jour(s) sans solde';
            document.getElementById('cout_conge').style.color = 'var(--warning)';
        } else {
            coutTexte = diffDays + ' jour(s) sans solde';
            document.getElementById('cout_conge').style.color = 'var(--danger)';
        }
        
        document.getElementById('cout_conge').value = coutTexte;
    } else {
        document.getElementById('duree_conge').value = '';
        document.getElementById('cout_conge').value = '';
    }
}

// Validation du formulaire de congé
const congeForm = document.getElementById('form-conge');
if (congeForm) {
    congeForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const dateDebut = new Date(this.elements['date_debut'].value);
        const dateFin = new Date(this.elements['date_fin'].value);
        
        if (dateFin < dateDebut) {
            showToast('La date de fin doit être postérieure à la date de début.', 'error');
            return;
        }
        
        // Calculer la durée pour confirmation
        const diffTime = Math.abs(dateFin - dateDebut);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        const soldeCumule = <?= $solde_conges['cumule'] ?>;
        
        let messageConfirmation = `Êtes-vous sûr de vouloir soumettre cette demande de congé ?\n\n`;
        messageConfirmation += `Durée: ${diffDays} jour(s)\n`;
        
        if (soldeCumule >= diffDays) {
            messageConfirmation += `Coût: ${diffDays} jour(s) avec solde\n`;
            messageConfirmation += `Solde restant après: ${(soldeCumule - diffDays).toFixed(1)} jour(s)`;
        } else if (soldeCumule > 0) {
            messageConfirmation += `Coût: ${soldeCumule} jour(s) avec solde + ${(diffDays - soldeCumule)} jour(s) sans solde\n`;
            messageConfirmation += `Solde avec solde épuisé après cette demande`;
        } else {
            messageConfirmation += `Coût: ${diffDays} jour(s) sans solde`;
        }
        
        if (confirm(messageConfirmation)) {
            this.submit();
        }
    });
}
</script>
</body>
</html>