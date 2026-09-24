<?php
require_once 'config/db.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM recommendations WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $_SESSION['user_id']]);
$rec = $stmt->fetch();
if (!$rec) { header('Location: dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($rec['career_title']) ?> · Career Compass</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
  <a href="dashboard.php" class="back">← Back</a>
  <span>Career</span>
</header>
<main class="container">
  <section class="card">
    <h1><?= htmlspecialchars($rec['career_title']) ?></h1>
    <div class="score-pill">Match: <?= (int)$rec['match_score'] ?>%</div>
    <h3>Why this fits you</h3>
    <p><?= nl2br(htmlspecialchars($rec['explanation'])) ?></p>
  </section>

  <section class="card">
    <h3>Recommended subjects</h3>
    <p><?= htmlspecialchars($rec['recommended_subjects']) ?></p>
    <h3>Education pathway</h3>
    <p><?= htmlspecialchars($rec['pathway']) ?></p>
  </section>
</main>
</body>
</html>