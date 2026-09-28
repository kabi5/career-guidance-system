<?php
require_once 'config/db.php';
require_login();
header('Content-Type: application/json');

$input   = json_decode(file_get_contents('php://input'), true) ?: [];
$message = trim($input['message'] ?? '');
if ($message === '') {
    http_response_code(400);
    echo json_encode(['reply' => 'Please type a question.', 'sources' => []]);
    exit;
}
$message = mb_substr($message, 0, 500);

// Context: latest Holland code + subjects
$uid = $_SESSION['user_id'];
$stmt = db()->prepare('SELECT top_interest FROM assessments WHERE user_id = ? ORDER BY taken_at DESC LIMIT 1');
$stmt->execute([$uid]);
$a = $stmt->fetch();

$stmt = db()->prepare('SELECT subjects FROM learner_profiles WHERE user_id = ?');
$stmt->execute([$uid]);
$p = $stmt->fetch();

$payload = [
    'message' => $message,
    'context' => [
        'riasec_code' => $a['top_interest'] ?? '',
        'subjects'    => array_values(array_filter(array_map('trim', explode(',', $p['subjects'] ?? '')))),
    ],
];

$ch = curl_init(AI_API . '/chat');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 15,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 200 && $resp) {
    echo $resp;
} else {
    http_response_code(502);
    echo json_encode(['reply' => 'The assistant is offline right now. Please try again later.', 'sources' => []]);
}