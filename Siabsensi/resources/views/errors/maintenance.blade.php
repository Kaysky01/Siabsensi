<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistem Dalam Pemeliharaan — SIABSEN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
  
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: #0f172a;
      color: #f8fafc;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .maint-card {
      background: #1e293b;
      border: 1px solid #334155;
      border-radius: 20px;
      padding: 40px 32px;
      max-width: 480px;
      width: 100%;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0,0,0,0.4);
    }
    .maint-icon {
      width: 72px;
      height: 72px;
      margin: 0 auto 20px auto;
      background: #2563eb;
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
    }
    .maint-icon span { font-size: 38px; }
    .maint-badge {
      display: inline-block;
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #fca5a5;
      padding: 4px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 16px;
    }
    .maint-title {
      font-size: 24px;
      font-weight: 800;
      margin-bottom: 10px;
      color: #ffffff;
    }
    .maint-sub {
      font-size: 14px;
      color: #94a3b8;
      line-height: 1.5;
      margin-bottom: 28px;
    }
    .btn-back {
      background: #2563eb;
      color: #ffffff;
      font-weight: 700;
      font-size: 14px;
      padding: 12px 24px;
      border-radius: 12px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s ease;
    }
    .btn-back:hover { background: #1d4ed8; }
  </style>
</head>
<body>
  <div class="maint-card">
    <div class="maint-icon">
      <span class="material-symbols-outlined">build</span>
    </div>

    <div class="maint-badge">MAINTENANCE MODE</div>

    <h1 class="maint-title">Sistem Dalam Pemeliharaan</h1>
    <p class="maint-sub">
      Sistem saat ini sedang dalam pemeliharaan (Maintenance Mode). Akses pengguna selain Administrator dibatasi sementara waktu. Silakan coba beberapa saat lagi.
    </p>

    <a href="{{ route('login') }}" class="btn-back">
      <span class="material-symbols-outlined">arrow_back</span>
      Kembali ke Halaman Login
    </a>
  </div>
</body>
</html>
