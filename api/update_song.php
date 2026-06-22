<?php
/**
 * KeySynx — Update song endpoint
 * POST { id, title, artist, bpm, musical_key, time_signature?, youtube_url?,
 *        section_keys?: [{section, key}], footnote? }
 * Only the song's original submitter, OR an admin, may edit it.
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/camelot.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required.', 405);
if (empty($_SESSION['user_id'])) jsonError('You must be logged in to edit a submission.', 401);

$db = getDb();
$userId = $_SESSION['user_id'];
$body = json_decode(file_get_contents('php://input'), true);
$songId = (int) ($body['id'] ?? 0);
if (!$songId) jsonError('id is required.');

// Ownership check: must be the original submitter, or an admin.
$stmt = $db->prepare('SELECT submitted_by FROM songs WHERE id = ?');
$stmt->bind_param('i', $songId);
$stmt->execute();
$song = $stmt->get_result()->fetch_assoc();
if (!$song) jsonError('Song not found.', 404);

$stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$isAdmin = $me && $me['role'] === 'admin';
$isOwner = $song['submitted_by'] !== null && (int) $song['submitted_by'] === (int) $userId;

if (!$isOwner && !$isAdmin) {
    jsonError('Only the original submitter or an admin can edit this entry.', 403);
}

$title = trim($body['title'] ?? '');
$artist = trim($body['artist'] ?? '');
$bpm = isset($body['bpm']) && $body['bpm'] !== '' ? (float) $body['bpm'] : null;
$musicalKey = trim($body['musical_key'] ?? '');
$timeSignature = $body['time_signature'] ?? null;
$footnote = $body['footnote'] ?? null;
$youtubeUrl = trim($body['youtube_url'] ?? '') ?: null;
$sectionKeys = $body['section_keys'] ?? [];

if (!$title || !$artist || !$musicalKey) {
    jsonError('title, artist, and musical_key are required.');
}
if ($youtubeUrl && !preg_match('#^https?://(www\.)?(youtube\.com/watch\?v=|youtu\.be/)[\w-]+#', $youtubeUrl)) {
    jsonError('youtube_url must be a valid YouTube link.');
}

$camelotCode = getCamelotCode($musicalKey);
$hasVariation = count($sectionKeys) > 0 ? 1 : 0;
$sectionKeysJson = $hasVariation ? json_encode($sectionKeys) : null;

$stmt = $db->prepare(
    'UPDATE songs SET title=?, artist=?, bpm=?, musical_key=?, camelot_code=?, time_signature=?,
     has_variation=?, section_keys=?, footnote=?, youtube_url=? WHERE id=?'
);
$stmt->bind_param(
    'ssdsssisssi',
    $title, $artist, $bpm, $musicalKey, $camelotCode, $timeSignature,
    $hasVariation, $sectionKeysJson, $footnote, $youtubeUrl, $songId
);
$stmt->execute();

// Refresh song_sections to match the edited section_keys
$db->query("DELETE FROM song_sections WHERE song_id = $songId");
$order = 1;
foreach ($sectionKeys as $sk) {
    $secCode = getCamelotCode($sk['key'] ?? '');
    $stmt = $db->prepare('INSERT INTO song_sections (song_id, section_name, order_index, musical_key, camelot_code) VALUES (?, ?, ?, ?, ?)');
    $secName = $sk['section'] ?? '';
    $secKey = $sk['key'] ?? '';
    $stmt->bind_param('isiss', $songId, $secName, $order, $secKey, $secCode);
    $stmt->execute();
    $order++;
}

jsonResponse(['ok' => true, 'message' => 'Song updated.']);
