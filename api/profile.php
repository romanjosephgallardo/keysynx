<?php
/**
 * KeySynx — Profile endpoint
 * GET                -> current user's profile, reputation log, and submission stats
 * POST { username?, email?, current_password?, new_password? } -> update profile
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) jsonError('Login required.', 401);
$db = getDb();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT id, username, email, role, reputation_points, created_at FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $user['reputation_tier'] = reputationTier((int) $user['reputation_points']);

    $stmt = $db->prepare('SELECT points, reason, created_at, song_id FROM reputation_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $log = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $db->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(status = 'verified') AS verified,
            SUM(status = 'pending') AS pending,
            SUM(status = 'rejected') AS rejected
         FROM songs WHERE submitted_by = ?"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    jsonResponse(['user' => $user, 'reputation_log' => $log, 'submission_stats' => $stats]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $username = trim($body['username'] ?? '');
    $email = trim($body['email'] ?? '');
    $currentPassword = $body['current_password'] ?? '';
    $newPassword = $body['new_password'] ?? '';

    if (!$username || !$email) jsonError('Username and email are required.');

    if ($newPassword) {
        if (strlen($newPassword) < 6) jsonError('New password must be at least 6 characters.');
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!password_verify($currentPassword, $row['password_hash'])) {
            jsonError('Current password is incorrect.', 401);
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET username=?, email=?, password_hash=? WHERE id=?');
        $stmt->bind_param('sssi', $username, $email, $hash, $userId);
    } else {
        $stmt = $db->prepare('UPDATE users SET username=?, email=? WHERE id=?');
        $stmt->bind_param('ssi', $username, $email, $userId);
    }

    if (!$stmt->execute()) {
        jsonError($db->errno === 1062 ? 'Username or email already taken.' : 'Could not update profile.');
    }

    jsonResponse(['ok' => true, 'message' => 'Profile updated.']);
}

jsonError('Method not allowed.', 405);
