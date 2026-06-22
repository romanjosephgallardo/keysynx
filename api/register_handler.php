<?php
/**
 * KeySynx — Register handler (form POST, not AJAX)
 */

session_start();
require_once __DIR__ . '/db.php';
$db = getDb();

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$redirect = $_POST['redirect'] ?? 'index.php';
$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

if (!$username || !$email || strlen($password) < 6) {
    header('Location: ' . $redirect . $sep . 'auth_error=' . urlencode('Username, email, and a password of at least 6 characters are required.'));
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$initials = mb_strtoupper(mb_substr($username, 0, 2));

$stmt = $db->prepare('INSERT INTO users (username, email, password_hash, avatar_initials) VALUES (?, ?, ?, ?)');
$stmt->bind_param('ssss', $username, $email, $hash, $initials);

if ($stmt->execute()) {
    $_SESSION['user_id'] = $db->insert_id;
    header('Location: ' . $redirect);
} else {
    $msg = ($db->errno === 1062) ? 'Username or email already taken.' : 'Could not create account.';
    header('Location: ' . $redirect . $sep . 'auth_error=' . urlencode($msg));
}
exit;
