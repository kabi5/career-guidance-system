<?php
require_once 'config/db.php';
require_login();
$user = current_user();

// Fetch latest assessment + recommendations
$stmt = db()->prepare('SELECT * FROM assessments WHERE user_id = ? ORDER BY taken_at DESC LIMIT 1');
$stmt->execute([$user['id']]);
$assessment = $stmt->fetch();

$stmt = db()->prepare('SELECT * FROM recommendations WHERE user_id = ? ORDER BY match_score DESC LIMIT 5');
$stmt->execute([$user['id']]);
$recs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard · Career Compass</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
  <span>🧭 Career Compass</span>
  <a href="logout.php" class="logout">Logout</a>
</header>
<main class="container">
  <h1>Hi, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?> 👋</h1>

  <?php if (!$assessment): ?>
    <section class="card">
      <h2>Start your journey</h2>
      <p>Take the interest assessment to get personalised career recommendations.</p>
      <a class="btn primary" href="assessment.php">Take Assessment</a>
    </section>
  <?php else: ?>
    <section class="card">
      <h2>Your RIASEC profile</h2>
      <?php $scores = json_decode($assessment['riasec_scores'], true); ?>
      <div class="riasec-chart">
        <?php foreach (['R','I','A','S','E','C'] as $d): ?>
          <div class="bar">
            <span class="bar-label"><?= $d ?></span>
            <div class="bar-track"><div class="bar-fill" style="width: <?= (float)$scores[$d] ?>%"></div></div>
            <span class="bar-value"><?= (int)$scores[$d] ?>%</span>
          </div>
        <?php endforeach; ?>
      </div>
     <?php
$names = ['R'=>'Realistic','I'=>'Investigative','A'=>'Artistic','S'=>'Social','E'=>'Enterprising','C'=>'Conventional'];
$first = substr($assessment['top_interest'], 0, 1);
?>
<p class="muted">
  Top interest: <strong><?= $first ?> — <?= $names[$first] ?? '' ?></strong>
  <br>
  <small>Full Holland code: <?= htmlspecialchars($assessment['top_interest']) ?></small>
</p>
      <div class="actions">
  <a class="btn secondary" href="profile.php">Edit Profile</a>
  <a class="btn primary" href="assessment.php">Retake Assessment</a>
</div>
    </section>

    <section class="card">
      <h2>Your recommendations</h2>
      <?php if (!$recs): ?>
        <p class="muted">No recommendations yet. Complete the assessment to generate them.</p>
      <?php else: ?>
        <ul class="rec-list">
        <?php foreach ($recs as $r): ?>
          <li>
            <a href="career-detail.php?id=<?= (int)$r['id'] ?>" class="rec-item">
              <span class="rec-title"><?= htmlspecialchars($r['career_title']) ?></span>
              <span class="rec-score"><?= (int)$r['match_score'] ?>%</span>
            </a>
          </li>
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</main>
</body>
</html>