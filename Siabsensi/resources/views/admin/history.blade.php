@extends('layouts.admin')
@section('title', 'Riwayat Absensi — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Riwayat Absensi</div>
      <div class="page-sub">{{ Carbon\Carbon::parse($start)->format('d/m/Y') }} — {{ Carbon\Carbon::parse($end)->format('d/m/Y') }}</div>
    </div>
    <div class="header-actions">
      <a href="{{ route('admin.attendance.export', ['start' => $start, 'end' => $end]) }}" class="btn btn-ghost btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Export
      </a>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;padding:14px 20px">
    <form method="GET" action="{{ route('admin.history') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div><label class="form-label">Dari</label><input type="date" name="start" class="form-input" value="{{ $start }}" style="padding:7px 10px"></div>
      <div><label class="form-label">Sampai</label><input type="date" name="end" class="form-input" value="{{ $end }}" style="padding:7px 10px"></div>
      <div><label class="form-label">Status</label>
        <select name="filter" class="form-input" style="width:120px;padding:7px 10px">
          <option value="all" {{ $filter=='all'?'selected':'' }}>Semua</option>
          <option value="izin" {{ $filter=='izin'?'selected':'' }}>Izin</option>
          <option value="sakit" {{ $filter=='sakit'?'selected':'' }}>Sakit</option>
          <option value="alpha" {{ $filter=='alpha'?'selected':'' }}>Alpha</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Cari</button>
    </form>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>No</th><th>Mahasiswa</th><th>Kompi</th><th>Tanggal</th><th>Tipe</th><th>Masuk</th><th>Keluar</th><th>Status</th><th>Keterlambatan</th><th>Aksi</th></tr></thead>
      <tbody>
        @forelse($attendances as $i => $att)
        <tr>
          <td>{{ $attendances->firstItem() + $i }}</td>
          <td><div class="mhs-name">{{ $att->name }}</div></td>
          <td><span class="badge badge-blue">{{ $att->kompi }}</span></td>
          <td style="font-size:13px">{{ $att->date ?? '-' }}</td>
          <td>
            @if($att->kegiatan_id)
              <span class="badge badge-purple" title="Absensi berbasis kegiatan">
                <i class="fas fa-calendar-alt"></i> Kegiatan
              </span>
            @else
              <span class="badge badge-gray" title="Absensi harian reguler">
                <i class="fas fa-calendar-day"></i> Harian
              </span>
            @endif
          </td>
          <td><span class="time-val">{{ $att->check_in ? Carbon\Carbon::parse($att->check_in)->format('H:i') : '-' }}</span></td>
          <td><span class="time-val">{{ $att->check_out ? Carbon\Carbon::parse($att->check_out)->format('H:i') : '-' }}</span></td>
          <td>
            @php $sc = match($att->status) { 'present','hadir' => 'badge-green', 'izin' => 'badge-blue', 'sakit' => 'badge-yellow', default => 'badge-red' }; @endphp
            <span class="badge {{ $sc }}">{{ strtoupper($att->status) }}</span>
          </td>
          <td>
            @if($att->kegiatan_id)
              <span class="badge badge-gray" title="Validasi waktu tidak berlaku untuk kegiatan">
                <i class="fas fa-minus"></i> N/A
              </span>
            @elseif($att->is_late ?? false)
              @if($att->late_overridden ?? false)
                <span class="badge badge-gray" title="Telat {{ $att->late_duration }} menit (Di-override oleh {{ $att->overridden_by }})">
                  <i class="fas fa-check-circle"></i> TELAT (Override)
                </span>
              @else
                <span class="badge badge-danger">
                  <i class="fas fa-clock"></i> TELAT {{ $att->late_duration }} menit
                </span>
              @endif
            @else
              <span class="badge badge-success">Tepat Waktu</span>
            @endif
          </td>
          <td>
            @if($att->kegiatan_id)
              <span style="color:var(--text-muted);font-size:12px" title="Override tidak tersedia untuk kegiatan">-</span>
            @elseif(($att->is_late ?? false) && !($att->late_overridden ?? false))
              <button type="button" class="btn btn-warning btn-xs" onclick="showOverrideModal({{ $att->id ?? 0 }}, '{{ $att->name }}', {{ $att->late_duration ?? 0 }})">
                <i class="fas fa-edit"></i> Override
              </button>
            @elseif($att->late_overridden ?? false)
              <button type="button" class="btn btn-info btn-xs" onclick="showOverrideDetails({{ $att->id ?? 0 }}, '{{ $att->overridden_by }}', '{{ $att->override_reason }}', '{{ $att->override_timestamp ? Carbon\Carbon::parse($att->override_timestamp)->format('d/m/Y H:i') : '' }}')">
                <i class="fas fa-info-circle"></i> Info
              </button>
            @else
              <span style="color:var(--text-muted);font-size:12px">-</span>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada data</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  
  <div style="margin-top: 16px;">
    {{ $attendances->links('pagination::bootstrap-4') }}
  </div>
</section>

<style>
.badge-purple {
  background: #9c27b0;
  color: white;
}

.badge-gray {
  background: #6c757d;
  color: white;
}
</style>
@endsection
