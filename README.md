# SIABSEN — Sistem Absensi Mahasiswa

Sistem absensi otomatis menggunakan **YOLO object detection** untuk mendeteksi **QR code paper** dan **pyzbar** untuk decode QR code, terintegrasi dengan **Webcam Lokal**, **Upload Video MP4**, **Form Pengajuan Izin/Sakit**, **Authentication System**, **Role-Based Access Control (RBAC)**, dan **MySQL database**.

## 📋 Fitur Utama

### 1. 📹 Real-time Webcam Monitoring
- Deteksi QR code dari webcam lokal (built-in atau USB)
- Auto-detect available webcams
- Multi-camera support (multiple USB webcams)
- Live preview dengan bounding box
- Auto check-in/check-out
- Simplified setup (no RTSP configuration needed)

### 2. 🎬 Upload & Deteksi Video MP4
- Upload video rekaman untuk deteksi offline
- Preview video dengan bounding box real-time
- Batch processing untuk multiple QR codes
- Validasi duplikasi

### 3. 📝 Form Pengajuan Izin/Sakit & Kehadiran Manual
- Pengajuan izin/sakit dengan upload bukti
- Pengajuan kehadiran manual
- Verifikasi oleh Tim Disiplin (Timdis)
- Riwayat pengajuan dengan status real-time

### 4. 📊 Dashboard & Reporting
- Statistik kehadiran real-time
- Export data ke CSV
- Grafik kehadiran per kelompok
- Material Icons untuk UI yang clean

### 5. 🔐 Authentication & Authorization
- Login/Logout dengan session management
- Password hashing menggunakan bcrypt
- Role-Based Access Control (Admin, Timdis, Mahasiswa)
- Session token dengan expiry 24 jam

### 6. 🎓 Sertifikat Kehadiran
- Generate sertifikat kehadiran otomatis
- Preview sertifikat sebelum download
- History sertifikat yang sudah diunduh
- Kriteria kehadiran minimum 80%

## 🏗️ Arsitektur Project

Project ini menggunakan arsitektur hybrid:

- **Frontend**: Laravel (PHP) - Web application, authentication, dashboard
- **Backend**: Python Flask - YOLO detection, QR code processing
- **Database**: MySQL - Data storage
- **Communication**: REST API antara Laravel dan Python backend

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

# Windows
venv\Scripts\activate

# Linux/Mac
source venv/bin/activate

# Install Python dependencies
pip install -r requirements.txt

# 5. Install library sistem (Ubuntu/Debian)
sudo apt install -y libzbar0 libzbar-dev ffmpeg libgl1-mesa-glx mysql-server
```

### Setup MySQL

```bash
# Buat database
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
# Kembali ke project root
cd ..

# Run migrations
php artisan migrate

# Seed database (admin user)
php artisan db:seed
```

### Jalankan Aplikasi

```bash
# Terminal 1: Start Laravel
php artisan serve

# Terminal 2: Start Python backend
cd python_backend
python api_server.py
```

Akses aplikasi:
- **Laravel**: http://127.0.0.1:8000
- **Python Backend**: http://127.0.0.1:5000
- **Login**: http://127.0.0.1:8000/login
- **Monitor**: http://127.0.0.1:8000/monitor

### First Login

1. Buka http://127.0.0.1:8000/login
2. Login dengan:
   - **Username**: `admin`
   - **Password**: `admin123`
3. ⚠️ **PENTING**: Ganti password default setelah login pertama!

## 📁 Struktur Project

```
YOLO-Siabsensi/
├── app/                          # Laravel application
│   ├── Http/Controllers/         # Controllers
│   │   ├── Admin/              # Admin controllers
│   │   ├── Mahasiswa/           # Mahasiswa controllers
│   │   ├── Auth/               # Authentication
│   │   └── SertifikatController.php
│   ├── Models/                  # Eloquent models
│   │   ├── Mahasiswa.php
│   │   ├── Attendance.php
│   │   ├── User.php
│   │   └── SertifikatHistory.php
│   └── ...
│
├── python_backend/              # Python Flask backend
│   ├── api_server.py            # Flask API server
│   ├── app/
│   │   ├── attendance_engine.py # Core engine (YOLO + QR)
│   │   ├── database_manager.py  # MySQL database manager
│   │   └── config_db.py         # Database configuration
│   ├── requirements.txt          # Python dependencies
│   └── venv/                    # Python virtual environment
│
├── resources/views/              # Blade templates
│   ├── admin/                   # Admin views
│   │   └── dashboard.blade.php
│   ├── mahasiswa/               # Mahasiswa views
│   │   └── mahasiswa.blade.php
│   ├── monitor.blade.php        # Live monitoring
│   └── auth/                    # Authentication views
│
├── public/                      # Public assets
│   ├── static/
│   │   ├── css/                 # Stylesheets
│   │   ├── js/                  # JavaScript files
│   │   │   ├── monitor.js       # Monitor logic
│   │   │   └── script.js        # Main script
│   │   ├── img/                 # Images
│   │   │   └── sertifikat.png   # Certificate template
│   │   ├── sounds/              # Sound notifications
│   │   │   └── beep.mp3
│   │   └── fonts/               # Custom fonts
│   └── uploads/                 # User uploads
│       ├── bukti_izin/          # Bukti pengajuan
│       └── videos/              # Uploaded videos
│
├── models/                      # YOLO models
│   └── qr_paper.pt              # Custom trained model
│
├── yolo_settings.json           # YOLO configuration
├── rtsp_settings.json           # RTSP configuration
├── .env                         # Environment variables
├── composer.json                # PHP dependencies
└── requirements.txt             # Python dependencies
```

## 🎓 Training Model YOLO

Model default belum dilatih untuk mendeteksi QR code paper. Anda perlu melatih model custom terlebih dahulu.

### Setup Training

```bash
# Install YOLO training dependencies
pip install ultralytics

# Latih model dengan dataset QR code paper
yolo detect train data=path/to/dataset.yaml model=yolov8n.pt epochs=100 imgsz=640

# Export model
cp runs/detect/train/weights/best.pt ../models/qr_paper.pt
```

### Konfigurasi Model

Edit `yolo_settings.json`:
```json
{
    "model_path": "../models/qr_paper.pt",
    "confidence": 0.45,
    "qr_cooldown": 30
}
```

## 🔐 User Management

### Default Users

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | admin123 |
| Timdis | timdis | timdis123 |

### Roles & Permissions

| Role | Dashboard | Verifikasi | Manage Users | Settings | Portal Mahasiswa |
|------|-----------|------------|--------------|----------|------------------|
| **Admin** | ✅ Full | ✅ | ✅ | ✅ | ✅ |
| **Timdis** | ✅ Read | ✅ | ❌ | ❌ | ✅ |
| **Mahasiswa** | ❌ | ❌ | ❌ | ❌ | ✅ |

## 🌐 API Endpoints

### Python Backend (http://127.0.0.1:5000)

#### Detection
- `POST /api/python/detect` - Detect QR code from image
- `POST /api/python/attendance` - Record attendance
- `GET /api/python/status` - Check backend status
- `POST /api/python/reload-settings` - Reload YOLO/RTSP settings

### Laravel Frontend (http://127.0.0.1:8000)

#### Authentication
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Get current user

#### Mahasiswa
- `GET /api/mahasiswa` - List semua mahasiswa
- `POST /api/mahasiswa` - Tambah mahasiswa baru
- `GET /api/mahasiswa/<id>/qr` - Get QR code mahasiswa
- `GET /api/mahasiswa/<id>/statistics` - Get mahasiswa statistics

#### Attendance
- `GET /api/attendance/today` - Absensi hari ini
- `GET /api/attendance/stats` - Statistik absensi
- `GET /api/attendance/history` - Riwayat absensi

#### Izin/Sakit
- `POST /api/izin/submit` - Submit pengajuan izin/sakit
- `GET /api/izin/list` - List semua pengajuan
- `POST /api/izin/verify` - Approve/Reject pengajuan

#### Sertifikat
- `POST /api/mahasiswa/<id>/sertifikat/preview` - Preview sertifikat
- `POST /api/mahasiswa/<id>/sertifikat/generate` - Generate sertifikat
- `GET /api/mahasiswa/<id>/sertifikat/history` - Riwayat sertifikat

## 🐛 Troubleshooting

### Python Backend Not Starting
```bash
# Check if port 5000 is in use
netstat -ano | findstr :5000

# Kill process if needed
taskkill /PID <PID> /F

# Check Python dependencies
pip install -r python_backend/requirements.txt
```

### Database Connection Error
```bash
# Check MySQL status
sudo systemctl status mysql

# Restart MySQL
sudo systemctl restart mysql

# Check credentials in .env file
```

### Webcam Not Detected
```bash
# Test webcam with OpenCV
python -c "import cv2; print(cv2.VideoCapture(0).isOpened())"

# If False, check:
# 1. Webcam is connected
# 2. Webcam drivers are installed
# 3. No other application is using the webcam
```

### YOLO Model Not Loading
```bash
# Check model path in yolo_settings.json
cat yolo_settings.json

# Verify model file exists
ls -la models/

# Test YOLO model
python -c "from ultralytics import YOLO; model = YOLO('models/qr_paper.pt'); print('Model loaded successfully')"
```

## 📝 License

Copyright © 2026 SIABSEN Team. All rights reserved.

## 👥 Team

- **Developer**: SIABSEN Development Team
- **Version**: 3.0.0
- **Last Updated**: June 2026
- **Major Changes**: 
  - v3.0.0: Laravel + Python hybrid architecture
  - v2.5.0: RTSP → Webcam refactoring
  - v2.4.0: Project restructuring

---

Untuk dokumentasi lengkap, lihat `docs/README.md`
