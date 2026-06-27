@extends('layouts.admin')
@section('title', 'Verifikasi Kehadiran — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Verifikasi Kehadiran</div>
      <div class="page-sub">Pending: {{ $stats['pending'] }} · Disetujui: {{ $stats['approved'] }} · Ditolak: {{ $stats['rejected'] }}</div>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;padding:14px 20px">
    <div style="display:flex;gap:8px;align-items:center">
      <span class="form-label" style="margin-bottom:0">Status:</span>
      @foreach(['' => 'Semua', 'pending' => 'Pending', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $val => $label)
        <a href="{{ route('admin.kehadiran-timdis', ['status' => $val]) }}" class="filter-btn {{ $filterStatus === $val ? 'active' : '' }}">{{ $label }}</a>
      @endforeach
    </div>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>Mahasiswa</th><th>Kompi</th><th>Tanggal</th><th>Check In</th><th>Check Out</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        @forelse($submissions as $s)
        <tr>
          <td><div class="mhs-name">{{ $s->name }}</div></td>
          <td><span class="badge badge-blue">{{ $s->kompi }}</span></td>
          <td style="font-size:13px">{{ $s->date }}</td>
          <td><span class="time-val">{{ $s->check_in_time ?? '-' }}</span></td>
          <td><span class="time-val">{{ $s->check_out_time ?? '-' }}</span></td>
          <td>
            @php $sc = match($s->status) { 'pending' => 'badge-warning', 'approved' => 'badge-green', 'rejected' => 'badge-red', default => 'badge-gray' }; @endphp
            <span class="badge {{ $sc }}">{{ strtoupper($s->status) }}</span>
          </td>
          <td>
            @if($s->status === 'pending')
            <div style="display:flex;gap:4px">
              <form method="POST" action="{{ route('admin.kehadiran.verify') }}">@csrf
                <input type="hidden" name="submission_id" value="{{ $s->id }}"><input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--success)"><span class="material-symbols-outlined" style="font-size:16px">check</span></button>
              </form>
              <form method="POST" action="{{ route('admin.kehadiran.verify') }}" onsubmit="var r=prompt('Alasan penolakan:');if(!r)return false;this.querySelector('[name=reject_reason]').value=r;">@csrf
                <input type="hidden" name="submission_id" value="{{ $s->id }}"><input type="hidden" name="action" value="reject"><input type="hidden" name="reject_reason" value="">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"><span class="material-symbols-outlined" style="font-size:16px">close</span></button>
              </form>
            </div>
            @else <span style="font-size:12px;color:var(--text-muted)">{{ $s->verified_by ?? '-' }}</span>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada pengajuan</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
