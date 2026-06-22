<?php
/**
 * KeySynx — Vote endpoint
 * POST { song_id, vote_type: "up"|"down" }
 * Requires an active session (logged-in user).
 * One vote per user per song — enforced by votes.unique_vote.
 * Auto-verify / reputation updates happen via the DB triggers
 * once upvotes/downvotes counts change (see schema.sql).
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required.', 405);
if (empty($_SESSION['user_id'])) jsonError('You must be logged in to vote.', 401);

$db = getDb();
$body = json_decode(file_get_contents('php://input'), true);
$songId = (int) ($body['song_id'] ?? 0);
$voteType = $body['vote_type'] ?? '';
$userId = $_SESSION['user_id'];

if (!$songId || !in_array($voteType, ['up', 'down'], true)) {
    jsonError('song_id and a valid vote_type are required.');
}

// Check for an existing vote from this user on this song
$stmt = $db->prepare('SELECT id, vote_type FROM votes WHERE song_id = ? AND user_id = ?');
$stmt->bind_param('ii', $songId, $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

$db->begin_transaction();
try {
    if ($existing) {
        if ($existing['vote_type'] === $voteType) {
            jsonError('You already cast this vote.', 409);
        }
        // switching vote: undo old, apply new
        $oldCol = $existing['vote_type'] === 'up' ? 'upvotes' : 'downvotes';
        $newCol = $voteType === 'up' ? 'upvotes' : 'downvotes';
        $db->query("UPDATE songs SET $oldCol = $oldCol - 1, $newCol = $newCol + 1 WHERE id = $songId");

        $stmt = $db->prepare('UPDATE votes SET vote_type = ? WHERE id = ?');
        $stmt->bind_param('si', $voteType, $existing['id']);
        $stmt->execute();
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
    jsonError('Vote failed: ' . $e->getMessage(), 500);
}

$stmt = $db->prepare('SELECT id, upvotes, downvotes, status FROM songs WHERE id = ?');
$stmt->bind_param('i', $songId);
$stmt->execute();
jsonResponse(['song' => $stmt->get_result()->fetch_assoc()]);
