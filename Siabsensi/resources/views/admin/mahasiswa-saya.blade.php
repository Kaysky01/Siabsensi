@extends('layouts.admin')
@section('title', 'Mahasiswa Saya — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Mahasiswa Saya</div>
      <div class="page-sub">Daftar mahasiswa dalam pengawasan Anda ( {{ auth()->user()->assigned_kompi ?? 'Semua' }})</div>
    </div>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>Mahasiswa</th><th>Kompi</th><th>Prodi</th><th>Email</th><th>No. Telp</th><th>Status Kegiatan</th></tr></thead>
      <tbody>
        @forelse($mahasiswaList as $m)
        <tr>
          <td>
            <div class="mahasiswa-cell">
              <div class="avatar" style="background:var(--primary-light);color:var(--primary)">{{ strtoupper(substr($m->name, 0, 2)) }}</div>
              <div>
                <div class="mhs-name">{{ $m->name }}</div>
                <div class="mhs-dept">{{ $m->nim ?? '-' }}</div>
              </div>
            </div>
          </td>
          <td><span class="badge badge-blue">{{ $m->kompi }}</span></td>
          <td style="font-size:13px">{{ $m->prodi ?? '-' }}</td>
          <td style="font-size:13px;color:var(--text-muted)">{{ $m->email ?? '-' }}</td>
          <td style="font-size:13px;color:var(--text-muted)">{{ $m->no_telp_mahasiswa ?? '-' }}</td>
          <td>
            <div style="display:flex;gap:4px">
              @foreach($allKegiatan as $keg)
                @php
                  $att = $m->attendances->where('kegiatan_id', $keg->id)->first();
                  if(!$att) {
                    // Alpha (Merah)
                    $color = '#ef4444';
                    $title = $keg->nama . ' - Alpha';
                  } else if(!$att->check_out) {
                    // Baru Masuk (Hitam)
                    $color = '#1f2937';
                    $title = $keg->nama . ' - Masuk (Belum Keluar)';
                  } else {
                    // Lengkap (Hijau)
                    $color = '#10b981';
                    $title = $keg->nama . ' - Hadir Penuh';
                  }
                @endphp
                <div style="width: 14px; height: 14px; background-color: {{ $color }}; border-radius: 50%; display:inline-block; border: 1px solid rgba(0,0,0,0.1);" title="{{ $title }}"></div>
              @endforeach
              @if($allKegiatan->isEmpty())
                <span style="font-size:12px;color:#9ca3af">Belum ada kegiatan</span>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada data mahasiswa di kompi Anda</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
