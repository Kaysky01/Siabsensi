@extends('layouts.mahasiswa')
@section('title', 'Dashboard — Portal Mahasiswa')

@section('content')
<div class="mhs-dashboard-wrapper">
  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <div class="welcome-content">
      <div class="welcome-badge">
        <span class="status-dot-pulse"></span> PORTAL PKKMB {{ date('Y') }}
      </div>
      <h1 class="welcome-title">Selamat datang, {{ $mahasiswa->name }}!</h1>
      <p class="welcome-sub">
        NIM / ID: <strong>{{ $mahasiswa->id }}</strong> • Kompi: <span class="kompi-tag">{{ $mahasiswa->kompi }}</span> • Prodi: <strong>{{ $mahasiswa->prodi ?? '-' }}</strong>
      </p>
    </div>
    <div class="welcome-actions">
      <a href="{{ route('mahasiswa.qr') }}" class="btn btn-qr-action">
        <span class="material-symbols-outlined">qr_code_2</span>
        Tampilkan QR Code
      </a>
    </div>
  </div>

  <!-- Today's Attendance Status Card -->
  @php
    $today = \Carbon\Carbon::today()->toDateString();
    $todayAtt = $mahasiswa->attendances()->where('date', $today)->first();
    $todayKhdSub = $mahasiswa->kehadiranSubmissions()->where('date', $today)->first();
    $todayIznSub = $mahasiswa->izinSubmissions()->where('date', $today)->first();
  @endphp

  <div class="today-status-card">
    @if($todayAtt)
      @php
        $isManualToday = !empty($todayAtt->absen_by) || strtolower($todayAtt->status) === 'manual';
      @endphp
      @if($todayAtt->check_in && $todayAtt->check_out)
        <div class="status-box status-complete">
          <div class="status-icon-wrapper">
            <span class="material-symbols-outlined">verified</span>
          </div>
          <div class="status-info">
            <div class="status-label">
              Kehadiran Hari Ini Lengkap
              @if($isManualToday)
                <span style="font-size:12px;font-weight:700;color:#0284c7;background:#e0f2fe;padding:2px 8px;border-radius:12px;margin-left:6px">(Absen Manual)</span>
              @endif
            </div>
            <div class="status-details">
              <span><span class="material-symbols-outlined icon-sm">login</span> Masuk: <strong>{{ \Carbon\Carbon::parse($todayAtt->check_in)->format('H:i:s') }} WIB</strong></span>
              <span class="divider">•</span>
              <span><span class="material-symbols-outlined icon-sm">logout</span> Keluar: <strong>{{ \Carbon\Carbon::parse($todayAtt->check_out)->format('H:i:s') }} WIB</strong></span>
              @if($isManualToday && $todayAtt->absen_by)
                <span class="divider">•</span>
                <span style="color:#0284c7;font-weight:600">Verified oleh {{ $todayAtt->absen_by }}</span>
              @endif
            </div>
          </div>
          <div class="status-badge-tag badge-complete">
            {{ $isManualToday ? 'Hadir Lengkap (Absen Manual)' : 'Selesai Absen' }}
          </div>
        </div>
      @elseif($todayAtt->check_in)
        <div class="status-box status-checkedin">
          <div class="status-icon-wrapper">
            <span class="material-symbols-outlined">login</span>
          </div>
          <div class="status-info">
            <div class="status-label">
              Sudah Check-In Masuk
              @if($isManualToday)
                <span style="font-size:12px;font-weight:700;color:#0284c7;background:#e0f2fe;padding:2px 8px;border-radius:12px;margin-left:6px">(Absen Manual)</span>
              @endif
            </div>
            <div class="status-details">
              <span>Waktu Masuk: <strong>{{ \Carbon\Carbon::parse($todayAtt->check_in)->format('H:i:s') }} WIB</strong></span>
              <span class="divider">•</span>
              <span>Menunggu Check-Out Keluar</span>
            </div>
          </div>
          <div class="status-badge-tag badge-in-progress">
            {{ $isManualToday ? 'Check-In (Absen Manual)' : 'Aktif Presensi' }}
          </div>
        </div>
      @else
        <div class="status-box status-info-mode">
          <div class="status-icon-wrapper">
            <span class="material-symbols-outlined">event_available</span>
          </div>
          <div class="status-info">
            <div class="status-label">
              Status Absen Terverifikasi
              @if($isManualToday)
                <span style="font-size:12px;font-weight:700;color:#0284c7;background:#e0f2fe;padding:2px 8px;border-radius:12px;margin-left:6px">(Absen Manual)</span>
              @endif
            </div>
            <div class="status-details">Tercatat dalam sistem presensi hari ini.</div>
          </div>
          <div class="status-badge-tag badge-complete">
            {{ $isManualToday ? 'HADIR (ABSEN MANUAL)' : strtoupper($todayAtt->status) }}
          </div>
        </div>
      @endif
    @elseif($todayKhdSub && $todayKhdSub->status === 'pending')
      <div class="status-box status-pending">
        <div class="status-icon-wrapper">
          <span class="material-symbols-outlined">hourglass_top</span>
        </div>
        <div class="status-info">
          <div class="status-label">Pengajuan Kehadiran Manual Sedang Diproses</div>
          <div class="status-details">Pengajuan Anda sedang ditinjau oleh Garda / Timdis Kompi {{ $mahasiswa->kompi }}.</div>
        </div>
        <div class="status-badge-tag badge-pending">Menunggu Konfirmasi</div>
      </div>
    @elseif($todayIznSub && $todayIznSub->status === 'pending')
      <div class="status-box status-pending">
        <div class="status-icon-wrapper">
          <span class="material-symbols-outlined">pending_actions</span>
        </div>
        <div class="status-info">
          <div class="status-label">Pengajuan Izin / Sakit Sedang Diproses</div>
          <div class="status-details">Alasan: {{ Str::limit($todayIznSub->keterangan, 60) }}</div>
        </div>
        <div class="status-badge-tag badge-pending">Menunggu Konfirmasi</div>
      </div>
    @else
      <div class="status-box status-warning">
        <div class="status-icon-wrapper">
          <span class="material-symbols-outlined">error_outline</span>
        </div>
        <div class="status-info">
          <div class="status-label">Anda Belum Absen Hari Ini</div>
          <div class="status-details">Silakan lakukan scan QR Code pada kamera gerbang atau ajukan kehadiran manual.</div>
        </div>
        <div class="status-actions">
          <a href="{{ route('mahasiswa.kehadiran') }}" class="btn btn-warning-action">Ajukan Kehadiran</a>
          <a href="{{ route('mahasiswa.izin') }}" class="btn btn-ghost-action">Ajukan Izin</a>
        </div>
      </div>
    @endif
  </div>

  <!-- Dashboard Stats Grid -->
  <div class="stats-overview-grid">
    <!-- Stat Item 1: Total Hari Hadir -->
    <div class="stat-card-modern stat-green">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">calendar_month</span>
        </div>
        <span class="card-trend text-green">+{{ $stats['totalHadir'] }} Hari</span>
      </div>
      <div class="card-value">{{ $stats['totalHadir'] }}</div>
      <div class="card-title">Total Hari Hadir</div>
      <div class="card-desc">Kehadiran PKKMB terkonfirmasi</div>
    </div>

    <!-- Stat Item 2: Izin / Sakit -->
    <div class="stat-card-modern stat-amber">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">clinical_notes</span>
        </div>
        <span class="card-trend text-amber">{{ $stats['totalIzin'] }} Pengajuan</span>
      </div>
      <div class="card-value">{{ $stats['totalIzin'] }}</div>
      <div class="card-title">Izin / Sakit</div>
      <div class="card-desc">Pengajuan disetujui</div>
    </div>

    <!-- Stat Item 3: Tidak Hadir / Alpha -->
    <div class="stat-card-modern stat-rose">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">event_busy</span>
        </div>
        <span class="card-trend text-rose">{{ $stats['tidakHadir'] }} Hari</span>
      </div>
      <div class="card-value">{{ $stats['tidakHadir'] }}</div>
      <div class="card-title">Tidak Hadir (Alpha)</div>
      <div class="card-desc">Hari tanpa presensi</div>
    </div>

    <!-- Stat Item 4: Total Hari Kegiatan -->
    <div class="stat-card-modern stat-indigo">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">event_note</span>
        </div>
        <span class="card-trend text-indigo">{{ $stats['totalJadwal'] }} Hari Selesai</span>
      </div>
      <div class="card-value">{{ $stats['totalJadwal'] }}</div>
      <div class="card-title">Total Hari Kegiatan</div>
      <div class="card-desc">Jadwal PKKMB terlaksana</div>
    </div>
  </div>

  <!-- Performance Metrics Cards (Percentage & Average Duration) -->
  <div class="metrics-grid">
    <!-- Card Persentase Kehadiran & Status Sertifikat -->
    <div class="metric-card">
      <div class="metric-header">
        <div>
          <div class="metric-title">Persentase Kehadiran</div>
          <div class="metric-sub">Rasio kehadiran terhadap total jadwal PKKMB</div>
        </div>
        <div class="metric-badge-percent">{{ $stats['persentaseKehadiran'] }}%</div>
      </div>
      <div class="progress-bar-container">
        <div class="progress-bar-fill" style="width: {{ $stats['persentaseKehadiran'] }}%"></div>
      </div>
      <div class="progress-footer">
        <span>Target Kelulusan: <strong>80%</strong></span>
        @if(isset($stats['certStats']) && $stats['certStats']['can_get'])
          <span class="cert-status-tag tag-unlocked" title="Sertifikat Terbuka & Dapat Diunduh">
            <span class="material-symbols-outlined icon-sm">lock_open</span> Sertifikat Terbuka
          </span>
        @else
          <span class="cert-status-tag tag-locked" title="{{ $stats['certStats']['reason'] ?? 'Sertifikat Terkunci' }}">
            <span class="material-symbols-outlined icon-sm">lock</span> Sertifikat Terkunci
          </span>
        @endif
      </div>

      <!-- Detail Keterangan Lock/Unlock Sertifikat -->
      @if(isset($stats['certStats']) && !$stats['certStats']['can_get'])
        <div class="cert-lock-info">
          <span class="material-symbols-outlined icon-sm">info</span>
          <div>
            <strong>Status Sertifikat:</strong> {{ $stats['certStats']['reason'] ?? 'Belum mencapai batas minimal kelulusan 80%' }}
          </div>
        </div>
      @elseif(isset($stats['certStats']) && $stats['certStats']['can_get'])
        <div class="cert-unlock-info">
          <span class="material-symbols-outlined icon-sm">verified</span>
          <div>
            <strong>Status Sertifikat:</strong> Terbuka! Anda telah memenuhi syarat kelulusan PKKMB.
          </div>
        </div>
      @endif
    </div>

    <!-- Card Rata-rata Durasi Kehadiran -->
    <div class="metric-card">
      <div class="metric-header">
        <div>
          <div class="metric-title">Rata-rata Durasi Kehadiran</div>
          <div class="metric-sub">Waktu rata-rata berada di lokasi kegiatan</div>
        </div>
        <div class="metric-icon-box">
          <span class="material-symbols-outlined">schedule</span>
        </div>
      </div>
      <div class="duration-display">
        <span class="duration-value">{{ $stats['rataRataDurasi'] }}</span>
      </div>
      <div class="progress-footer">
        <span>Berdasarkan data Check-In & Check-Out</span>
      </div>
    </div>
  </div>

  <!-- Shortcut Menu & Recent Activity Layout -->
  <div class="dashboard-bottom-grid">
    <!-- Quick Menu Panel -->
    <div class="quick-nav-panel">
      <div class="panel-heading">
        <span class="material-symbols-outlined">grid_view</span>
        Akses Cepat
      </div>
      <div class="quick-nav-items">
        <a href="{{ route('mahasiswa.qr') }}" class="quick-nav-card">
          <div class="nav-card-icon icon-blue">
            <span class="material-symbols-outlined">qr_code_2</span>
          </div>
          <div class="nav-card-info">
            <div class="nav-card-title">QR Code Saya</div>
            <div class="nav-card-desc">Tunjukkan untuk presensi cepat</div>
          </div>
        </a>

        <a href="{{ route('mahasiswa.kehadiran') }}" class="quick-nav-card">
          <div class="nav-card-icon icon-emerald">
            <span class="material-symbols-outlined">how_to_reg</span>
          </div>
          <div class="nav-card-info">
            <div class="nav-card-title">Pengajuan Kehadiran</div>
            <div class="nav-card-desc">Form klaim presensi manual</div>
          </div>
        </a>

        <a href="{{ route('mahasiswa.izin') }}" class="quick-nav-card">
          <div class="nav-card-icon icon-amber">
            <span class="material-symbols-outlined">edit_note</span>
          </div>
          <div class="nav-card-info">
            <div class="nav-card-title">Pengajuan Izin/Sakit</div>
            <div class="nav-card-desc">Kirim surat / dokumen izin</div>
          </div>
        </a>

        <a href="{{ route('mahasiswa.sertifikat') }}" class="quick-nav-card">
          <div class="nav-card-icon {{ isset($stats['certStats']) && $stats['certStats']['can_get'] ? 'icon-emerald' : 'icon-rose' }}">
            <span class="material-symbols-outlined">{{ isset($stats['certStats']) && $stats['certStats']['can_get'] ? 'workspace_premium' : 'lock' }}</span>
          </div>
          <div class="nav-card-info">
            <div class="nav-card-title">Sertifikat PKKMB</div>
            <div class="nav-card-desc">
              @if(isset($stats['certStats']) && $stats['certStats']['can_get'])
                <span style="color:#16a34a;font-weight:700">🔓 Terbuka</span> — Siap Diunduh
              @else
                <span style="color:#e11d48;font-weight:700">🔒 Terkunci</span> — Belum Lulus
              @endif
            </div>
          </div>
        </a>
      </div>
    </div>

    <!-- Recent Activity Timeline Panel -->
    <div class="activity-timeline-panel">
      <div class="panel-heading">
        <span class="material-symbols-outlined">history_toggle_off</span>
        Aktivitas Presensi Terbaru
      </div>
      <div class="timeline-list">
        @forelse($recentActivities as $activity)
          <div class="timeline-item">
            <div class="timeline-bullet bullet-{{ $activity->type }}">
              <span class="material-symbols-outlined">
                {{ $activity->type === 'checkin' ? 'check_circle' : ($activity->type === 'izin' ? 'event_note' : 'info') }}
              </span>
            </div>
            <div class="timeline-body">
              <div class="timeline-header">
                <span class="timeline-title">{{ $activity->title }}</span>
                <span class="timeline-time">{{ $activity->timestamp }}</span>
              </div>
              <div class="timeline-desc">{{ $activity->description }}</div>
            </div>
          </div>
        @empty
          <div class="timeline-empty">
            <span class="material-symbols-outlined" style="font-size:36px;color:var(--text-muted)">event_busy</span>
            <div>Belum ada aktivitas presensi tercatat</div>
          </div>
        @endforelse
      </div>
    </div>
  </div>
</div>

<style>
/* CSS Styles for Modern Mahasiswa Dashboard */
.mhs-dashboard-wrapper {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

/* Welcome Banner */
.welcome-banner {
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

.welcome-banner::after {
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

.welcome-badge {
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

.status-dot-pulse {
  width: 8px;
  height: 8px;
  background: #4ade80;
  border-radius: 50%;
  box-shadow: 0 0 8px #4ade80;
}

.welcome-title {
  font-size: 24px;
  font-weight: 800;
  margin: 0 0 6px 0;
  color: #ffffff;
}

.welcome-sub {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.85);
  margin: 0;
}

.kompi-tag {
  background: rgba(255, 255, 255, 0.25);
  padding: 2px 8px;
  border-radius: 6px;
  font-weight: 700;
}

.btn-qr-action {
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

.btn-qr-action:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
  background: #f8fafc;
}

/* Today Status Card */
.today-status-card {
  border-radius: 14px;
  overflow: hidden;
}

.status-box {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 18px 22px;
  border-radius: 14px;
  border: 1px solid transparent;
}

.status-complete {
  background: #f0fdf4;
  border-color: #bbf7d0;
}

.status-checkedin {
  background: #eff6ff;
  border-color: #bfdbfe;
}

.status-info-mode {
  background: #f8fafc;
  border-color: #e2e8f0;
}

.status-pending {
  background: #fffbeb;
  border-color: #fde68a;
}

.status-warning {
  background: #fff7ed;
  border-color: #fed7aa;
}

.status-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.status-complete .status-icon-wrapper { background: #dcfce7; color: #16a34a; }
.status-checkedin .status-icon-wrapper { background: #dbeafe; color: #2563eb; }
.status-pending .status-icon-wrapper { background: #fef3c7; color: #d97706; }
.status-warning .status-icon-wrapper { background: #ffedd5; color: #ea580c; }

.status-info {
  flex: 1;
}

.status-label {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 4px;
}

.status-details {
  font-size: 13px;
  color: #475569;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.status-details .divider {
  color: #cbd5e1;
}

.icon-sm {
  font-size: 16px !important;
  vertical-align: sub;
}

.status-badge-tag {
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.3px;
}

.badge-complete { background: #dcfce7; color: #15803d; }
.badge-in-progress { background: #dbeafe; color: #1d4ed8; }
.badge-pending { background: #fef3c7; color: #b45309; }

.status-actions {
  display: flex;
  gap: 8px;
}

.btn-warning-action {
  background: #ea580c;
  color: #ffffff;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
}

.btn-ghost-action {
  background: #ffffff;
  color: #475569;
  border: 1px solid #cbd5e1;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
}

/* Stats Overview Grid */
.stats-overview-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.stat-card-modern {
  background: #ffffff;
  border-radius: 14px;
  padding: 20px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card-modern:hover {
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
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-green .card-icon { background: #dcfce7; color: #16a34a; }
.stat-amber .card-icon { background: #fef3c7; color: #d97706; }
.stat-rose .card-icon { background: #ffe4e6; color: #e11d48; }
.stat-indigo .card-icon { background: #e0e7ff; color: #4f46e5; }

.card-trend {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 12px;
}

.text-green { color: #16a34a; background: #f0fdf4; }
.text-amber { color: #d97706; background: #fffbeb; }
.text-rose { color: #e11d48; background: #fff1f2; }
.text-indigo { color: #4f46e5; background: #eef2ff; }

.card-value {
  font-size: 30px;
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

/* Metrics Grid */
.metrics-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.metric-card {
  background: #ffffff;
  border-radius: 14px;
  padding: 20px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.metric-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
}

.metric-title {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
}

.metric-sub {
  font-size: 12px;
  color: #64748b;
  margin-top: 2px;
}

.metric-badge-percent {
  background: #eff6ff;
  color: #2563eb;
  font-size: 18px;
  font-weight: 800;
  padding: 6px 14px;
  border-radius: 10px;
  border: 1px solid #bfdbfe;
}

.metric-icon-box {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #f1f5f9;
  color: #475569;
  display: flex;
  align-items: center;
  justify-content: center;
}

.progress-bar-container {
  height: 10px;
  background: #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 12px;
}

.progress-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #2563eb, #3b82f6);
  border-radius: 10px;
  transition: width 0.6s ease;
}

.progress-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: #64748b;
}

.cert-status-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
}

.tag-unlocked {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

.tag-locked {
  background: #ffe4e6;
  color: #be123c;
  border: 1px solid #fecdd3;
}

.cert-lock-info {
  margin-top: 12px;
  padding: 8px 12px;
  border-radius: 8px;
  background: #fff1f2;
  border: 1px dashed #fecdd3;
  color: #9f1239;
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.cert-unlock-info {
  margin-top: 12px;
  padding: 8px 12px;
  border-radius: 8px;
  background: #f0fdf4;
  border: 1px dashed #bbf7d0;
  color: #166534;
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.duration-display {
  padding: 10px 0;
  margin-bottom: 8px;
}

.duration-value {
  font-size: 28px;
  font-weight: 800;
  color: #0f172a;
}

/* Dashboard Bottom Grid */
.dashboard-bottom-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.quick-nav-panel, .activity-timeline-panel {
  background: #ffffff;
  border-radius: 14px;
  padding: 20px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.panel-heading {
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

.quick-nav-items {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.quick-nav-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  text-decoration: none;
  background: #f8fafc;
  transition: all 0.2s ease;
}

.quick-nav-card:hover {
  background: #ffffff;
  border-color: #3b82f6;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);
}

.nav-card-icon {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-blue { background: #dbeafe; color: #2563eb; }
.icon-emerald { background: #dcfce7; color: #16a34a; }
.icon-amber { background: #fef3c7; color: #d97706; }
.icon-indigo { background: #e0e7ff; color: #4f46e5; }
.icon-rose { background: #ffe4e6; color: #e11d48; }

.nav-card-title {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
}

.nav-card-desc {
  font-size: 11px;
  color: #64748b;
}

/* Timeline */
.timeline-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.timeline-item {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}

.timeline-bullet {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.bullet-checkin { background: #dcfce7; color: #16a34a; }
.bullet-izin { background: #fef3c7; color: #d97706; }
.bullet-info { background: #e2e8f0; color: #64748b; }

.timeline-body {
  flex: 1;
  background: #f8fafc;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2px;
}

.timeline-title {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
}

.timeline-time {
  font-size: 11px;
  color: #94a3b8;
}

.timeline-desc {
  font-size: 12px;
  color: #475569;
}

.timeline-empty {
  text-align: center;
  padding: 30px;
  color: #94a3b8;
  font-size: 13px;
}

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE STYLES
   ═══════════════════════════════════════════════════════════════ */
@media (max-width: 992px) {
  .stats-overview-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .dashboard-bottom-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .metrics-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 576px) {
  .welcome-banner {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
  }

  .welcome-banner::after {
    display: none;
  }

  .welcome-title {
    font-size: 20px;
  }

  .welcome-actions {
    width: 100%;
  }

  .btn-qr-action {
    width: 100%;
    justify-content: center;
  }

  .status-box {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
  }

  .status-badge-tag {
    align-self: flex-start;
  }

  .status-actions {
    width: 100%;
    flex-direction: column;
    gap: 8px;
  }

  .btn-warning-action, .btn-ghost-action {
    width: 100%;
    text-align: center;
    justify-content: center;
    box-sizing: border-radius;
  }

  .stats-overview-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }

  .stat-card-modern {
    padding: 14px;
  }

  .card-value {
    font-size: 22px;
  }

  .card-title {
    font-size: 12px;
  }

  .card-trend {
    font-size: 10px;
    padding: 2px 6px;
  }

  .quick-nav-items {
    grid-template-columns: 1fr;
  }

  .timeline-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
  }
}
</style>
@endsection
