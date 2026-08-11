@extends('layouts.admin')
@section('title', 'Dashboard Tim Acara — SIABSEN')

@section('content')
<div class="acara-dashboard-wrapper">
  <!-- Welcome Hero Banner -->
  <div class="acara-welcome-banner">
    <div class="banner-content">
      <div class="banner-badge">
        <span class="pulse-dot"></span> TIM ACARA PKKMB {{ date('Y') }}
      </div>
      <h1 class="banner-title">Selamat Datang, {{ auth()->user()->full_name ?? auth()->user()->username }}!</h1>
      <p class="banner-sub">
        <span class="material-symbols-outlined icon-sm">calendar_today</span> {{ Carbon\Carbon::today()->translatedFormat('l, d F Y') }} • Pengelola Agenda & Sesi Kegiatan PKKMB
      </p>
    </div>
    <div class="banner-actions">
      <a href="{{ route('acara.pkkmb-schedule.index') }}" class="btn btn-banner-primary">
        <span class="material-symbols-outlined">calendar_month</span> Kelola Jadwal
      </a>
      <a href="{{ route('acara.kegiatan') }}" class="btn btn-banner-secondary">
        <span class="material-symbols-outlined">edit_calendar</span> Kelola Sesi
      </a>
      <a href="{{ route('acara.dashboard') }}" class="btn btn-banner-ghost" title="Refresh Data">
        <span class="material-symbols-outlined">refresh</span>
      </a>
    </div>
  </div>

  <!-- Stats Grid -->
  <div class="acara-stats-grid">
    <!-- Stat 1: Total Jadwal -->
    <div class="acara-stat-card card-blue">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">calendar_month</span>
        </div>
        <span class="card-badge bg-blue">{{ $activeSchedules }} Active</span>
      </div>
      <div class="card-value">{{ number_format($totalSchedules) }} <span class="unit-text">Hari</span></div>
      <div class="card-title">Total Jadwal PKKMB</div>
      <div class="card-desc">Agenda harian PKKMB</div>
    </div>

    <!-- Stat 2: Total Sesi Kegiatan -->
    <div class="acara-stat-card card-emerald">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">edit_calendar</span>
        </div>
        <span class="card-badge bg-emerald">{{ $activeSesi }} Active</span>
      </div>
      <div class="card-value">{{ number_format($totalSesi) }} <span class="unit-text">Sesi</span></div>
      <div class="card-title">Total Sesi Kegiatan</div>
      <div class="card-desc">Sesi terlaksana per-hari</div>
    </div>

    <!-- Stat 3: Toleransi Keterlambatan -->
    <div class="acara-stat-card card-amber">
      <div class="card-top">
        <div class="card-icon">
          <span class="material-symbols-outlined">schedule</span>
        </div>
        <span class="card-badge bg-amber">Grace Period</span>
      </div>
      <div class="card-value">{{ number_format($gracePeriod) }} <span class="unit-text">Menit</span></div>
      <div class="card-title">Toleransi Keterlambatan</div>
      <div class="card-desc">Batas keterlambatan sistem</div>
    </div>
  </div>

  <!-- Quick Actions Panel Grid -->
  <div class="acara-actions-grid">
    <a href="{{ route('acara.pkkmb-schedule.index') }}" class="action-card-item card-border-blue">
      <div class="action-card-icon icon-blue">
        <span class="material-symbols-outlined">calendar_month</span>
      </div>
      <div class="action-card-info">
        <div class="action-card-title">Kelola Jadwal Absensi PKKMB</div>
        <div class="action-card-sub">Atur tanggal, jam check-in & check-out harian PKKMB</div>
      </div>
      <span class="material-symbols-outlined action-arrow">arrow_forward</span>
    </a>

    <a href="{{ route('acara.kegiatan') }}" class="action-card-item card-border-indigo">
      <div class="action-card-icon icon-indigo">
        <span class="material-symbols-outlined">edit_calendar</span>
      </div>
      <div class="action-card-info">
        <div class="action-card-title">Kelola Sesi Kegiatan</div>
        <div class="action-card-sub">Tambah & susun sesi per-kegiatan di setiap hari PKKMB</div>
      </div>
      <span class="material-symbols-outlined action-arrow">arrow_forward</span>
    </a>
  </div>

  <!-- Schedule Summary Panel -->
  <div class="acara-panel">
    <div class="panel-header">
      <div>
        <h2 class="panel-title">
          <span class="material-symbols-outlined">event_note</span>
          Daftar Ringkasan Jadwal & Sesi PKKMB
        </h2>
        <div class="panel-sub">Daftar agenda harian beserta jumlah sesi kegiatan yang terdaftar</div>
      </div>
    </div>

    <div class="schedule-list">
      @forelse($upcomingSchedules as $sch)
        <div class="schedule-card-row">
          <div class="sch-left-info">
            <div class="sch-title-row">
              <span class="sch-day-title">PKKMB Hari ke-{{ $sch->hari_ke }}</span>
              <span class="sch-status-tag {{ $sch->is_active ? 'tag-active' : 'tag-inactive' }}">
                {{ $sch->is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </div>
            <div class="sch-meta-text">
              📅 {{ $sch->formatted_date }} • 🕒 Check-In: {{ Carbon\Carbon::parse($sch->check_in_start)->format('H:i') }} - {{ Carbon\Carbon::parse($sch->check_in_end)->format('H:i') }} WIB
            </div>
          </div>
          <div class="sch-right-badge">
            <span class="sesi-count-pill">
              <span class="material-symbols-outlined icon-xs">view_timeline</span>
              {{ $sch->sesi->count() }} Sesi Kegiatan
            </span>
          </div>
        </div>
      @empty
        <div class="table-empty-state">
          <span class="material-symbols-outlined">event_busy</span>
          <div>Belum ada jadwal PKKMB yang dikonfigurasi.</div>
        </div>
      @endforelse
    </div>
  </div>
</div>

<style>
/* CSS Styles for Acara Dashboard */
.acara-dashboard-wrapper {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1350px;
  margin: 0 auto;
}

/* Welcome Hero Banner */
.acara-welcome-banner {
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

.acara-welcome-banner::after {
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

/* Stats Grid */
.acara-stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.acara-stat-card {
  background: #ffffff;
  border-radius: 14px;
  padding: 22px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.acara-stat-card:hover {
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
.card-amber .card-icon { background: #fef3c7; color: #d97706; }

.card-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 12px;
}

.bg-blue { background: #eff6ff; color: #2563eb; }
.bg-emerald { background: #f0fdf4; color: #16a34a; }
.bg-amber { background: #fffbeb; color: #d97706; }

.card-value {
  font-size: 32px;
  font-weight: 800;
  color: #0f172a;
  line-height: 1;
  margin-bottom: 6px;
}

.unit-text {
  font-size: 14px;
  font-weight: 600;
  color: #64748b;
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

/* Quick Actions Grid */
.acara-actions-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.action-card-item {
  background: #ffffff;
  border-radius: 14px;
  padding: 22px;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  gap: 16px;
  text-decoration: none;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  transition: all 0.2s ease;
}

.card-border-blue { border-left: 5px solid #2563eb; }
.card-border-indigo { border-left: 5px solid #4f46e5; }

.action-card-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(37, 99, 235, 0.12);
  border-color: #3b82f6;
}

.action-card-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.icon-blue { background: #dbeafe; color: #2563eb; }
.icon-indigo { background: #e0e7ff; color: #4f46e5; }

.action-card-info {
  flex: 1;
}

.action-card-title {
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
}

.action-card-sub {
  font-size: 12px;
  color: #64748b;
  margin-top: 2px;
}

.action-arrow {
  color: #cbd5e1;
  font-size: 20px !important;
  transition: transform 0.2s ease;
}

.action-card-item:hover .action-arrow {
  transform: translateX(4px);
  color: #2563eb;
}

/* Schedule Panel */
.acara-panel {
  background: #ffffff;
  border-radius: 14px;
  padding: 22px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.panel-header {
  margin-bottom: 18px;
  padding-bottom: 14px;
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

.schedule-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.schedule-card-row {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 16px 20px;
  border-radius: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  transition: all 0.2s ease;
}

.schedule-card-row:hover {
  background: #ffffff;
  border-color: #cbd5e1;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.sch-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
}

.sch-day-title {
  font-size: 15px;
  font-weight: 800;
  color: #0f172a;
}

.sch-status-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}

.tag-active { background: #dcfce7; color: #15803d; }
.tag-inactive { background: #f1f5f9; color: #64748b; }

.sch-meta-text {
  font-size: 12px;
  color: #64748b;
}

.sesi-count-pill {
  font-size: 12px;
  font-weight: 700;
  background: #eff6ff;
  color: #1d4ed8;
  padding: 6px 14px;
  border-radius: 20px;
  border: 1px solid #bfdbfe;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.table-empty-state {
  text-align: center;
  padding: 40px !important;
  color: #94a3b8;
}

.table-empty-state span {
  font-size: 40px;
  margin-bottom: 8px;
}

.icon-sm { font-size: 16px !important; }
.icon-xs { font-size: 14px !important; vertical-align: middle; }

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE MOBILE STYLES
   ═══════════════════════════════════════════════════════════════ */
@media (max-width: 992px) {
  .acara-stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .acara-actions-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 576px) {
  .acara-welcome-banner {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
  }

  .acara-welcome-banner::after {
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

  .acara-stats-grid {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .schedule-card-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
}
</style>
@endsection
