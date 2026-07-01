@extends('layouts.admin')
@section('title', 'Laporan Keterlambatan — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Laporan Keterlambatan</div>
      <div class="page-sub">Analisis data keterlambatan mahasiswa</div>
    </div>
    <div>
      <a href="{{ route('admin.late-report.export', request()->all()) }}" class="btn btn-success">
        <i class="fas fa-file-excel"></i> Export CSV
      </a>
    </div>
  </div>

  {{-- Summary Statistics --}}
  <div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dc3545">
        <i class="fas fa-clock"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value">{{ $totalLateOccurrences }}</div>
        <div class="stat-label">Total Keterlambatan</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background:#28a745">
        <i class="fas fa-check-circle"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value">{{ $totalOverrides }}</div>
        <div class="stat-label">Total Override</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background:#ffc107">
        <i class="fas fa-hourglass-half"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value">{{ $avgLateDuration ? round($avgLateDuration, 1) : 0 }} min</div>
        <div class="stat-label">Rata-rata Durasi Telat</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background:#17a2b8">
        <i class="fas fa-percentage"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value">{{ $lateReports->total() }}</div>
        <div class="stat-label">Mahasiswa Terlambat</div>
      </div>
    </div>
  </div>

  {{-- Filters --}}
  <div class="panel" style="margin-bottom:24px">
    <form method="GET" action="{{ route('admin.late-report') }}" class="filter-form">
      <div class="form-row-grid">
        <div class="form-group">
          <label class="form-label">Tanggal Mulai</label>
          <input type="date" name="start" class="form-input" value="{{ $start }}" required>
        </div>

        <div class="form-group">
          <label class="form-label">Tanggal Akhir</label>
          <input type="date" name="end" class="form-input" value="{{ $end }}" required>
        </div>

        <div class="form-group">
          <label class="form-label">Kompi</label>
          <select name="kompi" class="form-input">
            <option value="">Semua Kompi</option>
            @foreach($kompiOptions as $kompi)
            <option value="{{ $kompi }}" {{ $filterKompi == $kompi ? 'selected' : '' }}>{{ $kompi }}</option>
            @endforeach
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Jurusan</label>
          <select name="jurusan" class="form-input">
            <option value="">Semua Jurusan</option>
            @foreach($jurusanOptions as $jurusan)
            <option value="{{ $jurusan }}" {{ $filterJurusan == $jurusan ? 'selected' : '' }}>{{ $jurusan }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div style="margin-top:16px;display:flex;gap:12px">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-search"></i> Filter
        </button>
        <a href="{{ route('admin.late-report') }}" class="btn btn-secondary">
          <i class="fas fa-redo"></i> Reset
        </a>
      </div>
    </form>
  </div>

  {{-- Report Table --}}
  <div class="panel">
    <div class="section-header">
      <div class="section-title">Laporan Keterlambatan per Mahasiswa</div>
      <div class="section-sub">Periode: {{ \Carbon\Carbon::parse($start)->format('d M Y') }} - {{ \Carbon\Carbon::parse($end)->format('d M Y') }}</div>
    </div>

    @if($lateReports->isEmpty())
    <div style="text-align:center;padding:40px;color:var(--text-muted)">
      <i class="fas fa-inbox" style="font-size:48px;margin-bottom:16px;opacity:0.3"></i>
      <p>Tidak ada data keterlambatan untuk periode dan filter yang dipilih.</p>
    </div>
    @else
    <div style="overflow-x:auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width:50px">No</th>
            <th>ID Mahasiswa</th>
            <th>Nama</th>
            <th>Kompi</th>
            <th>Jurusan</th>
            <th style="text-align:center">Total Telat</th>
            <th style="text-align:center">Rata-rata Durasi</th>
            <th style="text-align:center">Durasi Min</th>
            <th style="text-align:center">Durasi Max</th>
          </tr>
        </thead>
        <tbody>
          @foreach($lateReports as $index => $report)
          <tr>
            <td style="text-align:center">{{ $lateReports->firstItem() + $index }}</td>
            <td><strong>{{ $report->mahasiswa_id }}</strong></td>
            <td>{{ $report->name }}</td>
            <td><span class="badge badge-kompi">{{ $report->kompi }}</span></td>
            <td>{{ $report->jurusan }}</td>
            <td style="text-align:center">
              <span class="badge badge-danger">{{ $report->total_late }}x</span>
            </td>
            <td style="text-align:center">
              <strong>{{ round($report->avg_late_duration, 1) }}</strong> menit
            </td>
            <td style="text-align:center">
              {{ $report->min_late_duration }} menit
            </td>
            <td style="text-align:center">
              {{ $report->max_late_duration }} menit
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    <div style="margin-top:24px">
      {{ $lateReports->links() }}
    </div>
    @endif
  </div>
</section>

<style>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 24px;
}

.stat-content {
  flex: 1;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1;
  margin-bottom: 4px;
}

.stat-label {
  font-size: 14px;
  color: var(--text-muted);
}

.form-row-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.badge-kompi {
  background: #007bff;
  color: white;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.badge-danger {
  background: #dc3545;
  color: white;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.admin-table {
  width: 100%;
  border-collapse: collapse;
}

.admin-table th {
  background: #f8f9fa;
  padding: 12px;
  font-weight: 600;
  text-align: left;
  border-bottom: 2px solid #dee2e6;
}

.admin-table td {
  padding: 12px;
  border-bottom: 1px solid #dee2e6;
  vertical-align: middle;
}

.admin-table tbody tr:hover {
  background: #f8f9fa;
}
</style>
@endsection
