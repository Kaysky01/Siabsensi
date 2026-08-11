@extends('layouts.mahasiswa')
@section('title', 'Riwayat Kehadiran — Portal Mahasiswa')

@section('content')
<style>
  .mhs-riwayat-wrapper {
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-width: 1000px;
    margin: 0 auto;
  }

  .riwayat-header-card {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #3b82f6 100%);
    border-radius: 14px;
    padding: 18px 22px;
    color: #ffffff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 8px 20px -5px rgba(37, 99, 235, 0.25);
  }

  .riwayat-header-title {
    font-size: 20px;
    font-weight: 800;
    margin: 0 0 4px 0;
  }

  .riwayat-header-sub {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.85);
    margin: 0;
  }

  /* Summary Stat Pills */
  .riwayat-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
  }

  .riwayat-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
  }

  .riwayat-stat-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .riwayat-stat-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.5px;
  }

  .riwayat-stat-val {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
    margin-top: 2px;
  }

  /* Desktop Table Style */
  .riwayat-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 0;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
  }

  .riwayat-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
  }

  .riwayat-table thead th {
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 14px 16px;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
  }

  .riwayat-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
  }

  .riwayat-table tbody tr:hover {
    background: #f8fafc;
  }

  /* Mobile Timeline Card view (hidden by default on desktop) */
  .mobile-riwayat-list {
    display: none;
    flex-direction: column;
    gap: 10px;
  }

  .mobile-riwayat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .mobile-riwayat-card.status-border-lengkap { border-left: 4px solid #10b981; }
  .mobile-riwayat-card.status-border-masuk { border-left: 4px solid #1f2937; }
  .mobile-riwayat-card.status-border-izin { border-left: 4px solid #3b82f6; }
  .mobile-riwayat-card.status-border-sakit { border-left: 4px solid #eab308; }
  .mobile-riwayat-card.status-border-alpha { border-left: 4px solid #ef4444; }

  .mobile-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .mobile-date-title {
    font-weight: 800;
    font-size: 14px;
    color: #0f172a;
  }

  .mobile-date-sub {
    font-size: 11px;
    color: #64748b;
  }

  .mobile-time-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    background: #f8fafc;
    border-radius: 8px;
    padding: 8px 10px;
  }

  .mobile-time-box {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
  }

  .mobile-time-box .time-label {
    font-size: 10px;
    color: #64748b;
    font-weight: 600;
    display: block;
  }

  .mobile-time-box .time-val {
    font-weight: 700;
    color: #0f172a;
  }

  /* Legenda Status Bar */
  .status-legend-bar {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    font-size: 11px;
    color: #64748b;
    padding: 10px 14px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
  }
  .legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
  }
  .legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
  }

  @media (max-width: 768px) {
    .riwayat-stats-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 576px) {
    .riwayat-header-card {
      padding: 14px 16px;
    }
    .riwayat-header-title {
      font-size: 16px;
    }
    .desktop-table-container {
      display: none;
    }
    .mobile-riwayat-list {
      display: flex;
    }
  }
</style>

<div class="mhs-riwayat-wrapper">
  <!-- Header Banner -->
  <div class="riwayat-header-card">
    <div>
      <h1 class="riwayat-header-title">Riwayat Kehadiran</h1>
      <p class="riwayat-header-sub">Catatan lengkap presensi harian PKKMB {{ date('Y') }}</p>
    </div>
    <div style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;color:#fff">
      <span class="material-symbols-outlined" style="font-size:22px">history</span>
    </div>
  </div>

  @php
    $totalLengkap = 0;
    $totalMasukBelumKeluar = 0;
    $totalIzin = 0;
    $totalAlpha = 0;
    foreach($riwayat as $r) {
      $statusLower = strtolower($r->status);
      if ($r->isLengkap()) {
        $totalLengkap++;
      } elseif ($r->isMasihDiLokasi()) {
        $totalMasukBelumKeluar++;
      } elseif ($statusLower === 'izin' || $statusLower === 'sakit') {
        $totalIzin++;
      } else {
        $totalAlpha++;
      }
    }
  @endphp

  <!-- Summary Stats -->
  <div class="riwayat-stats-grid">
    <div class="riwayat-stat-card">
      <div class="riwayat-stat-icon" style="background:#dcfce7;color:#16a34a">
        <span class="material-symbols-outlined" style="font-size:18px">check_circle</span>
      </div>
      <div>
        <div class="riwayat-stat-label">Lengkap</div>
        <div class="riwayat-stat-val">{{ $totalLengkap }}</div>
      </div>
    </div>

    <div class="riwayat-stat-card">
      <div class="riwayat-stat-icon" style="background:#1e293b;color:#ffffff">
        <span class="material-symbols-outlined" style="font-size:18px">login</span>
      </div>
      <div>
        <div class="riwayat-stat-label">Belum Keluar</div>
        <div class="riwayat-stat-val">{{ $totalMasukBelumKeluar }}</div>
      </div>
    </div>

    <div class="riwayat-stat-card">
      <div class="riwayat-stat-icon" style="background:#dbeafe;color:#2563eb">
        <span class="material-symbols-outlined" style="font-size:18px">description</span>
      </div>
      <div>
        <div class="riwayat-stat-label">Izin / Sakit</div>
        <div class="riwayat-stat-val">{{ $totalIzin }}</div>
      </div>
    </div>

    <div class="riwayat-stat-card">
      <div class="riwayat-stat-icon" style="background:#fee2e2;color:#dc2626">
        <span class="material-symbols-outlined" style="font-size:18px">cancel</span>
      </div>
      <div>
        <div class="riwayat-stat-label">Alpha</div>
        <div class="riwayat-stat-val">{{ $totalAlpha }}</div>
      </div>
    </div>
  </div>

  <!-- LEGENDA STATUS WARNA -->
  <div class="status-legend-bar">
    <span style="font-weight:700;color:#0f172a">Keterangan:</span>
    <span class="legend-item"><span class="legend-dot" style="background:#10b981"></span> Lengkap / Hadir</span>
    <span class="legend-item"><span class="legend-dot" style="background:#1f2937"></span> Masuk (belum keluar)</span>
    <span class="legend-item"><span class="legend-dot" style="background:#ef4444"></span> Alpha</span>
    <span class="legend-item"><span class="legend-dot" style="background:#3b82f6"></span> Izin</span>
    <span class="legend-item"><span class="legend-dot" style="background:#eab308"></span> Sakit</span>
    <span class="legend-item"><span class="legend-dot" style="background:#d1d5db"></span> Belum ada</span>
  </div>

  <!-- Desktop Table Container -->
  <div class="riwayat-panel desktop-table-container">
    <div style="overflow-x:auto">
      <table class="riwayat-table">
        <thead>
          <tr>
            <th style="white-space:nowrap">Hari & Tanggal</th>
            <th style="white-space:nowrap">Jam Masuk</th>
            <th style="white-space:nowrap">Jam Keluar</th>
            <th style="white-space:nowrap">Status Kehadiran</th>
            <th style="white-space:nowrap">Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @forelse($riwayat as $r)
          @php
            $badge = $r->getStatusBadgeData();
          @endphp
          <tr>
            <td>
              <div style="font-weight:700;color:#0f172a;font-size:13px">{{ Carbon\Carbon::parse($r->date)->translatedFormat('l') }}</div>
              <div style="font-size:11px;color:#64748b;margin-top:1px">{{ Carbon\Carbon::parse($r->date)->format('d M Y') }}</div>
            </td>
            <td>
              @if($r->check_in)
                <div style="display:flex;align-items:center;gap:6px">
                  <span class="material-symbols-outlined" style="font-size:16px;color:#16a34a">login</span>
                  <span style="font-weight:700;color:#0f172a;font-size:13px">{{ date('H:i', strtotime($r->check_in)) }}</span>
                </div>
              @else
                <span style="color:#94a3b8;font-size:13px">-</span>
              @endif
            </td>
            <td>
              @if($r->check_out)
                <div style="display:flex;align-items:center;gap:6px">
                  <span class="material-symbols-outlined" style="font-size:16px;color:#2563eb">logout</span>
                  <span style="font-weight:700;color:#0f172a;font-size:13px">{{ date('H:i', strtotime($r->check_out)) }}</span>
                </div>
              @else
                <span style="color:#94a3b8;font-size:13px">-</span>
              @endif
            </td>
            <td>
              <span style="background:{{ $badge['bg'] }};color:{{ $badge['color'] }};border:1px solid {{ $badge['border'] }};font-weight:700;padding:4px 10px;border-radius:20px;font-size:11px;display:inline-flex;align-items:center;gap:4px">
                <span class="legend-dot" style="background:{{ $badge['dot'] }}"></span>
                {{ $badge['label'] }}
              </span>
            </td>
            <td style="font-size:12px;color:#64748b">
              @if(!empty($r->absen_by))
                Absen manual oleh {{ $r->absen_by }}
              @elseif($r->isLengkap())
                Presensi lengkap (Masuk & Keluar)
              @elseif($r->isMasihDiLokasi())
                Belum melakukan absen keluar
              @else
                -
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="5" style="text-align:center;padding:40px;color:#94a3b8">
              <span class="material-symbols-outlined" style="font-size:40px;display:block;margin-bottom:8px;color:#cbd5e1">event_busy</span>
              Belum ada data riwayat absensi.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Mobile Timeline Cards Container -->
  <div class="mobile-riwayat-list">
    @forelse($riwayat as $r)
    @php
      $badge = $r->getStatusBadgeData();
      $statusLower = strtolower($r->status ?? 'alpha');
      
      $borderClass = 'status-border-alpha';
      if ($r->isLengkap()) {
        $borderClass = 'status-border-lengkap';
      } elseif ($r->isMasihDiLokasi()) {
        $borderClass = 'status-border-masuk';
      } elseif ($statusLower === 'izin') {
        $borderClass = 'status-border-izin';
      } elseif ($statusLower === 'sakit') {
        $borderClass = 'status-border-sakit';
      }
    @endphp

    <div class="mobile-riwayat-card {{ $borderClass }}">
      <div class="mobile-card-header">
        <div>
          <div class="mobile-date-title">{{ Carbon\Carbon::parse($r->date)->translatedFormat('l') }}</div>
          <div class="mobile-date-sub">{{ Carbon\Carbon::parse($r->date)->format('d M Y') }}</div>
        </div>

        <div>
          <span style="background:{{ $badge['bg'] }};color:{{ $badge['color'] }};font-weight:700;padding:3px 8px;border-radius:12px;font-size:10px;display:inline-flex;align-items:center;gap:3px">
            <span class="legend-dot" style="background:{{ $badge['dot'] }}"></span> {{ $badge['label'] }}
          </span>
        </div>
      </div>

      <div class="mobile-time-grid">
        <div class="mobile-time-box">
          <span class="material-symbols-outlined" style="font-size:16px;color:#16a34a">login</span>
          <div>
            <span class="time-label">Jam Masuk</span>
            <span class="time-val">{{ $r->check_in ? date('H:i', strtotime($r->check_in)) : '-' }}</span>
          </div>
        </div>

        <div class="mobile-time-box">
          <span class="material-symbols-outlined" style="font-size:16px;color:#2563eb">logout</span>
          <div>
            <span class="time-label">Jam Keluar</span>
            <span class="time-val">{{ $r->check_out ? date('H:i', strtotime($r->check_out)) : '-' }}</span>
          </div>
        </div>
      </div>
    </div>
    @empty
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:30px;text-align:center;color:#94a3b8">
      <span class="material-symbols-outlined" style="font-size:36px;display:block;margin-bottom:6px;color:#cbd5e1">event_busy</span>
      Belum ada data riwayat absensi.
    </div>
    @endforelse
  </div>
</div>
@endsection
