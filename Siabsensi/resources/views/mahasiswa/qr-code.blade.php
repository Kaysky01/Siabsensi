@extends('layouts.mahasiswa')
@section('title', 'QR Code Saya — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Kartu Absensi QR</div>
    <div class="page-sub">Berikut adalah kartu ID Anda. Anda dapat mengunduhnya untuk dicetak atau disimpan di HP.</div>
  </div>
  <div class="header-actions">
    @if($mahasiswa->photo_path)
    <button onclick="downloadQR()" class="btn btn-primary btn-sm">
      <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Unduh Kartu
    </button>
    @else
    <a href="{{ route('mahasiswa.profile') }}" class="btn btn-primary btn-sm" style="background-color: #f59e0b; border: none; color: white; text-decoration: none;">
      <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">warning</span> Upload Foto Profil Dulu
    </a>
    @endif
  </div>
</div>

<div class="panel" style="display:flex; justify-content:center; align-items:center; background:var(--bg); padding: 40px 20px; overflow: auto;">
  <!-- Responsive container for both cards -->
  <div id="card-wrapper" style="display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; width: 100%;">
    
    <!-- KARTU DEPAN -->
    <div class="card-container" style="position: relative; width: 100%; max-width: 450px; aspect-ratio: 1099/1537;">
      <div id="card-depan" style="width: 1099px; height: 1537px; position: absolute; top: 0; left: 0; transform-origin: top left; background: url('{{ asset($templateDepan) }}') center/cover no-repeat; background-color: white; border-radius: 30px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.1); border: 2px solid var(--border-light);">
        
        <!-- Area Foto (Kiri Atas, bentuk bulat) -->
        @if($mahasiswa->photo_path)
        <div style="position: absolute; top: 45%; left: 30%; width: 280px; height: 280px; z-index: 5; margin-left: -140px;">
          <!-- White border circle -->
          <div style="position: absolute; width: 280px; height: 280px; border-radius: 50%; background: white; box-shadow: 0 8px 24px rgba(0,0,0,0.12);"></div>
          <!-- Image container -->
          <div style="position: absolute; width: 265px; height: 265px; top: 7.5px; left: 7.5px; border-radius: 50%; background: white; overflow: hidden;">
            <img src="{{ asset('storage/' . $mahasiswa->photo_path) }}" 
                 alt="Foto" 
                 crossorigin="anonymous"
                 style="width: 265px; height: 265px; object-fit: cover; object-position: center; display: block; border-radius: 50%;">
          </div>
        </div>
        @endif
        
        <!-- QR Code (Kanan Atas, sejajar dengan foto) -->
        <div style="position: absolute; top: 45%; left: 65%; z-index: 5; background: transparent; margin-left: -125px;">
          <div style="transform: scale(2); transform-origin: center center; background: transparent;">
            {!! $qrImage !!}
          </div>
        </div>

        <!-- Area Nama -->
        <div style="position: absolute; top: 70%; left: 0; right: 0; text-align: center; padding: 0 60px; z-index: 10;">
          <div style="font-size: 44px; font-weight: 800; color: #1e3a8a; text-transform: uppercase; letter-spacing: -0.5px; line-height: 1.15; word-break: break-word;">
            {{ $mahasiswa->name }}
          </div>
        </div>

        <!-- Area Kompi & Prodi -->
        <div style="position: absolute; top: 75%; left: 0; right: 0; text-align: center; padding: 0 60px; z-index: 10;">
          <div style="font-size: 32px; font-weight: 600; color: #334155; letter-spacing: 0.3px; line-height: 1.3;">
            {{ $mahasiswa->kompi }} | {{ $mahasiswa->prodi }}
          </div>
        </div>
        
      </div>
    </div>
    
    <!-- KARTU BELAKANG -->
    <div class="card-container" style="position: relative; width: 100%; max-width: 450px; aspect-ratio: 1099/1537;">
      <div id="card-belakang" style="width: 1099px; height: 1537px; position: absolute; top: 0; left: 0; transform-origin: top left; background: url('{{ asset($templateBelakang) }}') center/cover no-repeat; background-color: white; border-radius: 30px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.1); border: 2px solid var(--border-light);">
        <!-- Belakang template (static, no overlay needed) -->
      </div>
    </div>
    
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function resizeCards() {
    const containers = document.querySelectorAll('.card-container');
    containers.forEach(container => {
        const card = container.querySelector('[id^="card-"]');
        if(container && card) {
            const scale = container.offsetWidth / 1099;
            card.style.transform = `scale(${scale})`;
        }
    });
}

window.addEventListener('resize', resizeCards);
document.addEventListener('DOMContentLoaded', resizeCards);
setTimeout(resizeCards, 100);

function downloadQR() {
    const wrapper = document.getElementById('card-wrapper');
    const btn = document.querySelector('button[onclick="downloadQR()"]');
    
    if (!btn) return;
    
    // UI Loading state
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;animation:spin 1s linear infinite">refresh</span> Memproses...';
    btn.style.opacity = '0.8';
    btn.disabled = true;
    
    // Get both cards
    const cardDepan = document.getElementById('card-depan');
    const cardBelakang = document.getElementById('card-belakang');
    
    // Temporarily remove transforms and styles for clean export
    const elements = [cardDepan, cardBelakang];
    const originalStyles = elements.map(el => ({
        transform: el.style.transform,
        boxShadow: el.style.boxShadow,
        border: el.style.border
    }));
    
    elements.forEach(el => {
        el.style.transform = 'none';
        el.style.boxShadow = 'none';
        el.style.border = 'none';
    });
    
    // Create a temporary container for side-by-side export
    const exportContainer = document.createElement('div');
    exportContainer.style.display = 'flex';
    exportContainer.style.gap = '20px';
    exportContainer.style.width = (1099 * 2 + 20) + 'px';
    exportContainer.style.height = '1537px';
    exportContainer.style.position = 'absolute';
    exportContainer.style.left = '-9999px';
    exportContainer.style.backgroundColor = '#ffffff';
    
    // Clone cards
    const cloneDepan = cardDepan.cloneNode(true);
    const cloneBelakang = cardBelakang.cloneNode(true);
    cloneDepan.style.position = 'relative';
    cloneBelakang.style.position = 'relative';
    
    exportContainer.appendChild(cloneDepan);
    exportContainer.appendChild(cloneBelakang);
    document.body.appendChild(exportContainer);
    
    html2canvas(exportContainer, {
        scale: 1,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false,
        imageTimeout: 0,
        width: 1099 * 2 + 20,
        height: 1537
    }).then(canvas => {
        // Restore styles
        elements.forEach((el, idx) => {
            el.style.transform = originalStyles[idx].transform;
            el.style.boxShadow = originalStyles[idx].boxShadow;
            el.style.border = originalStyles[idx].border;
        });
        
        // Remove temporary container
        document.body.removeChild(exportContainer);
        
        // Download
        const link = document.createElement('a');
        link.download = 'Kartu_Lengkap_{{ $mahasiswa->id }}_{{ \Illuminate\Support\Str::slug($mahasiswa->name) }}.png';
        link.href = canvas.toDataURL('image/png', 1.0);
        link.click();
        
        // Restore button
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.disabled = false;
    }).catch(err => {
        console.error("Error generating image: ", err);
        alert("Gagal mengunduh kartu. Pastikan template tersedia dan foto sudah terupload.");
        
        // Restore styles on error
        elements.forEach((el, idx) => {
            el.style.transform = originalStyles[idx].transform;
            el.style.boxShadow = originalStyles[idx].boxShadow;
            el.style.border = originalStyles[idx].border;
        });
        
        // Remove temporary container
        if (document.body.contains(exportContainer)) {
            document.body.removeChild(exportContainer);
        }
        
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.disabled = false;
    });
}
</script>
<style>
@keyframes spin { 100% { transform: rotate(360deg); } }

/* Responsive behavior: Desktop side-by-side, Mobile stacked */
@media (max-width: 900px) {
    #card-wrapper {
        flex-direction: column !important;
        align-items: center;
    }
    
    .card-container {
        max-width: 100% !important;
        width: 90% !important;
    }
}
</style>
@endsection
