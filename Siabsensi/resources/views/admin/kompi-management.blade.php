@extends('layouts.admin')
@section('title', 'Manajemen Kompi — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Pengaturan Kompi</div>
      <div class="page-sub">Ubah kompi mahasiswa secara massal atau acak berdasarkan jurusan</div>
    </div>
    <form method="POST" action="{{ route('admin.kompi.shuffle') }}" onsubmit="return confirm('Peringatan: Ini akan mengacak ulang seluruh mahasiswa ke dalam Kompi yang tersedia secara merata per jurusan. Lanjutkan?')">
      @csrf
      <button type="submit" class="btn btn-secondary btn-sm">
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
    </form>
  </div>
</section>

<script>
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
@endsection
