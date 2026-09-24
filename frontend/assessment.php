<?php
require_once 'config/db.php';
require_login();

$questions = db()->query('SELECT * FROM assessment_questions ORDER BY id')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Build responses payload
    $responses = [];
    foreach ($questions as $q) {
        $key = 'q' . $q['id'];
        if (isset($_POST[$key])) {
            $responses[] = ['dimension' => $q['riasec_dimension'], 'answer' => (int)$_POST[$key]];
        }
    }

    // Grade & subjects profile (from previous profile save)
    $stmt = db()->prepare('SELECT * FROM learner_profiles WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch() ?: [];

    $payload = [
        'responses' => $responses,
        'marks' => [
            'maths' => (float)($profile['maths_mark'] ?? 0),
            'science' => (float)($profile['science_mark'] ?? 0),
            'english' => (float)($profile['english_mark'] ?? 0),
        ],
        'subjects' => array_filter(array_map('trim', explode(',', $profile['subjects'] ?? ''))),
        'aspirations' => $profile['aspirations'] ?? '',
        'top_n' => 5,
    ];

    // Call Python API
    $ch = curl_init(AI_API . '/recommend');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $resp) {
        $data = json_decode($resp, true);

        // Save assessment
        $ins = db()->prepare('INSERT INTO assessments (user_id, riasec_scores, top_interest) VALUES (?,?,?)');
        $ins->execute([
            $_SESSION['user_id'],
            json_encode($data['riasec_scores']),
            $data['top_code'],
        ]);

        // Clear old recs and save new
        db()->prepare('DELETE FROM recommendations WHERE user_id = ?')->execute([$_SESSION['user_id']]);
        $insR = db()->prepare('
                INSERT INTO recommendations
                    (user_id, career_title, match_score, explanation,
                    recommended_subjects, pathway, holland_code, education_level)
                VALUES (?,?,?,?,?,?,?,?)
    ');
    foreach ($data['recommendations'] as $r) {
        $insR->execute([
            $_SESSION['user_id'],
            $r['title'],
            $r['match_score'],
            $r['explanation'],
            $r['recommended_subjects'],
            $r['pathway'],
            $r['holland_code']   ?? null,
            $r['education_level'] ?? null,
        ]);
    }

        header('Location: dashboard.php');
        exit;
    }
    $error = 'Could not generate recommendations. Is the AI engine running?';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assessment · Career Compass</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
  <a href="dashboard.php" class="back">← Dashboard</a>
  <span>Assessment</span>
</header>
<main class="container">
  <h1>Career interest assessment</h1>
  <p class="muted">Rate how much you agree with each statement (1 = strongly disagree, 5 = strongly agree).</p>
  <?php if (!empty($error)): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="post" class="card form">
    <?php foreach ($questions as $q): ?>
      <div class="question">
        <p><?= htmlspecialchars($q['question']) ?></p>
        <div class="likert">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <label class="likert-opt">
              <input type="radio" name="q<?= (int)$q['id'] ?>" value="<?= $i ?>" required>
              <span><?= $i ?></span>
            </label>
          <?php endfor; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn primary">Get my recommendations</button>
  </form>
</main>
</body>
</html>