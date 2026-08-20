# Examination Management Platform

A production-oriented Laravel examination system supporting **online** and **offline (LAN)** deployments for Prelim, Midterm, and Final exams.

## System Requirements

- PHP 8.2+ with extensions: `pdo`, `mbstring`, `openssl`, `gd`, `fileinfo`
- Composer 2.x
- Node.js 18+ and npm
- MySQL 8+ (recommended) or SQLite (development)
- Apache/Nginx (XAMPP supported)

## Quick Start (XAMPP / Local)

```bash
cd c:\xampp\htdocs\examination
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install && npm run build
```

Access: `http://localhost/examination/public`

### Default Seed Accounts

| Role | Login | Password |
|------|-------|----------|
| Superadmin | `superadmin@exam.local` | `password` |
| Admin | `admin@exam.local` | `password` |
| Instructor | `instructor@exam.local` | `password` |
| Student | `2026-0001` or `student@exam.local` | `password` |

## Environment Configuration

Key variables in `.env`:

```env
APP_MODE=online          # online | offline
APP_TIMEZONE=Asia/Manila
SYNC_ENABLED=false
SYNC_TARGET_URL=
INSTITUTION_NAME="Your University"
DB_CONNECTION=mysql
DB_DATABASE=examination_db
```

## Architecture Overview

- **Backend:** Laravel 12, Livewire 3, Spatie Permission
- **Database:** Normalized MySQL schema with exam versioning and attempt snapshots
- **Grading:** Server-side `App\Services\Grading\GradingEngine`
- **Offline:** Local XAMPP server + IndexedDB client persistence (Phase 10+)
- **Sync:** Idempotent UUID-based sync queue (Phase 11+)

## User Roles

- **Superadmin** — full system control
- **Admin** — configurable academic/user management
- **Instructor** — exams, question banks, grading, monitoring
- **Student** — take exams, view released results

## Project Status

### Completed (Phase 2–6 foundation)
- Laravel scaffold + Breeze auth
- Full database schema (40+ tables)
- RBAC with Spatie Permission
- Academic structure seed data
- 20 sample questions (9 types)
- Prelim/Midterm/Final exam seeds
- Grading engine with unit tests
- Role-based dashboards
- Student ID / email login

### Upcoming Phases
- Exam wizard UI
- Student examination interface + timer
- Offline IndexedDB + Service Worker
- Sync API + dashboard
- Reports (PDF/Excel/CSV)
- Backup/restore
- Full UI modules

## Documentation

- [OFFLINE-SETUP.md](OFFLINE-SETUP.md)
- [ONLINE-DEPLOYMENT.md](ONLINE-DEPLOYMENT.md)
- [DATABASE-DOCUMENTATION.md](DATABASE-DOCUMENTATION.md)
- [EXAMINATION-GUIDE.md](EXAMINATION-GUIDE.md)

## Running Tests

```bash
php artisan test
php artisan test --filter=GradingEngineTest
```

## Development Server

```bash
php artisan serve --host=0.0.0.0 --port=8000
npm run dev
```

## License

MIT
