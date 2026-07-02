@extends('layouts.mahasiswa')
@section('title', 'Pengajuan Izin/Sakit — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Pengajuan Izin/Sakit</div>
    <div class="page-sub">Ajukan ketidakhadiran dengan melampirkan bukti yang sah</div>
  </div>
  <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-izin').classList.add('show')">
    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">add</span> Pengajuan Baru
  </button>
</div>

<div class="panel">
  <table class="att-table">
    <thead>
      <tr>
        <th>Tanggal Pengajuan</th>
        <th>Tanggal Izin</th>
        <th>Jenis</th>
        <th>Alasan</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($riwayatIzin as $izin)
      <tr>
        <td>{{ Carbon\Carbon::parse($izin->created_at)->format('d M Y') }}</td>
        <td>{{ Carbon\Carbon::parse($izin->date)->format('d M Y') }}</td>
        <td>{{ ucfirst($izin->type) }}</td>
        <td>{{ $izin->keterangan }}</td>
        <td>
          @if($izin->status === 'approved')
            <span class="badge badge-success">Disetujui</span>
          @elseif($izin->status === 'rejected')
            <span class="badge badge-danger">Ditolak</span>
          @else
            <span class="badge badge-warning">Menunggu</span>
          @endif
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted)">Belum ada riwayat pengajuan izin/sakit.</td>
      </tr>
      @endforelse
    </tbody>
  </table>
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
@endsection
