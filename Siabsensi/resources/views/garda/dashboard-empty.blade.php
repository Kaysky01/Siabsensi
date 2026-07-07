@extends('layouts.admin')
@section('title', 'Dashboard Garda — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Kompi Belum Ditugaskan</div>
      <div class="page-sub">Hubungi admin untuk mendapatkan penugasan kompi</div>
    </div>
  </div>

  <div class="empty-state-box" style="text-align:center;padding:60px 20px">
    <span class="material-symbols-outlined empty-icon" style="font-size:80px;color:var(--text-muted);opacity:0.5">person_off</span>
    <h2 style="margin:20px 0 8px 0;color:var(--text)">Kompi Belum Ditugaskan</h2>
    <p style="color:var(--text-secondary);margin:0 0 24px 0;font-size:16px">Anda belum memiliki penugasan kompi. Silakan hubungi administrator untuk mendapatkan penugasan.</p>
    <p style="color:var(--text-muted);margin:0;font-size:14px">Pesan: {{ $message ?? 'Kompi belum ditugaskan' }}</p>
  </div>
</section>

<style>
  .empty-state-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 60px 20px;
  }
  
  .empty-icon {
    display: block;
    font-size: 80px;
    color: var(--text-muted);
    opacity: 0.5;
    margin-bottom: 20px;
  }
</style>
@endsection
