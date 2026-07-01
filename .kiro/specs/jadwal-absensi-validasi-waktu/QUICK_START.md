# Quick Start Guide: Jadwal Absensi dengan Validasi Waktu

## 🚀 Deployment Cepat (5 Langkah)

### 1️⃣ Run Database Migration
```bash
cd Siabsensi
php artisan migrate
```

**Expected Output:**
```
Migration table created successfully.
Migrating: 2026_07_01_100000_create_attendance_schedules_table
Migrated:  2026_07_01_100000_create_attendance_schedules_table
Migrating: 2026_07_01_100001_add_schedule_fields_to_attendance_table
Migrated:  2026_07_01_100001_add_schedule_fields_to_attendance_table
Migrating: 2026_07_01_100002_create_system_config_table
Migrated:  2026_07_01_100002_create_system_config_table
```

---

### 2️⃣ Restart Python Backend
```bash
# Stop Python Backend yang running (Ctrl+C)
cd python_backend
python api_server.py
```

**Expected Output:**
```
 * Running on http://127.0.0.1:5000
 * Restarting with stat
Timezone configured: Asia/Jakarta
Database connected successfully
```

---

### 3️⃣ Buat Jadwal Pertama

1. Login ke admin panel
2. Klik menu **"Jadwal Absensi"** di sidebar
3. Toggle ON untuk hari yang ingin diaktifkan (misal: Senin)
4. Isi waktu:
   - **Check-in Mulai:** 05:00
   - **Batas Check-in:** 06:50
   - **Check-out Mulai:** 16:00
   - **Check-out Akhir:** 18:00
5. Klik **"Simpan Semua Jadwal"**

✅ **Success:** "Berhasil memperbarui 1 jadwal"

---

### 4️⃣ Test Absensi

**Scenario A: Hari Tanpa Jadwal**
- Scan QR code di hari Rabu (jika Rabu belum ada jadwal)
- **Expected:** ❌ "Tidak ada jadwal absensi untuk hari ini"

**Scenario B: Hari Dengan Jadwal**
- Scan QR code di hari Senin jam 06:30
- **Expected:** ✅ Check-in berhasil (tepat waktu)

**Scenario C: Check-in Telat**
- Scan QR code di hari Senin jam 07:10
- **Expected:** ✅ Check-in berhasil dengan badge **"TELAT 20 menit"**

---

### 5️⃣ Verifikasi Database

```sql
-- Check jadwal yang aktif
SELECT * FROM attendance_schedules WHERE is_active = 1;

-- Check grace period
SELECT * FROM system_config WHERE config_key = 'grace_period_minutes';

-- Check attendance dengan late status
SELECT mahasiswa_id, check_in_time, is_late, late_duration 
FROM attendance 
WHERE date = CURDATE() 
ORDER BY check_in_time DESC;
```

---

## 📋 Default Configuration

| Setting | Default Value | Adjustable |
|---------|--------------|------------|
| Grace Period | 40 menit | ✅ Via UI Admin |
| Timezone | Asia/Jakarta | ⚠️ Config file only |
| Schedule Cache TTL | 5 menit | ⚠️ Code only |
| Schedule per Day | 1 | ❌ By design |

---

## 🎯 Fitur yang Langsung Tersedia

### ✅ Admin Features
- [x] CRUD jadwal mingguan
- [x] Konfigurasi grace period
- [x] Override status telat mahasiswa
- [x] Laporan keterlambatan (dengan export CSV)
- [x] Riwayat absensi dengan filter

### ✅ Student Features
- [x] Badge telat di dashboard (jika telat)
- [x] Riwayat absensi dengan durasi telat
- [x] Total keterlambatan summary

### ✅ System Features
- [x] Validasi check-in dengan grace period
- [x] Validasi check-out
- [x] Auto-calculate late duration
- [x] Kegiatan independence (kegiatan bypass schedule)
- [x] Timezone consistency (Asia/Jakarta)
- [x] Schedule cache invalidation

---

## 🔧 Konfigurasi Tambahan (Optional)

### Ubah Grace Period
1. Admin → Jadwal Absensi
2. Form "Pengaturan Grace Period"
3. Input nilai (0-120 menit)
4. Klik "Simpan Grace Period"

### Buat Jadwal untuk Semua Hari
```
Senin - Jumat: Aktif (05:00-06:50, 16:00-18:00)
Sabtu - Minggu: Nonaktif
```

### Export Laporan Keterlambatan
1. Admin → Laporan Keterlambatan
2. Pilih filter (kompi, tanggal)
3. Klik "Tampilkan Laporan"
4. Klik "Export CSV"

---

## 🐛 Troubleshooting Cepat

### ❌ Error: "No schedule configured for today"
**Solusi:**
1. Pastikan jadwal sudah dibuat untuk hari ini
2. Toggle is_active = ON
3. Restart Python Backend

### ❌ Schedule tidak update setelah di-save
**Solusi:**
1. Check file `python_backend/data/.schedule_cache_version` exists
2. Check permission writable (755)
3. Wait 5 minutes atau restart Python Backend

### ❌ Late duration salah
**Solusi:**
1. Check timezone Laravel: `php artisan tinker` → `config('app.timezone')`
2. Check timezone Python: `python_backend/app/timezone_utils.py`
3. Pastikan sama: `Asia/Jakarta`

### ❌ Validasi form tidak muncul
**Solusi:**
1. Clear browser cache
2. Hard refresh (Ctrl+Shift+R)
3. Check browser console for JavaScript errors

---

## 📞 Quick Reference

### URLs
```
Admin Schedule:     /admin/schedule
Late Report:        /admin/late-report
Attendance History: /admin/history
Python Backend:     http://localhost:5000
```

### Database Tables
```
attendance_schedules      # Jadwal mingguan
system_config            # Grace period config
attendance               # Attendance records + late fields
```

### Log Files
```
python_backend/logs/attendance.log       # Python validation logs
Siabsensi/storage/logs/laravel.log      # Laravel app logs
```

### Commands
```bash
# Laravel
php artisan migrate                      # Run migrations
php artisan tinker                       # Laravel console
php artisan route:list | grep schedule   # List schedule routes

# Python Backend
python python_backend/api_server.py      # Start server
tail -f python_backend/logs/attendance.log  # Watch logs

# Database
mysql -u root -p siabsensi               # MySQL console
```

---

## 📚 Next Steps

1. **Read Full Documentation:**
   - `IMPLEMENTATION_SUMMARY.md` - Technical details
   - `TESTING_GUIDE.md` - Complete test cases
   - `requirements.md` - Full requirements spec

2. **Production Deployment:**
   - Backup database sebelum deploy
   - Test di staging environment dulu
   - Monitor logs saat first rollout

3. **Train Users:**
   - Admin: Cara manage jadwal dan override
   - Mahasiswa: Penjelasan grace period dan late badge

---

## ✅ Ready to Use!

Jika semua langkah 1-5 berhasil, sistem sudah siap digunakan! 🎉

**Test Checklist:**
- [ ] Migration berhasil
- [ ] Python Backend running
- [ ] Jadwal sudah dibuat untuk hari aktif
- [ ] Grace period configured (default 40 menit)
- [ ] Test scan QR → validasi bekerja
- [ ] Dashboard mahasiswa show late badge
- [ ] Admin bisa override late status
- [ ] Laporan keterlambatan bisa di-generate

**Support:** Jika ada masalah, lihat `TESTING_GUIDE.md` atau check log files.
