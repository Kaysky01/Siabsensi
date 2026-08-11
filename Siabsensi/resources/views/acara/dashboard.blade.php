@extends('layouts.admin')
@section('title', 'Dashboard Tim Acara — SIABSEN')

@section('content')
<section>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:18px">
    <div style="display:flex;align-items:center;gap:12px">
      <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 6px 16px rgba(139,92,246,0.25)">
        <span class="material-symbols-outlined" style="font-size:24px">event_note</span>
      </div>
      <div>
        <h1 style="font-size:18px;font-weight:800;color:#0f172a;margin:0">Dashboard Tim Acara</h1>
        <div style="font-size:12px;color:#64748b;margin-top:1px">Selamat datang, <strong>{{ auth()->user()->full_name ?? auth()->user()->username }}</strong> — Kelola Jadwal & Sesi Kegiatan PKKMB</div>
      </div>
    </div>
  </div>

  {{-- STATS GRID --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;margin-bottom:18px">
    <div class="panel" style="margin:0;padding:14px 16px;border-radius:12px;box-shadow:0 2px 6px rgba(0,0,0,0.02)">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">Total Jadwal PKKMB</span>
        <span class="material-symbols-outlined" style="color:#8b5cf6;font-size:20px">calendar_month</span>
      </div>
      <div style="font-size:26px;font-weight:800;color:#0f172a;margin-top:6px;line-height:1">{{ $totalSchedules }} <span style="font-size:12px;color:#64748b;font-weight:600">Hari</span></div>
      <div style="font-size:11px;color:#10b981;margin-top:4px;font-weight:600">✓ {{ $activeSchedules }} Jadwal Aktif</div>
    </div>

    <div class="panel" style="margin:0;padding:14px 16px;border-radius:12px;box-shadow:0 2px 6px rgba(0,0,0,0.02)">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">Total Sesi Kegiatan</span>
        <span class="material-symbols-outlined" style="color:#3b82f6;font-size:20px">edit_calendar</span>
      </div>
      <div style="font-size:26px;font-weight:800;color:#0f172a;margin-top:6px;line-height:1">{{ $totalSesi }} <span style="font-size:12px;color:#64748b;font-weight:600">Sesi</span></div>
      <div style="font-size:11px;color:#10b981;margin-top:4px;font-weight:600">✓ {{ $activeSesi }} Sesi Aktif</div>
    </div>

    <div class="panel" style="margin:0;padding:14px 16px;border-radius:12px;box-shadow:0 2px 6px rgba(0,0,0,0.02)">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">Toleransi Keterlambatan</span>
        <span class="material-symbols-outlined" style="color:#f59e0b;font-size:20px">schedule</span>
      </div>
      <div style="font-size:26px;font-weight:800;color:#0f172a;margin-top:6px;line-height:1">{{ $gracePeriod }} <span style="font-size:12px;color:#64748b;font-weight:600">Menit</span></div>
      <div style="font-size:11px;color:#64748b;margin-top:4px">Grace period sistem absensi</div>
    </div>
  </div>

  {{-- QUICK ACTIONS --}}
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px">
    <a href="{{ route('acara.pkkmb-schedule.index') }}" style="text-decoration:none">
      <div class="panel" style="margin:0;padding:16px;border-radius:12px;border-left:4px solid #8b5cf6;transition:all 0.2s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
        <div style="display:flex;align-items:center;gap:12px">
          <div style="width:40px;height:40px;border-radius:10px;background:#f3e8ff;display:flex;align-items:center;justify-content:center;color:#8b5cf6;flex-shrink:0">
            <span class="material-symbols-outlined" style="font-size:22px">calendar_month</span>
          </div>
          <div>
            <div style="font-size:14px;font-weight:800;color:#0f172a">Kelola Jadwal Absensi PKKMB</div>
            <div style="font-size:11px;color:#64748b;margin-top:1px">Atur tanggal, jam check-in & check-out harian PKKMB</div>
          </div>
        </div>
      </div>
    </a>

    <a href="{{ route('acara.kegiatan') }}" style="text-decoration:none">
      <div class="panel" style="margin:0;padding:16px;border-radius:12px;border-left:4px solid #3b82f6;transition:all 0.2s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
        <div style="display:flex;align-items:center;gap:12px">
          <div style="width:40px;height:40px;border-radius:10px;background:#dbeafe;display:flex;align-items:center;justify-content:center;color:#3b82f6;flex-shrink:0">
            <span class="material-symbols-outlined" style="font-size:22px">edit_calendar</span>
          </div>
          <div>
            <div style="font-size:14px;font-weight:800;color:#0f172a">Kelola Sesi Kegiatan</div>
            <div style="font-size:11px;color:#64748b;margin-top:1px">Tambah & susun sesi per-kegiatan di setiap hari PKKMB</div>
          </div>
        </div>
      </div>
    </a>
  </div>

  {{-- RINGKASAN JADWAL --}}
  <div class="panel" style="border-radius:12px;padding:18px 20px">
    <div style="font-size:15px;font-weight:800;color:#0f172a;margin-bottom:14px">Daftar Ringkasan Jadwal & Sesi PKKMB</div>

    <div style="display:flex;flex-direction:column;gap:10px">
      @forelse($upcomingSchedules as $sch)
      <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:12px 14px;border-radius:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <div>
          <div style="font-weight:700;font-size:14px;color:#0f172a;display:flex;align-items:center;gap:8px">
            <span>PKKMB Hari ke-{{ $sch->hari_ke }}</span>
            <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:6px;background:{{ $sch->is_active ? '#dcfce7' : '#f1f5f9' }};color:{{ $sch->is_active ? '#15803d' : '#64748b' }}">
              {{ $sch->is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
          </div>
          <div style="font-size:11px;color:#64748b;margin-top:3px">
            📅 {{ $sch->formatted_date }} | 🕒 Check-in: {{ Carbon\Carbon::parse($sch->check_in_start)->format('H:i') }} - {{ Carbon\Carbon::parse($sch->check_in_end)->format('H:i') }}
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:11px;font-weight:700;background:#eff6ff;color:#1e40af;padding:3px 8px;border-radius:6px">
            {{ $sch->sesi->count() }} Sesi Kegiatan
          </span>
        </div>
      </div>
      @empty
      <div style="text-align:center;padding:24px;color:#94a3b8;font-size:12px">Belum ada jadwal PKKMB.</div>
      @endforelse
    </div>
  </div>
</section>
@endsection
