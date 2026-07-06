@extends('layouts.admin')
@section('title', 'Absensi Persesi — SIABSEN')

@section('content')
<meta name="google" content="notranslate">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<section>
  <div class="page-header">
    <div>
      <div class="page-title">Absensi Persesi</div>
      <div class="page-sub">Pilih sesi untuk melakukan absensi manual mahasiswa</div>
    </div>
  </div>

  @if($schedules->isEmpty())
  <div class="empty-state-box">
    <span class="material-symbols-outlined empty-icon">event_busy</span>
    <h3 style="color:var(--text);margin:0 0 8px 0">Belum Ada Jadwal Aktif</h3>
    <p style="color:var(--text-secondary);margin:0 0 24px 0">Tidak ada jadwal PKKMB yang aktif saat ini</p>
    <a href="{{ route('admin.pkkmb-schedule.index') }}" class="btn btn-primary">
      <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">calendar_month</span>
      Lihat Jadwal
    </a>
  </div>
  @else
  
  @foreach($schedules as $schedule)
  <div class="day-schedule-card active">
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
        </div>
      </div>
      <span class="badge badge-green">Aktif</span>
    </div>

    @if($schedule->sesi->isEmpty())
    <div class="empty-sesi-state">
      <span class="material-symbols-outlined">inbox</span>
      <p>Belum ada sesi untuk hari ini</p>
    </div>
    @else
    <div class="sesi-list">
      @foreach($schedule->sesi as $index => $sesi)
      <div class="sesi-item-absensi sesi-active">
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
                {{ $sesi->total_hadir }} mahasiswa hadir
              </span>
            </div>
          </div>
        </div>
        
        <div class="sesi-actions-absensi">
          <a href="{{ route('admin.absensi-manual.index', $sesi->id) }}" class="btn btn-primary">
            <span class="material-symbols-outlined">edit</span>
            Edit Absensi
          </a>
          <a href="{{ route('admin.monitoring-sesi', $sesi->id) }}" class="btn btn-ghost">
            <span class="material-symbols-outlined">visibility</span>
            Lihat Detail
          </a>
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
        <strong style="color:var(--primary-dark);font-size:15px;display:block;margin-bottom:8px">💡 Cara Menggunakan</strong>
        <ul style="margin:0;padding-left:20px;color:var(--primary-dark);line-height:1.8;font-size:14px">
          <li>Pilih sesi yang ingin diabsen dengan klik tombol <strong>"Edit Absensi"</strong></li>
          <li>Centang mahasiswa yang hadir pada sesi tersebut</li>
          <li>Klik tombol <strong>"Simpan Absensi"</strong> untuk menyimpan data</li>
          <li>Data absensi sebelumnya akan <strong>diganti</strong> dengan data baru yang Anda simpan</li>
          <li>Gunakan tombol <strong>"Lihat Detail"</strong> untuk melihat rekap kehadiran</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<style>
/* Day Schedule Cards */
.day-schedule-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  margin-bottom: var(--space-lg);
  overflow: hidden;
  transition: all 0.3s ease;
}

.day-schedule-card:hover {
  box-shadow: var(--shadow-md);
}

.day-schedule-card.active {
  border-color: var(--success);
}

.day-schedule-header {
  padding: var(--space-md);
  background: var(--bg);
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-sm);
}

.day-schedule-info {
  flex: 1;
  min-width: 300px;
}

.day-schedule-title {
  font-size: 18px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  color: var(--text);
}

.day-schedule-title .material-symbols-outlined {
  font-size: 22px;
  color: var(--primary);
}

.day-schedule-meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-md);
  font-size: 13px;
  color: var(--text-secondary);
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 4px;
}

.meta-item .material-symbols-outlined {
  font-size: 16px;
  color: var(--text-muted);
}

/* Empty State */
.empty-state-box {
  text-align: center;
  padding: 60px 20px;
  background: var(--bg);
  border-radius: var(--radius-lg);
  border: 2px dashed var(--border);
}

.empty-icon {
  font-size: 80px;
  color: var(--text-muted);
  opacity: 0.3;
  display: block;
  margin-bottom: 16px;
}

/* Empty Sesi State */
.empty-sesi-state {
  text-align: center;
  padding: 48px 20px;
  color: var(--text-muted);
  background: var(--bg);
  border-radius: var(--radius-md);
  margin: var(--space-md);
}

.empty-sesi-state .material-symbols-outlined {
  font-size: 64px;
  opacity: 0.3;
  display: block;
  margin-bottom: 16px;
}

.empty-sesi-state p {
  margin: 0;
  font-size: 15px;
  color: var(--text-secondary);
}

/* Sesi List */
.sesi-list {
  padding: var(--space-md);
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
}

.sesi-item-absensi {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: var(--space-md);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-md);
  transition: all 0.2s ease;
}

.sesi-item-absensi:hover {
  border-color: var(--primary);
  box-shadow: var(--shadow-sm);
}

.sesi-item-absensi.sesi-active {
  border-color: var(--success);
  background: var(--success-light);
}

.sesi-main {
  display: flex;
  align-items: center;
  gap: var(--space-md);
  flex: 1;
  min-width: 0;
}

.sesi-number-badge {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--primary);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  flex-shrink: 0;
}

.sesi-info {
  flex: 1;
  min-width: 0;
}

.sesi-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sesi-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-sm);
  font-size: 12px;
  color: var(--text-secondary);
}

.meta-time,
.meta-attendance {
  display: flex;
  align-items: center;
  gap: 4px;
  font-family: var(--font-mono);
  font-weight: 500;
}

.meta-time .material-symbols-outlined,
.meta-attendance .material-symbols-outlined {
  font-size: 14px;
  color: var(--text-muted);
}

.sesi-actions-absensi {
  display: flex;
  gap: var(--space-sm);
  flex-wrap: wrap;
}

/* Responsive Design */
@media (max-width: 992px) {
  .sesi-item-absensi {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .sesi-actions-absensi {
    width: 100%;
  }
}

@media (max-width: 768px) {
  .day-schedule-meta {
    flex-direction: column;
    gap: 8px;
    align-items: flex-start;
  }
  
  .sesi-name {
    white-space: normal;
  }
}
</style>
@endsection
