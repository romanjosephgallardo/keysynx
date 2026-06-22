<?php
/**
 * KeySynx — Contributor feedback (comments) endpoint
 * GET  ?song_id=X         -> list comments for a song
 * POST { song_id, comment } -> add a comment (requires login)
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $songId = (int) ($_GET['song_id'] ?? 0);
    if (!$songId) jsonError('song_id is required.');

    $stmt = $db->prepare(
        'SELECT c.id, c.comment, c.created_at, c.user_id, u.username, u.reputation_points
         FROM song_comments c JOIN users u ON u.id = c.user_id
         WHERE c.song_id = ? ORDER BY c.created_at DESC'
    );
    $stmt->bind_param('i', $songId);
    $stmt->execute();
    jsonResponse(['comments' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['user_id'])) jsonError('Login required to comment.', 401);

    $body = json_decode(file_get_contents('php://input'), true);
    $songId = (int) ($body['song_id'] ?? 0);
    $comment = trim($body['comment'] ?? '');

    if (!$songId || !$comment) jsonError('song_id and comment are required.');
    if (strlen($comment) > 1000) jsonError('Comment is too long (max 1000 characters).');

    $userId = $_SESSION['user_id'];
    $stmt = $db->prepare('INSERT INTO song_comments (song_id, user_id, comment) VALUES (?, ?, ?)');
    $stmt->bind_param('iis', $songId, $userId, $comment);
    $stmt->execute();

    jsonResponse(['id' => $db->insert_id], 201);
}

jsonError('Method not allowed.', 405);
