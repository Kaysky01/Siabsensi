@extends('layouts.admin')
@section('title', 'Monitoring Sesi — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Monitoring: {{ $sesi->nama_sesi }}</div>
      <div class="page-sub">
        @if($sesi->pkkmbSchedule)
        PKKMB Hari ke-{{ $sesi->pkkmbSchedule->hari_ke }} - {{ $sesi->pkkmbSchedule->formatted_date }}
        @elseif($sesi->kegiatan)
        {{ $sesi->kegiatan->nama }} - {{ $sesi->kegiatan->tanggal_pelaksanaan->format('d M Y') }}
        @else
        {{ $sesi->tanggal ? $sesi->tanggal->format('d M Y') : 'Tanggal tidak diketahui' }}
        @endif
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <a href="{{ route('admin.monitoring-kegiatan') }}" class="btn btn-ghost btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">arrow_back</span> Kembali
      </a>
      @if(auth()->user()->role === 'garda' || auth()->user()->role === 'admin' || auth()->user()->role === 'timdis')
      <a href="{{ route('admin.absensi-manual.index', $sesi->id) }}" class="btn btn-primary btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">edit</span> Edit Absensi
      </a>
      @endif
    </div>
  </div>

  {{-- Statistics Cards --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px">
    <div class="info-card">
      <div class="info-icon" style="background:var(--primary-light);color:var(--primary)">
        <span class="material-symbols-outlined">group</span>
      </div>
      <div class="info-content">
        <div class="info-value">{{ $totalMahasiswa }}</div>
        <div class="info-label">Total Mahasiswa</div>
      </div>
    </div>
    <div class="info-card">
      <div class="info-icon" style="background:#dbeafe;color:#1d4ed8">
        <span class="material-symbols-outlined">login</span>
      </div>
      <div class="info-content">
        <div class="info-value">{{ $eligibleTotal }}</div>
        <div class="info-label">Sudah Absen Masuk</div>
      </div>
    </div>
    <div class="info-card">
      <div class="info-icon" style="background:var(--success-light);color:var(--success)">
        <span class="material-symbols-outlined">check_circle</span>
      </div>
      <div class="info-content">
        <div class="info-value">{{ $totalHadir }}</div>
        <div class="info-label">Hadir Sesi</div>
      </div>
    </div>
    <div class="info-card">
      <div class="info-icon" style="background:var(--warning-light);color:#D4A017">
        <span class="material-symbols-outlined">percent</span>
      </div>
      <div class="info-content">
        <div class="info-value">{{ $eligibleTotal > 0 ? round(($totalHadir / $eligibleTotal) * 100, 1) : 0 }}%</div>
        <div class="info-label">Kehadiran Sesi</div>
      </div>
    </div>
  </div>

  {{-- Filter & Search --}}
  <div class="panel" style="margin-bottom:16px">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <form method="GET" action="{{ route('admin.monitoring-sesi', $sesi->id) }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;width:100%">
      <input type="text" name="search" class="form-input" placeholder="Cari mahasiswa..." style="flex:1;min-width:200px" value="{{ $search }}">
      
      <select name="status" class="form-input" style="width:170px">
        <option value="">Semua Status</option>
        <option value="hadir" {{ $status === 'hadir' ? 'selected' : '' }}>Hadir</option>
        <option value="belum_sesi" {{ $status === 'belum_sesi' ? 'selected' : '' }}>Belum Hadir Sesi</option>
        <option value="belum_masuk" {{ $status === 'belum_masuk' ? 'selected' : '' }}>Belum Absen Masuk</option>
      </select>
      
      @if(auth()->user()->role !== 'garda')
      <select name="kompi" class="form-input" style="width:170px">
        <option value="">Semua Kompi</option>
        @foreach($kompiOptions as $kompiOption)
        <option value="{{ $kompiOption }}" {{ $kompi === $kompiOption ? 'selected' : '' }}>{{ $kompiOption }}</option>
        @endforeach
      </select>
      @endif
      
      <button type="submit" class="btn btn-primary btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px">search</span> Terapkan
      </button>
      <a href="{{ route('admin.monitoring-sesi', $sesi->id) }}" class="btn btn-ghost btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px">refresh</span> Reset
      </a>
      </form>
    </div>
  </div>

  {{-- Table --}}
  <div class="panel">
    <div style="overflow-x:auto">
      <table class="att-table" id="monitoring-table">
        <thead>
          <tr>
            <th style="width:60px">No</th>
            <th>Nama</th>
            <th style="width:100px">Kompi</th>
            <th style="width:150px">Prodi</th>
            <th style="width:120px">Status</th>
            <th style="width:120px">Diabsen Oleh</th>
            <th style="width:180px">Waktu Absensi</th>
          </tr>
        </thead>
        <tbody>
          @if(count($mahasiswaPaginated) > 0)
          @foreach($mahasiswaPaginated as $index => $mhs)
          <tr class="table-row" 
              data-name="{{ strtolower($mhs->name) }}" 
              data-kompi="{{ strtolower($mhs->kompi) }}"
              data-prodi="{{ strtolower($mhs->prodi) }}"
              data-status="{{ isset($attendances[$mhs->id]) ? 'hadir' : (isset($dailyAttendances[$mhs->id]) ? 'belum_sesi' : 'belum_masuk') }}">
            <td>{{ ($mahasiswaPaginated->currentPage() - 1) * $mahasiswaPaginated->perPage() + $index + 1 }}</td>
            <td><strong>{{ $mhs->name }}</strong></td>
            <td>{{ $mhs->kompi }}</td>
            <td>{{ $mhs->prodi }}</td>
            <td>
              @if(isset($attendances[$mhs->id]))
              <span class="badge badge-green">✓ Hadir Sesi</span>
              @elseif(!isset($dailyAttendances[$mhs->id]))
              <span class="badge badge-yellow">Belum Absen Masuk</span>
              @else
              <span class="badge badge-gray">− Belum Hadir Sesi</span>
              @endif
            </td>
            <td>
              @if(isset($attendances[$mhs->id]) && $attendances[$mhs->id]->absen_by)
              {{ $attendances[$mhs->id]->absen_by }}
              @else
              <span style="color:var(--text-muted)">−</span>
              @endif
            </td>
            <td>
              @if(isset($attendances[$mhs->id]) && $attendances[$mhs->id]->absen_at)
              {{ $attendances[$mhs->id]->absen_at->format('d M Y, H:i') }}
              @else
              <span style="color:var(--text-muted)">−</span>
              @endif
            </td>
          </tr>
          @endforeach
          @else
          <tr>
            <td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px">
              Tidak ada data mahasiswa
            </td>
          </tr>
          @endif
        </tbody>
      </table>
    </div>
    
    {{-- Pagination --}}
    @if($mahasiswaPaginated->hasPages())
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);display:flex;justify-content:center">
      {{ $mahasiswaPaginated->links('pagination::custom') }}
    </div>
    @endif
    
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);text-align:center;color:var(--text-muted);font-size:14px">
      Menampilkan <strong>{{ $mahasiswaPaginated->count() }}</strong> dari <strong>{{ $mahasiswaPaginated->total() }}</strong> mahasiswa
    </div>
  </div>
</section>

<style>
/* Info Cards */
.info-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: var(--space-md);
  display: flex;
  align-items: center;
  gap: var(--space-md);
  transition: all 0.3s ease;
}

.info-card:hover {
  border-color: var(--primary);
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.info-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.info-icon .material-symbols-outlined {
  font-size: 24px;
}

.info-content {
  flex: 1;
}

.info-value {
  font-size: 28px;
  font-weight: 700;
  font-family: var(--font-mono);
  color: var(--text);
  line-height: 1;
  margin-bottom: 4px;
}

.info-label {
  font-size: 12px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.table-row {
  transition: background-color 0.2s;
}

.table-row:hover {
  background: var(--primary-light);
}

.att-table thead th {
  background: var(--bg);
  font-weight: 600;
  padding: 12px;
  border-bottom: 2px solid var(--border);
  text-align: left;
  font-size: 12px;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.att-table tbody td {
  padding: 12px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
  font-size: 14px;
}

.att-table tbody tr:last-child td {
  border-bottom: none;
}

.badge-yellow {
  background: #fef3c7;
  color: #92400e;
}
</style>
@endsection
