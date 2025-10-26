<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Vérification du rôle PDG
if ($_SESSION['role'] !== 'pdg') {
    $_SESSION['pdg_message'] = "Accès refusé : Vous n'avez pas les droits PDG";
    header("Location: index.php");
    exit;
}

$name = $_SESSION['name'];
$pdo = new PDO("mysql:host=localhost;dbname=pointage", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'create_note':
                    $stmt = $pdo->prepare("INSERT INTO notes_service (titre, contenu, auteur_id, destinataires) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$_POST['titre'], $_POST['contenu'], $_SESSION['user_id'], $_POST['destinataires']]);
                    $_SESSION['pdg_message'] = "Note de service publiée avec succès";
                    break;
                
                case 'update_note':
                    $stmt = $pdo->prepare("UPDATE notes_service SET titre = ?, contenu = ?, destinataires = ?, date_modification = NOW() WHERE id = ?");
                    $stmt->execute([$_POST['titre'], $_POST['contenu'], $_POST['destinataires'], $_POST['note_id']]);
                    $_SESSION['pdg_message'] = "Note de service mise à jour avec succès";
                    break;
                
                case 'delete_note':
                    $stmt = $pdo->prepare("DELETE FROM notes_service WHERE id = ?");
                    $stmt->execute([$_POST['note_id']]);
                    $_SESSION['pdg_message'] = "Note de service supprimée avec succès";
                    break;
                
                case 'send_message':
                    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $_POST['receiver_id'], $_POST['message']]);
                    $_SESSION['pdg_message'] = "Message envoyé avec succès";
                    break;
                
                // CAS POUR TRAITER LES DEMANDES DE RECRUTEMENT
                case 'traiter_recrutement':
                    $demande_id = $_POST['demande_id'];
                    $action = $_POST['action_recrutement'];
                    $nouveau_statut = $action === 'approuver' ? 'approuve' : 'refuse';
                    $commentaire = $_POST['commentaire'] ?? '';
                    
                    // Mettre à jour la demande avec le PDG qui a traité et la date
                    $stmt = $pdo->prepare("UPDATE demandes_recrutement 
                                         SET statut = ?, date_traitement = NOW(), traite_par_pdg = ?, commentaire_pdg = ?
                                         WHERE id = ?");
                    $stmt->execute([$nouveau_statut, $_SESSION['user_id'], $commentaire, $demande_id]);
                    
                    // Récupérer les infos pour la notification
                    $stmt_info = $pdo->prepare("SELECT dr.*, u.name as responsable_nom, u.id as responsable_id, u.email as responsable_email
                                               FROM demandes_recrutement dr 
                                               JOIN users u ON dr.responsable_id = u.id 
                                               WHERE dr.id = ?");
                    $stmt_info->execute([$demande_id]);
                    $demande = $stmt_info->fetch();
                    
                    // Notifier le responsable
                    $message = "Votre demande de recrutement pour le poste '{$demande['poste']}' a été " . 
                               ($action === 'approuver' ? 'approuvée' : 'refusée') . " par le PDG.";
                    
                    if (!empty($commentaire)) {
                        $message .= "\nCommentaire du PDG : " . $commentaire;
                    }
                    
                    $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, message, date_creation, type) 
                                                VALUES (?, ?, NOW(), 'recrutement')");
                    $stmt_notif->execute([$demande['responsable_id'], $message]);
                    
                    // Optionnel : Envoyer un email
                    // $this->envoyerEmailRecrutement($demande, $action, $commentaire);
                    
                    $_SESSION['pdg_message'] = "Demande de recrutement " . ($action === 'approuver' ? 'approuvée' : 'refusée') . " avec succès";
                    header("Location: pdg.php?page=recrutement");
                    exit;
                    break;
            }

            header("Location: pdg.php?page=" . ($_POST['action'] == 'send_message' ? 'discussion' : 'notes_service'));
            exit;
        } catch (PDOException $e) {
            $_SESSION['pdg_message'] = "Erreur : " . $e->getMessage();
            header("Location: pdg.php?page=" . ($_POST['action'] == 'send_message' ? 'discussion' : 'notes_service'));
            exit;
        }
    }
}

$currentPage = $_GET['page'] ?? 'dashboard';

// Récupération des administrateurs pour la messagerie
$admins = $pdo->query("SELECT id, name FROM users WHERE role = 'admin'")->fetchAll();

// Récupération des notes de service
$notes_service = $pdo->query("
    SELECT ns.*, u.name as auteur_nom 
    FROM notes_service ns 
    JOIN users u ON ns.auteur_id = u.id 
    ORDER BY ns.date_creation DESC
")->fetchAll();

// Récupération des statistiques
$totalEmployees = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee'")->fetch()['count'];
$totalAdmins = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch()['count'];
$totalPointages = $pdo->query("SELECT COUNT(*) as count FROM pointages")->fetch()['count'];
$todayPointages = $pdo->query("SELECT COUNT(*) as count FROM pointages WHERE DATE(timestamp) = CURDATE()")->fetch()['count'];

// Récupération des messages non lus
$unreadMessages = $pdo->query("SELECT COUNT(*) as count FROM messages WHERE receiver_id = {$_SESSION['user_id']} AND is_read = FALSE")->fetch()['count'];

// Récupération des messages non lus par administrateur
$unreadMessagesByAdmin = [];
$stmt_unread_by_admin = $pdo->prepare("
    SELECT sender_id, COUNT(*) as unread_count 
    FROM messages 
    WHERE receiver_id = ? AND is_read = FALSE 
    GROUP BY sender_id
");
$stmt_unread_by_admin->execute([$_SESSION['user_id']]);
$unread_counts = $stmt_unread_by_admin->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($admins as &$admin) {
    $admin['unread_count'] = $unread_counts[$admin['id']] ?? 0;
}
unset($admin);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Tableau de bord PDG</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
      --header-bg: #2c3e50;
      --header-text: white;
      --table-header: #2c3e50;
      --table-header-text: white;
      --card-bg: white;
      --button-bg: #2c3e50;
      --button-text: white;
      --sidebar-bg: #34495e;
      --sidebar-text: #ecf0f1;
      --sidebar-active: #3498db;
      --danger: #e74c3c;
      --warning: #f39c12;
      --success: #2ecc71;
      --gold: #f1c40f;
    }
    body.dark {
      --bg-color: #1e1e1e;
      --text-color: #f0f0f0;
      --header-bg: #1a252f;
      --header-text: #f0f0f0;
      --table-header: #2c3e50;
      --table-header-text: #f0f0f0;
      --card-bg: #2c2c2c;
      --button-bg: #34495e;
      --button-text: #f0f0f0;
      --sidebar-bg: #2c3e50;
      --sidebar-text: #ecf0f1;
      --sidebar-active: #3498db;
      --danger: #c0392b;
      --warning: #d35400;
      --success: #27ae60;
      --gold: #f39c12;
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
      color: var(--gold);
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
    h2 { margin-top: 0; color: var(--gold); }
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
    .hidden {
      display: none;
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
    .btn-success {
      background: var(--success);
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
      color: var(--gold);
    }
    .stat-card h3 {
      margin: 0 0 10px;
      font-size: 1rem;
      color: var(--text-color);
    }
    .stat-card .number {
      font-size: 2rem;
      font-weight: bold;
      color: var(--gold);
    }
    .action-buttons {
      display: flex;
      gap: 5px;
    }
    .note-card {
      background: var(--card-bg);
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .note-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      border-bottom: 1px solid #eee;
      padding-bottom: 10px;
    }
    .note-title {
      font-size: 1.2em;
      font-weight: bold;
      color: var(--gold);
    }
    .note-meta {
      font-size: 0.9em;
      color: #666;
      text-align: right;
    }
    .note-destinataires {
      background: var(--button-bg);
      color: white;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 0.8em;
    }
    .note-content {
      line-height: 1.6;
      margin-bottom: 15px;
    }
    .note-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 15px;
      padding-top: 10px;
      border-top: 1px solid #eee;
      font-size: 0.9em;
      color: #666;
    }
    .new-badge {
      background: var(--danger);
      color: white;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 0.7em;
      margin-left: 10px;
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
    /* Styles pour la messagerie */
    .filters {
      display: flex;
      gap: 15px;
      margin: 20px 0;
      flex-wrap: wrap;
    }
    .filters select {
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
      background: var(--card-bg);
      color: var(--text-color);
    }
    .card {
      background: var(--card-bg);
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }
    .card-header {
      padding: 15px 20px;
      border-bottom: 1px solid #eee;
    }
    .card-title {
      margin: 0;
      font-size: 1.1rem;
      color: var(--text-color);
    }
    .card-body {
      padding: 20px;
    }
    .message {
      margin-bottom: 15px;
    }
    .text-center {
      text-align: center;
    }
    .unread-indicator {
      color: #e74c3c;
      margin-left: 5px;
      font-weight: bold;
    }

    /* Styles pour les demandes de recrutement */
.status-indicator {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 10px;
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

.urgence-critique {
    color: var(--danger);
    font-weight: bold;
}

.urgence-eleve {
    color: var(--warning);
    font-weight: bold;
}

.note-card .form-group {
    margin-bottom: 15px;
}

.note-card .form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--text-color);
}

.lu-badge {
    color: var(--success);
    font-size: 0.9em;
    font-style: italic;
}
  </style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-header">
    <h2>Menu PDG</h2>
  </div>
  <ul class="sidebar-menu">
    <li>
      <a href="?page=dashboard" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
        <i class="fas fa-tachometer-alt"></i> Tableau de bord
      </a>
    </li>
    <li>
      <a href="?page=notes_service" class="<?= $currentPage === 'notes_service' ? 'active' : '' ?>">
        <i class="fas fa-bullhorn"></i> Notes de service
      </a>
    </li>
    <!-- Dans la sidebar du PDG, ajouter après "Discussion avec admins" -->
<li>
    <a href="?page=recrutement" class="<?= $currentPage === 'recrutement' ? 'active' : '' ?>">
        <i class="fas fa-user-plus"></i> Demandes de recrutement
        <?php
        // Compter les demandes en attente
        $stmt_demandes_attente = $pdo->query("SELECT COUNT(*) as count FROM demandes_recrutement WHERE statut = 'en_attente'");
        $demandes_attente = $stmt_demandes_attente->fetch()['count'];
        if ($demandes_attente > 0): ?>
            <span style="background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;">
                <?= $demandes_attente ?>
            </span>
        <?php endif; ?>
    </a>
</li>
    <li>
      <a href="?page=discussion" class="<?= $currentPage === 'discussion' ? 'active' : '' ?>">
        <i class="fas fa-comments"></i> Discussion avec admins
        <?php if ($unreadMessages > 0): ?>
          <span style="background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; margin-left: 8px;">
            <?= $unreadMessages ?>
          </span>
        <?php endif; ?>
      </a>
    </li>
    <li>
    <a href="?page=historique_pointages" class="<?= $currentPage === 'historique_pointages' ? 'active' : '' ?>">
        <i class="fas fa-history"></i> Historique des pointages
    </a>
</li>
  </ul>
</div>

<div class="main-content">
  <div class="header">
    <h1>Bienvenue <?= htmlspecialchars($name) ?> (PDG)</h1>
    <div>
      <button class="toggle-mode" onclick="toggleMode()">Basculer Thème</button>
      <a class="logout" href="logout.php">Déconnexion</a>
    </div>
  </div>

  <div class="dashboard">
    <!-- Section Tableau de bord -->
    <div id="dashboard-section" class="<?= $currentPage !== 'dashboard' ? 'hidden' : '' ?>">
      <h2>Tableau de bord PDG</h2>
      
      <div class="stats-grid">
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
          <i class="fas fa-envelope"></i>
          <h3>Messages non lus</h3>
          <div class="number"><?= $unreadMessages ?></div>
        </div>
      </div>
    </div>
    <!-- Section Historique des pointages -->
<div id="historique_pointages-section" class="<?= $currentPage !== 'historique_pointages' ? 'hidden' : '' ?>">
    <h2>📊 Historique des pointages</h2>
    
    <div class="filters">
        <select id="filter-user" onchange="filtrerPointages()">
            <option value="">Tous les utilisateurs</option>
            <?php
            $users = $pdo->query("SELECT id, name, role FROM users ORDER BY role, name")->fetchAll();
            foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>">
                    <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        
        <select id="filter-date" onchange="filtrerPointages()">
            <option value="">Toutes les dates</option>
            <option value="today">Aujourd'hui</option>
            <option value="yesterday">Hier</option>
            <option value="week">Cette semaine</option>
            <option value="month">Ce mois</option>
        </select>
        
        <input type="date" id="filter-date-custom" onchange="filtrerPointages()" style="padding: 8px; border: 1px solid #ccc; border-radius: 6px;">
    </div>

    <?php
    // Récupération de l'historique des pointages
    $sql_pointages = "SELECT p.*, u.name as user_name, u.role as user_role 
                     FROM pointages p 
                     JOIN users u ON p.user_id = u.id 
                     ORDER BY p.timestamp DESC 
                     LIMIT 100";
    
    $stmt_pointages = $pdo->query($sql_pointages);
    $historique_pointages = $stmt_pointages->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (count($historique_pointages) > 0): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Type</th>
                        <th>Date et Heure</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="pointages-body">
                    <?php foreach ($historique_pointages as $pointage): ?>
                        <tr data-user="<?= $pointage['user_id'] ?>" data-date="<?= date('Y-m-d', strtotime($pointage['timestamp'])) ?>">
                            <td>
                                <strong><?= htmlspecialchars($pointage['user_name']) ?></strong>
                            </td>
                            <td>
                                <span class="status-indicator 
                                    <?= $pointage['user_role'] == 'pdg' ? 'status-present' : 
                                      ($pointage['user_role'] == 'admin' ? 'status-waiting' : 
                                      ($pointage['user_role'] == 'responsable' ? 'status-absent' : '')) ?>">
                                    <?= htmlspecialchars(ucfirst($pointage['user_role'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-indicator 
                                    <?= $pointage['type'] == 'entrée' ? 'status-present' : 'status-absent' ?>">
                                    <?= htmlspecialchars(ucfirst($pointage['type'])) ?>
                                </span>
                            </td>
                            <td>
                                <?= date('d/m/Y H:i:s', strtotime($pointage['timestamp'])) ?>
                            </td>
                            <td>
                                <button class="btn btn-warning" onclick="voirDetailsPointage(<?= $pointage['id'] ?>)">
                                    <i class="fas fa-eye"></i> Détails
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-center" style="margin-top: 20px;">
            <button class="btn btn-primary" onclick="exporterPointages()">
                <i class="fas fa-download"></i> Exporter en PDF
            </button>
        </div>
    <?php else: ?>
        <div class="text-center" style="padding: 40px; color: #666;">
            <i class="fas fa-history" style="font-size: 48px; margin-bottom: 20px; color: #ddd;"></i>
            <h3>Aucun pointage enregistré</h3>
            <p>Les pointages apparaîtront ici lorsqu'ils seront effectués par les utilisateurs.</p>
        </div>
    <?php endif; ?>
</div>

    <!-- Section Notes de service -->
    <div id="notes_service-section" class="<?= $currentPage !== 'notes_service' ? 'hidden' : '' ?>">
      <h2>Gestion des notes de service</h2>
      
      <div class="action-buttons" style="margin-bottom: 20px;">
        <button class="btn btn-primary" onclick="openNoteModal()">
          <i class="fas fa-plus"></i> Nouvelle note de service
        </button>
      </div>
      
      <?php if (count($notes_service) > 0): ?>
        <div class="notes-container">
          <?php foreach ($notes_service as $note): ?>
            <div class="note-card">
              <div class="note-header">
                <div class="note-title">
                  <?= htmlspecialchars($note['titre']) ?>
                </div>
                <div class="note-meta">
                  <span class="note-destinataires">
                    <?= htmlspecialchars(ucfirst($note['destinataires'])) ?>
                  </span>
                  <br>
                  <?= date('d/m/Y H:i', strtotime($note['date_creation'])) ?>
                </div>
              </div>
              
              <div class="note-content">
                <?= nl2br(htmlspecialchars($note['contenu'])) ?>
              </div>
              
              <div class="note-footer">
                <div>
                  <i class="fas fa-user"></i> Par <?= htmlspecialchars($note['auteur_nom']) ?>
                  <?php if ($note['date_modification'] != $note['date_creation']): ?>
                    | <i class="fas fa-edit"></i> Modifiée le <?= date('d/m/Y H:i', strtotime($note['date_modification'])) ?>
                  <?php endif; ?>
                </div>
                
                <div class="action-buttons">
                  <button class="btn btn-primary" onclick="openNoteModal(<?= $note['id'] ?>, '<?= htmlspecialchars($note['titre']) ?>', '<?= htmlspecialchars($note['contenu']) ?>', '<?= $note['destinataires'] ?>')">
                    <i class="fas fa-edit"></i> Modifier
                  </button>
                  <button class="btn btn-danger" onclick="confirmDeleteNote(<?= $note['id'] ?>, '<?= htmlspecialchars($note['titre']) ?>')">
                    <i class="fas fa-trash"></i> Supprimer
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center" style="padding: 40px; color: #666;">
          <i class="fas fa-bullhorn" style="font-size: 48px; margin-bottom: 20px; color: #ddd;"></i>
          <h3>Aucune note de service</h3>
          <p>Créez votre première note de service pour communiquer avec votre équipe.</p>
        </div>
      <?php endif; ?>
    </div>
<!-- Section Demandes de recrutement -->
<!-- Section Demandes de recrutement -->
<div id="recrutement-section" class="<?= $currentPage !== 'recrutement' ? 'hidden' : '' ?>">
    <h2>📄 Demandes de recrutement</h2>
    
    <div class="filters">
        <select id="filter-statut" onchange="filtrerDemandes()">
            <option value="">Tous les statuts</option>
            <option value="en_attente">En attente</option>
            <option value="approuve">Approuvé</option>
            <option value="refuse">Refusé</option>
        </select>
        
        <select id="filter-urgence" onchange="filtrerDemandes()">
            <option value="">Tous les niveaux d'urgence</option>
            <option value="normal">Normal</option>
            <option value="eleve">Élevé</option>
            <option value="critique">Critique</option>
        </select>
    </div>

    <?php
    // Récupération des demandes de recrutement avec informations du PDG traitant
    $sql_demandes = "SELECT dr.*, u.name as responsable_nom, pdg.name as pdg_nom 
                    FROM demandes_recrutement dr 
                    JOIN users u ON dr.responsable_id = u.id 
                    LEFT JOIN users pdg ON dr.traite_par_pdg = pdg.id
                    ORDER BY 
                        CASE 
                            WHEN dr.statut = 'en_attente' THEN 1
                            WHEN dr.urgence = 'critique' THEN 2
                            WHEN dr.urgence = 'eleve' THEN 3
                            ELSE 4
                        END,
                        dr.date_demande DESC";
    
    $stmt_demandes = $pdo->query($sql_demandes);
    $demandes_recrutement = $stmt_demandes->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (count($demandes_recrutement) > 0): ?>
        <div class="notes-container">
            <?php foreach ($demandes_recrutement as $demande): ?>
                <div class="note-card" data-statut="<?= $demande['statut'] ?>" data-urgence="<?= $demande['urgence'] ?>">
                    <div class="note-header">
                        <div class="note-title-section">
                            <h3 class="note-title">
                                <?= htmlspecialchars($demande['poste']) ?>
                                <span class="status-indicator 
                                    <?= $demande['statut'] == 'approuve' ? 'status-present' : 
                                      ($demande['statut'] == 'refuse' ? 'status-absent' : 'status-waiting') ?>">
                                    <?= htmlspecialchars(ucfirst($demande['statut'])) ?>
                                </span>
                            </h3>
                            <div class="note-meta">
                                <span class="destinataires-badge">
                                    <i class="fas fa-building"></i>
                                </span>
                                <span class="note-date urgence-<?= $demande['urgence'] ?>">
                                    <i class="fas fa-clock"></i>
                                    <?= date('d/m/Y à H:i', strtotime($demande['date_demande'])) ?>
                                    <?php if ($demande['urgence'] != 'normal'): ?>
                                        - <strong><?= strtoupper($demande['urgence']) ?></strong>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="note-actions">
                            <?php if ($demande['statut'] == 'en_attente'): ?>
                                <button class="btn btn-success" onclick="openModalTraitement(<?= $demande['id'] ?>, 'approuver')">
                                    <i class="fas fa-check"></i> Approuver
                                </button>
                                <button class="btn btn-danger" onclick="openModalTraitement(<?= $demande['id'] ?>, 'refuser')">
                                    <i class="fas fa-times"></i> Refuser
                                </button>
                            <?php else: ?>
                                <span class="lu-badge">
                                    Traité le <?= $demande['date_traitement'] ? date('d/m/Y à H:i', strtotime($demande['date_traitement'])) : 'N/A' ?>
                                    <?php if ($demande['pdg_nom']): ?>
                                        par <?= htmlspecialchars($demande['pdg_nom']) ?>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="note-content">
                        <div class="note-auteur">
                            <i class="fas fa-user-tie"></i>
                            <strong>Demandeur :</strong> <?= htmlspecialchars($demande['responsable_nom']) ?>
                        </div>
                        
                        <div class="form-group" style="margin: 15px 0;">
                            <label style="font-weight: bold; margin-bottom: 5px;">Motivation :</label>
                            <div class="note-contenu-text" style="background: rgba(0,0,0,0.03); padding: 15px; border-radius: 6px;">
                                <?= nl2br(htmlspecialchars($demande['motivation'])) ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($demande['commentaire_pdg'])): ?>
                            <div class="form-group">
                                <label style="font-weight: bold; margin-bottom: 5px; color: var(--gold);">
                                    <i class="fas fa-comment"></i> Commentaire du PDG :
                                </label>
                                <div style="background: rgba(52, 152, 219, 0.1); padding: 15px; border-radius: 6px; border-left: 4px solid var(--gold);">
                                    <?= nl2br(htmlspecialchars($demande['commentaire_pdg'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($demande['fichier_pdf'])): ?>
                            <div class="form-group">
                                <label style="font-weight: bold; margin-bottom: 5px;">Fichier joint :</label>
                                <div>
                                    <a href="uploads/recrutement/<?= $demande['fichier_pdf'] ?>" 
                                       target="_blank" class="btn btn-primary">
                                        <i class="fas fa-download"></i> Télécharger le PDF
                                    </a>
                                    <span style="margin-left: 10px; color: #666; font-size: 0.9em;">
                                        (<?= round(filesize('uploads/recrutement/' . $demande['fichier_pdf']) / 1024 / 1024, 2) ?> MB)
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center" style="padding: 40px; color: #666;">
            <i class="fas fa-user-plus" style="font-size: 48px; margin-bottom: 20px; color: #ddd;"></i>
            <h3>Aucune demande de recrutement</h3>
            <p>Les responsables n'ont pas encore soumis de demandes de recrutement.</p>
        </div>
    <?php endif; ?>
</div>
    <!-- Section Discussion avec les administrateurs -->
    <div id="discussion-section" class="<?= $currentPage !== 'discussion' ? 'hidden' : '' ?>">
      <h2>Discussion avec les administrateurs</h2>
      
      <div class="filters">
        <select id="admin-select" onchange="loadMessages()">
          <option value="">-- Sélectionner un administrateur --</option>
          <?php foreach ($admins as $admin): ?>
            <option value="<?= $admin['id'] ?>" data-unread="<?= $admin['unread_count'] ?>">
              <?= htmlspecialchars($admin['name']) ?>
              <?php if ($admin['unread_count'] > 0): ?>
                <span style="color: #e74c3c;">(<?= $admin['unread_count'] ?> non lu(s))</span>
              <?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Conversation</h3>
        </div>
        <div class="card-body">
          <div id="chat-container" style="height: 400px; overflow-y: auto; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px; padding: 15px;">
            <div id="chat-messages"></div>
          </div>
          
          <form id="message-form">
            <input type="hidden" id="receiver-id" name="receiver_id">
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
  </div>
</div>

<!-- Modal pour créer/modifier une note de service -->
<div class="modal" id="noteModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title" id="modalTitle">Nouvelle note de service</h3>
      <button class="close" onclick="closeNoteModal()">&times;</button>
    </div>
    <form id="noteForm" method="post">
      <input type="hidden" name="action" id="formAction" value="create_note">
      <input type="hidden" name="note_id" id="noteId">
      
      <div class="form-group">
        <label for="titre">Titre</label>
        <input type="text" id="titre" name="titre" class="form-control" required>
      </div>
      
      <div class="form-group">
        <label for="contenu">Contenu</label>
        <textarea id="contenu" name="contenu" class="form-control" rows="6" required></textarea>
      </div>
      
      <div class="form-group">
        <label for="destinataires">Destinataires</label>
        <select id="destinataires" name="destinataires" class="form-control" required>
          <option value="tous">Tous les employés</option>
          <option value="admin">Administrateurs uniquement</option>
          <option value="employee">Employés uniquement</option>
        </select>
      </div>
      
      <div class="form-actions">
        <button type="button" class="btn btn-warning" onclick="closeNoteModal()">Annuler</button>
        <button type="submit" class="btn btn-primary">Publier</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

<?php if (!empty($_SESSION['pdg_message'])): ?>
<script>
  const toast = document.getElementById('toast');
  toast.textContent = <?= json_encode($_SESSION['pdg_message']) ?>;
  toast.classList.add('show');
  setTimeout(() => { toast.classList.remove('show'); }, 3000);
</script>
<?php unset($_SESSION['pdg_message']); endif; ?>

<script>
// Gestion des notes de service
function openNoteModal(id = null, titre = '', contenu = '', destinataires = 'tous') {
  const modal = document.getElementById('noteModal');
  const title = document.getElementById('modalTitle');
  
  if (id) {
    // Mode édition
    title.textContent = 'Modifier la note de service';
    document.getElementById('formAction').value = 'update_note';
    document.getElementById('noteId').value = id;
    document.getElementById('titre').value = titre;
    document.getElementById('contenu').value = contenu;
    document.getElementById('destinataires').value = destinataires;
  } else {
    // Mode création
    title.textContent = 'Nouvelle note de service';
    document.getElementById('formAction').value = 'create_note';
    document.getElementById('noteId').value = '';
    document.getElementById('noteForm').reset();
  }
  
  modal.style.display = 'flex';
}

function closeNoteModal() {
  document.getElementById('noteModal').style.display = 'none';
}

function confirmDeleteNote(id, titre) {
  if (confirm(`Êtes-vous sûr de vouloir supprimer la note "${titre}" ?`)) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_note';
    form.appendChild(actionInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'note_id';
    idInput.value = id;
    form.appendChild(idInput);
    
    document.body.appendChild(form);
    form.submit();
  }
}

// Gestion de la messagerie
const messageForm = document.getElementById('message-form');
const messageText = document.getElementById('message-text');
const chatMessages = document.getElementById('chat-messages');
const chatContainer = document.getElementById('chat-container');
const adminSelect = document.getElementById('admin-select');
const receiverId = document.getElementById('receiver-id');

// Charger les messages avec un administrateur spécifique
function loadMessages() {
    const selectedAdminId = adminSelect.value;
    if (!selectedAdminId) {
        chatMessages.innerHTML = '<p class="text-center">Veuillez sélectionner un administrateur pour voir la conversation</p>';
        receiverId.value = '';
        return;
    }
    
    receiverId.value = selectedAdminId;
    
    fetch('pdg_get_messages.php?admin_id=' + selectedAdminId)
        .then(response => response.json())
        .then(messages => {
            chatMessages.innerHTML = '';
            
            if (messages.length === 0) {
                chatMessages.innerHTML = '<p class="text-center">Aucun message avec cet administrateur</p>';
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

// Envoyer un message
if (messageForm) {
    messageForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = messageText.value.trim();
        const receiver = receiverId.value;
        
        if (message && receiver) {
            fetch('pdg_send_message.php', {
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
            showToast('Veuillez sélectionner un administrateur', 'warning');
        }
    });
}

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

// Basculer le mode sombre/clair
function toggleMode() {
    document.body.classList.toggle('dark');
    const isDark = document.body.classList.contains('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
}

// Appliquer le mode au chargement
if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark');
}

// Actualiser les messages automatiquement si on est sur la page discussion
if (document.getElementById('discussion-section').style.display !== 'none') {
    if (adminSelect.value) {
        loadMessages();
        window.chatInterval = setInterval(loadMessages, 5000);
    }
}

// Charger les messages quand on change d'administrateur
adminSelect.addEventListener('change', function() {
    loadMessages();
    
    // Redémarrer l'intervalle d'actualisation
    if (window.chatInterval) {
        clearInterval(window.chatInterval);
    }
    
    if (this.value) {
        window.chatInterval = setInterval(loadMessages, 5000);
    }
});

// Vérifier les nouveaux messages par administrateur
function checkNewMessagesByAdmin() {
    fetch('pdg_check_new_messages_by_admin.php')
        .then(response => response.json())
        .then(data => {
            // Mettre à jour les compteurs pour chaque administrateur
            Object.keys(data).forEach(adminId => {
                const count = data[adminId];
                const option = document.querySelector(`#admin-select option[value="${adminId}"]`);
                
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

// Vérifier les nouveaux messages par administrateur toutes les 10 secondes
setInterval(checkNewMessagesByAdmin, 10000);

// Filtrer les demandes de recrutement
function filtrerDemandes() {
    const filtreStatut = document.getElementById('filter-statut').value;
    const filtreUrgence = document.getElementById('filter-urgence').value;
    const filtreDepartement = document.getElementById('filter-departement').value;
    
    const demandes = document.querySelectorAll('#recrutement-section .note-card');
    
    demandes.forEach(demande => {
        let visible = true;
        
        if (filtreStatut && demande.getAttribute('data-statut') !== filtreStatut) {
            visible = false;
        }
        
        if (filtreUrgence && demande.getAttribute('data-urgence') !== filtreUrgence) {
            visible = false;
        }
        
        if (filtreDepartement && demande.getAttribute('data-departement') !== filtreDepartement) {
            visible = false;
        }
        
        demande.style.display = visible ? 'block' : 'none';
    });
}

// Traiter une demande de recrutement
function traiterDemande(demandeId, action) {
    const actionText = action === 'approuve' ? 'approuver' : 'refuser';
    
    if (confirm(`Êtes-vous sûr de vouloir ${actionText} cette demande de recrutement ?`)) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = '';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'traiter_recrutement';
        form.appendChild(actionInput);
        
        const demandeInput = document.createElement('input');
        demandeInput.type = 'hidden';
        demandeInput.name = 'demande_id';
        demandeInput.value = demandeId;
        form.appendChild(demandeInput);
        
        const actionRecrutementInput = document.createElement('input');
        actionRecrutementInput.type = 'hidden';
        actionRecrutementInput.name = 'action_recrutement';
        actionRecrutementInput.value = action;
        form.appendChild(actionRecrutementInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
// Filtrer les pointages
function filtrerPointages() {
    const filtreUser = document.getElementById('filter-user').value;
    const filtreDate = document.getElementById('filter-date').value;
    const filtreDateCustom = document.getElementById('filter-date-custom').value;
    
    const pointages = document.querySelectorAll('#pointages-body tr');
    const aujourdhui = new Date().toISOString().split('T')[0];
    const hier = new Date(Date.now() - 86400000).toISOString().split('T')[0];
    
    pointages.forEach(pointage => {
        let visible = true;
        const user = pointage.getAttribute('data-user');
        const datePointage = pointage.getAttribute('data-date');
        
        // Filtre par utilisateur
        if (filtreUser && user !== filtreUser) {
            visible = false;
        }
        
        // Filtre par date
        if (filtreDate || filtreDateCustom) {
            let dateFiltre = filtreDateCustom;
            
            if (filtreDate === 'today') dateFiltre = aujourdhui;
            else if (filtreDate === 'yesterday') dateFiltre = hier;
            else if (filtreDate === 'week') {
                const debutSemaine = new Date();
                debutSemaine.setDate(debutSemaine.getDate() - debutSemaine.getDay());
                dateFiltre = debutSemaine.toISOString().split('T')[0];
            } else if (filtreDate === 'month') {
                const debutMois = new Date();
                debutMois.setDate(1);
                dateFiltre = debutMois.toISOString().split('T')[0];
            }
            
            if (dateFiltre && datePointage !== dateFiltre) {
                visible = false;
            }
        }
        
        pointage.style.display = visible ? '' : 'none';
    });
}

// Voir les détails d'un pointage
function voirDetailsPointage(pointageId) {
    fetch('pdg_get_pointage_details.php?id=' + pointageId)
        .then(response => response.json())
        .then(pointage => {
            if (pointage) {
                const message = `
                    Détails du pointage :
                    \nUtilisateur: ${pointage.user_name}
                    \nRôle: ${pointage.user_role}
                    \nType: ${pointage.type}
                    \nDate: ${pointage.timestamp}
                    \nID: ${pointage.id}
                `;
                alert(message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement des détails');
        });
}

// Exporter les pointages en PDF
function exporterPointages() {
    const filtreUser = document.getElementById('filter-user').value;
    const filtreDate = document.getElementById('filter-date').value;
    const filtreDateCustom = document.getElementById('filter-date-custom').value;
    
    let url = 'pdg_export_pointages.php?export=pdf';
    
    if (filtreUser) url += '&user=' + filtreUser;
    if (filtreDate) url += '&date=' + filtreDate;
    if (filtreDateCustom) url += '&date_custom=' + filtreDateCustom;
    
    window.open(url, '_blank');
}
// Modal pour traiter les demandes de recrutement
function openModalTraitement(demandeId, action) {
    const modal = document.getElementById('traitementModal');
    const title = document.getElementById('modalTraitementTitle');
    const submitBtn = document.getElementById('submitTraitement');
    
    document.getElementById('demandeId').value = demandeId;
    document.getElementById('actionRecrutement').value = action;
    document.getElementById('commentaire').value = '';
    
    // Adapter l'interface selon l'action
    if (action === 'approuver') {
        title.textContent = 'Approuver la demande de recrutement';
        submitBtn.className = 'btn btn-success';
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Approuver';
    } else {
        title.textContent = 'Refuser la demande de recrutement';
        submitBtn.className = 'btn btn-danger';
        submitBtn.innerHTML = '<i class="fas fa-times"></i> Refuser';
    }
    
    modal.style.display = 'flex';
}

function closeTraitementModal() {
    document.getElementById('traitementModal').style.display = 'none';
}

// Filtrer les demandes de recrutement
function filtrerDemandes() {
    const filtreStatut = document.getElementById('filter-statut').value;
    const filtreUrgence = document.getElementById('filter-urgence').value;
    
    const demandes = document.querySelectorAll('#recrutement-section .note-card');
    
    demandes.forEach(demande => {
        let visible = true;
        
        if (filtreStatut && demande.getAttribute('data-statut') !== filtreStatut) {
            visible = false;
        }
        
        if (filtreUrgence && demande.getAttribute('data-urgence') !== filtreUrgence) {
            visible = false;
        }
        
        demande.style.display = visible ? 'block' : 'none';
    });
}

// Gestion de la soumission du formulaire de traitement
document.getElementById('traitementForm').addEventListener('submit', function(e) {
    e.preventDefault();
    this.submit();
});
</script>
<!-- Modal pour traiter les demandes de recrutement -->
<div class="modal" id="traitementModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title" id="modalTraitementTitle">Traiter la demande</h3>
      <button class="close" onclick="closeTraitementModal()">&times;</button>
    </div>
    <form id="traitementForm" method="post">
      <input type="hidden" name="action" value="traiter_recrutement">
      <input type="hidden" name="demande_id" id="demandeId">
      <input type="hidden" name="action_recrutement" id="actionRecrutement">
      
      <div class="form-group">
        <label for="commentaire">Commentaire (optionnel) :</label>
        <textarea id="commentaire" name="commentaire" class="form-control" rows="4" 
                  placeholder="Ajoutez un commentaire pour expliquer votre décision..."></textarea>
      </div>
      
      <div class="form-actions">
        <button type="button" class="btn btn-warning" onclick="closeTraitementModal()">Annuler</button>
        <button type="submit" class="btn btn-primary" id="submitTraitement">Confirmer</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>