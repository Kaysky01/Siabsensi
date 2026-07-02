@extends('layouts.mahasiswa')
@section('title', 'QR Code Saya — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Kartu Absensi QR</div>
    <div class="page-sub">Berikut adalah kartu ID Anda. Anda dapat mengunduhnya untuk dicetak atau disimpan di HP.</div>
  </div>
  <div class="header-actions">
    <button onclick="downloadQR()" class="btn btn-primary btn-sm">
      <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Unduh Kartu
    </button>
  </div>
</div>

<div class="panel" style="display:flex; justify-content:center; align-items:center; background:var(--bg); padding: 40px 20px; overflow: hidden;">
  <!-- Responsive container -->
  <div id="card-wrapper" style="position: relative; width: 100%; max-width: 400px; aspect-ratio: 957/1650;">
    <div id="id-card" style="width: 957px; height: 1650px; position: absolute; top: 0; left: 0; transform-origin: top left; background: url('{{ asset('static/img/template_qr.png') }}') center/cover no-repeat; background-color: white; border-radius: 30px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.1); border: 2px solid var(--border-light);">
      
      <!-- Area Nama (Di atas garis) -->
      <div style="position: absolute; top: 48%; left: 0; right: 0; text-align: center; padding: 0 40px; z-index: 10;">
        <div style="font-size: 45px; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: -1px; line-height: 1.2; word-break: break-word;">
          {{ $mahasiswa->name }}
        </div>
      </div>

      <!-- Area QR (Di bawah garis) -->
      <div style="position: absolute; top: 62%; left: 50%; transform: translateX(-50%); display: flex; justify-content: center; align-items: center; z-index: 5;">
        <div style="transform: scale(2.6); transform-origin: center center; mix-blend-mode: multiply;">
          {!! str_replace(['fill="#ffffff"', 'fill="#fff"'], 'fill="transparent"', $qrImage) !!}
        </div>
      </div>
      
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function resizeCard() {
    const wrapper = document.getElementById('card-wrapper');
    const card = document.getElementById('id-card');
    if(wrapper && card) {
        const scale = wrapper.offsetWidth / 957;
        card.style.transform = `scale(${scale})`;
    }
}
window.addEventListener('resize', resizeCard);
document.addEventListener('DOMContentLoaded', resizeCard);
// Fallback timeout to ensure scaling applies after font rendering
setTimeout(resizeCard, 100);

function downloadQR() {
    const card = document.getElementById('id-card');
    const btn = document.querySelector('button[onclick="downloadQR()"]');
    
    // UI Loading state
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;animation:spin 1s linear infinite">refresh</span> Memproses...';
    btn.style.opacity = '0.8';
    btn.disabled = true;
    
    // Temporarily remove transform and border-radius for clean export
    const originalTransform = card.style.transform;
    const originalRadius = card.style.borderRadius;
    const originalShadow = card.style.boxShadow;
    const originalBorder = card.style.border;
    
    card.style.transform = 'none';
    card.style.borderRadius = '0';
    card.style.boxShadow = 'none';
    card.style.border = 'none';
    
    html2canvas(card, {
        scale: 1, // Native resolution is already 957x1650, so scale 1 is enough!
        useCORS: true, 
        backgroundColor: '#ffffff',
        logging: false
    }).then(canvas => {
        // Restore styles
        card.style.transform = originalTransform;
        card.style.borderRadius = originalRadius;
        card.style.boxShadow = originalShadow;
        card.style.border = originalBorder;
        
        // Download
        const link = document.createElement('a');
        link.download = 'ID_Card_{{ $mahasiswa->nim }}_{{ \Illuminate\Support\Str::slug($mahasiswa->name) }}.png';
        link.href = canvas.toDataURL('image/png', 1.0);
        link.click();
        
        // Restore button
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.disabled = false;
    }).catch(err => {
        console.error("Error generating image: ", err);
        alert("Gagal mengunduh kartu. Pastikan file template_qr.png ada di folder public/static/img/.");
        
        // Restore styles on error
        card.style.transform = originalTransform;
        card.style.borderRadius = originalRadius;
        card.style.boxShadow = originalShadow;
        card.style.border = originalBorder;
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.disabled = false;
    });
}
</script>
<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>
@endsection
