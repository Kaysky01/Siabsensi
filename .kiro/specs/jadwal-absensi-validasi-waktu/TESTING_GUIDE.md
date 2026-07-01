# Testing Guide: Jadwal Absensi dengan Validasi Waktu

## Status Implementasi
✅ **SELESAI** - Semua 15 requirements telah diimplementasi

## Persiapan Testing

### 1. Restart Python Backend
**PENTING:** Code validasi jadwal sudah diupdate, tapi Python Backend perlu di-restart untuk menerapkan perubahan.

```bash
# Stop Python Backend yang sedang running (Ctrl+C di terminal)
# Lalu jalankan ulang:
cd python_backend
python api_server.py
```

### 2. Jalankan Migration Database
```bash
cd Siabsensi
php artisan migrate
```

### 3. Akses Admin Panel
Login sebagai admin dan akses menu **Jadwal Absensi**

---

## Test Cases

### Test 1: Grace Period Configuration (Requirement 11)
**Tujuan:** Memastikan grace period dapat dikonfigurasi

**Langkah:**
1. Login sebagai admin
2. Buka halaman "Jadwal Absensi"
3. Di bagian "Pengaturan Grace Period", ubah nilai (default: 40 menit)
4. Coba nilai 30 menit → Klik "Simpan Grace Period"
5. Refresh halaman → Pastikan nilai tersimpan
6. Coba nilai invalid (misalnya 150 atau -5) → Harus ditolak (range: 0-120)

**Expected Result:**
- ✅ Grace period berhasil disimpan
- ✅ Validasi range 0-120 menit bekerja
- ✅ Nilai tersimpan di database `system_config`

---

### Test 2: Schedule CRUD dengan Validasi Client-Side (Requirement 8 + Task 9)
**Tujuan:** Memastikan validasi form jadwal bekerja

**Langkah:**
1. Di halaman "Jadwal Absensi", toggle ON untuk hari Senin
2. Kosongkan salah satu field waktu → Klik "Simpan Semua Jadwal"
3. **Expected:** Alert "Error validasi: Senin: Semua waktu harus diisi"
4. Isi semua field waktu dengan urutan salah:
   - Check-in Mulai: 05:00
   - Batas Check-in: 06:50
   - Check-out Mulai: 06:00 (SALAH: lebih awal dari 06:50)
   - Check-out Akhir: 18:00
5. Klik "Simpan Semua Jadwal"
6. **Expected:** Alert menunjukkan urutan waktu tidak valid

**Expected Result:**
- ✅ Client-side validation menangkap error sebelum submit
- ✅ Error message jelas dan menampilkan nilai waktu aktual

---

### Test 3: Schedule Creation dengan Urutan Benar (Requirement 1)
**Tujuan:** Membuat jadwal yang valid

**Langkah:**
1. Toggle ON untuk hari Senin
2. Isi waktu dengan urutan benar:
   - Check-in Mulai: **05:00**
   - Batas Check-in: **06:50**
   - Check-out Mulai: **16:00**
   - Check-out Akhir: **18:00**
3. Klik "Simpan Semua Jadwal"
4. **Expected:** Success message "Berhasil memperbarui X jadwal"
5. Verifikasi di database:
```sql
SELECT * FROM attendance_schedules WHERE day_of_week = 1;
```

**Expected Result:**
- ✅ Jadwal berhasil disimpan
- ✅ Field `is_active` = 1
- ✅ File `.schedule_cache_version` ter-update di `python_backend/data/`

---

### Test 4: Schedule Absence Handling (Requirement 13)
**Tujuan:** Memastikan absensi ditolak jika tidak ada jadwal

**Langkah:**
1. Pastikan hari ini (misal Rabu) **TIDAK** memiliki jadwal aktif
2. Scan QR code mahasiswa (via CCTV monitoring atau manual QR scan)
3. **Expected Response:**
```json
{
  "success": false,
  "message": "Tidak ada jadwal absensi untuk hari ini"
}
```
4. Periksa log Python Backend:
```bash
tail -f python_backend/logs/attendance.log
```
5. **Expected Log:** `No schedule configured for today`

**Expected Result:**
- ✅ Absensi ditolak dengan message jelas
- ✅ Tidak ada record attendance tercreate di database
- ✅ Event ter-log di `attendance.log`

---

### Test 5: Check-In Time Validation (Requirement 2)
**Tujuan:** Memastikan validasi waktu check-in bekerja

**Setup:** Buat jadwal untuk hari ini:
- Check-in Mulai: 05:00
- Batas Check-in: 06:50
- Grace Period: 40 menit
- Check-out Mulai: 16:00
- Check-out Akhir: 18:00

**Test Cases:**

#### 5a. Check-in Terlalu Awal (Sebelum 05:00)
- Ubah system time ke 04:30 (atau test manual jika sistem production)
- Scan QR code
- **Expected:** "Absen masuk belum dibuka"

#### 5b. Check-in Tepat Waktu (05:00 - 06:50)
- Scan QR code di jam 06:00
- **Expected:** 
  - ✅ Check-in berhasil
  - ✅ `is_late` = FALSE
  - ✅ `late_duration` = 0

#### 5c. Check-in Telat dalam Grace Period (06:51 - 07:30)
- Scan QR code di jam 07:10
- **Expected:**
  - ✅ Check-in berhasil dengan status TELAT
  - ✅ `is_late` = TRUE
  - ✅ `late_duration` = 20 (menit dari 06:50 ke 07:10)

#### 5d. Check-in Melewati Grace Period (> 07:30)
- Scan QR code di jam 07:35
- **Expected:** "Absen masuk sudah ditutup"

**Verifikasi Database:**
```sql
SELECT mahasiswa_id, check_in_time, is_late, late_duration 
FROM attendance 
WHERE date = CURDATE() 
ORDER BY check_in_time DESC;
```

---

### Test 6: Check-Out Time Validation (Requirement 4)
**Tujuan:** Memastikan validasi waktu check-out bekerja

**Setup:** Mahasiswa sudah check-in sebelumnya

#### 6a. Check-out Terlalu Awal (Sebelum 16:00)
- Scan QR code di jam 15:30
- **Expected:** "Belum waktunya check-out"

#### 6b. Check-out dalam Waktu (16:00 - 18:00)
- Scan QR code di jam 17:00
- **Expected:**
  - ✅ Check-out berhasil
  - ✅ Record attendance ter-update dengan `check_out_time`

#### 6c. Check-out Terlalu Malam (> 18:00)
- Scan QR code di jam 18:30
- **Expected:** "Waktu check-out sudah ditutup"

---

### Test 7: Late Status Override (Requirement 5)
**Tujuan:** Admin dapat menghapus status telat

**Langkah:**
1. Pastikan ada record attendance dengan `is_late = TRUE`
2. Login sebagai admin → Buka "Riwayat Absensi"
3. Cari record yang TELAT (badge merah "TELAT X menit")
4. Klik tombol "Hapus Status Telat"
5. Modal muncul → Isi alasan: "Mahasiswa izin terlambat karena sakit"
6. Submit form
7. **Expected:** 
   - ✅ Badge TELAT hilang
   - ✅ Muncul badge override (icon atau "Override by Admin")
   - ✅ Tooltip menampilkan alasan dan admin username

**Verifikasi Database:**
```sql
SELECT is_late, late_duration, late_overridden, overridden_by, override_reason 
FROM attendance 
WHERE id = [RECORD_ID];
```
**Expected Result:**
- `is_late` = 1 (tetap TRUE, data original preserved)
- `late_overridden` = 1
- `overridden_by` = username admin
- `override_reason` = alasan yang diinput

---

### Test 8: Student Dashboard Late Display (Requirement 6)
**Tujuan:** Mahasiswa melihat status telat di dashboard mereka

**Langkah:**
1. Login sebagai mahasiswa yang punya record telat
2. Buka dashboard mahasiswa
3. **Expected:**
   - Badge merah "TELAT X menit" tampil untuk record yang `is_late = TRUE` dan `late_overridden = FALSE`
   - Badge TIDAK tampil jika `late_overridden = TRUE`
4. Buka riwayat absensi mahasiswa
5. **Expected:** 
   - Kolom "Durasi Telat" menampilkan nilai `late_duration` dalam menit
   - Summary menampilkan total keterlambatan

---

### Test 9: Late Attendance Report (Requirement 12)
**Tujuan:** Admin dapat generate laporan keterlambatan

**Langkah:**
1. Login sebagai admin
2. Klik menu "Laporan Keterlambatan" di sidebar
3. Filter berdasarkan:
   - Kompi: [Pilih kompi]
   - Tanggal: [Range 1 bulan]
4. Klik "Tampilkan Laporan"
5. **Expected:**
   - Tabel menampilkan mahasiswa dengan total keterlambatan
   - Kolom: Nama, NIM, Kompi, Total Telat, Rata-rata Durasi Telat
   - Summary box: Total late occurrences, Total overrides, Average late duration
6. Klik "Export CSV"
7. **Expected:** File CSV ter-download dengan data yang sama

---

### Test 10: Kegiatan Independence (Requirement 15)
**Tujuan:** Absensi kegiatan bypass validasi jadwal

**Langkah:**
1. Scan QR code dengan `kegiatan_id` yang ter-set
2. **Expected:**
   - ✅ Absensi diterima tanpa memandang jadwal
   - ✅ Tidak ada validasi waktu
   - ✅ `is_late` tidak di-set untuk kegiatan
3. Verifikasi di riwayat absensi admin
4. **Expected:**
   - Badge **"Kegiatan"** (warna ungu) tampil di kolom "Tipe"
   - Kolom "Durasi Telat" menampilkan "N/A"

---

### Test 11: Timezone Consistency (Requirement 14)
**Tujuan:** Semua waktu konsisten dengan timezone aplikasi

**Langkah:**
1. Cek config timezone di Laravel:
```bash
php artisan tinker
>>> config('app.timezone')
# Expected: "Asia/Jakarta"
```

2. Cek Python Backend timezone:
```python
from app.timezone_utils import get_current_time
print(get_current_time())
# Expected: Datetime dengan timezone Asia/Jakarta
```

3. Di halaman jadwal admin, pastikan ada indikator timezone:
   - **Expected:** "Timezone: Asia/Jakarta"

4. Periksa database attendance records:
```sql
SELECT check_in_time, check_out_time FROM attendance LIMIT 5;
```
   - **Expected:** Timestamps dalam UTC (database storage)

5. Periksa display di UI (admin/mahasiswa)
   - **Expected:** Timestamps di-convert ke Asia/Jakarta untuk display

---

### Test 12: Schedule Cache Invalidation (Requirement 10.3)
**Tujuan:** Python Backend reload schedule saat diupdate

**Langkah:**
1. Buat jadwal untuk hari ini (misal Rabu)
2. Scan QR code → Pastikan validasi schedule bekerja
3. Update jadwal (ubah waktu check-in) → Klik "Simpan Semua Jadwal"
4. Cek file version:
```bash
cat python_backend/data/.schedule_cache_version
# Expected: Timestamp baru (format: 2026-07-01 12:34:56)
```
5. Scan QR code lagi (dalam 5 detik)
6. **Expected:** Python Backend otomatis menggunakan schedule baru tanpa restart

**Verifikasi Log:**
```bash
tail -f python_backend/logs/attendance.log
# Expected: Log menunjukkan cache invalidated dan schedule di-reload
```

---

## Troubleshooting

### Python Backend tidak membaca schedule baru
**Solusi:** Restart Python Backend
```bash
cd python_backend
python api_server.py
```

### Error "No schedule configured for today" padahal sudah dibuat
**Possible Causes:**
1. Schedule untuk hari yang salah (cek `day_of_week`)
2. Schedule `is_active = FALSE`
3. Python Backend belum restart setelah code update
4. Database connection error di Python Backend

**Debug:**
```bash
# Cek log Python Backend
tail -f python_backend/logs/attendance.log

# Cek database
mysql -u root -p
USE siabsensi;
SELECT * FROM attendance_schedules WHERE is_active = 1;
```

### Late duration salah
**Possible Causes:**
1. Timezone tidak konsisten antara Laravel dan Python
2. Grace period salah dikonfigurasi

**Debug:**
```python
# Di Python Backend
from app.timezone_utils import get_current_time
from app.time_validator import TimeValidator

tv = TimeValidator()
print(tv.grace_period_minutes)  # Expected: nilai dari database
print(get_current_time())  # Expected: waktu saat ini di Asia/Jakarta
```

---

## Checklist Testing Lengkap

- [ ] Test 1: Grace Period Configuration
- [ ] Test 2: Schedule CRUD dengan Validasi
- [ ] Test 3: Schedule Creation
- [ ] Test 4: Schedule Absence Handling
- [ ] Test 5a: Check-in Terlalu Awal
- [ ] Test 5b: Check-in Tepat Waktu
- [ ] Test 5c: Check-in Telat dalam Grace Period
- [ ] Test 5d: Check-in Melewati Grace Period
- [ ] Test 6a: Check-out Terlalu Awal
- [ ] Test 6b: Check-out dalam Waktu
- [ ] Test 6c: Check-out Terlalu Malam
- [ ] Test 7: Late Status Override
- [ ] Test 8: Student Dashboard Late Display
- [ ] Test 9: Late Attendance Report
- [ ] Test 10: Kegiatan Independence
- [ ] Test 11: Timezone Consistency
- [ ] Test 12: Schedule Cache Invalidation

---

## Production Deployment Checklist

- [ ] Migration database sudah dijalankan
- [ ] System timezone set ke Asia/Jakarta
- [ ] Grace period dikonfigurasi (default: 40 menit)
- [ ] Jadwal mingguan sudah dibuat untuk hari aktif
- [ ] Python Backend running dan terhubung ke database
- [ ] File permission untuk `python_backend/data/.schedule_cache_version` (writable)
- [ ] Log rotation configured untuk `attendance.log`
- [ ] Backup database sebelum deployment
- [ ] User admin dan mahasiswa sudah di-train untuk fitur baru

---

## Contact & Support
Jika ada bug atau issue, catat:
1. Error message lengkap
2. Langkah reproduksi
3. Log dari Python Backend (`attendance.log`)
4. Screenshot UI (jika ada)
