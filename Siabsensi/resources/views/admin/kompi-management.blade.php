@extends('layouts.admin')
@section('title', 'Manajemen Kompi — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Pengaturan Kompi</div>
      <div class="page-sub">Ubah kompi mahasiswa secara massal atau acak berdasarkan jurusan</div>
    </div>
    <div style="display:flex; gap: 8px; align-items: center; flex-wrap:wrap;">
        @if($filterKompi && $filterKompi !== 'all' && $filterKompi !== '__empty__')
        <button class="btn btn-secondary btn-sm dl-btn"
            data-url="{!! route('admin.kompi.download', ['kompi' => $filterKompi]) !!}"
            data-format="xlsx" data-label="{{ $filterKompi }}"
            title="Download Excel kompi {{ $filterKompi }}">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span>
            Download Excel – {{ $filterKompi }}
        </button>
        @endif
        <button class="btn btn-secondary btn-sm dl-btn"
            data-url="{!! route('admin.kompi.download') !!}"
            data-format="xlsx" data-label="Semua Kompi"
            title="Download Excel semua kompi">
            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span>
            Download Excel (Semua Kompi)
        </button>
        <form method="POST" action="{{ route('admin.kompi.shuffle') }}" id="shuffle-form">
            @csrf
            <button type="button" class="btn btn-secondary btn-sm" onclick="showConfirmModal()">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">shuffle</span> Acak Otomatis
            </button>
        </form>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;padding:14px 20px">
    <form method="GET" action="{{ route('admin.kompi-management') }}" id="filter-form" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1;min-width:200px"><label class="form-label">Cari Nama</label><input name="search" class="form-input" value="{{ $search ?? '' }}" placeholder="Cari nama mahasiswa..." style="padding:7px 10px"></div>
      <div><label class="form-label">Kompi</label><select name="kompi" class="form-input" style="width:200px;padding:7px 10px">
        <option value="all">Semua Kompi</option>
        @foreach($kompiOptions as $kompiName)
            <option value="{{ $kompiName }}" {{ $filterKompi == $kompiName ? 'selected' : '' }}>{{ $kompiName }}</option>
        @endforeach
        <option value="__empty__" {{ $filterKompi == '__empty__' ? 'selected' : '' }}>Belum Memiliki Kompi</option>
      </select></div>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="{{ route('admin.kompi-management') }}" class="btn btn-ghost btn-sm">Reset</a>
    </form>
  </div>

  <div class="panel">
    <form method="POST" action="{{ route('admin.kompi.bulkUpdate') }}" id="kompi-form">
      @csrf
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <div style="display:flex;gap:12px;align-items:center">
          <label class="form-label" style="margin-bottom:0">Set kompi terpilih ke:</label>
          <select id="bulk-kompi-value" class="form-input" style="width:150px;padding:5px 10px">
            <option value="">Pilih Kompi...</option>
            @foreach($kompiOptions as $k)
              <option value="{{ $k }}">{{ $k }}</option>
            @endforeach
          </select>
          <button type="button" class="btn btn-secondary btn-sm" onclick="applyBulkKompi()">Terapkan ke baris</button>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
      </div>

      <table class="att-table">
        <thead>
          <tr>
            <th style="width:40px"><input type="checkbox" id="check-all" onchange="toggleAll(this)"></th>
            <th>Nama Mahasiswa</th>
            <th>NPM/ID</th>
            <th>Kompi Saat Ini</th>
            <th>Kompi Baru</th>
          </tr>
        </thead>
        <tbody>
          @foreach($mahasiswaList as $i => $m)
          <tr>
            <td><input type="checkbox" class="row-checkbox" value="{{ $i }}"></td>
            <td>{{ $m->name }}</td>
            <td>{{ $m->id }}</td>
            <td>@if($m->kompi && $m->kompi !== '-')<span class="badge badge-blue">{{ $m->kompi }}</span>@else<span class="badge badge-red">Belum ada</span>@endif</td>
            <td>
              <input type="hidden" name="assignments[{{ $i }}][id]" value="{{ $m->id }}">
              <select name="assignments[{{ $i }}][kompi]" id="kompi-input-{{ $i }}" class="form-input" style="padding:4px 8px;width:120px" required>
                @foreach($kompiOptions as $k)
                  <option value="{{ $k }}" {{ $m->kompi == $k ? 'selected' : '' }}>{{ $k }}</option>
                @endforeach
              </select>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      <div style="margin-top: 16px;">
        {{ $mahasiswaList->links('pagination::bootstrap-4') }}
      </div>
    </form>
  </div>
</section>

{{-- ══════════════════════════════════════════════
     DOWNLOAD PROGRESS OVERLAY
══════════════════════════════════════════════ --}}
{{-- PENTING: tidak ada inline style display:none di sini, diatur via CSS class saja --}}
<div id="download-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%;
     background:rgba(10,15,30,0.82); z-index:9997; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
  <div style="background:#fff; border-radius:16px; padding:32px 36px; width:100%; max-width:420px;
              box-shadow:0 20px 60px rgba(0,0,0,0.4); text-align:center;">

    {{-- Icon animasi --}}
    <div id="dl-icon-wrap" style="width:64px; height:64px; border-radius:50%; background:#dbeafe;
         color:#1d4ed8; display:flex; justify-content:center; align-items:center; margin:0 auto 16px; position:relative;">
      <span class="material-symbols-outlined" style="font-size:32px;" id="dl-icon">download</span>
      {{-- Spinner ring --}}
      <svg id="dl-spinner" style="position:absolute;top:0;left:0;width:64px;height:64px;"
           viewBox="0 0 64 64" fill="none">
        <circle cx="32" cy="32" r="28" stroke="#bfdbfe" stroke-width="5"/>
        <circle id="dl-arc" cx="32" cy="32" r="28" stroke="#1d4ed8" stroke-width="5"
                stroke-linecap="round" stroke-dasharray="176" stroke-dashoffset="176"
                style="transform:rotate(-90deg);transform-origin:center;transition:stroke-dashoffset 0.3s ease"/>
      </svg>
    </div>

    <div id="dl-label" style="font-size:15px; font-weight:700; color:#1e293b; margin-bottom:6px;">
      Menyiapkan file...
    </div>
    <div id="dl-sublabel" style="font-size:12px; color:#64748b; margin-bottom:20px;">
      Mohon tunggu, server sedang memproses data
    </div>

    {{-- Progress bar --}}
    <div style="background:#e2e8f0; border-radius:99px; height:10px; overflow:hidden; margin-bottom:8px;">
      <div id="dl-bar" style="height:100%; width:0%; background:linear-gradient(90deg,#2563eb,#60a5fa);
           border-radius:99px; transition:width 0.25s ease;"></div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div id="dl-pct" style="font-size:22px; font-weight:800; color:#1d4ed8; font-family:monospace;">0%</div>
      <div id="dl-size" style="font-size:11px; color:#94a3b8;"></div>
    </div>

    <div id="dl-done-msg" style="display:none; margin-top:14px; padding:10px 14px; background:#dcfce7;
         border-radius:8px; color:#16a34a; font-size:13px; font-weight:600;">
      ✅ File berhasil diunduh!
    </div>
    <div id="dl-error-msg" style="display:none; margin-top:14px; padding:10px 14px; background:#fee2e2;
         border-radius:8px; color:#dc2626; font-size:13px; font-weight:600;">
      ❌ Gagal mengunduh. Coba lagi.
    </div>
  </div>
</div>

{{-- Confirm Modal (shuffle) --}}
<div id="confirm-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9998; flex-direction:column; justify-content:center; align-items:center;">
  <div style="background:#ffffff; padding:24px; border-radius:12px; width:100%; max-width:400px; box-shadow:0 10px 25px rgba(0,0,0,0.3); text-align:center;">
    <div style="width:50px; height:50px; border-radius:50%; background:#ffe4e6; color:#e11d48; display:flex; justify-content:center; align-items:center; margin:0 auto 16px;">
      <span class="material-symbols-outlined" style="font-size:24px;">warning</span>
    </div>
    <h3 style="margin:0 0 12px; font-family:var(--font-sans); font-size:18px; color:var(--text-main);">Acak Seluruh Kompi?</h3>
    <p style="margin:0 0 24px; color:var(--text-muted); font-size:14px; line-height:1.5;">
      Peringatan: Tindakan ini akan mengacak ulang <b>SELURUH DATA MAHASISWA</b> secara permanen ke dalam Kompi yang tersedia berdasarkan jurusan secara merata. Anda yakin ingin melanjutkan?
    </p>
    <div style="display:flex; gap:12px; justify-content:center;">
      <button class="btn btn-ghost" onclick="hideConfirmModal()" style="flex:1;">Batal</button>
      <button class="btn btn-primary" onclick="executeShuffle()" style="flex:1; background:var(--danger); border-color:var(--danger);">Ya, Acak Sekarang</button>
    </div>
  </div>
</div>

{{-- Loading Overlay (shuffle) --}}
<div id="loading-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; flex-direction:column; justify-content:center; align-items:center; color:white;">
  <div class="spinner" style="width: 50px; height: 50px; border: 5px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; margin-bottom: 20px;"></div>
  <h3 style="margin:0; font-family:var(--font-sans); font-size: 20px; font-weight: 600;">Mengacak Seluruh Data Mahasiswa...</h3>
  <p style="margin-top:10px; color:#ddd; font-size: 14px;">Mohon tunggu, proses ini mungkin membutuhkan beberapa saat. Jangan tutup halaman ini.</p>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
/* Overlay dikendalikan hanya via CSS class, bukan inline style */
#download-overlay { display: none !important; }
#download-overlay.visible { display: flex !important; }
</style>

<script>
// ─── Shuffle ────────────────────────────────────────────────
function showConfirmModal() { document.getElementById('confirm-modal').style.display = 'flex'; }
function hideConfirmModal() { document.getElementById('confirm-modal').style.display = 'none'; }
function executeShuffle() {
  hideConfirmModal();
  document.getElementById('loading-overlay').style.display = 'flex';
  document.getElementById('shuffle-form').submit();
}

// ─── Bulk kompi ─────────────────────────────────────────────
function toggleAll(source) {
  document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = source.checked);
}
function applyBulkKompi() {
  let val = document.getElementById('bulk-kompi-value').value.trim();
  if (!val) { alert('Masukkan nilai kompi!'); return; }
  let count = 0;
  document.querySelectorAll('.row-checkbox').forEach(cb => {
    if (cb.checked) {
      document.getElementById('kompi-input-' + cb.value).value = val;
      count++;
    }
  });
  if (count === 0) alert('Pilih setidaknya satu mahasiswa!');
}

// ─── Download buttons: pakai data attributes ─────────────────
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.dl-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      startDownload(this.dataset.url, this.dataset.format, this.dataset.label);
    });
  });
});

async function startDownload(url, format, label) {
  const overlay  = document.getElementById('download-overlay');
  const bar      = document.getElementById('dl-bar');
  const pct      = document.getElementById('dl-pct');
  const sizeEl   = document.getElementById('dl-size');
  const dlLabel  = document.getElementById('dl-label');
  const dlSub    = document.getElementById('dl-sublabel');
  const doneMsg  = document.getElementById('dl-done-msg');
  const errMsg   = document.getElementById('dl-error-msg');
  const arc      = document.getElementById('dl-arc');
  const icon     = document.getElementById('dl-icon');
  const arcLen   = 176; // 2*π*28 ≈ 176

  // Reset UI
  bar.style.width = '0%';
  bar.style.background = 'linear-gradient(90deg,#2563eb,#60a5fa)';
  pct.textContent = '0%';
  pct.style.color = '#1d4ed8';
  sizeEl.textContent = '';
  dlLabel.textContent = 'Menyiapkan Excel...';
  dlSub.textContent   = 'Server sedang memproses data mahasiswa';
  doneMsg.style.display = 'none';
  errMsg.style.display  = 'none';
  arc.style.strokeDashoffset = arcLen;
  arc.style.stroke = '#1d4ed8';
  icon.textContent = 'download';
  icon.style.color = '#1d4ed8';
  document.getElementById('dl-icon-wrap').style.background = '#dbeafe';

  overlay.classList.add('visible');

  try {
    const response = await fetch(url);
    if (!response.ok) {
      // Coba baca pesan error dari backend (JSON atau plain text)
      let errMsg = 'HTTP ' + response.status;
      try {
        const ct = response.headers.get('Content-Type') || '';
        if (ct.includes('application/json')) {
          const json = await response.json();
          errMsg = json.message || errMsg;
        } else {
          const txt = await response.text();
          // ambil baris pertama saja agar tidak terlalu panjang
          errMsg = txt.split('\n')[0].substring(0, 120) || errMsg;
        }
      } catch (_) {}
      throw new Error(errMsg);
    }

    const contentLength = parseInt(response.headers.get('Content-Length') || '0', 10);
    const reader        = response.body.getReader();
    let   received      = 0;
    const chunks        = [];

    // ── Fase 1: Server processing (0% → 15% simulasi selama fetch dimulai)
    let phase1Pct = 0;
    const phase1Interval = setInterval(() => {
      if (phase1Pct < 15) {
        phase1Pct += 1;
        setProgress(phase1Pct, contentLength, received);
      } else {
        clearInterval(phase1Interval);
      }
    }, 60);

    // ── Fase 2: Download streaming yang sesungguhnya
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      chunks.push(value);
      received += value.length;

      clearInterval(phase1Interval);

      if (contentLength > 0) {
        // Progress real berdasarkan bytes
        const real = Math.round(15 + (received / contentLength) * 82); // 15%→97%
        setProgress(Math.min(real, 97), contentLength, received);
      } else {
        // Tidak tahu total size, animasi smooth
        const approx = Math.min(15 + Math.round(Math.log1p(received / 1024) * 12), 90);
        setProgress(approx, 0, received);
      }
    }

    // 100%
    setProgress(100, contentLength, received);
    arc.style.stroke = '#16a34a';
    pct.style.color = '#16a34a';
    bar.style.background = 'linear-gradient(90deg,#16a34a,#4ade80)';
    icon.textContent = 'check_circle';
    icon.style.color = '#16a34a';
    document.getElementById('dl-icon-wrap').style.background = '#dcfce7';
    dlLabel.textContent = 'Download selesai!';
    dlSub.textContent   = `File ${format.toUpperCase()} untuk ${label} berhasil diunduh.`;
    doneMsg.style.display = 'block';

    // Trigger download dengan MIME & ekstensi yang sesuai
    const resCt = response.headers.get('Content-Type') || '';
    const mime  = resCt.includes('csv') ? 'text/csv;charset=utf-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    const ext   = resCt.includes('csv') ? 'csv' : 'xlsx';
    const blob = new Blob(chunks, { type: mime });
    const blobUrl = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const cd = response.headers.get('Content-Disposition') || '';
    const match = cd.match(/filename="?([^"]+)"?/);
    a.href = blobUrl;
    a.download = match ? match[1] : `download.${ext}`;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);

    // Tutup overlay setelah 2 detik
    setTimeout(() => overlay.classList.remove('visible'), 2200);

  } catch (err) {
    console.error('Download error:', err);
    setProgress(0, 0, 0);
    bar.style.background = '#ef4444';
    arc.style.stroke = '#ef4444';
    pct.style.color = '#ef4444';
    icon.textContent = 'error';
    icon.style.color = '#dc2626';
    document.getElementById('dl-icon-wrap').style.background = '#fee2e2';
    dlLabel.textContent = 'Download gagal!';
    dlSub.textContent = err.message;
    errMsg.style.display = 'block';
    setTimeout(() => overlay.classList.remove('visible'), 3000);
  }
}

function setProgress(p, total, received) {
  const arcLen = 176;
  document.getElementById('dl-bar').style.width = p + '%';
  document.getElementById('dl-pct').textContent  = p + '%';
  document.getElementById('dl-arc').style.strokeDashoffset = arcLen - (arcLen * p / 100);
  if (total > 0) {
    const rcvKb  = (received / 1024).toFixed(1);
    const totKb  = (total   / 1024).toFixed(1);
    document.getElementById('dl-size').textContent = `${rcvKb} / ${totKb} KB`;
  } else if (received > 0) {
    document.getElementById('dl-size').textContent = `${(received / 1024).toFixed(1)} KB`;
  }
}
</script>
@endsection
