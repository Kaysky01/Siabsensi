# ⚡ Quick Start - SIABSEN Python Backend

## 🎯 Production URL
```
Laravel: https://pkkmb.polinela.ac.id
Python:  http://127.0.0.1:5000
```

---

## 🚀 Start Server

```bash
cd python_backend
python api_server.py
```

Tunggu sampai muncul:
```
✓ Database connected successfully
* Running on http://0.0.0.0:5000
```

---

## 🔄 First Time: Sync Data

1. Buka: `http://127.0.0.1:5000/monitor`
2. Klik tombol hijau **"Sync Master Data"**
3. URL otomatis: `https://pkkmb.polinela.ac.id`
4. Klik **"Mulai Sinkronisasi"**
5. Tunggu selesai (±30 detik)

**Result**:
```
✅ Data Mahasiswa: 150 ditambahkan
✅ Jadwal PKKMB: 7 ditambahkan
✅ Kegiatan: 10 ditambahkan
```

---

## 📱 Absensi

1. Buka monitor: `http://127.0.0.1:5000/monitor`
2. Pilih kamera
3. Scan QR Code mahasiswa
4. ✅ Berhasil → Muncul notifikasi + beep sound

---

## 💾 Sync ke Server

**Setelah selesai absensi**:

1. Klik **"Sync ke Server"** (tombol biru)
2. Konfirmasi
3. ✅ Data terkirim ke Laravel production
4. Backup Excel otomatis tersimpan

---

## 🐛 Debug

### Check Jadwal
```
http://127.0.0.1:5000/api/python/debug/schedule
```

### Check Status
```
http://127.0.0.1:5000/api/python/status
```

### Check Database
```sql
SELECT COUNT(*) FROM mahasiswa;
SELECT * FROM pkkmb_schedules WHERE tanggal = CURDATE();
```

---

## ⚠️ Common Issues

| Error | Solution |
|-------|----------|
| Tidak Ada Jadwal | Sync ulang master data |
| Connection Refused | Check Laravel server running |
| Database Error | Check MySQL running |
| 403 Forbidden | Hard refresh browser (Ctrl+Shift+R) |

---

## 🔄 Daily Workflow

**Pagi**:
1. Start Python: `python api_server.py`
2. Sync master data (jika ada update)

**Siang** (Absensi):
1. Scan QR codes
2. Monitor real-time

**Sore**:
1. Sync ke server
2. Verify di Laravel dashboard

---

## 📞 Quick Help

```bash
# Restart Python
Ctrl+C
python api_server.py

# Clear browser cache
Ctrl + Shift + R

# Check logs
tail -f logs/attendance.log
```

---

**Need full guide?** → See `PRODUCTION_SETUP.md`
