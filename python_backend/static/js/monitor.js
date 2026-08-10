const API_URL = 'https://pkkmb.polinela.ac.id/api'; // Laravel Server API
const PYTHON_API_URL = 'http://127.0.0.1:5000/api/python';
const LOCAL_STORAGE_KEY = 'siabsen_local_sync_data';
const COOLDOWN_MS = 10 * 1000; // 10 detik (sebelumnya 1 jam)

// Audio beep untuk notifikasi
const beepSound = new Audio('/static/sounds/beep.mp3');
beepSound.volume = 1.0; // Set volume 70%
let isAudioUnlocked = false;

let lastActionCount = 0;
let pollingActive = false;

// ===== JAM REAL-TIME =====
function updateClock() {
    const now = new Date();
    const clockEl = document.getElementById('clock');
    if (clockEl) clockEl.textContent = now.toLocaleTimeString('id-ID');

    const dateEl = document.getElementById('date-label');
    const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    if (dateEl) dateEl.textContent = now.toLocaleDateString('id-ID', opts);
}
setInterval(updateClock, 1000);
updateClock();

// Global state for multiple active streams
let activeStreams = new Map(); // deviceId -> stream

// ===== INISIALISASI KAMERA =====
async function populateCameraSelect() {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(device => device.kind === 'videoinput');
        const select = document.getElementById('camera-select');

        if (select && videoDevices.length > 0) {
            select.innerHTML = '';
            videoDevices.forEach((device, index) => {
                const option = document.createElement('option');
                option.value = device.deviceId;
                option.text = device.label || `Kamera ${index + 1}`;
                select.appendChild(option);
            });

            // Restore previous choice if exists
            const savedCameras = JSON.parse(localStorage.getItem('siabsen_active_cameras') || '[]');
            if (savedCameras.length > 0) {
                // We don't change select value, let it be default
            }
        }
    } catch (err) {
        console.warn('Gagal membaca daftar kamera:', err);
    }
}

async function initCamera() {
    try {
        // Request dummy access to trigger permission prompt
        const dummyStream = await navigator.mediaDevices.getUserMedia({ video: true });
        dummyStream.getTracks().forEach(t => t.stop());

        await populateCameraSelect();

        // Auto-load previously active cameras
        const savedCameras = JSON.parse(localStorage.getItem('siabsen_active_cameras') || '[]');
        for (const deviceId of savedCameras) {
            await addCameraStreamById(deviceId);
        }

        // If no saved cameras, just load the first one available
        if (savedCameras.length === 0) {
            const select = document.getElementById('camera-select');
            if (select && select.options.length > 0) {
                await addCameraStreamById(select.options[0].value);
            }
        }
    } catch (err) {
        console.warn('Kamera tidak tersedia atau akses ditolak:', err);
        const grid = document.getElementById('video-grid');
        if (grid) {
            grid.innerHTML = `
                <div style="grid-column:1/-1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#6b7280;text-align:center;padding:40px;">
                    <span class="material-symbols-outlined" style="font-size:48px;margin-bottom:16px;">videocam_off</span>
                    <p style="font-size:16px;margin-bottom:8px;">Kamera tidak dapat diakses</p>
                    <p style="font-size:12px;opacity:0.7;">Pastikan izin kamera diberikan pada browser ini</p>
                    <button onclick="initCamera()" style="margin-top:16px;padding:10px 20px;background:#3b82f6;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;">
                        Izinkan & Coba Lagi
                    </button>
                </div>
            `;
        }
    }
}

async function addCameraStream() {
    const select = document.getElementById('camera-select');
    if (!select || !select.value) return;

    await addCameraStreamById(select.value);
}

async function addCameraStreamById(deviceId, skipStorage = false) {
    if (activeStreams.has(deviceId)) {
        if (typeof showToast !== 'undefined') showToast('Kamera ini sudah aktif di grid', '#f59e0b');
        return;
    }

    // Get camera name
    const select = document.getElementById('camera-select');
    let cameraName = 'Kamera';
    if (select) {
        const opt = [...select.options].find(o => o.value === deviceId);
        if (opt) cameraName = opt.text;
    }

    const customNames = JSON.parse(localStorage.getItem('siabsen_camera_names') || '{}');
    if (customNames[deviceId]) {
        cameraName = customNames[deviceId];
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { deviceId: { exact: deviceId }, width: { ideal: 1280 }, height: { ideal: 720 } }
        });

        activeStreams.set(deviceId, stream);
        if (!skipStorage) updateActiveCamerasStorage();

        const grid = document.getElementById('video-grid');
        const containerId = 'cam-container-' + deviceId.replace(/[^a-zA-Z0-9]/g, '');

        // Remove empty state if present
        const emptyState = grid.querySelector('.empty-grid-msg');
        if (emptyState) emptyState.remove();

        const html = `
            <div id="${containerId}" class="video-container" style="position:relative; border-radius:12px; overflow:hidden; min-height: 250px; background: #000; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <video autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;"></video>
                <div class="video-overlay" style="position:absolute; bottom:0; left:0; width:100%; background:linear-gradient(to top, rgba(0,0,0,0.8), transparent); padding:16px 12px 12px 12px; display:flex; justify-content:space-between; align-items:center;">
                    <div style="color:white; font-size:13px; font-weight:600; text-shadow: 0 1px 2px rgba(0,0,0,0.8); display:flex; align-items:center; gap:6px;">
                        <span style="color:#ef4444; animation: blink 1.5s infinite;">●</span> 
                        <span id="cam-name-${containerId}">${cameraName}</span>
                        <button onclick="renameCamera('${deviceId}', 'cam-name-${containerId}')" style="pointer-events: auto; background:none; border:none; color:rgba(255,255,255,0.7); cursor:pointer; padding:0; display:flex; align-items:center;" title="Ganti Nama Kamera">
                            <span class="material-symbols-outlined" style="font-size:14px;">edit</span>
                        </button>
                    </div>
                    <button onclick="removeCamera('${deviceId}', '${containerId}')" style="pointer-events: auto; background:rgba(255,255,255,0.2); border:none; color:white; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; cursor:pointer; backdrop-filter:blur(4px); transition:0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.8)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                        <span class="material-symbols-outlined" style="font-size:18px;">close</span>
                    </button>
                </div>
                
                <div class="scan-frame" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); width:60%; height:60%; border:2px dashed rgba(255,255,255,0.3); border-radius:16px;">
                    <div class="scan-line"></div>
                </div>
            </div>
        `;

        grid.insertAdjacentHTML('beforeend', html);

        const videoElement = document.querySelector(`#${containerId} video`);
        videoElement.srcObject = stream;
        videoElement.play();

        // Mulai deteksi untuk kamera ini
        startQRDetection(videoElement);

    } catch (e) {
        console.error(e);
        if (typeof showToast !== 'undefined') showToast('Gagal membuka kamera: ' + cameraName, '#ef4444');
    }
}

function removeCamera(deviceId, containerId, skipStorage = false) {
    if (activeStreams.has(deviceId)) {
        activeStreams.get(deviceId).getTracks().forEach(t => t.stop());
        activeStreams.delete(deviceId);
        if (!skipStorage) updateActiveCamerasStorage();
    }

    if (!containerId) {
        containerId = 'cam-container-' + deviceId.replace(/[^a-zA-Z0-9]/g, '');
    }

    const container = document.getElementById(containerId);
    if (container) container.remove();

    const grid = document.getElementById('video-grid');
    if (grid && grid.children.length === 0) {
        grid.innerHTML = '<div class="empty-grid-msg" style="grid-column:1/-1;text-align:center;color:#9ca3af;padding:40px;">Belum ada kamera yang aktif</div>';
    }
}

function updateActiveCamerasStorage() {
    const activeIds = Array.from(activeStreams.keys());
    localStorage.setItem('siabsen_active_cameras', JSON.stringify(activeIds));
}

async function renameCamera(deviceId, labelId) {
    const el = document.getElementById(labelId);
    if (!el) return;

    const currentName = el.textContent;
    const { value: newName } = await Swal.fire({
        title: 'Ubah Nama Kamera',
        input: 'text',
        inputValue: currentName,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        inputValidator: (value) => {
            if (!value || value.trim() === '') {
                return 'Nama kamera tidak boleh kosong!';
            }
        }
    });

    if (newName && newName.trim() !== '') {
        const customNames = JSON.parse(localStorage.getItem('siabsen_camera_names') || '{}');
        customNames[deviceId] = newName.trim();
        localStorage.setItem('siabsen_camera_names', JSON.stringify(customNames));

        el.textContent = newName.trim();
    }
}

// ===== QR DETECTION LOOP =====
function startQRDetection(videoElement) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const lastQrTime = new Map(); // qrData -> timestamp
    const QR_COOLDOWN_MS = 3000; // 3 detik debounce per QR

    async function detectFrame() {
        if (videoElement.paused || videoElement.ended) {
            setTimeout(detectFrame, 2000);
            return;
        }

        // Check if video has valid dimensions
        if (videoElement.videoWidth === 0 || videoElement.videoHeight === 0) {
            setTimeout(detectFrame, 2000);
            return;
        }

        // Downscale to 480px width to prevent UI stutter and reduce YOLO CPU load
        const scale = 480 / videoElement.videoWidth;
        canvas.width = 480;
        canvas.height = videoElement.videoHeight * scale;

        ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);

        // Convert to base64 with higher compression (0.5 is enough for YOLO shape detection)
        const imageData = canvas.toDataURL('image/jpeg', 0.5);

        // Check if image data is valid
        if (!imageData || imageData === 'data:,') {
            setTimeout(detectFrame, 2000);
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

                    // Debounce: skip if same QR was sent recently
                    const now = Date.now();
                    const last = lastQrTime.get(qrData) || 0;
                    if (now - last < QR_COOLDOWN_MS) {
                        setTimeout(detectFrame, 2000);
                        return;
                    }
                    lastQrTime.set(qrData, now);

                    // Record attendance with confidence
                    await recordAttendance(qrData, confidence);
                }
            }
        } catch (err) {
            console.warn('QR detection error:', err);
        }

        // Dynamic delay: Sesuaikan kecepatan dengan jumlah kamera yang aktif
        // Jika banyak kamera, beri jeda lebih lama agar CPU Python tidak hang
        const activeCount = activeStreams.size > 0 ? activeStreams.size : 1;
        const dynamicDelay = Math.max(1000, activeCount * 600);

        setTimeout(detectFrame, dynamicDelay);
    }

    detectFrame();
}

// ===== POPUP ABSENSI BERHASIL =====
let _attendancePopupTimeout = null;

function showAttendancePopup(type, mahasiswaName, kompi, timeStr) {
    const isMasuk = type === 'masuk';
    const gradientColor = isMasuk
        ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)'
        : 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)';
    const glowColor = isMasuk ? 'rgba(16, 185, 129, 0.25)' : 'rgba(59, 130, 246, 0.25)';
    const iconHtml = isMasuk
        ? `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" style="width:48px;height:48px;"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>`
        : `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" style="width:48px;height:48px;"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>`;
    const labelText = isMasuk ? 'ABSENSI MASUK TERKONFIRMASI' : 'ABSENSI KELUAR TERKONFIRMASI';
    const labelColor = isMasuk ? '#047857' : '#1d4ed8';
    const bgBadge = isMasuk ? '#ecfdf5' : '#eff6ff';
    const borderBadge = isMasuk ? '#a7f3d0' : '#bfdbfe';
    const displayName = mahasiswaName || 'Mahasiswa';
    const displayTime = timeStr || new Date().toLocaleTimeString('id-ID');
    const displayKompi = (kompi && kompi !== 'Local') ? kompi : '';

    // Hapus popup sebelumnya + batalkan timeout lama
    const existing = document.getElementById('att-popup-overlay');
    if (existing) existing.remove();
    if (_attendancePopupTimeout) { clearTimeout(_attendancePopupTimeout); _attendancePopupTimeout = null; }

    // Inject CSS animasi ultra-bersih jika belum ada
    if (!document.getElementById('att-popup-style')) {
        const s = document.createElement('style');
        s.id = 'att-popup-style';
        s.textContent = `
            @keyframes attFadeIn   { from { opacity:0 } to { opacity:1 } }
            @keyframes attFadeOut  { from { opacity:1; transform:scale(1); } to { opacity:0; transform:scale(0.95); } }
            @keyframes attPopIn    { 0%{transform:scale(0.7) translateY(10px);opacity:0} 100%{transform:scale(1) translateY(0);opacity:1} }
            @keyframes attShrink   { from { width:100% } to { width:0% } }
        `;
        document.head.appendChild(s);
    }

    // Buat overlay
    const overlay = document.createElement('div');
    overlay.id = 'att-popup-overlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(15, 23, 42, 0.75);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;animation:attFadeIn 0.2s ease forwards;';
    overlay.innerHTML = `
        <div style="background:#ffffff;border-radius:24px;padding:36px 32px 32px 32px;width:520px;max-width:90vw;
                    display:flex;flex-direction:column;align-items:center;text-align:center;
                    box-shadow:0 25px 60px -15px rgba(0,0,0,0.35), 0 0 30px ${glowColor};
                    animation:attPopIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                    position:relative;overflow:hidden;border:1px solid #e2e8f0;">
            
            <!-- Top Progress Line -->
            <div style="position:absolute;top:0;left:0;height:5px;background:${gradientColor};width:100%;animation:attShrink 3.5s linear forwards;"></div>
            
            <!-- Icon Circle -->
            <div style="display:flex;align-items:center;justify-content:center;
                        width:84px;height:84px;border-radius:50%;background:${gradientColor};
                        box-shadow:0 10px 24px ${glowColor};margin-top:8px;margin-bottom:16px;flex-shrink:0;">
                ${iconHtml}
            </div>

            <!-- Status Badge -->
            <div style="display:inline-flex;align-items:center;justify-content:center;gap:6px;
                        background:${bgBadge};color:${labelColor};border:1px solid ${borderBadge};
                        padding:6px 20px;border-radius:999px;font-size:13px;font-weight:800;
                        letter-spacing:1.5px;text-transform:uppercase;margin-bottom:16px;">
                ✓ ${labelText}
            </div>

            <!-- Mahasiswa Name -->
            <div style="font-size:30px;font-weight:800;color:#0f172a;margin-bottom:10px;line-height:1.25;word-break:break-word;width:100%;">
                ${displayName}
            </div>

            <!-- Kompi Badge -->
            ${displayKompi ? `
                <div style="font-size:14px;font-weight:700;color:#475569;background:#f1f5f9;border:1px solid #e2e8f0;padding:5px 18px;border-radius:8px;margin-bottom:20px;display:inline-flex;align-items:center;gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:18px;color:${labelColor};">shield</span>
                    ${displayKompi}
                </div>
            ` : '<div style="margin-bottom:16px;"></div>'}

            <!-- Time Box -->
            <div style="width:100%;display:flex;align-items:center;justify-content:center;gap:10px;background:${bgBadge};padding:12px 24px;border-radius:14px;border:1.5px solid ${borderBadge};">
                <span class="material-symbols-outlined" style="font-size:24px;color:${labelColor};">schedule</span>
                <span style="font-family:'IBM Plex Mono', monospace, sans-serif;font-size:24px;font-weight:800;color:${labelColor};letter-spacing:1px;">${displayTime}</span>
            </div>
        </div>`;


    document.body.appendChild(overlay);

    function closePopup() {
        const el = document.getElementById('att-popup-overlay');
        if (!el) return;
        el.style.animation = 'attFadeOut 0.2s ease forwards';
        setTimeout(() => { if (el.parentNode) el.remove(); }, 200);
    }

    _attendancePopupTimeout = setTimeout(closePopup, 3500);

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) { clearTimeout(_attendancePopupTimeout); closePopup(); }
    });
}




// ===== RECORD ATTENDANCE =====
async function recordAttendance(mahasiswaId, confidence = 0.0) {
    try {
        const kegiatanSelect = document.getElementById('kegiatan-select');
        const kegiatanId = kegiatanSelect ? kegiatanSelect.value : null;

        const res = await fetch(`${PYTHON_API_URL}/attendance`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mahasiswa_id: mahasiswaId,
                status: 'hadir',
                confidence: confidence,
                kegiatan_id: kegiatanId
            })
        });

        const data = await res.json();

        if (res.ok && data.success) {
            // === SIMPAN KE LOCAL UNTUK SYNC NANTI ===
            saveToLocalSync(data);

            const mahasiswaName = data.mahasiswa ? data.mahasiswa.name : 'Mahasiswa';
            const kompi = data.mahasiswa ? data.mahasiswa.kompi : '';
            const timeStr = data.result ? data.result.time : null;

            if (data.result && data.result.status === 'checked_in') {
                // ✅ BERHASIL MASUK - Popup besar
                playBeep();
                showAttendancePopup('masuk', mahasiswaName, kompi, timeStr);

                // Jika terlambat, tampilkan alert tambahan setelah popup utama
                if (data.result.is_late && data.result.late_duration > 0) {
                    setTimeout(() => {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: '⚠️ Terlambat!',
                                text: `${mahasiswaName} terlambat ${data.result.late_duration} menit. Absensi masuk tetap dicatat.`,
                                icon: 'warning',
                                confirmButtonColor: '#f59e0b',
                                timer: 4000,
                                timerProgressBar: true
                            });
                        }
                    }, 3200);
                }

            } else if (data.result && data.result.status === 'checked_out') {
                // ✅ BERHASIL KELUAR - Popup besar
                playBeep();
                showAttendancePopup('keluar', mahasiswaName, kompi, timeStr);

            } else if (data.result) {
                // Status lain: cooldown, already_checked_in, dll → hanya toast (jika tidak ada alert)
                if (!data.show_alert) {
                    if (data.result.status === 'already_checked_in') {
                        showToast(`${mahasiswaName} sudah absen masuk hari ini.`, '#f59e0b');
                    } else if (data.result.status === 'cooldown') {
                        showToast(`${mahasiswaName} masih dalam waktu jeda (cooldown).`, '#f59e0b');
                    } else if (data.result.status === 'already_checked_out') {
                        showToast(`${mahasiswaName} sudah absen pulang hari ini.`, '#f59e0b');
                    } else if (data.result.status === 'not_checked_in') {
                        showToast(`${mahasiswaName} belum absen masuk!`, '#ef4444');
                    }
                } else {
                    // Tampilkan alert dari server
                    Swal.fire({
                        title: data.alert_title || 'Perhatian',
                        text: data.alert_text || data.message,
                        icon: data.alert_type || 'info',
                        confirmButtonColor: data.alert_type === 'error' ? '#ef4444' : (data.alert_type === 'warning' ? '#f59e0b' : '#3b82f6'),
                        timer: 4000,
                        timerProgressBar: true
                    });
                }
            }

        } else {
            // Error responses (404, 403, 500, etc.)
            const msg = data.message || `Error ${res.status}`;
            console.warn('[Attendance]', msg);

            if (data.show_alert && typeof Swal !== 'undefined') {
                Swal.fire({
                    title: data.alert_title || 'Perhatian',
                    text: data.alert_text || data.message,
                    icon: data.alert_type || 'info',
                    confirmButtonColor: data.alert_type === 'error' ? '#ef4444' : (data.alert_type === 'warning' ? '#f59e0b' : '#3b82f6'),
                    timer: 4000,
                    timerProgressBar: true
                });
            } else if (!data.show_alert) {
                showToast(msg, res.status >= 500 ? '#ef4444' : '#f97316');
            }
        }
    } catch (err) {
        console.warn('Attendance recording error:', err);
        showToast('Gagal menghubungi server Python', '#ef4444');
    }
}

// ===== LOCAL SYNC STORAGE =====
function getLocalSyncData() {
    try {
        const raw = localStorage.getItem(LOCAL_STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch { return []; }
}

function getLocalTime() {
    const now = new Date();
    const pad = n => n < 10 ? '0' + n : n;
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
}

function saveToLocalSync(responseData) {
    const data = getLocalSyncData();
    const actionTime = responseData.result.time || getLocalTime();

    // For monitor view (no kegiatan selector), always use null for daily attendance
    const kegiatanId = null;

    const validActions = ['checked_in', 'checked_out', 'already_checked_in', 'already_checked_out'];
    if (!validActions.includes(responseData.result.status)) {
        return; // Jangan tambahkan ke log UI jika hanya 'none', 'cooldown', dll
    }

    const newRecord = {
        id: 'local_' + Date.now(),
        mahasiswa_id: responseData.mahasiswa.id,
        name: responseData.mahasiswa.name,
        kompi: responseData.mahasiswa.kompi,
        kegiatan_id: kegiatanId,
        check_in: ['checked_in', 'already_checked_in'].includes(responseData.result.status) ? actionTime : null,
        check_out: ['checked_out', 'already_checked_out'].includes(responseData.result.status) ? actionTime : null,
        action: responseData.result.status,
        is_late: responseData.result.is_late || false,
        late_duration: responseData.result.late_duration || 0,
        synced: false
    };

    // Check if already in today's local log
    const existingIdx = data.findIndex(d => d.mahasiswa_id === newRecord.mahasiswa_id);
    if (existingIdx >= 0) {
        if (newRecord.action === 'checked_out' || newRecord.action === 'already_checked_out') {
            data[existingIdx].check_out = newRecord.check_out || actionTime;
            data[existingIdx].action = newRecord.action; // Update action status
            data[existingIdx].synced = false; // Need to sync again
        }
    } else {
        data.unshift(newRecord);
    }

    localStorage.setItem(LOCAL_STORAGE_KEY, JSON.stringify(data));

    // Render local data
    handleIncomingData(data);
}

// ===== SYNC KE SERVER LARAVEL =====
async function syncDataToServer() {
    const btn = document.getElementById('sync-button');

    // Ambil data dari Local DB via Python API
    let unsynced = [];
    try {
        const kegiatanSelect = document.getElementById('kegiatan-select');
        const kegiatanId = kegiatanSelect ? kegiatanSelect.value : '';
        const url = `${PYTHON_API_URL}/attendance/today${kegiatanId ? '?kegiatan_id=' + kegiatanId : ''}`;
        const res = await fetch(url);
        if (res.ok) {
            const json = await res.json();
            if (json.success && json.data && json.data.length > 0) {
                unsynced = json.data;
            }
        }
    } catch (e) {
        // Fallback ke localStorage jika DB tidak tersedia
        unsynced = getLocalSyncData().filter(d => !d.synced);
    }

    if (unsynced.length === 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Tidak Ada Data',
                text: 'Belum ada data absensi di local database hari ini.',
                icon: 'info',
                confirmButtonColor: '#3b82f6'
            });
        } else {
            showToast('Tidak ada data untuk disinkronkan.', '#3b82f6');
        }
        return;
    }

    if (btn) { btn.textContent = 'Menyinkronkan...'; btn.disabled = true; }

    try {
        showToast(`Menyinkronkan ${unsynced.length} data ke server Laravel...`, '#3b82f6');

        const res = await fetch(`${PYTHON_API_URL}/sync/attendance`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ data: unsynced })
        });

        if (res.ok) {
            const resData = await res.json();
            const rejectedCount = resData.rejected_count || 0;
            const syncedCount = resData.synced_count || 0;
            const rejectionReasons = resData.rejection_reasons || [];

            if (syncedCount > 0) {
                // Update localStorage fallback if used
                const localData = getLocalSyncData();
                let updatedLocal = false;
                localData.forEach(d => {
                    if (!d.synced) {
                        d.synced = true;
                        updatedLocal = true;
                    }
                });
                if (updatedLocal) {
                    localStorage.setItem(LOCAL_STORAGE_KEY, JSON.stringify(localData));
                }

                // Force refresh list from local DB
                lastLocalSyncHash = "";
                await fetchLatestAttendance();
            }

            if (rejectedCount > 0) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Sinkronisasi Selesai',
                        html: `
                            <div style="text-align:left;margin:16px 0;">
                                <p><strong style="color:#10b981;">&#10003; Berhasil:</strong> ${syncedCount} data</p>
                                <p><strong style="color:#ef4444;">&#10007; Ditolak:</strong> ${rejectedCount} data</p>
                                ${rejectionReasons.length > 0 ? `
                                    <div style="margin-top:12px;padding:12px;background:#fef2f2;border-radius:8px;max-height:200px;overflow-y:auto;text-align:left;">
                                        <strong style="color:#991b1b;">Alasan Penolakan:</strong>
                                        <ul style="margin:8px 0;padding-left:20px;font-size:13px;color:#991b1b;">
                                            ${rejectionReasons.map(r => `<li>${r}</li>`).join('')}
                                        </ul>
                                    </div>
                                ` : ''}
                            </div>
                        `,
                        icon: syncedCount > 0 ? 'warning' : 'error',
                        confirmButtonColor: '#3b82f6'
                    });
                } else {
                    showToast(`Sync: ${syncedCount} berhasil, ${rejectedCount} ditolak`, '#f59e0b');
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: `${syncedCount} data absensi berhasil disinkronkan ke server.`,
                        icon: 'success',
                        confirmButtonColor: '#10b981'
                    });
                } else {
                    showToast('Sinkronisasi berhasil!', '#10b981');
                }
            }
        } else {
            throw new Error(`Server API error: ${res.status}`);
        }

    } catch (err) {
        console.error('Gagal sinkronisasi', err);
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Gagal Sinkronisasi!',
                html: `<p>Terjadi kesalahan saat menghubungi server Laravel.</p>
                       <p style="font-size:13px;color:#991b1b;margin-top:12px;font-family:monospace;">${err.message || err}</p>
                       <p style="margin-top:12px;font-size:13px;">Pastikan:</p>
                       <ul style="text-align:left;font-size:13px;margin-left:20px;">
                         <li>Laravel server berjalan di <code>https://pkkmb.polinela.ac.id</code></li>
                         <li>Python backend berjalan di <code>http://127.0.0.1:5000</code></li>
                         <li>Tidak ada firewall yang memblokir koneksi</li>
                       </ul>`,
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        } else {
            showToast(`Gagal: ${err.message}`, '#ef4444');
        }
    } finally {
        if (btn) { btn.textContent = 'Sync ke Server'; btn.disabled = false; }
    }
}


// ===== CLEAR LOCAL DATA =====

function clearLocalData() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Hapus Data Lokal?',
            text: 'Seluruh data absensi lokal (yang belum disinkronisasi) akan dihapus secara permanen. Anda yakin?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#9ca3af',
            confirmButtonText: 'Ya, Hapus Semua!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                executeClearLocalData();
            }
        });
    } else {
        if (confirm('Seluruh data absensi lokal akan dihapus secara permanen. Anda yakin?')) {
            executeClearLocalData();
        }
    }
}

function executeClearLocalData() {
    localStorage.removeItem(LOCAL_STORAGE_KEY);
    sessionStorage.removeItem('siabsen_checkin_times');
    currentAttendanceList = [];

    // Hapus file backup excel di python
    fetch(`${PYTHON_API_URL}/backup`, { method: 'DELETE' })
        .catch(err => console.warn('Gagal menghapus file excel backup lokal', err));

    // Reset in-memory attendance state di Python
    fetch(`${PYTHON_API_URL}/reset-state`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    }).catch(err => console.warn('Gagal reset state Python', err));

    // Reset UI
    if (typeof renderRecentScans === 'function') {
        renderRecentScans([]);
    }
    if (typeof updateStats === 'function') {
        updateStats([]);
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire('Terhapus!', 'Data absensi lokal dan file excel backup telah dibersihkan.', 'success');
    } else {
        if (typeof showToast !== 'undefined') showToast("Data absensi lokal & backup berhasil dihapus", "#10b981");
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
let currentMonitorTab = 'pending';

function switchMonitorTab(tab) {
    currentMonitorTab = tab;

    // Update active tab UI
    const btnPending = document.getElementById('tab-pending');
    const btnHistory = document.getElementById('tab-history');

    if (tab === 'pending') {
        btnPending.style.background = 'var(--primary)';
        btnPending.style.color = 'white';
        btnHistory.style.background = '#e5e7eb';
        btnHistory.style.color = '#4b5563';
    } else {
        btnHistory.style.background = 'var(--primary)';
        btnHistory.style.color = 'white';
        btnPending.style.background = '#e5e7eb';
        btnPending.style.color = '#4b5563';
    }

    // Trigger re-render
    renderRecentScans(currentAttendanceList);
}

function renderRecentScans(attendanceData) {
    const container = document.getElementById('recent-scans');
    const countEl = document.getElementById('log-count');

    // Filter by tab
    let filteredData = [];
    if (attendanceData) {
        if (currentMonitorTab === 'pending') {
            filteredData = attendanceData.filter(d => !d.synced);
        } else {
            filteredData = attendanceData.filter(d => d.synced);
        }
    }

    if (!filteredData || filteredData.length === 0) {
        const emptyMsg = currentMonitorTab === 'pending' ? 'Belum ada absensi baru yang menunggu sync' : 'Belum ada riwayat sinkronisasi';
        container.innerHTML = `
            <div class="empty-state">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <path d="M14 14h.01M14 17h3M17 14h3M20 17v3M17 20h3"/>
                </svg>
                <p>${emptyMsg}</p>
            </div>`;
        countEl.textContent = '0';
        return;
    }

    countEl.textContent = filteredData.length;

    // Sidebar: tampilkan max 100 data terbaru
    const recent = filteredData.slice(0, 100);

    container.innerHTML = recent.map(record => {
        const isCheckout = !!record.check_out;
        const timeRaw = isCheckout ? record.check_out : record.check_in;
        // Handle HH:MM:SS format langsung (dari Python) dan full datetime string
        let timeShow = '--:--:--';
        if (timeRaw) {
            if (/^\d{2}:\d{2}:\d{2}$/.test(timeRaw)) {
                // Already in HH:MM:SS format
                timeShow = timeRaw;
            } else {
                const d = new Date(timeRaw);
                timeShow = isNaN(d.getTime()) ? timeRaw : d.toLocaleTimeString('id-ID', { hour12: false }).replace(/\./g, ':');
            }
        }

        const statusLower = (record.status || '').toLowerCase();
        let actionText = 'MASUK';
        let badgeClass = 'masuk';

        if (statusLower === 'sakit') {
            actionText = 'SAKIT';
            badgeClass = 'sakit';
        } else if (statusLower === 'izin') {
            actionText = 'IZIN';
            badgeClass = 'izin';
        } else if (statusLower === 'alpha') {
            actionText = 'ALPHA';
            badgeClass = 'alpha';
        } else if (isCheckout) {
            actionText = 'KELUAR';
            badgeClass = 'keluar';
        }

        const initials = getInitials(record.name || 'U N');

        // Hitung cooldown jika masih check-in (belum check-out)
        const remainingSec = (!isCheckout && badgeClass === 'masuk') ? getCooldownRemaining(record) : null;
        const inCooldown = remainingSec !== null;

        let cooldownBadge = '';
        if (inCooldown) {
            cooldownBadge = `
                <div style="margin-top:6px;font-size:10px;color:#92400e;background:#fffbeb;border:1px solid #fcd34d;border-radius:4px;padding:3px 8px;display:inline-block;">
                    <span class="material-symbols-outlined" style="font-size:12px;vertical-align:middle">schedule</span> Bisa keluar dalam ${formatCooldown(remainingSec)}
                </div>`;
        }

        // Tampilkan badge terlambat jika is_late = true
        let lateBadge = '';
        if (record.is_late && !isCheckout && badgeClass === 'masuk') {
            const lateDuration = record.late_duration || 0;
            lateBadge = `
                <div style="margin-top:6px;font-size:10px;color:#991b1b;background:#fee2e2;border:1px solid #fca5a5;border-radius:4px;padding:3px 8px;display:inline-block;font-weight:600;">
                    <span class="material-symbols-outlined" style="font-size:12px;vertical-align:middle">warning</span> TERLAMBAT ${lateDuration} menit
                </div>`;
        }

        return `
            <div class="log-item ${isCheckout ? 'checkout' : ''}">
                <div class="log-avatar">${initials}</div>
                <div class="log-body">
                    <div class="mhs-name">${record.name}</div>
                    <div class="mhs-info">
                        <span>${record.kompi || '—'}</span>
                        <span class="action-badge ${badgeClass}">${actionText}</span>
                    </div>
                    ${lateBadge}
                    ${cooldownBadge}
                </div>
                <div class="scan-time">${timeShow}</div>
            </div>`;
    }).join('');
}

// ===== UPDATE STATISTIK =====
function updateStats(data) {
    // Gunakan unique mahasiswa_id untuk menghitung jumlah hadir
    const uniqueMhs = new Map();
    let sakitIzinCount = 0;

    data.forEach(r => {
        const key = r.mahasiswa_id || r.name;
        // Untuk duplikat, prioritaskan record dengan check_out (sudah lengkap)
        if (!uniqueMhs.has(key) || r.check_out) {
            uniqueMhs.set(key, r);
        }

        const st = (r.status || '').toLowerCase();
        if (['sakit', 'izin', 'alpha'].includes(st)) {
            sakitIzinCount++;
        }
    });

    const hadir = uniqueMhs.size - sakitIzinCount;
    
    // Masih di lokasi = unik mahasiswa yang sudah check_in tapi belum check_out
    let masih = 0;
    uniqueMhs.forEach(r => {
        const st = (r.status || '').toLowerCase();
        if (r.check_in && !r.check_out && !['sakit', 'izin', 'alpha'].includes(st)) {
            masih++;
        }
    });
    
    const totalScan = data.length;

    const elTotal = document.getElementById('stat-total');
    const elHadir = document.getElementById('stat-hadir');
    const elMasih = document.getElementById('stat-masih');
    const elSakit = document.getElementById('stat-sakit');

    // stat-total → Total Hadir (unique mahasiswa hadir)
    if (elTotal) elTotal.textContent = hadir > 0 ? hadir : 0;
    // stat-hadir → Total Scan (jumlah record)  
    if (elHadir) elHadir.textContent = totalScan;
    // stat-masih → Masih di Lokasi
    if (elMasih) elMasih.textContent = masih;
    // stat-sakit → Sakit / Izin / Alpha
    if (elSakit) elSakit.textContent = sakitIzinCount;
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

// ===== REAL-TIME DENGAN DELTA UPDATES (Polling) =====
let currentAttendanceList = [];
let lastUpdateTimestamp = null;
let lastLocalSyncHash = ""; // Untuk mencegah re-render jika tidak ada perubahan

function handleIncomingData(newData) {
    if (!newData || newData.length === 0) return;

    let hasNewCheck = false;
    let newActionMessage = '';

    newData.forEach(newItem => {
        // Cari apakah mahasiswa ini sudah ada di log absensi hari ini
        const idx = currentAttendanceList.findIndex(item => item.mahasiswa_id === newItem.mahasiswa_id);

        if (idx !== -1) {
            const oldItem = currentAttendanceList[idx];
            // Cek apakah ada aksi baru (misalnya check_out baru terisi)
            if (!oldItem.check_out && newItem.check_out) {
                hasNewCheck = true;
                newActionMessage = `${newItem.name} berhasil absen pulang`;
            }
            // Update data lama dengan data baru
            currentAttendanceList[idx] = newItem;
        } else {
            // Data baru absen masuk
            currentAttendanceList.unshift(newItem); // Letakkan paling atas
            hasNewCheck = true;
            newActionMessage = `${newItem.name} berhasil absen masuk`;
        }
    });

    // Urutkan berdasarkan waktu aksi terbaru (checkout jika ada, atau checkin) agar yang terbaru selalu di atas
    currentAttendanceList.sort((a, b) => {
        const timeA = a.check_out || a.check_in || a.created_at || '0';
        const timeB = b.check_out || b.check_in || b.created_at || '0';
        return timeB.localeCompare(timeA);
    });

    // Sync cooldown dan render
    syncCooldownMap(currentAttendanceList);
    renderRecentScans(currentAttendanceList);
    updateStats(currentAttendanceList);
    updateFooter();
}

async function fetchLatestAttendance() {
    // Poll dari Local MySQL DB via Python API
    try {
        const kegiatanSelect = document.getElementById('kegiatan-select');
        const kegiatanId = kegiatanSelect ? kegiatanSelect.value : '';
        const url = `${PYTHON_API_URL}/attendance/today${kegiatanId ? '?kegiatan_id=' + kegiatanId : ''}`;

        const res = await fetch(url);
        if (res.ok) {
            const json = await res.json();
            if (json.success && json.data) {
                // Simpan SEMUA data untuk stats, tanpa limit
                currentAttendanceList = json.data;
                
                // Urutkan: yang terbaru di atas (checkout time > checkin time)
                currentAttendanceList.sort((a, b) => {
                    const timeA = a.check_out || a.check_in || '0';
                    const timeB = b.check_out || b.check_in || '0';
                    return timeB.localeCompare(timeA);
                });
                
                syncCooldownMap(currentAttendanceList);
                renderRecentScans(currentAttendanceList);
                updateStats(currentAttendanceList);
                updateFooter();
            }
        }
    } catch (err) {
        // Jika DB tidak tersedia, fallback ke localStorage
        const data = getLocalSyncData();
        if (data && data.length > 0) {
            handleIncomingData(data);
        }
    }

    const dot = document.getElementById('polling-dot');
    if (dot) {
        dot.classList.add('active');
        setTimeout(() => dot.classList.remove('active'), 600);
    }
}

// ===== SUARA BEEP SAAT ABSEN BARU =====
function aktifkanSuara() {
    const alertBox = document.getElementById('audio-alert');
    if (alertBox) alertBox.remove();

    // Preload dan test audio untuk unlock di browser
    beepSound.load();
    beepSound.volume = 0.01; // Volume sangat kecil untuk test
    beepSound.play()
        .then(() => {
            beepSound.pause();
            beepSound.currentTime = 0;
            beepSound.volume = 0.7; // Set volume normal
            isAudioUnlocked = true;
            console.info('[SIABSEN] Audio berhasil diaktifkan ✓');
        })
        .catch(err => {
            isAudioUnlocked = false;
            console.warn('[SIABSEN] Izin audio gagal:', err);
        });
}

function playBeep() {
    if (!isAudioUnlocked) {
        console.warn('[SIABSEN] Audio belum diaktifkan. Klik "Aktifkan Suara" terlebih dahulu.');
        return;
    }

    try {
        // Reset ke awal dan play
        beepSound.currentTime = 0;
        beepSound.play().catch(err => {
            console.warn('Gagal memutar beep:', err);
        });
    } catch (err) {
        console.warn('Error audio:', err);
    }
}

// ===== TOAST NOTIFICATION =====
function showToast(message, bgColor) {
    const toast = document.getElementById('toast-notification');
    const toastText = document.getElementById('toast-text');
    if (toast && toastText) {
        toastText.textContent = message;
        toast.style.background = bgColor || 'var(--success, #22d3a0)';
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
            toast.style.background = '';
        }, 3500);
    }
}

// ===== MULAI =====
async function loadKegiatan() {
    const select = document.getElementById('kegiatan-select');
    if (!select) return;

    try {
        const res = await fetch(`${API_URL}/kegiatan`);
        if (res.ok) {
            const data = await res.json();
            if (data.success && data.data.length > 0) {
                select.innerHTML = '<option value="">Pilih Kegiatan/Sesi PKKMB...</option>';
                data.data.forEach(k => {
                    const option = document.createElement('option');
                    option.value = k.id;
                    option.textContent = `${k.nama} (${k.tanggal_pelaksanaan})`;
                    select.appendChild(option);
                });

                // Set saved value if exists
                const saved = localStorage.getItem('siabsen_kegiatan_id');
                if (saved) {
                    select.value = saved;
                }

                // Save on change with confirmation
                select.addEventListener('change', async (e) => {
                    const newValue = e.target.value;
                    const oldValue = localStorage.getItem('siabsen_kegiatan_id');

                    if (oldValue && newValue !== oldValue) {
                        if (typeof Swal !== 'undefined') {
                            const { isConfirmed } = await Swal.fire({
                                title: 'Ganti Kegiatan/Sesi?',
                                text: 'Tampilan antrean absensi lokal akan di-reset (pastikan sudah Sync jika menggunakan mode lokal penuh). Lanjutkan?',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3b82f6',
                                cancelButtonColor: '#9ca3af',
                                confirmButtonText: 'Ya, Ganti',
                                cancelButtonText: 'Batal'
                            });

                            if (!isConfirmed) {
                                e.target.value = oldValue;
                                return;
                            }
                        } else {
                            if (!confirm('Ganti kegiatan? Tampilan log akan di-reset.')) {
                                e.target.value = oldValue;
                                return;
                            }
                        }
                    }

                    localStorage.setItem('siabsen_kegiatan_id', newValue);

                    // Bersihkan cache log UI agar fresh untuk kegiatan yang baru
                    if (typeof executeClearLocalData === 'function') {
                        executeClearLocalData();
                    }
                });
            } else {
                select.innerHTML = '<option value="">Tidak ada kegiatan aktif</option>';
            }
        }
    } catch (err) {
        console.warn('Gagal load kegiatan:', err);
    }
}

loadKegiatan();
initCamera();
fetchLatestAttendance();

// Polling setiap 3 detik dari Local DB
setInterval(fetchLatestAttendance, 3000);

// ===== PHYSICAL BARCODE SCANNER INTEGRATION =====
let barcodeBuffer = "";
let lastKeyTime = Date.now();
let isPhysicalScannerConnected = false;
let scannerTimeout = null;

// Web Serial Scanner State
let serialPort = null;
let serialReader = null;

async function connectSerialScanner() {
    if (!("serial" in navigator)) {
        Swal.fire({
            title: 'Tidak Didukung',
            text: 'Browser Anda tidak mendukung Web Serial API. Gunakan Chrome, Edge, atau Opera terbaru.',
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
        return;
    }

    try {
        serialPort = await navigator.serial.requestPort();
        await serialPort.open({ baudRate: 9600 });

        const ind = document.getElementById('scanner-indicator');
        const txt = document.getElementById('scanner-status-text');
        if (ind && txt) {
            ind.style.background = '#d1fae5';
            ind.style.color = '#065f46';
            ind.style.borderColor = '#10b981';
            txt.textContent = 'Scanner: Terhubung (COM)';
            if (typeof showToast !== 'undefined') {
                showToast('Scanner Fisik Terhubung (COM)!', '#10b981');
            }
        }

        readSerialData();
    } catch (err) {
        console.error('Serial connection error:', err);
        Swal.fire({
            title: 'Gagal Terhubung',
            text: 'Gagal membuka port serial: ' + err.message,
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
    }
}

async function readSerialData() {
    const decoder = new TextDecoderStream();
    serialPort.readable.pipeTo(decoder.writable);
    serialReader = decoder.readable.getReader();

    let buffer = '';

    try {
        while (true) {
            const { value, done } = await serialReader.read();
            if (done) break;

            buffer += value;

            // Jika data lengkap diakhiri newline atau carriage return
            if (buffer.includes('\n') || buffer.includes('\r')) {
                const parts = buffer.split(/[\r\n]+/);
                buffer = parts.pop() || ''; // Simpan sisa input yang tidak lengkap

                for (const part of parts) {
                    const code = part.trim();
                    if (code) {
                        console.log("Scanner Fisik (Serial) Mendeteksi:", code);
                        // Flash indikator hijau saat scan berhasil
                        const ind = document.getElementById('scanner-indicator');
                        if (ind) {
                            ind.style.transform = 'scale(1.05)';
                            setTimeout(() => ind.style.transform = 'scale(1)', 200);
                        }

                        recordAttendance(code, 1.0);
                    }
                }
            }
        }
    } catch (err) {
        console.error('Serial read error:', err);
        const ind = document.getElementById('scanner-indicator');
        const txt = document.getElementById('scanner-status-text');
        if (ind && txt) {
            ind.style.background = '#f3f4f6';
            ind.style.color = '#4b5563';
            ind.style.borderColor = '#d1d5db';
            txt.textContent = 'Hubungkan Scanner (Serial)';
            if (typeof showToast !== 'undefined') {
                showToast('Scanner terputus!', '#ef4444');
            }
        }
    }
}

document.addEventListener('keydown', function (e) {
    // Abaikan input jika user sedang fokus mengetik di input form atau textarea
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        return;
    }

    const currentTime = Date.now();

    // Toleransi interval ketikan scanner keyboard emulation diperlonggar (300ms)
    if (currentTime - lastKeyTime > 300) {
        barcodeBuffer = "";
    }

    lastKeyTime = currentTime;

    // Jika Enter ditekan, proses buffer sebagai barcode
    if (e.key === 'Enter') {
        if (barcodeBuffer.length > 0) {
            console.log("Scanner Keyboard Emulation Mendeteksi:", barcodeBuffer);
            recordAttendance(barcodeBuffer, 1.0);

            // UI Indicator Update
            const ind = document.getElementById('scanner-indicator');
            const txt = document.getElementById('scanner-status-text');
            if (ind && txt) {
                ind.style.background = '#d1fae5';
                ind.style.color = '#065f46';
                ind.style.borderColor = '#10b981';
                txt.textContent = 'Scanner: Terhubung (Keyboard)';

                if (!isPhysicalScannerConnected) {
                    if (typeof showToast !== 'undefined') {
                        showToast('Scanner Fisik Terhubung!', '#10b981');
                    }
                    isPhysicalScannerConnected = true;
                }

                ind.style.transform = 'scale(1.05)';
                setTimeout(() => ind.style.transform = 'scale(1)', 200);

                clearTimeout(scannerTimeout);
                scannerTimeout = setTimeout(() => {
                    ind.style.background = '#f3f4f6';
                    ind.style.color = '#4b5563';
                    ind.style.borderColor = '#d1d5db';
                    txt.textContent = serialPort ? 'Scanner: Terhubung (COM)' : 'Hubungkan Scanner (Serial)';
                    isPhysicalScannerConnected = false;
                }, 10000);
            }

            barcodeBuffer = "";
            e.preventDefault();
        }
    }
    // Simpan karakter tunggal (huruf/angka/tanda baca)
    else if (e.key.length === 1) {
        barcodeBuffer += e.key;
    }
});

// Re-render tiap 10 detik untuk update countdown cooldown
setInterval(() => {
    if (currentAttendanceList.length > 0) {
        renderRecentScans(currentAttendanceList);
    }
}, 10000);

// Sync cameras across tabs (Monitor <-> CCTV Mode)
window.addEventListener('storage', async (e) => {
    if (e.key === 'siabsen_active_cameras') {
        const savedCameras = JSON.parse(e.newValue || '[]');

        // Buang kamera yang dimatikan di tab lain
        const activeIds = Array.from(activeStreams.keys());
        for (const id of activeIds) {
            if (!savedCameras.includes(id)) {
                removeCamera(id, null, true); // true = skip storage update
            }
        }

        // Tambahkan kamera yang dinyalakan di tab lain
        for (const id of savedCameras) {
            if (!activeStreams.has(id)) {
                await addCameraStreamById(id, true); // true = skip storage update
            }
        }
    }

    if (e.key === 'siabsen_camera_names') {
        const customNames = JSON.parse(e.newValue || '{}');
        for (const deviceId of activeStreams.keys()) {
            if (customNames[deviceId]) {
                const containerId = 'cam-container-' + deviceId.replace(/[^a-zA-Z0-9]/g, '');
                const label = document.getElementById('cam-name-' + containerId);
                if (label) label.textContent = customNames[deviceId];
            }
        }
    }
});