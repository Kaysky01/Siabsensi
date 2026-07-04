@extends('layouts.admin')
@section('title', 'Kelola Sesi — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Kelola Sesi: {{ $kegiatan->nama }}</div>
      <div class="page-sub">Tanggal: {{ $kegiatan->tanggal_pelaksanaan->format('d M Y') }}</div>
    </div>
    <div style="display:flex;gap:8px">
      <a href="{{ route('admin.kegiatan') }}" class="btn btn-ghost btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">arrow_back</span> Kembali
      </a>
      <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-sesi').classList.add('show')">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">add</span> Tambah Sesi
      </button>
    </div>
  </div>

  @if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  
  @if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="panel">
    <table class="att-table">
      <thead>
        <tr>
          <th>Nama Sesi</th>
          <th>Jam Mulai</th>
          <th>Jam Selesai</th>
          <th>Status</th>
          <th>Total Hadir</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($kegiatan->sesi as $sesi)
        <tr>
          <td><strong>{{ $sesi->nama_sesi }}</strong></td>
          <td>{{ $sesi->jam_mulai ? Carbon\Carbon::parse($sesi->jam_mulai)->format('H:i') : '-' }}</td>
          <td>{{ $sesi->jam_selesai ? Carbon\Carbon::parse($sesi->jam_selesai)->format('H:i') : '-' }}</td>
          <td>
            <span class="badge {{ $sesi->is_active ? 'badge-green' : 'badge-red' }}">
              {{ $sesi->is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
          </td>
          <td>{{ $sesi->total_hadir }} mahasiswa</td>
          <td>
            <div style="display:flex;gap:4px">
              @if(auth()->user()->role === 'garda' || auth()->user()->role === 'admin' || auth()->user()->role === 'timdis')
              <a href="{{ route('admin.absensi-manual.index', $sesi->id) }}" class="btn btn-ghost btn-sm" title="Absensi Manual" style="color:var(--primary)">
                <span class="material-symbols-outlined" style="font-size:16px">checklist</span>
              </a>
              <a href="{{ route('admin.monitoring-sesi', $sesi->id) }}" class="btn btn-ghost btn-sm" title="Monitoring" style="color:var(--info)">
                <span class="material-symbols-outlined" style="font-size:16px">monitoring</span>
              </a>
              @endif
              <form method="POST" action="{{ route('admin.kegiatan-sesi.toggle', [$kegiatan->id, $sesi->id]) }}" style="margin:0">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm" title="{{ $sesi->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                  <span class="material-symbols-outlined" style="font-size:16px">{{ $sesi->is_active ? 'pause_circle' : 'play_circle' }}</span>
                </button>
              </form>
              <button class="btn btn-ghost btn-sm" title="Edit" onclick="openEditSesi({{ $sesi->id }}, '{{ addslashes($sesi->nama_sesi) }}', '{{ $sesi->jam_mulai }}', '{{ $sesi->jam_selesai }}', {{ $sesi->is_active ? 1 : 0 }})">
                <span class="material-symbols-outlined" style="font-size:16px">edit</span>
              </button>
              <form method="POST" action="{{ route('admin.kegiatan-sesi.destroy', [$kegiatan->id, $sesi->id]) }}" onsubmit="return confirm('Hapus sesi ini dan semua data absensinya?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)" title="Hapus">
                  <span class="material-symbols-outlined" style="font-size:16px">delete</span>
                </button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="6" style="text-align:center;color:var(--text-muted);padding:30px">
            Belum ada sesi. Klik "Tambah Sesi" untuk membuat sesi baru.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

{{-- Modal Add --}}
<div class="modal-backdrop" id="modal-add-sesi">
  <div class="modal">
    <div class="modal-title">Tambah Sesi</div>
    <form method="POST" action="{{ route('admin.kegiatan-sesi.store', $kegiatan->id) }}">
      @csrf
      <div class="form-row">
        <label class="form-label">Nama Sesi *</label>
        <input name="nama_sesi" class="form-input" placeholder="Contoh: Sesi 1 - Pengenalan" required>
      </div>
      <div class="form-row">
        <label class="form-label">Jam Mulai</label>
        <input type="time" name="jam_mulai" class="form-input">
      </div>
      <div class="form-row">
        <label class="form-label">Jam Selesai</label>
        <input type="time" name="jam_selesai" class="form-input">
      </div>
      <div class="form-row">
        <label class="form-label">
          <input type="checkbox" name="is_active" value="1" checked> Aktif
        </label>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Edit --}}
<div class="modal-backdrop" id="modal-edit-sesi">
  <div class="modal">
    <div class="modal-title">Edit Sesi</div>
    <form method="POST" id="edit-sesi-form">
      @csrf @method('PUT')
      <div class="form-row">
        <label class="form-label">Nama Sesi *</label>
        <input name="nama_sesi" id="edit-nama-sesi" class="form-input" required>
      </div>
      <div class="form-row">
        <label class="form-label">Jam Mulai</label>
        <input type="time" name="jam_mulai" id="edit-jam-mulai" class="form-input">
      </div>
      <div class="form-row">
        <label class="form-label">Jam Selesai</label>
        <input type="time" name="jam_selesai" id="edit-jam-selesai" class="form-input">
      </div>
      <div class="form-row">
        <label class="form-label">
          <input type="checkbox" name="is_active" id="edit-is-active" value="1"> Aktif
        </label>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditSesi(id, nama, jamMulai, jamSelesai, isActive) {
  document.getElementById('edit-sesi-form').action = '/admin/kegiatan/{{ $kegiatan->id }}/sesi/' + id;
  document.getElementById('edit-nama-sesi').value = nama;
  document.getElementById('edit-jam-mulai').value = jamMulai || '';
  document.getElementById('edit-jam-selesai').value = jamSelesai || '';
  document.getElementById('edit-is-active').checked = isActive == 1;
  document.getElementById('modal-edit-sesi').classList.add('show');
}
</script>
@endsection
