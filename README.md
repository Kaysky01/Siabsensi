# SIABSEN — Sistem Absensi Mahasiswa

Sistem absensi otomatis menggunakan **YOLO object detection** untuk mendeteksi **QR code paper** dan **pyzbar** untuk decode QR code, terintegrasi dengan **Webcam Lokal**, **Upload Video MP4**, **Form Pengajuan Izin/Sakit**, **Authentication System**, **Role-Based Access Control (RBAC)**, **Kegiatan Dinamis**, dan **MySQL database**.

## 📋 Fitur Utama

### 1. 📹 Real-time Webcam Monitoring
- Deteksi QR code dari webcam lokal (built-in atau USB)
- Multi-camera support
- Live preview dengan bounding box
- Auto check-in/check-out
- Delta polling real-time (setiap 2 detik) tanpa WebSocket/Reverb

### 2. 🎬 Upload & Deteksi Video MP4
- Upload video rekaman untuk deteksi offline
- Preview video dengan bounding box real-time
- Batch processing untuk multiple QR codes
- Validasi duplikasi

### 3. 📝 Form Pengajuan Izin/Sakit & Kehadiran Manual
- Pengajuan izin/sakit dengan upload bukti
- Pengajuan kehadiran manual
- Verifikasi oleh Tim Disiplin (Timdis) — Admin tidak bisa verifikasi
- Riwayat pengajuan dengan status real-time

### 4. 📊 Dashboard & Reporting
- Statistik kehadiran real-time
- Export data ke CSV & Excel
- Grafik kehadiran per kompi
- Filter by kompi, prodi, jurusan
- **Laporan Kelulusan** (≥80% = lulus) per prodi/jurusan

### 5. 🔐 Authentication & Authorization
- Login/Logout dengan session management
- Password hashing menggunakan bcrypt
- Role-Based Access Control: **Admin**, **Timdis**, **Garda**, **Mahasiswa**
- Session token dengan expiry 24 jam

### 6. 🎓 Sertifikat Kehadiran
- Generate sertifikat kehadiran otomatis
- Preview sertifikat sebelum download
- History sertifikat yang sudah diunduh
- **Tanggal download tercantum di gambar sertifikat**
- Kriteria kehadiran minimum 80%

### 7. 🏢 Kompi & Prodi
- Setiap mahasiswa memiliki **kompi** (kelompok) dan **prodi** (program studi)
- Satu kompi bisa berisi mahasiswa dari berbagai prodi
- Admin bisa **acak otomatis** pembagian kompi dari semua prodi
- Pengaturan kompi massal (bulk update)

### 8. 📅 Kegiatan Dinamis
- Admin membuat kegiatan dengan nama, tanggal, jam, status wajib
- Mahasiswa absen pada kegiatan yang **sedang berlangsung**
- Monitoring kehadiran per kegiatan dengan filter (kompi, prodi, status)
- Terpisah dari absen harian (check-in/out)

### 9. 👮 Garda (Keamanan)
- Garda login melihat **"Mahasiswa Saya"** → daftar mahasiswa per kompi yang ditugaskan
- Admin menugaskan kompi ke setiap Garda
- Akses terbatas: hanya mahasiswa di kompi-nya, verifikasi, dan logout

## 🏗️ Arsitektur Project

Project ini menggunakan arsitektur hybrid:

- **Frontend & Logic**: Laravel 12 (PHP) — Web application, authentication, dashboard, CRUD, verifikasi
- **AI Detection**: Python Flask — YOLO detection, QR code processing (port 5000)
- **Database**: MySQL — Data storage
- **Communication**: REST API antara Laravel dan Python backend
- **Real-time**: Delta polling (AJAX) — tanpa WebSocket/Reverb/SSE

### Struktur Direktori

```
YOLO-Siabsensi/
├── app/                          # Laravel application
│   ├── Http/Controllers/         # Controllers
│   │   ├── Admin/              # Admin controllers
│   │   ├── Mahasiswa/           # Mahasiswa controllers
│   │   ├── Auth/               # Authentication
│   │   ├── KegiatanController.php # Kegiatan management
│   │   └── SertifikatController.php
│   ├── Models/                  # Eloquent models
│   └── Console/Commands/         # Artisan commands
│       └── MarkAlphaCommand.php  # Auto alpha marking
│
├── python_backend/              # Python Flask backend (YOLO only)
│   ├── api_server.py            # Flask API server
│   ├── app/
│   │   ├── attendance_engine.py # Core engine (YOLO + QR)
│   │   ├── database_manager.py  # Hanya method yang dipakai YOLO
│   │   └── config_db.py         # Database configuration
│   └── requirements.txt
│
├── resources/views/              # Blade templates
│   ├── admin/dashboard.blade.php # Dashboard (Admin, Timdis, Garda)
│   ├── mahasiswa/mahasiswa.blade.php # Portal mahasiswa
│   └── monitor.blade.php        # Live monitoring
│
├── public/static/js/             # JavaScript
│   ├── script.js                # Admin dashboard logic
│   ├── monitor.js               # Monitor + delta polling
│   └── mahasiswa.js             # Mahasiswa portal
│
├── models/                      # YOLO models (.pt files)
├── database/migrations/         # Database migrations
└── database/seeders/            # Database seeders
```

## 🚀 Quick Start

### Prerequisites

- **PHP 8.1+**
- **Python 3.11+**
- **MySQL 8.0+**
- **Webcam** (built-in atau USB)
- **Composer**
- **Node.js & NPM**
- **OS**: Windows 10+, Ubuntu 22.04+, atau macOS

### Instalasi

```bash
# 1. Clone repository
git clone <repository-url>
cd YOLO-Siabsensi

# 2. Install Laravel dependencies
composer install

# 3. Setup environment
cp .env.example .env
php artisan key:generate

# 4. Setup Python backend
cd python_backend
python -m venv venv
venv\Scripts\activate  # Windows
# source venv/bin/activate  # Linux/Mac
pip install -r requirements.txt

# 5. Install library sistem (Ubuntu/Debian)
sudo apt install -y libzbar0 libzbar-dev ffmpeg libgl1-mesa-glx mysql-server
```

### Setup MySQL

```bash
mysql -u root -p
```

```sql
CREATE DATABASE siabsensi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'siabsen'@'localhost' IDENTIFIED BY 'password_anda';
GRANT ALL PRIVILEGES ON siabsensi.* TO 'siabsen'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Konfigurasi Environment

Edit `.env` file:
```env
DB_DATABASE=siabsensi
DB_USERNAME=siabsen
DB_PASSWORD=password_anda
```

### Run Migrations

```bash
php artisan migrate
php artisan db:seed
```

### Jalankan Aplikasi

```bash
# Terminal 1: Start Laravel
php artisan serve

# Terminal 2: Start Python backend
cd python_backend
python api_server.py

# Terminal 3: Scheduler (auto alpha marking)
php artisan schedule:work
```

### Akses Aplikasi

- **Laravel**: http://127.0.0.1:8000
- **Python Backend**: http://127.0.0.1:5000
- **Login**: http://127.0.0.1:8000/login
- **Monitor**: http://127.0.0.1:8000/monitor

### First Login

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | admin123 |
| Timdis | timdis | timdis123 |
| Garda | garda | garda123 |

⚠️ **PENTING**: Ganti password default setelah login pertama!

## 🔐 Roles & Permissions

| Fitur | Admin | Timdis | Garda | Mahasiswa |
|-------|-------|--------|-------|-----------|
| Dashboard Absensi | ✅ | ✅ | ✅ | ❌ |
| Data Mahasiswa | ✅ | ✅ | ❌ | ❌ |
| Mahasiswa Saya (per kompi) | ❌ | ❌ | ✅ | ❌ |
| Pengaturan Kompi | ✅ | ❌ | ❌ | ❌ |
| Riwayat Absensi | ✅ | ✅ | ❌ | ❌ |
| Upload Video MP4 | ✅ | ❌ | ❌ | ❌ |
| Laporan Kelulusan | ✅ | ✅ | ❌ | ❌ |
| Verifikasi Izin/Sakit | ❌ | ✅ | ✅ | ❌ |
| Verifikasi Kehadiran | ❌ | ✅ | ✅ | ❌ |
| Kelola Kegiatan (CRUD) | ✅ | ❌ | ❌ | ❌ |
| Monitoring Kegiatan | ✅ | ✅ | ❌ | ❌ |
| Kelola Kamera | ✅ | ❌ | ❌ | ❌ |
| User Management | ✅ | ❌ | ❌ | ❌ |
| Pengaturan Sistem | ✅ | ❌ | ❌ | ❌ |
| Portal Mahasiswa | ❌ | ❌ | ❌ | ✅ |
| QR Scan / Deteksi | ❌ | ❌ | ❌ | ❌ (via Python) |

> Catatan: **Garda** hanya bisa melihat mahasiswa di kompi yang ditugaskan oleh Admin. **Timdis & Garda** bisa verifikasi pengajuan.

## ⚙️ Fitur Unggulan

### Delta Polling Real-time
Monitoring absensi di `/monitor` menggunakan delta polling — fetch data baru setiap 2 detik hanya untuk perubahan (delta), bukan seluruh data. Mendukung 3.000+ mahasiswa tanpa WebSocket.

### Auto Alpha Marking
Setiap hari jam 00:05, sistem otomatis menandai mahasiswa yang tidak hadir sebagai **Alpha**. Tidak menandai di akhir pekan. Jalankan `php artisan schedule:work`.

### Kegiatan Dinamis
Admin membuat kegiatan (upacara, seminar, dll) dengan jadwal. Mahasiswa absen klik tombol saat kegiatan berlangsung. Monitoring real-time per kegiatan.

### Kompi & Prodi
Satu kompi bisa memiliki mahasiswa dari berbagai prodi/jurusan. Admin bisa mengacak pembagian kompi secara otomatis dari semua prodi.

### Sertifikat dengan Tanggal Download
Sertifikat kehadiran menampilkan tanggal download di pojok kanan bawah. History download tersimpan.

### Laporan Kelulusan
Filter mahasiswa per prodi/jurusan dan lihat siapa yang lulus (≥80% kehadiran) atau tidak lulus. Bisa export CSV.

## 🌐 API Endpoints

### Python Backend (http://127.0.0.1:5000)
- `GET /api/python/status` — Check backend status
- `POST /api/python/detect` — Detect QR code from image
- `POST /api/python/attendance` — Record check-in/out
- `POST /api/python/reload-settings` — Reload YOLO/RTSP settings
- `GET /api/python/stream/<camera_id>` — Camera stream

### Laravel API (http://127.0.0.1:8000)

#### Authentication
- `POST /auth/login` — Login
- `GET /logout` — Logout
- `GET /api/auth/me` — Get current user

#### Mahasiswa
- `GET /api/mahasiswa` — List mahasiswa (filter: `?kompi=`)
- `POST /api/mahasiswa` — Tambah mahasiswa
- `PUT /api/mahasiswa/{id}` — Update profil
- `DELETE /api/mahasiswa/{id}` — Hapus
- `POST /api/mahasiswa/bulk-update-kompi` — Bulk update kompi

#### Attendance
- `GET /api/attendance/today` — Absensi hari ini
- `GET /api/attendance/history` — Riwayat absensi
- `GET /api/monitor/attendance/stream` — Delta polling endpoint
- `GET /api/attendance/kelulusan` — Laporan kelulusan

#### Izin/Sakit & Kehadiran
- `POST /api/izin/submit` — Submit izin/sakit
- `GET /api/izin/list` — List pengajuan (Admin & Timdis)
- `POST /api/izin/verify` — Verifikasi (Timdis only)
- `POST /api/kehadiran/submit` — Submit kehadiran manual
- `GET /api/kehadiran/list` — List pengajuan (Admin & Timdis)
- `POST /api/kehadiran/verify` — Verifikasi (Timdis only)

#### Kegiatan
- `GET /api/kegiatan` — List kegiatan
- `POST /api/kegiatan` — Buat kegiatan (Admin)
- `PUT /api/kegiatan/{id}` — Edit kegiatan
- `DELETE /api/kegiatan/{id}` — Hapus kegiatan
- `GET /api/kegiatan/{id}/rekap` — Monitoring kehadiran
- `GET /api/kegiatan/aktif` — Kegiatan berlangsung
- `POST /api/kegiatan/absen` — Absen kegiatan

#### Sertifikat
- `POST /api/mahasiswa/{id}/sertifikat/preview` — Preview
- `POST /api/mahasiswa/{id}/sertifikat/generate` — Generate
- `GET /api/mahasiswa/{id}/sertifikat/history` — History

## 🎓 Training Model YOLO

```bash
pip install ultralytics
yolo detect train data=path/to/dataset.yaml model=yolov8n.pt epochs=100 imgsz=640
cp runs/detect/train/weights/best.pt ../models/qr_paper.pt
```

Edit `yolo_settings.json`:
```json
{
    "model_path": "../models/qr_paper.pt",
    "confidence": 0.45,
    "qr_cooldown": 30
}
```

## 🐛 Troubleshooting

### Python Backend Not Starting
```bash
netstat -ano | findstr :5000
taskkill /PID <PID> /F
pip install -r python_backend/requirements.txt
```

### Webcam Not Detected
```bash
python -c "import cv2; print(cv2.VideoCapture(0).isOpened())"
```

### YOLO Model Not Loading
```bash
python -c "from ultralytics import YOLO; model = YOLO('models/qr_paper.pt'); print('OK')"
```

## 📝 License

Copyright © 2026 SIABSEN Team. All rights reserved.

## 👥 Team

- **Developer**: SIABSEN Development Team
- **Version**: 3.0.0
- **Last Updated**: June 2026
