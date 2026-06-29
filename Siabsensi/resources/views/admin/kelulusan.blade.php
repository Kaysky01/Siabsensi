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

  <div class="panel" style="margin-bottom:16px;padding:14px 20px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px; align-items:center;">
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

    <form method="POST" action="{{ route('admin.sertifikat.bulk-toggle') }}" style="display:flex;gap:8px;align-items:center;background:var(--bg-lighter);padding:8px 12px;border-radius:8px;">
      @csrf
      <input type="hidden" name="prodi" value="{{ $filterProdi }}">
      <input type="hidden" name="jurusan" value="{{ $filterJurusan }}">
      <span style="font-size:13px;font-weight:500;color:var(--text-main)">Ubah Semua (sesuai filter):</span>
      <select name="sertifikat_status" class="form-input" style="padding:4px 8px;font-size:12px;width:auto;cursor:pointer;">
        <option value="auto">Auto</option>
        <option value="unlocked">Force Buka</option>
        <option value="locked">Force Kunci</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm" style="padding:4px 10px;font-size:12px;" onclick="return confirm('Apakah Anda yakin ingin mengubah status sertifikat massal?')">Terapkan</button>
    </form>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>Mahasiswa</th><th>Kompi</th><th>Prodi</th><th>Total Kegiatan</th><th>Hadir</th><th>Persentase</th><th>Status Lulus</th><th>Aksi</th></tr></thead>
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
          <td>
            <div style="display:flex; gap:8px; align-items:center;">
              <form action="{{ route('admin.sertifikat.toggle-lock', $m->id) }}" method="POST" style="margin: 0;">
                @csrf
                <select name="sertifikat_status" onchange="this.form.submit()" class="form-input" style="padding: 2px 5px; font-size: 12px; height: auto; min-width: 85px; cursor: pointer; border-radius: 4px;">
                  <option value="auto" {{ $m->sertifikat_status === 'auto' ? 'selected' : '' }}>Auto</option>
                  <option value="unlocked" {{ $m->sertifikat_status === 'unlocked' ? 'selected' : '' }}>Buka</option>
                  <option value="locked" {{ $m->sertifikat_status === 'locked' ? 'selected' : '' }}>Kunci</option>
                </select>
              </form>
              
              @if($m->status_lulus === 'Lulus')
              <a href="{{ url('/api/mahasiswa/' . $m->id . '/sertifikat/preview/pdf') }}" target="_blank" class="btn btn-ghost btn-sm" style="color:var(--primary); padding: 4px;" title="Lihat Sertifikat">
                <span class="material-symbols-outlined" style="font-size:16px">workspace_premium</span>
              </a>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada data</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  
  <div style="margin-top: 16px;">
    {{ $kelulusanData->links('pagination::bootstrap-4') }}
  </div>
</section>
@endsection
