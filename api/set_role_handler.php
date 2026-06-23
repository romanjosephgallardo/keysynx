<?php
/**
 * KeySynx — Set user role handler (form POST, not AJAX)
 * Strictly restricted to true admins — reaching 100+ reputation grants
 * moderation access (see hasModeratorAccess), but NOT the ability to
 * promote/demote other users. That stays a real-admin-only action.
 */

session_start();
require_once __DIR__ . '/db.php';
$db = getDb();

$redirect = $_POST['redirect'] ?? 'admin.php';
$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

$adminId = $_SESSION['user_id'] ?? null;
$me = null;
if ($adminId) {
    $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
}

if (!isTrueAdmin($me)) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Admin access required to manage roles.'));
    exit;
}

$targetUserId = (int) ($_POST['user_id'] ?? 0);
$newRole = $_POST['role'] ?? '';

if (!$targetUserId || !in_array($newRole, ['user', 'admin'], true)) {
    header('Location: ' . $redirect);
    exit;
}

$stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
$stmt->bind_param('si', $newRole, $targetUserId);
$stmt->execute();

header('Location: ' . $redirect);
exit;
