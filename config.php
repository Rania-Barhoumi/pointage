<?php
// config.php - Configuration de l'application

// Désactiver l'affichage des erreurs en production
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Activer les erreurs en développement
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    ini_set('display_errors', 1);
}

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'pointage');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuration de l'application
define('APP_NAME', 'PointagePro');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Africa/Tunis');

// Configuration des horaires de travail
define('WORK_START_TIME', '08:00:00');
define('WORK_END_TIME', '17:00:00');
define('MIN_EARLY_ARRIVAL', '07:30:00');
define('MAX_LATE_DEPARTURE', '18:30:00');

// Configuration des chemins
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']));
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Définition du fuseau horaire
date_default_timezone_set(TIMEZONE);

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connexion à la base de données avec PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // Journaliser l'erreur plutôt que de l'afficher en production
    error_log("Erreur de connexion à la base de données: " . $e->getMessage());
    
    // Message générique pour l'utilisateur
    if (ini_get('display_errors')) {
        die("Erreur de connexion à la base de données: " . $e->getMessage());
    } else {
        die("Une erreur de connexion s'est produite. Veuillez réessayer plus tard.");
    }
}

// Fonctions utilitaires
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isEmployee() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        $_SESSION['error'] = "Accès refusé : droits administrateur requis";
        header('Location: index.php');
        exit;
    }
}

function requireEmployee() {
    requireAuth();
    if (!isEmployee()) {
        $_SESSION['error'] = "Accès refusé : espace employé uniquement";
        header('Location: admin.php');
        exit;
    }
}

// Fonction pour échapper les données HTML
function escape($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Fonction pour formater la date
function formatDate($date, $format = 'd/m/Y H:i') {
    if (!$date) return '';
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

// Fonction pour calculer les heures travaillées
function calculateWorkHours($entryTime, $exitTime) {
    if (!$entryTime || !$exitTime) return '00:00';
    
    $entry = new DateTime($entryTime);
    $exit = new DateTime($exitTime);
    
    $interval = $entry->diff($exit);
    return $interval->format('%H:%I');
}

// Fonction pour vérifier si c'est un jour ouvrable
function isWorkday($date = null) {
    $date = $date ? new DateTime($date) : new DateTime();
    $dayOfWeek = $date->format('N'); // 1 (lundi) à 7 (dimanche)
    
    // Samedi (6) et dimanche (7) sont des jours non ouvrables
    return $dayOfWeek < 6;
}

// Gestion des messages flash
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Fonction pour générer un token CSRF
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Fonction pour valider un token CSRF
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Fonction pour logger les activités
function logActivity($userId, $action, $details = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur de journalisation: " . $e->getMessage());
        return false;
    }
}

// Vérification de la table activity_log (création si elle n'existe pas)
try {
    $pdo->query("
        CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log("Erreur création table activity_log: " . $e->getMessage());
}

// Vérification de la table messages (création si elle n'existe pas)
try {
    $pdo->query("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            message TEXT NOT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_read BOOLEAN DEFAULT FALSE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_sender_receiver (sender_id, receiver_id),
            INDEX idx_receiver_read (receiver_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log("Erreur création table messages: " . $e->getMessage());
}

// Vérification de la table notifications (création si elle n'existe pas)
try {
    $pdo->query("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_read (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log("Erreur création table notifications: " . $e->getMessage());
}

// Vérification de la table des paramètres (création si elle n'existe pas)
try {
    $pdo->query("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            description VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insertion des paramètres par défaut
    $defaultSettings = [
        ['company_name', 'PointagePro', 'Nom de l\'entreprise'],
        ['work_start_time', WORK_START_TIME, 'Heure de début du travail'],
        ['work_end_time', WORK_END_TIME, 'Heure de fin du travail'],
        ['max_early_arrival', MIN_EARLY_ARRIVAL, 'Arrivée anticipée maximale'],
        ['max_late_departure', MAX_LATE_DEPARTURE, 'Départ tardif maximal'],
        ['allow_weekend_work', '0', 'Autoriser le travail le week-end'],
    ];
    
    foreach ($defaultSettings as $setting) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO settings (setting_key, setting_value, description) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute($setting);
    }
} catch (PDOException $e) {
    error_log("Erreur création table settings: " . $e->getMessage());
}

// Fonction pour récupérer un paramètre
function getSetting($key, $default = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        error_log("Erreur récupération paramètre: " . $e->getMessage());
        return $default;
    }
}

// Fonction pour définir un paramètre
function setSetting($key, $value) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        
        return $stmt->execute([$key, $value, $value]);
    } catch (PDOException $e) {
        error_log("Erreur mise à jour paramètre: " . $e->getMessage());
        return false;
    }
}

// Fonction pour nettoyer les anciens logs (30 jours)
function cleanupOldLogs() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM activity_log 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur nettoyage logs: " . $e->getMessage());
        return false;
    }
}

// Nettoyage occasionnel des anciens logs (1% de chance à chaque chargement)
if (rand(1, 100) === 1) {
    cleanupOldLogs();
}

// Gestion des CORS pour les API
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
}

// Gestion des requêtes OPTIONS pour les CORS preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}