<?php
/**
 * KeySynx — Avatar upload handler (form POST, multipart)
 * Always operates on the logged-in user's OWN account — there's no
 * user_id parameter, so there's nothing to spoof: you can only ever
 * upload your own avatar.
 */

session_start();
require_once __DIR__ . '/db.php';
$db = getDb();

$redirect = $_POST['redirect'] ?? 'profile.php';
$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Log in to update your avatar.'));
    exit;
}
$userId = $_SESSION['user_id'];

if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('No file uploaded, or the upload failed.'));
    exit;
}

$file = $_FILES['avatar'];
$maxBytes = 2 * 1024 * 1024; // 2MB
if ($file['size'] > $maxBytes) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Image must be 2MB or smaller.'));
    exit;
}

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$mime = mime_content_type($file['tmp_name']);
if (!isset($allowed[$mime])) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Only JPG, PNG, WEBP, or GIF images are allowed.'));
    exit;
}

$ext = $allowed[$mime];
$uploadDir = __DIR__ . '/../uploads/avatars';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Remove any previous avatar file for this user before saving the new one
$existing = glob($uploadDir . "/user_{$userId}.*");
foreach ($existing as $oldFile) { @unlink($oldFile); }

$filename = "user_{$userId}.{$ext}";
$destPath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Could not save the uploaded file.'));
    exit;
}

// Cache-bust with a timestamp query string so the new picture shows immediately
$relativePath = 'uploads/avatars/' . $filename . '?t=' . time();

$stmt = $db->prepare('UPDATE users SET avatar_path = ? WHERE id = ?');
$stmt->bind_param('si', $relativePath, $userId);
$stmt->execute();

header('Location: ' . $redirect);
exit;
