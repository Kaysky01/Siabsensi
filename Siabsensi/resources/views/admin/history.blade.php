@extends('layouts.admin')
@section('title', 'Riwayat & Rekap Absensi — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Riwayat & Rekap Absensi</div>
      <div class="page-sub">{{ Carbon\Carbon::parse($start)->format('d/m/Y') }} — {{ Carbon\Carbon::parse($end)->format('d/m/Y') }} • Data historis untuk analisis dan laporan</div>
    </div>
    <div class="header-actions">
      <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('modal-export-excel').classList.add('show')">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Export Excel
      </button>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;padding:14px 20px">
    <form method="GET" action="{{ route('admin.history') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div>
        <label class="form-label">Dari</label>
        <input type="date" name="start" class="form-input" value="{{ $start }}" style="padding:7px 10px">
      </div>
      <div>
        <label class="form-label">Sampai</label>
        <input type="date" name="end" class="form-input" value="{{ $end }}" style="padding:7px 10px">
      </div>
      <div>
        <label class="form-label">Status</label>
        <select name="filter" class="form-input" style="width:120px;padding:7px 10px">
          <option value="all" {{ $filter=='all'?'selected':'' }}>Semua</option>
          <option value="hadir" {{ $filter=='hadir'?'selected':'' }}>Hadir</option>
          <option value="izin" {{ $filter=='izin'?'selected':'' }}>Izin</option>
          <option value="sakit" {{ $filter=='sakit'?'selected':'' }}>Sakit</option>
          <option value="alpha" {{ $filter=='alpha'?'selected':'' }}>Alpha</option>
        </select>
      </div>
      <div>
        <label class="form-label">Nama Mahasiswa</label>
        <input type="text" name="search" class="form-input" placeholder="Cari nama..." value="{{ $search }}" style="padding:7px 10px;width:180px">
      </div>
      <div>
        <label class="form-label">Kompi</label>
        <select name="kompi" class="form-input" style="width:120px;padding:7px 10px">
          <option value="">Semua</option>
          @foreach($kompiOptions as $k)
          <option value="{{ $k }}" {{ $kompi === $k ? 'selected' : '' }}>{{ $k }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="form-label">Jurusan</label>
        <select name="jurusan" class="form-input" style="width:180px;padding:7px 10px">
          <option value="">Semua</option>
          @foreach($jurusanOptions as $j)
          <option value="{{ $j }}" {{ $jurusan === $j ? 'selected' : '' }}>{{ $j }}</option>
          @endforeach
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">search</span> Cari
      </button>
      @if($search || $kompi || $jurusan || $filter !== 'all')
      <a href="{{ route('admin.history', ['start' => $start, 'end' => $end]) }}" class="btn btn-secondary btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span> Reset Filter
      </a>
      @endif
    </form>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>No</th><th>Mahasiswa</th><th>Kompi</th><th>Tanggal</th><th>Tipe</th><th>Masuk</th><th>Keluar</th><th>Status</th><th>Keterlambatan</th><th>Aksi</th></tr></thead>
      <tbody>
        @forelse($attendances as $i => $att)
        <tr>
          <td>{{ $attendances->firstItem() + $i }}</td>
          <td><div class="mhs-name">{{ $att->name }}</div></td>
          <td><span class="badge badge-blue">{{ $att->kompi }}</span></td>
          <td style="font-size:13px">{{ $att->date ?? '-' }}</td>
          <td>
            @if($att->kegiatan_id)
              <span class="badge badge-purple" title="Absensi berbasis kegiatan">
                <i class="fas fa-calendar-alt"></i> Kegiatan
              </span>
            @else
              <span class="badge badge-gray" title="Absensi harian reguler">
                <i class="fas fa-calendar-day"></i> Harian
              </span>
            @endif
          </td>
          <td><span class="time-val">{{ $att->check_in ? Carbon\Carbon::parse($att->check_in)->format('H:i') : '-' }}</span></td>
          <td><span class="time-val">{{ $att->check_out ? Carbon\Carbon::parse($att->check_out)->format('H:i') : '-' }}</span></td>
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
            @if($att->kegiatan_id)
              <span class="badge badge-gray" title="Validasi waktu tidak berlaku untuk kegiatan">
                <i class="fas fa-minus"></i> N/A
              </span>
            @elseif($att->is_late ?? false)
              @if($att->late_overridden ?? false)
                <span class="badge badge-gray" title="Telat {{ $att->late_duration }} menit (Di-override oleh {{ $att->overridden_by }})">
                  <i class="fas fa-check-circle"></i> TELAT (Override)
                </span>
              @else
                <span class="badge badge-danger">
                  <i class="fas fa-clock"></i> TELAT {{ $att->late_duration }} menit
                </span>
              @endif
            @else
              <span class="badge badge-success">Tepat Waktu</span>
            @endif
          </td>
          <td>
            @if($att->kegiatan_id)
              <span style="color:var(--text-muted);font-size:12px" title="Override tidak tersedia untuk kegiatan">-</span>
            @elseif(($att->is_late ?? false) && !($att->late_overridden ?? false))
              <button type="button" class="btn btn-warning btn-xs" onclick="showOverrideModal({{ $att->id ?? 0 }}, '{{ $att->name }}', {{ $att->late_duration ?? 0 }})">
                <i class="fas fa-edit"></i> Override
              </button>
            @elseif($att->late_overridden ?? false)
              <button type="button" class="btn btn-info btn-xs" onclick="showOverrideDetails({{ $att->id ?? 0 }}, '{{ $att->overridden_by }}', '{{ $att->override_reason }}', '{{ $att->override_timestamp ? Carbon\Carbon::parse($att->override_timestamp)->format('d/m/Y H:i') : '' }}')">
                <i class="fas fa-info-circle"></i> Info
              </button>
            @else
              <span style="color:var(--text-muted);font-size:12px">-</span>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada data</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  
  <div style="margin-top: 16px;">
    {{ $attendances->links('pagination::bootstrap-4') }}
  </div>
</section>

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
              @foreach($kompiOptions ?? [] as $k)
                <option value="{{ $k }}" {{ ($kompi ?? '') == $k ? 'selected' : '' }}>{{ $k }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label" style="font-size:12px;">Jurusan</label>
            <select name="jurusan" class="form-input" style="padding:7px 10px;">
              <option value="">Semua Jurusan</option>
              @foreach($jurusanOptions ?? [] as $j)
                <option value="{{ $j }}" {{ ($jurusan ?? '') == $j ? 'selected' : '' }}>{{ $j }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="form-label" style="font-size:12px;">Prodi</label>
            <select name="prodi" class="form-input" style="padding:7px 10px;">
              <option value="">Semua Prodi</option>
              @foreach($prodiOptions ?? [] as $p)
                <option value="{{ $p }}">{{ $p }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      {{-- Section 2: Format File Export (Split per Day or Combined) --}}
      <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border);">
        <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: var(--text-muted);">2. Format File (Opsi Rentang Tanggal)</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px;">
          <label style="display:flex; align-items:flex-start; gap:10px; background:var(--bg-lighter); padding:12px 14px; border:1.5px solid var(--border); border-radius:8px; cursor:pointer; transition:all 0.2s;">
            <input type="radio" name="split_mode" value="combined" checked style="margin-top:2px; accent-color:var(--primary);">
            <div>
              <div style="font-size:13px; font-weight:700; color:var(--text);"><i class="fas fa-file-excel" style="color:#16a34a; margin-right:4px;"></i> Jadi 1 File Excel</div>
              <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Seluruh rentang tanggal digabung dalam 1 file Excel (.xlsx).</div>
            </div>
          </label>

          <label style="display:flex; align-items:flex-start; gap:10px; background:var(--bg-lighter); padding:12px 14px; border:1.5px solid var(--border); border-radius:8px; cursor:pointer; transition:all 0.2s;">
            <input type="radio" name="split_mode" value="per_day" style="margin-top:2px; accent-color:var(--primary);">
            <div>
              <div style="font-size:13px; font-weight:700; color:var(--text);"><i class="fas fa-file-archive" style="color:#2563eb; margin-right:4px;"></i> Dipisah Per Hari (ZIP / Multi Excel)</div>
              <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Setiap hari dipisah jadi file Excel tersendiri (Misal: Tanggal 10-15 = 6 File Excel).</div>
            </div>
          </label>
        </div>
      </div>

      {{-- Section 3: Choice of Jalur / Sheet --}}
      <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border);">
        <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: var(--text-muted);">3. Pilih Jalur / Kategori Sheet (Ceklis yang Diinginkan)</h4>
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

      {{-- Section 4: Field Selection --}}
      <div style="margin-bottom: 24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
          <h4 style="margin:0; font-size:14px; font-weight:600; color:var(--text-muted);">4. Pilih Kolom Data yang Ditampilkan</h4>
          <label style="display:flex; align-items:center; gap:6px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" id="export-check-all-history" checked onchange="toggleAllExportFieldsHistory(this)">
            <span style="font-weight:600; color:var(--primary);">Pilih Semua Kolom</span>
          </label>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; background:var(--bg-lighter); padding:16px; border:1px solid var(--border); border-radius:8px;">
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb-history" name="export_fields[]" value="id" checked>
            <span>ID / No. Pendaftaran</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb-history" name="export_fields[]" value="name" checked>
            <span>Nama Mahasiswa</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb-history" name="export_fields[]" value="email" checked>
            <span>Email Mahasiswa</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb-history" name="export_fields[]" value="kompi" checked>
            <span>Kompi</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb-history" name="export_fields[]" value="jurusan" checked>
            <span>Jurusan</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb-history" name="export_fields[]" value="prodi" checked>
            <span>Prodi</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb-history" name="export_fields[]" value="date" checked>
            <span>Tanggal Absensi</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb-history" name="export_fields[]" value="check_in" checked>
            <span>Waktu Masuk</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb-history" name="export_fields[]" value="check_out" checked>
            <span>Waktu Keluar</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb-history" name="export_fields[]" value="status" checked>
            <span>Status Absensi</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; font-size:12px; cursor:pointer; user-select:none;">
            <input type="checkbox" class="export-field-cb-history" name="export_fields[]" value="camera_id" checked>
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
function toggleAllExportFieldsHistory(source) {
  const checkboxes = document.querySelectorAll('.export-field-cb-history');
  checkboxes.forEach(cb => cb.checked = source.checked);
}
</script>
@endsection
