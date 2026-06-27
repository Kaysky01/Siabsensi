@extends('layouts.admin')
@section('title', 'Kelola Kegiatan — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Kelola Kegiatan</div>
      <div class="page-sub">Buat dan kelola kegiatan absensi mandiri</div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-kegiatan').classList.add('show')">
      <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">add</span> Tambah Kegiatan
    </button>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>Nama Kegiatan</th><th>Tanggal</th><th>Wajib Hadir</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        @forelse($kegiatanList as $k)
        <tr>
          <td>
            <div style="font-weight:600">{{ $k->nama }}</div>
          </td>
          <td>
            <div>{{ Carbon\Carbon::parse($k->tanggal_pelaksanaan)->format('d M Y') }}</div>
          </td>
          <td><span class="badge {{ $k->wajib_hadir ? 'badge-red' : 'badge-gray' }}">{{ $k->wajib_hadir ? 'Wajib' : 'Opsional' }}</span></td>
          <td><span class="badge {{ $k->is_active ? 'badge-green' : 'badge-red' }}">{{ $k->is_active ? 'Aktif' : 'Selesai' }}</span></td>
          <td>
            <div style="display:flex;gap:4px">
              <form method="POST" action="{{ route('admin.kegiatan.toggle', $k->id) }}" style="margin:0">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm" title="{{ $k->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" style="color:{{ $k->is_active ? 'var(--warning)' : 'var(--success)' }}">
                  <span class="material-symbols-outlined" style="font-size:16px">{{ $k->is_active ? 'pause_circle' : 'play_circle' }}</span>
                </button>
              </form>
              <button class="btn btn-ghost btn-sm" title="Edit" onclick="openEditKegiatan({{ $k->id }}, '{{ addslashes($k->nama) }}', '{{ $k->tanggal_pelaksanaan }}', {{ $k->wajib_hadir }}, {{ $k->is_active }})">
                <span class="material-symbols-outlined" style="font-size:16px">edit</span>
              </button>
              <form method="POST" action="{{ route('admin.kegiatan.destroy', $k->id) }}" onsubmit="return confirm('Hapus kegiatan ini?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)" title="Hapus"><span class="material-symbols-outlined" style="font-size:16px">delete</span></button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada kegiatan</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

{{-- Modal Add --}}
<div class="modal-backdrop" id="modal-add-kegiatan">
  <div class="modal">
    <div class="modal-title">Tambah Kegiatan</div>
    <form method="POST" action="{{ route('admin.kegiatan.store') }}">@csrf
      <div class="form-row"><label class="form-label">Nama Kegiatan *</label><input name="nama" class="form-input" required></div>
      <div class="form-row"><label class="form-label">Tanggal *</label><input type="date" name="tanggal_pelaksanaan" class="form-input" required></div>
      <div class="form-row"><label class="form-label"><input type="checkbox" name="wajib_hadir" value="1" checked> Wajib Hadir</label></div>
      <div class="form-row"><label class="form-label"><input type="checkbox" name="is_active" value="1" checked> Langsung Aktifkan</label></div>
      
      <div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
    </form>
  </div>
</div>

{{-- Modal Edit --}}
<div class="modal-backdrop" id="modal-edit-kegiatan">
  <div class="modal">
    <div class="modal-title">Edit Kegiatan</div>
    <form method="POST" id="edit-kegiatan-form">@csrf @method('PUT')
      <div class="form-row"><label class="form-label">Nama Kegiatan *</label><input name="nama" id="ek-nama" class="form-input" required></div>
      <div class="form-row"><label class="form-label">Tanggal *</label><input type="date" name="tanggal_pelaksanaan" id="ek-tanggal" class="form-input" required></div>
      <div class="form-row"><label class="form-label"><input type="checkbox" name="wajib_hadir" id="ek-wajib" value="1"> Wajib Hadir</label></div>
      <div class="form-row"><label class="form-label"><input type="checkbox" name="is_active" id="ek-aktif" value="1"> Aktif</label></div>
      
      <div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button><button type="submit" class="btn btn-primary">Update</button></div>
    </form>
  </div>
</div>

<script>
function openEditKegiatan(id, nama, tgl, wajib, aktif) {
    document.getElementById('edit-kegiatan-form').action = '/admin/kegiatan/' + id;
    document.getElementById('ek-nama').value = nama;
    document.getElementById('ek-tanggal').value = tgl;
    document.getElementById('ek-wajib').checked = wajib == 1;
    document.getElementById('ek-aktif').checked = aktif == 1;
    document.getElementById('modal-edit-kegiatan').classList.add('show');
}
</script>
@endsection
