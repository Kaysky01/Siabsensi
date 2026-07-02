@extends('layouts.mahasiswa')
@section('title', 'Sertifikat Kelulusan — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Sertifikat Kelulusan</div>
    <div class="page-sub">Unduh sertifikat kelulusan PKKMB jika Anda telah memenuhi syarat</div>
  </div>
</div>

<div class="panel" style="max-width:600px; margin: 0 auto; text-align:center; padding: 40px 20px;">
  
  @if($mahasiswa->status_kelulusan === 'LULUS')
    <div style="background:var(--success-light); border:1px solid var(--success); border-radius:12px; padding:24px; margin-bottom:24px;">
      <span class="material-symbols-outlined" style="font-size:64px; color:var(--success); margin-bottom:12px;">workspace_premium</span>
      <div style="font-size:20px; font-weight:700; color:var(--success); margin-bottom:8px;">Selamat! Anda Dinyatakan Lulus PKKMB.</div>
      <p style="color:var(--text-secondary); margin-bottom:0;">Sertifikat kelulusan resmi Anda sudah dapat diunduh.</p>
    </div>
    
    <div style="display:flex; justify-content:center; gap:16px;">
      <a href="{{ url('/api/mahasiswa/' . $mahasiswa->id . '/sertifikat/preview/pdf') }}" target="_blank" class="btn btn-ghost">Preview Sertifikat</a>
      <form action="{{ url('/api/mahasiswa/' . $mahasiswa->id . '/sertifikat/generate') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-primary">
          <span class="material-symbols-outlined" style="font-size:18px">download</span> Unduh PDF
        </button>
      </form>
    </div>
  @else
    <div style="background:var(--warning-light); border:1px solid #F59E0B; border-radius:12px; padding:24px;">
      <span class="material-symbols-outlined" style="font-size:64px; color:#F59E0B; margin-bottom:12px;">lock</span>
      <div style="font-size:20px; font-weight:700; color:#B45309; margin-bottom:8px;">Sertifikat Belum Tersedia</div>
      <p style="color:#92400E; margin-bottom:0;">Sertifikat kelulusan hanya tersedia bagi mahasiswa yang telah dinyatakan lulus PKKMB oleh panitia.</p>
    </div>
  @endif

</div>
@endsection
