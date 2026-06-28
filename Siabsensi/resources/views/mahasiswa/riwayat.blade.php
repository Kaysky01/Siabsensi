@extends('layouts.mahasiswa')
@section('title', 'Riwayat Kehadiran — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Riwayat Kehadiran</div>
    <div class="page-sub">Pantau seluruh catatan kehadiran Anda</div>
  </div>
</div>

<div class="panel">
  <table class="att-table">
    <thead>
      <tr>
        <th>Tanggal</th>
        <th>Jam Masuk</th>
        <th>Jam Pulang</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($riwayat as $r)
      <tr>
        <td>
          <div style="font-weight:600">{{ Carbon\Carbon::parse($r->date)->translatedFormat('l') }}</div>
          <div style="font-size:12px;color:var(--text-muted)">{{ Carbon\Carbon::parse($r->date)->format('d M Y') }}</div>
        </td>
        <td>{{ $r->check_in ? date('H:i', strtotime($r->check_in)) : '-' }}</td>
        <td>{{ $r->check_out ? date('H:i', strtotime($r->check_out)) : '-' }}</td>
        <td>
          @if(in_array(strtolower($r->status), ['hadir', 'present']))
            <span class="badge badge-success">Hadir</span>
          @elseif(in_array(strtolower($r->status), ['izin', 'sakit']))
            <span class="badge badge-warning">{{ ucfirst($r->status) }}</span>
          @else
            <span class="badge badge-danger">Alpha</span>
          @endif
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted)">Tidak ada data riwayat absensi.</td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
