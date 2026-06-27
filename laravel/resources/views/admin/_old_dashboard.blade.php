<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>SIABSEN — Sistem Absensi Cerdas</title>
  <link rel="icon" type="image/png" href="{{ asset('static/img/logo.png') }}">
  <link rel="stylesheet" href="{{ asset('static/css/style.css') }}">
  <link
    href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap"
    rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300;400;500;600;700" rel="stylesheet">
  <!-- jsQR Library for QR Code Detection in Browser -->
  <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

  <div class="app">

    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-mark">
          <img src="{{ asset('static/img/logo.png') }}" alt="Logo" class="logo-icon">
          <div>
            <div class="logo-text">SIABSEN</div>
            <div class="logo-sub">v2.4</div>
          </div>
        </div>
      </div>

      <nav class="nav nav-loading">
        <div id="sidebar-skeleton">
          <div class="skeleton-nav-section"></div>
          <div class="skeleton-nav-item"><span class="skeleton-nav-icon"></span><span class="skeleton-nav-text"></span></div>
          <div class="skeleton-nav-item"><span class="skeleton-nav-icon"></span><span class="skeleton-nav-text"></span></div>
          <div class="skeleton-nav-section"></div>
          <div class="skeleton-nav-item"><span class="skeleton-nav-icon"></span><span class="skeleton-nav-text"></span></div>
          <div class="skeleton-nav-item"><span class="skeleton-nav-icon"></span><span class="skeleton-nav-text"></span></div>
          <div class="skeleton-nav-item"><span class="skeleton-nav-icon"></span><span class="skeleton-nav-text"></span></div>
          <div class="skeleton-nav-section"></div>
          <div class="skeleton-nav-item"><span class="skeleton-nav-icon"></span><span class="skeleton-nav-text"></span></div>
          <div class="skeleton-nav-item"><span class="skeleton-nav-icon"></span><span class="skeleton-nav-text"></span></div>
          <div class="skeleton-nav-section"></div>
          <div class="skeleton-nav-item"><span class="skeleton-nav-icon"></span><span class="skeleton-nav-text"></span></div>
          <div class="skeleton-nav-item"><span class="skeleton-nav-icon"></span><span class="skeleton-nav-text"></span></div>
        </div>
        <div class="nav-section">Utama</div>
        <div class="nav-item active" onclick="showPage('dashboard')">
          <span class="material-symbols-outlined icon">dashboard</span> Dashboard
        </div>
        <div class="nav-item" onclick="showPage('attendance')">
          <span class="material-symbols-outlined icon">check_circle</span> Absensi Hari Ini
          <span class="badge" id="sidebar-present">0</span>
        </div>
        <div class="nav-section">Data</div>
        <div class="nav-item" onclick="showPage('mahasiswa')" id="nav-mahasiswa">
          <span class="material-symbols-outlined icon">badge</span> Mahasiswa
        </div>
        <div class="nav-item" onclick="showPage('mahasiswa-saya')" id="nav-mahasiswa-saya" style="display:none">
          <span class="material-symbols-outlined icon">visibility</span> Mahasiswa Saya
        </div>
        <div class="nav-item" onclick="showPage('kompi-management')" id="nav-kompi-management">
          <span class="material-symbols-outlined icon">groups</span> Pengaturan Kompi
        </div>
        <div class="nav-item" onclick="showPage('history')">
          <span class="material-symbols-outlined icon">history</span> Riwayat
        </div>
        <div class="nav-section">Kegiatan</div>
        <div class="nav-item" onclick="showPage('kegiatan')" id="nav-kelola-kegiatan">
          <span class="material-symbols-outlined icon">event</span> Kelola Kegiatan
        </div>
        <div class="nav-item" onclick="showPage('monitoring-kegiatan')" id="nav-monitoring-kegiatan">
          <span class="material-symbols-outlined icon">monitoring</span> Monitoring Kegiatan
        </div>
        <div class="nav-section">Analisis</div>

        <div class="nav-item" onclick="showPage('kelulusan')">
          <span class="material-symbols-outlined icon">assignment_turned_in</span> Laporan Kelulusan
        </div>
        <div class="nav-section">Verifikasi Pengajuan</div>
        <div class="nav-item" onclick="showPage('izin-timdis')">
          <span class="material-symbols-outlined icon">fact_check</span> Verifikasi Izin/Sakit
          <span class="badge badge-warning" id="sidebar-pending-izin">0</span>
        </div>
        <div class="nav-item" onclick="showPage('kehadiran-timdis')">
          <span class="material-symbols-outlined icon">how_to_reg</span> Verifikasi Kehadiran
          <span class="badge badge-warning" id="sidebar-pending-kehadiran">0</span>
        </div>
        <div class="nav-section">Sistem</div>
        <div class="nav-item" onclick="showPage('users')">
          <span class="material-symbols-outlined icon">manage_accounts</span> Admin Management
        </div>
        <div class="nav-item" onclick="showPage('settings')">
          <span class="material-symbols-outlined icon">settings</span> Pengaturan
        </div>
        <div class="nav-section">Keluar</div>
        <div class="nav-item" onclick="window.location.href='/logout'" style="color: #ff6b6b;">
          <span class="material-symbols-outlined icon" style="color: #ff6b6b;">logout</span> Logout
        </div>
      </nav>

      <div class="sidebar-footer">
        <span class="status-dot"></span>
        Sistem Aktif<br>
        <span id="current-time" style="font-family:var(--mono);font-size:10px;opacity:.6"></span>
      </div>
    </aside>

    <main class="main">

      <div id="skeleton-loader" class="skeleton-loader">
        <div class="skeleton-line title"></div>
        <div class="skeleton-line subtitle"></div>
        <div class="skeleton-line filter"></div>
        <div class="skeleton-table">
          <div class="skeleton-row">
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
          </div>
          <div class="skeleton-row">
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
          </div>
          <div class="skeleton-row">
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
          </div>
          <div class="skeleton-row">
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
          </div>
          <div class="skeleton-row">
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
            <div class="skeleton-cell"></div>
          </div>
        </div>
      </div>

      <section id="page-dashboard" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Dashboard Absensi</div>
            <div class="page-sub" id="today-label">Loading...</div>
            <div id="welcome-message"></div>
          </div>
          <div class="header-actions">
            <button class="btn btn-ghost btn-sm" onclick="refreshData()"><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span> Refresh</button>
          </div>
        </div>

        <div class="stats-grid">
          <!-- Hero Stat - Primary Focus -->
          <div class="stat-card">
            <span class="material-symbols-outlined stat-icon">group</span>
            <div class="stat-label">Total Mahasiswa</div>
            <div class="stat-value" id="s-total">—</div>
            <div class="stat-delta">Aktif terdaftar dalam sistem</div>
          </div>
          
          <!-- Secondary Stats -->
          <div class="stat-card">
            <span class="material-symbols-outlined stat-icon">task_alt</span>
            <div class="stat-label">Hadir Hari Ini</div>
            <div class="stat-value" id="s-present">—</div>
            <div class="stat-delta"><span class="up" id="s-pct">—</span>% kehadiran</div>
          </div>
          <div class="stat-card">
            <span class="material-symbols-outlined stat-icon">person_off</span>
            <div class="stat-label">Tidak Hadir</div>
            <div class="stat-value" id="s-absent">—</div>
            <div class="stat-delta">Belum absen masuk</div>
          </div>
          <div class="stat-card">
            <span class="material-symbols-outlined stat-icon">schedule</span>
            <div class="stat-label">Masih di Kantor</div>
            <div class="stat-value" id="s-inoffice">—</div>
            <div class="stat-delta">Belum absen keluar</div>
          </div>
        </div>

        <div class="three-col">
          <div class="panel">
            <div class="section-header">
              <div>
                <div class="section-title">Absensi Terkini</div>  
              </div>
              <button class="btn btn-ghost btn-sm" onclick="showPage('attendance')">Lihat Semua <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">arrow_forward</span></button>
            </div>
            <table class="att-table">
              <thead>
                <tr>
                  <th>Mahasiswa</th>
                  <th>Masuk</th>
                  <th>Keluar</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="recent-tbody">
                <tr>
                  <td colspan="4" style="text-align:center;color:var(--muted);padding:20px">Memuat data...</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div style="display:flex;flex-direction:column;gap:16px">

            <div class="panel">
              <div class="section-header">
                <div class="section-title">Tren 7 Hari</div>
              </div>
              <div class="trend-chart">
                <div class="bar-chart" id="trend-chart"></div>
              </div>
            </div>

            <div class="panel">
              <div class="section-header">
                <div class="section-title">Per kompi</div>
              </div>
              <div id="dept-list"></div>
            </div>
          </div>
        </div>
      </section>

      <section id="page-attendance" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Absensi Hari Ini</div>
            <div class="page-sub" id="att-date-label">—</div>
          </div>
          <div class="header-actions">
            <input type="date" id="att-date-filter" class="form-input" style="width:160px;padding:7px 10px"
              onchange="filterAttendance(this.value)">
            <button class="btn btn-ghost btn-sm" onclick="exportCSV()"><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Export CSV</button>
          </div>
        </div>
        <!-- Filter Status Absensi -->
        <div class="panel" style="margin-bottom:16px;padding:14px 20px">
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div>
              <label class="form-label">Filter Status</label>
              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="btn btn-sm filter-btn active" data-filter="all" onclick="setAttendanceFilter('all')">Semua</button>
                <button class="btn btn-sm filter-btn" data-filter="izin" onclick="setAttendanceFilter('izin')">Izin</button>
                <button class="btn btn-sm filter-btn" data-filter="sakit" onclick="setAttendanceFilter('sakit')">Sakit</button>
                <button class="btn btn-sm filter-btn" data-filter="alpha" onclick="setAttendanceFilter('alpha')">Alpha</button>
              </div>
            </div>
            <div style="align-self:flex-end">
              <button class="btn btn-secondary btn-sm" onclick="resetAttendanceFilter()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span>
                Reset
              </button>
            </div>
          </div>
        </div>
        <div class="panel">
          <table class="att-table" id="full-att-table">
            <thead>
              <tr>
                <th>No</th>
                <th>Mahasiswa</th>
                <th>kompi</th>
                <th>Jam Masuk</th>
                <th>Jam Keluar</th>
                <th>Durasi</th>
                <th>Kamera</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="full-att-tbody">
              <tr>
                <td colspan="8" style="text-align:center;color:var(--muted);padding:30px">Memuat...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section id="page-mahasiswa" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Data Mahasiswa</div>
            <div class="page-sub">Manajemen mahasiswa dan QR Code</div>
          </div>
          <button class="btn btn-primary btn-sm" onclick="openAddMahasiswa()"><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">add</span> Tambah Mahasiswa</button>
        </div>
        
        <!-- Filter dan Pencarian -->
        <div class="panel" style="margin-bottom:16px;padding:14px 20px">
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div style="flex:1;min-width:200px">
              <label class="form-label">Cari Nama Mahasiswa</label>
              <input type="text" id="mhs-search" class="form-input" placeholder="Ketik nama mahasiswa..." style="padding:7px 10px" oninput="filterMahasiswa()">
            </div>
            <div>
              <label class="form-label">kompi</label>
              <select id="mhs-filter-kompi" class="form-input" style="width:120px;padding:7px 10px" onchange="filterMahasiswa()">
                <option value="">Semua</option>
              </select>
            </div>
            <div>
              <label class="form-label">Jurusan</label>
              <select id="mhs-filter-jurusan" class="form-input" style="width:180px;padding:7px 10px" onchange="filterMahasiswa()">
                <option value="">Semua</option>
              </select>
            </div>
            <div>
              <label class="form-label">Prodi</label>
              <select id="mhs-filter-prodi" class="form-input" style="width:180px;padding:7px 10px" onchange="filterMahasiswa()">
                <option value="">Semua</option>
              </select>
            </div>
            <div style="align-self:flex-end">
              <button class="btn btn-secondary btn-sm" onclick="resetMahasiswaFilter()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span>
                Reset
              </button>
            </div>
          </div>
        </div>
        
        <div class="panel">
          <table class="att-table">
            <thead>
              <tr>
                <th>Mahasiswa</th>
                <th>Kompi</th>
                <th>Prodi</th>
                <th>Email</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="mhs-tbody">
              <tr>
                <td colspan="6" style="text-align:center;color:var(--muted);padding:30px">Memuat...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ===== PAGE: MAHASISWA SAYA (GARDA) ===== -->
      <section id="page-mahasiswa-saya" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Mahasiswa Saya</div>
            <div class="page-sub" id="garda-kompi-label">Daftar mahasiswa kompi Anda</div>
          </div>
        </div>
        <div class="panel" style="margin-bottom:16px;padding:14px 20px">
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div style="flex:1;min-width:200px">
              <label class="form-label">Cari Nama Mahasiswa</label>
              <input type="text" id="garda-mhs-search" class="form-input" placeholder="Ketik nama mahasiswa..." style="padding:7px 10px" oninput="filterGardaMahasiswa()">
            </div>
            <div>
              <label class="form-label">Prodi</label>
              <select id="garda-filter-prodi" class="form-input" style="width:180px;padding:7px 10px" onchange="filterGardaMahasiswa()">
                <option value="">Semua</option>
              </select>
            </div>
          </div>
        </div>
        <div class="panel">
          <table class="att-table">
            <thead>
              <tr>
                <th>Mahasiswa</th>
                <th>Prodi</th>
                <th>Jurusan</th>
                <th>Jam Masuk</th>
                <th>Jam Keluar</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="garda-mhs-tbody">
              <tr>
                <td colspan="7" style="text-align:center;color:var(--muted);padding:30px">Memuat data...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ===== PAGE: PENGATURAN KOMPI (ADMIN ONLY) ===== -->
      <section id="page-kompi-management" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Pengaturan & Pembagian Kompi</div>
            <div class="page-sub">Kelola dan bagi kompi mahasiswa secara massal (bulk)</div>
          </div>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:6px">
              <label class="form-label" style="margin:0;white-space:nowrap">Jumlah Kompi:</label>
              <input type="number" id="kompi-count" class="form-input" value="3" min="2" max="20" style="width:70px;padding:7px 10px">
            </div>
            <button class="btn btn-secondary btn-sm" onclick="shuffleKompi()">
              <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">shuffle</span> Acak
            </button>
            <button class="btn btn-primary btn-sm" onclick="saveBulkKompi()">
              <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">save</span> Simpan Pembagian Kompi
            </button>
          </div>
        </div>

        <div class="panel" style="margin-bottom:16px;padding:14px 20px">
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div style="flex:1;min-width:200px">
              <label class="form-label">Cari Nama Mahasiswa</label>
              <input type="text" id="kompi-mhs-search" class="form-input" placeholder="Ketik nama mahasiswa..." style="padding:7px 10px" oninput="filterKompiManagement()">
            </div>
            <div>
              <label class="form-label">Kompi Saat Ini</label>
              <select id="kompi-filter-current" class="form-input" style="width:120px;padding:7px 10px" onchange="filterKompiManagement()">
                <option value="">Semua</option>
              </select>
            </div>
            <div>
              <label class="form-label">Prodi</label>
              <select id="kompi-filter-prodi" class="form-input" style="width:180px;padding:7px 10px" onchange="filterKompiManagement()">
                <option value="">Semua</option>
              </select>
            </div>
            <div style="align-self:flex-end">
              <button class="btn btn-secondary btn-sm" onclick="resetKompiManagementFilter()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span>
                Reset
              </button>
            </div>
          </div>
        </div>

        <div class="panel">
          <table class="att-table">
            <thead>
              <tr>
                <th>Mahasiswa</th>
                <th>Program Studi (Prodi)</th>
                <th>Jurusan</th>
                <th style="width:250px">Atur Kompi</th>
              </tr>
            </thead>
            <tbody id="kompi-tbody">
              <tr>
                <td colspan="4" style="text-align:center;color:var(--muted);padding:30px">Memuat data mahasiswa...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section id="page-history" style="display:none">
        <div class="page-header">
          <div class="page-title">Riwayat Absensi</div>
        </div>
        <div class="panel" style="margin-bottom:16px;padding:14px 20px">
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div>
              <label class="form-label">Dari Tanggal</label>
              <input type="date" id="hist-start" class="form-input" style="width:150px;padding:7px 10px">
            </div>
            <div>
              <label class="form-label">Sampai Tanggal</label>
              <input type="date" id="hist-end" class="form-input" style="width:150px;padding:7px 10px">
            </div>
            <div style="align-self:flex-end">
              <button class="btn btn-primary btn-sm" onclick="loadHistory()">Cari</button>
            </div>
          </div>
        </div>
        <div class="panel">
          <table class="att-table">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Mahasiswa</th>
                <th>kompi</th>
                <th>Masuk</th>
                <th>Keluar</th>
                <th>Durasi</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="hist-tbody">
              <tr>
                <td colspan="7" style="text-align:center;color:var(--muted);padding:30px">Pilih rentang tanggal dan
                  tekan Cari</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>



      <!-- ===== LAPORAN KELULUSAN ===== -->
      <section id="page-kelulusan" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Laporan Kelulusan Kehadiran</div>
            <div class="page-sub">Pantau mahasiswa yang lulus/tidak lulus berdasarkan kehadiran (min. 80%)</div>
          </div>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <div>
              <label class="form-label">Prodi</label>
              <select id="kelulusan-filter-prodi" class="form-input" style="width:200px;padding:7px 10px">
                <option value="">Semua Prodi</option>
              </select>
            </div>
            <div>
              <label class="form-label">Jurusan</label>
              <select id="kelulusan-filter-jurusan" class="form-input" style="width:200px;padding:7px 10px">
                <option value="">Semua Jurusan</option>
              </select>
            </div>
            <div>
              <label class="form-label">Status</label>
              <select id="kelulusan-filter-status" class="form-input" style="width:140px;padding:7px 10px">
                <option value="">Semua</option>
                <option value="Lulus">Lulus</option>
                <option value="Tidak Lulus">Tidak Lulus</option>
              </select>
            </div>
            <div style="align-self:flex-end">
              <button class="btn btn-primary btn-sm" onclick="loadKelulusan()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">search</span> Tampilkan
              </button>
            </div>
          </div>
        </div>
        <div class="panel">
          <table class="att-table">
            <thead>
              <tr>
                <th>Mahasiswa</th>
                <th>Kompi</th>
                <th>Prodi</th>
                <th>Jurusan</th>
                <th>Total Hari</th>
                <th>Total Hadir</th>
                <th>Persentase</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="kelulusan-tbody">
              <tr>
                <td colspan="8" style="text-align:center;color:var(--muted);padding:30px">
                  Pilih filter dan klik Tampilkan
                </td>
              </tr>
            </tbody>
          </table>
          <div style="margin-top:16px;display:flex;gap:12px;align-items:center">
            <span id="kelulusan-summary" style="font-size:13px;color:var(--muted)"></span>
            <button class="btn btn-ghost btn-sm" onclick="exportKelulusanCSV()">
              <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Export CSV
            </button>
          </div>
        </div>
      </section>

      <!-- ===== VERIFIKASI IZIN/SAKIT (TIMDIS) ===== -->
      <section id="page-izin-timdis" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Verifikasi Pengajuan Izin/Sakit</div>
            <div class="page-sub">Kelola dan verifikasi pengajuan izin/sakit dari mahasiswa</div>
          </div>
          <div class="header-actions">
            <button class="btn btn-ghost btn-sm" onclick="loadIzinSubmissions()">
              <span class="material-symbols-outlined" style="font-size:16px">refresh</span> Refresh
            </button>
          </div>
        </div>

        <!-- Stats Cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px">
          <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value" id="stat-pending-izin">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Disetujui</div>
            <div class="stat-value" id="stat-approved-izin">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Ditolak</div>
            <div class="stat-value" id="stat-rejected-izin">0</div>
          </div>
        </div>

        <!-- Filter dan Pencarian -->
        <div class="panel" style="margin-bottom:16px;padding:14px 20px">
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div style="flex:1;min-width:200px">
              <label class="form-label">Cari Nama Mahasiswa</label>
              <input type="text" id="izin-search" class="form-input" placeholder="Ketik nama mahasiswa..." style="padding:7px 10px" oninput="filterIzinSubmissions()">
            </div>
            <div>
              <label class="form-label">kompi</label>
              <select id="izin-filter-kompi" class="form-input" style="width:120px;padding:7px 10px" onchange="filterIzinSubmissions()">
                <option value="">Semua</option>
              </select>
            </div>
            <div>
              <label class="form-label">Status</label>
              <select id="izin-filter-status" class="form-input" style="width:150px;padding:7px 10px" onchange="filterIzinSubmissions()">
                <option value="">Semua</option>
                <option value="pending" selected>Pending</option>
                <option value="approved">Disetujui</option>
                <option value="rejected">Ditolak</option>
              </select>
            </div>
            <div style="align-self:flex-end">
              <button class="btn btn-secondary btn-sm" onclick="resetIzinFilter()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span>
                Reset
              </button>
            </div>
          </div>
        </div>

        <!-- Tabel Pengajuan -->
        <div class="panel">
          <div style="overflow-x:auto">
            <table class="att-table">
              <thead>
                <tr>
                  <th>Mahasiswa</th>
                  <th>kompi</th>
                  <th>Jenis</th>
                  <th>Tanggal</th>
                  <th>Keterangan</th>
                  <th>Bukti</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody id="izin-submissions-table-body">
                <tr>
                  <td colspan="8" style="text-align:center;color:var(--muted);padding:30px">
                    Loading...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ===== VERIFIKASI PENGAJUAN KEHADIRAN (TIMDIS) ===== -->
      <section id="page-kehadiran-timdis" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Verifikasi Pengajuan Kehadiran</div>
            <div class="page-sub">Kelola dan verifikasi pengajuan kehadiran dari mahasiswa</div>
          </div>
          <div class="header-actions">
            <button class="btn btn-ghost btn-sm" onclick="loadKehadiranSubmissions()">
              <span class="material-symbols-outlined" style="font-size:16px">refresh</span> Refresh
            </button>
          </div>
        </div>

        <!-- Stats Cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px">
          <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value" id="stat-pending-kehadiran">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Disetujui</div>
            <div class="stat-value" id="stat-approved-kehadiran">0</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Ditolak</div>
            <div class="stat-value" id="stat-rejected-kehadiran">0</div>
          </div>
        </div>

        <!-- Filter dan Pencarian -->
        <div class="panel" style="margin-bottom:16px;padding:14px 20px">
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div style="flex:1;min-width:200px">
              <label class="form-label">Cari Nama Mahasiswa</label>
              <input type="text" id="kehadiran-search" class="form-input" placeholder="Ketik nama mahasiswa..." style="padding:7px 10px" oninput="filterKehadiranSubmissions()">
            </div>
            <div>
              <label class="form-label">kompi</label>
              <select id="kehadiran-filter-kompi" class="form-input" style="width:120px;padding:7px 10px" onchange="filterKehadiranSubmissions()">
                <option value="">Semua</option>
              </select>
            </div>
            <div>
              <label class="form-label">Status</label>
              <select id="kehadiran-filter-status" class="form-input" style="width:150px;padding:7px 10px" onchange="filterKehadiranSubmissions()">
                <option value="">Semua</option>
                <option value="pending" selected>Pending</option>
                <option value="approved">Disetujui</option>
                <option value="rejected">Ditolak</option>
              </select>
            </div>
            <div style="align-self:flex-end">
              <button class="btn btn-secondary btn-sm" onclick="resetKehadiranFilter()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span>
                Reset
              </button>
            </div>
          </div>
        </div>

        <!-- Tabel Pengajuan Kehadiran -->
        <div class="panel">
          <div style="overflow-x:auto">
            <table class="att-table">
              <thead>
                <tr>
                  <th>Mahasiswa</th>
                  <th>kompi</th>
                  <th>Tanggal</th>
                  <th>Jam Masuk</th>
                  <th>Jam Keluar</th>
                  <th>Keterangan</th>
                  <th>Bukti</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody id="kehadiran-submissions-table-body">
                <tr>
                  <td colspan="9" style="text-align:center;color:var(--muted);padding:30px">
                    Loading...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
      <!-- ===== KEGIATAN MANAGEMENT ===== -->
      <section id="page-kegiatan" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Kelola Kegiatan</div>
            <div class="page-sub">Buat dan kelola kegiatan absensi dinamis</div>
          </div>
          <button class="btn btn-primary btn-sm" onclick="openAddKegiatan()">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">add</span> Tambah Kegiatan
          </button>
        </div>
        <div class="panel">
          <table class="att-table">
            <thead>
              <tr>
                <th>Nama Kegiatan</th>
                <th>Tanggal</th>
                <th>Wajib Hadir</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="kegiatan-tbody">
              <tr>
                <td colspan="5" style="text-align:center;color:var(--muted);padding:30px">Memuat...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ===== MONITORING KEGIATAN ===== -->
      <section id="page-monitoring-kegiatan" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Monitoring Kegiatan</div>
            <div class="page-sub">Pantau kehadiran mahasiswa per kegiatan</div>
          </div>
        </div>
        <div class="panel" style="margin-bottom:16px;padding:14px 20px">
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div>
              <label class="form-label">Pilih Kegiatan *</label>
              <select id="mon-kegiatan-select" class="form-input" style="width:300px;padding:7px 10px" onchange="loadMonitoringKegiatan()">
                <option value="">-- Pilih Kegiatan --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Cari Nama</label>
              <input type="text" id="mon-search" class="form-input" placeholder="Ketik nama..." style="padding:7px 10px" oninput="filterMonitoringKegiatan()">
            </div>
            <div>
              <label class="form-label">Kompi</label>
              <select id="mon-filter-kompi" class="form-input" style="width:120px;padding:7px 10px" onchange="filterMonitoringKegiatan()">
                <option value="">Semua</option>
              </select>
            </div>
            <div>
              <label class="form-label">Prodi</label>
              <select id="mon-filter-prodi" class="form-input" style="width:180px;padding:7px 10px" onchange="filterMonitoringKegiatan()">
                <option value="">Semua</option>
              </select>
            </div>
            <div>
              <label class="form-label">Status</label>
              <select id="mon-filter-status" class="form-input" style="width:130px;padding:7px 10px" onchange="filterMonitoringKegiatan()">
                <option value="">Semua</option>
                <option value="hadir">Hadir</option>
                <option value="belum">Belum</option>
              </select>
            </div>
          </div>
          <div style="margin-top:8px">
            <span id="mon-summary" style="font-size:13px;color:var(--muted)"></span>
          </div>
        </div>
        <div class="panel">
          <table class="att-table">
            <thead>
              <tr>
                <th>Mahasiswa</th>
                <th>Kompi</th>
                <th>Prodi</th>
                <th>Status</th>
                <th>Masuk</th>
                <th>Keluar</th>
              </tr>
            </thead>
            <tbody id="mon-tbody">
              <tr>
                <td colspan="6" style="text-align:center;color:var(--muted);padding:30px">Pilih kegiatan terlebih dahulu</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section id="page-settings" style="display:none">
        <div class="page-title" style="margin-bottom:20px">Pengaturan Sistem</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
          <div class="panel">
            <div class="section-title" style="margin-bottom:16px">Konfigurasi YOLO</div>
            <div class="form-row">
              <label class="form-label">Model Path</label>
              <div style="display:flex;gap:8px">
                <input id="setting-model-path" class="form-input" value="models/yolov8n.pt" placeholder="models/yolov8n.pt" style="flex:1">
                <button class="btn btn-secondary btn-sm" onclick="browseModels()" style="white-space:nowrap">
                  <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">folder_open</span>
                  Browse
                </button>
              </div>
              <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">Contoh: models/yolov8n.pt, models/custom_qr.pt</small>
            </div>
            <div class="form-row">
              <label class="form-label">Confidence Threshold</label>
              <input id="setting-yolo-conf" class="form-input" type="number" value="0.45" min="0.1" max="1" step="0.05">
            </div>
            <div class="form-row">
              <label class="form-label">QR Cooldown (detik)</label>
              <input id="setting-qr-cooldown" class="form-input" type="number" value="30" min="5" max="300">
            </div>
            <button class="btn btn-primary btn-sm" onclick="saveYoloSettings()">Simpan Perubahan</button>
          </div>
          <div class="panel">
            <div class="section-title" style="margin-bottom:16px">Konfigurasi RTSP</div>
            <div class="form-row">
              <label class="form-label">Frame Width</label>
              <input id="setting-frame-width" class="form-input" type="number" value="1280" min="320" max="3840">
            </div>
            <div class="form-row">
              <label class="form-label">Frame Height</label>
              <input id="setting-frame-height" class="form-input" type="number" value="720" min="240" max="2160">
            </div>
            <div class="form-row">
              <label class="form-label">Frame FPS</label>
              <input id="setting-frame-fps" class="form-input" type="number" value="30" min="1" max="60">
            </div>
            <div class="form-row">
              <label class="form-label">Reconnect Delay (detik)</label>
              <input id="setting-reconnect-delay" class="form-input" type="number" value="5" min="1" max="30">
            </div>
            <button class="btn btn-primary btn-sm" onclick="saveRtspSettings()">Simpan Perubahan</button>
          </div>
          <div class="panel" style="grid-column:1/-1">
            <div class="section-title" style="margin-bottom:16px">Tentang Sistem</div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;font-size:12px;color:var(--muted)">
              <div><strong style="color:var(--text)">Engine Deteksi</strong><br>YOLOv8 (Ultralytics)<br>Kelas: Person
                (cls 0)</div>
              <div><strong style="color:var(--text)">QR Code</strong><br>Generator: qrcode[pil]<br>Decoder: pyzbar
                (libzbar)</div>
              <div><strong style="color:var(--text)">Stream Video</strong><br>Protokol: RTSP/FFMPEG<br>OpenCV CAP_FFMPEG
              </div>
              <div><strong style="color:var(--text)">Database</strong><br>MySQL<br>Thread-safe dengan lock
              </div>
              <div><strong style="color:var(--text)">API Server</strong><br>Flask 3.x + flask-cors<br>REST JSON API
              </div>
              <div><strong style="color:var(--text)">Versi</strong><br>SIABSEN v2.4<br>Python 3.11+</div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== USER MANAGEMENT PAGE ===== -->
      <section id="page-users" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Admin Management</div>
            <div class="page-sub">Kelola akun pengguna sistem</div>
          </div>
          <button class="btn btn-primary btn-sm" onclick="openAddUserModal()">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">person_add</span>
            Tambah User
          </button>
        </div>

        <!-- Stats Cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px">
          <div class="stat-card">
            <span class="material-symbols-outlined stat-icon">admin_panel_settings</span>
            <div class="stat-label">Admin</div>
            <div class="stat-value" id="stat-admin-count">0</div>
          </div>
          <div class="stat-card">
            <span class="material-symbols-outlined stat-icon">shield_person</span>
            <div class="stat-label">Tim Disiplin</div>
            <div class="stat-value" id="stat-timdis-count">0</div>
          </div>
          <div class="stat-card" style="display:none">
            <span class="material-symbols-outlined stat-icon">school</span>
            <div class="stat-label">Mahasiswa</div>
            <div class="stat-value" id="stat-mahasiswa-count">0</div>
          </div>
          <div class="stat-card">
            <span class="material-symbols-outlined stat-icon">group</span>
            <div class="stat-label">Total Users</div>
            <div class="stat-value" id="stat-total-users">0</div>
          </div>
        </div>

        <!-- Filter dan Pencarian -->
        <div class="panel" style="margin-bottom:16px;padding:14px 20px">
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div style="flex:1;min-width:200px">
              <label class="form-label">Cari Username/Nama</label>
              <input type="text" id="user-search" class="form-input" placeholder="Ketik username atau nama..." style="padding:7px 10px" oninput="filterUsers()">
            </div>
            <div>
              <label class="form-label">Role</label>
              <select id="user-filter-role" class="form-input" style="width:150px;padding:7px 10px" onchange="filterUsers()">
                <option value="">Semua</option>
                <option value="admin">Admin</option>
                <option value="timdis">Tim Disiplin</option>
                <option value="garda">Garda</option>
              </select>
            </div>
            <div>
              <label class="form-label">Status</label>
              <select id="user-filter-status" class="form-input" style="width:120px;padding:7px 10px" onchange="filterUsers()">
                <option value="">Semua</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
              </select>
            </div>
            <div style="align-self:flex-end">
              <button class="btn btn-secondary btn-sm" onclick="resetUserFilter()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span>
                Reset
              </button>
            </div>
          </div>
        </div>

        <!-- Tabel Users -->
        <div class="panel">
          <table class="att-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Email</th>
                <th>Role</th>
                <th>Mahasiswa ID</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="users-tbody">
              <tr>
                <td colspan="7" style="text-align:center;color:var(--muted);padding:30px">Memuat...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

    </main>
  </div>

  <!-- Modal Add/Edit User -->
  <div class="modal-backdrop" id="modal-user">
    <div class="modal">
      <div class="modal-title" id="modal-user-title">Tambah User</div>
      
      <form id="user-form" onsubmit="submitUser(event)">
        <input type="hidden" id="user-id" value="">
        
        <div class="form-row">
          <label class="form-label">Username *</label>
          <input type="text" id="user-username" class="form-input" placeholder="Username" required>
          <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
            Username harus unik, tanpa spasi
          </small>
        </div>

        <div class="form-row" id="password-row">
          <label class="form-label">Password *</label>
          <input type="password" id="user-password" class="form-input" placeholder="Password" autocomplete="new-password" required>
          <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
            Minimal 6 karakter
          </small>
        </div>

        <div class="form-row">
          <label class="form-label">Nama Lengkap *</label>
          <input type="text" id="user-fullname" class="form-input" placeholder="Nama Lengkap" required>
        </div>

        <div class="form-row">
          <label class="form-label">Email</label>
          <input type="email" id="user-email" class="form-input" placeholder="email@example.com">
        </div>

        <div class="form-row">
          <label class="form-label">Role *</label>
          <select id="user-role" class="form-input" onchange="toggleMahasiswaField()" required>
            <option value="">-- Pilih Role --</option>
            <option value="admin">Admin</option>
            <option value="timdis">Tim Disiplin</option>
            <option value="garda">Garda</option>
          </select>
        </div>



        <div class="form-row" id="assigned-kompi-row" style="display:none">
          <label class="form-label">Kompi *</label>
          <select id="user-assigned-kompi" class="form-input">
            <option value="">-- Pilih Kompi --</option>
          </select>
          <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
            Tentukan kompi yang akan di-handle oleh Garda ini
          </small>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-user')">Batal</button>
          <button type="submit" class="btn btn-primary">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">save</span>
            Simpan
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Reset Password -->
  <div class="modal-backdrop" id="modal-reset-password">
    <div class="modal">
      <div class="modal-title">Reset Password</div>
      
      <form id="reset-password-form" onsubmit="submitResetPassword(event)">
        <input type="hidden" id="reset-user-id" value="">
        
        <p style="margin-bottom:20px;color:var(--text-muted)">
          Reset password untuk user: <strong id="reset-username"></strong>
        </p>

        <div class="form-row">
          <label class="form-label">Password Baru *</label>
          <input type="password" id="reset-new-password" class="form-input" placeholder="Password baru" autocomplete="new-password" required>
          <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
            Minimal 6 karakter
          </small>
        </div>

        <div class="form-row">
          <label class="form-label">Konfirmasi Password *</label>
          <input type="password" id="reset-confirm-password" class="form-input" placeholder="Ketik ulang password" autocomplete="new-password" required>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-reset-password')">Batal</button>
          <button type="submit" class="btn btn-primary">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">lock_reset</span>
            Reset Password
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" id="modal-mahasiswa">
    <div class="modal">
      <div class="modal-title" id="modal-mahasiswa-title">Tambah Mahasiswa</div>
      
      <!-- Method Selection -->
      <div class="form-row" style="margin-bottom:20px">
        <label class="form-label">Metode Penambahan *</label>
        <select id="add-method-select" class="form-input" onchange="toggleAddMethod()">
          <option value="manual">Manual - Input Satu per Satu</option>
          <option value="excel">Otomatis - Upload File Excel</option>
        </select>
      </div>

      <!-- Manual Method -->
      <div id="manual-method">
        <div class="qr-result" id="qr-result-box">
          <img id="qr-img-display" class="qr-img" width="200" alt="QR Code">
          <div class="qr-code-label" id="qr-id-label">—</div>
          <p style="font-size:12px;color:var(--muted);margin-top:8px">Cetak dan berikan kartu ini ke mahasiswa</p>
          <button class="btn btn-ghost btn-sm" style="margin-top:12px" onclick="downloadQR()"><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Download QR</button>
        </div>
        <div id="mhs-form">
          <div class="form-row-2">
            <div class="form-row" style="margin:0">
              <label class="form-label">ID Mahasiswa</label>
              <input class="form-input" id="f-id" placeholder="Otomatis" disabled style="background:var(--bg3);color:var(--muted);cursor:not-allowed">
              <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">ID dibuat otomatis oleh sistem</small>
            </div>
            <div class="form-row" style="margin:0">
              <label class="form-label">Nama Lengkap *</label>
              <input class="form-input" id="f-name" placeholder="Nama Mahasiswa">
            </div>
          </div>
          <div style="height:12px"></div>
          <div class="form-row-2">
            <div class="form-row" style="margin:0">
              <label class="form-label">kompi *</label>
              <input class="form-input" id="f-dept" placeholder="A">
            </div>
            <div class="form-row" style="margin:0">
              <label class="form-label">Jurusan *</label>
              <input class="form-input" id="f-pos" placeholder="Teknik Informatika">
            </div>
          </div>
          <div style="height:12px"></div>
          <div class="form-row">
            <label class="form-label">Program Studi (Prodi) *</label>
            <select class="form-input" id="f-prodi">
              <option value="">-- Pilih Prodi --</option>
              <option value="Manajemen Informatika">Manajemen Informatika</option>
              <option value="Teknologi Rekayasa Internet">Teknologi Rekayasa Internet</option>
              <option value="Teknologi Rekayasa Perangkat Lunak">Teknologi Rekayasa Perangkat Lunak</option>
              <option value="Teknologi Rekayasa Elektronika">Teknologi Rekayasa Elektronika</option>
              <option value="Sains Data Terapan">Sains Data Terapan</option>
              <option value="Budidaya Perikanan">Budidaya Perikanan</option>
              <option value="Perikanan Tangkap">Perikanan Tangkap</option>
              <option value="Teknologi Pembenihan Ikan">Teknologi Pembenihan Ikan</option>
              <option value="Teknologi Akuakultur">Teknologi Akuakultur</option>
              <option value="Teknologi Cerdas Penangkapan Ikan">Teknologi Cerdas Penangkapan Ikan</option>
              <option value="Teknik Sumberdaya Lahan dan Lingkungan">Teknik Sumberdaya Lahan dan Lingkungan</option>
              <option value="Teknologi Rekayasa Kontruksi Jalan dan Jembatan">Teknologi Rekayasa Kontruksi Jalan dan Jembatan</option>
              <option value="Teknologi Rekayasa Kimia Industri">Teknologi Rekayasa Kimia Industri</option>
              <option value="Teknologi Rekayasa Otomotif">Teknologi Rekayasa Otomotif</option>
              <option value="Perjalanan Wisata">Perjalanan Wisata</option>
              <option value="Agribisnis Pangan">Agribisnis Pangan</option>
              <option value="Pengelolaan Agribisnis">Pengelolaan Agribisnis</option>
              <option value="Akuntansi Perpajakan">Akuntansi Perpajakan</option>
              <option value="Akuntansi Bisnis Digital">Akuntansi Bisnis Digital</option>
              <option value="Pengelolaan Perhotelan">Pengelolaan Perhotelan</option>
              <option value="Pengelolaan Konvensi dan Acara">Pengelolaan Konvensi dan Acara</option>
              <option value="Bahasa Inggris untuk Komunikasi Bisnis dan Profesional">Bahasa Inggris untuk Komunikasi Bisnis dan Profesional</option>
              <option value="Produksi Media">Produksi Media</option>
              <option value="Bisnis Digital">Bisnis Digital</option>
              <option value="Teknologi Pakan Ternak">Teknologi Pakan Ternak</option>
              <option value="Teknologi Produksi Ternak">Teknologi Produksi Ternak</option>
              <option value="Agribisnis Peternakan">Agribisnis Peternakan</option>
              <option value="Mekanisasi Pertanian">Mekanisasi Pertanian</option>
              <option value="Teknologi Pangan">Teknologi Pangan</option>
              <option value="Pengembangan Produk Agroindustri">Pengembangan Produk Agroindustri</option>
              <option value="Kimia Terapan">Kimia Terapan</option>
              <option value="Teknologi Pangan Halal">Teknologi Pangan Halal</option>
              <option value="Gizi Klinis">Gizi Klinis</option>
              <option value="Produksi Tanaman Perkebunan">Produksi Tanaman Perkebunan</option>
              <option value="Produksi dan Manajemen Industri Perkebunan">Produksi dan Manajemen Industri Perkebunan</option>
              <option value="Pengelolaan Perkebunan Kopi">Pengelolaan Perkebunan Kopi</option>
              <option value="Teknologi Produksi Tanaman Perkebunan">Teknologi Produksi Tanaman Perkebunan</option>
              <option value="Hortikultura">Hortikultura</option>
              <option value="Teknologi Perbenihan">Teknologi Perbenihan</option>
              <option value="Teknologi Produksi Tanaman Pangan">Teknologi Produksi Tanaman Pangan</option>
              <option value="Teknologi Produksi Tanaman Hortikultura">Teknologi Produksi Tanaman Hortikultura</option>
            </select>
          </div>
          <div style="height:12px"></div>
          <div class="form-row">
            <label class="form-label">Tanggal Lahir *</label>
            <input class="form-input" id="f-tgl-lahir" type="date" required>
            <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">Digunakan sebagai password default akun (format ddmmyyyy)</small>
          </div>
          <div style="height:12px"></div>
          <div class="form-row">
            <label class="form-label">Email</label>
            <input class="form-input" id="f-email" type="email" placeholder="mahasiswa@student.ac.id">
          </div>
          <div style="height:12px"></div>
          <div class="form-row-2">
            <div class="form-row" style="margin:0">
              <label class="form-label">No Telp Mahasiswa</label>
              <input class="form-input" id="f-telp-mhs" type="tel" placeholder="08123456789">
            </div>
            <div class="form-row" style="margin:0">
              <label class="form-label">No Telp Orang Tua</label>
              <input class="form-input" id="f-telp-ortu" type="tel" placeholder="08123456789">
            </div>
          </div>
        </div>
      </div>

      <!-- Excel Method -->
      <div id="excel-method" style="display:none">
        <div class="form-section" style="background:var(--primary-light);border:1px solid var(--primary);margin:16px 0">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
            <span class="material-symbols-outlined" style="color:var(--primary);font-size:24px">info</span>
            <div>
              <div style="font-weight:600;color:var(--primary)">Format File Excel</div>
              <div style="font-size:13px;color:var(--text-muted)">Pastikan file Excel memiliki kolom yang sesuai</div>
            </div>
          </div>
          <div style="font-size:13px;color:var(--text-secondary)">
            <strong>Kolom yang diperlukan:</strong><br>
            • <code>mahasiswa_id</code> - ID Mahasiswa (contoh: MHS001)<br>
            • <code>name</code> - Nama Lengkap<br>
            • <code>kompi</code> - kompi (contoh: A, B, C)<br>
            • <code>jurusan</code> - Jurusan<br>
            • <code>prodi</code> - Program Studi (contoh: D3 Teknik Informatika)<br>
            • <code>email</code> - Email (opsional)<br>
            • <code>no_telp_mahasiswa</code> - No Telp Mahasiswa (opsional)<br>
            • <code>no_telp_ortu</code> - No Telp Orang Tua (opsional)
          </div>
          <div style="margin-top:12px">
            <button class="btn btn-ghost btn-sm" onclick="downloadExcelTemplate()">
              <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span>
              Download Template Excel
            </button>
          </div>
        </div>

        <div class="form-row">
          <label class="form-label">Upload File Excel *</label>
          <input type="file" id="excel-file-input" class="form-input" 
                 accept=".xlsx,.xls" onchange="handleExcelFileSelect(event)">
          <div class="form-hint">
            Format: XLSX, XLS · Maksimal 5MB · Pastikan menggunakan template yang benar
          </div>
        </div>

        <div id="excel-preview" style="display:none;margin-top:20px">
          <div class="form-section">
            <div style="font-weight:600;margin-bottom:12px">Preview Data Excel</div>
            <div style="max-height:300px;overflow:auto">
              <table class="att-table" id="excel-preview-table">
                <thead>
                  <tr>
                    <th>ID Mahasiswa</th>
                    <th>Nama</th>
                    <th>kompi</th>
                    <th>Jurusan</th>
                    <th>Email</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="excel-preview-tbody">
                </tbody>
              </table>
            </div>
            <div id="excel-summary" style="margin-top:12px;font-size:13px;color:var(--text-muted)">
            </div>
          </div>
        </div>

        <div id="excel-upload-progress" style="display:none;margin-top:20px">
          <div class="form-section">
            <div style="font-weight:600;margin-bottom:12px">Progress Upload</div>
            <div class="progress-bar">
              <div class="progress-fill" id="upload-progress-fill" style="width:0%"></div>
            </div>
            <div id="upload-status" style="margin-top:8px;font-size:13px;color:var(--text-muted)">
              Memproses...
            </div>
          </div>
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="closeModal('modal-mahasiswa')">Tutup</button>
        <button class="btn btn-primary" id="mhs-submit-btn" onclick="showMahasiswaPreview()">Verifikasi & Simpan</button>
        <button class="btn btn-primary" id="excel-submit-btn" onclick="submitExcelMahasiswa()" style="display:none">
          <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">upload</span>
          Upload Data Excel
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Camera -->
  <div class="modal-backdrop" id="modal-camera">
    <div class="modal">
      <div class="modal-title" id="camera-modal-title">Tambah Webcam</div>
      
      <form id="camera-form" onsubmit="submitCamera(event)">
        <div class="form-row">
          <label class="form-label">ID Kamera *</label>
          <input type="text" id="c-id" class="form-input" placeholder="CAM-01" required>
          <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
            ID unik untuk kamera (contoh: CAM-01, CAM-02)
          </small>
        </div>

        <div class="form-row">
          <label class="form-label">Nama Kamera *</label>
          <input type="text" id="c-name" class="form-input" placeholder="Kamera Pintu Masuk" required>
        </div>

        <div class="form-row">
          <label class="form-label">Index Webcam *</label>
          <select id="c-webcam-index" class="form-input" required>
            <option value="">-- Pilih Webcam --</option>
          </select>
          <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
            Index webcam yang terdeteksi oleh sistem (0, 1, 2, dst)
          </small>
        </div>

        <div class="form-row">
          <label class="form-label">Lokasi</label>
          <input type="text" id="c-loc" class="form-input" placeholder="Pintu Masuk Utama">
        </div>

        <div class="modal-actions">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-camera')">Batal</button>
          <button type="button" class="btn btn-primary" id="camera-submit-btn" onclick="submitCamera(event)">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">save</span>
            Tambah Kamera
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Reject Izin -->
  <div class="modal-backdrop" id="modal-reject-izin">
    <div class="modal" style="max-width:480px">
      <div class="modal-title">Tolak Pengajuan</div>
      <input type="hidden" id="reject-submission-id">
      <div class="form-row" style="margin-top:16px">
        <label class="form-label">Alasan Penolakan *</label>
        <textarea id="reject-reason-input" class="form-input" rows="4"
                  placeholder="Jelaskan alasan penolakan..."></textarea>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="closeModal('modal-reject-izin')">Batal</button>
        <button class="btn btn-danger" onclick="confirmRejectIzin()">
          <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">close</span>
          Tolak Pengajuan
        </button>
      </div>
    </div>
  </div>

  <!-- Modal Detail Bukti -->
  <div class="modal-backdrop" id="modal-bukti">
    <div class="modal" style="max-width:700px">
      <div class="modal-title">Bukti Pengajuan</div>
      <div id="bukti-content" style="margin-top:16px;text-align:center;min-height:200px">
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="closeModal('modal-bukti')">Tutup</button>
      </div>
    </div>
  </div>

  <!-- Modal Browse Models -->
  <!-- Modal Kegiatan -->
  <div class="modal-backdrop" id="modal-kegiatan">
    <div class="modal">
      <div class="modal-title" id="modal-kegiatan-title">Tambah Kegiatan</div>
      <form id="kegiatan-form" onsubmit="submitKegiatan(event)">
        <input type="hidden" id="kegiatan-id" value="">
        <div class="form-row">
          <label class="form-label">Nama Kegiatan *</label>
          <input type="text" id="kegiatan-nama" class="form-input" placeholder="Contoh: Upacara Bendera" required>
        </div>
        <div class="form-row">
          <label class="form-label">Tanggal Pelaksanaan *</label>
          <input type="date" id="kegiatan-tanggal" class="form-input" required>
        </div>
        <div class="form-row" style="display:none">
          <input type="time" id="kegiatan-jam-mulai" class="form-input" value="00:00:00">
          <input type="time" id="kegiatan-jam-selesai" class="form-input" value="23:59:59">
        </div>
        <div class="form-row">
          <label class="form-label">
            <input type="checkbox" id="kegiatan-wajib" checked> Wajib Hadir
          </label>
        </div>
        <div class="form-row" id="kegiatan-status-row" style="display:none">
          <label class="form-label">Status</label>
          <select id="kegiatan-status" class="form-input">
            <option value="1">Aktif</option>
            <option value="0">Nonaktif</option>
          </select>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modal-kegiatan')">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Kegiatan -->
  <div class="modal-backdrop" id="modal-kegiatan">
    ...
  </div>

  <!-- Modal Rekap Kegiatan -->
  <div class="modal-backdrop" id="modal-rekap-kegiatan">
    <div class="modal" style="max-width:900px">
      <div id="modal-rekap-kegiatan-content"></div>
    </div>
  </div>

  <div class="modal-backdrop" id="modal-browse-models">
    <div class="modal" style="max-width:600px">
      <div class="modal-title">Pilih Model YOLO</div>
      <div style="margin-top:16px">
        <div id="models-list" style="max-height:400px;overflow-y:auto">
          <div style="text-align:center;padding:40px;color:var(--muted)">
            <div class="spinner"></div>
            <p style="margin-top:12px">Memuat daftar model...</p>
          </div>
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="closeModal('modal-browse-models')">Batal</button>
      </div>
    </div>
  </div>

  <div id="toast">
    <div class="toast-title" id="toast-title"></div>
    <div class="toast-msg" id="toast-msg"></div>
  </div>

  <!-- Authentication Module -->
  <script src="{{ asset('static/js/auth.js') }}"></script>
  <!-- Main Dashboard Script -->
  <script src="{{ asset('static/js/script.js?v=2.6') }}"></script>
</body>

</html>
