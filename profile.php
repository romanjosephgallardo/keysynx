<?php
/**
 * KeySynx — Profile page (server-rendered)
 * No ?user_id=  -> your own profile (full edit access)
 * ?user_id=<id> -> public, read-only view of that user
 *
 * Public view deliberately shows LESS than the owner sees: no email,
 * no pending/rejected submission counts, no detailed reputation log,
 * and only verified submissions in the recent-activity list. That's
 * the "proper things to show" — a public profile is a showcase of
 * verified contributions, not someone's full moderation history.
 */

session_start();
require_once __DIR__ . '/api/db.php';

$db = getDb();
$sessionUserId = $_SESSION['user_id'] ?? null;
$viewingUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $sessionUserId;
$isOwnProfile = $sessionUserId && (int) $viewingUserId === (int) $sessionUserId;

$activePage = 'profile';
$currentUrl = $_SERVER['REQUEST_URI'];
$errorMsg = $_GET['error'] ?? '';
$successMsg = $_GET['success'] ?? '';

$profileUser = null;
if ($viewingUserId) {
    $stmt = $db->prepare('SELECT id, username, email, role, reputation_points, avatar_path, created_at FROM users WHERE id = ?');
    $stmt->bind_param('i', $viewingUserId);
    $stmt->execute();
    $profileUser = $stmt->get_result()->fetch_assoc();
    if ($profileUser) $profileUser['reputation_tier'] = reputationTier((float) $profileUser['reputation_points']);
}

$stats = null;
$recentSongs = [];
$reputationLog = [];

if ($profileUser) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS total, SUM(status='verified') AS verified, SUM(status='pending') AS pending, SUM(status='rejected') AS rejected
         FROM songs WHERE submitted_by = ?"
    );
    $stmt->bind_param('i', $viewingUserId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    if ($isOwnProfile) {
        $stmt = $db->prepare('SELECT id, title, artist, status FROM songs WHERE submitted_by = ? ORDER BY created_at DESC LIMIT 10');
        $stmt->bind_param('i', $viewingUserId);
    } else {
        // Public view: verified contributions only — not pending/rejected drafts
        $stmt = $db->prepare("SELECT id, title, artist, status FROM songs WHERE submitted_by = ? AND status = 'verified' ORDER BY created_at DESC LIMIT 10");
        $stmt->bind_param('i', $viewingUserId);
    }
    $stmt->execute();
    $recentSongs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if ($isOwnProfile) {
        $stmt = $db->prepare('SELECT points, reason, created_at FROM reputation_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
        $stmt->bind_param('i', $viewingUserId);
        $stmt->execute();
        $reputationLog = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>(function(){try{if(localStorage.getItem('kx-theme')==='light')document.documentElement.setAttribute('data-theme','light');}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $profileUser ? htmlspecialchars($profileUser['username']) . "'s profile — KeySynx" : 'Profile — KeySynx' ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { theme: { extend: {
    colors: { ksbg:'#14141A', kssurface:'#1E1E27', kssurface2:'#25252F', ksborder:'#32323E',
              kstext:'#EDEDF2', ksmuted:'#8A8A99', ksviolet:'#B9A3FF', ksverified:'#5EE6A8',
              kspending:'#F2B84B', ksrejected:'#EF5C6E' },
    fontFamily: { display:['Space Grotesk','sans-serif'], body:['Inter','sans-serif'] }
  }}};
</script>
<link rel="stylesheet" href="css/style.css?v=6">
<link rel="stylesheet" href="css/animations.css?v=1">
<link rel="stylesheet" href="css/theme-light.css?v=1">
<link rel="stylesheet" href="css/responsiveness.css?v=1"> 
<script src="js/theme.js?v=1"></script>
</head>
<body>

<?php include __DIR__ . '/partials/topbar.php'; ?>

<main class="shell">
  <div class="page-head kx-animate kx-d1">
    <div class="eyebrow"><?= $isOwnProfile ? 'Your account' : 'Public profile' ?></div>
    <h1 class="page-title"><?= $isOwnProfile ? 'Profile' : htmlspecialchars($profileUser['username'] ?? '') . "'s profile" ?></h1>
    <p class="page-sub">
      <?= $isOwnProfile
          ? 'Manage your account and see how your contributor reputation is building.'
          : 'A look at ' . htmlspecialchars($profileUser['username'] ?? 'this user') . "'s verified contributions to the community." ?>
    </p>
  </div>

  <?php if (!$profileUser): ?>
    <div class="empty-state"><div class="icon">&#9834;</div>
      <?= $viewingUserId ? 'User not found.' : 'Log in (top right) to view your profile.' ?>
    </div>
  <?php else: ?>

    <div class="grid md:grid-cols-[1fr_360px] gap-6" style="padding-bottom:60px;">
      <div class="form-card kx-animate kx-d2" style="max-width:none;">

        <?php if ($isOwnProfile): ?>
          <!-- Own profile: full edit access -->
          <div style="display:flex; align-items:center; gap:16px; margin-bottom:24px;">
            <div style="width:72px;height:72px;border-radius:50%;overflow:hidden;flex-shrink:0;">
              <?php if (!empty($profileUser['avatar_path'])): ?>
                <img src="<?= htmlspecialchars($profileUser['avatar_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
              <?php else: ?>
                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:700;color:#fff;background:hsl(<?= avatarHue($profileUser['username']) ?>,55%,42%);">
                  <?= htmlspecialchars(mb_strtoupper(mb_substr($profileUser['username'], 0, 2))) ?>
                </div>
              <?php endif; ?>
            </div>
            <form method="post" action="api/upload_avatar.php" enctype="multipart/form-data" style="display:flex; align-items:center; gap:10px;">
              <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
              <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" required
                     style="font-size:0.78rem; color:var(--text-muted); max-width:200px;">
              <button type="submit" class="btn btn-sm">Upload photo</button>
            </form>
          </div>

          <?php if ($errorMsg): ?><p class="form-hint" style="color:var(--rejected); margin-bottom:14px;"><?= htmlspecialchars($errorMsg) ?></p><?php endif; ?>
          <?php if ($successMsg): ?><p class="form-hint" style="color:var(--verified); margin-bottom:14px;"><?= htmlspecialchars($successMsg) ?></p><?php endif; ?>

          <div class="section-label">Account details</div>
          <form method="post" action="api/update_profile_handler.php">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
            <div class="form-row">
              <label for="pUsername">Username</label>
              <input type="text" id="pUsername" name="username" value="<?= htmlspecialchars($profileUser['username']) ?>" required>
            </div>
            <div class="form-row">
              <label for="pEmail">Email</label>
              <input type="email" id="pEmail" name="email" value="<?= htmlspecialchars($profileUser['email']) ?>" required>
            </div>
            <div class="form-grid-2">
              <div class="form-row">
                <label for="pCurrentPassword">Current password</label>
                <input type="password" id="pCurrentPassword" name="current_password" placeholder="Only needed if changing password">
              </div>
              <div class="form-row">
                <label for="pNewPassword">New password</label>
                <input type="password" id="pNewPassword" name="new_password" placeholder="Leave blank to keep current">
              </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">Save changes</button>
          </form>
        <?php else: ?>
          <!-- Public view: read-only header, no email, no edit form -->
          <div style="display:flex; align-items:center; gap:16px; margin-bottom:24px;">
            <div style="width:72px;height:72px;border-radius:50%;overflow:hidden;flex-shrink:0;">
              <?php if (!empty($profileUser['avatar_path'])): ?>
                <img src="<?= htmlspecialchars($profileUser['avatar_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
              <?php else: ?>
                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:700;color:#fff;background:hsl(<?= avatarHue($profileUser['username']) ?>,55%,42%);">
                  <?= htmlspecialchars(mb_strtoupper(mb_substr($profileUser['username'], 0, 2))) ?>
                </div>
              <?php endif; ?>
            </div>
            <div>
              <div class="track-title" style="font-size:1.3rem;"><?= htmlspecialchars($profileUser['username']) ?></div>
              <div class="track-artist">Member since <?= date('n/j/Y', strtotime($profileUser['created_at'])) ?></div>
            </div>
          </div>
        <?php endif; ?>

        <div class="section-label" style="margin-top:32px;">Submissions</div>
        <div class="flex gap-3 flex-wrap">
          <div class="stat-chip" style="flex-direction:column; align-items:flex-start; gap:4px;">
            <span style="color:var(--text-dim); font-size:0.72rem; text-transform:uppercase;">Total</span>
            <b class="num" style="font-size:1.1rem;"><?= (int) ($stats['total'] ?? 0) ?></b>
          </div>
          <div class="stat-chip" style="flex-direction:column; align-items:flex-start; gap:4px;">
            <span style="color:var(--text-dim); font-size:0.72rem; text-transform:uppercase;">Verified</span>
            <b class="num" style="color:var(--verified); font-size:1.1rem;"><?= (int) ($stats['verified'] ?? 0) ?></b>
          </div>
          <?php if ($isOwnProfile): ?>
            <div class="stat-chip" style="flex-direction:column; align-items:flex-start; gap:4px;">
              <span style="color:var(--text-dim); font-size:0.72rem; text-transform:uppercase;">Pending</span>
              <b class="num" style="color:var(--pending); font-size:1.1rem;"><?= (int) ($stats['pending'] ?? 0) ?></b>
            </div>
            <div class="stat-chip" style="flex-direction:column; align-items:flex-start; gap:4px;">
              <span style="color:var(--text-dim); font-size:0.72rem; text-transform:uppercase;">Rejected</span>
              <b class="num" style="color:var(--rejected); font-size:1.1rem;"><?= (int) ($stats['rejected'] ?? 0) ?></b>
            </div>
          <?php endif; ?>
        </div>

        <div class="section-label" style="margin-top:24px;"><?= $isOwnProfile ? 'Recent submissions' : 'Verified submissions' ?></div>
        <div class="rec-list">
          <?php if (empty($recentSongs)): ?>
            <div class="more-line">No <?= $isOwnProfile ? '' : 'verified ' ?>submissions yet.</div>
          <?php else: foreach ($recentSongs as $s): ?>
            <a href="song.php?id=<?= $s['id'] ?>" class="rec-row" style="text-decoration:none; color:inherit;">
              <div class="rec-info">
                <div class="rec-title"><?= htmlspecialchars($s['title']) ?></div>
                <div class="rec-meta"><?= htmlspecialchars($s['artist']) ?></div>
              </div>
              <?php if ($isOwnProfile): ?><span class="status-pill status-<?= $s['status'] ?>" style="font-size:0.68rem;"><?= $s['status'] ?></span><?php endif; ?>
            </a>
          <?php endforeach; endif; ?>
        </div>

        <?php if ($isOwnProfile): ?>
          <div class="section-label" style="margin-top:32px;">Recent reputation activity</div>
          <div class="rec-list">
            <?php if (empty($reputationLog)): ?>
              <div class="more-line">No activity yet — submit an analysis to start earning reputation.</div>
            <?php else: foreach ($reputationLog as $l): ?>
              <div class="rec-row">
                <div class="rec-score" style="--pct:<?= $l['points'] > 0 ? 100 : 0 ?>%; color:<?= $l['points'] > 0 ? 'var(--verified)' : 'var(--rejected)' ?>;">
                  <?= $l['points'] > 0 ? '+' : '' ?><?= $l['points'] ?>
                </div>
                <div class="rec-info">
                  <div class="rec-title"><?= htmlspecialchars($l['reason']) ?></div>
                  <div class="rec-meta"><?= date('n/j/Y', strtotime($l['created_at'])) ?></div>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div>
        <div class="now-playing" style="margin-bottom:16px;">
          <div class="track-title" style="font-size:1.6rem;"><?= number_format((float) $profileUser['reputation_points'], 1) ?></div>
          <div class="track-artist">reputation points</div>
          <?php $myTc = reputationTierColor($profileUser['reputation_tier']); ?>
          <div class="status-pill" style="background:<?= $myTc['bg'] ?>; color:<?= $myTc['color'] ?>; margin-top:10px; display:inline-block;"><?= $profileUser['reputation_tier'] ?></div>
        </div>

        <div class="now-playing">
          <div class="section-label" style="margin-top:0;">How reputation works</div>
          <table class="admin-table" style="width:100%;">
            <thead><tr><th>Action</th><th>Points</th></tr></thead>
            <tbody>
              <tr><td>Correct submission</td><td class="num" style="color:var(--verified);">+0.1</td></tr>
              <tr><td>Each upvote received</td><td class="num" style="color:var(--verified);">+2</td></tr>
              <tr><td>Each downvote received</td><td class="num" style="color:var(--rejected);">−1</td></tr>
            </tbody>
          </table>
          <div class="more-line" style="font-style:normal; margin-top:8px;">Upvotes/downvotes affect your reputation immediately, in real time — not just once a track is fully verified.</div>
          <div style="margin-top:14px; display:flex; flex-direction:column; gap:9px;">
            <?php foreach ([
                ['New Contributor', '0–19 pts'],
                ['Trusted Analyzer', '20–49 pts'],
                ['Verified Contributor', '50–99 pts'],
                ['Moderator', '100+ pts'],
            ] as [$tierName, $range]): $legendTc = reputationTierColor($tierName); ?>
              <div style="display:flex; align-items:center; gap:8px; font-size:0.85rem;">
                <span style="width:9px; height:9px; border-radius:50%; flex-shrink:0; background:<?= $legendTc['color'] ?>;"></span>
                <span style="color:var(--text-dim);"><?= $range ?></span>
                <span style="color:var(--text-dim);">&rarr;</span>
                <b style="color:<?= $legendTc['color'] ?>;"><?= $tierName ?></b>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
