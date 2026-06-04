/**
 * Authentication Module
 * Shared authentication logic untuk semua halaman
 */

const AuthModule = (function() {
  const API_BASE = '/api';
  
  /**
   * Validate session dengan server menggunakan Cookies Laravel
   * @returns {Promise<Object|null>} User data if valid, null if invalid
   */
  async function validateSession() {
    try {
      const response = await fetch(API_BASE + '/auth/me', {
        headers: { 
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'include' // Ini kunci utamanya: membawa Session dari browser
      });
      
      // Jika server mengembalikan error (401, 404, 500, dll)
      if (!response.ok) {
        console.error('[AUTH] API /auth/me gagal dengan status:', response.status);
        return null;
      }

      const result = await response.json();
      
      if (result.success) {
        return result.data.mahasiswa || result.data.user || result.data;
      }
      
      return null;
    } catch (error) {
      console.error('Session validation error:', error);
      return null;
    }
  }
  
  /**
   * Check authentication and validate user role
   * @param {string|string[]} allowedRoles - Role(s) yang diizinkan akses
   * @param {Function} onSuccess - Callback jika auth berhasil, menerima user data
   * @param {Function} onFailure - Optional callback jika auth gagal
   */
  async function requireAuth(allowedRoles, onSuccess, onFailure) {
    // Langsung validasi ke server, tanpa mengecek token di localStorage
    const user = await validateSession();
    
    if (!user) {
      console.log('[AUTH] Sesi tidak valid, mengarahkan ke halaman login...');
      if (onFailure) onFailure('invalid_session');
      
      // Cegah infinite loop jika API terus-menerus gagal di hosting
      if (!sessionStorage.getItem('auth_redirect_loop')) {
        sessionStorage.setItem('auth_redirect_loop', 'true');
        window.location.href = '/login';
      } else {
        console.error('[AUTH] Terdeteksi infinite loop. Proses redirect dihentikan.');
        document.body.innerHTML = '<div style="padding:30px;text-align:center;font-family:sans-serif"><h2 style="color:red">Error Autentikasi API</h2><p>Sistem gagal membaca sesi login dari server melalui AJAX.</p><p>Silakan buka <b>Inspect Element (F12) -> Console / Network</b> untuk melihat pesan error aslinya.</p><button onclick="sessionStorage.removeItem(\'auth_redirect_loop\'); window.location.href=\'/logout\'" style="padding:10px 20px;margin-top:20px;cursor:pointer">Paksa Logout</button></div>';
      }
      return;
    }
    
    // Pengecekan Hak Akses (Role)
    if (allowedRoles) {
      const roles = Array.isArray(allowedRoles) ? allowedRoles : [allowedRoles];
      
      // Karena data role mungkin ada di user.role atau sekadar mengecek tipe data
      const userRole = user.role || 'mahasiswa'; 
      
      if (!roles.includes(userRole)) {
        console.log(`[AUTH] Access denied. Required: ${roles.join('|')}, Got: ${userRole}`);
        if (onFailure) onFailure('access_denied');
        
        // Lempar ke dashboard yang sesuai
        if (userRole === 'mahasiswa') {
          window.location.href = '/mahasiswa/dashboard';
        } else {
          window.location.href = '/admin/dashboard';
        }
        return;
      }
    }
    
    // Jika berhasil masuk, hapus flag loop
    sessionStorage.removeItem('auth_redirect_loop');
    
    console.log('[AUTH] Authentication successful:', user.name || user.username);
    
    if (onSuccess) {
      onSuccess(user);
    }
  }
  
  /**
   * API fetch helper
   * Tidak butuh Bearer token lagi karena pakai credentials include
   */
  async function apiFetch(path, options = {}) {
    // Ambil CSRF Token otomatis dari head untuk semua request POST/PUT
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    const headers = {
      'Accept': 'application/json',
      ...(options.headers || {})
    };
    
    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
    
    if (!(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }
    
    try {
      const response = await fetch(API_BASE + path, {
        ...options,
        headers,
        credentials: 'include' // Membawa session Laravel
      });
      
      return response;
    } catch (error) {
      console.error('API fetch error:', error);
      throw error;
    }
  }
  
  /**
   * Logout user
   */
  async function logout() {
    // Kita panggil langsung route logout milik Laravel
    window.location.href = '/logout';
  }
  
  // Public API
  return {
    validateSession,
    requireAuth,
    apiFetch,
    logout
  };
})();

// Export untuk digunakan di module lain
if (typeof module !== 'undefined' && module.exports) {
  module.exports = AuthModule;
}