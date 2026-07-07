@extends('layouts.admin')
@section('title', 'Verifikasi Kehadiran Manual — SIABSEN')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<section>
  <div class="page-header">
    <div>
      <div class="page-title">Verifikasi Kehadiran Manual</div>
      <div class="page-sub">Kelola pengajuan kehadiran manual dari mahasiswa kompi Anda</div>
    </div>
  </div>

  @if(session('success'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: '{{ session('success') }}',
        confirmButtonColor: '#28a745',
        timer: 3000,
        timerProgressBar: true
      });
    });
  </script>
  @endif

  @if(session('error'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: '{{ session('error') }}',
        confirmButtonColor: '#dc3545'
      });
    });
  </script>
  @endif

  <div class="stats-row" style="display:flex;gap:16px;margin-bottom:24px">
    <a href="{{ route('garda.kehadiran-manual') }}" class="stat-box {{ !$filterStatus ? 'active' : '' }}">
      <div class="stat-label">Semua</div>
      <div class="stat-value">{{ $stats['pending'] + $stats['approved'] + $stats['rejected'] }}</div>
    </a>
    <a href="{{ route('garda.kehadiran-manual', ['status' => 'pending']) }}" class="stat-box pending {{ $filterStatus == 'pending' ? 'active' : '' }}">
      <div class="stat-label">Pending</div>
      <div class="stat-value">{{ $stats['pending'] }}</div>
    </a>
    <a href="{{ route('garda.kehadiran-manual', ['status' => 'approved']) }}" class="stat-box success {{ $filterStatus == 'approved' ? 'active' : '' }}">
      <div class="stat-label">Disetujui</div>
      <div class="stat-value">{{ $stats['approved'] }}</div>
    </a>
    <a href="{{ route('garda.kehadiran-manual', ['status' => 'rejected']) }}" class="stat-box danger {{ $filterStatus == 'rejected' ? 'active' : '' }}">
      <div class="stat-label">Ditolak</div>
      <div class="stat-value">{{ $stats['rejected'] }}</div>
    </a>
  </div>

  <div class="panel">
    <table class="att-table">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Tanggal</th>
          <th>Jam Masuk</th>
          <th>Jam Keluar</th>
          <th>Keterangan</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($submissions as $item)
        <tr>
          <td><strong>{{ $item->mahasiswa->name ?? '-' }}</strong></td>
          <td>{{ $item->date ? \Carbon\Carbon::parse($item->date)->format('d M Y') : '-' }}</td>
          <td>{{ $item->check_in_time ?? '-' }}</td>
          <td>{{ $item->check_out_time ?? '-' }}</td>
          <td>{{ $item->reason ?? '-' }}</td>
          <td>
            @if($item->status == 'pending')
              <span class="badge badge-warning">Pending</span>
            @elseif($item->status == 'approved')
              <span class="badge badge-green">Disetujui</span>
            @else
              <span class="badge badge-red">Ditolak</span>
            @endif
          </td>
          <td>
            @if($item->status == 'pending')
            <div style="display:flex;gap:8px">
              <button type="button" class="btn btn-primary btn-sm" onclick="approveKehadiran({{ $item->id }})">Setujui</button>
              <button type="button" class="btn btn-danger btn-sm" onclick="rejectKehadiran({{ $item->id }})">Tolak</button>
            </div>
            @else
              <span style="color:var(--text-muted);font-size:12px">
                {{ $item->verified_by }} @ {{ $item->verified_at ? \Carbon\Carbon::parse($item->verified_at)->format('d/m H:i') : '-' }}
              </span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px">
            Tidak ada pengajuan kehadiran manual.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
    <div style="margin-top:16px">
      {{ $submissions->links('pagination::bootstrap-4') }}
    </div>
  </div>
</section>

<style>
.stats-row { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.stat-box {
  flex: 1;
  min-width: 120px;
  padding: 16px;
  background: var(--surface);
  border: 2px solid var(--border);
  border-radius: var(--radius-lg);
  text-align: center;
  text-decoration: none;
  color: var(--text);
  transition: all 0.2s;
}
.stat-box:hover { border-color: var(--primary); }
.stat-box.active { border-color: var(--primary); background: var(--primary-light); }
.stat-box.pending.active { border-color: #ffc107; background: #fff3cd; }
.stat-box.success.active { border-color: var(--success); background: var(--success-light); }
.stat-box.danger.active { border-color: var(--danger); background: var(--danger-light); }
.stat-label { font-size: 12px; color: var(--text-muted); margin-bottom: 4px; }
.stat-value { font-size: 24px; font-weight: 700; }

.badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-green { background: var(--success-light); color: var(--success); }
.badge-red { background: var(--danger-light); color: var(--danger); }

.att-table { width: 100%; border-collapse: collapse; }
.att-table thead th { background: var(--bg); padding: 12px; text-align: left; font-weight: 600; font-size: 12px; color: var(--text-muted); border-bottom: 2px solid var(--border); }
.att-table tbody td { padding: 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }

.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: var(--radius-sm); border: none; cursor: pointer; font-weight: 600; text-decoration: none; font-size: 13px; transition: all 0.2s; }
.btn-primary { background: var(--success); color: white; }
.btn-primary:hover { background: #0d9488; }
.btn-danger { background: var(--danger); color: white; }
.btn-danger:hover { background: #dc2626; }
.btn-sm { padding: 6px 10px; font-size: 12px; }
</style>

<script>
function approveKehadiran(id) {
  Swal.fire({
    title: 'Setujui Kehadiran?',
    text: 'Kehadiran akan disetujui dan data absensi akan diperbarui.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Setujui',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '{{ route('garda.kehadiran.verify') }}';
      form.innerHTML = `
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <input type="hidden" name="submission_id" value="${id}">
        <input type="hidden" name="action" value="approve">
      `;
      document.body.appendChild(form);
      form.submit();
    }
  });
}

function rejectKehadiran(id) {
  Swal.fire({
    title: 'Tolak Kehadiran?',
    text: 'Masukkan alasan penolakan (opsional):',
    icon: 'warning',
    input: 'textarea',
    inputPlaceholder: 'Alasan penolakan...',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Tolak',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '{{ route('garda.kehadiran.verify') }}';
      form.innerHTML = `
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <input type="hidden" name="submission_id" value="${id}">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="reject_reason" value="${result.value || ''}">
      `;
      document.body.appendChild(form);
      form.submit();
    }
  });
}
</script>
@endsection
