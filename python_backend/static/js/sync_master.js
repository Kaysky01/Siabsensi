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
                    <li>Data Mahasiswa</li>
                    <li>Jadwal PKKMB</li>
                    <li>Data Kegiatan</li>
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
        cancelButtonColor: '#6b7280',
        showLoaderOnConfirm: true,
        preConfirm: async () => {
            const urlInput = document.getElementById('laravel-url-input');
            const laravelUrl = urlInput.value.trim() || LARAVEL_URL;
            
            // Save URL for next time
            LARAVEL_URL = laravelUrl;
            
            return await syncAllMasterData(laravelUrl);
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            showSyncResults(result.value);
        }
    });
}

/**
 * Sync All Master Data
 */
async function syncAllMasterData(laravelUrl) {
    try {
        const response = await fetch(`/api/python/sync/all?laravel_url=${encodeURIComponent(laravelUrl)}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Sinkronisasi gagal');
        }

        return data;
    } catch (error) {
        Swal.showValidationMessage(
            `Sinkronisasi gagal: ${error.message}`
        );
        return null;
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
