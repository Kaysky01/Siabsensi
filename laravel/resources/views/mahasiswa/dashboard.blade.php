@extends('layouts.mahasiswa')
@section('title', 'Dashboard — Portal Mahasiswa')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Portal Mahasiswa SIABSEN</div>
    <div class="page-sub">Selamat datang, {{ $mahasiswa->name }}!</div>
  </div>
  <div class="header-actions">
    <a href="{{ route('mahasiswa.dashboard') }}" class="btn btn-ghost btn-sm">
      <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span>
      Refresh
    </a>
  </div>
</div>

<section id="section-dashboard">
  <!-- Today's Attendance Status -->
  <div style="margin-bottom:20px">
    @php
      $today = \Carbon\Carbon::today()->toDateString();
      $todayAtt = $mahasiswa->attendances()->where('date', $today)->first();
    @endphp
    @if($todayAtt)
      <div class="panel" style="background:var(--success-light);border-color:var(--success);display:flex;align-items:center;gap:12px">
        <span class="material-symbols-outlined" style="color:var(--success);font-size:32px">check_circle</span>
        <div>
          <div style="font-weight:600;color:var(--success)">Anda sudah absen hari ini</div>
          <div style="font-size:13px;color:var(--text-secondary)">Waktu Check-In: {{ \Carbon\Carbon::parse($todayAtt->check_in)->format('H:i') }}</div>
        </div>
      </div>
    @else
      <div class="panel" style="background:var(--warning-light);border-color:#F59E0B;display:flex;align-items:center;gap:12px">
        <span class="material-symbols-outlined" style="color:#F59E0B;font-size:32px">info</span>
        <div>
          <div style="font-weight:600;color:#B45309">Anda belum absen hari ini</div>
          <div style="font-size:13px;color:#92400E">Silakan lakukan absensi melalui kamera gerbang atau ajukan kehadiran.</div>
        </div>
      </div>
    @endif
  </div>
  
  <!-- Dashboard Statistics -->
  <div>
    <!-- Stats Cards Grid -->
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:16px">
      <div class="stat-card">
        <span class="material-symbols-outlined stat-icon" style="color:var(--primary);background:var(--primary-light);padding:8px;border-radius:8px">calendar_month</span>
        <div class="stat-label" style="margin-top:12px;font-size:13px;color:var(--text-muted)">Total Hari Hadir</div>
        <div class="stat-value" style="font-size:24px;font-weight:700">{{ $stats['totalHadir'] }}</div>
      </div>
      <div class="stat-card">
        <span class="material-symbols-outlined stat-icon" style="color:var(--success);background:var(--success-light);padding:8px;border-radius:8px">check_circle</span>
        <div class="stat-label" style="margin-top:12px;font-size:13px;color:var(--text-muted)">Hadir Bulan Ini</div>
        <div class="stat-value" style="font-size:24px;font-weight:700">{{ $stats['hadirBulanIni'] }}</div>
      </div>
      <div class="stat-card">
        <span class="material-symbols-outlined stat-icon" style="color:#D97706;background:var(--warning-light);padding:8px;border-radius:8px">schedule</span>
        <div class="stat-label" style="margin-top:12px;font-size:13px;color:var(--text-muted)">Izin/Sakit</div>
        <div class="stat-value" style="font-size:24px;font-weight:700">{{ $stats['totalIzin'] }}</div>
      </div>
      <div class="stat-card">
        <span class="material-symbols-outlined stat-icon" style="color:var(--danger);background:var(--danger-light);padding:8px;border-radius:8px">event_busy</span>
        <div class="stat-label" style="margin-top:12px;font-size:13px;color:var(--text-muted)">Tidak Hadir (Alpha)</div>
        <div class="stat-value" style="font-size:24px;font-weight:700">{{ $stats['tidakHadir'] }}</div>
      </div>
    </div>

    <!-- Additional Stats Row -->
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px">
      <div class="stat-card">
        <span class="material-symbols-outlined stat-icon" style="color:var(--info);background:var(--info-light);padding:8px;border-radius:8px">percent</span>
        <div class="stat-label" style="margin-top:12px;font-size:13px;color:var(--text-muted)">Persentase Kehadiran</div>
        <div class="stat-value" style="font-size:24px;font-weight:700">{{ $stats['persentaseKehadiran'] }}%</div>
      </div>
      <div class="stat-card">
        <span class="material-symbols-outlined stat-icon" style="color:var(--text);background:var(--border-light);padding:8px;border-radius:8px">avg_time</span>
        <div class="stat-label" style="margin-top:12px;font-size:13px;color:var(--text-muted)">Rata-rata Durasi</div>
        <div class="stat-value" style="font-size:24px;font-weight:700">{{ $stats['rataRataDurasi'] }}</div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="panel">
      <div class="section-header">
        <div class="section-title">
          <span class="material-symbols-outlined">notifications</span>
          Aktivitas Terbaru
        </div>
      </div>
      <div>
        @forelse($recentActivities as $activity)
        <div class="activity-item">
          <div class="activity-icon" style="background:{{ $activity->type === 'checkin' ? 'var(--success-light)' : 'var(--info-light)' }};color:{{ $activity->type === 'checkin' ? 'var(--success)' : 'var(--info)' }}">
            <span class="material-symbols-outlined" style="font-size:18px">{{ $activity->type === 'checkin' ? 'login' : 'info' }}</span>
          </div>
          <div class="activity-content">
            <div class="activity-title">{{ $activity->title }}</div>
            <div class="activity-desc">{{ $activity->description }}</div>
            <div class="activity-time">{{ $activity->timestamp }}</div>
          </div>
        </div>
        @empty
        <div class="empty-state" style="padding:30px;text-align:center;color:var(--text-muted)">Belum ada aktivitas kehadiran</div>
        @endforelse
      </div>
    </div>
  </div>
</section>
@endsection
