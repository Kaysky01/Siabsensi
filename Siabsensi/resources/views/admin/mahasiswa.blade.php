@extends('layouts.admin')
@section('title', 'Data Mahasiswa — SIABSEN')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@php
  $managementRoutePrefix = $managementRoutePrefix ?? (auth()->user()->role === 'timdis' ? 'timdis' : 'admin');
  $mahasiswaIndexRoute = $managementRoutePrefix . '.mahasiswa';
  $mahasiswaStoreRoute = $managementRoutePrefix . '.mahasiswa.store';
  $mahasiswaUpdateRoute = $managementRoutePrefix . '.mahasiswa.update';
  $mahasiswaDestroyRoute = $managementRoutePrefix . '.mahasiswa.destroy';
  $mahasiswaImportRoute = $managementRoutePrefix . '.mahasiswa.import';
  $mahasiswaImportTemplateRoute = $managementRoutePrefix . '.mahasiswa.import.template';
  $mahasiswaQrJsonBaseUrl = url('/' . $managementRoutePrefix . '/mahasiswa');
@endphp
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Data Mahasiswa</div>
      <div class="page-sub">Manajemen data mahasiswa ({{ $mahasiswaList->total() }} total, menampilkan {{ $mahasiswaList->count() }} data)</div>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-secondary btn-sm" onclick="document.getElementById('modal-import-csv').classList.add('show')">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">upload_file</span> Import Excel/CSV
      </button>
      <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-mhs').classList.add('show')">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">add</span> Tambah Mahasiswa
      </button>
    </div>
  </div>

  {{-- Filter --}}
  <div class="panel" style="margin-bottom:16px;padding:14px 20px">
    <form method="GET" action="{{ route($mahasiswaIndexRoute) }}" style="display:flex;gap:12px;align-items:stretch">
      <div style="flex:1;display:flex;flex-direction:column">
        <label class="form-label">Cari Nama</label>
        <input type="text" name="search" class="form-input" placeholder="Ketik nama..." value="{{ request('search') }}" style="flex:1;min-width:0">
      </div>
      <div style="display:flex;flex-direction:column">
        <label class="form-label">Kompi</label>
        <select name="kompi" class="form-input" style="width:120px;height:38px">
          <option value="">Semua</option>
          <option value="__empty__" {{ request('kompi') == '__empty__' ? 'selected' : '' }}>Belum Ada Kompi</option>
          @foreach($kompiOptions as $k)<option value="{{ $k }}" {{ request('kompi') == $k ? 'selected' : '' }}>{{ $k }}</option>@endforeach
        </select>
      </div>
      <div style="display:flex;flex-direction:column">
        <label class="form-label">Jurusan</label>
        <select name="jurusan" id="filter-jurusan" class="form-input" style="width:200px;height:38px" onchange="updateFilterProdi()">
          <option value="">Semua Jurusan</option>
          @foreach($jurusanWithProdi as $j)
            <option value="{{ $j->nama }}" data-prodi="{{ json_encode($j->prodi->pluck('nama')) }}" {{ request('jurusan') == $j->nama ? 'selected' : '' }}>
              {{ $j->nama }}
            </option>
          @endforeach
        </select>
      </div>
      <div style="display:flex;flex-direction:column">
        <label class="form-label">Prodi</label>
        <select name="prodi" id="filter-prodi" class="form-input" style="width:200px;height:38px" disabled>
          <option value="">Semua Prodi</option>
        </select>
      </div>
      <div style="display:flex;align-items:flex-end;gap:8px">
        <button type="submit" class="btn btn-primary" style="height:38px;padding:0 20px">Filter</button>
        <a href="{{ route($mahasiswaIndexRoute) }}" class="btn btn-ghost" style="height:38px;padding:0 20px">Reset</a>
      </div>
    </form>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>Mahasiswa</th><th>Kompi</th><th>Jurusan</th><th>Prodi</th><th>Email</th><th>Status Kegiatan</th><th>Aksi</th></tr></thead>
      <tbody>
        @forelse($mahasiswaList as $m)
        <tr>
          <td>
            <div class="mahasiswa-cell">
              <div class="avatar" style="background:var(--primary-light);color:var(--primary)">{{ strtoupper(substr($m->name, 0, 2)) }}</div>
              <div>
                <div class="mhs-name">{{ $m->name }}</div>
                <div class="mhs-dept">{{ $m->id }}</div>
              </div>
            </div>
          </td>
          <td><span class="badge badge-blue">{{ $m->kompi }}</span></td>
          <td style="font-size:13px">{{ $m->jurusan ?? '-' }}</td>
          <td style="font-size:13px">{{ $m->prodi ?? '-' }}</td>
          <td style="font-size:13px;color:var(--text-muted)">{{ $m->email ?? '-' }}</td>
          <td>
            <div style="display:flex;gap:4px">
              @foreach($allKegiatan as $keg)
                @php
                  $att = $m->attendances->filter(function($a) use ($keg) {
                      return $a->kegiatan_id == $keg->id || \Carbon\Carbon::parse($a->date)->format('Y-m-d') === \Carbon\Carbon::parse($keg->tanggal)->format('Y-m-d');
                  })->first();

                  if(!$att || $att->status === 'alpha') {
                    // Alpha (Merah)
                    $color = '#ef4444';
                    $title = $keg->nama . ' - Alpha';
                  } else if ($att->status === 'izin') {
                    // Izin (Biru)
                    $color = '#3b82f6';
                    $title = $keg->nama . ' - Izin';
                  } else if ($att->status === 'sakit') {
                    // Sakit (Kuning)
                    $color = '#eab308';
                    $title = $keg->nama . ' - Sakit';
                  } else if(!$att->check_out) {
                    // Baru Masuk (Hitam)
                    $color = '#1f2937';
                    $jamMasuk = $att->check_in ? \Carbon\Carbon::parse($att->check_in)->format('H:i') : '-';
                    $title = $keg->nama . ' - Masuk (' . $jamMasuk . ')';
                  } else {
                    // Lengkap (Hijau)
                    $color = '#10b981';
                    $jamMasuk = $att->check_in ? \Carbon\Carbon::parse($att->check_in)->format('H:i') : '-';
                    $jamKeluar = $att->check_out ? \Carbon\Carbon::parse($att->check_out)->format('H:i') : '-';
                    $title = $keg->nama . ' - Lengkap (In: ' . $jamMasuk . ', Out: ' . $jamKeluar . ')';
                  }
                @endphp
                <div style="width: 14px; height: 14px; background-color: {{ $color }}; border-radius: 50%; display:inline-block; border: 1px solid rgba(0,0,0,0.1);" title="{{ $title }}"></div>
              @endforeach
              @if($allKegiatan->isEmpty())
                <span style="font-size:12px;color:#9ca3af">Belum ada kegiatan</span>
              @endif
            </div>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <button onclick="showQrModal('{{ $m->id }}', '{{ addslashes($m->name) }}')" class="btn btn-ghost btn-sm" title="Lihat QR Code">
                <span class="material-symbols-outlined" style="font-size:16px">qr_code_2</span>
              </button>
              <button onclick="openEditMhs('{{ $m->id }}', '{{ addslashes($m->name) }}', '{{ $m->kompi }}', '{{ $m->jurusan }}', '{{ $m->prodi }}', '{{ $m->email }}', '{{ $m->no_telp_mahasiswa }}', '{{ $m->no_telp_ortu }}', '{{ $m->tanggal_lahir ? \Carbon\Carbon::parse($m->tanggal_lahir)->format('d/m/Y') : '' }}')" class="btn btn-ghost btn-sm" title="Edit">
                <span class="material-symbols-outlined" style="font-size:16px">edit</span>
              </button>
              <form method="POST" action="{{ route($mahasiswaDestroyRoute, $m->id) }}" onsubmit="return confirm('Hapus mahasiswa {{ $m->name }}?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm" title="Hapus" style="color:var(--danger)">
                  <span class="material-symbols-outlined" style="font-size:16px">delete</span>
                </button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada data mahasiswa</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  
  <div style="margin-top: 16px;">
    {{ $mahasiswaList->links('vendor.pagination.custom') }}
  </div>

  {{-- Legenda warna titik --}}
  <div style="margin-top:12px;display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:var(--text-muted);align-items:center;padding:10px 16px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm)">
    <span style="font-weight:600;color:var(--text)">Keterangan Status Kegiatan:</span>
    <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#10b981;display:inline-block"></span>Lengkap / Hadir</span>
    <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#1f2937;display:inline-block"></span>Masuk (belum keluar)</span>
    <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#ef4444;display:inline-block"></span>Alpha</span>
    <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#3b82f6;display:inline-block"></span>Izin</span>
    <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#eab308;display:inline-block"></span>Sakit</span>
    <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#d1d5db;display:inline-block"></span>Belum ada</span>
  </div>
</section>

{{-- Modal Tambah Mahasiswa --}}
<div class="modal-backdrop" id="modal-add-mhs">
  <div class="modal">
    <div class="modal-title">Tambah Mahasiswa</div>
    <form method="POST" action="{{ route($mahasiswaStoreRoute) }}">
      @csrf
      <div class="form-row-2">
        <div class="form-row">
          <label class="form-label">Nomor Registrasi *</label>
          <input name="id" class="form-input" value="{{ old('id') }}" required>
          <small style="font-size:11px;color:var(--text-muted)">Dipakai sebagai username login mahasiswa.</small>
        </div>
        <div class="form-row">
          <label class="form-label">Nama Lengkap *</label>
          <input name="name" class="form-input" value="{{ old('name') }}" required>
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-row">
          <label class="form-label">Kompi *</label>
          <select name="kompi" class="form-input" required>
            <option value="">Pilih Kompi...</option>
            @foreach($kompiOptions as $k)
              <option value="{{ $k }}" {{ old('kompi') == $k ? 'selected' : '' }}>{{ $k }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-row">
          <label class="form-label">Jurusan *</label>
          <select name="jurusan" id="add-jurusan" class="form-input" required onchange="updateProdiOptions('add')">
            <option value="">Pilih Jurusan...</option>
            @foreach($jurusanWithProdi as $j)
              <option value="{{ $j->nama }}" data-prodi="{{ json_encode($j->prodi->pluck('nama')) }}">{{ $j->nama }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="form-row">
        <label class="form-label">Prodi</label>
        <select name="prodi" id="add-prodi" class="form-input">
          <option value="">Pilih Prodi...</option>
        </select>
      </div>
      <div class="form-row"><label class="form-label">Tanggal Lahir *</label><input type="text" name="tanggal_lahir" class="form-input" placeholder="DD/MM/YYYY (contoh: 13/01/2008)" required>
        <small style="font-size:11px;color:var(--text-muted)">Format DD/MM/YYYY atau DDMMYYYY (digunakan sebagai password default)</small>
      </div>
      <div class="form-row"><label class="form-label">Email</label><input type="email" name="email" class="form-input" value="{{ old('email') }}"></div>
      <div class="form-row-2">
        <div class="form-row"><label class="form-label">No Telp Mahasiswa</label><input name="no_telp_mahasiswa" class="form-input" value="{{ old('no_telp_mahasiswa') }}"></div>
        <div class="form-row"><label class="form-label">No Telp Ortu</label><input name="no_telp_ortu" class="form-input" value="{{ old('no_telp_ortu') }}"></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Edit Mahasiswa --}}
<div class="modal-backdrop" id="modal-edit-mhs">
  <div class="modal">
    <div class="modal-title">Edit Mahasiswa</div>
    <form method="POST" id="edit-mhs-form">
      @csrf @method('PUT')
      <div class="form-row-2">
        <div class="form-row"><label class="form-label">Nama Lengkap *</label><input name="name" id="edit-name" class="form-input" required></div>
        <div class="form-row">
          <label class="form-label">Nomor Registrasi</label>
          <input id="edit-id-display" class="form-input" style="background:var(--bg);cursor:not-allowed;font-family:monospace" disabled>
          <small style="font-size:11px;color:var(--text-muted)">Digunakan sebagai username login dan tidak dapat diubah.</small>
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-row">
          <label class="form-label">Kompi *</label>
          <select name="kompi" id="edit-kompi" class="form-input" required>
            <option value="">Pilih Kompi...</option>
            @foreach($kompiOptions as $k)
              <option value="{{ $k }}">{{ $k }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-row">
          <label class="form-label">Jurusan *</label>
          <select name="jurusan" id="edit-jurusan" class="form-input" required onchange="updateProdiOptions('edit')">
            <option value="">Pilih Jurusan...</option>
            @foreach($jurusanWithProdi as $j)
              <option value="{{ $j->nama }}" data-prodi="{{ json_encode($j->prodi->pluck('nama')) }}">{{ $j->nama }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="form-row">
        <label class="form-label">Prodi</label>
        <select name="prodi" id="edit-prodi" class="form-input">
          <option value="">Pilih Prodi...</option>
        </select>
      </div>
      <div class="form-row-2">
        <div class="form-row"><label class="form-label">Tanggal Lahir</label><input type="text" name="tanggal_lahir" id="edit-tanggal-lahir" class="form-input" placeholder="DD/MM/YYYY (contoh: 13/01/2008)"></div>
        <div class="form-row"><label class="form-label">Email</label><input type="email" name="email" id="edit-email" class="form-input"></div>
      </div>
      <div class="form-row-2">
        <div class="form-row"><label class="form-label">No Telp Mahasiswa</label><input name="no_telp_mahasiswa" id="edit-telp-mhs" class="form-input"></div>
        <div class="form-row"><label class="form-label">No Telp Ortu</label><input name="no_telp_ortu" id="edit-telp-ortu" class="form-input"></div>
      </div>

      {{-- Seksi Reset Password --}}
      <div style="border-top:1px solid var(--border);margin-top:16px;padding-top:14px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
          <div style="font-size:13px;font-weight:600;color:var(--text-muted)">🔑 Reset Password</div>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
            <input type="checkbox" id="toggle-reset-pass" onchange="document.getElementById('reset-pass-section').style.display=this.checked?'block':'none'">
            Aktifkan reset password
          </label>
        </div>
        <div id="reset-pass-section" style="display:none">
          <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;padding:10px 14px;margin-bottom:10px;font-size:12px;color:#78350f">
            <strong>⚠️ Password baru</strong> akan langsung aktif. Bisa diisi tanggal lahir (format ddmmyyyy) untuk reset ke default.
          </div>
          <div class="form-row">
            <label class="form-label">Password Baru</label>
            <input type="password" name="new_password" id="edit-new-password" class="form-input" placeholder="Minimal 6 karakter" minlength="6">
          </div>
          <div class="form-row">
            <label class="form-label">Konfirmasi Password Baru</label>
            <input type="password" name="new_password_confirmation" id="edit-confirm-password" class="form-input" placeholder="Ulangi password baru">
          </div>
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Import CSV -->
<div class="modal-backdrop" id="modal-import-csv">
  <div class="modal">
    <div class="modal-title">Import Data dari Excel/CSV</div>
    <form method="POST" action="{{ route($mahasiswaImportRoute) }}" enctype="multipart/form-data" id="form-import-excel">
      @csrf
      <div class="form-row">
        <label class="form-label">Pilih File (.csv, .xls, .xlsx)</label>
        
        <a href="{{ route($mahasiswaImportTemplateRoute) }}" class="btn btn-ghost btn-sm" style="margin-bottom:12px;display:inline-block;border:1px solid var(--border)">
          <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Download Template CSV
        </a>

        <input type="file" name="csv_file" id="file-import" class="form-input" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required style="padding:10px">
        <span class="form-hint" style="margin-top:8px;display:block">
          <strong>Format Header:</strong><br>
          Nomor Registrasi, Nama, Kompi (Opsional), Jurusan, Prodi, Tanggal Lahir (YYYY-MM-DD), Email (Opsional), Telp Mhs (Opsional), Telp Ortu (Opsional)<br><br>
          <i>*Nomor Registrasi wajib ada di file dan wajib terisi di setiap baris.</i><br>
          <i>*Jika Kompi kosong atau tidak ada, sistem akan mengisi <strong>-</strong>.</i><br>
          <i>*Jurusan dan Prodi akan dicek ke master data. Jika belum ada, sistem akan menambahkannya otomatis.</i><br>
          <i>*File bisa berupa CSV atau Excel (.xls/.xlsx).</i>
        </span>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary"><span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">upload</span> Upload & Proses</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal QR Code -->
<div class="modal-backdrop" id="modal-qr">
  <div class="modal" style="max-width:900px;text-align:center">
    <div class="modal-title" style="margin-bottom:20px">Kartu Mahasiswa</div>
    
    <div id="qr-loading" style="padding:40px;color:var(--text-muted)">
        <span class="material-symbols-outlined" style="animation:spin 1s linear infinite">refresh</span>
        <div style="margin-top:8px">Memuat Kartu...</div>
    </div>
    
    <div id="qr-content" style="display:none; width:100%; flex-direction:column; align-items:center;">
        <!-- Card Wrapper for 2 cards -->
        <div id="admin-card-wrapper" style="display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; width: 100%; margin-bottom: 20px;">
            
            <!-- KARTU DEPAN -->
            <div class="admin-card-container" style="position: relative; width: 100%; max-width: 380px; aspect-ratio: 1099/1537;">
                <div id="admin-card-depan" style="width: 1099px; height: 1537px; position: absolute; top: 0; left: 0; transform-origin: top left; background-color: white; border-radius: 30px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 2px solid var(--border-light);">
                    <!-- Dynamic background will be set via JS -->
                    
                    <!-- QR CODE - Center Tengah -->
                    <div style="position: absolute; top: 56%; left: 50%; z-index: 5; background: transparent; transform: translate(-50%, -50%);">
                        <div id="admin-qr-container" style="transform: scale(2.5); transform-origin: center center; background: transparent;"></div>
                    </div>

                    <!-- FOTO - Center Atas -->
                    <div id="admin-photo-container" style="position: absolute; top: 18%; left: 50%; width: 280px; height: 280px; z-index: 5; transform: translateX(-50%); display: none;">
                        <!-- White border circle -->
                        <div style="position: absolute; width: 320px; height: 320px; border-radius: 50%; background: white; box-shadow: 0 12px 32px rgba(0,0,0,0.15);"></div>
                        <!-- Image container -->
                        <div style="position: absolute; width: 300px; height: 300px; top: 10px; left: 10px; border-radius: 50%; background: white; overflow: hidden;">
                            <img id="admin-photo-img" src="" alt="Foto" crossorigin="anonymous" style="width: 300px; height: 300px; object-fit: cover; object-position: center; display: block; border-radius: 50%;">
                        </div>
                    </div>
                    
                    <!-- NAMA - Center Bawah -->
                    <div style="position: absolute; top: 71.5%; left: 0; right: 0; text-align: center; padding: 0 60px; z-index: 10;">
                        <div id="admin-mhs-name" style="font-size: 48px; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: -0.5px; line-height: 1.15; word-break: break-word;"></div>
                    </div>

                    <!-- KOMPI | PRODI - Center Bawah -->
                    <div style="position: absolute; top: 76%; left: 0; right: 0; text-align: center; padding: 0 60px; z-index: 10;">
                        <div id="admin-mhs-info" style="font-size: 39.5px; font-weight: 600; color: #334155; letter-spacing: 0.3px; line-height: 1.3;"></div>
                    </div>
                </div>
            </div>
            
            <!-- KARTU BELAKANG -->
            <div class="admin-card-container" style="position: relative; width: 100%; max-width: 380px; aspect-ratio: 1099/1537;">
                <div id="admin-card-belakang" style="width: 1099px; height: 1537px; position: absolute; top: 0; left: 0; transform-origin: top left; background-color: white; border-radius: 30px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 2px solid var(--border-light);">
                    <!-- Dynamic background will be set via JS -->
                </div>
            </div>
            
        </div>
        
        <div class="modal-actions" style="justify-content:center; width:100%">
            <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Tutup</button>
            <button id="btn-download-qr" type="button" class="btn btn-primary" onclick="downloadAdminQR()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Download Kartu
            </button>
        </div>
    </div>
  </div>
</div>

<!-- Modal File Error -->
<div class="modal-backdrop" id="modal-file-error" style="z-index: 9999;">
  <div class="modal" style="max-width: 400px; text-align: center;">
    <div style="color: var(--danger); font-size: 48px; margin-bottom: 16px;">
      <span class="material-symbols-outlined" style="font-size: 48px;">error</span>
    </div>
    <h3 style="margin-top: 0; color: var(--text-main);">Format Tidak Didukung</h3>
    <p style="color: var(--text-muted); margin-bottom: 24px;">Format file tidak didukung. Harap upload file berekstensi .csv, .xls, atau .xlsx</p>
    <button type="button" class="btn btn-primary" style="width: 100%;" onclick="document.getElementById('modal-file-error').classList.remove('show')">Mengerti</button>
  </div>
</div>

<style>
@keyframes spin { 100% { transform: rotate(360deg); } }

/* Responsive behavior for admin modal */
@media (max-width: 900px) {
    #admin-card-wrapper {
        flex-direction: column !important;
        align-items: center;
    }
    
    .admin-card-container {
        max-width: 100% !important;
        width: 90% !important;
    }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
const mahasiswaManagementBaseUrl = @json($mahasiswaQrJsonBaseUrl);

function openEditMhs(id, name, kompi, jurusan, prodi, email, telpMhs, telpOrtu, tglLahir) {
  document.getElementById('edit-mhs-form').action = mahasiswaManagementBaseUrl + '/' + id;
  document.getElementById('edit-id-display').value = id;
  document.getElementById('edit-name').value = name;
  document.getElementById('edit-kompi').value = kompi;
  document.getElementById('edit-jurusan').value = jurusan;
  
  updateProdiOptions('edit', prodi);
  
  document.getElementById('edit-email').value = email;
  if (tglLahir && tglLahir.includes('-')) {
    const parts = tglLahir.split('-');
    if (parts.length === 3) {
      tglLahir = `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
  }
  document.getElementById('edit-tanggal-lahir').value = tglLahir;
  document.getElementById('edit-telp-mhs').value = telpMhs;
  document.getElementById('edit-telp-ortu').value = telpOrtu;

  // Reset password section
  document.getElementById('toggle-reset-pass').checked = false;
  document.getElementById('reset-pass-section').style.display = 'none';
  document.getElementById('edit-new-password').value = '';
  document.getElementById('edit-confirm-password').value = '';

  document.getElementById('modal-edit-mhs').classList.add('show');
}

function resizeAdminCard() {
    const containers = document.querySelectorAll('.admin-card-container');
    containers.forEach(container => {
        const card = container.querySelector('[id^="admin-card-"]');
        if(container && card) {
            const scale = container.offsetWidth / 1099;
            card.style.transform = `scale(${scale})`;
        }
    });
}
window.addEventListener('resize', resizeAdminCard);

function showQrModal(id, name) {
  document.getElementById('modal-qr').classList.add('show');
  document.getElementById('qr-loading').style.display = 'block';
  document.getElementById('qr-content').style.display = 'none';
  
  window.activeQrId = id;
  window.activeQrName = name;
  
  fetch(mahasiswaManagementBaseUrl + '/' + id + '/qr-json')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        // Set QR Code
        document.getElementById('admin-qr-container').innerHTML = data.data.qr_svg;
        
        // Set Nama
        document.getElementById('admin-mhs-name').textContent = data.data.name;
        
        // Set Kompi & Prodi
        document.getElementById('admin-mhs-info').textContent = data.data.kompi + ' | ' + data.data.prodi;
        
        // Set Photo
        if (data.data.photo_path) {
          document.getElementById('admin-photo-img').src = data.data.photo_path;
          document.getElementById('admin-photo-container').style.display = 'block';
        } else {
          document.getElementById('admin-photo-container').style.display = 'none';
        }
        
        // Set Background Templates
        document.getElementById('admin-card-depan').style.backgroundImage = `url('${data.data.template_depan}')`;
        document.getElementById('admin-card-depan').style.backgroundSize = 'cover';
        document.getElementById('admin-card-depan').style.backgroundPosition = 'center';
        document.getElementById('admin-card-depan').style.backgroundRepeat = 'no-repeat';
        
        document.getElementById('admin-card-belakang').style.backgroundImage = `url('${data.data.template_belakang}')`;
        document.getElementById('admin-card-belakang').style.backgroundSize = 'cover';
        document.getElementById('admin-card-belakang').style.backgroundPosition = 'center';
        document.getElementById('admin-card-belakang').style.backgroundRepeat = 'no-repeat';
        
        document.getElementById('qr-loading').style.display = 'none';
        document.getElementById('qr-content').style.display = 'flex';
        
        setTimeout(resizeAdminCard, 100);
      } else {
        alert(data.message || 'Gagal memuat Kartu Mahasiswa');
        document.getElementById('modal-qr').classList.remove('show');
      }
    })
    .catch(err => {
      console.error(err);
      alert('Terjadi kesalahan saat memuat Kartu Mahasiswa');
      document.getElementById('modal-qr').classList.remove('show');
    });
}

function downloadAdminQR() {
    const cardDepan = document.getElementById('admin-card-depan');
    const cardBelakang = document.getElementById('admin-card-belakang');
    const btn = document.getElementById('btn-download-qr');
    
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;animation:spin 1s linear infinite">refresh</span> Memproses...';
    btn.style.opacity = '0.8';
    btn.disabled = true;
    
    // Temporarily remove transforms and styles for clean export
    const elements = [cardDepan, cardBelakang];
    const originalStyles = elements.map(el => ({
        transform: el.style.transform,
        boxShadow: el.style.boxShadow,
        border: el.style.border
    }));
    
    elements.forEach(el => {
        el.style.transform = 'none';
        el.style.boxShadow = 'none';
        el.style.border = 'none';
    });
    
    // Create a temporary container for side-by-side export
    const exportContainer = document.createElement('div');
    exportContainer.style.display = 'flex';
    exportContainer.style.gap = '20px';
    exportContainer.style.width = (1099 * 2 + 20) + 'px';
    exportContainer.style.height = '1537px';
    exportContainer.style.position = 'absolute';
    exportContainer.style.left = '-9999px';
    exportContainer.style.backgroundColor = '#ffffff';
    
    // Clone cards
    const cloneDepan = cardDepan.cloneNode(true);
    const cloneBelakang = cardBelakang.cloneNode(true);
    cloneDepan.style.position = 'relative';
    cloneBelakang.style.position = 'relative';
    
    exportContainer.appendChild(cloneDepan);
    exportContainer.appendChild(cloneBelakang);
    document.body.appendChild(exportContainer);
    
    html2canvas(exportContainer, {
        scale: 1,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false,
        imageTimeout: 0,
        width: 1099 * 2 + 20,
        height: 1537
    }).then(canvas => {
        // Restore styles
        elements.forEach((el, idx) => {
            el.style.transform = originalStyles[idx].transform;
            el.style.boxShadow = originalStyles[idx].boxShadow;
            el.style.border = originalStyles[idx].border;
        });
        
        // Remove temporary container
        document.body.removeChild(exportContainer);
        
        // Download
        const link = document.createElement('a');
        let safeName = window.activeQrName ? window.activeQrName.replace(/[^a-z0-9]/gi, '_').toLowerCase() : 'mhs';
        link.download = 'Kartu_Lengkap_' + window.activeQrId + '_' + safeName + '.png';
        link.href = canvas.toDataURL('image/png', 1.0);
        link.click();
        
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.disabled = false;
    }).catch(err => {
        console.error("Error generating image: ", err);
        alert("Gagal mengunduh kartu. Pastikan template tersedia dan foto sudah terupload.");
        
        // Restore styles on error
        elements.forEach((el, idx) => {
            el.style.transform = originalStyles[idx].transform;
            el.style.boxShadow = originalStyles[idx].boxShadow;
            el.style.border = originalStyles[idx].border;
        });
        
        // Remove temporary container
        if (document.body.contains(exportContainer)) {
            document.body.removeChild(exportContainer);
        }
        
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.disabled = false;
    });
}

const jurusanProdiData = @json($jurusanWithProdi);

function updateProdiOptions(prefix, selectedProdi = null) {
  const jurusanSelect = document.getElementById(prefix + '-jurusan');
  const prodiSelect = document.getElementById(prefix + '-prodi');
  
  prodiSelect.innerHTML = '<option value="">Pilih Prodi...</option>';
  
  const selectedOption = jurusanSelect.options[jurusanSelect.selectedIndex];
  if (selectedOption && selectedOption.value) {
    const prodiList = JSON.parse(selectedOption.getAttribute('data-prodi') || '[]');
    prodiList.forEach(prodi => {
      const opt = document.createElement('option');
      opt.value = prodi;
      opt.textContent = prodi;
      if (prodi === selectedProdi) opt.selected = true;
      prodiSelect.appendChild(opt);
    });
  }
}

function updateFilterProdi() {
  const jurusanSelect = document.getElementById('filter-jurusan');
  const prodiSelect = document.getElementById('filter-prodi');
  const selectedProdi = '{{ request('prodi') }}';
  
  prodiSelect.innerHTML = '<option value="">Semua Prodi</option>';
  
  const selectedOption = jurusanSelect.options[jurusanSelect.selectedIndex];
  if (selectedOption && selectedOption.value) {
    prodiSelect.disabled = false;
    const prodiList = JSON.parse(selectedOption.getAttribute('data-prodi') || '[]');
    prodiList.forEach(prodi => {
      const opt = document.createElement('option');
      opt.value = prodi;
      opt.textContent = prodi;
      if (prodi === selectedProdi) opt.selected = true;
      prodiSelect.appendChild(opt);
    });
  } else {
    prodiSelect.disabled = true;
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const fileImport = document.getElementById('file-import');
  if (fileImport) {
    fileImport.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const validExts = ['.csv', '.xls', '.xlsx'];
            const fileName = file.name.toLowerCase();
            const isValid = validExts.some(ext => fileName.endsWith(ext));
            
            if (!isValid) {
                document.getElementById('modal-file-error').classList.add('show');
                e.target.value = ''; // clear the input
                const fileNameDisplay = document.getElementById('file-name-display');
                if (fileNameDisplay) fileNameDisplay.textContent = 'Belum ada file dipilih';
            }
        }
    });
  }

  updateFilterProdi();

  @if(session('success'))
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: @json(session('success')),
        timer: 3500,
        showConfirmButton: false
      });
    }
  @endif

  @if(session('error'))
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'error',
        title: 'Gagal Import Data',
        text: @json(session('error')),
        confirmButtonColor: '#ef4444'
      });
    }
  @endif

  const importForm = document.getElementById('form-import-excel');
  if (importForm) {
    importForm.addEventListener('submit', function(e) {
      const fileInput = this.querySelector('input[name="csv_file"]');
      if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        return;
      }

      const fileName = fileInput.files[0].name;
      const modalCsv = document.getElementById('modal-import-csv');
      if (modalCsv) modalCsv.classList.remove('show');

      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Memproses Import Data...',
          html: `
            <div style="text-align:center;padding:10px 0">
              <div style="font-size:14px;color:#475569;margin-bottom:12px">
                File: <strong>${fileName}</strong>
              </div>
              <div style="font-size:13px;color:#64748b;margin-bottom:16px">
                Sistem sedang membaca, memvalidasi, dan meng-import baris data ke database...
              </div>
              <div style="width:100%;height:12px;background:#e2e8f0;border-radius:6px;overflow:hidden;position:relative;margin-bottom:12px">
                <div id="import-progress-bar" style="width:15%;height:100%;background:linear-gradient(90deg, #3b82f6, #6366f1);border-radius:6px;transition:width 0.3s ease;"></div>
              </div>
              <div id="import-status-text" style="font-size:12px;font-weight:600;color:#3b82f6">Memulai pengolahan data... 15%</div>
              <small style="display:block;margin-top:14px;color:#94a3b8">Mohon tunggu dan jangan menutup halaman saat proses berlangsung.</small>
            </div>
          `,
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
          didOpen: () => {
            let progress = 15;
            const bar = document.getElementById('import-progress-bar');
            const txt = document.getElementById('import-status-text');
            const interval = setInterval(() => {
              if (progress < 94) {
                progress += Math.floor(Math.random() * 5) + 2;
                if (progress > 94) progress = 94;
                if (bar) bar.style.width = progress + '%';
                if (txt) txt.textContent = `Mengimpor & menyimpan data... ${progress}%`;
              }
            }, 250);
          }
        });
      }
    });
  }
});
</script>
@endsection
