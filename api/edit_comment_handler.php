<?php
/**
 * KeySynx — Edit comment handler (form POST, not AJAX)
 * Strictly owner-only — unlike delete, even an admin cannot edit someone
 * else's words for them; admins can only remove (delete) inappropriate ones.
 */

session_start();
require_once __DIR__ . '/db.php';
$db = getDb();

$redirect = $_POST['redirect'] ?? 'index.html';
$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Log in required.'));
    exit;
}
$userId = $_SESSION['user_id'];
$commentId = (int) ($_POST['comment_id'] ?? 0);
$newText = trim($_POST['comment'] ?? '');

if (!$newText || strlen($newText) > 1000) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Comment must be between 1 and 1000 characters.'));
    exit;
}

$stmt = $db->prepare('SELECT user_id FROM song_comments WHERE id = ?');
$stmt->bind_param('i', $commentId);
$stmt->execute();
$comment = $stmt->get_result()->fetch_assoc();

if (!$comment || (int) $comment['user_id'] !== (int) $userId) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('You can only edit your own feedback.'));
    exit;
}

$stmt = $db->prepare('UPDATE song_comments SET comment = ? WHERE id = ?');
$stmt->bind_param('si', $newText, $commentId);
$stmt->execute();

header('Location: ' . $redirect);
exit;
