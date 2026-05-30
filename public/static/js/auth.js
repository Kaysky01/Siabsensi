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
          'Content-Type': 'application/json'
        },
        credentials: 'include' // Ini kunci utamanya: membawa Session dari browser
      });
      
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
      window.location.href = '/login';
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
    const headers = {
      ...(options.headers || {})
    };
    
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