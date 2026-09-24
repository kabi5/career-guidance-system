<?php
session_start();
require_once 'config/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $grade = trim($_POST['grade'] ?? '');
    $school = trim($_POST['school'] ?? '');

    if (!$name || !$email || !$pass) {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        try {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO users (full_name,email,password_hash,grade,school) VALUES (?,?,?,?,?)');
            $stmt->execute([$name, $email, $hash, $grade, $school]);
            $_SESSION['user_id'] = db()->lastInsertId();
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Email already registered.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register · Career Compass</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="container">
  <a class="back" href="index.php">← Back</a>
  <h1>Create account</h1>
  <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" class="card form">
    <label>Full name *
      <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
    </label>
    <label>Email *
      <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </label>
    <label>Password * <small>(min 6 chars)</small>
      <input type="password" name="password" required minlength="6">
    </label>
    <label>Grade
      <select name="grade">
        <option value="">Select…</option>
        <?php foreach (['Grade 9','Grade 10','Grade 11','Grade 12'] as $g): ?>
          <option value="<?= $g ?>"><?= $g ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>School
      <input type="text" name="school" value="<?= htmlspecialchars($_POST['school'] ?? '') ?>">
    </label>
    <button type="submit" class="btn primary">Create account</button>
  </form>
  <p class="muted">Already have an account? <a href="login.php">Log in</a></p>
</main>
</body>
</html>