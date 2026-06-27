@extends('layouts.admin')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Master Data Kompi</div>
    <div class="page-sub">Kelola daftar kompi beserta penanggung jawab (Garda)</div>
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
        <th>Penanggung Jawab (Garda)</th>
        <th width="150">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($kompiList as $k)
      <tr>
        <td style="font-weight:600"><span class="badge badge-blue">{{ $k->nama }}</span></td>
        <td>
          @if($k->gardas && $k->gardas->count() > 0)
            <div style="display:flex;flex-wrap:wrap;gap:8px">
              @foreach($k->gardas as $g)
              <div style="display:inline-flex;align-items:center;gap:6px;background:#f8fafc;border:1px solid #e2e8f0;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:500;color:#334155;box-shadow:0 1px 2px rgba(0,0,0,0.02)">
                <span class="material-symbols-outlined" style="font-size:14px;color:var(--primary)">shield_person</span>
                {{ $g->full_name }}
              </div>
              @endforeach
            </div>
          @else
            <span style="color:var(--danger);font-size:13px"><span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">warning</span> Belum di-assign</span>
          @endif
        </td>
        <td>
          <div style="display:flex;gap:4px">
            <button onclick="editKompi({{ $k->id }}, '{{ addslashes($k->nama) }}', {{ json_encode($k->gardas->pluck('username')) }})" class="btn btn-ghost btn-sm" title="Edit Kompi">
              <span class="material-symbols-outlined" style="font-size:16px">edit</span>
            </button>
            <form method="POST" action="{{ route('admin.master.kompi.destroy', $k->id) }}" onsubmit="return confirm('Hapus Kompi ini?')">
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
        <td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">Belum ada data Kompi</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

<!-- Modal Tambah Kompi -->
<div class="modal-backdrop" id="modal-add-kompi">
  <div class="modal">
    <div class="modal-title">Tambah Kompi</div>
    <form method="POST" action="{{ route('admin.master.kompi.store') }}">
      @csrf
      <div class="form-row"><label class="form-label">Nama Kompi *</label><input name="nama" class="form-input" required placeholder="Contoh: A"></div>
      <div class="form-row">
        <label class="form-label">Penanggung Jawab Garda (Maks 5)</label>
        <select name="garda_ids[]" class="form-input" multiple style="height:140px;padding:8px;font-size:13px;line-height:1.5">
          @foreach($gardaUsers as $garda)
            <option value="{{ $garda->username }}" style="padding:6px 10px;border-radius:4px;margin-bottom:2px;">&#128110; {{ $garda->full_name }}</option>
          @endforeach
        </select>
        <span class="form-hint" style="display:block;margin-top:6px;color:var(--text-muted)">
          <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">info</span>
          Tahan tombol Ctrl (Windows) / Cmd (Mac) untuk memilih lebih dari 1
        </span>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Kompi -->
<div class="modal-backdrop" id="modal-edit-kompi">
  <div class="modal">
    <div class="modal-title">Edit Kompi</div>
    <form method="POST" id="form-edit-kompi">
      @csrf @method('PUT')
      <div class="form-row"><label class="form-label">Nama Kompi *</label><input name="nama" id="edit-kompi-nama" class="form-input" required></div>
      <div class="form-row">
        <label class="form-label">Penanggung Jawab Garda (Maks 5)</label>
        <select name="garda_ids[]" id="edit-kompi-garda" class="form-input" multiple style="height:140px;padding:8px;font-size:13px;line-height:1.5">
          @foreach($gardaUsers as $garda)
            <option value="{{ $garda->username }}" style="padding:6px 10px;border-radius:4px;margin-bottom:2px;">&#128110; {{ $garda->full_name }}</option>
          @endforeach
        </select>
        <span class="form-hint" style="display:block;margin-top:6px;color:var(--text-muted)">
          <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">info</span>
          Tahan tombol Ctrl (Windows) / Cmd (Mac) untuk memilih lebih dari 1
        </span>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function editKompi(id, nama, gardaIds) {
  document.getElementById('form-edit-kompi').action = '/admin/master/kompi/' + id;
  document.getElementById('edit-kompi-nama').value = nama;
  
  let select = document.getElementById('edit-kompi-garda');
  for (let i = 0; i < select.options.length; i++) {
    select.options[i].selected = gardaIds.includes(select.options[i].value);
  }
  
  document.getElementById('modal-edit-kompi').classList.add('show');
}
</script>
@endsection
