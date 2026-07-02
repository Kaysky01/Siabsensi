@extends('layouts.admin')
@section('title', 'Absensi Hari Ini — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Absensi Hari Ini</div>
      <div class="page-sub">{{ Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }}</div>
    </div>
    <div class="header-actions">
      <form method="GET" action="{{ route('admin.attendance') }}" style="display:flex;gap:8px;align-items:center">
        <input type="date" name="date" class="form-input" style="width:160px;padding:7px 10px" value="{{ $date }}">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <button type="submit" class="btn btn-ghost btn-sm">
          <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">search</span> Cari
        </button>
      </form>
      <a href="{{ route('admin.attendance.export', ['start' => $date, 'end' => $date]) }}" class="btn btn-ghost btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Export
      </a>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;padding:14px 20px">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <span class="form-label" style="margin-bottom:0">Filter Status</span>
      @foreach(['all' => 'Semua', 'hadir' => 'Hadir/Absen', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpha' => 'Alpha'] as $val => $label)
        <a href="{{ route('admin.attendance', ['date' => $date, 'filter' => $val]) }}" 
           class="filter-btn {{ $filter === $val ? 'active' : '' }}">{{ $label }}</a>
      @endforeach
    </div>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead>
        <tr>
          <th>No</th><th>Mahasiswa</th><th>Kompi</th><th>Jam Masuk</th><th>Jam Keluar</th><th>Durasi</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse($attendances as $i => $att)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>
            <div class="mahasiswa-cell">
              <div class="avatar" style="background:var(--primary-light);color:var(--primary)">{{ strtoupper(substr($att->name, 0, 2)) }}</div>
              <div class="mhs-name">{{ $att->name }}</div>
            </div>
          </td>
          <td><span class="badge badge-blue">{{ $att->kompi }}</span></td>
          <td><span class="time-val">{{ $att->check_in ? Carbon\Carbon::parse($att->check_in)->format('H:i:s') : '-' }}</span></td>
          <td><span class="time-val">{{ $att->check_out ? Carbon\Carbon::parse($att->check_out)->format('H:i:s') : '-' }}</span></td>
          <td>
            @if($att->check_in && $att->check_out)
              @php
                $diff = Carbon\Carbon::parse($att->check_in)->diff(Carbon\Carbon::parse($att->check_out));
              @endphp
              <span class="time-val">{{ $diff->h }}j {{ $diff->i }}m</span>
            @else
              <span class="time-dash">-</span>
            @endif
          </td>
          <td>
            @php
              $statusClass = match($att->status) {
                  'present', 'hadir' => 'badge-green',
                  'izin' => 'badge-blue',
                  'sakit' => 'badge-yellow',
                  default => 'badge-red'
              };
            @endphp
            <span class="badge {{ $statusClass }}">{{ strtoupper($att->status) }}</span>
          </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada data absensi</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
