<?php
session_start();
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Career Compass — AI Career Guidance</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="landing">
  <main class="container">
    <header class="hero">
      <h1>🧭 Career Compass</h1>
      <p class="tagline">AI-powered career guidance for high school learners in Lesotho</p>
    </header>

    <section class="card intro">
      <h2>Find your path</h2>
      <p>Answer a short assessment, share your subjects and marks, and receive personalised career recommendations that explain <em>why</em> they fit you.</p>
      <ul class="features">
        <li>✅ RIASEC interest assessment</li>
        <li>✅ Personalised recommendations</li>
        <li>✅ Local labour-market relevance</li>
        <li>✅ Explains every recommendation</li>
      </ul>
      <div class="actions">
        <a class="btn primary" href="register.php">Get Started</a>
        <a class="btn secondary" href="login.php">Log In</a>
      </div>
    </section>

    <footer class="foot">Prototype · Botho University Honours Project</footer>
  </main>
</body>
</html>