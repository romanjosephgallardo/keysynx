# KeySynx — Installation & Setup Guide

## Live Demo (Temporary)

A temporary public deployment of KeySynx is available through Cloudflare Tunnel:

**https://dance-dairy-montreal-plane.trycloudflare.com**

> **Note:** This deployment is temporary and may become unavailable whenever the local development machine or Cloudflare Tunnel is offline.

---

# Local Development Setup (XAMPP)

## 1. Clone or Copy the Project

Place the entire `keysynx` folder inside your XAMPP `htdocs` directory.

| Operating System | Project Location                      |
| ---------------- | ------------------------------------- |
| Windows          | `C:\xampp\htdocs\keysynx\`            |
| macOS            | `/Applications/XAMPP/htdocs/keysynx/` |
| Linux            | `/opt/lampp/htdocs/keysynx/`          |

---

## 2. Start Apache and MySQL

Open the **XAMPP Control Panel** and start both:

* Apache
* MySQL

---

## 3. Import the Database

1. Open **phpMyAdmin**

   ```
   http://localhost/phpmyadmin
   ```

2. Create a database named:

   ```
   keysynx
   ```

3. Select the newly created database.

4. Open the **Import** tab.

5. Import:

   ```
   schema.sql
   ```

This automatically creates:

* Database tables
* Relationships
* Triggers
* Reputation system
* Initial seed data

---

## 4. Import the Complete Music Catalog

The base database contains only the **Eternal Sunshine** album.

To import the complete catalog (423 songs across five artists), run:

```
http://localhost/keysynx/database/seed_runner.php
```

A successful import returns a JSON summary similar to:

```json
{
    "albums_inserted": 23,
    "songs_inserted": 411,
    "songs_skipped_already_existed": 0,
    "sections_inserted": 28,
    "total_songs_in_db_now": 423
}
```

The importer is idempotent, meaning it can safely be executed multiple times without creating duplicate records.

---

## 5. Launch the Application

Open:

```
http://localhost/keysynx/
```

or

```
http://localhost/keysynx/index.html
```

The homepage should display the complete music library loaded from MySQL.

---

## 6. Test User Accounts

The seeded accounts (`admin`, `djkurt`, `romanmix`, and `community`) are provided only as reference records for seeded content.

These accounts **cannot be used to log in** because they contain placeholder password hashes.

To test authentication:

1. Open KeySynx.
2. Click **Log In**.
3. Switch to **Register**.
4. Create a new account.

### Grant Administrator Access

Open phpMyAdmin and execute:

```sql
UPDATE users
SET role = 'admin'
WHERE username = 'your_username';
```

Log out and sign back in to access administrator features.

---

# Project Structure

```
keysynx/
│
├── api/                    PHP backend
├── css/                    Stylesheets
├── database/
│   ├── schema.sql
│   ├── seed_data.php
│   └── seed_runner.php
├── js/                     Frontend scripts
├── src/                    Images and assets
│
├── index.html
├── song.html
├── submit.html
├── wheel.html
├── about.php
├── admin.php
└── profile.php
```

---

# Cloudflare Tunnel (Optional)

For public access during development, expose the local project using Cloudflare Tunnel.

Run:

```bash
cloudflared tunnel --url http://localhost/keysynx
```

Cloudflare will generate a temporary public URL similar to:

```
https://xxxxxxxx.trycloudflare.com
```

Anyone with the generated URL can access the application while:

* Apache is running
* MySQL is running
* The Cloudflare Tunnel process remains active

---

# Troubleshooting

### Database connection failed

Verify the MySQL credentials in:

```
api/db.php
```

The default XAMPP configuration is:

* Username: `root`
* Password: *(empty)*

---

### Unable to load data

Ensure:

* Apache is running.
* MySQL is running.
* `schema.sql` has been imported successfully.
* `seed_runner.php` has been executed.

---

### Login or registration is not working

Check:

* Browser Developer Tools → Console
* Browser Developer Tools → Network

Most authentication issues are caused by:

* PHP errors
* Database connection failures
* Incorrect API paths

---

### Cloudflare Tunnel is unavailable

The public deployment is temporary.

If the Cloudflare URL is offline:

1. Start Apache.
2. Start MySQL.
3. Launch the tunnel again:

```bash
cloudflared tunnel --url http://localhost/keysynx
```

A new temporary URL will be generated.
