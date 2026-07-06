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
    
    .nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 16px;
      transition: all 0.2s;
    }
    
    .nav-item .badge {
      margin-left: auto;
      padding: 2px 8px;
      font-size: 11px;
      font-weight: 600;
      border-radius: 10px;
    }
    
    .badge-warning {
      background: #ffc107;
      color: #000;
    }
    
    .nav-item-logout {
      color: #ff6b6b !important;
      background: rgba(255, 107, 107, 0.1);
      border-radius: 6px;
      margin: 4px 8px;
    }
    
    .nav-item-logout:hover {
      background: rgba(255, 107, 107, 0.2) !important;
    }
    
    .nav-item-logout .icon {
      color: #ff6b6b !important;
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

        {{-- ADMIN & TIMDIS MENU --}}
        @if($user->role !== 'garda')
        
        {{-- Dashboard & Monitoring --}}
        <div class="nav-section">
          <span class="material-symbols-outlined section-icon">dashboard</span>
          Dashboard & Monitoring
        </div>
        <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">analytics</span> Dashboard
        </a>
        <a href="{{ route('admin.attendance') }}" class="nav-item {{ request()->routeIs('admin.attendance') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">sensors</span> Monitor Absensi
        </a>

        {{-- Data Master (Admin Only) --}}
        @if($user->role === 'admin')
        <div class="nav-section">
          <span class="material-symbols-outlined section-icon">database</span>
          Data Master
        </div>
        <a href="{{ route('admin.master.jurusan-prodi') }}" class="nav-item {{ request()->routeIs('admin.master.jurusan-prodi') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">school</span> Jurusan & Prodi
        </a>
        <a href="{{ route('admin.master.kompi') }}" class="nav-item {{ request()->routeIs('admin.master.kompi') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">corporate_fare</span> Data Kompi
        </a>
        <a href="{{ route('admin.mahasiswa') }}" class="nav-item {{ request()->routeIs('admin.mahasiswa') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">badge</span> Data Mahasiswa
        </a>
        <a href="{{ route('admin.kompi-management') }}" class="nav-item {{ request()->routeIs('admin.kompi-management') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">groups</span> Kelola Kompi
        </a>
        @endif

        {{-- Kegiatan --}}
        <div class="nav-section">
          <span class="material-symbols-outlined section-icon">event</span>
          Kegiatan
        </div>
        @if($user->role === 'admin')
        <a href="{{ route('admin.kegiatan') }}" class="nav-item {{ request()->routeIs('admin.kegiatan') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">edit_calendar</span> Kelola Kegiatan
        </a>
        @endif
        <a href="{{ route('admin.absensi-persesi') }}" class="nav-item {{ request()->routeIs('admin.absensi-persesi') || request()->routeIs('admin.absensi-manual.*') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">checklist</span> Absensi Persesi
        </a>
        <a href="{{ route('admin.monitoring-kegiatan') }}" class="nav-item {{ request()->routeIs('admin.monitoring-kegiatan') || request()->routeIs('admin.monitoring-sesi') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">monitoring</span> Monitor Kegiatan
        </a>

        {{-- Verifikasi (Admin & Timdis) --}}
        <div class="nav-section">
          <span class="material-symbols-outlined section-icon">task_alt</span>
          Verifikasi & Approval
        </div>
        <a href="{{ route('admin.izin-timdis') }}" class="nav-item {{ request()->routeIs('admin.izin-timdis') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">fact_check</span> 
          Izin/Sakit
          @if(($pendingIzin ?? 0) > 0)
          <span class="badge badge-warning">{{ $pendingIzin }}</span>
          @endif
        </a>
        <a href="{{ route('admin.kehadiran-timdis') }}" class="nav-item {{ request()->routeIs('admin.kehadiran-timdis') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">how_to_reg</span>
          Kehadiran Manual
          @if(($pendingKehadiran ?? 0) > 0)
          <span class="badge badge-warning">{{ $pendingKehadiran }}</span>
          @endif
        </a>

        {{-- Laporan (Admin Only) --}}
        @if($user->role === 'admin')
        <div class="nav-section">
          <span class="material-symbols-outlined section-icon">description</span>
          Laporan & Analisis
        </div>
        <a href="{{ route('admin.late-report') }}" class="nav-item {{ request()->routeIs('admin.late-report*') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">schedule_send</span> Keterlambatan
        </a>
        <a href="{{ route('admin.kelulusan') }}" class="nav-item {{ request()->routeIs('admin.kelulusan') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">assignment_turned_in</span> Kelulusan
        </a>

        {{-- Pengaturan Sistem (Admin Only) --}}
        <div class="nav-section">
          <span class="material-symbols-outlined section-icon">settings</span>
          Pengaturan Sistem
        </div>
        <a href="{{ route('admin.pkkmb-schedule.index') }}" class="nav-item {{ request()->routeIs('admin.pkkmb-schedule.*') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">calendar_month</span> Jadwal Absensi
        </a>
        <a href="{{ route('admin.users') }}" class="nav-item {{ request()->routeIs('admin.users') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">admin_panel_settings</span> Kelola Admin
        </a>
        <a href="{{ route('admin.settings') }}" class="nav-item {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">tune</span> Pengaturan Umum
        </a>
        @endif

        @endif

        {{-- GARDA MENU --}}
        @if($user->role === 'garda')
        <div class="nav-section">
          <span class="material-symbols-outlined section-icon">visibility</span>
          Data Mahasiswa
        </div>
        <a href="{{ route('admin.mahasiswa-saya') }}" class="nav-item {{ request()->routeIs('admin.mahasiswa-saya') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">group</span> Mahasiswa Saya
        </a>
        @endif

        {{-- Logout (All Roles) --}}
        <div class="nav-section" style="margin-top:auto;padding-top:20px;border-top:1px solid rgba(255,255,255,0.1)">
          <span class="material-symbols-outlined section-icon">logout</span>
          Akun
        </div>
        <a href="{{ route('logout') }}" class="nav-item nav-item-logout">
          <span class="material-symbols-outlined icon">power_settings_new</span> Keluar
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
