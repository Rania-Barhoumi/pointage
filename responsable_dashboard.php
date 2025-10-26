
<?php
session_start();
$pdo = new PDO("mysql:host=localhost;dbname=pointage", "root", "");

// Vérification si l'utilisateur est bien connecté comme responsable
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'responsable') {
    header("Location: login.php");
    exit();
}

// Initialisation des validations côté session
if (!isset($_SESSION['pointage_valide'])) {
    $_SESSION['pointage_valide'] = []; // tableau [id_pointage => "validé" ou "rejeté"]
}

// Gestion du pointage basique (entrée/sortie)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $user_id = $_SESSION['user_id'];
    $action  = $_POST['action']; // entrée ou sortie

    $stmt = $pdo->prepare("INSERT INTO pointages (user_id, type, timestamp) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $action]);
}

// Déterminer les matières à afficher selon le type de responsable
$matieres_a_afficher = [];

if ($_SESSION['matiere'] === 'Directeur technique') {
    $matieres_a_afficher = ['Directeur technique', 'Responsable production'];
} elseif ($_SESSION['matiere'] === 'Directeur qualité') {
    $matieres_a_afficher = ['Directeur qualité', 'Qualité système', 'Qualité chantier', 'Agent de contrôle qualité'];
} elseif ($_SESSION['matiere'] === 'Directeur achat et appro') {
    $matieres_a_afficher = ['Directeur achat et appro', 'Achat local', 'Ass achat', 'Chauffeur'];
} elseif ($_SESSION['matiere'] === 'Directeur Ressources Humaines') {
    $matieres_a_afficher = ['Directeur Ressources Humaines', 'Agent sécurité', 'Agents de nettoyage', 'Assistant RH'];
} elseif ($_SESSION['matiere'] === 'Directeur de production') {
    $matieres_a_afficher = ['Directeur de production', 'Agent de production'];
} elseif ($_SESSION['matiere'] === 'Directeur de système informatique') {
    $matieres_a_afficher = ['Directeur de système informatique', 'Technicien informatique'];
} else {
    // Par défaut, afficher seulement ses propres pointages
    $matieres_a_afficher = [$_SESSION['matiere']];
}

// Préparer les placeholders pour la requête SQL
$placeholders = implode(',', array_fill(0, count($matieres_a_afficher), '?'));

// Filtrage
$filters = $matieres_a_afficher; // On commence par les matières à afficher
$sql = "SELECT p.id, u.name, u.matiere, p.type, p.timestamp 
        FROM pointages p 
        JOIN users u ON u.id = p.user_id 
        WHERE u.matiere IN ($placeholders)";

if (!empty($_GET['employe'])) {
    $sql .= " AND u.name LIKE ?";
    $filters[] = "%" . $_GET['employe'] . "%";
}

if (!empty($_GET['date'])) {
    $sql .= " AND DATE(p.timestamp) = ?";
    $filters[] = $_GET['date'];
}

$sql .= " ORDER BY p.timestamp DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($filters);
$pointages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des demandes de congé pour les équipes du responsable
$sql_conges = "SELECT c.*, u.name as employe_nom, u.matiere
               FROM conges c 
               JOIN users u ON c.user_id = u.id 
               WHERE u.matiere IN ($placeholders) 
               ORDER BY c.date_demande DESC";

$stmt_conges = $pdo->prepare($sql_conges);
$stmt_conges->execute($matieres_a_afficher);
$conges = $stmt_conges->fetchAll(PDO::FETCH_ASSOC);

// Traitement des demandes de congé
// Dans la section de traitement des congés du responsable, ajouter cette vérification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_conge'])) {
    $conge_id = $_POST['conge_id'];
    $action = $_POST['action_conge'];
    
    // Vérifier si l'admin a déjà refusé ce congé
    $stmt_check = $pdo->prepare("SELECT statut FROM conges WHERE id = ?");
    $stmt_check->execute([$conge_id]);
    $current_status = $stmt_check->fetch()['statut'];
    
    // Si l'admin a refusé, empêcher le responsable d'accepter
    if ($current_status === 'refuse' && $action === 'approuver') {
        $_SESSION['message'] = "Impossible d'approuver ce congé - Refusé par l'administration";
        header("Location: responsable_dashboard.php?section=conges");
        exit;
    }
    
    // Continuer avec le traitement normal...
    $responsable_id = $_SESSION['user_id'];
    $nouveau_statut = $action === 'approuver' ? 'approuve' : 'refuse';
    
    // Mettre à jour le statut seulement si ce n'est pas déjà refusé par l'admin
    if ($current_status !== 'refuse') {
        $stmt = $pdo->prepare("UPDATE conges SET statut = ?, date_traitement = NOW(), traite_par = ? WHERE id = ?");
        $stmt->execute([$nouveau_statut, $responsable_id, $conge_id]);
        // ... reste du code de notification
    }
}
// Dans la section de traitement des congés du responsable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_conge'])) {
    $conge_id = $_POST['conge_id'];
    $action = $_POST['action_conge'];
    $responsable_id = $_SESSION['user_id'];
    
    // Vérifier si l'admin a déjà pris une décision FINALE
    $stmt_check = $pdo->prepare("
        SELECT c.*, u.name as traite_par_name 
        FROM conges c 
        LEFT JOIN users u ON c.traite_par = u.id 
        WHERE c.id = ?
    ");
    $stmt_check->execute([$conge_id]);
    $conge_info = $stmt_check->fetch();
    
    // Si déjà traité par un admin, empêcher toute modification
    if ($conge_info['traite_par_name'] && $conge_info['traite_par'] != $responsable_id) {
        // Vérifier si le traite_par est un admin
        $stmt_check_admin = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt_check_admin->execute([$conge_info['traite_par']]);
        $traite_par_role = $stmt_check_admin->fetch()['role'];
        
        if ($traite_par_role === 'admin') {
            $_SESSION['message'] = "DÉCISION ADMIN IRREVERSIBLE - Impossible de modifier la décision pour ce congé";
            header("Location: responsable_dashboard.php?section=conges");
            exit;
        }
    }
    
    // Si pas de décision admin, le responsable peut traiter normalement
    $nouveau_statut = $action === 'approuver' ? 'approuve' : 'refuse';
    $stmt = $pdo->prepare("UPDATE conges SET statut = ?, date_traitement = NOW(), traite_par = ? WHERE id = ?");
    $stmt->execute([$nouveau_statut, $responsable_id, $conge_id]);
    
    // Notification à l'employé
    $message = "Votre demande de congé du {$conge_info['date_debut']} au {$conge_info['date_fin']} a été " . 
               ($action === 'approuver' ? 'approuvée' : 'refusée') . " par votre responsable.";
    
    $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, message, date_creation) VALUES (?, ?, NOW())");
    $stmt_notif->execute([$conge_info['user_id'], $message]);
    
    $_SESSION['message'] = "Demande de congé traitée avec succès";
    header("Location: responsable_dashboard.php?section=conges");
    exit;
}
// Traitement des demandes de recrutement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'envoyer_recrutement') {
    $responsable_id = $_SESSION['user_id'];
    $poste = $_POST['poste'];
    $motivation = $_POST['motivation'];
    $urgence = $_POST['urgence'];
    
    // Gestion de l'upload du fichier PDF
    $dossier_upload = 'uploads/recrutement/';
    if (!is_dir($dossier_upload)) {
        mkdir($dossier_upload, 0755, true);
    }
    
    $nom_fichier = '';
    if (isset($_FILES['fichier_pdf']) && $_FILES['fichier_pdf']['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($_FILES['fichier_pdf']['name'], PATHINFO_EXTENSION);
        
        // Vérifier que c'est bien un PDF
        if (strtolower($extension) === 'pdf') {
            // Générer un nom unique pour le fichier
            $nom_fichier = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $poste) . '.pdf';
            $chemin_fichier = $dossier_upload . $nom_fichier;
            
            if (move_uploaded_file($_FILES['fichier_pdf']['tmp_name'], $chemin_fichier)) {
                // Insertion dans la base de données
                // Version corrigée
$stmt = $pdo->prepare("INSERT INTO demandes_recrutement 
                    (responsable_id, poste, motivation, urgence, fichier_pdf, date_demande, statut) 
                    VALUES (?, ?, ?, ?, ?, NOW(), 'en_attente')");
$stmt->execute([$responsable_id, $poste, $motivation, $urgence, $nom_fichier]);
                
                // Notification à l'admin RH uniquement
                $message_notif = "Nouvelle demande de recrutement pour le poste: $poste - Responsable: " . $_SESSION['name'];
                
                // Trouver l'admin RH
                $stmt_admin = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                $stmt_admin->execute();
                $admin_id = $stmt_admin->fetch()['id'];
                
                if ($admin_id) {
                    $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, message, date_creation) VALUES (?, ?, NOW())");
                    $stmt_notif->execute([$admin_id, $message_notif]);
                }
                
                $_SESSION['message'] = "Demande de recrutement envoyée avec succès à l'administration RH";
            } else {
                $_SESSION['message'] = "Erreur lors de l'upload du fichier";
            }
        } else {
            $_SESSION['message'] = "Veuillez uploader un fichier PDF valide";
        }
    } else {
        $_SESSION['message'] = "Veuillez sélectionner un fichier PDF";
    }
    
    header("Location: responsable_dashboard.php?section=recrutement");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_conge'])) {
    $conge_id = $_POST['conge_id'];
    $action = $_POST['action_conge'];
    $responsable_id = $_SESSION['user_id'];
    
    // Mettre à jour le statut
    $nouveau_statut = $action === 'approuver' ? 'approuve' : 'refuse';
    $stmt = $pdo->prepare("UPDATE conges SET statut = ?, date_traitement = NOW(), traite_par = ? WHERE id = ?");
    $stmt->execute([$nouveau_statut, $responsable_id, $conge_id]);
    
    // Récupérer les infos pour la notification
    $stmt_info = $pdo->prepare("SELECT c.*, u.name, u.id as user_id FROM conges c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
    $stmt_info->execute([$conge_id]);
    $conge = $stmt_info->fetch();
    
    // Notifier l'employé - CORRECTION ICI: suppression de la colonne 'type'
    $message = "Votre demande de congé du {$conge['date_debut']} au {$conge['date_fin']} a été " . 
               ($action === 'approuver' ? 'approuvée' : 'refusée') . " par votre responsable.";
    
    // Vérifier la structure de la table notifications
    $stmt_check = $pdo->query("DESCRIBE notifications");
    $columns = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('type', $columns)) {
        // Si la colonne type existe
        $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, message, type, date_creation) VALUES (?, ?, 'reponse_conge', NOW())");
        $stmt_notif->execute([$conge['user_id'], $message]);
    } else {
        // Si la colonne type n'existe pas
        $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, message, date_creation) VALUES (?, ?, NOW())");
        $stmt_notif->execute([$conge['user_id'], $message]);
    }
    
    $_SESSION['message'] = "Demande de congé traitée avec succès";
    header("Location: responsable_dashboard.php");
    exit;
}
// ... code existant ...

// Traitement des demandes de congé du responsable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'demander_conge') {
    $responsable_id = $_SESSION['user_id'];
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];
    $type_conge = $_POST['type_conge'];
    $cause = $_POST['cause'];
    
    // Validation des dates
    if ($date_debut >= $date_fin) {
        $_SESSION['message'] = "La date de fin doit être postérieure à la date de début";
        header("Location: responsable_dashboard.php?section=conges");
        exit;
    }
    
    // Vérifier si le responsable a déjà une demande en attente
    $stmt_check = $pdo->prepare("SELECT id FROM conges WHERE user_id = ? AND statut = 'en_attente'");
    $stmt_check->execute([$responsable_id]);
    if ($stmt_check->fetch()) {
        $_SESSION['message'] = "Vous avez déjà une demande de congé en attente";
        header("Location: responsable_dashboard.php?section=conges");
        exit;
    }
    
    // Insérer la demande de congé
    $stmt = $pdo->prepare("INSERT INTO conges (user_id, date_debut, date_fin, type_conge, cause, date_demande, statut) 
                           VALUES (?, ?, ?, ?, ?, NOW(), 'en_attente_pdg')");
    $stmt->execute([$responsable_id, $date_debut, $date_fin, $type_conge, $cause]);
    
    // Notification au PDG
    $message_notif = "Nouvelle demande de congé de " . $_SESSION['name'] . " (" . $_SESSION['matiere'] . ") nécessitant votre approbation";
    
    // Trouver le PDG
    $stmt_pdg = $pdo->prepare("SELECT id FROM users WHERE role = 'pdg' LIMIT 1");
    $stmt_pdg->execute();
    $pdg_id = $stmt_pdg->fetch()['id'];
    
    if ($pdg_id) {
        $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, message, date_creation) VALUES (?, ?, NOW())");
        $stmt_notif->execute([$pdg_id, $message_notif]);
    }
    
    $_SESSION['message'] = "Demande de congé envoyée au PDG avec succès";
    header("Location: responsable_dashboard.php?section=conges");
    exit;
}

// Récupération des demandes de congé du responsable lui-même
$sql_mes_conges = "SELECT * FROM conges WHERE user_id = ? ORDER BY date_demande DESC";
$stmt_mes_conges = $pdo->prepare($sql_mes_conges);
$stmt_mes_conges->execute([$_SESSION['user_id']]);
$mes_conges = $stmt_mes_conges->fetchAll(PDO::FETCH_ASSOC);

// ... reste du code existant ...
// Export CSV
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="pointages.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employé', 'Matière', 'Action', 'Date', 'État']);

    foreach ($pointages as $p) {
        $etat = $_SESSION['pointage_valide'][$p['id']] ?? "en attente";
        fputcsv($output, [$p['name'], $p['matiere'], $p['type'], $p['timestamp'], $etat]);
    }
    fclose($output);
    exit();
}
// Traitement de l'envoi au PDG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'envoyer_au_pdg') {
    $demande_id = $_POST['demande_id'];
    
    // Vérifier que la demande appartient bien au responsable
    $stmt_check = $pdo->prepare("SELECT id FROM demandes_recrutement WHERE id = ? AND responsable_id = ?");
    $stmt_check->execute([$demande_id, $_SESSION['user_id']]);
    
    if ($stmt_check->fetch()) {
        // Mettre à jour la demande
        $stmt_update = $pdo->prepare("UPDATE demandes_recrutement SET envoye_au_pdg = 1, date_envoi_pdg = NOW() WHERE id = ?");
        $stmt_update->execute([$demande_id]);
        
        // Notification au PDG
        $message_notif = "Nouvelle demande de recrutement urgente nécessitant votre approbation";
        
        // Trouver le PDG (supposons que le PDG a un rôle spécifique)
        $stmt_pdg = $pdo->prepare("SELECT id FROM users WHERE role = 'pdg' LIMIT 1");
        $stmt_pdg->execute();
        $pdg_id = $stmt_pdg->fetch()['id'];
        
        if ($pdg_id) {
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, message, date_creation) VALUES (?, ?, NOW())");
            $stmt_notif->execute([$pdg_id, $message_notif]);
        }
        
        $_SESSION['message'] = "Demande envoyée au PDG avec succès";
    } else {
        $_SESSION['message'] = "Erreur: Cette demande ne vous appartient pas";
    }
    
    header("Location: responsable_dashboard.php?section=recrutement");
    exit;
}
// Déterminer la section active
$current_section = isset($_GET['section']) ? $_GET['section'] : 'pointage';

// ... code existant ...

// Récupération des notes de service pour le responsable
$sql_notes = "SELECT ns.*, 
                     CASE 
                         WHEN ns.destinataires = 'tous' THEN 1
                         WHEN ns.destinataires = 'responsables' THEN 1
                         WHEN ns.destinataires = 'employes' THEN 0
                         WHEN ns.destinataires = 'admin' THEN 0
                         ELSE 0
                     END as est_destinataire
              FROM notes_service ns
              WHERE ns.destinataires IN ('tous', 'responsables')
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
if ($current_section === 'notes_service' && isset($_GET['lire_note'])) {
    $note_id = $_GET['lire_note'];
    $stmt_marquer_lu = $pdo->prepare("INSERT IGNORE INTO notes_service_lus (note_id, user_id) VALUES (?, ?)");
    $stmt_marquer_lu->execute([$note_id, $_SESSION['user_id']]);
}

// Récupération des demandes de crédit du responsable
$stmt_credits = $pdo->prepare("SELECT * FROM credits_salaire WHERE user_id = ? ORDER BY date_demande DESC");
$stmt_credits->execute([$_SESSION['user_id']]);
$credits_salaire = $stmt_credits->fetchAll();

// Récupération de la date d'embauche du responsable
$stmt_anciennete = $pdo->prepare("SELECT date_embauche FROM users WHERE id = ?");
$stmt_anciennete->execute([$_SESSION['user_id']]);
$result_anciennete = $stmt_anciennete->fetch();
$date_embauche = $result_anciennete ? $result_anciennete['date_embauche'] : null;

// Vérification de l'ancienneté
$anciennete_suffisante = false;
if ($date_embauche) {
    $date_embauche_obj = new DateTime($date_embauche);
    $aujourdhui = new DateTime();
    $difference = $date_embauche_obj->diff($aujourdhui);
    $anciennete_mois = $difference->y * 12 + $difference->m;
    $anciennete_suffisante = $anciennete_mois >= 12;
}

// Récupération du salaire mensuel
$stmt_salaire = $pdo->prepare("SELECT salaire_mensuel FROM salaires_employes WHERE user_id = ? ORDER BY date_effet DESC LIMIT 1");
$stmt_salaire->execute([$_SESSION['user_id']]);
$result_salaire = $stmt_salaire->fetch();
$salaire_mensuel = $result_salaire ? $result_salaire['salaire_mensuel'] : 1500.00;

// Vérification si le responsable a un crédit en cours
$stmt_credit_en_cours = $pdo->prepare("SELECT COUNT(*) as count FROM credits_salaire WHERE user_id = ? AND statut = 'approuve' AND solde_restant > 0");
$stmt_credit_en_cours->execute([$_SESSION['user_id']]);
$credit_en_cours = $stmt_credit_en_cours->fetch()['count'] > 0;

// Vérification du délai de 6 mois après remboursement complet
$peut_redemander = true;
$stmt_dernier_credit = $pdo->prepare("SELECT date_remboursement_complet FROM credits_salaire WHERE user_id = ? AND statut = 'approuve' AND solde_restant = 0 ORDER BY date_remboursement_complet DESC LIMIT 1");
$stmt_dernier_credit->execute([$_SESSION['user_id']]);
$dernier_credit = $stmt_dernier_credit->fetch();

if ($dernier_credit && $dernier_credit['date_remboursement_complet']) {
    $date_remboursement = new DateTime($dernier_credit['date_remboursement_complet']);
    $aujourdhui = new DateTime();
    $difference = $date_remboursement->diff($aujourdhui);
    $mois_ecoules = $difference->y * 12 + $difference->m;
    $peut_redemander = $mois_ecoules >= 6;
}

// Récupération de la liste des employés pour le filtre
$sql_employes = "SELECT DISTINCT u.id, u.name 
                 FROM users u 
                 WHERE u.matiere IN ($placeholders) 
                 ORDER BY u.name";
$stmt_employes = $pdo->prepare($sql_employes);
$stmt_employes->execute($matieres_a_afficher);
$employes_liste = $stmt_employes->fetchAll(PDO::FETCH_ASSOC);

// Récupération des demandes de recrutement du responsable
$stmt_recrutement = $pdo->prepare("SELECT * FROM demandes_recrutement WHERE responsable_id = ? ORDER BY date_demande DESC");
$stmt_recrutement->execute([$_SESSION['user_id']]);
$demandes_recrutement = $stmt_recrutement->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Responsable</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --bg-color: #f0f2f5;
            --text-color: #333;
            --header-bg: #4b6cb7;
            --header-text: white;
            --table-header: #4b6cb7;
            --table-header-text: white;
            --card-bg: white;
            --button-bg: #4b6cb7;
            --button-text: white;
            --sidebar-bg: #2c3e50;
            --sidebar-text: #ecf0f1;
            --sidebar-active: #3498db;
            --danger: #e74c3c;
            --warning: #f39c12;
            --success: #2ecc71;
        }

        body.dark {
            --bg-color: #1e1e1e;
            --text-color: #f0f0f0;
            --header-bg: #333;
            --header-text: #f0f0f0;
            --table-header: #444;
            --table-header-text: #f0f0f0;
            --card-bg: #2c2c2c;
            --button-bg: #555;
            --button-text: #f0f0f0;
            --sidebar-bg: #1a1a1a;
            --sidebar-text: #cccccc;
            --sidebar-active: #4b6cb7;
            --danger: #c0392b;
            --warning: #d35400;
            --success: #27ae60;
        }

        * { 
            box-sizing: border-box; 
            font-family: 'Inter', sans-serif; 
            transition: all 0.3s ease; 
        }
        
        body {
            margin: 0;
            background: var(--bg-color);
            color: var(--text-color);
            display: flex;
            min-height: 100vh;
        }

        .app-container {
            display: flex;
            width: 100%;
        }

        .sidebar {
            width: 250px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 20px 0;
            height: 100vh;
            position: sticky;
            top: 0;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            color: white;
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

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: all 0.2s;
        }

        .sidebar-menu li a:hover {
            background: rgba(255,255,255,0.1);
        }

        .sidebar-menu li a.active {
            background: var(--sidebar-active);
            color: white;
        }

        .sidebar-menu li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: var(--header-bg);
            color: var(--header-text);
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 { 
            margin: 0; 
            font-size: 20px; 
        }

        .logout, .toggle-mode {
            background: var(--danger);
            padding: 10px 16px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            font-weight: bold;
            margin-left: 10px;
            cursor: pointer;
            border: none;
        }

        .toggle-mode {
            background: var(--success);
        }

        .dashboard {
            flex: 1;
            max-width: 1100px;
            margin: 30px auto;
            background: var(--card-bg);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: calc(100% - 40px);
        }

        h2 { 
            margin-top: 0; 
            color: var(--button-bg); 
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: var(--table-header);
            color: var(--table-header-text);
        }

        .filters {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .filters select, .filters input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            background: var(--card-bg);
            color: var(--text-color);
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            font-weight: 500;
        }

        .btn-primary {
            background: var(--button-bg);
            color: var(--button-text);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .pointage-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .status-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-present {
            background: var(--success);
            color: white;
        }

        .status-absent {
            background: var(--danger);
            color: white;
        }

        .status-waiting {
            background: var(--warning);
            color: white;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            z-index: 9999;
        }

        .toast.error {
            background: var(--danger);
        }

        .toast.warning {
            background: var(--warning);
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .info-badge {
            background: var(--button-bg);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: inline-block;
        }
        
        .section-content {
            display: none;
        }
        
        .section-content.active {
            display: block;
        }
        
        .pending-count {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            margin-left: 8px;
        }
        /* Ajouter ces styles dans la section <style> */

.notes-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.note-card {
    background: var(--card-bg);
    border: 1px solid #ddd;
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
    border-bottom: 1px solid #eee;
}

.note-title-section {
    flex: 1;
}

.note-title {
    margin: 0 0 10px 0;
    color: var(--button-bg);
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
    background: var(--sidebar-bg);
    color: white;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.8em;
}

.note-date {
    background: var(--button-bg);
}

.note-actions {
    flex-shrink: 0;
}

.lu-badge {
    color: var(--success);
    font-size: 0.9em;
}

.note-content {
    margin-bottom: 15px;
}

.note-auteur {
    background: rgba(75, 108, 183, 0.1);
    padding: 10px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    color: var(--button-bg);
    font-size: 0.9em;
}

.note-contenu-text {
    line-height: 1.6;
    font-size: 1em;
    white-space: pre-wrap;
}

.note-footer {
    padding-top: 15px;
    border-top: 1px solid #eee;
    font-size: 0.8em;
    color: #666;
    font-style: italic;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #666;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 20px;
    color: #ddd;
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
}
/* Ajouter ces styles dans la section CSS */
.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--text-color);
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: var(--card-bg);
    color: var(--text-color);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.table-responsive {
    overflow-x: auto;
}
.btn-sm {
    padding: 4px 8px;
    font-size: 0.8rem;
}

.card {
    background: var(--card-bg);
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
}

.card-header {
    background: var(--table-header);
    color: var(--table-header-text);
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
}

.card-title {
    margin: 0;
    font-size: 1.2em;
}

.card-body {
    padding: 20px;
}
    </style>
</head>
<body>
<div class="app-container">
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PointagePro</h2>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></div>
                    <div class="user-role"><?= htmlspecialchars($_SESSION['matiere']) ?></div>
                </div>
            </div>
        </div>
        <!-- Dans la sidebar, remplacer la section du menu existante par : -->
<ul class="sidebar-menu">
    <li>
        <a href="?section=pointage" class="<?= $current_section === 'pointage' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i> Tableau de bord
        </a>
    </li>
    <li>
        <a href="?section=conges" class="<?= $current_section === 'conges' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> Gestion des congés
            <?php 
            $pending_count = 0;
            foreach ($conges as $c) {
                if ($c['statut'] == 'en_attente') {
                    $pending_count++;
                }
            }
            if ($pending_count > 0): ?>
                <span class="pending-count"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li>
        <a href="?section=notes_service" class="<?= $current_section === 'notes_service' ? 'active' : '' ?>">
            <i class="fas fa-bullhorn"></i> Notes de service
            <?php if ($notes_non_lues > 0): ?>
                <span class="pending-count"><?= $notes_non_lues ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li>
        <a href="?section=recrutement" class="<?= $current_section === 'recrutement' ? 'active' : '' ?>">
            <i class="fas fa-user-plus"></i> Demande de recrutement
        </a>
    </li>
    <li>
        <a href="?section=credit_salaire" class="<?= $current_section === 'credit_salaire' ? 'active' : '' ?>">
            <i class="fas fa-hand-holding-usd"></i> Crédit sur salaire
        </a>
    </li>
</ul>

    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Dashboard Responsable - <?= htmlspecialchars($_SESSION['matiere']) ?></h1>
            <div>
                <button class="toggle-mode" onclick="toggleDarkMode()">
                    <i class="fas fa-moon"></i> Mode sombre
                </button>
                <a href="logout.php" class="logout">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </div>

        <div class="dashboard">
            <!-- Section Pointage -->
            <div id="pointage" class="section-content <?= $current_section === 'pointage' ? 'active' : '' ?>">
                <h2><i class="fas fa-tachometer-alt"></i> Tableau de bord pointage</h2>
                
                <div class="info-badge">
                    <i class="fas fa-info-circle"></i> 
                    Vous gérez les pointages pour: <?= implode(', ', $matieres_a_afficher) ?>
                </div>

                <!-- Filtres -->
                <div class="filters">
                    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                        <input type="hidden" name="section" value="pointage">
                        
                        <select name="employe">
                            <option value="">Tous les employés</option>
                            <?php foreach ($employes_liste as $employe): ?>
                                <option value="<?= htmlspecialchars($employe['name']) ?>" 
                                    <?= isset($_GET['employe']) && $_GET['employe'] === $employe['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($employe['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="date" name="date" value="<?= $_GET['date'] ?? '' ?>">
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        
                        <a href="?section=pointage" class="btn btn-warning">
                            <i class="fas fa-refresh"></i> Réinitialiser
                        </a>
                        
                        <a href="?section=pointage&export_csv=1" class="btn btn-success">
                            <i class="fas fa-download"></i> Exporter CSV
                        </a>
                    </form>
                </div>

                <!-- Tableau des pointages -->
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Employé</th>
                                <th>Matière</th>
                                <th>Action</th>
                                <th>Date</th>
                                <th>État</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pointages)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">Aucun pointage trouvé</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pointages as $pointage): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($pointage['name']) ?></td>
                                        <td><?= htmlspecialchars($pointage['matiere']) ?></td>
                                        <td>
                                            <span class="status-indicator <?= $pointage['type'] === 'entrée' ? 'status-present' : 'status-absent' ?>">
                                                <?= $pointage['type'] === 'entrée' ? 'Entrée' : 'Sortie' ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($pointage['timestamp'])) ?></td>
                                        <td>
                                            <?php
                                            $etat = $_SESSION['pointage_valide'][$pointage['id']] ?? "en attente";
                                            $class = $etat === "validé" ? "status-present" : 
                                                    ($etat === "rejeté" ? "status-absent" : "status-waiting");
                                            ?>
                                            <span class="status-indicator <?= $class ?>"><?= $etat ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section Congés -->
             <!-- Section Congés -->
<div id="conges" class="section-content <?= $current_section === 'conges' ? 'active' : '' ?>">
    <h2><i class="fas fa-calendar-alt"></i> Gestion des congés</h2>
    
    <div class="info-badge">
        <i class="fas fa-info-circle"></i> 
        Demandes de congé pour votre équipe
    </div>

    <!-- Nouveau : Formulaire de demande de congé pour le responsable -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-plus-circle"></i> Faire une demande de congé</h3>
        </div>
        <div class="card-body">
            <form method="POST" style="max-width: 600px;">
                <input type="hidden" name="action" value="demander_conge">
                
                <div class="form-group">
                    <label for="date_debut">Date de début *</label>
                    <input type="date" id="date_debut" name="date_debut" class="form-control" required 
                           min="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_fin">Date de fin *</label>
                    <input type="date" id="date_fin" name="date_fin" class="form-control" required 
                           min="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label for="type_conge">Type de congé *</label>
                    <select id="type_conge" name="type_conge" class="form-control" required>
                        <option value="">Sélectionnez...</option>
                        <option value="Congé annuel">Congé annuel</option>
                        <option value="Congé exceptionnel">Congé exceptionnel</option>
                        <option value="Congé maternité">Congé maternité</option>
                        <option value="Congé paternité">Congé paternité</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="cause">Motif *</label>
                    <textarea id="cause" name="cause" class="form-control" rows="3" required 
                              placeholder="Décrivez le motif de votre congé..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="reset" class="btn btn-warning">
                        <i class="fas fa-undo"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i> Envoyer au PDG
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mes demandes de congé -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-user"></i> Mes demandes de congé</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Date début</th>
                            <th>Date fin</th>
                            <th>Type</th>
                            <th>Motif</th>
                            <th>Statut</th>
                            <th>Date demande</th>
                            <th>Traîté par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mes_conges)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">Aucune demande de congé</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($mes_conges as $conge): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($conge['date_debut'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($conge['date_fin'])) ?></td>
                                    <td><?= htmlspecialchars($conge['type_conge']) ?></td>
                                    <td title="<?= htmlspecialchars($conge['cause']) ?>">
                                        <div style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= htmlspecialchars($conge['cause']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $statut_class = '';
                                        if ($conge['statut'] == 'approuve') $statut_class = 'status-present';
                                        elseif ($conge['statut'] == 'refuse') $statut_class = 'status-absent';
                                        elseif ($conge['statut'] == 'en_attente_pdg') $statut_class = 'status-waiting';
                                        else $statut_class = 'status-waiting';
                                        ?>
                                        <span class="status-indicator <?= $statut_class ?>">
                                            <?= $conge['statut'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($conge['date_demande'])) ?></td>
                                    <td>
                                        <?php if ($conge['traite_par']): 
                                            $stmt_traite_par = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                                            $stmt_traite_par->execute([$conge['traite_par']]);
                                            $traite_par_name = $stmt_traite_par->fetch()['name'];
                                            echo htmlspecialchars($traite_par_name);
                                        else: ?>
                                            <span style="color: #666;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Demandes de congé de l'équipe (code existant) -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-users"></i> Demandes de congé de mon équipe</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Matière</th>
                            <th>Date début</th>
                            <th>Date fin</th>
                            <th>Type</th>
                            <th>Motif</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($conges)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">Aucune demande de congé</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($conges as $conge): ?>
                                <tr>
                                    <td><?= htmlspecialchars($conge['employe_nom']) ?></td>
                                    <td><?= htmlspecialchars($conge['matiere']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($conge['date_debut'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($conge['date_fin'])) ?></td>
                                    <td><?= htmlspecialchars($conge['type_conge']) ?></td>
                                    <td><?= htmlspecialchars($conge['cause']) ?></td>
                                    <td>
                                        <?php
                                        $statut_class = '';
                                        if ($conge['statut'] == 'approuve') $statut_class = 'status-present';
                                        elseif ($conge['statut'] == 'refuse') $statut_class = 'status-absent';
                                        else $statut_class = 'status-waiting';
                                        ?>
                                        <span class="status-indicator <?= $statut_class ?>">
                                            <?= $conge['statut'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($conge['statut'] == 'en_attente'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="conge_id" value="<?= $conge['id'] ?>">
                                                <input type="hidden" name="action_conge" value="approuver">
                                                <button type="submit" class="btn btn-success" onclick="return confirm('Approuver cette demande de congé?')">
                                                    <i class="fas fa-check"></i> Approuver
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="conge_id" value="<?= $conge['id'] ?>">
                                                <input type="hidden" name="action_conge" value="refuser">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Refuser cette demande de congé?')">
                                                    <i class="fas fa-times"></i> Refuser
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="status-indicator status-present">Traité</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
            

            <!-- Section Notes de service -->
            <div id="notes_service" class="section-content <?= $current_section === 'notes_service' ? 'active' : '' ?>">
                <h2><i class="fas fa-bullhorn"></i> Notes de service</h2>
                
                <div class="info-badge">
                    <i class="fas fa-info-circle"></i> 
                    Notes de service destinées aux responsables
                </div>

                <div class="notes-container">
                    <?php if (empty($notes_service)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <p>Aucune note de service disponible</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notes_service as $note): ?>
                            <?php if ($note['est_destinataire']): ?>
                                <?php
                                // Vérifier si la note a été lue
                                $stmt_lu = $pdo->prepare("SELECT id FROM notes_service_lus WHERE note_id = ? AND user_id = ?");
                                $stmt_lu->execute([$note['id'], $_SESSION['user_id']]);
                                $deja_lu = $stmt_lu->fetch();
                                
                                $note_class = $deja_lu ? '' : 'note-non-lue';
                                ?>
                                <div class="note-card <?= $note_class ?>" id="note-<?= $note['id'] ?>">
                                    <div class="note-header">
                                        <div class="note-title-section">
                                            <h3 class="note-title">
                                                <?= htmlspecialchars($note['titre']) ?>
                                                <?php if (!$deja_lu): ?>
                                                    <span class="nouveau-badge">NOUVEAU</span>
                                                <?php endif; ?>
                                            </h3>
                                            <div class="note-meta">
                                                <span class="destinataires-badge">
                                                    <i class="fas fa-users"></i> 
                                                    <?= htmlspecialchars($note['destinataires']) ?>
                                                </span>
                                                <span class="note-date">
                                                    <i class="fas fa-calendar"></i> 
                                                    <?= date('d/m/Y H:i', strtotime($note['date_creation'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="note-actions">
                                            <?php if ($deja_lu): ?>
                                                <span class="lu-badge">
                                                    <i class="fas fa-check-circle"></i> Lu
                                                </span>
                                            <?php else: ?>
                                                <a href="?section=notes_service&lire_note=<?= $note['id'] ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> Marquer comme lu
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($note['auteur'])): ?>
                                        <div class="note-auteur">
                                            <strong>Auteur:</strong> <?= htmlspecialchars($note['auteur']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="note-content">
                                        <div class="note-contenu-text">
                                            <?= nl2br(htmlspecialchars($note['contenu'])) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="note-footer">
                                        Note de service #<?= $note['id'] ?> - 
                                        <?= $deja_lu ? 'Lue le ' . date('d/m/Y H:i') : 'Non lue' ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
                        <!-- Section Recrutement -->
            <div id="recrutement" class="section-content <?= $current_section === 'recrutement' ? 'active' : '' ?>">
                <h2><i class="fas fa-user-plus"></i> Demande de recrutement</h2>
                
                <div class="info-badge">
                    <i class="fas fa-info-circle"></i> 
                    Envoyer une demande de recrutement à l'administration RH
                </div>

                <form method="POST" enctype="multipart/form-data" style="max-width: 600px;">
                    <input type="hidden" name="action" value="envoyer_recrutement">
                    
                    <div class="form-group">
                        <label for="poste">Poste à pourvoir *</label>
                        <input type="text" id="poste" name="poste" class="form-control" required 
                               placeholder="Ex: Développeur web, Assistant administratif...">
                    </div>
                    
                    <div class="form-group">
                        <label for="motivation">Motivation et besoins *</label>
                        <textarea id="motivation" name="motivation" class="form-control" rows="4" required 
                                  placeholder="Décrivez les raisons de ce recrutement, les compétences recherchées..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="urgence">Niveau d'urgence *</label>
                        <select id="urgence" name="urgence" class="form-control" required>
                            <option value="">Sélectionnez...</option>
                            <option value="normal">Normal</option>
                            <option value="eleve">Élevé</option>
                            <option value="critique">Critique</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="fichier_pdf">Document justificatif (PDF) *</label>
                        <input type="file" id="fichier_pdf" name="fichier_pdf" class="form-control" 
                               accept=".pdf" required>
                        <small style="color: #666;">Format accepté: PDF uniquement (max 5MB)</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn btn-warning">
                            <i class="fas fa-undo"></i> Annuler
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> Envoyer à l'administration RH
                        </button>
                    </div>
                </form>

                <!-- Historique des demandes de recrutement -->
                <div style="margin-top: 40px;">
                    <h3><i class="fas fa-history"></i> Historique de mes demandes</h3>
                    
                    <div class="table-responsive">
                        <table style="width: 100%; margin-top: 20px;">
                            <thead>
                                <tr>
                                    <th>Date demande</th>
                                    <th>Poste</th>
                                    <th>Motivation</th>
                                    <th>Urgence</th>
                                    <th>Statut RH</th>
                                    <th>Envoyé au PDG</th>
                                    <th>Statut PDG</th>
                                    <th>Commentaire PDG</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($demandes_recrutement as $demande): 
                                    $statut_class = '';
                                    if ($demande['statut'] == 'approuve') $statut_class = 'status-present';
                                    elseif ($demande['statut'] == 'refuse') $statut_class = 'status-absent';
                                    else $statut_class = 'status-waiting';
                                    
                                    $urgence_class = '';
                                    if ($demande['urgence'] == 'critique') $urgence_class = 'status-absent';
                                    elseif ($demande['urgence'] == 'eleve') $urgence_class = 'status-waiting';
                                    else $urgence_class = 'status-present';
                                ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($demande['date_demande'])) ?></td>
                                        <td><strong><?= htmlspecialchars($demande['poste']) ?></strong></td>
                                        <td title="<?= htmlspecialchars($demande['motivation']) ?>">
                                            <div style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?= strlen($demande['motivation']) > 50 ? substr($demande['motivation'], 0, 50) . '...' : htmlspecialchars($demande['motivation']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-indicator <?= $urgence_class ?>">
                                                <?= htmlspecialchars($demande['urgence']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-indicator <?= $statut_class ?>">
                                                <?= htmlspecialchars($demande['statut']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($demande['envoye_au_pdg']): ?>
                                                <span class="status-indicator status-present">
                                                    <i class="fas fa-check"></i> Oui
                                                </span>
                                                <br>
                                                <small><?= date('d/m/Y H:i', strtotime($demande['date_envoi_pdg'])) ?></small>
                                            <?php else: ?>
                                                <span class="status-indicator status-waiting">
                                                    <i class="fas fa-times"></i> Non
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($demande['traite_par_pdg']): ?>
                                                <span class="status-indicator <?= $demande['statut'] == 'approuve' ? 'status-present' : 'status-absent' ?>">
                                                    <?= htmlspecialchars($demande['statut']) ?>
                                                </span>
                                                <br>
                                                <small>Traité le: <?= date('d/m/Y H:i', strtotime($demande['date_traitement'])) ?></small>
                                            <?php else: ?>
                                                <span class="status-indicator status-waiting">
                                                    En attente
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td title="<?= htmlspecialchars($demande['commentaire_pdg']) ?>">
                                            <?php if ($demande['commentaire_pdg']): ?>
                                                <div style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    <?= htmlspecialchars($demande['commentaire_pdg']) ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #666;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($demande['fichier_pdf']): ?>
                                                <a href="uploads/recrutement/<?= htmlspecialchars($demande['fichier_pdf']) ?>" 
                                                   target="_blank" class="btn btn-primary btn-sm" style="margin-bottom: 5px;">
                                                    <i class="fas fa-eye"></i> Voir PDF
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!$demande['envoye_au_pdg'] && $demande['urgence'] == 'critique'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="demande_id" value="<?= $demande['id'] ?>">
                                                    <input type="hidden" name="action" value="envoyer_au_pdg">
                                                    <button type="submit" class="btn btn-warning btn-sm" 
                                                            onclick="return confirm('Envoyer cette demande urgente au PDG?')">
                                                        <i class="fas fa-paper-plane"></i> Envoyer au PDG
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($demandes_recrutement)): ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center;">Aucune demande de recrutement</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            

            <!-- Section Crédit sur salaire -->
            <div id="credit_salaire" class="section-content <?= $current_section === 'credit_salaire' ? 'active' : '' ?>">
                <h2><i class="fas fa-hand-holding-usd"></i> Crédit sur salaire</h2>
                
                <div class="info-badge">
                    <i class="fas fa-info-circle"></i> 
                    Gestion des demandes de crédit sur salaire
                </div>

                <!-- Afficher les conditions d'éligibilité -->
                <div style="background: rgba(52, 152, 219, 0.1); padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                    <h4 style="margin-top: 0; color: var(--button-bg);">
                        <i class="fas fa-info-circle"></i> Conditions d'éligibilité
                    </h4>
                    <ul style="margin-bottom: 0;">
                        <li>Ancienneté minimum: 12 mois dans l'entreprise</li>
                        <li>Aucun crédit en cours de remboursement</li>
                        <li>Délai de 6 mois après remboursement complet d'un précédent crédit</li>
                        <li>Montant maximum: 50% du salaire mensuel</li>
                    </ul>
                </div>

                <!-- Vérification de l'éligibilité -->
                <?php if (!$anciennete_suffisante): ?>
                    <div style="background: var(--warning); color: white; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Non éligible:</strong> Vous devez avoir au moins 12 mois d'ancienneté dans l'entreprise.
                    </div>
                <?php elseif ($credit_en_cours): ?>
                    <div style="background: var(--warning); color: white; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Crédit en cours:</strong> Vous avez déjà un crédit en cours de remboursement.
                    </div>
                <?php elseif (!$peut_redemander): ?>
                    <div style="background: var(--warning); color: white; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Délai non respecté:</strong> Vous devez attendre 6 mois après le remboursement complet de votre dernier crédit.
                    </div>
                <?php else: ?>
                    <!-- Formulaire de demande de crédit -->
                    <form method="POST" action="demande_credit.php" style="max-width: 500px;">
                        <div class="form-group">
                            <label for="montant">Montant demandé (€) *</label>
                            <input type="number" id="montant" name="montant" class="form-control" 
                                   min="100" max="<?= $salaire_mensuel * 0.5 ?>" step="50" required
                                   placeholder="Montant entre 100€ et <?= number_format($salaire_mensuel * 0.5, 2) ?>€">
                            <small style="color: #666;">Maximum: 50% de votre salaire mensuel (<?= number_format($salaire_mensuel, 2) ?>€)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="duree_remboursement">Durée de remboursement (mois) *</label>
                            <select id="duree_remboursement" name="duree_remboursement" class="form-control" required>
                                <option value="">Sélectionnez...</option>
                                <option value="6">6 mois</option>
                                <option value="12">12 mois</option>
                                <option value="18">18 mois</option>
                                <option value="24">24 mois</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="motif_credit">Motif du crédit *</label>
                            <textarea id="motif_credit" name="motif_credit" class="form-control" rows="3" required
                                      placeholder="Décrivez l'utilisation prévue de ce crédit..."></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-paper-plane"></i> Soumettre la demande
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <!-- Historique des demandes de crédit -->
                <div class="card">
        <div class="card-header">
            <h3 class="card-title">Mes demandes de crédit</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Date demande</th>
                            <th>Montant</th>
                            <th>Mensualités</th>
                            <th>Mensualité</th>
                            <th>Motif</th>
                            <th>Statut</th>
                            <th>Solde restant</th>
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
                                    <div style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= strlen($credit['motif']) > 50 ? substr($credit['motif'], 0, 50) . '...' : htmlspecialchars($credit['motif']) ?>
                                    </div>
                                </td>
                                <td><span class="status-indicator <?= $status_class ?>"><?= htmlspecialchars($credit['statut']) ?></span></td>
                                <td><?= $solde_restant ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($credits_salaire)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">Aucune demande de crédit</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast pour les messages -->
<?php if (isset($_SESSION['message'])): ?>
    <div class="toast show" id="toast">
        <?= $_SESSION['message'] ?>
    </div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<script>
// Gestion du toast
setTimeout(() => {
    const toast = document.getElementById('toast');
    if (toast) {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }
}, 4000);

// Mode sombre
function toggleDarkMode() {
    document.body.classList.toggle('dark');
    const isDark = document.body.classList.contains('dark');
    localStorage.setItem('darkMode', isDark);
}

// Restaurer le mode sombre
if (localStorage.getItem('darkMode') === 'true') {
    document.body.classList.add('dark');
}

// Navigation entre sections
function showSection(sectionId) {
    // Masquer toutes les sections
    document.querySelectorAll('.section-content').forEach(section => {
        section.classList.remove('active');
    });
    
    // Afficher la section sélectionnée
    document.getElementById(sectionId).classList.add('active');
    
    // Mettre à jour le menu
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.classList.remove('active');
    });
    event.target.classList.add('active');
}

// Gestion du marquage des notes comme lues
function marquerCommeLu(noteId) {
    fetch(`?section=notes_service&lire_note=${noteId}`)
        .then(response => {
            const noteElement = document.getElementById(`note-${noteId}`);
            if (noteElement) {
                noteElement.classList.remove('note-non-lue');
                // Mettre à jour l'interface utilisateur
                const badge = noteElement.querySelector('.nouveau-badge');
                if (badge) badge.remove();
                
                const actions = noteElement.querySelector('.note-actions');
                if (actions) {
                    actions.innerHTML = '<span class="lu-badge"><i class="fas fa-check-circle"></i> Lu</span>';
                }
            }
        });
}
// Fonction pour afficher les détails complets d'une motivation ou commentaire
function afficherDetails(contenu, titre) {
    const modal = document.createElement('div');
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100%';
    modal.style.height = '100%';
    modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
    modal.style.display = 'flex';
    modal.style.justifyContent = 'center';
    modal.style.alignItems = 'center';
    modal.style.zIndex = '10000';
    
    modal.innerHTML = `
        <div style="background: var(--card-bg); padding: 30px; border-radius: 10px; max-width: 600px; max-height: 80vh; overflow-y: auto;">
            <h3 style="margin-top: 0; color: var(--button-bg);">${titre}</h3>
            <div style="white-space: pre-wrap; line-height: 1.6;">${contenu}</div>
            <div style="text-align: center; margin-top: 20px;">
                <button onclick="this.closest('div').parentElement.remove()" class="btn btn-primary">
                    Fermer
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// Ajouter les événements de clic pour les cellules tronquées
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('td div[style*="ellipsis"]').forEach(cell => {
        cell.style.cursor = 'pointer';
        cell.title = 'Cliquer pour voir le contenu complet';
        cell.addEventListener('click', function() {
            const contenuComplet = this.getAttribute('title') || this.textContent;
            const titre = this.closest('tr').querySelector('td:nth-child(2)').textContent;
            afficherDetails(contenuComplet, titre);
        });
    });
});
</script>
</body>
</html>
