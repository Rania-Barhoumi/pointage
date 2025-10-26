<?php
session_start();
$pdo = new PDO("mysql:host=localhost;dbname=pointage;charset=utf8", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$success = "";
$error = "";

// Liste des matières disponibles
$matieres = [
    "Directeur qualité",
    "Qualité système",
    "Qualité chantier",
    "Directeur technique",
    "Responsable production",
    "Agent de contrôle qualité",
    "Directeur achat et appro",
    "Achat local",
    "Ass achat",
    "Chauffeur",
    "Directeur Ressources Humaines",
    "Agent sécurité",
    "Agents de nettoyage",
    "Assistant RH",
    "Agent de production",
    "Technicien informatique",
    "Agent de production",
    "Directeur de système informatique",
    "Directeur de production"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sécurisation des entrées
    $name       = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');
    $email      = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $matricule  = htmlspecialchars(trim($_POST['matricule']), ENT_QUOTES, 'UTF-8');
    $password   = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $matiere    = isset($_POST['matiere']) ? htmlspecialchars(trim($_POST['matiere']), ENT_QUOTES, 'UTF-8') : '';

    // Vérification de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strpos($email, "@") === false) {
        $error = "Adresse email invalide (elle doit contenir @).";
    } 
    // Vérification du matricule
    elseif (empty($matricule)) {
        $error = "Le matricule est obligatoire.";
    }
    // Vérification confirmation mot de passe
    elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    }
    // Vérification mot de passe fort
    elseif (strlen($password) < 8 
        || !preg_match("/[A-Z]/", $password)
        || !preg_match("/[a-z]/", $password)
        || !preg_match("/[0-9]/", $password)
        || !preg_match("/[\W]/", $password))
    {
        $error = "Le mot de passe doit contenir au minimum 8 caractères, une majuscule, une minuscule, un chiffre et un symbole.";
    }
    // Vérification de la matière sélectionnée
    elseif (empty($matiere) || !in_array($matiere, $matieres)) {
        $error = "Veuillez sélectionner une matière valide.";
    } 
    else {
        // Déterminer le rôle en fonction de l'email
        $role = ($email === 'admin@gmail.com') ? 'admin' : 'employee';
        
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        try {
            // Vérifier si le matricule existe déjà
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE matricule = ?");
            $checkStmt->execute([$matricule]);
            if ($checkStmt->fetch()) {
                $error = "Ce matricule est déjà utilisé.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, matricule, password, role, matiere) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $matricule, $hashedPassword, $role, $matiere]);
                $success = "Inscription réussie. Vous pouvez vous connecter.";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                if (strpos($e->getMessage(), 'email') !== false) {
                    $error = "Cet email est déjà utilisé.";
                } else {
                    $error = "Ce matricule est déjà utilisé.";
                }
            } else {
                $error = "Erreur lors de l'inscription: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inscription</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
    body {
      margin: 0;
      background: linear-gradient(135deg, #4b6cb7, #182848);
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .signup-container {
      background: #fff;
      padding: 40px;
      border-radius: 16px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 450px;
    }
    h2 { text-align: center; margin-bottom: 20px; color: #182848; }
    .input-group {
      position: relative;
      margin: 10px 0;
    }
    input, select {
      width: 100%;
      padding: 14px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 15px;
    }
    .toggle-password {
      position: absolute;
      top: 50%;
      right: 12px;
      transform: translateY(-50%);
      cursor: pointer;
      color: #666;
      font-size: 18px;
    }
    button {
      width: 100%;
      padding: 14px;
      background-color: #4b6cb7;
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 16px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    button:hover { background-color: #3a5199; }
    .message {
      padding: 12px;
      margin-bottom: 16px;
      border-radius: 6px;
      font-size: 14px;
    }
    .success { background: #dff0d8; color: #3c763d; }
    .error { background: #f2dede; color: #a94442; }
    .footer-link {
      text-align: center;
      margin-top: 16px;
      font-size: 14px;
    }
    .footer-link a { color: #4b6cb7; text-decoration: none; }
  </style>
</head>
<body>
  <div class="signup-container">
    <h2>Créer un compte</h2>

    <?php if ($error): ?>
      <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
      <div class="message success"><?= htmlspecialchars($success) ?> <a href="login.php">Connexion</a></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="text" name="name" placeholder="Nom complet" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
      <input type="email" name="email" placeholder="Adresse email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
      <input type="text" name="matricule" placeholder="Matricule" value="<?= isset($_POST['matricule']) ? htmlspecialchars($_POST['matricule']) : '' ?>" required>
      
      <select name="matiere" required>
        <option value="">Sélectionnez votre matière</option>
        <?php foreach ($matieres as $m): ?>
          <option value="<?= htmlspecialchars($m) ?>" <?= (isset($_POST['matiere']) && $_POST['matiere'] === $m) ? 'selected' : '' ?>>
            <?= htmlspecialchars($m) ?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <div class="input-group">
        <input type="password" name="password" id="password" placeholder="Mot de passe" required>
        <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
      </div>

      <div class="input-group">
        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirmer le mot de passe" required>
        <i class="fa-solid fa-eye toggle-password" id="toggleConfirmPassword"></i>
      </div>

      <button type="submit">S'inscrire</button>
    </form>

    <div class="footer-link">
      Déjà un compte ? <a href="login.php">Se connecter</a>
    </div>
  </div>

  <script>
    function togglePassword(inputId, toggleId) {
      const input = document.getElementById(inputId);
      const toggle = document.getElementById(toggleId);

      toggle.addEventListener("click", function () {
        const type = input.getAttribute("type") === "password" ? "text" : "password";
        input.setAttribute("type", type);
        this.classList.toggle("fa-eye");
        this.classList.toggle("fa-eye-slash");
      });
    }

    togglePassword("password", "togglePassword");
    togglePassword("confirm_password", "toggleConfirmPassword");
  </script>
</body>
</html>