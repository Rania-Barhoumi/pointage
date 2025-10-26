<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Vérification du rôle admin
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['admin_message'] = "Accès refusé : Vous n'avez pas les droits d'administration";
    header("Location: index.php");
    exit;
}

$name = $_SESSION['name'];
$pdo = new PDO("mysql:host=localhost;dbname=pointage", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Traitement des actions sur les utilisateurs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                // Dans la section de traitement POST, mettre à jour les cas 'create' et 'update'
case 'create':
    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, matricule, password, role, matiere, date_embauche) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['name'], 
        $_POST['email'], 
        $_POST['matricule'] ?: null, 
        $hashedPassword, 
        $_POST['role'],
        $_POST['matiere'] ?: null,
        $_POST['date_embauche'] ?: null
    ]);
    $_SESSION['admin_message'] = "Utilisateur créé avec succès";
    break;

case 'update':
    $data = [
        $_POST['name'], 
        $_POST['email'], 
        $_POST['matricule'] ?: null,
        $_POST['role'],
        $_POST['matiere'] ?: null,
        $_POST['date_embauche'] ?: null,
        $_POST['id']
    ];
    
    $sql = "UPDATE users SET name = ?, email = ?, matricule = ?, role = ?, matiere = ?, date_embauche = ? WHERE id = ?";
    
    if (!empty($_POST['password'])) {
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE users SET name = ?, email = ?, matricule = ?, password = ?, role = ?, matiere = ?, date_embauche = ? WHERE id = ?";
        array_splice($data, 3, 0, $hashedPassword);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    $_SESSION['admin_message'] = "Utilisateur mis à jour avec succès";
    break;


                    // Remplacer le cas 'delete' existant par :
case 'delete':
    // Désactiver le compte au lieu de le supprimer
    $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $_SESSION['admin_message'] = "Compte utilisateur désactivé avec succès";
    break;

// Ajouter un nouveau cas pour réactiver un compte
case 'activate':
    $stmt = $pdo->prepare("UPDATE users SET is_active = TRUE WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $_SESSION['admin_message'] = "Compte utilisateur réactivé avec succès";
    break;
                                   
// Dans le cas 'create', la requête reste la même car is_active a une valeur par défaut
case 'create':
    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['name'], $_POST['email'], $hashedPassword, $_POST['role']]);
    $_SESSION['admin_message'] = "Utilisateur créé avec succès";
    break;
// Dans la section de traitement POST, ajouter ce cas
case 'send_message':
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['receiver_id'], $_POST['message']]);
    $_SESSION['admin_message'] = "Message envoyé avec succès";
    break;                // Ajouter ce cas dans le switch($_POST['action'])

    // Ajouter ce cas dans le switch($_POST['action'])
case 'traiter_credit':
    $credit_id = $_POST['credit_id'];
    $statut = $_POST['statut'];
    $motif_refus = $_POST['motif_refus'] ?? null;
    
    $stmt = $pdo->prepare("UPDATE credits_salaire SET statut = ?, date_traitement = NOW(), traite_par = ?, motif_refus = ? WHERE id = ?");
    $stmt->execute([$statut, $_SESSION['user_id'], $motif_refus, $credit_id]);
    
    // Récupérer les infos du crédit pour la notification
    $stmt_info = $pdo->prepare("
        SELECT c.*, u.name, u.id as user_id 
        FROM credits_salaire c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    $stmt_info->execute([$credit_id]);
    $credit = $stmt_info->fetch();
    
    // Créer une notification pour l'employé
    $message = "Votre demande de crédit de " . number_format($credit['montant'], 2, ',', ' ') . " DT a été " . 
              ($statut == 'approuve' ? 'approuvée' : 'refusée');
    
    if ($statut == 'refuse' && $motif_refus) {
        $message .= ". Motif : " . $motif_refus;
    }
    
    $stmt_notif = $pdo->prepare("
        INSERT INTO notifications (user_id, message, date_creation) 
        VALUES (?, ?, NOW())
    ");
    $stmt_notif->execute([$credit['user_id'], $message]);
    
    $_SESSION['admin_message'] = "Demande de crédit " . 
        ($statut == 'approuve' ? 'approuvée' : 'refusée') . 
        " avec succès. L'employé a été notifié.";
    
    header("Location: admin_dashboard.php?page=credits_salaire");
    exit;
    break;
// Dans la section de traitement POST, ajouter ce cas après les autres actions
// Remplacer le cas 'traiter_recrutement' existant par :
case 'envoyer_au_pdg':
    $recrutement_id = $_POST['recrutement_id'];
    
    // Marquer la demande comme envoyée au PDG
    $stmt = $pdo->prepare("UPDATE demandes_recrutement SET envoye_au_pdg = 1, date_envoi_pdg = NOW() WHERE id = ?");
    $stmt->execute([$recrutement_id]);
    
    // Récupérer les infos de la demande pour la notification
    $stmt_info = $pdo->prepare("
        SELECT dr.*, u.name as responsable_nom, u.id as responsable_id 
        FROM demandes_recrutement dr 
        JOIN users u ON dr.responsable_id = u.id 
        WHERE dr.id = ?
    ");
    $stmt_info->execute([$recrutement_id]);
    $demande = $stmt_info->fetch();
    
    // Créer une notification pour le PDG
    $message = "Nouvelle demande de recrutement pour le poste de '" . $demande['poste'] . "' envoyée par l'administration";
    
    // Récupérer l'ID du PDG
    $pdg_user = $pdo->query("SELECT id FROM users WHERE role = 'pdg' LIMIT 1")->fetch();
    
    if ($pdg_user) {
        $stmt_notif = $pdo->prepare("
            INSERT INTO notifications (user_id, message, date_creation) 
            VALUES (?, ?, NOW())
        ");
        $stmt_notif->execute([$pdg_user['id'], $message]);
    }
    
    $_SESSION['admin_message'] = "Demande de recrutement envoyée au PDG avec succès";
    
    header("Location: admin_dashboard.php?page=recrutement");
    exit;
    break;


case 'traiter_ordre_mission':
    $ordre_mission_id = $_POST['ordre_mission_id'];
    $statut = $_POST['statut'];
    $motif_refus = $_POST['motif_refus'] ?? null;
    
    $stmt = $pdo->prepare("UPDATE ordres_mission SET statut = ?, date_traitement = NOW(), traite_par = ?, motif_refus = ? WHERE id = ?");
    $stmt->execute([$statut, $_SESSION['user_id'], $motif_refus, $ordre_mission_id]);
    
    // Récupérer les infos de l'ordre de mission pour la notification
    $stmt_info = $pdo->prepare("
        SELECT om.*, u.name, u.id as user_id 
        FROM ordres_mission om 
        JOIN users u ON om.user_id = u.id 
        WHERE om.id = ?
    ");
    $stmt_info->execute([$ordre_mission_id]);
    $ordre_mission = $stmt_info->fetch();
    
    // Créer une notification pour l'employé
    $message = "Votre demande d'ordre de mission du " . $ordre_mission['date_debut'] . " au " . $ordre_mission['date_fin'] . " a été " . 
              ($statut == 'approuve' ? 'approuvée' : 'refusée');
    
    if ($statut == 'refuse' && $motif_refus) {
        $message .= ". Motif : " . $motif_refus;
    }
    
    $stmt_notif = $pdo->prepare("
        INSERT INTO notifications (user_id, message, date_creation) 
        VALUES (?, ?, NOW())
    ");
    $stmt_notif->execute([$ordre_mission['user_id'], $message]);
    
    $_SESSION['admin_message'] = "Ordre de mission " . 
        ($statut == 'approuve' ? 'approuvé' : 'refusé') . 
        " avec succès. L'employé a été notifié.";
    
    header("Location: admin_dashboard.php?page=ordres_mission");
    exit;
    break;
case 'marquer_note_lu':
    $note_id = $_POST['note_id'];
    
    // Vérifier si la note n'est pas déjà marquée comme lue
    $stmt_check = $pdo->prepare("SELECT id FROM notes_service_lus WHERE note_id = ? AND user_id = ?");
    $stmt_check->execute([$note_id, $_SESSION['user_id']]);
    
    if (!$stmt_check->fetch()) {
        // Marquer comme lu
        $stmt_lu = $pdo->prepare("INSERT INTO notes_service_lus (note_id, user_id) VALUES (?, ?)");
        $stmt_lu->execute([$note_id, $_SESSION['user_id']]);
    }
    
    // Rediriger vers la même page
    header("Location: admin_dashboard.php?page=notes_service");
    exit;
    break;
// Ajouter ce cas dans le switch($_POST['action'])
case 'traiter_avance':
    $avance_id = $_POST['avance_id'];
    $statut = $_POST['statut'];
    $motif_refus = $_POST['motif_refus'] ?? null;
    
    $stmt = $pdo->prepare("UPDATE avances_salaire SET statut = ?, date_traitement = NOW(), traite_par = ?, motif_refus = ? WHERE id = ?");
    $stmt->execute([$statut, $_SESSION['user_id'], $motif_refus, $avance_id]);
    
    // Récupérer les infos de l'avance pour la notification
    $stmt_info = $pdo->prepare("
        SELECT a.*, u.name, u.id as user_id 
        FROM avances_salaire a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.id = ?
    ");
    $stmt_info->execute([$avance_id]);
    $avance = $stmt_info->fetch();
    
    // Créer une notification pour l'employé
    $message = "Votre demande d'avance de salaire de " . number_format($avance['montant'], 2, ',', ' ') . " DT a été " . 
              ($statut == 'approuve' ? 'approuvée' : 'refusée');
    
    if ($statut == 'refuse' && $motif_refus) {
        $message .= ". Motif : " . $motif_refus;
    }
    
    $stmt_notif = $pdo->prepare("
        INSERT INTO notifications (user_id, message, date_creation) 
        VALUES (?, ?, NOW())
    ");
    $stmt_notif->execute([$avance['user_id'], $message]);
    
    $_SESSION['admin_message'] = "Demande d'avance de salaire " . 
        ($statut == 'approuve' ? 'approuvée' : 'refusée') . 
        " avec succès. L'employé a été notifié.";
    
    header("Location: admin_dashboard.php?page=avances_salaire");
    exit;
    break;

case 'traiter_attestation':
    $attestation_id = $_POST['attestation_id'];
    $type_attestation = $_POST['type_attestation'];
    $statut = $_POST['statut'];
    
    // Déterminer la table en fonction du type d'attestation
    $table = ($type_attestation === 'travail') ? 'attestations_travail' : 'attestations_salaire';
    
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
    
    // Message de confirmation pour l'admin
    $_SESSION['admin_message'] = "Demande d'attestation $type_fr " . 
        ($statut == 'approuve' ? 'approuvée' : 'refusée') . 
        " avec succès. L'employé a été notifié.";
    break;

// Dans la section de traitement des congés
case 'traiter_conge':
    $conge_id = $_POST['conge_id'];
    $statut = $_POST['statut'];
    $avec_solde = $_POST['avec_solde'] ?? 0; // Nouveau champ
    
    $stmt = $pdo->prepare("UPDATE conges SET statut = ?, avec_solde = ?, date_traitement = NOW(), traite_par = ? WHERE id = ?");
    $stmt->execute([$statut, $avec_solde, $_SESSION['user_id'], $conge_id]);
    
    // Récupérer les infos pour la notification
    $stmt_info = $pdo->prepare("SELECT c.*, u.name, u.id as user_id FROM conges c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
    $stmt_info->execute([$conge_id]);
    $conge = $stmt_info->fetch();
    
    $type_solde = $avec_solde ? 'avec solde' : 'sans solde';
    $message = "Votre demande de congé du " . $conge['date_debut'] . " au " . $conge['date_fin'] . " a été " . 
              ($statut == 'approuve' ? "APPROUVÉE ($type_solde)" : "REFUSÉE");
    
    // FORCER la décision de l'admin (écrase toute décision précédente)
    $stmt = $pdo->prepare("UPDATE conges SET statut = ?, date_traitement = NOW(), traite_par = ? WHERE id = ?");
    $stmt->execute([$statut, $admin_id, $conge_id]);
    
    // Récupérer les infos du congé pour la notification
    $stmt_info = $pdo->prepare("
        SELECT c.*, u.name, u.id as user_id 
        FROM conges c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    $stmt_info->execute([$conge_id]);
    $conge = $stmt_info->fetch();
    
    // Notifier l'employé de la décision FINALE de l'admin
    $message = "DÉCISION FINALE - Votre demande de congé du " . $conge['date_debut'] . " au " . $conge['date_fin'] . " a été " . 
              ($statut == 'approuve' ? 'APPROUVÉE' : 'REFUSÉE') . " par l'administration (décision irréversible)";
    
    $stmt_notif = $pdo->prepare("
        INSERT INTO notifications (user_id, message, date_creation) 
        VALUES (?, ?, NOW())
    ");
    $stmt_notif->execute([$conge['user_id'], $message]);
    
    // Notifier aussi le responsable si différent de l'admin
    if ($conge['traite_par'] && $conge['traite_par'] != $admin_id) {
        $message_responsable = "DÉCISION ADMIN - Le congé de " . $conge['employe_nom'] . " a été " . 
                             ($statut == 'approuve' ? 'APPROUVÉ' : 'REFUSÉ') . " par l'administration";
        
        $stmt_notif_resp = $pdo->prepare("
            INSERT INTO notifications (user_id, message, date_creation) 
            VALUES (?, ?, NOW())
        ");
        $stmt_notif_resp->execute([$conge['traite_par'], $message_responsable]);
    }
    
    $_SESSION['admin_message'] = "Décision finale enregistrée - Le congé a été " . 
        ($statut == 'approuve' ? 'APPROUVÉ' : 'REFUSÉ') . " (irréversible)";
    break;

case 'traiter_autorisation':
    $autorisation_id = $_POST['autorisation_id'];
    $statut = $_POST['statut'];
    
    // Mettre à jour le statut de l'autorisation
    $stmt = $pdo->prepare("UPDATE autorisations SET statut = ?, date_traitement = NOW() WHERE id = ?");
    $stmt->execute([$statut, $autorisation_id]);
    
    // Récupérer les infos de l'autorisation pour la notification
    $stmt_info = $pdo->prepare("
        SELECT a.*, u.name 
        FROM autorisations a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.id = ?
    ");
    $stmt_info->execute([$autorisation_id]);
    $autorisation = $stmt_info->fetch();
    
    // Récupération des notifications (MIS À JOUR)
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

    // Créer une notification pour l'employé
    $message = "Votre demande d'autorisation du " . $autorisation['date'] . " a été " . 
              ($statut == 'approuve' ? 'approuvée' : 'refusée');
    
    $stmt_notif = $pdo->prepare("
        INSERT INTO notifications (user_id, message, date_creation) 
        VALUES (?, ?, NOW())
    ");
    $stmt_notif->execute([$autorisation['user_id'], $message]);
    
    // Message de confirmation pour l'admin
    $_SESSION['admin_message'] = "Demande d'autorisation " . 
        ($statut == 'approuve' ? 'approuvée' : 'refusée') . 
        " avec succès. L'employé a été notifié.";
    break;
            }

            // Modifier la ligne de redirection pour inclure le traitement des congés
// Modifier la ligne de redirection pour inclure les crédits
header("Location: admin_dashboard.php?page=" . 
    ($_POST['action'] == 'traiter_autorisation' ? 'autorisations' : 
    ($_POST['action'] == 'traiter_conge' ? 'conges' : 
    ($_POST['action'] == 'traiter_ordre_mission' ? 'ordres_mission' : 
    ($_POST['action'] == 'traiter_avance' ? 'avances_salaire' : 
    ($_POST['action'] == 'traiter_credit' ? 'credits_salaire' : 'utilisateurs'))))));
            
            exit;
        } catch (PDOException $e) {
            $_SESSION['admin_message'] = "Erreur : " . $e->getMessage();
            header("Location: admin_dashboard.php?page=" . ($_POST['action'] == 'traiter_autorisation' ? 'autorisations' : 'utilisateurs'));
            exit;
        }
    }
}

$filterUser = $_GET['user_id'] ?? '';
$filterDateStart = $_GET['date_start'] ?? '';
$filterDateEnd = $_GET['date_end'] ?? '';
$currentPage = $_GET['page'] ?? 'dashboard';

// Récupération de tous les employés pour les filtres
$employees = $pdo->query("SELECT id, name FROM users WHERE role = 'employee'")->fetchAll();

$users = $pdo->query("
    SELECT id, name, email, matricule, role, matiere, date_embauche, is_active 
    FROM users 
    ORDER BY is_active DESC, role, name
")->fetchAll();
$autorisations = $pdo->query("
    SELECT a.*, u.name as employe_nom 
    FROM autorisations a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.date_demande DESC
")->fetchAll();

// Ajouter cette section après la récupération des autorisations
// Récupération des demandes de congé
$conges = $pdo->query("
    SELECT c.*, u.name as employe_nom, u.matiere, r.name as responsable_nom
    FROM conges c 
    JOIN users u ON c.user_id = u.id 
    LEFT JOIN users r ON c.traite_par = r.id
    ORDER BY c.date_demande DESC
")->fetchAll();

// Récupération des statistiques pour les congés
$totalConges = $pdo->query("SELECT COUNT(*) as count FROM conges")->fetch()['count'];
$congesEnAttente = $pdo->query("SELECT COUNT(*) as count FROM conges WHERE statut = 'en_attente'")->fetch()['count'];

// Récupération des statistiques pour le tableau de bord
$totalUsers = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
$totalEmployees = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee'")->fetch()['count'];
$totalAdmins = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch()['count'];
$totalPointages = $pdo->query("SELECT COUNT(*) as count FROM pointages")->fetch()['count'];
$todayPointages = $pdo->query("SELECT COUNT(*) as count FROM pointages WHERE DATE(timestamp) = CURDATE()")->fetch()['count'];
$totalAutorisations = $pdo->query("SELECT COUNT(*) as count FROM autorisations")->fetch()['count'];
$autorisationsEnAttente = $pdo->query("SELECT COUNT(*) as count FROM autorisations WHERE statut = 'en_attente'")->fetch()['count'];
// Récupération des statistiques de messages
// Récupération des statistiques de messages
$totalMessages = $pdo->query("SELECT COUNT(*) as count FROM messages")->fetch()['count'];
$unreadMessages = $pdo->query("SELECT COUNT(*) as count FROM messages WHERE receiver_id = {$_SESSION['user_id']} AND is_read = FALSE")->fetch()['count'];

// Récupération des messages non lus par employé
$unreadMessagesByEmployee = [];
$stmt_unread_by_employee = $pdo->prepare("
    SELECT sender_id, COUNT(*) as unread_count 
    FROM messages 
    WHERE receiver_id = ? AND is_read = FALSE 
    GROUP BY sender_id
");
$stmt_unread_by_employee->execute([$_SESSION['user_id']]);
$unread_counts = $stmt_unread_by_employee->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($employees as &$emp) {
    $emp['unread_count'] = $unread_counts[$emp['id']] ?? 0;
}
unset($emp); // Détruire la référence
// Après la récupération des autorisations, ajoutez:
// Récupération des demandes d'attestation de travail
$attestations_travail = $pdo->query("
    SELECT at.*, u.name as employe_nom 
    FROM attestations_travail at 
    JOIN users u ON at.user_id = u.id 
    ORDER BY at.date_demande DESC
")->fetchAll();

// Récupération des demandes d'attestation de salaire
$attestations_salaire = $pdo->query("
    SELECT asa.*, u.name as employe_nom 
    FROM attestations_salaire asa 
    JOIN users u ON asa.user_id = u.id 
    ORDER BY asa.date_demande DESC
")->fetchAll();

// Récupération des statistiques pour les attestations
$totalAttestationsTravail = $pdo->query("SELECT COUNT(*) as count FROM attestations_travail")->fetch()['count'];
$totalAttestationsSalaire = $pdo->query("SELECT COUNT(*) as count FROM attestations_salaire")->fetch()['count'];
$attestationsTravailEnAttente = $pdo->query("SELECT COUNT(*) as count FROM attestations_travail WHERE statut = 'en_attente'")->fetch()['count'];
$attestationsSalaireEnAttente = $pdo->query("SELECT COUNT(*) as count FROM attestations_salaire WHERE statut = 'en_attente'")->fetch()['count'];

// Récupération des ordres de mission
$ordres_mission = $pdo->query("
    SELECT om.*, u.name as employe_nom, u.matiere, 
           admin.name as admin_traite_par
    FROM ordres_mission om 
    JOIN users u ON om.user_id = u.id 
    LEFT JOIN users admin ON om.traite_par = admin.id
    ORDER BY om.date_demande DESC
")->fetchAll();

// Statistiques des ordres de mission
$totalOrdresMission = $pdo->query("SELECT COUNT(*) as count FROM ordres_mission")->fetch()['count'];
$ordresMissionEnAttente = $pdo->query("SELECT COUNT(*) as count FROM ordres_mission WHERE statut = 'en_attente'")->fetch()['count'];

// Récupération des demandes d'avance de salaire
$avances_salaire = $pdo->query("
    SELECT a.*, u.name as employe_nom, u.matiere,
           admin.name as admin_traite_par
    FROM avances_salaire a 
    JOIN users u ON a.user_id = u.id 
    LEFT JOIN users admin ON a.traite_par = admin.id
    ORDER BY a.date_demande DESC
")->fetchAll();

// Statistiques des avances de salaire
$totalAvancesSalaire = $pdo->query("SELECT COUNT(*) as count FROM avances_salaire")->fetch()['count'];
$avancesSalaireEnAttente = $pdo->query("SELECT COUNT(*) as count FROM avances_salaire WHERE statut = 'en_attente'")->fetch()['count'];

// Mettre à jour la requête des notifications pour inclure les avances de salaire
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
$notifications = $stmt_notifs->fetchAll();

// Récupération des notes de service du PDG
$notes_service = $pdo->query("
    SELECT ns.*, 
           CASE WHEN nsl.id IS NULL THEN 0 ELSE 1 END as deja_lu
    FROM notes_service ns 
    LEFT JOIN notes_service_lus nsl ON ns.id = nsl.note_id AND nsl.user_id = " . $_SESSION['user_id'] . "
    WHERE ns.destinataires IN ('tous', 'admin')
    ORDER BY ns.date_creation DESC
")->fetchAll();

// Statistiques des notes
$totalNotesService = $pdo->query("SELECT COUNT(*) as count FROM notes_service WHERE destinataires IN ('tous', 'admin')")->fetch()['count'];
$notesNonLues = $pdo->query("
    SELECT COUNT(*) as count 
    FROM notes_service ns 
    LEFT JOIN notes_service_lus nsl ON ns.id = nsl.note_id AND nsl.user_id = " . $_SESSION['user_id'] . "
    WHERE nsl.id IS NULL AND ns.destinataires IN ('tous', 'admin')
")->fetch()['count'];
// Après la récupération des employés, ajouter :
// Récupération du PDG pour la messagerie
$pdg = $pdo->query("SELECT id, name FROM users WHERE role = 'pdg'")->fetch();

// Récupération des messages non lus avec le PDG
$unreadMessagesPDG = 0;
if ($pdg) {
    $stmt_unread_pdg = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE");
    $stmt_unread_pdg->execute([$_SESSION['user_id'], $pdg['id']]);
    $unreadMessagesPDG = $stmt_unread_pdg->fetch()['count'];
}

// Récupération des demandes de recrutement (version simplifiée)
$demandes_recrutement = $pdo->query("
    SELECT dr.*, 
           u.name as responsable_nom, 
           u.matiere
    FROM demandes_recrutement dr 
    JOIN users u ON dr.responsable_id = u.id 
    ORDER BY dr.date_demande DESC
")->fetchAll();

// Statistiques des demandes de recrutement
$totalDemandesRecrutement = $pdo->query("SELECT COUNT(*) as count FROM demandes_recrutement")->fetch()['count'];
$demandesRecrutementEnAttente = $pdo->query("SELECT COUNT(*) as count FROM demandes_recrutement WHERE statut = 'en_attente'")->fetch()['count'];

// Récupération des demandes de crédit salaire
$credits_salaire = $pdo->query("
    SELECT c.*, u.name as employe_nom, u.matiere
    FROM credits_salaire c 
    JOIN users u ON c.user_id = u.id 
    ORDER BY c.date_demande DESC
")->fetchAll();


// Statistiques des crédits de salaire
$totalCreditsSalaire = $pdo->query("SELECT COUNT(*) as count FROM credits_salaire")->fetch()['count'];
$creditsSalaireEnAttente = $pdo->query("SELECT COUNT(*) as count FROM credits_salaire WHERE statut = 'en_attente'")->fetch()['count'];
$montantTotalCredits = $pdo->query("SELECT SUM(montant) as total FROM credits_salaire WHERE statut = 'approuve'")->fetch()['total'] ?? 0;

// Mettre à jour la requête des notifications pour inclure les crédits
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
    SELECT 'credit_salaire' as type, CONCAT('Réponse à votre demande de crédit de salaire: ', statut) as message, date_demande as date_creation 
    FROM credits_salaire 
    WHERE user_id = ? AND statut != 'en_attente'
    ORDER BY date_creation DESC
");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$notifications = $stmt_notifs->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Tableau de bord Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""/>
  <style>
    * { box-sizing: border-box; font-family: 'Inter', sans-serif; transition: all 0.3s ease; }
    body {
      margin: 0;
      background: var(--bg-color);
      color: var(--text-color);
      display: flex;
      min-height: 100vh;
    }
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
    .header h1 { margin: 0; font-size: 20px; }
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
    h2 { margin-top: 0; color: var(--button-bg); }
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
    .filters button {
      padding: 10px 16px;
      background: var(--button-bg);
      color: var(--button-text);
      border: none;
      border-radius: 6px;
      cursor: pointer;
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
    .hidden {
      display: none;
    }
    #map {
      height: 500px;
      width: 100%;
      border-radius: 8px;
      margin-top: 20px;
    }
    .map-container {
      position: relative;
    }
    .map-controls {
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 1000;
      background: white;
      padding: 10px;
      border-radius: 5px;
      box-shadow: 0 0 10px rgba(0,0,0,0.2);
    }
    .dark #map {
      filter: brightness(0.8) contrast(1.2);
    }
    /* Styles pour la gestion des utilisateurs */
    .user-actions {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      margin-bottom: 20px;
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
    .btn-danger {
      background: var(--danger);
      color: white;
    }
    .btn-warning {
      background: var(--warning);
      color: white;
    }
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background: var(--card-bg);
      padding: 20px;
      border-radius: 8px;
      width: 100%;
      max-width: 500px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    }
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    .modal-title {
      margin: 0;
      font-size: 1.25rem;
      color: var(--text-color);
    }
    .close {
      font-size: 1.5rem;
      cursor: pointer;
      background: none;
      border: none;
      color: var(--text-color);
    }
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
    .role-badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: 500;
    }
    .role-admin {
      background: #4b6cb7;
      color: white;
    }
    .role-employee {
      background: #2ecc71;
      color: white;
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
    /* Styles pour le tableau de bord */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    .stat-card {
      background: var(--card-bg);
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      text-align: center;
    }
    .stat-card i {
      font-size: 2rem;
      margin-bottom: 10px;
      color: var(--button-bg);
    }
    .stat-card h3 {
      margin: 0 0 10px;
      font-size: 1rem;
      color: var(--text-color);
    }
    .stat-card .number {
      font-size: 2rem;
      font-weight: bold;
      color: var(--button-bg);
    }
    .recent-pointages {
      margin-top: 30px;
    }
    .action-buttons {
      display: flex;
      gap: 5px;
    }
    .action-buttons form {
      margin: 0;
    }
    /* Styles pour la messagerie */
.message {
  margin-bottom: 15px;
}

.text-center {
  text-align: center;
}

#chat-container {
  background: var(--card-bg);
}

#employee-select {
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  background: var(--card-bg);
  color: var(--text-color);
}
.unread-indicator {
    color: #e74c3c;
    margin-left: 5px;
    font-weight: bold;
}

#employee-select option {
    padding: 8px;
}
.work-time-summary {
    margin: 20px 0;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #4b6cb7;
}

.work-time-summary h3 {
    margin: 0;
    color: #4b6cb7;
    font-size: 1.1rem;
}

.work-time-total {
    margin: 10px 0 0 0;
    font-size: 1.2rem;
    font-weight: bold;
    color: #2c3e50;
}
/* Style pour l'alerte personnalisée */
.custom-alert {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    z-index: 10000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    max-width: 400px;
    transition: all 0.3s ease;
    transform: translateX(100%);
    opacity: 0;
}

.custom-alert.show {
    transform: translateX(0);
    opacity: 1;
}

.custom-alert.success {
    background-color: var(--success);
}

.custom-alert.error {
    background-color: var(--danger);
}

.custom-alert.warning {
    background-color: var(--warning);
}

.custom-alert.info {
    background-color: var(--info);
}
/* Styles spécifiques pour la gestion des congés admin */
#conges-section {
    overflow-x: auto;
    margin: 20px 0;
}

#conges-section table {
    min-width: 1200px;
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

#conges-section th {
    background: var(--table-header);
    color: var(--table-header-text);
    padding: 15px 12px;
    font-weight: 600;
    text-align: left;
    position: sticky;
    top: 0;
    z-index: 10;
    white-space: nowrap;
}

#conges-section td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    vertical-align: top;
    word-wrap: break-word;
}

#conges-section tr:nth-child(even) {
    background-color: rgba(0,0,0,0.02);
}

#conges-section tr:hover {
    background-color: rgba(75, 108, 183, 0.05);
}

/* Colonnes avec largeurs fixes */
#conges-section th:nth-child(1), /* Employé */
#conges-section td:nth-child(1) {
    width: 120px;
    min-width: 120px;
}

#conges-section th:nth-child(2), /* Matière */
#conges-section td:nth-child(2) {
    width: 100px;
    min-width: 100px;
}

#conges-section th:nth-child(3), /* Date début */
#conges-section th:nth-child(4), /* Date fin */
#conges-section td:nth-child(3),
#conges-section td:nth-child(4) {
    width: 100px;
    min-width: 100px;
}

#conges-section th:nth-child(5), /* Type */
#conges-section td:nth-child(5) {
    width: 100px;
    min-width: 100px;
}

#conges-section th:nth-child(6), /* Cause */
#conges-section td:nth-child(6) {
    width: 150px;
    min-width: 150px;
    max-width: 200px;
}

#conges-section th:nth-child(7), /* Date demande */
#conges-section td:nth-child(7) {
    width: 120px;
    min-width: 120px;
}

#conges-section th:nth-child(8), /* Statut */
#conges-section td:nth-child(8) {
    width: 100px;
    min-width: 100px;
}

#conges-section th:nth-child(9), /* Traité par */
#conges-section td:nth-child(9) {
    width: 120px;
    min-width: 120px;
}

#conges-section th:nth-child(10), /* Actions */
#conges-section td:nth-child(10) {
    width: 200px;
    min-width: 200px;
    text-align: center;
}

/* Styles responsifs */
@media (max-width: 1400px) {
    #conges-section {
        margin: 15px -20px;
        padding: 0 20px;
    }
    
    #conges-section table {
        font-size: 0.9em;
    }
    
    #conges-section th, 
    #conges-section td {
        padding: 10px 8px;
    }
}

/* Styles pour les états */
.conge-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 500;
    text-transform: uppercase;
}

.conge-approuve {
    background: var(--success);
    color: white;
}

.conge-refuse {
    background: var(--danger);
    color: white;
}

.conge-attente {
    background: var(--warning);
    color: white;
}

/* Styles pour les boutons d'action */
.conge-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: center;
}

.conge-actions form {
    margin: 0;
    width: 100%;
}

.conge-actions .btn {
    width: 100%;
    padding: 8px 12px;
    font-size: 0.85em;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

/* Indicateur de décision admin */
.admin-decision {
    background: linear-gradient(135deg, #4b6cb7, #182848);
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.8em;
    font-weight: 600;
    display: inline-block;
    margin-top: 5px;
}

/* Tooltip pour les textes longs */
.tooltip {
    position: relative;
    cursor: help;
}

.tooltip:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 0.8em;
    white-space: nowrap;
    z-index: 1000;
}

/* Scrollbar personnalisée */
#conges-section::-webkit-scrollbar {
    height: 8px;
}

#conges-section::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

#conges-section::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

#conges-section::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Mode sombre */
body.dark #conges-section table {
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

body.dark #conges-section tr:nth-child(even) {
    background-color: rgba(255,255,255,0.02);
}

body.dark #conges-section tr:hover {
    background-color: rgba(75, 108, 183, 0.1);
}

body.dark #conges-section::-webkit-scrollbar-track {
    background: #2a2a2a;
}

body.dark #conges-section::-webkit-scrollbar-thumb {
    background: #555;
}

body.dark #conges-section::-webkit-scrollbar-thumb:hover {
    background: #666;
}

/* Header fixe pour le tableau */
.table-container {
    position: relative;
    max-height: 600px;
    overflow: auto;
}

.table-header-fixed {
    position: sticky;
    top: 0;
    z-index: 10;
}

/* Styles pour les messages d'information */
.conge-info {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 0.9em;
}

body.dark .conge-info {
    background: #1a237e;
    border-left-color: #3f51b5;
}

/* Badge pour les décisions importantes */
.decision-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.7em;
    font-weight: bold;
    margin-left: 5px;
    vertical-align: middle;
}

.badge-final {
    background: #ff5722;
    color: white;
}

.badge-irreversible {
    background: #9c27b0;
    color: white;
}
/* Styles pour la section Gestion des ordres de mission */
#ordres_mission-section {
    overflow-x: auto;
    margin: 20px 0;
}

#ordres_mission-section table {
    min-width: 1200px;
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    font-size: 0.9em;
}

#ordres_mission-section th {
    background: var(--table-header);
    color: var(--table-header-text);
    padding: 15px 12px;
    font-weight: 600;
    text-align: left;
    position: sticky;
    top: 0;
    z-index: 10;
    white-space: nowrap;
    border-bottom: 2px solid var(--border-color);
}

#ordres_mission-section td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
    word-wrap: break-word;
    line-height: 1.4;
}

#ordres_mission-section tr:nth-child(even) {
    background-color: rgba(0,0,0,0.02);
}

#ordres_mission-section tr:hover {
    background-color: rgba(75, 108, 183, 0.05);
    transition: background-color 0.2s ease;
}

/* Largeurs fixes pour les colonnes pour une meilleure organisation */
#ordres_mission-section th:nth-child(1), /* Employé */
#ordres_mission-section td:nth-child(1) {
    width: 120px;
    min-width: 120px;
    max-width: 120px;
}

#ordres_mission-section th:nth-child(2), /* Poste */
#ordres_mission-section td:nth-child(2) {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
}

#ordres_mission-section th:nth-child(3), /* Période */
#ordres_mission-section td:nth-child(3) {
    width: 150px;
    min-width: 150px;
    max-width: 150px;
}

#ordres_mission-section th:nth-child(4), /* Destination */
#ordres_mission-section td:nth-child(4) {
    width: 140px;
    min-width: 140px;
    max-width: 140px;
}

#ordres_mission-section th:nth-child(5), /* Objet */
#ordres_mission-section td:nth-child(5) {
    width: 200px;
    min-width: 200px;
    max-width: 200px;
}

#ordres_mission-section th:nth-child(6), /* Transport */
#ordres_mission-section td:nth-child(6) {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
}

#ordres_mission-section th:nth-child(7), /* Frais */
#ordres_mission-section td:nth-child(7) {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
    text-align: right;
}

#ordres_mission-section th:nth-child(8), /* Date demande */
#ordres_mission-section td:nth-child(8) {
    width: 120px;
    min-width: 120px;
    max-width: 120px;
}

#ordres_mission-section th:nth-child(9), /* Statut */
#ordres_mission-section td:nth-child(9) {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
    text-align: center;
}

#ordres_mission-section th:nth-child(10), /* Traité par */
#ordres_mission-section td:nth-child(10) {
    width: 120px;
    min-width: 120px;
    max-width: 120px;
}

#ordres_mission-section th:nth-child(11), /* Actions */
#ordres_mission-section td:nth-child(11) {
    width: 180px;
    min-width: 180px;
    max-width: 180px;
    text-align: center;
}

/* Styles pour le contenu spécifique */
.ordre-mission-periode {
    font-weight: 500;
    color: var(--text-color);
}

.ordre-mission-duree {
    font-size: 0.8em;
    color: #666;
    display: block;
    margin-top: 4px;
}

.ordre-mission-objet {
    max-height: 60px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    line-height: 1.4;
}

.ordre-mission-objet-tooltip {
    cursor: help;
    position: relative;
}

.ordre-mission-objet-tooltip:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 0.8em;
    white-space: normal;
    width: 300px;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.ordre-mission-frais {
    font-weight: 500;
    color: #2ecc71;
}

.ordre-mission-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: center;
}

.ordre-mission-actions form {
    margin: 0;
    width: 100%;
}

.ordre-mission-actions .btn {
    width: 100%;
    padding: 8px 12px;
    font-size: 0.85em;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    white-space: nowrap;
}

/* Styles pour les états spécifiques */
.ordre-mission-refuse {
    background-color: rgba(231, 76, 60, 0.05);
}

.ordre-mission-approuve {
    background-color: rgba(46, 204, 113, 0.05);
}

.ordre-mission-en-attente {
    background-color: rgba(243, 156, 18, 0.05);
}

/* Badge pour le statut */
.ordre-mission-statut {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 500;
    text-transform: uppercase;
}

.ordre-mission-approuve-badge {
    background: var(--success);
    color: white;
}

.ordre-mission-refuse-badge {
    background: var(--danger);
    color: white;
}

.ordre-mission-attente-badge {
    background: var(--warning);
    color: white;
}

/* Styles responsifs */
@media (max-width: 1400px) {
    #ordres_mission-section {
        margin: 15px -20px;
        padding: 0 20px;
    }
    
    #ordres_mission-section table {
        font-size: 0.85em;
    }
    
    #ordres_mission-section th, 
    #ordres_mission-section td {
        padding: 10px 8px;
    }
}

@media (max-width: 768px) {
    #ordres_mission-section {
        margin: 10px -15px;
        padding: 0 15px;
    }
    
    #ordres_mission-section table {
        font-size: 0.8em;
        min-width: 1000px;
    }
    
    #ordres_mission-section th, 
    #ordres_mission-section td {
        padding: 8px 6px;
    }
}

/* Scrollbar personnalisée pour le tableau */
#ordres_mission-section::-webkit-scrollbar {
    height: 8px;
}

#ordres_mission-section::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

#ordres_mission-section::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

#ordres_mission-section::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Mode sombre */
body.dark #ordres_mission-section table {
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

body.dark #ordres_mission-section tr:nth-child(even) {
    background-color: rgba(255,255,255,0.02);
}

body.dark #ordres_mission-section tr:hover {
    background-color: rgba(75, 108, 183, 0.1);
}

body.dark .ordre-mission-duree {
    color: #aaa;
}

body.dark #ordres_mission-section::-webkit-scrollbar-track {
    background: #2a2a2a;
}

body.dark #ordres_mission-section::-webkit-scrollbar-thumb {
    background: #555;
}

body.dark #ordres_mission-section::-webkit-scrollbar-thumb:hover {
    background: #666;
}

/* Header fixe pour le tableau */
.ordre-mission-table-container {
    position: relative;
    max-height: 600px;
    overflow: auto;
}

.ordre-mission-table-header-fixed {
    position: sticky;
    top: 0;
    z-index: 10;
}

/* Styles pour les messages d'information */
.ordre-mission-info {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 0.9em;
}

body.dark .ordre-mission-info {
    background: #1a237e;
    border-left-color: #3f51b5;
}

/* Styles pour les indicateurs visuels */
.ordre-mission-priority {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 8px;
}

.ordre-mission-priority-high {
    background-color: #e74c3c;
}

.ordre-mission-priority-medium {
    background-color: #f39c12;
}

.ordre-mission-priority-low {
    background-color: #2ecc71;
}

/* Animation pour les nouvelles lignes */
@keyframes newRowHighlight {
    0% { background-color: rgba(46, 204, 113, 0.3); }
    100% { background-color: transparent; }
}

.ordre-mission-new {
    animation: newRowHighlight 2s ease-in-out;
}
/* Styles pour la section avances de salaire */
.avance-montant {
    font-weight: bold;
    color: #2ecc71;
    font-size: 1.1em;
}

.avance-statistics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.avance-stat-card {
    background: var(--card-bg);
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    border-left: 4px solid #4b6cb7;
}

.avance-stat-number {
    font-size: 1.5em;
    font-weight: bold;
    color: #4b6cb7;
}

.avance-stat-label {
    font-size: 0.9em;
    color: var(--text-color);
    opacity: 0.8;
}
/* Style global du tableau */
#avances_salaire-section table {
    width: 100%;
    max-width: 1200px; /* limite la largeur */
    margin: 0 auto; /* centre le tableau */
    border-collapse: collapse;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    background: #fff;
}

/* En-tête du tableau */
#avances_salaire-section table th {
    background-color: #2c3e50;
    color: #fff;
    padding: 12px 15px;
    text-align: left;
    font-size: 14px;
}

/* Cellules */
#avances_salaire-section table td {
    padding: 10px 15px;
    border-bottom: 1px solid #ddd;
    font-size: 14px;
    word-wrap: break-word;
    max-width: 250px; /* empêche un dépassement des textes longs */
}

/* Alternance des lignes */
#avances_salaire-section table tr:nth-child(even) {
    background-color: #f9f9f9;
}

/* Hover sur une ligne */
#avances_salaire-section table tr:hover {
    background-color: #f1f1f1;
}

/* Boutons dans le tableau */
#avances_salaire-section .action-buttons button {
    font-size: 13px;
    padding: 6px 10px;
    border-radius: 5px;
}

/* Status */
.status-indicator {
    padding: 4px 8px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: bold;
    text-transform: capitalize;
}

.status-present { background: #2ecc71; color: #fff; }
.status-absent { background: #e74c3c; color: #fff; }
.status-waiting { background: #f1c40f; color: #000; }

/* Styles pour la section Gestion des crédits de salaire */
#credits_salaire-section {
    overflow-x: auto;
    margin: 20px 0;
}

#credits_salaire-section table {
    min-width: 1200px;
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    font-size: 0.9em;
}

#credits_salaire-section th {
    background: var(--table-header);
    color: var(--table-header-text);
    padding: 15px 12px;
    font-weight: 600;
    text-align: left;
    position: sticky;
    top: 0;
    z-index: 10;
    white-space: nowrap;
    border-bottom: 2px solid var(--border-color);
}

#credits_salaire-section td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
    word-wrap: break-word;
    line-height: 1.4;
}

#credits_salaire-section tr:nth-child(even) {
    background-color: rgba(0,0,0,0.02);
}

#credits_salaire-section tr:hover {
    background-color: rgba(75, 108, 183, 0.05);
    transition: background-color 0.2s ease;
}

/* Largeurs fixes pour les colonnes pour une meilleure organisation */
#credits_salaire-section th:nth-child(1), /* Employé */
#credits_salaire-section td:nth-child(1) {
    width: 120px;
    min-width: 120px;
    max-width: 120px;
}

#credits_salaire-section th:nth-child(2), /* Poste */
#credits_salaire-section td:nth-child(2) {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
}

#credits_salaire-section th:nth-child(3), /* Montant */
#credits_salaire-section td:nth-child(3) {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
    text-align: right;
}

#credits_salaire-section th:nth-child(4), /* Mensualités */
#credits_salaire-section td:nth-child(4) {
    width: 80px;
    min-width: 80px;
    max-width: 80px;
    text-align: center;
}

#credits_salaire-section th:nth-child(5), /* Montant/mois */
#credits_salaire-section td:nth-child(5) {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
    text-align: right;
}

#credits_salaire-section th:nth-child(6), /* Motif */
#credits_salaire-section td:nth-child(6) {
    width: 150px;
    min-width: 150px;
    max-width: 150px;
}

#credits_salaire-section th:nth-child(7), /* Solde restant */
#credits_salaire-section td:nth-child(7) {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
    text-align: right;
}

#credits_salaire-section th:nth-child(8), /* Date demande */
#credits_salaire-section td:nth-child(8) {
    width: 120px;
    min-width: 120px;
    max-width: 120px;
}

#credits_salaire-section th:nth-child(9), /* Statut */
#credits_salaire-section td:nth-child(9) {
    width: 100px;
    min-width: 100px;
    max-width: 100px;
    text-align: center;
}

#credits_salaire-section th:nth-child(10), /* Traité par */
#credits_salaire-section td:nth-child(10) {
    width: 120px;
    min-width: 120px;
    max-width: 120px;
}

#credits_salaire-section th:nth-child(11), /* Actions */
#credits_salaire-section td:nth-child(11) {
    width: 180px;
    min-width: 180px;
    max-width: 180px;
    text-align: center;
}

/* Styles pour le contenu spécifique */
.credit-montant {
    font-weight: bold;
    color: #2ecc71;
}

.credit-mensualite {
    color: #3498db;
    font-weight: 500;
}

.credit-solde-restant {
    font-weight: 500;
}

.credit-solde-positive {
    color: #2ecc71;
}

.credit-solde-negative {
    color: #e74c3c;
}

.credit-motif {
    max-height: 60px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    line-height: 1.4;
}

.credit-motif-tooltip {
    cursor: help;
    position: relative;
}

.credit-motif-tooltip:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 0.8em;
    white-space: normal;
    width: 300px;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.credit-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: center;
}

.credit-actions form {
    margin: 0;
    width: 100%;
}

.credit-actions .btn {
    width: 100%;
    padding: 8px 12px;
    font-size: 0.85em;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    white-space: nowrap;
}

/* Styles pour les états spécifiques */
.credit-refuse {
    background-color: rgba(231, 76, 60, 0.05);
}

.credit-approuve {
    background-color: rgba(46, 204, 113, 0.05);
}

.credit-en-attente {
    background-color: rgba(243, 156, 18, 0.05);
}

/* Badge pour le statut */
.credit-statut {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 500;
    text-transform: uppercase;
}

.credit-approuve-badge {
    background: var(--success);
    color: white;
}

.credit-refuse-badge {
    background: var(--danger);
    color: white;
}

.credit-attente-badge {
    background: var(--warning);
    color: white;
}

/* Styles responsifs */
@media (max-width: 1400px) {
    #credits_salaire-section {
        margin: 15px -20px;
        padding: 0 20px;
    }
    
    #credits_salaire-section table {
        font-size: 0.85em;
    }
    
    #credits_salaire-section th, 
    #credits_salaire-section td {
        padding: 10px 8px;
    }
}

@media (max-width: 768px) {
    #credits_salaire-section {
        margin: 10px -15px;
        padding: 0 15px;
    }
    
    #credits_salaire-section table {
        font-size: 0.8em;
        min-width: 1000px;
    }
    
    #credits_salaire-section th, 
    #credits_salaire-section td {
        padding: 8px 6px;
    }
}

/* Scrollbar personnalisée pour le tableau */
#credits_salaire-section::-webkit-scrollbar {
    height: 8px;
}

#credits_salaire-section::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

#credits_salaire-section::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

#credits_salaire-section::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Mode sombre */
body.dark #credits_salaire-section table {
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

body.dark #credits_salaire-section tr:nth-child(even) {
    background-color: rgba(255,255,255,0.02);
}

body.dark #credits_salaire-section tr:hover {
    background-color: rgba(75, 108, 183, 0.1);
}

body.dark #credits_salaire-section::-webkit-scrollbar-track {
    background: #2a2a2a;
}

body.dark #credits_salaire-section::-webkit-scrollbar-thumb {
    background: #555;
}

body.dark #credits_salaire-section::-webkit-scrollbar-thumb:hover {
    background: #666;
}

/* Header fixe pour le tableau */
.credit-table-container {
    position: relative;
    max-height: 600px;
    overflow: auto;
}

.credit-table-header-fixed {
    position: sticky;
    top: 0;
    z-index: 10;
}

/* Styles pour les messages d'information */
.credit-info {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 0.9em;
}

body.dark .credit-info {
    background: #1a237e;
    border-left-color: #3f51b5;
}

/* Styles pour les indicateurs visuels */
.credit-priority {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 8px;
}

.credit-priority-high {
    background-color: #e74c3c;
}

.credit-priority-medium {
    background-color: #f39c12;
}

.credit-priority-low {
    background-color: #2ecc71;
}

/* Animation pour les nouvelles lignes */
@keyframes creditNewRowHighlight {
    0% { background-color: rgba(46, 204, 113, 0.3); }
    100% { background-color: transparent; }
}

.credit-new {
    animation: creditNewRowHighlight 2s ease-in-out;
}

/* Styles pour les statistiques des crédits */
.credit-statistics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.credit-stat-card {
    background: var(--card-bg);
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    border-left: 4px solid #4b6cb7;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.credit-stat-number {
    font-size: 1.5em;
    font-weight: bold;
    color: #4b6cb7;
    margin-bottom: 5px;
}

.credit-stat-label {
    font-size: 0.9em;
    color: var(--text-color);
    opacity: 0.8;
}
/* Sidebar avec scroll */
.sidebar {
    width: 250px;            /* largeur de la sidebar */
    height: 100vh;           /* prend toute la hauteur de la fenêtre */
    overflow-y: auto;        /* active le scroll vertical */
    overflow-x: hidden;      /* cache le scroll horizontal */
    background-color: #2c3e50; /* exemple couleur */
    padding: 15px;
}

/* Optionnel : styliser la scrollbar */
.sidebar::-webkit-scrollbar {
    width: 8px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 5px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: #555;
}
/* Conteneur pour le tableau */
#recrutement-section {
  max-width: 100%;
  overflow-x: auto; /* ✅ Ajoute une barre de défilement horizontale si nécessaire */
  padding: 10px;
  box-sizing: border-box;
}

/* Styles du tableau */
#recrutement-section table {
  width: 100%; /* ✅ Le tableau s'adapte à la largeur du conteneur */
  border-collapse: collapse;
  background: #fff;
  border-radius: 10px;
  overflow: hidden;
  min-width: 900px; /* ✅ Largeur minimale pour garder la lisibilité */
}

/* Cellules */
#recrutement-section th, 
#recrutement-section td {
  text-align: left;
  padding: 10px 12px;
  border-bottom: 1px solid #ddd;
  white-space: nowrap; /* ✅ Empêche le texte de casser la mise en page */
}

/* En-têtes */
#recrutement-section th {
  background: #182848;
  color: #fff;
  font-weight: 600;
  position: sticky;
  top: 0;
  z-index: 2;
}

/* Lignes alternées */
#recrutement-section tr:nth-child(even) {
  background-color: #f9f9f9;
}

/* Effet hover */
#recrutement-section tr:hover {
  background-color: #eef3ff;
}

/* Ajustement pour les boutons */
#recrutement-section .action-buttons {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

/* Scroll stylé (optionnel) */
#recrutement-section::-webkit-scrollbar {
  height: 8px;
}
#recrutement-section::-webkit-scrollbar-thumb {
  background: #4b6cb7;
  border-radius: 4px;
}
#recrutement-section::-webkit-scrollbar-track {
  background: #f0f0f0;
}
/* Styles pour les utilisateurs désactivés */
.user-inactive {
    opacity: 0.6;
    background-color: #f8f9fa !important;
}

.user-inactive td {
    color: #6c757d;
}

.status-inactive {
    background: #6c757d;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}
  </style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-header">
    <h2>Menu Administrateur</h2>
  </div>
  <ul class="sidebar-menu">
    <li>
      <a href="?page=dashboard" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
        <i class="fas fa-tachometer-alt"></i> Tableau de bord
      </a>
    </li>
    <li>
      <a href="?page=pointage" class="<?= $currentPage === 'pointage' ? 'active' : '' ?>">
        <i class="fas fa-history"></i> Historique des pointages
      </a>
    </li>
    <li>
      <a href="?page=utilisateurs" class="<?= $currentPage === 'utilisateurs' ? 'active' : '' ?>">
        <i class="fas fa-users-cog"></i> Gestion des utilisateurs
      </a>
    </li>
    <li>
      <a href="?page=conges" class="<?= $currentPage === 'conges' ? 'active' : '' ?>">
        <i class="fas fa-calendar-alt"></i> Gestion des congés
      </a>
    </li>
    <li>
      <a href="?page=autorisations" class="<?= $currentPage === 'autorisations' ? 'active' : '' ?>">
        <i class="fas fa-clipboard-check"></i> Gestion des autorisations
      </a>
    </li>
    <li>
    <a href="?page=attestations" class="<?= $currentPage === 'attestations' ? 'active' : '' ?>">
        <i class="fas fa-file-contract"></i> Gestion des attestations
        <?php if ($attestationsTravailEnAttente + $attestationsSalaireEnAttente > 0): ?>
            <span style="background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;">
                <?= $attestationsTravailEnAttente + $attestationsSalaireEnAttente ?>
            </span>
        <?php endif; ?>
    </a>
</li>
<!-- Dans la sidebar, après le menu Attestations -->
<li>
    <a href="?page=ordres_mission" class="<?= $currentPage === 'ordres_mission' ? 'active' : '' ?>">
        <i class="fas fa-plane"></i> Ordres de Mission
        <?php if ($ordresMissionEnAttente > 0): ?>
            <span style="background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;">
                <?= $ordresMissionEnAttente ?>
            </span>
        <?php endif; ?>
    </a>
</li>
<!-- Dans la sidebar, après le menu Avances de salaire -->
<li>
    <a href="?page=credits_salaire" class="<?= $currentPage === 'credits_salaire' ? 'active' : '' ?>">
        <i class="fas fa-credit-card"></i> Crédits de salaire
        <?php
        // Compter les crédits en attente
        $creditsEnAttente = $pdo->query("SELECT COUNT(*) as count FROM credits_salaire WHERE statut = 'en_attente'")->fetch()['count'];
        if ($creditsEnAttente > 0): ?>
            <span style="background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;">
                <?= $creditsEnAttente ?>
            </span>
        <?php endif; ?>
    </a>
</li>
<!-- Dans la sidebar, après le menu Notes de service -->
<li>
    <a href="?page=recrutement" class="<?= $currentPage === 'recrutement' ? 'active' : '' ?>">
        <i class="fas fa-user-plus"></i> Demandes de recrutement
        <?php if ($demandesRecrutementEnAttente > 0): ?>
            <span style="background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;">
                <?= $demandesRecrutementEnAttente ?>
            </span>
        <?php endif; ?>
    </a>
</li>
    <li>
      <a href="?page=map" class="<?= $currentPage === 'map' ? 'active' : '' ?>">
        <i class="fas fa-map-marked-alt"></i> Carte
      </a>
    </li>
    <!-- Dans la section de navigation de la sidebar, ajouter ceci après le menu Carte -->
<!-- Ajouter l'item Discussion dans la sidebar admin -->
<li>
    <a href="?page=discussion" class="<?= $currentPage === 'discussion' ? 'active' : '' ?>">
        <i class="fas fa-comments"></i> Discussion
        <?php
        // Compter tous les messages non lus (employés + PDG)
        $totalUnread = $unreadMessages + $unreadMessagesPDG;
        if ($totalUnread > 0): ?>
            <span style="background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;"><?= $totalUnread ?></span>
        <?php endif; ?>
    </a>
</li>
<!-- Dans la sidebar, après le menu Ordres de Mission -->
<li>
    <a href="?page=avances_salaire" class="<?= $currentPage === 'avances_salaire' ? 'active' : '' ?>">
        <i class="fas fa-hand-holding-usd"></i> Avances de salaire
        <?php if ($avancesSalaireEnAttente > 0): ?>
            <span style="background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;">
                <?= $avancesSalaireEnAttente ?>
            </span>
        <?php endif; ?>
    </a>
</li>
<!-- Dans la sidebar, après le menu Avances de salaire -->
<li>
    <a href="?page=notes_service" class="<?= $currentPage === 'notes_service' ? 'active' : '' ?>">
        <i class="fas fa-bullhorn"></i> Notes de service PDG
        <?php
        // Compter les notes non lues
        $stmt_notes_non_lues = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM notes_service ns 
            LEFT JOIN notes_service_lus nsl ON ns.id = nsl.note_id AND nsl.user_id = ? 
            WHERE nsl.id IS NULL AND ns.destinataires IN ('tous', 'admin')
        ");
        $stmt_notes_non_lues->execute([$_SESSION['user_id']]);
        $notes_non_lues = $stmt_notes_non_lues->fetch()['count'];
        
        if ($notes_non_lues > 0): ?>
            <span style="background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;">
                <?= $notes_non_lues ?>
            </span>
        <?php endif; ?>
    </a>
</li>
  </ul>
</div>

<div class="main-content">
  <div class="header">
    <h1>Bienvenue <?= htmlspecialchars($name) ?></h1>
    <div>
      <button class="toggle-mode" onclick="toggleMode()">Basculer Thème</button>
      <a class="logout" href="logout.php">Déconnexion</a>
    </div>
  </div>

  <div class="dashboard">
    <!-- Section Tableau de bord -->
    <div id="dashboard-section" class="<?= $currentPage !== 'dashboard' ? 'hidden' : '' ?>">
      <h2>Tableau de bord</h2>
      
      <div class="stats-grid">
        <div class="stat-card">
          <i class="fas fa-users"></i>
          <h3>Total Utilisateurs</h3>
          <div class="number"><?= $totalUsers ?></div>
        </div>
        
        <div class="stat-card">
          <i class="fas fa-user-tie"></i>
          <h3>Employés</h3>
          <div class="number"><?= $totalEmployees ?></div>
        </div>
        
        <div class="stat-card">
          <i class="fas fa-user-shield"></i>
          <h3>Administrateurs</h3>
          <div class="number"><?= $totalAdmins ?></div>
        </div>
        
        <div class="stat-card">
          <i class="fas fa-fingerprint"></i>
          <h3>Total Pointages</h3>
          <div class="number"><?= $totalPointages ?></div>
        </div>
        
        <div class="stat-card">
          <i class="fas fa-calendar-day"></i>
          <h3>Pointages Aujourd'hui</h3>
          <div class="number"><?= $todayPointages ?></div>
        </div>
        
        <div class="stat-card">
          <i class="fas fa-clipboard-check"></i>
          <h3>Demandes d'autorisation</h3>
          <div class="number"><?= $totalAutorisations ?></div>
        </div>
        
        <div class="stat-card">
          <i class="fas fa-clock"></i>
          <h3>Autorisations en attente</h3>
          <div class="number"><?= $autorisationsEnAttente ?></div>
        </div>
      </div>
      <div class="stat-card">
  <i class="fas fa-envelope"></i>
  <h3>Messages échangés</h3>
  <div class="number"><?= $totalMessages ?></div>
</div>

<div class="stat-card">
  <i class="fas fa-envelope-open-text"></i>
  <h3>Messages non lus</h3>
  <div class="number"><?= $unreadMessages ?></div>
</div>
<div class="stat-card">
    <i class="fas fa-file-alt"></i>
    <h3>Attestations travail</h3>
    <div class="number"><?= $totalAttestationsTravail ?></div>
</div>

<div class="stat-card">
    <i class="fas fa-file-invoice-dollar"></i>
    <h3>Attestations salaire</h3>
    <div class="number"><?= $totalAttestationsSalaire ?></div>
</div>

<div class="stat-card">
    <i class="fas fa-clock"></i>
    <h3>Attestations en attente</h3>
    <div class="number"><?= $attestationsTravailEnAttente + $attestationsSalaireEnAttente ?></div>
</div>
      
      <div class="recent-pointages">
        <h3>Derniers pointages</h3>
        <table>
          <tr>
            <th>Nom</th>
            <th>Type</th>
            <th>Date/Heure</th>
          </tr>
          <?php
          $stmt = $pdo->query("
            SELECT u.name, p.type, p.timestamp 
            FROM pointages p 
            JOIN users u ON p.user_id = u.id 
            ORDER BY p.timestamp DESC 
            LIMIT 10
          ");
          
          foreach ($stmt as $row):
          ?>
            <tr>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= htmlspecialchars($row['type']) ?></td>
              <td><?= htmlspecialchars($row['timestamp']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
    <!-- Section Historique des pointages -->
<div id="pointage-section" class="<?= $currentPage !== 'pointage' ? 'hidden' : '' ?>">
  <h2>Historique des pointages</h2>

  <form method="get" class="filters">
    <input type="hidden" name="page" value="pointage">
    
    <select name="user_id">
      <option value="">-- Tous les employés --</option>
      <?php foreach ($employees as $emp): ?>
        <option value="<?= $emp['id'] ?>" <?= ($filterUser == $emp['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($emp['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>De:</label>
    <input type="date" name="date_start" value="<?= htmlspecialchars($filterDateStart) ?>">
    
    <label>À:</label>
    <input type="date" name="date_end" value="<?= htmlspecialchars($filterDateEnd) ?>">

    <button type="submit">Filtrer</button>
    <button type="button" onclick="resetFilters()">Réinitialiser</button>
  </form>

  <?php
  // Calcul du temps de travail total
  $totalWorkTime = 0;
  $lastEntry = null;
  $currentUser = null;
  
  // Récupérer tous les pointages pour le calcul du temps de travail
  $queryWorkTime = "
    SELECT u.name, u.id as user_id, p.type, p.timestamp
    FROM pointages p
    JOIN users u ON p.user_id = u.id
    WHERE 1 ";
  
  $paramsWorkTime = [];
  if ($filterUser) {
      $queryWorkTime .= " AND u.id = ? ";
      $paramsWorkTime[] = $filterUser;
  }
  if ($filterDateStart) {
      $queryWorkTime .= " AND DATE(p.timestamp) >= ? ";
      $paramsWorkTime[] = $filterDateStart;
  }
  if ($filterDateEnd) {
      $queryWorkTime .= " AND DATE(p.timestamp) <= ? ";
      $paramsWorkTime[] = $filterDateEnd;
  }
  
  $queryWorkTime .= " ORDER BY u.id, p.timestamp";
  $stmtWorkTime = $pdo->prepare($queryWorkTime);
  $stmtWorkTime->execute($paramsWorkTime);
  
  // Parcourir tous les pointages pour calculer le temps de travail
  while ($row = $stmtWorkTime->fetch()) {
      if ($row['type'] == 'entrée') {
          $lastEntry = strtotime($row['timestamp']);
          $currentUser = $row['user_id'];
      } elseif ($row['type'] == 'sortie' && $lastEntry !== null && $currentUser == $row['user_id']) {
          $exitTime = strtotime($row['timestamp']);
          $totalWorkTime += ($exitTime - $lastEntry);
          $lastEntry = null;
      }
  }
  
  // Convertir le temps total en heures, minutes, secondes
  $hours = floor($totalWorkTime / 3600);
  $minutes = floor(($totalWorkTime % 3600) / 60);
  $seconds = $totalWorkTime % 60;
  
  // Afficher le temps de travail total
  if ($totalWorkTime > 0): ?>
  <div style="margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-radius: 8px; border-left: 4px solid #4b6cb7;">
    <h3 style="margin: 0; color: #4b6cb7;">Temps de travail total pour la période sélectionnée:</h3>
    <p style="margin: 10px 0 0 0; font-size: 1.2rem; font-weight: bold;">
      <?= sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds) ?>
    </p>
  </div>
  <?php endif; ?>

  <table>
    <tr>
      <th>Nom</th>
      <th>Rôle</th>
      <th>Type</th>
      <th>Date/Heure</th>
    </tr>
    <?php
    $query = "
      SELECT u.name, u.role, p.type, p.timestamp
      FROM pointages p
      JOIN users u ON p.user_id = u.id
      WHERE 1 ";

    $params = [];
    if ($filterUser) {
        $query .= " AND u.id = ? ";
        $params[] = $filterUser;
    }
    if ($filterDateStart) {
        $query .= " AND DATE(p.timestamp) >= ? ";
        $params[] = $filterDateStart;
    }
    if ($filterDateEnd) {
        $query .= " AND DATE(p.timestamp) <= ? ";
        $params[] = $filterDateEnd;
    }

    $query .= " ORDER BY p.timestamp DESC LIMIT 100";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    foreach ($stmt as $row):
    ?>
      <tr>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['role']) ?></td>
        <td><?= htmlspecialchars($row['type']) ?></td>
        <td><?= htmlspecialchars($row['timestamp']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php
// Calcul du temps de travail total (version améliorée)
$totalWorkTime = 0;

// Récupérer tous les pointages groupés par utilisateur et par date
$queryWorkTime = "
    SELECT u.id as user_id, u.name, 
           DATE(p.timestamp) as pointage_date,
           GROUP_CONCAT(p.type ORDER BY p.timestamp SEPARATOR ',') as types,
           GROUP_CONCAT(p.timestamp ORDER BY p.timestamp SEPARATOR ',') as timestamps
    FROM pointages p
    JOIN users u ON p.user_id = u.id
    WHERE 1 ";
  
$paramsWorkTime = [];
if ($filterUser) {
    $queryWorkTime .= " AND u.id = ? ";
    $paramsWorkTime[] = $filterUser;
}
if ($filterDateStart) {
    $queryWorkTime .= " AND DATE(p.timestamp) >= ? ";
    $paramsWorkTime[] = $filterDateStart;
}
if ($filterDateEnd) {
    $queryWorkTime .= " AND DATE(p.timestamp) <= ? ";
    $paramsWorkTime[] = $filterDateEnd;
}

$queryWorkTime .= " GROUP BY u.id, DATE(p.timestamp) ORDER BY u.id, DATE(p.timestamp)";
$stmtWorkTime = $pdo->prepare($queryWorkTime);
$stmtWorkTime->execute($paramsWorkTime);

// Parcourir tous les jours et tous les utilisateurs pour calculer le temps de travail
while ($row = $stmtWorkTime->fetch()) {
    $types = explode(',', $row['types']);
    $timestamps = explode(',', $row['timestamps']);
    
    $entryTime = null;
    
    for ($i = 0; $i < count($types); $i++) {
        if ($types[$i] == 'entrée' && $entryTime === null) {
            $entryTime = strtotime($timestamps[$i]);
        } elseif ($types[$i] == 'sortie' && $entryTime !== null) {
            $exitTime = strtotime($timestamps[$i]);
            $totalWorkTime += ($exitTime - $entryTime);
            $entryTime = null;
        }
    }
}

// Convertir le temps total en heures, minutes, secondes
$hours = floor($totalWorkTime / 3600);
$minutes = floor(($totalWorkTime % 3600) / 60);
$seconds = $totalWorkTime % 60;
?>

<!-- Section Gestion des demandes de recrutement -->
<div id="recrutement-section" class="<?= $currentPage !== 'recrutement' ? 'hidden' : '' ?>">
    <h2>Gestion des demandes de recrutement</h2>
    
    <div class="conge-info">
        <i class="fas fa-info-circle"></i>
        <strong>Information :</strong> En tant qu'administrateur, votre rôle est de vérifier et d'envoyer les demandes de recrutement au PDG pour décision finale.
    </div>
    
    <div class="stats-grid" style="margin-bottom: 30px;">
        <div class="stat-card">
            <i class="fas fa-user-plus"></i>
            <h3>Total demandes</h3>
            <div class="number"><?= $totalDemandesRecrutement ?></div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-clock"></i>
            <h3>En attente</h3>
            <div class="number"><?= $demandesRecrutementEnAttente ?></div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-paper-plane"></i>
            <h3>Envoyées au PDG</h3>
            <div class="number">
                <?php 
                $stmt_envoyees = $pdo->query("SELECT COUNT(*) as count FROM demandes_recrutement WHERE envoye_au_pdg = 1");
                $envoyees_pdg = $stmt_envoyees->fetch()['count'] ?? 0;
                echo $envoyees_pdg;
                ?>
            </div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-chart-bar"></i>
            <h3>Postes différents</h3>
            <div class="number">
                <?php 
                $stmt_postes = $pdo->query("SELECT COUNT(DISTINCT poste) as count FROM demandes_recrutement");
                $postes_differents = $stmt_postes->fetch()['count'] ?? 0;
                echo $postes_differents;
                ?>
            </div>
        </div>
    </div>

    <table>
        <tr>
            <th>Responsable</th>
            <th>Poste</th>
            <th>Service</th>
            <th>Motivation</th>
            <th>Urgence</th>
            <th>Fichier</th>
            <th>Date demande</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($demandes_recrutement as $demande): 
            // Déterminer la classe CSS pour le statut
            $status_class = '';
            $statut_affichage = '';
            
            if ($demande['envoye_au_pdg'] == 1) {
                $status_class = 'status-present';
                $statut_affichage = 'Envoyé au PDG';
            } elseif ($demande['statut'] == 'approuve') {
                $status_class = 'status-present';
                $statut_affichage = 'Approuvé';
            } elseif ($demande['statut'] == 'refuse') {
                $status_class = 'status-absent';
                $statut_affichage = 'Refusé';
            } else {
                $status_class = 'status-waiting';
                $statut_affichage = 'En attente';
            }
        ?>
            <tr>
                <td><?= htmlspecialchars($demande['responsable_nom']) ?></td>
                <td style="font-weight: bold;"><?= htmlspecialchars($demande['poste']) ?></td>
                <td><?= htmlspecialchars($demande['matiere']) ?></td>
                <td title="<?= htmlspecialchars($demande['motivation']) ?>">
                    <?= strlen($demande['motivation']) > 50 ? substr($demande['motivation'], 0, 50) . '...' : htmlspecialchars($demande['motivation']) ?>
                </td>
                <td>
                    <span class="status-indicator 
                        <?= $demande['urgence'] == 'critique' ? 'status-absent' : 
                          ($demande['urgence'] == 'eleve' ? 'status-waiting' : 'status-present') ?>">
                        <?= htmlspecialchars(ucfirst($demande['urgence'])) ?>
                    </span>
                </td>
                <td>
                    <?php if (!empty($demande['fichier_pdf'])): ?>
                        <a href="uploads/recrutement/<?= $demande['fichier_pdf'] ?>" 
                           target="_blank" class="btn btn-primary" style="padding: 5px 10px;">
                            <i class="fas fa-download"></i> PDF
                        </a>
                    <?php else: ?>
                        <span style="color: #999;">Aucun fichier</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($demande['date_demande']) ?></td>
                <td>
                    <span class="status-indicator <?= $status_class ?>">
                        <?= htmlspecialchars($statut_affichage) ?>
                    </span>
                    <?php if ($demande['envoye_au_pdg'] == 1 && $demande['date_envoi_pdg']): ?>
                        <br><small>Envoyé le <?= date('d/m/Y H:i', strtotime($demande['date_envoi_pdg'])) ?></small>
                    <?php endif; ?>
                </td>
                <td class="action-buttons">
                    <?php if ($demande['envoye_au_pdg'] == 0 && $demande['statut'] == 'en_attente'): ?>
                        <!-- Bouton Envoyer au PDG -->
                        <form method="post" onsubmit="return confirmEnvoyerAuPDG(this)" style="margin-bottom: 5px;">
                            <input type="hidden" name="action" value="envoyer_au_pdg">
                            <input type="hidden" name="recrutement_id" value="<?= $demande['id'] ?>">
                            <button type="submit" class="btn btn-primary" style="padding: 8px 12px; width: 100%; background: linear-gradient(135deg, #4b6cb7, #182848);">
                                <i class="fas fa-paper-plane"></i> Envoyer au PDG
                            </button>
                        </form>
                        
                        <!-- Bouton Voir les détails -->
                        <button type="button" class="btn btn-info" style="padding: 8px 12px; width: 100%;" 
                                onclick="afficherDetailsRecrutement(<?= htmlspecialchars(json_encode($demande)) ?>)">
                            <i class="fas fa-eye"></i> Voir détails
                        </button>
                    <?php elseif ($demande['envoye_au_pdg'] == 1): ?>
                        <div style="text-align: center;">
                            <i class="fas fa-check-circle" style="color: #2ecc71; font-size: 1.2em;"></i>
                            <div style="font-size: 0.9em; margin-top: 5px;">
                                Envoyé au PDG
                            </div>
                            <?php if ($demande['date_envoi_pdg']): ?>
                                <div style="font-size: 0.8em; color: #666;">
                                    <?= date('d/m/Y H:i', strtotime($demande['date_envoi_pdg'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center;">
                            <span class="status-indicator <?= $status_class ?>">
                                <?= htmlspecialchars(ucfirst($demande['statut'])) ?>
                            </span>
                            <?php if ($demande['date_traitement']): ?>
                                <br><small>Traité le <?= date('d/m/Y H:i', strtotime($demande['date_traitement'])) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- Modal pour afficher les détails de la demande -->
<div class="modal" id="detailsRecrutementModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Détails de la demande de recrutement</h3>
            <button class="close" onclick="closeDetailsRecrutementModal()">&times;</button>
        </div>
        <div class="modal-body" id="detailsRecrutementContent" style="max-height: 400px; overflow-y: auto;">
            <!-- Le contenu sera chargé dynamiquement -->
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-warning" onclick="closeDetailsRecrutementModal()">Fermer</button>
        </div>
    </div>
</div>

<!-- Modal pour le refus de demande de recrutement -->
<div class="modal" id="refusRecrutementModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Refuser la demande de recrutement</h3>
            <button class="close" onclick="closeRefusRecrutementModal()">&times;</button>
        </div>
        <form id="refusRecrutementForm" method="post">
            <input type="hidden" name="action" value="traiter_recrutement">
            <input type="hidden" name="recrutement_id" id="refusRecrutementId">
            <input type="hidden" name="statut" value="refuse">
            
            <div class="form-group">
                <label for="motif_refus_recrutement">Motif du refus (obligatoire)</label>
                <textarea id="motif_refus_recrutement" name="motif_refus" class="form-control" rows="4" 
                          placeholder="Veuillez indiquer le motif du refus..." required></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-warning" onclick="closeRefusRecrutementModal()">Annuler</button>
                <button type="submit" class="btn btn-danger">Confirmer le refus</button>
            </div>
        </form>
    </div>
</div>
    <!-- Après la section Carte, ajouter cette nouvelle section -->
<!-- Remplacer la section Discussion existante par : -->
<div id="discussion-section" class="<?= $currentPage !== 'discussion' ? 'hidden' : '' ?>">
  <h2>Messagerie</h2>
  
  <div class="filters">
    <select id="contact-select" onchange="loadMessages()">
      <option value="">-- Sélectionner un contact --</option>
      <!-- Option pour discuter avec le PDG -->
      <?php if ($pdg): ?>
        <option value="pdg_<?= $pdg['id'] ?>" data-type="pdg" data-unread="<?= $unreadMessagesPDG ?>">
          <?= htmlspecialchars($pdg['name']) ?>
          <?php if ($unreadMessagesPDG > 0): ?>
            <span style="color: #e74c3c;">(<?= $unreadMessagesPDG ?> non lu(s))</span>
          <?php endif; ?>
        </option>
      <?php endif; ?>
      <!-- Options pour les employés -->
      <optgroup label="Employés">
        <?php foreach ($employees as $emp): ?>
          <option value="emp_<?= $emp['id'] ?>" data-type="employee" data-unread="<?= $emp['unread_count'] ?>">
            <?= htmlspecialchars($emp['name']) ?>
            <?php if ($emp['unread_count'] > 0): ?>
              <span style="color: #e74c3c;">(<?= $emp['unread_count'] ?> non lu(s))</span>
            <?php endif; ?>
          </option>
        <?php endforeach; ?>
      </optgroup>
    </select>
  </div>
  
  <div class="card" style="margin-top: 20px;">
    <div class="card-header">
      <h3 class="card-title">Conversation</h3>
    </div>
    <div class="card-body">
      <div id="chat-container" style="height: 400px; overflow-y: auto; margin-bottom: 20px; border: 1px solid var(--border-color); border-radius: 8px; padding: 15px;">
        <div id="chat-messages"></div>
      </div>
      
      <form id="message-form">
        <input type="hidden" id="receiver-id" name="receiver_id">
        <input type="hidden" id="contact-type" name="contact_type">
        <div class="form-group">
          <textarea class="form-control" id="message-text" rows="3" placeholder="Tapez votre message ici..." required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-paper-plane"></i> Envoyer
        </button>
      </form>
    </div>
  </div>
</div>
<!-- Section Gestion des avances de salaire -->
<div id="avances_salaire-section" class="<?= $currentPage !== 'avances_salaire' ? 'hidden' : '' ?>">
    <h2>Gestion des demandes d'avance de salaire</h2>
    
    <div class="stats-grid" style="margin-bottom: 30px;">
        <div class="stat-card">
            <i class="fas fa-hand-holding-usd"></i>
            <h3>Total demandes d'avance</h3>
            <div class="number"><?= $totalAvancesSalaire ?></div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-clock"></i>
            <h3>En attente de traitement</h3>
            <div class="number"><?= $avancesSalaireEnAttente ?></div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-chart-bar"></i>
            <h3>Montant total demandé</h3>
            <div class="number">
                <?php 
                $stmt_montant_total = $pdo->query("SELECT SUM(montant) as total FROM avances_salaire");
                $montant_total = $stmt_montant_total->fetch()['total'] ?? 0;
                echo number_format($montant_total, 2, ',', ' ') . ' DT';
                ?>
            </div>
        </div>
    </div>

    <table>
        <tr>
            <th>Employé</th>
            <th>Poste</th>
            <th>Montant (DT)</th>
            <th>Date demande</th>
            <th>Motif</th>
            <th>Statut</th>
            <th>Traité par</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($avances_salaire as $avance): 
            $status_class = '';
            if ($avance['statut'] == 'approuve') $status_class = 'status-present';
            elseif ($avance['statut'] == 'refuse') $status_class = 'status-absent';
            else $status_class = 'status-waiting';
        ?>
            <tr>
                <td><?= htmlspecialchars($avance['employe_nom']) ?></td>
                <td><?= htmlspecialchars($avance['matiere']) ?></td>
                <td style="font-weight: bold; color: #2ecc71;">
                    <?= number_format($avance['montant'], 2, ',', ' ') ?> DT
                </td>
                <td><?= htmlspecialchars($avance['date_demande']) ?></td>
                <td title="<?= htmlspecialchars($avance['motif']) ?>">
                    <?= strlen($avance['motif']) > 50 ? substr($avance['motif'], 0, 50) . '...' : htmlspecialchars($avance['motif']) ?>
                </td>
                <td>
                    <span class="status-indicator <?= $status_class ?>">
                        <?= htmlspecialchars($avance['statut']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($avance['admin_traite_par'] ?? 'N/A') ?></td>
                <td class="action-buttons">
                    <?php if ($avance['statut'] == 'en_attente'): ?>
                        <!-- Bouton Approuver -->
                        <form method="post" onsubmit="return confirmTraiterAvance(this, 'approuve')" style="margin-bottom: 5px;">
                            <input type="hidden" name="action" value="traiter_avance">
                            <input type="hidden" name="avance_id" value="<?= $avance['id'] ?>">
                            <input type="hidden" name="statut" value="approuve">
                            <button type="submit" class="btn btn-primary" style="padding: 5px 10px; width: 100%;">
                                <i class="fas fa-check"></i> Approuver
                            </button>
                        </form>
                        
                        <!-- Bouton Refuser avec motif -->
                        <button type="button" class="btn btn-danger" style="padding: 5px 10px; width: 100%;" 
                                onclick="openRefusAvanceModal(<?= $avance['id'] ?>)">
                            <i class="fas fa-times"></i> Refuser
                        </button>
                    <?php else: ?>
                        Traité le <?= htmlspecialchars($avance['date_traitement'] ?? 'N/A') ?>
                        <?php if ($avance['statut'] == 'refuse' && $avance['motif_refus']): ?>
                            <br><small title="<?= htmlspecialchars($avance['motif_refus']) ?>">
                                Motif: <?= strlen($avance['motif_refus']) > 30 ? substr($avance['motif_refus'], 0, 30) . '...' : htmlspecialchars($avance['motif_refus']) ?>
                            </small>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<!-- Modal pour le refus d'avance de salaire -->
<div class="modal" id="refusAvanceModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Refuser la demande d'avance de salaire</h3>
            <button class="close" onclick="closeRefusAvanceModal()">&times;</button>
        </div>
        <form id="refusAvanceForm" method="post">
            <input type="hidden" name="action" value="traiter_avance">
            <input type="hidden" name="avance_id" id="refusAvanceId">
            <input type="hidden" name="statut" value="refuse">
            
            <div class="form-group">
                <label for="motif_refus_avance">Motif du refus (obligatoire)</label>
                <textarea id="motif_refus_avance" name="motif_refus" class="form-control" rows="4" 
                          placeholder="Veuillez indiquer le motif du refus..." required></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-warning" onclick="closeRefusAvanceModal()">Annuler</button>
                <button type="submit" class="btn btn-danger">Confirmer le refus</button>
            </div>
        </form>
    </div>
</div>
<!-- Section Gestion des attestations -->
<div id="attestations-section" class="<?= $currentPage !== 'attestations' ? 'hidden' : '' ?>">
    <h2>Gestion des demandes d'attestation</h2>
    
    <!-- Attestations de travail -->
    <h3>Attestations de travail</h3>
    <table>
        <tr>
            <th>Employé</th>
            <th>Année</th>
            <th>Date demande</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($attestations_travail as $attestation): ?>
            <tr>
                <td><?= htmlspecialchars($attestation['employe_nom']) ?></td>
                <td><?= htmlspecialchars($attestation['annee']) ?></td>
                <td><?= htmlspecialchars($attestation['date_demande']) ?></td>
                <td>
                    <span class="status-indicator 
                        <?= $attestation['statut'] == 'approuve' ? 'status-present' : 
                          ($attestation['statut'] == 'refuse' ? 'status-absent' : 'status-waiting') ?>">
                        <?= htmlspecialchars($attestation['statut']) ?>
                    </span>
                </td>
                <td class="action-buttons">
                    <?php if ($attestation['statut'] == 'en_attente'): ?>
                        <form method="post" onsubmit="return confirmTraiterAttestation(this, 'approuve', 'travail')">
                            <input type="hidden" name="action" value="traiter_attestation">
                            <input type="hidden" name="type_attestation" value="travail">
                            <input type="hidden" name="attestation_id" value="<?= $attestation['id'] ?>">
                            <input type="hidden" name="statut" value="approuve">
                            <button type="submit" class="btn btn-primary" style="padding: 5px 10px;">
                                <i class="fas fa-check"></i> Approuver
                            </button>
                        </form>
                        <form method="post" onsubmit="return confirmTraiterAttestation(this, 'refuse', 'travail')">
                            <input type="hidden" name="action" value="traiter_attestation">
                            <input type="hidden" name="type_attestation" value="travail">
                            <input type="hidden" name="attestation_id" value="<?= $attestation['id'] ?>">
                            <input type="hidden" name="statut" value="refuse">
                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px;">
                                <i class="fas fa-times"></i> Refuser
                            </button>
                        </form>
                    <?php else: ?>
                        Traité le <?= htmlspecialchars($attestation['date_traitement'] ?? 'N/A') ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    
    <!-- Attestations de salaire -->
    <h3 style="margin-top: 30px;">Attestations de salaire</h3>
    <table>
        <tr>
            <th>Employé</th>
            <th>Année</th>
            <th>Date demande</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($attestations_salaire as $attestation): ?>
            <tr>
                <td><?= htmlspecialchars($attestation['employe_nom']) ?></td>
                <td><?= htmlspecialchars($attestation['annee']) ?></td>
                <td><?= htmlspecialchars($attestation['date_demande']) ?></td>
                <td>
                    <span class="status-indicator 
                        <?= $attestation['statut'] == 'approuve' ? 'status-present' : 
                          ($attestation['statut'] == 'refuse' ? 'status-absent' : 'status-waiting') ?>">
                        <?= htmlspecialchars($attestation['statut']) ?>
                    </span>
                </td>
                <td class="action-buttons">
                    <?php if ($attestation['statut'] == 'en_attente'): ?>
                        <form method="post" onsubmit="return confirmTraiterAttestation(this, 'approuve', 'salaire')">
                            <input type="hidden" name="action" value="traiter_attestation">
                            <input type="hidden" name="type_attestation" value="salaire">
                            <input type="hidden" name="attestation_id" value="<?= $attestation['id'] ?>">
                            <input type="hidden" name="statut" value="approuve">
                            <button type="submit" class="btn btn-primary" style="padding: 5px 10px;">
                                <i class="fas fa-check"></i> Approuver
                            </button>
                        </form>
                        <form method="post" onsubmit="return confirmTraiterAttestation(this, 'refuse', 'salaire')">
                            <input type="hidden" name="action" value="traiter_attestation">
                            <input type="hidden" name="type_attestation" value="salaire">
                            <input type="hidden" name="attestation_id" value="<?= $attestation['id'] ?>">
                            <input type="hidden" name="statut" value="refuse">
                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px;">
                                <i class="fas fa-times"></i> Refuser
                            </button>
                        </form>
                    <?php else: ?>
                        Traité le <?= htmlspecialchars($attestation['date_traitement'] ?? 'N/A') ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<!-- Section Gestion des utilisateurs -->
<div id="utilisateurs-section" class="<?= $currentPage !== 'utilisateurs' ? 'hidden' : '' ?>">
  <h2>Gestion des utilisateurs</h2>
  
  <div class="user-actions">
    <button class="btn btn-primary" onclick="openUserModal()">
      <i class="fas fa-plus"></i> Ajouter un utilisateur
    </button>
  </div>

  <!-- Tableau des utilisateurs actifs -->
  <h3 style="margin-top: 30px; color: var(--success);">
    <i class="fas fa-user-check"></i> Utilisateurs actifs
  </h3>
  <table>
    <tr>
      <th>Nom</th>
      <th>Email</th>
      <th>Matricule</th>
      <th>Poste</th>
      <th>Rôle</th>
      <th>Date d'embauche</th>
      <th>Statut</th>
      <th>Actions</th>
    </tr>
    <?php 
    $activeUsers = array_filter($users, function($user) {
        return $user['is_active'];
    });
    
    foreach ($activeUsers as $user): 
    ?>
    <tr>
      <td><?= htmlspecialchars($user['name']) ?></td>
      <td><?= htmlspecialchars($user['email']) ?></td>
      <td><?= htmlspecialchars($user['matricule'] ?? 'N/A') ?></td>
      <td><?= htmlspecialchars($user['matiere'] ?? 'N/A') ?></td>
      <td>
        <span class="role-badge role-<?= $user['role'] ?>">
          <?= 
            $user['role'] === 'admin' ? 'Administrateur' : 
            ($user['role'] === 'employee' ? 'Employé' : 
            ($user['role'] === 'responsable' ? 'Responsable' : 'PDG'))
          ?>
        </span>
      </td>
      <td><?= htmlspecialchars($user['date_embauche'] ?? 'N/A') ?></td>
      <td>
        <span class="status-indicator status-present">
          Actif
        </span>
      </td>
      <td>
        <button class="btn btn-primary" onclick="openUserModal(
          <?= $user['id'] ?>, 
          '<?= htmlspecialchars($user['name']) ?>', 
          '<?= htmlspecialchars($user['email']) ?>', 
          '<?= $user['role'] ?>',
          '<?= htmlspecialchars($user['matricule'] ?? '') ?>',
          '<?= htmlspecialchars($user['matiere'] ?? '') ?>',
          '<?= $user['date_embauche'] ?? '' ?>'
        )">
          <i class="fas fa-edit"></i> Modifier
        </button>
        <?php if ($user['id'] != $_SESSION['user_id']): ?>
          <button class="btn btn-warning" onclick="confirmDeactivate(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')">
            <i class="fas fa-user-slash"></i> Désactiver
          </button>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    
    <?php if (count($activeUsers) === 0): ?>
    <tr>
      <td colspan="8" style="text-align: center; padding: 20px; color: #666;">
        <i class="fas fa-users" style="font-size: 48px; margin-bottom: 10px; display: block; color: #ddd;"></i>
        Aucun utilisateur actif
      </td>
    </tr>
    <?php endif; ?>
  </table>

  <!-- Tableau des utilisateurs inactifs -->
  <h3 style="margin-top: 40px; color: var(--danger);">
    <i class="fas fa-user-slash"></i> Utilisateurs désactivés
  </h3>
  <table style="opacity: 0.7;">
    <tr>
      <th>Nom</th>
      <th>Email</th>
      <th>Matricule</th>
      <th>Poste</th>
      <th>Rôle</th>
      <th>Date d'embauche</th>
      <th>Statut</th>
      <th>Actions</th>
    </tr>
    <?php 
    $inactiveUsers = array_filter($users, function($user) {
        return !$user['is_active'];
    });
    
    foreach ($inactiveUsers as $user): 
    ?>
    <tr class="user-inactive">
      <td><?= htmlspecialchars($user['name']) ?></td>
      <td><?= htmlspecialchars($user['email']) ?></td>
      <td><?= htmlspecialchars($user['matricule'] ?? 'N/A') ?></td>
      <td><?= htmlspecialchars($user['matiere'] ?? 'N/A') ?></td>
      <td>
        <span class="role-badge role-<?= $user['role'] ?>" style="opacity: 0.7;">
          <?= 
            $user['role'] === 'admin' ? 'Administrateur' : 
            ($user['role'] === 'employee' ? 'Employé' : 
            ($user['role'] === 'responsable' ? 'Responsable' : 'PDG'))
          ?>
        </span>
      </td>
      <td><?= htmlspecialchars($user['date_embauche'] ?? 'N/A') ?></td>
      <td>
        <span class="status-indicator status-absent">
          Désactivé
        </span>
      </td>
      <td>
        <button class="btn btn-primary" onclick="openUserModal(
          <?= $user['id'] ?>, 
          '<?= htmlspecialchars($user['name']) ?>', 
          '<?= htmlspecialchars($user['email']) ?>', 
          '<?= $user['role'] ?>',
          '<?= htmlspecialchars($user['matricule'] ?? '') ?>',
          '<?= htmlspecialchars($user['matiere'] ?? '') ?>',
          '<?= $user['date_embauche'] ?? '' ?>'
        )">
          <i class="fas fa-edit"></i> Modifier
        </button>
        <?php if ($user['id'] != $_SESSION['user_id']): ?>
          <button class="btn btn-success" onclick="confirmActivate(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')">
            <i class="fas fa-user-check"></i> Réactiver
          </button>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    
    <?php if (count($inactiveUsers) === 0): ?>
    <tr>
      <td colspan="8" style="text-align: center; padding: 20px; color: #666;">
        <i class="fas fa-user-slash" style="font-size: 48px; margin-bottom: 10px; display: block; color: #ddd;"></i>
        Aucun utilisateur désactivé
      </td>
    </tr>
    <?php endif; ?>
  </table>

  <!-- Statistiques des utilisateurs -->
  <div class="stats-grid" style="margin-top: 30px;">
    <div class="stat-card">
      <i class="fas fa-users"></i>
      <h3>Total utilisateurs</h3>
      <div class="number"><?= $totalUsers ?></div>
    </div>
    
    <div class="stat-card">
      <i class="fas fa-user-check"></i>
      <h3>Utilisateurs actifs</h3>
      <div class="number" style="color: var(--success);"><?= count($activeUsers) ?></div>
    </div>
    
    <div class="stat-card">
      <i class="fas fa-user-slash"></i>
      <h3>Utilisateurs désactivés</h3>
      <div class="number" style="color: var(--danger);"><?= count($inactiveUsers) ?></div>
    </div>
    
    <div class="stat-card">
      <i class="fas fa-user-tie"></i>
      <h3>Employés actifs</h3>
      <div class="number">
        <?php 
        $activeEmployees = array_filter($activeUsers, function($user) {
            return $user['role'] === 'employee';
        });
        echo count($activeEmployees);
        ?>
      </div>
    </div>
  </div>
</div>
    
<div id="conges-section" class="<?= $currentPage !== 'conges' ? 'hidden' : '' ?>">
    <h2>Gestion des demandes de congé</h2>
    
    <table>
        <tr>
            <th>Employé</th>
            <th>Poste</th>
            <th>Date début</th>
            <th>Date fin</th>
            <th>Type</th>
            <th>Cause</th>
            <th>Date demande</th>
            <th>Statut</th>
            <th>Traité par</th>
            <th>Actions (Décision finale)</th>
        </tr>
        <?php foreach ($conges as $conge): ?>
            <tr>
                <td><?= htmlspecialchars($conge['employe_nom']) ?></td>
                <td><?= htmlspecialchars($conge['matiere']) ?></td>
                <td><?= htmlspecialchars($conge['date_debut']) ?></td>
                <td><?= htmlspecialchars($conge['date_fin']) ?></td>
                <td><?= htmlspecialchars($conge['type_conge']) ?></td>
                <td><?= htmlspecialchars($conge['cause']) ?></td>
                <td><?= htmlspecialchars($conge['date_demande']) ?></td>
                <td>
                    <span class="status-indicator 
                        <?= $conge['statut'] == 'approuve' ? 'status-present' : 
                          ($conge['statut'] == 'refuse' ? 'status-absent' : 'status-waiting') ?>">
                        <?= htmlspecialchars($conge['statut']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($conge['responsable_nom'] ?? 'N/A') ?></td>
                <td class="action-buttons">
                    <!-- L'admin peut TOUJOURS modifier la décision, même si déjà traitée -->
                    <form method="post" onsubmit="return confirmTraiterConge(this, 'approuve')" style="display: inline-block;">
                        <input type="hidden" name="action" value="traiter_conge">
                        <input type="hidden" name="conge_id" value="<?= $conge['id'] ?>">
                        <input type="hidden" name="statut" value="approuve">
                        <button type="submit" class="btn btn-primary" style="padding: 5px 10px;">
                            <i class="fas fa-check"></i> Approuver
                        </button>
                    </form>
                    <form method="post" onsubmit="return confirmTraiterConge(this, 'refuse')" style="display: inline-block;">
                        <input type="hidden" name="action" value="traiter_conge">
                        <input type="hidden" name="conge_id" value="<?= $conge['id'] ?>">
                        <input type="hidden" name="statut" value="refuse">
                        <button type="submit" class="btn btn-danger" style="padding: 5px 10px;">
                            <i class="fas fa-times"></i> Refuser
                        </button>
                    </form>
                    <?php if ($conge['statut'] != 'en_attente'): ?>

                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<!-- Section Notes de service PDG -->
<div id="notes_service-section" class="<?= $currentPage !== 'notes_service' ? 'hidden' : '' ?>">
    <h2>Notes de service du PDG</h2>
    
    <div class="stats-grid" style="margin-bottom: 30px;">
        <div class="stat-card">
            <i class="fas fa-bullhorn"></i>
            <h3>Total notes de service</h3>
            <div class="number"><?= $totalNotesService ?></div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-envelope"></i>
            <h3>Notes non lues</h3>
            <div class="number"><?= $notesNonLues ?></div>
        </div>
    </div>

    <?php if (count($notes_service) > 0): ?>
        <div class="notes-container">
            <?php foreach ($notes_service as $note): ?>
                <div class="note-card <?= $note['deja_lu'] ? 'note-lue' : 'note-non-lue' ?>" 
                     style="background: var(--card-bg); border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); <?= !$note['deja_lu'] ? 'border-left: 4px solid var(--gold);' : '' ?>">
                    
                    <div class="note-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <div class="note-title" style="font-size: 1.2em; font-weight: bold; color: var(--gold);">
                            <?= htmlspecialchars($note['titre']) ?>
                            <?php if (!$note['deja_lu']): ?>
                                <span class="new-badge" style="background: var(--danger); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7em; margin-left: 10px;">Nouveau</span>
                            <?php endif; ?>
                        </div>
                        <div class="note-meta" style="font-size: 0.9em; color: #666; text-align: right;">
                            <span class="note-destinataires" style="background: var(--button-bg); color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8em;">
                                <?= htmlspecialchars(ucfirst($note['destinataires'])) ?>
                            </span>
                            <br>
                            <?= date('d/m/Y H:i', strtotime($note['date_creation'])) ?>
                        </div>
                    </div>
                    
                    <div class="note-content" style="line-height: 1.6; margin-bottom: 15px;">
                        <?= nl2br(htmlspecialchars($note['contenu'])) ?>
                    </div>
                    
                    <div class="note-footer" style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 10px; border-top: 1px solid #eee; font-size: 0.9em; color: #666;">
                        <div>
                            <i class="fas fa-user"></i> Par <?= htmlspecialchars($note['auteur_nom']) ?>
                            <?php if ($note['date_modification'] != $note['date_creation']): ?>
                                | <i class="fas fa-edit"></i> Modifiée le <?= date('d/m/Y H:i', strtotime($note['date_modification'])) ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$note['deja_lu']): ?>
                            <form method="post" style="margin: 0;">
                                <input type="hidden" name="action" value="marquer_note_lu">
                                <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                                <button type="submit" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8em;">
                                    <i class="fas fa-check"></i> Marquer comme lu
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="color: var(--success);">
                                <i class="fas fa-check-circle"></i> Lu le <?= date('d/m/Y H:i', strtotime($note['date_lecture'] ?? $note['date_creation'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state" style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-bullhorn" style="font-size: 48px; margin-bottom: 20px; color: #ddd;"></i>
            <h3>Aucune note de service</h3>
            <p>Le PDG n'a pas encore publié de notes de service.</p>
        </div>
    <?php endif; ?>
</div>
<!-- Section Gestion des crédits de salaire -->
<div id="credits_salaire-section" class="<?= $currentPage !== 'credits_salaire' ? 'hidden' : '' ?>">
    <h2>Gestion des demandes de crédit de salaire</h2>
    
    <div class="stats-grid" style="margin-bottom: 30px;">
        <div class="stat-card">
            <i class="fas fa-credit-card"></i>
            <h3>Total demandes de crédit</h3>
            <div class="number"><?= $totalCreditsSalaire ?></div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-clock"></i>
            <h3>En attente de traitement</h3>
            <div class="number"><?= $creditsSalaireEnAttente ?></div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-chart-bar"></i>
            <h3>Montant total approuvé</h3>
            <div class="number">
                <?= number_format($montantTotalCredits, 2, ',', ' ') ?> DT
            </div>
        </div>
    </div>

    <table>
        <tr>
            <th>Employé</th>
            <th>Poste</th>
            <th>Montant (DT)</th>
            <th>Mensualités</th>
            <th>Montant/mois</th>
            <th>Motif</th>
            <th>Solde restant</th>
            <th>Date demande</th>
            <th>Statut</th>
            <th>Traité par</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($credits_salaire as $credit): 
            $status_class = '';
            if ($credit['statut'] == 'approuve') $status_class = 'status-present';
            elseif ($credit['statut'] == 'refuse') $status_class = 'status-absent';
            else $status_class = 'status-waiting';
        ?>
            <tr>
                <td><?= htmlspecialchars($credit['employe_nom']) ?></td>
                <td><?= htmlspecialchars($credit['matiere']) ?></td>
                <td style="font-weight: bold; color: #2ecc71;">
                    <?= number_format($credit['montant'], 2, ',', ' ') ?> DT
                </td>
                <td><?= htmlspecialchars($credit['nombre_mensualites']) ?> mois</td>
                <td><?= number_format($credit['montant_mensualite'], 2, ',', ' ') ?> DT</td>
                <td title="<?= htmlspecialchars($credit['motif']) ?>">
                    <?= strlen($credit['motif']) > 50 ? substr($credit['motif'], 0, 50) . '...' : htmlspecialchars($credit['motif']) ?>
                </td>
                <td style="color: <?= $credit['solde_restant'] > 0 ? '#e74c3c' : '#2ecc71' ?>;">
                    <?= number_format($credit['solde_restant'], 2, ',', ' ') ?> DT
                </td>
                <td><?= htmlspecialchars($credit['date_demande']) ?></td>
                <td>
                    <span class="status-indicator <?= $status_class ?>">
                        <?= htmlspecialchars($credit['statut']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($credit['admin_traite_par'] ?? 'N/A') ?></td>
                <td class="action-buttons">
                    <?php if ($credit['statut'] == 'en_attente'): ?>
                        <!-- Bouton Approuver -->
                        <form method="post" onsubmit="return confirmTraiterCredit(this, 'approuve')" style="margin-bottom: 5px;">
                            <input type="hidden" name="action" value="traiter_credit">
                            <input type="hidden" name="credit_id" value="<?= $credit['id'] ?>">
                            <input type="hidden" name="statut" value="approuve">
                            <button type="submit" class="btn btn-primary" style="padding: 5px 10px; width: 100%;">
                                <i class="fas fa-check"></i> Approuver
                            </button>
                        </form>
                        
                        <!-- Bouton Refuser avec motif -->
                        <button type="button" class="btn btn-danger" style="padding: 5px 10px; width: 100%;" 
                                onclick="openRefusCreditModal(<?= $credit['id'] ?>)">
                            <i class="fas fa-times"></i> Refuser
                        </button>
                    <?php else: ?>
                        Traité le <?= htmlspecialchars($credit['date_traitement'] ?? 'N/A') ?>
                        <?php if ($credit['statut'] == 'refuse' && $credit['motif_refus']): ?>
                            <br><small title="<?= htmlspecialchars($credit['motif_refus']) ?>">
                                Motif: <?= strlen($credit['motif_refus']) > 30 ? substr($credit['motif_refus'], 0, 30) . '...' : htmlspecialchars($credit['motif_refus']) ?>
                            </small>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- Modal pour le refus de crédit -->
<div class="modal" id="refusCreditModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Refuser la demande de crédit</h3>
            <button class="close" onclick="closeRefusCreditModal()">&times;</button>
        </div>
        <form id="refusCreditForm" method="post">
            <input type="hidden" name="action" value="traiter_credit">
            <input type="hidden" name="credit_id" id="refusCreditId">
            <input type="hidden" name="statut" value="refuse">
            
            <div class="form-group">
                <label for="motif_refus_credit">Motif du refus (obligatoire)</label>
                <textarea id="motif_refus_credit" name="motif_refus" class="form-control" rows="4" 
                          placeholder="Veuillez indiquer le motif du refus..." required></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-warning" onclick="closeRefusCreditModal()">Annuler</button>
                <button type="submit" class="btn btn-danger">Confirmer le refus</button>
            </div>
        </form>
    </div>
</div>
<!-- Section Gestion des ordres de mission -->
<div id="ordres_mission-section" class="<?= $currentPage !== 'ordres_mission' ? 'hidden' : '' ?>">
    <h2>Gestion des ordres de mission</h2>
    
    <div class="stats-grid" style="margin-bottom: 30px;">
        <div class="stat-card">
            <i class="fas fa-plane"></i>
            <h3>Total ordres de mission</h3>
            <div class="number"><?= $totalOrdresMission ?></div>
        </div>
        
        <div class="stat-card">
            <i class="fas fa-clock"></i>
            <h3>En attente de traitement</h3>
            <div class="number"><?= $ordresMissionEnAttente ?></div>
        </div>
    </div>

    <table>
        <tr>
            <th>Employé</th>
            <th>Poste</th>
            <th>Période</th>
            <th>Destination</th>
            <th>Objet</th>
            <th>Transport</th>
            <th>Frais estimés</th>
            <th>Date demande</th>
            <th>Statut</th>
            <th>Traité par</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($ordres_mission as $ordre): 
    // Utiliser date_mission pour les deux dates (si c'est une mission d'un jour)
    $date_debut = new DateTime($ordre['date_mission']);
    $date_fin = new DateTime($ordre['date_mission']);
    
    // Ou si vous voulez calculer la durée basée sur les heures
    $heure_depart = new DateTime($ordre['heure_depart']);
    $heure_arrivee = new DateTime($ordre['heure_arrivee']);
    $duree_heures = $heure_depart->diff($heure_arrivee)->h;
    
    // Pour une mission d'un jour, la durée est de 1 jour
    $duree = 1;
?>

            <tr>
                <td><?= htmlspecialchars($ordre['employe_nom']) ?></td>
                <td><?= htmlspecialchars($ordre['matiere']) ?></td>
                <td>
                    <?= $date_debut->format('d/m/Y') ?> - <?= $date_fin->format('d/m/Y') ?><br>
                    <small>(<?= $duree ?> jour(s))</small>
                </td>
                <td><?= htmlspecialchars($ordre['destination']) ?></td>
                <td title="<?= htmlspecialchars($ordre['objet_mission']) ?>">
                    <?= strlen($ordre['objet_mission']) > 50 ? substr($ordre['objet_mission'], 0, 50) . '...' : htmlspecialchars($ordre['objet_mission']) ?>
                </td>
                <td><?= htmlspecialchars($ordre['moyens_transport']) ?></td>
                <td><?= $ordre['frais_estimes'] ? number_format($ordre['frais_estimes'], 2, ',', ' ') . ' DT' : 'N/A' ?></td>
                <td><?= htmlspecialchars($ordre['date_demande']) ?></td>
                <td>
                    <span class="status-indicator 
                        <?= $ordre['statut'] == 'approuve' ? 'status-present' : 
                          ($ordre['statut'] == 'refuse' ? 'status-absent' : 'status-waiting') ?>">
                        <?= htmlspecialchars($ordre['statut']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($ordre['admin_traite_par'] ?? 'N/A') ?></td>
                <td class="action-buttons">
                    <?php if ($ordre['statut'] == 'en_attente'): ?>
                        <!-- Bouton Approuver -->
                        <form method="post" onsubmit="return confirmTraiterOrdreMission(this, 'approuve')" style="margin-bottom: 5px;">
                            <input type="hidden" name="action" value="traiter_ordre_mission">
                            <input type="hidden" name="ordre_mission_id" value="<?= $ordre['id'] ?>">
                            <input type="hidden" name="statut" value="approuve">
                            <button type="submit" class="btn btn-primary" style="padding: 5px 10px; width: 100%;">
                                <i class="fas fa-check"></i> Approuver
                            </button>
                        </form>
                        
                        <!-- Bouton Refuser avec motif -->
                        <button type="button" class="btn btn-danger" style="padding: 5px 10px; width: 100%;" 
                                onclick="openRefusModal(<?= $ordre['id'] ?>)">
                            <i class="fas fa-times"></i> Refuser
                        </button>
                    <?php else: ?>
                        Traité le <?= htmlspecialchars($ordre['date_traitement'] ?? 'N/A') ?>
                        <?php if ($ordre['statut'] == 'refuse' && $ordre['motif_refus']): ?>
                            <br><small title="<?= htmlspecialchars($ordre['motif_refus']) ?>">
                                Motif: <?= strlen($ordre['motif_refus']) > 30 ? substr($ordre['motif_refus'], 0, 30) . '...' : htmlspecialchars($ordre['motif_refus']) ?>
                            </small>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- Modal pour le refus d'ordre de mission -->
<div class="modal" id="refusModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Refuser l'ordre de mission</h3>
            <button class="close" onclick="closeRefusModal()">&times;</button>
        </div>
        <form id="refusForm" method="post">
            <input type="hidden" name="action" value="traiter_ordre_mission">
            <input type="hidden" name="ordre_mission_id" id="refusOrdreMissionId">
            <input type="hidden" name="statut" value="refuse">
            
            <div class="form-group">
                <label for="motif_refus">Motif du refus (obligatoire)</label>
                <textarea id="motif_refus" name="motif_refus" class="form-control" rows="4" 
                          placeholder="Veuillez indiquer le motif du refus..." required></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-warning" onclick="closeRefusModal()">Annuler</button>
                <button type="submit" class="btn btn-danger">Confirmer le refus</button>
            </div>
        </form>
    </div>
</div>
    <!-- Section Gestion des autorisations -->
     <!-- Section Gestion des autorisations -->
<div id="autorisations-section" class="<?= $currentPage !== 'autorisations' ? 'hidden' : '' ?>">
  <h2>Gestion des demandes d'autorisation</h2>
  
  <table>
    <tr>
      <th>Employé</th>
      <th>Date</th>
      <th>Heure sortie</th>
      <th>Heure retour</th>
      <th>Motif</th>
      <th>Date demande</th>
      <th>Statut</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($autorisations as $autorisation): ?>
      <tr>
        <td><?= htmlspecialchars($autorisation['employe_nom']) ?></td>
        <td><?= htmlspecialchars($autorisation['date']) ?></td>
        <td><?= htmlspecialchars($autorisation['heure_sortie']) ?></td>
        <td><?= htmlspecialchars($autorisation['heure_retour']) ?></td>
        <td><?= htmlspecialchars($autorisation['motif']) ?></td>
        <td><?= htmlspecialchars($autorisation['date_demande']) ?></td>
        <td>
          <span class="status-indicator 
            <?= $autorisation['statut'] == 'approuve' ? 'status-present' : 
              ($autorisation['statut'] == 'refuse' ? 'status-absent' : 'status-waiting') ?>">
            <?= htmlspecialchars($autorisation['statut']) ?>
          </span>
        </td>
        <td class="action-buttons">
          <?php if ($autorisation['statut'] == 'en_attente'): ?>
            <form method="post" onsubmit="return confirmTraiterAutorisation(this, 'approuve')">
              <input type="hidden" name="action" value="traiter_autorisation">
              <input type="hidden" name="autorisation_id" value="<?= $autorisation['id'] ?>">
              <input type="hidden" name="statut" value="approuve">
              <button type="submit" class="btn btn-primary" style="padding: 5px 10px;">
                <i class="fas fa-check"></i> Approuver
              </button>
            </form>
            <form method="post" onsubmit="return confirmTraiterAutorisation(this, 'refuse')">
              <input type="hidden" name="action" value="traiter_autorisation">
              <input type="hidden" name="autorisation_id" value="<?= $autorisation['id'] ?>">
              <input type="hidden" name="statut" value="refuse">
              <button type="submit" class="btn btn-danger" style="padding: 5px 10px;">
                <i class="fas fa-times"></i> Refuser
              </button>
            </form>
          <?php else: ?>
            Traité le <?= htmlspecialchars($autorisation['date_traitement']) ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
    

    <!-- Section Carte -->
    <div id="map-section" class="<?= $currentPage !== 'map' ? 'hidden' : '' ?>">
      <h2>Carte des pointages</h2>
      <div class="map-container">
        <div id="map"></div>
        <div class="map-controls">
          <button onclick="locateUser()" title="Localiser ma position">
            <i class="fas fa-location-arrow"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Dans la section du modal utilisateur, ajouter le champ matricule -->
<div class="modal" id="userModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title" id="modalTitle">Nouvel utilisateur</h3>
      <button class="close" onclick="closeModal()">&times;</button>
    </div>
    <form id="userForm" method="post">
      <input type="hidden" name="action" id="formAction" value="create">
      <input type="hidden" name="id" id="userId">
      
      <div class="form-group">
        <label for="name">Nom complet</label>
        <input type="text" id="name" name="name" class="form-control" required>
      </div>
      
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control" required>
      </div>
      
      <!-- AJOUT: Champ Matricule -->
      <div class="form-group">
        <label for="matricule">Matricule</label>
        <input type="text" id="matricule" name="matricule" class="form-control">
        <small>Optionnel - doit être unique</small>
      </div>
      
      <div class="form-group">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" class="form-control">
        <small id="passwordHelp">Laisser vide pour ne pas modifier</small>
      </div>
      
      <div class="form-group">
        <label for="role">Rôle</label>
        <select id="role" name="role" class="form-control" required>
          <option value="employee">Employé</option>
          <option value="admin">Administrateur</option>
          <option value="responsable">Responsable</option>
          <option value="pdg">PDG</option>
        </select>
      </div>
      
      <!-- AJOUT: Champ Matière -->
      <div class="form-group">
        <label for="matiere">Matière/Poste</label>
        <input type="text" id="matiere" name="matiere" class="form-control">
        <small>Poste ou département de l'utilisateur</small>
      </div>
      
      <!-- AJOUT: Champ Date d'embauche -->
      <div class="form-group">
        <label for="date_embauche">Date d'embauche</label>
        <input type="date" id="date_embauche" name="date_embauche" class="form-control">
      </div>
      
      <div class="form-actions">
        <button type="button" class="btn btn-warning" onclick="closeModal()">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

<?php if (!empty($_SESSION['admin_message'])): ?>
<script>
  const toast = document.getElementById('toast');
  toast.textContent = <?= json_encode($_SESSION['admin_message']) ?>;
  toast.classList.add('show');
  setTimeout(() => { toast.classList.remove('show'); }, 3000);
</script>
<?php unset($_SESSION['admin_message']); endif; ?>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
  integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
  crossorigin=""></script>

<script>
// Variables globales pour la carte
let map;
let userMarker;
let pointageMarkers = [];

// Mettre à jour la fonction openUserModal pour inclure les nouveaux champs
function openUserModal(
    id = null, 
    name = '', 
    email = '', 
    role = 'employee', 
    matricule = '', 
    matiere = '', 
    dateEmbauche = ''
) {
  const modal = document.getElementById('userModal');
  const form = document.getElementById('userForm');
  const title = document.getElementById('modalTitle');
  const passwordField = document.getElementById('password');
  const passwordHelp = document.getElementById('passwordHelp');
  
  if (id) {
    // Mode édition
    title.textContent = 'Modifier utilisateur';
    document.getElementById('formAction').value = 'update';
    document.getElementById('userId').value = id;
    document.getElementById('name').value = name;
    document.getElementById('email').value = email;
    document.getElementById('matricule').value = matricule;
    document.getElementById('role').value = role;
    document.getElementById('matiere').value = matiere;
    document.getElementById('date_embauche').value = dateEmbauche;
    
    passwordField.placeholder = 'Nouveau mot de passe';
    passwordHelp.textContent = 'Laisser vide pour ne pas modifier';
    passwordField.required = false;
  } else {
    // Mode création
    title.textContent = 'Nouvel utilisateur';
    document.getElementById('formAction').value = 'create';
    document.getElementById('userId').value = '';
    form.reset();
    
    passwordField.placeholder = 'Mot de passe';
    passwordHelp.textContent = '';
    passwordField.required = true;
  }
  
  modal.style.display = 'flex';
}

function closeModal() {
  document.getElementById('userModal').style.display = 'none';
}

function confirmDelete(id, name) {
  if (confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur "${name}" ?`)) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete';
    form.appendChild(actionInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = id;
    form.appendChild(idInput);
    
    document.body.appendChild(form);
    form.submit();
  }
}

function initMap() {
  // Initialiser la carte avec une vue centrée sur la Tunisie (lat: 34, lng: 9)
  map = L.map('map').setView([34, 9], 7);
  
  // Ajouter le fond de carte OpenStreetMap
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);
  
  // Charger les pointages depuis la base de données
  loadPointagesOnMap();
}

function resetMapView() {
  // Réinitialiser la vue de la carte sur la Tunisie
  map.setView([34, 9], 7);
}

function locateUser() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      position => {
        const { latitude, longitude } = position.coords;
        
        // Supprimer l'ancien marqueur s'il existe
        if (userMarker) {
          map.removeLayer(userMarker);
        }
        
        // Ajouter un nouveau marqueur pour la position de l'utilisateur
        userMarker = L.marker([latitude, longitude], {
          icon: L.divIcon({
            className: 'user-location-marker',
            html: '<i class="fas fa-user" style="color: #3498db; font-size: 24px;"></i>',
            iconSize: [24, 24]
          })
        }).addTo(map)
          .bindPopup('Votre position actuelle');
        
        // Centrer la carte sur la position de l'utilisateur
        map.setView([latitude, longitude], 15);
      },
      error => {
        alert('Impossible de récupérer votre position: ' + error.message);
      }
    );
  } else {
    alert('La géolocalisation n\'est pas supportée par votre navigateur');
  }
}

function toggleMode() {
    document.body.classList.toggle('dark');
    // Optionnel : mémoriser le choix dans le localStorage
    const isDark = document.body.classList.contains('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    
    // Recharger la carte si nécessaire pour appliquer le thème sombre
    if (map && document.getElementById('map-section').style.display !== 'none') {
      setTimeout(() => {
        map.invalidateSize();
      }, 300);
    }
}

// Appliquer le mode au chargement
if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark');
}

// Initialiser la carte uniquement si on est sur la page de la carte
document.addEventListener('DOMContentLoaded', function() {
  if (document.getElementById('map-section').style.display !== 'none') {
    initMap();
  }
  
  // Observer les changements d'onglets pour initialiser/détruire la carte si nécessaire
  const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      if (mutation.attributeName === 'class') {
        const mapSection = document.getElementById('map-section');
        if (mapSection.classList.contains('hidden')) {
          // Si la carte existe et est cachée, on pourrait la détruire pour économiser des ressources
        } else if (mapSection.style.display !== 'none' && !map) {
          initMap();
        }
      }
    });
  });
  
  observer.observe(document.getElementById('map-section'), {
    attributes: true,
    attributeFilter: ['class']
  });
});
// Fonction pour afficher une notification toast
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Afficher les messages de session
<?php if (!empty($_SESSION['admin_message'])): ?>
    showToast(<?= json_encode($_SESSION['admin_message']) ?>);
    <?php unset($_SESSION['admin_message']); ?>
<?php endif; ?>

// Confirmation avant de traiter une autorisation
function confirmTraiterAutorisation(form, action) {
    const statut = action === 'approuve' ? 'approuver' : 'refuser';
    if (confirm(`Êtes-vous sûr de vouloir ${statut} cette demande d'autorisation ?`)) {
        form.submit();
    }
    return false;
}

// Modifier les formulaires d'autorisation pour utiliser la confirmation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form[action=""]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const actionInput = this.querySelector('input[name="statut"]');
            if (actionInput) {
                e.preventDefault();
                confirmTraiterAutorisation(this, actionInput.value);
            }
        });
    });
});
function resetFilters() {
  const url = new URL(window.location);
  url.searchParams.delete('user_id');
  url.searchParams.delete('date_start');
  url.searchParams.delete('date_end');
  window.location.href = url.toString();
}
// Gestion de la messagerie
const messageForm = document.getElementById('message-form');
const messageText = document.getElementById('message-text');
const chatMessages = document.getElementById('chat-messages');
const chatContainer = document.getElementById('chat-container');
const employeeSelect = document.getElementById('employee-select');
const receiverId = document.getElementById('receiver-id');

// Mettre à jour la fonction loadMessages
function loadMessages() {
    const selectedContact = contactSelect.value;
    if (!selectedContact) {
        chatMessages.innerHTML = '<p class="text-center">Veuillez sélectionner un contact pour voir la conversation</p>';
        receiverId.value = '';
        contactType.value = '';
        return;
    }
    
    const [type, id] = selectedContact.split('_');
    receiverId.value = id;
    contactType.value = type;
    
    const url = type === 'pdg' ? 'admin_get_pdg_messages.php' : 'admin_get_messages.php';
    const param = type === 'pdg' ? 'pdg_id' : 'employee_id';
    
    fetch(`${url}?${param}=${id}`)
        .then(response => response.json())
        .then(messages => {
            chatMessages.innerHTML = '';
            
            if (messages.length === 0) {
                chatMessages.innerHTML = '<p class="text-center">Aucun message avec ce contact</p>';
                return;
            }
            
            messages.forEach(message => {
                const messageElement = document.createElement('div');
                messageElement.classList.add('message');
                
                // Style différent selon l'expéditeur
                if (message.sender_id == <?= $_SESSION['user_id'] ?>) {
                    messageElement.style.textAlign = 'right';
                    messageElement.innerHTML = `
                        <div style="background: var(--button-bg); color: white; padding: 8px 12px; border-radius: 12px; display: inline-block; margin-bottom: 8px; max-width: 70%;">
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
        .catch(error => {
            console.error('Erreur:', error);
            chatMessages.innerHTML = '<p class="text-center">Erreur lors du chargement des messages</p>';
        });
}

// Mettre à jour l'envoi de message
if (messageForm) {
    messageForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = messageText.value.trim();
        const receiver = receiverId.value;
        const type = contactType.value;
        
        if (message && receiver) {
            const url = type === 'pdg' ? 'admin_send_pdg_message.php' : 'admin_send_message.php';
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `receiver_id=${receiver}&message=${encodeURIComponent(message)}`
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
        } else if (!receiver) {
            showToast('Veuillez sélectionner un contact', 'warning');
        }
    });
}

// Mettre à jour la vérification des nouveaux messages
function checkNewMessagesByContact() {
    fetch('admin_check_new_messages_by_contact.php')
        .then(response => response.json())
        .then(data => {
            // Mettre à jour les compteurs pour le PDG
            if (data.pdg && data.pdg > 0) {
                const pdgOption = document.querySelector('#contact-select option[value^="pdg_"]');
                if (pdgOption) {
                    let indicator = pdgOption.querySelector('.unread-indicator');
                    if (indicator) indicator.remove();
                    
                    indicator = document.createElement('span');
                    indicator.className = 'unread-indicator';
                    indicator.style = "color: #e74c3c; margin-left: 5px;";
                    indicator.textContent = `(${data.pdg} non lu(s))`;
                    pdgOption.appendChild(indicator);
                    
                    pdgOption.setAttribute('data-unread', data.pdg);
                }
            }
            
            // Mettre à jour les compteurs pour les employés
            Object.keys(data.employees).forEach(employeeId => {
                const count = data.employees[employeeId];
                const option = document.querySelector(`#contact-select option[value="emp_${employeeId}"]`);
                
                if (option) {
                    let indicator = option.querySelector('.unread-indicator');
                    if (indicator) indicator.remove();
                    
                    if (count > 0) {
                        indicator = document.createElement('span');
                        indicator.className = 'unread-indicator';
                        indicator.style = "color: #e74c3c; margin-left: 5px;";
                        indicator.textContent = `(${count} non lu(s))`;
                        option.appendChild(indicator);
                    }
                    
                    option.setAttribute('data-unread', count);
                }
            });
            
            // Mettre à jour le compteur total dans le menu
            updateTotalMessageCounter(data.total);
        })
        .catch(error => console.error('Erreur:', error));
}

// Mettre à jour le compteur total de messages
function updateTotalMessageCounter(total) {
    let counterElement = document.querySelector('a[href="?page=discussion"] span');
    
    if (total > 0) {
        if (!counterElement) {
            const menuItem = document.querySelector('a[href="?page=discussion"]');
            counterElement = document.createElement('span');
            counterElement.style = "background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;";
            menuItem.appendChild(counterElement);
        }
        counterElement.textContent = total;
    } else if (counterElement) {
        counterElement.remove();
    }
}

// Variables globales
const contactSelect = document.getElementById('contact-select');
const contactType = document.getElementById('contact-type');

// Remplacer les appels existants par :
if (document.getElementById('discussion-section').style.display !== 'none') {
    if (contactSelect.value) {
        loadMessages();
        window.chatInterval = setInterval(loadMessages, 5000);
    }
}

contactSelect.addEventListener('change', function() {
    loadMessages();
    
    if (window.chatInterval) {
        clearInterval(window.chatInterval);
    }
    
    if (this.value) {
        window.chatInterval = setInterval(loadMessages, 5000);
    }
});

// Vérifier les nouveaux messages toutes les 10 secondes
setInterval(checkNewMessagesByContact, 10000);
document.addEventListener('DOMContentLoaded', function() {
    checkNewMessagesByContact();
});

// Envoyer un message
if (messageForm) {
    messageForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = messageText.value.trim();
        const receiver = receiverId.value;
        
        if (message && receiver) {
            fetch('admin_send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `receiver_id=${receiver}&message=${encodeURIComponent(message)}`
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
        } else if (!receiver) {
            showToast('Veuillez sélectionner un employé', 'warning');
        }
    });
}

// Actualiser les messages automatiquement si on est sur la page discussion
if (document.getElementById('discussion-section').style.display !== 'none') {
    if (employeeSelect.value) {
        loadMessages();
        window.chatInterval = setInterval(loadMessages, 5000);
    }
}

// Charger les messages quand on change d'employé
employeeSelect.addEventListener('change', function() {
    loadMessages();
    
    // Redémarrer l'intervalle d'actualisation
    if (window.chatInterval) {
        clearInterval(window.chatInterval);
    }
    
    if (this.value) {
        window.chatInterval = setInterval(loadMessages, 5000);
    }
});

// Vérifier les nouveaux messages par employé
function checkNewMessagesByEmployee() {
    fetch('admin_check_new_messages_by_employee.php')
        .then(response => response.json())
        .then(data => {
            // Mettre à jour les compteurs pour chaque employé
            Object.keys(data).forEach(employeeId => {
                const count = data[employeeId];
                const option = document.querySelector(`#employee-select option[value="${employeeId}"]`);
                
                if (option) {
                    // Supprimer l'ancien indicateur de message non lu
                    let indicator = option.querySelector('.unread-indicator');
                    if (indicator) indicator.remove();
                    
                    // Ajouter le nouvel indicateur si nécessaire
                    if (count > 0) {
                        indicator = document.createElement('span');
                        indicator.className = 'unread-indicator';
                        indicator.style = "color: #e74c3c; margin-left: 5px;";
                        indicator.textContent = `(${count} non lu(s))`;
                        option.appendChild(indicator);
                    }
                    
                    // Mettre à jour l'attribut data-unread
                    option.setAttribute('data-unread', count);
                }
            });
        })
        .catch(error => console.error('Erreur:', error));
}

// Vérifier les nouveaux messages par employé toutes les 30 secondes
setInterval(checkNewMessagesByEmployee, 30000);

// Vérifier aussi au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    checkNewMessagesByEmployee();
});

// Mettre à jour le compteur de messages
function updateMessageCounter(count) {
    let counterElement = document.querySelector('a[href="?page=discussion"] span');
    
    if (!counterElement) {
        // Créer le compteur s'il n'existe pas
        const menuItem = document.querySelector('a[href="?page=discussion"]');
        counterElement = document.createElement('span');
        counterElement.style = "background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;";
        menuItem.appendChild(counterElement);
    }
    
    counterElement.textContent = count;
}

// Supprimer le compteur de messages
function removeMessageCounter() {
    let counterElement = document.querySelector('a[href="?page=discussion"] span');
    if (counterElement) {
        counterElement.remove();
    }
}

// Vérifier les nouveaux messages toutes les 30 secondes
setInterval(checkNewMessages, 30000);

// Vérifier aussi au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    checkNewMessages();
});
// Confirmation avant de traiter une attestation
function confirmTraiterAttestation(form, action, type) {
    const statut = action === 'approuve' ? 'approuver' : 'refuser';
    const type_fr = type === 'travail' ? 'de travail' : 'de salaire';
    
    if (confirm(`Êtes-vous sûr de vouloir ${statut} cette demande d'attestation ${type_fr} ?`)) {
        form.submit();
    }
    return false;
}

// Fonction pour afficher une alerte personnalisée
function showCustomAlert(message, type = 'info') {
    // Créer l'élément d'alerte s'il n'existe pas
    let alertDiv = document.getElementById('custom-alert');
    if (!alertDiv) {
        alertDiv = document.createElement('div');
        alertDiv.id = 'custom-alert';
        alertDiv.style = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 400px;
            transition: all 0.3s ease;
            transform: translateX(100%);
            opacity: 0;
        `;
        document.body.appendChild(alertDiv);
    }
    
    // Définir la couleur en fonction du type
    let backgroundColor;
    switch(type) {
        case 'success':
            backgroundColor = '#2ecc71';
            break;
        case 'error':
            backgroundColor = '#e74c3c';
            break;
        case 'warning':
            backgroundColor = '#f39c12';
            break;
        default:
            backgroundColor = '#3498db';
    }
    
    // Configurer l'alerte
    alertDiv.textContent = message;
    alertDiv.style.backgroundColor = backgroundColor;
    alertDiv.style.transform = 'translateX(0)';
    alertDiv.style.opacity = '1';
    
    // Masquer l'alerte après 5 secondes
    setTimeout(() => {
        alertDiv.style.transform = 'translateX(100%)';
        alertDiv.style.opacity = '0';
    }, 5000);
}

// Modifier la fonction showToast pour utiliser l'alerte personnalisée
function showToast(message, type = 'success') {
    showCustomAlert(message, type);
}

// Confirmation avant de traiter un congé
function confirmTraiterConge(form, action) {
    const statut = action === 'approuve' ? 'approuver' : 'refuser';
    if (confirm(`Êtes-vous sûr de vouloir ${statut} cette demande de congé ?`)) {
        form.submit();
    }
    return false;
}
// Fonctions pour la gestion des ordres de mission
function confirmTraiterOrdreMission(form, action) {
    const statut = action === 'approuve' ? 'approuver' : 'refuser';
    if (confirm(`Êtes-vous sûr de vouloir ${statut} cet ordre de mission ?`)) {
        form.submit();
    }
    return false;
}

function openRefusModal(ordreMissionId) {
    document.getElementById('refusOrdreMissionId').value = ordreMissionId;
    document.getElementById('refusModal').style.display = 'flex';
    document.getElementById('motif_refus').focus();
}

function closeRefusModal() {
    document.getElementById('refusModal').style.display = 'none';
    document.getElementById('refusForm').reset();
}

// Validation du formulaire de refus
document.getElementById('refusForm').addEventListener('submit', function(e) {
    const motif = document.getElementById('motif_refus').value.trim();
    if (!motif) {
        e.preventDefault();
        alert('Veuillez indiquer le motif du refus');
        document.getElementById('motif_refus').focus();
    }
});
// Fonctions pour la gestion des avances de salaire
function confirmTraiterAvance(form, action) {
    const statut = action === 'approuve' ? 'approuver' : 'refuser';
    if (confirm(`Êtes-vous sûr de vouloir ${statut} cette demande d'avance de salaire ?`)) {
        form.submit();
    }
    return false;
}

function openRefusAvanceModal(avanceId) {
    document.getElementById('refusAvanceId').value = avanceId;
    document.getElementById('refusAvanceModal').style.display = 'flex';
    document.getElementById('motif_refus_avance').focus();
}

function closeRefusAvanceModal() {
    document.getElementById('refusAvanceModal').style.display = 'none';
    document.getElementById('refusAvanceForm').reset();
}

// Validation du formulaire de refus d'avance
document.getElementById('refusAvanceForm').addEventListener('submit', function(e) {
    const motif = document.getElementById('motif_refus_avance').value.trim();
    if (!motif) {
        e.preventDefault();
        alert('Veuillez indiquer le motif du refus');
        document.getElementById('motif_refus_avance').focus();
    }
});
// Fonctions pour la gestion des demandes de recrutement
function confirmTraiterRecrutement(form, action) {
    const statut = action === 'approuve' ? 'approuver' : 'refuser';
    if (confirm(`Êtes-vous sûr de vouloir ${statut} cette demande de recrutement ?`)) {
        form.submit();
    }
    return false;
}

function openRefusRecrutementModal(recrutementId) {
    document.getElementById('refusRecrutementId').value = recrutementId;
    document.getElementById('refusRecrutementModal').style.display = 'flex';
    document.getElementById('motif_refus_recrutement').focus();
}

function closeRefusRecrutementModal() {
    document.getElementById('refusRecrutementModal').style.display = 'none';
    document.getElementById('refusRecrutementForm').reset();
}

// Validation du formulaire de refus de recrutement
document.getElementById('refusRecrutementForm').addEventListener('submit', function(e) {
    const motif = document.getElementById('motif_refus_recrutement').value.trim();
    if (!motif) {
        e.preventDefault();
        alert('Veuillez indiquer le motif du refus');
        document.getElementById('motif_refus_recrutement').focus();
    }
});

// Fonctions pour la gestion des crédits de salaire
function confirmTraiterCredit(form, action) {
    const statut = action === 'approuve' ? 'approuver' : 'refuser';
    if (confirm(`Êtes-vous sûr de vouloir ${statut} cette demande de crédit ?`)) {
        form.submit();
    }
    return false;
}

function openRefusCreditModal(creditId) {
    document.getElementById('refusCreditId').value = creditId;
    document.getElementById('refusCreditModal').style.display = 'flex';
    document.getElementById('motif_refus_credit').focus();
}

function closeRefusCreditModal() {
    document.getElementById('refusCreditModal').style.display = 'none';
    document.getElementById('refusCreditForm').reset();
}

// Validation du formulaire de refus de crédit
document.getElementById('refusCreditForm').addEventListener('submit', function(e) {
    const motif = document.getElementById('motif_refus_credit').value.trim();
    if (!motif) {
        e.preventDefault();
        alert('Veuillez indiquer le motif du refus');
        document.getElementById('motif_refus_credit').focus();
    }
});
// Fonctions pour la gestion des demandes de recrutement
function confirmEnvoyerAuPDG(form) {
    if (confirm("Êtes-vous sûr de vouloir envoyer cette demande de recrutement au PDG ?\n\nUne fois envoyée, vous ne pourrez plus la modifier.")) {
        form.submit();
    }
    return false;
}

function afficherDetailsRecrutement(demande) {
    const modalContent = document.getElementById('detailsRecrutementContent');
    
    let html = `
        <div style="margin-bottom: 20px;">
            <h4 style="color: #4b6cb7; margin-bottom: 15px;">Informations générales</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                <div><strong>Responsable:</strong> ${demande.responsable_nom}</div>
                <div><strong>Service:</strong> ${demande.matiere}</div>
                <div><strong>Poste demandé:</strong> ${demande.poste}</div>
                <div><strong>Urgence:</strong> <span class="status-indicator ${getUrgenceClass(demande.urgence)}">${demande.urgence}</span></div>
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h4 style="color: #4b6cb7; margin-bottom: 10px;">Motivation</h4>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #4b6cb7;">
                ${demande.motivation}
            </div>
        </div>
    `;
    
    if (demande.fichier_pdf) {
        html += `
            <div style="margin-bottom: 20px;">
                <h4 style="color: #4b6cb7; margin-bottom: 10px;">Document joint</h4>
                <a href="uploads/recrutement/${demande.fichier_pdf}" target="_blank" class="btn btn-primary">
                    <i class="fas fa-download"></i> Télécharger le PDF
                </a>
            </div>
        `;
    }
    
    html += `
        <div style="margin-bottom: 10px;">
            <h4 style="color: #4b6cb7; margin-bottom: 10px;">Dates</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div><strong>Date de demande:</strong> ${demande.date_demande}</div>
                ${demande.date_envoi_pdg ? `<div><strong>Envoyé au PDG:</strong> ${new Date(demande.date_envoi_pdg).toLocaleString()}</div>` : ''}
            </div>
        </div>
    `;
    
    modalContent.innerHTML = html;
    document.getElementById('detailsRecrutementModal').style.display = 'flex';
}

function closeDetailsRecrutementModal() {
    document.getElementById('detailsRecrutementModal').style.display = 'none';
}

function getUrgenceClass(urgence) {
    switch(urgence) {
        case 'critique': return 'status-absent';
        case 'eleve': return 'status-waiting';
        default: return 'status-present';
    }
}
// Confirmation avant de désactiver un utilisateur
function confirmDeactivate(id, name) {
  if (confirm(`Êtes-vous sûr de vouloir désactiver le compte de "${name}" ?\n\nL'utilisateur ne pourra plus se connecter mais ses données seront conservées.`)) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete';
    form.appendChild(actionInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = id;
    form.appendChild(idInput);
    
    document.body.appendChild(form);
    form.submit();
  }
}

// Confirmation avant de réactiver un utilisateur
function confirmActivate(id, name) {
  if (confirm(`Êtes-vous sûr de vouloir réactiver le compte de "${name}" ?\n\nL'utilisateur pourra à nouveau se connecter.`)) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'activate';
    form.appendChild(actionInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = id;
    form.appendChild(idInput);
    
    document.body.appendChild(form);
    form.submit();
  }
}
</script>
</body>
</html>