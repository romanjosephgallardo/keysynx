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

$tab = ($_GET['tab'] ?? 'songs') === 'users' && $canManageRoles ? 'users' : 'songs';
$perPage = 25;
$page = max(1, (int) ($_GET['page'] ?? 1));
$statusFilter = $_GET['status'] ?? '';

function tabUrl($tab) {
    $params = $_GET;
    $params['tab'] = $tab;
    unset($params['page']);
    return 'admin.php?' . http_build_query($params);
}
function pageUrl($p) {
    $params = $_GET;
    $params['page'] = $p;
    return 'admin.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
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
</head>
<body>

<?php include __DIR__ . '/partials/topbar.php'; ?>

<main class="shell">
  <div class="page-head">
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

    <div class="filterbar" style="justify-content:space-between;">
      <div style="display:flex; gap:10px;">
        <a href="<?= tabUrl('songs') ?>" class="btn btn-sm <?= $tab === 'songs' ? '' : 'btn-ghost' ?>">Songs</a>
        <?php if ($canManageRoles): ?>
          <a href="<?= tabUrl('users') ?>" class="btn btn-sm <?= $tab === 'users' ? '' : 'btn-ghost' ?>">Users &amp; Roles</a>
        <?php endif; ?>
      </div>
      <?php if ($tab === 'songs'): ?>
        <form method="get" action="admin.php">
          <input type="hidden" name="tab" value="songs">
          <select name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="verified" <?= $statusFilter === 'verified' ? 'selected' : '' ?>>Verified</option>
            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
          </select>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!empty($_GET['error'])): ?>
      <p style="color:var(--rejected); font-size:0.85rem; margin-bottom:14px;"><?= htmlspecialchars($_GET['error']) ?></p>
    <?php endif; ?>

    <?php if ($tab === 'songs'):
      $conditions = [];
      $params = [];
      $types = '';
      if ($statusFilter) { $conditions[] = 's.status = ?'; $params[] = $statusFilter; $types .= 's'; }
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
      <div style="overflow-x:auto; padding-bottom:30px;">
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

      <div class="pagination-bar">
        <div class="pagination-info">Showing <?= $total ? ($offset + 1) . ' to ' . min($total, $offset + $perPage) : 0 ?> of <?= $total ?> results</div>
        <div class="pagination-controls">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="<?= pageUrl($p) ?>" class="page-num <?= $p === $page ? 'active' : '' ?>" style="text-decoration:none;"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      </div>

    <?php else: // Users & Roles tab (true admins only)
      $totalUsers = $db->query('SELECT COUNT(*) AS c FROM users')->fetch_assoc()['c'];
      $totalPages = max(1, (int) ceil($totalUsers / $perPage));
      $offset = ($page - 1) * $perPage;
      $users = $db->query("SELECT id, username, email, role, reputation_points FROM users ORDER BY reputation_points DESC LIMIT $perPage OFFSET $offset")->fetch_all(MYSQLI_ASSOC);
    ?>
      <div style="overflow-x:auto; padding-bottom:30px;">
        <table class="admin-table">
          <thead><tr><th>User</th><th>Reputation</th><th>Tier</th><th>Role</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($users as $u): $tier = reputationTier((int) $u['reputation_points']); ?>
              <tr>
                <td>
                  <div style="font-weight:600;"><?= htmlspecialchars($u['username']) ?></div>
                  <div style="color:var(--text-muted); font-size:0.8rem;"><?= htmlspecialchars($u['email']) ?></div>
                </td>
                <td class="num"><?= $u['reputation_points'] ?></td>
                <td><span class="status-pill" style="background:rgba(185,163,255,0.15); color:var(--accent-violet);"><?= $tier ?></span></td>
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
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="pagination-bar">
        <div class="pagination-info">Showing <?= $totalUsers ? ($offset + 1) . ' to ' . min($totalUsers, $offset + $perPage) : 0 ?> of <?= $totalUsers ?> users</div>
        <div class="pagination-controls">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="<?= pageUrl($p) ?>" class="page-num <?= $p === $page ? 'active' : '' ?>" style="text-decoration:none;"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</main>

<footer>KeySynx · Final Project Prototype · Key &amp; BPM Analysis Platform</footer>
</body>
</html>
