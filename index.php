<?php
session_start();
// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: employee_dashboard.php");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TimeTrack - Application de Pointage</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #4b6cb7;
      --primary-dark: #3a5199;
      --secondary: #182848;
      --accent: #2ecc71;
      --light: #f8f9fa;
      --dark: #212529;
      --gray: #6c757d;
      --light-gray: #e9ecef;
    }
    
    * { 
      box-sizing: border-box; 
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Inter', sans-serif;
      line-height: 1.6;
      color: var(--dark);
      background-color: var(--light);
    }
    
    /* Header */
    header {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      padding: 1rem 2rem;
      position: fixed;
      width: 100%;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .navbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .logo {
      font-size: 1.8rem;
      font-weight: 700;
      display: flex;
      align-items: center;
    }
    
    .logo i {
      margin-right: 10px;
      color: var(--accent);
    }
    
    .nav-links {
      display: flex;
      list-style: none;
    }
    
    .nav-links li {
      margin-left: 2rem;
    }
    
    .nav-links a {
      color: white;
      text-decoration: none;
      font-weight: 500;
      transition: color 0.3s;
    }
    
    .nav-links a:hover {
      color: var(--accent);
    }
    
    .btn {
      display: inline-block;
      padding: 0.8rem 1.5rem;
      background-color: var(--accent);
      color: white;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
    }
    
    .btn:hover {
      background-color: #27ae60;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-outline {
      background: transparent;
      border: 2px solid white;
    }
    
    .btn-outline:hover {
      background: white;
      color: var(--primary);
    }
    
    /* Hero Section */
    .hero {
      padding: 8rem 2rem 5rem;
      background: linear-gradient(rgba(75, 108, 183, 0.9), rgba(24, 40, 72, 0.9)), url('https://images.unsplash.com/photo-1521791136064-7986c2920216?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80') no-repeat center center/cover;
      color: white;
      text-align: center;
    }
    
    .hero-content {
      max-width: 800px;
      margin: 0 auto;
    }
    
    .hero h1 {
      font-size: 3.5rem;
      margin-bottom: 1rem;
      font-weight: 700;
    }
    
    .hero p {
      font-size: 1.2rem;
      margin-bottom: 2rem;
      opacity: 0.9;
    }
    
    .hero-buttons {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin-top: 2rem;
    }
    
    /* Features Section */
    .features {
      padding: 5rem 2rem;
      background-color: white;
    }
    
    .section-title {
      text-align: center;
      margin-bottom: 3rem;
    }
    
    .section-title h2 {
      font-size: 2.5rem;
      color: var(--primary);
      margin-bottom: 1rem;
    }
    
    .section-title p {
      color: var(--gray);
      max-width: 600px;
      margin: 0 auto;
    }
    
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .feature-card {
      background: var(--light);
      border-radius: 10px;
      padding: 2rem;
      text-align: center;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .feature-icon {
      font-size: 2.5rem;
      color: var(--primary);
      margin-bottom: 1.5rem;
    }
    
    .feature-card h3 {
      margin-bottom: 1rem;
      color: var(--secondary);
    }
    
    /* How It Works */
    .how-it-works {
      padding: 5rem 2rem;
      background: linear-gradient(135deg, var(--light-gray) 0%, var(--light) 100%);
    }
    
    .steps {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 2rem;
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .step {
      flex: 1;
      min-width: 250px;
      text-align: center;
      padding: 2rem;
    }
    
    .step-number {
      display: inline-block;
      width: 50px;
      height: 50px;
      line-height: 50px;
      border-radius: 50%;
      background: var(--primary);
      color: white;
      font-weight: bold;
      margin-bottom: 1rem;
    }
    
    /* Testimonials */
    .testimonials {
      padding: 5rem 2rem;
      background-color: white;
    }
    
    .testimonials-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .testimonial {
      background: var(--light);
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }
    
    .testimonial-text {
      font-style: italic;
      margin-bottom: 1rem;
    }
    
    .testimonial-author {
      display: flex;
      align-items: center;
    }
    
    .author-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      margin-right: 1rem;
    }
    
    /* CTA Section */
    .cta {
      padding: 5rem 2rem;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      text-align: center;
    }
    
    .cta h2 {
      font-size: 2.5rem;
      margin-bottom: 1.5rem;
    }
    
    .cta p {
      max-width: 600px;
      margin: 0 auto 2rem;
      opacity: 0.9;
    }
    
    /* Footer */
    footer {
      background: var(--secondary);
      color: white;
      padding: 3rem 2rem;
    }
    
    .footer-content {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 2rem;
    }
    
    .footer-section h3 {
      margin-bottom: 1.5rem;
      font-size: 1.2rem;
    }
    
    .footer-links {
      list-style: none;
    }
    
    .footer-links li {
      margin-bottom: 0.8rem;
    }
    
    .footer-links a {
      color: #ccc;
      text-decoration: none;
      transition: color 0.3s;
    }
    
    .footer-links a:hover {
      color: var(--accent);
    }
    
    .social-icons {
      display: flex;
      gap: 1rem;
      margin-top: 1rem;
    }
    
    .social-icons a {
      color: white;
      font-size: 1.2rem;
      transition: color 0.3s;
    }
    
    .social-icons a:hover {
      color: var(--accent);
    }
    
    .copyright {
      text-align: center;
      margin-top: 3rem;
      padding-top: 1.5rem;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      color: #ccc;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .navbar {
        flex-direction: column;
        padding: 1rem;
      }
      
      .nav-links {
        margin-top: 1rem;
        flex-direction: column;
        text-align: center;
      }
      
      .nav-links li {
        margin: 0.5rem 0;
      }
      
      .hero h1 {
        font-size: 2.5rem;
      }
      
      .hero-buttons {
        flex-direction: column;
        align-items: center;
      }
      
      .hero {
        padding: 7rem 1rem 3rem;
      }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header>
    <nav class="navbar">
      <div class="logo">
        <i class="fas fa-clock"></i> TimeTrack
      </div>
      <ul class="nav-links">
        <li><a href="#features">Fonctionnalités</a></li>
        <li><a href="#how-it-works">Comment ça marche</a></li>
      </ul>
      <a href="login.php" class="btn btn-outline">Se connecter</a>
    </nav>
  </header>

  <!-- Hero Section -->
  <section class="hero">
    <div class="hero-content">
      <h1>Gérez votre temps de travail en toute simplicité</h1>
      <p>TimeTrack est la solution intuitive pour suivre vos heures de travail, optimiser votre productivité et simplifier la gestion du temps pour les employés et les managers.</p>
      <div class="hero-buttons">
        <a href="login.php" class="btn">Commencer maintenant</a>
        <a href="#features" class="btn btn-outline">En savoir plus</a>
      </div>
    </div>
  </section>

  <!-- Features Section -->
  <section class="features" id="features">
    <div class="section-title">
      <h2>Fonctionnalités principales</h2>
      <p>Découvrez comment TimeTrack révolutionne la gestion du temps de travail</p>
    </div>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-fingerprint"></i>
        </div>
        <h3>Pointage sécurisé</h3>
        <p>Système de pointage fiable avec authentification sécurisée pour éviter toute fraude.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-chart-line"></i>
        </div>
        <h3>Suivi en temps réel</h3>
        <p>Visualisez vos heures de travail et analysez votre productivité avec des rapports détaillés.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-bell"></i>
        </div>
        <h3>Notifications intelligentes</h3>
        <p>Recevez des alertes pour les oublis de pointage et les heures supplémentaires.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-mobile-alt"></i>
        </div>
        <h3>Accessible partout</h3>
        <p>Application responsive qui s'adapte à tous vos appareils : desktop, tablette et mobile.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-user-shield"></i>
        </div>
        <h3>Espace administrateur</h3>
        <p>Gérez les équipes, consultez les statistiques et exportez les données facilement.</p>
      </div>
    </div>
  </section>

  <!-- How It Works -->
  <section class="how-it-works" id="how-it-works">
    <div class="section-title">
      <h2>Comment ça marche</h2>
      <p>Une solution simple en trois étapes pour gérer votre temps de travail</p>
    </div>
    <div class="steps">
      <div class="step">
        <div class="step-number">1</div>
        <h3>Connectez-vous</h3>
        <p>Accédez à votre espace personnel avec vos identifiants sécurisés.</p>
      </div>
      <div class="step">
        <div class="step-number">2</div>
        <h3>Pointez</h3>
        <p>En un clic, pointez votre arrivée et votre départ du travail.</p>
      </div>
      <div class="step">
        <div class="step-number">3</div>
        <h3>Consultez</h3>
        <p>Visualisez votre historique et téléchargez vos rapports mensuels.</p>
      </div>
    </div>
  </section>

  
  <!-- CTA Section -->
  <section class="cta">
    <h2>Prêt à optimiser votre gestion du temps ?</h2>
    <p>Rejoignez des centaines d'entreprises qui utilisent déjà TimeTrack pour simplifier leur processus de pointage.</p>
    <a href="login.php" class="btn">Commencer maintenant</a>
  </section>

  <!-- Footer -->
  <footer>
    <div class="footer-content">
      <div class="footer-section">
        <h3>TimeTrack</h3>
        <p>La solution intuitive pour la gestion du temps de travail et le suivi de la productivité.</p>
        <div class="social-icons">
          <a href="#"><i class="fab fa-facebook"></i></a>
          <a href="#"><i class="fab fa-twitter"></i></a>
          <a href="#"><i class="fab fa-linkedin"></i></a>
          <a href="#"><i class="fab fa-instagram"></i></a>
        </div>
      </div>
      <div class="footer-section">
        <h3>Liens rapides</h3>
        <ul class="footer-links">
          <li><a href="#features">Fonctionnalités</a></li>
          <li><a href="#how-it-works">Comment ça marche</a></li>
          <li><a href="login.php">Connexion</a></li>
        </ul>
      </div>
      <div class="footer-section">
        <h3>Contact</h3>
        <ul class="footer-links">
          <li><i class="fas fa-envelope"></i> contact@timetrack.com</li>
          <li><i class="fas fa-phone"></i> +126 23 45 67 89</li>
          <li><i class="fas fa-map-marker-alt"></i> 123 Rue de Paris, 75000 Tunisie</li>
        </ul>
      </div>
    </div>
    <div class="copyright">
      <p>&copy; 2025 TimeTrack. Tous droits réservés.</p>
    </div>
  </footer>

  <script>
    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
          window.scrollTo({
            top: targetElement.offsetTop - 80,
            behavior: 'smooth'
          });
        }
      });
    });
  </script>
</body>
</html>