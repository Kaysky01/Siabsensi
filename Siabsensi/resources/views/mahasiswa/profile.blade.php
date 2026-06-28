@extends('layouts.mahasiswa')
@section('title', 'Profil Mahasiswa — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Edit Profil</div>
    <div class="page-sub">Kelola informasi data diri dan kata sandi Anda</div>
  </div>
</div>

<div class="panel" style="max-width:600px; margin: 0 auto;">
  <form method="POST" action="{{ route('mahasiswa.profile.update') }}">
    @csrf
    @method('PUT')
    
    <div class="form-row">
      <label class="form-label">Nomor Registrasi</label>
      <input type="text" class="form-input" value="{{ $mahasiswa->nim }}" disabled style="background:var(--bg);cursor:not-allowed">
      <span class="form-hint">Nomor Registrasi tidak dapat diubah secara mandiri.</span>
    </div>

    <div class="form-row">
      <label class="form-label">Nama Lengkap</label>
      <input type="text" name="name" class="form-input" value="{{ old('name', $mahasiswa->name) }}" required>
    </div>

    <div class="form-row">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-input" value="{{ old('email', $mahasiswa->email) }}">
    </div>

    <div class="form-row">
      <label class="form-label">Prodi</label>
      <input type="text" class="form-input" value="{{ $mahasiswa->prodi }}" disabled style="background:var(--bg);cursor:not-allowed">
    </div>
    
    <div class="form-row">
      <label class="form-label">Jurusan</label>
      <input type="text" class="form-input" value="{{ $mahasiswa->jurusan }}" disabled style="background:var(--bg);cursor:not-allowed">
    </div>

    <hr style="border:0;border-top:1px solid var(--border);margin:24px 0">

    <div style="font-weight:600;margin-bottom:16px">Ganti Kata Sandi</div>
    <span class="form-hint" style="margin-bottom:16px;display:block">Kosongkan bagian ini jika tidak ingin mengubah kata sandi Anda.</span>

    <div class="form-row">
      <label class="form-label">Kata Sandi Saat Ini</label>
      <input type="password" name="current_password" class="form-input" placeholder="Masukkan kata sandi saat ini">
    </div>

    <div class="form-row-2">
      <div class="form-row">
        <label class="form-label">Kata Sandi Baru</label>
        <input type="password" name="new_password" class="form-input" placeholder="Minimal 6 karakter">
      </div>
      <div class="form-row">
        <label class="form-label">Konfirmasi Kata Sandi Baru</label>
        <input type="password" name="new_password_confirmation" class="form-input" placeholder="Ulangi kata sandi baru">
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;margin-top:24px">
      <button type="submit" class="btn btn-primary">
        <span class="material-symbols-outlined" style="font-size:16px">save</span> Simpan Perubahan
      </button>
    </div>
  </form>
</div>
@endsection
