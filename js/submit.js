/* ============================================
   submit.html logic
   Two modes, both on this same page:
   - New submission (default):       POST api/submit.php
   - Edit existing (?edit=<id>):      POST api/update_song.php,
     only allowed if the logged-in user is the original
     submitter or an admin (also enforced server-side).
   ============================================ */

const params = new URLSearchParams(window.location.search);
const editId = params.get('edit');
let isEditMode = !!editId;

document.getElementById('youtubeUrl').addEventListener('input', (e) => {
  const m = e.target.value.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]{6,})/);
  const preview = document.getElementById('ytPreview');
  if(m){
    preview.src = `https://img.youtube.com/vi/${m[1]}/hqdefault.jpg`;
    preview.style.display = '';
  } else {
    preview.style.display = 'none';
  }
});

function addStructureRow(section = '', key = ''){
  const builder = document.getElementById('structureBuilder');
  const row = document.createElement('div');
  row.className = 'structure-row';
  row.innerHTML = `
    <input type="text" placeholder="Section (e.g. Chorus)" class="seg-name" value="${section}">
    <input type="text" placeholder="Key (e.g. Bb Minor)" class="seg-bars" value="${key}">
    <button type="button" class="icon-btn remove-row">×</button>
  `;
  builder.appendChild(row);
}

document.getElementById('addRow').addEventListener('click', () => addStructureRow());

document.getElementById('structureBuilder').addEventListener('click', (e) => {
  if(e.target.classList.contains('remove-row')){
    const rows = document.querySelectorAll('.structure-row');
    if(rows.length > 1){ e.target.closest('.structure-row').remove(); }
  }
});

function clearStructureRows(){
  document.getElementById('structureBuilder').innerHTML = '';
}

async function loadForEdit(id){
  const statusEl = document.getElementById('formStatus');
  try {
    const res = await fetch(`api/songs.php?id=${id}`);
    if(!res.ok) throw new Error('not found');
    const song = await res.json();

    // Ownership check (UI-level convenience; the API enforces this for real)
    const checkOwnership = () => {
      const user = window.Alpine && Alpine.store('auth').user;
      if(!user){
        statusEl.style.color = 'var(--rejected)';
        statusEl.textContent = 'Log in (top-right) to edit this submission.';
        document.getElementById('submitForm').style.opacity = '0.5';
        document.getElementById('submitForm').style.pointerEvents = 'none';
        return false;
      }
      const isOwner = song.submitted_by !== null && Number(user.id) === Number(song.submitted_by);
      const isAdmin = user.role === 'admin';
      if(!isOwner && !isAdmin){
        statusEl.style.color = 'var(--rejected)';
        statusEl.textContent = `Only the original submitter (${song.submitted_by_username || 'unknown'}) or an admin can edit this entry.`;
        document.getElementById('submitForm').style.opacity = '0.5';
        document.getElementById('submitForm').style.pointerEvents = 'none';
        return false;
      }
      return true;
    };

    // Wait until the auth store has definitively finished loading (not a
    // fixed timeout guess) before deciding whether to lock the form —
    // a fixed-delay check could miss a slow session lookup and wrongly
    // disable editing for the actual owner.
    async function waitForAuthReady(){
      for(let i = 0; i < 50; i++){
        if(window.Alpine && Alpine.store('auth') && !Alpine.store('auth').loading) return;
        await new Promise(r => setTimeout(r, 100));
      }
    }
    await waitForAuthReady();
    checkOwnership();

    document.getElementById('formHeading').textContent = 'Edit song analysis';
    document.getElementById('formSubheading').innerHTML = `Editing <b>${song.title}</b> — only the original submitter or an admin can save changes.`;
    document.getElementById('submitBtnLabel').textContent = 'Save changes';

    document.getElementById('title').value = song.title;
    document.getElementById('artist').value = song.artist;
    document.getElementById('bpm').value = song.bpm ?? '';
    document.getElementById('musicalKey').value = song.musical_key;
    document.getElementById('timeSignature').value = song.time_signature || '';
    document.getElementById('youtubeUrl').value = song.youtube_url || '';
    document.getElementById('notes').value = song.footnote || '';

    if(song.youtube_url){
      document.getElementById('youtubeUrl').dispatchEvent(new Event('input'));
    }

    clearStructureRows();
    const sections = song.sections && song.sections.length
      ? song.sections.map(s => ({ section: s.section_name, key: s.musical_key }))
      : (song.section_keys ? JSON.parse(song.section_keys) : []);
    if(sections.length){
      sections.forEach(s => addStructureRow(s.section, s.key));
    } else {
      addStructureRow();
    }
  } catch(err){
    statusEl.style.color = 'var(--rejected)';
    statusEl.textContent = 'Could not load this song for editing.';
  }
}

if(isEditMode){
  loadForEdit(editId);
} else {
  addStructureRow();
}

document.getElementById('submitForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  const sectionKeys = [...document.querySelectorAll('.structure-row')].map(row => ({
    section: row.querySelector('.seg-name').value,
    key: row.querySelector('.seg-bars').value
  })).filter(s => s.section && s.key);

  const payload = {
    title: document.getElementById('title').value,
    artist: document.getElementById('artist').value,
    bpm: document.getElementById('bpm').value,
    musical_key: document.getElementById('musicalKey').value,
    time_signature: document.getElementById('timeSignature').value || null,
    youtube_url: document.getElementById('youtubeUrl').value || null,
    section_keys: sectionKeys,
    footnote: document.getElementById('notes').value || null
  };

  const statusEl = document.getElementById('formStatus');
  const submitBtn = document.querySelector('#submitForm button[type="submit"]');
  const submitBtnLabel = document.getElementById('submitBtnLabel');

  if(!Alpine.store('auth').user){
    statusEl.style.color = 'var(--rejected)';
    statusEl.textContent = 'Please log in (top-right) before submitting an analysis — submissions are tied to your contributor reputation.';
    return;
  }

  const endpoint = isEditMode ? 'api/update_song.php' : 'api/submit.php';
  if(isEditMode) payload.id = Number(editId);

  // Loading state: disable the button and swap its label so there's
  // clear feedback while the request is in flight (no full page reload).
  const originalLabel = submitBtnLabel.textContent;
  submitBtn.disabled = true;
  submitBtn.style.opacity = '0.7';
  submitBtn.style.cursor = 'wait';
  submitBtnLabel.textContent = isEditMode ? 'Saving…' : 'Submitting…';
  statusEl.style.color = 'var(--text-dim)';
  statusEl.textContent = '';

  try {
    const res = await fetch(endpoint, {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if(!res.ok){
      statusEl.style.color = 'var(--rejected)';
      statusEl.textContent = data.error || 'Save failed.';
      submitBtn.disabled = false;
      submitBtn.style.opacity = '';
      submitBtn.style.cursor = '';
      submitBtnLabel.textContent = originalLabel;
      return;
    }
    statusEl.style.color = 'var(--verified)';
    if(isEditMode){
      statusEl.textContent = '✓ Changes saved. Redirecting…';
      submitBtnLabel.textContent = 'Saved ✓';
      setTimeout(() => { window.location.href = `song.php?id=${editId}`; }, 900);
    } else {
      statusEl.textContent = `✓ ${data.message} (+0.1 reputation awarded) — back to the library…`;
      submitBtnLabel.textContent = 'Submitted ✓';
      // Form stays disabled during this transition (no double-submits),
      // then we hand off to the home/browse page rather than resetting in place.
      setTimeout(() => { window.location.href = 'index.html'; }, 1400);
    }
  } catch(err){
    statusEl.style.color = 'var(--rejected)';
    statusEl.textContent = "Could not reach the server. Is XAMPP's Apache + MySQL running?";
    submitBtn.disabled = false;
    submitBtn.style.opacity = '';
    submitBtn.style.cursor = '';
    submitBtnLabel.textContent = originalLabel;
  }
});
