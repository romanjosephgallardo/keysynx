/* ============================================
   index.html logic
   Tries api/songs.php for search/filter (incl. Camelot
   compatibility — Advanced Search Feature #5). Falls back
   to local sample data + client-side camelot.js scoring if
   the backend isn't reachable (e.g. opened without XAMPP).
   ============================================ */

let allSongs = [];
let backendAvailable = true;

const THUMB_GRADIENTS = [
  ['#E0995A', '#C2547E'], ['#5A9CE0', '#7E54C2'], ['#E05A8C', '#C27E54'],
  ['#5AD6A8', '#5A8CD6'], ['#E0C25A', '#E05A6E'], ['#9C5AE0', '#5AC2D6']
];
function thumbGradient(id){ return THUMB_GRADIENTS[id % THUMB_GRADIENTS.length]; }

function statusLabel(status){
  return status === 'verified' ? 'Verified' : status === 'pending' ? 'Pending' : 'Rejected';
}

function normalizeApiSong(raw){
  return {
    id: raw.id, title: raw.title, artist: raw.artist, bpm: raw.bpm !== null ? parseFloat(raw.bpm) : null,
    musicalKey: raw.musical_key, hasVariation: !!raw.has_variation,
    sectionKeys: raw.section_keys ? JSON.parse(raw.section_keys) : [],
    status: raw.status, upvotes: raw.upvotes, downvotes: raw.downvotes,
    transition: raw.transition || null
  };
}

function morePanelHTML(song){
  if(!song.hasVariation || !song.sectionKeys.length) return '';
  const lines = song.sectionKeys.map(sk =>
    `<div class="more-line"><b>${sk.section}:</b> ${sk.key}</div>`
  ).join('');
  return `
    <button type="button" class="more-toggle" data-id="${song.id}">
      <span>&#8801; More</span><span class="chev">&#9662;</span>
    </button>
    <div class="more-panel" id="more-${song.id}">
      <div class="more-panel-inner">${lines}</div>
    </div>
  `;
}

function songCardHTML(song){
  const [a, b] = thumbGradient(song.id);
  const star = song.hasVariation ? '<span class="star">*</span>' : '';
  const transitionBadge = song.transition
    ? `<span class="status-pill" style="background:rgba(185,163,255,0.15); color:var(--accent-violet); margin-left:8px;">${song.transition.score}% match</span>`
    : '';
  return `
    <div class="track-card-wrap" data-id="${song.id}">
      <div class="track-card" data-nav="${song.id}">
        <div class="track-thumb" style="--thumb-a:${a}; --thumb-b:${b}">&#9834;</div>
        <div class="track-main">
          <div class="track-title">${song.title}</div>
          <div class="track-artist">${song.artist}</div>
        </div>
        <div class="track-divider"></div>
        <div class="track-stats">
          <div class="track-key">${star}${song.musicalKey}</div>
          <div class="track-bpm"><span class="num">${song.bpm !== null ? song.bpm + ' BPM' : 'Unfixed tempo'}</span></div>
        </div>
        <span class="status-pill status-${song.status}" style="margin-left:8px;">${statusLabel(song.status)}</span>
        ${transitionBadge}
      </div>
      ${morePanelHTML(song)}
    </div>
  `;
}

function renderGrid(songs){
  const grid = document.getElementById('songGrid');
  if(!songs.length){
    grid.innerHTML = `<div class="empty-state"><div class="icon">&#9834;</div><div>No tracks match those filters.</div></div>`;
    return;
  }
  grid.innerHTML = songs.map(songCardHTML).join('');
}

function populateFilterOptions(songs){
  const keySel = document.getElementById('keyFilter');
  const compatSel = document.getElementById('compatibleWith');
  const keys = [...new Set(songs.map(s => s.musicalKey))].sort();
  keys.forEach(k => {
    const opt = document.createElement('option');
    opt.value = k; opt.textContent = k;
    keySel.appendChild(opt);
  });
  songs.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.id; opt.textContent = s.title;
    compatSel.appendChild(opt);
  });
}

async function applyFilters(){
  const q = document.getElementById('searchInput').value.trim();
  const key = document.getElementById('keyFilter').value;
  const bpmMin = document.getElementById('bpmMin').value;
  const bpmMax = document.getElementById('bpmMax').value;
  const verifiedOnly = document.getElementById('verifiedOnly').checked;
  const compatibleWith = document.getElementById('compatibleWith').value;

  if(backendAvailable){
    const params = new URLSearchParams();
    if(q) params.set('q', q);
    if(key) params.set('key', key);
    if(bpmMin) params.set('bpm_min', bpmMin);
    if(bpmMax) params.set('bpm_max', bpmMax);
    if(verifiedOnly) params.set('verified_only', '1');
    if(compatibleWith) params.set('compatible_with', compatibleWith);

    try {
      const res = await fetch(`api/songs.php?${params.toString()}`);
      if(!res.ok) throw new Error('bad response');
      const data = await res.json();
      renderGrid(data.songs.map(normalizeApiSong));
      return;
    } catch(e){
      backendAvailable = false; // fall through to local filtering below
    }
  }

  // ---- local fallback (no backend) ----
  const qLower = q.toLowerCase();
  let filtered = allSongs.filter(s => {
    const matchesQuery = !qLower || s.title.toLowerCase().includes(qLower) || s.artist.toLowerCase().includes(qLower);
    const matchesKey = !key || s.musicalKey === key;
    const matchesBpm = s.bpm >= (parseFloat(bpmMin) || 0) && s.bpm <= (parseFloat(bpmMax) || Infinity);
    const matchesVerified = !verifiedOnly || s.status === 'verified';
    return matchesQuery && matchesKey && matchesBpm && matchesVerified;
  });
  if(compatibleWith && typeof computeTransitionScore === 'function'){
    const base = allSongs.find(s => s.id === Number(compatibleWith));
    if(base){
      filtered = filtered
        .filter(s => s.id !== base.id)
        .map(s => ({ ...s, transition: computeTransitionScore(base, s) }))
        .filter(s => s.transition.score > 0)
        .sort((x, y) => y.transition.score - x.transition.score);
    }
  }
  renderGrid(filtered);
}

['searchInput','keyFilter','bpmMin','bpmMax','verifiedOnly','compatibleWith'].forEach(id => {
  document.getElementById(id).addEventListener('input', applyFilters);
  document.getElementById(id).addEventListener('change', applyFilters);
});

document.getElementById('resetFilters').addEventListener('click', () => {
  document.getElementById('searchInput').value = '';
  document.getElementById('keyFilter').value = '';
  document.getElementById('bpmMin').value = '';
  document.getElementById('bpmMax').value = '';
  document.getElementById('verifiedOnly').checked = false;
  document.getElementById('compatibleWith').value = '';
  applyFilters();
});

document.getElementById('songGrid').addEventListener('click', (e) => {
  const toggle = e.target.closest('.more-toggle');
  if(toggle){
    const id = toggle.dataset.id;
    const panel = document.getElementById(`more-${id}`);
    panel.classList.toggle('open');
    toggle.classList.toggle('open');
    return;
  }
  const card = e.target.closest('.track-card');
  if(card){ window.location.href = `song.html?id=${card.dataset.nav}`; }
});

// ---------------- Hero stats ----------------
fetch('api/stats.php')
  .then(res => { if(!res.ok) throw new Error('not ok'); return res.json(); })
  .then(stats => {
    document.getElementById('statSongs').textContent = stats.songs.toLocaleString();
    document.getElementById('statUsers').textContent = stats.users.toLocaleString();
    document.getElementById('statArtists').textContent = stats.artists.toLocaleString();
  })
  .catch(() => {
    document.getElementById('statSongs').textContent = '—';
    document.getElementById('statUsers').textContent = '—';
    document.getElementById('statArtists').textContent = '—';
  });

// ---------------- Boot ----------------
fetch('api/songs.php')
  .then(res => { if(!res.ok) throw new Error('not ok'); return res.json(); })
  .then(data => {
    backendAvailable = true;
    allSongs = data.songs.map(normalizeApiSong);
    populateFilterOptions(allSongs);
    renderGrid(allSongs);
  })
  .catch(() => {
    backendAvailable = false;
    fetchSongs().then(songs => {
      allSongs = songs;
      populateFilterOptions(songs);
      renderGrid(songs);
    });
  });
