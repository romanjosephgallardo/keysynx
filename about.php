<?php
/**
 * KeySynx — About page (server-rendered, static content)
 * Content summarized from the team's project proposal and core
 * features documents.
 */

session_start();
require_once __DIR__ . '/api/db.php';

$activePage = 'about';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<script>(function(){try{if(localStorage.getItem('kx-theme')==='light')document.documentElement.setAttribute('data-theme','light');}catch(e){}})();</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About — KeySynx</title>
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
  <div class="page-head kx-animate kx-d1" style="text-align:center; margin-bottom:48px;">
    <div class="eyebrow" style="font-size:0.8rem;">A collaborative harmonic mixing and music analysis platform</div>
    <h1 class="page-title" style="display:flex; align-items:center; justify-content:center; gap:14px; font-size:2.4rem;">
      <span class="logo" style="display:inline-flex;"><span class="dot" style="width:30px; height:30px; flex-shrink:0;"></span></span>
      About KeySynx
    </h1>
    <p class="page-sub" style="font-size:1.05rem; line-height:1.7; max-width:720px; margin-left:auto; margin-right:auto;">
      KeySynx centralizes music analysis data — key, BPM, and song structure — into a single,
      searchable, community-verified database. It's built for DJs, producers, and music students
      who need reliable harmonic and tempo information to plan transitions, mashups, and sets.
    </p>
  </div>

  <div class="section-block kx-animate kx-d2" style="margin-top:40px; padding-top:32px; border-top:1px solid var(--border);">
    <div class="section-label" style="font-size:0.8rem;">Why community-driven?</div>
    <p class="more-line" style="font-style:normal; color:var(--text-muted); font-size:1rem; line-height:1.7;">
      Manually analyzing a song's key and BPM takes real musical ear training, and any single
      analysis can be wrong. Rather than relying on one source of truth, KeySynx lets contributors
      submit analyses and lets the community validate them through upvotes, downvotes, and
      reputation-weighted trust — so accuracy improves the more people use it.
    </p>
  </div>

  <div class="section-block kx-animate kx-d3" style="margin-top:40px; padding-top:32px; border-top:1px solid var(--border);">
    <div class="section-label" style="font-size:0.8rem;">Core features</div>
    <div class="grid md:grid-cols-2 gap-3">
      <div class="stat-chip kx-feature-card" style="flex-direction:column; align-items:flex-start; gap:6px; padding:16px;">
        <b style="font-size:1.02rem;">Song metadata database</b>
        <span style="color:var(--text-dim); font-size:0.9rem; font-weight:400; line-height:1.55;">Title, artist, BPM, musical key, Camelot notation, and song structure in one organized record.</span>
      </div>
      <div class="stat-chip kx-feature-card" style="flex-direction:column; align-items:flex-start; gap:6px; padding:16px;">
        <b style="font-size:1.02rem;">Section-based transitions</b>
        <span style="color:var(--text-dim); font-size:0.9rem; font-weight:400; line-height:1.55;">Key and BPM can change across a song's intro, verse, chorus, and bridge — KeySynx tracks each section.</span>
      </div>
      <div class="stat-chip kx-feature-card" style="flex-direction:column; align-items:flex-start; gap:6px; padding:16px;">
        <b style="font-size:1.02rem;">Community validation</b>
        <span style="color:var(--text-dim); font-size:0.9rem; font-weight:400; line-height:1.55;">Upvotes, downvotes, and verification status work together to surface the most reliable analyses.</span>
      </div>
      <div class="stat-chip kx-feature-card" style="flex-direction:column; align-items:flex-start; gap:6px; padding:16px;">
        <b style="font-size:1.02rem;">Advanced search &amp; filtering</b>
        <span style="color:var(--text-dim); font-size:0.9rem; font-weight:400; line-height:1.55;">Find tracks by title, artist, BPM range, musical key, or Camelot compatibility.</span>
      </div>
      <div class="stat-chip kx-feature-card" style="flex-direction:column; align-items:flex-start; gap:6px; padding:16px;">
        <b style="font-size:1.02rem;">Admin moderation panel</b>
        <span style="color:var(--text-dim); font-size:0.9rem; font-weight:400; line-height:1.55;">Submissions, validations, and user roles are kept in check to protect database integrity.</span>
      </div>
      <div class="stat-chip kx-feature-card" style="flex-direction:column; align-items:flex-start; gap:6px; padding:16px;">
        <b style="font-size:1.02rem;">Contributor reputation</b>
        <span style="color:var(--text-dim); font-size:0.9rem; font-weight:400; line-height:1.55;">Reputation grows with quality contributions, unlocking tiers up to Moderator.</span>
      </div>
    </div>
  </div>

  <div class="section-block kx-animate kx-d4" style="margin-top:40px; padding-top:32px; border-top:1px solid var(--border);">
    <div class="section-label" style="font-size:0.8rem;">Beyond the core database</div>
    <p class="more-line" style="font-style:normal; color:var(--text-muted); margin-bottom:10px; font-size:1rem;">
      KeySynx also goes past a plain searchable list:
    </p>
    <ul style="color:var(--text-muted); font-size:0.98rem; line-height:1.9; padding-left:20px; list-style:disc;">
      <li><b style="color:var(--text);">Interactive Camelot Wheel</b> — visualizes harmonic compatibility, highlighting the current key and any compatible ones in real time.</li>
      <li><b style="color:var(--text);">Confidence scoring</b> — each analysis carries a score based on validation count, contributor reputation, and agreement consistency.</li>
      <li><b style="color:var(--text);">Harmonic transition recommendations</b> — suggests compatible tracks by key relationship, Camelot proximity, and BPM closeness.</li>
    </ul>
  </div>

  <div class="section-block kx-animate kx-d5" style="margin-top:40px; padding-top:32px; border-top:1px solid var(--border);">
    <div class="section-label" style="font-size:0.8rem;">Built with</div>
    <div class="flex gap-2 flex-wrap">
      <?php foreach (['PHP', 'MySQL', 'Apache', 'Tailwind CSS', 'Alpine.js', 'Apache Cordova'] as $tech): ?>
        <span class="status-pill kx-tech-pill" style="background:rgba(255,255,255,0.06); color:var(--text-muted); font-size:0.88rem; padding:8px 14px;"><?= $tech ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="section-block kx-animate kx-d6" style="margin-top:40px; margin-bottom:50px; padding-top:32px; border-top:1px solid var(--border);">
    <div class="section-label" style="font-size:0.8rem;">Project team</div>
    <p class="more-line" style="font-style:normal; color:var(--text-muted); margin-bottom:16px; font-size:1rem;">
      Built as a final project for Web and Mobile Systems.
    </p>

    <!--
      TEAM PHOTOS: each .kx-team-photo below currently shows initials as a
      placeholder. To drop in a real formal photo, just replace the <span>
      with an <img>, e.g.:
        <img src="images/team/juliana.jpg" alt="Abendaño, Juliana Veronica V.">
      Recommended: square photos (same width and height) for a clean fit.
    -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="kx-team-card" style="border:1px solid var(--border); border-radius:14px; padding:16px; background:var(--surface-2);">
        <div class="kx-team-photo"><span class="kx-team-initials">JA</span></div>
        <div style="margin-top:10px; font-weight:600; color:var(--text); font-size:0.95rem; line-height:1.3;">Abendaño, Juliana Veronica V.</div>
        <div style="color:var(--accent-violet); font-size:0.82rem; font-weight:600; margin-top:2px;">Quality Assurance Lead</div>
      </div>
      <div class="kx-team-card" style="border:1px solid var(--border); border-radius:14px; padding:16px; background:var(--surface-2);">
        <div class="kx-team-photo"><span class="kx-team-initials">KA</span></div>
        <div style="margin-top:10px; font-weight:600; color:var(--text); font-size:0.95rem; line-height:1.3;">Albaira, Keannah Mhary D.</div>
        <div style="color:var(--accent-violet); font-size:0.82rem; font-weight:600; margin-top:2px;">Frontend Lead</div>
      </div>
      <div class="kx-team-card" style="border:1px solid var(--border); border-radius:14px; padding:16px; background:var(--surface-2);">
        <div class="kx-team-photo"><span class="kx-team-initials">RG</span></div>
        <div style="margin-top:10px; font-weight:600; color:var(--text); font-size:0.95rem; line-height:1.3;">Gallardo, Roman Joseph C.</div>
        <div style="color:var(--accent-violet); font-size:0.82rem; font-weight:600; margin-top:2px;">Project Lead / Manager</div>
      </div>
      <div class="kx-team-card" style="border:1px solid var(--border); border-radius:14px; padding:16px; background:var(--surface-2);">
        <div class="kx-team-photo"><span class="kx-team-initials">KL</span></div>
        <div style="margin-top:10px; font-weight:600; color:var(--text); font-size:0.95rem; line-height:1.3;">Lomod, Kurt A.</div>
        <div style="color:var(--accent-violet); font-size:0.82rem; font-weight:600; margin-top:2px;">Backend Lead</div>
      </div>
    </div>

    <p class="more-line" style="font-style:normal; color:var(--text-dim); margin-top:18px; font-size:0.88rem;">
      Faculty-in-charge: Engr. Arlene B. Canlas
    </p>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
