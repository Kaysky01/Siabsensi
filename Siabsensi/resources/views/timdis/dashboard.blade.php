@extends('layouts.admin')
@section('title', 'Dashboard — SIABSEN')

@section('content')
<style>
  .spotlight-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
  }
  @media (max-width: 992px) {
    .spotlight-grid {
      grid-template-columns: 1fr;
      gap: 14px;
    }
  }

  .spotlight-hero-card {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 60%, #3b82f6 100%);
    border-radius: 14px;
    padding: 20px 24px;
    color: #ffffff;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: center;
    box-shadow: 0 8px 20px -5px rgba(37, 99, 235, 0.35);
  }
  .spotlight-hero-card .hero-bg-icon {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 90px;
    color: rgba(255, 255, 255, 0.12);
    pointer-events: none;
    user-select: none;
  }
  .spotlight-hero-card .hero-label {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    color: #93c5fd;
    margin-bottom: 6px;
  }
  .spotlight-hero-card .hero-val {
    font-size: 40px;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 8px;
    color: #ffffff;
  }
  .spotlight-hero-card .hero-sub {
    font-size: 12px;
    color: #bfdbfe;
    font-weight: 500;
  }

  .spotlight-sub-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .sub-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    position: relative;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .sub-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.05);
  }
  .sub-stat-card .stat-icon-top {
    position: absolute;
    top: 14px;
    right: 14px;
    font-size: 20px;
    color: #94a3b8;
  }
  .sub-stat-card .sub-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 4px;
  }
  .sub-stat-card .sub-val {
    font-size: 26px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
    margin-bottom: 4px;
  }
  .sub-stat-card .sub-sub {
    font-size: 11px;
    color: #64748b;
  }
  .sub-stat-card.full-width {
    grid-column: 1 / -1;
  }

  .dashboard-layout-main {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 16px;
  }

  @media (max-width: 992px) {
    .dashboard-layout-main {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 576px) {
    .spotlight-hero-card {
      padding: 16px 18px;
    }
    .spotlight-hero-card .hero-val {
      font-size: 32px;
    }
    .spotlight-hero-card .hero-bg-icon {
      font-size: 70px;
      right: 10px;
    }
    .spotlight-sub-grid {
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }
    .sub-stat-card {
      padding: 12px;
    }
    .sub-stat-card .sub-val {
      font-size: 22px;
    }
    .sub-stat-card .stat-icon-top {
      font-size: 18px;
      top: 10px;
      right: 10px;
    }
  }
</style>

<section>
  <div class="page-header" style="margin-bottom: 16px;">
    <div>
      <div class="page-title" style="font-size:20px;font-weight:800;color:#0f172a">Dashboard Absensi</div>
      <div class="page-sub" style="font-size:13px;color:#64748b">{{ Carbon\Carbon::today()->translatedFormat('l, d F Y') }}</div>
      <div style="display:flex;align-items:center;gap:6px;margin-top:4px;font-size:12px;color:#64748b">
        <span class="material-symbols-outlined" style="font-size:16px;color:#2563eb">account_circle</span>
        <span style="font-weight:700;color:#0f172a">{{ Auth::user()->full_name }}</span>
        <span>•</span>
        <span>{{ ucfirst(Auth::user()->role) }}</span>
      </div>
    </div>
    <div class="header-actions">
      <a href="{{ route('timdis.dashboard') }}" class="btn btn-ghost btn-sm" style="background:#f1f5f9;border:1px solid #cbd5e1;padding:6px 12px;border-radius:8px;color:#334155;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:4px;text-decoration:none">
        <span class="material-symbols-outlined" style="font-size:15px">refresh</span> Refresh
      </a>
    </div>
  </div>

  <!-- SPOTLIGHT NUMBERS GRID -->
  <div class="spotlight-grid">
    <!-- HERO SPOTLIGHT: TOTAL MAHASISWA -->
    <div class="spotlight-hero-card">
      <span class="material-symbols-outlined hero-bg-icon">groups</span>
      <div class="hero-label">Total Mahasiswa</div>
      <div class="hero-val">{{ number_format($totalMahasiswa) }}</div>
      <div class="hero-sub">Aktif terdaftar dalam sistem</div>
    </div>

    <!-- SECONDARY STATS GRID -->
    <div class="spotlight-sub-grid">
      <div class="sub-stat-card">
        <span class="material-symbols-outlined stat-icon-top" style="color:#2563eb">check_circle</span>
        <div class="sub-label">Hadir Hari Ini</div>
        <div class="sub-val">{{ number_format($presentToday) }}</div>
        <div class="sub-sub"><span style="color:#16a34a;font-weight:700">{{ $pct }}%</span> kehadiran</div>
      </div>

      <div class="sub-stat-card">
        <span class="material-symbols-outlined stat-icon-top" style="color:#94a3b8">person_off</span>
        <div class="sub-label">Tidak Hadir</div>
        <div class="sub-val">{{ number_format($absent) }}</div>
        <div class="sub-sub">Belum absen masuk</div>
      </div>

      <div class="sub-stat-card full-width">
        <span class="material-symbols-outlined stat-icon-top" style="color:#2563eb">schedule</span>
        <div class="sub-label">Masih di Lokasi</div>
        <div class="sub-val">{{ number_format($stillIn) }}</div>
        <div class="sub-sub">Belum absen keluar</div>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT GRID -->
  <div class="dashboard-layout-main">
    <div class="panel" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;box-shadow:0 2px 6px rgba(0,0,0,0.02)">
      <div class="section-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div class="section-title" style="font-size:15px;font-weight:800;color:#0f172a">Absensi Terkini</div>
      </div>
      <table class="att-table" style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="border-bottom:2px solid #f1f5f9;text-align:left">
            <th style="padding:10px 8px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Mahasiswa</th>
            <th style="padding:10px 8px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Kegiatan / Sesi</th>
            <th style="padding:10px 8px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Masuk</th>
            <th style="padding:10px 8px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Keluar</th>
            <th style="padding:10px 8px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recent as $att)
          <tr style="border-bottom:1px solid #f8fafc">
            <td style="padding:10px 8px">
              <div class="mahasiswa-cell" style="display:flex;align-items:center;gap:10px">
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
                  <img src="{{ $photoUrl }}" alt="{{ $att->name }}" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #3b82f6;flex-shrink:0;">
                @else
                  <div class="avatar" style="background:#dbeafe;color:#2563eb;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">{{ strtoupper(substr($att->name, 0, 2)) }}</div>
                @endif
                <div>
                  <div class="mhs-name" style="font-weight:700;color:#0f172a;font-size:12px">{{ $att->name }}</div>
                  <div class="mhs-dept" style="font-size:10px;color:#64748b">{{ $att->kompi }}</div>
                </div>
              </div>
            </td>
            <td style="padding:10px 8px">
              @if(isset($att->sesi))
                <span class="badge" style="background:#dbeafe;color:#1d4ed8;padding:3px 8px;border-radius:12px;font-size:10px;font-weight:700">{{ $att->sesi->nama_sesi }}</span>
              @else
                <span style="color:#94a3b8;font-size:12px">-</span>
              @endif
            </td>
            <td style="padding:10px 8px"><span class="time-val" style="font-weight:600;color:#334155;font-size:12px">{{ $att->check_in ? Carbon\Carbon::parse($att->check_in)->format('H:i') : '-' }}</span></td>
            <td style="padding:10px 8px"><span class="time-val" style="font-weight:600;color:#334155;font-size:12px">{{ $att->check_out ? Carbon\Carbon::parse($att->check_out)->format('H:i') : '-' }}</span></td>
            <td style="padding:10px 8px">
              @php
                $badge = $att->getStatusBadgeData();
              @endphp
              <span class="badge" style="padding:4px 10px;border-radius:12px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:4px;background:{{ $badge['bg'] }};color:{{ $badge['color'] }};border:1px solid {{ $badge['border'] }}">
                <span style="width:8px;height:8px;border-radius:50%;background:{{ $badge['dot'] }};display:inline-block"></span>
                {{ $badge['label'] }}
              </span>
            </td>
          </tr>
          @empty
          <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:24px">Belum ada data absensi hari ini</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
      <!-- TREN 7 HARI -->
      <div class="panel" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;box-shadow:0 2px 6px rgba(0,0,0,0.02)">
        <div class="section-header" style="margin-bottom:12px">
          <div class="section-title" style="font-size:14px;font-weight:800;color:#0f172a">Tren 7 Hari</div>
        </div>
        <div class="trend-chart">
          <div class="bar-chart" style="display:flex;align-items:flex-end;gap:6px;height:120px;padding-top:16px">
            @php 
              $trendCounts = array_column($trend, 'count');
              $maxTrend = !empty($trendCounts) ? max($trendCounts) : 1;
              $maxTrend = $maxTrend > 0 ? $maxTrend : 1;
            @endphp
            @foreach($trend as $t)
            <div class="bar-item" style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%">
              <div class="bar-fill" style="width:100%;height:{{ max(8, ($t['count'] / $maxTrend) * 100) }}%;background:#3b82f6;border-radius:4px 4px 0 0;position:relative;margin-top:auto">
                <span class="bar-val" style="position:absolute;top:-16px;left:50%;transform:translateX(-50%);font-size:9px;font-weight:700;color:#2563eb">{{ $t['count'] }}</span>
              </div>
              <span class="bar-label" style="font-size:9px;color:#64748b;margin-top:4px;font-weight:600">{{ $t['date'] }}</span>
            </div>
            @endforeach
          </div>
        </div>
      </div>

      <!-- PER KOMPI -->
      <div class="panel" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;box-shadow:0 2px 6px rgba(0,0,0,0.02)">
        <div class="section-header" style="margin-bottom:12px">
          <div class="section-title" style="font-size:14px;font-weight:800;color:#0f172a">Per Kompi</div>
        </div>
        @forelse($byKompi as $k)
        <div class="dept-item" style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
          <span class="dept-name" style="font-size:11px;font-weight:700;color:#334155;width:65px">{{ $k->kompi }}</span>
          <div class="dept-bar-wrap" style="flex:1;background:#f1f5f9;height:7px;border-radius:4px;overflow:hidden">
            <div class="dept-bar-fill" style="width:{{ ($k->count / $maxKompi) * 100 }}%;background:#2563eb;height:100%;border-radius:4px"></div>
          </div>
          <span class="dept-count" style="font-size:11px;font-weight:700;color:#0f172a;width:28px;text-align:right">{{ $k->count }}</span>
        </div>
        @empty
        <p style="color:#94a3b8;text-align:center;padding:12px;font-size:12px">Belum ada data</p>
        @endforelse
      </div>
    </div>
  </div>
</section>
@endsection
