<?php
/**
 * KeySynx — Artist artwork upload handler (form POST, not AJAX)
 * Gated to moderator access (same tier as the rest of the admin panel),
 * not just true admins — this is content curation, not role management.
 */

session_start();
require_once __DIR__ . '/db.php';
$db = getDb();

$redirect = $_POST['redirect'] ?? '../admin.php';
$sep = (strpos($redirect, '?') !== false) ? '&' : '?';

$userId = $_SESSION['user_id'] ?? null;
$me = null;
if ($userId) {
    $stmt = $db->prepare('SELECT role, reputation_points FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
}
if (!hasModeratorAccess($me)) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Moderator access required.'));
    exit;
}

$artistId = (int) ($_POST['artist_id'] ?? 0);
if (!$artistId || empty($_FILES['artwork']) || $_FILES['artwork']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('No artist or file selected.'));
    exit;
}

$file = $_FILES['artwork'];
$allowedTypes = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
$mime = mime_content_type($file['tmp_name']);

if (!isset($allowedTypes[$mime])) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Only PNG, JPEG, or WEBP images are allowed.'));
    exit;
}
if ($file['size'] > 5 * 1024 * 1024) { // 5MB cap
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Image must be under 5MB.'));
    exit;
}

$uploadDir = __DIR__ . '/../uploads/artists';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = 'artist_' . $artistId . '_' . time() . '.' . $allowedTypes[$mime];
$destPath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    header('Location: ' . $redirect . $sep . 'error=' . urlencode('Could not save the uploaded file.'));
    exit;
}

// Remove the old artwork file (if any) so uploads/artists/ doesn't accumulate orphans.
$stmt = $db->prepare('SELECT image_path FROM artists WHERE id = ?');
$stmt->bind_param('i', $artistId);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();
if ($old && !empty($old['image_path'])) {
    $oldFullPath = __DIR__ . '/../' . $old['image_path'];
    if (is_file($oldFullPath)) @unlink($oldFullPath);
}

$relativePath = 'uploads/artists/' . $filename;
$stmt = $db->prepare('UPDATE artists SET image_path = ? WHERE id = ?');
$stmt->bind_param('si', $relativePath, $artistId);
$stmt->execute();

header('Location: ' . $redirect);
exit;
