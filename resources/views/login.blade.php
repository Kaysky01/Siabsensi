<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — SIABSEN</title>
  <link rel="icon" type="image/png" href="{{ asset('static/img/logo.png') }}">
  <link rel="stylesheet" href="{{ asset('static/css/style.css') }}">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300;400;500;600;700" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('static/css/login.css') }}">
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

      <form id="login-form" action="{{ route('auth') }}" method="POST">
        @csrf
        <div class="form-group">
          <label class="form-label">Username</label>
          <div class="form-input-wrapper">
            <span class="material-symbols-outlined">person</span>
            <input 
              type="text" 
              name="username" id="username" 
              class="form-input @error('username') error @enderror" 
              placeholder="Masukkan username"
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
              placeholder="Masukkan password"
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
        @if(session('error'))
            <div style="color: red; margin-bottom: 15px; padding: 10px; border: 1px solid red; background-color: #fdd;">
                {{ session('error') }}
            </div>
        @endif

        <div class="remember-me">
          <input type="checkbox" name="remember" id="remember-me">
          <label for="remember-me">Ingat saya</label>
        </div>

        <button type="submit" class="btn-login" id="login-btn">
          <span id="login-text">Masuk</span>
        </button>
      </form>

      <div class="login-footer">
        <div class="login-footer-text">
          SIABSEN v2.5 — Sistem Absensi Cerdas
        </div>
      </div>
    </div>
  </div>
</body>
</html>