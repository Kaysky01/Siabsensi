// ─── CSRF Token Helper ─────────────────────────────────────────────────────
// Mengambil CSRF token dari tag <meta> di HTML head untuk request POST/PUT/DELETE
function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : '';
}

// ─── State ─────────────────────────────────────────────────────────────────
let dashboardData = null;
let attendanceData = [];
let mahasiswaData = [];
let cameraData = [];
let currentPage = 'dashboard';
let currentQRBase64 = '';
let editingCameraId = null;
let currentUser = null;
let userPermissions = null;

// ─── Navigation ────────────────────────────────────────────────────────────
function showPage(page) {
  document.querySelectorAll('[id^="page-"]').forEach(s => s.style.display = 'none');
  const targetPage = document.getElementById('page-' + page);
  if (targetPage) targetPage.style.display = '';
  
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => {
    if (n.textContent.toLowerCase().includes(
      page === 'dashboard' ? 'dash' : 
      page === 'attendance' ? 'absensi' : 
      page === 'users' ? 'user management' : 
      page === 'cameras' ? 'kamera' : 
      page === 'mahasiswa' ? 'mahasiswa' : 
      page === 'history' ? 'riwayat' : 
      page === 'video-upload' ? 'upload video' : 
      page === 'izin-timdis' ? 'verifikasi izin' : 
      page === 'kehadiran-timdis' ? 'verifikasi kehadiran' : 'pengaturan')) {
        n.classList.add('active');
      }
  });
  
  currentPage = page;
  
  // Hanya panggil fetch jika halamannya dibuka
  if (page === 'izin-timdis') loadIzinSubmissions();
  if (page === 'kehadiran-timdis') loadKehadiranSubmissions();
  if (page === 'users') loadUsers();
}

// ─── Clock ──────────────────────────────────────────────────────────────────
function updateClock() {
  const now = new Date();
  const timeElement = document.getElementById('current-time');
  if(timeElement) timeElement.textContent = now.toLocaleTimeString('id-ID');
}
setInterval(updateClock, 1000);
updateClock();

// ─── Toast ──────────────────────────────────────────────────────────────────
function toast(title, msg = '', isError = false) {
  const t = document.getElementById('toast');
  if(!t) return;
  t.className = isError ? 'error show' : 'show';
  document.getElementById('toast-title').textContent = title;
  document.getElementById('toast-msg').textContent = msg;
  setTimeout(() => t.classList.remove('show'), 3500);
}

// ─── API Calls Wrapper ──────────────────────────────────────────────────────
async function apiFetch(path, opts = {}) {
  try {
    const headers = {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': getCsrfToken(), // Otomatis pasang token Laravel
      ...(opts.headers || {})
    };
    
    const r = await fetch(path, {
      ...opts,
      headers,
      credentials: 'same-origin' 
    });
    return await r.json();
  } catch (e) {
    console.error("API Error:", e);
    return null;
  }
}

// ─── EXPORT CSV ─────────────────────────────────────────────────────────────
function exportCSV() {
  const table = document.getElementById("full-att-table");
  if (!table) return;

  let csv = [];
  const rows = table.querySelectorAll("tr");
  
  for (let i = 0; i < rows.length; i++) {
    let row = [], cols = rows[i].querySelectorAll("td, th");
    for (let j = 0; j < cols.length; j++) {
      let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/"/g, '""');
      row.push('"' + data + '"'); 
    }
    csv.push(row.join(","));
  }

  const csvString = csv.join("\n");
  const blob = new Blob([csvString], { type: "text/csv;charset=utf-8;" });
  const link = document.createElement("a");
  
  const dateFilter = document.getElementById("att-date-filter")?.value || new Date().toISOString().split("T")[0];
  link.href = URL.createObjectURL(blob);
  link.download = "Data_Absensi_" + dateFilter + ".csv";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// ─── FILTER MAHASISWA ───────────────────────────────────────────────────────
function filterMahasiswa() {
  const search = document.getElementById('mhs-search')?.value.toLowerCase() || '';
  const kelompok = document.getElementById('mhs-filter-kelompok')?.value || '';
  const jurusan = document.getElementById('mhs-filter-jurusan')?.value || '';
  
  const rows = document.querySelectorAll('.mhs-row');
  rows.forEach(row => {
    const rowName = row.getAttribute('data-name');
    const rowKlp = row.getAttribute('data-kelompok');
    const rowJur = row.getAttribute('data-jurusan');
    
    let match = true;
    if (search && !rowName.includes(search)) match = false;
    if (kelompok && rowKlp !== kelompok) match = false;
    if (jurusan && rowJur !== jurusan) match = false;
    
    row.style.display = match ? '' : 'none';
  });
}

function resetMahasiswaFilter() {
  if(document.getElementById('mhs-search')) document.getElementById('mhs-search').value = '';
  if(document.getElementById('mhs-filter-kelompok')) document.getElementById('mhs-filter-kelompok').value = '';
  if(document.getElementById('mhs-filter-jurusan')) document.getElementById('mhs-filter-jurusan').value = '';
  filterMahasiswa();
}

// ─── FILTER RIWAYAT ─────────────────────────────────────────────────────────
function filterHistory() {
  const start = document.getElementById('hist-start')?.value || '';
  const end = document.getElementById('hist-end')?.value || '';
  
  const rows = document.querySelectorAll('.hist-row');
  rows.forEach(row => {
    const rowDate = row.getAttribute('data-date');
    let match = true;
    if (start && rowDate < start) match = false;
    if (end && rowDate > end) match = false;
    row.style.display = match ? '' : 'none';
  });
}

function resetHistoryFilter() {
  if(document.getElementById('hist-start')) document.getElementById('hist-start').value = '';
  if(document.getElementById('hist-end')) document.getElementById('hist-end').value = '';
  filterHistory();
}

// ─── UPLOAD VIDEO ───────────────────────────────────────────────────────────
let selectedVideoFile = null;

function handleVideoFileSelect(event) {
  const file = event.target.files[0];
  if (file && file.type === 'video/mp4') {
    selectedVideoFile = file;
    const videoPlayer = document.getElementById('preview-video-player');
    const fileURL = URL.createObjectURL(file);
    videoPlayer.src = fileURL;
    
    document.getElementById('video-preview-section').style.display = 'block';
    document.getElementById('preview-video-info').textContent = file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)';
  } else {
    alert('Silakan pilih file video MP4 yang valid.');
    event.target.value = '';
    selectedVideoFile = null;
    document.getElementById('video-preview-section').style.display = 'none';
  }
}

function cancelVideoUpload() {
  document.getElementById('video-file-input').value = '';
  selectedVideoFile = null;
  document.getElementById('video-preview-section').style.display = 'none';
  const videoPlayer = document.getElementById('preview-video-player');
  videoPlayer.pause();
  videoPlayer.src = '';
}

async function uploadAndProcessVideo() {
  if (!selectedVideoFile) {
    alert('Pilih video terlebih dahulu.');
    return;
  }

  const action = document.getElementById('video-action-select').value;
  const formData = new FormData();
  formData.append('video', selectedVideoFile);
  formData.append('action', action);

  document.getElementById('video-preview-section').style.display = 'none';
  document.getElementById('video-processing-panel').style.display = 'block';
  document.getElementById('processing-progress').textContent = 'Mengupload video...';

  try {
    const response = await fetch('/admin/upload-video', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': getCsrfToken() }, // Inject CSRF manual khusus FormData
      body: formData
    });

    const result = await response.json();

    if (result.success) {
      document.getElementById('video-processing-panel').style.display = 'none';
      document.getElementById('video-success-panel').style.display = 'block';
      document.getElementById('success-summary').textContent = 'Video ' + selectedVideoFile.name + ' (' + action + ') berhasil diunggah dan diproses.';
      cancelVideoUpload();
    } else {
      alert('Gagal memproses video: ' + result.message);
      document.getElementById('video-processing-panel').style.display = 'none';
      document.getElementById('video-preview-section').style.display = 'block';
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Terjadi kesalahan saat mengupload video.');
    document.getElementById('video-processing-panel').style.display = 'none';
    document.getElementById('video-preview-section').style.display = 'block';
  }
}


// ─── VERIFIKASI IZIN / SAKIT ────────────────────────────────────────────────
async function loadIzinSubmissions() {
  const search = document.getElementById('izin-search')?.value || '';
  const kelompok = document.getElementById('izin-filter-kelompok')?.value || '';
  const status = document.getElementById('izin-filter-status')?.value || '';
  
  const tbody = document.getElementById('izin-submissions-table-body');
  if (!tbody) return;
  
  tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px">Memuat data...</td></tr>';
  
  try {
    const queryParams = new URLSearchParams({ search, kelompok, status });
    const result = await apiFetch('/admin/izin-submissions?' + queryParams.toString());
    
    if (result && result.success) {
      document.getElementById('stat-pending-izin').textContent = result.stats.pending;
      document.getElementById('stat-approved-izin').textContent = result.stats.approved;
      document.getElementById('stat-rejected-izin').textContent = result.stats.rejected;
      
      const sidebarIzin = document.getElementById('sidebar-pending-izin');
      if (sidebarIzin) {
        sidebarIzin.textContent = result.stats.pending;
        sidebarIzin.style.display = result.stats.pending > 0 ? '' : 'none';
      }

      if (result.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:30px">Tidak ada pengajuan ditemukan</td></tr>';
        return;
      }
      
      let html = '';
      result.data.forEach(item => {
        let statusBadge = '';
        if (item.status === 'pending') statusBadge = '<span class="badge badge-warning" style="background:#f1f5f9;padding:4px 8px;border-radius:4px;font-size:12px;">Pending</span>';
        else if (item.status === 'approved') statusBadge = '<span style="background:#dcfce7;color:#166534;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600">Disetujui</span>';
        else if (item.status === 'rejected') statusBadge = '<span style="background:#fee2e2;color:#991b1b;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600">Ditolak</span>';
        
        let aksiBtns = '';
        if (item.status === 'pending') {
          aksiBtns = `
            <button class="btn btn-primary btn-sm" style="background:#22c55e;border:none" onclick="approveIzin(${item.id})" title="Setujui">
              Terima
            </button>
            <button class="btn btn-danger btn-sm" style="background:#ef4444;border:none" onclick="openRejectIzin(${item.id})" title="Tolak">
              Tolak
            </button>
          `;
        } else {
          aksiBtns = '<span style="font-size:12px;color:var(--text-muted)">-</span>';
        }
        
        let tanggal = item.tanggal || (item.created_at ? item.created_at.split('T')[0] : '-');
        let jenis = item.jenis ? item.jenis.toUpperCase() : 'IZIN';

        html += `
          <tr>
            <td><div style="font-weight:600">${item.mahasiswa?.name || 'Data Terhapus'}</div></td>
            <td>${item.mahasiswa?.kelompok || '-'}</td>
            <td><span style="font-size:12px;font-weight:600;color:${item.jenis === 'izin' ? '#f59e0b' : '#ef4444'}">${jenis}</span></td>
            <td>${tanggal}</td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${item.keterangan || ''}">${item.keterangan || '-'}</td>
            <td>
              ${item.bukti ? `<button class="btn btn-ghost btn-sm" onclick="viewBukti('/storage/${item.bukti}')">Lihat Bukti</button>` : '-'}
            </td>
            <td>${statusBadge}</td>
            <td><div style="display:flex;gap:4px">${aksiBtns}</div></td>
          </tr>
        `;
      });
      tbody.innerHTML = html;
    }
  } catch (error) {
    console.error('Error fetching izin:', error);
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--danger);padding:30px">Gagal memuat data</td></tr>';
  }
}

function filterIzinSubmissions() { loadIzinSubmissions(); }

function resetIzinFilter() {
  if(document.getElementById('izin-search')) document.getElementById('izin-search').value = '';
  if(document.getElementById('izin-filter-kelompok')) document.getElementById('izin-filter-kelompok').value = '';
  if(document.getElementById('izin-filter-status')) document.getElementById('izin-filter-status').value = '';
  loadIzinSubmissions();
}

function viewBukti(url) {
  const ext = url.split('.').pop().toLowerCase();
  const contentDiv = document.getElementById('bukti-content');
  
  if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
    contentDiv.innerHTML = `<img src="${url}" style="max-width:100%;max-height:60vh;border-radius:8px">`;
  } else if (ext === 'pdf') {
    contentDiv.innerHTML = `<iframe src="${url}" style="width:100%;height:60vh;border:none"></iframe>`;
  } else {
    contentDiv.innerHTML = `<a href="${url}" target="_blank" class="btn btn-primary">Download File</a>`;
  }
  const modal = document.getElementById('modal-bukti');
  if (modal) modal.style.display = 'flex';
}

async function approveIzin(id) {
  if (!confirm('Anda yakin ingin menyetujui pengajuan ini?')) return;
  try {
    const result = await apiFetch(`/admin/izin-submissions/${id}/verify`, {
      method: 'POST',
      body: JSON.stringify({ status: 'approved' })
    });
    if (result && result.success) {
      loadIzinSubmissions();
      toast('Berhasil', 'Pengajuan disetujui');
    } else {
      alert('Gagal: ' + (result?.message || 'Error server'));
    }
  } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan.'); }
}

function openRejectIzin(id) {
  document.getElementById('reject-submission-id').value = id;
  document.getElementById('reject-reason-input').value = '';
  const modal = document.getElementById('modal-reject-izin');
  if(modal) modal.style.display = 'flex';
}

async function confirmRejectIzin() {
  const id = document.getElementById('reject-submission-id').value;
  const reason = document.getElementById('reject-reason-input').value;
  if (!reason.trim()) return alert('Alasan penolakan wajib diisi!');
  
  try {
    const result = await apiFetch(`/admin/izin-submissions/${id}/verify`, {
      method: 'POST',
      body: JSON.stringify({ status: 'rejected', reject_reason: reason })
    });
    if (result && result.success) {
      const modal = document.getElementById('modal-reject-izin');
      if(modal) modal.style.display = 'none';
      loadIzinSubmissions();
      toast('Berhasil', 'Pengajuan ditolak');
    } else {
      alert('Gagal: ' + (result?.message || 'Error server'));
    }
  } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan.'); }
}

// ─── VERIFIKASI KEHADIRAN ───────────────────────────────────────────────────
async function loadKehadiranSubmissions() {
  const search = document.getElementById('kehadiran-search')?.value || '';
  const kelompok = document.getElementById('kehadiran-filter-kelompok')?.value || '';
  const status = document.getElementById('kehadiran-filter-status')?.value || '';
  
  const tbody = document.getElementById('kehadiran-submissions-table-body');
  if (!tbody) return;
  
  tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--muted);padding:30px">Memuat data...</td></tr>';
  
  try {
    const queryParams = new URLSearchParams({ search, kelompok, status });
    const result = await apiFetch('/admin/kehadiran-submissions?' + queryParams.toString());
    
    if (result && result.success) {
      document.getElementById('stat-pending-kehadiran').textContent = result.stats.pending;
      document.getElementById('stat-approved-kehadiran').textContent = result.stats.approved;
      document.getElementById('stat-rejected-kehadiran').textContent = result.stats.rejected;
      
      const sidebarKehadiran = document.getElementById('sidebar-pending-kehadiran');
      if (sidebarKehadiran) {
         sidebarKehadiran.textContent = result.stats.pending;
         sidebarKehadiran.style.display = result.stats.pending > 0 ? '' : 'none';
      }

      if (result.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--muted);padding:30px">Tidak ada pengajuan ditemukan</td></tr>';
        return;
      }
      
      let html = '';
      result.data.forEach(item => {
        let statusBadge = '';
        if (item.status === 'pending') statusBadge = '<span class="badge badge-warning" style="background:#f1f5f9;padding:4px 8px;border-radius:4px;font-size:12px;">Pending</span>';
        else if (item.status === 'approved') statusBadge = '<span style="background:#dcfce7;color:#166534;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600">Disetujui</span>';
        else if (item.status === 'rejected') statusBadge = '<span style="background:#fee2e2;color:#991b1b;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600">Ditolak</span>';
        
        let aksiBtns = '';
        if (item.status === 'pending') {
          aksiBtns = `
            <button class="btn btn-primary btn-sm" style="background:#22c55e;border:none" onclick="approveKehadiran(${item.id})" title="Setujui">
              Terima
            </button>
            <button class="btn btn-danger btn-sm" style="background:#ef4444;border:none" onclick="openRejectKehadiran(${item.id})" title="Tolak">
              Tolak
            </button>
          `;
        } else {
          aksiBtns = '<span style="font-size:12px;color:var(--text-muted)">-</span>';
        }
        
        let tanggal = item.tanggal || (item.created_at ? item.created_at.split('T')[0] : '-');

        html += `
          <tr>
            <td><div style="font-weight:600">${item.mahasiswa?.name || 'Data Terhapus'}</div></td>
            <td>${item.mahasiswa?.kelompok || '-'}</td>
            <td>${tanggal}</td>
            <td>${item.check_in_time || '-'}</td>
            <td>${item.check_out_time || '-'}</td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${item.keterangan || ''}">${item.keterangan || '-'}</td>
            <td>
              ${item.bukti ? `<button class="btn btn-ghost btn-sm" onclick="viewBukti('/storage/${item.bukti}')">Lihat Bukti</button>` : '-'}
            </td>
            <td>${statusBadge}</td>
            <td><div style="display:flex;gap:4px">${aksiBtns}</div></td>
          </tr>
        `;
      });
      tbody.innerHTML = html;
    }
  } catch (error) {
    console.error('Error fetching kehadiran:', error);
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--danger);padding:30px">Gagal memuat data</td></tr>';
  }
}

function filterKehadiranSubmissions() { loadKehadiranSubmissions(); }

function resetKehadiranFilter() {
  if(document.getElementById('kehadiran-search')) document.getElementById('kehadiran-search').value = '';
  if(document.getElementById('kehadiran-filter-kelompok')) document.getElementById('kehadiran-filter-kelompok').value = '';
  if(document.getElementById('kehadiran-filter-status')) document.getElementById('kehadiran-filter-status').value = '';
  loadKehadiranSubmissions();
}

async function approveKehadiran(id) {
  if (!confirm('Anda yakin ingin menyetujui pengajuan kehadiran ini? Data absensi akan otomatis diperbarui.')) return;
  try {
    const result = await apiFetch(`/admin/kehadiran-submissions/${id}/verify`, {
      method: 'POST',
      body: JSON.stringify({ status: 'approved' })
    });
    if (result && result.success) {
      loadKehadiranSubmissions();
      toast('Berhasil', 'Kehadiran disetujui');
    } else {
      alert('Gagal: ' + (result?.message || 'Error server'));
    }
  } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan.'); }
}

function openRejectKehadiran(id) {
  document.getElementById('reject-kehadiran-id').value = id;
  document.getElementById('reject-kehadiran-reason').value = '';
  const modal = document.getElementById('modal-reject-kehadiran');
  if(modal) modal.style.display = 'flex';
}

async function confirmRejectKehadiran() {
  const id = document.getElementById('reject-kehadiran-id').value;
  const reason = document.getElementById('reject-kehadiran-reason').value;
  if (!reason.trim()) return alert('Alasan penolakan wajib diisi!');
  
  try {
    const result = await apiFetch(`/admin/kehadiran-submissions/${id}/verify`, {
      method: 'POST',
      body: JSON.stringify({ status: 'rejected', reject_reason: reason })
    });
    if (result && result.success) {
      const modal = document.getElementById('modal-reject-kehadiran');
      if(modal) modal.style.display = 'none';
      loadKehadiranSubmissions();
      toast('Berhasil', 'Kehadiran ditolak');
    } else {
      alert('Gagal: ' + (result?.message || 'Error server'));
    }
  } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan.'); }
}

// ─── USER MANAGEMENT ────────────────────────────────────────────────────────
async function loadUsers() {
  const search = document.getElementById('user-search')?.value || '';
  const role = document.getElementById('user-filter-role')?.value || '';
  const status = document.getElementById('user-filter-status')?.value || '';

  const tbody = document.getElementById('users-tbody');
  if (!tbody) return;

  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">Memuat data...</td></tr>';

  try {
    const queryParams = new URLSearchParams({ search, role, status });
    const result = await apiFetch('/admin/users?' + queryParams.toString());

    if (result && result.success) {
      document.getElementById('stat-admin-count').textContent = result.stats.admin;
      document.getElementById('stat-timdis-count').textContent = result.stats.timdis;
      document.getElementById('stat-mahasiswa-count').textContent = result.stats.mahasiswa;
      document.getElementById('stat-total-users').textContent = result.stats.total;

      if (result.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">Tidak ada user ditemukan</td></tr>';
        return;
      }

      let html = '';
      result.data.forEach(user => {
        const initials = (user.full_name || 'U').split(' ').map(w => w[0] || '').join('').slice(0, 2).toUpperCase();
        const colors = ['#4f7cff', '#22d3a0', '#f5a623', '#ff6b6b', '#a78bfa'];
        const colorIndex = user.id % colors.length;
        const color = colors[colorIndex];
        
        let statusBadge = user.is_active ? '<span class="badge badge-success" style="background:#dcfce7;color:#166534;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600">Aktif</span>' : '<span class="badge badge-danger" style="background:#fee2e2;color:#991b1b;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600">Nonaktif</span>';
        let lastLogin = user.last_login ? user.last_login.split('T').join(' ').substring(0, 16) : '-';
        let mhsId = user.mahasiswa_id || '-';

        let toggleBtn = user.is_active 
          ? `<button class="btn btn-danger btn-sm" onclick="toggleUserStatus(${user.id}, false)" title="Nonaktifkan">
              <span class="material-symbols-outlined" style="font-size:16px">block</span>
            </button>`
          : `<button class="btn btn-primary btn-sm" onclick="toggleUserStatus(${user.id}, true)" title="Aktifkan">
              <span class="material-symbols-outlined" style="font-size:16px">check_circle</span>
            </button>`;

        let deleteBtn = `
            <button class="btn btn-danger btn-sm" onclick="deleteUser(${user.id})" title="Hapus">
              <span class="material-symbols-outlined" style="font-size:16px">delete</span>
            </button>
        `;

        html += `
          <tr>
            <td>
              <div class="mahasiswa-cell" style="display:flex;gap:12px;align-items:center">
                <div class="avatar" style="background:${color}22;color:${color};width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%;font-weight:600;font-size:14px">${initials}</div>
                <div>
                  <div class="mhs-name" style="font-weight:600">${user.full_name}</div>
                  <div class="mhs-dept" style="font-family:var(--mono);font-size:12px;color:var(--text-muted)">@${user.username}</div>
                </div>
              </div>
            </td>
            <td>${user.email || '-'}</td>
            <td><span style="text-transform:capitalize">${user.role}</span></td>
            <td><span style="font-family:var(--font-mono);font-size:12px">${mhsId}</span></td>
            <td>${statusBadge}</td>
            <td>${lastLogin}</td>
            <td>
              <div style="display:flex;gap:4px">
                <button class="btn btn-ghost btn-sm" onclick='editUser(${JSON.stringify(user).replace(/'/g, "&apos;").replace(/"/g, "&quot;")})' title="Edit">
                  <span class="material-symbols-outlined" style="font-size:16px">edit</span>
                </button>
                <button class="btn btn-ghost btn-sm" onclick="openResetPassword(${user.id}, '${user.username}')" title="Reset Password">
                  <span class="material-symbols-outlined" style="font-size:16px">lock_reset</span>
                </button>
                ${toggleBtn}
                ${deleteBtn}
              </div>
            </td>
          </tr>
        `;
      });
      tbody.innerHTML = html;
    }
  } catch (error) {
    console.error('Error fetching users:', error);
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--danger);padding:30px">Gagal memuat data</td></tr>';
  }
}

function filterUsers() { loadUsers(); }

function resetUserFilter() {
  if(document.getElementById('user-search')) document.getElementById('user-search').value = '';
  if(document.getElementById('user-filter-role')) document.getElementById('user-filter-role').value = '';
  if(document.getElementById('user-filter-status')) document.getElementById('user-filter-status').value = '';
  loadUsers();
}

async function loadMahasiswaOptions() {
  const select = document.getElementById('user-mahasiswa-id');
  select.innerHTML = '<option value="">Memuat...</option>';
  try {
    const result = await apiFetch('/admin/mahasiswa-options');
    if (result && result.success) {
      let html = '<option value="">-- Pilih Mahasiswa --</option>';
      result.data.forEach(mhs => {
        html += `<option value="${mhs.id}">${mhs.id} - ${mhs.name} (${mhs.kelompok || '-'})</option>`;
      });
      select.innerHTML = html;
    }
  } catch (error) {
    console.error('Error fetching mhs options:', error);
  }
}

function toggleMahasiswaField() {
  const role = document.getElementById('user-role').value;
  const mhsRow = document.getElementById('mahasiswa-id-row');
  const mhsSelect = document.getElementById('user-mahasiswa-id');
  if (role === 'mahasiswa') {
    mhsRow.style.display = 'block';
    mhsSelect.required = true;
    if(mhsSelect.options.length <= 1) loadMahasiswaOptions();
  } else {
    mhsRow.style.display = 'none';
    mhsSelect.required = false;
    mhsSelect.value = '';
  }
}

function openAddUserModal() {
  document.getElementById('user-id').value = '';
  document.getElementById('user-form').reset();
  document.getElementById('modal-user-title').textContent = 'Tambah User';
  document.getElementById('password-row').style.display = 'block';
  document.getElementById('user-password').required = true;
  toggleMahasiswaField();
  const modal = document.getElementById('modal-user');
  if(modal) modal.style.display = 'flex';
}

function editUser(user) {
  document.getElementById('user-id').value = user.id;
  document.getElementById('user-username').value = user.username;
  document.getElementById('user-fullname').value = user.full_name;
  document.getElementById('user-email').value = user.email || '';
  document.getElementById('user-role').value = user.role;
  
  document.getElementById('modal-user-title').textContent = 'Edit User';
  document.getElementById('password-row').style.display = 'none';
  document.getElementById('user-password').required = false;
  
  toggleMahasiswaField();
  
  if (user.role === 'mahasiswa') {
    const mhsSelect = document.getElementById('user-mahasiswa-id');
    const optionExists = Array.from(mhsSelect.options).some(opt => opt.value === user.mahasiswa_id);
    if (!optionExists && user.mahasiswa_id) {
      mhsSelect.innerHTML += `<option value="${user.mahasiswa_id}">${user.mahasiswa_id} (Saat ini)</option>`;
    }
    mhsSelect.value = user.mahasiswa_id;
  }
  
  const modal = document.getElementById('modal-user');
  if(modal) modal.style.display = 'flex';
}

async function submitUser(event) {
  event.preventDefault();
  const id = document.getElementById('user-id').value;
  const data = {
    username: document.getElementById('user-username').value,
    full_name: document.getElementById('user-fullname').value,
    email: document.getElementById('user-email').value,
    role: document.getElementById('user-role').value,
    mahasiswa_id: document.getElementById('user-mahasiswa-id').value
  };
  if (!id) data.password = document.getElementById('user-password').value;
  
  const url = id ? `/admin/users/${id}` : '/admin/users';
  const method = id ? 'PUT' : 'POST';
  
  try {
    const result = await apiFetch(url, {
      method: method,
      body: JSON.stringify(data)
    });
    if (result && result.success) {
      closeModal('modal-user');
      loadUsers();
      toast('Berhasil', 'User berhasil disimpan');
    } else {
      alert(result?.message || 'Gagal menyimpan user');
    }
  } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan'); }
}

function openResetPassword(id, username) {
  document.getElementById('reset-user-id').value = id;
  document.getElementById('reset-username').textContent = username;
  document.getElementById('reset-password-form').reset();
  const modal = document.getElementById('modal-reset-password');
  if(modal) modal.style.display = 'flex';
}

async function submitResetPassword(event) {
  event.preventDefault();
  const id = document.getElementById('reset-user-id').value;
  const password = document.getElementById('reset-new-password').value;
  const confirm = document.getElementById('reset-confirm-password').value;
  
  if (password !== confirm) return alert('Konfirmasi password tidak cocok!');
  
  try {
    const result = await apiFetch(`/admin/users/${id}/reset-password`, {
      method: 'POST',
      body: JSON.stringify({ password: password, password_confirmation: confirm })
    });
    if (result && result.success) {
      closeModal('modal-reset-password');
      alert('Password berhasil direset!');
    } else {
      alert(result?.message || 'Gagal mereset password');
    }
  } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan'); }
}

async function toggleUserStatus(id, activate) {
  const action = activate ? 'Aktifkan' : 'Nonaktifkan';
  if (!confirm(`Anda yakin ingin ${action.toLowerCase()} user ini?`)) return;
  try {
    const result = await apiFetch(`/admin/users/${id}/toggle-status`, {
      method: 'POST',
    });
    if (result && result.success) {
      loadUsers();
      toast('Berhasil', `Status user diubah`);
    } else {
      alert(result?.message || 'Gagal mengubah status');
    }
  } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan.'); }
}

async function deleteUser(id) {
  if (!confirm('Anda yakin ingin menghapus user ini secara permanen?')) return;
  try {
    const result = await apiFetch(`/admin/users/${id}`, {
      method: 'DELETE',
    });
    if (result && result.success) {
      loadUsers();
      toast('Berhasil', 'User dihapus');
    } else {
      alert(result?.message || 'Gagal menghapus user');
    }
  } catch (error) { console.error('Error:', error); alert('Terjadi kesalahan.'); }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if(modal) modal.classList.remove('show');
}

// ─── INITIALIZATION ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Hanya jalankan jika ID tabelnya ditemukan di halaman ini
  if(document.getElementById('izin-submissions-table-body')) {
      loadIzinSubmissions();
  }
  if(document.getElementById('kehadiran-submissions-table-body')) {
      loadKehadiranSubmissions();
  }
  if(document.getElementById('users-tbody')) {
      loadUsers();
  }

  // ─── RENDER ABSENSI LENGKAP ────────────────────────────────────────────────
async function renderFullAttendance() {
  const tableBody = document.getElementById("full-att-table-body"); // Pastikan ID ini ada di HTML Anda
  if (!tableBody) return;

  const dateFilter = document.getElementById("att-date-filter")?.value || new Date().toISOString().split("T")[0];
  
  tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Memuat data...</td></tr>';

  try {
    // Sesuaikan endpoint dengan route Laravel Anda
    const result = await apiFetch(`/admin/attendance-data?date=${dateFilter}`);
    
    if (result && result.success) {
      if (result.data.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Tidak ada data ditemukan</td></tr>';
        return;
      }

      let html = '';
      result.data.forEach(item => {
        html += `
          <tr>
            <td>${item.mahasiswa_name}</td>
            <td>${item.kelompok}</td>
            <td>${item.check_in || '-'}</td>
            <td>${item.check_out || '-'}</td>
            <td>${item.status}</td>
          </tr>
        `;
      });
      tableBody.innerHTML = html;
    } else {
      tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:red;">Gagal memuat data</td></tr>';
    }
  } catch (error) {
    console.error('Error fetching attendance:', error);
    tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:red;">Error saat mengambil data</td></tr>';
  }
}
});