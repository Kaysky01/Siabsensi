@extends('layouts.mahasiswa')
@section('title', 'Absensi Kegiatan & PKKMB — SIABSEN')

@section('content')
<div class="page-header" style="margin-bottom:20px">
  <div>
    <div class="page-title">Absensi Sesi Kegiatan & PKKMB</div>
    <div class="page-sub">Monitoring status absen masuk/keluar harian dan sesi kegiatan PKKMB Anda</div>
  </div>
</div>

@forelse($schedules as $sched)
@php
  $dateStr = \Carbon\Carbon::parse($sched->tanggal)->format('Y-m-d');
  $dailyAtt = $dailyAttendances[$dateStr] ?? null;
  $todayStr = \Carbon\Carbon::today()->format('Y-m-d');
  $isPastOrToday = $dateStr <= $todayStr;
@endphp

<div class="panel" style="margin-bottom:20px;padding:0;overflow:hidden;border:1px solid var(--border,#e2e8f0);border-radius:12px;background:#ffffff">
  
  {{-- Header: Hari & Ringkasan Absen Harian (Masuk & Keluar) --}}
  <div style="background:linear-gradient(to right, #f8fafc, #ffffff);padding:16px 20px;border-bottom:1px solid var(--border,#e2e8f0);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
    
    {{-- Left: Hari & Tanggal --}}
    <div style="display:flex;align-items:center;gap:12px">
      <div style="width:44px;height:44px;border-radius:10px;background:var(--primary-light,#eff6ff);display:flex;align-items:center;justify-content:center;color:var(--primary,#2563eb)">
        <span class="material-symbols-outlined" style="font-size:24px">event_available</span>
      </div>
      <div>
        <div style="font-size:16px;font-weight:700;color:var(--text,#1e293b)">PKKMB Hari ke-{{ $sched->hari_ke }}</div>
        <div style="font-size:13px;color:var(--text-muted,#64748b)">{{ $sched->formatted_date }}</div>
      </div>
    </div>

    {{-- Right: Status Absen Masuk, Keluar, & Titik Status --}}
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
      
      {{-- Jam Masuk --}}
      <div style="text-align:right">
        <div style="font-size:11px;font-weight:600;color:var(--text-muted,#64748b);text-transform:uppercase;letter-spacing:0.5px">Absen Masuk</div>
        <div style="font-size:13px;font-weight:700;color:{{ ($dailyAtt && $dailyAtt->check_in) ? '#047857' : '#9ca3af' }}">
          @if($dailyAtt && $dailyAtt->check_in)
            <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;color:#10b981">login</span>
            {{ \Carbon\Carbon::parse($dailyAtt->check_in)->format('H:i') }} WIB
          @else
            -
          @endif
        </div>
      </div>

      {{-- Jam Keluar --}}
      <div style="text-align:right">
        <div style="font-size:11px;font-weight:600;color:var(--text-muted,#64748b);text-transform:uppercase;letter-spacing:0.5px">Absen Keluar</div>
        <div style="font-size:13px;font-weight:700;color:{{ ($dailyAtt && $dailyAtt->check_out) ? '#047857' : '#9ca3af' }}">
          @if($dailyAtt && $dailyAtt->check_out)
            <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;color:#10b981">logout</span>
            {{ \Carbon\Carbon::parse($dailyAtt->check_out)->format('H:i') }} WIB
          @else
            -
          @endif
        </div>
      </div>

      {{-- Status Badge Harian (Titik Status) --}}
      <div>
        @if($dailyAtt && $dailyAtt->check_in)
          <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#dcfce7;color:#15803d;border-radius:20px;font-size:12px;font-weight:700">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981"></span> Hadir Harian
          </span>
        @elseif($dailyAtt && $dailyAtt->status === 'izin')
          <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#fffbeb;color:#b45309;border-radius:20px;font-size:12px;font-weight:700">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b"></span> Izin
          </span>
        @elseif($dailyAtt && $dailyAtt->status === 'sakit')
          <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#f0f9ff;color:#0369a1;border-radius:20px;font-size:12px;font-weight:700">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#0284c7"></span> Sakit
          </span>
        @elseif($isPastOrToday)
          <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#fef2f2;color:#b91c1c;border-radius:20px;font-size:12px;font-weight:700">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444"></span> Belum Absen (Alpha)
          </span>
        @else
          <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#f3f4f6;color:#6b7280;border-radius:20px;font-size:12px;font-weight:600">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#9ca3af"></span> Belum Dimulai
          </span>
        @endif
      </div>

    </div>
  </div>

  {{-- Body: Daftar Sesi Kegiatan Hari Ini --}}
  <div style="padding:16px 20px">
    <div style="font-size:12px;font-weight:700;color:var(--text-muted,#64748b);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px">
      Daftar Sesi Kegiatan ({{ count($sched->sesi) }} Sesi)
    </div>

    @if(count($sched->sesi) > 0)
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:12px">
      @foreach($sched->sesi as $sesi)
      @php
        $att = $attendancesBySesi[$sesi->id] ?? null;
        $status = $att ? $att->status : 'none';
      @endphp
      <div style="border:1px solid var(--border,#e2e8f0);border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;background:#ffffff">
        <div>
          <div style="font-weight:600;font-size:14px;color:var(--text,#1e293b)">{{ $sesi->nama_sesi }}</div>
          <div style="font-size:12px;color:var(--text-muted,#64748b);margin-top:2px">
            <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">schedule</span>
            {{ $sesi->jam_mulai ? substr($sesi->jam_mulai, 0, 5) : '00:00' }} - {{ $sesi->jam_selesai ? substr($sesi->jam_selesai, 0, 5) : 'Selesai' }}
            @if($att && $att->absen_at)
              • <span style="color:#047857;font-weight:600">Absen: {{ \Carbon\Carbon::parse($att->absen_at)->format('H:i') }}</span>
            @endif
          </div>
        </div>
        <div>
          @if($status === 'present' || $status === 'hadir')
            <span class="badge badge-green" style="display:inline-flex;align-items:center;gap:4px">
              <span class="material-symbols-outlined" style="font-size:14px">check_circle</span> Hadir
            </span>
          @elseif($status === 'alpha')
            <span class="badge badge-red" style="display:inline-flex;align-items:center;gap:4px">
              <span class="material-symbols-outlined" style="font-size:14px">cancel</span> Alpha
            </span>
          @elseif($status === 'izin')
            <span class="badge badge-orange" style="display:inline-flex;align-items:center;gap:4px">
              <span class="material-symbols-outlined" style="font-size:14px">event_note</span> Izin
            </span>
          @elseif($status === 'sakit')
            <span class="badge badge-cyan" style="display:inline-flex;align-items:center;gap:4px">
              <span class="material-symbols-outlined" style="font-size:14px">medical_services</span> Sakit
            </span>
          @else
            <span class="badge badge-gray" style="display:inline-flex;align-items:center;gap:4px">
              <span class="material-symbols-outlined" style="font-size:14px">hourglass_empty</span> Belum Diabsen
            </span>
          @endif
        </div>
      </div>
      @endforeach
    </div>
    @else
    <div style="color:var(--text-muted,#64748b);font-size:13px;padding:8px 0">Belum ada sesi kegiatan yang dibuat untuk hari ini.</div>
    @endif
  </div>

</div>
@empty
<div class="panel" style="text-align:center;padding:40px;color:var(--text-muted)">
  <span class="material-symbols-outlined" style="font-size:48px;opacity:0.4">calendar_today</span>
  <div style="font-size:15px;font-weight:600;margin-top:8px">Belum Ada Master Jadwal PKKMB</div>
  <div style="font-size:13px;margin-top:4px">Jadwal kegiatan PKKMB akan ditampilkan di sini setelah diatur oleh panitia/admin.</div>
</div>
@endforelse

<style>
.badge-orange { background-color: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
.badge-cyan { background-color: #ecfeff; color: #0891b2; border: 1px solid #cffaff; }
.badge-red { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.badge-green { background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.badge-gray { background-color: #f9fafb; color: #6b7280; border: 1px solid #e5e7eb; }
</style>
@endsection
