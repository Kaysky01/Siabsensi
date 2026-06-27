@extends('layouts.mahasiswa')
@section('title', 'Kegiatan — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Absensi Kegiatan</div>
    <div class="page-sub">Lakukan absensi untuk kegiatan tambahan di luar jadwal reguler</div>
  </div>
</div>

<div class="panel" style="margin-bottom:24px">
  <div class="section-header">
    <div class="section-title">Kegiatan Tersedia Hari Ini</div>
  </div>
  
  @if($kegiatanTersedia->count() > 0)
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;">
      @foreach($kegiatanTersedia as $keg)
      <div style="border:1px solid var(--border);border-radius:12px;padding:16px;background:var(--bg)">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
          <div>
            <div style="font-weight:600;font-size:16px">{{ $keg->nama }}</div>
            <div style="font-size:13px;color:var(--text-muted)">
              {{ Carbon\Carbon::parse($keg->tanggal_pelaksanaan)->format('d M Y') }} • {{ substr($keg->jam_mulai,0,5) }} - {{ substr($keg->jam_selesai,0,5) }}
            </div>
          </div>
          <span class="badge {{ $keg->wajib_hadir ? 'badge-red' : 'badge-blue' }}">
            {{ $keg->wajib_hadir ? 'Wajib' : 'Opsional' }}
          </span>
        </div>
        
        <div style="background:var(--primary-light);padding:12px;border-radius:8px;text-align:center;margin-top:16px">
          <span class="material-symbols-outlined" style="font-size:24px;color:var(--primary);margin-bottom:4px">qr_code_scanner</span>
          <div style="font-size:13px;color:var(--primary);font-weight:600">
            Silakan lakukan absensi kehadiran langsung di lokasi kegiatan menggunakan kamera/scanner .
          </div>
        </div>
      </div>
      @endforeach
    </div>
  @else
    <div style="padding:40px 20px;text-align:center;color:var(--text-muted)">
      <span class="material-symbols-outlined" style="font-size:48px;opacity:0.5;margin-bottom:12px">event_busy</span>
      <div>Tidak ada kegiatan yang aktif atau tersedia untuk diabsen saat ini.</div>
    </div>
  @endif
</div>

<div class="panel">
  <div class="section-header">
    <div class="section-title">Riwayat Absensi Kegiatan</div>
  </div>
  
  <table class="att-table">
    <thead>
      <tr>
        <th>Kegiatan</th>
        <th>Waktu Absen</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($riwayatKegiatan as $riwayat)
      <tr>
        <td>
          <div style="font-weight:600">{{ optional($riwayat->kegiatan)->nama ?? 'Kegiatan Tidak Diketahui' }}</div>
          <div style="font-size:12px;color:var(--text-muted)">{{ Carbon\Carbon::parse($riwayat->date)->format('d M Y') }}</div>
        </td>
        <td>
          Check-in: {{ date('H:i', strtotime($riwayat->check_in)) }}<br>
          Check-out: {{ $riwayat->check_out ? date('H:i', strtotime($riwayat->check_out)) : '-' }}
        </td>
        <td>
          <span class="badge badge-green">Hadir</span>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">Belum ada riwayat kegiatan.</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
