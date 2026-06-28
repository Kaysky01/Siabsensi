<?php
$content = file_get_contents('e:/laragon/www/YOLO-Siabsensi/laravel/resources/views/admin/_old_dashboard.blade.php');
preg_match_all('/<section id="page-([^"]+)"[^>]*>(.*?)<\/section>/s', $content, $matches);

foreach ($matches[1] as $index => $id) {
    $sectionContent = $matches[0][$index];
    $bladeContent = "@extends('layouts.admin')\n\n@section('content')\n" . $sectionContent . "\n@endsection\n";
    $filename = 'e:/laragon/www/YOLO-Siabsensi/laravel/resources/views/admin/' . $id . '.blade.php';
    file_put_contents($filename, $bladeContent);
    echo "Created $id.blade.php\n";
}
echo "All pages created.";
