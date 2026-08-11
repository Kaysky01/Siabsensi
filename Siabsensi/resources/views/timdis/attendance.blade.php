@extends('layouts.admin')
@section('title', 'Monitor Live Absensi — SIABSEN')

@section('content')
{{-- Font Awesome for Icons --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<section>
  <div class="page-header">
    <div>
      <div class="page-title" style="display:flex; align-items:center; gap:8px;">
        Monitor Live Absensi
        @if($start === $end && $start === \Carbon\Carbon::today()->toDateString())
        <span style="display:inline-block; width:10px; height:10px; background-color:var(--danger); border-radius:50%; animation: pulse 1.5s infinite;" title="Live Detection Active"></span>
        @endif
      </div>
      <div class="page-sub">
        {{ \Carbon\Carbon::parse($start)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($end)->format('d/m/Y') }}
        @if($start === $end && $start === \Carbon\Carbon::today()->toDateString())
          • Live monitoring hari ini
        @else
          • Menampilkan rekap periode
        @endif
      </div>
    </div>
    <div class="header-actions">
      <a href="{{ route('admin.attendance.export', ['start' => $start, 'end' => $end]) }}" class="btn btn-ghost btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Export
      </a>
    </div>
  </div>

  {{-- Filter Panel --}}
  <div class="panel filter-panel" style="margin-bottom:16px;padding:20px">
    <form method="GET" action="{{ route('timdis.attendance') }}" id="filterForm">
      
      {{-- Date Inputs & Filters --}}
      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;align-items:flex-end">
        {{-- Date Range --}}
        <div class="input-group">
          <label class="form-label">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px">calendar_month</span>
            Dari Tanggal
          </label>
          <input type="date" name="start" class="form-input form-input-modern" value="{{ $start }}" style="width:150px" required>
        </div>
        <div class="input-group">
          <label class="form-label">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px">calendar_month</span>
            Sampai Tanggal
          </label>
          <input type="date" name="end" class="form-input form-input-modern" value="{{ $end }}" style="width:150px" required>
        </div>
        
        {{-- Search Input --}}
        <div class="input-group" style="flex:1;min-width:200px">
          <label class="form-label">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px">search</span>
            Cari Nama Mahasiswa
          </label>
          <input type="text" name="search" class="form-input form-input-modern" placeholder="Ketik nama mahasiswa..." value="{{ $search ?? '' }}" style="width:100%">
        </div>
        
        {{-- Kompi Filter --}}
        <div class="input-group" style="min-width:140px">
          <label class="form-label">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px">group</span>
            Kompi
          </label>
          <select name="kompi" class="form-input form-input-modern" style="width:100%">
            <option value="">Semua Kompi</option>
            @foreach($kompiOptions ?? [] as $k)
            <option value="{{ $k }}" {{ ($kompi ?? '') === $k ? 'selected' : '' }}>{{ $k }}</option>
            @endforeach
          </select>
        </div>
        
        {{-- Jurusan Filter --}}
        <div class="input-group" style="min-width:180px">
          <label class="form-label">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px">school</span>
            Jurusan
          </label>
          <select name="jurusan" class="form-input form-input-modern" style="width:100%">
            <option value="">Semua Jurusan</option>
            @foreach($jurusanOptions ?? [] as $j)
            <option value="{{ $j }}" {{ ($jurusan ?? '') === $j ? 'selected' : '' }}>{{ $j }}</option>
            @endforeach
          </select>
        </div>
        
        {{-- Submit Button --}}
        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary" style="height:38px;display:flex;align-items:center;gap:6px">
            <span class="material-symbols-outlined" style="font-size:18px">search</span>
            <span>Cari</span>
          </button>
          
          {{-- Reset Button --}}
          @if(($search ?? '') || ($kompi ?? '') || ($jurusan ?? '') || ($filter ?? 'all') !== 'all')
          <a href="{{ route('timdis.attendance') }}" class="btn btn-secondary" style="height:38px;display:flex;align-items:center;gap:6px">
            <span class="material-symbols-outlined" style="font-size:18px">refresh</span>
            <span>Reset</span>
          </a>
          @endif
        </div>
      </div>
      
      {{-- Status Filter --}}
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding-top:16px;border-top:1px solid #e5e7eb">
        <span style="font-size:14px;font-weight:600;color:var(--text-primary);margin-right:4px">Filter Status:</span>
        @php
          $statusConfig = [
            'all' => ['label' => 'Semua', 'icon' => 'filter_list'],
            'hadir' => ['label' => 'Hadir', 'icon' => 'check_circle'],
            'izin' => ['label' => 'Izin', 'icon' => 'description'],
            'sakit' => ['label' => 'Sakit', 'icon' => 'medical_services'],
            'alpha' => ['label' => 'Alpha', 'icon' => 'cancel']
          ];
        @endphp
        @foreach($statusConfig as $val => $config)
          <input type="radio" name="filter" value="{{ $val }}" id="filter-{{ $val }}" {{ ($filter ?? 'all') === $val ? 'checked' : '' }} style="display:none">
          <label for="filter-{{ $val }}" class="filter-btn {{ ($filter ?? 'all') === $val ? 'active' : '' }}" onclick="document.getElementById('filter-{{ $val }}').checked = true; document.getElementById('filterForm').submit();">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px">{{ $config['icon'] }}</span>
            {{ $config['label'] }}
          </label>
        @endforeach
      </div>
    </form>
  </div>

  {{-- Table --}}
  <div class="panel">
    <table class="att-table">
      <thead>
        <tr>
          <th>No</th>
          <th>Mahasiswa</th>
          <th>Kompi</th>
          <th>Tanggal</th>
          <th>Masuk</th>
          <th>Keluar</th>
          <th>Status</th>
          <th>Keterlambatan</th>
        </tr>
      </thead>
      <tbody>
        @forelse($attendances as $i => $att)
        <tr>
          <td>{{ $attendances->firstItem() + $i }}</td>
          <td>
            <div class="mahasiswa-cell">
              @php
                $photoUrl = null;
                if (!empty($att->photo_path)) {
                  $path = $att->photo_path;
                  if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                    $photoUrl = $path;
                  } else {
                    $cleanPath = ltrim(str_replace(['public/', 'storage/'], '', $path), '/');
                    $photoUrl = url('/file-bukti/' . $cleanPath);
                  }
                }
              @endphp
              @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $att->name }}" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid #3b82f6;flex-shrink:0;">
              @else
                <div class="avatar" style="background:var(--primary-light);color:var(--primary)">{{ strtoupper(substr($att->name, 0, 2)) }}</div>
              @endif
              <div class="mhs-name">{{ $att->name }}</div>
            </div>
          </td>
          <td><span class="badge badge-blue">{{ $att->kompi }}</span></td>
          <td style="font-size:13px">{{ $att->date ?? '-' }}</td>
          <td><span class="time-val">{{ $att->check_in ? \Carbon\Carbon::parse($att->check_in)->format('H:i') : '-' }}</span></td>
          <td><span class="time-val">{{ $att->check_out ? \Carbon\Carbon::parse($att->check_out)->format('H:i') : '-' }}</span></td>
          <td>
            @php 
              $badge = $att->getStatusBadgeData();
            @endphp
            <span class="badge" style="padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px;background:{{ $badge['bg'] }};color:{{ $badge['color'] }};border:1px solid {{ $badge['border'] }};white-space:nowrap">
              <span style="width:7px;height:7px;border-radius:50%;background:{{ $badge['dot'] }};display:inline-block"></span>
              {{ $badge['label'] }}
            </span>
          </td>
          <td>
            @if(in_array($att->status ?? 'alpha', ['alpha', 'izin', 'sakit']))
              {{-- Tidak hadir, izin, atau sakit = tidak ada data keterlambatan --}}
              <span style="color:var(--text-muted);font-size:14px">-</span>
            @elseif($att->kegiatan_id ?? null)
              <span class="badge badge-gray" title="Validasi waktu tidak berlaku untuk kegiatan">
                <i class="fas fa-minus"></i> N/A (Kegiatan)
              </span>
            @elseif($att->is_late ?? false)
              @if($att->late_overridden ?? false)
                <span class="badge badge-gray" title="Telat {{ $att->late_duration ?? 0 }} menit (Di-override oleh {{ $att->overridden_by ?? '-' }})">
                  <i class="fas fa-check-circle"></i> Override
                </span>
              @else
                <span class="badge badge-danger">
                  <i class="fas fa-clock"></i> TELAT {{ $att->late_duration ?? 0 }} menit
                </span>
              @endif
            @elseif($att->check_in)
              {{-- Ada check_in dan tidak telat = tepat waktu --}}
              <span class="badge badge-success">
                <i class="fas fa-check-circle"></i> Tepat Waktu
              </span>
            @else
              {{-- No check_in data --}}
              <span style="color:var(--text-muted);font-size:14px">-</span>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada data absensi</td></tr>
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
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 6px;
  display: flex;
  align-items: center;
}

.input-group {
  display: flex;
  flex-direction: column;
}

.form-input-modern {
  padding: 8px 12px;
  border: 1.5px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  transition: all 0.2s;
  background: white;
}

.form-input-modern:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input-modern:hover {
  border-color: #9ca3af;
}

.filter-panel {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
}

.btn {
  font-weight: 500;
  border-radius: 6px;
  transition: all 0.2s;
}

.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.badge-purple {
  background: #9c27b0;
  color: white;
}

.badge-gray {
  background: #6c757d;
  color: white;
}

.badge-success {
  background: #28a745;
  color: white;
}

.badge-danger {
  background: #dc3545;
  color: white;
}
</style>

<script>
// Auto-refresh only when showing today's date as single day
@if($start === $end && $start === \Carbon\Carbon::today()->toDateString())
setTimeout(function(){
    window.location.reload();
}, 30000);
@endif
</script>
@endsection

