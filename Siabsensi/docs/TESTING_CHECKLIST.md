# Testing Checklist - reCAPTCHA & Rate Limiting

## ✅ Pre-requisites
- [ ] Server Laravel berjalan (`php artisan serve`)
- [ ] Database MySQL terhubung
- [ ] Cache table exists (`php artisan migrate`)
- [ ] reCAPTCHA keys configured di `.env`
- [ ] Config cache cleared (`php artisan config:clear`)

---

## 🔐 Test 1: reCAPTCHA Validation

### Test Case 1.1: reCAPTCHA Widget Muncul
**Steps:**
1. Buka browser ke halaman login: http://127.0.0.1:8000/login
2. Tunggu halaman load penuh

**Expected Result:**
- ✅ reCAPTCHA widget muncul di atas checkbox "Ingat saya"
- ✅ Widget menampilkan checkbox "I'm not a robot"
- ✅ Styling konsisten dengan design login page

**Status:** [ ]

---

### Test Case 1.2: Login Tanpa Centang reCAPTCHA
**Steps:**
1. Isi username: `admin`
2. Isi password: `admin123`
3. **Jangan** centang reCAPTCHA
4. Klik button "Masuk"

**Expected Result:**
- ❌ Alert popup muncul: "Silakan centang kotak 'Saya bukan robot' terlebih dahulu."
- ❌ Form tidak tersubmit
- ❌ Tidak redirect ke dashboard

**Status:** [ ]

---

### Test Case 1.3: Login dengan reCAPTCHA Valid
**Steps:**
1. Isi username: `admin`
2. Isi password: `admin123`
3. Centang reCAPTCHA ✅
4. Klik button "Masuk"

**Expected Result:**
- ✅ Login berhasil
- ✅ Redirect ke admin dashboard
- ✅ Tidak ada error message

**Status:** [ ]

---

## 🚫 Test 2: Rate Limiting

### Test Case 2.1: Login Gagal - Sisa Percobaan
**Steps:**
1. Logout jika sudah login
2. Isi username: `admin`
3. Isi password: **`wrongpassword123`** (salah)
4. Centang reCAPTCHA
5. Klik "Masuk"
6. Ulangi 4x lagi dengan password salah

**Expected Result (Percobaan 1-4):**
- ❌ Error message: "Nomor Registrasi atau password yang Anda masukkan salah. Sisa percobaan: X kali."
- ❌ X berkurang dari 4, 3, 2, 1
- ✅ Form tetap enabled
- ✅ Bisa mencoba lagi

**Status:** [ ]

---

### Test Case 2.2: Lockout Setelah 5x Gagal
**Steps:**
1. Lanjutkan dari Test 2.1
2. Coba login salah untuk ke-5 kalinya
3. Centang reCAPTCHA
4. Klik "Masuk"

**Expected Result:**
- ❌ Error message: "Terlalu banyak percobaan login gagal. Tunggu X detik lagi."
- 🔒 Form disabled (username, password, reCAPTCHA, button)
- 🔒 Button "Masuk" disabled dengan opacity 0.5
- ⏱️ Countdown timer muncul dan decrement setiap detik

**Status:** [ ]

---

### Test Case 2.3: Countdown Timer Behavior
**Steps:**
1. Lanjutkan dari Test 2.2
2. Tunggu dan amati countdown timer

**Expected Result:**
- ⏱️ Timer menampilkan: "Tunggu 120 detik lagi"
- ⏱️ Timer update setiap 1 detik: 119, 118, 117, ...
- 🔒 Form tetap disabled selama countdown
- ⏱️ Icon `lock_clock` muncul di error message

**Status:** [ ]

---

### Test Case 2.4: Form Re-enabled Setelah Lockout Expired
**Steps:**
1. Lanjutkan dari Test 2.3
2. Tunggu hingga countdown mencapai 0

**Expected Result:**
- ✅ Error message hilang
- ✅ Form re-enabled (username, password, reCAPTCHA, button)
- ✅ Button "Masuk" enabled kembali (opacity 1)
- ✅ reCAPTCHA clickable
- ✅ Bisa mencoba login lagi

**Status:** [ ]

---

### Test Case 2.5: Counter Reset Setelah Login Berhasil
**Steps:**
1. Login salah 2-3 kali (sisa percobaan: 2-3)
2. Login dengan credentials **benar**:
   - Username: `admin`
   - Password: `admin123`
   - Centang reCAPTCHA
3. Klik "Masuk"
4. Setelah login berhasil, logout
5. Login salah lagi

**Expected Result:**
- ✅ Login berhasil di step 3
- ✅ Redirect ke dashboard
- ✅ Setelah logout dan login salah lagi, error menampilkan "Sisa percobaan: 4 kali" (counter direset)

**Status:** [ ]

---

## 🌐 Test 3: Rate Limiting Per Username + IP

### Test Case 3.1: Multiple Users dari IP yang Sama
**Steps:**
1. Login salah 3x dengan username `admin` (sisa: 2)
2. Login salah 3x dengan username `mahasiswa` (berbeda user)

**Expected Result:**
- ✅ Username `admin` memiliki counter terpisah (sisa: 2)
- ✅ Username `mahasiswa` memiliki counter sendiri (sisa: 2 setelah 3x gagal)
- ✅ Tidak saling mempengaruhi

**Status:** [ ]

---

## 📱 Test 4: Responsive Design

### Test Case 4.1: Mobile Device (iPhone/Android)
**Steps:**
1. Buka halaman login di mobile browser atau dev tools mobile view
2. Resize ke 375px width

**Expected Result:**
- ✅ reCAPTCHA widget ter-scale dengan benar (scale: 0.77)
- ✅ Tidak ada horizontal scroll
- ✅ Form tetap readable dan usable
- ✅ Countdown timer readable di mobile

**Status:** [ ]

---

### Test Case 4.2: Tablet (iPad)
**Steps:**
1. Buka halaman login di tablet atau dev tools tablet view
2. Resize ke 768px width

**Expected Result:**
- ✅ reCAPTCHA widget ter-scale dengan benar (scale: 0.85)
- ✅ Layout responsive dan proporsional
- ✅ Touch-friendly UI elements

**Status:** [ ]

---

## 🔄 Test 5: Edge Cases

### Test Case 5.1: Refresh Page During Lockout
**Steps:**
1. Trigger lockout (5x login gagal)
2. Refresh page (F5) saat countdown masih berjalan

**Expected Result:**
- ⏱️ Countdown timer masih muncul dengan waktu yang tersisa
- 🔒 Form masih disabled
- ✅ Lockout tetap berlaku sampai expired

**Status:** [ ]

---

### Test Case 5.2: Multiple Tabs
**Steps:**
1. Buka login page di 2 tabs berbeda
2. Login salah 3x di Tab 1 (sisa: 2)
3. Login salah 2x di Tab 2

**Expected Result:**
- ✅ Total counter: 5x gagal
- 🔒 Kedua tab menampilkan lockout
- ✅ Counter shared antara tabs

**Status:** [ ]

---

### Test Case 5.3: Network Error (Offline)
**Steps:**
1. Disable internet connection
2. Isi form login dan centang reCAPTCHA
3. Klik "Masuk"

**Expected Result:**
- ⚠️ reCAPTCHA validation gagal (graceful degradation)
- ⚠️ Atau Laravel error: "Unable to verify reCAPTCHA"
- ✅ Aplikasi tidak crash

**Status:** [ ]

---

## 🎭 Test 6: Browser Compatibility

### Test Case 6.1: Chrome/Edge
**Steps:**
1. Buka login page di Chrome/Edge
2. Test semua fitur reCAPTCHA dan rate limiting

**Expected Result:**
- ✅ Semua fitur berfungsi normal

**Status:** [ ]

---

### Test Case 6.2: Firefox
**Steps:**
1. Buka login page di Firefox
2. Test semua fitur reCAPTCHA dan rate limiting

**Expected Result:**
- ✅ Semua fitur berfungsi normal

**Status:** [ ]

---

### Test Case 6.3: Safari (if available)
**Steps:**
1. Buka login page di Safari
2. Test semua fitur reCAPTCHA dan rate limiting

**Expected Result:**
- ✅ Semua fitur berfungsi normal

**Status:** [ ]

---

## 🐛 Test 7: Error Handling

### Test Case 7.1: Cache Driver Failed
**Steps:**
1. Stop MySQL server sementara
2. Coba login salah

**Expected Result:**
- ⚠️ Rate limiting mungkin tidak berfungsi
- ✅ Aplikasi tidak crash
- ⚠️ Log error di `storage/logs/laravel.log`

**Status:** [ ]

---

### Test Case 7.2: Invalid reCAPTCHA Keys
**Steps:**
1. Edit `.env` → set `RECAPTCHA_SECRET_KEY=invalid_key`
2. Clear config: `php artisan config:clear`
3. Coba login

**Expected Result:**
- ⚠️ reCAPTCHA validation gagal
- ❌ Error message: "Verifikasi reCAPTCHA gagal"
- ⚠️ Log warning di `storage/logs/laravel.log`

**Status:** [ ]

---

## ✅ Test 8: Performance

### Test Case 8.1: Response Time
**Steps:**
1. Login dengan credentials benar
2. Amati waktu response

**Expected Result:**
- ✅ Login response < 1 detik (dengan reCAPTCHA validation)
- ✅ Tidak ada performance degradation

**Status:** [ ]

---

### Test Case 8.2: Cache Query Performance
**Steps:**
1. Check Laravel debugbar atau log
2. Hitung jumlah cache queries

**Expected Result:**
- ✅ Maximum 2 cache queries per login attempt
- ✅ Tidak ada N+1 query

**Status:** [ ]

---

## 📊 Summary

**Total Test Cases:** 23

**Passed:** _____ / 23
**Failed:** _____ / 23
**Skipped:** _____ / 23

---

## 📝 Notes

- Semua test dilakukan pada environment: **Development/Production**
- PHP Version: _____
- Laravel Version: 12
- Browser(s) tested: _____
- Date tested: _____
- Tester: _____

---

## 🔧 Issues Found

| Test Case | Issue Description | Severity | Status |
|-----------|-------------------|----------|--------|
| Example: 2.3 | Countdown timer tidak smooth | Low | Fixed |
|  |  |  |  |

---

**End of Testing Checklist**
