@extends('layouts.admin')
@section('title', 'Verifikasi Kehadiran — SIABSEN')

@section('content')
<section>
  <div class="page-header" style="margin-bottom:20px">
    <div>
      <div class="page-title">Verifikasi Kehadiran</div>
      <div class="page-sub">Kelola pengajuan kehadiran manual mahasiswa</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(120px, 1fr));gap:12px;margin-bottom:20px">
    <div class="panel" style="padding:16px;display:flex;align-items:center;gap:12px;border-left:4px solid #F59E0B">
      <div style="background:var(--warning-light);color:#F59E0B;width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center">
        <span class="material-symbols-outlined">pending_actions</span>
      </div>
      <div>
        <div style="font-size:12px;color:var(--text-secondary);font-weight:600">PENDING</div>
        <div style="font-size:20px;font-weight:700">{{ $stats['pending'] }}</div>
      </div>
    </div>
    
    <div class="panel" style="padding:16px;display:flex;align-items:center;gap:12px;border-left:4px solid var(--success)">
      <div style="background:var(--success-light);color:var(--success);width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center">
        <span class="material-symbols-outlined">check_circle</span>
      </div>
      <div>
        <div style="font-size:12px;color:var(--text-secondary);font-weight:600">DISETUJUI</div>
        <div style="font-size:20px;font-weight:700">{{ $stats['approved'] }}</div>
      </div>
    </div>
    
    <div class="panel" style="padding:16px;display:flex;align-items:center;gap:12px;border-left:4px solid var(--danger)">
      <div style="background:var(--danger-light);color:var(--danger);width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center">
        <span class="material-symbols-outlined">cancel</span>
      </div>
      <div>
        <div style="font-size:12px;color:var(--text-secondary);font-weight:600">DITOLAK</div>
        <div style="font-size:20px;font-weight:700">{{ $stats['rejected'] }}</div>
      </div>
    </div>
  </div>

  <div class="panel" style="margin-bottom:16px;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
    <div style="display:flex;gap:8px;align-items:center">
      <span class="form-label" style="margin-bottom:0">Status:</span>
      @foreach(['' => 'Semua', 'pending' => 'Pending', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $val => $label)
        <a href="{{ request()->fullUrlWithQuery(['status' => $val, 'page' => 1]) }}" class="filter-btn {{ request('status') === $val || (request('status') === null && $val === '') ? 'active' : '' }}">{{ $label }}</a>
      @endforeach
    </div>
    <form method="GET" action="{{ route('garda.kehadiran-manual') }}" style="display:flex;gap:8px;max-width:300px;width:100%">
      <input type="hidden" name="status" value="{{ request('status') }}">
      <div class="form-input-wrapper" style="width:100%;display:flex">
        <input type="text" name="search" class="form-input" placeholder="Cari nama mahasiswa..." value="{{ request('search') }}" style="border-radius:4px 0 0 4px">
        <button type="submit" class="btn btn-primary" style="border-radius:0 4px 4px 0;padding:0 12px">
          <span class="material-symbols-outlined" style="font-size:18px">search</span>
        </button>
      </div>
    </form>
  </div>

  <div class="panel kehadiran-desktop">
    <table class="att-table">
      <thead><tr><th style="width:50px;text-align:center">No</th><th>Mahasiswa</th><th>Kompi</th><th>Tanggal</th><th>Waktu Pengajuan</th><th>Alasan</th><th>Bukti</th><th>Status</th><th style="width:60px;text-align:center;white-space:nowrap">Aksi</th></tr></thead>
      <tbody>
        @forelse($submissions as $index => $s)
        <tr>
          <td style="text-align:center">{{ $submissions->firstItem() + $index }}</td>
          <td><div class="mhs-name">{{ $s->name }}</div></td>
          <td><span class="badge badge-blue">{{ $s->kompi }}</span></td>
          <td style="font-size:13px">{{ \Carbon\Carbon::parse($s->date)->format('d M Y') }}</td>
          <td><span class="time-val">{{ \Carbon\Carbon::parse($s->created_at)->format('H:i') }} WIB</span></td>
          <td style="font-size:13px;max-width:200px">{{ Str::limit($s->keterangan, 50) }}</td>
          <td>
            @if($s->bukti_path)
            <button type="button" class="btn btn-ghost btn-sm" style="color:var(--primary);padding:4px 8px" title="Lihat Bukti" onclick="showBukti('{{ asset('file-bukti/' . $s->bukti_path) }}', '{{ Str::endsWith($s->bukti_path, ['.jpg', '.jpeg', '.png']) ? 'image' : 'pdf' }}')">
              <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">visibility</span> Lihat
            </button>
            @else
            <span style="color:var(--text-muted);font-size:12px">-</span>
            @endif
          </td>
          <td>
            @php $sc = match($s->status) { 'pending' => 'badge-warning', 'approved' => 'badge-green', 'rejected' => 'badge-red', default => 'badge-gray' }; @endphp
            <span class="badge {{ $sc }}">{{ strtoupper($s->status) }}</span>
            @if($s->status === 'rejected' && $s->rejection_reason)
              <div style="font-size: 11px; color: var(--danger); margin-top: 4px; max-width: 150px;">{{ $s->rejection_reason }}</div>
            @endif
          </td>
          <td>
            @if($s->status === 'pending')
            <div style="display:flex;gap:4px">
              <button type="button" class="btn btn-ghost btn-sm" style="color:var(--success)" title="Setujui" onclick="confirmVerify('{{ $s->id }}', 'approve')"><span class="material-symbols-outlined" style="font-size:16px">check</span></button>
              <button type="button" class="btn btn-ghost btn-sm" style="color:var(--danger)" title="Tolak" onclick="confirmVerify('{{ $s->id }}', 'reject')"><span class="material-symbols-outlined" style="font-size:16px">close</span></button>
            </div>
            @else
            <div style="display:flex;gap:8px;align-items:center">
              <span style="font-size:12px;color:var(--text-muted)">{{ $s->verified_by ?? '-' }}</span>
              <button type="button" class="btn btn-ghost btn-sm" style="color:#D97706" title="Batalkan Verifikasi" onclick="confirmVerify('{{ $s->id }}', 'cancel')">
                <span class="material-symbols-outlined" style="font-size:16px">history</span>
              </button>
            </div>
            @endif
          </td>
        </tr>
        @empty
        <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:30px">Tidak ada pengajuan</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  
  <div class="kehadiran-mobile">
    @forelse($submissions as $index => $s)
    <div class="panel" style="margin-bottom:12px;padding:14px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
        <div>
          <div style="font-weight:600;font-size:14px;margin-bottom:4px">#{{ $submissions->firstItem() + $index }} — {{ $s->name }}</div>
          <div style="display:flex;gap:4px">
            <span class="badge badge-blue">{{ $s->kompi }}</span>
          </div>
        </div>
        @php $sc = match($s->status) { 'pending' => 'badge-warning', 'approved' => 'badge-green', 'rejected' => 'badge-red', default => 'badge-gray' }; @endphp
        <div style="text-align: right">
          <span class="badge {{ $sc }}">{{ strtoupper($s->status) }}</span>
          @if($s->status === 'rejected' && $s->rejection_reason)
            <div style="font-size: 11px; color: var(--danger); margin-top: 4px;">{{ $s->rejection_reason }}</div>
          @endif
        </div>
      </div>
      
      <div style="font-size:13px;color:var(--text-secondary);margin-bottom:4px">
        <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">event</span>
        Tanggal: {{ \Carbon\Carbon::parse($s->date)->format('d M Y') }}
      </div>
      
      <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px">
        <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">schedule</span>
        Waktu Pengajuan: {{ \Carbon\Carbon::parse($s->created_at)->format('H:i') }} WIB
      </div>
      
      <div style="font-size:13px;color:var(--text-muted);margin-bottom:8px">
        {{ $s->keterangan }}
      </div>
      
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid var(--border-light)">
        <div>
          @if($s->bukti_path)
          <button type="button" class="btn btn-ghost btn-sm" style="color:var(--primary);padding:4px 8px;font-size:12px;width:auto" onclick="showBukti('{{ asset('file-bukti/' . $s->bukti_path) }}', '{{ Str::endsWith($s->bukti_path, ['.jpg', '.jpeg', '.png']) ? 'image' : 'pdf' }}')">
            <span class="material-symbols-outlined" style="font-size:14px">visibility</span> Lihat Bukti
          </button>
          @endif
        </div>
        
        @if($s->status === 'pending')
        <div style="display:flex;gap:4px">
          <button type="button" class="btn btn-primary btn-sm" style="background:var(--success);border-color:var(--success);padding:4px 12px;width:auto;font-size:12px" onclick="confirmVerify('{{ $s->id }}', 'approve')">Setujui</button>
          <button type="button" class="btn btn-primary btn-sm" style="background:var(--danger);border-color:var(--danger);padding:4px 12px;width:auto;font-size:12px" onclick="confirmVerify('{{ $s->id }}', 'reject')">Tolak</button>
        </div>
        @else
        <div style="display:flex;justify-content:space-between;align-items:center;width:100%;font-size:12px;color:var(--text-muted)">
          <span>By: {{ $s->verified_by ?? '-' }}</span>
          <button type="button" class="btn btn-ghost btn-sm" style="color:#D97706;padding:4px;width:auto;height:auto" title="Batalkan Verifikasi" onclick="confirmVerify('{{ $s->id }}', 'cancel')">
            <span class="material-symbols-outlined" style="font-size:16px">history</span> Batal
          </button>
        </div>
        @endif
      </div>
    </div>
    @empty
    <div class="panel" style="padding:30px;text-align:center;color:var(--text-muted)">Tidak ada pengajuan</div>
    @endforelse
  </div>

  <div style="margin-top: 16px;">
    {{ $submissions->links('pagination::bootstrap-4') }}
  </div>
</section>

<!-- Modal Bukti -->
<div class="modal-backdrop" id="modal-bukti">
  <div class="modal modal-bukti">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div class="modal-title" style="margin-bottom:0">Bukti Lampiran</div>
      <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('modal-bukti').classList.remove('show')" style="padding:4px">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <div id="bukti-content" style="text-align:center;min-height:200px"></div>
  </div>
</div>

<!-- Modal Verify -->
<div class="modal-backdrop" id="modal-verify">
  <div class="modal" style="max-width:400px;padding:24px">
    <div style="text-align:center;margin-bottom:16px">
      <div id="verify-icon" style="width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
        <span class="material-symbols-outlined" style="font-size:32px"></span>
      </div>
      <div id="verify-title" style="font-size:18px;font-weight:600;margin-bottom:8px"></div>
      <div id="verify-desc" style="font-size:14px;color:var(--text-secondary)"></div>
    </div>
    
    <form id="form-verify" method="POST" action="{{ route('garda.kehadiran.verify') }}">
      @csrf
      <input type="hidden" name="submission_id" id="verify-id">
      <input type="hidden" name="action" id="verify-action">
      
      <div id="rejection-reason-container" style="display:none;margin-bottom:20px;text-align:left">
        <label class="form-label">Alasan Penolakan (Opsional)</label>
        <textarea name="rejection_reason" class="form-input" rows="3" placeholder="Masukkan alasan penolakan..."></textarea>
      </div>

      <div style="display:flex;gap:12px;justify-content:center">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-verify').classList.remove('show')" style="flex:1;justify-content:center">Batal</button>
        <button type="submit" id="verify-btn-submit" class="btn btn-primary" style="flex:1;justify-content:center"></button>
      </div>
    </form>
  </div>
</div>

<style>
  .kehadiran-mobile { display: none; }
  @media (max-width: 768px) {
    .kehadiran-desktop { display: none; }
    .kehadiran-mobile { display: block; }
    .modal-bukti {
      width: calc(100% - 32px) !important;
      max-width: calc(100% - 32px) !important;
      padding: 16px;
      margin: 16px;
    }
    .modal-bukti #bukti-content img {
      max-height: 50vh;
    }
    .modal-bukti #bukti-content iframe {
      height: 50vh;
    }
    #modal-verify .modal {
      width: calc(100% - 32px) !important;
      max-width: calc(100% - 32px) !important;
      margin: 16px;
    }
    #modal-verify form > div:last-child {
      flex-direction: column-reverse;
    }
    #modal-verify form > div:last-child .btn {
      width: 100%;
    }
  }
</style>

<script>
  function showBukti(url, type) {
    var content = document.getElementById('bukti-content');
    if (type === 'image') {
      content.innerHTML = '<img src="' + url + '" alt="Bukti" style="max-width:100%;max-height:70vh;border-radius:8px;border:1px solid var(--border)">';
    } else {
      content.innerHTML = '<iframe src="' + url + '" style="width:100%;height:70vh;border:1px solid var(--border);border-radius:8px"></iframe>';
    }
    document.getElementById('modal-bukti').classList.add('show');
  }

  function confirmVerify(id, action) {
    document.getElementById('verify-id').value = id;
    document.getElementById('verify-action').value = action;
    
    var icon = document.querySelector('#verify-icon');
    var iconSpan = icon.querySelector('span');
    var title = document.getElementById('verify-title');
    var desc = document.getElementById('verify-desc');
    var submitBtn = document.getElementById('verify-btn-submit');
    var reasonContainer = document.getElementById('rejection-reason-container');
    
    if (action === 'approve') {
      icon.style.background = 'var(--success-light)';
      icon.style.color = 'var(--success)';
      iconSpan.textContent = 'check_circle';
      title.textContent = 'Setujui Kehadiran?';
      desc.textContent = 'Mahasiswa akan dicatat hadir pada jam yang diajukan.';
      submitBtn.textContent = 'Ya, Setujui';
      submitBtn.style.background = 'var(--success)';
      submitBtn.style.borderColor = 'var(--success)';
      reasonContainer.style.display = 'none';
      reasonContainer.querySelector('textarea').required = false;
    } else if (action === 'reject') {
      icon.style.background = 'var(--danger-light)';
      icon.style.color = 'var(--danger)';
      iconSpan.textContent = 'cancel';
      title.textContent = 'Tolak Kehadiran?';
      desc.textContent = 'Pengajuan kehadiran manual akan ditolak.';
      submitBtn.textContent = 'Ya, Tolak';
      submitBtn.style.background = 'var(--danger)';
      submitBtn.style.borderColor = 'var(--danger)';
      reasonContainer.style.display = 'block';
      reasonContainer.querySelector('textarea').required = false;
    } else if (action === 'cancel') {
      icon.style.background = 'var(--warning-light)';
      icon.style.color = '#D97706';
      iconSpan.textContent = 'history';
      title.textContent = 'Batalkan Verifikasi?';
      desc.textContent = 'Status pengajuan akan dikembalikan menjadi Pending/Menunggu.';
      submitBtn.textContent = 'Ya, Batalkan';
      submitBtn.style.background = '#D97706';
      submitBtn.style.borderColor = '#D97706';
      reasonContainer.style.display = 'none';
      reasonContainer.querySelector('textarea').required = false;
    }
    
    document.getElementById('modal-verify').classList.add('show');
  }
</script>
@endsection