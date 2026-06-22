<?php
/**
 * KeySynx — Admin moderation endpoint
 * POST { action: "approve"|"reject"|"delete"|"set_role", song_id?, user_id?, role?, notes? }
 * Requires an admin session.
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required.', 405);
if (empty($_SESSION['user_id'])) jsonError('Login required.', 401);

$db = getDb();
$adminId = $_SESSION['user_id'];

$stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
$stmt->bind_param('i', $adminId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
if (!$me || $me['role'] !== 'admin') jsonError('Admin access required.', 403);

$body = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

if (in_array($action, ['approve', 'reject', 'delete'], true)) {
    $songId = (int) ($body['song_id'] ?? 0);
    if (!$songId) jsonError('song_id is required.');

    if ($action === 'approve') {
        $db->query("UPDATE songs SET status = 'verified', verified_at = NOW() WHERE id = $songId");
        $logAction = 'approved';
    } elseif ($action === 'reject') {
        $db->query("UPDATE songs SET status = 'rejected' WHERE id = $songId");
        $logAction = 'rejected';
    } else {
        $db->query("DELETE FROM songs WHERE id = $songId");
        $logAction = 'deleted';
    }

    $notes = $body['notes'] ?? null;
    $stmt = $db->prepare('INSERT INTO moderation_log (song_id, admin_id, action, notes) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('iiss', $songId, $adminId, $logAction, $notes);
    $stmt->execute();

    jsonResponse(['ok' => true, 'action' => $logAction]);
}

if ($action === 'set_role') {
    $userId = (int) ($body['user_id'] ?? 0);
    $role = $body['role'] ?? '';
    if (!$userId || !in_array($role, ['user', 'admin'], true)) {
        jsonError('user_id and a valid role are required.');
    }
    $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
    $stmt->bind_param('si', $role, $userId);
    $stmt->execute();
    jsonResponse(['ok' => true]);
}

jsonError('Unknown action.', 404);
