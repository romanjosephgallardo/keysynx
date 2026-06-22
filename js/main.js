/* ============================================
   index.html logic
   Sortable, paginated library table (matches the
   reference "Showing X to Y of Z results" pattern).
   Tries api/songs.php first; falls back to local
   sample data (paginated client-side) if unreachable.
   ============================================ */

let backendAvailable = true;
let allSongsLocal = []; // only populated in fallback mode

let state = {
  page: 1,
  perPage: 25,
  sort: null,      // null = default (id) order
  dir: 'asc',
  q: '', key: '', bpmMin: '', bpmMax: '', verifiedOnly: false,
  camelotCode: new URLSearchParams(window.location.search).get('camelot_code') || ''
};

const THUMB_GRADIENTS = [
  ['#E0995A', '#C2547E'], ['#5A9CE0', '#7E54C2'], ['#E05A8C', '#C27E54'],
  ['#5AD6A8', '#5A8CD6'], ['#E0C25A', '#E05A6E'], ['#9C5AE0', '#5AC2D6']
];
function thumbGradient(id){ return THUMB_GRADIENTS[id % THUMB_GRADIENTS.length]; }

function thumbCellHTML(song){
  if(song.thumbnail_url){
    return `<img class="lib-thumb" src="${song.thumbnail_url}" alt="" loading="lazy"
                 onerror="this.outerHTML=this.parentElement.dataset.fallback">`;
  }
  const [a, b] = thumbGradient(song.id);
  return `<div class="lib-thumb-fallback" style="--thumb-a:${a}; --thumb-b:${b}">&#9834;</div>`;
}

function bpmLabel(bpm){
  return (bpm === null || bpm === undefined) ? '<span style="color:var(--text-dim)">—</span>' : `<span class="lib-bpm-badge">${bpm}</span>`;
}

function rowHTML(song){
  const fallback = (() => {
    const [a, b] = thumbGradient(song.id);
    return `<div class="lib-thumb-fallback" style="--thumb-a:${a}; --thumb-b:${b}">&#9834;</div>`;
  })().replace(/"/g, '&quot;');

  return `
    <tr data-id="${song.id}">
      <td data-fallback="${fallback}">${thumbCellHTML(song)}</td>
      <td class="lib-artist">${song.artist}</td>
      <td class="lib-title">${song.title}${song.hasVariation ? '<span class="star"> *</span>' : ''}</td>
      <td class="lib-key">${song.musicalKey}</td>
      <td>${bpmLabel(song.bpm)}</td>
      <td class="lib-year">${song.releaseYear ?? '—'}</td>
    </tr>
  `;
}

function normalizeApiSong(raw){
  return {
    id: raw.id, title: raw.title, artist: raw.artist,
    bpm: raw.bpm !== null ? parseFloat(raw.bpm) : null,
    musicalKey: raw.musical_key, hasVariation: !!raw.has_variation,
    status: raw.status, thumbnail_url: raw.thumbnail_url,
    releaseYear: raw.release_year, albumTitle: raw.album_title
  };
}

function renderRows(songs){
  const body = document.getElementById('libraryBody');
  if(!songs.length){
    body.innerHTML = `<tr><td colspan="6"><div class="empty-state"><div class="icon">&#9834;</div>No tracks match those filters.</div></td></tr>`;
    return;
  }
  body.innerHTML = songs.map(rowHTML).join('');
}

document.getElementById('libraryBody').addEventListener('click', (e) => {
  const row = e.target.closest('tr');
  if(row && row.dataset.id) window.location.href = `song.html?id=${row.dataset.id}`;
});

// ---------------- Sorting ----------------
document.querySelectorAll('.lib-sortable').forEach(th => {
  th.addEventListener('click', () => {
    const col = th.dataset.sort;
    if(state.sort === col){ state.dir = state.dir === 'asc' ? 'desc' : 'asc'; }
    else { state.sort = col; state.dir = 'asc'; }
    state.page = 1;
    document.querySelectorAll('.lib-sortable').forEach(h => {
      h.classList.toggle('active', h === th);
      h.querySelector('.sort-arrow').textContent = h === th ? (state.dir === 'asc' ? '▲' : '▼') : '';
    });
    load();
  });
});

// ---------------- Pagination ----------------
function renderPagination(total, page, perPage, totalPages){
  const start = total === 0 ? 0 : (page - 1) * perPage + 1;
  const end = Math.min(total, page * perPage);
  document.getElementById('paginationInfo').textContent =
    `Showing ${start} to ${end} of ${total.toLocaleString()} results`;

  document.getElementById('firstPageBtn').disabled = page <= 1;
  document.getElementById('prevPageBtn').disabled = page <= 1;
  document.getElementById('nextPageBtn').disabled = page >= totalPages;
  document.getElementById('lastPageBtn').disabled = page >= totalPages;

  const pages = [];
  const add = (p) => { if(!pages.includes(p)) pages.push(p); };
  add(1);
  for(let p = page - 1; p <= page + 1; p++) if(p > 0 && p <= totalPages) add(p);
  add(totalPages);
  pages.sort((a,b) => a - b);

  let html = '';
  let prev = 0;
  for(const p of pages){
    if(p - prev > 1) html += `<span class="page-ellipsis">…</span>`;
    html += `<div class="page-num ${p === page ? 'active' : ''}" data-page="${p}">${p}</div>`;
    prev = p;
  }
  document.getElementById('pageNumbers').innerHTML = html;
  document.querySelectorAll('.page-num').forEach(el => {
    el.addEventListener('click', () => { state.page = Number(el.dataset.page); load(); });
  });
}

document.getElementById('firstPageBtn').addEventListener('click', () => { state.page = 1; load(); });
document.getElementById('prevPageBtn').addEventListener('click', () => { state.page = Math.max(1, state.page - 1); load(); });
document.getElementById('nextPageBtn').addEventListener('click', () => { state.page++; load(); });
document.getElementById('lastPageBtn').addEventListener('click', () => { state.page = lastTotalPages; load(); });
document.getElementById('perPageSelect').addEventListener('change', (e) => {
  state.perPage = Number(e.target.value); state.page = 1; load();
});

let lastTotalPages = 1;

// ---------------- Filters ----------------
function populateFilterOptions(songs){
  const keySel = document.getElementById('keyFilter');
  const keys = [...new Set(songs.map(s => s.musicalKey))].sort();
  keys.forEach(k => {
    const opt = document.createElement('option');
    opt.value = k; opt.textContent = k;
    keySel.appendChild(opt);
  });
}

function readFilters(){
  state.q = document.getElementById('searchInput').value.trim();
  state.key = document.getElementById('keyFilter').value;
  state.bpmMin = document.getElementById('bpmMin').value;
  state.bpmMax = document.getElementById('bpmMax').value;
  state.verifiedOnly = document.getElementById('verifiedOnly').checked;
}

['searchInput','keyFilter','bpmMin','bpmMax','verifiedOnly'].forEach(id => {
  document.getElementById(id).addEventListener('input', () => { state.page = 1; readFilters(); load(); });
  document.getElementById(id).addEventListener('change', () => { state.page = 1; readFilters(); load(); });
});

document.getElementById('resetFilters').addEventListener('click', () => {
  document.getElementById('searchInput').value = '';
  document.getElementById('keyFilter').value = '';
  document.getElementById('bpmMin').value = '';
  document.getElementById('bpmMax').value = '';
  document.getElementById('verifiedOnly').checked = false;
  state = { ...state, page: 1, q:'', key:'', bpmMin:'', bpmMax:'', verifiedOnly:false };
  load();
});

// ---------------- Load (API with local fallback) ----------------
async function load(){
  if(backendAvailable){
    const params = new URLSearchParams();
    if(state.q) params.set('q', state.q);
    if(state.key) params.set('key', state.key);
    if(state.bpmMin) params.set('bpm_min', state.bpmMin);
    if(state.bpmMax) params.set('bpm_max', state.bpmMax);
    if(state.verifiedOnly) params.set('verified_only', '1');
    if(state.camelotCode) params.set('camelot_code', state.camelotCode);
    params.set('page', state.page);
    params.set('per_page', state.perPage);
    if(state.sort) { params.set('sort', state.sort); params.set('dir', state.dir); }

    try {
      const res = await fetch(`api/songs.php?${params.toString()}`);
      if(!res.ok) throw new Error('bad response');
      const data = await res.json();
      lastTotalPages = data.total_pages || 1;
      renderRows(data.songs.map(normalizeApiSong));
      renderPagination(data.total, data.page, data.per_page, data.total_pages);
      return;
    } catch(e){
      backendAvailable = false;
    }
  }

  // ---- local fallback: paginate the small sample dataset client-side ----
  const qLower = state.q.toLowerCase();
  let filtered = allSongsLocal.filter(s => {
    const matchesQuery = !qLower || s.title.toLowerCase().includes(qLower) || s.artist.toLowerCase().includes(qLower);
    const matchesKey = !state.key || s.musicalKey === state.key;
    const matchesBpm = (s.bpm ?? 0) >= (parseFloat(state.bpmMin) || 0) && (s.bpm ?? Infinity) <= (parseFloat(state.bpmMax) || Infinity);
    const matchesVerified = !state.verifiedOnly || s.status === 'verified';
    return matchesQuery && matchesKey && matchesBpm && matchesVerified;
  });

  const total = filtered.length;
  const totalPages = Math.max(1, Math.ceil(total / state.perPage));
  lastTotalPages = totalPages;
  const startIdx = (state.page - 1) * state.perPage;
  const pageSongs = filtered.slice(startIdx, startIdx + state.perPage);

  renderRows(pageSongs);
  renderPagination(total, state.page, state.perPage, totalPages);
}

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
const urlParams = new URLSearchParams(window.location.search);
const urlCamelotCode = urlParams.get('camelot_code');
if(urlCamelotCode){
  document.getElementById('libraryAnchor').insertAdjacentHTML('afterend', `
    <div style="margin-bottom:16px;">
      <span class="status-pill" style="background:rgba(185,163,255,0.15); color:var(--accent-violet); padding:6px 12px;">
        Filtered by key: <b>${urlCamelotCode}</b>
        <a href="index.html" style="margin-left:8px; color:var(--text-dim); text-decoration:none;">✕ clear</a>
      </span>
    </div>
  `);
}

fetch('api/songs.php?per_page=1')
  .then(res => { if(!res.ok) throw new Error('not ok'); return res.json(); })
  .then(() => {
    backendAvailable = true;
    load();
    // populate filter dropdowns from a larger sample (first 200 by default order)
    fetch('api/songs.php?per_page=200').then(r => r.json()).then(d => {
      populateFilterOptions(d.songs.map(normalizeApiSong));
    });
  })
  .catch(() => {
    backendAvailable = false;
    fetchSongs().then(songs => {
      allSongsLocal = songs;
      populateFilterOptions(songs);
      load();
    });
  });
