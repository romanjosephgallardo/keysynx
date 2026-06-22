<?php
/**
 * KeySynx — Stats endpoint
 * GET -> { songs, users, artists, albums }
 * Powers the homepage hero stat cards.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$db = getDb();

$songs = $db->query('SELECT COUNT(*) AS c FROM songs')->fetch_assoc()['c'];
$users = $db->query('SELECT COUNT(*) AS c FROM users')->fetch_assoc()['c'];
$artists = $db->query('SELECT COUNT(DISTINCT artist) AS c FROM songs')->fetch_assoc()['c'];
$albums = $db->query('SELECT COUNT(*) AS c FROM albums')->fetch_assoc()['c'];

jsonResponse([
    'songs' => (int) $songs,
    'users' => (int) $users,
    'artists' => (int) $artists,
    'albums' => (int) $albums,
]);
