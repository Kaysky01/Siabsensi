@extends('layouts.admin')
@section('title', 'Pengaturan Sistem — SIABSEN')

@section('content')
<section>
  <div class="page-header">
    <div>
      <div class="page-title">Pengaturan Sistem</div>
      <div class="page-sub">Konfigurasi YOLO dan sistem deteksi</div>
    </div>
  </div>

  <div class="panel" style="max-width:600px">
    <div class="section-header"><div class="section-title">Konfigurasi YOLO AI</div></div>
    <form method="POST" action="{{ route('admin.settings.save') }}">
      @csrf
      <div class="form-row">
        <label class="form-label">Model Path</label>
        <input type="text" name="model_path" class="form-input" value="{{ $yoloSettings['model_path'] ?? 'models/yolov8n.pt' }}" required>
        <small style="color:var(--text-muted);font-size:12px">Path relatif ke file .pt dari root python_backend</small>
      </div>
      
      <div class="form-row-2">
        <div class="form-row">
          <label class="form-label">Confidence Threshold (0.0 - 1.0)</label>
          <input type="number" step="0.01" min="0" max="1" name="confidence" class="form-input" value="{{ $yoloSettings['confidence'] ?? 0.65 }}" required>
        </div>
        <div class="form-row">
          <label class="form-label">QR Cooldown (detik)</label>
          <input type="number" name="qr_cooldown" class="form-input" value="{{ $yoloSettings['qr_cooldown'] ?? 10 }}" required>
        </div>
      </div>
      
      <div style="margin-top:24px">
        <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
      </div>
    </form>
  </div>
</section>
@endsection
