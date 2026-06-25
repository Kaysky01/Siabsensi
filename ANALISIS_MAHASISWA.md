# LAPORAN ANALISIS SISTEM MANAJEMEN MAHASISWA - SIABSEN

**Tanggal Analisis:** 2026-06-22  
**Fokus:** Fitur Tambah Mahasiswa, Struktur Database, dan Proses Verifikasi

---

## 1. STRUKTUR DATABASE TABEL MAHASISWA

### Tabel: mahasiswa
**Primary Key:** id (string, max 50) - Bukan auto-increment

| Kolom | Tipe | Karakteristik |
|-------|------|---------------|
| id | string(50) | PK, Unique, Format: MHS-001 |
| name | string(255) | Nama lengkap mahasiswa |
| kompi | string(100) | Kelompok (A,B,C,D,E,F) |
| jurusan | string(100) | Jurusan (Teknik Informatika) |
| prodi | string(100) | Program Studi (D3/S1 Teknik) |
| email | string(255) | Email (nullable) |
| no_telp_mahasiswa | string(20) | No. Telp Mahasiswa (nullable) |
| no_telp_ortu | string(20) | No. Telp Ortu (nullable) |
| qr_code_id | string(100) | Unique, Format: QR-{id} |
| created_at | timestamp | Waktu input (default: NOW()) |
| is_active | tinyint | Status aktif (default: 1) |

### Migrasi:
- `2026_05_05_112332_create_mahasiswa_table.php` (Initial)
- `2026_06_18_115344_add_prodi_to_mahasiswa_table.php` (Penambahan kolom prodi)

### Indeks:
- PRIMARY KEY: id
- UNIQUE: qr_code_id
- INDEX: idx_qr_code
- INDEX: idx_active

### Relasi (Foreign Key):
- ONE TO MANY ke Attendance
- ONE TO MANY ke IzinSubmission
- ONE TO MANY ke KehadiranSubmission
- ONE TO MANY ke SertifikatHistory
- ONE TO ONE ke User (via mahasiswa_id)

---

## 2. MODEL MAHASISWA (app/Models/mahasiswa.php)

✓ **Primary Key:** 'id' (string, non-incrementing)  
✓ **Fillable:** id, name, kompi, jurusan, prodi, email, no_telp_mahasiswa, no_telp_ortu, qr_code_id, is_active  
✓ **Casts:** is_active => boolean  
✓ **Updated_at:** Disabled (hanya created_at)

### Relasi yang tersedia:
- `user()` : Has One
- `attendances()` : Has Many
- `izinSubmissions()` : Has Many
- `kehadiranSubmissions()` : Has Many
- `sertifikatHistories()` : Has Many

### Metode bisnis:
- `calculateAlphaCount($startDate, $endDate)` : Hitung absensi alpha
- `canGetCertificate($startDate, $endDate)` : Cek persentase >= 80%
- `getTodayAttendanceStatus()` : Cek status absensi hari ini

---

## 3. CONTROLLER - METODE TAMBAH MAHASISWA

**Controller:** `app/Http/Controllers/Admin/AdminController.php`

**Metode:** `storeMahasiswa(Request $request)` (Baris 360-383)  
**Route:** `POST /api/mahasiswa` (middleware: auth, role:admin)

### Validasi Input:
- `id`: required, string, unique:mahasiswa,id
- `name`: required, string, max:255
- `kompi`: required, string
- `jurusan`: required, string
- `prodi`: nullable, string, max:100
- `email`: nullable, email
- `no_telp_mahasiswa`: nullable, string
- `no_telp_ortu`: nullable, string

### Proses:
1. Validasi input dari request
2. Generate qr_code_id = id
3. Create record di tabel mahasiswa
4. Return JSON dengan qr_code_id dan qr_image_base64 (PNG)

### Kelemahan:
❌ TIDAK ADA VERIFIKASI SEBELUM DISIMPAN  
❌ Data langsung tersimpan tanpa approval/review  
❌ Tidak ada preview data sebelum submit  
❌ Tidak ada pengecekan duplikasi email  
❌ Tidak ada history/log penambahan

---

## 4. JAVASCRIPT - FORM INPUT MAHASISWA

**File:** `public/static/js/script.js`

### Fungsi Utama:
- `submitMahasiswa()` [Baris 1376-1403] - Submit form ke backend
- `openAddMahasiswa()` [Baris 2150-2180] - Buka modal form
- `loadMahasiswa()` [Baris 530-536] - Load list mahasiswa
- `filterMahasiswa()` [Baris 564-586] - Filter/search mahasiswa

### Form Fields:
| Input ID | Label | Wajib |
|----------|-------|-------|
| f-id | ID Mahasiswa | Ya |
| f-name | Nama Lengkap | Ya |
| f-dept | kompi | Ya |
| f-pos | Jurusan | Ya |
| f-prodi | Program Studi | Ya |
| f-email | Email | Tidak |
| f-telp-mhs | No Telp Mahasiswa | Tidak |
| f-telp-ortu | No Telp Ortu | Tidak |

### Proses Submit:
1. Kumpulkan nilai dari form input
2. Validasi client-side (check null)
3. POST /api/mahasiswa
4. Jika success: tampilkan QR Code
5. Refresh list mahasiswa

### Kelemahan:
❌ Validasi minimal (hanya cek null)  
❌ Tidak ada preview data sebelum POST  
❌ Tidak ada SweetAlert2 confirmation dialog  
❌ Tidak ada pengecekan duplikasi di frontend  
❌ Tidak ada modal review/detail sebelum approval

---

## 5. VIEWS - FORM TAMBAH MAHASISWA

**File:** `resources/views/admin/dashboard.blade.php`

### Bagian Form Manual (Baris 1268-1318):
- Form fields input text untuk semua data
- Tombol "Tambah Mahasiswa" untuk submit

### Bagian Excel Method (Baris 1321-1354):
- Upload file Excel untuk bulk insert
- Template download link
- Format kolom yang diperlukan

### Kelemahan:
❌ Tidak ada preview area sebelum submit  
❌ Tidak ada modal untuk verifikasi data  
❌ Tidak ada status display (pending/approved/rejected)  
❌ Form tidak memiliki reset/cancel yang jelas

---

## 6. ROUTE YANG ADA

```
POST /api/mahasiswa
├─ Controller: AdminController@storeMahasiswa
├─ Middleware: auth, role:admin
└─ Langsung menyimpan ke database

GET /api/mahasiswa
├─ Controller: AdminController@getAllMahasiswa
├─ Middleware: auth, role:admin,timdis,garda
└─ Menampilkan semua mahasiswa dengan status absensi hari ini

DELETE /api/mahasiswa/{id}
├─ Controller: AdminController@deleteMahasiswa
├─ Middleware: auth, role:admin
└─ Hapus mahasiswa

GET /api/mahasiswa/{id}/qr
├─ Controller: AdminController@getMahasiswaQR
├─ Middleware: auth, role:admin
└─ Ambil QR Code mahasiswa
```

---

## 7. MASALAH YANG DITEMUKAN

### 🔴 KRITIS:

1. **Tidak Ada Sistem Verifikasi/Approval**
   - Data mahasiswa langsung tersimpan tanpa review
   - Tidak ada tahap "pending" untuk approval admin
   - Tidak ada penolakan dengan alasan

2. **Tidak Ada Preview Data**
   - Admin tidak bisa lihat data sebelum menyimpan
   - Tidak ada modal detail untuk review
   - Tidak ada konfirmasi dari user

3. **Tidak Ada Audit Log**
   - Tidak tercatat siapa yang menambah mahasiswa
   - Tidak tercatat waktu penambahan
   - Tidak ada history perubahan

4. **Validasi Duplikasi Tidak Lengkap**
   - Hanya cek unique untuk id dan qr_code_id
   - Tidak ada pengecekan duplikasi email
   - Tidak ada pengecekan kesamaan nama + kompi

5. **Excel Import Tidak Ada**
   - Menu untuk upload Excel ada (UI)
   - Tapi backend route tidak ada
   - Fungsi JavaScript handleExcelFileSelect() tidak selesai

### 🟡 SEDANG:

1. **Filter Tidak Ada di Modal Tambah**
   - Form input meminta manual input semua field
   - Tidak ada dropdown untuk kompi, jurusan, prodi
   - Admin harus hafal format data

2. **Tidak Ada SweetAlert2 Confirmation**
   - Hanya simple validation toast
   - Tidak ada confirmation dialog sebelum save

3. **Relasi ke User Tidak Ada**
   - Saat tambah mahasiswa, tidak auto-create user
   - Admin harus manual buat user terpisah

---

## 8. REKOMENDASI PERBAIKAN

### ✅ FASE 1 - IMMEDIATE (Harus Dilakukan):

1. **Tambahkan Modal Preview/Detail**
   - Tampilkan semua data yang akan disimpan
   - Gunakan Bootstrap 5 modal atau existing modal template
   - Tombol: Approve, Reject, Edit

2. **Tambahkan SweetAlert2 Confirmation**
   - Confirmation dialog sebelum save
   - Option untuk cancel atau lanjut

3. **Tambahkan Validasi Duplikasi Email**
   - Backend validation rule: unique:mahasiswa,email
   - Frontend fetch check sebelum save

4. **Tambahkan Dropdown untuk Filter Data**
   - kompi: ambil dari existing mahasiswa data
   - jurusan: dropdown list (bisa hardcode atau dari seeder)
   - prodi: dropdown list (bisa hardcode atau dari seeder)

### ✅ FASE 2 - IMPORTANT (Segera Setelah Fase 1):

1. **Implementasi Approval System**
   - Tambah tabel: mahasiswa_pending
   - Status: pending, approved, rejected
   - Admin: review di modal, approve/reject
   - On approve: move ke tabel mahasiswa

2. **Audit Logging**
   - Simpan siapa + kapan menambah mahasiswa
   - Tabel: system_logs atau mahasiswa_audit

3. **Excel Import**
   - Backend: Parse Excel, validate, simpan ke pending
   - Frontend: Upload file, preview data, approve bulk

4. **Auto-Create User**
   - Saat approve mahasiswa: auto-create user
   - Role: mahasiswa
   - Password: temporary (MHS{id} atau random)

### ✅ FASE 3 - ENHANCEMENT:

1. **DataTables untuk Pending Approval**
   - List semua mahasiswa pending
   - Filter, search, sort
   - Bulk approve/reject

2. **Duplicate Prevention UI**
   - Auto-suggest saat input ID
   - Highlight jika ada mahasiswa serupa
   - Konfirmasi override

3. **Field Validation Real-time**
   - Check ID format (MHS-XXX)
   - Check email format
   - Check phone format

---

## 9. FILE YANG PERLU DIMODIFIKASI

### Backend:
```
app/Http/Controllers/Admin/AdminController.php
├─ Modify: storeMahasiswa() - add preview/approval logic
└─ Add: New methods untuk approval system

app/Models/mahasiswa.php (Optional: add scopes/methods)

routes/web.php (Add new routes untuk approval)

database/migrations/ (Optional: add mahasiswa_pending table)

app/Http/Middleware/CekRole.php (check if already covers admin)
```

### Frontend:
```
resources/views/admin/dashboard.blade.php
├─ Add: Modal untuk preview mahasiswa
├─ Add: Approval/Reject buttons
└─ Update: Form untuk tambah mahasiswa

public/static/js/script.js
├─ Modify: submitMahasiswa() - add preview flow
├─ Add: Preview modal functions
├─ Add: SweetAlert2 confirmation
└─ Add: Excel upload handlers

public/static/css/ (if needed for custom styling)
```

### Database:
```
database/seeders/MahasiswaSeeder.php (untuk test data)

database/migrations/ (untuk new approval table - if needed)
```

---

## 10. KEPUTUSAN YANG DIPERLUKAN SEBELUM IMPLEMENTASI

### ❓ PERTANYAAN UNTUK STAKEHOLDER:

1. **Apakah perlu sistem approval/verifikasi sebelum mahasiswa tersimpan?**
   - Ya, gunakan tabel pending
   - Tidak, langsung tersimpan (current behavior)

2. **Siapa yang bisa approve mahasiswa?**
   - Hanya Admin
   - Admin + Tim Disiplin
   - Admin + Kordinator

3. **Apakah duplikasi email harus dicegah?**
   - Ya, unik per mahasiswa
   - Tidak, boleh sama

4. **Apakah saat tambah mahasiswa harus auto-create user?**
   - Ya, user + password auto-generated
   - Tidak, manual buat user terpisah

5. **Apakah perlu excel bulk import?**
   - Ya, dengan preview + approval
   - Ya, langsung insert
   - Tidak, manual satu-satu

---

## KESIMPULAN

### Status Sistem Saat Ini:
- ✓ BERFUNGSI: Form input data mahasiswa
- ✓ BERFUNGSI: Simpan ke database
- ✓ BERFUNGSI: Generate QR Code
- ✓ BERFUNGSI: Display list mahasiswa dengan filter

### Fitur BELUM Ada:
- ❌ Verifikasi/Approval sebelum save
- ❌ Preview data sebelum submit
- ❌ Audit log penambahan
- ❌ Pengecekan duplikasi email
- ❌ Excel import
- ❌ SweetAlert2 confirmation
- ❌ Dropdown filter data

### REKOMENDASI IMPLEMENTASI:
Mulai dengan **Fase 1** terlebih dahulu (add preview modal + SweetAlert2), kemudian lanjut **Fase 2** (approval system) jika diperlukan.
