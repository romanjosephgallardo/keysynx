/* ============================================
   profile.html logic
   No ?user_id= in URL  -> your own profile (editable)
   ?user_id=<id>        -> public, read-only view of that user
   ============================================ */

const params = new URLSearchParams(window.location.search);
const viewingUserId = params.get('user_id');

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

function songStatusPill(status){
  return `<span class="status-pill status-${status}" style="font-size:0.68rem;">${status}</span>`;
}

async function load(){
  const auth = await waitForAuth();
  if(!auth.user){
    document.getElementById('loggedOutNotice').style.display = '';
    document.getElementById('profileContent').style.display = 'none';
    return;
  }

  try {
    const url = viewingUserId ? `api/profile.php?user_id=${encodeURIComponent(viewingUserId)}` : 'api/profile.php';
    const res = await fetch(url);
    if(!res.ok) throw new Error('failed');
    const data = await res.json();
    const user = data.user;

    document.getElementById('pageTitle') && (document.getElementById('pageTitle').textContent = user.is_own_profile ? 'Profile' : `${user.username}'s profile`);

    if(user.is_own_profile){
      document.getElementById('editSection').style.display = '';
      document.getElementById('publicHeader').style.display = 'none';
      document.getElementById('pUsername').value = user.username;
      document.getElementById('pEmail').value = user.email;
    } else {
      document.getElementById('editSection').style.display = 'none';
      const header = document.getElementById('publicHeader');
      header.style.display = 'flex';
      header.innerHTML = `
        ${window.avatarHTML(user.username, 56)}
        <div>
          <div class="track-title" style="font-size:1.3rem;">${user.username}</div>
          <div class="track-artist">Member since ${new Date(user.created_at).toLocaleDateString()}</div>
        </div>
      `;
    }

    document.getElementById('repPoints').textContent = user.reputation_points;
    document.getElementById('repTier').textContent = user.reputation_tier;

    const s = data.submission_stats;
    document.getElementById('submissionStats').innerHTML =
      statTile('Total', s.total || 0, 'var(--text)') +
      statTile('Verified', s.verified || 0, 'var(--verified)') +
      statTile('Pending', s.pending || 0, 'var(--pending)') +
      statTile('Rejected', s.rejected || 0, 'var(--rejected)');

    const recent = data.recent_songs || [];
    document.getElementById('recentSongs').innerHTML = recent.length ? recent.map(song => `
      <a href="song.php?id=${song.id}" class="rec-row" style="text-decoration:none; color:inherit;">
        <div class="rec-info">
          <div class="rec-title">${song.title}</div>
          <div class="rec-meta">${song.artist}</div>
        </div>
        ${songStatusPill(song.status)}
      </a>
    `).join('') : `<div class="more-line">No submissions yet.</div>`;

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

if(!viewingUserId){
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
      Alpine.store('auth').init();
    } catch(err){
      statusEl.style.color = 'var(--rejected)';
      statusEl.textContent = 'Could not reach the server.';
    }
  });
}

load();
