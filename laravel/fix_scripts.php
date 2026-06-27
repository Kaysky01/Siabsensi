<?php
$mapping = [
    'dashboard' => "currentPage = 'dashboard';\nloadDashboard();",
    'settings' => "currentPage = 'settings';\nloadSettings();",
    'attendance' => "currentPage = 'attendance';\nloadFullAttendance();",
    'users' => "currentPage = 'users';\nloadUsers();",
    'mahasiswa' => "currentPage = 'mahasiswa';\nloadMahasiswa();",
    'mahasiswa-saya' => "currentPage = 'mahasiswa-saya';\nloadGardaMahasiswa();",
    'kompi-management' => "currentPage = 'kompi-management';\nloadKompiManagement();",
    'monitoring-kegiatan' => "currentPage = 'monitoring-kegiatan';\nloadMonitoringKegiatanPage();",
    'kelulusan' => "currentPage = 'kelulusan';\nloadKelulusanFilters();",
    'kegiatan' => "currentPage = 'kegiatan';\nloadKegiatan();",
    'izin-timdis' => "currentPage = 'izin-timdis';\nloadIzinSubmissions();",
    'kehadiran-timdis' => "currentPage = 'kehadiran-timdis';\nloadKehadiranSubmissions();",
    'history' => "currentPage = 'history';"
];

foreach ($mapping as $page => $script) {
    $file = "e:/laragon/www/YOLO-Siabsensi/laravel/resources/views/admin/$page.blade.php";
    if (file_exists($file)) {
        $content = file_get_contents($file);
        // Remove old push
        $content = preg_replace("/@push\('scripts'\).*?@endpush/s", "", $content);
        $content = trim($content);
        // Add new push
        $content .= "\n\n@push('scripts')\n<script>\n$script\n</script>\n@endpush\n";
        file_put_contents($file, $content);
        echo "Updated $page.blade.php\n";
    }
}
echo "All scripts updated without DOMContentLoaded.";
