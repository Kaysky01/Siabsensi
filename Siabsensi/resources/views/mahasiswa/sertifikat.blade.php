@extends('layouts.mahasiswa')
@section('title', 'Sertifikat Kelulusan — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Sertifikat Kelulusan PKKMB</div>
    <div class="page-sub">Syarat kelulusan dan pengunduhan sertifikat resmi PKKMB</div>
  </div>
</div>

<div class="panel" style="max-width:640px; margin: 0 auto; padding: 32px 24px;">
  
  {{-- Progress Kehadiran Card --}}
  <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:24px; text-align:left;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
      <span style="font-weight:600; font-size:14px; color:#334155;">Persentase Kehadiran PKKMB</span>
      <span style="font-weight:700; font-size:16px; color:{{ $certStats['can_get'] ? '#16a34a' : '#dc2626' }};">
        {{ $certStats['persentase'] }}%
      </span>
    </div>
    
    {{-- Progress Bar --}}
    <div style="width:100%; height:10px; background:#e2e8f0; border-radius:5px; overflow:hidden; margin-bottom:10px;">
      <div style="width:{{ min(100, max(0, $certStats['persentase'])) }}%; height:100%; background:{{ $certStats['can_get'] ? '#16a34a' : ($certStats['persentase'] >= 50 ? '#f59e0b' : '#dc2626') }}; transition:width 0.4s ease;"></div>
    </div>
    
    <div style="display:flex; justify-content:space-between; font-size:12px; color:#64748b;">
      <span>Hadir: <strong>{{ $certStats['hadir_sesi'] }}</strong> dari <strong>{{ $certStats['total_sesi'] }}</strong> {{ ($certStats['type'] ?? 'hari') === 'sesi' ? 'sesi' : 'hari' }} PKKMB</span>
      <span>Syarat Kelulusan: <strong>≥ 80%</strong></span>
    </div>
  </div>

  @if($certStats['can_get'])
    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:24px; margin-bottom:24px; text-align:center;">
      <span class="material-symbols-outlined" style="font-size:64px; color:#16a34a; margin-bottom:12px;">workspace_premium</span>
      <div style="font-size:20px; font-weight:700; color:#15803d; margin-bottom:8px;">Selamat! Anda Dinyatakan LULUS PKKMB.</div>
      <p style="color:#166534; font-size:14px; margin-bottom:0; line-height:1.6;">
        Kehadiran Anda telah memenuhi syarat kelulusan (<strong>{{ $certStats['persentase'] }}%</strong>). Sertifikat resmi Anda telah diterbitkan dan siap diunduh.
      </p>
    </div>
    
    <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
      <a href="{{ url('/api/mahasiswa/' . $mahasiswa->id . '/sertifikat/preview/pdf') }}" target="_blank" class="btn btn-ghost">
        <span class="material-symbols-outlined" style="font-size:18px">visibility</span> Preview Sertifikat
      </a>
      <form action="{{ url('/api/mahasiswa/' . $mahasiswa->id . '/sertifikat/generate') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-primary">
          <span class="material-symbols-outlined" style="font-size:18px">download</span> Unduh Sertifikat (PDF)
        </button>
      </form>
    </div>
  @else
    <div style="background:#fffbe0; border:1px solid #fef08a; border-radius:12px; padding:24px; text-align:center;">
      <span class="material-symbols-outlined" style="font-size:64px; color:#d97706; margin-bottom:12px;">lock</span>
      <div style="font-size:20px; font-weight:700; color:#b45309; margin-bottom:8px;">Sertifikat Terkunci (Belum Memenuhi Syarat)</div>
      <p style="color:#92400e; font-size:14px; margin-bottom:12px; line-height:1.6;">
        Sertifikat kelulusan PKKMB dikunci karena persentase kehadiran Anda saat ini adalah <strong>{{ $certStats['persentase'] }}%</strong>, belum mencapai batas minimal kelulusan <strong>80%</strong>.
      </p>
      <div style="font-size:12px; color:#78350f; background:#fef3c7; padding:8px 12px; border-radius:6px; display:inline-block;">
        <strong>Keterangan:</strong> {{ $certStats['reason'] }}
      </div>
    </div>
  @endif

</div>
@endsection
