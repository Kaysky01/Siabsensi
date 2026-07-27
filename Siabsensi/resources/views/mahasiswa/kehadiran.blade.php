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
        <th>Bukti</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($riwayatKehadiran as $kehadiran)
      <tr>
        <td>{{ Carbon\Carbon::parse($kehadiran->created_at)->format('d M Y') }}</td>
        <td>
          @php
            $pkkmbSch = $schedules->firstWhere(function($s) use ($kehadiran) {
              return \Carbon\Carbon::parse($s->tanggal)->format('Y-m-d') === \Carbon\Carbon::parse($kehadiran->date)->format('Y-m-d');
            });
          @endphp
          @if($pkkmbSch)
            <div style="font-weight:600;color:var(--primary)">PKKMB Day {{ $pkkmbSch->hari_ke }}</div>
            <div style="font-size:12px;color:var(--text-muted)">{{ \Carbon\Carbon::parse($kehadiran->date)->format('d M Y') }}</div>
          @else
            {{ \Carbon\Carbon::parse($kehadiran->date)->format('d M Y') }}
          @endif
        </td>
        <td>{{ $kehadiran->keterangan }}</td>
        <td>
          @if($kehadiran->bukti_path)
            <button type="button" class="btn btn-ghost btn-sm" style="color:var(--primary);padding:2px 8px;font-size:12px" onclick="showBukti('{{ asset('file-bukti/' . $kehadiran->bukti_path) }}')">
              <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">visibility</span> Lihat
            </button>
          @else
            <span style="color:var(--text-muted);font-size:12px">-</span>
          @endif
        </td>
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
        <td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted)">Belum ada riwayat pengajuan kehadiran manual.</td>
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
        <label class="form-label">Kegiatan PKKMB</label>
        <select name="date" class="form-input" required style="width:100%">
          <option value="">-- Pilih Kegiatan PKKMB --</option>
          @foreach($schedules as $sch)
            <option value="{{ \Carbon\Carbon::parse($sch->tanggal)->format('Y-m-d') }}">
              PKKMB Day {{ $sch->hari_ke }} ({{ \Carbon\Carbon::parse($sch->tanggal)->translatedFormat('d F Y') }})
            </option>
          @endforeach
        </select>
      </div>
      
      <div class="form-row">
        <label class="form-label">Alasan Pengajuan</label>
        <textarea name="reason" class="form-input" rows="3" required placeholder="Contoh: Kamera error saat absensi pagi, atau sedang tugas luar lab..."></textarea>
      </div>

      <div class="form-row">
        <label class="form-label">Bukti Kehadiran (Opsional/Jika Ada)</label>
        <input type="file" name="bukti" id="input-bukti-kehadiran" class="form-input" accept=".jpg,.jpeg,.png,.pdf" onchange="previewFileStandalone(this, 'preview-bukti-kehadiran')">
        <span class="form-hint">Misal: Foto lokasi, foto kegiatan lapangan (Maks 10MB).</span>
        <div id="preview-bukti-kehadiran" style="display:none;margin-top:12px;padding:12px;background:var(--bg,#f8fafc);border:1px dashed var(--border,#cbd5e1);border-radius:8px;text-align:center"></div>
      </div>
      
      <div class="modal-actions" style="margin-top:24px;display:flex;justify-content:flex-end;gap:12px">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-add-kehadiran').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Kirim Pengajuan</button>
      </div>
    </form>
  </div>
</div>
@endsection
