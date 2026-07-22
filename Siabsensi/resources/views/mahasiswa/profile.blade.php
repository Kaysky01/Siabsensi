@extends('layouts.mahasiswa')
@section('title', 'Profil Mahasiswa — SIABSEN')

@section('content')
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<div class="page-header">
  <div>
    <div class="page-title">Edit Profil</div>
    <div class="page-sub">Kelola informasi data diri, foto, dan kata sandi Anda</div>
  </div>
</div>

@if(!$isProfileComplete)
<div class="alert alert-warning" style="background:#fffbeb;border-left:4px solid #f59e0b;padding:14px 18px;border-radius:8px;margin-bottom:16px;color:#92400e">
  <div style="display:flex;align-items:center;gap:8px;font-weight:700;margin-bottom:8px">
    <span class="material-symbols-outlined" style="font-size:18px">lock</span>
    ID Card masih terkunci
  </div>
  <div style="font-size:14px;line-height:1.6">
    Foto profil wajib diupload karena akan tampil di ID card. Untuk membuka ID card, data berikut harus lengkap:
    <strong>{{ implode(', ', array_values($missingProfileFields)) }}</strong>.
  </div>
</div>
@else
<div class="alert alert-success" style="background:#ecfdf5;border-left:4px solid #10b981;padding:14px 18px;border-radius:8px;margin-bottom:16px;color:#065f46">
  <div style="display:flex;align-items:center;gap:8px;font-weight:700">
    <span class="material-symbols-outlined" style="font-size:18px">verified</span>
    Profil sudah lengkap. ID Card dapat diakses.
  </div>
</div>
@endif

@if(session('success'))
<div class="alert alert-success" style="background:#d1fae5;border-left:4px solid #10b981;padding:14px 18px;border-radius:8px;margin-bottom:16px;color:#065f46;font-weight:500">
  <span class="material-symbols-outlined" style="vertical-align:middle;font-size:18px">check_circle</span>
  {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="background:#fee2e2;border-left:4px solid #ef4444;padding:14px 18px;border-radius:8px;margin-bottom:16px;color:#7f1d1d;font-weight:500">
  <span class="material-symbols-outlined" style="vertical-align:middle;font-size:18px">error</span>
  {{ session('error') }}
</div>
@endif
@if($errors->any())
<div class="alert alert-danger" style="background:#fee2e2;border-left:4px solid #ef4444;padding:14px 18px;border-radius:8px;margin-bottom:16px;color:#7f1d1d">
  @foreach($errors->all() as $err)<div>• {{ $err }}</div>@endforeach
</div>
@endif

{{-- ===== FOTO PROFIL ===== --}}
<div class="panel" style="max-width:600px;margin:0 auto 20px auto">
  <div style="font-weight:700;font-size:16px;margin-bottom:16px;display:flex;align-items:center;gap:8px">
    <span class="material-symbols-outlined" style="color:var(--primary)">photo_camera</span>
    Foto Profil
  </div>

  <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
    {{-- Preview foto lingkaran --}}
    <div style="position:relative;flex-shrink:0">
      <div id="photo-preview-placeholder"
           style="width:110px;height:110px;border-radius:50%;background:var(--primary-light);display:{{ $mahasiswa->photo_url ? 'none' : 'flex' }};align-items:center;justify-content:center;border:3px dashed var(--primary)">
        <span class="material-symbols-outlined" style="font-size:48px;color:var(--primary);opacity:0.6">person</span>
      </div>
      <img id="photo-preview"
           src="{{ $mahasiswa->photo_url ?? '' }}"
           alt="Foto Profil"
           style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:3px solid var(--primary);display:{{ $mahasiswa->photo_url ? 'block' : 'none' }}">
      @if($mahasiswa->photo_url)
        <div style="position:absolute;bottom:2px;right:2px;width:28px;height:28px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid white">
          <span class="material-symbols-outlined" style="font-size:14px;color:white">check</span>
        </div>
      @endif
    </div>

    <div style="flex:1;min-width:200px">
      @if(!$mahasiswa->hasValidProfilePhoto())
      <div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#92400e;display:flex;align-items:center;gap:8px">
        <span class="material-symbols-outlined" style="font-size:16px;flex-shrink:0">warning</span>
        <span>Foto belum diupload. <strong>Download sertifikat/ID card tidak tersedia</strong> sebelum foto dilengkapi.</span>
      </div>
      @endif
      
      <div style="background:#dbeafe;border:1px solid #3b82f6;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px;color:#1e40af">
        <strong style="display:block;margin-bottom:4px">📸 Ketentuan Foto:</strong>
        <ul style="margin:4px 0 0 16px;padding:0;line-height:1.6">
          <li>Foto <strong>pribadi</strong> (tidak bersama orang lain)</li>
          <li>Wajah terlihat jelas dan menghadap kamera</li>
          <li>Latar belakang polos/rapi (disarankan)</li>
          <li>Format: JPG, PNG, atau WEBP</li>
          <li>Ukuran maksimal: 2 MB</li>
        </ul>
      </div>
      
      <form id="form-photo-upload" action="{{ route('mahasiswa.profile.photo') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="cropped_image" id="cropped-image-data">
        <label style="font-size:13px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:8px">
          Upload Foto {{ $mahasiswa->photo_url ? 'Baru' : '' }} (JPG/PNG/WEBP, max 2MB)
        </label>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <label style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:var(--bg);border:1px solid var(--border);border-radius:6px;font-size:13px;font-weight:600;transition:all 0.2s" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
            <span class="material-symbols-outlined" style="font-size:16px">upload</span>
            Pilih Foto
            <input type="file" id="input-file-photo" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="openCropper(this)">
          </label>
          @if($mahasiswa->photo_url)
          <span style="font-size:12px;color:var(--text-muted);font-style:italic">
            Foto saat ini akan diganti dengan foto baru
          </span>
          @endif
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ===== DATA PROFIL ===== --}}
<div class="panel" style="max-width:600px; margin: 0 auto;">
  <form method="POST" action="{{ route('mahasiswa.profile.update') }}">
    @csrf
    @method('PUT')
    
    <div style="font-weight:700;font-size:16px;margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <span class="material-symbols-outlined" style="color:var(--primary)">person</span>
      Informasi Data Diri
    </div>

    <div class="form-row">
      <label class="form-label">Nomor Registrasi</label>
      <input type="text" class="form-input" value="{{ $mahasiswa->id }}" disabled style="background:var(--bg);cursor:not-allowed">
      <span class="form-hint">Nomor Registrasi tidak dapat diubah secara mandiri.</span>
    </div>

    <div class="form-row">
      <label class="form-label">Nama Lengkap</label>
      <input type="text" name="name" class="form-input" value="{{ old('name', $mahasiswa->name) }}" required>
    </div>

    <div class="form-row">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-input" value="{{ old('email', $mahasiswa->email) }}" placeholder="nama@email.com">
    </div>

    <div class="form-row-2">
      <div class="form-row">
        <label class="form-label">Jurusan Polinela</label>
        <input type="text" class="form-input" value="{{ $mahasiswa->jurusan }}" disabled style="background:var(--bg);cursor:not-allowed">
      </div>
      <div class="form-row">
        <label class="form-label">Prodi Polinela</label>
        <input type="text" class="form-input" value="{{ $mahasiswa->prodi }}" disabled style="background:var(--bg);cursor:not-allowed">
      </div>
    </div>

    <div class="form-row">
      <label class="form-label">Tanggal Lahir</label>
      <input type="text" class="form-input" value="{{ $mahasiswa->tanggal_lahir ? $mahasiswa->tanggal_lahir->format('d M Y') : '-' }}" disabled style="background:var(--bg);cursor:not-allowed">
      <span class="form-hint">Jika tanggal lahir belum ada atau salah, hubungi admin.</span>
    </div>

    <div class="form-row-2">
      <div class="form-row">
        <label class="form-label">TLP Mahasiswa</label>
        <input type="text" name="no_telp_mahasiswa" class="form-input" value="{{ old('no_telp_mahasiswa', $mahasiswa->no_telp_mahasiswa) }}" placeholder="08xxxxxxxxxx">
      </div>
      <div class="form-row">
        <label class="form-label">TLP Ortu</label>
        <input type="text" name="no_telp_ortu" class="form-input" value="{{ old('no_telp_ortu', $mahasiswa->no_telp_ortu) }}" placeholder="08xxxxxxxxxx">
        <span class="form-hint">Kolom ini tetap nullable di data, tetapi wajib terisi untuk membuka/download ID card.</span>
      </div>
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

<!-- Modal Cropper -->
<div class="modal-backdrop" id="modal-cropper" style="z-index: 1000;">
  <div class="modal" style="max-width:500px; width:100%; padding:20px; background:var(--surface); border-radius:12px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="margin:0;font-size:18px">Sesuaikan Foto</h3>
      <button type="button" class="btn-icon" onclick="closeCropperModal()" style="border:none;background:transparent;cursor:pointer">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    
    <div style="width:100%;max-height:400px;overflow:hidden;background:#e5e7eb;display:flex;justify-content:center;align-items:center;border-radius:8px">
      <img id="image-to-crop" style="max-width:100%;display:block">
    </div>

    <div style="margin-top:16px;text-align:center;font-size:13px;color:var(--text-muted)">
      Gunakan scroll mouse untuk zoom in/out. Geser gambar untuk menyesuaikan posisi.
    </div>

    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px">
      <button type="button" class="btn btn-ghost" onclick="closeCropperModal()">Batal</button>
      <button type="button" class="btn btn-primary" onclick="processCrop()" id="btn-process-crop">
        <span class="material-symbols-outlined" style="font-size:16px">crop</span> Crop & Simpan
      </button>
    </div>
  </div>
</div>

<script>
let cropper = null;

function openCropper(input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    
    // Validasi ukuran (max 2MB)
    if (file.size > 2 * 1024 * 1024) {
      alert("Ukuran foto terlalu besar. Maksimal 2MB.");
      input.value = "";
      return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
      const image = document.getElementById('image-to-crop');
      image.src = e.target.result;
      
      document.getElementById('modal-cropper').classList.add('show');
      
      // Hancurkan cropper lama jika ada
      if (cropper !== null) {
        cropper.destroy();
      }
      
      // Inisialisasi cropper baru
      cropper = new Cropper(image, {
        aspectRatio: 1 / 1,
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 1,
        restore: false,
        guides: true,
        center: true,
        highlight: false,
        cropBoxMovable: false,
        cropBoxResizable: false,
        toggleDragModeOnDblclick: false,
      });
    }
    reader.readAsDataURL(file);
  }
}

function closeCropperModal() {
  document.getElementById('modal-cropper').classList.remove('show');
  document.getElementById('input-file-photo').value = "";
  if (cropper) {
    cropper.destroy();
    cropper = null;
  }
}

function processCrop() {
  if (!cropper) return;
  
  const btn = document.getElementById('btn-process-crop');
  btn.innerHTML = 'Memproses...';
  btn.disabled = true;

  // Dapatkan hasil crop dalam format base64
  const canvas = cropper.getCroppedCanvas({
    width: 500, // Ukuran resolusi standar
    height: 500,
  });

  const base64Image = canvas.toDataURL('image/jpeg', 0.85);

  // Masukkan string base64 ke input hidden
  document.getElementById('cropped-image-data').value = base64Image;
  
  // Hapus name "photo" agar file asli tidak terkirim via input file
  document.getElementById('input-file-photo').removeAttribute("name");

  // Submit form
  document.getElementById('form-photo-upload').submit();
}
</script>
@endsection
