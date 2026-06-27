<?php
$lines = file('e:/laragon/www/YOLO-Siabsensi/laravel/resources/views/admin/dashboard.blade.php');
$dashboardContent = implode('', array_slice($lines, 172, 259 - 172 + 1));
$indexPage = "@extends('layouts.admin')\n\n@section('content')\n" . $dashboardContent . "\n@endsection\n";
file_put_contents('e:/laragon/www/YOLO-Siabsensi/laravel/resources/views/admin/index.blade.php', $indexPage);
echo "Index created.";
