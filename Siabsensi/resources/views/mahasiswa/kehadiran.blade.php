@extends('layouts.mahasiswa')
@section('title', 'Pengajuan Kehadiran — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Pengajuan Kehadiran Manual</div>
    <div class="page-sub">Gunakan formulir ini jika kamera bermasalah atau Anda menghadiri tugas luar/lapangan.</div>
  </div>
  <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-kehadiran').classList.add('show')">
    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">add</span> Pengajuan Baru
  </button>
</div>

<div class="panel">
  <table class="att-table">
    <thead>
      <tr>
        <th>Tanggal Pengajuan</th>
        <th>Untuk Tanggal</th>
        <th>Alasan</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($riwayatKehadiran as $kehadiran)
      <tr>
        <td>{{ Carbon\Carbon::parse($kehadiran->created_at)->format('d M Y') }}</td>
        <td>{{ Carbon\Carbon::parse($kehadiran->date)->format('d M Y') }}</td>
        <td>{{ $kehadiran->keterangan }}</td>
        <td>
          @if($kehadiran->status === 'approved')
            <span class="badge badge-success">Disetujui</span>
          @elseif($kehadiran->status === 'rejected')
            <span class="badge badge-danger">Ditolak</span>
          @else
            <span class="badge badge-warning">Menunggu</span>
          @endif
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Belum ada riwayat pengajuan kehadiran manual.</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- Modal Pengajuan Baru --}}
<div class="modal-backdrop" id="modal-add-kehadiran">
  <div class="modal">
    <div class="modal-title">Buat Pengajuan Kehadiran</div>
    <form method="POST" action="{{ route('mahasiswa.kehadiran.submit') }}" enctype="multipart/form-data">
      @csrf
      
      <div class="form-row">
        <label class="form-label">Tanggal Kehadiran</label>
        <input type="date" name="date" class="form-input" required max="{{ date('Y-m-d') }}">
      </div>
      
      <div class="form-row">
        <label class="form-label">Alasan Pengajuan</label>
        <textarea name="reason" class="form-input" rows="3" required placeholder="Contoh: Kamera error saat absensi pagi, atau sedang tugas luar lab..."></textarea>
      </div>

      <div class="form-row">
        <label class="form-label">Bukti Kehadiran (Opsional/Jika Ada)</label>
        <input type="file" name="bukti" class="form-input" accept=".jpg,.jpeg,.png">
        <span class="form-hint">Misal: Foto lokasi, foto kegiatan lapangan (Maks 2MB).</span>
      </div>
      
      <div class="modal-actions" style="margin-top:24px;display:flex;justify-content:flex-end;gap:12px">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-add-kehadiran').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Kirim Pengajuan</button>
      </div>
    </form>
  </div>
</div>
@endsection
