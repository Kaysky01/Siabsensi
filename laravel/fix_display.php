<?php
$dir = 'e:/laragon/www/YOLO-Siabsensi/laravel/resources/views/admin';
$files = glob($dir . '/*.blade.php');
foreach ($files as $file) {
    if (basename($file) == '_old_dashboard.blade.php') continue;
    $content = file_get_contents($file);
    $newContent = str_replace(' style="display:none"', '', $content);
    file_put_contents($file, $newContent);
    echo "Updated " . basename($file) . "\n";
}
echo "All files updated.";
