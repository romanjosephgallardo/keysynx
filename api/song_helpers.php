<?php
/**
 * KeySynx — shared song-detail helper
 * Used by both api/songs.php (JSON endpoint) and song.php
 * (server-rendered page) so the logic only lives in one place.
 */

require_once __DIR__ . '/camelot.php';

function youtubeThumbnail(?string $url): ?string {
    if (!$url) return null;
    if (!preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]{6,})#', $url, $m)) return null;
    return "https://img.youtube.com/vi/{$m[1]}/hqdefault.jpg";
}

function computeConfidenceScore(mysqli $db, array $song): int {
    $totalVotes = $song['upvotes'] + $song['downvotes'];
    $validationFactor = min(1, $totalVotes / 10);
    $agreementFactor = $totalVotes > 0 ? $song['upvotes'] / $totalVotes : 0;

    $stmt = $db->prepare(
        'SELECT AVG(u.reputation_points) AS avg_rep FROM votes v
         JOIN users u ON u.id = v.user_id WHERE v.song_id = ?'
    );
    $stmt->bind_param('i', $song['id']);
    $stmt->execute();
    $avgRep = $stmt->get_result()->fetch_assoc()['avg_rep'] ?? 0;
    $reputationFactor = min(1, ($avgRep ?? 0) / 100);

    $score = ($validationFactor * 40) + ($agreementFactor * 30) + ($reputationFactor * 30);
    return (int) round($score);
}

/**
 * Returns the full detail payload for a song: base fields + album info +
 * sections + comments + confidence score + thumbnail + top 5 recommendations.
 * Returns null if the song doesn't exist.
 */
function getSongFullDetail(mysqli $db, int $id): ?array {
    $stmt = $db->prepare(
        'SELECT s.*, u.username AS submitted_by_username, a.title AS album_title, a.release_year
         FROM songs s
         LEFT JOIN users u ON u.id = s.submitted_by
         LEFT JOIN albums a ON a.id = s.album_id
         WHERE s.id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $song = $stmt->get_result()->fetch_assoc();
    if (!$song) return null;

    $stmt = $db->prepare('SELECT section_name, order_index, bpm, musical_key, camelot_code FROM song_sections WHERE song_id = ? ORDER BY order_index');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $song['sections'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $db->prepare(
        'SELECT c.id, c.comment, c.created_at, c.user_id, u.username, u.reputation_points, u.avatar_path
         FROM song_comments c JOIN users u ON u.id = c.user_id
         WHERE c.song_id = ? ORDER BY c.created_at DESC'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $song['comments'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $allSongs = $db->query('SELECT * FROM songs')->fetch_all(MYSQLI_ASSOC);
    $recommendations = [];
    foreach ($allSongs as $other) {
        if ($other['id'] == $song['id']) continue;
        $result = computeTransitionScore($song, $other);
        if ($result['score'] > 0) {
            $result['song'] = $other;
            $recommendations[] = $result;
        }
    }
    usort($recommendations, fn($a, $b) => $b['score'] - $a['score']);
    $song['recommendations'] = array_slice($recommendations, 0, 5);

    $song['confidence_score'] = computeConfidenceScore($db, $song);
    $song['thumbnail_url'] = youtubeThumbnail($song['youtube_url']);

    return $song;
}
