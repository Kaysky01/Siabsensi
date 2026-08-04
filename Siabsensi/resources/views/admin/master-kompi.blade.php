@extends('layouts.admin')

@section('content')
<style>
.checkbox-list-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 8px;
  max-height: 160px;
  overflow-y: auto;
  padding: 10px;
  background: var(--bg-light, #f8fafc);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: var(--radius-md, 8px);
}

.cb-card {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}

.cb-card:hover {
  border-color: var(--primary, #3b82f6);
  background: #f0f7ff;
}

.cb-card input[type="checkbox"] {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: var(--primary, #3b82f6);
  flex-shrink: 0;
}

.cb-card-icon {
  font-size: 18px;
  flex-shrink: 0;
}

.cb-card-text {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.cb-card-name {
  font-size: 12px;
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cb-card-user {
  font-size: 10px;
  color: #64748b;
}
</style>

<div class="page-header">
  <div>
    <div class="page-title">Master Data Kompi</div>
    <div class="page-sub">Kelola daftar kompi beserta penanggung jawab (Garda & Tim Disiplin)</div>
  </div>
  <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-kompi').classList.add('show')">
    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">add</span> Tambah Kompi
  </button>
</div>

<div class="panel">
  <table class="att-table">
    <thead>
      <tr>
        <th>Nama Kompi</th>
        <th>Jumlah Anggota</th>
        <th>Penanggung Jawab (Garda)</th>
        <th>Penanggung Jawab (Tim Disiplin)</th>
        <th width="120">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($kompiList as $k)
      <tr>
        <td style="font-weight:600"><span class="badge badge-blue" style="font-size:13px;padding:5px 12px">{{ $k->nama }}</span></td>
        <td>
          <span class="badge" style="background:#f1f5f9;color:#1e293b;border:1px solid #cbd5e1;font-size:12px;padding:4px 10px;border-radius:20px;font-weight:700">
            <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;margin-right:2px;color:#2563eb">groups</span>
            {{ number_format($k->totalMahasiswa ?? 0) }} Anggota
          </span>
        </td>
        <td>
          @if($k->gardas && $k->gardas->count() > 0)
            <div style="display:flex;flex-wrap:wrap;gap:6px">
              @foreach($k->gardas as $g)
              <div style="display:inline-flex;align-items:center;gap:6px;background:#f0fdf4;border:1px solid #bbf7d0;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;color:#166534;box-shadow:0 1px 2px rgba(0,0,0,0.02)">
                <span class="material-symbols-outlined" style="font-size:14px;color:#16a34a">shield_person</span>
                {{ $g->full_name }}
              </div>
              @endforeach
            </div>
          @else
            <span style="color:var(--text-muted);font-size:12px">Belum di-assign</span>
          @endif
        </td>
        <td>
          @if($k->timdisList && $k->timdisList->count() > 0)
            <div style="display:flex;flex-wrap:wrap;gap:6px">
              @foreach($k->timdisList as $t)
              <div style="display:inline-flex;align-items:center;gap:6px;background:#eff6ff;border:1px solid #bfdbfe;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;color:#1e40af;box-shadow:0 1px 2px rgba(0,0,0,0.02)">
                <span class="material-symbols-outlined" style="font-size:14px;color:#2563eb">gavel</span>
                {{ $t->full_name }}
              </div>
              @endforeach
            </div>
          @else
            <span style="color:var(--text-muted);font-size:12px">Belum di-assign</span>
          @endif
        </td>
        <td>
          <div style="display:flex;gap:4px">
            <button onclick="editKompi({{ $k->id }}, '{{ addslashes($k->nama) }}', {{ json_encode($k->gardas->pluck('username')) }}, {{ json_encode($k->timdisList->pluck('username')) }})" class="btn btn-ghost btn-sm" title="Edit Kompi">
              <span class="material-symbols-outlined" style="font-size:16px">edit</span>
            </button>
            <form method="POST" action="{{ route('admin.master.kompi.destroy', $k->id) }}" onsubmit="return confirm('Hapus Kompi {{ addslashes($k->nama) }}?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-ghost btn-sm" title="Hapus Kompi" style="color:var(--danger)">
                <span class="material-symbols-outlined" style="font-size:16px">delete</span>
              </button>
            </form>
          </div>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted)">Belum ada data Kompi</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

<!-- Modal Tambah Kompi -->
<div class="modal-backdrop" id="modal-add-kompi">
  <div class="modal" style="max-width:550px">
    <div class="modal-title">Tambah Kompi</div>
    <form method="POST" action="{{ route('admin.master.kompi.store') }}">
      @csrf
      <div class="form-row">
        <label class="form-label">Nama Kompi *</label>
        <input name="nama" class="form-input" required placeholder="Contoh: KOMPI 1">
      </div>

      {{-- Garda Checkboxes --}}
      <div class="form-row">
        <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
          <span>Penanggung Jawab Garda (Maks 5)</span>
          <span style="font-size:11px;color:var(--text-muted)">Pilih centang</span>
        </label>
        <div class="checkbox-list-container">
          @forelse($gardaUsers as $garda)
          <label class="cb-card">
            <input type="checkbox" name="garda_ids[]" value="{{ $garda->username }}" class="garda-cb-add">
            <span class="material-symbols-outlined cb-card-icon" style="color:#16a34a">shield_person</span>
            <div class="cb-card-text">
              <div class="cb-card-name">{{ $garda->full_name }}</div>
              <div class="cb-card-user">{{ $garda->username }} @if($garda->assigned_kompi) <span style="color:#2563eb">({{ $garda->assigned_kompi }})</span> @endif</div>
            </div>
          </label>
          @empty
          <div style="font-size:12px;color:var(--text-muted);padding:8px">Belum ada akun Garda</div>
          @endforelse
        </div>
      </div>

      {{-- Timdis Checkboxes --}}
      <div class="form-row" style="margin-top:14px">
        <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
          <span>Penanggung Jawab Tim Disiplin (Maks 5)</span>
          <span style="font-size:11px;color:var(--text-muted)">Pilih centang</span>
        </label>
        <div class="checkbox-list-container">
          @forelse($timdisUsers as $timdis)
          <label class="cb-card">
            <input type="checkbox" name="timdis_ids[]" value="{{ $timdis->username }}" class="timdis-cb-add">
            <span class="material-symbols-outlined cb-card-icon" style="color:#2563eb">gavel</span>
            <div class="cb-card-text">
              <div class="cb-card-name">{{ $timdis->full_name }}</div>
              <div class="cb-card-user">{{ $timdis->username }} @if($timdis->assigned_kompi) <span style="color:#2563eb">({{ $timdis->assigned_kompi }})</span> @endif</div>
            </div>
          </label>
          @empty
          <div style="font-size:12px;color:var(--text-muted);padding:8px">Belum ada akun Timdis</div>
          @endforelse
        </div>
      </div>

      <div class="modal-actions" style="margin-top:20px">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Kompi -->
<div class="modal-backdrop" id="modal-edit-kompi">
  <div class="modal" style="max-width:550px">
    <div class="modal-title">Edit Kompi</div>
    <form method="POST" id="form-edit-kompi">
      @csrf @method('PUT')
      <div class="form-row">
        <label class="form-label">Nama Kompi *</label>
        <input name="nama" id="edit-kompi-nama" class="form-input" required>
      </div>

      {{-- Garda Checkboxes Edit --}}
      <div class="form-row">
        <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
          <span>Penanggung Jawab Garda (Maks 5)</span>
          <span style="font-size:11px;color:var(--text-muted)">Pilih centang</span>
        </label>
        <div class="checkbox-list-container">
          @forelse($gardaUsers as $garda)
          <label class="cb-card">
            <input type="checkbox" name="garda_ids[]" value="{{ $garda->username }}" class="garda-cb-edit">
            <span class="material-symbols-outlined cb-card-icon" style="color:#16a34a">shield_person</span>
            <div class="cb-card-text">
              <div class="cb-card-name">{{ $garda->full_name }}</div>
              <div class="cb-card-user">{{ $garda->username }} @if($garda->assigned_kompi) <span style="color:#2563eb">({{ $garda->assigned_kompi }})</span> @endif</div>
            </div>
          </label>
          @empty
          <div style="font-size:12px;color:var(--text-muted);padding:8px">Belum ada akun Garda</div>
          @endforelse
        </div>
      </div>

      {{-- Timdis Checkboxes Edit --}}
      <div class="form-row" style="margin-top:14px">
        <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
          <span>Penanggung Jawab Tim Disiplin (Maks 5)</span>
          <span style="font-size:11px;color:var(--text-muted)">Pilih centang</span>
        </label>
        <div class="checkbox-list-container">
          @forelse($timdisUsers as $timdis)
          <label class="cb-card">
            <input type="checkbox" name="timdis_ids[]" value="{{ $timdis->username }}" class="timdis-cb-edit">
            <span class="material-symbols-outlined cb-card-icon" style="color:#2563eb">gavel</span>
            <div class="cb-card-text">
              <div class="cb-card-name">{{ $timdis->full_name }}</div>
              <div class="cb-card-user">{{ $timdis->username }} @if($timdis->assigned_kompi) <span style="color:#2563eb">({{ $timdis->assigned_kompi }})</span> @endif</div>
            </div>
          </label>
          @empty
          <div style="font-size:12px;color:var(--text-muted);padding:8px">Belum ada akun Timdis</div>
          @endforelse
        </div>
      </div>

      <div class="modal-actions" style="margin-top:20px">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function editKompi(id, nama, gardaUsernames, timdisUsernames) {
  document.getElementById('form-edit-kompi').action = '/admin/master/kompi/' + id;
  document.getElementById('edit-kompi-nama').value = nama;
  
  // Set garda checkboxes
  document.querySelectorAll('#modal-edit-kompi .garda-cb-edit').forEach(cb => {
    cb.checked = Array.isArray(gardaUsernames) && gardaUsernames.includes(cb.value);
  });

  // Set timdis checkboxes
  document.querySelectorAll('#modal-edit-kompi .timdis-cb-edit').forEach(cb => {
    cb.checked = Array.isArray(timdisUsernames) && timdisUsernames.includes(cb.value);
  });
  
  document.getElementById('modal-edit-kompi').classList.add('show');
}
</script>
@endsection
