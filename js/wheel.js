/* ============================================
   wheel.html logic
   - Track mode (existing): pick a track, see its key
     highlighted + compatible keys + recommended transitions.
   - Key mode (new): click a bare wedge on the wheel (not a
     track dot) to see that key's relatives and a sample of
     library tracks in that exact key, independent of any
     specific track.
   Tries the live API first; falls back to local sample data.
   ============================================ */

const CX = 200, CY = 200;
const R_B_OUT = 190, R_B_IN = 128;
const R_A_OUT = 128, R_A_IN = 68;
const DOT_R_B = 159, DOT_R_A = 98;

let allSongs = [];
let currentId = null;
let backendAvailable = true;

function polar(r, angleDeg){
  const a = angleDeg * Math.PI / 180;
  return { x: CX + r * Math.sin(a), y: CY - r * Math.cos(a) };
}

function wedgePath(rOuter, rInner, startAngle, endAngle){
  const p1 = polar(rOuter, startAngle);
  const p2 = polar(rOuter, endAngle);
  const p3 = polar(rInner, endAngle);
  const p4 = polar(rInner, startAngle);
  return `M ${p1.x.toFixed(2)} ${p1.y.toFixed(2)} A ${rOuter} ${rOuter} 0 0 1 ${p2.x.toFixed(2)} ${p2.y.toFixed(2)} L ${p3.x.toFixed(2)} ${p3.y.toFixed(2)} A ${rInner} ${rInner} 0 0 0 ${p4.x.toFixed(2)} ${p4.y.toFixed(2)} Z`;
}

function buildWheelSkeleton(){
  let svg = '';
  for(let n = 1; n <= 12; n++){
    const hue = (n - 1) * 30;
    const start = (n - 1) * 30 - 15;
    const end = (n - 1) * 30 + 15;
    const labelB = polar((R_B_OUT + R_B_IN) / 2, (n - 1) * 30);
    const labelA = polar((R_A_OUT + R_A_IN) / 2, (n - 1) * 30);

    svg += `<path class="wheel-seg dim" data-code="${n}B" d="${wedgePath(R_B_OUT, R_B_IN, start, end)}" fill="hsl(${hue},60%,42%)"></path>`;
    svg += `<path class="wheel-seg dim" data-code="${n}A" d="${wedgePath(R_A_OUT, R_A_IN, start, end)}" fill="hsl(${hue},42%,26%)"></path>`;
    svg += `<text class="wheel-label" x="${labelB.x.toFixed(1)}" y="${labelB.y.toFixed(1)}">${n}B</text>`;
    svg += `<text class="wheel-label" x="${labelA.x.toFixed(1)}" y="${labelA.y.toFixed(1)}">${n}A</text>`;
  }
  return svg;
}

function dotPosition(code, jitter = 0){
  const { num, letter } = parseCamelot(code);
  const baseAngle = (num - 1) * 30;
  const r = letter === 'B' ? DOT_R_B : DOT_R_A;
  return polar(r, baseAngle + jitter);
}

function highlightSegments(currentCode){
  const compatible = currentCode ? getCompatibleCodes(currentCode) : null;
  document.querySelectorAll('.wheel-seg').forEach(seg => {
    const code = seg.dataset.code;
    seg.classList.remove('current', 'compatible', 'dim');
    if(!currentCode){ seg.classList.add('dim'); return; }
    if(code === compatible.same) seg.classList.add('current');
    else if([compatible.relative, compatible.energyUp, compatible.energyDown].includes(code)) seg.classList.add('compatible');
    else seg.classList.add('dim');
  });
}

// ---------------- Track mode ----------------
function showTrackView(){
  document.getElementById('trackView').style.display = '';
  document.getElementById('keyView').style.display = 'none';
}

function renderTrackMode(){
  showTrackView();
  const current = allSongs.find(s => s.id === currentId);
  if(!current) return;

  const currentCode = getCamelotCode(current.musicalKey);
  highlightSegments(currentCode);

  const recs = getRecommendations(current, allSongs, 6);

  // Only plot dots for the current track + its recommendations — keeps
  // the wheel readable regardless of how large the catalog gets.
  const relevant = [current, ...recs.map(r => r.song)];
  const positions = {};
  const byCode = {};
  relevant.forEach(s => {
    const code = getCamelotCode(s.musicalKey);
    if(!code) return;
    byCode[code] = byCode[code] || [];
    byCode[code].push(s);
  });
  Object.entries(byCode).forEach(([code, group]) => {
    group.forEach((song, i) => {
      const jitter = (i - (group.length - 1) / 2) * 9;
      positions[song.id] = dotPosition(code, jitter);
    });
  });

  const dotsLayer = document.getElementById('dotsLayer');
  dotsLayer.innerHTML = Object.entries(positions).map(([id, pos]) => {
    const song = relevant.find(s => s.id === Number(id));
    const isCurrent = song.id === current.id;
    const isRec = recs.some(r => r.song.id === song.id);
    const cls = isCurrent ? 'track-dot current' : isRec ? 'track-dot recommended' : 'track-dot';
    return `<circle class="${cls}" data-id="${song.id}" cx="${pos.x.toFixed(1)}" cy="${pos.y.toFixed(1)}" r="${isCurrent ? 8 : 5.5}"></circle>`;
  }).join('');

  const linesLayer = document.getElementById('linesLayer');
  const curPos = positions[current.id];
  linesLayer.innerHTML = curPos ? recs.map(r => {
    const pos = positions[r.song.id];
    if(!pos) return '';
    const opacity = (r.score / 100) * 0.85 + 0.1;
    const width = 1 + (r.score / 100) * 2.5;
    return `<line x1="${curPos.x.toFixed(1)}" y1="${curPos.y.toFixed(1)}" x2="${pos.x.toFixed(1)}" y2="${pos.y.toFixed(1)}" stroke="var(--accent-violet)" stroke-width="${width.toFixed(1)}" opacity="${opacity.toFixed(2)}"></line>`;
  }).join('') : '';

  document.getElementById('nowPlaying').innerHTML = `
    <div class="track-title" style="font-size:1.1rem;">${current.title}</div>
    <div class="track-artist">${current.artist} · <span class="num">${current.bpm ?? 'unfixed tempo'}</span>${current.bpm !== null ? ' BPM' : ''} · ${current.musicalKey}${currentCode ? ` (${currentCode})` : ' — not mappable'}</div>
  `;
  document.getElementById('keySearchInput').value = current.musicalKey;

  const list = document.getElementById('recList');
  if(!recs.length){
    list.innerHTML = `<div class="empty-state"><div class="icon">&#9834;</div>No compatible tracks found.</div>`;
    return;
  }
  list.innerHTML = recs.map(r => `
    <div class="rec-row" data-id="${r.song.id}">
      <div class="rec-score" style="--pct:${r.score}%">${r.score}</div>
      <div class="rec-info">
        <div class="rec-title">${r.song.title}</div>
        <div class="rec-meta">${r.relation} · <span class="num">${r.song.bpm ?? 'unfixed tempo'}</span>${r.song.bpm !== null ? ' BPM' : ''} (${r.codeB})</div>
      </div>
      <button class="btn btn-sm rec-select">Set as current</button>
    </div>
  `).join('');
}

// ---------------- Key mode (click a bare wedge) ----------------
async function renderKeyMode(code){
  document.getElementById('trackView').style.display = 'none';
  document.getElementById('keyView').style.display = '';
  highlightSegments(code);

  const compatible = getCompatibleCodes(code);
  const relationLabel = {
    [compatible.relative]: 'Relative major/minor',
    [compatible.energyUp]: 'Energy boost (+1 step)',
    [compatible.energyDown]: 'Energy drop (−1 step)'
  };
  const keyNames = getKeysForCode(code).join(' / ') || '(modal/unmapped)';

  const familyHTML = [compatible.relative, compatible.energyUp, compatible.energyDown].map(c => `
    <div class="more-line" style="font-size:0.88rem; margin-bottom:6px;">
      <b>${c}</b> (${getKeysForCode(c).join(' / ') || '—'}) — ${relationLabel[c]}
    </div>
  `).join('');

  let songsHTML = `<div class="more-line">Loading…</div>`;
  let total = 0;
  let sample = [];

  if(backendAvailable){
    try {
      const res = await fetch(`api/songs.php?camelot_code=${encodeURIComponent(code)}&per_page=8`);
      const data = await res.json();
      total = data.total;
      sample = data.songs.map(s => ({ id: s.id, title: s.title, artist: s.artist }));
    } catch(e){ backendAvailable = false; }
  }
  if(!backendAvailable){
    const matches = allSongs.filter(s => getCamelotCode(s.musicalKey) === code);
    total = matches.length;
    sample = matches.slice(0, 8);
  }

  songsHTML = sample.length ? sample.map(s => `
    <a href="song.html?id=${s.id}" class="rec-row" style="text-decoration:none; color:inherit;">
      <div class="rec-info">
        <div class="rec-title">${s.title}</div>
        <div class="rec-meta">${s.artist}</div>
      </div>
    </a>
  `).join('') : `<div class="more-line">No tracks in this key yet.</div>`;

  document.getElementById('keyInfoPanel').innerHTML = `
    <div class="now-playing">
      <div class="track-title" style="font-size:1.1rem;">${code}</div>
      <div class="track-artist">${keyNames}</div>
    </div>
    <div class="section-label" style="margin-top:18px;">Compatible keys</div>
    ${familyHTML}
    <div class="section-label" style="margin-top:18px; display:flex; justify-content:space-between; align-items:center;">
      <span>Tracks in this key (${total})</span>
      <a href="index.html?camelot_code=${encodeURIComponent(code)}" class="btn btn-ghost btn-sm">View all in library →</a>
    </div>
    <div class="rec-list">${songsHTML}</div>
  `;
}

document.getElementById('backToTrackBtn').addEventListener('click', () => {
  renderTrackMode();
});

// ---------------- Key search input ----------------
// Case-insensitive lookup so "b major", "B Major", or a raw Camelot
// code like "1b" all resolve correctly.
const KEY_NAME_LOOKUP = {};
Object.keys(CAMELOT_MAP).forEach(k => { KEY_NAME_LOOKUP[k.toLowerCase()] = CAMELOT_MAP[k]; });

function resolveKeyQuery(raw){
  const q = raw.trim();
  if(!q) return null;
  if(KEY_NAME_LOOKUP[q.toLowerCase()]) return KEY_NAME_LOOKUP[q.toLowerCase()];
  const codeMatch = q.match(/^(\d{1,2})\s*([ab])$/i);
  if(codeMatch){
    const code = `${codeMatch[1]}${codeMatch[2].toUpperCase()}`;
    if(REVERSE_CAMELOT[code]) return code;
  }
  return null;
}

function populateKeyDatalist(){
  // no-op now — kept as a stub in case something else still calls it
}

function runKeySearch(){
  const input = document.getElementById('keySearchInput');
  const errorEl = document.getElementById('keySearchError');
  const code = resolveKeyQuery(input.value);
  if(code){
    errorEl.style.display = 'none';
    renderKeyMode(code);
  } else {
    errorEl.textContent = `Couldn't match "${input.value}" to a key. Try something like "B Major", "A Minor", or a Camelot code like "1B".`;
    errorEl.style.display = '';
  }
}

document.getElementById('keySearchBtn').addEventListener('click', runKeySearch);
document.getElementById('keySearchInput').addEventListener('keydown', (e) => {
  if(e.key === 'Enter'){ e.preventDefault(); runKeySearch(); }
});

// ---------------- Event wiring ----------------
document.getElementById('wheelSvg').addEventListener('click', (e) => {
  const dot = e.target.closest('.track-dot');
  if(dot){
    currentId = Number(dot.dataset.id);
    renderTrackMode();
    return;
  }
  const seg = e.target.closest('.wheel-seg');
  if(seg){
    renderKeyMode(seg.dataset.code);
  }
});

document.getElementById('recList').addEventListener('click', (e) => {
  if(e.target.classList.contains('rec-select')){
    currentId = Number(e.target.closest('.rec-row').dataset.id);
    renderTrackMode();
  }
});

// ---------------- Boot ----------------
async function boot(){
  document.getElementById('wheelGroup').insertAdjacentHTML('beforeend', buildWheelSkeleton());
  populateKeyDatalist();

  try {
    const res = await fetch('api/songs.php?per_page=500&verified_only=0');
    if(!res.ok) throw new Error('not ok');
    const data = await res.json();
    backendAvailable = true;
    allSongs = data.songs.map(s => ({
      id: s.id, title: s.title, artist: s.artist,
      bpm: s.bpm !== null ? parseFloat(s.bpm) : null,
      musicalKey: s.musical_key
    }));
  } catch(e){
    backendAvailable = false;
    allSongs = await fetchSongs();
  }

  const params = new URLSearchParams(window.location.search);
  const requestedId = Number(params.get('id'));

  if(allSongs.find(s => s.id === requestedId)){
    currentId = requestedId;
    renderTrackMode();
  } else {
    // No specific track requested — default into key mode so the
    // search box is the natural starting point, per the new flow.
    renderKeyMode('8B'); // C Major — a neutral, common default
  }
}

boot();
