<?php
/**
 * KeySynx — Submission endpoint
 * POST { title, artist, bpm, musical_key, time_signature?, tags?,
 *        footnote?, section_keys?: [{section, key}] }
 * Requires login. New songs start as 'pending'. The DB trigger
 * trg_submission_reward grants +10 reputation immediately.
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/camelot.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required.', 405);
if (empty($_SESSION['user_id'])) jsonError('You must be logged in to submit an analysis.', 401);

$db = getDb();
$body = json_decode(file_get_contents('php://input'), true);

$title = trim($body['title'] ?? '');
$artist = trim($body['artist'] ?? '');
$bpm = isset($body['bpm']) && $body['bpm'] !== '' ? (float) $body['bpm'] : null;
$musicalKey = trim($body['musical_key'] ?? '');
$timeSignature = $body['time_signature'] ?? null;
$tags = $body['tags'] ?? null;
$footnote = $body['footnote'] ?? null;
$youtubeUrl = trim($body['youtube_url'] ?? '') ?: null;
$sectionKeys = $body['section_keys'] ?? [];

if (!$title || !$artist || !$musicalKey) {
    jsonError('title, artist, and musical_key are required.');
}
if ($youtubeUrl && !preg_match('#^https?://(www\.)?(youtube\.com/watch\?v=|youtu\.be/)[\w-]+#', $youtubeUrl)) {
    jsonError('youtube_url must be a valid YouTube link (youtube.com/watch?v=... or youtu.be/...).');
}

$camelotCode = getCamelotCode($musicalKey);
$hasVariation = count($sectionKeys) > 0 ? 1 : 0;
$sectionKeysJson = $hasVariation ? json_encode($sectionKeys) : null;
$tagsStr = is_array($tags) ? implode(',', $tags) : $tags;
$userId = $_SESSION['user_id'];

$stmt = $db->prepare(
    'INSERT INTO songs (title, artist, bpm, musical_key, camelot_code, time_signature, has_variation, section_keys, footnote, youtube_url, tags, submitted_by, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
);
$stmt->bind_param(
    'ssdsssissssi',
    $title, $artist, $bpm, $musicalKey, $camelotCode, $timeSignature,
    $hasVariation, $sectionKeysJson, $footnote, $youtubeUrl, $tagsStr, $userId
);

if (!$stmt->execute()) {
    jsonError('Could not save submission: ' . $db->error, 500);
}

$songId = $db->insert_id;

// Also persist a proper section_sections row for each section key,
// so the section-timeline UI can render it.
if ($hasVariation) {
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
}

jsonResponse(['id' => $songId, 'message' => 'Submitted! This entry is pending community validation.'], 201);
