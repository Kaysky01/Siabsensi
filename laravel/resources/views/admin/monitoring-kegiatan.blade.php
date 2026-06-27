@extends('layouts.admin')
@section('title', 'Monitoring Kegiatan — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Monitoring Kegiatan</div>
      <div class="page-sub">Pantau absensi kegiatan mandiri mahasiswa</div>
    </div>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>Nama Kegiatan</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        @forelse($kegiatanList as $k)
        <tr>
          <td>
            <div style="font-weight:600">{{ $k->nama }}</div>
          </td>
          <td>
            <div>{{ Carbon\Carbon::parse($k->tanggal_pelaksanaan)->format('d M Y') }}</div>
            <div style="font-size:12px;color:var(--text-muted)">{{ substr($k->jam_mulai, 0, 5) }} - {{ substr($k->jam_selesai, 0, 5) }}</div>
          </td>
          <td><span class="badge {{ $k->is_active ? 'badge-green' : 'badge-red' }}">{{ $k->is_active ? 'Aktif' : 'Selesai' }}</span></td>
          <td>
            <a href="{{ route('admin.monitoring-kegiatan.detail', $k->id) }}" class="btn btn-primary btn-sm">Lihat Detail</a>
          </td>
        </tr>
        @empty
        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada kegiatan</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
