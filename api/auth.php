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

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $initials = strtoupper(substr($username, 0, 2));

    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, avatar_initials) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $username, $email, $hash, $initials);

    if (!$stmt->execute()) {
        jsonError($db->errno === 1062 ? 'Username or email already taken.' : 'Could not create account.');
    }

    $_SESSION['user_id'] = $db->insert_id;
    jsonResponse(['user' => currentUserPayload($db, $db->insert_id)], 201);
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        jsonError('Invalid username or password.', 401);
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
