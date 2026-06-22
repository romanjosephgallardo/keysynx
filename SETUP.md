# KeySynx — XAMPP Setup Guide

## 1. Place the project in htdocs
Copy the entire `keysynx/` folder into your XAMPP `htdocs` directory:
- Windows: `C:\xampp\htdocs\keysynx\`
- macOS: `/Applications/XAMPP/htdocs/keysynx/`
- Linux: `/opt/lampp/htdocs/keysynx/`

## 2. Start Apache + MySQL
Open the XAMPP Control Panel and click **Start** next to both **Apache** and **MySQL**.

## 3. Create the database
1. Go to `http://localhost/phpmyadmin`
2. Click **Import** (top tab)
3. Choose `schema.sql` from this project
4. Click **Go**

This creates the `keysynx` database, all tables, the triggers (auto-verify + reputation rewards), and seeds it with the 12-track Eternal Sunshine dataset plus 10 demo users.

## 3.5. Import the full 5-artist catalog (411 more tracks)
The base `schema.sql` only seeds the Eternal Sunshine album. To load the rest —
Ariana Grande's other albums, plus Britney Spears, Harry Styles, Taylor Swift,
and Bruno Mars — run the seeder once:

Visit: `http://localhost/keysynx/database/seed_runner.php`

You'll see a JSON summary like:
```json
{
    "albums_inserted": 23,
    "songs_inserted": 411,
    "songs_skipped_already_existed": 0,
    "sections_inserted": 28,
    "total_songs_in_db_now": 423
}
```
It's safe to reload that page — already-imported songs are skipped, not duplicated.

**Note on the 423 total:** the goal was 500+, but 423 is the honest count of everything
actually present and usable across the 5 uploaded PDFs (a small number of entries with
no BPM *and* no key given in the source — e.g. "Break The Ice" intro/outro split, "Ever
Been" — were left out rather than guessed at). Paste more album PDFs any time and I can
add them to `database/seed_data.php` the same way.

## 4. Open the app
Visit: `http://localhost/keysynx/index.html`

You should see the song database load with real data from MySQL (not the static fallback — if you see a "could not reach server" type behavior, double check Apache/MySQL are running and the database imported without errors).

## 5. Test accounts
The seeded users (`admin`, `djkurt`, `romanmix`, `community`) have **placeholder password hashes that won't actually log in** — they exist only so the seed songs/comments have something to reference.

**To actually test login-gated features (voting, submitting, commenting):**
1. Go to the app, click **Log in** (top right) → switch to **Register**
2. Create your own account
3. To test admin features: open phpMyAdmin → SQL tab → run:
   ```sql
   UPDATE users SET role = 'admin' WHERE username = 'your_username';
   ```
4. Log out and log back in — you'll now see the Users & Roles tab in Admin work, and be able to approve/reject submissions.

## 6. Folder reference
```
keysynx/
├── index.html, song.html, wheel.html, submit.html, admin.html
├── css/style.css
├── js/            ← frontend logic (falls back to local sample data if API unreachable)
├── database/
│   ├── seed_data.php     5-artist song data, hand-transcribed from the PDFs
│   └── seed_runner.php   imports seed_data.php into MySQL (run once, see step 3.5)
└── api/           ← PHP backend
    ├── db.php           connection config (edit if your MySQL user/password differs)
    ├── camelot.php      Camelot wheel mapping + transition scoring (PHP port of js/camelot.js)
    ├── auth.php         register / login / logout / session check
    ├── songs.php        search, filter, Camelot-compatibility filter, single-song detail
    ├── vote.php         upvote/downvote
    ├── submit.php       new song submissions (+10 reputation)
    ├── moderate.php     admin approve/reject/delete + role changes
    ├── comments.php     contributor feedback
    ├── users.php        admin user list (for role management)
    └── stats.php        homepage hero stat counts
```

## Troubleshooting
- **"Database connection failed"** → check `api/db.php` matches your MySQL credentials (XAMPP default is user `root`, empty password — only change this if you've customized your XAMPP MySQL setup).
- **Pages show old static sample data instead of your DB data** → that's the JS fallback kicking in, meaning a fetch to `api/*.php` failed. Open browser DevTools → Network tab to see the actual error (404 = wrong path/folder name, 500 = check `db.php` credentials and that `schema.sql` imported successfully).
- **Login modal does nothing** → check the browser console; Alpine.js or the Tailwind CDN script may have failed to load (requires internet access, since both are CDN-hosted).
