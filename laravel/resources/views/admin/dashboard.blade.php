@extends('layouts.admin')
@section('title', 'Dashboard — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Dashboard Absensi</div>
      <div class="page-sub">{{ Carbon\Carbon::today()->translatedFormat('l, d F Y') }}</div>
      <div style="display:flex;align-items:center;gap:12px;margin-top:8px">
        <span class="material-symbols-outlined" style="font-size:20px;color:var(--primary)">account_circle</span>
        <span style="font-weight:600">{{ Auth::user()->full_name }}</span>
        <span style="color:var(--text-muted)">{{ ucfirst(Auth::user()->role) }}</span>
      </div>
    </div>
    <div class="header-actions">
      <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span> Refresh
      </a>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <span class="material-symbols-outlined stat-icon">group</span>
      <div class="stat-label">Total Mahasiswa</div>
      <div class="stat-value">{{ $totalMahasiswa }}</div>
      <div class="stat-delta">Aktif terdaftar dalam sistem</div>
    </div>
    <div class="stat-card">
      <span class="material-symbols-outlined stat-icon">task_alt</span>
      <div class="stat-label">Hadir Hari Ini</div>
      <div class="stat-value">{{ $presentToday }}</div>
      <div class="stat-delta"><span class="up">{{ $pct }}%</span> kehadiran</div>
    </div>
    <div class="stat-card">
      <span class="material-symbols-outlined stat-icon">person_off</span>
      <div class="stat-label">Tidak Hadir</div>
      <div class="stat-value">{{ $absent }}</div>
      <div class="stat-delta">Belum absen masuk</div>
    </div>
    <div class="stat-card">
      <span class="material-symbols-outlined stat-icon">schedule</span>
      <div class="stat-label">Masih di Lokasi</div>
      <div class="stat-value">{{ $stillIn }}</div>
      <div class="stat-delta">Belum absen keluar</div>
    </div>
  </div>

  <div class="three-col">
    <div class="panel">
      <div class="section-header">
        <div class="section-title">Absensi Terkini</div>
        <a href="{{ route('admin.attendance') }}" class="btn btn-ghost btn-sm">Lihat Semua <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">arrow_forward</span></a>
      </div>
      <table class="att-table">
        <thead>
          <tr><th>Mahasiswa</th><th>Masuk</th><th>Keluar</th><th>Status</th></tr>
        </thead>
        <tbody>
          @forelse($recent as $att)
          <tr>
            <td>
              <div class="mahasiswa-cell">
                <div class="avatar" style="background:var(--primary-light);color:var(--primary)">{{ strtoupper(substr($att->name, 0, 2)) }}</div>
                <div>
                  <div class="mhs-name">{{ $att->name }}</div>
                  <div class="mhs-dept">{{ $att->kompi }}</div>
                </div>
              </div>
            </td>
            <td><span class="time-val">{{ $att->check_in ? Carbon\Carbon::parse($att->check_in)->format('H:i') : '-' }}</span></td>
            <td><span class="time-val">{{ $att->check_out ? Carbon\Carbon::parse($att->check_out)->format('H:i') : '-' }}</span></td>
            <td>
              @php
                $statusClass = match($att->status) {
                    'present', 'hadir' => 'badge-green',
                    'izin' => 'badge-blue',
                    'sakit' => 'badge-yellow',
                    default => 'badge-red'
                };
              @endphp
              <span class="badge {{ $statusClass }}">{{ strtoupper($att->status ?? 'ALPHA') }}</span>
            </td>
          </tr>
          @empty
          <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:30px">Belum ada data absensi hari ini</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
      <div class="panel">
        <div class="section-header">
          <div class="section-title">Tren 7 Hari</div>
        </div>
        <div class="trend-chart">
          <div class="bar-chart">
            @php 
              $trendCounts = array_column($trend, 'count');
              $maxTrend = !empty($trendCounts) ? max($trendCounts) : 1;
              $maxTrend = $maxTrend > 0 ? $maxTrend : 1;
            @endphp
            @foreach($trend as $t)
            <div class="bar-item">
              <div class="bar-fill" style="height:{{ max(8, ($t['count'] / $maxTrend) * 100) }}%;background:var(--primary);border-radius:var(--radius-sm) var(--radius-sm) 0 0;position:relative">
                <span class="bar-val">{{ $t['count'] }}</span>
              </div>
              <span class="bar-label">{{ $t['date'] }}</span>
            </div>
            @endforeach
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="section-header">
          <div class="section-title">Per Kompi</div>
        </div>
        @forelse($byKompi as $k)
        <div class="dept-item">
          <span class="dept-name">{{ $k->kompi }}</span>
          <div class="dept-bar-wrap">
            <div class="dept-bar-fill" style="width:{{ ($k->count / $maxKompi) * 100 }}%;background:var(--primary)"></div>
          </div>
          <span class="dept-count">{{ $k->count }}</span>
        </div>
        @empty
        <p style="color:var(--text-muted);text-align:center;padding:16px">Belum ada data</p>
        @endforelse
      </div>
    </div>
  </div>
</section>
@endsection
