@extends('layouts.admin')
@section('title', 'Manajemen Kompi — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Pengaturan Kompi</div>
      <div class="page-sub">Ubah kompi mahasiswa secara massal atau acak berdasarkan jurusan</div>
    </div>
    <form method="POST" action="{{ route('admin.kompi.shuffle') }}" id="shuffle-form">
      @csrf
      <button type="button" class="btn btn-secondary btn-sm" onclick="showConfirmModal()">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">shuffle</span> Acak Otomatis (Semua Jurusan)
      </button>
    </form>
  </div>

  <div class="panel">
    <form method="POST" action="{{ route('admin.kompi.bulkUpdate') }}" id="kompi-form">
      @csrf
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <div style="display:flex;gap:12px;align-items:center">
          <label class="form-label" style="margin-bottom:0">Set kompi terpilih ke:</label>
          <input type="text" id="bulk-kompi-value" class="form-input" style="width:150px;padding:5px 10px" placeholder="Misal: B">
          <button type="button" class="btn btn-secondary btn-sm" onclick="applyBulkKompi()">Terapkan ke baris</button>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
      </div>

      <table class="att-table">
        <thead>
          <tr>
            <th style="width:40px"><input type="checkbox" id="check-all" onchange="toggleAll(this)"></th>
            <th>Nama Mahasiswa</th>
            <th>NIM/ID</th>
            <th>Kompi Saat Ini</th>
            <th>Kompi Baru</th>
          </tr>
        </thead>
        <tbody>
          @foreach($mahasiswaList as $i => $m)
          <tr>
            <td><input type="checkbox" class="row-checkbox" value="{{ $i }}"></td>
            <td>{{ $m->name }}</td>
            <td>{{ $m->id }}</td>
            <td><span class="badge badge-blue">{{ $m->kompi }}</span></td>
            <td>
              <input type="hidden" name="assignments[{{ $i }}][id]" value="{{ $m->id }}">
              <input type="text" name="assignments[{{ $i }}][kompi]" id="kompi-input-{{ $i }}" class="form-input" style="padding:4px 8px;width:120px" value="{{ $m->kompi }}" required>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      <div style="margin-top: 16px;">
        {{ $mahasiswaList->links('pagination::bootstrap-4') }}
      </div>
    </form>
  </div>
</section>

<script>
function showConfirmModal() {
  document.getElementById('confirm-modal').style.display = 'flex';
}

function hideConfirmModal() {
  document.getElementById('confirm-modal').style.display = 'none';
}

function executeShuffle() {
  hideConfirmModal();
  document.getElementById('loading-overlay').style.display = 'flex';
  document.getElementById('shuffle-form').submit();
}

function toggleAll(source) {
  let checkboxes = document.querySelectorAll('.row-checkbox');
  for (let i = 0; i < checkboxes.length; i++) {
    checkboxes[i].checked = source.checked;
  }
}

function applyBulkKompi() {
  let val = document.getElementById('bulk-kompi-value').value;
  if(!val) { alert('Masukkan nilai kompi!'); return; }
  
  let checkboxes = document.querySelectorAll('.row-checkbox');
  let count = 0;
  for (let i = 0; i < checkboxes.length; i++) {
    if (checkboxes[i].checked) {
      document.getElementById('kompi-input-' + checkboxes[i].value).value = val;
      count++;
    }
  }
  if(count === 0) alert('Pilih setidaknya satu mahasiswa!');
}
</script>

<!-- Confirm Modal -->
<div id="confirm-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9998; flex-direction:column; justify-content:center; align-items:center;">
  <div style="background:#ffffff; padding:24px; border-radius:12px; width:100%; max-width:400px; box-shadow:0 10px 25px rgba(0,0,0,0.3); text-align:center;">
    <div style="width:50px; height:50px; border-radius:50%; background:#ffe4e6; color:#e11d48; display:flex; justify-content:center; align-items:center; margin:0 auto 16px;">
      <span class="material-symbols-outlined" style="font-size:24px;">warning</span>
    </div>
    <h3 style="margin:0 0 12px; font-family:var(--font-sans); font-size:18px; color:var(--text-main);">Acak Seluruh Kompi?</h3>
    <p style="margin:0 0 24px; color:var(--text-muted); font-size:14px; line-height:1.5;">
      Peringatan: Tindakan ini akan mengacak ulang <b>SELURUH DATA MAHASISWA</b> secara permanen ke dalam Kompi yang tersedia berdasarkan jurusan secara merata. Anda yakin ingin melanjutkan?
    </p>
    <div style="display:flex; gap:12px; justify-content:center;">
      <button class="btn btn-ghost" onclick="hideConfirmModal()" style="flex:1;">Batal</button>
      <button class="btn btn-primary" onclick="executeShuffle()" style="flex:1; background:var(--danger); border-color:var(--danger);">Ya, Acak Sekarang</button>
    </div>
  </div>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; flex-direction:column; justify-content:center; align-items:center; color:white;">
  <div class="spinner" style="width: 50px; height: 50px; border: 5px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; margin-bottom: 20px;"></div>
  <h3 style="margin:0; font-family:var(--font-sans); font-size: 20px; font-weight: 600;">Mengacak Seluruh Data Mahasiswa...</h3>
  <p style="margin-top:10px; color:#ddd; font-size: 14px;">Mohon tunggu, proses ini mungkin membutuhkan beberapa saat. Jangan tutup halaman ini.</p>
</div>

<style>
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
@endsection
