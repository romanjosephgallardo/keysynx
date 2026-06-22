/* ============================================
   profile.html logic
   ============================================ */

async function waitForAuth(){
  for(let i = 0; i < 20; i++){
    if(window.Alpine && Alpine.store('auth') && !Alpine.store('auth').loading) return Alpine.store('auth');
    await new Promise(r => setTimeout(r, 100));
  }
  return Alpine.store('auth');
}

function statTile(label, value, color){
  return `
    <div class="stat-chip" style="flex-direction:column; align-items:flex-start; gap:4px;">
      <span style="color:var(--text-dim); font-size:0.72rem; text-transform:uppercase;">${label}</span>
      <b class="num" style="color:${color}; font-size:1.1rem;">${value}</b>
    </div>
  `;
}

async function load(){
  const auth = await waitForAuth();
  if(!auth.user){
    document.getElementById('loggedOutNotice').style.display = '';
    document.getElementById('profileContent').style.display = 'none';
    return;
  }

  try {
    const res = await fetch('api/profile.php');
    if(!res.ok) throw new Error('failed');
    const data = await res.json();

    document.getElementById('pUsername').value = data.user.username;
    document.getElementById('pEmail').value = data.user.email;
    document.getElementById('repPoints').textContent = data.user.reputation_points;
    document.getElementById('repTier').textContent = data.user.reputation_tier;

    const s = data.submission_stats;
    document.getElementById('submissionStats').innerHTML =
      statTile('Total', s.total || 0, 'var(--text)') +
      statTile('Verified', s.verified || 0, 'var(--verified)') +
      statTile('Pending', s.pending || 0, 'var(--pending)') +
      statTile('Rejected', s.rejected || 0, 'var(--rejected)');

    const log = data.reputation_log;
    document.getElementById('reputationLog').innerHTML = log.length ? log.map(l => `
      <div class="rec-row">
        <div class="rec-score" style="--pct:${l.points > 0 ? 100 : 0}%; color:${l.points > 0 ? 'var(--verified)' : 'var(--rejected)'};">
          ${l.points > 0 ? '+' : ''}${l.points}
        </div>
        <div class="rec-info">
          <div class="rec-title">${l.reason}</div>
          <div class="rec-meta">${new Date(l.created_at).toLocaleDateString()}</div>
        </div>
      </div>
    `).join('') : `<div class="more-line">No activity yet — submit an analysis to start earning reputation.</div>`;
  } catch(e){
    document.getElementById('profileStatus').textContent = 'Could not load profile. Is the backend reachable?';
    document.getElementById('profileStatus').style.color = 'var(--rejected)';
  }
}

document.getElementById('profileForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const statusEl = document.getElementById('profileStatus');

  const payload = {
    username: document.getElementById('pUsername').value,
    email: document.getElementById('pEmail').value,
    current_password: document.getElementById('pCurrentPassword').value,
    new_password: document.getElementById('pNewPassword').value
  };

  try {
    const res = await fetch('api/profile.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if(!res.ok){
      statusEl.style.color = 'var(--rejected)';
      statusEl.textContent = data.error || 'Could not save changes.';
      return;
    }
    statusEl.style.color = 'var(--verified)';
    statusEl.textContent = '✓ Profile updated.';
    document.getElementById('pCurrentPassword').value = '';
    document.getElementById('pNewPassword').value = '';
    // refresh the topbar auth display in case the username changed
    Alpine.store('auth').init();
  } catch(err){
    statusEl.style.color = 'var(--rejected)';
    statusEl.textContent = 'Could not reach the server.';
  }
});

load();
