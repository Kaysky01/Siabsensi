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
      <a href="{{ route('admin.kegiatan') }}" class="btn btn-ghost btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">arrow_back</span> Kembali
      </a>
      @if(auth()->user()->role === 'garda' || auth()->user()->role === 'admin' || auth()->user()->role === 'timdis')
      <a href="{{ route('admin.absensi-manual.index', $sesi->id) }}" class="btn btn-primary btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">edit</span> Edit Absensi
      </a>
      @endif
    </div>
  </div>

  {{-- Statistics Card --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px">
    <div class="stat-card" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%)">
      <div class="stat-value">{{ $totalMahasiswa }}</div>
      <div class="stat-label">Total Mahasiswa</div>
    </div>
    <div class="stat-card" style="background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%)">
      <div class="stat-value">{{ $totalHadir }}</div>
      <div class="stat-label">Hadir</div>
    </div>
    <div class="stat-card" style="background:linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)">
      <div class="stat-value">{{ $totalMahasiswa - $totalHadir }}</div>
      <div class="stat-label">Tidak Hadir</div>
    </div>
    <div class="stat-card" style="background:linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)">
      <div class="stat-value">{{ $totalMahasiswa > 0 ? round(($totalHadir / $totalMahasiswa) * 100, 1) : 0 }}%</div>
      <div class="stat-label">Persentase Kehadiran</div>
    </div>
  </div>

  {{-- Filter & Search --}}
  <div class="panel" style="margin-bottom:16px">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <input type="text" id="search-box" class="form-input" placeholder="🔍 Cari mahasiswa..." style="flex:1;min-width:200px" onkeyup="filterTable()">
      
      <select id="filter-status" class="form-input" style="width:150px" onchange="filterTable()">
        <option value="">Semua Status</option>
        <option value="hadir">Hadir</option>
        <option value="belum">Belum Hadir</option>
      </select>
      
      @if(auth()->user()->role !== 'garda')
      <select id="filter-kompi" class="form-input" style="width:150px" onchange="filterTable()">
        <option value="">Semua Kompi</option>
        @foreach($mahasiswaList->pluck('kompi')->unique()->sort() as $kompi)
        <option value="{{ $kompi }}">{{ $kompi }}</option>
        @endforeach
      </select>
      @endif
      
      <button class="btn btn-ghost btn-sm" onclick="resetFilters()">
        <span class="material-symbols-outlined" style="font-size:16px">refresh</span> Reset
      </button>
    </div>
  </div>

  {{-- Table --}}
  <div class="panel">
    <div style="overflow-x:auto">
      <table class="att-table" id="monitoring-table">
        <thead>
          <tr>
            <th>No</th>
            <th>Nama</th>
            <th>Kompi</th>
            <th>Prodi</th>
            <th>Status</th>
            <th>Diabsen Oleh</th>
            <th>Waktu Absensi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($mahasiswaList as $index => $mhs)
          <tr class="table-row" 
              data-name="{{ strtolower($mhs->name) }}" 
              data-kompi="{{ strtolower($mhs->kompi) }}"
              data-prodi="{{ strtolower($mhs->prodi) }}"
              data-status="{{ isset($attendances[$mhs->id]) ? 'hadir' : 'belum' }}">
            <td>{{ $index + 1 }}</td>
            <td><strong>{{ $mhs->name }}</strong></td>
            <td>{{ $mhs->kompi }}</td>
            <td>{{ $mhs->prodi }}</td>
            <td>
              @if(isset($attendances[$mhs->id]))
              <span class="badge badge-green">✓ Hadir</span>
              @else
              <span class="badge badge-gray">− Belum Hadir</span>
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
          @empty
          <tr>
            <td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px">
              Tidak ada data mahasiswa
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border-color);text-align:center;color:var(--text-muted);font-size:14px">
      Menampilkan <strong id="visible-count">{{ $mahasiswaList->count() }}</strong> dari <strong>{{ $mahasiswaList->count() }}</strong> mahasiswa
    </div>
  </div>
</section>

<script>
function filterTable() {
  const searchTerm = document.getElementById('search-box').value.toLowerCase();
  const statusFilter = document.getElementById('filter-status').value;
  const kompiFilter = document.getElementById('filter-kompi') ? document.getElementById('filter-kompi').value.toLowerCase() : '';
  
  const rows = document.querySelectorAll('.table-row');
  let visibleCount = 0;
  
  rows.forEach(row => {
    const name = row.dataset.name;
    const kompi = row.dataset.kompi;
    const prodi = row.dataset.prodi;
    const status = row.dataset.status;
    
    const matchSearch = name.includes(searchTerm) || kompi.includes(searchTerm) || prodi.includes(searchTerm);
    const matchStatus = !statusFilter || status === statusFilter;
    const matchKompi = !kompiFilter || kompi === kompiFilter;
    
    if (matchSearch && matchStatus && matchKompi) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });
  
  document.getElementById('visible-count').textContent = visibleCount;
}

function resetFilters() {
  document.getElementById('search-box').value = '';
  document.getElementById('filter-status').value = '';
  if (document.getElementById('filter-kompi')) {
    document.getElementById('filter-kompi').value = '';
  }
  filterTable();
}
</script>

<style>
.stat-card {
  padding: 24px;
  border-radius: 12px;
  color: white;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  margin-bottom: 8px;
}

.stat-label {
  font-size: 14px;
  opacity: 0.9;
}

.table-row {
  transition: background-color 0.2s;
}

.table-row:hover {
  background-color: #f8f9fa;
}

.att-table thead th {
  background: var(--bg-primary);
  font-weight: 600;
  padding: 12px;
  border-bottom: 2px solid var(--border-color);
  text-align: left;
}

.att-table tbody td {
  padding: 12px;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}
</style>
@endsection
