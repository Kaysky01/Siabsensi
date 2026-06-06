<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Monitor Absensi</title>
    <link rel="stylesheet" href="/static/css/monitor.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined">
</head>
<body>
    <div class="monitor-layout">
        <!-- Left: Camera Section -->
        <div class="camera-section">
            <!-- Header -->
            <div class="header">
                <div class="logo">
                    <img src="/static/img/logo.png" alt="Logo" onerror="this.style.display='none'">
                    <div class="logo-text">
                        <div class="logo-title">SIABSEN</div>
                        <div class="logo-sub">Sistem Absensi Cerdas</div>
                    </div>
                </div>
                <div class="header-center">
                    <div class="clock" id="clock">--:--:--</div>
                    <div id="date-label">...</div>
                </div>
                <div class="header-right">
                    <div class="status-badge">
                        <div class="status-dot"></div>
                        LIVE
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value" id="stat-total">0</div>
                        <div class="stat-label">Total Hadir</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 1 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value" id="stat-masih">0</div>
                        <div class="stat-label">Masih di Lokasi</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon amber">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 3h6v6H3z"></path>
                            <path d="M15 3h6v6h-6z"></path>
                            <path d="M3 15h6v6H3z"></path>
                            <path d="M15 15h3"></path>
                            <path d="M18 18h3"></path>
                            <path d="M15 21h6"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value" id="stat-hadir">0</div>
                        <div class="stat-label">Total Scan</div>
                    </div>
                </div>
            </div>

            <!-- Video Container -->
            <div class="video-container">
                <video id="camera-video" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;"></video>
                <div class="video-overlay">
                    <div class="video-label">
                        <div class="rec-dot"></div>
                        LIVE FEED
                    </div>
                    <div class="scan-frame">
                        <span></span>
                        <div class="scan-line"></div>
                    </div>
                    <div class="scan-hint">Scan QR Code untuk absensi</div>
                </div>
            </div>
        </div>

        <!-- Right: Log Section -->
        <div class="log-section">
            <div class="log-header">
                <div class="log-header-top">
                    <h2>Log Absensi</h2>
                    <span class="log-count" id="log-count">0</span>
                </div>
                <p>Hari ini</p>
            </div>
            
            <div class="cooldown-notice" id="cooldown-notice">
                <strong>⚠️ Catatan:</strong> Mahasiswa yang baru check-in harus menunggu 1 jam sebelum bisa check-out.
            </div>

            <div class="log-list" id="recent-scans">
                <div class="empty-state">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <path d="M14 14h.01M14 17h3M17 14h3M20 17v3M17 20h3"/>
                    </svg>
                    <p>Belum ada absensi hari ini</p>
                </div>
            </div>

            <div class="log-footer">
                <div class="footer-info">SIABSEN Monitor v1.0</div>
                <div class="polling-indicator">
                    <span id="polling-dot"></span>
                    <span id="last-update">Diperbarui: --:--:--</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Audio Alert -->
    <div id="audio-alert" class="audio-alert">
        <div class="alert-content">
            <span class="material-symbols-outlined">volume_up</span>
            <p>Klik OK untuk mengaktifkan suara notifikasi absensi</p>
            <button onclick="aktifkanSuara()">OK</button>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast-notification" class="toast-notification">
        <div class="toast-content">
            <span class="material-symbols-outlined toast-icon">check_circle</span>
            <div class="toast-message">
                <div class="toast-title">Absensi Berhasil</div>
                <div class="toast-text" id="toast-text">Mahasiswa berhasil check-in</div>
            </div>
        </div>
    </div>

    <style>
        .audio-alert {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .alert-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            max-width: 400px;
        }
        .alert-content span {
            font-size: 48px;
            color: #3b82f6;
        }
        .alert-content p {
            margin: 15px 0;
            font-size: 16px;
        }
        .alert-content button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }

        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }
        .toast-notification.show {
            opacity: 1;
            transform: translateX(0);
        }
        .toast-content {
            background: white;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
        }
        .toast-icon {
            font-size: 24px;
            color: #10b981;
        }
        .toast-message {
            flex: 1;
        }
        .toast-title {
            font-weight: 600;
            font-size: 14px;
            color: #1f2937;
        }
        .toast-text {
            font-size: 13px;
            color: #6b7280;
            margin-top: 2px;
        }
    </style>

    <script src="/static/js/monitor.js"></script>
</body>
</html>
