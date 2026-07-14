@extends('layouts.mahasiswa')
@section('title', 'Monitoring Kegiatan — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Monitoring Kegiatan</div>
    <div class="page-sub">Riwayat absensi sesi kegiatan dan PKKMB Anda</div>
  </div>
</div>

<div class="panel">
  <div class="section-header">
    <div class="section-title">Riwayat Absensi Sesi</div>
    <div class="section-sub">Menampilkan semua sesi kegiatan yang sudah Anda ikuti</div>
  </div>
  
  <table class="att-table">
    <thead>
      <tr>
        <th>Kegiatan / Sesi</th>
        <th>Tanggal</th>
        <th>Waktu Absen</th>
        <th>Status</th>
        <th>Diabsen Oleh</th>
      </tr>
    </thead>
    <tbody>
      @forelse($riwayatSesi as $sesi)
      <tr>
        <td>
          <div style="display:flex;align-items:start;gap:8px">
            <span class="material-symbols-outlined" style="font-size:20px;color:var(--primary);margin-top:2px">calendar_view_day</span>
            <div>
              <div style="font-weight:600;margin-bottom:4px">{{ optional($sesi->sesi)->display_name ?? 'Sesi Tidak Diketahui' }}</div>
              @if($sesi->sesi)
                <div style="font-size:12px;color:var(--text-muted)">
                  @if($sesi->sesi->kegiatan)
                    {{ $sesi->sesi->kegiatan->nama }}
                  @elseif($sesi->sesi->pkkmbSchedule)
                    PKKMB Hari ke-{{ $sesi->sesi->pkkmbSchedule->hari_ke }}
                  @endif
                </div>
              @endif
            </div>
          </div>
        </td>
        <td>
          <div style="font-size:13px;font-weight:600">
            {{ optional($sesi->sesi)->tanggal ? Carbon\Carbon::parse($sesi->sesi->tanggal)->format('d M Y') : '-' }}
          </div>
          <div style="font-size:11px;color:var(--text-muted)">
            {{ optional($sesi->sesi)->tanggal ? Carbon\Carbon::parse($sesi->sesi->tanggal)->diffForHumans() : '' }}
          </div>
        </td>
        <td>
          <div style="font-size:13px">
            <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;color:var(--success)">schedule</span>
            {{ $sesi->absen_at ? Carbon\Carbon::parse($sesi->absen_at)->format('H:i') : '-' }}
          </div>
          <div style="font-size:11px;color:var(--text-muted)">
            {{ $sesi->created_at ? $sesi->created_at->format('d/m/Y H:i') : '' }}
          </div>
        </td>
        <td>
          @php
            $statusConfig = [
              'hadir' => ['badge' => 'badge-green', 'icon' => 'check_circle', 'text' => 'Hadir'],
              'izin' => ['badge' => 'badge-blue', 'icon' => 'event_note', 'text' => 'Izin'],
              'sakit' => ['badge' => 'badge-yellow', 'icon' => 'local_hospital', 'text' => 'Sakit'],
              'alpha' => ['badge' => 'badge-red', 'icon' => 'cancel', 'text' => 'Alpha'],
            ];
            $config = $statusConfig[$sesi->status] ?? $statusConfig['hadir'];
          @endphp
          <span class="badge {{ $config['badge'] }}" style="display:inline-flex;align-items:center;gap:4px">
            <span class="material-symbols-outlined" style="font-size:14px">{{ $config['icon'] }}</span>
            {{ $config['text'] }}
          </span>
        </td>
        <td style="font-size:12px;color:var(--text-muted)">
          @if($sesi->absenBy)
            <div style="display:flex;align-items:center;gap:4px">
              <span class="material-symbols-outlined" style="font-size:14px">person</span>
              {{ $sesi->absenBy->name ?? $sesi->absen_by }}
            </div>
            <div style="font-size:11px;opacity:0.7">
              ({{ $sesi->absenBy->role ?? '-' }})
            </div>
          @else
            <span style="opacity:0.5">-</span>
          @endif
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="5" style="text-align:center;padding:60px 20px;color:var(--text-muted)">
          <div style="display:flex;flex-direction:column;align-items:center;gap:12px">
            <span class="material-symbols-outlined" style="font-size:64px;opacity:0.3">event_busy</span>
            <div style="font-size:16px;font-weight:600">Belum Ada Riwayat Absensi</div>
            <div style="font-size:13px;max-width:400px;line-height:1.6">
              Anda belum terdaftar dalam absensi sesi kegiatan apapun. Absensi sesi dilakukan oleh Garda atau Timdis saat kegiatan berlangsung.
            </div>
          </div>
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
  
  @if($riwayatSesi->count() > 0)
  <div style="margin-top:16px;padding:12px;background:var(--info-light);border:1px solid var(--info);border-radius:8px;font-size:13px;color:var(--info-dark)">
    <div style="display:flex;align-items:start;gap:8px">
      <span class="material-symbols-outlined" style="font-size:18px">info</span>
      <div>
        <strong>Informasi:</strong> Data absensi sesi diinput oleh Garda atau Timdis saat kegiatan berlangsung. 
        Total sesi yang sudah Anda ikuti: <strong>{{ $riwayatSesi->count() }} sesi</strong>
      </div>
    </div>
  </div>
  @endif
</div>

<style>
.badge-purple {
  background-color: #f3e8ff;
  color: #7c3aed;
  border: 1px solid #c4b5fd;
}
</style>
@endsection
