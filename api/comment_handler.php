<?php
/**
 * KeySynx — Comment handler (form POST, not AJAX)
 */

session_start();
require_once __DIR__ . '/db.php';
$db = getDb();

$redirect = $_POST['redirect'] ?? 'index.php';
$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . $redirect . $sep . 'auth_error=' . urlencode('Log in to leave feedback.'));
    exit;
}

$songId = (int) ($_POST['song_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($songId && $comment && strlen($comment) <= 1000) {
    $userId = $_SESSION['user_id'];
    $stmt = $db->prepare('INSERT INTO song_comments (song_id, user_id, comment) VALUES (?, ?, ?)');
    $stmt->bind_param('iis', $songId, $userId, $comment);
    $stmt->execute();
}

header('Location: ' . $redirect);
exit;
