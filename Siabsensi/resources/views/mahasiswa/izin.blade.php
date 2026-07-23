@extends('layouts.mahasiswa')
@section('title', 'Pengajuan Izin/Sakit — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Pengajuan Izin/Sakit</div>
    <div class="page-sub">Ajukan ketidakhadiran dengan melampirkan bukti yang sah</div>
  </div>
  <div class="header-actions">
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-izin').classList.add('show')">
      <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">add</span> Pengajuan Baru
    </button>
  </div>
</div>

@if(session('success'))
<div style="background:var(--success-light);color:var(--success);padding:12px 16px;border-radius:8px;border:1px solid var(--success);display:flex;align-items:center;gap:8px;margin-bottom:16px">
  <span class="material-symbols-outlined" style="font-size:20px">check_circle</span>
  <span style="flex:1;font-size:14px;font-weight:500">{{ session('success') }}</span>
  <button onclick="this.parentElement.remove()" style="background:none;border:none;color:var(--success);cursor:pointer;font-size:18px">&times;</button>
</div>
@endif

@if(session('error'))
<div style="background:var(--danger-light);color:var(--danger);padding:12px 16px;border-radius:8px;border:1px solid var(--danger);display:flex;align-items:center;gap:8px;margin-bottom:16px">
  <span class="material-symbols-outlined" style="font-size:20px">error</span>
  <span style="flex:1;font-size:14px;font-weight:500">{{ session('error') }}</span>
  <button onclick="this.parentElement.remove()" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:18px">&times;</button>
</div>
@endif

@if($errors->any())
<div style="background:var(--danger-light);color:var(--danger);padding:12px 16px;border-radius:8px;border:1px solid var(--danger);display:flex;align-items:flex-start;gap:8px;margin-bottom:16px">
  <span class="material-symbols-outlined" style="font-size:20px;margin-top:2px">error</span>
  <div style="flex:1;font-size:14px">
    @foreach($errors->all() as $error)
    <div>{{ $error }}</div>
    @endforeach
  </div>
  <button onclick="this.parentElement.remove()" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:18px">&times;</button>
</div>
@endif

<div class="panel izin-desktop">
  <table class="att-table">
    <thead>
      <tr>
        <th style="width:50px;text-align:center;white-space:nowrap">No</th>
        <th>Tanggal Pengajuan</th>
        <th>Tanggal Izin</th>
        <th>Jenis</th>
        <th>Alasan</th>
        <th>Bukti</th>
        <th>Status</th>
        <th style="width:60px;text-align:center;white-space:nowrap">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($riwayatIzin as $izin)
      <tr>
        <td style="text-align:center">{{ $loop->iteration }}</td>
        <td>{{ Carbon\Carbon::parse($izin->created_at)->format('d M Y') }}</td>
        <td>{{ Carbon\Carbon::parse($izin->date)->format('d M Y') }}</td>
        <td>{{ ucfirst($izin->submission_type) }}</td>
        <td>{{ $izin->keterangan }}</td>
        <td>
          @if($izin->bukti_path)
            <button type="button" class="btn btn-ghost btn-sm" style="font-size:11px;padding:2px 8px" onclick="showBukti('{{ asset('storage/' . $izin->bukti_path) }}', '{{ Str::endsWith($izin->bukti_path, ['.jpg', '.jpeg', '.png']) ? 'image' : 'pdf' }}')">
              <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">visibility</span> Lihat
            </button>
          @else
            <span style="color:var(--text-muted);font-size:12px">-</span>
          @endif
        </td>
        <td>
          @if($izin->status === 'approved')
            <span class="badge badge-success">Disetujui</span>
          @elseif($izin->status === 'rejected')
            <span class="badge badge-danger">Ditolak</span>
          @else
            <span class="badge badge-warning">Menunggu</span>
          @endif
        </td>
        <td style="text-align:center">
          @if($izin->status === 'pending')
          <button type="button" class="btn btn-ghost btn-sm" style="color:var(--danger);padding:4px" onclick="confirmDelete('{{ route('mahasiswa.izin.delete', $izin->id) }}')">
            <span class="material-symbols-outlined" style="font-size:18px">delete</span>
          </button>
          @endif
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">Belum ada riwayat pengajuan izin/sakit.</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="izin-mobile">
  @forelse($riwayatIzin as $izin)
  <div class="panel" style="margin-bottom:12px;padding:14px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <span style="font-weight:600;font-size:14px">#{{ $loop->iteration }} — {{ ucfirst($izin->submission_type) }}</span>
      @if($izin->status === 'approved')
        <span class="badge badge-success">Disetujui</span>
      @elseif($izin->status === 'rejected')
        <span class="badge badge-danger">Ditolak</span>
      @else
        <span class="badge badge-warning">Menunggu</span>
      @endif
    </div>
    <div style="font-size:13px;color:var(--text-secondary);margin-bottom:4px">
      <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">calendar_today</span>
      Pengajuan: {{ Carbon\Carbon::parse($izin->created_at)->format('d M Y') }}
    </div>
    <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px">
      <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">event</span>
      Tanggal Izin: {{ Carbon\Carbon::parse($izin->date)->format('d M Y') }}
    </div>
    <div style="font-size:13px;color:var(--text-muted)">{{ $izin->keterangan }}</div>
    @if($izin->bukti_path)
    <div style="margin-top:8px;display:flex;justify-content:space-between;align-items:center">
      <button type="button" class="btn btn-ghost btn-sm" style="font-size:12px;padding:4px 10px;width:auto" onclick="showBukti('{{ asset('storage/' . $izin->bukti_path) }}', '{{ Str::endsWith($izin->bukti_path, ['.jpg', '.jpeg', '.png']) ? 'image' : 'pdf' }}')">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">visibility</span> Lihat Bukti
      </button>
      @if($izin->status === 'pending')
      <button type="button" class="btn btn-ghost btn-sm" style="color:var(--danger);font-size:12px;padding:4px 10px;width:auto" onclick="confirmDelete('{{ route('mahasiswa.izin.delete', $izin->id) }}')">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">delete</span> Hapus
      </button>
      @endif
    </div>
    @elseif($izin->status === 'pending')
    <div style="margin-top:8px;display:flex;justify-content:flex-end">
      <button type="button" class="btn btn-ghost btn-sm" style="color:var(--danger);font-size:12px;padding:4px 10px;width:auto" onclick="confirmDelete('{{ route('mahasiswa.izin.delete', $izin->id) }}')">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">delete</span> Hapus
      </button>
    </div>
    @endif
  </div>
  @empty
  <div class="panel" style="padding:30px;text-align:center;color:var(--text-muted)">Belum ada riwayat pengajuan izin/sakit.</div>
  @endforelse
</div>

{{-- Modal Pengajuan Baru --}}
<div class="modal-backdrop" id="modal-add-izin">
  <div class="modal">
    <div class="modal-title">Buat Pengajuan Baru</div>
    <form method="POST" action="{{ route('mahasiswa.izin.submit') }}" enctype="multipart/form-data">
      @csrf
      
      <div class="form-row-2">
        <div class="form-row">
          <label class="form-label">Jenis Pengajuan</label>
          <select name="type" class="form-input" required>
            <option value="izin">Izin</option>
            <option value="sakit">Sakit</option>
          </select>
        </div>
      </div>
      
      <div class="form-row">
        <label class="form-label">Tanggal Izin/Sakit</label>
        <input type="date" name="date" class="form-input" required min="{{ date('Y-m-d') }}">
      </div>
      
      <div class="form-row">
        <label class="form-label">Alasan</label>
        <textarea name="reason" class="form-input" rows="3" required></textarea>
      </div>

      <div class="form-row">
        <label class="form-label">Bukti Lampiran (PDF/JPG/PNG)</label>
        <input type="file" name="bukti" class="form-input" accept=".pdf,image/*" required>
        <span class="form-hint">Maksimal 2MB. Untuk sakit, harap lampirkan surat dokter.</span>
      </div>
      
      <div class="modal-actions" style="margin-top:24px;display:flex;justify-content:flex-end;gap:12px">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-add-izin').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Ajukan Sekarang</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="modal-bukti">
  <div class="modal modal-bukti">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div class="modal-title" style="margin-bottom:0">Bukti Lampiran</div>
      <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('modal-bukti').classList.remove('show')" style="padding:4px">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <div id="bukti-content" style="text-align:center;min-height:200px"></div>
  </div>
</div>

<div class="modal-backdrop" id="modal-delete">
  <div class="modal" style="max-width:400px;text-align:center;padding:32px 24px">
    <div style="width:64px;height:64px;border-radius:50%;background:var(--danger-light);color:var(--danger);display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
      <span class="material-symbols-outlined" style="font-size:32px">warning</span>
    </div>
    <div style="font-size:18px;font-weight:600;margin-bottom:8px">Batalkan Pengajuan?</div>
    <div style="font-size:14px;color:var(--text-secondary);margin-bottom:24px">
      Apakah Anda yakin ingin membatalkan pengajuan ini? Data yang dihapus tidak dapat dikembalikan.
    </div>
    
    <form id="form-delete" method="POST" action="">
      @csrf
      @method('DELETE')
      <div style="display:flex;gap:12px;justify-content:center">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-delete').classList.remove('show')" style="flex:1;justify-content:center">Batal</button>
        <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;background:var(--danger);border-color:var(--danger)">Ya, Batalkan</button>
      </div>
    </form>
  </div>
</div>

<style>
  .izin-mobile { display: none; }
  @media (max-width: 768px) {
    .izin-desktop { display: none; }
    .izin-mobile { display: block; }
    .page-header .header-actions .btn { width: auto; }
    #modal-add-izin .modal,
    .modal-bukti,
    #modal-delete .modal {
      width: calc(100% - 32px) !important;
      max-width: calc(100% - 32px) !important;
      padding: 16px;
      margin: 16px;
      max-height: 85vh;
    }
    #modal-add-izin .modal-title {
      font-size: 18px;
    }
    #modal-add-izin .modal-actions {
      flex-direction: column-reverse;
    }
    #modal-add-izin .modal-actions .btn {
      width: 100%;
      justify-content: center;
    }
    .modal-bukti #bukti-content img {
      max-height: 50vh;
    }
    .modal-bukti #bukti-content iframe {
      height: 50vh;
    }
    #modal-delete .modal form div {
      flex-direction: column-reverse;
    }
  }
</style>

<script>
  function showBukti(url, type) {
    var content = document.getElementById('bukti-content');
    if (type === 'image') {
      content.innerHTML = '<img src="' + url + '" alt="Bukti" style="max-width:100%;max-height:70vh;border-radius:8px;border:1px solid var(--border)">';
    } else {
      content.innerHTML = '<iframe src="' + url + '" style="width:100%;height:70vh;border:1px solid var(--border);border-radius:8px"></iframe>';
    }
    document.getElementById('modal-bukti').classList.add('show');
  }

  function confirmDelete(url) {
    document.getElementById('form-delete').action = url;
    document.getElementById('modal-delete').classList.add('show');
  }
</script>
@endsection
