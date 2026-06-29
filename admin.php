<?php
/**
 * KeySynx — Admin panel (server-rendered)
 * Access: true admins, or any user with 100+ reputation (Moderator tier)
 * can moderate songs. Only true admins can manage user roles — see
 * hasModeratorAccess() / isTrueAdmin() in api/db.php.
 */

session_start();
require_once __DIR__ . '/api/db.php';
$db = getDb();

$userId = $_SESSION['user_id'] ?? null;
$me = null;
if ($userId) {
    $stmt = $db->prepare('SELECT id, username, role, reputation_points FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
}

$activePage = 'admin';
$currentUrl = $_SERVER['REQUEST_URI'];
$canModerate = hasModeratorAccess($me);
$canManageRoles = isTrueAdmin($me);

$validTabs = ['songs', 'artists'];
if ($canManageRoles) $validTabs[] = 'users';
$tab = in_array($_GET['tab'] ?? '', $validTabs, true) ? $_GET['tab'] : 'songs';
$perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 25)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$statusFilter = $_GET['status'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');

function tabUrl($tab) {
    $params = $_GET;
    $params['tab'] = $tab;
    unset($params['page'], $params['q'], $params['status']);
    return 'admin.php?' . http_build_query($params);
}
function pageUrl($p) {
    $params = $_GET;
    $params['page'] = $p;
    return 'admin.php?' . http_build_query($params);
}
function paramsExcept(array $keys) {
    $p = $_GET;
    foreach ($keys as $k) unset($p[$k]);
    return $p;
}

/**
 * Renders the same "Showing X to Y of Z results / Per page / « ‹ 1 2 … › »"
 * pagination bar used on index.html, but server-rendered via plain links —
 * no JS required, since every control here is just a GET navigation.
 */
function renderAdminPagination($total, $page, $perPage, $totalPages, $label) {
    $start = $total ? (($page - 1) * $perPage + 1) : 0;
    $end = min($total, $page * $perPage);
    ?>
    <div class="pagination-bar">
      <div class="pagination-info">Showing <?= $start ?> to <?= $end ?> of <?= $total ?> <?= $label ?></div>
      <div class="pagination-controls">
        <span class="pp-label">Per page:</span>
        <form method="get" action="admin.php" style="display:inline;">
          <?php foreach (paramsExcept(['per_page', 'page']) as $k => $v): ?>
            <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
          <?php endforeach; ?>
          <select name="per_page" onchange="this.form.submit()">
            <option value="25" <?= $perPage === 25 ? 'selected' : '' ?>>25</option>
            <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100</option>
          </select>
        </form>
        <a href="<?= pageUrl(1) ?>" class="page-btn" style="<?= $page <= 1 ? 'opacity:0.35;pointer-events:none;' : '' ?>">&laquo;</a>
        <a href="<?= pageUrl(max(1, $page - 1)) ?>" class="page-btn" style="<?= $page <= 1 ? 'opacity:0.35;pointer-events:none;' : '' ?>">&lsaquo;</a>
        <div class="page-numbers">
          <?php
          $pagesToShow = [];
          $add = function ($p) use (&$pagesToShow) { if (!in_array($p, $pagesToShow)) $pagesToShow[] = $p; };
          $add(1);
          for ($p = $page - 1; $p <= $page + 1; $p++) if ($p > 0 && $p <= $totalPages) $add($p);
          $add($totalPages);
          sort($pagesToShow);
          $prev = 0;
          foreach ($pagesToShow as $p):
              if ($p - $prev > 1): ?><span class="page-ellipsis">&hellip;</span><?php endif;
              ?>
              <a href="<?= pageUrl($p) ?>" class="page-num <?= $p === $page ? 'active' : '' ?>" style="text-decoration:none;"><?= $p ?></a>
              <?php $prev = $p;
          endforeach;
          ?>
        </div>
        <a href="<?= pageUrl(min($totalPages, $page + 1)) ?>" class="page-btn" style="<?= $page >= $totalPages ? 'opacity:0.35;pointer-events:none;' : '' ?>">&rsaquo;</a>
        <a href="<?= pageUrl($totalPages) ?>" class="page-btn" style="<?= $page >= $totalPages ? 'opacity:0.35;pointer-events:none;' : '' ?>">&raquo;</a>
      </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>(function(){try{if(localStorage.getItem('kx-theme')==='light')document.documentElement.setAttribute('data-theme','light');}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel — KeySynx</title>
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
<link rel="stylesheet" href="css/animations.css?v=1">
<link rel="stylesheet" href="css/theme-light.css?v=1">
<script src="js/theme.js?v=1"></script>
</head>
<body>

<?php include __DIR__ . '/partials/topbar.php'; ?>

<main class="shell">
  <div class="page-head kx-animate kx-d1">
    <div class="eyebrow">Moderation</div>
    <h1 class="page-title">Admin panel</h1>
    <p class="page-sub">Review pending submissions, manage entries, and handle contributor roles.</p>
  </div>

  <?php if (!$canModerate): ?>
    <div class="empty-state">
      <div class="icon">&#9834;</div>
      Moderator access required — reach 100+ reputation points, or ask an admin to promote your account.
    </div>
  <?php else: ?>

    <div style="display:flex; gap:10px; margin-bottom:18px;">
      <a href="<?= tabUrl('songs') ?>" class="btn btn-sm <?= $tab === 'songs' ? '' : 'btn-ghost' ?>">Songs</a>
      <a href="<?= tabUrl('artists') ?>" class="btn btn-sm <?= $tab === 'artists' ? '' : 'btn-ghost' ?>">Artists</a>
      <?php if ($canManageRoles): ?>
        <a href="<?= tabUrl('users') ?>" class="btn btn-sm <?= $tab === 'users' ? '' : 'btn-ghost' ?>">Users &amp; Roles</a>
      <?php endif; ?>
    </div>

    <?php if ($tab === 'songs'): ?>
      <form method="get" action="admin.php" class="filterbar">
        <input type="hidden" name="tab" value="songs">
        <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search title or artist..." class="search-input" style="flex:1; min-width:220px;">
        <select name="status" onchange="this.form.submit()">
          <option value="">All statuses</option>
          <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="verified" <?= $statusFilter === 'verified' ? 'selected' : '' ?>>Verified</option>
          <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
        <button type="submit" class="btn btn-sm">Search</button>
        <?php if ($searchQuery || $statusFilter): ?>
          <a href="<?= tabUrl('songs') ?>" class="btn btn-ghost btn-sm">Reset</a>
        <?php endif; ?>
      </form>
    <?php elseif ($tab === 'users'): ?>
      <form method="get" action="admin.php" class="filterbar">
        <input type="hidden" name="tab" value="users">
        <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search username or email..." class="search-input" style="flex:1; min-width:220px;">
        <button type="submit" class="btn btn-sm">Search</button>
        <?php if ($searchQuery): ?>
          <a href="<?= tabUrl('users') ?>" class="btn btn-ghost btn-sm">Reset</a>
        <?php endif; ?>
      </form>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
      <p style="color:var(--rejected); font-size:0.85rem; margin-bottom:14px;"><?= htmlspecialchars($_GET['error']) ?></p>
    <?php endif; ?>

    <?php if ($tab === 'songs'):
      $conditions = [];
      $params = [];
      $types = '';
      if ($statusFilter) { $conditions[] = 's.status = ?'; $params[] = $statusFilter; $types .= 's'; }
      if ($searchQuery) {
          $conditions[] = '(s.title LIKE ? OR s.artist LIKE ?)';
          $like = '%' . $searchQuery . '%';
          $params[] = $like; $params[] = $like; $types .= 'ss';
      }
      $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

      $countSql = "SELECT COUNT(*) AS c FROM songs s $where";
      if ($params) { $stmt = $db->prepare($countSql); $stmt->bind_param($types, ...$params); $stmt->execute(); $total = $stmt->get_result()->fetch_assoc()['c']; }
      else { $total = $db->query($countSql)->fetch_assoc()['c']; }
      $totalPages = max(1, (int) ceil($total / $perPage));
      $offset = ($page - 1) * $perPage;

      $sql = "SELECT s.*, u.username AS submitted_by_username FROM songs s LEFT JOIN users u ON u.id = s.submitted_by $where ORDER BY s.id LIMIT $perPage OFFSET $offset";
      if ($params) { $stmt = $db->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $songs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); }
      else { $songs = $db->query($sql)->fetch_all(MYSQLI_ASSOC); }
    ?>
      <div style="overflow-x:auto; padding-bottom:10px; margin-top:20px;">
        <table class="admin-table">
          <thead><tr><th>Song</th><th>Key / BPM</th><th>Submitted by</th><th>Votes</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($songs)): ?>
              <tr><td colspan="6"><div class="empty-state"><div class="icon">&#9834;</div>No entries here.</div></td></tr>
            <?php else: foreach ($songs as $s): ?>
              <tr>
                <td>
                  <a href="song.php?id=<?= $s['id'] ?>" style="text-decoration:none; display:block;">
                    <div style="font-family:var(--font-display); font-weight:600; color:var(--text);"><?= htmlspecialchars($s['title']) ?></div>
                    <div style="color:var(--text-muted); font-size:0.8rem;"><?= htmlspecialchars($s['artist']) ?></div>
                  </a>
                </td>
                <td>
                  <?= $s['has_variation'] ? '<span style="color:var(--accent-violet)">*</span>' : '' ?><span style="font-family:var(--font-display); font-weight:600;"><?= htmlspecialchars($s['musical_key']) ?></span>
                  &nbsp;·&nbsp; <span class="num"><?= $s['bpm'] !== null ? $s['bpm'] : '—' ?></span> BPM
                </td>
                <td><?= htmlspecialchars($s['submitted_by_username'] ?? '—') ?></td>
                <td><span class="num" style="color:var(--verified)">▲<?= $s['upvotes'] ?></span> / <span class="num" style="color:var(--rejected)">▼<?= $s['downvotes'] ?></span></td>
                <td><span class="status-pill status-<?= $s['status'] ?>"><?= $s['status'] ?></span></td>
                <td>
                  <div class="row-actions">
                    <form method="post" action="api/moderate_handler.php" style="margin:0;">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="song_id" value="<?= $s['id'] ?>">
                      <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
                      <button type="submit" class="btn btn-sm">Approve</button>
                    </form>
                    <form method="post" action="api/moderate_handler.php" style="margin:0;">
                      <input type="hidden" name="action" value="reject">
                      <input type="hidden" name="song_id" value="<?= $s['id'] ?>">
                      <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
                      <button type="submit" class="btn btn-sm">Reject</button>
                    </form>
                    <form method="post" action="api/moderate_handler.php" style="margin:0;" onsubmit="return confirm('Delete this entry permanently?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="song_id" value="<?= $s['id'] ?>">
                      <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
                      <button type="submit" class="btn btn-sm btn-ghost">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php renderAdminPagination($total, $page, $perPage, $totalPages, 'results'); ?>

    <?php elseif ($tab === 'artists'):
      $artistsList = $db->query("SELECT id, name, image_path FROM artists ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
    ?>
      <div style="overflow-x:auto; padding-bottom:10px; margin-top:20px;">
        <table class="admin-table">
          <thead><tr><th>Artwork</th><th>Artist</th><th>Upload / Replace</th></tr></thead>
          <tbody>
            <?php if (empty($artistsList)): ?>
              <tr><td colspan="3"><div class="empty-state"><div class="icon">&#9834;</div>No artists found.</div></td></tr>
            <?php else: foreach ($artistsList as $a): ?>
              <tr>
                <td>
                  <?php if (!empty($a['image_path'])): ?>
                    <img src="<?= htmlspecialchars($a['image_path']) ?>" alt="" style="width:48px;height:48px;border-radius:10px;object-fit:cover;display:block;">
                  <?php else: ?>
                    <div style="width:48px;height:48px;border-radius:10px;background:var(--surface-2);display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:0.62rem;text-align:center;">No art</div>
                  <?php endif; ?>
                </td>
                <td style="font-weight:600;"><?= htmlspecialchars($a['name']) ?></td>
                <td>
                  <form method="post" action="api/upload_artist_artwork.php" enctype="multipart/form-data" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <input type="hidden" name="artist_id" value="<?= $a['id'] ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
                    <input type="file" name="artwork" accept="image/png,image/jpeg,image/webp" required style="font-size:0.78rem; color:var(--text-muted); max-width:180px;">
                    <button type="submit" class="btn btn-sm">Upload</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

    <?php else: // Users & Roles tab (true admins only)
      $uConditions = [];
      $uParams = [];
      $uTypes = '';
      if ($searchQuery) {
          $uConditions[] = '(username LIKE ? OR email LIKE ?)';
          $like = '%' . $searchQuery . '%';
          $uParams[] = $like; $uParams[] = $like; $uTypes .= 'ss';
      }
      $uWhere = $uConditions ? 'WHERE ' . implode(' AND ', $uConditions) : '';

      $countSql = "SELECT COUNT(*) AS c FROM users $uWhere";
      if ($uParams) { $stmt = $db->prepare($countSql); $stmt->bind_param($uTypes, ...$uParams); $stmt->execute(); $totalUsers = $stmt->get_result()->fetch_assoc()['c']; }
      else { $totalUsers = $db->query($countSql)->fetch_assoc()['c']; }
      $totalPages = max(1, (int) ceil($totalUsers / $perPage));
      $offset = ($page - 1) * $perPage;

      $sql = "SELECT id, username, email, role, reputation_points FROM users $uWhere ORDER BY reputation_points DESC LIMIT $perPage OFFSET $offset";
      if ($uParams) { $stmt = $db->prepare($sql); $stmt->bind_param($uTypes, ...$uParams); $stmt->execute(); $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); }
      else { $users = $db->query($sql)->fetch_all(MYSQLI_ASSOC); }
    ?>
      <div style="overflow-x:auto; padding-bottom:10px; margin-top:20px;">
        <table class="admin-table">
          <thead><tr><th>User</th><th>Reputation</th><th>Tier</th><th>Role</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr><td colspan="5"><div class="empty-state"><div class="icon">&#9834;</div>No users match.</div></td></tr>
            <?php else: foreach ($users as $u): $tier = reputationTier((float) $u['reputation_points']); ?>
              <tr>
                <td>
                  <div style="font-weight:600;"><?= htmlspecialchars($u['username']) ?></div>
                  <div style="color:var(--text-muted); font-size:0.8rem;"><?= htmlspecialchars($u['email']) ?></div>
                </td>
                <td class="num"><?= number_format((float) $u['reputation_points'], 1) ?></td>
                <td><?php $aTc = reputationTierColor($tier); ?><span class="status-pill" style="background:<?= $aTc['bg'] ?>; color:<?= $aTc['color'] ?>;"><?= $tier ?></span></td>
                <td><span class="status-pill status-<?= $u['role'] === 'admin' ? 'verified' : 'pending' ?>"><?= $u['role'] ?></span></td>
                <td>
                  <form method="post" action="api/set_role_handler.php" style="margin:0;">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
                    <?php if ($u['role'] === 'admin'): ?>
                      <input type="hidden" name="role" value="user">
                      <button type="submit" class="btn btn-sm">Demote to user</button>
                    <?php else: ?>
                      <input type="hidden" name="role" value="admin">
                      <button type="submit" class="btn btn-sm">Promote to admin</button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php renderAdminPagination($totalUsers, $page, $perPage, $totalPages, 'users'); ?>
    <?php endif; ?>

  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
