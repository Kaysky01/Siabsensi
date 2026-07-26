# Google reCAPTCHA Setup Guide

## 📋 Deskripsi

Dokumentasi lengkap untuk setup dan konfigurasi Google reCAPTCHA v2 (Checkbox) dan Rate Limiting pada sistem SIABSEN.

## 🔐 Fitur Keamanan

### 1. Google reCAPTCHA v2 (Checkbox)
- Mencegah serangan bot otomatis
- User harus mencentang "I'm not a robot" sebelum login
- Validasi di backend menggunakan Google API

### 2. Rate Limiting
- Maksimal 5 percobaan login per kombinasi username + IP address
- Lockout otomatis selama 2 menit (120 detik) setelah 5x gagal
- Countdown timer dinamis menampilkan sisa waktu lockout
- Counter direset otomatis saat login berhasil

---

## 🚀 Cara Mendapatkan reCAPTCHA Keys

### Langkah 1: Akses Google reCAPTCHA Admin Console
1. Buka [Google reCAPTCHA Admin Console](https://www.google.com/recaptcha/admin/create)
2. Login dengan Google Account Anda

### Langkah 2: Registrasi Domain
Isi form registrasi dengan informasi berikut:

**Label:**
```
SIABSEN Production
```
(atau nama sesuai environment Anda)

**reCAPTCHA Type:**
- Pilih: **reCAPTCHA v2**
- Sub-option: **"I'm not a robot" Checkbox**

**Domains:**
Tambahkan domain aplikasi Anda:
```
pkkmbm.polinela.ac.id
```

**Untuk Development/Testing:**
Tambahkan juga `localhost`:
```
localhost
127.0.0.1
```

**Accept reCAPTCHA Terms of Service:**
- ✅ Centang checkbox

### Langkah 3: Submit dan Copy Keys
1. Klik tombol **Submit**
2. Anda akan mendapatkan:
   - **Site Key** (Public Key) - untuk frontend
   - **Secret Key** (Private Key) - untuk backend validation
3. **Copy kedua keys tersebut**

---

## ⚙️ Konfigurasi di Laravel

### Langkah 1: Update File `.env`

Buka file `.env` dan tambahkan keys:

```env
RECAPTCHA_SITE_KEY=your_site_key_here
RECAPTCHA_SECRET_KEY=your_secret_key_here
```
```

### Langkah 2: Clear Config Cache

Setelah menambahkan keys, clear config cache Laravel:

```bash
php artisan config:cache
```

Jika ada error, clear semua cache:

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Langkah 3: Verifikasi Setup

Test login di browser:
1. Buka halaman login
2. Pastikan reCAPTCHA widget muncul
3. Centang "I'm not a robot"
4. Coba login

---

## 🔧 Konfigurasi Rate Limiting

### Default Configuration

Rate limiting sudah dikonfigurasi dengan nilai default:

- **Max Attempts:** 5 kali percobaan per kombinasi username + IP
- **Lockout Duration:** 120 detik (2 menit)

### Mengubah Konfigurasi

Untuk mengubah nilai, edit file `app/Services/RateLimitService.php`:

```php
class RateLimitService
{
    /**
     * Maximum login attempts before lockout
     */
    const MAX_ATTEMPTS = 5;  // Ubah nilai ini

    /**
     * Lockout duration in seconds
     */
    const LOCKOUT_DURATION = 120;  // Ubah nilai ini (dalam detik)
}
```

**Contoh Konfigurasi Alternatif:**

**Lebih Ketat (3 percobaan, lockout 5 menit):**
```php
const MAX_ATTEMPTS = 3;
const LOCKOUT_DURATION = 300;
```

**Lebih Longgar (10 percobaan, lockout 1 menit):**
```php
const MAX_ATTEMPTS = 10;
const LOCKOUT_DURATION = 60;
```

---

## 🗄️ Cache Requirements

### Check Cache Table

Pastikan tabel `cache` sudah ada di database:

```bash
php artisan migrate
```

### Jika Tabel Belum Ada

Jika tabel `cache` belum ada, generate migration:

```bash
php artisan cache:table
php artisan migrate
```

### Verify Cache Driver

Check file `.env` untuk memastikan cache driver configured:

```env
CACHE_STORE=database
```

Driver yang supported:
- `database` - Recommended (sudah dikonfigurasi)
- `redis` - Jika menggunakan Redis
- `file` - Fallback option

---

## ✅ Testing

### 1. Test reCAPTCHA

**Success Case:**
1. Buka halaman login
2. Isi username dan password
3. Centang reCAPTCHA
4. Klik "Masuk"
5. ✅ Should login successfully (jika credentials benar)

**Failure Case:**
1. Buka halaman login
2. Isi username dan password
3. **Jangan centang reCAPTCHA**
4. Klik "Masuk"
5. ❌ Should show error: "Silakan centang kotak 'Saya bukan robot'"

### 2. Test Rate Limiting

**Step-by-step:**

1. **Percobaan 1-4:** Login dengan password salah
   - ✅ Should show: "Nomor Registrasi atau password yang Anda masukkan salah. Sisa percobaan: X kali."

2. **Percobaan 5:** Login dengan password salah lagi
   - ❌ Should show lockout message dengan countdown timer

3. **During Lockout:**
   - Form disabled
   - reCAPTCHA disabled
   - Countdown timer menampilkan sisa detik
   - Button "Masuk" disabled

4. **After 2 Minutes:**
   - Form re-enabled otomatis
   - Countdown hilang
   - Dapat mencoba login lagi

5. **Successful Login:**
   - Counter direset ke 0
   - Dapat login lagi dari awal

### 3. Test Counter Reset

1. Login dengan password salah 2-3 kali
2. Login dengan credentials **benar**
3. Logout
4. Coba login salah lagi
5. ✅ Counter harus mulai dari awal (sisa percobaan: 5)

---

## 🐛 Troubleshooting

### ❌ reCAPTCHA Widget Tidak Muncul

**Possible Causes:**
1. Site Key tidak configured dengan benar
2. Domain tidak terdaftar di Google reCAPTCHA Admin Console

**Solution:**
- Check file `.env` untuk memastikan `RECAPTCHA_SITE_KEY` terisi
- Verify domain sudah ditambahkan di reCAPTCHA admin console
- Clear browser cache dan reload halaman

### ❌ Rate Limiting Tidak Berfungsi

**Possible Causes:**
1. Cache driver tidak configured
2. Tabel `cache` tidak ada di database

**Solution:**
```bash
# Check cache configuration
php artisan config:show cache

# Create cache table if not exists
php artisan cache:table
php artisan migrate

# Clear cache
php artisan cache:clear
```

### ❌ Lockout Tidak Expired

**Possible Causes:**
1. Cache TTL tidak berfungsi dengan benar

**Solution:**
```bash
# Manual clear cache
php artisan cache:clear

# Atau clear specific key via tinker
php artisan tinker
>>> Cache::forget('login_attempts:username:ip');
>>> Cache::forget('login_lockout:username:ip');
```

### ❌ reCAPTCHA Validation Gagal Terus

**Possible Causes:**
1. Secret Key salah
2. Domain mismatch
3. Network issue ke Google API

**Solution:**
- Verify `RECAPTCHA_SECRET_KEY` di `.env`
- Check domain sudah sesuai dengan yang terdaftar
- Check log Laravel di `storage/logs/laravel.log`

---

## 📱 Mobile Responsiveness

reCAPTCHA widget sudah dikonfigurasi responsive:

- **Desktop (>768px):** Scale 0.95
- **Mobile (480px):** Scale 0.85
- **Small Mobile (375px):** Scale 0.77

Jika perlu adjust, edit file `public/static/css/login.css`:

```css
.recaptcha-wrapper .g-recaptcha {
    transform: scale(0.95); /* Ubah nilai ini */
}
```

---

## 🔒 Security Best Practices

### 1. Protect Your Keys
- ❌ **JANGAN** commit file `.env` ke Git
- ✅ Simpan keys di environment variables production
- ✅ Gunakan keys yang berbeda untuk development dan production

### 2. Monitor Failed Attempts
Check log Laravel secara berkala:
```bash
tail -f storage/logs/laravel.log
```

### 3. Adjust Limits Based on Usage
- Untuk aplikasi internal: gunakan limits lebih ketat
- Untuk aplikasi publik: pertimbangkan limits lebih longgar

### 4. Regular Testing
- Test rate limiting setiap deploy
- Verify reCAPTCHA berfungsi di berbagai devices

---

## 📞 Support

Jika mengalami masalah:

1. Check log Laravel: `storage/logs/laravel.log`
2. Verify configuration dengan: `php artisan config:show`
3. Test cache dengan: `php artisan tinker` → `Cache::get('test')`

---

## 📝 Changelog

### v1.0 (Current)
- ✅ Implementasi Google reCAPTCHA v2 Checkbox
- ✅ Rate Limiting dengan Cache Laravel
- ✅ Countdown Timer Dinamis
- ✅ Client-side Validation
- ✅ Responsive Design
- ✅ Graceful Degradation

---

**Dokumentasi ini dibuat pada:** {{ date('Y-m-d') }}  
**Versi SIABSEN:** 2.5
