@extends('layouts.admin')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Master Data Jurusan & Prodi</div>
    <div class="page-sub">Kelola daftar jurusan dan program studi</div>
  </div>
  <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-jurusan').classList.add('show')">
    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">add</span> Tambah Jurusan
  </button>
</div>

<div class="panel">
  <table class="att-table">
    <thead>
      <tr>
        <th>Jurusan</th>
        <th>Daftar Program Studi</th>
        <th width="150">Aksi Jurusan</th>
      </tr>
    </thead>
    <tbody>
      @forelse($jurusanList as $jur)
      <tr>
        <td style="font-weight:600;vertical-align:top;padding-top:16px"><span class="badge badge-blue" style="font-size:14px;padding:6px 12px;box-shadow:0 2px 4px rgba(0,0,0,0.05)">{{ $jur->nama }}</span></td>
        <td style="padding:16px 12px">
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            @foreach($jur->prodi as $pro)
              <div style="display:inline-flex;align-items:center;gap:8px;background:white;border:1px solid #cbd5e1;padding:4px 10px;border-radius:6px;box-shadow:0 1px 2px rgba(0,0,0,0.05)">
                <span style="font-size:13px;font-weight:500;color:#334155">{{ $pro->nama }}</span>
                <div style="display:flex;gap:2px;border-left:1px solid #e2e8f0;padding-left:6px">
                  <button onclick="editProdi({{ $pro->id }}, '{{ addslashes($pro->nama) }}', {{ $jur->id }})" title="Edit Prodi" style="background:none;border:none;cursor:pointer;padding:2px;color:var(--primary);display:flex;border-radius:4px" onmouseover="this.style.background='#e0e7ff'" onmouseout="this.style.background='none'"><span class="material-symbols-outlined" style="font-size:14px">edit</span></button>
                  <form method="POST" action="{{ route('admin.master.prodi.destroy', $pro->id) }}" onsubmit="return confirm('Hapus Prodi ini?')" style="margin:0;display:flex">
                    @csrf @method('DELETE')
                    <button type="submit" title="Hapus Prodi" style="background:none;border:none;cursor:pointer;padding:2px;color:var(--danger);display:flex;border-radius:4px" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'"><span class="material-symbols-outlined" style="font-size:14px">delete</span></button>
                  </form>
                </div>
              </div>
            @endforeach
            <button onclick="addProdi({{ $jur->id }})" style="display:inline-flex;align-items:center;gap:4px;background:#f8fafc;border:1px dashed #94a3b8;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:500;color:#64748b;cursor:pointer;transition:all 0.2s" onmouseover="this.style.background='#f1f5f9';this.style.borderColor='#64748b'" onmouseout="this.style.background='#f8fafc';this.style.borderColor='#94a3b8'">
              <span class="material-symbols-outlined" style="font-size:14px">add</span> Tambah Prodi
            </button>
          </div>
        </td>
        <td style="vertical-align:top;padding-top:16px">
          <div style="display:flex;gap:4px">
            <button onclick="editJurusan({{ $jur->id }}, '{{ addslashes($jur->nama) }}')" class="btn btn-ghost btn-sm" title="Edit Jurusan">
              <span class="material-symbols-outlined" style="font-size:16px">edit</span>
            </button>
            <form method="POST" action="{{ route('admin.master.jurusan.destroy', $jur->id) }}" onsubmit="return confirm('Hapus Jurusan ini beserta seluruh Prodinya?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-ghost btn-sm" title="Hapus Jurusan" style="color:var(--danger)">
                <span class="material-symbols-outlined" style="font-size:16px">delete</span>
              </button>
            </form>
          </div>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">Belum ada data Jurusan</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

<!-- Modal Tambah Jurusan -->
<div class="modal-backdrop" id="modal-add-jurusan">
  <div class="modal">
    <div class="modal-title">Tambah Jurusan</div>
    <form method="POST" action="{{ route('admin.master.jurusan.store') }}">
      @csrf
      <div class="form-row"><label class="form-label">Nama Jurusan *</label><input name="nama" class="form-input" required></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Jurusan -->
<div class="modal-backdrop" id="modal-edit-jurusan">
  <div class="modal">
    <div class="modal-title">Edit Jurusan</div>
    <form method="POST" id="form-edit-jurusan">
      @csrf @method('PUT')
      <div class="form-row"><label class="form-label">Nama Jurusan *</label><input name="nama" id="edit-jurusan-nama" class="form-input" required></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Tambah Prodi -->
<div class="modal-backdrop" id="modal-add-prodi">
  <div class="modal">
    <div class="modal-title">Tambah Program Studi</div>
    <form method="POST" action="{{ route('admin.master.prodi.store') }}">
      @csrf
      <input type="hidden" name="jurusan_id" id="add-prodi-jurusan-id">
      <div class="form-row"><label class="form-label">Nama Program Studi *</label><input name="nama" class="form-input" required></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Prodi -->
<div class="modal-backdrop" id="modal-edit-prodi">
  <div class="modal">
    <div class="modal-title">Edit Program Studi</div>
    <form method="POST" id="form-edit-prodi">
      @csrf @method('PUT')
      <input type="hidden" name="jurusan_id" id="edit-prodi-jurusan-id">
      <div class="form-row"><label class="form-label">Nama Program Studi *</label><input name="nama" id="edit-prodi-nama" class="form-input" required></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function editJurusan(id, nama) {
  document.getElementById('form-edit-jurusan').action = '/admin/master/jurusan/' + id;
  document.getElementById('edit-jurusan-nama').value = nama;
  document.getElementById('modal-edit-jurusan').classList.add('show');
}
function addProdi(jurusanId) {
  document.getElementById('add-prodi-jurusan-id').value = jurusanId;
  document.getElementById('modal-add-prodi').classList.add('show');
}
function editProdi(id, nama, jurusanId) {
  document.getElementById('form-edit-prodi').action = '/admin/master/prodi/' + id;
  document.getElementById('edit-prodi-nama').value = nama;
  document.getElementById('edit-prodi-jurusan-id').value = jurusanId;
  document.getElementById('modal-edit-prodi').classList.add('show');
}
</script>
@endsection
