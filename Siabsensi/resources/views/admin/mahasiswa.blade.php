@extends('layouts.admin')
@section('title', 'Data Mahasiswa — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Data Mahasiswa</div>
      <div class="page-sub">Manajemen data mahasiswa ({{ $mahasiswaList->count() }} total)</div>
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
    <form method="GET" action="{{ route('admin.mahasiswa') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1;min-width:200px">
        <label class="form-label">Cari Nama</label>
        <input type="text" name="search" class="form-input" placeholder="Ketik nama..." value="{{ request('search') }}" style="padding:7px 10px">
      </div>
      <div>
        <label class="form-label">Kompi</label>
        <select name="kompi" class="form-input" style="width:120px;padding:7px 10px">
          <option value="">Semua</option>
          @foreach($kompiOptions as $k)<option value="{{ $k }}" {{ request('kompi') == $k ? 'selected' : '' }}>{{ $k }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="form-label">Jurusan</label>
        <select name="jurusan" class="form-input" style="width:180px;padding:7px 10px">
          <option value="">Semua</option>
          @foreach($jurusanOptions as $j)<option value="{{ $j }}" {{ request('jurusan') == $j ? 'selected' : '' }}>{{ $j }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="form-label">Prodi</label>
        <select name="prodi" class="form-input" style="width:180px;padding:7px 10px">
          <option value="">Semua</option>
          @foreach($prodiOptions as $p)<option value="{{ $p }}" {{ request('prodi') == $p ? 'selected' : '' }}>{{ $p }}</option>@endforeach
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="{{ route('admin.mahasiswa') }}" class="btn btn-ghost btn-sm">Reset</a>
    </form>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>Mahasiswa</th><th>Kompi</th><th>Prodi</th><th>Email</th><th>Status Kegiatan</th><th>Aksi</th></tr></thead>
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
          <td style="font-size:13px">{{ $m->prodi ?? '-' }}</td>
          <td style="font-size:13px;color:var(--text-muted)">{{ $m->email ?? '-' }}</td>
          <td>
            <div style="display:flex;gap:4px">
              @foreach($allKegiatan as $keg)
                @php
                  $att = $m->attendances->where('kegiatan_id', $keg->id)->first();
                  if(!$att) {
                    // Alpha (Merah)
                    $color = '#ef4444';
                    $title = $keg->nama . ' - Alpha';
                  } else if(!$att->check_out) {
                    // Baru Masuk (Hitam)
                    $color = '#1f2937';
                    $title = $keg->nama . ' - Masuk (Belum Keluar)';
                  } else {
                    // Lengkap (Hijau)
                    $color = '#10b981';
                    $title = $keg->nama . ' - Hadir Penuh';
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
              <button onclick="openEditMhs('{{ $m->id }}', '{{ $m->nim ?? '' }}', '{{ addslashes($m->name) }}', '{{ $m->kompi }}', '{{ $m->jurusan }}', '{{ $m->prodi }}', '{{ $m->email }}', '{{ $m->no_telp_mahasiswa }}', '{{ $m->no_telp_ortu }}', '{{ $m->tanggal_lahir ? Carbon\Carbon::parse($m->tanggal_lahir)->format('Y-m-d') : '' }}')" class="btn btn-ghost btn-sm" title="Edit">
                <span class="material-symbols-outlined" style="font-size:16px">edit</span>
              </button>
              <form method="POST" action="{{ route('admin.mahasiswa.destroy', $m->id) }}" onsubmit="return confirm('Hapus mahasiswa {{ $m->name }}?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm" title="Hapus" style="color:var(--danger)">
                  <span class="material-symbols-outlined" style="font-size:16px">delete</span>
                </button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada data mahasiswa</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  
  <div style="margin-top: 16px;">
    {{ $mahasiswaList->links('pagination::bootstrap-4') }}
  </div>
</section>

{{-- Modal Tambah Mahasiswa --}}
<div class="modal-backdrop" id="modal-add-mhs">
  <div class="modal">
    <div class="modal-title">Tambah Mahasiswa</div>
    <form method="POST" action="{{ route('admin.mahasiswa.store') }}">
      @csrf
      <div class="form-row"><label class="form-label">Nama Lengkap *</label><input name="name" class="form-input" required></div>
      <div class="form-row-2">
        <div class="form-row">
          <label class="form-label">Kompi *</label>
          <select name="kompi" class="form-input" required>
            <option value="">Pilih Kompi...</option>
            @foreach($kompiOptions as $k)
              <option value="{{ $k }}">{{ $k }}</option>
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
      <div class="form-row"><label class="form-label">Tanggal Lahir *</label><input type="date" name="tanggal_lahir" class="form-input" required>
        <small style="font-size:11px;color:var(--text-muted)">Digunakan sebagai password default (ddmmyyyy)</small>
      </div>
      <div class="form-row"><label class="form-label">Email</label><input type="email" name="email" class="form-input"></div>
      <div class="form-row-2">
        <div class="form-row"><label class="form-label">No Telp Mahasiswa</label><input name="no_telp_mahasiswa" class="form-input"></div>
        <div class="form-row"><label class="form-label">No Telp Ortu</label><input name="no_telp_ortu" class="form-input"></div>
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
        <div class="form-row"><label class="form-label">Nomor Registrasi *</label><input name="nim" id="edit-nim" class="form-input" required></div>
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
        <div class="form-row"><label class="form-label">Tanggal Lahir</label><input type="date" name="tanggal_lahir" id="edit-tanggal-lahir" class="form-input"></div>
        <div class="form-row"><label class="form-label">Email</label><input type="email" name="email" id="edit-email" class="form-input"></div>
      </div>
      <div class="form-row-2">
        <div class="form-row"><label class="form-label">No Telp Mahasiswa</label><input name="no_telp_mahasiswa" id="edit-telp-mhs" class="form-input"></div>
        <div class="form-row"><label class="form-label">No Telp Ortu</label><input name="no_telp_ortu" id="edit-telp-ortu" class="form-input"></div>
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
    <form method="POST" action="{{ route('admin.mahasiswa.import') }}" enctype="multipart/form-data">
      @csrf
      <div class="form-row">
        <label class="form-label">Pilih File (.csv)</label>
        
        <a href="{{ route('admin.mahasiswa.import.template') }}" class="btn btn-ghost btn-sm" style="margin-bottom:12px;display:inline-block;border:1px solid var(--border)">
          <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Download Template CSV
        </a>

        <input type="file" name="csv_file" class="form-input" accept=".csv" required style="padding:10px">
        <span class="form-hint" style="margin-top:8px;display:block">
          <strong>Format Header:</strong><br>
          Nama, Jurusan, Prodi, Tanggal Lahir (YYYY-MM-DD), Email (Opsional), Telp Mhs (Opsional), Telp Ortu (Opsional)<br><br>
          <i>*Pastikan menyimpan file Excel Anda dalam format .csv (Comma Separated Values).</i><br>
          <i>*Kompi akan otomatis diatur menjadi "-" dan Anda bisa membaginya lewat menu Pengaturan Kompi.</i>
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
  <div class="modal" style="max-width:400px;text-align:center">
    <div class="modal-title" style="margin-bottom:20px">QR Code Mahasiswa</div>
    
    <div id="qr-loading" style="padding:40px;color:var(--text-muted)">
        <span class="material-symbols-outlined" style="animation:spin 1s linear infinite">refresh</span>
        <div style="margin-top:8px">Memuat QR Code...</div>
    </div>
    
    <div id="qr-content" style="display:none; width:100%; flex-direction:column; align-items:center;">
        <div id="card-wrapper" style="position: relative; width: 100%; max-width: 320px; aspect-ratio: 957/1650; margin-bottom: 20px;">
            <div id="id-card" style="width: 957px; height: 1650px; position: absolute; top: 0; left: 0; transform-origin: top left; background: url('{{ asset('static/img/template_qr.png') }}') center/cover no-repeat; background-color: white; border-radius: 30px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 2px solid var(--border-light);">
              <!-- Area Nama -->
              <div style="position: absolute; top: 48%; left: 0; right: 0; text-align: center; padding: 0 40px; z-index: 10;">
                <div id="qr-mhs-name" style="font-size: 45px; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: -1px; line-height: 1.2; word-break: break-word;"></div>
              </div>
              <!-- Area QR (Di bawah garis) -->
              <div style="position: absolute; top: 62%; left: 50%; transform: translateX(-50%); display: flex; justify-content: center; align-items: center; z-index: 5;">
                <div id="qr-svg-container" style="transform: scale(2.6); transform-origin: center center; mix-blend-mode: multiply;">
                </div>
              </div>
            </div>
        </div>
        
        <div class="modal-actions" style="justify-content:center; width:100%">
            <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Tutup</button>
            <button id="btn-download-qr" type="button" class="btn btn-primary" onclick="downloadAdminQR()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Download Image
            </button>
        </div>
    </div>
  </div>
</div>

<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function openEditMhs(id, nim, name, kompi, jurusan, prodi, email, telpMhs, telpOrtu, tglLahir) {
  document.getElementById('edit-mhs-form').action = '/admin/mahasiswa/' + id;
  document.getElementById('edit-nim').value = nim;
  document.getElementById('edit-name').value = name;
  document.getElementById('edit-kompi').value = kompi;
  document.getElementById('edit-jurusan').value = jurusan;
  
  updateProdiOptions('edit');
  document.getElementById('edit-prodi').value = prodi;
  
  document.getElementById('edit-email').value = email;
  document.getElementById('edit-tanggal-lahir').value = tglLahir;
  document.getElementById('edit-telp-mhs').value = telpMhs;
  document.getElementById('edit-telp-ortu').value = telpOrtu;
  document.getElementById('modal-edit-mhs').classList.add('show');
}

function resizeAdminCard() {
    const wrapper = document.getElementById('card-wrapper');
    const card = document.getElementById('id-card');
    if(wrapper && card) {
        const scale = wrapper.offsetWidth / 957;
        card.style.transform = `scale(${scale})`;
    }
}
window.addEventListener('resize', resizeAdminCard);

function showQrModal(id, name) {
  document.getElementById('modal-qr').classList.add('show');
  document.getElementById('qr-loading').style.display = 'block';
  document.getElementById('qr-content').style.display = 'none';
  
  document.getElementById('qr-mhs-name').textContent = name;
  
  window.activeQrId = id;
  window.activeQrName = name;
  
  fetch('/admin/mahasiswa/' + id + '/qr-json')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        document.getElementById('qr-svg-container').innerHTML = data.data.qr_svg;
        
        document.getElementById('qr-loading').style.display = 'none';
        document.getElementById('qr-content').style.display = 'flex';
        
        setTimeout(resizeAdminCard, 50);
      } else {
        alert('Gagal memuat QR Code');
        document.getElementById('modal-qr').classList.remove('show');
      }
    })
    .catch(err => {
      console.error(err);
      alert('Terjadi kesalahan saat memuat QR Code');
      document.getElementById('modal-qr').classList.remove('show');
    });
}

function downloadAdminQR() {
    const card = document.getElementById('id-card');
    const btn = document.getElementById('btn-download-qr');
    
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;animation:spin 1s linear infinite">refresh</span> Memproses...';
    btn.style.opacity = '0.8';
    btn.disabled = true;
    
    const originalTransform = card.style.transform;
    const originalRadius = card.style.borderRadius;
    const originalShadow = card.style.boxShadow;
    const originalBorder = card.style.border;
    
    card.style.transform = 'none';
    card.style.borderRadius = '0';
    card.style.boxShadow = 'none';
    card.style.border = 'none';
    
    html2canvas(card, {
        scale: 1,
        useCORS: true, 
        backgroundColor: '#ffffff',
        logging: false
    }).then(canvas => {
        card.style.transform = originalTransform;
        card.style.borderRadius = originalRadius;
        card.style.boxShadow = originalShadow;
        card.style.border = originalBorder;
        
        const link = document.createElement('a');
        let safeName = window.activeQrName ? window.activeQrName.replace(/[^a-z0-9]/gi, '_').toLowerCase() : 'mhs';
        link.download = 'ID_Card_' + window.activeQrId + '_' + safeName + '.png';
        link.href = canvas.toDataURL('image/png', 1.0);
        link.click();
        
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.disabled = false;
    }).catch(err => {
        console.error("Error generating image: ", err);
        alert("Gagal mengunduh kartu. Pastikan koneksi lancar.");
        
        card.style.transform = originalTransform;
        card.style.borderRadius = originalRadius;
        card.style.boxShadow = originalShadow;
        card.style.border = originalBorder;
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.disabled = false;
    });
}

function updateProdiOptions(prefix) {
  const jurusanSelect = document.getElementById(prefix + '-jurusan');
  const prodiSelect = document.getElementById(prefix + '-prodi');
  
  // Clear current options
  prodiSelect.innerHTML = '<option value="">Pilih Prodi...</option>';
  
  const selectedOption = jurusanSelect.options[jurusanSelect.selectedIndex];
  if (selectedOption && selectedOption.value) {
    const prodiList = JSON.parse(selectedOption.getAttribute('data-prodi') || '[]');
    prodiList.forEach(prodi => {
      const opt = document.createElement('option');
      opt.value = prodi;
      opt.textContent = prodi;
      prodiSelect.appendChild(opt);
    });
  }
}
</script>
@endsection
