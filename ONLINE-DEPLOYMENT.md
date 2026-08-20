# Online Deployment Guide

## Supported Environments

- VPS (Ubuntu/CentOS)
- Institutional Linux server
- Cloud (AWS, DigitalOcean, Azure)
- Shared hosting with PHP 8.2+ and MySQL

## Production Checklist

```env
APP_ENV=production
APP_DEBUG=false
APP_MODE=online
APP_URL=https://exams.yourinstitution.edu
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

SYNC_ENABLED=true
```

## Deployment Steps

```bash
git clone <repo> /var/www/examination
cd /var/www/examination
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # first deploy only
npm ci && npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

## Web Server (Nginx example)

```nginx
server {
    listen 80;
    server_name exams.yourinstitution.edu;
    root /var/www/examination/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Queue Worker

Run for background sync, notifications, and reports:

```bash
php artisan queue:work --tries=3
```

Use Supervisor in production to keep the worker running.

## SSL

Use Let's Encrypt / Certbot for HTTPS. Set `APP_URL` to the HTTPS URL.

## Receiving Offline Sync

The central server exposes idempotent sync endpoints (Phase 11):

- `POST /api/sync/attempts`
- Validates UUID, rejects duplicates
- Returns sync confirmation

## Backups

Superadmin can create backups from the admin panel. Also schedule:

```bash
php artisan schedule:run
```

Add a nightly mysqldump via cron for disaster recovery.
