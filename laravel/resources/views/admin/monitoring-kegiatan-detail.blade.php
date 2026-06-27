@extends('layouts.admin')
@section('title', $kegiatan->nama . ' — Monitoring Kegiatan')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
        <a href="{{ route('admin.monitoring-kegiatan') }}" class="btn btn-ghost btn-sm" style="padding:4px 8px">
          <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>
        </a>
        <div class="page-title">{{ $kegiatan->nama }}</div>
      </div>
      <div class="page-sub">Detail kegiatan & absensi mahasiswa</div>
    </div>
  </div>

  {{-- Info Kegiatan --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px">
    <div class="panel" style="padding:16px">
      <div class="form-label">Tanggal</div>
      <div style="font-weight:600">{{ Carbon\Carbon::parse($kegiatan->tanggal_pelaksanaan)->format('d M Y') }}</div>
    </div>
    <div class="panel" style="padding:16px">
      <div class="form-label">Waktu</div>
      <div style="font-weight:600">{{ substr($kegiatan->jam_mulai, 0, 5) }} - {{ substr($kegiatan->jam_selesai, 0, 5) }}</div>
    </div>
    <div class="panel" style="padding:16px">
      <div class="form-label">Status</div>
      <span class="badge {{ $kegiatan->is_active ? 'badge-green' : 'badge-red' }}">{{ $kegiatan->is_active ? 'Aktif' : 'Selesai' }}</span>
    </div>
    <div class="panel" style="padding:16px">
      <div class="form-label">Wajib Hadir</div>
      <span class="badge {{ $kegiatan->wajib_hadir ? 'badge-yellow' : 'badge-blue' }}">{{ $kegiatan->wajib_hadir ? 'Ya' : 'Tidak' }}</span>
    </div>
  </div>

  {{-- Statistik --}}
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px">
    <div class="panel" style="padding:16px;text-align:center">
      <div style="font-size:28px;font-weight:700;color:var(--primary)">{{ $totalMahasiswa }}</div>
      <div class="form-label" style="margin-top:4px">Total Mahasiswa</div>
    </div>
    <div class="panel" style="padding:16px;text-align:center">
      <div style="font-size:28px;font-weight:700;color:#22c55e">{{ $hadir }}</div>
      <div class="form-label" style="margin-top:4px">Hadir</div>
    </div>
    <div class="panel" style="padding:16px;text-align:center">
      <div style="font-size:28px;font-weight:700;color:#ef4444">{{ $tidakHadir }}</div>
      <div class="form-label" style="margin-top:4px">Tidak Hadir</div>
    </div>
  </div>

  {{-- Tabel Absensi --}}
  <div class="panel">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:600">
      Daftar Absensi
    </div>
    <table class="att-table">
      <thead>
        <tr>
          <th>No</th>
          <th>Nama</th>
          <th>NIM</th>
          <th>Kompi</th>
          <th>Jam Masuk</th>
          <th>Jam Keluar</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse($attendances as $i => $att)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>
            <div class="mahasiswa-cell">
              <div class="avatar" style="background:var(--primary-light);color:var(--primary)">
                {{ strtoupper(substr($att->mahasiswa->name ?? '?', 0, 2)) }}
              </div>
              <div class="mhs-name">{{ $att->mahasiswa->name ?? '-' }}</div>
            </div>
          </td>
          <td>{{ $att->mahasiswa_id }}</td>
          <td><span class="badge badge-blue">{{ $att->mahasiswa->kompi ?? '-' }}</span></td>
          <td><span class="time-val">{{ $att->check_in_time ? substr($att->check_in_time, 0, 5) : '-' }}</span></td>
          <td><span class="time-val">{{ $att->check_out_time ? substr($att->check_out_time, 0, 5) : '-' }}</span></td>
          <td>
            @php
              $statusClass = match($att->status) {
                  'present', 'hadir' => 'badge-green',
                  'izin' => 'badge-blue',
                  'sakit' => 'badge-yellow',
                  default => 'badge-red'
              };
            @endphp
            <span class="badge {{ $statusClass }}">{{ strtoupper($att->status) }}</span>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px">
            Belum ada data absensi untuk kegiatan ini
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
