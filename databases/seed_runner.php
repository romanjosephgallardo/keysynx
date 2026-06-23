<?php
/**
 * KeySynx — Bulk seed runner
 *
 * Run this ONCE after importing schema.sql (and migration_v4.sql if you
 * already had data) to populate the database with all 5 artists.
 *
 * Usage:
 *   Browser:  http://localhost/keysynx/database/seed_runner.php
 *   CLI:      php seed_runner.php   (run from inside the database/ folder)
 *
 * Safe to re-run: skips any song that already exists for the same
 * title + artist + album (checked before each insert).
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/camelot.php';

$data = require __DIR__ . '/seed_data.php';
$db = getDb();

$albumsInserted = 0;
$songsInserted = 0;
$songsSkipped = 0;
$sectionsInserted = 0;

foreach ($data as $artist => $albums) {
    foreach ($albums as $albumTitle => $songs) {

        // Find or create the album
        $stmt = $db->prepare('SELECT id FROM albums WHERE title = ? AND artist = ?');
        $stmt->bind_param('ss', $albumTitle, $artist);
        $stmt->execute();
        $albumRow = $stmt->get_result()->fetch_assoc();

        if ($albumRow) {
            $albumId = $albumRow['id'];
        } else {
            $stmt = $db->prepare('INSERT INTO albums (title, artist) VALUES (?, ?)');
            $stmt->bind_param('ss', $albumTitle, $artist);
            $stmt->execute();
            $albumId = $db->insert_id;
            $albumsInserted++;
        }

        foreach ($songs as $song) {
            [$title, $bpm, $key, $timeSig, $footnote, $sections] = $song;

            // Skip if this exact song already exists in this album
            $stmt = $db->prepare('SELECT id FROM songs WHERE title = ? AND artist = ? AND album_id = ?');
            $stmt->bind_param('ssi', $title, $artist, $albumId);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $songsSkipped++;
                continue;
            }

            $camelotCode = getCamelotCode($key);
            $hasVariation = count($sections) > 0 ? 1 : 0;
            $sectionKeysJson = $hasVariation
                ? json_encode(array_map(fn($s) => ['section' => $s[0], 'key' => $s[1]], $sections))
                : null;

            $stmt = $db->prepare(
                'INSERT INTO songs (album_id, title, artist, bpm, musical_key, camelot_code, time_signature, has_variation, section_keys, footnote, status, submitted_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "verified", NULL)'
            );
            $stmt->bind_param(
                'issdsssiss',
                $albumId, $title, $artist, $bpm, $key, $camelotCode, $timeSig, $hasVariation, $sectionKeysJson, $footnote
            );
            $stmt->execute();
            $songId = $db->insert_id;
            $songsInserted++;

            // Persist section rows for the song-section timeline UI
            $order = 1;
            foreach ($sections as [$label, $sectionKey, $sectionBpm]) {
                $sectionCode = getCamelotCode($sectionKey);
                $stmt = $db->prepare(
                    'INSERT INTO song_sections (song_id, section_name, order_index, bpm, musical_key, camelot_code) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('isidss', $songId, $label, $order, $sectionBpm, $sectionKey, $sectionCode);
                $stmt->execute();
                $sectionsInserted++;
                $order++;
            }
        }
    }
}

$summary = [
    'albums_inserted' => $albumsInserted,
    'songs_inserted' => $songsInserted,
    'songs_skipped_already_existed' => $songsSkipped,
    'sections_inserted' => $sectionsInserted,
    'total_songs_in_db_now' => $db->query('SELECT COUNT(*) c FROM songs')->fetch_assoc()['c'],
];

if (php_sapi_name() === 'cli') {
    print_r($summary);
} else {
    header('Content-Type: application/json');
    echo json_encode($summary, JSON_PRETTY_PRINT);
}
