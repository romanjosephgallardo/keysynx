<?php
/**
 * KeySynx — Delete comment handler (form POST, not AJAX)
 * Only the comment's original author, or an admin, may delete it.
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

$stmt = $db->prepare('SELECT user_id FROM song_comments WHERE id = ?');
$stmt->bind_param('i', $commentId);
$stmt->execute();
$comment = $stmt->get_result()->fetch_assoc();

if (!$comment) { header('Location: ' . $redirect); exit; }

$stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$isAdmin = $me && $me['role'] === 'admin';
$isOwner = (int) $comment['user_id'] === (int) $userId;

if (!$isOwner && !$isAdmin) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('You can only delete your own feedback.'));
    exit;
}

$stmt = $db->prepare('DELETE FROM song_comments WHERE id = ?');
$stmt->bind_param('i', $commentId);
$stmt->execute();

header('Location: ' . $redirect);
exit;
