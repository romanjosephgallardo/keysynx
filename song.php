<?php
/**
 * KeySynx — Song detail page (server-rendered)
 * Replaces song.html + the AJAX-heavy parts of song.js for this page.
 * Comments, the Edit link, and vote state are decided here in PHP,
 * using the session the server already has — not guessed at client-side
 * after the page loads. JS is only used for the YouTube embed iframe
 * (which needs no logic) and is otherwise unnecessary for this page.
 */

session_start();
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/camelot.php';
require_once __DIR__ . '/api/song_helpers.php';

$db = getDb();
$id = (int) ($_GET['id'] ?? 0);
$song = getSongFullDetail($db, $id);

$currentUserId = $_SESSION['user_id'] ?? null;
$isAdmin = false;
if ($currentUserId) {
    $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->bind_param('i', $currentUserId);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    $isAdmin = $me && $me['role'] === 'admin';
}

$activePage = 'browse';
$currentUrl = $_SERVER['REQUEST_URI'];

function statusLabel($status) {
    return $status === 'verified' ? '✓ Verified' : ($status === 'pending' ? '◔ Pending review' : '✕ Rejected');
}
function confidenceColor($score) {
    if ($score >= 70) return 'var(--verified)';
    if ($score >= 40) return 'var(--pending)';
    return 'var(--rejected)';
}
function extractYoutubeId($url) {
    if (!$url) return null;
    return preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]{6,})#', $url, $m) ? $m[1] : null;
}
$thumbGradients = [
    ['#E0995A', '#C2547E'], ['#5A9CE0', '#7E54C2'], ['#E05A8C', '#C27E54'],
    ['#5AD6A8', '#5A8CD6'], ['#E0C25A', '#E05A6E'], ['#9C5AE0', '#5AC2D6']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $song ? htmlspecialchars($song['title']) . ' — KeySynx' : 'Track not found — KeySynx' ?></title>
<link rel="stylesheet" href="css/style.css?v=6">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { theme: { extend: {
    colors: { ksbg:'#14141A', kssurface:'#1E1E27', kssurface2:'#25252F', ksborder:'#32323E',
              kstext:'#EDEDF2', ksmuted:'#8A8A99', ksviolet:'#B9A3FF', ksverified:'#5EE6A8',
              kspending:'#F2B84B', ksrejected:'#EF5C6E' },
    fontFamily: { display:['Space Grotesk','sans-serif'], body:['Inter','sans-serif'] }
  }}};
</script>
</head>
<body>

<?php include __DIR__ . '/partials/topbar.php'; ?>

<main class="shell">
  <p style="padding:24px 0;"><a href="index.html" class="btn btn-ghost btn-sm">← Back to database</a></p>

  <?php if (!$song): ?>
    <div class="empty-state"><div class="icon">&#9834;</div>Track not found.</div>
  <?php else:
    $code = getCamelotCode($song['musical_key']);
    [$thumbA, $thumbB] = $thumbGradients[$song['id'] % count($thumbGradients)];
    $ytId = extractYoutubeId($song['youtube_url']);
    $isOwner = $currentUserId && $song['submitted_by'] !== null && (int) $song['submitted_by'] === (int) $currentUserId;
  ?>

    <div class="detail-head">
      <?php if ($song['thumbnail_url']): ?>
        <img src="<?= htmlspecialchars($song['thumbnail_url']) ?>" alt="" style="width:80px;height:80px;border-radius:16px;object-fit:cover;">
      <?php else: ?>
        <div class="track-thumb" style="--thumb-a:<?= $thumbA ?>; --thumb-b:<?= $thumbB ?>; width:80px;height:80px;font-size:1.8rem;border-radius:16px;">&#9834;</div>
      <?php endif; ?>
      <div>
        <h1 class="detail-title"><?= htmlspecialchars($song['title']) ?></h1>
        <div class="detail-artist">
          <?= htmlspecialchars($song['artist']) ?><?= $song['album_title'] ? ' · ' . htmlspecialchars($song['album_title']) : '' ?><?= $song['release_year'] ? ' (' . (int) $song['release_year'] . ')' : '' ?>
        </div>
        <div class="detail-stats">
          <div class="stat-chip"><b class="num"><?= $song['bpm'] !== null ? $song['bpm'] : 'Unfixed tempo' ?></b><?= $song['bpm'] !== null ? ' BPM' : '' ?></div>
          <div class="stat-chip"><?= $song['has_variation'] ? '<span class="star">*</span>' : '' ?><b><?= htmlspecialchars($song['musical_key']) ?></b><?= $code ? ' <span style="color:var(--text-dim)">(' . $code . ')</span>' : '' ?></div>
          <?php if ($song['time_signature']): ?><div class="stat-chip"><b><?= htmlspecialchars($song['time_signature']) ?></b></div><?php endif; ?>
          <div class="stat-chip" style="display:flex; align-items:center; gap:8px;">
            <div style="width:28px;height:28px;border-radius:50%;flex-shrink:0;background:conic-gradient(<?= confidenceColor($song['confidence_score']) ?> <?= $song['confidence_score'] ?>%, var(--border) <?= $song['confidence_score'] ?>%);display:flex;align-items:center;justify-content:center;position:relative;">
              <div style="position:absolute;inset:3px;border-radius:50%;background:var(--surface);"></div>
            </div>
            <span><b><?= $song['confidence_score'] ?></b> confidence</span>
          </div>
          <div class="stat-chip status-pill status-<?= $song['status'] ?>" style="background:transparent;border:1px solid var(--border);"><?= statusLabel($song['status']) ?></div>
        </div>
      </div>
    </div>

    <?php if (!empty($song['sections'])): ?>
      <div class="section-block">
        <div class="section-label">Section timeline</div>
        <div class="structure-track">
          <?php foreach ($song['sections'] as $sec): ?>
            <div class="structure-seg" style="flex:1;">
              <?= htmlspecialchars($sec['section_name']) ?>
              <small><?= htmlspecialchars($sec['musical_key']) ?><?= $sec['bpm'] ? ' · ' . $sec['bpm'] . ' BPM' : '' ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($song['footnote']): ?>
      <div class="section-block">
        <div class="section-label">Notes</div>
        <div class="more-line" style="font-size:0.92rem; white-space:pre-wrap; font-style:normal;"><?= htmlspecialchars($song['footnote']) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($ytId): ?>
      <div class="section-block">
        <div class="section-label">Listen (official YouTube link)</div>
        <div style="position:relative;padding-bottom:56.25%;border-radius:12px;overflow:hidden;background:var(--surface-2);">
          <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($ytId) ?>" style="position:absolute;inset:0;width:100%;height:100%;border:0;"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
      </div>
    <?php endif; ?>

    <div class="section-block">
      <div class="section-label" style="display:flex; align-items:center; justify-content:space-between;">
        <span>Recommended transitions</span>
        <a href="wheel.html?id=<?= $song['id'] ?>" class="btn btn-ghost btn-sm">Open on wheel →</a>
      </div>
      <div class="rec-list">
        <?php if (empty($song['recommendations'])): ?>
          <div class="more-line">No mappable compatible tracks in this album.</div>
        <?php else: foreach ($song['recommendations'] as $r): ?>
          <a href="song.php?id=<?= $r['song']['id'] ?>" class="rec-row" style="text-decoration:none; color:inherit;">
            <div class="rec-score" style="--pct:<?= $r['score'] ?>%"><?= $r['score'] ?></div>
            <div class="rec-info">
              <div class="rec-title"><?= htmlspecialchars($r['song']['title']) ?></div>
              <div class="rec-meta"><?= htmlspecialchars($r['relation']) ?> · <span class="num"><?= $r['song']['bpm'] ?? 'unfixed tempo' ?></span><?= $r['song']['bpm'] !== null ? ' BPM' : '' ?></div>
            </div>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="section-block" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
      <div style="color:var(--text-muted); font-size:0.88rem; display:flex; align-items:center; gap:10px;">
        <span>Submitted by <b style="color:var(--text)"><?= htmlspecialchars($song['submitted_by_username'] ?? 'unknown') ?></b></span>
        <?php if ($isOwner || $isAdmin): ?>
          <a href="submit.html?edit=<?= $song['id'] ?>" class="btn btn-ghost btn-sm">✎ Edit</a>
        <?php endif; ?>
      </div>
      <div class="vote-row">
        <form method="post" action="api/vote_handler.php" style="display:inline; margin:0;">
          <input type="hidden" name="song_id" value="<?= $song['id'] ?>">
          <input type="hidden" name="vote_type" value="up">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
          <button type="submit" class="vote-btn up">▲ <span class="num"><?= $song['upvotes'] ?></span></button>
        </form>
        <form method="post" action="api/vote_handler.php" style="display:inline; margin:0;">
          <input type="hidden" name="song_id" value="<?= $song['id'] ?>">
          <input type="hidden" name="vote_type" value="down">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
          <button type="submit" class="vote-btn down">▼ <span class="num"><?= $song['downvotes'] ?></span></button>
        </form>
      </div>
    </div>

    <div class="section-block">
      <div class="section-label">Contributor feedback (<?= count($song['comments']) ?>)</div>
      <?php if (!empty($_GET['error'])): ?>
        <p style="font-size:0.82rem; color:var(--rejected); margin-bottom:10px;"><?= htmlspecialchars($_GET['error']) ?></p>
      <?php endif; ?>
      <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:14px;">
        <?php if (empty($song['comments'])): ?>
          <p class="more-line">No feedback yet — be the first to weigh in.</p>
        <?php else: foreach ($song['comments'] as $c):
          $hue = array_sum(array_map('ord', str_split($c['username']))) % 360;
          $canDelete = $currentUserId && ((int) $c['user_id'] === (int) $currentUserId || $isAdmin);
          $canEdit = $currentUserId && (int) $c['user_id'] === (int) $currentUserId; // strictly owner-only, not even admin
          $isEditingThis = $canEdit && isset($_GET['edit_comment']) && (int) $_GET['edit_comment'] === (int) $c['id'];
          $baseSongUrl = 'song.php?id=' . $song['id'];
        ?>
          <div style="display:flex; gap:10px; background:var(--surface-2); border-radius:10px; padding:10px 12px;">
            <a href="profile.php?user_id=<?= (int) $c['user_id'] ?>" style="width:28px;height:28px;border-radius:50%;flex-shrink:0;overflow:hidden;display:block;">
              <?php if (!empty($c['avatar_path'])): ?>
                <img src="<?= htmlspecialchars($c['avatar_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
              <?php else: ?>
                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;background:hsl(<?= $hue ?>,55%,42%);">
                  <?= htmlspecialchars(mb_strtoupper(mb_substr($c['username'], 0, 2))) ?>
                </div>
              <?php endif; ?>
            </a>
            <div style="flex:1; min-width:0;">
              <a href="profile.php?user_id=<?= (int) $c['user_id'] ?>" style="text-decoration:none;">
                <span style="color:var(--text); font-weight:600;"><?= htmlspecialchars($c['username']) ?></span>
              </a>
              <span style="color:var(--text-dim); font-size:0.75rem;"> · <?= (int) $c['reputation_points'] ?> rep</span>

              <?php if ($isEditingThis): ?>
                <form method="post" action="api/edit_comment_handler.php" style="margin-top:6px; display:flex; gap:8px;">
                  <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                  <input type="hidden" name="redirect" value="<?= htmlspecialchars($baseSongUrl) ?>">
                  <input type="text" name="comment" value="<?= htmlspecialchars($c['comment']) ?>" class="search-input" style="flex:1;" maxlength="1000" required>
                  <button type="submit" class="btn btn-primary btn-sm">Save</button>
                  <a href="<?= htmlspecialchars($baseSongUrl) ?>" class="btn btn-ghost btn-sm">Cancel</a>
                </form>
              <?php else: ?>
                <div style="margin-top:4px; color:var(--text-muted); font-size:0.88rem;"><?= htmlspecialchars($c['comment']) ?></div>
              <?php endif; ?>
            </div>
            <?php if (!$isEditingThis): ?>
              <div style="display:flex; gap:4px;">
                <?php if ($canEdit): ?>
                  <a href="<?= htmlspecialchars($baseSongUrl . '&edit_comment=' . $c['id']) ?>" class="icon-btn" title="Edit" style="width:28px; height:28px; font-size:0.85rem; color:var(--text-dim); text-decoration:none; display:flex; align-items:center; justify-content:center;">✎</a>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                  <form method="post" action="api/delete_comment_handler.php" style="margin:0;" onsubmit="return confirm('Delete this comment?');">
                    <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
                    <button type="submit" class="icon-btn" title="Delete" style="width:28px; height:28px; font-size:0.95rem; color:var(--text-dim);">×</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <?php if ($currentUserId): ?>
        <form method="post" action="api/comment_handler.php" style="display:flex; gap:8px;">
          <input type="hidden" name="song_id" value="<?= $song['id'] ?>">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
          <input type="text" name="comment" placeholder="Add feedback on this analysis..." class="search-input" style="flex:1;" maxlength="1000" required>
          <button type="submit" class="btn btn-primary btn-sm">Post</button>
        </form>
      <?php else: ?>
        <p class="form-hint">Log in (top right) to leave feedback.</p>
      <?php endif; ?>
    </div>

  <?php endif; ?>
</main>

<footer>KeySynx · Final Project Prototype · Key &amp; BPM Analysis Platform</footer>
</body>
</html>
