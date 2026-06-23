<?php
/**
 * KeySynx — Song moderation handler (form POST, not AJAX)
 * Approve/reject/delete. Available to true admins AND to any user who's
 * reached 100+ reputation points (Moderator tier) — see hasModeratorAccess().
 */

session_start();
require_once __DIR__ . '/db.php';
$db = getDb();

$redirect = $_POST['redirect'] ?? 'admin.php';
$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

$userId = $_SESSION['user_id'] ?? null;
$me = null;
if ($userId) {
    $stmt = $db->prepare('SELECT role, reputation_points FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
}

if (!hasModeratorAccess($me)) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Moderator access required.'));
    exit;
}

$action = $_POST['action'] ?? '';
$songId = (int) ($_POST['song_id'] ?? 0);

if (!$songId || !in_array($action, ['approve', 'reject', 'delete'], true)) {
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'approve') {
    $db->query("UPDATE songs SET status = 'verified', verified_at = NOW() WHERE id = $songId");
    $stmt = $db->prepare('INSERT INTO moderation_log (song_id, admin_id, action) VALUES (?, ?, "approved")');
    $stmt->bind_param('ii', $songId, $userId);
    $stmt->execute();
} elseif ($action === 'reject') {
    $db->query("UPDATE songs SET status = 'rejected' WHERE id = $songId");
    $stmt = $db->prepare('INSERT INTO moderation_log (song_id, admin_id, action) VALUES (?, ?, "rejected")');
    $stmt->bind_param('ii', $songId, $userId);
    $stmt->execute();
} else {
    // Deleting the song cascades to moderation_log rows referencing it anyway,
    // so there's no point logging "deleted" against a song_id that's about
    // to stop existing — just perform the delete.
    $db->query("DELETE FROM songs WHERE id = $songId");
}

header('Location: ' . $redirect);
exit;
