<?php
/**
 * KeySynx — Auth endpoint
 * POST ?action=register   { username, email, password }
 * POST ?action=login      { username, password }
 * POST ?action=logout
 * GET  ?action=me         -> current session user, or null
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

$action = $_GET['action'] ?? '';
$db = getDb();

function currentUserPayload(mysqli $db, int $userId): array {
    $stmt = $db->prepare('SELECT id, username, email, role, reputation_points, avatar_initials, avatar_path FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $user['reputation_tier'] = reputationTier((float)$user['reputation_points']);
    return $user;
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $username = trim($body['username'] ?? '');
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if (!$username || !$email || strlen($password) < 6) {
        jsonError('Username, email, and a password of at least 6 characters are required.');
    }
    if (!preg_match('/^[^@\s]+@gmail\.com$/i', $email)) {
        jsonError('Please use a Gmail address (@gmail.com) to register — that\'s where your verification code will be sent.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $initials = strtoupper(substr($username, 0, 2));

    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        jsonError('That username is already taken. Try a different one, or log in instead.');
    }
    $stmt = $db->prepare('SELECT id, email_verified FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($existing = $stmt->get_result()->fetch_assoc()) {
        jsonError($existing['email_verified']
            ? 'This Gmail address already has an account. Log in instead.'
            : 'This Gmail address already has a pending, unverified account. Check your Gmail for the code, or use "Resend code" after logging in.');
    }

    $code = generateVerificationCode();
    $expires = date('Y-m-d H:i:s', time() + 900); // 15 minutes

    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, avatar_initials, email_verified, verification_code, verification_expires) VALUES (?, ?, ?, ?, 0, ?, ?)');
    $stmt->bind_param('ssssss', $username, $email, $hash, $initials, $code, $expires);

    if (!$stmt->execute()) {
        jsonError('Could not create account. Please try again.');
    }

    $emailSent = sendVerificationCode($email, $username, $code);
    jsonResponse([
        'pending_verification' => true,
        'username' => $username,
        'email_sent' => $emailSent,
        'message' => $emailSent
            ? 'Account created! Check your Gmail for a 6-digit verification code.'
            : 'Account created, but the verification email could not be sent. DEBUG: ' . getLastMailError(),
    ], 201);
}

if ($action === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $username = trim($body['username'] ?? '');
    $code = trim($body['code'] ?? '');

    $stmt = $db->prepare('SELECT id, verification_code, verification_expires, email_verified FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) jsonError('Account not found.', 404);
    if ($row['email_verified']) jsonError('This account is already verified — just log in.', 400);
    if (!$row['verification_code'] || $row['verification_code'] !== $code) jsonError('Invalid code.', 400);
    if (strtotime($row['verification_expires']) < time()) jsonError('Code expired. Tap "Resend code" for a new one.', 400);

    $stmt = $db->prepare('UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ?');
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();

    $_SESSION['user_id'] = $row['id'];
    jsonResponse(['user' => currentUserPayload($db, $row['id'])]);
}

if ($action === 'resend' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $username = trim($body['username'] ?? '');

    $stmt = $db->prepare('SELECT id, email, email_verified FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) jsonError('Account not found.', 404);
    if ($row['email_verified']) jsonError('This account is already verified — just log in.', 400);

    $code = generateVerificationCode();
    $expires = date('Y-m-d H:i:s', time() + 900);
    $stmt = $db->prepare('UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?');
    $stmt->bind_param('ssi', $code, $expires, $row['id']);
    $stmt->execute();

    $emailSent = sendVerificationCode($row['email'], $username, $code);
    jsonResponse(['email_sent' => $emailSent, 'message' => $emailSent ? 'New code sent — check your Gmail.' : 'DEBUG: ' . getLastMailError()]);
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    $stmt = $db->prepare('SELECT id, password_hash, email_verified FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        jsonError('Invalid username or password.', 401);
    }
    if (!$row['email_verified']) {
        jsonResponse(['error' => 'Please verify your email first — check your Gmail for the code.', 'needs_verification' => true, 'username' => $username], 403);
    }

    $_SESSION['user_id'] = $row['id'];
    jsonResponse(['user' => currentUserPayload($db, $row['id'])]);
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    jsonResponse(['ok' => true]);
}

if ($action === 'me') {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['user' => null]);
    }
    jsonResponse(['user' => currentUserPayload($db, $_SESSION['user_id'])]);
}

jsonError('Unknown action.', 404);
