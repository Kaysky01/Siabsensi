@extends('layouts.admin')
@section('title', 'Kelola Kegiatan PKKMB — Tim Acara')

@section('content')
<meta name="google" content="notranslate">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<section>
  <div class="page-header">
    <div>
      <div class="page-title" style="display:flex;align-items:center;gap:10px">
        <span class="material-symbols-outlined" style="font-size:32px;color:var(--primary)">edit_calendar</span>
        Kelola Kegiatan PKKMB (Tim Acara)
      </div>
      <div class="page-sub">Tambah dan kelola sesi untuk setiap hari PKKMB</div>
    </div>
    <button class="btn btn-primary" onclick="openModalAdd()">
      <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">add_circle</span> 
      <span>Tambah Kegiatan</span>
    </button>
  </div>

  @if(session('success'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: @json(session('success')),
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
        text: @json(session('error')),
        confirmButtonColor: '#dc3545'
      });
    });
  </script>
  @endif

  @if($errors->any())
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      Swal.fire({
        icon: 'error',
        title: 'Error Validasi',
        html: '<ul style="text-align:left;margin:0;padding-left:20px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>',
        confirmButtonColor: '#dc3545'
      });
    });
  </script>
  @endif

  {{-- Statistics Cards --}}
  @php
    $totalSchedules = $schedules->count();
    $activeSchedules = $schedules->where('is_active', true)->count();
    $totalSesi = $schedules->sum(function($schedule) { return $schedule->sesi->count(); });
    $activeSesi = $schedules->flatMap->sesi->where('is_active', true)->count();
  @endphp
  
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px">
    <div class="info-card">
      <div class="info-icon" style="background:var(--primary-light);color:var(--primary)">
        <span class="material-symbols-outlined">calendar_today</span>
      </div>
      <div class="info-content">
        <div class="info-value">{{ $totalSchedules }}</div>
        <div class="info-label">Hari PKKMB</div>
      </div>
    </div>
    <div class="info-card">
      <div class="info-icon" style="background:var(--secondary-light);color:var(--secondary)">
        <span class="material-symbols-outlined">event_note</span>
      </div>
      <div class="info-content">
        <div class="info-value">{{ $totalSesi }}</div>
        <div class="info-label">Total Sesi</div>
      </div>
    </div>
    <div class="info-card">
      <div class="info-icon" style="background:var(--success-light);color:var(--success)">
        <span class="material-symbols-outlined">check_circle</span>
      </div>
      <div class="info-content">
        <div class="info-value">{{ $activeSesi }}</div>
        <div class="info-label">Sesi Aktif</div>
      </div>
    </div>
  </div>

  @if($schedules->isEmpty())
  <div class="empty-state-box">
    <span class="material-symbols-outlined empty-icon">event_busy</span>
    <h3 style="color:var(--text);margin:0 0 8px 0">Belum Ada Jadwal PKKMB</h3>
    <p style="color:var(--text-secondary);margin:0 0 24px 0">Silakan buat jadwal PKKMB terlebih dahulu sebelum menambahkan kegiatan/sesi.</p>
    <a href="{{ route('acara.pkkmb-schedule.index') }}" class="btn btn-primary">
      <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">calendar_add_on</span>
      Buat Jadwal PKKMB
    </a>
  </div>
  @else
  
  @foreach($schedules as $schedule)
  <div class="day-schedule-card {{ $schedule->is_active ? 'active' : 'inactive' }}">
    <div class="day-schedule-header">
      <div class="day-schedule-info">
        <div class="day-schedule-title">
          <span class="material-symbols-outlined">event</span>
          PKKMB Hari ke-{{ $schedule->hari_ke }}
        </div>
        <div class="day-schedule-meta">
          <span class="meta-item">
            <span class="material-symbols-outlined">calendar_today</span>
            {{ $schedule->formatted_date }}
          </span>
          <span class="meta-item">
            <span class="material-symbols-outlined">login</span>
            {{ Carbon\Carbon::parse($schedule->check_in_start)->format('H:i') }} - {{ Carbon\Carbon::parse($schedule->check_in_end)->format('H:i') }}
          </span>
          <span class="meta-item">
            <span class="material-symbols-outlined">logout</span>
            {{ Carbon\Carbon::parse($schedule->check_out_start)->format('H:i') }} - {{ Carbon\Carbon::parse($schedule->check_out_end)->format('H:i') }}
          </span>
        </div>
      </div>
      <span class="badge {{ $schedule->is_active ? 'badge-green' : 'badge-gray' }}">
        {{ $schedule->is_active ? 'Aktif' : 'Nonaktif' }}
      </span>
    </div>

    @if($schedule->sesi->isEmpty())
    <div class="empty-sesi-state">
      <span class="material-symbols-outlined">inbox</span>
      <p>Belum ada kegiatan/sesi untuk hari ini</p>
      <button class="btn btn-sm btn-primary" onclick="openModalAdd({{ $schedule->id }})">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">add</span>
        Tambah Sesi
      </button>
    </div>
    @else
    <div class="sesi-list">
      @foreach($schedule->sesi as $index => $sesi)
      <div class="sesi-item {{ $sesi->is_active ? 'sesi-active' : 'sesi-inactive' }}">
        <div class="sesi-main">
          <div class="sesi-number-badge">{{ $index + 1 }}</div>
          <div class="sesi-info">
            <div class="sesi-name">{{ $sesi->nama_sesi }}</div>
            <div class="sesi-meta">
              @if($sesi->jam_mulai && $sesi->jam_selesai)
              <span class="meta-time">
                <span class="material-symbols-outlined">schedule</span>
                {{ Carbon\Carbon::parse($sesi->jam_mulai)->format('H:i') }} - {{ Carbon\Carbon::parse($sesi->jam_selesai)->format('H:i') }}
              </span>
              @endif
              <span class="meta-attendance">
                <span class="material-symbols-outlined">group</span>
                {{ $sesi->total_hadir }} mahasiswa
              </span>
              <span class="badge badge-sm {{ $sesi->is_active ? 'badge-green' : 'badge-gray' }}">
                {{ $sesi->is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </div>
          </div>
        </div>
        
        <div class="sesi-actions-compact">
          <form method="POST" action="{{ route('acara.kegiatan.toggle', $sesi->id) }}" style="margin:0">
            @csrf
            <button type="submit" class="action-btn-compact btn-toggle-compact" title="{{ $sesi->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
              <span class="material-symbols-outlined">{{ $sesi->is_active ? 'pause_circle' : 'play_circle' }}</span>
            </button>
          </form>
          <button class="action-btn-compact btn-edit-compact" title="Edit" onclick="openEditSesi({{ $sesi->id }}, {{ $schedule->id }}, '{{ addslashes($sesi->nama_sesi) }}', '{{ $sesi->jam_mulai }}', '{{ $sesi->jam_selesai }}', {{ $sesi->is_active ? 1 : 0 }})">
            <span class="material-symbols-outlined">edit</span>
          </button>
          <form method="POST" action="{{ route('acara.kegiatan.destroy', $sesi->id) }}" onsubmit="return confirm('Hapus sesi ini?')">
            @csrf @method('DELETE')
            <button type="submit" class="action-btn-compact btn-delete-compact" title="Hapus">
              <span class="material-symbols-outlined">delete</span>
            </button>
          </form>
        </div>
      </div>
      @endforeach
    </div>
    @endif
  </div>
  @endforeach
  
  @endif

  {{-- Info Panel --}}
  <div class="panel" style="margin-top:24px;background:var(--primary-light);border:1px solid var(--primary)">
    <div style="display:flex;gap:12px">
      <span class="material-symbols-outlined" style="font-size:32px;color:var(--primary)">info</span>
      <div style="flex:1">
        <strong style="color:var(--primary-dark);font-size:15px;display:block;margin-bottom:8px">💡 Informasi Tim Acara</strong>
        <ul style="margin:0;padding-left:20px;color:var(--primary-dark);line-height:1.8;font-size:14px">
          <li>Tim Acara berwenang membuat, memperbarui, dan mengatur status keaktifan sesi kegiatan PKKMB.</li>
          <li>Pelaksanaan absensi di lapangan akan ditangani oleh Tim Disiplin dan Garda pada kompi masing-masing.</li>
        </ul>
      </div>
    </div>
  </div>
</section>

{{-- Modal Add --}}
<div class="modal-backdrop" id="modal-add-sesi">
  <div class="modal modal-large">
    <div class="modal-header">
      <div class="modal-title">
        <span class="material-symbols-outlined">add_circle</span>
        Tambah Kegiatan (Sesi)
      </div>
      <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('show')">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <form method="POST" action="{{ route('acara.kegiatan.store') }}" id="form-add-sesi">
      @csrf
      <div class="modal-body">
        <div class="form-row">
          <label class="form-label">
            <span class="material-symbols-outlined label-icon">event</span>
            PKKMB Hari Ke- *
          </label>
          <select name="pkkmb_schedule_id" id="add-pkkmb-schedule" class="form-input" required>
            <option value="">-- Pilih Hari PKKMB --</option>
            @foreach($schedules as $sch)
            <option value="{{ $sch->id }}">
              Hari ke-{{ $sch->hari_ke }} ({{ $sch->formatted_date }})
            </option>
            @endforeach
          </select>
          @error('pkkmb_schedule_id')
          <small style="color:var(--danger)">{{ $message }}</small>
          @enderror
        </div>
        <div class="form-row">
          <label class="form-label">
            <span class="material-symbols-outlined label-icon">label</span>
            Nama Kegiatan/Sesi *
          </label>
          <input name="nama_sesi" id="add-nama-sesi" class="form-input" placeholder="Contoh: Sesi 1 - Upacara Pembukaan" required value="{{ old('nama_sesi') }}">
          <small style="color:var(--text-muted);font-size:12px;margin-top:4px;display:block">Nama kegiatan/sesi yang akan diabsen manual</small>
          @error('nama_sesi')
          <small style="color:var(--danger)">{{ $message }}</small>
          @enderror
        </div>
        <div class="form-section">
          <div class="form-section-title">
            <span class="material-symbols-outlined">schedule</span>
            Waktu Kegiatan (Opsional)
          </div>
          <div class="form-grid">
            <div class="form-row">
              <label class="form-label">Jam Mulai</label>
              <input type="time" name="jam_mulai" id="add-jam-mulai" class="form-input" value="{{ old('jam_mulai') }}">
              @error('jam_mulai')
              <small style="color:var(--danger)">{{ $message }}</small>
              @enderror
            </div>
            <div class="form-row">
              <label class="form-label">Jam Selesai</label>
              <input type="time" name="jam_selesai" id="add-jam-selesai" class="form-input" value="{{ old('jam_selesai') }}">
              @error('jam_selesai')
              <small style="color:var(--danger)">{{ $message }}</small>
              @enderror
            </div>
          </div>
        </div>
        <div class="form-row">
          <label class="form-label checkbox-label">
            <input type="checkbox" name="is_active" value="1" checked class="form-checkbox">
            <span>Aktifkan sesi ini</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <span class="material-symbols-outlined">save</span>
          Simpan
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Edit --}}
<div class="modal-backdrop" id="modal-edit-sesi">
  <div class="modal modal-large">
    <div class="modal-header">
      <div class="modal-title">
        <span class="material-symbols-outlined">edit</span>
        Edit Kegiatan (Sesi)
      </div>
      <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('show')">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <form method="POST" id="edit-sesi-form">
      @csrf @method('PUT')
      <div class="modal-body">
        <div class="form-row">
          <label class="form-label">
            <span class="material-symbols-outlined label-icon">event</span>
            PKKMB Hari Ke-
          </label>
          <input type="text" id="edit-pkkmb-display" class="form-input" disabled>
        </div>
        <div class="form-row">
          <label class="form-label">
            <span class="material-symbols-outlined label-icon">label</span>
            Nama Kegiatan/Sesi *
          </label>
          <input name="nama_sesi" id="edit-nama-sesi" class="form-input" required>
        </div>
        <div class="form-section">
          <div class="form-section-title">
            <span class="material-symbols-outlined">schedule</span>
            Waktu Kegiatan (Opsional)
          </div>
          <div class="form-grid">
            <div class="form-row">
              <label class="form-label">Jam Mulai</label>
              <input type="time" name="jam_mulai" id="edit-jam-mulai" class="form-input">
            </div>
            <div class="form-row">
              <label class="form-label">Jam Selesai</label>
              <input type="time" name="jam_selesai" id="edit-jam-selesai" class="form-input">
            </div>
          </div>
        </div>
        <div class="form-row">
          <label class="form-label checkbox-label">
            <input type="checkbox" name="is_active" id="edit-is-active" value="1" class="form-checkbox">
            <span>Aktifkan sesi ini</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <span class="material-symbols-outlined">save</span>
          Update
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openModalAdd(scheduleId = null) {
  const scheduleCount = {{ $schedules->count() }};
  
  if (scheduleCount === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Belum Ada Jadwal PKKMB',
      text: 'Silakan buat jadwal PKKMB terlebih dahulu',
      confirmButtonColor: '#ffc107',
      confirmButtonText: 'Buat Jadwal',
      showCancelButton: true,
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = '{{ route("acara.pkkmb-schedule.index") }}';
      }
    });
    return;
  }
  
  if (scheduleId) {
    document.getElementById('add-pkkmb-schedule').value = scheduleId;
  }
  
  document.getElementById('modal-add-sesi').classList.add('show');
}

function openEditSesi(sesiId, scheduleId, nama, jamMulai, jamSelesai, isActive) {
  const schedules = @json($schedules);
  const schedule = schedules.find(s => s.id === scheduleId);
  const displayName = schedule ? `Hari ke-${schedule.hari_ke} (${new Date(schedule.tanggal).toLocaleDateString('id-ID')})` : 'Unknown';
  
  document.getElementById('edit-sesi-form').action = '/acara/kegiatan/' + sesiId;
  document.getElementById('edit-pkkmb-display').value = displayName;
  document.getElementById('edit-nama-sesi').value = nama;
  document.getElementById('edit-jam-mulai').value = jamMulai ? jamMulai.substring(0, 5) : '';
  document.getElementById('edit-jam-selesai').value = jamSelesai ? jamSelesai.substring(0, 5) : '';
  document.getElementById('edit-is-active').checked = isActive == 1;
  document.getElementById('modal-edit-sesi').classList.add('show');
}
</script>

<style>
.info-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: var(--space-md); display: flex; align-items: center; gap: var(--space-md); transition: all 0.3s ease; }
.info-card:hover { border-color: var(--primary); box-shadow: var(--shadow-md); transform: translateY(-2px); }
.info-icon { width: 48px; height: 48px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 24px; }
.info-content { flex: 1; }
.info-value { font-size: 28px; font-weight: 700; color: var(--text); line-height: 1; margin-bottom: 4px; }
.info-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
.empty-state-box { text-align: center; padding: 60px 20px; background: var(--bg); border-radius: var(--radius-lg); border: 2px dashed var(--border); }
.empty-icon { font-size: 80px; color: var(--text-muted); opacity: 0.3; display: block; margin-bottom: 16px; }
.day-schedule-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); margin-bottom: var(--space-lg); overflow: hidden; transition: all 0.3s ease; }
.day-schedule-card.active { border-color: var(--success); }
.day-schedule-card.inactive { opacity: 0.8; }
.day-schedule-header { padding: var(--space-md); background: var(--bg); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-sm); }
.day-schedule-info { flex: 1; min-width: 300px; }
.day-schedule-title { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; color: var(--text); }
.day-schedule-meta { display: flex; flex-wrap: wrap; gap: var(--space-md); font-size: 13px; color: var(--text-secondary); }
.meta-item { display: flex; align-items: center; gap: 4px; }
.empty-sesi-state { text-align: center; padding: 48px 20px; color: var(--text-muted); background: var(--bg); border-radius: var(--radius-md); margin: var(--space-md); }
.sesi-list { padding: var(--space-md); display: flex; flex-direction: column; gap: var(--space-sm); }
.sesi-item { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--space-md); display: flex; justify-content: space-between; align-items: center; gap: var(--space-md); transition: all 0.2s ease; }
.sesi-item.sesi-active { border-color: var(--success); background: var(--success-light); }
.sesi-main { display: flex; align-items: center; gap: var(--space-md); flex: 1; min-width: 0; }
.sesi-number-badge { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; }
.sesi-info { flex: 1; min-width: 0; }
.sesi-name { font-size: 15px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
.sesi-meta { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-sm); font-size: 12px; color: var(--text-secondary); }
.sesi-actions-compact { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.action-btn-compact { padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; color: var(--text-secondary); }
.btn-toggle-compact { color: var(--warning); border-color: var(--warning); }
.btn-edit-compact { color: var(--primary); border-color: var(--primary); }
.btn-delete-compact { color: var(--danger); border-color: var(--danger); }
.modal-large { max-width: 600px; }
</style>
@endsection
