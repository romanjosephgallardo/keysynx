/* ============================================
   KeySynx — Shared auth store (Alpine.js)
   Provides Alpine.store('auth') with: user, loading,
   login(), register(), logout(). Session is server-side
   (PHP session cookie) — this just mirrors that state
   for the UI. Include this BEFORE alpine's cdn script
   runs init, i.e. via alpine:init listener below.
   ============================================ */

document.addEventListener('alpine:init', () => {
  Alpine.store('auth', {
    user: null,
    loading: true,
    error: '',

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
        if(!res.ok){ this.error = data.error || 'Login failed.'; return false; }
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
        this.user = data.user;
        return true;
      } catch(e){ this.error = 'Could not reach the server. Is XAMPP running?'; return false; }
    },

    async logout(){
      try { await fetch('api/auth.php?action=logout'); } catch(e){}
      this.user = null;
    }
  });
});
