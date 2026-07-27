@extends('layouts.admin')
@section('title', 'Kompi Saya (' . $kompi . ') — SIABSEN')

@section('content')
<section>
  <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:24px">
    <div>
      <div class="page-title" style="display:flex;align-items:center;gap:10px">
        <span class="material-symbols-outlined" style="font-size:32px;color:var(--primary)">diversity_3</span>
        Kompi Saya ( {{ $kompi }} )
      </div>
      <div class="page-sub">Dashboard ringkasan kehadiran & pengelolaan pengumuman pop-up grup WhatsApp mahasiswa {{ $kompi }}</div>
    </div>
  </div>

  {{-- STATS OVERVIEW --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-bottom:24px">
    <div class="stat-card" style="background:#fff;padding:20px;border-radius:12px;border:1px solid var(--border);box-shadow:0 2px 4px rgba(0,0,0,0.03)">
      <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px">Total Mahasiswa Kompi</div>
      <div style="font-size:32px;font-weight:800;color:var(--text);margin-top:8px">{{ $totalMahasiswa }} <span style="font-size:14px;font-weight:500;color:var(--text-muted)">Mahasiswa</span></div>
    </div>

    <div class="stat-card" style="background:#fff;padding:20px;border-radius:12px;border:1px solid var(--border);box-shadow:0 2px 4px rgba(0,0,0,0.03)">
      <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px">Absen Masuk Harian (Hari Ini)</div>
      <div style="display:flex;gap:16px;align-items:baseline;margin-top:8px">
        <div style="font-size:26px;font-weight:800;color:#10b981">{{ $sudahAbsenMasukTodayCount }} <span style="font-size:12px;font-weight:600;color:#10b981">Sudah</span></div>
        <div style="font-size:26px;font-weight:800;color:#ef4444">{{ $belumAbsenMasukTodayCount }} <span style="font-size:12px;font-weight:600;color:#ef4444">Belum</span></div>
      </div>
    </div>

    <div class="stat-card" style="background:#fff;padding:20px;border-radius:12px;border:1px solid var(--border);box-shadow:0 2px 4px rgba(0,0,0,0.03);grid-column:span 2">
      <div style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px">Ringkasan Total Akumulasi Absensi Sesi</div>
      <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;margin-top:10px">
        <div style="padding:6px 14px;background:#ecfdf5;border-radius:8px;border:1px solid #a7f3d0">
          <span style="font-size:12px;color:#047857;font-weight:600">Hadir:</span>
          <strong style="font-size:18px;color:#065f46;margin-left:4px">{{ $totalSesiHadir }}</strong>
        </div>
        <div style="padding:6px 14px;background:#fef2f2;border-radius:8px;border:1px solid #fecaca">
          <span style="font-size:12px;color:#b91c1c;font-weight:600">Alpha:</span>
          <strong style="font-size:18px;color:#991b1b;margin-left:4px">{{ $totalSesiAlpha }}</strong>
        </div>
        <div style="padding:6px 14px;background:#fffbeb;border-radius:8px;border:1px solid #fde68a">
          <span style="font-size:12px;color:#b45309;font-weight:600">Izin:</span>
          <strong style="font-size:18px;color:#92400e;margin-left:4px">{{ $totalSesiIzin }}</strong>
        </div>
        <div style="padding:6px 14px;background:#f0f9ff;border-radius:8px;border:1px solid #bae6fd">
          <span style="font-size:12px;color:#0369a1;font-weight:600">Sakit:</span>
          <strong style="font-size:18px;color:#075985;margin-left:4px">{{ $totalSesiSakit }}</strong>
        </div>
      </div>
    </div>
  </div>

  {{-- FORM PENGUMUMAN POP-UP & LINK GROUP WA --}}
  <div class="panel" style="margin-bottom:24px;border-left:4px solid #4f46e5;background:#ffffff">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-size:16px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px">
          <span class="material-symbols-outlined" style="color:#4f46e5">campaign</span>
          Kelola Pesan Pop-up & Link Group WA {{ $kompi }}
        </div>
        <div style="font-size:13px;color:var(--text-muted)">Pesan dan link ini akan **muncul sebagai Pop-up otomatis** ketika Mahasiswa di {{ $kompi }} login / membuka portal.</div>
      </div>
      <div>
        @if($announcement && $announcement->is_active)
          <span class="badge" style="background:#dcfce7;color:#15803d;padding:6px 12px;border-radius:20px;font-weight:700">🟢 Pop-up AKTIF</span>
        @else
          <span class="badge" style="background:#f3f4f6;color:#6b7280;padding:6px 12px;border-radius:20px;font-weight:700">⚪ Pop-up Nonaktif</span>
        @endif
      </div>
    </div>

    <form method="POST" action="{{ route('garda.kompi-saya.announcement') }}">
      @csrf
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--text)">Judul Pop-up / Pengumuman <span style="color:#ef4444">*</span></label>
          <input type="text" name="judul" class="form-input" placeholder="Contoh: Link Group WhatsApp Resmi Kompi 1" value="{{ old('judul', $announcement->judul ?? ('Pengumuman Garda ' . $kompi)) }}" required style="width:100%">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--text)">Link Group WhatsApp (URL)</label>
          <div style="position:relative">
            <input type="url" name="link_wa" class="form-input" placeholder="https://chat.whatsapp.com/..." value="{{ old('link_wa', $announcement->link_wa ?? '') }}" style="width:100%;padding-left:36px">
            <span class="material-symbols-outlined" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:18px;color:#25D366">link</span>
          </div>
        </div>
      </div>

      <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--text)">Isi Pesan Pop-up</label>
        <textarea name="pesan" class="form-input" rows="3" placeholder="Tuliskan instruksi atau pesan untuk mahasiswa kompi Anda di sini..." style="width:100%">{{ old('pesan', $announcement->pesan ?? '') }}</textarea>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
          <input type="checkbox" name="is_active" value="1" {{ old('is_active', $announcement->is_active ?? true) ? 'checked' : '' }} style="width:18px;height:18px;accent-color:#4f46e5">
          <span style="font-size:14px;font-weight:600;color:var(--text)">Aktifkan Pop-Up ini untuk Mahasiswa {{ $kompi }}</span>
        </label>
        <button type="submit" class="btn btn-primary" style="background:#4f46e5;border-color:#4f46e5;display:flex;align-items:center;gap:6px">
          <span class="material-symbols-outlined" style="font-size:18px">save</span>
          Simpan Pengumuman & Link WA
        </button>
      </div>
    </form>
  </div>

  {{-- TABEL DAFTAR MAHASISWA KOMPI --}}
  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <div style="font-size:16px;font-weight:700;color:var(--text)">Detail Mahasiswa {{ $kompi }}</div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="text" id="search-box" class="form-input" placeholder="Cari nama, NIM, prodi..." value="{{ $search }}" onkeydown="if(event.key === 'Enter'){ event.preventDefault(); applySearch(); }">
        <select id="per-page-select" class="form-input" style="width:auto" onchange="applySearch()">
          <option value="20" {{ ($perPageReq ?? '20') == '20' ? 'selected' : '' }}>20 mhs / hal</option>
          <option value="50" {{ ($perPageReq ?? '') == '50' ? 'selected' : '' }}>50 mhs / hal</option>
          <option value="100" {{ ($perPageReq ?? '') == '100' ? 'selected' : '' }}>100 mhs / hal</option>
          <option value="all" {{ ($perPageReq ?? '') == 'all' ? 'selected' : '' }}>Tampilkan Semua ({{ $totalMahasiswa }})</option>
        </select>
        <button type="button" class="btn btn-ghost btn-sm" onclick="applySearch()">
          <span class="material-symbols-outlined" style="font-size:16px">search</span> Cari
        </button>
        @if($search !== '' || ($perPageReq ?? '20') !== '20')
        <a href="{{ route('garda.kompi-saya') }}" class="btn btn-ghost btn-sm">
          <span class="material-symbols-outlined" style="font-size:16px">refresh</span> Reset
        </a>
        @endif
      </div>
    </div>

    <div style="overflow-x:auto">
      <table class="att-table">
        <thead>
          <tr>
            <th style="width:40px;text-align:center">No</th>
            <th>Foto</th>
            <th>Mahasiswa</th>
            <th>Prodi</th>
            <th>Absen Masuk (Hari Ini)</th>
            <th style="text-align:center">Summary Absensi Sesi</th>
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
            <td style="text-align:center;font-size:13px;color:var(--text-muted)">
              {{ ($mahasiswaPaginated->currentPage() - 1) * $mahasiswaPaginated->perPage() + $loop->iteration }}
            </td>
            <td>
              @if($m->photo_url)
                <img src="{{ $m->photo_url }}" alt="{{ $m->name }}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--primary)">
              @else
                <div style="width:40px;height:40px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;border:1px dashed var(--border)">
                  <span class="material-symbols-outlined" style="font-size:20px;color:var(--primary)">person</span>
                </div>
              @endif
            </td>
            <td>
              <div style="font-weight:600;font-size:14px;color:var(--text)">{{ $m->name }}</div>
              <div style="font-family:monospace;font-size:11px;color:var(--text-muted)">{{ $m->id }}</div>
            </td>
            <td style="font-size:13px">{{ $m->prodi ?? '-' }}</td>
            <td>
              @if($isCheckInToday)
                <span class="badge" style="background:#dcfce7;color:#15803d;padding:4px 10px;border-radius:12px;font-weight:600;font-size:12px">✓ Sudah Absen Masuk</span>
              @else
                <span class="badge" style="background:#fef2f2;color:#b91c1c;padding:4px 10px;border-radius:12px;font-weight:600;font-size:12px">✕ Belum Absen Masuk</span>
              @endif
            </td>
            <td style="text-align:center">
              <div style="display:inline-flex;gap:8px;font-size:12px;font-weight:600">
                <span style="color:#047857" title="Hadir">H: {{ $hCount }}</span>
                <span style="color:#b91c1c" title="Alpha">A: {{ $aCount }}</span>
                <span style="color:#b45309" title="Izin">I: {{ $iCount }}</span>
                <span style="color:#0369a1" title="Sakit">S: {{ $sCount }}</span>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">
              Tidak ada data mahasiswa ditemukan di {{ $kompi }}.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(($perPageReq ?? '') !== 'all' && $mahasiswaPaginated->hasPages())
    <div style="margin-top:16px;display:flex;justify-content:center">
      {{ $mahasiswaPaginated->links('pagination::bootstrap-4') }}
    </div>
    @endif
  </div>
</section>

<script>
function applySearch() {
  const search = document.getElementById('search-box').value || '';
  const perPageSelect = document.getElementById('per-page-select');
  const perPage = perPageSelect ? perPageSelect.value : '20';
  const url = new URL('{{ route('garda.kompi-saya') }}', window.location.origin);
  if (search.trim() !== '') {
    url.searchParams.set('search', search.trim());
  }
  if (perPage !== '20') {
    url.searchParams.set('per_page', perPage);
  }
  window.location.href = url.toString();
}
</script>

<style>
.att-table { width:100%;border-collapse:collapse }
.att-table thead th { background:var(--bg);padding:12px;text-align:left;font-weight:600;font-size:12px;color:var(--text-muted);border-bottom:2px solid var(--border) }
.att-table tbody td { padding:12px;border-bottom:1px solid var(--border);vertical-align:middle }
</style>
@endsection
