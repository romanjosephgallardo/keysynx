<?php
/**
 * KeySynx — Vote handler (form POST, not AJAX)
 * Same logic as api/vote.php, but redirect-based for the
 * server-rendered song.php page instead of returning JSON.
 */

session_start();
require_once __DIR__ . '/db.php';
$db = getDb();

$redirect = $_POST['redirect'] ?? 'index.php';
$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . $redirect . $sep . 'auth_error=' . urlencode('Log in to vote.'));
    exit;
}

$songId = (int) ($_POST['song_id'] ?? 0);
$voteType = $_POST['vote_type'] ?? '';
$userId = $_SESSION['user_id'];

if (!$songId || !in_array($voteType, ['up', 'down'], true)) {
    header('Location: ' . $redirect);
    exit;
}

$stmt = $db->prepare('SELECT id, vote_type FROM votes WHERE song_id = ? AND user_id = ?');
$stmt->bind_param('ii', $songId, $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

$db->begin_transaction();
try {
    if ($existing) {
        if ($existing['vote_type'] !== $voteType) {
            $oldCol = $existing['vote_type'] === 'up' ? 'upvotes' : 'downvotes';
            $newCol = $voteType === 'up' ? 'upvotes' : 'downvotes';
            $db->query("UPDATE songs SET $oldCol = $oldCol - 1, $newCol = $newCol + 1 WHERE id = $songId");
            $stmt = $db->prepare('UPDATE votes SET vote_type = ? WHERE id = ?');
            $stmt->bind_param('si', $voteType, $existing['id']);
            $stmt->execute();
        }
        // same vote clicked again -> no-op, not an error
    } else {
        $col = $voteType === 'up' ? 'upvotes' : 'downvotes';
        $db->query("UPDATE songs SET $col = $col + 1 WHERE id = $songId");
        $stmt = $db->prepare('INSERT INTO votes (song_id, user_id, vote_type) VALUES (?, ?, ?)');
        $stmt->bind_param('iis', $songId, $userId, $voteType);
        $stmt->execute();
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
}

header('Location: ' . $redirect);
exit;
