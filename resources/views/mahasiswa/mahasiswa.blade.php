<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SIABSEN — Portal Mahasiswa</title>
  <link rel="icon" type="image/png" href="{{ asset('static/img/logo.png') }}">
  <link rel="stylesheet" href="{{ asset('static/css/style.css') }}">
  <link rel="stylesheet" href="{{ asset('static/css/mahasiswa.css') }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link
    href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap"
    rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300;400;500;600;700" rel="stylesheet">
</head>

<body>

  <div id="skeleton-loader" class="skeleton-loader">
    <div class="skeleton-content"></div>
  </div>

  <div class="app">

    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-mark">
          <img src="{{ asset('static/img/logo.png') }}" alt="Logo" class="logo-icon">
          <div>
            <div class="logo-text">SIABSEN</div>
            <div class="logo-sub">Portal Mahasiswa</div>
          </div>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="nav">
        <div class="nav-section">Menu Utama</div>
        
        <div class="nav-item active" onclick="showSection('dashboard')" id="nav-dashboard">
          <span class="material-symbols-outlined icon">dashboard</span>
          Dashboard
        </div>
        
        <div class="nav-item" onclick="showSection('profile')" id="nav-profile">
          <span class="material-symbols-outlined icon">person</span>
          Edit Profil
        </div>
        
        <div class="nav-item" onclick="showSection('riwayat')" id="nav-riwayat">
          <span class="material-symbols-outlined icon">history</span>
          Riwayat Kehadiran
        </div>
        
        <div class="nav-item" onclick="showSection('qr-code')" id="nav-qr-code">
          <span class="material-symbols-outlined icon">qr_code</span>
          QR Code Saya
        </div>
        
        <div class="nav-section">Pengajuan</div>
        
        <div class="nav-item" onclick="showSection('izin')" id="nav-izin">
          <span class="material-symbols-outlined icon">edit_note</span>
          Pengajuan Izin/Sakit
        </div>
        
        <div class="nav-item" onclick="showSection('kehadiran')" id="nav-kehadiran">
          <span class="material-symbols-outlined icon">how_to_reg</span>
          Pengajuan Kehadiran
        </div>
        
        <div class="nav-section">Lainnya</div>
        
        <div class="nav-item" onclick="showSection('kegiatan')" id="nav-kegiatan">
          <span class="material-symbols-outlined icon">event</span>
          Absensi Kegiatan
        </div>

        <div class="nav-item" onclick="showSection('sertifikat')" id="nav-sertifikat">
          <span class="material-symbols-outlined icon">workspace_premium</span>
          Unduh Sertifikat
        </div>

        <div class="nav-section">Keluar</div>

        <div class="nav-item" onclick="window.location.href='/logout'" id="nav-keluar" style="color: #ff6b6b;">
          <span class="material-symbols-outlined icon" style="color: #ff6b6b;">logout</span>
          Logout
        </div>
      </nav>

      <!-- Footer -->
      <div class="sidebar-footer">
        <span class="status-dot"></span>
        Sistem Aktif<br>
        <span id="current-time" style="font-family:var(--mono);font-size:10px;opacity:.6"></span>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main">
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <div class="page-title">Portal Mahasiswa SIABSEN</div>
          <div class="page-sub" id="welcome-message">Dashboard & Manajemen Kehadiran</div>
        </div>

        <div class="header-actions">
            <button class="btn btn-ghost btn-sm" onclick="refreshDashboard()">
              <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span>
              Refresh
            </button>
        </div>
      </div>

      <!-- ===== SECTION: DASHBOARD ===== -->
      <section id="section-dashboard">
        
        <!-- Today's Attendance Status -->
        <div id="today-attendance-status" style="margin-bottom:20px"></div>
        
        <!-- Dashboard Statistics -->
        <div id="dashboard-stats" style="display:none">
          <!-- Stats Cards Grid -->
          <div class="stats-grid">
            <div class="stat-card">
              <span class="material-symbols-outlined stat-icon">calendar_month</span>
              <div class="stat-label">Total Hari Hadir</div>
              <div class="stat-value" id="stat-total-hadir">0</div>
              <div class="stat-delta">Kehadiran keseluruhan</div>
            </div>
            <div class="stat-card">
              <span class="material-symbols-outlined stat-icon">check_circle</span>
              <div class="stat-label">Hadir Bulan Ini</div>
              <div class="stat-value" id="stat-bulan-ini">0</div>
              <div class="stat-delta">Bulan berjalan</div>
            </div>
            <div class="stat-card">
              <span class="material-symbols-outlined stat-icon">schedule</span>
              <div class="stat-label">Izin/Sakit</div>
              <div class="stat-value" id="stat-izin-sakit">0</div>
              <div class="stat-delta">Total pengajuan</div>
            </div>
            <div class="stat-card">
              <span class="material-symbols-outlined stat-icon">event_busy</span>
              <div class="stat-label">Tidak Hadir</div>
              <div class="stat-value" id="stat-tidak-hadir">0</div>
              <div class="stat-delta">Tanpa keterangan</div>
            </div>
          </div>

          <!-- Additional Stats Row -->
          <div class="stats-grid" style="margin-top:16px">
            <div class="stat-card">
              <span class="material-symbols-outlined stat-icon">percent</span>
              <div class="stat-label">Persentase Kehadiran</div>
              <div class="stat-value" id="stat-percentage">0%</div>
              <div class="stat-delta">Tingkat kehadiran</div>
            </div>
            <div class="stat-card">
              <span class="material-symbols-outlined stat-icon">avg_time</span>
              <div class="stat-label">Rata-rata Durasi</div>
              <div class="stat-value" id="stat-avg-duration">0 jam</div>
              <div class="stat-delta">Per hari kerja</div>
            </div>
            <div class="stat-card">
              <span class="material-symbols-outlined stat-icon">trending_up</span>
              <div class="stat-label">Streak Terpanjang</div>
              <div class="stat-value" id="stat-longest-streak">0 hari</div>
              <div class="stat-delta">Berturut-turut</div>
            </div>
            <div class="stat-card">
              <span class="material-symbols-outlined stat-icon">schedule</span>
              <div class="stat-label">Terlambat</div>
              <div class="stat-value" id="stat-late-count">0 kali</div>
              <div class="stat-delta">Total keterlambatan</div>
            </div>
          </div>

          <!-- Recent Activity -->
          <div class="panel">
            <div class="section-header">
              <div class="section-title">
                <span class="material-symbols-outlined">notifications</span>
                Aktivitas Terbaru
              </div>
            </div>
            <div id="recent-activity">
              <div class="empty-state">Belum ada aktivitas</div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== SECTION: EDIT PROFIL ===== -->
      <section id="section-profile" style="display:none">
        <div class="panel">
          <div class="section-header">
            <div class="section-title">
              <span class="material-symbols-outlined">person</span>
              Edit Profil Mahasiswa
            </div>
          </div>
          
          <div class="form-row">
            <label class="form-label">Pilih Mahasiswa *</label>
            <select id="profile-mahasiswa-select" class="form-input" onchange="loadProfileData()">
              <option value="">-- Pilih Mahasiswa --</option>
            </select>
          </div>

          <div id="profile-form" style="display:none">
            <!-- Posisikan yang TIDAK BISA diganti di atas -->
            <div class="form-row">
              <label class="form-label">ID Mahasiswa</label>
              <input type="text" id="profile-id" class="form-input" style="background-color:#f5f5f5; cursor:not-allowed;" disabled>
            </div>

            <div class="form-row">
              <label class="form-label">kompi *</label>
              <input type="text" id="profile-kompi" class="form-input" placeholder="kompi" style="background-color:#f5f5f5; cursor:not-allowed;" disabled>
            </div>

            <div class="form-row">
              <label class="form-label">Jurusan *</label>
              <input type="text" id="profile-jurusan" class="form-input" placeholder="Jurusan" style="background-color:#f5f5f5; cursor:not-allowed;" disabled>
            </div>

            <div class="form-row">
              <label class="form-label">Program Studi *</label>
              <input type="text" id="profile-prodi" class="form-input" placeholder="Program Studi" style="background-color:#f5f5f5; cursor:not-allowed;" disabled>
            </div>

            <!-- Posisikan yang BISA diganti di bawah -->
            <div class="form-row" style="margin-top:24px; border-top:1px solid var(--border); padding-top:20px;">
              <label class="form-label">Nama Lengkap *</label>
              <input type="text" id="profile-name" class="form-input" placeholder="Nama Lengkap">
            </div>

            <div class="form-row">
              <label class="form-label">Email</label>
              <input type="email" id="profile-email" class="form-input" placeholder="email@student.ac.id">
            </div>

            <div class="form-row" style="margin-top:24px; border-top:1px solid var(--border); padding-top:20px;">
              <label class="form-label" style="font-size:16px; font-weight:600; margin-bottom:12px; display:block;">
                <span class="material-symbols-outlined" style="font-size:18px; vertical-align:middle;">lock</span>
                Ganti Password
              </label>
            </div>

            <div class="form-row">
              <label class="form-label">Password Sekarang</label>
              <input type="password" id="profile-current-password" class="form-input" placeholder="Password Sekarang">
              <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
                Kosongkan jika tidak ingin mengubah password. <strong>Lupa password saat ini?</strong> Silakan hubungi Administrator atau Tim Disiplin untuk meresetnya.
              </small>
            </div>

            <div class="form-row">
              <label class="form-label">Password Baru</label>
              <input type="password" id="profile-new-password" class="form-input" placeholder="Password Baru (min. 6 karakter)">
            </div>

            <div class="form-row">
              <label class="form-label">Konfirmasi Password Baru</label>
              <input type="password" id="profile-new-password-confirmation" class="form-input" placeholder="Konfirmasi Password Baru">
            </div>

            <div class="btn-group">
              <button class="btn btn-primary" onclick="updateProfile()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">save</span>
                Simpan Perubahan
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== SECTION: RIWAYAT KEHADIRAN ===== -->
      <section id="section-riwayat" style="display:none">
        <div class="panel">
          <div class="section-header">
            <div class="section-title">
              <span class="material-symbols-outlined">history</span>
              Riwayat Kehadiran
            </div>
          </div>
          
          <div class="form-row">
            <label class="form-label">Pilih Mahasiswa *</label>
            <select id="riwayat-mahasiswa-select" class="form-input" onchange="loadRiwayatData()">
              <option value="">-- Pilih Mahasiswa --</option>
            </select>
          </div>

          <div id="riwayat-table-container" style="display:none;margin-top:20px">
            <table class="att-table">
              <thead>
                <tr>
                  <th>No</th>
                  <th>Tanggal</th>
                  <th>Hari</th>
                  <th>Jam Masuk</th>
                  <th>Jam Keluar</th>
                  <th>Durasi</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="riwayat-table-body">
                <tr>
                  <td colspan="7" style="text-align:center;color:var(--muted);padding:30px">Pilih mahasiswa untuk melihat riwayat</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ===== SECTION: PENGAJUAN IZIN/SAKIT ===== -->
      <section id="section-izin" style="display:none">
        <div class="panel">
          <div class="section-header">
            <div class="section-title">
              <span class="material-symbols-outlined">edit_note</span>
              Form Pengajuan Izin/Sakit
            </div>
          </div>
          
          <div class="form-row">
            <label class="form-label">Mahasiswa *</label>
            <select id="izin-mahasiswa-select" class="form-input" onchange="loadMyIzinHistory()">
              <option value="">-- Pilih Mahasiswa --</option>
            </select>
          </div> 

          <div class="form-row">
            <label class="form-label">Jenis Pengajuan *</label>
            <select id="izin-type-select" class="form-input">
              <option value="izin">Izin</option>
              <option value="sakit">Sakit</option>
            </select>
          </div>

          <div class="form-row">
            <label class="form-label">Tanggal *</label>
            <input type="date" id="izin-date-input" class="form-input" required>
          </div>

          <div class="form-row">
            <label class="form-label">Keterangan *</label>
            <textarea id="izin-keterangan-input" class="form-input" rows="4" 
                      placeholder="Jelaskan alasan izin/sakit Anda..." required></textarea>
            <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
              Minimal 10 karakter
            </small>
          </div>

          <div class="form-row">
            <label class="form-label">Upload Bukti *</label>
            <input type="file" id="izin-bukti-input" class="form-input" 
                   accept=".jpg,.jpeg,.png,.pdf" required>
            <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
              Format: JPG, PNG, PDF · Maksimal 10MB · Wajib upload bukti (surat dokter, surat izin, dll)
            </small>
          </div>

          <div class="btn-group">
            <button class="btn btn-primary" onclick="submitIzin()">
              <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">send</span>
              Kirim Pengajuan
            </button>
            <button class="btn btn-ghost" onclick="resetIzinForm()">Reset</button>
          </div>
        </div>

        <!-- Riwayat Pengajuan Izin -->
        <div class="panel">
          <div class="section-header">
            <div class="section-title">
              <span class="material-symbols-outlined">history</span>
              Riwayat Pengajuan Izin/Sakit
            </div>
          </div>
          
          <table class="att-table">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Nama Mahasiswa</th>
                <th>kompi</th>
                <th>Jurusan</th>
                <th>Status</th>
                <th>Diverifikasi Oleh</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="my-izin-table-body">
              <tr>
                <td colspan="5" style="text-align:center;color:var(--muted);padding:30px">
                  Pilih mahasiswa untuk melihat riwayat
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ===== SECTION: PENGAJUAN KEHADIRAN MANUAL ===== -->
      <section id="section-kehadiran" style="display:none">
        <div class="panel">
          <div class="section-header">
            <div class="section-title">
              <span class="material-symbols-outlined">how_to_reg</span>
              Form Pengajuan Kehadiran Manual
            </div>
          </div>
          
          <div class="form-row">
            <label class="form-label">Mahasiswa *</label>
            <select id="kehadiran-mahasiswa-select" class="form-input" onchange="loadMyKehadiranHistory()">
              <option value="">-- Pilih Mahasiswa --</option>
            </select>
          </div>

          <div class="form-row">
            <label class="form-label">Tanggal *</label>
            <input type="date" id="kehadiran-date-input" class="form-input" required>
          </div>

          <div class="form-row">
            <label class="form-label">Jam Masuk *</label>
            <input type="time" id="kehadiran-checkin-input" class="form-input" required>
          </div>

          <div class="form-row">
            <label class="form-label">Jam Keluar *</label>
            <input type="time" id="kehadiran-checkout-input" class="form-input" required>
          </div>

          <div class="form-row">
            <label class="form-label">Keterangan *</label>
            <textarea id="kehadiran-keterangan-input" class="form-input" rows="4" 
                      placeholder="Jelaskan kenapa kehadiran tidak tercatat (contoh: lupa bawa kartu QR, kamera mati, dll)..." required></textarea>
            <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
              Minimal 10 karakter
            </small>
          </div>

          <div class="form-row">
            <label class="form-label">Upload Bukti *</label>
            <input type="file" id="kehadiran-bukti-input" class="form-input" 
                   accept=".jpg,.jpeg,.png,.pdf" required>
            <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
              Format: JPG, PNG, PDF · Maksimal 10MB · Wajib upload bukti (foto selfie di lokasi, dll)
            </small>
          </div>

          <div class="btn-group">
            <button class="btn btn-primary" onclick="submitKehadiran()">
              <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">send</span>
              Kirim Pengajuan
            </button>
            <button class="btn btn-ghost" onclick="resetKehadiranForm()">Reset</button>
          </div>
        </div>

        <!-- Riwayat Pengajuan -->
        <div class="panel">
          <div class="section-header">
            <div class="section-title">
              <span class="material-symbols-outlined">history</span>
              Riwayat Pengajuan Saya
            </div>
          </div>
          
          <table class="att-table">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Nama Mahasiswa</th>
                <th>kompi</th>
                <th>Jurusan</th>
                <th>Status</th>
                <th>Diverifikasi Oleh</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="my-kehadiran-table-body">
              <tr>
                <td colspan="7" style="text-align:center;color:var(--muted);padding:30px">
                  Pilih mahasiswa untuk melihat riwayat
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ===== SECTION: QR CODE SAYA ===== -->
      <section id="section-qr-code" style="display:none">
        <div class="panel">
          <div class="section-header">
            <div class="section-title">
              <span class="material-symbols-outlined">qr_code</span>
              QR Code Saya
            </div>
          </div>
          
          <div style="text-align:center;padding:40px">
            <div id="qr-code-container" style="display:inline-block;background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)">
              <img id="qr-code-image" src="" alt="QR Code" style="width:200px;height:200px;display:block">
            </div>
            <div style="margin-top:20px">
              <button class="btn btn-primary" onclick="downloadQRCode()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span>
                Download QR Code
              </button>
            </div>
            <div style="margin-top:16px;color:var(--text-muted);font-size:14px">
              Gunakan QR Code ini untuk absen saat scan oleh admin
            </div>
          </div>
        </div>
      </section>

      <!-- ===== SECTION: ABSENSI KEGIATAN ===== -->
      <section id="section-kegiatan" style="display:none">
        <div class="panel">
          <div class="section-header">
            <div class="section-title">
              <span class="material-symbols-outlined">event</span>
              Absensi Kegiatan
            </div>
          </div>
          <div id="kegiatan-aktif-container">
            <div class="empty-state">
              <span class="material-symbols-outlined" style="font-size:40px;color:var(--muted)">event_busy</span>
              <p style="margin-top:12px">Memuat kegiatan yang sedang berlangsung...</p>
            </div>
          </div>
        </div>
        <div class="panel">
          <div class="section-header">
            <div class="section-title">
              <span class="material-symbols-outlined">history</span>
              Riwayat Absensi Kegiatan
            </div>
          </div>
          <table class="att-table">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Kegiatan</th>
                <th>Jam</th>
                <th>Absen Pada</th>
              </tr>
            </thead>
            <tbody id="riwayat-kegiatan-tbody">
              <tr>
                <td colspan="4" style="text-align:center;color:var(--muted);padding:30px">Memuat riwayat...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ===== SECTION: UNDUH SERTIFIKAT ===== -->
      <section id="section-sertifikat" style="display:none">
        <div class="panel">
          <div class="section-header">
            <div class="section-title">
              <span class="material-symbols-outlined">workspace_premium</span>
              Unduh Sertifikat Kehadiran
            </div>
          </div>
          
          <div class="form-row">
            <label class="form-label">Pilih Mahasiswa *</label>
            <select id="sertifikat-mahasiswa-select" class="form-input" onchange="loadSertifikatData()">
              <option value="">-- Pilih Mahasiswa --</option>
            </select>
          </div>

          <div id="sertifikat-options" style="display:none">
            <div class="form-row">
              <label class="form-label">Periode Sertifikat *</label>
              <select id="sertifikat-periode" class="form-input">
                <option value="weekly">Mingguan (1 Minggu)</option>
              </select>
            </div>

            <div class="form-row" id="periode-weekly" style="display:block">
              <label class="form-label">Periode</label>
              <div style="background:var(--bg);padding:12px;border-radius:var(--radius-md);border:1px solid var(--border)">
                Sertifikat akan digenerate untuk periode 1 minggu (Senin - Minggu)
              </div>
            </div>

            <div id="periode-custom" style="display:none">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-row" style="margin:0">
                  <label class="form-label">Dari Tanggal</label>
                  <input type="date" id="sertifikat-start-date" class="form-input">
                </div>
                <div class="form-row" style="margin:0">
                  <label class="form-label">Sampai Tanggal</label>
                  <input type="date" id="sertifikat-end-date" class="form-input">
                </div>
              </div>
            </div>

            <!-- Preview Statistik -->
            <div id="sertifikat-preview" class="panel" style="background:var(--primary-light);border:1px solid var(--primary);margin-top:20px;display:none">
              <div class="section-header">
                <div class="section-title" style="color:var(--primary)">
                  <span class="material-symbols-outlined">preview</span>
                  Preview Statistik Sertifikat
                </div>
              </div>
              <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-top:16px">
                <div style="text-align:center">
                  <div style="font-size:24px;font-weight:700;color:var(--primary)" id="preview-total-hadir">0</div>
                  <div style="font-size:12px;color:var(--text-muted)">Total Hadir</div>
                </div>
                <div style="text-align:center">
                  <div style="font-size:24px;font-weight:700;color:var(--success)" id="preview-persentase">0%</div>
                  <div style="font-size:12px;color:var(--text-muted)">Persentase</div>
                </div>
                <div style="text-align:center">
                  <div style="font-size:24px;font-weight:700;color:var(--warning)" id="preview-total-izin">0</div>
                  <div style="font-size:12px;color:var(--text-muted)">Izin/Sakit</div>
                </div>
                <div style="text-align:center">
                  <div style="font-size:24px;font-weight:700;color:var(--text-secondary)" id="preview-total-hari">0</div>
                  <div style="font-size:12px;color:var(--text-muted)">Total Hari</div>
                </div>
              </div>
            </div>

            <div class="btn-group" style="margin-top:20px">
              <button class="btn btn-primary" onclick="openSertifikatPreviewModal(event)">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">preview</span>
                Preview Sertifikat
              </button>
            </div>
          </div>
        </div>

        <!-- Riwayat Sertifikat -->
        <div class="panel" id="sertifikat-history-panel" style="display:none">
          <div class="section-header">
            <div class="section-title">
              <span class="material-symbols-outlined">history</span>
              Riwayat Sertifikat yang Diunduh
            </div>
          </div>
          
          <table class="att-table">
            <thead>
              <tr>
                <th>Tanggal Generate</th>
                <th>Periode</th>
                <th>Total Hadir</th>
                <th>Persentase</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="sertifikat-history-table">
              <tr>
                <td colspan="5" style="text-align:center;color:var(--muted);padding:30px">
                  Pilih mahasiswa untuk melihat riwayat
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

    </main>
  </div>

  <!-- Modals -->
  <div class="modal-backdrop" id="modal-bukti">
    <div class="modal modal-bukti">
      <div class="modal-title">Bukti Pengajuan</div>
      <div id="bukti-content" class="bukti-content">
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="closeModal('modal-bukti')">Tutup</button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="modal-sertifikat-preview">
    <div class="modal" style="max-width:1100px">
      <div class="modal-title">Preview Sertifikat</div>
      <div style="margin-top:16px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-md);padding:12px;overflow:auto">
        <img id="sertifikat-preview-image" src="" alt="Preview Sertifikat" style="width:100%;display:block;border-radius:var(--radius-sm)">
      </div>
      <div class="modal-actions">
        <button class="btn btn-primary" id="btn-download-sertifikat-preview" onclick="downloadSertifikatFromPreview(event)">
          <span class="material-symbols-outlined" style="font-size:16px">download</span>
          Download PNG
        </button>
        <button class="btn btn-ghost" onclick="closeSertifikatPreviewModal()">Close</button>
      </div>
    </div>
  </div>

  <!-- Modal Detail Pengajuan -->
  <div class="modal-backdrop" id="modal-detail-mahasiswa">
    <div class="modal" style="max-width:800px">
      <div class="modal-title">Detail Pengajuan Izin/Sakit</div>
      <div style="margin-top:20px">
        <!-- Info Mahasiswa -->
        <div style="background:var(--bg);padding:20px;border-radius:var(--radius-md);margin-bottom:20px">
          <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;font-weight:600;margin-bottom:12px">Informasi Mahasiswa</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">ID Mahasiswa</div>
              <div style="font-weight:600;font-family:var(--font-mono)" id="mhs-detail-mahasiswa-id">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Nama Lengkap</div>
              <div style="font-weight:600" id="mhs-detail-mahasiswa-name">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">kompi</div>
              <div style="font-weight:600" id="mhs-detail-mahasiswa-kompi">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Jurusan</div>
              <div style="font-weight:600" id="mhs-detail-mahasiswa-jurusan">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Program Studi</div>
              <div style="font-weight:600" id="mhs-detail-mahasiswa-prodi">—</div>
            </div>
          </div>
        </div>

        <!-- Info Pengajuan -->
        <div style="background:var(--bg);padding:20px;border-radius:var(--radius-md);margin-bottom:20px">
          <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;font-weight:600;margin-bottom:12px">Detail Pengajuan</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Jenis Pengajuan</div>
              <div id="mhs-detail-jenis">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Tanggal</div>
              <div style="font-family:var(--font-mono);font-weight:600" id="mhs-detail-tanggal">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Waktu Pengajuan</div>
              <div style="font-family:var(--font-mono);font-size:13px" id="mhs-detail-submitted-at">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Status</div>
              <div id="mhs-detail-status">—</div>
            </div>
          </div>
          <div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Keterangan</div>
            <div style="background:var(--surface);padding:12px;border-radius:var(--radius-sm);border:1px solid var(--border)" id="mhs-detail-keterangan">—</div>
          </div>
        </div>

        <!-- Bukti -->
        <div style="background:var(--bg);padding:20px;border-radius:var(--radius-md);margin-bottom:20px">
          <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;font-weight:600;margin-bottom:12px">Bukti Pendukung</div>
          <div id="mhs-detail-bukti-container" style="text-align:center">—</div>
        </div>

        <!-- Info Verifikasi -->
        <div id="mhs-detail-verification-info" style="background:var(--bg);padding:20px;border-radius:var(--radius-md);display:none">
          <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;font-weight:600;margin-bottom:12px">Informasi Verifikasi</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Diverifikasi Oleh</div>
              <div style="font-weight:600" id="mhs-detail-verified-by">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Waktu Verifikasi</div>
              <div style="font-family:var(--font-mono);font-size:13px" id="mhs-detail-verified-at">—</div>
            </div>
          </div>
          <div id="mhs-detail-rejection-reason-container" style="margin-top:16px;display:none">
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Alasan Penolakan</div>
            <div style="background:var(--danger-light);color:var(--danger);padding:12px;border-radius:var(--radius-sm);border:1px solid var(--danger)" id="mhs-detail-rejection-reason">—</div>
          </div>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="closeModal('modal-detail-mahasiswa')">Tutup</button>
      </div>
    </div>
  </div>

  <div id="toast">
    <div class="toast-title" id="toast-title"></div>
    <div class="toast-msg" id="toast-msg"></div>
  </div>

  <div class="modal-backdrop" id="modal-attendance-reminder">
    <div class="modal" style="max-width:720px">
      <div class="modal-title">Peringatan Absensi Harian</div>
      <div id="attendance-reminder-content" style="margin-top:16px;color:var(--text)"></div>
      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="closeModal('modal-attendance-reminder')">Tutup</button>
        <button class="btn btn-primary" onclick="showSection('riwayat'); closeModal('modal-attendance-reminder')">Lihat Riwayat Absensi</button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" id="modal-detail-kehadiran">
    <div class="modal" style="max-width:800px">
      <div class="modal-title">Detail Pengajuan Kehadiran</div>
      <div style="margin-top:20px">
        
        <div style="background:var(--bg);padding:20px;border-radius:var(--radius-md);margin-bottom:20px">
          <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;font-weight:600;margin-bottom:12px">Informasi Mahasiswa</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">ID Mahasiswa</div>
              <div style="font-weight:600;font-family:var(--font-mono)" id="khd-detail-mahasiswa-id">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Nama Lengkap</div>
              <div style="font-weight:600" id="khd-detail-mahasiswa-name">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">kompi</div>
              <div style="font-weight:600" id="khd-detail-mahasiswa-kompi">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Jurusan</div>
              <div style="font-weight:600" id="khd-detail-mahasiswa-jurusan">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Program Studi</div>
              <div style="font-weight:600" id="khd-detail-mahasiswa-prodi">—</div>
            </div>
          </div>
        </div>

        <div style="background:var(--bg);padding:20px;border-radius:var(--radius-md);margin-bottom:20px">
          <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;font-weight:600;margin-bottom:12px">Detail Kehadiran</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px">
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Tanggal</div>
              <div style="font-family:var(--font-mono);font-weight:600" id="khd-detail-tanggal">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Jam Masuk</div>
              <div style="font-family:var(--font-mono);font-weight:600;color:var(--success)" id="khd-detail-checkin">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Jam Keluar</div>
              <div style="font-family:var(--font-mono);font-weight:600;color:var(--primary)" id="khd-detail-checkout">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Waktu Pengajuan</div>
              <div style="font-family:var(--font-mono);font-size:13px" id="khd-detail-submitted-at">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Status</div>
              <div id="khd-detail-status">—</div>
            </div>
          </div>
          <div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Keterangan</div>
            <div style="background:var(--surface);padding:12px;border-radius:var(--radius-sm);border:1px solid var(--border)" id="khd-detail-keterangan">—</div>
          </div>
        </div>

        <div style="background:var(--bg);padding:20px;border-radius:var(--radius-md);margin-bottom:20px">
          <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;font-weight:600;margin-bottom:12px">Bukti Pendukung</div>
          <div id="khd-detail-bukti-container" style="text-align:center">—</div>
        </div>

        <div id="khd-detail-verification-info" style="background:var(--bg);padding:20px;border-radius:var(--radius-md);display:none">
          <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;font-weight:600;margin-bottom:12px">Informasi Verifikasi</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Diverifikasi Oleh</div>
              <div style="font-weight:600" id="khd-detail-verified-by">—</div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Waktu Verifikasi</div>
              <div style="font-family:var(--font-mono);font-size:13px" id="khd-detail-verified-at">—</div>
            </div>
          </div>
          <div id="khd-detail-rejection-reason-container" style="margin-top:16px;display:none">
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Alasan Penolakan</div>
            <div style="background:var(--danger-light);color:var(--danger);padding:12px;border-radius:var(--radius-sm);border:1px solid var(--danger)" id="khd-detail-rejection-reason">—</div>
          </div>
        </div>

      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="closeModal('modal-detail-kehadiran')">Tutup</button>
      </div>
    </div>
  </div>

  <!-- Mahasiswa Portal Script -->
  <script src="{{ asset('static/js/mahasiswa.js?v=12') }}"></script>

</body>

</html>
