@extends('layouts.admin')
@section('title', 'Absensi Manual — SIABSEN')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@php
  $eligibleTotal = count($eligibleMahasiswaIds);
@endphp

<section>
  <div class="page-header">
    <div>
      <div class="page-title">Absensi Manual: {{ $sesi->nama_sesi }}</div>
      <div class="page-sub">
        @if($sesi->pkkmbSchedule)
        PKKMB Hari ke-{{ $sesi->pkkmbSchedule->hari_ke }} - {{ $sesi->pkkmbSchedule->formatted_date }}
        @elseif($sesi->kegiatan)
        {{ $sesi->kegiatan->nama }} - {{ $sesi->kegiatan->tanggal_pelaksanaan->format('d M Y') }}
        @else
        {{ $sesi->tanggal ? $sesi->tanggal->format('d M Y') : 'Tanggal tidak diketahui' }}
        @endif
      </div>
      @if(auth()->user()->role === 'garda')
      <div class="page-sub" style="color:var(--primary);font-weight:600">Kompi Anda: {{ auth()->user()->assigned_kompi ?? 'Tidak ada' }}</div>
      @endif
    </div>
    <a href="{{ route('admin.absensi-persesi') }}" class="btn btn-ghost btn-sm">
      <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">arrow_back</span> Kembali
    </a>
  </div>

  @if(session('success'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: @json(session('success')),
        confirmButtonColor: '#28a745',
        timer: 3500,
        timerProgressBar: true
      });
    });
  </script>
  @endif

  @if(session('error'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: @json(session('error')),
        confirmButtonColor: '#dc3545'
      });
    });
  </script>
  @endif

  <div class="panel">
    <form method="POST" action="{{ route('admin.absensi-manual.store', $sesi->id) }}" id="absensi-form">
      @csrf
      <input type="hidden" name="search" value="{{ $search }}">
      <input type="hidden" name="bulk_action" id="bulk_action_input" value="">
      
      <div class="section-header" style="margin-bottom:16px">
        <div>
          <div class="section-title">Input Status Absensi Sesi</div>
          <div class="section-sub">Mahasiswa eligible default <strong>Hadir</strong>, tidak eligible default <strong>Alpha</strong>. Anda dapat mengubah status ke Hadir, Alpha, Izin, atau Sakit.</div>
        </div>
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
          <button type="button" class="btn btn-ghost btn-sm" onclick="setAllStatus('present')" style="color:#28a745">
            <span class="material-symbols-outlined" style="font-size:16px">check_circle</span> Semua Hadir
          </button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="setAllStatus('alpha')" style="color:#dc3545">
            <span class="material-symbols-outlined" style="font-size:16px">cancel</span> Semua Alpha
          </button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="setAllStatus('izin')" style="color:#ff9800">
            <span class="material-symbols-outlined" style="font-size:16px">event_note</span> Semua Izin
          </button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="setAllStatus('sakit')" style="color:#17a2b8">
            <span class="material-symbols-outlined" style="font-size:16px">medical_services</span> Semua Sakit
          </button>
        </div>
      </div>

      <div style="margin-bottom:16px">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input type="text" id="search-box" class="form-input" style="flex:1;min-width:200px" placeholder="Cari nama, NIM, kompi, prodi..." value="{{ $search }}" onkeydown="if(event.key === 'Enter'){ event.preventDefault(); applySearch(); }">
          <select id="per-page-select" class="form-input" style="width:auto" onchange="applySearch()">
            <option value="20" {{ ($perPageReq ?? '20') == '20' ? 'selected' : '' }}>20 mhs / hal</option>
            <option value="50" {{ ($perPageReq ?? '') == '50' ? 'selected' : '' }}>50 mhs / hal</option>
            <option value="100" {{ ($perPageReq ?? '') == '100' ? 'selected' : '' }}>100 mhs / hal</option>
            <option value="all" {{ ($perPageReq ?? '') == 'all' ? 'selected' : '' }}>Tampilkan Semua ({{ $totalMahasiswaCount }})</option>
          </select>
          <button type="button" class="btn btn-ghost btn-sm" onclick="applySearch()">
            <span class="material-symbols-outlined" style="font-size:16px">search</span> Cari
          </button>
          @if($search !== '' || ($perPageReq ?? '20') !== '20')
          <a href="{{ route('admin.absensi-manual.index', $sesi->id) }}" class="btn btn-ghost btn-sm">
            <span class="material-symbols-outlined" style="font-size:16px">refresh</span> Reset
          </a>
          @endif
        </div>
      </div>

      <div style="overflow-x:auto;max-height:600px;overflow-y:auto;border:1px solid var(--border-color);border-radius:8px">
        <table class="att-table" id="mahasiswa-table" style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th style="width:40px;text-align:center">No</th>
              <th>Nama Mahasiswa</th>
              <th>Kompi</th>
              <th>Prodi</th>
              <th>Absen Masuk Harian</th>
              <th style="width:320px;text-align:center">Pilihan Status Absensi Sesi</th>
            </tr>
          </thead>
          <tbody>
            @if(count($mahasiswaPaginated) > 0)
            @foreach($mahasiswaPaginated as $index => $mhs)
            @php
              $isEligible = isset($dailyAttendances[$mhs->id]);
              $existingRecord = $attendances[$mhs->id] ?? null;
              $currentStatus = $existingRecord ? $existingRecord->status : ($isEligible ? 'present' : 'alpha');
            @endphp
            <tr class="mahasiswa-row">
              <td style="text-align:center;font-weight:600">{{ ($mahasiswaPaginated->currentPage() - 1) * $mahasiswaPaginated->perPage() + $index + 1 }}</td>
              <td>
                <strong>{{ $mhs->name }}</strong>
                <small style="display:block;color:var(--text-muted);font-size:11px">NIM: {{ $mhs->id }}</small>
              </td>
              <td>{{ $mhs->kompi }}</td>
              <td>{{ $mhs->prodi }}</td>
              <td>
                @if($isEligible)
                <span class="badge badge-green">✓ Sudah Absen Masuk</span>
                @else
                <span class="badge badge-yellow">⚠️ Belum Absen Masuk</span>
                @endif
              </td>
              <td style="text-align:center">
                @if($isEligible)
                <div class="status-options">
                  <label class="status-option option-present {{ ($currentStatus === 'present' || $currentStatus === 'hadir') ? 'active' : '' }}">
                    <input type="radio" name="status[{{ $mhs->id }}]" value="present" {{ ($currentStatus === 'present' || $currentStatus === 'hadir') ? 'checked' : '' }} onchange="onStatusChange(this)">
                    <span>Hadir</span>
                  </label>
                  <label class="status-option option-alpha {{ $currentStatus === 'alpha' ? 'active' : '' }}">
                    <input type="radio" name="status[{{ $mhs->id }}]" value="alpha" {{ $currentStatus === 'alpha' ? 'checked' : '' }} onchange="onStatusChange(this)">
                    <span>Alpha</span>
                  </label>
                  <label class="status-option option-izin {{ $currentStatus === 'izin' ? 'active' : '' }}">
                    <input type="radio" name="status[{{ $mhs->id }}]" value="izin" {{ $currentStatus === 'izin' ? 'checked' : '' }} onchange="onStatusChange(this)">
                    <span>Izin</span>
                  </label>
                  <label class="status-option option-sakit {{ $currentStatus === 'sakit' ? 'active' : '' }}">
                    <input type="radio" name="status[{{ $mhs->id }}]" value="sakit" {{ $currentStatus === 'sakit' ? 'checked' : '' }} onchange="onStatusChange(this)">
                    <span>Sakit</span>
                  </label>
                </div>
                @else
                <input type="hidden" name="status[{{ $mhs->id }}]" value="alpha">
                <div class="status-options" style="opacity:0.55;cursor:not-allowed;pointer-events:none" title="Tidak dapat diubah (Belum Absen Masuk Harian)">
                  <label class="status-option option-present">
                    <input type="radio" disabled>
                    <span>Hadir</span>
                  </label>
                  <label class="status-option option-alpha active">
                    <input type="radio" disabled checked>
                    <span>Alpha</span>
                  </label>
                  <label class="status-option option-izin">
                    <input type="radio" disabled>
                    <span>Izin</span>
                  </label>
                  <label class="status-option option-sakit">
                    <input type="radio" disabled>
                    <span>Sakit</span>
                  </label>
                </div>
                @endif
              </td>
            </tr>
            @endforeach
            @else
            <tr>
              <td colspan="6" style="text-align:center;color:var(--text-muted);padding:30px">
                @if(auth()->user()->role === 'garda')
                Tidak ada mahasiswa di kompi Anda.
                @else
                Tidak ada mahasiswa aktif.
                @endif
              </td>
            </tr>
            @endif
          </tbody>
        </table>
      </div>

      {{-- Pagination --}}
      @if(($perPageReq ?? '') !== 'all' && $mahasiswaPaginated->hasPages())
      <div style="margin-top:16px;display:flex;justify-content:center">
        {{ $mahasiswaPaginated->links('pagination::custom') }}
      </div>
      @endif

      <div style="margin-top:24px;padding-top:24px;border-top:2px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
        <div>
          <div style="font-size:15px;font-weight:700;color:var(--text-main);margin-bottom:4px">
            Total Mahasiswa Keseluruhan Sesi: <span style="color:#4f46e5">{{ $totalMahasiswaCount }}</span> Mahasiswa
          </div>
          <div style="font-size:13px;color:var(--text-muted);margin-bottom:4px">
            Summary Seluruh Data Sesi: 
            <strong style="color:#28a745">Hadir: {{ $totalHadirCount }}</strong> | 
            <strong style="color:#dc3545">Alpha: {{ $totalAlphaCount }}</strong> | 
            <strong style="color:#ff9800">Izin: {{ $totalIzinCount }}</strong> | 
            <strong style="color:#17a2b8">Sakit: {{ $totalSakitCount }}</strong>
          </div>
          <div style="font-size:12px;color:var(--text-muted)" id="status-summary-display">
            (Ditampilkan di Halaman Ini: {{ $mahasiswaPaginated->count() }} mhs)
          </div>
        </div>
        <button type="submit" class="btn btn-primary" onclick="return confirmSave()">
          <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">save</span> Simpan Absensi Sesi
        </button>
      </div>
    </form>
  </div>

  <div class="panel" style="margin-top:24px;background:#eef2ff;border-left:4px solid #4f46e5">
    <div style="font-size:14px;line-height:1.8;color:#3730a3">
      <strong>💡 Informasi Sistem Absensi Sesi:</strong>
      <ul style="margin:8px 0 0 20px">
        <li><strong>Simpan Absensi Sesi</strong> akan menyimpan/memperbarui data absensi untuk <strong>SELURUH {{ $totalMahasiswaCount }} MAHASISWA</strong> di sesi ini.</li>
        <li>Aksi massal (<em>"Semua Hadir" / "Semua Alpha"</em>) akan diterapkan ke <strong>seluruh data mahasiswa</strong> di sesi ini.</li>
        <li>Mahasiswa yang <strong>belum absen masuk harian</strong> secara otomatis dikunci ke status <strong>Alpha</strong>.</li>
      </ul>
    </div>
  </div>
</section>

<script>
function onStatusChange(radio) {
  const container = radio.closest('.status-options');
  container.querySelectorAll('.status-option').forEach(opt => opt.classList.remove('active'));
  radio.closest('.status-option').classList.add('active');
  updateSummaryCount();
}

function setAllStatus(targetStatus) {
  document.getElementById('bulk_action_input').value = targetStatus;
  document.querySelectorAll(`.status-option input[value="${targetStatus}"]:not(:disabled)`).forEach(radio => {
    radio.checked = true;
    onStatusChange(radio);
  });

  const statusNames = { 'present': 'Hadir', 'alpha': 'Alpha', 'izin': 'Izin', 'sakit': 'Sakit' };
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'info',
    title: `Aksi Massal: "Semua ${statusNames[targetStatus]}" akan diterapkan ke SELURUH {{ $totalMahasiswaCount }} mahasiswa saat disimpan.`,
    showConfirmButton: false,
    timer: 3500
  });
}

function applySearch() {
  const search = document.getElementById('search-box').value || '';
  const perPageSelect = document.getElementById('per-page-select');
  const perPage = perPageSelect ? perPageSelect.value : '20';
  const url = new URL('{{ route('admin.absensi-manual.index', $sesi->id) }}', window.location.origin);
  if (search.trim() !== '') {
    url.searchParams.set('search', search.trim());
  }
  if (perPage !== '20') {
    url.searchParams.set('per_page', perPage);
  }
  window.location.href = url.toString();
}

function updateSummaryCount() {
  const hadirCount = document.querySelectorAll('input[type="radio"][value="present"]:checked:not(:disabled)').length;
  const alphaCount = document.querySelectorAll('input[type="radio"][value="alpha"]:checked:not(:disabled)').length + document.querySelectorAll('input[type="hidden"][value="alpha"]').length;
  const izinCount = document.querySelectorAll('input[type="radio"][value="izin"]:checked:not(:disabled)').length;
  const sakitCount = document.querySelectorAll('input[type="radio"][value="sakit"]:checked:not(:disabled)').length;
  
  const display = document.getElementById('status-summary-display');
  if (display) {
    display.innerHTML = `(Di Halaman Ini: <span style="color:#28a745;font-weight:600">Hadir: ${hadirCount}</span> | <span style="color:#dc3545;font-weight:600">Alpha: ${alphaCount}</span> | <span style="color:#ff9800;font-weight:600">Izin: ${izinCount}</span> | <span style="color:#17a2b8;font-weight:600">Sakit: ${sakitCount}</span>)`;
  }
}

function confirmSave() {
  const totalMahasiswa = {{ $totalMahasiswaCount }};
  const bulkAction = document.getElementById('bulk_action_input').value;
  let bulkNotice = '';
  if (bulkAction) {
    const statusNames = { 'present': 'Hadir', 'alpha': 'Alpha', 'izin': 'Izin', 'sakit': 'Sakit' };
    bulkNotice = `<p style="color:#4f46e5;font-weight:600;margin-top:8px">ℹ️ Aksi Massal Aktif: <strong>Semua ${statusNames[bulkAction]}</strong> akan diterapkan ke seluruh ${totalMahasiswa} mahasiswa (kecuali yang belum absen masuk yang otomatis Alpha).</p>`;
  }

  Swal.fire({
    icon: 'question',
    title: 'Konfirmasi Simpan Absensi Sesi',
    html: `<div style="text-align:left">
      <p>Anda akan menyimpan absensi sesi untuk <strong>SELURUH ${totalMahasiswa} MAHASISWA</strong>.</p>
      ${bulkNotice}
      <p style="color:#6366f1;font-weight:600;margin-top:12px">⚠️ Data absensi sesi ini akan disimpan / diperbarui ke database.</p>
    </div>`,
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Simpan Semua Data!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({
        title: 'Menyimpan...',
        text: 'Mohon tunggu sebentar',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      document.getElementById('absensi-form').submit();
    }
  });
  
  return false;
}

document.addEventListener('DOMContentLoaded', function() {
  updateSummaryCount();
});
</script>

<style>
.mahasiswa-row {
  transition: background-color 0.2s;
}

.mahasiswa-row:hover {
  background-color: #f8fafc;
}

.att-table {
  width: 100%;
  border-collapse: collapse;
}

.att-table thead th {
  background: var(--bg);
  font-weight: 600;
  padding: 12px;
  border-bottom: 2px solid var(--border-color);
  position: sticky;
  top: 0;
  z-index: 10;
}

.att-table tbody td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}

.badge-yellow {
  background: #fef3c7;
  color: #92400e;
}

.status-options {
  display: inline-flex;
  gap: 4px;
  background: #f1f5f9;
  padding: 3px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
}

.status-option {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  user-select: none;
  transition: all 0.15s ease;
  color: #64748b;
}

.status-option input[type="radio"] {
  display: none;
}

.status-option:hover {
  background: #e2e8f0;
}

.status-option.option-present.active {
  background: #28a745;
  color: #ffffff;
  box-shadow: 0 1px 3px rgba(40,167,69,0.3);
}

.status-option.option-alpha.active {
  background: #dc3545;
  color: #ffffff;
  box-shadow: 0 1px 3px rgba(220,53,69,0.3);
}

.status-option.option-izin.active {
  background: #ff9800;
  color: #ffffff;
  box-shadow: 0 1px 3px rgba(255,152,0,0.3);
}

.status-option.option-sakit.active {
  background: #17a2b8;
  color: #ffffff;
  box-shadow: 0 1px 3px rgba(23,162,184,0.3);
}
</style>
@endsection
