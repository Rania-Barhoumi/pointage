<?php
// add_user.php - pour insérer un utilisateur dans la base
$pdo = new PDO("mysql:host=localhost;dbname=pointage", "root", "");

$name = "Jean Dupont";
$email = "jean@example.com";
$password = password_hash("123456", PASSWORD_DEFAULT);
$role = "employee"; // ou "admin"

$stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->execute([$name, $email, $password, $role]);

echo "Utilisateur ajouté.";
