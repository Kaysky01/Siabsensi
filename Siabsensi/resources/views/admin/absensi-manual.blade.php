@extends('layouts.admin')
@section('title', 'Absensi Manual — SIABSEN')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
        text: '{{ session('success') }}',
        confirmButtonColor: '#28a745',
        timer: 3000,
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
        text: '{{ session('error') }}',
        confirmButtonColor: '#dc3545'
      });
    });
  </script>
  @endif

  <div class="panel">
    <form method="POST" action="{{ route('admin.absensi-manual.store', $sesi->id) }}" id="absensi-form">
      @csrf
      
      <div class="section-header" style="margin-bottom:16px">
        <div>
          <div class="section-title">Daftar Mahasiswa</div>
          <div class="section-sub">Centang mahasiswa yang hadir</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <button type="button" class="btn btn-ghost btn-sm" onclick="checkAll()">
            <span class="material-symbols-outlined" style="font-size:16px">check_box</span> Centang Semua
          </button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="uncheckAll()">
            <span class="material-symbols-outlined" style="font-size:16px">check_box_outline_blank</span> Hapus Semua
          </button>
          <span id="count-display" style="font-size:14px;color:var(--text-muted)">0 dipilih</span>
        </div>
      </div>

      <div style="margin-bottom:16px">
        <input type="text" id="search-box" class="form-input" placeholder="Cari mahasiswa..." onkeyup="filterMahasiswa()">
      </div>

      <div style="overflow-x:auto;max-height:600px;overflow-y:auto;border:1px solid var(--border-color);border-radius:8px">
        <table class="att-table" id="mahasiswa-table" style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th style="width:60px;text-align:center">Hadir</th>
              <th>Nama</th>
              <th>Kompi</th>
              <th>Prodi</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($mahasiswaPaginated as $mhs)
            <tr class="mahasiswa-row" data-name="{{ strtolower($mhs->name) }}" data-kompi="{{ strtolower($mhs->kompi) }}">
              <td>
                <input type="checkbox" 
                       name="hadir[]" 
                       value="{{ $mhs->id }}" 
                       class="checkbox-hadir"
                       {{ isset($attendances[$mhs->id]) ? 'checked' : '' }}
                       onchange="updateCount()">
              </td>
              <td><strong>{{ $mhs->name }}</strong></td>
              <td>{{ $mhs->kompi }}</td>
              <td>{{ $mhs->prodi }}</td>
              <td>
                @if(isset($attendances[$mhs->id]))
                <span class="badge badge-green">Hadir</span>
                @if($attendances[$mhs->id]->absen_by)
                <small style="color:var(--text-muted);display:block;margin-top:4px">
                  oleh {{ $attendances[$mhs->id]->absen_by }} 
                  @ {{ $attendances[$mhs->id]->absen_at->format('H:i') }}
                </small>
                @endif
                @else
                <span class="badge badge-gray">Belum Hadir</span>
                @endif
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px">
                @if(auth()->user()->role === 'garda')
                Tidak ada mahasiswa di kompi Anda.
                @else
                Tidak ada mahasiswa aktif.
                @endif
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Pagination --}}
      @if($mahasiswaPaginated->hasPages())
      <div style="margin-top:16px;display:flex;justify-content:center">
        {{ $mahasiswaPaginated->links('pagination::custom') }}
      </div>
      @endif

      <div style="margin-top:24px;padding-top:24px;border-top:2px solid var(--border-color);display:flex;justify-content:space-between;align-items:center">
        <div>
          <div style="font-size:14px;color:var(--text-muted)">Total Mahasiswa: <strong>{{ $mahasiswaPaginated->total() }}</strong></div>
          <div style="font-size:14px;color:var(--text-muted)">Yang Hadir Sebelumnya: <strong>{{ $attendances->count() }}</strong></div>
        </div>
        <button type="submit" class="btn btn-primary" onclick="return confirmSave()">
          <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">save</span> Simpan Absensi
        </button>
      </div>
    </form>
  </div>

  <div class="panel" style="margin-top:24px;background:#fff3cd;border-left:4px solid #ffc107">
    <div style="font-size:14px;line-height:1.8;color:#856404">
      <strong>⚠️ Perhatian:</strong>
      <ul style="margin:8px 0 0 20px">
        <li>Menyimpan absensi akan <strong>mengganti semua data absensi sebelumnya</strong> untuk sesi ini</li>
        <li>Hanya mahasiswa yang dicentang yang akan tercatat hadir</li>
        <li>Pastikan Anda sudah memeriksa dengan teliti sebelum menyimpan</li>
      </ul>
    </div>
  </div>
</section>

<script>
function toggleAll(checkbox) {
  const checkboxes = document.querySelectorAll('.checkbox-hadir');
  const visibleCheckboxes = Array.from(checkboxes).filter(cb => {
    const row = cb.closest('.mahasiswa-row');
    return row && row.style.display !== 'none';
  });
  
  visibleCheckboxes.forEach(cb => {
    cb.checked = checkbox.checked;
  });
  updateCount();
}

function checkAll() {
  const checkboxes = document.querySelectorAll('.checkbox-hadir');
  const visibleCheckboxes = Array.from(checkboxes).filter(cb => {
    const row = cb.closest('.mahasiswa-row');
    return row && row.style.display !== 'none';
  });
  
  visibleCheckboxes.forEach(cb => {
    cb.checked = true;
  });
  updateCount();
}

function uncheckAll() {
  const checkboxes = document.querySelectorAll('.checkbox-hadir');
  checkboxes.forEach(cb => {
    cb.checked = false;
  });
  updateCount();
}

function updateCount() {
  const checked = document.querySelectorAll('.checkbox-hadir:checked').length;
  document.getElementById('count-display').textContent = checked + ' dipilih';
}

function filterMahasiswa() {
  const searchTerm = document.getElementById('search-box').value.toLowerCase();
  const rows = document.querySelectorAll('.mahasiswa-row');
  
  rows.forEach(row => {
    const name = row.dataset.name;
    const kompi = row.dataset.kompi;
    
    if (name.includes(searchTerm) || kompi.includes(searchTerm)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
  
  updateCount();
}

function confirmSave() {
  const checked = document.querySelectorAll('.checkbox-hadir:checked').length;
  const total = {{ $mahasiswaPaginated->total() }};
  
  if (checked === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Tidak Ada yang Dipilih',
      text: 'Anda belum mencentang mahasiswa yang hadir. Yakin ingin menyimpan (semua akan tercatat tidak hadir)?',
      showCancelButton: true,
      confirmButtonColor: '#ffc107',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Ya, Simpan',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        document.getElementById('absensi-form').submit();
      }
    });
    return false;
  }
  
  Swal.fire({
    icon: 'question',
    title: 'Konfirmasi Simpan',
    html: `<div style="text-align:left">
      <p>Anda akan menyimpan absensi dengan detail:</p>
      <ul>
        <li><strong>Hadir:</strong> ${checked} mahasiswa</li>
        <li><strong>Tidak Hadir:</strong> ${total - checked} mahasiswa</li>
      </ul>
      <p style="color:#dc3545;font-weight:600;margin-top:12px">⚠️ Data absensi sebelumnya akan diganti!</p>
    </div>`,
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Simpan!',
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

// Initialize count on page load
document.addEventListener('DOMContentLoaded', function() {
  updateCount();
});
</script>

<style>
.mahasiswa-row {
  transition: background-color 0.2s;
}

.mahasiswa-row:hover {
  background-color: #f8f9fa;
}

.checkbox-hadir {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

#select-all {
  width: 18px;
  height: 18px;
  cursor: pointer;
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
  padding: 12px;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
}
</style>
@endsection
