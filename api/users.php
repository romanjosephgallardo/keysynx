<?php
/**
 * KeySynx — Users endpoint (admin only)
 * GET -> list all users with role + reputation, for the
 * Admin Moderation Panel's "handle user roles" feature.
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) jsonError('Login required.', 401);

$db = getDb();
$meId = $_SESSION['user_id'];
$stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
$stmt->bind_param('i', $meId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
if (!$me || $me['role'] !== 'admin') jsonError('Admin access required.', 403);

$res = $db->query('SELECT id, username, email, role, reputation_points, created_at FROM users ORDER BY reputation_points DESC');
$users = $res->fetch_all(MYSQLI_ASSOC);
foreach ($users as &$u) {
    $u['reputation_tier'] = reputationTier((int) $u['reputation_points']);
}

jsonResponse(['users' => $users]);
