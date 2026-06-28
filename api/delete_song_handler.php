<?php
/**
 * KeySynx — Delete song handler (form POST, not AJAX)
 * STRICTLY admin-only. Unlike moderate_handler.php's "delete" action
 * (open to moderator-tier users, 100+ rep), this endpoint is only for
 * true admins, triggered from the song detail page itself.
 *
 * Related rows (song_comments, song_sections, votes) are removed
 * automatically via ON DELETE CASCADE — no manual cleanup needed.
 */

session_start();
require_once __DIR__ . '/db.php';
$db = getDb();

$redirect = $_POST['redirect'] ?? '../index.html';
$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Log in required.'));
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();

if (!$me || $me['role'] !== 'admin') {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Admin access required to delete songs.'));
    exit;
}

$songId = (int) ($_POST['song_id'] ?? 0);
if ($songId <= 0) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Invalid song.'));
    exit;
}

$stmt = $db->prepare('DELETE FROM songs WHERE id = ?');
$stmt->bind_param('i', $songId);
$stmt->execute();

// If we were on that song's own detail page, it no longer exists —
// always bounce to the library instead of back to a 404'd song.php?id=X.
// NOTE: this handler lives in api/, so the path must go up one level —
// a bare 'index.html' would resolve to api/index.html and 404.
if (str_contains($redirect, 'song.php')) {
    $redirect = '../index.html';
}

header('Location: ' . $redirect . (str_contains($redirect, '?') ? '&' : '?') . 'success=' . urlencode('Song deleted.'));
exit;
