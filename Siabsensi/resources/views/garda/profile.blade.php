@extends('layouts.admin')
@section('title', 'Profil Akun — SIABSEN')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<section>
  <div class="page-header">
    <div>
      <div class="page-title">Profil Akun</div>
      <div class="page-sub">Kelola informasi profil dan kata sandi Anda.</div>
    </div>
  </div>

  @if(session('success'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: @json(session('success')),
        confirmButtonColor: '#28a745',
        timer: 3000,
        timerProgressBar: true
      });
    });
  </script>
  @endif

  @if(session('error'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: @json(session('error')),
        confirmButtonColor: '#dc3545'
      });
    });
  </script>
  @endif

  <div class="panel">
    <form action="{{ route('garda.profile.update') }}" method="POST">
      @csrf
      @method('PUT')

      <h3 class="section-title">Informasi Profil</h3>
      <div class="form-row">
        <label for="full_name" class="form-label">Nama Lengkap</label>
        <input type="text" name="full_name" id="full_name" class="form-input" value="{{ old('full_name', $user->full_name) }}" required>
        @error('full_name')<div class="text-danger">{{ $message }}</div>@enderror
      </div>
      <div class="form-row">
        <label for="email" class="form-label">Email</label>
        <input type="email" name="email" id="email" class="form-input" value="{{ old('email', $user->email) }}">
        @error('email')<div class="text-danger">{{ $message }}</div>@enderror
      </div>
      <div class="form-row">
        <label for="username" class="form-label">Username</label>
        <input type="text" name="username" id="username" class="form-input" value="{{ old('username', $user->username) }}" required>
        @error('username')<div class="text-danger">{{ $message }}</div>@enderror
      </div>
      <div class="form-row">
        <label for="role" class="form-label">Role</label>
        <input type="text" id="role" class="form-input" value="{{ ucfirst($user->role) }}" disabled>
      </div>
      <div class="form-row">
        <label for="assigned_kompi" class="form-label">Kompi Ditugaskan</label>
        <input type="text" id="assigned_kompi" class="form-input" value="{{ $user->assigned_kompi ?? 'Tidak Ada' }}" disabled>
      </div>

      <h3 class="section-title" style="margin-top:30px">Ubah Kata Sandi</h3>
      <div class="form-row">
        <label for="current_password" class="form-label">Kata Sandi Saat Ini</label>
        <input type="password" name="current_password" id="current_password" class="form-input">
        @error('current_password')<div class="text-danger">{{ $message }}</div>@enderror
      </div>
      <div class="form-row">
        <label for="new_password" class="form-label">Kata Sandi Baru</label>
        <input type="password" name="new_password" id="new_password" class="form-input">
        @error('new_password')<div class="text-danger">{{ $message }}</div>@enderror
      </div>
      <div class="form-row">
        <label for="new_password_confirmation" class="form-label">Konfirmasi Kata Sandi Baru</label>
        <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="form-input">
      </div>

      <div style="margin-top:30px">
        <button type="submit" class="btn btn-primary">
          <span class="material-symbols-outlined" style="font-size:18px">save</span> Simpan Perubahan
        </button>
      </div>
    </form>
  </div>
</section>

<style>
.form-row {
  margin-bottom: 16px;
}

.form-label {
  display: block;
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 6px;
  font-weight: 600;
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  background: var(--surface);
  color: var(--text);
}

.form-input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.form-input[disabled] {
  background: var(--bg);
  cursor: not-allowed;
  opacity: 0.8;
}

.text-danger {
  color: var(--danger);
  font-size: 12px;
  margin-top: 4px;
}

.section-title {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 20px;
  color: var(--text-dark);
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: var(--radius-sm);
  border: none;
  cursor: pointer;
  font-weight: 600;
  text-decoration: none;
  font-size: 15px;
  transition: all 0.2s;
}

.btn-primary {
  background: var(--primary);
  color: white;
}

.btn-primary:hover {
  background: var(--primary-dark);
}
</style>
@endsection
