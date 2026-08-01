@extends('layouts.admin')
@section('title', 'Dashboard Tim Acara — SIABSEN')

@section('content')
<section>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:24px">
    <div style="display:flex;align-items:center;gap:14px">
      <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 8px 20px rgba(139,92,246,0.3)">
        <span class="material-symbols-outlined" style="font-size:30px">event_note</span>
      </div>
      <div>
        <h1 style="font-size:22px;font-weight:800;color:#0f172a;margin:0">Dashboard Tim Acara</h1>
        <div style="font-size:13px;color:#64748b;margin-top:2px">Selamat datang, <strong>{{ auth()->user()->full_name ?? auth()->user()->username }}</strong> — Kelola Jadwal & Sesi Kegiatan PKKMB</div>
      </div>
    </div>
  </div>

  {{-- STATS GRID --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-bottom:24px">
    <div class="panel" style="margin:0;padding:20px;border-radius:14px">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Total Jadwal PKKMB</span>
        <span class="material-symbols-outlined" style="color:#8b5cf6">calendar_month</span>
      </div>
      <div style="font-size:32px;font-weight:800;color:#0f172a;margin-top:8px">{{ $totalSchedules }} <span style="font-size:13px;color:#64748b">Hari</span></div>
      <div style="font-size:11px;color:#10b981;margin-top:4px">✓ {{ $activeSchedules }} Jadwal Aktif</div>
    </div>

    <div class="panel" style="margin:0;padding:20px;border-radius:14px">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Total Sesi Kegiatan</span>
        <span class="material-symbols-outlined" style="color:#3b82f6">edit_calendar</span>
      </div>
      <div style="font-size:32px;font-weight:800;color:#0f172a;margin-top:8px">{{ $totalSesi }} <span style="font-size:13px;color:#64748b">Sesi</span></div>
      <div style="font-size:11px;color:#10b981;margin-top:4px">✓ {{ $activeSesi }} Sesi Aktif</div>
    </div>

    <div class="panel" style="margin:0;padding:20px;border-radius:14px">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Toleransi Keterlambatan</span>
        <span class="material-symbols-outlined" style="color:#f59e0b">schedule</span>
      </div>
      <div style="font-size:32px;font-weight:800;color:#0f172a;margin-top:8px">{{ $gracePeriod }} <span style="font-size:13px;color:#64748b">Menit</span></div>
      <div style="font-size:11px;color:#64748b;margin-top:4px">Grace period sistem absensi</div>
    </div>
  </div>

  {{-- QUICK ACTIONS --}}
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
    <a href="{{ route('acara.pkkmb-schedule.index') }}" style="text-decoration:none">
      <div class="panel" style="margin:0;padding:24px;border-radius:14px;border-left:5px solid #8b5cf6;transition:all 0.2s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
        <div style="display:flex;align-items:center;gap:14px">
          <div style="width:46px;height:46px;border-radius:12px;background:#f3e8ff;display:flex;align-items:center;justify-content:center;color:#8b5cf6">
            <span class="material-symbols-outlined" style="font-size:26px">calendar_month</span>
          </div>
          <div>
            <div style="font-size:16px;font-weight:800;color:#0f172a">Kelola Jadwal Absensi PKKMB</div>
            <div style="font-size:12px;color:#64748b;margin-top:2px">Atur tanggal, jam check-in & check-out harian PKKMB</div>
          </div>
        </div>
      </div>
    </a>

    <a href="{{ route('acara.kegiatan') }}" style="text-decoration:none">
      <div class="panel" style="margin:0;padding:24px;border-radius:14px;border-left:5px solid #3b82f6;transition:all 0.2s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
        <div style="display:flex;align-items:center;gap:14px">
          <div style="width:46px;height:46px;border-radius:12px;background:#dbeafe;display:flex;align-items:center;justify-content:center;color:#3b82f6">
            <span class="material-symbols-outlined" style="font-size:26px">edit_calendar</span>
          </div>
          <div>
            <div style="font-size:16px;font-weight:800;color:#0f172a">Kelola Sesi Kegiatan</div>
            <div style="font-size:12px;color:#64748b;margin-top:2px">Tambah & susun sesi per-kegiatan di setiap hari PKKMB</div>
          </div>
        </div>
      </div>
    </a>
  </div>

  {{-- RINGKASAN JADWAL --}}
  <div class="panel" style="border-radius:14px">
    <div style="font-size:16px;font-weight:800;color:#0f172a;margin-bottom:16px">Daftar Ringkasan Jadwal & Sesi PKKMB</div>

    <div style="display:flex;flex-direction:column;gap:12px">
      @forelse($upcomingSchedules as $sch)
      <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:16px;border-radius:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-weight:700;font-size:15px;color:#0f172a;display:flex;align-items:center;gap:8px">
            <span>PKKMB Hari ke-{{ $sch->hari_ke }}</span>
            <span style="font-size:12px;padding:2px 8px;border-radius:6px;background:{{ $sch->is_active ? '#dcfce7' : '#f1f5f9' }};color:{{ $sch->is_active ? '#15803d' : '#64748b' }}">
              {{ $sch->is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
          </div>
          <div style="font-size:12px;color:#64748b;margin-top:4px">
            📅 {{ $sch->formatted_date }} | 🕒 Check-in: {{ Carbon\Carbon::parse($sch->check_in_start)->format('H:i') }} - {{ Carbon\Carbon::parse($sch->check_in_end)->format('H:i') }}
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
          <span style="font-size:12px;font-weight:700;background:#eff6ff;color:#1e40af;padding:4px 10px;border-radius:8px">
            {{ $sch->sesi->count() }} Sesi Kegiatan
          </span>
        </div>
      </div>
      @empty
      <div style="text-align:center;padding:30px;color:#94a3b8">Belum ada jadwal PKKMB.</div>
      @endforelse
    </div>
  </div>
</section>
@endsection
