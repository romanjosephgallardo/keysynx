/* ============================================
   submit.html logic
   Structure rows are fully functional (add/remove).
   Final submit currently logs to console + shows a
   confirmation message instead of POSTing — wire to
   api/submit_song.php (POST) once backend exists.
   ============================================ */

document.getElementById('addRow').addEventListener('click', () => {
  const builder = document.getElementById('structureBuilder');
  const row = document.createElement('div');
  row.className = 'structure-row';
  row.innerHTML = `
    <input type="text" placeholder="Section (e.g. Chorus)" class="seg-name">
    <input type="text" placeholder="Key (e.g. Bb Minor)" class="seg-bars">
    <button type="button" class="icon-btn remove-row">×</button>
  `;
  builder.appendChild(row);
});

document.getElementById('structureBuilder').addEventListener('click', (e) => {
  if(e.target.classList.contains('remove-row')){
    const rows = document.querySelectorAll('.structure-row');
    if(rows.length > 1){ e.target.closest('.structure-row').remove(); }
  }
});

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
    tags: document.getElementById('tags').value.split(',').map(t => t.trim()).filter(Boolean),
    footnote: document.getElementById('notes').value || null
  };

  const statusEl = document.getElementById('formStatus');

  if(!Alpine.store('auth').user){
    statusEl.style.color = 'var(--rejected)';
    statusEl.textContent = 'Please log in (top-right) before submitting an analysis — submissions are tied to your contributor reputation.';
    return;
  }

  try {
    const res = await fetch('api/submit.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if(!res.ok){
      statusEl.style.color = 'var(--rejected)';
      statusEl.textContent = data.error || 'Submission failed.';
      return;
    }
    statusEl.style.color = 'var(--verified)';
    statusEl.textContent = `✓ ${data.message} (+10 reputation awarded)`;
    document.getElementById('submitForm').reset();
  } catch(err){
    statusEl.style.color = 'var(--rejected)';
    statusEl.textContent = 'Could not reach the server. Is XAMPP\'s Apache + MySQL running?';
  }
});
