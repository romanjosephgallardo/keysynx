<?php
/**
 * KeySynx — Songs endpoint
 *
 * GET songs.php
 *   ?q=               search title/artist
 *   ?key=             exact musical_key match
 *   ?bpm_min=&bpm_max=
 *   ?verified_only=1
 *   ?compatible_with=<song_id>   only return tracks harmonically
 *                                 compatible with this song (Advanced
 *                                 Search Feature #5 — Camelot compatibility)
 *
 * GET songs.php?id=<id>
 *   Single song with: section timeline, contributor feedback,
 *   confidence score, and top 5 recommended transitions.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/camelot.php';

$db = getDb();

/**
 * Confidence Score System (Beyond-MVP feature #2).
 * Our formula (not specified in the proposal, so documented here):
 *   40% validation count   — total votes, maxes out at 10 votes
 *   30% agreement ratio    — upvotes / total votes
 *   30% contributor rep    — avg reputation of everyone who voted, maxes out at 100 pts
 */
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

function fetchAllSongs(mysqli $db): array {
    $res = $db->query('SELECT * FROM songs ORDER BY id');
    return $res->fetch_all(MYSQLI_ASSOC);
}

// ---------------- Single song detail ----------------
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    $stmt = $db->prepare(
        'SELECT s.*, u.username AS submitted_by_username
         FROM songs s LEFT JOIN users u ON u.id = s.submitted_by
         WHERE s.id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $song = $stmt->get_result()->fetch_assoc();

    if (!$song) jsonError('Song not found.', 404);

    $stmt = $db->prepare('SELECT section_name, order_index, bpm, musical_key, camelot_code FROM song_sections WHERE song_id = ? ORDER BY order_index');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $db->prepare(
        'SELECT c.id, c.comment, c.created_at, u.username, u.reputation_points
         FROM song_comments c JOIN users u ON u.id = c.user_id
         WHERE c.song_id = ? ORDER BY c.created_at DESC'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $allSongs = fetchAllSongs($db);
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
    $recommendations = array_slice($recommendations, 0, 5);

    $song['confidence_score'] = computeConfidenceScore($db, $song);
    $song['sections'] = $sections;
    $song['comments'] = $comments;
    $song['recommendations'] = $recommendations;

    jsonResponse($song);
}

// ---------------- List / search / filter ----------------
$conditions = [];
$params = [];
$types = '';

if (!empty($_GET['q'])) {
    $conditions[] = '(title LIKE ? OR artist LIKE ?)';
    $like = '%' . $_GET['q'] . '%';
    $params[] = $like; $params[] = $like;
    $types .= 'ss';
}
if (!empty($_GET['key'])) {
    $conditions[] = 'musical_key = ?';
    $params[] = $_GET['key'];
    $types .= 's';
}
if (!empty($_GET['bpm_min'])) {
    $conditions[] = 'bpm >= ?';
    $params[] = (float) $_GET['bpm_min'];
    $types .= 'd';
}
if (!empty($_GET['bpm_max'])) {
    $conditions[] = 'bpm <= ?';
    $params[] = (float) $_GET['bpm_max'];
    $types .= 'd';
}
if (!empty($_GET['verified_only'])) {
    $conditions[] = "status = 'verified'";
}

$sql = 'SELECT * FROM songs';
if ($conditions) $sql .= ' WHERE ' . implode(' AND ', $conditions);
$sql .= ' ORDER BY id';

if ($params) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $songs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $songs = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Camelot-compatibility filter (Advanced Search Feature #5) — applied
// in PHP after the base query since it depends on the chosen song's key.
if (!empty($_GET['compatible_with'])) {
    $baseId = (int) $_GET['compatible_with'];
    $stmt = $db->prepare('SELECT * FROM songs WHERE id = ?');
    $stmt->bind_param('i', $baseId);
    $stmt->execute();
    $baseSong = $stmt->get_result()->fetch_assoc();

    if ($baseSong) {
        $songs = array_values(array_filter($songs, function ($s) use ($baseSong) {
            if ($s['id'] == $baseSong['id']) return false;
            return computeTransitionScore($baseSong, $s)['score'] > 0;
        }));
        // attach the score so the frontend can show/sort by it
        foreach ($songs as &$s) {
            $s['transition'] = computeTransitionScore($baseSong, $s);
        }
    }
}

jsonResponse(['songs' => $songs, 'count' => count($songs)]);
