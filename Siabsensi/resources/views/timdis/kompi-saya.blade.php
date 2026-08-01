@extends('layouts.admin')
@section('title', 'Kompi Saya (' . $kompi . ') — SIABSEN')

@section('content')
<style>
.ks-header-badge {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
  color: #ffffff;
  padding: 4px 14px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.ks-stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.ks-card {
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid var(--border, #e2e8f0);
  padding: 20px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.ks-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
}

.ks-pill-group {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-top: 10px;
}

.ks-pill-item {
  flex: 1;
  padding: 10px 14px;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  transition: all 0.15s ease;
}

.ks-pill-success {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
}

.ks-pill-danger {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

.ks-sesi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  margin-top: 10px;
}

.ks-sesi-box {
  padding: 8px;
  border-radius: 8px;
  text-align: center;
  font-size: 11px;
  font-weight: 600;
}

.ks-grid-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 24px;
}

@media (max-width: 992px) {
  .ks-grid-2col {
    grid-template-columns: 1fr;
  }
}

.ks-breakdown-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: 10px;
  max-height: 230px;
  overflow-y: auto;
  padding-right: 4px;
}

.ks-breakdown-container::-webkit-scrollbar { width: 5px; }
.ks-breakdown-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
.ks-breakdown-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

.ks-breakdown-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  transition: all 0.15s ease;
}

.ks-breakdown-item:hover {
  border-color: #cbd5e1;
  background: #ffffff;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.ks-announcement-panel {
  background: linear-gradient(135deg, #f8fafc 0%, #f0f7ff 100%);
  border: 1px solid #bfdbfe;
  border-left: 5px solid #2563eb;
  border-radius: 14px;
  padding: 20px 22px;
  margin-bottom: 24px;
  box-shadow: 0 4px 15px rgba(37, 99, 235, 0.05);
}

.ks-filter-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.ks-mhs-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

.ks-mhs-table thead th {
  background: #f8fafc;
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 14px 16px;
  border-bottom: 2px solid #e2e8f0;
  white-space: nowrap;
}

.ks-mhs-table tbody tr { transition: background 0.15s ease; }
.ks-mhs-table tbody tr:hover { background: #f8fafc; }

.ks-mhs-table tbody td {
  padding: 14px 16px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
</style>

<section>
  {{-- PAGE HEADER --}}
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:24px">
    <div style="display:flex;align-items:center;gap:14px">
      <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 8px 20px rgba(59,130,246,0.3)">
        <span class="material-symbols-outlined" style="font-size:30px">diversity_3</span>
      </div>
      <div>
        <div style="display:flex;align-items:center;gap:10px">
          <h1 style="font-size:22px;font-weight:800;color:#0f172a;margin:0;letter-spacing:-0.3px">Kompi Saya</h1>
          <span class="ks-header-badge">{{ $kompi }}</span>
        </div>
        <div style="font-size:13px;color:#64748b;margin-top:2px">Ringkasan kehadiran, statistik jurusan/prodi & data mahasiswa kompi {{ $kompi }}</div>
      </div>
    </div>
  </div>

  {{-- TOP STAT CARDS --}}
  <div class="ks-stat-grid">
    {{-- Card 1: Total Mahasiswa --}}
    <div class="ks-card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">Total Mahasiswa</span>
        <span class="material-symbols-outlined" style="color:#3b82f6;font-size:22px">groups</span>
      </div>
      <div style="font-size:32px;font-weight:800;color:#0f172a;margin-top:10px;line-height:1">
        {{ number_format($totalMahasiswa) }}
        <span style="font-size:13px;font-weight:600;color:#64748b">Mahasiswa</span>
      </div>
      <div style="margin-top:12px;font-size:11px;color:#94a3b8;display:flex;align-items:center;gap:4px">
        <span class="material-symbols-outlined" style="font-size:14px;color:#10b981">verified</span> Aktif di {{ $kompi }}
      </div>
    </div>

    {{-- Card 2: Absen Masuk Harian --}}
    <div class="ks-card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">Absen Masuk (Hari Ini)</span>
        <span class="material-symbols-outlined" style="color:#10b981;font-size:22px">today</span>
      </div>
      <div class="ks-pill-group">
        <div class="ks-pill-item ks-pill-success">
          <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.3px">Sudah Absen</span>
          <span style="font-size:22px;font-weight:800;line-height:1.2">{{ $sudahAbsenMasukTodayCount }}</span>
        </div>
        <div class="ks-pill-item ks-pill-danger">
          <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.3px">Belum Absen</span>
          <span style="font-size:22px;font-weight:800;line-height:1.2">{{ $belumAbsenMasukTodayCount }}</span>
        </div>
      </div>
    </div>

    {{-- Card 3: Akumulasi Absensi Sesi --}}
    <div class="ks-card" style="grid-column:span 2">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">Ringkasan Total Absensi Sesi</span>
        <span class="material-symbols-outlined" style="color:#6366f1;font-size:22px">analytics</span>
      </div>
      <div class="ks-sesi-grid">
        <div class="ks-sesi-box" style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase">Hadir</div>
          <div style="font-size:20px;font-weight:800;margin-top:2px">{{ $totalSesiHadir }}</div>
        </div>
        <div class="ks-sesi-box" style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase">Alpha</div>
          <div style="font-size:20px;font-weight:800;margin-top:2px">{{ $totalSesiAlpha }}</div>
        </div>
        <div class="ks-sesi-box" style="background:#fffbeb;border:1px solid #fde68a;color:#b45309">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase">Izin</div>
          <div style="font-size:20px;font-weight:800;margin-top:2px">{{ $totalSesiIzin }}</div>
        </div>
        <div class="ks-sesi-box" style="background:#f0f9ff;border:1px solid #bae6fd;color:#0369a1">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase">Sakit</div>
          <div style="font-size:20px;font-weight:800;margin-top:2px">{{ $totalSesiSakit }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- BREAKDOWN JURUSAN & PRODI --}}
  <div class="ks-grid-2col">
    {{-- Jurusan Box --}}
    <div class="panel" style="margin:0;border-radius:14px;box-shadow:0 4px 15px rgba(0,0,0,0.02)">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div style="font-size:15px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px">
          <span class="material-symbols-outlined" style="color:#2563eb;font-size:20px">account_balance</span>
          Daftar Jurusan di {{ $kompi }}
        </div>
        <span style="font-size:11px;font-weight:700;background:#eff6ff;color:#1e40af;padding:4px 10px;border-radius:20px">
          {{ $jurusanSummary->count() }} Jurusan
        </span>
      </div>

      <div class="ks-breakdown-container">
        @forelse($jurusanSummary as $j)
        <div class="ks-breakdown-item">
          <span style="font-size:12px;font-weight:600;color:#334155;line-height:1.3">{{ $j->jurusan }}</span>
          <span style="font-size:11px;font-weight:700;background:#dbeafe;color:#1e40af;padding:3px 8px;border-radius:6px;white-space:nowrap;flex-shrink:0">
            {{ $j->count }} Mhs
          </span>
        </div>
        @empty
        <div style="font-size:12px;color:#94a3b8;padding:12px">Belum ada data Jurusan</div>
        @endforelse
      </div>
    </div>

    {{-- Prodi Box --}}
    <div class="panel" style="margin:0;border-radius:14px;box-shadow:0 4px 15px rgba(0,0,0,0.02)">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div style="font-size:15px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px">
          <span class="material-symbols-outlined" style="color:#10b981;font-size:20px">school</span>
          Daftar Program Studi di {{ $kompi }}
        </div>
        <span style="font-size:11px;font-weight:700;background:#ecfdf5;color:#047857;padding:4px 10px;border-radius:20px">
          {{ $prodiSummary->count() }} Prodi
        </span>
      </div>

      <div class="ks-breakdown-container">
        @forelse($prodiSummary as $p)
        <div class="ks-breakdown-item">
          <span style="font-size:12px;font-weight:600;color:#334155;line-height:1.3">{{ $p->prodi }}</span>
          <span style="font-size:11px;font-weight:700;background:#d1fae5;color:#047857;padding:3px 8px;border-radius:6px;white-space:nowrap;flex-shrink:0">
            {{ $p->count }} Mhs
          </span>
        </div>
        @empty
        <div style="font-size:12px;color:#94a3b8;padding:12px">Belum ada data Prodi</div>
        @endforelse
      </div>
    </div>
  </div>

  {{-- READ-ONLY ANNOUNCEMENT BANNER FOR TIMDIS --}}
  <div class="ks-announcement-panel">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
      <div style="display:flex;align-items:flex-start;gap:14px">
        <div style="width:42px;height:42px;border-radius:12px;background:#dbeafe;display:flex;align-items:center;justify-content:center;color:#2563eb;flex-shrink:0">
          <span class="material-symbols-outlined" style="font-size:24px">campaign</span>
        </div>
        <div>
          <div style="font-size:15px;font-weight:800;color:#1e293b">Pengumuman & Link WA {{ $kompi }}</div>
          @if($announcement && $announcement->is_active)
            <div style="font-size:13px;color:#334155;margin-top:4px">
              <strong>Judul:</strong> {{ $announcement->judul }}
            </div>
            @if($announcement->pesan)
              <div style="font-size:12px;color:#64748b;margin-top:2px">{{ \Illuminate\Support\Str::limit($announcement->pesan, 100) }}</div>
            @endif
          @else
            <div style="font-size:12px;color:#94a3b8;margin-top:4px">Belum ada pengumuman aktif dari Garda kompi ini.</div>
          @endif
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:12px">
        @if($announcement && $announcement->link_wa)
          <a href="{{ $announcement->link_wa }}" target="_blank" style="background:linear-gradient(135deg, #25D366 0%, #128C7E 100%);color:#fff;font-weight:700;font-size:12px;padding:8px 16px;border-radius:50px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(37,211,102,0.25)">
            <span class="material-symbols-outlined" style="font-size:18px">groups</span> Link Group WA
          </a>
        @endif
        <span style="font-size:11px;color:#64748b;background:#ffffff;padding:6px 12px;border-radius:8px;border:1px solid #e2e8f0">
          ℹ Pengisian pengumuman WA dikelola khusus oleh Garda
        </span>
      </div>
    </div>
  </div>

  {{-- TABEL MAHASISWA PANEL --}}
  <div class="panel" style="border-radius:14px;box-shadow:0 4px 15px rgba(0,0,0,0.02)">
    {{-- Header & Filter Bar --}}
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #e2e8f0">
      <div>
        <div style="font-size:16px;font-weight:800;color:#0f172a">Detail Mahasiswa {{ $kompi }}</div>
        <div style="font-size:12px;color:#64748b;margin-top:2px">Menampilkan {{ $mahasiswaPaginated->total() }} mahasiswa terdaftar</div>
      </div>

      <div class="ks-filter-bar">
        {{-- Filter Status Absen --}}
        <div style="position:relative">
          <select id="status-filter" class="form-input" style="padding-left:34px;padding-right:28px;height:38px;font-size:12px;border-radius:8px;background:#f8fafc" onchange="applySearch()">
            <option value="" {{ ($statusFilter ?? '') == '' ? 'selected' : '' }}>Semua Status Absen</option>
            <option value="absen" {{ ($statusFilter ?? '') == 'absen' ? 'selected' : '' }}>Sudah Absen Masuk</option>
            <option value="belum_absen" {{ ($statusFilter ?? '') == 'belum_absen' ? 'selected' : '' }}>Belum Absen Masuk</option>
          </select>
          <span class="material-symbols-outlined" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:18px;color:#64748b">filter_list</span>
        </div>

        {{-- Search Input --}}
        <div style="position:relative">
          <input type="text" id="search-box" class="form-input" placeholder="Cari nama, NIM, prodi..." value="{{ $search }}" style="padding-left:34px;height:38px;font-size:12px;border-radius:8px;width:220px;background:#f8fafc" onkeydown="if(event.key === 'Enter'){ event.preventDefault(); applySearch(); }">
          <span class="material-symbols-outlined" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:18px;color:#64748b">search</span>
        </div>

        {{-- Per Page Select --}}
        <select id="per-page-select" class="form-input" style="height:38px;font-size:12px;border-radius:8px;background:#f8fafc;width:auto" onchange="applySearch()">
          <option value="20" {{ ($perPageReq ?? '20') == '20' ? 'selected' : '' }}>20 mhs / hal</option>
          <option value="50" {{ ($perPageReq ?? '') == '50' ? 'selected' : '' }}>50 mhs / hal</option>
          <option value="100" {{ ($perPageReq ?? '') == '100' ? 'selected' : '' }}>100 mhs / hal</option>
          <option value="all" {{ ($perPageReq ?? '') == 'all' ? 'selected' : '' }}>Tampilkan Semua</option>
        </select>

        <button type="button" class="btn btn-primary btn-sm" onclick="applySearch()" style="height:38px;padding:0 14px;border-radius:8px;font-weight:700;display:flex;align-items:center;gap:4px">
          Cari
        </button>

        @if($search !== '' || ($perPageReq ?? '20') !== '20' || ($statusFilter ?? '') !== '')
        <a href="{{ route('timdis.kompi-saya') }}" class="btn btn-ghost btn-sm" style="height:38px;padding:0 12px;border-radius:8px;font-size:12px;color:#64748b">
          Reset Filter
        </a>
        @endif
      </div>
    </div>

    {{-- Table --}}
    <div style="overflow-x:auto">
      <table class="ks-mhs-table">
        <thead>
          <tr>
            <th style="width:40px;text-align:center">No</th>
            <th style="width:50px">Foto</th>
            <th>Mahasiswa</th>
            <th>Jurusan & Prodi</th>
            <th>Absen Masuk (Hari Ini)</th>
            <th style="text-align:center">Akumulasi Sesi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($mahasiswaPaginated as $index => $m)
          @php
            $isCheckInToday = in_array($m->id, $dailyCheckInsToday);
            $mhsAttendances = $attendancesByMhs->get($m->id, collect());
            
            $hCount = 0; $aCount = 0; $iCount = 0; $sCount = 0;
            foreach($mhsAttendances as $att) {
              if (in_array($att->status, ['present', 'hadir'])) $hCount++;
              elseif ($att->status === 'alpha') $aCount++;
              elseif ($att->status === 'izin') $iCount++;
              elseif ($att->status === 'sakit') $sCount++;
            }
          @endphp
          <tr>
            <td style="text-align:center;font-size:12px;color:#94a3b8;font-weight:600">
              {{ ($mahasiswaPaginated->currentPage() - 1) * $mahasiswaPaginated->perPage() + $loop->iteration }}
            </td>
            <td>
              @if($m->photo_url)
                <img src="{{ $m->photo_url }}" alt="{{ $m->name }}" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid #3b82f6">
              @else
                <div style="width:38px;height:38px;border-radius:50%;background:#eff6ff;display:flex;align-items:center;justify-content:center;border:1px dashed #bfdbfe;color:#2563eb;font-weight:700;font-size:14px">
                  {{ strtoupper(substr($m->name, 0, 2)) }}
                </div>
              @endif
            </td>
            <td>
              <div style="font-weight:700;font-size:13px;color:#0f172a">{{ $m->name }}</div>
              <div style="font-family:monospace;font-size:11px;color:#64748b;margin-top:1px">{{ $m->id }}</div>
            </td>
            <td>
              <div style="font-size:12px;font-weight:600;color:#334155">{{ $m->jurusan ?? '-' }}</div>
              <div style="font-size:11px;color:#64748b">{{ $m->prodi ?? '-' }}</div>
            </td>
            <td>
              @if($isCheckInToday)
                <span style="background:#dcfce7;color:#15803d;padding:5px 12px;border-radius:20px;font-weight:700;font-size:11px;display:inline-flex;align-items:center;gap:4px">
                  <span class="material-symbols-outlined" style="font-size:14px">check_circle</span> Sudah Absen
                </span>
              @else
                <span style="background:#fef2f2;color:#b91c1c;padding:5px 12px;border-radius:20px;font-weight:700;font-size:11px;display:inline-flex;align-items:center;gap:4px">
                  <span class="material-symbols-outlined" style="font-size:14px">cancel</span> Belum Absen
                </span>
              @endif
            </td>
            <td style="text-align:center">
              <div style="display:inline-flex;gap:6px;font-size:11px;font-weight:700">
                <span style="background:#ecfdf5;color:#047857;padding:3px 8px;border-radius:6px;border:1px solid #a7f3d0" title="Hadir">H: {{ $hCount }}</span>
                <span style="background:#fef2f2;color:#b91c1c;padding:3px 8px;border-radius:6px;border:1px solid #fecaca" title="Alpha">A: {{ $aCount }}</span>
                <span style="background:#fffbeb;color:#b45309;padding:3px 8px;border-radius:6px;border:1px solid #fde68a" title="Izin">I: {{ $iCount }}</span>
                <span style="background:#f0f9ff;color:#0369a1;padding:3px 8px;border-radius:6px;border:1px solid #bae6fd" title="Sakit">S: {{ $sCount }}</span>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" style="text-align:center;padding:40px;color:#94a3b8">
              <span class="material-symbols-outlined" style="font-size:36px;color:#cbd5e1;display:block;margin-bottom:8px">search_off</span>
              Tidak ada data mahasiswa ditemukan di {{ $kompi }}.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(($perPageReq ?? '') !== 'all' && $mahasiswaPaginated->hasPages())
    <div style="margin-top:20px;display:flex;justify-content:center">
      {{ $mahasiswaPaginated->links('pagination::bootstrap-4') }}
    </div>
    @endif
  </div>
</section>

<script>
function applySearch() {
  const search = document.getElementById('search-box').value || '';
  const statusFilter = document.getElementById('status-filter').value || '';
  const perPageSelect = document.getElementById('per-page-select');
  const perPage = perPageSelect ? perPageSelect.value : '20';
  const url = new URL('{{ route('timdis.kompi-saya') }}', window.location.origin);
  if (search.trim() !== '') {
    url.searchParams.set('search', search.trim());
  }
  if (statusFilter !== '') {
    url.searchParams.set('status', statusFilter);
  }
  if (perPage !== '20') {
    url.searchParams.set('per_page', perPage);
  }
  window.location.href = url.toString();
}
</script>
@endsection
