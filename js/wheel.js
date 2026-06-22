/* ============================================
   wheel.html logic
   Renders an interactive Camelot Wheel: 12 key
   numbers x 2 rings (B=major outer, A=minor inner).
   Tracks from the album are plotted inside their
   key's wedge. Selecting a track highlights its
   key + compatible keys, draws transition lines to
   recommended tracks, and lists the ranked engine output.
   ============================================ */

const CX = 200, CY = 200;
const R_B_OUT = 190, R_B_IN = 128;
const R_A_OUT = 128, R_A_IN = 68;
const DOT_R_B = 159, DOT_R_A = 98; // radius where track dots sit within each ring

let allSongs = [];
let currentId = null;

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

function trackDotPositions(songs){
  // group songs sharing the same Camelot code so dots don't overlap
  const groups = {};
  songs.forEach(s => {
    const code = getCamelotCode(s.musicalKey);
    if(!code) return;
    groups[code] = groups[code] || [];
    groups[code].push(s);
  });

  const positions = {};
  Object.entries(groups).forEach(([code, group]) => {
    const { num, letter } = parseCamelot(code);
    const baseAngle = (num - 1) * 30;
    const r = letter === 'B' ? DOT_R_B : DOT_R_A;
    group.forEach((song, i) => {
      const jitter = (i - (group.length - 1) / 2) * 9;
      positions[song.id] = polar(r, baseAngle + jitter);
    });
  });
  return positions;
}

function renderWheel(){
  const current = allSongs.find(s => s.id === currentId);
  if(!current) return;

  const currentCode = getCamelotCode(current.musicalKey);
  const compatible = currentCode ? getCompatibleCodes(currentCode) : null;
  const positions = trackDotPositions(allSongs);

  // --- segment highlighting ---
  document.querySelectorAll('.wheel-seg').forEach(seg => {
    const code = seg.dataset.code;
    seg.classList.remove('current', 'compatible', 'dim');
    if(!currentCode){ seg.classList.add('dim'); return; }
    if(code === compatible.same) seg.classList.add('current');
    else if([compatible.relative, compatible.energyUp, compatible.energyDown].includes(code)) seg.classList.add('compatible');
    else seg.classList.add('dim');
  });

  // --- recommendations (engine output) ---
  const recs = getRecommendations(current, allSongs, 6);

  // --- track dots ---
  const dotsLayer = document.getElementById('dotsLayer');
  dotsLayer.innerHTML = Object.entries(positions).map(([id, pos]) => {
    const song = allSongs.find(s => s.id === Number(id));
    const isCurrent = song.id === current.id;
    const isRec = recs.some(r => r.song.id === song.id);
    const cls = isCurrent ? 'track-dot current' : isRec ? 'track-dot recommended' : 'track-dot';
    return `<circle class="${cls}" data-id="${song.id}" cx="${pos.x.toFixed(1)}" cy="${pos.y.toFixed(1)}" r="${isCurrent ? 8 : 5.5}"></circle>`;
  }).join('');

  // --- transition lines from current dot to each recommended dot ---
  const linesLayer = document.getElementById('linesLayer');
  const curPos = positions[current.id];
  linesLayer.innerHTML = curPos ? recs.map(r => {
    const pos = positions[r.song.id];
    if(!pos) return '';
    const opacity = (r.score / 100) * 0.85 + 0.1;
    const width = 1 + (r.score / 100) * 2.5;
    return `<line x1="${curPos.x.toFixed(1)}" y1="${curPos.y.toFixed(1)}" x2="${pos.x.toFixed(1)}" y2="${pos.y.toFixed(1)}" stroke="var(--accent-violet)" stroke-width="${width.toFixed(1)}" opacity="${opacity.toFixed(2)}"></line>`;
  }).join('') : '';

  // --- now-playing readout ---
  document.getElementById('nowPlaying').innerHTML = `
    <div class="track-title" style="font-size:1.1rem;">${current.title}</div>
    <div class="track-artist">${current.artist} · <span class="num">${current.bpm}</span> BPM · ${current.musicalKey}${currentCode ? ` (${currentCode})` : ' — not mappable'}</div>
  `;

  // --- recommendation list ---
  const list = document.getElementById('recList');
  if(!recs.length){
    list.innerHTML = `<div class="empty-state"><div class="icon">&#9834;</div>No compatible tracks found in this album.</div>`;
    return;
  }
  list.innerHTML = recs.map(r => `
    <div class="rec-row" data-id="${r.song.id}">
      <div class="rec-score" style="--pct:${r.score}%">${r.score}</div>
      <div class="rec-info">
        <div class="rec-title">${r.song.title}</div>
        <div class="rec-meta">${r.relation} · <span class="num">${r.song.bpm}</span> BPM (${r.bpmDiff > 0 ? '±' : ''}${r.bpmDiff}) · ${r.codeB}</div>
      </div>
      <button class="btn btn-sm rec-select">Set as current</button>
    </div>
  `).join('');
}

document.getElementById('trackSelect').addEventListener('change', (e) => {
  currentId = Number(e.target.value);
  renderWheel();
});

document.getElementById('wheelSvg').addEventListener('click', (e) => {
  const dot = e.target.closest('.track-dot');
  if(dot){
    currentId = Number(dot.dataset.id);
    document.getElementById('trackSelect').value = currentId;
    renderWheel();
  }
});

document.getElementById('recList').addEventListener('click', (e) => {
  if(e.target.classList.contains('rec-select')){
    currentId = Number(e.target.closest('.rec-row').dataset.id);
    document.getElementById('trackSelect').value = currentId;
    renderWheel();
  }
});

fetchSongs().then(songs => {
  allSongs = songs;

  document.getElementById('wheelGroup').insertAdjacentHTML('beforeend', buildWheelSkeleton());

  const select = document.getElementById('trackSelect');
  select.innerHTML = songs.map(s => `<option value="${s.id}">${s.title}</option>`).join('');

  const params = new URLSearchParams(window.location.search);
  const requestedId = Number(params.get('id'));
  currentId = songs.find(s => s.id === requestedId) ? requestedId : songs[0].id;
  select.value = currentId;

  renderWheel();
});
