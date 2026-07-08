# Agent Instructions for Siabsensi

This repository is a hybrid system:
- **Frontend & Logic**: Laravel 12 (PHP) (in `Siabsensi/`)
- **AI Detection**: Python Flask with YOLO (in `python_backend/`)
- Both connect to the same MySQL database.

## Important Commands

- **Start Laravel**: 
  ```bash
  cd Siabsensi
  php artisan serve
  ```
- **Start Python Backend**:
  ```bash
  cd python_backend
  python api_server.py
  ```
- **Run Tests**:
  ```bash
  cd Siabsensi
  php artisan test
  ```
  *(Tests use in-memory SQLite per `phpunit.xml`)*

- **Run Scheduled Tasks (Auto Alpha Marking)**:
  ```bash
  cd Siabsensi
  php artisan schedule:work
  ```
  *(Important: Auto alpha marking runs at 00:05. Students without check-in/out get Alpha).*

## Directory Structure & Context

- `Siabsensi/` contains the entire Laravel 12 application.
  - `app/Http/Controllers/`: Controllers separated by Role (`Admin/`, `Mahasiswa/`)
  - `resources/views/`: Blade templates
  - `public/static/js/`: Client-side logic for real-time polling.
- `python_backend/` contains the Flask YOLO API.
  - `api_server.py`: Main entrypoint for detection.
- `models/`: YOLO `.pt` weights.

## Quirks & Rules
- **No Websockets**: Real-time monitoring uses delta polling (AJAX) every 2 seconds via `/monitor`. Do not introduce websockets, Echo, or Reverb.
- **Strict Check-in/out**: Students must check-in and check-out to get credit for the day. If only checked-in, attendance is voided (except for Izin/Sakit).
- **YOLO Settings**: The python backend reads `yolo_settings.json` and `rtsp_settings.json` from the repository root, and DB connection details from `Siabsensi/.env`.
- **Database**: The app relies heavily on bulk updates and role-based access. Do not bypass role checks (Admin, Timdis, Garda, Mahasiswa) when adding API endpoints.
