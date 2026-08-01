@extends('layouts.admin')
@section('title', 'Mahasiswa Saya — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Mahasiswa Saya</div>
      <div class="page-sub">Daftar mahasiswa dalam pengawasan Anda ( {{ auth()->user()->assigned_kompi ?? 'Semua' }})</div>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;padding:14px 20px">
    <form method="GET" action="{{ route('garda.mahasiswa-saya') }}" style="display:flex;gap:12px;align-items:center">
      <div class="form-input-wrapper" style="flex:1;max-width:300px;display:flex">
        <input type="text" name="search" class="form-input" placeholder="Cari nama atau NPM..." value="{{ request('search') }}" style="border-radius:4px 0 0 4px">
        <button type="submit" class="btn btn-primary" style="border-radius:0 4px 4px 0;padding:0 12px">
          <span class="material-symbols-outlined" style="font-size:18px">search</span>
        </button>
      </div>
      @if(request('search'))
        <a href="{{ route('garda.mahasiswa-saya') }}" class="btn btn-ghost" style="height:38px;display:flex;align-items:center;padding:0 12px">Reset</a>
      @endif
    </form>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>Foto</th><th>Mahasiswa</th><th>Kompi</th><th>Prodi</th><th>Email</th><th>No. Telp</th><th>No. Telp Ortu</th><th>Status Harian <small style="font-weight:400;color:var(--text-muted)">(per hari PKKMB)</small></th></tr></thead>
      <tbody>
        @forelse($mahasiswaList as $m)
        <tr>
          <td>
            {{-- Foto Lingkaran --}}
            @if($m->photo_url)
              <img src="{{ $m->photo_url }}"
                   alt="{{ $m->name }}"
                   style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid var(--primary);display:block">
            @else
              <div style="width:44px;height:44px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;border:2px dashed var(--border)" title="Foto belum diupload">
                <span class="material-symbols-outlined" style="font-size:22px;color:var(--primary);opacity:0.6">person</span>
              </div>
            @endif
          </td>
          <td>
            <div class="mahasiswa-cell">
              <div>
                <div class="mhs-name">{{ $m->name }}</div>
                <div class="mhs-dept" style="font-family:monospace;font-size:11px;color:var(--text-muted)">{{ $m->id ?? '-' }}</div>
              </div>
            </div>
          </td>
          <td><span class="badge badge-blue">{{ $m->kompi }}</span></td>
          <td style="font-size:13px">{{ $m->prodi ?? '-' }}</td>
          <td style="font-size:13px;color:var(--text-muted)">{{ $m->email ?? '-' }}</td>
          <td style="font-size:13px;color:var(--text-muted)">{{ $m->no_telp_mahasiswa ?? $m->no_telp ?? '-' }}</td>
          <td style="font-size:13px;color:var(--text-muted)">{{ $m->no_telp_ortu ?? '-' }}</td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
              @php
                // Satu titik per hari PKKMB
                $attendancesByDate = $m->attendances->keyBy(fn($a) => \Carbon\Carbon::parse($a->date)->format('Y-m-d'));
              @endphp

              @forelse($allSchedules as $sched)
                @php
                  $tgl = \Carbon\Carbon::parse($sched->tanggal)->format('Y-m-d');
                  $att = $attendancesByDate->get($tgl);
                  $status = $att ? $att->status : null;

                  if (!$att) {
                    // Jika hari sudah lewat: alpha, jika belum: abu
                    if (\Carbon\Carbon::parse($tgl)->isPast()) {
                      $color = '#ef4444'; $label = 'Alpha';
                    } else {
                      $color = '#d1d5db'; $label = 'Belum';
                    }
                  } elseif (in_array($status, ['hadir', 'present'])) {
                    if ($att->check_in && !$att->check_out) {
                      $color = '#1f2937'; $label = 'Masuk (belum keluar)';
                    } else {
                      $color = '#10b981'; $label = 'Hadir';
                    }
                  } elseif ($status === 'izin') {
                    $color = '#3b82f6'; $label = 'Izin';
                  } elseif ($status === 'sakit') {
                    $color = '#eab308'; $label = 'Sakit';
                  } else {
                    $color = '#ef4444'; $label = 'Alpha';
                  }

                  $title = "PKKMB Hari ke-{$sched->hari_ke} ({$tgl}) | Status: " . strtoupper($label);
                @endphp
                <div style="width:14px;height:14px;background-color:{{ $color }};border-radius:50%;display:inline-block;border:1px solid rgba(0,0,0,0.15);cursor:pointer;flex-shrink:0"
                     title="{{ $title }}"></div>
              @empty
                <span style="font-size:12px;color:#9ca3af">Belum ada jadwal</span>
              @endforelse
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada data mahasiswa di kompi Anda</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="margin-top: 16px;">
    {{ $mahasiswaList->links('pagination::bootstrap-4') }}
  </div>

  {{-- Legenda warna titik --}}
  <div style="margin-top:12px;display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:var(--text-muted);align-items:center;padding:10px 16px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm)">
    <span style="font-weight:600;color:var(--text)">Keterangan:</span>
    <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#10b981;display:inline-block"></span>Hadir</span>
    <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#1f2937;display:inline-block"></span>Masuk (blm keluar)</span>
    <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#ef4444;display:inline-block"></span>Alpha</span>
    <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#3b82f6;display:inline-block"></span>Izin</span>
    <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#eab308;display:inline-block"></span>Sakit</span>
    <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:50%;background:#d1d5db;display:inline-block"></span>Belum (jadwal mendatang)</span>
  </div>
</section>

<style>
.mahasiswa-cell { display:flex;align-items:center;gap:12px }
.mhs-name { font-weight:600;font-size:14px }
.badge { display:inline-block;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;text-transform:uppercase }
.badge-blue { background:var(--info-light);color:var(--info) }
.att-table { width:100%;border-collapse:collapse }
.att-table thead th { background:var(--bg);padding:12px;text-align:left;font-weight:600;font-size:12px;color:var(--text-muted);border-bottom:2px solid var(--border) }
.att-table tbody td { padding:12px;border-bottom:1px solid var(--border);vertical-align:middle }
</style>
@endsection
