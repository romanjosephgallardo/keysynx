<?php
/**
 * KeySynx — Email verification handler (form POST, not AJAX)
 * Checks the 6-digit code against verification_code + verification_expires.
 * On success: marks the account verified, clears the code, and logs the
 * user in (sets the session) — verification IS the final login step.
 */

session_start();
require_once __DIR__ . '/db.php';
$db = getDb();

$username = trim($_POST['username'] ?? '');
$code = trim($_POST['code'] ?? '');
$redirect = $_POST['redirect'] ?? 'index.php';
$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

function backToVerify($redirect, $sep, $username, $errorMsg) {
    header('Location: ' . $redirect . $sep . 'verify=1&vu=' . urlencode($username) . '&auth_error=' . urlencode($errorMsg));
    exit;
}

$stmt = $db->prepare('SELECT id, verification_code, verification_expires, email_verified FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) backToVerify($redirect, $sep, $username, 'Account not found.');
if ($row['email_verified']) { header('Location: ' . $redirect); exit; } // already verified, nothing to do
if (!$row['verification_code'] || $row['verification_code'] !== $code) backToVerify($redirect, $sep, $username, 'Invalid code.');
if (strtotime($row['verification_expires']) < time()) backToVerify($redirect, $sep, $username, 'Code expired. Tap "Resend code" for a new one.');

$stmt = $db->prepare('UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ?');
$stmt->bind_param('i', $row['id']);
$stmt->execute();

$_SESSION['user_id'] = $row['id'];
header('Location: ' . $redirect);
exit;
