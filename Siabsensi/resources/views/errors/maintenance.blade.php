<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistem Dalam Pemeliharaan — SIABSEN PKKMB</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
  
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: #0f172a;
      color: #f8fafc;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      position: relative;
      overflow-x: hidden;
    }

    /* Ambient Background Glows */
    .bg-glow-1 {
      position: absolute;
      top: -100px;
      left: 50%;
      transform: translateX(-50%);
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(37, 99, 235, 0.25) 0%, rgba(15, 23, 42, 0) 70%);
      pointer-events: none;
    }

    .bg-glow-2 {
      position: absolute;
      bottom: -150px;
      right: 10%;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, rgba(15, 23, 42, 0) 70%);
      pointer-events: none;
    }

    .maintenance-card {
      background: rgba(30, 41, 59, 0.7);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 24px;
      padding: 48px 40px;
      max-width: 560px;
      width: 100%;
      text-align: center;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
      position: relative;
      z-index: 10;
    }

    .icon-wrapper {
      width: 88px;
      height: 88px;
      margin: 0 auto 24px auto;
      background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
      border-radius: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 12px 28px -6px rgba(37, 99, 235, 0.4);
      position: relative;
    }

    .icon-wrapper .material-symbols-outlined {
      font-size: 44px;
      color: #ffffff;
      animation: gear-spin 12s linear infinite;
    }

    @keyframes gear-spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #fca5a5;
      padding: 6px 16px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 20px;
    }

    .pulse-dot-red {
      width: 8px;
      height: 8px;
      background: #ef4444;
      border-radius: 50%;
      box-shadow: 0 0 10px #ef4444;
      animation: pulse-red 1.5s infinite;
    }

    @keyframes pulse-red {
      0% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.4; transform: scale(1.3); }
      100% { opacity: 1; transform: scale(1); }
    }

    .title {
      font-size: 28px;
      font-weight: 800;
      color: #ffffff;
      margin-bottom: 12px;
      letter-spacing: -0.5px;
    }

    .subtitle {
      font-size: 15px;
      color: #94a3b8;
      line-height: 1.6;
      margin-bottom: 32px;
    }

    .info-box {
      background: rgba(15, 23, 42, 0.6);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px;
      padding: 16px 20px;
      margin-bottom: 32px;
      font-size: 13px;
      color: #cbd5e1;
      text-align: left;
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .info-box .material-symbols-outlined {
      color: #38bdf8;
      font-size: 22px;
      flex-shrink: 0;
      margin-top: 2px;
    }

    .action-group {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .btn-admin {
      background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
      color: #ffffff;
      font-size: 14px;
      font-weight: 700;
      padding: 14px 24px;
      border-radius: 12px;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
      transition: all 0.2s ease;
    }

    .btn-admin:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4);
      background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    }

    .btn-refresh {
      background: transparent;
      border: 1px solid rgba(255, 255, 255, 0.15);
      color: #cbd5e1;
      font-size: 14px;
      font-weight: 600;
      padding: 12px 24px;
      border-radius: 12px;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .btn-refresh:hover {
      background: rgba(255, 255, 255, 0.05);
      color: #ffffff;
    }

    .footer-note {
      margin-top: 28px;
      font-size: 12px;
      color: #64748b;
    }

    @media (max-width: 480px) {
      .maintenance-card {
        padding: 32px 24px;
      }
      .title {
        font-size: 24px;
      }
    }
  </style>
</head>
<body>
  <div class="bg-glow-1"></div>
  <div class="bg-glow-2"></div>

  <div class="maintenance-card">
    <div class="icon-wrapper">
      <span class="material-symbols-outlined">settings</span>
    </div>

    <div class="status-badge">
      <span class="pulse-dot-red"></span> MAINTENANCE MODE ACTIVE
    </div>

    <h1 class="title">Sistem Dalam Pemeliharaan</h1>
    <p class="subtitle">
      Aplikasi <strong>SIABSEN PKKMB</strong> saat ini sedang dalam perbaikan dan pemeliharaan server berkala untuk meningkatkan stabilitas sistem.
    </p>

    <div class="info-box">
      <span class="material-symbols-outlined">info</span>
      <div>
        <strong>Informasi Layanan:</strong>
        <div>Akses untuk mahasiswa, garda, timdis, & tim acara dinonaktifkan sementara. Administrator tetap dapat login untuk mengelola sistem.</div>
      </div>
    </div>

    <div class="action-group">
      <a href="{{ route('login') }}" class="btn-admin">
        <span class="material-symbols-outlined">admin_panel_settings</span>
        Login Administrator
      </a>
      <button onclick="window.location.reload()" class="btn-refresh">
        <span class="material-symbols-outlined">refresh</span>
        Cek Status Ulang
      </button>
    </div>

    <div class="footer-note">
      &copy; {{ date('Y') }} SIABSEN PKKMB — Polinela. All rights reserved.
    </div>
  </div>
</body>
</html>
