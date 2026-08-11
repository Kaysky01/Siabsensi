@extends('layouts.admin')
@section('title', 'Dashboard Utama — Portal Administrator')

@section('content')
<div class="admin-dashboard-wrapper">
  <!-- Welcome Hero Banner -->
  <div class="admin-welcome-banner">
    <div class="banner-content">
      <div class="banner-badge">
        <span class="pulse-dot"></span> SISTEM ABSENSI PKKMB {{ date('Y') }}
      </div>
      <h1 class="banner-title">Selamat Datang, {{ Auth::user()->full_name }}!</h1>
      <p class="banner-sub">
        <span class="material-symbols-outlined icon-sm">calendar_today</span> {{ Carbon\Carbon::today()->translatedFormat('l, d F Y') }} • Role: <span class="role-tag">{{ ucfirst(Auth::user()->role) }}</span>
      </p>
    </div>
    <div class="banner-actions">
      <a href="{{ route('admin.attendance') }}" class="btn btn-banner-primary">
        <span class="material-symbols-outlined">sensors</span> Live Monitoring
      </a>
      <a href="{{ route('admin.kehadiran') }}" class="btn btn-banner-secondary">
        <span class="material-symbols-outlined">how_to_reg</span> Verifikasi
      </a>
      <a href="{{ route('admin.dashboard') }}" class="btn btn-banner-ghost" title="Refresh Data">
        <span class="material-symbols-outlined">refresh</span>
      </a>
    </div>
  </div>

  <!-- Pending Submissions Alert Box (If Any) -->
  @if(isset($totalPending) && $totalPending > 0)
    <div class="pending-alert-box">
      <div class="alert-icon-box">
        <span class="material-symbols-outlined">notifications_active</span>
      </div>
      <div class="alert-info">
        <div class="alert-title">Perhatian: Ada Pengajuan Membutuhkan Verifikasi!</div>
        <div class="alert-desc">
          Terdapat <strong>{{ $totalPending }} pengajuan</strong> ({{ $pendingKehadiranCount }} Presensi Manual & {{ $pendingIzinCount }} Izin/Sakit) yang menunggu persetujuan Anda.
        </div>
      </div>
      <div class="alert-actions">
        @if($pendingKehadiranCount > 0)
          <a href="{{ route('admin.kehadiran') }}" class="btn btn-alert-action">Tinjau Presensi ({{ $pendingKehadiranCount }})</a>
        @endif
        @if($pendingIzinCount > 0)
          <a href="{{ route('admin.izin') }}" class="btn btn-alert-ghost">Tinjau Izin ({{ $pendingIzinCount }})</a>
        @endif
      </div>
    </div>
  @endif

  <!-- Dashboard Overview Stats Grid -->
  <div class="admin-stats-grid">
    <!-- Stat 1: Total Mahasiswa -->
    <div class="admin-stat-card card-blue">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">group</span>
        </div>
        <span class="card-badge bg-blue">Aktif Terdaftar</span>
      </div>
      <div class="card-value">{{ number_format($totalMahasiswa) }}</div>
      <div class="card-title">Total Mahasiswa</div>
      <div class="card-desc">Terdaftar dalam database</div>
    </div>

    <!-- Stat 2: Hadir Hari Ini -->
    <div class="admin-stat-card card-emerald">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">task_alt</span>
        </div>
        <span class="card-badge bg-emerald">{{ $pct }}% Kehadiran</span>
      </div>
      <div class="card-value">{{ number_format($presentToday) }}</div>
      <div class="card-title">Hadir Hari Ini</div>
      <div class="card-desc">Tercatat presensi harian</div>
    </div>

    <!-- Stat 3: Belum Hadir / Alpha -->
    <div class="admin-stat-card card-rose">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">person_off</span>
        </div>
        <span class="card-badge bg-rose">Belum Presensi</span>
      </div>
      <div class="card-value">{{ number_format($absent) }}</div>
      <div class="card-title">Tidak Hadir (Alpha)</div>
      <div class="card-desc">Mahasiswa belum check-in</div>
    </div>

    <!-- Stat 4: Masih di Lokasi -->
    <div class="admin-stat-card card-amber">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">schedule</span>
        </div>
        <span class="card-badge bg-amber">Check-In Aktif</span>
      </div>
      <div class="card-value">{{ number_format($stillIn) }}</div>
      <div class="card-title">Masih di Lokasi</div>
      <div class="card-desc">Belum melakukan check-out</div>
    </div>
  </div>

  <!-- Dashboard Main Layout Grid -->
  <div class="admin-main-grid">
    <!-- Left Column: Absensi Terkini Hari Ini -->
    <div class="admin-panel main-panel">
      <div class="panel-header">
        <div>
          <h2 class="panel-title">
            <span class="material-symbols-outlined">history</span>
            Log Absensi Terkini Hari Ini
          </h2>
          <div class="panel-sub">Daftar presensi mahasiswa terbaru yang tercatat oleh sistem</div>
        </div>
        <a href="{{ route('admin.attendance') }}" class="btn btn-panel-link">
          Lihat Semua <span class="material-symbols-outlined icon-sm">arrow_forward</span>
        </a>
      </div>

      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Mahasiswa</th>
              <th>Kompi</th>
              <th>Check-In</th>
              <th>Check-Out</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recent as $att)
              <tr>
                <td>
                  <div class="mhs-profile-cell">
                    @php
                      $photoPath = is_string($att->photo_path) ? trim($att->photo_path) : null;
                      $photoUrl = null;
                      if ($photoPath) {
                        if (str_starts_with($photoPath, 'http://') || str_starts_with($photoPath, 'https://')) {
                          $photoUrl = $photoPath;
                        } else {
                          $cleanPath = ltrim($photoPath, '/');
                          if (str_starts_with($cleanPath, 'storage/')) $cleanPath = substr($cleanPath, 8);
                          elseif (str_starts_with($cleanPath, 'public/')) $cleanPath = substr($cleanPath, 7);
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
                  <span class="kompi-badge">{{ $att->kompi }}</span>
                </td>
                <td>
                  <span class="time-badge checkin">
                    <span class="material-symbols-outlined icon-xs">login</span>
                    {{ $att->check_in ? Carbon\Carbon::parse($att->check_in)->format('H:i:s') : '-' }}
                  </span>
                </td>
                <td>
                  <span class="time-badge checkout">
                    <span class="material-symbols-outlined icon-xs">logout</span>
                    {{ $att->check_out ? Carbon\Carbon::parse($att->check_out)->format('H:i:s') : '-' }}
                  </span>
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
                <td colspan="5" class="table-empty-state">
                  <span class="material-symbols-outlined">event_busy</span>
                  <div>Belum ada data absensi tercatat hari ini</div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <!-- Right Column: Analytics & Quick Nav -->
    <div class="admin-side-column">
      <!-- 7-Day Trend Chart Panel -->
      <div class="admin-panel">
        <div class="panel-header-simple">
          <span class="material-symbols-outlined">bar_chart</span>
          Tren Kehadiran (7 Hari Terakhir)
        </div>
        <div class="chart-container">
          @php 
            $trendCounts = array_column($trend, 'count');
            $maxTrend = !empty($trendCounts) ? max($trendCounts) : 1;
            $maxTrend = $maxTrend > 0 ? $maxTrend : 1;
          @endphp
          <div class="bar-chart-flex">
            @foreach($trend as $t)
              @php $heightPct = max(10, round(($t['count'] / $maxTrend) * 100)); @endphp
              <div class="bar-column" title="{{ $t['date'] }}: {{ $t['count'] }} Mahasiswa Hadir">
                <div class="bar-wrapper">
                  <div class="bar-fill-element" style="height: {{ $heightPct }}%;">
                    <span class="bar-value-tooltip">{{ $t['count'] }}</span>
                  </div>
                </div>
                <span class="bar-date-label">{{ $t['date'] }}</span>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      <!-- Presensi Per Kompi Panel -->
      <div class="admin-panel">
        <div class="panel-header-simple">
          <span class="material-symbols-outlined">pie_chart</span>
          Presensi Hari Ini Per Kompi
        </div>
        <div class="kompi-list">
          @forelse($byKompi as $k)
            @php $pctKompi = round(($k->count / $maxKompi) * 100); @endphp
            <div class="kompi-item">
              <div class="kompi-info-row">
                <span class="kompi-name">Kompi {{ $k->kompi }}</span>
                <span class="kompi-count-text"><strong>{{ $k->count }}</strong> Mahasiswa</span>
              </div>
              <div class="kompi-progress-bg">
                <div class="kompi-progress-fill" style="width: {{ $pctKompi }}%"></div>
              </div>
            </div>
          @empty
            <div class="empty-side-text">Belum ada data presensi kompi hari ini</div>
          @endforelse
        </div>
      </div>

      <!-- Quick Actions Admin -->
      <div class="admin-panel">
        <div class="panel-header-simple">
          <span class="material-symbols-outlined">grid_view</span>
          Pintas Navigasi Admin
        </div>
        <div class="admin-quick-grid">
          <a href="{{ route('admin.mahasiswa') }}" class="admin-quick-card">
            <div class="quick-card-icon bg-light-blue">
              <span class="material-symbols-outlined">school</span>
            </div>
            <div>
              <div class="quick-card-title">Data Mahasiswa</div>
              <div class="quick-card-sub">Kelola profil & QR</div>
            </div>
          </a>

          <a href="{{ route('admin.kehadiran') }}" class="admin-quick-card">
            <div class="quick-card-icon bg-light-emerald">
              <span class="material-symbols-outlined">verified</span>
            </div>
            <div>
              <div class="quick-card-title">Verifikasi Presensi</div>
              <div class="quick-card-sub">Acc manual & izin</div>
            </div>
          </a>

          <a href="{{ route('admin.pkkmb-schedule.index') }}" class="admin-quick-card">
            <div class="quick-card-icon bg-light-amber">
              <span class="material-symbols-outlined">edit_calendar</span>
            </div>
            <div>
              <div class="quick-card-title">Jadwal PKKMB</div>
              <div class="quick-card-sub">Kelola tanggal event</div>
            </div>
          </a>

          <a href="{{ route('admin.kelulusan') }}" class="admin-quick-card">
            <div class="quick-card-icon bg-light-indigo">
              <span class="material-symbols-outlined">workspace_premium</span>
            </div>
            <div>
              <div class="quick-card-title">Generasi Sertifikat</div>
              <div class="quick-card-sub">Kelola status & pdf</div>
            </div>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* CSS Styles for Modern Admin Dashboard */
.admin-dashboard-wrapper {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1350px;
  margin: 0 auto;
}

/* Welcome Hero Banner */
.admin-welcome-banner {
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

.admin-welcome-banner::after {
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

.role-tag {
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
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.2);
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
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
}

.btn-banner-ghost {
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.2);
  padding: 10px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: all 0.2s ease;
}

.btn-banner-ghost:hover {
  background: rgba(255, 255, 255, 0.2);
  color: #ffffff;
}

/* Pending Alert Box */
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

/* Admin Stats Grid */
.admin-stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.admin-stat-card {
  background: #ffffff;
  border-radius: 14px;
  padding: 22px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.admin-stat-card:hover {
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
.admin-main-grid {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 20px;
}

.admin-panel {
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

/* Admin Table */
.table-responsive {
  overflow-x: auto;
}

.admin-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.admin-table th {
  padding: 12px 14px;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.admin-table td {
  padding: 14px;
  border-bottom: 1px solid #f1f5f9;
  font-size: 13px;
  vertical-align: middle;
}

.admin-table tr:hover {
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

.kompi-badge {
  background: #f1f5f9;
  color: #334155;
  font-size: 11px;
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
.time-badge.checkout { color: #2563eb; }

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

/* Side Column Elements */
.admin-side-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Bar Chart */
.chart-container {
  padding: 10px 0 0 0;
}

.bar-chart-flex {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  height: 140px;
  gap: 8px;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 8px;
}

.bar-column {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
}

.bar-wrapper {
  flex: 1;
  width: 100%;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.bar-fill-element {
  width: 70%;
  max-width: 28px;
  background: linear-gradient(180deg, #3b82f6 0%, #1d4ed8 100%);
  border-radius: 6px 6px 0 0;
  position: relative;
  transition: height 0.4s ease;
}

.bar-value-tooltip {
  position: absolute;
  top: -20px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 10px;
  font-weight: 800;
  color: #1e293b;
}

.bar-date-label {
  font-size: 10px;
  font-weight: 700;
  color: #64748b;
  margin-top: 6px;
}

/* Kompi List */
.kompi-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.kompi-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.kompi-info-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
}

.kompi-name {
  font-weight: 700;
  color: #334155;
}

.kompi-count-text {
  color: #64748b;
}

.kompi-progress-bg {
  height: 8px;
  background: #f1f5f9;
  border-radius: 8px;
  overflow: hidden;
}

.kompi-progress-fill {
  height: 100%;
  background: #2563eb;
  border-radius: 8px;
}

/* Quick Admin Cards */
.admin-quick-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.admin-quick-card {
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

.admin-quick-card:hover {
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

.icon-sm { font-size: 16px !important; }
.icon-xs { font-size: 14px !important; vertical-align: middle; }

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE STYLES
   ═══════════════════════════════════════════════════════════════ */
@media (max-width: 1024px) {
  .admin-main-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 992px) {
  .admin-stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 576px) {
  .admin-welcome-banner {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
  }

  .admin-welcome-banner::after {
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

  .admin-stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }

  .admin-stat-card {
    padding: 14px;
  }

  .card-value {
    font-size: 24px;
  }

  .admin-quick-grid {
    grid-template-columns: 1fr;
  }
}
</style>
@endsection
