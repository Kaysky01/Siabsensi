@extends('layouts.admin')
@section('title', 'Dashboard Garda — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Dashboard Garda</div>
      <div class="page-sub">{{ Carbon\Carbon::today()->translatedFormat('l, d F Y') }}</div>
      <div style="display:flex;align-items:center;gap:12px;margin-top:8px">
        <span class="material-symbols-outlined" style="font-size:20px;color:var(--primary)">account_circle</span>
        <span style="font-weight:600">{{ Auth::user()->full_name }}</span>
        <span style="color:var(--text-muted)">Kompi: {{ auth()->user()->assigned_kompi ?? '-' }}</span>
      </div>
    </div>
    <div class="header-actions">
      <a href="{{ route('garda.dashboard') }}" class="btn btn-ghost btn-sm">
        <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">refresh</span> Refresh
      </a>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card stat-card-blue">
      <span class="material-symbols-outlined stat-icon">group</span>
      <div class="stat-label">Total Mahasiswa</div>
      <div class="stat-value">{{ $totalMahasiswa }}</div>
      <div class="stat-delta">Kompi {{ auth()->user()->assigned_kompi }}</div>
    </div>
    <div class="stat-card stat-card-white">
      <span class="material-symbols-outlined stat-icon" style="color:#10b981">task_alt</span>
      <div class="stat-label">Hadir Hari Ini</div>
      <div class="stat-value">{{ $presentToday }}</div>
      <div class="stat-delta"><span class="up">{{ $presentPct }}%</span> kehadiran</div>
    </div>
    <div class="stat-card stat-card-white">
      <span class="material-symbols-outlined stat-icon" style="color:#f59e0b">person_off</span>
      <div class="stat-label">Tidak Hadir</div>
      <div class="stat-value">{{ $absentToday }}</div>
      <div class="stat-delta">Belum absen masuk</div>
    </div>
    <div class="stat-card stat-card-white">
      <span class="material-symbols-outlined stat-icon" style="color:#8b5cf6">description</span>
      <div class="stat-label">Izin/Sakit</div>
      <div class="stat-value">{{ $izinTotal }}</div>
      <div class="stat-delta">
        <span class="up">{{ $izinApproved }} disetujui</span> &bull;
        {{ $izinPending }} pending &bull;
        {{ $izinRejected }} ditolak
      </div>
    </div>
    <div class="stat-card stat-card-white">
      <span class="material-symbols-outlined stat-icon" style="color:#f97316">how_to_reg</span>
      <div class="stat-label">Kehadiran Manual</div>
      <div class="stat-value">{{ $kehadiranManualTotal }}</div>
      <div class="stat-delta">
        <span class="up">{{ $kehadiranManualApproved }} disetujui</span> &bull;
        {{ $kehadiranManualPending }} pending &bull;
        {{ $kehadiranManualRejected }} ditolak
      </div>
    </div>
  </div>

  <div class="three-col">
      <div class="panel">
        <div class="section-header">
          <div class="section-title">Kehadiran Terbaru</div>
          <a href="{{ route('garda.riwayat') }}" class="btn btn-ghost btn-sm">Lihat Riwayat <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle">arrow_forward</span></a>
        </div>
        <table class="att-table">
          <thead>
            <tr><th>Mahasiswa</th><th>Kegiatan</th><th>Jam Masuk</th><th>Status</th></tr>
          </thead>
          <tbody>
            @forelse($recentAttendances as $att)
            <tr>
              <td>
                <div class="mahasiswa-cell">
                  @php
                    $photoUrl = null;
                    if (!empty($att->photo_path)) {
                      $path = $att->photo_path;
                      if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                        $photoUrl = $path;
                      } else {
                        $cleanPath = ltrim(str_replace(['public/', 'storage/'], '', $path), '/');
                        $photoUrl = url('/file-bukti/' . $cleanPath);
                      }
                    }
                  @endphp
                  @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="{{ $att->name }}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #3b82f6;flex-shrink:0;">
                  @else
                    <div class="avatar" style="background:var(--primary-light);color:var(--primary)">{{ strtoupper(substr($att->name, 0, 2)) }}</div>
                  @endif
                  <div>
                    <div class="mhs-name">{{ $att->name }}</div>
                  </div>
                </div>
              </td>
              <td>
                @if(isset($att->sesi))
                  <span class="badge badge-blue">{{ $att->sesi->nama_sesi }}</span>
                @else
                  <span style="color:var(--text-muted);font-size:12px">-</span>
                @endif
              </td>
              <td>
                @if($att->absen_by)
                  <span class="badge" style="background:#e0f2fe;color:#0369a1;border:1px solid #0284c7;font-size:11px" title="Waktu: {{ $att->check_in ? Carbon\Carbon::parse($att->check_in)->format('H:i') : '-' }}">
                    Kehadiran Manual (oleh {{ $att->absen_by }})
                  </span>
                @else
                  <span class="time-val">{{ $att->check_in ? Carbon\Carbon::parse($att->check_in)->format('H:i') : '-' }}</span>
                @endif
              </td>
              <td>
                @php
                  $statusClass = match($att->status) {
                      'present', 'hadir' => 'badge-green',
                      'izin' => 'badge-blue',
                      'sakit' => 'badge-yellow',
                      default => 'badge-red'
                  };
                @endphp
                <span class="badge {{ $statusClass }}">{{ strtoupper($att->status ?? 'ALPHA') }}</span>
              </td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:30px">Belum ada data absensi hari ini</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

    <div style="display:flex;flex-direction:column;gap:16px">
      <div class="panel">
        <div class="section-header">
          <div class="section-title">Kegiatan Aktif</div>
        </div>
        <div class="kegiatan-list">
          @forelse($activeKegiatan as $keg)
          <a href="{{ route('garda.absensi-manual.index', $keg->id) }}" class="kegiatan-item">
            <div class="kegiatan-info">
              <span class="kegiatan-name">{{ $keg->nama_sesi }}</span>
              <span class="kegiatan-meta">
                @if($keg->pkkmbSchedule) H{{ $keg->pkkmbSchedule->hari_ke }} • @endif
                {{ \Carbon\Carbon::parse($keg->jam_mulai)->format('H:i') }}
              </span>
            </div>
            <span class="material-symbols-outlined">chevron_right</span>
          </a>
          @empty
          <p style="color:var(--text-muted);text-align:center;padding:16px">Belum ada kegiatan</p>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</section>

<style>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  padding: 20px;
  border-radius: var(--radius-lg);
  display: flex;
  flex-direction: column;
  gap: 12px;
  box-shadow: var(--shadow-md);
}

.stat-card-blue {
  background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
  color: white;
}

.stat-card-white {
  background: white;
  color: var(--text);
  border: 1px solid var(--border);
}

.stat-card-white .stat-label {
  color: var(--text-muted);
}

.stat-card-white .stat-value {
  color: var(--text);
}

.stat-card-white .stat-delta {
  color: var(--text-muted);
}

.stat-card-blue .stat-icon,
.stat-card-blue .stat-label,
.stat-card-blue .stat-value,
.stat-card-blue .stat-delta {
  color: white;
}

.stat-icon {
  font-size: 32px;
  opacity: 0.3;
}

.stat-label {
  font-size: 13px;
  opacity: 0.9;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  line-height: 1;
}

.stat-delta {
  font-size: 12px;
  opacity: 0.8;
}

.stat-delta .up {
  color: #10b981;
  font-weight: 600;
}

.three-col {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 16px;
}

.three-col .panel {
  margin-bottom: 0;
}

.mahasiswa-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 12px;
}

.mhs-name {
  font-weight: 600;
  font-size: 14px;
}

.mhs-dept {
  font-size: 12px;
  color: var(--text-muted);
  margin-top: 2px;
}

.time-val {
  font-family: var(--mono);
  font-weight: 500;
  font-size: 13px;
}

.badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.badge-green {
  background: var(--success-light);
  color: var(--success);
}

.badge-blue {
  background: var(--info-light);
  color: var(--info);
}

.badge-yellow {
  background: #ffeaa7;
  color: #d63031;
}

.badge-red {
  background: var(--danger-light);
  color: var(--danger);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--text);
}

.dept-item {
  padding: 12px 0;
  border-bottom: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.dept-item:last-child {
  border-bottom: none;
}

.dept-name {
  font-weight: 600;
  font-size: 14px;
}

.dept-bar-wrap {
  width: 100%;
  height: 8px;
  background: var(--border);
  border-radius: 4px;
  overflow: hidden;
}

.dept-bar-fill {
  height: 100%;
  background: var(--primary);
  border-radius: 4px;
}

.dept-count {
  font-size: 12px;
  color: var(--text-muted);
  font-weight: 600;
}

.kegiatan-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.kegiatan-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  text-decoration: none;
  color: var(--text);
  transition: all 0.2s;
}

.kegiatan-item:hover {
  border-color: var(--primary);
  box-shadow: var(--shadow-sm);
}

.kegiatan-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.kegiatan-name {
  font-weight: 600;
  font-size: 15px;
}

.kegiatan-meta {
  font-size: 12px;
  color: var(--text-muted);
}

.kegiatan-item .material-symbols-outlined {
  font-size: 20px;
  color: var(--text-muted);
}

.att-table {
  width: 100%;
  border-collapse: collapse;
}

.att-table thead th {
  background: var(--bg);
  padding: 12px;
  text-align: left;
  font-weight: 600;
  font-size: 12px;
  color: var(--text-muted);
  border-bottom: 2px solid var(--border);
}

.att-table tbody td {
  padding: 12px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
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

.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
}

@media (max-width: 768px) {
  .three-col {
    grid-template-columns: 1fr;
  }
  
  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
@endsection
