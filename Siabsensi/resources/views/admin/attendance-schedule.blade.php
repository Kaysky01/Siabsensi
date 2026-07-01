@extends('layouts.admin')
@section('title', 'Jadwal Absensi — SIABSEN')

@section('content')
{{-- Prevent Google Translate from translating this page --}}
<meta name="google" content="notranslate">

<section>
  <div class="page-header">
    <div>
      <div class="page-title">Jadwal Absensi</div>
      <div class="page-sub">Atur jadwal absensi masuk dan keluar untuk setiap hari (Timezone: {{ config('app.timezone') }})</div>
    </div>
  </div>

  {{-- Success/Error Messages --}}
  @if(session('success'))
  <div class="alert alert-success" style="margin-bottom:20px">
    {{ session('success') }}
  </div>
  @endif

  @if(session('error'))
  <div class="alert alert-danger" style="margin-bottom:20px">
    {{ session('error') }}
  </div>
  @endif

  @if($errors->any())
  <div class="alert alert-danger" style="margin-bottom:20px">
    <strong>Error:</strong>
    <ul style="margin:8px 0 0 20px">
      @foreach($errors->all() as $error)
      <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  {{-- Grace Period Configuration --}}
  <div class="panel" style="max-width:600px;margin-bottom:24px">
    <div class="section-header">
      <div class="section-title">Pengaturan Grace Period</div>
    </div>
    <form method="POST" action="{{ route('admin.schedule.gracePeriod') }}">
      @csrf
      <div class="form-row">
        <label class="form-label">Grace Period (Toleransi Keterlambatan)</label>
        <input type="number" name="grace_period_minutes" class="form-input" value="{{ $gracePeriod }}" min="0" max="120" required>
        <small style="color:var(--text-muted);font-size:12px">
          Waktu toleransi dalam menit setelah batas check-in dimana mahasiswa masih bisa absen tetapi dianggap telat. 
          <br>Contoh: Jika batas check-in 06:50 dan grace period 40 menit, mahasiswa bisa check-in sampai jam 07:30 (dengan status telat).
        </small>
      </div>
      <div style="margin-top:16px">
        <button type="submit" class="btn btn-primary">Simpan Grace Period</button>
      </div>
    </form>
  </div>

  {{-- Schedule Table --}}
  <div class="panel">
    <div class="section-header">
      <div class="section-title">Jadwal Mingguan</div>
      <div class="section-sub">Atur jadwal untuk setiap hari dalam seminggu</div>
    </div>

    <form method="POST" action="{{ route('admin.schedule.bulkUpdate') }}" id="scheduleForm" translate="no">
      @csrf
      
      <div style="overflow-x:auto">
        <table class="admin-table">
          <thead>
            <tr>
              <th style="width:120px">Hari</th>
              <th style="width:100px">Status</th>
              <th style="min-width:100px">Check-in Mulai</th>
              <th style="min-width:100px">Batas Check-in</th>
              <th style="min-width:100px">Check-out Mulai</th>
              <th style="min-width:100px">Check-out Akhir</th>
              <th style="width:80px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach($schedulesArray as $index => $item)
            <tr>
              <td>
                <strong>{{ $item['day_name'] }}</strong>
                <input type="hidden" name="schedules[{{ $index }}][day_of_week]" value="{{ $item['day_of_week'] }}">
              </td>
              
              <td>
                <label class="toggle-switch">
                  <input type="checkbox" 
                         name="schedules[{{ $index }}][is_active_checkbox]" 
                         value="1"
                         {{ $item['schedule'] && $item['schedule']->is_active ? 'checked' : '' }}
                         onchange="toggleScheduleRow(this, {{ $index }})">
                  <span class="toggle-slider"></span>
                </label>
                {{-- Hidden input yang akan selalu terkirim --}}
                <input type="hidden" 
                       name="schedules[{{ $index }}][is_active]" 
                       value="{{ $item['schedule'] && $item['schedule']->is_active ? '1' : '0' }}" 
                       class="is-active-{{ $index }}">
              </td>

              <td>
                <input type="time" 
                       name="schedules[{{ $index }}][check_in_start]" 
                       class="form-input form-input-sm schedule-input-{{ $index }}"
                       value="{{ $item['schedule'] ? \Carbon\Carbon::parse($item['schedule']->check_in_start)->format('H:i') : '' }}"
                       {{ !$item['schedule'] || !$item['schedule']->is_active ? 'disabled' : '' }}>
              </td>

              <td>
                <input type="time" 
                       name="schedules[{{ $index }}][check_in_end]" 
                       class="form-input form-input-sm schedule-input-{{ $index }}"
                       value="{{ $item['schedule'] ? \Carbon\Carbon::parse($item['schedule']->check_in_end)->format('H:i') : '' }}"
                       {{ !$item['schedule'] || !$item['schedule']->is_active ? 'disabled' : '' }}>
              </td>

              <td>
                <input type="time" 
                       name="schedules[{{ $index }}][check_out_start]" 
                       class="form-input form-input-sm schedule-input-{{ $index }}"
                       value="{{ $item['schedule'] ? \Carbon\Carbon::parse($item['schedule']->check_out_start)->format('H:i') : '' }}"
                       {{ !$item['schedule'] || !$item['schedule']->is_active ? 'disabled' : '' }}>
              </td>

              <td>
                <input type="time" 
                       name="schedules[{{ $index }}][check_out_end]" 
                       class="form-input form-input-sm schedule-input-{{ $index }}"
                       value="{{ $item['schedule'] ? \Carbon\Carbon::parse($item['schedule']->check_out_end)->format('H:i') : '' }}"
                       {{ !$item['schedule'] || !$item['schedule']->is_active ? 'disabled' : '' }}>
              </td>

              <td>
                @if($item['schedule'])
                <form method="POST" action="{{ route('admin.schedule.destroy', $item['day_of_week']) }}" style="display:inline" onsubmit="return confirm('Hapus jadwal untuk {{ $item['day_name'] }}?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn-icon btn-danger" title="Hapus jadwal">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div style="margin-top:24px;display:flex;gap:12px;align-items:center">
        <button type="submit" class="btn btn-primary" id="submitBtn">
          <i class="fas fa-save"></i> Simpan Semua Jadwal
        </button>
        <button type="button" class="btn btn-secondary" onclick="resetForm()">
          <i class="fas fa-undo"></i> Reset
        </button>
        <span style="font-size:12px;color:var(--text-muted)" id="submitStatus"></span>
      </div>
    </form>
  </div>

  {{-- Info Panel --}}
  <div class="panel" style="margin-top:24px;background:#f8f9fa">
    <div class="section-header">
      <div class="section-title">📖 Informasi</div>
    </div>
    <div style="font-size:14px;line-height:1.8;color:var(--text-secondary)">
      <p><strong>Cara kerja jadwal absensi:</strong></p>
      <ol style="margin-left:20px">
        <li><strong>Check-in Mulai:</strong> Waktu paling awal mahasiswa bisa check-in</li>
        <li><strong>Batas Check-in:</strong> Batas waktu check-in tepat waktu. Setelah ini = telat</li>
        <li><strong>Check-out Mulai:</strong> Waktu paling awal mahasiswa bisa check-out</li>
        <li><strong>Check-out Akhir:</strong> Batas waktu check-out. Setelah ini tidak bisa check-out</li>
      </ol>
      
      <p style="margin-top:16px"><strong>Contoh:</strong></p>
      <ul style="margin-left:20px">
        <li>Jadwal: 05:00 - 06:50 (check-in), 16:00 - 18:00 (check-out)</li>
        <li>Grace period: 40 menit</li>
        <li>Mahasiswa check-in jam <strong>07:10</strong> → <span style="color:#dc3545">TELAT 20 menit</span> (masih dalam grace period)</li>
        <li>Mahasiswa check-in jam <strong>07:35</strong> → <span style="color:#dc3545">DITOLAK</span> (melebihi grace period)</li>
        <li>Mahasiswa check-out jam <strong>15:30</strong> → <span style="color:#dc3545">DITOLAK</span> (terlalu awal)</li>
        <li>Mahasiswa check-out jam <strong>17:00</strong> → <span style="color:#28a745">DITERIMA</span></li>
      </ul>

      <p style="margin-top:16px;padding:12px;background:#fff;border-left:4px solid #17a2b8;border-radius:4px">
        <i class="fas fa-info-circle" style="color:#17a2b8"></i>
        <strong>Catatan:</strong> Absensi berbasis kegiatan (persesi) akan bypass validasi jadwal harian ini.
      </p>
      
      <p style="margin-top:12px;padding:12px;background:#fff;border-left:4px solid #9c27b0;border-radius:4px">
        <i class="fas fa-calendar-alt" style="color:#9c27b0"></i>
        <strong>Kegiatan Independence:</strong> Mahasiswa yang hadir pada kegiatan khusus tidak akan divalidasi terhadap jadwal harian dan tidak akan mendapat status telat. Validasi waktu hanya berlaku untuk absensi harian reguler.
      </p>
    </div>
  </div>
</section>

<style>
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 24px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: .3s;
  border-radius: 24px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .3s;
  border-radius: 50%;
}

input:checked + .toggle-slider {
  background-color: #28a745;
}

input:checked + .toggle-slider:before {
  transform: translateX(24px);
}

.form-input-sm {
  padding: 6px 10px;
  font-size: 14px;
}

.btn-icon {
  padding: 6px 10px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.btn-danger {
  background: #dc3545;
  color: white;
}

.btn-danger:hover {
  background: #c82333;
}

.admin-table th {
  background: #f8f9fa;
  padding: 12px;
  font-weight: 600;
  text-align: left;
  border-bottom: 2px solid #dee2e6;
}

.admin-table td {
  padding: 12px;
  border-bottom: 1px solid #dee2e6;
  vertical-align: middle;
}

.alert {
  padding: 12px 16px;
  border-radius: 6px;
  border-left: 4px solid;
}

.alert-success {
  background: #d4edda;
  border-color: #28a745;
  color: #155724;
}

.alert-danger {
  background: #f8d7da;
  border-color: #dc3545;
  color: #721c24;
}
</style>

<script>
function toggleScheduleRow(checkbox, index) {
  const inputs = document.querySelectorAll(`.schedule-input-${index}`);
  const hiddenInput = document.querySelector(`.is-active-${index}`);
  
  if (checkbox.checked) {
    // Enable time inputs and set is_active to 1
    inputs.forEach(input => input.disabled = false);
    if (hiddenInput) hiddenInput.value = '1';
  } else {
    // Disable time inputs and set is_active to 0
    inputs.forEach(input => input.disabled = true);
    if (hiddenInput) hiddenInput.value = '0';
  }
}

function resetForm() {
  if (confirm('Reset form ke nilai awal?')) {
    location.reload();
  }
}

// Form validation before submit
document.getElementById('scheduleForm').addEventListener('submit', function(e) {
  // IMPORTANT: Remove _method field if exists (can cause 405 error)
  const methodField = this.querySelector('input[name="_method"]');
  if (methodField) {
    console.warn('Removing _method field from form:', methodField.value);
    methodField.remove();
  }
  
  const checkboxes = document.querySelectorAll('input[type="checkbox"][name*="is_active"]');
  let hasActive = false;
  let validationErrors = [];
  
  checkboxes.forEach((cb, index) => {
    if (cb.checked) {
      hasActive = true;
      
      // Get all time inputs for this row
      const checkInStart = document.querySelector(`input[name="schedules[${index}][check_in_start]"]`);
      const checkInEnd = document.querySelector(`input[name="schedules[${index}][check_in_end]"]`);
      const checkOutStart = document.querySelector(`input[name="schedules[${index}][check_out_start]"]`);
      const checkOutEnd = document.querySelector(`input[name="schedules[${index}][check_out_end]"]`);
      
      // Check if any are empty
      if (!checkInStart || !checkInEnd || !checkOutStart || !checkOutEnd) return;
      
      if (!checkInStart.value || !checkInEnd.value || !checkOutStart.value || !checkOutEnd.value) {
        const dayNames = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        const dayOfWeek = document.querySelector(`input[name="schedules[${index}][day_of_week]"]`).value;
        validationErrors.push(`${dayNames[dayOfWeek]}: Semua waktu harus diisi`);
        return;
      }
      
      // Validate time order
      const timeToMinutes = (timeStr) => {
        const [h, m] = timeStr.split(':').map(Number);
        return h * 60 + m;
      };
      
      const t1 = timeToMinutes(checkInStart.value);
      const t2 = timeToMinutes(checkInEnd.value);
      const t3 = timeToMinutes(checkOutStart.value);
      const t4 = timeToMinutes(checkOutEnd.value);
      
      if (!(t1 < t2 && t2 < t3 && t3 < t4)) {
        const dayNames = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        const dayOfWeek = document.querySelector(`input[name="schedules[${index}][day_of_week]"]`).value;
        validationErrors.push(`${dayNames[dayOfWeek]}: Urutan waktu tidak valid (${checkInStart.value} < ${checkInEnd.value} < ${checkOutStart.value} < ${checkOutEnd.value})`);
      }
    }
  });
  
  if (!hasActive) {
    e.preventDefault();
    alert('Setidaknya satu hari harus aktif!');
    return false;
  }
  
  if (validationErrors.length > 0) {
    e.preventDefault();
    alert('Error validasi:\n\n' + validationErrors.join('\n'));
    return false;
  }
  
  // Validation passed - submit form traditionally (more reliable than AJAX)
  console.log('Validation passed, submitting form...');
  return true; // Let form submit naturally
});
</script>
@endsection
