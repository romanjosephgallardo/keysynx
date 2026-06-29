<?php
/**
 * KeySynx — Resend verification code handler (form POST, not AJAX)
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
$db = getDb();

$username = trim($_POST['username'] ?? '');
$redirect = $_POST['redirect'] ?? 'index.php';
$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

$stmt = $db->prepare('SELECT id, email, email_verified FROM users WHERE username = ?');
$stmt->bind_param('s', $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    header('Location: ' . $redirect . $sep . 'auth_error=' . urlencode('Account not found.'));
    exit;
}
if ($row['email_verified']) {
    header('Location: ' . $redirect);
    exit;
}

$code = generateVerificationCode();
$expires = date('Y-m-d H:i:s', time() + 900);
$stmt = $db->prepare('UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?');
$stmt->bind_param('ssi', $code, $expires, $row['id']);
$stmt->execute();

$emailSent = sendVerificationCode($row['email'], $username, $code);
$msg = $emailSent ? 'sent' : 'send_failed';
header('Location: ' . $redirect . $sep . 'verify=1&vu=' . urlencode($username) . '&vmsg=' . $msg);
exit;
