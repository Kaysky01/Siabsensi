<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — SIABSEN</title>
  <link rel="icon" type="image/png" href="{{ asset('static/img/logo.png') }}">
  @vite('resources/css/admin.css')
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300;400;500;600;700" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('static/css/login.css') }}">
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <div class="login-logo">
          <img src="{{ asset('static/img/logo.png') }}?v={{ time() }}" alt="Logo">
          <div class="login-logo-text">SIABSEN</div>
        </div>
        <div class="login-title">Selamat Datang</div>
        <div class="login-subtitle">Masuk ke sistem absensi cerdas</div>
      </div>

      @if(auth()->check())
      <div style="margin-bottom:16px;padding:12px 14px;border-radius:10px;background:#fff7ed;border:1px solid #fdba74;color:#9a3412;font-size:13px;line-height:1.6">
        Anda sedang login sebagai <strong>{{ auth()->user()->full_name ?? auth()->user()->username }}</strong>.
        Jika Anda masuk dengan akun lain, sesi akun saat ini akan otomatis diganti.
      </div>
      @endif

      <form id="login-form" action="{{ route('auth') }}" method="POST">
        @csrf
        <div class="form-group">
          <label class="form-label">Username / Nomor Registrasi</label>
          <div class="form-input-wrapper">
            <span class="material-symbols-outlined">person</span>
            <input 
              type="text" 
              name="username" id="username" 
              class="form-input @error('username') error @enderror" 
              placeholder="Username atau Nomor Registrasi"
              value="{{ old('username') }}" required
              autocomplete="username"
            >
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="form-input-wrapper">
            <span class="material-symbols-outlined">lock</span>
            <input 
              type="password" 
              name="password" id="password" 
              class="form-input" 
              placeholder="Password (mahasiswa: tanggal lahir ddmmyyyy)"
              required
              autocomplete="current-password"
            >
          </div>
        </div>


        @error('username')
            <div class="error-message show" id="error-message" style="display: block;">
                {{ $message }}
            </div>
        @enderror
        @error('g-recaptcha-response')
            <div class="error-message show" style="display: block;">
                {{ $message }}
            </div>
        @enderror
        @if(session('error'))
            <div style="color: red; margin-bottom: 15px; padding: 10px; border: 1px solid red; background-color: #fdd;">
                {{ session('error') }}
            </div>
        @endif

        <div class="remember-me">
          <input type="checkbox" name="remember" id="remember-me">
          <label for="remember-me">Ingat saya</label>
          <span id="toggle-password" style="margin-left:auto;display:inline-flex;align-items:center;gap:4px;cursor:pointer;user-select:none;font-size:13px;color:var(--muted,#888)">
            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
            Lihat Password
          </span>
        </div>

        <!-- reCAPTCHA Widget -->
        <div class="recaptcha-wrapper">
          <div class="g-recaptcha" 
               data-sitekey="{{ config('recaptcha.site_key') }}"
               data-theme="light"
               data-size="normal">
          </div>
          <div class="recaptcha-error" id="recaptcha-error" style="display: none;">
            <span class="material-symbols-outlined">error</span>
            Silakan centang kotak "Saya bukan robot" terlebih dahulu.
          </div>
        </div>

        <button type="submit" class="btn-login" id="login-btn">
          <span id="login-text">Masuk</span>
        </button>
      </form>

      <script>
        // Client-side validation untuk reCAPTCHA
        document.getElementById('login-form')?.addEventListener('submit', function(e) {
            // Check if form is disabled (during lockout)
            const loginButton = document.getElementById('login-btn');
            if (loginButton.disabled) {
                e.preventDefault();
                return false;
            }

            // Check reCAPTCHA
            if (typeof grecaptcha !== 'undefined') {
                const recaptchaResponse = grecaptcha.getResponse();
                const recaptchaError = document.getElementById('recaptcha-error');
                
                if (!recaptchaResponse) {
                    e.preventDefault();
                    
                    // Show inline error instead of alert
                    if (recaptchaError) {
                        recaptchaError.style.display = 'flex';
                        
                        // Scroll to reCAPTCHA
                        document.querySelector('.recaptcha-wrapper').scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        
                        // Hide error after 5 seconds
                        setTimeout(function() {
                            recaptchaError.style.display = 'none';
                        }, 5000);
                    }
                    
                    return false;
                } else {
                    // Hide error if reCAPTCHA checked
                    if (recaptchaError) {
                        recaptchaError.style.display = 'none';
                    }
                }
            }
            
            // Show loading state
            const loginText = document.getElementById('login-text');
            loginButton.disabled = true;
            loginText.textContent = 'Memproses...';
        });

        // Toggle password visibility
        document.getElementById('toggle-password')?.addEventListener('click', function() {
          const pw = document.getElementById('password');
          const isPassword = pw.type === 'password';
          pw.type = isPassword ? 'text' : 'password';
          this.querySelector('.material-symbols-outlined').textContent = isPassword ? 'visibility_off' : 'visibility';
        });
      </script>

      @if(session('lockout_seconds'))
      <script>
        document.addEventListener('DOMContentLoaded', function() {
            let remainingSeconds = {{ session('lockout_seconds') }};
            const errorMessage = document.querySelector('.error-message');
            const loginButton = document.getElementById('login-btn');
            const loginForm = document.getElementById('login-form');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            
            if (errorMessage && remainingSeconds > 0) {
                // Disable form elements
                loginButton.disabled = true;
                loginButton.style.opacity = '0.5';
                loginButton.style.cursor = 'not-allowed';
                usernameInput.disabled = true;
                passwordInput.disabled = true;
                
                // Disable reCAPTCHA if loaded
                if (typeof grecaptcha !== 'undefined') {
                    const recaptchaElement = document.querySelector('.g-recaptcha');
                    if (recaptchaElement) {
                        recaptchaElement.style.pointerEvents = 'none';
                        recaptchaElement.style.opacity = '0.5';
                    }
                }
                
                // Countdown timer
                const countdownInterval = setInterval(function() {
                    remainingSeconds--;
                    
                    if (remainingSeconds > 0) {
                        errorMessage.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px">lock_clock</span> Terlalu banyak percobaan login gagal. Tunggu <strong>' + remainingSeconds + ' detik</strong> lagi.';
                    } else {
                        clearInterval(countdownInterval);
                        errorMessage.style.display = 'none';
                        
                        // Re-enable form
                        loginButton.disabled = false;
                        loginButton.style.opacity = '1';
                        loginButton.style.cursor = 'pointer';
                        usernameInput.disabled = false;
                        passwordInput.disabled = false;
                        
                        // Re-enable reCAPTCHA
                        if (typeof grecaptcha !== 'undefined') {
                            const recaptchaElement = document.querySelector('.g-recaptcha');
                            if (recaptchaElement) {
                                recaptchaElement.style.pointerEvents = 'auto';
                                recaptchaElement.style.opacity = '1';
                            }
                        }
                    }
                }, 1000);
            }
        });
      </script>
      @endif

      <div class="login-footer">
        <div class="login-footer-text">
          SIABSEN v2.5 — Sistem Absensi Cerdas
        </div>
      </div>
    </div>
  </div>
</body>
</html>
