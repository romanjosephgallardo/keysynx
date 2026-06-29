/* ============================================================
   KeySynx — js/theme.js
   Handles the light/dark toggle button click. The INITIAL theme
   read (to avoid a flash of the wrong theme on page load) happens
   in a tiny inline <script> at the very top of <head> on every
   page — this file only needs to handle the toggle interaction.
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
