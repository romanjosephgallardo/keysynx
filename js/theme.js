/* ============================================================
   KeySynx — js/theme.js
   ============================================================ */

function kxApplyTheme(theme){
  if(theme === 'light'){
    document.documentElement.setAttribute('data-theme', 'light');
  } else {
    document.documentElement.removeAttribute('data-theme');
  }
  try { localStorage.setItem('kx-theme', theme); } catch(e){}
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.kx-theme-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const isLight = document.documentElement.getAttribute('data-theme') === 'light';
      kxApplyTheme(isLight ? 'dark' : 'light');
    });
  });
});
