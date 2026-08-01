/**
 * Sync Master Data Functions
 * Untuk sinkronisasi data master (Mahasiswa, Jadwal, Kegiatan) dari Laravel ke Python
 */

// Default Laravel URL
let LARAVEL_URL = 'https://pkkmb.polinela.ac.id';

/**
 * Show Sync Master Data Dialog
 */
function showSyncMasterDataDialog() {
    Swal.fire({
        title: 'Sinkronisasi Master Data',
        html: `
            <div style="text-align: left; margin-bottom: 20px;">
                <p style="margin-bottom: 15px; color: #6b7280;">
                    Sinkronisasi data master dari Laravel ke database Python lokal:
                </p>
                <ul style="margin-left: 20px; color: #4b5563; line-height: 1.8;">
                    <li>Data Mahasiswa & User Accounts</li>
                    <li>Jadwal PKKMB</li>
                    <li>Data Kegiatan</li>
                    <li>Toleransi Keterlambatan & Konfigurasi Sistem</li>
                </ul>
                <div style="margin-top: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">
                        URL Server Laravel:
                    </label>
                    <input 
                        type="text" 
                        id="laravel-url-input" 
                        value="${LARAVEL_URL}"
                        placeholder="https://pkkmb.polinela.ac.id"
                        style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;"
                    />
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Mulai Sinkronisasi',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280'
    }).then((result) => {
        if (result.isConfirmed) {
            const urlInput = document.getElementById('laravel-url-input');
            const laravelUrl = (urlInput && urlInput.value.trim()) || LARAVEL_URL;
            LARAVEL_URL = laravelUrl;
            
            // Show interactive progress dialog with percentage
            showProgressSyncDialog(laravelUrl);
        }
    });
}

/**
 * Show Progress Dialog with Visual Percentage Bar (%)
 */
function showProgressSyncDialog(laravelUrl) {
    Swal.fire({
        title: 'Proses Sinkronisasi Data',
        html: `
            <div style="text-align: left; padding: 5px 0;">
                <!-- Header Status & Percentage -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span id="sync-step-title" style="font-weight: 600; font-size: 14px; color: #374151;">Menghubungkan ke server...</span>
                    <span id="sync-percent-text" style="font-weight: 700; font-size: 18px; color: #10b981;">0%</span>
                </div>

                <!-- Animated Progress Bar -->
                <div style="background: #e5e7eb; border-radius: 10px; height: 18px; width: 100%; overflow: hidden; margin-bottom: 18px; position: relative;">
                    <div id="sync-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #10b981, #059669); border-radius: 10px; transition: width 0.3s ease-in-out;"></div>
                </div>

                <!-- Checklist Item Statuses -->
                <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; font-size: 13px; color: #4b5563;">
                    <div id="step-conn" style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px; transition: all 0.2s;">
                        <span class="step-icon" style="font-size: 15px;">⏳</span>
                        <span>Tes Koneksi Server Laravel</span>
                    </div>
                    <div id="step-mhs" style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px; opacity: 0.5; transition: all 0.2s;">
                        <span class="step-icon" style="font-size: 15px;">⚪</span>
                        <span>Sync Data Mahasiswa & User Accounts</span>
                    </div>
                    <div id="step-sch" style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px; opacity: 0.5; transition: all 0.2s;">
                        <span class="step-icon" style="font-size: 15px;">⚪</span>
                        <span>Sync Jadwal PKKMB</span>
                    </div>
                    <div id="step-keg" style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px; opacity: 0.5; transition: all 0.2s;">
                        <span class="step-icon" style="font-size: 15px;">⚪</span>
                        <span>Sync Data Kegiatan</span>
                    </div>
                    <div id="step-cfg" style="display: flex; align-items: center; gap: 10px; opacity: 0.5; transition: all 0.2s;">
                        <span class="step-icon" style="font-size: 15px;">⚪</span>
                        <span>Sync Toleransi Keterlambatan (Konfigurasi Sistem)</span>
                    </div>
                </div>
            </div>
        `,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: async () => {
            await executeStepByStepSync(laravelUrl);
        }
    });
}

/**
 * Execute step-by-step sync and update percentage progress smoothly
 */
async function executeStepByStepSync(laravelUrl) {
    const results = {
        success: true,
        mahasiswa: null,
        schedules: null,
        kegiatan: null,
        system_config: null
    };

    function updateProgress(percent, titleText) {
        const bar = document.getElementById('sync-progress-bar');
        const text = document.getElementById('sync-percent-text');
        const title = document.getElementById('sync-step-title');
        
        const validPct = Math.min(100, Math.max(0, percent));
        if (bar) bar.style.width = `${validPct}%`;
        if (text) text.innerText = `${validPct}%`;
        if (title && titleText) title.innerText = titleText;
    }

    function setStepStatus(stepId, state, labelText) {
        const stepEl = document.getElementById(stepId);
        if (!stepEl) return;
        stepEl.style.opacity = '1';
        const iconEl = stepEl.querySelector('.step-icon');
        const labelSpan = stepEl.querySelector('span:last-child');
        
        if (labelText && labelSpan) labelSpan.innerText = labelText;
        
        if (state === 'active') {
            if (iconEl) iconEl.innerHTML = '⏳';
            stepEl.style.fontWeight = '600';
            stepEl.style.color = '#059669';
        } else if (state === 'done') {
            if (iconEl) iconEl.innerHTML = '✅';
            stepEl.style.fontWeight = 'normal';
            stepEl.style.color = '#065f46';
        } else if (state === 'error') {
            if (iconEl) iconEl.innerHTML = '❌';
            stepEl.style.color = '#dc2626';
            stepEl.style.fontWeight = '600';
        }
    }

    let progressTimer = null;
    function startSmoothProgress(startPct, endPct, durationMs) {
        if (progressTimer) clearInterval(progressTimer);
        let current = startPct;
        const intervalTime = 250;
        const totalSteps = durationMs / intervalTime;
        const increment = (endPct - startPct) / totalSteps;
        
        progressTimer = setInterval(() => {
            current = Math.min(endPct, current + increment);
            updateProgress(Math.round(current));
            if (current >= endPct) {
                clearInterval(progressTimer);
            }
        }, intervalTime);
    }

    function stopSmoothProgress() {
        if (progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
    }

    try {
        // Step 1: Tes Koneksi (0% -> 10%)
        setStepStatus('step-conn', 'active');
        updateProgress(5, 'Menguji koneksi ke server...');
        
        const connRes = await fetch(`/api/python/sync/test-connection?laravel_url=${encodeURIComponent(laravelUrl)}`);
        const connData = await connRes.json();
        
        if (!connRes.ok || !connData.success) {
            setStepStatus('step-conn', 'error', `Koneksi Gagal: ${connData.message || 'Server tidak merespon'}`);
            throw new Error(connData.message || 'Gagal terhubung ke server Laravel');
        }

        // Ambil jumlah data aktual dari statistik server
        const serverStats = (connData.data && connData.data.stats) || connData.stats || {};
        const mhsCount = serverStats.mahasiswa_count ? Number(serverStats.mahasiswa_count).toLocaleString('id-ID') : '';
        
        setStepStatus('step-conn', 'done', 'Koneksi Server Laravel Berhasil');
        updateProgress(10, `Koneksi OK! Terdeteksi ${mhsCount || ''} data mahasiswa...`);

        // Step 2: Sync Mahasiswa (10% -> 55%)
        const mhsLabel = mhsCount ? `Sync ${mhsCount} Data Mahasiswa & User Accounts...` : 'Sync Data Mahasiswa & User Accounts...';
        setStepStatus('step-mhs', 'active', mhsLabel);
        startSmoothProgress(10, 52, 18000); // Smooth percentage ticks while fetching
        
        const mhsRes = await fetch(`/api/python/sync/mahasiswa?laravel_url=${encodeURIComponent(laravelUrl)}`);
        const mhsData = await mhsRes.json();
        stopSmoothProgress();

        if (!mhsRes.ok || !mhsData.success) {
            setStepStatus('step-mhs', 'error', `Sync Mahasiswa Gagal`);
            results.mahasiswa = { inserted: 0, updated: 0, errors: 1 };
        } else {
            const stats = mhsData.stats || {};
            const totalFetched = stats.total ? Number(stats.total).toLocaleString('id-ID') : mhsCount;
            setStepStatus('step-mhs', 'done', `Data Mahasiswa & User Selesai (${totalFetched} akun)`);
            results.mahasiswa = stats;
        }
        updateProgress(55, 'Data Mahasiswa selesai. Menarik Jadwal PKKMB...');

        // Step 3: Sync Schedules (55% -> 70%)
        setStepStatus('step-sch', 'active');
        updateProgress(60, 'Menyimpan Jadwal PKKMB...');
        
        const schRes = await fetch(`/api/python/sync/schedules?laravel_url=${encodeURIComponent(laravelUrl)}`);
        const schData = await schRes.json();

        if (!schRes.ok || !schData.success) {
            setStepStatus('step-sch', 'error', `Sync Jadwal Gagal`);
            results.schedules = { inserted: 0, updated: 0, errors: 1 };
        } else {
            setStepStatus('step-sch', 'done', `Jadwal PKKMB Selesai Disimpan`);
            results.schedules = schData.stats;
        }
        updateProgress(70, 'Jadwal selesai. Menarik Data Kegiatan...');

        // Step 4: Sync Kegiatan (70% -> 85%)
        setStepStatus('step-keg', 'active');
        updateProgress(75, 'Menyimpan Data Kegiatan...');
        
        const kegRes = await fetch(`/api/python/sync/kegiatan?laravel_url=${encodeURIComponent(laravelUrl)}`);
        const kegData = await kegRes.json();

        if (!kegRes.ok || !kegData.success) {
            setStepStatus('step-keg', 'error', `Sync Kegiatan Gagal`);
            results.kegiatan = { inserted: 0, updated: 0, errors: 1 };
        } else {
            setStepStatus('step-keg', 'done', `Data Kegiatan Selesai Disimpan`);
            results.kegiatan = kegData.stats;
        }
        updateProgress(85, 'Kegiatan selesai. Menarik Toleransi Keterlambatan...');

        // Step 5: Sync System Config / Toleransi (85% -> 100%)
        setStepStatus('step-cfg', 'active');
        updateProgress(90, 'Menyimpan Toleransi Keterlambatan & Konfigurasi...');

        const cfgRes = await fetch(`/api/python/sync/system-config?laravel_url=${encodeURIComponent(laravelUrl)}`);
        const cfgData = await cfgRes.json();

        if (!cfgRes.ok || !cfgData.success) {
            setStepStatus('step-cfg', 'error', `Sync Toleransi Keterlambatan Gagal`);
            results.system_config = { inserted: 0, updated: 0, errors: 1 };
        } else {
            setStepStatus('step-cfg', 'done', `Toleransi Keterlambatan Selesai Disimpan`);
            results.system_config = cfgData.stats;
        }

        updateProgress(100, 'Sinkronisasi 100% Selesai!');
        
        // Wait 700ms so user can see 100% complete state clearly before final summary dialog
        await new Promise(r => setTimeout(r, 700));

        // Display results summary popup
        showSyncResults({ success: true, results: results });

    } catch (error) {
        stopSmoothProgress();
        Swal.fire({
            title: 'Sinkronisasi Gagal',
            html: `<p style="color: #dc2626;">${error.message}</p>`,
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
    }
}

/**
 * Show Sync Results
 */
function showSyncResults(data) {
    if (!data.success) {
        Swal.fire({
            title: 'Sinkronisasi Gagal',
            html: `<p>${data.message}</p>`,
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
        return;
    }

    const results = data.results;
    
    // Build result HTML
    let resultHtml = '<div style="text-align: left;">';
    
    // Mahasiswa
    if (results.mahasiswa) {
        const mhs = results.mahasiswa;
        const uncreatedList = mhs.uncreated_users || [];
        
        resultHtml += `
            <div style="margin-bottom: 15px; padding: 12px; background: #f0fdf4; border-radius: 8px; border-left: 4px solid #10b981;">
                <div style="font-weight: 600; color: #065f46; margin-bottom: 8px;">
                    📋 Data Mahasiswa
                </div>
                <div style="font-size: 13px; color: #047857;">
                    ${mhs.inserted || 0} ditambahkan, 
                    ${mhs.updated || 0} diupdate
                    ${mhs.errors > 0 ? `<span style="color: #dc2626;">(${mhs.errors} error)</span>` : ''}
                </div>
                ${mhs.users_created || mhs.users_updated ? `
                    <div style="font-size: 12px; color: #059669; margin-top: 6px; padding-top: 6px; border-top: 1px solid #d1fae5;">
                        👤 User Accounts: ${mhs.users_created || 0} dibuat, ${mhs.users_updated || 0} diupdate
                    </div>
                ` : ''}
                ${uncreatedList.length > 0 ? `
                    <div style="margin-top: 10px; padding: 10px; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 6px;">
                        <div style="font-weight: 600; color: #e11d48; margin-bottom: 6px; font-size: 12px;">
                            ⚠️ ${uncreatedList.length} Akun User Gagal/Tidak Terbuat:
                        </div>
                        <div style="max-height: 120px; overflow-y: auto; font-size: 11px; color: #9f1239; line-height: 1.6;">
                            ${uncreatedList.map(u => `• <strong>${u.name}</strong> (NIM: ${u.id})`).join('<br>')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    // Schedules
    if (results.schedules) {
        const sch = results.schedules;
        resultHtml += `
            <div style="margin-bottom: 15px; padding: 12px; background: #eff6ff; border-radius: 8px; border-left: 4px solid #3b82f6;">
                <div style="font-weight: 600; color: #1e40af; margin-bottom: 8px;">
                    📅 Jadwal PKKMB
                </div>
                <div style="font-size: 13px; color: #1e40af;">
                    ${sch.inserted || 0} ditambahkan, 
                    ${sch.updated || 0} diupdate
                    ${sch.errors > 0 ? `<span style="color: #dc2626;">(${sch.errors} error)</span>` : ''}
                </div>
            </div>
        `;
    }
    
    // Kegiatan
    if (results.kegiatan) {
        const keg = results.kegiatan;
        resultHtml += `
            <div style="margin-bottom: 15px; padding: 12px; background: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">
                <div style="font-weight: 600; color: #92400e; margin-bottom: 8px;">
                    🎯 Data Kegiatan
                </div>
                <div style="font-size: 13px; color: #92400e;">
                    ${keg.inserted || 0} ditambahkan, 
                    ${keg.updated || 0} diupdate
                    ${keg.errors > 0 ? `<span style="color: #dc2626;">(${keg.errors} error)</span>` : ''}
                </div>
            </div>
        `;
    }

    // System Config / Toleransi Keterlambatan
    if (results.system_config) {
        const cfg = results.system_config;
        resultHtml += `
            <div style="margin-bottom: 15px; padding: 12px; background: #faf5ff; border-radius: 8px; border-left: 4px solid #8b5cf6;">
                <div style="font-weight: 600; color: #6b21a8; margin-bottom: 8px;">
                    ⚙️ Toleransi Keterlambatan & Konfigurasi Sistem
                </div>
                <div style="font-size: 13px; color: #6b21a8;">
                    ${cfg.inserted || 0} ditambahkan, 
                    ${cfg.updated || 0} diupdate
                    ${cfg.errors > 0 ? `<span style="color: #dc2626;">(${cfg.errors} error)</span>` : ''}
                </div>
            </div>
        `;
    }
    
    resultHtml += '</div>';
    
    Swal.fire({
        title: '✅ Sinkronisasi Berhasil!',
        html: resultHtml,
        icon: 'success',
        confirmButtonText: 'OK',
        confirmButtonColor: '#10b981'
    });
}

/**
 * Test Laravel Connection
 */
async function testLaravelConnection(laravelUrl) {
    try {
        const response = await fetch(`/api/python/sync/test-connection?laravel_url=${encodeURIComponent(laravelUrl)}`);
        const data = await response.json();
        
        return {
            success: data.success,
            message: data.message
        };
    } catch (error) {
        return {
            success: false,
            message: `Koneksi gagal: ${error.message}`
        };
    }
}

/**
 * Sync only Mahasiswa
 */
async function syncMahasiswaOnly() {
    const result = await Swal.fire({
        title: 'Sync Data Mahasiswa',
        text: 'Sinkronisasi data mahasiswa dari Laravel?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Sync',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10b981',
        showLoaderOnConfirm: true,
        preConfirm: async () => {
            try {
                const response = await fetch(`/api/python/sync/mahasiswa?laravel_url=${encodeURIComponent(LARAVEL_URL)}`, {
                    method: 'GET',
                    headers: {'Content-Type': 'application/json'}
                });
                
                const data = await response.json();
                if (!response.ok) throw new Error(data.message);
                return data;
            } catch (error) {
                Swal.showValidationMessage(`Error: ${error.message}`);
            }
        }
    });
    
    if (result.isConfirmed && result.value) {
        const stats = result.value.stats;
        Swal.fire({
            title: 'Sync Berhasil',
            html: `
                <div style="text-align: left;">
                    <p><strong>Ditambahkan:</strong> ${stats.inserted}</p>
                    <p><strong>Diupdate:</strong> ${stats.updated}</p>
                    ${stats.errors > 0 ? `<p style="color: #dc2626;"><strong>Error:</strong> ${stats.errors}</p>` : ''}
                </div>
            `,
            icon: 'success'
        });
    }
}

/**
 * Sync only Schedules
 */
async function syncSchedulesOnly() {
    const result = await Swal.fire({
        title: 'Sync Jadwal PKKMB',
        text: 'Sinkronisasi jadwal PKKMB dari Laravel?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Sync',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3b82f6',
        showLoaderOnConfirm: true,
        preConfirm: async () => {
            try {
                const response = await fetch(`/api/python/sync/schedules?laravel_url=${encodeURIComponent(LARAVEL_URL)}`, {
                    method: 'GET',
                    headers: {'Content-Type': 'application/json'}
                });
                
                const data = await response.json();
                if (!response.ok) throw new Error(data.message);
                return data;
            } catch (error) {
                Swal.showValidationMessage(`Error: ${error.message}`);
            }
        }
    });
    
    if (result.isConfirmed && result.value) {
        const stats = result.value.stats;
        Swal.fire({
            title: 'Sync Berhasil',
            html: `
                <div style="text-align: left;">
                    <p><strong>Ditambahkan:</strong> ${stats.inserted}</p>
                    <p><strong>Diupdate:</strong> ${stats.updated}</p>
                    ${stats.errors > 0 ? `<p style="color: #dc2626;"><strong>Error:</strong> ${stats.errors}</p>` : ''}
                </div>
            `,
            icon: 'success'
        });
    }
}

/**
 * Sync only Kegiatan
 */
async function syncKegiatanOnly() {
    const result = await Swal.fire({
        title: 'Sync Data Kegiatan',
        text: 'Sinkronisasi data kegiatan dari Laravel?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Sync',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#f59e0b',
        showLoaderOnConfirm: true,
        preConfirm: async () => {
            try {
                const response = await fetch(`/api/python/sync/kegiatan?laravel_url=${encodeURIComponent(LARAVEL_URL)}`, {
                    method: 'GET',
                    headers: {'Content-Type': 'application/json'}
                });
                
                const data = await response.json();
                if (!response.ok) throw new Error(data.message);
                return data;
            } catch (error) {
                Swal.showValidationMessage(`Error: ${error.message}`);
            }
        }
    });
    
    if (result.isConfirmed && result.value) {
        const stats = result.value.stats;
        Swal.fire({
            title: 'Sync Berhasil',
            html: `
                <div style="text-align: left;">
                    <p><strong>Ditambahkan:</strong> ${stats.inserted}</p>
                    <p><strong>Diupdate:</strong> ${stats.updated}</p>
                    ${stats.errors > 0 ? `<p style="color: #dc2626;"><strong>Error:</strong> ${stats.errors}</p>` : ''}
                </div>
            `,
            icon: 'success'
        });
    }
}
