<?php
/**
 * KeySynx — Login handler (form POST, not AJAX)
 * Sets the session and redirects back to wherever the form was submitted
 * from. Using a real form post here (not fetch/JSON) means login works
 * even with JavaScript disabled, and there's no client-side timing to race.
 */

session_start();
require_once __DIR__ . '/db.php';
$db = getDb();

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$redirect = $_POST['redirect'] ?? 'index.php';

$stmt = $db->prepare('SELECT id, password_hash FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

if ($row && password_verify($password, $row['password_hash'])) {
    $_SESSION['user_id'] = $row['id'];
    header('Location: ' . $redirect);
} else {
    header('Location: ' . $redirect . $sep . 'auth_error=' . urlencode('Invalid username or password.'));
}
exit;
