<?php
session_start();
$pdo = new PDO("mysql:host=localhost;dbname=pointage;charset=utf8", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login = trim($_POST['login']); // Peut être email ou matricule
    $password = $_POST['password'];

    // Vérifier si l'identifiant est un email ou un matricule
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR matricule = ?");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user) {
        // Vérifier si le compte est actif
        if (!$user['is_active']) {
            $error = "Votre compte a été désactivé. Veuillez contacter l'administrateur.";
        }
        // Vérifier le mot de passe
        elseif (password_verify($password, $user['password'])) {
            // Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['matiere'] = $user['matiere'];
            $_SESSION['matricule'] = $user['matricule'];

            // Déterminer le rôle en fonction de la matière pour les employés
            if ($user['role'] === 'employee') {
                // Matières qui ont le rôle de responsable
                $responsable_matieres = [
                    'Directeur technique', 
                    'Directeur qualité',
                    'Directeur achat et appro',
                    'Directeur Ressources Humaines',
                    'Directeur de production',
                    'Directeur de système informatique'
                ];
                
                // Matières qui sont des employés standards (qualité)
                $employe_qualite_matieres = [
                    'Qualité système',
                    'Qualité chantier', 
                    'Agent de contrôle qualité'
                ];
                
                // Matières qui sont des employés standards (achat)
                $employe_achat_matieres = [
                    'Achat local',
                    'Ass achat', 
                    'Chauffeur'
                ];
                
                // Matières qui sont des employés standards (RH)
                $employe_rh_matieres = [
                    'Agent sécurité',
                    'Agents de nettoyage', 
                    'Assistant RH'
                ];

                // Matières qui sont des employés standards (production)
                $employe_production_matieres = [
                    'Agent de production',
                    'Responsable production'
                ];

                // Matières qui sont des employés standards (informatique)
                $employe_informatique_matieres = [
                    'Technicien informatique'
                ];

                if (in_array($user['matiere'], $responsable_matieres)) {
                    $_SESSION['role'] = 'responsable';
                    
                    // Mettre à jour le rôle dans la base de données
                    $updateStmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $updateStmt->execute(['responsable', $user['id']]);
                }
            }

            // Redirection en fonction du rôle
            switch ($_SESSION['role']) {
                case 'admin':
                    header("Location: admin_dashboard.php");
                    break;
                case 'responsable':
                    header("Location: responsable_dashboard.php");
                    break;
                case 'pdg':
                    header("Location: pdg.php");
                    break;
                default:
                    header("Location: employee_dashboard.php");
                    break;
            }
            exit;
        } else {
            $error = "Mot de passe incorrect.";
        }
    } else {
        $error = "Aucun utilisateur trouvé avec cet email ou matricule.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Connexion</title>
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
    .login-container {
      background: #fff;
      padding: 40px;
      border-radius: 16px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 400px;
    }
    h2 { text-align: center; margin-bottom: 20px; color: #182848; }
    .input-group {
      position: relative;
      margin: 10px 0;
    }
    input {
      width: 100%;
      padding: 14px;
      padding-right: 40px;
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
    .error { background: #f2dede; color: #a94442; }
    .footer-link {
      text-align: center;
      margin-top: 16px;
      font-size: 14px;
    }
    .footer-link a { color: #4b6cb7; text-decoration: none; }
    .login-info {
      text-align: center;
      font-size: 12px;
      color: #666;
      margin-bottom: 10px;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h2>Connexion</h2>

    <?php if ($error): ?>
      <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="login-info">
      Vous pouvez vous connecter avec votre email ou votre matricule
    </div>

    <form method="POST" action="">
      <div class="input-group">
        <input type="text" name="login" placeholder="Email ou Matricule" required value="<?= isset($_POST['login']) ? htmlspecialchars($_POST['login']) : '' ?>">
      </div>

      <div class="input-group">
        <input type="password" name="password" id="password" placeholder="Mot de passe" required>
        <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
      </div>

      <button type="submit">Se connecter</button>
    </form>

    <div class="footer-link">
      Vous n'avez pas de compte ? <a href="signup.php">S'inscrire</a>
    </div>
  </div>

  <script>
    const togglePassword = document.querySelector("#togglePassword");
    const passwordInput = document.querySelector("#password");

    togglePassword.addEventListener("click", function () {
      const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
      passwordInput.setAttribute("type", type);
      this.classList.toggle("fa-eye");
      this.classList.toggle("fa-eye-slash");
    });
  </script>
</body>
</html>