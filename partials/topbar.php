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
    if ($currentUser) $currentUser['reputation_tier'] = reputationTier((float) $currentUser['reputation_points']);
}

$activePage = $activePage ?? '';
$currentUrl = $_SERVER['REQUEST_URI'];
$authError = $_GET['auth_error'] ?? '';
$showVerify = ($_GET['verify'] ?? '') === '1' && !empty($_GET['vu']);
$verifyUsername = $_GET['vu'] ?? '';
$verifyMsg = $_GET['vmsg'] ?? '';

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
        <a href="admin.php" class="admin-badge-link <?= navClass('admin', $activePage) ?>">
          <?= $activePage === 'admin' ? 'Admin Panel' : 'Admin' ?>
        </a>
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
      <details class="auth-popover" style="margin-left:auto; position:relative;" <?= $showVerify ? 'open' : '' ?>>
        <summary class="btn btn-sm" style="list-style:none; cursor:pointer;">Log in</summary>
        <div style="position:absolute; right:0; margin-top:8px; width:280px; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px; z-index:30; box-shadow:0 12px 30px rgba(0,0,0,0.4);">
          <?php if ($authError): ?>
            <p style="font-size:0.78rem; color:var(--rejected); margin-bottom:10px;"><?= htmlspecialchars($authError) ?></p>
          <?php endif; ?>

          <?php if ($showVerify): ?>
            <!-- Verify-code panel: shown right after register, or when an
                 unverified account tries to log in. Verifying the code
                 IS the final login step here — no separate login needed. -->
            <p style="font-size:0.82rem; color:var(--text); font-weight:600; margin-bottom:4px;">Verify your email</p>
            <p style="font-size:0.78rem; color:var(--text-muted); margin-bottom:10px;">
              <?php if ($verifyMsg === 'sent'): ?>
                We sent a 6-digit code to <b><?= htmlspecialchars($verifyUsername) ?></b>'s Gmail.
              <?php elseif ($verifyMsg === 'send_failed'): ?>
                Account created, but the email couldn't be sent. Try "Resend code" below.
              <?php elseif ($verifyMsg === 'needs_verify'): ?>
                This account hasn't been verified yet. Enter the code from your Gmail, or resend it.
              <?php else: ?>
                Enter the 6-digit code sent to your Gmail.
              <?php endif; ?>
            </p>
            <form method="post" action="api/verify_handler.php" style="margin-bottom:8px;">
              <input type="hidden" name="username" value="<?= htmlspecialchars($verifyUsername) ?>">
              <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
              <input type="text" name="code" placeholder="6-digit code" inputmode="numeric" maxlength="6" pattern="\d{6}"
                     class="search-input" style="width:100%; margin-bottom:8px; letter-spacing:3px; text-align:center; font-weight:700;" required autofocus>
              <button type="submit" class="btn btn-primary btn-sm" style="width:100%; justify-content:center;">Verify &amp; log in</button>
            </form>
            <form method="post" action="api/resend_code_handler.php" style="margin:0;">
              <input type="hidden" name="username" value="<?= htmlspecialchars($verifyUsername) ?>">
              <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="width:100%; justify-content:center;">Resend code</button>
            </form>
          <?php else: ?>
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
                <input type="email" name="email" placeholder="Gmail address" class="search-input" style="width:100%; margin-bottom:8px;" required>
                <input type="password" name="password" placeholder="Password (min 6 chars)" class="search-input" style="width:100%; margin-bottom:8px;" required>
                <div class="form-hint" style="margin-bottom:8px;">We'll email a verification code to this Gmail address.</div>
                <button type="submit" class="btn btn-primary btn-sm" style="width:100%; justify-content:center;">Create account</button>
              </form>
            </details>
          <?php endif; ?>
        </div>
      </details>
    <?php endif; ?>
  </div>
</header>
