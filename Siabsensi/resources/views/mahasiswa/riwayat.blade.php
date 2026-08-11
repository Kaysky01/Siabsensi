@extends('layouts.mahasiswa')
@section('title', 'Riwayat Kehadiran — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Riwayat Kehadiran</div>
    <div class="page-sub">Pantau seluruh catatan kehadiran Anda</div>
  </div>
</div>

<div class="panel" style="border-radius:14px;overflow:hidden">
  <div class="table-responsive">
    <table class="att-table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Jam Masuk</th>
          <th>Jam Pulang</th>
          <th>Status Kehadiran</th>
        </tr>
      </thead>
      <tbody>
        @forelse($riwayat as $r)
        @php
          $isManual = !empty($r->absen_by) || strtolower($r->status) === 'manual';
          $statusLower = strtolower($r->status);
          $isHadir = in_array($statusLower, ['hadir', 'present', 'lengkap', 'manual']) || $isManual;
        @endphp
        <tr>
          <td>
            <div style="font-weight:700;color:var(--text)">{{ Carbon\Carbon::parse($r->date)->translatedFormat('l') }}</div>
            <div style="font-size:12px;color:var(--text-muted)">{{ Carbon\Carbon::parse($r->date)->format('d M Y') }}</div>
          </td>
          <td>
            @if($r->check_in)
              <span class="time-val" style="font-weight:600">{{ date('H:i', strtotime($r->check_in)) }}</span>
              @if($isManual)
                <span style="font-size:11px;color:#0284c7;font-weight:600;display:block">(Manual)</span>
              @endif
            @else
              <span style="color:var(--text-muted)">-</span>
            @endif
          </td>
          <td>
            @if($r->check_out)
              <span class="time-val" style="font-weight:600">{{ date('H:i', strtotime($r->check_out)) }}</span>
              @if($isManual)
                <span style="font-size:11px;color:#0284c7;font-weight:600;display:block">(Manual)</span>
              @endif
            @else
              <span style="color:var(--text-muted)">-</span>
            @endif
          </td>
          <td>
            @if($isHadir)
              @if($isManual)
                <span class="badge" style="background:#e0f2fe;color:#0369a1;border:1px solid #0284c7;font-weight:700;padding:5px 12px;border-radius:20px;display:inline-flex;align-items:center;gap:4px">
                  <span class="material-symbols-outlined" style="font-size:14px">edit_note</span>
                  Hadir (Absen Manual)
                </span>
              @else
                <span class="badge badge-success" style="padding:5px 12px;border-radius:20px;font-weight:700">Hadir</span>
              @endif
            @elseif(in_array($statusLower, ['izin', 'sakit']))
              <span class="badge badge-warning" style="padding:5px 12px;border-radius:20px;font-weight:700">{{ ucfirst($r->status) }}</span>
            @else
              <span class="badge badge-danger" style="padding:5px 12px;border-radius:20px;font-weight:700">Alpha</span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="4" style="text-align:center;padding:40px;color:var(--text-muted)">
            <span class="material-symbols-outlined" style="font-size:36px;display:block;margin-bottom:8px">event_busy</span>
            Tidak ada data riwayat absensi.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
