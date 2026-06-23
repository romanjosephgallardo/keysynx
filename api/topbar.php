<?php
/**
 * KeySynx — Shared topbar partial
 * Include this from any server-rendered .php page. Expects (optionally):
 *   $activePage = 'browse' | 'wheel' | 'submit' | 'admin' | 'profile'
 *
 * This renders the logged-in/out state directly from the PHP session —
 * no client-side JS decides what to show here, which is the whole point:
 * the server is the actual authority on who's logged in, so there's no
 * timing race or stale-client-state class of bug possible for this part.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../api/db.php';
$__topbarDb = getDb();

$currentUser = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = $__topbarDb->prepare('SELECT id, username, role, reputation_points, avatar_path FROM users WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $currentUser = $stmt->get_result()->fetch_assoc();
    if ($currentUser) $currentUser['reputation_tier'] = reputationTier((int) $currentUser['reputation_points']);
}

$activePage = $activePage ?? '';
$currentUrl = $_SERVER['REQUEST_URI'];
$authError = $_GET['auth_error'] ?? '';

function navClass($page, $current) { return $page === $current ? 'active' : ''; }
?>
<header class="topbar">
  <div class="shell topbar-inner">
    <a href="index.html" class="logo"><span class="dot"></span> KeySynx</a>
    <nav class="nav-links">
      <a href="index.html" class="<?= navClass('browse', $activePage) ?>">Browse</a>
      <a href="wheel.html" class="<?= navClass('wheel', $activePage) ?>">Wheel</a>
      <a href="submit.html" class="<?= navClass('submit', $activePage) ?>">Submit</a>
      <?php if (hasModeratorAccess($currentUser)): ?>
        <a href="admin.php" class="admin-badge-link <?= navClass('admin', $activePage) ?>">Admin Panel</a>
      <?php endif; ?>
    </nav>

    <?php if ($currentUser): ?>
      <div style="margin-left:auto; display:flex; align-items:center; gap:10px;">
        <a href="profile.php?user_id=<?= (int) $currentUser['id'] ?>"
           title="<?= htmlspecialchars($currentUser['username'] . ' · ' . $currentUser['reputation_tier']) ?>"
           style="width:32px;height:32px;border-radius:50%;display:block;overflow:hidden;text-decoration:none;">
          <?php if (!empty($currentUser['avatar_path'])): ?>
            <img src="<?= htmlspecialchars($currentUser['avatar_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
          <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;background:hsl(<?= avatarHue($currentUser['username']) ?>,55%,42%);">
              <?= htmlspecialchars(mb_strtoupper(mb_substr($currentUser['username'], 0, 2))) ?>
            </div>
          <?php endif; ?>
        </a>
        <form method="post" action="api/logout_handler.php" style="display:inline; margin:0;">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
          <button type="submit" class="btn btn-sm btn-ghost">Log out</button>
        </form>
      </div>
    <?php else: ?>
      <details class="auth-popover" style="margin-left:auto; position:relative;">
        <summary class="btn btn-sm" style="list-style:none; cursor:pointer;">Log in</summary>
        <div style="position:absolute; right:0; margin-top:8px; width:280px; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px; z-index:30; box-shadow:0 12px 30px rgba(0,0,0,0.4);">
          <?php if ($authError): ?>
            <p style="font-size:0.78rem; color:var(--rejected); margin-bottom:10px;"><?= htmlspecialchars($authError) ?></p>
          <?php endif; ?>
          <form method="post" action="api/login_handler.php" style="margin-bottom:14px;">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
            <input type="text" name="username" placeholder="Username" class="search-input" style="width:100%; margin-bottom:8px;" required>
            <input type="password" name="password" placeholder="Password" class="search-input" style="width:100%; margin-bottom:8px;" required>
            <button type="submit" class="btn btn-primary btn-sm" style="width:100%; justify-content:center;">Log in</button>
          </form>
          <details>
            <summary style="font-size:0.8rem; color:var(--accent-violet); cursor:pointer; list-style:none;">No account? Register</summary>
            <form method="post" action="api/register_handler.php" style="margin-top:10px;">
              <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
              <input type="text" name="username" placeholder="Username" class="search-input" style="width:100%; margin-bottom:8px;" required>
              <input type="email" name="email" placeholder="Email" class="search-input" style="width:100%; margin-bottom:8px;" required>
              <input type="password" name="password" placeholder="Password (min 6 chars)" class="search-input" style="width:100%; margin-bottom:8px;" required>
              <button type="submit" class="btn btn-primary btn-sm" style="width:100%; justify-content:center;">Create account</button>
            </form>
          </details>
        </div>
      </details>
    <?php endif; ?>
  </div>
</header>
