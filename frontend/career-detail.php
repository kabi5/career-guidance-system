<?php
require_once 'config/db.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM recommendations WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $_SESSION['user_id']]);
$rec = $stmt->fetch();
if (!$rec) { header('Location: dashboard.php'); exit; }
$progs = json_decode($rec['local_programmes'] ?? '[]', true) ?: [];
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

    <h3>Where to study in Lesotho</h3>
    <?php if ($progs): ?>
      <ul class="prog-list">
        <?php foreach ($progs as $p): ?>
          <li>
            <strong><?= htmlspecialchars($p['programme']) ?></strong><br>
            <span class="muted"><?= htmlspecialchars($p['university']) ?>
              · <?= htmlspecialchars($p['level']) ?>
              <?php if (!empty($p['duration_years'])): ?> · <?= (int)$p['duration_years'] ?> yrs<?php endif; ?>
            </span>
            <?php if (!empty($p['missing_subjects'])): ?>
              <br><small>Subjects you still need: <?= htmlspecialchars(implode(', ', $p['missing_subjects'])) ?></small>
            <?php endif; ?>
            <?php if (empty($p['verified'])): ?>
              <br><small class="muted">Confirm requirements in the current prospectus.</small>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted">No matching local programme is listed yet. Ask the assistant or a career counsellor about related fields.</p>
    <?php endif; ?>

    <h3>Typical education level</h3>
    <p><?= htmlspecialchars($rec['education_level'] ?? 'Not specified') ?></p>
    <a class="btn secondary" href="chat.php">Ask the career assistant</a>
  </section>
</main>
</body>
</html>