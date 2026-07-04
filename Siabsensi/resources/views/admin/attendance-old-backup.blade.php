@extends('layouts.admin')
@section('title', 'Monitor Live — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title" style="display:flex; align-items:center; gap:8px;">
        Monitor Live Absensi
        <span style="display:inline-block; width:10px; height:10px; background-color:var(--danger); border-radius:50%; animation: pulse 1.5s infinite;" title="Live Detection Active"></span>
      </div>
      <div class="page-sub">{{ Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }} — Auto-refresh setiap 30 detik</div>
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
    <form method="GET" action="{{ route('admin.attendance') }}" id="filterForm">
      <input type="hidden" name="date" value="{{ $date }}">
      
      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px">
        {{-- Search Input --}}
        <div style="flex:1;min-width:200px">
          <label class="form-label">Cari Nama Mahasiswa</label>
          <input type="text" name="search" class="form-input" placeholder="Nama mahasiswa..." value="{{ $search }}" style="padding:7px 10px;width:100%">
        </div>
        
        {{-- Kompi Filter --}}
        <div style="min-width:150px">
          <label class="form-label">Kompi</label>
          <select name="kompi" class="form-input" style="padding:7px 10px;width:100%">
            <option value="">Semua Kompi</option>
            @foreach($kompiOptions as $k)
            <option value="{{ $k }}" {{ $kompi === $k ? 'selected' : '' }}>{{ $k }}</option>
            @endforeach
          </select>
        </div>
        
        {{-- Jurusan Filter --}}
        <div style="min-width:180px">
          <label class="form-label">Jurusan</label>
          <select name="jurusan" class="form-input" style="padding:7px 10px;width:100%">
            <option value="">Semua Jurusan</option>
            @foreach($jurusanOptions as $j)
            <option value="{{ $j }}" {{ $jurusan === $j ? 'selected' : '' }}>{{ $j }}</option>
            @endforeach
          </select>
        </div>
        
        {{-- Submit Button --}}
        <div style="display:flex;align-items:flex-end">
          <button type="submit" class="btn btn-primary btn-sm">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">search</span> Cari
          </button>
        </div>
        
        {{-- Reset Button --}}
        @if($search || $kompi || $jurusan || $filter !== 'all')
        <div style="display:flex;align-items:flex-end">
          <a href="{{ route('admin.attendance', ['date' => $date]) }}" class="btn btn-secondary btn-sm">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span> Reset
          </a>
        </div>
        @endif
      </div>
      
      {{-- Status Filter --}}
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding-top:8px;border-top:1px solid var(--border-color)">
        <span class="form-label" style="margin-bottom:0">Filter Status:</span>
        @foreach(['all' => 'Semua', 'hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpha' => 'Alpha'] as $val => $label)
          <input type="radio" name="filter" value="{{ $val }}" id="filter-{{ $val }}" {{ $filter === $val ? 'checked' : '' }} style="display:none">
          <label for="filter-{{ $val }}" class="filter-btn {{ $filter === $val ? 'active' : '' }}" onclick="document.getElementById('filter-{{ $val }}').checked = true; document.getElementById('filterForm').submit();">
            {{ $label }}
          </label>
        @endforeach
      </div>
    </form>
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
  <div style="margin-top: 16px;">
    {{ $attendances->links('pagination::bootstrap-4') }}
  </div>
</section>

<style>
@keyframes pulse {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

.filter-btn {
  padding: 6px 16px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  background: white;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;
  text-decoration: none;
  display: inline-block;
}

.filter-btn:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: var(--primary-light);
}

.filter-btn.active {
  background: var(--primary);
  color: white;
  border-color: var(--primary);
  font-weight: 500;
}

.form-label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: var(--text-secondary);
  margin-bottom: 6px;
}
</style>

<script>
  // Auto-refresh the page every 30 seconds to show live updates from YOLO
  setTimeout(function(){
      window.location.reload();
  }, 30000);
</script>
@endsection
