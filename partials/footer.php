<footer style="border-top:1px solid var(--border); margin-top:60px; padding:48px 0 24px;">
  <div class="shell">
    <div class="grid md:grid-cols-[1.3fr_1fr_1fr_1fr] gap-8 pb-10">
      <div style="text-align:center;">
        <a href="index.html" class="logo" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;"><span class="dot"></span> KeySynx</a>
        <p style="color:var(--text-dim); font-size:0.85rem; margin-top:10px; max-width:260px; margin-left:auto; margin-right:auto;">
          Community-verified music key &amp; BPM analysis for DJs, producers, and music students.
        </p>
      </div>
      <div>
        <div style="font-size:0.72rem; font-weight:700; letter-spacing:0.06em; color:var(--text-dim); text-transform:uppercase; margin-bottom:12px;">Platform</div>
        <div style="display:flex; flex-direction:column; gap:8px; font-size:0.85rem;">
          <a href="index.html" style="color:var(--text-muted); text-decoration:none;">Browse</a>
          <a href="wheel.html" style="color:var(--text-muted); text-decoration:none;">Harmonic Wheel</a>
          <a href="submit.html" style="color:var(--text-muted); text-decoration:none;">Submit</a>
        </div>
      </div>
      <div>
        <div style="font-size:0.72rem; font-weight:700; letter-spacing:0.06em; color:var(--text-dim); text-transform:uppercase; margin-bottom:12px;">Resources</div>
        <div style="display:flex; flex-direction:column; gap:8px; font-size:0.85rem;">
          <a href="about.php" style="color:var(--text-muted); text-decoration:none; display:inline-flex; align-items:center; gap:6px;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><line x1="12" y1="11" x2="12" y2="16"></line><circle cx="12" cy="7.5" r="1" fill="currentColor" stroke="none"></circle></svg>About</a>
          <a href="https://github.com/romanjosephgallardo/keysynx" target="_blank" rel="noopener" style="color:var(--text-muted); text-decoration:none; display:inline-flex; align-items:center; gap:6px;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 6 2 12 8 18"></polyline><polyline points="16 6 22 12 16 18"></polyline></svg>GitHub</a>
          <button type="button" onclick="document.getElementById('kxPrivacyModal').showModal()" style="background:none; border:none; padding:0; color:var(--text-muted); display:inline-flex; align-items:center; gap:6px; font-size:0.85rem; cursor:pointer; text-align:left;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 L20 6 V12 C20 17 16.5 20.5 12 22 C7.5 20.5 4 17 4 12 V6 Z"></path></svg>Privacy Policy</button>
          <button type="button" onclick="document.getElementById('kxTermsModal').showModal()" style="background:none; border:none; padding:0; color:var(--text-muted); display:inline-flex; align-items:center; gap:6px; font-size:0.85rem; cursor:pointer; text-align:left;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 H14 L18 6 V22 H6 Z"></path><line x1="9" y1="13" x2="15" y2="13"></line><line x1="9" y1="17" x2="15" y2="17"></line></svg>Terms of Service</button>
        </div>
      </div>
      <div>
        <div style="font-size:0.72rem; font-weight:700; letter-spacing:0.06em; color:var(--text-dim); text-transform:uppercase; margin-bottom:12px;">Project</div>
        <div style="display:flex; flex-direction:column; gap:8px; font-size:0.85rem; color:var(--text-muted);">
          <span>Web &amp; Mobile Systems</span>
          <span>Faculty-in-charge: Engr. Arlene B. Canlas</span>
        </div>
      </div>
    </div>
    <div style="border-top:1px solid var(--border); padding-top:20px; color:var(--text-dim); font-size:0.78rem;">
      © 2026 KeySynx · Built by Abendaño, Albaira, Gallardo &amp; Lomod · Final Project Prototype
    </div>
  </div>
</footer>

<dialog id="kxPrivacyModal" style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); margin:0; max-width:520px; width:90vw; background:var(--surface); color:var(--text); border:1px solid var(--border); border-radius:14px; padding:24px; box-shadow:0 20px 50px rgba(0,0,0,0.5);">
  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
    <h2 style="font-size:1.1rem; font-weight:700; margin:0;">Privacy Policy</h2>
    <button type="button" onclick="document.getElementById('kxPrivacyModal').close()" style="background:none; border:none; color:var(--text-dim); font-size:1.2rem; cursor:pointer; line-height:1;">×</button>
  </div>
  <div style="font-size:0.86rem; color:var(--text-muted); line-height:1.6; max-height:60vh; overflow-y:auto;">
    <p style="margin-bottom:12px;">KeySynx is an academic capstone project built for a Web and Mobile Systems class — not a commercial product. This policy explains what we collect and why, in plain terms.</p>
    <p style="margin-bottom:8px;"><b style="color:var(--text);">What we collect:</b> your username, Gmail address (used only to send your one-time verification code), a securely hashed password (we never store your actual password), any song analyses, votes, and comments you submit, and an optional profile photo.</p>
    <p style="margin-bottom:8px;"><b style="color:var(--text);">Why we collect it:</b> to operate your account, run the community validation and reputation system, and let you log in securely.</p>
    <p style="margin-bottom:8px;"><b style="color:var(--text);">Sharing:</b> we don't sell or share your data with third parties, and we don't use your email for marketing. Your Gmail address is only used by our verification system.</p>
    <p style="margin-bottom:8px;"><b style="color:var(--text);">Storage:</b> data is stored in our MySQL database for as long as this project/demo is active.</p>
    <p>Questions about this policy can be directed to any member of the KeySynx project team.</p>
  </div>
</dialog>

<dialog id="kxTermsModal" style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); margin:0; max-width:520px; width:90vw; background:var(--surface); color:var(--text); border:1px solid var(--border); border-radius:14px; padding:24px; box-shadow:0 20px 50px rgba(0,0,0,0.5);">
  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
    <h2 style="font-size:1.1rem; font-weight:700; margin:0;">Terms of Service</h2>
    <button type="button" onclick="document.getElementById('kxTermsModal').close()" style="background:none; border:none; color:var(--text-dim); font-size:1.2rem; cursor:pointer; line-height:1;">×</button>
  </div>
  <div style="font-size:0.86rem; color:var(--text-muted); line-height:1.6; max-height:60vh; overflow-y:auto;">
    <p style="margin-bottom:12px;">KeySynx is built as a final project for a Web and Mobile Systems class. It's provided as-is, for academic and demonstration purposes — not as a guaranteed, always-available commercial service.</p>
    <p style="margin-bottom:8px;"><b style="color:var(--text);">Your account:</b> keep your password private; you're responsible for activity under your account.</p>
    <p style="margin-bottom:8px;"><b style="color:var(--text);">Your contributions:</b> by submitting a song analysis, vote, or comment, you allow KeySynx to display it on the platform. Please keep submissions accurate and feedback respectful — no abuse, harassment, or spam.</p>
    <p style="margin-bottom:8px;"><b style="color:var(--text);">No audio storage:</b> KeySynx never stores or hosts copyrighted audio files — only metadata (title, artist, key, BPM, structure) and, optionally, a link to the official YouTube video.</p>
    <p style="margin-bottom:8px;"><b style="color:var(--text);">Moderation:</b> administrators may review, edit, or remove submissions and comments to maintain the integrity of the database.</p>
    <p>Continued use of KeySynx means you agree to these terms.</p>
  </div>
</dialog>

<style>
dialog#kxPrivacyModal::backdrop, dialog#kxTermsModal::backdrop {
  background: rgba(0,0,0,0.6);
}
</style>
