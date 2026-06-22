<?php
/**
 * KeySynx — Update own profile (form POST, not AJAX)
 */

session_start();
require_once __DIR__ . '/db.php';
$db = getDb();

$redirect = $_POST['redirect'] ?? 'profile.php';
$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Log in required.'));
    exit;
}
$userId = $_SESSION['user_id'];

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';

if (!$username || !$email) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Username and email are required.'));
    exit;
}

if ($newPassword) {
    if (strlen($newPassword) < 6) {
        header('Location: ' . $redirect . $sep . 'error=' . urlencode('New password must be at least 6 characters.'));
        exit;
    }
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!password_verify($currentPassword, $row['password_hash'])) {
        header('Location: ' . $redirect . $sep . 'error=' . urlencode('Current password is incorrect.'));
        exit;
    }
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE users SET username=?, email=?, password_hash=? WHERE id=?');
    $stmt->bind_param('sssi', $username, $email, $hash, $userId);
} else {
    $stmt = $db->prepare('UPDATE users SET username=?, email=? WHERE id=?');
    $stmt->bind_param('ssi', $username, $email, $userId);
}

if ($stmt->execute()) {
    header('Location: ' . $redirect . $sep . 'success=' . urlencode('Profile updated.'));
} else {
    $msg = ($db->errno === 1062) ? 'Username or email already taken.' : 'Could not update profile.';
    header('Location: ' . $redirect . $sep . 'error=' . urlencode($msg));
}
exit;
