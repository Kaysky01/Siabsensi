<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'SIABSEN — Portal Mahasiswa')</title>
  <link rel="icon" type="image/png" href="{{ asset('static/img/logo.png') }}">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300;400;500;600;700" rel="stylesheet">
  @vite(['resources/css/admin.css'])
  <link href="{{ asset('static/css/mahasiswa.css') }}" rel="stylesheet">
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
            <div class="logo-sub">Portal Mahasiswa</div>
          </div>
        </div>
        <button id="sidebar-close" style="background:transparent; border:none; cursor:pointer; color:var(--text-muted); display:flex; padding:4px;" class="mobile-only-btn">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <nav class="nav">
        <div class="nav-section">Menu Utama</div>
        <a href="{{ route('mahasiswa.dashboard') }}" class="nav-item {{ request()->routeIs('mahasiswa.dashboard') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">dashboard</span> Dashboard
        </a>
        <a href="{{ route('mahasiswa.profile') }}" class="nav-item {{ request()->routeIs('mahasiswa.profile') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">person</span> Edit Profil
        </a>
        <a href="{{ route('mahasiswa.riwayat') }}" class="nav-item {{ request()->routeIs('mahasiswa.riwayat') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">history</span> Riwayat Kehadiran
        </a>
        <a href="{{ route('mahasiswa.qr') }}" class="nav-item {{ request()->routeIs('mahasiswa.qr') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">qr_code</span> QR Code Saya
        </a>

        <div class="nav-section">Pengajuan</div>
        <a href="{{ route('mahasiswa.izin') }}" class="nav-item {{ request()->routeIs('mahasiswa.izin') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">edit_note</span> Pengajuan Izin/Sakit
        </a>
        <a href="{{ route('mahasiswa.kehadiran') }}" class="nav-item {{ request()->routeIs('mahasiswa.kehadiran') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">how_to_reg</span> Pengajuan Kehadiran
        </a>

        <div class="nav-section">Lainnya</div>
        <a href="{{ route('mahasiswa.kegiatan') }}" class="nav-item {{ request()->routeIs('mahasiswa.kegiatan') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">event</span> Absensi Kegiatan
        </a>
        <a href="{{ route('mahasiswa.sertifikat') }}" class="nav-item {{ request()->routeIs('mahasiswa.sertifikat') ? 'active' : '' }}">
          <span class="material-symbols-outlined icon">workspace_premium</span> Unduh Sertifikat
        </a>

        <div class="nav-section">Keluar</div>
        <a href="{{ route('logout') }}" class="nav-item" style="color: #ff6b6b;">
          <span class="material-symbols-outlined icon" style="color: #ff6b6b;">logout</span> Logout
        </a>
      </nav>

      <div class="sidebar-footer">
        <span class="status-dot"></span>
        Sistem Aktif<br>
        <span style="font-family:var(--mono);font-size:10px;opacity:.6">{{ \Carbon\Carbon::now()->format('d M Y') }}</span>
      </div>
    </aside>

    <main class="main">
      <div class="top-navbar">
        <button id="sidebar-toggle" title="Toggle Menu">
          <span class="material-symbols-outlined">menu</span>
        </button>
      </div>

      @if(session('success'))
      <div style="background:var(--success-light);color:var(--success);padding:12px 20px;border-bottom:1px solid var(--success);display:flex;align-items:center;gap:8px">
        <span class="material-symbols-outlined" style="font-size:18px">check_circle</span>
        {{ session('success') }}
      </div>
      @endif
      @if(session('error'))
      <div style="background:var(--danger-light);color:var(--danger);padding:12px 20px;border-bottom:1px solid var(--danger);display:flex;align-items:center;gap:8px">
        <span class="material-symbols-outlined" style="font-size:18px">error</span>
        {{ session('error') }}
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
