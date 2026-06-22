/* ============================================
   admin.html logic
   Songs tab: approve/reject/delete via api/moderate.php
   Users tab: promote/demote roles via api/moderate.php (set_role)
   Falls back to local sample data (read-only demo) if the
   backend isn't reachable.
   ============================================ */

let songsState = [];
let backendAvailable = true;

function statusLabel(status){
  return status === 'verified' ? 'Verified' : status === 'pending' ? 'Pending' : 'Rejected';
}

function normalizeApiSong(raw){
  return {
    id: raw.id, title: raw.title, artist: raw.artist, bpm: raw.bpm !== null ? parseFloat(raw.bpm) : null,
    musicalKey: raw.musical_key, hasVariation: !!raw.has_variation,
    submittedBy: raw.submitted_by, status: raw.status,
    upvotes: raw.upvotes, downvotes: raw.downvotes
  };
}

// ---------------- Songs tab ----------------
function songRowHTML(song){
  const star = song.hasVariation ? '<span style="color:var(--accent-violet)">*</span>' : '';
  return `
    <tr data-id="${song.id}">
      <td>
        <div style="font-family:var(--font-display); font-weight:600;">${song.title}</div>
        <div style="color:var(--text-muted); font-size:0.8rem;">${song.artist}</div>
      </td>
      <td>
        ${star}<span style="font-family:var(--font-display); font-weight:600;">${song.musicalKey}</span>
        &nbsp;·&nbsp; <span class="num">${song.bpm !== null ? song.bpm + ' BPM' : 'Unfixed tempo'}</span>
      </td>
      <td>${song.submittedBy ?? '—'}</td>
      <td><span class="num" style="color:var(--verified)">▲${song.upvotes}</span> / <span class="num" style="color:var(--rejected)">▼${song.downvotes}</span></td>
      <td><span class="status-pill status-${song.status}">${statusLabel(song.status)}</span></td>
      <td>
        <div class="row-actions">
          <button class="btn btn-sm approve-btn" ${backendAvailable ? '' : 'disabled'}>Approve</button>
          <button class="btn btn-sm reject-btn" ${backendAvailable ? '' : 'disabled'}>Reject</button>
          <button class="btn btn-sm btn-ghost delete-btn" ${backendAvailable ? '' : 'disabled'}>Delete</button>
        </div>
      </td>
    </tr>
  `;
}

function renderSongsTable(){
  const filter = document.getElementById('statusFilter').value;
  const tbody = document.getElementById('adminTableBody');
  const rows = songsState.filter(s => !filter || s.status === filter);
  tbody.innerHTML = rows.length ? rows.map(songRowHTML).join('')
    : `<tr><td colspan="6"><div class="empty-state"><div class="icon">&#9834;</div>No entries here.</div></td></tr>`;
}

async function moderateAction(action, songId){
  if(!backendAvailable){ alert('Backend not reachable — read-only demo mode.'); return; }
  try {
    const res = await fetch('api/moderate.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action, song_id: songId })
    });
    const data = await res.json();
    if(!res.ok){ alert(data.error || 'Action failed. Are you logged in as an admin?'); return; }
    await loadSongs();
  } catch(e){ alert('Could not reach the server.'); }
}

document.getElementById('statusFilter').addEventListener('change', renderSongsTable);

document.getElementById('adminTableBody').addEventListener('click', (e) => {
  const row = e.target.closest('tr');
  if(!row) return;
  const id = Number(row.dataset.id);
  if(e.target.classList.contains('approve-btn')) moderateAction('approve', id);
  else if(e.target.classList.contains('reject-btn')) moderateAction('reject', id);
  else if(e.target.classList.contains('delete-btn')){
    if(confirm('Delete this entry permanently?')) moderateAction('delete', id);
  }
});

async function loadSongs(){
  try {
    const res = await fetch('api/songs.php');
    if(!res.ok) throw new Error('not ok');
    const data = await res.json();
    backendAvailable = true;
    songsState = data.songs.map(normalizeApiSong);
  } catch(e){
    backendAvailable = false;
    const local = await fetchSongs();
    songsState = JSON.parse(JSON.stringify(local));
  }
  renderSongsTable();
}

// ---------------- Users tab ----------------
function userRowHTML(user){
  return `
    <tr data-id="${user.id}">
      <td>
        <div style="font-weight:600;">${user.username}</div>
        <div style="color:var(--text-muted); font-size:0.8rem;">${user.email}</div>
      </td>
      <td class="num">${user.reputation_points}</td>
      <td><span class="status-pill" style="background:rgba(185,163,255,0.15); color:var(--accent-violet);">${user.reputation_tier}</span></td>
      <td><span class="status-pill status-${user.role === 'admin' ? 'verified' : 'pending'}">${user.role}</span></td>
      <td>
        <div class="row-actions">
          ${user.role === 'admin'
            ? `<button class="btn btn-sm demote-btn">Demote to user</button>`
            : `<button class="btn btn-sm promote-btn">Promote to admin</button>`}
        </div>
      </td>
    </tr>
  `;
}

async function loadUsers(){
  const tbody = document.getElementById('usersTableBody');
  try {
    const res = await fetch('api/users.php');
    if(!res.ok) throw new Error('not ok');
    const data = await res.json();
    tbody.innerHTML = data.users.map(userRowHTML).join('');
  } catch(e){
    tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state"><div class="icon">&#9834;</div>Could not load users — log in as an admin and ensure the backend is reachable.</div></td></tr>`;
  }
}

document.getElementById('usersTableBody')?.addEventListener('click', async (e) => {
  const row = e.target.closest('tr');
  if(!row) return;
  const userId = Number(row.dataset.id);
  let newRole = null;
  if(e.target.classList.contains('promote-btn')) newRole = 'admin';
  if(e.target.classList.contains('demote-btn')) newRole = 'user';
  if(!newRole) return;

  try {
    const res = await fetch('api/moderate.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'set_role', user_id: userId, role: newRole })
    });
    const data = await res.json();
    if(!res.ok){ alert(data.error || 'Role change failed.'); return; }
    loadUsers();
  } catch(e){ alert('Could not reach the server.'); }
});

// ---------------- Tabs ----------------
document.getElementById('tabSongs').addEventListener('click', () => {
  document.getElementById('songsPanel').style.display = '';
  document.getElementById('usersPanel').style.display = 'none';
  document.getElementById('tabSongs').classList.remove('btn-ghost');
  document.getElementById('tabUsers').classList.add('btn-ghost');
});
document.getElementById('tabUsers').addEventListener('click', () => {
  document.getElementById('songsPanel').style.display = 'none';
  document.getElementById('usersPanel').style.display = '';
  document.getElementById('tabUsers').classList.remove('btn-ghost');
  document.getElementById('tabSongs').classList.add('btn-ghost');
  loadUsers();
});

loadSongs();
