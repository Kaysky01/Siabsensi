<?php
$lines = file('e:/laragon/www/YOLO-Siabsensi/laravel/resources/views/admin/dashboard.blade.php');
$top = implode('', array_slice($lines, 0, 172));
$bottom = implode('', array_slice($lines, 1032));
$layout = $top . "      <div class=\"content-container\">\n        @yield('content')\n      </div>\n" . $bottom;
file_put_contents('e:/laragon/www/YOLO-Siabsensi/laravel/resources/views/layouts/admin.blade.php', $layout);
echo "Layout created.";
