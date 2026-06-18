# AGENTS.md - SIABSEN YOLO Attendance System

## Architecture

**Hybrid Laravel + Python stack:**
- **Laravel 12** (PHP) — Primary web interface, user authentication, role-based dashboard, settings configuration, and database CRUD.
- **Python Flask** (port 5000) — Dedicated YOLO detection agent, image processing, and local WebRTC / USB camera frame analysis.
- **MySQL** — Primary database. SQLite `:memory:` is only for PHP unit tests.

**Port defaults:**
- Laravel: `http://127.0.0.1:8000`
- Python: `http://127.0.0.1:5000`

## Key Differences & Gotchas

1. **Kompi & Prodi:**
   - The field formerly named `kelompok` is now strictly `kompi` globally.
   - A Program Studi (`prodi`) field exists on the `mahasiswa` table and is fully managed.
   - "Pengaturan Kompi" page (admin only) allows bulk assignment of student companies.

2. **Real-time Live Monitoring (`/monitor`):**
   - Implemented using **Delta Polling** (AJAX fetches every 2 seconds to `/api/monitor/attendance/stream?last_update=...`).
   - Server-Sent Events (SSE) and Laravel Reverb are **NOT** used to prevent thread-blocking on the single-threaded PHP built-in server.
   - DB indexes are set on `(date, check_in)`, `(created_at)`, and `(mahasiswa_id, date)` to support heavy traffic for up to 3,000+ students.
   - Live monitor only holds a sliding window of max 50 scans to keep client-side rendering fast and low on memory.

3. **Verification Roles:**
   - **Tim Disiplin (Timdis)** is the only role allowed to verify `izin/sakit` and `kehadiran` submissions.
   - **Admin** has access to dashboard stats, settings, user management, and camera CRUD but cannot verify student absence submissions.

## Start Dev Servers

```bash
# Terminal 1 — Laravel
php artisan serve

# Terminal 2 — Python backend
cd python_backend
python api_server.py
```

## Running Tests

```bash
composer run test
```

## Lint / Format

```bash
vendor/bin/pint    # Auto-format codebase
```
