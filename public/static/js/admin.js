// ===============================
// ─── QR CODE MAHASISWA ─────────
// ===============================
function showQRCode(id, name) {

    const modal = document.getElementById('modal-qr');
    const canvas = document.getElementById('qr-canvas');
    const title = document.getElementById('qr-title');

    if (!id || id.trim() === '') {
        alert('ID QR Code tidak tersedia untuk mahasiswa ini.');
        return;
    }

    if (!modal || !canvas) {
        console.error('Modal QR tidak ditemukan');
        return;
    }

    title.innerText = 'QR Code - ' + name;

    // Bersihkan canvas
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    QRCode.toCanvas(canvas, id, {
        width: 220,
        margin: 2
    }, function(error) {

        if (error) {
            console.error(error);
            alert('Gagal membuat QR');
            return;
        }

        modal.style.display = 'flex';
    });
}

function closeQRModal() {

    const modal = document.getElementById('modal-qr');

    if (modal) {
        modal.style.display = 'none';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'none';
    }
}