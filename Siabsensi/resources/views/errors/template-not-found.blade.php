@extends('layouts.mahasiswa')
@section('title', 'Template Tidak Tersedia — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Template Kartu Tidak Tersedia</div>
    <div class="page-sub">Template kartu untuk jurusan Anda belum tersedia di sistem.</div>
  </div>
</div>

<div class="panel" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 400px; text-align: center; padding: 60px 40px;">
  
  <!-- Icon -->
  <div style="margin-bottom: 30px;">
    <span class="material-symbols-outlined" style="font-size: 120px; color: #f59e0b; opacity: 0.8;">
      error
    </span>
  </div>
  
  <!-- Title -->
  <h2 style="font-size: 32px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">
    Template Tidak Ditemukan
  </h2>
  
  <!-- Message -->
  <p style="font-size: 18px; color: var(--text-secondary); max-width: 600px; line-height: 1.6; margin-bottom: 12px;">
    Template kartu mahasiswa untuk jurusan <strong style="color: var(--primary);">{{ $jurusan }}</strong> belum tersedia di sistem.
  </p>
  
  <p style="font-size: 16px; color: var(--text-secondary); max-width: 600px; line-height: 1.6; margin-bottom: 40px;">
    Silakan hubungi administrator untuk menambahkan template kartu jurusan Anda.
  </p>
  
  <!-- Info Box -->
  <div style="background: #fef3c7; border: 2px solid #fbbf24; border-radius: 12px; padding: 20px 30px; max-width: 700px; margin-bottom: 30px;">
    <div style="display: flex; align-items: start; gap: 16px; text-align: left;">
      <span class="material-symbols-outlined" style="font-size: 28px; color: #f59e0b; flex-shrink: 0;">
        info
      </span>
      <div>
        <h4 style="font-size: 16px; font-weight: 600; color: #92400e; margin-bottom: 8px;">
          Informasi untuk Administrator
        </h4>
        <p style="font-size: 14px; color: #78350f; line-height: 1.5; margin: 0;">
          Template yang diperlukan:<br>
          <code style="background: white; padding: 2px 8px; border-radius: 4px; font-size: 13px;">
            public/static/img/{{ $jurusan }}/Depan.jpg
          </code><br>
          <code style="background: white; padding: 2px 8px; border-radius: 4px; font-size: 13px;">
            public/static/img/{{ $jurusan }}/Belakang.jpg
          </code>
        </p>
      </div>
    </div>
  </div>
  
  <!-- Actions -->
  <div style="display: flex; gap: 16px; flex-wrap: wrap; justify-content: center;">
    <a href="{{ route('mahasiswa.dashboard') }}" class="btn btn-primary">
      <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">home</span>
      Kembali ke Dashboard
    </a>
    
    <a href="{{ route('mahasiswa.profile') }}" class="btn btn-secondary" style="background: var(--bg-secondary); color: var(--text-primary);">
      <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span>
      Lihat Profil
    </a>
  </div>
  
  <!-- Student Info -->
  <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid var(--border); width: 100%; max-width: 600px;">
    <p style="font-size: 14px; color: var(--text-tertiary); margin-bottom: 8px;">Data Mahasiswa:</p>
    <div style="font-size: 15px; color: var(--text-secondary);">
      <strong>{{ $mahasiswa->name }}</strong><br>
      {{ $mahasiswa->id }} | {{ $mahasiswa->kompi }} | {{ $mahasiswa->prodi }}
    </div>
  </div>
  
</div>
@endsection
