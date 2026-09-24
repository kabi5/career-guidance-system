<?php
require_once 'config/db.php';
require_login();
$user = current_user();
$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subjects = trim($_POST['subjects'] ?? '');
    $maths    = ($_POST['maths_mark']    === '' ? null : (float)$_POST['maths_mark']);
    $science  = ($_POST['science_mark']  === '' ? null : (float)$_POST['science_mark']);
    $english  = ($_POST['english_mark']  === '' ? null : (float)$_POST['english_mark']);
    $asp      = trim($_POST['aspirations'] ?? '');

    // Basic validation
    foreach (['maths' => $maths, 'science' => $science, 'english' => $english] as $k => $v) {
        if ($v !== null && ($v < 0 || $v > 100)) {
            $error = ucfirst($k) . ' mark must be between 0 and 100.';
            break;
        }
    }

    if (!$error) {
        $stmt = db()->prepare('
            INSERT INTO learner_profiles
                (user_id, subjects, maths_mark, science_mark, english_mark, aspirations)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                subjects     = VALUES(subjects),
                maths_mark   = VALUES(maths_mark),
                science_mark = VALUES(science_mark),
                english_mark = VALUES(english_mark),
                aspirations  = VALUES(aspirations)
        ');
        $stmt->execute([$user['id'], $subjects, $maths, $science, $english, $asp]);
        $saved = true;
    }
}

// Reload profile (so form reflects saved values after POST)
$stmt = db()->prepare('SELECT * FROM learner_profiles WHERE user_id = ?');
$stmt->execute([$user['id']]);
$p = $stmt->fetch() ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile · Career Compass</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
  <a href="dashboard.php" class="back">← Dashboard</a>
  <span>My Profile</span>
</head>
<main class="container">
  <h1>My academic profile</h1>
  <p class="muted">
    These details feed directly into your career recommendations.
    The more accurate they are, the better your matches.
  </p>

  <?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
  <?php elseif ($saved): ?>
    <div class="alert success">Profile saved. Retake the assessment to refresh your recommendations.</div>
  <?php endif; ?>

  <form method="post" class="card form">
    <label>Subjects you are taking
      <small>(comma-separated)</small>
      <input type="text" name="subjects"
             placeholder="e.g. Mathematics, Physical Sciences, Computer Studies"
             value="<?= htmlspecialchars($p['subjects'] ?? '') ?>">
    </label>

    <label>Mathematics mark (%)
      <input type="number" name="maths_mark" min="0" max="100" step="0.1"
             value="<?= htmlspecialchars($p['maths_mark'] ?? '') ?>">
    </label>

    <label>Science mark (%) <small>(Physical Sciences / Biology / Chemistry — highest)</small>
      <input type="number" name="science_mark" min="0" max="100" step="0.1"
             value="<?= htmlspecialchars($p['science_mark'] ?? '') ?>">
    </label>

    <label>English mark (%)
      <input type="number" name="english_mark" min="0" max="100" step="0.1"
             value="<?= htmlspecialchars($p['english_mark'] ?? '') ?>">
    </label>

    <label>Career aspirations <small>(optional)</small>
      <input type="text" name="aspirations"
             placeholder="e.g. I want to work with computers or solve problems"
             value="<?= htmlspecialchars($p['aspirations'] ?? '') ?>">
    </label>

    <button type="submit" class="btn primary">Save profile</button>
  </form>

  <section class="card">
    <h3>Why does this matter?</h3>
    <p class="muted">
      Career matches use three signals: your <strong>RIASEC interests</strong> (55%),
      your <strong>subjects and marks</strong> (30%), and <strong>labour-market demand</strong> (15%).
      Without a profile, your academic signal is empty — which is why match scores
      often top out around 70%. Fill this in once and retake the assessment to see
      the difference.
    </p>
  </section>
</main>
</body>
</html>