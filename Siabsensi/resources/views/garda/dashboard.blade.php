@extends('layouts.admin')
@section('title', 'Dashboard Garda — SIABSEN')

@section('content')
<div class="garda-dashboard-wrapper">
  <!-- Welcome Hero Banner -->
  <div class="garda-welcome-banner">
    <div class="banner-content">
      <div class="banner-badge">
        <span class="pulse-dot"></span> PANITIA GARDA PKKMB {{ date('Y') }}
      </div>
      <h1 class="banner-title">Selamat Datang, {{ Auth::user()->full_name }}!</h1>
      <p class="banner-sub">
        <span class="material-symbols-outlined icon-sm">calendar_today</span> {{ Carbon\Carbon::today()->translatedFormat('l, d F Y') }} • Penugasan: <span class="kompi-tag">Kompi {{ auth()->user()->assigned_kompi ?? '-' }}</span>
      </p>
    </div>
    <div class="banner-actions">
      <a href="{{ route('garda.absensi-persesi') }}" class="btn btn-banner-primary">
        <span class="material-symbols-outlined">how_to_reg</span> Absensi Manual
      </a>
      <a href="{{ route('garda.kehadiran-manual') }}" class="btn btn-banner-secondary">
        <span class="material-symbols-outlined">verified</span> Verifikasi
      </a>
      <a href="{{ route('garda.dashboard') }}" class="btn btn-banner-ghost" title="Refresh Data">
        <span class="material-symbols-outlined">refresh</span>
      </a>
    </div>
  </div>

  <!-- Pending Action Alert (If Pending Submissions Exist) -->
  @if(($kehadiranManualPending ?? 0) > 0 || ($izinPending ?? 0) > 0)
    <div class="pending-alert-box">
      <div class="alert-icon-box">
        <span class="material-symbols-outlined">pending_actions</span>
      </div>
      <div class="alert-info">
        <div class="alert-title">Pengajuan Perlu Tindakan Garda</div>
        <div class="alert-desc">
          Terdapat <strong>{{ $kehadiranManualPending }} pengajuan presensi manual</strong> dan <strong>{{ $izinPending }} pengajuan izin</strong> dari mahasiswa Kompi {{ auth()->user()->assigned_kompi }}.
        </div>
      </div>
      <div class="alert-actions">
        @if(($kehadiranManualPending ?? 0) > 0)
          <a href="{{ route('garda.kehadiran-manual') }}" class="btn btn-alert-action">Tinjau Presensi ({{ $kehadiranManualPending }})</a>
        @endif
        @if(($izinPending ?? 0) > 0)
          <a href="{{ route('garda.izin') }}" class="btn btn-alert-ghost">Tinjau Izin ({{ $izinPending }})</a>
        @endif
      </div>
    </div>
  @endif

  <!-- Overview Stats Grid -->
  <div class="garda-stats-grid">
    <!-- Stat 1: Total Mahasiswa Kompi -->
    <div class="garda-stat-card card-blue">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">group</span>
        </div>
        <span class="card-badge bg-blue">Kompi {{ auth()->user()->assigned_kompi }}</span>
      </div>
      <div class="card-value">{{ number_format($totalMahasiswa) }}</div>
      <div class="card-title">Total Mahasiswa</div>
      <div class="card-desc">Anggota Kompi {{ auth()->user()->assigned_kompi }}</div>
    </div>

    <!-- Stat 2: Hadir Hari Ini -->
    <div class="garda-stat-card card-emerald">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">task_alt</span>
        </div>
        <span class="card-badge bg-emerald">{{ $presentPct }}% Hadir</span>
      </div>
      <div class="card-value">{{ number_format($presentToday) }}</div>
      <div class="card-title">Hadir Hari Ini</div>
      <div class="card-desc">Kehadiran terkonfirmasi</div>
    </div>

    <!-- Stat 3: Tidak Hadir (Alpha) -->
    <div class="garda-stat-card card-rose">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">person_off</span>
        </div>
        <span class="card-badge bg-rose">Belum Absen</span>
      </div>
      <div class="card-value">{{ number_format($absentToday) }}</div>
      <div class="card-title">Tidak Hadir</div>
      <div class="card-desc">Mahasiswa belum check-in</div>
    </div>

    <!-- Stat 4: Total Izin / Sakit -->
    <div class="garda-stat-card card-amber">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">clinical_notes</span>
        </div>
        <span class="card-badge bg-amber">{{ $izinApproved }} Disetujui</span>
      </div>
      <div class="card-value">{{ number_format($izinTotal) }}</div>
      <div class="card-title">Izin / Sakit</div>
      <div class="card-desc">{{ $izinPending }} pending • {{ $izinRejected }} ditolak</div>
    </div>
  </div>

  <!-- Main Grid Layout -->
  <div class="garda-main-grid">
    <!-- Left Column: Recent Attendance Table -->
    <div class="garda-panel main-panel">
      <div class="panel-header">
        <div>
          <h2 class="panel-title">
            <span class="material-symbols-outlined">history</span>
            Presensi Terbaru Kompi {{ auth()->user()->assigned_kompi }}
          </h2>
          <div class="panel-sub">Daftar mahasiswa yang melakukan presensi hari ini</div>
        </div>
        <a href="{{ route('garda.riwayat') }}" class="btn btn-panel-link">
          Lihat Riwayat <span class="material-symbols-outlined icon-sm">arrow_forward</span>
        </a>
      </div>

      <div class="table-responsive">
        <table class="garda-table">
          <thead>
            <tr>
              <th>Mahasiswa</th>
              <th>Sesi / Kegiatan</th>
              <th>Waktu Check-In</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recentAttendances as $att)
              <tr>
                <td>
                  <div class="mhs-profile-cell">
                    @php
                      $photoUrl = null;
                      if (!empty($att->photo_path)) {
                        $path = $att->photo_path;
                        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                          $photoUrl = $path;
                        } else {
                          $cleanPath = ltrim(str_replace(['public/', 'storage/'], '', $path), '/');
                          $photoUrl = url('/file-bukti/' . $cleanPath);
                        }
                      }
                    @endphp
                    @if($photoUrl)
                      <img src="{{ $photoUrl }}" alt="{{ $att->name }}" class="mhs-avatar-img">
                    @else
                      <div class="mhs-avatar-initials">
                        {{ strtoupper(substr($att->name, 0, 2)) }}
                      </div>
                    @endif
                    <div>
                      <div class="mhs-name-text">{{ $att->name }}</div>
                      <div class="mhs-id-text">ID: {{ $att->mahasiswa_id }}</div>
                    </div>
                  </div>
                </td>
                <td>
                  @if(isset($att->sesi))
                    <span class="sesi-badge">{{ $att->sesi->nama_sesi }}</span>
                  @else
                    <span class="text-muted-sm">-</span>
                  @endif
                </td>
                <td>
                  @if($att->absen_by)
                    <span class="time-badge manual" title="Waktu: {{ $att->check_in ? Carbon\Carbon::parse($att->check_in)->format('H:i:s') : '-' }}">
                      <span class="material-symbols-outlined icon-xs">edit_note</span> Manual ({{ $att->absen_by }})
                    </span>
                  @else
                    <span class="time-badge checkin">
                      <span class="material-symbols-outlined icon-xs">login</span>
                      {{ $att->check_in ? Carbon\Carbon::parse($att->check_in)->format('H:i:s') : '-' }}
                    </span>
                  @endif
                </td>
                <td>
                  @php
                    $statusClass = match(strtolower($att->status ?? '')) {
                        'present', 'hadir' => 'badge-status-green',
                        'izin' => 'badge-status-blue',
                        'sakit' => 'badge-status-amber',
                        default => 'badge-status-rose'
                    };
                  @endphp
                  <span class="badge-status {{ $statusClass }}">
                    {{ strtoupper($att->status ?? 'ALPHA') }}
                  </span>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="table-empty-state">
                  <span class="material-symbols-outlined">event_busy</span>
                  <div>Belum ada data presensi Kompi {{ auth()->user()->assigned_kompi }} hari ini</div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <!-- Right Side Column -->
    <div class="garda-side-column">
      <!-- Active Activities Panel -->
      <div class="garda-panel">
        <div class="panel-header-simple">
          <span class="material-symbols-outlined">event_available</span>
          Sesi Kegiatan Aktif
        </div>
        <div class="kegiatan-list">
          @forelse($activeKegiatan as $keg)
            <a href="{{ route('garda.absensi-manual.index', $keg->id) }}" class="kegiatan-card">
              <div class="kegiatan-card-info">
                <span class="kegiatan-name">{{ $keg->nama_sesi }}</span>
                <span class="kegiatan-meta">
                  @if($keg->pkkmbSchedule) Hari {{ $keg->pkkmbSchedule->hari_ke }} • @endif
                  {{ \Carbon\Carbon::parse($keg->jam_mulai)->format('H:i') }} WIB
                </span>
              </div>
              <span class="material-symbols-outlined icon-arrow">arrow_forward_ios</span>
            </a>
          @empty
            <div class="empty-side-text">Tidak ada kegiatan aktif saat ini</div>
          @endforelse
        </div>
      </div>

      <!-- Quick Actions Garda -->
      <div class="garda-panel">
        <div class="panel-header-simple">
          <span class="material-symbols-outlined">grid_view</span>
          Pintas Garda
        </div>
        <div class="garda-quick-grid">
          <a href="{{ route('garda.mahasiswa-saya') }}" class="garda-quick-card">
            <div class="quick-card-icon bg-light-blue">
              <span class="material-symbols-outlined">groups</span>
            </div>
            <div>
              <div class="quick-card-title">Mahasiswa Kompi</div>
              <div class="quick-card-sub">Daftar & Detail</div>
            </div>
          </a>

          <a href="{{ route('garda.kompi-saya') }}" class="garda-quick-card">
            <div class="quick-card-icon bg-light-emerald">
              <span class="material-symbols-outlined">shield</span>
            </div>
            <div>
              <div class="quick-card-title">Pengumuman Kompi</div>
              <div class="quick-card-sub">Informasi Kompi</div>
            </div>
          </a>

          <a href="{{ route('garda.kehadiran-manual') }}" class="garda-quick-card">
            <div class="quick-card-icon bg-light-amber">
              <span class="material-symbols-outlined">fact_check</span>
            </div>
            <div>
              <div class="quick-card-title">Klaim Kehadiran</div>
              <div class="quick-card-sub">Acc manual</div>
            </div>
          </a>

          <a href="{{ route('garda.izin') }}" class="garda-quick-card">
            <div class="quick-card-icon bg-light-indigo">
              <span class="material-symbols-outlined">event_note</span>
            </div>
            <div>
              <div class="quick-card-title">Verifikasi Izin</div>
              <div class="quick-card-sub">Surat Izin/Sakit</div>
            </div>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* CSS Styles for Garda Dashboard */
.garda-dashboard-wrapper {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1350px;
  margin: 0 auto;
}

/* Welcome Banner */
.garda-welcome-banner {
  background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #3b82f6 100%);
  border-radius: 16px;
  padding: 24px 28px;
  color: #ffffff;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.3);
  position: relative;
  overflow: hidden;
}

.garda-welcome-banner::after {
  content: '';
  position: absolute;
  right: -20px;
  top: -40px;
  width: 220px;
  height: 220px;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 50%;
  pointer-events: none;
}

.banner-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(8px);
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.5px;
  margin-bottom: 10px;
}

.pulse-dot {
  width: 8px;
  height: 8px;
  background: #4ade80;
  border-radius: 50%;
  box-shadow: 0 0 8px #4ade80;
}

.banner-title {
  font-size: 24px;
  font-weight: 800;
  margin: 0 0 6px 0;
  color: #ffffff;
}

.banner-sub {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.85);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
}

.kompi-tag {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
  padding: 2px 8px;
  border-radius: 6px;
  font-weight: 700;
}

.banner-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-banner-primary {
  background: #ffffff;
  color: #1e40af;
  font-weight: 700;
  padding: 10px 18px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  transition: all 0.2s ease;
}

.btn-banner-primary:hover {
  background: #f8fafc;
  color: #1d4ed8;
  transform: translateY(-2px);
}

.btn-banner-secondary {
  background: rgba(255, 255, 255, 0.15);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.25);
  font-weight: 600;
  padding: 10px 16px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  transition: all 0.2s ease;
}

.btn-banner-secondary:hover {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}

.btn-banner-ghost {
  background: rgba(255, 255, 255, 0.15);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.25);
  padding: 10px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: all 0.2s ease;
}

.btn-banner-ghost:hover {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}

/* Alert Box */
.pending-alert-box {
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 14px;
  padding: 18px 22px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.alert-icon-box {
  width: 44px;
  height: 44px;
  background: #fef3c7;
  color: #d97706;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.alert-info {
  flex: 1;
}

.alert-title {
  font-size: 15px;
  font-weight: 700;
  color: #92400e;
  margin-bottom: 2px;
}

.alert-desc {
  font-size: 13px;
  color: #b45309;
}

.alert-actions {
  display: flex;
  gap: 8px;
}

.btn-alert-action {
  background: #d97706;
  color: #ffffff;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s ease;
}

.btn-alert-action:hover {
  background: #b45309;
  color: #ffffff;
}

.btn-alert-ghost {
  background: #ffffff;
  color: #92400e;
  border: 1px solid #fcd34d;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
}

/* Stats Grid */
.garda-stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.garda-stat-card {
  background: #ffffff;
  border-radius: 14px;
  padding: 22px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.garda-stat-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
}

.card-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.card-blue .card-icon { background: #dbeafe; color: #2563eb; }
.card-emerald .card-icon { background: #dcfce7; color: #16a34a; }
.card-rose .card-icon { background: #ffe4e6; color: #e11d48; }
.card-amber .card-icon { background: #fef3c7; color: #d97706; }

.card-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 12px;
}

.bg-blue { background: #eff6ff; color: #2563eb; }
.bg-emerald { background: #f0fdf4; color: #16a34a; }
.bg-rose { background: #fff1f2; color: #e11d48; }
.bg-amber { background: #fffbeb; color: #d97706; }

.card-value {
  font-size: 32px;
  font-weight: 800;
  color: #0f172a;
  line-height: 1;
  margin-bottom: 6px;
}

.card-title {
  font-size: 13px;
  font-weight: 700;
  color: #334155;
  margin-bottom: 2px;
}

.card-desc {
  font-size: 11px;
  color: #94a3b8;
}

/* Main Grid */
.garda-main-grid {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 20px;
}

.garda-panel {
  background: #ffffff;
  border-radius: 14px;
  padding: 22px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 18px;
  padding-bottom: 14px;
  border-bottom: 1px solid #f1f5f9;
}

.panel-header-simple {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.panel-title {
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 2px 0;
}

.panel-sub {
  font-size: 12px;
  color: #64748b;
}

.btn-panel-link {
  color: #2563eb;
  font-weight: 700;
  font-size: 13px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  background: #eff6ff;
  transition: all 0.2s ease;
}

.btn-panel-link:hover {
  background: #dbeafe;
}

/* Table */
.table-responsive {
  overflow-x: auto;
}

.garda-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.garda-table th {
  padding: 12px 14px;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.garda-table td {
  padding: 14px;
  border-bottom: 1px solid #f1f5f9;
  font-size: 13px;
  vertical-align: middle;
}

.garda-table tr:hover {
  background: #f8fafc;
}

.mhs-profile-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.mhs-avatar-img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid #3b82f6;
  flex-shrink: 0;
}

.mhs-avatar-initials {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #dbeafe;
  color: #1d4ed8;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 14px;
  flex-shrink: 0;
}

.mhs-name-text {
  font-weight: 700;
  color: #0f172a;
}

.mhs-id-text {
  font-size: 11px;
  color: #94a3b8;
}

.sesi-badge {
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 6px;
}

.time-badge {
  font-family: monospace;
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.time-badge.checkin { color: #16a34a; }
.time-badge.manual { color: #0284c7; background: #e0f2fe; padding: 2px 6px; border-radius: 4px; }

.badge-status {
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.3px;
}

.badge-status-green { background: #dcfce7; color: #15803d; }
.badge-status-blue { background: #dbeafe; color: #1d4ed8; }
.badge-status-amber { background: #fef3c7; color: #b45309; }
.badge-status-rose { background: #ffe4e6; color: #be123c; }

.table-empty-state {
  text-align: center;
  padding: 40px !important;
  color: #94a3b8;
}

.table-empty-state span {
  font-size: 40px;
  margin-bottom: 8px;
}

/* Side Column */
.garda-side-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.kegiatan-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.kegiatan-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  text-decoration: none;
  transition: all 0.2s ease;
}

.kegiatan-card:hover {
  background: #ffffff;
  border-color: #2563eb;
  transform: translateX(3px);
}

.kegiatan-card-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.kegiatan-name {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
}

.kegiatan-meta {
  font-size: 11px;
  color: #64748b;
}

.icon-arrow {
  font-size: 14px !important;
  color: #94a3b8;
}

.garda-quick-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.garda-quick-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  text-decoration: none;
  transition: all 0.2s ease;
}

.garda-quick-card:hover {
  background: #ffffff;
  border-color: #2563eb;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
}

.quick-card-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.bg-light-blue { background: #dbeafe; color: #2563eb; }
.bg-light-emerald { background: #dcfce7; color: #16a34a; }
.bg-light-amber { background: #fef3c7; color: #d97706; }
.bg-light-indigo { background: #e0e7ff; color: #4f46e5; }

.quick-card-title {
  font-size: 12px;
  font-weight: 700;
  color: #0f172a;
}

.quick-card-sub {
  font-size: 10px;
  color: #64748b;
}

.empty-side-text {
  font-size: 12px;
  color: #94a3b8;
  text-align: center;
  padding: 16px 0;
}

.icon-sm { font-size: 16px !important; }
.icon-xs { font-size: 14px !important; vertical-align: middle; }

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE STYLES
   ═══════════════════════════════════════════════════════════════ */
@media (max-width: 1024px) {
  .garda-main-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 992px) {
  .garda-stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 576px) {
  .garda-welcome-banner {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
  }

  .garda-welcome-banner::after {
    display: none;
  }

  .banner-title {
    font-size: 22px;
  }

  .banner-actions {
    width: 100%;
    flex-wrap: wrap;
  }

  .btn-banner-primary, .btn-banner-secondary {
    flex: 1;
    justify-content: center;
  }

  .pending-alert-box {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .alert-actions {
    width: 100%;
    flex-direction: column;
  }

  .btn-alert-action, .btn-alert-ghost {
    width: 100%;
    text-align: center;
  }

  .garda-stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }

  .garda-stat-card {
    padding: 14px;
  }

  .card-value {
    font-size: 24px;
  }

  .garda-quick-grid {
    grid-template-columns: 1fr;
  }
}
</style>
@endsection
