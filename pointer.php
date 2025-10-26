<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['type'], ['entrée', 'sortie'])) {
    $type = $_POST['type'];
    $user_id = $_SESSION['user_id'];

    $pdo = new PDO("mysql:host=localhost;dbname=pointage", "root", "");

    // Vérifier le dernier type de pointage
    $stmt = $pdo->prepare("SELECT type FROM pointages WHERE user_id = ? ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$last || $last['type'] !== $type) {
        // Autoriser le pointage
        $stmt = $pdo->prepare("INSERT INTO pointages (user_id, type) VALUES (?, ?)");
        $stmt->execute([$user_id, $type]);
    } else {
        // Ignorer le double pointage (optionnel : afficher un message via session)
        $_SESSION['message'] = "Vous avez déjà pointé votre " . $type . ".";
    }
}

header("Location: employee_dashboard.php");
exit;
