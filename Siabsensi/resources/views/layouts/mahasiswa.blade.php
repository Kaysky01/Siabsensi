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

    function previewFileStandalone(input, containerId) {
      const container = document.getElementById(containerId);
      if (!container) return;

      if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();

        reader.onload = function(e) {
          container.style.display = 'block';
          if (file.type.startsWith('image/')) {
            container.innerHTML = `
              <div style="font-size:12px;color:var(--text-secondary,#64748b);margin-bottom:6px">Pratinjau Foto Bukti:</div>
              <img src="${e.target.result}" style="max-height:220px;max-width:100%;border-radius:8px;border:1px solid var(--border,#cbd5e1);object-fit:contain;box-shadow:0 2px 8px rgba(0,0,0,0.08)">
            `;
          } else if (file.type === 'application/pdf') {
            container.innerHTML = `
              <div style="font-size:12px;color:var(--text-secondary,#64748b);margin-bottom:6px">Pratinjau File PDF: ${file.name}</div>
              <iframe src="${e.target.result}" style="width:100%;height:220px;border:1px solid var(--border,#cbd5e1);border-radius:8px"></iframe>
            `;
          } else {
            container.innerHTML = `<div style="font-size:13px;color:var(--primary,#2563eb);font-weight:500">📄 ${file.name} (${(file.size/1024).toFixed(1)} KB)</div>`;
          }
        };

        reader.readAsDataURL(file);
      } else {
        container.style.display = 'none';
        container.innerHTML = '';
      }
    }

    function showBukti(url) {
      var content = document.getElementById('bukti-content-global');
      var backdrop = document.getElementById('modal-bukti-global');
      if (!content || !backdrop) return;
      
      var isPdf = url.toLowerCase().endsWith('.pdf');
      if (isPdf) {
        content.innerHTML = '<iframe src="' + url + '" style="width:100%;height:70vh;border:1px solid var(--border);border-radius:8px"></iframe>';
      } else {
        content.innerHTML = '<img src="' + url + '" alt="Bukti" style="max-width:100%;max-height:75vh;border-radius:8px;border:1px solid var(--border);object-fit:contain">';
      }
      backdrop.classList.add('show');
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      @if(session('success'))
        Swal.fire({
          icon: 'success',
          title: 'Berhasil!',
          text: '{!! session('success') !!}',
          confirmButtonColor: 'var(--primary)'
        });
      @endif

      @if(session('error'))
        Swal.fire({
          icon: 'error',
          title: 'Gagal!',
          text: '{!! session('error') !!}',
          confirmButtonColor: 'var(--danger)'
        });
      @endif

      @if($errors->any())
        Swal.fire({
          icon: 'error',
          title: 'Terjadi Kesalahan!',
          html: '<ul style="text-align:left;margin-bottom:0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>',
          confirmButtonColor: 'var(--danger)'
        });
      @endif
    });
  </script>

  <!-- Modal Bukti Global -->
  <div class="modal-backdrop" id="modal-bukti-global">
    <div class="modal modal-bukti" style="max-width:700px;padding:20px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <div class="modal-title" style="margin-bottom:0">Bukti Lampiran</div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('modal-bukti-global').classList.remove('show')" style="padding:4px">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div id="bukti-content-global" style="text-align:center;min-height:200px"></div>
    </div>
  </div>

  {{-- POPUP PENGUMUMAN KOMPI DARI GARDA --}}
  @php
    $userMhs = auth()->user();
    $kompiName = $userMhs->mahasiswa->kompi ?? $userMhs->assigned_kompi ?? null;
    $activeAnn = null;
    if ($kompiName && \Illuminate\Support\Facades\Schema::hasTable('kompi_announcements')) {
        $activeAnn = \App\Models\KompiAnnouncement::where('kompi', $kompiName)->where('is_active', 1)->first();
    }
  @endphp

  @if($activeAnn)
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const annId = "kompi_ann_{{ $activeAnn->id }}_{{ strtotime($activeAnn->updated_at) }}";
      if (sessionStorage.getItem(annId)) {
        return;
      }
      sessionStorage.setItem(annId, 'shown');

      Swal.fire({
        padding: '24px',
        width: '460px',
        showConfirmButton: true,
        confirmButtonText: 'Tutup Pengumuman',
        confirmButtonColor: '#1e293b',
        showDenyButton: false,
        showCancelButton: false,
        allowOutsideClick: true,
        customClass: {
          popup: 'swal2-modern-popup'
        },
        html: `
          <div style="font-family:inherit">
            <!-- Header Icon -->
            <div style="display:flex;align-items:center;justify-content:center;margin-bottom:12px">
              <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg, #25D366 0%, #128C7E 100%);display:flex;align-items:center;justify-content:center;color:#ffffff;box-shadow:0 6px 16px rgba(37,211,102,0.35)">
                <span class="material-symbols-outlined" style="font-size:26px">campaign</span>
              </div>
            </div>

            <!-- Title -->
            <h3 style="font-size:18px;font-weight:700;color:#0f172a;margin:0 0 16px 0;line-height:1.4;text-align:center">
              {{ addslashes($activeAnn->judul) }}
            </h3>

            <!-- Message Body -->
            @if($activeAnn->pesan)
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #25D366;padding:14px 16px;border-radius:10px;margin-bottom:18px;text-align:left;font-size:14px;color:#334155;line-height:1.6;white-space:pre-line">
              {{ addslashes($activeAnn->pesan) }}
            </div>
            @endif

            <!-- WhatsApp Action Button -->
            @if($activeAnn->link_wa)
            <div style="text-align:center;margin-bottom:18px">
              <a href="{{ $activeAnn->link_wa }}" target="_blank" style="background:linear-gradient(135deg, #25D366 0%, #128C7E 100%);color:#ffffff;font-weight:700;font-size:14px;padding:12px 24px;border-radius:50px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 6px 18px rgba(37,211,102,0.35);transition:transform 0.15s ease">
                <span class="material-symbols-outlined" style="font-size:22px">groups</span>
                Gabung Group WhatsApp Kompi
              </a>
            </div>
            @endif

            <!-- Checkbox Suppress Session -->
            <div style="padding-top:14px;border-top:1px dashed #e2e8f0;display:flex;align-items:center;justify-content:center">
              <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;color:#64748b;cursor:pointer;user-select:none">
                <input type="checkbox" id="chk_dont_show_session" style="width:16px;height:16px;accent-color:#25D366;cursor:pointer">
                Jangan tampilkan lagi pada sesi ini
              </label>
            </div>
          </div>
        `
      }).then(() => {
        const chk = document.getElementById('chk_dont_show_session');
        if (chk && chk.checked) {
          sessionStorage.setItem(annId, 'suppress');
        }
      });
    });
  </script>
  <style>
    .swal2-modern-popup {
      border-radius: 16px !important;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
    }
    .swal2-modern-popup .swal2-html-container {
      margin: 0 !important;
      padding: 0 !important;
    }
    .swal2-modern-popup .swal2-confirm {
      border-radius: 24px !important;
      padding: 10px 28px !important;
      font-weight: 600 !important;
      font-size: 14px !important;
      box-shadow: 0 4px 12px rgba(30, 41, 59, 0.2) !important;
    }
  </style>
  @endif
</body>
</html>
