@extends('layouts.admin')
@section('title', 'Laporan Kelulusan — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Laporan Kelulusan</div>
      <div class="page-sub">Analisis kehadiran mahasiswa berdasarkan persentase (min. 80%)</div>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;padding:14px 20px">
    <form method="GET" action="{{ route('admin.kelulusan') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div><label class="form-label">Jurusan</label>
        <select name="jurusan" class="form-input" style="width:150px;padding:7px 10px">
          <option value="">Semua</option>
          @foreach($jurusanOptions as $j)<option value="{{ $j }}" {{ $filterJurusan == $j ? 'selected' : '' }}>{{ $j }}</option>@endforeach
        </select>
      </div>
      <div><label class="form-label">Prodi</label>
        <select name="prodi" class="form-input" style="width:150px;padding:7px 10px">
          <option value="">Semua</option>
          @foreach($prodiOptions as $p)<option value="{{ $p }}" {{ $filterProdi == $p ? 'selected' : '' }}>{{ $p }}</option>@endforeach
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Proses Laporan</button>
      <a href="{{ route('admin.kelulusan') }}" class="btn btn-ghost btn-sm">Reset</a>
    </form>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>Mahasiswa</th><th>Kompi</th><th>Prodi</th><th>Total Kegiatan</th><th>Hadir</th><th>Persentase</th><th>Status Lulus</th></tr></thead>
      <tbody>
        @forelse($kelulusanData as $m)
        <tr>
          <td><div class="mhs-name">{{ $m->name }}</div></td>
          <td><span class="badge badge-blue">{{ $m->kompi }}</span></td>
          <td style="font-size:13px">{{ $m->prodi ?? '-' }}</td>
          <td>{{ $m->total_hari }}</td>
          <td>{{ $m->total_hadir }}</td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="flex:1;height:6px;background:var(--border);border-radius:3px;overflow:hidden">
                <div style="height:100%;width:{{ $m->persentase }}%;background:{{ $m->persentase >= 80 ? 'var(--success)' : 'var(--danger)' }}"></div>
              </div>
              <span style="font-size:12px;font-family:var(--font-mono)">{{ $m->persentase }}%</span>
            </div>
          </td>
          <td><span class="badge {{ $m->status_lulus === 'Lulus' ? 'badge-green' : 'badge-red' }}">{{ $m->status_lulus }}</span></td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada data</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
