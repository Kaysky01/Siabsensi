const API_URL = '/api/monitor';
const PYTHON_API_URL = 'http://127.0.0.1:5000/api/python';
const COOLDOWN_MS = 60 * 60 * 1000; // 1 jam dalam milidetik
const beepSound = new Audio('/static/sounds/beep.mp3'); // File harus ada di: static/sounds/beep.mp3

let lastActionCount = 0;
let pollingActive = false;

// ===== JAM REAL-TIME =====
function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleTimeString('id-ID');

    const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('date-label').textContent = now.toLocaleDateString('id-ID', opts);
}
setInterval(updateClock, 1000);
updateClock();

// ===== INISIALISASI KAMERA =====
async function initCamera() {
    try {
        // Check if Python backend is available
        const statusRes = await fetch(`${PYTHON_API_URL}/status`);
        if (!statusRes.ok) {
            throw new Error('Python backend tidak tersedia');
        }

        // Request camera access using WebRTC
        const stream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: 'environment'
            } 
        });
        
        const videoElement = document.getElementById('camera-video');
        if (videoElement) {
            videoElement.srcObject = stream;
            videoElement.play();
            
            // Start QR detection loop
            startQRDetection(videoElement);
        }
    } catch (err) {
        console.warn('Kamera tidak tersedia:', err);
        // Show error message to user
        const videoContainer = document.querySelector('.video-container');
        if (videoContainer) {
            videoContainer.innerHTML = `
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#fff;text-align:center;padding:20px;">
                    <span class="material-symbols-outlined" style="font-size:48px;margin-bottom:16px;">videocam_off</span>
                    <p style="font-size:16px;margin-bottom:8px;">Kamera tidak dapat diakses</p>
                    <p style="font-size:12px;opacity:0.7;">Pastikan izin kamera diberikan, kamera tersedia, dan Python backend berjalan</p>
                    <button onclick="initCamera()" style="margin-top:16px;padding:10px 20px;background:#3b82f6;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;">
                        Coba Lagi
                    </button>
                </div>
            `;
        }
    }
}

// ===== QR DETECTION LOOP =====
function startQRDetection(videoElement) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    
    async function detectFrame() {
        if (videoElement.paused || videoElement.ended) {
            requestAnimationFrame(detectFrame);
            return;
        }

        // Check if video has valid dimensions
        if (videoElement.videoWidth === 0 || videoElement.videoHeight === 0) {
            requestAnimationFrame(detectFrame);
            return;
        }

        canvas.width = videoElement.videoWidth;
        canvas.height = videoElement.videoHeight;
        ctx.drawImage(videoElement, 0, 0);

        // Convert to base64
        const imageData = canvas.toDataURL('image/jpeg', 0.8);

        // Check if image data is valid
        if (!imageData || imageData === 'data:,') {
            requestAnimationFrame(detectFrame);
            return;
        }

        try {
            const res = await fetch(`${PYTHON_API_URL}/detect`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: imageData })
            });

            if (res.ok) {
                const data = await res.json();
                if (data.success && data.results && data.results.length > 0) {
                    // QR code detected
                    const qrData = data.results[0].data;
                    const confidence = data.max_confidence || 0.0;
                    console.log('QR detected:', qrData, 'Confidence:', confidence);

                    // Record attendance with confidence
                    await recordAttendance(qrData, confidence);
                }
            }
        } catch (err) {
            console.warn('QR detection error:', err);
        }

        requestAnimationFrame(detectFrame);
    }
    
    detectFrame();
}

// ===== RECORD ATTENDANCE =====
async function recordAttendance(mahasiswaId, confidence = 0.0) {
    try {
        const res = await fetch(`${PYTHON_API_URL}/attendance`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mahasiswa_id: mahasiswaId,
                status: 'hadir',
                confidence: confidence
            })
        });

        if (res.ok) {
            const data = await res.json();
            if (data.success) {
                console.log('Attendance recorded:', mahasiswaId, data);
                // Play beep only for new check-in or check-out
                // Don't beep if already checked-in or checked-out today
                if (data.result && (data.result.status === 'checked_in' || data.result.status === 'checked_out')) {
                    playBeep();
                    // Show toast notification
                    const actionText = data.result.status === 'checked_in' ? 'absen masuk' : 'absen pulang';
                    const mahasiswaName = data.mahasiswa ? data.mahasiswa.name : 'Mahasiswa';
                    showToast(`${mahasiswaName} berhasil ${actionText}`);
                }
                // Refresh attendance data
                fetchLatestAttendance();
            }
        }
    } catch (err) {
        console.warn('Attendance recording error:', err);
    }
}

// ===== COOLDOWN: simpan waktu check-in tiap mahasiswa =====
// Key: NIM atau record.id | Value: timestamp ISO check-in
function getCooldownMap() {
    try {
        const raw = sessionStorage.getItem('siabsen_checkin_times');
        return raw ? JSON.parse(raw) : {};
    } catch { return {}; }
}

function setCooldownMap(map) {
    try { sessionStorage.setItem('siabsen_checkin_times', JSON.stringify(map)); }
    catch { /* ignore */ }
}

/**
 * Periksa apakah mahasiswa masih dalam masa cooldown 1 jam sejak check-in.
 * Jika ia sudah check-out, tidak perlu cooldown lagi.
 * @returns {number|null} Sisa detik cooldown, atau null jika boleh aksi
 */
function getCooldownRemaining(record) {
    // Jika sudah check-out, tidak ada cooldown
    if (record.check_out) return null;

    const map = getCooldownMap();
    const key = String(record.mahasiswa_id || record.id || record.name);
    const checkInTime = map[key] || record.check_in;

    if (!checkInTime) return null;

    const checkedInAt = new Date(checkInTime).getTime();
    const now = Date.now();
    const elapsed = now - checkedInAt;
    const remaining = COOLDOWN_MS - elapsed;

    return remaining > 0 ? Math.ceil(remaining / 1000) : null;
}

/**
 * Simpan waktu check-in ke sessionStorage saat data baru diterima
 */
function syncCooldownMap(attendanceData) {
    const map = getCooldownMap();
    attendanceData.forEach(record => {
        if (!record.check_out && record.check_in) {
            const key = String(record.mahasiswa_id || record.id || record.name);
            if (!map[key]) {
                map[key] = record.check_in;
            }
        }
    });
    setCooldownMap(map);
}

// ===== FORMAT SISA WAKTU COOLDOWN =====
function formatCooldown(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    if (m > 0) return `${m} menit ${s} detik`;
    return `${s} detik`;
}

// ===== INISIAL AVATAR =====
function getInitials(name) {
    return name
        .split(' ')
        .slice(0, 2)
        .map(w => w[0] || '')
        .join('')
        .toUpperCase();
}

// ===== RENDER LOG ABSENSI =====
function renderRecentScans(attendanceData) {
    const container = document.getElementById('recent-scans');
    const countEl = document.getElementById('log-count');

    if (!attendanceData || attendanceData.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <path d="M14 14h.01M14 17h3M17 14h3M20 17v3M17 20h3"/>
                </svg>
                <p>Belum ada absensi hari ini</p>
            </div>`;
        countEl.textContent = '0';
        return;
    }

    countEl.textContent = attendanceData.length;

    // Ambil 10 data teratas
    const recent = attendanceData.slice(0, 10);

    container.innerHTML = recent.map(record => {
        const isCheckout = !!record.check_out;
        const timeRaw = isCheckout ? record.check_out : record.check_in;
        const timeShow = timeRaw ? timeRaw.slice(11, 19) : '--:--:--';
        const actionText = isCheckout ? 'KELUAR' : 'MASUK';
        const initials = getInitials(record.name || 'U N');

        // Hitung cooldown jika masih check-in (belum check-out)
        const remainingSec = !isCheckout ? getCooldownRemaining(record) : null;
        const inCooldown = remainingSec !== null;

        let cooldownBadge = '';
        if (inCooldown) {
            cooldownBadge = `
                <div style="margin-top:6px;font-size:10px;color:#92400e;background:#fffbeb;border:1px solid #fcd34d;border-radius:4px;padding:3px 8px;display:inline-block;">
                    <span class="material-symbols-outlined" style="font-size:12px;vertical-align:middle">schedule</span> Bisa keluar dalam ${formatCooldown(remainingSec)}
                </div>`;
        }

        return `
            <div class="log-item ${isCheckout ? 'checkout' : ''} ${inCooldown ? '' : ''}">
                <div class="log-avatar">${initials}</div>
                <div class="log-body">
                    <div class="mhs-name">${record.name}</div>
                    <div class="mhs-info">
                        <span>${record.kelompok || '—'}</span>
                        <span class="action-badge ${isCheckout ? 'keluar' : 'masuk'}">${actionText}</span>
                    </div>
                    ${cooldownBadge}
                </div>
                <div class="scan-time">${timeShow}</div>
            </div>`;
    }).join('');
}

// ===== UPDATE STATISTIK =====
function updateStats(data) {
    const total = data.length;
    const hadir = new Set(data.map(r => r.mahasiswa_id || r.name)).size;
    const masih = data.filter(r => !r.check_out).length;

    const elTotal = document.getElementById('stat-total');
    const elHadir = document.getElementById('stat-hadir');
    const elMasih = document.getElementById('stat-masih');

    if (elTotal) elTotal.textContent = hadir;
    if (elHadir) elHadir.textContent = hadir;
    if (elMasih) elMasih.textContent = masih;
}

// ===== UPDATE FOOTER =====
function updateFooter() {
    const el = document.getElementById('last-update');
    if (el) {
        const now = new Date().toLocaleTimeString('id-ID');
        el.textContent = `Diperbarui: ${now}`;
    }

    const dot = document.getElementById('polling-dot');
    if (dot) {
        dot.classList.add('active');
        setTimeout(() => dot.classList.remove('active'), 600);
        setTimeout(() => dot.classList.add('active'), 1200);
    }
}

// ===== POLLING DATA ABSENSI =====
async function fetchLatestAttendance() {
    try {
        const res = await fetch(`${API_URL}/attendance/today`);
        const data = await res.json();

        if (data.success) {
            syncCooldownMap(data.data);

            // Hitung total aktivitas: setiap check-in dihitung 1, setiap check-out dihitung 1
            let currentActionCount = 0;
            data.data.forEach(record => {
                if (record.check_in) currentActionCount++;
                if (record.check_out) currentActionCount++;
            });

            // Update last action count
            lastActionCount = currentActionCount;

            renderRecentScans(data.data);
            updateStats(data.data);
            updateFooter();
        }
    } catch (err) {
        console.warn('Gagal memuat data absensi:', err);
    }
}

// ===== SUARA BEEP SAAT ABSEN BARU =====
let isAudioUnlocked = false;

// Fungsi yang dipanggil saat tombol OK di alert diklik
function aktifkanSuara() {
    const alertBox = document.getElementById('audio-alert');
    if (alertBox) alertBox.remove();

    // Preload audio terlebih dahulu
    beepSound.load();

    // Trik: Mainkan dengan volume 0 untuk membuka izin audio browser
    beepSound.volume = 0;
    beepSound.play()
        .then(() => {
            beepSound.pause();
            beepSound.currentTime = 0;
            beepSound.volume = 1;
            isAudioUnlocked = true;
            console.info('[SIABSEN] Audio berhasil diaktifkan.');
        })
        .catch(err => {
            // Jika file tidak ditemukan atau browser tolak, tandai gagal
            isAudioUnlocked = false;
            console.warn('[SIABSEN] Izin audio gagal — pastikan file ada di static/sounds/beep.mp3:', err);
        });
}

// 3. Fungsi playBeep yang dipanggil HANYA saat ada absen baru
function playBeep() {
    // Jangan lakukan apa-apa jika user belum klik OK di alert
    if (!isAudioUnlocked) return;

    try {
        beepSound.currentTime = 0; // Pastikan suara diputar dari awal
        beepSound.play().catch(err => console.warn('Gagal memutar beep absensi:', err));
    } catch (err) {
        console.warn('Error audio:', err);
    }
}

// ===== TOAST NOTIFICATION =====
function showToast(message) {
    const toast = document.getElementById('toast-notification');
    const toastText = document.getElementById('toast-text');
    
    if (toast && toastText) {
        toastText.textContent = message;
        toast.classList.add('show');
        
        // Hide after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
}

// ===== MULAI =====
initCamera();
fetchLatestAttendance();

// Refresh data absensi setiap 2 detik
setInterval(fetchLatestAttendance, 3000);

// Re-render tiap 10 detik untuk update countdown cooldown
setInterval(() => {
    const container = document.getElementById('recent-scans');
    if (container && !container.querySelector('.empty-state')) {
        fetchLatestAttendance();
    }
}, 10000);