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
      <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('modal-export-excel').classList.add('show')">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Export Excel
      </button>
    </div>
  </div>

  {{-- Filter Panel --}}
  <div class="panel filter-panel" style="margin-bottom:16px;padding:20px">
    <form method="GET" action="{{ route('admin.attendance') }}" id="filterForm">
      
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
          <a href="{{ route('admin.attendance') }}" class="btn btn-secondary" style="height:38px;display:flex;align-items:center;gap:6px">
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
                $photoPath = is_string($att->photo_path) ? trim($att->photo_path) : null;
                $photoUrl = null;
                if ($photoPath) {
                  if (str_starts_with($photoPath, 'http://') || str_starts_with($photoPath, 'https://')) {
                    $photoUrl = $photoPath;
                  } else {
                    $cleanPath = ltrim($photoPath, '/');
                    if (str_starts_with($cleanPath, 'storage/')) $cleanPath = substr($cleanPath, 8);
                    elseif (str_starts_with($cleanPath, 'public/')) $cleanPath = substr($cleanPath, 7);
                    $photoUrl = url('/file-bukti/' . $cleanPath);
                  }
                }
              @endphp
              @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $att->name }}" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid #3b82f6;flex-shrink:0;">
              @else
                <div class="avatar" style="background:var(--primary-light);color:var(--primary);width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">{{ strtoupper(substr($att->name, 0, 2)) }}</div>
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

<!-- Modal Export Excel Monitoring Absensi -->
<div class="modal-backdrop" id="modal-export-excel">
  <div class="modal" style="max-width: 780px; max-height: 90vh; overflow-y: auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border);">
      <div class="modal-title" style="margin:0; font-size:16px; font-weight:700;">Export Excel Monitoring Absensi</div>
      <button type="button" class="btn btn-ghost btn-sm" style="padding:4px 8px; border-radius:50%; font-size:18px;" onclick="document.getElementById('modal-export-excel').classList.remove('show')">&times;</button>
    </div>

    <form method="GET" action="{{ route('admin.attendance.export') }}" target="_blank">
      <div style="background: rgba(37, 99, 235, 0.08); border: 1px solid rgba(37, 99, 235, 0.2); border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 12px; color: #1e40af; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-file-excel" style="font-size: 18px; color: #2563eb; flex-shrink:0;"></i>
        <span>Pilih opsi jalur di bawah (ceklis). Setiap jalur yang dicentang akan otomatis dibuatkan <b>Sheet tersendiri</b> di dalam file Excel.</span>
      </div>

      {{-- Section 1: Filter --}}
      <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border);">
        <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: var(--text-muted);">1. Filter Data Absensi</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
          <div>
            <label class="form-label" style="font-size:12px;">Dari Tanggal</label>
            <input type="date" name="start" class="form-input" value="{{ $start }}" required style="padding:7px 10px;">
          </div>
          <div>
            <label class="form-label" style="font-size:12px;">Sampai Tanggal</label>
            <input type="date" name="end" class="form-input" value="{{ $end }}" required style="padding:7px 10px;">
          </div>
          <div style="grid-column: 1 / -1; margin-top: 4px;">
            <label class="form-label" style="font-size:12px; font-weight:600; margin-bottom:6px; display:block;">Status Absensi (Ceklis Status yang Diinginkan)</label>
            <div style="display: flex; flex-wrap: wrap; gap: 14px; background: var(--bg-lighter); padding: 10px 14px; border: 1px solid var(--border); border-radius: 6px;">
              <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; cursor:pointer; user-select:none; color:var(--text);">
                <input type="checkbox" name="statuses[]" value="hadir" {{ ($filter === 'all' || in_array($filter, ['hadir','present'])) ? 'checked' : '' }} style="accent-color: var(--primary);">
                <span>Hadir</span>
              </label>
              <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; cursor:pointer; user-select:none; color:var(--text);">
                <input type="checkbox" name="statuses[]" value="izin" {{ ($filter === 'all' || $filter === 'izin') ? 'checked' : '' }} style="accent-color: var(--primary);">
                <span>Izin</span>
              </label>
              <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; cursor:pointer; user-select:none; color:var(--text);">
                <input type="checkbox" name="statuses[]" value="sakit" {{ ($filter === 'all' || $filter === 'sakit') ? 'checked' : '' }} style="accent-color: var(--primary);">
                <span>Sakit</span>
              </label>
              <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; cursor:pointer; user-select:none; color:var(--text);">
                <input type="checkbox" name="statuses[]" value="alpha" {{ ($filter === 'all' || $filter === 'alpha') ? 'checked' : '' }} style="accent-color: var(--primary);">
                <span>Alpha (Belum Absen)</span>
              </label>
            </div>
          </div>
          <div>
            <label class="form-label" style="font-size:12px;">Kompi</label>
            <select name="kompi" class="form-input" style="padding:7px 10px;">
              <option value="all">Semua Kompi</option>
              @foreach($kompiOptions as $k)
                <option value="{{ $k }}" {{ $kompi == $k ? 'selected' : '' }}>{{ $k }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label" style="font-size:12px;">Jurusan</label>
            <select name="jurusan" class="form-input" style="padding:7px 10px;">
              <option value="">Semua Jurusan</option>
              @foreach($jurusanOptions as $j)
                <option value="{{ $j }}" {{ $jurusan == $j ? 'selected' : '' }}>{{ $j }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label" style="font-size:12px;">Prodi</label>
            <select name="prodi" class="form-input" style="padding:7px 10px;">
              <option value="">Semua Prodi</option>
              @foreach($prodiOptions as $p)
                <option value="{{ $p }}">{{ $p }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      {{-- Section 2: Choice of Jalur / Sheet --}}
      <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border);">
        <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: var(--text-muted);">2. Pilih Jalur / Kategori Sheet (Ceklis yang Diinginkan)</h4>
        <div style="display: flex; flex-wrap: wrap; gap: 16px; background: var(--bg-lighter); padding: 14px 16px; border: 1px solid var(--border); border-radius: 8px;">
          <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight: 600; cursor:pointer; user-select:none; color: var(--text);">
            <input type="checkbox" name="sheets[]" value="mandiri" checked style="width:16px; height:16px; accent-color: var(--primary);">
            <span>Jalur Mandiri (Sheet 1)</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight: 600; cursor:pointer; user-select:none; color: var(--text);">
            <input type="checkbox" name="sheets[]" value="reguler" checked style="width:16px; height:16px; accent-color: var(--primary);">
            <span>Jalur Reguler (Sheet 2)</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight: 600; cursor:pointer; user-select:none; color: var(--text);">
            <input type="checkbox" name="sheets[]" value="kompi_14" checked style="width:16px; height:16px; accent-color: var(--primary);">
            <span>Kompi 14 / Mahasiswa Ngulang (Sheet 3)</span>
          </label>
        </div>
      </div>

      {{-- Section 3: Field Selection --}}
      <div style="margin-bottom: 24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
          <h4 style="margin:0; font-size:14px; font-weight:600; color:var(--text-muted);">3. Pilih Kolom Data yang Ditampilkan</h4>
          <label style="display:flex; align-items:center; gap:6px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" id="export-check-all" checked onchange="toggleAllExportFields(this)">
            <span style="font-weight:600; color:var(--primary);">Pilih Semua Kolom</span>
          </label>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; background:var(--bg-lighter); padding:16px; border:1px solid var(--border); border-radius:8px;">
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb" name="export_fields[]" value="id" checked>
            <span>ID / No. Pendaftaran</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb" name="export_fields[]" value="name" checked>
            <span>Nama Mahasiswa</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb" name="export_fields[]" value="email" checked>
            <span>Email Mahasiswa</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb" name="export_fields[]" value="kompi" checked>
            <span>Kompi</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb" name="export_fields[]" value="jurusan" checked>
            <span>Jurusan</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb" name="export_fields[]" value="prodi" checked>
            <span>Prodi</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb" name="export_fields[]" value="date" checked>
            <span>Tanggal Absensi</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb" name="export_fields[]" value="check_in" checked>
            <span>Waktu Masuk</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb" name="export_fields[]" value="check_out" checked>
            <span>Waktu Keluar</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb" name="export_fields[]" value="status" checked>
            <span>Status Absensi</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb" name="export_fields[]" value="camera_id" checked>
            <span>Kamera / Device ID</span>
          </label>
        </div>
      </div>

      <div class="modal-actions" style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-export-excel').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-success" onclick="setTimeout(() => document.getElementById('modal-export-excel').classList.remove('show'), 500)">
          <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">download</span> Download Excel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleAllExportFields(source) {
  const checkboxes = document.querySelectorAll('.export-field-cb');
  checkboxes.forEach(cb => cb.checked = source.checked);
}

// Auto-refresh only when showing today's date as single day
@if($start === $end && $start === \Carbon\Carbon::today()->toDateString())
setTimeout(function(){
    window.location.reload();
}, 30000);
@endif
</script>
@endsection
