/* ============================================
   song.html logic
   Tries the real API first (api/songs.php?id=X). If the
   backend isn't reachable (e.g. opened as a static file
   without XAMPP running), falls back to the local sample
   data in js/data.js so the page still demos something.
   ============================================ */

const THUMB_GRADIENTS = [
  ['#E0995A', '#C2547E'], ['#5A9CE0', '#7E54C2'], ['#E05A8C', '#C27E54'],
  ['#5AD6A8', '#5A8CD6'], ['#E0C25A', '#E05A6E'], ['#9C5AE0', '#5AC2D6']
];
function thumbGradient(id){ return THUMB_GRADIENTS[id % THUMB_GRADIENTS.length]; }

function statusLabel(status){
  return status === 'verified' ? '&#10003; Verified'
       : status === 'pending'  ? '&#9684; Pending review'
       : '&#10005; Rejected';
}

function confidenceColor(score){
  if(score >= 70) return 'var(--verified)';
  if(score >= 40) return 'var(--pending)';
  return 'var(--rejected)';
}

// Normalizes either an API row (snake_case, sections as DB rows)
// or a local sample-data row (camelCase) into one shape for rendering.
function normalizeSong(raw, fromApi){
  if(!fromApi){
    return {
      id: raw.id, title: raw.title, artist: raw.artist, bpm: raw.bpm,
      musicalKey: raw.musicalKey, hasVariation: raw.hasVariation,
      sectionKeys: raw.sectionKeys, footnote: raw.footnote,
      timeSignature: raw.timeSignature, submittedBy: raw.submittedBy,
      albumTitle: 'Eternal Sunshine', releaseYear: 2024, submittedById: null,
      status: raw.status, upvotes: raw.upvotes, downvotes: raw.downvotes,
      confidenceScore: null, reputationTier: null, comments: [], recommendations: []
    };
  }
  return {
    id: raw.id, title: raw.title, artist: raw.artist, bpm: raw.bpm !== null ? parseFloat(raw.bpm) : null,
    musicalKey: raw.musical_key, hasVariation: !!raw.has_variation,
    sectionKeys: raw.sections.map(s => ({ section: s.section_name, key: s.musical_key, bpm: s.bpm })),
    footnote: raw.footnote, timeSignature: raw.time_signature, youtubeUrl: raw.youtube_url, thumbnailUrl: raw.thumbnail_url,
    albumTitle: raw.album_title, releaseYear: raw.release_year,
    submittedBy: raw.submitted_by_username || 'unknown', submittedById: raw.submitted_by,
    status: raw.status, upvotes: raw.upvotes, downvotes: raw.downvotes,
    confidenceScore: raw.confidence_score, comments: raw.comments,
    recommendations: raw.recommendations.map(r => ({
      song: { id: r.song.id, title: r.song.title, bpm: r.song.bpm !== null ? parseFloat(r.song.bpm) : null },
      score: r.score, relation: r.relation
    }))
  };
}

function renderSong(song, fromApi){
  const root = document.getElementById('detailRoot');
  const [a, b] = thumbGradient(song.id);
  const star = song.hasVariation ? '<span class="star">*</span>' : '';
  const code = (typeof getCamelotCode === 'function') ? getCamelotCode(song.musicalKey) : null;

  // ---- Section timeline (Core Feature #3) ----
  const sectionsHTML = (song.sectionKeys && song.sectionKeys.length) ? `
    <div class="section-block">
      <div class="section-label">Section timeline</div>
      <div class="structure-track">
        ${song.sectionKeys.map(sk => `
          <div class="structure-seg" style="flex:1;">
            ${sk.section}
            <small>${sk.key}${sk.bpm ? ' · ' + sk.bpm + ' BPM' : ''}</small>
          </div>
        `).join('')}
      </div>
    </div>` : '';

  const footnoteHTML = song.footnote ? `
    <div class="section-block">
      <div class="section-label">Notes</div>
      <div class="more-line" style="font-size:0.92rem; white-space:pre-wrap; font-style:normal;">${song.footnote}</div>
    </div>` : '';

  function extractYoutubeId(url){
    if(!url) return null;
    const m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]{6,})/);
    return m ? m[1] : null;
  }
  const ytId = extractYoutubeId(song.youtubeUrl);
  const youtubeHTML = ytId ? `
    <div class="section-block">
      <div class="section-label">Listen (official YouTube link)</div>
      <div style="position:relative; padding-bottom:56.25%; border-radius:12px; overflow:hidden; background:var(--surface-2);">
        <iframe src="https://www.youtube.com/embed/${ytId}" style="position:absolute; inset:0; width:100%; height:100%; border:0;"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
      </div>
    </div>` : '';

  // ---- Confidence score ring (Beyond-MVP #2) ----
  const confidenceHTML = (song.confidenceScore !== null) ? `
    <div class="stat-chip" style="display:flex; align-items:center; gap:8px;">
      <div style="width:28px; height:28px; border-radius:50%; flex-shrink:0;
                  background: conic-gradient(${confidenceColor(song.confidenceScore)} ${song.confidenceScore}%, var(--border) ${song.confidenceScore}%);
                  display:flex; align-items:center; justify-content:center; position:relative;">
        <div style="position:absolute; inset:3px; border-radius:50%; background:var(--surface);"></div>
      </div>
      <span><b>${song.confidenceScore}</b> confidence</span>
    </div>` : '';

  const thumbHTML = song.thumbnailUrl
    ? `<img src="${song.thumbnailUrl}" alt="" style="width:80px; height:80px; border-radius:16px; object-fit:cover;" onerror="this.outerHTML='<div class=&quot;track-thumb&quot; style=&quot;--thumb-a:${a}; --thumb-b:${b}; width:80px; height:80px; font-size:1.8rem; border-radius:16px;&quot;>&#9834;</div>'">`
    : `<div class="track-thumb" style="--thumb-a:${a}; --thumb-b:${b}; width:80px; height:80px; font-size:1.8rem; border-radius:16px;">&#9834;</div>`;

  root.insertAdjacentHTML('beforeend', `
    <div class="detail-head">
      ${thumbHTML}
      <div>
        <h1 class="detail-title">${song.title}</h1>
        <div class="detail-artist">${song.artist}${song.albumTitle ? ' · ' + song.albumTitle : ''}${song.releaseYear ? ' (' + song.releaseYear + ')' : ''}</div>
        <div class="detail-stats">
          <div class="stat-chip"><b class="num">${song.bpm !== null ? song.bpm : 'Unfixed tempo'}</b>${song.bpm !== null ? ' BPM' : ''}</div>
          <div class="stat-chip">${star}<b>${song.musicalKey}</b>${code ? ` <span style="color:var(--text-dim)">(${code})</span>` : ''}</div>
          ${song.timeSignature ? `<div class="stat-chip"><b>${song.timeSignature}</b></div>` : ''}
          ${confidenceHTML}
          <div class="stat-chip status-pill status-${song.status}" style="background:transparent;border:1px solid var(--border)">
            ${statusLabel(song.status)}
          </div>
        </div>
      </div>
    </div>

    ${sectionsHTML}
    ${footnoteHTML}
    ${youtubeHTML}

    <div class="section-block" id="transitionsBlock"></div>

    <div class="section-block" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
      <div style="color:var(--text-muted); font-size:0.88rem; display:flex; align-items:center; gap:10px;">
        <span>Submitted by <b style="color:var(--text)">${song.submittedBy}</b></span>
        <a href="submit.html?edit=${song.id}" class="btn btn-ghost btn-sm" id="editBtn" style="display:none;">✎ Edit</a>
      </div>
      <div class="vote-row">
        <button class="vote-btn up" id="upBtn">&#9650; <span class="num" id="upCount">${song.upvotes}</span></button>
        <button class="vote-btn down" id="downBtn">&#9660; <span class="num" id="downCount">${song.downvotes}</span></button>
      </div>
    </div>

    <div class="section-block" id="commentsBlock"></div>
  `);

  if(fromApi){
    renderTransitionsFromApi(song);
  } else {
    renderTransitionsLocal(song);
  }
  renderComments(song, fromApi);
  wireVoteButtons(song, fromApi);
  wireEditButton(song);
}

function wireEditButton(song){
  const reveal = () => {
    const user = window.Alpine && Alpine.store('auth').user;
    if(!user) return;
    const isOwner = song.submittedById !== null && song.submittedById !== undefined && Number(user.id) === Number(song.submittedById);
    const isAdmin = user.role === 'admin';
    if(isOwner || isAdmin) document.getElementById('editBtn').style.display = '';
  };
  // auth store may still be loading when this first runs — check now and shortly after
  reveal();
  setTimeout(reveal, 400);
}

function wireVoteButtons(song, fromApi){
  let voted = null;
  document.getElementById('upBtn').addEventListener('click', () => castVote(song.id, 'up', fromApi));
  document.getElementById('downBtn').addEventListener('click', () => castVote(song.id, 'down', fromApi));
}

async function castVote(songId, voteType, fromApi){
  if(!fromApi){
    // local-only demo fallback (no backend) — just bump the visible counter
    const el = document.getElementById(voteType === 'up' ? 'upCount' : 'downCount');
    el.textContent = Number(el.textContent) + 1;
    return;
  }
  if(!Alpine.store('auth').user){
    alert('Log in first (top-right) to vote.');
    return;
  }
  try {
    const res = await fetch('api/vote.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ song_id: songId, vote_type: voteType })
    });
    const data = await res.json();
    if(!res.ok){ alert(data.error || 'Vote failed.'); return; }
    document.getElementById('upCount').textContent = data.song.upvotes;
    document.getElementById('downCount').textContent = data.song.downvotes;
  } catch(e){
    alert('Could not reach the server.');
  }
}

function renderTransitionsFromApi(song){
  const block = document.getElementById('transitionsBlock');
  const recs = song.recommendations;
  const rowsHTML = recs.length ? recs.map(r => `
    <a href="song.html?id=${r.song.id}" class="rec-row" style="text-decoration:none; color:inherit;">
      <div class="rec-score" style="--pct:${r.score}%">${r.score}</div>
      <div class="rec-info">
        <div class="rec-title">${r.song.title}</div>
        <div class="rec-meta">${r.relation} · <span class="num">${r.song.bpm !== null ? r.song.bpm + ' BPM' : 'unfixed tempo'}</span></div>
      </div>
    </a>
  `).join('') : `<div class="more-line">No mappable compatible tracks in this album.</div>`;

  block.innerHTML = `
    <div class="section-label" style="display:flex; align-items:center; justify-content:space-between;">
      <span>Recommended transitions</span>
      <a href="wheel.html?id=${song.id}" class="btn btn-ghost btn-sm">Open on wheel →</a>
    </div>
    <div class="rec-list">${rowsHTML}</div>
  `;
}

function renderTransitionsLocal(song){
  const block = document.getElementById('transitionsBlock');
  fetchSongs().then(allSongs => {
    const recs = getRecommendations(song, allSongs, 3);
    const rowsHTML = recs.length ? recs.map(r => `
      <a href="song.html?id=${r.song.id}" class="rec-row" style="text-decoration:none; color:inherit;">
        <div class="rec-score" style="--pct:${r.score}%">${r.score}</div>
        <div class="rec-info">
          <div class="rec-title">${r.song.title}</div>
          <div class="rec-meta">${r.relation} · <span class="num">${r.song.bpm !== null ? r.song.bpm + ' BPM' : 'unfixed tempo'}</span></div>
        </div>
      </a>
    `).join('') : `<div class="more-line">No mappable compatible tracks in this album.</div>`;
    block.innerHTML = `
      <div class="section-label" style="display:flex; align-items:center; justify-content:space-between;">
        <span>Recommended transitions</span>
        <a href="wheel.html?id=${song.id}" class="btn btn-ghost btn-sm">Open on wheel →</a>
      </div>
      <div class="rec-list">${rowsHTML}</div>
    `;
  });
}

// ---- Contributor feedback (comments) — Alpine-powered ----
function renderComments(song, fromApi){
  const block = document.getElementById('commentsBlock');
  const comments = song.comments || [];

  block.innerHTML = `
    <div x-data="{ comments: ${JSON.stringify(comments)}, text: '', posting: false }">
      <div class="section-label">Contributor feedback (${comments.length})</div>

      <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:14px;">
        <template x-for="c in comments" :key="c.id">
          <div class="more-line" style="font-style:normal; background:var(--surface-2); border-radius:10px; padding:10px 12px;">
            <span style="color:var(--text); font-weight:600;" x-text="c.username"></span>
            <span style="color:var(--text-dim); font-size:0.75rem;" x-text="' · ' + (c.reputation_points || 0) + ' rep'"></span>
            <div style="margin-top:4px; color:var(--text-muted);" x-text="c.comment"></div>
          </div>
        </template>
        <p x-show="comments.length === 0" class="more-line">No feedback yet — be the first to weigh in.</p>
      </div>

      <div style="display:flex; gap:8px;">
        <input x-model="text" type="text" placeholder="${fromApi ? 'Add feedback on this analysis...' : 'Log in to add feedback (demo mode has no backend)'}"
               class="search-input" style="flex:1;" ${fromApi ? '' : 'disabled'}>
        <button
          ${fromApi ? `@click="(async () => {
            if(!$store.auth.user){ alert('Log in first to leave feedback.'); return; }
            if(!text.trim()) return;
            posting = true;
            try {
              const res = await fetch('api/comments.php', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ song_id: ${song.id}, comment: text })
              });
              if(res.ok){
                comments.unshift({ id: Date.now(), username: $store.auth.user.username, reputation_points: $store.auth.user.reputation_points, comment: text });
                text = '';
              } else {
                const d = await res.json(); alert(d.error || 'Could not post comment.');
              }
            } catch(e){ alert('Could not reach the server.'); }
            posting = false;
          })()"` : 'disabled'}
          class="btn btn-primary btn-sm">Post</button>
      </div>
    </div>
  `;
}

// ---------------- Boot ----------------
const params = new URLSearchParams(window.location.search);
const id = params.get('id') || 1;

fetch(`api/songs.php?id=${id}`)
  .then(res => { if(!res.ok) throw new Error('not ok'); return res.json(); })
  .then(raw => renderSong(normalizeSong(raw, true), true))
  .catch(() => {
    // Backend unreachable — fall back to local sample data (js/data.js)
    fetchSongById(id).then(raw => {
      if(!raw){
        document.getElementById('detailRoot').insertAdjacentHTML('beforeend',
          `<div class="empty-state"><div class="icon">&#9834;</div>Track not found.</div>`);
        return;
      }
      renderSong(normalizeSong(raw, false), false);
    });
  });
