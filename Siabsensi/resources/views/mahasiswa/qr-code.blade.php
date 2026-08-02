@extends('layouts.mahasiswa')
@section('title', 'QR Code Saya — SIABSEN')

@section('content')
<div class="page-header">
  <div>
    <div class="page-title">Kartu Absensi QR</div>
    <div class="page-sub">Berikut adalah kartu ID Anda. Anda dapat mengunduhnya untuk dicetak atau disimpan di HP.</div>
  </div>
  <div class="header-actions">
    @if($isProfileComplete && ($hasKompi ?? false))
    <button onclick="downloadQR()" class="btn btn-primary btn-sm">
      <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">download</span> Unduh Kartu
    </button>
    @elseif(!$isProfileComplete)
    <a href="{{ route('mahasiswa.profile') }}" class="btn btn-primary btn-sm" style="background-color:#f59e0b;border:none;color:white;text-decoration:none;">
      <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">edit</span> Lengkapi Profil
    </a>
    @endif
  </div>
</div>

@if(!$isProfileComplete)
<div class="panel" style="max-width:720px;margin:0 auto;padding:32px 24px;text-align:center">
  <div style="width:88px;height:88px;border-radius:999px;background:#fffbeb;border:2px solid #f59e0b;display:flex;align-items:center;justify-content:center;margin:0 auto 18px auto">
    <span class="material-symbols-outlined" style="font-size:42px;color:#d97706">lock</span>
  </div>

  <div style="font-size:24px;font-weight:800;color:#111827;margin-bottom:8px">ID Card Terkunci</div>
  <div style="font-size:14px;color:#6b7280;line-height:1.7;max-width:560px;margin:0 auto 18px auto">
    Foto profil wajib diupload karena foto tersebut akan ditampilkan di ID card. Akses dibuka setelah profil wajib dilengkapi.
  </div>

  <div style="background:#fff7ed;border:1px solid #fdba74;border-radius:12px;padding:18px;text-align:left;max-width:520px;margin:0 auto 20px auto">
    <div style="font-size:13px;font-weight:700;color:#9a3412;margin-bottom:10px">Data yang masih perlu dilengkapi</div>
    <ul style="margin:0;padding-left:18px;color:#7c2d12;line-height:1.8">
      @foreach($missingProfileFields as $label)
      <li>{{ $label }}</li>
      @endforeach
    </ul>
  </div>

  <a href="{{ route('mahasiswa.profile') }}" class="btn btn-primary" style="display:inline-flex;text-decoration:none">
    <span class="material-symbols-outlined" style="font-size:16px">person_edit</span> Ke Halaman Profil
  </a>
</div>
@elseif(!($hasKompi ?? false))
<div class="panel" style="max-width:720px;margin:0 auto;padding:32px 24px;text-align:center">
  <div style="width:88px;height:88px;border-radius:999px;background:#fef2f2;border:2px solid #ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 18px auto">
    <span class="material-symbols-outlined" style="font-size:42px;color:#dc2626">lock</span>
  </div>

  <div style="font-size:24px;font-weight:800;color:#111827;margin-bottom:8px">ID Card Belum Tersedia</div>
  <div style="font-size:14px;color:#6b7280;line-height:1.7;max-width:560px;margin:0 auto 18px auto">
    Anda belum ditempatkan ke dalam Kompi. ID Card dan QR Code baru bisa diunduh setelah Anda memiliki Kompi. Silakan hubungi admin untuk penempatan Kompi.
  </div>

  <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:12px;padding:18px;text-align:left;max-width:520px;margin:0 auto 20px auto">
    <div style="font-size:13px;font-weight:700;color:#991b1b;margin-bottom:6px">
      <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle">info</span> Informasi
    </div>
    <div style="font-size:13px;color:#7f1d1d;line-height:1.6">
      Kompi Anda belum ditentukan oleh admin. Kartu absensi QR tidak dapat diunduh sampai Anda memiliki Kompi.
    </div>
  </div>
</div>
@else
<div class="panel" style="display:flex;justify-content:center;align-items:center;background:var(--bg);padding:40px 20px;overflow:auto;">
  <div id="card-wrapper" style="display:flex;gap:20px;flex-wrap:wrap;justify-content:center;width:100%;">
    <div class="card-container" style="position:relative;width:100%;max-width:450px;aspect-ratio:1099/1537;">
      <div id="card-depan" style="width:1099px;height:1537px;position:absolute;top:0;left:0;transform-origin:top left;background:url('{{ asset($templateDepan) }}') center/cover no-repeat;background-color:white;border-radius:30px;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,0.1);border:2px solid var(--border-light);">
        <!-- QR CODE - Center Tengah -->
        <div style="position:absolute;top:56%;left:50%;z-index:5;background:transparent;transform:translate(-50%, -50%);">
          <div style="transform:scale(2.5);transform-origin:center center;background:transparent;">
            {!! $qrImage !!}
          </div>
        </div>

        <!-- FOTO - Center Atas -->
        <div style="position:absolute;top:18%;left:50%;width:280px;height:280px;z-index:5;transform:translateX(-50%);">
          <div style="position:absolute;width:320px;height:320px;border-radius:50%;background:white;box-shadow:0 12px 32px rgba(0,0,0,0.15);"></div>
          <div style="position:absolute;width:300px;height:300px;top:10px;left:10px;border-radius:50%;background:white;overflow:hidden;">
            <img src="{{ $mahasiswa->photo_url }}"
                 alt="Foto"
                 crossorigin="anonymous"
                 style="width:300px;height:300px;object-fit:cover;object-position:center;display:block;border-radius:50%;">
          </div>
        </div>


        <!-- NAMA - Center Bawah -->
        <div style="position:absolute;top:71.5%;left:0;right:0;text-align:center;padding:0 60px;z-index:10;">
          <div style="font-size:48px;font-weight:800;color:#1e3a8a;text-transform:uppercase;letter-spacing:-0.5px;line-height:1.15;word-break:break-word;">
            {{ $mahasiswa->name }}
          </div>
        </div>

        <!-- KOMPI | PRODI - Center Bawah -->
        <div style="position:absolute;top:76%;left:0;right:0;text-align:center;padding:0 60px;z-index:10;">
          <div style="font-size:39.5px;font-weight:600;color:#334155;letter-spacing:0.3px;line-height:1.3;">
            {{ $mahasiswa->kompi }} | {{ $mahasiswa->prodi }}
          </div>
        </div>
      </div>
    </div>

    <div class="card-container" style="position:relative;width:100%;max-width:450px;aspect-ratio:1099/1537;">
      <div id="card-belakang" style="width:1099px;height:1537px;position:absolute;top:0;left:0;transform-origin:top left;background:url('{{ asset($templateBelakang) }}') center/cover no-repeat;background-color:white;border-radius:30px;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,0.1);border:2px solid var(--border-light);">
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
function resizeCards() {
    const containers = document.querySelectorAll('.card-container');
    containers.forEach(container => {
        const card = container.querySelector('[id^="card-"]');
        if (container && card) {
            const scale = container.offsetWidth / 1099;
            card.style.transform = `scale(${scale})`;
        }
    });
}

window.addEventListener('resize', resizeCards);
document.addEventListener('DOMContentLoaded', resizeCards);
setTimeout(resizeCards, 100);

function downloadQR() {
    const btn = document.querySelector('button[onclick="downloadQR()"]');

    if (!btn) return;

    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;animation:spin 1s linear infinite">refresh</span> Memproses...';
    btn.style.opacity = '0.8';
    btn.disabled = true;

    const cardDepan = document.getElementById('card-depan');
    const cardBelakang = document.getElementById('card-belakang');
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

    const exportContainer = document.createElement('div');
    exportContainer.style.display = 'flex';
    exportContainer.style.gap = '20px';
    exportContainer.style.width = (1099 * 2 + 20) + 'px';
    exportContainer.style.height = '1537px';
    exportContainer.style.position = 'absolute';
    exportContainer.style.left = '-9999px';
    exportContainer.style.backgroundColor = '#ffffff';

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
        elements.forEach((el, idx) => {
            el.style.transform = originalStyles[idx].transform;
            el.style.boxShadow = originalStyles[idx].boxShadow;
            el.style.border = originalStyles[idx].border;
        });

        document.body.removeChild(exportContainer);

        const pdfWidth = canvas.width;
        const pdfHeight = canvas.height;
        const imageData = canvas.toDataURL('image/png', 1.0);
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({
            orientation: pdfWidth > pdfHeight ? 'landscape' : 'portrait',
            unit: 'px',
            format: [pdfWidth, pdfHeight]
        });

        pdf.addImage(imageData, 'PNG', 0, 0, pdfWidth, pdfHeight);
        pdf.save('Kartu_Lengkap_{{ $mahasiswa->id }}_{{ \Illuminate\Support\Str::slug($mahasiswa->name) }}.pdf');

        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.disabled = false;
    }).catch(err => {
        console.error('Error generating image: ', err);
        alert('Gagal mengunduh kartu. Pastikan template tersedia dan foto sudah terupload.');

        elements.forEach((el, idx) => {
            el.style.transform = originalStyles[idx].transform;
            el.style.boxShadow = originalStyles[idx].boxShadow;
            el.style.border = originalStyles[idx].border;
        });

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
@endif
@endsection
