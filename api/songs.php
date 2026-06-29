<?php
/**
 * KeySynx — Songs endpoint
 *
 * GET songs.php
 *   ?q=                 search title/artist
 *   ?key=               exact musical_key match
 *   ?bpm_min=&bpm_max=
 *   ?verified_only=1
 *   ?compatible_with=<song_id>   Camelot-compatibility filter (Advanced Search #5)
 *   ?page=1&per_page=25           pagination (defaults: page=1, per_page=25)
 *   ?sort=artist|title|musical_key|bpm|year   default: id
 *   ?dir=asc|desc                 default: asc
 *
 * GET songs.php?id=<id>
 *   Single song with: real album name/year, section timeline, contributor
 *   feedback, confidence score, top 5 recommended transitions, and a
 *   thumbnail derived from the YouTube link (if one was provided).
 */

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/camelot.php';
require_once __DIR__ . '/song_helpers.php';

$db = getDb();

function fetchAllSongs(mysqli $db): array {
    $res = $db->query('SELECT * FROM songs ORDER BY id');
    return $res->fetch_all(MYSQLI_ASSOC);
}

// ---------------- Single song detail ----------------
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $song = getSongFullDetail($db, $id);
    if (!$song) jsonError('Song not found.', 404);

    jsonResponse($song);
}

// ---------------- List / search / filter / sort / paginate ----------------
$conditions = [];
$params = [];
$types = '';

if (!empty($_GET['q'])) {
    $conditions[] = '(s.title LIKE ? OR s.artist LIKE ?)';
    $like = '%' . $_GET['q'] . '%';
    $params[] = $like; $params[] = $like;
    $types .= 'ss';
}
if (!empty($_GET['key'])) {
    $conditions[] = 's.musical_key = ?';
    $params[] = $_GET['key'];
    $types .= 's';
}
if (!empty($_GET['camelot_code'])) {
    $conditions[] = 's.camelot_code = ?';
    $params[] = strtoupper($_GET['camelot_code']);
    $types .= 's';
}
if (!empty($_GET['bpm_min'])) {
    $conditions[] = 's.bpm >= ?';
    $params[] = (float) $_GET['bpm_min'];
    $types .= 'd';
}
if (!empty($_GET['bpm_max'])) {
    $conditions[] = 's.bpm <= ?';
    $params[] = (float) $_GET['bpm_max'];
    $types .= 'd';
}
if (!empty($_GET['verified_only'])) {
    $conditions[] = "s.status = 'verified'";
}

$sortMap = [
    'artist' => 's.artist',
    'title' => 's.title',
    'musical_key' => 's.musical_key',
    'bpm' => 's.bpm',
    'year' => 'a.release_year',
];
$sortCol = $sortMap[$_GET['sort'] ?? ''] ?? 's.id';
$sortDir = (strtolower($_GET['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';

$baseSql = 'FROM songs s LEFT JOIN albums a ON a.id = s.album_id LEFT JOIN artists ar ON LOWER(ar.name) = LOWER(s.artist)';
if ($conditions) $baseSql .= ' WHERE ' . implode(' AND ', $conditions);

// Total count (for pagination UI), before applying LIMIT
if ($params) {
    $stmt = $db->prepare("SELECT COUNT(*) AS c $baseSql");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['c'];
} else {
    $total = (int) $db->query("SELECT COUNT(*) AS c $baseSql")->fetch_assoc()['c'];
}

$perPage = max(1, min(1000, (int) ($_GET['per_page'] ?? 25)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$sql = "SELECT s.*, a.title AS album_title, a.release_year, ar.image_path AS artist_image
        $baseSql
        ORDER BY $sortCol $sortDir
        LIMIT $perPage OFFSET $offset";

if ($params) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $songs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $songs = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
}

foreach ($songs as &$s) {
    $s['thumbnail_url'] = youtubeThumbnail($s['youtube_url']);
}

// Camelot-compatibility filter (Advanced Search Feature #5) — applied
// in PHP after the base query since it depends on the chosen song's key.
// NOTE: when active, this filters/sorts the CURRENT PAGE only (compatibility
// scoring against 400+ tracks per request isn't paginated server-side here).
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
        foreach ($songs as &$s) {
            $s['transition'] = computeTransitionScore($baseSong, $s);
        }
    }
}

jsonResponse([
    'songs' => $songs,
    'count' => count($songs),
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => (int) ceil($total / $perPage),
]);
