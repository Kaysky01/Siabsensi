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

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>Foto</th><th>Mahasiswa</th><th>Kompi</th><th>Prodi</th><th>Email</th><th>No. Telp</th><th>Status Kegiatan</th></tr></thead>
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
          <td style="font-size:13px;color:var(--text-muted)">{{ $m->no_telp_mahasiswa ?? '-' }}</td>
          <td>
            <div style="display:flex;gap:4px">
              @foreach($allKegiatan as $keg)
                @php
                  $att = $m->attendances->filter(function($a) use ($keg) {
                      return $a->kegiatan_id == $keg->id || \Carbon\Carbon::parse($a->date)->format('Y-m-d') === \Carbon\Carbon::parse($keg->tanggal)->format('Y-m-d');
                  })->first();

                  if(!$att || $att->status === 'alpha') {
                    $color = '#ef4444';
                    $title = $keg->nama . ' - Alpha';
                  } else if ($att->status === 'izin') {
                    $color = '#3b82f6';
                    $title = $keg->nama . ' - Izin';
                  } else if ($att->status === 'sakit') {
                    $color = '#eab308';
                    $title = $keg->nama . ' - Sakit';
                  } else if(!$att->check_out) {
                    $color = '#1f2937';
                    $jamMasuk = $att->check_in ? \Carbon\Carbon::parse($att->check_in)->format('H:i') : '-';
                    $title = $keg->nama . ' - Masuk (' . $jamMasuk . ')';
                  } else {
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
