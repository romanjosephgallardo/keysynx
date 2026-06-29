/* ============================================
   KeySynx — Shared auth store (Alpine.js)
   Provides Alpine.store('auth') with: user, loading,
   login(), register(), logout(). Session is server-side
   (PHP session cookie) — this just mirrors that state
   for the UI. Include this BEFORE alpine's cdn script
   runs init, i.e. via alpine:init listener below.
   ============================================ */

// Shared avatar-circle generator — used by the topbar profile icon,
// comment authors, and the profile page. Deterministic color per username.
function avatarHTML(username, size = 28){
  const initials = (username || '??').slice(0, 2).toUpperCase();
  const hue = [...(username || '')].reduce((h, c) => h + c.charCodeAt(0), 0) % 360;
  return `<div style="width:${size}px; height:${size}px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:${size * 0.4}px; font-weight:700; color:#fff; background:hsl(${hue},55%,42%);">${initials}</div>`;
}
window.avatarHTML = avatarHTML;

document.addEventListener('alpine:init', () => {
  Alpine.store('auth', {
    user: null,
    loading: true,
    error: '',
    pendingVerification: null, // holds the username while a "type your code" screen should show

    async init(){
      try {
        const res = await fetch('api/auth.php?action=me');
        const data = await res.json();
        this.user = data.user;
      } catch(e){
        this.user = null; // backend not reachable (e.g. opened without XAMPP running)
      }
      this.loading = false;
    },

    async login(username, password){
      this.error = '';
      try {
        const res = await fetch('api/auth.php?action=login', {
          method: 'POST', headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ username, password })
        });
        const data = await res.json();
        if(!res.ok){
          if(data.needs_verification){
            this.pendingVerification = data.username || username;
            this.error = '';
            return false;
          }
          this.error = data.error || 'Login failed.';
          return false;
        }
        this.user = data.user;
        return true;
      } catch(e){ this.error = 'Could not reach the server. Is XAMPP running?'; return false; }
    },

    async register(username, email, password){
      this.error = '';
      try {
        const res = await fetch('api/auth.php?action=register', {
          method: 'POST', headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ username, email, password })
        });
        const data = await res.json();
        if(!res.ok){ this.error = data.error || 'Registration failed.'; return false; }
        // Account created but NOT logged in yet — must verify the emailed
        // code first. The UI should switch to the verify panel now.
        this.pendingVerification = data.username || username;
        if(!data.email_sent){ this.error = data.message; } // surfaces the DEBUG: ... detail temporarily
        return false;
      } catch(e){ this.error = 'Could not reach the server. Is XAMPP running?'; return false; }
    },

    async verify(username, code){
      this.error = '';
      try {
        const res = await fetch('api/auth.php?action=verify', {
          method: 'POST', headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ username, code })
        });
        const data = await res.json();
        if(!res.ok){ this.error = data.error || 'Verification failed.'; return false; }
        this.user = data.user;
        this.pendingVerification = null;
        return true;
      } catch(e){ this.error = 'Could not reach the server. Is XAMPP running?'; return false; }
    },

    async resendCode(username){
      this.error = '';
      try {
        const res = await fetch('api/auth.php?action=resend', {
          method: 'POST', headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ username })
        });
        const data = await res.json();
        if(!res.ok){ this.error = data.error || 'Could not resend code.'; return false; }
        if(!data.email_sent){ this.error = data.message; return false; } // surfaces DEBUG: ... detail temporarily
        return true;
      } catch(e){ this.error = 'Could not reach the server. Is XAMPP running?'; return false; }
    },

    async logout(){
      try { await fetch('api/auth.php?action=logout'); } catch(e){}
      this.user = null;
      this.pendingVerification = null;
    }
  });
});
