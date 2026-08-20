# Offline Setup Guide

## Overview

Offline mode runs the **same Laravel codebase** on a local server (XAMPP). Students connect via LAN without internet.

```
Server (Teacher laptop / lab PC)
  └── XAMPP: Apache + MySQL + PHP
  └── http://192.168.x.x/examination/public

Students (same Wi‑Fi / LAN)
  └── Browser → local server IP
```

## Step 1 — Install on Local Server

1. Copy the project to `C:\xampp\htdocs\examination`
2. Install dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm install && npm run build
   ```
3. Configure `.env`:
   ```env
   APP_MODE=offline
   APP_URL=http://192.168.1.100/examination/public
   SYNC_ENABLED=true
   SYNC_TARGET_URL=https://your-central-server.edu
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=examination_offline
   DB_USERNAME=root
   DB_PASSWORD=
   ```
4. Create database and migrate:
   ```bash
   php artisan migrate:fresh --seed
   php artisan config:cache
   php artisan route:cache
   ```

## Step 2 — Configure Apache for LAN Access

Edit `C:\xampp\apache\conf\httpd.conf`:

```apache
Listen 0.0.0.0:80
```

Allow `.htaccess` overrides for the project directory.

Restart Apache from XAMPP Control Panel.

## Step 3 — Windows Firewall

Allow inbound TCP port 80 for private networks so student devices can reach the server.

## Step 4 — Find Server IP

```cmd
ipconfig
```

Example student URL: `http://192.168.1.100/examination/public`

## Step 5 — Pre-load Examination Data

Before disconnecting from the internet:

1. Import students and exam data on the local server
2. Publish/activate examinations
3. Verify all students can log in on the LAN

## Offline Answer Persistence

During exams, answers are:

1. Saved to the **local MySQL server** on each autosave
2. Cached in **IndexedDB** for resilience during brief disconnects/refreshes

Correct answers are **never** stored in browser-readable offline storage.

## Synchronization

When internet returns:

1. Open **Sync Dashboard** (Superadmin)
2. Review pending attempts
3. Run sync — duplicate records are prevented via UUID constraints

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Students cannot connect | Check firewall, IP address, Apache `Listen` |
| Session lost on refresh | Verify `SESSION_DRIVER=database` and migrations ran |
| Timer resets | Server-side `expires_at` validates duration — report as bug |
| Sync duplicates | Each attempt has unique UUID; re-sync is idempotent |
