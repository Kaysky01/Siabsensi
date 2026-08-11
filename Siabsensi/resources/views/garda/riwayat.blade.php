@extends('layouts.admin')
@section('title', 'Riwayat Absensi — SIABSEN')

@section('content')
<style>
  .ks-mhs-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
  }
  .ks-mhs-table thead th {
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
  }
  .ks-mhs-table tbody tr { transition: background 0.15s ease; }
  .ks-mhs-table tbody tr:hover { background: #f8fafc; }
  .ks-mhs-table tbody td {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
  }

  .status-legend-bar {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    font-size: 11px;
    color: #64748b;
    padding: 10px 14px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    margin-bottom: 16px;
  }
  .legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
  }
  .legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
  }
</style>

<section>
  <div class="page-header" style="margin-bottom:16px">
    <div>
      <div class="page-title" style="font-size:20px;font-weight:800;color:#0f172a">Riwayat Absensi</div>
      <div class="page-sub" style="color:#2563eb;font-weight:700;font-size:13px;margin-top:2px">Kompi {{ auth()->user()->assigned_kompi ?? '-' }}</div>
    </div>
    <a href="{{ route('garda.dashboard') }}" class="btn btn-ghost" style="background:#f1f5f9;border:1px solid #cbd5e1;padding:6px 12px;border-radius:8px;color:#334155;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:4px;text-decoration:none">
      <span class="material-symbols-outlined" style="font-size:16px">arrow_back</span>
      Kembali ke Dashboard
    </a>
  </div>

  {{-- FILTER PANEL --}}
  <div class="panel" style="margin-bottom:16px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;box-shadow:0 2px 6px rgba(0,0,0,0.02)">
    <form method="GET" action="{{ route('garda.riwayat') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <div style="flex:2;min-width:240px">
        <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:4px">Jadwal PKKMB</label>
        <select name="schedule" class="form-input" style="width:100%;height:38px;padding:0 12px;font-size:13px;border-radius:8px;background:#f8fafc;border:1px solid #cbd5e1">
          <option value="">-- Pilih Hari --</option>
          @foreach($schedules as $s)
            <option value="{{ $s->id }}" {{ $selectedSchedule == $s->id ? 'selected' : '' }}>
              PKKMB Hari ke-{{ $s->hari_ke }} - {{ \Carbon\Carbon::parse($s->tanggal)->format('d M Y') }}
            </option>
          @endforeach
        </select>
      </div>

      <div style="flex:1;min-width:140px">
        <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:4px">Status</label>
        <select name="status" class="form-input" style="width:100%;height:38px;padding:0 12px;font-size:13px;border-radius:8px;background:#f8fafc;border:1px solid #cbd5e1">
          <option value="">Semua Status</option>
          <option value="present" {{ request('status') == 'present' ? 'selected' : '' }}>Hadir</option>
          <option value="izin" {{ request('status') == 'izin' ? 'selected' : '' }}>Izin</option>
          <option value="sakit" {{ request('status') == 'sakit' ? 'selected' : '' }}>Sakit</option>
          <option value="alpha" {{ request('status') == 'alpha' ? 'selected' : '' }}>Alpha</option>
        </select>
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" class="btn" style="height:38px;padding:0 16px;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px">
          <span class="material-symbols-outlined" style="font-size:18px">filter_list</span>
          Filter
        </button>
        <a href="{{ route('garda.riwayat') }}" class="btn" style="height:38px;padding:0 12px;border-radius:8px;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;display:inline-flex;align-items:center;justify-content:center;text-decoration:none">
          <span class="material-symbols-outlined" style="font-size:18px">refresh</span>
        </a>
      </div>
    </form>
  </div>

  <!-- LEGENDA STATUS WARNA -->
  <div class="status-legend-bar">
    <span style="font-weight:700;color:#0f172a">Keterangan:</span>
    <span class="legend-item"><span class="legend-dot" style="background:#10b981"></span> Lengkap / Hadir</span>
    <span class="legend-item"><span class="legend-dot" style="background:#1f2937"></span> Masuk (belum keluar)</span>
    <span class="legend-item"><span class="legend-dot" style="background:#ef4444"></span> Alpha</span>
    <span class="legend-item"><span class="legend-dot" style="background:#3b82f6"></span> Izin</span>
    <span class="legend-item"><span class="legend-dot" style="background:#eab308"></span> Sakit</span>
    <span class="legend-item"><span class="legend-dot" style="background:#d1d5db"></span> Belum ada</span>
  </div>

  @if($riwayat->isEmpty())
  <div class="panel" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:48px 20px;text-align:center">
    <span class="material-symbols-outlined" style="font-size:64px;color:#cbd5e1;display:block;margin-bottom:12px">inbox</span>
    <h3 style="margin:0 0 6px 0;color:#0f172a;font-weight:800;font-size:18px">Pilih Jadwal Terlebih Dahulu</h3>
    <p style="color:#64748b;margin:0;font-size:13px">Silakan pilih jadwal PKKMB dari dropdown di atas untuk melihat riwayat absensi mahasiswa.</p>
  </div>
  @else
  <div class="panel" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:0;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,0.02)">
    <div style="overflow-x:auto">
      <table class="ks-mhs-table">
        <thead>
          <tr>
            <th>Nama Mahasiswa</th>
            <th>Tanggal</th>
            <th>Masuk</th>
            <th>Keluar</th>
            <th>Kegiatan</th>
            <th>Status</th>
            <th>Oleh</th>
          </tr>
        </thead>
        <tbody>
          @foreach($riwayat as $item)
          @php
            $badge = $item->getStatusBadgeData();
          @endphp
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                @php
                  $photoUrl = null;
                  if (!empty($item->photo_path)) {
                    $path = $item->photo_path;
                    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                      $photoUrl = $path;
                    } else {
                      $cleanPath = ltrim(str_replace(['public/', 'storage/'], '', $path), '/');
                      $photoUrl = url('/file-bukti/' . $cleanPath);
                    }
                  }
                @endphp
                @if($photoUrl)
                  <img src="{{ $photoUrl }}" alt="{{ $item->name }}" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #3b82f6;flex-shrink:0;">
                @else
                  <div style="width:34px;height:34px;background:#dbeafe;color:#2563eb;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;border-radius:50%;flex-shrink:0">
                    {{ strtoupper(substr($item->name, 0, 2)) }}
                  </div>
                @endif
                <div style="font-weight:700;color:#0f172a;font-size:13px">{{ $item->name }}</div>
              </div>
            </td>
            <td style="font-size:12px;color:#334155;white-space:nowrap">
              {{ \Carbon\Carbon::parse($item->created_at)->format('d M Y') }}
            </td>
            <td style="font-size:12px;white-space:nowrap">
              @if($item->absen_by)
                <span class="badge" style="background:#e0f2fe;color:#0369a1;border:1px solid #0284c7;font-size:10px;padding:3px 8px;border-radius:12px">
                  Manual ({{ $item->absen_by }})
                </span>
              @elseif($item->check_in)
                <span style="font-weight:600;color:#0f172a">{{ \Carbon\Carbon::parse($item->check_in)->format('H:i') }}</span>
              @else
                <span style="color:#94a3b8">-</span>
              @endif
            </td>
            <td style="font-size:12px;white-space:nowrap">
              @if($item->check_out)
                <span style="font-weight:600;color:#0f172a">{{ \Carbon\Carbon::parse($item->check_out)->format('H:i') }}</span>
              @else
                <span style="color:#94a3b8">-</span>
              @endif
            </td>
            <td>
              <span style="color:#64748b;font-size:12px">Absensi Harian</span>
            </td>
            <td>
              <span class="badge" style="background:{{ $badge['bg'] }};color:{{ $badge['color'] }};border:1px solid {{ $badge['border'] }};padding:3px 10px;border-radius:12px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:4px">
                <span class="legend-dot" style="background:{{ $badge['dot'] }}"></span>
                {{ $badge['label'] }}
              </span>
            </td>
            <td style="font-size:12px;color:#64748b">
              {{ $item->absen_by ?? '-' }}
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div style="margin-top:16px;display:flex;justify-content:center">
    {{ $riwayat->links('pagination::bootstrap-4') }}
  </div>
  @endif
</section>
@endsection
