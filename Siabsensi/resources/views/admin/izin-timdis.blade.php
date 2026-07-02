@extends('layouts.admin')
@section('title', 'Verifikasi Izin/Sakit — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Verifikasi Izin/Sakit</div>
      <div class="page-sub">Pending: {{ $stats['pending'] }} · Disetujui: {{ $stats['approved'] }} · Ditolak: {{ $stats['rejected'] }}</div>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;padding:14px 20px">
    <form method="GET" action="{{ route('admin.izin-timdis') }}" style="display:flex;gap:8px;align-items:center">
      <span class="form-label" style="margin-bottom:0">Status:</span>
      @foreach(['' => 'Semua', 'pending' => 'Pending', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $val => $label)
        <a href="{{ route('admin.izin-timdis', ['status' => $val]) }}" class="filter-btn {{ $filterStatus === $val ? 'active' : '' }}">{{ $label }}</a>
      @endforeach
    </form>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead><tr><th>Mahasiswa</th><th>Kompi</th><th>Tipe</th><th>Tanggal</th><th>Alasan</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        @forelse($submissions as $s)
        <tr>
          <td><div class="mhs-name">{{ $s->name }}</div></td>
          <td><span class="badge badge-blue">{{ $s->kompi }}</span></td>
          <td><span class="badge {{ $s->submission_type === 'izin' ? 'badge-blue' : 'badge-yellow' }}">{{ strtoupper($s->submission_type) }}</span></td>
          <td style="font-size:13px">{{ $s->date }}</td>
          <td style="font-size:13px;max-width:200px">{{ Str::limit($s->reason, 50) }}</td>
          <td>
            @php $sc = match($s->status) { 'pending' => 'badge-warning', 'approved' => 'badge-green', 'rejected' => 'badge-red', default => 'badge-gray' }; @endphp
            <span class="badge {{ $sc }}">{{ strtoupper($s->status) }}</span>
          </td>
          <td>
            @if($s->status === 'pending')
            <div style="display:flex;gap:4px">
              @if($s->bukti_path)
              <a href="{{ url('file-bukti/' . $s->bukti_path) }}" target="_blank" class="btn btn-ghost btn-sm" style="color:var(--primary)" title="Lihat Bukti">
                <span class="material-symbols-outlined" style="font-size:16px">visibility</span>
              </a>
              @endif
              <form method="POST" action="{{ route('admin.izin.verify') }}">@csrf
                <input type="hidden" name="submission_id" value="{{ $s->id }}">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--success)" title="Setujui"><span class="material-symbols-outlined" style="font-size:16px">check</span></button>
              </form>
              <form method="POST" action="{{ route('admin.izin.verify') }}" onsubmit="var r=prompt('Alasan penolakan:');if(!r)return false;this.querySelector('[name=rejection_reason]').value=r;">@csrf
                <input type="hidden" name="submission_id" value="{{ $s->id }}">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="rejection_reason" value="">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)" title="Tolak"><span class="material-symbols-outlined" style="font-size:16px">close</span></button>
              </form>
            </div>
            @else
            <div style="display:flex;gap:8px;align-items:center">
              <span style="font-size:12px;color:var(--text-muted)">{{ $s->verified_by ?? '-' }}</span>
              @if($s->bukti_path)
              <a href="{{ url('file-bukti/' . $s->bukti_path) }}" target="_blank" class="btn btn-ghost btn-sm" style="padding:4px 8px" title="Lihat Bukti">
                <span class="material-symbols-outlined" style="font-size:14px">visibility</span>
              </a>
              @endif
            </div>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada pengajuan</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  
  <div style="margin-top: 16px;">
    {{ $submissions->links('pagination::bootstrap-4') }}
  </div>
</section>
@endsection
