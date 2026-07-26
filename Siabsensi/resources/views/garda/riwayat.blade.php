@extends('layouts.admin')
@section('title', 'Riwayat Absensi — SIABSEN')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<section>
  <div class="page-header">
    <div>
      <div class="page-title">Riwayat Absensi</div>
      <div class="page-sub" style="color:var(--primary);font-weight:600">Kompi: {{ auth()->user()->assigned_kompi ?? '-' }}</div>
    </div>
    <a href="{{ route('garda.dashboard') }}" class="btn btn-ghost">
      <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>
      Kembali ke Dashboard
    </a>
  </div>

  <div class="panel" style="margin-bottom:20px">
    <form method="GET" action="{{ route('garda.riwayat') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <div style="flex:2;min-width:250px">
        <label style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:4px">Jadwal PKKMB</label>
        <select name="schedule" class="form-input" style="width:100%">
          <option value="">-- Pilih Hari --</option>
          @foreach($schedules as $s)
            <option value="{{ $s->id }}" {{ $selectedSchedule == $s->id ? 'selected' : '' }}>
              PKKMB Hari ke-{{ $s->hari_ke }} - {{ \Carbon\Carbon::parse($s->tanggal)->format('d M Y') }}
            </option>
          @endforeach
        </select>
      </div>
      <div style="flex:1;min-width:150px">
        <label style="display:block;font-size:12px;color:var(--text-muted);margin-bottom:4px">Status</label>
        <select name="status" class="form-input" style="width:100%">
          <option value="">Semua Status</option>
          <option value="present" {{ request('status') == 'present' ? 'selected' : '' }}>Hadir</option>
          <option value="izin" {{ request('status') == 'izin' ? 'selected' : '' }}>Izin</option>
          <option value="sakit" {{ request('status') == 'sakit' ? 'selected' : '' }}>Sakit</option>
          <option value="alpha" {{ request('status') == 'alpha' ? 'selected' : '' }}>Alpha</option>
        </select>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">
          <span class="material-symbols-outlined" style="font-size:16px">search</span>
          Filter
        </button>
        <a href="{{ route('garda.riwayat') }}" class="btn btn-ghost">
          <span class="material-symbols-outlined" style="font-size:16px">refresh</span>
        </a>
      </div>
    </form>
  </div>

  @if($riwayat->isEmpty())
  <div class="empty-state-box" style="text-align:center;padding:60px 20px">
    <span class="material-symbols-outlined empty-icon" style="font-size:80px;color:var(--text-muted);opacity:0.5">inbox</span>
    <h2 style="margin:20px 0 8px 0;color:var(--text)">Pilih Jadwal terlebih dahulu</h2>
    <p style="color:var(--text-secondary);margin:0 0 24px 0;font-size:16px">Pilih jadwal PKKMB dari dropdown di atas untuk melihat riwayat absensi</p>
  </div>
  @else
  <div class="panel">
    <table class="att-table">
      <thead>
        <tr>
          <th>Nama Mahasiswa</th>
          <th>Tanggal</th>
          <th>Jam Masuk</th>
          <th>Kegiatan</th>
          <th>Status</th>
          <th>Oleh</th>
        </tr>
      </thead>
      <tbody>
        @foreach($riwayat as $item)
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="avatar" style="width:32px;height:32px;background:var(--primary-light);color:var(--primary);font-size:12px;display:flex;align-items:center;justify-content:center;border-radius:50%">
                {{ strtoupper(substr($item->name, 0, 2)) }}
              </div>
              {{ $item->name }}
            </div>
          </td>
          <td>
            {{ \Carbon\Carbon::parse($item->created_at)->format('d M Y') }}
          </td>
          <td>
            @if($item->absen_by)
              <span class="badge" style="background:#e0f2fe;color:#0369a1;border:1px solid #0284c7;font-size:11px" title="Waktu: {{ $item->absen_at ? \Carbon\Carbon::parse($item->absen_at)->format('H:i') : (\Carbon\Carbon::parse($item->created_at)->format('H:i')) }}">
                Kehadiran Manual
              </span>
            @elseif($item->absen_at)
              {{ \Carbon\Carbon::parse($item->absen_at)->format('H:i') }}
            @elseif($item->created_at)
              {{ \Carbon\Carbon::parse($item->created_at)->format('H:i') }}
            @else
              -
            @endif
          </td>
          <td>
            {{ optional($item->sesi)->nama_sesi ?? $item->nama_sesi ?? 'Sesi Tidak Ditemukan' }}
          </td>
          <td>
            @if($item->status === 'present')
              <span class="badge badge-green">Hadir</span>
            @elseif($item->status === 'izin')
              <span class="badge badge-blue">Izin</span>
            @elseif($item->status === 'sakit')
              <span class="badge badge-yellow">Sakit</span>
            @elseif($item->status === 'alpha')
              <span class="badge badge-red">Alpha</span>
            @else
              <span class="badge badge-gray">{{ ucfirst($item->status) }}</span>
            @endif
          </td>
          <td style="font-size:12px;color:var(--text-muted)">
            {{ $item->absen_by ?? '-' }}
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px">
    {{ $riwayat->links('pagination::bootstrap-4') }}
  </div>
  @endif

  <div class="panel" style="margin-top:20px;background:var(--info-light);border:1px solid var(--info)">
    <div style="display:flex;gap:12px">
      <span class="material-symbols-outlined" style="font-size:32px;color:var(--info)">info</span>
      <div style="flex:1">
        <strong style="color:var(--info-dark);font-size:15px;display:block;margin-bottom:8px">ℹ️ Informasi</strong>
        <ul style="margin:0;padding-left:20px;color:var(--info-dark);line-height:1.8;font-size:14px">
          <li>Riwayat hanya menampilkan absensi mahasiswa dari kompi Anda</li>
          <li>Gunakan filter untuk melihat riwayat berdasarkan tanggal atau status tertentu</li>
          <li>Data absensi manual dihitung sejak Garda melakukan simpan absensi</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<style>
  .att-table { width: 100%; border-collapse: collapse; }
  .att-table thead th { background: var(--bg); padding: 12px; text-align: left; font-weight: 600; font-size: 12px; color: var(--text-muted); border-bottom: 2px solid var(--border); }
  .att-table tbody td { padding: 12px; border-bottom: 1px solid var(--border); }
  .att-table tbody tr:hover { background: var(--bg); }
  .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
  .badge-green { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
  .badge-blue { background: #e0f2fe; color: #0369a1; border: 1px solid #0284c7; }
  .badge-yellow { background: #fef9c3; color: #854d0e; border: 1px solid #eab308; }
  .badge-red { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
  .badge-gray { background: var(--border); color: var(--text-muted); }
  .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; border-radius: var(--radius-sm); border: none; cursor: pointer; font-weight: 600; text-decoration: none; font-size: 14px; transition: all 0.2s; }
  .btn-primary { background: var(--primary); color: white; }
  .btn-primary:hover { background: var(--primary-dark); }
  .btn-ghost { background: transparent; color: var(--text); border: 1px solid var(--border); }
  .btn-ghost:hover { background: var(--bg); }
  .form-input { padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 14px; width: 100%; }
  .form-input:focus { outline: none; border-color: var(--primary); }
</style>
@endsection
