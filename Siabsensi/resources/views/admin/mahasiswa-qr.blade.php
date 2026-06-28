<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - {{ $mahasiswa->name }}</title>
    <style>
        body { font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f1f5f9; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-align: center; }
        .name { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .nim { font-size: 14px; color: #64748b; margin-bottom: 20px; }
        .qr-container { padding: 16px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; display: inline-block; }
    </style>
</head>
<body>
    <div class="card">
        <div class="name">{{ $mahasiswa->name }}</div>
        <div class="nim">{{ $mahasiswa->id }}</div>
        <div class="qr-container">
            {!! $qrImage !!}
        </div>
        <div style="margin-top:20px;font-size:12px;color:#94a3b8">Scan QR Code ini untuk Absensi</div>
    </div>
</body>
</html>
