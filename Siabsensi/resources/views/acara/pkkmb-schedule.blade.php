@extends('layouts.admin')
@section('title', 'Jadwal Absensi — Tim Acara')

@section('content')
<meta name="google" content="notranslate">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<section>
  <div class="page-header">
    <div>
      <div class="page-title" style="display:flex;align-items:center;gap:10px">
        <span class="material-symbols-outlined" style="font-size:32px;color:var(--primary)">event</span>
        Jadwal Absensi PKKMB (Tim Acara)
      </div>
      <div class="page-sub">Kelola jadwal absensi harian berdasarkan tanggal PKKMB</div>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('modal-add-schedule').classList.add('show')">
      <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">add_circle</span> 
      <span>Tambah Jadwal</span>
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

  {{-- Statistics Cards --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px">
    <div class="info-card">
      <div class="info-icon" style="background:var(--primary-light);color:var(--primary)">
        <span class="material-symbols-outlined">calendar_month</span>
      </div>
      <div class="info-content">
        <div class="info-value">{{ $schedules->count() }}</div>
        <div class="info-label">Total Jadwal</div>
      </div>
    </div>
    <div class="info-card">
      <div class="info-icon" style="background:var(--success-light);color:var(--success)">
        <span class="material-symbols-outlined">check_circle</span>
      </div>
      <div class="info-content">
        <div class="info-value">{{ $schedules->where('is_active', true)->count() }}</div>
        <div class="info-label">Jadwal Aktif</div>
      </div>
    </div>
    <div class="info-card">
      <div class="info-icon" style="background:var(--warning-light);color:#D4A017">
        <span class="material-symbols-outlined">schedule</span>
      </div>
      <div class="info-content">
        <div class="info-value">{{ $gracePeriod }} menit</div>
        <div class="info-label">Toleransi Keterlambatan</div>
      </div>
    </div>
  </div>

  {{-- Grace Period Configuration --}}
  <div class="panel" style="margin-bottom:24px">
    <div class="section-header">
      <div>
        <div class="section-title">
          <span class="material-symbols-outlined" style="vertical-align:middle;margin-right:8px">schedule</span>
          Batas Toleransi Keterlambatan
        </div>
        <div class="section-sub">Atur waktu toleransi untuk keterlambatan check-in</div>
      </div>
    </div>
    <form method="POST" action="{{ route('acara.pkkmb-schedule.gracePeriod') }}" style="display:flex;gap:16px;align-items:end;flex-wrap:wrap">
      @csrf
      <div class="form-row" style="flex:1;min-width:200px;margin:0">
        <label class="form-label">Toleransi Keterlambatan (menit)</label>
        <input type="number" name="grace_period_minutes" class="form-input" value="{{ $gracePeriod }}" min="0" max="120" required>
        <small class="form-hint">Mahasiswa masih bisa check-in hingga {{ $gracePeriod }} menit setelah batas dengan status telat</small>
      </div>
      <button type="submit" class="btn btn-primary">
        <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">save</span>
        Simpan
      </button>
    </form>
  </div>

  {{-- Schedules Table --}}
  <div class="panel">
    <div class="section-header">
      <div class="section-title">
        <span class="material-symbols-outlined" style="vertical-align:middle">calendar_view_month</span>
        Daftar Jadwal PKKMB
      </div>
      <div class="section-sub">{{ $schedules->count() }} jadwal terdaftar</div>
    </div>

    @if($schedules->isEmpty())
    <div class="empty-state-box">
      <span class="material-symbols-outlined empty-icon">event_busy</span>
      <h3 style="color:var(--text);margin:0 0 8px 0">Belum Ada Jadwal</h3>
      <p style="color:var(--text-secondary);margin:0 0 24px 0">Klik tombol "Tambah Jadwal" untuk membuat jadwal absensi PKKMB</p>
      <button class="btn btn-primary" onclick="document.getElementById('modal-add-schedule').classList.add('show')">
        <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">add_circle</span> 
        Tambah Jadwal Pertama
      </button>
    </div>
    @else
    <div class="schedule-grid">
      @foreach($schedules as $schedule)
      <div class="schedule-card {{ $schedule->is_active ? 'active' : 'inactive' }}">
        <div class="schedule-header">
          <div class="schedule-badge">
            <span class="badge-day">Hari {{ $schedule->hari_ke }}</span>
            <span class="badge-status {{ $schedule->is_active ? 'badge-active' : 'badge-inactive' }}">
              {{ $schedule->is_active ? '✓ Aktif' : '✕ Nonaktif' }}
            </span>
          </div>
          <div class="schedule-date">
            <span class="material-symbols-outlined">event</span>
            {{ $schedule->formatted_date }}
          </div>
        </div>
        
        <div class="schedule-times">
          <div class="time-block check-in">
            <div class="time-label">
              <span class="material-symbols-outlined">login</span>
              Check-in
            </div>
            <div class="time-range">
              <span class="time-value">{{ Carbon\Carbon::parse($schedule->check_in_start)->format('H:i') }}</span>
              <span class="time-separator">—</span>
              <span class="time-value">{{ Carbon\Carbon::parse($schedule->check_in_end)->format('H:i') }}</span>
            </div>
          </div>
          
          <div class="time-block check-out">
            <div class="time-label">
              <span class="material-symbols-outlined">logout</span>
              Check-out
            </div>
            <div class="time-range">
              <span class="time-value">{{ Carbon\Carbon::parse($schedule->check_out_start)->format('H:i') }}</span>
              <span class="time-separator">—</span>
              <span class="time-value">{{ Carbon\Carbon::parse($schedule->check_out_end)->format('H:i') }}</span>
            </div>
          </div>
        </div>
        
        <div class="schedule-actions">
          <form method="POST" action="{{ route('acara.pkkmb-schedule.toggle', $schedule->id) }}" style="margin:0">
            @csrf
            <button type="submit" class="btn-icon btn-toggle" title="{{ $schedule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
              <span class="material-symbols-outlined">{{ $schedule->is_active ? 'pause_circle' : 'play_circle' }}</span>
            </button>
          </form>
          <button class="btn-icon btn-edit" title="Edit" onclick="openEditSchedule({{ $schedule->id }}, {{ $schedule->hari_ke }}, '{{ $schedule->tanggal->format('Y-m-d') }}', '{{ Carbon\Carbon::parse($schedule->check_in_start)->format('H:i') }}', '{{ Carbon\Carbon::parse($schedule->check_in_end)->format('H:i') }}', '{{ Carbon\Carbon::parse($schedule->check_out_start)->format('H:i') }}', '{{ Carbon\Carbon::parse($schedule->check_out_end)->format('H:i') }}', {{ $schedule->is_active ? 1 : 0 }})">
            <span class="material-symbols-outlined">edit</span>
          </button>
          <form method="POST" action="{{ route('acara.pkkmb-schedule.destroy', $schedule->id) }}" id="delete-form-{{ $schedule->id }}" style="margin:0">
            @csrf @method('DELETE')
          </form>
          <button type="button" class="btn-icon btn-delete" title="Hapus"
            onclick="confirmDeleteSchedule({{ $schedule->id }}, {{ $schedule->hari_ke }}, '{{ $schedule->tanggal->format('d M Y') }}')">
            <span class="material-symbols-outlined">delete</span>
          </button>
        </div>
      </div>
      @endforeach
    </div>
    @endif
  </div>
</section>

{{-- Modal Add --}}
<div class="modal-backdrop" id="modal-add-schedule">
  <div class="modal modal-large">
    <div class="modal-header">
      <div class="modal-title">
        <span class="material-symbols-outlined">add_circle</span>
        Tambah Jadwal PKKMB
      </div>
      <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('show')">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <form method="POST" action="{{ route('acara.pkkmb-schedule.store') }}">
      @csrf
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-row">
            <label class="form-label">
              <span class="material-symbols-outlined label-icon">counter_1</span>
              PKKMB Hari Ke- *
            </label>
            <input type="number" name="hari_ke" class="form-input" min="1" placeholder="Contoh: 1" required>
          </div>
          <div class="form-row">
            <label class="form-label">
              <span class="material-symbols-outlined label-icon">event</span>
              Tanggal *
            </label>
            <input type="date" name="tanggal" class="form-input" required>
          </div>
        </div>
        
        <div class="form-section">
          <div class="form-section-title">
            <span class="material-symbols-outlined">login</span>
            Waktu Check-in
          </div>
          <div class="form-grid">
            <div class="form-row">
              <label class="form-label">Mulai *</label>
              <input type="text" name="check_in_start" class="form-input timepicker" placeholder="07:00" required>
            </div>
            <div class="form-row">
              <label class="form-label">Batas *</label>
              <input type="text" name="check_in_end" class="form-input timepicker" placeholder="08:00" required>
            </div>
          </div>
        </div>
        
        <div class="form-section">
          <div class="form-section-title">
            <span class="material-symbols-outlined">logout</span>
            Waktu Check-out
          </div>
          <div class="form-grid">
            <div class="form-row">
              <label class="form-label">Mulai *</label>
              <input type="text" name="check_out_start" class="form-input timepicker" placeholder="16:00" required>
            </div>
            <div class="form-row">
              <label class="form-label">Akhir *</label>
              <input type="text" name="check_out_end" class="form-input timepicker" placeholder="17:00" required>
            </div>
          </div>
        </div>
        
        <div class="form-row">
          <label class="form-label checkbox-label">
            <input type="checkbox" name="is_active" value="1" checked class="form-checkbox">
            <span>Aktifkan jadwal ini</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <span class="material-symbols-outlined">save</span>
          Simpan Jadwal
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Edit --}}
<div class="modal-backdrop" id="modal-edit-schedule">
  <div class="modal modal-large">
    <div class="modal-header">
      <div class="modal-title">
        <span class="material-symbols-outlined">edit</span>
        Edit Jadwal PKKMB
      </div>
      <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('show')">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <form method="POST" id="edit-schedule-form">
      @csrf @method('PUT')
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-row">
            <label class="form-label">
              <span class="material-symbols-outlined label-icon">counter_1</span>
              PKKMB Hari Ke- *
            </label>
            <input type="number" name="hari_ke" id="edit-hari-ke" class="form-input" min="1" required>
          </div>
          <div class="form-row">
            <label class="form-label">
              <span class="material-symbols-outlined label-icon">event</span>
              Tanggal *
            </label>
            <input type="date" name="tanggal" id="edit-tanggal" class="form-input" required>
          </div>
        </div>
        
        <div class="form-section">
          <div class="form-section-title">
            <span class="material-symbols-outlined">login</span>
            Waktu Check-in
          </div>
          <div class="form-grid">
            <div class="form-row">
              <label class="form-label">Mulai *</label>
              <input type="text" name="check_in_start" id="edit-check-in-start" class="form-input timepicker" placeholder="07:00" required>
            </div>
            <div class="form-row">
              <label class="form-label">Batas *</label>
              <input type="text" name="check_in_end" id="edit-check-in-end" class="form-input timepicker" placeholder="08:00" required>
            </div>
          </div>
        </div>
        
        <div class="form-section">
          <div class="form-section-title">
            <span class="material-symbols-outlined">logout</span>
            Waktu Check-out
          </div>
          <div class="form-grid">
            <div class="form-row">
              <label class="form-label">Mulai *</label>
              <input type="text" name="check_out_start" id="edit-check-out-start" class="form-input timepicker" placeholder="16:00" required>
            </div>
            <div class="form-row">
              <label class="form-label">Akhir *</label>
              <input type="text" name="check_out_end" id="edit-check-out-end" class="form-input timepicker" placeholder="17:00" required>
            </div>
          </div>
        </div>
        
        <div class="form-row">
          <label class="form-label checkbox-label">
            <input type="checkbox" name="is_active" id="edit-is-active" value="1" class="form-checkbox">
            <span>Aktifkan jadwal ini</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <span class="material-symbols-outlined">save</span>
          Update Jadwal
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  flatpickr('.timepicker', {
    enableTime: true,
    noCalendar: true,
    dateFormat: "H:i",
    time_24hr: true,
    minuteIncrement: 1,
    allowInput: true
  });
});

function setPickerValue(id, val) {
  const el = document.getElementById(id);
  if (!el) return;
  if (el._flatpickr) {
    el._flatpickr.setDate(val, true);
  } else {
    el.value = val;
  }
}

function openEditSchedule(id, hariKe, tanggal, checkInStart, checkInEnd, checkOutStart, checkOutEnd, isActive) {
  document.getElementById('edit-schedule-form').action = '/acara/pkkmb-schedule/' + id;
  document.getElementById('edit-hari-ke').value = hariKe;
  document.getElementById('edit-tanggal').value = tanggal;
  setPickerValue('edit-check-in-start', checkInStart);
  setPickerValue('edit-check-in-end', checkInEnd);
  setPickerValue('edit-check-out-start', checkOutStart);
  setPickerValue('edit-check-out-end', checkOutEnd);
  document.getElementById('edit-is-active').checked = isActive == 1;
  document.getElementById('modal-edit-schedule').classList.add('show');
}

function confirmDeleteSchedule(id, hariKe, tanggal) {
  Swal.fire({
    icon: 'warning',
    title: 'Hapus Jadwal PKKMB?',
    html: `
      <div style="text-align:left;font-size:14px;line-height:1.7">
        <p style="margin-bottom:10px">Anda akan menghapus <strong>Jadwal Hari ke-${hariKe}</strong> (${tanggal}).</p>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-bottom:10px">
          <strong style="color:#856404">⚠️ Data berikut juga akan ikut dihapus secara permanen:</strong>
          <ul style="margin:8px 0 0 0;padding-left:20px;color:#664d03">
            <li>Seluruh <b>sesi kegiatan</b> pada hari ini</li>
            <li>Seluruh <b>absensi per-sesi</b> (attendance sesi)</li>
            <li>Seluruh <b>absensi QR harian</b> pada tanggal ini</li>
          </ul>
        </div>
        <p style="color:#dc3545;margin:0"><strong>Tindakan ini tidak dapat dibatalkan!</strong></p>
      </div>
    `,
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: '<span style="font-size:14px">Ya, Hapus Beserta Absensi</span>',
    cancelButtonText: 'Batal',
    reverseButtons: true,
    focusCancel: true,
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById('delete-form-' + id).submit();
    }
  });
}
</script>

<style>
.info-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: var(--space-md); display: flex; align-items: center; gap: var(--space-md); transition: all 0.3s ease; }
.info-card:hover { border-color: var(--primary); box-shadow: var(--shadow-md); transform: translateY(-2px); }
.info-icon { width: 48px; height: 48px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 24px; }
.info-value { font-size: 24px; font-weight: 700; color: var(--text); line-height: 1; margin-bottom: 4px; }
.info-label { font-size: 13px; color: var(--text-secondary); }
.schedule-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: var(--space-md); margin-top: var(--space-md); }
.schedule-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; transition: all 0.3s ease; }
.schedule-card:hover { border-color: var(--primary); box-shadow: var(--shadow-md); transform: translateY(-2px); }
.schedule-card.active { border-color: var(--success); }
.schedule-card.inactive { opacity: 0.7; }
.schedule-header { padding: var(--space-md); background: var(--bg); border-bottom: 1px solid var(--border); }
.schedule-badge { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-sm); }
.badge-day { font-size: 18px; font-weight: 700; color: var(--text); }
.badge-status { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.badge-active { background: var(--success-light); color: var(--success); }
.badge-inactive { background: var(--border-light); color: var(--text-muted); }
.schedule-date { display: flex; align-items: center; gap: 6px; color: var(--text-secondary); font-size: 14px; }
.schedule-times { padding: var(--space-md); display: grid; gap: var(--space-sm); }
.time-block { background: var(--bg); padding: var(--space-sm); border-radius: var(--radius-sm); border-left: 3px solid var(--primary); }
.time-block.check-in { border-left-color: var(--success); }
.time-block.check-out { border-left-color: var(--secondary); }
.time-label { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; }
.time-range { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 15px; color: var(--text); }
.schedule-actions { padding: var(--space-sm) var(--space-md); background: var(--bg); border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; }
.btn-icon { width: 36px; height: 36px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--surface); color: var(--text-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; }
.btn-icon:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
.btn-delete:hover { border-color: var(--danger); color: var(--danger); background: var(--danger-light); }
.modal-large { max-width: 650px; }
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-md); }
.form-section { background: var(--bg); padding: var(--space-md); border-radius: var(--radius-md); margin-bottom: var(--space-md); border: 1px solid var(--border); }
.form-section-title { font-weight: 700; font-size: 14px; color: var(--text); margin-bottom: var(--space-md); display: flex; align-items: center; gap: 8px; }
.empty-state-box { text-align: center; padding: 60px 20px; background: var(--bg); border-radius: var(--radius-lg); border: 2px dashed var(--border); }
.empty-icon { font-size: 64px; color: var(--text-muted); margin-bottom: 16px; }
.flatpickr-calendar { z-index: 99999 !important; box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important; border-radius: 12px !important; }
</style>
@endsection
