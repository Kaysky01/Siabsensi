# 🚀 Production Setup Guide

## 📋 Server Information

- **Laravel Production URL**: `https://pkkmb.polinela.ac.id`
- **Python Local**: `http://127.0.0.1:5000`
- **Database**: MySQL (shared between Laravel & Python)

---

## ✅ Pre-Requirements

### 1. **Laravel Production Server**
Pastikan endpoint berikut sudah di-deploy dan accessible:

```
✅ GET  https://pkkmb.polinela.ac.id/api/sync/status
✅ GET  https://pkkmb.polinela.ac.id/api/sync/mahasiswa
✅ GET  https://pkkmb.polinela.ac.id/api/sync/schedules
✅ GET  https://pkkmb.polinela.ac.id/api/sync/kegiatan
✅ GET  https://pkkmb.polinela.ac.id/api/sync/system-config
✅ POST https://pkkmb.polinela.ac.id/api/sync (untuk sync attendance)
```

### 2. **MySQL Database**
Pastikan database `siabsensi` bisa diakses dari Python:

```sql
-- Check connection
mysql -h 127.0.0.1 -u root -p siabsensi
```

### 3. **Python Dependencies**
Install semua dependencies:

```bash
cd python_backend
pip install -r requirements.txt
```

---

## 🔧 Configuration

### 1. **Python Database Config**

File: `python_backend/app/config_db.py`

```python
MYSQL_CONFIG = {
    'host': '127.0.0.1',        # Sesuaikan jika remote
    'port': 3306,
    'user': 'root',             # Sesuaikan user
    'password': '',             # Isi password jika ada
    'database': 'siabsensi',
    'charset': 'utf8mb4',
    'collation': 'utf8mb4_unicode_ci'
}
```

### 2. **Laravel URL Config**

File: `python_backend/static/js/sync_master.js`

```javascript
let LARAVEL_URL = 'https://pkkmb.polinela.ac.id';  // ✅ Production URL
```

File: `python_backend/static/js/monitor.js`

```javascript
const API_URL = 'https://pkkmb.polinela.ac.id/api';  // ✅ Production URL
```

---

## 🚀 First Time Setup

### Step 1: Deploy Laravel Code

Pastikan code terbaru sudah di-deploy ke production:

```bash
# Di server production Laravel
cd /path/to/laravel
git pull origin main
composer install --no-dev
php artisan config:cache
php artisan route:cache
```

### Step 2: Test Laravel Endpoints

Test manual dari browser atau curl:

```bash
# Test status endpoint
curl https://pkkmb.polinela.ac.id/api/sync/status

# Expected response:
{
  "success": true,
  "message": "Laravel API ready for sync",
  "stats": {
    "mahasiswa_count": 150,
    ...
  }
}
```

### Step 3: Start Python Backend

```bash
cd python_backend
python api_server.py
```

Output yang diharapkan:
```
============================================================
  SIABSEN Python Backend for Laravel
  Starting Flask API Server...
============================================================

Server akan berjalan di:
  - http://0.0.0.0:5000

Tekan Ctrl+C untuk menghentikan server
============================================================

✓ Database connected successfully
 * Running on http://0.0.0.0:5000
```

### Step 4: Sync Master Data

1. Buka browser: `http://127.0.0.1:5000/monitor`
2. Klik tombol **"Sync Master Data"** (tombol hijau)
3. URL akan otomatis: `https://pkkmb.polinela.ac.id`
4. Klik **"Mulai Sinkronisasi"**

**Expected Result**:
```
✅ Sinkronisasi Berhasil!

📋 Data Mahasiswa
   150 ditambahkan, 0 diupdate
   👤 User Accounts: 150 dibuat, 0 diupdate

📅 Jadwal PKKMB
   7 ditambahkan, 0 diupdate

🎯 Data Kegiatan
   10 ditambahkan, 0 diupdate
```

### Step 5: Verify Database

Check data sudah masuk ke database Python:

```sql
-- Check mahasiswa
SELECT COUNT(*) as total_mahasiswa FROM mahasiswa;

-- Check users
SELECT COUNT(*) as total_users FROM users WHERE role = 'mahasiswa';

-- Check schedules
SELECT * FROM pkkmb_schedules WHERE is_active = 1 ORDER BY tanggal;

-- Check system config
SELECT * FROM system_config WHERE config_key = 'attendance_grace_period_minutes';
```

### Step 6: Test Absensi

1. Siapkan QR Code mahasiswa
2. Buka monitor: `http://127.0.0.1:5000/monitor`
3. Aktifkan kamera
4. Scan QR Code
5. Seharusnya muncul: **"[Nama Mahasiswa] berhasil absen masuk"**

---

## 🔄 Daily Operations

### Morning Routine (Sebelum Absensi)

1. **Start Python Backend**:
   ```bash
   cd python_backend
   python api_server.py
   ```

2. **Sync Master Data** (jika ada perubahan):
   - Buka monitor
   - Klik "Sync Master Data"
   - Tunggu selesai

3. **Check Schedule**:
   - Akses: `http://127.0.0.1:5000/api/python/debug/schedule`
   - Pastikan ada jadwal untuk hari ini

### During Absensi

Monitor berjalan otomatis untuk:
- ✅ Scan QR Code mahasiswa
- ✅ Validasi jadwal dan waktu
- ✅ Simpan ke LocalStorage (temporary)

### Evening Routine (Setelah Absensi)

1. **Sync ke Server**:
   - Klik tombol **"Sync ke Server"**
   - Data akan dikirim ke Laravel production
   - Excel backup otomatis dibuat

2. **Verify**:
   - Login ke Laravel: `https://pkkmb.polinela.ac.id`
   - Cek dashboard attendance
   - Pastikan semua data masuk

---

## 🐛 Troubleshooting

### Error: "Gagal terhubung ke Laravel"

**Penyebab**: Tidak bisa akses `https://pkkmb.polinela.ac.id`

**Solusi**:
1. Test koneksi manual:
   ```bash
   curl -I https://pkkmb.polinela.ac.id/api/sync/status
   ```
2. Check firewall/network
3. Pastikan SSL certificate valid

### Error: "Tidak Ada Jadwal"

**Penyebab**: Jadwal belum di-sync atau tanggal tidak sesuai

**Solusi**:
1. Check debug endpoint:
   ```
   http://127.0.0.1:5000/api/python/debug/schedule
   ```
2. Pastikan ada `today_schedule` dengan `is_active = 1`
3. Jika kosong, sync ulang master data

### Error: "Database connection failed"

**Penyebab**: MySQL tidak running atau config salah

**Solusi**:
1. Check MySQL status:
   ```bash
   # Windows
   net start MySQL80
   
   # Linux
   sudo systemctl status mysql
   ```
2. Test connection:
   ```bash
   mysql -h 127.0.0.1 -u root -p siabsensi
   ```
3. Fix config di `config_db.py`

### Error: "403 Forbidden" saat sync ke Laravel

**Penyebab**: CORS atau route tidak accessible

**Solusi**:
1. Check Laravel logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```
2. Pastikan routes accessible without auth:
   ```php
   Route::prefix('api/sync')->group(function () {
       // No middleware auth
   });
   ```

### Error: SSL Certificate

**Penyebab**: Self-signed certificate atau expired

**Solusi Temporary** (Development only):
```python
# Di config_db.py atau saat init
sync_service = LaravelSyncService(
    'https://pkkmb.polinela.ac.id',
    verify_ssl=False  # ⚠️ Only for development
)
```

---

## 📊 Monitoring

### Check Python Logs

```bash
# Real-time log
tail -f python_backend/logs/attendance.log

# Search for errors
grep ERROR python_backend/logs/attendance.log
```

### Check Laravel Logs

```bash
# Di server production
tail -f storage/logs/laravel.log
```

### Check Database Stats

```sql
-- Total attendance today
SELECT COUNT(*) FROM attendance WHERE date = CURDATE();

-- Mahasiswa belum absen
SELECT m.id, m.name 
FROM mahasiswa m 
LEFT JOIN attendance a ON m.id = a.mahasiswa_id AND a.date = CURDATE()
WHERE a.id IS NULL AND m.is_active = 1;

-- Jadwal hari ini
SELECT * FROM pkkmb_schedules 
WHERE tanggal = CURDATE() AND is_active = 1;
```

---

## 🔐 Security Notes

### Production Recommendations

1. **Add Authentication** ke Laravel sync endpoints
2. **Use API Token** untuk Python → Laravel communication
3. **Enable SSL Verification** (verify_ssl=True)
4. **Restrict CORS** hanya untuk IP Python server
5. **Use Environment Variables** untuk sensitive config

### Example dengan Token (Future Enhancement)

```python
# python_backend/app/laravel_sync.py
self.session.headers.update({
    'Authorization': 'Bearer YOUR_API_TOKEN',
    'Accept': 'application/json'
})
```

```php
// Laravel routes/web.php
Route::middleware(['auth:sanctum'])->prefix('api/sync')->group(function () {
    // Protected routes
});
```

---

## 📞 Support

### Quick Check Commands

```bash
# Test Laravel endpoint
curl https://pkkmb.polinela.ac.id/api/sync/status

# Test Python endpoint
curl http://127.0.0.1:5000/api/python/status

# Check database
mysql -e "SELECT COUNT(*) FROM siabsensi.mahasiswa;"

# Check Python process
ps aux | grep python | grep api_server
```

### Contact

Jika ada masalah yang tidak bisa diselesaikan:
1. Cek log Python dan Laravel
2. Test endpoint manual dengan curl
3. Verify database connection
4. Check network/firewall

---

**Last Updated**: 2026-07-24  
**Version**: 1.0  
**Production URL**: https://pkkmb.polinela.ac.id
