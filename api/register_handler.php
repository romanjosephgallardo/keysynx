<?php
/**
 * KeySynx — Register handler (form POST, not AJAX)
 * Accounts start unverified. A 6-digit code is emailed to the Gmail
 * address given, and the redirect carries ?verify=1&vu=<username> so
 * topbar.php knows to show the "enter your code" panel next, instead
 * of treating the user as logged in.
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
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
if (!preg_match('/^[^@\s]+@gmail\.com$/i', $email)) {
    header('Location: ' . $redirect . $sep . 'auth_error=' . urlencode('Please use a Gmail address (@gmail.com) — that\'s where your verification code goes.'));
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$initials = mb_strtoupper(mb_substr($username, 0, 2));

$stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    header('Location: ' . $redirect . $sep . 'auth_error=' . urlencode('That username is already taken. Try a different one, or log in instead.'));
    exit;
}
$stmt = $db->prepare('SELECT id, email_verified FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
if ($existing = $stmt->get_result()->fetch_assoc()) {
    $msg = $existing['email_verified']
        ? 'This Gmail address already has an account. Log in instead.'
        : 'This Gmail address already has a pending, unverified account. Check your Gmail for the code, or use "Resend code".';
    header('Location: ' . $redirect . $sep . 'auth_error=' . urlencode($msg));
    exit;
}

$code = generateVerificationCode();
$expires = date('Y-m-d H:i:s', time() + 900); // 15 minutes

$stmt = $db->prepare('INSERT INTO users (username, email, password_hash, avatar_initials, email_verified, verification_code, verification_expires) VALUES (?, ?, ?, ?, 0, ?, ?)');
$stmt->bind_param('ssssss', $username, $email, $hash, $initials, $code, $expires);

if ($stmt->execute()) {
    $emailSent = sendVerificationCode($email, $username, $code);
    $msg = $emailSent ? 'sent' : 'send_failed';
    header('Location: ' . $redirect . $sep . 'verify=1&vu=' . urlencode($username) . '&vmsg=' . $msg);
} else {
    header('Location: ' . $redirect . $sep . 'auth_error=' . urlencode('Could not create account. Please try again.'));
}
exit;
