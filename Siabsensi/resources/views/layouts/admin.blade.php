<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'SIABSEN — Sistem Absensi Cerdas')</title>
  <link rel="icon" type="image/png" href="{{ asset('static/img/logo.png') }}">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300;400;500;600;700" rel="stylesheet">
  @vite('resources/css/admin.css')
  <style>
    .main { 
      transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1), max-width 0.3s !important; 
      max-width: 100% !important; 
    }
    .main.expanded { 
      margin-left: 0 !important; 
    }
    .sidebar.collapsed { 
      transform: translateX(-100%) !important; 
    }
  </style>
</head>

<body>

  <div class="app">
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <aside class="sidebar">
      <div class="sidebar-logo" style="display:flex; justify-content:space-between; align-items:center;">
        <div class="logo-mark">
          <img src="{{ asset('static/img/logo.png') }}" alt="Logo" class="logo-icon">
          <div>
            <div class="logo-text">SIABSEN</div>
            <div class="logo-sub">v3.0</div>
          </div>
        </div>
        <button id="sidebar-close" style="background:transparent; border:none; cursor:pointer; color:var(--text-muted); display:flex; padding:4px;" class="mobile-only-btn">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <nav class="nav">
        @php $user = Auth::user(); @endphp

        @if($user->role !== 'garda')
        <div class="nav-section">Utama</div>
        <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">dashboard</span> Dashboard
        </a>
        <a href="{{ route('admin.attendance') }}" class="nav-item {{ request()->routeIs('admin.attendance') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">check_circle</span> Absensi Hari Ini
        </a>

        @if($user->role === 'admin')
        <div class="nav-section">Data Utama</div>
        <a href="{{ route('admin.master.jurusan-prodi') }}" class="nav-item {{ request()->routeIs('admin.master.jurusan-prodi') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">school</span> Jurusan & Prodi
        </a>
        <a href="{{ route('admin.master.kompi') }}" class="nav-item {{ request()->routeIs('admin.master.kompi') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">corporate_fare</span> Data Kompi
        </a>

        <div class="nav-section">Data Mahasiswa</div>
        <a href="{{ route('admin.mahasiswa') }}" class="nav-item {{ request()->routeIs('admin.mahasiswa') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">badge</span> Mahasiswa
        </a>
        <a href="{{ route('admin.kompi-management') }}" class="nav-item {{ request()->routeIs('admin.kompi-management') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">groups</span> Pengaturan Kompi
        </a>
        @endif
        
        @if(in_array($user->role, ['admin', 'timdis']))
        <div class="nav-section">Riwayat</div>
        <a href="{{ route('admin.history') }}" class="nav-item {{ request()->routeIs('admin.history') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">history</span> Riwayat
        </a>
        @endif
        @endif

        @if($user->role === 'garda')
        <div class="nav-section">Data</div>
        <a href="{{ route('admin.mahasiswa-saya') }}" class="nav-item {{ request()->routeIs('admin.mahasiswa-saya') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">visibility</span> Mahasiswa Saya
        </a>
        @endif

        @if($user->role !== 'garda')
        <div class="nav-section">Kegiatan</div>
        @if($user->role === 'admin')
        <a href="{{ route('admin.kegiatan') }}" class="nav-item {{ request()->routeIs('admin.kegiatan') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">event</span> Kelola Kegiatan
        </a>
        @endif
        <a href="{{ route('admin.monitoring-kegiatan') }}" class="nav-item {{ request()->routeIs('admin.monitoring-kegiatan') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">monitoring</span> Monitoring Kegiatan
        </a>

        @if($user->role === 'admin')
        <div class="nav-section">Analisis</div>
        <a href="{{ route('admin.kelulusan') }}" class="nav-item {{ request()->routeIs('admin.kelulusan') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">assignment_turned_in</span> Laporan Kelulusan
        </a>
        @endif
        @endif

        <div class="nav-section">Verifikasi Pengajuan</div>
        <a href="{{ route('admin.izin-timdis') }}" class="nav-item {{ request()->routeIs('admin.izin-timdis') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">fact_check</span> Verifikasi Izin/Sakit
          @if(($pendingIzin ?? 0) > 0)
          <span class="badge badge-warning">{{ $pendingIzin }}</span>
          @endif
        </a>
        <a href="{{ route('admin.kehadiran-timdis') }}" class="nav-item {{ request()->routeIs('admin.kehadiran-timdis') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">how_to_reg</span> Verifikasi Kehadiran
          @if(($pendingKehadiran ?? 0) > 0)
          <span class="badge badge-warning">{{ $pendingKehadiran }}</span>
          @endif
        </a>

        @if($user->role === 'admin')
        <div class="nav-section">Sistem</div>
        <a href="{{ route('admin.schedule.index') }}" class="nav-item {{ request()->routeIs('admin.schedule.*') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">schedule</span> Jadwal Absensi
        </a>
        <a href="{{ route('admin.late-report') }}" class="nav-item {{ request()->routeIs('admin.late-report*') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">schedule_send</span> Laporan Keterlambatan
        </a>
        <a href="{{ route('admin.users') }}" class="nav-item {{ request()->routeIs('admin.users') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">manage_accounts</span> Admin Management
        </a>
        <a href="{{ route('admin.settings') }}" class="nav-item {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">settings</span> Pengaturan
        </a>
        @endif

        <div class="nav-section">Keluar</div>
        <a href="{{ route('logout') }}" class="nav-item" style="color: #ff6b6b;">
          <span class="material-symbols-outlined icon" style="color: #ff6b6b;">logout</span> Logout
        </a>
      </nav>

      <div class="sidebar-footer">
        <span class="status-dot"></span>
        {{ $user->full_name }} ({{ ucfirst($user->role) }})<br>
        <span style="font-family:var(--mono);font-size:10px;opacity:.6">{{ now()->format('d M Y') }}</span>
      </div>
    </aside>

    <main class="main">
      <div class="top-navbar">
        <button id="sidebar-toggle" title="Toggle Menu">
          <span class="material-symbols-outlined">menu</span>
        </button>
      </div>

      {{-- Flash Messages --}}
      @if(session('success'))
      <div style="background:var(--success-light);color:var(--success);padding:12px 20px;border-radius:var(--radius-sm);margin-bottom:20px;border:1px solid var(--success);font-weight:600;display:flex;align-items:center;gap:8px;">
        <span class="material-symbols-outlined" style="font-size:18px">check_circle</span>
        {{ session('success') }}
      </div>
      @endif

      @if(session('error'))
      <div style="background:var(--danger-light);color:var(--danger);padding:12px 20px;border-radius:var(--radius-sm);margin-bottom:20px;border:1px solid var(--danger);font-weight:600;display:flex;align-items:center;gap:8px;">
        <span class="material-symbols-outlined" style="font-size:18px">error</span>
        {{ session('error') }}
      </div>
      @endif

      @if($errors->any())
      <div style="background:var(--danger-light);color:var(--danger);padding:12px 20px;border-radius:var(--radius-sm);margin-bottom:20px;border:1px solid var(--danger);font-weight:600;">
        <ul style="margin:0;padding-left:16px">
          @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
      @endif

      @yield('content')
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const sidebar = document.querySelector('.sidebar');
      const main = document.querySelector('.main');
      const toggleBtn = document.getElementById('sidebar-toggle');
      const closeBtn = document.getElementById('sidebar-close');
      const overlay = document.getElementById('sidebar-overlay');
      const icon = toggleBtn.querySelector('span');

      function toggleSidebar() {
        const isMobile = window.innerWidth <= 992;
        
        if (isMobile) {
          sidebar.classList.toggle('open');
          if (sidebar.classList.contains('open')) {
            overlay.classList.add('show');
          } else {
            overlay.classList.remove('show');
          }
        } else {
          sidebar.classList.toggle('collapsed');
          main.classList.toggle('expanded');
        }
      }

      function closeSidebarMobile() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
      }

      toggleBtn.addEventListener('click', toggleSidebar);
      closeBtn.addEventListener('click', closeSidebarMobile);
      overlay.addEventListener('click', closeSidebarMobile);

      // Handle window resize
      window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
          sidebar.classList.remove('open');
          overlay.classList.remove('show');
        } else {
          sidebar.classList.remove('collapsed');
          main.classList.remove('expanded');
        }
      });
    });
  </script>
</body>

</html>
