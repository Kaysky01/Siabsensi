<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SIABSEN — Sistem Absensi Cerdas</title>
  <link rel="stylesheet" href="{{ asset('static/css/style.css') }}">
  <link
    href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap"
    rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300;400;500;600;700" rel="stylesheet">
  <!-- jsQR Library for QR Code Detection in Browser -->
  <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
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

      <nav class="nav">
        <div class="nav-section">Utama</div>
        <div class="nav-item active" onclick="showPage('dashboard')">
          <span class="material-symbols-outlined icon">dashboard</span> Dashboard
        </div>
        <div class="nav-item" onclick="showPage('attendance')">
          <span class="material-symbols-outlined icon">check_circle</span> Absensi Hari Ini
          <span class="badge" id="sidebar-present">0</span>
        </div>
        <div class="nav-section">Data</div>
        <div class="nav-item" onclick="showPage('mahasiswa')">
          <span class="material-symbols-outlined icon">badge</span> Mahasiswa
        </div>
        <div class="nav-item" onclick="showPage('history')">
          <span class="material-symbols-outlined icon">history</span> Riwayat
        </div>
        <div class="nav-section">Analisis</div>
        <div class="nav-item" onclick="showPage('video-upload')">
          <span class="material-symbols-outlined icon">video_file</span> Upload Video MP4
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
        @if(auth()->user()->role === 'admin')
        <div class="nav-section">Sistem</div>
        <div class="nav-item" onclick="showPage('users')">
          <span class="material-symbols-outlined icon">manage_accounts</span> User Management
        </div>
        <div class="nav-item" onclick="showPage('settings')">
          <span class="material-symbols-outlined icon">settings</span> Pengaturan
        </div>
        @endif
      </nav>

      <div class="sidebar-footer">
        <span class="status-dot"></span>
        Sistem Aktif<br>
        <span id="current-time" style="font-family:var(--mono);font-size:10px;opacity:.6"></span>
      </div>
    </aside>

    <main class="main">

      <section id="page-dashboard">
        <div class="page-header">
          <div>
            <div class="page-title">Dashboard Absensi</div>
            <div class="page-sub" id="today-label">Loading...</div>
          </div>
          <div class="header-actions">
            <button class="btn btn-ghost btn-sm" onclick="refreshData()"><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span> Refresh</button>
            <form action="{{ route('logout') }}" method="get" style="display:inline;">
              @csrf
              <button type="submit" class="btn btn-ghost btn-sm">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">logout</span> Logout
              </button>
            </form>
          </div>
        </div>

        <div class="stats-grid">
          <!-- Hero Stat - Primary Focus -->
          <div class="stat-card">
            <span class="material-symbols-outlined stat-icon">group</span>
            <div class="stat-label">Total Mahasiswa</div>
            <div class="stat-value" id="s-total">{{ $totalMahasiswaAktif }}</div>
            <div class="stat-delta">Aktif terdaftar dalam sistem</div>
          </div>
          
          <!-- Secondary Stats -->
          <div class="stat-card">
            <span class="material-symbols-outlined stat-icon">task_alt</span>
            <div class="stat-label">Hadir Hari Ini</div>
            <div class="stat-value" id="s-present">{{ $mahasiswaHadirHariIni }}</div>
            <div class="stat-delta"><span class="up" id="s-pct">{{ $persentaseKehadiran }}</span>% kehadiran</div>
          </div>
          <div class="stat-card">
            <span class="material-symbols-outlined stat-icon">person_off</span>
            <div class="stat-label">Tidak Hadir</div>
            <div class="stat-value" id="s-absent">{{ $mahasiswaTidakHadir }}</div>
            <div class="stat-delta">Belum absen masuk</div>
          </div>
          <div class="stat-card">
            <span class="material-symbols-outlined stat-icon">schedule</span>
            <div class="stat-label">Masih di Kantor</div>
            <div class="stat-value" id="s-inoffice">{{ $mahasiswaMasihDiKantor }}</div>
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
                @forelse($absensiTerkini as $absen)
                  <tr>
                    <td>{{ $absen->name }}</td>
                    <td>{{ $absen->jam_masuk }}</td>
                    <td>{{ $absen->jam_keluar }}</td>
                    <td>
                        <span style="color:{{ $absen->status_color }};font-weight:600">
                            {{ $absen->status_label }}
                        </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" style="text-align:center;color:var(--muted);padding:20px">Belum ada absensi hari ini</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div style="display:flex;flex-direction:column;gap:16px">

            <div class="panel">
              <div class="section-header">
                <div class="section-title">Tren 7 Hari</div>
              </div>
              <div class="trend-chart" id="trend-chart" style="height:150px; display:flex; align-items:flex-end; gap:8px; padding-top:10px;">
                @php $maxTren = collect($tren7Hari)->max('jumlah') ?: 1; @endphp
                @foreach($tren7Hari as $tren)
                  @php $height = ($tren['jumlah'] / $maxTren) * 100; @endphp
                  <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; height:100%;">
                    <div style="margin-top:auto; font-size:11px; color:var(--text-muted)">{{ $tren['jumlah'] }}</div>
                    <div style="width:100%; background:var(--primary); border-radius:4px 4px 0 0; height:{{ $height }}%; min-height:4px;"></div>
                    <div style="font-size:10px; color:var(--text-muted); text-align:center">{{ $tren['tanggal'] }}</div>
                  </div>
                @endforeach
              </div>
            </div>

            <div class="panel">
              <div class="section-header">
                <div class="section-title">Per Kelompok</div>
              </div>
              <div id="dept-list" style="display:flex; flex-direction:column; gap:8px;">
                @forelse($perKelompok as $kelompok)
                  <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border);">
                    <span>{{ $kelompok->kelompok ?: 'Tidak Diketahui' }}</span>
                    <span style="font-weight:600;">{{ $kelompok->total }} <span style="font-weight:normal;color:var(--text-muted);font-size:12px">Hadir</span></span>
                  </div>
                @empty
                  <div style="text-align:center;color:var(--muted);padding:10px">Belum ada data</div>
                @endforelse
              </div>
            </div>
          </div>
        </div>
      </section>

      <section id="page-attendance" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Absensi Hari Ini</div>
            <div class="page-sub" id="att-date-label">{{ \Carbon\Carbon::today()->translatedFormat('l, d F Y') }}</div>
          </div>
          <div class="header-actions">
            <input type="date" id="att-date-filter" class="form-input" style="width:160px;padding:7px 10px"
              onchange="filterAttendance(this.value)">
            <button class="btn btn-ghost btn-sm" onclick="exportCSV()"><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Export CSV</button>
          </div>
        </div>
        <div class="panel">
          <table class="att-table" id="full-att-table">
            <thead>
              <tr>
                <th>No</th>
                <th>Mahasiswa</th>
                <th>Kelompok</th>
                <th>Jam Masuk</th>
                <th>Jam Keluar</th>
                <th>Durasi</th>
                <th>Kamera</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="full-att-tbody">
              @forelse($seluruhAbsensiHariIni as $index => $absen)
            <tr>
              <td>{{ $index + 1 }}</td>
              <td>{{ $absen->name }}</td>
              <td>{{ $absen->kelompok ?? '-' }}</td>
              <td>{{ $absen->jam_masuk }}</td>
              <td>{{ $absen->jam_keluar }}</td>
              <td>{{ $absen->durasi }}</td>
              <td>Kamera Sistem</td>

              <td>
                  <span style="color:{{ $absen->status_color }};font-weight:600">
                      {{ $absen->status_label }}
                  </span>
              </td>
            </tr>

            @empty

            <tr>
              <td colspan="8"
                  style="text-align:center;color:var(--muted);padding:30px">
                  Belum ada absensi hari ini
              </td>
            </tr>
            @endforelse
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
              <label class="form-label">Kelompok</label>
              <select id="mhs-filter-kelompok" class="form-input" style="width:120px;padding:7px 10px" onchange="filterMahasiswa()">
                <option value="">Semua</option>
                @foreach($kelompokList as $klp)
                  <option value="{{ $klp }}">{{ $klp }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="form-label">Jurusan</label>
              <select id="mhs-filter-jurusan" class="form-input" style="width:180px;padding:7px 10px" onchange="filterMahasiswa()">
                <option value="">Semua</option>
                @foreach($jurusanList as $jur)
                  <option value="{{ $jur }}">{{ $jur }}</option>
                @endforeach
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
                <th>Kelompok</th>
                <th>Jurusan</th>
                <th>Email</th>
                <th>No Telp Mahasiswa</th>
                <th>No Telp Orang Tua</th>
                <th>QR Code ID</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody id="mhs-tbody">
              @forelse($mahasiswas as $mhs)
                <tr class="mhs-row" data-name="{{ strtolower($mhs->name) }}" data-kelompok="{{ $mhs->kelompok }}" data-jurusan="{{ $mhs->jurusan }}">
                  <td>
                    <div style="font-weight:600">{{ $mhs->name }}</div>
                    <div style="font-size:12px;color:var(--text-muted)">{{ $mhs->id }}</div>
                  </td>
                  <td>{{ $mhs->kelompok ?? '-' }}</td>
                  <td>{{ $mhs->jurusan ?? '-' }}</td>
                  <td>{{ $mhs->email ?? '-' }}</td>
                  <td>{{ $mhs->no_telp_mahasiswa ?? '-' }}</td>
                  <td>{{ $mhs->no_telp_ortu ?? '-' }}</td>
                  <td>{{ $mhs->qr_code_id ?? '-' }}</td>
                  <td>
                    <div style="display:flex;gap:6px">
                      <!-- Tombol QR -->
                      <button class="btn btn-secondary btn-sm"
                              onclick="showQRCode('{{ $mhs->qr_code_id }}','{{ $mhs->name }}')"
                              title="Lihat QR Code">
                        <span class="material-symbols-outlined"
                              style="font-size:16px;vertical-align:middle">
                          qr_code
                        </span>
                      </button>
                      
                      <!-- Tombol Edit -->
                      <button class="btn btn-ghost btn-sm"
                              onclick="editMahasiswa('{{ $mhs->id }}')"
                              title="Edit Mahasiswa">
                        <span class="material-symbols-outlined"
                              style="font-size:16px;vertical-align:middle">
                          edit
                        </span>
                      </button>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" style="text-align:center;color:var(--muted);padding:30px">Belum ada data mahasiswa</td>
                </tr>
              @endforelse
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
            <div style="align-self:flex-end;display:flex;gap:8px">
              <button class="btn btn-primary btn-sm" onclick="filterHistory()">Cari</button>
              <button class="btn btn-secondary btn-sm" onclick="resetHistoryFilter()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span> Reset
              </button>
            </div>
          </div>
        </div>
        <div class="panel">
          <table class="att-table">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Mahasiswa</th>
                <th>Kelompok</th>
                <th>Masuk</th>
                <th>Keluar</th>
                <th>Durasi</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="hist-tbody">
              @forelse($riwayatAbsensi as $absen)
            <tr class="hist-row">
                <td>{{ $absen->tanggal }}</td>
                <td>{{ $absen->name }}</td>
                <td>{{ $absen->kelompok ?? '-' }}</td>
                <td>{{ $absen->jam_masuk }}</td>
                <td>{{ $absen->jam_keluar }}</td>
                <td>{{ $absen->durasi }}</td>
                <td>
                    <span style="color:{{ $absen->status_color }};font-weight:600">
                        {{ $absen->status_label }}
                    </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7"
                    style="text-align:center;color:var(--muted);padding:30px">
                    Belum ada riwayat absensi
                </td>
            </tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </section>

      <section id="page-video-upload" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">Upload dan Deteksi Video MP4</div>
            <div class="page-sub">Unggah video rekaman untuk deteksi QR Code otomatis</div>
          </div>
        </div>

        <div class="panel">
          <div class="section-title" style="margin-bottom:16px">Upload Video</div>
          <div class="form-row">
            <label class="form-label">Pilih Action *</label>
            <select id="video-action-select" class="form-input" style="width:200px">
              <option value="check_in">Check-in (Masuk)</option>
              <option value="check_out">Check-out (Keluar)</option>
            </select>
          </div>
          <div class="form-row">
            <label class="form-label">Pilih File Video (MP4)</label>
            <input type="file" id="video-file-input" class="form-input" accept=".mp4,video/mp4" 
                   onchange="handleVideoFileSelect(event)">
          </div>
          <div id="video-preview-section" style="display:none;margin-top:20px">
            <div class="form-row">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <label class="form-label" style="margin:0">Preview</label>
                <span id="preview-video-info" style="font-family:var(--font-mono);font-size:12px;color:var(--text-muted)"></span>
              </div>
              <div class="video-preview-container">
                <video id="preview-video-player" controls></video>
                <canvas id="preview-canvas-overlay"></canvas>
              </div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:6px">
                <span style="color:#FFD700;font-weight:600">Bounding box kuning</span> = QR Code terdeteksi secara real-time &nbsp;·&nbsp; Deteksi menggunakan jsQR (sama seperti attendance_engine.py)
              </div>
            </div>
            <div style="margin-top:16px;display:flex;gap:12px">
              <button class="btn btn-primary" onclick="uploadAndProcessVideo()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">upload</span>
                Upload & Proses Video
              </button>
              <button class="btn btn-ghost" onclick="cancelVideoUpload()">Batal</button>
            </div>
          </div>
        </div>

        <div id="video-processing-panel" class="panel" style="display:none">
          <div class="section-title" style="margin-bottom:16px">Status Pemrosesan</div>
          <div id="processing-status" style="padding:20px;text-align:center">
            <div class="spinner" style="margin:0 auto 16px"></div>
            <div style="font-size:16px;font-weight:600;color:var(--text)">Memproses video...</div>
            <div id="processing-progress" style="font-size:14px;color:var(--text-muted);margin-top:8px">0%</div>
            
            <!-- Preview dengan bounding box -->
            <div style="margin-top:20px">
              <canvas id="video-preview-canvas" style="max-width:100%;border:2px solid var(--border);border-radius:var(--radius-md);display:none"></canvas>
            </div>
          </div>
        </div>

        <div id="video-success-panel" class="panel" style="display:none">
          <div class="section-header">
            <div>
              <div class="section-title"><span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;color:var(--success)">check_circle</span> Video Berhasil Diproses</div>
              <div class="section-sub" id="success-summary">—</div>
            </div>
            <div style="display:flex;gap:8px">
              <button class="btn btn-primary btn-sm" onclick="showPage('attendance')">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">list_alt</span>
                Lihat Absensi Hari Ini
              </button>
            </div>
          </div>
          <div style="padding:20px;text-align:center">
            <span class="material-symbols-outlined" style="font-size:80px;color:var(--success)">check_circle</span>
            <p style="margin-top:16px;font-size:16px;color:var(--text)">
              Semua data absensi telah tercatat ke database.<br>
              Silakan cek di halaman <strong>"Absensi Hari Ini"</strong>
            </p>
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
            <button class="btn btn-ghost btn-sm" onclick="location.reload()">
              <span class="material-symbols-outlined" style="font-size:16px">refresh</span> Refresh
            </button>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px">
          <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value" id="stat-pending-izin">{{ $totalPendingIzin }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Disetujui</div>
            <div class="stat-value" id="stat-approved-izin">{{ $totalApprovedIzin }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Ditolak</div>
            <div class="stat-value" id="stat-rejected-izin">{{ $totalRejectedIzin }}</div>
          </div>
        </div>

        <div class="panel" style="margin-bottom:16px;padding:14px 20px">
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div style="flex:1;min-width:200px">
              <label class="form-label">Cari Nama Mahasiswa</label>
              <input type="text" id="izin-search" class="form-input" placeholder="Ketik nama mahasiswa..." style="padding:7px 10px" oninput="filterIzinSubmissions()">
            </div>
            <div>
              <label class="form-label">Kelompok</label>
              <select id="izin-filter-kelompok" class="form-input" style="width:120px;padding:7px 10px" onchange="filterIzinSubmissions()">
                <option value="">Semua</option>
                @foreach($kelompokList as $klp)
                  <option value="{{ strtolower($klp) }}">{{ $klp }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="form-label">Status</label>
              <select id="izin-filter-status" class="form-input" style="width:150px;padding:7px 10px" onchange="filterIzinSubmissions()">
                <option value="" selected>Semua</option>
                <option value="pending">Pending</option>
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

        <div class="panel">
          <div style="overflow-x:auto">
            <table class="att-table">
              <thead>
                <tr>
                  <th>Mahasiswa</th>
                  <th>Kelompok</th>
                  <th>Jenis</th>
                  <th>Tanggal</th>
                  <th>Keterangan</th>
                  <th>Bukti</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody id="izin-submissions-table-body">
                @forelse($izinSubmissions as $izin)
                  <tr class="izin-row" 
                      data-name="{{ strtolower($izin->mahasiswa->name ?? '') }}" 
                      data-kelompok="{{ strtolower($izin->mahasiswa->kelompok ?? '') }}" 
                      data-status="{{ strtolower($izin->status) }}">
                    
                    <td>{{ $izin->mahasiswa->name ?? 'Data Terhapus' }}</td>
                    <td>{{ $izin->mahasiswa->kelompok ?? '-' }}</td>
                    <td>
                        <span style="font-weight: 600; color: {{ $izin->jenis == 'izin' ? '#f59e0b' : '#ef4444' }}">
                            {{ ucfirst($izin->jenis) }}
                        </span>
                    </td>
                    <td>{{ \Carbon\Carbon::parse($izin->tanggal)->format('d M Y') }}</td>
                    <td>{{ $izin->keterangan }}</td>
                    <td>
                      @if($izin->bukti)
                        <a href="{{ asset('storage/' . $izin->bukti) }}" target="_blank" style="color: var(--primary);">Lihat Bukti</a>
                      @else
                        -
                      @endif
                    </td>
                    <td>
                      @if($izin->status == 'pending')
                        <span style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Pending</span>
                      @elseif($izin->status == 'approved')
                        <span style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Disetujui</span>
                      @else
                        <span style="background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Ditolak</span>
                      @endif
                    </td>
                    <td>
                      @if($izin->status == 'pending')
                        <form action="{{ url('/admin/izin/'.$izin->id.'/approve') }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-sm" style="background: #22c55e; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;">Terima</button>
                        </form>
                        <form action="{{ url('/admin/izin/'.$izin->id.'/reject') }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-sm" style="background: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;">Tolak</button>
                        </form>
                      @else
                        -
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="8" style="text-align:center;color:var(--muted);padding:30px">
                      Belum ada data pengajuan izin atau sakit.
                    </td>
                  </tr>
                @endforelse
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
              <label class="form-label">Kelompok</label>
              <select id="kehadiran-filter-kelompok" class="form-input" style="width:120px;padding:7px 10px" onchange="filterKehadiranSubmissions()">
                <option value="">Semua</option>
                @foreach($kelompokList as $klp)
                  <option value="{{ $klp }}">{{ $klp }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="form-label">Status</label>
              <select id="kehadiran-filter-status" class="form-input" style="width:150px;padding:7px 10px" onchange="filterKehadiranSubmissions()">
                <option value="" selected>Semua</option>
                <option value="pending">Pending</option>
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
                  <th>Kelompok</th>
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
      @if(auth()->user()->role === 'admin')
      <section id="page-users" style="display:none">
        <div class="page-header">
          <div>
            <div class="page-title">User Management</div>
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
          <div class="stat-card">
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
                <option value="mahasiswa">Mahasiswa</option>
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
      @endif

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
          <input type="password" id="user-password" class="form-input" placeholder="Password" required>
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
            <option value="mahasiswa">Mahasiswa</option>
          </select>
        </div>

        <div class="form-row" id="mahasiswa-id-row" style="display:none">
          <label class="form-label">Mahasiswa ID *</label>
          <select id="user-mahasiswa-id" class="form-input">
            <option value="">-- Pilih Mahasiswa --</option>
          </select>
          <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
            Hanya mahasiswa yang belum punya akun
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
          <input type="password" id="reset-new-password" class="form-input" placeholder="Password baru" required>
          <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block">
            Minimal 6 karakter
          </small>
        </div>

        <div class="form-row">
          <label class="form-label">Konfirmasi Password *</label>
          <input type="password" id="reset-confirm-password" class="form-input" placeholder="Ketik ulang password" required>
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
      <div class="modal-title">QR-CODE</div>
      
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
              <label class="form-label">ID Mahasiswa *</label>
              <input class="form-input" id="f-id" placeholder="MHS001">
            </div>
            <div class="form-row" style="margin:0">
              <label class="form-label">Nama Lengkap *</label>
              <input class="form-input" id="f-name" placeholder="Nama Mahasiswa">
            </div>
          </div>
          <div style="height:12px"></div>
          <div class="form-row-2">
            <div class="form-row" style="margin:0">
              <label class="form-label">Kelompok *</label>
              <input class="form-input" id="f-dept" placeholder="A">
            </div>
            <div class="form-row" style="margin:0">
              <label class="form-label">Jurusan *</label>
              <input class="form-input" id="f-pos" placeholder="Teknik Informatika">
            </div>
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
            • <code>kelompok</code> - Kelompok (contoh: A, B, C)<br>
            • <code>jurusan</code> - Jurusan<br>
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
                    <th>Kelompok</th>
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
        <button class="btn btn-primary" id="mhs-submit-btn" onclick="submitMahasiswa()">Simpan & Generate QR</button>
        <button class="btn btn-primary" id="excel-submit-btn" onclick="submitExcelMahasiswa()" style="display:none">
          <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">upload</span>
          Upload Data Excel
        </button>
      </div>
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

  <!-- Modal Reject Kehadiran -->
  <div class="modal-backdrop" id="modal-reject-kehadiran">
    <div class="modal" style="max-width:480px">
      <div class="modal-title">Tolak Pengajuan Kehadiran</div>
      <input type="hidden" id="reject-kehadiran-id">
      <div class="form-row" style="margin-top:16px">
        <label class="form-label">Alasan Penolakan *</label>
        <textarea id="reject-kehadiran-reason" class="form-input" rows="4"
                  placeholder="Jelaskan alasan penolakan..."></textarea>
      </div>
      <div class="modal-actions">
        <button class="btn btn-ghost" onclick="closeModal('modal-reject-kehadiran')">Batal</button>
        <button class="btn btn-danger" onclick="confirmRejectKehadiran()">
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

  <!-- Main Dashboard Script -->
  <script src="{{ asset('static/js/script.js?v=2.5') }}"></script>
    <script>
      const csrfToken = "{{ csrf_token() }}";
  </script>

  <script src="{{ asset('static/js/admin.js') }}"></script>
  <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
  <!-- <script>
    // Fungsi untuk Export CSV data Absensi sisi Klien (Browser)
    function exportCSV() {
      const table = document.getElementById("full-att-table");
      if (!table) return;

      let csv = [];
      const rows = table.querySelectorAll("tr");
      
      for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
          let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/"/g, '""');
          row.push('"' + data + '"'); 
        }
        csv.push(row.join(","));
      }

      const csvString = csv.join("\n");
      const blob = new Blob([csvString], { type: "text/csv;charset=utf-8;" });
      const link = document.createElement("a");
      
      const dateFilter = document.getElementById("att-date-filter")?.value || new Date().toISOString().split("T")[0];
      link.href = URL.createObjectURL(blob);
      link.download = "Data_Absensi_" + dateFilter + ".csv";
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    // Fungsi Filter Data Mahasiswa di sisi Klien
    function filterMahasiswa() {
      const search = document.getElementById('mhs-search').value.toLowerCase();
      const kelompok = document.getElementById('mhs-filter-kelompok').value;
      const jurusan = document.getElementById('mhs-filter-jurusan').value;
      
      const rows = document.querySelectorAll('.mhs-row');
      rows.forEach(row => {
        const rowName = row.getAttribute('data-name');
        const rowKlp = row.getAttribute('data-kelompok');
        const rowJur = row.getAttribute('data-jurusan');
        
        let match = true;
        if (search && !rowName.includes(search)) match = false;
        if (kelompok && rowKlp !== kelompok) match = false;
        if (jurusan && rowJur !== jurusan) match = false;
        
        row.style.display = match ? '' : 'none';
      });
    }

    function resetMahasiswaFilter() {
      document.getElementById('mhs-search').value = '';
      document.getElementById('mhs-filter-kelompok').value = '';
      document.getElementById('mhs-filter-jurusan').value = '';
      filterMahasiswa();
    }

    // Fungsi Filter Data Riwayat Absensi
    function filterHistory() {
      const start = document.getElementById('hist-start').value;
      const end = document.getElementById('hist-end').value;
      
      const rows = document.querySelectorAll('.hist-row');
      rows.forEach(row => {
        const rowDate = row.getAttribute('data-date');
        let match = true;
        if (start && rowDate < start) match = false;
        if (end && rowDate > end) match = false;
        row.style.display = match ? '' : 'none';
      });
    }

    function resetHistoryFilter() {
      document.getElementById('hist-start').value = '';
      document.getElementById('hist-end').value = '';
      filterHistory();
    }

    // --- LOGIKA UPLOAD VIDEO MP4 ---
    let selectedVideoFile = null;

    function handleVideoFileSelect(event) {
      const file = event.target.files[0];
      if (file && file.type === 'video/mp4') {
        selectedVideoFile = file;
        const videoPlayer = document.getElementById('preview-video-player');
        const fileURL = URL.createObjectURL(file);
        videoPlayer.src = fileURL;
        
        document.getElementById('video-preview-section').style.display = 'block';
        document.getElementById('preview-video-info').textContent = file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)';
      } else {
        alert('Silakan pilih file video MP4 yang valid.');
        event.target.value = '';
        selectedVideoFile = null;
        document.getElementById('video-preview-section').style.display = 'none';
      }
    }

    function cancelVideoUpload() {
      document.getElementById('video-file-input').value = '';
      selectedVideoFile = null;
      document.getElementById('video-preview-section').style.display = 'none';
      const videoPlayer = document.getElementById('preview-video-player');
      videoPlayer.pause();
      videoPlayer.src = '';
    }

    async function uploadAndProcessVideo() {
      if (!selectedVideoFile) {
        alert('Pilih video terlebih dahulu.');
        return;
      }

      const action = document.getElementById('video-action-select').value;
      const formData = new FormData();
      formData.append('video', selectedVideoFile);
      formData.append('action', action);
      formData.append('_token', '{{ csrf_token() }}');

      document.getElementById('video-preview-section').style.display = 'none';
      document.getElementById('video-processing-panel').style.display = 'block';
      document.getElementById('processing-progress').textContent = 'Mengupload video...';

      try {
        const response = await fetch('{{ url("/admin/upload-video") }}', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          document.getElementById('video-processing-panel').style.display = 'none';
          document.getElementById('video-success-panel').style.display = 'block';
          document.getElementById('success-summary').textContent = 'Video ' + selectedVideoFile.name + ' (' + action + ') berhasil diunggah dan diproses.';
          cancelVideoUpload();
        } else {
          alert('Gagal memproses video: ' + result.message);
          document.getElementById('video-processing-panel').style.display = 'none';
          document.getElementById('video-preview-section').style.display = 'block';
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat mengupload video.');
        document.getElementById('video-processing-panel').style.display = 'none';
        document.getElementById('video-preview-section').style.display = 'block';
      }
    }

    // --- LOGIKA VERIFIKASI IZIN / SAKIT ---
    async function loadIzinSubmissions() {
      const search = document.getElementById('izin-search')?.value || '';
      const kelompok = document.getElementById('izin-filter-kelompok')?.value || '';
      const status = document.getElementById('izin-filter-status')?.value || '';
      
      const tbody = document.getElementById('izin-submissions-table-body');
      if (!tbody) return;
      
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px">Memuat data...</td></tr>';
      
      try {
        const queryParams = new URLSearchParams({ search, kelompok, status });
        const response = await fetch('{{ url("/admin/izin-submissions") }}?' + queryParams.toString());
        const result = await response.json();
        
        if (result.success) {
          document.getElementById('stat-pending-izin').textContent = result.stats.pending;
          document.getElementById('stat-approved-izin').textContent = result.stats.approved;
          document.getElementById('stat-rejected-izin').textContent = result.stats.rejected;
          
          const sidebarIzin = document.getElementById('sidebar-pending-izin');
          if (sidebarIzin) {
            sidebarIzin.textContent = result.stats.pending;
            sidebarIzin.style.display = result.stats.pending > 0 ? '' : 'none';
          }

          if (result.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px">Tidak ada pengajuan ditemukan</td></tr>';
            return;
          }
          
          let html = '';
          result.data.forEach(item => {
            let statusBadge = '';
            if (item.status === 'pending') statusBadge = '<span class="badge badge-warning">Pending</span>';
            else if (item.status === 'approved') statusBadge = '<span style="background:var(--success-light);color:var(--success);padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600">Disetujui</span>';
            else if (item.status === 'rejected') statusBadge = '<span style="background:var(--danger-light);color:var(--danger);padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600">Ditolak</span>';
            
            let aksiBtns = '';
            if (item.status === 'pending') {
              aksiBtns = `
                <button class="btn btn-primary btn-sm" onclick="approveIzin(${item.id})" title="Setujui">
                  <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">check</span>
                </button>
                <button class="btn btn-danger btn-sm" onclick="openRejectIzin(${item.id})" title="Tolak">
                  <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">close</span>
                </button>
              `;
            } else {
              aksiBtns = '<span style="font-size:12px;color:var(--text-muted)">Selesai</span>';
            }
            
            let tanggal = item.date || (item.created_at ? item.created_at.split('T')[0] : '-');
            let jenis = item.submission_type ? item.submission_type.toUpperCase() : 'IZIN';

            html += `
              <tr>
                <td><div style="font-weight:600">${item.mahasiswa_name}</div></td>
                <td>${item.kelompok || '-'}</td>
                <td><span style="font-size:12px;font-weight:600">${jenis}</span></td>
                <td>${tanggal}</td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${item.keterangan || ''}">${item.keterangan || '-'}</td>
                <td>
                  ${item.bukti_path ? `<button class="btn btn-ghost btn-sm" onclick="viewBukti('${item.bukti_path}')">Lihat Bukti</button>` : '-'}
                </td>
                <td>${statusBadge}</td>
                <td><div style="display:flex;gap:4px">${aksiBtns}</div></td>
              </tr>
            `;
          });
          tbody.innerHTML = html;
        }
      } catch (error) {
        console.error('Error fetching izin:', error);
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--danger);padding:30px">Gagal memuat data</td></tr>';
      }
    }

    function filterIzinSubmissions() { loadIzinSubmissions(); }

    function resetIzinFilter() {
      if(document.getElementById('izin-search')) document.getElementById('izin-search').value = '';
      if(document.getElementById('izin-filter-kelompok')) document.getElementById('izin-filter-kelompok').value = '';
      if(document.getElementById('izin-filter-status')) document.getElementById('izin-filter-status').value = '';
      loadIzinSubmissions();
    }

    function viewBukti(url) {
      const baseUrl = "{{ asset('storage') }}";
      const fullUrl = url.startsWith('public/') ? baseUrl + '/' + url.substring(7) : url;
      const ext = fullUrl.split('.').pop().toLowerCase();
      const contentDiv = document.getElementById('bukti-content');
      
      if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
        contentDiv.innerHTML = `<img src="${fullUrl}" style="max-width:100%;max-height:60vh;border-radius:8px">`;
      } else if (ext === 'pdf') {
        contentDiv.innerHTML = `<iframe src="${fullUrl}" style="width:100%;height:60vh;border:none"></iframe>`;
      } else {
        contentDiv.innerHTML = `<a href="${fullUrl}" target="_blank" class="btn btn-primary">Download File</a>`;
      }
      const modal = document.getElementById('modal-bukti');
      if (modal) modal.style.display = 'flex';
    }

    async function approveIzin(id) {
      if (!confirm('Anda yakin ingin menyetujui pengajuan ini?')) return;
      try {
        const response = await fetch('{{ url("/admin/izin-submissions") }}/' + id + '/verify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
          body: JSON.stringify({ status: 'approved' })
        });
        const result = await response.json();
        if (result.success) {
          loadIzinSubmissions();
        } else {
          alert('Gagal: ' + result.message);
        }
      } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan.'); }
    }

    function openRejectIzin(id) {
      document.getElementById('reject-submission-id').value = id;
      document.getElementById('reject-reason-input').value = '';
      const modal = document.getElementById('modal-reject-izin');
      if(modal) modal.style.display = 'flex';
    }

    async function confirmRejectIzin() {
      const id = document.getElementById('reject-submission-id').value;
      const reason = document.getElementById('reject-reason-input').value;
      if (!reason.trim()) return alert('Alasan penolakan wajib diisi!');
      
      try {
        const response = await fetch('{{ url("/admin/izin-submissions") }}/' + id + '/verify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
          body: JSON.stringify({ status: 'rejected', reject_reason: reason })
        });
        const result = await response.json();
        if (result.success) {
          const modal = document.getElementById('modal-reject-izin');
          if(modal) modal.style.display = 'none';
          loadIzinSubmissions();
        } else {
          alert('Gagal: ' + result.message);
        }
      } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan.'); }
    }

    // --- LOGIKA VERIFIKASI KEHADIRAN ---
    async function loadKehadiranSubmissions() {
      const search = document.getElementById('kehadiran-search')?.value || '';
      const kelompok = document.getElementById('kehadiran-filter-kelompok')?.value || '';
      const status = document.getElementById('kehadiran-filter-status')?.value || '';
      
      const tbody = document.getElementById('kehadiran-submissions-table-body');
      if (!tbody) return;
      
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--muted);padding:30px">Memuat data...</td></tr>';
      
      try {
        const queryParams = new URLSearchParams({ search, kelompok, status });
        const response = await fetch('{{ url("/admin/kehadiran-submissions") }}?' + queryParams.toString());
        const result = await response.json();
        
        if (result.success) {
          document.getElementById('stat-pending-kehadiran').textContent = result.stats.pending;
          document.getElementById('stat-approved-kehadiran').textContent = result.stats.approved;
          document.getElementById('stat-rejected-kehadiran').textContent = result.stats.rejected;
          
          const sidebarKehadiran = document.getElementById('sidebar-pending-kehadiran');
          if (sidebarKehadiran) {
             sidebarKehadiran.textContent = result.stats.pending;
             sidebarKehadiran.style.display = result.stats.pending > 0 ? '' : 'none';
          }

          if (result.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--muted);padding:30px">Tidak ada pengajuan ditemukan</td></tr>';
            return;
          }
          
          let html = '';
          result.data.forEach(item => {
            let statusBadge = '';
            if (item.status === 'pending') statusBadge = '<span class="badge badge-warning">Pending</span>';
            else if (item.status === 'approved') statusBadge = '<span style="background:var(--success-light);color:var(--success);padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600">Disetujui</span>';
            else if (item.status === 'rejected') statusBadge = '<span style="background:var(--danger-light);color:var(--danger);padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600">Ditolak</span>';
            
            let aksiBtns = '';
            if (item.status === 'pending') {
              aksiBtns = `
                <button class="btn btn-primary btn-sm" onclick="approveKehadiran(${item.id})" title="Setujui">
                  <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">check</span>
                </button>
                <button class="btn btn-danger btn-sm" onclick="openRejectKehadiran(${item.id})" title="Tolak">
                  <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">close</span>
                </button>
              `;
            } else {
              aksiBtns = '<span style="font-size:12px;color:var(--text-muted)">Selesai</span>';
            }
            
            let tanggal = item.date || (item.created_at ? item.created_at.split('T')[0] : '-');

            html += `
              <tr>
                <td><div style="font-weight:600">${item.mahasiswa_name}</div></td>
                <td>${item.kelompok || '-'}</td>
                <td>${tanggal}</td>
                <td>${item.check_in_time || '-'}</td>
                <td>${item.check_out_time || '-'}</td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${item.keterangan || ''}">${item.keterangan || '-'}</td>
                <td>
                  ${item.bukti_path ? `<button class="btn btn-ghost btn-sm" onclick="viewBukti('${item.bukti_path}')">Lihat Bukti</button>` : '-'}
                </td>
                <td>${statusBadge}</td>
                <td><div style="display:flex;gap:4px">${aksiBtns}</div></td>
              </tr>
            `;
          });
          tbody.innerHTML = html;
        }
      } catch (error) {
        console.error('Error fetching kehadiran:', error);
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--danger);padding:30px">Gagal memuat data</td></tr>';
      }
    }

    function filterKehadiranSubmissions() { loadKehadiranSubmissions(); }

    function resetKehadiranFilter() {
      if(document.getElementById('kehadiran-search')) document.getElementById('kehadiran-search').value = '';
      if(document.getElementById('kehadiran-filter-kelompok')) document.getElementById('kehadiran-filter-kelompok').value = '';
      if(document.getElementById('kehadiran-filter-status')) document.getElementById('kehadiran-filter-status').value = '';
      loadKehadiranSubmissions();
    }

    async function approveKehadiran(id) {
      if (!confirm('Anda yakin ingin menyetujui pengajuan kehadiran ini? Data absensi akan otomatis diperbarui.')) return;
      try {
        const response = await fetch('{{ url("/admin/kehadiran-submissions") }}/' + id + '/verify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
          body: JSON.stringify({ status: 'approved' })
        });
        const result = await response.json();
        if (result.success) {
          loadKehadiranSubmissions();
        } else {
          alert('Gagal: ' + result.message);
        }
      } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan.'); }
    }

    function openRejectKehadiran(id) {
      document.getElementById('reject-kehadiran-id').value = id;
      document.getElementById('reject-kehadiran-reason').value = '';
      const modal = document.getElementById('modal-reject-kehadiran');
      if(modal) modal.style.display = 'flex';
    }

    async function confirmRejectKehadiran() {
      const id = document.getElementById('reject-kehadiran-id').value;
      const reason = document.getElementById('reject-kehadiran-reason').value;
      if (!reason.trim()) return alert('Alasan penolakan wajib diisi!');
      
      try {
        const response = await fetch('{{ url("/admin/kehadiran-submissions") }}/' + id + '/verify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
          body: JSON.stringify({ status: 'rejected', reject_reason: reason })
        });
        const result = await response.json();
        if (result.success) {
          const modal = document.getElementById('modal-reject-kehadiran');
          if(modal) modal.style.display = 'none';
          loadKehadiranSubmissions();
        } else {
          alert('Gagal: ' + result.message);
        }
      } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan.'); }
    }

    // --- LOGIKA USER MANAGEMENT ---
    async function loadUsers() {
      const search = document.getElementById('user-search')?.value || '';
      const role = document.getElementById('user-filter-role')?.value || '';
      const status = document.getElementById('user-filter-status')?.value || '';

      const tbody = document.getElementById('users-tbody');
      if (!tbody) return;

      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">Memuat data...</td></tr>';

      try {
        const queryParams = new URLSearchParams({ search, role, status });
        const response = await fetch('{{ url("/admin/users") }}?' + queryParams.toString());
        const result = await response.json();

        if (result.success) {
          document.getElementById('stat-admin-count').textContent = result.stats.admin;
          document.getElementById('stat-timdis-count').textContent = result.stats.timdis;
          document.getElementById('stat-mahasiswa-count').textContent = result.stats.mahasiswa;
          document.getElementById('stat-total-users').textContent = result.stats.total;

          if (result.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">Tidak ada user ditemukan</td></tr>';
            return;
          }

          let html = '';
          result.data.forEach(user => {
            const initials = (user.full_name || 'U').split(' ').map(w => w[0] || '').join('').slice(0, 2).toUpperCase();
            const colors = ['#4f7cff', '#22d3a0', '#f5a623', '#ff6b6b', '#a78bfa'];
            const colorIndex = user.id % colors.length;
            const color = colors[colorIndex];
            
            let statusBadge = user.is_active ? '<span class="badge badge-success" style="background:var(--success-light);color:var(--success);padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600">Aktif</span>' : '<span class="badge badge-danger" style="background:var(--danger-light);color:var(--danger);padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600">Nonaktif</span>';
            let lastLogin = user.last_login ? user.last_login.split('T').join(' ').substring(0, 16) : '-';
            let mhsId = user.mahasiswa_id || '-';

            let toggleBtn = user.is_active 
              ? `<button class="btn btn-danger btn-sm" onclick="toggleUserStatus(${user.id}, false)" title="Nonaktifkan">
                  <span class="material-symbols-outlined" style="font-size:16px">block</span>
                </button>`
              : `<button class="btn btn-primary btn-sm" onclick="toggleUserStatus(${user.id}, true)" title="Aktifkan">
                  <span class="material-symbols-outlined" style="font-size:16px">check_circle</span>
                </button>`;

            let deleteBtn = `
                <button class="btn btn-danger btn-sm" onclick="deleteUser(${user.id})" title="Hapus">
                  <span class="material-symbols-outlined" style="font-size:16px">delete</span>
                </button>
            `;

            html += `
              <tr>
                <td>
                  <div class="mahasiswa-cell" style="display:flex;gap:12px;align-items:center">
                    <div class="avatar" style="background:${color}22;color:${color};width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%;font-weight:600;font-size:14px">${initials}</div>
                    <div>
                      <div class="mhs-name" style="font-weight:600">${user.full_name}</div>
                      <div class="mhs-dept" style="font-family:var(--mono);font-size:12px;color:var(--text-muted)">@${user.username}</div>
                    </div>
                  </div>
                </td>
                <td>${user.email || '-'}</td>
                <td><span style="text-transform:capitalize">${user.role}</span></td>
                <td><span style="font-family:var(--font-mono);font-size:12px">${mhsId}</span></td>
                <td>${statusBadge}</td>
                <td>${lastLogin}</td>
                <td>
                  <div style="display:flex;gap:4px">
                    <button class="btn btn-ghost btn-sm" onclick='editUser(${JSON.stringify(user).replace(/'/g, "&apos;").replace(/"/g, "&quot;")})' title="Edit">
                      <span class="material-symbols-outlined" style="font-size:16px">edit</span>
                    </button>
                    <button class="btn btn-ghost btn-sm" onclick="openResetPassword(${user.id}, '${user.username}')" title="Reset Password">
                      <span class="material-symbols-outlined" style="font-size:16px">lock_reset</span>
                    </button>
                    ${toggleBtn}
                    ${deleteBtn}
                  </div>
                </td>
              </tr>
            `;
          });
          tbody.innerHTML = html;
        }
      } catch (error) {
        console.error('Error fetching users:', error);
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--danger);padding:30px">Gagal memuat data</td></tr>';
      }
    }

    function filterUsers() { loadUsers(); }

    function resetUserFilter() {
      if(document.getElementById('user-search')) document.getElementById('user-search').value = '';
      if(document.getElementById('user-filter-role')) document.getElementById('user-filter-role').value = '';
      if(document.getElementById('user-filter-status')) document.getElementById('user-filter-status').value = '';
      loadUsers();
    }

    async function loadMahasiswaOptions() {
      const select = document.getElementById('user-mahasiswa-id');
      select.innerHTML = '<option value="">Memuat...</option>';
      try {
        const response = await fetch('{{ url("/admin/mahasiswa-options") }}');
        const result = await response.json();
        if (result.success) {
          let html = '<option value="">-- Pilih Mahasiswa --</option>';
          result.data.forEach(mhs => {
            html += `<option value="${mhs.id}">${mhs.id} - ${mhs.name} (${mhs.kelompok || '-'})</option>`;
          });
          select.innerHTML = html;
        }
      } catch (error) {
        console.error('Error fetching mhs options:', error);
      }
    }

    function toggleMahasiswaField() {
      const role = document.getElementById('user-role').value;
      const mhsRow = document.getElementById('mahasiswa-id-row');
      const mhsSelect = document.getElementById('user-mahasiswa-id');
      if (role === 'mahasiswa') {
        mhsRow.style.display = 'block';
        mhsSelect.required = true;
        if(mhsSelect.options.length <= 1) loadMahasiswaOptions();
      } else {
        mhsRow.style.display = 'none';
        mhsSelect.required = false;
        mhsSelect.value = '';
      }
    }

    function openAddUserModal() {
      document.getElementById('user-id').value = '';
      document.getElementById('user-form').reset();
      document.getElementById('modal-user-title').textContent = 'Tambah User';
      document.getElementById('password-row').style.display = 'block';
      document.getElementById('user-password').required = true;
      toggleMahasiswaField();
      const modal = document.getElementById('modal-user');
      if(modal) modal.style.display = 'flex';
    }

    function editUser(user) {
      document.getElementById('user-id').value = user.id;
      document.getElementById('user-username').value = user.username;
      document.getElementById('user-fullname').value = user.full_name;
      document.getElementById('user-email').value = user.email || '';
      document.getElementById('user-role').value = user.role;
      
      document.getElementById('modal-user-title').textContent = 'Edit User';
      document.getElementById('password-row').style.display = 'none';
      document.getElementById('user-password').required = false;
      
      toggleMahasiswaField();
      
      if (user.role === 'mahasiswa') {
        const mhsSelect = document.getElementById('user-mahasiswa-id');
        const optionExists = Array.from(mhsSelect.options).some(opt => opt.value === user.mahasiswa_id);
        if (!optionExists && user.mahasiswa_id) {
          mhsSelect.innerHTML += `<option value="${user.mahasiswa_id}">${user.mahasiswa_id} (Saat ini)</option>`;
        }
        mhsSelect.value = user.mahasiswa_id;
      }
      
      const modal = document.getElementById('modal-user');
      if(modal) modal.style.display = 'flex';
    }

    async function submitUser(event) {
      event.preventDefault();
      const id = document.getElementById('user-id').value;
      const data = {
        username: document.getElementById('user-username').value,
        full_name: document.getElementById('user-fullname').value,
        email: document.getElementById('user-email').value,
        role: document.getElementById('user-role').value,
        mahasiswa_id: document.getElementById('user-mahasiswa-id').value
      };
      if (!id) data.password = document.getElementById('user-password').value;
      
      const url = id ? `{{ url("/admin/users") }}/${id}` : '{{ url("/admin/users") }}';
      const method = id ? 'PUT' : 'POST';
      
      try {
        const response = await fetch(url, {
          method: method,
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
          body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) {
          closeModal('modal-user');
          loadUsers();
        } else {
          alert(result.message);
        }
      } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan'); }
    }

    function openResetPassword(id, username) {
      document.getElementById('reset-user-id').value = id;
      document.getElementById('reset-username').textContent = username;
      document.getElementById('reset-password-form').reset();
      const modal = document.getElementById('modal-reset-password');
      if(modal) modal.style.display = 'flex';
    }

    async function submitResetPassword(event) {
      event.preventDefault();
      const id = document.getElementById('reset-user-id').value;
      const password = document.getElementById('reset-new-password').value;
      const confirm = document.getElementById('reset-confirm-password').value;
      
      if (password !== confirm) return alert('Konfirmasi password tidak cocok!');
      
      try {
        const response = await fetch(`{{ url("/admin/users") }}/${id}/reset-password`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
          body: JSON.stringify({ password: password, password_confirmation: confirm })
        });
        const result = await response.json();
        if (result.success) {
          closeModal('modal-reset-password');
          alert('Password berhasil direset!');
        } else {
          alert(result.message);
        }
      } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan'); }
    }

    async function toggleUserStatus(id, activate) {
      const action = activate ? 'Aktifkan' : 'Nonaktifkan';
      if (!confirm(`Anda yakin ingin ${action.toLowerCase()} user ini?`)) return;
      try {
        const response = await fetch(`{{ url("/admin/users") }}/${id}/toggle-status`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        });
        const result = await response.json();
        if (result.success) {
          loadUsers();
        } else {
          alert(result.message);
        }
      } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan.'); }
    }

    async function deleteUser(id) {
      if (!confirm('Anda yakin ingin menghapus user ini secara permanen?')) return;
      try {
        const response = await fetch(`{{ url("/admin/users") }}/${id}`, {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        });
        const result = await response.json();
        if (result.success) {
          loadUsers();
        } else {
          alert(result.message);
        }
      } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan.'); }
    }

    // Otomatis load data ketika halaman ini dibuka atau dijalankan pertama kali
    document.addEventListener('DOMContentLoaded', () => {
      loadIzinSubmissions();
      loadKehadiranSubmissions();
      @if(auth()->user()->role === 'admin')
      loadUsers();
      @endif
    });
  </script> -->

  <!-- MODAL QR CODE -->
   <div id="modal-qr"
        style="
            display:none;
            position:fixed;
            inset:0;
            background:rgba(0,0,0,.6);
            z-index:99999;
            align-items:center;
            justify-content:center;
        ">

        <div style="
            background:white;
            padding:24px;
            border-radius:16px;
            width:340px;
            text-align:center;
        ">

            <h3 id="qr-title">QR Code</h3>

            <canvas id="qr-canvas"></canvas>

            <button class="btn btn-primary"
                    style="margin-top:16px"
                    onclick="closeQRModal()">
                Tutup
            </button>

        </div>

    </div>
</body>
</html>