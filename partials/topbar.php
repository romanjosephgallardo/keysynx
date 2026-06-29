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
    <a href="index.html" class="logo" style="text-decoration:none; display:flex; align-items:center; gap:10px;">
      <span class="dot" style="width:13px; height:13px; flex-shrink:0;"></span>
      <span style="display:flex; flex-direction:column; line-height:1.1;">
        <span style="font-size:1.2rem; font-weight:700; color:var(--text); font-family:'Space Grotesk',sans-serif;">KeySynx</span>
        <span style="font-size:0.68rem; color:var(--text-dim); font-weight:500; letter-spacing:0.02em;">Keys and BPM Database</span>
      </span>
    </a>
    <div style="margin-left:auto; display:flex; align-items:center; gap:24px;">
    <nav class="nav-links" style="display:flex; align-items:center; gap:20px;">
      <a href="index.html" class="<?= navClass('browse', $activePage) ?>" style="display:inline-flex; align-items:center; gap:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>Browse</a>
      <a href="wheel.html" class="<?= navClass('wheel', $activePage) ?>" style="display:inline-flex; align-items:center; gap:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="2.2" fill="currentColor" stroke="none"></circle></svg>Harmonic Wheel</a>
      <a href="submit.html" class="<?= navClass('submit', $activePage) ?>" style="display:inline-flex; align-items:center; gap:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>Submit</a>
      <a href="about.php" class="<?= navClass('about', $activePage) ?>" style="display:inline-flex; align-items:center; gap:6px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><line x1="12" y1="11" x2="12" y2="16"></line><circle cx="12" cy="7.5" r="1" fill="currentColor" stroke="none"></circle></svg>About</a>
      <?php if (hasModeratorAccess($currentUser)): ?>
        <a href="admin.php" class="admin-badge-link <?= navClass('admin', $activePage) ?>">
          <?= $activePage === 'admin' ? 'Admin Panel' : 'Admin' ?>
        </a>
      <?php endif; ?>
    </nav>

    <button type="button" class="kx-theme-toggle btn btn-sm btn-ghost" title="Toggle light/dark mode" style="padding:8px;">
      <svg class="kx-icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
      <svg class="kx-icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
    </button>

    <?php if ($currentUser): ?>
      <div style="display:flex; align-items:center; gap:10px;">
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
      <details class="auth-popover" style="position:relative;" <?= $showVerify ? 'open' : '' ?>>
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
            <details name="kxAuthMode" open style="margin:0;">
              <summary style="font-size:0.82rem; color:var(--text); font-weight:600; cursor:pointer; list-style:none; margin-bottom:10px;">Log in</summary>
              <form method="post" action="api/login_handler.php">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
                <input type="text" name="username" placeholder="Username" class="search-input" style="width:100%; margin-bottom:8px;" required>
                <input type="password" name="password" placeholder="Password" class="search-input" style="width:100%; margin-bottom:8px;" required>
                <button type="submit" class="btn btn-primary btn-sm" style="width:100%; justify-content:center;">Log in</button>
              </form>
            </details>
            <details name="kxAuthMode" style="margin-top:10px;">
              <summary style="font-size:0.8rem; color:var(--accent-violet); cursor:pointer; list-style:none;">No account? Register</summary>
              <form method="post" action="api/register_handler.php" style="margin-top:10px;">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($currentUrl) ?>">
                <input type="text" name="username" placeholder="Username" class="search-input" style="width:100%; margin-bottom:8px;" required>
                <input type="email" name="email" placeholder="Email Address" class="search-input" style="width:100%; margin-bottom:8px;" required>
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
  </div>
</header>
