@extends('layouts.admin')
@section('title', 'Absensi Persesi — SIABSEN')

@section('content')
<meta name="google" content="notranslate">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<section>
  <div class="page-header">
    <div>
      <div class="page-title">Absensi Persesi</div>
      <div class="page-sub">Kompi: <strong style="color:var(--primary)">{{ auth()->user()->assigned_kompi ?? '-' }}</strong> — Pilih sesi untuk melakukan absensi</div>
    </div>
    <form method="GET" action="{{ route('garda.absensi-persesi') }}" style="display:flex;gap:8px;align-items:center">
      <select name="kompi" class="form-input" onchange="this.form.submit()" style="padding:6px 12px;border-radius:6px">
        <option value="all" {{ $filterKompi == 'all' ? 'selected' : '' }}>Semua Kompi</option>
        @foreach($kompiOptions as $k)
          <option value="{{ $k }}" {{ $filterKompi == $k ? 'selected' : '' }}>{{ $k }}</option>
        @endforeach
      </select>
    </form>
  </div>

  @if($schedules->isEmpty())
  <div class="empty-state-box">
    <span class="material-symbols-outlined empty-icon">event_busy</span>
    <h3 style="color:var(--text);margin:0 0 8px 0">Belum Ada Jadwal Aktif</h3>
    <p style="color:var(--text-secondary);margin:0 0 24px 0">Tidak ada jadwal PKKMB yang aktif saat ini</p>
    <a href="{{ route('garda.dashboard') }}" class="btn btn-primary">
      <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle">arrow_back</span>
      Kembali ke Dashboard
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
          <a href="{{ route('garda.absen-kegiatan', $sesi->id) }}" class="btn btn-ghost">
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
</section>

<style>
.day-schedule-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  margin-bottom: 20px;
  overflow: hidden;
}

.day-schedule-card.active {
  border-color: var(--success);
}

.day-schedule-header {
  padding: 16px;
  background: var(--bg);
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.day-schedule-info {
  flex: 1;
}

.day-schedule-title {
  font-size: 18px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.day-schedule-meta {
  display: flex;
  gap: 16px;
  font-size: 13px;
  color: var(--text-secondary);
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 4px;
}

.empty-state-box {
  text-align: center;
  padding: 60px 20px;
  background: var(--surface);
  border: 2px dashed var(--border);
  border-radius: var(--radius-lg);
}

.empty-icon {
  font-size: 80px;
  color: var(--text-muted);
  opacity: 0.3;
}

.empty-sesi-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--text-muted);
}

.sesi-list {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.sesi-item-absensi {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.sesi-item-absensi.sesi-active {
  border-color: var(--success);
  background: var(--success-light);
}

.sesi-main {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
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
}

.sesi-name {
  font-size: 15px;
  font-weight: 600;
  margin-bottom: 6px;
}

.sesi-meta {
  display: flex;
  gap: 16px;
  font-size: 12px;
  color: var(--text-secondary);
}

.meta-time, .meta-attendance {
  display: flex;
  align-items: center;
  gap: 4px;
}

.sesi-actions-absensi {
  display: flex;
  gap: 8px;
}

.badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}

.badge-green {
  background: var(--success-light);
  color: var(--success);
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: var(--radius-sm);
  border: none;
  cursor: pointer;
  font-weight: 600;
  text-decoration: none;
  font-size: 14px;
  transition: all 0.2s;
}

.btn-primary {
  background: var(--primary);
  color: white;
}

.btn-primary:hover {
  background: var(--primary-dark);
}

.btn-ghost {
  background: transparent;
  color: var(--text);
  border: 1px solid var(--border);
}

.btn-ghost:hover {
  background: var(--bg);
}

.form-input {
  padding: 8px 12px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 14px;
}

@media (max-width: 992px) {
  .sesi-item-absensi {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  
  .sesi-actions-absensi {
    width: 100%;
  }
  
  .sesi-actions-absensi .btn {
    flex: 1;
    justify-content: center;
  }
}
</style>
@endsection
